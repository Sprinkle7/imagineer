<?php
/**
 * Core functionality for Image Converter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagineer_Core {
    
    private $supported_formats = array(
        'png', 'jpg', 'jpeg', 'webp', 'heic', 'tiff', 'tif', 'bmp', 'gif'
    );
    
    public function __construct() {
        // Constructor
    }
    
    /**
     * Check if format is supported
     */
    public function is_format_supported($format, $is_pro = false) {
        return in_array(strtolower($format), $this->supported_formats);
    }
    
    /**
     * Get max file size
     */
    public function get_max_file_size() {
        return 50 * 1024 * 1024; // 50MB
    }
    
    /**
     * All features are now free!
     */
    /**
     * Check if Pro features are active
     * DISABLED: Always returns true - all features are available
     */
    public function is_pro_active() {
        // LICENSE SYSTEM DISABLED - Always return true
        return true;
        
        /* ORIGINAL CODE - COMMENTED OUT
        return true; // All features are free */ 
    }
    
    
    /**
     * Validate file before conversion
     */
    public function validate_file($file) {
        $errors = array();
        
        // Check if file exists
        if (!file_exists($file['tmp_name'])) {
            $errors[] = __('File not found.', 'imagineer');
            return $errors;
        }
        
        // Check file size
        $max_size = $this->get_max_file_size();
        if ($file['size'] > $max_size) {
            $errors[] = sprintf(
                __('File size exceeds maximum allowed size of %s.', 'imagineer'),
                size_format($max_size)
            );
        }
        
        // Check file type
        $file_type = wp_check_filetype($file['name']);
        $allowed_types = array('image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/tiff', 'image/tif', 'image/bmp', 'image/gif');
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = __('Invalid file type. Supported: PNG, JPG, WEBP, TIFF, BMP, GIF', 'imagineer');
        }
        
        return $errors;
    }
    
    /**
     * Get conversion capabilities - all features are free!
     */
    public function get_conversion_capabilities() {
        return array(
            'bulk_processing' => true,
            'max_files' => -1, // Unlimited
            'advanced_quality' => true,
            'resize' => true,
            'pro_formats' => true,
            'media_library' => true,
            'woocommerce' => true,
            'scheduling' => true,
            'api_access' => true
        );
    }
}

