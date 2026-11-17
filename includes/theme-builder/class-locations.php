<?php
namespace OldBearExpansion\ThemeBuilder;

use Elementor\Plugin as Elementor;
use OldBearExpansion\Context;
use const OldBearExpansion\DIR;

if (!defined('ABSPATH')) { exit; }

/**
 * Resolves and prints Theme Builder locations in classic themes:
 * header/footer replace the theme's header.php/footer.php, single/archive/404 replace the template.
 */
final class Locations {
    public const INDEX = 'obx_location_index';
    private static array $resolved = [];
    private static array $html = [];
    private static string $main = '';
    private static bool $swallowing = false;

    public static function boot(): void {
        add_action('get_header', [self::class, 'header'], 10, 2);
        add_action('get_footer', [self::class, 'footer'], 10, 2);
        add_filter('template_include', [self::class, 'template'], 12);
        add_action('wp_enqueue_scripts', [self::class, 'prepare'], 30);
        add_filter('body_class', [self::class, 'body_class']);
        foreach (['save_post_elementor_library','deleted_post','trashed_post','untrashed_post'] as $hook) { add_action($hook, [self::class, 'flush']); }
        add_action('elementor/document/after_save', [self::class, 'flush']);
        add_action('transition_post_status', static function($new, $old, $post) { if ($post->post_type === 'elementor_library') { self::flush(); } }, 10, 3);
    }

    public static function flush(...$unused): void { delete_option(self::INDEX); self::$resolved = []; }

    /** @return array<string,array<int,array>> location => [template id => conditions] */
    public static function index(): array {
        $index = get_option(self::INDEX);
        if (is_array($index)) { return $index; }
        $index = array_fill_keys(array_keys(Module::LOCATIONS), []);
        $posts = get_posts(['post_type'=>'elementor_library','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','orderby'=>'ID','order'=>'DESC',
            'meta_query'=>[['key'=>'_elementor_template_type','value'=>array_keys(Module::TYPES),'compare'=>'IN']]]);
        foreach ($posts as $id) {
            $document = Elementor::$instance->documents->get($id);
            if ($document instanceof Location_Document) { $index[$document::LOCATION][$id] = $document->get_conditions(); }
        }
        update_option(self::INDEX, $index, true);
        return $index;
    }

    public static function disabled(): bool {
        return is_singular('elementor_library') || is_embed() || is_feed() || is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST);
    }

    /** Template id for a location in the current request, or 0. */
    public static function template_id(string $location): int {
        if (array_key_exists($location, self::$resolved)) { return self::$resolved[$location]; }
        $best = 0; $winner = 0;
        if (!self::disabled()) {
            foreach (self::index()[$location] ?? [] as $id => $conditions) {
                $score = Conditions::evaluate($conditions);
                if ($score > $best || ($score === $best && $score > 0 && $id > $winner)) { $best = $score; $winner = (int) $id; }
            }
        }
        $winner = (int) apply_filters('obx/location/template_id', $winner, $location);
        return self::$resolved[$location] = $winner;
    }

    public static function reset(): void { self::$resolved = []; self::$html = []; self::$main = ''; }

    public static function main_location(): string {
        if (is_404()) { return 'error-404'; }
        if (is_singular()) {
            $template = get_page_template_slug(get_queried_object_id());
            return in_array($template, ['elementor_canvas'], true) ? '' : 'single';
        }
        return (is_archive() || is_home() || is_search()) ? 'archive' : '';
    }

    public static function template($template) {
        $location = self::main_location();
        if ($location !== '' && self::template_id($location)) {
            self::$main = $location;
            return DIR . '/templates/location.php';
        }
        return $template;
    }

    public static function current_main(): string { return self::$main; }

    public static function body_class(array $classes): array {
        foreach (array_keys(Module::LOCATIONS) as $location) {
            if (!empty(self::$resolved[$location])) { $classes[] = 'obx-has-' . $location; }
        }
        return $classes;
    }

    /** Renders header/footer early so Elementor enqueues their CSS inside <head>. */
    public static function prepare(): void {
        $frontend = Elementor::$instance->frontend;
        $found = false;
        foreach (array_keys(Module::LOCATIONS) as $location) {
            $id = self::template_id($location);
            if (!$id) { continue; }
            \Elementor\Core\Files\CSS\Post::create($id)->enqueue();
            $found = true;
            if (in_array($location, ['header','footer'], true)) { self::$html[$location] = self::build($id); }
        }
        if ($found) { $frontend->enqueue_styles(); $frontend->enqueue_scripts(); }
    }

    private static function build(int $id): string {
        return (string) Elementor::$instance->frontend->get_builder_content_for_display($id, false);
    }

    public static function render(string $location): void {
        $id = self::template_id($location);
        if (!$id) { return; }
        Context::push($location);
        echo self::$html[$location] ?? self::build($id); // Elementor output.
        Context::pop();
    }

    public static function has(string $location): bool { return self::template_id($location) > 0; }

    public static function header($name = null, $args = []): void {
        if (!self::has('header')) { return; }
        require DIR . '/templates/header.php';
        self::swallow('header', $name, 'wp_head');
    }

    public static function footer($name = null, $args = []): void {
        if (!self::has('footer')) { return; }
        require DIR . '/templates/footer.php';
        self::swallow('footer', $name, 'wp_footer');
    }

    /**
     * get_header()/get_footer() load the theme file right after their action with require_once.
     * Loading it here first, into a discarded buffer, prevents the theme version from printing.
     */
    private static function swallow(string $part, $name, string $hook): void {
        $templates = [];
        if (is_string($name) && $name !== '') { $templates[] = "{$part}-{$name}.php"; }
        $templates[] = "{$part}.php";
        remove_all_actions($hook);
        self::$swallowing = true;
        ob_start();
        locate_template($templates, true);
        ob_end_clean();
        self::$swallowing = false;
    }

    /** Theme API (elementor_theme_do_location): true when this plugin handles the location. */
    public static function do_location(string $location): bool {
        if (self::$swallowing) { return true; }
        if (in_array($location, ['header','footer'], true)) { return false; }
        if (!self::has($location)) { return false; }
        self::render($location);
        return true;
    }
}
