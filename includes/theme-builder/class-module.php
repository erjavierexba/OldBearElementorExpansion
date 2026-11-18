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
        Loop_Item_Document::TYPE=>Loop_Item_Document::class,
        Product_Document::TYPE=>Product_Document::class,
        Product_Archive_Document::TYPE=>Product_Archive_Document::class,
        Popup_Document::TYPE=>Popup_Document::class,
    ];

    /** @return string[] template types printed at a location */
    public static function types_for(string $location): array {
        return array_keys(array_filter(self::TYPES, static fn($class) => $class::LOCATION === $location));
    }

    public static function boot(): void {
        add_action('elementor/documents/register', [self::class, 'documents']);
        add_action('elementor/controls/register', static function ($controls) {
            require_once DIR . '/includes/widgets/class-menu-dropdown-animation.php';
            if (!$controls->get_control(\OldBearExpansion\Widgets\Menu_Dropdown_Animation::TYPE)) { $controls->register(new \OldBearExpansion\Widgets\Menu_Dropdown_Animation()); }
        });
        add_action('elementor/document/after_save', [self::class, 'sync_conditions']);
        self::boot_widgets();
        Locations::boot();
        Admin::boot();
        Site_Editor::boot();
        Kit_Import::boot();
        Upgrade::boot();
        Saved_Documents::boot();
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
        // Live results of the Search widget; the widget files load only when the route is registered.
        add_action('rest_api_init', static function() { self::load_widgets(); \OldBearExpansion\Widgets\Search::routes(); });
        // Shop archives paginate with the «columns × rows» of the Archive Products widget that prints them.
        add_action('pre_get_posts', static function($query) {
            if (Plugin::pro_active() || !class_exists('WooCommerce') || !$query instanceof \WP_Query || !$query->is_main_query() || is_admin()) { return; }
            self::load_widgets();
            \OldBearExpansion\Widgets\Woo_Archive_Products::main_query($query);
        }, 20);
        // Menu carts refresh their count, subtotal and contents with WooCommerce's cart fragments.
        add_filter('woocommerce_add_to_cart_fragments', static function($fragments) {
            if (Plugin::pro_active() || !is_array($fragments)) { return $fragments; }
            self::load_widgets();
            return \OldBearExpansion\Widgets\Woo_Menu_Cart::fragments($fragments);
        });
        // WooCommerce's AJAX refreshes (cart totals, order review) print the texts of the page's widget again.
        add_action('wc_ajax_get_cart_totals', static function() {
            if (Plugin::pro_active()) { return; }
            self::load_widgets();
            \OldBearExpansion\Widgets\Woo_Cart::replace_texts(\OldBearExpansion\Widgets\Woo_Cart::remembered());
        }, 1);
        add_action('woocommerce_checkout_update_order_review', static function() {
            if (Plugin::pro_active()) { return; }
            self::load_widgets();
            $data = \OldBearExpansion\Widgets\Woo_Checkout::remembered();
            if ($data) { \OldBearExpansion\Widgets\Woo_Checkout::apply($data); }
        });
        // A WooCommerce Notices widget on the page or its templates prints the notices instead of WooCommerce.
        add_action('wp', static function() {
            if (Plugin::pro_active() || !class_exists('WooCommerce') || is_admin() || wp_doing_ajax()) { return; }
            $documents = array_filter([is_singular() ? get_queried_object_id() : 0, Locations::template_id('header'), Locations::template_id('footer'), Locations::template_id(Locations::main_location())]);
            if ($documents) { self::load_widgets(); \OldBearExpansion\Widgets\Woo_Notices::relocate($documents); }
        }, 20);
        add_action('init', [self::class, 'assets'], 5);
        if (!Plugin::pro_active()) { add_filter('wp_check_filetype_and_ext', [self::class, 'json_file_type'], 10, 3); }
        add_action('wp_enqueue_scripts', static function() { wp_enqueue_style('obx-frontend'); wp_add_inline_style('obx-frontend', self::menu_breakpoints_css()); });
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

    public static function load_widgets(): void {
        require_once DIR . '/includes/widgets/class-base.php';
        require_once DIR . '/includes/widgets/class-query.php';
        require_once DIR . '/includes/widgets/theme-widgets.php';
        require_once DIR . '/includes/widgets/site-widgets.php';
        require_once DIR . '/includes/widgets/posts-widgets.php';
        require_once DIR . '/includes/widgets/pro-widgets.php';
        require_once DIR . '/includes/widgets/theme-extra-widgets.php';
        require_once DIR . '/includes/widgets/interactive-widgets.php';
        require_once DIR . '/includes/widgets/form-widget.php';
        require_once DIR . '/includes/widgets/carousel-widgets.php';
        require_once DIR . '/includes/widgets/nested-widgets.php';
        require_once DIR . '/includes/widgets/media-widgets.php';
        require_once DIR . '/includes/widgets/lottie-widget.php';
        require_once DIR . '/includes/widgets/portfolio-widget.php';
        // Elementor includes its widget files itself (no require_once): ours extending them wait for them.
        if (class_exists('\\Elementor\\Widget_Image', false) && !class_exists('\\OldBearExpansion\\Widgets\\Hotspot', false)) { require DIR . '/includes/widgets/image-widgets.php'; }
        require_once DIR . '/includes/widgets/loop-widgets.php';
        require_once DIR . '/includes/widgets/woocommerce-widgets.php';
        require_once DIR . '/includes/widgets/woocommerce-products.php';
        require_once DIR . '/includes/widgets/woocommerce-single.php';
        require_once DIR . '/includes/widgets/woocommerce-cart.php';
        require_once DIR . '/includes/widgets/woocommerce-pages.php';
        require_once DIR . '/includes/widgets/woocommerce-classic.php';
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
            if (class_exists('WooCommerce')) {
                foreach (['woocommerce-elements'=>['WooCommerce','eicon-woocommerce'],'woocommerce-elements-single'=>['Producto individual','eicon-single-product'],'woocommerce-elements-archive'=>['Archivo de productos','eicon-products']] as $key => [$title,$icon]) {
                    if (!isset($manager->get_categories()[$key])) { $manager->add_category($key, ['title'=>$title,'icon'=>$icon,'active'=>false]); }
                }
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

    /** Menu widget: below its «Breakpoint» the bar becomes a toggle button (before the script decides, to avoid a jump). */
    public static function menu_breakpoints_css(): string {
        $css = '';
        if (!class_exists('\\Elementor\\Plugin') || !isset(\Elementor\Plugin::$instance->breakpoints)) { return $css; }
        foreach (\Elementor\Plugin::$instance->breakpoints->get_active_breakpoints() as $key => $breakpoint) {
            if ($key === 'widescreen') { continue; }
            $class = '.elementor .elementor-widget-n-menu.e-n-menu-' . sanitize_html_class((string) $key);
            $css .= '@media (max-width:' . (int) $breakpoint->get_value() . 'px){' . $class . '{--n-menu-wrapper-display:none}' . $class . ' > .e-n-menu > .e-n-menu-toggle{display:flex}}';
        }
        return $css;
    }

    public static function assets(): void {
        wp_register_style('obx-frontend', Plugin::url('assets/css/frontend.css'), [], VERSION);
        wp_register_script('obx-nav-menu', Plugin::url('assets/js/nav-menu.js'), [], VERSION, ['strategy'=>'defer','in_footer'=>true]);
        wp_register_script('obx-mega-menu', Plugin::url('assets/js/mega-menu.js'), [], VERSION, ['strategy'=>'defer','in_footer'=>true]);
        wp_register_script('obx-off-canvas', Plugin::url('assets/js/off-canvas.js'), [], VERSION, ['strategy'=>'defer','in_footer'=>true]);
        wp_register_script('obx-hotspot', Plugin::url('assets/js/hotspot.js'), [], VERSION, ['strategy'=>'defer','in_footer'=>true]);
        wp_register_script('obx-widgets', Plugin::url('assets/js/widgets.js'), [], VERSION, ['strategy'=>'defer','in_footer'=>true]);
        // lottie-web 5.12.2 (MIT, Airbnb) plays the Lottie widget's animations.
        wp_register_script('obx-lottie-web', Plugin::url('assets/vendor/lottie/lottie.min.js'), [], '5.12.2', ['strategy'=>'defer','in_footer'=>true]);
        wp_register_script('obx-portfolio', Plugin::url('assets/js/portfolio.js'), [], VERSION, ['strategy'=>'defer','in_footer'=>true]);
        wp_register_script('obx-lottie', Plugin::url('assets/js/lottie.js'), ['obx-lottie-web'], VERSION, ['strategy'=>'defer','in_footer'=>true]);
        wp_add_inline_script('obx-lottie', 'window.obxLottie = ' . wp_json_encode(['defaultAnimationUrl'=>Plugin::url('assets/lottie/default.json')]) . ';', 'before');
    }

    /** WordPress may not recognise an uploaded .json (finfo reports text/plain): the Lottie widget needs it. */
    public static function json_file_type($data, $file, $filename) {
        if (!empty($data['ext']) && !empty($data['type'])) { return $data; }
        $type = wp_check_filetype((string) $filename);
        if ('json' === $type['ext']) { $data['ext'] = 'json'; $data['type'] = 'application/json'; }
        return $data;
    }
}
