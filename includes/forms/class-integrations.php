<?php
namespace OldBearExpansion\Forms;

use Elementor\Controls_Manager;
use Elementor\Controls_Stack;
use Elementor\Repeater;
use OldBearExpansion\Plugin;
use const OldBearExpansion\VERSION;

if (!defined('ABSPATH')) { exit; }

/**
 * Form actions that talk to other services: newsletter lists (Mailchimp, Drip, ActiveCampaign, GetResponse,
 * Kit/ConvertKit, MailerLite), chat notifications (Slack, Discord) and the popup action.
 * Settings use Elementor Pro's keys; the global API keys live in Elementor → Ajustes → Integraciones under
 * the option names Pro uses (elementor_pro_mailchimp_api_key…), so sites moving from Pro keep them.
 */
final class Integrations {
    public const NS = 'obx/v1';
    public const CACHE = 'obx_integrations_cache';
    public const LABELS = ['mailchimp'=>'Mailchimp','drip'=>'Drip','activecampaign'=>'ActiveCampaign','getresponse'=>'GetResponse','convertkit'=>'Kit (ConvertKit)','mailerlite'=>'MailerLite','slack'=>'Slack','discord'=>'Discord','popup'=>'Popup'];
    public const LISTS = ['mailchimp','drip','activecampaign','getresponse','convertkit','mailerlite'];

    /**
     * Per list service: credentials source control, credentials (widget key => global Elementor field),
     * list control and its label, extra option lists (control => cache key) and the fields map control.
     */
    public const SERVICES = [
        'mailchimp'=>['source'=>'mailchimp_api_key_source','credentials'=>['key'=>['mailchimp_api_key','pro_mailchimp_api_key']],'list'=>['mailchimp_list','Audiencia'],'options'=>['mailchimp_groups'=>'groups'],'map'=>'mailchimp_fields_map'],
        'drip'=>['source'=>'drip_api_token_source','credentials'=>['key'=>['drip_custom_api_token','pro_drip_api_token']],'list'=>['drip_account','Cuenta'],'options'=>[],'map'=>'drip_fields_map'],
        'activecampaign'=>['source'=>'activecampaign_api_credentials_source','credentials'=>['key'=>['activecampaign_api_key','pro_activecampaign_api_key'],'url'=>['activecampaign_api_url','pro_activecampaign_api_url']],'list'=>['activecampaign_list','Lista'],'options'=>[],'map'=>'activecampaign_fields_map'],
        'getresponse'=>['source'=>'getresponse_api_key_source','credentials'=>['key'=>['getresponse_custom_api_key','pro_getresponse_api_key']],'list'=>['getresponse_list','Lista'],'options'=>[],'map'=>'getresponse_fields_map'],
        'convertkit'=>['source'=>'convertkit_api_key_source','credentials'=>['key'=>['convertkit_custom_api_key','pro_convertkit_api_key']],'list'=>['convertkit_form','Formulario'],'options'=>['convertkit_tags'=>'tags'],'map'=>'convertkit_fields_map'],
        'mailerlite'=>['source'=>'mailerlite_api_key_source','credentials'=>['key'=>['mailerlite_custom_api_key','pro_mailerlite_api_key']],'list'=>['mailerlite_group','Grupo'],'options'=>[],'map'=>'mailerlite_fields_map'],
    ];

    public static function boot(): void {
        add_action('elementor/admin/after_create_settings/elementor', [self::class, 'settings']);
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('elementor/editor/after_enqueue_scripts', [self::class, 'editor_script']);
    }

    /** Global keys in Elementor's «Integraciones» tab (Elementor stores field X as option elementor_X). */
    public static function settings($settings): void {
        if (Plugin::pro_active() || !is_object($settings) || !method_exists($settings, 'add_section')) { return; }
        $labels = ['key'=>'Clave de API','url'=>'URL de la API'];
        foreach (self::SERVICES as $service => $def) {
            $fields = [];
            foreach ($def['credentials'] as $part => [, $field]) {
                $fields[$field] = ['label'=>($service === 'drip' ? 'Token de API' : $labels[$part]),'field_args'=>['type'=>'text','class'=>'regular-text','desc'=>$part === 'url' ? 'La dirección de tu cuenta, p. ej. https://tucuenta.api-us1.com' : '']];
            }
            $settings->add_section('integrations', 'obx_' . $service, ['label'=>self::LABELS[$service],'callback'=>static function() use ($service) {
                echo '<p>' . esc_html(sprintf('Clave que usan por defecto los formularios con la acción «%s».', self::LABELS[$service])) . '</p>';
            },'fields'=>$fields]);
        }
    }

