jQuery(function($) {
    console.log('NICEPay Ewallet classic checkout initialized');

    function createEwalletSelector() {
        if (!nicepayData || !nicepayData.enabled_mitra) {
            console.error('NICEPay configuration is missing');
            return $('<div>').text('Payment configuration is not available');
        }

        const container = $('<div/>', {
            class: 'nicepay-ewallet-container'
        });

        // Header dengan logo
        const header = $('<div/>', {
            class: 'nicepay-ewallet-header'
        }).append(
            $('<img/>', {
                src: nicepayData.pluginUrl + '/config/ewallet1.png',
                alt: 'Ewallet Logo',
                class: 'nicepay-ewallet-icon'
            })
        );

        // Ewallet selector
        const ewalletSelect = $('<div/>', {
            class: 'nicepay-ewallet-select'
        }).append(
            $('<label/>', {
                for: 'nicepay-ewallet-select',
                text: 'Pilih E-wallet:'
            }),
            $('<select/>', {
                name: 'nicepay_mitra',
                id: 'nicepay-ewallet-select'
            }).append(
                $('<option/>', {
                    value: '',
                    text: 'Pilih E-wallet'
                }),
                nicepayData.enabled_mitra.map(mitra => 
                    $('<option/>', {
                        value: mitra.value,
                        text: mitra.label
                    })
                )
            )
        );
        const errorContainer = $('<div/>', {
            class: 'nicepay-ewallet-error',
            style: 'display: none; color: red; margin-top: 10px;'
        });

        container.append(header, ewalletSelect, errorContainer);
        return container;
    }
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