<?php
/**
 * Handle AJAX requests for the referral plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    WP_Referral_Plugin
 * @subpackage WP_Referral_Plugin/includes
 */

/**
 * Handle AJAX requests for the referral plugin.
 *
 * This class handles all AJAX requests for the plugin, including login,
 * registration, and referral data retrieval.
 *
 * @since      1.0.0
 * @package    WP_Referral_Plugin
 * @subpackage WP_Referral_Plugin/includes
 * @author     Your Name
 */
class WC_Referral_Ajax_Handler {

    /**
     * Register all AJAX hooks.
     *
     * @since    1.0.0
     */
    public function register_ajax_hooks() {
        // Login and registration hooks
        add_action('wp_ajax_nopriv_wp_referral_check_email', array($this, 'check_email'));
        add_action('wp_ajax_nopriv_wp_referral_login', array($this, 'process_login'));
        add_action('wp_ajax_nopriv_wp_referral_register', array($this, 'process_registration'));
        
        // Referral data hooks
        add_action('wp_ajax_wp_referral_get_stats', array($this, 'get_referral_stats'));
        add_action('wp_ajax_wp_referral_get_referrals', array($this, 'get_referral_list'));
        
        // Common hooks
        add_action('wp_ajax_wp_referral_get_content', array($this, 'get_referral_content'));
        add_action('wp_ajax_nopriv_wp_referral_get_content', array($this, 'get_referral_content'));
    }

