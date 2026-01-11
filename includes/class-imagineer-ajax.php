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
    private $backup;
    private $license;
    
    public function __construct() {
        $this->core = new Imagineer_Core();
        $this->optimizer = new Imagineer_Optimizer();
        $this->backup = new Imagineer_Backup();
        $this->license = new Imagineer_License();
        
        // AJAX actions
        add_action('wp_ajax_ic_convert_image', array($this, 'convert_image'));
        add_action('wp_ajax_ic_bulk_convert', array($this, 'bulk_convert'));
        add_action('wp_ajax_ic_media_library_convert', array($this, 'media_library_convert'));
        add_action('wp_ajax_ic_download_zip', array($this, 'download_zip'));
        add_action('wp_ajax_ic_install_library', array($this, 'install_library'));
        add_action('wp_ajax_ic_check_library_status', array($this, 'check_library_status'));
        add_action('wp_ajax_ic_activate_license', array($this, 'activate_license_ajax'));
        add_action('wp_ajax_ic_activate_license_new', array($this, 'activate_license_new_ajax'));
        add_action('wp_ajax_ic_deactivate_license', array($this, 'deactivate_license_ajax'));
        add_action('wp_ajax_ic_restore_backup', array($this, 'restore_backup_ajax'));
        
        // Filter attachment URLs to ensure correct format is returned
        add_filter('wp_get_attachment_url', array($this, 'filter_attachment_url'), 10, 2);
        add_filter('wp_get_attachment_image_src', array($this, 'filter_attachment_image_src'), 10, 4);
    }
    
    /**
     * AJAX handler for restoring backup
     */
    public function restore_backup_ajax() {
        check_ajax_referer('ic_convert_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'imagineer')));
        }
        
        // LICENSE CHECK DISABLED - All features are available
        
        $attachment_id = intval($_POST['attachment_id']);
        $backup_index = isset($_POST['backup_index']) ? intval($_POST['backup_index']) : null;
        
        $result = $this->backup->restore_backup($attachment_id, $backup_index);
        
        if ($result['success']) {
            // Update image URLs in posts after restore
            $file_path = get_attached_file($attachment_id);
            $upload_dir = wp_upload_dir();
            $this->update_image_urls_in_posts($attachment_id, '', $file_path, $upload_dir);
            
            // Don't cleanup backups immediately after restore - keep them so user can convert again
            // Only cleanup if there are more than 10 backups (very unlikely)
            $this->backup->cleanup_old_backups($attachment_id, 10);
            
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Activate license via AJAX (new license system)
     */
    public function activate_license_new_ajax() {
        check_ajax_referer('ic_convert_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'imagineer')));
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        
        if (empty($license_key)) {
            wp_send_json_error(array('message' => __('Please enter a license key.', 'imagineer')));
        }
        
        update_option('ic_license_key', $license_key);
        $result = $this->license->validate_license($license_key);
        
        if ($result['valid']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Filter attachment URL to return correct format
     */
    public function filter_attachment_url($url, $attachment_id) {
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return $url;
        }
        
        $current_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $url_ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        // If the file extension doesn't match the URL extension, fix it
        if ($current_ext !== $url_ext && in_array($current_ext, array('webp', 'jpg', 'jpeg', 'png'))) {
            $url = str_replace('.' . $url_ext, '.' . $current_ext, $url);
        }
        
        return $url;
    }
    
    /**
     * Filter attachment image src to return correct format
     */
    public function filter_attachment_image_src($image, $attachment_id, $size, $icon) {
        if (!$image || !is_array($image)) {
            return $image;
        }
        
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return $image;
        }
        
        $current_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $url_ext = strtolower(pathinfo(parse_url($image[0], PHP_URL_PATH), PATHINFO_EXTENSION));
        
        // If the file extension doesn't match the URL extension, fix it
        if ($current_ext !== $url_ext && in_array($current_ext, array('webp', 'jpg', 'jpeg', 'png'))) {
            $image[0] = str_replace('.' . $url_ext, '.' . $current_ext, $image[0]);
        }
        
        return $image;
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
        
        // LICENSE CHECK DISABLED - All features are available
        // $security = new Imagineer_Security();
        // if (!$security->v()) {
        //     wp_send_json_error(array('message' => __('License validation failed.', 'imagineer')));
        // }
        
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
        
        // Get source format
        $source_format = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
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
        
        // LICENSE CHECK DISABLED - All features are available
        // $security = new Imagineer_Security();
        // if (!$security->v()) {
        //     wp_send_json_error(array('message' => __('License validation failed.', 'imagineer')));
        // }
        
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
        
        // LICENSE CHECK DISABLED - All features are available
        
        $attachment_id = intval($_POST['attachment_id']);
        $target_format = sanitize_text_field($_POST['target_format']);
        $quality = isset($_POST['quality']) ? max(1, min(100, intval($_POST['quality']))) : get_option('ic_default_quality', 80);
        $maintain_resolution = isset($_POST['maintain_resolution']) && $_POST['maintain_resolution'] === 'true';
        $replace_original = isset($_POST['replace_original']) && $_POST['replace_original'] === 'true';
        
        // Ensure quality is within valid range
        if ($quality < 1) $quality = 1;
        if ($quality > 100) $quality = 100;
        
        // Log for debugging
        error_log('Imagineer Media Library Convert: ID=' . $attachment_id . ', Format=' . $target_format . ', Replace=' . ($replace_original ? 'yes' : 'no'));
        
        $file_path = get_attached_file($attachment_id);
        
        // If file not found, try to get from metadata
        if (!$file_path || !file_exists($file_path)) {
            $metadata = wp_get_attachment_metadata($attachment_id);
            if ($metadata && isset($metadata['file'])) {
                $upload_dir = wp_upload_dir();
                $file_path = $upload_dir['basedir'] . '/' . $metadata['file'];
                error_log('Imagineer: Trying metadata path: ' . $file_path);
            }
        }
        
        // If still not found, try GUID
        if (!$file_path || !file_exists($file_path)) {
            $attachment = get_post($attachment_id);
            if ($attachment && $attachment->guid) {
                $upload_dir = wp_upload_dir();
                $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $attachment->guid);
                error_log('Imagineer: Trying GUID path: ' . $file_path);
            }
        }
        
        if (!$file_path || !file_exists($file_path)) {
            error_log('Imagineer: File not found for attachment ID: ' . $attachment_id);
            error_log('Imagineer: Tried path: ' . $file_path);
            error_log('Imagineer: Attachment metadata: ' . print_r(wp_get_attachment_metadata($attachment_id), true));
            wp_send_json_error(array('message' => __('File not found. Please check that the image exists in the Media Library.', 'imagineer')));
        }
        
        error_log('Imagineer: Found file at: ' . $file_path);
        
        $source_format = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        $file = array(
            'name' => basename($file_path),
            'type' => get_post_mime_type($attachment_id),
            'tmp_name' => $file_path,
            'size' => filesize($file_path),
            'error' => 0
        );
        
        error_log('Imagineer: Starting conversion from ' . $source_format . ' to ' . $target_format);
        
        // If replacing original, convert DIRECTLY to Media Library location
        if ($replace_original) {
            $original_file = get_attached_file($attachment_id);
            $upload_dir = wp_upload_dir();
            $original_dir = dirname($original_file);
            
            // Create backup before conversion (if enabled)
            $backup_enabled = get_option('ic_enable_backups', true);
            if ($backup_enabled) {
                error_log('Imagineer: Creating backup before conversion for attachment ' . $attachment_id);
                $backup_path = $this->backup->create_backup($attachment_id, $original_file);
                if ($backup_path) {
                    error_log('Imagineer: ✓ Backup created successfully: ' . $backup_path);
                    // Verify backup was saved to meta
                    $saved_backups = get_post_meta($attachment_id, '_ic_backups', true);
                    error_log('Imagineer: Backup verification - Meta has ' . (is_array($saved_backups) ? count($saved_backups) : 0) . ' backups');
                } else {
                    error_log('Imagineer: ✗ Backup creation FAILED for attachment ' . $attachment_id);
                }
            } else {
                error_log('Imagineer: Backups are disabled in settings');
            }
            
            // Create new filename in the SAME directory as original
            // Remove WordPress suffixes like -scaled, -rotated, etc. to maintain original filename
            $path_info = pathinfo($original_file);
            $base_filename = preg_replace('/-(scaled|rotated|\d+x\d+)$/', '', $path_info['filename']);
            
            // Ensure we don't add -scaled suffix (WordPress does this for large images)
            // We want to keep the original filename structure
            $new_filename = $base_filename . '.' . $target_format;
            $new_file_path = $original_dir . '/' . $new_filename;
            
            // If file already exists with this name, add a number suffix
            $counter = 1;
            $final_new_file_path = $new_file_path;
            while (file_exists($final_new_file_path) && $counter < 100) {
                $final_new_file_path = $original_dir . '/' . $base_filename . '-' . $counter . '.' . $target_format;
                $counter++;
            }
            $new_file_path = $final_new_file_path;
            $new_filename = basename($new_file_path);
            
            error_log('Imagineer: Converting directly to: ' . $new_file_path . ' with quality: ' . $quality . ', maintain_resolution: ' . ($maintain_resolution ? 'yes' : 'no'));
            
            // Convert directly to the target location (no temp directory needed!)
            // If maintain_resolution is true, don't pass resize dimensions (null = keep original size)
            $resize_width = $maintain_resolution ? null : (isset($_POST['resize_width']) ? intval($_POST['resize_width']) : null);
            $resize_height = $maintain_resolution ? null : (isset($_POST['resize_height']) ? intval($_POST['resize_height']) : null);
            
            $result = $this->optimizer->fast_convert($file_path, $target_format, $quality, $new_file_path, $resize_width, $resize_height);
            
            if (isset($result['error'])) {
                error_log('Imagineer: Conversion failed: ' . $result['error']);
                wp_send_json_error(array('message' => $result['error']));
            }
            
            if (!file_exists($new_file_path)) {
                error_log('Imagineer: Converted file not created at: ' . $new_file_path);
                wp_send_json_error(array('message' => __('Conversion failed - file not created.', 'imagineer')));
            }
            
            error_log('Imagineer: File converted successfully to: ' . $new_file_path);
            
            // Delete old file if format changed
            if ($original_file !== $new_file_path && file_exists($original_file)) {
                error_log('Imagineer: Deleting old file: ' . $original_file);
                @unlink($original_file);
            }
            
            // Update attachment metadata in database
            error_log('Imagineer: Updating attachment in database');
            // WordPress stores relative paths, so we need to update both
            $relative_path = str_replace($upload_dir['basedir'] . '/', '', $new_file_path);
            update_attached_file($attachment_id, $new_file_path);
            // Also update the meta directly to ensure it's saved
            update_post_meta($attachment_id, '_wp_attached_file', $relative_path);
            
            // Update mime type and GUID
            $mime_type = 'image/' . $target_format;
            if ($target_format === 'jpg' || $target_format === 'jpeg') {
                $mime_type = 'image/jpeg';
            } elseif ($target_format === 'tiff' || $target_format === 'tif') {
                $mime_type = 'image/tiff';
            } elseif ($target_format === 'webp') {
                $mime_type = 'image/webp';
            }
            
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_mime_type' => $mime_type,
                'guid' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_file_path)
            ));
            
            error_log('Imagineer: Updated attachment file path after conversion - Relative: ' . $relative_path . ', Absolute: ' . $new_file_path);
            
            // Delete old thumbnails
            $metadata = wp_get_attachment_metadata($attachment_id);
            if ($metadata && isset($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size => $size_info) {
                    $thumb_path = $original_dir . '/' . $size_info['file'];
                    if (file_exists($thumb_path)) {
                        @unlink($thumb_path);
                    }
                }
            }
            
            // Regenerate thumbnails
            error_log('Imagineer: Regenerating thumbnails');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attachment_id, $new_file_path);
            
            if (is_wp_error($attach_data)) {
                error_log('Imagineer: Thumbnail generation failed: ' . $attach_data->get_error_message());
            } elseif ($attach_data) {
                // Ensure the file path in metadata is correct (relative to uploads directory)
                if (!isset($attach_data['file']) || empty($attach_data['file'])) {
                    if (strpos($new_file_path, $upload_dir['basedir']) === 0) {
                        $attach_data['file'] = str_replace($upload_dir['basedir'] . '/', '', $new_file_path);
                    } else {
                        $attach_data['file'] = $relative_path;
                    }
                }
                wp_update_attachment_metadata($attachment_id, $attach_data);
                error_log('Imagineer: Regenerated thumbnails and updated metadata');
            } else {
                error_log('Imagineer: Failed to regenerate thumbnails: ' . (is_wp_error($attach_data) ? $attach_data->get_error_message() : 'Unknown error'));
            }
            
            // Clear all caches
            clean_attachment_cache($attachment_id);
            wp_cache_delete($attachment_id, 'posts');
            wp_cache_delete($attachment_id, 'post_meta');
            clean_post_cache($attachment_id);
            
            // IMPORTANT: Verify backup still exists after conversion
            $verify_backups = get_post_meta($attachment_id, '_ic_backups', true);
            error_log('Imagineer: After conversion - Backups in meta: ' . (is_array($verify_backups) ? count($verify_backups) : 0));
            if (is_array($verify_backups) && count($verify_backups) > 0) {
                $latest_backup = end($verify_backups);
                error_log('Imagineer: Latest backup - Path: ' . (isset($latest_backup['backup_path']) ? $latest_backup['backup_path'] : 'N/A') . ', Ext: ' . (isset($latest_backup['backup_path']) ? pathinfo($latest_backup['backup_path'], PATHINFO_EXTENSION) : 'N/A'));
            }
            
            // Update image URLs in post content (must be done after metadata is updated)
            $this->update_image_urls_in_posts($attachment_id, $original_file, $new_file_path, $upload_dir);
            
            // Force WordPress to regenerate image URLs by clearing attachment URL cache
            delete_transient('_wp_attachment_' . $attachment_id);
            
            error_log('Imagineer: Media Library replacement complete! New file: ' . $new_file_path . ', Format: ' . $target_format);
            
            // Get file sizes for tracking
            $original_size = $file['size'];
            $converted_size = filesize($new_file_path);
            $space_saved = $original_size - $converted_size;
            
            // Track conversion for statistics
            do_action('ic_image_converted', $original_size, $converted_size);
            
            // Track in conversion history
            $track_file = array(
                'name' => basename($new_file_path),
                'size' => $original_size
            );
            $track_result = array(
                'size' => $converted_size
            );
            $this->track_conversion($track_file, $source_format, $target_format, $track_result);
            
            // Return success with file info
            $result = array(
                'filename' => basename($new_file_path),
                'path' => $new_file_path,
                'url' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_file_path),
                'size' => $converted_size,
                'original_size' => $original_size,
                'space_saved' => $space_saved,
                'format' => $target_format,
                'replaced' => true,
                'message' => 'Image successfully replaced in Media Library!'
            );
        } else {
            // Not replacing - use temp directory for download
            $result = $this->perform_conversion($file, $target_format, $quality);
            
            if (is_wp_error($result)) {
                error_log('Imagineer: Conversion failed: ' . $result->get_error_message());
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
            
            if (!isset($result['path']) || !file_exists($result['path'])) {
                error_log('Imagineer: Converted file not found at: ' . (isset($result['path']) ? $result['path'] : 'unknown'));
                wp_send_json_error(array('message' => __('Conversion failed - output file not created.', 'imagineer')));
            }
            
            error_log('Imagineer: Conversion successful for download');
        }
        
            // Track conversion for statistics
            do_action('ic_image_converted', $file['size'], $result['size']);
            
            // Track conversion in history
            $this->track_conversion($file, $source_format, $target_format, $result);
            
            wp_send_json_success($result);
    }
    
    /**
     * Perform the actual image conversion (optimized version)
     */
    public function perform_conversion($file, $target_format, $quality, $resize_width = null, $resize_height = null) {
        $source_path = $file['tmp_name'];
        $source_format = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        error_log('Imagineer perform_conversion: source_path=' . $source_path . ', target=' . $target_format);
        
        // Validate source file exists
        if (!file_exists($source_path)) {
            error_log('Imagineer: Source file not found: ' . $source_path);
            return new WP_Error('file_not_found', __('Source file not found: ' . $source_path, 'imagineer'));
        }
        
        // Validate conversion is supported
        if (!$this->core->is_format_supported($source_format)) {
            error_log('Imagineer: Source format not supported: ' . $source_format);
            return new WP_Error('unsupported_format', __('Source format not supported: ' . $source_format, 'imagineer'));
        }
        
        if (!$this->core->is_format_supported($target_format)) {
            error_log('Imagineer: Target format not supported: ' . $target_format);
            return new WP_Error('unsupported_format', __('Target format not supported: ' . $target_format, 'imagineer'));
        }
        
        // Create output filename
        $upload_dir = wp_upload_dir();
        $ic_dir = $upload_dir['basedir'] . '/imagineer';
        
        if (!file_exists($ic_dir)) {
            error_log('Imagineer: Creating directory: ' . $ic_dir);
            wp_mkdir_p($ic_dir);
            chmod($ic_dir, 0777);
        }
        
        // Test if we can actually write (more reliable than is_writable check)
        $test_file = $ic_dir . '/.imagineer_test_' . time();
        $can_write = @file_put_contents($test_file, 'test');
        
        if ($can_write === false) {
            error_log('Imagineer: Cannot write to directory: ' . $ic_dir);
            
            // Provide helpful error with fix command
            $fix_command = sprintf(
                'sudo chmod -R 777 %s',
                $ic_dir
            );
            
            return new WP_Error(
                'directory_not_writable', 
                sprintf(
                    __('Cannot write to output directory. Please run this command in Terminal and try again:\n\n%s\n\nOr visit: %s', 'imagineer'),
                    $fix_command,
                    site_url('wp-content/plugins/imagineer/fix-permissions.php')
                )
            );
        } else {
            @unlink($test_file);
        }
        
        $base_name = pathinfo($file['name'], PATHINFO_FILENAME);
        $resize_suffix = '';
        if ($resize_width || $resize_height) {
            $resize_suffix = '_' . ($resize_width ?: 'auto') . 'x' . ($resize_height ?: 'auto');
        }
        $output_filename = $base_name . $resize_suffix . '.' . $target_format;
        $output_path = $ic_dir . '/' . $output_filename;
        
        error_log('Imagineer: Output path will be: ' . $output_path);
        
        // Use optimized converter for better performance
        $result = $this->optimizer->fast_convert($source_path, $target_format, $quality, $output_path, $resize_width, $resize_height);
        
        if (isset($result['error'])) {
            error_log('Imagineer: Conversion error: ' . $result['error']);
            return new WP_Error('conversion_error', $result['error']);
        }
        
        // Verify result
        if (!isset($result['path']) || !file_exists($result['path'])) {
            error_log('Imagineer: Result path missing or file not created');
            return new WP_Error('conversion_failed', __('Conversion completed but output file not found.', 'imagineer'));
        }
        
        error_log('Imagineer: Conversion successful - file at: ' . $result['path'] . ', size: ' . $result['size']);
        
        // Ensure filename has proper extension
        $output_filename = basename($result['path']);
        // If filename doesn't have extension or has wrong extension, fix it
        if (!pathinfo($output_filename, PATHINFO_EXTENSION) || 
            strtolower(pathinfo($output_filename, PATHINFO_EXTENSION)) !== strtolower($target_format)) {
            $base_name = pathinfo($file['name'], PATHINFO_FILENAME);
            $resize_suffix = '';
            if ($resize_width || $resize_height) {
                $resize_suffix = '_' . ($resize_width ?: 'auto') . 'x' . ($resize_height ?: 'auto');
            }
            $output_filename = $base_name . $resize_suffix . '.' . $target_format;
        }
        
        // Return file info
        return array(
            'filename' => $output_filename,
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
     * Made public so auto-optimize can call it
     */
    public function track_conversion($file, $from_format, $to_format, $result) {
        if (!isset($result['size']) || !isset($file['size'])) {
            error_log('Imagineer: Cannot track conversion - missing size data');
            return;
        }
        
        $conversions = get_option('ic_recent_conversions', array());
        if (!is_array($conversions)) {
            $conversions = array();
        }
        
        // Add new conversion
        $conversion_entry = array(
            'date' => current_time('Y-m-d H:i:s'),
            'filename' => isset($file['name']) ? $file['name'] : 'unknown',
            'from' => strtolower($from_format),
            'to' => strtolower($to_format),
            'original_size' => intval($file['size']),
            'converted_size' => intval($result['size']),
            'space_saved' => intval($file['size'] - $result['size'])
        );
        
        $conversions[] = $conversion_entry;
        
        // Keep only last 100 conversions (increased from 50)
        if (count($conversions) > 100) {
            $conversions = array_slice($conversions, -100);
        }
        
        update_option('ic_recent_conversions', $conversions);
        
        // Update totals
        $total_conversions = get_option('ic_total_conversions', 0);
        update_option('ic_total_conversions', $total_conversions + 1);
        
        // Update files processed (same as conversions)
        update_option('ic_total_uploads', $total_conversions + 1);
        
        $space_saved = $file['size'] - $result['size'];
        if ($space_saved > 0) {
            $total_space_saved = get_option('ic_total_space_saved', 0);
            update_option('ic_total_space_saved', $total_space_saved + $space_saved);
        }
        
        error_log('Imagineer: Conversion tracked - ' . $conversion_entry['filename'] . ' (' . strtoupper($from_format) . ' → ' . strtoupper($to_format) . ') - Saved: ' . size_format($space_saved));
    }
    
    /**
     * Update image URLs in all posts/pages when image is converted
     */
    private function update_image_urls_in_posts($attachment_id, $old_file_path, $new_file_path, $upload_dir) {
        global $wpdb;
        
        // Get old and new URLs (both with and without domain)
        $old_url_full = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $old_file_path);
        $new_url_full = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_file_path);
        
        // Get relative URLs (without domain)
        $old_url_relative = str_replace($upload_dir['basedir'], '', $old_file_path);
        $new_url_relative = str_replace($upload_dir['basedir'], '', $new_file_path);
        
        // Get old and new filenames
        $old_filename = basename($old_file_path);
        $new_filename = basename($new_file_path);
        
        // Get old and new extensions
        $old_ext = strtolower(pathinfo($old_file_path, PATHINFO_EXTENSION));
        $new_ext = strtolower(pathinfo($new_file_path, PATHINFO_EXTENSION));
        
        // If extension changed, we need to update URLs
        if ($old_ext === $new_ext) {
            return; // No format change, no need to update
        }
        
        error_log('Imagineer: Updating image URLs in posts from ' . $old_ext . ' to ' . $new_ext);
        error_log('Imagineer: Old URL: ' . $old_url_full);
        error_log('Imagineer: New URL: ' . $new_url_full);
        
        // Get old and new base names (filename without extension)
        $old_base_name = pathinfo($old_filename, PATHINFO_FILENAME);
        $new_base_name = pathinfo($new_filename, PATHINFO_FILENAME);
        
        // Get all posts that might contain the old image URL (search more broadly)
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_content, post_type FROM {$wpdb->posts} 
            WHERE (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s)
            AND post_status != 'trash'",
            '%' . $wpdb->esc_like($old_base_name) . '%',
            '%' . $wpdb->esc_like($old_filename) . '%',
            '%' . $wpdb->esc_like($old_ext) . '%'
        ));
        
        $updated_count = 0;
        foreach ($posts as $post) {
            $original_content = $post->post_content;
            $updated_content = $original_content;
            
            // Replace full URLs (with domain) - both with and without trailing slash
            $updated_content = str_replace($old_url_full, $new_url_full, $updated_content);
            $updated_content = str_replace(rtrim($old_url_full, '/'), rtrim($new_url_full, '/'), $updated_content);
            
            // Replace relative URLs
            $updated_content = str_replace($old_url_relative, $new_url_relative, $updated_content);
            
            // Replace URLs with size suffixes in various formats:
            // Pattern 1: filename-1024x683.jpg (with size suffix) - most common for thumbnails
            $updated_content = preg_replace(
                '/' . preg_quote($old_base_name, '/') . '-(\d+x\d+)\.' . preg_quote($old_ext, '/') . '/i',
                $new_base_name . '-$1.' . $new_ext,
                $updated_content
            );
            
            // Pattern 2: filename.jpg (without size suffix) - full size images
            $updated_content = preg_replace(
                '/' . preg_quote($old_base_name, '/') . '\.' . preg_quote($old_ext, '/') . '/i',
                $new_base_name . '.' . $new_ext,
                $updated_content
            );
            
            // Pattern 3: In srcset attributes (handle multiple URLs)
            $updated_content = preg_replace(
                '/' . preg_quote($old_base_name, '/') . '-(\d+x\d+)\.' . preg_quote($old_ext, '/') . '(\s+\d+w)?/i',
                $new_base_name . '-$1.' . $new_ext . '$2',
                $updated_content
            );
            
            // Pattern 4: Handle URLs in HTML attributes (src, srcset, data-src, etc.)
            // This handles cases where the URL is in an attribute
            $updated_content = preg_replace_callback(
                '/(src|srcset|data-src|data-srcset|href)=["\']([^"\']*)' . preg_quote($old_base_name, '/') . '([^"\']*\.' . preg_quote($old_ext, '/') . '[^"\']*)["\']/i',
                function($matches) use ($old_base_name, $new_base_name, $old_ext, $new_ext) {
                    $attr = $matches[1];
                    $before = $matches[2];
                    $after = $matches[3];
                    // Replace the base name and extension
                    $new_after = str_replace($old_base_name, $new_base_name, $after);
                    $new_after = str_replace('.' . $old_ext, '.' . $new_ext, $new_after);
                    return $attr . '="' . $before . $new_base_name . $new_after . '"';
                },
                $updated_content
            );
            
            if ($updated_content !== $original_content) {
                $wpdb->update(
                    $wpdb->posts,
                    array('post_content' => $updated_content),
                    array('ID' => $post->ID),
                    array('%s'),
                    array('%d')
                );
                $updated_count++;
                error_log('Imagineer: Updated post ID ' . $post->ID . ' (type: ' . $post->post_type . ') with new image URL');
            }
        }
        
        // Also update postmeta (for featured images, custom fields, etc.)
        $meta_updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s",
            $old_url_full,
            $new_url_full,
            '%' . $wpdb->esc_like($old_url_full) . '%'
        ));
        
        // Update relative URLs in postmeta
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s",
            $old_url_relative,
            $new_url_relative,
            '%' . $wpdb->esc_like($old_url_relative) . '%'
        ));
        
        // Clear object cache for the attachment
        wp_cache_delete($attachment_id, 'posts');
        wp_cache_delete($attachment_id, 'post_meta');
        
        // Clear any attachment-related caches
        clean_post_cache($attachment_id);
        
        error_log('Imagineer: Updated ' . $updated_count . ' posts and metadata with new image URLs');
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
    
    /**
     * Install WebP Convert library via AJAX
     */
    public function install_library() {
        check_ajax_referer('ic_convert_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'imagineer')));
        }
        
        $installer = new Imagineer_Library_Installer();
        $result = $installer->install();
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'already_installed' => isset($result['already_installed']) && $result['already_installed']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message'],
                'error_code' => isset($result['error_code']) ? $result['error_code'] : 'unknown'
            ));
        }
    }
    
    /**
     * Check library installation status
     */
    public function check_library_status() {
        check_ajax_referer('ic_convert_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'imagineer')));
        }
        
        $installer = new Imagineer_Library_Installer();
        $status = $installer->get_status();
        
        wp_send_json_success($status);
    }
}
