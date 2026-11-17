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
    /** Legacy preview key (0.1–0.7); read as a fallback of Elementor Pro's preview_type / preview_id. */
    public const PREVIEW = 'obx_preview_id';
    /** Whether display conditions decide where it prints (loop items are only used by widgets). */
    public const HAS_CONDITIONS = true;
    /** Preview content shown while editing: 'single' (one post), 'archive' (recent posts) or ''. */
    public const PREVIEW_KIND = '';
    /** Default of preview_type, as in Elementor Pro (e.g. single/post, archive/recent_posts, search). */
    public const PREVIEW_DEFAULT = '';
    /** Which preview_type groups are offered: any, single, archive, search, 404, loop or product-archive. */
    public const PREVIEW_GROUPS = 'any';
    /** Whether the template can pick a page layout (page_template), like single and archive templates in Pro. */
    public const HAS_PAGE_TEMPLATE = false;
    public const WRAPPER_TAGS = ['div'=>'div','main'=>'main','article'=>'article','header'=>'header','footer'=>'footer','section'=>'section','aside'=>'aside','nav'=>'nav'];

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

    /** Same markup as Elementor, with the wrapper tag chosen in «content_wrapper_html_tag». */
    public function print_elements_with_wrapper($elements_data = null) {
        if (!$elements_data) { $elements_data = $this->get_elements_data(); }
        $tag = (string) $this->get_settings('content_wrapper_html_tag');
        $tag = isset(self::WRAPPER_TAGS[$tag]) ? $tag : 'div';
        echo '<' . $tag . ' ' . \Elementor\Utils::render_html_attributes($this->get_container_attributes()) . '>';
        $this->print_elements($elements_data);
        echo '</' . $tag . '>';
    }

    protected function register_controls() {
        parent::register_controls();
        $this->document_controls();
        if (static::HAS_CONDITIONS) { $this->conditions_controls(); }
        $this->preview_controls();
        $this->style_controls();
        $this->custom_css_controls();
        if (static::HAS_PAGE_TEMPLATE && class_exists(\OldBearExpansion\Extras\Site_Settings::class)) { \OldBearExpansion\Extras\Site_Settings::scroll_snap_controls($this); }
    }

    /** «content_wrapper_html_tag» and, for single and archive templates, «page_template» (Pro's keys). */
    protected function document_controls(): void {
        $this->start_injection(['of'=>'post_status','fallback'=>['of'=>'post_title']]);
        if (static::HAS_PAGE_TEMPLATE) {
            $this->add_control('page_template', ['label'=>'Diseño de página','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Por defecto del tema','elementor_canvas'=>'Lienzo de Elementor','elementor_header_footer'=>'Ancho completo de Elementor'],'description'=>'Cómo se envuelve la plantilla en la página: con la cabecera y el pie del sitio o en un lienzo vacío.']);
        }
        $this->add_control('content_wrapper_html_tag', ['label'=>'Etiqueta HTML','type'=>Controls_Manager::SELECT,'default'=>'div','options'=>self::WRAPPER_TAGS]);
        $this->end_injection();
    }

    /** Margin, padding and background of the template, in Pro's «section_page_style». */
    protected function style_controls(): void {
        $this->start_controls_section('section_page_style', ['label'=>'Estilo','tab'=>Controls_Manager::TAB_STYLE]);
        if (in_array(static::LOCATION, ['header','footer'], true)) { $this->add_control('hidden_header_footer_style_control', ['type'=>Controls_Manager::HIDDEN,'default'=>'hidden_control']); }
        $this->add_group_control(\Elementor\Group_Control_Background::get_type(), ['name'=>'background','types'=>['classic','gradient'],'selector'=>'{{WRAPPER}}']);
        $this->add_responsive_control('margin', ['label'=>'Margen','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'selectors'=>['{{WRAPPER}}'=>'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'selectors'=>['{{WRAPPER}}'=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    /** Custom CSS of the template («custom_css», printed by Extras::document_css). */
    protected function custom_css_controls(): void {
        if ($this->get_controls('custom_css')) { return; }
        $this->start_controls_section('section_custom_css', ['label'=>'CSS personalizado','tab'=>Controls_Manager::TAB_ADVANCED]);
        $this->add_control('custom_css', ['label'=>'CSS','type'=>Controls_Manager::CODE,'language'=>'css','render_type'=>'ui','show_label'=>false,'separator'=>'none','description'=>'Usa «selector» para referirte a la plantilla: selector .hijo { color: red; }']);
        $this->end_controls_section();
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

    /**
     * «Preview settings» with Elementor Pro's keys: preview_type (single/{type}, archive/recent_posts, archive/date,
     * archive/author, taxonomy/{tax}, post_type_archive/{type}, search, page/404), preview_id and preview_search_term.
     */
    protected function preview_controls(): void {
        $this->start_controls_section('preview_settings', ['label'=>'Vista previa','tab'=>Controls_Manager::TAB_SETTINGS]);
        $this->add_control('preview_type', ['label'=>'Previsualizar como','type'=>Controls_Manager::SELECT,'default'=>static::PREVIEW_DEFAULT,'groups'=>self::preview_groups(static::PREVIEW_GROUPS),'label_block'=>true,'export'=>false]);
        $this->add_control('preview_id', ['label'=>'Contenido','type'=>Controls_Manager::SELECT2,'options'=>self::preview_objects(),'label_block'=>true,'export'=>false,'condition'=>['preview_type!'=>['','search','page/404','archive/recent_posts','archive/date']],'description'=>'Vacío: el más reciente.']);
        $this->add_control('preview_search_term', ['label'=>'Buscar','type'=>Controls_Manager::TEXT,'label_block'=>true,'export'=>false,'condition'=>['preview_type'=>'search']]);
        $this->add_control('apply_preview', ['type'=>Controls_Manager::BUTTON,'label'=>'Aplicar','text'=>'Guardar y previsualizar','separator'=>'none','event'=>'obx:document:applyPreview']);
        $this->end_controls_section();
    }

    /** preview_type choices as select groups (same values as Elementor Pro). */
    public static function preview_groups(string $which): array {
        $single = [];
        foreach (get_post_types(['public'=>true], 'objects') as $type) {
            if (in_array($type->name, ['elementor_library','e-landing-page'], true)) { continue; }
            $single['single/' . $type->name] = $type->labels->singular_name;
        }
        $archive = ['archive/recent_posts'=>'Entradas recientes','archive/date'=>'Archivo por fecha','archive/author'=>'Archivo de autor','search'=>'Resultados de búsqueda'];
        foreach (get_taxonomies(['public'=>true], 'objects') as $taxonomy) { $archive['taxonomy/' . $taxonomy->name] = 'Archivo: ' . $taxonomy->labels->singular_name; }
        foreach (get_post_types(['public'=>true,'has_archive'=>true], 'objects') as $type) { $archive['post_type_archive/' . $type->name] = 'Archivo: ' . $type->labels->name; }
        $groups = [];
        switch ($which) {
            case 'single': $groups = ['single'=>['label'=>'Individual','options'=>$single],'page/404'=>'Página 404']; break;
            case 'archive': $groups = ['archive'=>['label'=>'Archivo','options'=>$archive]]; break;
            case 'search': $groups = ['archive'=>['label'=>'Archivo','options'=>['search'=>'Resultados de búsqueda']]]; break;
            case '404': $groups = ['page/404'=>'Página 404']; break;
            case 'loop': $groups = ['single'=>['label'=>'Individual','options'=>array_intersect_key($single, array_flip(['single/post','single/page','single/product'])) ?: $single]]; break;
            case 'product-archive':
                $groups = ['archive'=>['label'=>'Archivo','options'=>array_filter(['search'=>'Resultados de búsqueda'] + array_intersect_key($archive, array_flip(['taxonomy/product_brand','taxonomy/product_cat','taxonomy/product_tag','post_type_archive/product'])))]];
                break;
            default: $groups = [''=>'Elegir…','archive'=>['label'=>'Archivo','options'=>$archive],'single'=>['label'=>'Individual','options'=>$single],'page/404'=>'Página 404'];
        }
        return $groups;
    }

    /** Recent content, terms and authors for preview_id (values are bare IDs, as Pro stores them). */
    public static function preview_objects(): array {
        $options = [];
        foreach (get_posts(['post_type'=>array_keys(Conditions::post_types()),'posts_per_page'=>60,'post_status'=>'publish']) as $post) {
            $options[(string) $post->ID] = get_post_type_object($post->post_type)->labels->singular_name . ': ' . ($post->post_title ?: '#' . $post->ID);
        }
        foreach (get_taxonomies(['public'=>true,'show_ui'=>true], 'objects') as $taxonomy) {
            foreach (get_terms(['taxonomy'=>$taxonomy->name,'hide_empty'=>false,'number'=>40]) ?: [] as $term) {
                if ($term instanceof \WP_Term) { $options += [(string) $term->term_id=>$taxonomy->labels->singular_name . ': ' . $term->name]; }
            }
        }
        foreach (get_users(['capability'=>['edit_posts'],'number'=>40,'fields'=>['ID','display_name']]) as $user) { $options += [(string) $user->ID=>'Autor: ' . $user->display_name]; }
        return $options;
    }

    /**
     * Preview chosen for this template: [type, object id, search term]. Falls back to the legacy
     * obx_preview_id and to the template's own default.
     * @return array{0:string,1:int,2:string}
     */
    public function preview(): array {
        $type = (string) $this->get_settings('preview_type');
        $id = absint($this->get_settings('preview_id'));
        if ($type === '' || $type === 'single/') {
            $legacy = absint($this->get_settings(self::PREVIEW));
            if ($legacy) { $post = get_post($legacy); return ['single/' . ($post ? $post->post_type : 'post'), $legacy, '']; }
            $type = static::PREVIEW_DEFAULT !== '' && static::PREVIEW_DEFAULT !== 'single/' ? static::PREVIEW_DEFAULT : (static::PREVIEW_KIND === 'single' || static::LOCATION === 'single' ? 'single/post' : (static::LOCATION === 'archive' ? 'archive/recent_posts' : ''));
        }
        return [$type, $id, (string) $this->get_settings('preview_search_term')];
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
    public const PREVIEW_DEFAULT = 'single/post';
    public const PREVIEW_GROUPS = 'single';
    public const HAS_PAGE_TEMPLATE = true;
    public static function get_title() { return 'Entrada individual'; }
    public static function get_plural_title() { return 'Entradas individuales'; }
}
final class Single_Page_Document extends Location_Document {
    public const LOCATION = 'single';
    public const TYPE = 'single-page';
    public const PREVIEW_DEFAULT = 'single/page';
    public const PREVIEW_GROUPS = 'single';
    public const HAS_PAGE_TEMPLATE = true;
    public static function get_title() { return 'Página individual'; }
    public static function get_plural_title() { return 'Páginas individuales'; }
}
/** Generic single template from older Elementor Pro versions. */
final class Single_Document extends Location_Document {
    public const LOCATION = 'single';
    public const TYPE = 'single';
    public const PREVIEW_DEFAULT = 'single/post';
    public const PREVIEW_GROUPS = 'single';
    public const HAS_PAGE_TEMPLATE = true;
    public static function get_title() { return 'Individual'; }
    public static function get_plural_title() { return 'Individuales'; }
}
class Archive_Document extends Location_Document {
    public const LOCATION = 'archive';
    public const TYPE = 'archive';
    public const PREVIEW_DEFAULT = 'archive/recent_posts';
    public const PREVIEW_GROUPS = 'archive';
    public const HAS_PAGE_TEMPLATE = true;
    public static function get_title() { return 'Archivo'; }
    public static function get_plural_title() { return 'Archivos'; }
}
final class Search_Results_Document extends Location_Document {
    public const LOCATION = 'archive';
    public const TYPE = 'search-results';
    public const PREVIEW_DEFAULT = 'search';
    public const PREVIEW_GROUPS = 'search';
    public const HAS_PAGE_TEMPLATE = true;
    public static function get_title() { return 'Resultados de búsqueda'; }
    public static function get_plural_title() { return 'Resultados de búsqueda'; }
}
final class Error_Document extends Location_Document {
    public const LOCATION = 'error-404';
    public const TYPE = 'error-404';
    public const PREVIEW_DEFAULT = 'page/404';
    public const PREVIEW_GROUPS = '404';
    public const HAS_PAGE_TEMPLATE = true;
    public static function get_title() { return 'Página 404'; }
    public static function get_plural_title() { return 'Páginas 404'; }
}

/** Template rendered for every post of a Loop Grid / Loop Carousel. */
final class Loop_Item_Document extends Location_Document {
    public const LOCATION = 'loop-item';
    public const TYPE = 'loop-item';
    public const HAS_CONDITIONS = false;
    public const PREVIEW_KIND = 'single';
    public const PREVIEW_DEFAULT = 'single/post';
    public const PREVIEW_GROUPS = 'loop';
    public static function get_title() { return 'Elemento de bucle'; }
    public static function get_plural_title() { return 'Elementos de bucle'; }
    public function get_container_attributes() {
        $attributes = parent::get_container_attributes();
        $attributes['class'] .= ' e-loop-item-template';
        return $attributes;
    }

    /** What the card loops over (Pro's «source»: post, product, post_taxonomy or product_taxonomy). */
    public function source(): string {
        $source = (string) $this->get_settings('source');
        return in_array($source, ['post','product','post_taxonomy','product_taxonomy'], true) ? $source : 'post';
    }

    protected function preview_controls(): void {
        $sources = ['post'=>'Entradas','post_taxonomy'=>'Taxonomía de entradas'];
        if (post_type_exists('product')) { $sources += ['product'=>'Productos','product_taxonomy'=>'Taxonomía de productos']; }
        $this->start_controls_section('preview_settings', ['label'=>'Elemento de bucle','tab'=>Controls_Manager::TAB_SETTINGS]);
        $this->add_control('source', ['label'=>'Origen','type'=>Controls_Manager::SELECT,'default'=>'post','options'=>$sources,'description'=>'Qué recorre la tarjeta: contenidos o términos.']);
        $this->add_control('apply_query_source', ['type'=>Controls_Manager::BUTTON,'label'=>'Aplicar origen','text'=>'Guardar y previsualizar','separator'=>'none','event'=>'obx:document:applyPreview']);
        $this->add_control('preview_type', ['label'=>'Previsualizar como','type'=>Controls_Manager::SELECT,'default'=>static::PREVIEW_DEFAULT,'groups'=>self::preview_groups('loop'),'label_block'=>true,'export'=>false,'separator'=>'before','condition'=>['source'=>['post','product']]]);
        $this->add_control('preview_id', ['label'=>'Contenido','type'=>Controls_Manager::SELECT2,'options'=>self::loop_preview_objects(),'label_block'=>true,'export'=>false,'condition'=>['source'=>['post','product']],'description'=>'Vacío: el más reciente.']);
        $this->add_control('preview_search_term', ['label'=>'Buscar','type'=>Controls_Manager::TEXT,'label_block'=>true,'export'=>false,'condition'=>['preview_type'=>'search']]);
        $this->add_responsive_control('preview_width', ['label'=>'Ancho de la vista previa','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vw','custom'],'range'=>['px'=>['min'=>200,'max'=>1140],'em'=>['min'=>20,'max'=>100],'rem'=>['min'=>20,'max'=>100]],'selectors'=>['body.elementor-editor-active {{WRAPPER}}'=>'width: {{SIZE}}{{UNIT}}; margin-inline: auto;'],'description'=>'Solo en el editor.']);
        $this->add_control('apply_preview', ['type'=>Controls_Manager::BUTTON,'label'=>'Aplicar','text'=>'Guardar y previsualizar','separator'=>'none','event'=>'obx:document:applyPreview']);
        $this->end_controls_section();
    }

    private static function loop_preview_objects(): array {
        $options = [];
        foreach (get_posts(['post_type'=>array_values(array_filter(['post','page', post_type_exists('product') ? 'product' : ''])),'posts_per_page'=>60,'post_status'=>'publish']) as $post) {
            $options[(string) $post->ID] = get_post_type_object($post->post_type)->labels->singular_name . ': ' . ($post->post_title ?: '#' . $post->ID);
        }
        return $options;
    }
}

final class Product_Document extends Single_Post_Document {
    public const TYPE = 'product';
    public const PREVIEW_DEFAULT = 'single/product';
    public static function get_title() { return 'Producto individual'; }
    public static function get_plural_title() { return 'Productos individuales'; }
}

final class Product_Archive_Document extends Archive_Document {
    public const TYPE = 'product-archive';
    public const PREVIEW_DEFAULT = 'post_type_archive/product';
    public const PREVIEW_GROUPS = 'product-archive';
    public static function get_title() { return 'Archivo de productos'; }
    public static function get_plural_title() { return 'Archivos de productos'; }
}

/**
 * Elementor Pro-compatible popup document; Popups handles display outside theme locations.
 * Layout, style and advanced settings use Pro's keys; triggers and timing go to the
 * `_elementor_popup_display_settings` meta, as Pro stores them.
 */
final class Popup_Document extends Location_Document {
    public const LOCATION = 'popup';
    public const TYPE = 'popup';
    public static function get_title() { return 'Ventana emergente'; }
    public static function get_plural_title() { return 'Ventanas emergentes'; }

    /** Same wrapper as Pro, so «{{WRAPPER}}» rules and custom CSS target the whole popup. */
    public function get_css_wrapper_selector() { return '#elementor-popup-modal-' . $this->get_main_id(); }

    /** In the editor preview the popup is shown open, inside the same modal markup the frontend prints. */
    public function print_elements_with_wrapper($elements_data = null) {
        $id = (int) $this->get_main_id();
        if (!\Elementor\Plugin::$instance->preview->is_preview_mode($id)) { parent::print_elements_with_wrapper($elements_data); return; }
        $s = $this->get_settings_for_display();
        $classes = 'obx-popup elementor-popup-modal is-open obx-popup--editor' . (($s['close_button_position'] ?? '') === 'outside' ? ' obx-popup--close-outside' : '');
        echo '<div id="elementor-popup-modal-' . esc_attr((string) $id) . '" class="' . esc_attr($classes) . '">';
        if (($s['overlay'] ?? 'yes') === 'yes') { echo '<div class="obx-popup__overlay"></div>'; }
        echo '<div class="obx-popup__dialog">';
        if (($s['close_button'] ?? 'yes') === 'yes') { echo '<span class="obx-popup__close" aria-hidden="true">&times;</span>'; }
        echo '<div class="obx-popup__content">';
        parent::print_elements_with_wrapper($elements_data);
        echo '</div></div></div>';
    }

    protected function register_controls() {
        parent::register_controls();
        $h = ['left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-h-align-center'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right']];
        $v = ['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'center'=>['title'=>'Centro','icon'=>'eicon-v-align-middle'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']];
        $dialog = '{{WRAPPER}} .obx-popup__dialog';

        $this->start_controls_section('popup_layout', ['label'=>'Diseño de la ventana','tab'=>Controls_Manager::TAB_SETTINGS]);
        $this->add_control('assets_loading_hidden_control', ['type'=>Controls_Manager::HIDDEN,'default'=>'hidden']);
        $this->add_responsive_control('width', ['label'=>'Ancho','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vw','custom'],'range'=>['px'=>['min'=>100,'max'=>1600],'vw'=>['min'=>10,'max'=>100]],'default'=>['unit'=>'px','size'=>640],'selectors'=>[$dialog=>'width: {{SIZE}}{{UNIT}};']]);
        $this->add_control('height_type', ['label'=>'Altura','type'=>Controls_Manager::SELECT,'default'=>'auto','options'=>['auto'=>'Ajustar al contenido','fit_to_screen'=>'Ajustar a pantalla','custom'=>'Personalizada'],'selectors_dictionary'=>['fit_to_screen'=>'100vh','auto'=>'auto','custom'=>''],'selectors'=>[$dialog=>'height: {{VALUE}};']]);
        $this->add_responsive_control('height', ['label'=>'Altura personalizada','type'=>Controls_Manager::SLIDER,'size_units'=>['px','vh','custom'],'range'=>['px'=>['min'=>100,'max'=>1600],'vh'=>['min'=>10,'max'=>100]],'default'=>['unit'=>'px','size'=>380],'condition'=>['height_type'=>'custom'],'selectors'=>[$dialog=>'height: {{SIZE}}{{UNIT}};']]);
        $this->add_control('content_position', ['label'=>'Posición del contenido','type'=>Controls_Manager::SELECT,'default'=>'top','options'=>['top'=>'Arriba','center'=>'Centro','bottom'=>'Abajo'],'condition'=>['height_type!'=>'auto'],'selectors_dictionary'=>['top'=>'flex-start','center'=>'center','bottom'=>'flex-end'],'selectors'=>[$dialog=>'justify-content: {{VALUE}};']]);
        $this->add_responsive_control('horizontal_position', ['label'=>'Posición horizontal','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>$h,'toggle'=>false,'selectors_dictionary'=>['left'=>'flex-start','center'=>'center','right'=>'flex-end'],'selectors'=>['{{WRAPPER}}'=>'justify-content: {{VALUE}};']]);
        $this->add_responsive_control('vertical_position', ['label'=>'Posición vertical','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>$v,'toggle'=>false,'selectors_dictionary'=>['top'=>'flex-start','center'=>'center','bottom'=>'flex-end'],'selectors'=>['{{WRAPPER}}'=>'align-items: {{VALUE}};']]);
        $this->add_control('overlay', ['label'=>'Superposición','type'=>Controls_Manager::SWITCHER,'default'=>'yes','label_off'=>'No','label_on'=>'Sí','separator'=>'before']);
        $this->add_control('close_button', ['label'=>'Botón de cierre','type'=>Controls_Manager::SWITCHER,'default'=>'yes','label_off'=>'No','label_on'=>'Sí']);
        $this->add_responsive_control('entrance_animation', ['label'=>'Animación de entrada','type'=>Controls_Manager::ANIMATION,'frontend_available'=>true,'separator'=>'before']);
        $this->add_responsive_control('exit_animation', ['label'=>'Animación de salida','type'=>Controls_Manager::EXIT_ANIMATION,'frontend_available'=>true]);
        $this->add_control('entrance_animation_duration', ['label'=>'Duración de la animación (s)','type'=>Controls_Manager::SLIDER,'default'=>['size'=>1.2],'range'=>['px'=>['min'=>0.1,'max'=>5,'step'=>0.1]],'selectors'=>[$dialog=>'animation-duration: {{SIZE}}s;'],'conditions'=>['relation'=>'or','terms'=>[['name'=>'entrance_animation','operator'=>'!==','value'=>''],['name'=>'exit_animation','operator'=>'!==','value'=>'']]]]);
        $this->end_controls_section();

        $this->start_controls_section('obx_popup_triggers', ['label'=>'Disparadores','tab'=>Controls_Manager::TAB_SETTINGS]);
        $this->add_control('obx_page_load', ['label'=>'Al cargar la página','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('obx_page_load_delay', ['label'=>'Retraso (segundos)','type'=>Controls_Manager::NUMBER,'default'=>0,'min'=>0,'condition'=>['obx_page_load'=>'yes']]);
        $this->add_control('obx_scroll', ['label'=>'Al desplazarse','type'=>Controls_Manager::SWITCHER]);
        $this->add_control('obx_scroll_percent', ['label'=>'Porcentaje','type'=>Controls_Manager::NUMBER,'default'=>50,'min'=>1,'max'=>100,'condition'=>['obx_scroll'=>'yes']]);
        $this->add_control('obx_exit_intent', ['label'=>'Al intentar salir','type'=>Controls_Manager::SWITCHER]);
        $this->add_control('obx_inactivity', ['label'=>'Tras inactividad','type'=>Controls_Manager::SWITCHER]);
        $this->add_control('obx_inactivity_time', ['label'=>'Segundos de inactividad','type'=>Controls_Manager::NUMBER,'default'=>30,'min'=>1,'condition'=>['obx_inactivity'=>'yes']]);
        $this->add_control('obx_show_times', ['label'=>'Mostrar como máximo','type'=>Controls_Manager::NUMBER,'min'=>0,'separator'=>'before','description'=>'Número de veces por visitante; 0 no limita.']);
        $this->end_controls_section();
    }

    /** Pro's «section_page_style», «section_overlay» and «section_close_button» (style tab) and «section_advanced». */
    protected function style_controls(): void {
        $dialog = '{{WRAPPER}} .obx-popup__dialog';
        $this->start_controls_section('section_page_style', ['label'=>'Ventana','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_group_control(\Elementor\Group_Control_Background::get_type(), ['name'=>'background','types'=>['classic','gradient'],'selector'=>$dialog]);
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name'=>'border','selector'=>$dialog]);
        $this->add_responsive_control('border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'selectors'=>[$dialog=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), ['name'=>'box_shadow','selector'=>$dialog,'fields_options'=>['box_shadow_type'=>['default'=>'yes'],'box_shadow'=>['default'=>['horizontal'=>2,'vertical'=>8,'blur'=>23,'spread'=>3,'color'=>'rgba(0,0,0,0.2)']]]]);
        $this->end_controls_section();

        $this->start_controls_section('section_overlay', ['label'=>'Superposición','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['overlay'=>'yes']]);
        $this->add_group_control(\Elementor\Group_Control_Background::get_type(), ['name'=>'overlay_background','types'=>['classic','gradient'],'selector'=>'{{WRAPPER}} .obx-popup__overlay','fields_options'=>['background'=>['default'=>'classic'],'color'=>['default'=>'rgba(0,0,0,.8)']]]);
        $this->end_controls_section();

        $close = '{{WRAPPER}} .obx-popup__close';
        $this->start_controls_section('section_close_button', ['label'=>'Botón de cierre','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['close_button!'=>'']]);
        $this->add_control('close_button_position', ['label'=>'Posición','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Dentro','outside'=>'Fuera'],'frontend_available'=>true]);
        $this->add_responsive_control('close_button_vertical', ['label'=>'Posición vertical','type'=>Controls_Manager::SLIDER,'size_units'=>['%','px','em','rem','custom'],'range'=>['%'=>['max'=>100,'min'=>0,'step'=>0.1],'px'=>['max'=>500,'min'=>-500]],'default'=>['unit'=>'%'],'selectors'=>[$close=>'inset-block-start: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('close_button_horizontal', ['label'=>'Posición horizontal','type'=>Controls_Manager::SLIDER,'size_units'=>['%','px','em','rem','custom'],'range'=>['%'=>['max'=>100,'min'=>0,'step'=>0.1],'px'=>['max'=>500,'min'=>-500]],'default'=>['unit'=>'%'],'selectors'=>[$close=>'inset-inline-end: {{SIZE}}{{UNIT}};']]);
        $this->start_controls_tabs('close_button_style_tabs');
        $this->start_controls_tab('tab_x_button_normal', ['label'=>'Normal']);
        $this->add_control('close_button_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>[$close=>'color: {{VALUE}};']]);
        $this->add_control('close_button_background_color', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>[$close=>'background-color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->start_controls_tab('tab_x_button_hover', ['label'=>'Al pasar el ratón']);
        $this->add_control('close_button_hover_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>[$close . ':hover'=>'color: {{VALUE}};']]);
        $this->add_control('close_button_hover_background_color', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>[$close . ':hover'=>'background-color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_responsive_control('icon_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>100]],'separator'=>'before','selectors'=>[$close=>'font-size: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('section_advanced', ['label'=>'Avanzado','tab'=>Controls_Manager::TAB_ADVANCED]);
        $this->add_control('close_button_delay', ['label'=>'Mostrar el botón de cierre tras (s)','type'=>Controls_Manager::NUMBER,'min'=>0.1,'max'=>60,'step'=>0.1,'condition'=>['close_button'=>'yes'],'frontend_available'=>true]);
        $this->add_control('close_automatically', ['label'=>'Cerrar automáticamente tras (s)','type'=>Controls_Manager::NUMBER,'min'=>0.1,'max'=>60,'step'=>0.1,'frontend_available'=>true]);
        $this->add_control('prevent_close_on_background_click', ['label'=>'No cerrar al pulsar fuera','type'=>Controls_Manager::SWITCHER,'frontend_available'=>true]);
        $this->add_control('prevent_close_on_esc_key', ['label'=>'No cerrar con Escape','type'=>Controls_Manager::SWITCHER,'frontend_available'=>true]);
        $this->add_control('prevent_scroll', ['label'=>'Bloquear el desplazamiento de la página','type'=>Controls_Manager::SWITCHER,'frontend_available'=>true]);
        $this->add_control('avoid_multiple_popups', ['label'=>'Evitar varias ventanas a la vez','type'=>Controls_Manager::SWITCHER,'description'=>'Si ya hay una ventana abierta, esta no se abre por sus disparadores.','frontend_available'=>true]);
        $this->add_control('a11y_navigation', ['label'=>'Navegación accesible','type'=>Controls_Manager::SWITCHER,'default'=>'yes','description'=>'Lleva el foco al primer elemento enfocable y lo mantiene dentro de la ventana.','frontend_available'=>true]);
        $this->add_control('open_selector', ['label'=>'Abrir al hacer clic en','type'=>Controls_Manager::TEXT,'placeholder'=>'.abrir-popup, #boton','description'=>'Selector CSS. También funciona el enlace dinámico «Ventana emergente».','frontend_available'=>true,'separator'=>'before']);
        $this->add_responsive_control('margin', ['label'=>'Margen','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'selectors'=>[$dialog=>'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],'separator'=>'before']);
        $this->add_responsive_control('padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'selectors'=>[$dialog . ' > .obx-popup__content'=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_control('classes', ['label'=>'Clases CSS','type'=>Controls_Manager::TEXT,'title'=>'Clases separadas por espacios, sin punto.','classes'=>'elementor-control-direction-ltr']);
        $this->end_controls_section();
    }
}

/**
 * Saved section and floating-buttons templates: Pro adds the wrapper tag and the preview settings
 * to these Elementor documents too. Controls go in through the documents hook; the section document
 * is replaced so the wrapper tag is printed.
 */
final class Saved_Documents {
    public const TYPES = ['section','floating-buttons'];

    public static function boot(): void {
        add_action('elementor/documents/register_controls', [self::class, 'controls']);
        add_action('elementor/documents/register', [self::class, 'register'], 20);
    }

    public static function register($manager): void {
        if (class_exists(\Elementor\Modules\Library\Documents\Section::class)) { $manager->register_document_type('section', Section_Document::class); }
    }

    public static function controls($document): void {
        if (!in_array($document->get_name(), self::TYPES, true) || $document->get_controls('preview_type')) { return; }
        $document->start_injection(['of'=>'post_status','fallback'=>['of'=>'post_title']]);
        $document->add_control('content_wrapper_html_tag', ['label'=>'Etiqueta HTML','type'=>Controls_Manager::SELECT,'default'=>'div','options'=>Location_Document::WRAPPER_TAGS]);
        $document->end_injection();
        $document->start_controls_section('preview_settings', ['label'=>'Vista previa','tab'=>Controls_Manager::TAB_SETTINGS]);
        $document->add_control('preview_type', ['label'=>'Previsualizar como','type'=>Controls_Manager::SELECT,'default'=>'','groups'=>Location_Document::preview_groups('any'),'label_block'=>true,'export'=>false]);
        $document->add_control('preview_id', ['label'=>'Contenido','type'=>Controls_Manager::SELECT2,'options'=>Location_Document::preview_objects(),'label_block'=>true,'export'=>false,'condition'=>['preview_type!'=>['','search','page/404','archive/recent_posts','archive/date']],'description'=>'Vacío: el más reciente.']);
        $document->add_control('preview_search_term', ['label'=>'Buscar','type'=>Controls_Manager::TEXT,'label_block'=>true,'export'=>false,'condition'=>['preview_type'=>'search']]);
        $document->add_control('apply_preview', ['type'=>Controls_Manager::BUTTON,'label'=>'Aplicar','text'=>'Guardar y previsualizar','separator'=>'none','event'=>'obx:document:applyPreview']);
        $document->end_controls_section();
    }
}

if (class_exists(\Elementor\Modules\Library\Documents\Section::class)) {
    final class Section_Document extends \Elementor\Modules\Library\Documents\Section {
        public function print_elements_with_wrapper($elements_data = null) {
            if (!$elements_data) { $elements_data = $this->get_elements_data(); }
            $tag = (string) $this->get_settings('content_wrapper_html_tag');
            $tag = isset(Location_Document::WRAPPER_TAGS[$tag]) ? $tag : 'div';
            echo '<' . $tag . ' ' . \Elementor\Utils::render_html_attributes($this->get_container_attributes()) . '>';
            $this->print_elements($elements_data);
            echo '</' . $tag . '>';
        }
    }
}
