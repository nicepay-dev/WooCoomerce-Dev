<?php
/**
 * Virtual Account form template
 * 
 * This template can be overridden by copying it to yourtheme/woocommerce/nicepay/form-va.php
 */

defined('ABSPATH') || exit;
?>

<div class="nicepay-va-form">
    <?php if ($description) : ?>
        <p><?php echo wp_kses_post($description); ?></p>
    <?php endif; ?>

    <div class="nicepay-va-banks">
        <label for="nicepay-bank-select"><?php esc_html_e('Select Bank', 'nicepay-wc'); ?></label>
        <select id="nicepay-bank-select" name="nicepay_bank" class="wc-enhanced-select">
            <option value=""><?php esc_html_e('Select a bank...', 'nicepay-wc'); ?></option>
            <?php foreach ($banks as $bank) : ?>
                <option value="<?php echo esc_attr($bank['code']); ?>">
                    <?php echo esc_html($bank['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="nicepay-va-description">
        <p class="nicepay-va-info">
            <?php esc_html_e('After payment, your order will be processed automatically. Virtual Account number will be provided after checkout.', 'nicepay-wc'); ?>
        </p>
    </div>
</div>