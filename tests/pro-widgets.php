<?php
/** Pro-named widgets: registration, panel visibility and rendering. Run with `wp eval-file`. */
if (!defined('WP_CLI') || !WP_CLI) { exit; }

use Elementor\Plugin as Elementor;
use OldBearExpansion\Widgets\Base;

$checks = 0;
$assert = static function ($ok, string $message) use (&$checks) {
    if (!$ok) { fwrite(STDERR, "Fallo: {$message}\n"); throw new RuntimeException($message); }
    $checks++;
};
$admin = get_users(['role'=>'administrator','number'=>1]);
wp_set_current_user($admin[0]->ID);

$types = Elementor::$instance->widgets_manager->get_widget_types();
$categories = Elementor::$instance->elements_manager->get_categories();
foreach (Base::registry() as $class) {
    if ($class::PRO !== '') {
        $widget = $types[$class::PRO] ?? null;
        $assert($widget instanceof $class, "Falta el widget " . $class::PRO . ".");
        // Pro's deprecated widgets keep rendering saved pages but are not offered in the panel.
        $deprecated = in_array($class::PRO, ['wc-products','wc-single-elements'], true);
        $assert($widget->show_in_panel() !== $deprecated, $class::PRO . ($deprecated ? " no debe aparecer en el panel (obsoleto en Pro)." : " debe aparecer en el panel."));
        $assert(isset($categories[$widget->get_categories()[0]]), $class::PRO . " usa una categoría inexistente.");
    }
    if ($class::NAME !== '') {
        $legacy = $types[$class::NAME] ?? null;
        $assert($legacy instanceof $class, "El nombre antiguo " . $class::NAME . " debe seguir registrado.");
        $assert($class::PRO === '' || !$legacy->show_in_panel(), $class::NAME . " no debe duplicarse en el panel.");
    }
}

// The editor panel loads every widget's controls through the get_widgets_config AJAX action;
// a misplaced control makes Elementor wp_die() there and the panel spins forever.
$die = static function () { return static function ($message) { throw new RuntimeException(is_string($message) ? $message : 'wp_die'); }; };
add_filter('wp_die_handler', $die, PHP_INT_MAX);
add_filter('wp_die_ajax_handler', $die, PHP_INT_MAX);
// WP-CLI counts as frontend, where Elementor skips that check: run it as the editor does.
$frontend = new ReflectionProperty(\Elementor\Core\Frontend\Performance::class, 'is_frontend');
$frontend->setAccessible(true);
$was_frontend = $frontend->getValue();
$frontend->setValue(null, false);
foreach (Base::registry() as $class) {
    foreach ($class::names() as $name) {
        $widget = $types[$name] ?? null;
        if (!$widget) { continue; }
        try { $config = $widget->get_stack(false); } catch (RuntimeException $e) { $assert(false, "{$name}: " . $e->getMessage()); }
        $assert(!empty($config['controls']), "{$name} no expone controles al editor.");
    }
}
$frontend->setValue(null, $was_frontend);
remove_filter('wp_die_handler', $die, PHP_INT_MAX);
remove_filter('wp_die_ajax_handler', $die, PHP_INT_MAX);

// Elementor no longer offers a locked promotion for widgets we provide.
$raw = get_option('_transient_elementor_remote_info_api_data_' . ELEMENTOR_VERSION);
if (is_array($raw) && !empty($raw['pro_widgets'])) {
    $filtered = \OldBearExpansion\ThemeBuilder\Module::drop_promotions($raw);
    $left = array_column($filtered['pro_widgets'], 'name');
    $assert(!in_array('countdown', $left, true) && !in_array('theme-post-title', $left, true), 'Las promociones de widgets propios deben desaparecer.');
    $assert(count($left) < count($raw['pro_widgets']), 'Solo deben quitarse las promociones de widgets propios.');
}

