<?php
/**
 * Backup and Restore system for image conversions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagineer_Backup {
    
    private $backup_dir;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->backup_dir = $upload_dir['basedir'] . '/imagineer/backups';
        
        // Create backup directory if it doesn't exist
        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
            // Create .htaccess to protect backups
            file_put_contents($this->backup_dir . '/.htaccess', 'deny from all');
        }
    }
    
    /**
     * Create backup of original file before conversion
     */
    public function create_backup($attachment_id, $file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        // Get original filename
        $original_filename = basename($file_path);
        $path_info = pathinfo($original_filename);
        
        // Remove any WordPress suffixes like -scaled, -rotated, etc.
        $base_name = preg_replace('/-(scaled|rotated|\d+x\d+)$/', '', $path_info['filename']);
        
        // Create backup filename with timestamp
        $backup_filename = $base_name . '_backup_' . time() . '.' . $path_info['extension'];
        $backup_path = $this->backup_dir . '/' . $backup_filename;
        
        // Copy file to backup location
        if (@copy($file_path, $backup_path)) {
            // Store backup info in attachment meta
            $backups = get_post_meta($attachment_id, '_ic_backups', true);
            if (!is_array($backups)) {
                $backups = array();
            }
            
            $backups[] = array(
                'backup_path' => $backup_path,
                'backup_url' => str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $backup_path),
                'original_path' => $file_path,
                'original_filename' => $original_filename,
                'created_at' => current_time('mysql'),
                'file_size' => filesize($backup_path)
            );
            
            update_post_meta($attachment_id, '_ic_backups', $backups);
            
            // Verify backup was saved
            $saved_backups = get_post_meta($attachment_id, '_ic_backups', true);
            error_log('Imagineer: Backup created: ' . $backup_path);
            error_log('Imagineer: Backup saved to meta - Count: ' . count($saved_backups) . ', Latest: ' . print_r(end($saved_backups), true));
            
            return $backup_path;
        }
        
        error_log('Imagineer: Failed to create backup: ' . $backup_path);
        return false;
    }
    
    /**
     * Restore original file from backup
     */
    public function restore_backup($attachment_id, $backup_index = null) {
        $backups = get_post_meta($attachment_id, '_ic_backups', true);
        
        if (empty($backups) || !is_array($backups)) {
            return array(
                'success' => false,
                'message' => __('No backups found for this image.', 'imagineer')
            );
        }
        
        // If no index specified, use the most recent backup
        if ($backup_index === null) {
            $backup_index = count($backups) - 1;
        }
        
        if (!isset($backups[$backup_index])) {
            return array(
                'success' => false,
                'message' => __('Backup not found.', 'imagineer')
            );
        }
        
        $backup = $backups[$backup_index];
        $backup_path = $backup['backup_path'];
        $original_path = $backup['original_path'];
        
        if (!file_exists($backup_path)) {
            return array(
                'success' => false,
                'message' => __('Backup file no longer exists.', 'imagineer')
            );
        }
        
        $upload_dir = wp_upload_dir();
        $current_file = get_attached_file($attachment_id);
        $current_dir = dirname($current_file);
        
        // Get original filename from backup (without any WordPress suffixes)
        $backup_filename = basename($backup_path);
        $backup_path_info = pathinfo($backup_filename);
        // Extract original filename from backup name (remove _backup_TIMESTAMP)
        $original_base = preg_replace('/_backup_\d+$/', '', $backup_path_info['filename']);
        $restored_filename = $original_base . '.' . $backup_path_info['extension'];
        $restored_file_path = $current_dir . '/' . $restored_filename;
        
        // Copy backup back to original location with correct filename
        if (@copy($backup_path, $restored_file_path)) {
            // Set flag to prevent auto-optimize from running during restore
            update_post_meta($attachment_id, '_ic_restoring', true);
            
            // Delete current file if different
            if ($current_file !== $restored_file_path && file_exists($current_file)) {
                @unlink($current_file);
            }
            
            // Update attachment file path in WordPress
            // This is critical - WordPress stores relative paths in _wp_attached_file meta
            $relative_path = str_replace($upload_dir['basedir'] . '/', '', $restored_file_path);
            update_attached_file($attachment_id, $restored_file_path);
            // Also update the meta directly to ensure it's saved
            update_post_meta($attachment_id, '_wp_attached_file', $relative_path);
            
            // Get original mime type from backup
            $backup_info = wp_check_filetype($backup_path);
            if ($backup_info['type']) {
                wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_mime_type' => $backup_info['type']
                ));
            }
            
            // Update GUID
            $restored_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $restored_file_path);
            wp_update_post(array(
                'ID' => $attachment_id,
                'guid' => $restored_url
            ));
            
            // Remove auto-optimized flag so it can be processed again if needed
            delete_post_meta($attachment_id, '_ic_auto_optimized');
            
            // IMPORTANT: Don't delete backups after restore - keep them so user can convert again
            // The restore button will hide because current format matches backup format
            // But when user converts again, a new backup will be created and button will appear
            
            // Regenerate thumbnails
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attachment_id, $restored_file_path);
            if ($attach_data && !is_wp_error($attach_data)) {
                // Ensure file path in metadata is correct (relative to uploads directory)
                if (strpos($restored_file_path, $upload_dir['basedir']) === 0) {
                    $attach_data['file'] = str_replace($upload_dir['basedir'] . '/', '', $restored_file_path);
                } else {
                    $attach_data['file'] = $relative_path;
                }
                wp_update_attachment_metadata($attachment_id, $attach_data);
            }
            
            error_log('Imagineer: Restored file path updated - Relative: ' . $relative_path . ', Absolute: ' . $restored_file_path);
            
            // Clear caches
            clean_attachment_cache($attachment_id);
            wp_cache_delete($attachment_id, 'posts');
            wp_cache_delete($attachment_id, 'post_meta');
            delete_transient('attachment_' . $attachment_id);
            
            // Remove restore flag after a short delay (to ensure metadata generation completes)
            // The flag will be removed by auto-optimize hook if it runs, or we remove it here
            delete_post_meta($attachment_id, '_ic_restoring');
            
            error_log('Imagineer: Restored backup for attachment ID: ' . $attachment_id . ' to: ' . $restored_file_path);
            
            return array(
                'success' => true,
                'message' => __('Image restored successfully!', 'imagineer'),
                'file_path' => $restored_file_path
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Failed to restore backup file.', 'imagineer')
        );
    }
    
    /**
     * Get all backups for an attachment
     */
    public function get_backups($attachment_id) {
        $backups = get_post_meta($attachment_id, '_ic_backups', true);
        return is_array($backups) ? $backups : array();
    }
    
    /**
     * Delete old backups (keep only last N backups)
     */
    public function cleanup_old_backups($attachment_id, $keep = 5) {
        $backups = $this->get_backups($attachment_id);
        
        if (count($backups) <= $keep) {
            return;
        }
        
        // Sort by creation time (oldest first)
        usort($backups, function($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });
        
        // Delete oldest backups
        $to_delete = array_slice($backups, 0, count($backups) - $keep);
        foreach ($to_delete as $backup) {
            if (file_exists($backup['backup_path'])) {
                @unlink($backup['backup_path']);
            }
        }
        
        // Update meta with remaining backups
        $remaining = array_slice($backups, -$keep);
        update_post_meta($attachment_id, '_ic_backups', $remaining);
    }
    
    /**
     * Get backup directory path
     */
    public function get_backup_dir() {
        return $this->backup_dir;
    }
}
