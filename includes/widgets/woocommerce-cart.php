<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

if (!defined('ABSPATH')) { exit; }

/**
 * Menu cart with Elementor Pro's keys: a toggle button (icon, count, subtotal) that opens a side panel or a
 * dropdown with the cart contents. The list fires WooCommerce's mini cart hooks, removing an item uses
 * WooCommerce's own AJAX (wc-add-to-cart) and the counts refresh through cart fragments.
 */
final class Woo_Menu_Cart extends Woo_Single_Element {
    public const PRO = 'woocommerce-menu-cart';
    public const PRO_CATEGORY = 'woocommerce-elements';
    protected const LEGACY = ['woo_color'=>'toggle_button_text_color','woo_hover'=>'toggle_button_hover_text_color','woo_typography'=>'toggle_button_typography'];
    private const ICONS = ['cart-light'=>'Carrito claro','cart-medium'=>'Carrito medio','cart-solid'=>'Carrito sólido','basket-light'=>'Cesta clara','basket-medium'=>'Cesta media','basket-solid'=>'Cesta sólida','bag-light'=>'Bolsa clara','bag-medium'=>'Bolsa media','bag-solid'=>'Bolsa sólida','custom'=>'Personalizado'];
    private const BORDERS = [''=>'Por defecto','none'=>'Ninguno','solid'=>'Continuo','double'=>'Doble','dotted'=>'Punteado','dashed'=>'Discontinuo','groove'=>'Ranurado'];

    public function get_title(): string { return 'Carrito del menú'; }
    public function get_icon(): string { return 'eicon-cart'; }
    public function get_keywords(): array { return ['woocommerce', 'carrito', 'cart', 'cesta', 'menú']; }
    public function get_script_depends(): array { return ['obx-widgets']; }
    public function get_style_depends(): array { return ['obx-frontend', 'elementor-icons']; }
    protected function is_dynamic_content(): bool { return true; }

    public static function wants_upgrade(string $name, array $settings): bool {
        return $name === self::PRO && (parent::wants_upgrade($name, $settings) || array_key_exists('cart_icon', $settings) || in_array($settings['items_indicator'] ?? null, ['yes', ''], true));
    }
    protected static function upgrade_settings(array $settings): array {
        if (in_array($settings['items_indicator'] ?? null, ['yes', ''], true)) { $settings['items_indicator'] = $settings['items_indicator'] === 'yes' ? 'bubble' : 'none'; }
        if (array_key_exists('cart_icon', $settings)) {
            if (!empty($settings['cart_icon']['value']) && !isset($settings['menu_icon_svg'])) { $settings['icon'] = 'custom'; $settings['menu_icon_svg'] = $settings['cart_icon']; }
            unset($settings['cart_icon']);
        }
        if (isset($settings['woo_color']) && !isset($settings['toggle_button_icon_color'])) { $settings['toggle_button_icon_color'] = $settings['woo_color']; }
        return parent::upgrade_settings($settings);
    }

    protected function register_controls(): void {
        $this->section('section_menu_icon_content', 'Menú', false);
        $this->add_control('icon', ['label'=>'Icono','type'=>Controls_Manager::SELECT,'default'=>'cart-medium','options'=>self::ICONS]);
        $this->add_control('menu_icon_svg', ['label'=>'Icono personalizado','type'=>Controls_Manager::ICONS,'default'=>['value'=>'fas fa-shopping-cart','library'=>'fa-solid'],'skin'=>'inline','label_block'=>false,'condition'=>['icon'=>'custom']]);
        $this->add_control('items_indicator', ['label'=>'Indicador de artículos','type'=>Controls_Manager::SELECT,'default'=>'bubble','options'=>['none'=>'Ninguno','bubble'=>'Burbuja','plain'=>'Texto']]);
        $this->add_control('hide_empty_indicator', ['label'=>'Ocultar si está vacío','type'=>Controls_Manager::SWITCHER,'return_value'=>'hide','condition'=>['items_indicator!'=>'none']]);
        $this->add_control('show_subtotal', ['label'=>'Subtotal','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes']);
        $this->add_responsive_control('alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),
            'selectors_dictionary'=>['left'=>'flex-start','center'=>'center','right'=>'flex-end'],'selectors'=>['{{WRAPPER}} .obx-menu-cart__toggle'=>'justify-content: {{VALUE}};']]);
        $this->end_controls_section();

