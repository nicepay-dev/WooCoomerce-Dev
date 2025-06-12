(function() {
    console.log("Loading NICEPay VA Payment Method");
    
    // Gunakan React elements dari WP
    const wpElement = window.wp && window.wp.element;
    if (!wpElement) {
        console.error("WP Element not available");
        return;
    }
    
    const { createElement, useState } = wpElement;
    
    // VA Component
    const NicepayVAComponent = () => {
        const [selectedBank, setSelectedBank] = useState('');
        const [isLoading, setIsLoading] = useState(false);
        
        const banks = [
            { code: 'BMRI', name: 'Bank Mandiri' },
            { code: 'BNIN', name: 'Bank BNI' },
            { code: 'BRIN', name: 'Bank BRI' },
            { code: 'BBBA', name: 'Bank Permata' },
            { code: 'CENA', name: 'Bank BCA' },
            { code: 'IBBK', name: 'Maybank' },
            { code: 'BBBB', name: 'Bank Permata Syariah' },
            { code: 'HNBN', name: 'Bank KEB Hana Indonesia' },
            { code: 'BNIA', name: 'Bank CIMB' },
            { code: 'BDIN', name: 'Bank Danamon' },
            { code: 'PDJB', name: 'Bank BJB' },
            { code: 'YUDB', name: 'Bank Neo Commerce (BNC)' },
            { code: 'BDKI', name: 'Bank DKI' },
        ];
        useEffect(() => {
            const savedBank = sessionStorage.getItem('nicepay_selected_bank');
            if (savedBank) {
                setSelectedBank(savedBank);
            }
        }, []);
        const handleBankChange = (e) => {
            const selectedBankCode = e.target.value;
            console.log('Bank selected:', selectedBankCode);
            setSelectedBank(selectedBankCode);
            
            // Save to session storage immediately
            if (selectedBankCode) {
                sessionStorage.setItem('nicepay_selected_bank', selectedBankCode);
            } else {
                sessionStorage.removeItem('nicepay_selected_bank');
            }
            
            // Also try to save via AJAX if available
            saveBankSelection(selectedBankCode);
        };
        
         const saveBankSelection = (bankCode) => {
            console.log('Attempting to save bank selection:', bankCode);
            
            // Validate required data
            if (typeof jQuery === 'undefined') {
                console.warn('jQuery not available, using sessionStorage only');
                return;
            }
            
            if (typeof nicepayData === 'undefined') {
                console.warn('nicepayData not available, using sessionStorage only');
                return;
            }
            
            if (!nicepayData.ajax_url || !nicepayData.nonce) {
                console.warn('Required AJAX data missing, using sessionStorage only');
                return;
            }
            
            setIsLoading(true);
            
            jQuery.ajax({
                url: nicepayData.ajax_url,
                type: 'POST',
                data: {
                    action: 'set_nicepay_bank',
                    bank_code: bankCode,
                    security: nicepayData.nonce
                },
                timeout: 10000, // 10 second timeout
                success: function(response) {
                    console.log('Bank selection saved successfully:', response);
                    setIsLoading(false);
                },
                error: function(xhr, status, error) {
                    console.error('Error saving bank selection:', {
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

         return createElement('div', { className: 'nicepay-va-container' }, [
            createElement('div', { className: 'nicepay-va-header', key: 'header' }, [
                createElement('img', { 
                    src: (nicepayData?.pluginUrl || '') + 'assets/images/logobank.png', 
                    alt: 'Bank Icon', 
                    className: 'nicepay-va-bank-icon',
                    key: 'bank-icon',
                    onError: function(e) {
                        console.warn('Bank icon failed to load');
                        e.target.style.display = 'none';
                    }
                }),
            ]),
           createElement('div', { className: 'nicepay-va-bank-select', key: 'bank-select' }, [
                createElement('label', { 
                    htmlFor: 'nicepay-bank-select',
                    key: 'label'
                }, 'Pilih Bank:'),
                createElement('select',
                    {
                        name: 'nicepay_bank',
                        id: 'nicepay-bank-select',
                        onChange: handleBankChange,
                        value: selectedBank,
                        disabled: isLoading,
                        key: 'select',
                        required: true
                    },
                    [
                        createElement('option', { value: '', key: 'default' }, 'Pilih Bank'),
                        ...banks.map(bank =>
                            createElement('option', { value: bank.code, key: bank.code }, bank.name)
                        )
                    ]
                ),
                isLoading && createElement('span', { 
                    className: 'nicepay-loading',
                    key: 'loading'
                }, 'Menyimpan...')
            ]),
            createElement('p', { 
                className: 'nicepay-va-instruction',
                key: 'instruction'
            }, 'Silakan pilih bank untuk pembayaran Virtual Account Anda.'),
            
            // Hidden input for form submission
            createElement('input', {
                type: 'hidden',
                name: 'nicepay_selected_bank',
                value: selectedBank,
                key: 'hidden-input'
            })
        ]);
    };


    // Fungsi untuk registrasi yang aman
   const safelyRegisterVAPaymentMethod = function() {
        console.log("Attempting to register VA Payment Method");
        
        // Periksa apakah registry tersedia
        if (!window.wc || !window.wc.wcBlocksRegistry) {
            console.warn('WooCommerce Blocks registry not yet available for VA, retrying...');
            setTimeout(safelyRegisterVAPaymentMethod, 500);
            return;
        }
        
        try {
            // Hanya jalankan sekali menggunakan flag
            if (window.nicepay_va_registered === true) {
                console.log("VA already registered, skipping");
                return;
            }
            
            const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
            
            registerPaymentMethod({
                name: "nicepay_va",
                label: "NICEPay Virtual Account",
                content: createElement(NicepayVAComponent),
                edit: createElement(NicepayVAComponent),
                canMakePayment: () => {
                    // Check if bank is selected
                    const selectedBank = sessionStorage.getItem('nicepay_selected_bank');
                    if (!selectedBank) {
                        console.warn('No bank selected for VA payment');
                        return false;
                    }
                    return true;
                },
                ariaLabel: "NICEPay Virtual Account payment method",
                supports: {
                    features: ['products'],
                },
            });
            
            // Set flag global
            window.nicepay_va_registered = true;
            console.log("VA Payment Method successfully registered");
            
        } catch (error) {
            console.error("Error registering VA Payment Method:", error);
            console.error("Error details:", error.message);
            
            // Retry dengan delay jika gagal
            setTimeout(safelyRegisterVAPaymentMethod, 1000);
        }
    };

    // Multi-layered initialization
    const initializeVAPayment = () => {
        console.log("Initializing VA Payment Method");
        
        // Delay registration to ensure all dependencies are loaded
        setTimeout(safelyRegisterVAPaymentMethod, 100);
    };

    // Cek apakah document sudah siap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeVAPayment);
    } else {
        initializeVAPayment();
    }
    
    // Fallback: Register setelah window load
    window.addEventListener('load', function() {
        setTimeout(safelyRegisterVAPaymentMethod, 200);
    });
    
})();