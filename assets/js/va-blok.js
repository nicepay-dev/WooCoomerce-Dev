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
        
        const handleBankChange = (e) => {
            const selectedBankCode = e.target.value;
            console.log('Bank selected:', selectedBankCode);
            setSelectedBank(selectedBankCode);
            saveBankSelection(selectedBankCode);
        };
        
        const saveBankSelection = (bankCode) => {
            console.log('Attempting to save bank selection:', bankCode);
            if (typeof jQuery !== 'undefined' && typeof nicepayData !== 'undefined') {
                jQuery.ajax({
                    url: nicepayData.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'set_nicepay_bank',
                        bank_code: bankCode,
                        security: nicepayData.nonce
                    },
                    success: function(response) {
                        console.log('Bank selection saved:', response);
                    },
                    error: function(error) {
                        console.error('Error saving bank selection:', error);
                    }
                });
            } else {
                console.error('jQuery or nicepayData is not available');
            }
        };

        return createElement('div', { className: 'nicepay-va-container' }, [
            createElement('div', { className: 'nicepay-va-header' }, [
                createElement('img', { 
                    src: nicepayData.pluginUrl + 'assets/images/logobank.png', 
                    alt: 'Bank Icon', 
                    className: 'nicepay-va-bank-icon' 
                }),
            ]),
            createElement('div', { className: 'nicepay-va-bank-select' }, [
                createElement('label', { htmlFor: 'nicepay-bank-select' }, 'Pilih Bank:'),
                createElement('select',
                    {
                        name: 'nicepay_bank',
                        id: 'nicepay-bank-select',
                        onChange: handleBankChange,
                        value: selectedBank
                    },
                    [
                        createElement('option', { value: '' }, 'Pilih Bank'),
                        ...banks.map(bank =>
                            createElement('option', { value: bank.code, key: bank.code }, bank.name)
                        )
                    ]
                )
            ]),
            createElement('p', { className: 'nicepay-va-instruction' }, 'Silakan pilih bank untuk pembayaran Virtual Account Anda.')
        ]);
    };

    // Fungsi untuk registrasi yang aman
    const safelyRegisterVAPaymentMethod = function() {
        console.log("Attempting to register VA Payment Method");
        
        // Periksa apakah registry tersedia
        if (!window.wc || !window.wc.wcBlocksRegistry) {
            console.error('WooCommerce Blocks registry tidak tersedia untuk VA');
            setTimeout(safelyRegisterVAPaymentMethod, 300);
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
                canMakePayment: () => true,
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
            
            // Retry dengan delay jika gagal
            setTimeout(safelyRegisterVAPaymentMethod, 500);
        }
    };

    // Cek apakah document sudah siap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Register segera untuk VA
            safelyRegisterVAPaymentMethod();
        });
    } else {
        // Document sudah siap, register langsung
        safelyRegisterVAPaymentMethod();
    }
})();