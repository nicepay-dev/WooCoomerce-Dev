jQuery(document).ready(function($) {
    console.log('VA Complete JS loaded');
    console.log('Available nicepayData:', nicepayData);
    
    let selectedBank = '';
    let checkoutMode = 'classic'; // default
    
    // FITUR 1: Detect checkout mode
    function detectCheckoutMode() {
        if ($('.wc-block-checkout').length > 0) {
            checkoutMode = 'blocks';
            console.log('WooCommerce Blocks checkout detected');
        } else if ($('form.checkout').length > 0) {
            checkoutMode = 'classic';
            console.log('Classic WooCommerce checkout detected');
        }
        return checkoutMode;
    }
    
    // FITUR 2: Render bank list dengan logo (seperti kode asli)
    function renderBankList() {
        console.log('Rendering bank list');
        
        const bankContainer = $('.nicepay-va-bank-select');
        if (bankContainer.length === 0) {
            console.log('Bank container not found');
            return;
        }
        
        // Bank list dengan logo (sesuai kode asli)
        const banks = [
            { code: 'BMRI', name: 'Bank Mandiri', logo: 'mandiri.png' },
            { code: 'BNIN', name: 'Bank BNI', logo: 'bni.png' },
            { code: 'BRIN', name: 'Bank BRI', logo: 'bri.png' },
           { code: 'BBBA', name: 'Bank Permata' },
            { code: 'CENA', name: 'Bank BCA' },
            { code: 'IBBK', name: 'Maybank' },
            { code: 'BBBB', name: 'Bank Permata Syariah' },
            { code: 'HNBN', name: 'Bank KEB Hana Indonesia' },
            { code: 'BNIA', name: 'Bank CIMB' },
            { code: 'BDIN', name: 'Bank Danamon' },
            { code: 'PDJB', name: 'Bank BJB' },
            { code: 'YUDB', name: 'Bank Neo Commerce (BNC)' },
            { code: 'BDKI', name: 'Bank DKI' }
        ];
        
        // Create visual bank selection (seperti kode asli)
        let bankListHtml = '<div class="nicepay-bank-list">';
        banks.forEach(bank => {
            bankListHtml += `
                <div class="nicepay-bank-item" data-bank-code="${bank.code}">
                    <img src="${nicepayData.pluginUrl}assets/images/${bank.logo}" 
                         alt="${bank.name}" 
                         class="nicepay-bank-logo"
                         onerror="this.style.display='none'">
                    <span class="nicepay-bank-name">${bank.name}</span>
                </div>
            `;
        });
        bankListHtml += '</div>';
        
        // Insert after dropdown
        if ($('.nicepay-bank-list').length === 0) {
            bankContainer.after(bankListHtml);
        }
    }
    
    // FITUR 3: Save bank selection (diperbaiki)
    function saveBankSelection(bankCode) {
        console.log('Attempting to save bank selection:', bankCode);
        
        if (!bankCode) {
            console.log('No bank code provided');
            return;
        }
        
        selectedBank = bankCode;
        
        const ajaxData = {
            action: 'set_nicepay_bank',
            bank_code: bankCode,
            nonce: nicepayData.nonce,
            security: nicepayData.nonce
        };
        
        console.log('AJAX Data being sent:', ajaxData);
        
        $.ajax({
            url: nicepayData.ajax_url,
            type: 'POST',
            data: ajaxData,
            dataType: 'json',
            success: function(response) {
                console.log('Bank selection saved successfully:', response);
                
                // Update UI
                updateBankSelection(bankCode);
                
                // Set hidden input untuk form submission
                ensureHiddenInput(bankCode);
                
                // Session storage backup
                if (typeof(Storage) !== "undefined") {
                    sessionStorage.setItem('nicepay_selected_bank', bankCode);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error saving bank selection:', {
                    status: status,
                    error: error,
                    response: xhr.responseText,
                    statusCode: xhr.status
                });
            }
        });
    }
    
    // FITUR 4: Update UI bank selection
    function updateBankSelection(bankCode) {
        // Update dropdown
        $('#nicepay-bank-select').val(bankCode);
        
        // Update visual bank list
        $('.nicepay-bank-item').removeClass('selected');
        $(`.nicepay-bank-item[data-bank-code="${bankCode}"]`).addClass('selected');
        
        console.log('UI updated for bank:', bankCode);
    }
    
    // FITUR 5: Ensure hidden input exists
    function ensureHiddenInput(bankCode) {
        let hiddenInput = $('input[name="nicepay_bank"]');
        if (hiddenInput.length === 0) {
            const formSelector = checkoutMode === 'blocks' ? 
                'form.wc-block-checkout__form' : 'form.checkout';
            hiddenInput = $('<input type="hidden" name="nicepay_bank">');
            $(formSelector).append(hiddenInput);
            console.log('Created hidden input for', checkoutMode, 'mode');
        }
        hiddenInput.val(bankCode);
        console.log('Hidden input set with value:', bankCode);
    }
    
    // FITUR 6: Event handlers
    function setupEventHandlers() {
        // Dropdown selection
        $(document).on('change', '#nicepay-bank-select', function() {
            const bankCode = $(this).val();
            console.log('Bank selected from dropdown:', bankCode);
            
            if (bankCode) {
                saveBankSelection(bankCode);
            }
        });
        
        // Visual bank list selection
        $(document).on('click', '.nicepay-bank-item', function() {
            const bankCode = $(this).data('bank-code');
            console.log('Bank selected from visual list:', bankCode);
            
            if (bankCode) {
                saveBankSelection(bankCode);
            }
        });
        
        // FITUR 7: Checkout validation berdasarkan mode
        if (checkoutMode === 'classic') {
            $(document).on('checkout_place_order_nicepay_va', function() {
                return validateBankSelection();
            });
        } else if (checkoutMode === 'blocks') {
            // WooCommerce Blocks validation
            if (typeof wp !== 'undefined' && wp.hooks) {
                wp.hooks.addFilter(
                    'woocommerce_checkout_payment_method_data_nicepay_va',
                    'nicepay-va',
                    function(data) {
                        const bankCode = getSelectedBank();
                        if (bankCode) {
                            data.nicepay_bank = bankCode;
                        }
                        return data;
                    }
                );
            }
        }
        
        // Form submission monitoring
        $(document).on('submit', 'form.checkout, form.wc-block-checkout__form', function() {
            console.log('Form submission detected in', checkoutMode, 'mode');
            
            const bankCode = getSelectedBank();
            if (bankCode) {
                ensureHiddenInput(bankCode);
            }
        });
    }
    
    // FITUR 8: Get selected bank dari berbagai source
    function getSelectedBank() {
        return $('#nicepay-bank-select').val() || 
               $('input[name="nicepay_bank"]').val() ||
               sessionStorage.getItem('nicepay_selected_bank') ||
               selectedBank;
    }
    
    // FITUR 9: Validate bank selection
    function validateBankSelection() {
        const bankCode = getSelectedBank();
        
        console.log('Validation check for', checkoutMode, 'mode:', {
            bankCode: bankCode,
            fromSelect: $('#nicepay-bank-select').val(),
            fromHidden: $('input[name="nicepay_bank"]').val(),
            fromStorage: sessionStorage.getItem('nicepay_selected_bank')
        });
        
        if (!bankCode) {
            alert('Please select a bank for payment');
            return false;
        }
        
        ensureHiddenInput(bankCode);
        return true;
    }
    
    // FITUR 10: Restore selection on load
    function restoreSelection() {
        const savedBank = sessionStorage.getItem('nicepay_selected_bank');
        if (savedBank) {
            updateBankSelection(savedBank);
            ensureHiddenInput(savedBank);
            console.log('Restored bank selection:', savedBank);
        }
    }
    
    // INITIALIZATION
    function initialize() {
        console.log('Initializing VA payment method');
        
        // 1. Detect checkout mode
        detectCheckoutMode();
        
        // 2. Render bank list visual
        renderBankList();
        
        // 3. Setup event handlers
        setupEventHandlers();
        
        // 4. Restore previous selection
        restoreSelection();
        
        console.log('VA initialization complete for', checkoutMode, 'mode');
    }
    
    // Start initialization
    initialize();
    
    // Re-initialize on checkout update (untuk AJAX updates)
    $(document.body).on('updated_checkout', function() {
        console.log('Checkout updated - reinitializing');
        setTimeout(initialize, 100);
    });
});