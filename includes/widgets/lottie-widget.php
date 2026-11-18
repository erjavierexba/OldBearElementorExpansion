<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as Elementor;

if (!defined('ABSPATH')) { exit; }

/**
 * Lottie: plays a Lottie (Bodymovin) JSON animation, uploaded to the media library or from an
 * external URL, on arriving to the viewport, on click, on hover, bound to the scroll or always.
 * Played by assets/js/lottie.js with the bundled lottie-web player (MIT).
 */
final class Lottie extends Element {
    public const PRO = 'lottie';

    public function get_title(): string { return 'Lottie'; }
    public function get_icon(): string { return 'eicon-lottie'; }
    public function get_keywords(): array { return ['lottie','animation','animación','bodymovin','json','svg']; }
    public function get_script_depends(): array { return ['obx-lottie']; }
    public function has_widget_inner_wrapper(): bool { return !Elementor::$instance->experiments->is_feature_active('e_optimized_markup'); }

    /** External URLs run any JSON from anywhere: only for users trusted to publish pages. */
    private static function can_use_external(): bool { return current_user_can('publish_pages'); }

    protected function register_controls(): void {
        $this->content_controls();
        $this->settings_controls();
        $this->style_controls();
        $this->caption_style_controls();
    }

    private function content_controls(): void {
        $this->start_controls_section('lottie', ['label'=>'Lottie']);
        $sources = ['media_file'=>'Archivo multimedia'];
        if (self::can_use_external()) { $sources['external_url'] = 'URL externa'; }
        $this->add_control('source', ['label'=>'Origen','type'=>Controls_Manager::SELECT,'default'=>'media_file','options'=>$sources,'frontend_available'=>true]);
        $this->add_control('source_external_url', [
            'label'=>'URL externa',
            'type'=>self::can_use_external() ? Controls_Manager::URL : Controls_Manager::HIDDEN,
            'condition'=>['source'=>'external_url'],
            'dynamic'=>['active'=>true],
            'default'=>['url'=>''],
            'placeholder'=>'Introduce la URL',
            'frontend_available'=>true,
        ]);
        $this->add_control('source_json', ['label'=>'Subir archivo JSON','type'=>Controls_Manager::MEDIA,'media_types'=>['application/json'],'frontend_available'=>true,'condition'=>['source'=>'media_file']]);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),'prefix_class'=>'elementor%s-align-','default'=>'center']);
        $this->add_control('caption_source', [
            'label'=>'Leyenda',
            'type'=>Controls_Manager::SELECT,
            'default'=>'none',
            'options'=>['none'=>'Ninguna','title'=>'Título','caption'=>'Leyenda','custom'=>'Personalizada'],
            'condition'=>['source!'=>'external_url','source_json[url]!'=>''],
            'frontend_available'=>true,
        ]);
        $this->add_control('caption', [
            'label'=>'Leyenda personalizada',
            'type'=>Controls_Manager::TEXT,
            'render_type'=>'none',
            'conditions'=>['relation'=>'or','terms'=>[['name'=>'caption_source','value'=>'custom'],['name'=>'source','value'=>'external_url']]],
            'dynamic'=>['active'=>true],
            'frontend_available'=>true,
        ]);
        $this->add_control('link_to', ['label'=>'Enlace','type'=>Controls_Manager::SELECT,'render_type'=>'none','default'=>'none','options'=>['none'=>'Ninguno','custom'=>'URL personalizada'],'frontend_available'=>true]);
        $this->add_control('custom_link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'render_type'=>'none','placeholder'=>'Introduce la URL','condition'=>['link_to'=>'custom'],'dynamic'=>['active'=>true],'default'=>['url'=>''],'frontend_available'=>true]);
        $this->end_controls_section();
    }

    private function settings_controls(): void {
        $this->start_controls_section('settings', ['label'=>'Ajustes']);
        $this->add_control('trigger', [
            'label'=>'Disparador',
            'type'=>Controls_Manager::SELECT,
            'default'=>'arriving_to_viewport',
            'options'=>['arriving_to_viewport'=>'Al entrar en pantalla','on_click'=>'Al hacer clic','on_hover'=>'Al pasar el ratón','bind_to_scroll'=>'Desplazamiento','none'=>'Ninguno'],
            'frontend_available'=>true,
        ]);
        $this->add_control('viewport', [
            'label'=>'Ventana gráfica',
            'type'=>Controls_Manager::SLIDER,
            'render_type'=>'none',
            'conditions'=>['relation'=>'or','terms'=>[['name'=>'trigger','operator'=>'===','value'=>'arriving_to_viewport'],['name'=>'trigger','operator'=>'===','value'=>'bind_to_scroll']]],
            'default'=>['sizes'=>['start'=>0,'end'=>100],'unit'=>'%'],
            'labels'=>['Abajo','Arriba'],
            'scales'=>1,
            'handles'=>'range',
            'frontend_available'=>true,
        ]);
        $this->add_control('effects_relative_to', ['label'=>'Efectos relativos a','type'=>Controls_Manager::SELECT,'render_type'=>'none','condition'=>['trigger'=>'bind_to_scroll'],'default'=>'viewport','options'=>['viewport'=>'Ventana gráfica','page'=>'Toda la página'],'frontend_available'=>true]);
        $this->add_control('loop', ['label'=>'Bucle','type'=>Controls_Manager::SWITCHER,'render_type'=>'none','condition'=>['trigger!'=>'bind_to_scroll'],'default'=>'','frontend_available'=>true]);
        $this->add_control('number_of_times', [
            'label'=>'Veces',
            'type'=>Controls_Manager::NUMBER,
            'render_type'=>'none',
            'conditions'=>['relation'=>'and','terms'=>[['name'=>'trigger','operator'=>'!==','value'=>'bind_to_scroll'],['name'=>'loop','operator'=>'===','value'=>'yes']]],
            'min'=>0,
            'step'=>1,
            'frontend_available'=>true,
        ]);
        $this->add_control('link_timeout', [
            'label'=>'Retardo del enlace (ms)',
            'type'=>Controls_Manager::NUMBER,
            'render_type'=>'none',
            'conditions'=>['relation'=>'and','terms'=>[['name'=>'link_to','operator'=>'===','value'=>'custom'],['name'=>'trigger','operator'=>'===','value'=>'on_click'],['name'=>'custom_link[url]','operator'=>'!==','value'=>'']]],
            'description'=>'Abre el enlace pasado este tiempo',
            'min'=>0,
            'max'=>5000,
            'step'=>1,
            'frontend_available'=>true,
        ]);
        $this->add_control('on_hover_out', ['label'=>'Al quitar el ratón','type'=>Controls_Manager::SELECT,'render_type'=>'none','condition'=>['trigger'=>'on_hover'],'default'=>'default','options'=>['default'=>'Predeterminado','reverse'=>'Invertir','pause'=>'Pausar'],'frontend_available'=>true]);
        $this->add_control('hover_area', ['label'=>'Zona del ratón','type'=>Controls_Manager::SELECT,'render_type'=>'none','condition'=>['trigger'=>'on_hover'],'default'=>'animation','options'=>['animation'=>'Animación','column'=>'Columna','section'=>'Sección','container'=>'Contenedor'],'frontend_available'=>true]);
        $this->add_control('play_speed', [
            'label'=>'Velocidad (x)',
            'type'=>Controls_Manager::SLIDER,
            'render_type'=>'none',
            'condition'=>['trigger!'=>'bind_to_scroll'],
            'default'=>['size'=>1],
            'range'=>['px'=>['min'=>0.1,'max'=>5,'step'=>0.1]],
            'size_units'=>['px'],
            'dynamic'=>['active'=>true],
            'frontend_available'=>true,
        ]);
        $this->add_control('start_point', ['label'=>'Punto de inicio','type'=>Controls_Manager::SLIDER,'frontend_available'=>true,'render_type'=>'none','default'=>['size'=>'0','unit'=>'%'],'size_units'=>['%']]);
        $this->add_control('end_point', ['label'=>'Punto final','type'=>Controls_Manager::SLIDER,'frontend_available'=>true,'render_type'=>'none','default'=>['size'=>'100','unit'=>'%'],'size_units'=>['%']]);
        $this->add_control('reverse_animation', [
            'label'=>'Invertir',
            'type'=>Controls_Manager::SWITCHER,
            'render_type'=>'none',
            'conditions'=>['relation'=>'and','terms'=>[['name'=>'trigger','operator'=>'!==','value'=>'bind_to_scroll'],['name'=>'trigger','operator'=>'!==','value'=>'on_hover']]],
            'default'=>'',
            'frontend_available'=>true,
        ]);
        $this->add_control('renderer', ['label'=>'Renderizador','type'=>Controls_Manager::SELECT,'default'=>'svg','options'=>['svg'=>'SVG','canvas'=>'Canvas'],'separator'=>'before','frontend_available'=>true]);
        $this->add_control('lazyload', ['label'=>'Carga diferida','type'=>Controls_Manager::SWITCHER,'default'=>'','frontend_available'=>true]);
        $this->end_controls_section();
    }

    private function style_controls(): void {
        $this->start_controls_section('style', ['label'=>'Lottie','tab'=>Controls_Manager::TAB_STYLE]);
        $range = ['px'=>['min'=>1,'max'=>1000],'%'=>['min'=>1,'max'=>100],'em'=>['max'=>100],'rem'=>['max'=>100],'vw'=>['min'=>1,'max'=>100]];
        $percent = ['unit'=>'%'];
        $this->add_responsive_control('width', ['label'=>'Ancho','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vw','custom'],'default'=>$percent,'tablet_default'=>$percent,'mobile_default'=>$percent,'range'=>$range,'selectors'=>['{{WRAPPER}}'=>'--lottie-container-width: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('space', ['label'=>'Ancho máximo','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vw','custom'],'default'=>$percent,'tablet_default'=>$percent,'mobile_default'=>$percent,'range'=>$range,'selectors'=>['{{WRAPPER}}'=>'--lottie-container-max-width: {{SIZE}}{{UNIT}};'],'separator'=>'after']);
        $this->start_controls_tabs('image_effects');
        $this->start_controls_tab('normal', ['label'=>'Normal']);
        $this->add_control('opacity', ['label'=>'Opacidad','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['max'=>1,'min'=>0.10,'step'=>0.01]],'selectors'=>['{{WRAPPER}}'=>'--lottie-container-opacity: {{SIZE}};']]);
        $this->add_group_control(Group_Control_Css_Filter::get_type(), ['name'=>'css_filters','selector'=>'{{WRAPPER}} .e-lottie__container']);
        $this->end_controls_tab();
        $this->start_controls_tab('hover', ['label'=>'Al pasar el ratón']);
        $this->add_control('opacity_hover', ['label'=>'Opacidad','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['max'=>1,'min'=>0.10,'step'=>0.01]],'selectors'=>['{{WRAPPER}}'=>'--lottie-container-opacity-hover: {{SIZE}};']]);
        $this->add_group_control(Group_Control_Css_Filter::get_type(), ['name'=>'css_filters_hover','selector'=>'{{WRAPPER}} .e-lottie__container:hover']);
        $this->add_control('background_hover_transition', ['label'=>'Duración de la transición (s)','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>0,'max'=>3,'step'=>0.1]],'selectors'=>['{{WRAPPER}}'=>'--lottie-container-transition-duration-hover: {{SIZE}}s']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();
    }

    private function caption_style_controls(): void {
        $this->start_controls_section('section_style_caption', ['label'=>'Leyenda','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['caption_source!'=>'none']]);
        $this->add_control('caption_align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),'default'=>'center','selectors'=>['{{WRAPPER}}'=>'--caption-text-align: {{VALUE}};']]);
        $this->add_control('text_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'default'=>'','selectors'=>['{{WRAPPER}}'=>'--caption-color: {{VALUE}};'],'global'=>['default'=>Global_Colors::COLOR_TEXT]]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'caption_typography','selector'=>'{{WRAPPER}} .e-lottie__caption','global'=>['default'=>Global_Typography::TYPOGRAPHY_TEXT]]);
        $this->add_responsive_control('caption_space', ['label'=>'Espaciado','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>100],'em'=>['max'=>10],'rem'=>['max'=>10]],'selectors'=>['{{WRAPPER}}'=>'--caption-margin-top: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    /** Custom text, or the uploaded file's title or caption (only for an attachment the visitor may read). */
    private static function caption(array $s): string {
        $source = (string) ($s['source'] ?? 'media_file');
        $type = (string) ($s['caption_source'] ?? 'none');
        if ('external_url' === $source) { return (string) ($s['caption'] ?? ''); }
        if ('custom' === $type) { return (string) ($s['caption'] ?? ''); }
        if (!in_array($type, ['title','caption'], true)) { return ''; }
        $post = get_post((int) ($s['source_json']['id'] ?? 0));
        if (!$post || 'attachment' !== $post->post_type) { return ''; }
        // Attachments of private posts are not shown to visitors who cannot read them.
        $parent = $post->post_parent ? get_post($post->post_parent) : null;
        if ($parent && 'publish' !== $parent->post_status && !current_user_can('read_post', $parent->ID)) { return ''; }
        return 'title' === $type ? get_the_title($post) : wp_get_attachment_caption($post->ID);
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $caption = self::caption($s);
        $html = '<div class="e-lottie__container"><div class="e-lottie__animation"></div>' . ($caption !== '' ? '<p class="e-lottie__caption"> ' . esc_html($caption) . '</p>' : '') . '</div>';
        if ('custom' === ($s['link_to'] ?? '') && !empty($s['custom_link']['url'])) {
            $this->add_link_attributes('url', $s['custom_link']);
            $html = '<a class="e-lottie__container__link" ' . $this->get_render_attribute_string('url') . '>' . $html . '</a>';
        }
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
    }

    protected function content_template(): void {
        ?>
        <#
        var attachment = function( id, type ) {
            if ( ! id || ( 'title' !== type && 'caption' !== type ) || ! window.wp || ! wp.media ) { return ''; }
            var model = wp.media.attachment( id );
            if ( undefined === model.get( type ) ) { model.fetch().then( function() { view.render(); } ); }
            return model.get( type ) || '';
        };
        var caption = '';
        if ( 'external_url' === settings.source || 'custom' === settings.caption_source ) {
            caption = settings.caption || '';
        } else if ( 'title' === settings.caption_source || 'caption' === settings.caption_source ) {
            caption = attachment( settings.source_json && settings.source_json.id, settings.caption_source );
        }
        var html = '<div class="e-lottie__container"><div class="e-lottie__animation"></div>' + ( caption ? '<p class="e-lottie__caption">' + _.escape( caption ) + '</p>' : '' ) + '</div>';
        if ( 'custom' === settings.link_to && settings.custom_link && settings.custom_link.url ) {
            html = '<a class="e-lottie__container__link" href="' + _.escape( elementor.helpers.sanitizeUrl( settings.custom_link.url ) ) + '">' + html + '</a>';
        }
        print( html );
        #>
        <?php
    }
}
