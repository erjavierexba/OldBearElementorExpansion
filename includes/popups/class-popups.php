<?php
namespace OldBearExpansion\Popups;

use Elementor\Plugin as Elementor;
use OldBearExpansion\Plugin;
use OldBearExpansion\ThemeBuilder\Conditions;
use OldBearExpansion\ThemeBuilder\Location_Document;
use OldBearExpansion\ThemeBuilder\Popup_Document;
use const OldBearExpansion\VERSION;

if (!defined('ABSPATH')) { exit; }

/** Popup templates, conditions, triggers and Elementor action links. */
final class Popups {
    public const DISPLAY_META = '_elementor_popup_display_settings';
    private static array $queued = [];

    public static function boot(): void {
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
        add_action('wp_footer', [self::class, 'render'], 8);
        add_action('elementor/document/after_save', [self::class, 'save']);
        add_action('admin_menu', [self::class, 'menu'], 31);
        add_action('admin_post_obx_new_popup', [self::class, 'create']);
    }

    public static function assets(): void {
        wp_register_script('obx-popups', Plugin::url('assets/js/popups.js'), [], VERSION, ['strategy'=>'defer','in_footer'=>true]);
    }

    public static function enqueue(int $id): void {
        if ($id > 0 && get_post_type($id) === 'elementor_library' && get_post_status($id) === 'publish') { self::$queued[$id] = true; }
    }

    public static function save($document): void {
        if (!$document instanceof Popup_Document) { return; }
        $s = $document->get_settings();
        $display = [
            'triggers'=>[
                'page_load'=>($s['obx_page_load'] ?? '') === 'yes' ? 'yes' : '',
                'page_load_delay'=>max(0, (float) ($s['obx_page_load_delay'] ?? 0)),
                'scrolling'=>($s['obx_scroll'] ?? '') === 'yes' ? 'yes' : '',
                'scrolling_direction'=>'down',
                'scrolling_offset'=>max(1, min(100, (int) ($s['obx_scroll_percent'] ?? 50))),
                'exit_intent'=>($s['obx_exit_intent'] ?? '') === 'yes' ? 'yes' : '',
                'inactivity'=>($s['obx_inactivity'] ?? '') === 'yes' ? 'yes' : '',
                'inactivity_time'=>max(1, (int) ($s['obx_inactivity_time'] ?? 30)),
            ],
            'timing'=>['times'=>!empty($s['obx_show_times']) ? 'yes' : '','times_count'=>max(0, (int) ($s['obx_show_times'] ?? 0))],
        ];
        update_post_meta((int) $document->get_main_id(), self::DISPLAY_META, $display);
    }

    /** Saved page settings, including legacy keys (obx_*, open_by_selector) no longer registered as controls. */
    private static function raw(int $id): array {
        $raw = get_post_meta($id, '_elementor_page_settings', true);
        return is_array($raw) ? $raw : [];
    }

    private static function display(int $id, array $settings): array {
        $raw = self::raw($id) + $settings;
        $display = get_post_meta($id, self::DISPLAY_META, true);
        $display = is_string($display) ? json_decode($display, true) : $display;
        if (!is_array($display)) { $display = []; }
        $display['triggers'] = is_array($display['triggers'] ?? null) ? $display['triggers'] : [];
        $display['timing'] = is_array($display['timing'] ?? null) ? $display['timing'] : [];
        if (!$display['triggers']) {
            $display['triggers'] = ['page_load'=>($raw['obx_page_load'] ?? 'yes'),'page_load_delay'=>$raw['obx_page_load_delay'] ?? 0];
        }
        $yes = static fn(string ...$keys) => (bool) array_filter($keys, static fn($k) => ($raw[$k] ?? '') === 'yes');
        $num = static fn(string $key) => max(0, (float) ($settings[$key] ?? 0));
        $display['selector'] = trim((string) (($settings['open_selector'] ?? '') ?: ($raw['open_by_selector'] ?? '')));
        $display['preventCloseOverlay'] = $yes('obx_prevent_close_overlay', 'prevent_close_on_background_click');
        $display['preventCloseEsc'] = $yes('prevent_close_on_esc_key', 'obx_prevent_close_esc');
        $display['preventScroll'] = $yes('prevent_scroll', 'obx_prevent_scroll');
        $display['avoidMultiple'] = ($settings['avoid_multiple_popups'] ?? '') === 'yes';
        $display['a11y'] = ($settings['a11y_navigation'] ?? 'yes') === 'yes';
        $display['closeDelay'] = $num('close_button_delay');
        $display['closeAuto'] = $num('close_automatically');
        $display['animation'] = [];
        foreach (['entrance_animation','exit_animation'] as $key) {
            foreach (['', '_tablet', '_mobile'] as $suffix) { $display['animation'][$key . $suffix] = sanitize_html_class((string) ($settings[$key . $suffix] ?? '')); }
        }
        return $display;
    }

