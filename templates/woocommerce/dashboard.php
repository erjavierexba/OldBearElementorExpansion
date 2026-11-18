<?php
/**
 * My Account dashboard replaced by an Elementor template (My Account widget, «Escritorio personalizado»).
 * WooCommerce loads this file instead of myaccount/dashboard.php while the widget renders.
 */
if (!defined('ABSPATH')) { exit; }
echo \OldBearExpansion\Widgets\Woo_My_Account::$dashboard; // phpcs:ignore WordPress.Security.EscapeOutput -- Elementor output.
