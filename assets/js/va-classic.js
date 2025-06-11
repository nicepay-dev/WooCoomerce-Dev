/**
 * NICEPay VA Classic Checkout JS
 * Handles VA payment method in classic checkout mode
 */
jQuery(function($) {
    console.log('NICEPay VA classic checkout initialized');

    // Handler untuk perubahan bank
    $('#nicepay-bank-select').on('change', function() {
        var selectedBank = $(this).val();
        console.log('Selected bank:', selectedBank);

        $.ajax({
            url: nicepayData.ajax_url,
            type: 'POST',
            data: {
                action: 'set_nicepay_bank',
                bank_code: selectedBank,
                security: nicepayData.nonce
            },
            success: function(response) {
                console.log('Bank selection saved:', response);
            },
            error: function(error) {
                console.error('Error saving bank selection:', error);
            }
        });
    });

    // Validasi form submission
    $('form.checkout').on('submit', function() {
        // Jika payment method yang dipilih adalah nicepay_va
        if ($('input[name="payment_method"]:checked').val() === 'nicepay_va') {
            var selectedBank = $('#nicepay-bank-select').val();
            
            // Validasi bank dipilih
            if (!selectedBank) {
                // Tambahkan notifikasi error
                if ($('.nicepay-va-error').length === 0) {
                    $('#nicepay-bank-select').after('<div class="nicepay-va-error" style="color:red;margin-top:5px;">Silakan pilih bank untuk pembayaran</div>');
                }
                return false;
            }
            
            // Hapus error jika ada
            $('.nicepay-va-error').remove();
            
            // Tambahkan selected bank ke form jika belum ada
            if (!$('input[name="nicepay_bank"]').length) {
                $(this).append('<input type="hidden" name="nicepay_bank" value="' + selectedBank + '">');
            } else {
                $('input[name="nicepay_bank"]').val(selectedBank);
            }
        }
    });
});