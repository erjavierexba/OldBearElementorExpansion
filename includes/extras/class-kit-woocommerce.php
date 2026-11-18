<?php
namespace OldBearExpansion\Extras;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Kit;
use Elementor\Core\Kits\Documents\Tabs\Tab_Base;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;

if (!defined('ABSPATH')) { exit; }

/**
 * Site Settings → WooCommerce with Elementor Pro's keys: the store pages (saved to WooCommerce's own options, as
 * Pro does), the page that shows the purchase summary after checkout, and the style of WooCommerce's notices.
 */
final class Kit_Woocommerce extends Tab_Base {
    public const TAB_ID = 'settings-woocommerce';
    /** Kit setting => WooCommerce option. */
    public const PAGES = ['woocommerce_cart_page_id'=>'woocommerce_cart_page_id','woocommerce_checkout_page_id'=>'woocommerce_checkout_page_id','woocommerce_myaccount_page_id'=>'woocommerce_myaccount_page_id','woocommerce_terms_page_id'=>'woocommerce_terms_page_id','woocommerce_purchase_summary_page_id'=>'elementor_woocommerce_purchase_summary_page_id','woocommerce_shop_page_id'=>'woocommerce_shop_page_id'];
    private const NOTICES = ['wc_error'=>['error', '.woocommerce-error, {{WRAPPER}} .wc-block-components-notice-banner.is-error', 'Errores'], 'wc_message'=>['message', '.woocommerce-message, {{WRAPPER}} .wc-block-components-notice-banner.is-success', 'Mensajes'], 'wc_info'=>['info', '.woocommerce-info, {{WRAPPER}} .wc-block-components-notice-banner.is-info', 'Información']];
    private const BORDER_TYPES = ['none'=>'Ninguno','solid'=>'Continuo','double'=>'Doble','dotted'=>'Punteado','dashed'=>'Discontinuo','groove'=>'Ranurado'];

    /** After checkout, WooCommerce sends the customer to the purchase summary page chosen in the kit. */
    public static function order_received_url($url, $order) {
        $page = absint(get_option('elementor_woocommerce_purchase_summary_page_id'));
        if (!$page || !$order instanceof \WC_Order || get_post_status($page) !== 'publish') { return $url; }
        return add_query_arg(['order-received'=>$order->get_id(), 'key'=>$order->get_order_key()], get_permalink($page));
    }

    public function get_id() { return self::TAB_ID; }
    public function get_title() { return 'WooCommerce'; }
    public function get_group() { return 'settings'; }
    public function get_icon() { return 'eicon-woocommerce'; }
    public function get_help_url() { return ''; }

    private static function pages(): array {
        $options = [''=>'— Ninguna —'];
        foreach (get_pages(['post_status'=>'publish','sort_column'=>'post_title']) as $page) { $options[(string) $page->ID] = $page->post_title !== '' ? $page->post_title : '#' . $page->ID; }
        return $options;
    }

