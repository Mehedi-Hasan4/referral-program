<?php
/**
 * Plugin Name: WooCommerce Referral System
 * Description: A comprehensive referral system with tracking, rewards, and admin dashboard
 * Version: 1.0.0
 * Author: Mehedi Hasan
 * Text Domain: wc-referral-system
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('WC_REFERRAL_SYSTEM_VERSION', '1.0.0');
define('WC_REFERRAL_SYSTEM_FILE', __FILE__);
define('WC_REFERRAL_SYSTEM_DIR', plugin_dir_path(__FILE__));
define('WC_REFERRAL_SYSTEM_URL', plugin_dir_url(__FILE__));

// Include required files
require_once WC_REFERRAL_SYSTEM_DIR . 'includes/class-referral-system.php';


// Initialize the plugin
function wc_referral_system_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wc_referral_system_woocommerce_notice');
        return;
    }
    
    // Initialize the main plugin class
    $instance = WC_Referral_System::instance();
    $instance->init();
}
add_action('plugins_loaded', 'wc_referral_system_init');

// Admin notice for WooCommerce dependency
function wc_referral_system_woocommerce_notice() {
    ?>
    <div class="error">
        <p><?php _e('WooCommerce Referral System requires WooCommerce to be installed and active.', 'wc-referral-system'); ?></p>
    </div>
    <?php
}

// Plugin activation
function wc_referral_system_activate() {
    // Create database tables
    require_once WC_REFERRAL_SYSTEM_DIR . 'includes/class-referral-system-install.php';
    WC_Referral_System_Install::create_tables();
    
    // Clear rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(WC_REFERRAL_SYSTEM_FILE, 'wc_referral_system_activate');

// Plugin deactivation
function wc_referral_system_deactivate() {
    // Clear scheduled hooks
    wp_clear_scheduled_hook('wc_referral_cleanup_expired_coupons');
    
    // Clear rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(WC_REFERRAL_SYSTEM_FILE, 'wc_referral_system_deactivate');