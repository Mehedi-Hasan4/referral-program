<?php
/**
 * WooCommerce Referral AJAX Handlers
 * File: includes/class-wc-referral-ajax.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Referral_Ajax {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX actions for both logged in and non-logged in users
        add_action('wp_ajax_check_referral_email', array($this, 'check_referral_email'));
        add_action('wp_ajax_nopriv_check_referral_email', array($this, 'check_referral_email'));
        
        add_action('wp_ajax_process_referral_login', array($this, 'process_referral_login'));
        add_action('wp_ajax_nopriv_process_referral_login', array($this, 'process_referral_login'));
        
        add_action('wp_ajax_process_referral_registration', array($this, 'process_referral_registration'));
        add_action('wp_ajax_nopriv_process_referral_registration', array($this, 'process_referral_registration'));
        
        add_action('wp_ajax_get_referral_content', array($this, 'get_referral_content'));
        add_action('wp_ajax_nopriv_get_referral_content', array($this, 'get_referral_content'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add popup to footer
        add_action('wp_footer', array($this, 'add_referral_popup'));
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_script('wc-referral-popup', plugin_dir_url(__FILE__) . 'assets/js/referral-popup.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('wc-referral-popup', plugin_dir_url(__FILE__) . 'assets/css/referral-popup.css', array(), '1.0.0');
        
        // Localize script for AJAX
        wp_localize_script('wc-referral-popup', 'wc_referral_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_referral_nonce'),
            'is_logged_in' => is_user_logged_in()
        ));
    }
    
    /**
     * Add referral popup HTML to footer
     */
    public function add_referral_popup() {
        ?>
        <div id="wc-referral-popup" class="wc-referral-popup" style="display: none;">
            <div class="wc-referral-popup-overlay"></div>
            <div class="wc-referral-popup-content">
                <div class="wc-referral-popup-header">
                    <h3 id="popup-title">Referral Program</h3>
                    <button class="wc-referral-close">&times;</button>
                </div>
                <div class="wc-referral-popup-body">
                    <div class="wc-referral-loading" style="display: none;">
                        <div class="spinner"></div>
                        <p>Loading...</p>
                    </div>
                    
                    <!-- Email Input Step -->
                    <div id="email-step" class="referral-step">
                        <p>Enter your email to get started with our referral program:</p>
                        <form id="email-form">
                            <div class="form-group">
                                <input type="email" id="user-email" name="email" placeholder="Enter your email" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Continue</button>
                        </form>
                    </div>
                    
                    <!-- Login Step -->
                    <div id="login-step" class="referral-step" style="display: none;">
                        <p id="login-message">Welcome back! Please enter your password:</p>
                        <form id="login-form">
                            <div class="form-group">
                                <input type="email" id="login-email" name="email" readonly>
                            </div>
                            <div class="form-group">
                                <input type="password" id="login-password" name="password" placeholder="Enter your password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Login</button>
                            <button type="button" class="btn btn-secondary" id="back-to-email">Back</button>
                        </form>
                    </div>
                    
                    <!-- Registration Step -->
                    <div id="register-step" class="referral-step" style="display: none;">
                        <p id="register-message">New here? Let's create your account!</p>
                        <form id="register-form">
                            <div class="form-group">
                                <input type="email" id="register-email" name="email" readonly>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="text" id="first-name" name="first_name" placeholder="First Name" required>
                                </div>
                                <div class="form-group">
                                    <input type="text" id="last-name" name="last_name" placeholder="Last Name" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <input type="password" id="register-password" name="password" placeholder="Create Password (min 6 characters)" required minlength="6">
                            </div>
                            <button type="submit" class="btn btn-primary">Create Account</button>
                            <button type="button" class="btn btn-secondary" id="back-to-email-register">Back</button>
                        </form>
                    </div>
                    
                    <!-- Referral Content Step -->
                    <div id="referral-content" class="referral-step" style="display: none;">
                        <div class="referral-dashboard">
                            <div class="referral-stats">
                                <div class="stat-card">
                                    <h4>Total Referrals</h4>
                                    <span class="stat-number" id="total-referrals">0</span>
                                </div>
                                <div class="stat-card">
                                    <h4>Total Earnings</h4>
                                    <span class="stat-number" id="total-earnings">$0.00</span>
                                </div>
                                <div class="stat-card">
                                    <h4>Pending Commission</h4>
                                    <span class="stat-number" id="pending-commission">$0.00</span>
                                </div>
                            </div>
                            
                            <div class="referral-link-section">
                                <h4>Your Referral Link</h4>
                                <div class="referral-link-container">
                                    <input type="text" id="referral-link" readonly>
                                    <button class="btn btn-copy" id="copy-link">Copy</button>
                                </div>
                                <div class="share-buttons">
                                    <button class="btn btn-facebook" id="share-facebook">Share on Facebook</button>
                                    <button class="btn btn-twitter" id="share-twitter">Share on Twitter</button>
                                    <button class="btn btn-whatsapp" id="share-whatsapp">Share on WhatsApp</button>
                                    <button class="btn btn-email" id="share-email">Share via Email</button>
                                </div>
                            </div>
                            
                            <div class="recent-referrals">
                                <h4>Recent Referrals</h4>
                                <div id="recent-referrals-list">
                                    <!-- Will be populated via AJAX -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="error-message" id="error-message" style="display: none;"></div>
                    <div class="success-message" id="success-message" style="display: none;"></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Check if email exists in database
     */
    public function check_referral_email() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_referral_nonce')) {
            wp_die('Security check failed');
        }
        
        $email = sanitize_email($_POST['email']);
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email address.'));
        }
        
        // Check if user exists
        $user = get_user_by('email', $email);
        
        if ($user) {
            wp_send_json_success(array(
                'action' => 'login',
                'message' => 'Welcome back! Please enter your password.'
            ));
        } else {
            wp_send_json_success(array(
                'action' => 'register',
                'message' => 'New here? Let\'s create your account!'
            ));
        }
    }
    
    /**
     * Process user login
     */
    public function process_referral_login() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_referral_nonce')) {
            wp_die('Security check failed');
        }
        
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        
        if (!is_email($email) || empty($password)) {
            wp_send_json_error(array('message' => 'Email and password are required.'));
        }
        
        // Attempt to log in user
        $credentials = array(
            'user_login'    => $email,
            'user_password' => $password,
            'remember'      => true,
        );
        
        $user = wp_signon($credentials, false);
        
        if (is_wp_error($user)) {
            wp_send_json_error(array('message' => 'Invalid email or password.'));
        }
        
        // Set current user
        wp_set_current_user($user->ID);
        
        // Create or update referral data
        $this->ensure_user_referral_data($user->ID);
        
        wp_send_json_success(array('message' => 'Login successful! Loading your referral dashboard...'));
    }
    
    /**
     * Process user registration
     */
    public function process_referral_registration() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_referral_nonce')) {
            wp_die('Security check failed');
        }
        
        $email = sanitize_email($_POST['email']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $password = $_POST['password'];
        
        // Validation
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email address.'));
        }
        
        if (empty($first_name) || empty($last_name)) {
            wp_send_json_error(array('message' => 'First and last name are required.'));
        }
        
        if (strlen($password) < 6) {
            wp_send_json_error(array('message' => 'Password must be at least 6 characters long.'));
        }
        
        // Check if user already exists
        if (get_user_by('email', $email)) {
            wp_send_json_error(array('message' => 'An account with this email already exists.'));
        }
        
        // Create user
        $user_id = wp_create_user($email, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => 'Failed to create account. Please try again.'));
        }
        
        // Update user meta
        wp_update_user(array(
            'ID'         => $user_id,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'display_name' => $first_name . ' ' . $last_name,
        ));
        
        // Log in the user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        // Create referral data
        $this->ensure_user_referral_data($user_id);
        
        // Send welcome email (optional)
        $this->send_welcome_email($user_id);
        
        wp_send_json_success(array('message' => 'Account created successfully! Loading your referral dashboard...'));
    }
    
    /**
     * Get referral content for logged in users
     */
    public function get_referral_content() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_referral_nonce')) {
            wp_die('Security check failed');
        }
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Please log in to access referral content.'));
        }
        
        // Ensure user has referral data
        $this->ensure_user_referral_data($user_id);
        
        // Get referral data
        $referral_code = get_user_meta($user_id, 'wc_referral_code', true);
        $referral_link = $this->generate_referral_link($referral_code);
        
        // Get referral statistics
        $stats = $this->get_user_referral_stats($user_id);
        
        // Get recent referrals
        $recent_referrals = $this->get_recent_referrals($user_id);
        
        $data = array(
            'referral_code'    => $referral_code,
            'referral_link'    => $referral_link,
            'total_referrals'  => $stats['total_referrals'],
            'total_earnings'   => wc_price($stats['total_earnings']),
            'pending_commission' => wc_price($stats['pending_commission']),
            'recent_referrals' => $recent_referrals,
        );
        
        wp_send_json_success($data);
    }
    
    /**
     * Ensure user has referral data
     */
    private function ensure_user_referral_data($user_id) {
        $referral_code = get_user_meta($user_id, 'wc_referral_code', true);
        
        if (empty($referral_code)) {
            // Generate unique referral code
            $referral_code = $this->generate_referral_code($user_id);
            update_user_meta($user_id, 'wc_referral_code', $referral_code);
        }
        
        // Initialize other referral meta if needed
        if (!get_user_meta($user_id, 'wc_referral_earnings', true)) {
            update_user_meta($user_id, 'wc_referral_earnings', 0);
        }
        
        if (!get_user_meta($user_id, 'wc_referral_count', true)) {
            update_user_meta($user_id, 'wc_referral_count', 0);
        }
    }
    
    /**
     * Generate unique referral code
     */
    private function generate_referral_code($user_id) {
        $user = get_user_by('ID', $user_id);
        $base_code = strtoupper(substr($user->user_login, 0, 3) . $user_id);
        
        // Ensure uniqueness
        $code = $base_code;
        $counter = 1;
        
        while ($this->referral_code_exists($code)) {
            $code = $base_code . $counter;
            $counter++;
        }
        
        return $code;
    }
    
    /**
     * Check if referral code exists
     */
    private function referral_code_exists($code) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'wc_referral_code' AND meta_value = %s",
            $code
        ));
        
        return $result > 0;
    }
    
    /**
     * Generate referral link
     */
    private function generate_referral_link($referral_code) {
        return add_query_arg('ref', $referral_code, home_url());
    }
    
    /**
     * Get user referral statistics
     */
    private function get_user_referral_stats($user_id) {
        global $wpdb;
        
        // Get referral count
        $total_referrals = get_user_meta($user_id, 'wc_referral_count', true) ?: 0;
        
        // Get earnings from custom table or meta
        $total_earnings = get_user_meta($user_id, 'wc_referral_earnings', true) ?: 0;
        $pending_commission = get_user_meta($user_id, 'wc_referral_pending', true) ?: 0;
        
        return array(
            'total_referrals' => $total_referrals,
            'total_earnings' => $total_earnings,
            'pending_commission' => $pending_commission
        );
    }
    
    /**
     * Get recent referrals
     */
    private function get_recent_referrals($user_id, $limit = 5) {
        global $wpdb;
        
        // This would typically query a custom referrals table
        // For now, return sample data structure
        return array(
            array(
                'date' => date('Y-m-d H:i:s'),
                'email' => 'example@email.com',
                'status' => 'pending',
                'commission' => 10.00
            )
        );
    }
    
    /**
     * Send welcome email
     */
    private function send_welcome_email($user_id) {
        $user = get_user_by('ID', $user_id);
        $referral_code = get_user_meta($user_id, 'wc_referral_code', true);
        $referral_link = $this->generate_referral_link($referral_code);
        
        $subject = 'Welcome to our Referral Program!';
        $message = "Hi {$user->first_name},\n\n";
        $message .= "Welcome to our referral program! Your unique referral code is: {$referral_code}\n\n";
        $message .= "Your referral link: {$referral_link}\n\n";
        $message .= "Start sharing and earn commissions today!\n\n";
        $message .= "Best regards,\nThe Team";
        
        wp_mail($user->user_email, $subject, $message);
    }
}

// Initialize the class
new WC_Referral_Ajax();