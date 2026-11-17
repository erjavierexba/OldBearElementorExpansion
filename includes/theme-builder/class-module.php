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
        add_action('elementor/elements/categories_registered', [self::class, 'categories']);
        add_action('elementor/widgets/register', [self::class, 'widgets']);
        add_action('init', [self::class, 'assets'], 5);
        Locations::boot();
        Admin::boot();
    }

    public static function documents($manager): void {
        foreach (self::LOCATIONS as $location => $class) { $manager->register_document_type('obx-' . $location, $class); }
    }

    public static function categories($manager): void {
        $manager->add_category('obx-site', ['title'=>'Sitio','icon'=>'eicon-site-identity']);
        $manager->add_category('obx-single', ['title'=>'Entrada individual','icon'=>'eicon-single-post']);
        $manager->add_category('obx-archive', ['title'=>'Archivo','icon'=>'eicon-archive']);
    }

    public static function widgets($manager): void {
        require_once DIR . '/includes/widgets/class-base.php';
        require_once DIR . '/includes/widgets/theme-widgets.php';
        require_once DIR . '/includes/widgets/site-widgets.php';
        foreach (\OldBearExpansion\Widgets\Base::registry() as $class) { $manager->register(new $class()); }
    }

    public static function assets(): void {
        wp_register_style('obx-frontend', Plugin::url('assets/css/frontend.css'), [], VERSION);
        wp_register_script('obx-nav-menu', Plugin::url('assets/js/nav-menu.js'), [], VERSION, ['strategy'=>'defer','in_footer'=>true]);
    }
}
