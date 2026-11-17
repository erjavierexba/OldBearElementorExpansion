<?php
namespace OldBearExpansion\ThemeBuilder;
use Elementor\Controls_Manager;
use Elementor\Modules\Library\Documents\Library_Document;
use Elementor\Repeater;

if (!defined('ABSPATH')) { exit; }

/** Base class for Theme Builder templates (stored in the Elementor library). */
abstract class Location_Document extends Library_Document {
    /** Where it prints: header, footer, single, archive or error-404. */
    public const LOCATION = '';
    /** Template type stored in _elementor_template_type, the same one Elementor Pro uses. */
    public const TYPE = '';
    public const CONDITIONS = 'obx_conditions';
    public const PREVIEW = 'obx_preview_id';
    /** Whether display conditions decide where it prints (loop items are only used by widgets). */
    public const HAS_CONDITIONS = true;
    /** Preview content shown while editing: 'single' (one post), 'archive' (recent posts) or ''. */
    public const PREVIEW_KIND = '';

    public static function get_properties() {
        $properties = parent::get_properties();
        $properties['support_kit'] = true;
        $properties['support_wp_page_templates'] = true;
        $properties['show_in_finder'] = true;
        $properties['obx_location'] = static::LOCATION;
        return $properties;
    }

    public static function get_type() { return static::TYPE; }

    /** Templates are edited on a blank canvas: the theme header/footer would only get in the way. */
    public function get_meta($key) {
        $value = parent::get_meta($key);
        return $key === '_wp_page_template' && (!$value || $value === 'default') ? 'elementor_canvas' : $value;
    }

    public function get_container_attributes() {
        $attributes = parent::get_container_attributes();
        $attributes['class'] .= ' obx-location obx-location-' . static::LOCATION;
        return $attributes;
    }

    protected function register_controls() {
        parent::register_controls();
        if (static::HAS_CONDITIONS) { $this->conditions_controls(); }
        $this->preview_controls();
    }

