<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * NICEPay Virtual Account Gateway
 *
 * Provides a Virtual Account Payment Gateway for WooCommerce.
 *
 * @class     WC_Gateway_Nicepay_VA
 * @extends   WC_Nicepay_Payment_Gateway
 */
class WC_Gateway_Nicepay_VA extends WC_Nicepay_Payment_Gateway {
     /**
     * Gateway title
     *
     * @var string
     */
    public $title;
    
    /**
     * Gateway description
     *
     * @var string
     */
    public $description;
    
    /**
     * Payment instructions
     *
     * @var string
     */
    public $instructions;
        /**
     * API endpoint
     *
     * @var string
     */
    public $api_endpoints;
    public function __construct() {
        $this->id                 = 'nicepay_va';
        $this->icon               = NICEPAY_WC_PLUGIN_URL . 'assets/images/logobank.png';
        $this->method_title       = __('NICEPay Virtual Account', 'nicepay-wc');
        $this->method_description = __('Allow customers to pay using NICEPay Virtual Account payment methods.', 'nicepay-wc');

        $this->supports = [
            'products',
            'refunds',
        ];

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        error_log('VA Settings loaded: ' . print_r($this->settings, true));
        
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $this->environment  = $this->get_option('environment', 'sandbox');

        // Define user set variables
        $this->merchant_id  = $this->get_option('X-CLIENT-KEY', 'IONPAYTEST');
        $this->merchant_key = $this->get_option('client_secret', '33F49GnCMS1mFYlGXisbUDzVf2ATWCl9k3R++d5hDd3Frmuos/XLx8XhXpe+LDYAbpGKZYSwtlyyLOtS/8aD7A==');
        $this->channel_id   = $this->get_option('Channel_ID', 'IONPAYTEST01');
        $this->private_key  = $this->get_option('private_key', 'MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAInJe1G22R2fMchIE6BjtYRqyMj6lurP/zq6vy79WaiGKt0Fxs4q3Ab4ifmOXd97ynS5f0JRfIqakXDcV/e2rx9bFdsS2HORY7o5At7D5E3tkyNM9smI/7dk8d3O0fyeZyrmPMySghzgkR3oMEDW1TCD5q63Hh/oq0LKZ/4Jjcb9AgMBAAECgYA4Boz2NPsjaE+9uFECrohoR2NNFVe4Msr8/mIuoSWLuMJFDMxBmHvO+dBggNr6vEMeIy7zsF6LnT32PiImv0mFRY5fRD5iLAAlIdh8ux9NXDIHgyera/PW4nyMaz2uC67MRm7uhCTKfDAJK7LXqrNVDlIBFdweH5uzmrPBn77foQJBAMPCnCzR9vIfqbk7gQaA0hVnXL3qBQPMmHaeIk0BMAfXTVq37PUfryo+80XXgEP1mN/e7f10GDUPFiVw6Wfwz38CQQC0L+xoxraftGnwFcVN1cK/MwqGS+DYNXnddo7Hu3+RShUjCz5E5NzVWH5yHu0E0Zt3sdYD2t7u7HSr9wn96OeDAkEApzB6eb0JD1kDd3PeilNTGXyhtIE9rzT5sbT0zpeJEelL44LaGa/pxkblNm0K2v/ShMC8uY6Bbi9oVqnMbj04uQJAJDIgTmfkla5bPZRR/zG6nkf1jEa/0w7i/R7szaiXlqsIFfMTPimvRtgxBmG6ASbOETxTHpEgCWTMhyLoCe54WwJATmPDSXk4APUQNvX5rr5OSfGWEOo67cKBvp5Wst+tpvc6AbIJeiRFlKF4fXYTb6HtiuulgwQNePuvlzlt2Q8hqQ==');
        
        error_log('VA merchant_id set to: ' . $this->merchant_id);
        error_log('VA merchant_key set to: ' . (!empty($this->merchant_key) ? 'Filled value' : 'Empty'));
        error_log('VA channel_id set to: ' . $this->channel_id);
        error_log('VA private_key set to: ' . (!empty($this->private_key) ? 'Filled value' : 'Empty'));
    
        // $this->environment  = $this->get_option('environment', 'sandbox');
        parent::__construct();
        if ($this->environment === 'sandbox') {
            @ini_set('display_errors', 1);
            @ini_set('display_startup_errors', 1);
            @error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        }
        
        // Tambahkan action untuk AJAX
        add_action('wp_ajax_set_nicepay_bank', array($this, 'handle_set_nicepay_bank'));
        add_action('wp_ajax_nopriv_set_nicepay_bank', array($this, 'handle_set_nicepay_bank'));
        
        // Tambahkan action untuk halaman terima kasih
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        
        // Tambahkan action untuk callback dari NICEPay
        add_action('woocommerce_api_wc_gateway_nicepay_va', array($this, 'handle_callback'));
        
        // Tambahkan scripts
        if (is_checkout()) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        }

