<?php
/**
 * NICEPay Admin Class - Simplified Version
 * 
 * Hanya untuk mengaktifkan/menonaktifkan payment methods
 * Setting credentials tetap di WooCommerce > Settings > Payments
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Nicepay_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
    }

    /**
     * Add admin menu
     */
    public function admin_menu() {
        add_menu_page(
            __('NICEPay Settings', 'nicepay-wc'),
            __('NICEPay', 'nicepay-wc'),
            'manage_woocommerce',
            'nicepay-settings',
            array($this, 'admin_page'),
            'dashicons-money-alt',
            56
        );
    }

    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting('nicepay_settings', 'nicepay_enable_va');
        register_setting('nicepay_settings', 'nicepay_enable_cc');
        register_setting('nicepay_settings', 'nicepay_enable_ewallet');
        register_setting('nicepay_settings', 'nicepay_debug_mode');
        register_setting('nicepay_settings', 'nicepay_reduce_stock');
    }

    /**
     * Enqueue admin scripts
     */
    public function admin_scripts($hook) {
        if ($hook !== 'toplevel_page_nicepay-settings') {
            return;
        }

        wp_enqueue_style(
            'nicepay-admin-style',
            plugins_url('assets/css/admin.css', dirname(dirname(__FILE__))),
            array(),
            defined('NICEPAY_WC_VERSION') ? NICEPAY_WC_VERSION : '1.0.0'
        );
    }

    /**
     * Display admin page
     */
    public function admin_page() {
        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('nicepay_settings_save', 'nicepay_nonce')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'nicepay-wc') . '</p></div>';
        }

        // Get current settings
        $va_enabled = get_option('nicepay_enable_va', 'yes');
        $cc_enabled = get_option('nicepay_enable_cc', 'yes');
        $ewallet_enabled = get_option('nicepay_enable_ewallet', 'yes');
        $debug_mode = get_option('nicepay_debug_mode', 'no');
        $reduce_stock = get_option('nicepay_reduce_stock', 'no');
        
        // Check if credentials are configured
        $credentials_configured = $this->check_credentials();
        ?>
        <div class="wrap">
            <div class="nicepay-header">
                <div class="nicepay-logo">
                    <img src="<?php echo plugins_url('assets/images/nicepay-logo.png', dirname(dirname(__FILE__))); ?>" alt="NICEPay" />
                </div>
                <div class="nicepay-info">
                    <h1><?php _e('NICEPay Payment Gateway', 'nicepay-wc'); ?></h1>
                    <p class="nicepay-version"><?php _e('Version', 'nicepay-wc'); ?> <?php echo defined('NICEPAY_WC_VERSION') ? NICEPAY_WC_VERSION : '1.0.0'; ?></p>
                </div>
            </div>

            <div class="nicepay-description">
                <p><?php _e('Configure your NICEPay payment gateway settings below. You can enable/disable available payment methods and customize their behavior.', 'nicepay-wc'); ?></p>
                
                <?php if (!$credentials_configured): ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php _e('Credentials not configured!', 'nicepay-wc'); ?></strong>
                        <?php _e('Please configure your merchant credentials in', 'nicepay-wc'); ?>
                        <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout'); ?>"><?php _e('WooCommerce > Settings > Payments', 'nicepay-wc'); ?></a>
                        <?php _e('for each payment method you want to enable.', 'nicepay-wc'); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('nicepay_settings_save', 'nicepay_nonce'); ?>
                
                <h2 class="title"><?php _e('Payment Methods', 'nicepay-wc'); ?></h2>
                <p><?php _e('Enable or disable payment methods. Configure individual settings in WooCommerce Payment Settings.', 'nicepay-wc'); ?></p>
                
                <table class="form-table nicepay-payment-methods">
                    <tbody>
                        <!-- Virtual Account -->
                        <tr>
                            <th scope="row">
                                <label for="nicepay_enable_va">
                                    <img src="<?php echo plugins_url('assets/images/va-logo.jpg', dirname(dirname(__FILE__))); ?>" alt="Virtual Account" class="payment-method-icon" />
                                    <?php _e('Virtual Account', 'nicepay-wc'); ?>
                                </label>
                            </th>
                            <td>
                                <fieldset>
                                    <label for="nicepay_enable_va">
                                        <input type="checkbox" id="nicepay_enable_va" name="nicepay_enable_va" value="yes" <?php checked($va_enabled, 'yes'); ?> />
                                        <?php _e('Enable Virtual Account payment method', 'nicepay-wc'); ?>
                                    </label>
                                    <p class="description"><?php _e('Allow customers to pay using NICEPay Virtual Account payment methods.', 'nicepay-wc'); ?></p>
                                    <p class="payment-method-link">
                                        <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=nicepay_va'); ?>" class="button-secondary">
                                            <?php _e('Configure Settings', 'nicepay-wc'); ?>
                                        </a>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- Credit Card -->
                        <tr>
                            <th scope="row">
                                <label for="nicepay_enable_cc">
                                    <img src="<?php echo plugins_url('assets/images/nicepaycc.png', dirname(dirname(__FILE__))); ?>" alt="Credit Card" class="payment-method-icon" />
                                    <?php _e('Credit Card', 'nicepay-wc'); ?>
                                </label>
                            </th>
                            <td>
                                <fieldset>
                                    <label for="nicepay_enable_cc">
                                        <input type="checkbox" id="nicepay_enable_cc" name="nicepay_enable_cc" value="yes" <?php checked($cc_enabled, 'yes'); ?> />
                                        <?php _e('Enable Credit Card payment method', 'nicepay-wc'); ?>
                                    </label>
                                    <p class="description"><?php _e('Allows payments using NICEPay Credit Card.', 'nicepay-wc'); ?></p>
                                    <p class="payment-method-link">
                                        <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=nicepay_cc'); ?>" class="button-secondary">
                                            <?php _e('Configure Settings', 'nicepay-wc'); ?>
                                        </a>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- E-Wallet -->
                        <tr>
                            <th scope="row">
                                <label for="nicepay_enable_ewallet">
                                    <img src="<?php echo plugins_url('assets/images/ewallet-logo.jpg', dirname(dirname(__FILE__))); ?>" alt="E-Wallet" class="payment-method-icon" />
                                    <?php _e('E-Wallet', 'nicepay-wc'); ?>
                                </label>
                            </th>
                            <td>
                                <fieldset>
                                    <label for="nicepay_enable_ewallet">
                                        <input type="checkbox" id="nicepay_enable_ewallet" name="nicepay_enable_ewallet" value="yes" <?php checked($ewallet_enabled, 'yes'); ?> />
                                        <?php _e('Enable E-Wallet payment method', 'nicepay-wc'); ?>
                                    </label>
                                    <p class="description"><?php _e('Allows payments using NICEPAY E-wallet like OVO, DANA, LinkAja, and ShopeePay.', 'nicepay-wc'); ?></p>
                                    <p class="payment-method-link">
                                        <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=nicepay_ewallet'); ?>" class="button-secondary">
                                            <?php _e('Configure Settings', 'nicepay-wc'); ?>
                                        </a>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2 class="title"><?php _e('Advanced Settings', 'nicepay-wc'); ?></h2>
                
                <table class="form-table">
                    <tbody>
                        <!-- Debug Mode -->
                        <tr>
                            <th scope="row">
                                <label for="nicepay_debug_mode"><?php _e('Debug Mode', 'nicepay-wc'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label for="nicepay_debug_mode">
                                        <input type="checkbox" id="nicepay_debug_mode" name="nicepay_debug_mode" value="yes" <?php checked($debug_mode, 'yes'); ?> />
                                        <?php _e('Enable debug mode (logging)', 'nicepay-wc'); ?>
                                    </label>
                                    <p class="description"><?php _e('Log debug messages for troubleshooting. Only enable when needed.', 'nicepay-wc'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- Reduce Stock -->
                        <tr>
                            <th scope="row">
                                <label for="nicepay_reduce_stock"><?php _e('Reduce Stock', 'nicepay-wc'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label for="nicepay_reduce_stock">
                                        <input type="checkbox" id="nicepay_reduce_stock" name="nicepay_reduce_stock" value="yes" <?php checked($reduce_stock, 'yes'); ?> />
                                        <?php _e('Reduce stock levels when order is placed', 'nicepay-wc'); ?>
                                    </label>
                                    <p class="description"><?php _e('Reduce stock when payment is initiated rather than when completed.', 'nicepay-wc'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Save Changes', 'nicepay-wc')); ?>
            </form>

            <div class="nicepay-footer">
                <h3><?php _e('Need Help?', 'nicepay-wc'); ?></h3>
                <p>
                    <?php _e('For technical support or questions about NICEPay integration:', 'nicepay-wc'); ?>
                </p>
                <ul>
                    <li><a href="https://docs.nicepay.co.id" target="_blank"><?php _e('NICEPay Documentation', 'nicepay-wc'); ?></a></li>
                    <li><a href="mailto:technical.support@nicepay.co.id"><?php _e('Technical Support', 'nicepay-wc'); ?></a></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Save settings
     */
    private function save_settings() {
        $va_enabled = isset($_POST['nicepay_enable_va']) ? 'yes' : 'no';
        $cc_enabled = isset($_POST['nicepay_enable_cc']) ? 'yes' : 'no';
        $ewallet_enabled = isset($_POST['nicepay_enable_ewallet']) ? 'yes' : 'no';
        $debug_mode = isset($_POST['nicepay_debug_mode']) ? 'yes' : 'no';
        $reduce_stock = isset($_POST['nicepay_reduce_stock']) ? 'yes' : 'no';

        update_option('nicepay_enable_va', $va_enabled);
        update_option('nicepay_enable_cc', $cc_enabled);
        update_option('nicepay_enable_ewallet', $ewallet_enabled);
        update_option('nicepay_debug_mode', $debug_mode);
        update_option('nicepay_reduce_stock', $reduce_stock);

        // Log the changes
        if (function_exists('cc_error_log')) {
            cc_error_log('NICEPay settings updated: VA=' . $va_enabled . ', CC=' . $cc_enabled . ', E-wallet=' . $ewallet_enabled, 'info');
        }
    }

    /**
     * Check if credentials are configured
     */
    private function check_credentials() {
        $va_settings = get_option('woocommerce_nicepay_va_settings', array());
        $cc_settings = get_option('woocommerce_nicepay_cc_settings', array());
        $ewallet_settings = get_option('woocommerce_nicepay_ewallet_settings', array());

        $va_configured = !empty($va_settings['merchant_id']) && !empty($va_settings['merchant_key']);
        $cc_configured = !empty($cc_settings['merchant_id']) && !empty($cc_settings['merchant_key']);
        $ewallet_configured = !empty($ewallet_settings['merchant_id']) && !empty($ewallet_settings['merchant_key']);

        // Return true if at least one payment method has credentials configured
        return $va_configured || $cc_configured || $ewallet_configured;
    }
}

// Initialize admin class
new WC_Nicepay_Admin();