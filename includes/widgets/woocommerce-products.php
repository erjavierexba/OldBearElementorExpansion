<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as Elementor;
use OldBearExpansion\Context;
use OldBearExpansion\ThemeBuilder\Locations;

if (!defined('ABSPATH')) { exit; }

/**
 * Product grids (Products, Archive Products, Related, Upsells) with Elementor Pro's keys.
 * They print WooCommerce's own loop templates (content-product.php), so themes and plugins that hook
 * the shop loop keep working; the grid, spacing and styles come from the widget.
 */
abstract class Woo_Products_Base extends Woo_Element {
    protected const LI = '{{WRAPPER}} ul.products li.product';

    protected static function orderby_options(): array {
        return ['date'=>'Fecha','title'=>'Título','price'=>'Precio','popularity'=>'Popularidad','rating'=>'Valoración','rand'=>'Aleatorio','menu_order'=>'Orden del menú'];
    }

    /** Responsive «columns» (sets the grid) plus Pro's hidden products_class. */
    protected function columns_control(int $default = 4): void {
        $this->add_responsive_control('columns', ['label'=>'Columnas','type'=>Controls_Manager::NUMBER,'default'=>$default,'tablet_default'=>3,'mobile_default'=>2,'min'=>1,'max'=>12,
            'selectors'=>['{{WRAPPER}} .obx-products'=>'--obx-products-columns: {{VALUE}};']]);
    }

