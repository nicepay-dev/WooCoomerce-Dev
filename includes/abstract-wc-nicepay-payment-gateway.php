<?php
/**
 * Abstract NICEPay Payment Gateway Class
 *
 * Extended by individual payment gateways to provide common functionality.
 * @file        abstract-wc-nicepay-payment-gateway.php
 * @class       WC_Nicepay_Payment_Gateway
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     NICEPay_WC
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Nicepay_Payment_Gateway class.
 */
abstract class WC_Nicepay_Payment_Gateway extends WC_Payment_Gateway {

    /**
     * Is test mode active?
     *
     * @var bool
     */
    public $testmode;

    /**
     * Merchant ID
     *
     * @var string
     */
    public $merchant_id;

    /**
     * Merchant Key
     *
     * @var string
     */
    public $merchant_key;

    /**
     * Channel ID
     *
     * @var string
     */
    public $channel_id;

    /**
     * Private Key
     *
     * @var string
     */
    public $private_key;

    /**
     * API Environment
     *
     * @var string
     */
    public $environment;

    /**
     * Debug mode
     *
     * @var bool
     */
    public $debug;

    /**
     * Instructions
     *
     * @var string
     */
    public $instructions;

    /**
     * API Endpoints
     *
     * @var array
     */
    public $api_endpoints;

    /**
     * Logger instance
     *
     * @var WC_Logger
     */
    public static $log;

