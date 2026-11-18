<?php
namespace OldBearExpansion\Forms;

use OldBearExpansion\Widgets\Form;

if (!defined('ABSPATH')) { exit; }

/**
 * Receives Form widget submissions. The browser only sends the document id, the element id
 * and the values: field definitions and actions are always read from the saved document.
 */
final class Handler {
    public const ROUTE_NS = 'obx/v1';
    public const ROUTE = '/form';
    public const POST_TYPE = 'obx_submission';
    /** Submissions allowed per visitor and form in RATE_WINDOW seconds. */
    public const RATE_LIMIT = 8;
    public const RATE_WINDOW = 600;

    public static function boot(): void {
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('init', [self::class, 'post_type']);
        add_action('elementor/editor-one/menu/register', [self::class, 'editor_one_menu'], 20);
        add_action('admin_menu', [self::class, 'menu'], 1000);
        add_action('add_meta_boxes_' . self::POST_TYPE, [self::class, 'meta_box']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [self::class, 'columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [self::class, 'column'], 10, 2);
    }

    public static function routes(): void {
        register_rest_route(self::ROUTE_NS, self::ROUTE, ['methods'=>'POST','callback'=>[self::class, 'submit'],'permission_callback'=>'__return_true']);
    }

    public static function post_type(): void {
        $cap = 'manage_options';
        register_post_type(self::POST_TYPE, [
            'labels'=>['name'=>'Envíos de formularios','singular_name'=>'Envío','menu_name'=>'Envíos','all_items'=>'Envíos','edit_item'=>'Envío','search_items'=>'Buscar envíos','not_found'=>'Todavía no hay envíos.','not_found_in_trash'=>'No hay envíos en la papelera.'],
            'public'=>false, 'show_ui'=>true, 'show_in_menu'=>false, 'show_in_rest'=>false, 'exclude_from_search'=>true,
            'supports'=>['title'], 'map_meta_cap'=>false, 'rewrite'=>false, 'query_var'=>false,
            'capabilities'=>['create_posts'=>'do_not_allow','edit_post'=>$cap,'read_post'=>$cap,'delete_post'=>$cap,'edit_posts'=>$cap,'edit_others_posts'=>$cap,'delete_posts'=>$cap,'delete_others_posts'=>$cap,'publish_posts'=>$cap,'read_private_posts'=>$cap,'edit_published_posts'=>$cap,'delete_published_posts'=>$cap,'edit_private_posts'=>$cap,'delete_private_posts'=>$cap],
        ]);
    }

    private static bool $editor_one = false;

    /** Elementor 3.32+ builds its admin menu from registered items: take the «Envíos» slot. */
    public static function editor_one_menu($provider): void {
        if (!is_object($provider) || !method_exists($provider, 'register_menu') || !interface_exists('\Elementor\Core\Admin\EditorOneMenu\Interfaces\Menu_Item_With_Custom_Url_Interface')) { return; }
        require_once __DIR__ . '/class-menu-item.php';
        $provider->register_menu(new Menu_Item());
        self::$editor_one = true;
    }

    /** Older Elementor: a plain submenu, added late (a post type menu under «elementor» would replace Elementor's own links). */
    public static function menu(): void {
        if (self::$editor_one) { return; }
        global $menu;
        $parent = in_array('elementor', array_column((array) $menu, 2), true) ? 'elementor' : 'tools.php';
        add_submenu_page($parent, 'Envíos de formularios', 'Envíos', 'manage_options', 'edit.php?post_type=' . self::POST_TYPE);
    }

    public static function meta_box(): void {
        add_meta_box('obx-submission-fields', 'Datos enviados', static function(\WP_Post $post): void {
            $fields = (array) get_post_meta($post->ID, '_obx_fields', true);
            $meta = (array) get_post_meta($post->ID, '_obx_meta', true);
            echo '<table class="widefat striped"><tbody>';
            foreach ($fields as $field) {
                if (!is_array($field)) { continue; }
                echo '<tr><th style="width:30%">' . esc_html((string) ($field['title'] ?? $field['id'] ?? '')) . '</th><td>' . nl2br(esc_html((string) ($field['value'] ?? ''))) . '</td></tr>';
            }
            foreach ($meta as $key => $value) { echo '<tr><th>' . esc_html((string) $key) . '</th><td>' . esc_html((string) $value) . '</td></tr>'; }
            echo '</tbody></table>';
        }, self::POST_TYPE, 'normal', 'high');
    }

    public static function columns(array $columns): array {
        return ['cb'=>$columns['cb'] ?? '', 'title'=>'Envío', 'obx_form'=>'Formulario', 'obx_page'=>'Página', 'date'=>$columns['date'] ?? 'Fecha'];
    }

    public static function column(string $column, int $post_id): void {
        if ($column === 'obx_form') { echo esc_html((string) get_post_meta($post_id, '_obx_form_name', true)); }
        if ($column === 'obx_page') {
            $page = (int) get_post_meta($post_id, '_obx_post_id', true);
            if ($page) { echo '<a href="' . esc_url((string) get_permalink($page)) . '">' . esc_html(get_the_title($page)) . '</a>'; }
        }
    }

    /** Finds an element by id in Elementor's element tree. */
    public static function find(array $elements, string $id): ?array {
        foreach ($elements as $element) {
            if (!is_array($element)) { continue; }
            if (($element['id'] ?? '') === $id) { return $element; }
            if (!empty($element['elements']) && ($found = self::find($element['elements'], $id))) { return $found; }
        }
        return null;
    }

    private static function reply(bool $ok, string $message, int $status = 200, array $extra = []): \WP_REST_Response {
        return new \WP_REST_Response(['success'=>$ok,'data'=>['message'=>$message] + $extra], $status);
    }

    /** Settings of the form widget saved in a document, or null. */
    public static function settings(int $post_id, string $form_id): ?array {
        $post = get_post($post_id);
        if (!$post || ($post->post_status !== 'publish' && !current_user_can('edit_post', $post_id))) { return null; }
        $document = \Elementor\Plugin::$instance->documents->get($post_id);
        if (!$document) { return null; }
        $element = self::find((array) $document->get_elements_data(), $form_id);
        if (!$element || ($element['elType'] ?? '') !== 'widget' || ($element['widgetType'] ?? '') !== Form::PRO) { return null; }
        $widget = \Elementor\Plugin::$instance->elements_manager->create_element_instance($element);
        if (!$widget instanceof Form) { return null; }
        return $widget->get_settings_for_display();
    }

    public static function submit(\WP_REST_Request $request): \WP_REST_Response {
        // Registering the widget types loads our widget classes.
        \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
        $messages = Form::messages();
        $params = $request->get_body_params() ?: $request->get_params();
        // Bots fill every field: pretend it worked and drop it.
        if (!empty($params['obx_hp'])) { return self::reply(true, $messages['success_message']); }
        $post_id = absint($params['post_id'] ?? 0);
        $form_id = preg_replace('/[^a-z0-9]/i', '', (string) ($params['form_id'] ?? ''));
        $settings = $post_id && $form_id !== '' ? self::settings($post_id, $form_id) : null;
        if (!$settings) { return self::reply(false, $messages['error_message'], 404); }
        if (($settings['custom_messages'] ?? '') !== '') {
            foreach ($messages as $key => $text) { if ((string) ($settings[$key] ?? '') !== '') { $messages[$key] = (string) $settings[$key]; } }
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $rate_key = 'obx_form_' . md5($ip . '|' . $post_id . '|' . $form_id);
        $count = (int) get_transient($rate_key);
        if ($count >= (int) apply_filters('obx/forms/rate_limit', self::RATE_LIMIT)) { return self::reply(false, 'Has enviado demasiados mensajes seguidos. Prueba de nuevo en unos minutos.', 429); }

        $values = is_array($params['form_fields'] ?? null) ? $params['form_fields'] : [];
        $files = $request->get_file_params()['form_fields'] ?? [];
        $record = [];
        $errors = [];
        foreach ((array) ($settings['form_fields'] ?? []) as $field) {
            if (!is_array($field)) { continue; }
            $type = (string) ($field['field_type'] ?? 'text');
            if (in_array($type, ['html','step'], true) || !in_array($type, Form::TYPES, true)) { continue; }
            $id = Form::field_id($field);
            $required = ($field['required'] ?? '') === 'true';
            if ($type === 'upload') {
                $result = self::upload($field, self::files_for($files, $id));
                if (is_wp_error($result)) { $errors[$id] = $result->get_error_message(); continue; }
                if (!$result && $required) { $errors[$id] = $messages['required_field_message']; continue; }
                $record[$id] = ['id'=>$id,'type'=>$type,'title'=>(string) ($field['field_label'] ?? $id),'value'=>implode(' , ', $result),'raw'=>$result];
                continue;
            }
            $raw = $values[$id] ?? '';
            [$value, $error] = self::validate($field, $type, $raw);
            if ($error === '' && $required && ($value === '' || $value === [])) { $error = $messages['required_field_message']; }
            if ($error !== '') { $errors[$id] = $error; continue; }
            $record[$id] = ['id'=>$id,'type'=>$type,'title'=>(string) ($field['field_label'] ?? $id),'value'=>is_array($value) ? implode(', ', $value) : $value,'raw'=>$value];
        }
        if ($errors) { return self::reply(false, $messages['invalid_message'], 400, ['errors'=>$errors]); }
        set_transient($rate_key, $count + 1, self::RATE_WINDOW);

        $meta = [
            'date'=>wp_date((string) get_option('date_format')),
            'time'=>wp_date((string) get_option('time_format')),
            'page_url'=>esc_url_raw((string) (wp_get_referer() ?: get_permalink($post_id))),
            'user_agent'=>sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))),
            'remote_ip'=>(string) (filter_var($ip, FILTER_VALIDATE_IP) ?: ''),
        ];
        $context = ['post_id'=>$post_id,'form_id'=>$form_id,'form_name'=>(string) ($settings['form_name'] ?? ''),'meta'=>$meta,'referer_title'=>sanitize_text_field((string) ($params['referer_title'] ?? ''))];
        $actions = array_map('strval', (array) ($settings['submit_actions'] ?? ['email']));
        $failed = false;
        $extra = [];
        $problems = [];
        foreach ($actions as $action) {
            switch ($action) {
                case 'email':
                case 'email2':
                    if (!self::send_mail($settings, $action === 'email' ? '' : '_2', $record, $context)) { $failed = true; }
                    break;
                case 'redirect':
                    $url = esc_url_raw(self::replace((string) ($settings['redirect_to'] ?? ''), $record, 'url'));
                    if ($url !== '') { $extra['redirect_url'] = $url; }
                    break;
                case 'webhook':
                    self::webhook($settings, $record, $context);
                    break;
                case 'save-to-database':
                case 'collect_submissions':
                    $meta = array_map('strval', (array) ($settings['submissions_metadata'] ?? ['remote_ip','user_agent']));
                    self::save($record, ['meta'=>array_diff_key($context['meta'], array_flip(array_diff(['remote_ip','user_agent'], $meta)))] + $context);
                    break;
                case 'mailchimp':
                case 'drip':
                case 'activecampaign':
                case 'getresponse':
                case 'convertkit':
                case 'mailerlite':
                case 'slack':
                case 'discord':
                    try {
                        in_array($action, Integrations::LISTS, true) ? Integrations::subscribe($action, $settings, $record) : Integrations::notify($action, $settings, $record, $context);
                    } catch (\Throwable $e) {
                        $failed = true;
                        $problems[] = Integrations::LABELS[$action] . ': ' . $e->getMessage();
                    }
                    break;
                case 'popup':
                    if ($popup = Integrations::popup($settings)) { $extra['popup'] = $popup; }
                    break;
            }
        }
        /** Lets other code act on a valid submission. */
        do_action('obx/forms/new_record', $record, $settings, $context);
        if ($problems && defined('WP_DEBUG') && WP_DEBUG) { error_log('OldBear Expansion form ' . $form_id . ': ' . implode(' | ', $problems)); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        // Editors see why an integration failed, as in Elementor Pro.
        if ($failed) { return self::reply(false, $messages['server_message'], 500, $problems && current_user_can('edit_post', $post_id) ? ['admin_error'=>implode("\n", $problems)] : []); }
        return self::reply(true, $messages['success_message'], 200, $extra);
    }

    /** @return array{0:string|array,1:string} [value, error] */
    private static function validate(array $field, string $type, $raw): array {
        if (in_array($type, ['checkbox'], true) || ($type === 'select' && ($field['allow_multiple'] ?? '') === 'true')) {
            $allowed = array_column(Form::options($field), 1);
            $chosen = array_values(array_intersect(array_map('strval', array_map('wp_unslash', (array) $raw)), $allowed));
            return [$chosen, ''];
        }
        $value = is_array($raw) ? '' : trim((string) wp_unslash($raw));
        if ($value === '') { return ['', '']; }
        switch ($type) {
            case 'email':
                return is_email($value) ? [sanitize_email($value), ''] : ['', 'Escribe una dirección de correo válida.'];
            case 'url':
                $url = esc_url_raw($value);
                return $url !== '' && wp_http_validate_url($url) ? [$url, ''] : ['', 'Escribe una dirección web válida.'];
            case 'tel':
                return preg_match('/^[0-9()#&+*\-=.\s]+$/', $value) ? [$value, ''] : ['', 'Solo se admiten números y los caracteres ()#&+*-=.'];
            case 'number':
                if (!is_numeric($value)) { return ['', 'Escribe un número.']; }
                if (($field['field_min'] ?? '') !== '' && (float) $value < (float) $field['field_min']) { return ['', sprintf('El valor mínimo es %s.', $field['field_min'])]; }
                if (($field['field_max'] ?? '') !== '' && (float) $value > (float) $field['field_max']) { return ['', sprintf('El valor máximo es %s.', $field['field_max'])]; }
                return [$value, ''];
            case 'radio':
            case 'select':
                return in_array($value, array_column(Form::options($field), 1), true) ? [$value, ''] : ['', 'Elige una de las opciones.'];
            case 'acceptance':
                return [$value === 'on' ? 'on' : '', ''];
            case 'date':
                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? [$value, ''] : ['', 'Escribe una fecha válida.'];
            case 'time':
                return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) ? [$value, ''] : ['', 'Escribe una hora válida.'];
            case 'textarea':
                return [sanitize_textarea_field($value), ''];
            case 'password':
                return [$value, ''];
            default:
                return [sanitize_text_field($value), ''];
        }
    }

