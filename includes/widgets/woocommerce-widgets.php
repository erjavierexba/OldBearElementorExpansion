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
