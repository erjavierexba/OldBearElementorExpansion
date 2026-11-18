<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Modules\DynamicTags\Module as TagsModule;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Elementor\Utils;

if (!defined('ABSPATH')) { exit; }

/**
 * Video playlist: a list of YouTube, Vimeo and self-hosted videos (grouped by section titles) beside
 * the player, with optional content tabs under each video. Played by assets/js/video-playlist.js.
 */
final class Video_Playlist extends Element {
    public const PRO = 'video-playlist';
    public const ROUTE = '/video-data';
    private const SAMPLE = 'https://www.youtube.com/watch?v=XHOmBV4js_E';

    public function get_title(): string { return 'Lista de vídeos'; }
    public function get_icon(): string { return 'eicon-video-playlist'; }
    public function get_keywords(): array { return ['video','vídeo','playlist','lista','youtube','vimeo','embed']; }
    public function get_script_depends(): array { return ['obx-video-playlist']; }
    public function get_style_depends(): array { return ['obx-frontend', 'elementor-icons']; }
    public function has_widget_inner_wrapper(): bool { return !Elementor::$instance->experiments->is_feature_active('e_optimized_markup'); }

    protected function register_controls(): void {
        $this->playlist_controls();
        $this->inner_tab_controls();
        $this->overlay_controls();
        $this->additional_controls();
        $this->layout_style_controls();
        $this->top_bar_style_controls();
        $this->videos_style_controls();
        $this->sections_style_controls();
        $this->inner_tab_style_controls();
    }

    private static function video_types(): array { return ['youtube', 'hosted', 'vimeo']; }

