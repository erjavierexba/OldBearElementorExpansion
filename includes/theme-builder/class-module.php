<?php
namespace OldBearExpansion\ThemeBuilder;

use OldBearExpansion\Plugin;
use const OldBearExpansion\DIR;
use const OldBearExpansion\VERSION;

if (!defined('ABSPATH')) { exit; }

final class Module {
    /** location => document class */
    public const LOCATIONS = [
        'header'=>Header_Document::class,
        'footer'=>Footer_Document::class,
        'single'=>Single_Document::class,
        'archive'=>Archive_Document::class,
        'error-404'=>Error_Document::class,
    ];

    public static function boot(): void {
        add_action('elementor/documents/register', [self::class, 'documents']);
        self::boot_widgets();
        Locations::boot();
        Admin::boot();
    }

    /** Widgets and assets only: also used when Elementor Pro handles the Theme Builder. */
    public static function boot_widgets(): void {
        add_action('elementor/elements/categories_registered', [self::class, 'categories']);
        add_action('elementor/widgets/register', [self::class, 'widgets']);
        add_action('init', [self::class, 'assets'], 5);
        add_action('wp_enqueue_scripts', static function() { wp_enqueue_style('obx-frontend'); });
        if (!Plugin::pro_active() && defined('ELEMENTOR_VERSION')) {
            // Elementor lists Pro widgets as locked promotions; drop the ones we provide.
            add_filter('transient_elementor_remote_info_api_data_' . ELEMENTOR_VERSION, [self::class, 'drop_promotions']);
        }
    }

    /** @return string[] Widget names this plugin registers in place of Elementor Pro's. */
    public static function pro_names(): array {
        self::load_widgets();
        $names = [];
        foreach (\OldBearExpansion\Widgets\Base::registry() as $class) { if ($class::PRO !== '') { $names[] = $class::PRO; } }
        return $names;
    }

    public static function drop_promotions($data) {
        if (is_array($data) && !empty($data['pro_widgets']) && is_array($data['pro_widgets'])) {
            $ours = array_flip(self::pro_names());
            $data['pro_widgets'] = array_values(array_filter($data['pro_widgets'], static fn($w) => !isset($ours[$w['name'] ?? ''])));
        }
        return $data;
    }

    private static function load_widgets(): void {
        require_once DIR . '/includes/widgets/class-base.php';
        require_once DIR . '/includes/widgets/theme-widgets.php';
        require_once DIR . '/includes/widgets/site-widgets.php';
        require_once DIR . '/includes/widgets/theme-extra-widgets.php';
        require_once DIR . '/includes/widgets/pro-widgets.php';
    }

    public static function documents($manager): void {
        foreach (self::LOCATIONS as $location => $class) { $manager->register_document_type('obx-' . $location, $class); }
    }

    public static function categories($manager): void {
        // Without Pro, our widgets live in Elementor's own «Pro» and «Sitio» categories.
        if (!Plugin::pro_active()) {
            if (!isset($manager->get_categories()['theme-elements-single'])) {
                $manager->add_category('theme-elements-single', ['title'=>'Individual','icon'=>'eicon-single-post','active'=>false]);
            }
            return;
        }
        $manager->add_category('obx-site', ['title'=>'Sitio','icon'=>'eicon-site-identity']);
        $manager->add_category('obx-single', ['title'=>'Entrada individual','icon'=>'eicon-single-post']);
        $manager->add_category('obx-archive', ['title'=>'Archivo','icon'=>'eicon-archive']);
    }

    public static function widgets($manager): void {
        self::load_widgets();
        $pro = Plugin::pro_active();
        foreach (\OldBearExpansion\Widgets\Base::registry() as $class) {
            // With Elementor Pro active only our own names are added; Pro keeps its widgets.
            if ($class::NAME !== '') { $manager->register(new $class([], ['obx_name'=>$class::NAME])); }
            if (!$pro && $class::PRO !== '') { $manager->register(new $class([], ['obx_name'=>$class::PRO])); }
        }
    }

    public static function assets(): void {
        wp_register_style('obx-frontend', Plugin::url('assets/css/frontend.css'), [], VERSION);
        wp_register_script('obx-nav-menu', Plugin::url('assets/js/nav-menu.js'), [], VERSION, ['strategy'=>'defer','in_footer'=>true]);
        wp_register_script('obx-widgets', Plugin::url('assets/js/widgets.js'), [], VERSION, ['strategy'=>'defer','in_footer'=>true]);
    }
}
