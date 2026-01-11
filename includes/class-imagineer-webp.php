<?php
/**
 * WebP Conversion using webp-convert library
 * Works without Imagick or GD WebP support
 */

if (!defined('ABSPATH')) {
    exit;
}

use WebPConvert\WebPConvert;

class Imagineer_WebP {
    
    public function __construct() {
        // Load composer autoloader if it exists
        $autoload_file = IC_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($autoload_file)) {
            require_once $autoload_file;
        }
    }
    
    /**
     * Check if WebP Convert library is available
     */
    public function is_available() {
        return class_exists('WebPConvert\WebPConvert');
    }
    
    /**
     * Convert image to WebP using webp-convert library
     */
    public function convert_to_webp($source_path, $destination_path, $quality = 80) {
        if (!$this->is_available()) {
            return array(
                'success' => false,
                'error' => 'WebP Convert library not available'
            );
        }
        
        try {
            $options = array(
                'quality' => $quality,
                'metadata' => 'none', // Strip metadata
                'converters' => array('gd', 'imagick', 'gmagick', 'cwebp', 'wpc', 'ewww'),
                'log-call-arguments' => false
            );
            
            WebPConvert::convert($source_path, $destination_path, $options);
            
            if (file_exists($destination_path)) {
                return array(
                    'success' => true,
                    'path' => $destination_path,
                    'size' => filesize($destination_path)
                );
            } else {
                return array(
                    'success' => false,
                    'error' => 'Conversion failed - output file not created'
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Convert WebP to other format (using multiple methods)
     */
    public function convert_from_webp($source_path, $target_format, $quality = 80, $output_path = null) {
        // Use provided output path, or generate one from source path
        if (!$output_path) {
            $output_path = preg_replace('/\.webp$/i', '.' . $target_format, $source_path);
        }
        
        // Ensure output directory exists
        $output_dir = dirname($output_path);
        if (!file_exists($output_dir)) {
            wp_mkdir_p($output_dir);
        }
        
        // Method 1: Try GD if available
        if (function_exists('imagecreatefromwebp')) {
            $source_image = @imagecreatefromwebp($source_path);
            if ($source_image) {
                $success = false;
                switch ($target_format) {
                    case 'jpg':
                    case 'jpeg':
                        // Remove transparency for JPEG
                        $width = imagesx($source_image);
                        $height = imagesy($source_image);
                        $jpeg_image = imagecreatetruecolor($width, $height);
                        $white = imagecolorallocate($jpeg_image, 255, 255, 255);
                        imagefill($jpeg_image, 0, 0, $white);
                        imagecopy($jpeg_image, $source_image, 0, 0, 0, 0, $width, $height);
                        imagedestroy($source_image);
                        $success = imagejpeg($jpeg_image, $output_path, $quality);
                        imagedestroy($jpeg_image);
                        break;
                    case 'png':
                        $compression = 9 - round(($quality / 100) * 9);
                        // Preserve transparency for PNG
                        imagealphablending($source_image, false);
                        imagesavealpha($source_image, true);
                        $success = imagepng($source_image, $output_path, $compression);
                        imagedestroy($source_image);
                        break;
                    case 'gif':
                        $success = imagegif($source_image, $output_path);
                        imagedestroy($source_image);
                        break;
                    case 'bmp':
                        if (function_exists('imagebmp')) {
                            $success = imagebmp($source_image, $output_path);
                            imagedestroy($source_image);
                        } else {
                            imagedestroy($source_image);
                            $success = false;
                        }
                        break;
                    default:
                        imagedestroy($source_image);
                        $success = false;
                }
                
                if ($success && file_exists($output_path)) {
                    return array(
                        'success' => true,
                        'path' => $output_path,
                        'size' => filesize($output_path)
                    );
                }
            }
        }
        
        // Method 2: Try Imagick if available
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            try {
                $imagick = new Imagick($source_path);
                
                // Handle format-specific settings
                switch ($target_format) {
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
                        // PNG compression level (0-9)
                        $compression = 9 - round(($quality / 100) * 9);
                        $imagick->setImageCompressionQuality($compression * 10);
                        break;
                    case 'gif':
                        $imagick->setImageFormat('gif');
                        break;
                    case 'bmp':
                        $imagick->setImageFormat('bmp');
                        break;
                    case 'tiff':
                    case 'tif':
                        $imagick->setImageFormat('tiff');
                        $imagick->setImageCompression(Imagick::COMPRESSION_LZW);
                        break;
                    default:
                        $imagick->setImageFormat($target_format);
                }
                
                $imagick->setImageCompressionQuality($quality);
                $imagick->writeImage($output_path);
                $imagick->clear();
                $imagick->destroy();
                
                if (file_exists($output_path)) {
                    return array(
                        'success' => true,
                        'path' => $output_path,
                        'size' => filesize($output_path)
                    );
                }
            } catch (Exception $e) {
                error_log('Imagineer: Imagick WebP conversion failed: ' . $e->getMessage());
                // Continue to next method
            }
        }
        
        // Method 3: Use WebP Convert library (convert WebP to PNG first, then to target)
        if ($this->is_available() && class_exists('WebPConvert\WebPConvert')) {
            try {
                // First convert WebP to PNG using reverse conversion
                $temp_png = sys_get_temp_dir() . '/webp_temp_' . uniqid() . '.png';
                
                // Read WebP as image data and save as PNG
                $image_data = @file_get_contents($source_path);
                if ($image_data) {
                    // Create a temporary PNG using basic image operations
                    // This is a workaround: use exec if dwebp is available
                    $dwebp_path = $this->find_dwebp_binary();
                    
                    if ($dwebp_path) {
                        // Use dwebp binary to convert WebP to PNG
                        exec(escapeshellarg($dwebp_path) . ' ' . escapeshellarg($source_path) . ' -o ' . escapeshellarg($temp_png) . ' 2>&1', $output, $return_code);
                        
                        if ($return_code === 0 && file_exists($temp_png)) {
                            // Now convert PNG to target format using GD
                            $source_image = imagecreatefrompng($temp_png);
                            @unlink($temp_png);
                            
                            if ($source_image) {
                                $success = false;
                                switch ($target_format) {
                                    case 'jpg':
                                    case 'jpeg':
                                        // Remove transparency for JPEG
                                        $width = imagesx($source_image);
                                        $height = imagesy($source_image);
                                        $jpeg_image = imagecreatetruecolor($width, $height);
                                        $white = imagecolorallocate($jpeg_image, 255, 255, 255);
                                        imagefill($jpeg_image, 0, 0, $white);
                                        imagecopy($jpeg_image, $source_image, 0, 0, 0, 0, $width, $height);
                                        imagedestroy($source_image);
                                        $success = imagejpeg($jpeg_image, $output_path, $quality);
                                        imagedestroy($jpeg_image);
                                        break;
                                    case 'png':
                                        $compression = 9 - round(($quality / 100) * 9);
                                        imagealphablending($source_image, false);
                                        imagesavealpha($source_image, true);
                                        $success = imagepng($source_image, $output_path, $compression);
                                        imagedestroy($source_image);
                                        break;
                                    case 'gif':
                                        $success = imagegif($source_image, $output_path);
                                        imagedestroy($source_image);
                                        break;
                                    case 'bmp':
                                        if (function_exists('imagebmp')) {
                                            $success = imagebmp($source_image, $output_path);
                                            imagedestroy($source_image);
                                        } else {
                                            imagedestroy($source_image);
                                            $success = false;
                                        }
                                        break;
                                    default:
                                        imagedestroy($source_image);
                                        $success = false;
                                }
                                
                                if ($success && file_exists($output_path)) {
                                    return array(
                                        'success' => true,
                                        'path' => $output_path,
                                        'size' => filesize($output_path)
                                    );
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Continue to error
            }
        }
        
        return array(
            'success' => false,
            'error' => 'Cannot read WebP files on this server. WebP reading requires GD with WebP support or Imagick. You can convert TO WebP, but not FROM WebP.'
        );
    }
    
    /**
     * Find dwebp binary on system
     */
    private function find_dwebp_binary() {
        $possible_paths = array(
            '/usr/bin/dwebp',
            '/usr/local/bin/dwebp',
            '/opt/local/bin/dwebp',
            'dwebp' // Try PATH
        );
        
        foreach ($possible_paths as $path) {
            if (@is_executable($path)) {
                return $path;
            }
        }
        
        // Try to find in PATH
        exec('which dwebp 2>/dev/null', $output, $return_code);
        if ($return_code === 0 && !empty($output[0])) {
            return $output[0];
        }
        
        return false;
    }
    
    /**
     * Get conversion method used
     */
    public function get_conversion_info() {
        $methods = array();
        
        if (function_exists('imagewebp')) {
            $methods[] = 'GD WebP';
        }
        
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            try {
                $imagick = new Imagick();
                $formats = $imagick->queryFormats();
                if (in_array('WEBP', $formats)) {
                    $methods[] = 'Imagick WebP';
                }
            } catch (Exception $e) {
                // Ignore
            }
        }
        
        if ($this->is_available()) {
            $methods[] = 'WebP Convert Library';
        }
        
        return array(
            'available' => !empty($methods),
            'methods' => $methods,
            'primary_method' => !empty($methods) ? $methods[0] : 'None'
        );
    }
}

