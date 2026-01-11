<?php
/**
 * Shortcodes for Frontend Image Conversion
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagineer_Shortcodes {
    
    private $core;
    
    public function __construct() {
        $this->core = new Imagineer_Core();
        
        // Register shortcodes
        add_shortcode('imagineer_converter', array($this, 'converter_shortcode'));
        add_shortcode('imagineer_png_to_jpg', array($this, 'png_to_jpg_shortcode'));
        add_shortcode('imagineer_jpg_to_png', array($this, 'jpg_to_png_shortcode'));
        add_shortcode('imagineer_to_webp', array($this, 'to_webp_shortcode'));
        
        // All shortcodes are free!
        add_shortcode('imagineer_bulk_converter', array($this, 'bulk_converter_shortcode'));
        add_shortcode('imagineer_resize', array($this, 'resize_shortcode'));
        
        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('imagineer-frontend', IC_PLUGIN_URL . 'assets/css/frontend.css', array(), IC_VERSION);
        wp_enqueue_script('imagineer-frontend', IC_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), IC_VERSION, true);
        
        wp_localize_script('imagineer-frontend', 'imagineerData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ic_convert_nonce'),
            'downloadNonce' => wp_create_nonce('ic_download_file'),
            'isPro' => $this->core->is_pro_active(),
            'maxFileSize' => $this->core->get_max_file_size(),
            'strings' => array(
                'uploading' => __('Uploading...', 'imagineer'),
                'converting' => __('Converting...', 'imagineer'),
                'success' => __('Done!', 'imagineer'),
                'error' => __('Error', 'imagineer'),
                'selectFile' => __('Please select a file', 'imagineer'),
                'fileTooLarge' => __('File too large', 'imagineer')
            )
        ));
    }
    
    /**
     * Generic converter shortcode
     * [imagineer_converter]
     */
    public function converter_shortcode($atts) {
        $atts = shortcode_atts(array(
            'from' => 'auto',
            'to' => 'jpg',
            'quality' => '80',
            'title' => 'Convert Image',
            'button_text' => 'Convert'
        ), $atts);
        
        $converter_id = 'ic-converter-' . uniqid();
        
        ob_start();
        ?>
        <div class="imagineer-converter-widget" id="<?php echo esc_attr($converter_id); ?>">
            <div class="ic-widget-body">
                <div class="ic-upload-zone">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    <p>Drop image or click to browse</p>
                    <input type="file" class="ic-file-input" accept="image/*" style="position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none;">
                </div>
                
                <button type="button" class="ic-convert-button" 
                        data-from="<?php echo esc_attr($atts['from']); ?>"
                        data-to="<?php echo esc_attr($atts['to']); ?>"
                        data-quality="<?php echo esc_attr($atts['quality']); ?>">
                    <?php echo esc_html($atts['button_text']); ?>
                </button>
                
                <div class="ic-widget-result"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * PNG to JPG converter
     * [imagineer_png_to_jpg]
     */
    public function png_to_jpg_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'PNG to JPG Converter',
            'quality' => '85',
            'button_text' => 'Convert to JPG'
        ), $atts);
        
        return $this->converter_shortcode(array(
            'from' => 'png',
            'to' => 'jpg',
            'quality' => $atts['quality'],
            'title' => $atts['title'],
            'button_text' => $atts['button_text']
        ));
    }
    
    /**
     * JPG to PNG converter
     * [imagineer_jpg_to_png]
     */
    public function jpg_to_png_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'JPG to PNG Converter',
            'quality' => '95',
            'button_text' => 'Convert to PNG'
        ), $atts);
        
        return $this->converter_shortcode(array(
            'from' => 'jpg',
            'to' => 'png',
            'quality' => $atts['quality'],
            'title' => $atts['title'],
            'button_text' => $atts['button_text']
        ));
    }
    
    /**
     * Any format to WEBP converter
     * [imagineer_to_webp]
     */
    public function to_webp_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Convert to WEBP',
            'quality' => '80',
            'button_text' => 'Convert to WEBP'
        ), $atts);
        
        return $this->converter_shortcode(array(
            'from' => 'auto',
            'to' => 'webp',
            'quality' => $atts['quality'],
            'title' => $atts['title'],
            'button_text' => $atts['button_text']
        ));
    }
    
    /**
     * Bulk converter with resize
     * [imagineer_bulk_converter]
     */
    public function bulk_converter_shortcode($atts) {
        
        $atts = shortcode_atts(array(
            'title' => 'Bulk Image Converter',
            'show_resize' => 'yes'
        ), $atts);
        
        ob_start();
        ?>
        <div class="imagineer-bulk-widget">
            <div class="ic-widget-body">
                <div class="ic-upload-zone ic-bulk-upload">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    <p>Drop multiple images or click to browse</p>
                    <input type="file" class="ic-bulk-file-input" accept="image/*" multiple style="position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none;">
                </div>
                
                <div class="ic-bulk-controls">
                    <select class="ic-bulk-format">
                        <option value="jpg">Convert to JPG</option>
                        <option value="png">Convert to PNG</option>
                        <option value="webp">Convert to WEBP</option>
                        <option value="gif">Convert to GIF</option>
                        <option value="bmp">Convert to BMP</option>
                        <option value="tiff">Convert to TIFF</option>
                    </select>
                    
                    <select class="ic-bulk-quality">
                        <option value="60">Low Quality</option>
                        <option value="80" selected>Medium Quality</option>
                        <option value="95">High Quality</option>
                        <option value="100">Maximum Quality</option>
                    </select>
                    
                    <?php if ($atts['show_resize'] === 'yes'): ?>
                    <div class="ic-resize-inputs">
                        <input type="number" class="ic-bulk-width" placeholder="Width (px)" min="1">
                        <span>×</span>
                        <input type="number" class="ic-bulk-height" placeholder="Height (px)" min="1">
                    </div>
                    <?php endif; ?>
                    
                    <button type="button" class="ic-bulk-convert-button">
                        Convert All
                    </button>
                </div>
                
                <div class="ic-bulk-progress"></div>
                <div class="ic-bulk-results"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Resize converter
     * [imagineer_resize width="800" height="600"]
     */
    public function resize_shortcode($atts) {
        
        $atts = shortcode_atts(array(
            'width' => '',
            'height' => '',
            'title' => 'Resize & Convert Image',
            'format' => 'jpg'
        ), $atts);
        
        ob_start();
        ?>
        <div class="imagineer-resize-widget">
            <div class="ic-widget-body">
                <div class="ic-upload-zone">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    <p>Upload image to resize</p>
                    <input type="file" class="ic-file-input" accept="image/*" style="position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none;">
                </div>
                
                <button type="button" class="ic-convert-button"
                        data-to="<?php echo esc_attr($atts['format']); ?>"
                        data-width="<?php echo esc_attr($atts['width']); ?>"
                        data-height="<?php echo esc_attr($atts['height']); ?>">
                    Resize & Convert
                </button>
                
                <div class="ic-widget-result"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize shortcodes
new Imagineer_Shortcodes();