    private function playlist_controls(): void {
        $this->start_controls_section('section_playlist', ['label'=>'Lista de reproducción']);
        $this->add_control('tabs_direction', ['label'=>'Posición','type'=>Controls_Manager::HIDDEN,'default'=>'vertical','options'=>['horizontal'=>'Horizontal','vertical'=>'Vertical'],'prefix_class'=>'e-tabs-view-']);
        $this->add_control('playlist_title', ['label'=>'Nombre de la lista','type'=>Controls_Manager::TEXT,'default'=>'Playlist','placeholder'=>'Playlist','frontend_available'=>true]);
        $this->add_control('playlist_title_tag', ['label'=>'Etiqueta HTML','type'=>Controls_Manager::SELECT,'options'=>self::heading_tags(false),'default'=>'h2','condition'=>['playlist_title!'=>'']]);

        $url_tags = ['active'=>true,'categories'=>[TagsModule::POST_META_CATEGORY, TagsModule::URL_CATEGORY]];
        $videos = ['type'=>self::video_types(),'inner_tab_is_content_visible'=>'show'];
        $repeater = new Repeater();
        $repeater->add_control('type', ['label'=>'Tipo','type'=>Controls_Manager::SELECT,'default'=>'youtube','options'=>['youtube'=>'YouTube','vimeo'=>'Vimeo','hosted'=>'Alojado en el sitio','section'=>'Sección']]);
        $repeater->add_control('youtube_url', ['label'=>'Enlace','type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true],'ai'=>['active'=>false],'placeholder'=>'Pega la URL (YouTube)','label_block'=>true,'condition'=>['type'=>'youtube']]);
        $repeater->add_control('vimeo_url', ['label'=>'Enlace','type'=>Controls_Manager::TEXT,'dynamic'=>$url_tags,'ai'=>['active'=>false],'placeholder'=>'Introduce la URL (Vimeo)','default'=>'https://vimeo.com/235215203','label_block'=>true,'condition'=>['type'=>'vimeo']]);
        $repeater->add_control('vimeo_fetch_data', ['type'=>Controls_Manager::BUTTON,'label_block'=>true,'text'=>'Obtener datos del vídeo','event'=>'elementorPlaylistWidget:fetchVideoData','condition'=>['type'=>['youtube', 'vimeo']]]);
        $repeater->add_control('is_external_url', ['label'=>'URL externa','type'=>Controls_Manager::SWITCHER,'condition'=>['type'=>'hosted']]);
        $repeater->add_control('hosted_url', ['label'=>'Elegir archivo','type'=>Controls_Manager::MEDIA,'dynamic'=>['active'=>true,'categories'=>[TagsModule::MEDIA_CATEGORY]],'media_types'=>['video'],'condition'=>['type'=>'hosted','is_external_url'=>'']]);
        $repeater->add_control('external_url', ['label'=>'URL','type'=>Controls_Manager::URL,'autocomplete'=>false,'options'=>false,'label_block'=>true,'show_label'=>false,'dynamic'=>$url_tags,'media_types'=>['video'],'placeholder'=>'Introduce la URL','condition'=>['type'=>'hosted','is_external_url'=>'yes']]);
        $repeater->add_control('title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true],'default'=>'Title','placeholder'=>'Añade tu texto aquí','label_block'=>true]);
        $repeater->add_control('section_html_tag', ['label'=>'Etiqueta HTML del título','type'=>Controls_Manager::SELECT,'options'=>self::heading_tags(false),'default'=>'h3','condition'=>['type'=>'section']]);
        $repeater->add_control('video_html_tag', ['label'=>'Etiqueta HTML del título','type'=>Controls_Manager::SELECT,'options'=>self::heading_tags(false),'default'=>'h4','condition'=>['type!'=>'section']]);
        $repeater->add_control('duration', ['label'=>'Duración','type'=>Controls_Manager::TEXT,'placeholder'=>'1:05','default'=>'','ai'=>['active'=>false],'condition'=>['type!'=>'section']]);
        $repeater->add_control('thumbnail', ['label'=>'Miniatura','type'=>Controls_Manager::MEDIA,'dynamic'=>['active'=>true],'default'=>['url'=>Utils::get_placeholder_image_src()],'condition'=>['type!'=>'section']]);
        $repeater->add_control('inner_tab_is_content_visible', ['label'=>'Pestañas de contenido','type'=>Controls_Manager::SWITCHER,'label_on'=>'Mostrar','label_off'=>'Ocultar','return_value'=>'show','default'=>'','condition'=>['type'=>self::video_types()]]);
        $repeater->start_controls_tabs('video_tabs');
        $repeater->start_controls_tab('inner_tab_1', ['label'=>'Pestaña n.º 1','condition'=>$videos]);
        $repeater->add_control('inner_tab_content_1', ['label'=>'','type'=>Controls_Manager::WYSIWYG,'default'=>'<p>Añade contenido para cada uno de tus vídeos, como una descripción, una transcripción o enlaces externos. Para añadir, quitar o editar los nombres de las pestañas, ve a Pestañas.</p>','condition'=>$videos]);
        $repeater->end_controls_tab();
        $repeater->start_controls_tab('inner_tab_2', ['label'=>'Pestaña n.º 2','condition'=>$videos]);
        $repeater->add_control('inner_tab_content_2', ['label'=>'','type'=>Controls_Manager::WYSIWYG,'condition'=>$videos]);
        $repeater->end_controls_tab();
        $repeater->end_controls_tabs();

        $sample = ['title'=>'Sample Video','youtube_url'=>self::SAMPLE,'duration'=>'0:16','thumbnail'=>['url'=>'https://img.youtube.com/vi/XHOmBV4js_E/maxresdefault.jpg']];
        $this->add_control('tabs', [
            'label'=>'Elementos de la lista',
            'type'=>Controls_Manager::REPEATER,
            'fields'=>$repeater->get_controls(),
            'default'=>[$sample, $sample, $sample],
            'frontend_available'=>true,
            'title_field'=>'{{{ title }}}',
            'separator'=>'before',
        ]);
        $this->end_controls_section();
    }

    private function inner_tab_controls(): void {
        $this->start_controls_section('section_inner_tab', ['label'=>'Pestañas']);
        $text = ['type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true],'ai'=>['active'=>false]];
        $this->add_control('inner_tab_title_1', ['label'=>'Nombre de la pestaña 1','default'=>'Tab #1','placeholder'=>'Nombre','frontend_available'=>true] + $text);
        $this->add_control('inner_tab_title_2', ['label'=>'Nombre de la pestaña 2','default'=>'Tab #2','placeholder'=>'Nombre','frontend_available'=>true] + $text);
        $this->add_control('inner_tab_is_content_collapsible', ['label'=>'Plegable','type'=>Controls_Manager::SWITCHER,'label_on'=>'Mostrar','label_off'=>'Ocultar','return_value'=>'collapsible','default'=>'','separator'=>'before','frontend_available'=>true]);
        $collapsible = ['inner_tab_is_content_collapsible'=>'collapsible'];
        $this->add_control('inner_tab_label_show_more', ['label'=>'Texto de «Ver más»','placeholder'=>'Show More','default'=>'Show More','condition'=>$collapsible] + $text);
        $this->add_control('inner_tab_label_show_less', ['label'=>'Texto de «Ver menos»','placeholder'=>'Show Less','default'=>'Show Less','condition'=>$collapsible] + $text);
        $this->add_responsive_control('inner_tab_collapsible_height', [
            'label'=>'Altura',
            'type'=>Controls_Manager::SLIDER,
            'size_units'=>['px','em','rem','vh','custom'],
            'default'=>['size'=>54],
            'range'=>['px'=>['min'=>50,'max'=>500],'em'=>['min'=>5,'max'=>50],'rem'=>['min'=>5,'max'=>50]],
            'render_type'=>'template',
            'selectors'=>['{{WRAPPER}} .e-inner-tabs-content-wrapper .collapsible .e-inner-tab-text'=>'height: {{SIZE}}{{UNIT}};'],
            'condition'=>$collapsible,
            'frontend_available'=>true,
        ]);
        $this->end_controls_section();
    }

    private function overlay_controls(): void {
        $this->start_controls_section('section_image_overlay', ['label'=>'Imagen superpuesta']);
        $this->add_control('show_image_overlay', ['label'=>'Imagen superpuesta','type'=>Controls_Manager::SWITCHER,'label_off'=>'Ocultar','label_on'=>'Mostrar']);
        $on = ['show_image_overlay'=>'yes'];
        $this->add_control('image_overlay', ['label'=>'Elegir imagen','type'=>Controls_Manager::MEDIA,'default'=>['url'=>Utils::get_placeholder_image_src()],'dynamic'=>['active'=>true],'condition'=>$on]);
        $this->add_group_control(Group_Control_Image_Size::get_type(), ['name'=>'image_overlay','default'=>'full','condition'=>$on]);
        $this->add_control('show_play_icon', ['label'=>'Icono de reproducción','type'=>Controls_Manager::ICONS,'fa4compatibility'=>'icon','default'=>['value'=>'far fa-play-circle','library'=>'fa-regular'],'label_block'=>false,'skin'=>'inline','condition'=>$on]);
        $this->end_controls_section();
    }

    private function additional_controls(): void {
        $this->start_controls_section('section_additional_options', ['label'=>'Opciones adicionales']);
        $show = ['type'=>Controls_Manager::SWITCHER,'label_on'=>'Mostrar','label_off'=>'Ocultar'];
        $this->add_control('heading_autoplay', ['label'=>'Reproducción automática','type'=>Controls_Manager::HEADING]);
        $this->add_control('autoplay_on_load', ['label'=>'Al cargar','frontend_available'=>true] + $show);
        $this->add_control('autoplay_next', ['label'=>'El siguiente','frontend_available'=>true] + $show);
        $this->add_control('show_watched_indication', ['label'=>'Marcar vistos','type'=>Controls_Manager::SWITCHER,'separator'=>'before','frontend_available'=>true]);
        $this->add_control('show_video_count', ['label'=>'Número de vídeos','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('show_duration', ['label'=>'Duración','default'=>'yes'] + $show);
        $this->add_control('show_thumbnail', ['label'=>'Miniaturas','default'=>'yes'] + $show);
        $icon = ['type'=>Controls_Manager::ICONS,'fa4compatibility'=>'icon','label_block'=>false,'skin'=>'inline'];
        $this->add_control('play_icon', ['label'=>'Icono de reproducción','default'=>['value'=>'fas fa-play-circle','library'=>'fa-solid']] + $icon);
        $this->add_control('watched_icon', ['label'=>'Icono de visto','default'=>['value'=>'fas fa-check-circle','library'=>'fa-solid']] + $icon);
        $this->add_control('lazy_load', ['label'=>'Carga diferida','type'=>Controls_Manager::SWITCHER,'separator'=>'before','frontend_available'=>true]);
        $this->end_controls_section();
    }

    private function layout_style_controls(): void {
        $this->start_controls_section('section_style_layout', ['label'=>'Diseño','tab'=>Controls_Manager::TAB_STYLE]);
        $start = is_rtl() ? 'right' : 'left';
        $end = is_rtl() ? 'left' : 'right';
        $this->add_control('tabs_alignment', [
            'label'=>'Posición del vídeo',
            'type'=>Controls_Manager::CHOOSE,
            'default'=>'end',
            'options'=>['start'=>['title'=>'Inicio','icon'=>"eicon-h-align-$start"],'end'=>['title'=>'Final','icon'=>"eicon-h-align-$end"]],
            'prefix_class'=>'elementor-layout-',
        ]);
        $this->add_responsive_control('layout_height', [
            'label'=>'Altura',
            'type'=>Controls_Manager::SLIDER,
            'size_units'=>['px','em','rem','vh','custom'],
            'range'=>['px'=>['min'=>200,'max'=>1200],'em'=>['min'=>20,'max'=>120],'rem'=>['min'=>20,'max'=>120]],
            'selectors'=>['{{WRAPPER}} .e-tabs .e-tabs-main-area'=>'height: {{SIZE}}{{UNIT}};'],
        ]);
        $this->end_controls_section();
    }

    private function top_bar_style_controls(): void {
        $this->start_controls_section('section_style_top_bar', ['label'=>'Barra superior','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('heading_playlist_name', ['label'=>'Nombre de la lista','type'=>Controls_Manager::HEADING]);
        $this->color('playlist_name_background', 'Fondo', '.e-tabs-wrapper .e-tabs-header', 'background-color');
        $this->color('playlist_name_color', 'Color', '.e-tabs-wrapper .e-tabs-header .e-tabs-title', 'color', ['default'=>'','global'=>['default'=>Global_Colors::COLOR_TEXT]]);
        $this->typography('playlist_name_typography', '.e-tabs-header .e-tabs-title');
        $this->add_control('heading_videos_amount', ['label'=>'Número de vídeos','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control('videos_amount_color', [
            'label'=>'Color',
            'type'=>Controls_Manager::COLOR,
            'default'=>'',
            'selectors'=>[
                '{{WRAPPER}} .e-tabs-wrapper .e-tabs-header .e-tabs-videos-count'=>'color: {{VALUE}};',
                '{{WRAPPER}} .e-tabs-wrapper .e-tabs-header .e-tabs-header-right-side i'=>'color: {{VALUE}};',
                '{{WRAPPER}} .e-tabs-wrapper .e-tabs-header .e-tabs-header-right-side svg'=>'fill: {{VALUE}};',
            ],
            'global'=>['default'=>Global_Colors::COLOR_TEXT],
        ]);
        $this->typography('videos_amount_typography', '.e-tabs-header .e-tabs-videos-count');
        $this->end_controls_section();
    }

    private static function separator_styles(): array {
        return [''=>'Ninguno','solid'=>'Sólido','double'=>'Doble','dotted'=>'Punteado','dashed'=>'Discontinuo','groove'=>'Ranurado'];
    }

    /** Icon size and separator of one state; $item is the state's item selector. */
    private function item_icon_and_separator(string $state, string $item, array $size_selectors): void {
        // $item never holds a comma: self::css() splits selector lists on commas.
        $this->add_responsive_control($state . '_icon_size', [
            'label'=>'Tamaño',
            'type'=>Controls_Manager::SLIDER,
            'size_units'=>['px','em','rem','vw','custom'],
            'range'=>['px'=>['min'=>10,'max'=>30],'em'=>['min'=>1,'max'=>3],'rem'=>['min'=>1,'max'=>3]],
            'selectors'=>$size_selectors,
        ]);
        $this->add_control('heading_separator_' . $state, ['label'=>'Separador','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control($state . '_separator_style', ['label'=>'Estilo','type'=>Controls_Manager::SELECT,'default'=>'','options'=>self::separator_styles(),'selectors'=>self::css($item, 'border-style: {{VALUE}};')]);
        $styled = [$state . '_separator_style!'=>''];
        $this->add_responsive_control($state . '_separator_weight', [
            'label'=>'Grosor',
            'type'=>Controls_Manager::SLIDER,
            'size_units'=>['px','em','rem','vw','custom'],
            'range'=>['px'=>['max'=>10],'em'=>['max'=>1],'rem'=>['max'=>1]],
            'selectors'=>self::css($item, 'border-width: 0 0 {{SIZE}}{{UNIT}} 0;'),
            'condition'=>$styled,
        ]);
        $this->color($state . '_separator_color', 'Color', $item, 'border-color', ['condition'=>$styled]);
    }

    private function videos_style_controls(): void {
        $this->start_controls_section('section_style_videos', ['label'=>'Vídeos','tab'=>Controls_Manager::TAB_STYLE]);
        $text_color = ['global'=>['default'=>Global_Colors::COLOR_TEXT]];
        $text_type = ['global'=>['default'=>Global_Typography::TYPOGRAPHY_TEXT]];
        $this->start_controls_tabs('playlist_tabs');

        $this->start_controls_tab('playlist_tabs_normal', ['label'=>'Normal']);
        $this->add_control('heading_tab_normal', ['label'=>'Elemento','type'=>Controls_Manager::HEADING]);
        $this->add_control('normal_background', [
            'label'=>'Fondo',
            'type'=>Controls_Manager::COLOR,
            'selectors'=>[
                '{{WRAPPER}} .e-tabs-items .e-tab-title:not(:where( .e-active ))'=>'background-color: {{VALUE}};',
                '{{WRAPPER}} .e-tabs-wrapper .e-tabs-items-wrapper'=>'background-color: {{VALUE}};',
                '{{WRAPPER}} .e-tabs-wrapper .e-tabs-items-wrapper .shadow-bottom'=>'background: linear-gradient(180deg, transparent 0%, {{VALUE}} 100%)',
                '{{WRAPPER}} .e-tabs-wrapper .e-tabs-items-wrapper .shadow-top'=>'background: linear-gradient(0deg, transparent 0%, {{VALUE}} 100%);',
            ],
        ]);
        $this->color('normal_color', 'Color', '.e-tabs-items .e-tab-title .e-tab-title-text, .e-tabs-items .e-tab-title .e-tab-title-text button', 'color', $text_color);
        $this->typography('normal_typography', '.e-tabs-items .e-tab-title .e-tab-title-text button', $text_type);
        $this->add_control('heading_duration_normal', ['label'=>'Duración','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('normal_duration_color', 'Color', '.e-tabs-items .e-tab-title .e-tab-duration', 'color', $text_color);
        $this->typography('normal_duration_typography', '.e-tab-title .e-tab-duration');
        $this->add_control('heading_icon_normal', ['label'=>'Icono','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control('normal_icon_color', [
            'label'=>'Color',
            'type'=>Controls_Manager::COLOR,
            'selectors'=>[
                '{{WRAPPER}} .e-tabs-items-wrapper .e-tab-title i'=>'color: {{VALUE}};',
                '{{WRAPPER}} .e-tabs-items-wrapper .e-tab-title svg'=>'fill: {{VALUE}};',
                '{{WRAPPER}} .e-tabs-items-wrapper .e-tab-title svg path'=>'fill: {{VALUE}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), [
            'name'=>'normal_icon_top_text_shadow',
            'fields_options'=>[
                'text_shadow_type'=>['label'=>'Sombra'],
                'text_shadow'=>['selectors'=>[
                    '{{WRAPPER}} .e-tabs-items-wrapper .e-tab-title i'=>'text-shadow: {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}};',
                    '{{WRAPPER}} .e-tabs-items-wrapper .e-tab-title svg'=>'filter: drop-shadow({{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}});',
                ]],
            ],
        ]);
        $this->item_icon_and_separator('normal', '.e-tabs-items-wrapper .e-tab-title', ['{{WRAPPER}}'=>'--playlist-item-icon-size: {{SIZE}}{{UNIT}}']);
        $this->end_controls_tab();

        $active = '.e-tabs-items-wrapper .e-tab-title:where( .e-active, :hover )';
        $this->start_controls_tab('playlist_tabs_active', ['label'=>'Activo']);
        $this->add_control('heading_tab_active', ['label'=>'Elemento','type'=>Controls_Manager::HEADING]);
        // Selectors with :where( a, b ) are written whole: the helpers split selector lists on commas.
        $this->add_control('active_background', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>["{{WRAPPER}} $active"=>'background-color: {{VALUE}};']]);
        $this->add_control('active_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'default'=>'#556068','selectors'=>["{{WRAPPER}} $active .e-tab-title-text"=>'color: {{VALUE}};', "{{WRAPPER}} $active .e-tab-title-text button"=>'color: {{VALUE}};']] + $text_color);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'active_typography','selector'=>"{{WRAPPER}} $active .e-tab-title-text button"] + $text_type);
        $this->add_control('heading_duration_active', ['label'=>'Duración','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control('active_duration_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'default'=>'','selectors'=>["{{WRAPPER}} $active .e-tab-duration"=>'color: {{VALUE}};']] + $text_color);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'active_duration_typography','selector'=>"{{WRAPPER}} $active .e-tab-duration"]);
        $this->add_control('heading_icon_active', ['label'=>'Icono','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control('active_icon_color', [
            'label'=>'Color',
            'type'=>Controls_Manager::COLOR,
            'selectors'=>[
                "{{WRAPPER}} $active i"=>'color: {{VALUE}};',
                "{{WRAPPER}} $active svg"=>'color: {{VALUE}};',
                "{{WRAPPER}} $active svg path"=>'fill: {{VALUE}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), [
            'name'=>'active_icon_top_text_shadow',
            'fields_options'=>['text_shadow_type'=>['label'=>'Sombra']],
            'selector'=>'{{WRAPPER}} .e-tab-title:where( .e-active, :hover ) i, {{WRAPPER}} .e-tab-title:where( .e-active, :hover ) svg',
        ]);
        $this->item_icon_and_separator('active', '.e-tabs-items-wrapper .e-tab-title.e-active', [
            "{{WRAPPER}} $active span i"=>'font-size: {{SIZE}}{{UNIT}}',
            "{{WRAPPER}} $active span svg"=>'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
        ]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();
    }

    private function sections_style_controls(): void {
        $this->start_controls_section('section_style_sections', ['label'=>'Secciones','tab'=>Controls_Manager::TAB_STYLE]);
        $title = '.e-tabs-items-wrapper .e-section-title';
        $this->add_control('heading_section', ['label'=>'Sección','type'=>Controls_Manager::HEADING]);
        $this->color('section_background', 'Fondo', $title, 'background-color');
        $this->color('section_color', 'Color', $title, 'color', ['default'=>'','global'=>['default'=>Global_Colors::COLOR_TEXT]]);
        $this->typography('section_typography', $title);
        $this->add_control('section_border_type', ['label'=>'Tipo de borde','type'=>Controls_Manager::SELECT,'default'=>'solid','options'=>self::separator_styles(),'selectors'=>self::css($title, 'border-style: {{VALUE}};')]);
        $this->add_responsive_control('section_border_width', ['label'=>'Ancho del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'selectors'=>self::css($title, 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};')]);
        $this->color('section_border_color', 'Color del borde', $title, 'border-color');
        $this->end_controls_section();
    }

    private function inner_tab_style_controls(): void {
        $this->start_controls_section('section_inner_tab_style', ['label'=>'Pestañas','tab'=>Controls_Manager::TAB_STYLE]);
        $titles = '{{WRAPPER}} .e-tabs .e-tabs-inner-tabs .e-inner-tabs-wrapper';
        $contents = '{{WRAPPER}} .e-tabs .e-tabs-inner-tabs .e-inner-tabs-content-wrapper';
        $this->add_responsive_control('inner_tab_border_width', [
            'label'=>'Ancho del borde',
            'type'=>Controls_Manager::SLIDER,
            'size_units'=>['px','%','em','rem','vw','custom'],
            'range'=>['px'=>['max'=>20],'em'=>['max'=>2],'rem'=>['max'=>2]],
            'selectors'=>[$titles=>'--inner-tabs-border-height: {{SIZE}}{{UNIT}};', "$titles .e-inner-tab-title.e-inner-tab-active"=>'border-width: 0 0 {{SIZE}}{{UNIT}} 0;'],
        ]);
        $this->add_control('inner_tab_border_color', ['label'=>'Color del borde','type'=>Controls_Manager::COLOR,'selectors'=>[$titles=>'--inner-tabs-border-color: {{VALUE}};']]);
        $this->add_control('inner_tab_background_color', [
            'label'=>'Fondo',
            'type'=>Controls_Manager::COLOR,
            'selectors'=>[
                "$titles .e-inner-tab-active"=>'background-color: {{VALUE}};',
                "$contents .e-inner-tab-content"=>'background-color: {{VALUE}};',
                "$contents .e-inner-tab-title"=>'background-color: {{VALUE}};',
            ],
        ]);
        $text_type = ['global'=>['default'=>Global_Typography::TYPOGRAPHY_TEXT]];
        $this->add_control('heading_inner_tab_title', ['label'=>'Título','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control('inner_tab_title_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>["$titles .e-inner-tab-title a"=>'color: {{VALUE}};']]);
        $this->add_control('inner_tab_active_title_color', [
            'label'=>'Color activo',
            'type'=>Controls_Manager::COLOR,
            'selectors'=>["$titles .e-inner-tab-title.e-inner-tab-active a"=>'color: {{VALUE}};', "$titles .e-inner-tab-title.e-inner-tab-active"=>'border-color: {{VALUE}};'],
        ]);
        $this->typography('inner_tab_title_typography', '.e-tabs-inner-tabs .e-inner-tabs-wrapper .e-inner-tab-title a', $text_type);
        $content = '.e-tabs-inner-tabs .e-inner-tabs-content-wrapper .e-inner-tab-content';
        $this->add_control('heading_inner_tab_content', ['label'=>'Contenido','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('inner_tab_content_color', 'Color', "$content .e-inner-tab-text");
        $this->typography('inner_tab_content_typography', "$content .e-inner-tab-text", $text_type);
        $this->add_responsive_control('inner_tab_content_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'selectors'=>self::css($content, 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};')]);
        $this->add_control('heading_inner_tab_show_more', ['label'=>'Ver más','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['inner_tab_is_content_collapsible'=>'collapsible']]);
        $this->start_controls_tabs('inner_tab_show_more_color');
        $this->start_controls_tab('inner_tab_normal_show_more', ['label'=>'Normal']);
        $this->color('inner_tab_normal_show_more_color', 'Color', "$content button", 'color', ['global'=>['default'=>Global_Colors::COLOR_TEXT]]);
        $this->end_controls_tab();
        $this->start_controls_tab('inner_tab_hover_show_more', ['label'=>'Al pasar el ratón']);
        $this->color('inner_tab_hover_show_more_color', 'Color', "$content button:hover", 'color', ['global'=>['default'=>Global_Colors::COLOR_TEXT]]);
        $this->add_control('inner_tab_hover_show_more_color_transition_duration', ['label'=>'Duración de la transición','type'=>Controls_Manager::SLIDER,'size_units'=>['s','ms','custom'],'default'=>['unit'=>'ms'],'selectors'=>self::css("$content button", 'transition-duration: {{SIZE}}{{UNIT}};')]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->typography('inner_tab_show_more_typography', "$content button", ['global'=>['default'=>Global_Typography::TYPOGRAPHY_ACCENT]]);
        $this->end_controls_section();
    }

    /** The video address of an item, from its type (empty for sections or missing files). */
    private static function item_url(array $item): string {
        switch ($item['type'] ?? '') {
            case 'youtube': return (string) ($item['youtube_url'] ?? '');
            case 'vimeo': return (string) ($item['vimeo_url'] ?? '');
            case 'hosted':
                if ('yes' === ($item['is_external_url'] ?? '')) { return (string) ($item['external_url']['url'] ?? ''); }
                return (string) ($item['hosted_url']['url'] ?? '');
        }
        return '';
    }

    private static function count_videos(array $items): int {
        return count(array_filter($items, static fn($item) => 'section' !== ($item['type'] ?? 'youtube')));
    }

    private static function icon($icon): string {
        if (empty($icon['value'])) { return ''; }
        return (string) Icons_Manager::try_get_icon_html($icon, ['aria-hidden'=>'true']);
    }

    /** Attributes of an item's panel: the same in the player area and under it (inner tabs). */
    private static function panel_attributes(int $n, string $id, array $item, string $url): string {
        $attrs = [
            'id'=>'e-tab-content-' . $id . $n,
            'class'=>'e-tab-content elementor-clearfix',
            'data-tab'=>$n,
            'role'=>'tabpanel',
            'aria-labelledby'=>'e-tab-title-' . $id . $n,
            'tabindex'=>'0',
            'data-video-url'=>$url,
            'data-video-type'=>(string) ($item['type'] ?? ''),
            'data-video-title'=>(string) ($item['title'] ?? ''),
            'data-video-duration'=>(string) ($item['duration'] ?? ''),
        ];
        if (1 !== $n) { $attrs['hidden'] = 'hidden'; }
        return Utils::render_html_attributes($attrs);
    }

    /** The overlay image: the chosen size of the attachment, or the stored address. */
    private static function overlay_image(array $s): string {
        $image = $s['image_overlay'] ?? [];
        if (empty($image['url'])) { return ''; }
        $src = !empty($image['id']) ? Group_Control_Image_Size::get_attachment_image_src($image['id'], 'image_overlay', $s) : '';
        return $src ?: (string) $image['url'];
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $items = is_array($s['tabs'] ?? null) ? array_values($s['tabs']) : [];
        $id = $this->get_id();
        // Pro keeps only 3 digits, which repeats between playlists on one page: the whole number keeps ids unique.
        $short = (string) $this->get_id_int();
        $tag = self::pick_tag($s['playlist_title_tag'] ?? 'h2', 'h2', false);
        $thumbnails = !empty($s['show_thumbnail']);
        $play = '<span class="icon-play">' . self::icon($s['play_icon'] ?? []) . '</span><span class="icon-watched">' . self::icon($s['watched_icon'] ?? []) . '</span>';
        $overlay = !empty($s['show_image_overlay']) && 'yes' !== ($s['autoplay_on_load'] ?? '');
        $collapsible = (string) ($s['inner_tab_is_content_collapsible'] ?? '');
        $tab_titles = [(string) ($s['inner_tab_title_1'] ?? ''), (string) ($s['inner_tab_title_2'] ?? '')];

        $list = '';
        $panels = '';
        $inner = '';
        foreach ($items as $index => $item) {
            $n = $index + 1;
            $type = (string) ($item['type'] ?? 'youtube');
            $title = (string) ($item['title'] ?? '');
            $url = 'section' === $type ? '' : self::item_url($item);
            $attributes = self::panel_attributes($n, $short, $item, $url);
            if ('section' === $type) {
                $section_tag = self::pick_tag($item['section_html_tag'] ?? 'h3', 'h3', false);
                $list .= '<' . $section_tag . ' class="e-section-title">' . $title . '</' . $section_tag . '>';
                $panels .= '<div ' . $attributes . '><div></div></div>';
                continue;
            }
            $video_tag = self::pick_tag($item['video_html_tag'] ?? 'h4', 'h4', false);
            $title_attributes = Utils::render_html_attributes([
                'id'=>'e-tab-title-' . $short . $n,
                'class'=>'e-tab-title e-tab-desktop-title',
                'aria-selected'=>1 === $n ? 'true' : 'false',
                'data-tab'=>$n,
                'role'=>'tab',
                'tabindex'=>1 === $n ? '0' : '-1',
                'aria-controls'=>'e-tab-content-' . $short . $n,
            ]);
            $thumbnail = $thumbnails ? (string) ($item['thumbnail']['url'] ?? '') : '';
            $list .= '<div ' . $title_attributes . '>';
            $list .= $thumbnails
                ? '<div class="e-tab-thumbnail">' . ($thumbnail !== '' ? '<img src="' . esc_url($thumbnail) . '" alt="' . esc_attr($title) . '" loading="lazy" />' : '') . $play . '</div>'
                : $play;
            $list .= '<' . $video_tag . ' class="e-tab-title-text"><button type="button">' . $title . '</button></' . $video_tag . '>';
            $duration = !empty($s['show_duration']) ? (string) ($item['duration'] ?? '') : '';
            if ($duration !== '') { $list .= '<span class="e-tab-duration">' . esc_html($duration) . '</span>'; }
            $list .= '</div>';

            $panels .= '<div ' . $attributes . '><div></div>';
            if (0 === $index && $overlay) {
                $panels .= '<div class="elementor-custom-embed-image-overlay elementor-clickable" style="background-image: url(\'' . esc_url(self::overlay_image($s)) . '\');">';
                $icon = self::icon($s['show_play_icon'] ?? []);
                if ($icon !== '') { $panels .= '<div class="elementor-custom-embed-play" role="button" tabindex="0" aria-label="Reproducir vídeo">' . $icon . '</div>'; }
                $panels .= '</div>';
            }
            $panels .= '</div>';

            if (empty($item['inner_tab_is_content_visible'])) { continue; }
            $inner .= '<div ' . $attributes . '>' . $this->inner_tabs($item, $index, $tab_titles, $collapsible, $s) . '</div>';
        }

        $count = !empty($s['show_video_count']) ? '<span class="e-tabs-videos-count">' . esc_html((string) self::count_videos($items)) . ' Vídeos</span>' : '';
        $toggle = Icons_Manager::try_get_icon_html(['library'=>'eicons','value'=>'eicon-caret-down'], ['aria-hidden'=>'true','class'=>['e-tabs-toggle-videos-display-button', 'rotate-down']]);
        // The playlist and item titles are the widget's main text and, as Pro's, are not escaped.
        echo '<div class="e-tabs" role="region" aria-labelledby="e-playlist-' . esc_attr($id) . '">'
            . '<div class="e-tabs-main-area"><div class="e-tabs-wrapper">'
            . '<div class="e-tabs-header"><' . $tag . ' class="e-tabs-title" id="e-playlist-' . esc_attr($id) . '">' . ($s['playlist_title'] ?? '') . '</' . $tag . '>'
            . '<div class="e-tabs-header-right-side">' . $count . $toggle . '</div></div>'
            . '<div class="e-tabs-items-wrapper"><div class="e-tabs-items" role="tablist">' . $list . '</div>'
            . '<div class="shadow shadow-top" aria-hidden="true"></div><div class="shadow shadow-bottom" aria-hidden="true"></div></div>'
            . '</div><div class="e-tabs-content-wrapper" role="tablist" aria-orientation="vertical">' . $panels . '</div></div>'
            . '<div class="e-tabs-inner-tabs">' . $inner . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /** The two content tabs under a video: the first one with content starts open. */
    private function inner_tabs(array $item, int $index, array $titles, string $collapsible, array $s): string {
        $tabs = [];
        foreach ([1, 2] as $n) {
            $content = (string) ($item['inner_tab_content_' . $n] ?? '');
            if ($content !== '') { $tabs[] = ['title'=>$titles[$n - 1], 'content'=>$content, 'key'=>$this->get_repeater_setting_key('inner_tab_content_' . $n, 'tabs', $index)]; }
        }
        if (!$tabs) { return ''; }
        $heads = '';
        $bodies = '';
        foreach ($tabs as $i => $tab) {
            $active = 0 === $i ? 'e-inner-tab-active' : '';
            $heads .= '<div class="e-inner-tab-title ' . $active . '"><a class="e-inner-tab-title-text" tabindex="0">' . $tab['title'] . '</a></div>';
            $this->add_inline_editing_attributes($tab['key'], 'advanced');
            $bodies .= '<div class="e-inner-tab-title e-tab-mobile-title ' . $active . '">' . $tab['title'] . '</div>'
                . '<div ' . (0 === $i ? '' : 'hidden') . ' class="e-inner-tab-content ' . $active . ' ' . esc_attr($collapsible) . '">'
                . '<div class="e-inner-tab-text"><div ' . $this->get_render_attribute_string($tab['key']) . '>' . $this->parse_text_editor($tab['content']) . '</div></div>'
                . '<div class="e-inner-tab-buttons"><button class="show-button">' . ($s['inner_tab_label_show_more'] ?? '') . '</button><button>' . ($s['inner_tab_label_show_less'] ?? '') . '</button></div>'
                . '</div>';
        }
        return '<div class="e-inner-tabs-wrapper">' . $heads . '</div><div class="e-inner-tabs-content-wrapper">' . $bodies . '</div>';
    }

    /** «Get video data» in the editor: title, duration and thumbnail of a YouTube or Vimeo address (oEmbed). */
    public static function routes(): void {
        register_rest_route('obx/v1', self::ROUTE, [
            'methods'=>'POST',
            'callback'=>[self::class, 'video_data'],
            'permission_callback'=>static fn() => current_user_can('edit_posts'),
            'args'=>['url'=>['type'=>'string','required'=>true]],
        ]);
    }

    /** Only YouTube and Vimeo addresses are looked up (the request runs on the server). */
    public static function provider(string $url): string {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^(www\.|m\.)/', '', $host);
        if (in_array($host, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'], true)) { return 'youtube'; }
        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)) { return 'vimeo'; }
        return '';
    }

    public static function format_duration($seconds): string {
        $seconds = (int) $seconds;
        if ($seconds <= 0) { return ''; }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $sec = $seconds % 60;
        return $h ? sprintf('%d:%02d:%02d', $h, $m, $sec) : sprintf('%d:%02d', $m, $sec);
    }

    public static function video_data(\WP_REST_Request $request): \WP_REST_Response {
        $url = esc_url_raw(trim((string) $request['url']));
        if ($url === '' || self::provider($url) === '') { return new \WP_REST_Response(['message'=>'Introduce una URL de YouTube o Vimeo.'], 400); }
        $key = 'obx_video_data_' . md5($url);
        $data = get_transient($key);
        if (!is_array($data)) {
            require_once ABSPATH . WPINC . '/class-wp-oembed.php';
            $object = _wp_oembed_get_object()->get_data($url, ['width'=>1280]);
            if (!$object) { return new \WP_REST_Response(['message'=>'No se han podido obtener los datos del vídeo.'], 502); }
            $data = [
                'title'=>isset($object->title) ? wp_strip_all_tags((string) $object->title) : '',
                'thumbnail'=>isset($object->thumbnail_url) ? esc_url_raw((string) $object->thumbnail_url) : '',
                'duration'=>self::format_duration($object->duration ?? 0),
            ];
            set_transient($key, $data, DAY_IN_SECONDS);
        }
        return new \WP_REST_Response($data);
    }
}
