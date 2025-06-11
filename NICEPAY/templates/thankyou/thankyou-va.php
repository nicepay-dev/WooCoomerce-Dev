<?php
/**
 * Virtual Account thankyou page template
 * 
 * This template can be overridden by copying it to yourtheme/woocommerce/nicepay/thankyou-va.php
 */

defined('ABSPATH') || exit;

$va_number = $order->get_meta('_nicepay_va_number');
$bank_code = $order->get_meta('_nicepay_bank_code');
$bank_name = $gateway->get_bank_name($bank_code);
$expiry_date = $order->get_meta('_nicepay_va_expiry');
$formatted_expiry = $gateway->format_expiry_date($expiry_date);
$payment_status = $order->get_status();
?>

<div class="woocommerce-order-payment-info">
    <h2><?php esc_html_e('Payment Instructions', 'nicepay-wc'); ?></h2>
    
    <p class="payment-status">
        <strong><?php esc_html_e('Payment Status:', 'nicepay-wc'); ?></strong> 
        <?php echo esc_html($gateway->get_payment_status_description($payment_status)); ?>
    </p>
    
    <?php if ($payment_status !== 'completed' && $payment_status !== 'processing') : ?>
        <div class="payment-details">
            <p><?php 
                echo sprintf(
                    esc_html__('Please transfer %s to the following Virtual Account details:', 'nicepay-wc'),
                    wc_price($order->get_total())
                ); 
            ?></p>
            
            <ul class="payment-info-list">
                <li>
                    <strong><?php esc_html_e('Bank:', 'nicepay-wc'); ?></strong> 
                    <?php echo esc_html($bank_name); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Virtual Account Number:', 'nicepay-wc'); ?></strong> 
                    <span class="va-number"><?php echo esc_html($va_number); ?></span>
                </li>
                <li>
                    <strong><?php esc_html_e('Amount:', 'nicepay-wc'); ?></strong> 
                    <?php echo wc_price($order->get_total()); ?>
                </li>
                <?php if ($expiry_date) : ?>
                <li>
                    <strong><?php esc_html_e('Expiry Date:', 'nicepay-wc'); ?></strong> 
                    <?php echo esc_html($formatted_expiry); ?>
                </li>
                <?php endif; ?>
            </ul>
            
            <div class="payment-notes">
                <h4><?php esc_html_e('Important Notes:', 'nicepay-wc'); ?></h4>
                <ol>
                    <li><?php esc_html_e('Please transfer the exact amount as mentioned above.', 'nicepay-wc'); ?></li>
                    <li><?php esc_html_e('Make sure to complete the payment before the expiry date.', 'nicepay-wc'); ?></li>
                    <li><?php esc_html_e('After payment is completed, it may take a few moments for the system to confirm your payment.', 'nicepay-wc'); ?></li>
                </ol>
            </div>
            
            <div class="payment-guide">
                <p><?php 
                    echo sprintf(
                        esc_html__('For detailed payment instructions, please visit %s.', 'nicepay-wc'),
                        '<a href="https://template.nicepay.co.id/" target="_blank">NICEPay Payment Guide</a>'
                    ); 
                ?></p>
            </div>
        </div>
    <?php else : ?>
        <div class="payment-completed">
            <p><?php esc_html_e('Thank you for your payment. Your transaction has been completed and your order is being processed.', 'nicepay-wc'); ?></p>
        </div>
    <?php endif; ?>
</div>