    private function conditions_controls(): void {
        $this->start_controls_section('obx_conditions_section', ['label'=>'Condiciones de visualización','tab'=>Controls_Manager::TAB_SETTINGS]);
        if (static::LOCATION === 'error-404') {
            $this->add_control('obx_404_note', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Esta plantilla se muestra automáticamente en las páginas no encontradas.','content_classes'=>'elementor-descriptor']);
        } else {
            $repeater = new Repeater();
            $repeater->add_control('mode', ['label'=>'Acción','type'=>Controls_Manager::SELECT,'options'=>['include'=>'Incluir','exclude'=>'Excluir'],'default'=>'include']);
            $repeater->add_control('rule', ['label'=>'Dónde','type'=>Controls_Manager::SELECT,'options'=>Conditions::rules(static::LOCATION),'default'=>Conditions::default_rule(static::LOCATION),'label_block'=>true]);
            $repeater->add_control('object_id', ['label'=>'IDs concretos','type'=>Controls_Manager::TEXT,'placeholder'=>'Vacío = todos · 12, 34','description'=>'Entradas, términos o autores, separados por comas. Obligatorio en «Con término».','label_block'=>true]);
            $this->add_control(self::CONDITIONS, [
                'label'=>'Reglas','type'=>Controls_Manager::REPEATER,'fields'=>$repeater->get_controls(),'prevent_empty'=>false,'default'=>[],
                'title_field'=>'{{{ mode === "exclude" ? "Excluir" : "Incluir" }}} · {{{ (' . wp_json_encode(Conditions::rules(static::LOCATION)) . ')[rule] || rule }}}{{{ object_id ? " #" + object_id : "" }}}',
            ]);
            $this->add_control('obx_conditions_help', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Sin reglas, la plantilla no se muestra. Si varias plantillas coinciden, gana la regla más específica (ID concreto &gt; tipo &gt; todo el sitio). Las exclusiones siempre ganan.','content_classes'=>'elementor-descriptor']);
        }
        $this->end_controls_section();
    }

    private function preview_controls(): void {
        $kind = static::PREVIEW_KIND ?: (in_array(static::LOCATION, ['single','archive'], true) ? static::LOCATION : '');
        if ($kind !== '') {
            $this->start_controls_section('obx_preview_section', ['label'=>'Vista previa','tab'=>Controls_Manager::TAB_SETTINGS]);
            $options = [''=>'Contenido más reciente'];
            if ($kind === 'single') {
                foreach (get_posts(['post_type'=>array_keys(Conditions::post_types()),'posts_per_page'=>40,'post_status'=>'publish']) as $post) {
                    $options[(string) $post->ID] = get_post_type_object($post->post_type)->labels->singular_name . ': ' . $post->post_title;
                }
            }
            $this->add_control(self::PREVIEW, ['label'=>$kind === 'single' ? 'Previsualizar con' : 'Previsualizar con entradas recientes','type'=>Controls_Manager::SELECT,'options'=>$options,'default'=>'','label_block'=>true,'description'=>'Solo afecta al editor. Guarda y recarga para aplicar.']);
            $this->end_controls_section();
        }
    }

    /**
     * Conditions from the editor repeater when it has been saved, otherwise from the
     * `_elementor_conditions` meta (templates made with Elementor Pro or imported from a kit).
     * @return array<int,array>
     */
    public function get_conditions(): array {
        $id = (int) $this->get_main_id();
        $settings = get_post_meta($id, '_elementor_page_settings', true);
        if (is_array($settings) && array_key_exists(self::CONDITIONS, $settings)) {
            $conditions = Conditions::normalize($settings[self::CONDITIONS], Conditions::default_rule(static::LOCATION));
        } else {
            $conditions = Conditions::from_strings(get_post_meta($id, Conditions::META, true) ?: []);
        }
        if (!$conditions && static::LOCATION === 'error-404') {
            $conditions = [['mode'=>'include','rule'=>'singular/not_found404','ids'=>[]]];
        }
        return $conditions;
    }

    /** Writes conditions in both formats: the editor repeater and Elementor Pro's meta. */
    public static function store_conditions(int $id, array $conditions): void {
        $settings = get_post_meta($id, '_elementor_page_settings', true);
        $settings = is_array($settings) ? $settings : [];
        $settings[self::CONDITIONS] = Conditions::to_rows($conditions);
        update_post_meta($id, '_elementor_page_settings', $settings);
        update_post_meta($id, Conditions::META, Conditions::to_strings($conditions));
    }
}

final class Header_Document extends Location_Document {
    public const LOCATION = 'header';
    public const TYPE = 'header';
    public static function get_title() { return 'Cabecera'; }
    public static function get_plural_title() { return 'Cabeceras'; }
}
final class Footer_Document extends Location_Document {
    public const LOCATION = 'footer';
    public const TYPE = 'footer';
    public static function get_title() { return 'Pie de página'; }
    public static function get_plural_title() { return 'Pies de página'; }
}
class Single_Post_Document extends Location_Document {
    public const LOCATION = 'single';
    public const TYPE = 'single-post';
    public static function get_title() { return 'Entrada individual'; }
    public static function get_plural_title() { return 'Entradas individuales'; }
}
final class Single_Page_Document extends Location_Document {
    public const LOCATION = 'single';
    public const TYPE = 'single-page';
    public static function get_title() { return 'Página individual'; }
    public static function get_plural_title() { return 'Páginas individuales'; }
}
/** Generic single template from older Elementor Pro versions. */
final class Single_Document extends Location_Document {
    public const LOCATION = 'single';
    public const TYPE = 'single';
    public static function get_title() { return 'Individual'; }
    public static function get_plural_title() { return 'Individuales'; }
}
class Archive_Document extends Location_Document {
    public const LOCATION = 'archive';
    public const TYPE = 'archive';
    public static function get_title() { return 'Archivo'; }
    public static function get_plural_title() { return 'Archivos'; }
}
final class Search_Results_Document extends Location_Document {
    public const LOCATION = 'archive';
    public const TYPE = 'search-results';
    public static function get_title() { return 'Resultados de búsqueda'; }
    public static function get_plural_title() { return 'Resultados de búsqueda'; }
}
final class Error_Document extends Location_Document {
    public const LOCATION = 'error-404';
    public const TYPE = 'error-404';
    public static function get_title() { return 'Página 404'; }
    public static function get_plural_title() { return 'Páginas 404'; }
}

/** Template rendered for every post of a Loop Grid / Loop Carousel. */
final class Loop_Item_Document extends Location_Document {
    public const LOCATION = 'loop-item';
    public const TYPE = 'loop-item';
    public const HAS_CONDITIONS = false;
    public const PREVIEW_KIND = 'single';
    public static function get_title() { return 'Elemento de bucle'; }
    public static function get_plural_title() { return 'Elementos de bucle'; }
    public function get_container_attributes() {
        $attributes = parent::get_container_attributes();
        $attributes['class'] .= ' e-loop-item-template';
        return $attributes;
    }
}

final class Product_Document extends Single_Post_Document {
    public const TYPE = 'product';
    public static function get_title() { return 'Producto individual'; }
    public static function get_plural_title() { return 'Productos individuales'; }
}

final class Product_Archive_Document extends Archive_Document {
    public const TYPE = 'product-archive';
    public static function get_title() { return 'Archivo de productos'; }
    public static function get_plural_title() { return 'Archivos de productos'; }
}

/** Elementor Pro-compatible popup document; Popups handles display outside theme locations. */
final class Popup_Document extends Location_Document {
    public const LOCATION = 'popup';
    public const TYPE = 'popup';
    public static function get_title() { return 'Ventana emergente'; }
    public static function get_plural_title() { return 'Ventanas emergentes'; }

    protected function register_controls() {
        parent::register_controls();
        $this->start_controls_section('popup_layout', ['label'=>'Diseño de la ventana','tab'=>Controls_Manager::TAB_SETTINGS]);
        $this->add_responsive_control('width', ['label'=>'Ancho','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','vw'],'default'=>['unit'=>'px','size'=>640]]);
        $this->add_control('height_type', ['label'=>'Altura','type'=>Controls_Manager::SELECT,'default'=>'auto','options'=>['auto'=>'Ajustar al contenido','fit_to_screen'=>'Ajustar a pantalla','custom'=>'Personalizada']]);
        $this->add_responsive_control('height', ['label'=>'Altura personalizada','type'=>Controls_Manager::SLIDER,'size_units'=>['px','vh'],'condition'=>['height_type'=>'custom']]);
        $this->add_control('horizontal_position', ['label'=>'Posición horizontal','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-h-align-center'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right']]]);
        $this->add_control('vertical_position', ['label'=>'Posición vertical','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'center'=>['title'=>'Centro','icon'=>'eicon-v-align-middle'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']]]);
        $this->add_control('overlay', ['label'=>'Superposición','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('close_button', ['label'=>'Botón de cierre','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('entrance_animation', ['label'=>'Animación de entrada','type'=>Controls_Manager::ANIMATION,'default'=>'fadeIn']);
        $this->add_control('exit_animation', ['label'=>'Animación de salida','type'=>Controls_Manager::ANIMATION,'default'=>'fadeOut']);
        $this->end_controls_section();

        $this->start_controls_section('obx_popup_triggers', ['label'=>'Disparadores','tab'=>Controls_Manager::TAB_SETTINGS]);
        $this->add_control('obx_page_load', ['label'=>'Al cargar la página','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('obx_page_load_delay', ['label'=>'Retraso (segundos)','type'=>Controls_Manager::NUMBER,'default'=>0,'min'=>0,'condition'=>['obx_page_load'=>'yes']]);
        $this->add_control('obx_scroll', ['label'=>'Al desplazarse','type'=>Controls_Manager::SWITCHER]);
        $this->add_control('obx_scroll_percent', ['label'=>'Porcentaje','type'=>Controls_Manager::NUMBER,'default'=>50,'min'=>1,'max'=>100,'condition'=>['obx_scroll'=>'yes']]);
        $this->add_control('obx_exit_intent', ['label'=>'Al intentar salir','type'=>Controls_Manager::SWITCHER]);
        $this->add_control('obx_inactivity', ['label'=>'Tras inactividad','type'=>Controls_Manager::SWITCHER]);
        $this->add_control('obx_inactivity_time', ['label'=>'Segundos de inactividad','type'=>Controls_Manager::NUMBER,'default'=>30,'min'=>1,'condition'=>['obx_inactivity'=>'yes']]);
        $this->add_control('open_by_selector', ['label'=>'Al hacer clic en selector','type'=>Controls_Manager::TEXT,'placeholder'=>'.abrir-popup','description'=>'Selector CSS. También funciona el enlace dinámico «Ventana emergente».']);
        $this->end_controls_section();

        $this->start_controls_section('obx_popup_advanced', ['label'=>'Reglas avanzadas','tab'=>Controls_Manager::TAB_SETTINGS]);
        $this->add_control('obx_show_times', ['label'=>'Mostrar como máximo','type'=>Controls_Manager::NUMBER,'min'=>0,'description'=>'Número de veces por visitante; 0 no limita.']);
        $this->add_control('obx_prevent_close_overlay', ['label'=>'No cerrar al pulsar fuera','type'=>Controls_Manager::SWITCHER]);
        $this->add_control('obx_prevent_close_esc', ['label'=>'No cerrar con Escape','type'=>Controls_Manager::SWITCHER]);
        $this->add_control('obx_prevent_scroll', ['label'=>'Bloquear desplazamiento','type'=>Controls_Manager::SWITCHER]);
        $this->end_controls_section();
    }
}
