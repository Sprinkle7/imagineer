<?php
/**
 * Security and Anti-Tampering System
 * This file contains obfuscated license checks and integrity verification
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagineer_Security {
    
    // Hardcoded Google Analytics ID (change this to your actual GA ID)
    // IMPORTANT: Replace 'G-XXXXXXXXXX' with your actual Google Analytics Measurement ID
    private static $GA_ID = 'G-XXXXXXXXXX'; // e.g., 'G-ABC123XYZ'
    
    private $license;
    
    public function __construct() {
        $this->license = new Imagineer_License();
        
        // Multiple license check points
        add_action('init', array($this, 'check_license_on_init'), 1);
        add_action('admin_init', array($this, 'check_license_on_admin'), 1);
        add_filter('plugin_action_links_' . IC_PLUGIN_BASENAME, array($this, 'add_security_check'), 10, 2);
        
        // Integrity check
        add_action('wp_loaded', array($this, 'verify_integrity'), 5);
    }
    
    /**
     * Check license on WordPress init (early check)
     */
    public function check_license_on_init() {
        // LICENSE CHECKING DISABLED - Do nothing
        return;
        
        /* ORIGINAL CODE - COMMENTED OUT
        // Silent check - only log if license key is set but invalid
        $license_info = $this->license->get_license_info();
        $has_license_key = !empty($license_info['key']);
        
        // Only log if they have a license key but it's invalid/disabled
        if ($has_license_key && !$this->license->is_license_valid()) {
            // Log unauthorized usage
            $this->log_unauthorized_usage();
        }
        */
    }
    
    /**
     * Check license on admin init
     */
    public function check_license_on_admin() {
        // LICENSE CHECKING DISABLED - Do nothing
        return;
        
        /* ORIGINAL CODE - COMMENTED OUT
        // Only check on Imagineer admin pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'imagineer') === false) {
            return;
        }
        
        // Only show warning if license key is set but invalid/disabled
        $license_info = $this->license->get_license_info();
        $has_license_key = !empty($license_info['key']);
        
        // Only show warning if they have a license key but it's invalid/disabled
        if ($has_license_key && !$this->license->is_license_valid()) {
            add_action('admin_notices', array($this, 'show_license_warning'));
        }
        */
    }
    
    /**
     * Show license warning
     */
    public function show_license_warning() {
        // Only show on Imagineer pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'imagineer') === false) {
            return;
        }
        
        $license_info = $this->license->get_license_info();
        if ($license_info['disabled']) {
            echo '<div class="notice notice-error"><p><strong>Imagineer:</strong> ' . __('Your license has been disabled. Please contact support.', 'imagineer') . '</p></div>';
        } elseif ($license_info['status'] !== 'valid' && !empty($license_info['key'])) {
            echo '<div class="notice notice-warning"><p><strong>Imagineer:</strong> ' . __('Your license key is invalid or expired. Please check your license key in Settings.', 'imagineer') . '</p></div>';
        }
    }
    
    /**
     * Verify code integrity
     */
    public function verify_integrity() {
        // Check if critical files have been modified
        $plugin_file = IC_PLUGIN_DIR . 'imagineer.php';
        $expected_hash = $this->get_file_hash($plugin_file);
        $stored_hash = get_option('ic_file_hash_' . md5($plugin_file));
        
        if ($stored_hash && $expected_hash !== $stored_hash) {
            // File has been modified - log and potentially disable
            $this->log_tampering_attempt();
        } elseif (!$stored_hash) {
            // First run - store hash
            update_option('ic_file_hash_' . md5($plugin_file), $expected_hash);
        }
    }
    
    /**
     * Get file hash
     */
    private function get_file_hash($file) {
        if (file_exists($file)) {
            return md5_file($file);
        }
        return '';
    }
    
    /**
     * Log unauthorized usage
     */
    private function log_unauthorized_usage() {
        $site_url = home_url();
        $data = array(
            'action' => 'unauthorized_usage',
            'site_url' => $site_url,
            'timestamp' => current_time('mysql'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        
        // Send to tracking server
        $this->send_security_log($data);
    }
    
    /**
     * Log tampering attempt
     */
    private function log_tampering_attempt() {
        $site_url = home_url();
        $data = array(
            'action' => 'tampering_detected',
            'site_url' => $site_url,
            'timestamp' => current_time('mysql'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        
        // Send to tracking server
        $this->send_security_log($data);
    }
    
    /**
     * Send security log to server
     */
    private function send_security_log($data) {
        $license = new Imagineer_License();
        $api_url = 'https://apis.khairaltawn.com/'; // Change to your server
        
        wp_remote_post($api_url, array(
            'body' => array_merge($data, array('plugin' => 'imagineer')),
            'timeout' => 5,
            'blocking' => false // Don't block execution
        ));
    }
    
    /**
     * Get Google Analytics ID (hardcoded)
     */
    public static function get_ga_id() {
        return self::$GA_ID;
    }
    
    /**
     * Check if license is valid (obfuscated method name)
     * DISABLED: Always returns true
     */
    public function v() {
        // LICENSE CHECKING DISABLED - Always return true
        return true;
        // return $this->license->is_license_valid();
    }
    
    /**
     * Add security check to plugin links
     */
    public function add_security_check($links, $file) {
        // Add hidden integrity check
        return $links;
    }
}
