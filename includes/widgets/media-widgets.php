<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Icons_Manager;
use Elementor\Repeater;

if (!defined('ABSPATH')) { exit; }

/** Classic Pro Slides widget, rendered by the shared dependency-free carousel. */
final class Slides extends Element {
    use Carousel;
    public const PRO = 'slides';
    /** Keys of this widget before 0.11.0. */
    private const OLD_KEYS = ['show_arrows','pagination','height','content_padding','speed','pagination_size','pagination_color','pagination_color_inactive','button_color','button_bg','button_hover_bg','button_radius','button_padding','slides_to_show','slides_to_scroll'];
    private const ANIMATIONS = ['fadeInDown','fadeInUp','fadeInRight','fadeInLeft','zoomIn'];
    public function get_title(): string { return 'Diapositivas'; }
    public function get_icon(): string { return 'eicon-slides'; }
    public function get_keywords(): array { return ['slides','slider','diapositivas','carrusel','hero']; }

    public static function wants_upgrade(string $name, array $settings): bool { return $name === self::PRO && (bool) array_intersect_key($settings, array_flip(self::OLD_KEYS)); }

    protected static function upgrade_settings(array $s): array {
        if (array_key_exists('show_arrows', $s) || array_key_exists('pagination', $s)) {
            $arrows = ($s['show_arrows'] ?? 'yes') === 'yes';
            $dots = ($s['pagination'] ?? 'bullets') !== '';
            $s['navigation'] = $arrows ? ($dots ? 'both' : 'arrows') : ($dots ? 'dots' : 'none');
        }
        $s = self::rename($s, ['height'=>'slides_height','content_padding'=>'slides_padding','speed'=>'transition_speed','pagination_size'=>'dots_size','pagination_color'=>'dots_color','pagination_color_inactive'=>'dots_color_inactive','button_color'=>'button_text_color','button_hover_color'=>'button_hover_text_color']);
        foreach (['button_bg'=>'button_background','button_hover_bg'=>'button_hover_background'] as $old => $group) {
            if (!empty($s[$old]) && !isset($s[$group . '_color'])) { $s[$group . '_background'] = 'classic'; $s[$group . '_color'] = $s[$old]; }
        }
        if (isset($s['button_radius']['top']) && $s['button_radius']['top'] !== '' && !isset($s['button_border_radius'])) { $s['button_border_radius'] = ['unit'=>$s['button_radius']['unit'] ?? 'px','size'=>(float) $s['button_radius']['top'],'sizes'=>[]]; }
        foreach (['button_bg','button_hover_bg','button_radius','button_padding','show_arrows','pagination','slides_to_show','slides_to_scroll'] as $key) { unset($s[$key]); }
        foreach ((array) ($s['slides'] ?? []) as $i => $slide) {
            if (!is_array($slide)) { continue; }
            if (($slide['background_overlay_background'] ?? '') !== '' && !empty($slide['background_overlay_color'])) { $slide['background_overlay'] = 'yes'; }
            if (isset($slide['horizontal_position']) || isset($slide['vertical_position']) || isset($slide['text_align'])) { $slide['custom_style'] = 'yes'; }
            if (isset($slide['horizontal_position'])) { $slide['horizontal_position'] = ['start'=>'left','end'=>'right'][$slide['horizontal_position']] ?? $slide['horizontal_position']; }
            unset($slide['background_background'], $slide['background_overlay_background']);
            $s['slides'][$i] = $slide;
        }
        return $s;
    }

