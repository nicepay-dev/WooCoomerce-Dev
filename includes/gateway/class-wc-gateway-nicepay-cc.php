<?php
/**
 * NICEPay Credit Card Payment Gateway
 *
 * Provides a Credit Card Payment Gateway for WooCommerce.
 *
 * @class   WC_Gateway_Nicepay_CC
 * @extends WC_Payment_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gateway_Nicepay_CC Class
 * CCV2
 */
class WC_Gateway_Nicepay_CC extends WC_Payment_Gateway {

    protected $redirect_url;
    protected $environment;
    protected $api_endpoints;
    protected $iMid;
    protected $mKey;
    protected $Instmount1;
    protected $Instmount3;
    protected $Instmount6;
    protected $Instmount12;
    protected $reduceStock;
    public function __construct() {
        if (ob_get_level() == 0) {
            ob_start();
        }
        $this->id                 = 'nicepay_cc';
        $this->method_title       = __('NICEPay Credit Card', 'nicepay-wc');
        $this->method_description = __('Accept credit card payments through NICEPay payment gateway.', 'nicepay-wc');
        $this->has_fields         = true;
        $this->supports           = array('products', 'refunds');

        $this->redirect_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'WC_Gateway_NICEPay_CCV2', home_url('/')));
        $this->method_description = __('Allows payments using NICEPay Credit Card.', 'nicepay-cc-snap-gateway');

        // Define user set variables
        $this->title        = $this->get_option('title', __('Credit Card', 'nicepay-wc'));
        $this->description  = $this->get_option('description', __('Pay securely using your credit card.', 'nicepay-wc'));
        $this->instructions = $this->get_option('instructions');
        
        $this->environment = get_option('nicepay_environment', 'sandbox');
        $this->api_endpoints = $this->get_api_endpoints();
        $this->init_form_fields();
        $this->init_settings();

        // add_action('wp_enqueue_scripts', array($this, 'enqueue_classic_mode'));

        // if ($this->get_option('enable_blocks') === 'classic') {
        //     add_action('wp_enqueue_scripts', array($this, 'enqueue_classic_mode'));
        // } else {
        //     add_action('wp_enqueue_scripts', array($this, 'enqueue_blocks_mode'));
        // }

        if ($this->environment === 'sandbox') {
            @ini_set('display_errors', 1);
            @ini_set('display_startup_errors', 1);
            @error_reporting(E_ALL);
        }

        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->iMid = $this->get_option('iMid');
        $this->mKey = $this->get_option('mKey');
        $this->Instmount1 = $this->get_option('instmntMon1');
        $this->Instmount3 = $this->get_option('instmntMon3');
        $this->Instmount6 = $this->get_option('instmntMon6');
        $this->Instmount12 = $this->get_option('instmntMon12');
        //---------------------------------------------//
        $this->reduceStock   = $this->get_option( 'reduceStock' );
        //---------------------------------------------//
        
        // Get installment options
        // $this->installment_options = array(
        //     'fullpayment' => $this->get_option('fullpayment', 'yes'),
        //     'installment_3' => $this->get_option('installment_3', 'no'),
        //     'installment_6' => $this->get_option('installment_6', 'no'),
        //     'installment_12' => $this->get_option('installment_12', 'no')
        // );
        
        // Set the icon
        $this->icon = apply_filters('woocommerce_nicepay_cc_icon', NICEPAY_WC_PLUGIN_URL . 'assets/images/cc-logo.png');
        
        // Set the callback URL
        // $this->notify_url = WC()->api_request_url('WC_Gateway_Nicepay_CC');
        
        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_nicepay_return', array($this, 'handle_return_url'));
        add_action('woocommerce_settings_save_' . $this->id, array($this, 'update_api_endpoints'));
        add_action('woocommerce_api_wc_gateway_nicepay_ccv2', array($this, 'handle_notification'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'), 1);
        
        // Enqueue scripts for checkout
        // add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    /**
     * Get API Endpoints
     */
    private function get_api_endpoints() {
        $base_url = ($this->environment === 'production')  
                ? 'https://www.nicepay.co.id'
                : 'https://dev.nicepay.co.id';
        
        return [
            'registration' => $base_url . '/nicepay/redirect/v2/registration',
            'check_status' => $base_url . '/nicepay/direct/v2/inquiry',
        ];
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'nicepay-wc'),
                'type'        => 'checkbox',
                'label'       => __('Enable NICEPay Credit Card Payment', 'nicepay-wc'),
                'default'     => 'yes'
            ),
            'title' => array(
                'title'       => __('Title', 'nicepay-wc'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'nicepay-wc'),
                'default'     => __('Credit Card', 'nicepay-wc'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'nicepay-wc'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'nicepay-wc'),
                'default'     => __('Pay securely using your credit card.', 'nicepay-wc'),
                'desc_tip'    => true,
            ),
            'enable_blocks' => array(
            'title'       => __('Checkout Mode', 'woocommerce'),
            'type'        => 'select',
            'description' => __('Select checkout mode. Block checkout is for modern WooCommerce checkout, while Classic is for traditional checkout.', 'woocommerce'),
            'default'     => 'classic',
            'options'     => array(
            'classic' => __('Classic Checkout / Element Checkout (Non-Blocks)', 'woocommerce'),
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
            'mKey' => array(
                'title' => 'Merchant Key',
                'type' => 'text',
                'description' =>'<small>Isikan dengan Merchant Key dari NICEPay</small>.',
                'default' => '33F49GnCMS1mFYlGXisbUDzVf2ATWCl9k3R++d5hDd3Frmuos/XLx8XhXpe+LDYAbpGKZYSwtlyyLOtS/8aD7A==',
            ),
            'iMid' => array(
                'title' => 'Merchant ID',
                'type' => 'text',
                'description' => '<small>Isikan dengan Merchant ID dari NICEPay</small>.',
                'default' => 'IONPAYTEST',
            ),
            'instructions' => array(
                'title'       => __('Instructions', 'nicepay-wc'),
                'type'        => 'textarea',
                'description' => __('Instructions that will be added to the thank you page.', 'nicepay-wc'),
                'default'     => __('Your credit card has been charged and your transaction is successful.', 'nicepay-wc'),
                'desc_tip'    => true,
            ),
            'instmntMon1' => array(
            'title' => __('Fullpayment', 'woocommerce'),
            'type' => 'checkbox',
            'description' => __('<small>Check to enable. Please confirm to NICEPay before enabled this Installment.</small>', 'woocommerce'),
            'default' => 'yes',
             ),
            'instmntMon3' => array(
            'title' => __('Installment 3 Month', 'woocommerce'),
            'type' => 'checkbox',
            'description' => __('<small>Check to enable. Please confirm to NICEPay before enabled this Installment.</small>', 'woocommerce'),
            'default' => 'no',
            ),
            'instmntMon6' => array(
            'title' => __('Installment 6 Month', 'woocommerce'),
            'type' => 'checkbox',
            'description' => __('<small>Check to enable. Please confirm to NICEPay before enabled this Installment.</small>', 'woocommerce'),
            'default' => 'no',
            ),
            'instmntMon12' => array(
            'title' => __('Installment 12 Month', 'woocommerce'),
            'type' => 'checkbox',
            'description' => __('<small>Check to enable. Please confirm to NICEPay before enabled this Installment.</small>', 'woocommerce'),
            'default' => 'no',
          ),
            'reduce_stock' => array(
                'title'       => __('Reduce Stock', 'nicepay-wc'),
                'type'        => 'checkbox',
                'label'       => __('Reduce stock on payment initiation', 'nicepay-wc'),
                'default'     => 'no',
                'description' => __('Enable to reduce stock when payment is initiated rather than when completed.', 'nicepay-wc'),
            ),
        );
    }

    /**
     * Load payment form and scripts on checkout page
     */
    public function payment_scripts() {
        if (!is_checkout()) {
            return;
        }

        // Register and enqueue CC script
        wp_register_script(
            'nicepay-cc-js',
            NICEPAY_WC_PLUGIN_URL . 'assets/js/cc-payment.js',
            array('jquery'),
            NICEPAY_WC_VERSION,
            true
        );

        // Localize script with data
        wp_localize_script(
            'nicepay-cc-js',
            'nicepay_cc_params',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('nicepay-cc-nonce'),
                'pluginUrl' => NICEPAY_WC_PLUGIN_URL,
                'installmentOptions' => $this->get_available_installments()
            )
        );

        wp_enqueue_script('nicepay-cc-js');
        
        // Enqueue CSS
        wp_enqueue_style(
            'nicepay-cc-style',
            NICEPAY_WC_PLUGIN_URL . 'assets/css/nicepay.css',
            array(),
            NICEPAY_WC_VERSION
        );
    }

    /**
     * Get available installment options
     */
    private function get_available_installments() {
        $options = array();
        
        if ($this->installment_options['fullpayment'] === 'yes') {
            $options[] = array('value' => '1', 'label' => __('Full Payment', 'nicepay-wc'));
        }
        
        if ($this->installment_options['installment_3'] === 'yes') {
            $options[] = array('value' => '3', 'label' => __('3 Month Installment', 'nicepay-wc'));
        }
        
        if ($this->installment_options['installment_6'] === 'yes') {
            $options[] = array('value' => '6', 'label' => __('6 Month Installment', 'nicepay-wc'));
        }
        
        if ($this->installment_options['installment_12'] === 'yes') {
            $options[] = array('value' => '12', 'label' => __('12 Month Installment', 'nicepay-wc'));
        }
        
        return $options;
    }

    /**
     * Output payment fields
     */
    public function payment_fields() {
        // Display description if set
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
        
        // Display installment options if available
        $installment_options = $this->get_available_installments();
        if (count($installment_options) > 0) {
            echo '<div class="nicepay-cc-container">';
            echo '<div class="nicepay-cc-header">';
            echo '<img src="' . NICEPAY_WC_PLUGIN_URL . 'assets/images/cc-logo.png" class="nicepay-cc-icon" alt="Credit Card" />';
            
            echo '<div class="nicepay-cc-logos">';
            echo '<img src="' . NICEPAY_WC_PLUGIN_URL . 'assets/images/visa.png" alt="Visa" />';
            echo '<img src="' . NICEPAY_WC_PLUGIN_URL . 'assets/images/mastercard.png" alt="MasterCard" />';
            echo '<img src="' . NICEPAY_WC_PLUGIN_URL . 'assets/images/jcb.png" alt="JCB" />';
            echo '</div>';
            
            echo '</div>';
            
            if (count($installment_options) > 1) {
                echo '<div class="nicepay-cc-select">';
                echo '<label for="nicepay_cc_installment">' . __('Choose Installment Option', 'nicepay-wc') . '</label>';
                echo '<select name="nicepay_cc_installment" id="nicepay_cc_installment">';
                
                foreach ($installment_options as $option) {
                    echo '<option value="' . esc_attr($option['value']) . '">' . esc_html($option['label']) . '</option>';
                }
                
                echo '</select>';
                echo '</div>';
            } else {
                // If only one option, just show text
                echo '<div class="nicepay-cc-single-option">';
                echo '<p>' . esc_html($installment_options[0]['label']) . '</p>';
                echo '<input type="hidden" name="nicepay_cc_installment" value="' . esc_attr($installment_options[0]['value']) . '" />';
                echo '</div>';
            }
            
            echo '</div>';
        }
    }

    /**
     * Process the payment
     */
    public function process_payment($order_id) {
        try {
            // Get order object
            $order = wc_get_order($order_id);
            
            if (!$order) {
                throw new Exception(__('Order not found', 'nicepay-wc'));
            }
            
            // Get installment option if set
            $installment = isset($_POST['nicepay_cc_installment']) ? sanitize_text_field($_POST['nicepay_cc_installment']) : '1';
            
            // Reduce stock levels if enabled
            if ($this->get_option('reduce_stock') === 'yes') {
                wc_reduce_stock_levels($order_id);
            }
            
            // Prepare registration data
            $registration_data = $this->prepare_registration_data($order, $installment);
            
            // Send registration request
            $response = $this->send_registration_request($registration_data);
            
            if (!isset($response->tXid) || !isset($response->paymentURL)) {
                throw new Exception(__('Invalid response from NICEPay', 'nicepay-wc'));
            }
            
            // Store transaction data in order meta
            $order->update_meta_data('_nicepay_txid', $response->tXid);
            $order->update_meta_data('_nicepay_cc_installment', $installment);
            $order->update_meta_data('_nicepay_response', json_encode($response));
            
            // Add order note
            $order->add_order_note(
                sprintf(__('NICEPay CC payment initiated. Transaction ID: %s', 'nicepay-wc'), $response->tXid)
            );
            
            // Mark as pending
            $order->update_status('pending', __('Awaiting NICEPay payment', 'nicepay-wc'));
            
            $order->save();
            
            // Redirect to payment page
            $payment_url = $response->paymentURL . '?tXid=' . $response->tXid;
            
            return array(
                'result'   => 'success',
                'redirect' => $payment_url
            );
            
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return array('result' => 'failure');
        }
    }

    /**
     * Prepare registration data for NICEPay
     */
    private function prepare_registration_data($order, $installment = '1') {
        // Format timestamp
        $timestamp = date('YmdHis');
        
        // Prepare cart data
        $cart_data = $this->prepare_cart_data($order);
        
        // Return URL for redirect after payment
        $return_url = add_query_arg(array(
            'wc-api' => 'WC_Gateway_Nicepay_CC',
            'order_id' => $order->get_id(),
            'key' => $order->get_order_key()
        ), home_url('/'));
        
        // Basic data
        $data = array(
            'timeStamp' => $timestamp,
            'iMid' => $this->merchant_id,
            'payMethod' => '00', 
            'currency' => 'IDR',
            'amt' => number_format($order->get_total(), 0, '', ''),
            'referenceNo' => $order->get_id(),
            'goodsNm' => 'Order #' . $order->get_id(),
            'billingNm' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'billingPhone' => $order->get_billing_phone(),
            'billingEmail' => $order->get_billing_email(),
            'billingAddr' => $order->get_billing_address_1(),
            'billingCity' => $order->get_billing_city(),
            'billingState' => $order->get_billing_state(),
            'billingPostCd' => $order->get_billing_postcode(),
            'billingCountry' => $order->get_billing_country(),
            'callBackUrl' => $return_url,
            'dbProcessUrl' => add_query_arg('wc-api', 'WC_Gateway_Nicepay_CC', home_url('/')),
            'description' => 'Payment for order #' . $order->get_id(),
            'userIP' => $this->get_client_ip(),
            'cartData' => $cart_data,
            'instmntType' => '1',
            'instmntMon' => $installment
        );
        
        // Generate merchant token
        $data['merchantToken'] = $this->generate_merchant_token($data);
        
        return $data;
    }

    /**
     * Prepare cart data in NICEPay format
     */
    private function prepare_cart_data($order) {
        $items = $order->get_items();
        $cart_items = array();
        
        foreach ($items as $item) {
            $product = $item->get_product();
            $unit_price = number_format($order->get_item_total($item, false, false), 0, '', '');
            
            $cart_items[] = array(
                'goods_id' => $product ? $product->get_sku() ?: $product->get_id() : 'item-' . $item->get_id(),
                'goods_name' => $item->get_name(),
                'goods_detail' => $product ? substr(strip_tags($product->get_description()), 0, 50) : '',
                'goods_amt' => $unit_price,
                'goods_quantity' => $item->get_quantity(),
                'goods_type' => $product ? $product->get_type() : 'item',
                'goods_url' => $product ? get_permalink($product->get_id()) : '',
                'goods_sellers_id' => 'STORE',
                'goods_sellers_name' => get_bloginfo('name')
            );
        }
        
        // Add shipping if applicable
        if ($order->get_shipping_total() > 0) {
            $shipping_amount = number_format($order->get_shipping_total(), 0, '', '');
            $cart_items[] = array(
                'goods_id' => 'SHIPPING',
                'goods_name' => 'Shipping',
                'goods_detail' => 'Shipping Cost',
                'goods_amt' => $shipping_amount,
                'goods_quantity' => 1,
                'goods_type' => 'shipping',
                'goods_url' => '',
                'goods_sellers_id' => 'STORE',
                'goods_sellers_name' => get_bloginfo('name')
            );
        }
        
        // Add tax if separate
        if ($order->get_total_tax() > 0) {
            $tax_amount = number_format($order->get_total_tax(), 0, '', '');
            $cart_items[] = array(
                'goods_id' => 'TAX',
                'goods_name' => 'Tax',
                'goods_detail' => 'Tax Amount',
                'goods_amt' => $tax_amount,
                'goods_quantity' => 1,
                'goods_type' => 'tax',
                'goods_url' => '',
                'goods_sellers_id' => 'STORE',
                'goods_sellers_name' => get_bloginfo('name')
            );
        }
        
        $cart_data = array(
            'count' => count($cart_items),
            'item' => $cart_items
        );
        
        return json_encode($cart_data);
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ipaddress = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } else if (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = '127.0.0.1';
        }
        
        return $ipaddress;
    }

    /**
     * Generate merchant token for API requests
     */
    private function generate_merchant_token($data) {
        $amt = number_format($data['amt'], 0, '', '');
        $raw_data = $data['timeStamp'] . $data['iMid'] . $data['referenceNo'] . $amt . $this->merchant_key;
        
        return hash('sha256', $raw_data);
    }

    /**
     * Send registration request to NICEPay
     */
    private function send_registration_request($data) {
        $response = wp_remote_post($this->api_endpoints['registration'], array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($body);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        if (isset($decoded_response->resultCd) && $decoded_response->resultCd !== '0000') {
            throw new Exception('Error: ' . (isset($decoded_response->resultMsg) ? $decoded_response->resultMsg : 'Unknown error'));
        }
        
        return $decoded_response;
    }

    /**
     * Handle the callback from NICEPay
     */
    public function handle_callback() {
        try {
            // Get raw POST data
            $notification = $_POST;
            
            if (empty($notification) || !isset($notification['referenceNo']) || !isset($notification['tXid']) || !isset($notification['status'])) {
                throw new Exception('Invalid notification data');
            }
            
            $order_id = sanitize_text_field($notification['referenceNo']);
            $txid = sanitize_text_field($notification['tXid']);
            $status = sanitize_text_field($notification['status']);
            
            // Get order
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception('Order not found');
            }
            
            // Verify transaction ID
            $stored_txid = $order->get_meta('_nicepay_txid');
            if ($stored_txid !== $txid) {
                throw new Exception('Transaction ID mismatch');
            }
            
            // Process based on status
            if ($status === '0') { // Success
                // Verify with status check
                $status_check = $this->check_payment_status($order);
                
                if ($status_check && isset($status_check->status) && $status_check->status === '0') {
                    // Payment successful
                    $order->payment_complete($txid);
                    
                    // Add payment details as order note
                    $payment_details = $this->get_payment_details_note($status_check);
                    $order->add_order_note($payment_details);
                    
                    // Clear cart
                    WC()->cart->empty_cart();
                }
            } else if ($status === '1') { // Failed
                $order->update_status('failed', __('Payment failed. Reason: ', 'nicepay-wc') . 
                (isset($notification['failureMsg']) ? $notification['failureMsg'] : 'Unknown reason'));
            }
            
            // Redirect to thank you page if this is a return from payment
            if (isset($_GET['order_id']) && isset($_GET['key'])) {
                wp_redirect($order->get_checkout_order_received_url());
                exit;
            }
            
            // Respond with OK to NICEPay
            echo "OK";
            exit;
            
        } catch (Exception $e) {
            // Log error
            error_log('NICEPay CC callback error: ' . $e->getMessage());
            
            // Respond with error
            echo "ERROR: " . $e->getMessage();
            exit;
        }
    }

    /**
     * Check payment status with NICEPay API
     */
    private function check_payment_status($order) {
        try {
            $txid = $order->get_meta('_nicepay_txid');
            
            if (!$txid) {
                throw new Exception('Transaction ID not found');
            }
            
            $timestamp = date('YmdHis');
            $amount = number_format($order->get_total(), 0, '', '');
            
            $request_data = array(
                'timeStamp' => $timestamp,
                'tXid' => $txid,
                'iMid' => $this->merchant_id,
                'referenceNo' => $order->get_id(),
                'amt' => $amount
            );
            
            // Generate token
            $raw_data = $request_data['timeStamp'] . $request_data['iMid'] . $request_data['referenceNo'] . 
                        $request_data['amt'] . $this->merchant_key;
            $request_data['merchantToken'] = hash('sha256', $raw_data);
            
            // Send request
            $response = wp_remote_post($this->api_endpoints['check_status'], array(
                'headers' => array(
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($request_data),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            return json_decode($body);
            
        } catch (Exception $e) {
            error_log('NICEPay status check error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Format payment details for order note
     */
    private function get_payment_details_note($payment_data) {
        $note = __('Payment Successful:', 'nicepay-wc') . "\n";
        $note .= __('Transaction ID: ', 'nicepay-wc') . $payment_data->tXid . "\n";
        $note .= __('Amount: ', 'nicepay-wc') . 'Rp ' . number_format($payment_data->amt, 0, ',', '.') . "\n";
        
        if (isset($payment_data->cardNo)) {
            $note .= __('Card Number: ', 'nicepay-wc') . $payment_data->cardNo . "\n";
        }
        
        if (isset($payment_data->acquBankNm)) {
            $note .= __('Bank: ', 'nicepay-wc') . $payment_data->acquBankNm . "\n";
        }
        
        if (isset($payment_data->transDt) && isset($payment_data->transTm)) {
            $note .= __('Transaction Time: ', 'nicepay-wc') . $payment_data->transDt . ' ' . $payment_data->transTm . "\n";
        }
        
        return $note;
    }

    /**
     * Display thank you page
     */
    public function thankyou_page($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || $order->get_payment_method() !== $this->id) {
            return;
        }
        
        // Display instructions if set
        if ($this->instructions) {
            echo wpautop(wptexturize($this->instructions));
        }
        
        // Display payment details
        $txid = $order->get_meta('_nicepay_txid');
        
        if ($txid) {
            echo '<h2>' . __('Payment Details', 'nicepay-wc') . '</h2>';
            echo '<ul class="order_details">';
            echo '<li>' . __('Transaction ID:', 'nicepay-wc') . ' <strong>' . $txid . '</strong></li>';
            
            $installment = $order->get_meta('_nicepay_cc_installment');
            if ($installment) {
                if ($installment === '1') {
                    echo '<li>' . __('Payment Type:', 'nicepay-wc') . ' <strong>' . __('Full Payment', 'nicepay-wc') . '</strong></li>';
                } else {
                    echo '<li>' . __('Installment:', 'nicepay-wc') . ' <strong>' . $installment . ' ' . __('months', 'nicepay-wc') . '</strong></li>';
                }
            }
            
            echo '</ul>';
        }
    }
    
    /**
     * Process refund
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        // This method would implement refund functionality
        // For now just return true as refund would be manual
        return true;
    }
}