<?php
/** Theme Builder footer: replaces the theme's footer.php. */
use OldBearExpansion\ThemeBuilder\Locations;
if (!defined('ABSPATH')) { exit; }
?>
<footer id="obx-footer" class="obx-site-footer"><?php Locations::render('footer'); ?></footer>
<?php wp_footer(); ?>
</body>
</html>
