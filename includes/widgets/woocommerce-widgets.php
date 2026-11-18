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

final class Woo_Menu_Cart extends Woo_Element {
    public const PRO = 'woocommerce-menu-cart'; public const PRO_CATEGORY = 'woocommerce-elements';
    public function get_title(): string { return 'Carrito del menú'; } public function get_icon(): string { return 'eicon-cart'; }
    protected function register_controls(): void { $this->start_controls_section('section_menu_cart',['label'=>'Carrito']); $this->add_control('items_indicator',['label'=>'Mostrar cantidad','type'=>Controls_Manager::SWITCHER,'default'=>'yes']); $this->add_control('show_subtotal',['label'=>'Mostrar subtotal','type'=>Controls_Manager::SWITCHER,'default'=>'yes']); $this->add_control('cart_icon',['label'=>'Icono','type'=>Controls_Manager::ICONS,'default'=>['value'=>'fas fa-shopping-cart','library'=>'fa-solid']]); $this->end_controls_section(); $this->basic_style('.obx-menu-cart'); }
    protected function render(): void { $cart = WC()->cart; if (!$cart) { return; } echo '<a class="obx-menu-cart" href="' . esc_url(wc_get_cart_url()) . '" aria-label="Ver carrito">'; \Elementor\Icons_Manager::render_icon((array) $this->get_settings_for_display('cart_icon'), ['aria-hidden'=>'true']); if ($this->get_settings_for_display('items_indicator') === 'yes') { echo '<span class="obx-menu-cart__count">' . esc_html((string) $cart->get_cart_contents_count()) . '</span>'; } if ($this->get_settings_for_display('show_subtotal') === 'yes') { echo '<span class="obx-menu-cart__subtotal">' . wp_kses_post($cart->get_cart_subtotal()) . '</span>'; } echo '</a>'; }
}

abstract class Woo_Page extends Woo_Element { public const PRO_CATEGORY='woocommerce-elements'; protected function register_controls():void{$this->basic_style('.woocommerce');} }
final class Woo_Cart extends Woo_Page { public const PRO='woocommerce-cart'; public function get_title():string{return 'Carrito';} public function get_icon():string{return 'eicon-cart';} protected function render():void{echo do_shortcode('[woocommerce_cart]');} }
final class Woo_Checkout extends Woo_Page { public const PRO='woocommerce-checkout-page'; public function get_title():string{return 'Finalizar compra';} public function get_icon():string{return 'eicon-checkout';} protected function render():void{echo do_shortcode('[woocommerce_checkout]');} }
final class Woo_My_Account extends Woo_Page { public const PRO='woocommerce-my-account'; public function get_title():string{return 'Mi cuenta';} public function get_icon():string{return 'eicon-my-account';} protected function render():void{echo do_shortcode('[woocommerce_my_account]');} }
