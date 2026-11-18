<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Utils;

if (!defined('ABSPATH')) { exit; }

/**
 * General purpose widgets offered in the «Pro» panel category. Setting keys, value lists and
 * defaults follow Elementor Pro's saved data, so templates move between both plugins.
 */
abstract class Element extends Base {
    public const PRO_CATEGORY = 'pro-elements';
    /** Keys used by v0.3 of this plugin under the same widget name → current keys. */
    protected const V03 = [];
    protected function is_dynamic_content(): bool { return false; }
    public function get_script_depends(): array { return ['obx-widgets']; }

    public static function wants_upgrade(string $name, array $settings): bool {
        return $name === static::PRO && static::V03 && array_intersect_key($settings, static::V03) && !array_intersect_key($settings, array_flip(static::V03));
    }
    protected static function upgrade_settings(array $settings): array { return self::rename($settings, static::V03); }

    /** ['{{WRAPPER}} .a' => decl] for one selector. */
    protected static function css(string $selector, string $declaration): array {
        return ['{{WRAPPER}} ' . implode(', {{WRAPPER}} ', array_map('trim', explode(',', $selector))) => $declaration];
    }

    protected function color(string $key, string $label, string $selector, string $property = 'color', array $extra = []): void {
        $this->add_control($key, ['label'=>$label,'type'=>Controls_Manager::COLOR,'selectors'=>self::css($selector, $property . ': {{VALUE}};')] + $extra);
    }