    /** Normalises PHP's nested $_FILES layout for one field into a list of files. */
    private static function files_for(array $files, string $id): array {
        if (!isset($files['name'][$id])) { return []; }
        $out = [];
        foreach ((array) $files['name'][$id] as $i => $name) {
            $pick = static fn($key) => is_array($files[$key][$id] ?? null) ? ($files[$key][$id][$i] ?? null) : ($files[$key][$id] ?? null);
            if ((int) $pick('error') === UPLOAD_ERR_NO_FILE) { continue; }
            $out[] = ['name'=>(string) $name,'type'=>(string) $pick('type'),'tmp_name'=>(string) $pick('tmp_name'),'error'=>(int) $pick('error'),'size'=>(int) $pick('size')];
        }
        return $out;
    }

    /** @return string[]|\WP_Error URLs of the stored files. */
    private static function upload(array $field, array $files) {
        if (!$files) { return []; }
        $multiple = ($field['allow_multiple_upload'] ?? '') === 'yes';
        $max_files = $multiple ? max(1, (int) ($field['max_files'] ?? 0) ?: 20) : 1;
        if (count($files) > $max_files) { return new \WP_Error('obx_files', sprintf('Puedes subir como máximo %d archivos.', $max_files)); }
        $max_size = !empty($field['file_sizes']) ? (int) $field['file_sizes'] * MB_IN_BYTES : wp_max_upload_size();
        $types = array_filter(array_map(static fn($t) => trim(strtolower($t), " .\t"), explode(',', (string) ($field['file_types'] ?? ''))));
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $urls = [];
        $dir = static function(array $dirs): array {
            $dirs['subdir'] = '/obx-forms';
            $dirs['path'] = $dirs['basedir'] . $dirs['subdir'];
            $dirs['url'] = $dirs['baseurl'] . $dirs['subdir'];
            return $dirs;
        };
        add_filter('upload_dir', $dir);
        try {
            foreach ($files as $file) {
                if ($file['error'] !== UPLOAD_ERR_OK) { return new \WP_Error('obx_files', 'No se ha podido subir el archivo.'); }
                if ($file['size'] > $max_size) { return new \WP_Error('obx_files', 'El archivo es demasiado grande.'); }
                $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
                $ext = strtolower((string) ($check['ext'] ?: ''));
                if ($ext === '' || ($types && !in_array($ext, $types, true))) { return new \WP_Error('obx_files', 'Ese tipo de archivo no está permitido.'); }
                $file['name'] = wp_generate_password(16, false) . '.' . $ext;
                $moved = wp_handle_upload($file, ['test_form'=>false,'test_type'=>true]);
                if (!empty($moved['error']) || empty($moved['url'])) { return new \WP_Error('obx_files', 'No se ha podido guardar el archivo.'); }
                $urls[] = (string) $moved['url'];
            }
            $index = wp_upload_dir()['path'] . '/index.php';
            if (!file_exists($index)) { @file_put_contents($index, "<?php\n// Silence is golden.\n"); }
        } finally {
            remove_filter('upload_dir', $dir);
        }
        return $urls;
    }

