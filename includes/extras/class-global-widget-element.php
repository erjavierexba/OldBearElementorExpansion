<?php
namespace OldBearExpansion\Extras;

use Elementor\Core\Base\Document;
use Elementor\Plugin as Elementor;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) { exit; }

/**
 * Widget `global`: an instance of a global widget template. It renders the template's widget with the
 * template's current settings; in the editor it is loaded as that widget plus `templateID`.
 */
final class Global_Widget extends Widget_Base {
    /** @var array|null the template's widget element */
    private ?array $template = null;
    /** Registered type of the original widget. */
    private ?Widget_Base $original_type = null;
    private ?Widget_Base $original = null;
    /** The element's data as received (get_data() would sanitize through get_controls(), which needs the original). */
    private array $source = [];

    public function __construct($data = [], $args = null) {
        if ($data) { $data = $this->resolve((array) $data); $this->source = $data; }
        parent::__construct($data, $args);
    }

    private function resolve(array $data): array {
        $widgets = Elementor::$instance->widgets_manager;
        $template = !empty($data['templateID']) ? Global_Widgets::element((int) $data['templateID']) : null;
        $type = $template ? $widgets->get_widget_types((string) $template['widgetType']) : null;
        if ($template && $type) {
            $this->template = $template;
            $this->original_type = $type;
            if (empty($data['draft'])) {
                // Loaded from the database: it becomes the original widget. Coming from the editor's save it stays `global`.
                if (empty($data['originalWidgetType'])) { $data['widgetType'] = (string) $template['widgetType']; }
                if (!self::saving_draft()) { $data['settings'] = (array) ($template['settings'] ?? []); }
            }
            return $data;
        }
        $name = (string) ($data['originalWidgetType'] ?? '');
        if ($name === '' && !empty($data['widgetType']) && 'global' !== $data['widgetType']) { $name = (string) $data['widgetType']; }
        if ($name !== '' && 'global' !== $name && ($type = $widgets->get_widget_types($name))) { $this->original_type = $type; }
        return $data;
    }

    public function get_name() { return 'global'; }

    public function get_title() { return 'Global'; }

    public function get_icon() { return 'eicon-global-settings'; }

    public function show_in_panel() { return false; }

    public function has_widget_inner_wrapper(): bool { return !Elementor::$instance->experiments->is_feature_active('e_optimized_markup'); }

    private function resolvable(): bool { return !$this->is_type_instance() && $this->original_type; }

    /** The original widget, built from the template (or from the draft kept in the page). */
    public function get_original_element_instance(): ?Widget_Base {
        if (!$this->resolvable()) { return null; }
        if (!$this->original) {
            if (!empty($this->source['draft'])) {
                $content = $this->source;
                if ($this->template) { $content['widgetType'] = $this->template['widgetType']; }
            } else {
                $content = $this->template ?: $this->source;
            }
            if (($content['widgetType'] ?? 'global') === 'global') { $content['widgetType'] = $this->original_type->get_name(); }
            $content['id'] = $this->get_id();
            $class = $this->original_type->get_class_name();
            $this->original = new $class($content, $this->original_type->get_default_args());
        }
        return $this->original;
    }

    public function get_controls($control_id = null) {
        $original = $this->get_original_element_instance();
        return $original ? $original->get_controls($control_id) : ($control_id ? null : []);
    }

    public function get_script_depends() {
        $original = $this->get_original_element_instance();
        return $original ? $original->get_script_depends() : [];
    }

    public function get_style_depends() {
        $original = $this->get_original_element_instance();
        return $original ? $original->get_style_depends() : [];
    }

    public function get_unique_selector() { return '.elementor-global-' . $this->get_data('templateID'); }

    public function get_raw_data($with_html_content = false) {
        $raw = parent::get_raw_data($with_html_content);
        $raw['templateID'] = $this->get_data('templateID');
        if (self::saving_draft()) {
            // A draft keeps the page's own copy; the template changes when the page is published.
            $raw['draft'] = true;
            return $raw;
        }
        if ($this->get_data('draft')) {
            $raw['widgetType'] = $this->template ? $this->template['widgetType'] : ($this->get_data('originalWidgetType') ?: $this->get_name());
            return $raw;
        }
        // Elementor's element cache rebuilds the element from this data: it must come back as `global`.
        if (apply_filters('elementor/element/should_render_shortcode', false)) { $raw['widgetType'] = $this->get_name(); }
        return $raw;
    }

    public function render_content() {
        $original = $this->get_original_element_instance();
        if ($original) { $original->render_content(); }
    }

    public function render_plain_content() {
        $original = $this->get_original_element_instance();
        if ($original) { $original->render_plain_content(); }
    }

    public function on_export() {
        return $this->template ?: $this->source;
    }

    protected function add_render_attributes() {
        parent::add_render_attributes();
        $original = $this->get_original_element_instance();
        if (!$original) { return; }
        $type = (string) $original->get_data('widgetType');
        $skin = (string) $this->get_settings('_skin');
        $this->set_render_attribute('_wrapper', 'data-widget_type', $type . '.' . ($skin !== '' ? $skin : 'default'));
        $this->add_render_attribute('_wrapper', 'class', ['elementor-global-' . $this->get_data('templateID'), 'elementor-widget-' . $type]);
    }

    private static function saving_draft(): bool {
        $common = Elementor::$instance->common;
        if (!$common) { return false; }
        $ajax = $common->get_component('ajax');
        $action = $ajax ? $ajax->get_current_action_data() : null;
        return $action && 'save_builder' === ($action['action'] ?? '') && in_array($action['data']['status'] ?? '', [Document::STATUS_DRAFT, Document::STATUS_AUTOSAVE], true);
    }
}
