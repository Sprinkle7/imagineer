<?php
/**
 * Plugin Name: Imagineer - Image Converter
 * Plugin URI: https://wordpress.org/plugins/imagineer
 * Description: Convert and optimize images between PNG, JPG, WEBP, TIFF, BMP, and GIF formats. Features bulk processing, image resizing, quality control, and more - all completely free!
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Allauddin Yousafxai
 * Author URI: https://www.buymeacoffee.com/adusafxai
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: imagineer
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('IC_VERSION', '1.0.1764766641');
define('IC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check if Pro version is active
define('IC_IS_PRO', defined('IC_PRO_VERSION'));

// Load composer autoloader for WebP Convert library
if (file_exists(IC_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once IC_PLUGIN_DIR . 'vendor/autoload.php';
}

// Include core files
require_once IC_PLUGIN_DIR . 'includes/class-imagineer-core.php';
require_once IC_PLUGIN_DIR . 'includes/class-imagineer-webp.php';
require_once IC_PLUGIN_DIR . 'includes/class-imagineer-optimizer.php';
require_once IC_PLUGIN_DIR . 'includes/class-imagineer-ajax.php';
require_once IC_PLUGIN_DIR . 'includes/class-imagineer-admin.php';
require_once IC_PLUGIN_DIR . 'includes/class-imagineer-shortcodes.php';
require_once IC_PLUGIN_DIR . 'includes/class-imagineer-welcome.php';
require_once IC_PLUGIN_DIR . 'includes/class-imagineer-presets.php';
require_once IC_PLUGIN_DIR . 'includes/class-imagineer-messages.php';
require_once IC_PLUGIN_DIR . 'includes/class-imagineer-auto-optimize.php';
require_once IC_PLUGIN_DIR . 'includes/class-imagineer-backup.php';
require_once IC_PLUGIN_DIR . 'includes/class-imagineer-license.php';
require_once IC_PLUGIN_DIR . 'includes/class-imagineer-tracking.php';
require_once IC_PLUGIN_DIR . 'includes/class-imagineer-security.php';
require_once IC_PLUGIN_DIR . 'includes/class-imagineer-library-installer.php';

// Initialize the plugin
function ic_init() {
    $core = new Imagineer_Core();
    $admin = new Imagineer_Admin();
    $ajax = new Imagineer_Ajax();
    $auto_optimize = new Imagineer_Auto_Optimize();
    $license = new Imagineer_License();
    $tracking = new Imagineer_Tracking();
    $security = new Imagineer_Security(); // Initialize security first
    
    // Schedule library installation if needed (non-blocking)
    add_action('ic_install_library', 'ic_install_library_background');
    
    $instances = array(
        'core' => $core,
        'admin' => $admin,
        'ajax' => $ajax,
        'auto_optimize' => $auto_optimize,
        'license' => $license,
        'tracking' => $tracking,
        'security' => $security
    );
    
    return $instances;
}

/**
 * Background library installation (non-blocking)
 */
function ic_install_library_background() {
    $installer = new Imagineer_Library_Installer();
    if (!$installer->is_installed()) {
        $result = $installer->install();
        // Log result (won't block activation)
        if ($result['success']) {
            error_log('Imagineer: WebP Convert library installed successfully.');
        } else {
            error_log('Imagineer: Library installation failed: ' . $result['message']);
        }
    }
}

// Hook into WordPress
add_action('plugins_loaded', 'ic_init');

// Activation hook
register_activation_hook(__FILE__, 'ic_activate');
function ic_activate() {
    global $wpdb;
    
    // Set default options
    add_option('ic_max_file_size', 2 * 1024 * 1024); // 2MB for free version
    add_option('ic_default_quality', 80);
    add_option('ic_is_pro', false);
    
    // Create upload directory if it doesn't exist
    $upload_dir = wp_upload_dir();
    $ic_dir = $upload_dir['basedir'] . '/imagineer';
    if (!file_exists($ic_dir)) {
        wp_mkdir_p($ic_dir);
    }
    
    // Create conversion history table
    $table_name = $wpdb->prefix . 'ic_conversion_history';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        filename varchar(255) NOT NULL,
        original_format varchar(10) NOT NULL,
        target_format varchar(10) NOT NULL,
        original_size bigint(20) NOT NULL,
        converted_size bigint(20) NOT NULL,
        converted_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY converted_at (converted_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Set redirect flag for welcome screen
    set_transient('ic_activation_redirect', true, 30);
    
    // Attempt to install WebP Convert library automatically (non-blocking)
    // This runs in background - won't block activation if it fails
    $installer = new Imagineer_Library_Installer();
    if (!$installer->is_installed()) {
        // Schedule installation attempt (non-blocking)
        wp_schedule_single_event(time() + 5, 'ic_install_library');
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'ic_deactivate');
function ic_deactivate() {
    // Clean up if needed
}

