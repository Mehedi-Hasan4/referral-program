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
        wp_send_json_success(array('exists' => false));
    }
}
add_action('wp_ajax_check_email_exists', 'wc_referral_check_email_exists');
add_action('wp_ajax_nopriv_check_email_exists', 'wc_referral_check_email_exists');

/**
 * AJAX login handler
 */
function wc_referral_ajax_login() {
    check_ajax_referer('wc-referral-popup-nonce', 'nonce');
    
    $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    $credentials = array(
        'user_login' => $username,
        'user_password' => $password,
        'remember' => true
    );
    
    $user = wp_signon($credentials, false);
    
    if (is_wp_error($user)) {
        wp_send_json_error(array(
            'message' => $user->get_error_message()
        ));
    } else {
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        wp_send_json_success(array(
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
    
    if (empty($password)) {
        wp_send_json_error(array(
            'message' => __('Please enter a password.', 'wc-referral-system')
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
    
    // Create new user
    $new_user_id = wp_create_user($username, $password, $email);
    
    if (is_wp_error($new_user_id)) {
        wp_send_json_error(array(
            'message' => $new_user_id->get_error_message()
        ));
    } else {
        // Log the user in
        wp_set_current_user($new_user_id);
        wp_set_auth_cookie($new_user_id);
        
        // Generate referral code
        WC_Referral_Codes::instance()->generate_user_referral_code($new_user_id);
        
        // Send welcome email
        wc_referral_send_welcome_email($new_user_id, $username, $password);
        
        wp_send_json_success(array(
            'user_id' => $new_user_id
        ));
    }
}
add_action('wp_ajax_ajax_register', 'wc_referral_ajax_register');
add_action('wp_ajax_nopriv_ajax_register', 'wc_referral_ajax_register');

/**
 * Send welcome email
 */
function wc_referral_send_welcome_email($user_id, $username, $password) {
    $user = get_user_by('id', $user_id);
    if (!$user) return;
    
    $to = $user->user_email;
    $subject = sprintf(__('Welcome to %s', 'wc-referral-system'), get_bloginfo('name'));
    
    $referral_link = WC_Referral_Codes::instance()->get_user_referral_link($user_id);
    $discount = get_option('wc_referral_referee_discount', '10');
    $reward = get_option('wc_referral_referrer_reward', '15');
    
    $message = sprintf(__('Hello %s,', 'wc-referral-system'), $user->display_name) . "\n\n";
    $message .= sprintf(__('Thank you for creating an account on %s. Your account has been created successfully.', 'wc-referral-system'), get_bloginfo('name')) . "\n\n";
    $message .= __('Your account details:', 'wc-referral-system') . "\n";
    $message .= sprintf(__('Username: %s', 'wc-referral-system'), $username) . "\n";
    $message .= sprintf(__('Password: %s', 'wc-referral-system'), $password) . "\n\n";
    $message .= __('You can now participate in our referral program! Here\'s your personal referral link:', 'wc-referral-system') . "\n";
    $message .= $referral_link . "\n\n";
    $message .= sprintf(__('Share this link with friends and they\'ll get %s%% off their first purchase. When they complete an order, you\'ll earn a %s%% discount on your next purchase!', 'wc-referral-system'), $discount, $reward) . "\n\n";
    $message .= sprintf(__('Login to your account: %s', 'wc-referral-system'), wc_get_page_permalink('myaccount')) . "\n\n";
    $message .= sprintf(__('Thank you for shopping with %s!', 'wc-referral-system'), get_bloginfo('name'));
    
    wp_mail($to, $subject, $message);
}