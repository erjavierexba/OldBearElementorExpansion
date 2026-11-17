<?php
namespace OldBearExpansion\ThemeBuilder;

use Elementor\Plugin as Elementor;
use OldBearExpansion\Plugin;
use const OldBearExpansion\VERSION;

if (!defined('ABSPATH')) { exit; }

/**
 * The Theme Builder inside Elementor's own app (admin.php?page=elementor-app#/site-editor), the
 * screen Elementor opens from its menu, the editor and the admin bar. Elementor Free ships the
 * site editor shell (layout, menu of site parts, cards) and only registers a promotion route; this
 * class adds the working routes with our own script and a small REST API for the templates.
 */
final class Site_Editor {
    public const HANDLE = 'obx-site-editor';
    public const NS = 'obx/v1';

    /** Theme Builder parts, in Elementor's order: app type => template types listed under it. */
    public const PARTS = [
        'header'=>['header'],
        'footer'=>['footer'],
        'single-page'=>['single-page'],
        'single-post'=>['single-post','single'],
        'archive'=>['archive'],
        'search-results'=>['search-results'],
        'product'=>['product'],
        'product-archive'=>['product-archive'],
        'error-404'=>['error-404'],
        'loop-item'=>['loop-item'],
    ];

    public static function boot(): void {
        add_action('init', [self::class, 'menu_url'], 20);
        add_action('elementor/app/init', [self::class, 'app']);
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('elementor/editor/after_enqueue_scripts', [self::class, 'editor']);
    }

    /** Editor: the first publish of a Theme Builder template asks for its display conditions. */
    public static function editor(): void {
        $types = [];
        foreach (self::PARTS as $list) {
            foreach ($list as $type) {
                $class = Module::TYPES[$type] ?? null;
                if ($class && $class::HAS_CONDITIONS && $class::LOCATION !== 'error-404') { $types[] = $type; }
            }
        }
        wp_enqueue_script('obx-editor-conditions', Plugin::url('assets/js/editor-conditions.js'), ['elementor-editor'], VERSION, true);
        wp_add_inline_script('obx-editor-conditions', 'window.obxEditorConditions = ' . wp_json_encode(['types'=>$types,'app'=>admin_url('admin.php?page=elementor-app')]) . ';', 'before');
    }

    /** The app's entry point for the Theme Builder (used by Elementor's menu, admin bar, finder and editor). */
    public static function url(string $route = '/site-editor'): string {
        return admin_url('admin.php?page=elementor-app') . '#' . $route;
    }

    public static function menu_url(): void {
        if (isset(Elementor::$instance->app) && Elementor::$instance->app) {
            Elementor::$instance->app->set_settings('menu_url', Elementor::$instance->app->get_base_url() . '#/site-editor');
        }
    }

    /** Elementor's app page: our routes load before the app boots. */
    public static function app(): void {
        // The editor runs in its own agent cluster (Document-Isolation-Policy, WordPress 7.1+): the app opened
        // in its modal must join it, or it cannot reach the editor (close the modal, refresh its settings).
        if (method_exists(\Elementor\Core\Editor\Editor::class, 'send_document_isolation_policy_header')) {
            \Elementor\Core\Editor\Editor::send_document_isolation_policy_header();
        }
        add_action('wp_print_styles', [self::class, 'enqueue'], 0);
    }

    public static function enqueue(): void {
        $scripts = wp_scripts();
        if (!isset($scripts->registered['elementor-app'])) { return; }
        wp_register_script(self::HANDLE, Plugin::url('assets/js/site-editor.js'), ['elementor-app-packages', 'react', 'wp-i18n'], VERSION, true);
        wp_add_inline_script(self::HANDLE, 'window.obxSiteEditor = ' . wp_json_encode(self::config()) . ';', 'before');
        // app.js builds the site editor module on load: our routes must be registered first.
        if (!in_array(self::HANDLE, $scripts->registered['elementor-app']->deps, true)) { $scripts->registered['elementor-app']->deps[] = self::HANDLE; }
        wp_register_style(self::HANDLE, Plugin::url('assets/css/site-editor.css'), [], VERSION);
        wp_print_styles([self::HANDLE]);
    }

    public static function config(): array {
        $rules = [];
        foreach (['header','single','archive','error-404'] as $location) {
            $rules[$location] = [];
            foreach (Conditions::rules($location) as $value => $label) { $rules[$location][] = ['value'=>$value,'label'=>$label,'ids'=>Conditions::requires_ids($value)]; }
        }
        $create = [];
        foreach (array_keys(self::PARTS) as $type) { $create[$type] = self::create_url($type); }
        return [
            'rest'=>esc_url_raw(rest_url(self::NS . '/site-editor')),
            'nonce'=>wp_create_nonce('wp_rest'),
            'create'=>$create,
            'woocommerce'=>class_exists('WooCommerce'),
            'rules'=>$rules,
            'assets'=>ELEMENTOR_ASSETS_URL . 'images/app/site-editor/',
            'templates_url'=>admin_url('edit.php?post_type=elementor_library&tabs_group=theme'),
        ];
    }

