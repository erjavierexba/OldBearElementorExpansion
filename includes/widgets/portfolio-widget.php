<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Typography;
use Elementor\Utils;

if (!defined('ABSPATH')) { exit; }

/**
 * Portfolio: a grid of post thumbnails with the title over them on hover, a fixed item ratio or
 * masonry, and a filter bar by taxonomy term. Pro's markup and keys; assets/js/portfolio.js filters
 * and lays out the items.
 */
final class Portfolio extends Element {
    public const PRO = 'portfolio';

    public function get_title(): string { return 'Portafolio'; }
    public function get_icon(): string { return 'eicon-gallery-grid'; }
    public function get_keywords(): array { return ['posts','cpt','item','loop','query','portfolio','portafolio','custom post type']; }
    public function get_script_depends(): array { return ['obx-portfolio']; }
    protected function is_dynamic_content(): bool { return true; }
    public function has_widget_inner_wrapper(): bool { return !\Elementor\Plugin::$instance->experiments->is_feature_active('e_optimized_markup'); }

    /** Saved with a post type that does not exist here (imported): it shows posts, as Pro. */
    public function on_import($element) {
        if (isset($element['settings']['posts_post_type']) && !in_array($element['settings']['posts_post_type'], ['by_id','current_query','related'], true) && !get_post_type_object($element['settings']['posts_post_type'])) {
            $element['settings']['posts_post_type'] = 'post';
        }
        return $element;
    }

    protected function register_controls(): void {
        $this->layout_controls();
        Query::controls($this, 'posts_');
        $this->filter_bar_controls();
        $this->items_style_controls();
        $this->overlay_style_controls();
        $this->filter_style_controls();
    }

    private function layout_controls(): void {
        $this->start_controls_section('section_layout', ['label'=>'Diseño','tab'=>Controls_Manager::TAB_CONTENT]);
        $columns = [];
        foreach (range(1, 6) as $n) { $columns[(string) $n] = (string) $n; }
        $this->add_responsive_control('columns', ['label'=>'Columnas','type'=>Controls_Manager::SELECT,'default'=>'3','tablet_default'=>'2','mobile_default'=>'1','options'=>$columns,'prefix_class'=>'elementor-grid%s-','frontend_available'=>true]);
        $this->add_control('posts_per_page', ['label'=>'Entradas por página','type'=>Controls_Manager::NUMBER,'default'=>6]);
        $this->add_group_control(Group_Control_Image_Size::get_type(), ['name'=>'thumbnail_size','exclude'=>['custom'],'default'=>'medium','prefix_class'=>'elementor-portfolio--thumbnail-size-']);
        $this->add_control('masonry', ['label'=>'Mampostería','type'=>Controls_Manager::SWITCHER,'label_off'=>'No','label_on'=>'Sí','condition'=>['columns!'=>'1'],'render_type'=>'ui','frontend_available'=>true]);
        $this->add_control('item_ratio', [
            'label'=>'Proporción del elemento',
            'type'=>Controls_Manager::SLIDER,
            'default'=>['size'=>0.66],
            'range'=>['px'=>['min'=>0.1,'max'=>2,'step'=>0.01]],
            'selectors'=>[
                '{{WRAPPER}} .elementor-post__thumbnail__link'=>'padding-bottom: calc( {{SIZE}} * 100% )',
                '{{WRAPPER}}:after'=>'content: "{{SIZE}}"; position: absolute; color: transparent;',
            ],
            'condition'=>['masonry'=>''],
            'frontend_available'=>true,
        ]);
        $this->add_control('show_title', ['label'=>'Mostrar título','type'=>Controls_Manager::SWITCHER,'default'=>'yes','label_off'=>'No','label_on'=>'Sí']);
        $this->add_control('title_tag', ['label'=>'Etiqueta HTML del título','type'=>Controls_Manager::SELECT,'options'=>['h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','h6'=>'H6','div'=>'div','span'=>'span','p'=>'p'],'default'=>'h3','condition'=>['show_title'=>'yes']]);
        $this->end_controls_section();
    }

    /** Taxonomies offered for the filter bar (the ones shown in menus, as Pro). */
    private static function taxonomies(): array {
        $options = [''=>''];
        foreach (get_taxonomies(['show_in_nav_menus'=>true], 'objects') as $taxonomy) { $options[$taxonomy->name] = $taxonomy->label; }
        return $options;
    }

