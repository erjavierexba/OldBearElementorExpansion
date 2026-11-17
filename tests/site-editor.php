<?php
/** Theme Builder inside Elementor's app (#/site-editor): REST API, links and stored conditions. Run with `wp eval-file`. */
if (!defined('WP_CLI') || !WP_CLI) { exit; }

use Elementor\Plugin as Elementor;
use OldBearExpansion\ThemeBuilder\Admin;
use OldBearExpansion\ThemeBuilder\Conditions;
use OldBearExpansion\ThemeBuilder\Location_Document;
use OldBearExpansion\ThemeBuilder\Locations;
use OldBearExpansion\ThemeBuilder\Site_Editor;
use OldBearExpansion\ThemeBuilder\Upgrade;

$checks = 0; $created = [];
$assert = static function ($ok, string $message) use (&$checks) {
    if (!$ok) { fwrite(STDERR, "Fallo: {$message}\n"); throw new RuntimeException($message); }
    $checks++;
};
$admin = get_users(['role'=>'administrator','number'=>1]);
wp_set_current_user($admin[0]->ID);
$rest = static function (string $method, string $route, array $body = []) {
    $request = new WP_REST_Request($method, '/obx/v1/site-editor' . $route);
    if ($body) { $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($body)); }
    return rest_do_request($request);
};
$new = static function (string $type, string $title, array $conditions) use (&$created): int {
    $document = Elementor::$instance->documents->create($type, ['post_title'=>$title,'post_status'=>'publish']);
    if (is_wp_error($document)) { throw new RuntimeException($document->get_error_message()); }
    $id = (int) $document->get_main_id();
    $created[] = $id;
    Location_Document::store_conditions($id, $conditions);
    return $id;
};