    public static function create_url(string $type): string {
        // Raw URL (no &amp;): it is used from JavaScript.
        return add_query_arg(['action'=>'obx_new_template','type'=>$type,'obx_top'=>1,'_wpnonce'=>wp_create_nonce('obx_new_template')], admin_url('admin-post.php'));
    }

    public static function routes(): void {
        $can = static fn() => current_user_can('edit_posts');
        $can_post = static fn(\WP_REST_Request $request) => current_user_can('edit_post', (int) $request['id']);
        register_rest_route(self::NS, '/site-editor/templates', ['methods'=>'GET','callback'=>[self::class, 'list'],'permission_callback'=>$can]);
        register_rest_route(self::NS, '/site-editor/templates/(?P<id>\d+)', [
            ['methods'=>'POST','callback'=>[self::class, 'update'],'permission_callback'=>$can_post],
            ['methods'=>'DELETE','callback'=>[self::class, 'delete'],'permission_callback'=>static fn(\WP_REST_Request $request) => current_user_can('delete_post', (int) $request['id'])],
        ]);
    }

    /** @return array<string,string> template type => app part */
    private static function part_of(): array {
        $map = [];
        foreach (self::PARTS as $part => $types) { foreach ($types as $type) { $map[$type] = $part; } }
        return $map;
    }

    public static function list(): \WP_REST_Response {
        $parts = self::part_of();
        $posts = get_posts(['post_type'=>'elementor_library','post_status'=>['publish','draft','private','pending','future'],'posts_per_page'=>-1,'orderby'=>'modified','order'=>'DESC',
            'meta_query'=>[['key'=>'_elementor_template_type','value'=>array_keys($parts),'compare'=>'IN']]]);
        $items = [];
        foreach ($posts as $post) {
            $item = self::item($post);
            if ($item) { $items[] = $item; }
        }
        return new \WP_REST_Response($items);
    }

    public static function item(\WP_Post $post): ?array {
        $document = Elementor::$instance->documents->get($post->ID);
        if (!$document instanceof Location_Document) { return null; }
        $part = self::part_of()[$document::get_type()] ?? null;
        if (!$part) { return null; }
        $location = $document::LOCATION;
        $conditions = $document::HAS_CONDITIONS ? $document->get_conditions() : [];
        $active = $post->post_status === 'publish' && isset(Locations::index()[$location][$post->ID]) && $conditions;
        $source = Elementor::$instance->templates_manager->get_source('local');
        $export = $source ? ($source->get_item($post->ID)['export_link'] ?? '') : '';
        return [
            'id'=>$post->ID,
            'title'=>get_the_title($post) ?: '(sin título)',
            'type'=>$document::get_type(),
            'part'=>$part,
            'location'=>$location,
            'status'=>$post->post_status,
            'modified'=>get_post_modified_time('U', true, $post),
            'modified_human'=>sprintf('Modificado hace %s', human_time_diff((int) get_post_modified_time('U', true, $post))),
            'author'=>get_the_author_meta('display_name', (int) $post->post_author),
            'has_conditions'=>$document::HAS_CONDITIONS && $location !== 'error-404',
            'conditions'=>array_map(static fn($c) => ['mode'=>$c['mode'],'rule'=>$c['rule'],'ids'=>implode(', ', $c['ids'])], $conditions),
            'instances'=>$document::HAS_CONDITIONS ? Conditions::describe($conditions, $location) : '',
            'active'=>(bool) $active,
            'urls'=>[
                'edit'=>$document->get_edit_url(),
                'preview'=>$document->get_wp_preview_url(),
                'export'=>$export ? html_entity_decode($export) : '',
            ],
        ];
    }

    public static function update(\WP_REST_Request $request) {
        $post = get_post((int) $request['id']);
        if (!$post || $post->post_type !== 'elementor_library') { return new \WP_Error('obx_not_found', 'Plantilla no encontrada.', ['status'=>404]); }
        $document = Elementor::$instance->documents->get($post->ID);
        if (!$document instanceof Location_Document) { return new \WP_Error('obx_not_found', 'Plantilla no encontrada.', ['status'=>404]); }
        $title = $request->get_param('title');
        if (is_string($title) && trim($title) !== '') {
            wp_update_post(['ID'=>$post->ID,'post_title'=>sanitize_text_field($title)]);
        }
        $rows = $request->get_param('conditions');
        if (is_array($rows) && $document::HAS_CONDITIONS) {
            $rows = array_map(static fn($row) => is_array($row) ? ['mode'=>$row['mode'] ?? 'include','rule'=>$row['rule'] ?? '','object_id'=>$row['ids'] ?? ''] : [], $rows);
            Location_Document::store_conditions($post->ID, Conditions::normalize($rows));
            Locations::flush();
        }
        Elementor::$instance->documents->get($post->ID, false);
        return new \WP_REST_Response(self::item(get_post($post->ID)));
    }

    public static function delete(\WP_REST_Request $request) {
        $post = get_post((int) $request['id']);
        if (!$post || $post->post_type !== 'elementor_library') { return new \WP_Error('obx_not_found', 'Plantilla no encontrada.', ['status'=>404]); }
        wp_trash_post($post->ID);
        Locations::flush();
        return new \WP_REST_Response(['deleted'=>true,'id'=>$post->ID]);
    }
}
