<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Icons_Manager;
use Elementor\Plugin as Elementor;

if (!defined('ABSPATH')) { exit; }

final class Animated_Headline extends Element {
    public const PRO = 'animated-headline';
    protected const V03 = ['style'=>'headline_style','animation'=>'animation_type','shape'=>'marker','before'=>'before_text','highlighted'=>'highlighted_text','rotating'=>'rotating_text','after'=>'after_text','duration'=>'rotate_iteration_delay','align'=>'alignment','shape_color'=>'marker_color','shape_width'=>'stroke_width','shape_front'=>'above_content','text_color'=>'title_color','dynamic_color'=>'words_color'];
    public const ANIMATIONS = ['typing'=>'Escritura','clip'=>'Recorte','flip'=>'Giro','swirl'=>'Remolino','blinds'=>'Persiana','drop-in'=>'Caída','wave'=>'Ola','slide'=>'Deslizar','slide-down'=>'Deslizar hacia abajo'];
    /** Strokes drawn in a 500×150 box stretched over the highlighted words. */
    private const MARKERS = [
        'circle'=>['M325 18C228-6 13 15 9 75c-4 53 236 72 410 47C561 102 526 6 285 13'],
        'curly'=>['M3 146c25-21 50-21 75 0s50 21 75 0 50-21 75 0 50 21 75 0 50-21 75 0 50 21 75 0 50-21 75 0'],
        'underline'=>['M7 138c87-12 321-17 487-8'],
        'double'=>['M8 12c56 11 334-1 484-5','M14 145c45-11 277-9 478-3'],
        'double_underline'=>['M5 125c93-9 324-11 489-3','M28 147c93-9 324-11 465-3'],
        'underline_zigzag'=>['M9 140c140-10 290-12 485-10M15 148c143-3 350-7 478-1M5 133c99-4 280-11 491-6'],
        'diagonal'=>['M13 145L488 10'],
        'strikethrough'=>['M3 79c125-6 330-7 493 0'],
        'x'=>['M497 12L3 140','M3 9l494 132'],
    ];
    public function get_title(): string { return 'Titular animado'; }
    public function get_icon(): string { return 'eicon-animated-headline'; }
    public function get_keywords(): array { return ['headline','titular','animado','animated','texto','rotar']; }

    protected static function upgrade_settings(array $s): array {
        $s['marker'] = ['strike'=>'strikethrough'][$s['shape'] ?? ''] ?? ($s['shape'] ?? 'underline');
        unset($s['shape']);
        if (($s['animation'] ?? '') === 'fade') { $s['animation'] = 'clip'; }
        $s['headline_style'] = $s['style'] ?? 'highlight';
        unset($s['style']);
        $s = self::rename_prefix($s, 'text_typography_', 'title_typography_');
        $s = self::rename_prefix($s, 'dynamic_typography_', 'words_typography_');
        return parent::upgrade_settings($s);
    }

