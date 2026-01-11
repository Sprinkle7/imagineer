<?php
/**
 * Auto-optimize images on upload
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagineer_Auto_Optimize {
    
    private $optimizer;
    private $core;
    private $backup;
    
    public function __construct() {
        $this->optimizer = new Imagineer_Optimizer();
        $this->core = new Imagineer_Core();
        $this->backup = new Imagineer_Backup();
        
        // Hook into WordPress upload process
        add_filter('wp_generate_attachment_metadata', array($this, 'auto_optimize_on_upload'), 10, 2);
    }
    
    /**
     * Auto-optimize images when uploaded
     */
    public function auto_optimize_on_upload($metadata, $attachment_id) {
        // Only process images
        if (!wp_attachment_is_image($attachment_id)) {
            return $metadata;
        }
        
        // Skip if this is a restore operation (prevent infinite loop)
        if (get_post_meta($attachment_id, '_ic_restoring', true)) {
            delete_post_meta($attachment_id, '_ic_restoring');
            error_log('Imagineer: Skipping auto-optimize during restore for attachment ID: ' . $attachment_id);
            return $metadata;
        }
        
        // Skip if already processed (prevent double processing)
        if (get_post_meta($attachment_id, '_ic_auto_optimized', true)) {
            return $metadata;
        }
        
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            return $metadata;
        }
        
        // Check if file is already WebP (don't convert WebP to WebP)
        $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if ($file_ext === 'webp') {
            error_log('Imagineer: File is already WebP, skipping conversion: ' . $file_path);
            update_post_meta($attachment_id, '_ic_auto_optimized', true);
            return $metadata;
        }
        
        $mime_type = get_post_mime_type($attachment_id);
        // If mime type not set, try to detect from file
        if (!$mime_type) {
            $file_info = wp_check_filetype($file_path);
            $mime_type = $file_info['type'];
        }
        
        $file_size = filesize($file_path);
        
        // Check if auto-compress is enabled
        $auto_compress = get_option('ic_auto_compress', false);
        $auto_compress_size = get_option('ic_auto_compress_size', 500 * 1024); // Default 500KB
        
        // Check if auto-convert to WebP is enabled
        $auto_convert_png = get_option('ic_auto_convert_png', false);
        $auto_convert_jpg = get_option('ic_auto_convert_jpg', false);
        
        error_log('Imagineer: Auto-optimize check - MIME: ' . $mime_type . ', PNG convert: ' . ($auto_convert_png ? 'yes' : 'no') . ', JPG convert: ' . ($auto_convert_jpg ? 'yes' : 'no'));
        
        $should_convert = false;
        $target_format = 'webp';
        
        // Determine if we should convert to WebP
        if (($mime_type === 'image/png' || $file_ext === 'png') && $auto_convert_png) {
            $should_convert = true;
            error_log('Imagineer: PNG conversion enabled, will convert to WebP');
        } elseif (($mime_type === 'image/jpeg' || $mime_type === 'image/jpg' || $file_ext === 'jpg' || $file_ext === 'jpeg') && $auto_convert_jpg) {
            $should_convert = true;
            error_log('Imagineer: JPG conversion enabled, will convert to WebP');
        }
        
        // Check if WebP is supported before converting
        if ($should_convert && !$this->optimizer->is_webp_supported()) {
            error_log('Imagineer: WebP not supported on this server, skipping conversion');
            $should_convert = false; // Can't convert if WebP not supported
        }
        
        // Perform conversion if needed
        if ($should_convert) {
            error_log('Imagineer: Starting auto-convert to WebP for attachment ID: ' . $attachment_id);
            $new_metadata = $this->convert_to_webp($attachment_id, $file_path, $metadata);
            // After conversion, file_path changes, so update it
            $file_path = get_attached_file($attachment_id);
            if (!$file_path || !file_exists($file_path)) {
                return $metadata;
            }
            // Use the new metadata returned from conversion
            if ($new_metadata) {
                $metadata = $new_metadata;
            }
            $file_size = filesize($file_path);
            // Mark as processed to prevent double processing
            update_post_meta($attachment_id, '_ic_auto_optimized', true);
        }
        
        // Perform compression if enabled and file is large enough
        if ($auto_compress && $file_size > $auto_compress_size) {
            $new_metadata = $this->compress_image($attachment_id, $file_path, $metadata);
            // Use the new metadata if compression regenerated thumbnails
            if ($new_metadata) {
                $metadata = $new_metadata;
            }
        }
        
        return $metadata;
    }
    
    /**
     * Convert image to WebP
     */
    private function convert_to_webp($attachment_id, $file_path, $metadata) {
        $upload_dir = wp_upload_dir();
        $original_dir = dirname($file_path);
        
        // Get original file size before conversion
        $original_size = filesize($file_path);
        
        // Create backup if enabled
        $backup_enabled = get_option('ic_enable_backups', true);
        if ($backup_enabled) {
            $this->backup->create_backup($attachment_id, $file_path);
        }
        
        // Create new filename with .webp extension
        // Remove WordPress suffixes like -scaled, -rotated, etc.
        $path_info = pathinfo($file_path);
        $base_filename = preg_replace('/-(scaled|rotated|\d+x\d+)$/', '', $path_info['filename']);
        $new_filename = $base_filename . '.webp';
        $new_file_path = $original_dir . '/' . $new_filename;
        
        // Get quality setting (default 80)
        $quality = get_option('ic_default_quality', 80);
        // Ensure quality is within valid range
        $quality = max(1, min(100, $quality));
        
        // Convert using optimizer - maintain resolution (null = keep original dimensions)
        $maintain_resolution = get_option('ic_maintain_resolution', true);
        $resize_width = $maintain_resolution ? null : null;
        $resize_height = $maintain_resolution ? null : null;
        
        $result = $this->optimizer->fast_convert($file_path, 'webp', $quality, $new_file_path, $resize_width, $resize_height);
        
        if (isset($result['error']) || !file_exists($new_file_path)) {
            error_log('Imagineer: Auto-convert to WebP failed: ' . (isset($result['error']) ? $result['error'] : 'File not created'));
            return;
        }
        
        // Get converted file size
        $converted_size = filesize($new_file_path);
        
        // Delete old file
        if ($file_path !== $new_file_path && file_exists($file_path)) {
            @unlink($file_path);
        }
        
        // Update attachment in database
        // WordPress stores relative paths, so we need to update both
        $relative_path = str_replace($upload_dir['basedir'] . '/', '', $new_file_path);
        update_attached_file($attachment_id, $new_file_path);
        // Also update the meta directly to ensure it's saved
        update_post_meta($attachment_id, '_wp_attached_file', $relative_path);
        
        // Update mime type
        wp_update_post(array(
            'ID' => $attachment_id,
            'post_mime_type' => 'image/webp',
            'guid' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_file_path)
        ));
        
        error_log('Imagineer: Updated attachment file path - Relative: ' . $relative_path . ', Absolute: ' . $new_file_path);
        
        // Delete old thumbnails (only if they exist and are different format)
        if ($metadata && isset($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size => $size_info) {
                if (isset($size_info['file'])) {
                    $thumb_path = $original_dir . '/' . $size_info['file'];
                    // Only delete if it's the old format
                    $thumb_ext = strtolower(pathinfo($size_info['file'], PATHINFO_EXTENSION));
                    if (file_exists($thumb_path) && $thumb_ext !== 'webp') {
                        @unlink($thumb_path);
                    }
                }
            }
        }
        
        // Regenerate thumbnails with the new WebP file
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attachment_id, $new_file_path);
        
        if (is_wp_error($attach_data)) {
            error_log('Imagineer: Thumbnail generation failed: ' . $attach_data->get_error_message());
            // Return empty metadata to prevent issues
            return null;
        }
        
        if ($attach_data) {
            // Update the file path in metadata to match the new file
            // wp_generate_attachment_metadata should already set this, but ensure it's correct
            if (!isset($attach_data['file']) || empty($attach_data['file'])) {
                $upload_dir = wp_upload_dir();
                if (strpos($new_file_path, $upload_dir['basedir']) === 0) {
                    $attach_data['file'] = str_replace($upload_dir['basedir'] . '/', '', $new_file_path);
                } else {
                    $attach_data['file'] = basename($new_file_path);
                }
            }
            
            // Update the metadata in database
            wp_update_attachment_metadata($attachment_id, $attach_data);
        }
        
        // Clear caches
        clean_attachment_cache($attachment_id);
        
        // Update image URLs in post content (if image was already used)
        $this->update_image_urls_in_posts($attachment_id, $file_path, $new_file_path, $upload_dir);
        
        // Track conversion
        do_action('ic_image_converted', $original_size, $converted_size);
        
        // Track in conversion history
        $ajax_handler = new Imagineer_Ajax();
        $original_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $track_file = array(
            'name' => basename($new_file_path),
            'size' => $original_size
        );
        $track_result = array(
            'size' => $converted_size
        );
        $ajax_handler->track_conversion($track_file, $original_ext, 'webp', $track_result);
        
        error_log('Imagineer: Auto-convert tracked in statistics');
        
        error_log('Imagineer: Auto-converted image to WebP: ' . $new_file_path . ' (saved ' . size_format($original_size - $converted_size) . ')');
        
        // Return the new metadata so it can be used by the filter
        return $attach_data;
    }
    
    /**
     * Update image URLs in all posts/pages when image is converted
     */
    private function update_image_urls_in_posts($attachment_id, $old_file_path, $new_file_path, $upload_dir) {
        global $wpdb;
        
        // Get old and new URLs
        $old_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $old_file_path);
        $new_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_file_path);
        
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
        
        // Get old and new base names
        $old_base_name = pathinfo($old_filename, PATHINFO_FILENAME);
        $new_base_name = pathinfo($new_filename, PATHINFO_FILENAME);
        
        // Update full-size image URLs
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
            $old_url,
            $new_url,
            '%' . $wpdb->esc_like($old_url) . '%'
        ));
        
        // Get all posts that might contain the old image URL
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_content FROM {$wpdb->posts} 
            WHERE post_content LIKE %s 
            AND post_type IN ('post', 'page', 'attachment') 
            AND post_status != 'trash'",
            '%' . $wpdb->esc_like($old_base_name) . '%'
        ));
        
        $updated_count = 0;
        foreach ($posts as $post) {
            $original_content = $post->post_content;
            $updated_content = $original_content;
            
            // Replace full URL
            $updated_content = str_replace($old_url, $new_url, $updated_content);
            
            // Replace URLs with size suffixes (e.g., -819x1024.jpg -> -819x1024.webp)
            $pattern = '/' . preg_quote($old_base_name, '/') . '-(\d+x\d+)\.' . preg_quote($old_ext, '/') . '/';
            $replacement = $new_base_name . '-$1.' . $new_ext;
            $updated_content = preg_replace($pattern, $replacement, $updated_content);
            
            // Replace any remaining old extension references
            $updated_content = preg_replace(
                '/' . preg_quote($old_base_name, '/') . '\.' . preg_quote($old_ext, '/') . '/',
                $new_base_name . '.' . $new_ext,
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
                error_log('Imagineer: Updated post ID ' . $post->ID . ' with new image URL');
            }
        }
        
        // Also update postmeta (for featured images, custom fields, etc.)
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s",
            $old_url,
            $new_url,
            '%' . $wpdb->esc_like($old_url) . '%'
        ));
        
        error_log('Imagineer: Updated ' . $updated_count . ' posts with new image URLs');
    }
    
    /**
     * Compress image to reduce file size
     */
    private function compress_image($attachment_id, $file_path, $metadata) {
        $mime_type = get_post_mime_type($attachment_id);
        $format = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // Skip compression for formats that don't benefit from quality reduction
        // WebP is already optimized, and GIF has special requirements
        if ($format === 'webp' || $format === 'gif') {
            return;
        }
        
        // Get quality setting (use lower quality for compression)
        $quality = get_option('ic_default_quality', 80);
        // Reduce quality by 10-15% for compression (but not below 60)
        $compression_quality = max(60, $quality - 12);
        
        $upload_dir = wp_upload_dir();
        $original_dir = dirname($file_path);
        
        // Create temp file for compressed version
        $path_info = pathinfo($file_path);
        $temp_file = $original_dir . '/temp_compress_' . time() . '.' . $format;
        
        // Convert to same format with lower quality (compression)
        $result = $this->optimizer->fast_convert($file_path, $format, $compression_quality, $temp_file);
        
        if (isset($result['error']) || !file_exists($temp_file)) {
            error_log('Imagineer: Auto-compress failed: ' . (isset($result['error']) ? $result['error'] : 'File not created'));
            return;
        }
        
        $original_size = filesize($file_path);
        $compressed_size = filesize($temp_file);
        
        // Only replace if compressed version is at least 5% smaller
        $size_reduction = ($original_size - $compressed_size) / $original_size;
        if ($compressed_size < $original_size && $size_reduction >= 0.05) {
            // Replace original with compressed version
            @unlink($file_path);
            @rename($temp_file, $file_path);
            
            // Regenerate thumbnails if needed
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            
            if (is_wp_error($attach_data)) {
                error_log('Imagineer: Thumbnail generation failed after compression: ' . $attach_data->get_error_message());
                return null;
            }
            
            if ($attach_data) {
                // Ensure file path in metadata is correct
                if (!isset($attach_data['file']) || empty($attach_data['file'])) {
                    $upload_dir = wp_upload_dir();
                    if (strpos($file_path, $upload_dir['basedir']) === 0) {
                        $attach_data['file'] = str_replace($upload_dir['basedir'] . '/', '', $file_path);
                    } else {
                        $attach_data['file'] = basename($file_path);
                    }
                }
                
                wp_update_attachment_metadata($attachment_id, $attach_data);
            }
            
            // Clear caches
            clean_attachment_cache($attachment_id);
            
            error_log('Imagineer: Auto-compressed image: ' . $file_path . ' (saved ' . size_format($original_size - $compressed_size) . ', ' . round($size_reduction * 100, 1) . '%)');
            
            // Return the new metadata
            return $attach_data;
        } else {
            // Compressed version is larger or not enough reduction, keep original
            @unlink($temp_file);
            error_log('Imagineer: Compression would not reduce size enough, keeping original');
            return null;
        }
    }
}