        $this->section('section_cart', 'Carrito', false);
        $this->add_control('cart_type', ['label'=>'Tipo de carrito','type'=>Controls_Manager::SELECT,'default'=>'side-cart','options'=>['side-cart'=>'Lateral','mini-cart'=>'Desplegable']]);
        $this->add_control('open_cart', ['label'=>'Abrir el carrito','type'=>Controls_Manager::SELECT,'default'=>'click','options'=>['click'=>'Al hacer clic','mouseover'=>'Al pasar el ratón']]);
        $this->add_responsive_control('side_cart_alignment', ['label'=>'Posición','type'=>Controls_Manager::CHOOSE,'options'=>['start'=>['title'=>'Inicio','icon'=>'eicon-h-align-left'],'end'=>['title'=>'Final','icon'=>'eicon-h-align-right']],'condition'=>['cart_type'=>'side-cart'],
            'selectors_dictionary'=>['start'=>'--obx-mc-side-start: 0; --obx-mc-side-end: auto; --obx-mc-side-shift: -100%','end'=>'--obx-mc-side-start: auto; --obx-mc-side-end: 0; --obx-mc-side-shift: 100%'],'selectors'=>['{{WRAPPER}} .obx-menu-cart'=>'{{VALUE}};']]);
        $this->add_responsive_control('mini_cart_alignment', ['label'=>'Posición','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(),'condition'=>['cart_type'=>'mini-cart'],
            'selectors_dictionary'=>['start'=>'--obx-mc-drop-start: 0; --obx-mc-drop-end: auto; --obx-mc-drop-shift: 0','center'=>'--obx-mc-drop-start: 50%; --obx-mc-drop-end: auto; --obx-mc-drop-shift: -50%','end'=>'--obx-mc-drop-start: auto; --obx-mc-drop-end: 0; --obx-mc-drop-shift: 0'],'selectors'=>['{{WRAPPER}} .obx-menu-cart'=>'{{VALUE}};']]);
        $this->add_responsive_control('mini_cart_spacing', ['label'=>'Distancia','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','custom'],'range'=>['px'=>['min'=>0,'max'=>100]],'condition'=>['cart_type'=>'mini-cart'],'selectors'=>['{{WRAPPER}} .obx-menu-cart'=>'--obx-mc-drop-gap: {{SIZE}}{{UNIT}};']]);
        $this->add_control('close_cart_button_show', ['label'=>'Botón de cerrar','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes','separator'=>'before']);
        $this->add_control('close_cart_icon_svg', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'default'=>['value'=>'','library'=>''],'skin'=>'inline','label_block'=>false,'condition'=>['close_cart_button_show!'=>'']]);
        $this->add_responsive_control('close_cart_button_alignment', ['label'=>'Posición','type'=>Controls_Manager::CHOOSE,'options'=>['start'=>['title'=>'Inicio','icon'=>'eicon-h-align-left'],'end'=>['title'=>'Final','icon'=>'eicon-h-align-right']],'condition'=>['close_cart_button_show!'=>''],
            'selectors_dictionary'=>['start'=>'flex-start','end'=>'flex-end'],'selectors'=>['{{WRAPPER}} .obx-menu-cart__close'=>'align-self: {{VALUE}};']]);
        $this->add_control('show_remove_icon', ['label'=>'Botón de quitar','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes','separator'=>'before']);
        $this->add_responsive_control('remove_item_button_position', ['label'=>'Posición','type'=>Controls_Manager::CHOOSE,'options'=>['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'middle'=>['title'=>'En medio','icon'=>'eicon-v-align-middle'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']],'condition'=>['show_remove_icon!'=>''],
            'selectors_dictionary'=>['top'=>'start','middle'=>'center','bottom'=>'end'],'selectors'=>['{{WRAPPER}} .obx-menu-cart__product-remove'=>'align-self: {{VALUE}};']]);
        $this->add_responsive_control('price_quantity_position', ['label'=>'Precio y cantidad','type'=>Controls_Manager::CHOOSE,'options'=>['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']],
            'selectors_dictionary'=>['top'=>'--obx-mc-price-row: 1; --obx-mc-name-row: 2','bottom'=>'--obx-mc-price-row: 2; --obx-mc-name-row: 1'],'selectors'=>['{{WRAPPER}} .obx-menu-cart__product'=>'{{VALUE}};']]);
        $this->add_control('show_divider', ['label'=>'Separadores','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes','separator'=>'before']);
        $this->add_control('view_cart_button_show', ['label'=>'Botón «Ver carrito»','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes','separator'=>'before']);
        $buttons = ['start'=>['title'=>'Inicio','icon'=>'eicon-h-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-h-align-center'],'end'=>['title'=>'Final','icon'=>'eicon-h-align-right'],'justify'=>['title'=>'Justificado','icon'=>'eicon-h-align-stretch']];
        $one = ['start'=>'flex-start; --obx-mc-button-grow: 0','center'=>'center; --obx-mc-button-grow: 0','end'=>'flex-end; --obx-mc-button-grow: 0','justify'=>'stretch; --obx-mc-button-grow: 1'];
        $this->add_responsive_control('view_cart_button_alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>$buttons,'condition'=>['view_cart_button_show!'=>'','checkout_button_show'=>''],'selectors_dictionary'=>$one,'selectors'=>['{{WRAPPER}} .obx-menu-cart__footer-buttons'=>'--obx-mc-buttons-justify: {{VALUE}};']]);
        $this->add_control('checkout_button_show', ['label'=>'Botón «Finalizar compra»','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes']);
        $this->add_responsive_control('checkout_button_alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>$buttons,'condition'=>['checkout_button_show!'=>'','view_cart_button_show'=>''],'selectors_dictionary'=>$one,'selectors'=>['{{WRAPPER}} .obx-menu-cart__footer-buttons'=>'--obx-mc-buttons-justify: {{VALUE}};']]);
        $this->add_control('checkout_button_display', ['type'=>Controls_Manager::HIDDEN,'default'=>'--cart-footer-buttons-alignment-display: none;','condition'=>['checkout_button_show'=>'','view_cart_button_show'=>''],'selectors'=>['{{WRAPPER}}'=>'{{VALUE}}']]);
        $this->add_control('buttons_position', ['label'=>'Posición de los botones','type'=>Controls_Manager::CHOOSE,'options'=>['top'=>['title'=>'Tras los productos','icon'=>'eicon-v-align-top'],'bottom'=>['title'=>'Al final del panel','icon'=>'eicon-v-align-bottom']],'condition'=>['cart_type'=>'side-cart'],
            'selectors_dictionary'=>['top'=>'0','bottom'=>'auto'],'selectors'=>['{{WRAPPER}} .obx-menu-cart--side-cart .obx-menu-cart__footer-buttons'=>'margin-block-start: {{VALUE}};']]);
        $this->end_controls_section();

        $this->section('section_additional_options', 'Opciones adicionales', false);
        $this->add_control('automatically_open_cart', ['label'=>'Abrir al añadir un producto','type'=>Controls_Manager::SWITCHER,'default'=>'no','return_value'=>'yes']);
        $this->add_control('automatically_update_cart', ['label'=>'Actualizar sin recargar','description'=>'Al quitar un producto, el carrito se actualiza sin recargar la página.','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes']);
        $this->end_controls_section();

        $this->toggle_style();
        $this->cart_style();
        $this->products_style();
        $this->buttons_style();

        $this->section('section_style_messages', 'Mensajes');
        $this->typo('cart_empty_message_typography', '{{WRAPPER}} .woocommerce-mini-cart__empty-message');
        $this->paint('empty_message_color', 'Color', '{{WRAPPER}} .woocommerce-mini-cart__empty-message');
        $this->add_responsive_control('empty_message_alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options() + ['justify'=>['title'=>'Justificado','icon'=>'eicon-text-align-justify']],'selectors'=>['{{WRAPPER}} .woocommerce-mini-cart__empty-message'=>'text-align: {{VALUE}};']]);
        $this->end_controls_section();
    }

    private function toggle_style(): void {
        $button = '{{WRAPPER}} .obx-menu-cart__toggle-button';
        $text = ['show_subtotal!'=>''];
        $this->section('section_toggle_style', 'Botón del menú');
        $this->start_controls_tabs('toggle_button_colors');
        foreach (['normal'=>['Normal', '', ''], 'hover'=>['Al pasar el ratón', ':hover', 'hover_']] as $state => [$label, $pseudo, $infix]) {
            $this->start_controls_tab('toggle_button_' . $state . '_colors', ['label'=>$label]);
            $this->paint('toggle_button_' . $infix . 'text_color', 'Color del texto', $button . $pseudo . ' .obx-menu-cart__subtotal', 'color', ['condition'=>$text]);
            $this->paint('toggle_button_' . $infix . 'icon_color', 'Color del icono', $button . $pseudo . ' .obx-menu-cart__icon');
            $this->paint('toggle_button_' . $infix . 'background_color', 'Fondo', $button . $pseudo, 'background-color');
            $this->paint('toggle_button_' . $infix . 'border_color', 'Color del borde', $button . $pseudo, 'border-color');
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'toggle_button_' . $state . '_box_shadow','selector'=>$button . $pseudo]);
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->add_control('toggle_button_border_width', ['label'=>'Grosor del borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vw','custom'],'range'=>['px'=>['min'=>0,'max'=>20]],'separator'=>'before','selectors'=>[$button=>'border-width: {{SIZE}}{{UNIT}};']]);
        $this->add_control('toggle_button_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','custom'],'selectors'=>[$button=>'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->typo('toggle_button_typography', $button . ' .obx-menu-cart__subtotal', ['condition'=>$text]);
        $this->add_control('heading_toggle_icon_style', ['label'=>'Icono','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_responsive_control('toggle_icon_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','custom'],'range'=>['px'=>['min'=>0,'max'=>50]],'selectors'=>['{{WRAPPER}} .obx-menu-cart'=>'--obx-mc-icon-size: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('toggle_icon_spacing', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vw','custom'],'range'=>['px'=>['min'=>0,'max'=>50]],'condition'=>$text,'selectors'=>[$button=>'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_control('toggle_icon_position', ['label'=>'Posición del icono','type'=>Controls_Manager::CHOOSE,'options'=>['row-reverse'=>['title'=>'Antes','icon'=>'eicon-h-align-left'],'row'=>['title'=>'Después','icon'=>'eicon-h-align-right']],'condition'=>$text,'selectors'=>[$button=>'flex-direction: {{VALUE}};']]);
        $this->dims('toggle_button_padding', 'Relleno', $button, 'padding', ['separator'=>'before']);
        $this->add_control('heading_items_indicator_style', ['label'=>'Indicador de artículos','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['items_indicator!'=>'none']]);
        $this->paint('items_indicator_text_color', 'Color del texto', '{{WRAPPER}} .obx-menu-cart__count', 'color', ['condition'=>['items_indicator!'=>'none']]);
        $this->paint('items_indicator_background_color', 'Fondo', '{{WRAPPER}} .obx-menu-cart__count', 'background-color', ['condition'=>['items_indicator'=>'bubble']]);
        $this->add_control('items_indicator_distance', ['label'=>'Distancia','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','custom'],'range'=>['em'=>['min'=>0,'max'=>4,'step'=>0.1]],'condition'=>['items_indicator'=>'bubble'],'selectors'=>['{{WRAPPER}} .obx-menu-cart'=>'--obx-mc-bubble-distance: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    private function cart_style(): void {
        $main = '{{WRAPPER}} .obx-menu-cart__main';
        $this->section('section_cart_style', 'Carrito');
        $this->paint('background_color', 'Fondo', $main, 'background-color');
        $this->add_control('border_type', ['label'=>'Tipo de borde','type'=>Controls_Manager::SELECT,'default'=>'none','options'=>array_slice(self::BORDERS, 1, null, true),'selectors'=>[$main=>'border-style: {{VALUE}};']]);
        $this->add_responsive_control('border_width', ['label'=>'Grosor','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'condition'=>['border_type!'=>'none'],'selectors'=>[$main=>'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->paint('border_color', 'Color del borde', $main, 'border-color', ['condition'=>['border_type!'=>'none']]);
        $this->dims('border_radius', 'Radio del borde', $main, 'border-radius');
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'cart_box_shadow','selector'=>$main]);
        $this->dims('cart_padding', 'Relleno', $main, 'padding');
        $this->add_control('heading_close_cart_style', ['label'=>'Botón de cerrar','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['close_cart_button_show!'=>'']]);
        $this->add_responsive_control('close_cart_icon_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','custom'],'condition'=>['close_cart_button_show!'=>''],'selectors'=>['{{WRAPPER}} .obx-menu-cart__close'=>'font-size: {{SIZE}}{{UNIT}};']]);
        $this->start_controls_tabs('cart_icon_style', ['condition'=>['close_cart_button_show!'=>'']]);
        $this->start_controls_tab('cart_icon_normal', ['label'=>'Normal']);
        $this->paint('close_cart_icon_color', 'Color', '{{WRAPPER}} .obx-menu-cart__close', 'color', ['condition'=>['close_cart_button_show!'=>'']]);
        $this->end_controls_tab();
        $this->start_controls_tab('cart_icon_hover', ['label'=>'Al pasar el ratón']);
        $this->paint('close_cart_icon_hover_color', 'Color', '{{WRAPPER}} .obx-menu-cart__close:hover', 'color', ['condition'=>['close_cart_button_show!'=>'']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_control('heading_remove_item_style', ['label'=>'Botón de quitar','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['show_remove_icon!'=>'']]);
        $this->add_responsive_control('remove_item_button_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'condition'=>['show_remove_icon!'=>''],'selectors'=>['{{WRAPPER}} .obx-menu-cart__product-remove'=>'font-size: {{SIZE}}{{UNIT}};']]);
        $this->start_controls_tabs('cart_remove_item_button_style', ['condition'=>['show_remove_icon!'=>'']]);
        $this->start_controls_tab('cart_remove_item_button_normal', ['label'=>'Normal']);
        $this->paint('remove_item_button_color', 'Color', '{{WRAPPER}} .obx-menu-cart__product-remove a', 'color', ['condition'=>['show_remove_icon!'=>'']]);
        $this->end_controls_tab();
        $this->start_controls_tab('cart_remove_item_button_hover', ['label'=>'Al pasar el ratón']);
        $this->paint('remove_item_button_hover_color', 'Color', '{{WRAPPER}} .obx-menu-cart__product-remove a:hover', 'color', ['condition'=>['show_remove_icon!'=>'']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $total = '{{WRAPPER}} .obx-menu-cart__subtotal-row';
        $this->add_control('heading_subtotal_style', ['label'=>'Subtotal','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->paint('subtotal_color', 'Color', $total);
        $this->typo('subtotal_typography', $total . ', ' . $total . ' strong');
        $this->add_responsive_control('subtotal_alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),'selectors'=>[$total=>'text-align: {{VALUE}};']]);
        $this->add_control('subtotal_divider_style', ['label'=>'Separador','type'=>Controls_Manager::SELECT,'options'=>self::BORDERS,'selectors'=>[$total=>'border-top-style: {{VALUE}}; border-bottom-style: {{VALUE}};']]);
        $this->add_responsive_control('subtotal_divider_width', ['label'=>'Grosor','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'allowed_dimensions'=>['top','bottom'],'selectors'=>[$total=>'border-top-width: {{TOP}}{{UNIT}}; border-bottom-width: {{BOTTOM}}{{UNIT}};']]);
        $this->paint('subtotal_divider_color', 'Color del separador', $total, 'border-color');
        $this->end_controls_section();
    }

    private function products_style(): void {
        $product = '{{WRAPPER}} .obx-menu-cart__product';
        $this->section('section_product_tabs_style', 'Productos');
        $this->add_control('heading_product_title_style', ['label'=>'Nombre del producto','type'=>Controls_Manager::HEADING]);
        $this->typo('product_title_typography', $product . '-name, ' . $product . '-name a');
        $this->start_controls_tabs('product_title_colors');
        $this->start_controls_tab('product_title_normal', ['label'=>'Normal']);
        $this->paint('product_title_color', 'Color', $product . '-name, ' . $product . '-name a');
        $this->end_controls_tab();
        $this->start_controls_tab('product_title_hover', ['label'=>'Al pasar el ratón']);
        $this->paint('product_title_hover_color', 'Color', $product . '-name a:hover');
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_control('heading_product_variations_style', ['label'=>'Variaciones','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->paint('product_variations_color', 'Color', $product . '-name .variation');
        $this->typo('product_variations_typography', $product . '-name .variation');
        $this->add_control('heading_product_price_style', ['label'=>'Precio','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->paint('product_price_color', 'Color', $product . '-price .amount');
        $this->typo('product_price_typography', $product . '-price .amount');
        $this->add_control('heading_product_quantity_style', ['label'=>'Cantidad','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->paint('product_quantity_color', 'Color', $product . '-price');
        $this->typo('product_quantity_typography', $product . '-price');
        $this->add_control('heading_product_divider_style', ['label'=>'Separadores','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control('divider_style', ['label'=>'Estilo','type'=>Controls_Manager::SELECT,'options'=>self::BORDERS,'selectors'=>['{{WRAPPER}} .obx-menu-cart'=>'--obx-mc-divider-style: {{VALUE}};']]);
        $this->paint('divider_color', 'Color', '{{WRAPPER}} .obx-menu-cart', '--obx-mc-divider-color');
        $this->add_responsive_control('divider_width', ['label'=>'Grosor','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['min'=>0,'max'=>10]],'selectors'=>['{{WRAPPER}} .obx-menu-cart'=>'--obx-mc-divider-width: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('divider_gap', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['min'=>0,'max'=>50]],'selectors'=>['{{WRAPPER}} .obx-menu-cart'=>'--obx-mc-divider-gap: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    private function buttons_style(): void {
        $both = ['view_cart_button_show!'=>'','checkout_button_show!'=>''];
        $all = '{{WRAPPER}} .obx-menu-cart__footer-buttons .button';
        $this->section('section_style_buttons', 'Botones');
        $this->add_control('buttons_layout', ['label'=>'Disposición','type'=>Controls_Manager::SELECT,'default'=>'inline','options'=>['inline'=>'En línea','stacked'=>'Apilados'],'condition'=>$both,
            'selectors_dictionary'=>['inline'=>'row','stacked'=>'column'],'selectors'=>['{{WRAPPER}} .obx-menu-cart__footer-buttons'=>'flex-direction: {{VALUE}};']]);
        $this->add_responsive_control('space_between_buttons', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['min'=>0,'max'=>50]],'condition'=>$both,'selectors'=>['{{WRAPPER}} .obx-menu-cart__footer-buttons'=>'gap: {{SIZE}}{{UNIT}};']]);
        $this->typo('product_buttons_typography', $all);
        $this->add_control('button_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','custom'],'selectors'=>[$all=>'border-radius: {{SIZE}}{{UNIT}};']]);
        foreach (['view_cart'=>['«Ver carrito»', ':not(.checkout)', 'view_cart_button_show', 'view_cart_buttons_typography', 'view_cart_button_text_colors', 'view_cart_button', 'view_cart_border', 'view_cart_button'], 'checkout'=>['«Finalizar compra»', '.checkout', 'checkout_button_show', 'cart_checkout_button_typography', 'cart_checkout_button_text_colors', 'checkout_button', 'checkout_border', 'view_checkout_button']] as $kind => [$label, $filter, $show, $typo, $tabs, $color, $border, $box]) {
            $on = [$show . '!'=>''];
            $target = '{{WRAPPER}} .obx-menu-cart__footer-buttons .button' . $filter;
            $this->add_control('heading_' . $kind . '_button_style', ['label'=>'Botón ' . $label,'type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>$on]);
            $this->typo($typo, $target, ['condition'=>$on]);
            $this->start_controls_tabs($tabs, ['condition'=>$on]);
            $this->start_controls_tab($tabs . '_normal', ['label'=>'Normal']);
            $this->paint($color . '_text_color', 'Color del texto', $target, 'color', ['condition'=>$on]);
            $this->paint($color . '_background_color', 'Fondo', $target, 'background-color', ['condition'=>$on]);
            $this->end_controls_tab();
            $this->start_controls_tab($tabs . '_hover', ['label'=>'Al pasar el ratón']);
            $this->paint($color . '_hover_text_color', 'Color del texto', $target . ':hover', 'color', ['condition'=>$on]);
            $this->paint($color . '_hover_background', 'Fondo', $target . ':hover', 'background-color', ['condition'=>$on]);
            $this->paint($color . '_border_hover_color', 'Color del borde', $target . ':hover', 'border-color', ['condition'=>$on + [$border . '_border!'=>'']]);
            $this->end_controls_tab();
            $this->end_controls_tabs();
            $this->add_group_control(Group_Control_Border::get_type(), ['name'=>$border,'selector'=>$target,'condition'=>$on,'separator'=>'before']);
            $this->dims($box . '_border_radius', 'Radio del borde', $target, 'border-radius', ['condition'=>$on]);
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>$box . '_box_shadow','selector'=>$target,'condition'=>$on]);
            $this->dims($box . '_padding', 'Relleno', $target, 'padding', ['condition'=>$on]);
        }
        $this->end_controls_section();
    }

    protected function render(): void {
        if (!function_exists('WC')) { return; }
        if (!WC()->cart && function_exists('wc_load_cart')) { wc_load_cart(); }
        $cart = WC()->cart;
        if (!$cart) { return; }
        $s = $this->get_settings_for_display();
        $editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
        $live = ($s['automatically_update_cart'] ?? 'yes') === 'yes';
        if (!$editor) {
            foreach ($live ? ['wc-cart-fragments', 'wc-add-to-cart'] : ['wc-cart-fragments'] as $handle) { if (wp_script_is($handle, 'registered')) { wp_enqueue_script($handle); } }
        }
        $type = ($s['cart_type'] ?? 'side-cart') === 'mini-cart' ? 'mini-cart' : 'side-cart';
        $indicator = in_array($s['items_indicator'] ?? 'bubble', ['none', 'bubble', 'plain'], true) ? $s['items_indicator'] : 'bubble';
        $classes = ['obx-menu-cart', 'obx-menu-cart--' . $type, 'obx-menu-cart--indicator-' . $indicator];
        foreach (['show_subtotal'=>'subtotal', 'show_remove_icon'=>'remove', 'show_divider'=>'divider', 'view_cart_button_show'=>'view-cart', 'checkout_button_show'=>'checkout', 'close_cart_button_show'=>'close'] as $key => $flag) {
            if (($s[$key] ?? 'yes') === 'yes') { $classes[] = 'obx-menu-cart--' . $flag; }
        }
        if (($s['hide_empty_indicator'] ?? '') === 'hide') { $classes[] = 'obx-menu-cart--hide-empty'; }
        $panel = 'obx-menu-cart-' . $this->get_id();
        $config = ['open'=>($s['open_cart'] ?? 'click') === 'mouseover' ? 'mouseover' : 'click', 'autoOpen'=>($s['automatically_open_cart'] ?? '') === 'yes', 'live'=>$live];

        echo '<div class="' . esc_attr(implode(' ', $classes)) . '" data-obx-menu-cart="' . esc_attr((string) wp_json_encode($config)) . '">';
        echo '<div class="obx-menu-cart__toggle">';
        echo '<a class="obx-menu-cart__toggle-button" href="' . esc_url(wc_get_cart_url()) . '" role="button" aria-expanded="false" aria-controls="' . esc_attr($panel) . '" aria-label="' . esc_attr__('Carrito', 'oldbear-elementor-expansion') . '">';
        if (($s['show_subtotal'] ?? 'yes') === 'yes') { echo self::subtotal_html(); } // phpcs:ignore WordPress.Security.EscapeOutput -- built from WooCommerce price HTML.
        echo '<span class="obx-menu-cart__icon" aria-hidden="true">';
        if (($s['icon'] ?? 'cart-medium') === 'custom') { \Elementor\Icons_Manager::render_icon((array) ($s['menu_icon_svg'] ?? []), ['aria-hidden'=>'true']); }
        else { $icon = array_key_exists($s['icon'] ?? '', self::ICONS) ? $s['icon'] : 'cart-medium'; echo '<i class="eicon-' . esc_attr($icon) . '"></i>'; }
        if ($indicator !== 'none') { echo self::count_html(); } // phpcs:ignore WordPress.Security.EscapeOutput
        echo '</span></a></div>';

        echo '<div class="obx-menu-cart__container" id="' . esc_attr($panel) . '" aria-hidden="true">';
        echo '<div class="obx-menu-cart__main" role="dialog" aria-label="' . esc_attr__('Carrito', 'oldbear-elementor-expansion') . '">';
        if (($s['close_cart_button_show'] ?? 'yes') === 'yes') {
            echo '<button type="button" class="obx-menu-cart__close" aria-label="' . esc_attr__('Cerrar el carrito', 'oldbear-elementor-expansion') . '">';
            if (!empty($s['close_cart_icon_svg']['value'])) { \Elementor\Icons_Manager::render_icon((array) $s['close_cart_icon_svg'], ['aria-hidden'=>'true']); } else { echo '<span class="obx-menu-cart__close-x" aria-hidden="true"></span>'; }
            echo '</button>';
        }
        echo self::content_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce output, escaped per item.
        echo '</div></div></div>';
    }

    public static function count_html(): string {
        $count = WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
        return '<span class="obx-menu-cart__count" data-counter="' . esc_attr((string) $count) . '">' . esc_html((string) $count) . '</span>';
    }

    public static function subtotal_html(): string {
        $cart = WC()->cart;
        return '<span class="obx-menu-cart__subtotal">' . ($cart ? wp_kses_post($cart->get_cart_subtotal()) : '') . '</span>';
    }

    /** Cart contents with WooCommerce's mini cart hooks and filters; the same HTML refreshes through fragments. */
    public static function content_html(): string {
        $cart = WC()->cart;
        ob_start();
        echo '<div class="obx-menu-cart__content">';
        do_action('woocommerce_before_mini_cart');
        if (!$cart || $cart->is_empty()) {
            echo '<div class="woocommerce-mini-cart__empty-message">' . esc_html__('No products in the cart.', 'woocommerce') . '</div>';
        } else {
            echo '<div class="obx-menu-cart__products woocommerce-mini-cart cart_list product_list_widget">';
            do_action('woocommerce_before_mini_cart_contents');
            foreach ($cart->get_cart() as $key => $item) {
                $product = apply_filters('woocommerce_cart_item_product', $item['data'], $item, $key);
                $product_id = apply_filters('woocommerce_cart_item_product_id', $item['product_id'], $item, $key);
                if (!$product || !$product->exists() || $item['quantity'] <= 0 || !apply_filters('woocommerce_widget_cart_item_visible', true, $item, $key)) { continue; }
                $name = apply_filters('woocommerce_cart_item_name', $product->get_name(), $item, $key);
                $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $product->get_image(), $item, $key);
                $price = apply_filters('woocommerce_cart_item_price', $cart->get_product_price($product), $item, $key);
                $link = apply_filters('woocommerce_cart_item_permalink', $product->is_visible() ? $product->get_permalink($item) : '', $item, $key);
                $class = apply_filters('woocommerce_mini_cart_item_class', 'mini_cart_item', $item, $key);
                echo '<div class="obx-menu-cart__product woocommerce-mini-cart-item ' . esc_attr($class) . '">';
                echo '<div class="obx-menu-cart__product-image">' . ($link ? '<a href="' . esc_url($link) . '">' . $thumbnail . '</a>' : $thumbnail) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- core image HTML.
                echo '<div class="obx-menu-cart__product-name">' . ($link ? '<a href="' . esc_url($link) . '">' . wp_kses_post($name) . '</a>' : wp_kses_post($name)) . wc_get_formatted_cart_item_data($item) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce item data.
                echo '<div class="obx-menu-cart__product-price">' . apply_filters('woocommerce_widget_cart_item_quantity', '<span class="quantity">' . sprintf('%s &times; %s', (int) $item['quantity'], $price) . '</span>', $item, $key) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce price HTML.
                echo '<div class="obx-menu-cart__product-remove">' . apply_filters('woocommerce_cart_item_remove_link', sprintf(
                    '<a href="%s" class="remove remove_from_cart_button" aria-label="%s" data-product_id="%s" data-cart_item_key="%s" data-product_sku="%s">&times;</a>',
                    esc_url(wc_get_cart_remove_url($key)),
                    /* translators: %s: product name */
                    esc_attr(sprintf(__('Remove %s from cart', 'woocommerce'), wp_strip_all_tags($name))),
                    esc_attr((string) $product_id), esc_attr($key), esc_attr($product->get_sku())
                ), $key) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
                echo '</div>';
            }
            do_action('woocommerce_mini_cart_contents');
            echo '</div>';
            echo '<div class="obx-menu-cart__subtotal-row woocommerce-mini-cart__total total">';
            do_action('woocommerce_widget_shopping_cart_total');
            echo '</div>';
            do_action('woocommerce_widget_shopping_cart_before_buttons');
            echo '<div class="obx-menu-cart__footer-buttons woocommerce-mini-cart__buttons buttons">';
            do_action('woocommerce_widget_shopping_cart_buttons');
            echo '</div>';
            do_action('woocommerce_widget_shopping_cart_after_buttons');
        }
        do_action('woocommerce_after_mini_cart');
        echo '</div>';
        return (string) ob_get_clean();
    }

    /** WooCommerce refreshes these parts of every menu cart after adding or removing products. */
    public static function fragments(array $fragments): array {
        if (!function_exists('WC') || !WC()->cart) { return $fragments; }
        $fragments['span.obx-menu-cart__count'] = self::count_html();
        $fragments['span.obx-menu-cart__subtotal'] = self::subtotal_html();
        $fragments['div.obx-menu-cart__content'] = self::content_html();
        return $fragments;
    }
}
