<?php
namespace OldBearExpansion\ThemeBuilder;

use OldBearExpansion\Plugin;
use const OldBearExpansion\DIR;
use const OldBearExpansion\VERSION;

if (!defined('ABSPATH')) { exit; }

final class Module {
    /** location => document class used when creating a template there */
    public const LOCATIONS = [
        'header'=>Header_Document::class,
        'footer'=>Footer_Document::class,
        'single'=>Single_Post_Document::class,
        'archive'=>Archive_Document::class,
        'error-404'=>Error_Document::class,
    ];

    /** Every template type handled, with Elementor Pro's names. */
    public const TYPES = [
        Header_Document::TYPE=>Header_Document::class,
        Footer_Document::TYPE=>Footer_Document::class,
        Single_Post_Document::TYPE=>Single_Post_Document::class,
        Single_Page_Document::TYPE=>Single_Page_Document::class,
        Single_Document::TYPE=>Single_Document::class,
        Archive_Document::TYPE=>Archive_Document::class,
        Search_Results_Document::TYPE=>Search_Results_Document::class,
        Error_Document::TYPE=>Error_Document::class,
    ];

    /** @return string[] template types printed at a location */
    public static function types_for(string $location): array {
        return array_keys(array_filter(self::TYPES, static fn($class) => $class::LOCATION === $location));
    }

    public static function boot(): void {
        add_action('elementor/documents/register', [self::class, 'documents']);
        add_action('elementor/document/after_save', [self::class, 'sync_conditions']);
        self::boot_widgets();
        Locations::boot();
        Admin::boot();
        Kit_Import::boot();
        Upgrade::boot();
    }

    /** After saving in the editor, mirror the conditions into Elementor Pro's meta. */
    public static function sync_conditions($document): void {
        if ($document instanceof Location_Document) {
            update_post_meta((int) $document->get_main_id(), Conditions::META, Conditions::to_strings($document->get_conditions()));
        }
    }

    /** Widgets and assets only: also used when Elementor Pro handles the Theme Builder. */
    public static function boot_widgets(): void {
        add_action('elementor/elements/categories_registered', [self::class, 'categories']);
        add_action('elementor/widgets/register', [self::class, 'widgets']);
        add_action('init', [self::class, 'assets'], 5);
        add_action('wp_enqueue_scripts', static function() { wp_enqueue_style('obx-frontend'); });
        add_action('elementor/editor/after_enqueue_scripts', static function() {
            if (!Plugin::pro_active()) { wp_enqueue_script('obx-editor-nested', Plugin::url('assets/js/editor-nested.js'), ['jquery'], VERSION, true); }
        });
        if (!Plugin::pro_active() && defined('ELEMENTOR_VERSION')) {
            // Elementor lists Pro widgets as locked promotions; drop the ones we provide.
            add_filter('transient_elementor_remote_info_api_data_' . ELEMENTOR_VERSION, [self::class, 'drop_promotions']);
            self::filter_remote_info([self::class, 'drop_promotions']);
        }
    }

    /** @return string[] Widget names this plugin registers in place of Elementor Pro's. */
    public static function pro_names(): array {
        self::load_widgets();
        $names = [];
        foreach (\OldBearExpansion\Widgets\Base::registry() as $class) { $names = array_merge($names, array_filter(array_merge([$class::PRO], $class::ALIASES))); }
        return $names;
    }

    /**
     * Applies $callback to Elementor's remote info also when it has just been downloaded:
     * on that first request Elementor uses the response directly, without reading the transient.
     */
    public static function filter_remote_info(callable $callback): void {
        add_filter('http_response', static function ($response, $args, $url) use ($callback) {
            $prefix = class_exists('\Elementor\Api') && isset(\Elementor\Api::$api_info_url) ? (string) \Elementor\Api::$api_info_url : 'https://my.elementor.com/api/v2/info/';
            if (!is_string($url) || !str_starts_with($url, $prefix) || !is_array($response)) { return $response; }
            $data = json_decode((string) wp_remote_retrieve_body($response), true);
            if (is_array($data)) { $response['body'] = (string) wp_json_encode($callback($data)); }
            return $response;
        }, 10, 3);
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
        require_once DIR . '/includes/widgets/posts-widgets.php';
        require_once DIR . '/includes/widgets/pro-widgets.php';
        require_once DIR . '/includes/widgets/theme-extra-widgets.php';
        require_once DIR . '/includes/widgets/interactive-widgets.php';
        require_once DIR . '/includes/widgets/form-widget.php';
        require_once DIR . '/includes/widgets/carousel-widgets.php';
    }

    public static function documents($manager): void {
        foreach (self::TYPES as $type => $class) { $manager->register_document_type($type, $class); }
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
            if (!$class::available()) { continue; }
            // With Elementor Pro active only our own names are added; Pro keeps its widgets.
            if ($class::NAME !== '') { $manager->register(new $class([], ['obx_name'=>$class::NAME])); }
            if ($pro) { continue; }
            foreach (array_merge([$class::PRO], $class::ALIASES) as $name) {
                if ($name !== '') { $manager->register(new $class([], ['obx_name'=>$name])); }
            }
        }
    }

    public static function assets(): void {
        wp_register_style('obx-frontend', Plugin::url('assets/css/frontend.css'), [], VERSION);
        wp_register_script('obx-nav-menu', Plugin::url('assets/js/nav-menu.js'), [], VERSION, ['strategy'=>'defer','in_footer'=>true]);
        wp_register_script('obx-widgets', Plugin::url('assets/js/widgets.js'), [], VERSION, ['strategy'=>'defer','in_footer'=>true]);
    }
}