    public static function routes(): void {
        register_rest_route(self::NS, '/integrations/(?P<service>[a-z]+)', ['methods'=>'POST','callback'=>[self::class, 'fetch'],
            'permission_callback'=>static fn() => current_user_can('edit_posts')]);
    }

    public static function editor_script(): void {
        if (Plugin::pro_active()) { return; }
        wp_enqueue_script('obx-editor-integrations', Plugin::url('assets/js/editor-integrations.js'), ['jquery'], VERSION, true);
        $services = [];
        foreach (self::SERVICES as $service => $def) {
            $services[$service] = ['source'=>$def['source'],'list'=>$def['list'][0],'options'=>$def['options'],'help'=>$service . '_fields_help','credentials'=>array_map(static fn($pair) => $pair[0], $def['credentials'])];
        }
        wp_add_inline_script('obx-editor-integrations', 'window.obxIntegrations = ' . wp_json_encode(['rest'=>esc_url_raw(rest_url(self::NS . '/integrations/')),'nonce'=>wp_create_nonce('wp_rest'),'services'=>$services]) . ';', 'before');
    }

    /** Editor: lists, groups, tags and fields of a service, with the widget's own key or the global one. */
    public static function fetch(\WP_REST_Request $request): \WP_REST_Response {
        $service = (string) $request['service'];
        if (!isset(self::SERVICES[$service])) { return new \WP_REST_Response(['message'=>'Servicio desconocido.'], 404); }
        $credentials = [];
        foreach (self::SERVICES[$service]['credentials'] as $part => [, $field]) {
            $value = trim(sanitize_text_field((string) ($request[$part] ?? '')));
            $credentials[$part] = $value !== '' ? $value : self::option($field);
        }
        try {
            $data = self::remote($service, $credentials, sanitize_text_field((string) ($request['list'] ?? '')));
        } catch (\Throwable $e) {
            return new \WP_REST_Response(['message'=>$e->getMessage()], 400);
        }
        $cache = (array) get_option(self::CACHE, []);
        foreach ($data as $kind => $items) { $cache[$service][$kind] = (array) ($cache[$service][$kind] ?? []) + $items; }
        update_option(self::CACHE, $cache, false);
        return new \WP_REST_Response($data);
    }

    private static function option(string $field): string { return trim((string) get_option('elementor_' . $field, '')); }

    /** Options seen in earlier lookups, so saved choices keep their names in the editor. */
    private static function cached(string $service, string $kind): array { return (array) (((array) get_option(self::CACHE, []))[$service][$kind] ?? []); }

    /** API credentials for a form: the widget's own when its source is «custom», otherwise the global ones. */
    public static function credentials(string $service, array $s): array {
        $def = self::SERVICES[$service];
        $custom = ($s[$def['source']] ?? 'default') === 'custom';
        $out = [];
        foreach ($def['credentials'] as $part => [$key, $field]) { $out[$part] = $custom ? trim((string) ($s[$key] ?? '')) : self::option($field); }
        return $out;
    }

    // ---------------------------------------------------------------- Controls

