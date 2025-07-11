<?php
/**
 * Referral Widget Class
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Referral_Widget {
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
        add_action('wp_footer', array($this, 'render_floating_widget'));
        add_action('wp_footer', array($this, 'render_referral_popup_for_widget'));
        add_action('wp_ajax_get_referral_link', array($this, 'ajax_get_referral_link'));
        add_action('wp_ajax_nopriv_get_referral_link', array($this, 'ajax_get_referral_link'));
    }

    /**
     * Render referral popup for widget use
     */
    public function render_referral_popup_for_widget() {
        // Skip if admin
        if (is_admin()) {
            return;
        }
        
        // Check if the frontend class exists and call its popup method
        if (class_exists('WC_Referral_Frontend')) {
            $frontend = WC_Referral_Frontend::instance();
            if (method_exists($frontend, 'render_referral_popup')) {
                $frontend->render_referral_popup();
            }
        }
    }
    public function ajax_check_email() {
        check_ajax_referer('wc-referral-system-nonce', 'nonce');
        if ( empty($_POST['email']) || !is_email($_POST['email']) ) {
            wp_send_json_error(['message' => __('Invalid email address.', 'wc-referral-system')]);
        }

        $email = sanitize_email($_POST['email']);
        $user = get_user_by('email', $email);

        if ( $user ) {
            wp_send_json_success([
                'exists'  => true,
                'message' => __('Email found. Please enter your password to log in.', 'wc-referral-system')
            ]);
        } else {
            wp_send_json_success([
                'exists'  => false,
                'message' => __('No account found. Please set a password to register.', 'wc-referral-system')
            ]);
        }
    }

    public function ajax_login() {
        check_ajax_referer('wc-referral-system-nonce', 'nonce');
        $email    = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ( empty($email) || empty($password) ) {
            wp_send_json_error(['message' => __('Please fill in all fields.', 'wc-referral-system')]);
        }

        $creds = [
            'user_login'    => $email,
            'user_password' => $password,
            'remember'      => true,
        ];

        $user = wp_signon($creds, is_ssl());

        if ( is_wp_error($user) ) {
            wp_send_json_error(['message' => $user->get_error_message()]);
        }

        // Logged in successfully — get referral widget HTML
        ob_start();
        $this->render_user_referral_block();
        $html = ob_get_clean();

        wp_send_json_success([
            'message'     => __('Login successful!', 'wc-referral-system'),
            'widget_html' => $html,
        ]);
    }

    public function ajax_register() {
        check_ajax_referer('wc-referral-system-nonce', 'nonce');
        $email    = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ( empty($email) || empty($password) ) {
            wp_send_json_error(['message' => __('Please fill in all fields.', 'wc-referral-system')]);
        }
        if ( strlen($password) < 6 ) {
            wp_send_json_error(['message' => __('Password must be at least 6 characters.', 'wc-referral-system')]);
        }

        if ( email_exists($email) ) {
            wp_send_json_error(['message' => __('Email already registered.', 'wc-referral-system')]);
        }

        $user_id = wc_create_new_customer($email, $email, $password);

        if ( is_wp_error($user_id) ) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }

        wc_set_customer_auth_cookie($user_id);

        ob_start();
        $this->render_user_referral_block();
        $html = ob_get_clean();

        wp_send_json_success([
            'message'     => __('Registration successful!', 'wc-referral-system'),
            'widget_html' => $html,
        ]);
    }

    /**
     * Render floating widget
     */
    public function render_floating_widget() {
        // Skip if admin
        if (is_admin()) {
            return;
        }
        
        // Get discount amount
        $discount = get_option('wc_referral_referee_discount', '10');
        $reward = get_option('wc_referral_referrer_reward', '15');
        
        ?>
        <div id="referral-widget-button" class="referral-widget-button">
            <span class="widget-icon">🎁</span>
            <span class="widget-text"><?php _e('10% discount', 'wc-referral-system'); ?></span>
        </div>
        
        <!-- Referral Popup (from class-referral-frontend.php) -->
        <div id="referral-popup" class="referral-popup" style="display: none;">
            <div class="referral-popup-content wrs-popup-container">
                <span class="close-popup">&times;</span>
                <div id="referral-popup-body">
                    <?php if (is_user_logged_in()): ?>
                        <?php 
                        // If you have a method to render widget content, call it here
                        // Otherwise, you'll need to include the logged-in user content
                        if (method_exists($this, 'render_referral_widget_content')) {
                            $this->render_referral_widget_content();
                        }
                        ?>
                    <?php else: ?>
                        <div class="wrs-popup-content">
                            <!-- Email Step -->
                            <div class="wrs-popup-step wrs-step-email" id="wrs-step-email">
                                <h3><?php _e('🎁 Unlock Referral Rewards', 'woo-referral-system'); ?></h3>
                                <p><?php _e('Enter your email to access your referral rewards and start earning!', 'woo-referral-system'); ?></p>
                                
                                <div class="wrs-form-group">
                                    <input type="email" id="wrs-email" class="wrs-input" placeholder="<?php _e('Enter your email address', 'woo-referral-system'); ?>" />
                                </div>
                                
                                <div class="wrs-form-group">
                                    <button class="wrs-button wrs-button-primary" id="wrs-check-email"><?php _e('Continue', 'woo-referral-system'); ?></button>
                                </div>
                            </div>
                            
                            <!-- Password Step (Login) -->
                            <div class="wrs-popup-step wrs-step-login" id="wrs-step-login" style="display:none;">
                                <h3><?php _e('Welcome Back!', 'woo-referral-system'); ?></h3>
                                <p id="wrs-login-message"><?php _e('Please enter your password to login', 'woo-referral-system'); ?></p>
                                
                                <div class="wrs-form-group">
                                    <input type="password" id="wrs-login-password" class="wrs-input" placeholder="<?php _e('Enter your password', 'woo-referral-system'); ?>" />
                                </div>
                                
                                <div class="wrs-form-group">
                                    <button class="wrs-button wrs-button-primary" id="wrs-login-button"><?php _e('Login', 'woo-referral-system'); ?></button>
                                </div>
                            </div>
                            
                            <!-- Password Step (Register) -->
                            <div class="wrs-popup-step wrs-step-register" id="wrs-step-register" style="display:none;">
                                <h3><?php _e('Create Your Account', 'woo-referral-system'); ?></h3>
                                <p id="wrs-register-message"><?php _e('Create a password to complete registration', 'woo-referral-system'); ?></p>
                                
                                <div class="wrs-form-group">
                                    <input type="password" id="wrs-register-password" class="wrs-input" placeholder="<?php _e('Create a password (min 6 characters)', 'woo-referral-system'); ?>" />
                                </div>
                                
                                <div class="wrs-form-group">
                                    <button class="wrs-button wrs-button-primary" id="wrs-register-button"><?php _e('Create Account', 'woo-referral-system'); ?></button>
                                </div>
                            </div>
                            
                            <!-- Referral Widget Step -->
                            <div class="wrs-popup-step wrs-step-referral" id="wrs-step-referral" style="display:none;">
                                <!-- Content loaded via AJAX -->
                                <div class="wrs-loading"><?php _e('Loading your referral rewards...', 'woo-referral-system'); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Handle floating button click to open referral popup
            $('#referral-widget-button').on('click', function(e) {
                e.preventDefault();
                $('#referral-popup').fadeIn(300);
            });
            
            // Handle close popup
            $(document).on('click', '.close-popup', function() {
                $('#referral-popup').fadeOut(300);
            });
            
            // Close popup when clicking outside
            $(document).on('click', '#referral-popup', function(e) {
                if (e.target === this) {
                    $('#referral-popup').fadeOut(300);
                }
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX get referral link
     */
    public function ajax_get_referral_link() {
        check_ajax_referer('wc-referral-system-nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in to get your referral link.', 'wc-referral-system')));
            return;
        }
        
        $user_id = get_current_user_id();
        $referral_link = WC_Referral_Codes::instance()->get_user_referral_link($user_id);
        
        wp_send_json_success(array(
            'referral_link' => $referral_link
        ));
    }
}