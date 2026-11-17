<?php
/** Theme Builder single / archive / 404 template. */
use OldBearExpansion\ThemeBuilder\Locations;
if (!defined('ABSPATH')) { exit; }
get_header();
$obx_location = Locations::current_main();
?>
<main id="content" class="obx-main obx-main-<?php echo esc_attr($obx_location); ?>">
<?php
if ($obx_location === 'single') {
    while (have_posts()) { the_post(); Locations::render('single'); }
} else {
    Locations::render($obx_location);
}
?>
</main>
<?php
get_footer();
