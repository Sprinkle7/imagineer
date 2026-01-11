<?php
/**
 * Welcome Screen & Setup Wizard
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Imagineer_Welcome {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_welcome_page'));
        add_action('admin_init', array($this, 'maybe_redirect_to_welcome'));
        add_action('wp_ajax_ic_dismiss_welcome', array($this, 'dismiss_welcome'));
        add_action('wp_ajax_ic_complete_setup', array($this, 'complete_setup'));
    }
    
    /**
     * Add welcome page (hidden from menu)
     */
    public function add_welcome_page() {
        add_submenu_page(
            null, // Hidden from menu
            __('Welcome to Imagineer', 'imagineer'),
            __('Welcome', 'imagineer'),
            'manage_options',
            'imagineer-welcome',
            array($this, 'render_welcome_page')
        );
    }
    
    /**
     * Redirect to welcome page on first activation
     */
    public function maybe_redirect_to_welcome() {
        if (get_transient('ic_activation_redirect')) {
            delete_transient('ic_activation_redirect');
            
            if (!get_option('ic_welcome_dismissed', false)) {
                wp_safe_redirect(admin_url('admin.php?page=imagineer-welcome'));
                exit;
            }
        }
    }
    
    /**
     * Dismiss welcome screen
     */
    public function dismiss_welcome() {
        check_ajax_referer('ic_convert_nonce', 'nonce');
        update_option('ic_welcome_dismissed', true);
        wp_send_json_success();
    }
    
    /**
     * Complete setup wizard
     */
    public function complete_setup() {
        check_ajax_referer('ic_convert_nonce', 'nonce');
        
        // Save setup preferences
        if (isset($_POST['default_quality'])) {
            update_option('ic_default_quality', intval($_POST['default_quality']));
        }
        
        update_option('ic_welcome_dismissed', true);
        update_option('ic_setup_completed', true);
        
        wp_send_json_success(array(
            'redirect' => admin_url('admin.php?page=imagineer')
        ));
    }
    
    /**
     * Render welcome page
     */
    public function render_welcome_page() {
        ?>
        <div class="ic-welcome-screen">
            <div class="ic-welcome-container">
                
                <!-- Header -->
                <div class="ic-welcome-header">
                    <div class="ic-welcome-logo">
                        <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
                            <rect width="60" height="60" rx="12" fill="url(#grad1)"/>
                            <path d="M20 25 L30 35 L45 20" stroke="white" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="30" cy="40" r="8" fill="white" opacity="0.3"/>
                            <defs>
                                <linearGradient id="grad1" x1="0" y1="0" x2="60" y2="60">
                                    <stop offset="0%" style="stop-color:#667eea"/>
                                    <stop offset="100%" style="stop-color:#764ba2"/>
                                </linearGradient>
                            </defs>
                        </svg>
                    </div>
                    <h1><?php _e('Welcome to Imagineer!', 'imagineer'); ?></h1>
                    <p class="ic-welcome-subtitle"><?php _e('The Ultimate Image Conversion & Optimization Plugin', 'imagineer'); ?></p>
                </div>
                
                <!-- Setup Wizard -->
                <div class="ic-setup-wizard">
                    
                    <!-- Step 1: Welcome -->
                    <div class="ic-wizard-step active" data-step="1">
                        <div class="ic-wizard-content">
                            <h2>🎉 <?php _e('Thank You for Installing!', 'imagineer'); ?></h2>
                            <p><?php _e('Let\'s get you set up in less than 2 minutes.', 'imagineer'); ?></p>
                            
                            <div class="ic-features-grid">
                                <div class="ic-feature-card">
                                    <div class="ic-feature-icon">⚡</div>
                                    <h3><?php _e('Lightning Fast', 'imagineer'); ?></h3>
                                    <p><?php _e('Direct GD/Imagick processing for maximum speed', 'imagineer'); ?></p>
                                </div>
                                <div class="ic-feature-card">
                                    <div class="ic-feature-icon">🎨</div>
                                    <h3><?php _e('Multiple Formats', 'imagineer'); ?></h3>
                                    <p><?php _e('Convert PNG, JPG, WEBP, HEIC, SVG, TIFF & more', 'imagineer'); ?></p>
                                </div>
                                <div class="ic-feature-card">
                                    <div class="ic-feature-icon">📦</div>
                                    <h3><?php _e('Bulk Operations', 'imagineer'); ?></h3>
                                    <p><?php _e('Process hundreds of images in one click', 'imagineer'); ?></p>
                                </div>
                                <div class="ic-feature-card">
                                    <div class="ic-feature-icon">🔌</div>
                                    <h3><?php _e('Seamless Integration', 'imagineer'); ?></h3>
                                    <p><?php _e('Works with WooCommerce, Media Library & more', 'imagineer'); ?></p>
                                </div>
                            </div>
                            
                            <button class="ic-wizard-btn ic-wizard-next"><?php _e('Get Started', 'imagineer'); ?> →</button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Settings -->
                    <div class="ic-wizard-step" data-step="2">
                        <div class="ic-wizard-content">
                            <h2>⚙️ <?php _e('Quick Settings', 'imagineer'); ?></h2>
                            <p><?php _e('Configure your default conversion preferences', 'imagineer'); ?></p>
                            
                            <div class="ic-wizard-form">
                                <div class="ic-form-group">
                                    <label><?php _e('Default Quality', 'imagineer'); ?></label>
                                    <div class="ic-quality-selector">
                                        <label class="ic-quality-option">
                                            <input type="radio" name="default_quality" value="70">
                                            <div class="ic-quality-card">
                                                <strong><?php _e('Web Optimized', 'imagineer'); ?></strong>
                                                <span><?php _e('70% - Smaller files', 'imagineer'); ?></span>
                                            </div>
                                        </label>
                                        <label class="ic-quality-option">
                                            <input type="radio" name="default_quality" value="85" checked>
                                            <div class="ic-quality-card selected">
                                                <strong><?php _e('Balanced', 'imagineer'); ?></strong>
                                                <span><?php _e('85% - Recommended', 'imagineer'); ?></span>
                                            </div>
                                        </label>
                                        <label class="ic-quality-option">
                                            <input type="radio" name="default_quality" value="95">
                                            <div class="ic-quality-card">
                                                <strong><?php _e('High Quality', 'imagineer'); ?></strong>
                                                <span><?php _e('95% - Larger files', 'imagineer'); ?></span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="ic-wizard-buttons">
                                <button class="ic-wizard-btn ic-wizard-back">← <?php _e('Back', 'imagineer'); ?></button>
                                <button class="ic-wizard-btn ic-wizard-next"><?php _e('Continue', 'imagineer'); ?> →</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3: Complete -->
                    <div class="ic-wizard-step" data-step="3">
                        <div class="ic-wizard-content">
                            <div class="ic-wizard-success">
                                <div class="ic-success-icon">✓</div>
                                <h2><?php _e('All Set!', 'imagineer'); ?></h2>
                                <p><?php _e('Imagineer is ready to optimize your images.', 'imagineer'); ?></p>
                            </div>
                            
                            <div class="ic-next-steps">
                                <h3><?php _e('What\'s Next?', 'imagineer'); ?></h3>
                                <div class="ic-steps-list">
                                    <div class="ic-step-item">
                                        <span class="ic-step-number">1</span>
                                        <div>
                                            <strong><?php _e('Start Converting', 'imagineer'); ?></strong>
                                            <p><?php _e('Upload images and convert to any format', 'imagineer'); ?></p>
                                        </div>
                                    </div>
                                    <div class="ic-step-item">
                                        <span class="ic-step-number">2</span>
                                        <div>
                                            <strong><?php _e('Optimize Media Library', 'imagineer'); ?></strong>
                                            <p><?php _e('Convert existing images in bulk', 'imagineer'); ?></p>
                                        </div>
                                    </div>
                                    <div class="ic-step-item">
                                        <span class="ic-step-number">3</span>
                                        <div>
                                            <strong><?php _e('Use Shortcodes', 'imagineer'); ?></strong>
                                            <p><?php _e('Add frontend converters to any page', 'imagineer'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button class="ic-wizard-btn ic-wizard-complete"><?php _e('Go to Dashboard', 'imagineer'); ?> 🚀</button>
                            <button class="ic-wizard-skip"><?php _e('Skip for now', 'imagineer'); ?></button>
                        </div>
                    </div>
                    
                </div>
                
                <!-- Progress Indicator -->
                <div class="ic-wizard-progress">
                    <div class="ic-progress-step active" data-step="1">
                        <div class="ic-progress-dot"></div>
                        <span><?php _e('Welcome', 'imagineer'); ?></span>
                    </div>
                    <div class="ic-progress-step" data-step="2">
                        <div class="ic-progress-dot"></div>
                        <span><?php _e('Settings', 'imagineer'); ?></span>
                    </div>
                    <div class="ic-progress-step" data-step="3">
                        <div class="ic-progress-dot"></div>
                        <span><?php _e('Complete', 'imagineer'); ?></span>
                    </div>
                </div>
                
            </div>
        </div>
        
        <style>
        .ic-welcome-screen {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 20px;
            margin-left: -20px;
        }
        
        .ic-welcome-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .ic-welcome-header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }
        
        .ic-welcome-logo {
            margin-bottom: 20px;
        }
        
        .ic-welcome-header h1 {
            font-size: 42px;
            font-weight: 700;
            margin: 20px 0 10px;
            color: white;
        }
        
        .ic-welcome-subtitle {
            font-size: 20px;
            opacity: 0.9;
        }
        
        .ic-setup-wizard {
            background: white;
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
        }
        
        .ic-wizard-step {
            display: none;
        }
        
        .ic-wizard-step.active {
            display: block;
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .ic-wizard-content h2 {
            font-size: 32px;
            margin-bottom: 15px;
            color: #1a202c;
        }
        
        .ic-wizard-content > p {
            font-size: 18px;
            color: #718096;
            margin-bottom: 40px;
        }
        
        .ic-features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin: 40px 0;
        }
        
        .ic-feature-card {
            padding: 25px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .ic-feature-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.1);
        }
        
        .ic-feature-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }
        
        .ic-feature-card h3 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #2d3748;
        }
        
        .ic-feature-card p {
            font-size: 14px;
            color: #718096;
            line-height: 1.6;
        }
        
        .ic-quality-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        
        .ic-quality-option {
            cursor: pointer;
        }
        
        .ic-quality-option input {
            display: none;
        }
        
        .ic-quality-card {
            padding: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .ic-quality-card:hover,
        .ic-quality-option input:checked + .ic-quality-card {
            border-color: #667eea;
            background: #f7fafc;
        }
        
        .ic-quality-card strong {
            display: block;
            font-size: 16px;
            margin-bottom: 8px;
            color: #2d3748;
        }
        
        .ic-quality-card span {
            font-size: 14px;
            color: #718096;
        }
        
        .ic-wizard-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 20px 10px 0 0;
        }
        
        .ic-wizard-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .ic-wizard-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        
        .ic-wizard-back {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .ic-wizard-success {
            text-align: center;
            padding: 40px 0;
        }
        
        .ic-success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: white;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s;
        }
        
        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
        
        .ic-next-steps {
            margin: 40px 0;
        }
        
        .ic-next-steps h3 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2d3748;
        }
        
        .ic-steps-list {
            background: #f7fafc;
            border-radius: 12px;
            padding: 30px;
        }
        
        .ic-step-item {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .ic-step-item:last-child {
            margin-bottom: 0;
        }
        
        .ic-step-number {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .ic-step-item strong {
            display: block;
            font-size: 16px;
            margin-bottom: 5px;
            color: #2d3748;
        }
        
        .ic-step-item p {
            font-size: 14px;
            color: #718096;
            margin: 0;
        }
        
        .ic-wizard-skip {
            background: none;
            border: none;
            color: #718096;
            cursor: pointer;
            margin-top: 15px;
            font-size: 14px;
        }
        
        .ic-wizard-skip:hover {
            color: #4a5568;
            text-decoration: underline;
        }
        
        .ic-wizard-progress {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 40px;
        }
        
        .ic-progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            color: white;
            opacity: 0.5;
            transition: all 0.3s;
        }
        
        .ic-progress-step.active {
            opacity: 1;
        }
        
        .ic-progress-dot {
            width: 12px;
            height: 12px;
            background: white;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.3);
        }
        
        .ic-progress-step.active .ic-progress-dot {
            border-color: white;
            box-shadow: 0 0 20px rgba(255,255,255,0.5);
        }
        
        .ic-progress-step span {
            font-size: 14px;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .ic-features-grid,
            .ic-quality-selector {
                grid-template-columns: 1fr;
            }
            
            .ic-setup-wizard {
                padding: 30px 20px;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let currentStep = 1;
            
            // Next button
            $('.ic-wizard-next').on('click', function() {
                currentStep++;
                showStep(currentStep);
            });
            
            // Back button
            $('.ic-wizard-back').on('click', function() {
                currentStep--;
                showStep(currentStep);
            });
            
            // Complete button
            $('.ic-wizard-complete').on('click', function() {
                const quality = $('input[name="default_quality"]:checked').val() || 85;
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ic_complete_setup',
                        nonce: '<?php echo wp_create_nonce('ic_convert_nonce'); ?>',
                        default_quality: quality
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect;
                        }
                    }
                });
            });
            
            // Skip button
            $('.ic-wizard-skip').on('click', function() {
                $.post(ajaxurl, {
                    action: 'ic_dismiss_welcome',
                    nonce: '<?php echo wp_create_nonce('ic_convert_nonce'); ?>'
                }, function() {
                    window.location.href = '<?php echo admin_url('admin.php?page=imagineer'); ?>';
                });
            });
            
            function showStep(step) {
                $('.ic-wizard-step').removeClass('active');
                $('.ic-wizard-step[data-step="' + step + '"]').addClass('active');
                
                $('.ic-progress-step').removeClass('active');
                $('.ic-progress-step[data-step="' + step + '"]').addClass('active');
            }
        });
        </script>
        <?php
    }
}

new Imagineer_Welcome();

