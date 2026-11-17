<?php
namespace OldBearExpansion\Clean;

use Elementor\Plugin as Elementor;

if (!defined('ABSPATH')) { exit; }

/**
 * "Modo limpio": removes Elementor's upsells for features this plugin provides or plans,
 * without touching any free functionality. Enabled by default; Settings → OldBear Expansion.
 */
final class Clean_Mode {
    public const OPTION = 'obx_clean_mode';

    /** Upsell-only controls Elementor Free adds to every element and document. */
    private const PRO_CONTROLS = ['display_conditions_pro','scrolling_effects_pro','mouse_effects_pro','sticky_pro','custom_css_pro','custom_attributes_pro'];

    public static function enabled(): bool {
        return (bool) apply_filters('obx/clean_mode', (bool) get_option(self::OPTION, 1));
    }

    public static function boot(): void {
        add_action('admin_init', [self::class, 'register_setting']);
        add_action('admin_menu', [self::class, 'settings_page']);
        if (!self::enabled()) { return; }
        // Elementor builds its modules on init:0. Unhook its promotions module right after.
        add_action('init', [self::class, 'unhook_promotions'], 1);
        add_filter('elementor/editor/localize_settings', [self::class, 'editor_config'], 999);
        add_filter('elementor/editor-one/menu/theme_builder_url', [self::class, 'theme_builder_url']);
        add_action('elementor/element/after_section_end', [self::class, 'strip_controls'], 999, 2);
        add_filter('pre_option__elementor_editor_upgrade_notice_dismissed', static fn() => time());
        add_filter('plugin_action_links_' . (defined('ELEMENTOR_PLUGIN_BASE') ? ELEMENTOR_PLUGIN_BASE : 'elementor/elementor.php'), [self::class, 'plugin_links'], 999);
        add_action('elementor/editor/after_enqueue_styles', [self::class, 'editor_styles']);
        add_action('elementor/preview/enqueue_styles', [self::class, 'editor_styles']);
        add_action('admin_enqueue_scripts', [self::class, 'admin_styles']);
        add_action('admin_enqueue_scripts', [self::class, 'sidebar_cta'], PHP_INT_MAX);
        add_action('admin_head', [self::class, 'remove_upgrade_menu']);
        if (defined('ELEMENTOR_VERSION')) {
            add_filter('transient_elementor_remote_info_api_data_' . ELEMENTOR_VERSION, [self::class, 'remote_info']);
        }
    }

    /** Elementor appends locked "Pro" widgets from its remote info after the editor config filter. */
    public static function remote_info($data) {
        if (is_array($data)) { $data['pro_widgets'] = []; }
        return $data;
    }

    /** The admin sidebar (React) only uses hasPro to decide whether to show its upgrade button. */
    public static function sidebar_cta(): void {
        if (wp_script_is('editor-one-sidebar-navigation', 'enqueued')) {
            wp_add_inline_script('editor-one-sidebar-navigation', 'if(window.editorOneSidebarConfig){window.editorOneSidebarConfig.hasPro=true;}', 'before');
        }
    }

    public static function remove_upgrade_menu(): void {
        remove_submenu_page('elementor-home', 'elementor-one-upgrade');
        remove_submenu_page('elementor', 'go_elementor_pro');
    }

