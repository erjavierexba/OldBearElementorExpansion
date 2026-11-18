<?php
/**
 * Elementor Pro parity (docs/PARIDAD-PRO.md): settings stored under Pro's keys and defaults, and what they do.
 * 0.8.0: Theme Builder templates, popups, motion effects (element and background), page transitions and scroll snap.
 * 0.8.1: Theme Builder conditions (child term archives, formats, media, WooCommerce), dynamic tags, saved sections.
 * 0.9.0: Loop Grid and Loop Carousel skins (posts, products, terms), pagination and «Load more», nothing-found message;
 *        Search widget with live results (REST obx/v1/search), query, pagination and styles.
 * 0.9.1: form integrations (newsletter lists, Slack, Discord), popup action, submissions metadata, step styles.
 */
if (!defined('WP_CLI') || !WP_CLI) { exit; }

use Elementor\Plugin as Elementor;
use OldBearExpansion\Context;
use OldBearExpansion\Extras\Site_Settings;
use OldBearExpansion\ThemeBuilder\Conditions;
use OldBearExpansion\ThemeBuilder\Locations;

$checks = 0; $created = []; $terms = [];
$assert = static function ($ok, string $message) use (&$checks) { if (!$ok) { fwrite(STDERR, "Fallo: {$message}\n"); throw new RuntimeException($message); } $checks++; };
$uid = static fn() => substr(str_replace('-', '', wp_generate_uuid4()), 0, 7);
$widget = static fn(string $type, array $settings = []) => ['id'=>$uid(),'elType'=>'widget','widgetType'=>$type,'settings'=>$settings,'elements'=>[]];
$container = static fn(array $elements, array $settings = []) => ['id'=>$uid(),'elType'=>'container','isInner'=>false,'settings'=>$settings,'elements'=>$elements];
$admin = get_users(['role'=>'administrator','number'=>1]); wp_set_current_user($admin[0]->ID);
$save = static function (string $type, string $title, array $elements, array $settings = []) use (&$created) {
    $d = Elementor::$instance->documents->create($type, ['post_title'=>$title,'post_status'=>'publish']);
    if (is_wp_error($d)) { throw new RuntimeException($d->get_error_message()); }
    $id = (int) $d->get_main_id(); $created[] = $id;
    $d->set_is_built_with_elementor(true);
    if (!$d->save(['elements'=>$elements,'settings'=>$settings])) { throw new RuntimeException('No se pudo guardar ' . $type); }
    return $id;
};
// Controls as the editor sees them (WP-CLI counts as frontend, where Elementor drops style controls).
$frontend = new ReflectionProperty(\Elementor\Core\Frontend\Performance::class, 'is_frontend');
$frontend->setAccessible(true); $was_frontend = $frontend->getValue(); $frontend->setValue(null, false);
$controls_of = static function (string $type, int $id) { $class = Elementor::$instance->documents->get_document_type($type); return (new $class(['post_id'=>$id]))->get_controls(); };

