<?php
/**
 * Main Referral System Class
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Referral_System {
    /**
     * Singleton instance
     */
    protected static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Load required files
        $this->includes();
        
        // Initialize components
        $this->init_components();
        
        // Register hooks
        $this->register_hooks();
    }

    /**
     * Include required files
     */
    public function includes() {
        // Core classes
        require_once WC_REFERRAL_SYSTEM_DIR . 'includes/class-referral-codes.php';
        require_once WC_REFERRAL_SYSTEM_DIR . 'includes/class-referral-coupon.php';
        require_once WC_REFERRAL_SYSTEM_DIR . 'includes/class-referral-admin.php';
        require_once WC_REFERRAL_SYSTEM_DIR . 'includes/class-referral-frontend.php';
        require_once WC_REFERRAL_SYSTEM_DIR . 'includes/class-referral-emails.php';
        require_once WC_REFERRAL_SYSTEM_DIR . 'includes/class-referral-widget.php';
    }

    /**
     * Initialize components
     */
    public function init_components() {
        // Initialize classes
        WC_Referral_Codes::instance();
        WC_Referral_Coupon::instance();
        WC_Referral_Admin::instance();
        WC_Referral_Frontend::instance();
        WC_Referral_Emails::instance();
        WC_Referral_Widget::instance();
    }

    /**
     * Register hooks
     */
    public function register_hooks() {
        // Load translations
        add_action('init', array($this, 'load_plugin_textdomain'));
        
        // Register assets
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'));
    }

    /**
     * Load plugin translations
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'wc-referral-system',
            false,
            dirname(plugin_basename(WC_REFERRAL_SYSTEM_FILE)) . '/languages/'
        );
    }

    /**
     * Register frontend assets
     */
    public function register_assets() {
        // Register and enqueue CSS
        wp_register_style(
            'wc-referral-system',
            WC_REFERRAL_SYSTEM_URL . 'assets/css/referral-system.css',
            array(),
            WC_REFERRAL_SYSTEM_VERSION
        );
        wp_enqueue_style('wc-referral-system');
        
        // Register and enqueue JS
        wp_register_script(
            'wc-referral-widget',
            WC_REFERRAL_SYSTEM_URL . 'assets/js/referral-widget.js',
            array('jquery'),
            WC_REFERRAL_SYSTEM_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('wc-referral-widget', 'wcReferralSystem', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc-referral-system-nonce')
        ));
        
        wp_enqueue_script('wc-referral-widget');
    }

    /**
     * Register admin assets
     */
    public function register_admin_assets($hook) {
        // Only load on referral system pages
        if (strpos($hook, 'wc-referral-system') === false) {
            return;
        }
        
        // Register and enqueue admin CSS
        wp_register_style(
            'wc-referral-system-admin',
            WC_REFERRAL_SYSTEM_URL . 'assets/css/admin-referral-system.css',
            array(),
            WC_REFERRAL_SYSTEM_VERSION
        );
        wp_enqueue_style('wc-referral-system-admin');
        
        // Register and enqueue admin JS
        wp_register_script(
            'wc-referral-system-admin',
            WC_REFERRAL_SYSTEM_URL . 'assets/js/admin-referral-system.js',
            array('jquery'),
            WC_REFERRAL_SYSTEM_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('wc-referral-system-admin', 'wcReferralSystemAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc-referral-system-admin-nonce')
        ));
        
        wp_enqueue_script('wc-referral-system-admin');
    }
}