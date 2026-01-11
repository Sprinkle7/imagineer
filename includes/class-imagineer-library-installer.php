<?php
/**
 * Library Installer - Downloads and installs WebP Convert library
 * Works without Composer - downloads directly from GitHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagineer_Library_Installer {
    
    private $library_version = '2.9.0';
    private $github_repo = 'rosell-dk/webp-convert';
    private $vendor_dir;
    
    public function __construct() {
        $this->vendor_dir = IC_PLUGIN_DIR . 'vendor';
    }
    
    /**
     * Check if library is installed
     */
    public function is_installed() {
        return file_exists($this->vendor_dir . '/autoload.php') && 
               file_exists($this->vendor_dir . '/rosell-dk/webp-convert');
    }
    
    /**
     * Install library from GitHub
     */
    public function install() {
        // Check if already installed
        if ($this->is_installed()) {
            return array(
                'success' => true,
                'message' => __('WebP Convert library is already installed.', 'imagineer'),
                'already_installed' => true
            );
        }
        
        // Check if we can write to plugin directory
        if (!is_writable(IC_PLUGIN_DIR)) {
            return array(
                'success' => false,
                'message' => __('Plugin directory is not writable. Please check file permissions.', 'imagineer'),
                'error_code' => 'not_writable'
            );
        }
        
        // Create vendor directory
        if (!file_exists($this->vendor_dir)) {
            if (!wp_mkdir_p($this->vendor_dir)) {
                return array(
                    'success' => false,
                    'message' => __('Could not create vendor directory.', 'imagineer'),
                    'error_code' => 'mkdir_failed'
                );
            }
        }
        
        // Download library ZIP from GitHub
        $zip_url = "https://github.com/{$this->github_repo}/archive/refs/tags/{$this->library_version}.zip";
        $temp_file = download_url($zip_url);
        
        if (is_wp_error($temp_file)) {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Failed to download library: %s. Please check your internet connection or try manual installation.', 'imagineer'),
                    $temp_file->get_error_message()
                ),
                'error_code' => 'download_failed',
                'error_details' => $temp_file->get_error_message()
            );
        }
        
        // Extract ZIP file
        $zip = new ZipArchive();
        $zip_result = $zip->open($temp_file);
        
        if ($zip_result !== TRUE) {
            @unlink($temp_file);
            return array(
                'success' => false,
                'message' => __('Failed to open downloaded ZIP file.', 'imagineer'),
                'error_code' => 'zip_open_failed'
            );
        }
        
        // Extract to temporary location first
        $temp_extract = get_temp_dir() . 'imagineer-webp-convert-' . time();
        if (!wp_mkdir_p($temp_extract)) {
            $zip->close();
            @unlink($temp_file);
            return array(
                'success' => false,
                'message' => __('Could not create temporary extraction directory.', 'imagineer'),
                'error_code' => 'extract_dir_failed'
            );
        }
        
        $extract_result = $zip->extractTo($temp_extract);
        $zip->close();
        @unlink($temp_file);
        
        if (!$extract_result) {
            $this->delete_directory($temp_extract);
            return array(
                'success' => false,
                'message' => __('Failed to extract library files.', 'imagineer'),
                'error_code' => 'extract_failed'
            );
        }
        
        // Find the extracted directory (GitHub ZIPs have format: webp-convert-{version})
        $extracted_dirs = glob($temp_extract . '/webp-convert-*');
        if (empty($extracted_dirs)) {
            // Try alternative pattern
            $extracted_dirs = glob($temp_extract . '/*');
            $extracted_dirs = array_filter($extracted_dirs, 'is_dir');
        }
        
        if (empty($extracted_dirs)) {
            $this->delete_directory($temp_extract);
            return array(
                'success' => false,
                'message' => __('Unexpected ZIP structure. Please try manual installation.', 'imagineer'),
                'error_code' => 'unexpected_structure'
            );
        }
        
        $extracted_dir = reset($extracted_dirs); // Get first directory
        
        // Move to vendor directory
        $target_dir = $this->vendor_dir . '/rosell-dk/webp-convert';
        if (file_exists($target_dir)) {
            $this->delete_directory($target_dir);
        }
        
        if (!wp_mkdir_p($this->vendor_dir . '/rosell-dk')) {
            $this->delete_directory($temp_extract);
            return array(
                'success' => false,
                'message' => __('Could not create vendor subdirectory.', 'imagineer'),
                'error_code' => 'vendor_subdir_failed'
            );
        }
        
        // Copy files
        if (!$this->copy_directory($extracted_dir, $target_dir)) {
            $this->delete_directory($temp_extract);
            return array(
                'success' => false,
                'message' => __('Failed to copy library files to vendor directory.', 'imagineer'),
                'error_code' => 'copy_failed'
            );
        }
        
        // Clean up temp directory
        $this->delete_directory($temp_extract);
        
        // Install dependencies (rosell-dk packages)
        $this->install_dependencies();
        
        // Create autoload.php
        $this->create_autoload();
        
        // Verify installation
        if ($this->is_installed()) {
            return array(
                'success' => true,
                'message' => __('WebP Convert library installed successfully! Enhanced WebP support is now available.', 'imagineer')
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Library files were copied but verification failed. Please try again.', 'imagineer'),
                'error_code' => 'verification_failed'
            );
        }
    }
    
    /**
     * Install dependencies (other rosell-dk packages)
     */
    private function install_dependencies() {
        $dependencies = array(
            'rosell-dk/exec-with-fallback' => 'https://github.com/rosell-dk/exec-with-fallback/archive/refs/heads/master.zip',
            'rosell-dk/file-util' => 'https://github.com/rosell-dk/file-util/archive/refs/heads/master.zip',
            'rosell-dk/image-mime-type-guesser' => 'https://github.com/rosell-dk/image-mime-type-guesser/archive/refs/heads/master.zip',
            'rosell-dk/image-mime-type-sniffer' => 'https://github.com/rosell-dk/image-mime-type-sniffer/archive/refs/heads/master.zip',
            'rosell-dk/locate-binaries' => 'https://github.com/rosell-dk/locate-binaries/archive/refs/heads/master.zip',
        );
        
        foreach ($dependencies as $package => $url) {
            $package_dir = $this->vendor_dir . '/' . $package;
            
            // Skip if already installed
            if (file_exists($package_dir)) {
                continue;
            }
            
            // Download and extract
            $temp_file = download_url($url);
            if (is_wp_error($temp_file)) {
                continue; // Skip failed dependencies
            }
            
            $zip = new ZipArchive();
            if ($zip->open($temp_file) === TRUE) {
                $temp_extract = get_temp_dir() . 'imagineer-dep-' . time() . '-' . basename($package);
                wp_mkdir_p($temp_extract);
                
                if ($zip->extractTo($temp_extract)) {
                    $zip->close();
                    $extracted_dirs = glob($temp_extract . '/*');
                    if (!empty($extracted_dirs)) {
                        $extracted_dir = $extracted_dirs[0];
                        $package_parts = explode('/', $package);
                        $target_base = $this->vendor_dir . '/' . $package_parts[0];
                        wp_mkdir_p($target_base);
                        $this->copy_directory($extracted_dir, $package_dir);
                    }
                    $this->delete_directory($temp_extract);
                } else {
                    $zip->close();
                }
                @unlink($temp_file);
            }
        }
    }
    
    /**
     * Create autoload.php file
     */
    private function create_autoload() {
        $autoload_file = $this->vendor_dir . '/autoload.php';
        
        $autoload_content = <<<'PHP'
<?php
/**
 * Autoloader for Imagineer WebP Convert Library
 * Auto-generated by Imagineer Library Installer
 */

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(function ($class) {
    // Project-specific namespace prefix
    $prefix = 'WebPConvert\\';
    
    // Base directory for the namespace prefix
    $base_dir = __DIR__ . '/rosell-dk/webp-convert/src/';
    
    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Load other dependencies
$deps = array(
    'exec-with-fallback' => 'ExecWithFallback',
    'file-util' => 'FileUtil',
    'image-mime-type-guesser' => 'ImageMimeTypeGuesser',
    'image-mime-type-sniffer' => 'ImageMimeTypeSniffer',
    'locate-binaries' => 'LocateBinaries',
);

foreach ($deps as $dir => $namespace) {
    $dep_dir = __DIR__ . '/rosell-dk/' . $dir . '/src/';
    if (file_exists($dep_dir)) {
        spl_autoload_register(function ($class) use ($dep_dir, $namespace) {
            if (strpos($class, $namespace . '\\') === 0) {
                $relative_class = substr($class, strlen($namespace) + 1);
                $file = $dep_dir . str_replace('\\', '/', $relative_class) . '.php';
                if (file_exists($file)) {
                    require $file;
                }
            }
        });
    }
}
PHP;
        
        file_put_contents($autoload_file, $autoload_content);
    }
    
    /**
     * Copy directory recursively
     */
    private function copy_directory($source, $destination) {
        if (!is_dir($source)) {
            return false;
        }
        
        if (!is_dir($destination)) {
            if (!wp_mkdir_p($destination)) {
                return false;
            }
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $dest_path = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($dest_path)) {
                    wp_mkdir_p($dest_path);
                }
            } else {
                copy($item, $dest_path);
            }
        }
        
        return true;
    }
    
    /**
     * Delete directory recursively
     */
    private function delete_directory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Get installation status
     */
    public function get_status() {
        $installed = $this->is_installed();
        $writable = is_writable(IC_PLUGIN_DIR);
        
        return array(
            'installed' => $installed,
            'writable' => $writable,
            'vendor_dir' => $this->vendor_dir,
            'vendor_exists' => file_exists($this->vendor_dir),
            'can_install' => $writable && !$installed
        );
    }
}
