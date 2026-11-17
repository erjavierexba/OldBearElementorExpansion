<?php
namespace OldBearExpansion\Extras;

use Elementor\Controls_Manager;
use Elementor\Element_Base;
use OldBearExpansion\Plugin;
use const OldBearExpansion\VERSION;

if (!defined('ABSPATH')) { exit; }

/**
 * Element extras Elementor Free only advertises: custom CSS, custom attributes, sticky and
 * motion effects. Free's teaser controls are replaced by working ones stored under Elementor
 * Pro's keys (custom_css, _attributes, sticky*, motion_fx_*), so data moves both ways.
 */
final class Extras {
    public const DEVICES = ['desktop'=>'Escritorio','tablet'=>'Tableta','mobile'=>'Móvil'];
    private const TEASERS = ['custom_css_pro','custom_attributes_pro','scrolling_effects_pro','mouse_effects_pro','sticky_pro'];

    public static function boot(): void {
        add_action('elementor/element/before_section_end', [self::class, 'before_section_end'], 10, 3);
        add_action('elementor/element/after_section_start', [self::class, 'section_start'], 10, 3);
        add_action('elementor/element/after_section_end', [self::class, 'section_end'], 10, 3);
        add_action('elementor/element/parse_css', [self::class, 'element_css'], 10, 2);
        add_action('elementor/css-file/post/parse', [self::class, 'document_css']);
        add_action('elementor/element/after_add_attributes', [self::class, 'attributes']);
        add_action('elementor/frontend/after_register_scripts', [self::class, 'register_script']);
        add_action('elementor/frontend/after_enqueue_scripts', static function () { wp_enqueue_script('obx-extras'); });
        add_action('elementor/preview/enqueue_scripts', static function () { wp_enqueue_script('obx-extras'); });
    }

    public static function register_script(): void {
        wp_register_script('obx-extras', Plugin::url('assets/js/extras.js'), ['elementor-frontend'], VERSION, true);
    }

    /** Replace Elementor Free's Custom CSS teaser while its section is still open. */
    public static function before_section_end($stack, $section_id, $args): void {
        $background_section = ['container'=>'section_background','section'=>'section_background','column'=>'section_style'];
        if ($stack instanceof Element_Base && ($background_section[$stack->get_name()] ?? '') === $section_id && !$stack->get_controls('background_motion_fx_motion_fx_scrolling')) {
            self::motion_controls($stack, 'background_motion_fx_');
            return;
        }
        if ($section_id !== 'section_custom_css_pro' || !is_object($stack) || !method_exists($stack, 'add_control')) { return; }
        if ($stack->get_controls('custom_css_pro')) { $stack->update_control('custom_css_pro', ['condition'=>['__obx_never'=>'yes'],'classes'=>'elementor-hidden']); }
        $stack->update_control('section_custom_css_pro', ['label'=>'CSS personalizado']);
        if (!$stack->get_controls('custom_css')) {
            $stack->add_control('custom_css', ['label'=>'CSS','type'=>Controls_Manager::CODE,'language'=>'css','render_type'=>'ui','control_type'=>'content','show_label'=>false,'separator'=>'none','description'=>'Usa «selector» para referirte a este elemento o documento: selector .hijo { color: red; }'], ['overwrite'=>true]);
        }
    }

    /** Motion effects and sticky go first in «Motion Effects», as in Elementor Pro. */
    public static function section_start($element, $section_id, $args): void {
        if ($section_id !== 'section_effects' || !$element instanceof Element_Base) { return; }
        self::motion_controls($element);
        self::sticky_controls($element);
    }

    public static function section_end($element, $section_id, $args): void {
        if (!$element instanceof Element_Base) { return; }
        foreach (self::TEASERS as $teaser) {
            if ($teaser === 'custom_css_pro' && $element->get_controls('custom_css')) { continue; }
            if ($element->get_controls($teaser)) { $element->remove_control($teaser); }
        }
        $tab = $args['tab'] ?? Controls_Manager::TAB_ADVANCED;
        if ($section_id === 'section_custom_attributes_pro') {
            $element->remove_control('section_custom_attributes_pro');
            $element->start_controls_section('_section_attributes', ['label'=>'Atributos','tab'=>$tab]);
            $element->add_control('_attributes', ['label'=>'Atributos personalizados','type'=>Controls_Manager::TEXTAREA,'dynamic'=>['active'=>true],'placeholder'=>'clave|valor','description'=>'Un atributo por línea, con el formato clave|valor. Se omiten los eventos (on…) y href.','render_type'=>'none','classes'=>'elementor-control-direction-ltr']);
            $element->end_controls_section();
        }
    }

