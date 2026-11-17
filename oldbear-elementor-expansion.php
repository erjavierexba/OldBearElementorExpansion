<?php
/**
 * Plugin Name: OldBear Expansion for Elementor
 * Plugin URI: https://github.com/erjavierexba/OldBearElementorExpansion
 * Description: Theme Builder con condiciones de visualización y widgets de tema para Elementor Free.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: elementor
 * Author: OldBearStudios
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: oldbear-expansion
 * Elementor tested up to: 3.34.2
 */
namespace OldBearExpansion;

if (!defined('ABSPATH')) { exit; }

const VERSION = '0.1.0';
const FILE = __FILE__;
const DIR = __DIR__;

require_once DIR . '/includes/class-plugin.php';
add_action('plugins_loaded', [Plugin::class, 'boot'], 20);
