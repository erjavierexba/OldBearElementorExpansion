<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use OldBearExpansion\Context;

if (!defined('ABSPATH')) { exit; }

/** Shared runtime for Elementor Pro-named WooCommerce widgets. */
abstract class Woo_Element extends Element {
    public const PRO_CATEGORY = 'woocommerce-elements-single';
    public static function available(): bool { return class_exists('WooCommerce') && function_exists('wc_get_product'); }
    protected function is_dynamic_content(): bool { return true; }
    protected function product(): ?\WC_Product { $post = Context::post(); $product = $post ? wc_get_product($post->ID) : null; return $product ?: null; }
    protected function with_product(callable $render): void {
        $product = $this->product(); if (!$product) { $this->placeholder('Este widget necesita un producto para la vista previa.'); return; }
        $old = $GLOBALS['product'] ?? null; $GLOBALS['product'] = $product; $render($product); $GLOBALS['product'] = $old;
    }
    protected function basic_style(string $selector, string $label = 'Contenido'): void { $this->text_style($selector, $label, 'woo', false); }
}

final class Woo_Breadcrumbs extends Woo_Element {
    public const PRO = 'woocommerce-breadcrumb'; public const PRO_CATEGORY = 'woocommerce-elements';
    public function get_title(): string { return 'Migas de pan de WooCommerce'; } public function get_icon(): string { return 'eicon-product-breadcrumbs'; }
    protected function register_controls(): void { $this->start_controls_section('section_content',['label'=>'Migas de pan']); $this->add_control('delimiter',['label'=>'Separador','type'=>Controls_Manager::TEXT,'default'=>' / ']); $this->end_controls_section(); $this->basic_style('.woocommerce-breadcrumb'); }
    protected function render(): void { if (function_exists('woocommerce_breadcrumb')) { woocommerce_breadcrumb(['delimiter'=>(string) ($this->get_settings_for_display('delimiter') ?: ' / ')]); } }
}

final class Woo_Menu_Cart extends Woo_Element {
    public const PRO = 'woocommerce-menu-cart'; public const PRO_CATEGORY = 'woocommerce-elements';
    public function get_title(): string { return 'Carrito del menú'; } public function get_icon(): string { return 'eicon-cart'; }
    protected function register_controls(): void { $this->start_controls_section('section_menu_cart',['label'=>'Carrito']); $this->add_control('items_indicator',['label'=>'Mostrar cantidad','type'=>Controls_Manager::SWITCHER,'default'=>'yes']); $this->add_control('show_subtotal',['label'=>'Mostrar subtotal','type'=>Controls_Manager::SWITCHER,'default'=>'yes']); $this->add_control('cart_icon',['label'=>'Icono','type'=>Controls_Manager::ICONS,'default'=>['value'=>'fas fa-shopping-cart','library'=>'fa-solid']]); $this->end_controls_section(); $this->basic_style('.obx-menu-cart'); }
    protected function render(): void { $cart = WC()->cart; if (!$cart) { return; } echo '<a class="obx-menu-cart" href="' . esc_url(wc_get_cart_url()) . '" aria-label="Ver carrito">'; \Elementor\Icons_Manager::render_icon((array) $this->get_settings_for_display('cart_icon'), ['aria-hidden'=>'true']); if ($this->get_settings_for_display('items_indicator') === 'yes') { echo '<span class="obx-menu-cart__count">' . esc_html((string) $cart->get_cart_contents_count()) . '</span>'; } if ($this->get_settings_for_display('show_subtotal') === 'yes') { echo '<span class="obx-menu-cart__subtotal">' . wp_kses_post($cart->get_cart_subtotal()) . '</span>'; } echo '</a>'; }
}

