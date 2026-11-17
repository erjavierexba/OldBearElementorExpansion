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
        if (defined('ELEMENTOR_PRO_VERSION')) {
            add_action('admin_notices', [self::class, 'pro_notice']);
            return;
        }
        require_once DIR . '/includes/class-context.php';
        require_once DIR . '/includes/theme-builder/class-conditions.php';
        require_once DIR . '/includes/theme-builder/class-documents.php';
        require_once DIR . '/includes/theme-builder/class-locations.php';
        require_once DIR . '/includes/theme-builder/class-admin.php';
        require_once DIR . '/includes/theme-builder/class-module.php';
        ThemeBuilder\Module::boot();
        require_once DIR . '/includes/compat.php';
    }

    public static function pro_notice(): void {
        if (current_user_can('activate_plugins')) {
            echo '<div class="notice notice-info"><p><strong>OldBear Expansion</strong> está en pausa porque Elementor Pro está activo: ambos ofrecen Theme Builder.</p></div>';
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
