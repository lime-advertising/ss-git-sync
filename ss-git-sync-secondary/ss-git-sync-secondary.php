<?php
/**
 * Plugin Name: SS Git Sync (Secondary)
 * Description: Pulls Smart Slider 3 projects from Git and imports into this site.
 * Version: 0.2.0
 * Author: Lime Advertising
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SSGSS_FILE', __FILE__);
define('SSGSS_PATH', plugin_dir_path(__FILE__));
define('SSGSS_URL', plugin_dir_url(__FILE__));
define('SSGSS_VER', '0.2.0');

require_once SSGSS_PATH . 'includes/Helpers.php';
require_once SSGSS_PATH . 'includes/Logger.php';
require_once SSGSS_PATH . 'includes/Git.php';
require_once SSGSS_PATH . 'src/Admin.php';
require_once SSGSS_PATH . 'src/Importer.php';
require_once SSGSS_PATH . 'src/Cron.php';
require_once SSGSS_PATH . 'src/Plugin.php';

add_action('plugins_loaded', [\SSGSS\Plugin::class, 'init']);
