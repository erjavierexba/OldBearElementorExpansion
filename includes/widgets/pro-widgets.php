<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Elementor\Utils;

if (!defined('ABSPATH')) { exit; }

/** General purpose widgets offered in the «Pro» panel category. */
abstract class Element extends Base {
    public const PRO_CATEGORY = 'pro-elements';
    protected function is_dynamic_content(): bool { return false; }
    public function get_script_depends(): array { return ['obx-widgets']; }
}

final class Blockquote extends Element {
    public const PRO = 'blockquote';
    public function get_title(): string { return 'Cita'; }
    public function get_icon(): string { return 'eicon-blockquote'; }
    public function get_keywords(): array { return ['blockquote','cita','quote','frase','testimonio']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Cita']);
        $this->add_control('skin', ['label'=>'Estilo','type'=>Controls_Manager::SELECT,'options'=>['border'=>'Borde','quotation'=>'Comillas','boxed'=>'Caja','clean'=>'Limpio'],'default'=>'border']);
        $this->add_control('quote', ['label'=>'Texto','type'=>Controls_Manager::TEXTAREA,'rows'=>5,'default'=>'Lo que haces con constancia acaba hablando por ti.','dynamic'=>['active'=>true]]);
        $this->add_control('author', ['label'=>'Autor','type'=>Controls_Manager::TEXT,'default'=>'Nombre del autor','dynamic'=>['active'=>true]]);
        $this->add_control('share', ['label'=>'Botón para compartir','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'']);
        $this->add_control('share_label', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Compartir','condition'=>['share'=>'yes']]);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),'selectors'=>['{{WRAPPER}} .obx-quote'=>'text-align: {{VALUE}};']]);
        $this->end_controls_section();
        $this->text_style('.obx-quote__text', 'Texto', 'text', false);
        $this->text_style('.obx-quote__author', 'Autor', 'author', false);
        $this->start_controls_section('accent_style', ['label'=>'Acento','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('accent', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-quote'=>'--obx-accent: {{VALUE}};']]);
        $this->add_control('box_bg', ['label'=>'Fondo de la caja','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-quote--boxed'=>'background-color: {{VALUE}};'],'condition'=>['skin'=>'boxed']]);
        $this->end_controls_section();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $skin = in_array($s['skin'], ['border','quotation','boxed','clean'], true) ? $s['skin'] : 'border';
        echo '<blockquote class="obx-quote obx-quote--' . esc_attr($skin) . '"><p class="obx-quote__text">' . nl2br(esc_html($s['quote'])) . '</p>';
        if ($s['author'] !== '' || $s['share'] === 'yes') {
            echo '<footer class="obx-quote__footer">';
            if ($s['author'] !== '') { echo '<cite class="obx-quote__author">' . esc_html($s['author']) . '</cite>'; }
            if ($s['share'] === 'yes') {
                $text = trim($s['quote'] . ($s['author'] !== '' ? ' — ' . $s['author'] : ''));
                $url = 'https://twitter.com/intent/tweet?text=' . rawurlencode($text) . '&url=' . rawurlencode((string) get_permalink());
                echo '<a class="obx-quote__share" href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($s['share_label']) . '</a>';
            }
            echo '</footer>';
        }
        echo '</blockquote>';
    }
}

final class Call_To_Action extends Element {
    public const PRO = 'call-to-action';
    public function get_title(): string { return 'Llamada a la acción'; }
    public function get_icon(): string { return 'eicon-image-rollover'; }
    public function get_keywords(): array { return ['cta','call to action','llamada','accion','banner']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Contenido']);
        $this->add_control('skin', ['label'=>'Diseño','type'=>Controls_Manager::SELECT,'options'=>['classic'=>'Clásico','cover'=>'Portada'],'default'=>'classic']);
        $this->add_control('image_position', ['label'=>'Posición de la imagen','type'=>Controls_Manager::SELECT,'options'=>['top'=>'Arriba','left'=>'Izquierda','right'=>'Derecha'],'default'=>'top','condition'=>['skin'=>'classic']]);
        $this->add_control('image', ['label'=>'Imagen','type'=>Controls_Manager::MEDIA,'default'=>['url'=>Utils::get_placeholder_image_src()],'dynamic'=>['active'=>true]]);
        $this->add_control('title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Esto es una llamada a la acción','label_block'=>true,'dynamic'=>['active'=>true]]);
        $this->add_control('title_tag', ['label'=>'Etiqueta del título','type'=>Controls_Manager::SELECT,'options'=>['h2'=>'H2','h3'=>'H3','h4'=>'H4','p'=>'p'],'default'=>'h2']);
        $this->add_control('description', ['label'=>'Descripción','type'=>Controls_Manager::TEXTAREA,'default'=>'Cuenta en una frase por qué merece la pena hacer clic.','dynamic'=>['active'=>true]]);
        $this->add_control('button', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Haz clic aquí']);
        $this->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true],'default'=>['url'=>'#']]);
        $this->add_control('link_click', ['label'=>'Zona clicable','type'=>Controls_Manager::SELECT,'options'=>['button'=>'Solo el botón','box'=>'Toda la caja'],'default'=>'button']);
        $this->add_control('ribbon', ['label'=>'Cinta','type'=>Controls_Manager::TEXT,'default'=>'','separator'=>'before']);
        $this->end_controls_section();
        $this->start_controls_section('box_layout', ['label'=>'Caja','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('min_height', ['label'=>'Altura mínima','type'=>Controls_Manager::SLIDER,'size_units'=>['px','vh'],'range'=>['px'=>['min'=>100,'max'=>1000]],'selectors'=>['{{WRAPPER}} .obx-cta__content'=>'min-height: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),'default'=>'center','selectors'=>['{{WRAPPER}} .obx-cta__content'=>'text-align: {{VALUE}};']]);
        $this->add_control('content_bg', ['label'=>'Fondo del contenido','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-cta__content'=>'background-color: {{VALUE}};']]);
        $this->add_control('overlay', ['label'=>'Capa sobre la imagen','type'=>Controls_Manager::COLOR,'default'=>'rgba(0,0,0,0.45)','selectors'=>['{{WRAPPER}} .obx-cta--cover .obx-cta__bg::after'=>'background-color: {{VALUE}};'],'condition'=>['skin'=>'cover']]);
        $this->add_control('hover_zoom', ['label'=>'Zoom al pasar el ratón','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes','prefix_class'=>'obx-cta-zoom-']);
        $this->add_responsive_control('padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','em'],'selectors'=>['{{WRAPPER}} .obx-cta__content'=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->end_controls_section();
        $this->text_style('.obx-cta__title', 'Título', 'title', false);
        $this->text_style('.obx-cta__description', 'Descripción', 'description', false);
        $this->button_style('.obx-cta__button');
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $skin = $s['skin'] === 'cover' ? 'cover' : 'classic';
        $position = in_array($s['image_position'], ['top','left','right'], true) ? $s['image_position'] : 'top';
        $box_link = $s['link_click'] === 'box' && !empty($s['link']['url']);
        $wrapper = $box_link ? 'a' : 'div';
        if ($box_link) { $this->add_link_attributes('box', $s['link']); }
        $this->add_render_attribute('box', 'class', ['obx-cta','obx-cta--' . $skin,'obx-cta--image-' . $position]);
        echo '<' . $wrapper . ' ' . $this->get_render_attribute_string('box') . '>';
        if (!empty($s['image']['url'])) {
            $src = !empty($s['image']['id']) ? (string) wp_get_attachment_image_url((int) $s['image']['id'], 'large') : $s['image']['url'];
            echo '<div class="obx-cta__bg"><div class="obx-cta__bg-image" style="background-image:url(' . esc_url($src ?: $s['image']['url']) . ')"></div></div>';
        }
        $tag = in_array($s['title_tag'], ['h2','h3','h4','p'], true) ? $s['title_tag'] : 'h2';
        echo '<div class="obx-cta__content">';
        if ($s['title'] !== '') { printf('<%1$s class="obx-cta__title">%2$s</%1$s>', $tag, esc_html($s['title'])); }
        if ($s['description'] !== '') { echo '<p class="obx-cta__description">' . esc_html($s['description']) . '</p>'; }
        if ($s['button'] !== '') {
            echo $box_link ? '<span class="obx-cta__button">' . esc_html($s['button']) . '</span>' : $this->open_link('button', $s['link'], 'obx-cta__button') . esc_html($s['button']) . $this->close_link($s['link']);
        }
        echo '</div>';
        if ($s['ribbon'] !== '') { echo '<span class="obx-ribbon">' . esc_html($s['ribbon']) . '</span>'; }
        echo '</' . $wrapper . '>';
    }
}