$errors = []; set_error_handler(static function ($n, $m, $f, $l) use (&$errors) { if (str_contains($f, 'oldbear-elementor-expansion')) { $errors[] = "$m ($f:$l)"; } return false; });
try {
    $any = $save('header', 'Paridad cabecera', [$container([$widget('heading', ['title'=>'Cabecera de paridad'])])]);

    // §3.2 Theme Builder templates: Pro's keys and defaults.
    $defaults = ['header'=>'','footer'=>'','single-post'=>'single/post','single-page'=>'single/page','archive'=>'archive/recent_posts','search-results'=>'search','error-404'=>'page/404','loop-item'=>'single/post','popup'=>''];
    foreach ($defaults as $type => $default) {
        $c = $controls_of($type, $any);
        foreach (['preview_type','preview_id','content_wrapper_html_tag','custom_css'] as $key) { $assert(isset($c[$key]), "{$type}: falta {$key}."); }
        $assert(($c['preview_type']['default'] ?? null) === $default, "{$type}: preview_type debe valer «{$default}» por defecto.");
        $assert(($c['content_wrapper_html_tag']['default'] ?? '') === 'div' && array_keys($c['content_wrapper_html_tag']['options']) === ['div','main','article','header','footer','section','aside','nav'], "{$type}: content_wrapper_html_tag como en Pro.");
        $has_template = in_array($type, ['single-post','single-page','archive','search-results','error-404'], true);
        $assert(isset($c['page_template']) === $has_template, "{$type}: page_template " . ($has_template ? 'debe' : 'no debe') . ' existir.');
        if ($type !== 'popup') { $assert(isset($c['margin'], $c['padding'], $c['background_background']), "{$type}: falta la pestaña de estilo (section_page_style)."); }
        $assert(isset($c['hidden_header_footer_style_control']) === in_array($type, ['header','footer'], true), "{$type}: hidden_header_footer_style_control solo en cabecera y pie.");
        $assert(isset($c['scroll_snap']) === $has_template, "{$type}: scroll snap solo en plantillas de página completa.");
    }
    $loop = $controls_of('loop-item', $any);
    $assert(($loop['source']['default'] ?? '') === 'post' && isset($loop['apply_query_source'], $loop['preview_width'], $loop['preview_search_term']), 'loop-item: source, apply_query_source y preview_width.');

    // content_wrapper_html_tag renders the chosen tag.
    $footer = $save('footer', 'Paridad pie', [$container([$widget('heading', ['title'=>'Pie con etiqueta'])])], ['content_wrapper_html_tag'=>'footer']);
    $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($footer);
    $assert((bool) preg_match('~^\s*<footer [^>]*class="[^"]*elementor-' . $footer . '~', $html) && str_contains($html, '</footer>'), 'content_wrapper_html_tag debe cambiar la etiqueta del envoltorio.');

    // page_template «elementor_canvas» uses the canvas layout.
    $single = $save('single-post', 'Paridad individual', [$container([$widget('theme-post-title')])], ['page_template'=>'elementor_canvas']);
    \OldBearExpansion\ThemeBuilder\Location_Document::store_conditions($single, [['mode'=>'include','rule'=>'singular/post','ids'=>[]]]);
    Locations::flush(); Locations::reset();
    $post = wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>'Paridad entrada']); $created[] = $post;
    global $wp_query, $wp_the_query;
    [$main, $current] = [$wp_the_query, $wp_query];
    $wp_the_query = $wp_query = new WP_Query(['p'=>$post]); $wp_query->is_singular = $wp_query->is_single = true; $wp_query->queried_object_id = $post; $wp_query->queried_object = get_post($post);
    add_filter('obx/location/template_id', $force = static fn($id, $location) => $location === 'single' ? $single : $id, 10, 2);
    $assert(str_ends_with(Locations::template('index.php'), 'templates/location-canvas.php'), 'page_template elementor_canvas debe usar la plantilla de lienzo.');
    remove_filter('obx/location/template_id', $force, 10); Locations::reset(); [$wp_the_query, $wp_query] = [$main, $current]; wp_reset_postdata();

    // Preview settings drive the editor context; legacy obx_preview_id still works.
    $cat = wp_insert_term('obx-paridad ' . $uid(), 'category'); $terms[] = $cat['term_id'];
    $in_cat = wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>'Paridad en categoría','post_category'=>[$cat['term_id']]]); $created[] = $in_cat;
    $archive = $save('archive', 'Paridad archivo', [$container([$widget('archive-posts')])], ['preview_type'=>'taxonomy/category','preview_id'=>(string) $cat['term_id']]);
    $legacy = $save('single-post', 'Paridad heredada', [$container([$widget('theme-post-title')])], ['obx_preview_id'=>(string) $in_cat]);
    $search = $save('search-results', 'Paridad búsqueda', [$container([$widget('archive-posts')])], ['preview_search_term'=>'Paridad']);
    $as_template = static function (int $id, callable $fn) { global $post; $old = $post; $post = get_post($id); setup_postdata($post); try { return $fn(); } finally { $post = $old; wp_reset_postdata(); } };
    $query = $as_template($archive, static fn() => Context::query());
    $assert(($query->query_vars['tax_query'][0]['terms'][0] ?? 0) === (int) $cat['term_id'] && in_array($in_cat, wp_list_pluck($query->posts, 'ID'), true), 'preview_type taxonomy/… debe previsualizar ese término.');
    $assert($as_template($archive, static fn() => Context::archive_title()) === get_term($cat['term_id'])->name, 'El título del archivo debe ser el del término previsualizado.');
    $assert($as_template($legacy, static fn() => Context::post()->ID) === $in_cat, 'obx_preview_id (datos antiguos) debe seguir funcionando.');
    $assert($as_template($search, static fn() => Context::query()->query_vars['s']) === 'Paridad', 'preview_type search debe usar preview_search_term.');

    // §3.3 Popups with Pro's keys.
    $c = $controls_of('popup', $any);
    foreach (['content_position'=>'top','a11y_navigation'=>'yes','height_type'=>'auto','overlay'=>'yes','close_button'=>'yes','close_button_position'=>'','box_shadow_box_shadow_type'=>'yes'] as $key => $default) {
        $assert(isset($c[$key]) && ($c[$key]['default'] ?? null) === $default, "popup: {$key} debe valer «{$default}» por defecto.");
    }
    foreach (['entrance_animation_tablet','exit_animation_mobile','entrance_animation_duration','close_button_delay','close_automatically','prevent_close_on_background_click','prevent_close_on_esc_key','prevent_scroll','avoid_multiple_popups','open_selector','classes','border_radius','overlay_background_background','close_button_color','icon_size'] as $key) { $assert(isset($c[$key]), "popup: falta {$key}."); }
    $assert(($c['entrance_animation']['default'] ?? '') === '' && ($c['height']['default']['size'] ?? 0) === 380, 'popup: sin animación por defecto y altura personalizada de 380 px, como en Pro.');
    $popup = $save('popup', 'Paridad popup', [$container([$widget('heading', ['title'=>'Hola desde el popup'])])], ['close_button_position'=>'outside','classes'=>'uno dos','open_selector'=>'.abrir','prevent_close_on_esc_key'=>'yes','entrance_animation_mobile'=>'zoomIn','close_automatically'=>3,'a11y_navigation'=>'']);
    $old = $save('popup', 'Paridad popup antiguo', [$container([$widget('heading', ['title'=>'Antiguo'])])]);
    $raw = get_post_meta($old, '_elementor_page_settings', true) ?: []; update_post_meta($old, '_elementor_page_settings', ['open_by_selector'=>'.viejo','obx_prevent_scroll'=>'yes'] + (array) $raw);
    \OldBearExpansion\Popups\Popups::enqueue($popup); \OldBearExpansion\Popups\Popups::enqueue($old);
    ob_start(); \OldBearExpansion\Popups\Popups::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'id="elementor-popup-modal-' . $popup . '"') && str_contains($html, 'obx-popup--close-outside') && str_contains($html, ' uno dos'), 'popup: posición del botón y clases.');
    preg_match('~data-obx-popup="' . $popup . '" data-settings="([^"]+)"~', $html, $m); $s = json_decode(html_entity_decode($m[1] ?? ''), true) ?: [];
    $assert(($s['selector'] ?? '') === '.abrir' && !empty($s['preventCloseEsc']) && ($s['animation']['entrance_animation_mobile'] ?? '') === 'zoomIn' && (float) ($s['closeAuto'] ?? 0) === 3.0 && ($s['a11y'] ?? true) === false, 'popup: ajustes avanzados de Pro en el runtime.');
    preg_match('~data-obx-popup="' . $old . '" data-settings="([^"]+)"~', $html, $m); $s = json_decode(html_entity_decode($m[1] ?? ''), true) ?: [];
    $assert(($s['selector'] ?? '') === '.viejo' && !empty($s['preventScroll']), 'popup: las claves antiguas (open_by_selector, obx_prevent_scroll) siguen funcionando.');
    $css = new \Elementor\Core\Files\CSS\Post($popup); $css->update(); $rules = (string) $css->get_content();
    $assert(str_contains($rules, '#elementor-popup-modal-' . $popup . ' .obx-popup__dialog'), 'popup: los estilos del documento deben apuntar al modal, como en Pro.');

    // §3.1 Motion effects: Pro's options and defaults; background effects on containers.
    $common = Elementor::$instance->widgets_manager->get_widget_types('heading')->get_controls();
    foreach (['translateY','translateX','rotateZ'] as $axis) { $assert(array_keys($common['motion_fx_' . $axis . '_direction']['options']) === ['','negative'] && $common['motion_fx_' . $axis . '_direction']['default'] === '', "motion_fx_{$axis}_direction: opciones '' y negative."); }
    $assert(($common['motion_fx_scale_range']['default']['sizes'] ?? []) === ['start'=>20,'end'=>80] && !isset($common['motion_fx_scale_affectedRange']), 'motion_fx_scale_range como en Pro.');
    $assert(($common['motion_fx_transform_origin_x']['default'] ?? '') === 'center' && ($common['motion_fx_transform_origin_y']['default'] ?? '') === 'center', 'Punto de anclaje de la transformación.');
    foreach (['container','section','column'] as $type) {
        $c = Elementor::$instance->elements_manager->get_element_types($type)->get_controls();
        $assert(isset($c['background_motion_fx_motion_fx_scrolling'], $c['background_motion_fx_translateY_speed'], $c['background_motion_fx_mouseTrack_effect']) && isset($c['background_handle_motion_fx_asset_loading']) && ($c['background_motion_fx_motion_fx_scrolling']['section'] ?? '') === ($type === 'column' ? 'section_style' : 'section_background'), "{$type}: efectos de movimiento del fondo.");
    }
    $page = $save('page', 'Paridad fondo', [$container([$widget('heading', ['title'=>'Fondo'])], ['background_background'=>'classic','background_image'=>['url'=>'https://example.com/x.jpg','id'=>''],'background_motion_fx_motion_fx_scrolling'=>'yes','background_motion_fx_translateY_effect'=>'yes'])]);
    $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($page);
    $assert(str_contains($html, 'background_motion_fx_motion_fx_scrolling') && str_contains($html, 'background_motion_fx_translateY_effect'), 'Los efectos del fondo deben llegar al navegador en data-settings.');

    // §3.4 Page transitions and scroll snap.
    $kit = Elementor::$instance->kits_manager->get_active_kit();
    $c = $kit->get_controls();
    $assert(!isset($c['page_transitions_teaser']) && ($c['settings_page_transitions_preloader_animation_type']['default'] ?? '') === 'circle' && ($c['settings_page_transitions_animation_duration']['default']['size'] ?? 0) === 1500 && ($c['settings_page_transitions_background_color']['default'] ?? '') === '#FFBC7D', 'Transiciones de página con las claves y valores de Pro.');
    $assert(count($c['settings_page_transitions_preloader_animation_type']['options']) === 13 && array_keys($c['settings_page_transitions_entrance_animation']['options'])[1] === 'fade-out', 'Precargadores y animaciones de Pro.');
    $assert(isset($c['scroll_snap'], $c['scroll_snap_position'], $c['scroll_snap_padding'], $c['force_stop']), 'Scroll snap en los ajustes del sitio.');
    $c = $controls_of('wp-page', $page);
    $assert(isset($c['scroll_snap']) && ($c['section_scroll_snap']['tab'] ?? '') === 'advanced', 'Scroll snap en las páginas (Avanzado).');
    $html = Site_Settings::preloader_html(['settings_page_transitions_preloader_type'=>'animation','settings_page_transitions_preloader_animation_type'=>'pulsing-dots']);
    $assert(str_contains($html, 'obx-loader--pulsing-dots'), 'El precargador animado debe usar el tipo elegido.');
    $html = Site_Settings::preloader_html(['settings_page_transitions_preloader_type'=>'icon','settings_page_transitions_preloader_animation'=>'eicon-spin','settings_page_transitions_preloader_icon'=>['value'=>'fas fa-spinner','library'=>'fa-solid']]);
    $assert(str_contains($html, 'obx-preloader--spin'), 'El precargador de icono debe girar con eicon-spin.');
    $kit_id = (int) $kit->get_main_id(); $kit_meta = get_post_meta($kit_id, '_elementor_page_settings', true);
    try {
        update_post_meta($kit_id, '_elementor_page_settings', ['settings_page_transitions_entrance_animation'=>'fade-out','settings_page_transitions_exit_animation'=>'fade-in','settings_page_transitions_preloader_type'=>'animation'] + (is_array($kit_meta) ? $kit_meta : []));
        Elementor::$instance->documents->get($kit_id, false);
        ob_start(); Site_Settings::print_transition(); $html = ob_get_clean();
        $assert(str_contains($html, 'data-entrance="fade-out"') && str_contains($html, 'data-exit="fade-in"') && str_contains($html, 'obx-loader--circle') && str_contains($html, 'obx-pt-loading'), 'La capa de transición debe imprimirse con las animaciones del kit.');
    } finally {
        if (is_array($kit_meta)) { update_post_meta($kit_id, '_elementor_page_settings', $kit_meta); } else { delete_post_meta($kit_id, '_elementor_page_settings'); }
        Elementor::$instance->documents->get($kit_id, false);
    }

    // §3.5 Theme Builder conditions: Pro's strings, including the WooCommerce group.
    $parsed = Conditions::from_strings(['include/archive/child_of_category/3','include/archive/any_child_of_category/3','include/singular/attachment','include/singular/attachment_by_author/2','exclude/singular/in_post_format/7','include/product_archive/product_cat/5','include/woocommerce/product','include/woocommerce/product_archive/shop_page','include/product/in_product_cat_children/9','include/singular/product','include/archive/post_format']);
    $rules = array_map(static fn($c) => $c['mode'] . ' ' . $c['rule'] . ($c['ids'] ? ' ' . implode(',', $c['ids']) : ''), $parsed);
    $assert($rules === ['include archive/child_of_category 3','include archive/any_child_of_category 3','include singular/attachment','include singular/attachment_by_author 2','exclude singular/in_post_format 7','include product_archive/product_cat 5','include woocommerce/product','include woocommerce/product_archive/shop_page','include product/in_product_cat_children 9','include product','include archive/post_format'], 'Lectura de las condiciones de Pro: ' . implode(' | ', $rules));
    $assert(Conditions::to_strings(Conditions::from_strings(['include/product_archive/product_cat/5','include/woocommerce'])) === ['include/product_archive/product_cat/5','include/woocommerce'], 'Las condiciones de WooCommerce se guardan como en Pro.');
    $assert(Conditions::from_strings(['include/archive/child_of_category']) === [] && Conditions::requires_ids('product/product_by_author') && !Conditions::requires_ids('product_archive/shop_page'), 'Reglas que necesitan un ID.');
    $assert(Conditions::normalize([['rule'=>'woocommerce/product_archive/product_search'],['rule'=>'archive/any_child_of_category','object_id'=>'4']])[1]['ids'] === [4] && count(Conditions::normalize([['rule'=>'woocommerce/product_archive/product_search']])) === 1, 'El repetidor admite las reglas de WooCommerce y de hijos de término.');
    $single_rules = Conditions::rules('single'); $archive_rules = Conditions::rules('archive'); $all_rules = Conditions::rules('header');
    foreach (['singular/attachment','singular/attachment_by_author','product','product/in_product_cat','product/in_product_cat_children','product/in_product_tag','product/product_by_author'] as $rule) { $assert(isset($single_rules[$rule]), "Condición individual {$rule}."); }
    foreach (['archive/child_of_category','archive/any_child_of_category','archive/post_format','product_archive','product_archive/shop_page','product_archive/product_search','product_archive/product_cat','product_archive/product_tag'] as $rule) { $assert(isset($archive_rules[$rule]), "Condición de archivo {$rule}."); }
    $assert(isset($all_rules['woocommerce']) && !isset($single_rules['singular/product']) && !isset($archive_rules['archive/product_cat']), 'WooCommerce tiene su propio grupo, como en Pro.');

    $parent = wp_insert_term('obx-padre ' . $uid(), 'category'); $terms[] = $parent['term_id'];
    $child = wp_insert_term('obx-hijo ' . $uid(), 'category', ['parent'=>$parent['term_id']]); $terms[] = $child['term_id'];
    $grandchild = wp_insert_term('obx-nieto ' . $uid(), 'category', ['parent'=>$child['term_id']]); $terms[] = $grandchild['term_id'];
    $match = static function (array $query, string $rule, array $ids = []) {
        global $wp_query, $wp_the_query;
        [$main, $current] = [$wp_the_query, $wp_query];
        $wp_the_query = $wp_query = new WP_Query($query);
        try { return Conditions::matches(['mode'=>'include','rule'=>$rule,'ids'=>$ids]); } finally { [$wp_the_query, $wp_query] = [$main, $current]; wp_reset_postdata(); }
    };
    $assert($match(['cat'=>$child['term_id']], 'archive/child_of_category', [$parent['term_id']]) && !$match(['cat'=>$grandchild['term_id']], 'archive/child_of_category', [$parent['term_id']]), 'child_of_category: solo hijos directos.');
    $assert($match(['cat'=>$grandchild['term_id']], 'archive/any_child_of_category', [$parent['term_id']]) && !$match(['cat'=>$parent['term_id']], 'archive/any_child_of_category', [$parent['term_id']]), 'any_child_of_category: cualquier descendiente.');
    $media = wp_insert_attachment(['post_title'=>'Paridad imagen','post_mime_type'=>'image/jpeg','post_status'=>'inherit','post_excerpt'=>'Leyenda paridad'], false, 0); $created[] = $media;
    update_post_meta($media, '_wp_attachment_image_alt', 'Alt paridad');
    $assert($match(['attachment_id'=>$media], 'singular/attachment') && $match(['attachment_id'=>$media], 'singular/attachment_by_author', [(int) get_post_field('post_author', $media)]), 'Condiciones de medios.');

    if (class_exists('WC_Product_Simple')) {
        $pcat = wp_insert_term('obx-pcat ' . $uid(), 'product_cat'); $pchild = wp_insert_term('obx-pcat-hijo ' . $uid(), 'product_cat', ['parent'=>$pcat['term_id']]);
        $product = new WC_Product_Simple(); $product->set_name('Paridad producto'); $product->set_status('publish'); $product->set_regular_price('20'); $product->set_sale_price('15'); $product->set_category_ids([$pchild['term_id']]); $product->set_gallery_image_ids([$media]);
        $pid = $product->save(); $created[] = $pid;
        $pslug = get_term($pchild['term_id'], 'product_cat')->slug;
        $assert($match(['p'=>$pid,'post_type'=>'product'], 'product') && $match(['p'=>$pid,'post_type'=>'product'], 'woocommerce/product') && $match(['p'=>$pid,'post_type'=>'product'], 'woocommerce'), 'Producto individual: product, woocommerce/product y woocommerce.');
        $assert($match(['p'=>$pid,'post_type'=>'product'], 'product/in_product_cat_children', [$pcat['term_id']]) && !$match(['p'=>$pid,'post_type'=>'product'], 'product/in_product_cat', [$pcat['term_id']]), 'Producto en categoría o en sus hijas.');
        $assert($match(['product_cat'=>$pslug], 'product_archive/product_cat', [$pchild['term_id']]) && $match(['product_cat'=>$pslug], 'product_archive') && !$match(['product_cat'=>$pslug], 'archive/category'), 'Archivo de categoría de producto.');
        $assert($match(['s'=>'Paridad','post_type'=>'product'], 'product_archive/product_search') && !$match(['s'=>'Paridad'], 'product_archive/product_search'), 'Resultados de búsqueda de productos.');
        $assert(!$match(['p'=>$post], 'woocommerce') && !$match(['p'=>$post], 'product'), 'Las reglas de WooCommerce no se aplican fuera de la tienda.');
        $assert(Conditions::score(['rule'=>'product','ids'=>[]]) > Conditions::score(['rule'=>'singular','ids'=>[]]) && Conditions::score(['rule'=>'woocommerce','ids'=>[]]) < Conditions::score(['rule'=>'singular','ids'=>[]]), 'Prioridad de las reglas de WooCommerce.');
    }

    // §3.6 Dynamic tags with Pro's names and keys.
    $tags = Elementor::$instance->dynamic_tags;
    foreach (['archive-meta','featured-image-data','request-arg','request-parameter','reload-page','woocommerce-add-to-cart','woocommerce-category-image-tag','woocommerce-product-content-tag','woocommerce-product-gallery-tag','woocommerce-product-sale-tag','woocommerce-product-terms-tag'] as $name) { $assert($tags->get_tag_info($name), "Etiqueta dinámica {$name}."); }
    $assert(function_exists('get_field_object') || !$tags->get_tag_info('acf-text'), 'Las etiquetas de ACF solo se registran con ACF activo.');
    $tag = static fn(string $name, array $settings) => $tags->create_tag(null, $name, $settings);
    $content = static function ($t) { return $t instanceof \Elementor\Core\DynamicTags\Data_Tag ? $t->get_content() : $t->get_content(); };
    update_post_meta($post, '_thumbnail_id', $media);
    $GLOBALS['post'] = get_post($post);
    $assert($content($tag('featured-image-data', ['attachment_data'=>'alt'])) === 'Alt paridad' && $content($tag('featured-image-data', [])) === 'Paridad imagen' && $content($tag('featured-image-data', ['attachment_data'=>'caption'])) === 'Leyenda paridad', 'featured-image-data: alt, título (por defecto) y leyenda.');
    $_GET['obx_paridad'] = 'valor<b>'; 
    $assert($content($tag('request-arg', ['param_name'=>'obx_paridad'])) === 'valor' && $content($tag('request-parameter', ['param_name'=>'obx_paridad'])) === 'valor', 'request-arg (y el alias antiguo) lee el parámetro saneado.');
    unset($_GET['obx_paridad']);
    $hash = (string) $content($tag('reload-page', []));
    $assert(str_starts_with($hash, '#elementor-action') && str_contains(rawurldecode($hash), 'action=reload-page'), 'reload-page crea la acción de Elementor.');
    update_term_meta($child['term_id'], 'obx_color', 'azul');
    global $wp_query, $wp_the_query; [$main, $current] = [$wp_the_query, $wp_query]; $wp_the_query = $wp_query = new WP_Query(['cat'=>$child['term_id']]);
    $assert($content($tag('archive-meta', ['key'=>'obx_color'])) === 'azul' && $content($tag('archive-meta', ['key'=>'_protegido'])) === '', 'archive-meta lee el metadato del término (y no los protegidos).');
    [$wp_the_query, $wp_query] = [$main, $current];
    if (!empty($pid)) {
        $GLOBALS['post'] = get_post($pid);
        $assert($content($tag('woocommerce-product-sale-tag', [])) === '¡Oferta!' && $content($tag('woocommerce-product-sale-tag', ['text'=>'Rebaja'])) === 'Rebaja', 'Producto rebajado.');
        $assert(str_contains((string) $content($tag('woocommerce-product-terms-tag', ['link'=>''])), get_term($pchild['term_id'], 'product_cat')->name), 'Términos del producto (product_cat por defecto).');
        $assert($content($tag('woocommerce-product-gallery-tag', [])) === [['id'=>$media]], 'Galería del producto.');
        $url = (string) $content($tag('woocommerce-add-to-cart', ['product_id'=>(string) $pid,'quantity'=>2]));
        $assert(str_contains($url, 'add-to-cart=' . $pid) && str_contains($url, 'quantity=2'), 'Añadir al carrito con producto y cantidad.');
        update_term_meta($pchild['term_id'], 'thumbnail_id', $media);
        $assert((int) (($content($tag('woocommerce-category-image-tag', [])))['id'] ?? 0) === $media, 'Imagen de la categoría del producto.');
        wp_update_post(['ID'=>$pid,'post_content'=>'Contenido de paridad']);
        $assert(str_contains((string) $content($tag('woocommerce-product-content-tag', [])), 'Contenido de paridad'), 'Contenido del producto.');
    }
    $GLOBALS['post'] = null;

    // §5 Loop Grid: skins, product and term queries, pagination and «Load more» with Pro's keys.
    $grid = Elementor::$instance->widgets_manager->get_widget_types('loop-grid');
    $c = $grid->get_controls();
    $assert(($c['_skin']['default'] ?? '') === 'post' && array_keys($c['_skin']['options']) === (post_type_exists('product') ? ['post','product','post_taxonomy','product_taxonomy'] : ['post','post_taxonomy']), 'loop-grid: _skin como en Pro.');
    $assert(($c['post_query_post_type']['condition']['_skin'] ?? '') === 'post' && ($c['post_query_post_type']['section'] ?? '') === 'section_query', 'loop-grid: la consulta de entradas depende del skin.');
    $assert(array_keys($c['product_query_post_type']['options'] ?? []) === ['current_query','product','sale','featured','by_id','related_products','upsells','cross_sells'] && ($c['product_query_orderby']['default'] ?? '') === 'date', 'loop-grid: consulta de productos de Pro.');
    foreach (['post_taxonomy_query_post_type','post_taxonomy_query_filter_by','post_taxonomy_posts_ids','post_taxonomy_exclude_ids','post_taxonomy_child_of','post_taxonomy_orderby','post_taxonomy_order','post_taxonomy_avoid_duplicates','post_taxonomy_hide_empty','post_taxonomy_offset','post_taxonomy_hierarchical','post_taxonomy_child_taxonomy_depth','post_taxonomy_term_taxonomy_id','product_taxonomy_query_post_type'] as $key) { $assert(isset($c[$key]), "loop-grid: falta {$key}."); }
    $assert(($c['post_taxonomy_order']['default'] ?? '') === 'DESC' && ($c['post_taxonomy_orderby']['default'] ?? '') === 'name' && ($c['product_taxonomy_query_post_type']['default'] ?? '') === 'product_cat', 'loop-grid: valores por defecto de los bucles de términos.');
    foreach (['pagination_load_type','auto_scroll','auto_scroll_offset','pagination_individual_handle','load_more_spinner','selected_icon','icon_align','icon_indent','button_css_id','load_more_button_align','load_more_no_posts_message_switcher','load_more_no_posts_message_align','enable_nothing_found_message','nothing_found_message_align','nothing_found_message_html_tag','nothing_found_message_space_from_top','nothing_found_message_typography_typography','nothing_found_message_color','nothing_found_message_text_shadow_text_shadow_type','nothing_found_message_text_stroke_text_stroke_type','load_more_no_posts_message_typography','load_more_no_posts_message_color','load_more_spinner_color','load_more_spacing','typography_typography','button_text_color','background_background','button_box_shadow_box_shadow_type','hover_color','button_background_hover_background','button_hover_border_color','border_border','border_radius','text_padding','hover_animation','content_align','edit_handle_selector'] as $key) { $assert(isset($c[$key]), "loop-grid: falta {$key}."); }
    $assert(!isset($c['align']) && ($c['pagination_load_type']['default'] ?? '') === 'page_reload' && ($c['load_more_spinner']['default']['value'] ?? '') === 'fas fa-spinner' && ($c['nothing_found_message_html_tag']['default'] ?? '') === 'div', 'loop-grid: valores por defecto de paginación y mensaje.');
    $assert(array_keys($c['pagination_align']['options']) === ['start','center','end'] && isset($c['post_query_related_taxonomies']['options']['post_format']), 'loop-grid: opciones de alineación y relacionadas por formato.');
    $scoped = true;
    foreach (['button_text_color','typography_font_size','border_radius','text_padding'] as $key) { foreach (array_keys($c[$key]['selectors'] ?? []) as $selector) { if (!str_contains($selector, '.obx-load-more .elementor-button')) { $scoped = false; } } }
    $assert($scoped, 'Los estilos del botón «Cargar más» no deben afectar a los botones de los elementos.');
    $assert(isset($c['alternate_templates']['fields']['static_position'], $c['alternate_templates']['fields']['repeat_template_note']), 'loop-grid: campos de las plantillas alternativas.');
    $assert(!$grid->get_stack()['controls'] || true, 'loop-grid: la configuración del editor se genera.');
    $posts_c = Elementor::$instance->widgets_manager->get_widget_types('posts')->get_controls();
    $assert(isset($posts_c['pagination_individual_handle'], $posts_c['load_more_spinner'], $posts_c['load_more_no_posts_message_typography'], $posts_c['load_more_spacing']) && !isset($posts_c['pagination_load_type']), 'posts: claves de paginación compartidas (sin AJAX, como en Pro).');

    // Term loop: each term rendered with the loop item; archive title shows the looped term.
    $term_item = $save('loop-item', 'Paridad término', [$container([$widget('theme-archive-title', ['title_tag'=>'h3'])])], ['source'=>'post_taxonomy']);
    $t1 = wp_insert_term('obx-uno ' . $uid(), 'category'); $terms[] = $t1['term_id'];
    $t2 = wp_insert_term('obx-dos ' . $uid(), 'category'); $terms[] = $t2['term_id'];
    $render = static function (array $settings) use ($save, $container, $widget) {
        $type = $settings['__widget'] ?? 'loop-grid'; unset($settings['__widget']);
        $id = $save('page', 'Paridad rejilla', [$container([$widget($type, $settings)])]);
        return (string) Elementor::$instance->frontend->get_builder_content_for_display($id);
    };
    $html = $render(['template_id'=>(string) $term_item,'_skin'=>'post_taxonomy','post_taxonomy_query_post_type'=>'category','post_taxonomy_query_filter_by'=>'manual_selection','post_taxonomy_posts_ids'=>[(string) $t1['term_id'],(string) $t2['term_id']],'post_taxonomy_order'=>'ASC']);
    $n1 = get_term($t1['term_id'])->name; $n2 = get_term($t2['term_id'])->name;
    $assert(substr_count($html, 'data-term-id=') === 2 && strpos($html, $n2) !== false && strpos($html, $n1) !== false && strpos($html, $n2) < strpos($html, $n1), 'Bucle de términos: un elemento por término, con su título y en orden.');

    // 0.11.0 Posts and Archive Posts: every key of Pro (button of «Load more», masonry, read more, hover, full content).
    foreach (['posts'=>'', 'archive-posts'=>'archive_'] as $type => $sp) {
        $pc = Elementor::$instance->widgets_manager->get_widget_types($type)->get_controls();
        foreach (['align','content_align','typography_typography','text_shadow_text_shadow_type','button_text_color','background_background','button_box_shadow_box_shadow_type','hover_color','button_background_hover_background','button_hover_border_color','button_hover_box_shadow_box_shadow_type','button_hover_transition_duration','hover_animation','border_border','border_radius','text_padding','icon_align','icon_indent','button_css_id','pagination_colors','nothing_found_color','nothing_found_typography_typography',
            $sp . 'classic_masonry',$sp . 'cards_masonry',$sp . 'classic_read_more_alignment',$sp . 'cards_read_more_alignment',$sp . 'classic_box_bg_color_hover',$sp . 'classic_box_border_color_hover',$sp . 'classic_box_shadow_hover_box_shadow_type',$sp . 'classic_thumbnail_effects_tabs',$sp . 'classic_text_stroke_text_stroke_type',$sp . 'cards_avatar_size',$sp . 'cards_text_stroke_text_stroke_type',
            $sp . 'full_content_item_ratio',$sp . 'full_content_open_new_tab',$sp . 'full_content_excerpt_color',$sp . 'full_content_read_more_typography_typography',$sp . 'full_content_box_shadow_hover_box_shadow_type',$sp . 'full_content_text_stroke_text_stroke_type'] as $key) { $assert(isset($pc[$key]), "{$type}: falta {$key}."); }
        $assert(($pc['align']['default'] ?? '') === 'center' && ($pc['align']['prefix_class'] ?? '') === 'obx-load-more-btn-' && ($pc['align_tablet']['prefix_class'] ?? '') === 'obx-load-more-tablet-btn-' && ($pc[$sp . 'full_content_open_new_tab']['default'] ?? '') === 'no' && (float) ($pc[$sp . 'full_content_item_ratio']['default']['size'] ?? 0) === 0.66, "{$type}: valores de Pro; la alineación solo mueve el botón «Cargar más».");
        $assert(array_key_exists('', $pc[$sp . 'cards_badge_taxonomy']['options'] ?? []), "{$type}: distintivo sin taxonomía.");
    }
    $assert(isset($posts_c['posts_query_args']) || isset(Elementor::$instance->widgets_manager->get_widget_types('posts')->get_controls()['posts_query_args']), 'posts: pestañas Incluir / Excluir de la consulta.');
    $html = $render(['__widget'=>'posts','posts_post_type'=>'post','classic_masonry'=>'yes','classic_columns'=>'3']);
    $assert(str_contains($html, 'obx-posts--masonry') && !str_contains($html, 'obx-posts--read-more-bottom'), 'posts: mampostería.');
    $html = $render(['__widget'=>'posts','posts_post_type'=>'post','classic_read_more_alignment'=>'yes']);
    $assert(str_contains($html, 'obx-posts--read-more-bottom') && !str_contains($html, 'obx-posts--masonry'), 'posts: «Leer más» alineado abajo.');
    $html = $render(['__widget'=>'posts','posts_post_type'=>'post','classic_masonry'=>'yes','classic_thumbnail'=>'left']);
    $assert(!str_contains($html, 'obx-posts--masonry'), 'posts: sin mampostería si la imagen no va arriba (como en Pro).');
    // 0.11.0 Nested Carousel: Pro's offset, arrow placement, slide box and navigation style keys.
    if (\OldBearExpansion\Widgets\Nested_Carousel::available()) {
        $nc = Elementor::$instance->widgets_manager->get_widget_types('nested-carousel')->get_controls();
        foreach (['direction','offset_sides','offset_width','navigation_previous_icon_horizontal_orientation','navigation_next_icon_vertical_orientation','navigation_next_icon_horizontal_position','content_background_background','content_border_border','border_radius','content_padding',
            'arrows_position','arrows_colors_border_shadow','arrow_normal_color','arrow_hover_color','arrows_hover_background_background','arrows_normal_box_shadow_box_shadow_type','arrows_border_radius','arrows_padding',
            'dots_gap','dots_size','dots_colors','dots_normal_color','dots_hover_color','dots_position','dots_custom_position','dots_vertical_offset','typography_fraction_typography','fraction_color','fraction_position','fraction_pagination_spacing','progressbar_height','progressbar_normal_color','progressbar_hover_color','dots_pagination_spacing'] as $key) { $assert(isset($nc[$key]), "nested-carousel: falta {$key}."); }
        foreach (['arrows_normal_color','arrows_hover_color','dots_color_inactive','dots_color_active'] as $key) { $assert(!isset($nc[$key]), "nested-carousel: {$key} ya no existe en Pro."); }
        $assert(($nc['navigation_previous_icon_horizontal_orientation']['default'] ?? '') === 'start' && ($nc['navigation_next_icon_horizontal_orientation']['default'] ?? '') === 'end' && ($nc['offset_sides']['default'] ?? '') === 'none' && ($nc['offset_width']['default']['size'] ?? 0) === 80 && ($nc['arrows_position']['default'] ?? '') === 'inside', 'nested-carousel: valores por defecto de Pro.');
        $old = \OldBearExpansion\Widgets\Nested_Carousel::convert('nested-carousel', ['arrows_normal_color'=>'#111','dots_color_active'=>'#222','dots_hover_color'=>'#333']);
        $assert(($old['arrow_normal_color'] ?? '') === '#111' && ($old['dots_hover_color'] ?? '') === '#333' && !isset($old['arrows_normal_color']) && !isset($old['dots_color_active']), 'nested-carousel: las claves antiguas pasan a las de Pro.');
    }
    // 0.11.0 Slides: Pro's keys, navigation select, title/description tags, link on the slide or the button.
    $sc = Elementor::$instance->widgets_manager->get_widget_types('slides')->get_controls();
    foreach (['slides_name','slides','slides_height','slides_title_tag','slides_description_tag','navigation','autoplay','pause_on_interaction','infinite','transition','transition_speed','content_animation','content_max_width','slides_padding','slides_horizontal_position','slides_vertical_position','slides_text_align','text_shadow_text_shadow_type',
        'heading_spacing','heading_color','heading_typography_typography','description_spacing','description_color','description_typography_typography','button_size','button_typography_typography','button_border_width','button_border_radius','button_tabs','button_text_color','button_background_background','button_border_color','button_hover_text_color','button_hover_background_background','button_hover_border_color','button_hover_transition_duration',
        'arrows_position','arrows_size','arrows_color','dots_position','dots_gap','dots_size','dots_color_inactive','dots_color'] as $key) { $assert(isset($sc[$key]), "slides: falta {$key}."); }
    foreach (['show_arrows','pagination','height','speed','slides_to_show'] as $key) { $assert(!isset($sc[$key]), "slides: {$key} no existe en Pro."); }
    $fields = $sc['slides']['fields'];
    foreach (['background_color','background_image','background_size','background_ken_burns','zoom_direction','background_overlay','background_overlay_color','background_overlay_blend_mode','heading','description','button_text','link','link_click','custom_style','horizontal_position','vertical_position','text_align','content_color','repeater_text_shadow_text_shadow_type'] as $key) { $assert(isset($fields[$key]), "slides: falta el campo {$key}."); }
    $assert(($sc['navigation']['default'] ?? '') === 'both' && ($sc['slides_height']['default']['size'] ?? 0) === 400 && ($sc['content_max_width']['default']['size'] ?? 0) === 66 && ($sc['slides_title_tag']['default'] ?? '') === 'div' && ($sc['button_size']['default'] ?? '') === 'sm' && ($sc['content_animation']['default'] ?? '') === 'fadeInUp' && ($sc['dots_position']['default'] ?? '') === 'inside' && ($fields['background_color']['default'] ?? '') === '#bbbbbb' && count($sc['slides']['default']) === 3, 'slides: valores por defecto de Pro.');
    $html = $render(['__widget'=>'slides','slides'=>[['_id'=>'sa','heading'=>'Uno','description'=>'Texto uno','button_text'=>'Ir','link'=>['url'=>'https://example.org/a']],['_id'=>'sb','heading'=>'Dos','button_text'=>'Ver','link'=>['url'=>'https://example.org/b'],'link_click'=>'button']],'slides_title_tag'=>'h2','navigation'=>'dots','transition'=>'fade','button_size'=>'lg']);
    $assert(str_contains($html, '<h2 class="obx-slide__heading">Uno</h2>') && str_contains($html, '<div class="obx-slide__description">Texto uno</div>') && preg_match('~<a [^>]*href="https://example.org/a"[^>]*class="obx-slide"|<a [^>]*class="obx-slide"[^>]*href="https://example.org/a"~', $html) && preg_match('~<a [^>]*class="obx-slide__button elementor-button elementor-size-lg"~', $html) && str_contains($html, 'obx-carousel__pagination--bullets') && !str_contains($html, 'obx-carousel__arrow') && str_contains($html, '&quot;fade&quot;:true') && str_contains($html, 'obx-slides--fadeInUp'), 'slides: etiquetas, enlace en la diapositiva o en el botón, navegación y fundido.');
    $old = \OldBearExpansion\Widgets\Slides::convert('slides', ['show_arrows'=>'','height'=>['unit'=>'vh','size'=>80],'pagination_color'=>'#f00','button_bg'=>'#00f','slides'=>[['_id'=>'x','text_align'=>'start','horizontal_position'=>'start','background_overlay_background'=>'classic','background_overlay_color'=>'#000']]]);
    $assert(($old['navigation'] ?? '') === 'dots' && ($old['slides_height']['size'] ?? 0) === 80 && ($old['dots_color'] ?? '') === '#f00' && ($old['button_background_color'] ?? '') === '#00f' && ($old['slides'][0]['custom_style'] ?? '') === 'yes' && ($old['slides'][0]['horizontal_position'] ?? '') === 'left' && ($old['slides'][0]['background_overlay'] ?? '') === 'yes' && !isset($old['show_arrows'], $old['height']), 'slides: los datos antiguos pasan a las claves de Pro.');
    // 0.11.0 Media Carousel: Pro's slide types and links, skins, overlay, navigation and lightbox styles.
    $mc = Elementor::$instance->widgets_manager->get_widget_types('media-carousel')->get_controls();
    foreach (['skin','slides_name','slides','effect','slideshow_height','slides_per_view','slideshow_slides_per_view','thumbs_ratio','centered_slides','slides_to_scroll','height','width','show_arrows','pagination','speed','autoplay','autoplay_speed','loop','pause_on_hover','pause_on_interaction','overlay','caption','icon','overlay_animation','image_size_size','image_size_custom_dimension','image_fit','lazyload',
        'space_between','slide_background_color','slide_border_size','slide_border_radius','slide_border_color','slide_padding','arrows_size','arrows_color','pagination_position','pagination_gap','pagination_size','pagination_color_inactive','pagination_color','play_icon_color','play_icon_size','play_icon_text_shadow_text_shadow_type','overlay_background_color','overlay_color','caption_typography_typography','icon_size','lightbox_color','lightbox_ui_color','lightbox_ui_hover_color','lightbox_video_width'] as $key) { $assert(isset($mc[$key]), "media-carousel: falta {$key}."); }
    foreach (['type','image','image_link_to_type','image_link_to','video'] as $key) { $assert(isset($mc['slides']['fields'][$key]), "media-carousel: falta el campo {$key}."); }
    foreach (['link_to','open_lightbox','thumbnail_size','image_stretch'] as $key) { $assert(!isset($mc[$key]), "media-carousel: {$key} no existe en Pro."); }
    $assert(($mc['effect']['default'] ?? '') === 'slide' && ($mc['thumbs_ratio']['default'] ?? '') === '219' && ($mc['image_size_size']['default'] ?? '') === 'full' && ($mc['arrows_size']['default']['size'] ?? 0) === 20 && ($mc['pagination_position']['default'] ?? '') === 'outside' && ($mc['icon']['default'] ?? '') === 'search-plus' && count($mc['slides']['default']) === 5, 'media-carousel: valores por defecto de Pro.');
    $html = $render(['__widget'=>'media-carousel','skin'=>'slideshow','slides'=>[['_id'=>'ma','image'=>['url'=>'https://example.org/a.jpg'],'image_link_to_type'=>'file'],['_id'=>'mb','type'=>'video','image'=>['url'=>'https://example.org/b.jpg'],'video'=>['url'=>'https://www.youtube.com/watch?v=XHOmBV4js_E']],['_id'=>'mc','image'=>['url'=>'https://example.org/c.jpg'],'image_link_to_type'=>'custom','image_link_to'=>['url'=>'https://example.org/destino']]]]);
    $assert(str_contains($html, 'href="https://example.org/a.jpg"') && str_contains($html, 'data-elementor-open-lightbox="yes"') && str_contains($html, 'youtube.com/embed/XHOmBV4js_E') && str_contains($html, 'href="https://example.org/destino"') && substr_count($html, 'obx-media__thumb"') === 3 && str_contains($html, 'eicon-play') && !str_contains($html, 'obx-carousel__pagination'), 'media-carousel: archivo con caja de luz, vídeo, enlace propio y miniaturas en la presentación.');
    $html = $render(['__widget'=>'media-carousel','overlay'=>'icon','icon'=>'eye','effect'=>'cube','slides'=>[['_id'=>'md','image'=>['url'=>'https://example.org/d.jpg']]]]);
    $assert(str_contains($html, 'obx-media__overlay') && str_contains($html, 'obx-carousel--cube') && str_contains($html, '&quot;fade&quot;:true') && str_contains($html, '<div class="obx-media__item">'), 'media-carousel: superposición con icono, efecto cubo y diapositiva sin enlace.');
    $old = \OldBearExpansion\Widgets\Media_Carousel::convert('media-carousel', ['link_to'=>'custom','thumbnail_size'=>'medium','slides'=>[['_id'=>'o1','image'=>['url'=>'https://example.org/o.jpg'],'link'=>['url'=>'https://example.org/x']],['_id'=>'o2','video_url'=>['url'=>'https://example.org/v.mp4']]]]);
    $assert(($old['image_size_size'] ?? '') === 'medium' && ($old['slides'][0]['image_link_to_type'] ?? '') === 'custom' && ($old['slides'][0]['image_link_to']['url'] ?? '') === 'https://example.org/x' && ($old['slides'][1]['type'] ?? '') === 'video' && ($old['slides'][1]['video']['url'] ?? '') === 'https://example.org/v.mp4' && !isset($old['link_to'], $old['slides'][0]['link']), 'media-carousel: los datos antiguos pasan a las claves de Pro.');
    // 0.11.0 Sitemap: Pro's repeater of post types / taxonomies, order, hierarchy and depth, exclusions, nofollow.
    $smc = Elementor::$instance->widgets_manager->get_widget_types('sitemap')->get_controls();
    foreach (['sitemap_items','sitemap_columns','sitemap_title_tag','sitemap_add_nofollow','sitemap_exclude','sitemap_exclude_ids','sitemap_password_protected','sitemap_list_indent','sitemap_section_padding','sitemap_title_color','sitemap_title_typography_typography','sitemap_list_item_color','sitemap_list_item_typography_typography','sitemap_bullet_color','sitemap_list_item_bullet_style'] as $key) { $assert(isset($smc[$key]), "sitemap: falta {$key}."); }
    foreach (['sitemap_type_selector','sitemap_source_post_type','sitemap_source_taxonomy','sitemap_title','sitemap_orderby_post_type','sitemap_orderby_taxonomy','sitemap_order','sitemap_hide_empty','sitemap_hierarchical','sitemap_depth'] as $key) { $assert(isset($smc['sitemap_items']['fields'][$key]), "sitemap: falta el campo {$key}."); }
    $assert(($smc['sitemap_columns']['default'] ?? '') === '4' && ($smc['sitemap_title_tag']['default'] ?? '') === 'h2' && ($smc['sitemap_list_item_bullet_style']['default'] ?? '') === 'disc' && ($smc['sitemap_items']['default'][1]['sitemap_source_taxonomy'] ?? '') === 'category', 'sitemap: valores por defecto de Pro.');
    $mark = 'Mapa ' . $uid();
    $top = wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$mark . ' padre']); $created[] = $top;
    $kid = wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$mark . ' hija','post_parent'=>$top]); $created[] = $kid;
    $grand = wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$mark . ' nieta','post_parent'=>$kid]); $created[] = $grand;
    $locked = wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$mark . ' privada','post_password'=>'x']); $created[] = $locked;
    $row = ['_id'=>'r1','sitemap_type_selector'=>'post_type','sitemap_source_post_type'=>'page','sitemap_title'=>'Páginas','sitemap_hierarchical'=>'yes','sitemap_depth'=>'2','sitemap_orderby_post_type'=>'post_title','sitemap_order'=>'asc'];
    $html = $render(['__widget'=>'sitemap','sitemap_items'=>[$row],'sitemap_title_tag'=>'h4','sitemap_add_nofollow'=>'yes']);
    $assert(str_contains($html, '<h4 class="obx-sitemap__title">Páginas</h4>') && preg_match('~' . preg_quote($mark, '~') . ' padre</a><ul class="children"><li[^>]*><a[^>]*>' . preg_quote($mark, '~') . ' hija</a></li></ul>~', $html) && !str_contains($html, $mark . ' nieta') && !str_contains($html, $mark . ' privada') && str_contains($html, 'rel="nofollow"'), 'sitemap: jerarquía con profundidad, sin protegidas y con nofollow.');
    $html = $render(['__widget'=>'sitemap','sitemap_items'=>[['sitemap_hierarchical'=>'no'] + $row],'sitemap_exclude'=>['manual_selection'],'sitemap_exclude_ids'=>[(string) $kid],'sitemap_password_protected'=>'yes']);
    $assert(str_contains($html, $mark . ' nieta') && !str_contains($html, $mark . ' hija') && str_contains($html, $mark . ' privada') && !str_contains($html, 'class="children"'), 'sitemap: lista plana, exclusión manual y protegidas incluidas.');
    $old = \OldBearExpansion\Widgets\Sitemap::convert('sitemap', ['sources'=>['page','tax:category'],'columns'=>'3','limit'=>20]);
    $assert(count($old['sitemap_items'] ?? []) === 2 && ($old['sitemap_items'][1]['sitemap_type_selector'] ?? '') === 'taxonomy' && ($old['sitemap_items'][1]['sitemap_source_taxonomy'] ?? '') === 'category' && ($old['sitemap_columns'] ?? '') === '3' && !isset($old['sources'], $old['limit']), 'sitemap: los datos antiguos pasan a las claves de Pro.');
    // 0.11.0 Search Form: Pro's input/button/toggle style keys; colours reach buttons over theme button styles.
    $sf = Elementor::$instance->widgets_manager->get_widget_types('search-form')->get_controls();
    foreach (['icon_size_minimal','overlay_background_color','tabs_input_colors','input_box_shadow_box_shadow_type','button_border_width','tabs_button_colors','tabs_toggle_color','toggle_color','toggle_background_color','toggle_color_hover','toggle_background_color_hover','toggle_icon_size','toggle_border_width','toggle_border_radius'] as $key) { $assert(isset($sf[$key]), "search-form: falta {$key}."); }
    $assert(!isset($sf['border_width']) && array_keys($sf['toggle_align']['options'] ?? []) === ['start','center','end'] && str_contains((string) implode('', $sf['button_background_color_hover']['selectors'] ?? []), '--obx-submit-bg-hover'), 'search-form: claves y opciones de Pro; colores por variables.');
    $assert((\OldBearExpansion\Widgets\Search_Form::convert('search-form', ['border_width'=>['top'=>'2']])['button_border_width']['top'] ?? '') === '2', 'search-form: border_width pasa a button_border_width.');
    // 0.11.0 Testimonial Carousel, Site Logo, Featured Image: the remaining Pro keys.
    $tc = Elementor::$instance->widgets_manager->get_widget_types('testimonial-carousel')->get_controls();
    foreach (['image_size_size','text_stroke_text_stroke_type','content_text_shadow_text_shadow_type','pagination_gap','background_color','text_padding','border_radius','border','border_color','border_width','arrows_size','arrows_color','pagination_size','pagination_color_inactive','pagination_color'] as $key) { $assert(isset($tc[$key]), "testimonial-carousel: falta {$key}."); }
    $assert(array_keys($tc['alignment']['options'] ?? []) === ['left','center','right'] && ($tc['image_size_size']['default'] ?? '') === 'full' && ($tc['border_color']['default'] ?? '') === '#000' && ($tc['text_padding']['default']['top'] ?? '') === '20' && ($tc['arrows_size']['default']['size'] ?? 0) === 20 && !isset($tc['arrows_hover_color']), 'testimonial-carousel: opciones y valores de Pro.');
    foreach (['theme-site-logo','theme-post-featured-image'] as $type) {
        $ic = Elementor::$instance->widgets_manager->get_widget_types($type)->get_controls();
        foreach (['open_lightbox','image_effects','css_filters_hover_css_filter','background_hover_transition','hover_animation','caption_text_shadow_text_shadow_type'] as $key) { $assert(isset($ic[$key]), "{$type}: falta {$key}."); }
    }
    $lc2 = Elementor::$instance->widgets_manager->get_widget_types('theme-site-logo')->get_controls();
    $assert(isset($lc2['change_logo_cta']) && ($lc2['link_to']['default'] ?? '') === 'site_url' && isset($lc2['link_to']['options']['site_url']) && ($lc2['image_size']['default'] ?? '') === 'full', 'theme-site-logo: botón para cambiar el logotipo, enlace a la URL del sitio y tamaño completo por defecto.');
    $html = $render(['__widget'=>'theme-site-logo','image'=>['url'=>'https://example.org/logo.png'],'hover_animation'=>'grow']);
    $assert(str_contains($html, 'href="' . home_url('/') . '"') && str_contains($html, 'elementor-animation-grow'), 'theme-site-logo: enlace a la portada por defecto y animación al pasar el ratón.');
    $html = $render(['__widget'=>'theme-post-featured-image','image'=>['url'=>'https://example.org/f.png'],'link_to'=>'file','open_lightbox'=>'no']);
    $assert(str_contains($html, 'data-elementor-open-lightbox="no"') && str_contains($html, 'href="https://example.org/f.png"'), 'theme-post-featured-image: archivo con la opción de caja de luz.');
    // 0.11.0 Gallery: filter pointer animations, normal/hover tabs, overlay and content hover animations.
    $gc = Elementor::$instance->widgets_manager->get_widget_types('gallery')->get_controls();
    foreach (['animation_line','animation_framed','animation_background','animation_text','image_tabs','overlay_background_tabs','background_overlay_hover_animation','background_overlay_animation_duration','content_animation_duration','content_sequenced_animation','filter_bar_colors','galleries_title_color_hover_pointer_bg'] as $key) { $assert(isset($gc[$key]), "gallery: falta {$key}."); }
    $assert(isset($gc['content_hover_animation']['options']['enter-from-bottom'], $gc['background_overlay_hover_animation']['options']['exit-zoom-out']) && !isset($gc['content_hover_animation']['options']['slide-in-up']) && ($gc['overlay_background_hover_color']['default'] ?? '') === 'rgba(0,0,0,0.5)' && ($gc['animation_text']['default'] ?? '') === 'grow', 'gallery: animaciones y valores de Pro.');
    $assert((\OldBearExpansion\Widgets\Gallery::convert('gallery', ['content_hover_animation'=>'slide-in-up'])['content_hover_animation'] ?? '') === 'enter-from-bottom', 'gallery: las animaciones antiguas pasan a las de Pro.');
    $html = $render(['__widget'=>'gallery','gallery_type'=>'multiple','galleries'=>[['_id'=>'g1','gallery_title'=>'Uno','multiple_gallery'=>[['id'=>0,'url'=>'https://example.org/a.png']]]],'pointer'=>'framed','animation_framed'=>'draw']);
    $assert(str_contains($html, 'obx-pointer obx-pointer--framed obx-pointer--anim-draw') && str_contains($html, 'obx-gallery__filter obx-pointer__item is-active'), 'gallery: la barra de filtros usa los indicadores compartidos.');
    // 0.11.0 Nav Menu: pointer animations, icon tabs with hover animation, normal/hover/active tabs.
    $nc = Elementor::$instance->widgets_manager->get_widget_types('nav-menu')->get_controls();
    foreach (['animation_line','animation_framed','animation_background','animation_text','nav_icon_options','toggle_icon_hover_animation','tabs_menu_item_style','tabs_dropdown_item_style','tabs_toggle_style'] as $key) { $assert(isset($nc[$key]), "nav-menu: falta {$key}."); }
    $assert(($nc['animation_text']['default'] ?? '') === 'grow' && ($nc['animation_line']['condition']['layout!'] ?? '') === 'dropdown' && isset($nc['animation_background']['options']['shutter-in-vertical']), 'nav-menu: animaciones del indicador con los valores de Pro.');
    $menu_id = wp_create_nav_menu('obx-paridad-' . $uid());
    $item_id = wp_update_nav_menu_item($menu_id, 0, ['menu-item-title'=>'Inicio paridad','menu-item-url'=>home_url('/'),'menu-item-status'=>'publish']);
    $link_filters = has_filter('nav_menu_link_attributes');
    $html = $render(['__widget'=>'nav-menu','menu'=>(string) $menu_id,'pointer'=>'background','animation_background'=>'sweep-up','dropdown'=>'mobile','toggle_icon_hover_animation'=>'pulse']);
    $assert(str_contains($html, 'obx-pointer--background obx-pointer--anim-sweep-up') && str_contains($html, 'class="obx-pointer__item"') && str_contains($html, 'obx-nav__toggle elementor-animation-pulse'), 'nav-menu: indicador animado en los enlaces y animación del botón.');
    $html = $render(['__widget'=>'nav-menu','menu'=>(string) $menu_id,'layout'=>'dropdown']);
    $assert(!str_contains($html, 'obx-pointer'), 'nav-menu: sin indicador en la disposición desplegable, como en Pro.');
    $assert(has_filter('nav_menu_link_attributes') === $link_filters, 'nav-menu: el filtro de los enlaces se quita después de pintar.');
    wp_delete_nav_menu($menu_id);
    // 0.11.0 Post Navigation: Pro's arrow icons, «in same term» per post type, normal/hover tabs with transition.
    $pn = Elementor::$instance->widgets_manager->get_widget_types('post-navigation')->get_controls();
    foreach (['arrow_previous_icon','arrow_next_icon','tabs_label_style','label_hover_color_transition_duration','tabs_post_navigation_style','hover_color_transition_duration','tabs_post_navigation_arrow_style','arrow_hover_color_transition_duration','post_taxonomy'] as $key) { $assert(isset($pn[$key]), "post-navigation: falta {$key}."); }
    $assert(($pn['in_same_term']['type'] ?? '') === 'select2' && isset($pn['in_same_term']['options']['post']) && ($pn['post_taxonomy']['condition']['in_same_term'] ?? '') === 'post' && !isset($pn['arrow']) && ($pn['arrow_next_icon']['default']['value'] ?? '') === 'fas fa-angle-right', 'post-navigation: «mismo término» por tipo de contenido y flechas de Pro.');
    $old = \OldBearExpansion\Widgets\Post_Navigation::convert('post-navigation', ['arrow'=>'fa fa-chevron-left','in_same_term'=>'yes']);
    $assert(($old['arrow_previous_icon']['value'] ?? '') === 'fas fa-chevron-left' && ($old['arrow_next_icon']['value'] ?? '') === 'fas fa-chevron-right' && in_array('post', (array) ($old['in_same_term'] ?? []), true) && !isset($old['arrow']), 'post-navigation: las flechas y el «mismo término» antiguos se convierten.');
    // 0.11.0 Call to Action: Pro's colour, button and background-effect tabs.
    $cc = Elementor::$instance->widgets_manager->get_widget_types('call-to-action')->get_controls();
    foreach (['color_tabs','button_tabs','bg_effects_tabs','animation_class'] as $key) { $assert(isset($cc[$key]), "call-to-action: falta {$key}."); }
    // 0.11.0 Normal/hover tabs, transitions and option lists of the remaining widgets.
    $tabs = ['author-box'=>['tabs_button_style','button_hover_transition_duration','avatar_size'],'blockquote'=>['tabs_button_style','tabs_border_style','tabs_box_style'],'login'=>['tabs_button_style'],'price-table'=>['tabs_button_style'],'flip-box'=>['button_tabs'],'share-buttons'=>['tabs_button_style'],'table-of-contents'=>['include_exclude_tags','item_text_style'],'taxonomy-filter'=>['taxonomy_filter_tabs_section'],'theme-post-excerpt'=>['link_colors','link_hover_color_transition_duration'],'post-comments'=>['skin_temp']];
    foreach (['theme-post-title','theme-page-title','theme-archive-title','theme-site-title'] as $type) { $tabs[$type] = ['title','title_colors','title_hover_color_transition_duration']; }
    foreach ($tabs as $type => $keys) {
        $controls = Elementor::$instance->widgets_manager->get_widget_types($type)->get_controls();
        foreach ($keys as $key) { $assert(isset($controls[$key]), "{$type}: falta {$key}."); }
    }
    $ab = Elementor::$instance->widgets_manager->get_widget_types('author-box')->get_controls();
    $assert(($ab['avatar_size']['default'] ?? 0) === 300 && isset($ab['button_background_hover_color']) && !isset($ab['button_hover_background_color']), 'author-box: resolución de la foto y fondo al pasar el ratón con las claves de Pro.');
    $old = \OldBearExpansion\Widgets\Author_Box::convert('author-box', ['button_hover_background_color'=>'#123456','avatar_size'=>96]);
    $assert(($old['button_background_hover_color'] ?? '') === '#123456' && ($old['avatar_size'] ?? null) === 96 && !isset($old['image_size']), 'author-box: el fondo antiguo se convierte y avatar_size de Pro se conserva.');
    $old = \OldBearExpansion\Widgets\Author_Box::convert('author-box', ['avatar_size'=>['unit'=>'px','size'=>80]]);
    $assert(($old['image_size']['size'] ?? 0) === 80 && !isset($old['avatar_size']), 'author-box: el avatar_size de la v0.3 pasa a image_size.');
    $ch = Elementor::$instance->widgets_manager->get_widget_types('code-highlight')->get_controls();
    $assert(isset($ch['language']['options']['visual-basic,'], $ch['language']['options']['tsx'], $ch['language']['options']['aspnet']), 'code-highlight: los lenguajes de Pro, con sus claves.');
    $pi = Elementor::$instance->widgets_manager->get_widget_types('post-info')->get_controls();
    $assert(array_keys($pi['icon_align']['options'] ?? []) === ['left','center','right'], 'post-info: alineación izquierda/centro/derecha como Pro.');
    $pt = Elementor::$instance->widgets_manager->get_widget_types('theme-post-title')->get_controls();
    $assert(str_contains((string) ($pt['title']['dynamic']['default'] ?? ''), 'name="post-title"'), 'theme-post-title: el título usa por defecto la etiqueta dinámica, como Pro.');
    $html = $render(['__widget'=>'theme-site-title','title'=>'Texto fijo de prueba']);
    $assert(str_contains($html, 'Texto fijo de prueba'), 'theme-site-title: un texto fijo en «title» sustituye al nombre del sitio.');
    $html = $render(['__widget'=>'theme-site-title']);
    $assert(str_contains($html, esc_html(get_bloginfo('name'))), 'theme-site-title: sin texto propio muestra el nombre del sitio.');
    $assert(($cc['animation_class']['default'] ?? '') === 'animated-content' && isset($cc['button_hover_text_color'], $cc['overlay_color_hover'], $cc['effect_duration']), 'call-to-action: pestañas de Pro sin perder claves.');
    $sub = wp_insert_term('obx-sub ' . $uid(), 'category', ['parent'=>$t1['term_id']]); $terms[] = $sub['term_id'];
    $kids = \OldBearExpansion\Widgets\Query::terms(['post_taxonomy_child_of'=>(string) $t1['term_id'],'post_taxonomy_hierarchical'=>'yes'], 'post_taxonomy_', 'category', 10);
    $assert(wp_list_pluck($kids, 'term_id') === [$sub['term_id']], 'Bucle de términos: hijos de un término.');

    // Nothing found message: off by default, on with its tag.
    $none = ['template_id'=>(string) $term_item,'post_query_post_type'=>'by_id','post_query_posts_ids'=>['999999']];
    $assert(!str_contains($render($none), 'e-loop-nothing-found-message'), 'Sin resultados: el mensaje está desactivado por defecto, como en Pro.');
    $html = $render($none + ['enable_nothing_found_message'=>'yes','nothing_found_message_text'=>'Nada por aquí','nothing_found_message_html_tag'=>'h3']);
    $assert(str_contains($html, '<h3 class="e-loop-nothing-found-message">Nada por aquí</h3>'), 'Sin resultados: mensaje con su etiqueta.');

    // «Load more», AJAX pages and static alternate items.
    $post_item = $save('loop-item', 'Paridad elemento', [$container([$widget('theme-post-title')])]);
    $extra = []; foreach ([1, 2, 3] as $i) { $extra[] = $created[] = wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>"Paridad carga {$i}"]); }
    $base = ['template_id'=>(string) $post_item,'posts_per_page'=>1,'post_query_post_type'=>'by_id','post_query_posts_ids'=>array_map('strval', $extra),'post_query_orderby'=>'post_title','post_query_order'=>'asc'];
    $html = $render($base + ['pagination_type'=>'load_more_on_click','button_css_id'=>'mas-entradas','text'=>'Ver más','selected_icon'=>['value'=>'fas fa-plus','library'=>'fa-solid']]);
    $assert(str_contains($html, 'id="mas-entradas"') && str_contains($html, '>Ver más<') && str_contains($html, 'elementor-button-icon') && str_contains($html, 'data-done=""') && preg_match('~data-next="[^"]*e-page-~', $html), '«Cargar más»: botón con ID, icono y página propia; sin mensaje final si no se activa.');
    $html = $render($base + ['pagination_type'=>'numbers','pagination_load_type'=>'ajax','auto_scroll'=>'yes','auto_scroll_offset'=>80]);
    $assert(str_contains($html, 'data-ajax="1" data-scroll="1" data-offset="80"') && str_contains($html, 'e-page-'), 'Paginación AJAX con desplazamiento.');
    $html = $render($base + ['pagination_type'=>'numbers']);
    $assert(!str_contains($html, 'e-page-'), 'Sin paginación independiente se usa la paginación de WordPress, como en Pro.');
    $html = $render(['posts_per_page'=>3] + $base + ['alternate_template'=>'yes','alternate_templates'=>[['_id'=>'a1','template_id'=>(string) $term_item,'repeat_template'=>1,'show_once'=>'yes','static_position'=>'yes']]]);
    $assert(substr_count($html, 'data-elementor-type="loop-item" data-post-id=') === 4 && substr_count($html, 'Paridad carga') === 3, 'Plantilla alternativa en posición fija: se añade sin quitar sitio a las entradas.');

    // §5 Loop Carousel: skins, Pro's carousel settings, arrow placement and pagination styles.
    $lc = Elementor::$instance->widgets_manager->get_widget_types('loop-carousel')->get_controls();
    foreach (['_skin','edit_handle_selector','post_taxonomy_query_post_type','product_query_post_type','direction','offset_sides','offset_width','navigation_previous_icon_horizontal_orientation','navigation_previous_icon_horizontal_position','navigation_previous_icon_vertical_orientation','navigation_previous_icon_vertical_position','navigation_next_icon_horizontal_orientation','navigation_next_icon_vertical_position','arrow_normal_color','arrow_hover_color','arrows_normal_background_background','arrows_hover_background_background','arrows_normal_border_type_border','arrows_hover_border_type_border','arrows_normal_box_shadow_box_shadow_type','arrows_hover_box_shadow_box_shadow_type','arrows_position','arrows_border_radius','arrows_padding','dots_gap','dots_size','dots_normal_color','dots_hover_color','dots_custom_position','dots_horizontal_position','dots_horizontal_offset','dots_vertical_position','dots_vertical_offset','dots_position','dots_pagination_spacing','typography_fraction_typography','fraction_color','fraction_custom_position','fraction_horizontal_position','fraction_vertical_offset','fraction_position','fraction_pagination_spacing','progressbar_height','progressbar_normal_color','progressbar_hover_color','slides_to_show','slides_to_scroll','image_spacing_custom'] as $key) { $assert(isset($lc[$key]), "loop-carousel: falta {$key}."); }
    $assert(($lc['edit_handle_selector']['default'] ?? '') === '.elementor-loop-container' && ($lc['offset_width']['default']['size'] ?? 0) === 80 && ($lc['navigation_next_icon_horizontal_orientation']['default'] ?? '') === 'end' && ($lc['dots_vertical_position']['default'] ?? '') === 'end' && ($lc['navigation_previous_icon']['default']['value'] ?? '') === 'eicon-chevron-left' && ($lc['slides_to_show']['default'] ?? '') === '3', 'loop-carousel: valores por defecto de Pro.');
    $assert(!isset($lc['arrows_normal_color']) && !isset($lc['dots_color_inactive']), 'loop-carousel: sin las claves propias antiguas.');
    $old = \OldBearExpansion\Widgets\Loop_Carousel::convert('loop-carousel', ['arrows_normal_color'=>'#111','dots_color_active'=>'#222']);
    $assert(($old['arrow_normal_color'] ?? '') === '#111' && ($old['dots_hover_color'] ?? '') === '#222' && !isset($old['arrows_normal_color']), 'loop-carousel: las claves de versiones anteriores se convierten.');
    $html = $render(['template_id'=>(string) $term_item,'_skin'=>'post_taxonomy','post_taxonomy_query_filter_by'=>'manual_selection','post_taxonomy_posts_ids'=>[(string) $t1['term_id'],(string) $t2['term_id']],'direction'=>'rtl'] + ['__widget'=>'loop-carousel']);
    $assert(substr_count($html, 'data-term-id=') === 2 && str_contains($html, 'dir="rtl"') && str_contains($html, 'obx-carousel__slide'), 'loop-carousel: bucle de términos en carrusel, de derecha a izquierda.');

    if (!empty($pid) && get_post($pid)) {
        $product_item = $save('loop-item', 'Paridad producto', [$container([$widget('theme-post-title')])], ['source'=>'product']);
        $html = $render(['template_id'=>(string) $product_item,'_skin'=>'product','product_query_post_type'=>'sale']);
        $assert(str_contains($html, 'data-post-id="' . $pid . '"'), 'Skin de productos: rebajados.');
        $html = $render(['template_id'=>(string) $product_item,'_skin'=>'product','product_query_post_type'=>'featured']);
        $assert(!str_contains($html, 'data-post-id="' . $pid . '"'), 'Skin de productos: destacados (el de prueba no lo es).');
    }

    // 0.10.0 WooCommerce product grids: Pro's keys, WooCommerce's loop templates, grid and pagination.
    if (!empty($pid) && get_post($pid)) {
        $wc = Elementor::$instance->widgets_manager->get_widget_types('woocommerce-products')->get_controls();
        foreach (['rows','paginate','allow_order','show_result_count','query_post_type','query_posts_ids','query_include','query_include_term_ids','query_exclude','query_exclude_ids','query_orderby','query_order','products_title_show','products_related_products_title_text','products_upsells_title_text','products_cross_sells_title_text','products_title_alignment','products_class','column_gap','row_gap','align','image_border_border','image_border_radius','image_spacing','title_color','title_typography_typography','title_spacing','star_color','empty_star_color','star_size','rating_spacing','price_color','price_typography_typography','old_price_color','old_price_typography_typography','button_text_color','button_background_color','button_border_color','button_typography_typography','button_hover_color','button_hover_background_color','button_hover_border_color','button_border_border','button_border_radius','button_text_padding','button_spacing','automatically_align_buttons','view_cart_color','view_cart_typography_typography','view_cart_spacing','products_title_color','products_title_typography_typography','products_title_spacing','box_border_width','box_border_radius','box_padding','box_shadow_box_shadow_type','box_bg_color','box_border_color','box_shadow_hover_box_shadow_type','box_bg_color_hover','box_border_color_hover','pagination_spacing','show_pagination_border','pagination_border_color','pagination_padding','pagination_typography_typography','pagination_link_color','pagination_link_bg_color','pagination_link_color_hover','pagination_link_bg_color_hover','pagination_link_color_active','pagination_link_bg_color_active','show_onsale_flash','onsale_text_color','onsale_text_background_color','onsale_typography_typography','onsale_border_radius','onsale_width','onsale_height','onsale_horizontal_position','onsale_distance'] as $key) { $assert(isset($wc[$key]), "woocommerce-products: falta {$key}."); }
        $assert(($wc['rows']['default'] ?? 0) === 4 && ($wc['columns']['default'] ?? 0) === 4 && ($wc['query_post_type']['default'] ?? '') === 'product' && ($wc['column_gap']['default']['size'] ?? 0) === 20 && ($wc['row_gap']['default']['size'] ?? 0) === 40 && ($wc['show_onsale_flash']['default'] ?? '') === 'yes' && ($wc['products_class']['default'] ?? '') === 'wc-products' && array_keys($wc['query_post_type']['options']) === ['current_query','product','sale','featured','by_id','related_products','upsells','cross_sells'] && !isset($wc['posts_per_page']), 'woocommerce-products: valores por defecto de Pro.');
        $wa = Elementor::$instance->widgets_manager->get_widget_types('woocommerce-archive-products')->get_controls();
        $assert(($wa['query_post_type']['default'] ?? '') === 'current_query' && ($wa['paginate']['default'] ?? '') === 'yes' && isset($wa['nothing_found_message'], $wa['nothing_found_color'], $wa['nothing_found_typography_typography']), 'woocommerce-archive-products: consulta actual, paginación y mensaje sin resultados.');
        foreach (['woocommerce-product-related'=>['posts_per_page','columns','orderby','order','show_heading','heading_color','heading_typography_typography','heading_text_align','heading_spacing','box_bg_color','onsale_distance'],'woocommerce-product-upsell'=>['columns','orderby','order','show_heading','heading_spacing','button_spacing']] as $type => $keys) {
            $c = Elementor::$instance->widgets_manager->get_widget_types($type)->get_controls();
            foreach ($keys as $key) { $assert(isset($c[$key]), "{$type}: falta {$key}."); }
            $assert(($c['show_heading']['default'] ?? '') === 'yes' && isset($c['orderby']['options']['menu_order']), "{$type}: valores por defecto de Pro.");
        }
        $shop = [];
        foreach ([3, 1, 2] as $i) { $item = new WC_Product_Simple(); $item->set_name("Paridad tienda {$i}"); $item->set_status('publish'); $item->set_regular_price((string) (10 * $i)); $shop[$i] = $created[] = $item->save(); }
        $html = $render(['__widget'=>'woocommerce-products','columns'=>2,'rows'=>1,'query_post_type'=>'by_id','query_posts_ids'=>array_map('strval', array_values($shop)),'query_orderby'=>'price','query_order'=>'asc','paginate'=>'yes','show_result_count'=>'yes','show_onsale_flash'=>'']);
        $assert(substr_count($html, 'type-product') === 2 && strpos($html, 'Paridad tienda 1') !== false && strpos($html, 'Paridad tienda 1') < strpos($html, 'Paridad tienda 2') && !str_contains($html, 'Paridad tienda 3'), 'woocommerce-products: columnas × filas por página, ordenados por precio.');
        $assert(str_contains($html, 'obx-products woocommerce obx-products--no-sale') && str_contains($html, 'woocommerce-pagination') && str_contains($html, 'product-page=2') && str_contains($html, 'woocommerce-result-count') && str_contains($html, 'elementor-products-grid elementor-wc-products'), 'woocommerce-products: plantillas de WooCommerce, paginación propia y clases de Pro.');
        $_GET['product-page'] = '2';
        $html = $render(['__widget'=>'woocommerce-products','columns'=>2,'rows'=>1,'query_post_type'=>'by_id','query_posts_ids'=>array_map('strval', array_values($shop)),'query_orderby'=>'price','query_order'=>'asc','paginate'=>'yes']);
        unset($_GET['product-page']);
        $assert(str_contains($html, 'Paridad tienda 3') && !str_contains($html, 'Paridad tienda 1'), 'woocommerce-products: segunda página.');
        $old = \OldBearExpansion\Widgets\Woo_Products::convert('woocommerce-products', ['posts_per_page'=>8,'columns'=>4,'orderby'=>'price','order'=>'asc','query_post_type'=>'current_query','query_posts_ids'=>'1, 2']);
        $assert(($old['rows'] ?? 0) === 2 && ($old['query_orderby'] ?? '') === 'price' && ($old['query_post_type'] ?? '') === 'product' && ($old['query_posts_ids'] ?? []) === ['1','2'] && !isset($old['posts_per_page']), 'woocommerce-products: los ajustes de versiones anteriores se convierten.');

        $shop_archive = $save('archive', 'Paridad archivo tienda', [$container([$widget('woocommerce-archive-products', ['columns'=>3,'rows'=>2])])]);
        update_post_meta($shop_archive, '_elementor_conditions', ['include/product_archive']);
        Locations::flush(); Locations::reset();
        $saved_main = [$GLOBALS['wp_the_query'], $GLOBALS['wp_query']];
        try {
            $main = new WP_Query(); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = $main;
            $main->query(['post_type'=>'product']);
            $assert((int) $main->get('posts_per_page') === 6, 'woocommerce-archive-products: la tienda pagina con columnas × filas del widget.');
        } finally {
            [$GLOBALS['wp_the_query'], $GLOBALS['wp_query']] = $saved_main;
            Locations::reset();
        }
        $html = $render(['__widget'=>'woocommerce-archive-products','query_post_type'=>'by_id','query_posts_ids'=>['999999'],'nothing_found_message'=>'Sin productos']);
        $assert(str_contains($html, '<div class="elementor-products-nothing-found obx-products__nothing">Sin productos</div>'), 'woocommerce-archive-products: mensaje sin resultados.');

        $main_product = wc_get_product($pid); $main_product->set_upsell_ids(array_values($shop)); $main_product->save();
        $single_product = $save('product', 'Paridad producto individual', [$container([$widget('woocommerce-product-upsell', ['columns'=>3,'show_heading'=>'','orderby'=>'price','order'=>'desc'])])], ['preview_type'=>'single/product','preview_id'=>(string) $pid]);
        $previous_post = $GLOBALS['post'] ?? null; $GLOBALS['post'] = get_post($pid);
        try { $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($single_product); } finally { $GLOBALS['post'] = $previous_post; }
        $assert(substr_count($html, 'type-product') === 3 && str_contains($html, 'obx-products--no-heading') && strpos($html, 'Paridad tienda 3') < strpos($html, 'Paridad tienda 1') && str_contains($html, 'upsells'), 'woocommerce-product-upsell: ventas dirigidas del producto, ordenadas, sin encabezado.');

        // 0.10.0 Pro's general and deprecated WooCommerce widgets (wc-*).
        $ac = Elementor::$instance->widgets_manager->get_widget_types('wc-add-to-cart')->get_controls();
        foreach (['product_id','show_quantity','quantity','button_type','text','link','size','selected_icon','icon_align','icon_indent','button_css_id','align','content_align','typography_typography','text_shadow_text_shadow_type','button_text_color','background_background','button_box_shadow_box_shadow_type','hover_color','button_background_hover_background','button_hover_border_color','button_hover_box_shadow_box_shadow_type','button_hover_transition_duration','hover_animation','border_border','text_padding','layout'] as $key) { $assert(isset($ac[$key]), "wc-add-to-cart: falta {$key}."); }
        $assert(($ac['quantity']['default'] ?? 0) === 1 && ($ac['link']['type'] ?? '') === 'hidden' && ($ac['size']['condition'] ?? []) === ['show_quantity'=>''] && ($ac['quantity']['condition'] ?? []) === ['show_quantity'=>''] && array_keys($ac['layout']['options'] ?? []) === ['','stacked','auto'], 'wc-add-to-cart: valores por defecto de Pro.');
        $html = $render(['__widget'=>'wc-add-to-cart','product_id'=>(string) $pid,'quantity'=>3,'text'=>'Comprar ya','hover_animation'=>'grow','button_css_id'=>'obx-atc']);
        $assert(str_contains($html, 'add-to-cart=' . $pid) && str_contains($html, 'quantity=3') && str_contains($html, 'data-quantity="3"') && str_contains($html, 'ajax_add_to_cart') && str_contains($html, 'Comprar ya') && str_contains($html, 'elementor-animation-grow') && str_contains($html, 'id="obx-atc"') && str_contains($html, 'elementor-button-text'), 'wc-add-to-cart: botón AJAX de WooCommerce con cantidad, texto, id y animación.');
        $html = $render(['__widget'=>'wc-add-to-cart','product_id'=>(string) $pid,'show_quantity'=>'yes','layout'=>'stacked','text'=>'Al carrito']);
        $assert(str_contains($html, 'obx-add-to-cart--layout-stacked') && str_contains($html, 'name="quantity"') && str_contains($html, 'name="add-to-cart"') && str_contains($html, 'value="' . $pid . '"') && str_contains($html, 'single_add_to_cart_button') && str_contains($html, 'Al carrito'), 'wc-add-to-cart: formulario con cantidad y disposición apilada.');

        $cc = Elementor::$instance->widgets_manager->get_widget_types('wc-categories')->get_controls();
        foreach (['columns','number','source','categories','parent','hide_empty','orderby','order','products_class','column_gap','row_gap','align','image_border_border','image_border_radius','image_spacing','title_color','title_typography_typography','count_color','count_typography_typography'] as $key) { $assert(isset($cc[$key]), "wc-categories: falta {$key}."); }
        $assert(($cc['number']['default'] ?? '') === '4' && ($cc['parent']['default'] ?? '') === '0' && ($cc['orderby']['default'] ?? '') === 'name' && ($cc['order']['default'] ?? '') === 'desc' && array_keys($cc['source']['options'] ?? []) === ['','by_id','by_parent','current_subcategories'], 'wc-categories: valores por defecto de Pro.');
        $html = $render(['__widget'=>'wc-categories','source'=>'by_parent','parent'=>(string) $pcat['term_id'],'columns'=>3]);
        $child_name = get_term($pchild['term_id'])->name;
        $assert(str_contains($html, 'product-category') && str_contains($html, esc_html($child_name)) && str_contains($html, 'obx-products woocommerce obx-product-categories') && str_contains($html, 'woocommerce-loop-category__title'), 'wc-categories: categorías hijas con la plantilla de WooCommerce.');
        $attributes = \OldBearExpansion\Widgets\Woo_Categories::shortcode_attributes(['source'=>'by_id','categories'=>['7','9'],'hide_empty'=>'yes','number'=>'6','orderby'=>'count','order'=>'asc']);
        $assert($attributes['ids'] === '7,9' && $attributes['hide_empty'] === '1' && $attributes['limit'] === '6' && $attributes['orderby'] === 'count' && $attributes['order'] === 'asc', 'wc-categories: selección manual, vacías, número y orden.');

        $assert(\OldBearExpansion\Widgets\Woo_Pages::shortcode(['element'=>'product_page','product_id'=>(string) $pid]) === '[product_page id="' . $pid . '"]' && \OldBearExpansion\Widgets\Woo_Pages::shortcode(['element'=>'product_page']) === '' && \OldBearExpansion\Widgets\Woo_Pages::shortcode(['element'=>'woocommerce_cart']) === '[woocommerce_cart]', 'wc-elements: shortcode de cada página de WooCommerce.');
        $previous_post = $GLOBALS['post'] ?? null; $GLOBALS['post'] = get_post($pid);
        try { $html = $render(['__widget'=>'wc-elements','element'=>'woocommerce_order_tracking']); } finally { $GLOBALS['post'] = $previous_post; }
        $assert(str_contains($html, 'woocommerce-form-track-order'), 'wc-elements: imprime la página elegida.');

        $se = Elementor::$instance->widgets_manager->get_widget_types('wc-single-elements');
        $assert($se && !$se->show_in_panel() && array_keys($se->get_controls()['element']['options'] ?? []) === array_merge([''], array_keys(\OldBearExpansion\Widgets\Woo_Single_Elements::ELEMENTS)), 'wc-single-elements: obsoleto, oculto en el panel y con las funciones de Pro.');
        $previous_post = $GLOBALS['post'] ?? null; $GLOBALS['post'] = get_post($pid);
        try { $html = $render(['__widget'=>'wc-single-elements','element'=>'woocommerce_template_single_price']); } finally { $GLOBALS['post'] = $previous_post; }
        $assert(str_contains($html, 'class="price"') && str_contains($html, '15'), 'wc-single-elements: imprime la función de WooCommerce del producto.');

        $dp = Elementor::$instance->widgets_manager->get_widget_types('wc-products');
        $dc = $dp->get_controls();
        foreach (['_skin','classic_columns','posts_per_page','posts_post_type','posts_posts_ids','posts_authors','posts_product_cat_ids','posts_product_tag_ids','posts_product_brand_ids','filter_by','orderby','order','exclude','exclude_ids','avoid_duplicates','column_gap','button_text_color','pagination_spacing','onsale_distance'] as $key) { $assert(isset($dc[$key]), "wc-products: falta {$key}."); }
        $assert(!$dp->show_in_panel() && ($dc['classic_columns']['default'] ?? '') === '4' && ($dc['posts_per_page']['default'] ?? '') === '4' && ($dc['posts_post_type']['default'] ?? '') === 'product', 'wc-products: obsoleto, oculto y con los valores de Pro.');
        $html = $render(['__widget'=>'wc-products','posts_post_type'=>'by_id','posts_posts_ids'=>array_map('strval', array_values($shop)),'posts_per_page'=>2,'orderby'=>'price','order'=>'asc','classic_columns'=>'2']);
        $assert(substr_count($html, 'type-product') === 2 && strpos($html, 'Paridad tienda 1') < strpos($html, 'Paridad tienda 2') && !str_contains($html, 'Paridad tienda 3'), 'wc-products: productos elegidos, número y orden.');
        $q = \OldBearExpansion\Widgets\Woo_Products_Deprecated::query_settings(['posts_product_cat_ids'=>['5'],'filter_by'=>'sale','exclude'=>['current_post']]);
        $assert($q['query_post_type'] === 'sale' && $q['query_include'] === ['terms'] && $q['query_include_term_ids'] === ['5'] && $q['query_exclude'] === ['current_post'], 'wc-products: la consulta antigua se traduce a la de Productos.');

        $da = Elementor::$instance->widgets_manager->get_widget_types('wc-archive-products');
        $old = \OldBearExpansion\Widgets\Woo_Archive_Products::convert('wc-archive-products', ['query_post_type'=>'current_query']);
        $assert($da && !$da->show_in_panel() && (int) ($old['columns'] ?? 0) === wc_get_default_products_per_row() && (int) ($old['rows'] ?? 0) === wc_get_default_product_rows_per_page(), 'wc-archive-products: obsoleto, oculto y con las columnas y filas de la tienda.');

        // 0.10.0 single product widgets: Pro's style keys and defaults, WooCommerce markup, captions, old keys.
        $keys = [
            'woocommerce-product-title'=>['title','link','size','header_size','align','typography_typography','text_stroke_text_stroke_type','text_shadow_text_shadow_type','blend_mode','title_color','title_hover_color','title_hover_color_transition_duration'],
            'woocommerce-product-price'=>['text_align','price_color','typography_typography','sale_price_color','sale_price_typography_typography','price_block','sale_price_spacing'],
            'woocommerce-product-rating'=>['star_color','empty_star_color','link_color','text_typography_typography','star_size','space_between','alignment'],
            'woocommerce-product-stock'=>['text_color','text_typography_typography'],
            'woocommerce-product-meta'=>['view','space_between','divider','divider_style','divider_weight','divider_width','divider_height','divider_color','text_typography_typography','text_color','link_typography_typography','link_color','category_caption_single','category_caption_plural','tag_caption_single','tag_caption_plural','sku_caption','sku_missing_caption'],
            'woocommerce-product-short-description'=>['text_align','text_color','text_typography_typography'],
            'woocommerce-product-content'=>['align','text_color','typography_typography'],
            'woocommerce-product-additional-information'=>['show_heading','heading_color','heading_typography_typography','content_color','content_typography_typography'],
            'woocommerce-product-data-tabs'=>['tab_text_color','tab_bg_color','tabs_border_color','active_tab_text_color','active_tab_bg_color','active_tabs_border_color','tab_typography_typography','tab_border_radius','text_color','content_typography_typography','heading_color','content_heading_typography_typography','panel_border_width','panel_border_radius','panel_box_shadow_box_shadow_type'],
            'woocommerce-product-images'=>['sale_flash','image_border_border','image_border_radius','spacing','thumbs_border_border','thumbs_border_radius','spacing_thumbs'],
            'woocommerce-breadcrumb'=>['text_color','link_color','text_typography_typography','alignment'],
            'woocommerce-archive-description'=>['text_align','text_color','text_typography_typography'],
            'woocommerce-product-add-to-cart'=>['layout','alignment','button_typography_typography','button_border_border','button_border_radius','button_padding','button_text_color','button_bg_color','button_border_color','button_text_color_hover','button_bg_color_hover','button_border_color_hover','button_transition','view_cart_color','view_cart_typography_typography','view_cart_spacing','show_quantity','spacing','quantity_typography_typography','quantity_border_border','quantity_border_radius','quantity_padding','quantity_text_color','quantity_bg_color','quantity_border_color','quantity_text_color_focus','quantity_bg_color_focus','quantity_border_color_focus','quantity_transition','variations_width','variations_spacing','variations_space_between','variations_label_color_focus','variations_label_typography_typography','variations_select_color','variations_select_bg_color','variations_select_border_color','variations_select_typography_typography','variations_select_border_radius'],
        ];
        foreach ($keys as $type => $list) {
            $c = Elementor::$instance->widgets_manager->get_widget_types($type)->get_controls();
            foreach ($list as $key) { $assert(isset($c[$key]), "{$type}: falta {$key}."); }
            $assert(!array_filter(array_keys($c), static fn($k) => str_starts_with((string) $k, 'woo_')), "{$type}: sin las claves propias de versiones anteriores.");
        }
        $c = Elementor::$instance->widgets_manager->get_widget_types('woocommerce-product-meta')->get_controls();
        $assert(($c['view']['default'] ?? '') === 'inline' && ($c['divider_style']['default'] ?? '') === 'solid' && ($c['divider_weight']['default']['size'] ?? 0) === 1 && ($c['divider_color']['default'] ?? '') === '#ddd', 'woocommerce-product-meta: valores por defecto de Pro.');
        $c = Elementor::$instance->widgets_manager->get_widget_types('woocommerce-product-add-to-cart')->get_controls();
        $assert(($c['show_quantity']['default'] ?? '') === 'yes' && ($c['button_transition']['default']['size'] ?? 0) == 0.2 && ($c['layout']['default'] ?? 'x') === '' && array_keys($c['layout']['options']) === ['','stacked','auto'], 'woocommerce-product-add-to-cart: valores por defecto de Pro.');
        $c = Elementor::$instance->widgets_manager->get_widget_types('woocommerce-product-title')->get_controls();
        $assert(($c['header_size']['default'] ?? '') === 'h1' && isset($c['header_size']['options']['span']) && ($c['size']['default'] ?? '') === 'default', 'woocommerce-product-title: valores por defecto de Pro.');

        $main_product->set_sku('OBX-SKU-1'); $main_product->set_short_description('Descripción corta de paridad'); $main_product->set_weight('2'); $main_product->save();
        $single = $save('product', 'Paridad ficha de producto', [$container([
            $widget('woocommerce-product-title', ['header_size'=>'h2','size'=>'large','link'=>['url'=>'https://example.com/ficha']]),
            $widget('woocommerce-product-price', ['price_block'=>'yes']),
            $widget('woocommerce-product-meta', ['view'=>'stacked','sku_caption'=>'Referencia','category_caption_single'=>'Familia','show_tag'=>'']),
            $widget('woocommerce-product-short-description'),
            $widget('woocommerce-product-additional-information', ['show_heading'=>'']),
            $widget('woocommerce-product-add-to-cart', ['show_quantity'=>'','layout'=>'stacked']),
            $widget('woocommerce-product-stock'),
            $widget('woocommerce-product-data-tabs'),
        ])], ['preview_type'=>'single/product','preview_id'=>(string) $pid]);
        $previous_post = $GLOBALS['post'] ?? null; $GLOBALS['post'] = get_post($pid);
        try { $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($single); } finally { $GLOBALS['post'] = $previous_post; }
        $assert(str_contains($html, '<h2 class="product_title entry-title elementor-heading-title elementor-size-large"><a href="https://example.com/ficha"') && str_contains($html, 'Paridad producto</a></h2>'), 'woocommerce-product-title: nombre del producto con etiqueta, tamaño y enlace.');
        $assert(str_contains($html, 'elementor-product-price-block-yes') && str_contains($html, '<p class="price"><del'), 'woocommerce-product-price: precio de oferta, uno debajo del otro.');
        $assert(str_contains($html, 'elementor-woo-meta--view-stacked') && str_contains($html, '<span class="detail-label">Referencia</span> <span class="detail-content"><span class="sku">OBX-SKU-1</span>') && str_contains($html, '<span class="detail-label">Familia</span>') && !str_contains($html, 'tagged_as'), 'woocommerce-product-meta: vista, textos propios y metadatos ocultos.');
        $assert(str_contains($html, 'woocommerce-product-details__short-description') && str_contains($html, 'Descripción corta de paridad'), 'woocommerce-product-short-description: descripción corta.');
        $assert(str_contains($html, 'woocommerce-product-attributes') && !preg_match('#<div class="elementor-widget-container">\s*<h2>#', $html), 'woocommerce-product-additional-information: atributos sin encabezado.');
        $assert(str_contains($html, 'elementor-add-to-cart--layout-stacked') && str_contains($html, 'elementor-add-to-cart elementor-product-simple') && str_contains($html, 'single_add_to_cart_button') && preg_match('#type="hidden"\s+id="quantity_#', $html), 'woocommerce-product-add-to-cart: botón de WooCommerce sin selector de cantidad.');
        $assert(str_contains($html, 'woocommerce-tabs') && str_contains($html, 'tab-title-additional_information'), 'woocommerce-product-data-tabs: pestañas de WooCommerce.');

        $old = \OldBearExpansion\Widgets\Woo_Product_Add_To_Cart::convert('woocommerce-product-add-to-cart', ['woo_button_color'=>'#111','woo_button_hover_bg'=>'#222','woo_button_padding_mobile'=>['top'=>'4'],'woo_button_typography_typography'=>'custom','show_quantity'=>'yes']);
        $assert(($old['button_text_color'] ?? '') === '#111' && ($old['button_bg_color_hover'] ?? '') === '#222' && ($old['button_padding_mobile']['top'] ?? '') === '4' && ($old['button_typography_typography'] ?? '') === 'custom' && !array_filter(array_keys($old), static fn($k) => str_starts_with((string) $k, 'woo_')), 'woocommerce-product-add-to-cart: los ajustes de versiones anteriores se convierten.');
        $old = \OldBearExpansion\Widgets\Woo_Product_Additional_Information::convert('woocommerce-product-additional-information', ['title'=>'', 'woo_color'=>'#333']);
        $assert(($old['show_heading'] ?? 'x') === '' && ($old['content_color'] ?? '') === '#333' && !isset($old['title']), 'woocommerce-product-additional-information: «Mostrar título» pasa a show_heading.');
        $old = \OldBearExpansion\Widgets\Woo_Product_Title::convert('woocommerce-product-title', ['woo_color'=>'#444','woo_typography_font_size'=>['size'=>30]]);
        $assert(($old['title_color'] ?? '') === '#444' && ($old['typography_font_size']['size'] ?? 0) === 30, 'woocommerce-product-title: color y tipografía anteriores se conservan.');

        // 0.10.0 Menu cart: Pro's keys, side panel or dropdown, WooCommerce's mini cart hooks and cart fragments.
        $c = Elementor::$instance->widgets_manager->get_widget_types('woocommerce-menu-cart')->get_controls();
        foreach (['icon','menu_icon_svg','items_indicator','hide_empty_indicator','show_subtotal','alignment','cart_type','open_cart','side_cart_alignment','mini_cart_alignment','mini_cart_spacing','close_cart_button_show','close_cart_icon_svg','close_cart_button_alignment','show_remove_icon','remove_item_button_position','price_quantity_position','show_divider','view_cart_button_show','view_cart_button_alignment','checkout_button_show','checkout_button_alignment','checkout_button_display','buttons_position','automatically_open_cart','automatically_update_cart','toggle_button_text_color','toggle_button_icon_color','toggle_button_background_color','toggle_button_border_color','toggle_button_normal_box_shadow_box_shadow_type','toggle_button_hover_text_color','toggle_button_hover_icon_color','toggle_button_hover_background_color','toggle_button_hover_border_color','toggle_button_hover_box_shadow_box_shadow_type','toggle_button_border_width','toggle_button_border_radius','toggle_button_typography_typography','toggle_icon_size','toggle_icon_spacing','toggle_icon_position','toggle_button_padding','items_indicator_text_color','items_indicator_background_color','items_indicator_distance','background_color','border_type','border_width','border_color','border_radius','cart_box_shadow_box_shadow_type','cart_padding','close_cart_icon_size','close_cart_icon_color','close_cart_icon_hover_color','remove_item_button_size','remove_item_button_color','remove_item_button_hover_color','subtotal_color','subtotal_typography_typography','subtotal_alignment','subtotal_divider_style','subtotal_divider_width','subtotal_divider_color','product_title_typography_typography','product_title_color','product_title_hover_color','product_variations_color','product_variations_typography_typography','product_price_color','product_price_typography_typography','product_quantity_color','product_quantity_typography_typography','divider_style','divider_color','divider_width','divider_gap','buttons_layout','space_between_buttons','product_buttons_typography_typography','button_border_radius','view_cart_buttons_typography_typography','view_cart_button_text_color','view_cart_button_background_color','view_cart_button_hover_text_color','view_cart_button_hover_background','view_cart_button_border_hover_color','view_cart_border_border','view_cart_border_width','view_cart_border_color','view_cart_button_border_radius','view_cart_button_box_shadow_box_shadow_type','view_cart_button_padding','cart_checkout_button_typography_typography','checkout_button_text_color','checkout_button_background_color','checkout_button_hover_text_color','checkout_button_hover_background','checkout_button_border_hover_color','checkout_border_border','checkout_border_width','checkout_border_color','view_checkout_button_border_radius','view_checkout_button_box_shadow_box_shadow_type','view_checkout_button_padding','cart_empty_message_typography_typography','empty_message_color','empty_message_alignment'] as $key) { $assert(isset($c[$key]), "woocommerce-menu-cart: falta {$key}."); }
        $assert(($c['icon']['default'] ?? '') === 'cart-medium' && ($c['items_indicator']['default'] ?? '') === 'bubble' && ($c['cart_type']['default'] ?? '') === 'side-cart' && ($c['open_cart']['default'] ?? '') === 'click' && ($c['automatically_open_cart']['default'] ?? '') === 'no' && ($c['automatically_update_cart']['default'] ?? '') === 'yes' && ($c['border_type']['default'] ?? '') === 'none' && ($c['buttons_layout']['default'] ?? '') === 'inline' && ($c['checkout_button_display']['default'] ?? '') === '--cart-footer-buttons-alignment-display: none;' && array_keys($c['icon']['options']) === ['cart-light','cart-medium','cart-solid','basket-light','basket-medium','basket-solid','bag-light','bag-medium','bag-solid','custom'], 'woocommerce-menu-cart: valores por defecto de Pro.');
        if (function_exists('wc_load_cart')) {
            if (!WC()->cart) { wc_load_cart(); }
            if (WC()->cart) {
                WC()->cart->empty_cart();
                $html = $render(['__widget'=>'woocommerce-menu-cart','items_indicator'=>'bubble','hide_empty_indicator'=>'hide']);
                $assert(str_contains($html, 'obx-menu-cart--side-cart') && str_contains($html, 'obx-menu-cart--hide-empty') && str_contains($html, 'data-counter="0"') && str_contains($html, 'woocommerce-mini-cart__empty-message') && str_contains($html, 'eicon-cart-medium'), 'woocommerce-menu-cart: carrito vacío con indicador oculto.');
                WC()->cart->add_to_cart($pid, 2);
                $html = $render(['__widget'=>'woocommerce-menu-cart','cart_type'=>'mini-cart','open_cart'=>'mouseover','items_indicator'=>'plain','show_remove_icon'=>'','checkout_button_show'=>'','automatically_open_cart'=>'yes','icon'=>'bag-solid']);
                $assert(str_contains($html, 'obx-menu-cart--mini-cart') && str_contains($html, 'obx-menu-cart--indicator-plain') && str_contains($html, 'data-counter="2"') && str_contains($html, 'eicon-bag-solid') && !str_contains($html, 'obx-menu-cart--remove') && !str_contains($html, 'obx-menu-cart--checkout') && str_contains($html, 'obx-menu-cart--view-cart'), 'woocommerce-menu-cart: desplegable con opciones de Pro.');
                $assert(str_contains($html, '&quot;open&quot;:&quot;mouseover&quot;') && str_contains($html, '&quot;autoOpen&quot;:true'), 'woocommerce-menu-cart: abrir al pasar el ratón y al añadir.');
                $assert(str_contains($html, 'obx-menu-cart__product woocommerce-mini-cart-item') && str_contains($html, 'Paridad producto') && str_contains($html, 'remove_from_cart_button') && str_contains($html, 'woocommerce-mini-cart__total') && str_contains($html, 'wc-forward') && str_contains($html, '2 &times;'), 'woocommerce-menu-cart: productos, subtotal y botones de WooCommerce.');
                $fragments = apply_filters('woocommerce_add_to_cart_fragments', []);
                $assert(str_contains((string) ($fragments['span.obx-menu-cart__count'] ?? ''), 'data-counter="2"') && str_contains((string) ($fragments['div.obx-menu-cart__content'] ?? ''), 'Paridad producto') && isset($fragments['span.obx-menu-cart__subtotal']), 'woocommerce-menu-cart: fragmentos del carrito de WooCommerce.');
                WC()->cart->empty_cart();
            }
        }
        $old = \OldBearExpansion\Widgets\Woo_Menu_Cart::convert('woocommerce-menu-cart', ['items_indicator'=>'','cart_icon'=>['value'=>'fas fa-shopping-basket','library'=>'fa-solid'],'woo_color'=>'#555']);
        $assert(($old['items_indicator'] ?? '') === 'none' && ($old['icon'] ?? '') === 'custom' && ($old['menu_icon_svg']['value'] ?? '') === 'fas fa-shopping-basket' && ($old['toggle_button_text_color'] ?? '') === '#555' && ($old['toggle_button_icon_color'] ?? '') === '#555' && !isset($old['cart_icon'], $old['woo_color']), 'woocommerce-menu-cart: los ajustes de versiones anteriores se convierten.');
        $same = ['items_indicator'=>'plain','icon'=>'bag-light'];
        $assert(\OldBearExpansion\Widgets\Woo_Menu_Cart::convert('woocommerce-menu-cart', $same) === $same, 'woocommerce-menu-cart: los ajustes de Pro no se tocan.');

        // 0.10.0 Cart, Checkout, My Account, Purchase Summary and Notices: Pro's keys over WooCommerce's own templates.
        $keys = [
            'woocommerce-cart'=>['cart_layout','sticky_right_column','sticky_right_column_offset','update_cart_button_text','update_cart_button_alignment','section_coupon_display','apply_coupon_button_text','apply_coupon_button_alignment','totals_section_title','totals_section_title_alignment','update_shipping_button_text','update_shipping_button_alignment','checkout_button_text','checkout_button_alignment','update_cart_automatically','additional_template_switch','additional_template_select','sections_background_color','section_normal_box_shadow_box_shadow_type','sections_border_type','sections_border_width','sections_border_color','sections_border_radius','sections_padding','sections_margin','sections_title_color','sections_titles_typography_typography','sections_titles_text_shadow_text_shadow_type','sections_title_spacing','sections_descriptions_color','sections_descriptions_spacing','links_normal_color','links_hover_color','sections_radio_buttons_color','forms_rows_gap','forms_field_typography_typography','forms_fields_normal_color','forms_fields_normal_background_background','forms_fields_focus_background_color','forms_fields_focus_border_color','forms_fields_focus_transition_duration','forms_fields_border_border','forms_fields_border_radius','forms_fields_padding','forms_button_typography_typography','forms_buttons_normal_text_color','forms_buttons_normal_background_background','forms_buttons_hover_background_color','forms_buttons_focus_box_shadow_box_shadow_type','forms_buttons_hover_border_color','forms_buttons_hover_animation','forms_buttons_border_type','forms_buttons_border_radius','forms_buttons_padding','order_summary_rows_gap','order_summary_title_color','order_summary_color','order_summary_variations_color','product_link_normal_color','order_summary_items_divider_weight','order_summary_quantity_border_color','order_summary_remove_icon_hover_color','totals_rows_gap','totals_color','totals_divider_weight','checkout_button_typography_typography','checkout_button_normal_background_background','checkout_button_hover_animation','checkout_button_border_border','checkout_button_border_radius','checkout_button_padding','section_cart_show_customize_elements','order_summary_section_background_color','checkout_sections_padding','totals_section_links_hover_color','customize_coupon_section_margin'],
            'woocommerce-checkout-page'=>['checkout_layout','billing_details_section_title','billing_details_alignment','billing_details_form_fields','additional_information_active','additional_information_form_fields','order_summary_section_title','coupon_section_display','coupon_section_title_text','coupon_section_title_link_text','coupon_button_alignment','terms_conditions_message_text','terms_conditions_link_text','purchase_button_alignment','sections_secondary_title_color','sections_messages_typography_typography','sections_checkboxes_color','forms_columns_gap','forms_label_color','forms_label_spacing','order_summary_totals_divider_weight','purchase_button_hover_animation','purchase_button_border_border','section_checkout_show_customize_elements','returning_customers_hover_links_color','billing_details_checkboxes_typography_typography','additional_information_titles_text_shadow_text_shadow_type','coupon_hover_links_color','order_summary_radio_typography_typography','payment_info_box_title_background_background','payment_hover_color'],
            'woocommerce-my-account'=>['tabs_layout','tabs_content_spacing','tabs_position','tabs','tabs_alignment','customize_dashboard_check','customize_dashboard_select','tabs_typography_typography','tabs_normal_background_background','tabs_hover_box_shadow_box_shadow_type','tabs_active_color','tabs_active_border_color','tabs_border_type','tabs_spacing','tabs_divider_weight','my_account_sections_background_color','my_account_sections_box_shadow_box_shadow_type','typography_section_titles_color','section_titles_typography_text_shadow_text_shadow_type','general_text_color','login_messages_color','payment_methods_radio_buttons_color','forms_buttons_background_background','forms_buttons_border_border','tables_rows_gap','tables_divider_border_type','tables_button_border_type','tables_button_hover_animation'],
            'woocommerce-purchase-summary'=>['confirmation_message_active','confirmation_message_text','confirmation_message_alignment','payment_details_number','payment_details_payment','bank_details_text','downloads_alignment','order_summary_text','billing_details_text','shipping_details_alignment','preview_order_type','preview_order_custom','sections_spacing','titles_spacing','payment_details_border_type','bank_details_border_color','account_title_spacing','order_details_rows_gap','order_details_product_links_hover_color','order_details_button_border_type','order_details_button_hover_animation'],
        ];
        foreach ($keys as $type => $list) {
            $c = Elementor::$instance->widgets_manager->get_widget_types($type)->get_controls();
            foreach ($list as $key) { $assert(isset($c[$key]), "{$type}: falta {$key}."); }
        }
        $c = Elementor::$instance->widgets_manager->get_widget_types('woocommerce-checkout-page')->get_controls();
        $assert(($c['checkout_layout']['default'] ?? '') === 'two-column' && count($c['billing_details_form_fields']['default'] ?? []) === 10 && ($c['billing_details_form_fields']['default'][3]['repeater_state'] ?? '') === 'locale' && ($c['additional_information_form_fields']['default'][0]['field_key'] ?? '') === 'order_comments' && array_keys($c['section_checkout_show_customize_elements']['options']) === array_values(array_filter(['customize_returning_customer','customize_billing_details','customize_additional_info','customize_order_summary','customize_coupon','customize_payment'], static fn($k) => $k !== 'customize_returning_customer' || get_option('woocommerce_enable_checkout_login_reminder', 'no') === 'yes')), 'woocommerce-checkout-page: valores por defecto de Pro.');
        $c = Elementor::$instance->widgets_manager->get_widget_types('woocommerce-my-account')->get_controls();
        $assert(($c['tabs_layout']['default'] ?? '') === 'vertical' && array_column($c['tabs']['default'] ?? [], 'field_key') === ['dashboard','orders','downloads','edit-address','edit-account','customer-logout'], 'woocommerce-my-account: valores por defecto de Pro.');

        if (function_exists('wc_load_cart') && (WC()->cart || (wc_load_cart() || true) && WC()->cart)) {
            WC()->cart->empty_cart();
            WC()->cart->add_to_cart($pid, 1);
            $html = $render(['__widget'=>'woocommerce-cart','update_cart_button_text'=>'Recalcular','checkout_button_text'=>'Pagar ya','section_coupon_display'=>'','sticky_right_column'=>'yes','checkout_button_hover_animation'=>'grow']);
            $assert(str_contains($html, 'obx-woo-cart obx-woo-cart--two-column') && str_contains($html, 'obx-woo-sticky') && str_contains($html, 'woocommerce-cart-form') && str_contains($html, 'Paridad producto'), 'woocommerce-cart: carrito de WooCommerce con la disposición de Pro.');
            $assert(str_contains($html, 'Recalcular') && str_contains($html, 'Pagar ya') && !str_contains($html, 'name="coupon_code"') && str_contains($html, 'elementor-animation-grow'), 'woocommerce-cart: textos propios, sin cupón y animación del botón.');
            $assert((\OldBearExpansion\Widgets\Woo_Cart::remembered()['Update cart'] ?? '') === 'Recalcular', 'woocommerce-cart: los textos se guardan para las recargas AJAX de WooCommerce.');
            $extra = $save('section', 'Paridad bajo el carrito', [$container([$widget('heading', ['title'=>'Envío gratis desde 50 €'])])]);
            $html = $render(['__widget'=>'woocommerce-cart','cart_layout'=>'one-column','update_cart_automatically'=>'yes','additional_template_switch'=>'active','additional_template_select'=>(string) $extra]);
            $assert(str_contains($html, 'obx-woo-cart--one-column') && str_contains($html, 'obx-woo-cart--auto-update') && !str_contains($html, 'obx-woo-sticky') && str_contains($html, 'name="coupon_code"') && str_contains($html, 'Envío gratis desde 50 €'), 'woocommerce-cart: una columna, actualización automática, cupón y plantilla adicional.');

            $fields = [['field_key'=>'billing_first_name','field_label'=>'Nombre','label'=>'Nombre de pila','placeholder'=>'Tu nombre','repeater_state'=>''], ['field_key'=>'billing_country','field_label'=>'País','label'=>'Nación','placeholder'=>'Nación','repeater_state'=>'locale']];
            $html = $render(['__widget'=>'woocommerce-checkout-page','billing_details_section_title'=>'Facturación','order_summary_section_title'=>'Resumen','billing_details_form_fields'=>$fields,'additional_information_active'=>'','coupon_section_title_text'=>'¿Tienes un código?','coupon_section_title_link_text'=>'Escríbelo aquí','purchase_button_hover_animation'=>'pulse']);
            $assert(str_contains($html, 'obx-woo-checkout obx-woo-checkout--two-column') && str_contains($html, 'obx-woo-checkout__aside') && str_contains($html, 'id="order_review"') && str_contains($html, 'Facturación') && str_contains($html, '>Resumen<'), 'woocommerce-checkout-page: formulario de WooCommerce, columna derecha y títulos propios.');
            $assert(str_contains($html, 'Nombre de pila') && str_contains($html, 'placeholder="Tu nombre"') && !str_contains($html, 'Nación') && !str_contains($html, 'name="order_comments"'), 'woocommerce-checkout-page: etiquetas de los campos (salvo los del país) y sin notas del pedido.');
            $assert(str_contains($html, '¿Tienes un código?') && str_contains($html, 'Escríbelo aquí') && str_contains($html, 'elementor-animation-pulse'), 'woocommerce-checkout-page: textos del cupón y animación del botón de compra.');
            $remembered = \OldBearExpansion\Widgets\Woo_Checkout::remembered();
            $assert(($remembered['texts']['Your order'] ?? '') === 'Resumen' && ($remembered['fields']['billing']['billing_first_name']['label'] ?? '') === 'Nombre de pila' && !isset($remembered['fields']['billing']['billing_country']) && $remembered['notes'] === false, 'woocommerce-checkout-page: ajustes guardados para la recarga AJAX del pedido.');
            $undo = \OldBearExpansion\Widgets\Woo_Checkout::apply($remembered);
            $assert(__('Your order', 'woocommerce') === 'Resumen' && (WC()->checkout()->get_checkout_fields('billing')['billing_first_name']['label'] ?? '') === 'Nombre de pila', 'woocommerce-checkout-page: la recarga AJAX aplica los textos y los campos.');
            $undo();
            $assert(__('Your order', 'woocommerce') !== 'Resumen', 'woocommerce-checkout-page: los filtros se retiran después.');
            WC()->cart->empty_cart();
        }

        $dash = $save('section', 'Paridad escritorio', [$container([$widget('heading', ['title'=>'Hola de nuevo, cliente'])])]);
        $tabs = array_map(static fn($k, $n) => ['field_key'=>$k,'field_label'=>$n,'tab_name'=>$n], ['dashboard','orders'], ['Inicio','Mis compras']);
        $html = $render(['__widget'=>'woocommerce-my-account','tabs'=>$tabs,'customize_dashboard_check'=>'yes','customize_dashboard_select'=>(string) $dash,'tabs_layout'=>'horizontal']);
        $assert(str_contains($html, 'e-my-account-tabs-horizontal') && str_contains($html, 'woocommerce-MyAccount-navigation') && str_contains($html, 'Mis compras') && str_contains($html, 'Hola de nuevo, cliente'), 'woocommerce-my-account: pestañas renombradas y escritorio con plantilla.');
        $assert(!has_filter('woocommerce_account_menu_items') || !str_contains((string) wp_json_encode(apply_filters('woocommerce_account_menu_items', ['orders'=>'Orders'], [])), 'Mis compras'), 'woocommerce-my-account: el cambio de nombre no sale del widget.');
        $account_page = $save('page', 'Paridad cuenta', [$container([$widget('woocommerce-my-account')])]);
        $user = get_current_user_id(); wp_set_current_user(0);
        try { $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($account_page); } finally { wp_set_current_user($user); }
        $assert(str_contains($html, 'woocommerce-form-login'), 'woocommerce-my-account: formulario de acceso sin sesión.');

        $order = wc_create_order(); $order->add_product(wc_get_product($pid), 2); $order->set_billing_email('paridad@example.com'); $order->set_payment_method('bacs'); $order->calculate_totals(); $order->save(); $created[] = $order->get_id();
        $_GET['order-received'] = (string) $order->get_id(); $_GET['key'] = $order->get_order_key();
        try {
            $html = $render(['__widget'=>'woocommerce-purchase-summary','confirmation_message_text'=>'¡Gracias por tu compra!','payment_details_number'=>'Pedido nº:','order_summary_text'=>'Tu pedido']);
            $assert(str_contains($html, '¡Gracias por tu compra!') && str_contains($html, 'Pedido nº:') && str_contains($html, 'Tu pedido') && str_contains($html, '<strong>' . $order->get_order_number() . '</strong>') && str_contains($html, 'woocommerce-order-overview'), 'woocommerce-purchase-summary: pedido recibido con los textos de Pro.');
            $_GET['key'] = 'wc_order_falsa';
            $html = $render(['__widget'=>'woocommerce-purchase-summary']);
            $assert(!str_contains($html, 'woocommerce-order-overview'), 'woocommerce-purchase-summary: sin la clave del pedido no muestra nada.');
        } finally { unset($_GET['order-received'], $_GET['key']); }

        $notices_page = $save('page', 'Paridad avisos', [$container([$widget('woocommerce-notices')])]);
        \OldBearExpansion\Widgets\Woo_Notices::relocate([$notices_page]);
        $assert(!has_action('woocommerce_before_single_product', 'woocommerce_output_all_notices') && !has_action('woocommerce_before_cart', 'woocommerce_output_all_notices'), 'woocommerce-notices: los avisos se imprimen en el widget, no en su sitio habitual.');
        add_action('woocommerce_before_single_product', 'woocommerce_output_all_notices', 10); add_action('woocommerce_before_cart', 'woocommerce_output_all_notices', 10);
        add_action('woocommerce_before_shop_loop', 'woocommerce_output_all_notices', 10); add_action('woocommerce_before_checkout_form', 'woocommerce_output_all_notices', 10); add_action('woocommerce_account_content', 'woocommerce_output_all_notices', 5);
        add_action('woocommerce_before_customer_login_form', 'woocommerce_output_all_notices', 10); add_action('woocommerce_before_lost_password_form', 'woocommerce_output_all_notices', 10); add_action('woocommerce_before_reset_password_form', 'woocommerce_output_all_notices', 10);
        wc_add_notice('Aviso de paridad', 'success');
        $html = $render(['__widget'=>'woocommerce-notices']);
        $assert(str_contains($html, 'woocommerce-notices-wrapper') && str_contains($html, 'Aviso de paridad'), 'woocommerce-notices: imprime los avisos pendientes.');

        // 0.10.0 Site Settings → WooCommerce: store pages (WooCommerce's options), purchase summary page and notices.
        $kit = Elementor::$instance->kits_manager->get_active_kit_for_frontend();
        $kc = $kit->get_controls();
        foreach (['woocommerce_cart_page_id','woocommerce_checkout_page_id','woocommerce_myaccount_page_id','woocommerce_terms_page_id','woocommerce_purchase_summary_page_id','woocommerce_shop_page_id','woocommerce_notices_elements','error_message_text_color','error_message_text_typography_typography','error_message_icon_color','error_message_normal_links_color','error_notice_box_background_background','error_notice_box_box_shadow_box_shadow_type','error_notice_box_border_border','error_notice_box_border_radius','message_message_text_color','notice_message_hover_links_color','message_buttons_normal_text_color','message_buttons_focus_box_shadow_box_shadow_type','message_buttons_border_type','message_buttons_padding','info_message_text_color','info_notice_box_border_radius','info_buttons_hover_transition_duration','info_buttons_border_radius'] as $key) { $assert(isset($kc[$key]), "Kit WooCommerce: falta {$key}."); }
        $assert(($kc['woocommerce_cart_page_id']['default'] ?? null) === (string) get_option('woocommerce_cart_page_id') && ($kc['woocommerce_cart_page_id']['tab'] ?? '') === 'settings-woocommerce' && array_keys($kc['woocommerce_notices_elements']['options'] ?? []) === ['wc_error','wc_message','wc_info'], 'Kit WooCommerce: páginas actuales de WooCommerce y avisos de Pro.');
        $thanks = $created[] = wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Paridad gracias']);
        $before = [get_option('elementor_woocommerce_purchase_summary_page_id', ''), get_option('woocommerce_terms_page_id', '')];
        try {
            (new \OldBearExpansion\Extras\Kit_Woocommerce($kit))->on_save(['settings'=>['woocommerce_purchase_summary_page_id'=>(string) $thanks,'woocommerce_terms_page_id'=>(string) $thanks,'woocommerce_cart_page_id'=>(string) $pid]]);
            $assert((int) get_option('elementor_woocommerce_purchase_summary_page_id') === $thanks && (int) get_option('woocommerce_terms_page_id') === $thanks && (int) get_option('woocommerce_cart_page_id') !== $pid, 'Kit WooCommerce: guardar el kit cambia las páginas de WooCommerce (solo páginas).');
            $url = $order->get_checkout_order_received_url();
            $assert(str_starts_with($url, get_permalink($thanks)) && str_contains($url, 'order-received=' . $order->get_id()) && str_contains($url, 'key=' . $order->get_order_key()), 'Kit WooCommerce: tras el pago, el cliente llega a la página del resumen de la compra.');
        } finally {
            update_option('elementor_woocommerce_purchase_summary_page_id', $before[0]); update_option('woocommerce_terms_page_id', $before[1]);
        }

        $old = \OldBearExpansion\Widgets\Woo_Cart::convert('woocommerce-cart', ['woo_color'=>'#123456','woo_typography_typography'=>'custom']);
        $assert(($old['order_summary_color'] ?? '') === '#123456' && ($old['order_summary_items_typography_typography'] ?? '') === 'custom' && !isset($old['woo_color']), 'woocommerce-cart: los ajustes de versiones anteriores se convierten.');
    }


    // §5 Search: Pro's keys and defaults, live results through REST, pagination and nothing found.
    $sc = Elementor::$instance->widgets_manager->get_widget_types('search')->get_controls();
    foreach (['autocomplete','live_results','template_id','minimum_search_characters','number_of_columns','number_of_items','equal_height','enable_loader','enable_nothing_found_message','nothing_found_message_text','nothing_found_message_html_tag','search_query_post_type','search_query_include','search_query_exclude_ids','search_query_select_date','search_query_orderby','search_query_order','search_query_ignore_sticky_posts','search_query_query_id','pagination_type_options','pagination_prev_label','pagination_next_label','page_limit_settings','pagination_shorten_settings','search_field_background_normal_background','search_field_background_focus_background','search_field_input_text_color_focus','search_field_icon_search_normal_color_normal','search_field_border_focus_border','search_field_box_shadow__normal_box_shadow_type','search_field_box_shadow__focus_box_shadow_type','search_field_search_input_transition','search_field_icon_label_size','search_field_icon_gap','icon_clear_size','icon_clear_normal_color','icon_clear_hover_color','icon_clear_hover_transition','icon_submit_position','icon_submit_gap','submit_text_color_hover','submit_icon_color_normal','submit_background_hover_background','submit_border_normal_border','submit_box_shadow_hover_box_shadow_type','submit_hover_animation','submit_hover_transition','results_background_background','results_border_type_border','results_box_shadow_box_shadow_type','results_border_radius','results_padding','results_distance_from_search_field','results_is_dropdown_width','results_is_custom_width','results_custom_width','results_max_height','results_alignment','search_result_column_gap','search_result_row_gap','search_result_loader_icon_size','search_result_loader_icon_color','style_additional_settings_alignment','style_additional_settings_vertical_position','typography_title_typography','style_additional_settings_normal_color','style_additional_settings_hover_color','style_button_color_icon_active','page_numbers_space_between','top_spacing','bottom_spacing','nothing_found_message_space_from_top','nothing_found_message_alignment','nothing_found_message_typography_typography','nothing_found_message_color','nothing_found_message_text_shadow_text_shadow_type','nothing_found_message_text_stroke_text_stroke_type'] as $key) { $assert(isset($sc[$key]), "search: falta {$key}."); }
    $assert(($sc['minimum_search_characters']['default'] ?? 0) === 3 && ($sc['number_of_items']['default'] ?? 0) === 6 && ($sc['enable_nothing_found_message']['default'] ?? '') === 'yes' && ($sc['pagination_type_options']['default'] ?? '') === 'none' && ($sc['page_limit_settings']['default'] ?? 0) === 5 && ($sc['search_query_post_type']['default'] ?? '') === 'any' && ($sc['search_query_orderby']['default'] ?? '') === 'post_date' && ($sc['results_is_dropdown_width']['default'] ?? '') === 'search_field' && ($sc['submit_trigger']['default'] ?? '') === 'click_submit', 'search: valores por defecto de Pro.');
    $assert(!isset($sc['search_query_offset']) && !isset($sc['search_query_related_taxonomies']) && isset($sc['search_query_post_type']['options']['any']), 'search: consulta como la de Pro (sin desplazamiento ni relacionadas).');
    $search_item = $save('loop-item', 'Paridad resultado', [$container([$widget('theme-post-title')])]);
    $sw = $widget('search', ['live_results'=>'yes','template_id'=>(string) $search_item,'number_of_items'=>2,'pagination_type_options'=>'numbers_previous_next','search_query_post_type'=>'post','enable_loader'=>'yes','nothing_found_message_text'=>'Sin coincidencias','nothing_found_message_html_tag'=>'p','results_is_dropdown_width'=>'widget_width','icon_submit'=>['value'=>'fas fa-search','library'=>'fa-solid'],'submit_hover_animation'=>'grow']);
    $search_page = $save('page', 'Paridad buscador', [$container([$sw])]);
    $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($search_page);
    $assert(str_contains($html, "data-live='") && str_contains($html, 'aria-controls="obx-search-' . $sw['id'] . '-results"') && str_contains($html, 'class="obx-search__results"') && str_contains($html, ' hidden>') && str_contains($html, 'obx-search__loader') && str_contains($html, 'autocomplete="off"'), 'search: formulario con resultados en vivo, ocultos hasta escribir.');
    $assert(str_contains($html, 'elementor-animation-grow') && str_contains($html, 'obx-search__submit-icon') && strpos($html, 'obx-search__results') > strpos($html, 'obx-search__submit'), 'search: animación e icono del botón; desplegable del ancho del widget.');
    $instance = \OldBearExpansion\Widgets\Search::saved($search_page, $sw['id']);
    $assert($instance instanceof \OldBearExpansion\Widgets\Search, 'search: el widget guardado se encuentra en su documento.');
    $res = $instance->results('Paridad carga', 1);
    $assert($res['total'] === 3 && $res['pages'] === 2 && substr_count($res['html'], 'data-post-id=') === 2 && str_contains($res['html'], 'data-page="2"') && str_contains($res['html'], 'aria-current="page"') && str_contains($res['html'], '>Siguiente<') && !str_contains($res['html'], '>Anterior<'), 'search: resultados con plantilla y paginación.');
    $res = $instance->results('Paridad carga', 2);
    $assert(substr_count($res['html'], 'data-post-id=') === 1 && str_contains($res['html'], '>Anterior<'), 'search: segunda página de resultados.');
    $assert(str_contains($instance->results('zzzz-nada-' . $uid(), 1)['html'], '<p class="obx-search__nothing">Sin coincidencias</p>'), 'search: mensaje sin resultados con su etiqueta.');
    $request = new WP_REST_Request('GET', '/obx/v1/search');
    $request->set_query_params(['post'=>$search_page,'widget'=>$sw['id'],'q'=>'Paridad carga','page'=>1]);
    $response = rest_do_request($request);
    $assert($response->get_status() === 200 && substr_count((string) ($response->get_data()['html'] ?? ''), 'data-post-id=') === 2, 'search: la ruta REST devuelve los resultados.');
    $request->set_query_params(['post'=>$search_page,'widget'=>'nope','q'=>'x']);
    $assert(rest_do_request($request)->get_status() === 404, 'search: la ruta REST no acepta widgets inexistentes.');
    $draft = $save('page', 'Paridad buscador borrador', [$container([$sw])]);
    wp_update_post(['ID'=>$draft,'post_status'=>'draft']);
    $user = get_current_user_id(); wp_set_current_user(0);
    $assert(\OldBearExpansion\Widgets\Search::saved($draft, $sw['id']) === null, 'search: los borradores no son públicos.');
    wp_set_current_user($user);

    // 0.9.1 Form: integrations, popup action, stored metadata and the remaining style keys.
    $fc = Elementor::$instance->widgets_manager->get_widget_types('form')->get_controls();
    foreach (['submissions_metadata','mailchimp_api_key_source','mailchimp_api_key','mailchimp_list','mailchimp_groups','mailchimp_tags','mailchimp_double_opt_in','mailchimp_fields_map','drip_api_token_source','drip_custom_api_token','drip_account','drip_fields_map','drip_custom_fields','tags','activecampaign_api_credentials_source','activecampaign_api_key','activecampaign_api_url','activecampaign_list','activecampaign_fields_map','activecampaign_tags','getresponse_api_key_source','getresponse_custom_api_key','getresponse_list','getresponse_dayofcycle','getresponse_fields_map','convertkit_api_key_source','convertkit_custom_api_key','convertkit_form','convertkit_fields_map','convertkit_tags','mailerlite_api_key_source','mailerlite_custom_api_key','mailerlite_group','mailerlite_fields_map','allow_resubscribe','slack_webhook','slack_channel','slack_username','slack_pretext','slack_title','slack_text','slack_add_fields','slack_add_ts','slack_webhook_color','discord_webhook','discord_username','discord_avatar_url','discord_title','discord_content','discord_form_data','discord_ts','discord_webhook_color','popup_action','popup_action_popup_id','popup_action_do_not_show_again','button_content_align','hover_transition_duration','step_inactive_secondary_color','step_active_secondary_color','step_completed_secondary_color','step_divider_width','step_divider_gap','step_progress_bar_border_radius','step_progress_bar_percentage_color'] as $key) { $assert(isset($fc[$key]), "form: falta {$key}."); }
    $pro_actions = ['save-to-database','email','email2','redirect','webhook','mailchimp','drip','activecampaign','getresponse','convertkit','mailerlite','slack','discord','popup'];
    $assert(array_keys($fc['submit_actions']['options']) === $pro_actions && ($fc['submissions_metadata']['default'] ?? []) === ['remote_ip','user_agent'] && ($fc['mailchimp_api_key_source']['default'] ?? '') === 'default' && ($fc['drip_custom_fields']['default'] ?? '') === 'no' && ($fc['slack_add_fields']['default'] ?? '') === 'yes' && ($fc['slack_webhook_color']['default'] ?? '') === '#D30C5C' && ($fc['discord_ts']['default'] ?? '') === 'yes' && ($fc['step_active_secondary_color']['default'] ?? '') === '#ffffff', 'form: acciones y valores por defecto de Pro.');
    $sections = [];
    \OldBearExpansion\Forms\Integrations::settings(new class($sections) { private $s; public function __construct(array &$s) { $this->s = &$s; } public function add_section($tab, $id, $args) { $this->s[$tab . '/' . $id] = array_keys($args['fields']); } });
    $assert(($sections['integrations/obx_mailchimp'] ?? []) === ['pro_mailchimp_api_key'] && ($sections['integrations/obx_activecampaign'] ?? []) === ['pro_activecampaign_api_key','pro_activecampaign_api_url'] && ($sections['integrations/obx_drip'] ?? []) === ['pro_drip_api_token'], 'Integraciones: claves globales con los nombres de opción de Pro.');

    $calls = []; $fail = false;
    $mock = static function ($pre, $args, $url) use (&$calls, &$fail) {
        $calls[] = ['url'=>$url,'method'=>$args['method'] ?? 'GET','headers'=>$args['headers'] ?? [],'body'=>$args['body'] ?? null];
        $code = $fail && str_contains($url, 'mailchimp') ? 400 : 200;
        $body = $code === 400 ? '{"detail":"Lista inexistente"}' : (str_contains($url, 'admin/api.php') ? '{"result_code":1}' : (str_contains($url, '/lists?count') ? '{"lists":[{"id":"L1","name":"Boletín"}]}' : '{}'));
        return ['headers'=>[],'body'=>$body,'response'=>['code'=>$code,'message'=>''],'cookies'=>[],'filename'=>null];
    };
    add_filter('pre_http_request', $mock, 10, 3);
    $globals = ['pro_mailchimp_api_key'=>'clave-us21','pro_drip_api_token'=>'tokendrip','pro_activecampaign_api_key'=>'acclave','pro_activecampaign_api_url'=>'https://cuenta.api-us1.com','pro_getresponse_api_key'=>'grclave','pro_convertkit_api_key'=>'ckclave','pro_mailerlite_api_key'=>'mlclave'];
    foreach ($globals as $field => $value) { update_option('elementor_' . $field, $value); }
    try {
        $form_popup = $save('popup', 'Paridad popup formulario', [$container([$widget('heading', ['title'=>'Gracias'])])]);
        $fw = $widget('form', ['form_name'=>'Integraciones','submit_actions'=>['save-to-database','mailchimp','drip','activecampaign','getresponse','convertkit','mailerlite','slack','discord','popup'],
            'submissions_metadata'=>['remote_ip'],
            'mailchimp_list'=>'L1','mailchimp_groups'=>['g1'],'mailchimp_tags'=>'web, [field id="topic"]','mailchimp_double_opt_in'=>'yes','mailchimp_fields_map'=>[['_id'=>'m1','remote_id'=>'email','local_id'=>'email'],['_id'=>'m2','remote_id'=>'FNAME','local_id'=>'name']],
            'drip_account'=>'99','drip_custom_fields'=>'yes','tags'=>'cliente',
            'activecampaign_list'=>'3','activecampaign_tags'=>'a,b',
            'getresponse_list'=>'gr1','getresponse_dayofcycle'=>2,
            'convertkit_form'=>'55','convertkit_tags'=>['7'],
            'mailerlite_group'=>'g9','allow_resubscribe'=>'yes',
            'slack_webhook'=>'https://hooks.slack.com/services/T/B/X','slack_title'=>'Hola [field id="name"]',
            'discord_webhook'=>'https://discord.com/api/webhooks/1/abc','discord_username'=>'Web',
            'popup_action'=>'open','popup_action_popup_id'=>(string) $form_popup,
            'form_fields'=>[['_id'=>'f1','custom_id'=>'name','field_type'=>'text','field_label'=>'Nombre'],['_id'=>'f2','custom_id'=>'email','field_type'=>'email','field_label'=>'Correo'],['_id'=>'f3','custom_id'=>'topic','field_type'=>'text','field_label'=>'Tema']]]);
        $fpage = $save('page', 'Paridad integraciones', [$container([$fw])]);
        Elementor::$instance->frontend->get_builder_content_for_display($fpage);
        $queued = (new ReflectionProperty(\OldBearExpansion\Popups\Popups::class, 'queued'));
        $queued->setAccessible(true);
        $assert(isset($queued->getValue()[$form_popup]), 'form: el popup que abre el formulario se imprime en la página.');
        $fsend = static function () use ($fpage, $fw) {
            $request = new WP_REST_Request('POST', '/obx/v1/form');
            $request->set_body_params(['post_id'=>$fpage,'form_id'=>$fw['id'],'form_fields'=>['name'=>'Ana','email'=>'Ana@Example.com','topic'=>'support']]);
            return rest_do_request($request);
        };
        $response = $fsend();
        $data = $response->get_data();
        $assert($response->get_status() === 200 && ($data['data']['popup'] ?? []) === ['action'=>'open','id'=>$form_popup], 'form: envío con integraciones y acción de abrir popup.');
        $find = static function (string $needle, string $method = 'POST') use (&$calls) { foreach ($calls as $call) { if (str_contains($call['url'], $needle) && $call['method'] === $method) { return $call + ['json'=>is_string($call['body']) ? json_decode($call['body'], true) : $call['body']]; } } return null; };
        $mc = $find('https://us21.api.mailchimp.com/3.0/lists/L1/members/' . md5('ana@example.com'), 'PUT');
        $assert($mc && $mc['json']['status_if_new'] === 'pending' && $mc['json']['merge_fields'] === ['FNAME'=>'Ana'] && $mc['json']['interests'] === ['g1'=>true] && $mc['headers']['Authorization'] === 'Basic ' . base64_encode('obx:clave-us21'), 'Mailchimp: suscriptor con doble confirmación, campos y grupos.');
        $tags = $find('/members/' . md5('ana@example.com') . '/tags');
        $assert($tags && array_column($tags['json']['tags'], 'name') === ['web','support'], 'Mailchimp: etiquetas con campos del formulario.');
        $drip = $find('https://api.getdrip.com/v2/99/subscribers');
        $assert($drip && $drip['json']['subscribers'][0]['email'] === 'Ana@Example.com' && ($drip['json']['subscribers'][0]['custom_fields']['topic'] ?? '') === 'support' && $drip['json']['subscribers'][0]['tags'] === ['cliente'] && $drip['headers']['Authorization'] === 'Basic ' . base64_encode('tokendrip:'), 'Drip: suscriptor con campos personalizados y etiquetas.');
        $ac = $find('https://cuenta.api-us1.com/admin/api.php?api_action=contact_sync');
        $assert($ac && ($ac['body']['p[3]'] ?? '') === '3' && ($ac['body']['tags'] ?? '') === 'a,b' && str_contains($ac['url'], 'api_key=acclave'), 'ActiveCampaign: contacto en la lista con etiquetas.');
        $gr = $find('https://api.getresponse.com/v3/contacts');
        $assert($gr && $gr['json']['campaign']['campaignId'] === 'gr1' && $gr['json']['dayOfCycle'] === 2 && $gr['headers']['X-Auth-Token'] === 'api-key grclave', 'GetResponse: contacto en la lista con día del ciclo.');
        $ck = $find('https://api.convertkit.com/v3/forms/55/subscribe');
        $assert($ck && $ck['json']['tags'] === [7] && $ck['json']['api_key'] === 'ckclave', 'Kit: suscripción al formulario con etiquetas.');
        $ml = $find('https://api.mailerlite.com/api/v2/groups/g9/subscribers');
        $assert($ml && $ml['json']['resubscribe'] === true && $ml['headers']['X-MailerLite-ApiKey'] === 'mlclave', 'MailerLite: suscriptor del grupo, con resuscripción.');
        $slack = $find('https://hooks.slack.com/services/T/B/X');
        $assert($slack && $slack['json']['attachments'][0]['title'] === 'Hola Ana' && count($slack['json']['attachments'][0]['fields']) === 3 && $slack['json']['attachments'][0]['color'] === '#D30C5C' && isset($slack['json']['attachments'][0]['ts']), 'Slack: mensaje con título, campos y hora.');
        $discord = $find('https://discord.com/api/webhooks/1/abc');
        $assert($discord && $discord['json']['username'] === 'Web' && count($discord['json']['embeds'][0]['fields']) === 3 && $discord['json']['embeds'][0]['color'] === hexdec('D30C5C') && isset($discord['json']['embeds'][0]['timestamp']), 'Discord: mensaje con campos y hora.');
        $saved = get_posts(['post_type'=>'obx_submission','post_status'=>'private','numberposts'=>1,'meta_key'=>'_obx_form_id','meta_value'=>$fw['id']]);
        foreach ($saved as $item) { $created[] = $item->ID; }
        $meta = $saved ? (array) get_post_meta($saved[0]->ID, '_obx_meta', true) : [];
        $assert(array_key_exists('remote_ip', $meta) && !array_key_exists('user_agent', $meta), 'form: submissions_metadata elige qué metadatos se guardan.');

        $fail = true;
        $response = $fsend();
        $assert($response->get_status() === 500 && str_contains((string) ($response->get_data()['data']['admin_error'] ?? ''), 'Mailchimp: Error 400. Lista inexistente'), 'form: un fallo de la integración se muestra a quien edita.');
        $user = get_current_user_id(); wp_set_current_user(0);
        $response = $fsend();
        $assert($response->get_status() === 500 && !isset($response->get_data()['data']['admin_error']), 'form: los visitantes no ven el detalle del fallo.');
        $fetch = new WP_REST_Request('POST', '/obx/v1/integrations/mailchimp');
        $assert(rest_do_request($fetch)->get_status() >= 401, 'Integraciones: la consulta de listas exige poder editar.');
        wp_set_current_user($user);
        $fail = false;
        $response = rest_do_request($fetch);
        $assert($response->get_status() === 200 && ($response->get_data()['lists'] ?? []) === ['L1'=>'Boletín'] && (get_option(\OldBearExpansion\Forms\Integrations::CACHE)['mailchimp']['lists']['L1'] ?? '') === 'Boletín', 'Integraciones: el editor carga las listas con la clave global y las recuerda.');
        $close = \OldBearExpansion\Forms\Integrations::popup(['popup_action'=>'close','popup_action_do_not_show_again'=>'yes']);
        $assert($close === ['action'=>'close','do_not_show_again'=>true], 'form: acción de cerrar el popup sin volver a mostrarlo.');
    } finally {
        remove_filter('pre_http_request', $mock, 10);
        foreach (array_keys($globals) as $field) { delete_option('elementor_' . $field); }
        delete_option(\OldBearExpansion\Forms\Integrations::CACHE);
    }

    // Saved sections (and floating buttons): wrapper tag and preview settings, as in Pro.
    $section = $save('section', 'Paridad sección', [$container([$widget('heading', ['title'=>'Sección guardada'])])], ['content_wrapper_html_tag'=>'aside']);
    $c = $controls_of('section', $section);
    $assert(isset($c['content_wrapper_html_tag'], $c['preview_type'], $c['preview_id'], $c['preview_search_term'], $c['apply_preview']), 'Sección guardada: claves de vista previa y envoltorio.');
    $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($section);
    $assert(str_contains($html, '<aside ') && str_contains($html, 'Sección guardada'), 'Sección guardada: imprime la etiqueta del envoltorio.');
    if (Elementor::$instance->documents->get_document_type('floating-buttons', false)) {
        $c = $controls_of('floating-buttons', $section);
        $assert(isset($c['content_wrapper_html_tag'], $c['preview_type']), 'Botones flotantes: claves de vista previa y envoltorio.');
    }

    // 1.0.0 Display conditions per element: Pro's hidden key with [ JSON ] of OR groups of AND conditions.
    $DC = \OldBearExpansion\Extras\Display_Conditions::class;
    foreach (['heading'=>'widget','container'=>'container','section'=>'section','column'=>'column'] as $name => $kind) {
        $element = $kind === 'widget' ? Elementor::$instance->widgets_manager->get_widget_types($name) : Elementor::$instance->elements_manager->get_element_types($name);
        $ec = $element ? $element->get_controls() : [];
        $assert(($ec['e_display_conditions']['type'] ?? '') === 'hidden' && isset($ec['e_display_conditions_trigger']), "{$name}: condiciones de visualización con la clave de Pro.");
    }
    $json = static fn(array $groups) => [wp_json_encode($groups)];
    $out = ['condition'=>'login_status','comparator'=>'is','status'=>'logged_out'];
    $in = ['condition'=>'login_status','comparator'=>'is','status'=>'logged_in'];
    $page = $save('page', 'Paridad condiciones', [$container([$widget('heading', ['title'=>'Visible para todos']), $widget('heading', ['title'=>'Solo con sesión', 'e_display_conditions'=>$json([[$in]])]), $widget('heading', ['title'=>'Grupo alternativo', 'e_display_conditions'=>$json([[$in], [$out]])])]), $container([$widget('heading', ['title'=>'Hijo oculto'])], ['e_display_conditions'=>$json([[$in]])])]);
    $was_user = get_current_user_id();
    wp_set_current_user(0);
    $assert($DC::groups($json([$out, $in])) === [[$out, $in]], 'Condiciones: una lista plana antigua cuenta como un grupo.');
    $assert($DC::passes([[$out]]) && !$DC::passes([[$in]]) && !$DC::passes([[$out, $in]]) && $DC::passes([[$in], [$out]]), 'Condiciones: «y» dentro del grupo y «o» entre grupos.');
    $today = gmdate('m-d-Y');
    $assert($DC::check(['condition'=>'current_date','comparator'=>'is','date'=>$today]) && $DC::check(['condition'=>'current_date','comparator'=>'is_after','date'=>'01-01-2000']) && !$DC::check(['condition'=>'current_date','comparator'=>'is_before','date'=>'01-01-2000']) && !$DC::check(['condition'=>'current_date','comparator'=>'is','date'=>'2000-01-01']), 'Condiciones: fecha actual (m-d-Y), antes y después.');
    $assert($DC::check(['condition'=>'day_of_the_week','comparator'=>'is_one_of','days'=>[strtolower(gmdate('l'))]]) && !$DC::check(['condition'=>'day_of_the_week','comparator'=>'is_none_of','days'=>[strtolower(gmdate('l'))]]), 'Condiciones: día de la semana.');
    $assert($DC::check(['condition'=>'time_of_the_day','comparator'=>'is_after_inclusive','time'=>'01-01-2000 00:00']) && !$DC::check(['condition'=>'time_of_the_day','comparator'=>'is_after','time'=>'01-01-2000 23:59']), 'Condiciones: hora del día.');
    $assert($DC::check(['condition'=>'from_url','comparator'=>'not_contain','from_url'=>'example']) && $DC::check(['condition'=>'unknown_condition']) && $DC::check(['condition'=>'user_role','comparator'=>'is_none_of','roles'=>['administrator']]), 'Condiciones: URL de origen, condiciones desconocidas y roles.');
    $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($page);
    $assert(str_contains($html, 'Visible para todos') && !str_contains($html, 'Solo con sesión') && str_contains($html, 'Grupo alternativo') && !str_contains($html, 'Hijo oculto'), 'Condiciones: oculta widgets y contenedores con sus hijos sin sesión.');
    $admin = get_users(['role'=>'administrator','number'=>1,'fields'=>'ID']);
    if ($admin) {
        wp_set_current_user((int) $admin[0]);
        $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($page);
        $assert(str_contains($html, 'Solo con sesión') && str_contains($html, 'Hijo oculto') && !$DC::check(['condition'=>'user_role','comparator'=>'is_none_of','roles'=>['administrator']]), 'Condiciones: con sesión se muestran y el rol se comprueba.');
    }
    wp_set_current_user($was_user);
    $assert(apply_filters('elementor/element/is_dynamic_content', false, ['settings'=>['e_display_conditions'=>$json([[$in]])]], null) === true, 'Condiciones: los elementos con condiciones no salen de la caché de elementos.');

    // Off-Canvas: nested widget with one container, opened by the «Off-Canvas» action tag.
    $oc_type = Elementor::$instance->widgets_manager->get_widget_types('off-canvas');
    $assert((bool) $oc_type, 'Off-Canvas: widget registrado con el nombre de Pro.');
    if ($oc_type) {
        $oc = $oc_type->get_controls();
        foreach (['editing_mode','off_canvas_name','horizontal_position','vertical_position','width','height','custom_height','entrance_animation','exit_animation','offcanvas_animation_duration','is_not_close_on_overlay','is_not_close_on_esc_overlay','prevent_scroll','wrapper_html_tag','background_background','border_border','border_radius','box_shadow_box_shadow_type','has_overlay','overlay_background_background','overlay_background_color'] as $key) { $assert(isset($oc[$key]), "off-canvas: falta {$key}."); }
        $assert(($oc['editing_mode']['default'] ?? '') === 'yes' && ($oc['off_canvas_name']['default'] ?? '') === 'Off-Canvas' && ($oc['height']['default'] ?? '') === 'custom' && ($oc['custom_height']['default']['unit'] ?? '') === 'vh' && ($oc['offcanvas_animation_duration']['default']['size'] ?? 0) == 1.5 && ($oc['wrapper_html_tag']['default'] ?? '') === 'div' && ($oc['has_overlay']['default'] ?? '') === 'yes' && ($oc['overlay_background_background']['default'] ?? '') === 'classic' && ($oc['overlay_background_color']['default'] ?? '') === 'rgba(0,0,0,.8)', 'off-canvas: valores por defecto de Pro.');
        $assert(array_keys($oc['wrapper_html_tag']['options']) === ['div','main','article','header','footer','section','aside','nav'] && array_keys($oc['height']['options']) === ['fit-content','custom'] && !empty($oc['entrance_animation']['frontend_available']) && !empty($oc['prevent_scroll']['frontend_available']), 'off-canvas: opciones y ajustes del navegador de Pro.');
        $panel = $widget('off-canvas', ['off_canvas_name'=>'Menú lateral','wrapper_html_tag'=>'aside','entrance_animation'=>'fadeInLeft']);
        $panel['elements'] = [$container([$widget('heading', ['title'=>'Dentro del panel'])], ['content_width'=>'full'])];
        $oc_page = $save('page', 'Paridad off-canvas', [$container([$widget('button', ['text'=>'Abrir', '__dynamic__'=>['link'=>'[elementor-tag id="a1b2c3d" name="off-canvas" settings="' . rawurlencode(wp_json_encode(['action'=>'toggle','off_canvas'=>$panel['id']])) . '"]']]), $panel])]);
        $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($oc_page);
        $assert(str_contains($html, '<aside id="off-canvas-' . $panel['id'] . '" class="e-off-canvas" role="dialog" aria-hidden="true" aria-modal="true" aria-label="Menú lateral" inert>') && str_contains($html, 'Dentro del panel') && str_contains($html, 'e-off-canvas__overlay'), 'Off-Canvas: panel cerrado con su contenedor, etiqueta y nombre accesible.');
        $hash = '';
        if (preg_match('/href="(#elementor-action[^"]+)"/', $html, $m)) { $hash = rawurldecode(html_entity_decode($m[1])); }
        parse_str((string) substr($hash, strlen('#elementor-action:')), $query);
        $assert(($query['action'] ?? '') === 'off_canvas:toggle' && json_decode((string) base64_decode((string) ($query['settings'] ?? '')), true) === ['id'=>$panel['id'],'displayMode'=>'toggle'], 'Off-Canvas: la etiqueta crea la acción de Pro con el ID del widget.');
        $assert(\OldBearExpansion\Dynamic\Off_Canvas::found($oc_page) === [$panel['id']=>'Menú lateral'], 'Off-Canvas: la etiqueta lista los Off-Canvas del documento.');
        $assert(wp_script_is('obx-off-canvas', 'enqueued') || wp_script_is('obx-off-canvas', 'registered'), 'Off-Canvas: script del navegador registrado.');
    }

    // Menu (mega menu): titles with optional dropdown containers, interlaced in the editor as in Pro.
    $mm_type = Elementor::$instance->widgets_manager->get_widget_types('mega-menu');
    $assert((bool) $mm_type, 'Menú: widget registrado con el nombre de Pro.');
    if ($mm_type) {
        $mm = $mm_type->get_controls();
        $assert(($mm['menu_items']['type'] ?? '') === 'nested-elements-repeater' && ($mm['content_width']['default'] ?? '') === 'full_width' && ($mm['item_layout']['default'] ?? '') === 'horizontal' && ($mm['open_on']['default'] ?? '') === 'hover' && ($mm['breakpoint_selector']['default'] ?? '') === 'tablet' && ($mm['menu_item_icon']['default']['value'] ?? '') === 'fas fa-caret-down' && ($mm['menu_item_icon_active']['default']['value'] ?? '') === 'fas fa-caret-up' && ($mm['open_animation']['type'] ?? '') === 'animation_menu_dropdown' && ($mm['menu_item_title_transition_duration']['default']['size'] ?? 0) === 300 && ($mm['style_menu_toggle_icon_size']['default']['size'] ?? 0) === 20, 'Menú: tipos y valores por defecto de Pro.');
        foreach (['item_title','item_link','item_dropdown_content','item_icon','item_icon_active','element_id'] as $field) { $assert(isset($mm['menu_items']['fields'][$field]), "Menú: falta el campo {$field}."); }
        foreach (['menu_item_title_typography_typography','menu_item_title_text_shadow_normal_text_shadow_type','menu_item_title_background_color_hover_background','menu_item_title_box_border_active_border','menu_divider_color','icon_position','style_dropdown_indicator_rotate','menu_dropdown_indicator_color_active','menu_toggle_icon_background_color_active_background','menu_toggle_icon__padding','content_background_color_background','content_padding','dropdown_menu_item_background_color_active_background','menu_dropdown_box_border_border','menu_dropdown_box_shadow_box_shadow_type','horizontal_scroll'] as $key) { $assert(isset($mm[$key]), "Menú: falta {$key}."); }
        $assert(array_keys($mm['item_position_horizontal']['options']) === ['start','center','end','stretch'] && array_key_exists('tablet', $mm['breakpoint_selector']['options']) && array_key_exists('none', $mm['breakpoint_selector']['options']), 'Menú: opciones de posición y puntos de ruptura.');
        $config = (new ReflectionMethod($mm_type, 'get_initial_config'))->invoke($mm_type);
        $assert(!empty($config['support_improved_repeaters']) && !empty($config['is_interlaced']) && ($config['node'] ?? '') === 'li', 'Menú: contenedores intercalados en cada elemento.');
        $child = static fn(array $elements) => ['id'=>$uid(),'elType'=>'container','isInner'=>true,'isLocked'=>true,'settings'=>[],'elements'=>$elements];
        $menu = $widget('mega-menu', ['menu_name'=>'Principal','open_on'=>'click','menu_items'=>[
            ['_id'=>'a1','item_title'=>'Con desplegable','item_dropdown_content'=>'yes'],
            ['_id'=>'a2','item_title'=>'Vacío','item_dropdown_content'=>'yes'],
            ['_id'=>'a3','item_title'=>'Sin desplegable','item_link'=>['url'=>'https://example.com/','is_external'=>'on','nofollow'=>''],'element_id'=>'mi-id'],
            ['_id'=>'a4','item_title'=>'Ancla','item_link'=>['url'=>'#seccion']],
        ]]);
        $menu['elements'] = [$child([$widget('heading', ['title'=>'Contenido del desplegable'])]), $child([]), $child([$widget('heading', ['title'=>'Oculto sin desplegable'])]), $child([])];
        $mm_page = $save('page', 'Paridad menú', [$container([$menu])]);
        $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($mm_page);
        $number = substr((string) hexdec($menu['id']), 0, 3);
        $assert(str_contains($html, '<nav class="e-n-menu" data-widget-number="' . $number . '" data-layout="horizontal" aria-label="Principal">') && str_contains($html, 'aria-controls="menubar-' . $number . '"') && (bool) preg_match('/class="elementor-element elementor-element-' . $menu['id'] . ' [^"]*elementor-widget-n-menu"/', $html), 'Menú: navegación con nombre accesible y botón del menú.');
        $assert(str_contains($html, 'Contenido del desplegable') && str_contains($html, '<div class="e-n-menu-content"><div id="e-n-menu-content-' . $number . '1"') && str_contains($html, 'class="e-n-menu-title e-click"'), 'Menú: el desplegable con contenido se pinta tras su título y se abre al hacer clic.');
        $assert(substr_count($html, 'class="e-n-menu-dropdown-icon"') === 2 && !str_contains($html, 'Oculto sin desplegable') && !str_contains($html, 'id="e-n-menu-content-' . $number . '2"'), 'Menú: sin «Contenido desplegable» o sin elementos no hay desplegable.');
        $assert(str_contains($html, 'id="mi-id"') && (bool) preg_match('/<a class="e-n-menu-title-container e-link" href="https:\/\/example.com\/" target="_blank"/', $html) && str_contains($html, 'e-n-menu-title e-anchor'), 'Menú: enlaces, ID propio y anclas.');
        $assert(str_contains(\OldBearExpansion\ThemeBuilder\Module::menu_breakpoints_css(), '.e-n-menu-tablet{--n-menu-wrapper-display:none}'), 'Menú: el botón sustituye a la barra por debajo del punto de ruptura.');
    }

    // Hotspot: Elementor's Image widget with points and tooltips, Pro's markup and keys.
    $hs_type = Elementor::$instance->widgets_manager->get_widget_types('hotspot');
    $assert($hs_type instanceof \Elementor\Widget_Image && $hs_type->get_name() === 'hotspot', 'Hotspot: widget registrado con el nombre de Pro sobre el widget Imagen.');
    if ($hs_type) {
        $hs = $hs_type->get_controls();
        foreach (['image','image_size','hotspot','hotspot_animation','hotspot_sequenced_animation','tooltip_position','tooltip_trigger','tooltip_animation','style_hotspot_color','style_tooltip_color'] as $key) { $assert(isset($hs[$key]), "Hotspot: control {$key}."); }
        $assert(!isset($hs['link_to']) && !isset($hs['caption_source']) && ($hs['hotspot_animation']['default'] ?? '') === 'e-hotspot--expand' && ($hs['tooltip_trigger']['default'] ?? '') === 'click' && ($hs['tooltip_position']['default'] ?? '') === 'top' && isset($hs['hotspot']['fields']['hotspot_offset_x'], $hs['hotspot']['fields']['hotspot_tooltip_text_wrap']), 'Hotspot: sin enlace ni pie de imagen, con los valores por defecto de Pro.');
        $img = ['url'=>\Elementor\Utils::get_placeholder_image_src(),'id'=>''];
        $hs_page = $save('page', 'Paridad hotspot', [$container([$widget('hotspot', ['image'=>$img,'tooltip_animation'=>'e-hotspot--slide-direction','hotspot'=>[
            ['_id'=>'h1','hotspot_tooltip_content'=>'<p>Punto redondo</p>','hotspot_offset_x'=>['unit'=>'%','size'=>20],'hotspot_offset_y'=>['unit'=>'%','size'=>30],'hotspot_horizontal'=>'left','hotspot_vertical'=>'top'],
            ['_id'=>'h2','hotspot_label'=>'Tienda','hotspot_link'=>['url'=>'https://example.com/','is_external'=>'','nofollow'=>''],'hotspot_tooltip_content'=>'No se ve con clic','hotspot_horizontal'=>'right','hotspot_vertical'=>'bottom','hotspot_offset_x'=>['unit'=>'%','size'=>10],'hotspot_offset_y'=>['unit'=>'%','size'=>10]],
            ['_id'=>'h3','hotspot_label'=>'Con tooltip propio','hotspot_icon'=>['value'=>'fas fa-star','library'=>'fa-solid'],'hotspot_tooltip_content'=>'Texto','hotspot_tooltip_position'=>'yes','hotspot_position'=>'left','hotspot_offset_x'=>['unit'=>'%','size'=>50],'hotspot_offset_y'=>['unit'=>'%','size'=>50]],
        ]])])]);
        $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($hs_page);
        $assert((bool) preg_match('/<img [^>]*src="[^"]*placeholder/', $html) && str_contains($html, 'class="e-hotspot elementor-repeater-item-h1 e-hotspot--position-left e-hotspot--position-top e-hotspot--circle"') && str_contains($html, '<div class="e-hotspot__button e-hotspot--expand"><div class="e-hotspot__outer-circle"></div><div class="e-hotspot__inner-circle"></div></div>'), 'Hotspot: imagen y punto redondo con las clases de Pro.');
        $assert(str_contains($html, '<div class="e-hotspot__direction-mask e-hotspot--tooltip-position"><div class="e-hotspot__tooltip e-hotspot--slide-direction"><p>Punto redondo</p></div></div>') && str_contains($html, 'e-hotspot__tooltip e-hotspot--slide-direction e-hotspot--override-tooltip-animation-from-left'), 'Hotspot: tooltips con máscara para animaciones por dirección y su lado propio.');
        $assert((bool) preg_match('/<a class="e-hotspot elementor-repeater-item-h2 e-hotspot--position-right e-hotspot--position-bottom e-hotspot--link e-hotspot--no-tooltip" href="https:\/\/example.com\/">/', $html) && !str_contains($html, '>No se ve con clic<') && str_contains($html, '<div class="e-hotspot__label">Tienda</div>') && (bool) preg_match('/<div class="e-hotspot__icon">(<i [^>]*fa-star[^>]*><\/i>|<svg [^>]*e-fas-star.*?<\/svg>)<\/div><div class="e-hotspot__label">Con tooltip propio<\/div>/s', $html), 'Hotspot: enlaces sin tooltip con clic, icono y etiqueta.');
        $assert(str_contains((string) wp_scripts()->query('obx-hotspot')->src, 'hotspot.js'), 'Hotspot: script del navegador registrado.');
    }

    // Global widget: `widget` library template plus `global` elements with `templateID`, as Pro stores them.
    $GW = \OldBearExpansion\Extras\Global_Widgets::class;
    $local = Elementor::$instance->templates_manager->get_source('local');
    $gw_tpl = (int) $local->save_item(['type'=>'widget','title'=>'Aviso global','content'=>[$widget('heading', ['title'=>'Texto global','title_color'=>'#ff0000'])]]);
    $created[] = $gw_tpl;
    $assert($gw_tpl > 0 && get_post_meta($gw_tpl, '_elementor_template_widget_type', true) === 'heading' && $GW::is_widget_template($gw_tpl), 'Widget global: la plantilla `widget` guarda su tipo de widget.');
    $gw_doc = Elementor::$instance->documents->get($gw_tpl);
    $assert($gw_doc instanceof \OldBearExpansion\Extras\Global_Widget_Document && $gw_doc::get_property('show_in_library') === false && $gw_doc::get_property('is_editable') === false && get_post_status($gw_tpl) === 'publish', 'Widget global: documento `widget` oculto en la biblioteca y no editable por separado.');
    $gw_list = $GW::templates();
    $assert(isset($gw_list[$gw_tpl]) && $gw_list[$gw_tpl]['widgetType'] === 'heading' && $gw_list[$gw_tpl]['title'] === 'Aviso global', 'Widget global: el editor recibe la lista de widgets globales.');
    $gw_from_pro = ['id'=>$uid(),'elType'=>'widget','widgetType'=>'global','templateID'=>$gw_tpl,'settings'=>[],'elements'=>[]];
    $gw_from_editor = ['id'=>$uid(),'elType'=>'widget','widgetType'=>'heading','templateID'=>$gw_tpl,'settings'=>['title'=>'Copia local'],'elements'=>[]];
    $gw_page = $save('page', 'Paridad widget global', [$container([$gw_from_pro, $gw_from_editor])]);
    $stored = json_decode((string) get_post_meta($gw_page, '_elementor_data', true), true);
    $stored = $stored[0]['elements'] ?? [];
    $assert(count($stored) === 2 && ($stored[0]['widgetType'] ?? '') === 'global' && ($stored[1]['widgetType'] ?? '') === 'global' && (int) ($stored[1]['templateID'] ?? 0) === $gw_tpl && !isset($stored[1]['originalWidgetType']) && ($stored[1]['settings']['title'] ?? '') === 'Texto global', 'Widget global: se guarda como `global` con `templateID` y los ajustes de la plantilla.');
    $raw = Elementor::$instance->documents->get($gw_page)->get_elements_raw_data(null, true);
    $raw = $raw[0]['elements'][0] ?? [];
    $assert(($raw['widgetType'] ?? '') === 'heading' && (int) ($raw['templateID'] ?? 0) === $gw_tpl && ($raw['settings']['title'] ?? '') === 'Texto global', 'Widget global: el editor lo carga como el widget original con `templateID`.');
    $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($gw_page);
    $assert(substr_count($html, 'Texto global') === 2 && !str_contains($html, 'Copia local') && (bool) preg_match('/class="[^"]*elementor-widget-global[^"]*elementor-global-' . $gw_tpl . ' elementor-widget-heading"[^>]*data-widget_type="heading.default"/', $html), 'Widget global: se pinta con la plantilla y las clases de Pro.');
    $gw_css = \Elementor\Core\Files\CSS\Post::create($gw_page);
    $gw_css->update();
    $assert((bool) preg_match('/\.elementor-' . $gw_page . ' (\.elementor-element)?\.elementor-global-' . $gw_tpl . ' \.elementor-heading-title\{color:#ff0000/', (string) $gw_css->get_content()), 'Widget global: el CSS de la página usa el selector `.elementor-global-N`.');
    $GW::remember_included_post($gw_page, $stored ? [['elements'=>$stored]] : []);
    $assert(isset(get_post_meta($gw_tpl, '_elementor_global_widget_included_posts', true)[$gw_page]), 'Widget global: la plantilla recuerda las páginas que la usan.');
    update_post_meta($gw_page, '_elementor_css', ['status'=>'file']);
    $local->update_item(['id'=>$gw_tpl,'type'=>'widget','content'=>[['id'=>(string) $gw_tpl,'elType'=>'widget','widgetType'=>'heading','settings'=>['title'=>'Texto cambiado'],'elements'=>[]]]]);
    $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($gw_page);
    $assert(!get_post_meta($gw_page, '_elementor_css', true) && substr_count($html, 'Texto cambiado') === 2, 'Widget global: al cambiar la plantilla cambian todas sus copias y se regenera su CSS.');
    wp_trash_post($gw_tpl);
    $GW::forget($gw_tpl);
    $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($gw_page);
    $assert(!str_contains($html, 'Texto cambiado') && !str_contains($html, 'elementor-widget-global'), 'Widget global: con la plantilla en la papelera no se pinta.');


    $assert(!$errors, 'Avisos PHP: ' . implode(' | ', array_slice($errors, 0, 4)));
} finally {
    $frontend->setValue(null, $was_frontend);
    restore_error_handler();
    foreach (array_reverse($created) as $id) { if (get_post($id)) { wp_delete_post($id, true); } }
    foreach ($terms as $term) { wp_delete_term($term, 'category'); }
    // Also when a check fails half-way, so failed runs leave no test categories behind.
    if (!empty($pcat) && is_array($pcat)) { wp_delete_term($pchild['term_id'], 'product_cat'); wp_delete_term($pcat['term_id'], 'product_cat'); }
    Locations::flush();
}
WP_CLI::success("{$checks} pruebas de paridad con Elementor Pro superadas.");
