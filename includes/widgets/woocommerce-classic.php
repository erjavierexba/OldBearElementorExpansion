<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;

if (!defined('ABSPATH')) { exit; }

/**
 * Elementor Pro's general WooCommerce widgets (Custom Add To Cart, Product Categories, WooCommerce Pages)
 * and its deprecated ones (Woo - Products, Woo - Single Elements), with Pro's keys. They print WooCommerce's
 * own templates and shortcodes, so theme and plugin hooks keep working.
 */

/** Custom Add To Cart (Pro: wc-add-to-cart): Elementor's button for any product, with or without a quantity field. */
final class Woo_Add_To_Cart extends Woo_Element {
    use \Elementor\Includes\Widgets\Traits\Button_Trait;
    public const PRO = 'wc-add-to-cart';
    public const PRO_CATEGORY = 'woocommerce-elements';
    public function get_title(): string { return 'Añadir al carrito personalizado'; }
    public function get_icon(): string { return 'eicon-woocommerce'; }
    public function get_keywords(): array { return ['woocommerce','tienda','shop','carrito','cart','añadir','add to cart','botón','button','producto']; }
    /** Rendered on the server: Button_Trait brings a JS template meant for the Button widget. */
    protected function content_template() {}

    protected function register_controls(): void {
        $this->start_controls_section('section_product', ['label'=>'Producto']);
        $this->add_control('product_id', ['label'=>'Producto','type'=>Controls_Manager::SELECT2,'options'=>Query::product_options(),'label_block'=>true,'description'=>'Vacío: el producto que se está mostrando (plantilla de producto o elemento de bucle).']);
        $this->add_control('show_quantity', ['label'=>'Mostrar el campo de cantidad','type'=>Controls_Manager::SWITCHER,'label_on'=>'Sí','label_off'=>'No','return_value'=>'yes','description'=>'Muestra el formulario de WooCommerce con la cantidad (y las variaciones en productos variables).']);
        $this->add_control('quantity', ['label'=>'Cantidad','type'=>Controls_Manager::NUMBER,'default'=>1,'min'=>1,'condition'=>['show_quantity'=>'']]);
        $this->end_controls_section();

        $this->start_controls_section('section_button', ['label'=>'Botón']);
        $this->register_button_content_controls(['button_default_text'=>'Añadir al carrito','text_control_label'=>'Texto']);
        // The button always adds the product to the cart.
        $this->update_control('link', ['type'=>Controls_Manager::HIDDEN]);
        $this->update_control('size', ['condition'=>['show_quantity'=>'']]);
        $this->end_controls_section();

        $this->start_controls_section('section_layout', ['label'=>'Diseño','condition'=>['show_quantity'=>'yes']]);
        $this->add_control('layout', ['label'=>'Disposición','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'En línea','stacked'=>'Apilada','auto'=>'Automática']]);
        $this->end_controls_section();

        $this->start_controls_section('section_style', ['label'=>'Botón','tab'=>Controls_Manager::TAB_STYLE]);
        $this->register_button_style_controls();
        $this->end_controls_section();
    }

    private function target(array $s): ?\WC_Product {
        $id = (int) ($s['product_id'] ?? 0);
        $product = $id ? wc_get_product($id) : $this->product();
        return $product instanceof \WC_Product ? $product : null;
    }

    /** Classes and id of the button (Elementor's button classes, so the style controls apply). */
    private function button_attributes(array $s): array {
        $classes = ['elementor-button', 'elementor-size-' . sanitize_html_class((string) ($s['size'] ?? '') ?: 'sm')];
        if (!empty($s['hover_animation'])) { $classes[] = 'elementor-animation-' . sanitize_html_class((string) $s['hover_animation']); }
        $attributes = ['class'=>$classes];
        if (!empty($s['button_css_id'])) { $attributes['id'] = (string) $s['button_css_id']; }
        return $attributes;
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $product = $this->target($s);
        if (!$product) { $this->placeholder('Elige el producto que añade este botón.'); return; }
        if (($s['show_quantity'] ?? '') === 'yes') { $this->render_form($product, $s); return; }

        $quantity = max(1, (int) ($s['quantity'] ?? 1));
        $attributes = $this->button_attributes($s);
        $attributes['class'][] = 'product_type_' . $product->get_type();
        $attributes['href'] = $product->is_type('simple') ? add_query_arg('quantity', $quantity, $product->add_to_cart_url()) : $product->add_to_cart_url();
        $attributes['rel'] = 'nofollow';
        $attributes['data-quantity'] = (string) $quantity;
        $attributes['data-product_id'] = (string) $product->get_id();
        $attributes['data-product_sku'] = (string) $product->get_sku();
        $attributes['aria-label'] = wp_strip_all_tags($product->add_to_cart_description());
        if ($product->is_purchasable() && $product->is_in_stock()) {
            $attributes['class'][] = 'add_to_cart_button';
            if ($product->supports('ajax_add_to_cart')) { $attributes['class'][] = 'ajax_add_to_cart'; }
        }
        $this->add_render_attribute('button', $attributes);
        echo '<div class="elementor-button-wrapper obx-add-to-cart"><a ';
        $this->print_render_attribute_string('button');
        echo '>';
        $this->render_text();
        echo '</a></div>';
    }

    /** WooCommerce's add-to-cart form: own markup for simple products, WooCommerce's template for the rest. */
    private function render_form(\WC_Product $product, array $s): void {
        $layout = in_array($s['layout'] ?? '', ['stacked','auto'], true) ? (string) $s['layout'] : 'inline';
        $attributes = $this->button_attributes($s);
        $class = 'obx-add-to-cart obx-add-to-cart--form obx-add-to-cart--layout-' . $layout;
        if (!$product->is_type('simple')) {
            $text = wp_strip_all_tags((string) ($s['text'] ?? ''));
            $label = static fn($default) => $text !== '' ? $text : $default;
            add_filter('woocommerce_product_single_add_to_cart_text', $label, 99);
            $previous = $GLOBALS['product'] ?? null;
            $GLOBALS['product'] = $product;
            ob_start();
            woocommerce_template_single_add_to_cart();
            $html = (string) ob_get_clean();
            $GLOBALS['product'] = $previous;
            remove_filter('woocommerce_product_single_add_to_cart_text', $label, 99);
            $extra = implode(' ', array_map('esc_attr', $attributes['class']));
            echo '<div class="' . esc_attr($class) . '">' . str_replace('class="single_add_to_cart_button ', 'class="single_add_to_cart_button ' . $extra . ' ', $html) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce's template.
            return;
        }
        if (!$product->is_purchasable() || !$product->is_in_stock()) { echo wp_kses_post(wc_get_stock_html($product)); return; }
        $attributes['class'][] = 'single_add_to_cart_button';
        $attributes['type'] = 'submit';
        $attributes['name'] = 'add-to-cart';
        $attributes['value'] = (string) $product->get_id();
        $this->add_render_attribute('button', $attributes);
        echo '<form class="cart ' . esc_attr($class) . '" action="' . esc_url($product->get_permalink()) . '" method="post" enctype="multipart/form-data">';
        woocommerce_quantity_input(['min_value'=>$product->get_min_purchase_quantity(),'max_value'=>$product->get_max_purchase_quantity(),'input_value'=>$product->get_min_purchase_quantity()], $product);
        echo '<button ';
        $this->print_render_attribute_string('button');
        echo '>';
        $this->render_text();
        echo '</button></form>';
    }
}

/** Product Categories (Pro: wc-categories): WooCommerce's category loop in the products grid. */
final class Woo_Categories extends Woo_Products_Base {
    public const PRO = 'wc-categories';
    public const PRO_CATEGORY = 'woocommerce-elements';
    public function get_title(): string { return 'Categorías de productos'; }
    public function get_icon(): string { return 'eicon-product-categories'; }
    public function get_keywords(): array { return ['woocommerce','tienda','shop','categorías','categories','productos','rejilla']; }

    /** @return array<string,string> */
    private static function categories(): array {
        $options = [];
        if (!taxonomy_exists('product_cat')) { return $options; }
        foreach (get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'number'=>300]) ?: [] as $term) { if ($term instanceof \WP_Term) { $options[(string) $term->term_id] = $term->name; } }
        return $options;
    }

