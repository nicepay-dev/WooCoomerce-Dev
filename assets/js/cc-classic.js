/**
 * NICEPay Credit Card Classic Checkout Integration
 * 
 * Script untuk checkout mode classic (non-blocks)
 */

jQuery(document).ready(function($) {
    console.log('NICEPay CC Classic script loaded');

    if (!$('body').hasClass('woocommerce-checkout')) {
        return;
    }

    
    $('body').on('change', 'input[name="payment_method"]', function() {
        const selectedMethod = $(this).val();
        
        if (selectedMethod === 'nicepay_cc') {
            console.log('NICEPay CC payment method selected');
           
            $('.nicepay-cc-container').show();
        } else {
           
            $('.nicepay-cc-container').hide();
        }
    });

    
    $('input[name="payment_method"]:checked').change();

    
    $('#nicepay_cc_installment').on('change', function() {
        const selectedInstallment = $(this).val();
        console.log('Selected installment: ' + selectedInstallment);
    });

    
    $('form.checkout').on('checkout_place_order_nicepay_cc', function() {
        
        console.log('NICEPay CC checkout form submitted');
        return true;
    });

   
    function stylePaymentMethod() {
        if ($('#payment_method_nicepay_cc').length) {
            const $label = $('label[for="payment_method_nicepay_cc"]');
            
            if (!$label.find('img').length) {
                const logoUrl = nicepay_cc_params?.pluginUrl + '/assets/images/cc-logo.png';
                $label.prepend('<img src="' + logoUrl + '" alt="Credit Card" style="max-height: 24px; margin-right: 0.5em; vertical-align: middle;">');
            }
            
            $('#payment_method_nicepay_cc').closest('li').addClass('nicepay-cc-payment-method');
        }
    }

    stylePaymentMethod();
    
    $(document.body).on('updated_checkout', function() {
        stylePaymentMethod();
        $('input[name="payment_method"]:checked').change();
    });
});