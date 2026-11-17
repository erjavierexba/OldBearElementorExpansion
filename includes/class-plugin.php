<?php
namespace OldBearExpansion;

if (!defined('ABSPATH')) { exit; }

final class Plugin {
    public const MIN_ELEMENTOR = '3.20.0';

    public static function boot(): void {
        if (!did_action('elementor/loaded') || !defined('ELEMENTOR_VERSION')) {
            add_action('admin_notices', [self::class, 'missing_notice']);
            return;
        }
        if (version_compare(ELEMENTOR_VERSION, self::MIN_ELEMENTOR, '<')) {
            add_action('admin_notices', [self::class, 'version_notice']);
            return;
        }
        require_once DIR . '/includes/class-context.php';
        require_once DIR . '/includes/theme-builder/class-conditions.php';
        require_once DIR . '/includes/theme-builder/class-documents.php';
        require_once DIR . '/includes/theme-builder/class-locations.php';
        require_once DIR . '/includes/theme-builder/class-admin.php';
        require_once DIR . '/includes/theme-builder/class-upgrade.php';
        require_once DIR . '/includes/theme-builder/class-kit-import.php';
        require_once DIR . '/includes/theme-builder/class-module.php';
        require_once DIR . '/includes/class-converter.php';
        Converter::boot();
        if (self::pro_active()) {
            // Coexistence: Elementor Pro owns the Theme Builder; our widgets stay available.
            ThemeBuilder\Module::boot_widgets();
            add_action('admin_notices', [self::class, 'pro_notice']);
            return;
        }
        ThemeBuilder\Module::boot();
        require_once DIR . '/includes/dynamic/class-tags.php';
        Dynamic\Tags::boot();
        require_once DIR . '/includes/extras/class-extras.php';
        Extras\Extras::boot();
        require_once DIR . '/includes/popups/class-popups.php';
        Popups\Popups::boot();
        require_once DIR . '/includes/forms/class-handler.php';
        Forms\Handler::boot();
        require_once DIR . '/includes/compat.php';
        require_once DIR . '/includes/clean/class-clean-mode.php';
        Clean\Clean_Mode::boot();
    }

    public static function pro_active(): bool {
        return (bool) apply_filters('obx/pro_active', defined('ELEMENTOR_PRO_VERSION'));
    }

    public static function pro_notice(): void {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (current_user_can('activate_plugins') && $screen && in_array($screen->id, ['plugins','dashboard'], true)) {
            echo '<div class="notice notice-info"><p><strong>OldBear Expansion</strong> convive con Elementor Pro: el Theme Builder de Pro tiene prioridad y los widgets de OldBear siguen disponibles en el editor.</p></div>';
        }
    }

    public static function url(string $path): string {
        return plugins_url($path, FILE);
    }

    public static function missing_notice(): void {
        if (current_user_can('activate_plugins')) {
            echo '<div class="notice notice-warning"><p><strong>OldBear Expansion</strong> necesita Elementor activo.</p></div>';
        }
    }

    public static function version_notice(): void {
        if (current_user_can('activate_plugins')) {
            echo '<div class="notice notice-warning"><p><strong>OldBear Expansion</strong> necesita Elementor ' . esc_html(self::MIN_ELEMENTOR) . ' o superior.</p></div>';
        }
    }
}