    /** Replaces [field id="…"] and [all-fields]. $mode: html, plain, url or header. */
    public static function replace(string $text, array $record, string $mode = 'plain'): string {
        $escape = static function(string $value) use ($mode): string {
            if ($mode === 'html') { return nl2br(esc_html($value)); }
            if ($mode === 'url') { return rawurlencode($value); }
            if ($mode === 'header') { return trim(str_replace(["\r", "\n"], ' ', $value)); }
            return $value;
        };
        if (str_contains($text, '[all-fields]')) {
            $lines = [];
            foreach ($record as $field) {
                if ($field['value'] === '' || $field['type'] === 'password') { continue; }
                $lines[] = $escape(($field['title'] !== '' ? $field['title'] : $field['id']) . ': ' . $field['value']);
            }
            $text = str_replace('[all-fields]', implode($mode === 'html' ? '<br>' : "\n", $lines), $text);
        }
        return (string) preg_replace_callback('/\[field id=["\']?([A-Za-z0-9_-]+)["\']?\]/', static fn($m) => isset($record[$m[1]]) ? $escape((string) $record[$m[1]]['value']) : '', $text);
    }

    private static function emails(string $list): array {
        return array_values(array_filter(array_map('sanitize_email', array_map('trim', explode(',', $list))), 'is_email'));
    }

