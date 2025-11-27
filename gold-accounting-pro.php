<?php
/*
Plugin Name: Gold Accounting Pro
Plugin URI:  https://example.com/
Description: سیستم کامل حسابداری و مدیریت فروشگاه‌های طلا و جواهر
Version:     4.1.2
Author:      Your Name
Author URI:  https://example.com/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: gold-accounting-pro
*/

if (!defined('ABSPATH')) {
    exit;
}

// The rest of the code will be added incrementally.
class GoldAccountingPro {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    public function init() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'check_database_version'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'register_custom_post_types'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX Handlers
        $this->register_ajax_handlers();
        
        add_shortcode('gold_accounting_pro', array($this, 'main_panel_shortcode'));
        add_action('template_redirect', array($this, 'force_template'));
        add_action('wp_footer', array($this, 'output_inline_scripts'));
    }
    private function register_ajax_handlers() {
        $ajax_actions = array(
            'get_live_gold_price',
            'gcg_save_invoice',
            'gcg_load_invoice',
            'gcg_delete_invoice',
            'gcg_fetch_invoices',
            'gcg_save_product',
            'gcg_get_products',
            'gcg_delete_product',
            'gcg_save_customer',
            'gcg_get_customers',
            'gcg_delete_customer',
            'gcg_save_gold_purchase',
            'gcg_get_gold_purchases',
            'gcg_delete_gold_purchase',
            'gcg_save_coin',
            'gcg_get_coins',
            'gcg_delete_coin',
            'gcg_save_ticket',
            'gcg_get_tickets',
            'gcg_get_ticket_details',
            'gcg_save_ticket_reply',
            'gcg_get_dashboard_stats',
            'gcg_get_sales_report',
            'gcg_search_invoices_by_id',
            'gcg_get_customer_stats',
            'gcg_get_product_stats',
            'gcg_update_invoice_settings',
            'gcg_get_invoice_settings_ajax',
            'gcg_update_shop_settings',
            'gcg_get_invoice_print_html',
            'gcg_save_exchange_invoice',
            'gcg_close_ticket'
        );
        
        foreach ($ajax_actions as $action) {
            add_action('wp_ajax_' . $action, array($this, 'ajax_' . $action));
        }
        
        add_action('wp_ajax_nopriv_get_live_gold_price', array($this, 'ajax_get_live_gold_price'));
        add_action('wp_ajax_nopriv_gcg_shop_login', array($this, 'ajax_gcg_shop_login'));
    }
    
    public function activate() {
        $this->create_database_tables();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('gold-accounting-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    public function check_database_version() {
        $current_db_version = get_option('gcg_db_version', '1.0.0');
        if (version_compare($current_db_version, '4.1.2', '<')) {
            $this->create_database_tables();
            update_option('gcg_db_version', '4.1.2');
        }
    }
    public function register_custom_post_types() {
        register_post_type('gcg_invoice', array(
            'labels' => array(
                'name' => 'فاکتورهای طلا',
                'singular_name' => 'فاکتور طلا',
                'menu_name' => 'فاکتورها',
                'add_new' => 'افزودن فاکتور جدید',
                'add_new_item' => 'افزودن فاکتور جدید',
                'edit_item' => 'ویرایش فاکتور',
                'new_item' => 'فاکتور جدید',
                'all_items' => 'همه فاکتورها',
                'view_item' => 'مشاهده فاکتور',
                'search_items' => 'جستجوی فاکتور',
                'not_found' => 'فاکتوری یافت نشد',
                'not_found_in_trash' => 'فاکتوری در زباله‌دان یافت نشد',
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'gold_accounting_pro',
            'supports' => array('title')
        ));
        
        register_post_type('gcg_announcement', array(
            'labels' => array(
                'name' => 'اعلامیه‌ها',
                'singular_name' => 'اعلامیه',
                'menu_name' => 'اعلامیه‌ها',
                'add_new' => 'ارسال اعلامیه جدید',
                'add_new_item' => 'ارسال اعلامیه جدید',
                'edit_item' => 'ویرایش اعلامیه',
                'new_item' => 'اعلامیه جدید',
                'all_items' => 'همه اعلامیه‌ها',
                'search_items' => 'جستجوی اعلامیه',
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'gold_accounting_pro',
            'supports' => array('title', 'editor')
        ));

        register_post_type('gcg_exchange', array(
            'labels' => array('name' => 'فاکتورهای تعویض'),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'gold_accounting_pro',
            'supports' => array('title')
        ));
        
        add_action('add_meta_boxes', array($this, 'add_announcement_meta_box'));
        add_action('save_post_gcg_announcement', array($this, 'save_announcement_meta_box'));
    }
    
    public function add_announcement_meta_box() {
        add_meta_box('gcg_announcement_target', 'تنظیمات ارسال اعلامیه', array($this, 'announcement_meta_box_html'), 'gcg_announcement', 'side', 'high');
    }
    
    public function announcement_meta_box_html($post) {
        wp_nonce_field(basename(__FILE__), 'gcg_announcement_nonce');
        $selected_shop_id = get_post_meta($post->ID, 'gcg_target_shop_id', true);
        $shops = get_option('gcg_shops', array());
        ?>
        <label for="gcg_target_shop" style="font-weight: bold; display: block; margin-bottom: 5px;">گیرنده اعلامیه:</label>
        <select name="gcg_target_shop" id="gcg_target_shop" style="width: 100%;">
            <option value="all" <?php selected($selected_shop_id, 'all'); ?>>ارسال برای همه فروشگاه‌ها (جمعی)</option>
            <?php foreach ($shops as $shop): ?>
                <option value="<?php echo esc_attr($shop['id']); ?>" <?php selected($selected_shop_id, $shop['id']); ?>>
                    <?php echo esc_html($shop['name']); ?> (تکی)
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description" style="font-size: 11px;">انتخاب کنید که این اعلامیه به کدام فروشگاه یا همه آنها ارسال شود.</p>
        <?php
    }
    
    public function save_announcement_meta_box($post_id) {
        if (!isset($_POST['gcg_announcement_nonce']) || !wp_verify_nonce($_POST['gcg_announcement_nonce'], basename(__FILE__))) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['gcg_target_shop'])) {
            update_post_meta($post_id, 'gcg_target_shop_id', sanitize_text_field($_POST['gcg_target_shop']));
        }
    }
    public function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $tables = array(
            "gcg_products" => "CREATE TABLE {$wpdb->prefix}gcg_products (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                shop_id varchar(50) NOT NULL,
                name varchar(255) NOT NULL,
                category varchar(100) DEFAULT '',
                purity VARCHAR(20) DEFAULT '18',
                weight decimal(10,3) DEFAULT 0,
                default_labor_percent decimal(5,2) DEFAULT 10.00,
                default_profit_percent decimal(5,2) DEFAULT 7.00,
                description text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY shop_id (shop_id)
            ) $charset_collate;",
            
            "gcg_customers" => "CREATE TABLE {$wpdb->prefix}gcg_customers (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                shop_id varchar(50) NOT NULL,
                name varchar(255) NOT NULL,
                phone varchar(20) DEFAULT '',
                email varchar(100) DEFAULT '',
                address text,
                total_purchases int DEFAULT 0,
                total_amount decimal(15,2) DEFAULT 0,
                last_purchase_date datetime NULL,
                notes text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY shop_id (shop_id),
                KEY phone (phone)
            ) $charset_collate;",
            
            "gcg_gold_purchases" => "CREATE TABLE {$wpdb->prefix}gcg_gold_purchases (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                shop_id varchar(50) NOT NULL,
                customer_id mediumint(9) DEFAULT NULL,
                customer_name varchar(255) NOT NULL,
                weight decimal(10,3) NOT NULL,
                purity varchar(20) DEFAULT '18k',
                purchase_price decimal(15,2) NOT NULL,
                purchase_date datetime NOT NULL,
                description text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY shop_id (shop_id),
                KEY customer_id (customer_id),
                KEY purchase_date (purchase_date)
            ) $charset_collate;",
            
            "gcg_coins" => "CREATE TABLE {$wpdb->prefix}gcg_coins (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                shop_id varchar(50) NOT NULL,
                transaction_type enum('buy','sell') NOT NULL,
                coin_type varchar(50) NOT NULL,
                weight decimal(10,3) NOT NULL,
                quantity int NOT NULL,
                price_per_coin decimal(15,2) NOT NULL,
                total_amount decimal(15,2) NOT NULL,
                transaction_date datetime NOT NULL,
                customer_id mediumint(9) DEFAULT NULL,
                customer_name varchar(255) DEFAULT '',
                notes text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY shop_id (shop_id),
                KEY transaction_type (transaction_type),
                KEY transaction_date (transaction_date)
            ) $charset_collate;",
            
            "gcg_tickets" => "CREATE TABLE {$wpdb->prefix}gcg_tickets (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                shop_id varchar(50) NOT NULL,
                subject varchar(255) NOT NULL,
                message text NOT NULL,
                status enum('open','pending','resolved','closed') DEFAULT 'open',
                priority enum('low','medium','high','urgent') DEFAULT 'medium',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY shop_id (shop_id),
                KEY status (status)
            ) $charset_collate;",
            
            "gcg_ticket_replies" => "CREATE TABLE {$wpdb->prefix}gcg_ticket_replies (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                ticket_id mediumint(9) NOT NULL,
                user_type enum('shop','admin') NOT NULL,
                message text NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY ticket_id (ticket_id)
            ) $charset_collate;"
        );
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tables as $table_sql) {
            dbDelta($table_sql);
        }
    }
    public function enqueue_scripts() {
        global $post;
        
        if (is_page() && get_post_meta($post->ID, 'gcg_shop_user_id', true)) {
            wp_enqueue_style('dashicons');
            wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
            wp_enqueue_style('gcg-main-style', plugin_dir_url(__FILE__) . 'assets/css/main.css', array(), '4.1.2');

            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', true);


            wp_localize_script('jquery', 'gcg_vars', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'gcg_security_nonce' => wp_create_nonce('gcg_nonce'),
                'current_shop_id' => get_post_meta($post->ID, 'gcg_shop_id', true),
                'current_user_id' => get_current_user_id()
            ));
        }
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'gold_accounting_pro') !== false) {
            wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', true);
        }
    }
    
    public function add_admin_menu() {
        add_menu_page('سیستم حسابداری طلا', 'طلا و حسابداری', 'manage_options', 'gold_accounting_pro', array($this, 'admin_dashboard_page'), 'dashicons-chart-line', 6);
        add_submenu_page('gold_accounting_pro', 'داشبورد', 'داشبورد', 'manage_options', 'gold_accounting_pro', array($this, 'admin_dashboard_page'));
        add_submenu_page('gold_accounting_pro', 'فروشگاه‌ها', 'فروشگاه‌ها', 'manage_options', 'gcg_shops', array($this, 'shops_management_page'));
        add_submenu_page('gold_accounting_pro', 'گزارشات جامع', 'گزارشات', 'manage_options', 'gcg_reports', array($this, 'reports_page'));
        add_submenu_page('gold_accounting_pro', 'اعلامیه‌ها', 'اعلامیه‌ها', 'manage_options', 'edit.php?post_type=gcg_announcement');
        add_submenu_page('gold_accounting_pro', 'تیکت‌های پشتیبانی', 'پشتیبانی', 'manage_options', 'gcg_tickets', array($this, 'tickets_page'));
        add_submenu_page('gold_accounting_pro', 'تنظیمات', 'تنظیمات', 'manage_options', 'gcg_settings', array($this, 'settings_page'));
    }
    public function admin_dashboard_page() {
        $shops = get_option('gcg_shops', array());
        $total_invoices = $this->get_total_invoices_count();
        $today_invoices = $this->get_today_invoices_count();
        $monthly_sales = $this->get_monthly_sales_total();
        $active_shops = $this->get_active_shops_count();
        ?>
        <div class="wrap">
            <h1>داشبورد مدیریت سیستم طلا</h1>
            
            <div class="gcg-admin-stats" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
                <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666;">تعداد فروشگاه‌ها</h3>
                    <div style="font-size: 2em; font-weight: bold; color: #3498db;"><?php echo esc_html(count($shops)); ?></div>
                </div>
                <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666;">فاکتورهای امروز</h3>
                    <div style="font-size: 2em; font-weight: bold; color: #27ae60;"><?php echo esc_html($today_invoices); ?></div>
                </div>
                <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666;">فروش ماه</h3>
                    <div style="font-size: 2em; font-weight: bold; color: #e74c3c;"><?php echo esc_html(number_format($monthly_sales)); ?> تومان</div>
                </div>
                <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666;">فعال‌ترین فروشگاه‌ها</h3>
                    <div style="font-size: 2em; font-weight: bold; color: #f39c12;"><?php echo esc_html($active_shops); ?></div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 30px;">
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3>فروشگاه‌های اخیر</h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th>نام فروشگاه</th>
                                <th>کاربر</th>
                                <th>تعداد فاکتور</th>
                                <th>فروش کل</th>
                                <th>آخرین فعالیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recent_shops = array_slice($shops, 0, 5);
                            if (empty($recent_shops)): ?>
                                <tr><td colspan="5">هیچ فروشگاهی ثبت نشده است.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_shops as $shop): 
                                    $user = get_user_by('id', $shop['user_id']);
                                    ?>
                                <tr>
                                    <td><?php echo esc_html($shop['name']); ?></td>
                                    <td><?php echo $user ? esc_html($user->user_login) : 'حذف شده'; ?></td>
                                    <td><?php echo $this->get_shop_invoice_count($shop['id']); ?></td>
                                    <td><?php echo number_format($this->get_shop_total_sales($shop['id'])); ?> تومان</td>
                                    <td><?php echo $this->get_shop_last_activity($shop['id']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3>آمار سریع</h3>
                    <div style="margin-top: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                            <span>کل فاکتورها:</span>
                            <strong><?php echo esc_html($total_invoices); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                            <span>میانگین فاکتور:</span>
                            <strong><?php echo esc_html(number_format($monthly_sales / max($today_invoices, 1))); ?> تومان</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                            <span>فروش امروز:</span>
                            <strong><?php echo esc_html(number_format($this->get_today_sales())); ?> تومان</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function shops_management_page() {
        $shops = get_option('gcg_shops', array());
        
        if (isset($_POST['gcg_add_shop'])) {
            $this->handle_add_shop($shops);
            $shops = get_option('gcg_shops', array());
        }
        
        if (isset($_POST['gcg_update_shop'])) {
            $this->handle_update_shop($shops);
            $shops = get_option('gcg_shops', array());
        }

        if (isset($_POST['gcg_reset_password'])) {
            $this->handle_reset_password();
        }

        if (isset($_POST['gcg_create_new_user'])) {
            $shops = get_option('gcg_shops', array());
            $this->handle_create_new_user($shops);
            $shops = get_option('gcg_shops', array());
        }
        
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['shop_id'])) {
            $this->handle_delete_shop($shops);
            $shops = get_option('gcg_shops', array());
        }
        
        $this->render_shops_management_html($shops);
    }
    private function handle_add_shop(&$shops) {
        $shop_name = sanitize_text_field($_POST['shop_name']);
        $username = sanitize_user($_POST['shop_username']);
        $password = sanitize_text_field($_POST['shop_password']);

        if (empty($password)) {
            echo '<div class="notice notice-error is-dismissible"><p>رمز عبور نمی‌تواند خالی باشد.</p></div>';
            return;
        }

        if (username_exists($username)) {
            echo '<div class="notice notice-error is-dismissible"><p>نام کاربری از قبل وجود دارد.</p></div>';
            return;
        }

        $user_id = wp_create_user($username, $password);
        if (is_wp_error($user_id)) {
            echo '<div class="notice notice-error is-dismissible"><p>خطا در ایجاد کاربر: ' . $user_id->get_error_message() . '</p></div>';
            return;
        }

        $user = new WP_User($user_id);
        $user->set_role('subscriber');
        
        $new_shop = array(
            'id' => uniqid(),
            'user_id' => $user_id,
            'name' => $shop_name,
            'address' => sanitize_textarea_field($_POST['shop_address']),
            'phone' => sanitize_text_field($_POST['shop_phone']),
            'logo' => esc_url_raw($_POST['shop_logo']),
            'instagram' => sanitize_text_field($_POST['shop_instagram']),
            'telegram' => sanitize_text_field($_POST['shop_telegram'])
        );
        $shops[] = $new_shop;
        update_option('gcg_shops', $shops);

        $page_id = wp_insert_post(array(
            'post_title' => 'پنل مدیریت ' . $shop_name,
            'post_content' => '[gold_accounting_pro]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => 'gcg-shop-' . $new_shop['id'],
        ));

        if (!is_wp_error($page_id)) {
            update_post_meta($page_id, 'gcg_shop_user_id', $user_id);
            update_post_meta($page_id, 'gcg_shop_id', $new_shop['id']);
            echo '<div class="notice notice-success is-dismissible"><p>فروشگاه جدید با موفقیت اضافه شد.</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>خطا در ایجاد صفحه فروشگاه.</p></div>';
        }
    }
    
    private function render_shops_management_html($shops) {
        ?>
        <div class="wrap" style="direction: rtl;">
            <h1>مدیریت فروشگاه‌های طلا</h1>
            
            <?php 
            $show_add_form = isset($_GET['action']) && $_GET['action'] === 'add';
            $show_edit_form = isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['shop_id']);

            if ($show_edit_form) {
                $this->render_edit_shop_form($shops);
            } elseif ($show_add_form) {
                $this->render_add_shop_form();
            } else {
                $this->render_shops_list($shops);
            }
            ?>
        </div>
        <?php
    }
    
    private function render_shops_list($shops) {
        ?>
        <h2>لیست فروشگاه‌ها</h2>
        <p><a href="<?php echo esc_url(admin_url('admin.php?page=gcg_shops&action=add')); ?>" class="page-title-action">افزودن فروشگاه جدید</a></p>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>نام فروشگاه</th>
                    <th>نام کاربری</th>
                    <th>لینک اختصاصی</th>
                    <th>تعداد فاکتور</th>
                    <th>فروش کل</th>
                    <th>آخرین فعالیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($shops)): ?>
                    <tr><td colspan="7">هیچ فروشگاهی ثبت نشده است.</td></tr>
                <?php else: ?>
                    <?php foreach ($shops as $shop): 
                        $user = get_user_by('id', $shop['user_id']);
                        $page = get_posts(array(
                            'post_type' => 'page',
                            'meta_key' => 'gcg_shop_id',
                            'meta_value' => $shop['id'],
                            'posts_per_page' => 1,
                        ));
                        $page_link = $page ? get_permalink($page[0]->ID) : '#';
                        $invoice_count = $this->get_shop_invoice_count($shop['id']);
                        $total_sales = $this->get_shop_total_sales($shop['id']);
                        $last_activity = $this->get_shop_last_activity($shop['id']);
                    ?>
                    <tr>
                        <td><?php echo esc_html($shop['name']); ?></td>
                        <td><?php echo $user ? esc_html($user->user_login) : 'حذف شده'; ?></td>
                        <td>
                            <input type="text" value="<?php echo esc_url($page_link); ?>" readonly style="width: 200px; direction: ltr; font-size: 11px;">
                            <button onclick="navigator.clipboard.writeText('<?php echo esc_js(esc_url($page_link)); ?>')">کپی</button>
                        </td>
                        <td><?php echo esc_html($invoice_count); ?></td>
                        <td><?php echo esc_html(number_format($total_sales)); ?> تومان</td>
                        <td><?php echo esc_html($last_activity); ?></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=gcg_shops&action=edit&shop_id=' . $shop['id'])); ?>">ویرایش</a> |
                            <a href="<?php echo esc_url(admin_url('admin.php?page=gcg_shops&action=delete&shop_id=' . $shop['id'])); ?>" onclick="return confirm('آیا از حذف مطمئن هستید؟');">حذف</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    private function render_add_shop_form() {
        ?>
        <h2>افزودن فروشگاه جدید</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="shop_name">نام فروشگاه:</label></th>
                    <td><input name="shop_name" type="text" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="shop_username">نام کاربری:</label></th>
                    <td><input name="shop_username" type="text" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="shop_password">رمز عبور:</label></th>
                    <td><input name="shop_password" type="text" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="shop_address">آدرس:</label></th>
                    <td><textarea name="shop_address" class="large-text"></textarea></td>
                </tr>
                <tr>
                    <th><label for="shop_phone">تلفن:</label></th>
                    <td><input name="shop_phone" type="text" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="shop_logo">لوگو:</label></th>
                    <td><input name="shop_logo" type="url" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="shop_instagram">اینستاگرام:</label></th>
                    <td><input name="shop_instagram" type="text" class="regular-text" placeholder="@username"></td>
                </tr>
                <tr>
                    <th><label for="shop_telegram">تلگرام:</label></th>
                    <td><input name="shop_telegram" type="text" class="regular-text" placeholder="@username"></td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="gcg_add_shop" class="button button-primary" value="افزودن فروشگاه"></p>
        </form>
        <?php
    }
    // AJAX Handlers - FIXED VERSIONS
    public function ajax_get_live_gold_price() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        $price_rial = $this->get_live_gold_price(true);
        if (is_numeric($price_rial)) {
            wp_send_json_success(array('price' => round($price_rial / 10)));
        } else {
            wp_send_json_error(array('message' => $price_rial));
        }
    }
    
    public function ajax_gcg_save_invoice() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        $shop_id = sanitize_text_field($_POST['shop_id']);
        $invoice_data = json_decode(stripslashes($_POST['invoice_data']), true);
        $invoice_id_to_edit = intval($_POST['invoice_id_to_edit']);
        
        if (empty($shop_id) || !current_user_can('read')) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز یا اطلاعات ناقص.'));
        }

        // === SERVER-SIDE RECALCULATION START ===
        $subtotal = 0;
        $total_labor_profit = 0;
        $sanitized_items = array(); // Create a new array to store sanitized item data

        if (!empty($invoice_data['items'])) {
            foreach ($invoice_data['items'] as $item) {
                // Recalculate financial data to prevent manipulation
                $base_price = floatval($item['weight']) * floatval($invoice_data['gold_price']);
                $labor_amount = $base_price * (floatval($item['labor_value']) / 100);
                $profit_amount = ($base_price + $labor_amount) * (floatval($item['profit_value']) / 100);
                $item_total = $base_price + $labor_amount + $profit_amount;
                
                // Add to totals
                $subtotal += $item_total;
                $total_labor_profit += $labor_amount + $profit_amount;

                // Build the sanitized item array, preserving necessary fields like 'purity'
                $sanitized_items[] = array(
                    'name' => sanitize_text_field($item['name']),
                    'purity' => sanitize_text_field($item['purity']),
                    'weight' => floatval($item['weight']),
                    'labor_value' => floatval($item['labor_value']),
                    'labor_type' => sanitize_text_field($item['labor_type'] ?? 'percent'),
                    'profit_value' => floatval($item['profit_value']),
                    'profit_type' => sanitize_text_field($item['profit_type'] ?? 'percent'),
                );
            }
        }
        
        // Replace the original items with the sanitized and recalculated ones
        $invoice_data['items'] = $sanitized_items;

        if (!empty($invoice_data['coins'])) {
            $sanitized_coins = [];
            foreach ($invoice_data['coins'] as $coin) {
                // Ensure quantity is at least 1 and correctly parsed
                $quantity = isset($coin['quantity']) ? intval($coin['quantity']) : 1;
                if ($quantity < 1) $quantity = 1;

                $unit_price = floatval($coin['unit_price']);
                $coin_total = $unit_price * $quantity;
                $subtotal += $coin_total;

                $sanitized_coins[] = [
                    'type' => sanitize_text_field($coin['type']),
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                ];
            }
            $invoice_data['coins'] = $sanitized_coins;
        }

        $tax_amount = $total_labor_profit * (floatval($invoice_data['tax_percent']) / 100);
        $final_price = $subtotal + $tax_amount;

        // Override client-side values with robust server-calculated values
        $invoice_data['finalPrice'] = $final_price;
        $invoice_data['tax_amount'] = $tax_amount;
        $invoice_data['subtotal'] = $subtotal;
        // === SERVER-SIDE RECALCULATION END ===
        
        $invoice_title = 'فاکتور ' . $shop_id . ' - ' . $invoice_data['customer_name'];
        
        $post_data = array(
            'post_title' => $invoice_title,
            'post_status' => 'publish',
            'post_type' => 'gcg_invoice',
        );
        
        if ($invoice_id_to_edit > 0) {
            $post_data['ID'] = $invoice_id_to_edit;
            $post_id = wp_update_post($post_data);
            $message = 'فاکتور با موفقیت بروزرسانی شد!';
        } else {
            $post_id = wp_insert_post($post_data);
            $message = 'فاکتور با موفقیت ذخیره شد!';
        }

        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => 'خطا در ذخیره/بروزرسانی فاکتور.'));
        }

        update_post_meta($post_id, 'gcg_shop_id', $shop_id);
        update_post_meta($post_id, 'gcg_invoice_data', $invoice_data);
        
        // Update customer stats if customer exists
        if (!empty($invoice_data['customer_name'])) {
            $this->update_customer_stats($shop_id, $invoice_data['customer_name'], $invoice_data['customer_phone'], $invoice_data['finalPrice']);
        }
        
        // Save new products automatically
        if (!empty($invoice_data['items'])) {
            $this->save_new_products_from_invoice($shop_id, $invoice_data['items']);
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'invoice_id' => $post_id
        ));
    }
    
    public function ajax_gcg_load_invoice() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        $invoice_id = intval($_POST['invoice_id']);
        $invoice = get_post($invoice_id);
        
        if ($invoice && $invoice->post_type === 'gcg_invoice') {
            $invoice_data = get_post_meta($invoice_id, 'gcg_invoice_data', true);
            wp_send_json_success(array('invoice_data' => $invoice_data));
        } else {
            wp_send_json_error(array('message' => 'فاکتور یافت نشد.'));
        }
    }
    
    public function ajax_gcg_delete_invoice() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        $invoice_id = intval($_POST['invoice_id']);
        $result = wp_delete_post($invoice_id, true);
        
        if ($result) {
            wp_send_json_success(array('message' => 'فاکتور با موفقیت حذف شد.'));
        } else {
            wp_send_json_error(array('message' => 'خطا در حذف فاکتور.'));
        }
    }
    
    public function ajax_gcg_fetch_invoices() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        $shop_id = sanitize_text_field($_POST['shop_id']);
        $invoices = get_posts(array(
            'post_type' => 'gcg_invoice',
            'meta_key' => 'gcg_shop_id',
            'meta_value' => $shop_id,
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        $html = '';
        if ($invoices) {
            foreach ($invoices as $invoice) {
                $invoice_data = get_post_meta($invoice->ID, 'gcg_invoice_data', true);
                $customer_name = $invoice_data['customer_name'] ?? 'نامشخص';
                $final_price = $invoice_data['finalPrice'] ?? 0;
                $date = $this->get_formatted_date($invoice->post_date);
                
                $html .= '<tr>';
                $html .= '<td>' . esc_html($invoice->ID) . '</td>';
                $html .= '<td>' . esc_html($customer_name) . '</td>';
                $html .= '<td>' . esc_html($date) . '</td>';
                $html .= '<td>' . esc_html(number_format($final_price)) . ' تومان</td>';
                $html .= '<td>';
                $html .= '<button class="gcg-btn-small view-invoice" data-id="' . esc_attr($invoice->ID) . '">مشاهده</button> ';
                $html .= '<button class="gcg-btn-small edit-invoice" data-id="' . esc_attr($invoice->ID) . '">ویرایش</button> ';
                $html .= '<button class="gcg-btn-small delete-invoice" data-id="' . esc_attr($invoice->ID) . '" style="background: #e74c3c;">حذف</button>';
                $html .= '</td>';
                $html .= '</tr>';
            }
        } else {
            $html = '<tr><td colspan="5">هیچ فاکتوری یافت نشد.</td></tr>';
        }
        
        wp_send_json_success(array('html' => $html));
    }
    public function ajax_gcg_save_product() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'gcg_products';
        
        $product_data = array(
            'shop_id' => sanitize_text_field($_POST['shop_id']),
            'name' => sanitize_text_field($_POST['name']),
            'category' => sanitize_text_field($_POST['category']),
            'purity' => sanitize_text_field($_POST['purity']),
            'weight' => floatval($_POST['weight']),
            'default_labor_percent' => floatval($_POST['default_labor_percent']),
            'default_profit_percent' => floatval($_POST['default_profit_percent']),
            'description' => sanitize_textarea_field($_POST['description'])
        );
        
        $format = array('%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s');
        
        if (isset($_POST['product_id']) && !empty($_POST['product_id'])) {
            $result = $wpdb->update(
                $table_name, 
                $product_data, 
                array('id' => intval($_POST['product_id'])),
                $format,
                array('%d')
            );
            $product_id = intval($_POST['product_id']);
            $message = 'محصول با موفقیت بروزرسانی شد.';
        } else {
            $result = $wpdb->insert($table_name, $product_data, $format);
            $product_id = $wpdb->insert_id;
            $message = 'محصول جدید با موفقیت اضافه شد.';
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => $message,
                'product_id' => $product_id
            ));
        } else {
            $debug_message = 'خطا در ذخیره محصول.';
            if (!empty($wpdb->last_error)) {
                $debug_message .= ' جزئیات خطا: ' . $wpdb->last_error;
            }
            wp_send_json_error(array('message' => $debug_message));
        }
    }
    
    public function ajax_gcg_get_products() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $shop_id = sanitize_text_field($_POST['shop_id']);
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        $table_name = $wpdb->prefix . 'gcg_products';
        
        $is_full_list = isset($_POST['full_list']) && $_POST['full_list'] == 'true';

        if ($is_full_list) {
            $sort_order = isset($_POST['sort_order']) ? sanitize_text_field($_POST['sort_order']) : 'created_at_desc';
            $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

            $query = "SELECT * FROM $table_name WHERE shop_id = %s";
            $params = array($shop_id);

            if (!empty($search_term)) {
                $query .= " AND (name LIKE %s OR category LIKE %s)";
                $params[] = '%' . $wpdb->esc_like($search_term) . '%';
                $params[] = '%' . $wpdb->esc_like($search_term) . '%';
            }

            // Securely build the ORDER BY clause
            $sort_param = isset($_POST['sort_order']) ? sanitize_text_field($_POST['sort_order']) : 'created_at_desc';
            $allowed_columns = ['name', 'category', 'purity', 'weight', 'default_labor_percent', 'default_profit_percent', 'created_at'];
            
            $sort_column = 'created_at';
            $sort_direction = 'DESC';

            $last_underscore_pos = strrpos($sort_param, '_');
            if ($last_underscore_pos !== false) {
                $column_candidate = substr($sort_param, 0, $last_underscore_pos);
                $direction_candidate = strtoupper(substr($sort_param, $last_underscore_pos + 1));

                if (in_array($column_candidate, $allowed_columns) && in_array($direction_candidate, ['ASC', 'DESC'])) {
                    $sort_column = $column_candidate;
                    $sort_direction = $direction_candidate;
                }
            }

            $order_by_clause = " ORDER BY `$sort_column` $sort_direction ";
            $query .= $order_by_clause;
            
            // Add a sensible limit to prevent performance issues on large datasets
            $query .= " LIMIT 200";

            $products = $wpdb->get_results($wpdb->prepare($query, $params));
            $html = '';
            if ($products) {
                foreach ($products as $product) {
                    $product_data_attr = htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8');
                    $html .= '<tr>';
                    $html .= '<td>' . esc_html($product->name) . '</td>';
                    $html .= '<td>' . esc_html($product->category) . '</td>';
                    $html .= '<td>' . esc_html($product->purity) . '</td>';
                    $html .= '<td>' . $product->weight . '</td>';
                    $html .= '<td>' . $product->default_labor_percent . '</td>';
                    $html .= '<td>' . $product->default_profit_percent . '</td>';
                    $html .= '<td>';
                    $html .= '<button class="gcg-btn-small edit-product" data-product=\'' . $product_data_attr . '\'>ویرایش</button> ';
                    $html .= '<button class="gcg-btn-small delete-product" data-id="' . $product->id . '" style="background: #e74c3c;">حذف</button>';
                    $html .= '</td>';
                    $html .= '</tr>';
                }
            } else {
                $html = '<tr><td colspan="7">هیچ محصولی با این مشخصات یافت نشد.</td></tr>';
            }
        } else if (!empty($search)) {
            $products = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE shop_id = %s AND (name LIKE %s OR category LIKE %s) ORDER BY name LIMIT 10",
                $shop_id, '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%'
            ));
            $html = '';
            if ($products) {
                foreach ($products as $product) {
                    // Prepare the detail string for display, ensuring values are escaped.
                    $product_details_display = sprintf(
                        'وزن: %s گرم - اجرت: %s%% - سود: %s%%',
                        esc_html($product->weight),
                        esc_html($product->default_labor_percent),
                        esc_html($product->default_profit_percent)
                    );

                    // Build the final HTML for the suggestion item using sprintf for safety and clarity.
                    $html .= sprintf(
                        '<div class="gcg-suggestion-item" data-product-id="%s" data-name="%s" data-weight="%s" data-labor="%s" data-profit="%s" data-purity="%s">' .
                        '<strong>%s</strong>' .
                        '<span>%s</span>' .
                        '</div>',
                        esc_attr($product->id),
                        esc_attr($product->name),
                        esc_attr($product->weight),
                        esc_attr($product->default_labor_percent),
                        esc_attr($product->default_profit_percent),
                        esc_attr($product->purity),
                        esc_html($product->name),
                        $product_details_display
                    );
                }
            } else {
                $html = '<div class="gcg-suggestion-item no-results">محصولی یافت نشد</div>';
            }
        }
        
        wp_send_json_success(array('html' => $html));
    }

    public function ajax_gcg_get_ticket_details() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $ticket_id = intval($_POST['ticket_id']);
        
        $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gcg_tickets WHERE id = %d", $ticket_id));
        $replies = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gcg_ticket_replies WHERE ticket_id = %d ORDER BY created_at ASC", $ticket_id));
        
        ob_start();
        ?>
        <div class="gcg-ticket-detail-header">
            <h3><?php echo esc_html($ticket->subject); ?></h3>
            <p>وضعیت: <?php echo $this->get_ticket_status_text($ticket->status); ?></p>
        </div>
        <div class="gcg-ticket-messages">
            <div class="gcg-ticket-message original">
                <p><?php echo nl2br(esc_html($ticket->message)); ?></p>
                <span class="meta">ارسال شده در <?php echo date_i18n('Y/m/d H:i', strtotime($ticket->created_at)); ?></span>
            </div>
            <?php foreach ($replies as $reply): ?>
                <div class="gcg-ticket-message <?php echo $reply->user_type; ?>">
                    <p><?php echo nl2br(esc_html($reply->message)); ?></p>
                    <span class="meta">پاسخ در <?php echo date_i18n('Y/m/d H:i', strtotime($reply->created_at)); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($ticket->status !== 'closed'): ?>
            <div class="gcg-ticket-reply-form">
                <textarea id="ticket-reply-message" rows="4" placeholder="پاسخ خود را بنویسید..."></textarea>
                <button id="submit-ticket-reply" class="gcg-btn gcg-btn-primary" data-ticket-id="<?php echo $ticket_id; ?>">ارسال پاسخ</button>
                <button id="close-ticket" class="gcg-btn gcg-btn-secondary" data-ticket-id="<?php echo $ticket_id; ?>">بستن تیکت</button>
            </div>
        <?php else: ?>
            <div class="gcg-ticket-closed-message">
                <p>این تیکت بسته شده است و امکان ارسال پاسخ وجود ندارد.</p>
            </div>
        <?php endif; ?>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }
    
    public function ajax_gcg_delete_product() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $product_id = intval($_POST['product_id']);
        $table_name = $wpdb->prefix . 'gcg_products';
        
        $result = $wpdb->delete($table_name, array('id' => $product_id));
        
        if ($result) {
            wp_send_json_success(array('message' => 'محصول با موفقیت حذف شد.'));
        } else {
            wp_send_json_error(array('message' => 'خطا در حذف محصول.'));
        }
    }
    public function ajax_gcg_save_customer() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'gcg_customers';
        
        $customer_data = array(
            'shop_id' => sanitize_text_field($_POST['shop_id']),
            'name' => sanitize_text_field($_POST['name']),
            'phone' => sanitize_text_field($_POST['phone']),
            'email' => sanitize_email($_POST['email']),
            'address' => sanitize_textarea_field($_POST['address']),
            'notes' => sanitize_textarea_field($_POST['notes'])
        );
        
        $format = array('%s', '%s', '%s', '%s', '%s', '%s');
        
        if (isset($_POST['customer_id']) && !empty($_POST['customer_id'])) {
            $result = $wpdb->update($table_name, $customer_data, array('id' => intval($_POST['customer_id'])), $format, array('%d'));
            $customer_id = intval($_POST['customer_id']);
            $message = 'مشتری با موفقیت بروزرسانی شد.';
        } else {
            $result = $wpdb->insert($table_name, $customer_data, $format);
            $customer_id = $wpdb->insert_id;
            $message = 'مشتری جدید با موفقیت اضافه شد.';
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => $message,
                'customer_id' => $customer_id
            ));
        } else {
            wp_send_json_error(array('message' => 'خطا در ذخیره مشتری'));
        }
    }
    
    public function ajax_gcg_get_customers() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $shop_id = sanitize_text_field($_POST['shop_id']);
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        $table_name = $wpdb->prefix . 'gcg_customers';
        
        $is_full_list = isset($_POST['full_list']) && $_POST['full_list'] == 'true';

        if ($is_full_list) {
            $sort_order = isset($_POST['sort_order']) ? sanitize_text_field($_POST['sort_order']) : 'created_at_desc';
            $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

            $query = "SELECT * FROM $table_name WHERE shop_id = %s";
            $params = array($shop_id);

            if (!empty($search_term)) {
                $query .= " AND (name LIKE %s OR phone LIKE %s)";
                $params[] = '%' . $wpdb->esc_like($search_term) . '%';
                $params[] = '%' . $wpdb->esc_like($search_term) . '%';
            }

            // Securely build the ORDER BY clause
            $sort_param = isset($_POST['sort_order']) ? sanitize_text_field($_POST['sort_order']) : 'created_at_desc';
            $allowed_columns = ['name', 'phone', 'total_purchases', 'total_amount', 'last_purchase_date', 'created_at'];
            
            $sort_column = 'created_at';
            $sort_direction = 'DESC';

            $last_underscore_pos = strrpos($sort_param, '_');
            if ($last_underscore_pos !== false) {
                $column_candidate = substr($sort_param, 0, $last_underscore_pos);
                $direction_candidate = strtoupper(substr($sort_param, $last_underscore_pos + 1));

                if (in_array($column_candidate, $allowed_columns) && in_array($direction_candidate, ['ASC', 'DESC'])) {
                    $sort_column = $column_candidate;
                    $sort_direction = $direction_candidate;
                }
            }
            
            $order_by_clause = " ORDER BY `$sort_column` $sort_direction ";
            $query .= $order_by_clause;

            $customers = $wpdb->get_results($wpdb->prepare($query, $params));

            $html = '';
            if ($customers) {
                foreach ($customers as $customer) {
                    $customer_data_attr = esc_attr(json_encode($customer));
                    $last_purchase = $customer->last_purchase_date ? $this->get_formatted_date($customer->last_purchase_date) : '-';
                    $created_at = $customer->created_at ? $this->get_formatted_date($customer->created_at) : '-';
                    $html .= '<tr>';
                    $html .= '<td>' . esc_html($customer->name) . '</td>';
                    $html .= '<td>' . esc_html($customer->phone) . '</td>';
                    $html .= '<td>' . esc_html($customer->total_purchases) . '</td>';
                    $html .= '<td>' . esc_html(number_format($customer->total_amount)) . '</td>';
                    $html .= '<td>' . esc_html($created_at) . '</td>';
                    $html .= '<td>' . esc_html($last_purchase) . '</td>';
                    $html .= '<td>';
                    $html .= '<button class="gcg-btn-small edit-customer" data-customer=\'' . $customer_data_attr . '\'>ویرایش</button> ';
                    $html .= '<button class="gcg-btn-small delete-customer" data-id="' . esc_attr($customer->id) . '" style="background: #e74c3c;">حذف</button>';
                    $html .= '</td>';
                    $html .= '</tr>';
                }
            } else {
                $html = '<tr><td colspan="7">هیچ مشتری با این مشخصات یافت نشد.</td></tr>';
            }
        } else if (!empty($search)) {
            $customers = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE shop_id = %s AND (name LIKE %s OR phone LIKE %s) ORDER BY name LIMIT 10",
                $shop_id, '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%'
            ));
            $html = '';
            if ($customers) {
                foreach ($customers as $customer) {
                    $html .= '<div class="gcg-suggestion-item" data-customer-id="' . esc_attr($customer->id) . '" data-name="' . esc_attr($customer->name) . '" data-phone="' . esc_attr($customer->phone) . '">';
                    $html .= '<strong>' . esc_html($customer->name) . '</strong>';
                    $html .= '<span>' . esc_html($customer->phone ?: 'بدون شماره') . '</span>';
                    $html .= '</div>';
                }
            } else {
                $html = '<div class="gcg-suggestion-item no-results">مشتری یافت نشد</div>';
            }
        }
        
        wp_send_json_success(array('html' => $html));
    }
    
    public function ajax_gcg_delete_customer() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $customer_id = intval($_POST['customer_id']);
        $table_name = $wpdb->prefix . 'gcg_customers';
        
        $result = $wpdb->delete($table_name, array('id' => $customer_id));
        
        if ($result) {
            wp_send_json_success(array('message' => 'مشتری با موفقیت حذف شد.'));
        } else {
            wp_send_json_error(array('message' => 'خطا در حذف مشتری.'));
        }
    }
    public function ajax_gcg_shop_login() {
        $this->prevent_caching();
        $creds = array(
            'user_login' => sanitize_user($_POST['username']),
            'user_password' => $_POST['password'],
            'remember' => false
        );

        $user = wp_signon($creds, false);

        if (is_wp_error($user)) {
            wp_send_json_error(array('message' => 'نام کاربری یا رمز عبور اشتباه است.'));
        }

        $shops = get_option('gcg_shops', array());
        $is_shop_user = false;
        foreach($shops as $shop) {
            if (isset($shop['user_id']) && $shop['user_id'] == $user->ID) {
                $is_shop_user = true;
                break;
            }
        }
        
        if (!$is_shop_user && !user_can($user, 'manage_options')) {
            wp_logout();
            wp_send_json_error(array('message' => 'حساب کاربری شما مجاز به دسترسی به این پنل نیست.'));
        }

        wp_send_json_success(array('message' => 'ورود موفقیت‌آمیز!'));
    }
    
    public function ajax_gcg_update_invoice_settings() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        $shop_id = sanitize_text_field($_POST['shop_id']);
        parse_str($_POST['settings'], $settings);

        // Get existing settings to merge with, ensuring we have a base.
        $current_settings = get_option("gcg_invoice_settings_{$shop_id}", array());
        
        $new_settings = array(
            'print_show_labor_column' => isset($settings['print_show_labor_column']),
            'print_show_profit_column' => isset($settings['print_show_profit_column']),
            'print_show_tax' => isset($settings['print_show_tax']),
            'default_tax' => isset($settings['default_tax']) ? floatval($settings['default_tax']) : 9.0
        );
        
        $updated_settings = array_merge($current_settings, $new_settings);
        
        update_option("gcg_invoice_settings_{$shop_id}", $updated_settings);
        wp_send_json_success(array('message' => 'تنظیمات فاکتور با موفقیت ذخیره شد.'));
    }
    public function ajax_gcg_get_invoice_settings_ajax() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        $shop_id = sanitize_text_field($_POST['shop_id']);
        $settings = $this->get_invoice_settings($shop_id);
        wp_send_json_success($settings);
    }
    
    public function ajax_gcg_update_shop_settings() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        $shop_id = sanitize_text_field($_POST['shop_id']);
        $shop_data = array(
            'name' => sanitize_text_field($_POST['shop_name']),
            'address' => sanitize_textarea_field($_POST['shop_address']),
            'phone' => sanitize_text_field($_POST['shop_phone']),
            'logo' => esc_url_raw($_POST['shop_logo']),
            'instagram' => sanitize_text_field($_POST['shop_instagram']),
            'telegram' => sanitize_text_field($_POST['shop_telegram'])
        );
        
        $shops = get_option('gcg_shops', array());
        foreach ($shops as &$shop) {
            if ($shop['id'] === $shop_id) {
                $shop = array_merge($shop, $shop_data);
                break;
            }
        }
        
        update_option('gcg_shops', $shops);
        wp_send_json_success(array('message' => 'تنظیمات فروشگاه با موفقیت بروزرسانی شد.'));
    }
    // Main Panel Shortcode
    public function main_panel_shortcode($atts) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        
        global $post;

        $shop_id = null;
        $required_user_id = null;

        if (is_object($post)) {
            $shop_id = get_post_meta($post->ID, 'gcg_shop_id', true);
            $required_user_id = get_post_meta($post->ID, 'gcg_shop_user_id', true);
        }
        
        $current_user_id = get_current_user_id();
        
        if (empty($shop_id) || empty($required_user_id)) {
            if (current_user_can('manage_options')) {
                return '<div class="notice notice-error"><p><strong>خطای ادمین:</strong> این صفحه به درستی پیکربندی نشده است. لطفاً مطمئن شوید که `shop_id` و `user_id` به درستی در meta-data این برگه تنظیم شده‌اند.</p></div>';
            }
            return '<p>خطا: شناسه فروشگاه معتبر نیست.</p>';
        }
        
        $is_authorized = ($current_user_id == $required_user_id || current_user_can('manage_options'));
        
        if (!$is_authorized) {
            $shops = get_option('gcg_shops', array());
            $shop_name = 'ناشناس';
            foreach ($shops as $shop) {
                if (isset($shop['user_id']) && $shop['user_id'] == $required_user_id) {
                    $shop_name = $shop['name'];
                    break;
                }
            }
            return $this->login_form_html($shop_name);
        }
        
        return $this->main_panel_html($shop_id);
    }
    private function main_panel_html($shop_id) {
        $shop_details = $this->get_shop_details($shop_id);
        $gold_price = $this->get_live_gold_price();
        $gold_price_toman = is_numeric($gold_price) ? round($gold_price / 10) : 0;
        $announcements = $this->get_shop_announcements($shop_id);
        $unread_announcements = count($announcements);
        
        ob_start();
        ?>
        <div id="gcg-main-panel">
            <!-- Mobile Header -->
            <div class="gcg-mobile-header">
                <button class="gcg-menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h2>پنل مدیریت طلا</h2>
                <div class="gcg-mobile-actions">
                    <span class="gcg-announcement-bell">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_announcements > 0): ?>
                            <span class="gcg-badge"><?php echo $unread_announcements; ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="gcg-sidebar">
                <div class="gcg-sidebar-header">
                    <h3>پنل مدیریت طلا</h3>
                    <div class="gcg-shop-info">
                        <strong><?php echo esc_html($shop_details['name']); ?></strong>
                    </div>
                </div>
                <nav class="gcg-sidebar-nav">
                    <ul>
                        <li class="gcg-nav-item active" data-section="dashboard">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>داشبورد</span>
                        </li>
                        <li class="gcg-nav-item" data-section="invoice">
                            <i class="fas fa-file-invoice"></i>
                            <span>فاکتور فروش</span>
                        </li>
                        <li class="gcg-nav-item" data-section="accounting">
                            <i class="fas fa-chart-line"></i>
                            <span>حسابداری و گزارشات</span>
                        </li>
                        <li class="gcg-nav-item" data-section="products">
                            <i class="fas fa-box"></i>
                            <span>مدیریت کالاها</span>
                        </li>
                        <li class="gcg-nav-item" data-section="customers">
                            <i class="fas fa-users"></i>
                            <span>مدیریت مشتریان</span>
                        </li>
                        <li class="gcg-nav-item" data-section="gold-purchase">
                            <i class="fas fa-shopping-cart"></i>
                            <span>خرید طلا</span>
                        </li>
                        <li class="gcg-nav-item" data-section="gold-exchange">
                            <i class="fas fa-exchange-alt"></i>
                            <span>تعویض طلا</span>
                        </li>
                        <li class="gcg-nav-item" data-section="tickets">
                            <i class="fas fa-headset"></i>
                            <span>پشتیبانی</span>
                        </li>
                    </ul>
                </nav>
                
                <div class="gcg-sidebar-footer">
                    <div class="gcg-user-actions">
                        <button class="gcg-logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            خروج از سیستم
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="gcg-main-content">
                <div class="gcg-content-header">
                    <div class="gcg-header-left">
                        <h2 id="gcg-section-title">داشبورد</h2>
                    </div>
                    <div class="gcg-header-right">
                        <div class="gcg-live-price">
                            <span>نرخ لحظه‌ای طلا: </span>
                            <strong id="live-gold-price"><?php echo number_format($gold_price_toman); ?></strong>
                            <span> تومان</span>
                            <button id="refresh-gold-price" class="gcg-btn-small">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="gcg-user-info">
                            <span>خوش آمدید، <?php echo esc_html($shop_details['name']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="gcg-content-body">
                    <div id="gcg-section-dashboard" class="gcg-section active">
                        <?php echo $this->dashboard_section_html($shop_id); ?>
                    </div>
                    <div id="gcg-section-invoice" class="gcg-section">
                        <?php echo $this->invoice_section_html($shop_id); ?>
                    </div>
                    <div id="gcg-section-accounting" class="gcg-section">
                        <?php echo $this->accounting_section_html($shop_id); ?>
                    </div>
                    <div id="gcg-section-products" class="gcg-section">
                        <?php echo $this->products_section_html($shop_id); ?>
                    </div>
                    <div id="gcg-section-customers" class="gcg-section">
                        <?php echo $this->customers_section_html($shop_id); ?>
                    </div>
                    <div id="gcg-section-gold-purchase" class="gcg-section">
                        <?php echo $this->gold_purchase_section_html($shop_id); ?>
                    </div>
                    <div id="gcg-section-gold-exchange" class="gcg-section">
                        <?php echo $this->gold_exchange_section_html($shop_id); ?>
                    </div>
                    <div id="gcg-section-tickets" class="gcg-section">
                        <?php echo $this->tickets_section_html($shop_id); ?>
                    </div>
                </div>
            </div>

            <!-- Announcements Modal -->
            <div id="gcg-announcements-modal" class="gcg-modal">
                <div class="gcg-modal-content">
                    <span class="gcg-close">&times;</span>
                    <h3>اعلامیه‌های مهم</h3>
                    <div class="gcg-announcements-list">
                        <?php if (empty($announcements)): ?>
                            <p>هیچ اعلامیه جدیدی وجود ندارد.</p>
                        <?php else: ?>
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="gcg-announcement-item">
                                    <h4><?php echo esc_html($announcement->post_title); ?></h4>
                                    <div class="gcg-announcement-content">
                                        <?php echo wp_kses_post($announcement->post_content); ?>
                                    </div>
                                    <div class="gcg-announcement-date">
                                        <?php echo date_i18n('j F Y', strtotime($announcement->post_date)); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    private function dashboard_section_html($shop_id) {
        $shop = $this->get_shop_details($shop_id);
        $stats = $this->get_shop_stats($shop_id);
        ?>
        <div class="gcg-dashboard">
            <div class="gcg-welcome-box">
                <h3>خوش آمدید به پنل <?php echo esc_html($shop['name']); ?></h3>
                <p>این پنل کامل مدیریت فروشگاه طلا و جواهر شما می‌باشد.</p>
            </div>

            <div class="gcg-stats-grid">
                <div class="gcg-stat-card">
                    <div class="gcg-stat-icon" style="background: #3498db;">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="gcg-stat-info">
                        <h3><?php echo $stats['total_invoices']; ?></h3>
                        <p>کل فاکتورها</p>
                    </div>
                </div>
                <div class="gcg-stat-card">
                    <div class="gcg-stat-icon" style="background: #27ae60;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="gcg-stat-info">
                        <h3><?php echo $stats['total_customers']; ?></h3>
                        <p>مشتریان</p>
                    </div>
                </div>
                <div class="gcg-stat-card">
                    <div class="gcg-stat-icon" style="background: #e74c3c;">
                        <i class="fas fa-weight-hanging"></i>
                    </div>
                    <div class="gcg-stat-info">
                        <h3><?php echo number_format($stats['total_sold_weight'], 3); ?></h3>
                        <p>گرم فروخته شده</p>
                    </div>
                </div>
                <div class="gcg-stat-card">
                    <div class="gcg-stat-icon" style="background: #f39c12;">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="gcg-stat-info">
                        <h3><?php echo number_format($stats['total_revenue']); ?></h3>
                        <p>درآمد کل (تومان)</p>
                    </div>
                </div>
            </div>

            <div class="gcg-recent-activities">
                <h3>فعالیت‌های اخیر</h3>
                <div class="gcg-activities-list">
                    <?php foreach ($stats['recent_activities'] as $activity): ?>
                    <div class="gcg-activity-item">
                        <div class="gcg-activity-icon">
                            <i class="fas <?php echo $activity['icon']; ?>"></i>
                        </div>
                        <div class="gcg-activity-content">
                            <p><?php echo $activity['text']; ?></p>
                            <span><?php echo $activity['time']; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }
    private function invoice_section_html($shop_id) {
        $gold_price = $this->get_live_gold_price();
        $gold_price_toman = is_numeric($gold_price) ? round($gold_price / 10) : 0;
        $invoice_settings = $this->get_invoice_settings($shop_id);
        ?>
        <div class="gcg-invoice-section">
            <div class="gcg-section-header">
                <h3>فاکتور فروش جدید</h3>
                <div class="gcg-header-actions">
                    <button class="gcg-btn gcg-btn-secondary" id="gcg-invoice-settings-btn">
                        <i class="fas fa-cog"></i>
                        تنظیمات فاکتور
                    </button>
                </div>
            </div>

            <!-- Invoice Settings Modal -->
            <div id="gcg-invoice-settings-modal" class="gcg-modal">
                <div class="gcg-modal-content">
                    <span class="gcg-close">&times;</span>
                    <h3>تنظیمات نمایش فاکتور</h3>
                    <form id="gcg-invoice-settings-form">
                        <p>این تنظیمات فقط بر روی **فاکتور چاپی** تاثیر می‌گذارند.</p>
                        <div class="gcg-form-group">
                            <label class="gcg-checkbox-label">
                                <input type="checkbox" name="print_show_labor_column" <?php checked($invoice_settings['print_show_labor_column'] ?? true, true); ?>>
                                <span>نمایش ستون اجرت</span>
                            </label>
                        </div>
                        <div class="gcg-form-group">
                            <label class="gcg-checkbox-label">
                                <input type="checkbox" name="print_show_profit_column" <?php checked($invoice_settings['print_show_profit_column'] ?? true, true); ?>>
                                <span>نمایش ستون سود</span>
                            </label>
                        </div>
                        <div class="gcg-form-group">
                            <label class="gcg-checkbox-label">
                                <input type="checkbox" name="print_show_tax" <?php checked($invoice_settings['print_show_tax'] ?? true, true); ?>>
                                <span>نمایش مالیات در فاکتور</span>
                            </label>
                        </div>
                        <hr>
                        <div class="gcg-form-group">
                            <label>مالیات پیش‌فرض (%):</label>
                            <input type="number" name="default_tax" value="<?php echo esc_attr($invoice_settings['default_tax']); ?>" step="0.1" min="0" max="100">
                        </div>
                        <div class="gcg-form-actions">
                            <button type="submit" class="gcg-btn gcg-btn-primary">ذخیره تنظیمات</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="gcg-invoice-form">
                <input type="hidden" id="invoice-id-to-edit" value="0">
                <div class="gcg-form-row">
                    <div class="gcg-form-group">
                        <label>جستجوی مشتری:</label>
                        <div class="search-wrapper">
                            <input type="text" id="customer-search" placeholder="نام یا شماره تماس مشتری">
                        </div>
                        <div id="customer-suggestions" class="gcg-suggestions"></div>
                    </div>
                    <div class="gcg-form-group">
                        <label>نام مشتری:</label>
                        <input type="text" id="customer-name" required>
                    </div>
                    <div class="gcg-form-group">
                        <label>شماره تماس:</label>
                        <input type="text" id="customer-phone">
                    </div>
                </div>

                <div class="gcg-form-row">
                    <div class="gcg-form-group">
                        <label>نرخ طلا (تومان):</label>
                        <input type="number" id="gold-price" value="<?php echo $gold_price_toman; ?>" step="1000">
                    </div>
                    <div class="gcg-form-group">
                        <label>مالیات (%):</label>
                        <input type="number" id="tax-percent" value="<?php echo $invoice_settings['default_tax']; ?>" step="0.1">
                    </div>
                </div>

                <div class="gcg-products-section">
                    <h4>کالاها</h4>
                    <div class="gcg-form-group">
                        <label>جستجوی کالا:</label>
                        <div class="search-wrapper">
                           <input type="text" id="product-search" placeholder="نام کالا را برای افزودن به فاکتور جستجو کنید...">
                        </div>
                        <div id="product-suggestions" class="gcg-suggestions"></div>
                    </div>
                    
                    <table class="gcg-items-table">
                        <thead>
                            <tr>
                                <th>کالا</th>
                                <th>عیار</th>
                                <th>وزن (گرم)</th>
                                <th>اجرت (% / تومان)</th>
                                <th>سود (% / تومان)</th>
                                <th>قیمت نهایی</th>
                                <th>حذف</th>
                            </tr>
                        </thead>
                        <tbody id="invoice-items">
                        </tbody>
                    </table>
                    <button type="button" id="add-item" class="gcg-btn">افزودن کالا</button>
                    <button type="button" id="add-coin-item" class="gcg-btn gcg-btn-secondary">افزودن سکه</button>
                </div>

                <div class="gcg-invoice-summary">
                    <div class="gcg-summary-row" id="base-price-row" style="<?php echo $invoice_settings['show_base_price'] ? '' : 'display: none;'; ?>">
                        <span>جمع کل:</span>
                        <span id="subtotal">0</span>
                    </div>
                    <div class="gcg-summary-row" id="labor-profit-row" style="<?php echo $invoice_settings['show_labor_profit'] ? '' : 'display: none;'; ?>">
                        <span>اجرت و سود:</span>
                        <span id="labor-profit-amount">0</span>
                    </div>
                    <div class="gcg-summary-row" id="tax-row" style="<?php echo $invoice_settings['show_tax'] ? '' : 'display: none;'; ?>">
                        <span>مالیات:</span>
                        <span id="tax-amount">0</span>
                    </div>
                    <div class="gcg-summary-row total">
                        <span>مبلغ نهایی:</span>
                        <span id="final-total">0</span>
                    </div>
                </div>

                <div class="gcg-form-actions">
                    <button type="button" id="print-invoice" class="gcg-btn gcg-btn-success">ذخیره و چاپ</button>
                    <button type="button" id="clear-invoice" class="gcg-btn gcg-btn-secondary">پاک کردن</button>
                </div>
            </div>

            <div class="gcg-recent-invoices">
                <h4>فاکتورهای اخیر</h4>
                <table class="gcg-data-table">
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>مشتری</th>
                            <th>تاریخ</th>
                            <th>مبلغ</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="recent-invoices-list">
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    private function accounting_section_html($shop_id) {
        ?>
        <div class="gcg-accounting-section">
            <div class="gcg-section-header">
                <h3>حسابداری</h3>
                <div class="gcg-period-selector">
                    <select id="report-period">
                        <option value="today">امروز</option>
                        <option value="week">هفته جاری</option>
                        <option value="month" selected>ماه جاری</option>
                        <option value="year">سال جاری</option>
                        <option value="all">نمایش همه</option>
                        <option value="custom">بازه سفارشی</option>
                    </select>
                    <div id="custom-date-range-picker" style="display:none; gap: 10px; align-items: center;">
                    <div class="gcg-date-picker-wrapper">
                        <input type="text" id="gcg-date-from-picker" placeholder="از تاریخ" readonly>
                        <span class="dashicons dashicons-calendar-alt gcg-datepicker-icon" data-target="gcg-date-from-picker"></span>
                    </div>
                    <div class="gcg-date-picker-wrapper">
                        <input type="text" id="gcg-date-to-picker" placeholder="تا تاریخ" readonly>
                        <span class="dashicons dashicons-calendar-alt gcg-datepicker-icon" data-target="gcg-date-to-picker"></span>
                    </div>
                        <button id="apply-date-filter" class="gcg-btn gcg-btn-primary">اعمال</button>
                    </div>
                </div>
            </div>

             <!-- Custom Jalali Calendar HTML Structure -->
            <div id="jalali-calendar" class="jalali-calendar">
                <div class="calendar-header">
                    <button class="prev-month">&lt;</button>
                    <div class="current-month"></div>
                    <button class="next-month">&gt;</button>
                </div>
                <div class="calendar-grid">
                    <div class="day-name">ش</div>
                    <div class="day-name">ی</div>
                    <div class="day-name">د</div>
                    <div class="day-name">س</div>
                    <div class="day-name">چ</div>
                    <div class="day-name">پ</div>
                    <div class="day-name">ج</div>
                </div>
                <div class="days-grid"></div>
                 <div class="calendar-footer">
                    <button class="go-today">امروز</button>
                </div>
            </div>

            <div class="gcg-stats-grid" id="accounting-stats-grid">
                <!-- Cards will be dynamically rendered here by JavaScript -->
            </div>

            <div class="gcg-invoice-search-container">
                <div class="gcg-form-group">
                    <label for="invoice-search-input">جستجوی فاکتور بر اساس شماره:</label>
                    <div class="search-wrapper">
                        <input type="text" id="invoice-search-input" placeholder="شماره فاکتور را وارد کنید...">
                    </div>
                    <div id="invoice-search-suggestions" class="gcg-suggestions"></div>
                </div>
            </div>

            <div class="gcg-detailed-reports">
                <h4>فاکتورهای فروش</h4>
                <table class="gcg-data-table" id="invoices-report-table">
                    <thead>
                        <tr>
                            <th><a href="#" class="sortable" data-sort="id">شماره فاکتور <i class="fas fa-sort"></i></a></th>
                            <th><a href="#" class="sortable" data-sort="date">تاریخ <i class="fas fa-sort"></i></a></th>
                            <th><a href="#" class="sortable" data-sort="customer">نام مشتری <i class="fas fa-sort"></i></a></th>
                            <th><a href="#" class="sortable" data-sort="profit">سود شما <i class="fas fa-sort"></i></a></th>
                            <th><a href="#" class="sortable" data-sort="total">مبلغ کل <i class="fas fa-sort"></i></a></th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="accounting-invoices-table">
                    </tbody>
                </table>
                <div class="gcg-load-more-container" style="text-align: center; margin-top: 20px;">
                    <button id="load-more-invoices" class="gcg-btn gcg-btn-secondary" style="display: none;">نمایش بیشتر</button>
                </div>
                <hr style="margin: 30px 0;">
                <h4>فاکتورهای خرید</h4>
                 <table class="gcg-data-table">
                    <thead>
                        <tr>
                            <th>شماره خرید</th>
                            <th>تاریخ</th>
                            <th>نام فروشنده</th>
                            <th>وزن کل (گرم)</th>
                            <th>مبلغ کل</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="accounting-purchases-table">
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    private function products_section_html($shop_id) {
        ?>
        <div class="gcg-products-section">
            <div class="gcg-section-header">
                <h3>مدیریت کالاها</h3>
                <button class="gcg-btn gcg-btn-primary" id="add-product-btn">افزودن کالای جدید</button>
            </div>

            <div class="gcg-search-and-filter-container">
                <div class="gcg-form-group">
                    <label for="product-search-input">جستجوی کالا:</label>
                    <input type="text" id="product-search-input" placeholder="نام کالا، دسته‌بندی و...">
                </div>
                </div>
            </div>

        <table class="gcg-data-table" id="products-table">
                <thead>
                    <tr>
                    <th><a href="#" class="sortable" data-sort="name">نام کالا <i class="fas fa-sort"></i></a></th>
                    <th><a href="#" class="sortable" data-sort="category">دسته‌بندی <i class="fas fa-sort"></i></a></th>
                    <th><a href="#" class="sortable" data-sort="purity">عیار <i class="fas fa-sort"></i></a></th>
                    <th><a href="#" class="sortable" data-sort="weight">وزن (گرم) <i class="fas fa-sort"></i></a></th>
                    <th><a href="#" class="sortable" data-sort="default_labor_percent">اجرت (%) <i class="fas fa-sort"></i></a></th>
                    <th><a href="#" class="sortable" data-sort="default_profit_percent">سود (%) <i class="fas fa-sort"></i></a></th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody id="products-table-body">
                    <!-- Products will be loaded here -->
                </tbody>
            </table>

            <div id="product-modal" class="gcg-modal">
                <div class="gcg-modal-content">
                    <span class="gcg-close">&times;</span>
                    <h3>افزودن/ویرایش کالا</h3>
                    <form id="product-form">
                        <input type="hidden" id="product-id">
                        <div class="gcg-form-group">
                            <label>نام کالا:</label>
                            <input type="text" id="product-name" required>
                        </div>
                        <div class="gcg-form-group">
                            <label>دسته‌بندی:</label>
                            <input type="text" id="product-category">
                        </div>
                        <div class="gcg-form-group">
                            <label>عیار:</label>
                            <input type="text" id="product-purity" placeholder="مثلا 18 یا 750">
                        </div>
                        <div class="gcg-form-group">
                            <label>وزن (گرم):</label>
                            <input type="number" id="product-weight" step="0.001" min="0">
                        </div>
                        <div class="gcg-form-group">
                            <label>اجرت پیش‌فرض (%):</label>
                            <input type="number" id="product-labor" value="10" step="0.1" min="0">
                        </div>
                        <div class="gcg-form-group">
                            <label>سود پیش‌فرض (%):</label>
                            <input type="number" id="product-profit" value="7" step="0.1" min="0">
                        </div>
                        <div class="gcg-form-group">
                            <label>توضیحات:</label>
                            <textarea id="product-description"></textarea>
                        </div>
                        <div class="gcg-form-actions">
                            <button type="submit" class="gcg-btn gcg-btn-primary">ذخیره</button>
                            <button type="button" class="gcg-btn gcg-btn-secondary" id="cancel-product">انصراف</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function customers_section_html($shop_id) {
        ?>
        <div class="gcg-customers-section">
            <div class="gcg-section-header">
                <h3>مدیریت مشتریان</h3>
                <button class="gcg-btn gcg-btn-primary" id="add-customer-btn">افزودن مشتری جدید</button>
            </div>

            <div class="gcg-search-and-filter-container">
                <div class="gcg-form-group">
                    <label for="customer-search-input">جستجوی مشتری:</label>
                    <input type="text" id="customer-search-input" placeholder="نام، شماره تماس و...">
                </div>
            </div>

        <table class="gcg-data-table" id="customers-table">
                <thead>
                    <tr>
                    <th><a href="#" class="sortable" data-sort="name">نام <i class="fas fa-sort"></i></a></th>
                    <th><a href="#" class="sortable" data-sort="phone">تماس <i class="fas fa-sort"></i></a></th>
                    <th><a href="#" class="sortable" data-sort="total_purchases">تعداد خرید <i class="fas fa-sort"></i></a></th>
                    <th><a href="#" class="sortable" data-sort="total_amount">مجموع خرید (تومان) <i class="fas fa-sort"></i></a></th>
                    <th><a href="#" class="sortable" data-sort="created_at">تاریخ ثبت <i class="fas fa-sort"></i></a></th>
                    <th><a href="#" class="sortable" data-sort="last_purchase_date">آخرین خرید <i class="fas fa-sort"></i></a></th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody id="customers-table-body">
                    <!-- Customers will be loaded here -->
                </tbody>
            </table>

            <div id="customer-modal" class="gcg-modal">
                <div class="gcg-modal-content">
                    <span class="gcg-close">&times;</span>
                    <h3>افزودن/ویرایش مشتری</h3>
                    <form id="customer-form">
                        <input type="hidden" id="customer-id">
                        <div class="gcg-form-group">
                            <label>نام کامل:</label>
                            <input type="text" id="customer-fullname" required>
                        </div>
                        <div class="gcg-form-group">
                            <label>شماره تماس:</label>
                            <input type="text" id="customer-phone">
                        </div>
                        <div class="gcg-form-group">
                            <label>ایمیل:</label>
                            <input type="email" id="customer-email">
                        </div>
                        <div class="gcg-form-group">
                            <label>آدرس:</label>
                            <textarea id="customer-address"></textarea>
                        </div>
                        <div class="gcg-form-group">
                            <label>توضیحات:</label>
                            <textarea id="customer-notes"></textarea>
                        </div>
                        <div class="gcg-form-actions">
                            <button type="submit" class="gcg-btn gcg-btn-primary">ذخیره</button>
                            <button type="button" class="gcg-btn gcg-btn-secondary" id="cancel-customer">انصراف</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    private function gold_purchase_section_html($shop_id) {
        ?>
        <div class="gcg-gold-purchase-section">
            <div class="gcg-section-header">
                <h3>خرید طلا از مشتری</h3>
                <button class="gcg-btn gcg-btn-primary" id="add-purchase-btn">ثبت خرید جدید</button>
            </div>

            <div class="gcg-purchase-form" id="purchase-form" style="display: none;">
                 <div class="gcg-form-row">
                    <div class="gcg-form-group">
                        <label>جستجوی فروشنده (مشتری):</label>
                        <div class="search-wrapper">
                            <input type="text" id="purchase-customer-search" placeholder="نام یا شماره تماس مشتری">
                        </div>
                        <div id="purchase-customer-suggestions" class="gcg-suggestions"></div>
                    </div>
                    <div class="gcg-form-group">
                        <label>نام فروشنده:</label>
                        <input type="text" id="purchase-customer-name" required>
                    </div>
                    <div class="gcg-form-group">
                        <label>شماره تماس:</label>
                        <input type="text" id="purchase-customer-phone">
                    </div>
                </div>
                
                <table class="gcg-items-table">
                    <thead>
                        <tr>
                            <th>شرح کالا</th>
                            <th>وزن (گرم)</th>
                            <th>عیار</th>
                            <th>قیمت خرید</th>
                            <th>حذف</th>
                        </tr>
                    </thead>
                    <tbody id="purchase-items"></tbody>
                </table>
                <button type="button" id="add-purchase-item" class="gcg-btn gcg-btn-secondary">افزودن کالای دیگر</button>

                <div class="gcg-invoice-summary" style="margin-top: 20px;">
                    <div class="gcg-summary-row total">
                        <span>مبلغ نهایی پرداختی:</span>
                        <span id="purchase-final-total">0</span>
                    </div>
                </div>

                <div class="gcg-form-actions">
                    <button type="button" id="save-purchase" class="gcg-btn gcg-btn-primary">ذخیره خرید</button>
                    <button type="button" id="cancel-purchase" class="gcg-btn gcg-btn-secondary">انصراف</button>
                </div>
            </div>

            <div class="gcg-purchases-list">
                <table class="gcg-data-table">
                    <thead>
                        <tr>
                            <th>تاریخ</th>
                            <th>فروشنده</th>
                            <th>وزن کل</th>
                            <th>مبلغ کل</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="purchases-table">
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    private function gold_exchange_section_html($shop_id) {
        ?>
        <div class="gcg-gold-exchange-section">
            <div class="gcg-section-header">
                <h3>سیستم تعویض طلا</h3>
                <p>در این بخش می‌توانید عملیات تعویض طلای مشتریان را مدیریت کنید.</p>
            </div>
            <div class="gcg-exchange-form" id="gold-exchange-form">
                <div class="gcg-form-row">
                    <div class="gcg-form-group">
                        <label>جستجوی مشتری:</label>
                        <div class="search-wrapper">
                           <input type="text" id="exchange-customer-search" placeholder="نام یا شماره تماس مشتری">
                        </div>
                        <div id="exchange-customer-suggestions" class="gcg-suggestions"></div>
                    </div>
                     <div class="gcg-form-group">
                        <label>نام مشتری:</label>
                        <input type="text" id="exchange-customer-name" required>
                    </div>
                    <div class="gcg-form-group">
                        <label>نرخ روز طلا (دستی):</label>
                        <input type="number" id="exchange-gold-price" placeholder="نرخ به تومان" required>
                    </div>
                </div>

                <div class="gcg-exchange-columns">
                    <div class="gcg-exchange-column" id="received-gold">
                        <h4>طلای دریافتی از مشتری</h4>
                        <div class="gcg-form-group">
                            <label>وزن (گرم):</label>
                            <input type="number" class="exchange-weight" step="0.001" min="0">
                        </div>
                        <div class="gcg-form-group">
                            <label>عیار:</label>
                            <input type="number" class="exchange-purity" value="750" placeholder="مثال: 750">
                        </div>
                        <hr>
                        <div class="column-total">
                            <strong>ارزش طلای دریافتی:</strong>
                            <span id="received-gold-value">0 تومان</span>
                        </div>
                    </div>

                    <div class="gcg-exchange-column" id="delivered-gold">
                        <h4>طلای تحویلی به مشتری</h4>
                        <table class="gcg-items-table">
                            <thead>
                                <tr>
                                    <th>کالا</th>
                                    <th>وزن</th>
                                    <th>اجرت(%)</th>
                                    <th>سود(%)</th>
                                    <th>قیمت</th>
                                    <th>حذف</th>
                                </tr>
                            </thead>
                            <tbody id="exchange-items"></tbody>
                        </table>
                        <button type="button" id="add-exchange-item" class="gcg-btn gcg-btn-secondary">افزودن کالا</button>
                        <hr>
                        <div class="column-total">
                            <strong>ارزش طلای تحویلی:</strong>
                            <span id="delivered-gold-value">0 تومان</span>
                        </div>
                    </div>
                </div>

                <div class="gcg-exchange-summary">
                    <h4>خلاصه تعویض</h4>
                    <div class="summary-row"><span>ارزش طلای دریافتی:</span> <span id="summary-received">0</span></div>
                    <div class="summary-row"><span>ارزش طلای تحویلی:</span> <span id="summary-delivered">0</span></div>
                    <div class="summary-row final-payment">
                        <strong>مبلغ نهایی:</strong>
                        <span id="summary-final-payment">0 تومان</span>
                        <small id="payment-direction">(پرداختی/دریافتی توسط مشتری)</small>
                    </div>
                </div>
                
                <div class="gcg-form-actions">
                    <button type="button" id="save-exchange-invoice" class="gcg-btn gcg-btn-success">ذخیره و چاپ فاکتور</button>
                    <button type="button" id="clear-exchange" class="gcg-btn gcg-btn-secondary">پاک کردن</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function coins_section_html($shop_id) {
        ?>
        <div class="gcg-coins-section">
            <div class="gcg-section-header">
                <h3>مدیریت سکه</h3>
                <div class="gcg-coin-actions">
                    <button class="gcg-btn gcg-btn-primary" id="add-coin-btn">ثبت معامله سکه</button>
                </div>
            </div>

            <div class="gcg-coin-stats">
                <div class="gcg-coin-stat">
                    <h4>موجودی سکه</h4>
                    <div class="gcg-coin-balance">
                        <span id="coin-balance">0</span>
                        <span>عدد</span>
                    </div>
                </div>
                <div class="gcg-coin-stat">
                    <h4>میانگین قیمت خرید</h4>
                    <div class="gcg-coin-avg-price">
                        <span id="coin-avg-price">0</span>
                        <span>تومان</span>
                    </div>
                </div>
            </div>

            <div id="coin-modal" class="gcg-modal">
                <div class="gcg-modal-content">
                    <span class="gcg-close">&times;</span>
                    <h3>ثبت معامله سکه</h3>
                    <form id="coin-form">
                        <div class="gcg-form-group">
                            <label>نوع معامله:</label>
                            <select id="coin-transaction-type" required>
                                <option value="buy">خرید</option>
                                <option value="sell">فروش</option>
                            </select>
                        </div>
                        <div class="gcg-form-group">
                            <label>نوع سکه:</label>
                            <select id="coin-type" required>
                                <option value="emami">امامی</option>
                                <option value="bahar">بهار آزادی</option>
                                <option value="nim">نیم سکه</option>
                                <option value="rob">ربع سکه</option>
                            </select>
                        </div>
                        <div class="gcg-form-group">
                            <label>تعداد:</label>
                            <input type="number" id="coin-quantity" min="1" required>
                        </div>
                        <div class="gcg-form-group">
                            <label>قیمت هر سکه (تومان):</label>
                            <input type="number" id="coin-price" required>
                        </div>
                        <div class="gcg-form-group">
                            <label>مشتری/فروشنده:</label>
                            <input type="text" id="coin-customer">
                        </div>
                        <div class="gcg-form-actions">
                            <button type="submit" class="gcg-btn gcg-btn-primary">ذخیره</button>
                            <button type="button" class="gcg-btn gcg-btn-secondary" id="cancel-coin">انصراف</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="gcg-coin-transactions">
                <table class="gcg-data-table">
                    <thead>
                        <tr>
                            <th>تاریخ</th>
                            <th>نوع</th>
                            <th>سکه</th>
                            <th>تعداد</th>
                            <th>قیمت واحد</th>
                            <th>جمع</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="coin-transactions-table">
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    private function tickets_section_html($shop_id) {
        ?>
        <div class="gcg-tickets-section">
            <div class="gcg-section-header">
                <h3>سیستم پشتیبانی</h3>
                <button class="gcg-btn gcg-btn-primary" id="new-ticket-btn">تیکت جدید</button>
            </div>

            <div class="gcg-tickets-container">
                <div class="gcg-tickets-list">
                    <div class="gcg-ticket-filters">
                        <select id="ticket-status-filter">
                            <option value="all">همه وضعیت‌ها</option>
                            <option value="open">باز</option>
                            <option value="pending">در حال بررسی</option>
                            <option value="resolved">حل شده</option>
                            <option value="closed">بسته</option>
                        </select>
                    </div>
                    
                    <div id="tickets-list" class="gcg-tickets-items">
                        <!-- Tickets will be loaded here -->
                    </div>
                </div>
                
                <div class="gcg-ticket-detail" id="ticket-detail">
                    <div class="gcg-ticket-detail-placeholder">
                        <p>لطفاً یک تیکت را برای مشاهده جزئیات انتخاب کنید.</p>
                    </div>
                </div>
            </div>

            <div id="ticket-modal" class="gcg-modal">
                <div class="gcg-modal-content">
                    <span class="gcg-close">&times;</span>
                    <h3>ارسال تیکت جدید</h3>
                    <form id="ticket-form">
                        <div class="gcg-form-group">
                            <label>موضوع:</label>
                            <input type="text" id="ticket-subject" required>
                        </div>
                        <div class="gcg-form-group">
                            <label>اولویت:</label>
                            <select id="ticket-priority">
                                <option value="low">کم</option>
                                <option value="medium" selected>متوسط</option>
                                <option value="high">بالا</option>
                                <option value="urgent">فوری</option>
                            </select>
                        </div>
                        <div class="gcg-form-group">
                            <label>پیام:</label>
                            <textarea id="ticket-message" rows="5" required></textarea>
                        </div>
                        <div class="gcg-form-actions">
                            <button type="submit" class="gcg-btn gcg-btn-primary">ارسال تیکت</button>
                            <button type="button" class="gcg-btn gcg-btn-secondary" id="cancel-ticket">انصراف</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function settings_section_html($shop_id) {
        $shop_details = $this->get_shop_details($shop_id);
        $invoice_settings = $this->get_invoice_settings($shop_id);
        ?>
        <div class="gcg-settings-section">
            <div class="gcg-section-header">
                <h3>تنظیمات پنل</h3>
            </div>

            <div class="gcg-settings-tabs">
                <div class="gcg-tab-buttons">
                    <button class="gcg-tab-btn active" data-tab="shop-settings">اطلاعات فروشگاه</button>
                    <button class="gcg-tab-btn" data-tab="invoice-settings">تنظیمات فاکتور</button>
                    <button class="gcg-tab-btn" data-tab="display-settings">تنظیمات نمایش</button>
                </div>

                <div id="shop-settings" class="gcg-tab-content active">
                    <form id="shop-settings-form">
                        <div class="gcg-form-group">
                            <label>نام فروشگاه:</label>
                            <input type="text" id="shop-name" value="<?php echo esc_attr($shop_details['name']); ?>" required>
                        </div>
                        <div class="gcg-form-group">
                            <label>آدرس:</label>
                            <textarea id="shop-address"><?php echo esc_textarea($shop_details['address']); ?></textarea>
                        </div>
                        <div class="gcg-form-group">
                            <label>شماره تماس:</label>
                            <input type="text" id="shop-phone" value="<?php echo esc_attr($shop_details['phone']); ?>">
                        </div>
                        <div class="gcg-form-group">
                            <label>آدرس لوگو:</label>
                            <input type="url" id="shop-logo" value="<?php echo esc_url($shop_details['logo']); ?>">
                        </div>
                        <div class="gcg-form-group">
                            <label>اینستاگرام:</label>
                            <input type="text" id="shop-instagram" value="<?php echo esc_attr($shop_details['instagram']); ?>" placeholder="@username">
                        </div>
                        <div class="gcg-form-group">
                            <label>تلگرام:</label>
                            <input type="text" id="shop-telegram" value="<?php echo esc_attr($shop_details['telegram']); ?>" placeholder="@username">
                        </div>
                        <div class="gcg-form-actions">
                            <button type="submit" class="gcg-btn gcg-btn-primary">ذخیره تغییرات</button>
                        </div>
                    </form>
                </div>

                <div id="invoice-settings" class="gcg-tab-content">
                    <form id="invoice-settings-form">
                        <div class="gcg-form-group">
                            <label class="gcg-checkbox-label">
                                <input type="checkbox" name="show_base_price" <?php checked($invoice_settings['show_base_price'], true); ?>>
                                <span>نمایش مجموع قیمت خام در فاکتور</span>
                            </label>
                        </div>
                        <div class="gcg-form-group">
                            <label class="gcg-checkbox-label">
                                <input type="checkbox" name="show_labor_profit" <?php checked($invoice_settings['show_labor_profit'], true); ?>>
                                <span>نمایش مجموع اجرت و سود در فاکتور</span>
                            </label>
                        </div>
                        <div class="gcg-form-group">
                            <label class="gcg-checkbox-label">
                                <input type="checkbox" name="show_tax" <?php checked($invoice_settings['show_tax'], true); ?>>
                                <span>نمایش مبلغ مالیات در فاکتور</span>
                            </label>
                        </div>
                        <div class="gcg-form-group">
                            <label>مالیات پیش‌فرض (%):</label>
                            <input type="number" name="default_tax" value="<?php echo esc_attr($invoice_settings['default_tax']); ?>" step="0.1" min="0" max="100">
                        </div>
                        <div class="gcg-form-actions">
                            <button type="submit" class="gcg-btn gcg-btn-primary">ذخیره تنظیمات</button>
                        </div>
                    </form>
                </div>

                <div id="display-settings" class="gcg-tab-content">
                    <div class="gcg-form-group">
                        <label>تم رنگ:</label>
                        <select id="color-theme">
                            <option value="default">پیش‌فرض (آبی)</option>
                            <option value="green">سبز</option>
                            <option value="purple">بنفش</option>
                            <option value="dark">تیره</option>
                        </select>
                    </div>
                    <div class="gcg-form-group">
                        <label>زبان:</label>
                        <select id="language">
                            <option value="fa">فارسی</option>
                            <option value="en">English</option>
                        </select>
                    </div>
                    <div class="gcg-form-actions">
                        <button type="button" id="save-display-settings" class="gcg-btn gcg-btn-primary">ذخیره تنظیمات</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    private function login_form_html($shop_name) {
        ob_start();
        ?>
        <div class="gcg-login-container">
            <div class="gcg-login-box">
                <div class="gcg-login-header">
                    <h2>ورود به پنل <?php echo esc_html($shop_name); ?></h2>
                </div>
                <form id="gcg-login-form" class="gcg-login-form">
                    <div class="gcg-form-group">
                        <label for="gcg-username">نام کاربری:</label>
                        <input type="text" id="gcg-username" name="username" required>
                    </div>
                    <div class="gcg-form-group">
                        <label for="gcg-password">رمز عبور:</label>
                        <input type="password" id="gcg-password" name="password" required>
                    </div>
                    <button type="submit" class="gcg-btn gcg-btn-primary gcg-btn-block">
                        <i class="fas fa-sign-in-alt"></i>
                        ورود به پنل
                    </button>
                    <div id="gcg-login-message" class="gcg-message"></div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function force_template() {
        global $post;
        
        if (is_page() && get_post_meta($post->ID, 'gcg_shop_user_id', true)) {
            echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head>';
            echo '<meta charset="' . get_bloginfo('charset') . '">';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<title>' . get_the_title() . ' | ' . get_bloginfo('name') . '</title>';
            wp_head();
            echo '</head><body>';
            echo '<div id="gcg-panel-wrapper">';
            echo do_shortcode($post->post_content);
            echo '</div>';
            wp_footer();
            echo '</body></html>';
            exit();
        }
    }
    public function output_inline_scripts() {
        global $post;
        
        if (is_page() && get_post_meta($post->ID, 'gcg_shop_user_id', true)) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                'use strict';

                const App = {
                    // Initialization
                    init: function() {
                        this.cacheDOMElements();
                        this.bindEvents();
                        this.loadInitialSection();
                        
                        // Auto-refresh gold price every minute
                        setInterval(this.refreshGoldPrice.bind(this), 60000);
                        this.JalaliCalendar.init();
                        this.Products.init();
                        this.Customers.init();
                    },

                    // Cache DOM elements to avoid repeated lookups
                    cacheDOMElements: function() {
                        this.dom = {
                            sidebar: $('.gcg-sidebar'),
                            navItems: $('.gcg-nav-item'),
                            sections: $('.gcg-section'),
                            sectionTitle: $('#gcg-section-title'),
                            logoutBtn: $('.gcg-logout-btn'),
                            modals: $('.gcg-modal'),
                            modalClosers: $('.gcg-close'),
                            menuToggle: $('.gcg-menu-toggle'),
                            announcementBell: $('.gcg-announcement-bell'),
                            announcementModal: $('#gcg-announcements-modal'),
                            goldPriceRefreshBtn: $('#refresh-gold-price'),
                            liveGoldPrice: $('#live-gold-price'),
                            invoiceForm: $('#gcg-invoice-form'),
                            loginForm: $('#gcg-login-form'),
                        };
                    },

                    // Bind all event listeners
                    bindEvents: function() {
                        this.dom.navItems.on('click', this.handleNavigation.bind(this));
                        this.dom.logoutBtn.on('click', this.handleLogout);
                        this.dom.modalClosers.on('click', (e) => $(e.target).closest('.gcg-modal').hide());
                        $(window).on('click', this.handleWindowClick.bind(this));
                        this.dom.menuToggle.on('click', () => this.dom.sidebar.toggleClass('active'));
                        this.dom.announcementBell.on('click', () => this.dom.announcementModal.show());
                        this.dom.goldPriceRefreshBtn.on('click', this.refreshGoldPrice.bind(this));
                        if (this.dom.loginForm.length) this.dom.loginForm.on('submit', this.handleLogin.bind(this));
                        
                        // Use event delegation for dynamically generated content
                        $(document).on('submit', '#gcg-invoice-settings-form, #shop-settings-form, #ticket-form, #coin-form', this.handleFormSubmissions.bind(this));
                        $(document).on('click', '#gcg-invoice-settings-btn, #add-item, #add-coin-item, #print-invoice, #clear-invoice, #add-product-btn, #add-customer-btn, #new-ticket-btn, #add-coin-btn, #add-purchase-btn, #cancel-purchase, #save-purchase, #calculate-exchange, #add-exchange-item, #add-purchase-item, #save-exchange-invoice, #clear-exchange, .edit-invoice, .delete-invoice, .view-invoice, .gcg-ticket-item, #submit-ticket-reply, #close-ticket, .delete-purchase, .delete-coin, .remove-item, .gcg-suggestion-item, .gcg-tab-btn, .edit-product, .delete-product, .edit-customer, .delete-customer', this.handleClicks.bind(this));
                        $(document).on('input', '#invoice-items input, #invoice-items select, #product-search, #customer-search, #gold-exchange-form input, #purchase-form input, #invoice-search-input', this.handleInputs.bind(this));
                        $(document).on('change', '#report-period', this.Accounting.handlePeriodChange.bind(this.Accounting));
                        $(document).on('click', '#apply-date-filter', function() { App.Accounting.load(); });
                        $(document).on('click', '#invoices-report-table .sortable', this.Accounting.handleSort.bind(this.Accounting));
                        $(document).on('click', '#products-table .sortable', this.Products.handleSort.bind(this.Products));
                        $(document).on('click', '#customers-table .sortable', this.Customers.handleSort.bind(this.Customers));
                        $(document).on('click', '#load-more-invoices', () => this.Accounting.load(true));
                        $(document).on('click', '.gcg-datepicker-icon', (e) => this.JalaliCalendar.show(e));

                    },

                    debounce: function(func, delay) {
                        let timeout;
                        return function(...args) {
                            const context = this;
                            clearTimeout(timeout);
                            timeout = setTimeout(() => func.apply(context, args), delay);
                        };
                    },

                    // Centralized AJAX handler
                    ajaxRequest: function(action, data, successCallback, errorCallback) {
                        console.log('--- AJAX Request ---');
                        console.log('Action:', action);
                        console.log('Data Sent:', data);

                        const ajaxData = {
                            action: action,
                            gcg_security_nonce: gcg_vars.gcg_security_nonce,
                            shop_id: gcg_vars.current_shop_id,
                            ...data
                        };

                        $.ajax({
                            url: gcg_vars.ajax_url,
                            type: 'POST',
                            data: ajaxData,
                            success: (response) => {
                                console.log('Response Received:', response);
                                if (response.success) {
                                    if(successCallback) successCallback(response.data);
                                } else {
                                    console.error('AJAX Error:', response.data.message);
                                    alert('خطا: ' + (response.data.message || 'درخواست با مشکل مواجه شد.'));
                                    if(errorCallback) errorCallback();
                                }
                            },
                            error: (jqXHR, textStatus, errorThrown) => {
                                console.error('--- AJAX Failure ---');
                                console.error('Status:', textStatus);
                                console.error('Error:', errorThrown);
                                console.error('Response Text:', jqXHR.responseText);
                                alert('خطای ارتباط با سرور.');
                                if(errorCallback) errorCallback();
                            }
                        });
                    },
                    
                    // --- Event Handlers ---

                    handleNavigation: function(e) {
                        const navItem = $(e.currentTarget);
                        const section = navItem.data('section');
                        
                        this.dom.navItems.removeClass('active');
                        navItem.addClass('active');
                        
                        this.dom.sections.removeClass('active');
                        $(`#gcg-section-${section}`).addClass('active');
                        
                        this.dom.sectionTitle.text(navItem.find('span').text());
                        
                        if (this.dom.sidebar.hasClass('active')) {
                            this.dom.sidebar.removeClass('active');
                        }
                        
                        this.loadSectionData(section);
                    },

                    handleLogout: function() {
                        if (confirm('آیا مطمئن هستید که می‌خواهید خارج شوید؟')) {
                            window.location.href = '<?php echo wp_logout_url(get_permalink()); ?>';
                        }
                    },
                    
                    handleWindowClick: function(e) {
                        if ($(e.target).hasClass('gcg-modal')) {
                            this.dom.modals.hide();
                        }
                    },

                    handleLogin: function(e) {
                        e.preventDefault();
                        const form = $(e.currentTarget);
                        const submitBtn = form.find('button[type="submit"]');
                        this.toggleButtonLoading(submitBtn, true, 'در حال ورود...');

                        this.ajaxRequest('gcg_shop_login', {
                            username: $('#gcg-username').val(),
                            password: $('#gcg-password').val(),
                        }, (data) => {
                            alert(data.message);
                            window.location.reload();
                        }, () => {
                             this.toggleButtonLoading(submitBtn, false, 'ورود به پنل');
                        });
                    },
                    
                    handleFormSubmissions: function(e) {
                        e.preventDefault();
                        const formId = $(e.currentTarget).attr('id');
                        let action, data;

                        switch(formId) {
                             case 'gcg-invoice-settings-form':
                                action = 'gcg_update_invoice_settings';
                                data = { settings: $('#gcg-invoice-settings-form').serialize() };
                                break;
                            case 'shop-settings-form':
                                action = 'gcg_update_shop_settings';
                                data = {
                                    shop_name: $('#shop-name').val(),
                                    shop_address: $('#shop-address').val(),
                                    shop_phone: $('#shop-phone').val(),
                                    shop_logo: $('#shop-logo').val(),
                                    shop_instagram: $('#shop-instagram').val(),
                                    shop_telegram: $('#shop-telegram').val(),
                                };
                                break;
                            case 'ticket-form':
                                action = 'gcg_save_ticket';
                                data = {
                                    subject: $('#ticket-subject').val(),
                                    message: $('#ticket-message').val(),
                                    priority: $('#ticket-priority').val()
                                };
                                break;
                            // Add other forms here
                        }

                        if(action) {
                             this.ajaxRequest(action, data, (response) => {
                                alert(response.message);
                                if(formId === 'ticket-form') {
                                    $('#ticket-modal').hide();
                                    this.loadSectionData('tickets');
                                }
                            });
                        }
                    },

                    handleClicks: function(e) {
                        e.preventDefault();
                        const target = $(e.currentTarget);
                        const id = target.attr('id');
                        const classList = target.attr('class');

                        if (id === 'gcg-invoice-settings-btn') this.Invoice.loadAndShowSettings();
                        else if (id === 'print-invoice') this.Invoice.print();
                        else if (id === 'clear-invoice') this.Invoice.clear();
                        else if (id === 'add-item') this.Invoice.addItemRow();
                        else if (id === 'add-coin-item') this.Invoice.addCoinRow();
                        else if (classList.includes('remove-item')) target.closest('tr').remove();
                        else if (classList.includes('gcg-suggestion-item')) {
                            if(target.data('customer-id')) {
                                $('#customer-name').val(target.data('name'));
                                $('#customer-phone').val(target.data('phone'));
                                $('#customer-suggestions').hide();
                            } else if (target.data('product-id')) {
                                this.Invoice.addItemRow(target.data());
                                $('#product-suggestions').hide();
                            } else if (target.data('invoice-id')) {
                                this.Accounting.displaySingleInvoice(target.data('invoice-id'));
                                $('#invoice-search-suggestions').hide();
                                $('#invoice-search-input').val(target.data('invoice-id'));
                            }
                        }
                        else if (classList.includes('delete-invoice')) this.Invoice.delete(target.data('id'));
                        else if (classList.includes('edit-invoice')) this.Invoice.edit(target.data('id'));
                        else if (classList.includes('view-invoice')) this.Invoice.view(target.data('id'));
                        else if (classList.includes('gcg-ticket-item')) {
                            this.Tickets.showDetails(target.data('ticket-id'));
                            $('.gcg-ticket-item').removeClass('active');
                            target.addClass('active');
                        }
                        else if (classList.includes('gcg-tab-btn')) {
                            const tab = target.data('tab');
                            $('.gcg-tab-btn').removeClass('active');
                            target.addClass('active');
                            $('.gcg-tab-content').removeClass('active');
                            $('#' + tab).addClass('active');
                        }
                        else if (id === 'submit-ticket-reply') this.Tickets.submitReply(target.data('ticket-id'));
                        else if (id === 'close-ticket') this.Tickets.close(target.data('ticket-id'));
                        else if (id === 'new-ticket-btn') $('#ticket-modal').show();
                        else if (id === 'add-product-btn') {
                            this.Products.resetForm();
                            $('#product-modal').show();
                        }
                        else if (id === 'add-customer-btn') {
                            this.Customers.resetForm();
                            $('#customer-modal').show();
                        }
                        else if (id === 'add-purchase-btn') $('#purchase-form').slideDown();
                        else if (id === 'cancel-purchase') $('#purchase-form').slideUp();
                        else if (id === 'save-purchase') this.GoldPurchase.save();
                        else if (classList.includes('delete-purchase')) this.GoldPurchase.delete(target.data('id'));
                        else if (id === 'add-coin-btn') $('#coin-modal').show();
                        else if (classList.includes('delete-coin')) this.Coins.delete(target.data('id'));
                        else if (id === 'calculate-exchange') this.GoldExchange.calculate();
                        else if (id === 'add-exchange-item') this.GoldExchange.addExchangeItemRow();
                        else if (id === 'save-exchange-invoice') this.GoldExchange.saveAndPrint();
                        else if (id === 'clear-exchange') this.GoldExchange.clear();
                        else if (id === 'add-purchase-item') this.GoldPurchase.addPurchaseItemRow();
                        else if (classList.includes('edit-product')) this.Products.edit(target.data('product'));
                        else if (classList.includes('delete-product')) this.Products.delete(target.data('id'));
                        else if (classList.includes('edit-customer')) this.Customers.edit(target.data('customer'));
                        else if (classList.includes('delete-customer')) this.Customers.delete(target.data('id'));
                    },

                    handleInputs: function(e) {
                        const target = $(e.currentTarget);
                        const id = target.attr('id');
                        
                        if (target.closest('#invoice-items').length) this.Invoice.calculate();
                        else if (target.closest('#gold-exchange-form').length) {
                            if (id === 'exchange-customer-search') this.GoldExchange.searchCustomers(target.val());
                            else this.GoldExchange.calculate();
                        }
                        else if (target.closest('#purchase-form').length) {
                            if (id === 'purchase-customer-search') this.GoldPurchase.searchCustomers(target.val());
                            else this.GoldPurchase.calculate();
                        }
                        else if (id === 'product-search') this.Invoice.searchProducts(target.val());
                        else if (id === 'customer-search') this.Invoice.searchCustomers(target.val());
                        else if (id === 'invoice-search-input') this.Accounting.searchInvoices(target.val());
                    },
                    
                    // --- Core Functionality ---

                    loadInitialSection: function() {
                        const activeNavItem = this.dom.navItems.filter('.active');
                        if (activeNavItem.length) {
                            const initialSection = activeNavItem.data('section');
                            this.dom.sectionTitle.text(activeNavItem.find('span').text());
                            this.loadSectionData(initialSection);
                        }
                    },

                    loadSectionData: function(section) {
                        switch(section) {
                            case 'dashboard': this.Dashboard.load(); break;
                            case 'invoice': 
                                this.Invoice.loadRecent(); 
                                // Also, fetch the latest settings to update the default tax field
                                App.ajaxRequest('gcg_get_invoice_settings_ajax', {}, (settings) => {
                                    $('#tax-percent').val(settings.default_tax);
                                });
                                break;
                            case 'accounting': 
                                this.Accounting.load(); 
                                break;
                            case 'products': this.Products.load(); break;
                            case 'customers': this.Customers.load(); break;
                            case 'gold-purchase': this.GoldPurchase.load(); break;
                            case 'coins': this.Coins.load(); break;
                            case 'tickets': this.Tickets.load(); break;
                        }
                    },
                    
                    refreshGoldPrice: function() {
                        this.toggleButtonLoading(this.dom.goldPriceRefreshBtn, true);
                        this.ajaxRequest('get_live_gold_price', {}, (data) => {
                            this.dom.liveGoldPrice.text(data.price.toLocaleString('fa-IR'));
                            $('#gold-price').val(data.price);
                            this.Invoice.calculate(); // Recalculate invoice if price changes
                            this.toggleButtonLoading(this.dom.goldPriceRefreshBtn, false);
                        }, () => {
                            this.toggleButtonLoading(this.dom.goldPriceRefreshBtn, false);
                        });
                    },
                    
                    toggleButtonLoading: function(button, isLoading, loadingText = '') {
                        if (isLoading) {
                            if (!button.data('original-html')) {
                                button.data('original-html', button.html());
                            }
                            button.prop('disabled', true).html(`<i class="fas fa-spinner fa-spin"></i> ${loadingText}`);
                        } else {
                            if (button.data('original-html')) {
                                button.prop('disabled', false).html(button.data('original-html'));
                            }
                        }
                    },

                    // --- Modules ---
                    Dashboard: {
                        load: function() {
                            App.ajaxRequest('gcg_get_dashboard_stats', {}, (data) => {
                                const stats = data.stats;
                                const statCards = $('.gcg-stats-grid .gcg-stat-info h3');
                                $(statCards[0]).text(stats.total_invoices);
                                $(statCards[1]).text(stats.total_customers);
                                $(statCards[2]).text(parseFloat(stats.total_sold_weight).toLocaleString('fa-IR', {minimumFractionDigits: 3, maximumFractionDigits: 3}));
                                $(statCards[3]).text(parseInt(stats.total_revenue).toLocaleString('fa-IR'));

                                const activitiesList = $('.gcg-activities-list');
                                activitiesList.empty();
                                if (stats.recent_activities && stats.recent_activities.length > 0) {
                                    stats.recent_activities.forEach(activity => {
                                        const activityHtml = `
                                            <div class="gcg-activity-item">
                                                <div class="gcg-activity-icon">
                                                    <i class="fas ${activity.icon}"></i>
                                                </div>
                                                <div class="gcg-activity-content">
                                                    <p>${activity.text}</p>
                                                    <span>${activity.time}</span>
                                                </div>
                                            </div>
                                        `;
                                        activitiesList.append(activityHtml);
                                    });
                                } else {
                                    activitiesList.html('<div class="gcg-activity-item"><p>فعالیت اخیری وجود ندارد.</p></div>');
                                }
                            });
                        }
                    },
                    Invoice: {
                        loadAndShowSettings: function() {
                            App.ajaxRequest('gcg_get_invoice_settings_ajax', {}, (settings) => {
                                // Populate the form with the latest settings
                                $('#gcg-invoice-settings-form input[name="print_show_labor_amount"]').prop('checked', settings.print_show_labor_amount);
                                $('#gcg-invoice-settings-form input[name="print_show_profit_amount"]').prop('checked', settings.print_show_profit_amount);
                                $('#gcg-invoice-settings-form input[name="print_show_tax_amount"]').prop('checked', settings.print_show_tax_amount);
                                $('#gcg-invoice-settings-form input[name="default_tax"]').val(settings.default_tax);
                                
                                // Now, show the modal
                                $('#gcg-invoice-settings-modal').show();
                            });
                        },
                        loadRecent: function() {
                            App.ajaxRequest('gcg_fetch_invoices', {}, (data) => {
                                $('#recent-invoices-list').html(data.html);
                            });
                        },
                        addItemRow: function(productData = {}) {
                            const itemName = productData.name || '';
                            const itemPurity = productData.purity || '18';
                            const itemWeight = productData.weight || '';
                            const itemLabor = productData.labor || 10;
                            const itemProfit = productData.profit || 7;

                            const itemRow = `
                                <tr class="invoice-item">
                                    <td><input type="text" class="item-name" placeholder="نام کالا" value="${itemName}"></td>
                                    <td><input type="text" class="item-purity" value="${itemPurity}"></td>
                                    <td><input type="number" class="item-weight" placeholder="0.000" step="0.001" min="0" value="${itemWeight}"></td>
                                    <td class="item-labor-cell">
                                        <input type="number" class="item-labor" value="${itemLabor}" step="0.1" min="0">
                                        <span>%</span>
                                        <div class="calculated-amount">0 تومان</div>
                                    </td>
                                    <td class="item-profit-cell">
                                        <input type="number" class="item-profit" value="${itemProfit}" step="0.1" min="0">
                                        <span>%</span>
                                        <div class="calculated-amount">0 تومان</div>
                                    </td>
                                    <td class="item-price">0</td>
                                    <td>
                                        <button type="button" class="gcg-btn-small remove-item" style="background: #e74c3c;"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            `;
                            $('#invoice-items').append(itemRow);
                            this.calculate();
                        },
                        addCoinRow: function() {
                             const coinRow = `
                                <tr class="invoice-item coin-item">
                                    <td><input type="text" class="item-name" placeholder="نام سکه (مثلا: سکه امامی)"></td>
                                    <td colspan="3"><input type="number" class="coin-unit-price" placeholder="قیمت واحد سکه"></td>
                                    <td class="item-price">0</td>
                                    <td>
                                        <button type="button" class="gcg-btn-small remove-item" style="background: #e74c3c;"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            `;
                            $('#invoice-items').append(coinRow);
                        },
                        calculate: function() {
                            let totalBasePrice = 0, totalLaborAndProfit = 0, finalTotal = 0;
                            const taxPercent = parseFloat($('#tax-percent').val()) || 0;

                            $('.invoice-item').each(function() {
                                const row = $(this);
                                if (row.hasClass('coin-item')) {
                                    const unitPrice = parseFloat(row.find('.coin-unit-price').val()) || 0;
                                    row.find('.item-price').text(unitPrice.toLocaleString('fa-IR'));
                                    finalTotal += unitPrice;
                                    totalBasePrice += unitPrice;
                                    return;
                                }

                                const weight = parseFloat(row.find('.item-weight').val()) || 0;
                                const goldPrice = parseFloat($('#gold-price').val()) || 0;
                                const basePrice = weight * goldPrice;

                                const laborPercent = parseFloat(row.find('.item-labor').val()) || 0;
                                const laborAmount = basePrice * (laborPercent / 100);
                                row.find('.item-labor-cell .calculated-amount').text(laborAmount.toLocaleString('fa-IR') + ' تومان');

                                const profitPercent = parseFloat(row.find('.item-profit').val()) || 0;
                                const profitAmount = (basePrice + laborAmount) * (profitPercent / 100);
                                row.find('.item-profit-cell .calculated-amount').text(profitAmount.toLocaleString('fa-IR') + ' تومان');
                                
                                const itemFinalPrice = basePrice + laborAmount + profitAmount;
                                row.find('.item-price').text(itemFinalPrice.toLocaleString('fa-IR'));
                                
                                totalBasePrice += basePrice;
                                totalLaborAndProfit += laborAmount + profitAmount;
                                finalTotal += itemFinalPrice;
                            });

                            const totalTax = totalLaborAndProfit * (taxPercent / 100);
                            const finalPriceWithTax = finalTotal + totalTax;
                            
                            $('#subtotal').text(totalBasePrice.toLocaleString('fa-IR'));
                            $('#labor-profit-amount').text(totalLaborAndProfit.toLocaleString('fa-IR'));
                            $('#tax-amount').text(totalTax.toLocaleString('fa-IR'));
                            $('#final-total').text(finalPriceWithTax.toLocaleString('fa-IR'));
                        },
                        save: function(callback) {
                            const invoiceData = this.gatherData();
                            if (!invoiceData) return;

                            App.ajaxRequest('gcg_save_invoice', {
                                invoice_data: JSON.stringify(invoiceData),
                                invoice_id_to_edit: $('#invoice-id-to-edit').val()
                            }, (data) => {
                                alert(data.message);
                                this.clear();
                                this.loadRecent();
                                App.Dashboard.load(); // Refresh dashboard stats
                                if (typeof callback === 'function') {
                                    callback(data.invoice_id);
                                }
                            });
                        },
                        print: function() {
                            this.save((invoiceId) => {
                                if (invoiceId) {
                                     App.ajaxRequest('gcg_get_invoice_print_html', { invoice_id: invoiceId }, (data) => {
                                        const printWindow = window.open('', '', 'height=600,width=800');
                                        printWindow.document.write(data.html);
                                        printWindow.document.close();
                                        printWindow.focus();
                                        printWindow.print();
                                    });
                                }
                            });
                        },
                        clear: function() {
                            if (confirm('آیا از پاک کردن فاکتور اطمینان دارید؟')) {
                                $('#customer-name, #customer-phone').val('');
                                $('#invoice-items').empty();
                                $('#invoice-id-to-edit').val('0');
                                $('#save-invoice').text('ذخیره فاکتور');
                                this.calculate();
                            }
                        },
                        edit: function(id) { 
                            App.ajaxRequest('gcg_load_invoice', { invoice_id: id }, (data) => {
                                const invoiceData = data.invoice_data;
                                $('#customer-name').val(invoiceData.customer_name);
                                $('#customer-phone').val(invoiceData.customer_phone);
                                $('#gold-price').val(invoiceData.gold_price);
                                $('#tax-percent').val(invoiceData.tax_percent);
                                $('#invoice-id-to-edit').val(id);
                                $('#save-invoice').text('بروزرسانی فاکتور');
                                $('#invoice-items').empty();
                                
                                if (invoiceData.items && invoiceData.items.length > 0) {
                                    invoiceData.items.forEach(item => {
                                        this.addItemRow({
                                            name: item.name,
                                            purity: item.purity,
                                            weight: item.weight,
                                            labor: item.labor_value,
                                            profit: item.profit_value
                                        });
                                    });
                                }
                                if (invoiceData.coins && invoiceData.coins.length > 0) {
                                    invoiceData.coins.forEach(coin => {
                                        const coinRow = `
                                            <tr class="invoice-item coin-item">
                                                <td><input type="text" class="item-name" value="${coin.type}"></td>
                                                <td colspan="3"><input type="number" class="coin-unit-price" value="${coin.unit_price}"></td>
                                                <td class="item-price">0</td>
                                                <td>
                                                    <button type="button" class="gcg-btn-small remove-item" style="background: #e74c3c;"><i class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        `;
                                        $('#invoice-items').append(coinRow);
                                    });
                                }

                                this.calculate();
                                $('html, body').animate({ scrollTop: $('.gcg-invoice-form').offset().top }, 500);
                            });
                        },
                        delete: function(id) {
                            if (confirm('آیا از حذف این فاکتور اطمینان دارید؟')) {
                                App.ajaxRequest('gcg_delete_invoice', { invoice_id: id }, (data) => {
                                    alert(data.message);
                                    this.loadRecent();
                                });
                            }
                        },
                        view: function(id) {
                             App.ajaxRequest('gcg_get_invoice_print_html', { invoice_id: id }, (data) => {
                                const viewWindow = window.open('', '', 'height=600,width=800');
                                viewWindow.document.write(data.html);
                                viewWindow.document.close();
                            });
                        },
                        searchProducts: function(term) {
                            if (term.length < 2) {
                                $('#product-suggestions').hide();
                                return;
                            }
                            App.ajaxRequest('gcg_get_products', { search: term }, (data) => {
                                $('#product-suggestions').html(data.html).show();
                            });
                        },
                        searchCustomers: function(term) {
                            if (term.length < 2) {
                                $('#customer-suggestions').hide();
                                return;
                            }
                            App.ajaxRequest('gcg_get_customers', { search: term }, (data) => {
                                $('#customer-suggestions').html(data.html).show();
                            });
                        },
                        gatherData: function() {
                            const customerName = $('#customer-name').val();
                            if (!customerName) {
                                alert('لطفا نام مشتری را وارد کنید');
                                return null;
                            }

                            const items = [];
                            const coins = [];
                             $('.invoice-item').each(function() {
                                const row = $(this);
                                if (row.hasClass('coin-item')) {
                                    const name = row.find('.item-name').val();
                                    const price = parseFloat(row.find('.coin-unit-price').val()) || 0;
                                    if (name && price > 0) {
                                        coins.push({
                                            type: name,
                                            quantity: 1, // Quantity is always 1 for manual entry
                                            unit_price: price
                                        });
                                    }
                                    return;
                                }
                                const name = row.find('.item-name').val();
                                const weight = parseFloat(row.find('.item-weight').val()) || 0;
                                if (name && weight > 0) {
                                    items.push({
                                        name: name,
                                        purity: row.find('.item-purity').val(),
                                        weight: weight,
                                        labor_value: parseFloat(row.find('.item-labor').val()) || 0,
                                        labor_type: row.find('.item-labor-type').val(),
                                        profit_value: parseFloat(row.find('.item-profit').val()) || 0,
                                        profit_type: row.find('.item-profit-type').val(),
                                        tax_percent: parseFloat(row.find('.item-tax').val()) || 0,
                                        display_options: {
                                            show_labor: row.find('.show-labor').is(':checked'),
                                            show_profit: row.find('.show-profit').is(':checked'),
                                            show_tax: row.find('.show-tax').is(':checked'),
                                            show_base_price: row.find('.show-base-price').is(':checked')
                                        }
                                    });
                                }
                            });

                            if (items.length === 0 && coins.length === 0) {
                                alert('لطفا حداقل یک کالا یا سکه به فاکتور اضافه کنید');
                                return null;
                            }
                            
                            const parseLocalFloat = (text) => parseFloat(text.replace(/[,\٬]/g, '')) || 0;

                            return {
                                customer_name: customerName,
                                customer_phone: $('#customer-phone').val(),
                                gold_price: parseFloat($('#gold-price').val()) || 0,
                                tax_percent: parseFloat($('#tax-percent').val()) || 0,
                                items: items,
                                coins: coins,
                                subtotal: parseLocalFloat($('#subtotal').text()),
                                tax_amount: parseLocalFloat($('#tax-amount').text()),
                                finalPrice: parseLocalFloat($('#final-total').text())
                            };
                        }
                    },
                    Accounting: {
                        currentSort: {
                            by: 'date',
                            order: 'DESC'
                        },
                        currentPage: 1,
                        
                        handlePeriodChange: function(e) {
                            const period = $(e.currentTarget).val();
                            if (period === 'custom') {
                                $('#custom-date-range-picker').css('display', 'flex');
                            } else {
                                $('#custom-date-range-picker').hide();
                                this.load(); // Reload data for the selected period
                            }
                        },

                        searchInvoices: function(term) {
                            if (term.length === 0) {
                                $('#invoice-search-suggestions').empty().hide();
                                // Restore the original list
                                this.load();
                                return;
                            }
                             if (term.length >= 1) {
                                App.ajaxRequest('gcg_search_invoices_by_id', { 
                                    search: term,
                                    shop_id: gcg_vars.current_shop_id // Add shop_id to the request
                                }, (data) => {
                                    $('#invoice-search-suggestions').html(data.html).show();
                                });
                            }
                        },
                        displaySingleInvoice: function(invoiceId) {
                            const ajaxData = {
                                period: 'all', // Search across all invoices
                                invoice_id: invoiceId // Special parameter to fetch only one
                            };
                             App.ajaxRequest('gcg_get_sales_report', ajaxData, (data) => {
                                const report = data.report;
                                $('#accounting-invoices-table').html(report.sales_invoices_html);
                                $('#load-more-invoices').hide(); // Hide load more when showing single invoice
                                $('.gcg-stats-grid').slideUp(); // Hide summary cards
                            });
                        },
                        load: function(loadMore = false) {
                            if (loadMore) {
                                this.currentPage++;
                            } else {
                                this.currentPage = 1;
                            }
                            
                            // If search is active, don't load all invoices
                            if ($('#invoice-search-input').val().length > 0) {
                                return;
                            }
                            $('.gcg-stats-grid').slideDown(); // Show summary cards
                    
                            const period = $('#report-period').val();
                            const ajaxData = {
                                period: period,
                                orderby: this.currentSort.by,
                                order: this.currentSort.order,
                                page: this.currentPage,
                                date_from: '',
                                date_to: ''
                            };

                            if (period === 'custom') {
                                const jalaliFrom = $('#gcg-date-from-picker').val();
                                const jalaliTo = $('#gcg-date-to-picker').val();

                                if (jalaliFrom) {
                                    const fromParts = jalaliFrom.split('/').map(Number);
                                    const gregorianFrom = App.JalaliCalendar.jalaliToGregorian(fromParts[0], fromParts[1], fromParts[2]);
                                    ajaxData.date_from = `${gregorianFrom[0]}-${String(gregorianFrom[1]).padStart(2, '0')}-${String(gregorianFrom[2]).padStart(2, '0')}`;
                                }
                                if (jalaliTo) {
                                    const toParts = jalaliTo.split('/').map(Number);
                                    const gregorianTo = App.JalaliCalendar.jalaliToGregorian(toParts[0], toParts[1], toParts[2]);
                                    ajaxData.date_to = `${gregorianTo[0]}-${String(gregorianTo[1]).padStart(2, '0')}-${String(gregorianTo[2]).padStart(2, '0')}`;
                                }
                            }
                    
                            App.ajaxRequest('gcg_get_sales_report', ajaxData, (data) => {
                                const report = data.report;
                                
                                // Only update summary cards on the first page load
                                if (!loadMore) {
                                    this.renderSummaryCards(report);
                                    $('#accounting-invoices-table').html(report.sales_invoices_html);
                                } else {
                                    $('#accounting-invoices-table').append(report.sales_invoices_html);
                                }
                                
                                // Always update purchase invoices and sort icons
                                $('#accounting-purchases-table').html(report.purchase_invoices_html);
                                this.updateSortIcons();
                                
                                // Handle "Load More" button visibility
                                if (report.has_more_pages) {
                                    $('#load-more-invoices').show();
                                } else {
                                    $('#load-more-invoices').hide();
                                }
                            });
                        },
                        handleSort: function(e) {
                            e.preventDefault();
                            const newSortBy = $(e.currentTarget).data('sort');
                            
                            if (this.currentSort.by === newSortBy) {
                                this.currentSort.order = (this.currentSort.order === 'ASC') ? 'DESC' : 'ASC';
                            } else {
                                this.currentSort.by = newSortBy;
                                this.currentSort.order = 'ASC';
                            }
                            
                            this.load(false); // Reset and load from page 1
                        },
                        updateSortIcons: function() {
                            $('#invoices-report-table .sortable i').removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');
                            const activeSorter = $(`#invoices-report-table .sortable[data-sort="${this.currentSort.by}"] i`);
                            if (this.currentSort.order === 'ASC') {
                                activeSorter.removeClass('fa-sort').addClass('fa-sort-up');
                            } else {
                                activeSorter.removeClass('fa-sort').addClass('fa-sort-down');
                            }
                        },
                        renderSummaryCards: function(report) {
                            const container = $('#accounting-stats-grid');
                            container.empty(); // Clear previous cards

                            const ss = report.sales_summary;
                            const ps = report.profit_summary;
                            const pes = report.purchase_exchange_stats;

                            const cards = [
                                {
                                    icon: 'fa-file-invoice',
                                    color: '#3498db',
                                    title: 'خلاصه فروش',
                                    content: `
                                        <p><strong>فاکتورها:</strong> ${ss.total_invoices.toLocaleString('fa-IR')}</p>
                                        <p><strong>گرم فروخته شده:</strong> ${parseFloat(ss.total_weight_sold).toLocaleString('fa-IR', {maximumFractionDigits: 3})}</p>
                                        <p><strong>فروش خالص:</strong> ${parseInt(ss.net_sales).toLocaleString('fa-IR')} تومان</p>
                                    `
                                },
                                {
                                    icon: 'fa-money-bill-wave',
                                    color: '#27ae60',
                                    title: 'سود شما',
                                    content: `<h3 style="font-size: 1.5em; margin: 0; color: #27ae60;">${parseInt(ps.total_profit).toLocaleString('fa-IR')} تومان</h3>`
                                },
                                {
                                    icon: 'fa-weight-hanging',
                                    color: '#f39c12',
                                    title: 'آمار خرید',
                                    content: `<p style="font-size: 1.2em; margin: 0;"><strong>${parseFloat(pes.purchased_weight).toLocaleString('fa-IR', {maximumFractionDigits: 3})}</strong> گرم</p>`
                                }
                            ];

                            cards.forEach(card => {
                                const cardHtml = `
                                    <div class="gcg-stat-card">
                                        <div class="gcg-stat-icon" style="background: ${card.color};">
                                            <i class="fas ${card.icon}"></i>
                                        </div>
                                        <div class="gcg-stat-info">
                                            <h4>${card.title}</h4>
                                            ${card.content}
                                        </div>
                                    </div>
                                `;
                                container.append(cardHtml);
                            });
                        }
                    },
                    Products: {
                        currentSort: {
                            by: 'created_at',
                            order: 'desc'
                        },
                        init: function() {
                            $('#product-form').on('submit', (e) => { e.preventDefault(); this.save(); });
                            $('#product-search-input').on('input', App.debounce(() => this.load(), 300));
                        },
                        load: function() { 
                            const search = $('#product-search-input').val();
                            const sort_order_string = `${this.currentSort.by}_${this.currentSort.order}`;
                            App.ajaxRequest('gcg_get_products', { 
                                full_list: true,
                                search: search,
                                sort_order: sort_order_string
                            }, (data) => {
                                $('#products-table-body').html(data.html);
                                this.updateSortIcons();
                            });
                        },
                        handleSort: function(e) {
                            e.preventDefault();
                            const newSortBy = $(e.currentTarget).data('sort');
                            
                            if (this.currentSort.by === newSortBy) {
                                this.currentSort.order = (this.currentSort.order === 'asc') ? 'desc' : 'asc';
                            } else {
                                this.currentSort.by = newSortBy;
                                this.currentSort.order = 'asc';
                            }
                            this.load();
                        },
                        updateSortIcons: function() {
                            $('#products-table .sortable i').removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');
                            const activeSorter = $(`#products-table .sortable[data-sort="${this.currentSort.by}"] i`);
                            if (this.currentSort.order === 'asc') {
                                activeSorter.removeClass('fa-sort').addClass('fa-sort-up');
                            } else {
                                activeSorter.removeClass('fa-sort').addClass('fa-sort-down');
                            }
                        },
                        save: function() {
                            const productData = {
                                product_id: $('#product-id').val(),
                                name: $('#product-name').val(),
                                category: $('#product-category').val(),
                                purity: $('#product-purity').val(),
                                weight: $('#product-weight').val(),
                                default_labor_percent: $('#product-labor').val(),
                                default_profit_percent: $('#product-profit').val(),
                                description: $('#product-description').val()
                            };
                            if (!productData.name) { alert('نام کالا الزامی است.'); return; }
                            
                            App.ajaxRequest('gcg_save_product', productData, (data) => {
                                alert(data.message);
                                $('#product-modal').hide();
                                this.load();
                            });
                        },
                        edit: function(product) {
                            $('#product-id').val(product.id);
                            $('#product-name').val(product.name);
                            $('#product-category').val(product.category);
                            $('#product-purity').val(product.purity);
                            $('#product-weight').val(product.weight);
                            $('#product-labor').val(product.default_labor_percent);
                            $('#product-profit').val(product.default_profit_percent);
                            $('#product-description').val(product.description);
                            $('#product-modal').show();
                        },
                        delete: function(productId) {
                            if (confirm('آیا از حذف این محصول اطمینان دارید؟')) {
                                App.ajaxRequest('gcg_delete_product', { product_id: productId }, (data) => {
                                    alert(data.message);
                                    this.load();
                                });
                            }
                        },
                        resetForm: function() {
                            $('#product-form')[0].reset();
                            $('#product-id').val('');
                        }
                    },
                    Customers: {
                        currentSort: {
                            by: 'created_at',
                            order: 'desc'
                        },
                        init: function() {
                            $('#customer-form').on('submit', (e) => { e.preventDefault(); this.save(); });
                            $('#customer-search-input').on('input', App.debounce(() => this.load(), 300));
                        },
                        load: function() {
                            const search = $('#customer-search-input').val();
                            const sort_order_string = `${this.currentSort.by}_${this.currentSort.order}`;
                            App.ajaxRequest('gcg_get_customers', { 
                                full_list: true,
                                search: search,
                                sort_order: sort_order_string
                            }, (data) => {
                                $('#customers-table-body').html(data.html);
                                this.updateSortIcons();
                            });
                        },
                        handleSort: function(e) {
                            e.preventDefault();
                            const newSortBy = $(e.currentTarget).data('sort');
                            
                            if (this.currentSort.by === newSortBy) {
                                this.currentSort.order = (this.currentSort.order === 'asc') ? 'desc' : 'asc';
                            } else {
                                this.currentSort.by = newSortBy;
                                this.currentSort.order = 'asc';
                            }
                            this.load();
                        },
                        updateSortIcons: function() {
                            $('#customers-table .sortable i').removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');
                            const activeSorter = $(`#customers-table .sortable[data-sort="${this.currentSort.by}"] i`);
                            if (this.currentSort.order === 'asc') {
                                activeSorter.removeClass('fa-sort').addClass('fa-sort-up');
                            } else {
                                activeSorter.removeClass('fa-sort').addClass('fa-sort-down');
                            }
                        },
                        save: function() {
                            const customerData = {
                                customer_id: $('#customer-id').val(),
                                name: $('#customer-fullname').val(),
                                phone: $('#customer-phone').val(),
                                email: $('#customer-email').val(),
                                address: $('#customer-address').val(),
                                notes: $('#customer-notes').val()
                            };
                            if (!customerData.name) { alert('نام مشتری الزامی است.'); return; }

                            App.ajaxRequest('gcg_save_customer', customerData, (data) => {
                                alert(data.message);
                                $('#customer-modal').hide();
                                this.load();
                            });
                        },
                        edit: function(customer) {
                            $('#customer-id').val(customer.id);
                            $('#customer-fullname').val(customer.name);
                            $('#customer-phone').val(customer.phone);
                            $('#customer-email').val(customer.email);
                            $('#customer-address').val(customer.address);
                            $('#customer-notes').val(customer.notes);
                            $('#customer-modal').show();
                        },
                        delete: function(customerId) {
                             if (confirm('آیا از حذف این مشتری اطمینان دارید؟')) {
                                App.ajaxRequest('gcg_delete_customer', { customer_id: customerId }, (data) => {
                                    alert(data.message);
                                    this.load();
                                });
                            }
                        },
                        resetForm: function() {
                            $('#customer-form')[0].reset();
                            $('#customer-id').val('');
                        }
                    },
                    GoldPurchase: {
                        init: function() {
                            this.addPurchaseItemRow(); // Start with one item row
                        },
                        load: function() {
                             App.ajaxRequest('gcg_get_gold_purchases', {}, (data) => {
                                $('#purchases-table').html(data.html);
                            });
                        },
                        addPurchaseItemRow: function() {
                            const itemRow = `
                                <tr class="purchase-item">
                                    <td><input type="text" class="item-name" placeholder="شرح کالا"></td>
                                    <td><input type="number" class="item-weight" step="0.001" min="0"></td>
                                    <td><input type="text" class="item-purity" value="18k"></td>
                                    <td><input type="number" class="item-price"></td>
                                    <td><button type="button" class="gcg-btn-small remove-item" style="background: #e74c3c;"><i class="fas fa-trash"></i></button></td>
                                </tr>`;
                            $('#purchase-items').append(itemRow);
                        },
                        calculate: function() {
                            let finalTotal = 0;
                            $('.purchase-item').each(function() {
                                finalTotal += parseFloat($(this).find('.item-price').val()) || 0;
                            });
                            $('#purchase-final-total').text(finalTotal.toLocaleString('fa-IR'));
                        },
                        save: function() {
                            const customerName = $('#purchase-customer-name').val();
                            if (!customerName) { alert('نام فروشنده الزامی است.'); return; }
                            
                            const items = [];
                            $('.purchase-item').each(function() {
                                const row = $(this);
                                const name = row.find('.item-name').val();
                                const weight = parseFloat(row.find('.item-weight').val()) || 0;
                                const price = parseFloat(row.find('.item-price').val()) || 0;
                                if (name && weight > 0 && price > 0) {
                                    items.push({
                                        name: name,
                                        weight: weight,
                                        purity: row.find('.item-purity').val(),
                                        price: price
                                    });
                                }
                            });

                            if (items.length === 0) { alert('حداقل یک کالا برای خرید اضافه کنید.'); return; }

                            const purchaseData = {
                                customer_name: customerName,
                                customer_phone: $('#purchase-customer-phone').val(),
                                items: JSON.stringify(items),
                                total_amount: parseFloat($('#purchase-final-total').text().replace(/,/g, '')) || 0
                            };

                            App.ajaxRequest('gcg_save_gold_purchase', purchaseData, (data) => {
                                alert(data.message);
                                $('#purchase-form').slideUp().find('input, textarea').val('');
                                $('#purchase-items').empty();
                                this.addPurchaseItemRow();
                                this.load();
                            });
                        },
                        delete: function(purchaseId) {
                            if (confirm('آیا از حذف این خرید اطمینان دارید؟')) {
                                App.ajaxRequest('gcg_delete_gold_purchase', { purchase_id: purchaseId }, (data) => {
                                    alert(data.message);
                                    this.load();
                                });
                            }
                        },
                        searchCustomers: function(term) {
                            if (term.length < 2) { $('#purchase-customer-suggestions').hide(); return; }
                            App.ajaxRequest('gcg_get_customers', { search: term }, (data) => {
                                $('#purchase-customer-suggestions').html(data.html).show();
                            });
                        }
                    },
                    GoldExchange: {
                        init: function() {
                           this.addExchangeItemRow();
                        },
                        addExchangeItemRow: function() {
                            const itemRow = `
                                <tr class="exchange-item">
                                    <td><input type="text" class="item-name" placeholder="نام کالا"></td>
                                    <td><input type="number" class="item-weight" step="0.001" min="0"></td>
                                    <td><input type="number" class="item-labor" value="10" step="0.1" min="0"></td>
                                    <td><input type="number" class="item-profit" value="7" step="0.1" min="0"></td>
                                    <td class="item-price">0</td>
                                    <td><button type="button" class="gcg-btn-small remove-item" style="background: #e74c3c;"><i class="fas fa-trash"></i></button></td>
                                </tr>`;
                            $('#exchange-items').append(itemRow);
                        },
                        calculate: function() {
                            const goldPrice = parseFloat($('#exchange-gold-price').val()) || 0;
                            if (goldPrice === 0) {
                                $('#received-gold-value, #delivered-gold-value, #summary-received, #summary-delivered, #summary-final-payment').text('0 تومان');
                                return;
                            }

                            const receivedWeight = parseFloat($('#received-gold .exchange-weight').val()) || 0;
                            const receivedPurity = parseFloat($('#received-gold .exchange-purity').val()) || 750;
                            const receivedValue = (receivedWeight * goldPrice * receivedPurity) / 750;
                            $('#received-gold-value').text(receivedValue.toLocaleString('fa-IR') + ' تومان');

                            let deliveredValue = 0;
                            $('#exchange-items tr').each(function() {
                                const row = $(this);
                                const weight = parseFloat(row.find('.item-weight').val()) || 0;
                                const labor = parseFloat(row.find('.item-labor').val()) || 0;
                                const profit = parseFloat(row.find('.item-profit').val()) || 0;

                                const basePrice = weight * goldPrice;
                                const laborAmount = basePrice * (labor / 100);
                                const profitAmount = (basePrice + laborAmount) * (profit / 100);
                                const itemTotal = basePrice + laborAmount + profitAmount;
                                
                                row.find('.item-price').text(itemTotal.toLocaleString('fa-IR'));
                                deliveredValue += itemTotal;
                            });
                            $('#delivered-gold-value').text(deliveredValue.toLocaleString('fa-IR') + ' تومان');

                            const finalPayment = deliveredValue - receivedValue;
                            $('#summary-received').text(receivedValue.toLocaleString('fa-IR') + ' تومان');
                            $('#summary-delivered').text(deliveredValue.toLocaleString('fa-IR') + ' تومان');
                            $('#summary-final-payment').text(Math.abs(finalPayment).toLocaleString('fa-IR') + ' تومان');
                            $('#payment-direction').text(finalPayment > 0 ? '(پرداختی مشتری)' : (finalPayment < 0 ? '(دریافتی مشتری)' : ''));
                        },
                        clear: function() {
                            if (confirm('آیا از پاک کردن فرم اطمینان دارید؟')) {
                                $('#gold-exchange-form')[0].reset();
                                $('#exchange-items').empty();
                                this.addExchangeItemRow();
                                this.calculate();
                            }
                        },
                        saveAndPrint: function() {
                            const data = this.gatherData();
                            if (!data) return;

                            App.ajaxRequest('gcg_save_exchange_invoice', { exchange_data: JSON.stringify(data) }, (response) => {
                                const printWindow = window.open('', '', 'height=600,width=800');
                                printWindow.document.write(response.html);
                                printWindow.document.close();
                                printWindow.focus();
                                printWindow.print();
                                this.clear();
                            });
                        },
                        gatherData: function() {
                            const customerName = $('#exchange-customer-name').val();
                            if (!customerName) { alert('نام مشتری الزامی است.'); return null; }

                            const deliveredItems = [];
                            $('#exchange-items tr').each(function() {
                                const row = $(this);
                                const name = row.find('.item-name').val();
                                const weight = parseFloat(row.find('.item-weight').val()) || 0;
                                if(name && weight > 0) {
                                    deliveredItems.push({
                                        name: name,
                                        weight: weight,
                                        labor: parseFloat(row.find('.item-labor').val()) || 0,
                                        profit: parseFloat(row.find('.item-profit').val()) || 0,
                                    });
                                }
                            });
                            
                            const parseLocalFloat = (text) => parseFloat(text.replace(/[,\٬]/g, '')) || 0;

                            return {
                                customer_name: customerName,
                                gold_price: parseFloat($('#exchange-gold-price').val()) || 0,
                                received_gold: {
                                    weight: parseFloat($('#received-gold .exchange-weight').val()) || 0,
                                    purity: parseFloat($('#received-gold .exchange-purity').val()) || 750,
                                    value: parseLocalFloat($('#received-gold-value').text())
                                },
                                delivered_gold: {
                                    items: deliveredItems,
                                    value: parseLocalFloat($('#delivered-gold-value').text())
                                },
                                final_payment: parseLocalFloat($('#summary-final-payment').text())
                            };
                        },
                        searchCustomers: function(term) {
                            if (term.length < 2) { $('#exchange-customer-suggestions').hide(); return; }
                            App.ajaxRequest('gcg_get_customers', { search: term }, (data) => {
                                $('#exchange-customer-suggestions').html(data.html).show();
                            });
                        }
                    },
                    Coins: {
                        load: function() {
                            App.ajaxRequest('gcg_get_coins', {}, (data) => {
                                $('#coin-transactions-table').html(data.html);
                            });
                        }
                    },
                    Tickets: {
                        load: function() {
                             App.ajaxRequest('gcg_get_tickets', {}, (data) => {
                                $('#tickets-list').html(data.html);
                            });
                        },
                        showDetails: function(ticketId) {
                            App.ajaxRequest('gcg_get_ticket_details', { ticket_id: ticketId }, (data) => {
                                $('#ticket-detail').html(data.html);
                            });
                        },
                        submitReply: function(ticketId) {
                             const message = $('#ticket-reply-message').val();
                             if (!message) { alert('پاسخ خالی است.'); return; }
                             App.ajaxRequest('gcg_save_ticket_reply', { ticket_id: ticketId, message: message }, (data) => {
                                 alert(data.message);
                                 this.showDetails(ticketId); // Refresh
                             });
                        },
                        close: function(ticketId) {
                            if (confirm('آیا از بستن این تیکت اطمینان دارید؟')) {
                                App.ajaxRequest('gcg_close_ticket', { ticket_id: ticketId }, (data) => {
                                    alert(data.message);
                                    this.load();
                                    $('#ticket-detail').html('<div class="gcg-ticket-detail-placeholder"><p>تیکت بسته شد.</p></div>');
                                });
                            }
                        }
                    },

                    JalaliCalendar: {
                        g_days_in_month: [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31],
                        j_days_in_month: [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29],
                        jalaliDate: { year: 0, month: 0, day: 0 },
                        activeInput: null,

                        init: function() {
                            this.cacheDOMElements();
                            this.bindEvents();
                            const today = new Date();
                            const jalaliToday = this.gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
                            this.jalaliDate = { year: jalaliToday[0], month: jalaliToday[1], day: jalaliToday[2] };
                        },

                        cacheDOMElements: function() {
                            this.dom = {
                                calendar: $('#jalali-calendar'),
                                currentMonth: $('#jalali-calendar .current-month'),
                                daysGrid: $('#jalali-calendar .days-grid'),
                                prevMonth: $('#jalali-calendar .prev-month'),
                                nextMonth: $('#jalali-calendar .next-month'),
                                goToday: $('#jalali-calendar .go-today')
                            };
                        },

                        bindEvents: function() {
                            this.dom.prevMonth.on('click', () => this.changeMonth(-1));
                            this.dom.nextMonth.on('click', () => this.changeMonth(1));
                            this.dom.goToday.on('click', () => this.goToToday());
                            this.dom.daysGrid.on('click', '.day', (e) => this.selectDate(e));
                            $(document).on('click', (e) => {
                                if (!this.dom.calendar.is(e.target) && this.dom.calendar.has(e.target).length === 0 && !$(e.target).hasClass('gcg-datepicker-icon')) {
                                    this.hide();
                                }
                            });
                        },

                        gregorianToJalali: function(gy, gm, gd) {
                            var g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
                            var jy = (gy <= 1600) ? 0 : 979;
                            gy -= (gy <= 1600) ? 621 : 1600;
                            var gy2 = (gm > 2) ? (gy + 1) : gy;
                            var days = (365 * gy) + (parseInt((gy2 + 3) / 4)) - (parseInt((gy2 + 99) / 100)) + (parseInt((gy2 + 399) / 400)) - 80 + gd + g_d_m[gm - 1];
                            jy += 33 * (parseInt(days / 12053));
                            days %= 12053;
                            jy += 4 * (parseInt(days / 1461));
                            days %= 1461;
                            jy += parseInt((days - 1) / 365);
                            if (days > 365) days = (days - 1) % 365;
                            var jm = (days < 186) ? 1 + parseInt(days / 31) : 7 + parseInt((days - 186) / 30);
                            var jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
                            return [jy, jm, jd];
                        },

                        jalaliToGregorian: function(jy, jm, jd) {
                            var gy = (jy <= 979) ? 621 : 1600;
                            jy -= (jy <= 979) ? 0 : 979;
                            var days = (365 * jy) + ((parseInt(jy / 33)) * 8) + (parseInt(((jy % 33) + 3) / 4)) + 78 + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
                            gy += 400 * (parseInt(days / 146097));
                            days %= 146097;
                            if (days > 36524) {
                                gy += 100 * (parseInt(--days / 36524));
                                days %= 36524;
                                if (days >= 365) days++;
                            }
                            gy += 4 * (parseInt(days / 1461));
                            days %= 1461;
                            gy += parseInt((days - 1) / 365);
                            if (days > 365) days = (days - 1) % 365;
                            var gd = days + 1;
                            var sal_a = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
                            var gm;
                            for (gm = 0; gm < 13; gm++) {
                                var v = sal_a[gm];
                                if (gd <= v) break;
                                gd -= v;
                            }
                            return [gy, gm, gd];
                        },

                        render: function() {
                            this.dom.daysGrid.empty();
                            const jYear = this.jalaliDate.year;
                            const jMonth = this.jalaliDate.month;

                            const monthNames = ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"];
                            this.dom.currentMonth.text(monthNames[jMonth - 1] + ' ' + jYear);

                            const firstDayGregorian = this.jalaliToGregorian(jYear, jMonth, 1);
                            const firstDay = new Date(firstDayGregorian[0], firstDayGregorian[1] - 1, firstDayGregorian[2]).getDay();
                            const startDay = (firstDay + 1) % 7;

                            let daysInMonth = this.j_days_in_month[jMonth - 1];
                            // Leap year check for Esfand (last month of Jalali calendar)
                            if (jMonth === 12 && (((jYear - 1395) % 4 === 3))) { 
                                daysInMonth = 30;
                            }

                            for (let i = 0; i < startDay; i++) {
                                this.dom.daysGrid.append('<div class="day other-month"></div>');
                            }

                            const today = new Date();
                            const jalaliToday = this.gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
                            
                            const selectedDateParts = this.activeInput && this.activeInput.val() ? this.activeInput.val().split('/') : null;

                            for (let i = 1; i <= daysInMonth; i++) {
                                let dayClass = 'day';
                                if (i === jalaliToday[2] && jMonth === jalaliToday[1] && jYear === jalaliToday[0]) {
                                    dayClass += ' today';
                                }
                                if (selectedDateParts && parseInt(selectedDateParts[0]) === jYear && parseInt(selectedDateParts[1]) === jMonth && parseInt(selectedDateParts[2]) === i) {
                                    dayClass += ' selected';
                                }
                                this.dom.daysGrid.append(`<div class="${dayClass}" data-day="${i}">${i}</div>`);
                            }
                        },

                        show: function(e) {
                            const icon = $(e.currentTarget);
                            this.activeInput = $(`#${icon.data('target')}`);
                            
                            // Re-parse date from input every time it's shown
                            const dateValue = this.activeInput.val();
                             if(dateValue) {
                                const dateParts = dateValue.split('/');
                                this.jalaliDate.year = parseInt(dateParts[0]);
                                this.jalaliDate.month = parseInt(dateParts[1]);
                                this.jalaliDate.day = parseInt(dateParts[2]);
                            } else {
                                const today = new Date();
                                const jalaliToday = this.gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
                                this.jalaliDate = { year: jalaliToday[0], month: jalaliToday[1], day: jalaliToday[2] };
                            }

                            const wrapper = icon.closest('.gcg-date-picker-wrapper');
                            const wrapperOffset = wrapper.offset();
                            const mainContent = $('.gcg-main-content');
                            
                            this.dom.calendar.css({
                                top: wrapperOffset.top + wrapper.outerHeight() - mainContent.offset().top,
                                right: mainContent.width() - (wrapperOffset.left - mainContent.offset().left) - wrapper.outerWidth()
                            });

                            this.render();
                            this.dom.calendar.show();
                        },

                        hide: function() {
                            this.dom.calendar.hide();
                            this.activeInput = null;
                        },

                        changeMonth: function(offset) {
                            this.jalaliDate.month += offset;
                            if (this.jalaliDate.month > 12) {
                                this.jalaliDate.month = 1;
                                this.jalaliDate.year++;
                            }
                            if (this.jalaliDate.month < 1) {
                                this.jalaliDate.month = 12;
                                this.jalaliDate.year--;
                            }
                            this.render();
                        },

                        selectDate: function(e) {
                            const day = $(e.currentTarget).data('day');
                            if (!day || $(e.currentTarget).hasClass('other-month')) return;

                            const selectedJalali = `${this.jalaliDate.year}/${String(this.jalaliDate.month).padStart(2, '0')}/${String(day).padStart(2, '0')}`;
                            if (this.activeInput) {
                                this.activeInput.val(selectedJalali);
                            }
                            this.hide();
                        },
                        
                        goToToday: function() {
                            const today = new Date();
                            const jalaliToday = this.gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
                            this.jalaliDate = { year: jalaliToday[0], month: jalaliToday[1], day: jalaliToday[2] };
                            this.render();
                        }
                    }
                };

                App.init();

            });
            </script>
            <?php
        }
    }
    // Helper methods
    private function get_live_gold_price($force_update = false) {
        $cache_key = 'live_gold_price';
        
        $cached_price = get_transient($cache_key);

        if (false !== $cached_price && is_numeric($cached_price) && !$force_update) {
            return $cached_price;
        }

        $url = 'http://mohaseb.tabangohar.com/';
        $response = wp_remote_get($url, array('timeout' => 30));

        if (is_wp_error($response)) {
            return 'خطا در دریافت نرخ. لطفا بعدا تلاش کنید.';
        }

        $html = wp_remote_retrieve_body($response);
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);

        $price_node = $xpath->query("//input[@id='GheymatMadenazar']")->item(0);

        if ($price_node) {
            $price = $price_node->getAttribute('value');
            $price = floatval($price);
            
            $gold_price_toman_correct = $price * 1000;
            $price_rial_for_calc = $gold_price_toman_correct * 10;
            
            set_transient($cache_key, $price_rial_for_calc, 1 * MINUTE_IN_SECONDS);
            return $price_rial_for_calc;
        }

        return 'نرخ طلا یافت نشد.';
    }
    
    private function get_shop_details($shop_id) {
        $shops = get_option('gcg_shops', array());
        foreach ($shops as $shop) {
            if ($shop['id'] === $shop_id) {
                return $shop;
            }
        }
        return array('name' => 'فروشگاه', 'address' => '', 'phone' => '', 'logo' => '', 'instagram' => '', 'telegram' => '');
    }
    
    private function get_shop_stats($shop_id) {
        global $wpdb;
        
        $invoices = get_posts(array(
            'post_type' => 'gcg_invoice',
            'meta_key' => 'gcg_shop_id',
            'meta_value' => $shop_id,
            'posts_per_page' => -1,
        ));
        
        $total_revenue = 0;
        $total_weight = 0;
        
        foreach ($invoices as $invoice) {
            $invoice_data = get_post_meta($invoice->ID, 'gcg_invoice_data', true);
            if ($invoice_data && isset($invoice_data['finalPrice'])) {
                $total_revenue += floatval($invoice_data['finalPrice']);
            }
            if ($invoice_data && isset($invoice_data['items'])) {
                foreach ($invoice_data['items'] as $item) {
                    $total_weight += floatval($item['weight']);
                }
            }
        }
        
        $customers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gcg_customers WHERE shop_id = %s",
            $shop_id
        ));
        
        return array(
            'total_invoices' => count($invoices),
            'total_customers' => $customers ?: 0,
            'total_sold_weight' => $total_weight,
            'total_revenue' => $total_revenue,
            'recent_activities' => $this->get_recent_activities($shop_id)
        );
    }
    
    private function get_recent_activities($shop_id) {
        global $wpdb;
        
        $activities = array();
        
        // Recent invoices
        $recent_invoices = get_posts(array(
            'post_type' => 'gcg_invoice',
            'meta_key' => 'gcg_shop_id',
            'meta_value' => $shop_id,
            'posts_per_page' => 3,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        foreach ($recent_invoices as $invoice) {
            $invoice_data = get_post_meta($invoice->ID, 'gcg_invoice_data', true);
            $customer_name = $invoice_data['customer_name'] ?? 'مشتری';
            
            $activities[] = array(
                'icon' => 'fa-file-invoice',
                'text' => 'فاکتور جدید برای ' . $customer_name,
                'time' => date_i18n('j F Y', strtotime($invoice->post_date))
            );
        }
        
        // Recent customers
        $recent_customers = $wpdb->get_results($wpdb->prepare(
            "SELECT name, created_at FROM {$wpdb->prefix}gcg_customers WHERE shop_id = %s ORDER BY created_at DESC LIMIT 2",
            $shop_id
        ));
        
        foreach ($recent_customers as $customer) {
            $activities[] = array(
                'icon' => 'fa-user-plus',
                'text' => 'مشتری جدید: ' . $customer->name,
                'time' => human_time_diff(strtotime($customer->created_at), current_time('timestamp')) . ' پیش'
            );
        }
        
        return $activities;
    }
    
    private function get_shop_announcements($shop_id) {
        return get_posts(array(
            'post_type' => 'gcg_announcement',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'gcg_target_shop_id',
                    'value' => 'all',
                    'compare' => '=',
                ),
                array(
                    'key' => 'gcg_target_shop_id',
                    'value' => $shop_id,
                    'compare' => '=',
                ),
            ),
            'orderby' => 'date',
            'order' => 'DESC',
        ));
    }
    
    private function get_invoice_settings($shop_id) {
        $defaults = array(
            'show_base_price' => true,
            'show_labor_profit' => true,
            'show_tax' => true,
            'print_show_labor_column' => true,
            'print_show_profit_column' => true,
            'print_show_tax' => true,
            'default_tax' => 9
        );
        
        $settings = get_option("gcg_invoice_settings_{$shop_id}", array());
        return array_merge($defaults, $settings);
    }
    
    private function save_new_products_from_invoice($shop_id, $items) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gcg_products';

        foreach ($items as $item) {
            if (empty($item['name'])) continue;

            $existing_product = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE shop_id = %s AND name = %s",
                $shop_id,
                $item['name']
            ));

            if (!$existing_product) {
                // Use the now-correct types passed from the invoice data
                $default_labor = get_option('gcg_default_labor_percent', 10);
                $default_profit = get_option('gcg_default_profit_percent', 7);
                $labor_percent = ($item['labor_type'] === 'percent') ? floatval($item['labor_value']) : $default_labor;
                $profit_percent = ($item['profit_type'] === 'percent') ? floatval($item['profit_value']) : $default_profit;
                
                $wpdb->insert(
                    $table_name,
                    array(
                        'shop_id' => $shop_id,
                        'name' => sanitize_text_field($item['name']),
                        'purity' => sanitize_text_field($item['purity'] ?? '18'),
                        'weight' => floatval($item['weight']),
                        'default_labor_percent' => $labor_percent,
                        'default_profit_percent' => $profit_percent
                    ),
                    array('%s', '%s', '%s', '%f', '%f', '%f')
                );
            }
        }
    }
    
    private function update_customer_stats($shop_id, $customer_name, $customer_phone, $amount, $is_purchase = false) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'gcg_customers';
        
        // Check if customer exists
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE shop_id = %s AND (name = %s OR (phone != '' AND phone = %s))",
            $shop_id, $customer_name, $customer_phone
        ));
        
        if ($customer) {
            // Update existing customer
            $update_data = array(
                'total_purchases' => $customer->total_purchases + ($is_purchase ? 0 : 1), // Don't count gold purchases as a "purchase" stat
                'total_amount' => $customer->total_amount + floatval($amount),
                'last_purchase_date' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            );
            $wpdb->update($table_name, $update_data, array('id' => $customer->id));
        } else {
            // Create new customer
            $wpdb->insert(
                $table_name,
                array(
                    'shop_id' => $shop_id,
                    'name' => $customer_name,
                    'phone' => $customer_phone,
                    'total_purchases' => 1,
                    'total_amount' => floatval($amount),
                    'last_purchase_date' => current_time('mysql'),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                )
            );
        }
    }
    
    private function get_total_invoices_count() {
        $invoices = get_posts(array(
            'post_type' => 'gcg_invoice',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        return count($invoices);
    }
    
    private function get_today_invoices_count() {
        $today = date('Y-m-d');
        $invoices = get_posts(array(
            'post_type' => 'gcg_invoice',
            'date_query' => array(
                array(
                    'year' => date('Y'),
                    'month' => date('m'),
                    'day' => date('d'),
                )
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        return count($invoices);
    }
    
    private function get_monthly_sales_total() {
        $month = date('Y-m');
        $invoices = get_posts(array(
            'post_type' => 'gcg_invoice',
            'date_query' => array(
                array(
                    'year' => date('Y'),
                    'month' => date('m'),
                )
            ),
            'posts_per_page' => -1
        ));
        
        $total = 0;
        foreach ($invoices as $invoice) {
            $invoice_data = get_post_meta($invoice->ID, 'gcg_invoice_data', true);
            if ($invoice_data && isset($invoice_data['finalPrice'])) {
                $total += floatval($invoice_data['finalPrice']);
            }
        }
        
        return $total;
    }
    
    private function get_active_shops_count() {
        $shops = get_option('gcg_shops', array());
        $active_count = 0;
        
        foreach ($shops as $shop) {
            $invoices = get_posts(array(
                'post_type' => 'gcg_invoice',
                'meta_key' => 'gcg_shop_id',
                'meta_value' => $shop['id'],
                'date_query' => array(
                    array(
                        'after' => '1 month ago'
                    )
                ),
                'posts_per_page' => 1
            ));
            
            if (count($invoices) > 0) {
                $active_count++;
            }
        }
        
        return $active_count;
    }
    
    private function get_shop_invoice_count($shop_id) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'gcg_invoice' AND p.post_status = 'publish'
             AND pm.meta_key = 'gcg_shop_id' AND pm.meta_value = %s",
            $shop_id
        ));
        return $count ? intval($count) : 0;
    }
    
    private function get_shop_total_sales($shop_id) {
        global $wpdb;
        $meta_values = $wpdb->get_col($wpdb->prepare(
            "SELECT pm.meta_value FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type = 'gcg_invoice' AND p.post_status = 'publish'
             AND pm.meta_key = 'gcg_invoice_data'
             AND p.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'gcg_shop_id' AND meta_value = %s)",
            $shop_id
        ));
        
        $total = 0;
        foreach ($meta_values as $meta_value) {
            $invoice_data = json_decode($meta_value, true);
            if ($invoice_data && isset($invoice_data['finalPrice'])) {
                $total += floatval($invoice_data['finalPrice']);
            }
        }
        
        return $total;
    }
    
    private function get_shop_last_activity($shop_id) {
        global $wpdb;
        $last_date = $wpdb->get_var($wpdb->prepare(
            "SELECT p.post_date FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'gcg_invoice' AND p.post_status = 'publish'
             AND pm.meta_key = 'gcg_shop_id' AND pm.meta_value = %s
             ORDER BY p.post_date DESC LIMIT 1",
            $shop_id
        ));

        if ($last_date) {
            return human_time_diff(strtotime($last_date), current_time('timestamp')) . ' پیش';
        }
        
        return 'بدون فعالیت';
    }
    
    private function get_today_sales() {
        $today = date('Y-m-d');
        $invoices = get_posts(array(
            'post_type' => 'gcg_invoice',
            'date_query' => array(
                array(
                    'year' => date('Y'),
                    'month' => date('m'),
                    'day' => date('d'),
                )
            ),
            'posts_per_page' => -1
        ));
        
        $total = 0;
        foreach ($invoices as $invoice) {
            $invoice_data = get_post_meta($invoice->ID, 'gcg_invoice_data', true);
            if ($invoice_data && isset($invoice_data['finalPrice'])) {
                $total += floatval($invoice_data['finalPrice']);
            }
        }
        
        return $total;
    }
    
    private function handle_update_shop(&$shops) {
        $shop_id = sanitize_text_field($_POST['shop_id']);
        foreach ($shops as &$shop) {
            if ($shop['id'] == $shop_id) {
                $shop['name'] = sanitize_text_field($_POST['shop_name']);
                $shop['address'] = sanitize_textarea_field($_POST['shop_address']);
                $shop['phone'] = sanitize_text_field($_POST['shop_phone']);
                $shop['logo'] = esc_url_raw($_POST['shop_logo']);
                $shop['instagram'] = sanitize_text_field($_POST['shop_instagram']);
                $shop['telegram'] = sanitize_text_field($_POST['shop_telegram']);
                break;
            }
        }
        update_option('gcg_shops', $shops);
        echo '<div class="notice notice-success is-dismissible"><p>فروشگاه با موفقیت ویرایش شد.</p></div>';
    }

    private function handle_reset_password() {
        if (!isset($_POST['gcg_reset_password_nonce']) || !wp_verify_nonce($_POST['gcg_reset_password_nonce'], 'gcg_reset_password_nonce')) {
            return;
        }
        $user_id = intval($_POST['user_id']);
        $new_password = $_POST['new_password'];

        if (empty($new_password)) {
            echo '<div class="notice notice-warning is-dismissible"><p>لطفا رمز عبور جدید را وارد کنید.</p></div>';
            return;
        }

        wp_set_password($new_password, $user_id);
        echo '<div class="notice notice-success is-dismissible"><p>رمز عبور کاربر با موفقیت بروزرسانی شد.</p></div>';
    }

    private function handle_create_new_user(&$shops) {
        if (!isset($_POST['gcg_create_user_nonce']) || !wp_verify_nonce($_POST['gcg_create_user_nonce'], 'gcg_create_user_nonce')) {
            return;
        }

        $shop_id = sanitize_text_field($_POST['shop_id']);
        $username = sanitize_user($_POST['new_username']);
        $password = $_POST['new_password'];

        if (username_exists($username)) {
            echo '<div class="notice notice-error is-dismissible"><p>این نام کاربری قبلا استفاده شده است. لطفا نام دیگری انتخاب کنید.</p></div>';
            return;
        }

        $user_id = wp_create_user($username, $password);

        if (is_wp_error($user_id)) {
            echo '<div class="notice notice-error is-dismissible"><p>خطا در ساخت کاربر جدید: ' . $user_id->get_error_message() . '</p></div>';
            return;
        }

        $user = new WP_User($user_id);
        $user->set_role('subscriber');

        // Assign the new user to the shop
        foreach ($shops as &$shop) {
            if ($shop['id'] == $shop_id) {
                $shop['user_id'] = $user_id;
                break;
            }
        }
        update_option('gcg_shops', $shops);

        // Update the corresponding page meta
        $page = get_posts(array(
            'post_type' => 'page',
            'meta_key' => 'gcg_shop_id',
            'meta_value' => $shop_id,
            'posts_per_page' => 1,
        ));
        if ($page) {
            update_post_meta($page[0]->ID, 'gcg_shop_user_id', $user_id);
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>کاربر جدید با موفقیت ساخته و به فروشگاه اختصاص داده شد.</p></div>';
    }
    
    private function handle_delete_shop(&$shops) {
        $shop_id = sanitize_text_field($_GET['shop_id']);
        $user_id = 0;
        foreach ($shops as $shop) {
            if ($shop['id'] === $shop_id) {
                $user_id = $shop['user_id'];
                break;
            }
        }
        
        $shops = array_filter($shops, function($shop) use ($shop_id) {
            return $shop['id'] !== $shop_id;
        });
        update_option('gcg_shops', array_values($shops));
        
        if ($user_id) {
            require_once(ABSPATH.'wp-admin/includes/user.php');
            wp_delete_user($user_id);
            $page = get_posts(array(
                'post_type' => 'page',
                'meta_key' => 'gcg_shop_id',
                'meta_value' => $shop_id,
                'posts_per_page' => 1,
            ));
            if ($page) {
                wp_delete_post($page[0]->ID, true);
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>فروشگاه و کاربر مربوطه با موفقیت حذف شدند.</p></div>';
    }
    
    private function render_edit_shop_form($shops) {
        $edit_shop_id = sanitize_text_field($_GET['shop_id']);
        $shop_to_edit = array();
        foreach ($shops as $shop) {
            if ($shop['id'] === $edit_shop_id) {
                $shop_to_edit = $shop;
                break;
            }
        }
        if ($shop_to_edit) {
            ?>
            <h2>ویرایش فروشگاه: <?php echo esc_html($shop_to_edit['name']); ?></h2>
            <form method="post">
                <input type="hidden" name="shop_id" value="<?php echo esc_attr($edit_shop_id); ?>">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="shop_name">نام فروشگاه:</label></th>
                            <td><input name="shop_name" type="text" id="shop_name" class="regular-text" required value="<?php echo esc_attr($shop_to_edit['name']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="shop_address">آدرس فروشگاه:</label></th>
                            <td><textarea name="shop_address" id="shop_address" class="large-text"><?php echo esc_textarea($shop_to_edit['address']); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="shop_phone">شماره تماس:</label></th>
                            <td><input name="shop_phone" type="text" id="shop_phone" class="regular-text" value="<?php echo esc_attr($shop_to_edit['phone']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="shop_logo">آدرس لوگو:</label></th>
                            <td><input name="shop_logo" type="url" id="shop_logo" class="regular-text" value="<?php echo esc_url($shop_to_edit['logo']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="shop_instagram">اینستاگرام:</label></th>
                            <td><input name="shop_instagram" type="text" id="shop_instagram" class="regular-text" placeholder="@username" value="<?php echo esc_attr($shop_to_edit['instagram'] ?? ''); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="shop_telegram">تلگرام:</label></th>
                            <td><input name="shop_telegram" type="text" id="shop_telegram" class="regular-text" placeholder="@username" value="<?php echo esc_attr($shop_to_edit['telegram'] ?? ''); ?>"></td>
                        </tr>
                    </tbody>
                </table>
                <p class="submit"><input type="submit" name="gcg_update_shop" class="button button-primary" value="بروزرسانی فروشگاه"></p>
            </form>

            <hr style="margin: 20px 0;">

            <h3>مدیریت کاربر</h3>
            <?php
            $user = get_user_by('id', $shop_to_edit['user_id']);
            if ($user) :
            ?>
                <p>کاربر مرتبط با این فروشگاه: <strong><?php echo esc_html($user->user_login); ?></strong></p>
                <form method="post">
                    <input type="hidden" name="shop_id" value="<?php echo esc_attr($edit_shop_id); ?>">
                    <input type="hidden" name="user_id" value="<?php echo esc_attr($shop_to_edit['user_id']); ?>">
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="new_password">رمز عبور جدید:</label></th>
                                <td><input name="new_password" type="password" id="new_password" class="regular-text" placeholder="برای تغییر، رمز جدید را وارد کنید"></td>
                            </tr>
                        </tbody>
                    </table>
                    <?php wp_nonce_field('gcg_reset_password_nonce', 'gcg_reset_password_nonce'); ?>
                    <p class="submit"><input type="submit" name="gcg_reset_password" class="button button-secondary" value="بازنشانی رمز عبور"></p>
                </form>
            <?php else : ?>
                <div class="notice notice-warning"><p>کاربر مرتبط با این فروشگاه حذف شده است. برای دسترسی مجدد، یک کاربر جدید ایجاد کنید.</p></div>
                <form method="post">
                    <input type="hidden" name="shop_id" value="<?php echo esc_attr($edit_shop_id); ?>">
                     <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="new_username">نام کاربری جدید:</label></th>
                                <td><input name="new_username" type="text" id="new_username" class="regular-text" required></td>
                            </tr>
                             <tr>
                                <th scope="row"><label for="new_password_create">رمز عبور جدید:</label></th>
                                <td><input name="new_password" type="password" id="new_password_create" class="regular-text" required></td>
                            </tr>
                        </tbody>
                    </table>
                    <?php wp_nonce_field('gcg_create_user_nonce', 'gcg_create_user_nonce'); ?>
                    <p class="submit"><input type="submit" name="gcg_create_new_user" class="button button-primary" value="ایجاد و تخصیص کاربر جدید"></p>
                </form>
            <?php endif; ?>
            <p><a href="<?php echo admin_url('admin.php?page=gcg_shops'); ?>">بازگشت به لیست</a></p>
            <?php
        }
    }
    
    // AJAX handlers for other sections (implemented)
    public function ajax_gcg_save_gold_purchase() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'gcg_gold_purchases';
        
        $items = json_decode(stripslashes($_POST['items']), true);
        if (empty($items)) {
            wp_send_json_error(array('message' => 'هیچ کالایی برای ثبت وجود ندارد.'));
        }
        
        $total_weight = 0;
        foreach($items as $item) {
            $total_weight += floatval($item['weight']);
        }
        
        $purchase_data = array(
            'shop_id' => sanitize_text_field($_POST['shop_id']),
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'weight' => $total_weight,
            'purchase_price' => floatval($_POST['total_amount']),
            'purchase_date' => current_time('mysql'),
            'description' => json_encode($items) // Store items as JSON in description
        );
        
        $result = $wpdb->insert($table_name, $purchase_data);
        
        if ($result !== false) {
            // Also add/update customer in main customer list
            $this->update_customer_stats($_POST['shop_id'], $_POST['customer_name'], $_POST['customer_phone'], 0, true); // is_purchase = true
            wp_send_json_success(array('message' => 'خرید طلا با موفقیت ثبت شد.'));
        } else {
            wp_send_json_error(array('message' => 'خطا در ثبت خرید طلا.'));
        }
    }
    
    public function ajax_gcg_get_gold_purchases() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $shop_id = sanitize_text_field($_POST['shop_id']);
        $table_name = $wpdb->prefix . 'gcg_gold_purchases';
        
        $purchases = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE shop_id = %s ORDER BY purchase_date DESC LIMIT 10",
            $shop_id
        ));
        
        $html = '';
        if ($purchases) {
            foreach ($purchases as $purchase) {
                $date = $this->get_formatted_date($purchase->purchase_date);
                $html .= '<tr>';
                $html .= '<td>' . $date . '</td>';
                $html .= '<td>' . esc_html($purchase->customer_name) . '</td>';
                $html .= '<td>' . $purchase->weight . '</td>';
                $html .= '<td>' . $purchase->purity . '</td>';
                $html .= '<td>' . number_format($purchase->purchase_price) . ' تومان</td>';
                $html .= '<td>';
                $html .= '<button class="gcg-btn-small delete-purchase" data-id="' . $purchase->id . '" style="background: #e74c3c;">حذف</button>';
                $html .= '</td>';
                $html .= '</tr>';
            }
        } else {
            $html = '<tr><td colspan="6">هیچ خریدی ثبت نشده است.</td></tr>';
        }
        
        wp_send_json_success(array('html' => $html));
    }
    
    public function ajax_gcg_delete_gold_purchase() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $purchase_id = intval($_POST['purchase_id']);
        $table_name = $wpdb->prefix . 'gcg_gold_purchases';
        
        $result = $wpdb->delete($table_name, array('id' => $purchase_id));
        
        if ($result) {
            wp_send_json_success(array('message' => 'خرید طلا با موفقیت حذف شد.'));
        } else {
            wp_send_json_error(array('message' => 'خطا در حذف خرید طلا.'));
        }
    }
    
    public function ajax_gcg_save_coin() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'gcg_coins';
        
        $coin_data = array(
            'shop_id' => sanitize_text_field($_POST['shop_id']),
            'transaction_type' => sanitize_text_field($_POST['transaction_type']),
            'coin_type' => sanitize_text_field($_POST['coin_type']),
            'weight' => floatval($_POST['weight']),
            'quantity' => intval($_POST['quantity']),
            'price_per_coin' => floatval($_POST['price_per_coin']),
            'total_amount' => floatval($_POST['price_per_coin']) * intval($_POST['quantity']),
            'transaction_date' => current_time('mysql'),
            'customer_name' => sanitize_text_field($_POST['customer_name'])
        );
        
        $format = array('%s', '%s', '%s', '%f', '%d', '%f', '%f', '%s', '%s');
        
        $result = $wpdb->insert($table_name, $coin_data, $format);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'معامله سکه با موفقیت ثبت شد.'));
        } else {
            wp_send_json_error(array('message' => 'خطا در ثبت معامله سکه.'));
        }
    }
    
    public function ajax_gcg_get_coins() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $shop_id = sanitize_text_field($_POST['shop_id']);
        $table_name = $wpdb->prefix . 'gcg_coins';
        
        $coins = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE shop_id = %s ORDER BY transaction_date DESC LIMIT 10",
            $shop_id
        ));
        
        $html = '';
        if ($coins) {
            foreach ($coins as $coin) {
                $date = $this->get_formatted_date($coin->transaction_date);
                $type_text = $coin->transaction_type === 'buy' ? 'خرید' : 'فروش';
                $html .= '<tr>';
                $html .= '<td>' . $date . '</td>';
                $html .= '<td>' . $type_text . '</td>';
                $html .= '<td>' . $coin->coin_type . '</td>';
                $html .= '<td>' . $coin->quantity . '</td>';
                $html .= '<td>' . number_format($coin->price_per_coin) . ' تومان</td>';
                $html .= '<td>' . number_format($coin->total_amount) . ' تومان</td>';
                $html .= '<td>';
                $html .= '<button class="gcg-btn-small delete-coin" data-id="' . $coin->id . '" style="background: #e74c3c;">حذف</button>';
                $html .= '</td>';
                $html .= '</tr>';
            }
        } else {
            $html = '<tr><td colspan="7">هیچ معامله‌ای ثبت نشده است.</td></tr>';
        }
        
        wp_send_json_success(array('html' => $html));
    }
    
    public function ajax_gcg_delete_coin() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $coin_id = intval($_POST['coin_id']);
        $table_name = $wpdb->prefix . 'gcg_coins';
        
        $result = $wpdb->delete($table_name, array('id' => $coin_id));
        
        if ($result) {
            wp_send_json_success(array('message' => 'معامله سکه با موفقیت حذف شد.'));
        } else {
            wp_send_json_error(array('message' => 'خطا در حذف معامله سکه.'));
        }
    }
    
    public function ajax_gcg_save_ticket() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'gcg_tickets';
        
        $ticket_data = array(
            'shop_id' => sanitize_text_field($_POST['shop_id']),
            'subject' => sanitize_text_field($_POST['subject']),
            'message' => sanitize_textarea_field($_POST['message']),
            'priority' => sanitize_text_field($_POST['priority']),
            'status' => 'open'
        );
        
        $format = array('%s', '%s', '%s', '%s', '%s');
        
        $result = $wpdb->insert($table_name, $ticket_data, $format);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'تیکت با موفقیت ارسال شد.'));
        } else {
            wp_send_json_error(array('message' => 'خطا در ارسال تیکت.'));
        }
    }
    
    public function ajax_gcg_get_tickets() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $shop_id = sanitize_text_field($_POST['shop_id']);
        $table_name = $wpdb->prefix . 'gcg_tickets';
        
        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE shop_id = %s ORDER BY created_at DESC",
            $shop_id
        ));
        
        $html = '';
        if ($tickets) {
            foreach ($tickets as $ticket) {
                $date = $this->get_formatted_date($ticket->created_at);
                $status_text = $this->get_ticket_status_text($ticket->status);
                $priority_text = $this->get_ticket_priority_text($ticket->priority);
                
                $html .= '<div class="gcg-ticket-item" data-ticket-id="' . $ticket->id . '">';
                $html .= '<div class="gcg-ticket-header">';
                $html .= '<h4>' . esc_html($ticket->subject) . '</h4>';
                $html .= '<span class="gcg-ticket-status ' . $ticket->status . '">' . $status_text . '</span>';
                $html .= '</div>';
                $html .= '<div class="gcg-ticket-meta">';
                $html .= '<span>اولویت: ' . $priority_text . '</span>';
                $html .= '<span>تاریخ: ' . $date . '</span>';
                $html .= '</div>';
                $html .= '</div>';
            }
        } else {
            $html = '<div class="gcg-ticket-item">هیچ تیکتی یافت نشد.</div>';
        }
        
        wp_send_json_success(array('html' => $html));
    }
    
    public function ajax_gcg_save_ticket_reply() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $ticket_id = intval($_POST['ticket_id']);

        // Server-side check for ticket status
        $ticket_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}gcg_tickets WHERE id = %d",
            $ticket_id
        ));

        if ($ticket_status === 'closed') {
            wp_send_json_error(array('message' => 'امکان ارسال پاسخ برای تیکت بسته شده وجود ندارد.'));
            return;
        }
        
        $table_name = $wpdb->prefix . 'gcg_ticket_replies';
        
        $reply_data = array(
            'ticket_id' => $ticket_id,
            'user_type' => 'shop',
            'message' => sanitize_textarea_field($_POST['message'])
        );
        
        $format = array('%d', '%s', '%s');
        
        $result = $wpdb->insert($table_name, $reply_data, $format);
        
        if ($result !== false) {
            // Optionally, update ticket status to 'pending' if it was answered by the shop
            $wpdb->update(
                $wpdb->prefix . 'gcg_tickets',
                ['status' => 'pending', 'updated_at' => current_time('mysql')],
                ['id' => $ticket_id]
            );
            wp_send_json_success(array('message' => 'پاسخ با موفقیت ارسال شد.'));
        } else {
            wp_send_json_error(array('message' => 'خطا در ارسال پاسخ.'));
        }
    }
    
    public function ajax_gcg_get_dashboard_stats() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        $shop_id = sanitize_text_field($_POST['shop_id']);
        $stats = $this->get_shop_stats($shop_id);
        
        wp_send_json_success(array('stats' => $stats));
    }
    
    public function ajax_gcg_search_invoices_by_id() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        global $wpdb;

        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $shop_id = sanitize_text_field($_POST['shop_id']);

        if (empty($search_term)) {
            wp_send_json_success(array('html' => ''));
            return;
        }

        // Direct SQL query for better control and performance
        $query = $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'gcg_invoice'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'gcg_shop_id'
            AND pm.meta_value = %s
            AND CAST(p.ID AS CHAR) LIKE %s
            ORDER BY p.ID DESC
            LIMIT 10",
            $shop_id,
            $wpdb->esc_like($search_term) . '%'
        );

        $invoice_ids = $wpdb->get_col($query);

        $html = '';
        if (!empty($invoice_ids)) {
            foreach ($invoice_ids as $invoice_id) {
                $html .= '<div class="gcg-suggestion-item" data-invoice-id="' . esc_attr($invoice_id) . '">';
                $html .= '<strong>شماره فاکتور: ' . esc_html($invoice_id) . '</strong>';
                $html .= '</div>';
            }
        } else {
            $html = '<div class="gcg-suggestion-item no-results">فاکتوری یافت نشد</div>';
        }

        wp_send_json_success(array('html' => $html));
    }
    public function ajax_gcg_get_sales_report() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
    
        global $wpdb;
        $shop_id = sanitize_text_field($_POST['shop_id']);
        $period = sanitize_text_field($_POST['period']);
        $orderby_input = isset($_POST['orderby']) ? sanitize_key($_POST['orderby']) : 'date';
        $allowed_orderby = ['id', 'date', 'customer', 'profit', 'total'];
        $orderby = in_array($orderby_input, $allowed_orderby) ? $orderby_input : 'date';
        
        $order_input = isset($_POST['order']) ? strtoupper(sanitize_key($_POST['order'])) : 'DESC';
        $order = in_array($order_input, ['ASC', 'DESC']) ? $order_input : 'DESC';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $single_invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
        $invoices_per_page = 10;
    
        $date_query = $this->get_date_query_for_period($period, $date_from, $date_to);
    
        // Optimized Query: Get only Post IDs first
        $args = array(
            'post_type' => 'gcg_invoice',
            'meta_key' => 'gcg_shop_id',
            'meta_value' => $shop_id,
            'posts_per_page' => -1,
            'date_query' => $date_query,
            'fields' => 'ids', // Important: fetch only IDs
        );

        if ($single_invoice_id > 0) {
            $args['post__in'] = array($single_invoice_id);
            $args['date_query'] = null; // Ignore date query when fetching a single invoice
        }
    
        $invoice_ids = get_posts($args);
        $total_invoices_for_period = count($invoice_ids);
    
        if (empty($invoice_ids)) {
            wp_send_json_success(array('report' => array(
                'sales_summary' => array('total_invoices' => 0, 'gross_sales' => 0, 'total_tax' => 0, 'net_sales' => 0),
                'profit_summary' => array('total_profit' => 0),
                'product_stats' => array('sold_weight' => 0, 'lowest_rate' => 0, 'highest_rate' => 0),
                'purchase_exchange_stats' => array('purchased_weight' => 0),
                'sales_invoices_html' => '<tr><td colspan="6">هیچ فاکتور فروشی یافت نشد.</td></tr>',
                'purchase_invoices_html' => '<tr><td colspan="6">هیچ فاکتور خریدی یافت نشد.</td></tr>',
                'has_more_pages' => false
            )));
            return;
        }
    
        // Prime the meta cache to prevent N+1 queries
        update_meta_cache('post', $invoice_ids);
        
        // Now get the full post objects for the primed IDs
        $invoices = get_posts(array(
            'post__in' => $invoice_ids,
            'post_type' => 'gcg_invoice',
            'posts_per_page' => -1,
            'orderby' => 'date', // Default sort, will be re-sorted in PHP
            'order' => 'DESC'
        ));
    
        $report_data = array();
        $total_gross_sales = 0;
        $total_tax = 0;
        $total_profit = 0;
        $total_weight_sold = 0;
    
        foreach ($invoices as $invoice) {
            $invoice_data = get_post_meta($invoice->ID, 'gcg_invoice_data', true);
            if (!$invoice_data) continue;
    
            $invoice_profit = 0;
            if (!empty($invoice_data['items'])) {
                foreach ($invoice_data['items'] as $item) {
                    $base_price = floatval($item['weight']) * floatval($invoice_data['gold_price']);
                    $labor_amount = $base_price * (floatval($item['labor_value']) / 100);
                    $profit_amount = ($base_price + $labor_amount) * (floatval($item['profit_value']) / 100);
                    $invoice_profit += $profit_amount;
                    $total_weight_sold += floatval($item['weight']);
                }
            }
    
            $total_gross_sales += floatval($invoice_data['finalPrice']);
            $total_tax += floatval($invoice_data['tax_amount']);
            $total_profit += $invoice_profit;
    
            $report_data[] = array(
                'post' => $invoice,
                'customer' => $invoice_data['customer_name'] ?? '',
                'profit' => $invoice_profit,
                'total' => floatval($invoice_data['finalPrice'] ?? 0),
                'id' => $invoice->ID,
                'date' => $invoice->post_date,
            );
        }
    
        // Manual sorting in PHP
        usort($report_data, function ($a, $b) use ($orderby, $order) {
            $val_a = $a[$orderby];
            $val_b = $b[$orderby];
            $result = ($orderby === 'customer') ? strcasecmp($val_a, $val_b) : (($orderby === 'date') ? strtotime($val_a) <=> strtotime($val_b) : $val_a <=> $val_b);
            return ($order === 'ASC') ? $result : -$result;
        });
    
        // Pagination Logic
        $initial_load_count = 20;
        $load_more_count = 10;
        $offset = 0;
        $limit = $initial_load_count;

        if ($page > 1) {
            $offset = $initial_load_count + (($page - 2) * $load_more_count);
            $limit = $load_more_count;
        }
        
        $paginated_data = array_slice($report_data, $offset, $limit);

        // More accurate check for has_more_pages
        $total_fetched_so_far = $offset + count($paginated_data);
        $has_more_pages = $total_fetched_so_far < $total_invoices_for_period;
    
        $sales_invoices_html = '';
        if (empty($paginated_data)) {
             $sales_invoices_html = '<tr><td colspan="6">هیچ فاکتور فروشی یافت نشد.</td></tr>';
        } else {
            foreach ($paginated_data as $data) {
                $invoice = $data['post'];
                $sales_invoices_html .= '<tr>';
                $sales_invoices_html .= '<td>' . $invoice->ID . '</td>';
                $sales_invoices_html .= '<td>' . $this->get_formatted_date($invoice->post_date) . '</td>';
                $sales_invoices_html .= '<td>' . esc_html($data['customer']) . '</td>';
                $sales_invoices_html .= '<td>' . number_format($data['profit']) . '</td>';
                $sales_invoices_html .= '<td>' . number_format($data['total']) . '</td>';
                $sales_invoices_html .= '<td><button class="gcg-btn-small view-invoice" data-id="' . $invoice->ID . '">مشاهده</button> <button class="gcg-btn-small delete-invoice" data-id="' . $invoice->ID . '" style="background: #e74c3c;">حذف</button></td>';
                $sales_invoices_html .= '</tr>';
            }
        }
    
        // This part remains the same as it's a separate, non-paginated list
        $purchases_table = $wpdb->prefix . 'gcg_gold_purchases';
        $purchases = $wpdb->get_results($wpdb->prepare("SELECT * FROM $purchases_table WHERE shop_id = %s", $shop_id));
        $purchase_invoices_html = '';
        $total_purchased_weight = 0;
        foreach($purchases as $purchase) {
            $purchase_invoices_html .= '<tr>';
            $purchase_invoices_html .= '<td>' . $purchase->id . '</td>';
            $purchase_invoices_html .= '<td>' . $this->get_formatted_date($purchase->purchase_date) . '</td>';
            $purchase_invoices_html .= '<td>' . esc_html($purchase->customer_name) . '</td>';
            $purchase_invoices_html .= '<td>' . number_format(floatval($purchase->weight), 3) . '</td>';
            $purchase_invoices_html .= '<td>' . number_format(floatval($purchase->purchase_price)) . '</td>';
            $purchase_invoices_html .= '<td><button class="gcg-btn-small delete-purchase" data-id="' . $purchase->id . '" style="background: #e74c3c;">حذف</button></td>';
            $purchase_invoices_html .= '</tr>';
            $total_purchased_weight += floatval($purchase->weight);
        }
    
        $report = array(
            'sales_summary' => array(
                'total_invoices' => $total_invoices_for_period,
                'gross_sales' => $total_gross_sales,
                'total_tax' => $total_tax,
                'net_sales' => $total_gross_sales - $total_tax,
                'total_weight_sold' => $total_weight_sold
            ),
            'profit_summary' => array('total_profit' => $total_profit),
            'purchase_exchange_stats' => array(
                'purchased_weight' => $total_purchased_weight
            ),
            'sales_invoices_html' => $sales_invoices_html,
            'purchase_invoices_html' => $purchase_invoices_html ?: '<tr><td colspan="6">هیچ فاکتور خریدی یافت نشد.</td></tr>',
            'has_more_pages' => $has_more_pages
        );
    
        wp_send_json_success(array('report' => $report));
    }

    private function get_date_query_for_period($period, $date_from = '', $date_to = '') {
        if ($period === 'custom' && !empty($date_from)) {
            $query = array('after' => $date_from . ' 00:00:00');
            if (!empty($date_to)) {
                $query['before'] = $date_to . ' 23:59:59';
            }
            return $query;
        }
    
        switch ($period) {
            case 'today': return array('year' => date('Y'), 'month' => date('m'), 'day' => date('d'));
            case 'week': return array('year' => date('Y'), 'week' => date('W'));
            case 'month': return array('year' => date('Y'), 'month' => date('m'));
            case 'year': return array('year' => date('Y'));
            default: return array();
        }
    }
    
    public function ajax_gcg_get_customer_stats() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        global $wpdb;
        $shop_id = sanitize_text_field($_POST['shop_id']);
        $orderBy = isset($_POST['orderBy']) && $_POST['orderBy'] === 'purchases' ? 'total_purchases' : 'total_amount';
        $table_name = $wpdb->prefix . 'gcg_customers';
        
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE shop_id = %s ORDER BY $orderBy DESC LIMIT 10",
            $shop_id
        ));
        
        $html = '';
        if ($customers) {
            foreach ($customers as $customer) {
                $last_purchase = $customer->last_purchase_date ? date_i18n('Y/m/d', strtotime($customer->last_purchase_date)) : 'بدون خرید';
                $html .= '<tr>';
                $html .= '<td>' . esc_html($customer->name) . '</td>';
                $html .= '<td>' . $customer->total_purchases . '</td>';
                $html .= '<td>' . number_format($customer->total_amount) . ' تومان</td>';
                $html .= '<td>' . $last_purchase . '</td>';
                $html .= '</tr>';
            }
        } else {
            $html = '<tr><td colspan="4">هیچ داده‌ای یافت نشد.</td></tr>';
        }
        
        wp_send_json_success(array('html' => $html));
    }
    
    public function ajax_gcg_get_product_stats() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        $shop_id = sanitize_text_field($_POST['shop_id']);
        $period = sanitize_text_field($_POST['period'] ?? 'month');
        $date_query = $this->get_date_query_for_period($period);

        $invoices = get_posts(array(
            'post_type' => 'gcg_invoice',
            'meta_key' => 'gcg_shop_id',
            'meta_value' => $shop_id,
            'posts_per_page' => -1,
            'date_query' => $date_query
        ));

        $product_stats = array();

        foreach ($invoices as $invoice) {
            $invoice_data = get_post_meta($invoice->ID, 'gcg_invoice_data', true);
            if (!empty($invoice_data['items'])) {
                foreach ($invoice_data['items'] as $item) {
                    $name = $item['name'];
                    if (!isset($product_stats[$name])) {
                        $product_stats[$name] = array('name' => $name, 'count' => 0, 'weight' => 0, 'revenue' => 0);
                    }
                    $product_stats[$name]['count']++;
                    $product_stats[$name]['weight'] += floatval($item['weight']);
                    
                    $base_price = $item['weight'] * $invoice_data['gold_price'];
                    $labor_amount = $base_price * ($item['labor_value'] / 100);
                    $profit_amount = ($base_price + $labor_amount) * ($item['profit_value'] / 100);
                    $product_stats[$name]['revenue'] += $base_price + $labor_amount + $profit_amount;
                }
            }
        }

        uasort($product_stats, function($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });

        $html = '';
        if ($product_stats) {
            foreach (array_slice($product_stats, 0, 10) as $product) {
                $html .= '<tr>';
                $html .= '<td>' . esc_html($product['name']) . '</td>';
                $html .= '<td>' . $product['count'] . '</td>';
                $html .= '<td>' . number_format($product['weight'], 3) . '</td>';
                $html .= '<td>' . number_format($product['revenue']) . ' تومان</td>';
                $html .= '</tr>';
            }
        } else {
            $html = '<tr><td colspan="4">هیچ داده‌ای یافت نشد.</td></tr>';
        }
        
        wp_send_json_success(array('html' => $html));
    }
    
    public function ajax_gcg_get_invoice_print_html() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');

        $invoice_id = intval($_POST['invoice_id']);
        $invoice = get_post($invoice_id);

        if (!$invoice || $invoice->post_type !== 'gcg_invoice') {
            wp_send_json_error(array('message' => 'فاکتور یافت نشد.'));
        }

        $invoice_data = get_post_meta($invoice_id, 'gcg_invoice_data', true);
        $shop_id = get_post_meta($invoice_id, 'gcg_shop_id', true);
        $shop_details = $this->get_shop_details($shop_id);
        $invoice_settings = $this->get_invoice_settings($shop_id);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html dir="rtl" lang="fa-IR">
        <head>
            <meta charset="UTF-8">
            <title>فاکتور شماره <?php echo $invoice_id; ?></title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <style>
                @media print {
                    @page { margin: 0.5cm; size: A4; }
                    body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; margin: 0; padding: 0; background: white !important; color: black !important; }
                    .no-print { display: none !important; }
                    .invoice-container { box-shadow: none !important; border: none !important; }
                    .invoice-header, .items-table thead { background: #f5f5f5 !important; color: black !important; }
                    .border-bottom, .summary-section > div { border-color: #ddd !important; }
                    .logo img { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; filter: none !important; }
                }
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Vazirmatn', sans-serif; direction: rtl; font-size: 13px; background-color: #f8f9fa; color: #333; line-height: 1.5; padding: 20px; }
                .invoice-container { max-width: 800px; margin: 0 auto; background: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); border: 1px solid #ddd; }
                .invoice-content { padding: 25px; }
                .invoice-header { padding: 20px 0; border-bottom: 2px solid #333; margin-bottom: 20px; }
                .header-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
                .logo { width: 20%; }
                .logo img { max-width: 100px; max-height: 80px; }
                .invoice-title { text-align: center; flex-grow: 1; }
                .invoice-title h1 { font-size: 22px; font-weight: 700; margin-bottom: 5px; }
                .invoice-title h2 { font-size: 16px; font-weight: 500; }
                .invoice-number { width: 20%; text-align: left; }
                .invoice-number p { margin: 5px 0; font-weight: 600; }
                .header-bottom { display: flex; justify-content: space-between; margin-top: 15px; }
                .client-info, .gold-rate { width: 48%; }
                .client-info h3, .gold-rate h3 { font-size: 14px; margin-bottom: 8px; padding-bottom: 5px; border-bottom: 1px solid #ddd; }
                .client-info p, .gold-rate p { margin: 6px 0; display: flex; justify-content: space-between; }
                .client-info span, .gold-rate span { font-weight: 600; }
                .items-section { margin: 20px 0; }
                .section-title { font-size: 16px; font-weight: 600; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #333; }
                .items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .items-table thead { background: #f5f5f5; }
                .items-table th { padding: 10px 8px; text-align: center; font-weight: 600; font-size: 13px; border: 1px solid #ddd; }
                .items-table td { padding: 10px 8px; text-align: center; border: 1px solid #ddd; }
                .items-table tbody tr:nth-child(even) { background-color: #f9f9f9; }
                .summary-section { display: flex; justify-content: space-between; margin-top: 20px; gap: 15px; }
                .summary-details, .summary-total { padding: 15px; border: 1px solid #ddd; flex: 1; }
                .summary-details h3, .summary-total h3 { font-size: 15px; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #ddd; }
                .summary-details p, .summary-total p { margin: 8px 0; display: flex; justify-content: space-between; }
                .total-amount { font-size: 18px; font-weight: 700; margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd; }
                .total-in-words { font-size: 12px; margin-top: 8px; line-height: 1.6; padding: 8px; background: #f5f5f5; border-radius: 4px; }
                .invoice-footer { margin-top: 25px; padding-top: 15px; border-top: 1px solid #333; display: flex; justify-content: space-between; align-items: flex-start; }
                .shop-info { flex: 1; }
                .shop-info p { margin: 4px 0; }
                .signatures { display: flex; gap: 40px; }
                .signature-box { text-align: center; }
                .signature-line { width: 150px; height: 1px; background: #333; margin: 25px auto 8px; }
                .signature-box p { font-weight: 600; font-size: 12px; }
                .print-btn { position: fixed; bottom: 20px; left: 20px; background: #333; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-family: 'Vazirmatn', sans-serif; font-weight: 600; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
                .print-btn:hover { background: #555; }
                @media print { .items-table tbody tr:nth-child(even) { background-color: #f0f0f0 !important; } .total-in-words { background: #f0f0f0 !important; } }
                .text-left { text-align: left; } .text-right { text-align: right; } .text-center { text-align: center; } .bold { font-weight: 700; }
                .border-bottom { border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 10px; }
                .tax-info { background: #f8f8f8; padding: 8px 12px; border-radius: 4px; margin-top: 10px; border-right: 3px solid #333; }
            </style>
        </head>
        <body>
            <div class="invoice-container">
                <div class="invoice-content">
                    <div class="invoice-header">
                        <div class="header-top">
                            <div class="logo">
                                <?php if (!empty($shop_details['logo'])): ?>
                                    <img src="<?php echo esc_url($shop_details['logo']); ?>" alt="لوگو">
                                <?php endif; ?>
                            </div>
                            <div class="invoice-title">
                                <h1>فاکتور فروش طلا و جواهر</h1>
                                <h2><?php echo esc_html($shop_details['name']); ?></h2>
                            </div>
                            <div class="invoice-number">
                                <p>شماره: <span class="bold"><?php echo $invoice_id; ?></span></p>
                                <p>تاریخ: <span class="bold"><?php echo $this->get_formatted_date($invoice->post_date); ?></span></p>
                            </div>
                        </div>
                        <div class="header-bottom">
                            <div class="client-info">
                                <h3>اطلاعات مشتری</h3>
                                <p>نام کامل: <span><?php echo esc_html($invoice_data['customer_name']); ?></span></p>
                                <p>شماره تماس: <span><?php echo esc_html($invoice_data['customer_phone']); ?></span></p>
                            </div>
                            <div class="gold-rate">
                                <h3>نرخ طلای روز</h3>
                                <p>نرخ یک گرم طلای ۱۸ عیار: <span><?php echo number_format($invoice_data['gold_price']); ?> تومان</span></p>
                            </div>
                        </div>
                    </div>
                    <div class="items-section">
                        <h3 class="section-title">جزئیات کالاها</h3>
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>شرح کالا</th>
                                    <th>عیار</th>
                                    <th>وزن (گرم)</th>
                                    <?php if ($invoice_settings['print_show_labor_column']): ?><th>اجرت %</th><?php endif; ?>
                                    <?php if ($invoice_settings['print_show_profit_column']): ?><th>سود %</th><?php endif; ?>
                                    <th>مبلغ نهایی</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (!empty($invoice_data['items'])) {
                                    foreach ($invoice_data['items'] as $item) {
                                        $base_price = $item['weight'] * $invoice_data['gold_price'];
                                        $labor_amount = $base_price * ($item['labor_value'] / 100);
                                        $profit_amount = ($base_price + $labor_amount) * ($item['profit_value'] / 100);
                                        $item_labor_profit = $labor_amount + $profit_amount;
                                        $item_tax = $item_labor_profit * ($invoice_data['tax_percent'] / 100);
                                        $item_total_with_tax = $base_price + $item_labor_profit + $item_tax;
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html($item['name']); ?></td>
                                            <td><?php echo esc_html($item['purity']); ?></td>
                                            <td><?php echo number_format($item['weight'], 3); ?></td>
                                            <?php if ($invoice_settings['print_show_labor_column']): ?><td><?php echo esc_html($item['labor_value']); ?>%</td><?php endif; ?>
                                            <?php if ($invoice_settings['print_show_profit_column']): ?><td><?php echo esc_html($item['profit_value']); ?>%</td><?php endif; ?>
                                            <td><?php echo number_format($item_total_with_tax); ?></td>
                                        </tr>
                                    <?php }
                                }
                                if (!empty($invoice_data['coins'])) {
                                     foreach ($invoice_data['coins'] as $coin) { 
                                        $colspan = 2;
                                        if ($invoice_settings['print_show_labor_column']) $colspan++;
                                        if ($invoice_settings['print_show_profit_column']) $colspan++;
                                     ?>
                                        <tr>
                                             <td><?php echo esc_html($coin['type']); ?></td>
                                             <td colspan="<?php echo $colspan; ?>">-</td>
                                             <td><?php echo number_format($coin['unit_price']); ?></td>
                                        </tr>
                                     <?php }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="summary-section">
                        <div class="summary-total">
                            <h3>جمع کل فاکتور</h3>
                            <p class="total-amount"><?php echo number_format($invoice_data['finalPrice']); ?> تومان</p>
                            <div class="total-in-words">
                                <span class="bold">مبلغ به حروف:</span> <?php echo $this->number_to_persian_words($invoice_data['finalPrice']); ?> تومان
                            </div>
                        </div>
                        <?php if ($invoice_settings['print_show_tax']): ?>
                        <div class="summary-details">
                            <h3>جزئیات مالی</h3>
                            <div class="tax-info">
                                <p>مالیات بر ارزش افزوده: <span><?php echo $invoice_data['tax_percent']; ?>%</span></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="invoice-footer">
                        <div class="shop-info">
                             <p><span class="bold">آدرس:</span> <?php echo esc_html($shop_details['address']); ?></p>
                            <p><span class="bold">تلفن:</span> <?php echo esc_html($shop_details['phone']); ?></p>
                        </div>
                        <div class="signatures">
                            <div class="signature-box">
                                <div class="signature-line"></div>
                                <p>مهر و امضای فروشنده</p>
                            </div>
                            <div class="signature-box">
                                <div class="signature-line"></div>
                                <p>مهر و امضای خریدار</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <button class="print-btn no-print" onclick="window.print()">چاپ فاکتور</button>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const today = new Date();
                    const options = { year: 'numeric', month: '2-digit', day: '2-digit' };
                });
            </script>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }

    public function ajax_gcg_save_exchange_invoice() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        
        $shop_id = sanitize_text_field($_POST['shop_id']);
        $exchange_data = json_decode(stripslashes($_POST['exchange_data']), true);
        
        $post_id = wp_insert_post([
            'post_title' => 'فاکتور تعویض برای ' . $exchange_data['customer_name'],
            'post_type' => 'gcg_exchange',
            'post_status' => 'publish'
        ]);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => 'خطا در ذخیره فاکتور تعویض.']);
        }
        
        update_post_meta($post_id, 'gcg_shop_id', $shop_id);
        update_post_meta($post_id, 'gcg_exchange_data', $exchange_data);
        
        $shop_details = $this->get_shop_details($shop_id);
        
        ob_start();
        // HTML for print goes here, similar to ajax_gcg_get_invoice_print_html but for exchange
        ?>
        <!DOCTYPE html>
        <html dir="rtl" lang="fa-IR">
        <head><title>فاکتور تعویض</title></head>
        <body>
            <div class="invoice-print">
                <h1>فاکتور تعویض کالا</h1>
                <p>مشتری: <?php echo esc_html($exchange_data['customer_name']); ?></p>
                <hr>
                <h2>طلای دریافتی از مشتری</h2>
                <p>ارزش کل: <?php echo number_format($exchange_data['received_gold']['value']); ?> تومان</p>
                <h2>طلای تحویلی به مشتری</h2>
                <p>ارزش کل: <?php echo number_format($exchange_data['delivered_gold']['value']); ?> تومان</p>
                <hr>
                <h3>مبلغ نهایی: <?php echo number_format(abs($exchange_data['final_payment'])); ?> تومان (<?php echo $exchange_data['final_payment'] > 0 ? 'پرداختی مشتری' : 'دریافتی مشتری'; ?>)</h3>
            </div>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }
    
    public function ajax_gcg_close_ticket() {
        $this->prevent_caching();
        check_ajax_referer('gcg_nonce', 'gcg_security_nonce');
        global $wpdb;
        $ticket_id = intval($_POST['ticket_id']);
        $wpdb->update($wpdb->prefix . 'gcg_tickets', ['status' => 'closed'], ['id' => $ticket_id]);
        wp_send_json_success(['message' => 'تیکت با موفقیت بسته شد.']);
    }

    private function get_ticket_status_text($status) {
        $statuses = array(
            'open' => 'باز',
            'pending' => 'در حال بررسی',
            'resolved' => 'حل شده',
            'closed' => 'بسته'
        );
        
        return $statuses[$status] ?? $status;
    }
    
    private function get_ticket_priority_text($priority) {
        $priorities = array(
            'low' => 'کم',
            'medium' => 'متوسط',
            'high' => 'بالا',
            'urgent' => 'فوری'
        );
        
        return $priorities[$priority] ?? $priority;
    }

    private function get_formatted_date($timestamp_str) {
        if (function_exists('jdate')) {
            return jdate('Y/m/d', strtotime($timestamp_str));
        }
        return date_i18n('Y/m/d', strtotime($timestamp_str));
    }
    private function prevent_caching() {
        header('Cache-Control: no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
    }

    private function number_to_persian_words($number) {
        $number = intval($number); // Ensure it's an integer
        $ones = array("", "یک", "دو", "سه", "چهار", "پنج", "شش", "هفت", "هشت", "نه");
        $teens = array("ده", "یازده", "دوازده", "سیزده", "چهارده", "پانزده", "شانزده", "هفده", "هجده", "نوزده");
        $tens = array("", "", "بیست", "سی", "چهل", "پنجاه", "شصت", "هفتاد", "هشتاد", "نود");
        $hundreds = array("", "یکصد", "دویست", "سیصد", "چهارصد", "پانصد", "ششصد", "هفتصد", "هشتصد", "نهصد");
        $levels = array("", "هزار", "میلیون", "میلیارد");

        if ($number == 0) {
            return "صفر";
        }

        $result = "";
        $level = 0;

        while ($number > 0) {
            $chunk = $number % 1000;
            $number = intval($number / 1000);

            if ($chunk > 0) {
                $chunk_text = "";
                $hundred = intval($chunk / 100);
                $rest = $chunk % 100;

                if ($hundred > 0) {
                    $chunk_text .= $hundreds[$hundred];
                }

                if ($rest > 0) {
                    if ($hundred > 0) {
                        $chunk_text .= " و ";
                    }

                    if ($rest < 10) {
                        $chunk_text .= $ones[$rest];
                    } elseif ($rest < 20) {
                        $chunk_text .= $teens[$rest - 10];
                    } else {
                        $ten = intval($rest / 10);
                        $one = $rest % 10;
                        $chunk_text .= $tens[$ten];
                        if ($one > 0) {
                            $chunk_text .= " و " . $ones[$one];
                        }
                    }
                }
                
                if ($level > 0 && !empty($chunk_text)) {
                     $result = $chunk_text . " " . $levels[$level] . (empty($result) ? "" : " و ") . $result;
                } else {
                     $result = $chunk_text . (empty($result) ? "" : " و ") . $result;
                }
            }
            $level++;
        }
        return trim($result);
    }
    
    // Admin Pages
    public function reports_page() {
        ?>
        <div class="wrap">
            <h1>گزارشات جامع سیستم</h1>
            
            <div class="gcg-admin-reports">
                <div class="gcg-report-filters">
                    <div class="gcg-filter-row">
                        <div class="gcg-filter-group">
                            <label>بازه زمانی:</label>
                            <select id="admin-report-period">
                                <option value="today">امروز</option>
                                <option value="week">هفته جاری</option>
                                <option value="month" selected>ماه جاری</option>
                                <option value="year">سال جاری</option>
                                <option value="custom">سفارشی</option>
                            </select>
                        </div>
                        <div class="gcg-filter-group">
                            <label>فروشگاه:</label>
                            <select id="admin-report-shop">
                                <option value="all">همه فروشگاه‌ها</option>
                                <?php
                                $shops = get_option('gcg_shops', array());
                                foreach ($shops as $shop) {
                                    echo '<option value="' . esc_attr($shop['id']) . '">' . esc_html($shop['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="gcg-reports-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">
                    <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <h3 style="margin: 0 0 10px 0; color: #666;">کل فروش</h3>
                        <div style="font-size: 1.5em; font-weight: bold; color: #27ae60;" id="total-sales-report">0 تومان</div>
                    </div>
                    <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <h3 style="margin: 0 0 10px 0; color: #666;">تعداد فاکتور</h3>
                        <div style="font-size: 1.5em; font-weight: bold; color: #3498db;" id="total-invoices-report">0</div>
                    </div>
                    <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <h3 style="margin: 0 0 10px 0; color: #666;">میانگین فاکتور</h3>
                        <div style="font-size: 1.5em; font-weight: bold; color: #e74c3c;" id="avg-invoice-report">0 تومان</div>
                    </div>
                </div>

                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3>گزارش تفصیلی فروش</h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th>فروشگاه</th>
                                <th>تعداد فاکتور</th>
                                <th>فروش ناخالص</th>
                                <th>مالیات</th>
                                <th>فروش خالص</th>
                                <th>میانگین فاکتور</th>
                            </tr>
                        </thead>
                        <tbody id="detailed-sales-report">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function tickets_page() {
        global $wpdb;

        if (isset($_POST['status_submit']) && check_admin_referer('gcg_change_status')) {
            $ticket_id = intval($_POST['ticket_id']);
            $new_status = sanitize_text_field($_POST['ticket_status']);
            $wpdb->update($wpdb->prefix . 'gcg_tickets', ['status' => $new_status], ['id' => $ticket_id]);
            echo '<div class="notice notice-success is-dismissible"><p>وضعیت تیکت با موفقیت بروزرسانی شد.</p></div>';
        }

        if (isset($_POST['reply_submit']) && check_admin_referer('gcg_reply_ticket')) {
            $ticket_id = intval($_POST['ticket_id']);
            $reply_message = sanitize_textarea_field($_POST['reply_message']);
            
            $wpdb->insert(
                $wpdb->prefix . 'gcg_ticket_replies',
                ['ticket_id' => $ticket_id, 'user_type' => 'admin', 'message' => $reply_message],
                ['%d', '%s', '%s']
            );
            
            $wpdb->update(
                $wpdb->prefix . 'gcg_tickets',
                ['status' => 'pending'],
                ['id' => $ticket_id],
                ['%s'],
                ['%d']
            );
            
            echo '<div class="notice notice-success is-dismissible"><p>پاسخ شما با موفقیت ثبت شد.</p></div>';
        }

        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['ticket_id'])) {
            $this->render_admin_ticket_view_page();
            return;
        }

        $tickets = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gcg_tickets ORDER BY created_at DESC");
        $shops = get_option('gcg_shops', array());
        ?>
        <div class="wrap">
            <h1>تیکت‌های پشتیبانی</h1>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>فروشگاه</th>
                        <th>موضوع</th>
                        <th>اولویت</th>
                        <th>وضعیت</th>
                        <th>آخرین بروزرسانی</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr><td colspan="6">هیچ تیکتی یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $ticket):
                            $shop_name = 'ناشناس';
                            foreach($shops as $shop) {
                                if($shop['id'] == $ticket->shop_id) {
                                    $shop_name = $shop['name'];
                                    break;
                                }
                            }
                        ?>
                        <tr>
                            <td><?php echo esc_html($shop_name); ?></td>
                            <td><?php echo esc_html($ticket->subject); ?></td>
                            <td><?php echo $this->get_ticket_priority_text($ticket->priority); ?></td>
                            <td><?php echo $this->get_ticket_status_text($ticket->status); ?></td>
                            <td><?php echo human_time_diff(strtotime($ticket->updated_at)) . ' پیش'; ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=gcg_tickets&action=view&ticket_id=' . $ticket->id); ?>" class="button">مشاهده و پاسخ</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_admin_ticket_view_page() {
        global $wpdb;
        $ticket_id = intval($_GET['ticket_id']);
        $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gcg_tickets WHERE id = %d", $ticket_id));
        $replies = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gcg_ticket_replies WHERE ticket_id = %d ORDER BY created_at ASC", $ticket_id));
        ?>
        <div class="wrap">
            <h1>مشاهده تیکت: <?php echo esc_html($ticket->subject); ?></h1>
            <div class="postbox">
                <div class="postbox-header"><h2>پیام اصلی</h2></div>
                <div class="inside">
                    <p><?php echo nl2br(esc_html($ticket->message)); ?></p>
                    <p><strong>وضعیت:</strong> <?php echo $this->get_ticket_status_text($ticket->status); ?></p>
                </div>
            </div>
            
            <?php foreach($replies as $reply): ?>
                 <div class="postbox">
                    <div class="postbox-header"><h2>پاسخ از طرف <?php echo ($reply->user_type === 'admin' ? 'پشتیبانی' : 'فروشگاه'); ?></h2></div>
                    <div class="inside">
                        <p><?php echo nl2br(esc_html($reply->message)); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="postbox">
                 <div class="postbox-header"><h2>ارسال پاسخ جدید</h2></div>
                 <div class="inside">
                     <form method="post">
                         <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                         <?php wp_nonce_field('gcg_reply_ticket'); ?>
                         <textarea name="reply_message" rows="5" style="width: 100%;"></textarea>
                         <?php submit_button('ارسال پاسخ', 'primary', 'reply_submit'); ?>
                     </form>
                 </div>
            </div>

            <div class="postbox">
                 <div class="postbox-header"><h2>تغییر وضعیت تیکت</h2></div>
                 <div class="inside">
                     <form method="post">
                         <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                         <?php wp_nonce_field('gcg_change_status'); ?>
                         <select name="ticket_status">
                            <option value="open" <?php selected($ticket->status, 'open'); ?>>باز</option>
                            <option value="pending" <?php selected($ticket->status, 'pending'); ?>>در حال بررسی</option>
                            <option value="resolved" <?php selected($ticket->status, 'resolved'); ?>>حل شده</option>
                            <option value="closed" <?php selected($ticket->status, 'closed'); ?>>بسته</option>
                         </select>
                         <?php submit_button('تغییر وضعیت', 'secondary', 'status_submit'); ?>
                     </form>
                 </div>
            </div>
        </div>
        <?php
    }
    
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>تنظیمات سیستم</h1>
            
            <div class="gcg-admin-settings">
                <form method="post" action="options.php">
                    <?php settings_fields('gcg_settings'); ?>
                    <?php do_settings_sections('gcg_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">نرخ‌دهی خودکار طلا</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="gcg_auto_price_update" value="1" <?php checked(get_option('gcg_auto_price_update', 1)); ?>>
                                    فعال‌سازی به‌روزرسانی خودکار نرخ طلا
                                </label>
                                <p class="description">در صورت فعال‌سازی، نرخ طلا هر 1 دقیقه به‌روزرسانی می‌شود.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">بازه زمانی کش نرخ طلا</th>
                            <td>
                                <select name="gcg_price_cache_time">
                                    <option value="60" <?php selected(get_option('gcg_price_cache_time', 60), 60); ?>>1 دقیقه</option>
                                    <option value="300" <?php selected(get_option('gcg_price_cache_time', 60), 300); ?>>5 دقیقه</option>
                                    <option value="600" <?php selected(get_option('gcg_price_cache_time', 60), 600); ?>>10 دقیقه</option>
                                </select>
                                <p class="description">تعیین مدت زمان ذخیره‌سازی موقت نرخ طلا</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">مالیات پیش‌فرض</th>
                            <td>
                                <input type="number" name="gcg_default_tax" value="<?php echo esc_attr(get_option('gcg_default_tax', 9)); ?>" step="0.1" min="0" max="100" class="small-text">
                                <span>درصد</span>
                                <p class="description">مالیات بر ارزش افزوده پیش‌فرض برای فاکتورها</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">اجرت پیش‌فرض برای کالاهای جدید</th>
                            <td>
                                <input type="number" name="gcg_default_labor_percent" value="<?php echo esc_attr(get_option('gcg_default_labor_percent', 10)); ?>" step="0.1" min="0" max="100" class="small-text">
                                <span>درصد</span>
                                <p class="description">این درصد زمانی استفاده می‌شود که یک کالای جدید از طریق فاکتور ثبت شود.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">سود پیش‌فرض برای کالاهای جدید</th>
                            <td>
                                <input type="number" name="gcg_default_profit_percent" value="<?php echo esc_attr(get_option('gcg_default_profit_percent', 7)); ?>" step="0.1" min="0" max="100" class="small-text">
                                <span>درصد</span>
                                <p class="description">این درصد زمانی استفاده می‌شود که یک کالای جدید از طریق فاکتور ثبت شود.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">اعلان‌های ایمیلی</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="gcg_email_notifications" value="1" <?php checked(get_option('gcg_email_notifications', 1)); ?>>
                                    فعال‌سازی اعلان‌های ایمیلی
                                </label>
                                <p class="description">ارسال ایمیل برای فاکتورهای جدید و اعلامیه‌ها  </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('ذخیره تنظیمات'); ?>
                </form>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
GoldAccountingPro::get_instance();

// Register settings
add_action('admin_init', function() {
    register_setting('gcg_settings', 'gcg_auto_price_update');
    register_setting('gcg_settings', 'gcg_price_cache_time');
    register_setting('gcg_settings', 'gcg_default_tax');
    register_setting('gcg_settings', 'gcg_default_labor_percent');
    register_setting('gcg_settings', 'gcg_default_profit_percent');
    register_setting('gcg_settings', 'gcg_email_notifications');
});

