<?php
/**
 * Optimized Image Converter with Performance Enhancements
 * Uses direct GD/Imagick calls for better performance
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagineer_Optimizer {
    
    private $cache_enabled = true;
    private $cache_dir;
    private $use_imagick = false;
    private $imagick_webp_support = false;
    private $memory_limit;
    private $webp_converter = null;
    private $security = null;
    
    public function __construct() {
        $this->cache_dir = wp_upload_dir()['basedir'] . '/imagineer/cache';
        $this->check_imagick();
        $this->set_memory_limit();
        
        // Initialize security (for license checks)
        if (class_exists('Imagineer_Security')) {
            $this->security = new Imagineer_Security();
        }
        
        // Initialize WebP converter
        if (class_exists('Imagineer_WebP')) {
            $this->webp_converter = new Imagineer_WebP();
        }
        
        // Create cache directory
        if ($this->cache_enabled && !file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }
    }
    
    /**
     * Check license (obfuscated method)
     */
    private function check_license() {
        return isset($this->security) ? $this->security->v() : true;
    }
    
    /**
     * Check if Imagick is available (faster than GD for most operations)
     */
    private function check_imagick() {
        $this->use_imagick = extension_loaded('imagick') && class_exists('Imagick');
        $this->imagick_webp_support = false;
        
        // Check if Imagick supports WebP
        if ($this->use_imagick) {
            try {
                $imagick = new Imagick();
                $formats = $imagick->queryFormats();
                $this->imagick_webp_support = in_array('WEBP', $formats);
                $imagick->clear();
                $imagick->destroy();
            } catch (Exception $e) {
                $this->imagick_webp_support = false;
            }
        }
    }
    
    /**
     * Check if WebP is supported (GD, Imagick, or WebP Convert library)
     */
    public function is_webp_supported() {
        // Check WebP Convert library first
        if ($this->webp_converter && $this->webp_converter->is_available()) {
            return true;
        }
        
        // Check Imagick (better support)
        if ($this->use_imagick && isset($this->imagick_webp_support) && $this->imagick_webp_support) {
            return true;
        }
        
        // Check GD WebP support (PHP 7.1+)
        if (function_exists('imagewebp') && function_exists('imagecreatefromwebp')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get WebP support details
     */
    public function get_webp_support_info() {
        $info = array(
            'supported' => false,
            'method' => 'none',
            'php_version' => PHP_VERSION,
            'gd_webp' => false,
            'imagick_webp' => false,
            'library_webp' => false,
            'message' => ''
        );
        
        // Check WebP Convert Library first
        if ($this->webp_converter && $this->webp_converter->is_available()) {
            $info['library_webp'] = true;
            $info['method'] = 'WebP Convert Library';
            $info['supported'] = true;
            $info['message'] = 'WebP is supported via WebP Convert Library (works on any server!)';
            return $info;
        }
        
        // Check GD
        if (function_exists('imagewebp') && function_exists('imagecreatefromwebp')) {
            $info['gd_webp'] = true;
            $info['method'] = 'GD';
            $info['supported'] = true;
        }
        
        // Check Imagick
        if ($this->use_imagick) {
            try {
                $imagick = new Imagick();
                $formats = $imagick->queryFormats();
                if (in_array('WEBP', $formats)) {
                    $info['imagick_webp'] = true;
                    $info['method'] = 'Imagick';
                    $info['supported'] = true;
                }
            } catch (Exception $e) {
                // Imagick available but WebP not supported
            }
        }
        
        // Generate helpful message
        if (!$info['supported']) {
            $info['message'] = 'WebP library not loaded. The plugin should include WebP Convert library for automatic WebP support.';
        } else {
            $info['message'] = 'WebP is supported via ' . $info['method'];
        }
        
        return $info;
    }
    
    /**
     * Set memory limit for large images
     */
    private function set_memory_limit() {
        $current_limit = ini_get('memory_limit');
        $this->memory_limit = wp_convert_hr_to_bytes($current_limit);
        
        // Increase memory limit for large images
        if ($this->memory_limit < 256 * 1024 * 1024) { // 256MB
            @ini_set('memory_limit', '256M');
        }
    }
    
    /**
     * Fast conversion using optimized methods
     */
    public function fast_convert($source_path, $target_format, $quality, $output_path = null, $resize_width = null, $resize_height = null) {
        // LICENSE CHECK DISABLED - All features are available
        // if (!$this->check_license()) {
        //     error_log('Imagineer: Conversion attempted without valid license - ' . basename($source_path));
        // }
        
        // Check cache first (skip if resizing)
        if ($this->cache_enabled && !$resize_width && !$resize_height) {
            $cached = $this->get_cached($source_path, $target_format, $quality);
            if ($cached && file_exists($cached)) {
                return array(
                    'path' => $cached,
                    'url' => str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $cached),
                    'cached' => true
                );
            }
        }
        
        // Use fastest available method
        if ($this->use_imagick) {
            $result = $this->convert_with_imagick($source_path, $target_format, $quality, $output_path, $resize_width, $resize_height);
        } else {
            $result = $this->convert_with_gd($source_path, $target_format, $quality, $output_path, $resize_width, $resize_height);
        }
        
        // Cache the result
        if ($this->cache_enabled && $result && !isset($result['error'])) {
            $this->cache_result($source_path, $target_format, $quality, $result['path']);
        }
        
        return $result;
    }
    
    /**
     * Convert using Imagick (faster and better quality)
     */
    private function convert_with_imagick($source_path, $target_format, $quality, $output_path = null, $resize_width = null, $resize_height = null) {
        try {
            $imagick = new Imagick($source_path);
            
            // Resize if dimensions provided
            if ($resize_width || $resize_height) {
                $current_width = $imagick->getImageWidth();
                $current_height = $imagick->getImageHeight();
                
                // Calculate dimensions maintaining aspect ratio
                if ($resize_width && !$resize_height) {
                    $resize_height = ($current_height / $current_width) * $resize_width;
                } elseif ($resize_height && !$resize_width) {
                    $resize_width = ($current_width / $current_height) * $resize_height;
                }
                
                $imagick->resizeImage($resize_width, $resize_height, Imagick::FILTER_LANCZOS, 1);
            }
            
            // Set quality
            $imagick->setImageCompressionQuality($quality);
            
            // Optimize based on format
            switch (strtolower($target_format)) {
                case 'webp':
                    // Check if Imagick supports WebP
                    $formats = $imagick->queryFormats();
                    if (!in_array('WEBP', $formats)) {
                        $imagick->clear();
                        $imagick->destroy();
                        $webp_info = $this->get_webp_support_info();
                        return array(
                            'error' => 'WebP is not supported by Imagick on your server. ' . $webp_info['message'],
                            'error_code' => 'webp_not_supported',
                            'suggestion' => 'Please contact your hosting provider to enable WebP support in ImageMagick, or use PNG/JPG format instead.'
                        );
                    }
                    $imagick->setImageFormat('webp');
                    // WebP specific optimizations
                    $imagick->setOption('webp:method', '6'); // Better compression
                    $imagick->setOption('webp:lossless', $quality >= 95 ? 'true' : 'false');
                    break;
                    
                case 'jpg':
                case 'jpeg':
                    $imagick->setImageFormat('jpeg');
                    // Remove transparency for JPEG
                    if ($imagick->getImageAlphaChannel()) {
                        $white = new ImagickPixel('white');
                        $imagick->setImageBackgroundColor($white);
                        $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                    }
                    break;
                    
                case 'png':
                    $imagick->setImageFormat('png');
                    // PNG optimization - use higher compression to reduce file size
                    // Compression level 0-9, where 9 is maximum compression
                    $compression_level = max(6, min(9, round((100 - $quality) / 10))); // Adjust based on quality
                    $imagick->setOption('png:compression-level', $compression_level);
                    // Use adaptive filtering for better compression
                    $imagick->setOption('png:compression-filter', '5'); // All filters
                    break;
                    
                case 'tiff':
                case 'tif':
                    $imagick->setImageFormat('tiff');
                    $imagick->setImageCompression(Imagick::COMPRESSION_LZW);
                    break;
                    
                case 'bmp':
                    $imagick->setImageFormat('bmp');
                    break;
                    
                case 'gif':
                    $imagick->setImageFormat('gif');
                    break;
            }
            
            // Strip metadata for smaller file size
            $imagick->stripImage();
            
            // Auto-orient based on EXIF
            $imagick->autoOrient();
            
            // Generate output path if not provided
            if (!$output_path) {
                $upload_dir = wp_upload_dir();
                $output_dir = $upload_dir['basedir'] . '/imagineer';
                wp_mkdir_p($output_dir);
                $base_name = pathinfo($source_path, PATHINFO_FILENAME);
                $output_path = $output_dir . '/' . $base_name . '.' . $target_format;
            }
            
            // Validate and prepare output directory
            $output_dir = dirname($output_path);
            if (!file_exists($output_dir)) {
                if (!wp_mkdir_p($output_dir)) {
                    $imagick->clear();
                    $imagick->destroy();
                    return array(
                        'error' => 'Failed to create output directory: ' . $output_dir,
                        'error_code' => 'directory_creation_failed'
                    );
                }
            }
            
            // Check if directory is writable
            if (!is_writable($output_dir)) {
                $imagick->clear();
                $imagick->destroy();
                return array(
                    'error' => 'Output directory is not writable: ' . $output_dir . '. Please check directory permissions.',
                    'error_code' => 'directory_not_writable'
                );
            }
            
            // Check disk space (at least 10MB free)
            $free_space = disk_free_space($output_dir);
            if ($free_space !== false && $free_space < 10 * 1024 * 1024) {
                $imagick->clear();
                $imagick->destroy();
                return array(
                    'error' => 'Insufficient disk space. Please free up at least 10MB of space.',
                    'error_code' => 'insufficient_disk_space'
                );
            }
            
            // Write optimized image
            $write_result = $imagick->writeImage($output_path);
            $imagick->clear();
            $imagick->destroy();
            
            if (!$write_result || !file_exists($output_path)) {
                return array(
                    'error' => 'Failed to save image. Imagick writeImage() returned false or file was not created. Output path: ' . $output_path,
                    'error_code' => 'save_failed',
                    'output_path' => $output_path,
                    'directory_writable' => is_writable($output_dir),
                    'disk_space' => disk_free_space($output_dir)
                );
            }
            
            return array(
                'path' => $output_path,
                'url' => str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $output_path),
                'size' => filesize($output_path),
                'format' => $target_format
            );
            
        } catch (Exception $e) {
            return array(
                'error' => 'Image conversion failed: ' . $e->getMessage(),
                'error_code' => 'conversion_exception',
                'exception_type' => get_class($e)
            );
        } catch (Error $e) {
            return array(
                'error' => 'Image conversion error: ' . $e->getMessage(),
                'error_code' => 'conversion_error',
                'exception_type' => get_class($e)
            );
        }
    }
    
    /**
     * Convert using GD (fallback, optimized)
     */
    private function convert_with_gd($source_path, $target_format, $quality, $output_path = null, $resize_width = null, $resize_height = null) {
        // Get image info
        $info = getimagesize($source_path);
        if (!$info) {
            return array('error' => 'Invalid image file');
        }
        
        $width = $info[0];
        $height = $info[1];
        $mime = $info['mime'];
        
        // Calculate resize dimensions
        if ($resize_width || $resize_height) {
            if ($resize_width && !$resize_height) {
                $resize_height = ($height / $width) * $resize_width;
            } elseif ($resize_height && !$resize_width) {
                $resize_width = ($width / $height) * $resize_height;
            }
            $resize_width = intval($resize_width);
            $resize_height = intval($resize_height);
        }
        
        // Create image resource from source
        switch ($mime) {
            case 'image/jpeg':
                $source_image = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $source_image = imagecreatefrompng($source_path);
                break;
            case 'image/tiff':
            case 'image/tif':
                if ($this->use_imagick) {
                    try {
                        $imagick = new Imagick($source_path);
                        $temp_png = tempnam(sys_get_temp_dir(), 'tiff_') . '.png';
                        $imagick->setImageFormat('png');
                        $imagick->writeImage($temp_png);
                        $imagick->clear();
                        $imagick->destroy();
                        
                        $source_image = imagecreatefrompng($temp_png);
                        @unlink($temp_png);
                    } catch (Exception $e) {
                        return array('error' => 'Failed to read TIFF file');
                    }
                } else {
                    return array('error' => 'TIFF format requires Imagick extension');
                }
                break;
                
            case 'image/bmp':
            case 'image/x-ms-bmp':
                if (function_exists('imagecreatefrombmp')) {
                    $source_image = imagecreatefrombmp($source_path);
                } else {
                    return array('error' => 'BMP format not supported in this PHP version');
                }
                break;
                
            case 'image/gif':
                $source_image = imagecreatefromgif($source_path);
                break;
                
            case 'image/webp':
                // Use WebP converter class for better WebP handling
                if ($this->webp_converter && $this->webp_converter->is_available()) {
                    // Generate output path if not provided
                    if (!$output_path) {
                        $upload_dir = wp_upload_dir();
                        $output_dir = $upload_dir['basedir'] . '/imagineer';
                        wp_mkdir_p($output_dir);
                        $base_name = pathinfo($source_path, PATHINFO_FILENAME);
                        $output_path = $output_dir . '/' . $base_name . '.' . $target_format;
                    }
                    
                    // Ensure output directory exists
                    $output_dir = dirname($output_path);
                    if (!file_exists($output_dir)) {
                        wp_mkdir_p($output_dir);
                    }
                    
                    error_log('Imagineer: Converting WebP to ' . $target_format . ' using WebP converter class. Output: ' . $output_path);
                    $webp_result = $this->webp_converter->convert_from_webp($source_path, $target_format, $quality, $output_path);
                    
                    if ($webp_result['success']) {
                        // Verify file exists
                        if (!file_exists($webp_result['path'])) {
                            error_log('Imagineer: WebP converter reported success but file not found: ' . $webp_result['path']);
                            // Fall through to GD method
                        } else {
                            error_log('Imagineer: WebP conversion successful: ' . $webp_result['path']);
                            return array(
                                'path' => $webp_result['path'],
                                'url' => str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $webp_result['path']),
                                'size' => $webp_result['size'],
                                'format' => $target_format
                            );
                        }
                    } else {
                        error_log('Imagineer: WebP converter failed: ' . (isset($webp_result['error']) ? $webp_result['error'] : 'Unknown error'));
                        // Fall through to GD method as fallback
                    }
                }
                
                // Fallback: Try Imagick
                if ($this->use_imagick) {
                    try {
                        $imagick = new Imagick($source_path);
                        $temp_png = tempnam(sys_get_temp_dir(), 'webp_') . '.png';
                        $imagick->setImageFormat('png');
                        $imagick->writeImage($temp_png);
                        $imagick->clear();
                        $imagick->destroy();
                        
                        $source_image = imagecreatefrompng($temp_png);
                        @unlink($temp_png);
                        
                        if (!$source_image) {
                            return array('error' => 'Failed to read WebP file');
                        }
                    } catch (Exception $e) {
                        if (function_exists('imagecreatefromwebp')) {
                            $source_image = imagecreatefromwebp($source_path);
                        } else {
                            return array('error' => 'Cannot read WebP files. Your server can CREATE WebP but cannot READ them.');
                        }
                    }
                } elseif (function_exists('imagecreatefromwebp')) {
                    $source_image = @imagecreatefromwebp($source_path);
                    if (!$source_image) {
                        $last_error = error_get_last();
                        error_log('Imagineer: imagecreatefromwebp failed - ' . ($last_error ? $last_error['message'] : 'Unknown error') . ' - Path: ' . $source_path);
                        return array('error' => 'Failed to read WebP file. ' . ($last_error ? $last_error['message'] : 'Unknown error'));
                    }
                } else {
                    return array('error' => 'Cannot read WebP files. Your server can CREATE WebP but cannot READ them. This is a server limitation.');
                }
                break;
            default:
                return array('error' => 'Unsupported source format');
        }
        
        if (!$source_image) {
            return array('error' => 'Failed to create image resource');
        }
        
        // Resize if needed
        if ($resize_width && $resize_height) {
            $resized_image = imagecreatetruecolor($resize_width, $resize_height);
            
            // Handle transparency for PNG/WEBP
            if ($target_format === 'png' || $target_format === 'webp') {
                imagealphablending($resized_image, false);
                imagesavealpha($resized_image, true);
                $transparent = imagecolorallocatealpha($resized_image, 0, 0, 0, 127);
                imagefill($resized_image, 0, 0, $transparent);
            }
            
            imagecopyresampled($resized_image, $source_image, 0, 0, 0, 0, $resize_width, $resize_height, $width, $height);
            imagedestroy($source_image);
            $source_image = $resized_image;
            $width = $resize_width;
            $height = $resize_height;
        }
        
        // Handle transparency for PNG
        if ($target_format === 'png') {
            imagealphablending($source_image, false);
            imagesavealpha($source_image, true);
        }
        
        // Generate output path if not provided
        if (!$output_path) {
            $upload_dir = wp_upload_dir();
            $output_dir = $upload_dir['basedir'] . '/imagineer';
            wp_mkdir_p($output_dir);
            $base_name = pathinfo($source_path, PATHINFO_FILENAME);
            $output_path = $output_dir . '/' . $base_name . '.' . $target_format;
        }
        
        // Validate and prepare output directory
        $output_dir = dirname($output_path);
        if (!file_exists($output_dir)) {
            if (!wp_mkdir_p($output_dir)) {
                imagedestroy($source_image);
                return array(
                    'error' => 'Failed to create output directory: ' . $output_dir,
                    'error_code' => 'directory_creation_failed'
                );
            }
        }
        
        // Check if directory is writable
        if (!is_writable($output_dir)) {
            imagedestroy($source_image);
            return array(
                'error' => 'Output directory is not writable: ' . $output_dir . '. Please check directory permissions.',
                'error_code' => 'directory_not_writable'
            );
        }
        
        // Check disk space (at least 10MB free)
        $free_space = disk_free_space($output_dir);
        if ($free_space !== false && $free_space < 10 * 1024 * 1024) {
            imagedestroy($source_image);
            return array(
                'error' => 'Insufficient disk space. Please free up at least 10MB of space.',
                'error_code' => 'insufficient_disk_space'
            );
        }
        
        // Save in target format with optimizations
        $result = false;
        $last_error = null;
        
        // Capture any PHP errors
        $error_handler = function($errno, $errstr, $errfile, $errline) use (&$last_error) {
            $last_error = $errstr;
        };
        set_error_handler($error_handler);
        
        switch (strtolower($target_format)) {
            case 'jpg':
            case 'jpeg':
                // Convert transparency to white for JPEG
                if ($mime === 'image/png') {
                    $white_bg = imagecreatetruecolor($width, $height);
                    imagefill($white_bg, 0, 0, imagecolorallocate($white_bg, 255, 255, 255));
                    imagecopy($white_bg, $source_image, 0, 0, 0, 0, $width, $height);
                    imagedestroy($source_image);
                    $source_image = $white_bg;
                }
                $result = imagejpeg($source_image, $output_path, $quality);
                break;
                
            case 'png':
                // PNG compression level (0-9, but GD uses 0-9 differently)
                // Lower quality = higher compression = smaller file size
                // For better compression, use higher compression level when quality is lower
                $compression = 9 - round(($quality / 100) * 9);
                // Ensure minimum compression of 6 for better file size reduction
                $compression = max(6, $compression);
                $result = imagepng($source_image, $output_path, $compression);
                break;
                
            case 'webp':
                // Try WebP Convert library first
                if ($this->webp_converter && $this->webp_converter->is_available()) {
                    imagedestroy($source_image);
                    $webp_result = $this->webp_converter->convert_to_webp($source_path, $output_path, $quality);
                    restore_error_handler();
                    if ($webp_result['success']) {
                        return array(
                            'path' => $webp_result['path'],
                            'url' => str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $webp_result['path']),
                            'size' => $webp_result['size'],
                            'format' => 'webp'
                        );
                    } else {
                        return array('error' => $webp_result['error']);
                    }
                } elseif (function_exists('imagewebp')) {
                    $result = imagewebp($source_image, $output_path, $quality);
                } else {
                    imagedestroy($source_image);
                    restore_error_handler();
                    return array(
                        'error' => 'WebP library not available. Please ensure the plugin includes the WebP Convert library.',
                        'error_code' => 'webp_not_supported'
                    );
                }
                break;
                
            case 'tiff':
            case 'tif':
                imagedestroy($source_image);
                restore_error_handler();
                // Use Imagick for TIFF
                if ($this->use_imagick) {
                    try {
                        $imagick = new Imagick($source_path);
                        if ($resize_width && $resize_height) {
                            $imagick->resizeImage($resize_width, $resize_height, Imagick::FILTER_LANCZOS, 1);
                        }
                        $imagick->setImageFormat('tiff');
                        $imagick->setImageCompressionQuality($quality);
                        $imagick->setImageCompression(Imagick::COMPRESSION_LZW);
                        $imagick->writeImage($output_path);
                        $imagick->clear();
                        $imagick->destroy();
                        return array(
                            'path' => $output_path,
                            'url' => str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $output_path),
                            'size' => filesize($output_path),
                            'format' => 'tiff'
                        );
                    } catch (Exception $e) {
                        return array('error' => 'TIFF conversion failed: ' . $e->getMessage());
                    }
                } else {
                    return array('error' => 'TIFF format requires Imagick extension');
                }
                break;
                
            case 'bmp':
                if (function_exists('imagebmp')) {
                    $result = imagebmp($source_image, $output_path);
                } else {
                    imagedestroy($source_image);
                    restore_error_handler();
                    return array('error' => 'BMP format not supported in this PHP version');
                }
                break;
                
            case 'gif':
                $result = imagegif($source_image, $output_path);
                break;
        }
        
        restore_error_handler();
        imagedestroy($source_image);
        
        if (!$result) {
            // Provide detailed error message
            $error_msg = 'Failed to save image';
            if ($last_error) {
                $error_msg .= ': ' . $last_error;
            }
            $error_msg .= '. Output path: ' . $output_path;
            
            // Check if file was partially created
            if (file_exists($output_path)) {
                $error_msg .= ' (File exists but may be corrupted)';
            } else {
                $error_msg .= ' (File was not created)';
            }
            
            return array(
                'error' => $error_msg,
                'error_code' => 'save_failed',
                'output_path' => $output_path,
                'directory_writable' => is_writable($output_dir),
                'disk_space' => disk_free_space($output_dir)
            );
        }
        
        return array(
            'path' => $output_path,
            'url' => str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $output_path),
            'size' => filesize($output_path),
            'format' => $target_format
        );
    }
    
    /**
     * Get cached conversion if exists
     */
    private function get_cached($source_path, $target_format, $quality) {
        $cache_key = md5($source_path . $target_format . $quality . filemtime($source_path));
        $cached_file = $this->cache_dir . '/' . $cache_key . '.' . $target_format;
        
        if (file_exists($cached_file)) {
            // Check if cache is still valid (24 hours)
            if (filemtime($cached_file) > (time() - 86400)) {
                return $cached_file;
            } else {
                // Delete old cache
                @unlink($cached_file);
            }
        }
        
        return false;
    }
    
    /**
     * Cache conversion result
     */
    private function cache_result($source_path, $target_format, $quality, $output_path) {
        $cache_key = md5($source_path . $target_format . $quality . filemtime($source_path));
        $cached_file = $this->cache_dir . '/' . $cache_key . '.' . $target_format;
        
        // Copy to cache
        @copy($output_path, $cached_file);
    }
    
    /**
     * Clear cache
     */
    public function clear_cache() {
        if (file_exists($this->cache_dir)) {
            $files = glob($this->cache_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }
    
    /**
     * Get cache size
     */
    public function get_cache_size() {
        $size = 0;
        if (file_exists($this->cache_dir)) {
            $files = glob($this->cache_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $size += filesize($file);
                }
            }
        }
        return $size;
    }
    
    /**
     * Enable/disable cache
     */
    public function set_cache_enabled($enabled) {
        $this->cache_enabled = $enabled;
    }
    
    /**
     * Check which image editor is being used
     */
    public function get_editor_info() {
        $webp_info = $this->get_webp_support_info();
        
        return array(
            'imagick_available' => $this->use_imagick,
            'gd_available' => function_exists('imagecreatefromjpeg'),
            'webp_support' => $webp_info['supported'],
            'webp_method' => $webp_info['method'],
            'webp_info' => $webp_info,
            'memory_limit' => ini_get('memory_limit'),
            'cache_enabled' => $this->cache_enabled,
            'php_version' => PHP_VERSION
        );
    }
}

