<?php
/**
 * Referral Admin Class
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Referral_Admin {
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
        add_action('admin_menu', array($this, 'add_menu_items'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_referral_column'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'show_order_referral_column_content'));
        add_action('admin_head', array($this, 'add_referral_column_style'));
        add_action('show_user_profile', array($this, 'add_referral_info_to_user_profile'));
        add_action('edit_user_profile', array($this, 'add_referral_info_to_user_profile'));
    }

    /**
     * Add admin menu items
     */
    public function add_menu_items() {
        // Add main menu
        add_menu_page(
            __('Referral System', 'wc-referral-system'),
            __('Referrals', 'wc-referral-system'),
            'manage_woocommerce',
            'wc-referral-system',
            array($this, 'render_referrals_page'),
            'dashicons-share',
            56
        );
        
        // Add submenu items
        add_submenu_page(
            'wc-referral-system',
            __('All Referrals', 'wc-referral-system'),
            __('All Referrals', 'wc-referral-system'),
            'manage_woocommerce',
            'wc-referral-system',
            array($this, 'render_referrals_page')
        );
        
        add_submenu_page(
            'wc-referral-system',
            __('Analytics', 'wc-referral-system'),
            __('Analytics', 'wc-referral-system'),
            'manage_woocommerce',
            'wc-referral-analytics',
            array($this, 'render_analytics_page')
        );
        
        add_submenu_page(
            'wc-referral-system',
            __('Settings', 'wc-referral-system'),
            __('Settings', 'wc-referral-system'),
            'manage_woocommerce',
            'wc-referral-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'wc_referral_dashboard_widget',
            __('Referral System Overview', 'wc-referral-system'),
            array($this, 'render_dashboard_widget')
        );
    }

    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_referrals';
        
        // Get statistics
        $total_referrals = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $completed_referrals = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
        $pending_referrals = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
        $conversion_rate = ($total_referrals > 0) ? round(($completed_referrals / $total_referrals) * 100, 2) : 0;
        
        // Recent referrals
        $recent_referrals = $wpdb->get_results("SELECT * FROM $table_name ORDER BY referral_date DESC LIMIT 5");
        
        ?>
        <div class="referral-dashboard-stats">
            <div class="stat-box">
                <h4><?php _e('Total', 'wc-referral-system'); ?></h4>
                <p class="stat-number"><?php echo $total_referrals; ?></p>
            </div>
            <div class="stat-box">
                <h4><?php _e('Completed', 'wc-referral-system'); ?></h4>
                <p class="stat-number completed"><?php echo $completed_referrals; ?></p>
            </div>
            <div class="stat-box">
                <h4><?php _e('Pending', 'wc-referral-system'); ?></h4>
                <p class="stat-number pending"><?php echo $pending_referrals; ?></p>
            </div>
            <div class="stat-box">
                <h4><?php _e('Rate', 'wc-referral-system'); ?></h4>
                <p class="stat-number rate"><?php echo $conversion_rate; ?>%</p>
            </div>
        </div>
        
        <h4><?php _e('Recent Referrals', 'wc-referral-system'); ?></h4>
        <?php if (empty($recent_referrals)) : ?>
            <p><?php _e('No referrals yet.', 'wc-referral-system'); ?></p>
        <?php else : ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Referrer', 'wc-referral-system'); ?></th>
                        <th><?php _e('Date', 'wc-referral-system'); ?></th>
                        <th><?php _e('Status', 'wc-referral-system'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_referrals as $referral) : ?>
                        <tr>
                            <td>
                                <?php 
                                $user = get_user_by('id', $referral->referrer_id);
                                echo $user ? esc_html($user->display_name) : esc_html($referral->referrer_email);
                                ?>
                            </td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($referral->referral_date)); ?></td>
                            <td>
                                <?php if ($referral->status === 'completed') : ?>
                                    <span class="status-completed">✅</span>
                                <?php else : ?>
                                    <span class="status-pending">⏳</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="referral-links">
                <a href="<?php echo admin_url('admin.php?page=wc-referral-system'); ?>"><?php _e('View all referrals', 'wc-referral-system'); ?></a>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render referrals page
     */
    public function render_referrals_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_referrals';
        
        // Handle bulk actions
        if (isset($_POST['action']) && $_POST['action'] == 'mark_completed' && isset($_POST['referral_ids'])) {
            if (check_admin_referer('bulk_action_referrals', 'referral_nonce')) {
                $referral_ids = array_map('intval', $_POST['referral_ids']);
                
                foreach ($referral_ids as $referral_id) {
                    $wpdb->update(
                        $table_name,
                        array('status' => 'completed'),
                        array('id' => $referral_id),
                        array('%s'),
                        array('%d')
                    );
                    
                    // Get referral details
                    $referral = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $referral_id));
                    
                    // Create reward coupon for referrer if status changed to completed
                    if ($referral && $referral->referrer_id && empty($referral->reward_coupon_id)) {
                        $coupon = WC_Referral_Coupon::instance();
                        $coupon->create_referrer_reward_coupon($referral->referral_code, $referral->order_id);
                    }
                }
                
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Selected referrals marked as completed and rewards generated.', 'wc-referral-system') . '</p></div>';
            }
        }
        
        // Handle status changes
        if (isset($_GET['action']) && isset($_GET['referral_id']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'wc_referral_action')) {
            $referral_id = intval($_GET['referral_id']);
            $action = sanitize_text_field($_GET['action']);
            
            if ($action === 'complete') {
                $wpdb->update(
                    $table_name,
                    array('status' => 'completed'),
                    array('id' => $referral_id),
                    array('%s'),
                    array('%d')
                );
                
                // Get referral details
                $referral = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $referral_id));
                
                // Create reward coupon for referrer if status changed to completed
                if ($referral && !$referral->reward_coupon_id) {
                    $coupon = WC_Referral_Coupon::instance();
                    $coupon->create_referrer_reward_coupon($referral->referral_code, $referral->order_id);
                }
                
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Referral marked as completed and reward coupon generated.', 'wc-referral-system') . '</p></div>';
            } elseif ($action === 'delete') {
                $wpdb->delete(
                    $table_name,
                    array('id' => $referral_id),
                    array('%d')
                );
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Referral deleted successfully.', 'wc-referral-system') . '</p></div>';
            }
        }
        
        // Pagination setup
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Filter by status
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $status_query = '';
        $status_query_params = array();
        
        if ($status_filter) {
            $status_query = "WHERE status = %s";
            $status_query_params[] = $status_filter;
        }
        
        // Get total count
        $total_query = "SELECT COUNT(*) FROM $table_name $status_query";
        $total = $wpdb->get_var($wpdb->prepare($total_query, $status_query_params));
        $total_pages = ceil($total / $per_page);
        
        // Get referrals
        $referrals_query = "SELECT * FROM $table_name $status_query ORDER BY referral_date DESC LIMIT %d OFFSET %d";
        $query_params = array_merge($status_query_params, array($per_page, $offset));
        $referrals = $wpdb->get_results($wpdb->prepare($referrals_query, $query_params));
        
        // Status counts for filter
        $total_pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
        $total_completed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Referral System', 'wc-referral-system'); ?></h1>
            
            <hr class="wp-header-end">
            
            <ul class="subsubsub">
                <li>
                    <a href="<?php echo admin_url('admin.php?page=wc-referral-system'); ?>" class="<?php echo $status_filter === '' ? 'current' : ''; ?>">
                        <?php _e('All', 'wc-referral-system'); ?> <span class="count">(<?php echo $wpdb->get_var("SELECT COUNT(*) FROM $table_name"); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo add_query_arg('status', 'completed', admin_url('admin.php?page=wc-referral-system')); ?>" class="<?php echo $status_filter === 'completed' ? 'current' : ''; ?>">
                        <?php _e('Completed', 'wc-referral-system'); ?> <span class="count">(<?php echo $total_completed; ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo add_query_arg('status', 'pending', admin_url('admin.php?page=wc-referral-system')); ?>" class="<?php echo $status_filter === 'pending' ? 'current' : ''; ?>">
                        <?php _e('Pending', 'wc-referral-system'); ?> <span class="count">(<?php echo $total_pending; ?>)</span>
                    </a>
                </li>
            </ul>
            
            <form method="post">
                <?php wp_nonce_field('bulk_action_referrals', 'referral_nonce'); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="action">
                            <option value="-1"><?php _e('Bulk Actions', 'wc-referral-system'); ?></option>
                            <option value="mark_completed"><?php _e('Mark as Completed', 'wc-referral-system'); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php _e('Apply', 'wc-referral-system'); ?>">
                    </div>
                    
                    <?php if ($total_pages > 1) : ?>
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php echo sprintf(_n('%s item', '%s items', $total, 'wc-referral-system'), number_format_i18n($total)); ?></span>
                            <span class="pagination-links">
                                <?php
                                echo paginate_links(array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;',
                                    'total' => $total_pages,
                                    'current' => $current_page
                                ));
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column">
                                <input id="cb-select-all-1" type="checkbox">
                            </td>
                            <th scope="col" class="manage-column"><?php _e('ID', 'wc-referral-system'); ?></th>
                            <th scope="col" class="manage-column"><?php _e('Referrer', 'wc-referral-system'); ?></th>
                            <th scope="col" class="manage-column"><?php _e('Referee', 'wc-referral-system'); ?></th>
                            <th scope="col" class="manage-column"><?php _e('Date', 'wc-referral-system'); ?></th>
                            <th scope="col" class="manage-column"><?php _e('Status', 'wc-referral-system'); ?></th>
                            <th scope="col" class="manage-column"><?php _e('Order', 'wc-referral-system'); ?></th>
                            <th scope="col" class="manage-column"><?php _e('Reward', 'wc-referral-system'); ?></th>
                            <th scope="col" class="manage-column"><?php _e('Actions', 'wc-referral-system'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($referrals)) : ?>
                            <tr>
                                <td colspan="9"><?php _e('No referrals found.', 'wc-referral-system'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($referrals as $referral) : ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="referral_ids[]" value="<?php echo $referral->id; ?>">
                                    </th>
                                    <td><?php echo esc_html($referral->id); ?></td>
                                    <td>
                                        <?php 
                                        if ($referral->referrer_id) {
                                            $referrer = get_user_by('id', $referral->referrer_id);
                                            echo $referrer ? '<a href="' . admin_url('user-edit.php?user_id=' . $referral->referrer_id) . '">' . esc_html($referrer->display_name) . '</a>' : 'N/A';
                                        } elseif (!empty($referral->referrer_email)) {
                                            echo esc_html($referral->referrer_email);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($referral->referee_id) {
                                            $referee = get_user_by('id', $referral->referee_id);
                                            echo $referee ? '<a href="' . admin_url('user-edit.php?user_id=' . $referral->referee_id) . '">' . esc_html($referee->display_name) . '</a>' : 'N/A';
                                        } elseif (!empty($referral->referee_email)) {
                                            echo esc_html($referral->referee_email);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($referral->referral_date)); ?></td>
                                    <td>
                                        <?php
                                        if ($referral->status === 'completed') {
                                            echo '<mark class="status-completed">' . __('Completed', 'wc-referral-system') . '</mark>';
                                        } else {
                                            echo '<mark class="status-pending">' . __('Pending', 'wc-referral-system') . '</mark>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($referral->order_id) {
                                            echo '<a href="' . admin_url('post.php?post=' . $referral->order_id . '&action=edit') . '">#' . $referral->order_id . '</a>';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($referral->reward_coupon_id) {
                                            $coupon_code = get_the_title($referral->reward_coupon_id);
                                            echo '<a href="' . admin_url('post.php?post=' . $referral->reward_coupon_id . '&action=edit') . '">' . esc_html($coupon_code) . '</a>';
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($referral->status === 'pending') : ?>
                                            <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'complete', 'referral_id' => $referral->id), admin_url('admin.php?page=wc-referral-system')), 'wc_referral_action'); ?>" class="button button-small"><?php _e('Mark Complete', 'wc-referral-system'); ?></a>
                                        <?php endif; ?>
                                        <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'delete', 'referral_id' => $referral->id), admin_url('admin.php?page=wc-referral-system')), 'wc_referral_action'); ?>" class="button button-small" onclick="return confirm('<?php _e('Are you sure you want to delete this referral?', 'wc-referral-system'); ?>');"><?php _e('Delete', 'wc-referral-system'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <?php
    }

    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_referrals';
        
        // Get referral statistics
        $total_referrals = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $completed_referrals = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
        $pending_referrals = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
        
        // Get top referrers
        $top_referrers = $wpdb->get_results("
            SELECT referrer_id, COUNT(*) as count FROM $table_name 
            WHERE status = 'completed' 
            GROUP BY referrer_id 
            ORDER BY count DESC 
            LIMIT 10
        ");
        
        // Get monthly statistics for the past 12 months
        $monthly_stats = array();
        $end_date = current_time('mysql');
        $start_date = date('Y-m-d H:i:s', strtotime('-12 months', strtotime($end_date)));
        
        $monthly_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE_FORMAT(referral_date, '%%Y-%%m') as month,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM $table_name
            WHERE referral_date BETWEEN %s AND %s
            GROUP BY month
            ORDER BY month ASC
        ", $start_date, $end_date));
        
        foreach ($monthly_data as $data) {
            $month_year = explode('-', $data->month);
            $month_name = date_i18n('F Y', mktime(0, 0, 0, $month_year[1], 1, $month_year[0]));
            $monthly_stats[$month_name] = array(
                'total' => $data->total,
                'completed' => $data->completed
            );
        }
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Referral Analytics', 'wc-referral-system'); ?></h1>
            
            <hr class="wp-header-end">
            
            <div class="metabox-holder">
                <div class="postbox-container" style="width: 100%;">
                    <div class="postbox">
                        <h2 class="hndle"><span><?php _e('Overview', 'wc-referral-system'); ?></span></h2>
                        <div class="inside">
                            <div class="analytics-overview">
                                <div class="analytics-box">
                                    <h3><?php _e('Total Referrals', 'wc-referral-system'); ?></h3>
                                    <h2><?php echo $total_referrals; ?></h2>
                                </div>
                                <div class="analytics-box">
                                    <h3><?php _e('Completed Referrals', 'wc-referral-system'); ?></h3>
                                    <h2 class="completed"><?php echo $completed_referrals; ?></h2>
                                </div>
                                <div class="analytics-box">
                                    <h3><?php _e('Pending Referrals', 'wc-referral-system'); ?></h3>
                                    <h2 class="pending"><?php echo $pending_referrals; ?></h2>
                                </div>
                            </div>
                            
                            <?php if ($total_referrals > 0) : ?>
                                <div class="conversion-rate">
                                    <h3><?php _e('Conversion Rate', 'wc-referral-system'); ?></h3>
                                    <div class="conversion-bar">
                                        <?php
                                        $conversion_rate = ($completed_referrals / $total_referrals) * 100;
                                        ?>
                                        <div class="conversion-progress" style="width: <?php echo min(100, $conversion_rate); ?>%;">
                                            <?php echo number_format($conversion_rate, 2); ?>%
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="analytics-columns">
                <div class="analytics-column">
                    <div class="postbox">
                        <h2 class="hndle"><span><?php _e('Top Referrers', 'wc-referral-system'); ?></span></h2>
                        <div class="inside">
                            <?php if (!empty($top_referrers)) : ?>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('User', 'wc-referral-system'); ?></th>
                                            <th><?php _e('Successful Referrals', 'wc-referral-system'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_referrers as $referrer) : ?>
                                            <?php $user = get_user_by('id', $referrer->referrer_id); ?>
                                            <tr>
                                                <td>
                                                    <?php if ($user) : ?>
                                                        <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $user->ID)); ?>">
                                                            <?php echo esc_html($user->display_name); ?>
                                                        </a>
                                                    <?php else : ?>
                                                        <?php _e('Unknown User', 'wc-referral-system'); ?> (ID: <?php echo esc_html($referrer->referrer_id); ?>)
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo esc_html($referrer->count); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else : ?>
                                <p><?php _e('No referral data available yet.', 'wc-referral-system'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="analytics-column">
                    <div class="postbox">
                        <h2 class="hndle"><span><?php _e('Monthly Statistics', 'wc-referral-system'); ?></span></h2>
                        <div class="inside">
                            <?php if (!empty($monthly_stats)) : ?>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Month', 'wc-referral-system'); ?></th>
                                            <th><?php _e('Total Referrals', 'wc-referral-system'); ?></th>
                                            <th><?php _e('Completed', 'wc-referral-system'); ?></th>
                                            <th><?php _e('Conversion', 'wc-referral-system'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($monthly_stats as $month => $stats) : ?>
                                            <tr>
                                                <td><?php echo esc_html($month); ?></td>
                                                <td><?php echo esc_html($stats['total']); ?></td>
                                                <td><?php echo esc_html($stats['completed']); ?></td>
                                                <td>
                                                    <?php
                                                    if ($stats['total'] > 0) {
                                                        $conversion = ($stats['completed'] / $stats['total']) * 100;
                                                        echo number_format($conversion, 2) . '%';
                                                    } else {
                                                        echo '—';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else : ?>
                                <p><?php _e('No monthly data available yet.', 'wc-referral-system'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Save settings
        if (isset($_POST['wc_referral_settings_nonce']) && wp_verify_nonce($_POST['wc_referral_settings_nonce'], 'wc_referral_settings')) {
            
            update_option('wc_referral_referee_discount', sanitize_text_field($_POST['referee_discount']));
            update_option('wc_referral_referee_expiry_days', sanitize_text_field($_POST['referee_expiry_days']));
            update_option('wc_referral_referrer_reward', sanitize_text_field($_POST['referrer_reward']));
            update_option('wc_referral_referrer_expiry_days', sanitize_text_field($_POST['referrer_expiry_days']));
            
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'wc-referral-system') . '</p></div>';
        }
        
        $referee_discount = get_option('wc_referral_referee_discount', '10');
        $referee_expiry_days = get_option('wc_referral_referee_expiry_days', '30');
        $referrer_reward = get_option('wc_referral_referrer_reward', '15');
        $referrer_expiry_days = get_option('wc_referral_referrer_expiry_days', '60');
        
        ?>
        <div class="wrap">
            <h1><?php _e('Referral System Settings', 'wc-referral-system'); ?></h1>
            
            <form method="post">
                <?php wp_nonce_field('wc_referral_settings', 'wc_referral_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="referee_discount"><?php _e('Referee Discount (%)', 'wc-referral-system'); ?></label></th>
                        <td>
                            <input type="number" name="referee_discount" id="referee_discount" value="<?php echo esc_attr($referee_discount); ?>" min="0" max="100" step="1">
                            <p class="description"><?php _e('Discount percentage for new customers who use a referral link.', 'wc-referral-system'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="referee_expiry_days"><?php _e('Referee Coupon Expiry (Days)', 'wc-referral-system'); ?></label></th>
                        <td>
                            <input type="number" name="referee_expiry_days" id="referee_expiry_days" value="<?php echo esc_attr($referee_expiry_days); ?>" min="1" step="1">
                            <p class="description"><?php _e('Number of days until the referee\'s discount coupon expires.', 'wc-referral-system'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="referrer_reward"><?php _e('Referrer Reward (%)', 'wc-referral-system'); ?></label></th>
                        <td>
                            <input type="number" name="referrer_reward" id="referrer_reward" value="<?php echo esc_attr($referrer_reward); ?>" min="0" max="100" step="1">
                            <p class="description"><?php _e('Discount percentage for existing customers when their referral completes a purchase.', 'wc-referral-system'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="referrer_expiry_days"><?php _e('Referrer Reward Expiry (Days)', 'wc-referral-system'); ?></label></th>
                        <td>
                            <input type="number" name="referrer_expiry_days" id="referrer_expiry_days" value="<?php echo esc_attr($referrer_expiry_days); ?>" min="1" step="1">
                            <p class="description"><?php _e('Number of days until the referrer\'s reward coupon expires.', 'wc-referral-system'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes', 'wc-referral-system'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Add meta box to order page
     */
    public function add_order_meta_box() {
        add_meta_box(
            'wc_referral_meta_box',
            __('Referral Information', 'wc-referral-system'),
            array($this, 'show_referral_meta_box'),
            'shop_order',
            'side',
            'default'
        );
    }

    /**
     * Show referral information in order meta box
     */
    public function show_referral_meta_box($post) {
        global $wpdb;
        $order_id = $post->ID;
        $table_name = $wpdb->prefix . 'wc_referrals';
        
        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_id = %d",
            $order_id
        ));
        
        if ($referral) {
            $referrer = get_user_by('id', $referral->referrer_id);
            
            echo '<div class="wc-referral-info">';
            
            echo '<p><strong>' . __('Status:', 'wc-referral-system') . '</strong> ';
            echo '<span class="status-' . esc_attr($referral->status) . '">' . ucfirst(esc_html($referral->status)) . '</span></p>';
            
            echo '<p><strong>' . __('Referrer:', 'wc-referral-system') . '</strong> ';
            if ($referrer) {
                echo '<a href="' . esc_url(admin_url('user-edit.php?user_id=' . $referrer->ID)) . '">' . esc_html($referrer->display_name) . '</a>';
            } else {
                echo __('Unknown User (ID: ', 'wc-referral-system') . esc_html($referral->referrer_id) . ')';
            }
            echo '</p>';
            
            echo '<p><strong>' . __('Date:', 'wc-referral-system') . '</strong> ';
            echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($referral->referral_date));
            echo '</p>';
            
            if ($referral->reward_coupon_id) {
                $coupon_code = get_the_title($referral->reward_coupon_id);
                echo '<p><strong>' . __('Reward Coupon:', 'wc-referral-system') . '</strong> ';
                echo '<code>' . esc_html($coupon_code) . '</code>';
                echo '</p>';
            }
            
            echo '</div>';
        } else {
            echo '<p>' . __('No referral information found for this order.', 'wc-referral-system') . '</p>';
        }
    }

    /**
     * Add column to orders admin screen
     */
    public function add_order_referral_column($columns) {
        $new_columns = array();
        
        foreach ($columns as $column_name => $column_info) {
            $new_columns[$column_name] = $column_info;
            
            if ($column_name == 'order_status') {
                $new_columns['referral'] = __('Referral', 'wc-referral-system');
            }
        }
        
        return $new_columns;
    }

    /**
     * Show referral information in orders column
     */
    public function show_order_referral_column_content($column) {
        global $post, $wpdb;
        
        if ($column != 'referral') {
            return;
        }
        
        $order_id = $post->ID;
        $table_name = $wpdb->prefix . 'wc_referrals';
        
        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_id = %d",
            $order_id
        ));
        
        if ($referral) {
            $user = get_user_by('id', $referral->referrer_id);
            $username = $user ? $user->display_name : __('User ID: ', 'wc-referral-system') . $referral->referrer_id;
            
            echo '<mark class="referral ' . esc_attr($referral->status) . '" title="' . esc_attr($username) . '">';
            echo '<span>' . ucfirst(esc_html($referral->status)) . '</span>';
            echo '</mark>';
        } else {
            echo '<span class="na">—</span>';
        }
    }

    /**
     * Add CSS for referral column
     */
    public function add_referral_column_style() {
        if (get_current_screen()->id != 'edit-shop_order') {
            return;
        }
        ?>
        <style>
            .column-referral {
                width: 90px;
            }
            mark.referral {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 3px;
                color: #fff;
                font-size: 12px;
                font-weight: bold;
                text-align: center;
            }
            mark.referral.completed {
                background: #2ecc71;
            }
            mark.referral.pending {
                background: #f39c12;
            }
        </style>
        <?php
    }

    /**
     * Add referral information to user profile
     */
    public function add_referral_info_to_user_profile($user) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_referrals';
        
        // Get user's referral code
        $referral_code = get_user_meta($user->ID, '_wc_referral_code', true);
        
        if (empty($referral_code)) {
            $referral_code = WC_Referral_Codes::instance()->generate_user_referral_code($user->ID);
        }
        
        $referral_link = WC_Referral_Codes::instance()->get_user_referral_link($user->ID);
        
        // Get referrals made by this user
        $referrals_made = WC_Referral_Codes::instance()->get_user_referrals($user->ID);
        
        // Get referrals where this user was referred
        $referred_by = WC_Referral_Codes::instance()->get_user_referrer($user->ID);
        
        ?>
        <h2><?php _e('Referral Information', 'wc-referral-system'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="referral_code"><?php _e('Referral Code', 'wc-referral-system'); ?></label></th>
                <td>
                    <input type="text" name="referral_code" id="referral_code" value="<?php echo esc_attr($referral_code); ?>" class="regular-text" readonly />
                </td>
            </tr>
            <tr>
                <th><label for="referral_link"><?php _e('Referral Link', 'wc-referral-system'); ?></label></th>
                <td>
                    <input type="text" name="referral_link" id="referral_link" value="<?php echo esc_url($referral_link); ?>" class="regular-text" readonly />
                </td>
            </tr>
        </table>
        
        <h3><?php _e('Referrals Made', 'wc-referral-system'); ?></h3>
        <table class="wp-list-table widefat fixed striped" style="width: 95%;">
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
                <?php if (empty($referrals_made)) : ?>
                    <tr>
                        <td colspan="5"><?php _e('No referrals made yet.', 'wc-referral-system'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($referrals_made as $referral) : ?>
                        <tr>
                            <td>
                                <?php 
                                if ($referral->referee_id) {
                                    $referee = get_user_by('id', $referral->referee_id);
                                    echo $referee ? $referee->display_name : $referral->referee_email;
                                } elseif (!empty($referral->referee_email)) {
                                    echo $referral->referee_email;
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($referral->referral_date)); ?></td>
                            <td>
                                <?php 
                                if ($referral->status === 'completed') {
                                    echo '<span style="color: green;">' . __('Completed', 'wc-referral-system') . '</span>';
                                } else {
                                    echo '<span style="color: orange;">' . __('Pending', 'wc-referral-system') . '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($referral->order_id) {
                                    echo '<a href="' . admin_url('post.php?post=' . $referral->order_id . '&action=edit') . '">#' . $referral->order_id . '</a>';
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($referral->reward_coupon_id) {
                                    $coupon_code = get_the_title($referral->reward_coupon_id);
                                    echo '<a href="' . admin_url('post.php?post=' . $referral->reward_coupon_id . '&action=edit') . '">' . $coupon_code . '</a>';
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <h3><?php _e('Referred By', 'wc-referral-system'); ?></h3>
        <?php if ($referred_by) : 
            $referrer = get_user_by('id', $referred_by->referrer_id);
            ?>
            <p>
                <?php _e('This user was referred by:', 'wc-referral-system'); ?> 
                <strong>
                    <?php 
                    if ($referrer) {
                        echo '<a href="' . admin_url('user-edit.php?user_id=' . $referrer->ID) . '">' . $referrer->display_name . '</a>';
                    } else {
                        echo $referred_by->referrer_email;
                    }
                    ?>
                </strong>
            </p>
            <p><?php _e('Referral Date:', 'wc-referral-system'); ?> <?php echo date('Y-m-d', strtotime($referred_by->referral_date)); ?></p>
            <p>
                <?php _e('Order:', 'wc-referral-system'); ?> 
                <?php 
                if ($referred_by->order_id) {
                    echo '<a href="' . admin_url('post.php?post=' . $referred_by->order_id . '&action=edit') . '">#' . $referred_by->order_id . '</a>';
                } else {
                    echo 'N/A';
                }
                ?>
            </p>
        <?php else : ?>
            <p><?php _e('This user was not referred by anyone.', 'wc-referral-system'); ?></p>
        <?php endif; ?>
        <?php
    }
}