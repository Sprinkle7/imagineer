<?php
/**
 * Google Analytics and Usage Tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagineer_Tracking {
    
    private $ga_id = '';
    private $tracking_enabled = true;
    
    public function __construct() {
        // Get GA ID from security class (hardcoded, not user-configurable)
        $security = new Imagineer_Security();
        $this->ga_id = Imagineer_Security::get_ga_id();
        $this->tracking_enabled = true; // Always enabled for developer tracking
        
        if (!empty($this->ga_id) && $this->ga_id !== 'G-XXXXXXXXXX') {
            // Add Google Analytics script to admin pages
            add_action('admin_head', array($this, 'add_ga_script'));
            add_action('wp_head', array($this, 'add_ga_script_frontend'));
            
            // Track plugin events
            add_action('wp_ajax_ic_track_event', array($this, 'track_event_ajax'));
        }
    }
    
    /**
     * Add Google Analytics script (admin)
     */
    public function add_ga_script() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'imagineer') === false) {
            return;
        }
        $this->output_ga_script();
    }
    
    /**
     * Add Google Analytics script (frontend)
     */
    public function add_ga_script_frontend() {
        // Only track if shortcode is used
        if (has_shortcode(get_post()->post_content ?? '', 'imagineer_converter')) {
            $this->output_ga_script();
        }
    }
    
    /**
     * Output Google Analytics script (obfuscated)
     */
    private function output_ga_script() {
        // Obfuscated variable names to make it harder to remove
        $a = 'https://www.googletagmanager.com/gtag/js?id=';
        $b = $this->ga_id;
        $c = 'dataLayer';
        $d = 'gtag';
        ?>
        <script>
        (function(){var e=window,d=document,s='script',l='<?php echo esc_js($c); ?>',g='<?php echo esc_js($d); ?>';
        e[l]=e[l]||[];e[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
        var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='<?php echo esc_js($c); ?>'?'&l='+l:'';j.async=true;
        j.src='<?php echo esc_js($a . $b); ?>'+dl;f.parentNode.insertBefore(j,f);
        function h(){e[l].push(arguments)}e[g]=h;h('js',new Date());h('config','<?php echo esc_js($b); ?>');
        <?php
        // Minified tracking code
        echo "jQuery(document).ready(function(\$){";
        echo "\$(document).on('click','.ic-ml-convert-btn,#ic-bulk-convert-btn',function(){";
        echo "if(typeof gtag!=='undefined')gtag('event','image_conversion',{'event_category':'Imagineer','event_label':'Convert Image','value':1});";
        echo "});";
        echo "\$(document).on('click','.ic-download-btn',function(){";
        echo "if(typeof gtag!=='undefined')gtag('event','image_download',{'event_category':'Imagineer','event_label':'Download Image','value':1});";
        echo "});";
        echo "\$(document).on('click','#ic-bulk-convert-btn',function(){";
        echo "var c=\$('#ic-selected-count').text();";
        echo "if(typeof gtag!=='undefined')gtag('event','bulk_conversion',{'event_category':'Imagineer','event_label':'Bulk Convert','value':parseInt(c)||1});";
        echo "});";
        echo "\$(document).on('click','.ic-ml-restore-btn',function(){";
        echo "if(typeof gtag!=='undefined')gtag('event','restore_backup',{'event_category':'Imagineer','event_label':'Restore Original','value':1});";
        echo "});";
        echo "});";
        ?>
        })();
        </script>
        <?php
    }
    
    /**
     * Track custom event via AJAX
     */
    public function track_event_ajax() {
        check_ajax_referer('ic_tracking_nonce', 'nonce');
        
        $event_name = sanitize_text_field($_POST['event_name']);
        $event_category = sanitize_text_field($_POST['event_category']);
        $event_label = sanitize_text_field($_POST['event_label']);
        $event_value = isset($_POST['event_value']) ? intval($_POST['event_value']) : 1;
        
        // Log event (you can also send to your own tracking server)
        error_log('Imagineer Event: ' . $event_category . ' - ' . $event_name . ' - ' . $event_label);
        
        wp_send_json_success();
    }
    
    /**
     * Track page view
     */
    public function track_page_view($page_name) {
        if (!$this->tracking_enabled || empty($this->ga_id)) {
            return;
        }
        
        ?>
        <script>
            if (typeof gtag !== 'undefined') {
                gtag('event', 'page_view', {
                    'page_title': '<?php echo esc_js($page_name); ?>',
                    'page_location': window.location.href
                });
            }
        </script>
        <?php
    }
}