    /**
     * Scrolling and mouse effects with Elementor Pro's keys and defaults. «motion_fx_» acts on the element;
     * «background_motion_fx_» (containers, sections and columns) moves the background on its own layer.
     */
    private static function motion_controls(Element_Base $element, string $p = 'motion_fx_'): void {
        $background = $p !== 'motion_fx_';
        $range = static fn(int $start, int $end) => ['unit'=>'%','size'=>'','sizes'=>['start'=>$start,'end'=>$end]];
        $speed = static fn(float $size) => ['unit'=>'px','size'=>$size,'sizes'=>[]];
        $common = ['render_type'=>'none','frontend_available'=>true];
        $element->add_control($p . 'motion_fx_scrolling', ['label'=>$background ? 'Efectos de desplazamiento del fondo' : 'Efectos de desplazamiento','type'=>Controls_Manager::SWITCHER,'label_off'=>'No','label_on'=>'Sí','separator'=>$background ? 'before' : 'default','render_type'=>'ui','frontend_available'=>true]);
        $on = [$p . 'motion_fx_scrolling'=>'yes'];
        // [label, direction options, default direction, amount control, amount default, range key, range default]
        $effects = [
            'translateY'=>['Desplazamiento vertical',[''=>'Hacia arriba','negative'=>'Hacia abajo'],'','speed',4,'affectedRange',[0,100]],
            'translateX'=>['Desplazamiento horizontal',[''=>'Hacia la izquierda','negative'=>'Hacia la derecha'],'','speed',4,'affectedRange',[0,100]],
            'opacity'=>['Transparencia',['out-in'=>'Aparecer','in-out'=>'Desvanecer','in-out-in'=>'Desvanecer y aparecer','out-in-out'=>'Aparecer y desvanecer'],'out-in','level',10,'range',[20,80]],
            'blur'=>['Desenfoque',['out-in'=>'Enfocar','in-out'=>'Desenfocar','in-out-in'=>'Desenfocar y enfocar','out-in-out'=>'Enfocar y desenfocar'],'out-in','level',7,'range',[20,80]],
            'rotateZ'=>['Rotación',[''=>'A la izquierda','negative'=>'A la derecha'],'','speed',1,'affectedRange',[0,100]],
            'scale'=>['Escala',['out-in'=>'Acercar','in-out'=>'Alejar','in-out-in'=>'Alejar y acercar','out-in-out'=>'Acercar y alejar'],'out-in','speed',4,'range',[20,80]],
        ];
        foreach ($effects as $name => [$label, $directions, $direction, $amount, $amount_default, $range_key, [$start, $end]]) {
            $key = $p . $name;
            $element->add_control($key . '_effect', ['label'=>$label,'type'=>Controls_Manager::POPOVER_TOGGLE,'condition'=>$on] + $common);
            $element->start_popover();
            $show = $on + [$key . '_effect'=>'yes'];
            $element->add_control($key . '_direction', ['label'=>'Dirección','type'=>Controls_Manager::SELECT,'default'=>$direction,'options'=>$directions,'condition'=>$show] + $common);
            if ($amount === 'speed') {
                $element->add_control($key . '_speed', ['label'=>'Velocidad','type'=>Controls_Manager::SLIDER,'default'=>$speed($amount_default),'range'=>['px'=>['max'=>10,'step'=>0.1]],'condition'=>$show] + $common);
            } else {
                $element->add_control($key . '_level', ['label'=>'Nivel','type'=>Controls_Manager::SLIDER,'default'=>$speed($amount_default),'range'=>['px'=>['min'=>1,'max'=>$name === 'blur' ? 15 : 10,'step'=>0.1]],'condition'=>$show] + $common);
            }
            $element->add_control($key . '_' . $range_key, ['label'=>'Viewport','type'=>Controls_Manager::SLIDER,'default'=>$range($start, $end),'labels'=>['Abajo','Arriba'],'scales'=>1,'handles'=>'range','condition'=>$show] + $common);
            $element->end_popover();
        }
        if (!$background) {
            $origin = ['conditions'=>['terms'=>[['name'=>$p . 'motion_fx_scrolling','value'=>'yes'],['relation'=>'or','terms'=>[['name'=>$p . 'rotateZ_effect','value'=>'yes'],['name'=>$p . 'scale_effect','value'=>'yes']]]]]];
            $element->add_control($p . 'transform_origin_x', ['label'=>'Punto de anclaje X','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-h-align-center'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right']],'toggle'=>false] + $origin + $common);
            $element->add_control($p . 'transform_origin_y', ['label'=>'Punto de anclaje Y','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'center'=>['title'=>'Centro','icon'=>'eicon-v-align-middle'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']],'toggle'=>false] + $origin + $common);
        }
        $element->add_control($p . 'devices', ['label'=>'Aplicar en','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'default'=>array_keys(self::DEVICES),'options'=>self::DEVICES,'condition'=>$on] + $common);
        $element->add_control($p . 'range', ['label'=>'Efectos relativos a','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Por defecto','viewport'=>'Ventana','page'=>'Página completa'],'condition'=>$on] + $common);

        $element->add_control($p . 'motion_fx_mouse', ['label'=>$background ? 'Efectos del ratón en el fondo' : 'Efectos del ratón','type'=>Controls_Manager::SWITCHER,'label_off'=>'No','label_on'=>'Sí','separator'=>'before'] + $common);
        $mouse = [$p . 'motion_fx_mouse'=>'yes'];
        foreach (['mouseTrack'=>['Seguir el ratón',1],'tilt'=>['Inclinación 3D',4]] as $name => [$label, $default]) {
            $key = $p . $name;
            $element->add_control($key . '_effect', ['label'=>$label,'type'=>Controls_Manager::POPOVER_TOGGLE,'condition'=>$mouse] + $common);
            $element->start_popover();
            $element->add_control($key . '_direction', ['label'=>'Dirección','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Con el ratón','negative'=>'Contraria'],'condition'=>$mouse + [$key . '_effect'=>'yes']] + $common);
            $element->add_control($key . '_speed', ['label'=>'Velocidad','type'=>Controls_Manager::SLIDER,'default'=>$speed($default),'range'=>['px'=>['max'=>10,'step'=>0.1]],'condition'=>$mouse + [$key . '_effect'=>'yes']] + $common);
            $element->end_popover();
        }
        $element->add_control($background ? 'background_handle_motion_fx_asset_loading' : 'handle_motion_fx_asset_loading', ['type'=>Controls_Manager::HIDDEN,'default'=>'']);
    }

