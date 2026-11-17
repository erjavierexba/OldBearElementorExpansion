<?php
/** Theme Builder single / archive / 404 template whose «page_template» is the Elementor canvas: no theme header or footer. */
use OldBearExpansion\ThemeBuilder\Locations;
if (!defined('ABSPATH')) { exit; }
$obx_location = Locations::current_main();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class('elementor-template-canvas'); ?>>
<?php
wp_body_open();
if ($obx_location === 'single') {
    while (have_posts()) { the_post(); Locations::render('single'); }
} else {
    Locations::render($obx_location);
}
wp_footer();
?>
</body>
</html>
