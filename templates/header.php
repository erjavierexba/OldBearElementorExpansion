<?php
/** Theme Builder header: replaces the theme's header.php. */
use OldBearExpansion\ThemeBuilder\Locations;
if (!defined('ABSPATH')) { exit; }
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="obx-skip-link screen-reader-text" href="<?php echo esc_attr(apply_filters('obx/skip_link_target', '#content')); ?>">Saltar al contenido</a>
<header id="obx-header" class="obx-site-header"><?php Locations::render('header'); ?></header>
