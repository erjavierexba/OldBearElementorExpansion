<?php
namespace OldBearExpansion;

use OldBearExpansion\Widgets\Base;

if (!defined('ABSPATH')) { exit; }

/**
 * Rewrites stored Elementor data to Elementor Pro's format: widgets saved with our own names
 * (obx-*) take the Pro name, and settings saved by earlier versions take Pro's keys.
 * Rendering already translates on the fly; this is only needed before switching to Elementor Pro.
 */
final class Converter {
    public const ACTION = 'obx_convert_to_pro';

    public static function boot(): void {
        add_action('admin_post_' . self::ACTION, [self::class, 'handle']);
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('obx convert', [self::class, 'cli'], ['shortdesc'=>'Convierte los datos de OldBear al formato de Elementor Pro.','synopsis'=>[['type'=>'flag','name'=>'dry-run','optional'=>true,'description'=>'Solo cuenta lo que cambiaría.']]]);
        }
    }

    /** @return array<string,class-string<Base>> saved widget name => class */
    private static function classes(): array {
        \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
        $map = [];
        foreach (Base::registry() as $class) { foreach ($class::names() as $name) { $map[$name] = $class; } }
        return $map;
    }

    /** Converts an element tree in place. @return int elements changed */
    public static function convert_elements(array &$elements, ?array $classes = null): int {
        $classes ??= self::classes();
        $changed = 0;
        foreach ($elements as &$element) {
            if (!is_array($element)) { continue; }
            if (($element['elType'] ?? '') === 'widget' && isset($classes[$element['widgetType'] ?? ''])) {
                $name = (string) $element['widgetType'];
                $class = $classes[$name];
                $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
                $new_settings = $class::convert($name, $settings);
                $new_name = $class::PRO !== '' && $name === $class::NAME ? $class::PRO : $name;
                if ($new_settings !== $settings || $new_name !== $name) {
                    $element['settings'] = $new_settings ?: (object) [];
                    $element['widgetType'] = $new_name;
                    $changed++;
                }
            }
            if (!empty($element['elements']) && is_array($element['elements'])) { $changed += self::convert_elements($element['elements'], $classes); }
        }
        unset($element);
        return $changed;
    }

    /** @return array{posts:int,elements:int} */
    public static function run(bool $dry_run = false): array {
        global $wpdb;
        $classes = self::classes();
        $ids = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE '%widgetType%'");
        $result = ['posts'=>0,'elements'=>0];
        foreach (array_map('intval', $ids) as $id) {
            $raw = get_post_meta($id, '_elementor_data', true);
            $data = is_string($raw) ? json_decode($raw, true) : $raw;
            if (!is_array($data)) { continue; }
            $changed = self::convert_elements($data, $classes);
            if (!$changed) { continue; }
            $result['posts']++;
            $result['elements'] += $changed;
            if (!$dry_run) {
                update_metadata('post', $id, '_elementor_data', wp_slash((string) wp_json_encode($data)));
                delete_post_meta($id, '_elementor_css');
                delete_post_meta($id, '_elementor_element_cache');
            }
        }
        if (!$dry_run && $result['posts'] && isset(\Elementor\Plugin::$instance->files_manager)) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
        return $result;
    }

    public static function handle(): void {
        if (!current_user_can('manage_options')) { wp_die('No tienes permiso para hacer esto.', 403); }
        check_admin_referer(self::ACTION);
        $result = self::run();
        wp_safe_redirect(add_query_arg(['page'=>ThemeBuilder\Admin::PAGE,'obx_converted'=>$result['elements'],'obx_posts'=>$result['posts']], admin_url('admin.php')));
        exit;
    }

    /** Admin box shown on the Theme Builder screen. */
    public static function box(): void {
        if (!current_user_can('manage_options')) { return; }
        echo '<h2 style="margin-top:40px">Pasar a Elementor Pro</h2>';
        if (isset($_GET['obx_converted'])) {
            printf('<div class="notice notice-success inline"><p>Listo: %d elementos actualizados en %d documentos.</p></div>', absint($_GET['obx_converted']), absint($_GET['obx_posts'] ?? 0));
        }
        $pending = self::run(true);
        echo '<p style="max-width:760px">Las plantillas ya usan los tipos y condiciones de Elementor Pro, y los widgets se guardan con sus nombres y ajustes. Este botón actualiza los datos guardados con versiones anteriores de OldBear para que Elementor Pro los abra tal cual.</p>';
        echo '<p>' . ($pending['elements'] ? sprintf('Pendientes: %d elementos en %d documentos.', $pending['elements'], $pending['posts']) : 'No hay nada pendiente: todo está en formato de Elementor Pro.') . '</p>';
        if ($pending['elements']) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">';
            wp_nonce_field(self::ACTION);
            echo '<button class="button button-primary" type="submit">Convertir al formato de Elementor Pro</button></form>';
        }
        echo '<p style="max-width:760px"><em>Después de activar Elementor Pro, abre Plantillas → Theme Builder y guarda las condiciones de cada plantilla una vez para que Pro regenere su caché de condiciones.</em></p>';
    }

    public static function cli(array $args, array $assoc): void {
        $dry = !empty($assoc['dry-run']);
        $result = self::run($dry);
        \WP_CLI::success(sprintf('%s %d elementos en %d documentos.', $dry ? 'Se convertirían' : 'Convertidos', $result['elements'], $result['posts']));
    }
}
