/**
 * NICEPay Credit Card Block Integration
 * 
 * Mendaftarkan metode pembayaran NICEPay CC untuk WooCommerce Blocks
 */

console.log('NICEPay CC script loaded');

document.addEventListener('DOMContentLoaded', function() {
    if (window.wp && window.wc && window.wc.wcBlocksRegistry && window.wp.element) {
        console.log('Initializing NICEPay Credit Card method for blocks');
        initNicepayCC();
    } else {
        console.error('Required dependencies for NICEPay CC blocks integration not available');
    }
});

function initNicepayCC() {
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { createElement, Fragment, useState } = window.wp.element;
    const { SelectControl } = window.wp.components;

    const NicepayCCComponent = ({ eventRegistration, emitResponse }) => {
        // State untuk menyimpan opsi cicilan yang dipilih
        const [selectedInstallment, setSelectedInstallment] = useState('1');
        
        // Menyiapkan opsi cicilan dari data yang diberikan
        let installmentOptions = [
            { value: '1', label: 'Full Payment' }
        ];
        
        // Jika data installment tersedia dari script localization
        if (typeof nicepay_cc_params !== 'undefined' && nicepay_cc_params.installmentOptions) {
            installmentOptions = nicepay_cc_params.installmentOptions;
        }
        
        // Mengirim data saat pembayaran diproses
        const { onPaymentProcessing } = eventRegistration;
        
        React.useEffect(() => {
            const unsubscribe = onPaymentProcessing((processingData) => {
                const installmentData = {
                    nicepay_cc_installment: selectedInstallment
                };
                
                return {
                    type: 'success',
                    meta: {
                        paymentMethodData: {
                            nicepay_cc_data: JSON.stringify(installmentData)
                        }
                    }
                };
            });
            
            return () => {
                unsubscribe();
            };
        }, [onPaymentProcessing, selectedInstallment]);
        
        // Render komponen
        return createElement(
            'div',
            { className: 'nicepay-cc-container' },
            createElement(
                'div',
                { className: 'nicepay-cc-header' },
                createElement('img', {
                    src: nicepay_cc_params?.pluginUrl + '/assets/images/cc-logo.png',
                    className: 'nicepay-cc-icon',
                    alt: 'Credit Card'
                }),
                createElement(
                    'div',
                    { className: 'nicepay-cc-logos' },
                    createElement('img', {
                        src: nicepay_cc_params?.pluginUrl + '/assets/images/visa.png',
                        alt: 'Visa'
                    }),
                    createElement('img', {
                        src: nicepay_cc_params?.pluginUrl + '/assets/images/mastercard.png',
                        alt: 'MasterCard'
                    }),
                    createElement('img', {
                        src: nicepay_cc_params?.pluginUrl + '/assets/images/jcb.png',
                        alt: 'JCB'
                    })
                )
            ),
            installmentOptions.length > 1 && createElement(
                SelectControl,
                {
                    label: 'Choose Installment Option',
                    value: selectedInstallment,
                    options: installmentOptions,
                    onChange: (value) => {
                        setSelectedInstallment(value);
                    },
                    className: 'nicepay-cc-block-select'
                }
            ),
            installmentOptions.length === 1 && createElement(
                'div',
                { className: 'nicepay-cc-block-single-option' },
                createElement(
                    'p',
                    null,
                    installmentOptions[0].label
                ),
                createElement('input', {
                    type: 'hidden',
                    name: 'nicepay_cc_installment',
                    value: installmentOptions[0].value
                })
            ),
            createElement(
                'p',
                { className: 'nicepay-cc-block-description' },
                'You will be redirected to NICEPay secure payment page to complete your purchase.'
            )
        );
    };
    
    const NicepayCCEdit = () => {
        return createElement(
            'div',
            null,
            createElement(
                'p',
                null,
                'NICEPay Credit Card payment gateway'
            )
        );
    };

    // Registrasi metode pembayaran
    registerPaymentMethod({
        name: 'nicepay_cc',
        label: createElement(
            Fragment,
            null,
            createElement('img', {
                src: nicepay_cc_params?.pluginUrl + '/assets/images/cc-logo.png',
                alt: 'NICEPay Credit Card',
                style: {
                    maxHeight: '32px',
                    maxWidth: '100%',
                    marginRight: '8px',
                    verticalAlign: 'middle'
                }
            }),
            ' Credit Card (Pembayaran Dengan Kartu Kredit)'
        ),
        content: NicepayCCComponent,
        edit: NicepayCCEdit,
        canMakePayment: () => true,
        ariaLabel: 'Credit Card payment method',
        paymentMethodId: 'nicepay_cc',
        supports: {
            features: ['products']
        }
    });
}