        error_log("NICEPay VA gateway initialized");
    }
    
    // public function log($message, $type = 'info') {
    //     if (!class_exists('NICEPay_Log_Manager')) {
    //         $debug_setting = $this->get_option('debug');
    //         $debug_enabled = ($debug_setting === 'yes' || $debug_setting === '1' || $debug_setting === true);
            
    //         if (!$debug_enabled) {
    //             return;
    //         }
    //     error_log('NICEPay_Log_Manager::log called with type: ' . $type);
    //     $log_entry = array(
    //         'time'    => current_time('mysql'),
    //         'type'    => $type,
    //         'message' => is_array($message) || is_object($message) ? json_encode($message) : $message
    //     );
        
    //     $logs = get_option('nicepay_va_debug_logs', array());
    //     $logs[] = $log_entry;
        
        
    //     if (count($logs) > 1000) {
    //         $logs = array_slice($logs, -1000);
    //     }
        
    //     update_option('nicepay_va_debug_logs', $logs);
    //     } else {
    //         NICEPay_Log_Manager::log($message, $type);
    //     }
    // }
    public function is_available() {
        // Debug logs
        error_log('NICEPay VA is_available check started');
        
        // Check the original availability
        $enabled = $this->get_option('enabled') === 'yes';
        
        // Log detailed information
        $merchant_id_set = !empty($this->merchant_id);
        $merchant_key_set = !empty($this->merchant_key);
    
    // Hasil akhir
    $is_available = $enabled && $merchant_id_set && $merchant_key_set;
    
    // Log detailed information
    error_log('NICEPay VA enabled setting: ' . ($enabled ? 'yes' : 'no'));
    error_log('NICEPay VA merchant_id: ' . $this->merchant_id);
    error_log('NICEPay VA merchant_key: ' . (!empty($this->merchant_key) ? 'set' : 'empty'));
    // Untuk debugging/testing, uncomment baris di bawah ini
    // return true;
    // return $is_available;
    return $enabled;
}

    /**
     * Register scripts to be used on the checkout page
     */
    public function enqueue_scripts() {
        // Check if we're on checkout page
        if (!is_checkout()) {
            return;
        }
        error_log('Enqueuing NICEPay VA scripts');
    
        // Enqueue CSS styles
        wp_enqueue_style(
            'nicepay-va-style',
            NICEPAY_WC_PLUGIN_URL . 'assets/css/nicepay.css',
            array(),
            NICEPAY_WC_VERSION
        );
    
        // Check if WooCommerce Blocks is being used
        $is_block_checkout = false;
        if (class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            // WooCommerce Blocks is available
            $is_block_checkout = true;
        }

        $blocks_file = NICEPAY_WC_PLUGIN_URL . 'assets/js/va-blok.js';
        $classic_file = NICEPAY_WC_PLUGIN_URL . 'assets/js/va-classic.js';
        error_log('Block JS path: ' . $blocks_file);
        error_log('Classic JS path: ' . $classic_file);
    
    
        if ($is_block_checkout) {
            // Enqueue Blocks mode JS
            wp_enqueue_script(
                'nicepay-va-blok',
                $blocks_file,
                array('jquery', 'wp-element', 'wp-hooks'),
                NICEPAY_WC_VERSION,
                true
            );
            $script_handle = 'nicepay-va-blok';
        } else {
            // Enqueue Classic mode JS
            wp_enqueue_script(
                'nicepay-va-classic',
                $classic_file,
                array('jquery'),
                NICEPAY_WC_VERSION,
                true
            );
            $script_handle = 'nicepay-va-classic';
        }
    
        // Localize script with data
        wp_localize_script(
            $script_handle,
            'nicepayData',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('nicepay-va-nonce'),
                'pluginUrl' => NICEPAY_WC_PLUGIN_URL,
                'enableVA' => get_option('nicepay_enable_va', 'yes')
            )
        );
        
        error_log('NICEPay VA scripts enqueued successfully');
    }

    /**
     * Setup form fields for the payment gateway
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'nicepay-wc'),
                'type'    => 'checkbox',
                'label'   => __('Enable NICEPay Virtual Account Payment', 'nicepay-wc'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Title', 'nicepay-wc'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'nicepay-wc'),
                'default'     => __('NICEPay Virtual Account', 'nicepay-wc'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'nicepay-wc'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'nicepay-wc'),
                'default'     => __('Pay with Virtual Account via NICEPay.', 'nicepay-wc'),
                'desc_tip'    => true,
            ),
            'instructions' => array(
                'title'       => __('Instructions', 'nicepay-wc'),
                'type'        => 'textarea',
                'description' => __('Instructions that will be added to the thank you page.', 'nicepay-wc'),
                'default'     => __('Please transfer the exact amount to the virtual account number provided. Your order will be processed after payment is confirmed.', 'nicepay-wc'),
                'desc_tip'    => true,
            ),
            'enable_blocks' => array(
            'title'       => __('Checkout Mode', 'woocommerce'),
            'type'        => 'select',
            'description' => __('Select checkout mode. Block checkout is for modern WooCommerce checkout, while Classic is for traditional checkout.', 'woocommerce'),
            'default'     => 'classic',
            'options'     => array(
                'classic' => __('Classic Checkout (Non-Blocks)', 'woocommerce'),
                'blocks'  => __('Block Checkout', 'woocommerce')
                )
            ),
            'environment' => array(
            'title'       => __('Environment', 'woocommerce'),
            'type'        => 'select',
            'desc_tip'    => true,
            'description' => __('Select the NICEPay environment.', 'woocommerce'),
            'default'     => 'sandbox',
            'options'     => array(
                'sandbox'    => __('Sandbox / Development', 'woocommerce'),
                'production' => __('Production', 'woocommerce'),
                ),
            ),
            'X-CLIENT-KEY' => array(
                'title' => __('Merchant ID / Client ID', 'woocommerce'),
                'type' => 'text',
                'description' => __('<small>Isikan dengan Merchant ID dari NICEPay</small>.', 'woocommerce'),
                'default' => 'IONPAYTEST',
            ),
            'client_secret' => array(
            'title'       => __('Client Secret Key', 'woocommerce'),
            'type'        => 'text',
            'description' => __('Enter your NICEPay Client Key.', 'woocommerce'),
            'default'     => '33F49GnCMS1mFYlGXisbUDzVf2ATWCl9k3R++d5hDd3Frmuos/XLx8XhXpe+LDYAbpGKZYSwtlyyLOtS/8aD7A==',
            'desc_tip'    => true,
        ),
        'Channel_ID' => array(
            'title'       => __('Channel ID', 'woocommerce'),
            'type'        => 'text',
            'description' => __('Enter your NICEPay Channel ID.', 'woocommerce'),
            'default'     => 'IONPAYTEST01',
            'desc_tip'    => true,
        ),

        'merchant_key' => array(
            'title'       => __('Merchant Key', 'woocommerce'),
            'type'        => 'text',
            'description' => __('Enter your NICEPay Merchant Key.', 'woocommerce'),
            'default'     => '33F49GnCMS1mFYlGXisbUDzVf2ATWCl9k3R2222222',
        ),
        'private_key' => array(
            'title'       => __('Private Key', 'woocommerce'),
            'type'        => 'textarea',
            'description' => __('Enter your NICEPay Private Key.', 'woocommerce'),
            'default'     => 'MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAInJe1G22R2fMchIE6BjtYRqyMj6lurP/zq6vy79WaiGKt0Fxs4q3Ab4ifmOXd97ynS5f0JRfIqakXDcV/e2rx9bFdsS2HORY7o5At7D5E3tkyNM9smI/7dk8d3O0fyeZyrmPMySghzgkR3oMEDW1TCD5q63Hh/oq0LKZ/4Jjcb9AgMBAAECgYA4Boz2NPsjaE+9uFECrohoR2NNFVe4Msr8/mIuoSWLuMJFDMxBmHvO+dBggNr6vEMeIy7zsF6LnT32PiImv0mFRY5fRD5iLAAlIdh8ux9NXDIHgyera/PW4nyMaz2uC67MRm7uhCTKfDAJK7LXqrNVDlIBFdweH5uzmrPBn77foQJBAMPCnCzR9vIfqbk7gQaA0hVnXL3qBQPMmHaeIk0BMAfXTVq37PUfryo+80XXgEP1mN/e7f10GDUPFiVw6Wfwz38CQQC0L+xoxraftGnwFcVN1cK/MwqGS+DYNXnddo7Hu3+RShUjCz5E5NzVWH5yHu0E0Zt3sdYD2t7u7HSr9wn96OeDAkEApzB6eb0JD1kDd3PeilNTGXyhtIE9rzT5sbT0zpeJEelL44LaGa/pxkblNm0K2v/ShMC8uY6Bbi9oVqnMbj04uQJAJDIgTmfkla5bPZRR/zG6nkf1jEa/0w7i/R7szaiXlqsIFfMTPimvRtgxBmG6ASbOETxTHpEgCWTMhyLoCe54WwJATmPDSXk4APUQNvX5rr5OSfGWEOo67cKBvp5Wst+tpvc6AbIJeiRFlKF4fXYTb6HtiuulgwQNePuvlzlt2Q8hqQ==',
            'desc_tip'    => true,
        ),
       'debug' => array(
            'title'       => __('Debug Mode', 'woocommerce'),
            'type'        => 'checkbox',
            'label'       => __('Enable debug logging', 'woocommerce'),
            'default'     => 'no',
            'description' => __('Log NICEPay API interactions for debugging purposes. Logs will be stored in the NICEPay Logs page.', 'woocommerce'),
            'desc_tip'    => true,
            'custom_attributes' => array(
            'data-debug-toggle' => 'true'
            )
        ),
        
       'log_retention' => array(
            'title'       => __('Log Retention', 'woocommerce'),
            'type'        => 'select',
            'description' => __('How long to keep debug logs before automatic deletion.', 'woocommerce'),
            'default'     => '7',
            'options'     => array(
                '1'  => __('1 day', 'woocommerce'),
                '7'  => __('7 days', 'woocommerce'),
            ),
        ),
            'bank_options' => array(
                'title'       => __('Available Banks', 'nicepay-wc'),
                'type'        => 'multiselect',
                'description' => __('Select which banks to enable for Virtual Account payments.', 'nicepay-wc'),
                'options'     => array(
                    'BMRI'     => __('Bank Mandiri', 'nicepay-wc'),
                    'BNIN'     => __('Bank BNI', 'nicepay-wc'),
                    'BRIN'     => __('Bank BRI', 'nicepay-wc'),
                    'BBBA'     => __('Bank Permata', 'nicepay-wc'),
                    'CENA'     => __('Bank BCA', 'nicepay-wc'),
                    'IBBK'     => __('Maybank', 'nicepay-wc'),
                    'BBBB'     => __('Bank Permata Syariah', 'nicepay-wc'),
                    'HNBN'     => __('Bank KEB Hana Indonesia', 'nicepay-wc'),
                    'BNIA'     => __('Bank CIMB', 'nicepay-wc'),
                    'BDIN'     => __('Bank Danamon', 'nicepay-wc'),
                    'PDJB'     => __('Bank BJB', 'nicepay-wc'),
                    'YUDB'     => __('Bank Neo Commerce (BNC)', 'nicepay-wc'),
                    'BDKI'     => __('Bank DKI', 'nicepay-wc')
                ),
                'default'     => array('BMRI', 'BNIN', 'CENA', 'BRIN')
            )
        );
    }

    /**
     * Get list of available banks for Virtual Account
     */
    public function get_bank_list() {
        $available_banks = $this->get_option('bank_options', array('BMRI', 'BNIN', 'CENA', 'BRIN'));
        $all_banks = array(
            array('code' => 'BMRI', 'name' => 'Bank Mandiri'),
            array('code' => 'BNIN', 'name' => 'Bank BNI'),
            array('code' => 'BRIN', 'name' => 'Bank BRI'),
            array('code' => 'BBBA', 'name' => 'Bank Permata'),
            array('code' => 'CENA', 'name' => 'Bank BCA'),
            array('code' => 'IBBK', 'name' => 'Maybank'),
            array('code' => 'BBBB', 'name' => 'Bank Permata Syariah'),
            array('code' => 'HNBN', 'name' => 'Bank KEB Hana Indonesia'),
            array('code' => 'BNIA', 'name' => 'Bank CIMB'),
            array('code' => 'BDIN', 'name' => 'Bank Danamon'),
            array('code' => 'PDJB', 'name' => 'Bank BJB'),
            array('code' => 'YUDB', 'name' => 'Bank Neo Commerce (BNC)'),
            array('code' => 'BDKI', 'name' => 'Bank DKI')
        );
        
        // Filter banks based on enabled options
        if (!empty($available_banks)) {
            return array_filter($all_banks, function($bank) use ($available_banks) {
                return in_array($bank['code'], $available_banks);
            });
        }
        
        return $all_banks;
    }
    
    /**
     * Get bank name from bank code
     */
    public function get_bank_name($bank_code) {
        $banks = $this->get_bank_list();
        foreach ($banks as $bank) {
            if ($bank['code'] === $bank_code) {
                return $bank['name'];
            }
        }
        return $bank_code;
    }

    /**
     * Handle AJAX request to set selected bank
     */
    public function handle_set_nicepay_bank() {
        ob_start();
        error_log("handle_set_nicepay_bank called" . print_r($_POST, true));
        try{
        if (isset($_POST['bank_code'])) {
            $bank_code = sanitize_text_field($_POST['bank_code']);
            $valid_banks = array_column($this->get_bank_list(), 'code');
            
            if (!in_array($bank_code, $valid_banks)) {
                error_log("Invalid bank code: " . $bank_code);
                wp_send_json_error('Invalid bank code');
                wp_die();
            }

            // Set ke session
            WC()->session->set('nicepay_selected_bank', $bank_code);
            error_log("Bank code saved to session: " . $bank_code);

            // Tambahkan ke order meta jika ada order yang sedang diproses
            if (WC()->session->get('order_awaiting_payment')) {
                $order_id = WC()->session->get('order_awaiting_payment');
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->update_meta_data('_nicepay_selected_bank', $bank_code);
                    $order->save();
                    error_log("Bank code saved to order meta: " . $bank_code);
                }
            }
            ob_clean();
            wp_send_json_success([
                'message' => 'Bank code saved: ' . $bank_code,
                'bank_code' => $bank_code
            ]);
        }else {
            // Bersihkan buffer output
            ob_clean();
            
            // Kirim respons error
            wp_send_json_error('Bank code not provided');
        }
    } catch (Exception $e) {
        // Bersihkan buffer output
        ob_clean();
        
        // Kirim respons error
        wp_send_json_error($e->getMessage());
    }
    
    // Pastikan untuk keluar
    wp_die();
}

    /**
     * Payment fields displayed on the checkout page
     */
    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
        
        $selected_bank = '';
        if (isset($_POST['nicepay_bank'])) {
            $selected_bank = sanitize_text_field($_POST['nicepay_bank']);
        }
        
        ?>
        <div class="nicepay-va-container">
            <div class="nicepay-va-header">
                <img src="<?php echo NICEPAY_WC_PLUGIN_URL . 'assets/images/logobank.png'; ?>" 
                     alt="Bank Logo" 
                     class="nicepay-va-bank-icon">
            </div>
            <div class="nicepay-va-bank-select">
                <label for="nicepay-bank-select"><?php _e('Select Bank:', 'nicepay-wc'); ?></label>
                <select name="nicepay_bank" id="nicepay-bank-select">
                    <option value=""><?php _e('Select Bank', 'nicepay-wc'); ?></option>
                    <?php foreach ($this->get_bank_list() as $bank): ?>
                        <option value="<?php echo esc_attr($bank['code']); ?>" 
                                <?php selected($selected_bank, $bank['code']); ?>>
                            <?php echo esc_html($bank['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <p class="nicepay-va-instruction">
                <?php _e('Please select a bank for your Virtual Account payment.', 'nicepay-wc'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Process the payment and return the result
     */
    public function process_payment($order_id) {
        error_log("Starting process_payment for order $order_id");
        $order = wc_get_order($order_id);
        
        // Get selected bank
        $selected_bank = isset($_POST['nicepay_bank']) ? 
            sanitize_text_field($_POST['nicepay_bank']) : 
            WC()->session->get('nicepay_selected_bank');
        
        error_log("Selected bank: " . ($selected_bank ?: 'Not set'));
        
        if (!$selected_bank) {
            wc_add_notice(__('Please select a bank for payment', 'nicepay-wc'), 'error');
            return array(
                'result'   => 'failure',
                'redirect' => '',
            );
        }
        
        // Save selected bank to session
        WC()->session->set('nicepay_selected_bank', $selected_bank);
        
        try {
            // Get access token
            $access_token = $this->get_access_token();
            
            // Create virtual account
            $va_data = $this->create_virtual_account($order, $access_token, $selected_bank);
            
            if (isset($va_data['virtualAccountData'])) {
                // Handle successful VA creation
                $this->handle_va_creation_response($order, $va_data);
                
                // Empty cart
                WC()->cart->empty_cart();
                
                // Return success
                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order),
                );
            } else {
                throw new Exception(__('Failed to create Virtual Account', 'nicepay-wc'));
            }
        } catch (Exception $e) {
            wc_add_notice(__('Payment error:', 'nicepay-wc') . ' ' . $e->getMessage(), 'error');
            error_log("Payment error: " . $e->getMessage());
            return array(
                'result'   => 'failure',
                'redirect' => '',
            );
        }
    }

    /**
     * Get NICEPay access token
     */
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

    /**
     * Create virtual account
     */
    protected function create_virtual_account($order, $access_token, $selected_bank) {
        error_log("Starting create_virtual_account for order " . $order->get_id());
        
        if (!$selected_bank) {
            throw new Exception(__('Please select a bank for payment', 'nicepay-wc'));
        }

        $X_CLIENT_KEY = $this->merchant_id;
        $secretClient = $this->merchant_key;
        $channel = $this->channel_id;
        $X_TIMESTAMP = $this->generate_formatted_timestamp();
        $timestamp = date('YmdHis');
        $external = $timestamp . rand(1000, 9999);
        
        $additionalInfo = [
            "bankCd" => $selected_bank,
            "goodsNm" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            "dbProcessUrl" => home_url('/wc-api/wc_gateway_nicepay_va'),
            "vacctValidDt" => date('Ymd', strtotime('+1 day')),
            "vacctValidTm" => date('His', strtotime('+1 day')),
            "msId" => "",
            "msFee" => "",
            "msFeeType" => "",
            "mbFee" => "",
            "mbFeeType" => ""
        ];
        
        $TotalAmount = [
            "value" => number_format($order->get_total(), 2, '.', ''),
            "currency" => $order->get_currency()
        ];
    
        $newBody = [
            "partnerServiceId" => "",
            "customerNo" => "",
            "virtualAccountNo" => "",
            "virtualAccountName" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            "trxId" => $order->get_id() . "",
            "totalAmount" => $TotalAmount,
            "additionalInfo" => $additionalInfo
        ];
    
        $stringBody = json_encode($newBody);
        $hashbody = strtolower(hash("SHA256", $stringBody));
    
        $strigSign = "POST:/api/v1.0/transfer-va/create-va:" . $access_token . ":" . $hashbody . ":" . $X_TIMESTAMP;
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
    
        error_log("Request body for create_virtual_account: " . $stringBody);
    
        $response = wp_remote_post($this->api_endpoints['create_va'], $args);
    
        if (is_wp_error($response)) {
            error_log("Error in create_virtual_account: " . $response->get_error_message());
            throw new Exception($response->get_error_message());
        }
    
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        error_log("Create virtual account response: " . json_encode($response_body));
    
        return $response_body;
    }

    /**
     * Handle successful virtual account creation
     */
    protected function handle_va_creation_response($order, $data) {
        if (isset($data['responseCode']) && $data['responseCode'] == "2002700") {
            $va_number = $data['virtualAccountData']['virtualAccountNo'];
            $txid_va = $data['virtualAccountData']['additionalInfo']['tXidVA'];
            $bank_code = $data['virtualAccountData']['additionalInfo']['bankCd'];
            $expiry_date = $data['virtualAccountData']['additionalInfo']['vacctValidDt'];
            $expiry_time = $data['virtualAccountData']['additionalInfo']['vacctValidTm'];

            $status_note = sprintf(
                __('Awaiting NICEPay payment. VA Number: %s, tXidVA: %s', 'nicepay-wc'),
                $va_number,
                $txid_va
            );
            
            $order->update_status('on-hold', $status_note);
            $order->add_order_note(sprintf(
                __('NICEPay Virtual Account created. Details:
                Number: %s
                Bank: %s
                Expire Date: %s %s
                tXidVA: %s', 'nicepay-wc'),
                $va_number,
                $this->get_bank_name($bank_code),
                $expiry_date,
                $expiry_time,
                $txid_va
            ));
            
            // Save transaction data to order meta
            $order->update_meta_data('_nicepay_va_number', $va_number);
            $order->update_meta_data('_nicepay_bank_code', $bank_code);
            $order->update_meta_data('_nicepay_va_expiry', $expiry_date . ' ' . $expiry_time);
            $order->update_meta_data('_nicepay_txid_va', $txid_va);
            $order->save();

            // Save data to session
            WC()->session->set('nicepay_va_number', $va_number);
            WC()->session->set('nicepay_bank_name', $bank_code);
        } else {
            throw new Exception(__('Failed to create Virtual Account', 'nicepay-wc'));
        }
    }

    /**
     * Thankyou page
     */
    public function thankyou_page($order_id) {
        $order = wc_get_order($order_id);
        if ($order && $order->get_payment_method() === $this->id) {
            $va_number = $order->get_meta('_nicepay_va_number');
            $bank_code = $order->get_meta('_nicepay_bank_code');
            $expiry_date = $order->get_meta('_nicepay_va_expiry');
            $payment_status = $order->get_status();
    
            if (!$order->get_meta('_nicepay_thankyou_displayed')) {
                echo '<div class="woocommerce-order-payment-details">';
                echo '<h2>' . __('Payment Instructions', 'nicepay-wc') . '</h2>';
    
                echo '<p><strong>' . __('Payment Status:', 'nicepay-wc') . '</strong> ' . $this->get_payment_status_description($payment_status) . '</p>';
    
                if ($payment_status !== 'completed' && $payment_status !== 'processing') {
                    echo '<p>' . sprintf(
                        __('Please transfer %s to the following Virtual Account details:', 'nicepay-wc'),
                        wc_price($order->get_total())
                    ) . '</p>';
                    echo '<ul>';
                    echo '<li><strong>' . __('Bank:', 'nicepay-wc') . '</strong> ' . esc_html($this->get_bank_name($bank_code)) . '</li>';
                    echo '<li><strong>' . __('Virtual Account Number:', 'nicepay-wc') . '</strong> ' . esc_html($va_number) . '</li>';
                    echo '<li><strong>' . __('Amount:', 'nicepay-wc') . '</strong> ' . wc_price($order->get_total()) . '</li>';
                    if ($expiry_date) {
                        $formatted_expiry = $this->format_expiry_date($expiry_date);
                        echo '<li><strong>' . __('Expiry Date:', 'nicepay-wc') . '</strong> ' . esc_html($formatted_expiry) . '</li>';
                    }
                    echo '</ul>';
    
                    echo '<p>' . sprintf(
                        __('For detailed payment instructions, please visit <a href="%s" target="_blank">NICEPay Payment Guide</a>.', 'nicepay-wc'),
                        'https://template.nicepay.co.id/'
                    ) . '</p>';
    
                    echo '<p>' . __('Please complete the payment before the VA expires. After payment is completed, it may take a few moments for the system to confirm your payment.', 'nicepay-wc') . '</p>';
                } else {
                    echo '<p>' . __('Thank you for your payment. Your transaction has been completed.', 'nicepay-wc') . '</p>';
                }
    
                echo '</div>';
                $order->update_meta_data('_nicepay_thankyou_displayed', 'yes');
                $order->save();
            }
        }
    }

    /**
     * Format expiry date
     */
    private function format_expiry_date($expiry_date) {
        $datetime = DateTime::createFromFormat('Ymd His', $expiry_date);
        if ($datetime) {
            return $datetime->format('d F Y H:i:s'); 
        }
        return $expiry_date;
    }

    /**
     * Get payment status description
     */
    protected function get_payment_status_description($status) {
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
     * Handle callback from NICEPay
     */
    public function handle_callback() {
        error_log('NICEPay callback received. Starting processing...');
        status_header(200);

        $raw_post = file_get_contents('php://input');
        error_log('Raw input: ' . $raw_post);

        $decoded_post = json_decode($raw_post, true);
        if (!$decoded_post) {
            parse_str($raw_post, $decoded_post);
        }
        if (empty($decoded_post)) {
            $decoded_post = $_POST;
        }

        error_log('Processed callback data: ' . print_r($decoded_post, true));

        if (isset($decoded_post['tXid']) && isset($decoded_post['vacctNo'])) {
            $order_id = trim($decoded_post['referenceNo']);
            error_log('Attempting to find order with ID: ' . $order_id);
            
            $order = wc_get_order($order_id);

            if ($order) {
                error_log('Order found: ' . $order_id);
                // Update order meta
                $order->update_meta_data('_nicepay_txid', $decoded_post['tXid']);
                $order->update_meta_data('_nicepay_vacctno', $decoded_post['vacctNo']);
                $order->update_meta_data('_nicepay_amount', $decoded_post['amt']);
                $order->update_meta_data('_nicepay_reference_no', $order_id);
                $order->update_meta_data('_nicepay_currency', $decoded_post['currency']);
                $order->save();  

                error_log('Order meta updated for order ' . $order_id);
                // Check payment status
                $this->check_payment_status($order);

                wp_send_json(array('status' => 'received'), 200);
                exit;
            } else {
                error_log('Order not found for referenceNo: ' . $order_id);
                wp_send_json_error('Order not found', 404);
            }
        } else {
            error_log('Invalid callback data received: ' . print_r($decoded_post, true));
            wp_send_json_error('Invalid callback data', 400);
        }
    }

    /**
     * Check payment status from NICEPay
     */
    private function check_payment_status($order) {
        error_log('Starting check_payment_status for order ' . $order->get_id());
        
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                error_log('Failed to get access token for status check');
                return;
            }
            
            $txid = trim($order->get_meta('_nicepay_txid_va'));
            $vacctno = trim($order->get_meta('_nicepay_va_number'));
            $amount = $order->get_total();
            $amt = number_format((float)$amount, 2, '.', '');
            $currency = $order->get_currency();
            $reference_no = $order->get_id();
            $external = date('YmdHis') . rand(1000, 9999);
        
            $body = [
                "partnerServiceId" => "",
                "customerNo" => "",
                "virtualAccountNo" => $vacctno,
                "inquiryRequestId" => $external,
                "additionalInfo" => [
                    "totalAmount" => [
                        "value" => $amt,
                        "currency" => $currency
                    ],
                    "trxId" => $reference_no,
                    "tXidVA" => $txid
                ]
            ];
        
            $X_CLIENT_KEY = $this->merchant_id;
            $secretClient = $this->merchant_key;
            $X_TIMESTAMP = $this->generate_formatted_timestamp();
            $channel = $this->channel_id;
            
            $stringBody = json_encode($body);
            $hashbody = strtolower(hash("SHA256", $stringBody));
            error_log('Request body for status check: ' . $stringBody);
    
            $strigSign = "POST:/api/v1.0/transfer-va/status:" . $access_token . ":" . $hashbody . ":" . $X_TIMESTAMP;
            $bodyHasing = hash_hmac("sha512", $strigSign, $secretClient, true);
            $X_SIGNATURE = base64_encode($bodyHasing);
    
            error_log('Sending check status request for order ' . $order->get_id());
    
            $args = array(
                'method'  => 'POST',
                'timeout' => 45,
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => "Bearer " . $access_token,
                    'X-CLIENT-KEY' => $X_CLIENT_KEY,
                    'X-TIMESTAMP'  => $X_TIMESTAMP,
                    'X-SIGNATURE' => $X_SIGNATURE,
                    'X-PARTNER-ID' => $X_CLIENT_KEY,
                    'X-EXTERNAL-ID' => $external,
                    'CHANNEL-ID' => $channel
                ),
                'body'    => $stringBody,
            );
    
            $response = wp_remote_post($this->api_endpoints['check_status_url'], $args);
        
            if (is_wp_error($response)) {
                error_log('Error checking payment status: ' . $response->get_error_message());
                return;
            }
        
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            error_log('Received check status response for order ' . $order->get_id() . ': ' . print_r($response_body, true));
        
            if (isset($response_body['responseCode']) && $response_body['responseCode'] === '2002600') {
                $status = $response_body['virtualAccountData']['additionalInfo']['latestTransactionStatus'] ?? '';
                $status_desc = $response_body['virtualAccountData']['additionalInfo']['transactionStatusDesc'] ?? '';
                error_log('Payment status for order ' . $order->get_id() . ': ' . $status . ' - ' . $status_desc);
        
                switch ($status) {
                    case '00':
                        $order->payment_complete($response_body['virtualAccountData']['additionalInfo']['tXidVA']);
                        $order->add_order_note('Payment completed via NICEPay Virtual Account. Status: ' . $status_desc);
                        error_log('Order ' . $order->get_id() . ' marked as paid. Status: ' . $status_desc);
                        break;
                    case '03':
                        if ($order->get_status() !== 'on-hold') {
                            $order->update_status('on-hold', 'Payment still pending via NICEPay Virtual Account. Status: ' . $status_desc);
                        } else {
                            $order->add_order_note('Payment still pending via NICEPay Virtual Account. Status: ' . $status_desc);
                        }
                        error_log('Order ' . $order->get_id() . ' is still pending. Status: ' . $status_desc);
                        break;
                    case '04':
                        $order->update_status('cancelled', 'NICEPay Virtual Account payment expired. Status: ' . $status_desc);
                        error_log('Order ' . $order->get_id() . ' marked as expired. Status: ' . $status_desc);
                        break;
                    default:
                        $order->add_order_note('Unknown payment status from NICEPay: ' . $status . ' - ' . $status_desc);
                        error_log('Unknown payment status for order ' . $order->get_id() . ': ' . $status . ' - ' . $status_desc);
                        break;
                }
                
                $order->save();
            } else {
                error_log('Invalid response from NICEPay status check: ' . print_r($response_body, true));
                $order->add_order_note('Failed to check payment status with NICEPay. Response: ' . print_r($response_body, true));
                $order->save();
            }
        } catch (Exception $e) {
            error_log('Exception in check_payment_status: ' . $e->getMessage());
            $order->add_order_note('Error checking payment status: ' . $e->getMessage());
            $order->save();
        }
    }
    
    /**
     * Generate formatted timestamp for NICEPay API
     */
    private function generate_formatted_timestamp() {
        $date = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        return $date->format('Y-m-d\TH:i:sP');
    }
}