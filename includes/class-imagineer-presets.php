<?php
/**
 * Conversion Presets System
 * Quick settings for common use cases
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Imagineer_Presets {
    
    /**
     * Get all available presets
     */
    public static function get_presets() {
        return array(
            'web_optimized' => array(
                'name' => __('Web Optimized', 'imagineer'),
                'description' => __('Perfect for websites - smaller file size, good quality', 'imagineer'),
                'icon' => '🌐',
                'settings' => array(
                    'quality' => 70,
                    'format' => 'webp',
                    'max_width' => 1920,
                    'max_height' => 1080,
                ),
                'color' => '#0891b2',
            ),
            'social_media' => array(
                'name' => __('Social Media', 'imagineer'),
                'description' => __('Optimized for Facebook, Instagram, Twitter', 'imagineer'),
                'icon' => '📱',
                'settings' => array(
                    'quality' => 85,
                    'format' => 'jpg',
                    'max_width' => 2048,
                    'max_height' => 2048,
                ),
                'color' => '#d97706',
            ),
            'high_quality' => array(
                'name' => __('High Quality', 'imagineer'),
                'description' => __('Maximum quality for print and professional use', 'imagineer'),
                'icon' => '✨',
                'settings' => array(
                    'quality' => 95,
                    'format' => 'png',
                    'max_width' => null,
                    'max_height' => null,
                ),
                'color' => '#1e40af',
            ),
            'thumbnail' => array(
                'name' => __('Thumbnail', 'imagineer'),
                'description' => __('Small size for thumbnails and previews', 'imagineer'),
                'icon' => '🖼️',
                'settings' => array(
                    'quality' => 75,
                    'format' => 'jpg',
                    'max_width' => 400,
                    'max_height' => 400,
                ),
                'color' => '#059669',
            ),
            'email_friendly' => array(
                'name' => __('Email Friendly', 'imagineer'),
                'description' => __('Small file size for email attachments', 'imagineer'),
                'icon' => '📧',
                'settings' => array(
                    'quality' => 65,
                    'format' => 'jpg',
                    'max_width' => 1024,
                    'max_height' => 768,
                ),
                'color' => '#6366f1',
            ),
            'retina_display' => array(
                'name' => __('Retina Display', 'imagineer'),
                'description' => __('High resolution for retina/4K screens', 'imagineer'),
                'icon' => '🖥️',
                'settings' => array(
                    'quality' => 90,
                    'format' => 'webp',
                    'max_width' => 3840,
                    'max_height' => 2160,
                ),
                'color' => '#dc2626',
            ),
        );
    }
    
    /**
     * Get preset by ID
     */
    public static function get_preset($preset_id) {
        $presets = self::get_presets();
        return isset($presets[$preset_id]) ? $presets[$preset_id] : null;
    }
    
    /**
     * Apply preset settings
     */
    public static function apply_preset($preset_id) {
        $preset = self::get_preset($preset_id);
        
        if (!$preset) {
            return false;
        }
        
        $settings = $preset['settings'];
        
        // Save to options
        if (isset($settings['quality'])) {
            update_option('ic_preset_quality', $settings['quality']);
        }
        if (isset($settings['format'])) {
            update_option('ic_preset_format', $settings['format']);
        }
        if (isset($settings['max_width'])) {
            update_option('ic_preset_max_width', $settings['max_width']);
        }
        if (isset($settings['max_height'])) {
            update_option('ic_preset_max_height', $settings['max_height']);
        }
        
        update_option('ic_active_preset', $preset_id);
        
        return true;
    }
    
    /**
     * Get active preset
     */
    public static function get_active_preset() {
        return get_option('ic_active_preset', '');
    }
    
    /**
     * Render preset selector
     */
    public static function render_preset_selector() {
        $presets = self::get_presets();
        $active = self::get_active_preset();
        ?>
        <div class="ic-presets-section">
            <h3 class="ic-presets-title">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" style="vertical-align: middle; margin-right: 8px;">
                    <path d="M10 2L12 8h6l-5 4 2 6-5-4-5 4 2-6-5-4h6l2-6z" fill="currentColor"/>
                </svg>
                <?php _e('Quick Presets', 'imagineer'); ?>
            </h3>
            <p class="ic-presets-subtitle"><?php _e('Choose a preset or customize your own settings', 'imagineer'); ?></p>
            
            <div class="ic-presets-grid">
                <?php foreach ($presets as $id => $preset): ?>
                <div class="ic-preset-card <?php echo $active === $id ? 'active' : ''; ?>" 
                     data-preset="<?php echo esc_attr($id); ?>"
                     data-settings='<?php echo json_encode($preset['settings']); ?>'
                     style="border-color: <?php echo $preset['color']; ?>">
                    <div class="ic-preset-icon" style="background: <?php echo $preset['color']; ?>">
                        <?php echo $preset['icon']; ?>
                    </div>
                    <div class="ic-preset-content">
                        <h4 class="ic-preset-name"><?php echo esc_html($preset['name']); ?></h4>
                        <p class="ic-preset-description"><?php echo esc_html($preset['description']); ?></p>
                    </div>
                    <div class="ic-preset-settings">
                        <span class="ic-preset-tag"><?php echo $preset['settings']['quality']; ?>% quality</span>
                        <span class="ic-preset-tag"><?php echo strtoupper($preset['settings']['format']); ?></span>
                        <?php if ($preset['settings']['max_width']): ?>
                        <span class="ic-preset-tag"><?php echo $preset['settings']['max_width']; ?>px</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($active === $id): ?>
                    <div class="ic-preset-active-badge">✓ Active</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="ic-preset-custom">
                <button type="button" class="ic-preset-custom-btn" id="ic-show-custom-settings">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M8 1v14M1 8h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <?php _e('Or customize your own settings', 'imagineer'); ?>
                </button>
            </div>
        </div>
        
        <style>
        .ic-presets-section {
            background: white;
            border-radius: 12px;
            padding: 32px;
            margin: 24px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
        }
        
        .ic-presets-title {
            font-size: 22px;
            font-weight: 600;
            color: #111827;
            margin: 0 0 10px 0;
        }
        
        .ic-presets-subtitle {
            font-size: 15px;
            color: #6b7280;
            margin: 0 0 28px 0;
        }
        
        .ic-presets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .ic-preset-card {
            position: relative;
            padding: 24px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #f9fafb;
        }
        
        .ic-preset-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-color: currentColor;
            background: white;
        }
        
        .ic-preset-card.active {
            background: white;
            border-width: 2px;
            border-color: currentColor;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        
        .ic-preset-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 18px;
        }
        
        .ic-preset-name {
            font-size: 17px;
            font-weight: 600;
            color: #111827;
            margin: 0 0 10px 0;
        }
        
        .ic-preset-description {
            font-size: 14px;
            color: #6b7280;
            margin: 0 0 14px 0;
            line-height: 1.6;
        }
        
        .ic-preset-settings {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .ic-preset-tag {
            font-size: 11px;
            padding: 4px 10px;
            background: #e2e8f0;
            border-radius: 12px;
            color: #4a5568;
            font-weight: 500;
        }
        
        .ic-preset-active-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: #48bb78;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .ic-preset-custom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .ic-preset-custom-btn {
            background: none;
            border: 2px dashed #cbd5e0;
            color: #718096;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .ic-preset-custom-btn:hover {
            border-color: #667eea;
            color: #667eea;
            background: #f7fafc;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Preset card click
            $('.ic-preset-card').on('click', function() {
                const $card = $(this);
                const presetId = $card.data('preset');
                const settings = $card.data('settings');
                
                // Update UI
                $('.ic-preset-card').removeClass('active');
                $card.addClass('active');
                
                // Apply settings
                if (settings.quality) {
                    $('#ic-quality').val(settings.quality);
                    $('#ic-quality-value').text(settings.quality);
                }
                if (settings.format) {
                    $('#ic-format').val(settings.format);
                }
                if (settings.max_width) {
                    $('#ic-resize-width').val(settings.max_width);
                }
                if (settings.max_height) {
                    $('#ic-resize-height').val(settings.max_height);
                }
                
                // Save preset
                $.post(ajaxurl, {
                    action: 'ic_apply_preset',
                    preset_id: presetId,
                    nonce: '<?php echo wp_create_nonce('ic_convert_nonce'); ?>'
                });
                
                // Toast notification
                if (window.ImagineerToast) {
                    ImagineerToast.success('Preset Applied', 'Settings updated successfully!');
                }
            });
            
            // Show custom settings
            $('#ic-show-custom-settings').on('click', function() {
                $('.ic-preset-card').removeClass('active');
                $('.ic-controls').slideDown();
                $('html, body').animate({
                    scrollTop: $('.ic-controls').offset().top - 100
                }, 500);
            });
        });
        </script>
        <?php
    }
}

// AJAX handler for applying presets
add_action('wp_ajax_ic_apply_preset', function() {
    check_ajax_referer('ic_convert_nonce', 'nonce');
    
    $preset_id = sanitize_text_field($_POST['preset_id']);
    $result = Imagineer_Presets::apply_preset($preset_id);
    
    if ($result) {
        wp_send_json_success(array('message' => __('Preset applied successfully!', 'imagineer')));
    } else {
        wp_send_json_error(array('message' => __('Invalid preset.', 'imagineer')));
    }
});