    private static function sticky_controls(Element_Base $element): void {
        $element->add_control('sticky', ['label'=>'Fijo al desplazar','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Ninguno','top'=>'Arriba','bottom'=>'Abajo'],'separator'=>'before','render_type'=>'none','frontend_available'=>true]);
        $on = ['sticky!'=>''];
        $element->add_control('sticky_on', ['label'=>'Fijo en','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'default'=>array_keys(self::DEVICES),'options'=>self::DEVICES,'condition'=>$on,'render_type'=>'none','frontend_available'=>true]);
        $element->add_responsive_control('sticky_offset', ['label'=>'Distancia','type'=>Controls_Manager::NUMBER,'default'=>0,'min'=>0,'max'=>500,'required'=>true,'condition'=>$on,'render_type'=>'none','frontend_available'=>true]);
        $element->add_responsive_control('sticky_effects_offset', ['label'=>'Distancia para los efectos','type'=>Controls_Manager::NUMBER,'default'=>0,'min'=>0,'max'=>1000,'required'=>true,'description'=>'A partir de este desplazamiento se añade la clase elementor-sticky--effects.','condition'=>$on,'render_type'=>'none','frontend_available'=>true]);
        $element->add_responsive_control('sticky_anchor_link_offset', ['label'=>'Compensación de anclas','type'=>Controls_Manager::NUMBER,'default'=>0,'min'=>0,'max'=>500,'condition'=>['sticky'=>'top'],'render_type'=>'none','frontend_available'=>true]);
        $element->add_control('sticky_parent', ['label'=>'Dentro de su contenedor','type'=>Controls_Manager::SWITCHER,'label_off'=>'No','label_on'=>'Sí','condition'=>$on,'render_type'=>'none','frontend_available'=>true]);
    }

    public static function attributes(Element_Base $element): void {
        $raw = (string) $element->get_settings_for_display('_attributes');
        if (trim($raw) === '') { return; }
        $blocked = ['id','class','href','src','srcdoc','form','formaction','xlink:href','data-id','data-settings','data-element_type','data-widget_type','data-model-cid','style'];
        foreach (\Elementor\Utils::parse_custom_attributes($raw, "\n") as $key => $value) {
            $key = strtolower((string) $key);
            if (!str_starts_with($key, 'on') && !in_array($key, $blocked, true)) { $element->add_render_attribute('_wrapper', $key, $value); }
        }
    }

    public static function clean_css(string $css): string {
        return trim(str_ireplace(['</style', '<'], '', $css));
    }

    public static function element_css($post_css, $element): void {
        if (!$element instanceof Element_Base) { return; }
        $css = self::clean_css((string) $element->get_settings('custom_css'));
        if ($css === '') { return; }
        $css = str_replace('selector', $post_css->get_element_unique_selector($element), $css);
        $post_css->get_stylesheet()->add_raw_css($css);
    }

    public static function document_css($post_css): void {
        if (!method_exists($post_css, 'get_post_id')) { return; }
        $document = \Elementor\Plugin::$instance->documents->get((int) $post_css->get_post_id());
        if (!$document) { return; }
        $css = self::clean_css((string) $document->get_settings('custom_css'));
        if ($css === '') { return; }
        $post_css->get_stylesheet()->add_raw_css(str_replace('selector', $document->get_css_wrapper_selector(), $css));
    }
}
