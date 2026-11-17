<?php
namespace OldBearExpansion\ThemeBuilder;

use const OldBearExpansion\DIR;

if (!defined('ABSPATH')) { exit; }

/**
 * Elementor Free skips the Theme Builder templates of a kit unless Elementor Pro is active.
 * This swaps in a runner that imports them as our templates, with their display conditions.
 */
final class Kit_Import {
    public static function boot(): void {
        add_action('elementor/import-export/import-kit', [self::class, 'register']);
    }

    public static function register($import): void {
        if (!is_object($import) || !method_exists($import, 'register') || !class_exists('\Elementor\App\Modules\ImportExport\Runners\Import\Import_Runner_Base')) { return; }
        require_once DIR . '/includes/theme-builder/class-kit-templates-runner.php';
        $import->register(new Kit_Templates_Runner());
    }

    /** Conditions of a manifest entry: Elementor's own format or Envato's metadata. */
    public static function manifest_conditions(array $settings): array {
        $items = $settings['conditions'] ?? ($settings['metadata']['elementor_pro_conditions'] ?? []);
        return Conditions::from_strings(is_array($items) ? $items : []);
    }
}
