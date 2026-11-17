<?php
/**
 * Theme compatibility. Classic themes such as Hello Elementor or Astra call
 * elementor_theme_do_location() when it exists and fall back to their own markup otherwise.
 */
if (!defined('ABSPATH')) { exit; }

if (!function_exists('elementor_theme_do_location')) {
    function elementor_theme_do_location($location) {
        return is_string($location) && \OldBearExpansion\ThemeBuilder\Locations::do_location($location);
    }
}
