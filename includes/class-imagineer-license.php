<?php
/**
 * License and Usage Tracking System
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagineer_License {
    
    // IMPORTANT: Change this to your actual license server URL
    private $api_url = 'https://apis.khairaltawn.com/index.php'; // License server endpoint
    // IMPORTANT: If your license server requires an API key, set it here
    private $api_key = ''; // Leave empty if no API key required
    private $plugin_slug = 'imagineer';
    private $plugin_version = IC_VERSION;
    
    public function __construct() {
        // Track plugin usage
        add_action('admin_init', array($this, 'track_usage'));
        
        // Validate license periodically
        add_action('wp_loaded', array($this, 'validate_license_periodically'));
    }
    
    /**
     * Track plugin usage
     * DISABLED: Usage tracking is currently disabled
     */
    public function track_usage() {
        // USAGE TRACKING DISABLED - Do nothing
        return;
        
        /* ORIGINAL CODE - COMMENTED OUT
        // Only track once per day
        $last_tracked = get_transient('ic_last_tracked');
        if ($last_tracked) {
            return;
        }
        
        $site_url = home_url();
        $site_name = get_bloginfo('name');
        $admin_email = get_option('admin_email');
        $wp_version = get_bloginfo('version');
        $php_version = PHP_VERSION;
        
        $data = array(
            'action' => 'track_usage',
            'plugin' => $this->plugin_slug,
            'version' => $this->plugin_version,
            'site_url' => $site_url,
            'site_name' => $site_name,
            'admin_email' => $admin_email,
            'wp_version' => $wp_version,
            'php_version' => $php_version,
            'timestamp' => current_time('mysql')
        );
        
        // Send to tracking server
        $this->send_to_server($data);
        
        // Set transient to track once per day
        set_transient('ic_last_tracked', true, DAY_IN_SECONDS);
        */
    }
    
    /**
     * Validate license with remote server
     * DISABLED: License validation is currently disabled - always returns valid
     */
    public function validate_license($license_key = null) {
        // LICENSE VALIDATION DISABLED - Always return valid
        update_option('ic_license_status', 'valid');
        update_option('ic_license_type', 'pro');
        update_option('ic_license_disabled', false);
        
        return array(
            'valid' => true,
            'message' => __('License is valid.', 'imagineer'),
            'expires' => '',
            'type' => 'pro',
            'disabled' => false
        );
        
        /* ORIGINAL CODE - COMMENTED OUT
        if (!$license_key) {
            $license_key = get_option('ic_license_key');
        }
        
        if (!$license_key) {
            return array(
                'valid' => false,
                'message' => __('No license key provided.', 'imagineer')
            );
        }
        
        $site_url = home_url();
        $data = array(
            'action' => 'validate_license',
            'license_key' => $license_key,
            'site_url' => $site_url,
            'plugin' => $this->plugin_slug,
            'version' => $this->plugin_version
        );
        
        $response = $this->send_to_server($data);
        
        if (is_wp_error($response)) {
            return array(
                'valid' => false,
                'message' => __('Failed to connect to license server.', 'imagineer')
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (isset($result['valid']) && $result['valid']) {
            // License is valid
            update_option('ic_license_status', 'valid');
            update_option('ic_license_expires', isset($result['expires']) ? $result['expires'] : '');
            update_option('ic_license_type', isset($result['type']) ? $result['type'] : 'free');
            
            // Store remote disable flag
            if (isset($result['disabled'])) {
                update_option('ic_license_disabled', $result['disabled']);
            }
            
            return array(
                'valid' => true,
                'message' => __('License is valid.', 'imagineer'),
                'expires' => isset($result['expires']) ? $result['expires'] : '',
                'type' => isset($result['type']) ? $result['type'] : 'free',
                'disabled' => isset($result['disabled']) ? $result['disabled'] : false
            );
        } else {
            // License is invalid
            update_option('ic_license_status', 'invalid');
            return array(
                'valid' => false,
                'message' => isset($result['message']) ? $result['message'] : __('License is invalid.', 'imagineer')
            );
        }
        */
    }
    
    /**
     * Check if license is valid and not disabled
     * DISABLED: License validation is currently disabled - all features are available
     */
    public function is_license_valid() {
        // LICENSE CHECKING DISABLED - Always return true to allow all features
        return true;
        
        /* ORIGINAL CODE - COMMENTED OUT
        $license_key = get_option('ic_license_key', '');
        
        // If no license key is set, allow free usage (return true)
        if (empty($license_key)) {
            return true;
        }
        
        $status = get_option('ic_license_status', 'invalid');
        $disabled = get_option('ic_license_disabled', false);
        
        return ($status === 'valid' && !$disabled);
        */
    }
    
    /**
     * Check if feature is allowed
     */
    public function is_feature_allowed($feature) {
        if (!$this->is_license_valid()) {
            return false;
        }
        
        $license_type = get_option('ic_license_type', 'free');
        
        // Define feature restrictions based on license type
        $free_features = array('basic_convert', 'download');
        $pro_features = array('bulk_convert', 'auto_optimize', 'backup_restore', 'advanced_settings');
        
        if ($license_type === 'free') {
            return in_array($feature, $free_features);
        } else {
            return true; // Pro license has all features
        }
    }
    
    /**
     * Validate license periodically (once per day)
     */
    public function validate_license_periodically() {
        $last_validation = get_transient('ic_last_validation');
        if ($last_validation) {
            return;
        }
        
        $license_key = get_option('ic_license_key');
        if ($license_key) {
            $this->validate_license($license_key);
        }
        
        // Validate once per day
        set_transient('ic_last_validation', true, DAY_IN_SECONDS);
    }
    
    /**
     * Send data to remote server
     */
    private function send_to_server($data) {
        // API CALLS DISABLED - Return empty response
        return new WP_Error('disabled', 'License server API calls are disabled');
        
        /* ORIGINAL CODE - COMMENTED OUT
        // Add API key if configured
        if (!empty($this->api_key)) {
            $data['api_key'] = $this->api_key;
        }
        
        $args = array(
            'body' => $data,
            'timeout' => 15,
            'sslverify' => true,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        );
        
        $response = wp_remote_post($this->api_url, $args);
        return $response;
        */
    }
    
    /**
     * Get license info
     */
    public function get_license_info() {
        return array(
            'key' => get_option('ic_license_key', ''),
            'status' => get_option('ic_license_status', 'invalid'),
            'type' => get_option('ic_license_type', 'free'),
            'expires' => get_option('ic_license_expires', ''),
            'disabled' => get_option('ic_license_disabled', false)
        );
    }
}
