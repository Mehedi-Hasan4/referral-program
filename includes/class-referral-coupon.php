<?php
/**
 * Referral Coupon Class
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Referral_Coupon {
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
     * Constructor
     */
    public function __construct() {
        // Register hooks
        add_action('woocommerce_before_checkout_form', array($this, 'auto_apply_referral_coupon'));
        add_action('woocommerce_before_cart', array($this, 'auto_apply_referral_coupon'));
        add_action('woocommerce_order_status_completed', array($this, 'update_referral_on_order_completed'));
        // add_action('woocommerce_order_status_processing', array($this, 'update_referral_on_order_completed'));
        add_action('wp_ajax_wc_referral_check_coupon_usage', array($this, 'ajax_check_coupon_usage'));
        add_action('wp_ajax_nopriv_wc_referral_check_coupon_usage', array($this, 'ajax_check_coupon_usage'));
        add_action('wp_ajax_wc_referral_clear_used_code', array($this, 'ajax_clear_used_code'));
        add_action('wp_ajax_nopriv_wc_referral_clear_used_code', array($this, 'ajax_clear_used_code'));

    }

    /**
     * Create and apply discount coupon for referee
     */
    public function create_referee_coupon($referral_code) {
        global $wpdb;
    
        // Check if coupon for this referral already exists
        $table_name = $wpdb->prefix . 'wc_referrals';
        $existing_coupon = $wpdb->get_var($wpdb->prepare(
            "SELECT coupon_id FROM $table_name WHERE referral_code = %s LIMIT 1",
            $referral_code
        ));
    
        if ($existing_coupon) {
            $coupon_code = get_the_title($existing_coupon);
        } else {
            // Get referrer user ID from referral code
            $referrer_id = WC_Referral_Codes::instance()->get_referrer_id_from_code($referral_code);
            
            if ($referrer_id) {
                $user_data = get_userdata($referrer_id);
                $username = $user_data->user_login;
                
                // Generate coupon code using username format
                $coupon_code = 'REF-' . strtoupper($username);
            } else {
                // Fallback to original method if user not found
                $coupon_code = 'REF-' . strtoupper(substr(md5($referral_code . time()), 0, 8));
            }
        
            // Get discount amount
            $discount_amount = get_option('wc_referral_referee_discount', '10');
            $expiry_days = get_option('wc_referral_referee_expiry_days', '30');
        
            // Create the coupon
            $coupon = array(
                'post_title' => $coupon_code,
                'post_content' => 'Referral discount coupon',
                'post_status' => 'publish',
                'post_author' => 1,
                'post_type' => 'shop_coupon',
            );
        
            $coupon_id = wp_insert_post($coupon);
        
            // Set coupon meta - UPDATED: Remove usage_limit and add usage_limit_per_user
            update_post_meta($coupon_id, 'discount_type', 'percent');
            update_post_meta($coupon_id, 'coupon_amount', $discount_amount);
            update_post_meta($coupon_id, 'individual_use', 'yes');
            // Remove total usage limit to allow multiple people to use it
            // update_post_meta($coupon_id, 'usage_limit', '1'); // REMOVED
            update_post_meta($coupon_id, 'usage_limit_per_user', '1'); // ADDED: 1 use per user
            update_post_meta($coupon_id, 'expiry_date', date('Y-m-d', strtotime("+{$expiry_days} days")));
            update_post_meta($coupon_id, '_is_referral_coupon', 'yes');
            update_post_meta($coupon_id, '_referral_code', $referral_code);
        
            if ($referrer_id) {
                // Update referral record with coupon
                $wpdb->update(
                    $table_name,
                    array('coupon_id' => $coupon_id),
                    array('referral_code' => $referral_code, 'referrer_id' => $referrer_id),
                    array('%d'),
                    array('%s', '%d')
                );
            }
        }
    
        return $coupon_code;
    }

/**
 * AJAX handler to check if user can use the referral coupon
 */
