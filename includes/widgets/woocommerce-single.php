<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;
use OldBearExpansion\Context;

if (!defined('ABSPATH')) { exit; }

/**
 * Single product and shop widgets with Elementor Pro's style keys. They print WooCommerce's own markup
 * (templates and template functions), so theme and plugin hooks keep working.
 */
abstract class Woo_Single_Element extends Woo_Element {
    /** Keys of versions before 0.10.0 (one generic «Contenido» style) → current keys. */
    protected const LEGACY = [];

    public static function wants_upgrade(string $name, array $settings): bool {
        if ($name !== static::PRO || !static::LEGACY) { return false; }
        foreach (array_keys($settings) as $key) { if (is_string($key) && str_starts_with($key, 'woo_')) { return true; } }
        return false;
    }

    protected static function upgrade_settings(array $settings): array {
        foreach (static::LEGACY as $old => $new) {
            if ($old === 'woo_typography') { $settings = self::rename_prefix($settings, 'woo_typography_', $new . '_'); continue; }
            foreach (['', '_widescreen', '_laptop', '_tablet_extra', '_tablet', '_mobile_extra', '_mobile'] as $device) {
                if (!array_key_exists($old . $device, $settings)) { continue; }
                if (!array_key_exists($new . $device, $settings)) { $settings[$new . $device] = $settings[$old . $device]; }
                unset($settings[$old . $device]);
            }
        }
        foreach (array_keys($settings) as $key) { if (is_string($key) && str_starts_with($key, 'woo_')) { unset($settings[$key]); } }
        return $settings;
    }

    protected function paint(string $key, string $label, string $selector, string $property = 'color', array $extra = []): void {
        $this->add_control($key, ['label'=>$label,'type'=>Controls_Manager::COLOR,'selectors'=>[$selector=>$property . ': {{VALUE}};']] + $extra);
    }

    protected function typo(string $name, string $selector, array $extra = []): void {
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>$name,'selector'=>$selector] + $extra);
    }

    protected function dims(string $key, string $label, string $selector, string $property, array $extra = []): void {
        $this->add_responsive_control($key, ['label'=>$label,'type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem'],
            'selectors'=>[$selector=>$property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']] + $extra);
    }

    protected function size(string $key, string $label, string $selector, string $declaration, array $extra = [], array $units = ['px','em','rem']): void {
        $this->add_responsive_control($key, ['label'=>$label,'type'=>Controls_Manager::SLIDER,'size_units'=>$units,'range'=>['px'=>['min'=>0,'max'=>100]],'selectors'=>[$selector=>$declaration]] + $extra);
    }

    protected function seconds(string $key, string $label, string $selector, array $extra = []): void {
        $this->add_control($key, ['label'=>$label,'type'=>Controls_Manager::SLIDER,'size_units'=>['s','ms'],'range'=>['s'=>['min'=>0,'max'=>3,'step'=>0.1],'ms'=>['min'=>0,'max'=>3000,'step'=>50]],
            'selectors'=>[$selector=>'transition-duration: {{SIZE}}{{UNIT}};']] + $extra);
    }

    protected function align(string $key, string $selector, bool $justify = false, array $extra = []): void {
        $this->add_responsive_control($key, ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align($justify),'selectors'=>[$selector=>'text-align: {{VALUE}};']] + $extra);
    }

    protected function section(string $id, string $label, bool $style = true, array $extra = []): void {
        $this->start_controls_section($id, ['label'=>$label] + ($style ? ['tab'=>Controls_Manager::TAB_STYLE] : []) + $extra);
    }
}

final class Woo_Breadcrumbs extends Woo_Single_Element {
    public const PRO = 'woocommerce-breadcrumb';
    public const PRO_CATEGORY = 'woocommerce-elements';
    protected const LEGACY = ['woo_color'=>'text_color','woo_hover'=>'link_color','woo_typography'=>'text_typography'];
    public function get_title(): string { return 'Migas de pan de WooCommerce'; }
    public function get_icon(): string { return 'eicon-product-breadcrumbs'; }
    protected function register_controls(): void {
        $this->section('section_content', 'Migas de pan', false);
        $this->add_control('delimiter', ['label'=>'Separador','type'=>Controls_Manager::TEXT,'default'=>' / ']);
        $this->end_controls_section();
        $this->section('section_product_rating_style', 'Migas de pan');
        $this->paint('text_color', 'Color del texto', '{{WRAPPER}} .woocommerce-breadcrumb');
        $this->paint('link_color', 'Color de los enlaces', '{{WRAPPER}} .woocommerce-breadcrumb > a');
        $this->typo('text_typography', '{{WRAPPER}} .woocommerce-breadcrumb');
        $this->align('alignment', '{{WRAPPER}} .woocommerce-breadcrumb');
        $this->end_controls_section();
    }
    protected function render(): void {
        if (function_exists('woocommerce_breadcrumb')) { woocommerce_breadcrumb(['delimiter'=>esc_html((string) ($this->get_settings_for_display('delimiter') ?: ' / '))]); }
    }
}