    private function dims(string $key, string $label, string $selector, string $property, array $extra = []): void {
        $this->add_responsive_control($key, ['label'=>$label,'type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem'],
            'selectors'=>[$selector=>$property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']] + $extra);
    }

    private function size(string $key, string $label, string $selector, string $declaration, array $extra = []): void {
        $this->add_responsive_control($key, ['label'=>$label,'type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'range'=>['px'=>['min'=>0,'max'=>100]],'selectors'=>[$selector=>$declaration]] + $extra);
    }

    private function paint(string $key, string $label, string $selector, string $property = 'color', array $extra = []): void {
        $this->add_control($key, ['label'=>$label,'type'=>Controls_Manager::COLOR,'selectors'=>[$selector=>$property . ': {{VALUE}};']] + $extra);
    }

    /** Products, box, pagination and sale badge styles shared by every product grid. */
    protected function products_style(bool $paginated): void {
        $li = self::LI;
        $this->start_controls_section('section_products_style', ['label'=>'Productos','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('products_class', ['type'=>Controls_Manager::HIDDEN,'default'=>'wc-products','prefix_class'=>'elementor-products-grid elementor-']);
        $this->size('column_gap', 'Separación entre columnas', '{{WRAPPER}} .obx-products', '--obx-products-column-gap: {{SIZE}}{{UNIT}};', ['default'=>['unit'=>'px','size'=>20]]);
        $this->size('row_gap', 'Separación entre filas', '{{WRAPPER}} .obx-products', '--obx-products-row-gap: {{SIZE}}{{UNIT}};', ['default'=>['unit'=>'px','size'=>40]]);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),
            'selectors_dictionary'=>['left'=>'left; --obx-products-button-align: flex-start; --obx-products-stars-margin: 0 auto 0 0','center'=>'center; --obx-products-button-align: center; --obx-products-stars-margin: 0 auto','right'=>'right; --obx-products-button-align: flex-end; --obx-products-stars-margin: 0 0 0 auto'],
            'selectors'=>[$li=>'text-align: {{VALUE}};']]);

        $this->add_control('heading_image_style', ['label'=>'Imagen','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $image = $li . ' a img';
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'image_border','selector'=>$image]);
        $this->dims('image_border_radius', 'Radio del borde', $image, 'border-radius');
        $this->size('image_spacing', 'Separación', $image, 'margin-bottom: {{SIZE}}{{UNIT}};');

        $this->add_control('heading_title_style', ['label'=>'Título','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $title = $li . ' .woocommerce-loop-product__title, ' . $li . ' .woocommerce-loop-category__title';
        $this->paint('title_color', 'Color', $title);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'title_typography','selector'=>$title]);
        $this->size('title_spacing', 'Separación', $title, 'margin-bottom: {{SIZE}}{{UNIT}};');

        $this->add_control('heading_rating_style', ['label'=>'Valoración','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->paint('star_color', 'Color de las estrellas', $li . ' .star-rating, ' . $li . ' .star-rating span::before');
        $this->paint('empty_star_color', 'Color de las estrellas vacías', $li . ' .star-rating::before');
        $this->add_control('star_size', ['label'=>'Tamaño de las estrellas','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em'],'default'=>['unit'=>'em'],'range'=>['em'=>['min'=>0,'max'=>4,'step'=>0.1]],'selectors'=>[$li . ' .star-rating'=>'font-size: {{SIZE}}{{UNIT}};']]);
        $this->size('rating_spacing', 'Separación', $li . ' .star-rating', 'margin-bottom: {{SIZE}}{{UNIT}};');

        $this->add_control('heading_price_style', ['label'=>'Precio','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $price = $li . ' .price';
        $this->paint('price_color', 'Color', $price . ', ' . $price . ' ins, ' . $price . ' ins .amount');
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'price_typography','selector'=>$price]);
        $this->add_control('heading_old_price_style', ['label'=>'Precio anterior','type'=>Controls_Manager::HEADING]);
        $this->paint('old_price_color', 'Color', $price . ' del, ' . $price . ' del .amount');
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'old_price_typography','selector'=>$price . ' del .amount, ' . $price . ' del']);

        $this->add_control('heading_button_style', ['label'=>'Botón','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $button = $li . ' .button';
        $this->start_controls_tabs('tabs_button_style');
        $this->start_controls_tab('tab_button_normal', ['label'=>'Normal']);
        $this->paint('button_text_color', 'Color del texto', $button);
        $this->paint('button_background_color', 'Fondo', $button, 'background-color');
        $this->paint('button_border_color', 'Color del borde', $button, 'border-color');
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'button_typography','selector'=>$button]);
        $this->end_controls_tab();
        $this->start_controls_tab('tab_button_hover', ['label'=>'Al pasar el ratón']);
        $hover = $button . ':hover, ' . $button . ':focus-visible';
        $this->paint('button_hover_color', 'Color del texto', $hover);
        $this->paint('button_hover_background_color', 'Fondo', $hover, 'background-color');
        $this->paint('button_hover_border_color', 'Color del borde', $hover, 'border-color');
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'button_border','exclude'=>['color'],'selector'=>$button,'separator'=>'before']);
        $this->dims('button_border_radius', 'Radio del borde', $button, 'border-radius');
        $this->dims('button_text_padding', 'Relleno', $button, 'padding');
        $this->size('button_spacing', 'Separación', $button, 'margin-top: {{SIZE}}{{UNIT}};');
        $this->add_control('automatically_align_buttons', ['label'=>'Alinear los botones abajo','type'=>Controls_Manager::SWITCHER,'description'=>'Todos los botones quedan a la misma altura aunque los títulos ocupen distintas líneas.',
            'selectors'=>[$li=>'display: flex; flex-direction: column;',$button=>'margin-top: auto; align-self: var(--obx-products-button-align, flex-start);']]);

        $this->add_control('heading_view_cart_style', ['label'=>'Enlace «Ver carrito»','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $view = $li . ' .added_to_cart';
        $this->paint('view_cart_color', 'Color', $view);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'view_cart_typography','selector'=>$view]);
        $this->size('view_cart_spacing', 'Separación', $view, 'margin-inline-start: {{SIZE}}{{UNIT}};');
        $this->end_controls_section();

        $this->start_controls_section('section_design_box', ['label'=>'Caja','tab'=>Controls_Manager::TAB_STYLE]);
        $this->dims('box_border_width', 'Grosor del borde', $li, 'border-style: solid; border-width');
        $this->add_control('box_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em'],'range'=>['px'=>['max'=>200]],'selectors'=>[$li=>'border-radius: {{SIZE}}{{UNIT}}; overflow: hidden;']]);
        $this->dims('box_padding', 'Relleno', $li, 'padding');
        $this->start_controls_tabs('box_style_tabs');
        foreach (['normal'=>['Normal', '', ''], 'hover'=>['Al pasar el ratón', ':hover', '_hover']] as $state => [$label, $pseudo, $suffix]) {
            $this->start_controls_tab('classic_style_' . $state, ['label'=>$label]);
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'box_shadow' . $suffix,'selector'=>$li . $pseudo]);
            $this->paint('box_bg_color' . $suffix, 'Fondo', $li . $pseudo, 'background-color');
            $this->paint('box_border_color' . $suffix, 'Color del borde', $li . $pseudo, 'border-color');
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->end_controls_section();

        $nav = '{{WRAPPER}} nav.woocommerce-pagination';
        $on = $paginated ? ['paginate'=>'yes'] : [];
        $this->start_controls_section('section_pagination_style', ['label'=>'Paginación','tab'=>Controls_Manager::TAB_STYLE] + ($on ? ['condition'=>$on] : []));
        $this->size('pagination_spacing', 'Separación', $nav, 'margin-top: {{SIZE}}{{UNIT}};', ['condition'=>$on]);
        $this->add_control('show_pagination_border', ['label'=>'Bordes','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes','condition'=>$on]);
        $this->paint('pagination_border_color', 'Color del borde', $nav . ' ul, ' . $nav . ' ul li', 'border-color', ['condition'=>$on + ['show_pagination_border'=>'yes']]);
        $this->size('pagination_padding', 'Relleno', $nav . ' ul li a, ' . $nav . ' ul li span', 'padding: {{SIZE}}{{UNIT}};', ['condition'=>$on]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'pagination_typography','selector'=>$nav,'condition'=>$on]);
        $this->start_controls_tabs('pagination_style_tabs', ['condition'=>$on]);
        foreach (['normal'=>['Normal', ' ul li a', ''], 'hover'=>['Al pasar el ratón', ' ul li a:hover', '_hover'], 'active'=>['Actual', ' ul li span.current', '_active']] as $state => [$label, $target, $suffix]) {
            $this->start_controls_tab('pagination_style_' . $state, ['label'=>$label]);
            $this->paint('pagination_link_color' . $suffix, 'Color', $nav . $target, 'color', ['condition'=>$on]);
            $this->paint('pagination_link_bg_color' . $suffix, 'Fondo', $nav . $target, 'background-color', ['condition'=>$on]);
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->end_controls_section();

        $flash = $li . ' span.onsale';
        $sale = ['show_onsale_flash'=>'yes'];
        $this->start_controls_section('sale_flash_style', ['label'=>'Etiqueta de oferta','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('show_onsale_flash', ['label'=>'Mostrar','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes']);
        $this->paint('onsale_text_color', 'Color del texto', $flash, 'color', ['condition'=>$sale]);
        $this->paint('onsale_text_background_color', 'Fondo', $flash, 'background-color', ['condition'=>$sale]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'onsale_typography','selector'=>$flash,'condition'=>$sale]);
        $this->add_control('onsale_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em'],'condition'=>$sale,'selectors'=>[$flash=>'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_control('onsale_width', ['label'=>'Ancho','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em'],'range'=>['px'=>['min'=>20,'max'=>200]],'condition'=>$sale,'selectors'=>[$flash=>'min-width: {{SIZE}}{{UNIT}};']]);
        $this->add_control('onsale_height', ['label'=>'Alto','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em'],'range'=>['px'=>['min'=>20,'max'=>200]],'condition'=>$sale,'selectors'=>[$flash=>'min-height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}};']]);
        $this->add_control('onsale_horizontal_position', ['label'=>'Posición','type'=>Controls_Manager::CHOOSE,'options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right']],'condition'=>$sale,
            'selectors_dictionary'=>['left'=>'right: auto; left: 0','right'=>'left: auto; right: 0'],'selectors'=>[$flash=>'{{VALUE}};']]);
        $this->add_control('onsale_distance', ['label'=>'Distancia al borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em'],'range'=>['px'=>['max'=>50]],'condition'=>$sale,'selectors'=>[$flash=>'margin: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    /** «Productos relacionados»-style heading that WooCommerce prints (Related and Upsells). */
    protected function heading_style(): void {
        $on = ['show_heading!'=>''];
        $heading = '{{WRAPPER}} .obx-products .products > h2';
        $this->start_controls_section('section_heading_style', ['label'=>'Encabezado','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('show_heading', ['label'=>'Mostrar','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes']);
        $this->add_control('heading_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'condition'=>$on,'selectors'=>[$heading=>'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'heading_typography','selector'=>$heading,'condition'=>$on]);
        $this->add_responsive_control('heading_text_align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(),'condition'=>$on,'selectors'=>[$heading=>'text-align: {{VALUE}};']]);
        $this->add_responsive_control('heading_spacing', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'condition'=>$on,'selectors'=>[$heading=>'margin-bottom: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    /** Wrapper classes for switches that hide WooCommerce markup. */
    protected function wrapper_class(array $s, string $extra = ''): string {
        $classes = ['obx-products', 'woocommerce'];
        if (($s['show_onsale_flash'] ?? 'yes') !== 'yes') { $classes[] = 'obx-products--no-sale'; }
        if (($s['show_pagination_border'] ?? 'yes') !== 'yes') { $classes[] = 'obx-products--no-pagination-border'; }
        if (array_key_exists('show_heading', $s) && ($s['show_heading'] ?? 'yes') !== 'yes') { $classes[] = 'obx-products--no-heading'; }
        if ($extra !== '') { $classes[] = $extra; }
        return implode(' ', $classes);
    }
}

/** Products (Pro: woocommerce-products) and Archive Products (Pro: woocommerce-archive-products). */
abstract class Woo_Products_Grid extends Woo_Products_Base {
    protected const SOURCE_DEFAULT = 'product';
    protected const PAGINATE_DEFAULT = '';
    protected const TITLES = ['related_products'=>['products_related_products_title_text','Productos relacionados'],'upsells'=>['products_upsells_title_text','También te puede interesar…'],'cross_sells'=>['products_cross_sells_title_text','Quizá te interese…']];
    public function get_keywords(): array { return ['woocommerce','tienda','shop','productos','products','rejilla','grid']; }

    protected function register_controls(): void {
        $this->start_controls_section('section_content', ['label'=>'Contenido']);
        $this->columns_control();
        $this->add_control('rows', ['label'=>'Filas','type'=>Controls_Manager::NUMBER,'default'=>4,'min'=>1,'max'=>50]);
        $this->add_control('paginate', ['label'=>'Paginación','type'=>Controls_Manager::SWITCHER,'default'=>static::PAGINATE_DEFAULT,'return_value'=>'yes','condition'=>['query_post_type!'=>['related_products','upsells','cross_sells']]]);
        $this->add_control('allow_order', ['label'=>'Permitir ordenar','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','condition'=>['paginate'=>'yes']]);
        $this->add_control('show_result_count', ['label'=>'Mostrar el número de resultados','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','condition'=>['paginate'=>'yes']]);
        $this->end_controls_section();

        $this->start_controls_section('section_query', ['label'=>'Consulta']);
        Query::product_controls($this, 'query_', []);
        $this->update_control('query_post_type', ['default'=>static::SOURCE_DEFAULT]);
        $this->end_controls_section();

        $this->start_controls_section('section_products_title', ['label'=>'Título']);
        $this->add_control('products_title_show', ['label'=>'Mostrar título','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','description'=>'Se muestra con los orígenes «Relacionados», «Ventas dirigidas» y «Ventas cruzadas».']);
        foreach (self::TITLES as $source => [$key, $default]) {
            $this->add_control($key, ['label'=>'Texto','type'=>Controls_Manager::TEXT,'default'=>$default,'dynamic'=>['active'=>true],'condition'=>['products_title_show!'=>'','query_post_type'=>$source]]);
        }
        $this->add_responsive_control('products_title_alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(),'condition'=>['products_title_show!'=>''],'selectors'=>['{{WRAPPER}} .obx-products__title'=>'text-align: {{VALUE}};']]);
        $this->end_controls_section();

        $this->extra_content_controls();

        $this->start_controls_section('products_title_style', ['label'=>'Título','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['products_title_show!'=>'']]);
        $this->add_control('products_title_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'condition'=>['products_title_show!'=>''],'selectors'=>['{{WRAPPER}} .obx-products__title'=>'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'products_title_typography','selector'=>'{{WRAPPER}} .obx-products__title','condition'=>['products_title_show!'=>'']]);
        $this->add_responsive_control('products_title_spacing', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'condition'=>['products_title_show!'=>''],'selectors'=>['{{WRAPPER}} .obx-products__title'=>'margin-bottom: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->products_style(true);
        $this->extra_style_controls();
    }

    protected function extra_content_controls(): void {}
    protected function extra_style_controls(): void {}

    /** Settings of versions before 0.10.0 (posts_per_page/orderby/order, «current_query» meant latest products). */
    public static function wants_upgrade(string $name, array $settings): bool {
        return $name === static::PRO && static::PRO === 'woocommerce-products' && isset($settings['posts_per_page']) && !isset($settings['rows']);
    }
    protected static function upgrade_settings(array $settings): array {
        $columns = max(1, (int) ($settings['columns'] ?? 4));
        $settings['rows'] = (int) max(1, ceil(max(1, (int) $settings['posts_per_page']) / $columns));
        foreach (['orderby'=>'query_orderby','order'=>'query_order'] as $old => $new) { if (isset($settings[$old])) { $settings[$new] = $settings[$old]; unset($settings[$old]); } }
        $source = (string) ($settings['query_post_type'] ?? 'current_query');
        $settings['query_post_type'] = $source === 'current_query' ? 'product' : $source;
        if (isset($settings['query_posts_ids']) && is_string($settings['query_posts_ids'])) { $settings['query_posts_ids'] = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $settings['query_posts_ids'])))); }
        unset($settings['posts_per_page']);
        return $settings;
    }

    /** The visitor's «Ordenar por» choice, when the widget allows it. */
    private static function visitor_order(array $s, string $p): array {
        if (($s['allow_order'] ?? '') !== 'yes' || empty($_GET['orderby'])) { return $s; } // phpcs:ignore WordPress.Security.NonceVerification
        $map = ['menu_order'=>['menu_order','asc'],'popularity'=>['popularity','desc'],'rating'=>['rating','desc'],'date'=>['date','desc'],'price'=>['price','asc'],'price-desc'=>['price','desc']];
        $choice = $map[sanitize_key(wp_unslash((string) $_GET['orderby']))] ?? null; // phpcs:ignore WordPress.Security.NonceVerification
        if ($choice) { [$s[$p . 'orderby'], $s[$p . 'order']] = $choice; }
        return $s;
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $columns = max(1, (int) ($s['columns'] ?? 4));
        $per_page = $columns * max(1, (int) ($s['rows'] ?? 4));
        $source = (string) ($s['query_post_type'] ?? static::SOURCE_DEFAULT);
        $grouped = in_array($source, ['related_products','upsells','cross_sells'], true);
        $paginate = !$grouped && ($s['paginate'] ?? '') === 'yes';
        $main = $source === 'current_query';
        $paged = $paginate ? max(1, $main ? (int) get_query_var('paged') : absint($_GET['product-page'] ?? 1)) : 1; // phpcs:ignore WordPress.Security.NonceVerification
        $query = Query::products($main ? $s : self::visitor_order($s, 'query_'), 'query_', $per_page, $paged, $this);
        if ($main) { $paged = max(1, (int) $query->get('paged')); }
        if (!$query->have_posts()) { $this->nothing_found($s); return; }

        wc_setup_loop(['columns'=>$columns,'name'=>'obx-products','is_shortcode'=>!$main,'is_search'=>$main && is_search(),'is_paginated'=>$paginate,
            'total'=>(int) $query->found_posts,'total_pages'=>(int) $query->max_num_pages,'per_page'=>$main ? (int) $query->get('posts_per_page') : $per_page,'current_page'=>$paged]);
        echo '<div class="' . esc_attr($this->wrapper_class($s)) . '">';
        if (($s['products_title_show'] ?? '') !== '' && isset(self::TITLES[$source])) {
            $text = (string) ($s[self::TITLES[$source][0]] ?? '');
            if ($text !== '') { echo '<h2 class="obx-products__title">' . esc_html($text) . '</h2>'; }
        }
        if ($paginate) {
            if (($s['show_result_count'] ?? '') === 'yes') { woocommerce_result_count(); }
            if (($s['allow_order'] ?? '') === 'yes') { woocommerce_catalog_ordering(); }
        }
        $previous = $GLOBALS['post'] ?? null;
        woocommerce_product_loop_start();
        foreach ($query->posts as $post) {
            $GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- restored below.
            setup_postdata($post);
            wc_get_template_part('content', 'product');
        }
        woocommerce_product_loop_end();
        if ($paginate) { woocommerce_pagination(); }
        echo '</div>';
        $GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
        if ($previous instanceof \WP_Post) { setup_postdata($previous); } else { wp_reset_postdata(); }
        wc_reset_loop();
    }

    protected function nothing_found(array $s): void {
        if ($this->editing()) { $this->placeholder('No hay productos que mostrar con esta consulta.'); }
    }
}

final class Woo_Products extends Woo_Products_Grid {
    public const PRO = 'woocommerce-products';
    public const PRO_CATEGORY = 'woocommerce-elements';
    public function get_title(): string { return 'Productos'; }
    public function get_icon(): string { return 'eicon-products'; }
}

final class Woo_Archive_Products extends Woo_Products_Grid {
    public const PRO = 'woocommerce-archive-products';
    public const PRO_CATEGORY = 'woocommerce-elements-archive';
    protected const SOURCE_DEFAULT = 'current_query';
    protected const PAGINATE_DEFAULT = 'yes';
    public function get_title(): string { return 'Productos del archivo'; }
    public function get_icon(): string { return 'eicon-products'; }

    protected function extra_content_controls(): void {
        $this->start_controls_section('section_advanced', ['label'=>'Sin resultados']);
        $this->add_control('nothing_found_message', ['label'=>'Mensaje','type'=>Controls_Manager::TEXTAREA,'default'=>'No hemos encontrado productos que coincidan.','dynamic'=>['active'=>true]]);
        $this->end_controls_section();
    }

    protected function extra_style_controls(): void {
        $message = '{{WRAPPER}} .elementor-products-nothing-found';
        $this->start_controls_section('section_nothing_found_style', ['label'=>'Sin resultados','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['nothing_found_message!'=>'']]);
        $this->add_control('nothing_found_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'condition'=>['nothing_found_message!'=>''],'selectors'=>[$message=>'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'nothing_found_typography','selector'=>$message,'condition'=>['nothing_found_message!'=>'']]);
        $this->end_controls_section();
    }

    protected function nothing_found(array $s): void {
        $message = (string) ($s['nothing_found_message'] ?? '');
        if ($message !== '') { echo '<div class="elementor-products-nothing-found obx-products__nothing">' . wp_kses_post($message) . '</div>'; return; }
        parent::nothing_found($s);
    }

    /**
     * Main shop queries get «columns × rows» products per page from the Archive Products widget of the archive
     * template that will print them, so WordPress paginates (and 404s) with the same numbers.
     */
    public static function main_query(\WP_Query $query): void {
        if (is_admin() || !$query->is_main_query() || !function_exists('is_shop')) { return; }
        $product_search = $query->is_search() && in_array('product', (array) $query->get('post_type'), true);
        if (!$query->is_post_type_archive('product') && !$query->is_tax(get_object_taxonomies('product')) && !$product_search) { return; }
        $template = Locations::template_id('archive');
        $document = $template ? Elementor::$instance->documents->get($template) : null;
        $element = $document ? self::first_widget((array) $document->get_elements_data()) : null;
        if (!$element) { return; }
        $settings = (array) ($element['settings'] ?? []);
        if (($settings['query_post_type'] ?? 'current_query') !== 'current_query') { return; }
        $query->set('posts_per_page', max(1, (int) ($settings['columns'] ?? 4)) * max(1, (int) ($settings['rows'] ?? 4)));
    }

    private static function first_widget(array $elements): ?array {
        foreach ($elements as $element) {
            if (!is_array($element)) { continue; }
            if (($element['elType'] ?? '') === 'widget' && ($element['widgetType'] ?? '') === self::PRO) { return $element; }
            if (!empty($element['elements']) && ($found = self::first_widget($element['elements']))) { return $found; }
        }
        return null;
    }
}

/** Related products and upsells: WooCommerce's own sections, with the grid and Pro's styles. */
abstract class Woo_Product_Section extends Woo_Products_Base {
    protected function register_controls(): void {
        $this->start_controls_section(static::SECTION, ['label'=>$this->get_title()]);
        if (static::PRO === 'woocommerce-product-related') { $this->add_control('posts_per_page', ['label'=>'Productos','type'=>Controls_Manager::NUMBER,'default'=>4,'min'=>1,'max'=>50]); }
        $this->columns_control();
        $this->add_control('orderby', ['label'=>'Ordenar por','type'=>Controls_Manager::SELECT,'default'=>'date','options'=>self::orderby_options()]);
        $this->add_control('order', ['label'=>'Orden','type'=>Controls_Manager::SELECT,'default'=>'desc','options'=>['asc'=>'Ascendente','desc'=>'Descendente']]);
        $this->end_controls_section();
        $this->products_style(false);
        $this->heading_style();
    }

    abstract protected function print_section(array $s, int $columns): void;

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $this->with_product(function () use ($s): void {
            $columns = max(1, (int) ($s['columns'] ?? 4));
            ob_start();
            $this->print_section($s, $columns);
            $html = (string) ob_get_clean();
            if (trim($html) === '') { if ($this->editing()) { $this->placeholder('Este producto no tiene ' . (static::PRO === 'woocommerce-product-related' ? 'productos relacionados.' : 'ventas dirigidas.')); } return; }
            echo '<div class="' . esc_attr($this->wrapper_class($s)) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce template output.
        });
    }
}

final class Woo_Product_Related extends Woo_Product_Section {
    public const PRO = 'woocommerce-product-related';
    protected const SECTION = 'section_related_products_content';
    public function get_title(): string { return 'Productos relacionados'; }
    public function get_icon(): string { return 'eicon-product-related'; }
    protected function print_section(array $s, int $columns): void {
        woocommerce_output_related_products(['posts_per_page'=>max(1, (int) ($s['posts_per_page'] ?? 4)),'columns'=>$columns,'orderby'=>sanitize_key((string) ($s['orderby'] ?? 'date')),'order'=>strtolower((string) ($s['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc']);
    }
}

final class Woo_Product_Upsell extends Woo_Product_Section {
    public const PRO = 'woocommerce-product-upsell';
    protected const SECTION = 'section_upsell_content';
    public function get_title(): string { return 'Ventas dirigidas'; }
    public function get_icon(): string { return 'eicon-product-upsell'; }
    protected function print_section(array $s, int $columns): void {
        woocommerce_upsell_display(-1, $columns, sanitize_key((string) ($s['orderby'] ?? 'date')), strtolower((string) ($s['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc');
    }
}
