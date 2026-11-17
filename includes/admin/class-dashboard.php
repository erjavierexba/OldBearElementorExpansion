<?php
namespace OldBearExpansion\Admin;

use OldBearExpansion\Popups\Popups;
use OldBearExpansion\ThemeBuilder\Admin as Theme_Admin;
use const OldBearExpansion\DIR;
use const OldBearExpansion\VERSION;

if (!defined('ABSPATH')) { exit; }

/** Elementor Pro-compatible dashboard tools: fonts, icons, custom code and role restrictions. */
final class Dashboard {
    public const FONT = 'elementor_font';
    public const ICON = 'elementor_icons';
    public const CODE = 'elementor_snippet';

    public static function boot(): void {
        add_action('init', [self::class, 'post_types'], 4);
        add_action('add_meta_boxes', [self::class, 'meta_boxes']);
        add_action('post_edit_form_tag', [self::class, 'form_encoding']);
        add_action('save_post', [self::class, 'save'], 10, 2);
        add_action('elementor/editor-one/menu/register', [self::class, 'editor_menu'], 30);
        add_action('wp_dashboard_setup', [self::class, 'dashboard_widget'], 100);
        add_action('elementor/role/restrictions/controls', [self::class, 'role_control'], 5, 2);

        add_filter('elementor/fonts/groups', [self::class, 'font_groups']);
        add_filter('elementor/fonts/additional_fonts', [self::class, 'fonts']);
        add_filter('elementor/icons_manager/additional_tabs', [self::class, 'icon_tabs']);
        add_action('wp_head', [self::class, 'font_styles'], 5);
        add_action('elementor/editor/after_enqueue_styles', [self::class, 'font_styles']);
        add_action('wp_ajax_obx_icons_css', [self::class, 'icon_css']);
        add_action('wp_ajax_nopriv_obx_icons_css', [self::class, 'icon_css']);

        add_action('wp_head', static fn() => self::print_code('head'), 1);
        add_action('wp_body_open', static fn() => self::print_code('body_start'), 1);
        add_action('wp_footer', static fn() => self::print_code('body_end'), 1000);
    }

    private static function capabilities(): array {
        return [
            'edit_post'=>'manage_options','read_post'=>'manage_options','delete_post'=>'manage_options','edit_posts'=>'manage_options',
            'edit_others_posts'=>'manage_options','publish_posts'=>'manage_options','read_private_posts'=>'manage_options',
            'delete_posts'=>'manage_options','delete_private_posts'=>'manage_options','delete_published_posts'=>'manage_options',
            'delete_others_posts'=>'manage_options','edit_private_posts'=>'manage_options','edit_published_posts'=>'manage_options',
            'create_posts'=>'manage_options',
        ];
    }

    public static function post_types(): void {
        $common = ['public'=>false,'show_ui'=>true,'show_in_menu'=>false,'show_in_rest'=>false,'exclude_from_search'=>true,
            'supports'=>['title'],'map_meta_cap'=>false,'capabilities'=>self::capabilities(),'rewrite'=>false,'query_var'=>false];
        register_post_type(self::FONT, $common + ['labels'=>self::labels('Fuentes personalizadas', 'Fuente personalizada', 'Añadir fuente')]);
        register_post_type(self::ICON, $common + ['labels'=>self::labels('Iconos personalizados', 'Icono personalizado', 'Añadir icono')]);
        register_post_type(self::CODE, $common + ['labels'=>self::labels('Código personalizado', 'Código personalizado', 'Añadir código')]);
    }

    private static function labels(string $plural, string $singular, string $add): array {
        return ['name'=>$plural,'singular_name'=>$singular,'menu_name'=>$plural,'all_items'=>$plural,'add_new'=>$add,
            'add_new_item'=>$add,'edit_item'=>'Editar ' . mb_strtolower($singular),'new_item'=>$add,'search_items'=>'Buscar',
            'not_found'=>'Todavía no hay elementos.','item_published'=>$singular . ' publicado.','item_updated'=>$singular . ' actualizado.'];
    }

