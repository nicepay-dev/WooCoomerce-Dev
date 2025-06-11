
(function() {
    console.log("Loading NICEPay Ewallet Plugin");
    
    // Gunakan React elements dari WP
    const wpElement = window.wp.element;
    const { createElement, useState } = wpElement;
    
    // Ewallet Component
    const NicepayEwalletComponent = () => {
        const [selectedMitra, setSelectedMitra] = useState('');
        
        // Gunakan nicepayEwalletData untuk blocks
        const mitra = window.nicepayEwalletData?.enabled_mitra || [];
        console.log('Available mitra (Ewallet):', mitra);

        const handleMitraChange = (e) => {
            const selectedMitraCode = e.target.value;
            console.log('Mitra selected:', selectedMitraCode);
            setSelectedMitra(selectedMitraCode);
            saveMitraSelection(selectedMitraCode);
        };

        const saveMitraSelection = (mitraCode) => {
            console.log('Attempting to save mitra selection:', mitraCode);
            if (typeof jQuery !== 'undefined' && typeof nicepayEwalletData !== 'undefined') {
                jQuery.ajax({
                    url: nicepayEwalletData.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'set_nicepay_mitra',
                        mitra_code: mitraCode,
                        nonce: nicepayEwalletData.nonce
                    },
                    success: function(response) {
                        console.log('Mitra selection saved:', response);
                        sessionStorage.setItem('nicepay_selected_mitra', mitraCode);
                    },
                    error: function(error) {
                        console.error('Error saving mitra selection:', error);
                    }
                });
            } else {
                console.error('jQuery or nicepayEwalletData is not available');
            }
        };

        if (!Array.isArray(mitra) || mitra.length === 0) {
            console.log('No active mitra available');
            return null;
        }

        return createElement('div', { className: 'nicepay-ewallet-container' }, [
            createElement('div', { className: 'nicepay-ewallet-header' }, [
                createElement('img', { 
                    src: nicepayEwalletData.pluginUrl + '/assets/images/ewallet1.png', 
                    alt: 'E-wallet Options', 
                    className: 'nicepay-ewallet-image' 
                }),
            ]),
            createElement('div', { className: 'nicepay-ewallet-select' }, [
                createElement('label', { htmlFor: 'nicepay-ewallet-select' }, 'Pilih E-wallet:'),
                createElement('select',
                    {
                        name: 'nicepay_mitra',
                        id: 'nicepay-ewallet-select',
                        onChange: handleMitraChange,
                        value: selectedMitra
                    },
                    [
                        createElement('option', { value: '' }, 'Pilih E-wallet'),
                        ...mitra.map(m => createElement('option', 
                            { 
                                value: m.value || m.code, 
                                key: m.value || m.code 
                            }, 
                            m.label || m.name
                        ))
                    ]
                )
            ]),
            createElement('p', { className: 'nicepay-ewallet-instruction' }, 
                'Silakan pilih e-wallet untuk pembayaran Anda.'
            )
        ]);
    };

    // Fungsi untuk registrasi yang aman dengan delay
    const safelyRegisterEwalletPaymentMethod = function() {
        console.log("Attempting to register Ewallet Payment Method");
        
        
        if (!window.wc || !window.wc.wcBlocksRegistry) {
            console.error('WooCommerce Blocks registry tidak tersedia untuk Ewallet');
            setTimeout(safelyRegisterEwalletPaymentMethod, 300);
            return;
        }
        
        try {
            // Hindari pendaftaran berulang
            if (window.nicepay_ewallet_registered === true) {
                console.log("Ewallet already registered, skipping");
                return;
            }
            
            const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
            
            registerPaymentMethod({
                name: "nicepay_ewallet",
                label: "NICEPay E-wallet",
                content: createElement(NicepayEwalletComponent),
                edit: createElement(NicepayEwalletComponent),
                canMakePayment: () => true,
                ariaLabel: "NICEPay E-wallet payment method",
                supports: {
                    features: ['products'],
                },
            });
            
            // Set flag global
            window.nicepay_ewallet_registered = true;
            console.log("Ewallet Payment Method successfully registered");
        } catch (error) {
            console.error("Error registering Ewallet Payment Method:", error);
            console.error("Error details:", error.message);
            console.error("Error stack:", error.stack);
            
            // Retry dengan delay jika gagal
            setTimeout(safelyRegisterEwalletPaymentMethod, 500);
        }
    };

    console.log("WC Registry available:", !!window.wc && !!window.wc.wcBlocksRegistry);
    console.log("NicepayEwalletData available:", !!window.nicepayEwalletData);
    

    // Cek apakah document sudah siap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOM Content Loaded - delaying ewallet registration");
            // Registrasi dengan delay yang lebih pendek
            setTimeout(safelyRegisterEwalletPaymentMethod, 300);
        });
    } else {
        // Document sudah siap
        console.log("Document already ready - delaying ewallet registration");
        setTimeout(safelyRegisterEwalletPaymentMethod, 300);
    }
})();