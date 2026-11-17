<?php
namespace OldBearExpansion\ThemeBuilder;

use Elementor\Plugin as Elementor;

if (!defined('ABSPATH')) { exit; }

final class Admin {
    public const PAGE = 'obx-theme-builder';

    public static function boot(): void {
        add_action('admin_menu', [self::class, 'menu'], 30);
        add_action('admin_post_obx_new_template', [self::class, 'create']);
        add_filter('manage_elementor_library_posts_columns', [self::class, 'columns']);
        add_action('manage_elementor_library_posts_custom_column', [self::class, 'column'], 10, 2);
    }

    /** With clean mode, Elementor's own «Theme Builder» menu entry points here, so ours stays hidden. */
    private static function hidden(): bool {
        return class_exists(\OldBearExpansion\Clean\Clean_Mode::class) && \OldBearExpansion\Clean\Clean_Mode::enabled();
    }

    public static function url(): string {
        return self::hidden() ? admin_url('admin.php?page=' . self::PAGE) : admin_url('edit.php?post_type=elementor_library&page=' . self::PAGE);
    }

    public static function menu(): void {
        $hook = add_submenu_page(self::hidden() ? '' : 'edit.php?post_type=elementor_library', 'Theme Builder', 'Theme Builder', 'edit_posts', self::PAGE, [self::class, 'page'], 1);
        if ($hook && self::hidden()) {
            // A page without parent has no title and no highlighted menu: show it under Elementor's «Theme Builder».
            add_action('load-' . $hook, static function () {
                $GLOBALS['title'] = 'Theme Builder'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- admin page title.
                add_filter('parent_file', static fn() => 'elementor-home', 999);
                add_filter('submenu_file', static fn() => self::url(), 999);
            });
        }
    }

    public static function columns(array $columns): array {
        $columns['obx_conditions'] = 'Condiciones';
        return $columns;
    }

    public static function column(string $column, int $id): void {
        if ($column !== 'obx_conditions') { return; }
        $document = Elementor::$instance->documents->get($id);
        if ($document instanceof Location_Document) {
            echo esc_html(Conditions::describe($document->get_conditions(), $document::LOCATION));
        }
    }

    /** New template from a location (this page) or a template type (Elementor's app, through GET). */
    public static function create(): void {
        $read = static fn(string $key) => isset($_REQUEST[$key]) && is_string($_REQUEST[$key]) ? sanitize_key(wp_unslash($_REQUEST[$key])) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked below.
        $location = $read('location');
        $type = $read('type');
        $class = $type !== '' ? (Module::TYPES[$type] ?? null) : ($location === 'loop-item' ? Loop_Item_Document::class : (Module::LOCATIONS[$location] ?? null));
        if ($class === Popup_Document::class) { $class = null; }
        if (!current_user_can('edit_posts') || !$class || !check_admin_referer('obx_new_template')) {
            wp_die('No tienes permiso para crear plantillas.', '', ['response'=>403]);
        }
        $location = $class::LOCATION;
        $document = Elementor::$instance->documents->create($class::TYPE, ['post_title'=>$class::get_title() . ' ' . wp_date('Y-m-d H:i'),'post_status'=>'draft']);
        if (is_wp_error($document)) { wp_die(esc_html($document->get_error_message())); }
        if ($class::HAS_CONDITIONS) { Location_Document::store_conditions((int) $document->get_main_id(), [['mode'=>'include','rule'=>self::default_rule($class),'ids'=>[]]]); }
        $url = $document->get_edit_url();
        if (!empty($_GET['obx_top'])) { // phpcs:ignore WordPress.Security.NonceVerification -- checked above.
            // Opened inside Elementor's app frame (editor modal): the editor takes the whole window.
            nocache_headers();
            echo '<!doctype html><meta charset="utf-8"><script>(window.top||window).location.replace(' . wp_json_encode($url) . ');</script>'; // JSON-encoded URL.
            exit;
        }
        wp_safe_redirect($url);
        exit;
    }

