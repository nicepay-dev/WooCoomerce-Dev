(function() {
    'use strict';

    // Check if WooCommerce Blocks and required dependencies are available
    if (typeof wc === 'undefined' || typeof wc.wcBlocksRegistry === 'undefined') {
        console.error('NICEPay: WooCommerce Blocks registry not found');
        return;
    }

    const { registerPaymentMethod } = wc.wcBlocksRegistry;
    const { createElement, useState, useEffect } = wp.element;
    const { __ } = wp.i18n;

    // Check if nicepayEwalletData is available
    if (typeof nicepayEwalletData === 'undefined') {
        console.error('NICEPay: nicepayEwalletData not found');
        return;
    }

    console.log('NICEPay E-wallet Blocks integration loaded');
    console.log('Available mitra:', nicepayEwalletData.enabled_mitra);

    // E-wallet Selection Component
    const EwalletPaymentComponent = (props) => {
        const [selectedMitra, setSelectedMitra] = useState('');
        const [isLoading, setIsLoading] = useState(false);

        // Save mitra selection to session
        const saveMitraSelection = async (mitraCode) => {
            if (!mitraCode) return;

            setIsLoading(true);
            
            try {
                const response = await fetch(nicepayEwalletData.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'set_nicepay_mitra',
                        mitra_code: mitraCode,
                        nonce: nicepayEwalletData.nonce
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    console.log('Mitra saved successfully:', data.data.mitra_code);
                } else {
                    console.error('Failed to save mitra:', data.data);
                    throw new Error(data.data || 'Failed to save e-wallet selection');
                }
            } catch (error) {
                console.error('Error saving mitra selection:', error);
                // You might want to show user-friendly error here
            } finally {
                setIsLoading(false);
            }
        };

        // Handle mitra selection change
        const handleMitraChange = (event) => {
            const mitraCode = event.target.value;
            setSelectedMitra(mitraCode);
            
            if (mitraCode) {
                saveMitraSelection(mitraCode);
            }
        };

        // Validate selection before payment
        useEffect(() => {
            // This will run when component mounts or selectedMitra changes
            if (selectedMitra) {
                // You can add additional validation logic here
                console.log('Selected mitra updated:', selectedMitra);
            }
        }, [selectedMitra]);

        return createElement('div', {
            className: 'nicepay-ewallet-blocks-container',
            style: {
                margin: '15px 0',
                padding: '15px',
                background: '#f8f8f8',
                borderRadius: '4px'
            }
        }, [
            // Logo section
            createElement('div', {
                key: 'header',
                className: 'nicepay-ewallet-header',
                style: {
                    marginBottom: '15px',
                    textAlign: 'center',
                    padding: '10px 0'
                }
            }, [
                createElement('div', {
                    key: 'logos',
                    className: 'nicepay-ewallet-logos',
                    style: {
                        display: 'flex',
                        justifyContent: 'center',
                        alignItems: 'center',
                        gap: '15px',
                        marginBottom: '20px'
                    }
                }, [
                    createElement('img', {
                        key: 'logo',
                        src: nicepayEwalletData.pluginUrl + '/assets/images/ewallet1.png',
                        alt: 'E-wallet Logo',
                        style: {
                            height: '30px',
                            width: 'auto'
                        }
                    })
                ])
            ]),
            
            // Selection section
            createElement('div', {
                key: 'select',
                className: 'nicepay-ewallet-select',
                style: {
                    margin: '10px 0'
                }
            }, [
                createElement('label', {
                    key: 'label',
                    htmlFor: 'nicepay-ewallet-select-blocks',
                    style: {
                        display: 'block',
                        marginBottom: '5px',
                        fontWeight: 'bold'
                    }
                }, __('Pilih E-wallet:', 'nicepay-wc')),
                
                createElement('select', {
                    key: 'select-input',
                    id: 'nicepay-ewallet-select-blocks',
                    value: selectedMitra,
                    onChange: handleMitraChange,
                    disabled: isLoading,
                    required: true,
                    style: {
                        width: '100%',
                        padding: '8px',
                        border: '1px solid #ddd',
                        borderRadius: '4px',
                        fontSize: '14px'
                    }
                }, [
                    createElement('option', {
                        key: 'default',
                        value: ''
                    }, __('Pilih E-wallet', 'nicepay-wc')),
                    
                    ...nicepayEwalletData.enabled_mitra.map(mitra => 
                        createElement('option', {
                            key: mitra.value,
                            value: mitra.value
                        }, mitra.label)
                    )
                ]),
                
                // Loading indicator
                isLoading && createElement('div', {
                    key: 'loading',
                    style: {
                        marginTop: '10px',
                        fontSize: '12px',
                        color: '#666'
                    }
                }, __('Saving selection...', 'nicepay-wc'))
            ])
        ]);
    };

    // Payment method configuration
    const nicepayEwalletPaymentMethod = {
        name: 'nicepay_ewallet',
        label: createElement('span', {
            style: { display: 'flex', alignItems: 'center', gap: '8px' }
        }, [
            createElement('img', {
                key: 'icon',
                src: nicepayEwalletData.pluginUrl + '/assets/images/ewallet1.png',
                alt: 'NICEPay E-wallet',
                style: {
                    height: '24px',
                    width: 'auto'
                }
            }),
            createElement('span', {
                key: 'text'
            }, __('NICEPAY E-wallet', 'nicepay-wc'))
        ]),
        content: createElement(EwalletPaymentComponent),
        edit: createElement(EwalletPaymentComponent),
        canMakePayment: () => {
            // Check if e-wallet options are available
            return nicepayEwalletData.enabled_mitra && nicepayEwalletData.enabled_mitra.length > 0;
        },
        ariaLabel: __('NICEPay E-wallet payment method', 'nicepay-wc'),
        supports: {
            features: ['products']
        }
    };

    // Register the payment method
    registerPaymentMethod(nicepayEwalletPaymentMethod);

    console.log('NICEPay E-wallet payment method registered for Blocks checkout');
})();