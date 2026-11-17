<?php
/** Development demo content. Idempotent: run with `wp eval-file`. */
if (!defined('WP_CLI') || !WP_CLI) { exit; }

use Elementor\Plugin as Elementor;

$admin = get_users(['role'=>'administrator','number'=>1]);
wp_set_current_user($admin[0]->ID);
update_option('elementor_experiment-container', 'active');
update_option('elementor_disable_color_schemes', 'yes');
update_option('elementor_disable_typography_schemes', 'yes');
if (get_option('obx_dev_seeded')) { WP_CLI::success('La demo ya estaba preparada.'); return; }

$uid = static fn() => substr(str_replace('-', '', wp_generate_uuid4()), 0, 8);
$widget = static fn(string $type, array $settings = []) => ['id'=>$uid(),'elType'=>'widget','widgetType'=>$type,'settings'=>$settings,'elements'=>[]];
$box = static fn(array $elements, array $settings = []) => ['id'=>$uid(),'elType'=>'container','isInner'=>false,'settings'=>$settings + ['content_width'=>'boxed','flex_direction'=>'column'],'elements'=>$elements];

$category = term_exists('Diario', 'category') ?: wp_insert_term('Diario', 'category');
$category_id = (int) (is_array($category) ? $category['term_id'] : $category);
foreach ([
    ['Hola desde la madriguera', 'Primera entrada de ejemplo: el Theme Builder pinta título, metadatos y contenido desde una plantilla de Elementor.'],
    ['Cómo diseñamos un mundo', 'Segunda entrada de ejemplo para el listado del archivo y la paginación.'],
    ['Notas de desarrollo', 'Tercera entrada de ejemplo. Las entradas reales se escriben con el editor de siempre.'],
] as [$title, $text]) {
    if (!get_page_by_path(sanitize_title($title), OBJECT, 'post')) {
        wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>$title,'post_content'=>'<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->','post_category'=>[$category_id]]);
    }
}
$about = get_page_by_path('sobre-nosotros') ?: get_post(wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Sobre nosotros','post_content'=>'<p>Página de ejemplo.</p>']));

$menu_id = wp_create_nav_menu('Principal');
if (is_wp_error($menu_id)) { $menu_id = wp_get_nav_menu_object('Principal')->term_id; }
else {
    wp_update_nav_menu_item($menu_id, 0, ['menu-item-title'=>'Inicio','menu-item-url'=>home_url('/'),'menu-item-status'=>'publish']);
    wp_update_nav_menu_item($menu_id, 0, ['menu-item-title'=>'Diario','menu-item-object'=>'category','menu-item-object-id'=>$category_id,'menu-item-type'=>'taxonomy','menu-item-status'=>'publish']);
    wp_update_nav_menu_item($menu_id, 0, ['menu-item-title'=>'Sobre nosotros','menu-item-object'=>'page','menu-item-object-id'=>$about->ID,'menu-item-type'=>'post_type','menu-item-status'=>'publish']);
}

$row = ['flex_direction'=>'row','flex_justify_content'=>'space-between','flex_align_items'=>'center','flex_wrap'=>'wrap','padding'=>['unit'=>'px','top'=>'20','right'=>'20','bottom'=>'20','left'=>'20','isLinked'=>false]];
$section = ['padding'=>['unit'=>'px','top'=>'60','right'=>'20','bottom'=>'60','left'=>'20','isLinked'=>false]];
$templates = [
    'header'=>['Cabecera demo', [$box([$widget('obx-site-title', ['tag'=>'p','tagline'=>'yes']), $widget('obx-nav-menu', ['menu'=>(string) $menu_id]), $widget('obx-search-form')], $row + ['css_classes'=>'obx-demo-header'])], 'general'],
    'footer'=>['Pie demo', [$box([$widget('text-editor', ['editor'=>'<p>Pie de página creado con OldBear Expansion.</p>']), $widget('obx-site-title', ['tag'=>'p'])], $row + ['css_classes'=>'obx-demo-footer'])], 'general'],
    'single'=>['Entrada demo', [$box([$widget('obx-post-title'), $widget('obx-post-info'), $widget('obx-featured-image'), $widget('obx-post-content')], $section + ['css_classes'=>'obx-demo-single'])], 'singular/post'],
    'archive'=>['Archivo demo', [$box([$widget('obx-archive-title'), $widget('obx-archive-posts')], $section + ['css_classes'=>'obx-demo-archive'])], 'archive'],
    'error-404'=>['404 demo', [$box([$widget('heading', ['title'=>'Página no encontrada','header_size'=>'h1']), $widget('text-editor', ['editor'=>'<p>Prueba a buscar lo que necesitas.</p>']), $widget('obx-search-form')], $section + ['css_classes'=>'obx-demo-404'])], 'not_found'],
];
foreach ($templates as $location => [$title, $elements, $rule]) {
    $document = Elementor::$instance->documents->create('obx-' . $location, ['post_title'=>$title,'post_status'=>'publish']);
    if (is_wp_error($document)) { WP_CLI::error($document->get_error_message()); }
    $settings = $location === 'error-404' ? [] : ['obx_conditions'=>[['_id'=>$uid(),'mode'=>'include','rule'=>$rule,'object_id'=>'']]];
    if (!$document->save(['elements'=>$elements,'settings'=>$settings])) { WP_CLI::error('No se pudo guardar ' . $title); }
}
update_option('obx_dev_seeded', 1);
WP_CLI::success('Demo creada: entradas, menú y cinco plantillas del Theme Builder.');
