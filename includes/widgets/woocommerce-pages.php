<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;

if (!defined('ABSPATH')) { exit; }

/**
 * Cart, Checkout, My Account, Purchase Summary and Notices with Elementor Pro's keys. They print WooCommerce's
 * own shortcodes and templates (so payment gateways and plugins keep working) and style them by selector.
 * Texts change through WooCommerce's filters, or a scoped gettext filter for strings without one; the texts are
 * kept in the customer's session so the AJAX refreshes of WooCommerce (totals, order review) print them too.
 */
abstract class Woo_Page_Widget extends Woo_Single_Element {
    public const PRO_CATEGORY = 'woocommerce-elements';
    protected const BORDER_TYPES = ['none'=>'Ninguno','solid'=>'Continuo','double'=>'Doble','dotted'=>'Punteado','dashed'=>'Discontinuo','groove'=>'Ranurado'];
    /** WooCommerce string => setting key, applied while the widget renders. */
    protected const TEXTS = [];
    /** Session key for the texts of the AJAX refreshes. */
    protected const SESSION = '';

    public function get_script_depends(): array { return ['obx-widgets']; }
    protected function is_dynamic_content(): bool { return true; }

    public static function wants_upgrade(string $name, array $settings): bool { return $name === static::PRO && parent::wants_upgrade($name, $settings); }

    /* ---------- Control builders ---------- */

    protected function gap(string $key, string $label, array $selectors, array $extra = [], array $units = ['px','em','rem','custom']): void {
        $this->add_responsive_control($key, ['label'=>$label,'type'=>Controls_Manager::SLIDER,'size_units'=>$units,'range'=>['px'=>['min'=>0,'max'=>100]],'selectors'=>$selectors] + $extra);
    }

    /** Transition in seconds, stored as a unitless number like Pro's. */
    protected function duration(string $key, string $selector, array $extra = []): void {
        $this->add_control($key, ['label'=>'Duración de la transición (s)','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>0,'max'=>3,'step'=>0.1]],'selectors'=>[$selector=>'transition-duration: {{SIZE}}s;']] + $extra);
    }

    protected function border_type(string $key, string $selector, array $extra = [], string $property = 'border-style'): void {
        $this->add_control($key, ['label'=>'Tipo de borde','type'=>Controls_Manager::SELECT,'options'=>self::BORDER_TYPES,'selectors'=>[$selector=>$property . ': {{VALUE}};']] + $extra);
    }

    /** Colour + typography (+ text shadow, + spacing) of one kind of text. */
    protected function text(string $label, ?string $color, ?string $typo, string $selector, array $cond = [], ?string $shadow = null, ?string $spacing = null, string $spacing_property = 'margin-bottom'): void {
        $c = $cond ? ['condition'=>$cond] : [];
        $this->add_control('heading_' . ($color ?? $typo), ['label'=>$label,'type'=>Controls_Manager::HEADING,'separator'=>'before'] + $c);
        if ($color) { $this->paint($color, 'Color', $selector, 'color', $c); }
        if ($typo) { $this->typo($typo, $selector, $c); }
        if ($shadow) { $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>$shadow,'selector'=>$selector] + $c); }
        if ($spacing) { $this->gap($spacing, 'Separación', [$selector=>$spacing_property . ': {{SIZE}}{{UNIT}};'], $c); }
    }

    /** Box of a section: background, shadow, border, radius, padding and margin with Pro's (irregular) keys. */
    protected function box(string $selector, array $k, array $cond = []): void {
        $c = $cond ? ['condition'=>$cond] : [];
        if (isset($k['bg'])) { $this->paint($k['bg'], 'Fondo', $selector, 'background-color', $c); }
        if (isset($k['shadow'])) { $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>$k['shadow'],'selector'=>$selector] + $c); }
        if (isset($k['type'])) {
            $this->border_type($k['type'], $selector, $c);
            $on = ['condition'=>$cond + [$k['type'] . '!'=>'none']];
            $this->add_responsive_control($k['width'], ['label'=>'Grosor del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'selectors'=>[$selector=>'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']] + $on);
            $this->paint($k['color'], 'Color del borde', $selector, 'border-color', $on);
        }
        if (isset($k['radius'])) { $this->dims($k['radius'], 'Radio del borde', $selector, 'border-radius', $c); }
        if (isset($k['padding'])) { $this->dims($k['padding'], 'Relleno', $selector, 'padding', $c); }
        if (isset($k['margin'])) { $this->dims($k['margin'], 'Margen', $selector, 'margin', $c); }
    }

    /**
     * Button styles. $k: typo, tshadow, text, bg (background group), shadow, hover_text, hover_bg, hover_shadow,
     * hover_border, transition, animation, border_group (group name) or border (prefix with _type/_width/_color), radius, padding.
     */
    protected function button_set(string $selector, array $k, array $cond = []): void {
        $c = $cond ? ['condition'=>$cond] : [];
        $hover = implode(', ', array_map(static fn($s) => trim($s) . ':hover', explode(',', $selector)));
        $this->typo($k['typo'], $selector, $c);
        if (isset($k['tshadow'])) { $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>$k['tshadow'],'selector'=>$selector] + $c); }
        $this->start_controls_tabs($k['tabs'] ?? $k['text'] . '_tabs', $c);
        $this->start_controls_tab(($k['tabs'] ?? $k['text'] . '_tabs') . '_normal', ['label'=>'Normal'] + $c);
        $this->paint($k['text'], 'Color del texto', $selector, 'color', $c);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>$k['bg'],'types'=>['classic','gradient'],'selector'=>$selector] + $c);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>$k['shadow'],'selector'=>$selector] + $c);
        $this->end_controls_tab();
        $this->start_controls_tab(($k['tabs'] ?? $k['text'] . '_tabs') . '_hover', ['label'=>'Al pasar el ratón'] + $c);
        $this->paint($k['hover_text'], 'Color del texto', $hover, 'color', $c);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>$k['hover_bg'],'types'=>['classic','gradient'],'selector'=>$hover] + $c);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>$k['hover_shadow'],'selector'=>$hover] + $c);
        $border_on = isset($k['border_group']) ? [$k['border_group'] . '_border!'=>''] : [$k['border'] . '_type!'=>'none'];
        $this->paint($k['hover_border'], 'Color del borde', $hover, 'border-color', ['condition'=>$cond + $border_on]);
        $this->duration($k['transition'], $selector, $c);
        $this->add_control($k['animation'], ['label'=>'Animación','type'=>Controls_Manager::HOVER_ANIMATION,'frontend_available'=>true] + $c);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        if (isset($k['border_group'])) {
            $this->add_group_control(Group_Control_Border::get_type(), ['name'=>$k['border_group'],'selector'=>$selector,'separator'=>'before'] + $c);
        } else {
            $this->border_type($k['border'] . '_type', $selector, $c + ['separator'=>'before']);
            $on = ['condition'=>$cond + [$k['border'] . '_type!'=>'none']];
            $this->add_responsive_control($k['border'] . '_width', ['label'=>'Grosor del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','em','rem','vw','custom'],'selectors'=>[$selector=>'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']] + $on);
            $this->paint($k['border'] . '_color', 'Color del borde', $selector, 'border-color', $on);
        }
        $this->dims($k['radius'], 'Radio del borde', $selector, 'border-radius', $c);
        $this->dims($k['padding'], 'Relleno', $selector, 'padding', $c);
    }

    /** Form fields (and optionally labels and columns) with Pro's «forms_*» keys. */
    protected function form_fields(string $rows, string $fields, ?string $labels = null, ?string $columns = null): void {
        if ($columns) { $this->gap('forms_columns_gap', 'Separación entre columnas', [$columns=>'--obx-forms-columns-gap: {{SIZE}}{{UNIT}};']); }
        $this->gap('forms_rows_gap', 'Separación entre filas', [$rows=>'margin-bottom: {{SIZE}}{{UNIT}};']);
        if ($labels) {
            $this->text('Etiquetas', 'forms_label_color', 'forms_label_typography', $labels);
            $this->gap('forms_label_spacing', 'Separación', [$labels=>'margin-bottom: {{SIZE}}{{UNIT}};']);
        }
        $focus = implode(', ', array_map(static fn($s) => trim($s) . ':focus', explode(',', $fields)));
        $this->add_control('heading_forms_fields', ['label'=>'Campos','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->typo('forms_field_typography', $fields);
        $this->start_controls_tabs('forms_fields_styles');
        $this->start_controls_tab('forms_fields_normal', ['label'=>'Normal']);
        $this->paint('forms_fields_normal_color', 'Color del texto', $fields);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'forms_fields_normal_background','types'=>['classic','gradient'],'selector'=>$fields]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'forms_fields_normal_box_shadow','selector'=>$fields]);
        $this->end_controls_tab();
        $this->start_controls_tab('forms_fields_focus', ['label'=>'Al escribir']);
        $this->paint('forms_fields_focus_color', 'Color del texto', $focus);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'forms_fields_focus_background','types'=>['classic','gradient'],'selector'=>$focus]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'forms_fields_focus_box_shadow','selector'=>$focus]);
        $this->paint('forms_fields_focus_border_color', 'Color del borde', $focus, 'border-color', ['condition'=>['forms_fields_border_border!'=>'']]);
        $this->duration('forms_fields_focus_transition_duration', $fields);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'forms_fields_border','selector'=>$fields,'separator'=>'before']);
        $this->dims('forms_fields_border_radius', 'Radio del borde', $fields, 'border-radius');
        $this->dims('forms_fields_padding', 'Relleno', $fields, 'padding');
    }

    /** «forms_*» buttons; My Account names two keys differently and uses a border group. */
    protected function form_buttons(string $selector, bool $account = false): void {
        $this->add_control('heading_forms_buttons', ['label'=>'Botones','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->button_set($selector, ['typo'=>'forms_button_typography','tshadow'=>'forms_button_text_shadow','tabs'=>'forms_buttons_styles','text'=>'forms_buttons_normal_text_color','bg'=>$account ? 'forms_buttons_background' : 'forms_buttons_normal_background','shadow'=>'forms_buttons_normal_box_shadow','hover_text'=>'forms_buttons_hover_text_color','hover_bg'=>'forms_buttons_hover_background','hover_shadow'=>'forms_buttons_focus_box_shadow','hover_border'=>'forms_buttons_hover_border_color','transition'=>'forms_buttons_hover_transition_duration','animation'=>'forms_buttons_hover_animation']
            + ($account ? ['border_group'=>'forms_buttons_border'] : ['border'=>'forms_buttons_border']) + ['radius'=>'forms_buttons_border_radius','padding'=>'forms_buttons_padding']);
    }

    /** Alignment of a button inside its container (start, center, end or full width). */
    protected function button_align(string $key, string $container, string $button, array $cond = [], string $label = 'Alineación'): void {
        $var = '--obx-' . str_replace('_', '-', $key) . '-grow';
        $this->add_responsive_control($key, ['label'=>$label,'type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align() + ['justify'=>['title'=>'Justificado','icon'=>'eicon-text-align-justify']],
            'selectors_dictionary'=>['start'=>'flex-start; ' . $var . ': 0','center'=>'center; ' . $var . ': 0','end'=>'flex-end; ' . $var . ': 0','justify'=>'stretch; ' . $var . ': 1'],
            'selectors'=>[$container=>'display: flex; flex-wrap: wrap; justify-content: {{VALUE}};', $button=>'flex-grow: var(' . $var . ', 0); float: none;']] + ($cond ? ['condition'=>$cond] : []));
    }

    /** Normal and hover colours in tabs (link colours). */
    protected function color_tabs(string $id, string $normal, string $hover, string $selector, array $cond = [], string $property = 'color', string $label = 'Color'): void {
        $c = $cond ? ['condition'=>$cond] : [];
        $hovered = implode(', ', array_map(static fn($part) => trim($part) . ':hover', explode(',', $selector)));
        $this->start_controls_tabs($id, $c);
        $this->start_controls_tab($id . '_normal', ['label'=>'Normal'] + $c);
        $this->paint($normal, $label, $selector, $property, $c);
        $this->end_controls_tab();
        $this->start_controls_tab($id . '_hover', ['label'=>'Al pasar el ratón'] + $c);
        $this->paint($hover, $label, $hovered, $property, $c);
        $this->end_controls_tab();
        $this->end_controls_tabs();
    }

    /** Sticky right column of the two-column layout, with Pro's keys. */
    protected function sticky_column(string $layout, string $column): void {
        $this->add_control('sticky_right_column', ['label'=>'Columna derecha fija','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','condition'=>[$layout . '!'=>'one-column'],'render_type'=>'template']);
        $this->add_control('sticky_right_column_offset', ['label'=>'Distancia desde arriba (px)','type'=>Controls_Manager::NUMBER,'conditions'=>['relation'=>'and','terms'=>[['name'=>'sticky_right_column','operator'=>'!==','value'=>''],['name'=>$layout,'operator'=>'!==','value'=>'one-column']]],
            'selectors'=>[$column=>'--obx-sticky-offset: {{VALUE}}px;']]);
    }

    protected function title_align(string $key, string $selector, array $cond = []): void {
        $this->add_responsive_control($key, ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(),'selectors'=>[$selector=>'text-align: {{VALUE}};']] + ($cond ? ['condition'=>$cond] : []));
    }

    /** Elementor templates for the «query» controls of Pro (stored as the template ID). */
    protected static function templates(): array {
        static $options = null;
        if ($options !== null) { return $options; }
        $options = [];
        foreach (get_posts(['post_type'=>'elementor_library','post_status'=>'publish','posts_per_page'=>200,'orderby'=>'title','order'=>'ASC','meta_key'=>'_elementor_template_type','meta_value'=>['page','section','container'],'meta_compare'=>'IN']) as $post) { $options[(string) $post->ID] = $post->post_title; }
        return $options;
    }

    protected static function template_html($id): string {
        static $rendering = [];
        $id = absint($id);
        if (!$id || get_post_type($id) !== 'elementor_library' || get_post_status($id) !== 'publish' || isset($rendering[$id])) { return ''; }
        $rendering[$id] = true;
        $html = (string) \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($id, true);
        unset($rendering[$id]);
        return $html;
    }

    /* ---------- Rendering helpers ---------- */

    /** WooCommerce string => replacement, from the settings that differ from WooCommerce's text. */
    protected function texts(array $s): array {
        $map = [];
        foreach (static::TEXTS as $original => $key) {
            $value = trim((string) ($s[$key] ?? ''));
            if ($value !== '' && $value !== __($original, 'woocommerce')) { $map[$original] = $value; } // phpcs:ignore WordPress.WP.I18n -- WooCommerce's own strings.
        }
        return $map;
    }

    /** Replaces WooCommerce's strings until the returned function is called. */
    public static function replace_texts(array $map): callable {
        if (!$map) { return static function() {}; }
        $filter = static function($translation, $text, $domain) use ($map) { return $domain === 'woocommerce' && isset($map[$text]) ? $map[$text] : $translation; };
        add_filter('gettext', $filter, 20, 3);
        return static function() use ($filter) { remove_filter('gettext', $filter, 20); };
    }

    /** Runs $render with WooCommerce's strings replaced. */
    protected static function with_texts(array $map, callable $render) {
        $undo = self::replace_texts($map);
        try { return $render(); } finally { $undo(); }
    }

    /** Keeps the texts for WooCommerce's AJAX refreshes of this page. */
    protected static function remember(array $data): void {
        if (static::SESSION === '' || !function_exists('WC') || !WC()->session) { return; }
        if (WC()->session->get(static::SESSION) !== $data) { WC()->session->set(static::SESSION, $data); }
    }

    /** Texts kept by remember() for AJAX requests. */
    public static function remembered(): array {
        if (static::SESSION === '' || !function_exists('WC') || !WC()->session) { return []; }
        $data = WC()->session->get(static::SESSION);
        return is_array($data) ? $data : [];
    }

    /** Hover animations as selector => class, applied by widgets.js (also after WooCommerce's AJAX refreshes). */
    protected function animations(array $map): string {
        $s = $this->get_settings_for_display();
        $out = [];
        foreach ($map as $key => $selector) { if (!empty($s[$key])) { $out[$selector] = 'elementor-animation-' . sanitize_html_class((string) $s[$key]); } }
        return $out ? ' data-obx-animations="' . esc_attr((string) wp_json_encode($out)) . '"' : '';
    }

    protected static function ensure_cart(): bool {
        if (!function_exists('WC')) { return false; }
        if (!WC()->cart && function_exists('wc_load_cart')) { wc_load_cart(); }
        return (bool) WC()->cart;
    }
}