final class Woo_Product_Title extends Woo_Single_Element {
    public const PRO = 'woocommerce-product-title';
    protected const LEGACY = ['woo_color'=>'title_color','woo_hover'=>'title_hover_color','woo_typography'=>'typography'];
    public function get_title(): string { return 'Título del producto'; }
    public function get_icon(): string { return 'eicon-product-title'; }
    protected function register_controls(): void {
        $this->section('section_title', 'Título', false);
        // Pro's title is a dynamic tag with the product name; ours always prints the product name.
        $this->add_control('title', ['type'=>Controls_Manager::HIDDEN,'default'=>'']);
        $this->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true],'default'=>['url'=>'']]);
        $this->add_control('size', ['label'=>'Tamaño','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>['default'=>'Por defecto','small'=>'Pequeño','medium'=>'Mediano','large'=>'Grande','xl'=>'XL','xxl'=>'XXL']]);
        $this->add_control('header_size', ['label'=>'Etiqueta HTML','type'=>Controls_Manager::SELECT,'default'=>'h1','options'=>['h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','h6'=>'H6','div'=>'div','span'=>'span','p'=>'p']]);
        $this->end_controls_section();

        $title = '{{WRAPPER}} .elementor-heading-title';
        $this->section('section_title_style', 'Título');
        $this->align('align', '{{WRAPPER}}', true);
        $this->typo('typography', $title);
        $this->add_group_control(Group_Control_Text_Stroke::get_type(), ['name'=>'text_stroke','selector'=>$title]);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'text_shadow','selector'=>$title]);
        $this->add_control('blend_mode', ['label'=>'Modo de fusión','type'=>Controls_Manager::SELECT,'options'=>[''=>'Normal','multiply'=>'Multiplicar','screen'=>'Trama','overlay'=>'Superponer','darken'=>'Oscurecer','lighten'=>'Aclarar','color-dodge'=>'Sobreexponer color','saturation'=>'Saturación','color'=>'Color','difference'=>'Diferencia','exclusion'=>'Exclusión','hue'=>'Tono','luminosity'=>'Luminosidad'],
            'selectors'=>['{{WRAPPER}} .elementor-heading-title'=>'mix-blend-mode: {{VALUE}};'],'separator'=>'none']);
        $this->start_controls_tabs('title_colors');
        $this->start_controls_tab('title_colors_normal', ['label'=>'Normal']);
        $this->paint('title_color', 'Color', $title . ', ' . $title . ' a');
        $this->end_controls_tab();
        $this->start_controls_tab('title_colors_hover', ['label'=>'Al pasar el ratón']);
        $this->paint('title_hover_color', 'Color', $title . ':hover, ' . $title . ' a:hover');
        $this->seconds('title_hover_color_transition_duration', 'Duración de la transición', $title . ', ' . $title . ' a');
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();
    }
    protected function render(): void {
        $this->with_product(function (\WC_Product $product): void {
            $s = $this->get_settings_for_display();
            $tag = \Elementor\Utils::validate_html_tag((string) ($s['header_size'] ?? 'h1'));
            $size = (string) ($s['size'] ?? 'default');
            $class = 'product_title entry-title elementor-heading-title' . ($size !== 'default' && $size !== '' ? ' elementor-size-' . sanitize_html_class($size) : '');
            $text = esc_html($product->get_name());
            if (!empty($s['link']['url'])) { $this->add_link_attributes('url', $s['link']); $text = '<a ' . $this->get_render_attribute_string('url') . '>' . $text . '</a>'; }
            echo '<' . $tag . ' class="' . esc_attr($class) . '">' . $text . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
        });
    }
}

