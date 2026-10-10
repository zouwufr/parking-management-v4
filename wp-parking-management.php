<?php
/**
 * Plugin Name: Parking management
 * Description: Plugin to manage park booking
 * Author: David ALEXANDRE
 * License: GPL2 license
 * URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 5.0.0
 * Requires at least: 6.3
 * Requires PHP: 8.1
 */

if (!defined('PKMGMT_PLUGIN'))
	define('PKMGMT_PLUGIN', __FILE__);

require_once __DIR__ . '/define.php';

require_once(PKMGMT_PLUGIN_DIR . DS . "load.php");