final class Flip_Box extends Element {
    public const PRO = 'flip-box';
    public function get_title(): string { return 'Caja giratoria'; }
    public function get_icon(): string { return 'eicon-flip-box'; }
    public function get_keywords(): array { return ['flip','box','caja','giratoria','voltear']; }
    protected function register_controls(): void {
        $this->start_controls_section('front', ['label'=>'Cara frontal']);
        $this->add_control('icon', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'default'=>['value'=>'fas fa-star','library'=>'fa-solid']]);
        $this->add_control('front_title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Cara frontal','label_block'=>true,'dynamic'=>['active'=>true]]);
        $this->add_control('front_text', ['label'=>'Descripción','type'=>Controls_Manager::TEXTAREA,'default'=>'Pasa el ratón para ver el reverso.','dynamic'=>['active'=>true]]);
        $this->end_controls_section();
        $this->start_controls_section('back', ['label'=>'Cara trasera']);
        $this->add_control('back_title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Cara trasera','label_block'=>true,'dynamic'=>['active'=>true]]);
        $this->add_control('back_text', ['label'=>'Descripción','type'=>Controls_Manager::TEXTAREA,'default'=>'Añade aquí el detalle y un enlace.','dynamic'=>['active'=>true]]);
        $this->add_control('button', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Saber más']);
        $this->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true],'default'=>['url'=>'#']]);
        $this->end_controls_section();
        $this->start_controls_section('settings', ['label'=>'Ajustes']);
        $this->add_responsive_control('height', ['label'=>'Altura','type'=>Controls_Manager::SLIDER,'size_units'=>['px','vh'],'range'=>['px'=>['min'=>100,'max'=>1000]],'default'=>['unit'=>'px','size'=>280],'selectors'=>['{{WRAPPER}} .obx-flip'=>'height: {{SIZE}}{{UNIT}};']]);
        $this->add_control('effect', ['label'=>'Efecto','type'=>Controls_Manager::SELECT,'options'=>['flip'=>'Girar','slide'=>'Deslizar','push'=>'Empujar','zoom'=>'Zoom','fade'=>'Fundido'],'default'=>'flip']);
        $this->add_control('direction', ['label'=>'Dirección','type'=>Controls_Manager::SELECT,'options'=>['left'=>'Izquierda','right'=>'Derecha','up'=>'Arriba','down'=>'Abajo'],'default'=>'up','condition'=>['effect'=>['flip','slide','push']]]);
        $this->end_controls_section();
        foreach (['front'=>'Cara frontal','back'=>'Cara trasera'] as $side => $label) {
            $this->start_controls_section($side . '_style', ['label'=>$label,'tab'=>Controls_Manager::TAB_STYLE]);
            $this->add_group_control(Group_Control_Background::get_type(), ['name'=>$side . '_background','types'=>['classic','gradient'],'selector'=>'{{WRAPPER}} .obx-flip__' . $side]);
            $this->add_control($side . '_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-flip__' . $side=>'color: {{VALUE}};']]);
            $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>$side . '_title_typography','label'=>'Tipografía del título','selector'=>'{{WRAPPER}} .obx-flip__' . $side . ' .obx-flip__title']);
            $this->add_responsive_control($side . '_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','em'],'selectors'=>['{{WRAPPER}} .obx-flip__' . $side=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
            $this->end_controls_section();
        }
        $this->button_style('.obx-flip__button');
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $effect = in_array($s['effect'], ['flip','slide','push','zoom','fade'], true) ? $s['effect'] : 'flip';
        $direction = in_array($s['direction'], ['left','right','up','down'], true) ? $s['direction'] : 'up';
        echo '<div class="obx-flip obx-flip--' . esc_attr($effect) . ' obx-flip--' . esc_attr($direction) . '" tabindex="0"><div class="obx-flip__inner">';
        echo '<div class="obx-flip__front"><div class="obx-flip__layer">';
        if (!empty($s['icon']['value'])) { echo '<span class="obx-flip__icon">'; Icons_Manager::render_icon($s['icon'], ['aria-hidden'=>'true']); echo '</span>'; }
        if ($s['front_title'] !== '') { echo '<h3 class="obx-flip__title">' . esc_html($s['front_title']) . '</h3>'; }
        if ($s['front_text'] !== '') { echo '<p class="obx-flip__text">' . esc_html($s['front_text']) . '</p>'; }
        echo '</div></div><div class="obx-flip__back"><div class="obx-flip__layer">';
        if ($s['back_title'] !== '') { echo '<h3 class="obx-flip__title">' . esc_html($s['back_title']) . '</h3>'; }
        if ($s['back_text'] !== '') { echo '<p class="obx-flip__text">' . esc_html($s['back_text']) . '</p>'; }
        if ($s['button'] !== '') { echo $this->open_link('button', $s['link'], 'obx-flip__button') . esc_html($s['button']) . $this->close_link($s['link']); }
        echo '</div></div></div></div>';
    }
}