    protected function register_controls(): void {
        $h = ['left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-h-align-center'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right']];
        $v = ['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'middle'=>['title'=>'Centro','icon'=>'eicon-v-align-middle'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']];
        $hd = ['left'=>'flex-start','center'=>'center','right'=>'flex-end'];
        $vd = ['top'=>'flex-start','middle'=>'center','bottom'=>'flex-end'];

        $this->start_controls_section('section_slides', ['label'=>'Diapositivas']);
        $this->add_control('slides_name', ['label'=>'Nombre del carrusel','type'=>Controls_Manager::TEXT,'default'=>'Diapositivas','description'=>'Nombre accesible del carrusel.']);
        $r = new Repeater();
        $item = '{{WRAPPER}} {{CURRENT_ITEM}}';
        $r->start_controls_tabs('slides_repeater');
        $r->start_controls_tab('background', ['label'=>'Fondo']);
        $r->add_control('background_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'default'=>'#bbbbbb','selectors'=>[$item . ' .obx-slide__bg'=>'background-color: {{VALUE}};']]);
        $r->add_control('background_image', ['label'=>'Imagen','type'=>Controls_Manager::MEDIA,'dynamic'=>['active'=>true],'selectors'=>[$item . ' .obx-slide__bg'=>'background-image: url({{URL}});']]);
        $has_image = ['background_image[url]!'=>''];
        $r->add_control('background_size', ['label'=>'Tamaño','type'=>Controls_Manager::SELECT,'default'=>'cover','options'=>['cover'=>'Cubrir','contain'=>'Contener','auto'=>'Automático'],'selectors'=>[$item . ' .obx-slide__bg'=>'background-size: {{VALUE}};'],'condition'=>$has_image]);
        $r->add_control('background_ken_burns', ['label'=>'Efecto Ken Burns','type'=>Controls_Manager::SWITCHER,'separator'=>'before','condition'=>$has_image]);
        $r->add_control('zoom_direction', ['label'=>'Zoom','type'=>Controls_Manager::SELECT,'default'=>'in','options'=>['in'=>'Acercar','out'=>'Alejar'],'condition'=>$has_image + ['background_ken_burns!'=>'']]);
        $r->add_control('background_overlay', ['label'=>'Superposición','type'=>Controls_Manager::SWITCHER,'separator'=>'before','condition'=>$has_image]);
        $r->add_control('background_overlay_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'default'=>'rgba(0,0,0,0.5)','selectors'=>[$item . ' .obx-slide__overlay'=>'background-color: {{VALUE}};'],'condition'=>$has_image + ['background_overlay'=>'yes']]);
        $r->add_control('background_overlay_blend_mode', ['label'=>'Modo de fusión','type'=>Controls_Manager::SELECT,'options'=>[''=>'Normal','multiply'=>'Multiplicar','screen'=>'Trama','overlay'=>'Superponer','darken'=>'Oscurecer','lighten'=>'Aclarar','color-dodge'=>'Sobreexponer color','color-burn'=>'Subexponer color','hue'=>'Tono','saturation'=>'Saturación','color'=>'Color','exclusion'=>'Exclusión','luminosity'=>'Luminosidad'],'selectors'=>[$item . ' .obx-slide__overlay'=>'mix-blend-mode: {{VALUE}};'],'condition'=>$has_image + ['background_overlay'=>'yes']]);
        $r->end_controls_tab();
        $r->start_controls_tab('content', ['label'=>'Contenido']);
        $r->add_control('heading', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Título de la diapositiva','label_block'=>true,'dynamic'=>['active'=>true]]);
        $r->add_control('description', ['label'=>'Descripción','type'=>Controls_Manager::TEXTAREA,'default'=>'Cuenta aquí una idea importante en una o dos frases.','show_label'=>false,'dynamic'=>['active'=>true]]);
        $r->add_control('button_text', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Saber más','dynamic'=>['active'=>true]]);
        $r->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true]]);
        $r->add_control('link_click', ['label'=>'Aplicar el enlace a','type'=>Controls_Manager::SELECT,'default'=>'slide','options'=>['slide'=>'Toda la diapositiva','button'=>'Solo el botón'],'condition'=>['link[url]!'=>'']]);
        $r->end_controls_tab();
        $r->start_controls_tab('style', ['label'=>'Estilo']);
        $r->add_control('custom_style', ['label'=>'Estilo propio','type'=>Controls_Manager::SWITCHER,'description'=>'Cambia el estilo de esta diapositiva.']);
        $custom = ['custom_style'=>'yes'];
        $r->add_control('horizontal_position', ['label'=>'Posición horizontal','type'=>Controls_Manager::CHOOSE,'options'=>$h,'selectors_dictionary'=>$hd,'selectors'=>[$item . ' .obx-slide'=>'justify-content: {{VALUE}};'],'condition'=>$custom]);
        $r->add_control('vertical_position', ['label'=>'Posición vertical','type'=>Controls_Manager::CHOOSE,'options'=>$v,'selectors_dictionary'=>$vd,'selectors'=>[$item . ' .obx-slide'=>'align-items: {{VALUE}};'],'condition'=>$custom]);
        $r->add_control('text_align', ['label'=>'Alineación del texto','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(),'selectors'=>[$item . ' .obx-slide__content'=>'text-align: {{VALUE}};'],'condition'=>$custom]);
        $r->add_control('content_color', ['label'=>'Color del contenido','type'=>Controls_Manager::COLOR,'selectors'=>[$item . ' .obx-slide__heading, ' . $item . ' .obx-slide__description'=>'color: {{VALUE}};',$item . ' .obx-slide__button.elementor-button'=>'color: {{VALUE}}; border-color: {{VALUE}};'],'condition'=>$custom]);
        $r->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'repeater_text_shadow','selector'=>$item . ' .obx-slide__content','condition'=>$custom]);
        $r->end_controls_tab();
        $r->end_controls_tabs();
        $sample = 'Una frase corta que presenta la idea de esta diapositiva.';
        $this->add_control('slides', ['label'=>'Diapositivas','type'=>Controls_Manager::REPEATER,'fields'=>$r->get_controls(),'show_label'=>true,'default'=>[
            ['heading'=>'Título de la diapositiva 1','description'=>$sample,'button_text'=>'Saber más','background_color'=>'#833ca3'],
            ['heading'=>'Título de la diapositiva 2','description'=>$sample,'button_text'=>'Saber más','background_color'=>'#4054b2'],
            ['heading'=>'Título de la diapositiva 3','description'=>$sample,'button_text'=>'Saber más','background_color'=>'#1abc9c'],
        ],'title_field'=>'{{{ heading }}}']);
        $this->add_responsive_control('slides_height', ['label'=>'Altura','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','vh','custom'],'default'=>['unit'=>'px','size'=>400],'range'=>['px'=>['min'=>100,'max'=>1000],'vh'=>['min'=>10,'max'=>100]],'selectors'=>self::css('.obx-slide', 'height: {{SIZE}}{{UNIT}};'),'separator'=>'before']);
        $this->add_control('slides_title_tag', ['label'=>'Etiqueta HTML del título','type'=>Controls_Manager::SELECT,'default'=>'div','options'=>self::heading_tags(),'separator'=>'before']);
        $this->add_control('slides_description_tag', ['label'=>'Etiqueta HTML de la descripción','type'=>Controls_Manager::SELECT,'default'=>'div','options'=>self::heading_tags()]);
        $this->end_controls_section();

        $this->start_controls_section('section_slider_options', ['label'=>'Opciones del carrusel']);
        $this->add_control('navigation', ['label'=>'Navegación','type'=>Controls_Manager::SELECT,'default'=>'both','options'=>['both'=>'Flechas y puntos','arrows'=>'Flechas','dots'=>'Puntos','none'=>'Ninguna'],'frontend_available'=>true]);
        $this->add_control('autoplay', ['label'=>'Reproducción automática','type'=>Controls_Manager::SWITCHER,'default'=>'yes','separator'=>'before','frontend_available'=>true]);
        $this->add_control('pause_on_hover', ['label'=>'Pausar al pasar el ratón','type'=>Controls_Manager::SWITCHER,'default'=>'yes','condition'=>['autoplay!'=>''],'frontend_available'=>true]);
        $this->add_control('pause_on_interaction', ['label'=>'Pausar al interactuar','type'=>Controls_Manager::SWITCHER,'default'=>'yes','condition'=>['autoplay!'=>''],'frontend_available'=>true]);
        $this->add_control('autoplay_speed', ['label'=>'Intervalo (ms)','type'=>Controls_Manager::NUMBER,'default'=>5000,'condition'=>['autoplay'=>'yes'],'frontend_available'=>true]);
        $this->add_control('infinite', ['label'=>'Bucle infinito','type'=>Controls_Manager::SWITCHER,'default'=>'yes','frontend_available'=>true]);
        $this->add_control('transition', ['label'=>'Transición','type'=>Controls_Manager::SELECT,'default'=>'slide','options'=>['slide'=>'Deslizar','fade'=>'Fundido'],'frontend_available'=>true]);
        $this->add_control('transition_speed', ['label'=>'Duración de la transición (ms)','type'=>Controls_Manager::NUMBER,'default'=>500,'frontend_available'=>true]);
        $this->add_control('content_animation', ['label'=>'Animación del contenido','type'=>Controls_Manager::SELECT,'default'=>'fadeInUp','options'=>['fadeInDown'=>'Aparecer desde arriba','fadeInUp'=>'Aparecer desde abajo','fadeInRight'=>'Aparecer desde la derecha','fadeInLeft'=>'Aparecer desde la izquierda','zoomIn'=>'Acercar','' =>'Ninguna']]);
        $this->end_controls_section();

        $this->start_controls_section('section_style_slides', ['label'=>'Diapositivas','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('content_max_width', ['label'=>'Ancho del contenido','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vw','custom'],'default'=>['unit'=>'%','size'=>66],'range'=>['px'=>['max'=>1000],'%'=>['max'=>100]],'selectors'=>self::css('.obx-slide__content', 'max-width: {{SIZE}}{{UNIT}};')]);
        $this->add_responsive_control('slides_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'selectors'=>self::css('.obx-slide', 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};')]);
        $this->add_control('slides_horizontal_position', ['label'=>'Posición horizontal','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>$h,'selectors_dictionary'=>$hd,'selectors'=>self::css('.obx-slide', 'justify-content: {{VALUE}};')]);
        $this->add_control('slides_vertical_position', ['label'=>'Posición vertical','type'=>Controls_Manager::CHOOSE,'default'=>'middle','options'=>$v,'selectors_dictionary'=>$vd,'selectors'=>self::css('.obx-slide', 'align-items: {{VALUE}};')]);
        $this->add_control('slides_text_align', ['label'=>'Alineación del texto','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>self::logical_align(),'selectors'=>self::css('.obx-slide__content', 'text-align: {{VALUE}};')]);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'text_shadow','selector'=>'{{WRAPPER}} .obx-slide__content']);
        $this->end_controls_section();

        foreach (['title'=>['heading', 'Título'],'description'=>['description', 'Descripción']] as $section => [$p, $label]) {
            $this->start_controls_section('section_style_' . $section, ['label'=>$label,'tab'=>Controls_Manager::TAB_STYLE]);
            $this->slider($p . '_spacing', 'Separación', '.obx-slide__content .obx-slide__' . $p . ':not(:last-child)', 'margin-bottom: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>100]]], true);
            $this->color($p . '_color', 'Color', '.obx-slide__' . $p);
            $this->typography($p . '_typography', '.obx-slide__' . $p);
            $this->end_controls_section();
        }

        $button = '{{WRAPPER}} .obx-slide__button.elementor-button';
        $this->start_controls_section('section_style_button', ['label'=>'Botón','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('button_size', ['label'=>'Tamaño','type'=>Controls_Manager::SELECT,'default'=>'sm','options'=>self::sizes()]);
        $this->typography('button_typography', '.obx-slide__button.elementor-button');
        $this->slider('button_border_width', 'Grosor del borde', '.obx-slide__button.elementor-button', 'border-width: {{SIZE}}{{UNIT}};', ['size_units'=>['px','%','em','rem','vw','custom'],'range'=>['px'=>['max'=>20]]]);
        $this->slider('button_border_radius', 'Radio del borde', '.obx-slide__button.elementor-button', 'border-radius: {{SIZE}}{{UNIT}};', ['size_units'=>['px','%','em','rem','custom'],'range'=>['px'=>['max'=>100]],'separator'=>'after']);
        $this->start_controls_tabs('button_tabs');
        foreach (['normal'=>['', $button, 'Normal'],'hover'=>['hover_', $button . ':hover, ' . $button . ':focus-visible', 'Al pasar el ratón']] as $state => [$p, $selector, $label]) {
            $this->start_controls_tab('button_' . $state, ['label'=>$label]);
            $this->add_control('button_' . $p . 'text_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>[$selector=>'color: {{VALUE}};']]);
            $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'button_' . $p . 'background','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>$selector,'fields_options'=>['background'=>['default'=>'classic']]]);
            $this->add_control('button_' . $p . 'border_color', ['label'=>'Color del borde','type'=>Controls_Manager::COLOR,'selectors'=>[$selector=>'border-color: {{VALUE}};']]);
            if ($state === 'hover') {
                $this->add_control('button_hover_transition_duration', ['label'=>'Duración de la transición','type'=>Controls_Manager::SLIDER,'size_units'=>['s','ms','custom'],'default'=>['unit'=>'ms'],'selectors'=>[$button=>'transition-duration: {{SIZE}}{{UNIT}};']]);
            }
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->end_controls_section();

        $arrows = ['navigation'=>['arrows','both']];
        $dots = ['navigation'=>['dots','both']];
        $this->start_controls_section('section_style_navigation', ['label'=>'Navegación','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['navigation'=>['arrows','dots','both']]]);
        $this->add_control('heading_style_arrows', ['label'=>'Flechas','type'=>Controls_Manager::HEADING,'condition'=>$arrows]);
        $this->add_control('arrows_position', ['label'=>'Posición','type'=>Controls_Manager::SELECT,'default'=>'inside','options'=>['inside'=>'Dentro','outside'=>'Fuera'],'prefix_class'=>'obx-carousel-arrows-','condition'=>$arrows]);
        $this->slider('arrows_size', 'Tamaño', '.obx-carousel__arrow', 'font-size: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','custom'],'range'=>['px'=>['min'=>20,'max'=>60]],'condition'=>$arrows]);
        $this->color('arrows_color', 'Color', '.obx-carousel__arrow', 'color', ['condition'=>$arrows]);
        $this->add_control('heading_style_dots', ['label'=>'Puntos','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>$dots]);
        $this->add_control('dots_position', ['label'=>'Posición','type'=>Controls_Manager::SELECT,'default'=>'inside','options'=>['outside'=>'Fuera','inside'=>'Dentro'],'prefix_class'=>'obx-carousel-dots-','condition'=>$dots]);
        $this->slider('dots_gap', 'Separación', '.obx-carousel__pagination--bullets', 'gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>40]],'condition'=>$dots]);
        $this->slider('dots_size', 'Tamaño', '.obx-carousel__pagination', '--obx-dot: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','custom'],'range'=>['px'=>['min'=>5,'max'=>15]],'condition'=>$dots]);
        $this->color('dots_color_inactive', 'Color', '.obx-carousel__pagination', '--obx-dot-inactive', ['condition'=>$dots]);
        $this->color('dots_color', 'Color activo', '.obx-carousel__pagination', '--obx-dot-active', ['condition'=>$dots]);
        $this->end_controls_section();
    }

    protected function render(): void {
        $s = $this->get_settings_for_display(); $slides = array_values(array_filter((array) ($s['slides'] ?? []), 'is_array'));
        if (!$slides) { $this->placeholder('Añade alguna diapositiva.'); return; }
        $nav = (string) ($s['navigation'] ?? 'both');
        $options = ['autoplay_speed'=>$s['autoplay_speed'] ?? 5000,'speed'=>$s['transition_speed'] ?? 500] + $s;
        $data = json_decode($this->carousel_options($options, 'arrows', 'infinite'), true) + ['fade'=>($s['transition'] ?? 'slide') === 'fade'];
        $animation = in_array($s['content_animation'] ?? 'fadeInUp', self::ANIMATIONS, true) ? (string) ($s['content_animation'] ?? 'fadeInUp') : '';
        $title_tag = self::pick_tag($s['slides_title_tag'] ?? 'div', 'div');
        $text_tag = self::pick_tag($s['slides_description_tag'] ?? 'div', 'div');
        $size = array_key_exists((string) ($s['button_size'] ?? 'sm'), self::sizes()) ? (string) ($s['button_size'] ?? 'sm') : 'sm';
        echo '<div class="obx-carousel obx-slides' . ($animation ? ' obx-slides--animate obx-slides--' . esc_attr($animation) : '') . '" data-carousel="' . esc_attr((string) wp_json_encode($data)) . '" role="region" aria-roledescription="carrusel" aria-label="' . esc_attr((string) ($s['slides_name'] ?? 'Diapositivas')) . '" style="--obx-speed:' . max(0, (int) ($s['transition_speed'] ?? 500)) . 'ms"><div class="obx-carousel__track">';
        foreach ($slides as $i => $slide) {
            $id = sanitize_html_class((string) ($slide['_id'] ?? $i));
            $link = is_array($slide['link'] ?? null) && !empty($slide['link']['url']) ? $slide['link'] : null;
            $whole = $link && ($slide['link_click'] ?? 'slide') !== 'button';
            $bg = 'obx-slide__bg';
            if (!empty($slide['background_image']['url']) && ($slide['background_ken_burns'] ?? '') === 'yes') { $bg .= ' obx-slide__bg--ken-burns obx-slide__bg--zoom-' . (($slide['zoom_direction'] ?? 'in') === 'out' ? 'out' : 'in'); }
            echo '<div class="obx-carousel__slide elementor-repeater-item-' . esc_attr($id) . '"><div class="' . esc_attr($bg) . '"></div>';
            if (!empty($slide['background_image']['url']) && ($slide['background_overlay'] ?? '') === 'yes') { echo '<div class="obx-slide__overlay"></div>'; }
            if ($whole) { $this->add_link_attributes('slide_' . $id, $link); $this->add_render_attribute('slide_' . $id, 'class', 'obx-slide'); echo '<a ' . $this->get_render_attribute_string('slide_' . $id) . '>'; } else { echo '<div class="obx-slide">'; }
            echo '<div class="obx-slide__content">';
            if ((string) ($slide['heading'] ?? '') !== '') { echo '<' . $title_tag . ' class="obx-slide__heading">' . esc_html($slide['heading']) . '</' . $title_tag . '>'; }
            if ((string) ($slide['description'] ?? '') !== '') { echo '<' . $text_tag . ' class="obx-slide__description">' . wp_kses_post((string) $slide['description']) . '</' . $text_tag . '>'; }
            if ((string) ($slide['button_text'] ?? '') !== '') {
                $class = 'obx-slide__button elementor-button elementor-size-' . $size;
                echo ($link && !$whole ? $this->open_link('button_' . $id, $link, $class, 'div') : '<div class="' . esc_attr($class) . '">') . esc_html($slide['button_text']) . ($link && !$whole ? '</a>' : '</div>');
            }
            echo '</div>' . ($whole ? '</a>' : '</div>') . '</div>';
        }
        echo '</div>' . $this->carousel_controls_html(['arrows'=>in_array($nav, ['arrows','both'], true) ? 'yes' : '','pagination'=>in_array($nav, ['dots','both'], true) ? 'bullets' : ''], 'arrows') . '</div>';
    }
}

/** Image/video carousel using Elementor Pro's `media-carousel` data keys. */
final class Media_Carousel extends Element {
    use Carousel;
    public const PRO = 'media-carousel';
    public function get_title(): string { return 'Carrusel de medios'; }
    public function get_icon(): string { return 'eicon-media-carousel'; }
    public function get_keywords(): array { return ['media','medios','image','video','carousel','carrusel']; }

    protected function register_controls(): void {
        $this->start_controls_section('section_slides', ['label'=>'Medios']);
        $r = new Repeater();
        $r->add_control('image', ['label'=>'Imagen','type'=>Controls_Manager::MEDIA,'dynamic'=>['active'=>true]]);
        $r->add_control('video_url', ['label'=>'Vídeo (URL)','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true]]);
        $r->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true]]);
        $this->add_control('slides', ['type'=>Controls_Manager::REPEATER,'fields'=>$r->get_controls(),'default'=>[],'title_field'=>'Medio #{{{ _id }}}']);
        $this->add_group_control(Group_Control_Image_Size::get_type(), ['name'=>'thumbnail','default'=>'large']);
        $this->add_control('skin', ['label'=>'Apariencia','type'=>Controls_Manager::SELECT,'default'=>'carousel','options'=>['carousel'=>'Carrusel','slideshow'=>'Presentación','coverflow'=>'Portadas'],'prefix_class'=>'obx-media--skin-']);
        $this->add_control('link_to', ['label'=>'Enlazar a','type'=>Controls_Manager::SELECT,'default'=>'file','options'=>['none'=>'Nada','file'=>'Archivo multimedia','custom'=>'Enlace personalizado']]);
        $this->add_control('open_lightbox', ['label'=>'Caja de luz','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>['default'=>'Por defecto','yes'=>'Sí','no'=>'No'],'condition'=>['link_to'=>'file']]);
        $this->end_controls_section();

        $this->start_controls_section('section_carousel_options', ['label'=>'Carrusel']);
        $this->carousel_controls(['show'=>'slides_per_view','scroll'=>'slides_to_scroll','gap'=>'space_between','arrows'=>'show_arrows','loop'=>'loop'], ['show'=>'3','show_tablet'=>'2','show_mobile'=>'1']);
        $this->add_control('image_stretch', ['label'=>'Estirar imagen','type'=>Controls_Manager::SWITCHER]);
        $this->end_controls_section();
        $this->start_controls_section('section_style_image', ['label'=>'Imagen','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('space_between', 'Separación', '.obx-carousel', '--obx-gap: {{SIZE}}{{UNIT}};', ['default'=>['size'=>10]], true);
        $this->slider('height', 'Altura', '.obx-media__image', 'height: {{SIZE}}{{UNIT}};', ['size_units'=>['px','vh'],'default'=>['unit'=>'px','size'=>300]], true);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'image_border','selector'=>'{{WRAPPER}} .obx-media__image']);
        $this->dimensions('border_radius', 'Radio del borde', '.obx-media__image', 'border-radius');
        $this->end_controls_section();
        $this->carousel_navigation_style('show_arrows');
    }

    protected function render(): void {
        $s = $this->get_settings_for_display(); $slides = array_values(array_filter((array) ($s['slides'] ?? []), 'is_array'));
        if (!$slides) { $this->placeholder('Añade imágenes o vídeos.'); return; }
        echo '<div class="obx-carousel obx-media" data-carousel="' . esc_attr($this->carousel_options($s, 'show_arrows', 'loop')) . '" role="region" aria-roledescription="carrusel" aria-label="Medios"><div class="obx-carousel__track">';
        foreach ($slides as $i => $slide) {
            $image = (array) ($slide['image'] ?? []); $id = (int) ($image['id'] ?? 0); $src = $id ? Group_Control_Image_Size::get_attachment_image_src($id, 'thumbnail', $s) : (string) ($image['url'] ?? '');
            if ($src === '') { continue; }
            $file = $id ? (string) wp_get_attachment_url($id) : $src; $video = (string) (($slide['video_url']['url'] ?? '') ?: '');
            $href = ''; $attrs = '';
            if (($s['link_to'] ?? 'file') === 'file') { $href = $video ?: $file; $attrs = ' data-elementor-open-lightbox="' . esc_attr((string) ($s['open_lightbox'] ?? 'default')) . '" data-elementor-lightbox-slideshow="media-' . esc_attr($this->get_id()) . '"' . ($video ? ' data-elementor-lightbox-video="' . esc_attr($video) . '"' : ''); }
            elseif (($s['link_to'] ?? '') === 'custom') { $href = (string) ($slide['link']['url'] ?? ''); }
            $tag = $href ? 'a' : 'div';
            echo '<div class="obx-carousel__slide"><' . $tag . ' class="obx-media__item"' . ($href ? ' href="' . esc_url($href) . '"' . $attrs : '') . '><img class="obx-media__image" src="' . esc_url($src) . '" alt="' . esc_attr($id ? (string) get_post_meta($id, '_wp_attachment_image_alt', true) : '') . '" loading="lazy">' . ($video ? '<span class="obx-media__play" aria-hidden="true">▶</span>' : '') . '</' . $tag . '></div>';
        }
        echo '</div>' . $this->carousel_controls_html($s, 'show_arrows') . '</div>';
    }
}