try {
    // Elementor's app opens our routes: menu, admin bar, finder and the editor use this URL.
    $assert(str_ends_with((string) Elementor::$instance->app->get_settings('menu_url'), '#/site-editor'), 'La URL del Theme Builder de Elementor debe abrir #/site-editor.');
    $assert(str_contains(Site_Editor::url(), 'page=elementor-app#/site-editor'), 'Site_Editor::url() debe apuntar a la app de Elementor.');
    $url = Site_Editor::create_url('header');
    $assert(!str_contains($url, '&amp;') && str_contains($url, 'type=header') && str_contains($url, '_wpnonce='), 'El enlace de creación debe ser una URL cruda con tipo y nonce.');
    $config = Site_Editor::config();
    foreach (array_keys(Site_Editor::PARTS) as $part) { $assert(!empty($config['create'][$part]), "Falta el enlace de creación de {$part}."); }
    $assert(!empty($config['rules']['single']) && !empty($config['rules']['archive']) && $config['rules']['header'][0]['value'] === 'general', 'Faltan las reglas de condiciones para la app.');
    $assert(Admin::default_rule(\OldBearExpansion\ThemeBuilder\Search_Results_Document::class) === 'archive/search' && Admin::default_rule(\OldBearExpansion\ThemeBuilder\Single_Page_Document::class) === 'singular/page', 'Cada tipo nuevo debe nacer con su condición lógica.');
    // Dark from the first paint (no white frame before Elementor applies its theme).
    $assert(Site_Editor::theme_css('light') === '', 'El tema claro no debe cambiar nada.');
    $assert(str_contains(Site_Editor::theme_css('dark'), 'color-scheme:dark') && str_contains(Site_Editor::theme_css('dark'), '#1f2124') && !str_contains(Site_Editor::theme_css('dark'), '<script'), 'El tema oscuro debe pintarse desde el principio.');
    $auto = Site_Editor::theme_css('auto');
    $assert(str_contains($auto, '@media (prefers-color-scheme:dark)') && str_contains($auto, 'eps-theme-dark') && str_contains($auto, 'MutationObserver'), 'El tema automático debe seguir al sistema desde el principio.');
    $assert(in_array(Site_Editor::ui_theme(), ['light','dark','auto'], true), 'La preferencia de tema debe leerse de Elementor.');
    $routes = rest_get_server()->get_routes();
    $assert(isset($routes['/obx/v1/site-editor/templates']) && isset($routes['/obx/v1/site-editor/templates/(?P<id>\d+)']), 'Faltan las rutas REST del Theme Builder.');

    // Listing: every Theme Builder part, never popups.
    $header = $new('header', 'obx-se cabecera', [['mode'=>'include','rule'=>'general','ids'=>[]]]);
    $search = $new('search-results', 'obx-se búsqueda', [['mode'=>'include','rule'=>'archive/search','ids'=>[]]]);
    $popup = $new('popup', 'obx-se popup', [['mode'=>'include','rule'=>'general','ids'=>[]]]);
    $response = $rest('GET', '/templates');
    $assert($response->get_status() === 200, 'GET templates debe responder 200.');
    $items = array_column($response->get_data(), null, 'id');
    $assert(isset($items[$header], $items[$search]) && !isset($items[$popup]), 'El listado debe incluir las plantillas del Theme Builder y no los popups.');
    $assert($items[$header]['part'] === 'header' && $items[$header]['active'] && $items[$header]['instances'] === 'Todo el sitio', 'La cabecera debe aparecer activa en todo el sitio.');
    $assert($items[$search]['part'] === 'search-results' && str_contains($items[$search]['urls']['edit'], 'action=elementor'), 'Cada plantilla debe enlazar con su editor.');

    // Conditions, rename and delete.
    $response = $rest('POST', '/templates/' . $header, ['conditions'=>[['mode'=>'include','rule'=>'singular/page','ids'=>''],['mode'=>'exclude','rule'=>'singular/front_page','ids'=>'']]]);
    $assert($response->get_status() === 200, 'POST condiciones debe responder 200.');
    $assert(get_post_meta($header, Conditions::META, true) === ['include/singular/page','exclude/singular/front_page'], 'Las condiciones deben guardarse en el formato de Elementor Pro.');
    $assert(count(Elementor::$instance->documents->get($header, false)->get_conditions()) === 2, 'El editor debe ver las condiciones guardadas desde la app.');
    $assert(isset(Locations::index()['header'][$header]) && count(Locations::index()['header'][$header]) === 2, 'El índice de ubicaciones debe refrescarse.');
    $rest('POST', '/templates/' . $header, ['title'=>'obx-se renombrada']);
    $assert(get_the_title($header) === 'obx-se renombrada', 'Renombrar debe cambiar el título.');
    $response = $rest('DELETE', '/templates/' . $search);
    $assert($response->get_status() === 200 && get_post_status($search) === 'trash', 'Eliminar debe mover la plantilla a la papelera.');

    // Permissions: a subscriber cannot list or change templates.
    $subscriber = wp_insert_user(['user_login'=>'obx_se_' . wp_generate_password(6, false),'user_pass'=>wp_generate_password(),'role'=>'subscriber']);
    wp_set_current_user($subscriber);
    $assert($rest('GET', '/templates')->get_status() === 403 && $rest('POST', '/templates/' . $header, ['title'=>'x'])->get_status() === 403, 'Un suscriptor no debe acceder al Theme Builder.');
    wp_set_current_user($admin[0]->ID);
    wp_delete_user($subscriber);

    // Elementor saves repeater rows without the values equal to the control default.
    $assert(Conditions::normalize([['_id'=>'a1']], 'general') === [['mode'=>'include','rule'=>'general','ids'=>[]]], 'Una fila sin valores por defecto es «Incluir · Todo el sitio».');
    $footer = $new('footer', 'obx-se pie', [['mode'=>'include','rule'=>'general','ids'=>[]]]);
    update_post_meta($footer, '_elementor_page_settings', [Location_Document::CONDITIONS=>[['_id'=>'b2'],['_id'=>'c3','mode'=>'exclude','rule'=>'singular/front_page']]]);
    $conditions = Elementor::$instance->documents->get($footer, false)->get_conditions();
    $assert(count($conditions) === 2 && $conditions[0]['rule'] === 'general' && $conditions[1]['mode'] === 'exclude', 'Las condiciones guardadas desde el editor no deben perder la fila por defecto.');
    update_post_meta($footer, Conditions::META, []);
    $assert(Upgrade::repair_stripped_rows() >= 1 && get_post_meta($footer, Conditions::META, true) === ['include/general','exclude/singular/front_page'], 'La migración debe reparar las condiciones ya guardadas.');
    $rows = get_post_meta($footer, '_elementor_page_settings', true)[Location_Document::CONDITIONS];
    $assert(($rows[0]['rule'] ?? '') === 'general', 'La migración debe reescribir las filas completas.');
} finally {
    foreach (array_reverse($created) as $id) { if (get_post($id)) { wp_delete_post($id, true); } }
    Locations::flush();
}
WP_CLI::success("{$checks} pruebas del Theme Builder en la app de Elementor superadas.");
