<?php

if (!defined('DS'))
	define('DS', DIRECTORY_SEPARATOR);
if (!defined('_PKMGMT'))
	define('_PKMGMT', 1);
if (!defined('PKMGMT_VERSION'))
	define('PKMGMT_VERSION', '5.0.0');
if (!defined('PKMGMT_REQUIRED_WP_VERSION'))
	define('PKMGMT_REQUIRED_WP_VERSION', '6.3');
if (!defined('PKMGMT_TEXT_DOMAIN'))
	define('PKMGMT_TEXT_DOMAIN', 'parking-management');
if (!defined('PKMGMT_PLUGIN_BASENAME'))
	define('PKMGMT_PLUGIN_BASENAME', plugin_basename(PKMGMT_PLUGIN));
if (!defined('PKMGMT_PLUGIN_NAME'))
	define('PKMGMT_PLUGIN_NAME', trim(dirname(PKMGMT_PLUGIN_BASENAME), DS));
if (!defined('PKMGMT_PLUGIN_DIR'))
	define('PKMGMT_PLUGIN_DIR', untrailingslashit(dirname(PKMGMT_PLUGIN)));
if (!defined('PKMGMT_PLUGIN_MODULES_DIR'))
	define('PKMGMT_PLUGIN_MODULES_DIR', PKMGMT_PLUGIN_DIR . DS . 'modules');

if (!defined('PKMGMT_LOAD_JS'))
	define('PKMGMT_LOAD_JS', true);
if (!defined('PKMGMT_LOAD_CSS'))
	define('PKMGMT_LOAD_CSS', true);
if (!defined('PKMGMT_USE_PIPE'))
	define('PKMGMT_USE_PIPE', true);

if (!defined('PKMGMT_ADMIN_READ_CAPABILITY'))
	define('PKMGMT_ADMIN_READ_CAPABILITY', 'edit_posts');
if (!defined('PKMGMT_ADMIN_READ_WRITE_CAPABILITY'))
	define('PKMGMT_ADMIN_READ_WRITE_CAPABILITY', 'publish_pages');
if (!defined('PKMGMT_ADMIN_MANAGE_INTEGRATION'))
	define('PKMGMT_ADMIN_MANAGE_INTEGRATION', 'manage_options');
if (!defined('PKMGMT_VERIFY_NONCE'))
	define('PKMGMT_VERIFY_NONCE', true);

if (!defined('PKMGMT_PLUGIN_URL'))
	define('PKMGMT_PLUGIN_URL', untrailingslashit(plugins_url('', PKMGMT_PLUGIN)));

if (!defined('PKMGMT_PLUGIN_INCLUDES_DIR'))
	define('PKMGMT_PLUGIN_INCLUDES_DIR', PKMGMT_PLUGIN_DIR . DS . 'includes');
if (!defined('PKMGMT_LANGUAGES_DIR'))
	define('PKMGMT_LANGUAGES_DIR', PKMGMT_PLUGIN_DIR . DS . 'languages');
if (!defined('PKMGMT_PLUGIN_TEMPLATES'))
	define('PKMGMT_PLUGIN_TEMPLATES', PKMGMT_PLUGIN_DIR . DS . "templates");

if (getenv('PKMGMT_DEBUG') == "true")
	define('PKMGMT_DEBUG', true);
else
	define('PKMGMT_DEBUG', false);
