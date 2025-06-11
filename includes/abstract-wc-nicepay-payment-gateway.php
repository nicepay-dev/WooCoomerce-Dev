<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract NICEPay Payment Gateway Class
 * 
 * Extends the WooCommerce Payment Gateway class to provide
 * base functionality for all NICEPay payment methods.
 * 
 * @class     WC_Nicepay_Payment_Gateway
 * @extends   WC_Payment_Gateway
 */
abstract class WC_Nicepay_Payment_Gateway extends WC_Payment_Gateway {
    /**
     * API endpoints for NICEPay
     */
    protected $api_endpoints;
    
    /**
     * Environment (sandbox or production)
     */
    protected $environment;
    
    /**
     * Merchant ID from NICEPay
     */
    protected $merchant_id;
    
    /**
     * Merchant Key from NICEPay
     */
    protected $merchant_key;
    
    /**
     * Channel ID from NICEPay
     */
    protected $channel_id;
    
    /**
     * Private Key from NICEPay
     */
    protected $private_key;
    
    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        // Load common settings from the main plugin settings
        $this->init_common_settings();
        
        // Setup API endpoints
        $this->api_endpoints = $this->get_api_endpoints();
        
        // Add actions for all NICEPay gateways
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }
    
    /**
     * Initialize common settings from the main plugin options
     */
    protected function init_common_settings() {
        // Get common settings from options
        $this->environment = get_option('nicepay_environment', 'sandbox');
        $this->merchant_id = get_option('nicepay_merchant_id', '');
        $this->merchant_key = get_option('nicepay_merchant_key', '');
        $this->channel_id = get_option('nicepay_channel_id', '');
        $this->private_key = get_option('nicepay_private_key', '');
    }
    
    /**
     * Define API endpoints based on environment
     */
    protected function get_api_endpoints() {
        $base_url = $this->environment === 'production' 
            ? 'https://www.nicepay.co.id'
            : 'https://dev.nicepay.co.id';


        
            $endpoints = [
            'access_token'     => $base_url . '/nicepay/v1.0/access-token/b2b',
            'create_va'        => $base_url . '/nicepay/api/v1.0/transfer-va/create-va',
            'check_status_url' => $base_url . '/nicepay/api/v1.0/transfer-va/status',
            'create_cc'        => $base_url . '/nicepay/redirect/v2/registration',
            'check_cc_status'  => $base_url . '/nicepay/direct/v2/inquiry',
            'create_ewallet'   => $base_url . '/nicepay/api/v1.0/debit/payment-host-to-host',
            'check_ewallet_status' => $base_url . '/nicepay/api/v1.0/debit/status',
        ];
        error_log('API endpoints initialized: ' . print_r($endpoints, true));
        return $endpoints;
    }
    
    /**
     * Check if this gateway is available for the current cart/order
     */
    public function is_available() {
        $is_available = ('yes' === $this->enabled);
        
        // Check if required settings are configured
        if (empty($this->merchant_id) || empty($this->merchant_key)) {
            $is_available = false;
        }
        
        // Additional checks for shipping country if needed
        if (WC()->cart && WC()->cart->needs_shipping()) {
            $is_available = $is_available && $this->supports_shipping_country(WC()->customer->get_shipping_country());
        }
    
        return $is_available;
    }
    
    /**
     * Check if shipping country is supported
     */
    protected function supports_shipping_country($country) {
        $supported_countries = array('ID'); // Default to only support Indonesia
        return in_array($country, $supported_countries);
    }
    
    /**
     * Get formatted private key
     */
    protected function get_formatted_private_key() {
        $private_key = $this->private_key;
        
        // Remove any whitespace or newline characters
        $private_key = preg_replace('/\s+/', '', $private_key);
        
        // Check if the key is already correctly formatted
        if (strpos($private_key, '-----BEGIN RSA PRIVATE KEY-----') === 0) {
            return $private_key;
        }
        
        // Format the key
        $formatted_key = "-----BEGIN RSA PRIVATE KEY-----\n" .
            chunk_split($private_key, 64, "\n") .
            "-----END RSA PRIVATE KEY-----";
        
        // Verify the key
        $key_resource = openssl_pkey_get_private($formatted_key);
        if ($key_resource === false) {
            error_log("Invalid private key: " . openssl_error_string());
            return false;
        }
        openssl_free_key($key_resource);
        
        return $formatted_key;
    }
    protected function get_access_token() {
        error_log("Starting get_access_token process");
        
        $X_CLIENT_KEY = $this->merchant_id;
        $timestamp = $this->generate_formatted_timestamp();
        $stringToSign = $X_CLIENT_KEY . "|" . $timestamp;
        
        $privatekey = "-----BEGIN RSA PRIVATE KEY-----\r\n" .
            $this->private_key . "\r\n" .
            "-----END RSA PRIVATE KEY-----";
        
        $binary_signature = "";
        $pKey = openssl_pkey_get_private($privatekey);
        
        if ($pKey === false) {
            error_log("Failed to get private key: " . openssl_error_string());
            throw new Exception("Invalid private key");
        }
        
        $sign_result = openssl_sign($stringToSign, $binary_signature, $pKey, OPENSSL_ALGO_SHA256);
        
        if ($sign_result === false) {
            error_log("Failed to create signature: " . openssl_error_string());
            throw new Exception("Failed to create signature");
        }
        
        $signature = base64_encode($binary_signature);
        
        $jsonData = array(
            "grantType" => "client_credentials",
            "additionalInfo" => ""
        );
        
        $jsonDataEncode = json_encode($jsonData);
        
        $requestToken = $this->api_endpoints['access_token'];
        
        $args = array(
            'method'  => 'POST',
            'timeout' => 45,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-SIGNATURE'  => $signature,
                'X-CLIENT-KEY' => $X_CLIENT_KEY,
                'X-TIMESTAMP'  => $timestamp
            ),
            'body'    => $jsonDataEncode,
        );
        
        $response = wp_remote_post($requestToken, $args);
        
        if (is_wp_error($response)) {
            error_log("Error in get_access_token: " . $response->get_error_message());
            throw new Exception($response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response));
        error_log("Access token response: " . json_encode($body));
        
        if (!isset($body->accessToken)) {
            error_log("Invalid access token response: " . json_encode($body));
            throw new Exception(__('Invalid access token response', 'nicepay-wc'));
        }
        
        error_log("Successfully obtained access token");
        
        return $body->accessToken;
    }

    protected function get_payment_status_description($status) {
        // Default implementation, can be overridden
        switch ($status) {
            case 'pending':
                return __('Pending payment', 'nicepay-wc');
            case 'on-hold':
                return __('Awaiting payment confirmation', 'nicepay-wc');
            case 'processing':
                return __('Payment received, processing order', 'nicepay-wc');
            case 'completed':
                return __('Payment completed', 'nicepay-wc');
            case 'cancelled':
                return __('Order cancelled', 'nicepay-wc');
            case 'failed':
                return __('Payment failed', 'nicepay-wc');
            default:
                return ucfirst($status);
        }
    }

    
    /**
     * Generate signature for NICEPay API
     */
    protected function generate_signature($string_to_sign, $use_hmac = false) {
        if ($use_hmac) {
            return hash_hmac("sha512", $string_to_sign, $this->merchant_key, true);
        } else {
            $private_key = $this->get_formatted_private_key();
            
            if (!$private_key) {
                throw new Exception("Invalid private key format");
            }

            $binary_signature = "";
            $result = openssl_sign($string_to_sign, $binary_signature, $private_key, OPENSSL_ALGO_SHA256);
            
            if ($result === false) {
                throw new Exception("Failed to generate signature: " . openssl_error_string());
            }
            
            return $binary_signature;
        }
    }
    
    /**
     * Generate random external ID for requests
     */
    protected function generate_external_id() {
        return date('YmdHis') . rand(1000, 9999);
    }
    
    /**
     * Abstract methods that must be implemented by child classes
     */
    abstract protected function create_virtual_account($order, $access_token, $additional_data);
    abstract protected function handle_callback();
    abstract protected function thankyou_page($order_id);
}