    protected function register_controls(): void {
        $categories = self::categories();
        $this->start_controls_section('section_layout', ['label'=>'Diseño']);
        $this->columns_control();
        $this->add_control('number', ['label'=>'Número de categorías','type'=>Controls_Manager::NUMBER,'default'=>'4','min'=>1]);
        $this->end_controls_section();

        $this->start_controls_section('section_filter', ['label'=>'Consulta']);
        $this->add_control('source', ['label'=>'Origen','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Todas','by_id'=>'Selección manual','by_parent'=>'Por categoría madre','current_subcategories'=>'Subcategorías de la actual']]);
        $this->add_control('categories', ['label'=>'Categorías','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$categories,'condition'=>['source'=>'by_id']]);
        $this->add_control('parent', ['label'=>'Categoría madre','type'=>Controls_Manager::SELECT,'default'=>'0','options'=>['0'=>'Solo las de primer nivel'] + $categories,'condition'=>['source'=>'by_parent']]);
        $this->add_control('hide_empty', ['label'=>'Ocultar las vacías','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes']);
        $this->add_control('orderby', ['label'=>'Ordenar por','type'=>Controls_Manager::SELECT,'default'=>'name','options'=>['name'=>'Nombre','slug'=>'Slug','description'=>'Descripción','count'=>'Número de productos']]);
        $this->add_control('order', ['label'=>'Orden','type'=>Controls_Manager::SELECT,'default'=>'desc','options'=>['asc'=>'Ascendente','desc'=>'Descendente']]);
        $this->end_controls_section();

        $li = self::LI;
        $this->start_controls_section('section_products_style', ['label'=>'Categorías','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('products_class', ['type'=>Controls_Manager::HIDDEN,'default'=>'wc-products','prefix_class'=>'elementor-products-grid elementor-']);
        $this->size('column_gap', 'Separación entre columnas', '{{WRAPPER}} .obx-products', '--obx-products-column-gap: {{SIZE}}{{UNIT}};', ['default'=>['unit'=>'px','size'=>20]]);
        $this->size('row_gap', 'Separación entre filas', '{{WRAPPER}} .obx-products', '--obx-products-row-gap: {{SIZE}}{{UNIT}};', ['default'=>['unit'=>'px','size'=>40]]);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),'selectors'=>[$li=>'text-align: {{VALUE}};']]);
        $this->add_control('heading_image_style', ['label'=>'Imagen','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'image_border','selector'=>$li . ' a img']);
        $this->dims('image_border_radius', 'Radio del borde', $li . ' a img', 'border-radius');
        $this->size('image_spacing', 'Separación', $li . ' a img', 'margin-bottom: {{SIZE}}{{UNIT}};');
        $this->add_control('heading_title_style', ['label'=>'Título','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $title = $li . ' .woocommerce-loop-category__title';
        $this->paint('title_color', 'Color', $title);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'title_typography','selector'=>$title]);
        $this->add_control('heading_count_style', ['label'=>'Número de productos','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $count = $li . ' .woocommerce-loop-category__title .count';
        $this->paint('count_color', 'Color', $count);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'count_typography','selector'=>$count]);
        $this->end_controls_section();
    }

    /** Attributes of WooCommerce's [product_categories] shortcode. */
    public static function shortcode_attributes(array $s): array {
        $attributes = ['limit'=>(string) max(1, (int) ($s['number'] ?? 4)),'columns'=>(string) max(1, (int) ($s['columns'] ?? 4)),
            'hide_empty'=>($s['hide_empty'] ?? '') === 'yes' ? '1' : '0',
            'orderby'=>in_array($s['orderby'] ?? '', ['name','slug','description','count'], true) ? (string) $s['orderby'] : 'name',
            'order'=>strtolower((string) ($s['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc'];
        switch ((string) ($s['source'] ?? '')) {
            case 'by_id': $attributes['ids'] = implode(',', array_map('absint', (array) ($s['categories'] ?? []))); break;
            case 'by_parent': $attributes['parent'] = (string) absint($s['parent'] ?? 0); break;
            case 'current_subcategories':
                $term = get_queried_object();
                $attributes['parent'] = (string) ($term instanceof \WP_Term && $term->taxonomy === 'product_cat' ? $term->term_id : 0);
                break;
        }
        return $attributes;
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $html = class_exists('WC_Shortcodes') ? (string) \WC_Shortcodes::product_categories(self::shortcode_attributes($s)) : '';
        if (!str_contains($html, '<li')) { $this->placeholder('No hay categorías de productos que mostrar.'); return; }
        echo '<div class="' . esc_attr($this->wrapper_class($s, 'obx-product-categories')) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce's shortcode.
    }
}

/** WooCommerce Pages (Pro: wc-elements): one of WooCommerce's page shortcodes. */
final class Woo_Pages extends Woo_Element {
    public const PRO = 'wc-elements';
    public const PRO_CATEGORY = 'woocommerce-elements';
    public const ELEMENTS = ['woocommerce_cart'=>'Carrito','product_page'=>'Página de producto','woocommerce_checkout'=>'Finalizar compra','woocommerce_order_tracking'=>'Seguimiento del pedido','woocommerce_my_account'=>'Mi cuenta'];
    public function get_title(): string { return 'Páginas de WooCommerce'; }
    public function get_icon(): string { return 'eicon-product-pages'; }
    public function get_keywords(): array { return ['woocommerce','tienda','shop','páginas','pages','carrito','cart','checkout','cuenta','account','pedido','order','shortcode']; }

    protected function register_controls(): void {
        $this->start_controls_section('section_product', ['label'=>'Elemento']);
        $this->add_control('element', ['label'=>'Página','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'— Elige —'] + self::ELEMENTS]);
        $this->add_control('product_id', ['label'=>'Producto','type'=>Controls_Manager::SELECT2,'options'=>Query::product_options(),'label_block'=>true,'condition'=>['element'=>['product_page']]]);
        $this->end_controls_section();
    }

    /** @return string the shortcode for the settings, or '' when incomplete. */
    public static function shortcode(array $s): string {
        $element = (string) ($s['element'] ?? '');
        if (!isset(self::ELEMENTS[$element])) { return ''; }
        if ($element !== 'product_page') { return '[' . $element . ']'; }
        $id = absint($s['product_id'] ?? 0);
        return $id ? '[product_page id="' . $id . '"]' : '';
    }

    protected function render(): void {
        $shortcode = self::shortcode($this->get_settings_for_display());
        if ($shortcode === '') { $this->placeholder('Elige la página de WooCommerce que quieres mostrar.'); return; }
        // Editor and AJAX renders do not load the cart that these shortcodes read.
        if (function_exists('wc_load_cart') && function_exists('WC') && !WC()->cart) { wc_load_cart(); }
        // Order tracking posts its form to the global post's permalink.
        $previous = $GLOBALS['post'] ?? null;
        if (!$previous instanceof \WP_Post) { $GLOBALS['post'] = \OldBearExpansion\Context::post() ?: get_post((int) get_option('page_on_front')) ?: null; } // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- restored below.
        $html = $GLOBALS['post'] instanceof \WP_Post || $shortcode !== '[woocommerce_order_tracking]' ? do_shortcode($shortcode) : '';
        $GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
        echo '<div class="obx-woo-page">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce's shortcode.
    }
}

/** Woo - Single Elements (Pro: wc-single-elements, deprecated): one WooCommerce template function of the product. */
final class Woo_Single_Elements extends Woo_Element {
    public const PRO = 'wc-single-elements';
    public const ELEMENTS = [
        'woocommerce_output_product_data_tabs'=>'Pestañas de datos','woocommerce_template_single_title'=>'Título','woocommerce_template_single_rating'=>'Valoración',
        'woocommerce_template_single_price'=>'Precio','woocommerce_template_single_excerpt'=>'Descripción corta','woocommerce_template_single_meta'=>'Metadatos',
        'woocommerce_template_single_sharing'=>'Compartir','woocommerce_show_product_sale_flash'=>'Distintivo de oferta',
        'woocommerce_product_additional_information_tab'=>'Información adicional','woocommerce_upsell_display'=>'Ventas dirigidas','wc_get_stock_html'=>'Existencias',
    ];
    public function get_title(): string { return 'Elementos de producto (obsoleto)'; }
    public function get_icon(): string { return 'eicon-woocommerce'; }
    public function show_in_panel(): bool { return false; }

    protected function register_controls(): void {
        $this->start_controls_section('section_product', ['label'=>'Elemento']);
        $this->add_control('deprecated_notice', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Este widget está obsoleto: usa los widgets de producto individual (título, precio, pestañas…).','content_classes'=>'elementor-panel-alert elementor-panel-alert-info']);
        $this->add_control('element', ['label'=>'Elemento','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'— Elige —'] + self::ELEMENTS]);
        $this->end_controls_section();
    }

    protected function render(): void {
        $element = (string) $this->get_settings_for_display('element');
        if (!isset(self::ELEMENTS[$element])) { $this->placeholder('Elige el elemento del producto que quieres mostrar.'); return; }
        $this->with_product(static function (\WC_Product $product) use ($element): void {
            echo '<div class="obx-single-element product">';
            if ($element === 'woocommerce_output_product_data_tabs') { Woo_Product_Data_Tabs::print_tabs($product); }
            elseif ($element === 'wc_get_stock_html') { echo wp_kses_post(wc_get_stock_html($product)); }
            elseif (function_exists($element)) { $element(); }
            echo '</div>';
        });
    }
}

/** Woo - Products (Pro: wc-products, deprecated): the old product grid with its «posts_» query keys. */
final class Woo_Products_Deprecated extends Woo_Products_Base {
    public const PRO = 'wc-products';
    public const PRO_CATEGORY = 'woocommerce-elements';
    public function get_title(): string { return 'Productos (obsoleto)'; }
    public function get_icon(): string { return 'eicon-products'; }
    public function show_in_panel(): bool { return false; }

    /** @return array<string,string> terms of a product taxonomy. */
    private static function terms(string $taxonomy): array {
        $options = [];
        if (!taxonomy_exists($taxonomy)) { return $options; }
        foreach (get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false,'number'=>300]) ?: [] as $term) { if ($term instanceof \WP_Term) { $options[(string) $term->term_id] = $term->name; } }
        return $options;
    }

    protected function register_controls(): void {
        $this->start_controls_section('Deprecated', ['label'=>'Obsoleto']);
        // Pro's only skin; classic_columns depends on it.
        $this->add_control('_skin', ['type'=>Controls_Manager::HIDDEN,'default'=>'classic']);
        $this->add_control('deprecated_notice', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Este widget está obsoleto: usa «Productos», que tiene las mismas opciones y más.','content_classes'=>'elementor-panel-alert elementor-panel-alert-info']);
        $this->end_controls_section();

        $this->start_controls_section('section_layout', ['label'=>'Diseño']);
        $this->add_control('classic_columns', ['label'=>'Columnas','type'=>Controls_Manager::SELECT,'default'=>'4','options'=>array_combine(array_map('strval', range(1, 6)), array_map('strval', range(1, 6))),
            'condition'=>['_skin'=>'classic'],'selectors'=>['{{WRAPPER}} .obx-products'=>'--obx-products-columns: {{VALUE}};']]);
        $this->add_control('posts_per_page', ['label'=>'Productos','type'=>Controls_Manager::NUMBER,'default'=>'4','min'=>1]);
        $this->end_controls_section();

        $authors = [];
        foreach (get_users(['capability'=>['edit_posts'],'number'=>200,'fields'=>['ID','display_name']]) as $user) { $authors[(string) $user->ID] = $user->display_name; }
        $products = Query::product_options();
        $this->start_controls_section('section_filter', ['label'=>'Consulta']);
        $this->add_control('posts_post_type', ['label'=>'Origen','type'=>Controls_Manager::SELECT,'default'=>'product','options'=>['product'=>'Últimos productos','by_id'=>'Selección manual','current_query'=>'Consulta actual']]);
        $this->add_control('posts_posts_ids', ['label'=>'Productos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$products,'condition'=>['posts_post_type'=>'by_id']]);
        $this->add_control('posts_authors', ['label'=>'Autores','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$authors,'condition'=>['posts_post_type!'=>['by_id','current_query']]]);
        foreach (['product_brand'=>'Marcas','product_cat'=>'Categorías','product_tag'=>'Etiquetas'] as $taxonomy => $label) {
            $this->add_control('posts_' . $taxonomy . '_ids', ['label'=>$label,'type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>self::terms($taxonomy),'condition'=>['posts_post_type'=>['product']]]);
        }
        $this->add_control('filter_by', ['label'=>'Filtrar por','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Ninguno','featured'=>'Destacados','sale'=>'Rebajados'],'condition'=>['posts_post_type'=>'product']]);
        $this->add_control('orderby', ['label'=>'Ordenar por','type'=>Controls_Manager::SELECT,'default'=>'date','options'=>self::orderby_options()]);
        $this->add_control('order', ['label'=>'Orden','type'=>Controls_Manager::SELECT,'default'=>'desc','options'=>['asc'=>'Ascendente','desc'=>'Descendente']]);
        $this->add_control('exclude', ['label'=>'Excluir','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>['current_post'=>'Producto actual','manual_selection'=>'Selección manual'],'condition'=>['posts_post_type!'=>['by_id','current_query']]]);
        $this->add_control('exclude_ids', ['label'=>'Productos excluidos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$products,'condition'=>['exclude'=>'manual_selection','posts_post_type!'=>['by_id','current_query']]]);
        $this->add_control('avoid_duplicates', ['label'=>'Evitar duplicados','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','condition'=>['posts_post_type!'=>['by_id','current_query']]]);
        $this->end_controls_section();

        $this->products_style(true);
    }

    /** The old keys as the «query_» settings of Products. */
    public static function query_settings(array $s): array {
        $source = (string) ($s['posts_post_type'] ?? 'product');
        $filter = (string) ($s['filter_by'] ?? '');
        $q = ['query_post_type'=>$source === 'product' && in_array($filter, ['featured','sale'], true) ? $filter : (in_array($source, ['by_id','current_query'], true) ? $source : 'product'),
            'query_posts_ids'=>(array) ($s['posts_posts_ids'] ?? []),'query_orderby'=>(string) ($s['orderby'] ?? 'date'),'query_order'=>(string) ($s['order'] ?? 'desc'),
            'query_exclude'=>(array) ($s['exclude'] ?? []),'query_exclude_ids'=>(array) ($s['exclude_ids'] ?? []),'query_avoid_duplicates'=>(string) ($s['avoid_duplicates'] ?? '')];
        $terms = array_merge((array) ($s['posts_product_cat_ids'] ?? []), (array) ($s['posts_product_tag_ids'] ?? []), (array) ($s['posts_product_brand_ids'] ?? []));
        $authors = (array) ($s['posts_authors'] ?? []);
        $q['query_include'] = array_keys(array_filter(['terms'=>$terms,'authors'=>$authors]));
        $q['query_include_term_ids'] = $terms;
        $q['query_include_authors'] = $authors;
        return $q;
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $columns = max(1, (int) ($s['classic_columns'] ?? 4));
        $per_page = max(1, (int) ($s['posts_per_page'] ?? 4));
        $query = Query::products(self::query_settings($s), 'query_', $per_page, 1, $this);
        if (!$query->have_posts()) { if ($this->editing()) { $this->placeholder('No hay productos que mostrar con esta consulta.'); } return; }
        wc_setup_loop(['columns'=>$columns,'name'=>'obx-products','is_shortcode'=>true,'is_paginated'=>false,'total'=>(int) $query->found_posts,'total_pages'=>1,'per_page'=>$per_page,'current_page'=>1]);
        echo '<div class="' . esc_attr($this->wrapper_class($s)) . '">';
        $posts = ($s['posts_post_type'] ?? '') === 'current_query' ? array_slice($query->posts, 0, $per_page) : $query->posts;
        $this->print_loop($posts);
        echo '</div>';
        wc_reset_loop();
    }
}