// Every Pro-named widget renders its defaults inside a real post without PHP notices.
$post = wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>'obx-pro-widgets <b>x</b>','post_content'=>'<h2>Uno</h2><p>Texto</p><h3>Dos</h3>']);
$page = wp_insert_post(['post_type'=>'page','post_status'=>'draft','post_title'=>'obx-pro-widgets page']);
$uid = static fn() => substr(str_replace('-', '', wp_generate_uuid4()), 0, 7);
$errors = [];
set_error_handler(static function ($no, $message, $file, $line) use (&$errors) {
    if (str_contains($file, 'oldbear-elementor-expansion')) { $errors[] = "{$message} ({$file}:{$line})"; }
    return false;
});
try {
    global $wp_query, $wp_the_query;
    $wp_the_query = $wp_query = new WP_Query(['p'=>$post]);
    $wp_query->the_post();
    $elements = [];
    foreach (Base::registry() as $class) {
        if ($class::PRO !== '' && $class::available()) { $elements[] = ['id'=>$uid(),'elType'=>'widget','widgetType'=>$class::PRO,'settings'=>[],'elements'=>[]]; }
    }
    $document = Elementor::$instance->documents->get($page);
    $document->set_is_built_with_elementor(true);
    $document->save(['elements'=>[['id'=>$uid(),'elType'=>'container','isInner'=>false,'settings'=>[],'elements'=>$elements]]]);
    $html = Elementor::$instance->frontend->get_builder_content_for_display($page);
    $missing = [];
    foreach (Base::registry() as $class) {
        // Without a logo, featured image, menu, images or chosen template these print nothing, as they should.
        $configured = ['theme-site-logo','theme-post-featured-image','nav-menu','template','gallery','media-carousel','loop-grid','loop-carousel','taxonomy-filter','woocommerce-product-images','woocommerce-product-rating','woocommerce-product-data-tabs','woocommerce-product-related','woocommerce-product-upsell','woocommerce-archive-products'];
        $needs_woo_context = is_subclass_of($class, \OldBearExpansion\Widgets\Woo_Element::class);
        if ($class::PRO !== '' && $class::available() && !$needs_woo_context && !in_array($class::PRO, $configured, true)
            && !preg_match('/elementor-widget-' . preg_quote($class::PRO, '/') . '[\s"]/', $html)) { $missing[] = $class::PRO; }
    }
    $assert(!$missing, 'No se han renderizado: ' . implode(', ', $missing));
    foreach (['obx-countdown','obx-price-table','obx-flip','obx-cta','obx-quote','obx-share','obx-headline','obx-toc','obx-progress','obx-code','obx-breadcrumbs','obx-postnav','obx-posts','obx-login'] as $class) {
        $assert(str_contains($html, 'class="' . $class) || str_contains($html, $class . ' '), "Falta el marcado {$class}.");
    }
    $assert(str_contains($html, 'obx-pro-widgets &lt;b&gt;x&lt;/b&gt;') && !str_contains($html, 'obx-pro-widgets <b>x</b>'), 'Los títulos deben escaparse.');
    $assert(!$errors, 'Avisos PHP al renderizar: ' . implode(' | ', array_slice($errors, 0, 3)));

    // A template saved with the old name keeps rendering through the same class.
    $document->save(['elements'=>[['id'=>$uid(),'elType'=>'container','isInner'=>false,'settings'=>[],'elements'=>[['id'=>$uid(),'elType'=>'widget','widgetType'=>'obx-post-title','settings'=>['tag'=>'h4'],'elements'=>[]]]]]]);
    $html = Elementor::$instance->frontend->get_builder_content_for_display($page);
    $assert((bool) preg_match('~<h4 class="elementor-heading-title [^"]*obx-post-title~', $html), 'El nombre antiguo debe seguir renderizando.');

    // Template widget refuses to include itself.
    $document->save(['elements'=>[['id'=>$uid(),'elType'=>'container','isInner'=>false,'settings'=>[],'elements'=>[['id'=>$uid(),'elType'=>'widget','widgetType'=>'template','settings'=>['template_id'=>(string) $page],'elements'=>[]]]]]]);
    $assert(is_string(Elementor::$instance->frontend->get_builder_content_for_display($page)), 'El widget Plantilla no debe fallar con IDs no válidos.');

    // OldBear's mark: badges in the widget panel, help links, admin headings and generator tag.
    $names = \OldBearExpansion\Admin\Branding::widget_names();
    $assert(in_array('posts', $names, true) && in_array('woocommerce-cart', $names, true) && in_array('nested-carousel', $names, true) && !in_array('heading', $names, true), 'Branding: insignia en los widgets de la expansión y solo en ellos.');
    update_option(\OldBearExpansion\ThemeBuilder\Upgrade::MARKUP_OPTION, '0.0.1');
    update_post_meta(1, '_elementor_element_cache', ['timeout'=>time() + 3600,'value'=>['content'=>'viejo']]);
    \OldBearExpansion\ThemeBuilder\Upgrade::maybe_flush();
    $assert(get_option(\OldBearExpansion\ThemeBuilder\Upgrade::MARKUP_OPTION) === \OldBearExpansion\VERSION && get_post_meta(1, '_elementor_element_cache', true) === '', 'Al actualizar el plugin se vacía la caché de Elementor (HTML y CSS de la versión anterior).');
    $assert(str_starts_with((string) ($types['posts']->get_config()['help_url'] ?? ''), \OldBearExpansion\Admin\Branding::URL) && str_starts_with((string) ($types['nested-carousel']->get_config()['help_url'] ?? ''), \OldBearExpansion\Admin\Branding::URL), 'Branding: «¿Necesitas ayuda?» lleva a la documentación de la expansión.');
    $heading = \OldBearExpansion\Admin\Branding::heading('Theme Builder');
    $assert(str_contains($heading, 'oldbear-32.png') && str_contains($heading, 'oldbear-64.png 2x') && str_contains($heading, 'OldBear Expansion'), 'Branding: logo y nombre en las pantallas de administración.');
    $assert(is_readable(\OldBearExpansion\DIR . '/assets/img/oldbear-32.png') && is_readable(\OldBearExpansion\DIR . '/assets/img/oldbear-128.png'), 'Branding: imágenes del logo.');
    ob_start(); \OldBearExpansion\Admin\Branding::generator(); $meta = (string) ob_get_clean();
    $assert($meta === '<meta name="generator" content="OldBear Expansion for Elementor ' . \OldBearExpansion\VERSION . '">' . "\n", 'Branding: etiqueta generator.');
} finally {
    restore_error_handler();
    wp_reset_postdata();
    wp_delete_post($post, true);
    wp_delete_post($page, true);
}
WP_CLI::success("{$checks} pruebas de widgets superadas.");
