<?php
/**
 * Uninstall Image Converter Plugin
 * 
 * This file is executed when the plugin is uninstalled.
 */

// Exit if uninstall not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('ic_max_file_size');
delete_option('ic_default_quality');
delete_option('ic_is_pro');
delete_option('ic_license_key');
delete_option('ic_total_conversions');
delete_option('ic_total_space_saved');
delete_option('ic_woo_auto_convert');
delete_option('ic_woo_auto_optimize');
delete_option('ic_auto_compress');
delete_option('ic_auto_compress_size');

// Delete uploaded converted images directory
$upload_dir = wp_upload_dir();
$ic_dir = $upload_dir['basedir'] . '/imagineer';

if (file_exists($ic_dir)) {
    // Remove all files in directory
    $files = glob($ic_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    // Remove directory
    rmdir($ic_dir);
}