    /** First condition of a new template: the obvious one for its type. */
    public static function default_rule(string $class): string {
        return match ($class::TYPE) {
            'single-page'=>'singular/page',
            'search-results'=>'archive/search',
            'product'=>'product',
            'product-archive'=>post_type_exists('product') ? 'product_archive' : 'archive',
            default=>Conditions::default_rule($class::LOCATION),
        };
    }

    public static function page(): void {
        if (!current_user_can('edit_posts')) { return; }
        $index = Locations::index();
        echo '<div class="wrap"><h1>Theme Builder</h1><p>Diseña con Elementor la cabecera, el pie, las entradas, los archivos y la página 404 de todo el sitio. Cada plantilla se muestra según sus condiciones de visualización (Ajustes de la plantilla → Condiciones de visualización).</p>';
        foreach (Module::LOCATIONS as $location => $class) {
            echo '<h2 style="margin-top:32px">' . esc_html($class::get_plural_title()) . '</h2>';
            $posts = get_posts(['post_type'=>'elementor_library','post_status'=>['publish','draft','private'],'posts_per_page'=>-1,'meta_query'=>[['key'=>'_elementor_template_type','value'=>Module::types_for($location),'compare'=>'IN']]]);
            if ($posts) {
                echo '<table class="widefat striped" style="max-width:1000px"><thead><tr><th>Plantilla</th><th>Estado</th><th>Condiciones</th><th></th></tr></thead><tbody>';
                foreach ($posts as $post) {
                    $document = Elementor::$instance->documents->get($post->ID);
                    $conditions = $document instanceof Location_Document ? $document->get_conditions() : [];
                    $active = isset($index[$location][$post->ID]);
                    $kind = $document ? $document::get_title() : '';
                    echo '<tr><td><strong>' . esc_html($post->post_title) . '</strong>' . ($kind && $kind !== $class::get_title() ? ' <span style="opacity:.7">· ' . esc_html($kind) . '</span>' : '') . '</td><td>' . esc_html($active ? 'Activa' : ($post->post_status === 'publish' ? 'Publicada' : 'Borrador')) . '</td><td>' . esc_html(Conditions::describe($conditions, $location)) . '</td><td>';
                    if (current_user_can('edit_post', $post->ID)) {
                        echo '<a class="button button-primary" href="' . esc_url(admin_url('post.php?post=' . $post->ID . '&action=elementor')) . '">Editar con Elementor</a>';
                    }
                    echo '</td></tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>Todavía no hay ninguna. El tema activo se encarga de esta parte.</p>';
            }
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:12px"><input type="hidden" name="action" value="obx_new_template"><input type="hidden" name="location" value="' . esc_attr($location) . '">';
            wp_nonce_field('obx_new_template');
            echo '<button class="button" type="submit">Añadir ' . esc_html(mb_strtolower($class::get_title())) . '</button></form>';
        }
        echo '<h2 style="margin-top:32px">Elementos de bucle</h2><p>Diseña una tarjeta reutilizable para Loop Grid y Loop Carousel. Dentro puedes usar widgets de entrada y etiquetas dinámicas.</p>';
        $loops = get_posts(['post_type'=>'elementor_library','post_status'=>['publish','draft','private'],'posts_per_page'=>-1,'meta_key'=>'_elementor_template_type','meta_value'=>'loop-item']);
        if ($loops) { echo '<table class="widefat striped" style="max-width:1000px"><tbody>'; foreach ($loops as $loop) { echo '<tr><td><strong>' . esc_html($loop->post_title) . '</strong></td><td>' . esc_html($loop->post_status) . '</td><td><a class="button button-primary" href="' . esc_url(admin_url('post.php?post=' . $loop->ID . '&action=elementor')) . '">Editar con Elementor</a></td></tr>'; } echo '</tbody></table>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:12px"><input type="hidden" name="action" value="obx_new_template"><input type="hidden" name="location" value="loop-item">'; wp_nonce_field('obx_new_template'); echo '<button class="button" type="submit">Añadir elemento de bucle</button></form>';
        \OldBearExpansion\Converter::box();
        echo '<p style="margin-top:32px"><em>Necesita un tema clásico (por ejemplo Hello Elementor). Los temas de bloques no usan header.php ni footer.php.</em></p></div>';
    }
}