    private static function send_mail(array $s, string $suffix, array $record, array $context): bool {
        $to = self::emails(self::replace((string) ($s['email_to' . $suffix] ?? ''), $record, 'header'));
        if ($suffix === '' && !$to) { $to = [(string) get_option('admin_email')]; }
        if (!$to) { return true; }
        $html = ($s['email_content_type' . $suffix] ?? 'html') === 'html';
        $subject = self::replace((string) ($s['email_subject' . $suffix] ?? ''), $record, 'header') ?: sprintf('Nuevo mensaje desde «%s»', get_option('blogname'));
        $body = self::replace((string) ($s['email_content' . $suffix] ?? '[all-fields]'), $record, $html ? 'html' : 'plain');
        $labels = ['date'=>'Fecha','time'=>'Hora','page_url'=>'Página','user_agent'=>'Navegador','remote_ip'=>'IP'];
        $meta = [];
        foreach ((array) ($s['form_metadata' . $suffix] ?? []) as $key) {
            if (isset($labels[$key], $context['meta'][$key]) && $context['meta'][$key] !== '') { $meta[] = $labels[$key] . ': ' . $context['meta'][$key]; }
        }
        if ($meta) { $body .= $html ? '<br><br>---<br>' . implode('<br>', array_map('esc_html', $meta)) : "\n\n---\n" . implode("\n", $meta); }
        $from = self::emails(self::replace((string) ($s['email_from' . $suffix] ?? ''), $record, 'header'))[0] ?? '';
        $from_name = self::replace((string) ($s['email_from_name' . $suffix] ?? ''), $record, 'header');
        $headers = [];
        if ($from !== '') { $headers[] = 'From: ' . ($from_name !== '' ? '"' . str_replace(['"', '<', '>'], '', $from_name) . '" ' : '') . '<' . $from . '>'; }
        foreach (self::emails(self::replace((string) ($s['email_reply_to' . $suffix] ?? ''), $record, 'header')) as $reply) { $headers[] = 'Reply-To: ' . $reply; }
        foreach (['email_to_cc'=>'Cc','email_to_bcc'=>'Bcc'] as $key => $header) {
            foreach (self::emails(self::replace((string) ($s[$key . $suffix] ?? ''), $record, 'header')) as $address) { $headers[] = $header . ': ' . $address; }
        }
        if ($html) { $headers[] = 'Content-Type: text/html; charset=UTF-8'; $body = '<div>' . $body . '</div>'; }
        return (bool) wp_mail($to, $subject, $body, $headers);
    }

