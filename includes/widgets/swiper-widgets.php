<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Elementor\Utils;

if (!defined('ABSPATH')) { exit; }

/**
 * Carousels with Elementor Pro's markup (`elementor-main-swiper swiper`, `swiper-slide`,
 * `elementor-swiper-button-*`, `swiper-pagination`) on the Swiper that ships with Elementor.
 * assets/js/carousels.js extends Elementor's own carousel handler with Pro's setting keys.
 */
abstract class Swiper_Carousel extends Element {
    private int $slide_prints = 0;

    public function get_script_depends(): array { return ['swiper', 'obx-carousels']; }
    public function get_style_depends(): array { return ['obx-frontend', 'e-swiper']; }
    public function has_widget_inner_wrapper(): bool { return !Elementor::$instance->experiments->is_feature_active('e_optimized_markup'); }
    protected function is_dynamic_content(): bool { return false; }

    abstract protected function add_repeater_controls(Repeater $repeater): void;
    abstract protected function repeater_defaults(): array;
    abstract protected function print_slide(array $slide, array $settings, string $key): void;

    protected function register_controls(): void {
        $this->start_controls_section('section_slides', ['label'=>'Diapositivas','tab'=>Controls_Manager::TAB_CONTENT]);
        $this->add_control('slides_name', ['label'=>'Nombre de las diapositivas','type'=>Controls_Manager::TEXT,'default'=>'Diapositivas']);
        $repeater = new Repeater();
        $this->add_repeater_controls($repeater);
        $this->add_control('slides', ['label'=>'Diapositivas','type'=>Controls_Manager::REPEATER,'fields'=>$repeater->get_controls(),'default'=>$this->repeater_defaults(),'separator'=>'after']);
        $this->add_control('effect', ['type'=>Controls_Manager::SELECT,'label'=>'Efecto','default'=>'slide','options'=>['slide'=>'Deslizar','fade'=>'Fundido','cube'=>'Cubo'],'frontend_available'=>true]);
        $counts = [''=>'Por defecto'] + array_combine(range(1, 10), range(1, 10));
        $this->add_responsive_control('slides_per_view', ['type'=>Controls_Manager::SELECT,'label'=>'Diapositivas visibles','options'=>$counts,'inherit_placeholders'=>false,'condition'=>['effect'=>'slide'],'frontend_available'=>true]);
        $this->add_responsive_control('slides_to_scroll', ['type'=>Controls_Manager::SELECT,'label'=>'Diapositivas por avance','description'=>'Cuántas diapositivas avanza cada paso.','options'=>$counts,'inherit_placeholders'=>false,'condition'=>['effect'=>'slide'],'frontend_available'=>true]);
        $this->add_responsive_control('height', ['type'=>Controls_Manager::SLIDER,'label'=>'Altura','size_units'=>['px','em','rem','vh','custom'],'range'=>['px'=>['min'=>100,'max'=>1000],'em'=>['min'=>10,'max'=>100],'rem'=>['min'=>10,'max'=>100],'vh'=>['min'=>20]],'selectors'=>['{{WRAPPER}} .elementor-main-swiper'=>'height: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('width', ['type'=>Controls_Manager::SLIDER,'label'=>'Ancho','size_units'=>['px','%','em','rem','vw','custom'],'range'=>['px'=>['min'=>100,'max'=>1140],'%'=>['min'=>50]],'default'=>['unit'=>'%'],'selectors'=>['{{WRAPPER}} .elementor-main-swiper'=>'width: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('section_additional_options', ['label'=>'Opciones adicionales']);
        $this->add_control('show_arrows', ['type'=>Controls_Manager::SWITCHER,'label'=>'Flechas','default'=>'yes','label_off'=>'Ocultar','label_on'=>'Mostrar','prefix_class'=>'elementor-arrows-','render_type'=>'template','frontend_available'=>true]);
        $this->add_control('pagination', ['label'=>'Paginación','type'=>Controls_Manager::SELECT,'default'=>'bullets','options'=>[''=>'Ninguna','bullets'=>'Puntos','fraction'=>'Fracción','progressbar'=>'Progreso'],'prefix_class'=>'elementor-pagination-type-','render_type'=>'template','frontend_available'=>true]);
        $this->add_control('speed', ['label'=>'Duración de la transición','type'=>Controls_Manager::NUMBER,'default'=>500,'render_type'=>'none','frontend_available'=>true]);
        $this->add_control('autoplay', ['label'=>'Reproducción automática','type'=>Controls_Manager::SWITCHER,'default'=>'yes','separator'=>'before','render_type'=>'none','frontend_available'=>true]);
        $this->add_control('autoplay_speed', ['label'=>'Velocidad de reproducción','type'=>Controls_Manager::NUMBER,'default'=>5000,'condition'=>['autoplay'=>'yes'],'render_type'=>'none','frontend_available'=>true]);
        $this->add_control('loop', ['label'=>'Bucle infinito','type'=>Controls_Manager::SWITCHER,'default'=>'yes','frontend_available'=>true]);
        $this->add_control('pause_on_hover', ['label'=>'Pausar al pasar el ratón','type'=>Controls_Manager::SWITCHER,'default'=>'yes','condition'=>['autoplay'=>'yes'],'render_type'=>'none','frontend_available'=>true]);
        $this->add_control('pause_on_interaction', ['label'=>'Pausar al interactuar','type'=>Controls_Manager::SWITCHER,'default'=>'yes','condition'=>['autoplay'=>'yes'],'render_type'=>'none','frontend_available'=>true]);
        $this->add_group_control(Group_Control_Image_Size::get_type(), ['name'=>'image_size','default'=>'full','separator'=>'before']);
        $this->add_control('lazyload', ['label'=>'Carga diferida','type'=>Controls_Manager::SWITCHER,'separator'=>'before','frontend_available'=>true]);
        $this->end_controls_section();

        $this->start_controls_section('section_slides_style', ['label'=>'Diapositivas','tab'=>Controls_Manager::TAB_STYLE]);
        $space = ['label'=>'Espacio entre diapositivas','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['max'=>50]],'render_type'=>'none','frontend_available'=>true];
        foreach (array_merge(['desktop'], array_keys(Elementor::$instance->breakpoints->get_active_breakpoints())) as $device) { $space[$device . '_default'] = ['size'=>10]; }
        $this->add_responsive_control('space_between', $space);
        $slide = '{{WRAPPER}} .elementor-main-swiper .swiper-slide';
        $this->add_control('slide_background_color', ['label'=>'Color de fondo','type'=>Controls_Manager::COLOR,'selectors'=>[$slide=>'background-color: {{VALUE}}']]);
        $this->add_control('slide_border_size', ['label'=>'Ancho del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'selectors'=>[$slide=>'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}']]);
        $this->add_control('slide_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','custom'],'range'=>['%'=>['max'=>50]],'selectors'=>[$slide=>'border-radius: {{SIZE}}{{UNIT}}']]);
        $this->add_control('slide_border_color', ['label'=>'Color del borde','type'=>Controls_Manager::COLOR,'selectors'=>[$slide=>'border-color: {{VALUE}}']]);
        $this->add_control('slide_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'selectors'=>[$slide=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}'],'separator'=>'before']);
        $this->end_controls_section();

        $this->start_controls_section('section_navigation', ['label'=>'Navegación','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('heading_arrows', ['label'=>'Flechas','type'=>Controls_Manager::HEADING]);
        $this->add_responsive_control('arrows_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'default'=>['size'=>20],'range'=>['px'=>['max'=>100],'em'=>['max'=>10],'rem'=>['max'=>10]],'selectors'=>['{{WRAPPER}} .elementor-swiper-button'=>'font-size: {{SIZE}}{{UNIT}}']]);
        $this->add_control('arrows_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .elementor-swiper-button'=>'color: {{VALUE}}','{{WRAPPER}} .elementor-swiper-button svg'=>'fill: {{VALUE}}']]);
        $paginated = ['pagination!'=>''];
        $this->add_control('heading_pagination', ['label'=>'Paginación','type'=>Controls_Manager::HEADING,'condition'=>$paginated]);
        $this->add_control('pagination_position', ['label'=>'Posición','type'=>Controls_Manager::SELECT,'default'=>'outside','options'=>['outside'=>'Fuera','inside'=>'Dentro'],'prefix_class'=>'elementor-pagination-position-','condition'=>$paginated]);
        $this->add_responsive_control('pagination_gap', ['label'=>'Espacio entre puntos','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>50]],'selectors'=>['{{WRAPPER}} .swiper-pagination-bullet'=>'--swiper-pagination-bullet-horizontal-gap: {{SIZE}}{{UNIT}}; --swiper-pagination-bullet-vertical-gap: {{SIZE}}{{UNIT}};'],'condition'=>['pagination'=>'bullets']]);
        $this->add_responsive_control('pagination_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>100],'em'=>['max'=>10],'rem'=>['max'=>10]],'selectors'=>['{{WRAPPER}} .swiper-pagination-bullet'=>'height: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}','{{WRAPPER}} .swiper-horizontal .swiper-pagination-progressbar'=>'height: {{SIZE}}{{UNIT}}'],'condition'=>$paginated]);
        // Opacity 1 replaces Swiper's translucent inactive dots.
        $this->add_control('pagination_color_inactive', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .swiper-pagination-bullet:not(.swiper-pagination-bullet-active)'=>'background-color: {{VALUE}}; opacity: 1;'],'condition'=>$paginated]);
        $this->add_control('pagination_color', ['label'=>'Color activo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .swiper-pagination-bullet-active, {{WRAPPER}} .swiper-pagination-progressbar-fill'=>'background-color: {{VALUE}}','{{WRAPPER}} .swiper-pagination-fraction'=>'color: {{VALUE}}'],'condition'=>$paginated,'control_type'=>'content']);
        $this->end_controls_section();
    }

    protected function print_slider(?array $settings = null): void {
        $settings = array_merge(['container_class'=>'elementor-main-swiper'], $settings ?? $this->get_settings_for_display());
        $slides = (array) ($settings['slides'] ?? []);
        $optimized = Elementor::$instance->experiments->is_feature_active('e_optimized_markup');
        $this->add_render_attribute('wrapper', ['class'=>[$settings['container_class'], 'swiper'],'role'=>'region','aria-roledescription'=>'carousel','aria-label'=>(string) ($settings['slides_name'] ?? '')]);
        if (!$optimized) { echo '<div class="elementor-swiper">'; }
        echo '<div ' . $this->get_render_attribute_string('wrapper') . '><div class="swiper-wrapper">';
        foreach ($slides as $index => $slide) {
            $this->slide_prints++;
            echo '<div class="swiper-slide" role="group" aria-roledescription="slide">';
            $this->print_slide((array) $slide, $settings, 'slide-' . $index . '-' . $this->slide_prints);
            echo '</div>';
        }
        echo '</div>';
        if (count($slides) > 1) {
            if (!empty($settings['show_arrows'])) {
                foreach (['prev'=>['Anterior', is_rtl() ? 'right' : 'left'], 'next'=>['Siguiente', is_rtl() ? 'left' : 'right']] as $type => [$label, $direction]) {
                    echo '<div class="elementor-swiper-button elementor-swiper-button-' . $type . '" role="button" tabindex="0" aria-label="' . esc_attr($label) . '">';
                    Icons_Manager::render_icon(['library'=>'eicons','value'=>'eicon-chevron-' . $direction], ['aria-hidden'=>'true']);
                    echo '</div>';
                }
            }
            if (!empty($settings['pagination'])) { echo '<div class="swiper-pagination"></div>'; }
        }
        echo '</div>';
        if (!$optimized) { echo '</div>'; }
    }

    protected function slide_image_url(array $slide, array $settings): string {
        $url = !empty($slide['image']['id']) ? Group_Control_Image_Size::get_attachment_image_src($slide['image']['id'], 'image_size', $settings) : '';
        return (string) ($url ?: ($slide['image']['url'] ?? ''));
    }

    protected function slide_image_alt(array $slide): string {
        if (!empty($slide['name'])) { return (string) $slide['name']; }
        return (string) ($slide['image']['alt'] ?? '');
    }

    public function render_plain_content() {}
}

/** Reviews: a carousel of social-network style reviews (image, name, rating, network icon, text). */
final class Reviews extends Swiper_Carousel {
    public const PRO = 'reviews';
    private const TEXT = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.';

    public function get_title(): string { return 'Reseñas'; }
    public function get_icon(): string { return 'eicon-review'; }
    public function get_keywords(): array { return ['reviews','reseñas','opiniones','social','rating','valoración','testimonial','carousel','carrusel']; }
    public function get_style_depends(): array { return ['obx-frontend', 'e-swiper', 'widget-star-rating', 'elementor-icons']; }

    protected function register_controls(): void {
        parent::register_controls();
        $this->update_control('slide_padding', ['selectors'=>[
            '{{WRAPPER}} .elementor-testimonial__header'=>'padding-top: {{TOP}}{{UNIT}}; padding-left: {{LEFT}}{{UNIT}}; padding-right: {{RIGHT}}{{UNIT}};',
            '{{WRAPPER}} .elementor-testimonial__content'=>'padding-bottom: {{BOTTOM}}{{UNIT}}; padding-left: {{LEFT}}{{UNIT}}; padding-right: {{RIGHT}}{{UNIT}};',
        ]]);
        $sizes = ['size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>100],'em'=>['max'=>10],'rem'=>['max'=>10]]];

        $this->start_injection(['of'=>'slide_padding']);
        $this->add_control('heading_header', ['label'=>'Cabecera','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control('header_background_color', ['label'=>'Color de fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .elementor-testimonial__header'=>'background-color: {{VALUE}}']]);
        $this->add_responsive_control('content_gap', ['label'=>'Separación','type'=>Controls_Manager::SLIDER] + $sizes + ['selectors'=>['{{WRAPPER}} .elementor-testimonial__header'=>'padding-block-end: calc( {{SIZE}}{{UNIT}} / 2 )','{{WRAPPER}} .elementor-testimonial__content'=>'padding-block-start: calc( {{SIZE}}{{UNIT}} / 2 )']]);
        $this->add_control('show_separator', ['label'=>'Separador','type'=>Controls_Manager::SWITCHER,'label_off'=>'Ocultar','label_on'=>'Mostrar','default'=>'has-separator','return_value'=>'has-separator','prefix_class'=>'elementor-review--','separator'=>'before']);
        $this->add_control('separator_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .elementor-testimonial__header'=>'border-block-end-color: {{VALUE}}'],'condition'=>['show_separator!'=>'']]);
        $this->add_control('separator_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>20],'em'=>['max'=>2],'rem'=>['max'=>2]],'condition'=>['show_separator!'=>''],'selectors'=>['{{WRAPPER}} .elementor-testimonial__header'=>'border-block-end-width: {{SIZE}}{{UNIT}}']]);
        $this->end_injection();

        $this->start_injection(['at'=>'before','of'=>'section_navigation']);
        $this->start_controls_section('section_content_style', ['label'=>'Texto','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('name_title_style', ['label'=>'Nombre','type'=>Controls_Manager::HEADING]);
        $this->add_control('name_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .elementor-testimonial__name'=>'color: {{VALUE}}']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'name_typography','selector'=>'{{WRAPPER}} .elementor-testimonial__header, {{WRAPPER}} .elementor-testimonial__name','global'=>['default'=>Global_Typography::TYPOGRAPHY_PRIMARY]]);
        $this->add_control('heading_title_style', ['label'=>'Título','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control('title_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .elementor-testimonial__title'=>'color: {{VALUE}}']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'title_typography','selector'=>'{{WRAPPER}} .elementor-testimonial__title']);
        $this->add_control('heading_review_style', ['label'=>'Reseña','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control('content_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .elementor-testimonial__text'=>'color: {{VALUE}}']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'content_typography','selector'=>'{{WRAPPER}} .elementor-testimonial__text','global'=>['default'=>Global_Typography::TYPOGRAPHY_TEXT]]);
        $this->end_controls_section();

        $this->start_controls_section('section_image_style', ['label'=>'Imagen','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('image_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER] + $sizes + ['selectors'=>['{{WRAPPER}} .elementor-testimonial__image img'=>'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}']]);
        $this->add_responsive_control('image_gap', ['label'=>'Separación','type'=>Controls_Manager::SLIDER] + $sizes + ['selectors'=>['{{WRAPPER}} .elementor-testimonial__image + cite'=>'margin-inline-start: {{SIZE}}{{UNIT}}; margin-inline-end: 0;']]);
        $this->add_control('image_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','custom'],'selectors'=>['{{WRAPPER}} .elementor-testimonial__image img'=>'border-radius: {{SIZE}}{{UNIT}}']]);
        $this->end_controls_section();

        $this->start_controls_section('section_icon_style', ['label'=>'Icono','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('icon_color', ['label'=>'Color','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>['default'=>'Oficial','custom'=>'Personalizado']]);
        $this->add_control('icon_custom_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'condition'=>['icon_color'=>'custom'],'selectors'=>['{{WRAPPER}} .elementor-testimonial__icon:not(.elementor-testimonial__rating)'=>'color: {{VALUE}};','{{WRAPPER}} .elementor-testimonial__icon:not(.elementor-testimonial__rating) svg'=>'fill: {{VALUE}};']]);
        $this->add_responsive_control('icon_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER] + $sizes + ['selectors'=>['{{WRAPPER}} .elementor-testimonial__icon'=>'font-size: {{SIZE}}{{UNIT}}','{{WRAPPER}} .elementor-testimonial__icon svg'=>'width: {{SIZE}}{{UNIT}}']]);
        $this->end_controls_section();

        $this->start_controls_section('section_rating_style', ['label'=>'Valoración','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('star_style', ['label'=>'Icono','type'=>Controls_Manager::SELECT,'options'=>['star_fontawesome'=>'Font Awesome','star_unicode'=>'Unicode'],'default'=>'star_fontawesome','render_type'=>'template','prefix_class'=>'elementor--star-style-','separator'=>'before']);
        $this->add_control('unmarked_star_style', ['label'=>'Estilo sin marcar','type'=>Controls_Manager::CHOOSE,'options'=>['solid'=>['title'=>'Sólido','icon'=>'eicon-star'],'outline'=>['title'=>'Contorno','icon'=>'eicon-star-o']],'default'=>'solid']);
        $this->add_control('star_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER] + $sizes + ['selectors'=>['{{WRAPPER}} .elementor-star-rating'=>'font-size: {{SIZE}}{{UNIT}}'],'separator'=>'before']);
        $this->add_control('star_space', ['label'=>'Espaciado','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>50],'em'=>['max'=>5],'rem'=>['max'=>5]],'selectors'=>['{{WRAPPER}} .elementor-star-rating i:not(:last-of-type)'=>'margin-inline-end: {{SIZE}}{{UNIT}}']]);
        $this->add_control('stars_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .elementor-star-rating i:before'=>'color: {{VALUE}}'],'separator'=>'before']);
        $this->add_control('stars_unmarked_color', ['label'=>'Color sin marcar','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .elementor-star-rating i'=>'color: {{VALUE}}']]);
        $this->end_controls_section();
        $this->end_injection();

        $this->update_responsive_control('width', ['selectors'=>['{{WRAPPER}}.elementor-arrows-yes .elementor-main-swiper'=>'width: calc( {{SIZE}}{{UNIT}} - 40px )','{{WRAPPER}} .elementor-main-swiper'=>'width: {{SIZE}}{{UNIT}}']]);
        $this->update_responsive_control('slides_per_view', ['condition'=>null]);
        $this->update_control('slides_to_scroll', ['condition'=>null]);
        $this->remove_control('effect');
        $this->remove_responsive_control('height');
        $this->remove_control('pagination_position');
    }

    protected function add_repeater_controls(Repeater $repeater): void {
        $repeater->add_control('image', ['label'=>'Imagen','type'=>Controls_Manager::MEDIA,'default'=>['url'=>Utils::get_placeholder_image_src()],'dynamic'=>['active'=>true]]);
        $repeater->add_control('name', ['label'=>'Nombre','type'=>Controls_Manager::TEXT,'default'=>'John Doe','dynamic'=>['active'=>true],'ai'=>['active'=>false]]);
        $repeater->add_control('title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'@username','dynamic'=>['active'=>true]]);
        $repeater->add_control('rating', ['label'=>'Valoración','type'=>Controls_Manager::NUMBER,'min'=>0,'max'=>5,'step'=>0.1,'dynamic'=>['active'=>true]]);
        $repeater->add_control('selected_social_icon', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'fa4compatibility'=>'social_icon','default'=>['value'=>'fab fa-twitter','library'=>'fa-brands'],'recommended'=>[
            'fa-solid'=>['rss','shopping-cart','thumbtack'],
            'fa-brands'=>['android','apple','behance','bitbucket','codepen','delicious','digg','dribbble','envelope','facebook','flickr','foursquare','github','google-plus','houzz','instagram','jsfiddle','linkedin','medium','meetup','mix','mixcloud','odnoklassniki','pinterest','product-hunt','reddit','skype','slideshare','snapchat','soundcloud','spotify','stack-overflow','steam','telegram','threads','tripadvisor','tumblr','twitch','twitter','vimeo','fa-vk','weibo','weixin','whatsapp','wordpress','x-twitter','xing','yelp','youtube','500px'],
        ]]);
        $repeater->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true]]);
        $repeater->add_control('content', ['label'=>'Reseña','type'=>Controls_Manager::TEXTAREA,'default'=>self::TEXT,'dynamic'=>['active'=>true]]);
    }

    protected function repeater_defaults(): array {
        $slide = ['content'=>self::TEXT,'name'=>'John Doe','title'=>'@username','image'=>['url'=>Utils::get_placeholder_image_src()]];
        return [$slide, $slide, $slide];
    }

    /** Five stars: full, partial (tenths) and empty, in the eicons font or as Unicode characters. */
    private static function stars($rating, array $settings): string {
        $outline = 'outline' === ($settings['unmarked_star_style'] ?? 'solid');
        $glyph = 'star_unicode' === ($settings['star_style'] ?? '') ? ($outline ? '&#9734;' : '&#9733;') : ($outline ? '&#xE933;' : '&#xE934;');
        $rating = min(5.0, (float) $rating);
        $whole = (int) $rating;
        $html = '';
        for ($star = 1; $star <= 5; $star++) {
            if ($star <= $whole) { $class = 'elementor-star-full'; }
            elseif ($star === $whole + 1 && $rating > $whole) { $class = 'elementor-star-' . (int) round(($rating - $whole) * 10); }
            else { $class = 'elementor-star-empty'; }
            $html .= '<i class="' . $class . '">' . $glyph . '</i>';
        }
        return '<div class="elementor-star-rating">' . $html . '</div>';
    }

    private function cite(array $slide, array $settings): string {
        if (empty($slide['name']) && empty($slide['title'])) { return ''; }
        $html = '<cite class="elementor-testimonial__cite">';
        if (!empty($slide['name'])) { $html .= '<span class="elementor-testimonial__name">' . wp_kses_post($slide['name']) . '</span>'; }
        if (!empty($slide['rating'])) { $html .= self::stars($slide['rating'], $settings); }
        if (!empty($slide['title'])) { $html .= '<span class="elementor-testimonial__title">' . wp_kses_post($slide['title']) . '</span>'; }
        return $html . '</cite>';
    }

    /** The network icon; its name (twitter, facebook…) picks the official colour. */
    private function icon(array $slide, string $key): string {
        $migration = Icons_Manager::is_migration_allowed();
        if (!isset($slide['social_icon']) && !$migration) { $slide['social_icon'] = 'fa fa-twitter'; }
        $selected = (array) ($slide['selected_social_icon'] ?? []);
        if (empty($slide['social_icon']) && empty($selected['value'])) { return ''; }
        $new = isset($slide['__fa4_migrated']['selected_social_icon']) || (empty($slide['social_icon']) && $migration);
        if ($new) {
            ob_start();
            Icons_Manager::render_icon($selected, ['aria-hidden'=>'true']);
            $icon = (string) ob_get_clean();
            $parts = 'svg' === ($selected['library'] ?? '') || !is_string($selected['value'] ?? null) ? [] : explode(' ', $selected['value'], 2);
            $network = isset($parts[1]) ? str_replace('fa-', '', $parts[1]) : '';
        } else {
            $icon = '<i class="' . esc_attr($slide['social_icon']) . '" aria-hidden="true"></i>';
            $network = str_replace('fa fa-', '', (string) $slide['social_icon']);
        }
        $this->add_render_attribute('icon_wrapper_' . $key, ['class'=>['elementor-testimonial__icon elementor-icon', 'elementor-icon-' . $network],'aria-label'=>'Leer más']);
        return '<div ' . $this->get_render_attribute_string('icon_wrapper_' . $key) . '>' . $icon . '</div>';
    }

    protected function print_slide(array $slide, array $settings, string $key): void {
        $lazy = 'yes' === $this->get_settings('lazyload');
        $this->add_render_attribute($key . '-testimonial', 'class', ['elementor-testimonial', 'elementor-repeater-item-' . ($slide['_id'] ?? '')]);
        $image = !empty($slide['image']['url']);
        if ($image) {
            $src = $this->slide_image_url($slide, $settings);
            $this->add_render_attribute($key . '-image', ($lazy ? ['class'=>'swiper-lazy','data-src'=>$src] : ['src'=>$src]) + ['alt'=>$this->slide_image_alt($slide)]);
        }
        echo '<div ' . $this->get_render_attribute_string($key . '-testimonial') . '>';
        if ($image || !empty($slide['name']) || !empty($slide['title'])) {
            $link = !empty($slide['link']['url']);
            $header = 'header_' . ($slide['_id'] ?? $key);
            $this->add_render_attribute($header, 'class', 'elementor-testimonial__header');
            if ($link) { $this->add_link_attributes($header, $slide['link']); }
            $tag = $link ? 'a' : 'div';
            echo '<' . $tag . ' ' . $this->get_render_attribute_string($header) . '>';
            if ($image) {
                echo '<div class="elementor-testimonial__image"><img ' . $this->get_render_attribute_string($key . '-image') . '>' . ($lazy ? '<div class="swiper-lazy-preloader"></div>' : '') . '</div>';
            }
            echo $this->cite($slide, $settings); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from kses'd parts.
            echo $this->icon($slide, $key); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped attributes and Elementor's icon markup.
            echo '</' . $tag . '>';
        }
        if (!empty($slide['content'])) {
            // The review text is the page author's HTML, as in Pro.
            echo '<div class="elementor-testimonial__content"><div class="elementor-testimonial__text">' . $slide['content'] . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '</div>';
    }

    protected function render(): void { $this->print_slider(); }
}