final class Price_Table extends Element {
    public const PRO = 'price-table';
    public function get_title(): string { return 'Tabla de precios'; }
    public function get_icon(): string { return 'eicon-price-table'; }
    public function get_keywords(): array { return ['price','precio','tabla','plan','tarifa','pricing']; }
    protected function register_controls(): void {
        $this->start_controls_section('header', ['label'=>'Cabecera']);
        $this->add_control('heading', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Plan básico','dynamic'=>['active'=>true]]);
        $this->add_control('sub_heading', ['label'=>'Subtítulo','type'=>Controls_Manager::TEXT,'default'=>'Para empezar','dynamic'=>['active'=>true]]);
        $this->end_controls_section();
        $this->start_controls_section('pricing', ['label'=>'Precio']);
        $this->add_control('currency', ['label'=>'Moneda','type'=>Controls_Manager::TEXT,'default'=>'€']);
        $this->add_control('currency_position', ['label'=>'Posición de la moneda','type'=>Controls_Manager::SELECT,'options'=>['before'=>'Antes','after'=>'Después'],'default'=>'after']);
        $this->add_control('price', ['label'=>'Precio','type'=>Controls_Manager::TEXT,'default'=>'29,99','dynamic'=>['active'=>true]]);
        $this->add_control('original_price', ['label'=>'Precio anterior','type'=>Controls_Manager::TEXT,'default'=>'']);
        $this->add_control('period', ['label'=>'Periodo','type'=>Controls_Manager::TEXT,'default'=>'al mes']);
        $this->end_controls_section();
        $this->start_controls_section('features', ['label'=>'Características']);
        $repeater = new Repeater();
        $repeater->add_control('text', ['label'=>'Texto','type'=>Controls_Manager::TEXT,'default'=>'Característica','label_block'=>true]);
        $repeater->add_control('included', ['label'=>'Incluida','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $repeater->add_control('icon', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'default'=>['value'=>'fas fa-check','library'=>'fa-solid']]);
        $this->add_control('items', ['type'=>Controls_Manager::REPEATER,'fields'=>$repeater->get_controls(),'title_field'=>'{{{ text }}}',
            'default'=>[['text'=>'Una web'],['text'=>'Soporte por correo'],['text'=>'Copias diarias','included'=>'','icon'=>['value'=>'fas fa-times','library'=>'fa-solid']]]]);
        $this->end_controls_section();
        $this->start_controls_section('footer', ['label'=>'Pie']);
        $this->add_control('button', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Elegir plan']);
        $this->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true],'default'=>['url'=>'#']]);
        $this->add_control('footer_text', ['label'=>'Información adicional','type'=>Controls_Manager::TEXTAREA,'default'=>'']);
        $this->add_control('ribbon', ['label'=>'Cinta','type'=>Controls_Manager::TEXT,'default'=>'','separator'=>'before']);
        $this->end_controls_section();
        $this->box_style('.obx-price-table', 'Tabla', 'table');
        $this->start_controls_section('header_style', ['label'=>'Cabecera','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('header_bg', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-price-table__header'=>'background-color: {{VALUE}};']]);
        $this->add_control('header_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-price-table__header'=>'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'heading_typography','label'=>'Tipografía del título','selector'=>'{{WRAPPER}} .obx-price-table__heading']);
        $this->end_controls_section();
        $this->text_style('.obx-price-table__price', 'Precio', 'price', false);
        $this->text_style('.obx-price-table__features', 'Características', 'features', false);
        $this->button_style('.obx-price-table__button');
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $currency = '<span class="obx-price-table__currency">' . esc_html($s['currency']) . '</span>';
        $amount = '<span class="obx-price-table__amount">' . esc_html($s['price']) . '</span>';
        echo '<div class="obx-price-table"><div class="obx-price-table__header">';
        if ($s['heading'] !== '') { echo '<h3 class="obx-price-table__heading">' . esc_html($s['heading']) . '</h3>'; }
        if ($s['sub_heading'] !== '') { echo '<p class="obx-price-table__subheading">' . esc_html($s['sub_heading']) . '</p>'; }
        echo '</div><div class="obx-price-table__price">';
        if ($s['original_price'] !== '') { echo '<del class="obx-price-table__original">' . esc_html($s['original_price']) . '</del>'; }
        echo $s['currency_position'] === 'before' ? $currency . $amount : $amount . $currency;
        if ($s['period'] !== '') { echo '<span class="obx-price-table__period">' . esc_html($s['period']) . '</span>'; }
        echo '</div>';
        if (!empty($s['items'])) {
            echo '<ul class="obx-price-table__features">';
            foreach ($s['items'] as $item) {
                echo '<li class="' . ($item['included'] === 'yes' ? 'is-included' : 'is-excluded') . '">';
                if (!empty($item['icon']['value'])) { Icons_Manager::render_icon($item['icon'], ['aria-hidden'=>'true']); }
                echo '<span>' . esc_html($item['text']) . '</span>' . ($item['included'] === 'yes' ? '' : '<span class="screen-reader-text"> (no incluida)</span>') . '</li>';
            }
            echo '</ul>';
        }
        echo '<div class="obx-price-table__footer">';
        if ($s['button'] !== '') { echo $this->open_link('button', $s['link'], 'obx-price-table__button') . esc_html($s['button']) . $this->close_link($s['link']); }
        if ($s['footer_text'] !== '') { echo '<p class="obx-price-table__note">' . esc_html($s['footer_text']) . '</p>'; }
        echo '</div>';
        if ($s['ribbon'] !== '') { echo '<span class="obx-ribbon">' . esc_html($s['ribbon']) . '</span>'; }
        echo '</div>';
    }
}

final class Price_List extends Element {
    public const PRO = 'price-list';
    public function get_title(): string { return 'Lista de precios'; }
    public function get_icon(): string { return 'eicon-price-list'; }
    public function get_keywords(): array { return ['price','precio','lista','menu','carta','tarifas']; }
    protected function register_controls(): void {
        $this->start_controls_section('list', ['label'=>'Lista']);
        $repeater = new Repeater();
        $repeater->add_control('title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Producto','label_block'=>true]);
        $repeater->add_control('price', ['label'=>'Precio','type'=>Controls_Manager::TEXT,'default'=>'10 €']);
        $repeater->add_control('description', ['label'=>'Descripción','type'=>Controls_Manager::TEXTAREA,'default'=>'']);
        $repeater->add_control('image', ['label'=>'Imagen','type'=>Controls_Manager::MEDIA]);
        $repeater->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL]);
        $this->add_control('items', ['type'=>Controls_Manager::REPEATER,'fields'=>$repeater->get_controls(),'title_field'=>'{{{ title }}}',
            'default'=>[['title'=>'Primer producto','price'=>'12 €','description'=>'Una línea que lo describe.'],['title'=>'Segundo producto','price'=>'18 €','description'=>'Otra línea que lo describe.']]]);
        $this->end_controls_section();
        $this->start_controls_section('list_style', ['label'=>'Lista','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('separator', ['label'=>'Separador','type'=>Controls_Manager::SELECT,'options'=>['dotted'=>'Puntos','dashed'=>'Guiones','solid'=>'Línea','none'=>'Ninguno'],'default'=>'dotted','selectors'=>['{{WRAPPER}} .obx-price-list__sep'=>'border-bottom-style: {{VALUE}};']]);
        $this->add_responsive_control('row_gap', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>0,'max'=>80]],'default'=>['unit'=>'px','size'=>20],'selectors'=>['{{WRAPPER}} .obx-price-list'=>'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('image_size', ['label'=>'Tamaño de la imagen','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>20,'max'=>200]],'default'=>['unit'=>'px','size'=>64],'selectors'=>['{{WRAPPER}} .obx-price-list__image img'=>'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
        $this->text_style('.obx-price-list__title', 'Título', 'title', false);
        $this->text_style('.obx-price-list__price', 'Precio', 'price', false);
        $this->text_style('.obx-price-list__description', 'Descripción', 'description', false);
    }
    protected function render(): void {
        $items = $this->get_settings_for_display('items');
        if (empty($items)) { $this->placeholder('Añade elementos a la lista.'); return; }
        echo '<ul class="obx-price-list">';
        foreach ($items as $i => $item) {
            echo '<li class="obx-price-list__item">' . $this->open_link('item-' . $i, $item['link'] ?? [], 'obx-price-list__row', 'div');
            if (!empty($item['image']['id'])) {
                echo '<span class="obx-price-list__image">' . wp_get_attachment_image((int) $item['image']['id'], 'thumbnail', false, ['loading'=>'lazy']) . '</span>';
            } elseif (!empty($item['image']['url'])) {
                echo '<span class="obx-price-list__image"><img src="' . esc_url($item['image']['url']) . '" alt="" loading="lazy"></span>';
            }
            echo '<span class="obx-price-list__text"><span class="obx-price-list__header"><span class="obx-price-list__title">' . esc_html($item['title']) . '</span><span class="obx-price-list__sep"></span><span class="obx-price-list__price">' . esc_html($item['price']) . '</span></span>';
            if ($item['description'] !== '') { echo '<span class="obx-price-list__description">' . esc_html($item['description']) . '</span>'; }
            echo '</span>' . $this->close_link($item['link'] ?? [], 'div') . '</li>';
        }
        echo '</ul>';
    }
}