    private function filter_bar_controls(): void {
        $this->start_controls_section('filter_bar', ['label'=>'Barra de filtros','tab'=>Controls_Manager::TAB_CONTENT]);
        $this->add_control('show_filter_bar', ['label'=>'Mostrar','type'=>Controls_Manager::SWITCHER,'label_off'=>'No','label_on'=>'Sí']);
        $this->add_control('taxonomy', ['label'=>'Taxonomía','type'=>Controls_Manager::SELECT2,'label_block'=>true,'default'=>[],'options'=>self::taxonomies(),'condition'=>['show_filter_bar'=>'yes','posts_post_type!'=>'by_id']]);
        $this->end_controls_section();
    }

    private function items_style_controls(): void {
        $this->start_controls_section('section_design_layout', ['label'=>'Elementos','tab'=>Controls_Manager::TAB_STYLE]);
        $gap = ['size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>100],'em'=>['max'=>10],'rem'=>['max'=>10]]];
        // «Item Gap» sets both gaps; the next two override one each.
        $this->add_control('item_gap', ['label'=>'Separación entre elementos','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'selectors'=>['{{WRAPPER}}'=>'--grid-row-gap: {{SIZE}}{{UNIT}}; --grid-column-gap: {{SIZE}}{{UNIT}};'],'frontend_available'=>true]);
        $this->add_control('column_gap', ['label'=>'Separación entre columnas','type'=>Controls_Manager::SLIDER] + $gap + ['selectors'=>['{{WRAPPER}}'=>' --grid-column-gap: {{SIZE}}{{UNIT}}']]);
        $this->add_control('row_gap', ['label'=>'Separación entre filas','type'=>Controls_Manager::SLIDER] + $gap + ['frontend_available'=>true,'selectors'=>['{{WRAPPER}}'=>'--grid-row-gap: {{SIZE}}{{UNIT}}']]);
        $this->add_control('border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'selectors'=>['{{WRAPPER}} .elementor-portfolio-item__img, {{WRAPPER}} .elementor-portfolio-item__overlay'=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    private function overlay_style_controls(): void {
        $this->start_controls_section('section_design_overlay', ['label'=>'Capa del elemento','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('color_background', ['label'=>'Color de fondo','type'=>Controls_Manager::COLOR,'global'=>['default'=>Global_Colors::COLOR_ACCENT],'selectors'=>['{{WRAPPER}} a .elementor-portfolio-item__overlay'=>'background-color: {{VALUE}};']]);
        $this->add_control('color_title', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} a .elementor-portfolio-item__title'=>'color: {{VALUE}};'],'condition'=>['show_title'=>'yes']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'typography_title','global'=>['default'=>Global_Typography::TYPOGRAPHY_PRIMARY],'selector'=>'{{WRAPPER}} .elementor-portfolio-item__title','condition'=>['show_title'=>'yes']]);
        $this->end_controls_section();
    }

    private function filter_style_controls(): void {
        $this->start_controls_section('section_design_filter', ['label'=>'Barra de filtros','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['show_filter_bar'=>'yes']]);
        $this->add_control('color_filter', ['label'=>'Color','type'=>Controls_Manager::COLOR,'global'=>['default'=>Global_Colors::COLOR_TEXT],'selectors'=>['{{WRAPPER}} .elementor-portfolio__filter'=>'color: {{VALUE}}']]);
        $this->add_control('color_filter_active', ['label'=>'Color activo','type'=>Controls_Manager::COLOR,'global'=>['default'=>Global_Colors::COLOR_PRIMARY],'selectors'=>['{{WRAPPER}} .elementor-portfolio__filter.elementor-active'=>'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'typography_filter','global'=>['default'=>Global_Typography::TYPOGRAPHY_PRIMARY],'selector'=>'{{WRAPPER}} .elementor-portfolio__filter']);
        $space = ['size_units'=>['px','em','rem','custom'],'default'=>['size'=>10],'range'=>['px'=>['max'=>100],'em'=>['max'=>10],'rem'=>['max'=>10]]];
        $this->add_control('filter_item_spacing', ['label'=>'Espacio entre filtros','type'=>Controls_Manager::SLIDER] + $space + ['selectors'=>[
            '{{WRAPPER}} .elementor-portfolio__filter:not(:last-child)'=>'margin-right: calc({{SIZE}}{{UNIT}}/2)',
            '{{WRAPPER}} .elementor-portfolio__filter:not(:first-child)'=>'margin-left: calc({{SIZE}}{{UNIT}}/2)',
        ]]);
        $this->add_control('filter_spacing', ['label'=>'Separación','type'=>Controls_Manager::SLIDER] + $space + ['selectors'=>['{{WRAPPER}} .elementor-portfolio__filters'=>'margin-bottom: {{SIZE}}{{UNIT}}']]);
        $this->end_controls_section();
    }

    /** @return array<int,\WP_Term[]> post ID => its terms of the filter taxonomy (by term ID) */
    private static function terms_of(array $posts, string $taxonomy): array {
        $terms = [];
        foreach ($posts as $post) {
            $terms[$post->ID] = [];
            if ($taxonomy === '' || !taxonomy_exists($taxonomy)) { continue; }
            $list = wp_get_post_terms($post->ID, $taxonomy);
            if (is_wp_error($list)) { continue; }
            foreach ($list as $term) { $terms[$post->ID][$term->term_id] = $term; }
        }
        return $terms;
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $query = Query::build($s, 'posts_', max(1, (int) ($s['posts_per_page'] ?? 6)), 1, $this);
        if (!$query->found_posts && !$query->post_count) { return; }
        $taxonomy = is_array($s['taxonomy'] ?? '') ? '' : (string) ($s['taxonomy'] ?? '');
        $terms = self::terms_of($query->posts, $taxonomy);
        if (($s['show_filter_bar'] ?? '') === 'yes') { $this->filter_menu($taxonomy, $terms); }
        $masonry = ($s['masonry'] ?? '') === 'yes' && (string) ($s['columns'] ?? '3') !== '1';
        $classes = 'elementor-portfolio elementor-grid elementor-posts-container' . ($masonry ? ' elementor-posts-masonry' : ' elementor-has-item-ratio');
        echo '<div class="' . esc_attr($classes) . '" role="list">';
        $tag = Utils::validate_html_tag((string) ($s['title_tag'] ?? 'h3'));
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            Query::$shown[] = $id;
            $filters = array_map(static fn($term_id) => 'elementor-filter-' . $term_id, array_keys($terms[$id] ?? []));
            echo '<article class="' . esc_attr(implode(' ', get_post_class(array_merge(['elementor-portfolio-item','elementor-post'], $filters), $id))) . '" role="listitem">';
            echo '<a class="elementor-post__thumbnail__link" href="' . esc_url(get_permalink()) . '">';
            echo '<div class="elementor-portfolio-item__img elementor-post__thumbnail">';
            Group_Control_Image_Size::print_attachment_image_html(array_merge($s, ['thumbnail_size'=>['id'=>get_post_thumbnail_id()]]), 'thumbnail_size');
            echo '</div><div class="elementor-portfolio-item__overlay">';
            if (($s['show_title'] ?? '') === 'yes') { echo '<' . $tag . ' class="elementor-portfolio-item__title">' . esc_html(get_the_title()) . '</' . $tag . '>'; }
            echo '</div></a></article>';
        }
        echo '</div>';
        wp_reset_postdata();
    }

    /** «All» plus the terms of the posts shown, by name. */
    private function filter_menu(string $taxonomy, array $terms): void {
        if ($taxonomy === '') { return; }
        $all = [];
        foreach ($terms as $list) { $all += $list; }
        if (!$all) { return; }
        usort($all, static fn($a, $b) => strcmp($a->name, $b->name));
        echo '<ul class="elementor-portfolio__filters"><li class="elementor-portfolio__filter elementor-active" tabindex="0" data-filter="__all">Todos</li>';
        foreach ($all as $term) { echo '<li class="elementor-portfolio__filter" tabindex="0" data-filter="' . esc_attr((string) $term->term_id) . '">' . esc_html($term->name) . '</li>'; }
        echo '</ul>';
    }

    public function render_plain_content() {}
}
