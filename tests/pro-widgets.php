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
        $assert($widget->show_in_panel(), $class::PRO . " debe aparecer en el panel.");
        $assert(isset($categories[$widget->get_categories()[0]]), $class::PRO . " usa una categoría inexistente.");
    }
    if ($class::NAME !== '') {
        $legacy = $types[$class::NAME] ?? null;
        $assert($legacy instanceof $class, "El nombre antiguo " . $class::NAME . " debe seguir registrado.");
        $assert($class::PRO === '' || !$legacy->show_in_panel(), $class::NAME . " no debe duplicarse en el panel.");
    }
}

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
        if ($class::PRO !== '') { $elements[] = ['id'=>$uid(),'elType'=>'widget','widgetType'=>$class::PRO,'settings'=>[],'elements'=>[]]; }
    }
    $document = Elementor::$instance->documents->get($page);
    $document->set_is_built_with_elementor(true);
    $document->save(['elements'=>[['id'=>$uid(),'elType'=>'container','isInner'=>false,'settings'=>[],'elements'=>$elements]]]);
    $html = Elementor::$instance->frontend->get_builder_content_for_display($page);
    $missing = [];
    foreach (Base::registry() as $class) {
        // Without a logo, featured image, menu or chosen template these print nothing, as they should.
        if ($class::PRO !== '' && !in_array($class::PRO, ['theme-site-logo','theme-post-featured-image','nav-menu','template'], true)
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
    $assert(str_contains($html, '<h4 class="obx-post-title">'), 'El nombre antiguo debe seguir renderizando.');

    // Template widget refuses to include itself.
    $document->save(['elements'=>[['id'=>$uid(),'elType'=>'container','isInner'=>false,'settings'=>[],'elements'=>[['id'=>$uid(),'elType'=>'widget','widgetType'=>'template','settings'=>['template_id'=>(string) $page],'elements'=>[]]]]]]);
    $assert(is_string(Elementor::$instance->frontend->get_builder_content_for_display($page)), 'El widget Plantilla no debe fallar con IDs no válidos.');
} finally {
    restore_error_handler();
    wp_reset_postdata();
    wp_delete_post($post, true);
    wp_delete_post($page, true);
}
WP_CLI::success("{$checks} pruebas de widgets superadas.");
