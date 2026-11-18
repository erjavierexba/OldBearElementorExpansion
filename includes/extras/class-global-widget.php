<?php
namespace OldBearExpansion\Extras;

use Elementor\Plugin as Elementor;
use Elementor\TemplateLibrary\Source_Local;
use OldBearExpansion\Plugin;
use const OldBearExpansion\VERSION;

if (!defined('ABSPATH')) { exit; }

/**
 * Global widgets stored as Elementor Pro stores them, so sites move both ways:
 * - the widget is a library template of type `widget` whose `_elementor_data` holds that one widget,
 *   with its type in `_elementor_template_widget_type`;
 * - pages keep `{"widgetType":"global","templateID":N}` (plus `draft` while only saved as a draft) and
 *   always render the template's current settings;
 * - each template lists the posts using it in `_elementor_global_widget_included_posts`, whose CSS is
 *   regenerated when the template changes.
 */
final class Global_Widgets {
    public const TYPE = 'widget';
    public const WIDGET_TYPE_META = '_elementor_template_widget_type';
    public const INCLUDED_POSTS_META = '_elementor_global_widget_included_posts';

    /** @var array<int, array|null> template id => its widget element */
    private static array $elements = [];

    public static function boot(): void {
        add_action('elementor/documents/register', [self::class, 'register_document']);
        add_action('elementor/widgets/register', [self::class, 'register_widget']);
        add_action('elementor/template-library/after_save_template', [self::class, 'remember_widget_type'], 10, 2);
        add_action('elementor/template-library/after_update_template', [self::class, 'template_updated'], 10, 2);
        add_action('elementor/editor/after_save', [self::class, 'remember_included_post'], 10, 2);
        add_filter('elementor/template-library/get_template', [self::class, 'template_data']);
        add_filter('elementor/element/get_child_type', [self::class, 'child_type'], 10, 2);
        add_filter('elementor/utils/is_post_support', [self::class, 'post_support'], 10, 3);
        add_filter('elementor/template_library/is_template_supports_export', [self::class, 'supports_export'], 10, 2);
        add_filter('elementor/document/save/data', [self::class, 'save_data'], 10, 2);
        add_action('elementor/editor/after_enqueue_scripts', [self::class, 'editor_assets']);
        add_action('elementor/editor/after_enqueue_styles', [self::class, 'editor_styles']);
        add_action('elementor/editor/footer', [self::class, 'editor_templates']);
    }

    public static function register_document($manager): void {
        require_once __DIR__ . '/class-global-widget-document.php';
        $manager->register_document_type(self::TYPE, Global_Widget_Document::class);
    }

    public static function register_widget($widgets): void {
        require_once __DIR__ . '/class-global-widget-element.php';
        $widgets->register(new Global_Widget());
    }

    public static function is_widget_template($id): bool {
        return self::TYPE === Source_Local::get_template_type((int) $id);
    }

    /** The widget element saved in a global widget template, or null when it is missing or trashed. */
    public static function element(int $id): ?array {
        if (!array_key_exists($id, self::$elements)) {
            $element = null;
            $post = $id ? get_post($id) : null;
            if ($post && 'trash' !== $post->post_status && self::is_widget_template($id)) {
                $document = Elementor::$instance->documents->get($id);
                $data = $document ? $document->get_elements_data() : [];
                if (!empty($data[0]['widgetType'])) { $element = $data[0]; }
            }
            self::$elements[$id] = $element;
        }
        return self::$elements[$id];
    }

    public static function forget(int $id): void { unset(self::$elements[$id]); }

    public static function remember_widget_type($post_id, $data): void {
        if (self::TYPE === ($data['type'] ?? '') && !empty($data['content'][0]['widgetType'])) {
            update_post_meta((int) $post_id, self::WIDGET_TYPE_META, (string) $data['content'][0]['widgetType']);
        }
    }

    /** A changed template regenerates the CSS of every post using it. */
    public static function template_updated($template_id, $data): void {
        if (self::TYPE !== ($data['type'] ?? Source_Local::get_template_type((int) $template_id))) { return; }
        self::forget((int) $template_id);
        $posts = get_post_meta((int) $template_id, self::INCLUDED_POSTS_META, true);
        foreach (array_keys(is_array($posts) ? $posts : []) as $post_id) { delete_post_meta((int) $post_id, '_elementor_css'); delete_post_meta((int) $post_id, '_elementor_element_cache'); }
    }

    public static function remember_included_post($post_id, $data): void {
        $ids = [];
        Elementor::$instance->db->iterate_data((array) $data, static function($element) use (&$ids) {
            if (!empty($element['templateID'])) { $ids[(int) $element['templateID']] = true; }
            return $element;
        });
        foreach (array_keys($ids) as $id) {
            $posts = get_post_meta($id, self::INCLUDED_POSTS_META, true);
            $posts = is_array($posts) ? $posts : [];
            $posts[(int) $post_id] = true;
            update_post_meta($id, self::INCLUDED_POSTS_META, $posts);
        }
    }

    public static function template_data($data) {
        if (is_array($data) && self::TYPE === ($data['type'] ?? '') && !empty($data['template_id'])) {
            $data['widgetType'] = get_post_meta((int) $data['template_id'], self::WIDGET_TYPE_META, true);
        }
        return $data;
    }

    /** A global widget whose template was deleted or trashed is not printed. */
    public static function child_type($type, $data) {
        if (!empty($data['templateID']) && !self::element((int) $data['templateID'])) { return false; }
        return $type;
    }

