<?php
/**
 * Admin interface for Image Converter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagineer_Admin {
    
    private $core;
    
    public function __construct() {
        $this->core = new Imagineer_Core();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Format file size for display
     */
    private function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Imagineer', 'imagineer'),
            __('Imagineer', 'imagineer'),
            'upload_files',
            'imagineer',
            array($this, 'render_admin_page'),
            'dashicons-format-image',
            30
        );
        
        // Add settings submenu for Pro
        if ($this->core->is_pro_active()) {
            add_submenu_page(
                'imagineer',
                __('Settings', 'imagineer'),
                __('Settings', 'imagineer'),
                'manage_options',
                'imagineer-settings',
                array($this, 'render_settings_page')
            );
        }
        
        
        // Add Media Library converter (Pro)
        if ($this->core->is_pro_active()) {
            add_submenu_page(
                'imagineer',
                __('Media Library Converter', 'imagineer'),
                __('Media Library', 'imagineer'),
                'upload_files',
                'imagineer-media-library',
                array($this, 'render_media_library_page')
            );
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_imagineer') {
            return;
        }
        
        wp_enqueue_style(
            'ic-admin-style',
            IC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            IC_VERSION
        );
        
        wp_enqueue_script(
            'ic-admin-script',
            IC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            IC_VERSION,
            true
        );
        
        wp_localize_script('ic-admin-script', 'icData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ic_convert_nonce'),
            'isPro' => $this->core->is_pro_active(),
            'maxFileSize' => $this->core->get_max_file_size(),
            'capabilities' => $this->core->get_conversion_capabilities(),
            'strings' => array(
                'selectFormat' => __('Select target format', 'imagineer'),
                'uploading' => __('Uploading...', 'imagineer'),
                'converting' => __('Converting...', 'imagineer'),
                'success' => __('Conversion successful!', 'imagineer'),
                'downloaded' => __('Downloaded!', 'imagineer'),
                'error' => __('An error occurred.', 'imagineer'),
                'upgradeRequired' => __('This feature requires Pro version.', 'imagineer'),
                'fileTooLarge' => __('File size exceeds maximum allowed size.', 'imagineer'),
                'download' => __('Download', 'imagineer')
            )
        ));
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Handle clear history
        if (isset($_POST['ic_clear_history']) && check_admin_referer('ic_clear_history')) {
            delete_option('ic_recent_conversions');
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Conversion history cleared!', 'imagineer') . '</p></div>';
        }
        
        // Handle export statistics
        if (isset($_POST['ic_export_stats']) && check_admin_referer('ic_clear_history')) {
            $this->export_statistics();
            return;
        }
        
        $is_pro = $this->core->is_pro_active();
        $capabilities = $this->core->get_conversion_capabilities();
        
        // Get stats
        global $wpdb;
        $table_name = $wpdb->prefix . 'ic_conversion_history';
        $total_conversions = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $total_saved = $wpdb->get_var("SELECT SUM(original_size - converted_size) FROM {$table_name}");
        $today_conversions = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE DATE(converted_at) = %s", current_time('Y-m-d')));
        $formats = $wpdb->get_results("SELECT target_format, COUNT(*) as count FROM {$table_name} GROUP BY target_format ORDER BY count DESC LIMIT 3");
        
        ?>
        <div class="wrap ic-admin-wrap">
            <div class="ic-header-section">
                <div>
                    <h1 class="ic-main-title"><?php echo esc_html(get_admin_page_title()); ?></h1>
                    <p class="ic-subtitle"><?php _e('Convert and optimize images in seconds', 'imagineer'); ?></p>
                </div>
                <?php if ($is_pro): ?>
                <div class="ic-pro-badge">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M10 1l2.928 6.472L20 8.472l-5 5.164 1.18 7.364L10 17.472 3.82 21l1.18-7.364-5-5.164 7.072-1 L10 1z" fill="currentColor"/>
                    </svg>
                    <?php _e('PRO', 'imagineer'); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Stats Cards -->
            <div class="ic-stats-grid">
                <div class="ic-stat-card ic-stat-primary">
                    <div class="ic-stat-icon">
                        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                            <path d="M16 4v24M4 16h24" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                            <circle cx="16" cy="16" r="12" stroke="currentColor" stroke-width="2" fill="none"/>
                        </svg>
                    </div>
                    <div class="ic-stat-content">
                        <div class="ic-stat-value"><?php echo number_format($total_conversions); ?></div>
                        <div class="ic-stat-label"><?php _e('Total Conversions', 'imagineer'); ?></div>
                    </div>
                </div>
                
                <div class="ic-stat-card ic-stat-success">
                    <div class="ic-stat-icon">
                        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                            <path d="M20 12l-8 8-4-4" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="4" y="4" width="24" height="24" rx="4" stroke="currentColor" stroke-width="2" fill="none"/>
                        </svg>
                    </div>
                    <div class="ic-stat-content">
                        <div class="ic-stat-value"><?php echo $this->format_file_size($total_saved); ?></div>
                        <div class="ic-stat-label"><?php _e('Space Saved', 'imagineer'); ?></div>
                    </div>
                </div>
                
                <div class="ic-stat-card ic-stat-info">
                    <div class="ic-stat-icon">
                        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                            <circle cx="16" cy="16" r="12" stroke="currentColor" stroke-width="2" fill="none"/>
                            <path d="M16 10v6l4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="ic-stat-content">
                        <div class="ic-stat-value"><?php echo number_format($today_conversions); ?></div>
                        <div class="ic-stat-label"><?php _e('Today', 'imagineer'); ?></div>
                    </div>
                </div>
                
                <div class="ic-stat-card ic-stat-warning">
                    <div class="ic-stat-icon">
                        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                            <path d="M4 8h24M4 16h24M4 24h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="ic-stat-content">
                        <div class="ic-stat-value"><?php echo $formats[0]->target_format ?? 'N/A'; ?></div>
                        <div class="ic-stat-label"><?php _e('Top Format', 'imagineer'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Conversion Presets -->
            <?php Imagineer_Presets::render_preset_selector(); ?>
            
            <!-- Support Banner -->
            <div class="ic-coffee-banner" style="background: linear-gradient(135deg, #ff813f 0%, #ff4b2b 100%); color: white; padding: 24px 28px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(255, 75, 43, 0.25); text-align: center;">
                <h3 style="color: white; margin: 0 0 12px 0; font-size: 20px;">☕ Enjoying Imagineer?</h3>
                <p style="margin: 0 0 8px 0; opacity: 0.95; font-size: 14px;">All features are completely free! If you find this plugin helpful, consider buying me a coffee to support continued development.</p>
                <p style="margin: 0 0 16px 0; opacity: 0.9; font-size: 13px;">📧 Feedback or Support: <a href="mailto:allauddinyousafxai@gmail.com" style="color: white; text-decoration: underline;">allauddinyousafxai@gmail.com</a></p>
                <a href="https://www.buymeacoffee.com/adusafxai" target="_blank" class="button button-large" style="background: white; color: #ff4b2b; border: none; padding: 12px 32px; font-weight: 600; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s ease;">
                    <span style="font-size: 20px;">☕</span>
                    <span>Buy Me a Coffee</span>
                </a>
            </div>
            
            <?php if (false): // Hidden upgrade banner ?>
            <div style="display: none;">
                    <div style="text-align: center; margin-top: 30px;">
                        <a href="https://codecanyon.net/" target="_blank" class="button button-primary button-hero">Get Pro Now - $29</a>
                        <p style="margin-top: 15px; color: #666;"><small>One-time payment. Lifetime updates. 30-day money-back guarantee.</small></p>
                    </div>
                </div>
            </div>
            <?php 
            endif;
            
            // Handle Pro test activation
            if (isset($_POST['ic_enable_pro_test']) && check_admin_referer('ic_enable_pro_test')) {
                update_option('ic_is_pro', true);
                update_option('ic_license_key', 'TEST-' . time());
                echo '<div class="notice notice-success"><p><strong>Pro Mode Enabled!</strong> Refresh the page to see Pro features.</p></div>';
                echo '<meta http-equiv="refresh" content="2">';
            }
            ?>
            
            <?php 
            // Show performance info
            if (class_exists('Imagineer_Optimizer')) {
                $optimizer = new Imagineer_Optimizer();
                $editor_info = $optimizer->get_editor_info();
            } else {
                $editor_info = array(
                    'imagick_available' => extension_loaded('imagick'),
                    'gd_available' => function_exists('imagecreatefromjpeg'),
                    'webp_support' => function_exists('imagewebp'),
                    'memory_limit' => ini_get('memory_limit'),
                    'cache_enabled' => false
                );
            }
            ?>
            
            <!-- <div class="ic-performance-info" style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <h3 style="margin-top: 0;">⚡ Performance Status</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <strong>Image Editor:</strong> 
                        <?php echo $editor_info['imagick_available'] ? '✅ Imagick (Fastest)' : ($editor_info['gd_available'] ? '✅ GD Library' : '❌ None'); ?>
                    </div>
                    <div>
                        <strong>WebP Support:</strong> 
                        <?php 
                        if (isset($editor_info['webp_support']) && $editor_info['webp_support']) {
                            echo '✅ Enabled (' . (isset($editor_info['webp_method']) ? $editor_info['webp_method'] : 'GD/Imagick') . ')';
                        } else {
                            echo '❌ Not Available';
                            if (isset($editor_info['webp_info']['message'])) {
                                echo '<br><small style="color: #666;">' . esc_html($editor_info['webp_info']['message']) . '</small>';
                            }
                        }
                        ?>
                    </div>
                    <div>
                        <strong>Cache:</strong> 
                        <?php echo $editor_info['cache_enabled'] ? '✅ Enabled' : '❌ Disabled'; ?>
                    </div>
                    <div>
                        <strong>Memory Limit:</strong> 
                        <?php echo esc_html($editor_info['memory_limit']); ?>
                    </div>
                </div>
            </div> -->
            
            <div class="ic-converter-container">
                <div class="ic-upload-area" id="ic-upload-area">
                    <div class="ic-upload-content">
                        <svg class="ic-upload-icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        <h3><?php _e('Drag & Drop Images Here', 'imagineer'); ?></h3>
                        <p><?php _e('or click to browse', 'imagineer'); ?></p>
                        <input type="file" id="ic-file-input" accept="image/png,image/jpeg,image/jpg,image/webp" <?php echo $capabilities['bulk_processing'] ? 'multiple' : ''; ?>>
                        <p class="ic-file-info">
                            <?php 
                            printf(
                                __('Max file size: %s | %s', 'imagineer'),
                                size_format($this->core->get_max_file_size()),
                                $capabilities['bulk_processing'] ? __('Multiple files supported', 'imagineer') : __('Single file only', 'imagineer')
                            );
                            ?>
                        </p>
                    </div>
                </div>
                
                <div class="ic-controls">
                    <div class="ic-format-select">
                        <label for="ic-target-format"><?php _e('Convert to:', 'imagineer'); ?></label>
                        <select id="ic-target-format">
                            <option value="jpg">JPG</option>
                            <option value="png">PNG</option>
                            <?php 
                            // Only show WebP if supported
                            if (class_exists('Imagineer_Optimizer')) {
                                $optimizer = new Imagineer_Optimizer();
                                $webp_supported = $optimizer->is_webp_supported();
                                if ($webp_supported) {
                                    echo '<option value="webp">WEBP</option>';
                                } else {
                                    echo '<option value="webp" disabled>WEBP (Not Available)</option>';
                                }
                            } else {
                                // Fallback check
                                if (function_exists('imagewebp') || (extension_loaded('imagick') && class_exists('Imagick'))) {
                                    echo '<option value="webp">WEBP</option>';
                                } else {
                                    echo '<option value="webp" disabled>WEBP (Not Available)</option>';
                                }
                            }
                            ?>
                            <option value="tiff">TIFF</option>
                            <option value="bmp">BMP</option>
                            <option value="gif">GIF</option>
                        </select>
                    </div>
                    
                    <div class="ic-quality-control">
                        <label for="ic-quality"><?php _e('Quality:', 'imagineer'); ?></label>
                        <?php if ($capabilities['advanced_quality']): ?>
                            <input type="range" id="ic-quality" min="1" max="100" value="80">
                            <span id="ic-quality-value">80</span>
                        <?php else: ?>
                            <select id="ic-quality">
                                <option value="60"><?php _e('Low', 'imagineer'); ?></option>
                                <option value="80" selected><?php _e('Medium', 'imagineer'); ?></option>
                                <option value="95"><?php _e('High', 'imagineer'); ?></option>
                            </select>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($is_pro): ?>
                    <div class="ic-resize-control">
                        <label><?php _e('Resize:', 'imagineer'); ?></label>
                        <input type="number" id="ic-resize-width" placeholder="Width" min="1" style="width: 80px;">
                        <span>×</span>
                        <input type="number" id="ic-resize-height" placeholder="Height" min="1" style="width: 80px;">
                        <small style="display: block; color: #666;">Leave empty to keep original size</small>
                    </div>
                    <?php endif; ?>
                    
                    <button type="button" id="ic-convert-btn" class="button button-primary button-large">
                        <?php _e('Convert', 'imagineer'); ?>
                    </button>
                </div>
                
                <div class="ic-results" id="ic-results"></div>
                
                <div class="ic-progress" id="ic-progress" style="display: none;">
                    <div class="ic-progress-bar">
                        <div class="ic-progress-fill" id="ic-progress-fill"></div>
                    </div>
                    <p class="ic-progress-text" id="ic-progress-text"></p>
                </div>
            </div>
            
            <?php if ($is_pro): 
                // Get statistics
                $stats = array(
                    'total_conversions' => get_option('ic_total_conversions', 0),
                    'total_space_saved' => get_option('ic_total_space_saved', 0),
                    'space_saved_formatted' => size_format(get_option('ic_total_space_saved', 0)),
                    'total_uploads' => get_option('ic_total_uploads', 0),
                    'total_downloads' => get_option('ic_total_downloads', 0)
                );
                
                // Get recent conversions
                $recent_conversions = get_option('ic_recent_conversions', array());
                $recent_conversions = array_slice($recent_conversions, -5); // Last 5
            ?>
            <div class="ic-pro-features">
                <h2><?php _e('Statistics & History', 'imagineer'); ?></h2>
                
                <div class="ic-stats-grid">
                    <!-- Total Conversions - Blue -->
                    <div class="ic-stat-card ic-stat-primary">
                        <div class="ic-stat-icon">
                            <span class="dashicons dashicons-image-rotate" style="font-size: 24px; width: auto; height: auto;"></span>
                        </div>
                        <div class="ic-stat-content">
                            <div class="ic-stat-value"><?php echo number_format($stats['total_conversions']); ?></div>
                            <div class="ic-stat-label"><?php _e('Total Conversions', 'imagineer'); ?></div>
                        </div>
                    </div>

                    <!-- Space Saved - Green -->
                    <div class="ic-stat-card ic-stat-success">
                        <div class="ic-stat-icon">
                            <span class="dashicons dashicons-cloud-saved" style="font-size: 24px; width: auto; height: auto;"></span>
                        </div>
                        <div class="ic-stat-content">
                            <div class="ic-stat-value"><?php echo esc_html($stats['space_saved_formatted']); ?></div>
                            <div class="ic-stat-label"><?php _e('Space Saved', 'imagineer'); ?></div>
                        </div>
                    </div>

                    <!-- Files Processed - Cyan -->
                    <div class="ic-stat-card ic-stat-info">
                        <div class="ic-stat-icon">
                            <span class="dashicons dashicons-images-alt2" style="font-size: 24px; width: auto; height: auto;"></span>
                        </div>
                        <div class="ic-stat-content">
                            <div class="ic-stat-value"><?php echo number_format($stats['total_uploads']); ?></div>
                            <div class="ic-stat-label"><?php _e('Files Processed', 'imagineer'); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Conversion Trends Chart -->
                <div class="ic-chart-section" style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h3><?php _e('Format Distribution', 'imagineer'); ?></h3>
                    <?php 
                    // Calculate format distribution
                    $format_counts = array();
                    foreach ($recent_conversions as $conv) {
                        $key = strtoupper($conv['from']) . ' → ' . strtoupper($conv['to']);
                        if (!isset($format_counts[$key])) {
                            $format_counts[$key] = 0;
                        }
                        $format_counts[$key]++;
                    }
                    
                    if (!empty($format_counts)):
                        arsort($format_counts);
                        $max_count = max($format_counts);
                    ?>
                    <div style="margin-top: 15px;">
                        <?php foreach ($format_counts as $format => $count): 
                            $percentage = ($count / $max_count) * 100;
                        ?>
                        <div style="margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span><strong><?php echo esc_html($format); ?></strong></span>
                                <span><?php echo $count; ?> conversion<?php echo $count > 1 ? 's' : ''; ?></span>
                            </div>
                            <div style="background: #e0e0e0; height: 20px; border-radius: 10px; overflow: hidden;">
                                <div style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 20px; width: <?php echo $percentage; ?>%;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p style="color: #666; font-style: italic;"><?php _e('No conversions yet. Start converting to see statistics!', 'imagineer'); ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($recent_conversions)): ?>
                <div class="ic-history-section">
                    <h3><?php _e('Recent Conversions', 'imagineer'); ?></h3>
                    <table class="ic-history-table">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'imagineer'); ?></th>
                                <th><?php _e('File', 'imagineer'); ?></th>
                                <th><?php _e('From → To', 'imagineer'); ?></th>
                                <th><?php _e('Original', 'imagineer'); ?></th>
                                <th><?php _e('Converted', 'imagineer'); ?></th>
                                <th><?php _e('Saved', 'imagineer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($recent_conversions) as $conversion): ?>
                            <tr>
                                <td><?php echo esc_html($conversion['date']); ?></td>
                                <td><?php echo esc_html($conversion['filename']); ?></td>
                                <td><?php echo strtoupper($conversion['from']) . ' → ' . strtoupper($conversion['to']); ?></td>
                                <td><?php echo size_format($conversion['original_size']); ?></td>
                                <td><?php echo size_format($conversion['converted_size']); ?></td>
                                <td style="color: <?php echo $conversion['space_saved'] > 0 ? 'green' : 'red'; ?>">
                                    <?php echo $conversion['space_saved'] > 0 ? '-' : '+'; ?>
                                    <?php echo size_format(abs($conversion['space_saved'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <form method="post" style="margin-top: 15px;">
                        <?php wp_nonce_field('ic_clear_history'); ?>
                        <button type="submit" name="ic_clear_history" class="button" onclick="return confirm('Clear all conversion history?')">
                            <?php _e('Clear History', 'imagineer'); ?>
                        </button>
                        <button type="submit" name="ic_export_stats" class="button button-primary" style="margin-left: 10px;">
                            <?php _e('Export Statistics (CSV)', 'imagineer'); ?>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="ic-features-grid">
                    <div class="ic-feature-card">
                        <h3>🔄 Media Library</h3>
                        <p>Convert directly from WordPress Media Library</p>
                    </div>
                    <div class="ic-feature-card">
                        <h3>🛒 WooCommerce</h3>
                        <p>Auto-optimize product images</p>
                    </div>
                    <div class="ic-feature-card">
                        <h3>⚙️ Automation</h3>
                        <p>Schedule and automate conversions</p>
                    </div>
                    <div class="ic-feature-card">
                        <h3>🔌 REST API</h3>
                        <p>Developer-friendly API access</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Handle form submission
        if (isset($_POST['ic_save_settings']) && check_admin_referer('ic_settings_nonce')) {
            update_option('ic_woo_auto_convert', isset($_POST['ic_woo_auto_convert']));
            update_option('ic_woo_auto_optimize', isset($_POST['ic_woo_auto_optimize']));
            update_option('ic_auto_compress', isset($_POST['ic_auto_compress']));
            update_option('ic_auto_compress_size', intval($_POST['ic_auto_compress_size']));
            update_option('ic_auto_convert_png', isset($_POST['ic_auto_convert_png']));
            update_option('ic_auto_convert_jpg', isset($_POST['ic_auto_convert_jpg']));
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved!', 'imagineer') . '</p></div>';
        }
        
        $woo_auto_convert = get_option('ic_woo_auto_convert', false);
        $woo_auto_optimize = get_option('ic_woo_auto_optimize', false);
        $auto_compress = get_option('ic_auto_compress', false);
        $auto_compress_size = get_option('ic_auto_compress_size', 500 * 1024);
        
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
        ?>
        <div class="wrap imagineer-settings-wrap">
            <h1><?php _e('Imagineer Pro Settings', 'imagineer'); ?></h1>
            
            <nav class="nav-tab-wrapper" style="margin: 20px 0;">
                <a href="?page=imagineer-settings&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    ⚙️ <?php _e('Settings', 'imagineer'); ?>
                </a>
                <a href="?page=imagineer-settings&tab=shortcodes" class="nav-tab <?php echo $active_tab === 'shortcodes' ? 'nav-tab-active' : ''; ?>">
                    📝 <?php _e('Shortcodes', 'imagineer'); ?>
                </a>
            </nav>
            
            <?php if ($active_tab === 'settings'): ?>
            <div class="ic-settings-section">
                <form method="post" action="">
                    <?php wp_nonce_field('ic_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('WooCommerce Integration', 'imagineer'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="ic_woo_auto_convert" value="1" <?php checked($woo_auto_convert); ?>>
                                    <?php _e('Auto-convert product images to WEBP', 'imagineer'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="ic_woo_auto_optimize" value="1" <?php checked($woo_auto_optimize); ?>>
                                    <?php _e('Auto-optimize product thumbnails', 'imagineer'); ?>
                                </label>
                                
                                <?php if (class_exists('WooCommerce')): 
                                    $woo_conversions = get_option('ic_woo_conversions', 0);
                                    $woo_space_saved = get_option('ic_woo_space_saved', 0);
                                ?>
                                <div style="margin-top: 15px; padding: 15px; background: #f0f9ff; border-radius: 6px; border: 1px solid #bae6fd;">
                                    <strong><?php _e('WooCommerce Stats:', 'imagineer'); ?></strong><br>
                                    <small>
                                        <?php printf(__('Product images converted: %d', 'imagineer'), $woo_conversions); ?><br>
                                        <?php printf(__('Space saved: %s', 'imagineer'), size_format($woo_space_saved)); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Auto Compression', 'imagineer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ic_auto_compress" value="1" <?php checked($auto_compress); ?>>
                                <?php _e('Auto-compress images on upload', 'imagineer'); ?>
                            </label>
                            <p class="description">
                                <label>
                                    <?php _e('Compress images larger than:', 'imagineer'); ?>
                                    <input type="number" name="ic_auto_compress_size" value="<?php echo esc_attr($auto_compress_size); ?>" min="100000" step="10000" style="width: 150px;">
                                    <?php _e('bytes (e.g., 500000 = 500KB)', 'imagineer'); ?>
                                </label>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Conversion Rules', 'imagineer'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="ic_auto_convert_png" value="1" <?php checked(get_option('ic_auto_convert_png', false)); ?>>
                                    <?php _e('Auto-convert all PNG uploads to WEBP', 'imagineer'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="ic_auto_convert_jpg" value="1" <?php checked(get_option('ic_auto_convert_jpg', false)); ?>>
                                    <?php _e('Auto-convert all JPG uploads to WEBP', 'imagineer'); ?>
                                </label>
                            </fieldset>
                            <p class="description"><?php _e('Automatically convert uploaded images to WEBP format to save space.', 'imagineer'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'imagineer'), 'primary', 'ic_save_settings'); ?>
            </form>
            </div>
            
            <?php elseif ($active_tab === 'shortcodes'): ?>
            <div class="ic-shortcodes-section">
                <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <h2 style="margin-top: 0;"><?php _e('Available Shortcodes', 'imagineer'); ?></h2>
                    <p><?php _e('Add these shortcodes to any page or post to create frontend conversion tools:', 'imagineer'); ?></p>
                    
                    <!-- Free Shortcodes -->
                    <div style="margin-top: 30px;">
                        <h3 style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px;">
                            🆓 Free Version Shortcodes
                        </h3>
                        
                        <div class="ic-shortcode-grid" style="display: grid; gap: 20px; margin-top: 20px;">
                            <!-- PNG to JPG -->
                            <div class="ic-shortcode-card" style="padding: 20px; border: 2px solid #e0e0e0; border-radius: 8px; background: #fafafa;">
                                <h4 style="margin: 0 0 10px; color: #333;">PNG to JPG Converter</h4>
                                <code style="display: block; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0;">[imagineer_png_to_jpg]</code>
                                <p style="margin: 10px 0 0; color: #666; font-size: 14px;">Creates a dedicated PNG to JPG conversion tool on any page.</p>
                                <details style="margin-top: 10px;">
                                    <summary style="cursor: pointer; color: #667eea; font-weight: 600;">Options</summary>
                                    <ul style="margin: 10px 0; padding-left: 20px; font-size: 13px;">
                                        <li><code>quality="85"</code> - Set conversion quality (1-100)</li>
                                        <li><code>title="Your Title"</code> - Custom widget title</li>
                                        <li><code>button_text="Convert Now"</code> - Custom button text</li>
                                    </ul>
                                </details>
                            </div>
                            
                            <!-- JPG to PNG -->
                            <div class="ic-shortcode-card" style="padding: 20px; border: 2px solid #e0e0e0; border-radius: 8px; background: #fafafa;">
                                <h4 style="margin: 0 0 10px; color: #333;">JPG to PNG Converter</h4>
                                <code style="display: block; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0;">[imagineer_jpg_to_png]</code>
                                <p style="margin: 10px 0 0; color: #666; font-size: 14px;">Creates a dedicated JPG to PNG conversion tool.</p>
                            </div>
                            
                            <!-- To WEBP -->
                            <div class="ic-shortcode-card" style="padding: 20px; border: 2px solid #e0e0e0; border-radius: 8px; background: #fafafa;">
                                <h4 style="margin: 0 0 10px; color: #333;">Convert to WEBP</h4>
                                <code style="display: block; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0;">[imagineer_to_webp]</code>
                                <p style="margin: 10px 0 0; color: #666; font-size: 14px;">Convert any image (PNG/JPG) to WEBP format.</p>
                            </div>
                            
                            <!-- Generic -->
                            <div class="ic-shortcode-card" style="padding: 20px; border: 2px solid #e0e0e0; border-radius: 8px; background: #fafafa;">
                                <h4 style="margin: 0 0 10px; color: #333;">Generic Converter</h4>
                                <code style="display: block; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0;">[imagineer_converter from="png" to="jpg"]</code>
                                <p style="margin: 10px 0 0; color: #666; font-size: 14px;">Flexible converter with custom source and target formats.</p>
                                <details style="margin-top: 10px;">
                                    <summary style="cursor: pointer; color: #667eea; font-weight: 600;">Options</summary>
                                    <ul style="margin: 10px 0; padding-left: 20px; font-size: 13px;">
                                        <li><code>from="png"</code> - Source format (png/jpg/webp/auto)</li>
                                        <li><code>to="jpg"</code> - Target format (jpg/png/webp)</li>
                                        <li><code>quality="80"</code> - Conversion quality</li>
                                    </ul>
                                </details>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pro Shortcodes -->
                    <div style="margin-top: 40px;">
                        <h3 style="color: #764ba2; border-bottom: 2px solid #764ba2; padding-bottom: 10px;">
                            Advanced Shortcodes
                        </h3>
                        
                        <div class="ic-shortcode-grid" style="display: grid; gap: 20px; margin-top: 20px;">
                            <!-- Bulk Converter -->
                            <div class="ic-shortcode-card" style="padding: 20px; border: 2px solid #764ba2; border-radius: 8px; background: linear-gradient(to bottom, #f9f7fc, white);">
                                <h4 style="margin: 0 0 10px; color: #333;">
                                    Bulk Converter
                                </h4>
                                <code style="display: block; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0;">[imagineer_bulk_converter]</code>
                                <p style="margin: 10px 0 0; color: #666; font-size: 14px;">Convert multiple images at once with resize options.</p>
                                <details style="margin-top: 10px;">
                                    <summary style="cursor: pointer; color: #764ba2; font-weight: 600;">Options & Example</summary>
                                    <ul style="margin: 10px 0; padding-left: 20px; font-size: 13px;">
                                        <li><code>title="Bulk Converter"</code> - Widget title</li>
                                        <li><code>show_resize="yes"</code> - Show resize inputs</li>
                                    </ul>
                                    <code style="display: block; padding: 8px; background: #f5f5f5; border-radius: 4px; font-size: 12px; margin-top: 8px;">
                                        [imagineer_bulk_converter title="Professional Bulk Converter" show_resize="yes"]
                                    </code>
                                </details>
                            </div>
                            
                            <!-- Resize Converter -->
                            <div class="ic-shortcode-card" style="padding: 20px; border: 2px solid #764ba2; border-radius: 8px; background: linear-gradient(to bottom, #f9f7fc, white);">
                                <h4 style="margin: 0 0 10px; color: #333;">
                                    Resize & Convert
                                </h4>
                                <code style="display: block; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0;">[imagineer_resize width="1200"]</code>
                                <p style="margin: 10px 0 0; color: #666; font-size: 14px;">Resize images to specific dimensions while converting.</p>
                                <details style="margin-top: 10px;">
                                    <summary style="cursor: pointer; color: #764ba2; font-weight: 600;">Options & Example</summary>
                                    <ul style="margin: 10px 0; padding-left: 20px; font-size: 13px;">
                                        <li><code>width="1200"</code> - Target width in pixels</li>
                                        <li><code>height="800"</code> - Target height in pixels</li>
                                        <li><code>format="webp"</code> - Output format</li>
                                    </ul>
                                    <code style="display: block; padding: 8px; background: #f5f5f5; border-radius: 4px; font-size: 12px; margin-top: 8px;">
                                        [imagineer_resize width="1200" height="800" format="webp"]
                                    </code>
                                </details>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Copy -->
                    <div style="margin-top: 40px; padding: 25px; background: #f0f9ff; border: 2px solid #bae6fd; border-radius: 8px;">
                        <h3 style="margin-top: 0; color: #0369a1;">💡 Quick Start</h3>
                        <ol style="margin: 0; padding-left: 20px;">
                            <li style="margin-bottom: 10px;">Create a new page (e.g., "PNG to JPG Converter")</li>
                            <li style="margin-bottom: 10px;">Copy one of the shortcodes above</li>
                            <li style="margin-bottom: 10px;">Paste it into your page content</li>
                            <li style="margin-bottom: 10px;">Publish and visit the page</li>
                            <li>Users can now convert images on your website!</li>
                        </ol>
                    </div>
                    
                    <!-- Example Page Templates -->
                    <div style="margin-top: 30px;">
                        <h3 style="color: #333;">📄 Example Page Templates</h3>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                            <div style="padding: 20px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;">
                                <h4 style="margin: 0 0 10px;">Simple PNG to JPG Page</h4>
                                <pre style="background: white; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px;"><code>&lt;h2&gt;Free PNG to JPG Converter&lt;/h2&gt;
&lt;p&gt;Convert your PNG images to JPG format for free!&lt;/p&gt;

[imagineer_png_to_jpg quality="90"]</code></pre>
                                <button class="button button-small" onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent); alert('Copied!')">
                                    📋 Copy
                                </button>
                            </div>
                            
                            <div style="padding: 20px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;">
                                <h4 style="margin: 0 0 10px;">Multi-Tool Page</h4>
                                <pre style="background: white; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px;"><code>&lt;h2&gt;Image Conversion Tools&lt;/h2&gt;

&lt;h3&gt;PNG to JPG&lt;/h3&gt;
[imagineer_png_to_jpg]

&lt;h3&gt;JPG to PNG&lt;/h3&gt;
[imagineer_jpg_to_png]

&lt;h3&gt;Convert to WEBP&lt;/h3&gt;
[imagineer_to_webp]</code></pre>
                                <button class="button button-small" onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent); alert('Copied!')">
                                    📋 Copy
                                </button>
                            </div>
                            
                            <?php if ($this->core->is_pro_active()): ?>
                            <div style="padding: 20px; background: linear-gradient(to bottom, #f9f7fc, white); border: 2px solid #764ba2; border-radius: 8px;">
                                <h4 style="margin: 0 0 10px;">
                                    Bulk Converter Page
                                </h4>
                                <pre style="background: white; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px;"><code>&lt;h2&gt;Professional Bulk Image Converter&lt;/h2&gt;
&lt;p&gt;Convert and resize multiple images at once!&lt;/p&gt;

[imagineer_bulk_converter show_resize="yes"]</code></pre>
                                <button class="button button-small" onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent); alert('Copied!')">
                                    📋 Copy
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Live Preview -->
                    <div style="margin-top: 40px; padding: 25px; background: #fffbeb; border: 2px solid #fcd34d; border-radius: 8px;">
                        <h3 style="margin-top: 0; color: #92400e;">🎨 Styling Your Widgets</h3>
                        <p>Customize the widget appearance with CSS in <strong>Appearance → Customize → Additional CSS</strong></p>
                        <pre style="background: white; padding: 15px; border-radius: 4px; overflow-x: auto; margin-top: 15px; font-size: 12px;"><code>/* Customize widget colors */
.imagineer-converter-widget {
    border-color: #your-brand-color;
}

.ic-widget-header {
    background: linear-gradient(135deg, #your-color-1, #your-color-2);
}

.ic-convert-button {
    background: #your-button-color;
}</code></pre>
                    </div>
                    
                    <!-- Features List -->
                    <div style="margin-top: 30px;">
                        <h3 style="color: #333;">✨ Shortcode Features</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                            <div style="padding: 15px; background: #f0fff4; border-left: 4px solid #46b450; border-radius: 4px;">
                                <strong>✅ Auto-Download</strong><br>
                                <small style="color: #666;">Converted files download automatically</small>
                            </div>
                            <div style="padding: 15px; background: #f0fff4; border-left: 4px solid #46b450; border-radius: 4px;">
                                <strong>✅ Drag & Drop</strong><br>
                                <small style="color: #666;">Easy file upload interface</small>
                            </div>
                            <div style="padding: 15px; background: #f0fff4; border-left: 4px solid #46b450; border-radius: 4px;">
                                <strong>✅ Mobile Friendly</strong><br>
                                <small style="color: #666;">Works on all devices</small>
                            </div>
                            <div style="padding: 15px; background: #f0fff4; border-left: 4px solid #46b450; border-radius: 4px;">
                                <strong>✅ Beautiful UI</strong><br>
                                <small style="color: #666;">Professional gradient design</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Support Banner -->
            <div class="ic-coffee-banner" style="background: linear-gradient(135deg, #ff813f 0%, #ff4b2b 100%); color: white; padding: 24px 28px; border-radius: 12px; margin-top: 24px; box-shadow: 0 4px 12px rgba(255, 75, 43, 0.25); text-align: center;">
                <h3 style="color: white; margin: 0 0 12px 0; font-size: 20px;">☕ Enjoying Imagineer?</h3>
                <p style="margin: 0 0 8px 0; opacity: 0.95; font-size: 14px;">All features are completely free! If you find this plugin helpful, consider buying me a coffee to support continued development.</p>
                <p style="margin: 0 0 16px 0; opacity: 0.9; font-size: 13px;">📧 Feedback or Support: <a href="mailto:allauddinyousafxai@gmail.com" style="color: white; text-decoration: underline;">allauddinyousafxai@gmail.com</a></p>
                <a href="https://www.buymeacoffee.com/adusafxai" target="_blank" class="button button-large" style="background: white; color: #ff4b2b; border: none; padding: 12px 32px; font-weight: 600; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px;">☕</span>
                    <span><?php _e('Buy Me a Coffee', 'imagineer'); ?></span>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Activate license (validates with your server)
     */
    private function activate_license($license_key) {
        $purchase_code = isset($_POST['ic_purchase_code']) ? sanitize_text_field($_POST['ic_purchase_code']) : '';
        
        // Basic validation
        if (empty($license_key) || empty($purchase_code)) {
            return array(
                'success' => false,
                'message' => __('Please enter both License Key and Purchase Code.', 'imagineer')
            );
        }
        
        // Validate with license server
        $licensing = new Imagineer_Licensing();
        $domain = $licensing->get_domain();
        $result = $licensing->validate_license($license_key, $purchase_code, $domain);
        
        if ($result['success']) {
            // Activation successful
            update_option('ic_license_key', $license_key);
            update_option('ic_purchase_code', $purchase_code);
            update_option('ic_is_pro', true);
            update_option('ic_license_data', $result['data']);
            
            // Create secure hash to prevent tampering
            $this->core->set_license_hash($license_key . $purchase_code);
            
            $status_msg = $result['data']['verification_status'] === 'verified' 
                ? __('License activated and verified!', 'imagineer')
                : __('License activated! Your purchase will be verified within 24-48 hours.', 'imagineer');
            
            return array(
                'success' => true,
                'message' => $status_msg
            );
        }
        
        return array(
            'success' => false,
            'message' => $result['message'] ?? __('License validation failed. Please check your License Key and Purchase Code.', 'imagineer')
        );
    }
    
    /**
     * Export statistics as CSV
     */
    private function export_statistics() {
        $conversions = get_option('ic_recent_conversions', array());
        
        if (empty($conversions)) {
            echo '<div class="notice notice-error"><p>' . __('No conversion history to export.', 'imagineer') . '</p></div>';
            return;
        }
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="imagineer-statistics-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write header row
        fputcsv($output, array('Date', 'Filename', 'From Format', 'To Format', 'Original Size', 'Converted Size', 'Space Saved'));
        
        // Write data rows
        foreach ($conversions as $conversion) {
            fputcsv($output, array(
                $conversion['date'],
                $conversion['filename'],
                strtoupper($conversion['from']),
                strtoupper($conversion['to']),
                size_format($conversion['original_size']),
                size_format($conversion['converted_size']),
                ($conversion['space_saved'] > 0 ? '-' : '+') . size_format(abs($conversion['space_saved']))
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Render Media Library converter page
     */
    public function render_media_library_page() {
        // Get pagination and filters
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 30;
        $filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'all';
        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Build mime type filter
        $mime_type = 'image';
        if ($filter_type !== 'all') {
            $mime_type = 'image/' . $filter_type;
        }
        
        // Get all images from Media Library
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => $mime_type,
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'post_status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        // Add search if provided
        if (!empty($search_query)) {
            $args['s'] = $search_query;
        }
        
        $query = new WP_Query($args);
        $images = $query->posts;
        $total_images = $query->found_posts;
        $total_pages = $query->max_num_pages;
        ?>
        <style>
            a.page-numbers {
                padding: 10px;
                background: white;
                box-shadow: 0 0 2px rgba(0, 0, 0, 0.2   );
            }
            span.page-numbers.current {
                background: #e0dcdc;
                padding: 10px;
                box-shadow: 0 0 2px rgba(0, 0, 0, 0.2   );
            }
        </style>
        <div class="wrap ic-media-library-wrap">
            <h1><?php _e('Media Library Converter', 'imagineer'); ?></h1>
            
            <div class="ic-ml-toolbar" style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 20px;">
                    <div style="flex: 1; min-width: 250px;">
                        <div style="position: relative;">
                            <input type="text" 
                                   id="ic-search-input" 
                                   placeholder="<?php _e('Search images...', 'imagineer'); ?>" 
                                   value="<?php echo esc_attr($search_query); ?>"
                                   style="width: 100%; padding: 10px 40px 10px 15px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
                            <button id="ic-search-btn" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: #667eea; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                🔍 Search
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label style="font-weight: 600; margin-right: 8px; display: block; margin-bottom: 5px; font-size: 12px; color: #666;">
                            <?php _e('Type:', 'imagineer'); ?>
                        </label>
                        <select id="ic-filter-type" style="padding: 10px 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; min-width: 120px;">
                            <option value="all" <?php selected($filter_type, 'all'); ?>>All</option>
                            <option value="jpeg" <?php selected($filter_type, 'jpeg'); ?>>JPEG</option>
                            <option value="png" <?php selected($filter_type, 'png'); ?>>PNG</option>
                            <option value="webp" <?php selected($filter_type, 'webp'); ?>>WEBP</option>
                            <option value="gif" <?php selected($filter_type, 'gif'); ?>>GIF</option>
                        </select>
                    </div>
                    
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <div style="font-size: 12px; opacity: 0.9; margin-bottom: 2px;"><?php _e('Total Images', 'imagineer'); ?></div>
                        <div style="font-size: 20px; font-weight: bold;"><?php echo number_format($total_images); ?></div>
                    </div>
                </div>
                
                <div class="ic-bulk-actions" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <button type="button" id="ic-select-all-btn" class="button" style="font-weight: 600;">
                        <?php _e('Select All', 'imagineer'); ?>
                    </button>
                    
                    <div>
                        <label style="font-weight: 600; margin-right: 8px;"><?php _e('Bulk convert to:', 'imagineer'); ?></label>
                        <select id="ic-bulk-format" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">Select format...</option>
                            <option value="webp">WEBP</option>
                            <option value="jpg">JPG</option>
                            <option value="png">PNG</option>
                            <option value="tiff">TIFF</option>
                        </select>
                    </div>
                    
                    <label style="font-weight: 600;">
                        <input type="checkbox" id="ic-bulk-replace" style="margin-right: 5px;">
                        <?php _e('Replace originals', 'imagineer'); ?>
                    </label>
                    
                    <button type="button" id="ic-bulk-convert-btn" class="button button-primary button-large" style="font-weight: 600;">
                        <?php _e('Convert Selected', 'imagineer'); ?>
                    </button>
                    
                    <span id="ic-selected-count" style="color: #666; font-weight: 600;"></span>
                </div>
            </div>
            
            <div class="ic-media-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px;">
                <?php foreach ($images as $image): 
                    $img_url = wp_get_attachment_image_url($image->ID, 'medium');
                    $img_title = get_the_title($image->ID);
                    $file_size = filesize(get_attached_file($image->ID));
                    $mime_type = get_post_mime_type($image->ID);
                    $format = strtoupper(str_replace('image/', '', $mime_type));
                ?>
                <div class="ic-media-item" data-id="<?php echo $image->ID; ?>" style="border: 2px solid #e0e0e0; border-radius: 12px; padding: 0; background: white; transition: all 0.3s ease; position: relative; overflow: hidden; cursor: pointer;">
                    <div style="position: relative;" onclick="document.querySelector('.ic-ml-checkbox[data-id=&quot;<?php echo $image->ID; ?>&quot;]').click();">
                        <input type="checkbox" class="ic-ml-checkbox" data-id="<?php echo $image->ID; ?>" style="position: absolute; top: 10px; left: 10px; width: 22px; height: 22px; cursor: pointer; z-index: 10;" onclick="event.stopPropagation();">
                        <div style="background: #667eea; color: white; padding: 5px 12px; font-size: 11px; font-weight: bold; position: absolute; top: 10px; right: 10px; border-radius: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                            <?php echo esc_html($format); ?>
                        </div>
                        <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($img_title); ?>" style="width: 100%; height: 200px; object-fit: cover; display: block;">
                    </div>
                    
                    <div style="padding: 15px; cursor: default;">
                        <p style="margin: 0 0 5px; font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo esc_attr($img_title); ?>">
                            <?php echo esc_html($img_title); ?>
                        </p>
                        <p style="margin: 0 0 15px; font-size: 13px; color: #666;">
                            <strong><?php echo size_format($file_size); ?></strong>
                        </p>
                        
                        <select class="ic-ml-format" data-id="<?php echo $image->ID; ?>" style="width: 100%; margin-bottom: 10px; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 13px; cursor: pointer; transition: border-color 0.3s;">
                            <option value="">Convert to...</option>
                            <option value="webp">WEBP</option>
                            <option value="jpg">JPG</option>
                            <option value="png">PNG</option>
                            <option value="tiff">TIFF</option>
                        </select>
                        
                        <label style="display: flex; align-items: center; margin-bottom: 10px; font-size: 13px; cursor: pointer; user-select: none;">
                            <input type="checkbox" class="ic-ml-single-replace" data-id="<?php echo $image->ID; ?>" style="margin-right: 8px; width: 18px; height: 18px; cursor: pointer;">
                            <span style="color: #666;"><?php _e('Replace original', 'imagineer'); ?></span>
                        </label>
                        
                        <button class="button button-primary ic-ml-convert-btn" data-id="<?php echo $image->ID; ?>" style="width: 100%; height: 42px; font-weight: 600; border-radius: 6px; font-size: 14px;">
                            Convert
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($images)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px;">
                    <p style="color: #666; font-size: 16px;"><?php _e('No images found in Media Library.', 'imagineer'); ?></p>
                    <p><a href="<?php echo admin_url('media-new.php'); ?>" class="button button-primary"><?php _e('Upload Images', 'imagineer'); ?></a></p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="tablenav" style="margin-top: 30px;">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo; Previous'),
                        'next_text' => __('Next &raquo;'),
                        'total' => $total_pages,
                        'current' => $paged
                    ));
                    ?>
                </div>
            </div>
            <?php endif; ?>
            
            <style>
            .ic-media-item:hover {
                border-color: #667eea;
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.2);
                transform: translateY(-4px);
            }
            .ic-media-item.selected {
                border-color: #667eea;
                border-width: 3px;
                background: linear-gradient(to bottom, #f0f4ff 0%, white 100%);
            }
            #ic-selected-count {
                color: #667eea;
                padding: 8px 15px;
                border-radius: 20px;
                font-size: 13px;
                background: #f0f4ff;
                border: 2px solid #667eea;
            }
            .ic-ml-format:focus,
            #ic-search-input:focus {
                border-color: #667eea;
                outline: none;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            #ic-search-btn:hover {
                background: #5568d3;
            }
            </style>
            
            <script>
            jQuery(document).ready(function($) {
                let selectedItems = [];
                
                // Search functionality
                $('#ic-search-btn').on('click', function() {
                    performSearch();
                });
                
                $('#ic-search-input').on('keypress', function(e) {
                    if (e.which === 13) {
                        performSearch();
                    }
                });
                
                function performSearch() {
                    const searchTerm = $('#ic-search-input').val();
                    const type = $('#ic-filter-type').val();
                    let url = '?page=imagineer-media-library';
                    
                    if (type !== 'all') {
                        url += '&filter_type=' + type;
                    }
                    
                    if (searchTerm) {
                        url += '&s=' + encodeURIComponent(searchTerm);
                    }
                    
                    window.location.href = url;
                }
                
                // Type filter
                $('#ic-filter-type').on('change', function() {
                    const type = $(this).val();
                    const searchTerm = $('#ic-search-input').val();
                    let url = '?page=imagineer-media-library&filter_type=' + type;
                    
                    if (searchTerm) {
                        url += '&s=' + encodeURIComponent(searchTerm);
                    }
                    
                    window.location.href = url;
                });
                
                // Checkbox selection
                $('.ic-ml-checkbox').on('change', function() {
                    const id = $(this).data('id');
                    const $item = $('.ic-media-item[data-id="' + id + '"]');
                    
                    if ($(this).is(':checked')) {
                        selectedItems.push(id);
                        $item.addClass('selected');
                    } else {
                        selectedItems = selectedItems.filter(i => i !== id);
                        $item.removeClass('selected');
                    }
                    
                    updateSelectedCount();
                });
                
                // Select all
                $('#ic-select-all-btn').on('click', function() {
                    const allChecked = $('.ic-ml-checkbox:checked').length === $('.ic-ml-checkbox').length;
                    
                    if (allChecked) {
                        $('.ic-ml-checkbox').prop('checked', false).trigger('change');
                        $(this).text('Select All');
                    } else {
                        $('.ic-ml-checkbox').prop('checked', true).trigger('change');
                        $(this).text('Deselect All');
                    }
                });
                
                // Update selected count
                function updateSelectedCount() {
                    if (selectedItems.length > 0) {
                        $('#ic-selected-count').text(selectedItems.length + ' selected').show();
                    } else {
                        $('#ic-selected-count').hide();
                    }
                }
                
                // Bulk convert
                $('#ic-bulk-convert-btn').on('click', function() {
                    if (selectedItems.length === 0) {
                        alert('Please select at least one image');
                        return;
                    }
                    
                    const format = $('#ic-bulk-format').val();
                    if (!format) {
                        alert('Please select a format to convert to');
                        return;
                    }
                    
                    const replaceOriginal = $('#ic-bulk-replace').is(':checked');
                    
                    if (replaceOriginal && !confirm(`This will REPLACE ${selectedItems.length} original images in Media Library. Continue?`)) {
                        return;
                    }
                    
                    // Disable button
                    $(this).text('Converting...').prop('disabled', true);
                    
                    // Convert each selected item
                    let completed = 0;
                    const total = selectedItems.length;
                    
                    function convertNext(index) {
                        if (index >= total) {
                            alert(`✅ Successfully converted ${completed} of ${total} images!`);
                            location.reload();
                            return;
                        }
                        
                        const id = selectedItems[index];
                        const $item = $('.ic-media-item[data-id="' + id + '"]');
                        $item.css('opacity', '0.5');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'ic_media_library_convert',
                                nonce: '<?php echo wp_create_nonce('ic_convert_nonce'); ?>',
                                attachment_id: id,
                                target_format: format,
                                quality: 80,
                                replace_original: replaceOriginal
                            },
                            success: function(response) {
                                if (response.success) {
                                    completed++;
                                    $item.css('opacity', '1').css('border-color', '#46b450');
                                    
                                    if (!replaceOriginal) {
                                        // Trigger download
                                        const link = document.createElement('a');
                                        link.href = response.data.url;
                                        link.download = response.data.filename;
                                        link.click();
                                    }
                                }
                                convertNext(index + 1);
                            },
                            error: function() {
                                $item.css('opacity', '1').css('border-color', 'red');
                                convertNext(index + 1);
                            }
                        });
                    }
                    
                    convertNext(0);
                });
                
                // Individual convert
                $('.ic-ml-convert-btn').on('click', function() {
                    const $btn = $(this);
                    const id = $btn.data('id');
                    const format = $('.ic-ml-format[data-id="' + id + '"]').val();
                    const replaceOriginal = $('.ic-ml-single-replace[data-id="' + id + '"]').is(':checked');
                    
                    if (!format) {
                        alert('Please select a format');
                        return;
                    }
                    
                    // Confirm if replacing original
                    if (replaceOriginal) {
                        if (!confirm('This will REPLACE the original image in Media Library. Are you sure?')) {
                            return;
                        }
                    }
                    
                    $btn.text('Converting...').prop('disabled', true);
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'ic_media_library_convert',
                            nonce: '<?php echo wp_create_nonce('ic_convert_nonce'); ?>',
                            attachment_id: id,
                            target_format: format,
                            quality: 80,
                            replace_original: replaceOriginal
                        },
                        success: function(response) {
                            if (response.success) {
                                if (replaceOriginal) {
                                    $btn.text('✅ Replaced').css('background', '#46b450');
                                    alert('✅ Image replaced successfully in Media Library!\n\nNew format: ' + format.toUpperCase());
                                    // Reload to show updated image
                                    setTimeout(() => {
                                        location.reload();
                                    }, 1500);
                                } else {
                                    $btn.text('✅ Downloaded');
                                    // Trigger download
                                    const link = document.createElement('a');
                                    link.href = response.data.url;
                                    link.download = response.data.filename;
                                    link.click();
                                    
                                    setTimeout(() => {
                                        $btn.text('Convert').prop('disabled', false);
                                    }, 2000);
                                }
                            } else {
                                alert('Error: ' + response.data.message);
                                $btn.text('Convert').prop('disabled', false);
                            }
                        },
                        error: function() {
                            alert('Conversion failed');
                            $btn.text('Convert').prop('disabled', false);
                        }
                    });
                });
            });
            </script>
            
            <!-- Support Banner -->
            <div class="ic-coffee-banner" style="background: linear-gradient(135deg, #ff813f 0%, #ff4b2b 100%); color: white; padding: 24px 28px; border-radius: 12px; margin-top: 24px; box-shadow: 0 4px 12px rgba(255, 75, 43, 0.25); text-align: center;">
                <h3 style="color: white; margin: 0 0 12px 0; font-size: 20px;">☕ Enjoying Imagineer?</h3>
                <p style="margin: 0 0 8px 0; opacity: 0.95; font-size: 14px;">All features are completely free! If you find this plugin helpful, consider buying me a coffee to support continued development.</p>
                <p style="margin: 0 0 16px 0; opacity: 0.9; font-size: 13px;">📧 Feedback or Support: <a href="mailto:allauddinyousafxai@gmail.com" style="color: white; text-decoration: underline;">allauddinyousafxai@gmail.com</a></p>
                <a href="https://www.buymeacoffee.com/adusafxai" target="_blank" class="button button-large" style="background: white; color: #ff4b2b; border: none; padding: 12px 32px; font-weight: 600; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px;">☕</span>
                    <span><?php _e('Buy Me a Coffee', 'imagineer'); ?></span>
                </a>
            </div>
        </div>
        <?php
    }
}