    public static function controls(Controls_Stack $w): void {
        $source = ['default'=>'Clave global (Ajustes → Integraciones)','custom'=>'Otra clave'];
        foreach (self::SERVICES as $service => $def) {
            $on = ['submit_actions'=>$service];
            [$list, $list_label] = $def['list'];
            $chosen = $on + [$list . '!'=>''];
            $w->start_controls_section('section_' . $service, ['label'=>self::LABELS[$service],'condition'=>$on]);
            $w->add_control($def['source'], ['label'=>$service === 'drip' ? 'Token de API' : 'Clave de API','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>$source,'render_type'=>'none']);
            foreach ($def['credentials'] as $part => [$key]) {
                $w->add_control($key, ['label'=>$part === 'url' ? 'URL de la API' : ($service === 'drip' ? 'Token' : 'Clave'),'type'=>Controls_Manager::TEXT,'label_block'=>true,'render_type'=>'none','condition'=>$on + [$def['source']=>'custom'],'ai'=>['active'=>false]]);
            }
            $w->add_control($service . '_fetch', ['type'=>Controls_Manager::BUTTON,'label'=>'','text'=>'Cargar desde ' . self::LABELS[$service],'event'=>'obx:integration:fetch','separator'=>'after','condition'=>$on]);
            $w->add_control($list, ['label'=>$list_label,'type'=>Controls_Manager::SELECT,'options'=>[''=>'Elige…'] + self::cached($service, 'lists'),'default'=>'','label_block'=>true,'render_type'=>'none','condition'=>$on]);
            switch ($service) {
                case 'mailchimp':
                    $w->add_control('mailchimp_groups', ['label'=>'Grupos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'options'=>self::cached($service, 'groups'),'label_block'=>true,'render_type'=>'none','condition'=>$chosen]);
                    $w->add_control('mailchimp_tags', ['label'=>'Etiquetas','type'=>Controls_Manager::TEXT,'description'=>'Separadas por comas. Admite [field id="…"].','label_block'=>true,'render_type'=>'none','condition'=>$chosen]);
                    $w->add_control('mailchimp_double_opt_in', ['label'=>'Doble confirmación','type'=>Controls_Manager::SWITCHER,'description'=>'Mailchimp pide al suscriptor que confirme su dirección.','render_type'=>'none','condition'=>$chosen]);
                    break;
                case 'drip':
                    $w->add_control('drip_custom_fields', ['label'=>'Enviar los demás campos','type'=>Controls_Manager::SWITCHER,'default'=>'no','return_value'=>'yes','description'=>'Los campos sin correspondencia se guardan como campos personalizados de Drip (con su ID).','render_type'=>'none','condition'=>$chosen]);
                    $w->add_control('tags', ['label'=>'Etiquetas','type'=>Controls_Manager::TEXT,'description'=>'Separadas por comas. Admite [field id="…"].','label_block'=>true,'render_type'=>'none','condition'=>$chosen]);
                    break;
                case 'activecampaign':
                    $w->add_control('activecampaign_tags', ['label'=>'Etiquetas','type'=>Controls_Manager::TEXT,'description'=>'Separadas por comas. Admite [field id="…"].','label_block'=>true,'render_type'=>'none','condition'=>$chosen]);
                    break;
                case 'getresponse':
                    $w->add_control('getresponse_dayofcycle', ['label'=>'Día del ciclo','type'=>Controls_Manager::NUMBER,'min'=>0,'description'=>'Día del autorespondedor en el que entra el contacto.','render_type'=>'none','condition'=>$chosen]);
                    break;
                case 'convertkit':
                    $w->add_control('convertkit_tags', ['label'=>'Etiquetas','type'=>Controls_Manager::SELECT2,'multiple'=>true,'options'=>self::cached($service, 'tags'),'label_block'=>true,'render_type'=>'none','condition'=>$chosen]);
                    break;
                case 'mailerlite':
                    $w->add_control('allow_resubscribe', ['label'=>'Permitir volver a suscribirse','type'=>Controls_Manager::SWITCHER,'render_type'=>'none','condition'=>$chosen]);
                    break;
            }
            $map = new Repeater();
            $map->add_control('remote_id', ['label'=>'Campo en ' . self::LABELS[$service],'type'=>Controls_Manager::TEXT,'label_block'=>true]);
            $map->add_control('local_id', ['label'=>'ID del campo del formulario','type'=>Controls_Manager::TEXT,'label_block'=>true]);
            $w->add_control($def['map'], ['label'=>'Correspondencia de campos','type'=>Controls_Manager::REPEATER,'fields'=>$map->get_controls(),'default'=>[['remote_id'=>'email','local_id'=>'email']],
                'title_field'=>'{{{ remote_id }}} ← {{{ local_id }}}','prevent_empty'=>false,'render_type'=>'none','condition'=>$chosen]);
            $w->add_control($service . '_fields_help', ['type'=>Controls_Manager::RAW_HTML,'raw'=>self::fields_help(self::cached($service, 'fields')),'content_classes'=>'elementor-descriptor','condition'=>$chosen]);
            $w->end_controls_section();
        }

        $text = static fn(string $label, string $description = '') => ['label'=>$label,'type'=>Controls_Manager::TEXT,'label_block'=>true,'render_type'=>'none'] + ($description !== '' ? ['description'=>$description] : []);
        $w->start_controls_section('section_slack', ['label'=>'Slack','condition'=>['submit_actions'=>'slack']]);
        $w->add_control('slack_webhook', $text('Webhook', 'Dirección del webhook entrante de Slack (https://hooks.slack.com/…).') + ['placeholder'=>'https://hooks.slack.com/services/…']);
        $w->add_control('slack_channel', $text('Canal', 'Opcional; si no, el canal del webhook.'));
        $w->add_control('slack_username', $text('Nombre del remitente'));
        $w->add_control('slack_pretext', $text('Texto previo'));
        $w->add_control('slack_title', $text('Título'));
        $w->add_control('slack_text', ['type'=>Controls_Manager::TEXTAREA] + $text('Texto', 'Admite [field id="…"] y [all-fields].'));
        $w->add_control('slack_add_fields', ['label'=>'Incluir los campos','type'=>Controls_Manager::SWITCHER,'default'=>'yes','render_type'=>'none']);
        $w->add_control('slack_add_ts', ['label'=>'Incluir la hora','type'=>Controls_Manager::SWITCHER,'default'=>'yes','render_type'=>'none']);
        $w->add_control('slack_webhook_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'default'=>'#D30C5C','alpha'=>false,'render_type'=>'none']);
        $w->end_controls_section();

        $w->start_controls_section('section_discord', ['label'=>'Discord','condition'=>['submit_actions'=>'discord']]);
        $w->add_control('discord_webhook', $text('Webhook', 'Dirección del webhook del canal de Discord.') + ['placeholder'=>'https://discord.com/api/webhooks/…']);
        $w->add_control('discord_username', $text('Nombre del remitente'));
        $w->add_control('discord_avatar_url', $text('Imagen del remitente (URL)'));
        $w->add_control('discord_title', $text('Título'));
        $w->add_control('discord_content', ['type'=>Controls_Manager::TEXTAREA] + $text('Texto', 'Admite [field id="…"] y [all-fields].'));
        $w->add_control('discord_form_data', ['label'=>'Incluir los campos','type'=>Controls_Manager::SWITCHER,'default'=>'yes','render_type'=>'none']);
        $w->add_control('discord_ts', ['label'=>'Incluir la hora','type'=>Controls_Manager::SWITCHER,'default'=>'yes','render_type'=>'none']);
        $w->add_control('discord_webhook_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'default'=>'#D30C5C','alpha'=>false,'render_type'=>'none']);
        $w->end_controls_section();

        $popups = [''=>'Elige un popup'];
        foreach (get_posts(['post_type'=>'elementor_library','post_status'=>'publish','posts_per_page'=>200,'meta_key'=>'_elementor_template_type','meta_value'=>'popup']) as $popup) { $popups[(string) $popup->ID] = $popup->post_title ?: '#' . $popup->ID; }
        $w->start_controls_section('section_popup', ['label'=>'Popup','condition'=>['submit_actions'=>'popup']]);
        $w->add_control('popup_action', ['label'=>'Acción','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Elige una acción','open'=>'Abrir un popup','close'=>'Cerrar el popup'],'render_type'=>'none']);
        $w->add_control('popup_action_popup_id', ['label'=>'Popup','type'=>Controls_Manager::SELECT,'options'=>$popups,'default'=>'','label_block'=>true,'condition'=>['popup_action'=>'open']]);
        $w->add_control('popup_action_do_not_show_again', ['label'=>'No volver a mostrarlo','type'=>Controls_Manager::SWITCHER,'render_type'=>'none','condition'=>['popup_action'=>'close']]);
        $w->end_controls_section();
    }

    public static function fields_help(array $fields): string {
        if (!$fields) { return 'Pulsa «Cargar» para ver los campos disponibles. El correo se envía con el ID <code>email</code>.'; }
        $items = [];
        foreach ($fields as $id => $label) { $items[] = '<code>' . esc_html((string) $id) . '</code> ' . esc_html((string) $label); }
        return 'Campos disponibles: ' . implode(', ', $items);
    }

    // ---------------------------------------------------------------- HTTP

    /** @return array decoded JSON body */
    private static function request(string $method, string $url, array $headers = [], $body = null): array {
        $args = ['method'=>$method,'timeout'=>10,'headers'=>$headers + ['Accept'=>'application/json'],'user-agent'=>'OldBear Expansion/' . VERSION];
        if ($body !== null) {
            $json = ($headers['Content-Type'] ?? '') === 'application/json';
            $args['body'] = $json ? (string) wp_json_encode($body) : $body;
        }
        $response = wp_safe_remote_request($url, $args);
        if (is_wp_error($response)) { throw new \RuntimeException($response->get_error_message()); }
        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);
        if ($code >= 400) {
            $detail = is_array($data) ? (string) ($data['detail'] ?? $data['message'] ?? $data['error'] ?? $data['title'] ?? ($data['errors'][0]['message'] ?? '')) : '';
            throw new \RuntimeException(trim(sprintf('Error %d. %s', $code, is_array($detail) ? wp_json_encode($detail) : $detail)), $code);
        }
        return is_array($data) ? $data : [];
    }

    private static function require(array $credentials, string $part = 'key'): string {
        $value = (string) ($credentials[$part] ?? '');
        if ($value === '') { throw new \RuntimeException($part === 'url' ? 'Falta la URL de la API.' : 'Falta la clave de API.'); }
        return $value;
    }

    private static function mailchimp_base(string $key): string {
        $dc = preg_replace('/[^a-z0-9]/', '', strtolower((string) substr(strrchr($key, '-') ?: '', 1)));
        if ($dc === '') { throw new \RuntimeException('La clave de Mailchimp debe terminar en el centro de datos (p. ej. -us21).'); }
        return 'https://' . $dc . '.api.mailchimp.com/3.0';
    }

    private static function mailchimp_auth(string $key): array { return ['Authorization'=>'Basic ' . base64_encode('obx:' . $key)]; }

    private static function activecampaign_api(array $credentials, string $action, array $body = []): array {
        $url = untrailingslashit(esc_url_raw(self::require($credentials, 'url')));
        $query = ['api_action'=>$action,'api_key'=>self::require($credentials),'api_output'=>'json'];
        $data = $body
            ? self::request('POST', $url . '/admin/api.php?' . http_build_query($query), ['Content-Type'=>'application/x-www-form-urlencoded'], $body)
            : self::request('GET', $url . '/admin/api.php?' . http_build_query($query + ['ids'=>'all','full'=>0]));
        if ((int) ($data['result_code'] ?? 0) !== 1) { throw new \RuntimeException((string) ($data['result_message'] ?? 'ActiveCampaign rechazó la petición.')); }
        return $data;
    }

    private static function mailerlite_new(string $key): bool { return substr_count($key, '.') === 2 || strlen($key) > 100; }

    /** id => name lists of a response. */
    private static function pairs(array $items, string $id = 'id', string $name = 'name'): array {
        $out = [];
        foreach ($items as $item) { if (is_array($item) && isset($item[$id])) { $out[(string) $item[$id]] = (string) ($item[$name] ?? $item[$id]); } }
        return $out;
    }

    /**
     * Lists (and, for a chosen list, groups and fields) of a service.
     * @return array{lists:array,groups?:array,tags?:array,fields:array}
     */
    public static function remote(string $service, array $credentials, string $list = ''): array {
        $fields = ['email'=>'Correo electrónico (obligatorio)'];
        switch ($service) {
            case 'mailchimp':
                $key = self::require($credentials);
                $base = self::mailchimp_base($key);
                $auth = self::mailchimp_auth($key);
                $out = ['lists'=>self::pairs(self::request('GET', $base . '/lists?count=1000&fields=lists.id,lists.name', $auth)['lists'] ?? []),'groups'=>[],'fields'=>$fields];
                if ($list !== '') {
                    foreach (self::request('GET', $base . '/lists/' . rawurlencode($list) . '/interest-categories?count=100', $auth)['categories'] ?? [] as $category) {
                        foreach (self::request('GET', $base . '/lists/' . rawurlencode($list) . '/interest-categories/' . rawurlencode((string) $category['id']) . '/interests?count=100', $auth)['interests'] ?? [] as $interest) {
                            $out['groups'][(string) $interest['id']] = ($category['title'] ?? '') . ' · ' . ($interest['name'] ?? '');
                        }
                    }
                    foreach (self::request('GET', $base . '/lists/' . rawurlencode($list) . '/merge-fields?count=100', $auth)['merge_fields'] ?? [] as $field) { $out['fields'][(string) $field['tag']] = (string) $field['name']; }
                }
                return $out;
            case 'drip':
                $auth = ['Authorization'=>'Basic ' . base64_encode(self::require($credentials) . ':')];
                return ['lists'=>self::pairs(self::request('GET', 'https://api.getdrip.com/v2/accounts', $auth)['accounts'] ?? []),
                    'fields'=>$fields + ['first_name'=>'Nombre','last_name'=>'Apellidos','phone'=>'Teléfono','address1'=>'Dirección','city'=>'Ciudad','state'=>'Provincia','zip'=>'Código postal','country'=>'País']];
            case 'activecampaign':
                $lists = [];
                foreach (self::activecampaign_api($credentials, 'list_list') as $key => $item) { if (is_numeric($key) && is_array($item)) { $lists[(string) $item['id']] = (string) $item['name']; } }
                $out = ['lists'=>$lists,'fields'=>$fields + ['first_name'=>'Nombre','last_name'=>'Apellidos','phone'=>'Teléfono','orgname'=>'Organización']];
                try {
                    foreach (self::activecampaign_api($credentials, 'list_field_view') as $key => $item) { if (is_numeric($key) && is_array($item) && !empty($item['perstag'])) { $out['fields']['field[%' . $item['perstag'] . '%,0]'] = (string) $item['title']; } }
                } catch (\RuntimeException $e) { /* no custom fields */ }
                return $out;
            case 'getresponse':
                $auth = ['X-Auth-Token'=>'api-key ' . self::require($credentials)];
                $out = ['lists'=>self::pairs(self::request('GET', 'https://api.getresponse.com/v3/campaigns?perPage=1000', $auth), 'campaignId'),'fields'=>$fields + ['name'=>'Nombre']];
                foreach (self::request('GET', 'https://api.getresponse.com/v3/custom-fields?perPage=1000', $auth) as $field) { if (is_array($field) && isset($field['customFieldId'])) { $out['fields'][(string) $field['customFieldId']] = (string) $field['name']; } }
                return $out;
            case 'convertkit':
                $key = rawurlencode(self::require($credentials));
                $out = ['lists'=>self::pairs(self::request('GET', 'https://api.convertkit.com/v3/forms?api_key=' . $key)['forms'] ?? []),
                    'tags'=>self::pairs(self::request('GET', 'https://api.convertkit.com/v3/tags?api_key=' . $key)['tags'] ?? []),'fields'=>$fields + ['first_name'=>'Nombre']];
                foreach (self::request('GET', 'https://api.convertkit.com/v3/custom_fields?api_key=' . $key)['custom_fields'] ?? [] as $field) { $out['fields'][(string) $field['key']] = (string) $field['label']; }
                return $out;
            case 'mailerlite':
                $key = self::require($credentials);
                if (self::mailerlite_new($key)) {
                    $auth = ['Authorization'=>'Bearer ' . $key];
                    $out = ['lists'=>self::pairs(self::request('GET', 'https://connect.mailerlite.com/api/groups?limit=1000', $auth)['data'] ?? []),'fields'=>$fields];
                    foreach (self::request('GET', 'https://connect.mailerlite.com/api/fields?limit=100', $auth)['data'] ?? [] as $field) { $out['fields'][(string) $field['key']] = (string) $field['name']; }
                    return $out;
                }
                $auth = ['X-MailerLite-ApiKey'=>$key];
                $out = ['lists'=>self::pairs(self::request('GET', 'https://api.mailerlite.com/api/v2/groups?limit=1000', $auth)),'fields'=>$fields];
                foreach (self::request('GET', 'https://api.mailerlite.com/api/v2/fields', $auth) as $field) { if (is_array($field) && isset($field['key'])) { $out['fields'][(string) $field['key']] = (string) ($field['title'] ?? $field['key']); } }
                return $out;
        }
        throw new \RuntimeException('Servicio desconocido.');
    }

    // ---------------------------------------------------------------- Submission

    /** remote id => value, from a fields map repeater; «email» falls back to the first email field. */
    public static function mapped(array $s, string $key, array $record): array {
        $out = [];
        foreach ((array) ($s[$key] ?? []) as $row) {
            if (!is_array($row)) { continue; }
            $remote = trim((string) ($row['remote_id'] ?? ''));
            $local = trim((string) ($row['local_id'] ?? ''));
            if ($remote !== '' && $local !== '' && isset($record[$local]) && (string) $record[$local]['value'] !== '') { $out[$remote] = (string) $record[$local]['value']; }
        }
        if (empty($out['email'])) {
            foreach ($record as $field) { if ($field['type'] === 'email' && $field['value'] !== '') { $out['email'] = (string) $field['value']; break; } }
        }
        if (empty($out['email']) || !is_email($out['email'])) { throw new \RuntimeException('Falta un correo electrónico válido.'); }
        return $out;
    }

    private static function tags(string $text, array $record): array {
        return array_values(array_filter(array_map('trim', explode(',', Handler::replace($text, $record)))));
    }

    /** Adds the submitter to the chosen list of $service. Throws on failure. */
    public static function subscribe(string $service, array $s, array $record): void {
        $def = self::SERVICES[$service];
        $list = trim((string) ($s[$def['list'][0]] ?? ''));
        if ($list === '') { throw new \RuntimeException('No se ha elegido ' . strtolower($def['list'][1]) . '.'); }
        $credentials = self::credentials($service, $s);
        $data = self::mapped($s, $def['map'], $record);
        $email = $data['email'];
        unset($data['email']);
        $json = ['Content-Type'=>'application/json'];
        switch ($service) {
            case 'mailchimp':
                $key = self::require($credentials);
                $member = self::mailchimp_base($key) . '/lists/' . rawurlencode($list) . '/members/' . md5(strtolower($email));
                $body = ['email_address'=>$email,'status_if_new'=>($s['mailchimp_double_opt_in'] ?? '') === 'yes' ? 'pending' : 'subscribed'];
                if ($data) { $body['merge_fields'] = $data; }
                $groups = array_filter(array_map('strval', (array) ($s['mailchimp_groups'] ?? [])));
                if ($groups) { $body['interests'] = array_fill_keys($groups, true); }
                self::request('PUT', $member, self::mailchimp_auth($key) + $json, $body);
                $tags = self::tags((string) ($s['mailchimp_tags'] ?? ''), $record);
                if ($tags) { self::request('POST', $member . '/tags', self::mailchimp_auth($key) + $json, ['tags'=>array_map(static fn($t) => ['name'=>$t,'status'=>'active'], $tags)]); }
                return;
            case 'drip':
                $subscriber = ['email'=>$email] + $data;
                if (($s['drip_custom_fields'] ?? 'no') === 'yes') {
                    $custom = [];
                    foreach ($record as $id => $field) { if (!isset($data[$id]) && $field['type'] !== 'password' && $field['value'] !== '' && $field['value'] !== $email) { $custom[sanitize_key((string) $id)] = (string) $field['value']; } }
                    if ($custom) { $subscriber['custom_fields'] = $custom; }
                }
                $tags = self::tags((string) ($s['tags'] ?? ''), $record);
                if ($tags) { $subscriber['tags'] = $tags; }
                self::request('POST', 'https://api.getdrip.com/v2/' . rawurlencode($list) . '/subscribers', ['Authorization'=>'Basic ' . base64_encode(self::require($credentials) . ':'),'Content-Type'=>'application/json'], ['subscribers'=>[$subscriber]]);
                return;
            case 'activecampaign':
                $body = ['email'=>$email,'p[' . $list . ']'=>$list,'status[' . $list . ']'=>1] + $data;
                $tags = self::tags((string) ($s['activecampaign_tags'] ?? ''), $record);
                if ($tags) { $body['tags'] = implode(',', $tags); }
                self::activecampaign_api($credentials, 'contact_sync', $body);
                return;
            case 'getresponse':
                $body = ['email'=>$email,'campaign'=>['campaignId'=>$list]];
                if (isset($data['name'])) { $body['name'] = $data['name']; unset($data['name']); }
                if (($s['getresponse_dayofcycle'] ?? '') !== '' && $s['getresponse_dayofcycle'] !== null) { $body['dayOfCycle'] = max(0, (int) $s['getresponse_dayofcycle']); }
                if ($data) { $body['customFieldValues'] = array_map(static fn($id, $value) => ['customFieldId'=>(string) $id,'value'=>[$value]], array_keys($data), array_values($data)); }
                try {
                    self::request('POST', 'https://api.getresponse.com/v3/contacts', ['X-Auth-Token'=>'api-key ' . self::require($credentials)] + $json, $body);
                } catch (\RuntimeException $e) {
                    if ($e->getCode() !== 409) { throw $e; } // Already on the list.
                }
                return;
            case 'convertkit':
                $body = ['api_key'=>self::require($credentials),'email'=>$email];
                if (isset($data['first_name'])) { $body['first_name'] = $data['first_name']; unset($data['first_name']); }
                if ($data) { $body['fields'] = $data; }
                $tags = array_values(array_filter(array_map('intval', (array) ($s['convertkit_tags'] ?? []))));
                if ($tags) { $body['tags'] = $tags; }
                self::request('POST', 'https://api.convertkit.com/v3/forms/' . rawurlencode($list) . '/subscribe', $json, $body);
                return;
            case 'mailerlite':
                $key = self::require($credentials);
                $resubscribe = ($s['allow_resubscribe'] ?? '') === 'yes';
                if (self::mailerlite_new($key)) {
                    $body = ['email'=>$email,'groups'=>[$list]] + ($data ? ['fields'=>$data] : []) + ($resubscribe ? ['status'=>'active'] : []);
                    self::request('POST', 'https://connect.mailerlite.com/api/subscribers', ['Authorization'=>'Bearer ' . $key] + $json, $body);
                    return;
                }
                $body = ['email'=>$email,'resubscribe'=>$resubscribe] + ($data ? ['fields'=>$data] : []);
                if (isset($data['name'])) { $body['name'] = $data['name']; }
                self::request('POST', 'https://api.mailerlite.com/api/v2/groups/' . rawurlencode($list) . '/subscribers', ['X-MailerLite-ApiKey'=>$key] + $json, $body);
                return;
        }
    }

    /** Fields of the record as name/value pairs for chat messages (no passwords, no empty values). */
    private static function chat_fields(array $record, int $limit): array {
        $out = [];
        foreach ($record as $field) {
            if ($field['type'] === 'password' || (string) $field['value'] === '') { continue; }
            $out[] = [$field['title'] !== '' ? $field['title'] : $field['id'], mb_substr((string) $field['value'], 0, $limit)];
        }
        return $out;
    }

    private static function webhook_url(string $url): string {
        $url = esc_url_raw(trim($url));
        if ($url === '' || !wp_http_validate_url($url)) { throw new \RuntimeException('La dirección del webhook no es válida.'); }
        return $url;
    }

    /** Slack or Discord message about the submission. Throws on failure. */
    public static function notify(string $service, array $s, array $record, array $context): void {
        $site = (string) get_bloginfo('name');
        $page = (string) ($context['meta']['page_url'] ?? '');
        $json = ['Content-Type'=>'application/json'];
        if ($service === 'slack') {
            $attachment = array_filter([
                'fallback'=>sprintf('Nuevo envío de «%s»', $context['form_name'] !== '' ? $context['form_name'] : 'Formulario'),
                'color'=>sanitize_hex_color((string) ($s['slack_webhook_color'] ?? '')) ?: '#D30C5C',
                'pretext'=>Handler::replace((string) ($s['slack_pretext'] ?? ''), $record),
                'title'=>Handler::replace((string) ($s['slack_title'] ?? ''), $record),
                'title_link'=>$page,
                'text'=>Handler::replace((string) ($s['slack_text'] ?? ''), $record),
            ], static fn($v) => $v !== '');
            if (($s['slack_add_fields'] ?? 'yes') === 'yes') { $attachment['fields'] = array_map(static fn($f) => ['title'=>$f[0],'value'=>$f[1],'short'=>false], self::chat_fields($record, 2000)); }
            if (($s['slack_add_ts'] ?? 'yes') === 'yes') { $attachment['footer'] = $site; $attachment['ts'] = time(); }
            $body = array_filter(['channel'=>trim((string) ($s['slack_channel'] ?? '')),'username'=>trim((string) ($s['slack_username'] ?? ''))]) + ['attachments'=>[$attachment]];
            self::request('POST', self::webhook_url((string) ($s['slack_webhook'] ?? '')), $json, $body);
            return;
        }
        $color = sanitize_hex_color((string) ($s['discord_webhook_color'] ?? '')) ?: '#D30C5C';
        $embed = array_filter([
            'title'=>mb_substr(Handler::replace((string) ($s['discord_title'] ?? ''), $record) ?: sprintf('Nuevo envío de «%s»', $context['form_name'] !== '' ? $context['form_name'] : 'Formulario'), 0, 256),
            'description'=>mb_substr(Handler::replace((string) ($s['discord_content'] ?? ''), $record), 0, 4000),
            'url'=>$page,
            'color'=>hexdec(ltrim($color, '#')),
        ], static fn($v) => $v !== '');
        if (($s['discord_form_data'] ?? 'yes') === 'yes') { $embed['fields'] = array_slice(array_map(static fn($f) => ['name'=>mb_substr($f[0], 0, 256),'value'=>$f[1],'inline'=>false], self::chat_fields($record, 1024)), 0, 25); }
        if (($s['discord_ts'] ?? 'yes') === 'yes') { $embed['timestamp'] = gmdate('c'); $embed['footer'] = ['text'=>$site]; }
        $avatar = esc_url_raw(trim((string) ($s['discord_avatar_url'] ?? '')));
        $body = array_filter(['username'=>mb_substr(trim((string) ($s['discord_username'] ?? '')), 0, 80),'avatar_url'=>$avatar]) + ['embeds'=>[$embed]];
        self::request('POST', self::webhook_url((string) ($s['discord_webhook'] ?? '')), $json, $body);
    }

    /** What the browser does with popups after a successful submission. */
    public static function popup(array $s): array {
        $action = (string) ($s['popup_action'] ?? '');
        if ($action === 'open' && ($id = absint($s['popup_action_popup_id'] ?? 0))) { return ['action'=>'open','id'=>$id]; }
        if ($action === 'close') { return ['action'=>'close','do_not_show_again'=>($s['popup_action_do_not_show_again'] ?? '') === 'yes']; }
        return [];
    }
}
