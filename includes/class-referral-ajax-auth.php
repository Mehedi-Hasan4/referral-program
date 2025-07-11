<?php
/**
 * AJAX Authentication Handlers for Referral Widget
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Referral_Ajax_Auth {
    
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
        // Register AJAX hooks
        add_action('wp_ajax_wc_referral_check_email', array($this, 'check_email'));
        add_action('wp_ajax_nopriv_wc_referral_check_email', array($this, 'check_email'));
        
        add_action('wp_ajax_wc_referral_login', array($this, 'login_user'));
        add_action('wp_ajax_nopriv_wc_referral_login', array($this, 'login_user'));
        
        add_action('wp_ajax_wc_referral_register', array($this, 'register_user'));
        add_action('wp_ajax_nopriv_wc_referral_register', array($this, 'register_user'));
    }

    /**
     * Check if email exists
     */
    public function check_email() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc-referral-system-nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed. Please refresh the page and try again.', 'wc-referral-system')
            ));
        }

        $email = sanitize_email($_POST['email']);
        
        // Validate email
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array(
                'message' => __('Please enter a valid email address.', 'wc-referral-system')
            ));
        }

        // Check if user exists
        $user = get_user_by('email', $email);
        
        wp_send_json_success(array(
            'exists' => (bool) $user,
            'message' => $user ? 
                __('Welcome back! Please enter your password.', 'wc-referral-system') : 
                __('Create your account to get started.', 'wc-referral-system')
        ));
    }

    /**
     * Login user
     */
    public function login_user() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc-referral-system-nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed. Please refresh the page and try again.', 'wc-referral-system')
            ));
        }

        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        
        // Validate inputs
        if (empty($email) || empty($password)) {
            wp_send_json_error(array(
                'message' => __('Please fill in all fields.', 'wc-referral-system')
            ));
        }

        if (!is_email($email)) {
            wp_send_json_error(array(
                'message' => __('Please enter a valid email address.', 'wc-referral-system')
            ));
        }

        // Get user by email
        $user = get_user_by('email', $email);
        
        if (!$user) {
            wp_send_json_error(array(
                'message' => __('No account found with this email address.', 'wc-referral-system')
            ));
        }

        // Attempt login
        $creds = array(
            'user_login'    => $user->user_login,
            'user_password' => $password,
            'remember'      => true,
        );

        $user_login = wp_signon($creds, false);
        
        if (is_wp_error($user_login)) {
            wp_send_json_error(array(
                'message' => __('Incorrect password. Please try again.', 'wc-referral-system')
            ));
        }

        // Login successful - generate referral content
        $content = $this->get_referral_content($user_login->ID);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Welcome back, %s!', 'wc-referral-system'), $user_login->display_name),
            'content' => $content
        ));
    }

    /**
     * Register user
     */
    public function register_user() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc-referral-system-nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed. Please refresh the page and try again.', 'wc-referral-system')
            ));
        }

        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        
        // Validate inputs
        if (empty($email) || empty($password)) {
            wp_send_json_error(array(
                'message' => __('Please fill in all fields.', 'wc-referral-system')
            ));
        }

        if (!is_email($email)) {
            wp_send_json_error(array(
                'message' => __('Please enter a valid email address.', 'wc-referral-system')
            ));
        }

        if (strlen($password) < 6) {
            wp_send_json_error(array(
                'message' => __('Password must be at least 6 characters long.', 'wc-referral-system')
            ));
        }

        // Check if user already exists
        if (email_exists($email)) {
            wp_send_json_error(array(
                'message' => __('An account with this email already exists. Please try logging in instead.', 'wc-referral-system')
            ));
        }

        // Generate username from email
        $username = $this->generate_username_from_email($email);
        
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array(
                'message' => $user_id->get_error_message()
            ));
        }

        // Auto-login the user
        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => true,
        );

        $user_login = wp_signon($creds, false);
        
        if (is_wp_error($user_login)) {
            wp_send_json_error(array(
                'message' => __('Account created but login failed. Please try logging in manually.', 'wc-referral-system')
            ));
        }

        // Set user role to customer if WooCommerce exists
        if (function_exists('wc_create_new_customer_account')) {
            $user = new WP_User($user_id);
            $user->set_role('customer');
        }

        // Send welcome email (optional)
        wp_new_user_notification($user_id, null, 'user');

        // Generate referral content
        $content = $this->get_referral_content($user_id);
        
        wp_send_json_success(array(
            'message' => __('Account created successfully! Welcome to our referral program.', 'wc-referral-system'),
            'content' => $content
        ));
    }

    /**
     * Generate referral content for logged-in user
     */
    private function get_referral_content($user_id) {
        $discount = get_option('wc_referral_referee_discount', '10');
        $reward = get_option('wc_referral_referrer_reward', '15');
        $referral_link = WC_Referral_Codes::instance()->get_user_referral_link($user_id);
        
        ob_start();
        ?>
        <div class="wc-referral-success-content">
            <h3><?php _e('🎉 Your Referral Link is Ready!', 'wc-referral-system'); ?></h3>
            
            <p class="wc-referral-info">
                <?php printf(__('Share your unique link and your friends will get %s%% off their first order!', 'wc-referral-system'), $discount); ?>
            </p>
            
            <p class="wc-referral-reward">
                <?php printf(__('You\'ll earn a %s%% discount coupon when they make a purchase.', 'wc-referral-system'), $reward); ?>
            </p>
            
            <div class="wc-referral-link-container">
                <input type="text" id="wc-referral-link" value="<?php echo esc_url($referral_link); ?>" readonly>
                <button id="wc-referral-copy" class="wc-referral-copy-btn"><?php _e('Copy', 'wc-referral-system'); ?></button>
            </div>
            
            <div class="wc-referral-social-share">
                <p><?php _e('Share via:', 'wc-referral-system'); ?></p>
                <div class="wc-referral-social-buttons">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_link); ?>" 
                       target="_blank" class="wc-referral-social-btn facebook">
                        <?php _e('Facebook', 'wc-referral-system'); ?>
                    </a>
                    <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode(sprintf(__('Use my referral link to get %s%% off your first order!', 'wc-referral-system'), $discount)); ?>&url=<?php echo urlencode($referral_link); ?>" 
                       target="_blank" class="wc-referral-social-btn twitter">
                        <?php _e('Twitter', 'wc-referral-system'); ?>
                    </a>
                    <a href="mailto:?subject=<?php echo urlencode(__('Get a discount on your order', 'wc-referral-system')); ?>&body=<?php echo urlencode(sprintf(__('Use my referral link to get %s%% off your first order: %s', 'wc-referral-system'), $discount, $referral_link)); ?>" 
                       class="wc-referral-social-btn email">
                        <?php _e('Email', 'wc-referral-system'); ?>
                    </a>
                    <a href="https://wa.me/?text=<?php echo urlencode(sprintf(__('Use my referral link to get %s%% off your first order: %s', 'wc-referral-system'), $discount, $referral_link)); ?>" 
                       target="_blank" class="wc-referral-social-btn whatsapp">
                        <?php _e('WhatsApp', 'wc-referral-system'); ?>
                    </a>
                </div>
            </div>
            
            <div class="wc-referral-footer">
                <a href="<?php echo wc_get_endpoint_url('referrals', '', wc_get_page_permalink('myaccount')); ?>" 
                   class="wc-referral-view-link">
                    <?php _e('View your referrals →', 'wc-referral-system'); ?>
                </a>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Generate username from email
     */
    private function generate_username_from_email($email) {
        $username = sanitize_user(current(explode('@', $email)), true);
        
        // If username exists, append numbers
        if (username_exists($username)) {
            $i = 1;
            $new_username = $username;
            
            while (username_exists($new_username)) {
                $new_username = $username . $i;
                $i++;
            }
            
            $username = $new_username;
        }
        
        return $username;
    }

    /**
     * Enqueue scripts and localize data
     */
    public static function enqueue_scripts() {
        if (!wp_script_is('jquery', 'enqueued')) {
            wp_enqueue_script('jquery');
        }

        // Localize script with AJAX data
        wp_localize_script('jquery', 'wc_referral_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc-referral-system-nonce'),
            'messages' => array(
                'error' => __('Something went wrong. Please try again.', 'wc-referral-system'),
                'copied' => __('Link copied to clipboard!', 'wc-referral-system'),
            ),
            'has_referral_code' => isset($_GET['ref']) ? true : false
        ));
    }
}

// Initialize the class
WC_Referral_Ajax_Auth::instance();

// Hook to enqueue scripts
add_action('wp_enqueue_scripts', array('WC_Referral_Ajax_Auth', 'enqueue_scripts'));