final class Countdown extends Element {
    public const PRO = 'countdown';
    public function get_title(): string { return 'Cuenta atrás'; }
    public function get_icon(): string { return 'eicon-countdown'; }
    public function get_keywords(): array { return ['countdown','cuenta atras','temporizador','timer','fecha']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Cuenta atrás']);
        $this->add_control('type', ['label'=>'Tipo','type'=>Controls_Manager::SELECT,'options'=>['due_date'=>'Hasta una fecha','evergreen'=>'Por visitante'],'default'=>'due_date']);
        $this->add_control('due_date', ['label'=>'Fecha límite','type'=>Controls_Manager::DATE_TIME,'default'=>wp_date('Y-m-d H:i', time() + MONTH_IN_SECONDS),'description'=>'Zona horaria del sitio (' . wp_timezone_string() . ').','condition'=>['type'=>'due_date']]);
        $this->add_control('evergreen_hours', ['label'=>'Horas','type'=>Controls_Manager::NUMBER,'min'=>0,'default'=>47,'condition'=>['type'=>'evergreen']]);
        $this->add_control('evergreen_minutes', ['label'=>'Minutos','type'=>Controls_Manager::NUMBER,'min'=>0,'max'=>59,'default'=>59,'condition'=>['type'=>'evergreen']]);
        $this->add_control('view', ['label'=>'Disposición','type'=>Controls_Manager::SELECT,'options'=>['block'=>'Bloques','inline'=>'En línea'],'default'=>'block']);
        foreach (['days'=>'Días','hours'=>'Horas','minutes'=>'Minutos','seconds'=>'Segundos'] as $unit => $label) {
            $this->add_control('show_' . $unit, ['label'=>$label,'type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
            $this->add_control('label_' . $unit, ['label'=>'Etiqueta','type'=>Controls_Manager::TEXT,'default'=>$label,'condition'=>['show_' . $unit=>'yes']]);
        }
        $this->add_control('expire', ['label'=>'Al terminar','type'=>Controls_Manager::SELECT,'options'=>['none'=>'Dejar a cero','hide'=>'Ocultar','message'=>'Mostrar un mensaje','redirect'=>'Redirigir'],'default'=>'none','separator'=>'before']);
        $this->add_control('message', ['label'=>'Mensaje','type'=>Controls_Manager::TEXTAREA,'default'=>'¡Se acabó el tiempo!','condition'=>['expire'=>'message']]);
        $this->add_control('redirect', ['label'=>'Redirigir a','type'=>Controls_Manager::URL,'condition'=>['expire'=>'redirect']]);
        $this->end_controls_section();
        $this->start_controls_section('boxes_style', ['label'=>'Bloques','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('box_bg', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-countdown__item'=>'background-color: {{VALUE}};']]);
        $this->add_responsive_control('box_gap', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>0,'max'=>60]],'default'=>['unit'=>'px','size'=>12],'selectors'=>['{{WRAPPER}} .obx-countdown'=>'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('box_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','em'],'selectors'=>['{{WRAPPER}} .obx-countdown__item'=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_control('box_radius', ['label'=>'Radio','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>0,'max'=>60]],'selectors'=>['{{WRAPPER}} .obx-countdown__item'=>'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
        $this->text_style('.obx-countdown__digits', 'Números', 'digits', false);
        $this->text_style('.obx-countdown__label', 'Etiquetas', 'label', false);
        $this->text_style('.obx-countdown__message', 'Mensaje', 'message', false);
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $this->add_render_attribute('countdown', 'class', ['obx-countdown','obx-countdown--' . ($s['view'] === 'inline' ? 'inline' : 'block')]);
        if ($s['type'] === 'evergreen') {
            $this->add_render_attribute('countdown', 'data-evergreen', (string) (max(0, (int) $s['evergreen_hours']) * 3600 + max(0, (int) $s['evergreen_minutes']) * 60));
            $this->add_render_attribute('countdown', 'data-id', $this->get_id());
        } else {
            $date = date_create_immutable((string) $s['due_date'], wp_timezone());
            $this->add_render_attribute('countdown', 'data-due', (string) ($date ? $date->getTimestamp() : time()));
        }
        $expire = in_array($s['expire'], ['none','hide','message','redirect'], true) ? $s['expire'] : 'none';
        $this->add_render_attribute('countdown', 'data-expire', $expire);
        if ($expire === 'redirect' && !empty($s['redirect']['url'])) { $this->add_render_attribute('countdown', 'data-redirect', esc_url_raw($s['redirect']['url'])); }
        echo '<div ' . $this->get_render_attribute_string('countdown') . '>';
        foreach (['days','hours','minutes','seconds'] as $unit) {
            if ($s['show_' . $unit] !== 'yes') { continue; }
            echo '<div class="obx-countdown__item"><span class="obx-countdown__digits" data-unit="' . esc_attr($unit) . '">00</span><span class="obx-countdown__label">' . esc_html($s['label_' . $unit]) . '</span></div>';
        }
        echo '</div>';
        if ($expire === 'message') { echo '<div class="obx-countdown__message" hidden>' . esc_html($s['message']) . '</div>'; }
    }
}

