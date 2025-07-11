<?php
/**
 * Referral Codes Class
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Referral_Codes {
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
        add_action('init', array($this, 'check_referral_code'), 10);
        add_action('wp_loaded', array($this, 'process_referral_link'), 20);
    }

    /**
     * Generate a unique referral code for a user
     */
    public function generate_user_referral_code($user_id) {
        // Check if user already has a referral code
        $referral_code = get_user_meta($user_id, '_wc_referral_code', true);
        
        if (empty($referral_code)) {
            // Generate a new referral code
            $user_data = get_userdata($user_id);
            $username = sanitize_title($user_data->user_login);
            $random_suffix = substr(md5(time() . $user_id), 0, 6);
            $referral_code = $username . '-' . $random_suffix;
            
            // Save the code to user meta
            update_user_meta($user_id, '_wc_referral_code', $referral_code);
        }
        
        return $referral_code;
    }

    /**
     * Get referral link for a user
     */
    public function get_user_referral_link($user_id) {
        $referral_code = $this->generate_user_referral_code($user_id);
        $referral_link = add_query_arg('ref', $referral_code, home_url('/'));
        
        return $referral_link;
    }

    /**
     * Check for referral code in URL and store in session
     */
    public function check_referral_code() {
        if (!session_id()) {
            session_start();
        }
        
        if (isset($_GET['ref']) && !empty($_GET['ref'])) {
            $referral_code = sanitize_text_field($_GET['ref']);
            $_SESSION['wc_referral_code'] = $referral_code;
            
            // Store in cookie for 30 days
            setcookie('wc_referral_code', $referral_code, time() + (30 * DAY_IN_SECONDS), '/');
            
            // Track referral visit
            $this->track_referral_visit($referral_code);
        }
    }

    /**
     * Track referral visit
     */
    public function track_referral_visit($referral_code) {
        if (!isset($_SESSION['wc_referral_tracked'])) {
            // Get referrer ID from code
            $referrer_id = $this->get_referrer_id_from_code($referral_code);
            
            if ($referrer_id) {
                // Store visit in analytics
                $visits = get_option('wc_referral_visits_count', array());
                
                if (!isset($visits[$referral_code])) {
                    $visits[$referral_code] = 0;
                }
                
                $visits[$referral_code]++;
                update_option('wc_referral_visits_count', $visits);
                
                // Mark as tracked for this session
                $_SESSION['wc_referral_tracked'] = true;
            }
        }
    }

    /**
     * Process referral link
     */
    public function process_referral_link() {
        if (!is_admin() && !session_id()) {
            session_start();
        }
        
        // Check for referral code in URL
        if (isset($_GET['ref']) && !empty($_GET['ref'])) {
            $referral_code = sanitize_text_field($_GET['ref']);
            
            // Store in session
            $_SESSION['wc_referral_code'] = $referral_code;
            $_SESSION['wc_referral_time'] = time();
            
            // Get referrer ID from code
            $referrer_id = $this->get_referrer_id_from_code($referral_code);
            
            if ($referrer_id) {
                // Store preliminary referral information
                global $wpdb;
                $table_name = $wpdb->prefix . 'wc_referrals';
                
                // Check if user is logged in
                $referee_id = get_current_user_id();
                $referee_email = '';
                
                if ($referee_id) {
                    $user = get_user_by('id', $referee_id);
                    $referee_email = $user->user_email;
                }
                
                // Check if this referral already exists
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE referral_code = %s AND referee_id = %d AND referee_email = %s LIMIT 1",
                    $referral_code, $referee_id, $referee_email
                ));
                
                if (!$existing) {
                    // Get referrer email
                    $referrer = get_user_by('id', $referrer_id);
                    $referrer_email = $referrer ? $referrer->user_email : '';
                    
                    // Insert new preliminary referral
                    $wpdb->insert(
                        $table_name,
                        array(
                            'referrer_id' => $referrer_id,
                            'referrer_email' => $referrer_email,
                            'referee_id' => $referee_id,
                            'referee_email' => $referee_email,
                            'referral_code' => $referral_code,
                            'referral_date' => current_time('mysql'),
                            'status' => 'pending'
                        ),
                        array('%d', '%s', '%d', '%s', '%s', '%s', '%s')
                    );
                    
                    $_SESSION['wc_referral_record_id'] = $wpdb->insert_id;
                } else {
                    $_SESSION['wc_referral_record_id'] = $existing;
                }
            }
        }
    }

    /**
     * Get referrer ID from referral code
     */
    public function get_referrer_id_from_code($referral_code) {
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_wc_referral_code' AND meta_value = %s LIMIT 1",
            $referral_code
        ));
        
        return $user_id;
    }

    /**
     * Get referrals by user
     */
    public function get_user_referrals($user_id, $status = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_referrals';
        
        $sql = "SELECT * FROM $table_name WHERE referrer_id = %d";
        $params = array($user_id);
        
        if (!empty($status)) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY referral_date DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Get referrer of a user
     */
    public function get_user_referrer($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_referrals';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE referee_id = %d AND status = 'completed' LIMIT 1",
            $user_id
        ));
    }
}