    protected function slider(string $key, string $label, string $selector, string $declaration, array $extra = [], bool $responsive = false): void {
        $args = ['label'=>$label,'type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','%','custom'],'selectors'=>self::css($selector, $declaration)] + $extra;
        $responsive ? $this->add_responsive_control($key, $args) : $this->add_control($key, $args);
    }

    protected function dimensions(string $key, string $label, string $selector, string $property, array $extra = [], bool $responsive = true): void {
        $args = ['label'=>$label,'type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'selectors'=>self::css($selector, $property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};')] + $extra;
        $responsive ? $this->add_responsive_control($key, $args) : $this->add_control($key, $args);
    }

    protected function typography(string $name, string $selector, array $extra = []): void {
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>$name,'selector'=>(string) array_key_first(self::css($selector, ''))] + $extra);
    }

    protected static function heading_tags(bool $paragraph = true): array {
        $tags = ['h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','h6'=>'H6','div'=>'div','span'=>'span'];
        return $paragraph ? $tags + ['p'=>'p'] : $tags;
    }

    protected static function pick_tag($value, string $default, bool $paragraph = true): string {
        return array_key_exists((string) $value, self::heading_tags($paragraph)) ? (string) $value : $default;
    }

    protected static function sizes(): array { return ['xs'=>'Muy pequeño','sm'=>'Pequeño','md'=>'Mediano','lg'=>'Grande','xl'=>'Muy grande']; }

    /** Start/center/end choices that also accept the left/right values stored by older data. */
    protected static function logical_choose(): array {
        return ['options'=>self::logical_align(),'selectors_dictionary'=>['left'=>'start','right'=>'end']];
    }

    /** Icon views shared by call to action and flip box («default», «stacked», «framed»). */
    protected function icon_view_controls(string $selector, array $condition): void {
        $this->add_control('icon_view', ['label'=>'Vista','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>['default'=>'Predeterminada','stacked'=>'Apilada','framed'=>'Enmarcada'],'condition'=>$condition]);
        $this->add_control('icon_shape', ['label'=>'Forma','type'=>Controls_Manager::SELECT,'default'=>'circle','options'=>['circle'=>'Círculo','square'=>'Cuadrado'],'condition'=>$condition + ['icon_view!'=>'default']]);
        $this->slider('icon_spacing', 'Separación', $selector, 'margin-bottom: {{SIZE}}{{UNIT}};', ['condition'=>$condition]);
        $this->color('icon_primary_color', 'Color principal', $selector, '--obx-icon-primary', ['condition'=>$condition]);
        $this->color('icon_secondary_color', 'Color secundario', $selector, '--obx-icon-secondary', ['condition'=>$condition + ['icon_view!'=>'default']]);
        $this->slider('icon_size', 'Tamaño', $selector, 'font-size: {{SIZE}}{{UNIT}};', ['range'=>['px'=>['min'=>6,'max'=>300]],'condition'=>$condition]);
        $this->slider('icon_padding', 'Relleno', $selector . ' .obx-icon', 'padding: {{SIZE}}{{UNIT}};', ['condition'=>$condition + ['icon_view!'=>'default']]);
        $this->slider('icon_border_width', 'Grosor del borde', $selector . ' .obx-icon', 'border-width: {{SIZE}}{{UNIT}};', ['condition'=>$condition + ['icon_view'=>'framed']]);
        $this->dimensions('icon_border_radius', 'Radio del borde', $selector . ' .obx-icon', 'border-radius', ['condition'=>$condition + ['icon_view!'=>'default']], false);
    }

    protected function render_icon_view(array $icon, array $s): string {
        $html = Icons_Manager::try_get_icon_html($icon, ['aria-hidden'=>'true']);
        if (!$html) { return ''; }
        $view = in_array($s['icon_view'] ?? 'default', ['default','stacked','framed'], true) ? $s['icon_view'] : 'default';
        $shape = ($s['icon_shape'] ?? 'circle') === 'square' ? 'square' : 'circle';
        return '<span class="obx-icon obx-icon--' . $view . ($view !== 'default' ? ' obx-icon--' . $shape : '') . '">' . $html . '</span>';
    }

    /** Elementor's own button classes, so the site kit's button styles apply. */
    protected static function button_classes(string $class, $size): string {
        $size = array_key_exists((string) $size, self::sizes()) ? (string) $size : 'sm';
        return 'elementor-button elementor-size-' . $size . ' ' . $class;
    }
}

final class Blockquote extends Element {
    public const PRO = 'blockquote';
    protected const V03 = ['skin'=>'blockquote_skin','quote'=>'blockquote_content','author'=>'author_name','share'=>'tweet_button','share_label'=>'tweet_button_label','align'=>'alignment','text_color'=>'content_text_color','author_color'=>'author_text_color','accent'=>'border_color','box_bg'=>'box_background_color'];
    public function get_title(): string { return 'Cita'; }
    public function get_icon(): string { return 'eicon-blockquote'; }
    public function get_keywords(): array { return ['blockquote','cita','quote','frase','testimonio','tweet']; }
    public function get_style_depends(): array { return ['obx-frontend','elementor-icons-fa-brands']; }

    protected static function upgrade_settings(array $s): array {
        $s['tweet_button'] = $s['share'] ?? '';
        return parent::upgrade_settings($s);
    }

    protected function register_controls(): void {
        $this->start_controls_section('section_blockquote_content', ['label'=>'Cita']);
        $this->add_control('blockquote_skin', ['label'=>'Estilo','type'=>Controls_Manager::SELECT,'default'=>'border','options'=>['border'=>'Borde','quotation'=>'Comillas','boxed'=>'Caja','clean'=>'Limpio'],'prefix_class'=>'obx-blockquote--skin-']);
        $this->add_control('blockquote_content', ['label'=>'Texto','type'=>Controls_Manager::TEXTAREA,'rows'=>5,'default'=>'Lo que haces con constancia acaba hablando por ti. Escribe aquí la frase que quieres destacar.','dynamic'=>['active'=>true]]);
        $this->add_control('author_name', ['label'=>'Autor','type'=>Controls_Manager::TEXT,'default'=>'Nombre del autor','dynamic'=>['active'=>true],'separator'=>'after']);
        $this->add_control('tweet_button', ['label'=>'Botón para publicar en X','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('tweet_button_view', ['label'=>'Mostrar','type'=>Controls_Manager::SELECT,'default'=>'icon-text','options'=>['icon-text'=>'Icono y texto','icon'=>'Icono','text'=>'Texto'],'condition'=>['tweet_button'=>'yes']]);
        $this->add_control('tweet_button_skin', ['label'=>'Aspecto','type'=>Controls_Manager::SELECT,'default'=>'classic','options'=>['classic'=>'Clásico','bubble'=>'Burbuja','link'=>'Enlace'],'condition'=>['tweet_button'=>'yes']]);
        $this->add_control('tweet_button_label', ['label'=>'Texto','type'=>Controls_Manager::TEXT,'default'=>'Publicar','condition'=>['tweet_button'=>'yes','tweet_button_view!'=>'icon']]);
        $this->add_control('user_name', ['label'=>'Usuario','type'=>Controls_Manager::TEXT,'placeholder'=>'@usuario','condition'=>['tweet_button'=>'yes']]);
        $this->add_control('url_type', ['label'=>'Enlace compartido','type'=>Controls_Manager::SELECT,'default'=>'current_page','options'=>['current_page'=>'Página actual','none'=>'Ninguno','custom'=>'Personalizado'],'condition'=>['tweet_button'=>'yes']]);
        $this->add_control('url', ['label'=>'Enlace','type'=>Controls_Manager::TEXT,'input_type'=>'url','placeholder'=>'https://…','condition'=>['tweet_button'=>'yes','url_type'=>'custom']]);
        $this->end_controls_section();

        $this->start_controls_section('section_content_style', ['label'=>'Contenido','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),'prefix_class'=>'obx-blockquote%s--align-','selectors'=>self::css('.obx-quote', 'text-align: {{VALUE}};')]);
        $this->add_control('heading_content_style', ['label'=>'Texto','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('content_text_color', 'Color', '.obx-quote__content');
        $this->typography('content_typography', '.obx-quote__content');
        $this->slider('content_gap', 'Separación', '.obx-quote__content', 'margin-bottom: {{SIZE}}{{UNIT}};', [], true);
        $this->add_control('heading_author_style', ['label'=>'Autor','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('author_text_color', 'Color', '.obx-quote__author');
        $this->typography('author_typography', '.obx-quote__author');
        $this->slider('author_gap', 'Separación', '.obx-quote__author', 'margin-bottom: {{SIZE}}{{UNIT}};', ['default'=>['size'=>20],'condition'=>['alignment'=>'center','tweet_button'=>'yes']], true);
        $this->end_controls_section();

        $this->start_controls_section('section_button_style', ['label'=>'Botón','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['tweet_button'=>'yes']]);
        $this->slider('button_size', 'Tamaño', '.obx-quote__tweet', 'font-size: calc({{SIZE}}{{UNIT}} * 10);', ['size_units'=>['px'],'range'=>['px'=>['min'=>0.5,'max'=>2,'step'=>0.1]]], true);
        $this->slider('button_border_radius', 'Radio del borde', '.obx-quote__tweet', 'border-radius: {{SIZE}}{{UNIT}};', [], true);
        $this->start_controls_tabs('tabs_button_style');
        $this->start_controls_tab('tab_button_normal', ['label'=>'Normal']);
        $this->color('button_background_color', 'Fondo', '.obx-quote__tweet', '--obx-tweet-bg', ['condition'=>['tweet_button_skin!'=>'link']]);
        $this->color('button_text_color', 'Color del texto', '.obx-quote__tweet', 'color');
        $this->end_controls_tab();
        $this->start_controls_tab('tab_button_hover', ['label'=>'Al pasar el ratón']);
        $this->color('button_background_color_hover', 'Fondo al pasar el ratón', '.obx-quote__tweet:hover', '--obx-tweet-bg', ['condition'=>['tweet_button_skin!'=>'link']]);
        $this->color('button_text_color_hover', 'Color al pasar el ratón', '.obx-quote__tweet:hover', 'color');
        $this->slider('button_transition_duration', 'Duración de la transición', '.obx-quote__tweet', 'transition-duration: {{SIZE}}{{UNIT}};', ['size_units'=>['ms','s'],'default'=>['unit'=>'ms'],'range'=>['ms'=>['max'=>3000]]]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->typography('button_typography', '.obx-quote__tweet', ['condition'=>['tweet_button_view!'=>'icon']]);
        $this->end_controls_section();

        $this->start_controls_section('section_border_style', ['label'=>'Borde','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['blockquote_skin'=>'border']]);
        $this->start_controls_tabs('tabs_border_style');
        foreach (['' => ['normal', 'Normal'], '_hover' => ['hover', 'Al pasar el ratón']] as $suffix => [$tab, $label]) {
            $this->start_controls_tab('tab_border_' . $tab, ['label'=>$label]);
            $sel = '.obx-quote' . ($suffix ? ':hover' : '');
            $this->color('border_color' . $suffix, 'Color', $sel, 'border-color');
            $this->slider('border_width' . $suffix, 'Grosor', $sel, 'border-inline-start-width: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>20]]], true);
            $this->slider('border_gap' . $suffix, 'Separación', $sel, 'padding-inline-start: {{SIZE}}{{UNIT}};', [], true);
            if ($suffix) { $this->slider('border_transition_duration', 'Duración de la transición', '.obx-quote', 'transition-duration: {{SIZE}}{{UNIT}};', ['size_units'=>['ms','s'],'default'=>['unit'=>'ms'],'range'=>['ms'=>['max'=>3000]]]); }
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->slider('border_vertical_padding', 'Relleno vertical', '.obx-quote', 'padding-block: {{SIZE}}{{UNIT}};', [], true);
        $this->end_controls_section();

        $this->start_controls_section('section_box_style', ['label'=>'Caja','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['blockquote_skin!'=>'border']]);
        $this->slider('box_padding', 'Relleno', '.obx-quote', 'padding: {{SIZE}}{{UNIT}};', [], true);
        $this->start_controls_tabs('tabs_box_style');
        foreach (['' => ['normal', 'Normal'], '_hover' => ['hover', 'Al pasar el ratón']] as $suffix => [$tab, $label]) {
            $this->start_controls_tab('tab_box_' . $tab, ['label'=>$label]);
            $sel = '.obx-quote' . ($suffix ? ':hover' : '');
            $this->color('box_background_color' . $suffix, 'Fondo', $sel, 'background-color');
            $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'box_border' . $suffix,'selector'=>'{{WRAPPER}} ' . $sel]);
            $this->slider('box_border_radius' . $suffix, 'Radio del borde', $sel, 'border-radius: {{SIZE}}{{UNIT}};', [], true);
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'box_box_shadow' . $suffix,'selector'=>'{{WRAPPER}} ' . $sel]);
            if ($suffix) { $this->slider('box_transition_duration', 'Duración de la transición', '.obx-quote', 'transition-duration: {{SIZE}}{{UNIT}};', ['size_units'=>['ms','s'],'default'=>['unit'=>'ms'],'range'=>['ms'=>['max'=>3000]]]); }
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->end_controls_section();

        $this->start_controls_section('section_quote_style', ['label'=>'Comillas','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['blockquote_skin'=>'quotation']]);
        $this->color('quote_text_color', 'Color', '.obx-quote', '--obx-quote-mark');
        $this->slider('quote_size', 'Tamaño', '.obx-quote', '--obx-quote-size: {{SIZE}};', ['size_units'=>['px'],'default'=>['size'=>1],'range'=>['px'=>['min'=>0.5,'max'=>2,'step'=>0.1]]], true);
        $this->slider('quote_gap', 'Separación', '.obx-quote__content', 'margin-top: {{SIZE}}{{UNIT}};', [], true);
        $this->end_controls_section();
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $skin = in_array($s['blockquote_skin'] ?? '', ['border','quotation','boxed','clean'], true) ? $s['blockquote_skin'] : 'border';
        echo '<blockquote class="obx-quote obx-quote--' . esc_attr($skin) . '"><p class="obx-quote__content">' . nl2br(esc_html((string) $s['blockquote_content'])) . '</p>';
        $tweet = ($s['tweet_button'] ?? '') === 'yes';
        if ((string) $s['author_name'] !== '' || $tweet) {
            echo '<footer class="obx-quote__footer">';
            if ((string) $s['author_name'] !== '') { echo '<cite class="obx-quote__author">' . esc_html($s['author_name']) . '</cite>'; }
            if ($tweet) {
                $view = in_array($s['tweet_button_view'] ?? '', ['icon-text','icon','text'], true) ? $s['tweet_button_view'] : 'icon-text';
                $style = in_array($s['tweet_button_skin'] ?? '', ['classic','bubble','link'], true) ? $s['tweet_button_skin'] : 'classic';
                $args = ['text'=>wp_strip_all_tags((string) $s['blockquote_content']) . ((string) $s['author_name'] !== '' ? ' — ' . $s['author_name'] : '')];
                $user = ltrim((string) ($s['user_name'] ?? ''), '@');
                if ($user !== '') { $args['via'] = $user; }
                $type = $s['url_type'] ?? 'current_page';
                if ($type === 'current_page') { $args['url'] = is_singular() ? (string) get_permalink() : home_url(add_query_arg([])); }
                if ($type === 'custom' && ($s['url'] ?? '') !== '') { $args['url'] = esc_url_raw((string) $s['url']); }
                $label = (string) ($s['tweet_button_label'] ?? '');
                echo '<a class="obx-quote__tweet obx-quote__tweet--' . esc_attr($style) . '" href="' . esc_url('https://x.com/intent/post?' . http_build_query($args, '', '&', PHP_QUERY_RFC3986)) . '" target="_blank" rel="noopener nofollow"' . ($view === 'icon' ? ' aria-label="Publicar en X"' : '') . '>';
                if ($view !== 'text') { echo Icons_Manager::try_get_icon_html(['value'=>'fab fa-x-twitter','library'=>'fa-brands'], ['aria-hidden'=>'true']); }
                if ($view !== 'icon' && $label !== '') { echo '<span>' . esc_html($label) . '</span>'; }
                echo '</a>';
            }
            echo '</footer>';
        }
        echo '</blockquote>';
    }
}

final class Call_To_Action extends Element {
    public const PRO = 'call-to-action';
    protected const V03 = ['image'=>'bg_image','ribbon'=>'ribbon_title','min_height'=>'min-height','align'=>'alignment','content_bg'=>'content_bg_color','overlay'=>'overlay_color'];
    public const ANIMATIONS = [''=>'Ninguna','grow'=>'Crecer','shrink'=>'Encoger','move-right'=>'Mover a la derecha','move-left'=>'Mover a la izquierda','move-up'=>'Mover arriba','move-down'=>'Mover abajo','enter-from-right'=>'Entrar por la derecha','enter-from-left'=>'Entrar por la izquierda','enter-from-top'=>'Entrar por arriba','enter-from-bottom'=>'Entrar por abajo','enter-zoom-in'=>'Entrar con zoom','enter-zoom-out'=>'Entrar alejando','fade-in'=>'Aparecer','exit-to-right'=>'Salir por la derecha','exit-to-left'=>'Salir por la izquierda','exit-to-top'=>'Salir por arriba','exit-to-bottom'=>'Salir por abajo','exit-zoom-in'=>'Salir con zoom','exit-zoom-out'=>'Salir alejando','fade-out'=>'Desvanecer'];
    public function get_title(): string { return 'Llamada a la acción'; }
    public function get_icon(): string { return 'eicon-image-rollover'; }
    public function get_keywords(): array { return ['cta','call to action','llamada','accion','banner']; }

    protected static function upgrade_settings(array $s): array {
        if (isset($s['image_position'])) { $s['layout'] = ['top'=>'above','left'=>'left','right'=>'right'][$s['image_position']] ?? 'above'; unset($s['image_position']); }
        if (($s['hover_zoom'] ?? 'yes') !== 'yes') { $s['transformation'] = ''; }
        unset($s['hover_zoom']);
        return parent::upgrade_settings($s);
    }

    protected function register_controls(): void {
        $this->start_controls_section('section_main_image', ['label'=>'Imagen']);
        $this->add_control('skin', ['label'=>'Apariencia','type'=>Controls_Manager::SELECT,'default'=>'classic','options'=>['classic'=>'Clásica','cover'=>'Portada'],'prefix_class'=>'obx-cta--skin-','render_type'=>'template']);
        $this->add_responsive_control('layout', ['label'=>'Posición','type'=>Controls_Manager::CHOOSE,'options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'above'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right'],'below'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']],'prefix_class'=>'obx-cta-%slayout-image-','condition'=>['skin!'=>'cover']]);
        $this->add_control('bg_image', ['label'=>'Imagen','type'=>Controls_Manager::MEDIA,'default'=>['url'=>Utils::get_placeholder_image_src()],'dynamic'=>['active'=>true]]);
        $this->add_group_control(Group_Control_Image_Size::get_type(), ['name'=>'bg_image','label'=>'Tamaño de imagen','default'=>'large','condition'=>['bg_image[id]!'=>'']]);
        $this->end_controls_section();

        $this->start_controls_section('section_content', ['label'=>'Contenido']);
        $this->add_control('graphic_element', ['label'=>'Elemento gráfico','type'=>Controls_Manager::CHOOSE,'default'=>'none','options'=>['none'=>['title'=>'Ninguno','icon'=>'eicon-ban'],'image'=>['title'=>'Imagen','icon'=>'eicon-image-bold'],'icon'=>['title'=>'Icono','icon'=>'eicon-star']]]);
        $this->add_control('graphic_image', ['label'=>'Imagen','type'=>Controls_Manager::MEDIA,'default'=>['url'=>Utils::get_placeholder_image_src()],'dynamic'=>['active'=>true],'condition'=>['graphic_element'=>'image']]);
        $this->add_group_control(Group_Control_Image_Size::get_type(), ['name'=>'graphic_image','default'=>'thumbnail','condition'=>['graphic_element'=>'image','graphic_image[id]!'=>'']]);
        $this->add_control('selected_icon', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'fa4compatibility'=>'icon','default'=>['value'=>'fas fa-star','library'=>'fa-solid'],'condition'=>['graphic_element'=>'icon']]);
        $this->add_control('title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Esto es una llamada a la acción','label_block'=>true,'dynamic'=>['active'=>true],'separator'=>'before']);
        $this->add_control('title_tag', ['label'=>'Etiqueta del título','type'=>Controls_Manager::SELECT,'default'=>'h2','options'=>self::heading_tags(false),'condition'=>['title!'=>'']]);
        $this->add_control('description', ['label'=>'Descripción','type'=>Controls_Manager::TEXTAREA,'default'=>'Cuenta en una frase por qué merece la pena hacer clic.','dynamic'=>['active'=>true]]);
        $this->add_control('description_tag', ['label'=>'Etiqueta de la descripción','type'=>Controls_Manager::SELECT,'default'=>'div','options'=>self::heading_tags(false),'condition'=>['description!'=>'']]);
        $this->add_control('button', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Haz clic aquí','dynamic'=>['active'=>true],'separator'=>'before']);
        $this->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true]]);
        $this->add_control('link_click', ['label'=>'Aplicar el enlace a','type'=>Controls_Manager::SELECT,'default'=>'button','options'=>['box'=>'Toda la caja','button'=>'Solo el botón'],'condition'=>['link[url]!'=>'']]);
        $this->end_controls_section();

        $this->start_controls_section('section_ribbon', ['label'=>'Cinta']);
        $this->add_control('ribbon_title', ['label'=>'Texto','type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true]]);
        $this->add_control('ribbon_horizontal_position', ['label'=>'Posición','type'=>Controls_Manager::CHOOSE,'options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right']],'condition'=>['ribbon_title!'=>'']]);
        $this->end_controls_section();

        $this->start_controls_section('box_style', ['label'=>'Caja','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('min-height', 'Altura', '.obx-cta__content', 'min-height: {{SIZE}}{{UNIT}};', ['size_units'=>['px','vh','em','rem','custom'],'range'=>['px'=>['min'=>100,'max'=>1000]]], true);
        $this->add_responsive_control('alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'default'=>'center','selectors'=>self::css('.obx-cta__content', 'text-align: {{VALUE}}; --obx-cta-align: {{VALUE}};')] + self::logical_choose());
        $this->add_control('vertical_position', ['label'=>'Posición vertical','type'=>Controls_Manager::CHOOSE,'options'=>['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'middle'=>['title'=>'Centro','icon'=>'eicon-v-align-middle'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']],'selectors_dictionary'=>['top'=>'flex-start','middle'=>'center','bottom'=>'flex-end'],'selectors'=>self::css('.obx-cta__content', 'justify-content: {{VALUE}};')]);
        $this->dimensions('padding', 'Relleno', '.obx-cta__content', 'padding');
        $this->add_control('heading_bg_image_style', ['label'=>'Imagen','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['bg_image[url]!'=>'','skin'=>'classic']]);
        $this->slider('image_min_width', 'Ancho mínimo', '.obx-cta__bg', 'min-width: {{SIZE}}{{UNIT}};', ['size_units'=>['px','%','em','rem','vw','custom'],'range'=>['px'=>['max'=>500]],'condition'=>['skin'=>'classic','layout!'=>'above']], true);
        $this->slider('image_min_height', 'Altura mínima', '.obx-cta__bg', 'min-height: {{SIZE}}{{UNIT}};', ['size_units'=>['px','vh','em','rem','custom'],'range'=>['px'=>['max'=>500]],'condition'=>['skin'=>'classic']], true);
        $this->end_controls_section();

        $this->start_controls_section('graphic_element_style', ['label'=>'Elemento gráfico','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['graphic_element!'=>'none']]);
        $this->slider('graphic_image_spacing', 'Separación', '.obx-cta__image', 'margin-bottom: {{SIZE}}{{UNIT}};', ['condition'=>['graphic_element'=>'image']]);
        $this->slider('graphic_image_width', 'Tamaño', '.obx-cta__image img', 'width: {{SIZE}}{{UNIT}};', ['default'=>['unit'=>'%'],'condition'=>['graphic_element'=>'image']]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'graphic_image_border','selector'=>'{{WRAPPER}} .obx-cta__image img','condition'=>['graphic_element'=>'image']]);
        $this->slider('graphic_image_border_radius', 'Radio del borde', '.obx-cta__image img', 'border-radius: {{SIZE}}{{UNIT}};', ['condition'=>['graphic_element'=>'image']]);
        $this->icon_view_controls('.obx-cta__icon', ['graphic_element'=>'icon']);
        $this->end_controls_section();

        $this->start_controls_section('section_content_style', ['label'=>'Contenido','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('heading_style_title', ['label'=>'Título','type'=>Controls_Manager::HEADING,'condition'=>['title!'=>'']]);
        $this->typography('title_typography', '.obx-cta__title', ['condition'=>['title!'=>'']]);
        $this->add_group_control(Group_Control_Text_Stroke::get_type(), ['name'=>'text_stroke','selector'=>'{{WRAPPER}} .obx-cta__title']);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'title_text_shadow','selector'=>'{{WRAPPER}} .obx-cta__title']);
        $this->slider('title_spacing', 'Separación', '.obx-cta__title', 'margin-bottom: {{SIZE}}{{UNIT}};', ['condition'=>['title!'=>'']], true);
        $this->add_control('heading_style_description', ['label'=>'Descripción','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['description!'=>'']]);
        $this->typography('description_typography', '.obx-cta__description', ['condition'=>['description!'=>'']]);
        $this->slider('description_spacing', 'Separación', '.obx-cta__description', 'margin-bottom: {{SIZE}}{{UNIT}};', ['condition'=>['description!'=>'']], true);
        $this->start_controls_tabs('color_tabs', ['separator'=>'before']);
        foreach (['' => ['normal', 'Normal'], '_hover' => ['hover', 'Al pasar el ratón']] as $suffix => [$tab, $label]) {
            $this->start_controls_tab('colors_' . $tab, ['label'=>$label]);
            $hover = $suffix ? '.obx-cta:hover ' : '';
            $this->color('content_bg_color' . $suffix, 'Fondo', $hover . '.obx-cta__content', 'background-color', ['condition'=>['skin'=>'classic']]);
            $this->color('title_color' . $suffix, 'Título', $hover . '.obx-cta__title', 'color', ['condition'=>['title!'=>'']]);
            $this->color('description_color' . $suffix, 'Descripción', $hover . '.obx-cta__description', 'color', ['condition'=>['description!'=>'']]);
            $this->color('button_color' . $suffix, 'Botón', $hover . '.obx-cta__button', 'border-color', ['condition'=>['button!'=>'']]);
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->end_controls_section();

        $this->start_controls_section('button_style', ['label'=>'Botón','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['button!'=>'']]);
        $this->add_control('button_size', ['label'=>'Tamaño','type'=>Controls_Manager::SELECT,'default'=>'sm','options'=>self::sizes()]);
        $this->typography('button_typography', '.obx-cta__button');
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'button_text_shadow','selector'=>'{{WRAPPER}} .obx-cta__button']);
        $this->start_controls_tabs('button_tabs');
        foreach (['' => ['normal', 'Normal', ''], '_hover' => ['hover', 'Al pasar el ratón', ':hover']] as $infix => [$tab, $label, $state]) {
            $this->start_controls_tab('button_' . $tab, ['label'=>$label]);
            $this->color('button' . $infix . '_text_color', 'Color del texto', '.obx-cta__button' . $state, 'color');
            $this->color('button' . $infix . '_background_color', 'Fondo', '.obx-cta__button' . $state, 'background-color');
            $this->color('button' . $infix . '_border_color', 'Color del borde', '.obx-cta__button' . $state, 'border-color');
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->slider('button_border_width', 'Grosor del borde', '.obx-cta__button', 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>20]],'separator'=>'before']);
        $this->slider('button_border_radius', 'Radio del borde', '.obx-cta__button', 'border-radius: {{SIZE}}{{UNIT}};', ['range'=>['px'=>['max'=>100]]]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'button_box_shadow','selector'=>'{{WRAPPER}} .obx-cta__button']);
        $this->dimensions('button_padding', 'Relleno', '.obx-cta__button', 'padding');
        $this->end_controls_section();

        $this->start_controls_section('section_ribbon_style', ['label'=>'Cinta','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['ribbon_title!'=>'']]);
        $this->color('ribbon_bg_color', 'Fondo', '.obx-ribbon__inner', 'background-color');
        $this->color('ribbon_text_color', 'Color del texto', '.obx-ribbon__inner', 'color');
        $this->slider('ribbon_distance', 'Distancia', '.obx-ribbon__inner', 'margin-top: {{SIZE}}{{UNIT}}; transform: translateY(-50%) translateX(-50%) translateX({{SIZE}}{{UNIT}}) rotate(-45deg);', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>50]]], true);
        $this->typography('ribbon_typography', '.obx-ribbon__inner');
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'box_shadow','selector'=>'{{WRAPPER}} .obx-ribbon__inner']);
        $this->end_controls_section();

        $this->start_controls_section('hover_effects', ['label'=>'Efectos al pasar el ratón','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('content_hover_heading', ['label'=>'Contenido','type'=>Controls_Manager::HEADING,'condition'=>['skin'=>'cover']]);
        $this->add_control('content_animation', ['label'=>'Animación','type'=>Controls_Manager::SELECT,'default'=>'grow','options'=>self::ANIMATIONS,'condition'=>['skin'=>'cover']]);
        $this->add_control('animation_class', ['type'=>Controls_Manager::HIDDEN,'default'=>'animated-content','prefix_class'=>'obx-cta--','condition'=>['content_animation!'=>'']]);
        $this->add_control('content_animation_duration', ['label'=>'Duración (ms)','type'=>Controls_Manager::SLIDER,'size_units'=>['px'],'default'=>['size'=>1000],'range'=>['px'=>['min'=>0,'max'=>3000,'step'=>100]],'selectors'=>self::css('.obx-cta__content > *', 'transition-duration: {{SIZE}}ms;'),'condition'=>['content_animation!'=>'','skin'=>'cover']]);
        $this->add_control('sequenced_animation', ['label'=>'Animación escalonada','type'=>Controls_Manager::SWITCHER,'condition'=>['content_animation!'=>'','skin'=>'cover']]);
        $this->add_control('background_hover_heading', ['label'=>'Fondo','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['skin'=>'cover']]);
        $this->add_control('transformation', ['label'=>'Animación','type'=>Controls_Manager::SELECT,'default'=>'zoom-in','options'=>[''=>'Ninguna','zoom-in'=>'Acercar','zoom-out'=>'Alejar','move-left'=>'Mover a la izquierda','move-right'=>'Mover a la derecha','move-up'=>'Mover arriba','move-down'=>'Mover abajo']]);
        $this->start_controls_tabs('bg_effects_tabs');
        $this->start_controls_tab('bg_effects_normal', ['label'=>'Normal']);
        $this->color('overlay_color', 'Color de la capa', '.obx-cta__bg-overlay', 'background-color');
        $this->add_group_control(Group_Control_Css_Filter::get_type(), ['name'=>'bg_filters','selector'=>'{{WRAPPER}} .obx-cta__bg-image']);
        $this->add_control('overlay_blend_mode', ['label'=>'Modo de fusión','type'=>Controls_Manager::SELECT,'options'=>[''=>'Normal','multiply'=>'Multiplicar','screen'=>'Trama','overlay'=>'Superponer','darken'=>'Oscurecer','lighten'=>'Aclarar','color-dodge'=>'Sobreexponer color','color-burn'=>'Subexponer color','hue'=>'Tono','saturation'=>'Saturación','color'=>'Color','exclusion'=>'Exclusión','luminosity'=>'Luminosidad'],'selectors'=>self::css('.obx-cta__bg-overlay', 'mix-blend-mode: {{VALUE}};')]);
        $this->end_controls_tab();
        $this->start_controls_tab('bg_effects_hover', ['label'=>'Al pasar el ratón']);
        $this->color('overlay_color_hover', 'Color de la capa', '.obx-cta:hover .obx-cta__bg-overlay', 'background-color');
        $this->add_group_control(Group_Control_Css_Filter::get_type(), ['name'=>'bg_filters_hover','selector'=>'{{WRAPPER}} .obx-cta:hover .obx-cta__bg-image']);
        $this->add_control('effect_duration', ['label'=>'Duración de la transición (ms)','type'=>Controls_Manager::SLIDER,'size_units'=>['px'],'default'=>['size'=>1500],'range'=>['px'=>['min'=>0,'max'=>3000,'step'=>100]],'selectors'=>self::css('.obx-cta__bg-image, .obx-cta__bg-overlay, .obx-cta__content', 'transition-duration: {{SIZE}}ms;'),'separator'=>'before']);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $skin = ($s['skin'] ?? 'classic') === 'cover' ? 'cover' : 'classic';
        $has_link = !empty($s['link']['url']);
        $box_link = $has_link && ($s['link_click'] ?? 'button') === 'box';
        $wrapper = $box_link ? 'a' : 'div';
        $classes = ['obx-cta','obx-cta--' . $skin];
        $transformation = (string) ($s['transformation'] ?? 'zoom-in');
        if ($transformation !== '') { $classes[] = 'obx-cta--bg-' . sanitize_html_class($transformation); }
        if ($skin === 'cover' && ($s['content_animation'] ?? 'grow') !== '' && array_key_exists($s['content_animation'] ?? 'grow', self::ANIMATIONS)) {
            $classes[] = 'obx-cta--anim-' . ($s['content_animation'] ?? 'grow');
            if (($s['sequenced_animation'] ?? '') === 'yes') { $classes[] = 'obx-cta--sequenced'; }
        }
        if ($box_link) { $this->add_link_attributes('box', $s['link']); }
        $this->add_render_attribute('box', 'class', $classes);
        echo '<' . $wrapper . ' ' . $this->get_render_attribute_string('box') . '>';
        if (!empty($s['bg_image']['url'])) {
            $src = !empty($s['bg_image']['id']) ? (string) Group_Control_Image_Size::get_attachment_image_src((int) $s['bg_image']['id'], 'bg_image', $s) : '';
            echo '<div class="obx-cta__bg"><div class="obx-cta__bg-image" style="background-image:url(' . esc_url($src ?: $s['bg_image']['url']) . ')"></div><div class="obx-cta__bg-overlay"></div></div>';
        }
        echo '<div class="obx-cta__content">';
        if (($s['graphic_element'] ?? 'none') === 'image' && !empty($s['graphic_image']['url'])) {
            echo '<div class="obx-cta__image">' . (Group_Control_Image_Size::get_attachment_image_html($s, 'graphic_image', 'graphic_image') ?: '<img src="' . esc_url($s['graphic_image']['url']) . '" alt="">') . '</div>';
        } elseif (($s['graphic_element'] ?? 'none') === 'icon' && !empty($s['selected_icon']['value'])) {
            echo '<div class="obx-cta__icon">' . $this->render_icon_view($s['selected_icon'], $s) . '</div>';
        }
        if ((string) $s['title'] !== '') { $tag = self::pick_tag($s['title_tag'] ?? '', 'h2', false); echo '<' . $tag . ' class="obx-cta__title">' . esc_html($s['title']) . '</' . $tag . '>'; }
        if ((string) $s['description'] !== '') { $tag = self::pick_tag($s['description_tag'] ?? '', 'div', false); echo '<' . $tag . ' class="obx-cta__description">' . esc_html($s['description']) . '</' . $tag . '>'; }
        if ((string) $s['button'] !== '') {
            $class = self::button_classes('obx-cta__button', $s['button_size'] ?? 'sm');
            echo '<div class="obx-cta__button-wrapper">';
            echo $box_link || !$has_link ? '<span class="' . esc_attr($class) . '">' . esc_html($s['button']) . '</span>' : $this->open_link('button', $s['link'], $class) . esc_html($s['button']) . '</a>';
            echo '</div>';
        }
        echo '</div>';
        if ((string) ($s['ribbon_title'] ?? '') !== '') {
            echo '<div class="obx-ribbon' . (($s['ribbon_horizontal_position'] ?? '') === 'left' ? ' obx-ribbon--left' : '') . '"><div class="obx-ribbon__inner">' . esc_html($s['ribbon_title']) . '</div></div>';
        }
        echo '</' . $wrapper . '>';
    }
}

final class Flip_Box extends Element {
    public const PRO = 'flip-box';
    protected const V03 = ['front_title'=>'title_text_a','front_text'=>'description_text_a','back_title'=>'title_text_b','back_text'=>'description_text_b','button'=>'button_text','effect'=>'flip_effect','direction'=>'flip_direction','front_padding'=>'padding_a','back_padding'=>'padding_b','front_color'=>'title_color_a','back_color'=>'title_color_b'];
    public function get_title(): string { return 'Caja giratoria'; }
    public function get_icon(): string { return 'eicon-flip-box'; }
    public function get_keywords(): array { return ['flip','box','caja','giratoria','voltear']; }

    protected static function upgrade_settings(array $s): array {
        if (($s['effect'] ?? '') === 'zoom') { $s['effect'] = 'zoom-in'; }
        if (isset($s['icon']) && is_array($s['icon'])) { $s['selected_icon'] = $s['icon']; unset($s['icon']); }
        foreach (['front'=>'a','back'=>'b'] as $old => $new) { $s = self::rename_prefix($s, $old . '_background_', 'background_' . $new . '_'); $s = self::rename_prefix($s, $old . '_title_typography_', 'title_typography_' . $new . '_'); }
        return parent::upgrade_settings($s);
    }

    protected function register_controls(): void {
        $this->start_controls_section('section_side_a_content', ['label'=>'Cara frontal']);
        $this->add_control('graphic_element', ['label'=>'Elemento gráfico','type'=>Controls_Manager::CHOOSE,'default'=>'icon','options'=>['none'=>['title'=>'Ninguno','icon'=>'eicon-ban'],'image'=>['title'=>'Imagen','icon'=>'eicon-image-bold'],'icon'=>['title'=>'Icono','icon'=>'eicon-star']]]);
        $this->add_control('image', ['label'=>'Imagen','type'=>Controls_Manager::MEDIA,'default'=>['url'=>Utils::get_placeholder_image_src()],'dynamic'=>['active'=>true],'condition'=>['graphic_element'=>'image']]);
        $this->add_group_control(Group_Control_Image_Size::get_type(), ['name'=>'image','default'=>'thumbnail','condition'=>['graphic_element'=>'image']]);
        $this->add_control('selected_icon', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'fa4compatibility'=>'icon','default'=>['value'=>'fas fa-star','library'=>'fa-solid'],'condition'=>['graphic_element'=>'icon']]);
        $this->add_control('title_text_a', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Cara frontal','label_block'=>true,'dynamic'=>['active'=>true],'separator'=>'before']);
        $this->add_control('description_text_a', ['label'=>'Descripción','type'=>Controls_Manager::TEXTAREA,'default'=>'Pasa el ratón para ver el reverso.','dynamic'=>['active'=>true]]);
        $this->end_controls_section();

        $this->start_controls_section('section_side_b_content', ['label'=>'Cara trasera']);
        $this->add_control('title_text_b', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Cara trasera','label_block'=>true,'dynamic'=>['active'=>true]]);
        $this->add_control('description_text_b', ['label'=>'Descripción','type'=>Controls_Manager::TEXTAREA,'default'=>'Añade aquí el detalle y un enlace.','dynamic'=>['active'=>true]]);
        $this->add_control('button_text', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Haz clic aquí','dynamic'=>['active'=>true],'separator'=>'before']);
        $this->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true]]);
        $this->add_control('link_click', ['label'=>'Aplicar el enlace a','type'=>Controls_Manager::SELECT,'default'=>'button','options'=>['box'=>'Toda la caja','button'=>'Solo el botón'],'condition'=>['link[url]!'=>'']]);
        $this->end_controls_section();

        $this->start_controls_section('section_box_settings', ['label'=>'Ajustes']);
        $this->add_control('title_tag', ['label'=>'Etiqueta del título','type'=>Controls_Manager::SELECT,'default'=>'h3','options'=>self::heading_tags()]);
        $this->add_control('description_tag', ['label'=>'Etiqueta de la descripción','type'=>Controls_Manager::SELECT,'default'=>'div','options'=>self::heading_tags()]);
        $this->slider('height', 'Altura', '.obx-flip', 'height: {{SIZE}}{{UNIT}};', ['size_units'=>['px','vh','em','rem','custom'],'range'=>['px'=>['min'=>100,'max'=>1000]],'separator'=>'before'], true);
        $this->slider('border_radius', 'Radio del borde', '.obx-flip__layer', 'border-radius: {{SIZE}}{{UNIT}};', ['range'=>['px'=>['max'=>200]]]);
        $this->add_control('flip_effect', ['label'=>'Efecto','type'=>Controls_Manager::SELECT,'default'=>'flip','options'=>['flip'=>'Girar','slide'=>'Deslizar','push'=>'Empujar','zoom-in'=>'Acercar','zoom-out'=>'Alejar','fade'=>'Fundido'],'prefix_class'=>'obx-flip-box--effect-','separator'=>'before']);
        $this->add_control('flip_direction', ['label'=>'Dirección','type'=>Controls_Manager::CHOOSE,'default'=>'up','options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right'],'up'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'down'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']],'prefix_class'=>'obx-flip-box--direction-','condition'=>['flip_effect!'=>['fade','zoom-in','zoom-out']]]);
        $this->add_control('flip_3d', ['label'=>'Profundidad 3D','type'=>Controls_Manager::SWITCHER,'return_value'=>'obx-flip-box--3d','default'=>'','prefix_class'=>'','condition'=>['flip_effect'=>'flip']]);
        $this->end_controls_section();

        foreach (['a'=>'Cara frontal','b'=>'Cara trasera'] as $side => $label) {
            $sel = '.obx-flip__' . ($side === 'a' ? 'front' : 'back');
            $this->start_controls_section('section_style_' . $side, ['label'=>$label,'tab'=>Controls_Manager::TAB_STYLE]);
            $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'background_' . $side,'types'=>['classic','gradient'],'selector'=>'{{WRAPPER}} ' . $sel]);
            $this->color('background_overlay_' . $side, 'Capa de color', $sel . ' .obx-flip__overlay', 'background-color', ['separator'=>'before','condition'=>['background_' . $side . '_image[id]!'=>'']]);
            $this->add_group_control(Group_Control_Css_Filter::get_type(), ['name'=>'background_overlay_' . $side . '_filters','selector'=>'{{WRAPPER}} ' . $sel . ' .obx-flip__overlay','condition'=>['background_overlay_' . $side . '!'=>'']]);
            $this->add_control('background_overlay_' . $side . '_blend_mode', ['label'=>'Modo de fusión','type'=>Controls_Manager::SELECT,'options'=>[''=>'Normal','multiply'=>'Multiplicar','screen'=>'Trama','overlay'=>'Superponer','darken'=>'Oscurecer','lighten'=>'Aclarar','color-dodge'=>'Sobreexponer color','color-burn'=>'Subexponer color','hue'=>'Tono','saturation'=>'Saturación','color'=>'Color','exclusion'=>'Exclusión','luminosity'=>'Luminosidad'],'selectors'=>self::css($sel . ' .obx-flip__overlay', 'mix-blend-mode: {{VALUE}};'),'condition'=>['background_overlay_' . $side . '!'=>'']]);
            $this->dimensions('padding_' . $side, 'Relleno', $sel . ' .obx-flip__overlay', 'padding', ['separator'=>'before']);
            $this->add_control('alignment_' . $side, ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'default'=>'center','selectors'=>self::css($sel . ' .obx-flip__overlay', 'text-align: {{VALUE}}; --obx-flip-align: {{VALUE}};')] + self::logical_choose());
            $this->add_control('vertical_position_' . $side, ['label'=>'Posición vertical','type'=>Controls_Manager::CHOOSE,'options'=>['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'middle'=>['title'=>'Centro','icon'=>'eicon-v-align-middle'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']],'selectors_dictionary'=>['top'=>'flex-start','middle'=>'center','bottom'=>'flex-end'],'selectors'=>self::css($sel . ' .obx-flip__overlay', 'justify-content: {{VALUE}};')]);
            $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'border_' . $side,'selector'=>'{{WRAPPER}} ' . $sel,'separator'=>'before']);
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'shadow_' . $side,'selector'=>'{{WRAPPER}} ' . $sel]);
            if ($side === 'a') {
                $this->add_control('heading_image_style', ['label'=>'Imagen','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['graphic_element'=>'image']]);
                $this->slider('image_spacing', 'Separación', '.obx-flip__image', 'margin-bottom: {{SIZE}}{{UNIT}};', ['condition'=>['graphic_element'=>'image']]);
                $this->slider('image_width', 'Tamaño', '.obx-flip__image img', 'width: {{SIZE}}{{UNIT}};', ['default'=>['unit'=>'%'],'condition'=>['graphic_element'=>'image']]);
                $this->slider('image_opacity', 'Opacidad', '.obx-flip__image', 'opacity: {{SIZE}};', ['size_units'=>['px'],'default'=>['size'=>1],'range'=>['px'=>['max'=>1,'min'=>0,'step'=>0.01]],'condition'=>['graphic_element'=>'image']]);
                $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'image_border','selector'=>'{{WRAPPER}} .obx-flip__image img','condition'=>['graphic_element'=>'image']]);
                $this->slider('image_border_radius', 'Radio del borde', '.obx-flip__image img', 'border-radius: {{SIZE}}{{UNIT}};', ['condition'=>['graphic_element'=>'image']]);
                $this->add_control('heading_icon_style', ['label'=>'Icono','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['graphic_element'=>'icon']]);
                $this->icon_view_controls('.obx-flip__icon', ['graphic_element'=>'icon']);
                $this->add_control('icon_rotate', ['label'=>'Rotación','type'=>Controls_Manager::SLIDER,'size_units'=>['deg'],'default'=>['size'=>0,'unit'=>'deg'],'selectors'=>self::css('.obx-flip__icon i, .obx-flip__icon svg', 'transform: rotate({{SIZE}}{{UNIT}});'),'condition'=>['graphic_element'=>'icon']]);
            }
            $this->add_control('heading_title_style_' . $side, ['label'=>'Título','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['title_text_' . $side . '!'=>'']]);
            $this->slider('title_spacing_' . $side, 'Separación', $sel . ' .obx-flip__title', 'margin-bottom: {{SIZE}}{{UNIT}};', ['condition'=>['title_text_' . $side . '!'=>'']]);
            $this->color('title_color_' . $side, 'Color', $sel . ' .obx-flip__title', 'color', ['condition'=>['title_text_' . $side . '!'=>'']]);
            $this->typography('title_typography_' . $side, $sel . ' .obx-flip__title', ['condition'=>['title_text_' . $side . '!'=>'']]);
            $this->add_group_control(Group_Control_Text_Stroke::get_type(), ['name'=>$side === 'a' ? 'text_stroke' : 'text_stroke_b','selector'=>'{{WRAPPER}} ' . $sel . ' .obx-flip__title']);
            $this->add_control('heading_description_style_' . $side, ['label'=>'Descripción','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['description_text_' . $side . '!'=>'']]);
            if ($side === 'b') { $this->slider('description_spacing_b', 'Separación', $sel . ' .obx-flip__description', 'margin-bottom: {{SIZE}}{{UNIT}};', ['condition'=>['description_text_b!'=>'','button_text!'=>'']]); }
            $this->color('description_color_' . $side, 'Color', $sel . ' .obx-flip__description', 'color', ['condition'=>['description_text_' . $side . '!'=>'']]);
            $this->typography('description_typography_' . $side, $sel . ' .obx-flip__description', ['condition'=>['description_text_' . $side . '!'=>'']]);
            if ($side === 'b') {
                $this->add_control('heading_button', ['label'=>'Botón','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['button_text!'=>'']]);
                $this->add_control('button_size', ['label'=>'Tamaño','type'=>Controls_Manager::SELECT,'default'=>'sm','options'=>self::sizes(),'condition'=>['button_text!'=>'']]);
                $this->typography('button_typography', '.obx-flip__button', ['condition'=>['button_text!'=>'']]);
                $this->start_controls_tabs('button_tabs', ['condition'=>['button_text!'=>'']]);
                $this->start_controls_tab('normal_button_tab', ['label'=>'Normal','condition'=>['button_text!'=>'']]);
                $this->color('button_text_color', 'Color del texto', '.obx-flip__button', 'color', ['condition'=>['button_text!'=>'']]);
                $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'button_background','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>'{{WRAPPER}} .obx-flip__button','condition'=>['button_text!'=>'']]);
                $this->color('button_border_color', 'Color del borde', '.obx-flip__button', 'border-color', ['condition'=>['button_text!'=>'']]);
                $this->end_controls_tab();
                $this->start_controls_tab('hover_button_tab', ['label'=>'Al pasar el ratón','condition'=>['button_text!'=>'']]);
                $this->color('button_hover_text_color', 'Color al pasar el ratón', '.obx-flip__button:hover', 'color', ['condition'=>['button_text!'=>'']]);
                $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'button_hover_background','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>'{{WRAPPER}} .obx-flip__button:hover','condition'=>['button_text!'=>'']]);
                $this->color('button_hover_border_color', 'Borde al pasar el ratón', '.obx-flip__button:hover', 'border-color', ['condition'=>['button_text!'=>'']]);
                $this->slider('button_hover_transition_duration', 'Duración de la transición', '.obx-flip__button', 'transition-duration: {{SIZE}}{{UNIT}};', ['size_units'=>['ms','s'],'default'=>['unit'=>'ms'],'range'=>['ms'=>['max'=>3000]],'condition'=>['button_text!'=>'']]);
                $this->end_controls_tab();
                $this->end_controls_tabs();
                $this->slider('button_border_width', 'Grosor del borde', '.obx-flip__button', 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>20]],'condition'=>['button_text!'=>'']]);
                $this->slider('button_border_radius', 'Radio del borde', '.obx-flip__button', 'border-radius: {{SIZE}}{{UNIT}};', ['range'=>['px'=>['max'=>100]],'condition'=>['button_text!'=>'']]);
            }
            $this->end_controls_section();
        }
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $effect = in_array($s['flip_effect'] ?? '', ['flip','slide','push','zoom-in','zoom-out','fade'], true) ? $s['flip_effect'] : 'flip';
        $direction = in_array($s['flip_direction'] ?? '', ['left','right','up','down'], true) ? $s['flip_direction'] : 'up';
        $has_link = !empty($s['link']['url']);
        $box_link = $has_link && ($s['link_click'] ?? 'button') === 'box';
        $title_tag = self::pick_tag($s['title_tag'] ?? '', 'h3');
        $description_tag = self::pick_tag($s['description_tag'] ?? '', 'div');
        echo '<div class="obx-flip obx-flip--' . esc_attr($effect) . ' obx-flip--' . esc_attr($direction) . (($s['flip_3d'] ?? '') !== '' ? ' obx-flip--3d' : '') . '" tabindex="0"><div class="obx-flip__inner">';
        echo '<div class="obx-flip__front obx-flip__layer"><div class="obx-flip__overlay"><div class="obx-flip__layer-inner">';
        if (($s['graphic_element'] ?? 'icon') === 'image' && !empty($s['image']['url'])) {
            echo '<div class="obx-flip__image">' . (Group_Control_Image_Size::get_attachment_image_html($s) ?: '<img src="' . esc_url($s['image']['url']) . '" alt="">') . '</div>';
        } elseif (($s['graphic_element'] ?? 'icon') === 'icon' && !empty($s['selected_icon']['value'])) {
            echo '<div class="obx-flip__icon">' . $this->render_icon_view($s['selected_icon'], $s) . '</div>';
        }
        if ((string) $s['title_text_a'] !== '') { echo '<' . $title_tag . ' class="obx-flip__title">' . esc_html($s['title_text_a']) . '</' . $title_tag . '>'; }
        if ((string) $s['description_text_a'] !== '') { echo '<' . $description_tag . ' class="obx-flip__description">' . esc_html($s['description_text_a']) . '</' . $description_tag . '>'; }
        echo '</div></div></div>';
        if ($box_link) { $this->add_link_attributes('back', $s['link']); }
        $this->add_render_attribute('back', 'class', ['obx-flip__back','obx-flip__layer']);
        $back_tag = $box_link ? 'a' : 'div';
        echo '<' . $back_tag . ' ' . $this->get_render_attribute_string('back') . '><div class="obx-flip__overlay"><div class="obx-flip__layer-inner">';
        if ((string) $s['title_text_b'] !== '') { echo '<' . $title_tag . ' class="obx-flip__title">' . esc_html($s['title_text_b']) . '</' . $title_tag . '>'; }
        if ((string) $s['description_text_b'] !== '') { echo '<' . $description_tag . ' class="obx-flip__description">' . esc_html($s['description_text_b']) . '</' . $description_tag . '>'; }
        if ((string) $s['button_text'] !== '') {
            $class = self::button_classes('obx-flip__button', $s['button_size'] ?? 'sm');
            echo $box_link || !$has_link ? '<span class="' . esc_attr($class) . '">' . esc_html($s['button_text']) . '</span>' : $this->open_link('button', $s['link'], $class) . esc_html($s['button_text']) . '</a>';
        }
        echo '</div></div></' . $back_tag . '></div></div>';
    }
}

final class Price_Table extends Element {
    public const PRO = 'price-table';
    protected const V03 = ['items'=>'features_list','button'=>'button_text','footer_text'=>'footer_additional_info','ribbon'=>'ribbon_title','header_bg'=>'header_bg_color','header_color'=>'heading_color'];
    public const SYMBOLS = ['dollar'=>'$','euro'=>'€','baht'=>'฿','franc'=>'₣','guilder'=>'ƒ','krona'=>'kr','lira'=>'₤','peseta'=>'₧','peso'=>'₱','pound'=>'£','real'=>'R$','ruble'=>'₽','rupee'=>'₨','indian_rupee'=>'₹','shekel'=>'₪','yen'=>'¥','won'=>'₩'];
    public function get_title(): string { return 'Tabla de precios'; }
    public function get_icon(): string { return 'eicon-price-table'; }
    public function get_keywords(): array { return ['price','precio','tabla','plan','tarifa','pricing']; }

    protected static function upgrade_settings(array $s): array {
        $symbol = (string) ($s['currency'] ?? '€');
        $known = array_search($symbol, self::SYMBOLS, true);
        $s['currency_symbol'] = $known !== false ? $known : 'custom';
        if ($known === false) { $s['currency_symbol_custom'] = $symbol; }
        $s['currency_position'] = ($s['currency_position'] ?? 'after') === 'before' ? 'before' : 'after';
        if (str_contains((string) ($s['price'] ?? '29,99'), ',')) { $s['currency_format'] = ','; }
        if (($s['original_price'] ?? '') !== '') { $s['sale'] = 'yes'; }
        $s['show_ribbon'] = ($s['ribbon'] ?? '') !== '' ? 'yes' : '';
        foreach ((array) ($s['items'] ?? []) as $i => $item) {
            $s['items'][$i] = ['_id'=>$item['_id'] ?? substr(md5((string) $i), 0, 7),'item_text'=>$item['text'] ?? '','selected_item_icon'=>$item['icon'] ?? []];
        }
        unset($s['currency']);
        return parent::upgrade_settings($s);
    }

    protected function register_controls(): void {
        $this->start_controls_section('section_header', ['label'=>'Cabecera']);
        $this->add_control('heading', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Escribe el título','dynamic'=>['active'=>true]]);
        $this->add_control('sub_heading', ['label'=>'Descripción','type'=>Controls_Manager::TEXT,'default'=>'Escribe la descripción','dynamic'=>['active'=>true]]);
        $this->add_control('heading_tag', ['label'=>'Etiqueta del título','type'=>Controls_Manager::SELECT,'default'=>'h3','options'=>['h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','h6'=>'H6'],'condition'=>['heading!'=>'']]);
        $this->end_controls_section();

        $this->start_controls_section('section_pricing', ['label'=>'Precio']);
        $symbols = [''=>'Ninguno'];
        foreach (self::SYMBOLS as $key => $symbol) { $symbols[$key] = $symbol . ' ' . ucfirst(str_replace('_', ' ', $key)); }
        $this->add_control('currency_symbol', ['label'=>'Moneda','type'=>Controls_Manager::SELECT,'default'=>'dollar','options'=>$symbols + ['custom'=>'Personalizada']]);
        $this->add_control('currency_symbol_custom', ['label'=>'Símbolo','type'=>Controls_Manager::TEXT,'condition'=>['currency_symbol'=>'custom']]);
        $this->add_control('price', ['label'=>'Precio','type'=>Controls_Manager::TEXT,'default'=>'39.99','dynamic'=>['active'=>true]]);
        $this->add_control('currency_format', ['label'=>'Formato','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'1,234.56 (predeterminado)',','=>'1.234,56']]);
        $this->add_control('sale', ['label'=>'En oferta','type'=>Controls_Manager::SWITCHER,'default'=>'','separator'=>'before']);
        $this->add_control('original_price', ['label'=>'Precio anterior','type'=>Controls_Manager::NUMBER,'default'=>'59','condition'=>['sale'=>'yes'],'dynamic'=>['active'=>true]]);
        $this->add_control('period', ['label'=>'Periodo','type'=>Controls_Manager::TEXT,'default'=>'Al mes']);
        $this->end_controls_section();

        $this->start_controls_section('section_features', ['label'=>'Características']);
        $r = new Repeater();
        $r->add_control('item_text', ['label'=>'Texto','type'=>Controls_Manager::TEXT,'default'=>'Característica','dynamic'=>['active'=>true]]);
        $r->add_control('selected_item_icon', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'fa4compatibility'=>'item_icon','default'=>['value'=>'far fa-check-circle','library'=>'fa-regular']]);
        $r->add_control('item_icon_color', ['label'=>'Color del icono','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} {{CURRENT_ITEM}} i'=>'color: {{VALUE}};','{{WRAPPER}} {{CURRENT_ITEM}} svg'=>'fill: {{VALUE}};']]);
        $this->add_control('features_list', ['type'=>Controls_Manager::REPEATER,'fields'=>$r->get_controls(),'title_field'=>'{{{ item_text }}}',
            'default'=>[['item_text'=>'Característica 1','selected_item_icon'=>['value'=>'fa fa-check','library'=>'fa-solid']],['item_text'=>'Característica 2','selected_item_icon'=>['value'=>'fa fa-check','library'=>'fa-solid']],['item_text'=>'Característica 3','selected_item_icon'=>['value'=>'fa fa-check','library'=>'fa-solid']]]]);
        $this->end_controls_section();

        $this->start_controls_section('section_footer', ['label'=>'Pie']);
        $this->add_control('button_text', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Haz clic aquí','dynamic'=>['active'=>true]]);
        $this->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'default'=>['url'=>'#'],'dynamic'=>['active'=>true],'condition'=>['button_text!'=>'']]);
        $this->add_control('footer_additional_info', ['label'=>'Información adicional','type'=>Controls_Manager::TEXTAREA,'default'=>'Texto adicional','rows'=>2,'dynamic'=>['active'=>true],'condition'=>['button_text!'=>'']]);
        $this->end_controls_section();

        $this->start_controls_section('section_ribbon', ['label'=>'Cinta']);
        $this->add_control('show_ribbon', ['label'=>'Mostrar','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('ribbon_title', ['label'=>'Texto','type'=>Controls_Manager::TEXT,'default'=>'Popular','dynamic'=>['active'=>true],'condition'=>['show_ribbon'=>'yes']]);
        $this->add_control('ribbon_horizontal_position', ['label'=>'Posición','type'=>Controls_Manager::CHOOSE,'options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right']],'condition'=>['show_ribbon'=>'yes']]);
        $this->end_controls_section();

        $this->start_controls_section('section_header_style', ['label'=>'Cabecera','tab'=>Controls_Manager::TAB_STYLE]);
        $this->color('header_bg_color', 'Fondo', '.obx-price-table__header', 'background-color');
        $this->dimensions('header_padding', 'Relleno', '.obx-price-table__header', 'padding');
        $this->add_control('heading_heading_style', ['label'=>'Título','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['heading!'=>'']]);
        $this->color('heading_color', 'Color', '.obx-price-table__heading', 'color', ['condition'=>['heading!'=>'']]);
        $this->typography('heading_typography', '.obx-price-table__heading', ['condition'=>['heading!'=>'']]);
        $this->add_control('heading_sub_heading_style', ['label'=>'Descripción','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['sub_heading!'=>'']]);
        $this->color('sub_heading_color', 'Color', '.obx-price-table__subheading', 'color', ['condition'=>['sub_heading!'=>'']]);
        $this->typography('sub_heading_typography', '.obx-price-table__subheading', ['condition'=>['sub_heading!'=>'']]);
        $this->end_controls_section();

        $this->start_controls_section('section_pricing_element_style', ['label'=>'Precio','tab'=>Controls_Manager::TAB_STYLE]);
        $this->color('pricing_element_bg_color', 'Fondo', '.obx-price-table__price', 'background-color');
        $this->dimensions('pricing_element_padding', 'Relleno', '.obx-price-table__price', 'padding');
        $this->color('price_color', 'Color', '.obx-price-table__currency, .obx-price-table__integer, .obx-price-table__fraction', 'color', ['separator'=>'before']);
        $this->typography('price_typography', '.obx-price-table__price');
        $this->add_control('heading_currency_style', ['label'=>'Símbolo de moneda','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['currency_symbol!'=>'']]);
        $this->slider('currency_size', 'Tamaño', '.obx-price-table__currency', 'font-size: calc({{SIZE}}em/100);', ['size_units'=>['px'],'range'=>['px'=>['max'=>100]],'condition'=>['currency_symbol!'=>'']]);
        $this->add_control('currency_position', ['label'=>'Posición','type'=>Controls_Manager::CHOOSE,'default'=>'before','options'=>['before'=>['title'=>'Antes','icon'=>'eicon-h-align-left'],'after'=>['title'=>'Después','icon'=>'eicon-h-align-right']]]);
        $this->add_control('currency_vertical_position', ['label'=>'Posición vertical','type'=>Controls_Manager::CHOOSE,'default'=>'top','options'=>['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'middle'=>['title'=>'Centro','icon'=>'eicon-v-align-middle'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']],'selectors_dictionary'=>['top'=>'flex-start','middle'=>'center','bottom'=>'flex-end'],'selectors'=>self::css('.obx-price-table__currency', 'align-self: {{VALUE}};'),'condition'=>['currency_symbol!'=>'']]);
        $this->add_control('fractional_part_style', ['label'=>'Decimales','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->slider('fractional-part_size', 'Tamaño', '.obx-price-table__fraction', 'font-size: calc({{SIZE}}em/100);', ['size_units'=>['px'],'range'=>['px'=>['max'=>100]]]);
        $this->add_control('fractional_part_vertical_position', ['label'=>'Posición vertical','type'=>Controls_Manager::CHOOSE,'default'=>'top','options'=>['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'middle'=>['title'=>'Centro','icon'=>'eicon-v-align-middle'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']],'selectors_dictionary'=>['top'=>'flex-start','middle'=>'center','bottom'=>'flex-end'],'selectors'=>self::css('.obx-price-table__after-price', 'justify-content: {{VALUE}};')]);
        $this->add_control('heading_original_price_style', ['label'=>'Precio anterior','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['sale'=>'yes','original_price!'=>'']]);
        $this->color('original_price_color', 'Color', '.obx-price-table__original', 'color', ['condition'=>['sale'=>'yes','original_price!'=>'']]);
        $this->typography('original_price_typography', '.obx-price-table__original', ['condition'=>['sale'=>'yes','original_price!'=>'']]);
        $this->add_control('original_price_vertical_position', ['label'=>'Posición vertical','type'=>Controls_Manager::CHOOSE,'default'=>'bottom','options'=>['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'middle'=>['title'=>'Centro','icon'=>'eicon-v-align-middle'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']],'selectors_dictionary'=>['top'=>'flex-start','middle'=>'center','bottom'=>'flex-end'],'selectors'=>self::css('.obx-price-table__original', 'align-self: {{VALUE}};'),'condition'=>['sale'=>'yes','original_price!'=>'']]);
        $this->add_control('heading_period_style', ['label'=>'Periodo','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['period!'=>'']]);
        $this->color('period_color', 'Color', '.obx-price-table__period', 'color', ['condition'=>['period!'=>'']]);
        $this->typography('period_typography', '.obx-price-table__period', ['condition'=>['period!'=>'']]);
        $this->add_control('period_position', ['label'=>'Posición','type'=>Controls_Manager::SELECT,'default'=>'below','options'=>['below'=>'Debajo','beside'=>'Al lado'],'condition'=>['period!'=>'']]);
        $this->end_controls_section();

        $this->start_controls_section('section_features_list_style', ['label'=>'Características','tab'=>Controls_Manager::TAB_STYLE]);
        $this->color('features_list_bg_color', 'Fondo', '.obx-price-table__features', 'background-color');
        $this->dimensions('features_list_padding', 'Relleno', '.obx-price-table__features', 'padding');
        $this->color('features_list_color', 'Color', '.obx-price-table__features', 'color', ['separator'=>'before']);
        $this->typography('features_list_typography', '.obx-price-table__features li');
        $this->add_control('features_list_alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'selectors'=>self::css('.obx-price-table__features', 'text-align: {{VALUE}}; --obx-features-align: {{VALUE}};')] + self::logical_choose());
        $this->slider('item_width', 'Ancho', '.obx-price-table__feature-inner', 'margin-inline: calc((100% - {{SIZE}}%)/2);', ['size_units'=>['%'],'range'=>['%'=>['min'=>25,'max'=>100]]], true);
        $this->add_control('list_divider', ['label'=>'Separador','type'=>Controls_Manager::SWITCHER,'default'=>'yes','separator'=>'before']);
        $this->add_control('divider_style', ['label'=>'Estilo','type'=>Controls_Manager::SELECT,'default'=>'solid','options'=>['solid'=>'Sólido','double'=>'Doble','dotted'=>'Puntos','dashed'=>'Guiones'],'selectors'=>self::css('.obx-price-table__features li + li::before', 'border-top-style: {{VALUE}};'),'condition'=>['list_divider'=>'yes']]);
        $this->color('divider_color', 'Color', '.obx-price-table__features li + li::before', 'border-top-color', ['default'=>'#ddd','condition'=>['list_divider'=>'yes']]);
        $this->slider('divider_weight', 'Grosor', '.obx-price-table__features li + li::before', 'border-top-width: {{SIZE}}{{UNIT}};', ['size_units'=>['px'],'default'=>['size'=>2],'range'=>['px'=>['min'=>1,'max'=>10]],'condition'=>['list_divider'=>'yes']]);
        $this->slider('divider_width', 'Ancho', '.obx-price-table__features li + li::before', 'margin-inline: calc((100% - {{SIZE}}%)/2);', ['size_units'=>['%'],'condition'=>['list_divider'=>'yes']]);
        $this->slider('divider_gap', 'Separación', '.obx-price-table__features li + li::before', 'margin-block: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'default'=>['size'=>15],'range'=>['px'=>['max'=>50]],'condition'=>['list_divider'=>'yes']]);
        $this->end_controls_section();

        $this->start_controls_section('section_footer_style', ['label'=>'Pie','tab'=>Controls_Manager::TAB_STYLE]);
        $this->color('footer_bg_color', 'Fondo', '.obx-price-table__footer', 'background-color', ['condition'=>['button_text!'=>'']]);
        $this->dimensions('footer_padding', 'Relleno', '.obx-price-table__footer', 'padding', ['condition'=>['button_text!'=>'']]);
        $this->add_control('heading_footer_button', ['label'=>'Botón','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['button_text!'=>'']]);
        $this->add_control('button_size', ['label'=>'Tamaño','type'=>Controls_Manager::SELECT,'default'=>'md','options'=>self::sizes(),'condition'=>['button_text!'=>'']]);
        $this->start_controls_tabs('tabs_button_style', ['condition'=>['button_text!'=>'']]);
        $this->start_controls_tab('tab_button_normal', ['label'=>'Normal','condition'=>['button_text!'=>'']]);
        $this->color('button_text_color', 'Color del texto', '.obx-price-table__button', 'color', ['condition'=>['button_text!'=>'']]);
        $this->typography('button_typography', '.obx-price-table__button', ['condition'=>['button_text!'=>'']]);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'button_background','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>'{{WRAPPER}} .obx-price-table__button','condition'=>['button_text!'=>'']]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'button_border','selector'=>'{{WRAPPER}} .obx-price-table__button','condition'=>['button_text!'=>'']]);
        $this->dimensions('button_border_radius', 'Radio del borde', '.obx-price-table__button', 'border-radius', ['condition'=>['button_text!'=>'']], false);
        $this->dimensions('button_text_padding', 'Relleno', '.obx-price-table__button', 'padding', ['condition'=>['button_text!'=>'']], false);
        $this->end_controls_tab();
        $this->start_controls_tab('tab_button_hover', ['label'=>'Al pasar el ratón','condition'=>['button_text!'=>'']]);
        $this->color('button_hover_color', 'Color al pasar el ratón', '.obx-price-table__button:hover', 'color', ['condition'=>['button_text!'=>'']]);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'button_background_hover','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>'{{WRAPPER}} .obx-price-table__button:hover','condition'=>['button_text!'=>'']]);
        $this->color('button_hover_border_color', 'Borde al pasar el ratón', '.obx-price-table__button:hover', 'border-color', ['condition'=>['button_text!'=>'']]);
        $this->add_control('button_hover_animation', ['label'=>'Animación','type'=>Controls_Manager::HOVER_ANIMATION,'condition'=>['button_text!'=>'']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_control('heading_additional_info', ['label'=>'Información adicional','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['button_text!'=>'','footer_additional_info!'=>'']]);
        $this->color('additional_info_color', 'Color', '.obx-price-table__note', 'color', ['condition'=>['button_text!'=>'','footer_additional_info!'=>'']]);
        $this->typography('additional_info_typography', '.obx-price-table__note', ['condition'=>['button_text!'=>'','footer_additional_info!'=>'']]);
        $this->dimensions('additional_info_margin', 'Margen', '.obx-price-table__note', 'margin', ['default'=>['top'=>15,'right'=>30,'bottom'=>0,'left'=>30,'unit'=>'px'],'condition'=>['button_text!'=>'','footer_additional_info!'=>'']], false);
        $this->end_controls_section();

        $this->start_controls_section('section_ribbon_style', ['label'=>'Cinta','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['show_ribbon'=>'yes']]);
        $this->color('ribbon_bg_color', 'Fondo', '.obx-ribbon__inner', 'background-color');
        $this->slider('ribbon_distance', 'Distancia', '.obx-ribbon__inner', 'margin-top: {{SIZE}}{{UNIT}}; transform: translateY(-50%) translateX(-50%) translateX({{SIZE}}{{UNIT}}) rotate(-45deg);', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>50]]], true);
        $this->color('ribbon_text_color', 'Color del texto', '.obx-ribbon__inner', 'color', ['default'=>'#ffffff','separator'=>'before']);
        $this->typography('ribbon_typography', '.obx-ribbon__inner');
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'box_shadow','selector'=>'{{WRAPPER}} .obx-ribbon__inner']);
        $this->end_controls_section();
    }

    public static function symbol(array $s): string {
        $key = (string) ($s['currency_symbol'] ?? 'dollar');
        if ($key === 'custom') { return (string) ($s['currency_symbol_custom'] ?? ''); }
        return self::SYMBOLS[$key] ?? '';
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $symbol = self::symbol($s);
        $separator = ($s['currency_format'] ?? '') === ',' ? ',' : '.';
        [$integer, $fraction] = array_pad(explode($separator, (string) $s['price'], 2), 2, '');
        $after = ($s['currency_position'] ?? 'before') === 'after';
        $currency = $symbol !== '' ? '<span class="obx-price-table__currency' . ($after ? ' obx-price-table__currency--after' : '') . '">' . esc_html($symbol) . '</span>' : '';
        $period = (string) ($s['period'] ?? '');
        $beside = ($s['period_position'] ?? 'below') === 'beside';

        echo '<div class="obx-price-table">';
        if ((string) $s['heading'] !== '' || (string) $s['sub_heading'] !== '') {
            $tag = in_array($s['heading_tag'] ?? '', ['h2','h3','h4','h5','h6'], true) ? $s['heading_tag'] : 'h3';
            echo '<div class="obx-price-table__header">';
            if ((string) $s['heading'] !== '') { echo '<' . $tag . ' class="obx-price-table__heading">' . esc_html($s['heading']) . '</' . $tag . '>'; }
            if ((string) $s['sub_heading'] !== '') { echo '<span class="obx-price-table__subheading">' . esc_html($s['sub_heading']) . '</span>'; }
            echo '</div>';
        }
        echo '<div class="obx-price-table__price">';
        if (($s['sale'] ?? '') === 'yes' && (string) ($s['original_price'] ?? '') !== '') {
            echo '<span class="obx-price-table__original"><span class="screen-reader-text">Precio anterior: </span>' . ($after ? '' : esc_html($symbol)) . esc_html((string) $s['original_price']) . ($after ? esc_html($symbol) : '') . '</span>';
        }
        if (!$after) { echo $currency; }
        if ($integer !== '' || $fraction !== '') { echo '<span class="obx-price-table__integer">' . esc_html($integer) . '</span>'; }
        if ($fraction !== '' || ($period !== '' && $beside)) {
            echo '<div class="obx-price-table__after-price">';
            if ($fraction !== '') { echo '<span class="obx-price-table__fraction">' . esc_html($fraction) . '</span>'; }
            if ($period !== '' && $beside) { echo '<span class="obx-price-table__period">' . esc_html($period) . '</span>'; }
            echo '</div>';
        }
        if ($after) { echo $currency; }
        if ($period !== '' && !$beside) { echo '<span class="obx-price-table__period obx-price-table__period--below">' . esc_html($period) . '</span>'; }
        echo '</div>';
        if (!empty($s['features_list'])) {
            echo '<ul class="obx-price-table__features' . (($s['list_divider'] ?? 'yes') === 'yes' ? ' obx-price-table__features--divider' : '') . '">';
            foreach ($s['features_list'] as $item) {
                echo '<li class="elementor-repeater-item-' . esc_attr((string) ($item['_id'] ?? '')) . '"><div class="obx-price-table__feature-inner">';
                if (!empty($item['selected_item_icon']['value'])) { echo Icons_Manager::try_get_icon_html($item['selected_item_icon'], ['aria-hidden'=>'true']); }
                if ((string) ($item['item_text'] ?? '') !== '') { echo '<span>' . esc_html($item['item_text']) . '</span>'; }
                echo '</div></li>';
            }
            echo '</ul>';
        }
        if ((string) $s['button_text'] !== '' || (string) ($s['footer_additional_info'] ?? '') !== '') {
            echo '<div class="obx-price-table__footer">';
            if ((string) $s['button_text'] !== '') {
                $class = self::button_classes('obx-price-table__button', $s['button_size'] ?? 'md') . (($s['button_hover_animation'] ?? '') ? ' elementor-animation-' . sanitize_html_class($s['button_hover_animation']) : '');
                echo !empty($s['link']['url']) ? $this->open_link('button', $s['link'], $class) . esc_html($s['button_text']) . '</a>' : '<span class="' . esc_attr($class) . '">' . esc_html($s['button_text']) . '</span>';
            }
            if ((string) $s['button_text'] !== '' && (string) ($s['footer_additional_info'] ?? '') !== '') { echo '<div class="obx-price-table__note">' . esc_html($s['footer_additional_info']) . '</div>'; }
            echo '</div>';
        }
        if (($s['show_ribbon'] ?? 'yes') === 'yes' && (string) ($s['ribbon_title'] ?? '') !== '') {
            echo '<div class="obx-ribbon' . (($s['ribbon_horizontal_position'] ?? '') === 'left' ? ' obx-ribbon--left' : '') . '"><div class="obx-ribbon__inner">' . esc_html($s['ribbon_title']) . '</div></div>';
        }
        echo '</div>';
    }
}

final class Price_List extends Element {
    public const PRO = 'price-list';
    protected const V03 = ['items'=>'price_list','separator'=>'separator_style','title_color'=>'heading_color'];
    public function get_title(): string { return 'Lista de precios'; }
    public function get_icon(): string { return 'eicon-price-list'; }
    public function get_keywords(): array { return ['price','precio','lista','menu','carta','tarifas']; }

    protected static function upgrade_settings(array $s): array {
        foreach ((array) ($s['items'] ?? []) as $i => $item) {
            $item['item_description'] = $item['description'] ?? '';
            unset($item['description']);
            $s['items'][$i] = $item;
        }
        $s = self::rename_prefix($s, 'title_typography_', 'heading_typography_');
        return parent::upgrade_settings($s);
    }

    protected function register_controls(): void {
        $this->start_controls_section('section_list', ['label'=>'Lista']);
        $r = new Repeater();
        $r->add_control('price', ['label'=>'Precio','type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true]]);
        $r->add_control('title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'','label_block'=>true,'dynamic'=>['active'=>true]]);
        $r->add_control('item_description', ['label'=>'Descripción','type'=>Controls_Manager::TEXTAREA,'default'=>'','dynamic'=>['active'=>true]]);
        $r->add_control('image', ['label'=>'Imagen','type'=>Controls_Manager::MEDIA,'default'=>[],'dynamic'=>['active'=>true]]);
        $r->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'default'=>['url'=>'#'],'dynamic'=>['active'=>true]]);
        $this->add_control('price_list', ['type'=>Controls_Manager::REPEATER,'fields'=>$r->get_controls(),'title_field'=>'{{{ title }}}',
            'default'=>[['title'=>'Primer producto','item_description'=>'Una línea que lo describe.','price'=>'$20','link'=>['url'=>'#']],['title'=>'Segundo producto','item_description'=>'Otra línea que lo describe.','price'=>'$9','link'=>['url'=>'#']],['title'=>'Tercer producto','item_description'=>'Y una más.','price'=>'$32','link'=>['url'=>'#']]]]);
        $this->add_control('title_tag', ['label'=>'Etiqueta del título','type'=>Controls_Manager::SELECT,'default'=>'span','options'=>self::heading_tags(),'separator'=>'before']);
        $this->add_control('description_tag', ['label'=>'Etiqueta de la descripción','type'=>Controls_Manager::SELECT,'default'=>'p','options'=>self::heading_tags()]);
        $this->end_controls_section();

        $this->start_controls_section('section_list_style', ['label'=>'Lista','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('vertical_align', ['label'=>'Alineación vertical','type'=>Controls_Manager::CHOOSE,'default'=>'top','options'=>['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'center'=>['title'=>'Centro','icon'=>'eicon-v-align-middle'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']],'selectors_dictionary'=>['top'=>'flex-start','center'=>'center','bottom'=>'flex-end'],'selectors'=>self::css('.obx-price-list__row', 'align-items: {{VALUE}};')]);
        $this->slider('row_gap', 'Separación entre filas', '.obx-price-list', 'gap: {{SIZE}}{{UNIT}};', ['default'=>['size'=>20],'range'=>['px'=>['max'=>50]]], true);
        $this->slider('image_spacing', 'Separación de la imagen', '.obx-price-list__row', 'gap: {{SIZE}}{{UNIT}};', ['default'=>['size'=>20],'range'=>['px'=>['max'=>50]]], true);
        $this->slider('content_spacing', 'Separación del contenido', '.obx-price-list__header', 'margin-bottom: {{SIZE}}{{UNIT}};', ['range'=>['px'=>['max'=>50]]], true);
        $this->end_controls_section();

        $this->start_controls_section('section_content_style', ['label'=>'Contenido','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('heading__title', ['label'=>'Título y precio','type'=>Controls_Manager::HEADING]);
        $this->color('heading_color', 'Color del título', '.obx-price-list__title', 'color');
        $this->typography('heading_typography', '.obx-price-list__header');
        $this->add_group_control(Group_Control_Text_Stroke::get_type(), ['name'=>'text_stroke','selector'=>'{{WRAPPER}} .obx-price-list__header']);
        $this->add_control('price_title', ['label'=>'Precio','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('price_color', 'Color', '.obx-price-list__price', 'color');
        $this->typography('price_typography', '.obx-price-list__price');
        $this->add_control('heading_item_description', ['label'=>'Descripción','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('description_color', 'Color', '.obx-price-list__description', 'color');
        $this->typography('description_typography', '.obx-price-list__description');
        $this->add_control('heading_separator', ['label'=>'Separador','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control('separator_style', ['label'=>'Estilo','type'=>Controls_Manager::SELECT,'default'=>'dotted','options'=>['none'=>'Ninguno','solid'=>'Sólido','dotted'=>'Puntos','dashed'=>'Guiones','double'=>'Doble'],'selectors'=>self::css('.obx-price-list__sep', 'border-bottom-style: {{VALUE}};')]);
        $this->slider('separator_weight', 'Grosor', '.obx-price-list__sep', 'border-bottom-width: {{SIZE}}{{UNIT}};', ['size_units'=>['px'],'default'=>['size'=>2],'range'=>['px'=>['max'=>10]],'condition'=>['separator_style!'=>'none']]);
        $this->color('separator_color', 'Color', '.obx-price-list__sep', 'border-bottom-color', ['condition'=>['separator_style!'=>'none']]);
        $this->slider('separator_spacing', 'Separación', '.obx-price-list__sep', 'margin-inline: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>40]],'condition'=>['separator_style!'=>'none']]);
        $this->end_controls_section();

        $this->start_controls_section('section_image_style', ['label'=>'Imagen','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Image_Size::get_type(), ['name'=>'image_size','default'=>'thumbnail']);
        $this->dimensions('border_radius', 'Radio del borde', '.obx-price-list__image img', 'border-radius', [], false);
        $this->end_controls_section();
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $items = (array) ($s['price_list'] ?? []);
        if (!$items) { $this->placeholder('Añade elementos a la lista.'); return; }
        $title_tag = self::pick_tag($s['title_tag'] ?? '', 'span');
        $description_tag = self::pick_tag($s['description_tag'] ?? '', 'p');
        $size = (string) ($s['image_size_size'] ?? 'thumbnail');
        echo '<ul class="obx-price-list' . (($s['separator_style'] ?? 'dotted') === 'none' ? ' obx-price-list--no-separator' : '') . '">';
        foreach ($items as $i => $item) {
            if ((string) ($item['title'] ?? '') === '' && (string) ($item['price'] ?? '') === '' && (string) ($item['item_description'] ?? '') === '') { continue; }
            echo '<li class="obx-price-list__item elementor-repeater-item-' . esc_attr((string) ($item['_id'] ?? '')) . '">' . $this->open_link('item-' . $i, $item['link'] ?? [], 'obx-price-list__row', 'div');
            if (!empty($item['image']['id'])) {
                echo '<span class="obx-price-list__image">' . wp_get_attachment_image((int) $item['image']['id'], $size === 'custom' ? 'thumbnail' : $size, false, ['loading'=>'lazy']) . '</span>';
            } elseif (!empty($item['image']['url'])) {
                echo '<span class="obx-price-list__image"><img src="' . esc_url($item['image']['url']) . '" alt="" loading="lazy"></span>';
            }
            echo '<span class="obx-price-list__text"><span class="obx-price-list__header">';
            if ((string) ($item['title'] ?? '') !== '') { echo '<' . $title_tag . ' class="obx-price-list__title">' . esc_html($item['title']) . '</' . $title_tag . '>'; }
            if ((string) ($item['price'] ?? '') !== '') { echo '<span class="obx-price-list__sep"></span><span class="obx-price-list__price">' . esc_html($item['price']) . '</span>'; }
            echo '</span>';
            if ((string) ($item['item_description'] ?? '') !== '') { echo '<' . $description_tag . ' class="obx-price-list__description">' . esc_html($item['item_description']) . '</' . $description_tag . '>'; }
            echo '</span>' . $this->close_link($item['link'] ?? [], 'div') . '</li>';
        }
        echo '</ul>';
    }
}

final class Countdown extends Element {
    public const PRO = 'countdown';
    protected const V03 = ['type'=>'countdown_type','evergreen_hours'=>'evergreen_counter_hours','evergreen_minutes'=>'evergreen_counter_minutes','view'=>'label_display','message'=>'message_after_expire','redirect'=>'expire_redirect_url','box_bg'=>'box_background_color','box_gap'=>'box_spacing'];
    private const LABELS = ['days'=>'Días','hours'=>'Horas','minutes'=>'Minutos','seconds'=>'Segundos'];
    public function get_title(): string { return 'Cuenta atrás'; }
    public function get_icon(): string { return 'eicon-countdown'; }
    public function get_keywords(): array { return ['countdown','cuenta atras','temporizador','timer','fecha']; }

    protected static function upgrade_settings(array $s): array {
        $expire = $s['expire'] ?? 'none';
        $s['expire_actions'] = in_array($expire, ['hide','message','redirect'], true) ? [$expire] : [];
        unset($s['expire']);
        foreach (self::LABELS as $unit => $label) { if (isset($s['label_' . $unit]) && $s['label_' . $unit] !== $label) { $s['custom_labels'] = 'yes'; } }
        return parent::upgrade_settings($s);
    }

    protected function register_controls(): void {
        $this->start_controls_section('section_countdown', ['label'=>'Cuenta atrás']);
        $this->add_control('countdown_type', ['label'=>'Tipo','type'=>Controls_Manager::SELECT,'default'=>'due_date','options'=>['due_date'=>'Hasta una fecha','evergreen'=>'Por visitante']]);
        $this->add_control('due_date', ['label'=>'Fecha límite','type'=>Controls_Manager::DATE_TIME,'default'=>gmdate('Y-m-d H:i', strtotime('+1 month') + (int) (get_option('gmt_offset') * HOUR_IN_SECONDS)),'description'=>'Zona horaria del sitio (' . wp_timezone_string() . ').','condition'=>['countdown_type'=>'due_date']]);
        $this->add_control('evergreen_counter_hours', ['label'=>'Horas','type'=>Controls_Manager::NUMBER,'default'=>47,'condition'=>['countdown_type'=>'evergreen']]);
        $this->add_control('evergreen_counter_minutes', ['label'=>'Minutos','type'=>Controls_Manager::NUMBER,'default'=>59,'condition'=>['countdown_type'=>'evergreen']]);
        foreach (self::LABELS as $unit => $label) { $this->add_control('show_' . $unit, ['label'=>$label,'type'=>Controls_Manager::SWITCHER,'default'=>'yes']); }
        $this->add_control('show_labels', ['label'=>'Mostrar etiquetas','type'=>Controls_Manager::SWITCHER,'default'=>'yes','separator'=>'before']);
        $this->add_control('custom_labels', ['label'=>'Etiquetas personalizadas','type'=>Controls_Manager::SWITCHER,'condition'=>['show_labels!'=>'']]);
        foreach (self::LABELS as $unit => $label) { $this->add_control('label_' . $unit, ['label'=>$label,'type'=>Controls_Manager::TEXT,'default'=>$label,'placeholder'=>$label,'condition'=>['show_labels!'=>'','custom_labels!'=>'','show_' . $unit=>'yes']]); }
        $this->add_control('expire_actions', ['label'=>'Al terminar','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>['redirect'=>'Redirigir','hide'=>'Ocultar','message'=>'Mostrar mensaje'],'separator'=>'before']);
        $this->add_control('message_after_expire', ['label'=>'Mensaje','type'=>Controls_Manager::TEXTAREA,'dynamic'=>['active'=>true],'condition'=>['expire_actions'=>'message']]);
        $this->add_control('expire_redirect_url', ['label'=>'Redirigir a','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true],'condition'=>['expire_actions'=>'redirect']]);
        $this->end_controls_section();

        $this->start_controls_section('section_countdown_style', ['label'=>'Bloques','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('label_display', ['label'=>'Vista','type'=>Controls_Manager::CHOOSE,'default'=>'block','options'=>['block'=>['title'=>'Bloque','icon'=>'eicon-v-align-top'],'inline'=>['title'=>'En línea','icon'=>'eicon-h-align-right']],'prefix_class'=>'obx-countdown--label-']);
        $this->slider('container_width', 'Ancho del contenedor', '.obx-countdown', 'max-width: {{SIZE}}{{UNIT}};', ['default'=>['unit'=>'%','size'=>100],'size_units'=>['%','px','vw'],'condition'=>['label_display'=>'block']], true);
        $this->add_responsive_control('boxes_alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(),'selectors_dictionary'=>['start'=>'flex-start','end'=>'flex-end','left'=>'flex-start','right'=>'flex-end','center'=>'center'],'selectors'=>self::css('.obx-countdown', 'justify-content: {{VALUE}};'),'condition'=>['label_display'=>'inline']]);
        $this->color('box_background_color', 'Fondo', '.obx-countdown__item', 'background-color', ['separator'=>'before']);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'box_border','selector'=>'{{WRAPPER}} .obx-countdown__item']);
        $this->dimensions('box_border_radius', 'Radio del borde', '.obx-countdown__item', 'border-radius', [], false);
        $this->slider('box_spacing', 'Separación', '.obx-countdown', 'gap: {{SIZE}}{{UNIT}};', ['default'=>['size'=>10],'range'=>['px'=>['max'=>100]]], true);
        $this->dimensions('box_padding', 'Relleno', '.obx-countdown__item', 'padding');
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'box_shadow','selector'=>'{{WRAPPER}} .obx-countdown__item']);
        $this->end_controls_section();

        $this->start_controls_section('section_content_style', ['label'=>'Contenido','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('heading_digits', ['label'=>'Números','type'=>Controls_Manager::HEADING]);
        $this->color('digits_color', 'Color', '.obx-countdown__digits');
        $this->typography('digits_typography', '.obx-countdown__digits');
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'digits_text_shadow','selector'=>'{{WRAPPER}} .obx-countdown__digits']);
        $this->add_control('heading_label', ['label'=>'Etiquetas','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['show_labels!'=>'']]);
        $this->color('label_color', 'Color', '.obx-countdown__label', 'color', ['condition'=>['show_labels!'=>'']]);
        $this->typography('label_typography', '.obx-countdown__label', ['condition'=>['show_labels!'=>'']]);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'label_text_shadow','selector'=>'{{WRAPPER}} .obx-countdown__label','condition'=>['show_labels!'=>'']]);
        $this->add_group_control(Group_Control_Text_Stroke::get_type(), ['name'=>'text_stroke','selector'=>'{{WRAPPER}} .obx-countdown__label','condition'=>['show_labels!'=>'']]);
        $this->end_controls_section();

        $this->start_controls_section('section_expire_message_style', ['label'=>'Mensaje','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['expire_actions'=>'message']]);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'selectors'=>self::css('.obx-countdown__message', 'text-align: {{VALUE}};')] + self::logical_choose());
        $this->color('text_color', 'Color', '.obx-countdown__message');
        $this->typography('typography', '.obx-countdown__message');
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'message_text_shadow','selector'=>'{{WRAPPER}} .obx-countdown__message']);
        $this->dimensions('message_padding', 'Relleno', '.obx-countdown__message', 'padding');
        $this->end_controls_section();
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $this->add_render_attribute('countdown', 'class', ['obx-countdown','obx-countdown--' . (($s['label_display'] ?? 'block') === 'inline' ? 'inline' : 'block')]);
        if (($s['countdown_type'] ?? 'due_date') === 'evergreen') {
            $this->add_render_attribute('countdown', 'data-evergreen', (string) (max(0, (int) $s['evergreen_counter_hours']) * 3600 + max(0, (int) $s['evergreen_counter_minutes']) * 60));
            $this->add_render_attribute('countdown', 'data-id', $this->get_id());
        } else {
            $date = date_create_immutable((string) $s['due_date'], wp_timezone());
            $this->add_render_attribute('countdown', 'data-due', (string) ($date ? $date->getTimestamp() : time()));
        }
        $actions = array_values(array_intersect((array) ($s['expire_actions'] ?? []), ['redirect','hide','message']));
        $this->add_render_attribute('countdown', 'data-expire', implode(',', $actions));
        if (in_array('redirect', $actions, true) && !empty($s['expire_redirect_url']['url'])) { $this->add_render_attribute('countdown', 'data-redirect', esc_url_raw($s['expire_redirect_url']['url'])); }
        $labels = ($s['show_labels'] ?? 'yes') !== '';
        $custom = ($s['custom_labels'] ?? '') !== '';
        echo '<div ' . $this->get_render_attribute_string('countdown') . '>';
        foreach (self::LABELS as $unit => $default) {
            if (($s['show_' . $unit] ?? 'yes') !== 'yes') { continue; }
            $label = $custom && (string) ($s['label_' . $unit] ?? '') !== '' ? $s['label_' . $unit] : $default;
            echo '<div class="obx-countdown__item"><span class="obx-countdown__digits" data-unit="' . esc_attr($unit) . '">00</span>' . ($labels ? '<span class="obx-countdown__label">' . esc_html($label) . '</span>' : '<span class="screen-reader-text">' . esc_html($default) . '</span>') . '</div>';
        }
        echo '</div>';
        if (in_array('message', $actions, true)) { echo '<div class="obx-countdown__message" hidden>' . wp_kses_post((string) ($s['message_after_expire'] ?? '')) . '</div>'; }
    }
}

final class Share_Buttons extends Element {
    public const PRO = 'share-buttons';
    protected const V03 = ['buttons'=>'share_buttons','target'=>'share_url_type','align'=>'alignment','gap'=>'column_gap','color'=>'primary_color','text_color'=>'secondary_color'];
    /** network => [label, icon, share URL pattern] */
    public const NETWORKS = [
        'facebook'=>['Facebook','fab fa-facebook','https://www.facebook.com/sharer.php?u={url}'],
        'x-twitter'=>['X','fab fa-x-twitter','https://x.com/intent/post?text={text}&url={url}'],
        'twitter'=>['Twitter','fab fa-twitter','https://twitter.com/intent/tweet?text={text}&url={url}'],
        'threads'=>['Threads','fab fa-threads','https://threads.net/intent/post?text={text}%20{url}'],
        'linkedin'=>['LinkedIn','fab fa-linkedin','https://www.linkedin.com/shareArticle?mini=true&url={url}&title={text}'],
        'pinterest'=>['Pinterest','fab fa-pinterest','https://www.pinterest.com/pin/create/button/?url={url}&description={text}'],
        'reddit'=>['Reddit','fab fa-reddit','https://reddit.com/submit?url={url}&title={text}'],
        'vk'=>['VK','fab fa-vk','https://vkontakte.ru/share.php?url={url}&title={text}'],
        'odnoklassniki'=>['OK','fab fa-odnoklassniki','https://connect.ok.ru/offer?url={url}&title={text}'],
        'tumblr'=>['Tumblr','fab fa-tumblr','https://tumblr.com/share/link?url={url}'],
        'digg'=>['Digg','fab fa-digg','https://digg.com/submit?url={url}'],
        'skype'=>['Skype','fab fa-skype','https://web.skype.com/share?url={url}'],
        'stumbleupon'=>['StumbleUpon','fab fa-stumbleupon','https://www.stumbleupon.com/submit?url={url}'],
        'mix'=>['Mix','fab fa-mix','https://mix.com/add?url={url}'],
        'telegram'=>['Telegram','fab fa-telegram','https://telegram.me/share/url?url={url}&text={text}'],
        'pocket'=>['Pocket','fab fa-get-pocket','https://getpocket.com/edit?url={url}'],
        'xing'=>['XING','fab fa-xing','https://www.xing.com/spi/shares/new?url={url}'],
        'whatsapp'=>['WhatsApp','fab fa-whatsapp','https://api.whatsapp.com/send?text=*{text}*%0A{url}'],
        'email'=>['Correo','fas fa-envelope','mailto:?subject={text}&body={text}%0A{url}'],
        'print'=>['Imprimir','fas fa-print',''],
    ];
    public function get_title(): string { return 'Botones para compartir'; }
    public function get_icon(): string { return 'eicon-share'; }
    public function get_keywords(): array { return ['share','compartir','redes','social','facebook','whatsapp']; }
    public function get_style_depends(): array { return ['obx-frontend','elementor-icons-fa-brands','elementor-icons-fa-solid']; }

    protected static function upgrade_settings(array $s): array {
        foreach ((array) ($s['buttons'] ?? []) as $i => $item) {
            $network = ($item['network'] ?? 'facebook') === 'x' ? 'x-twitter' : ($item['network'] ?? 'facebook');
            $s['buttons'][$i] = ['_id'=>$item['_id'] ?? substr(md5((string) $i), 0, 7),'button'=>$network,'text'=>$item['text'] ?? ''];
        }
        $legacy = $s['skin'] ?? 'brand';
        $s['skin'] = $legacy === 'outline' ? 'framed' : 'flat';
        $s['color_source'] = $legacy === 'brand' ? 'official' : 'custom';
        if (($s['shape'] ?? 'rounded') === 'circle') { $s['shape'] = 'circle'; }
        if (($s['target'] ?? 'current') === 'current') { $s['target'] = 'current_page'; }
        return parent::upgrade_settings($s);
    }

    protected function register_controls(): void {
        $this->start_controls_section('section_buttons_content', ['label'=>'Botones']);
        $r = new Repeater();
        $r->add_control('button', ['label'=>'Red','type'=>Controls_Manager::SELECT,'default'=>'facebook','options'=>array_map(static fn($n) => $n[0], self::NETWORKS)]);
        $r->add_control('text', ['label'=>'Texto personalizado','type'=>Controls_Manager::TEXT]);
        $this->add_control('share_buttons', ['type'=>Controls_Manager::REPEATER,'fields'=>$r->get_controls(),'title_field'=>'{{{ button }}}','default'=>[['button'=>'facebook'],['button'=>'x-twitter'],['button'=>'linkedin']]]);
        $this->add_control('view', ['label'=>'Mostrar','type'=>Controls_Manager::SELECT,'default'=>'icon-text','options'=>['icon-text'=>'Icono y texto','icon'=>'Icono','text'=>'Texto'],'separator'=>'before','prefix_class'=>'obx-share-buttons--view-','render_type'=>'template']);
        $this->add_control('show_label', ['label'=>'Etiqueta','type'=>Controls_Manager::SWITCHER,'default'=>'yes','condition'=>['view'=>'icon-text']]);
        $this->add_control('skin', ['label'=>'Aspecto','type'=>Controls_Manager::SELECT,'default'=>'gradient','options'=>['gradient'=>'Degradado','minimal'=>'Mínimo','framed'=>'Enmarcado','boxed'=>'Icono en caja','flat'=>'Plano'],'prefix_class'=>'obx-share-buttons--skin-']);
        $this->add_control('shape', ['label'=>'Forma','type'=>Controls_Manager::SELECT,'default'=>'square','options'=>['square'=>'Cuadrada','rounded'=>'Redondeada','circle'=>'Círculo'],'prefix_class'=>'obx-share-buttons--shape-']);
        $this->add_responsive_control('columns', ['label'=>'Columnas','type'=>Controls_Manager::SELECT,'default'=>'0','options'=>['0'=>'Automático','1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6'],'prefix_class'=>'obx-grid%s-']);
        $this->add_responsive_control('alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options() + ['justify'=>['title'=>'Justificado','icon'=>'eicon-text-align-justify']],'prefix_class'=>'obx-share-buttons%s--align-','condition'=>['columns'=>'0']]);
        $this->add_control('share_url_type', ['label'=>'Dirección compartida','type'=>Controls_Manager::SELECT,'default'=>'current_page','options'=>['current_page'=>'Página actual','custom'=>'Personalizada'],'separator'=>'before']);
        $this->add_control('share_url', ['label'=>'Dirección','type'=>Controls_Manager::URL,'options'=>false,'dynamic'=>['active'=>true],'condition'=>['share_url_type'=>'custom']]);
        $this->end_controls_section();

        $this->start_controls_section('section_buttons_style', ['label'=>'Botones','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('column_gap', 'Separación entre columnas', '.obx-share', 'column-gap: {{SIZE}}{{UNIT}};', ['default'=>['size'=>10],'range'=>['px'=>['max'=>100]]], true);
        $this->slider('row_gap', 'Separación entre filas', '.obx-share', 'row-gap: {{SIZE}}{{UNIT}};', ['default'=>['size'=>10],'range'=>['px'=>['max'=>100]]], true);
        $this->slider('button_size', 'Tamaño del botón', '.obx-share__button', 'font-size: calc({{SIZE}}{{UNIT}} * 10);', ['size_units'=>['px'],'range'=>['px'=>['min'=>0.5,'max'=>2,'step'=>0.05]]], true);
        $this->slider('icon_size', 'Tamaño del icono', '.obx-share__icon', 'font-size: {{SIZE}}{{UNIT}};', ['default'=>['unit'=>'em'],'size_units'=>['em','px','rem'],'range'=>['em'=>['min'=>0.5,'max'=>4,'step'=>0.1]],'condition'=>['view!'=>'text']], true);
        $this->slider('button_height', 'Altura del botón', '.obx-share__button', 'height: {{SIZE}}{{UNIT}};', ['default'=>['unit'=>'em'],'size_units'=>['em','px','rem'],'range'=>['em'=>['min'=>1,'max'=>7,'step'=>0.1]]], true);
        $this->slider('border_size', 'Grosor del borde', '.obx-share__button', 'border-width: {{SIZE}}{{UNIT}};', ['default'=>['size'=>2],'size_units'=>['px','em'],'range'=>['px'=>['max'=>20]],'condition'=>['skin'=>['framed','boxed']]], true);
        $this->add_control('color_source', ['label'=>'Color','type'=>Controls_Manager::SELECT,'default'=>'official','options'=>['official'=>'El de cada red','custom'=>'Personalizado'],'prefix_class'=>'obx-share-buttons--color-','separator'=>'before']);
        $this->start_controls_tabs('tabs_button_style', ['condition'=>['color_source'=>'custom']]);
        $this->start_controls_tab('tab_button_normal', ['label'=>'Normal','condition'=>['color_source'=>'custom']]);
        $this->color('primary_color', 'Color principal', '.obx-share__button', '--obx-share-primary', ['condition'=>['color_source'=>'custom']]);
        $this->color('secondary_color', 'Color secundario', '.obx-share__button', '--obx-share-secondary', ['condition'=>['color_source'=>'custom','skin!'=>'framed']]);
        $this->end_controls_tab();
        $this->start_controls_tab('tab_button_hover', ['label'=>'Al pasar el ratón','condition'=>['color_source'=>'custom']]);
        $this->color('primary_color_hover', 'Color principal', '.obx-share__button:hover', '--obx-share-primary', ['condition'=>['color_source'=>'custom']]);
        $this->color('secondary_color_hover', 'Color secundario', '.obx-share__button:hover', '--obx-share-secondary', ['condition'=>['color_source'=>'custom']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->typography('typography', '.obx-share__title', ['separator'=>'before','exclude'=>['line_height']]);
        $this->dimensions('text_padding', 'Relleno del texto', '.obx-share__text', 'padding', ['condition'=>['view'=>'text']], false);
        $this->end_controls_section();
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $custom = ($s['share_url_type'] ?? 'current_page') === 'custom' && !empty($s['share_url']['url']);
        $url = $custom ? (string) $s['share_url']['url'] : (is_singular() ? (string) get_permalink() : home_url(add_query_arg([])));
        $title = $custom ? '' : wp_strip_all_tags(is_singular() ? get_the_title() : wp_get_document_title());
        $view = in_array($s['view'] ?? '', ['icon-text','icon','text'], true) ? $s['view'] : 'icon-text';
        $label = $view === 'text' || ($view === 'icon-text' && ($s['show_label'] ?? 'yes') === 'yes');
        $classes = ['obx-share','obx-share--view-' . $view,'obx-share--skin-' . (in_array($s['skin'] ?? '', ['gradient','minimal','framed','boxed','flat'], true) ? $s['skin'] : 'gradient'),'obx-share--shape-' . (in_array($s['shape'] ?? '', ['square','rounded','circle'], true) ? $s['shape'] : 'square'),'obx-share--color-' . (($s['color_source'] ?? 'official') === 'custom' ? 'custom' : 'official')];
        $columns = (int) ($s['columns'] ?? 0);
        if ($columns > 0) { $classes[] = 'obx-share--columns'; }
        echo '<div class="' . esc_attr(implode(' ', $classes)) . '"' . ($columns > 0 ? ' style="--obx-share-columns:' . $columns . '"' : '') . '>';
        foreach ((array) ($s['share_buttons'] ?? []) as $button) {
            $network = isset(self::NETWORKS[$button['button'] ?? '']) ? $button['button'] : 'facebook';
            [$name, $icon, $pattern] = self::NETWORKS[$network];
            $text = (string) ($button['text'] ?? '') !== '' ? $button['text'] : $name;
            $href = $pattern === '' ? '#' : str_replace(['{url}','{text}'], [rawurlencode($url), rawurlencode($title)], $pattern);
            $attrs = $network === 'print' ? ' data-obx-print role="button"' : ($network === 'email' ? '' : ' target="_blank" rel="noopener nofollow"');
            echo '<a class="obx-share__button obx-share__button--' . esc_attr($network) . ' elementor-repeater-item-' . esc_attr((string) ($button['_id'] ?? '')) . '" href="' . esc_url($href, $network === 'email' ? ['mailto'] : null) . '"' . $attrs . (!$label ? ' aria-label="' . esc_attr(sprintf('Compartir en %s', $name)) . '"' : '') . '>';
            if ($view !== 'text') { echo '<span class="obx-share__icon">' . Icons_Manager::try_get_icon_html(['value'=>$icon,'library'=>str_starts_with($icon, 'fab') ? 'fa-brands' : 'fa-solid'], ['aria-hidden'=>'true']) . '</span>'; }
            if ($label) { echo '<span class="obx-share__text"><span class="obx-share__title">' . esc_html($text) . '</span></span>'; }
            echo '</a>';
        }
        echo '</div>';
    }
}