final class Share_Buttons extends Element {
    public const PRO = 'share-buttons';
    private const NETWORKS = [
        'facebook'=>['Facebook','fab fa-facebook-f','https://www.facebook.com/sharer/sharer.php?u={url}'],
        'x'=>['X','fab fa-x-twitter','https://twitter.com/intent/tweet?text={text}&url={url}'],
        'linkedin'=>['LinkedIn','fab fa-linkedin-in','https://www.linkedin.com/sharing/share-offsite/?url={url}'],
        'whatsapp'=>['WhatsApp','fab fa-whatsapp','https://api.whatsapp.com/send?text={text}%20{url}'],
        'telegram'=>['Telegram','fab fa-telegram-plane','https://t.me/share/url?url={url}&text={text}'],
        'pinterest'=>['Pinterest','fab fa-pinterest-p','https://pinterest.com/pin/create/button/?url={url}&description={text}'],
        'reddit'=>['Reddit','fab fa-reddit-alien','https://reddit.com/submit?url={url}&title={text}'],
        'threads'=>['Threads','fab fa-threads','https://www.threads.net/intent/post?text={text}%20{url}'],
        'email'=>['Correo','fas fa-envelope','mailto:?subject={text}&body={url}'],
        'print'=>['Imprimir','fas fa-print',''],
    ];
    public function get_title(): string { return 'Botones para compartir'; }
    public function get_icon(): string { return 'eicon-share'; }
    public function get_keywords(): array { return ['share','compartir','redes','social','facebook','whatsapp']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Botones']);
        $repeater = new Repeater();
        $repeater->add_control('network', ['label'=>'Red','type'=>Controls_Manager::SELECT,'options'=>array_map(static fn($n) => $n[0], self::NETWORKS),'default'=>'facebook']);
        $repeater->add_control('text', ['label'=>'Texto personalizado','type'=>Controls_Manager::TEXT,'default'=>'']);
        $this->add_control('buttons', ['type'=>Controls_Manager::REPEATER,'fields'=>$repeater->get_controls(),'title_field'=>'{{{ network }}}','default'=>[['network'=>'facebook'],['network'=>'x'],['network'=>'linkedin'],['network'=>'whatsapp']]]);
        $this->add_control('view', ['label'=>'Mostrar','type'=>Controls_Manager::SELECT,'options'=>['icon-text'=>'Icono y texto','icon'=>'Solo icono','text'=>'Solo texto'],'default'=>'icon-text']);
        $this->add_control('shape', ['label'=>'Forma','type'=>Controls_Manager::SELECT,'options'=>['square'=>'Cuadrada','rounded'=>'Redondeada','circle'=>'Píldora'],'default'=>'rounded']);
        $this->add_control('skin', ['label'=>'Estilo','type'=>Controls_Manager::SELECT,'options'=>['brand'=>'Colores de cada red','outline'=>'Contorno','custom'=>'Personalizado'],'default'=>'brand']);
        $this->add_control('target', ['label'=>'Compartir','type'=>Controls_Manager::SELECT,'options'=>['current'=>'Página actual','custom'=>'Otra dirección'],'default'=>'current']);
        $this->add_control('share_url', ['label'=>'Dirección','type'=>Controls_Manager::URL,'condition'=>['target'=>'custom']]);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),'selectors'=>['{{WRAPPER}} .obx-share'=>'justify-content: {{VALUE}};'],'selectors_dictionary'=>['left'=>'flex-start','center'=>'center','right'=>'flex-end']]);
        $this->end_controls_section();
        $this->start_controls_section('buttons_style', ['label'=>'Botones','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('gap', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>0,'max'=>40]],'default'=>['unit'=>'px','size'=>8],'selectors'=>['{{WRAPPER}} .obx-share'=>'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_control('color', ['label'=>'Color principal','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-share'=>'--obx-share-color: {{VALUE}};'],'condition'=>['skin!'=>'brand']]);
        $this->add_control('text_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-share'=>'--obx-share-text: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'typography','selector'=>'{{WRAPPER}} .obx-share__button']);
        $this->end_controls_section();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $url = $s['target'] === 'custom' && !empty($s['share_url']['url']) ? $s['share_url']['url'] : (is_singular() ? (string) get_permalink() : home_url(add_query_arg([])));
        $title = is_singular() ? get_the_title() : wp_get_document_title();
        $view = in_array($s['view'], ['icon-text','icon','text'], true) ? $s['view'] : 'icon-text';
        $classes = 'obx-share obx-share--' . $view . ' obx-share--' . (in_array($s['shape'], ['square','rounded','circle'], true) ? $s['shape'] : 'rounded') . ' obx-share--' . (in_array($s['skin'], ['brand','outline','custom'], true) ? $s['skin'] : 'brand');
        echo '<div class="' . esc_attr($classes) . '">';
        foreach ((array) $s['buttons'] as $button) {
            $network = isset(self::NETWORKS[$button['network']]) ? $button['network'] : 'facebook';
            [$label, $icon, $pattern] = self::NETWORKS[$network];
            $text = $button['text'] !== '' ? $button['text'] : $label;
            $href = $pattern === '' ? '#' : str_replace(['{url}','{text}'], [rawurlencode($url), rawurlencode(wp_strip_all_tags($title))], $pattern);
            $attrs = $network === 'print' ? ' data-obx-print' : ($network === 'email' ? '' : ' target="_blank" rel="noopener nofollow"');
            echo '<a class="obx-share__button obx-share__button--' . esc_attr($network) . '" href="' . esc_url($href, $network === 'email' ? ['mailto'] : null) . '"' . $attrs . ($view === 'icon' ? ' aria-label="' . esc_attr($text) . '"' : '') . '>';
            if ($view !== 'text') { echo '<span class="obx-share__icon">'; Icons_Manager::render_icon(['value'=>$icon,'library'=>str_starts_with($icon, 'fab') ? 'fa-brands' : 'fa-solid'], ['aria-hidden'=>'true']); echo '</span>'; }
            if ($view !== 'icon') { echo '<span class="obx-share__text">' . esc_html($text) . '</span>'; }
            echo '</a>';
        }
        echo '</div>';
    }
}

