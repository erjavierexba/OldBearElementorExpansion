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

        if (in_array(static::LOCATION, ['single','archive'], true)) {
            $this->start_controls_section('obx_preview_section', ['label'=>'Vista previa','tab'=>Controls_Manager::TAB_SETTINGS]);
            $options = [''=>'Contenido más reciente'];
            if (static::LOCATION === 'single') {
                foreach (get_posts(['post_type'=>array_keys(Conditions::post_types()),'posts_per_page'=>40,'post_status'=>'publish']) as $post) {
                    $options[(string) $post->ID] = get_post_type_object($post->post_type)->labels->singular_name . ': ' . $post->post_title;
                }
            }
            $this->add_control(self::PREVIEW, ['label'=>static::LOCATION === 'single' ? 'Previsualizar con' : 'Previsualizar con entradas recientes','type'=>Controls_Manager::SELECT,'options'=>$options,'default'=>'','label_block'=>true,'description'=>'Solo afecta al editor. Guarda y recarga para aplicar.']);
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
            $conditions = Conditions::normalize($settings[self::CONDITIONS]);
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