    public static function editor_menu($provider): void {
        if (!is_object($provider) || !method_exists($provider, 'register_menu') || !interface_exists('\Elementor\Core\Admin\EditorOneMenu\Interfaces\Menu_Item_Interface')) { return; }
        require_once DIR . '/includes/admin/class-menu-items.php';
        $custom = 'elementor-editor-custom-elements';
        $templates = 'elementor-editor-templates';
        $provider->register_menu(new Menu_Parent('elementor-custom-elements', 'Elementos personalizados', $custom, 'adjustments', 70));
        $provider->register_menu(new Menu_Child('edit.php?post_type=' . self::FONT, 'Fuentes', admin_url('edit.php?post_type=' . self::FONT), $custom, 10));
        $provider->register_menu(new Menu_Child('edit.php?post_type=' . self::ICON, 'Iconos', admin_url('edit.php?post_type=' . self::ICON), $custom, 20));
        $provider->register_menu(new Menu_Child('edit.php?post_type=' . self::CODE, 'Código', admin_url('edit.php?post_type=' . self::CODE), $custom, 30));
        $provider->register_menu(new Menu_Child('obx-theme-builder', 'Theme Builder', Theme_Admin::url(), $templates, 20, 'edit_posts'));
        $provider->register_menu(new Menu_Child('obx-popups', 'Popups', admin_url('edit.php?post_type=elementor_library&page=obx-popups'), $templates, 50, 'edit_posts'));
    }

    /** Replace Elementor's promotional dashboard card with useful site-management shortcuts. */
    public static function dashboard_widget(): void {
        remove_meta_box('e-dashboard-overview', 'dashboard', 'normal');
        wp_add_dashboard_widget('obx-dashboard-overview', 'OldBear Expansion · Elementor', [self::class, 'render_dashboard']);

        // Keep the OldBear panel at the top of the main dashboard column.
        global $wp_meta_boxes;
        $core = &$wp_meta_boxes['dashboard']['normal']['core'];
        if (is_array($core) && isset($core['obx-dashboard-overview'])) {
            $widget = $core['obx-dashboard-overview'];
            unset($core['obx-dashboard-overview']);
            $core = ['obx-dashboard-overview' => $widget] + $core;
        }
    }