    protected function register_controls(): void {
        $this->start_controls_section('text_elements', ['label'=>'Titular']);
        $this->add_control('headline_style', ['label'=>'Estilo','type'=>Controls_Manager::SELECT,'default'=>'highlight','options'=>['highlight'=>'Resaltado','rotate'=>'Rotación'],'prefix_class'=>'obx-headline--style-','render_type'=>'template']);
        $this->add_control('animation_type', ['label'=>'Animación','type'=>Controls_Manager::SELECT,'default'=>'typing','options'=>self::ANIMATIONS,'condition'=>['headline_style'=>'rotate'],'render_type'=>'template']);
        $this->add_control('marker', ['label'=>'Forma','type'=>Controls_Manager::SELECT,'default'=>'circle','options'=>['circle'=>'Círculo','curly'=>'Ondulado','underline'=>'Subrayado','double'=>'Doble','double_underline'=>'Doble subrayado','underline_zigzag'=>'Subrayado en zigzag','diagonal'=>'Diagonal','strikethrough'=>'Tachado','x'=>'Aspa'],'condition'=>['headline_style'=>'highlight'],'render_type'=>'template']);
        $this->add_control('before_text', ['label'=>'Texto anterior','type'=>Controls_Manager::TEXT,'default'=>'Esta página es','label_block'=>true,'dynamic'=>['active'=>true],'separator'=>'before']);
        $this->add_control('highlighted_text', ['label'=>'Texto resaltado','type'=>Controls_Manager::TEXT,'default'=>'increíble','label_block'=>true,'dynamic'=>['active'=>true],'condition'=>['headline_style'=>'highlight']]);
        $this->add_control('rotating_text', ['label'=>'Texto que rota','type'=>Controls_Manager::TEXTAREA,'default'=>"mejor\nmás grande\nmás rápida",'description'=>'Una palabra o frase por línea.','dynamic'=>['active'=>true],'condition'=>['headline_style'=>'rotate']]);
        $this->add_control('after_text', ['label'=>'Texto posterior','type'=>Controls_Manager::TEXT,'label_block'=>true,'dynamic'=>['active'=>true]]);
        $this->add_control('loop', ['label'=>'Repetir','type'=>Controls_Manager::SWITCHER,'default'=>'yes','render_type'=>'template']);
        $this->add_control('highlight_animation_duration', ['label'=>'Duración (ms)','type'=>Controls_Manager::NUMBER,'default'=>1200,'condition'=>['headline_style'=>'highlight'],'render_type'=>'template']);
        $this->add_control('highlight_iteration_delay', ['label'=>'Pausa entre repeticiones (ms)','type'=>Controls_Manager::NUMBER,'default'=>8000,'condition'=>['headline_style'=>'highlight','loop'=>'yes'],'render_type'=>'template']);
        $this->add_control('rotate_iteration_delay', ['label'=>'Duración de cada palabra (ms)','type'=>Controls_Manager::NUMBER,'default'=>2500,'condition'=>['headline_style'=>'rotate'],'render_type'=>'template']);
        $this->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true],'separator'=>'before']);
        $this->add_control('tag', ['label'=>'Etiqueta HTML','type'=>Controls_Manager::SELECT,'default'=>'h3','options'=>self::heading_tags()]);
        $this->end_controls_section();

        $this->start_controls_section('section_style_text', ['label'=>'Titular','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'default'=>'center','selectors'=>self::css('.obx-headline', 'text-align: {{VALUE}};')] + self::logical_choose());
        $this->add_control('heading_style', ['label'=>'Texto','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('title_color', 'Color', '.obx-headline__plain');
        $this->typography('title_typography', '.obx-headline');
        $this->add_group_control(Group_Control_Text_Stroke::get_type(), ['name'=>'text_stroke','selector'=>'{{WRAPPER}} .obx-headline']);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'title_text_shadow','selector'=>'{{WRAPPER}} .obx-headline']);
        $this->add_control('heading_words_style', ['label'=>'Texto animado','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('words_color', 'Color', '.obx-headline__dynamic');
        $this->typography('words_typography', '.obx-headline__dynamic', ['exclude'=>['font_size']]);
        $this->add_group_control(Group_Control_Text_Stroke::get_type(), ['name'=>'animated_text_stroke','selector'=>'{{WRAPPER}} .obx-headline__dynamic']);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'animated_text_shadow','selector'=>'{{WRAPPER}} .obx-headline__dynamic']);
        $this->add_control('typing_animation_highlight_colors', ['label'=>'Selección al escribir','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['headline_style'=>'rotate','animation_type'=>'typing']]);
        $this->color('highlighted_text_background_color', 'Fondo', '.obx-headline', '--obx-typing-bg', ['condition'=>['headline_style'=>'rotate','animation_type'=>'typing']]);
        $this->color('highlighted_text_color', 'Color', '.obx-headline', '--obx-typing-color', ['condition'=>['headline_style'=>'rotate','animation_type'=>'typing']]);
        $this->add_control('highlight_animation_shape_colors', ['label'=>'Forma','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['headline_style'=>'highlight']]);
        $this->color('marker_color', 'Color', '.obx-headline__shape path', 'stroke', ['condition'=>['headline_style'=>'highlight']]);
        $this->slider('stroke_width', 'Grosor', '.obx-headline__shape path', 'stroke-width: {{SIZE}};', ['size_units'=>['px'],'range'=>['px'=>['min'=>1,'max'=>20]],'condition'=>['headline_style'=>'highlight']]);
        $this->add_control('above_content', ['label'=>'Delante del texto','type'=>Controls_Manager::SWITCHER,'selectors'=>self::css('.obx-headline__shape', 'z-index: 2;'),'condition'=>['headline_style'=>'highlight']]);
        $this->add_control('rounded_edges', ['label'=>'Bordes redondeados','type'=>Controls_Manager::SWITCHER,'selectors'=>self::css('.obx-headline__shape path', 'stroke-linecap: round; stroke-linejoin: round;'),'condition'=>['headline_style'=>'highlight']]);
        $this->end_controls_section();
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $tag = self::pick_tag($s['tag'] ?? '', 'h3');
        $loop = ($s['loop'] ?? 'yes') === 'yes';
        $this->add_render_attribute('headline', ['class'=>'obx-headline','data-loop'=>$loop ? '1' : '0']);
        if (($s['headline_style'] ?? 'highlight') === 'rotate') {
            $words = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $s['rotating_text']) ?: []), 'strlen')) ?: [''];
            $animation = array_key_exists($s['animation_type'] ?? '', self::ANIMATIONS) ? $s['animation_type'] : 'typing';
            $this->add_render_attribute('headline', ['class'=>['obx-headline--rotate','obx-headline--' . $animation],'data-words'=>wp_json_encode($words),'data-duration'=>(string) max(300, (int) ($s['rotate_iteration_delay'] ?? 2500))]);
            $dynamic = '<span class="obx-headline__dynamic" aria-hidden="true">' . esc_html($words[0]) . '</span><span class="screen-reader-text">' . esc_html(implode(', ', $words)) . '</span>';
        } else {
            $marker = isset(self::MARKERS[$s['marker'] ?? '']) ? $s['marker'] : 'circle';
            $this->add_render_attribute('headline', ['class'=>['obx-headline--highlight','obx-headline--' . $marker],'data-duration'=>(string) max(100, (int) ($s['highlight_animation_duration'] ?? 1200)),'data-delay'=>(string) max(500, (int) ($s['highlight_iteration_delay'] ?? 8000))]);
            $paths = implode('', array_map(static fn($d) => '<path d="' . esc_attr($d) . '"/>', self::MARKERS[$marker]));
            $dynamic = '<span class="obx-headline__dynamic">' . esc_html((string) $s['highlighted_text']) . '</span><svg class="obx-headline__shape" viewBox="0 0 500 150" preserveAspectRatio="none" aria-hidden="true">' . $paths . '</svg>';
        }
        $html = '';
        if ((string) $s['before_text'] !== '') { $html .= '<span class="obx-headline__plain obx-headline__text">' . esc_html($s['before_text']) . '</span> '; }
        $html .= '<span class="obx-headline__wrap">' . $dynamic . '</span>';
        if ((string) ($s['after_text'] ?? '') !== '') { $html .= ' <span class="obx-headline__plain obx-headline__text">' . esc_html($s['after_text']) . '</span>'; }
        if (!empty($s['link']['url'])) { $html = $this->open_link('link', $s['link'], 'obx-headline__link') . $html . '</a>'; }
        printf('<%1$s %2$s>%3$s</%1$s>', $tag, $this->get_render_attribute_string('headline'), $html);
    }
}

final class Table_Of_Contents extends Element {
    public const PRO = 'table-of-contents';
    protected const V03 = ['headings'=>'headings_by_tags','exclude'=>'exclude_headings_by_selector','marker'=>'marker_view','hierarchical'=>'hierarchical_view','collapsible'=>'minimize_box','offset'=>'obx_scroll_offset','list_color'=>'item_text_color_normal','list_hover'=>'item_text_color_hover','list_active'=>'item_text_color_active','box_bg'=>'background_color'];
    public function get_title(): string { return 'Tabla de contenidos'; }
    public function get_icon(): string { return 'eicon-table-of-contents'; }
    public function get_keywords(): array { return ['toc','indice','tabla de contenidos','contents','encabezados']; }

    protected static function upgrade_settings(array $s): array {
        if (($s['marker'] ?? '') === 'none') { $s['marker'] = 'bullets'; $s['icon'] = ['value'=>'','library'=>'']; }
        $s['minimize_box'] = $s['collapsible'] ?? '';
        if (($s['collapsed'] ?? '') === 'yes') { $s['minimized_on'] = 'desktop'; }
        unset($s['collapsed']);
        $s = self::rename_prefix($s, 'title_typography_', 'header_typography_');
        $s = self::rename_prefix($s, 'list_typography_', 'list_typography_');
        return parent::upgrade_settings($s);
    }