    /** Removes every hook registered by Elementor's promotions module instance. */
    public static function unhook_promotions(): int {
        $module = Elementor::$instance->modules_manager->get_modules('promotions');
        if (!$module) { return 0; }
        global $wp_filter;
        $removed = 0;
        foreach ($wp_filter as $hook => $handlers) {
            foreach ($handlers->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $id => $callback) {
                    if (self::belongs_to($callback['function'], $module)) {
                        remove_filter($hook, $callback['function'], $priority);
                        $removed++;
                    }
                }
            }
        }
        return $removed;
    }

    private static function belongs_to($function, object $owner): bool {
        if (is_array($function)) { return ($function[0] ?? null) === $owner; }
        if ($function instanceof \Closure) {
            try { return (new \ReflectionFunction($function))->getClosureThis() === $owner; } catch (\ReflectionException) { return false; }
        }
        return false;
    }

    public static function editor_config(array $config): array {
        $config['promotionWidgets'] = [];
        foreach (['initial_document','document'] as $key) {
            if (isset($config[$key]['panel']['elements_categories']) && is_array($config[$key]['panel']['elements_categories'])) {
                foreach ($config[$key]['panel']['elements_categories'] as &$category) { unset($category['promotion']); }
                unset($category);
            }
        }
        return $config;
    }

    public static function theme_builder_url(): string {
        return \OldBearExpansion\ThemeBuilder\Admin::url();
    }

    public static function strip_controls($element, $section_id): void {
        if (!is_object($element) || !method_exists($element, 'get_controls')) { return; }
        $controls = $element->get_controls();
        foreach ($controls as $name => $control) {
            $promo_section = str_ends_with((string) $section_id, '_pro') && (($control['section'] ?? '') === $section_id || $name === $section_id);
            if ($promo_section || in_array($name, self::PRO_CONTROLS, true)) { $element->remove_control($name); }
        }
    }

    public static function plugin_links(array $links): array {
        unset($links['go_pro']);
        return $links;
    }

    /** Last resort for upsell markup printed straight from Elementor templates. */
    public static function editor_styles(): void {
        wp_register_style('obx-clean', false, [], \OldBearExpansion\VERSION);
        wp_enqueue_style('obx-clean');
        wp_add_inline_style('obx-clean', '#elementor-navigator__footer__promotion,#elementor-panel-get-pro-elements,#elementor-panel-get-pro-elements-sticky,.elementor-panel-heading-promotion,.e-link-promotion,.elementor-nerd-box,.elementor-control-type-promotion_control,#elementor-notice-bar,.elementor-panel-category-items .elementor-element--promotion,.elementor-element-wrapper.elementor-element--promotion{display:none!important}.elementor-navigator-list__promotion{padding-bottom:0!important}');
    }

    public static function admin_styles(): void {
        wp_register_style('obx-clean-admin', false, [], \OldBearExpansion\VERSION);
        wp_enqueue_style('obx-clean-admin');
        wp_add_inline_style('obx-clean-admin', '#adminmenu a[href*="go_elementor_pro"],#adminmenu a[href*="elementor_go_pro"],#adminmenu .elementor-app-link[href*="promotion"],.e-notice--cta[data-notice_id*="pro"],.elementor-go-pro{display:none!important}');
    }

    public static function register_setting(): void {
        register_setting('obx_settings', self::OPTION, ['type'=>'boolean','default'=>true,'sanitize_callback'=>'rest_sanitize_boolean']);
    }

    public static function settings_page(): void {
        add_options_page('OldBear Expansion', 'OldBear Expansion', 'manage_options', 'obx-settings', [self::class, 'render_settings']);
    }

    public static function render_settings(): void {
        if (!current_user_can('manage_options')) { return; }
        echo '<div class="wrap"><h1>OldBear Expansion for Elementor</h1><form method="post" action="options.php">';
        settings_fields('obx_settings');
        echo '<table class="form-table"><tr><th scope="row">Modo limpio</th><td><input type="hidden" name="' . esc_attr(self::OPTION) . '" value="0"><label><input type="checkbox" name="' . esc_attr(self::OPTION) . '" value="1" ' . checked((bool) get_option(self::OPTION, 1), true, false) . '> Ocultar los anuncios y funciones bloqueadas de Elementor Pro</label><p class="description">No desactiva ninguna función gratuita de Elementor. Su entrada «Maquetador de temas» abre el Theme Builder de este plugin.</p></td></tr></table>';
        submit_button();
        echo '</form><p><a href="' . esc_url(\OldBearExpansion\ThemeBuilder\Admin::url()) . '">Ir al Theme Builder</a></p></div>';
    }
}
