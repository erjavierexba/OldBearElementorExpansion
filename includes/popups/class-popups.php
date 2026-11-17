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

    private static function display(int $id, array $settings): array {
        $display = get_post_meta($id, self::DISPLAY_META, true);
        $display = is_string($display) ? json_decode($display, true) : $display;
        if (!is_array($display)) { $display = []; }
        $display['triggers'] = is_array($display['triggers'] ?? null) ? $display['triggers'] : [];
        $display['timing'] = is_array($display['timing'] ?? null) ? $display['timing'] : [];
        if (!$display['triggers']) {
            $display['triggers'] = ['page_load'=>($settings['obx_page_load'] ?? 'yes'),'page_load_delay'=>$settings['obx_page_load_delay'] ?? 0];
        }
        $display['selector'] = (string) ($settings['open_by_selector'] ?? '');
        $display['preventCloseOverlay'] = ($settings['obx_prevent_close_overlay'] ?? '') === 'yes' || ($settings['prevent_close_on_background_click'] ?? '') === 'yes';
        $display['preventCloseEsc'] = ($settings['obx_prevent_close_esc'] ?? '') === 'yes' || ($settings['prevent_close_on_esc_key'] ?? '') === 'yes';
        $display['preventScroll'] = ($settings['obx_prevent_scroll'] ?? '') === 'yes' || ($settings['prevent_scroll'] ?? '') === 'yes';
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
            $width = is_array($s['width'] ?? null) ? $s['width'] : ['size'=>640,'unit'=>'px'];
            $height = is_array($s['height'] ?? null) ? $s['height'] : [];
            $style = '--obx-popup-width:' . (float) ($width['size'] ?? 640) . preg_replace('/[^a-z%]/', '', (string) ($width['unit'] ?? 'px')) . ';';
            if (($s['height_type'] ?? 'auto') === 'custom' && !empty($height['size'])) { $style .= '--obx-popup-height:' . (float) $height['size'] . preg_replace('/[^a-z%]/', '', (string) ($height['unit'] ?? 'px')) . ';'; }
            $classes = ['obx-popup','obx-popup--h-' . sanitize_html_class((string) ($s['horizontal_position'] ?? 'center')),'obx-popup--v-' . sanitize_html_class((string) ($s['vertical_position'] ?? 'center'))];
            if (($s['height_type'] ?? '') === 'fit_to_screen') { $classes[] = 'obx-popup--fit'; }
            $display = self::display($id, $s);
            echo '<div id="elementor-popup-modal-' . esc_attr((string) $id) . '" class="' . esc_attr(implode(' ', $classes)) . '" data-obx-popup="' . esc_attr((string) $id) . '" data-settings="' . esc_attr((string) wp_json_encode($display)) . '" aria-hidden="true">';
            if (($s['overlay'] ?? 'yes') === 'yes') { echo '<div class="obx-popup__overlay" data-popup-close></div>'; }
            echo '<div class="obx-popup__dialog animated ' . esc_attr((string) ($s['entrance_animation'] ?? 'fadeIn')) . '" role="dialog" aria-modal="true" aria-label="' . esc_attr(get_the_title($id)) . '" style="' . esc_attr($style) . '">';
            if (($s['close_button'] ?? 'yes') === 'yes') { echo '<button type="button" class="obx-popup__close" data-popup-close aria-label="Cerrar">&times;</button>'; }
            echo '<div class="obx-popup__content">' . Elementor::$instance->frontend->get_builder_content_for_display($id, true) . '</div></div></div>'; // Elementor output.
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
        echo '<div class="wrap"><h1>Ventanas emergentes</h1><p>Diseña ventanas con Elementor y decide dónde aparecen mediante sus condiciones y disparadores.</p>';
        if ($posts) { echo '<table class="widefat striped"><thead><tr><th>Plantilla</th><th>Estado</th><th></th></tr></thead><tbody>'; foreach ($posts as $post) { echo '<tr><td>' . esc_html($post->post_title) . '</td><td>' . esc_html($post->post_status) . '</td><td><a class="button" href="' . esc_url(admin_url('post.php?post=' . $post->ID . '&action=elementor')) . '">Editar con Elementor</a></td></tr>'; } echo '</tbody></table>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:16px"><input type="hidden" name="action" value="obx_new_popup">'; wp_nonce_field('obx_new_popup'); echo '<button class="button button-primary">Añadir ventana emergente</button></form></div>';
    }
}