final class Woo_Product_Images extends Woo_Single_Element {
    public const PRO = 'woocommerce-product-images';
    public function get_title(): string { return 'Imágenes del producto'; }
    public function get_icon(): string { return 'eicon-product-images'; }
    protected function register_controls(): void {
        $main = '{{WRAPPER}} .woocommerce-product-gallery .flex-viewport, {{WRAPPER}} .woocommerce-product-gallery > .woocommerce-product-gallery__wrapper';
        $thumbs = '{{WRAPPER}} .woocommerce-product-gallery .flex-control-thumbs img';
        $this->section('section_product_gallery_style', 'Galería');
        $this->add_control('sale_flash', ['label'=>'Etiqueta de oferta','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes']);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'image_border','selector'=>$main,'separator'=>'before']);
        $this->dims('image_border_radius', 'Radio del borde', $main, 'overflow: hidden; border-radius');
        $this->size('spacing', 'Separación', '{{WRAPPER}} .woocommerce-product-gallery .flex-viewport', 'margin-bottom: {{SIZE}}{{UNIT}};');
        $this->add_control('heading_thumbs_style', ['label'=>'Miniaturas','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'thumbs_border','selector'=>$thumbs]);
        $this->dims('thumbs_border_radius', 'Radio del borde', $thumbs, 'border-radius');
        $this->size('spacing_thumbs', 'Separación', '{{WRAPPER}} .obx-product-images', '--obx-thumbs-gap: {{SIZE}}{{UNIT}};');
        $this->end_controls_section();
    }
    protected function render(): void {
        $this->with_product(function (): void {
            $columns = (int) apply_filters('woocommerce_product_thumbnails_columns', 4);
            echo '<div class="obx-product-images" style="--obx-thumbs-columns:' . esc_attr((string) max(1, $columns)) . '">';
            if ($this->get_settings_for_display('sale_flash') === 'yes') { woocommerce_show_product_sale_flash(); }
            woocommerce_show_product_images();
            echo '</div>';
        });
    }
}

