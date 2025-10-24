<?php
/**
 * Plugin Name: SS Git Sync (Master)
 * Description: Exports Smart Slider 3 projects to Git for downstream sync.
 * Version: 0.2.0
 * Author: Lime Advertising
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SSGSM_FILE', __FILE__);
define('SSGSM_PATH', plugin_dir_path(__FILE__));
define('SSGSM_URL', plugin_dir_url(__FILE__));
define('SSGSM_VER', '0.2.0');
require_once SSGSM_PATH . 'includes/Helpers.php';
require_once SSGSM_PATH . 'includes/Logger.php';
require_once SSGSM_PATH . 'includes/Git.php';
require_once SSGSM_PATH . 'src/Admin.php';
require_once SSGSM_PATH . 'src/Exporter.php';
require_once SSGSM_PATH . 'src/Plugin.php';

add_action('plugins_loaded', [\SSGSM\Plugin::class, 'init']);
