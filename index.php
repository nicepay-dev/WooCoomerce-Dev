<?php
/**
 * Plugin Name: NICEPay Payment Gateway for WooCommerce
 * Plugin URI: http://nicepay.co.id
 * Description: Terintegrasi berbagai metode pembayaran NICEPay untuk WooCommerce (VA, Credit Card, E-Wallet)
 * Version: 1.0.0
 * Author: NICEPay
 * Author URI: http://nicepay.co.id
 * Text Domain: nicepay-wc
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 7.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definisikan konstanta plugin
define('NICEPAY_WC_VERSION', '1.0.0');
define('NICEPAY_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NICEPAY_WC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main NICEPay WooCommerce Class
 */
class NICEPay_WC {
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Return an instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }
    
    /**
     * Construct the plugin
     */
    public function __construct() {
        // Hook into WordPress/WooCommerce
        add_action('plugins_loaded', array($this, 'init'));
        
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Add settings link to plugin page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WC_Payment_Gateway')) {
            add_action('admin_notices', array($this, 'woocommerce_not_active_notice'));
            return;
        }
        
        // Load plugin text domain
        load_plugin_textdomain('nicepay-wc', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Include required files
        $this->includes();
        
        // Add payment gateways to WooCommerce
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
        
        // Add admin menu for settings
        add_action('admin_menu', array($this, 'add_menu_page'));
        
        // Register plugin settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
    }
    
    /**
     * Show notice if WooCommerce is not active
     */
    public function woocommerce_not_active_notice() {
        ?>
        <div class="error">
            <p><?php _e('NICEPay Payment Gateway requires WooCommerce to be active!', 'nicepay-wc'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Include abstract gateway class
        include_once NICEPAY_WC_PLUGIN_DIR . 'includes/abstract-wc-nicepay-payment-gateway.php';
        
        // Include admin class
        include_once NICEPAY_WC_PLUGIN_DIR . 'includes/admin/class-wc-nicepay-admin.php';
        
        // Include available gateways
        $this->include_gateways();
    }
    
    /**
     * Include available payment gateways
     */
    private function include_gateways() {
        // Get enabled gateways from settings
        $enabled_gateways = array(
            'va' => get_option('nicepay_enable_va', 'yes'),
            'cc' => get_option('nicepay_enable_cc', 'yes'),
            'ewallet' => get_option('nicepay_enable_ewallet', 'yes')
        );
        
        // Always include all gateway files regardless of enabled status
        // This ensures admin can still configure them
        
        // Virtual Account
        include_once NICEPAY_WC_PLUGIN_DIR . 'includes/gateway/class-wc-gateway-nicepay-va.php';
        
        // Credit Card
        if (file_exists(NICEPAY_WC_PLUGIN_DIR . 'includes/gateway/class-wc-gateway-nicepay-cc.php')) {
            include_once NICEPAY_WC_PLUGIN_DIR . 'includes/gateway/class-wc-gateway-nicepay-cc.php';
        }
        
        // E-Wallet
        if (file_exists(NICEPAY_WC_PLUGIN_DIR . 'includes/gateway/class-wc-gateway-nicepay-ewallet.php')) {
            include_once NICEPAY_WC_PLUGIN_DIR . 'includes/gateway/class-wc-gateway-nicepay-ewallet.php';
        }
    }
    
    /**
     * Add payment gateways to WooCommerce
     */
    public function add_gateways($gateways) {
        error_log('Adding NICEPay gateways to WooCommerce');
        error_log('VA enabled: ' . (get_option('nicepay_enable_va', 'yes') === 'yes' ? 'yes' : 'no'));
    
        // Add gateways based on settings
        if (get_option('nicepay_enable_va', 'yes') === 'yes') {
            $gateways[] = 'WC_Gateway_Nicepay_VA';
            error_log('Added NICEPay VA gateway');
        }
        
        if (get_option('nicepay_enable_cc', 'yes') === 'yes' && 
            class_exists('WC_Gateway_Nicepay_CC')) {
            $gateways[] = 'WC_Gateway_Nicepay_CC';
        }
        
        if (get_option('nicepay_enable_ewallet', 'yes') === 'yes' && 
            class_exists('WC_Gateway_Nicepay_Ewallet')) {
            $gateways[] = 'WC_Gateway_Nicepay_Ewallet';
        }
        
        return $gateways;
    }
    
    /**
     * Add menu page for NICEPay settings
     */
    public function add_menu_page() {
        add_submenu_page(
            'woocommerce',
            __('NICEPay Settings', 'nicepay-wc'),
            __('NICEPay', 'nicepay-wc'),
            'manage_woocommerce',
            'nicepay-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Display settings page
     */
    public function settings_page() {
        include_once NICEPAY_WC_PLUGIN_DIR . 'includes/admin/view/html-admin-settings.php';
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register settings
        register_setting('nicepay_settings', 'nicepay_environment');
        register_setting('nicepay_settings', 'nicepay_merchant_id');
        register_setting('nicepay_settings', 'nicepay_merchant_key');
        register_setting('nicepay_settings', 'nicepay_channel_id');
        register_setting('nicepay_settings', 'nicepay_private_key');
        
        // Register gateway enable settings
        register_setting('nicepay_settings', 'nicepay_enable_va');
        register_setting('nicepay_settings', 'nicepay_enable_cc');
        register_setting('nicepay_settings', 'nicepay_enable_ewallet');
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_scripts($hook) {
        // Only load on NICEPay settings page
        if ($hook !== 'woocommerce_page_nicepay-settings') {
            return;
        }
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'nicepay-admin-style',
            NICEPAY_WC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            NICEPAY_WC_VERSION
        );
        
        // Enqueue admin JS
        wp_enqueue_script(
            'nicepay-admin-script',
            NICEPAY_WC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            NICEPAY_WC_VERSION,
            true
        );
    }
    
    /**
     * Plugin activation hook
     */
    public function activate() {
        // Set default settings if not already set
        if (get_option('nicepay_environment') === false) {
            update_option('nicepay_environment', 'sandbox');
        }
        
        if (get_option('nicepay_enable_va') === false) {
            update_option('nicepay_enable_va', 'yes');
        }
        
        if (get_option('nicepay_enable_cc') === false) {
            update_option('nicepay_enable_cc', 'yes');
        }
        
        if (get_option('nicepay_enable_ewallet') === false) {
            update_option('nicepay_enable_ewallet', 'yes');
        }
        
        // Create necessary database tables if needed
        // $this->create_tables();
        
        // Flush rewrite rules to ensure our custom endpoints work
        flush_rewrite_rules();
    }
    
    /**
     * Add action links on plugin page
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=nicepay-settings') . '">' . __('Settings', 'nicepay-wc') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Start the plugin
function nicepay_wc_init() {
    return NICEPay_WC::get_instance();
}

// Initialize plugin
nicepay_wc_init();