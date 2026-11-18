<?php
namespace OldBearExpansion\Admin;

use OldBearExpansion\Plugin;
use const OldBearExpansion\VERSION;

if (!defined('ABSPATH')) { exit; }

/**
 * OldBear's mark wherever the plugin stands in for Elementor Pro, so editors can tell that these
 * widgets and screens come from this expansion: a badge on its widgets in the editor panel,
 * the logo on its admin screens, help links to its documentation and a generator meta tag.
 */
final class Branding {
    public const NAME = 'OldBear Expansion';
    public const URL = 'https://github.com/erjavierexba/OldBearElementorExpansion';

    public static function boot(): void {
        add_action('elementor/editor/after_enqueue_styles', [self::class, 'editor_badges']);
        add_filter('plugin_row_meta', [self::class, 'row_meta'], 10, 2);
        add_action('wp_head', [self::class, 'generator'], 2);
    }

    public static function logo_url(int $size = 64): string {
        return Plugin::url('assets/img/oldbear-' . (in_array($size, [32, 64, 128], true) ? $size : 64) . '.png');
    }

    /** The bear at $size CSS pixels, with a double-density source. */
    public static function logo(int $size = 32, string $class = 'obx-brand__logo'): string {
        return sprintf('<img src="%s" srcset="%s 2x" width="%d" height="%d" alt="%s" class="%s" style="vertical-align:middle;border-radius:50%%">',
            esc_url(self::logo_url($size <= 32 ? 32 : 64)), esc_url(self::logo_url($size <= 32 ? 64 : 128)), $size, $size, esc_attr(self::NAME), esc_attr($class));
    }

    /** Admin screen title with the bear and the plugin's name. */
    public static function heading(string $title): string {
        $by = str_starts_with($title, self::NAME) ? '' : '<span class="obx-brand-heading__by" style="font-size:13px;font-weight:400;color:#646970">' . esc_html(self::NAME) . '</span>';
        return '<h1 class="obx-brand-heading" style="display:flex;align-items:center;gap:10px">' . self::logo(32) . '<span>' . esc_html($title) . '</span>' . $by . '</h1>';
    }

    /** Widget names to badge: the Pro names it provides (only without Pro) and its own names. */
    public static function widget_names(): array {
        \OldBearExpansion\ThemeBuilder\Module::load_widgets();
        $names = [];
        foreach (\OldBearExpansion\Widgets\Base::registry() as $class) {
            if (!$class::available()) { continue; }
            $names[] = $class::NAME;
            if (!Plugin::pro_active()) { $names = array_merge($names, [$class::PRO], $class::ALIASES); }
        }
        return array_values(array_unique(array_filter($names)));
    }

    /** A small bear in the corner of each of its widgets in the editor's widget panel. */
    public static function editor_badges(): void {
        $names = self::widget_names();
        if (!$names) { return; }
        $selectors = implode(',', array_map(static fn($name) => '.elementor-element[data-library-element-type="' . esc_attr($name) . '"]::after', $names));
        $css = $selectors . '{content:"";position:absolute;top:5px;left:5px;width:16px;height:16px;border-radius:50%;'
            . 'background:url("' . esc_url(self::logo_url(32)) . '") center/contain no-repeat;opacity:.9;pointer-events:none}';
        wp_add_inline_style('elementor-editor', $css);
    }

    /** Documentation link next to the plugin's name in Plugins. */
    public static function row_meta($links, $file) {
        if (is_array($links) && $file === plugin_basename(\OldBearExpansion\FILE)) {
            $links[] = '<a href="' . esc_url(self::URL . '#readme') . '" target="_blank" rel="noopener">Documentación</a>';
        }
        return $links;
    }

    public static function generator(): void {
        if (!apply_filters('obx/generator_tag', true)) { return; }
        echo '<meta name="generator" content="' . esc_attr('OldBear Expansion for Elementor ' . VERSION) . '">' . "\n";
    }
}
