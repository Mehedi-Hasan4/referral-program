<?php
/**
 * Referral Frontend Class - Updated with Fixed AJAX Handlers
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Referral_Frontend {
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
        add_action('wp_footer', array($this, 'render_referral_popup'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('init', array($this, 'add_referrals_endpoint'));
        add_filter('query_vars', array($this, 'add_referrals_query_vars'), 0);
        add_filter('woocommerce_account_menu_items', array($this, 'add_referrals_link_my_account'));
        add_action('woocommerce_account_referrals_endpoint', array($this, 'referrals_content'));
        add_action('woocommerce_account_dashboard', array($this, 'display_referral_link_on_account'));
        add_action('woocommerce_account_referrals_endpoint', array($this, 'add_social_sharing_to_referrals_page'));
        
        // AJAX Actions
        add_action('wp_ajax_get_referral_content', array($this, 'ajax_get_referral_content'));
        add_action('wp_ajax_nopriv_get_referral_content', array($this, 'ajax_get_referral_content'));
        
        // Email check
        add_action('wp_ajax_wrs_check_email', array($this, 'check_email'));
        add_action('wp_ajax_nopriv_wrs_check_email', array($this, 'check_email'));
        
        // Login
        add_action('wp_ajax_nopriv_wrs_login', array($this, 'login'));
        
        // Register
        add_action('wp_ajax_nopriv_wrs_register', array($this, 'register'));
        
        // Get referral widget
        add_action('wp_ajax_wrs_get_referral_widget', array($this, 'get_referral_widget'));
        add_action('wp_ajax_nopriv_wrs_get_referral_widget', array($this, 'get_referral_widget'));
        
        // Get referral stats
        add_action('wp_ajax_wrs_get_referral_stats', array($this, 'get_referral_stats'));
        
        // Register shortcodes
        add_shortcode('referral_link', array($this, 'referral_link_shortcode'));
        add_shortcode('referral_dashboard', array($this, 'referrals_content'));
        add_shortcode('referral_stats', array($this, 'referral_stats_shortcode'));
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'wc-referral-popup',
            plugins_url('assets/css/referral-popup.css', dirname(__FILE__)),
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'wc-referral-popup',
            plugins_url('assets/js/referral-popup.js', dirname(__FILE__)),
            array('jquery'),
            '1.0.0',
            true
        );

        // Localize script with proper data
        wp_localize_script('wc-referral-popup', 'wrsData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wrs-nonce'),
            'is_logged_in' => is_user_logged_in(),
            'user_id' => get_current_user_id(),
            'texts' => array(
                'invalid_email' => __('Please enter a valid email address', 'woo-referral-system'),
                'enter_password' => __('Please enter your password', 'woo-referral-system'),
                'loading' => __('Loading...', 'woo-referral-system')
            )
        ));
    }

    /**
     * Check email AJAX handler
     */
    public function check_email() {
        check_ajax_referer('wrs-nonce', 'nonce');
        
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address', 'woo-referral-system')));
        }
        
        $user = get_user_by('email', $email);
        
        if ($user) {
            wp_send_json_success(array(
                'exists' => true,
                'message' => __('Welcome back! Please enter your password to login', 'woo-referral-system')
            ));
        } else {
            wp_send_json_success(array(
                'exists' => false,
                'message' => __('Create a password to set up your account and start earning rewards', 'woo-referral-system')
            ));
        }
    }
    
    /**
     * Login AJAX handler
     */
    public function login() {
        check_ajax_referer('wrs-nonce', 'nonce');
        
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address', 'woo-referral-system')));
        }
        
        if (empty($password)) {
            wp_send_json_error(array('message' => __('Please enter your password', 'woo-referral-system')));
        }
        
        $user = get_user_by('email', $email);
        
        if (!$user) {
            wp_send_json_error(array('message' => __('User not found', 'woo-referral-system')));
        }
        
        $credentials = array(
            'user_login' => $user->user_login,
            'user_password' => $password,
            'remember' => true
        );
        
        $user_login = wp_signon($credentials);
        
        if (is_wp_error($user_login)) {
            wp_send_json_error(array('message' => __('Invalid password. Please try again.', 'woo-referral-system')));
        }
        
        // Get referral widget HTML
        ob_start();
        $this->render_referral_widget_content($user_login->ID);
        $widget_html = ob_get_clean();
        
        wp_send_json_success(array(
            'message' => __('Login successful! Welcome back.', 'woo-referral-system'),
            'user_id' => $user_login->ID,
            'widget_html' => $widget_html
        ));
    }
    
    /**
     * Register AJAX handler
     */
    public function register() {
        check_ajax_referer('wrs-nonce', 'nonce');
        
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address', 'woo-referral-system')));
        }
        
        if (empty($password) || strlen($password) < 6) {
            wp_send_json_error(array('message' => __('Password must be at least 6 characters long', 'woo-referral-system')));
        }
        
        if (email_exists($email)) {
            wp_send_json_error(array('message' => __('This email is already registered. Please use the login option.', 'woo-referral-system')));
        }
        
        // Generate username from email
        $username = sanitize_user(current(explode('@', $email)), true);
        
        $suffix = 1;
        $original_username = $username;
        while (username_exists($username)) {
            $username = $original_username . $suffix;
            $suffix++;
        }
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }
        
        // Set user role
        $user = new WP_User($user_id);
        $user->set_role('customer');
        
        // Auto-login the new user
        $credentials = array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => true
        );
        
        wp_signon($credentials);
        
        // Send welcome email
        $this->send_welcome_email($user_id, $email, $username, $password);
        
        // Check for referral cookie and process referral
        $this->process_referral_signup($user_id);
        
        // Get referral widget HTML
        ob_start();
        $this->render_referral_widget_content($user_id);
        $widget_html = ob_get_clean();
        
        wp_send_json_success(array(
            'message' => __('Account created successfully! Check your email for login details.', 'woo-referral-system'),
            'user_id' => $user_id,
            'widget_html' => $widget_html
        ));
    }

    /**
     * Send welcome email to new user
     */
    private function send_welcome_email($user_id, $email, $username, $password) {
        $blogname = get_option('blogname');
        $subject = sprintf(__('Welcome to %s - Your Account Details', 'woo-referral-system'), $blogname);
        
        $message = sprintf(
            __("Welcome to %s!\n\nYour account has been created successfully. Here are your login details:\n\nEmail: %s\nUsername: %s\nPassword: %s\n\nYou can login at: %s\n\nStart sharing your referral link and earn rewards!\n\nThank you!", 'woo-referral-system'),
            $blogname,
            $email,
            $username,
            $password,
            wp_login_url()
        );
        
        wp_mail($email, $subject, $message);
    }

    /**
     * Process referral signup
     */
    private function process_referral_signup($user_id) {
        if (isset($_COOKIE['wrs_referral']) && !empty($_COOKIE['wrs_referral'])) {
            $referral_code = sanitize_text_field($_COOKIE['wrs_referral']);
            
            // Find the referrer
            $args = array(
                'meta_key' => 'wrs_referral_code',
                'meta_value' => $referral_code,
                'number' => 1,
                'fields' => 'ID'
            );
            
            $users = get_users($args);
            
            if (!empty($users)) {
                $referrer_id = $users[0];
                
                // Create referral record (you'll need to implement this based on your referral system)
                if (class_exists('WC_Referral_Codes')) {
                    WC_Referral_Codes::instance()->create_referral($referrer_id, $user_id);
                }
            }
        }
    }

    /**
     * Render referral widget content
     */
    private function render_referral_widget_content($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return;
        }
        
        $referral_link = WC_Referral_Codes::instance()->get_user_referral_link($user_id);
        $discount = get_option('wc_referral_referee_discount', '10');
        $reward = get_option('wc_referral_referrer_reward', '15');
        
        ?>
        <div class="referral-widget-body">
            <h3><?php _e('🎉 Your Referral Rewards', 'woo-referral-system'); ?></h3>
            
            <p class="referral-widget-info">
                <?php printf(__('Share your unique link and your friends will get %s%% off their first order!', 'woo-referral-system'), $discount); ?>
            </p>
            
            <p class="referral-widget-reward">
                <?php printf(__('You\'ll earn a %s%% discount coupon when they make a purchase.', 'woo-referral-system'), $reward); ?>
            </p>
            
            <div class="referral-widget-link-container">
                <input type="text" id="widget-referral-link" value="<?php echo esc_url($referral_link); ?>" readonly>
                <button id="widget-copy-link" class="widget-copy-button"><?php _e('Copy', 'woo-referral-system'); ?></button>
            </div>
            
            <p id="widget-copy-message" class="widget-copy-message" style="display: none;">
                <?php _e('Link copied to clipboard!', 'woo-referral-system'); ?>
            </p>
            
            <div class="referral-widget-social">
                <p><?php _e('Share via:', 'wc-referral-system'); ?></p>
                <div class="widget-social-buttons">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_link); ?>" target="_blank" class="widget-social-button facebook">
                        <i class="fab fa-facebook"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode(sprintf(__('Use my referral link to get %s%% off your first order!', 'wc-referral-system'), $discount)); ?>&url=<?php echo urlencode($referral_link); ?>" target="_blank" class="widget-social-button twitter">
                        <i class="fab fa-x-twitter"></i>
                    </a>
                    <a href="mailto:?subject=<?php echo urlencode(__('Get a discount on your order', 'wc-referral-system')); ?>&body=<?php echo urlencode(sprintf(__('Use my referral link to get %s%% off your first order: %s', 'wc-referral-system'), $discount, $referral_link)); ?>" class="widget-social-button email">
                        <i class="fa-regular fa-envelope"></i>
                    </a>
                    <a href="https://wa.me/?text=<?php echo urlencode(sprintf(__('Use my referral link to get %s%% off your first order: %s', 'wc-referral-system'), $discount, $referral_link)); ?>" target="_blank" class="widget-social-button whatsapp">
                        <i class="fa-brands fa-whatsapp"></i>
                    </a>
                </div>
            </div>
            
            <div class="referral-widget-footer">
                <a href="<?php echo wc_get_endpoint_url('referrals', '', wc_get_page_permalink('myaccount')); ?>" class="widget-view-referrals">
                    <?php _e('View your referrals', 'woo-referral-system'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render referral popup
     */
    public function render_referral_popup() {
        ?>
        <div id="referral-popup" class="referral-popup" style="display: none;">
            <div class="referral-popup-content wrs-popup-container">
                <span class="close-popup">&times;</span>
                <div id="referral-popup-body">
                    <?php if (is_user_logged_in()): ?>
                        <?php $this->render_referral_widget_content(); ?>
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
        <?php
    }








    /**

     * Add referrals endpoint to WooCommerce

     */

    public function add_referrals_endpoint() {

        add_rewrite_endpoint('referrals', EP_ROOT | EP_PAGES);

    }

/**

     * Check email

     */

    

    /**

     * Register

     */


    

    /**

     * Get referral widget

     */

    public function get_referral_widget() {

        check_ajax_referer('wrs-nonce', 'nonce');

        

        $user_id = get_current_user_id();

        

        if (!$user_id) {

            ob_start();

            include WRS_PLUGIN_DIR . 'templates/login-register.php';

            $html = ob_get_clean();

        } else {

            $referral_code = WRS_Referral::get_referral_code($user_id);

            $referral_link = WRS_Referral::get_referral_link($user_id);

            $referrer_amount = get_option('wrs_referrer_amount', 10);

            $referred_amount = get_option('wrs_referred_amount', 5);

            

            ob_start();

            include WRS_PLUGIN_DIR . 'templates/referral-widget.php';

            $html = ob_get_clean();

        }

        

        wp_send_json_success(array('html' => $html));

    }

    

    /**

     * Get referral stats

     */

    public function get_referral_stats() {

        check_ajax_referer('wrs-nonce', 'nonce');

        

        if (!is_user_logged_in()) {

            wp_send_json_error(array('message' => __('Please log in to view your referral stats', 'woo-referral-system')));

        }

        

        $user_id = get_current_user_id();

        $stats = WRS_Referral::get_referral_stats($user_id);

        $coupon = new WRS_Coupon();

        $coupons = $coupon->get_user_coupons($user_id);

        $referrals = WRS_Referral::get_user_referrals($user_id);

        

        ob_start();

        include WRS_PLUGIN_DIR . 'templates/referral-stats.php';

        $html = ob_get_clean();

        

        wp_send_json_success(array(

            'html' => $html,

            'stats' => $stats

        ));

    }

    /**

     * Add referrals query vars

     */

    public function add_referrals_query_vars($vars) {

        $vars[] = 'referrals';

        return $vars;

    }



    /**

     * Add referrals link to My Account menu

     */

    public function add_referrals_link_my_account($items) {

        // Add referrals item after orders

        $new_items = array();

        

        foreach ($items as $key => $item) {

            $new_items[$key] = $item;

            

            if ($key === 'orders') {

                $new_items['referrals'] = __('My Referrals', 'wc-referral-system');

            }

        }

        

        return $new_items;

    }



    /**

     * Display referral link on account dashboard

     */

    public function display_referral_link_on_account() {

        if (is_user_logged_in()) {

            $user_id = get_current_user_id();

            $referral_link = WC_Referral_Codes::instance()->get_user_referral_link($user_id);

            $discount = get_option('wc_referral_referee_discount', '10');

            $reward = get_option('wc_referral_referrer_reward', '15');

            

            ?>

            <div class="woocommerce-referral-box">

                <h3><?php _e('Your Referral Link', 'wc-referral-system'); ?></h3>

                <p><?php _e('Share this link with friends and earn rewards when they make a purchase!', 'wc-referral-system'); ?></p>

                <div class="referral-link-container">

                    <input type="text" id="referral-link" value="<?php echo esc_url($referral_link); ?>" readonly>

                    <button id="copy-referral-link" class="button"><?php _e('Copy', 'wc-referral-system'); ?></button>

                </div>

                <p class="referral-explanation">

                    <small><?php printf(__('When your friends use this link, they will receive a %s%% discount on their purchase.', 'wc-referral-system'), $discount); ?></small>

                </p>

                <p class="referral-explanation">

                    <small><?php printf(__('You will earn a %s%% reward coupon after they complete their order!', 'wc-referral-system'), $reward); ?></small>

                </p>

            </div>

            <script>

                jQuery(document).ready(function($) {

                    $("#copy-referral-link").on("click", function() {

                        var linkInput = document.getElementById("referral-link");

                        linkInput.select();

                        document.execCommand("copy");

                        $(this).text("<?php _e('Copied!', 'wc-referral-system'); ?>");

                        setTimeout(function() {

                            $("#copy-referral-link").text("<?php _e('Copy', 'wc-referral-system'); ?>");

                        }, 2000);

                    });

                });

            </script>

            <?php

        }

    }



    /**

     * Referrals content for My Account page

     */

    public function referrals_content() {

        global $wpdb;

        $table_name = $wpdb->prefix . 'wc_referrals';

        $user_id = get_current_user_id();

        

        // Get user's referral code and link

        $referral_link = WC_Referral_Codes::instance()->get_user_referral_link($user_id);

        

        // Get referrals made by this user

        $referrals = WC_Referral_Codes::instance()->get_user_referrals($user_id);

        

        // Get statistics

        $total_referrals = count($referrals);

        $completed_referrals = $wpdb->get_var($wpdb->prepare(

            "SELECT COUNT(*) FROM $table_name WHERE referrer_id = %d AND status = 'completed'",

            $user_id

        ));

        $conversion_rate = ($total_referrals > 0) ? round(($completed_referrals / $total_referrals) * 100, 2) : 0;

        

        $discount = get_option('wc_referral_referee_discount', '10');

        $reward = get_option('wc_referral_referrer_reward', '15');

        

        ?>

        <div class="woocommerce-referral-system">

            <div class="woocommerce-referral-box">

                <h3><?php _e('Your Referral Link', 'wc-referral-system'); ?></h3>

                <p><?php _e('Share this link with friends and earn rewards when they make a purchase!', 'wc-referral-system'); ?></p>

                <div class="referral-link-container">

                    <input type="text" id="referral-link" value="<?php echo esc_url($referral_link); ?>" readonly>

                    <button id="copy-referral-link" class="button"><?php _e('Copy', 'wc-referral-system'); ?></button>

                </div>

                <p id="copy-message" style="display: none;"><?php _e('Link copied to clipboard!', 'wc-referral-system'); ?></p>

                <p class="referral-explanation">

                    <small><?php printf(__('When your friends use this link, they will receive a %s%% discount on their purchase.', 'wc-referral-system'), $discount); ?></small>

                </p>

                <p class="referral-explanation">

                    <small><?php printf(__('You will earn a %s%% reward coupon after they complete their order!', 'wc-referral-system'), $reward); ?></small>

                </p>

            </div>

            

            <div class="referral-stats">

                <h3><?php _e('Your Referral Statistics', 'wc-referral-system'); ?></h3>

                <div class="stat-boxes">

                    <div class="stat-box total-referrals">

                        <h4><?php _e('Total Referrals', 'wc-referral-system'); ?></h4>

                        <p class="stat-number"><?php echo $total_referrals; ?></p>

                    </div>

                    <div class="stat-box completed-referrals">

                        <h4><?php _e('Completed Referrals', 'wc-referral-system'); ?></h4>

                        <p class="stat-number"><?php echo $completed_referrals; ?></p>

                    </div>

                    <div class="stat-box conversion-rate">

                        <h4><?php _e('Conversion Rate', 'wc-referral-system'); ?></h4>

                        <p class="stat-number"><?php echo $conversion_rate; ?>%</p>

                    </div>

                </div>

            </div>

            

            <h3><?php _e('Your Referrals', 'wc-referral-system'); ?></h3>

            <?php if (empty($referrals)) : ?>

                <p><?php _e('You haven\'t made any referrals yet. Share your referral link with friends to start earning rewards!', 'wc-referral-system'); ?></p>

            <?php else : ?>

                <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">

                    <thead>

                        <tr>

                            <th><?php _e('Referee', 'wc-referral-system'); ?></th>

                            <th><?php _e('Date', 'wc-referral-system'); ?></th>

                            <th><?php _e('Status', 'wc-referral-system'); ?></th>

                            <th><?php _e('Order', 'wc-referral-system'); ?></th>

                            <th><?php _e('Reward', 'wc-referral-system'); ?></th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($referrals as $referral) : ?>

                            <tr>

                                <td data-label="<?php _e('Referee', 'wc-referral-system'); ?>">

                                    <?php 

                                    if ($referral->referee_id) {

                                        $referee = get_user_by('id', $referral->referee_id);

                                        echo $referee ? esc_html($referee->display_name) : esc_html($referral->referee_email);

                                    } elseif (!empty($referral->referee_email)) {

                                        echo esc_html($referral->referee_email);

                                    } else {

                                        echo '<em>' . __('Pending', 'wc-referral-system') . '</em>';

                                    }

                                    ?>

                                </td>

                                <td data-label="<?php _e('Date', 'wc-referral-system'); ?>">

                                    <?php echo date_i18n(get_option('date_format'), strtotime($referral->referral_date)); ?>

                                </td>

                                <td data-label="<?php _e('Status', 'wc-referral-system'); ?>">

                                    <?php if ($referral->status === 'completed') : ?>

                                        <span class="referral-status completed"><?php _e('Completed', 'wc-referral-system'); ?></span>

                                    <?php else : ?>

                                        <span class="referral-status pending"><?php _e('Pending', 'wc-referral-system'); ?></span>

                                    <?php endif; ?>

                                </td>

                                <td data-label="<?php _e('Order', 'wc-referral-system'); ?>">

                                    <?php if ($referral->order_id) : ?>

                                        <a href="<?php echo esc_url(wc_get_endpoint_url('view-order', $referral->order_id, wc_get_page_permalink('myaccount'))); ?>">

                                            #<?php echo $referral->order_id; ?>

                                        </a>

                                    <?php else : ?>

                                        <?php _e('N/A', 'wc-referral-system'); ?>

                                    <?php endif; ?>

                                </td>

                                <td data-label="<?php _e('Reward', 'wc-referral-system'); ?>">

                                    <?php if ($referral->reward_coupon_id) :

                                        $coupon_code = get_the_title($referral->reward_coupon_id);

                                    ?>

                                        <span class="referral-reward"><?php echo esc_html($coupon_code); ?></span>

                                    <?php else : ?>

                                        <?php if ($referral->status === 'completed') : ?>

                                            <span class="reward-processing"><?php _e('Processing', 'wc-referral-system'); ?></span>

                                        <?php else : ?>

                                            <?php _e('Pending order completion', 'wc-referral-system'); ?>

                                        <?php endif; ?>

                                    <?php endif; ?>

                                </td>

                            </tr>



                        <?php endforeach; ?>

                    </tbody>

                </table>

                

                <div class="referral-info">

                    <h4><?php _e('How It Works', 'wc-referral-system'); ?></h4>

                    <ol>

                        <li><?php _e('Share your unique referral link with friends and family', 'wc-referral-system'); ?></li>

                        <li><?php printf(__('When they visit your link, a %s%% discount coupon is automatically applied to their cart', 'wc-referral-system'), $discount); ?></li>

                        <li><?php printf(__('After they complete their purchase, you\'ll receive a %s%% reward coupon by email', 'wc-referral-system'), $reward); ?></li>

                        <li><?php _e('Your reward coupon will be valid for 60 days', 'wc-referral-system'); ?></li>

                    </ol>

                </div>

            <?php endif; ?>

        </div>

        

        <script>

            jQuery(document).ready(function($) {

                $("#copy-referral-link").on("click", function() {

                    var linkInput = document.getElementById("referral-link");

                    linkInput.select();

                    document.execCommand("copy");

                    

                    var copyMessage = document.getElementById("copy-message");

                    copyMessage.style.display = "block";

                    

                    $(this).text("<?php _e('Copied!', 'wc-referral-system'); ?>");

                    

                    setTimeout(function() {

                        $("#copy-referral-link").text("<?php _e('Copy', 'wc-referral-system'); ?>");

                        copyMessage.style.display = "none";

                    }, 2000);

                });

            });

        </script>

        <?php

    }



    /**

     * Add social sharing buttons to referrals page

     */

    public function add_social_sharing_to_referrals_page() {

        if (!is_account_page() || !is_wc_endpoint_url('referrals')) {

            return;

        }

        

        $user_id = get_current_user_id();

        $referral_link = WC_Referral_Codes::instance()->get_user_referral_link($user_id);

        $shop_name = get_bloginfo('name');

        $discount = get_option('wc_referral_referee_discount', '10');

        $share_text = urlencode(sprintf(__('Save %s%% on your purchase at %s with my referral link!', 'wc-referral-system'), $discount, $shop_name));

        

        ?>

        <div class="referral-widget-social">
            <p><?php _e('Share via:', 'wc-referral-system'); ?></p>
            <div class="widget-social-buttons">
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_link); ?>" target="_blank" class="widget-social-button facebook">
                    <i class="fab fa-facebook"></i>
                </a>
                <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode(sprintf(__('Use my referral link to get %s%% off your first order!', 'wc-referral-system'), $discount)); ?>&url=<?php echo urlencode($referral_link); ?>" target="_blank" class="widget-social-button twitter">
                    <i class="fab fa-x-twitter"></i>
                </a>
                <a href="mailto:?subject=<?php echo urlencode(__('Get a discount on your order', 'wc-referral-system')); ?>&body=<?php echo urlencode(sprintf(__('Use my referral link to get %s%% off your first order: %s', 'wc-referral-system'), $discount, $referral_link)); ?>" class="widget-social-button email">
                    <i class="fa-regular fa-envelope"></i>
                </a>
                <a href="https://wa.me/?text=<?php echo urlencode(sprintf(__('Use my referral link to get %s%% off your first order: %s', 'wc-referral-system'), $discount, $referral_link)); ?>" target="_blank" class="widget-social-button whatsapp">
                    <i class="fa-brands fa-whatsapp"></i>
                </a>
            </div>
        </div>

        <?php

    }



    /**

     * Referral link shortcode

     */

    public function referral_link_shortcode($atts) {

        if (!is_user_logged_in()) {

            return '<p>' . sprintf(__('Please <a href="%s">login</a> to get your referral link.', 'wc-referral-system'), esc_url(wc_get_page_permalink('myaccount'))) . '</p>';

        }

        

        $user_id = get_current_user_id();

        $referral_link = WC_Referral_Codes::instance()->get_user_referral_link($user_id);

        $discount = get_option('wc_referral_referee_discount', '10');

        $reward = get_option('wc_referral_referrer_reward', '15');

        

        ob_start();

        ?>

        <div class="woocommerce-referral-box">

            <h3><?php _e('Your Referral Link', 'wc-referral-system'); ?></h3>

            <p><?php _e('Share this link with friends and earn rewards when they make a purchase!', 'wc-referral-system'); ?></p>

            <div class="referral-link-container">

                <input type="text" id="shortcode-referral-link" value="<?php echo esc_url($referral_link); ?>" readonly>

                <button id="shortcode-copy-referral-link" class="button"><?php _e('Copy', 'wc-referral-system'); ?></button>

            </div>

            <p class="referral-explanation">

                <small><?php printf(__('When your friends use this link, they will receive a %s%% discount on their purchase.', 'wc-referral-system'), $discount); ?></small>

            </p>

            <p class="referral-explanation">

                <small><?php printf(__('You will earn a %s%% reward coupon after they complete their order!', 'wc-referral-system'), $reward); ?></small>

            </p>

            <script>

                jQuery(document).ready(function($) {

                    $("#shortcode-copy-referral-link").on("click", function() {

                        var linkInput = document.getElementById("shortcode-referral-link");

                        linkInput.select();

                        document.execCommand("copy");

                        $(this).text("<?php _e('Copied!', 'wc-referral-system'); ?>");

                        setTimeout(function() {

                            $("#shortcode-copy-referral-link").text("<?php _e('Copy', 'wc-referral-system'); ?>");

                        }, 2000);

                    });

                });

            </script>

        </div>

        <?php

        return ob_get_clean();

    }



    /**

     * Referral stats shortcode

     */

    public function referral_stats_shortcode($atts) {

        if (!is_user_logged_in()) {

            return '<p>' . sprintf(__('Please <a href="%s">login</a> to see your referral statistics.', 'wc-referral-system'), esc_url(wc_get_page_permalink('myaccount'))) . '</p>';

        }

        

        global $wpdb;

        $table_name = $wpdb->prefix . 'wc_referrals';

        $user_id = get_current_user_id();

        

        // Get referrals made by this user

        $referrals = WC_Referral_Codes::instance()->get_user_referrals($user_id);

        

        // Get statistics

        $total_referrals = count($referrals);

        $completed_referrals = $wpdb->get_var($wpdb->prepare(

            "SELECT COUNT(*) FROM $table_name WHERE referrer_id = %d AND status = 'completed'",

            $user_id

        ));

        $conversion_rate = ($total_referrals > 0) ? round(($completed_referrals / $total_referrals) * 100, 2) : 0;

        

        ob_start();

        ?>

        <div class="referral-stats">

            <div class="stat-boxes">

                <div class="stat-box total-referrals">

                    <h4><?php _e('Total Referrals', 'wc-referral-system'); ?></h4>

                    <p class="stat-number"><?php echo $total_referrals; ?></p>

                </div>

                <div class="stat-box completed-referrals">

                    <h4><?php _e('Completed Referrals', 'wc-referral-system'); ?></h4>

                    <p class="stat-number"><?php echo $completed_referrals; ?></p>

                </div>

                <div class="stat-box conversion-rate">

                    <h4><?php _e('Conversion Rate', 'wc-referral-system'); ?></h4>

                    <p class="stat-number"><?php echo $conversion_rate; ?>%</p>

                </div>

            </div>

        </div>

        <?php

        return ob_get_clean();

    }

}