public function ajax_check_coupon_usage() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'wc_referral_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    // Get referral code from session or cookie
    $referral_code = '';
    if (session_id() && isset($_SESSION['wc_referral_code'])) {
        $referral_code = $_SESSION['wc_referral_code'];
    } elseif (isset($_COOKIE['wc_referral_code'])) {
        $referral_code = sanitize_text_field($_COOKIE['wc_referral_code']);
    }
    
    if (empty($referral_code)) {
        wp_send_json_error(array('message' => 'No referral code found'));
        return;
    }
    
    // Get the coupon code for this referral
    $coupon_code = $this->get_coupon_code_by_referral($referral_code);
    
    if (empty($coupon_code)) {
        wp_send_json_error(array('message' => 'No coupon found for this referral'));
        return;
    }
    
    // Check if user can use this coupon
    $can_use = $this->can_user_use_referral_coupon($coupon_code);
    
    if ($can_use) {
        wp_send_json_success(array(
            'can_use_coupon' => true,
            'message' => 'Coupon can be used'
        ));
    } else {
        wp_send_json_success(array(
            'can_use_coupon' => false,
            'message' => 'You have already used this referral discount before.'
        ));
    }
}

/**
 * AJAX handler to clear used referral code from session/cookie
 */
public function ajax_clear_used_code() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'wc_referral_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    // Clear from session
    if (session_id() && isset($_SESSION['wc_referral_code'])) {
        unset($_SESSION['wc_referral_code']);
        unset($_SESSION['wc_referral_record_id']);
        unset($_SESSION['wc_referral_time']);
    }
    
    // Clear from cookie
    if (isset($_COOKIE['wc_referral_code'])) {
        setcookie('wc_referral_code', '', time() - 3600, '/');
    }
    
    wp_send_json_success(array('message' => 'Referral code cleared'));
}

/**
 * Get coupon code by referral code
 */
private function get_coupon_code_by_referral($referral_code) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wc_referrals';
    $coupon_id = $wpdb->get_var($wpdb->prepare(
        "SELECT coupon_id FROM $table_name WHERE referral_code = %s LIMIT 1",
        $referral_code
    ));
    
    if ($coupon_id) {
        return get_the_title($coupon_id);
    }
    
    return '';
}

/**
 * Check if current user can use the referral coupon (enhanced version)
 */
private function can_user_use_referral_coupon($coupon_code) {
    $coupon = new WC_Coupon($coupon_code);
    if (!$coupon->get_id()) {
        return false;
    }
    
    $current_user_id = get_current_user_id();
    $current_user_email = '';
    
    // Get current user email
    if ($current_user_id) {
        $user = get_userdata($current_user_id);
        $current_user_email = $user->user_email;
    } else {
        // For guest users, try to get email from various sources
        if (WC()->customer && WC()->customer->get_email()) {
            $current_user_email = WC()->customer->get_email();
        } elseif (isset($_POST['billing_email'])) {
            $current_user_email = sanitize_email($_POST['billing_email']);
        } elseif (session_id() && isset($_SESSION['guest_email'])) {
            $current_user_email = sanitize_email($_SESSION['guest_email']);
        }
    }
    
    // Check if user has already used this coupon
    global $wpdb;
    
    $used_count = 0;
    
    // Check by user ID if logged in
    if ($current_user_id) {
        $used_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items oi
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE oi.order_item_type = 'coupon'
            AND oi.order_item_name = %s
            AND pm.meta_key = '_customer_user'
            AND pm.meta_value = %d
            AND p.post_status IN ('wc-completed', 'wc-processing')",
            $coupon_code,
            $current_user_id
        ));
    }
    
    // Also check by email if available (for both logged-in and guest users)
    if (!empty($current_user_email)) {
        $email_used_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items oi
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE oi.order_item_type = 'coupon'
            AND oi.order_item_name = %s
            AND pm.meta_key = '_billing_email'
            AND pm.meta_value = %s
            AND p.post_status IN ('wc-completed', 'wc-processing')",
            $coupon_code,
            $current_user_email
        ));
        
        $used_count = max($used_count, $email_used_count);
    }
    
    // Additional check: Look in our referrals table for completed referrals
    if ($current_user_id || !empty($current_user_email)) {
        $referral_table = $wpdb->prefix . 'wc_referrals';
        
        $referral_used_count = 0;
        
        if ($current_user_id) {
            $referral_used_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $referral_table 
                WHERE referee_id = %d 
                AND status = 'completed' 
                AND order_id IS NOT NULL",
                $current_user_id
            ));
        }
        
        if (!empty($current_user_email)) {
            $email_referral_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $referral_table 
                WHERE referee_email = %s 
                AND status = 'completed' 
                AND order_id IS NOT NULL",
                $current_user_email
            ));
            
            $referral_used_count = max($referral_used_count, $email_referral_count);
        }
        
        $used_count = max($used_count, $referral_used_count);
    }
    
    return $used_count == 0;
}

