<?php
/** Theme Builder integration tests. Run with `wp eval-file`. */
if (!defined('WP_CLI') || !WP_CLI) { exit; }

use Elementor\Plugin as Elementor;
use OldBearExpansion\ThemeBuilder\Conditions;
use OldBearExpansion\ThemeBuilder\Locations;
use OldBearExpansion\ThemeBuilder\Location_Document;

$checks = 0;
$assert = static function ($ok, string $message) use (&$checks) {
    if (!$ok) { fwrite(STDERR, "Fallo: {$message}\n"); throw new RuntimeException($message); }
    $checks++;
};
$admin = get_users(['role'=>'administrator','number'=>1]);
wp_set_current_user($admin[0]->ID);

/** Simulates the main query of a request. */
$go = static function (array $args, bool $not_found = false): void {
    global $wp_query, $wp_the_query, $post;
    $wp_the_query = $wp_query = new WP_Query($args);
    if ($not_found) { $wp_query->set_404(); }
    $post = $wp_query->posts[0] ?? null;
    Locations::reset();
};
$uid = static fn() => substr(str_replace('-', '', wp_generate_uuid4()), 0, 8);
$widget = static fn(string $type, array $settings = []) => ['id'=>$uid(),'elType'=>'widget','widgetType'=>$type,'settings'=>$settings,'elements'=>[]];
$created = [];
$types = ['header'=>'header','footer'=>'footer','single'=>'single-post','archive'=>'archive','error-404'=>'error-404'];
$template = static function (string $location, array $rules, array $elements = []) use (&$created, $uid, $types) {
    $document = Elementor::$instance->documents->create($types[$location], ['post_title'=>'obx-test ' . $location,'post_status'=>'publish']);
    if (is_wp_error($document)) { throw new RuntimeException($document->get_error_message()); }
    $rows = array_map(static fn($r) => ['_id'=>$uid(),'mode'=>$r[0],'rule'=>$r[1],'object_id'=>$r[2] ?? ''], $rules);
    $document->save(['elements'=>$elements ?: [['id'=>$uid(),'elType'=>'container','isInner'=>false,'settings'=>[],'elements'=>[]]],'settings'=>['obx_conditions'=>$rows]]);
    $created[] = $document->get_main_id();
    return $document->get_main_id();
};

// Document types.
foreach (['header','footer','single-post','single-page','single','archive','search-results','error-404'] as $type) {
    $class = Elementor::$instance->documents->get_document_type($type, false);
    $assert($class && is_subclass_of($class, Location_Document::class), "Tipo de documento no registrado: {$type}");
}
$assert(function_exists('elementor_theme_do_location'), 'Falta la API de compatibilidad para temas.');

// Pure condition helpers.
$assert(Conditions::ids(' 3, 5,abc, 3 ') === [3, 5], 'Los IDs deben normalizarse.');
$assert(Conditions::normalize([['mode'=>'hack','rule'=>'singular/post','object_id'=>'9']]) === [['mode'=>'include','rule'=>'singular/post','ids'=>[9]]], 'Las filas deben normalizarse.');
$assert(Conditions::normalize([['rule'=>'in/category','object_id'=>'']]) === [], '«Con término» sin IDs debe descartarse.');
$assert(Conditions::normalize([['rule'=>'../../etc']]) === [], 'Las reglas inválidas deben descartarse.');