    /** Global widget templates are edited from the pages using them, not from the library screens. */
    public static function post_support($supported, $post_id, $post_type) {
        if (!$supported || Source_Local::CPT !== $post_type || !self::is_widget_template($post_id) || !function_exists('get_current_screen')) { return $supported; }
        $screen = get_current_screen();
        return !($screen && in_array($screen->id, ['elementor_library','edit-elementor_library'], true));
    }

    public static function supports_export($supported, $template_id) {
        return $supported && !self::is_widget_template($template_id);
    }

    /** Elements linked to a template are saved as Pro saves them: `global`, with the original type alongside. */
    public static function save_data($data, $document) {
        if (empty($data['elements']) || $document instanceof Global_Widget_Document) { return $data; }
        $data['elements'] = Elementor::$instance->db->iterate_data($data['elements'], static function($element) {
            if (!empty($element['templateID'])) {
                $type = (string) ($element['widgetType'] ?? '');
                if ('global' === $type) { $type = (string) ($element['originalWidgetType'] ?? (self::element((int) $element['templateID'])['widgetType'] ?? 'global')); }
                $element['originalWidgetType'] = $type;
                $element['widgetType'] = 'global';
            }
            return $element;
        });
        return $data;
    }

    /** @return array<int, array> template id => title, widget type and saved settings */
    public static function templates(): array {
        // Elementor removes `edit_post` from non-editable documents such as these, so the editor's own check applies.
        if (!current_user_can('edit_posts')) { return []; }
        $types = Elementor::$instance->widgets_manager->get_widget_types();
        $posts = get_posts(['post_type'=>Source_Local::CPT,'post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC','meta_key'=>Source_Local::TYPE_META_KEY,'meta_value'=>self::TYPE]);
        $templates = [];
        foreach ($posts as $post) {
            $element = self::element((int) $post->ID);
            if (!$element || empty($types[$element['widgetType']])) { continue; }
            $templates[(int) $post->ID] = ['title'=>get_the_title($post),'widgetType'=>(string) $element['widgetType'],'settings'=>(object) ($element['settings'] ?? [])];
        }
        return $templates;
    }

    public static function editor_assets(): void {
        wp_enqueue_script('obx-editor-global-widget', Plugin::url('assets/js/editor-global-widget.js'), ['jquery'], VERSION, true);
        wp_add_inline_script('obx-editor-global-widget', 'window.obxGlobalWidgets = ' . wp_json_encode(['templates'=>(object) self::templates()]) . ';', 'before');
    }

    public static function editor_styles(): void {
        wp_register_style('obx-editor-global-widget', false, [], VERSION);
        wp_enqueue_style('obx-editor-global-widget');
        wp_add_inline_style('obx-editor-global-widget', self::editor_css());
    }

    private static function editor_css(): string {
        return '#elementor-panel-global-widget{padding:20px 15px}'
            . '#elementor-global-widget-locked-header,#elementor-panel-global-widget-no-templates{display:block;text-align:center}'
            . '#elementor-global-widget-locked-tools{margin-top:20px}'
            . '.elementor-global-widget-locked-tool{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 0;border-top:1px solid var(--e-a-border-color,#e6e8ea);font-size:12px}'
            . '#elementor-global-widget-loading{text-align:center;padding:20px;font-size:20px}'
            . '.elementor-editor-active .elementor-global-widget>.elementor-element-overlay .elementor-editor-element-setting{background-color:#b4b5b7}';
    }

    public static function editor_templates(): void {
        $icon = esc_url(ELEMENTOR_ASSETS_URL . 'images/information.svg');
        ?>
        <script type="text/template" id="tmpl-elementor-panel-global-widget">
            <div id="elementor-global-widget-locked-header" class="elementor-nerd-box elementor-panel-nerd-box">
                <img class="elementor-nerd-box-icon" src="<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>" alt="" loading="lazy" />
                <div class="elementor-nerd-box-title elementor-panel-nerd-box-title">Tu widget global está bloqueado</div>
                <div class="elementor-nerd-box-message elementor-panel-nerd-box-message">Edita este widget global para cambiarlo a la vez en todos los sitios donde lo usas, o desvincúlalo para que vuelva a ser un widget normal.</div>
            </div>
            <div id="elementor-global-widget-locked-tools">
                <div id="elementor-global-widget-locked-edit" class="elementor-global-widget-locked-tool">
                    <div class="elementor-global-widget-locked-tool-description">Editar el widget global</div>
                    <button type="button" class="elementor-button">Editar</button>
                </div>
                <div id="elementor-global-widget-locked-unlink" class="elementor-global-widget-locked-tool">
                    <div class="elementor-global-widget-locked-tool-description">Desvincular del global</div>
                    <button type="button" class="elementor-button elementor-button-danger">Desvincular</button>
                </div>
            </div>
        </script>
        <script type="text/template" id="tmpl-elementor-panel-global-widget-no-templates">
            <img src="<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>" alt="" loading="lazy" />
            <div class="elementor-nerd-box-title elementor-panel-nerd-box-title">Guarda tu primer widget global</div>
            <div class="elementor-nerd-box-message elementor-panel-nerd-box-message">Guarda un widget como global (clic derecho → «Guardar como global») y añádelo en varios sitios. Todos se editan desde un único lugar.</div>
        </script>
        <?php
    }
}
