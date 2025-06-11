<?php
/**
 * NICEPAY E-Wallet Payment Gateway
 *
 * Provides an E-Wallet Payment Gateway for NICEPAY.
 *
 * @class       WC_Gateway_Nicepay_Ewallet
 * @extends     WC_Payment_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * NICEPAY E-wallet Payment Gateway
 */
class WC_Gateway_Nicepay_Ewallet extends WC_Payment_Gateway {
    protected $instructions;
    protected $environment;
    protected $api_endpoints;

    /**
     * Constructor for the gateway
     */
    public function __construct() {
        $this->id                 = 'nicepay_ewallet';
        $this->icon               = apply_filters('woocommerce_nicepay_ewallet_icon', NICEPAY_WC_PLUGIN_URL . '/assets/images/ewallet1.png');
        $this->has_fields         = true;
        $this->method_title       = __('NICEPAY E-wallet', 'nicepay-wc');
        $this->method_description = __('Allows payments using NICEPAY E-wallet like OVO, DANA, LinkAja, and ShopeePay.', 'nicepay-wc');

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        $this->register_scheduled_hooks();
        $this->environment = $this->get_option('environment', 'sandbox');

        $this->api_endpoints = $this->get_api_endpoints();

        // Define user set variables
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');

        // Check if we're using block checkout or classic checkout
        if ($this->get_option('enable_blocks') === 'classic') {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_classic_mode'));
        } else {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_blocks_mode'));
        }

        // Actions
        add_action('wp_ajax_set_nicepay_mitra', array($this, 'handle_set_nicepay_mitra'));
        add_action('wp_ajax_nopriv_set_nicepay_mitra', array($this, 'handle_set_nicepay_mitra'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_settings_save_' . $this->id, array($this, 'update_api_endpoints'));
        add_action('woocommerce_api_wc_gateway_nicepay_ewallet', array($this, 'handle_callback'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'handle_return_url'));
        add_action('woocommerce_api_nicepay_linkaja_process', array($this, 'process_linkaja_payment'));

        $this->supports = [
            'products',
            'refunds',
        ];
        error_log("NICEPAY E-Wallet gateway initialized");
    }

    /**
     * Enqueue scripts and styles for classic checkout
     */
    public function enqueue_classic_mode() {
        if (!is_checkout()) {
            return;
        }

        ?>
        <style>
            .nicepay-ewallet-container {
                margin: 15px 0;
                padding: 15px;
                background: #f8f8f8;
                border-radius: 4px;
            }
            .nicepay-ewallet-header {
                margin-bottom: 15px;
                text-align: center;
                padding: 10px 0;
            }
            .nicepay-ewallet-icon {
                max-height: 150px;
                width: auto;
                display: inline-block;
                margin: 10px 0;
            }
            .nicepay-ewallet-select {
                margin: 10px 0;
            }
            .nicepay-ewallet-select label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
            .nicepay-ewallet-logos {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 15px; /* Spacing antar logo */
                margin-bottom: 20px;
            }
            .nicepay-ewallet-logos img {
                height: 30px; /* Ukuran untuk logo individu */
                width: auto;
            }
            .nicepay-ewallet-select select {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
        </style>
        <?php

        // Enqueue JS
        wp_enqueue_script(
            'nicepay-classic-checkout',
            NICEPAY_WC_PLUGIN_URL . '/assets/js/ewallet-classic.js',
            array('jquery'),
            NICEPAY_WC_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'nicepay-classic-checkout',
            'nicepayData',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'pluginUrl' => NICEPAY_WC_PLUGIN_URL,
                'enabled_mitra' => $this->get_ewallet_options(),
                'nonce' => wp_create_nonce('nicepay-ewallet-nonce')
            )
        );
    }

    /**
     * Enqueue scripts and styles for blocks checkout
     */
    public function enqueue_blocks_mode() {
        $version = date('YmdHis');
        
        // Enqueue CSS
        wp_enqueue_style(
            'nicepay-ewallet-style',
            NICEPAY_WC_PLUGIN_URL . '/assets/css/nicepay.css',
            [],
            $version
        );

        // Register blocks script
        wp_register_script(
            'nicepay-ewallet-blocks-integration',
            NICEPAY_WC_PLUGIN_URL . '/assets/js/ewallet-blok.js',
            array('wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wc-blocks-registry', 'jquery'),
            NICEPAY_WC_VERSION,
            true
        );
        error_log("Localizing ewallet script with data: " . print_r(array(
            'enabled_mitra' => $this->get_ewallet_options(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nicepay-ewallet-nonce'),
            'pluginUrl' => NICEPAY_WC_PLUGIN_URL,
            'isEwallet' => true
        ), true));
        // Localize script
        wp_localize_script(
            'nicepay-ewallet-blocks-integration',
            'nicepayEwalletData',
            array(
                'enabled_mitra' => $this->get_ewallet_options(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('nicepay-ewallet-nonce'),
                'pluginUrl' => NICEPAY_WC_PLUGIN_URL,
                'isEwallet' => true
            )
        );

        // Enqueue blocks script
        wp_enqueue_script('nicepay-ewallet-blocks-integration');
        error_log("Enqueued NICEPay E-Wallet blocks checkout scripts");
    }

    /**
     * Update API endpoints based on environment
     */
    public function update_api_endpoints() {
        $this->environment = $this->get_option('environment', 'sandbox');
        $this->api_endpoints = $this->get_api_endpoints();
    }

    /**
     * Get API endpoints based on environment
     */
    private function get_api_endpoints() {
        $environment = $this->get_option('environment', 'sandbox');
        error_log("Current environment setting: " . $environment);

        $base_url = ($environment === 'production')  
            ? 'https://www.nicepay.co.id'
            : 'https://dev.nicepay.co.id';
        
        return [
            'access_token'     => $base_url . '/nicepay/v1.0/access-token/b2b',
            'registration'     => $base_url . '/nicepay/api/v1.0/debit/payment-host-to-host',
            'check_status_url' => $base_url . '/nicepay/api/v1.0/debit/status',
        ];
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'nicepay-wc'),
                'type'    => 'checkbox',
                'label'   => __('Enable NICEPAY E-wallet Payment', 'nicepay-wc'),
                'default' => 'no'
            ),
            'title' => array(
                'title'       => __('Title', 'nicepay-wc'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'nicepay-wc'),
                'default'     => __('NICEPAY E-wallet', 'nicepay-wc'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'nicepay-wc'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'nicepay-wc'),
                'default'     => __('Pay with e-wallet via NICEPAY (OVO, DANA, LinkAja, ShopeePay)', 'nicepay-wc'),
                'desc_tip'    => true,
            ),
            'enable_blocks' => array(
                'title'       => __('Checkout Mode', 'nicepay-wc'),
                'type'        => 'select',
                'description' => __('Select checkout mode. Block checkout is for modern WooCommerce checkout, while Classic is for traditional checkout.', 'nicepay-wc'),
                'default'     => 'classic',
                'options'     => array(
                    'classic' => __('Classic Checkout / Element Checkout (Non-Blocks)', 'nicepay-wc'),
                    'blocks'  => __('Block Checkout', 'nicepay-wc')
                )
            ),
            'environment' => array(
                'title'       => __('Environment', 'nicepay-wc'),
                'type'        => 'select',
                'desc_tip'    => true,
                'description' => __('Select the NICEPAY environment.', 'nicepay-wc'),
                'default'     => 'sandbox',
                'options'     => array(
                    'sandbox'    => __('Sandbox / Development', 'nicepay-wc'),
                    'production' => __('Production', 'nicepay-wc'),
                ),
            ),
            'X-CLIENT-KEY' => array(
                'title' => __('Merchant ID', 'nicepay-wc'),
                'type' => 'text',
                'description' => __('<small>Isikan dengan Merchant ID dari NICEPAY</small>.', 'nicepay-wc'),
                'default' => 'IONPAYTEST',
            ),
            'CHANNEL-ID' => array(
                'title' => __('Channel ID', 'nicepay-wc'),
                'type' => 'text',
                'description' => __('<small>Isikan dengan Channel ID dari NICEPAY</small>.', 'nicepay-wc'),
                'default' => 'IONPAYTEST01',
            ),
            'client_secret' => array(
                'title'       => __('Client Key', 'nicepay-wc'),
                'type'        => 'text',
                'description' => __('Enter your NICEPAY Client Key.', 'nicepay-wc'),
                'default'     => '33F49GnCMS1mFYlGXisbUDzVf2ATWCl9k3R++d5hDd3Frmuos/XLx8XhXpe+LDYAbpGKZYSwtlyyLOtS/8aD7A==',
                'desc_tip'    => true,
            ),
            'merchant_key' => array(
                'title'       => __('Merchant Key', 'nicepay-wc'),
                'type'        => 'text',
                'description' => __('Enter your NICEPAY Merchant Key.', 'nicepay-wc'),
                'default'     => '33F49GnCMS1mFYlGXisbUDzVf2ATWCl9k3R',
            ),
            'private_key' => array(
                'title'       => __('Private Key', 'nicepay-wc'),
                'type'        => 'textarea',
                'description' => __('Enter your NICEPAY Private Key.', 'nicepay-wc'),
                'default'     => 'MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAInJe1G22R2fMchIE6BjtYRqyMj6lurP/zq6vy79WaiGKt0Fxs4q3Ab4ifmOXd97ynS5f0JRfIqakXDcV/e2rx9bFdsS2HORY7o5At7D5E3tkyNM9smI/7dk8d3O0fyeZyrmPMySghzgkR3oMEDW1TCD5q63Hh/oq0LKZ/4Jjcb9AgMBAAECgYA4Boz2NPsjaE+9uFECrohoR2NNFVe4Msr8/mIuoSWLuMJFDMxBmHvO+dBggNr6vEMeIy7zsF6LnT32PiImv0mFRY5fRD5iLAAlIdh8ux9NXDIHgyera/PW4nyMaz2uC67MRm7uhCTKfDAJK7LXqrNVDlIBFdweH5uzmrPBn77foQJBAMPCnCzR9vIfqbk7gQaA0hVnXL3qBQPMmHaeIk0BMAfXTVq37PUfryo+80XXgEP1mN/e7f10GDUPFiVw6Wfwz38CQQC0L+xoxraftGnwFcVN1cK/MwqGS+DYNXnddo7Hu3+RShUjCz5E5NzVWH5yHu0E0Zt3sdYD2t7u7HSr9wn96OeDAkEApzB6eb0JD1kDd3PeilNTGXyhtIE9rzT5sbT0zpeJEelL44LaGa/pxkblNm0K2v/ShMC8uY6Bbi9oVqnMbj04uQJAJDIgTmfkla5bPZRR/zG6nkf1jEa/0w7i/R7szaiXlqsIFfMTPimvRtgxBmG6ASbOETxTHpEgCWTMhyLoCe54WwJATmPDSXk4APUQNvX5rr5OSfGWEOo67cKBvp5Wst+tpvc6AbIJeiRFlKF4fXYTb6HtiuulgwQNePuvlzlt2Q8hqQ==',
                'desc_tip'    => true,
            ),
            'mitra_options' => array(
                'title'       => __('E-wallet Options', 'nicepay-wc'),
                'type'        => 'title',
                'description' => __('Select which e-wallets you want to enable.', 'nicepay-wc'),
            ),
            'enable_ovo' => array(
                'title'   => __('OVO', 'nicepay-wc'),
                'type'    => 'checkbox',
                'label'   => __('Enable OVO Payment', 'nicepay-wc'),
                'default' => 'yes'
            ),
            'enable_dana' => array(
                'title'   => __('DANA', 'nicepay-wc'),
                'type'    => 'checkbox',
                'label'   => __('Enable DANA Payment', 'nicepay-wc'),
                'default' => 'yes'
            ),
            'enable_linkaja' => array(
                'title'   => __('LinkAja', 'nicepay-wc'),
                'type'    => 'checkbox',
                'label'   => __('Enable LinkAja Payment', 'nicepay-wc'),
                'default' => 'yes'
            ),
            'enable_shopeepay' => array(
                'title'   => __('ShopeePay', 'nicepay-wc'),
                'type'    => 'checkbox',
                'label'   => __('Enable ShopeePay Payment', 'nicepay-wc'),
                'default' => 'yes'
            ),
        );
    }

    /**
     * Get available e-wallet options
     */
    public function get_ewallet_options() {
        $available_options = array();

        if ($this->get_option('enable_ovo') === 'yes') {
            $available_options[] = array('value' => 'OVOE', 'label' => 'OVO');
        }
        if ($this->get_option('enable_dana') === 'yes') {
            $available_options[] = array('value' => 'DANA', 'label' => 'DANA');
        }
        if ($this->get_option('enable_linkaja') === 'yes') {
            $available_options[] = array('value' => 'LINK', 'label' => 'LINK AJA');
        }
        if ($this->get_option('enable_shopeepay') === 'yes') {
            $available_options[] = array('value' => 'ESHP', 'label' => 'ShopeePay');
        }

        error_log("Available e-wallet options: " . print_r($available_options, true));
        return $available_options;
    }

    /**
     * Handle the Ajax request to set the selected mitra
     */
    public function handle_set_nicepay_mitra() {
        check_ajax_referer('nicepay-ewallet-nonce', 'nonce');
        
        if (!isset($_POST['mitra_code'])) {
            wp_send_json_error('Mitra not specified');
        }

        $mitra = sanitize_text_field($_POST['mitra_code']);
        WC()->session->set('nicepay_selected_mitra', $mitra);
        
        wp_send_json_success('Mitra selection saved');
    }

    /**
     * Check if this gateway is available for use
     */
    public function is_available() {
        $is_available = ('yes' === $this->enabled);

        if ($is_available) {
            $available_mitra = $this->get_ewallet_options();
            if (empty($available_mitra)) {
                error_log('No e-wallet options are enabled');
                return false;
            }
        }
        
        if (WC()->cart && WC()->cart->needs_shipping()) {
            $is_available = $is_available && $this->supports_shipping_country(WC()->customer->get_shipping_country());
        }

        return $is_available;
    }

    /**
     * Check if customer shipping country is supported
     */
    private function supports_shipping_country($country) {
        $supported_countries = array('ID'); 
        return in_array($country, $supported_countries);
    }

    /**
     * Output payment fields
     */
    public function payment_fields() {
        if ($this->get_option('enable_blocks') === 'classic') {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }
            
            $selected_mitra = WC()->session->get('nicepay_selected_mitra');
            
            ?>
            <div class="nicepay-ewallet-container">
                <!-- Logo container -->
                <div class="nicepay-ewallet-header">
                    <div class="nicepay-ewallet-logos">
                        <img src="<?php echo NICEPAY_WC_PLUGIN_URL; ?>/assets/images/ewallet1.png" 
                             alt="E-wallet Logo" 
                             class="nicepay-ewallet-icon">
                    </div>
                </div>
                
                <!-- Selector container -->
                <div class="nicepay-ewallet-select">
                    <label for="nicepay-ewallet-select">Pilih E-wallet:</label>
                    <select name="nicepay_mitra" id="nicepay-ewallet-select">
                        <option value="">Pilih E-wallet</option>
                        <?php foreach ($this->get_ewallet_options() as $mitra): ?>
                            <option value="<?php echo esc_attr($mitra['value']); ?>" 
                                    <?php selected($selected_mitra, $mitra['value']); ?>>
                                <?php echo esc_html($mitra['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Process the payment
     */
    public function process_payment($order_id) {
        $checkout_mode = $this->get_option('enable_blocks', 'classic');
        
        if ($checkout_mode === 'classic') {
            return $this->process_classic_payment($order_id);
        } else {
            return $this->process_blocks_payment($order_id);
        }
    }

    /**
     * Process payment for blocks checkout
     */
    public function process_blocks_payment($order_id) {
        error_log("Starting process_blocks_payment for order $order_id");
        error_log("POST data received: " . print_r($_POST, true));
        error_log("Session data: " . print_r(WC()->session->get_session_data(), true));
        
        $order = wc_get_order($order_id);
        $selected_mitra = WC()->session->get('nicepay_selected_mitra');
        error_log("Selected mitra from session: " . $selected_mitra);

        if (empty($selected_mitra)) {
            wc_add_notice(__('Please select an e-wallet payment method.', 'nicepay-wc'), 'error');
            return array(
                'result'   => 'failure',
                'redirect' => '',
            );
        }

        try {
            $access_token = $this->get_access_token();
            $ewallet_data = $this->create_registration($order, $access_token);
            
            if (!isset($ewallet_data['responseCode'])) {
                throw new Exception(__('Invalid response from payment gateway', 'nicepay-wc'));
            }
            error_log("E-wallet registration response: " . print_r($ewallet_data, true));

            if ($ewallet_data['responseCode'] === '2005400' && $ewallet_data['responseMessage'] === 'Successful') {
                // Simpan data response ke order meta
                $order->update_meta_data('_nicepay_reference_no', $ewallet_data['referenceNo']);
                $order->update_meta_data('_nicepay_partner_reference_no', $ewallet_data['partnerReferenceNo']);
                $order->update_meta_data('_nicepay_mitra', $selected_mitra);
                $order->save();

                // Empty cart
                WC()->cart->empty_cart();
                
                // Return success with redirect URL
                if ($selected_mitra === 'OVOE') {
                    // Untuk OVO, check status setelah mendapat response sukses
                    error_log("Checking payment status for OVO payment...");
                    $status_response = $this->check_payment_status($order, $ewallet_data['referenceNo']);
                    error_log("Status check response: " . print_r($status_response, true));

                    // Update order status berdasarkan response check status
                    $this->update_order_status($order, $status_response);

                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                } elseif ($selected_mitra === 'LINK' && isset($ewallet_data['additionalInfo']['redirectToken'])) {
                    error_log("Processing LinkAja payment...");
                    
                    // Ensure correct URL format
                    $redirectUrl = $ewallet_data['webRedirectUrl'] . "?Message=" . $ewallet_data['additionalInfo']['redirectToken'];
                    error_log("LinkAja Redirect URL: " . $redirectUrl);
                    
                    $order->update_status('pending', sprintf(
                        __('Menunggu pembayaran %s', 'nicepay-wc'),
                        $selected_mitra
                    ));
                    
                    // Save minimal required data
                    $order->update_meta_data('_nicepay_reference_no', $ewallet_data['referenceNo']);
                    $order->update_meta_data('_nicepay_partner_reference_no', $ewallet_data['partnerReferenceNo']);
                    $order->update_meta_data('_nicepay_mitra', $selected_mitra);
                    $order->save();
                    WC()->cart->empty_cart();

                    return array(
                        'result' => 'success',
                        'redirect' => $redirectUrl
                    );
                } elseif (isset($ewallet_data['webRedirectUrl'])) {
                    // Untuk DANA, Shopee Pay
                    $order->update_meta_data('_nicepay_redirect_url', $ewallet_data['webRedirectUrl']);
                    $order->update_status('pending', sprintf(
                        __('Menunggu pembayaran %s', 'nicepay-wc'),
                        $selected_mitra
                    ));
                    $order->save();

                    return array(
                        'result' => 'success',
                        'redirect' => $ewallet_data['webRedirectUrl']
                    );
                }
            }
            
            // Jika response code tidak sesuai
            throw new Exception(sprintf(
                __('Payment gateway error: %s', 'nicepay-wc'),
                $ewallet_data['responseMessage'] ?? 'Unknown error'
            ));

        } catch (Exception $e) {
            wc_add_notice(__('Payment error:', 'woocommerce') . ' ' . $e->getMessage(), 'error');
            error_log("Payment error in process_payment: " . $e->getMessage());
            return array(
                'result'   => 'failure',
                'redirect' => '',
            );
        }
    }

    /**
     * Process LinkAja payment with redirect
     */
    public function process_linkaja_payment() {
        if (!isset($_GET['url']) || !isset($_GET['token'])) {
            wp_die('Invalid request');
        }

        $url = sanitize_text_field($_GET['url']);
        $token = sanitize_text_field($_GET['token']);

        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Processing LinkAja Payment</title>
            <style>
                body { 
                    font-family: Arial, sans-serif;
                    text-align: center;
                    padding: 20px;
                    background: #f5f5f5;
                    margin: 0;
                }
                .container {
                    max-width: 500px;
                    margin: 40px auto;
                    background: white;
                    padding: 30px;
                    border-radius: 10px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .spinner {
                    display: inline-block;
                    width: 50px;
                    height: 50px;
                    margin: 20px auto;
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #ff0000;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                h2 {
                    color: #333;
                    margin-bottom: 20px;
                }
                p {
                    color: #666;
                    margin: 15px 0;
                }
                button {
                    background: #ff0000;
                    color: white;
                    border: none;
                    padding: 12px 25px;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 16px;
                    margin-top: 20px;
                }
                button:hover {
                    background: #e60000;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Menghubungkan ke LinkAja</h2>
                <div class="spinner"></div>
                <p>Mohon tunggu, Anda akan diarahkan ke halaman pembayaran LinkAja...</p>
                <form id="linkaja_form" method="POST" action="<?php echo esc_url($url); ?>">
                    <input type="hidden" name="redirectToken" value="<?php echo esc_attr($token); ?>">
                </form>
                <script>
                    // Submit form setelah semua konten dimuat
                    window.onload = function() {
                        setTimeout(function() {
                            document.getElementById('linkaja_form').submit();
                        }, 1000);
                    };
                </script>
                <noscript>
                    <p>Jika Anda tidak dialihkan secara otomatis, silakan klik tombol di bawah ini:</p>
                    <button type="submit" form="linkaja_form">Lanjutkan ke LinkAja</button>
                </noscript>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Generate formatted timestamp
     */
    // private function generate_formatted_timestamp() {
    //     $date = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    //     return $date->format('Y-m-d\TH:i:sP');
    // }

    /**
     * Get access token from NICEPAY
     */
    private function get_access_token() {
        error_log("Starting get_access_token process");

        $X_CLIENT_KEY = $this->get_option('X-CLIENT-KEY');
        $timestamp = $this->generate_formatted_timestamp();
        $stringToSign = $X_CLIENT_KEY . "|" . $timestamp;

        error_log("X_CLIENT_KEY: " . $X_CLIENT_KEY);
        error_log("Timestamp: " . $timestamp);
        error_log("StringToSign: " . $stringToSign);
        
        $privatekey = "-----BEGIN RSA PRIVATE KEY-----\r\n" .
            $this->get_option('private_key') . "\r\n" .
            "-----END RSA PRIVATE KEY-----";
        
        error_log("Private Key Structure: " . substr($privatekey, 0, 100) . "...");
        $binary_signature = "";
        $pKey = openssl_pkey_get_private($privatekey);
        
        if ($pKey === false) {
            $error = openssl_error_string();
            error_log("Failed to get private key. OpenSSL Error: " . $error);
            throw new Exception("Invalid private key: " . $error);
        }
        
        $sign_result = openssl_sign($stringToSign, $binary_signature, $pKey, OPENSSL_ALGO_SHA256);
        
        if ($sign_result === false) {
            $error = openssl_error_string();
            error_log("Failed to create signature. OpenSSL Error: " . $error);
            throw new Exception("Failed to create signature: " . $error);
        }
        
        $signature = base64_encode($binary_signature);
        error_log("Generated Signature: " . $signature);
        
        $jsonData = array(
            "grantType" => "client_credentials",
        );
        
        $jsonDataEncode = json_encode($jsonData);
        error_log("Request Body JSON: " . $jsonDataEncode);
        
        $requestToken = $this->api_endpoints['access_token'];
        error_log("Request URL: " . $requestToken);
        
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

        error_log("Full Request Headers: " . print_r($args['headers'], true));
        error_log("Full Request Body: " . print_r($args['body'], true));
        
        $response = wp_remote_post($requestToken, $args);
        
        if (is_wp_error($response)) {
            error_log("Error in get_access_token: " . $response->get_error_message());
            throw new Exception($response->get_error_message());
        }
        error_log("Response Code: " . wp_remote_retrieve_response_code($response));
        error_log("Response Headers: " . print_r(wp_remote_retrieve_headers($response), true));
        
        $body = json_decode(wp_remote_retrieve_body($response));
        error_log("Access token response: " . json_encode($body));
        
        if (!isset($body->accessToken)) {
            error_log("Invalid access token response: " . json_encode($body));
            throw new Exception(__('Invalid access token response', 'nicepay-wc'));
        }
        
        error_log("Successfully obtained access token");
        
        return $body->accessToken;
    }

    /**
     * Create payment registration with NICEPAY
     */
    private function create_registration($order, $access_token) {
        error_log("Starting create_registration for order " . $order->get_id());

        $X_CLIENT_KEY = $this->get_option('X-CLIENT-KEY');
        $secretClient = $this->get_option('client_secret');
        $X_TIMESTAMP = $this->generate_formatted_timestamp();
        $timestamp = date('YmdHis');
        $channel = $this->get_option('CHANNEL-ID');
        $external = $timestamp . rand(1000, 9999);
        error_log("secret client: " . $secretClient);
        $selected_mitra = WC()->session->get('nicepay_selected_mitra', '');
        error_log("Selected mitra: " . $selected_mitra);
        if (empty($selected_mitra)) {
            throw new Exception(__('No e-wallet selected. Please choose an e-wallet payment method.', 'nicepay-wc'));
        }

        $cart_items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $cart_items[] = array(
                "img_url" => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: "http://placeholder.com/image.jpg",
                "goods_name" => $item->get_name(),
                "goods_detail" => $product->get_short_description() ?: $item->get_name(),
                "goods_amt" => number_format($order->get_item_total($item, true), 2, '.', ''),
                "goods_quantity" => (string)$item->get_quantity()
            );
        }
        if ($order->get_shipping_total() > 0) {
            $cart_items[] = array(
                "img_url" => "http://placeholder.com/shipping.jpg",
                "goods_name" => "Shipping",
                "goods_detail" => "Delivery Fee",
                "goods_amt" => number_format($order->get_shipping_total() + $order->get_shipping_tax(), 2, '.', ''),
                "goods_quantity" => "1"
            );
        }
        $cart_data = array(
            "count" => (string)count($cart_items),
            "item" => $cart_items
        );
        $cart_data_json = json_encode($cart_data, JSON_UNESCAPED_SLASHES);
        error_log("Cart Data JSON: " . $cart_data_json);

        $newBody = [
            "partnerReferenceNo" => $order->get_id(),
            "merchantId" => $X_CLIENT_KEY,
            "subMerchantId" => "",
            "externalStoreId" => "",
            "validUpTo" => date('Y-m-d\TH:i:s\Z', strtotime('+1 day')),
            "pointOfInitiation" => "MOBILE_APP",
            "amount" => [
                "value" => number_format($order->get_total(), 2, '.', ''),
                "currency" => $order->get_currency()
            ],
            "urlParam" => [
                [
                    "url" => home_url('/wc-api/wc_gateway_nicepay_ewallet'),
                    "type" => "PAY_NOTIFY",
                    "isDeeplink" => "Y" 
                ],
                [
                    "url" => $this->get_return_url($order),
                    "type" => "PAY_RETURN",
                    "isDeeplink" => "Y" 
                ]
            ],
            "additionalInfo" => [
                "mitraCd" => $selected_mitra,
                "goodsNm" => $this->get_order_items_names($order),
                "billingNm" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                "billingPhone" => $order->get_billing_phone(),
                "customerEmail" => $order->get_billing_email(), 
                "cartData" => $cart_data_json, 
                "dbProcessUrl" => home_url('/wc-api/wc_gateway_nicepay_ewallet'),
                "callBackUrl" => $this->get_return_url($order),
                "msId" => ""
            ]
        ];
        error_log("Request body structure: " . print_r($newBody, true));

        $stringBody = json_encode($newBody, JSON_UNESCAPED_SLASHES);;
        $hashbody = strtolower(hash("SHA256", $stringBody));

        $strigSign = "POST:/api/v1.0/debit/payment-host-to-host:" . $access_token . ":" . $hashbody . ":" . $X_TIMESTAMP;
        $bodyHasing = hash_hmac("sha512", $strigSign, $secretClient, true);

        $args = array(
            'method'  => 'POST',
            'timeout' => 45,
            'headers' => array(
                'Content-Type'   => 'application/json',
                'X-SIGNATURE'    => base64_encode($bodyHasing),
                'X-CLIENT-KEY'   => $X_CLIENT_KEY,
                'X-TIMESTAMP'    => $X_TIMESTAMP,
                'Authorization'  => "Bearer " . $access_token,
                'CHANNEL-ID'     => $channel,
                'X-EXTERNAL-ID'  => $external,
                'X-PARTNER-ID'   => $X_CLIENT_KEY
            ),
            'body'    => $stringBody,
        );

        error_log("Request body for create_registration: " . $stringBody);
        error_log("Request headers for create_registration: " . print_r($args['headers'], true));

        $response = wp_remote_post($this->api_endpoints['registration'], $args);

        if (is_wp_error($response)) {
            error_log("Error in create_registration: " . $response->get_error_message());
            throw new Exception($response->get_error_message());
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        error_log("Create registration response: " . json_encode($response_body));

        if (!isset($response_body['responseCode']) || $response_body['responseCode'] !== '2005400') {
            throw new Exception(__('Failed to create registration: ' . 
                ($response_body['responseMessage'] ?? 'Unknown error'), 'nicepay-wc'));
        } 

        return $response_body;
    }

    /**
     * Get cart data
     */
    private function get_cart_data($order) {
        $cart_data = [
            "count" => $order->get_item_count(),
            "item" => []
        ];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $cart_data["item"][] = [
                "img_url" => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                "goods_name" => $item->get_name(),
                "goods_detail" => $product->get_short_description(),
                "goods_amt" => number_format($order->get_item_total($item, true), 2, '.', ''),
                "goods_quantity" => $item->get_quantity()
            ];
        }

        return $cart_data;
    }

    /**
     * Get order items names
     */
    private function get_order_items_names($order) {
        $item_names = array();
        foreach ($order->get_items() as $item) {
            $item_names[] = $item->get_name();
        }
        return implode(', ', $item_names);
    }

    /**
     * Check payment status with NICEPAY
     */
    private function check_payment_status($order, $reference_no) {
        error_log("Starting check_payment_status for reference_no: " . $reference_no);
    
        try {
            $access_token = $this->get_access_token();
            
            $X_CLIENT_KEY = $this->get_option('X-CLIENT-KEY');
            $secretClient = $this->get_option('client_secret');
            $X_TIMESTAMP = $this->generate_formatted_timestamp();
            $timestamp = date('YmdHis');
            $channel = $this->get_option('CHANNEL-ID');
            $external = $timestamp . rand(1000, 9999);
    
            $newBody = [
                "merchantId" => $X_CLIENT_KEY,
                "subMerchantId" => "",
                "originalPartnerReferenceNo" => $order->get_meta('_nicepay_partner_reference_no'),
                "originalReferenceNo" => $reference_no,
                "serviceCode" => "54",
                "transactionDate" => date('Y-m-d\TH:i:sP'), 
                "externalStoreId" => "",
                "amount" => [
                    "value" => number_format($order->get_total(), 2, '.', ''),
                    "currency" => $order->get_currency()
                ],
                "additionalInfo" => (object)[]
            ];
    
            $stringBody = json_encode($newBody, JSON_UNESCAPED_SLASHES);
            $hashbody = strtolower(hash("SHA256", $stringBody));
    
            error_log("Check status request body: " . $stringBody);
    
            $strigSign = "POST:/api/v1.0/debit/status:" . $access_token . ":" . $hashbody . ":" . $X_TIMESTAMP;
            $bodyHasing = hash_hmac("sha512", $strigSign, $secretClient, true);
    
            $args = array(
                'method'  => 'POST',
                'timeout' => 45,
                'headers' => array(
                    'Content-Type'   => 'application/json',
                    'X-SIGNATURE'    => base64_encode($bodyHasing),
                    'X-CLIENT-KEY'   => $X_CLIENT_KEY,
                    'X-TIMESTAMP'    => $X_TIMESTAMP,
                    'Authorization'  => "Bearer " . $access_token,
                    'CHANNEL-ID'     => $channel,
                    'X-EXTERNAL-ID'  => $external,
                    'X-PARTNER-ID'   => $X_CLIENT_KEY
                ),
                'body'    => $stringBody,
            );
    
            error_log("Check status request headers: " . print_r($args['headers'], true));
    
            $response = wp_remote_post($this->api_endpoints['check_status_url'], $args);
    
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
    
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            error_log("Check status response: " . json_encode($response_body));
    
            return $response_body;
    
        } catch (Exception $e) {
            error_log("Error checking payment status: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update order status based on response from NICEPAY
     */
    private function update_order_status($order, $status_response) {
        if (!isset($status_response['responseCode'])) {
            error_log("Invalid status response: missing responseCode");
            return false;
        }
    
        // Pastikan response sukses dan ada latestTransactionStatus
        if ($status_response['responseCode'] === '2005500' && isset($status_response['latestTransactionStatus'])) {
            $transaction_status = $status_response['latestTransactionStatus'];
            $status_desc = $status_response['transactionStatusDesc'] ?? '';
    
            error_log("Updating order status. Latest Transaction Status: " . $transaction_status);
            error_log("Transaction Status Description: " . $status_desc);
    
            switch ($transaction_status) {
                case '00': // Success
                    $order->payment_complete();
                    $order->add_order_note(sprintf(
                        __('Payment successful via NICEPAY. Reference: %s. Status: %s', 'nicepay-wc'),
                        $status_response['originalReferenceNo'],
                        $status_desc
                    ));
                    return true;
    
                case '03': // Pending
                    $order->update_status('pending', sprintf(
                        __('Payment pending. Status: %s', 'nicepay-wc'),
                        $status_desc
                    ));
                    return true;
    
                case '04': // Refund
                    $order->update_status('refunded', sprintf(
                        __('Payment refunded. Status: %s', 'nicepay-wc'),
                        $status_desc
                    ));
                    return true;
    
                case '06': // Failed
                    $order->update_status('failed', sprintf(
                        __('Payment failed. Status: %s', 'nicepay-wc'),
                        $status_desc
                    ));
                    return false;
    
                default:
                    error_log("Unknown transaction status code: " . $transaction_status);
                    $order->add_order_note(sprintf(
                        __('Unknown payment status received (%s). Status description: %s', 'nicepay-wc'),
                        $transaction_status,
                        $status_desc
                    ));
                    return false;
            }
        } else {
            error_log("Invalid or unsuccessful status response: " . print_r($status_response, true));
            return false;
        }
    }

    /**
     * Check scheduled payment status
     */
    public function check_scheduled_payment_status($order_id) {
        error_log("Running scheduled payment status check for order: " . $order_id);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("Order not found: " . $order_id);
            return;
        }
    
        $reference_no = $order->get_meta('_nicepay_reference_no');
        if (empty($reference_no)) {
            error_log("No reference number found for order: " . $order_id);
            return;
        }
    
        try {
            $status_response = $this->check_payment_status($order, $reference_no);
            $check_status_result = $this->update_order_status($order, $status_response);
            error_log("Scheduled payment status check result: " . ($check_status_result ? 'success' : 'pending/failed'));
            
            // If payment is still pending, schedule another check
            if (!$check_status_result && $order->get_status() === 'pending') {
                wp_schedule_single_event(time() + 300, 'check_nicepay_payment_status', array($order_id));
            }
        } catch (Exception $e) {
            error_log("Error in scheduled payment status check: " . $e->getMessage());
        }
    }
    
    /**
     * Register the scheduled action hook
     */
    public function register_scheduled_hooks() {
        add_action('check_nicepay_payment_status', array($this, 'check_scheduled_payment_status'));
    }

    /**
     * Handle return URL after payment
     */
    public function handle_return_url($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_status() !== 'pending') {
            return;
        }
    
        $reference_no = $order->get_meta('_nicepay_reference_no');
        $mitra = $order->get_meta('_nicepay_mitra');
    
        if (!empty($reference_no) && $mitra !== 'OVOE') {
            error_log("Checking payment status for order {$order_id} after redirect");
            try {
                $status_response = $this->check_payment_status($order, $reference_no);
                $this->update_order_status($order, $status_response);
            } catch (Exception $e) {
                error_log("Error checking payment status: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle callback from NICEPAY
     */
    public function handle_callback() {
        $raw_post = file_get_contents('php://input');
        error_log("Callback received: " . $raw_post);
        
        try {
            $data = json_decode($raw_post, true);
            if (!$data) {
                throw new Exception("Invalid callback data");
            }
            
            if (!isset($data['partnerReferenceNo']) || !isset($data['referenceNo'])) {
                throw new Exception("Missing required callback parameters");
            }
            
            $order_id = $data['partnerReferenceNo'];
            $reference_no = $data['referenceNo'];
            
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception("Order not found: " . $order_id);
            }
            
            // Verify that the reference number matches
            $stored_reference_no = $order->get_meta('_nicepay_reference_no');
            if ($stored_reference_no !== $reference_no) {
                throw new Exception("Reference number mismatch");
            }
            
            // Check transaction status
            if (isset($data['transactionStatus'])) {
                $transaction_status = $data['transactionStatus'];
                $status_desc = $data['transactionStatusDesc'] ?? '';
                
                switch ($transaction_status) {
                    case '00': // Success
                        $order->payment_complete();
                        $order->add_order_note(sprintf(
                            __('Payment successful via NICEPAY callback. Reference: %s. Status: %s', 'nicepay-wc'),
                            $reference_no,
                            $status_desc
                        ));
                        break;
                        
                    case '03': // Pending
                        $order->update_status('pending', sprintf(
                            __('Payment pending via callback. Status: %s', 'nicepay-wc'),
                            $status_desc
                        ));
                        break;
                        
                    case '04': // Refund
                        $order->update_status('refunded', sprintf(
                            __('Payment refunded via callback. Status: %s', 'nicepay-wc'),
                            $status_desc
                        ));
                        break;
                        
                    case '06': // Failed
                        $order->update_status('failed', sprintf(
                            __('Payment failed via callback. Status: %s', 'nicepay-wc'),
                            $status_desc
                        ));
                        break;
                        
                    default:
                        $order->add_order_note(sprintf(
                            __('Unknown payment status received via callback (%s). Status description: %s', 'nicepay-wc'),
                            $transaction_status,
                            $status_desc
                        ));
                        break;
                }
            }
            
            // Respond to the callback
            wp_send_json(array(
                'status' => 'OK',
                'message' => 'Callback processed successfully'
            ));
            
        } catch (Exception $e) {
            error_log("Error processing callback: " . $e->getMessage());
            wp_send_json_error(array(
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Process refund
     */
    // public function process_refund($order_id, $amount = null, $reason = '') {
    //     $order = wc_get_order($order_id);
    //     if (!$order) {
    //         return false;
    //     }

    //     $reference_no = $order->get_meta('_nicepay_reference_no');
    //     if (empty($reference_no)) {
    //         return false;
    //     }

    //     try {
    //         // Refund implementation would go here
    //         // This would involve calling the NICEPAY refund API
            
    //         // For now, just add a note
    //         $order->add_order_note(sprintf(
    //             __('Refund requested via WooCommerce. Amount: %s. Reason: %s', 'nicepay-wc'),
    //             wc_price($amount),
    //             $reason
    //         ));
            
    //         return true;
    //     } catch (Exception $e) {
    //         error_log("Error processing refund: " . $e->getMessage());
    //         return false;
    //     }
    // }
    //     update_meta_data('_nicepay_reference_no', $ewallet_data['referenceNo']);
    //                 $order->update_meta_data('_nicepay_partner_reference_no', $ewallet_data['partnerReferenceNo']);
    //                 $order->update_meta_data('_nicepay_mitra', $selected_mitra);
    //                 $order->save();
    //                 WC()->cart->empty_cart();

    //                 return array(
    //                     'result' => 'success',
    //                     'redirect' => $redirectUrl
    //                 );
    //             } elseif (isset($ewallet_data['webRedirectUrl'])) {
    //                 // Untuk DANA, Shopee Pay
    //                 $order->update_meta_data('_nicepay_redirect_url', $ewallet_data['webRedirectUrl']);
    //                 $order->update_status('pending', sprintf(
    //                     __('Menunggu pembayaran %s', 'nicepay-wc'),
    //                     $selected_mitra
    //                 ));
    //                 $order->save();

    //                 return array(
    //                     'result' => 'success',
    //                     'redirect' => $ewallet_data['webRedirectUrl']
    //                 );
    //             }
    //         }
            
    //         // Jika response code tidak sesuai
    //         throw new Exception(sprintf(
    //             __('Payment gateway error: %s', 'nicepay-wc'),
    //             $ewallet_data['responseMessage'] ?? 'Unknown error'
    //         ));

    //     } catch (Exception $e) {
    //         wc_add_notice(__('Payment error:', 'woocommerce') . ' ' . $e->getMessage(), 'error');
    //         error_log("Payment error in process_payment: " . $e->getMessage());
    //         return array(
    //             'result'   => 'failure',
    //             'redirect' => '',
    //         );
    //     }
    // }

    /**
     * Process payment for classic checkout
     */
    public function process_classic_payment($order_id) {
        error_log("Starting process_classic_payment for order $order_id");
        error_log("POST data received: " . print_r($_POST, true));
        
        $order = wc_get_order($order_id);
        $selected_mitra = sanitize_text_field($_POST['nicepay_mitra'] ?? '');

        if (empty($selected_mitra)) {
            wc_add_notice(__('Please select an e-wallet payment method.', 'nicepay-wc'), 'error');
            return array(
                'result'   => 'failure',
                'redirect' => '',
            );
        }
        WC()->session->set('nicepay_selected_mitra', $selected_mitra);

        try {
            $access_token = $this->get_access_token();
            $ewallet_data = $this->create_registration($order, $access_token);
            
            if (!isset($ewallet_data['responseCode'])) {
                throw new Exception(__('Invalid response from payment gateway', 'nicepay-wc'));
            }
            error_log("E-wallet registration response: " . print_r($ewallet_data, true));

            if ($ewallet_data['responseCode'] === '2005400' && $ewallet_data['responseMessage'] === 'Successful') {
                // Simpan data response ke order meta
                $order->update_meta_data('_nicepay_reference_no', $ewallet_data['referenceNo']);
                $order->update_meta_data('_nicepay_partner_reference_no', $ewallet_data['partnerReferenceNo']);
                $order->update_meta_data('_nicepay_mitra', $selected_mitra);
                $order->save();

                // Empty cart
                WC()->cart->empty_cart();
                
                // Return success with redirect URL
                if ($selected_mitra === 'OVOE') {
                    // Untuk OVO, check status setelah mendapat response sukses
                    error_log("Checking payment status for OVO payment...");
                    $status_response = $this->check_payment_status($order, $ewallet_data['referenceNo']);
                    error_log("Status check response: " . print_r($status_response, true));

                    // Update order status berdasarkan response check status
                    $this->update_order_status($order, $status_response);

                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                } elseif ($selected_mitra === 'LINK' && isset($ewallet_data['additionalInfo']['redirectToken'])) {
                    error_log("Processing LinkAja payment...");
                    
                    // Ensure correct URL format
                    $redirectUrl = $ewallet_data['webRedirectUrl'] . "?Message=" . $ewallet_data['additionalInfo']['redirectToken'];
                    error_log("LinkAja Redirect URL: " . $redirectUrl);
                    
                    $order->update_status('pending', sprintf(
                        __('Menunggu pembayaran %s', 'nicepay-wc'),
                        $selected_mitra
                    ));
                    
                    // Save minimal required data
                    $order->update_meta_data('_nicepay_reference_no', $ewallet_data['referenceNo']);
                    $order->update_meta_data('_nicepay_partner_reference_no', $ewallet_data['partnerReferenceNo']);
                    $order->update_meta_data('_nicepay_mitra', $selected_mitra);
                    $order->save();
                    WC()->cart->empty_cart();
    
                    return array(
                        'result' => 'success',
                        'redirect' => $redirectUrl
                    );
                } elseif (isset($ewallet_data['webRedirectUrl'])) {
                    // Untuk DANA, Shopee Pay
                    $order->update_meta_data('_nicepay_redirect_url', $ewallet_data['webRedirectUrl']);
                    $order->update_status('pending', sprintf(
                        __('Menunggu pembayaran %s', 'nicepay-ewallet-snap-gateway'),
                        $selected_mitra
                    ));
                    $order->save();
    
                    add_action('woocommerce_thankyou_' . $this->id, function($order_id) {
                        $order = wc_get_order($order_id);
                        if (!$order || $order->get_status() !== 'pending') {
                            return;
                        }
    
                        $reference_no = $order->get_meta('_nicepay_reference_no');
                        $mitra = $order->get_meta('_nicepay_mitra');
    
                        if (!empty($reference_no) && $mitra !== 'OVOE') {
                            error_log("Checking payment status for order {$order_id} after redirect");
                            try {
                                $status_response = $this->check_payment_status($order, $reference_no);
                                $this->update_order_status($order, $status_response);
                            } catch (Exception $e) {
                                error_log("Error checking payment status: " . $e->getMessage());
                            }
                        }
                    });
    
                    return array(
                        'result' => 'success',
                        'redirect' => $ewallet_data['webRedirectUrl']
                    );
                }
            }
            
            // Jika response code tidak sesuai
            throw new Exception(sprintf(
                __('Payment gateway error: %s', 'nicepay-ewallet-snap-gateway'),
                $ewallet_data['responseMessage'] ?? 'Unknown error'
            ));
    
        } catch (Exception $e) {
            wc_add_notice(__('Payment error:', 'woocommerce') . ' ' . $e->getMessage(), 'error');
            error_log("Payment error in process_payment: " . $e->getMessage());
            return array(
                'result'   => 'failure',
                'redirect' => '',
            );
        }
    }
            
    
    
    private function generate_formatted_timestamp() {
        $date = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        return $date->format('Y-m-d\TH:i:sP');
    }
    
    }