final class Animated_Headline extends Element {
    public const PRO = 'animated-headline';
    public function get_title(): string { return 'Titular animado'; }
    public function get_icon(): string { return 'eicon-animated-headline'; }
    public function get_keywords(): array { return ['headline','titular','animado','animated','texto','rotar']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Titular']);
        $this->add_control('style', ['label'=>'Estilo','type'=>Controls_Manager::SELECT,'options'=>['rotate'=>'Palabras que rotan','highlight'=>'Resaltado'],'default'=>'highlight']);
        $this->add_control('animation', ['label'=>'Animación','type'=>Controls_Manager::SELECT,'options'=>['typing'=>'Escritura','fade'=>'Fundido','slide'=>'Deslizar hacia arriba','flip'=>'Girar'],'default'=>'typing','condition'=>['style'=>'rotate']]);
        $this->add_control('shape', ['label'=>'Forma','type'=>Controls_Manager::SELECT,'options'=>['underline'=>'Subrayado','double'=>'Doble subrayado','curly'=>'Ondulado','circle'=>'Círculo','strike'=>'Tachado','x'=>'Aspa'],'default'=>'underline','condition'=>['style'=>'highlight']]);
        $this->add_control('before', ['label'=>'Texto anterior','type'=>Controls_Manager::TEXT,'default'=>'Este es el','label_block'=>true,'dynamic'=>['active'=>true]]);
        $this->add_control('highlighted', ['label'=>'Texto resaltado','type'=>Controls_Manager::TEXT,'default'=>'mejor','label_block'=>true,'condition'=>['style'=>'highlight']]);
        $this->add_control('rotating', ['label'=>'Palabras (una por línea)','type'=>Controls_Manager::TEXTAREA,'default'=>"mejor\nmás bonito\nmás rápido",'condition'=>['style'=>'rotate']]);
        $this->add_control('after', ['label'=>'Texto posterior','type'=>Controls_Manager::TEXT,'default'=>'titular','label_block'=>true,'dynamic'=>['active'=>true]]);
        $this->add_control('loop', ['label'=>'Repetir','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('duration', ['label'=>'Duración (ms)','type'=>Controls_Manager::NUMBER,'min'=>500,'default'=>2500]);
        $this->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true]]);
        $this->add_control('tag', ['label'=>'Etiqueta HTML','type'=>Controls_Manager::SELECT,'options'=>['h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','h6'=>'H6','div'=>'div','p'=>'p'],'default'=>'h3']);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),'default'=>'center','selectors'=>['{{WRAPPER}} .obx-headline'=>'text-align: {{VALUE}};']]);
        $this->end_controls_section();
        $this->start_controls_section('shape_style', ['label'=>'Forma','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['style'=>'highlight']]);
        $this->add_control('shape_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-headline__shape path'=>'stroke: {{VALUE}};']]);
        $this->add_control('shape_width', ['label'=>'Grosor','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>1,'max'=>20]],'default'=>['unit'=>'px','size'=>8],'selectors'=>['{{WRAPPER}} .obx-headline__shape path'=>'stroke-width: {{SIZE}};']]);
        $this->add_control('shape_front', ['label'=>'Delante del texto','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'','selectors'=>['{{WRAPPER}} .obx-headline__shape'=>'z-index: 2;']]);
        $this->end_controls_section();
        $this->text_style('.obx-headline__plain', 'Texto', 'text', false);
        $this->text_style('.obx-headline__dynamic', 'Texto animado', 'dynamic', false);
    }
    /** Hand-drawn style strokes in a 500×150 box, stretched over the highlighted words. */
    private const SHAPES = [
        'underline'=>['M5 135 Q 250 115 495 135'],
        'double'=>['M5 125 Q 250 110 495 125','M15 145 Q 250 130 485 145'],
        'curly'=>['M5 135 q 25 -20 50 0 t 50 0 t 50 0 t 50 0 t 50 0 t 50 0 t 50 0 t 50 0 t 50 0 t 50 0'],
        'circle'=>['M270 12 C 120 0 10 30 15 80 C 20 140 400 150 480 100 C 530 60 420 10 230 18'],
        'strike'=>['M5 80 Q 250 70 495 82'],
        'x'=>['M10 10 L 490 140','M490 10 L 10 140'],
    ];
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $tag = in_array($s['tag'], ['h1','h2','h3','h4','h5','h6','div','p'], true) ? $s['tag'] : 'h3';
        $this->add_render_attribute('headline', 'class', 'obx-headline');
        $this->add_render_attribute('headline', 'data-duration', (string) max(500, (int) $s['duration']));
        $this->add_render_attribute('headline', 'data-loop', $s['loop'] === 'yes' ? '1' : '0');
        if ($s['style'] === 'rotate') {
            $words = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $s['rotating']) ?: [])));
            $words = $words ?: [''];
            $animation = in_array($s['animation'], ['typing','fade','slide','flip'], true) ? $s['animation'] : 'typing';
            $this->add_render_attribute('headline', 'class', ['obx-headline--rotate','obx-headline--' . $animation]);
            $this->add_render_attribute('headline', 'data-words', wp_json_encode($words));
            $dynamic = '<span class="obx-headline__dynamic" aria-hidden="true">' . esc_html($words[0]) . '</span><span class="screen-reader-text">' . esc_html(implode(', ', $words)) . '</span>';
        } else {
            $shape = isset(self::SHAPES[$s['shape']]) ? $s['shape'] : 'underline';
            $this->add_render_attribute('headline', 'class', ['obx-headline--highlight','obx-headline--' . $shape]);
            $paths = implode('', array_map(static fn($d) => '<path d="' . esc_attr($d) . '"/>', self::SHAPES[$shape]));
            $dynamic = '<span class="obx-headline__dynamic">' . esc_html($s['highlighted']) . '<svg class="obx-headline__shape" viewBox="0 0 500 150" preserveAspectRatio="none" aria-hidden="true">' . $paths . '</svg></span>';
        }
        $html = '';
        if ($s['before'] !== '') { $html .= '<span class="obx-headline__plain">' . esc_html($s['before']) . '</span> '; }
        $html .= '<span class="obx-headline__wrap">' . $dynamic . '</span>';
        if ($s['after'] !== '') { $html .= ' <span class="obx-headline__plain">' . esc_html($s['after']) . '</span>'; }
        if (!empty($s['link']['url'])) { $html = $this->open_link('link', $s['link'], 'obx-headline__link') . $html . '</a>'; }
        printf('<%1$s %2$s>%3$s</%1$s>', $tag, $this->get_render_attribute_string('headline'), $html);
    }
}

final class Table_Of_Contents extends Element {
    public const PRO = 'table-of-contents';
    public function get_title(): string { return 'Tabla de contenidos'; }
    public function get_icon(): string { return 'eicon-table-of-contents'; }
    public function get_keywords(): array { return ['toc','indice','tabla de contenidos','contents','encabezados']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Tabla de contenidos']);
        $this->add_control('title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Contenido','label_block'=>true]);
        $this->add_control('headings', ['label'=>'Encabezados','type'=>Controls_Manager::SELECT2,'multiple'=>true,'options'=>['h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','h6'=>'H6'],'default'=>['h2','h3','h4'],'label_block'=>true]);
        $this->add_control('container', ['label'=>'Buscar dentro de (selector CSS)','type'=>Controls_Manager::TEXT,'default'=>'','placeholder'=>'.obx-post-content','description'=>'Vacío: toda la página.','label_block'=>true]);
        $this->add_control('exclude', ['label'=>'Excluir (selector CSS)','type'=>Controls_Manager::TEXT,'default'=>'','placeholder'=>'.no-toc','label_block'=>true]);
        $this->add_control('marker', ['label'=>'Marcador','type'=>Controls_Manager::SELECT,'options'=>['numbers'=>'Números','bullets'=>'Viñetas','none'=>'Ninguno'],'default'=>'numbers']);
        $this->add_control('hierarchical', ['label'=>'Jerárquica','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('collapsible', ['label'=>'Plegable','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'']);
        $this->add_control('collapsed', ['label'=>'Plegada al inicio','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'','condition'=>['collapsible'=>'yes']]);
        $this->add_control('offset', ['label'=>'Margen al desplazar (px)','type'=>Controls_Manager::NUMBER,'min'=>0,'default'=>0,'description'=>'Útil con una cabecera fija.']);
        $this->end_controls_section();
        $this->box_style('.obx-toc', 'Caja', 'box');
        $this->text_style('.obx-toc__title', 'Título', 'title', false);
        $this->start_controls_section('list_style', ['label'=>'Lista','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'list_typography','selector'=>'{{WRAPPER}} .obx-toc__list a']);
        $this->add_control('list_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-toc__list a, {{WRAPPER}} .obx-toc__list li::marker'=>'color: {{VALUE}};']]);
        $this->add_control('list_hover', ['label'=>'Color al pasar el ratón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-toc__list a:hover'=>'color: {{VALUE}};']]);
        $this->add_control('list_active', ['label'=>'Color de la sección actual','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-toc__list a.is-active'=>'color: {{VALUE}};']]);
        $this->end_controls_section();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $headings = array_values(array_intersect((array) $s['headings'], ['h1','h2','h3','h4','h5','h6'])) ?: ['h2','h3'];
        $this->add_render_attribute('toc', ['class'=>['obx-toc','obx-toc--' . (in_array($s['marker'], ['numbers','bullets','none'], true) ? $s['marker'] : 'numbers')],
            'data-headings'=>implode(',', $headings),'data-container'=>sanitize_text_field($s['container']),'data-exclude'=>sanitize_text_field($s['exclude']),
            'data-hierarchical'=>$s['hierarchical'] === 'yes' ? '1' : '0','data-offset'=>(string) max(0, (int) $s['offset'])]);
        $collapsible = $s['collapsible'] === 'yes';
        $open = !$collapsible || $s['collapsed'] !== 'yes';
        $list = 'obx-toc-' . $this->get_id();
        if ($collapsible && !$open) { $this->add_render_attribute('toc', 'class', 'is-collapsed'); }
        echo '<nav ' . $this->get_render_attribute_string('toc') . ' aria-label="' . esc_attr($s['title'] ?: 'Tabla de contenidos') . '"><div class="obx-toc__header">';
        if ($s['title'] !== '') { echo '<p class="obx-toc__title">' . esc_html($s['title']) . '</p>'; }
        if ($collapsible) { echo '<button class="obx-toc__toggle" type="button" aria-expanded="' . ($open ? 'true' : 'false') . '" aria-controls="' . esc_attr($list) . '"><span class="screen-reader-text">Mostrar u ocultar</span></button>'; }
        echo '</div><div class="obx-toc__body" id="' . esc_attr($list) . '"' . ($open ? '' : ' hidden') . '><p class="obx-toc__empty">No hay encabezados en esta página.</p></div></nav>';
    }
}

