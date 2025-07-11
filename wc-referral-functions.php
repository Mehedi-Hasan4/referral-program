<?php
/**
 * Referral System Functions
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AJAX function to check if email exists
 */
function wc_referral_check_email_exists() {
    check_ajax_referer('wc-referral-popup-nonce', 'nonce');
    
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    
    if (empty($email) || !is_email($email)) {
        wp_send_json_error(array(
            'message' => __('Please enter a valid email address.', 'wc-referral-system')
        ));
    }
    
    $user = get_user_by('email', $email);
    
    if ($user) {
        wp_send_json_success(array('exists' => true));
    } else {
        wp_send_json_error(array('exists' => false));
    }
}
add_action('wp_ajax_check_email_exists', 'wc_referral_check_email_exists');
add_action('wp_ajax_nopriv_check_email_exists', 'wc_referral_check_email_exists');

/**
 * AJAX login handler
 */
function wc_referral_ajax_login() {
    check_ajax_referer('wc-referral-popup-nonce', 'nonce');
    
    $username = isset($_POST['username']) ? sanitize_email($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($username) || empty($password)) {
        wp_send_json_error(array(
            'message' => __('Username and password are required.', 'wc-referral-system')
        ));
    }
    
    $user = wp_authenticate($username, $password);
    
    if (is_wp_error($user)) {
        wp_send_json_error(array(
            'message' => __('Invalid username or password.', 'wc-referral-system')
        ));
    } else {
        wp_set_auth_cookie($user->ID, true);
        wp_set_current_user($user->ID);
        
        wp_send_json_success(array(
            'redirect' => false,
            'user_id' => $user->ID
        ));
    }
}
add_action('wp_ajax_ajax_login', 'wc_referral_ajax_login');
add_action('wp_ajax_nopriv_ajax_login', 'wc_referral_ajax_login');

/**
 * AJAX registration handler
 */
function wc_referral_ajax_register() {
    check_ajax_referer('wc-referral-popup-nonce', 'nonce');
    
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($email) || !is_email($email)) {
        wp_send_json_error(array(
            'message' => __('Please enter a valid email address.', 'wc-referral-system')
        ));
    }
    
    if (empty($password) || strlen($password) < 6) {
        wp_send_json_error(array(
            'message' => __('Password must be at least 6 characters long.', 'wc-referral-system')
        ));
    }
    
    // Check if user exists
    if (email_exists($email)) {
        wp_send_json_error(array(
            'message' => __('An account with this email already exists.', 'wc-referral-system')
        ));
    }
    
    // Generate username from email
    $username = sanitize_user(current(explode('@', $email)), true);
    
    // Ensure username is unique
    $append = 1;
    $username_check = $username;
    
    while (username_exists($username_check)) {
        $username_check = $username . $append;
        $append++;
    }
    
    $username = $username_check;
    
    // Register user
    $user_id = wc_create_new_customer($email, $username, $password);
    
    if (is_wp_error($user_id)) {
        wp_send_json_error(array(
            'message' => $user_id->get_error_message()
        ));
    } else {
        // Log the user in
        wp_set_auth_cookie($user_id, true);
        wp_set_current_user($user_id);
        
        // Check for referral code in cookies
        if (isset($_COOKIE['wc_referral_code'])) {
            $referral_code = sanitize_text_field($_COOKIE['wc_referral_code']);
            
            // Record this referral
            if (function_exists('WC_Referral_Codes') && method_exists(WC_Referral_Codes::instance(), 'record_referral')) {
                WC_Referral_Codes::instance()->record_referral($referral_code, $user_id);
            }
        }
        
        // Generate user's referral code
        if (function_exists('WC_Referral_Codes') && method_exists(WC_Referral_Codes::instance(), 'generate_user_referral_code')) {
            WC_Referral_Codes::instance()->generate_user_referral_code($user_id);
        }
        
        // Send welcome email with account info
        wc_referral_send_welcome_email($user_id, $username, $password);
        
        wp_send_json_success(array(
            'redirect' => false,
            'user_id' => $user_id
        ));
    }
}
add_action('wp_ajax_ajax_register', 'wc_referral_ajax_register');
add_action('wp_ajax_nopriv_ajax_register', 'wc_referral_ajax_register');

/**
 * Send welcome email with account details
 */
function wc_referral_send_welcome_email($user_id, $username, $password) {
    $user = get_user_by('id', $user_id);
    if (!$user) return;
    
    $referral_link = '';
    if (function_exists('WC_Referral_Codes') && method_exists(WC_Referral_Codes::instance(), 'get_user_referral_link')) {
        $referral_link = WC_Referral_Codes::instance()->get_user_referral_link($user_id);
    }
    
    $discount = get_option('wc_referral_referee_discount', '10');
    $reward = get_option('wc_referral_referrer_reward', '15');
    
    $to = $user->user_email;
    $subject = sprintf(__('Welcome to %s - Your Account Details', 'wc-referral-system'), get_bloginfo('name'));
    
    // Email content
    $message = sprintf(__('Hello %s,', 'wc-referral-system'), $user->display_name) . "\n\n";
    $message .= sprintf(__('Thank you for joining %s! Your account has been created successfully.', 'wc-referral-system'), get_bloginfo('name')) . "\n\n";
    $message .= __('Your account details:', 'wc-referral-system') . "\n";
    $message .= sprintf(__('Username: %s', 'wc-referral-system'), $username) . "\n";
    $message .= sprintf(__('Password: %s', 'wc-referral-system'), $password) . "\n\n";
    
    if (!empty($referral_link)) {
        $message .= __('We\'ve also created a personal referral link for you:', 'wc-referral-system') . "\n";
        $message .= $referral_link . "\n\n";
        $message .= sprintf(__('Share this link with friends and they\'ll get %s%% off their first purchase. When they make an order, you\'ll earn a %s%% discount coupon!', 'wc-referral-system'), $discount, $reward) . "\n\n";
    }
    
    $message .= sprintf(__('You can access your account here: %s', 'wc-referral-system'), wc_get_page_permalink('myaccount')) . "\n\n";
    $message .= sprintf(__('Thank you for shopping with %s!', 'wc-referral-system'), get_bloginfo('name'));
    
    // Send email
    wp_mail($to, $subject, $message);
}