final class Woo_Product_Title extends Woo_Element {
    public const PRO = 'woocommerce-product-title'; public function get_title(): string { return 'Título del producto'; } public function get_icon(): string { return 'eicon-product-title'; }
    protected function register_controls(): void { $this->start_controls_section('section_title',['label'=>'Título']); $this->add_control('header_size',['label'=>'Etiqueta HTML','type'=>Controls_Manager::SELECT,'default'=>'h1','options'=>['h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','div'=>'div','p'=>'p']]); $this->end_controls_section(); $this->basic_style('.product_title'); }
    protected function render(): void { $this->with_product(function($p){ $tag = in_array($this->get_settings_for_display('header_size'),['h1','h2','h3','h4','div','p'],true) ? $this->get_settings_for_display('header_size') : 'h1'; echo '<' . $tag . ' class="product_title entry-title">' . esc_html($p->get_name()) . '</' . $tag . '>'; }); }
}

final class Woo_Product_Images extends Woo_Element {
    public const PRO = 'woocommerce-product-images'; public function get_title(): string { return 'Imágenes del producto'; } public function get_icon(): string { return 'eicon-product-images'; }
    protected function register_controls(): void { $this->start_controls_section('section_images',['label'=>'Imágenes']); $this->add_control('sale_flash',['label'=>'Etiqueta de oferta','type'=>Controls_Manager::SWITCHER,'default'=>'yes']); $this->end_controls_section(); }
    protected function render(): void { $this->with_product(function(){ if ($this->get_settings_for_display('sale_flash') === 'yes') { woocommerce_show_product_sale_flash(); } woocommerce_show_product_images(); }); }
}

final class Woo_Product_Price extends Woo_Element {
    public const PRO = 'woocommerce-product-price'; public function get_title(): string { return 'Precio del producto'; } public function get_icon(): string { return 'eicon-product-price'; }
    protected function register_controls(): void { $this->basic_style('.price','Precio'); }
    protected function render(): void { $this->with_product(function($p){ echo '<p class="price">' . wp_kses_post($p->get_price_html()) . '</p>'; }); }
}

final class Woo_Product_Add_To_Cart extends Woo_Element {
    public const PRO = 'woocommerce-product-add-to-cart'; public function get_title(): string { return 'Añadir al carrito'; } public function get_icon(): string { return 'eicon-product-add-to-cart'; }
    protected function register_controls(): void { $this->start_controls_section('section_atc',['label'=>'Botón']); $this->add_control('show_quantity',['label'=>'Mostrar cantidad','type'=>Controls_Manager::SWITCHER,'default'=>'yes']); $this->end_controls_section(); $this->button_style('.single_add_to_cart_button','Botón','woo_button'); }
    protected function render(): void { $this->with_product(function(){ woocommerce_template_single_add_to_cart(); }); }
}

final class Woo_Product_Rating extends Woo_Element {
    public const PRO = 'woocommerce-product-rating'; public function get_title(): string { return 'Valoración del producto'; } public function get_icon(): string { return 'eicon-product-rating'; }
    protected function register_controls(): void { $this->basic_style('.woocommerce-product-rating','Valoración'); }
    protected function render(): void { $this->with_product(function(){ woocommerce_template_single_rating(); }); }
}

final class Woo_Product_Stock extends Woo_Element {
    public const PRO = 'woocommerce-product-stock'; public function get_title(): string { return 'Existencias del producto'; } public function get_icon(): string { return 'eicon-product-stock'; }
    protected function register_controls(): void { $this->basic_style('.stock','Existencias'); }
    protected function render(): void { $this->with_product(function($p){ echo wp_kses_post(wc_get_stock_html($p)); }); }
}

final class Woo_Product_Meta extends Woo_Element {
    public const PRO = 'woocommerce-product-meta'; public function get_title(): string { return 'Metadatos del producto'; } public function get_icon(): string { return 'eicon-product-meta'; }
    protected function register_controls(): void { $this->start_controls_section('section_meta',['label'=>'Metadatos']); foreach(['sku'=>'SKU','category'=>'Categorías','tag'=>'Etiquetas'] as $k=>$l){$this->add_control('show_'.$k,['label'=>$l,'type'=>Controls_Manager::SWITCHER,'default'=>'yes']);} $this->end_controls_section(); $this->basic_style('.product_meta'); }
    protected function render(): void { $this->with_product(function(){ woocommerce_template_single_meta(); }); }
}

