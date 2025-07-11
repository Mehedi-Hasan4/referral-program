<?php
/**
 * Referral Emails Class
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Referral_Emails {
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
        add_action('wc_referral_system_reward_earned', array($this, 'send_reward_email'), 10, 2);
        add_action('woocommerce_email_after_order_table', array($this, 'add_referral_info_to_emails'), 10, 4);
        add_action('woocommerce_before_account_content', array($this, 'display_referral_reward_notice'));
    }

    /**
     * Send reward email
     */
    public function send_reward_email($referral, $reward_coupon_code) {
        if (empty($referral->referrer_email)) {
            return;
        }
        
        $user = get_user_by('id', $referral->referrer_id);
        if (!$user) {
            return;
        }
        
        $referee = get_user_by('id', $referral->referee_id);
        $referee_name = $referee ? $referee->display_name : __('Someone', 'wc-referral-system');
        
        $reward_amount = get_option('wc_referral_referrer_reward', '15');
        $expiry_days = get_option('wc_referral_referrer_expiry_days', '60');
        
        $subject = sprintf(__('Your %s%% Referral Reward Coupon!', 'wc-referral-system'), $reward_amount);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <title><?php echo $subject; ?></title>
        </head>
        <body style="background-color: #f7f7f7; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.5; margin: 0; padding: 0;">
            <div style="background-color: #f7f7f7; padding: 30px 0;">
                <div style="background-color: #ffffff; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin: 0 auto; max-width: 600px; padding: 30px;">
                    <div style="border-bottom: 1px solid #eeeeee; margin-bottom: 20px; padding-bottom: 20px; text-align: center;">
                        <h1 style="color: #444444; font-size: 24px; margin: 0;"><?php _e('Congratulations!', 'wc-referral-system'); ?></h1>
                    </div>
                    
                    <p style="color: #666666; margin-bottom: 15px;">
                        <?php echo sprintf(__('Hello %s,', 'wc-referral-system'), $user->display_name); ?>
                    </p>
                    
                    <p style="color: #666666; margin-bottom: 15px;">
                        <?php echo sprintf(__('%s has completed their purchase using your referral link.', 'wc-referral-system'), $referee_name); ?>
                    </p>
                    
                    <p style="color: #666666; margin-bottom: 15px;">
                        <?php echo sprintf(__('Here\'s your reward coupon for a %s%% discount on your next purchase:', 'wc-referral-system'), $reward_amount); ?>
                    </p>
                    
                    <div style="background-color: #f7f7f7; border: 2px dashed #dddddd; border-radius: 5px; margin: 20px 0; padding: 15px; text-align: center;">
                        <h2 style="color: #4e73df; font-size: 28px; letter-spacing: 1px; margin: 0;"><?php echo $reward_coupon_code; ?></h2>
                    </div>
                    
                    <p style="color: #666666; margin-bottom: 15px;">
                        <?php echo sprintf(__('This coupon is valid for one-time use and expires in %s days.', 'wc-referral-system'), $expiry_days); ?>
                    </p>
                    
                    <p style="color: #666666; margin-bottom: 15px;">
                        <?php _e('Thank you for referring your friends to our store!', 'wc-referral-system'); ?>
                    </p>
                    
                    <div style="border-top: 1px solid #eeeeee; margin-top: 20px; padding-top: 20px; text-align: center;">
                        <p style="color: #999999; font-size: 12px; margin: 0;">
                            <?php echo sprintf(__('To view all your referrals, please visit your <a href="%s" style="color: #4e73df; text-decoration: none;">account page</a>.', 'wc-referral-system'), wc_get_endpoint_url('referrals', '', wc_get_page_permalink('myaccount'))); ?>
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        $message = ob_get_clean();
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($referral->referrer_email, $subject, $message, $headers);
    }

    /**
     * Add referral information to order emails
     */
    public function add_referral_info_to_emails($order, $sent_to_admin, $plain_text, $email) {
        if ($sent_to_admin) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_referrals';
        $order_id = $order->get_id();
        
        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_id = %d",
            $order_id
        ));
        
        if ($referral && $referral->status === 'completed') {
            // This was a successful referral
            if ($plain_text) {
                echo "\n\n" . __('Thank you for using a referral link!', 'wc-referral-system') . "\n";
                echo __('You received a discount on this order through our referral program.', 'wc-referral-system') . "\n";
                echo __('Why not share your own referral link with friends to earn rewards?', 'wc-referral-system') . "\n";
                echo wc_get_endpoint_url('referrals', '', wc_get_page_permalink('myaccount')) . "\n";
            } else {
                echo '<div style="margin-top: 20px; margin-bottom: 20px; padding: 15px; background-color: #f7f7f7; border-left: 4px solid #4e73df;">';
                echo '<h2 style="margin-top: 0;">' . __('Thank you for using a referral link!', 'wc-referral-system') . '</h2>';
                echo '<p>' . __('You received a discount on this order through our referral program.', 'wc-referral-system') . '</p>';
                echo '<p>' . __('Why not share your own referral link with friends to earn rewards?', 'wc-referral-system') . '</p>';
                echo '<p><a href="' . wc_get_endpoint_url('referrals', '', wc_get_page_permalink('myaccount')) . '">' . __('View your referral link', 'wc-referral-system') . '</a></p>';
                echo '</div>';
            }
        }
    }

    /**
     * Display referral reward notice
     */
    public function display_referral_reward_notice() {
        if (is_account_page() && isset($_GET['referral_reward']) && $_GET['referral_reward'] === 'success') {
            wc_add_notice(
                __('Congratulations! You have earned a reward coupon from a successful referral. Check your email for details.', 'wc-referral-system'),
                'success'
            );
        }
    }
}