    protected function register_controls(): void {
        $this->start_controls_section('table_of_contents', ['label'=>'Tabla de contenidos']);
        $this->add_control('title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Contenido','label_block'=>true,'dynamic'=>['active'=>true]]);
        $this->add_control('html_tag', ['label'=>'Etiqueta HTML','type'=>Controls_Manager::SELECT,'default'=>'h4','options'=>['h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','h6'=>'H6','div'=>'div']]);
        $this->start_controls_tabs('include_exclude_tags');
        $this->start_controls_tab('tab_include', ['label'=>'Incluir']);
        $this->add_control('headings_by_tags', ['label'=>'Encabezados incluidos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'default'=>['h2','h3','h4','h5','h6'],'options'=>['h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','h6'=>'H6'],'label_block'=>true,'separator'=>'before']);
        $this->add_control('container', ['label'=>'Buscar dentro de','type'=>Controls_Manager::TEXT,'label_block'=>true,'description'=>'Selector CSS. Vacío: toda la página.']);
        $this->end_controls_tab();
        $this->start_controls_tab('tab_exclude', ['label'=>'Excluir']);
        $this->add_control('exclude_headings_by_selector', ['label'=>'Excluir','type'=>Controls_Manager::TEXT,'label_block'=>true,'description'=>'Selector CSS de los encabezados que no deben aparecer.']);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_control('marker_view', ['label'=>'Marcador','type'=>Controls_Manager::SELECT,'default'=>'numbers','options'=>['numbers'=>'Números','bullets'=>'Viñetas'],'separator'=>'before']);
        $this->add_control('icon', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'default'=>['value'=>'fas fa-circle','library'=>'fa-solid'],'skin'=>'inline','label_block'=>false,'condition'=>['marker_view'=>'bullets']]);
        $this->add_control('no_headings_message', ['label'=>'Mensaje sin encabezados','type'=>Controls_Manager::TEXT,'default'=>'No hay encabezados en esta página.','label_block'=>true,'separator'=>'before']);
        $this->end_controls_section();

        $this->start_controls_section('additional_options', ['label'=>'Opciones']);
        $this->add_control('word_wrap', ['label'=>'Ajuste de línea','type'=>Controls_Manager::SWITCHER,'return_value'=>'ellipsis','prefix_class'=>'obx-toc--content-']);
        $this->add_control('minimize_box', ['label'=>'Plegable','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('expand_icon', ['label'=>'Icono para abrir','type'=>Controls_Manager::ICONS,'default'=>['value'=>'fas fa-chevron-down','library'=>'fa-solid'],'skin'=>'inline','label_block'=>false,'condition'=>['minimize_box'=>'yes']]);
        $this->add_control('collapse_icon', ['label'=>'Icono para cerrar','type'=>Controls_Manager::ICONS,'default'=>['value'=>'fas fa-chevron-up','library'=>'fa-solid'],'skin'=>'inline','label_block'=>false,'condition'=>['minimize_box'=>'yes']]);
        $this->add_control('minimized_on', ['label'=>'Plegada en','type'=>Controls_Manager::SELECT,'default'=>'tablet','options'=>['desktop'=>'Todos los dispositivos','tablet'=>'Tableta (≤ 1024 px)','mobile'=>'Móvil (≤ 767 px)'],'condition'=>['minimize_box!'=>'']]);
        $this->add_control('hierarchical_view', ['label'=>'Vista jerárquica','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('collapse_subitems', ['label'=>'Plegar subelementos','type'=>Controls_Manager::SWITCHER,'description'=>'Muestra los subniveles solo de la sección actual.','condition'=>['hierarchical_view'=>'yes']]);
        $this->add_control('obx_scroll_offset', ['label'=>'Margen al desplazar (px)','type'=>Controls_Manager::NUMBER,'min'=>0,'default'=>0,'description'=>'Útil con una cabecera fija.']);
        $this->end_controls_section();

        $this->start_controls_section('box_style', ['label'=>'Caja','tab'=>Controls_Manager::TAB_STYLE]);
        $this->color('background_color', 'Fondo', '.obx-toc', 'background-color');
        $this->color('border_color', 'Color del borde', '.obx-toc', '--obx-toc-border');
        $this->color('loader_color', 'Color de carga', '.obx-toc', '--obx-toc-loader');
        $this->slider('border_width', 'Grosor del borde', '.obx-toc', 'border-width: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem']], true);
        $this->slider('border_radius', 'Radio del borde', '.obx-toc', 'border-radius: {{SIZE}}{{UNIT}};', [], true);
        $this->slider('padding', 'Relleno', '.obx-toc', '--obx-toc-padding: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem']], true);
        $this->slider('min_height', 'Altura mínima', '.obx-toc', 'min-height: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','vh']], true);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'box_shadow','selector'=>'{{WRAPPER}} .obx-toc']);
        $this->end_controls_section();

        $this->start_controls_section('header_style', ['label'=>'Cabecera','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('header_text_align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'default'=>'start','selectors'=>self::css('.obx-toc__title', 'text-align: {{VALUE}};')] + self::logical_choose());
        $this->color('header_background_color', 'Fondo', '.obx-toc__header', 'background-color');
        $this->color('header_text_color', 'Color del texto', '.obx-toc__title', 'color');
        $this->typography('header_typography', '.obx-toc__title');
        $this->color('toggle_button_color', 'Color del botón', '.obx-toc__toggle', 'color', ['condition'=>['minimize_box'=>'yes']]);
        $this->add_responsive_control('toggle_button_position', ['label'=>'Posición del botón','type'=>Controls_Manager::CHOOSE,'default'=>'row','options'=>['row-reverse'=>['title'=>'Antes','icon'=>'eicon-h-align-left'],'row'=>['title'=>'Después','icon'=>'eicon-h-align-right']],'selectors'=>self::css('.obx-toc__header', 'flex-direction: {{VALUE}};'),'condition'=>['minimize_box'=>'yes']]);
        $this->slider('heading_gap', 'Separación', '.obx-toc__header', 'gap: {{SIZE}}{{UNIT}};', ['condition'=>['minimize_box'=>'yes']], true);
        $this->slider('header_separator_width', 'Grosor del separador', '.obx-toc__header', 'border-bottom-width: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em']], true);
        $this->end_controls_section();

        $this->start_controls_section('list_style', ['label'=>'Lista','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('max_height', 'Altura máxima', '.obx-toc__body', 'max-height: {{SIZE}}{{UNIT}}; overflow-y: auto;', ['size_units'=>['px','em','rem','vh']], true);
        $this->typography('list_typography', '.obx-toc__list a');
        $this->slider('list_indent', 'Sangría', '.obx-toc__list', '--obx-toc-indent: {{SIZE}}{{UNIT}};', ['default'=>['unit'=>'em'],'size_units'=>['px','em','rem']], true);
        $this->start_controls_tabs('item_text_style');
        foreach (['normal'=>['', 'Normal'],'hover'=>[':hover', 'Al pasar el ratón'],'active'=>['.is-active', 'Activo']] as $state => [$pseudo, $label]) {
            $this->start_controls_tab('item_text_tab_' . $state, ['label'=>$label]);
            $this->color('item_text_color_' . $state, 'Color', '.obx-toc__list a' . $pseudo, 'color');
            $this->add_control('item_text_underline_' . $state, ['label'=>'Subrayado','type'=>Controls_Manager::SWITCHER,'default'=>$state === 'hover' ? 'yes' : '','selectors'=>self::css('.obx-toc__list a' . $pseudo, 'text-decoration: underline;')]);
            if ($state === 'hover') {
                $this->slider('item_text_hover_transition_duration', 'Duración de la transición', '.obx-toc__list a', 'transition-duration: {{SIZE}}{{UNIT}};', ['size_units'=>['ms','s'],'default'=>['unit'=>'ms'],'range'=>['ms'=>['max'=>3000]]]);
            }
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->add_control('heading_marker', ['label'=>'Marcador','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('marker_color', 'Color', '.obx-toc__list', '--obx-toc-marker');
        $this->slider('marker_size', 'Tamaño', '.obx-toc__list', '--obx-toc-marker-size: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem']], true);
        $this->end_controls_section();
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $headings = array_values(array_intersect((array) ($s['headings_by_tags'] ?? []), ['h1','h2','h3','h4','h5','h6'])) ?: ['h2','h3','h4','h5','h6'];
        $marker = ($s['marker_view'] ?? 'numbers') === 'bullets' ? 'bullets' : 'numbers';
        $collapsible = ($s['minimize_box'] ?? 'yes') === 'yes';
        $minimized = $collapsible ? (in_array($s['minimized_on'] ?? 'tablet', ['desktop','tablet','mobile'], true) ? $s['minimized_on'] : 'tablet') : '';
        $this->add_render_attribute('toc', ['class'=>['obx-toc','obx-toc--' . $marker],
            'data-headings'=>implode(',', $headings),'data-container'=>sanitize_text_field((string) ($s['container'] ?? '')),'data-exclude'=>sanitize_text_field((string) ($s['exclude_headings_by_selector'] ?? '')),
            'data-hierarchical'=>($s['hierarchical_view'] ?? 'yes') === 'yes' ? '1' : '0','data-collapse-subitems'=>($s['collapse_subitems'] ?? '') === 'yes' ? '1' : '0',
            'data-offset'=>(string) max(0, (int) ($s['obx_scroll_offset'] ?? 0)),'data-minimized-on'=>$this->editing() ? '' : $minimized]);
        $list = 'obx-toc-' . $this->get_id();
        $tag = in_array($s['html_tag'] ?? '', ['h2','h3','h4','h5','h6','div'], true) ? $s['html_tag'] : 'h4';
        echo '<nav ' . $this->get_render_attribute_string('toc') . ' aria-label="' . esc_attr(($s['title'] ?? '') ?: 'Tabla de contenidos') . '"><div class="obx-toc__header">';
        echo '<' . $tag . ' class="obx-toc__title">' . esc_html((string) ($s['title'] ?? '')) . '</' . $tag . '>';
        if ($collapsible) {
            $expand = Icons_Manager::try_get_icon_html($s['expand_icon'] ?? [], ['aria-hidden'=>'true']);
            $collapse = Icons_Manager::try_get_icon_html($s['collapse_icon'] ?? [], ['aria-hidden'=>'true']);
            echo '<button class="obx-toc__toggle" type="button" aria-expanded="true" aria-controls="' . esc_attr($list) . '"><span class="obx-toc__toggle-expand">' . $expand . '</span><span class="obx-toc__toggle-collapse">' . $collapse . '</span><span class="screen-reader-text">Mostrar u ocultar</span></button>';
        }
        echo '</div><div class="obx-toc__body" id="' . esc_attr($list) . '">';
        if ($marker === 'bullets' && !empty($s['icon']['value'])) { echo '<template class="obx-toc__bullet">' . Icons_Manager::try_get_icon_html($s['icon'], ['aria-hidden'=>'true']) . '</template>'; }
        echo '<p class="obx-toc__empty">' . esc_html((string) ($s['no_headings_message'] ?? '')) . '</p></div></nav>';
    }
}

final class Progress_Tracker extends Element {
    public const PRO = 'progress-tracker';
    public const PRO_CATEGORY = 'theme-elements';
    protected const V03 = ['relative'=>'relative_to','fill'=>'horizontal_color_color','circle_size'=>'circular_size','thickness'=>'horizontal_height','value_color'=>'percentage_color'];
    public function get_title(): string { return 'Indicador de progreso'; }
    public function get_icon(): string { return 'eicon-progress-tracker'; }
    public function get_keywords(): array { return ['progress','progreso','lectura','scroll','barra']; }

    protected static function upgrade_settings(array $s): array {
        if (isset($s['relative'])) { $s['relative'] = ['page'=>'entire_page','post'=>'post_content','selector'=>'selector'][$s['relative']] ?? 'entire_page'; }
        if (isset($s['fill'])) { $s['horizontal_color_background'] = 'classic'; $s['circular_color'] = $s['fill']; }
        if (isset($s['track'])) { $s['horizontal_background_color_background'] = 'classic'; $s['horizontal_background_color_color'] = $s['track']; $s['circular_background_color'] = $s['track']; unset($s['track']); }
        $s['percentage'] = ($s['percentage'] ?? '') === 'yes' ? 'yes' : 'no';
        unset($s['fixed']);
        $s = self::rename_prefix($s, 'value_typography_', 'percentage_typography_');
        return parent::upgrade_settings($s);
    }

    protected function register_controls(): void {
        $this->start_controls_section('section_content_scrolling_tracker', ['label'=>'Indicador']);
        $this->add_control('type', ['label'=>'Tipo','type'=>Controls_Manager::SELECT,'default'=>'horizontal','options'=>['horizontal'=>'Barra','circular'=>'Círculo'],'render_type'=>'template']);
        $this->add_control('relative_to', ['label'=>'Progreso de','type'=>Controls_Manager::SELECT,'default'=>'entire_page','options'=>['entire_page'=>'Toda la página','post_content'=>'Contenido de la entrada','selector'=>'Selector'],'render_type'=>'template']);
        $this->add_control('selector', ['label'=>'Selector','type'=>Controls_Manager::TEXT,'placeholder'=>'#id, .class','condition'=>['relative_to'=>'selector'],'render_type'=>'template']);
        $this->add_control('relative_to_description', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Sigue la lectura del widget «Contenido de la entrada» de la plantilla.','content_classes'=>'elementor-descriptor','condition'=>['relative_to'=>'post_content']]);
        $this->add_control('direction', ['label'=>'Dirección','type'=>Controls_Manager::CHOOSE,'options'=>['ltr'=>['title'=>'Izquierda a derecha','icon'=>'eicon-h-align-left'],'rtl'=>['title'=>'Derecha a izquierda','icon'=>'eicon-h-align-right']],'render_type'=>'template']);
        $this->add_control('percentage', ['label'=>'Porcentaje','type'=>Controls_Manager::SWITCHER,'default'=>'no','render_type'=>'template']);
        $this->add_responsive_control('percentage_position', ['label'=>'Posición del porcentaje','type'=>Controls_Manager::CHOOSE,'options'=>['rtl'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'ltr'=>['title'=>'Derecha','icon'=>'eicon-h-align-right']],'selectors_dictionary'=>['rtl'=>'flex-start','ltr'=>'flex-end'],'selectors'=>self::css('.obx-progress--horizontal .obx-progress__fill', 'justify-content: {{VALUE}};'),'condition'=>['type'=>'horizontal','percentage'=>'yes']]);
        $this->end_controls_section();

        $this->start_controls_section('section_style_scrolling_tracker', ['label'=>'Indicador','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('circular_size', 'Tamaño', '.obx-progress--circular', 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','vw'],'range'=>['px'=>['min'=>20,'max'=>400]],'condition'=>['type'=>'circular']], true);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),'selectors_dictionary'=>['left'=>'0 auto 0 0','center'=>'0 auto','right'=>'0 0 0 auto'],'selectors'=>self::css('.obx-progress--circular', 'margin: {{VALUE}};'),'condition'=>['type'=>'circular']]);
        $this->add_control('heading_progress_style', ['label'=>'Progreso','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('circular_color', 'Color', '.obx-progress', '--obx-progress-fill', ['condition'=>['type'=>'circular']]);
        $this->slider('circular_width', 'Grosor', '.obx-progress .obx-progress__fill', 'stroke-width: {{SIZE}};', ['size_units'=>['px'],'range'=>['px'=>['min'=>1,'max'=>50]],'condition'=>['type'=>'circular']], true);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'horizontal_color','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>'{{WRAPPER}} .obx-progress--horizontal .obx-progress__fill','condition'=>['type'=>'horizontal']]);
        $this->add_control('horizontal_border_style', ['label'=>'Borde','type'=>Controls_Manager::SELECT,'default'=>'none','options'=>['none'=>'Ninguno','solid'=>'Sólido','double'=>'Doble','dotted'=>'Puntos','dashed'=>'Guiones','groove'=>'Ranura'],'selectors'=>self::css('.obx-progress--horizontal .obx-progress__fill', 'border-style: {{VALUE}};'),'condition'=>['type'=>'horizontal']]);
        $this->dimensions('horizontal_border_width', 'Grosor del borde', '.obx-progress--horizontal .obx-progress__fill', 'border-width', ['size_units'=>['px','em','rem'],'condition'=>['horizontal_border_style!'=>'none','type'=>'horizontal']]);
        $this->color('horizontal_border_color', 'Color del borde', '.obx-progress--horizontal .obx-progress__fill', 'border-color', ['condition'=>['horizontal_border_style!'=>'none','type'=>'horizontal']]);
        $this->dimensions('horizontal_border_radius', 'Radio del borde', '.obx-progress--horizontal .obx-progress__fill', 'border-radius', ['condition'=>['type'=>'horizontal']]);
        $this->add_control('heading_tracker_background_style', ['label'=>'Fondo','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('circular_background_color', 'Color', '.obx-progress', '--obx-progress-track', ['condition'=>['type'=>'circular']]);
        $this->slider('circular_background_width', 'Grosor', '.obx-progress .obx-progress__track', 'stroke-width: {{SIZE}};', ['size_units'=>['px'],'range'=>['px'=>['min'=>1,'max'=>50]],'condition'=>['type'=>'circular']], true);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'horizontal_background_color','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>'{{WRAPPER}} .obx-progress--horizontal .obx-progress__bar','condition'=>['type'=>'horizontal']]);
        $this->slider('horizontal_height', 'Altura', '.obx-progress--horizontal .obx-progress__bar', 'height: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['min'=>1,'max'=>100]],'condition'=>['type'=>'horizontal']], true);
        $this->add_control('horizontal_tracker_border_style', ['label'=>'Borde','type'=>Controls_Manager::SELECT,'default'=>'none','options'=>['none'=>'Ninguno','solid'=>'Sólido','double'=>'Doble','dotted'=>'Puntos','dashed'=>'Guiones','groove'=>'Ranura'],'selectors'=>self::css('.obx-progress--horizontal .obx-progress__bar', 'border-style: {{VALUE}};'),'condition'=>['type'=>'horizontal']]);
        $this->dimensions('horizontal_tracker_border_width', 'Grosor del borde', '.obx-progress--horizontal .obx-progress__bar', 'border-width', ['size_units'=>['px','em','rem'],'condition'=>['horizontal_tracker_border_style!'=>'none','type'=>'horizontal']]);
        $this->color('horizontal_tracker_border_color', 'Color del borde', '.obx-progress--horizontal .obx-progress__bar', 'border-color', ['condition'=>['horizontal_tracker_border_style!'=>'none','type'=>'horizontal']]);
        $this->dimensions('horizontal_tracker_border_radius', 'Radio del borde', '.obx-progress--horizontal .obx-progress__bar', 'border-radius', ['condition'=>['type'=>'horizontal']]);
        $this->dimensions('horizontal_padding', 'Relleno', '.obx-progress--horizontal .obx-progress__bar', 'padding', ['condition'=>['type'=>'horizontal']]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'box_shadow','selector'=>'{{WRAPPER}} .obx-progress--horizontal .obx-progress__bar','condition'=>['type'=>'horizontal']]);
        $this->end_controls_section();

        $this->start_controls_section('section__content_style_scrolling_tracker', ['label'=>'Porcentaje','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['percentage'=>'yes']]);
        $this->add_control('heading_percentage_style', ['label'=>'Porcentaje','type'=>Controls_Manager::HEADING]);
        $this->color('percentage_color', 'Color', '.obx-progress__value');
        $this->typography('percentage_typography', '.obx-progress__value');
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'percentage_text_shadow','selector'=>'{{WRAPPER}} .obx-progress__value']);
        $this->end_controls_section();
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $type = ($s['type'] ?? 'horizontal') === 'circular' ? 'circular' : 'horizontal';
        $relative = (string) ($s['relative_to'] ?? 'entire_page');
        $target = $relative === 'post_content' ? '.elementor-widget-theme-post-content, .elementor-widget-obx-post-content, .entry-content' : ($relative === 'selector' ? sanitize_text_field((string) ($s['selector'] ?? '')) : '');
        $direction = ($s['direction'] ?? '') === 'rtl' ? 'rtl' : 'ltr';
        $this->add_render_attribute('progress', ['class'=>['obx-progress','obx-progress--' . $type,'obx-progress--' . $direction],'data-target'=>$target,'role'=>'progressbar','aria-valuemin'=>'0','aria-valuemax'=>'100','aria-valuenow'=>'0','aria-label'=>'Progreso de lectura']);
        $value = ($s['percentage'] ?? 'no') === 'yes' ? '<span class="obx-progress__value">0%</span>' : '';
        echo '<div ' . $this->get_render_attribute_string('progress') . '>';
        echo $type === 'circular'
            ? '<svg viewBox="0 0 100 100" aria-hidden="true"><circle class="obx-progress__track" cx="50" cy="50" r="45"/><circle class="obx-progress__fill" cx="50" cy="50" r="45" pathLength="100"/></svg>' . $value
            : '<div class="obx-progress__bar"><div class="obx-progress__fill">' . $value . '</div></div>';
        echo '</div>';
    }
}

final class Login extends Element {
    public const PRO = 'login';
    protected const V03 = ['remember'=>'show_remember_me','button'=>'button_text','lost_password'=>'show_lost_password','register'=>'show_register','redirect'=>'redirect_url','fields_color'=>'field_text_color','fields_bg'=>'field_background_color','labels_color'=>'label_color'];
    public function get_title(): string { return 'Inicio de sesión'; }
    public function get_icon(): string { return 'eicon-lock-user'; }
    public function get_keywords(): array { return ['login','acceso','sesion','usuario','entrar']; }
    protected function is_dynamic_content(): bool { return true; }
    public function get_script_depends(): array { return []; }

    protected static function upgrade_settings(array $s): array {
        if (!empty($s['redirect']['url'])) { $s['redirect_after_login'] = 'yes'; }
        $s['show_logged_in_message'] = ($s['logged_in'] ?? 'message') === 'message' ? 'yes' : '';
        unset($s['logged_in']);
        if (isset($s['user_label']) || isset($s['password_label'])) { $s['custom_labels'] = 'yes'; }
        $s = self::rename_prefix($s, 'fields_typography_', 'field_typography_');
        return parent::upgrade_settings($s);
    }

    protected function register_controls(): void {
        $this->start_controls_section('section_fields_content', ['label'=>'Campos']);
        $this->add_control('show_labels', ['label'=>'Etiquetas','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('input_size', ['label'=>'Tamaño de los campos','type'=>Controls_Manager::SELECT,'default'=>'sm','options'=>self::sizes()]);
        $this->end_controls_section();

        $this->start_controls_section('section_button_content', ['label'=>'Botón']);
        $this->add_control('button_text', ['label'=>'Texto','type'=>Controls_Manager::TEXT,'default'=>'Acceder']);
        $this->add_control('button_size', ['label'=>'Tamaño','type'=>Controls_Manager::SELECT,'default'=>'sm','options'=>self::sizes()]);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align() + ['stretch'=>['title'=>'Ancho completo','icon'=>'eicon-h-align-stretch']],'selectors_dictionary'=>['start'=>'flex-start','end'=>'flex-end','left'=>'flex-start','right'=>'flex-end','center'=>'center','stretch'=>'stretch'],'selectors'=>self::css('.obx-login__submit', 'align-items: {{VALUE}};')]);
        $this->end_controls_section();

        $this->start_controls_section('section_login_content', ['label'=>'Opciones']);
        $this->add_control('redirect_after_login', ['label'=>'Redirigir tras acceder','type'=>Controls_Manager::SWITCHER]);
        $this->add_control('redirect_url', ['label'=>'Dirección','type'=>Controls_Manager::URL,'options'=>false,'description'=>'Solo direcciones de este sitio.','condition'=>['redirect_after_login'=>'yes']]);
        $this->add_control('redirect_after_logout', ['label'=>'Redirigir tras salir','type'=>Controls_Manager::SWITCHER]);
        $this->add_control('redirect_logout_url', ['label'=>'Dirección','type'=>Controls_Manager::URL,'options'=>false,'description'=>'Solo direcciones de este sitio.','condition'=>['redirect_after_logout'=>'yes']]);
        $this->add_control('show_lost_password', ['label'=>'Enlace «¿Has olvidado tu contraseña?»','type'=>Controls_Manager::SWITCHER,'default'=>'yes','separator'=>'before']);
        if (get_option('users_can_register')) { $this->add_control('show_register', ['label'=>'Enlace de registro','type'=>Controls_Manager::SWITCHER,'default'=>'yes']); }
        $this->add_control('show_remember_me', ['label'=>'Casilla «Recuérdame»','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('show_logged_in_message', ['label'=>'Mensaje con sesión iniciada','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('custom_labels', ['label'=>'Etiquetas personalizadas','type'=>Controls_Manager::SWITCHER,'separator'=>'before']);
        $this->add_control('user_label', ['label'=>'Etiqueta del usuario','type'=>Controls_Manager::TEXT,'default'=>'Usuario o correo electrónico','conditions'=>['relation'=>'or','terms'=>[['name'=>'show_labels','operator'=>'===','value'=>'yes'],['name'=>'custom_labels','operator'=>'===','value'=>'yes']]]]);
        $this->add_control('user_placeholder', ['label'=>'Texto de ejemplo del usuario','type'=>Controls_Manager::TEXT,'default'=>'Usuario o correo electrónico','condition'=>['custom_labels'=>'yes']]);
        $this->add_control('password_label', ['label'=>'Etiqueta de la contraseña','type'=>Controls_Manager::TEXT,'default'=>'Contraseña','conditions'=>['relation'=>'or','terms'=>[['name'=>'show_labels','operator'=>'===','value'=>'yes'],['name'=>'custom_labels','operator'=>'===','value'=>'yes']]]]);
        $this->add_control('password_placeholder', ['label'=>'Texto de ejemplo de la contraseña','type'=>Controls_Manager::TEXT,'default'=>'Contraseña','condition'=>['custom_labels'=>'yes']]);
        $this->end_controls_section();

        $this->start_controls_section('section_style', ['label'=>'Formulario','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('row_gap', 'Separación entre filas', '.obx-login__form', 'gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'default'=>['size'=>10],'range'=>['px'=>['max'=>60]]]);
        $this->color('links_color', 'Color de los enlaces', '.obx-login__links a');
        $this->color('links_hover_color', 'Color de los enlaces al pasar el ratón', '.obx-login__links a:hover');
        $this->end_controls_section();

        $this->start_controls_section('section_style_labels', ['label'=>'Etiquetas','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['show_labels!'=>'']]);
        $this->slider('label_spacing', 'Separación', '.obx-login__field label', 'margin-bottom: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'default'=>['size'=>0],'range'=>['px'=>['max'=>60]]]);
        $this->color('label_color', 'Color', '.obx-login__field label, .obx-login__remember');
        $this->typography('label_typography', '.obx-login__field label, .obx-login__remember');
        $this->end_controls_section();

        $this->start_controls_section('section_field_style', ['label'=>'Campos','tab'=>Controls_Manager::TAB_STYLE]);
        $this->color('field_text_color', 'Color del texto', '.obx-login__input');
        $this->typography('field_typography', '.obx-login__input');
        $this->color('field_background_color', 'Fondo', '.obx-login__input', 'background-color', ['default'=>'#ffffff','separator'=>'before']);
        $this->color('field_border_color', 'Color del borde', '.obx-login__input', 'border-color');
        $this->dimensions('field_border_width', 'Grosor del borde', '.obx-login__input', 'border-width', ['size_units'=>['px','em','rem']], false);
        $this->dimensions('field_border_radius', 'Radio del borde', '.obx-login__input', 'border-radius', [], false);
        $this->end_controls_section();

        $this->start_controls_section('section_button_style', ['label'=>'Botón','tab'=>Controls_Manager::TAB_STYLE]);
        $this->start_controls_tabs('tabs_button_style');
        $this->start_controls_tab('tab_button_normal', ['label'=>'Normal']);
        $this->typography('button_typography', '.obx-login__button');
        $this->color('button_text_color', 'Color del texto', '.obx-login__button');
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'button_background','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>'{{WRAPPER}} .obx-login__button']);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'button_border','selector'=>'{{WRAPPER}} .obx-login__button','separator'=>'before']);
        $this->dimensions('button_border_radius', 'Radio del borde', '.obx-login__button', 'border-radius', [], false);
        $this->dimensions('button_text_padding', 'Relleno', '.obx-login__button', 'padding', [], false);
        $this->end_controls_tab();
        $this->start_controls_tab('tab_button_hover', ['label'=>'Al pasar el ratón']);
        $this->color('button_hover_color', 'Color del texto', '.obx-login__button:hover', 'color');
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'button_background_hover','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>'{{WRAPPER}} .obx-login__button:hover']);
        $this->color('button_hover_border_color', 'Borde al pasar el ratón', '.obx-login__button:hover', 'border-color', ['condition'=>['button_border_border!'=>'']]);
        $this->slider('button_hover_transition_duration', 'Duración de la transición', '.obx-login__button', 'transition-duration: {{SIZE}}{{UNIT}};', ['size_units'=>['ms','s'],'default'=>['unit'=>'ms'],'range'=>['ms'=>['max'=>3000]]]);
        $this->add_control('button_hover_animation', ['label'=>'Animación','type'=>Controls_Manager::HOVER_ANIMATION]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();

        $this->start_controls_section('section_style_message', ['label'=>'Mensaje con sesión iniciada','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['show_logged_in_message'=>'yes']]);
        $this->color('message_color', 'Color', '.obx-login--logged-in');
        $this->typography('message_typography', '.obx-login--logged-in');
        $this->end_controls_section();
    }

    private static function local_url($link, string $fallback): string {
        $url = is_array($link) ? (string) ($link['url'] ?? '') : '';
        return $url !== '' ? wp_validate_redirect($url, $fallback) : $fallback;
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $current = is_singular() ? (string) get_permalink() : home_url(add_query_arg([]));
        if (is_user_logged_in() && !$this->editing()) {
            if (($s['show_logged_in_message'] ?? 'yes') !== 'yes') { return; }
            $logout = ($s['redirect_after_logout'] ?? '') === 'yes' ? self::local_url($s['redirect_logout_url'] ?? [], $current) : $current;
            echo '<div class="obx-login obx-login--logged-in">Has iniciado sesión como <strong>' . esc_html(wp_get_current_user()->display_name) . '</strong> (<a href="' . esc_url(wp_logout_url($logout)) . '">cerrar sesión</a>)</div>';
            return;
        }
        $redirect = ($s['redirect_after_login'] ?? '') === 'yes' ? self::local_url($s['redirect_url'] ?? [], $current) : $current;
        $labels = ($s['show_labels'] ?? 'yes') === 'yes';
        $custom = ($s['custom_labels'] ?? '') === 'yes';
        $id = $this->get_id();
        $size = array_key_exists($s['input_size'] ?? '', self::sizes()) ? $s['input_size'] : 'sm';
        $field = static function (string $name, string $type, string $label, string $placeholder, string $auto) use ($labels, $id, $size): string {
            $field_id = 'obx-login-' . $name . '-' . $id;
            return '<div class="obx-login__field">' . ($labels ? '<label for="' . esc_attr($field_id) . '">' . esc_html($label) . '</label>' : '<label class="screen-reader-text" for="' . esc_attr($field_id) . '">' . esc_html($label) . '</label>')
                . '<input class="obx-login__input elementor-field elementor-size-' . esc_attr($size) . '" id="' . esc_attr($field_id) . '" type="' . $type . '" name="' . $name . '" autocomplete="' . $auto . '" required' . ($placeholder !== '' ? ' placeholder="' . esc_attr($placeholder) . '"' : '') . '></div>';
        };
        $user_label = $custom || $labels ? (string) ($s['user_label'] ?? '') : '';
        $password_label = $custom || $labels ? (string) ($s['password_label'] ?? '') : '';
        echo '<div class="obx-login"><form class="obx-login__form" method="post" action="' . esc_url(site_url('wp-login.php', 'login_post')) . '">';
        echo $field('log', 'text', $user_label ?: 'Usuario o correo electrónico', $custom ? (string) ($s['user_placeholder'] ?? '') : '', 'username');
        echo $field('pwd', 'password', $password_label ?: 'Contraseña', $custom ? (string) ($s['password_placeholder'] ?? '') : '', 'current-password');
        if (($s['show_remember_me'] ?? 'yes') === 'yes') { echo '<label class="obx-login__remember"><input type="checkbox" name="rememberme" value="forever"> Recuérdame</label>'; }
        $animation = ($s['button_hover_animation'] ?? '') ? ' elementor-animation-' . sanitize_html_class($s['button_hover_animation']) : '';
        echo '<div class="obx-login__submit"><button type="submit" name="wp-submit" class="' . esc_attr(self::button_classes('obx-login__button', $s['button_size'] ?? 'sm') . $animation) . '">' . esc_html(($s['button_text'] ?? '') ?: 'Acceder') . '</button></div>';
        echo '<input type="hidden" name="redirect_to" value="' . esc_attr($redirect) . '">';
        $links = [];
        if (($s['show_lost_password'] ?? 'yes') === 'yes') { $links[] = '<a href="' . esc_url(wp_lostpassword_url($redirect)) . '">¿Has olvidado tu contraseña?</a>'; }
        if (($s['show_register'] ?? 'yes') === 'yes' && get_option('users_can_register')) { $links[] = '<a href="' . esc_url(wp_registration_url()) . '">Registrarse</a>'; }
        if ($links) { echo '<p class="obx-login__links">' . implode('<span class="obx-login__sep" aria-hidden="true"> | </span>', $links) . '</p>'; }
        echo '</form></div>';
    }
}

final class Code_Highlight extends Element {
    public const PRO = 'code-highlight';
    protected const V03 = ['copy'=>'copy_to_clipboard'];
    /** Pro's keys, including its literal «visual-basic,»; then ours. */
    private const LANGUAGES = ['markup'=>'Markup','html'=>'HTML','css'=>'CSS','sass'=>'Sass (Sass)','scss'=>'Sass (Scss)','less'=>'Less','javascript'=>'JavaScript','typescript'=>'TypeScript','jsx'=>'React JSX','tsx'=>'React TSX','php'=>'PHP','ruby'=>'Ruby','json'=>'JSON','http'=>'HTTP','xml'=>'XML','svg'=>'SVG','rust'=>'Rust','csharp'=>'C#','dart'=>'Dart','git'=>'Git','java'=>'Java','sql'=>'SQL','go'=>'Go','kotlin'=>'Kotlin + Kotlin Script','julia'=>'Julia','python'=>'Python','swift'=>'Swift','bash'=>'Bash','scala'=>'Scala','haskell'=>'Haskell','perl'=>'Perl','objectivec'=>'Objective-C','visual-basic,'=>'Visual Basic + VBA','r'=>'R','c'=>'C','cpp'=>'C++','aspnet'=>'ASP.NET (C#)','yaml'=>'YAML','powershell'=>'PowerShell','markdown'=>'Markdown','text'=>'Texto'];
    public const THEMES = ['default'=>'Claro','dark'=>'Oscuro','okaidia'=>'Okaidia','solarizedlight'=>'Solarizado claro','tomorrow'=>'Noche','twilight'=>'Crepúsculo'];
    public function get_title(): string { return 'Resaltado de código'; }
    public function get_icon(): string { return 'eicon-code-highlight'; }
    public function get_keywords(): array { return ['code','codigo','highlight','resaltado','snippet','programacion']; }

    protected static function upgrade_settings(array $s): array {
        $s['line_numbers'] = ($s['line_numbers'] ?? 'yes') === 'yes' ? 'line-numbers' : '';
        $s['copy'] = ($s['copy'] ?? 'yes') === 'yes' ? 'copy-to-clipboard' : '';
        $s['theme'] = ($s['theme'] ?? 'dark') === 'light' ? 'default' : 'dark';
        $s['word_wrap'] = ($s['word_wrap'] ?? '') === 'yes' ? 'word-wrap' : '';
        return parent::upgrade_settings($s);
    }

    protected function register_controls(): void {
        $this->start_controls_section('section_content', ['label'=>'Código']);
        $this->add_control('language', ['label'=>'Lenguaje','type'=>Controls_Manager::SELECT2,'multiple'=>false,'default'=>'javascript','options'=>self::LANGUAGES]);
        $this->add_control('code', ['label'=>'Código','type'=>Controls_Manager::CODE,'language'=>'text','rows'=>12,'default'=>"console.log( 'Hola, mundo' );",'dynamic'=>['active'=>true]]);
        $this->add_control('line_numbers', ['label'=>'Números de línea','type'=>Controls_Manager::SWITCHER,'return_value'=>'line-numbers','default'=>'line-numbers']);
        $this->add_control('copy_to_clipboard', ['label'=>'Botón copiar','type'=>Controls_Manager::SWITCHER,'return_value'=>'copy-to-clipboard','default'=>'copy-to-clipboard']);
        $this->add_control('highlight_lines', ['label'=>'Resaltar líneas','type'=>Controls_Manager::TEXT,'placeholder'=>'1, 3-6']);
        $this->add_control('word_wrap', ['label'=>'Ajustar líneas largas','type'=>Controls_Manager::SWITCHER,'return_value'=>'word-wrap','default'=>'']);
        $this->add_control('theme', ['label'=>'Tema','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>self::THEMES,'separator'=>'before']);
        $this->slider('height', 'Altura', '.obx-code pre', 'max-height: {{SIZE}}{{UNIT}};', ['size_units'=>['px','vh','em','rem'],'range'=>['px'=>['min'=>115,'max'=>1000]]], true);
        $this->slider('font_size', 'Tamaño de letra', '.obx-code pre', 'font-size: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','vw'],'range'=>['px'=>['min'=>1,'max'=>100]]], true);
        $this->end_controls_section();
    }

    /** "1, 3-5" → [1,3,4,5] */
    private static function lines(string $spec): array {
        $lines = [];
        foreach (explode(',', $spec) as $part) {
            if (preg_match('/^\s*(\d+)\s*(?:-\s*(\d+))?\s*$/', $part, $m)) {
                $from = (int) $m[1]; $to = isset($m[2]) ? min((int) $m[2], $from + 1000) : $from;
                for ($i = $from; $i <= $to; $i++) { $lines[] = $i; }
            }
        }
        return array_values(array_unique($lines));
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $language = isset(self::LANGUAGES[$s['language'] ?? '']) ? $s['language'] : 'text';
        $theme = array_key_exists($s['theme'] ?? '', self::THEMES) ? $s['theme'] : 'default';
        $classes = 'obx-code obx-code--theme-' . $theme . (in_array($theme, ['dark','okaidia','tomorrow','twilight'], true) ? ' obx-code--dark' : ' obx-code--light')
            . (($s['line_numbers'] ?? '') !== '' ? ' obx-code--numbers' : '') . (($s['word_wrap'] ?? '') !== '' ? ' obx-code--wrap' : '');
        echo '<div class="' . esc_attr($classes) . '" data-language="' . esc_attr($language) . '" data-highlight="' . esc_attr(implode(',', self::lines((string) ($s['highlight_lines'] ?? '')))) . '">';
        echo '<div class="obx-code__bar"><span class="obx-code__language">' . esc_html(self::LANGUAGES[$language]) . '</span>';
        if (($s['copy_to_clipboard'] ?? '') !== '') { echo '<button class="obx-code__copy" type="button" data-done="Copiado">Copiar</button>'; }
        echo '</div><pre><code>' . esc_html((string) $s['code']) . '</code></pre></div>';
    }
}

/** Embeds a saved Elementor template. */
final class Template extends Element {
    public const PRO = 'template';
    private static array $rendering = [];
    public function get_title(): string { return 'Plantilla'; }
    public function get_icon(): string { return 'eicon-document-file'; }
    public function get_keywords(): array { return ['template','plantilla','guardada','library','biblioteca']; }
    protected function is_dynamic_content(): bool { return true; }
    public function get_script_depends(): array { return []; }
    protected function register_controls(): void {
        $skip = array_merge(['kit','obx-header','obx-footer','obx-single','obx-archive','obx-error-404'], array_keys(\OldBearExpansion\ThemeBuilder\Module::TYPES));
        $options = [];
        foreach (get_posts(['post_type'=>'elementor_library','post_status'=>'publish','posts_per_page'=>200,'orderby'=>'title','order'=>'ASC']) as $post) {
            $type = (string) get_post_meta($post->ID, '_elementor_template_type', true);
            if (in_array($type, $skip, true)) { continue; }
            $options[(string) $post->ID] = $post->post_title . ' (' . $type . ')';
        }
        $this->start_controls_section('section_template', ['label'=>'Plantilla']);
        $this->add_control('template_id', ['label'=>'Elige una plantilla','type'=>Controls_Manager::SELECT2,'options'=>$options,'label_block'=>true]);
        $this->end_controls_section();
    }
    protected function render(): void {
        $id = absint($this->get_settings_for_display('template_id'));
        if (!$id || get_post_type($id) !== 'elementor_library' || get_post_status($id) !== 'publish') { $this->placeholder('Elige una plantilla guardada.'); return; }
        if (isset(self::$rendering[$id])) { $this->placeholder('Una plantilla no puede incluirse a sí misma.'); return; }
        self::$rendering[$id] = true;
        echo '<div class="obx-template">' . Elementor::$instance->frontend->get_builder_content_for_display($id, true) . '</div>'; // Elementor output.
        unset(self::$rendering[$id]);
    }
}