    public static function render_dashboard(): void {
        $create_url = admin_url('post-new.php?post_type=page');
        if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->documents) && method_exists(\Elementor\Plugin::$instance->documents, 'get_create_new_post_url')) {
            $elementor_url = \Elementor\Plugin::$instance->documents->get_create_new_post_url('page');
            if (is_string($elementor_url) && $elementor_url !== '') { $create_url = $elementor_url; }
        }

        $links = [
            ['Theme Builder', Theme_Admin::url(), 'Cabecera, pie, entradas y archivos'],
            ['Popups', admin_url('edit.php?post_type=elementor_library&page=obx-popups'), 'Ventanas emergentes y condiciones'],
            ['Envíos', admin_url('edit.php?post_type=obx_submission'), 'Respuestas de formularios'],
            ['Fuentes', admin_url('edit.php?post_type=' . self::FONT), 'Tipografías personalizadas'],
            ['Iconos', admin_url('edit.php?post_type=' . self::ICON), 'Biblioteca SVG propia'],
            ['Código', admin_url('edit.php?post_type=' . self::CODE), 'CSS, HTML y JavaScript'],
            ['Administrador de roles', admin_url('admin.php?page=elementor-role-manager'), 'Permisos del editor'],
            ['Plantillas', admin_url('edit.php?post_type=elementor_library'), 'Biblioteca de Elementor'],
        ];

        echo '<style>#obx-dashboard-overview .inside{margin:0;padding:0}.obx-dashboard-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 20px;background:#f5f3ff;border-bottom:1px solid #ddd6fe}.obx-dashboard-head p{margin:3px 0 0;color:#50575e}.obx-dashboard-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;background:#e2e4e7}.obx-dashboard-link{display:block;padding:14px 16px;background:#fff;text-decoration:none}.obx-dashboard-link strong{display:block;color:#1d2327;font-size:14px}.obx-dashboard-link span{display:block;color:#646970;margin-top:4px}.obx-dashboard-recent{padding:14px 20px;border-top:1px solid #e2e4e7}.obx-dashboard-recent h3{margin:0 0 9px}.obx-dashboard-recent ul{margin:0}.obx-dashboard-recent li{margin:0 0 7px}@media(max-width:782px){.obx-dashboard-head{align-items:flex-start;flex-direction:column}.obx-dashboard-grid{grid-template-columns:1fr}}</style>';
        echo '<div class="obx-dashboard-head"><div><strong>Elementor ' . esc_html(defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '') . ' + OldBear ' . esc_html(VERSION) . '</strong><p>Herramientas de diseño y contenido, sin promociones.</p></div><a class="button button-primary" href="' . esc_url($create_url) . '">Crear página con Elementor</a></div>';
        echo '<div class="obx-dashboard-grid">';
        foreach ($links as [$label, $url, $description]) {
            echo '<a class="obx-dashboard-link" href="' . esc_url($url) . '"><strong>' . esc_html($label) . '</strong><span>' . esc_html($description) . '</span></a>';
        }
        echo '</div>';

        $recent = get_posts(['post_type'=>'any','post_status'=>['publish','draft','pending','private'],'posts_per_page'=>5,'orderby'=>'modified','order'=>'DESC','meta_key'=>'_elementor_edit_mode','meta_value'=>'builder']);
        echo '<div class="obx-dashboard-recent"><h3>Editado recientemente con Elementor</h3>';
        if (!$recent) { echo '<p>Todavía no hay contenido reciente.</p>'; }
        else {
            echo '<ul>';
            foreach ($recent as $post) {
                $url = admin_url('post.php?post=' . $post->ID . '&action=elementor');
                echo '<li><a href="' . esc_url($url) . '">' . esc_html(get_the_title($post) ?: '(Sin título)') . '</a> <span class="description">· ' . esc_html(get_post_type_object($post->post_type)->labels->singular_name ?? $post->post_type) . '</span></li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }

    public static function meta_boxes(): void {
        add_meta_box('obx-font-files', 'Archivo y variante', [self::class, 'font_box'], self::FONT, 'normal', 'high');
        add_meta_box('obx-icon-file', 'Archivo SVG', [self::class, 'icon_box'], self::ICON, 'normal', 'high');
        add_meta_box('obx-code-editor', 'Código', [self::class, 'code_box'], self::CODE, 'normal', 'high');
        add_meta_box('obx-code-settings', 'Ubicación y condición', [self::class, 'code_settings_box'], self::CODE, 'side', 'default');
    }

    public static function form_encoding(): void {
        $screen = get_current_screen();
        if ($screen && in_array($screen->post_type, [self::FONT, self::ICON], true)) { echo ' enctype="multipart/form-data"'; }
    }

    public static function font_box(\WP_Post $post): void {
        wp_nonce_field('obx_custom_element', 'obx_custom_element_nonce');
        $url = (string) get_post_meta($post->ID, '_obx_font_url', true);
        $weight = (string) (get_post_meta($post->ID, '_obx_font_weight', true) ?: '400');
        $style = (string) (get_post_meta($post->ID, '_obx_font_style', true) ?: 'normal');
        echo '<p>El título es el nombre de familia que aparecerá en Tipografía. Publica varias entradas con el mismo título para añadir variantes.</p>';
        echo '<p><label><strong>Archivo WOFF2, WOFF, TTF u OTF</strong><br><input type="file" name="obx_font_file" accept=".woff2,.woff,.ttf,.otf"></label></p>';
        if ($url !== '') { echo '<p>Actual: <a href="' . esc_url($url) . '">' . esc_html(wp_basename((string) parse_url($url, PHP_URL_PATH))) . '</a></p>'; }
        echo '<p><label>Peso<br><select name="obx_font_weight">';
        foreach (['100','200','300','400','500','600','700','800','900'] as $value) { echo '<option value="' . esc_attr($value) . '" ' . selected($weight, $value, false) . '>' . esc_html($value) . '</option>'; }
        echo '</select></label> &nbsp; <label>Estilo<br><select name="obx_font_style"><option value="normal" ' . selected($style, 'normal', false) . '>Normal</option><option value="italic" ' . selected($style, 'italic', false) . '>Cursiva</option></select></label></p>';
    }

    public static function icon_box(\WP_Post $post): void {
        wp_nonce_field('obx_custom_element', 'obx_custom_element_nonce');
        $url = (string) get_post_meta($post->ID, '_obx_icon_url', true);
        echo '<p>El título será el nombre del icono en la biblioteca de Elementor.</p><p><label><strong>SVG</strong><br><input type="file" name="obx_icon_file" accept="image/svg+xml,.svg"></label></p>';
        if ($url !== '') { echo '<p>Actual: <a href="' . esc_url($url) . '">' . esc_html(wp_basename((string) parse_url($url, PHP_URL_PATH))) . '</a></p>'; }
    }

    public static function code_box(\WP_Post $post): void {
        wp_nonce_field('obx_custom_element', 'obx_custom_element_nonce');
        echo '<textarea name="obx_code" class="large-text code" rows="22" spellcheck="false">' . esc_textarea((string) get_post_meta($post->ID, '_obx_code', true)) . '</textarea><p class="description">HTML, CSS y JavaScript. No se ejecuta PHP.</p>';
    }

    public static function code_settings_box(\WP_Post $post): void {
        $location = (string) (get_post_meta($post->ID, '_obx_code_location', true) ?: 'head');
        $condition = (string) (get_post_meta($post->ID, '_obx_code_condition', true) ?: 'entire_site');
        $priority = (int) (get_post_meta($post->ID, '_obx_code_priority', true) ?: 5);
        echo '<p><label>Ubicación<br><select name="obx_code_location">';
        foreach (['head'=>'&lt;head&gt;','body_start'=>'&lt;body&gt; — inicio','body_end'=>'&lt;body&gt; — final'] as $value=>$label) { echo '<option value="' . esc_attr($value) . '" ' . selected($location, $value, false) . '>' . $label . '</option>'; }
        echo '</select></label></p><p><label>Prioridad<br><input type="number" min="1" max="10" name="obx_code_priority" value="' . esc_attr((string) $priority) . '"></label></p><p><label>Condición<br><select name="obx_code_condition">';
        foreach (['entire_site'=>'Todo el sitio','front_page'=>'Portada','singular'=>'Entradas y páginas','archive'=>'Archivos','logged_in'=>'Usuarios conectados','logged_out'=>'Visitantes'] as $value=>$label) { echo '<option value="' . esc_attr($value) . '" ' . selected($condition, $value, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></label></p>';
    }

    public static function save(int $post_id, \WP_Post $post): void {
        if (!in_array($post->post_type, [self::FONT,self::ICON,self::CODE], true) || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) { return; }
        $nonce = isset($_POST['obx_custom_element_nonce']) ? sanitize_text_field(wp_unslash($_POST['obx_custom_element_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'obx_custom_element') || !current_user_can('manage_options')) { return; }
        if ($post->post_type === self::CODE) {
            $code = isset($_POST['obx_code']) ? wp_unslash((string) $_POST['obx_code']) : '';
            update_post_meta($post_id, '_obx_code', current_user_can('unfiltered_html') ? $code : wp_kses_post($code));
            $location = sanitize_key(wp_unslash((string) ($_POST['obx_code_location'] ?? 'head')));
            $condition = sanitize_key(wp_unslash((string) ($_POST['obx_code_condition'] ?? 'entire_site')));
            update_post_meta($post_id, '_obx_code_location', in_array($location, ['head','body_start','body_end'], true) ? $location : 'head');
            update_post_meta($post_id, '_obx_code_condition', in_array($condition, ['entire_site','front_page','singular','archive','logged_in','logged_out'], true) ? $condition : 'entire_site');
            update_post_meta($post_id, '_obx_code_priority', max(1, min(10, absint($_POST['obx_code_priority'] ?? 5))));
            return;
        }
        if ($post->post_type === self::FONT) {
            update_post_meta($post_id, '_obx_font_weight', in_array((string) ($_POST['obx_font_weight'] ?? ''), ['100','200','300','400','500','600','700','800','900'], true) ? (string) $_POST['obx_font_weight'] : '400');
            update_post_meta($post_id, '_obx_font_style', ($_POST['obx_font_style'] ?? '') === 'italic' ? 'italic' : 'normal');
            self::upload($post_id, 'obx_font_file', '_obx_font_url', ['woff2'=>'font/woff2','woff'=>'font/woff','ttf'=>'font/ttf','otf'=>'font/otf']);
        } else {
            self::upload($post_id, 'obx_icon_file', '_obx_icon_url', ['svg'=>'image/svg+xml'], true);
        }
    }

    private static function upload(int $post_id, string $field, string $meta, array $mimes, bool $svg = false): void {
        if (empty($_FILES[$field]['name']) || !is_string($_FILES[$field]['name'])) { return; }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $filter = static fn(array $allowed): array => $allowed + $mimes;
        add_filter('upload_mimes', $filter);
        $file = wp_handle_upload($_FILES[$field], ['test_form'=>false,'mimes'=>$mimes]);
        remove_filter('upload_mimes', $filter);
        if (!empty($file['error']) || empty($file['file']) || empty($file['url'])) { return; }
        if ($svg) {
            $clean = self::sanitize_svg((string) file_get_contents($file['file']));
            if ($clean === '') { wp_delete_file($file['file']); return; }
            file_put_contents($file['file'], $clean);
        }
        update_post_meta($post_id, $meta, esc_url_raw((string) $file['url']));
    }

    public static function sanitize_svg(string $svg): string {
        if (!class_exists('\DOMDocument') || !preg_match('/<svg\b/i', $svg)) { return ''; }
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        if (!$loaded || !$dom->documentElement || strtolower($dom->documentElement->tagName) !== 'svg') { return ''; }
        foreach (['script','foreignObject','iframe','object','embed'] as $tag) {
            while (($nodes = $dom->getElementsByTagName($tag))->length) { $nodes->item(0)?->parentNode?->removeChild($nodes->item(0)); }
        }
        foreach ($dom->getElementsByTagName('*') as $node) {
            if (!$node->hasAttributes()) { continue; }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name); $value = trim($attribute->value);
                if (str_starts_with($name, 'on') || (in_array($name, ['href','xlink:href'], true) && $value !== '' && !str_starts_with($value, '#')) || ($name === 'style' && preg_match('/url\s*\(/i', $value))) { $node->removeAttributeNode($attribute); }
            }
        }
        return (string) $dom->saveXML($dom->documentElement);
    }

    public static function font_groups(array $groups): array { $groups['obx-custom'] = 'Fuentes personalizadas'; return $groups; }

    public static function fonts(array $fonts): array {
        foreach (get_posts(['post_type'=>self::FONT,'post_status'=>'publish','posts_per_page'=>-1]) as $post) {
            if (get_post_meta($post->ID, '_obx_font_url', true)) { $fonts[$post->post_title] = 'obx-custom'; }
        }
        return $fonts;
    }

    private static function css_string(string $value): string { return str_replace(['\\','\''], ['\\\\','\\\''], wp_strip_all_tags($value)); }

    public static function font_styles(): void {
        $css = '';
        foreach (get_posts(['post_type'=>self::FONT,'post_status'=>'publish','posts_per_page'=>-1]) as $post) {
            $url = (string) get_post_meta($post->ID, '_obx_font_url', true); if ($url === '') { continue; }
            $extension = strtolower((string) pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $format = ['woff2'=>'woff2','woff'=>'woff','ttf'=>'truetype','otf'=>'opentype'][$extension] ?? $extension;
            $css .= "@font-face{font-family:'" . self::css_string($post->post_title) . "';src:url('" . esc_url($url) . "') format('" . esc_attr($format) . "');font-weight:" . esc_attr((string) (get_post_meta($post->ID, '_obx_font_weight', true) ?: '400')) . ';font-style:' . esc_attr((string) (get_post_meta($post->ID, '_obx_font_style', true) ?: 'normal')) . ';font-display:swap}';
        }
        if ($css !== '') { echo '<style id="obx-custom-fonts">' . $css . '</style>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function icons(): array {
        $icons = [];
        foreach (get_posts(['post_type'=>self::ICON,'post_status'=>'publish','posts_per_page'=>-1]) as $post) {
            $url = (string) get_post_meta($post->ID, '_obx_icon_url', true);
            if ($url !== '') { $icons[sanitize_title($post->post_title) ?: 'icon-' . $post->ID] = $url; }
        }
        return $icons;
    }

    public static function icon_tabs(array $tabs): array {
        $icons = self::icons(); if (!$icons) { return $tabs; }
        $tabs['obx-custom-icons'] = ['name'=>'obx-custom-icons','label'=>'Iconos personalizados','url'=>add_query_arg(['action'=>'obx_icons_css','ver'=>VERSION], admin_url('admin-ajax.php')),
            'enqueue'=>[],'prefix'=>'obx-icon-','displayPrefix'=>'obx-icon','labelIcon'=>'eicon-folder','ver'=>VERSION,'fetchJson'=>false,'native'=>true,'icons'=>array_keys($icons)];
        return $tabs;
    }

    public static function icon_css(): void {
        header('Content-Type: text/css; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        echo '.obx-icon{display:inline-block;width:1em;height:1em;background-color:currentColor;vertical-align:-.125em}';
        foreach (self::icons() as $name=>$url) { echo '.obx-icon-' . esc_attr($name) . '{-webkit-mask:url("' . esc_url($url) . '") center/contain no-repeat;mask:url("' . esc_url($url) . '") center/contain no-repeat}'; }
        exit;
    }

    public static function role_control(string $role_slug, array $role_data): void {
        $options = (array) get_option('elementor_role-manager', []);
        $checked = in_array('design', (array) ($options[$role_slug] ?? []), true);
        echo '<div class="elementor-role-control"><label><input type="checkbox" name="elementor_role-manager[' . esc_attr($role_slug) . '][]" value="design" ' . checked($checked, true, false) . '> Editar solo el contenido existente</label><p class="description">Permite cambiar textos e imágenes, pero no añadir elementos ni modificar el diseño.</p></div>';
    }

    private static function condition(string $condition): bool {
        return match ($condition) {
            'front_page' => is_front_page(), 'singular' => is_singular(), 'archive' => is_archive() || is_search() || is_home(),
            'logged_in' => is_user_logged_in(), 'logged_out' => !is_user_logged_in(), default => true,
        };
    }

    public static function print_code(string $location): void {
        if (is_admin()) { return; }
        $posts = get_posts(['post_type'=>self::CODE,'post_status'=>'publish','posts_per_page'=>-1,'meta_key'=>'_obx_code_location','meta_value'=>$location]);
        usort($posts, static fn($a,$b) => (int) get_post_meta($a->ID, '_obx_code_priority', true) <=> (int) get_post_meta($b->ID, '_obx_code_priority', true));
        foreach ($posts as $post) {
            if (!self::condition((string) get_post_meta($post->ID, '_obx_code_condition', true))) { continue; }
            echo "\n<!-- OldBear Custom Code: " . esc_html($post->post_title) . " -->\n";
            echo (string) get_post_meta($post->ID, '_obx_code', true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- saved only by unfiltered_html administrators.
            echo "\n<!-- /OldBear Custom Code -->\n";
        }
    }
}
