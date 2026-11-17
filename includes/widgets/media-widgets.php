<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Image_Size;
use Elementor\Icons_Manager;
use Elementor\Repeater;

if (!defined('ABSPATH')) { exit; }

/** Classic Pro Slides widget, rendered by the shared dependency-free carousel. */
final class Slides extends Element {
    use Carousel;
    public const PRO = 'slides';
    public function get_title(): string { return 'Diapositivas'; }
    public function get_icon(): string { return 'eicon-slides'; }
    public function get_keywords(): array { return ['slides','slider','diapositivas','carrusel','hero']; }

    protected function register_controls(): void {
        $this->start_controls_section('section_slides', ['label'=>'Diapositivas']);
        $r = new Repeater();
        $r->start_controls_tabs('slide_background_tabs');
        $r->start_controls_tab('background_normal', ['label'=>'Fondo']);
        $r->add_group_control(Group_Control_Background::get_type(), ['name'=>'background','types'=>['classic','gradient'],'selector'=>'{{WRAPPER}} {{CURRENT_ITEM}} .obx-slide__background']);
        $r->end_controls_tab();
        $r->start_controls_tab('background_overlay', ['label'=>'Superposición']);
        $r->add_group_control(Group_Control_Background::get_type(), ['name'=>'background_overlay','types'=>['classic','gradient'],'selector'=>'{{WRAPPER}} {{CURRENT_ITEM}} .obx-slide__overlay']);
        $r->end_controls_tab();
        $r->end_controls_tabs();
        $r->add_control('heading', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Título de la diapositiva','dynamic'=>['active'=>true],'label_block'=>true]);
        $r->add_control('description', ['label'=>'Descripción','type'=>Controls_Manager::TEXTAREA,'default'=>'Cuenta aquí una idea importante.','dynamic'=>['active'=>true]]);
        $r->add_control('button_text', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Saber más','dynamic'=>['active'=>true]]);
        $r->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true]]);
        $r->add_control('horizontal_position', ['label'=>'Posición horizontal','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>self::logical_align()]);
        $r->add_control('vertical_position', ['label'=>'Posición vertical','type'=>Controls_Manager::CHOOSE,'default'=>'middle','options'=>['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'middle'=>['title'=>'Centro','icon'=>'eicon-v-align-middle'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']]]);
        $r->add_control('text_align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>self::logical_align()]);
        $this->add_control('slides', ['type'=>Controls_Manager::REPEATER,'fields'=>$r->get_controls(),'default'=>[['heading'=>'Descubre una nueva historia','description'=>'Crea una portada visual con texto y llamada a la acción.','button_text'=>'Saber más'],['heading'=>'Diseño adaptable','description'=>'Cada diapositiva funciona en escritorio, tableta y móvil.','button_text'=>'Explorar']],'title_field'=>'{{{ heading }}}']);
        $this->end_controls_section();

        $this->start_controls_section('section_slider_options', ['label'=>'Carrusel']);
        $this->carousel_controls(['show'=>'slides_to_show','scroll'=>'slides_to_scroll','gap'=>'space_between','arrows'=>'show_arrows','loop'=>'infinite'], ['show'=>'1']);
        $this->add_control('transition', ['label'=>'Transición','type'=>Controls_Manager::SELECT,'default'=>'slide','options'=>['slide'=>'Deslizar','fade'=>'Fundido'],'prefix_class'=>'obx-slides--transition-']);
        $this->add_responsive_control('height', ['label'=>'Altura','type'=>Controls_Manager::SLIDER,'size_units'=>['px','vh'],'default'=>['unit'=>'px','size'=>500],'selectors'=>self::css('.obx-slide', 'min-height: {{SIZE}}{{UNIT}};')]);
        $this->end_controls_section();

        $this->start_controls_section('section_style_slides', ['label'=>'Contenido','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('content_max_width', 'Ancho del contenido', '.obx-slide__content', 'max-width: {{SIZE}}{{UNIT}};', ['size_units'=>['px','%'],'default'=>['unit'=>'%','size'=>70]], true);
        $this->dimensions('content_padding', 'Relleno', '.obx-slide', 'padding');
        $this->color('heading_color', 'Color del título', '.obx-slide__heading');
        $this->typography('heading_typography', '.obx-slide__heading');
        $this->color('description_color', 'Color de la descripción', '.obx-slide__description');
        $this->typography('description_typography', '.obx-slide__description');
        $this->end_controls_section();
        $this->button_style('.obx-slide__button', 'Botón', 'button');
        $this->carousel_navigation_style('show_arrows');
    }

    protected function render(): void {
        $s = $this->get_settings_for_display(); $slides = array_values(array_filter((array) ($s['slides'] ?? []), 'is_array'));
        if (!$slides) { $this->placeholder('Añade alguna diapositiva.'); return; }
        echo '<div class="obx-carousel obx-slides" data-carousel="' . esc_attr($this->carousel_options($s, 'show_arrows', 'infinite')) . '" role="region" aria-roledescription="carrusel" aria-label="Diapositivas"><div class="obx-carousel__track">';
        foreach ($slides as $i => $slide) {
            $id = sanitize_html_class((string) ($slide['_id'] ?? $i));
            $h = self::flex_dictionary()[(string) ($slide['horizontal_position'] ?? 'center')] ?? 'center';
            $v = ['top'=>'flex-start','middle'=>'center','bottom'=>'flex-end'][(string) ($slide['vertical_position'] ?? 'middle')] ?? 'center';
            $align = ['left'=>'left','start'=>'start','center'=>'center','right'=>'right','end'=>'end'][(string) ($slide['text_align'] ?? 'center')] ?? 'center';
            echo '<div class="obx-carousel__slide elementor-repeater-item-' . esc_attr($id) . '"><div class="obx-slide" style="--obx-slide-h:' . esc_attr($h) . ';--obx-slide-v:' . esc_attr($v) . ';--obx-slide-align:' . esc_attr($align) . '"><span class="obx-slide__background"></span><span class="obx-slide__overlay"></span><div class="obx-slide__content">';
            if ((string) ($slide['heading'] ?? '') !== '') { echo '<h2 class="obx-slide__heading">' . esc_html($slide['heading']) . '</h2>'; }
            if ((string) ($slide['description'] ?? '') !== '') { echo '<div class="obx-slide__description">' . wp_kses_post(wpautop((string) $slide['description'])) . '</div>'; }
            if ((string) ($slide['button_text'] ?? '') !== '') { echo $this->open_link('slide_' . $id, $slide['link'] ?? [], 'obx-slide__button elementor-button', 'span') . esc_html($slide['button_text']) . $this->close_link($slide['link'] ?? [], 'span'); }
            echo '</div></div></div>';
        }
        echo '</div>' . $this->carousel_controls_html($s, 'show_arrows') . '</div>';
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