final class Woo_Cart extends Woo_Page_Widget {
    public const PRO = 'woocommerce-cart';
    protected const LEGACY = ['woo_color'=>'order_summary_color','woo_hover'=>'links_hover_color','woo_typography'=>'order_summary_items_typography'];
    protected const TEXTS = ['Update cart'=>'update_cart_button_text','Apply coupon'=>'apply_coupon_button_text','Cart totals'=>'totals_section_title','Update'=>'update_shipping_button_text','Proceed to checkout'=>'checkout_button_text'];
    protected const SESSION = 'obx_cart_texts';
    public function get_title(): string { return 'Carrito'; }
    public function get_icon(): string { return 'eicon-woo-cart'; }
    public function get_keywords(): array { return ['woocommerce', 'carrito', 'cart']; }

    protected function register_controls(): void {
        $w = '{{WRAPPER}}';
        $this->section('section_content', 'Diseño', false);
        $this->add_control('cart_layout', ['label'=>'Disposición','type'=>Controls_Manager::SELECT,'default'=>'two-column','options'=>['two-column'=>'Dos columnas','one-column'=>'Una columna'],'render_type'=>'template']);
        $this->sticky_column('cart_layout', $w . ' .obx-woo-cart');
        $this->end_controls_section();

        $this->section('section_order_summary', 'Resumen del pedido', false);
        $this->add_control('update_cart_button_text', ['label'=>'Texto del botón «Actualizar carrito»','type'=>Controls_Manager::TEXT,'default'=>__('Update cart', 'woocommerce'),'dynamic'=>['active'=>true],'condition'=>['update_cart_automatically'=>'']]);
        $this->button_align('update_cart_button_alignment', $w . ' .woocommerce-cart-form td.actions', $w . ' .woocommerce-cart-form td.actions > .button', ['update_cart_automatically'=>'']);
        $this->end_controls_section();

        $this->section('section_coupon', 'Cupón', false);
        $this->add_control('section_coupon_display', ['label'=>'Mostrar','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes','render_type'=>'template']);
        $this->add_control('apply_coupon_button_text', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>__('Apply coupon', 'woocommerce'),'dynamic'=>['active'=>true],'condition'=>['section_coupon_display'=>'yes']]);
        $this->button_align('apply_coupon_button_alignment', $w . ' .coupon', $w . ' .coupon .button', ['section_coupon_display'=>'yes']);
        $this->end_controls_section();