final class Progress_Tracker extends Element {
    public const PRO = 'progress-tracker';
    public const PRO_CATEGORY = 'theme-elements';
    public function get_title(): string { return 'Indicador de progreso'; }
    public function get_icon(): string { return 'eicon-progress-tracker'; }
    public function get_keywords(): array { return ['progress','progreso','lectura','scroll','barra']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Indicador']);
        $this->add_control('type', ['label'=>'Tipo','type'=>Controls_Manager::SELECT,'options'=>['horizontal'=>'Barra','circular'=>'Círculo'],'default'=>'horizontal']);
        $this->add_control('relative', ['label'=>'Progreso de','type'=>Controls_Manager::SELECT,'options'=>['page'=>'Toda la página','post'=>'Contenido de la entrada','selector'=>'Selector CSS'],'default'=>'page']);
        $this->add_control('selector', ['label'=>'Selector','type'=>Controls_Manager::TEXT,'placeholder'=>'#content','condition'=>['relative'=>'selector']]);
        $this->add_control('percentage', ['label'=>'Mostrar porcentaje','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'']);
        $this->add_control('fixed', ['label'=>'Fijar arriba de la ventana','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'','condition'=>['type'=>'horizontal']]);
        $this->end_controls_section();
        $this->start_controls_section('tracker_style', ['label'=>'Indicador','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('fill', ['label'=>'Color de progreso','type'=>Controls_Manager::COLOR,'default'=>'#6d28d9','selectors'=>['{{WRAPPER}} .obx-progress'=>'--obx-progress-fill: {{VALUE}};']]);
        $this->add_control('track', ['label'=>'Color de fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-progress'=>'--obx-progress-track: {{VALUE}};']]);
        $this->add_responsive_control('thickness', ['label'=>'Grosor','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>1,'max'=>40]],'default'=>['unit'=>'px','size'=>6],'selectors'=>['{{WRAPPER}} .obx-progress'=>'--obx-progress-size: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('circle_size', ['label'=>'Tamaño del círculo','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>30,'max'=>300]],'default'=>['unit'=>'px','size'=>80],'selectors'=>['{{WRAPPER}} .obx-progress--circular'=>'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'],'condition'=>['type'=>'circular']]);
        $this->end_controls_section();
        $this->text_style('.obx-progress__value', 'Porcentaje', 'value', false);
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $type = $s['type'] === 'circular' ? 'circular' : 'horizontal';
        $target = $s['relative'] === 'post' ? '.obx-post-content, .elementor-widget-theme-post-content, .entry-content' : ($s['relative'] === 'selector' ? sanitize_text_field($s['selector']) : '');
        $this->add_render_attribute('progress', ['class'=>['obx-progress','obx-progress--' . $type],'data-target'=>$target,'role'=>'progressbar','aria-valuemin'=>'0','aria-valuemax'=>'100','aria-valuenow'=>'0','aria-label'=>'Progreso de lectura']);
        if ($type === 'horizontal' && $s['fixed'] === 'yes' && !$this->editing()) { $this->add_render_attribute('progress', 'class', 'obx-progress--fixed'); }
        echo '<div ' . $this->get_render_attribute_string('progress') . '>';
        echo $type === 'circular'
            ? '<svg viewBox="0 0 100 100" aria-hidden="true"><circle class="obx-progress__track" cx="50" cy="50" r="45"/><circle class="obx-progress__fill" cx="50" cy="50" r="45" pathLength="100"/></svg>'
            : '<div class="obx-progress__bar"><div class="obx-progress__fill"></div></div>';
        if ($s['percentage'] === 'yes') { echo '<span class="obx-progress__value">0%</span>'; }
        echo '</div>';
    }
}

final class Login extends Element {
    public const PRO = 'login';
    public function get_title(): string { return 'Inicio de sesión'; }
    public function get_icon(): string { return 'eicon-lock-user'; }
    public function get_keywords(): array { return ['login','acceso','sesion','usuario','entrar']; }
    protected function is_dynamic_content(): bool { return true; }
    public function get_script_depends(): array { return []; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Formulario']);
        $this->add_control('show_labels', ['label'=>'Etiquetas','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('user_label', ['label'=>'Etiqueta del usuario','type'=>Controls_Manager::TEXT,'default'=>'Usuario o correo electrónico']);
        $this->add_control('password_label', ['label'=>'Etiqueta de la contraseña','type'=>Controls_Manager::TEXT,'default'=>'Contraseña']);
        $this->add_control('remember', ['label'=>'Casilla «Recuérdame»','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('button', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Acceder']);
        $this->add_control('lost_password', ['label'=>'Enlace «¿Has olvidado tu contraseña?»','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('register', ['label'=>'Enlace de registro','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes','description'=>'Solo si Ajustes → Generales permite registrarse.']);
        $this->add_control('redirect', ['label'=>'Redirigir tras acceder','type'=>Controls_Manager::URL,'description'=>'Vacío: la misma página.']);
        $this->add_control('logged_in', ['label'=>'Con sesión iniciada','type'=>Controls_Manager::SELECT,'options'=>['message'=>'Mostrar saludo y salir','hide'=>'Ocultar el widget'],'default'=>'message','separator'=>'before']);
        $this->end_controls_section();
        $this->box_style('.obx-login', 'Formulario', 'form');
        $this->start_controls_section('fields_style', ['label'=>'Campos','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'fields_typography','selector'=>'{{WRAPPER}} .obx-login input[type=text], {{WRAPPER}} .obx-login input[type=password]']);
        $this->add_control('fields_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-login input[type=text], {{WRAPPER}} .obx-login input[type=password]'=>'color: {{VALUE}};']]);
        $this->add_control('fields_bg', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-login input[type=text], {{WRAPPER}} .obx-login input[type=password]'=>'background-color: {{VALUE}};']]);
        $this->add_control('labels_color', ['label'=>'Color de las etiquetas','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-login label'=>'color: {{VALUE}};']]);
        $this->end_controls_section();
        $this->button_style('.obx-login .button');
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        if (is_user_logged_in() && !$this->editing()) {
            if ($s['logged_in'] === 'hide') { return; }
            $user = wp_get_current_user();
            echo '<div class="obx-login obx-login--logged-in">Has iniciado sesión como <strong>' . esc_html($user->display_name) . '</strong>. <a href="' . esc_url(wp_logout_url((string) get_permalink())) . '">Cerrar sesión</a></div>';
            return;
        }
        $redirect = !empty($s['redirect']['url']) ? wp_validate_redirect($s['redirect']['url'], home_url('/')) : (is_singular() ? (string) get_permalink() : home_url(add_query_arg([])));
        $form = wp_login_form(['echo'=>false,'redirect'=>$redirect,'form_id'=>'obx-login-' . $this->get_id(),'id_username'=>'obx-user-' . $this->get_id(),'id_password'=>'obx-pass-' . $this->get_id(),
            'id_remember'=>'obx-remember-' . $this->get_id(),'id_submit'=>'obx-submit-' . $this->get_id(),'label_username'=>$s['user_label'],'label_password'=>$s['password_label'],
            'label_log_in'=>$s['button'] ?: 'Acceder','remember'=>$s['remember'] === 'yes']);
        echo '<div class="obx-login' . ($s['show_labels'] === 'yes' ? '' : ' obx-login--no-labels') . '">' . $form; // Core login form markup.
        $links = [];
        if ($s['lost_password'] === 'yes') { $links[] = '<a href="' . esc_url(wp_lostpassword_url($redirect)) . '">¿Has olvidado tu contraseña?</a>'; }
        if ($s['register'] === 'yes' && get_option('users_can_register')) { $links[] = '<a href="' . esc_url(wp_registration_url()) . '">Registrarse</a>'; }
        if ($links) { echo '<p class="obx-login__links">' . implode(' · ', $links) . '</p>'; }
        echo '</div>';
    }
}

final class Code_Highlight extends Element {
    public const PRO = 'code-highlight';
    private const LANGUAGES = ['markup'=>'HTML','css'=>'CSS','javascript'=>'JavaScript','typescript'=>'TypeScript','php'=>'PHP','python'=>'Python','bash'=>'Bash','sql'=>'SQL','json'=>'JSON','yaml'=>'YAML','java'=>'Java','c'=>'C','cpp'=>'C++','csharp'=>'C#','go'=>'Go','rust'=>'Rust','ruby'=>'Ruby','text'=>'Texto'];
    public function get_title(): string { return 'Resaltado de código'; }
    public function get_icon(): string { return 'eicon-code-highlight'; }
    public function get_keywords(): array { return ['code','codigo','highlight','resaltado','snippet','programacion']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Código']);
        $this->add_control('language', ['label'=>'Lenguaje','type'=>Controls_Manager::SELECT,'options'=>self::LANGUAGES,'default'=>'javascript']);
        $this->add_control('code', ['label'=>'Código','type'=>Controls_Manager::CODE,'language'=>'text','rows'=>12,'default'=>"// Escribe aquí tu código\nconst saludo = 'Hola';\nconsole.log(saludo);"]);
        $this->add_control('line_numbers', ['label'=>'Números de línea','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('copy', ['label'=>'Botón copiar','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('highlight_lines', ['label'=>'Resaltar líneas','type'=>Controls_Manager::TEXT,'placeholder'=>'1, 3-5']);
        $this->add_control('word_wrap', ['label'=>'Ajustar líneas largas','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'']);
        $this->add_control('theme', ['label'=>'Tema','type'=>Controls_Manager::SELECT,'options'=>['dark'=>'Oscuro','light'=>'Claro'],'default'=>'dark']);
        $this->add_responsive_control('height', ['label'=>'Altura máxima','type'=>Controls_Manager::SLIDER,'size_units'=>['px','vh'],'range'=>['px'=>['min'=>100,'max'=>1200]],'selectors'=>['{{WRAPPER}} .obx-code pre'=>'max-height: {{SIZE}}{{UNIT}};']]);
        $this->add_control('font_size', ['label'=>'Tamaño de letra','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em'],'range'=>['px'=>['min'=>10,'max'=>30]],'selectors'=>['{{WRAPPER}} .obx-code pre'=>'font-size: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
    }
    /** "1, 3-5" → [1,3,4,5] */
    private static function lines(string $spec): array {
        $lines = [];
        foreach (explode(',', $spec) as $part) {
            if (preg_match('/^\s*(\d+)\s*(?:-\s*(\d+))?\s*$/', $part, $m)) {
                $from = (int) $m[1]; $to = isset($m[2]) ? min((int) $m[2], $from + 1000) : $from;
                for ($i = $from; $i <= $to; $i++) { $lines[] = $i; }
            }
        }
        return array_values(array_unique($lines));
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $language = isset(self::LANGUAGES[$s['language']]) ? $s['language'] : 'text';
        $classes = 'obx-code obx-code--' . ($s['theme'] === 'light' ? 'light' : 'dark') . ($s['line_numbers'] === 'yes' ? ' obx-code--numbers' : '') . ($s['word_wrap'] === 'yes' ? ' obx-code--wrap' : '');
        echo '<div class="' . esc_attr($classes) . '" data-language="' . esc_attr($language) . '" data-highlight="' . esc_attr(implode(',', self::lines((string) $s['highlight_lines']))) . '">';
        echo '<div class="obx-code__bar"><span class="obx-code__language">' . esc_html(self::LANGUAGES[$language]) . '</span>';
        if ($s['copy'] === 'yes') { echo '<button class="obx-code__copy" type="button" data-done="Copiado">Copiar</button>'; }
        echo '</div><pre><code>' . esc_html((string) $s['code']) . '</code></pre></div>';
    }
}

/** Embeds a saved Elementor template. */
final class Template extends Element {
    public const PRO = 'template';
    private static array $rendering = [];
    public function get_title(): string { return 'Plantilla'; }
    public function get_icon(): string { return 'eicon-document-file'; }
    public function get_keywords(): array { return ['template','plantilla','guardada','library','biblioteca']; }
    protected function is_dynamic_content(): bool { return true; }
    public function get_script_depends(): array { return []; }
    protected function register_controls(): void {
        $options = [];
        foreach (get_posts(['post_type'=>'elementor_library','post_status'=>'publish','posts_per_page'=>200,'orderby'=>'title','order'=>'ASC']) as $post) {
            $type = (string) get_post_meta($post->ID, '_elementor_template_type', true);
            if (in_array($type, ['kit','obx-header','obx-footer','obx-single','obx-archive','obx-error-404'], true)) { continue; }
            $options[(string) $post->ID] = $post->post_title . ' (' . $type . ')';
        }
        $this->start_controls_section('content', ['label'=>'Plantilla']);
        $this->add_control('template_id', ['label'=>'Elige una plantilla','type'=>Controls_Manager::SELECT2,'options'=>$options,'label_block'=>true]);
        $this->end_controls_section();
    }
    protected function render(): void {
        $id = absint($this->get_settings_for_display('template_id'));
        if (!$id || get_post_type($id) !== 'elementor_library' || get_post_status($id) !== 'publish') { $this->placeholder('Elige una plantilla guardada.'); return; }
        if (isset(self::$rendering[$id])) { $this->placeholder('Una plantilla no puede incluirse a sí misma.'); return; }
        self::$rendering[$id] = true;
        echo '<div class="obx-template">' . Elementor::$instance->frontend->get_builder_content_for_display($id, true) . '</div>'; // Elementor output.
        unset(self::$rendering[$id]);
    }
}