final class Woo_Product_Content extends Woo_Element {
    public const PRO = 'woocommerce-product-content'; public function get_title(): string { return 'Contenido del producto'; } public function get_icon(): string { return 'eicon-product-content'; }
    protected function register_controls(): void { $this->basic_style('.woocommerce-product-content'); }
    protected function render(): void { $post=Context::post(); if($post){ echo '<div class="woocommerce-product-content">' . apply_filters('the_content',$post->post_content) . '</div>'; } }
}

final class Woo_Product_Short_Description extends Woo_Element {
    public const PRO = 'woocommerce-product-short-description'; public function get_title(): string { return 'Descripción corta'; } public function get_icon(): string { return 'eicon-product-description'; }
    protected function register_controls(): void { $this->basic_style('.woocommerce-product-details__short-description'); }
    protected function render(): void { $this->with_product(function($p){ echo '<div class="woocommerce-product-details__short-description">' . wp_kses_post(wpautop($p->get_short_description())) . '</div>'; }); }
}

final class Woo_Product_Data_Tabs extends Woo_Element {
    public const PRO = 'woocommerce-product-data-tabs'; public function get_title(): string { return 'Pestañas del producto'; } public function get_icon(): string { return 'eicon-product-tabs'; }
    protected function register_controls(): void { $this->basic_style('.woocommerce-tabs'); }
    protected function render(): void { $this->with_product(function(){ woocommerce_output_product_data_tabs(); }); }
}

final class Woo_Product_Additional_Information extends Woo_Element {
    public const PRO = 'woocommerce-product-additional-information'; public function get_title(): string { return 'Información adicional'; } public function get_icon(): string { return 'eicon-product-info'; }
    protected function register_controls(): void { $this->start_controls_section('section_info',['label'=>'Información']); $this->add_control('title',['label'=>'Mostrar título','type'=>Controls_Manager::SWITCHER,'default'=>'yes']); $this->end_controls_section(); $this->basic_style('.woocommerce-product-attributes'); }
    protected function render(): void { $this->with_product(function($p){ wc_get_template('single-product/tabs/additional-information.php',['product'=>$p,'heading'=>$this->get_settings_for_display('title')==='yes'?__('Additional information','woocommerce'):'']); }); }
}

final class Woo_Archive_Description extends Woo_Element {
    public const PRO='woocommerce-archive-description'; public const PRO_CATEGORY='woocommerce-elements-archive'; public function get_title():string{return 'Descripción del archivo';} public function get_icon():string{return 'eicon-archive-description';}
    protected function register_controls():void{$this->basic_style('.woocommerce-archive-description');}
    protected function render():void{echo '<div class="woocommerce-archive-description">'; do_action('woocommerce_archive_description'); echo '</div>';}
}
abstract class Woo_Page extends Woo_Element { public const PRO_CATEGORY='woocommerce-elements'; protected function register_controls():void{$this->basic_style('.woocommerce');} }
final class Woo_Cart extends Woo_Page { public const PRO='woocommerce-cart'; public function get_title():string{return 'Carrito';} public function get_icon():string{return 'eicon-cart';} protected function render():void{echo do_shortcode('[woocommerce_cart]');} }
final class Woo_Checkout extends Woo_Page { public const PRO='woocommerce-checkout-page'; public function get_title():string{return 'Finalizar compra';} public function get_icon():string{return 'eicon-checkout';} protected function render():void{echo do_shortcode('[woocommerce_checkout]');} }
final class Woo_My_Account extends Woo_Page { public const PRO='woocommerce-my-account'; public function get_title():string{return 'Mi cuenta';} public function get_icon():string{return 'eicon-my-account';} protected function render():void{echo do_shortcode('[woocommerce_my_account]');} }
