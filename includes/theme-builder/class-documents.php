<?php
namespace OldBearExpansion\ThemeBuilder;

use Elementor\Controls_Manager;
use Elementor\Modules\Library\Documents\Library_Document;
use Elementor\Repeater;

if (!defined('ABSPATH')) { exit; }

/** Base class for Theme Builder templates (stored in the Elementor library). */
abstract class Location_Document extends Library_Document {
    public const LOCATION = '';
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

    public static function get_type() { return 'obx-' . static::LOCATION; }

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
                'label'=>'Reglas','type'=>Controls_Manager::REPEATER,'fields'=>$repeater->get_controls(),'prevent_empty'=>false,
                'default'=>[['mode'=>'include','rule'=>Conditions::default_rule(static::LOCATION),'object_id'=>'']],
                'title_field'=>'{{{ mode === "exclude" ? "Excluir" : "Incluir" }}} · {{{ (' . wp_json_encode(Conditions::rules(static::LOCATION)) . ')[rule] || rule }}}{{{ object_id ? " #" + object_id : "" }}}',
            ]);
            $this->add_control('obx_conditions_help', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Si varias plantillas coinciden, gana la regla más específica (ID concreto &gt; tipo &gt; todo el sitio). Las exclusiones siempre ganan.','content_classes'=>'elementor-descriptor']);
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

    /** @return array<int,array> */
    public function get_conditions(): array {
        if (static::LOCATION === 'error-404') { return [['mode'=>'include','rule'=>'not_found','ids'=>[]]]; }
        return Conditions::normalize($this->get_settings(self::CONDITIONS));
    }
}

final class Header_Document extends Location_Document {
    public const LOCATION = 'header';
    public static function get_title() { return 'Cabecera'; }
    public static function get_plural_title() { return 'Cabeceras'; }
}
final class Footer_Document extends Location_Document {
    public const LOCATION = 'footer';
    public static function get_title() { return 'Pie de página'; }
    public static function get_plural_title() { return 'Pies de página'; }
}
final class Single_Document extends Location_Document {
    public const LOCATION = 'single';
    public static function get_title() { return 'Entrada individual'; }
    public static function get_plural_title() { return 'Entradas individuales'; }
}
final class Archive_Document extends Location_Document {
    public const LOCATION = 'archive';
    public static function get_title() { return 'Archivo'; }
    public static function get_plural_title() { return 'Archivos'; }
}
final class Error_Document extends Location_Document {
    public const LOCATION = 'error-404';
    public static function get_title() { return 'Página 404'; }
    public static function get_plural_title() { return 'Páginas 404'; }
}