        $this->section('section_totals', 'Totales', false);
        $this->add_control('totals_section_title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>__('Cart totals', 'woocommerce'),'dynamic'=>['active'=>true]]);
        $this->title_align('totals_section_title_alignment', $w . ' .cart_totals > h2');
        $this->add_control('update_shipping_button_text', ['label'=>'Texto del botón «Actualizar» del envío','type'=>Controls_Manager::TEXT,'default'=>__('Update', 'woocommerce'),'dynamic'=>['active'=>true],'separator'=>'before']);
        $this->button_align('update_shipping_button_alignment', $w . ' .shipping-calculator-form p:has(> .button)', $w . ' .shipping-calculator-form .button');
        $this->add_control('checkout_button_text', ['label'=>'Texto del botón «Finalizar compra»','type'=>Controls_Manager::TEXT,'default'=>__('Proceed to checkout', 'woocommerce'),'dynamic'=>['active'=>true],'separator'=>'before']);
        $this->button_align('checkout_button_alignment', $w . ' .wc-proceed-to-checkout', $w . ' .wc-proceed-to-checkout .checkout-button');
        $this->end_controls_section();

        $this->section('section_additional_options', 'Opciones adicionales', false);
        $this->add_control('update_cart_automatically', ['label'=>'Actualizar el carrito automáticamente','description'=>'Cambiar una cantidad actualiza el carrito sin pulsar el botón.','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','render_type'=>'template']);
        $this->add_control('additional_template_switch', ['label'=>'Plantilla adicional','type'=>Controls_Manager::SWITCHER,'return_value'=>'active','separator'=>'before']);
        $this->add_control('additional_template_select', ['label'=>'Plantilla','description'=>'Se muestra bajo el carrito.','type'=>Controls_Manager::SELECT2,'options'=>self::templates(),'label_block'=>true,'condition'=>['additional_template_switch'=>'active']]);
        $this->end_controls_section();

        $sections = $w . ' .woocommerce-cart-form, ' . $w . ' .cart_totals';
        $this->section('section_cart_tabs_style', 'Secciones');
        $this->box($sections, ['bg'=>'sections_background_color','shadow'=>'section_normal_box_shadow','type'=>'sections_border_type','width'=>'sections_border_width','color'=>'sections_border_color','radius'=>'sections_border_radius','padding'=>'sections_padding','margin'=>'sections_margin']);
        $this->end_controls_section();

        $this->section('section_cart_tabs_typography', 'Tipografía');
        $this->text('Títulos', 'sections_title_color', 'sections_titles_typography', $w . ' .cart_totals > h2, ' . $w . ' .cross-sells > h2', [], 'sections_titles_text_shadow', 'sections_title_spacing');
        $this->text('Descripciones', 'sections_descriptions_color', 'sections_descriptions_typography', $w . ' .woocommerce-shipping-destination, ' . $w . ' .woocommerce-shipping-contents', [], null, 'sections_descriptions_spacing');
        $this->add_control('heading_links', ['label'=>'Enlaces','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color_tabs('links_colors', 'links_normal_color', 'links_hover_color', $w . ' .cart_totals a:not(.button)');
        $this->text('Botones de opción', 'sections_radio_buttons_color', 'sections_radio_buttons_typography', $w . ' .woocommerce-shipping-methods label');
        $this->end_controls_section();

        $this->section('section_cart_tabs_forms', 'Formularios');
        $this->form_fields($w . ' .shipping-calculator-form p.form-row', $w . ' .coupon .input-text, ' . $w . ' .shipping-calculator-form .input-text, ' . $w . ' .shipping-calculator-form select, ' . $w . ' .shipping-calculator-form .select2-selection');
        $this->form_buttons($w . ' .woocommerce-cart-form .button, ' . $w . ' .shipping-calculator-form .button');
        $this->end_controls_section();

        $table = $w . ' .shop_table.cart';
        $this->section('tabs_order_summary', 'Resumen del pedido');
        $this->gap('order_summary_rows_gap', 'Separación entre filas', [$table . ' td'=>'padding-top: calc({{SIZE}}{{UNIT}} / 2); padding-bottom: calc({{SIZE}}{{UNIT}} / 2);']);
        $this->text('Encabezados', 'order_summary_title_color', 'order_summary_title_typography', $table . ' thead th', [], 'order_summary_title_text_shadow', 'order_summary_title_spacing', 'padding-bottom');
        $this->text('Productos', 'order_summary_color', 'order_summary_items_typography', $table . ' td');
        $this->text('Variaciones', 'order_summary_variations_color', 'order_summary_variations_typography', $table . ' .variation, ' . $table . ' .variation p');
        $this->add_control('heading_product_link', ['label'=>'Enlaces de los productos','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color_tabs('order_summary', 'product_link_normal_color', 'product_link_hover_color', $table . ' td.product-name a');
        $this->paint('order_summary_items_divider_color', 'Color del separador', $table . ' td', 'border-top-color', ['separator'=>'before']);
        $this->gap('order_summary_items_divider_weight', 'Grosor del separador', [$table . ' td'=>'border-top-style: solid; border-top-width: {{SIZE}}{{UNIT}};']);
        $this->paint('order_summary_quantity_border_color', 'Color del borde de la cantidad', $table . ' .qty', 'border-color', ['separator'=>'before']);
        $this->gap('order_summary_quantity_border_weight', 'Grosor del borde de la cantidad', [$table . ' .qty'=>'border-style: solid; border-width: {{SIZE}}{{UNIT}};']);
        $this->add_control('heading_remove_icon', ['label'=>'Icono de quitar','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color_tabs('order_summary_remove_icon', 'order_summary_remove_icon_normal_color', 'order_summary_remove_icon_hover_color', $table . ' a.remove', [], 'background-color: transparent; color: {{VALUE}} !important; --obx-remove');
        $this->end_controls_section();

        $totals = $w . ' .cart_totals table';
        $this->section('section_cart_totals', 'Totales');
        $this->gap('totals_rows_gap', 'Separación entre filas', [$totals . ' th, ' . $totals . ' td'=>'padding-top: calc({{SIZE}}{{UNIT}} / 2); padding-bottom: calc({{SIZE}}{{UNIT}} / 2);']);
        $this->paint('totals_color', 'Color', $totals);
        $this->typo('totals_typography', $totals . ' th, ' . $totals . ' td');
        $this->paint('totals_divider_color', 'Color del separador', $totals . ' th, ' . $totals . ' td', 'border-top-color', ['separator'=>'before']);
        $this->gap('totals_divider_weight', 'Grosor del separador', [$totals . ' th, ' . $totals . ' td'=>'border-top-style: solid; border-top-width: {{SIZE}}{{UNIT}};']);
        $this->end_controls_section();

        $this->section('section_cart_tabs_checkout_button', 'Botón «Finalizar compra»');
        $this->button_set($w . ' .wc-proceed-to-checkout .checkout-button', ['typo'=>'checkout_button_typography','tshadow'=>'checkout_button_text_shadow','tabs'=>'checkout_button_styles','text'=>'checkout_button_normal_text_color','bg'=>'checkout_button_normal_background','shadow'=>'checkout_button_normal_box_shadow','hover_text'=>'checkout_button_hover_text_color','hover_bg'=>'checkout_button_hover_background','hover_shadow'=>'checkout_button_hover_box_shadow','hover_border'=>'checkout_button_hover_border_color','transition'=>'checkout_button_hover_transition_duration','animation'=>'checkout_button_hover_animation','border_group'=>'checkout_button_border','radius'=>'checkout_button_border_radius','padding'=>'checkout_button_padding']);
        $this->end_controls_section();

        $this->section('section_cart_tabs_customize', 'Personalizar');
        $this->add_control('section_cart_show_customize_elements', ['label'=>'Elige las secciones que quieres personalizar','type'=>Controls_Manager::SELECT2,'label_block'=>true,'multiple'=>true,'options'=>['customize_order_summary'=>'Resumen del pedido','customize_coupon'=>'Cupón','customize_totals'=>'Totales']]);
        $this->end_controls_section();
        $only = static fn(string $part): array => ['section_cart_show_customize_elements'=>'customize_' . $part];

        $this->section('section_cart_tabs_customize_order_summary', 'Personalizar: resumen del pedido', true, ['condition'=>$only('order_summary')]);
        $this->box($w . ' .woocommerce-cart-form', ['bg'=>'order_summary_section_background_color','shadow'=>'order_summary_section_normal_box_shadow','type'=>'order_summary_section_border_type','width'=>'order_summary_section_border_width','color'=>'order_summary_section_border_color','radius'=>'order_summary_section_border_radius','padding'=>'order_summary_section_padding','margin'=>'order_summary_section_margin'], $only('order_summary'));
        $this->end_controls_section();

        $this->section('section_cart_tabs_customize_totals', 'Personalizar: totales', true, ['condition'=>$only('totals')]);
        $this->box($w . ' .cart_totals', ['bg'=>'sections_totals_background_color','shadow'=>'totals_section_normal_box_shadow','type'=>'totals_section_border_type','width'=>'totals_section_border_width','color'=>'totals_section_border_color','radius'=>'totals_section_border_radius','padding'=>'checkout_sections_padding','margin'=>'totals_section_margin'], $only('totals'));
        $this->text('Títulos', 'totals_section_titles_color', 'totals_section_titles_typography', $w . ' .cart_totals > h2', $only('totals'), 'totals_section_titles_text_shadow');
        $this->text('Contenido', 'totals_section_content_color', 'totals_section_content_typography', $w . ' .cart_totals table', $only('totals'));
        $this->color_tabs('totals_section_links_colors', 'totals_section_links_normal_color', 'totals_section_links_hover_color', $w . ' .cart_totals a:not(.button)', $only('totals'), 'color', 'Color de los enlaces');
        $this->end_controls_section();

        $this->section('section_cart_tabs_customize_coupon', 'Personalizar: cupón', true, ['condition'=>$only('coupon')]);
        $this->box($w . ' .coupon', ['bg'=>'customize_coupon_background_color','shadow'=>'customize_coupon_section_normal_box_shadow','type'=>'customize_coupon_section_border_type','width'=>'customize_coupon_section_border_width','color'=>'customize_coupon_section_border_color','radius'=>'customize_coupon_section_border_radius','padding'=>'customize_coupon_section_padding','margin'=>'customize_coupon_section_margin'], $only('coupon'));
        $this->end_controls_section();
    }

    protected function render(): void {
        if (!self::ensure_cart()) { return; }
        $s = $this->get_settings_for_display();
        $texts = $this->texts($s);
        self::remember($texts);
        $classes = ['obx-woo-cart', 'obx-woo-cart--' . (($s['cart_layout'] ?? 'two-column') === 'one-column' ? 'one-column' : 'two-column')];
        if (($s['section_coupon_display'] ?? 'yes') !== 'yes') { $classes[] = 'obx-woo-cart--no-coupon'; }
        if (($s['update_cart_automatically'] ?? '') === 'yes') { $classes[] = 'obx-woo-cart--auto-update'; }
        if (($s['sticky_right_column'] ?? '') === 'yes') { $classes[] = 'obx-woo-sticky'; }
        $coupons = ($s['section_coupon_display'] ?? 'yes') === 'yes';
        if (!$coupons) { add_filter('woocommerce_coupons_enabled', '__return_false', 99); }
        $html = self::with_texts($texts, static fn() => (string) do_shortcode('[woocommerce_cart]'));
        if (!$coupons) { remove_filter('woocommerce_coupons_enabled', '__return_false', 99); }
        echo '<div class="' . esc_attr(implode(' ', $classes)) . '"' . $this->animations(['forms_buttons_hover_animation'=>'.woocommerce-cart-form .button, .shipping-calculator-form .button', 'checkout_button_hover_animation'=>'.wc-proceed-to-checkout .checkout-button']) . '>';
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce shortcode output.
        if (($s['additional_template_switch'] ?? '') === 'active') { echo '<div class="obx-woo-cart__additional">' . self::template_html($s['additional_template_select'] ?? 0) . '</div>'; } // phpcs:ignore WordPress.Security.EscapeOutput -- Elementor output.
        echo '</div>';
    }
}

final class Woo_Checkout extends Woo_Page_Widget {
    public const PRO = 'woocommerce-checkout-page';
    protected const LEGACY = ['woo_color'=>'order_summary_items_color','woo_hover'=>'links_hover_color','woo_typography'=>'order_summary_items_typography'];
    protected const TEXTS = ['Billing details'=>'billing_details_section_title','Billing &amp; Shipping'=>'billing_details_section_title','Your order'=>'order_summary_section_title','terms and conditions'=>'terms_conditions_link_text'];
    protected const SESSION = 'obx_checkout_texts';
    /** Address fields of the billing form, as Pro lists them («locale» ones change with the country). */
    private const BILLING = ['billing_first_name'=>['First name',''],'billing_last_name'=>['Last name',''],'billing_company'=>['Company name',''],'billing_country'=>['Country / Region','locale'],'billing_address_1'=>['Street address','locale'],'billing_postcode'=>['Postcode / ZIP','locale'],'billing_city'=>['Town / City','locale'],'billing_state'=>['State / County','locale'],'billing_phone'=>['Phone',''],'billing_email'=>['Email address','']];
    public function get_title(): string { return 'Finalizar compra'; }
    public function get_icon(): string { return 'eicon-checkout'; }
    public function get_keywords(): array { return ['woocommerce', 'checkout', 'pago', 'finalizar compra', 'pedido']; }

    protected function register_controls(): void {
        $w = '{{WRAPPER}}';
        $this->section('section_content', 'Diseño', false);
        $this->add_control('checkout_layout', ['label'=>'Disposición','type'=>Controls_Manager::SELECT,'default'=>'two-column','options'=>['two-column'=>'Dos columnas','one-column'=>'Una columna'],'render_type'=>'template']);
        $this->sticky_column('checkout_layout', $w . ' .obx-woo-checkout');
        $this->end_controls_section();

        $this->section('billing_details_section', 'Datos de facturación', false);
        $this->add_control('billing_details_section_title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>__('Billing details', 'woocommerce'),'dynamic'=>['active'=>true]]);
        $this->title_align('billing_details_alignment', $w . ' .woocommerce-billing-fields > h3');
        $fields = new \Elementor\Repeater();
        $own = ['condition'=>['repeater_state'=>'']];
        $fields->start_controls_tabs('tabs', $own);
        $fields->start_controls_tab('content_tab', ['label'=>'Contenido'] + $own);
        $fields->add_control('label', ['label'=>'Etiqueta','type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true]] + $own);
        $fields->add_control('placeholder', ['label'=>'Marcador de posición','type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true]] + $own);
        $fields->end_controls_tab();
        $fields->start_controls_tab('advanced_tab', ['label'=>'Avanzado'] + $own);
        $fields->add_control('default', ['label'=>'Valor por defecto','type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true]] + $own);
        $fields->end_controls_tab();
        $fields->end_controls_tabs();
        $fields->add_control('repeater_state', ['type'=>Controls_Manager::HIDDEN]);
        $fields->add_control('from_billing_notice', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Este campo toma el valor de los datos de facturación.','content_classes'=>'elementor-descriptor','condition'=>['repeater_state'=>'from_billing']]);
        $fields->add_control('locale_notice', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'WooCommerce adapta este campo al país del cliente.','content_classes'=>'elementor-descriptor','condition'=>['repeater_state'=>'locale']]);
        $default = [];
        foreach (self::BILLING as $key => [$label, $state]) {
            $text = __($label, 'woocommerce'); // phpcs:ignore WordPress.WP.I18n -- WooCommerce's own strings.
            $default[] = ['field_key'=>$key,'field_label'=>$text,'label'=>$text,'placeholder'=>$text,'repeater_state'=>$state];
        }
        $this->add_control('billing_details_form_fields', ['label'=>'Campos del formulario','type'=>Controls_Manager::REPEATER,'fields'=>$fields->get_controls(),'default'=>$default,'title_field'=>'{{{ field_label }}}','item_actions'=>['add'=>false,'duplicate'=>false,'remove'=>false,'sort'=>false],'separator'=>'before']);
        $this->end_controls_section();

        $this->section('additional_information_section', 'Información adicional', false);
        $this->add_control('additional_information_active', ['label'=>'Mostrar','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes','render_type'=>'template']);
        $notes = new \Elementor\Repeater();
        $notes->start_controls_tabs('additional_information_form_fields_tabs');
        $notes->start_controls_tab('additional_information_form_fields_content_tab', ['label'=>'Contenido']);
        $notes->add_control('label', ['label'=>'Etiqueta','type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true]]);
        $notes->add_control('placeholder', ['label'=>'Marcador de posición','type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true]]);
        $notes->end_controls_tab();
        $notes->start_controls_tab('additional_information_form_fields_advanced_tab', ['label'=>'Avanzado']);
        $notes->add_control('default', ['label'=>'Valor por defecto','type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true]]);
        $notes->end_controls_tab();
        $notes->end_controls_tabs();
        $this->add_control('additional_information_form_fields', ['label'=>'Campos del formulario','type'=>Controls_Manager::REPEATER,'fields'=>$notes->get_controls(),
            'default'=>[['field_key'=>'order_comments','field_label'=>__('Order notes', 'woocommerce'),'label'=>__('Order notes', 'woocommerce'),'placeholder'=>__('Notes about your order, e.g. special notes for delivery.', 'woocommerce')]],
            'title_field'=>'{{{ field_label }}}','item_actions'=>['add'=>false,'duplicate'=>false,'remove'=>false,'sort'=>false],'condition'=>['additional_information_active!'=>'']]);
        $this->end_controls_section();

        $this->section('order_summary_section', 'Tu pedido', false);
        $this->add_control('order_summary_section_title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>__('Your order', 'woocommerce'),'dynamic'=>['active'=>true]]);
        $this->title_align('order_summary_alignment', $w . ' #order_review_heading');
        $this->end_controls_section();

        $on = ['coupon_section_display'=>'yes'];
        $this->section('coupon_section', 'Cupón', false);
        $this->add_control('coupon_section_display', ['label'=>'Mostrar','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes','render_type'=>'template']);
        $this->add_control('coupon_section_title_text', ['label'=>'Texto','type'=>Controls_Manager::TEXT,'default'=>__('Have a coupon?', 'woocommerce'),'dynamic'=>['active'=>true],'condition'=>$on]);
        $this->add_control('coupon_section_title_link_text', ['label'=>'Texto del enlace','type'=>Controls_Manager::TEXT,'default'=>__('Click here to enter your code', 'woocommerce'),'dynamic'=>['active'=>true],'condition'=>$on]);
        $this->title_align('coupon_alignment', $w . ' .woocommerce-form-coupon-toggle .woocommerce-info', $on);
        $this->button_align('coupon_button_alignment', $w . ' form.checkout_coupon .form-row-last', $w . ' form.checkout_coupon .button', $on, 'Alineación del botón');
        $this->end_controls_section();

        $this->section('payment_section', 'Pago', false);
        $this->add_control('terms_conditions_message_text', ['label'=>'Texto de los términos','type'=>Controls_Manager::TEXT,'default'=>trim(str_replace('%s', '', __('I have read and agree to the website %s', 'woocommerce'))),'dynamic'=>['active'=>true]]);
        $this->add_control('terms_conditions_link_text', ['label'=>'Texto del enlace','type'=>Controls_Manager::TEXT,'default'=>__('terms and conditions', 'woocommerce'),'dynamic'=>['active'=>true]]);
        $this->button_align('purchase_button_alignment', $w . ' .place-order', $w . ' #place_order', [], 'Alineación del botón de compra');
        $this->end_controls_section();

        $login = $w . ' .woocommerce-form-login-toggle .woocommerce-info, ' . $w . ' form.woocommerce-form-login';
        $coupon = $w . ' .woocommerce-form-coupon-toggle .woocommerce-info, ' . $w . ' form.checkout_coupon';
        $billing = $w . ' .woocommerce-billing-fields, ' . $w . ' .woocommerce-shipping-fields';
        $additional = $w . ' .woocommerce-additional-fields';
        $summary = $w . ' .woocommerce-checkout-review-order-table';
        $payment = $w . ' #payment';
        $this->section('section_checkout_tabs_style', 'Secciones');
        $this->box(implode(', ', [$login, $coupon, $billing, $additional, $summary, $payment]), ['bg'=>'sections_background_color','shadow'=>'section_normal_box_shadow','type'=>'sections_border_type','width'=>'sections_border_width','color'=>'sections_border_color','radius'=>'sections_border_radius','padding'=>'sections_padding','margin'=>'sections_margin']);
        $this->end_controls_section();

        $toggles = $w . ' .woocommerce-form-login-toggle .woocommerce-info, ' . $w . ' .woocommerce-form-coupon-toggle .woocommerce-info';
        $this->section('section_checkout_tabs_typography', 'Tipografía');
        $this->text('Títulos', 'sections_title_color', 'sections_titles_typography', $w . ' .woocommerce-billing-fields > h3, ' . $w . ' .woocommerce-shipping-fields > h3, ' . $w . ' .woocommerce-additional-fields > h3, ' . $w . ' #order_review_heading', [], 'sections_titles_text_shadow', 'sections_title_spacing');
        $this->text('Títulos secundarios', 'sections_secondary_title_color', 'sections_secondary_titles_typography', $toggles, [], null, 'sections_secondary_title_spacing');
        $this->text('Descripciones', 'sections_descriptions_color', 'sections_descriptions_typography', $w . ' form.woocommerce-form-login > p:first-child, ' . $w . ' form.checkout_coupon > p:first-child', [], null, 'sections_descriptions_spacing');
        $this->text('Mensajes', 'sections_messages_color', 'sections_messages_typography', $w . ' .woocommerce-privacy-policy-text, ' . $w . ' #payment .woocommerce-info');
        $this->text('Casillas', 'sections_checkboxes_color', 'sections_checkboxes_typography', $w . ' .woocommerce-form__label-for-checkbox');
        $this->text('Botones de opción', 'sections_radio_buttons_color', 'sections_radio_buttons_typography', $w . ' .wc_payment_method > label, ' . $w . ' #shipping_method label');
        $this->add_control('heading_links', ['label'=>'Enlaces','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color_tabs('links_colors', 'links_normal_color', 'links_hover_color', $w . ' .woocommerce a:not(.button)');
        $this->end_controls_section();

        $this->section('section_checkout_tabs_forms', 'Formularios');
        $this->form_fields($w . ' .woocommerce .form-row', $w . ' .woocommerce .input-text, ' . $w . ' .woocommerce select, ' . $w . ' .woocommerce .select2-container .select2-selection', $w . ' .woocommerce .form-row > label', $w . ' .woocommerce');
        $this->form_buttons($w . ' form.woocommerce-form-login .button, ' . $w . ' form.checkout_coupon .button');
        $this->end_controls_section();

        $this->section('section_checkout_tabs_order_summary', 'Resumen del pedido');
        $this->gap('order_summary_rows_gap', 'Separación entre filas', [$summary . ' th, ' . $summary . ' td'=>'padding-top: calc({{SIZE}}{{UNIT}} / 2); padding-bottom: calc({{SIZE}}{{UNIT}} / 2);']);
        $this->text('Productos', 'order_summary_items_color', 'order_summary_items_typography', $summary . ' thead th, ' . $summary . ' tbody td');
        $this->text('Variaciones', 'order_summary_variations_color', 'order_summary_variations_typography', $summary . ' .variation, ' . $summary . ' .variation p');
        $this->paint('order_summary_items_divider_color', 'Color del separador', $summary . ' tbody td', 'border-top-color');
        $this->gap('order_summary_items_divider_weight', 'Grosor del separador', [$summary . ' tbody td'=>'border-top-style: solid; border-top-width: {{SIZE}}{{UNIT}};']);
        $this->text('Totales', 'order_summary_totals_color', 'order_summary_totals_typography', $summary . ' tfoot th, ' . $summary . ' tfoot td');
        $this->paint('order_summary_totals_divider_color', 'Color del separador', $summary . ' tfoot th, ' . $summary . ' tfoot td', 'border-top-color');
        $this->gap('order_summary_totals_divider_weight', 'Grosor del separador', [$summary . ' tfoot th, ' . $summary . ' tfoot td'=>'border-top-style: solid; border-top-width: {{SIZE}}{{UNIT}};']);
        $this->end_controls_section();

        $this->section('section_checkout_tabs_purchase_button', 'Botón de compra');
        $this->button_set($w . ' #place_order', ['typo'=>'purchase_button_typography','tshadow'=>'purchase_button_text_shadow','tabs'=>'purchase_button_styles','text'=>'purchase_button_normal_text_color','bg'=>'purchase_button_normal_background','shadow'=>'purchase_button_normal_box_shadow','hover_text'=>'purchase_button_hover_text_color','hover_bg'=>'purchase_button_hover_background','hover_shadow'=>'purchase_button_hover_box_shadow','hover_border'=>'purchase_button_hover_border_color','transition'=>'purchase_button_hover_transition_duration','animation'=>'purchase_button_hover_animation','border_group'=>'purchase_button_border','radius'=>'purchase_button_border_radius','padding'=>'purchase_button_padding']);
        $this->end_controls_section();

        $parts = ['customize_billing_details'=>'Datos de facturación','customize_additional_info'=>'Información adicional','customize_order_summary'=>'Resumen del pedido','customize_coupon'=>'Cupón','customize_payment'=>'Pago'];
        if (get_option('woocommerce_enable_checkout_login_reminder', 'no') === 'yes') { $parts = ['customize_returning_customer'=>'Cliente habitual'] + $parts; }
        $this->section('section_checkout_tabs_customize', 'Personalizar');
        $this->add_control('section_checkout_show_customize_elements', ['label'=>'Elige las secciones que quieres personalizar','type'=>Controls_Manager::SELECT2,'label_block'=>true,'multiple'=>true,'options'=>$parts]);
        $this->end_controls_section();
        $only = static fn(string $part): array => ['section_checkout_show_customize_elements'=>'customize_' . $part];

        $c = $only('returning_customer');
        $this->section('section_checkout_tabs_customize_returning_customer', 'Personalizar: cliente habitual', true, ['condition'=>$c]);
        $this->box($login, ['bg'=>'customize_returning_customer_background_color','shadow'=>'returning_customers_section_normal_box_shadow','type'=>'returning_customers_border_type','width'=>'returning_customers_border_width','color'=>'returning_customers_border_color','radius'=>'returning_customers_border_radius','padding'=>'returning_customers_padding','margin'=>'returning_customers_margin'], $c);
        $this->text('Título secundario', 'returning_customers_secondary_title_color', 'returning_customers_content_typography', $w . ' .woocommerce-form-login-toggle .woocommerce-info', $c);
        $this->text('Descripción', 'returning_customers_description_color', 'returning_customers_description_typography', $w . ' form.woocommerce-form-login > p:first-child', $c);
        $this->text('Casillas', 'returning_customers_checkboxes_color', 'returning_customers_checkboxes_typography', $w . ' form.woocommerce-form-login .woocommerce-form__label-for-checkbox', $c);
        $this->color_tabs('returning_customers_links', 'returning_customers_normal_links_color', 'returning_customers_hover_links_color', $w . ' .woocommerce-form-login-toggle a, ' . $w . ' form.woocommerce-form-login a', $c, 'color', 'Color de los enlaces');
        $this->end_controls_section();

        $c = $only('billing_details');
        $this->section('section_checkout_tabs_customize_billing_details', 'Personalizar: datos de facturación', true, ['condition'=>$c]);
        $this->box($billing, ['bg'=>'customize_billing_details_background_color','shadow'=>'billing_details_section_normal_box_shadow','type'=>'billing_details_border_type','width'=>'billing_details_border_width','color'=>'billing_details_border_color','radius'=>'billing_details_border_radius','padding'=>'billing_details_padding','margin'=>'billing_details_margin'], $c);
        $this->text('Títulos', 'billing_details_titles_color', 'billing_details_titles_typography', $w . ' .woocommerce-billing-fields > h3, ' . $w . ' .woocommerce-shipping-fields > h3', $c, 'billing_details_titles_text_shadow');
        $this->text('Casillas', 'billing_details_checkboxes_color', 'billing_details_checkboxes_typography', $w . ' .woocommerce-billing-fields .woocommerce-form__label-for-checkbox, ' . $w . ' .woocommerce-account-fields .woocommerce-form__label-for-checkbox, ' . $w . ' .woocommerce-shipping-fields .woocommerce-form__label-for-checkbox', $c);
        $this->end_controls_section();

        $c = $only('additional_info');
        $this->section('section_checkout_tabs_customize_additional_info', 'Personalizar: información adicional', true, ['condition'=>$c]);
        $this->box($additional, ['bg'=>'customize_additional_information_background_color','shadow'=>'additional_information_section_normal_box_shadow','type'=>'additional_information_border_type','width'=>'additional_information_border_width','color'=>'additional_information_border_color','radius'=>'additional_information_border_radius','padding'=>'additional_information_padding','margin'=>'additional_information_margin'], $c);
        $this->text('Títulos', 'additional_information_titles_color', 'additional_information_titles_typography', $additional . ' > h3', $c, 'additional_information_titles_text_shadow');
        $this->end_controls_section();

        $c = $only('coupon');
        $this->section('section_checkout_tabs_customize_coupon', 'Personalizar: cupón', true, ['condition'=>$c]);
        $this->box($coupon, ['bg'=>'customize_coupon_background_color','shadow'=>'coupon_section_normal_box_shadow','type'=>'coupon_border_type','width'=>'coupon_border_width','color'=>'coupon_border_color','radius'=>'coupon_border_radius','padding'=>'coupon_padding','margin'=>'coupon_margin'], $c);
        $this->text('Título secundario', 'coupon_secondary_title_color', 'coupon_content_typography', $w . ' .woocommerce-form-coupon-toggle .woocommerce-info', $c);
        $this->color_tabs('coupon_links', 'coupon_normal_links_color', 'coupon_hover_links_color', $w . ' .woocommerce-form-coupon-toggle a', $c, 'color', 'Color de los enlaces');
        $this->end_controls_section();

        $c = $only('order_summary');
        $this->section('section_checkout_tabs_customize_order_summary', 'Personalizar: resumen del pedido', true, ['condition'=>$c]);
        $this->box($summary, ['bg'=>'customize_order_summary_background_color','shadow'=>'order_summary_section_normal_box_shadow','type'=>'order_summary_border_type','width'=>'order_summary_border_width','color'=>'order_summary_border_color','radius'=>'order_summary_border_radius','padding'=>'order_summary_padding','margin'=>'order_summary_margin'], $c);
        $this->text('Títulos', 'order_summary_titles_color', 'order_summary_titles_typography', $w . ' #order_review_heading', $c, 'order_summary_titles_text_shadow');
        $this->text('Descripciones', 'order_summary_descriptions_color', 'order_summary_descriptions_typography', $summary . ' .woocommerce-shipping-destination, ' . $summary . ' .woocommerce-shipping-contents', $c);
        $this->text('Botones de opción', 'order_summary_radios_color', 'order_summary_radio_typography', $summary . ' #shipping_method label', $c);
        $this->end_controls_section();

        $c = $only('payment');
        $this->section('section_checkout_tabs_customize_payment', 'Personalizar: pago', true, ['condition'=>$c]);
        $this->box($payment, ['bg'=>'customize_payment_background_color','shadow'=>'payment_section_normal_box_shadow','type'=>'payment_border_type','width'=>'payment_border_width','color'=>'payment_border_color','radius'=>'payment_border_radius','padding'=>'payment_padding','margin'=>'payment_margin'], $c);
        $this->add_control('heading_payment_info_box', ['label'=>'Cuadro de información','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>$c]);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'payment_info_box_title_background','types'=>['classic','gradient'],'selector'=>$payment . ' .payment_box','condition'=>$c]);
        $this->text('Descripción', 'payment_description_color', 'payment_description_typography', $payment . ' .payment_box, ' . $payment . ' .payment_box p', $c);
        $this->text('Mensajes', 'payment_messages_color', 'payment_messages_typography', $payment . ' .woocommerce-privacy-policy-text, ' . $payment . ' .woocommerce-info', $c);
        $this->text('Casillas', 'payment_checkboxes_color', 'payment_checkboxes_typography', $payment . ' .woocommerce-form__label-for-checkbox', $c);
        $this->text('Botones de opción', 'payment_radio_color', 'payment_radio_typography', $payment . ' .wc_payment_method > label', $c);
        $this->color_tabs('payment_colors', 'payment_normal_color', 'payment_hover_color', $payment . ' a:not(.button)', $c, 'color', 'Color de los enlaces');
        $this->end_controls_section();
    }

    /** Texts and field settings that also apply to WooCommerce's AJAX refreshes (order review, payment). */
    private function checkout_data(array $s): array {
        $fields = [];
        foreach ((array) ($s['billing_details_form_fields'] ?? []) as $row) {
            $key = (string) ($row['field_key'] ?? '');
            if ($key === '' || ($row['repeater_state'] ?? '') !== '') { continue; }
            $fields['billing'][$key] = array_filter(['label'=>(string) ($row['label'] ?? ''),'placeholder'=>(string) ($row['placeholder'] ?? ''),'default'=>(string) ($row['default'] ?? '')], static fn($v) => $v !== '');
        }
        foreach ((array) ($s['additional_information_form_fields'] ?? []) as $row) {
            $key = (string) ($row['field_key'] ?? 'order_comments');
            $fields['order'][$key] = array_filter(['label'=>(string) ($row['label'] ?? ''),'placeholder'=>(string) ($row['placeholder'] ?? ''),'default'=>(string) ($row['default'] ?? '')], static fn($v) => $v !== '');
        }
        $terms = trim((string) ($s['terms_conditions_message_text'] ?? ''));
        return ['texts'=>$this->texts($s), 'fields'=>$fields, 'terms'=>$terms, 'notes'=>($s['additional_information_active'] ?? 'yes') === 'yes',
            'coupon'=>[trim((string) ($s['coupon_section_title_text'] ?? '')), trim((string) ($s['coupon_section_title_link_text'] ?? ''))]];
    }

    /** Applies checkout_data() through WooCommerce's filters; returns a function that removes them. */
    public static function apply(array $data): callable {
        $filters = [];
        $add = static function(string $hook, callable $fn, int $priority = 20, int $args = 1) use (&$filters) { add_filter($hook, $fn, $priority, $args); $filters[] = [$hook, $fn, $priority]; };
        if (!empty($data['fields'])) {
            $add('woocommerce_checkout_fields', static function($all) use ($data) {
                foreach ($data['fields'] as $group => $set) { foreach ($set as $key => $props) { if (isset($all[$group][$key])) { $all[$group][$key] = array_merge($all[$group][$key], $props); } } }
                return $all;
            });
        }
        if (empty($data['notes'])) { $add('woocommerce_enable_order_notes_field', '__return_false', 99); }
        if (($data['terms'] ?? '') !== '') { $add('woocommerce_get_terms_and_conditions_checkbox_text', static fn() => $data['terms'] . ' [terms]'); }
        [$text, $link] = array_pad((array) ($data['coupon'] ?? []), 2, '');
        if ($text !== '' || $link !== '') {
            $add('woocommerce_checkout_coupon_message', static fn() => esc_html($text !== '' ? $text : __('Have a coupon?', 'woocommerce')) . ' <a href="#" role="button" aria-label="' . esc_attr__('Enter your coupon code', 'woocommerce') . '" aria-controls="woocommerce-checkout-form-coupon" aria-expanded="false" class="showcoupon">' . esc_html($link !== '' ? $link : __('Click here to enter your code', 'woocommerce')) . '</a>');
        }
        $texts = self::replace_texts((array) ($data['texts'] ?? []));
        return static function() use (&$filters, $texts) { foreach ($filters as [$hook, $fn, $priority]) { remove_filter($hook, $fn, $priority); } $filters = []; $texts(); };
    }

    protected function render(): void {
        if (!self::ensure_cart()) { return; }
        $s = $this->get_settings_for_display();
        $data = $this->checkout_data($s);
        self::remember($data);
        $classes = ['obx-woo-checkout', 'obx-woo-checkout--' . (($s['checkout_layout'] ?? 'two-column') === 'one-column' ? 'one-column' : 'two-column')];
        if (($s['sticky_right_column'] ?? '') === 'yes') { $classes[] = 'obx-woo-sticky'; }
        $coupons = ($s['coupon_section_display'] ?? 'yes') === 'yes';
        if (!$coupons) { remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10); }
        $undo = self::apply($data);
        // The order summary and the payment form make the right column of the two-column layout.
        $open = static function() { echo '<div class="obx-woo-checkout__aside">'; };
        $close = static function() { echo '</div>'; };
        add_action('woocommerce_checkout_before_order_review_heading', $open, 1);
        add_action('woocommerce_checkout_after_order_review', $close, 999);
        try {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode() && WC()->cart->is_empty()) {
                // The editor previews the form even with an empty cart (WooCommerce would print «cart is empty»).
                $html = (string) wc_get_template_html('checkout/form-checkout.php', ['checkout'=>WC()->checkout()]);
                $html = '<div class="woocommerce">' . $html . '</div>';
            } else {
                $html = (string) do_shortcode('[woocommerce_checkout]');
            }
        } finally {
            $undo();
            remove_action('woocommerce_checkout_before_order_review_heading', $open, 1);
            remove_action('woocommerce_checkout_after_order_review', $close, 999);
            if (!$coupons) { add_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10); }
        }
        echo '<div class="' . esc_attr(implode(' ', $classes)) . '"' . $this->animations(['forms_buttons_hover_animation'=>'form.woocommerce-form-login .button, form.checkout_coupon .button', 'purchase_button_hover_animation'=>'#place_order']) . '>';
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce shortcode output.
        echo '</div>';
    }
}

final class Woo_My_Account extends Woo_Page_Widget {
    public const PRO = 'woocommerce-my-account';
    protected const LEGACY = ['woo_color'=>'general_text_color','woo_hover'=>'links_hover_color','woo_typography'=>'general_text_typography'];
    private const TABS = ['dashboard'=>'Dashboard','orders'=>'Orders','downloads'=>'Downloads','edit-address'=>'Addresses','edit-account'=>'Account details','customer-logout'=>'Log out'];
    public function get_title(): string { return 'Mi cuenta'; }
    public function get_icon(): string { return 'eicon-my-account'; }
    public function get_keywords(): array { return ['woocommerce', 'cuenta', 'account', 'pedidos', 'cliente']; }

    protected function register_controls(): void {
        $w = '{{WRAPPER}}';
        $nav = $w . ' .woocommerce-MyAccount-navigation';
        $this->section('section_menu_icon_content', 'Pestañas', false);
        $this->add_control('tabs_layout', ['label'=>'Disposición','type'=>Controls_Manager::SELECT,'default'=>'vertical','options'=>['vertical'=>'Vertical','horizontal'=>'Horizontal'],'prefix_class'=>'e-my-account-tabs-']);
        $this->gap('tabs_content_spacing', 'Separación del contenido', [$w . ' .obx-woo-account .woocommerce'=>'--obx-account-gap: {{SIZE}}{{UNIT}};'], [], ['px','%','em','rem','custom']);
        $this->add_responsive_control('tabs_position', ['label'=>'Posición','type'=>Controls_Manager::CHOOSE,'options'=>['start'=>['title'=>'Inicio','icon'=>'eicon-h-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-h-align-center'],'end'=>['title'=>'Final','icon'=>'eicon-h-align-right'],'stretch'=>['title'=>'Ocupar todo','icon'=>'eicon-h-align-stretch']],'condition'=>['tabs_layout'=>'horizontal'],
            'selectors_dictionary'=>['start'=>'flex-start; --obx-account-tab-grow: 0','center'=>'center; --obx-account-tab-grow: 0','end'=>'flex-end; --obx-account-tab-grow: 0','stretch'=>'stretch; --obx-account-tab-grow: 1'],'selectors'=>[$nav . ' ul'=>'justify-content: {{VALUE}};']]);
        $tabs = new \Elementor\Repeater();
        $tabs->add_control('tab_name', ['label'=>'Nombre de la pestaña','type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true]]);
        $tabs->add_control('order_display_description', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Los pedidos se muestran con la tabla de WooCommerce.','content_classes'=>'elementor-descriptor','condition'=>['field_key'=>'orders']]);
        $default = [];
        foreach (self::TABS as $key => $label) { $text = __($label, 'woocommerce'); $default[] = ['field_key'=>$key,'field_label'=>$text,'tab_name'=>$text]; } // phpcs:ignore WordPress.WP.I18n -- WooCommerce's own strings.
        $this->add_control('tabs', ['label'=>'','type'=>Controls_Manager::REPEATER,'fields'=>$tabs->get_controls(),'default'=>$default,'title_field'=>'{{{ field_label }}}','item_actions'=>['add'=>false,'duplicate'=>false,'remove'=>false,'sort'=>false]]);
        $this->add_responsive_control('tabs_alignment', ['label'=>'Alineación del texto','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(),'selectors'=>[$nav . ' a'=>'text-align: {{VALUE}}; justify-content: {{VALUE}};']]);
        $this->end_controls_section();

        $this->section('section_additional_options', 'Opciones adicionales', false);
        $this->add_control('customize_dashboard_check', ['label'=>'Escritorio personalizado','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes']);
        $this->add_control('customize_dashboard_select', ['label'=>'Plantilla','description'=>'Sustituye el contenido del escritorio de la cuenta.','type'=>Controls_Manager::SELECT2,'options'=>self::templates(),'label_block'=>true,'condition'=>['customize_dashboard_check'=>'yes']]);
        $this->end_controls_section();

        $tab = $nav . ' li a';
        $this->section('tabs_style', 'Pestañas');
        $this->typo('tabs_typography', $tab);
        $this->start_controls_tabs('tabs_section');
        foreach (['normal'=>['Normal', ''], 'hover'=>['Al pasar el ratón', ':hover'], 'active'=>['Activa', '']] as $state => [$label, $pseudo]) {
            $target = $state === 'active' ? $nav . ' li.is-active a' : $tab . $pseudo;
            $this->start_controls_tab('tabs_' . $state, ['label'=>$label]);
            $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'tabs_' . $state . '_background','types'=>['classic','gradient'],'selector'=>$target]);
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'tabs_' . $state . '_box_shadow','selector'=>$target]);
            $this->paint('tabs_' . $state . '_color', 'Color del texto', $target);
            if ($state !== 'normal') { $this->paint('tabs_' . $state . '_border_color', 'Color del borde', $target, 'border-color', ['condition'=>['tabs_border_type!'=>'']]); }
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->border_type('tabs_border_type', $tab, ['separator'=>'before']);
        $this->add_responsive_control('tabs_border_width', ['label'=>'Grosor del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'condition'=>['tabs_border_type!'=>'none'],'selectors'=>[$tab=>'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->paint('tabs_border_color', 'Color del borde', $tab, 'border-color', ['condition'=>['tabs_border_type!'=>'none']]);
        $this->dims('tabs_border_radius', 'Radio del borde', $tab, 'border-radius');
        $this->dims('tabs_padding', 'Relleno', $tab, 'padding');
        $this->gap('tabs_spacing', 'Separación entre pestañas', [$nav . ' ul'=>'gap: {{SIZE}}{{UNIT}};']);
        $this->paint('tabs_divider_color', 'Color del separador', $nav . ' li:not(:last-child)', '--obx-account-divider-color', ['separator'=>'before']);
        $this->gap('tabs_divider_weight', 'Grosor del separador', [$nav . ' li:not(:last-child)'=>'--obx-account-divider-weight: {{SIZE}}{{UNIT}};'], [], ['px','%','em','rem','vw','custom']);
        $this->end_controls_section();

        $sections = $w . ' .woocommerce-MyAccount-content, ' . $w . ' form.woocommerce-form-login, ' . $w . ' form.woocommerce-form-register, ' . $w . ' form.woocommerce-ResetPassword';
        $this->section('sections_title', 'Secciones');
        $this->box($sections, ['bg'=>'my_account_sections_background_color','shadow'=>'my_account_sections_box_shadow','type'=>'sections_border_type','width'=>'sections_border_width','color'=>'sections_border_color','radius'=>'sections_border_radius','padding'=>'sections_padding']);
        $this->end_controls_section();

        $this->section('typography_title', 'Tipografía');
        $this->text('Títulos', 'typography_section_titles_color', 'section_titles_typography', $w . ' .woocommerce h2, ' . $w . ' .woocommerce h3', [], 'section_titles_typography_text_shadow', 'section_title_spacing');
        $this->text('Texto general', 'general_text_color', 'general_text_typography', $w . ' .woocommerce-MyAccount-content, ' . $w . ' .woocommerce-MyAccount-content p, ' . $w . ' .woocommerce address');
        $this->text('Mensajes de acceso', 'login_messages_color', 'login_messages_typography', $w . ' form.woocommerce-form-login > p:first-child, ' . $w . ' form.woocommerce-ResetPassword > p:first-child, ' . $w . ' .woocommerce-privacy-policy-text');
        $this->text('Casillas', 'checkboxes_color', 'checkboxes_typography', $w . ' .woocommerce-form__label-for-checkbox');
        $this->text('Botones de opción de los métodos de pago', 'payment_methods_radio_buttons_color', 'payment_methods_radio_buttons_typography', $w . ' .wc_payment_method > label');
        $this->add_control('heading_links', ['label'=>'Enlaces','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color_tabs('links_colors', 'links_normal_color', 'links_hover_color', $w . ' .woocommerce-MyAccount-content a:not(.button), ' . $w . ' .woocommerce form a:not(.button)');
        $this->end_controls_section();

        $this->section('forms_section', 'Formularios');
        $this->form_fields($w . ' .woocommerce form .form-row', $w . ' .woocommerce form .input-text, ' . $w . ' .woocommerce form select, ' . $w . ' .woocommerce form .select2-container .select2-selection', $w . ' .woocommerce form .form-row > label', $w . ' .woocommerce');
        $this->form_buttons($w . ' .woocommerce form .button', true);
        $this->end_controls_section();

        $table = $w . ' .woocommerce table.shop_table';
        $this->section('tables_section', 'Tablas');
        $this->gap('tables_rows_gap', 'Separación entre filas', [$table . ' th, ' . $table . ' td'=>'padding-top: calc({{SIZE}}{{UNIT}} / 2); padding-bottom: calc({{SIZE}}{{UNIT}} / 2);']);
        $this->text('Títulos', 'tables_title_color', 'tables_titles_typography', $table . ' thead th, ' . $table . ' tfoot th', [], 'tables_titles_text_shadow');
        $this->text('Contenido', 'tables_items_color', 'tables_items_typography', $table . ' tbody td, ' . $table . ' tfoot td');
        $this->text('Variaciones', 'variations_color', 'variations_typography', $table . ' .wc-item-meta, ' . $table . ' .variation');
        $this->add_control('heading_tables_links', ['label'=>'Enlaces','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color_tabs('tables_links_colors', 'tables_links_normal_color', 'tables_links_hover_color', $table . ' a:not(.button)');
        $this->border_type('tables_divider_border_type', $table . ' th, ' . $table . ' td', ['separator'=>'before'], 'border-top-style');
        $this->gap('tables_divider_border_width', 'Grosor del separador', [$table . ' th, ' . $table . ' td'=>'border-top-width: {{SIZE}}{{UNIT}};'], ['condition'=>['tables_divider_border_type!'=>'none']], ['px','%','em','rem','vw','custom']);
        $this->paint('tables_divider_border_color', 'Color del separador', $table . ' th, ' . $table . ' td', 'border-top-color', ['condition'=>['tables_divider_border_type!'=>'none']]);
        $this->add_control('heading_tables_button', ['label'=>'Botones','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->button_set($table . ' .button', ['typo'=>'tables_button_typography','tshadow'=>'tables_button_text_shadow','tabs'=>'tables_button_styles','text'=>'tables_button_normal_text_color','bg'=>'tables_button_normal_background','shadow'=>'tables_button_normal_box_shadow','hover_text'=>'tables_button_hover_text_color','hover_bg'=>'tables_button_hover_background','hover_shadow'=>'tables_button_hover_box_shadow','hover_border'=>'tables_button_hover_border_color','transition'=>'tables_button_hover_transition_duration','animation'=>'tables_button_hover_animation','border'=>'tables_button_border','radius'=>'tables_button_border_radius','padding'=>'tables_button_padding']);
        $this->end_controls_section();
    }

    protected function render(): void {
        if (!function_exists('WC')) { return; }
        $s = $this->get_settings_for_display();
        $names = [];
        foreach ((array) ($s['tabs'] ?? []) as $row) { $key = (string) ($row['field_key'] ?? ''); $name = trim((string) ($row['tab_name'] ?? '')); if ($key !== '' && $name !== '') { $names[$key] = $name; } }
        $rename = static function($items) use ($names) { foreach ($names as $key => $name) { if (isset($items[$key])) { $items[$key] = $name; } } return $items; };
        add_filter('woocommerce_account_menu_items', $rename, 99);
        $dashboard = null;
        if (($s['customize_dashboard_check'] ?? '') === 'yes' && absint($s['customize_dashboard_select'] ?? 0)) {
            $template = (int) $s['customize_dashboard_select'];
            $dashboard = static function($located, $name) use ($template) {
                if ($name !== 'myaccount/dashboard.php') { return $located; }
                Woo_My_Account::$dashboard = self::template_html($template);
                return \OldBearExpansion\DIR . '/templates/woocommerce/dashboard.php';
            };
            add_filter('wc_get_template', $dashboard, 99, 2);
        }
        try { $html = (string) do_shortcode('[woocommerce_my_account]'); }
        finally {
            remove_filter('woocommerce_account_menu_items', $rename, 99);
            if ($dashboard) { remove_filter('wc_get_template', $dashboard, 99); }
        }
        echo '<div class="obx-woo-account"' . $this->animations(['forms_buttons_hover_animation'=>'.woocommerce form .button', 'tables_button_hover_animation'=>'table.shop_table .button']) . '>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce shortcode output.
    }

    /** HTML of the custom dashboard template, printed by woocommerce-dashboard.php. */
    public static string $dashboard = '';
}

final class Woo_Purchase_Summary extends Woo_Page_Widget {
    public const PRO = 'woocommerce-purchase-summary';
    protected const TEXTS = ['Order number:'=>'payment_details_number','Date:'=>'payment_details_date','Email:'=>'payment_details_email','Total:'=>'payment_details_total','Payment method:'=>'payment_details_payment','Our bank details'=>'bank_details_text','Downloads'=>'downloads_text','Order details'=>'order_summary_text','Billing address'=>'billing_details_text','Shipping address'=>'shipping_details_text'];
    public function get_title(): string { return 'Resumen de la compra'; }
    public function get_icon(): string { return 'eicon-purchase-summary'; }
    public function get_keywords(): array { return ['woocommerce', 'gracias', 'thank you', 'pedido', 'resumen', 'compra']; }

    protected function register_controls(): void {
        $w = '{{WRAPPER}}';
        $on = ['confirmation_message_active!'=>''];
        $this->section('confirmation_message', 'Mensaje de confirmación', false);
        $this->add_control('confirmation_message_active', ['label'=>'Mostrar','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes']);
        $this->add_control('confirmation_message_text', ['label'=>'Mensaje','type'=>Controls_Manager::TEXTAREA,'default'=>__('Thank you. Your order has been received.', 'woocommerce'),'dynamic'=>['active'=>true],'condition'=>$on]);
        $this->title_align('confirmation_message_alignment', $w . ' .woocommerce-thankyou-order-received', $on);
        $this->end_controls_section();

        $this->section('payment_details', 'Datos del pago', false);
        foreach (['number'=>['Order number:', 'Número de pedido'], 'date'=>['Date:', 'Fecha'], 'email'=>['Email:', 'Correo electrónico'], 'total'=>['Total:', 'Total'], 'payment'=>['Payment method:', 'Método de pago']] as $key => [$original, $label]) {
            $this->add_control('payment_details_' . $key, ['label'=>$label,'type'=>Controls_Manager::TEXT,'default'=>__($original, 'woocommerce'),'dynamic'=>['active'=>true]]); // phpcs:ignore WordPress.WP.I18n -- WooCommerce's own strings.
        }
        $this->end_controls_section();

        foreach (['bank_details'=>['Datos bancarios', 'Our bank details', '.woocommerce-bacs-bank-details > h2'], 'downloads'=>['Descargas', 'Downloads', '.woocommerce-order-downloads > h2'], 'order_summary'=>['Detalles del pedido', 'Order details', '.woocommerce-order-details > h2'], 'billing_details'=>['Datos de facturación', 'Billing address', '.woocommerce-column--billing-address > h2'], 'shipping_details'=>['Datos de envío', 'Shipping address', '.woocommerce-column--shipping-address > h2']] as $key => [$label, $original, $selector]) {
            $this->section($key, $label, false);
            $this->add_control($key . '_text', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>__($original, 'woocommerce'),'dynamic'=>['active'=>true]]); // phpcs:ignore WordPress.WP.I18n -- WooCommerce's own strings.
            $this->title_align($key . '_alignment', $w . ' ' . $selector);
            $this->end_controls_section();
        }

        $this->section('preview_order', 'Vista previa', false);
        $this->add_control('preview_order_type', ['label'=>'Pedido de la vista previa','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'El último pedido','custom-order'=>'Un pedido concreto']]);
        $this->add_control('preview_order_custom', ['label'=>'Número de pedido','type'=>Controls_Manager::TEXT,'condition'=>['preview_order_type'=>'custom-order']]);
        $this->end_controls_section();

        $sections = $w . ' .woocommerce-order-overview, ' . $w . ' .woocommerce-bacs-bank-details, ' . $w . ' .woocommerce-order-details, ' . $w . ' .woocommerce-order-downloads, ' . $w . ' .woocommerce-customer-details .woocommerce-column, ' . $w . ' .woocommerce-customer-details > address';
        $this->section('sections_tabs_style', 'Secciones');
        $this->box($sections, ['bg'=>'sections_background_color','shadow'=>'sections_box_shadow','type'=>'sections_border_type','width'=>'sections_border_width','color'=>'sections_border_color','radius'=>'sections_border_radius','padding'=>'sections_padding']);
        $this->gap('sections_spacing', 'Separación', [$w . ' .woocommerce-order'=>'--obx-summary-gap: {{SIZE}}{{UNIT}};'], [], ['px','%','em','rem','vw','custom']);
        $this->end_controls_section();

        $this->section('typography_title', 'Tipografía');
        $this->text('Mensaje de confirmación', 'confirmation_message_color', 'confirmation_message_typography', $w . ' .woocommerce-thankyou-order-received', [], 'confirmation_message_text_shadow');
        $this->text('Títulos', 'titles_color', 'titles_typography', $w . ' .woocommerce-order h2', [], 'titles_text_shadow', 'titles_spacing');
        $this->text('Texto general', 'general_text_color', 'general_text_typography', $w . ' .woocommerce-order p, ' . $w . ' .woocommerce-order address');
        $this->end_controls_section();

        $overview = $w . ' ul.woocommerce-order-overview';
        $this->section('payment_details_title', 'Datos del pago');
        $this->gap('payment_details_space_between', 'Separación', [$overview=>'gap: {{SIZE}}{{UNIT}};']);
        $this->text('Títulos', 'payment_details_titles_color', 'payment_details_titles_typography', $overview . ' li', [], 'payment_details_titles_text_shadow', 'payment_details_titles_spacing', '--obx-summary-label-gap');
        $this->text('Datos', 'payment_details_items_color', 'payment_details_items_typography', $overview . ' li strong');
        $this->border_type('payment_details_border_type', $overview . ' li:not(:last-child)', ['separator'=>'before'], 'border-inline-end-style');
        $this->gap('payment_details_border_width', 'Grosor', [$overview . ' li:not(:last-child)'=>'border-inline-end-width: {{SIZE}}{{UNIT}};'], ['condition'=>['payment_details_border_type!'=>'none']], ['px','%','em','rem','vw','custom']);
        $this->paint('payment_details_border_color', 'Color', $overview . ' li:not(:last-child)', 'border-inline-end-color', ['condition'=>['payment_details_border_type!'=>'none']]);
        $this->end_controls_section();

        $bank = $w . ' .woocommerce-bacs-bank-details';
        $this->section('bank_details_title', 'Datos bancarios');
        $this->gap('bank_details_space_between', 'Separación', [$bank . ' ul'=>'gap: {{SIZE}}{{UNIT}};']);
        $this->text('Titular de la cuenta', 'account_title_color', 'account_title_typography', $bank . ' .wc-bacs-bank-details-account-name', [], 'account_title_text_shadow', 'account_title_spacing');
        $this->text('Títulos', 'bank_details_titles_color', 'bank_details_titles_typography', $bank . ' ul li', [], 'bank_details_titles_text_shadow', 'bank_details_titles_spacing', '--obx-summary-label-gap');
        $this->text('Datos', 'bank_details_items_color', 'bank_details_items_typography', $bank . ' ul li strong');
        $this->border_type('bank_details_border_type', $bank . ' ul li:not(:last-child)', ['separator'=>'before'], 'border-inline-end-style');
        $this->gap('bank_details_border_width', 'Grosor', [$bank . ' ul li:not(:last-child)'=>'border-inline-end-width: {{SIZE}}{{UNIT}};'], ['condition'=>['bank_details_border_type!'=>'none']], ['px','%','em','rem','vw','custom']);
        $this->paint('bank_details_border_color', 'Color', $bank . ' ul li:not(:last-child)', 'border-inline-end-color', ['condition'=>['bank_details_border_type!'=>'none']]);
        $this->end_controls_section();

        $table = $w . ' .woocommerce-order table.shop_table';
        $this->section('order_details_title', 'Detalles del pedido');
        $this->gap('order_details_rows_gap', 'Separación entre filas', [$table . ' th, ' . $table . ' td'=>'padding-top: calc({{SIZE}}{{UNIT}} / 2); padding-bottom: calc({{SIZE}}{{UNIT}} / 2);']);
        $this->text('Títulos y totales', 'order_details_titles_totals_color', 'order_details_titles_totals_typography', $table . ' thead th, ' . $table . ' tfoot th, ' . $table . ' tfoot td', [], 'order_details_titles_totals_text_shadow');
        $this->text('Productos', 'order_details_items_color', 'order_details_items_typography', $table . ' tbody td');
        $this->text('Variaciones', 'order_details_variations_color', 'order_details_variations_typography', $table . ' .wc-item-meta, ' . $table . ' .wc-item-meta p');
        $this->add_control('heading_order_details_links', ['label'=>'Enlaces de los productos','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color_tabs('order_details_product_links_colors', 'order_details_product_links_normal_color', 'order_details_product_links_hover_color', $table . ' a:not(.button)');
        $this->border_type('order_details_border_type', $table . ' th, ' . $table . ' td', ['separator'=>'before'], 'border-top-style');
        $this->gap('order_details_border_width', 'Grosor', [$table . ' th, ' . $table . ' td'=>'border-top-width: {{SIZE}}{{UNIT}};'], ['condition'=>['order_details_border_type!'=>'none']], ['px','%','em','rem','vw','custom']);
        $this->paint('order_details_border_color', 'Color', $table . ' th, ' . $table . ' td', 'border-top-color', ['condition'=>['order_details_border_type!'=>'none']]);
        $this->add_control('heading_order_details_button', ['label'=>'Botones','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->button_set($w . ' .woocommerce-order .button', ['typo'=>'order_details_button_typography','tshadow'=>'order_details_button_text_shadow','tabs'=>'order_details_button_styles','text'=>'order_details_button_normal_text_color','bg'=>'order_details_button_normal_background','shadow'=>'order_details_button_normal_box_shadow','hover_text'=>'order_details_button_hover_text_color','hover_bg'=>'order_details_button_hover_background','hover_shadow'=>'order_details_button_hover_box_shadow','hover_border'=>'order_details_button_hover_border_color','transition'=>'order_details_button_hover_transition_duration','animation'=>'order_details_button_hover_animation','border'=>'order_details_button_border','radius'=>'order_details_button_border_radius','padding'=>'order_details_button_padding']);
        $this->end_controls_section();
    }

    /** The order of the «order received» request (with its key), or a preview order in the editor. */
    private function order(array $s): ?\WC_Order {
        $id = absint($_GET['order-received'] ?? get_query_var('order-received')); // phpcs:ignore WordPress.Security.NonceVerification -- read-only, checked with the order key.
        if ($id) {
            $order = wc_get_order($id);
            $key = sanitize_text_field(wp_unslash((string) ($_GET['key'] ?? ''))); // phpcs:ignore WordPress.Security.NonceVerification
            if ($order instanceof \WC_Order && $key !== '' && hash_equals($order->get_order_key(), $key)) { return $order; }
            return null;
        }
        $preview = \Elementor\Plugin::$instance->editor->is_edit_mode() || is_preview() || isset($_GET['elementor-preview']); // phpcs:ignore WordPress.Security.NonceVerification
        if (!$preview || !current_user_can('edit_shop_orders')) { return null; }
        if (($s['preview_order_type'] ?? '') === 'custom-order' && absint($s['preview_order_custom'] ?? 0)) {
            $order = wc_get_order(absint($s['preview_order_custom']));
            return $order instanceof \WC_Order ? $order : null;
        }
        $orders = wc_get_orders(['limit'=>1,'orderby'=>'date','order'=>'DESC','type'=>'shop_order']);
        return ($orders[0] ?? null) instanceof \WC_Order ? $orders[0] : null;
    }

    protected function render(): void {
        if (!function_exists('wc_get_order')) { return; }
        $s = $this->get_settings_for_display();
        $order = $this->order($s);
        if (!$order) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) { $this->placeholder('Aquí se mostrará el resumen del pedido recibido. Aún no hay pedidos para la vista previa.'); }
            return;
        }
        $message = ($s['confirmation_message_active'] ?? 'yes') === 'yes' ? trim((string) ($s['confirmation_message_text'] ?? '')) : '';
        $hide = ($s['confirmation_message_active'] ?? 'yes') !== 'yes';
        $received = static fn($text) => $hide ? '' : ($message !== '' ? esc_html($message) : $text);
        add_filter('woocommerce_thankyou_order_received_text', $received, 99);
        try { $html = self::with_texts($this->texts($s), static fn() => (string) wc_get_template_html('checkout/thankyou.php', ['order'=>$order])); }
        finally { remove_filter('woocommerce_thankyou_order_received_text', $received, 99); }
        echo '<div class="obx-woo-summary woocommerce' . ($hide ? ' obx-woo-summary--no-message' : '') . '"' . $this->animations(['order_details_button_hover_animation'=>'.woocommerce-order .button']) . '>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce template output.
    }
}

final class Woo_Notices extends Woo_Page_Widget {
    public const PRO = 'woocommerce-notices';
    /** WooCommerce prints its notices here instead of these default places. */
    private const DEFAULTS = [['woocommerce_before_single_product', 10], ['woocommerce_before_shop_loop', 10], ['woocommerce_before_cart', 10], ['woocommerce_before_checkout_form', 10], ['woocommerce_account_content', 5], ['woocommerce_before_customer_login_form', 10], ['woocommerce_before_lost_password_form', 10], ['woocommerce_before_reset_password_form', 10]];
    public function get_title(): string { return 'Avisos de WooCommerce'; }
    public function get_icon(): string { return 'eicon-woocommerce-notices'; }
    public function get_keywords(): array { return ['woocommerce', 'avisos', 'notices', 'mensajes', 'errores']; }
    public function get_script_depends(): array { return []; }
    protected function register_controls(): void {
        $this->section('section_notices', 'Avisos', false);
        $this->add_control('notices_description', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Los avisos de WooCommerce (producto añadido, errores del formulario…) aparecen aquí en lugar de en su sitio habitual. Su estilo se cambia en la pestaña WooCommerce de los ajustes del sitio.','content_classes'=>'elementor-descriptor']);
        $this->end_controls_section();
    }

    /** When the page or one of its templates has this widget, WooCommerce's own notice output is removed. */
    public static function relocate(array $documents): void {
        foreach ($documents as $id) {
            $data = (string) get_post_meta((int) $id, '_elementor_data', true);
            if ($data !== '' && str_contains($data, '"widgetType":"woocommerce-notices"')) {
                foreach (self::DEFAULTS as [$hook, $priority]) { remove_action($hook, 'woocommerce_output_all_notices', $priority); }
                return;
            }
        }
    }

    protected function render(): void {
        // While a document is saved Elementor renders it to plain text: printing would use up the notices.
        $saving = did_action('elementor/document/before_save') > did_action('elementor/document/after_save');
        if ($saving || \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            echo '<div class="woocommerce-notices-wrapper"><div class="woocommerce-message" role="alert">' . esc_html__('Aquí aparecerán los avisos de WooCommerce.', 'oldbear-elementor-expansion') . '</div></div>';
            return;
        }
        if (function_exists('woocommerce_output_all_notices')) { echo '<div class="woocommerce">'; woocommerce_output_all_notices(); echo '</div>'; }
    }
}
