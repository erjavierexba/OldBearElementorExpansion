<?php
namespace OldBearExpansion\Admin;

use OldBearExpansion\ThemeBuilder\Admin as Theme_Admin;
use const OldBearExpansion\VERSION;

if (!defined('ABSPATH')) { exit; }

/**
 * Makes Elementor's own admin screens behave as they do on a site with Elementor Pro, since this
 * plugin provides those features: the Elementor One screens see the Pro tools as installed (no
 * «Upgrade» buttons, «Open» instead of «Go Pro»), the dashboard widget shows our version instead of
 * the upgrade link, and the admin URLs Elementor links to for Pro tools open our screens.
 * No licence is simulated: only the screens that decide between «upgrade» and «open» are told.
 */
final class Pro_Parity {
    /** Elementor Pro admin pages → our equivalents (resolved lazily, they need admin_url()). */
    private static function pages(): array {
        return [
            'e-custom-code'=>admin_url('edit.php?post_type=' . Dashboard::CODE),
            'e-custom-fonts'=>admin_url('edit.php?post_type=' . Dashboard::FONT),
            'e-custom-icons'=>admin_url('edit.php?post_type=' . Dashboard::ICON),
            'e-form-submissions'=>admin_url('edit.php?post_type=obx_submission'),
            'e-popups'=>admin_url('edit.php?post_type=elementor_library&page=obx-popups'),
            'elementor-theme-builder'=>Theme_Admin::url(),
        ];
    }

    public static function boot(): void {
        add_filter('rest_post_dispatch', [self::class, 'one_plugins'], 10, 3);
        add_filter('elementor/admin/homescreen_promotion_tier', static fn() => 'pro');
        add_filter('elementor/core/admin/homescreen', [self::class, 'homescreen']);
        add_filter('elementor/admin/dashboard_overview_widget/footer_actions', [self::class, 'footer_actions'], 99);
        add_action('elementor/admin/dashboard_overview_widget/after_version', [self::class, 'version']);
        add_filter('elementor/editor/panel/get_pro_details', [self::class, 'panel_details'], 99);
        add_filter('elementor_one/upgrade_available', '__return_false', 99);
        add_action('admin_init', [self::class, 'redirect_pro_pages'], 1);
        add_action('elementor/app/init', [self::class, 'app_routes']);
        add_action('admin_enqueue_scripts', [self::class, 'admin_styles']);
    }

    /** Elementor One (top bar and Home cards) decides «Upgrade» vs «Open» from this list. */
    public static function one_plugins($response, $server, $request) {
        if (!$response instanceof \WP_REST_Response || $request->get_method() !== 'GET' || $request->get_route() !== '/elementor-one/v1/plugins') { return $response; }
        $data = (array) $response->get_data();
        foreach ($data as $plugin) { if (is_array($plugin) && ($plugin['slug'] ?? '') === 'elementor-pro') { return $response; } }
        $data[] = ['slug'=>'elementor-pro','status'=>'active','version'=>VERSION,'newVersion'=>null,'isMigrated'=>false,'isConnected'=>false,'hasWpOnePackage'=>false];
        $response->set_data(array_values($data));
        return $response;
    }

    /** Pro users' Home screen content, without the remote sale banner. */
    public static function homescreen($data) {
        if (!is_array($data)) { return $data; }
        unset($data['sidebar_promotion_variants']);
        foreach (['get_started','top_with_licences'] as $key) {
            if (empty($data[$key]) || !is_array($data[$key])) { continue; }
            $paid = array_values(array_filter($data[$key], static fn($item) => is_array($item) && ($item['license'][0] ?? 'free') !== 'free'));
            if ($paid) { $data[$key] = $paid; }
        }
        return $data;
    }

    public static function footer_actions($actions): array {
        $actions = is_array($actions) ? $actions : [];
        unset($actions['go-pro']);
        return $actions;
    }

    public static function version(): void {
        echo '<span class="e-overview__version">OldBear Expansion v' . esc_html(VERSION) . '</span>';
    }

    public static function panel_details($details): array {
        $details = is_array($details) ? $details : [];
        $details['show_banner'] = false;
        return $details;
    }

    public static function redirect_pro_pages(): void {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
        $pages = self::pages();
        if ($page !== '' && isset($pages[$page]) && !defined('ELEMENTOR_PRO_VERSION')) {
            wp_safe_redirect($pages[$page]);
            exit;
        }
    }

    /** Elementor's app routes for the Theme Builder (#/site-editor…) open ours; the app page renders on its own. */
    public static function app_routes(): void {
        // The app view prints its <head> with wp_print_styles(), before its own scripts load.
        add_action('wp_print_styles', static function () {
            echo '<script>if(/^#\\/site-editor/.test(location.hash)){location.replace(' . wp_json_encode(Theme_Admin::url()) . ');}</script>'; // Static code, JSON-encoded URL.
        }, 0);
    }

    /** Notices Elementor only shows on sites without Pro. */
    public static function admin_styles(): void {
        wp_register_style('obx-parity', false, [], VERSION);
        wp_enqueue_style('obx-parity');
        wp_add_inline_style('obx-parity', '.e-notice[data-notice_id="role_manager_promote"],[data-test="header-upgrade-button"],#e-conversion-banner{display:none!important}');
    }
}
