<?php
/**
 * Plugin Name: Foxy Builder Pro
 * Plugin URI: https://www.foxywebsite.com/builder/
 * Description: Drag and drop page builder, pixel perfect design, mobile responsive editing, and more. Create stunningly beautiful web pages and hero sections for your WordPress website.
 * Version: 1.0.0
 * Author: Foxy Website LLC
 * Author URI: https://www.foxywebsite.com/builder/
 * Text Domain: foxy-builder-pro
 */

namespace FoxyBuilderPro;

if (!defined('ABSPATH'))
	exit;

define('FOXYBUILDERPRO_VERSION', '1.0.0');
define('FOXYBUILDERPRO_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FOXYBUILDERPRO_PLUGIN_PATHANDFILE', __FILE__);
define('FOXYBUILDERPRO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FOXYBUILDERPRO_TEXT_DOMAIN', 'foxy-builder-pro');

require_once FOXYBUILDERPRO_PLUGIN_PATH . '/main.php';

Main::instance();
