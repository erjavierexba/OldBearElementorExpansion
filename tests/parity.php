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
