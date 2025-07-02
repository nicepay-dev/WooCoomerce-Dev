<?php
/**
 * NICEPay Blocks Integration
 *
 * Handles WooCommerce Blocks checkout integration for all NICEPay payment methods.
 *
 * @package NICEPay_WC
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * NICEPay Blocks integration class
 */
final class WC_NICEPay_Blocks_Integration extends AbstractPaymentMethodType {

    /**
     * The gateway instance.
     *
     * @var WC_Gateway_Nicepay_VA|WC_Gateway_Nicepay_CC|WC_Gateway_Nicepay_Ewallet
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name;

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option("woocommerce_{$this->name}_settings", []);
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $script_path = NICEPAY_WC_PLUGIN_URL . 'assets/js/' . $this->name . '-blok.js';
        $script_asset_path = NICEPAY_WC_PLUGIN_DIR . 'assets/js/' . $this->name . '-blok.asset.php';
        
        $script_asset = file_exists($script_asset_path)
            ? require($script_asset_path)
            : array(
                'dependencies' => array('wp-element', 'wp-i18n', 'wp-hooks', 'wc-blocks-registry'),
                'version' => NICEPAY_WC_VERSION
            );

        wp_register_script(
            "wc-{$this->name}-blocks",
            $script_path,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        // Localize script data
        wp_localize_script(
            "wc-{$this->name}-blocks",
            'nicepayBlocksData',
            array(
                'title' => $this->get_setting('title'),
                'description' => $this->get_setting('description'),
                'supports' => $this->get_supported_features(),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce("nicepay-{$this->name}-nonce"),
                'pluginUrl' => NICEPAY_WC_PLUGIN_URL,
                'paymentMethod' => $this->name
            )
        );

        return ["wc-{$this->name}-blocks"];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        return [
            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports' => $this->get_supported_features(),
        ];
    }

    /**
     * Get gateway setting
     */
    protected function get_setting($key) {
        return isset($this->settings[$key]) ? $this->settings[$key] : '';
    }

    /**
     * Get supported features
     */
    protected function get_supported_features() {
        $gateway = $this->get_gateway();
        return $gateway ? $gateway->supports : [];
    }

    /**
     * Get the gateway instance
     */
    protected function get_gateway() {
        if (!$this->gateway) {
            $gateways = WC()->payment_gateways->payment_gateways();
            $this->gateway = isset($gateways[$this->name]) ? $gateways[$this->name] : false;
        }
        return $this->gateway;
    }
}

/**
 * NICEPay Virtual Account Blocks Integration
 */
final class WC_NICEPay_VA_Blocks_Integration extends WC_NICEPay_Blocks_Integration {
    protected $name = 'nicepay_va';

    public function get_payment_method_data() {
        $data = parent::get_payment_method_data();
        
        // Add VA-specific data
        $gateway = $this->get_gateway();
        if ($gateway) {
            $data['bankList'] = $gateway->get_bank_list();
        }
        
        return $data;
    }
}

/**
 * NICEPay Credit Card Blocks Integration
 */
final class WC_NICEPay_CC_Blocks_Integration extends WC_NICEPay_Blocks_Integration {
    protected $name = 'nicepay_cc';

    public function get_payment_method_data() {
        $data = parent::get_payment_method_data();
        
        // Add CC-specific data
        $gateway = $this->get_gateway();
        if ($gateway) {
            $data['installmentOptions'] = method_exists($gateway, 'get_available_installments') 
                ? $gateway->get_available_installments() 
                : [];
        }
        
        return $data;
    }
}

/**
 * NICEPay E-wallet Blocks Integration
 */
final class WC_NICEPay_Ewallet_Blocks_Integration extends WC_NICEPay_Blocks_Integration {
    protected $name = 'nicepay_ewallet';

    public function get_payment_method_data() {
        $data = parent::get_payment_method_data();
        
        // Add E-wallet specific data
        $gateway = $this->get_gateway();
        if ($gateway) {
            $data['ewalletOptions'] = method_exists($gateway, 'get_ewallet_list') 
                ? $gateway->get_ewallet_list() 
                : [];
        }
        
        return $data;
    }
}

/**
 * Register all NICEPay payment methods with WooCommerce Blocks
 */
add_action('woocommerce_blocks_loaded', function() {
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        // Register VA
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new WC_NICEPay_VA_Blocks_Integration());
            }
        );

        // Register CC
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new WC_NICEPay_CC_Blocks_Integration());
            }
        );

        // Register E-wallet
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new WC_NICEPay_Ewallet_Blocks_Integration());
            }
        );
    }
});