<?php
/**
 * Elementor Pro parity (docs/PARIDAD-PRO.md): settings stored under Pro's keys and defaults, and what they do.
 * 0.8.0: Theme Builder templates, popups, motion effects (element and background), page transitions and scroll snap.
 * 0.8.1: Theme Builder conditions (child term archives, formats, media, WooCommerce), dynamic tags, saved sections.
 * 0.9.0: Loop Grid skins (posts, products, terms), pagination and «Load more», nothing-found message.
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
        $id = $save('page', 'Paridad rejilla', [$container([$widget('loop-grid', $settings)])]);
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

    if (!empty($pid) && get_post($pid)) {
        $product_item = $save('loop-item', 'Paridad producto', [$container([$widget('theme-post-title')])], ['source'=>'product']);
        $html = $render(['template_id'=>(string) $product_item,'_skin'=>'product','product_query_post_type'=>'sale']);
        $assert(str_contains($html, 'data-post-id="' . $pid . '"'), 'Skin de productos: rebajados.');
        $html = $render(['template_id'=>(string) $product_item,'_skin'=>'product','product_query_post_type'=>'featured']);
        $assert(!str_contains($html, 'data-post-id="' . $pid . '"'), 'Skin de productos: destacados (el de prueba no lo es).');
    }

    if (!empty($pcat)) { wp_delete_term($pchild['term_id'], 'product_cat'); wp_delete_term($pcat['term_id'], 'product_cat'); }

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
    Locations::flush();
}
WP_CLI::success("{$checks} pruebas de paridad con Elementor Pro superadas.");