    protected function register_tab_controls() {
        $kit = $this->parent;
        $kit->start_controls_section('section_woocommerce_pages', ['label'=>'Páginas','tab'=>$this->get_id()]);
        $pages = self::pages();
        foreach (['woocommerce_cart_page_id'=>'Carrito','woocommerce_checkout_page_id'=>'Finalizar compra','woocommerce_myaccount_page_id'=>'Mi cuenta','woocommerce_terms_page_id'=>'Términos y condiciones','woocommerce_purchase_summary_page_id'=>'Resumen de la compra','woocommerce_shop_page_id'=>'Tienda'] as $key => $label) {
            $default = (string) get_option(self::PAGES[$key], '');
            $kit->add_control($key, ['label'=>$label,'type'=>Controls_Manager::SELECT2,'options'=>$pages,'default'=>$default !== '0' ? $default : '','label_block'=>true]
                + ($key === 'woocommerce_purchase_summary_page_id' ? ['description'=>'Tras el pago, el cliente llega a esta página (con el widget «Resumen de la compra»).'] : []));
        }
        $kit->end_controls_section();

        $kit->start_controls_section('section_woocommerce_notices', ['label'=>'Avisos','tab'=>$this->get_id()]);
        $kit->add_control('woocommerce_notices_elements', ['label'=>'Avisos que quieres personalizar','type'=>Controls_Manager::SELECT2,'label_block'=>true,'multiple'=>true,'options'=>['wc_error'=>'Errores','wc_message'=>'Mensajes','wc_info'=>'Información']]);
        $kit->end_controls_section();

        foreach (self::NOTICES as $type => [$p, $selector, $label]) {
            $box = '{{WRAPPER}} ' . $selector;
            $on = ['woocommerce_notices_elements'=>$type];
            $c = ['condition'=>$on];
            $kit->start_controls_section('woocommerce_' . $p . '_notices', ['label'=>'Avisos: ' . mb_strtolower($label),'tab'=>$this->get_id(),'condition'=>$on]);
            $kit->add_control($p . '_message_text_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>[$box=>'color: {{VALUE}};']] + $c);
            $kit->add_group_control(Group_Control_Typography::get_type(), ['name'=>$p . '_message_text_typography','selector'=>$box] + $c);
            $kit->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>$p . '_message_text_shadow','selector'=>$box] + $c);
            $icons = implode(', ', array_map(static fn($part) => trim($part) . '::before', explode(',', $box)));
            $kit->add_control($p . '_message_icon_color', ['label'=>'Color del icono','type'=>Controls_Manager::COLOR,'selectors'=>[$icons=>'color: {{VALUE}};', '{{WRAPPER}} .wc-block-components-notice-banner.is-' . ($p === 'message' ? 'success' : $p) . ' > svg'=>'background-color: {{VALUE}};']] + $c);
            if ($p !== 'info') {
                $links = implode(', ', array_map(static fn($part) => trim($part) . ' a:not(.button)', explode(',', $box)));
                $hover = implode(', ', array_map(static fn($part) => trim($part) . ' a:not(.button):hover', explode(',', $box)));
                $lp = $p === 'message' ? 'notice_message' : 'error_message';
                $kit->add_control('heading_' . $p . '_links', ['label'=>'Enlaces','type'=>Controls_Manager::HEADING,'separator'=>'before'] + $c);
                $kit->add_group_control(Group_Control_Typography::get_type(), ['name'=>$lp . '_link_typography','selector'=>$links] + $c);
                $kit->start_controls_tabs($lp . '_links', $c);
                $kit->start_controls_tab($lp . '_links_normal', ['label'=>'Normal'] + $c);
                $kit->add_control($lp . '_normal_links_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>[$links=>'color: {{VALUE}};']] + $c);
                $kit->end_controls_tab();
                $kit->start_controls_tab($lp . '_links_hover', ['label'=>'Al pasar el ratón'] + $c);
                $kit->add_control($lp . '_hover_links_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>[$hover=>'color: {{VALUE}};']] + $c);
                $kit->end_controls_tab();
                $kit->end_controls_tabs();
            }
            $kit->add_control('heading_' . $p . '_box', ['label'=>'Caja','type'=>Controls_Manager::HEADING,'separator'=>'before'] + $c);
            $kit->add_group_control(Group_Control_Background::get_type(), ['name'=>$p . '_notice_box_background','types'=>['classic','gradient'],'selector'=>$box] + $c);
            $kit->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>$p . '_notice_box_box_shadow','selector'=>$box] + $c);
            $kit->add_group_control(Group_Control_Border::get_type(), ['name'=>$p . '_notice_box_border','selector'=>$box] + $c);
            $kit->add_responsive_control($p . '_notice_box_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'selectors'=>[$box=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']] + $c);
            if ($p !== 'error') {
                $button = implode(', ', array_map(static fn($part) => trim($part) . ' .button', explode(',', $box)));
                $hovered = implode(', ', array_map(static fn($part) => trim($part) . ' .button:hover', explode(',', $box)));
                $kit->add_control('heading_' . $p . '_buttons', ['label'=>'Botones','type'=>Controls_Manager::HEADING,'separator'=>'before'] + $c);
                $kit->add_group_control(Group_Control_Typography::get_type(), ['name'=>$p . '_button_typography','selector'=>$button] + $c);
                $kit->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>$p . '_button_text_shadow','selector'=>$button] + $c);
                $kit->start_controls_tabs($p . '_buttons_styles', $c);
                $kit->start_controls_tab($p . '_buttons_normal', ['label'=>'Normal'] + $c);
                $kit->add_control($p . '_buttons_normal_text_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>[$button=>'color: {{VALUE}};']] + $c);
                $kit->add_group_control(Group_Control_Background::get_type(), ['name'=>$p . '_buttons_normal_background','types'=>['classic','gradient'],'selector'=>$button] + $c);
                $kit->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>$p . '_buttons_normal_box_shadow','selector'=>$button] + $c);
                $kit->end_controls_tab();
                $kit->start_controls_tab($p . '_buttons_hover', ['label'=>'Al pasar el ratón'] + $c);
                $kit->add_control($p . '_buttons_hover_text_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>[$hovered=>'color: {{VALUE}};']] + $c);
                $kit->add_group_control(Group_Control_Background::get_type(), ['name'=>$p . '_buttons_hover_background','types'=>['classic','gradient'],'selector'=>$hovered] + $c);
                $kit->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>$p . '_buttons_focus_box_shadow','selector'=>$hovered] + $c);
                $kit->add_control($p . '_buttons_hover_border_color', ['label'=>'Color del borde','type'=>Controls_Manager::COLOR,'condition'=>$on + [$p . '_buttons_border_type!'=>'none'],'selectors'=>[$hovered=>'border-color: {{VALUE}};']]);
                $kit->add_control($p . '_buttons_hover_transition_duration', ['label'=>'Duración de la transición (s)','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>0,'max'=>3,'step'=>0.1]],'selectors'=>[$button=>'transition-duration: {{SIZE}}s;']] + $c);
                $kit->end_controls_tab();
                $kit->end_controls_tabs();
                $kit->add_control($p . '_buttons_border_type', ['label'=>'Tipo de borde','type'=>Controls_Manager::SELECT,'options'=>self::BORDER_TYPES,'separator'=>'before','selectors'=>[$button=>'border-style: {{VALUE}};']] + $c);
                $border = ['condition'=>$on + [$p . '_buttons_border_type!'=>'none']];
                $kit->add_responsive_control($p . '_buttons_border_width', ['label'=>'Grosor del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','em','rem','vw','custom'],'selectors'=>[$button=>'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']] + $border);
                $kit->add_control($p . '_buttons_border_color', ['label'=>'Color del borde','type'=>Controls_Manager::COLOR,'selectors'=>[$button=>'border-color: {{VALUE}};']] + $border);
                $kit->add_responsive_control($p . '_buttons_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'selectors'=>[$button=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']] + $c);
                $kit->add_responsive_control($p . '_buttons_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'selectors'=>[$button=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']] + $c);
            }
            $kit->end_controls_section();
        }
    }

    /** The store pages chosen in the kit become WooCommerce's pages. */
    public function on_save($data) {
        $settings = $data['settings'] ?? null;
        if (!is_array($settings) || !current_user_can('manage_woocommerce')) { return; }
        foreach (self::PAGES as $key => $option) {
            if (!array_key_exists($key, $settings)) { continue; }
            $id = absint($settings[$key]);
            if ($id && get_post_type($id) !== 'page') { continue; }
            update_option($option, $id ? (string) $id : '');
        }
    }
}