    /**
     * Constructor
     */
    public function __construct() {
        // Setup general properties.
        $this->order_button_text = __('Proceed to NICEPay', 'nicepay-wc');
        $this->supports = array(
            'products',
            'refunds',
        );

        // Note: init_form_fields() and credential setup will be handled by child classes
        // as each payment method has different credential requirements

       // Hooks.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'handle_webhook'));
     }

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level   Optional. Default 'info'. Possible values:
     *                        emergency|alert|critical|error|warning|notice|info|debug.
     */
    public static function log($message, $level = 'info') {
        if (apply_filters('wc_nicepay_logging', true, $message)) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->log($level, $message, array('source' => 'nicepay'));
        }
    }

   /**
     * Check if this gateway is enabled.
     */
    public function is_available() {
        if ('yes' !== $this->enabled) {
            return false;
        }

        if (!$this->merchant_id || !$this->merchant_key) {
            return false;
        }

        return true;
    }

    /**
     * Get API endpoint URLs based on environment and payment method.
     * Each payment method will override this to return their specific endpoints.
     */
    protected function get_api_endpoints() {
        $base_url = ('production' === $this->environment) 
            ? 'https://www.nicepay.co.id'
            : 'https://dev.nicepay.co.id';

        // Base endpoints - to be overridden by child classes
        return array(
            'access_token' => $base_url . '/nicepay/v1.0/access-token/b2b',
        );
    }

    /**
     * Get VA-specific API endpoints
     */
    protected function get_va_endpoints() {
        $base_url = ('production' === $this->environment) 
            ? 'https://www.nicepay.co.id'
            : 'https://dev.nicepay.co.id';

        return array(
            'access_token'     => $base_url . '/nicepay/v1.0/access-token/b2b',
            'registration'     => $base_url . '/nicepay/api/v1.0/transfer-va/create-va',
            'check_status'     => $base_url . '/nicepay/api/v1.0/transfer-va/status',
            'cancel'          => $base_url . '/nicepay/api/v1.0/transfer-va/cancel',
            'inquiry'         => $base_url . '/nicepay/api/v1.0/transfer-va/inquiry',
        );
    }

    /**
     * Get E-wallet-specific API endpoints
     */
    protected function get_ewallet_endpoints() {
        $base_url = ('production' === $this->environment) 
            ? 'https://www.nicepay.co.id'
            : 'https://dev.nicepay.co.id';

        return array(
            'access_token'     => $base_url . '/nicepay/v1.0/access-token/b2b',
            'registration'     => $base_url . '/nicepay/api/v1.0/debit/payment-host-to-host',
            'check_status'     => $base_url . '/nicepay/api/v1.0/debit/status',
            'refund'          => $base_url . '/nicepay/api/v1.0/debit/refund',
            'cancel'          => $base_url . '/nicepay/api/v1.0/debit/cancel',
        );
    }

    /**
     * Get Credit Card-specific API endpoints
     */
    protected function get_cc_endpoints() {
        $base_url = ('production' === $this->environment) 
            ? 'https://www.nicepay.co.id'
            : 'https://dev.nicepay.co.id';

        return array(
            'access_token'     => $base_url . '/nicepay/v1.0/access-token/b2b',
            'registration'     => $base_url . '/nicepay/api/v1.0/credit-card/payment',
            'check_status'     => $base_url . '/nicepay/api/v1.0/credit-card/status',
            'refund'          => $base_url . '/nicepay/api/v1.0/credit-card/refund',
            'cancel'          => $base_url . '/nicepay/api/v1.0/credit-card/cancel',
            'capture'         => $base_url . '/nicepay/api/v1.0/credit-card/capture',
        );
    }

    /**
     * Get QRIS-specific API endpoints
     */
    protected function get_qris_endpoints() {
        $base_url = ('production' === $this->environment) 
            ? 'https://www.nicepay.co.id'
            : 'https://dev.nicepay.co.id';

        return array(
            'access_token'     => $base_url . '/nicepay/v1.0/access-token/b2b',
            'registration'     => $base_url . '/nicepay/api/v1.0/qris/qr-mpm-generate',
            'check_status'     => $base_url . '/nicepay/api/v1.0/qris/payment-status',
            'refund'          => $base_url . '/nicepay/api/v1.0/qris/refund',
            'cancel'          => $base_url . '/nicepay/api/v1.0/qris/cancel',
        );
    }

    /**
     * Get Payloan-specific API endpoints  
     */
    protected function get_payloan_endpoints() {
        $base_url = ('production' === $this->environment) 
            ? 'https://www.nicepay.co.id'
            : 'https://dev.nicepay.co.id';

        return array(
            'access_token'     => $base_url . '/nicepay/v1.0/access-token/b2b',
            'registration'     => $base_url . '/nicepay/api/v1.0/payloan/registration',
            'check_status'     => $base_url . '/nicepay/api/v1.0/payloan/status',
            'approval'        => $base_url . '/nicepay/api/v1.0/payloan/approval',
            'cancel'          => $base_url . '/nicepay/api/v1.0/payloan/cancel',
        );
    }

    /**
     * Get CVS (Convenience Store)-specific API endpoints
     */
    protected function get_cvs_endpoints() {
        $base_url = ('production' === $this->environment) 
            ? 'https://www.nicepay.co.id'
            : 'https://dev.nicepay.co.id';

        return array(
            'access_token'     => $base_url . '/nicepay/v1.0/access-token/b2b',
            'registration'     => $base_url . '/nicepay/api/v1.0/convenience-store/create-payment',
            'check_status'     => $base_url . '/nicepay/api/v1.0/convenience-store/status',
            'cancel'          => $base_url . '/nicepay/api/v1.0/convenience-store/cancel',
        );
    }

    /**
     * Generate timestamp in required format.
     */
    protected function generate_timestamp() {
        $date = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        return $date->format('Y-m-d\TH:i:sP');
    }

    /**
     * Generate signature for API requests.
     */
    protected function generate_signature($string_to_sign) {
        $private_key = "-----BEGIN RSA PRIVATE KEY-----\r\n" .
            $this->private_key . "\r\n" .
            "-----END RSA PRIVATE KEY-----";

        $binary_signature = "";
        $pKey = openssl_pkey_get_private($private_key);
        
        if ($pKey === false) {
            throw new Exception('Invalid private key');
        }
        
        $sign_result = openssl_sign($string_to_sign, $binary_signature, $pKey, OPENSSL_ALGO_SHA256);
        
        if ($sign_result === false) {
            throw new Exception('Failed to create signature');
        }
        
        return base64_encode($binary_signature);
    }

    /**
     * Get access token from NICEPay API.
     */
    protected function get_access_token() {
        $timestamp = $this->generate_timestamp();
        $string_to_sign = $this->merchant_id . "|" . $timestamp;
        $signature = $this->generate_signature($string_to_sign);

        $request_data = array(
            'grantType' => 'client_credentials'
        );

        $args = array(
            'method'  => 'POST',
            'timeout' => 45,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-SIGNATURE'  => $signature,
                'X-CLIENT-KEY' => $this->merchant_id,
                'X-TIMESTAMP'  => $timestamp
            ),
            'body' => json_encode($request_data),
        );

        // Use the access token endpoint (same for all payment methods)
        $endpoints = $this->get_api_endpoints();
        $response = wp_remote_post($endpoints['access_token'], $args);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['accessToken'])) {
            throw new Exception('Invalid access token response');
        }

        return $body['accessToken'];
    }

    /**
     * Process the payment and return the result.
     * Must be implemented by child classes.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    abstract public function process_payment($order_id);

    /**
     * Handle webhook/callback from NICEPay.
     * Can be overridden by child classes for specific handling.
     */
    public function handle_webhook() {
        // Basic webhook handler - can be overridden by child classes
        wp_die('NICEPay webhook handler not implemented.', 'Webhook Handler', array('response' => 200));
    }
}