final class Woo_Product_Price extends Woo_Single_Element {
    public const PRO = 'woocommerce-product-price';
    protected const LEGACY = ['woo_color'=>'price_color','woo_typography'=>'typography'];
    public function get_title(): string { return 'Precio del producto'; }
    public function get_icon(): string { return 'eicon-product-price'; }
    protected function register_controls(): void {
        $price = '{{WRAPPER}} .price';
        $this->section('section_price_style', 'Precio');
        $this->align('text_align', '{{WRAPPER}}');
        $this->paint('price_color', 'Color', $price . ', ' . $price . ' ins');
        $this->typo('typography', $price);
        $this->add_control('heading_sale_price_style', ['label'=>'Precio anterior','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->paint('sale_price_color', 'Color', $price . ' del, ' . $price . ' del .amount');
        $this->typo('sale_price_typography', $price . ' del');
        $this->add_control('price_block', ['label'=>'Uno debajo del otro','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','prefix_class'=>'elementor-product-price-block-']);
        $this->size('sale_price_spacing', 'Separación', '{{WRAPPER}}', '--obx-sale-price-spacing: {{SIZE}}{{UNIT}};');
        $this->end_controls_section();
    }
    protected function render(): void {
        $this->with_product(function (\WC_Product $product): void { echo '<p class="price">' . wp_kses_post($product->get_price_html()) . '</p>'; });
    }
}

final class Woo_Product_Rating extends Woo_Single_Element {
    public const PRO = 'woocommerce-product-rating';
    protected const LEGACY = ['woo_color'=>'link_color','woo_typography'=>'text_typography'];
    public function get_title(): string { return 'Valoración del producto'; }
    public function get_icon(): string { return 'eicon-product-rating'; }
    protected function register_controls(): void {
        $this->section('section_product_rating_style', 'Valoración');
        $this->paint('star_color', 'Color de las estrellas', '{{WRAPPER}} .star-rating, {{WRAPPER}} .star-rating span::before');
        $this->paint('empty_star_color', 'Color de las estrellas vacías', '{{WRAPPER}} .star-rating::before');
        $this->paint('link_color', 'Color del enlace', '{{WRAPPER}} .woocommerce-review-link');
        $this->typo('text_typography', '{{WRAPPER}} .woocommerce-review-link');
        $this->add_responsive_control('star_size', ['label'=>'Tamaño de las estrellas','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em'],'default'=>['unit'=>'em'],'range'=>['em'=>['min'=>0,'max'=>4,'step'=>0.1]],'selectors'=>['{{WRAPPER}} .star-rating'=>'font-size: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('space_between', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em'],'default'=>['unit'=>'em'],'range'=>['em'=>['min'=>0,'max'=>4,'step'=>0.1]],'selectors'=>['{{WRAPPER}} .woocommerce-product-rating'=>'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options() + ['justify'=>['title'=>'Justificado','icon'=>'eicon-text-align-justify']],
            'selectors_dictionary'=>['left'=>'flex-start','center'=>'center','right'=>'flex-end','justify'=>'space-between'],'selectors'=>['{{WRAPPER}} .woocommerce-product-rating'=>'justify-content: {{VALUE}};']]);
        $this->end_controls_section();
    }
    protected function render(): void {
        $this->with_product(function (\WC_Product $product): void {
            if (!wc_review_ratings_enabled()) { return; }
            echo '<div class="obx-product-rating">'; woocommerce_template_single_rating(); echo '</div>';
        });
    }
}

final class Woo_Product_Stock extends Woo_Single_Element {
    public const PRO = 'woocommerce-product-stock';
    protected const LEGACY = ['woo_color'=>'text_color','woo_typography'=>'text_typography'];
    public function get_title(): string { return 'Existencias del producto'; }
    public function get_icon(): string { return 'eicon-product-stock'; }
    protected function register_controls(): void {
        $this->section('section_product_stock_style', 'Existencias');
        $this->paint('text_color', 'Color del texto', '{{WRAPPER}} .stock');
        $this->typo('text_typography', '{{WRAPPER}} .stock');
        $this->end_controls_section();
    }
    protected function render(): void {
        $this->with_product(function (\WC_Product $product): void { echo wp_kses_post(wc_get_stock_html($product)); });
    }
}

final class Woo_Product_Meta extends Woo_Single_Element {
    public const PRO = 'woocommerce-product-meta';
    protected const LEGACY = ['woo_color'=>'text_color','woo_hover'=>'link_color','woo_typography'=>'text_typography'];
    public function get_title(): string { return 'Metadatos del producto'; }
    public function get_icon(): string { return 'eicon-product-meta'; }
    protected function register_controls(): void {
        $this->section('section_meta', 'Metadatos', false);
        foreach (['sku'=>'SKU','category'=>'Categorías','tag'=>'Etiquetas'] as $key => $label) { $this->add_control('show_' . $key, ['label'=>$label,'type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes']); }
        $this->end_controls_section();

        $item = '{{WRAPPER}} .product_meta .detail-container';
        $this->section('section_product_meta_style', 'Metadatos');
        $this->add_control('view', ['label'=>'Vista','type'=>Controls_Manager::SELECT,'default'=>'inline','options'=>['table'=>'Tabla','stacked'=>'Apilada','inline'=>'En línea'],'prefix_class'=>'elementor-woo-meta--view-']);
        $this->size('space_between', 'Separación', '{{WRAPPER}} .product_meta', '--obx-meta-gap: {{SIZE}}{{UNIT}};');
        $this->add_control('divider', ['label'=>'Separador','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','label_off'=>'No','label_on'=>'Sí','separator'=>'before','selectors'=>['{{WRAPPER}} .product_meta .detail-container:not(:last-child)::after'=>'content: "";']]);
        $on = ['divider'=>'yes'];
        $this->add_control('divider_style', ['label'=>'Estilo','type'=>Controls_Manager::SELECT,'default'=>'solid','options'=>['solid'=>'Continuo','double'=>'Doble','dotted'=>'Punteado','dashed'=>'Discontinuo'],'condition'=>$on,'selectors'=>['{{WRAPPER}} .product_meta'=>'--obx-meta-divider-style: {{VALUE}};']]);
        $this->add_control('divider_weight', ['label'=>'Grosor','type'=>Controls_Manager::SLIDER,'size_units'=>['px'],'default'=>['unit'=>'px','size'=>1],'range'=>['px'=>['min'=>1,'max'=>20]],'condition'=>$on,'selectors'=>['{{WRAPPER}} .product_meta'=>'--obx-meta-divider-weight: {{SIZE}}{{UNIT}};']]);
        $this->add_control('divider_width', ['label'=>'Ancho','type'=>Controls_Manager::SLIDER,'size_units'=>['%','px'],'default'=>['unit'=>'%'],'condition'=>$on + ['view!'=>'inline'],'selectors'=>['{{WRAPPER}} .product_meta'=>'--obx-meta-divider-width: {{SIZE}}{{UNIT}};']]);
        $this->add_control('divider_height', ['label'=>'Alto','type'=>Controls_Manager::SLIDER,'size_units'=>['%','px'],'default'=>['unit'=>'%'],'condition'=>$on + ['view'=>'inline'],'selectors'=>['{{WRAPPER}} .product_meta'=>'--obx-meta-divider-height: {{SIZE}}{{UNIT}};']]);
        $this->paint('divider_color', 'Color', '{{WRAPPER}} .product_meta', '--obx-meta-divider-color', ['default'=>'#ddd','condition'=>$on]);
        $this->add_control('heading_text_style', ['label'=>'Texto','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->typo('text_typography', $item);
        $this->paint('text_color', 'Color', $item);
        $this->add_control('heading_link_style', ['label'=>'Enlaces','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->typo('link_typography', $item . ' a');
        $this->paint('link_color', 'Color', $item . ' a');
        $this->end_controls_section();

        $this->start_controls_section('section_product_meta_captions', ['label'=>'Textos']);
        foreach (['category_caption_single'=>['Categoría','Categoría'],'category_caption_plural'=>['Categorías','Categorías'],'tag_caption_single'=>['Etiqueta','Etiqueta'],'tag_caption_plural'=>['Etiquetas','Etiquetas'],'sku_caption'=>['SKU','SKU'],'sku_missing_caption'=>['SKU sin indicar','N/D']] as $key => [$label, $placeholder]) {
            $this->add_control($key, ['label'=>$label,'type'=>Controls_Manager::TEXT,'placeholder'=>$placeholder]);
        }
        $this->end_controls_section();
    }
    protected function render(): void {
        $this->with_product(function (\WC_Product $product): void {
            $s = $this->get_settings_for_display();
            $caption = static fn(string $key, string $fallback): string => (string) ($s[$key] ?? '') !== '' ? (string) $s[$key] : $fallback;
            $row = static fn(string $class, string $label, string $content): string => '<span class="' . $class . ' detail-container"><span class="detail-label">' . esc_html($label) . '</span> <span class="detail-content">' . $content . '</span></span>';
            echo '<div class="product_meta">';
            do_action('woocommerce_product_meta_start');
            if (($s['show_sku'] ?? 'yes') === 'yes' && wc_product_sku_enabled() && ($product->get_sku() || $product->is_type('variable'))) {
                $sku = $product->get_sku();
                echo $row('sku_wrapper', $caption('sku_caption', 'SKU'), '<span class="sku">' . esc_html($sku !== '' ? $sku : $caption('sku_missing_caption', 'N/D')) . '</span>'); // phpcs:ignore WordPress.Security.EscapeOutput
            }
            foreach (['category'=>['product_cat','posted_in','Categoría','Categorías'],'tag'=>['product_tag','tagged_as','Etiqueta','Etiquetas']] as $kind => [$taxonomy, $class, $one, $many]) {
                if (($s['show_' . $kind] ?? 'yes') !== 'yes') { continue; }
                $terms = get_the_terms($product->get_id(), $taxonomy);
                if (!$terms || is_wp_error($terms)) { continue; }
                $label = count($terms) === 1 ? $caption($kind . '_caption_single', $one) : $caption($kind . '_caption_plural', $many);
                echo $row($class, $label, (string) get_the_term_list($product->get_id(), $taxonomy, '', ', ')); // phpcs:ignore WordPress.Security.EscapeOutput -- core term links.
            }
            do_action('woocommerce_product_meta_end');
            echo '</div>';
        });
    }
}

final class Woo_Product_Content extends Woo_Single_Element {
    public const PRO = 'woocommerce-product-content';
    protected const LEGACY = ['woo_color'=>'text_color','woo_typography'=>'typography'];
    public function get_title(): string { return 'Contenido del producto'; }
    public function get_icon(): string { return 'eicon-product-description'; }
    protected function register_controls(): void {
        $this->section('section_style', 'Contenido');
        $this->align('align', '{{WRAPPER}}', true);
        $this->paint('text_color', 'Color del texto', '{{WRAPPER}}');
        $this->typo('typography', '{{WRAPPER}}');
        $this->end_controls_section();
    }
    protected function render(): void {
        $post = Context::post();
        if (!$post) { return; }
        static $printing = [];
        if (isset($printing[$post->ID])) { return; }
        $printing[$post->ID] = true;
        echo '<div class="woocommerce-product-content">' . apply_filters('the_content', $post->post_content) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- the_content.
        unset($printing[$post->ID]);
    }
}

final class Woo_Product_Short_Description extends Woo_Single_Element {
    public const PRO = 'woocommerce-product-short-description';
    protected const LEGACY = ['woo_color'=>'text_color','woo_typography'=>'text_typography'];
    public function get_title(): string { return 'Descripción corta del producto'; }
    public function get_icon(): string { return 'eicon-product-description'; }
    protected function register_controls(): void {
        $this->section('section_product_description_style', 'Descripción');
        $this->align('text_align', '{{WRAPPER}}', true);
        $this->paint('text_color', 'Color del texto', '{{WRAPPER}}');
        $this->typo('text_typography', '{{WRAPPER}}');
        $this->end_controls_section();
    }
    protected function render(): void {
        $this->with_product(function (\WC_Product $product): void {
            $text = (string) apply_filters('woocommerce_short_description', $product->get_short_description());
            if ($text !== '') { echo '<div class="woocommerce-product-details__short-description">' . wp_kses_post($text) . '</div>'; }
        });
    }
}

final class Woo_Product_Data_Tabs extends Woo_Single_Element {
    public const PRO = 'woocommerce-product-data-tabs';
    protected const LEGACY = ['woo_color'=>'text_color','woo_typography'=>'content_typography'];
    public function get_title(): string { return 'Pestañas de datos del producto'; }
    public function get_icon(): string { return 'eicon-product-tabs'; }
    protected function register_controls(): void {
        $tab = '{{WRAPPER}} .woocommerce-tabs ul.wc-tabs li';
        $this->section('section_product_tabs_style', 'Pestañas');
        $this->start_controls_tabs('tabs_style');
        $this->start_controls_tab('normal_tabs_style', ['label'=>'Normal']);
        $this->paint('tab_text_color', 'Color del texto', $tab . ' a');
        $this->paint('tab_bg_color', 'Fondo', $tab, 'background-color');
        $this->paint('tabs_border_color', 'Color del borde', '{{WRAPPER}} .woocommerce-tabs .woocommerce-Tabs-panel, ' . $tab . ', {{WRAPPER}} .woocommerce-tabs ul.wc-tabs::before', 'border-color');
        $this->end_controls_tab();
        $this->start_controls_tab('active_tabs_style', ['label'=>'Activa']);
        $this->paint('active_tab_text_color', 'Color del texto', $tab . '.active a');
        $this->paint('active_tab_bg_color', 'Fondo', $tab . '.active, {{WRAPPER}} .woocommerce-tabs .woocommerce-Tabs-panel', 'background-color');
        $this->paint('active_tabs_border_color', 'Color del borde', $tab . '.active', 'border-color');
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->typo('tab_typography', $tab . ' a', ['separator'=>'before']);
        $this->add_control('tab_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em'],'selectors'=>[$tab=>'border-radius: {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 0 0;']]);
        $this->end_controls_section();

        $panel = '{{WRAPPER}} .woocommerce-tabs .woocommerce-Tabs-panel';
        $this->section('section_product_panel_style', 'Panel');
        $this->paint('text_color', 'Color del texto', $panel);
        $this->typo('content_typography', $panel);
        $this->add_control('heading_panel_heading_style', ['label'=>'Encabezado','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->paint('heading_color', 'Color', $panel . ' h2');
        $this->typo('content_heading_typography', $panel . ' h2');
        $this->add_control('separator_panel_style', ['type'=>Controls_Manager::DIVIDER]);
        $this->dims('panel_border_width', 'Grosor del borde', $panel, 'border-style: solid; border-width');
        $this->dims('panel_border_radius', 'Radio del borde', $panel, 'border-radius');
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'panel_box_shadow','selector'=>$panel]);
        $this->end_controls_section();
    }
    protected function render(): void { $this->with_product([self::class, 'print_tabs']); }

    /** WooCommerce's tabs; the description tab prints the_content() of the global post, so it must be the product. */
    public static function print_tabs(\WC_Product $product): void {
        static $printing = [];
        if (isset($printing[$product->get_id()])) { return; }
        $printing[$product->get_id()] = true;
        $old = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = get_post($product->get_id());
        setup_postdata($GLOBALS['post']);
        woocommerce_output_product_data_tabs();
        $GLOBALS['post'] = $old;
        if ($old instanceof \WP_Post) { setup_postdata($old); }
        unset($printing[$product->get_id()]);
    }
}

final class Woo_Product_Additional_Information extends Woo_Single_Element {
    public const PRO = 'woocommerce-product-additional-information';
    protected const LEGACY = ['woo_color'=>'content_color','woo_typography'=>'content_typography'];
    public function get_title(): string { return 'Información adicional'; }
    public function get_icon(): string { return 'eicon-product-info'; }
    public static function wants_upgrade(string $name, array $settings): bool { return parent::wants_upgrade($name, $settings) || ($name === self::PRO && array_key_exists('title', $settings) && !array_key_exists('show_heading', $settings)); }
    protected static function upgrade_settings(array $settings): array {
        if (array_key_exists('title', $settings)) { $settings['show_heading'] = $settings['title'] === 'yes' ? 'yes' : ''; unset($settings['title']); }
        return parent::upgrade_settings($settings);
    }
    protected function register_controls(): void {
        $this->section('section_additional_info_style', 'Información');
        $this->add_control('show_heading', ['label'=>'Encabezado','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes','render_type'=>'template']);
        $this->paint('heading_color', 'Color del encabezado', '{{WRAPPER}} h2', 'color', ['condition'=>['show_heading!'=>'']]);
        $this->typo('heading_typography', '{{WRAPPER}} h2', ['condition'=>['show_heading!'=>'']]);
        $this->add_control('heading_content_style', ['label'=>'Contenido','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->paint('content_color', 'Color', '{{WRAPPER}} .shop_attributes');
        $this->typo('content_typography', '{{WRAPPER}} .shop_attributes');
        $this->end_controls_section();
    }
    protected function render(): void {
        $this->with_product(function (\WC_Product $product): void {
            $hide = $this->get_settings_for_display('show_heading') !== 'yes';
            if ($hide) { add_filter('woocommerce_product_additional_information_heading', '__return_empty_string', 99); }
            wc_get_template('single-product/tabs/additional-information.php', ['product'=>$product]);
            if ($hide) { remove_filter('woocommerce_product_additional_information_heading', '__return_empty_string', 99); }
        });
    }
}

final class Woo_Archive_Description extends Woo_Single_Element {
    public const PRO = 'woocommerce-archive-description';
    public const PRO_CATEGORY = 'woocommerce-elements-archive';
    protected const LEGACY = ['woo_color'=>'text_color','woo_typography'=>'text_typography'];
    public function get_title(): string { return 'Descripción del archivo'; }
    public function get_icon(): string { return 'eicon-product-description'; }
    protected function register_controls(): void {
        $this->section('section_product_description_style', 'Descripción');
        $this->align('text_align', '{{WRAPPER}}', true);
        $this->paint('text_color', 'Color del texto', '{{WRAPPER}}');
        $this->typo('text_typography', '{{WRAPPER}}');
        $this->end_controls_section();
    }
    protected function render(): void {
        ob_start();
        do_action('woocommerce_archive_description');
        $html = (string) ob_get_clean();
        if (trim($html) === '') { $html = Context::archive_description() !== '' ? '<div class="term-description">' . wp_kses_post(wpautop(Context::archive_description())) . '</div>' : ''; }
        if ($html !== '') { echo '<div class="woocommerce-archive-description">' . $html . '</div>'; } // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce output.
    }
}

final class Woo_Product_Add_To_Cart extends Woo_Single_Element {
    public const PRO = 'woocommerce-product-add-to-cart';
    protected const LEGACY = ['woo_button_color'=>'button_text_color','woo_button_bg'=>'button_bg_color','woo_button_hover_color'=>'button_text_color_hover','woo_button_hover_bg'=>'button_bg_color_hover','woo_button_padding'=>'button_padding','woo_button_radius'=>'button_border_radius','woo_typography'=>'button_typography'];
    public function get_title(): string { return 'Añadir al carrito'; }
    public function get_icon(): string { return 'eicon-product-add-to-cart'; }
    protected static function upgrade_settings(array $settings): array { return parent::upgrade_settings(self::rename_prefix($settings, 'woo_button_typography_', 'button_typography_')); }
    protected function register_controls(): void {
        $this->section('section_layout', 'Diseño', false);
        $this->add_control('layout', ['label'=>'Disposición','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'En línea','stacked'=>'Apilada','auto'=>'Automática'],'prefix_class'=>'elementor-add-to-cart--layout-','render_type'=>'template']);
        $this->end_controls_section();

        $button = '{{WRAPPER}} .cart button, {{WRAPPER}} .cart .button';
        $this->section('section_atc_button_style', 'Botón');
        $this->add_responsive_control('alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options() + ['justify'=>['title'=>'Justificado','icon'=>'eicon-text-align-justify']],
            'selectors_dictionary'=>['left'=>'flex-start; --obx-atc-grow: 0','center'=>'center; --obx-atc-grow: 0','right'=>'flex-end; --obx-atc-grow: 0','justify'=>'stretch; --obx-atc-grow: 1'],'selectors'=>['{{WRAPPER}}'=>'--obx-atc-justify: {{VALUE}};']]);
        $this->typo('button_typography', $button);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'button_border','exclude'=>['color'],'selector'=>$button]);
        $this->dims('button_border_radius', 'Radio del borde', $button, 'border-radius');
        $this->dims('button_padding', 'Relleno', $button, 'padding');
        $this->start_controls_tabs('button_style_tabs');
        foreach (['normal'=>['Normal', '', ''], 'hover'=>['Al pasar el ratón', ':hover', '_hover']] as $state => [$label, $pseudo, $suffix]) {
            $target = '{{WRAPPER}} .cart button' . $pseudo . ', {{WRAPPER}} .cart .button' . $pseudo;
            $this->start_controls_tab('button_style_' . $state, ['label'=>$label]);
            $this->paint('button_text_color' . $suffix, 'Color del texto', $target);
            $this->paint('button_bg_color' . $suffix, 'Fondo', $target, 'background-color');
            $this->paint('button_border_color' . $suffix, 'Color del borde', $target, 'border-color');
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->add_control('button_transition', ['label'=>'Duración de la transición','type'=>Controls_Manager::SLIDER,'default'=>['size'=>0.2],'range'=>['px'=>['min'=>0,'max'=>2,'step'=>0.1]],'selectors'=>[$button=>'transition: all {{SIZE}}s;']]);
        $this->add_control('heading_view_cart_style', ['label'=>'Enlace «Ver carrito»','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->paint('view_cart_color', 'Color', '{{WRAPPER}} .added_to_cart');
        $this->typo('view_cart_typography', '{{WRAPPER}} .added_to_cart');
        $this->size('view_cart_spacing', 'Separación', '{{WRAPPER}} .added_to_cart', 'margin-inline-start: {{SIZE}}{{UNIT}};');
        $this->end_controls_section();

        $qty = '{{WRAPPER}} .quantity .qty';
        $on = ['show_quantity!'=>''];
        $this->section('section_atc_quantity_style', 'Cantidad');
        $this->add_control('show_quantity', ['label'=>'Mostrar la cantidad','type'=>Controls_Manager::SWITCHER,'default'=>'yes','return_value'=>'yes','prefix_class'=>'e-add-to-cart--show-quantity-','render_type'=>'template']);
        $this->size('spacing', 'Separación', '{{WRAPPER}} .cart', '--obx-atc-gap: {{SIZE}}{{UNIT}};', ['condition'=>$on]);
        $this->typo('quantity_typography', $qty, ['condition'=>$on]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'quantity_border','exclude'=>['color'],'selector'=>$qty,'condition'=>$on]);
        $this->dims('quantity_border_radius', 'Radio del borde', $qty, 'border-radius', ['condition'=>$on]);
        $this->dims('quantity_padding', 'Relleno', $qty, 'padding', ['condition'=>$on]);
        $this->start_controls_tabs('quantity_style_tabs', ['condition'=>$on]);
        foreach (['normal'=>['Normal', '', ''], 'focus'=>['Al escribir', ':focus', '_focus']] as $state => [$label, $pseudo, $suffix]) {
            $this->start_controls_tab('quantity_style_' . $state, ['label'=>$label]);
            $this->paint('quantity_text_color' . $suffix, 'Color del texto', $qty . $pseudo, 'color', ['condition'=>$on]);
            $this->paint('quantity_bg_color' . $suffix, 'Fondo', $qty . $pseudo, 'background-color', ['condition'=>$on]);
            $this->paint('quantity_border_color' . $suffix, 'Color del borde', $qty . $pseudo, 'border-color', ['condition'=>$on]);
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->add_control('quantity_transition', ['label'=>'Duración de la transición','type'=>Controls_Manager::SLIDER,'default'=>['size'=>0.2],'range'=>['px'=>['min'=>0,'max'=>2,'step'=>0.1]],'condition'=>$on,'selectors'=>[$qty=>'transition: all {{SIZE}}s;']]);
        $this->end_controls_section();

        $select = '{{WRAPPER}} .cart table.variations td.value select';
        $this->section('section_atc_variations_style', 'Variaciones');
        $this->add_responsive_control('variations_width', ['label'=>'Ancho','type'=>Controls_Manager::SLIDER,'size_units'=>['%','px'],'default'=>['unit'=>'%'],'selectors'=>['{{WRAPPER}} .cart .variations'=>'width: {{SIZE}}{{UNIT}};']]);
        $this->size('variations_spacing', 'Separación', '{{WRAPPER}} .cart .variations', 'margin-bottom: {{SIZE}}{{UNIT}};');
        $this->size('variations_space_between', 'Separación entre opciones', '{{WRAPPER}} .cart table.variations tr th, {{WRAPPER}} .cart table.variations tr td', 'padding-top: calc({{SIZE}}{{UNIT}} / 2); padding-bottom: calc({{SIZE}}{{UNIT}} / 2);');
        $this->add_control('heading_variations_label_style', ['label'=>'Etiqueta','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->paint('variations_label_color_focus', 'Color', '{{WRAPPER}} .cart .variations label');
        $this->typo('variations_label_typography', '{{WRAPPER}} .cart .variations label');
        $this->add_control('heading_variations_select_style', ['label'=>'Desplegable','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->paint('variations_select_color', 'Color', $select);
        $this->paint('variations_select_bg_color', 'Fondo', $select, 'background-color');
        $this->paint('variations_select_border_color', 'Color del borde', $select, 'border: 1px solid {{VALUE}}; border-color');
        $this->typo('variations_select_typography', $select);
        $this->add_control('variations_select_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em'],'selectors'=>[$select=>'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
    }
    protected function render(): void {
        $this->with_product(function (): void {
            $hide = $this->get_settings_for_display('show_quantity') !== 'yes';
            if ($hide) { add_filter('woocommerce_quantity_input_args', [self::class, 'hidden_quantity'], 99); }
            echo '<div class="elementor-add-to-cart elementor-product-' . esc_attr((string) $GLOBALS['product']->get_type()) . '">';
            woocommerce_template_single_add_to_cart();
            echo '</div>';
            if ($hide) { remove_filter('woocommerce_quantity_input_args', [self::class, 'hidden_quantity'], 99); }
        });
    }
    /** Without the quantity field the product is added once (WooCommerce prints a hidden input). */
    public static function hidden_quantity(array $args): array { $args['min_value'] = $args['max_value'] = max(1, (int) ($args['min_value'] ?? 1)); return $args; }
}