$prefix = 'obxtest' . strtolower(wp_generate_password(6, false));
$term = wp_insert_term($prefix, 'category');
$post_a = wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>$prefix . ' A <script>alert(1)</script>','post_content'=>'Contenido ' . $prefix,'post_category'=>[$term['term_id']]]);
$post_b = wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>$prefix . ' B']);
$page = wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$prefix . ' página']);
$existing = Locations::index();
try {
    $general = $template('header', [['include','general']]);
    $singles = $template('header', [['include','singular']]);
    $specific = $template('header', [['include','singular/post', (string) $post_b]]);
    $excluded = $template('footer', [['include','general'], ['exclude','singular/page']]);
    $single = $template('single', [['include','singular/post']], [['id'=>$uid(),'elType'=>'container','isInner'=>false,'settings'=>['css_classes'=>'obx-test-single'],'elements'=>[$widget('obx-post-title', ['tag'=>'h1']), $widget('obx-post-content'), $widget('obx-post-info')]]]);
    $in_term = $template('single', [['include','in/category', (string) $term['term_id']]]);
    $archive = $template('archive', [['include','taxonomy/category', (string) $term['term_id']]]);
    $error = $template('error-404', []);

    $go(['p'=>$post_a]);
    $assert(is_singular('post'), 'La consulta simulada debe ser una entrada.');
    $assert(Locations::template_id('header') === $singles, 'En una entrada gana «Todo contenido individual» sobre «Todo el sitio».');
    $assert(Locations::template_id('single') === $in_term, '«Con término» es más específico que el tipo de contenido.');
    $assert(Locations::template_id('footer') === $excluded, 'El pie general aplica a las entradas.');

    $go(['p'=>$post_b]);
    $assert(Locations::template_id('header') === $specific, 'Un ID concreto gana a las reglas generales.');
    $assert(Locations::template_id('single') === $single, 'Sin término, aplica la plantilla de entradas.');

    $go(['page_id'=>$page]);
    $assert(Locations::template_id('footer') !== $excluded, 'La exclusión debe impedir el pie en páginas.');
    $assert(Locations::template_id('single') === 0, 'Las plantillas de entradas no aplican a páginas.');

    $go(['cat'=>$term['term_id']]);
    $assert(is_category(), 'La consulta simulada debe ser un archivo de categoría.');
    $assert(Locations::template_id('archive') === $archive, 'La plantilla de archivo de la categoría debe aplicarse.');
    $assert(Locations::template_id('header') === $general, 'En un archivo aplica la cabecera general.');

    $go(['p'=>999999999], true);
    $assert(is_404(), 'La consulta simulada debe ser 404.');
    $assert(Locations::template_id('error-404') >= $error, 'La plantilla 404 debe aplicarse.');
    $assert(Locations::main_location() === 'error-404', 'La ubicación principal de un 404 es error-404.');

    // Rendering with real widgets and escaping.
    $go(['p'=>$post_a]);
    $html = Elementor::$instance->frontend->get_builder_content_for_display($single);
    $assert(str_contains($html, 'obx-test-single') && preg_match('~<h1 class="elementor-heading-title [^"]*obx-post-title~', $html), 'La plantilla debe renderizar los widgets de tema.');
    $assert(str_contains($html, 'Contenido ' . $prefix), 'El contenido de la entrada debe aparecer.');
    $assert(!str_contains($html, '<script>alert(1)</script>') && str_contains($html, '&lt;script&gt;'), 'El título debe escaparse.');
    $assert(str_contains($html, $prefix), 'Los metadatos deben incluir la categoría.');

    // Index cache follows template lifecycle.
    wp_trash_post($specific);
    $go(['p'=>$post_b]);
    $assert(Locations::template_id('header') === $singles, 'Al enviar una plantilla a la papelera debe dejar de aplicarse.');
    wp_update_post(['ID'=>$general,'post_status'=>'draft']);
    $go(['cat'=>$term['term_id']]);
    $assert(Locations::template_id('header') !== $general, 'Los borradores no se aplican.');

    // Editor preview context.
    $GLOBALS['post'] = get_post($single);
    $assert(\OldBearExpansion\Context::template() instanceof Location_Document, 'El contexto debe detectar la plantilla en edición.');
    $assert(\OldBearExpansion\Context::post() instanceof WP_Post && \OldBearExpansion\Context::post()->post_type === 'post', 'La vista previa debe usar una entrada real.');

    // Elementor Pro format: strings in _elementor_conditions, kept in sync with the editor rows.
    $strings = ['include/singular/post/' . $post_b, 'include/archive/category/' . $term['term_id'], 'exclude/singular/page', 'include/singular/not_found404'];
    $assert(Conditions::to_strings(Conditions::from_strings($strings)) === $strings, 'Las condiciones de Pro deben convertirse sin pérdidas.');
    $assert(Conditions::from_strings([['type'=>'include','name'=>'singular','sub_name'=>'in_category','sub_id'=>(string) $term['term_id']]]) === [['mode'=>'include','rule'=>'singular/in_category','ids'=>[(int) $term['term_id']]]], 'Las condiciones en formato de manifiesto deben leerse.');
    $assert(Conditions::from_strings(['include/singular/in_category', 'include/../../x', 'other']) === [], 'Las condiciones incompletas o inválidas deben descartarse.');
    $saved = get_post_meta($specific, Conditions::META, true);
    $assert($saved === ['include/singular/post/' . $post_b], 'Al guardar, las condiciones deben escribirse también en el formato de Pro.');
    $assert(get_post_meta($in_term, Conditions::META, true) === ['include/singular/in_category/' . $term['term_id']], 'Las reglas antiguas deben guardarse con los nombres de Pro.');

    // A template made with Pro: only the meta, no editor rows.
    $pro_doc = Elementor::$instance->documents->create('single-page', ['post_title'=>'obx-test pro','post_status'=>'publish']);
    $pro_page = $pro_doc->get_main_id();
    $created[] = $pro_page;
    $pro_doc->save(['elements'=>[['id'=>$uid(),'elType'=>'container','isInner'=>false,'settings'=>[],'elements'=>[]]]]);
    delete_post_meta($pro_page, '_elementor_page_settings');
    update_post_meta($pro_page, Conditions::META, ['include/singular/page/' . $page]);
    Locations::flush();
    $go(['page_id'=>$page]);
    $assert(Locations::template_id('single') === $pro_page, 'Una plantilla con condiciones de Pro debe aplicarse.');
    delete_post_meta($pro_page, Conditions::META);
    Locations::flush();
    $go(['page_id'=>$page]);
    $assert(Locations::template_id('single') === 0, 'Sin condiciones, una plantilla no se muestra.');

    // Templates from earlier versions are migrated to Elementor Pro's types.
    $legacy = wp_insert_post(['post_type'=>'elementor_library','post_status'=>'publish','post_title'=>'obx-test legacy']);
    $created[] = $legacy;
    update_post_meta($legacy, '_elementor_template_type', 'obx-footer');
    update_post_meta($legacy, '_elementor_edit_mode', 'builder');
    update_post_meta($legacy, '_elementor_page_settings', ['obx_conditions'=>[['_id'=>'a1','mode'=>'include','rule'=>'taxonomy/category','object_id'=>(string) $term['term_id']]]]);
    $assert(\OldBearExpansion\ThemeBuilder\Upgrade::run() >= 1, 'La migración debe convertir las plantillas antiguas.');
    $assert(get_post_meta($legacy, '_elementor_template_type', true) === 'footer' && has_term('footer', 'elementor_library_type', $legacy), 'El tipo antiguo debe pasar a «footer».');
    $assert(get_post_meta($legacy, Conditions::META, true) === ['include/archive/category/' . $term['term_id']], 'La migración debe escribir las condiciones de Pro.');
} finally {
    foreach ($created as $id) { wp_delete_post($id, true); }
    foreach ([$post_a, $post_b, $page] as $id) { wp_delete_post($id, true); }
    if (!is_wp_error($term)) { wp_delete_term($term['term_id'], 'category'); }
    Locations::flush();
}
$assert(array_keys(Locations::index()) === array_keys($existing), 'El índice debe reconstruirse tras limpiar.');
WP_CLI::success("{$checks} pruebas del Theme Builder superadas.");
