<?php
/**
 * User-Friendly Error Messages
 * Replaces technical errors with helpful, actionable messages
 */

class Imagineer_Messages {
    
    /**
     * Get user-friendly error message
     */
    public static function get_friendly_error($technical_error) {
        $error_map = array(
            // File errors
            'invalid_file_type' => array(
                'title' => __('Unsupported File Type', 'imagineer'),
                'message' => __('This file format isn\'t supported. Please upload PNG, JPG, WEBP, or other supported image formats.', 'imagineer'),
                'action' => __('Check supported formats in the help section', 'imagineer'),
            ),
            'file_too_large' => array(
                'title' => __('File Size Too Large', 'imagineer'),
                'message' => __('This file exceeds the maximum size limit (50MB). Please compress the image first or split it into smaller files.', 'imagineer'),
                'action' => __('Try a smaller file', 'imagineer'),
            ),
            'file_upload_failed' => array(
                'title' => __('Upload Failed', 'imagineer'),
                'message' => __('We couldn\'t upload your file. This might be due to network issues or server restrictions.', 'imagineer'),
                'action' => __('Check your connection and try again', 'imagineer'),
            ),
            
            // Conversion errors
            'conversion_failed' => array(
                'title' => __('Conversion Failed', 'imagineer'),
                'message' => __('We couldn\'t convert this image. The file might be corrupted or in an unsupported format.', 'imagineer'),
                'action' => __('Try a different image or format', 'imagineer'),
            ),
            'format_not_supported' => array(
                'title' => __('Format Not Available', 'imagineer'),
                'message' => __('This conversion format requires additional server support. Contact your hosting provider or upgrade your server.', 'imagineer'),
                'action' => __('Choose a different format like JPG or PNG', 'imagineer'),
            ),
            'webp_not_supported' => array(
                'title' => __('WebP Not Available', 'imagineer'),
                'message' => __('WebP conversion requires GD Library with WebP support or Imagick. Your server doesn\'t have this enabled yet.', 'imagineer'),
                'action' => __('Contact your hosting provider to enable WebP support', 'imagineer'),
            ),
            
            // Permission errors
            'permission_denied' => array(
                'title' => __('Permission Denied', 'imagineer'),
                'message' => __('You don\'t have permission to perform this action. Contact your site administrator.', 'imagineer'),
                'action' => __('Ask an administrator for help', 'imagineer'),
            ),
            'disk_space_full' => array(
                'title' => __('Storage Full', 'imagineer'),
                'message' => __('Your server is out of storage space. Free up some space or contact your hosting provider.', 'imagineer'),
                'action' => __('Delete unused files or upgrade hosting', 'imagineer'),
            ),
            
            // Feature info (all features are now free!)
            'pro_feature_required' => array(
                'title' => __('Feature Not Available', 'imagineer'),
                'message' => __('This feature requires additional server configuration. Please contact your hosting provider.', 'imagineer'),
                'action' => __('Contact hosting support', 'imagineer'),
            ),
            'bulk_limit_reached' => array(
                'title' => __('Please Process Separately', 'imagineer'),
                'message' => __('For best results, please process your images in batches to avoid server timeout.', 'imagineer'),
                'action' => __('Process in smaller batches', 'imagineer'),
            ),
            
            // License errors
            'license_invalid' => array(
                'title' => __('Invalid License', 'imagineer'),
                'message' => __('Your license key isn\'t valid. Please check it and try again, or contact support if you need help.', 'imagineer'),
                'action' => __('Check your license key or contact support', 'imagineer'),
            ),
            'license_expired' => array(
                'title' => __('License Expired', 'imagineer'),
                'message' => __('Your license has expired. Renew it to continue using Pro features.', 'imagineer'),
                'action' => __('Renew your license', 'imagineer'),
            ),
            
            // Network errors
            'network_error' => array(
                'title' => __('Connection Error', 'imagineer'),
                'message' => __('We couldn\'t reach the server. Check your internet connection and try again.', 'imagineer'),
                'action' => __('Check your connection', 'imagineer'),
            ),
            'server_error' => array(
                'title' => __('Server Error', 'imagineer'),
                'message' => __('Something went wrong on the server. This is usually temporary - try again in a moment.', 'imagineer'),
                'action' => __('Wait a moment and try again', 'imagineer'),
            ),
        );
        
        // Find matching error
        foreach ($error_map as $key => $error) {
            if (stripos($technical_error, $key) !== false || stripos($technical_error, str_replace('_', ' ', $key)) !== false) {
                return $error;
            }
        }
        
        // Generic fallback
        return array(
            'title' => __('Oops! Something Went Wrong', 'imagineer'),
            'message' => __('We encountered an unexpected error. Please try again or contact support if the problem persists.', 'imagineer'),
            'action' => __('Try again', 'imagineer'),
        );
    }
    
    /**
     * Format error for display
     */
    public static function format_error($error) {
        if (is_array($error)) {
            return $error;
        }
        
        return self::get_friendly_error($error);
    }
    
    /**
     * Get success message
     */
    public static function get_success_message($action, $data = array()) {
        $messages = array(
            'conversion_complete' => array(
                'title' => __('Conversion Complete!', 'imagineer'),
                'message' => sprintf(
                    __('Your image has been converted successfully. Saved %s!', 'imagineer'),
                    isset($data['saved']) ? $data['saved'] : __('space', 'imagineer')
                ),
            ),
            'bulk_complete' => array(
                'title' => __('All Done!', 'imagineer'),
                'message' => sprintf(
                    __('%d images converted successfully!', 'imagineer'),
                    isset($data['count']) ? $data['count'] : 0
                ),
            ),
            'settings_saved' => array(
                'title' => __('Settings Saved', 'imagineer'),
                'message' => __('Your preferences have been saved successfully.', 'imagineer'),
            ),
            'license_activated' => array(
                'title' => __('Welcome!', 'imagineer'),
                'message' => __('Welcome to Imagineer! All features are available and ready to use.', 'imagineer'),
            ),
        );
        
        return isset($messages[$action]) ? $messages[$action] : array(
            'title' => __('Success!', 'imagineer'),
            'message' => __('Operation completed successfully.', 'imagineer'),
        );
    }
}