    public static function render(): void {
        if (is_admin() || wp_doing_ajax()) { return; }
        $ids = get_posts(['post_type'=>'elementor_library','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','meta_key'=>'_elementor_template_type','meta_value'=>'popup']);
        foreach ($ids as $id) {
            $document = Elementor::$instance->documents->get($id);
            if (!$document instanceof Popup_Document) { continue; }
            if (Conditions::evaluate($document->get_conditions()) > 0) { self::$queued[(int) $id] = true; }
        }
        if (!self::$queued) { return; }
        wp_enqueue_script('obx-popups');
        foreach (array_keys(self::$queued) as $id) {
            $document = Elementor::$instance->documents->get($id);
            if (!$document) { continue; }
            $s = $document->get_settings_for_display();
            $display = self::display($id, $s);
            foreach (array_unique(array_filter($display['animation'])) as $animation) { self::animation_style($animation); }
            $classes = ['obx-popup', 'elementor-popup-modal'];
            if (($s['close_button_position'] ?? '') === 'outside') { $classes[] = 'obx-popup--close-outside'; }
            foreach (preg_split('/\s+/', (string) ($s['classes'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $class) { $classes[] = sanitize_html_class($class); }
            echo '<div id="elementor-popup-modal-' . esc_attr((string) $id) . '" class="' . esc_attr(implode(' ', $classes)) . '" data-obx-popup="' . esc_attr((string) $id) . '" data-settings="' . esc_attr((string) wp_json_encode($display)) . '" aria-hidden="true">';
            if (($s['overlay'] ?? 'yes') === 'yes') { echo '<div class="obx-popup__overlay" data-popup-close></div>'; }
            echo '<div class="obx-popup__dialog" role="dialog" aria-modal="true" aria-label="' . esc_attr(get_the_title($id)) . '" tabindex="-1">';
            if (($s['close_button'] ?? 'yes') === 'yes') { echo '<button type="button" class="obx-popup__close" data-popup-close aria-label="Cerrar"' . ($display['closeDelay'] ? ' hidden' : '') . '>&times;</button>'; }
            echo '<div class="obx-popup__content">' . Elementor::$instance->frontend->get_builder_content_for_display($id, true) . '</div></div></div>'; // Elementor output.
        }
    }

    /** Elementor ships one stylesheet per entrance animation; enqueue the ones popups and off-canvas widgets use. */
    public static function animation_style(string $animation): void {
        $handle = 'e-animation-' . $animation;
        if (wp_style_is($handle, 'registered')) { wp_enqueue_style($handle); return; }
        if (defined('ELEMENTOR_ASSETS_PATH') && is_file(ELEMENTOR_ASSETS_PATH . 'lib/animations/styles/' . $animation . '.min.css')) {
            wp_enqueue_style($handle, ELEMENTOR_ASSETS_URL . 'lib/animations/styles/' . $animation . '.min.css', [], ELEMENTOR_VERSION);
        }
    }

    public static function menu(): void {
        add_submenu_page('edit.php?post_type=elementor_library', 'Ventanas emergentes', 'Ventanas emergentes', 'edit_posts', 'obx-popups', [self::class, 'page'], 2);
    }

    public static function create(): void {
        if (!current_user_can('edit_posts') || !check_admin_referer('obx_new_popup')) { wp_die('No tienes permiso.', '', ['response'=>403]); }
        $document = Elementor::$instance->documents->create('popup', ['post_title'=>'Ventana emergente ' . wp_date('Y-m-d H:i'),'post_status'=>'draft']);
        if (is_wp_error($document)) { wp_die(esc_html($document->get_error_message())); }
        Location_Document::store_conditions((int) $document->get_main_id(), [['mode'=>'include','rule'=>'general','ids'=>[]]]);
        wp_safe_redirect($document->get_edit_url()); exit;
    }

    public static function page(): void {
        if (!current_user_can('edit_posts')) { return; }
        $posts = get_posts(['post_type'=>'elementor_library','post_status'=>['publish','draft','private'],'posts_per_page'=>-1,'meta_key'=>'_elementor_template_type','meta_value'=>'popup']);
        echo '<div class="wrap">' . \OldBearExpansion\Admin\Branding::heading('Ventanas emergentes') . '<p>Diseña ventanas con Elementor y decide dónde aparecen mediante sus condiciones y disparadores.</p>';
        if ($posts) { echo '<table class="widefat striped"><thead><tr><th>Plantilla</th><th>Estado</th><th></th></tr></thead><tbody>'; foreach ($posts as $post) { echo '<tr><td>' . esc_html($post->post_title) . '</td><td>' . esc_html($post->post_status) . '</td><td><a class="button" href="' . esc_url(admin_url('post.php?post=' . $post->ID . '&action=elementor')) . '">Editar con Elementor</a></td></tr>'; } echo '</tbody></table>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:16px"><input type="hidden" name="action" value="obx_new_popup">'; wp_nonce_field('obx_new_popup'); echo '<button class="button button-primary">Añadir ventana emergente</button></form></div>';
    }
}
