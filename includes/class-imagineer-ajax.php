<?php
/**
 * AJAX handlers for Image Converter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagineer_Ajax {
    
    private $core;
    private $optimizer;
    
    public function __construct() {
        $this->core = new Imagineer_Core();
        $this->optimizer = new Imagineer_Optimizer();
        
        // AJAX actions
        add_action('wp_ajax_ic_convert_image', array($this, 'convert_image'));
        add_action('wp_ajax_ic_bulk_convert', array($this, 'bulk_convert'));
        add_action('wp_ajax_ic_media_library_convert', array($this, 'media_library_convert'));
        add_action('wp_ajax_ic_download_zip', array($this, 'download_zip'));
        add_action('wp_ajax_ic_activate_license', array($this, 'activate_license_ajax'));
        add_action('wp_ajax_ic_deactivate_license', array($this, 'deactivate_license_ajax'));
    }
    
    /**
     * AJAX license activation
     */
    public function activate_license_ajax() {
        check_ajax_referer('ic_convert_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'imagineer')));
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        $purchase_code = sanitize_text_field($_POST['purchase_code']);
        
        if (empty($license_key) || empty($purchase_code)) {
            wp_send_json_error(array('message' => __('Please enter both License Key and Purchase Code.', 'imagineer')));
        }
        
        // Validate with license server
        $licensing = new Imagineer_Licensing();
        $domain = $licensing->get_domain();
        $result = $licensing->validate_license($license_key, $purchase_code, $domain);
        
        if ($result['success']) {
            // Simple activation - just save the flags
            delete_option('ic_license_key');
            delete_option('ic_purchase_code');
            delete_option('ic_is_pro');
            delete_option('ic_license_data');
            delete_option('ic_last_license_check');
            
            add_option('ic_license_key', $license_key);
            add_option('ic_purchase_code', $purchase_code);
            add_option('ic_is_pro', true);
            add_option('ic_license_data', $result['data']);
            add_option('ic_last_license_check', time());
            
            $status_msg = isset($result['data']['verification_status']) && $result['data']['verification_status'] === 'verified' 
                ? __('License activated and verified! Pro features unlocked.', 'imagineer')
                : __('License activated! Your purchase will be verified within 24-48 hours. Pro features are now unlocked.', 'imagineer');
            
            wp_send_json_success(array('message' => $status_msg));
        } else {
            wp_send_json_error(array('message' => $result['message'] ?? __('Activation failed. Please check your codes.', 'imagineer')));
        }
    }
    
    /**
     * AJAX license deactivation
     */
    public function deactivate_license_ajax() {
        check_ajax_referer('ic_convert_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'imagineer')));
        }
        
        delete_option('ic_license_key');
        delete_option('ic_purchase_code');
        delete_option('ic_is_pro');
        delete_option('ic_license_data');
        delete_option('ic_last_license_check');
        
        wp_send_json_success(array('message' => __('License deactivated successfully.', 'imagineer')));
    }
    
    /**
     * Convert single image
     */
    public function convert_image() {
        check_ajax_referer('ic_convert_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'imagineer')));
        }
        
        $file = $_FILES['image'];
        $target_format = sanitize_text_field($_POST['target_format']);
        $quality = isset($_POST['quality']) ? intval($_POST['quality']) : 80;
        $resize_width = isset($_POST['resize_width']) ? intval($_POST['resize_width']) : null;
        $resize_height = isset($_POST['resize_height']) ? intval($_POST['resize_height']) : null;
        
        // Validate file
        $errors = $this->core->validate_file($file);
        if (!empty($errors)) {
            wp_send_json_error(array('message' => implode(' ', $errors)));
        }
        
        // Perform conversion
        $result = $this->perform_conversion($file, $target_format, $quality, $resize_width, $resize_height);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Track conversion for statistics
        do_action('ic_image_converted', $file['size'], $result['size']);
        
        // Add to recent conversions
        $this->track_conversion($file, $source_format, $target_format, $result);
        
        wp_send_json_success($result);
    }
    
    /**
     * Bulk convert images
     */
    public function bulk_convert() {
        check_ajax_referer('ic_convert_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'imagineer')));
        }
        
        $files = $_FILES['images'];
        $target_format = sanitize_text_field($_POST['target_format']);
        $quality = isset($_POST['quality']) ? intval($_POST['quality']) : 80;
        
        $results = array();
        $errors = array();
        
        foreach ($files['name'] as $key => $name) {
            $file = array(
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
                'tmp_name' => $files['tmp_name'][$key],
                'error' => $files['error'][$key],
                'size' => $files['size'][$key]
            );
            
            $result = $this->perform_conversion($file, $target_format, $quality);
            
            if (is_wp_error($result)) {
                $errors[] = $name . ': ' . $result->get_error_message();
            } else {
                $results[] = $result;
                // Track conversion
                do_action('ic_image_converted', $file['size'], $result['size']);
            }
        }
        
        if (!empty($errors)) {
            wp_send_json_error(array('message' => implode('; ', $errors), 'results' => $results));
        }
        
        wp_send_json_success(array('results' => $results, 'count' => count($results)));
    }
    
    /**
     * Convert from Media Library
     */
    public function media_library_convert() {
        check_ajax_referer('ic_convert_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'imagineer')));
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        $target_format = sanitize_text_field($_POST['target_format']);
        $quality = isset($_POST['quality']) ? intval($_POST['quality']) : 80;
        $replace_original = isset($_POST['replace_original']) && $_POST['replace_original'] === 'true';
        
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error(array('message' => __('File not found.', 'imagineer')));
        }
        
        $file = array(
            'name' => basename($file_path),
            'type' => get_post_mime_type($attachment_id),
            'tmp_name' => $file_path,
            'size' => filesize($file_path)
        );
        
        $result = $this->perform_conversion($file, $target_format, $quality);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // If replace original, update attachment
        if ($replace_original && !is_wp_error($result)) {
            $original_file = get_attached_file($attachment_id);
            
            // Get upload directory info
            $upload_dir = wp_upload_dir();
            $original_dir = dirname($original_file);
            
            // Create new filename with proper extension
            $path_info = pathinfo($original_file);
            $new_filename = $path_info['filename'] . '.' . $target_format;
            $new_file_path = $original_dir . '/' . $new_filename;
            
            // Copy converted file to original location
            if (copy($result['path'], $new_file_path)) {
                // Delete old file
                if (file_exists($original_file)) {
                    @unlink($original_file);
                }
                
                // Delete converted file from temp location
                @unlink($result['path']);
                
                // Update attachment file path
                update_attached_file($attachment_id, $new_file_path);
                
                // Update mime type
                $mime_type = 'image/' . $target_format;
                if ($target_format === 'jpg' || $target_format === 'jpeg') {
                    $mime_type = 'image/jpeg';
                } elseif ($target_format === 'tiff' || $target_format === 'tif') {
                    $mime_type = 'image/tiff';
                }
                
                wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_mime_type' => $mime_type
                ));
                
                // Regenerate thumbnails
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attachment_id, $new_file_path);
                wp_update_attachment_metadata($attachment_id, $attach_data);
                
                // Update result path to new location
                $result['path'] = $new_file_path;
                $result['url'] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_file_path);
                $result['replaced'] = true;
            }
        }
        
        // Track conversion
        do_action('ic_image_converted', $file['size'], $result['size']);
        
        wp_send_json_success($result);
    }
    
    /**
     * Perform the actual image conversion (optimized version)
     */
    public function perform_conversion($file, $target_format, $quality, $resize_width = null, $resize_height = null) {
        $source_path = $file['tmp_name'];
        $source_format = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate conversion is supported
        if (!$this->core->is_format_supported($source_format)) {
            return new WP_Error('unsupported_format', __('Source format not supported.', 'imagineer'));
        }
        
        if (!$this->core->is_format_supported($target_format)) {
            return new WP_Error('unsupported_format', __('Target format not supported.', 'imagineer'));
        }
        
        // Create output filename
        $upload_dir = wp_upload_dir();
        $ic_dir = $upload_dir['basedir'] . '/imagineer';
        
        if (!file_exists($ic_dir)) {
            wp_mkdir_p($ic_dir);
        }
        
        $base_name = pathinfo($file['name'], PATHINFO_FILENAME);
        $resize_suffix = '';
        if ($resize_width || $resize_height) {
            $resize_suffix = '_' . ($resize_width ?: 'auto') . 'x' . ($resize_height ?: 'auto');
        }
        $output_filename = $base_name . $resize_suffix . '.' . $target_format;
        $output_path = $ic_dir . '/' . $output_filename;
        
        // Use optimized converter for better performance
        $result = $this->optimizer->fast_convert($source_path, $target_format, $quality, $output_path, $resize_width, $resize_height);
        
        if (isset($result['error'])) {
            return new WP_Error('conversion_error', $result['error']);
        }
        
        // Return file info
        return array(
            'filename' => basename($result['path']),
            'url' => $result['url'],
            'path' => $result['path'],
            'size' => $result['size'],
            'original_size' => $file['size'],
            'format' => $target_format,
            'cached' => isset($result['cached']) ? $result['cached'] : false
        );
    }
    
    /**
     * Track conversion for history
     */
    private function track_conversion($file, $from_format, $to_format, $result) {
        $conversions = get_option('ic_recent_conversions', array());
        
        // Add new conversion
        $conversions[] = array(
            'date' => current_time('Y-m-d H:i:s'),
            'filename' => $file['name'],
            'from' => $from_format,
            'to' => $to_format,
            'original_size' => $file['size'],
            'converted_size' => $result['size'],
            'space_saved' => $file['size'] - $result['size']
        );
        
        // Keep only last 50 conversions
        if (count($conversions) > 50) {
            $conversions = array_slice($conversions, -50);
        }
        
        update_option('ic_recent_conversions', $conversions);
        
        // Update totals
        update_option('ic_total_conversions', get_option('ic_total_conversions', 0) + 1);
        update_option('ic_total_uploads', get_option('ic_total_uploads', 0) + 1);
        
        $space_saved = $file['size'] - $result['size'];
        if ($space_saved > 0) {
            update_option('ic_total_space_saved', get_option('ic_total_space_saved', 0) + $space_saved);
        }
    }
    
    /**
     * Create ZIP download for bulk conversions (Pro only)
     */
    public function download_zip() {
        check_ajax_referer('ic_convert_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'imagineer')));
        }
        
        $files = isset($_POST['files']) ? json_decode(stripslashes($_POST['files']), true) : array();
        
        if (empty($files)) {
            wp_send_json_error(array('message' => __('No files to download.', 'imagineer')));
        }
        
        // Create ZIP file
        $upload_dir = wp_upload_dir();
        $zip_filename = 'imagineer-converted-' . time() . '.zip';
        $zip_path = $upload_dir['basedir'] . '/imagineer/' . $zip_filename;
        
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
            wp_send_json_error(array('message' => __('Could not create ZIP file.', 'imagineer')));
        }
        
        foreach ($files as $file) {
            if (file_exists($file['path'])) {
                $zip->addFile($file['path'], $file['filename']);
            }
        }
        
        $zip->close();
        
        // Return ZIP URL
        $zip_url = $upload_dir['baseurl'] . '/imagineer/' . $zip_filename;
        
        wp_send_json_success(array(
            'url' => $zip_url,
            'filename' => $zip_filename,
            'size' => filesize($zip_path)
        ));
    }
}