    /**
     * Check if an email exists in the database.
     *
     * @since    1.0.0
     */
    public function check_email() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp-referral-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-referral-plugin')));
        }
        
        // Get email from request
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if (empty($email)) {
            wp_send_json_error(array('message' => __('Email is required.', 'wp-referral-plugin')));
        }
        
        // Check if email exists
        $user = get_user_by('email', $email);
        
        if ($user) {
            wp_send_json_success(array(
                'exists' => true,
                'message' => __('Please enter your password to login.', 'wp-referral-plugin')
            ));
        } else {
            wp_send_json_success(array(
                'exists' => false,
                'message' => __('Please set a password to create your account.', 'wp-referral-plugin')
            ));
        }
    }

    /**
     * Process user login.
     *
     * @since    1.0.0
     */
    public function process_login() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp-referral-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-referral-plugin')));
        }
        
        // Get login data
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($email) || empty($password)) {
            wp_send_json_error(array('message' => __('Email and password are required.', 'wp-referral-plugin')));
        }
        
        // Attempt to log in
        $user = wp_authenticate($email, $password);
        
        if (is_wp_error($user)) {
            wp_send_json_error(array('message' => __('Invalid email or password.', 'wp-referral-plugin')));
        }
        
        // Log the user in
        wp_set_auth_cookie($user->ID, true);
        
        // Return success with referral content
        wp_send_json_success(array(
            'message' => __('Login successful.', 'wp-referral-plugin'),
            'content' => $this->get_referral_content_html()
        ));
    }

    /**
     * Process user registration.
     *
     * @since    1.0.0
     */
    public function process_registration() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp-referral-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-referral-plugin')));
        }
        
        // Get registration data
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($email) || empty($password)) {
            wp_send_json_error(array('message' => __('Email and password are required.', 'wp-referral-plugin')));
        }
        
        // Check if email already exists
        if (email_exists($email)) {
            wp_send_json_error(array('message' => __('Email already exists. Please login instead.', 'wp-referral-plugin')));
        }
        
        // Generate username from email
        $username = $this->generate_username_from_email($email);
        
        // Create the user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }
        
        // Set user role
        $user = new WP_User($user_id);
        $user->set_role('customer');
        
        // Log the user in
        wp_set_auth_cookie($user_id, true);
        
        // Check for referral cookie and record the referral
        $referral_code = isset($_COOKIE['wp_referral_code']) ? sanitize_text_field($_COOKIE['wp_referral_code']) : '';
        
        if (!empty($referral_code)) {
            $referral_manager = new WP_Referral_Manager();
            $referrer_id = $referral_manager->get_user_id_from_referral_code($referral_code);
            
            if ($referrer_id) {
                global $wpdb;
                
                // Record the referral
                $wpdb->insert(
                    $wpdb->prefix . 'referrals',
                    array(
                        'referrer_id' => $referrer_id,
                        'referred_id' => $user_id,
                        'referral_code' => $referral_code,
                        'referral_date' => current_time('mysql'),
                        'status' => 'pending'
                    ),
                    array('%d', '%d', '%s', '%s', '%s')
                );
                
                // Update referral stats
                $referral_manager->update_referral_stats($referrer_id);
            }
        }
        
        // Return success with referral content
        wp_send_json_success(array(
            'message' => __('Registration successful.', 'wp-referral-plugin'),
            'content' => $this->get_referral_content_html()
        ));
    }

    /**
     * Generate a username from email address.
     *
     * @since    1.0.0
     * @param    string    $email    User email address.
     * @return   string    Generated username.
     */
    private function generate_username_from_email($email) {
        // Get the part before @ in the email
        $username = strtolower(substr($email, 0, strpos($email, '@')));
        
        // Remove special characters
        $username = preg_replace('/[^a-z0-9]/', '', $username);
        
        // Make sure it's unique
        $base_username = $username;
        $i = 1;
        
        while (username_exists($username)) {
            $username = $base_username . $i;
            $i++;
        }
        
        return $username;
    }

    /**
     * Get referral stats for the current user.
     *
     * @since    1.0.0
     */
    public function get_referral_stats() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp-referral-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-referral-plugin')));
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to view referral stats.', 'wp-referral-plugin')));
        }
        
        $user_id = get_current_user_id();
        
        global $wpdb;
        
        // Get stats from the stats table
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}referral_stats WHERE user_id = %d",
            $user_id
        ));
        
        if (!$stats) {
            // Return empty stats
            $stats = array(
                'total_referrals' => 0,
                'successful_referrals' => 0,
                'total_rewards' => 0
            );
        }
        
        wp_send_json_success(array('stats' => $stats));
    }

    /**
     * Get referral list for the current user.
     *
     * @since    1.0.0
     */
    public function get_referral_list() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp-referral-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-referral-plugin')));
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to view referrals.', 'wp-referral-plugin')));
        }
        
        $user_id = get_current_user_id();
        
        global $wpdb;
        
        // Get referrals excluding placeholders
        $referrals = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.display_name as referred_name 
            FROM {$wpdb->prefix}referrals r 
            LEFT JOIN {$wpdb->users} u ON r.referred_id = u.ID 
            WHERE r.referrer_id = %d AND r.status != 'placeholder' 
            ORDER BY r.referral_date DESC",
            $user_id
        ));
        
        wp_send_json_success(array('referrals' => $referrals));
    }

    /**
     * Get referral content HTML.
     *
     * @since    1.0.0
     */
    public function get_referral_content() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp-referral-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-referral-plugin')));
        }
        
        wp_send_json_success(array('content' => $this->get_referral_content_html()));
    }

    /**
     * Get referral content HTML.
     *
     * @since    1.0.0
     * @return   string    The HTML content for the referral popup/widget.
     */
    private function get_referral_content_html() {
        ob_start();
        
        if (is_user_logged_in()) {
            // For logged-in users, show referral content
            $referral_manager = new WP_Referral_Manager();
            $referral_link = $referral_manager->get_user_referral_link();
            
            ?>
            <div class="wp-referral-content">
                <h3><?php _e('Share and Earn Rewards', 'wp-referral-plugin'); ?></h3>
                <p><?php _e('Share your unique link and your friends will get 10% off their first order!', 'wp-referral-plugin'); ?></p>
                <p><?php _e('You\'ll earn a 15% discount coupon when they make a purchase.', 'wp-referral-plugin'); ?></p>
                
                <div class="wp-referral-link-container">
                    <input type="text" class="wp-referral-link" value="<?php echo esc_attr($referral_link); ?>" readonly>
                    <button class="wp-referral-copy-link"><?php _e('Copy Link', 'wp-referral-plugin'); ?></button>
                </div>
                
                <div class="wp-referral-share-buttons">
                    <button class="wp-referral-share-facebook" data-link="<?php echo esc_attr($referral_link); ?>">
                        <?php _e('Share on Facebook', 'wp-referral-plugin'); ?>
                    </button>
                    <button class="wp-referral-share-twitter" data-link="<?php echo esc_attr($referral_link); ?>">
                        <?php _e('Share on Twitter', 'wp-referral-plugin'); ?>
                    </button>
                    <button class="wp-referral-share-email" data-link="<?php echo esc_attr($referral_link); ?>">
                        <?php _e('Share via Email', 'wp-referral-plugin'); ?>
                    </button>
                </div>
                
                <div class="wp-referral-stats-preview">
                    <h4><?php _e('Your Referral Stats', 'wp-referral-plugin'); ?></h4>
                    <div class="wp-referral-stats-loading"><?php _e('Loading stats...', 'wp-referral-plugin'); ?></div>
                    <div class="wp-referral-stats-content" style="display: none;"></div>
                </div>
                
                <p class="wp-referral-dashboard-link">
                    <a href="<?php echo esc_url(wc_get_account_endpoint_url('referrals')); ?>">
                        <?php _e('View your full referral dashboard', 'wp-referral-plugin'); ?>
                    </a>
                </p>
            </div>
            <?php
        } else {
            // For non-logged-in users, show email form
            ?>
            <div class="wp-referral-login-register">
                <h3><?php _e('Join Our Referral Program', 'wp-referral-plugin'); ?></h3>
                <p><?php _e('Share with friends and earn rewards!', 'wp-referral-plugin'); ?></p>
                
                <form class="wp-referral-email-form">
                    <div class="wp-referral-form-group">
                        <label for="wp-referral-email"><?php _e('Email Address', 'wp-referral-plugin'); ?></label>
                        <input type="email" id="wp-referral-email" name="email" required>
                    </div>
                    
                    <div class="wp-referral-form-group wp-referral-password-group" style="display: none;">
                        <label for="wp-referral-password" class="wp-referral-password-label"></label>
                        <input type="password" id="wp-referral-password" name="password">
                    </div>
                    
                    <div class="wp-referral-form-message"></div>
                    
                    <button type="submit" class="wp-referral-continue-button">
                        <?php _e('Continue', 'wp-referral-plugin'); ?>
                    </button>
                </form>
            </div>
            <?php
        }
        
        return ob_get_clean();
    }
}