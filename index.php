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
        if (file_exists(NICEPAY_WC_PLUGIN_DIR . 'includes/abstract-wc-nicepay-payment-gateway.php')) {
            include_once NICEPAY_WC_PLUGIN_DIR . 'includes/abstract-wc-nicepay-payment-gateway.php';
        }
        
        // Include admin class
        if (file_exists(NICEPAY_WC_PLUGIN_DIR . 'includes/admin/class-wc-nicepay-admin.php')) {
            include_once NICEPAY_WC_PLUGIN_DIR . 'includes/admin/class-wc-nicepay-admin.php';
        }

        if (file_exists(NICEPAY_WC_PLUGIN_DIR . 'includes/integrations/class-wc-nicepay-blocks-integration.php')) {
    include_once NICEPAY_WC_PLUGIN_DIR . 'includes/integrations/class-wc-nicepay-blocks-integration.php';
    error_log("NICEPay: Block integration file loaded");
} else {
    error_log("NICEPay: Block integration file NOT FOUND");
}

        
        // Include available gateways
        $this->include_gateways();
    }
    
    /**
     * Include available payment gateways
     */
     private function include_gateways() {
        // Array of gateway files to include
        $gateway_files = array(
            'va' => NICEPAY_WC_PLUGIN_DIR . 'includes/gateway/class-wc-gateway-nicepay-va.php',
            'cc' => NICEPAY_WC_PLUGIN_DIR . 'includes/gateway/class-wc-gateway-nicepay-cc.php',
            'ewallet' => NICEPAY_WC_PLUGIN_DIR . 'includes/gateway/class-wc-gateway-nicepay-ewallet.php',
            'qris' => NICEPAY_WC_PLUGIN_DIR . 'includes/gateway/class-wc-gateway-nicepay-qris.php',
            'payloan' => NICEPAY_WC_PLUGIN_DIR . 'includes/gateway/class-wc-gateway-nicepay-payloan.php',
            'cvs' => NICEPAY_WC_PLUGIN_DIR . 'includes/gateway/class-wc-gateway-nicepay-cvs.php'
        );
        
        foreach ($gateway_files as $gateway_type => $file_path) {
            if (file_exists($file_path)) {
                include_once $file_path;
                error_log("NICEPay: {$gateway_type} gateway file loaded");
            } else {
                error_log("NICEPay: {$gateway_type} gateway file NOT FOUND at: {$file_path}");
            }
        }
    }
    
    /**
     * Add payment gateways to WooCommerce
     */
   public function add_gateways($gateways) {
        // Array of gateway classes to check and add
        $gateway_classes = array(
            'WC_Gateway_Nicepay_VA',
            'WC_Gateway_Nicepay_CC', 
            'WC_Gateway_Nicepay_Ewallet',
            'WC_Gateway_Nicepay_QRIS',
            'WC_Gateway_Nicepay_Payloan',
            'WC_Gateway_Nicepay_CVS'
        );
        
        foreach ($gateway_classes as $class_name) {
            if (class_exists($class_name)) {
                $gateways[] = $class_name;
                error_log("NICEPay: Gateway class {$class_name} registered");
            } else {
                error_log("NICEPay: Gateway class {$class_name} NOT FOUND");
            }
        }
        
        error_log('NICEPay: Total gateways registered: ' . count($gateways));
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
        ?>
        <div class="wrap">
            <h1><?php _e('NICEPay Settings', 'nicepay-wc'); ?></h1>
            <p><?php _e('Configure your NICEPay payment gateway settings below. You can also configure individual payment methods in WooCommerce > Settings > Payments.', 'nicepay-wc'); ?></p>
            
            <div class="card">
                <h2><?php _e('Quick Links', 'nicepay-wc'); ?></h2>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=nicepay_ewallet'); ?>" class="button button-primary">
                        <?php _e('Configure E-Wallet', 'nicepay-wc'); ?>
                    </a>
                    <?php if (class_exists('WC_Gateway_Nicepay_VA')): ?>
                    <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=nicepay_va'); ?>" class="button">
                        <?php _e('Configure Virtual Account', 'nicepay-wc'); ?>
                    </a>
                    <?php endif; ?>
                    <?php if (class_exists('WC_Gateway_Nicepay_CC')): ?>
                    <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=nicepay_cc'); ?>" class="button">
                        <?php _e('Configure Credit Card', 'nicepay-wc'); ?>
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout'); ?>" class="button">
                        <?php _e('All Payment Methods', 'nicepay-wc'); ?>
                    </a>
                </p>
            </div>
            
             <div class="card">
                <h2><?php _e('Available Payment Methods', 'nicepay-wc'); ?></h2>
                <ul>
                    <?php if (class_exists('WC_Gateway_Nicepay_Ewallet')): ?>
                    <li>✅ <strong>E-Wallet</strong> - OVO, DANA, LinkAja, ShopeePay</li>
                    <?php else: ?>
                    <li>❌ <strong>E-Wallet</strong> - Class not found</li>
                    <?php endif; ?>
                    <?php if (class_exists('WC_Gateway_Nicepay_VA')): ?>
                    <li>✅ <strong>Virtual Account</strong> - Bank transfers</li>
                    <?php else: ?>
                    <li>❌ <strong>Virtual Account</strong> - Class not found</li>
                    <?php endif; ?>
                    <?php if (class_exists('WC_Gateway_Nicepay_CC')): ?>
                    <li>✅ <strong>Credit Card</strong> - Visa, Mastercard, JCB</li>
                    <?php else: ?>
                    <li>❌ <strong>Credit Card</strong> - Class not found</li>
                    <?php endif; ?>
                </ul>
            </div>
            
          <div class="card">
                <h2><?php _e('Debug Information', 'nicepay-wc'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Plugin Version', 'nicepay-wc'); ?></th>
                        <td><?php echo NICEPAY_WC_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('WooCommerce Version', 'nicepay-wc'); ?></th>
                        <td><?php echo defined('WC_VERSION') ? WC_VERSION : __('Not detected', 'nicepay-wc'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('PHP Version', 'nicepay-wc'); ?></th>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Abstract Class', 'nicepay-wc'); ?></th>
                        <td><?php echo class_exists('WC_Nicepay_Payment_Gateway') ? '✅ Loaded' : '❌ Not found'; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Gateway Classes', 'nicepay-wc'); ?></th>
                        <td>
                            E-wallet: <?php echo class_exists('WC_Gateway_Nicepay_Ewallet') ? '✅' : '❌'; ?><br>
                            VA: <?php echo class_exists('WC_Gateway_Nicepay_VA') ? '✅' : '❌'; ?><br>
                            CC: <?php echo class_exists('WC_Gateway_Nicepay_CC') ? '✅' : '❌'; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register global settings
        register_setting('nicepay_settings', 'nicepay_environment');
        register_setting('nicepay_settings', 'nicepay_debug_mode');
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_scripts($hook) {
        // Only load on NICEPay settings page
        if ($hook !== 'woocommerce_page_nicepay-settings') {
            return;
        }
        
        // Check if CSS file exists before enqueuing
        $css_file = NICEPAY_WC_PLUGIN_DIR . 'assets/css/admin.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'nicepay-admin-style',
                NICEPAY_WC_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                NICEPAY_WC_VERSION
            );
        }
    }
    
     /**
     * Plugin activation hook
     */
    public function activate() {
        // Set default settings if not already set
        if (get_option('nicepay_environment') === false) {
            update_option('nicepay_environment', 'sandbox');
        }
        
        // Create necessary directories
        $upload_dir = wp_upload_dir();
        $nicepay_dir = $upload_dir['basedir'] . '/nicepay-logs';
        
        if (!file_exists($nicepay_dir)) {
            wp_mkdir_p($nicepay_dir);
        }
        
        // Flush rewrite rules to ensure our custom endpoints work
        flush_rewrite_rules();
        
        // Log activation
        error_log('NICEPay plugin activated');
    }
    
    /**
     * Add action links on plugin page
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=nicepay-settings') . '">' . __('Settings', 'nicepay-wc') . '</a>';
        $docs_link = '<a href="https://docs.nicepay.co.id" target="_blank">' . __('Docs', 'nicepay-wc') . '</a>';
        
        array_unshift($links, $settings_link, $docs_link);
        return $links;
    }
}

// Start the plugin - menghindari duplikasi inisialisasi
function nicepay_wc_init() {
    return NICEPay_WC::get_instance();
}
// AKTIFKAN PLUGIN - ini yang penting!
add_action('plugins_loaded', 'nicepay_wc_init', 11);


// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up scheduled events
    wp_clear_scheduled_hook('check_nicepay_payment_status');
    error_log('NICEPay plugin deactivated');
});


// Initialize plugin
// nicepay_wc_init();