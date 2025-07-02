
(function() {
    console.log("Loading NICEPay Ewallet Plugin");
    
    // Gunakan React elements dari WP
     const wpElement = window.wp && window.wp.element;
    if (!wpElement) {
        console.error("WP Element not available");
        return;
    }
    
    const { createElement, useState, useEffect } = wpElement;
    
    // Ewallet Component
    const NicepayEwalletComponent = () => {
        const [selectedMitra, setSelectedMitra] = useState('');
        const [isLoading, setIsLoading] = useState(false);
        // Gunakan nicepayEwalletData untuk blocks
        const mitra = window.nicepayEwalletData?.enabled_mitra || [];
        console.log('Available mitra (Ewallet):', mitra);

          // Load saved selection on mount
        useEffect(() => {
            const savedMitra = sessionStorage.getItem('nicepay_selected_mitra');
            if (savedMitra) {
                setSelectedMitra(savedMitra);
            }
        }, []);

        const handleMitraChange = (e) => {
            const selectedMitraCode = e.target.value;
            console.log('Mitra selected:', selectedMitraCode);
            setSelectedMitra(selectedMitraCode);
            
            // Save to session storage immediately
            if (selectedMitraCode) {
                sessionStorage.setItem('nicepay_selected_mitra', selectedMitraCode);
            } else {
                sessionStorage.removeItem('nicepay_selected_mitra');
            }
            
            // Also try to save via AJAX if available
            saveMitraSelection(selectedMitraCode);
        };

        const saveMitraSelection = (mitraCode) => {
            console.log('Attempting to save mitra selection:', mitraCode);
            
            // Validate required data
            if (typeof jQuery === 'undefined') {
                console.warn('jQuery not available, using sessionStorage only');
                return;
            }
            
            if (typeof nicepayEwalletData === 'undefined') {
                console.warn('nicepayEwalletData not available, using sessionStorage only');
                return;
            }
            
            if (!nicepayEwalletData.ajax_url || !nicepayEwalletData.nonce) {
                console.warn('Required AJAX data missing, using sessionStorage only');
                return;
            }
            
            setIsLoading(true);
            
            jQuery.ajax({
                url: nicepayEwalletData.ajax_url,
                type: 'POST',
                data: {
                    action: 'set_nicepay_mitra',
                    mitra_code: mitraCode,
                    nonce: nicepayEwalletData.nonce
                },
                timeout: 10000, // 10 second timeout
                success: function(response) {
                    console.log('Mitra selection saved successfully:', response);
                    setIsLoading(false);
                },
                error: function(xhr, status, error) {
                    console.error('Error saving mitra selection:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: error
                    });
                    setIsLoading(false);
                    
                    // Even if AJAX fails, sessionStorage should work
                    console.log('AJAX failed, but sessionStorage should still work');
                }
            });
        };


         if (!Array.isArray(mitra) || mitra.length === 0) {
            console.log('No active mitra available');
            return createElement('div', { className: 'nicepay-ewallet-container' }, [
                createElement('p', { key: 'no-mitra' }, 'Tidak ada e-wallet yang tersedia saat ini.')
            ]);
        }
         return createElement('div', { className: 'nicepay-ewallet-container' }, [
            createElement('div', { className: 'nicepay-ewallet-header', key: 'header' }, [
                createElement('img', { 
                    src: (nicepayEwalletData?.pluginUrl || '') + '/assets/images/ewallet1.png', 
                    alt: 'E-wallet Options', 
                    className: 'nicepay-ewallet-image',
                    key: 'ewallet-icon',
                    onError: function(e) {
                        console.warn('E-wallet icon failed to load');
                        e.target.style.display = 'none';
                    }
                }),
            ]),
            createElement('div', { className: 'nicepay-ewallet-select', key: 'ewallet-select' }, [
                createElement('label', { 
                    htmlFor: 'nicepay-ewallet-select',
                    key: 'label'
                }, 'Pilih E-wallet:'),
                createElement('select',
                    {
                        name: 'nicepay_mitra',
                        id: 'nicepay-ewallet-select',
                        onChange: handleMitraChange,
                        value: selectedMitra,
                        disabled: isLoading,
                        key: 'select',
                        required: true
                    },
                    [
                        createElement('option', { value: '', key: 'default' }, 'Pilih E-wallet'),
                        ...mitra.map(m => createElement('option', 
                            { 
                                value: m.value || m.code, 
                                key: m.value || m.code 
                            }, 
                            m.label || m.name
                        ))
                    ]
                ),
                isLoading && createElement('span', { 
                    className: 'nicepay-loading',
                    key: 'loading'
                }, 'Menyimpan...')
            ]),
            createElement('p', { 
                className: 'nicepay-ewallet-instruction',
                key: 'instruction'
            }, 'Silakan pilih e-wallet untuk pembayaran Anda.'),
            
            // Hidden input for form submission
            createElement('input', {
                type: 'hidden',
                name: 'nicepay_selected_mitra',
                value: selectedMitra,
                key: 'hidden-input'
            })
        ]);
    };

  const safelyRegisterEwalletPaymentMethod = function() {
        console.log("Attempting to register Ewallet Payment Method");
        
        if (!window.wc || !window.wc.wcBlocksRegistry) {
            console.warn('WooCommerce Blocks registry not yet available for Ewallet, retrying...');
            setTimeout(safelyRegisterEwalletPaymentMethod, 500);
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
                canMakePayment: () => {
                    // Check if mitra is selected
                    const selectedMitra = sessionStorage.getItem('nicepay_selected_mitra');
                    if (!selectedMitra) {
                        console.warn('No e-wallet selected');
                        return false;
                    }
                    return true;
                },
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
            setTimeout(safelyRegisterEwalletPaymentMethod, 1000);
        }
    };

    // Multi-layered initialization
    const initializeEwalletPayment = () => {
        console.log("Initializing E-wallet Payment Method");
        console.log("WC Registry available:", !!window.wc && !!window.wc.wcBlocksRegistry);
        console.log("NicepayEwalletData available:", !!window.nicepayEwalletData);
        
        // Delay registration to ensure all dependencies are loaded
        setTimeout(safelyRegisterEwalletPaymentMethod, 100);
    };

    // Cek apakah document sudah siap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeEwalletPayment);
    } else {
        initializeEwalletPayment();
    }
    
    // Fallback: Register setelah window load
    window.addEventListener('load', function() {
        setTimeout(safelyRegisterEwalletPaymentMethod, 200);
    });
    
})();