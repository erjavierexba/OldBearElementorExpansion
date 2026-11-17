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

    public static function menu(): void {
        add_submenu_page('edit.php?post_type=elementor_library', 'Theme Builder', 'Theme Builder', 'edit_posts', self::PAGE, [self::class, 'page'], 1);
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

    public static function create(): void {
        $location = isset($_POST['location']) && is_string($_POST['location']) ? sanitize_key(wp_unslash($_POST['location'])) : '';
        if (!current_user_can('edit_posts') || !isset(Module::LOCATIONS[$location]) || !check_admin_referer('obx_new_template')) {
            wp_die('No tienes permiso para crear plantillas.', '', ['response'=>403]);
        }
        $class = Module::LOCATIONS[$location];
        $document = Elementor::$instance->documents->create('obx-' . $location, ['post_title'=>$class::get_title() . ' ' . wp_date('Y-m-d H:i'),'post_status'=>'draft']);
        if (is_wp_error($document)) { wp_die(esc_html($document->get_error_message())); }
        wp_safe_redirect($document->get_edit_url());
        exit;
    }

    public static function page(): void {
        if (!current_user_can('edit_posts')) { return; }
        $index = Locations::index();
        echo '<div class="wrap"><h1>Theme Builder</h1><p>Diseña con Elementor la cabecera, el pie, las entradas, los archivos y la página 404 de todo el sitio. Cada plantilla se muestra según sus condiciones de visualización (Ajustes de la plantilla → Condiciones de visualización).</p>';
        foreach (Module::LOCATIONS as $location => $class) {
            echo '<h2 style="margin-top:32px">' . esc_html($class::get_plural_title()) . '</h2>';
            $posts = get_posts(['post_type'=>'elementor_library','post_status'=>['publish','draft','private'],'posts_per_page'=>-1,'meta_key'=>'_elementor_template_type','meta_value'=>'obx-' . $location]);
            if ($posts) {
                echo '<table class="widefat striped" style="max-width:1000px"><thead><tr><th>Plantilla</th><th>Estado</th><th>Condiciones</th><th></th></tr></thead><tbody>';
                foreach ($posts as $post) {
                    $document = Elementor::$instance->documents->get($post->ID);
                    $conditions = $document instanceof Location_Document ? $document->get_conditions() : [];
                    $active = isset($index[$location][$post->ID]);
                    echo '<tr><td><strong>' . esc_html($post->post_title) . '</strong></td><td>' . esc_html($active ? 'Activa' : ($post->post_status === 'publish' ? 'Publicada' : 'Borrador')) . '</td><td>' . esc_html(Conditions::describe($conditions, $location)) . '</td><td>';
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
        echo '<p style="margin-top:32px"><em>Necesita un tema clásico (por ejemplo Hello Elementor). Los temas de bloques no usan header.php ni footer.php.</em></p></div>';
    }
}