    private static function webhook(array $s, array $record, array $context): void {
        $url = esc_url_raw((string) ($s['webhooks'] ?? ''));
        if ($url === '' || !wp_http_validate_url($url)) { return; }
        if (($s['webhooks_advanced_data'] ?? 'no') === 'yes') {
            $fields = [];
            foreach ($record as $id => $field) { $fields[$id] = ['id'=>$id,'type'=>$field['type'],'title'=>$field['title'],'value'=>$field['value'],'raw_value'=>$field['raw']]; }
            $body = ['form'=>['id'=>$context['form_id'],'name'=>$context['form_name']],'fields'=>$fields,'meta'=>$context['meta']];
        } else {
            $body = [];
            foreach ($record as $field) { $body[$field['title'] !== '' ? $field['title'] : $field['id']] = $field['value']; }
            $body['form_id'] = $context['form_id'];
            $body['form_name'] = $context['form_name'];
        }
        wp_safe_remote_post($url, ['body'=>$body,'timeout'=>5,'blocking'=>false]);
    }

    private static function save(array $record, array $context): void {
        $fields = array_values(array_map(static fn($f) => ['id'=>$f['id'],'title'=>$f['title'],'value'=>$f['type'] === 'password' ? '' : $f['value']], $record));
        $id = wp_insert_post(['post_type'=>self::POST_TYPE,'post_status'=>'private','post_title'=>sprintf('%s · %s', $context['form_name'] !== '' ? $context['form_name'] : 'Formulario', wp_date('Y-m-d H:i'))], true);
        if (is_wp_error($id)) { return; }
        update_post_meta($id, '_obx_fields', $fields);
        update_post_meta($id, '_obx_meta', $context['meta']);
        update_post_meta($id, '_obx_form_name', $context['form_name']);
        update_post_meta($id, '_obx_form_id', $context['form_id']);
        update_post_meta($id, '_obx_post_id', $context['post_id']);
    }
}