/**
 * Enhanced version of the original auto_apply_referral_coupon method
 * Add this to your WC_Referral_Coupon class to replace the existing method
 */
public function auto_apply_referral_coupon() {
    // Skip if cart is empty
    if (WC()->cart->is_empty()) {
        return;
    }
    
    $referral_code = '';
    
    // Check session for referral code
    if (isset($_SESSION['wc_referral_code'])) {
        $referral_code = $_SESSION['wc_referral_code'];
    } 
    // Check cookie for referral code
    elseif (isset($_COOKIE['wc_referral_code'])) {
        $referral_code = sanitize_text_field($_COOKIE['wc_referral_code']);
    }
    
    if (!empty($referral_code)) {
        $coupon_code = $this->create_referee_coupon($referral_code);
        
        // Check if coupon is already applied
        $applied_coupons = WC()->cart->get_applied_coupons();
        if (in_array($coupon_code, $applied_coupons)) {
            return; // Coupon already applied
        }
        
        // Check if current user can use this coupon
        if ($this->can_user_use_referral_coupon($coupon_code)) {
            $result = WC()->cart->apply_coupon($coupon_code);
            
            if ($result) {
                // Display a notice that coupon was applied
                wc_add_notice(__('Referral discount automatically applied!', 'wc-referral-system'), 'success');
            }
        } else {
            // User has already used this referral coupon
            // Clear the referral code to prevent future attempts
            if (isset($_SESSION['wc_referral_code'])) {
                unset($_SESSION['wc_referral_code']);
                unset($_SESSION['wc_referral_record_id']);
                unset($_SESSION['wc_referral_time']);
            }
            
            if (isset($_COOKIE['wc_referral_code'])) {
                setcookie('wc_referral_code', '', time() - 3600, '/');
            }
            
            // Optionally display a notice (only once per session)
            if (!isset($_SESSION['referral_used_notice_shown'])) {
                wc_add_notice(__('This referral discount has already been used.', 'wc-referral-system'), 'notice');
                $_SESSION['referral_used_notice_shown'] = true;
            }
        }
    }
}
    /**
     * Check if current user can use the coupon (hasn't used it before)
     */
    public function can_user_use_coupon($coupon_code) {
        $coupon = new WC_Coupon($coupon_code);
        if (!$coupon->get_id()) {
            return false;
        }
        
        $current_user_id = get_current_user_id();
        $current_user_email = '';
        
        // Get current user email
        if ($current_user_id) {
            $user = get_userdata($current_user_id);
            $current_user_email = $user->user_email;
        } else {
            // For guest users, try to get email from session or form data
            if (isset($_POST['billing_email'])) {
                $current_user_email = sanitize_email($_POST['billing_email']);
            }
        }
        
        // Check if user has already used this coupon
        global $wpdb;
        
        // Check by user ID if logged in
        if ($current_user_id) {
            $used_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items oi
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE oi.order_item_type = 'coupon'
                AND oim.meta_key = 'coupon_data'
                AND oim.meta_value LIKE %s
                AND pm.meta_key = '_customer_user'
                AND pm.meta_value = %d
                AND p.post_status IN ('wc-completed', 'wc-processing')",
                '%' . $wpdb->esc_like($coupon_code) . '%',
                $current_user_id
            ));
        } else {
            // For guest users, check by email if available
            if (!empty($current_user_email)) {
                $used_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items oi
                    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                    INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE oi.order_item_type = 'coupon'
                    AND oim.meta_key = 'coupon_data'
                    AND oim.meta_value LIKE %s
                    AND pm.meta_key = '_billing_email'
                    AND pm.meta_value = %s
                    AND p.post_status IN ('wc-completed', 'wc-processing')",
                    '%' . $wpdb->esc_like($coupon_code) . '%',
                    $current_user_email
                ));
            } else {
                // If no user ID or email, allow usage (guest checkout)
                return true;
            }
        }
        
        return $used_count == 0;
    }

    /**
     * Update referral status when order is completed
     */
    public function update_referral_on_order_completed($order_id) {
        if (!$order_id) return;
        
        $order = wc_get_order($order_id);
        $coupons = $order->get_coupon_codes();
        
        foreach ($coupons as $coupon_code) {
            $coupon = new WC_Coupon($coupon_code);
            $coupon_id = $coupon->get_id();
            
            $is_referral_coupon = get_post_meta($coupon_id, '_is_referral_coupon', true);
            
            if ($is_referral_coupon === 'yes') {
                $referral_code = get_post_meta($coupon_id, '_referral_code', true);
                
                if (!empty($referral_code)) {
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'wc_referrals';
                    
                    // Check if this specific user-referral combination already exists
                    $existing_record = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $table_name WHERE referral_code = %s AND (referee_id = %d OR referee_email = %s) AND order_id IS NOT NULL LIMIT 1",
                        $referral_code,
                        $order->get_customer_id(),
                        $order->get_billing_email()
                    ));
                    
                    if (!$existing_record) {
                        // Create new referral record for this user
                        $wpdb->insert(
                            $table_name,
                            array(
                                'referral_code' => $referral_code,
                                'referrer_id' => WC_Referral_Codes::instance()->get_referrer_id_from_code($referral_code),
                                'referee_id' => $order->get_customer_id(),
                                'referee_email' => $order->get_billing_email(),
                                'order_id' => $order_id,
                                'coupon_id' => $coupon_id,
                                'status' => 'completed',
                                'created_at' => current_time('mysql')
                            ),
                            array('%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s')
                        );
                        
                        // Create reward coupon for referrer
                        $this->create_referrer_reward_coupon($referral_code, $order_id);
                    }
                }
            }
        }
        
        // Also check session for referral code
        if (session_id() && isset($_SESSION['wc_referral_code']) && isset($_SESSION['wc_referral_record_id'])) {
            $referral_code = $_SESSION['wc_referral_code'];
            $referral_record_id = $_SESSION['wc_referral_record_id'];
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'wc_referrals';
            
            // Update referral record with order details
            $wpdb->update(
                $table_name,
                array(
                    'referee_id' => $order->get_customer_id(),
                    'referee_email' => $order->get_billing_email(),
                    'order_id' => $order_id,
                    'status' => 'completed'
                ),
                array('id' => $referral_record_id),
                array('%d', '%s', '%d', '%s'),
                array('%d')
            );
            
            // Create reward coupon for referrer
            $this->create_referrer_reward_coupon($referral_code, $order_id);
            
            // Clear session
            unset($_SESSION['wc_referral_code']);
            unset($_SESSION['wc_referral_record_id']);
            unset($_SESSION['wc_referral_time']);
        }
    }

    /**
     * Create reward coupon for referrer
     */
    public function create_referrer_reward_coupon($referral_code, $order_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_referrals';
        
        // Get referral record
        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE referral_code = %s AND order_id = %d LIMIT 1",
            $referral_code,
            $order_id
        ));
        
        if (!$referral) {
            return;
        }
        
        // Skip if reward coupon already created
        if (!empty($referral->reward_coupon_id)) {
            return;
        }
        
        // Generate a unique reward coupon code
        $reward_coupon_code = 'REWARD-' . strtoupper(substr(md5($referral_code . $order_id . time()), 0, 8));
        
        // Get reward amount and expiry days
        $reward_amount = get_option('wc_referral_referrer_reward', '15');
        $expiry_days = get_option('wc_referral_referrer_expiry_days', '60');
        
        // Create the coupon
        $coupon = array(
            'post_title' => $reward_coupon_code,
            'post_content' => 'Referral reward coupon',
            'post_status' => 'publish',
            'post_author' => 1,
            'post_type' => 'shop_coupon',
        );
        
        $reward_coupon_id = wp_insert_post($coupon);
        
        // Set coupon meta
        update_post_meta($reward_coupon_id, 'discount_type', 'percent');
        update_post_meta($reward_coupon_id, 'coupon_amount', $reward_amount);
        update_post_meta($reward_coupon_id, 'individual_use', 'yes');
        update_post_meta($reward_coupon_id, 'usage_limit', '1');
        update_post_meta($reward_coupon_id, 'expiry_date', date('Y-m-d', strtotime("+{$expiry_days} days")));
        update_post_meta($reward_coupon_id, '_is_reward_coupon', 'yes');
        update_post_meta($reward_coupon_id, '_referral_id', $referral->id);
        
        // Update referral record with reward coupon
        $wpdb->update(
            $table_name,
            array('reward_coupon_id' => $reward_coupon_id),
            array('id' => $referral->id),
            array('%d'),
            array('%d')
        );
        
        // Trigger reward email
        do_action('wc_referral_system_reward_earned', $referral, $reward_coupon_code);
        
        return $reward_coupon_id;
    }
}