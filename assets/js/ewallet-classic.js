jQuery(document).ready(function($) {
    'use strict';

    // Check if nicepayData is available
    if (typeof nicepayData === 'undefined') {
        console.error('NICEPay: nicepayData not found');
        return;
    }

    console.log('NICEPay Classic Checkout initialized');
    console.log('Available mitra:', nicepayData.enabled_mitra);

    // Handle e-wallet selection
    $(document).on('change', '#nicepay-ewallet-select', function() {
        var selectedMitra = $(this).val();
        console.log('E-wallet selected:', selectedMitra);

        if (selectedMitra) {
            // Send AJAX request to save selection
            $.ajax({
                url: nicepayData.ajax_url,
                type: 'POST',
                data: {
                    action: 'set_nicepay_mitra',
                    mitra_code: selectedMitra,
                    nonce: nicepayData.nonce
                },
                beforeSend: function() {
                    console.log('Saving mitra selection...');
                },
                success: function(response) {
                    console.log('Mitra selection response:', response);
                    if (response.success) {
                        console.log('Mitra saved successfully:', response.data.mitra_code);
                        
                        // Enable place order button if it was disabled
                        $('#place_order').prop('disabled', false);
                        
                        // Remove any previous error messages
                        $('.woocommerce-error, .woocommerce-message').remove();
                        
                        // Show success message (optional)
                        // $('form.checkout').before('<div class="woocommerce-message">E-wallet selected: ' + response.data.mitra_code + '</div>');
                    } else {
                        console.error('Failed to save mitra:', response.data);
                        alert('Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    console.error('Response:', xhr.responseText);
                    alert('Network error occurred. Please try again.');
                }
            });
        }
    });
      $(document).on('click', '#place_order', function(e) {
        // Check if NICEPay e-wallet is selected as payment method
        var selectedPayment = $('input[name="payment_method"]:checked').val();
        
        if (selectedPayment === 'nicepay_ewallet') {
            var selectedMitra = $('#nicepay-ewallet-select').val();
            
            if (!selectedMitra) {
                e.preventDefault();
                alert('Please select an e-wallet payment method.');
                
                // Scroll to e-wallet selection
                $('html, body').animate({
                    scrollTop: $('#nicepay-ewallet-select').offset().top - 100
                }, 500);
                
                // Focus on select element
                $('#nicepay-ewallet-select').focus();
                
                return false;
            }
        }
    });
    
    function showError(message, container) {
        const errorDiv = container.find('.nicepay-ewallet-error');
        errorDiv.text(message).show();
        setTimeout(() => errorDiv.fadeOut(), 5000);
    }
    function saveMitraSelection(selectedMitra, container) {
        if (!selectedMitra) {
            return;
        }
        const select = container.find('#nicepay-ewallet-select');
        select.prop('disabled', true);
        $.ajax({
            url: nicepayData.ajax_url,
            type: 'POST',
            data: {
                action: 'set_nicepay_mitra',
                mitra_code: selectedMitra,
                nonce: nicepayData.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Mitra selection saved:', response.data);
                    sessionStorage.setItem('nicepay_selected_mitra', selectedMitra);
                } else {
                    showError(response.data || 'Failed to save selection', container);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error saving mitra selection:', error);
                showError('Failed to save e-wallet selection', container);
            },
            complete: function() {
                select.prop('disabled', false);
            }
        });
    }

    function initNicepayClassic() {
        const paymentMethod = $('input[name="payment_method"][value="nicepay_ewallet_snap"]');
        if (paymentMethod.length) {
            // Remove existing container if any
            paymentMethod.closest('li').find('.nicepay-ewallet-container').remove();
            
            const ewalletSelector = createEwalletSelector();
            paymentMethod.closest('li').append(ewalletSelector);

            // Restore previous selection if any
            const savedMitra = sessionStorage.getItem('nicepay_selected_mitra');
            if (savedMitra) {
                $('#nicepay-ewallet-select').val(savedMitra);
            }

            // Handle ewallet selection
            $('#nicepay-ewallet-select').on('change', function() {
                const selectedMitra = $(this).val();
                saveMitraSelection(selectedMitra, ewalletSelector);
            });
        }
    }

    $('form.checkout').on('checkout_place_order_nicepay_ewallet_snap', function() {
        const selectedMitra = $('#nicepay-ewallet-select').val();
        if (!selectedMitra) {
            showError('Silakan pilih e-wallet untuk pembayaran', $('.nicepay-ewallet-container'));
            return false;
        }

        // Clean up any existing hidden input
        $('input[name="nicepay_mitra"]').remove();
        
        // Add selected mitra to form
        $('<input>').attr({
            type: 'hidden',
            name: 'nicepay_mitra',
            value: selectedMitra
        }).appendTo('form.checkout');
        
        return true;
    });

    // Initialize on page load
    initNicepayClassic();

    // Reinitialize when checkout is updated
    $(document.body).on('updated_checkout payment_method_selected', function() {
        initNicepayClassic();
    });
});