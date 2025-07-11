<?php
/**
 * Referral System Installation Class
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Referral_System_Install {
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $wpdb->hide_errors();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Referrals table
        $table_name = $wpdb->prefix . 'wc_referrals';
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            referrer_id bigint(20) NOT NULL,
            referrer_email varchar(100) NOT NULL,
            referee_id bigint(20) DEFAULT NULL,
            referee_email varchar(100) DEFAULT NULL,
            referral_code varchar(50) NOT NULL,
            coupon_id bigint(20) DEFAULT NULL,
            referral_date datetime DEFAULT CURRENT_TIMESTAMP,
            order_id bigint(20) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            reward_coupon_id bigint(20) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY referrer_id (referrer_id),
            KEY referral_code (referral_code),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Add version to options
        add_option('wc_referral_system_db_version', WC_REFERRAL_SYSTEM_VERSION);
        
        // Add default settings
        self::add_default_settings();
    }
    
    /**
     * Add default settings
     */
    public static function add_default_settings() {
        // Add default settings if they don't exist
        if (!get_option('wc_referral_referee_discount')) {
            update_option('wc_referral_referee_discount', '10');
        }
        
        if (!get_option('wc_referral_referrer_reward')) {
            update_option('wc_referral_referrer_reward', '15');
        }
        
        if (!get_option('wc_referral_referee_expiry_days')) {
            update_option('wc_referral_referee_expiry_days', '30');
        }
        
        if (!get_option('wc_referral_referrer_expiry_days')) {
            update_option('wc_referral_referrer_expiry_days', '60');
        }
    }
}