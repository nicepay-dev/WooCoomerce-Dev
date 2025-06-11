<?php
if (!defined('ABSPATH')) {
    exit;
}

class NICEPay_Log_Manager {
    private $parent_slug = 'woocommerce';
    private $capability = 'manage_woocommerce';
    private $option_name = 'nicepay_va_debug_logs';
    
    public function __construct() {
        
        add_action('admin_menu', array($this, 'add_admin_menu'));

        add_action('wp_ajax_nicepay_clear_logs', array($this, 'ajax_clear_logs'));
        
        add_action('nicepay_cleanup_logs', array($this, 'cleanup_logs'));
        
        register_deactivation_hook(NICEPAY_VA_PLUGIN_FILE, array($this, 'deactivate'));
        
        $this->schedule_cleanup();
    }
    
    public function schedule_cleanup() {
        if (!wp_next_scheduled('nicepay_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'nicepay_cleanup_logs');
        }
    }
    private function test_transaction_logs() {
        $gateway_settings = get_option('woocommerce_nicepay_va_snap_settings', array());
        $debug_enabled = isset($gateway_settings['debug']) && 
                        ($gateway_settings['debug'] === 'yes' || 
                         $gateway_settings['debug'] === '1' || 
                         $gateway_settings['debug'] === true);
        
        if (!$debug_enabled) {
            return false;
        }
        
        // Buat log transaction dummy
        $test_logs = array(
            array(
                'time'    => current_time('mysql'),
                'type'    => 'info',
                'message' => 'Test log: Starting process_payment for order 12345'
            ),
            array(
                'time'    => current_time('mysql'),
                'type'    => 'info',
                'message' => 'Test log: Processing classic payment for order 12345'
            ),
            array(
                'time'    => current_time('mysql'),
                'type'    => 'info',
                'message' => 'Test log: POST data received: ' . json_encode(['nicepay_bank' => 'BMRI', 'other_data' => 'test'])
            ),
            array(
                'time'    => current_time('mysql'),
                'type'    => 'info',
                'message' => 'Test log: Selected bank from POST/session: BMRI'
            ),
            array(
                'time'    => current_time('mysql'),
                'type'    => 'info',
                'message' => 'Test log: Starting get_access_token process'
            ),
            array(
                'time'    => current_time('mysql'),
                'type'    => 'info',
                'message' => 'Test log: Access token response: {"responseCode":"2007300","responseMessage":"Successful","accessToken":"eyToken123"}'
            ),
            array(
                'time'    => current_time('mysql'),
                'type'    => 'info',
                'message' => 'Test log: Starting create_virtual_account for order 12345'
            ),
            array(
                'time'    => current_time('mysql'),
                'type'    => 'info',
                'message' => 'Test log: Create virtual account response: {"responseCode":"2002700","responseMessage":"Successful","virtualAccountData":{"virtualAccountNo":"8782345678","additionalInfo":{"bankCd":"BMRI"}}}'
            ),
            array(
                'time'    => current_time('mysql'),
                'type'    => 'info',
                'message' => 'Test log: NICEPay callback received'
            ),
            array(
                'time'    => current_time('mysql'),
                'type'    => 'info',
                'message' => 'Test log: Starting check_payment_status for order 12345'
            )
        );
        
        $logs = get_option('nicepay_va_debug_logs', array());
        $logs = array_merge($logs, $test_logs);
        update_option('nicepay_va_debug_logs', $logs);
        error_log('NICEPay test transaction logs created');
        
        return true;
    }
    private function create_test_logs() {
        error_log('NICEPay create_test_logs called');
        
        $gateway_settings = get_option('woocommerce_nicepay_va_snap_settings', array());
        $debug_enabled = isset($gateway_settings['debug']) && 
                        ($gateway_settings['debug'] === 'yes' || 
                         $gateway_settings['debug'] === '1' || 
                         $gateway_settings['debug'] === true);
        
        error_log('Debug enabled in create_test_logs: ' . ($debug_enabled ? 'yes' : 'no'));
        
        if (!$debug_enabled) {
            error_log('Debug not enabled, skipping test log creation');
            return false;
        }
        
        $logs = get_option('nicepay_va_debug_logs', array());
        $current_log_count = count($logs);
        error_log("Current log count before adding test logs: $current_log_count");
        
        // Tambahkan test log
        $test_logs = array(
            array(
                'time'    => current_time('mysql'),
                'type'    => 'info',
                'message' => 'Test log entry - info'
            ),
            array(
                'time'    => current_time('mysql'),
                'type'    => 'warning',
                'message' => 'Test log entry - warning'
            ),
            array(
                'time'    => current_time('mysql'),
                'type'    => 'error',
                'message' => 'Test log entry - error'
            )
        );
        
        $logs = array_merge($logs, $test_logs);
        $result = update_option('nicepay_va_debug_logs', $logs);
        
        error_log("Test logs added, result: " . ($result ? 'success' : 'failed'));
        error_log("New log count: " . count($logs));
        
        return true;
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('nicepay_cleanup_logs');
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            $this->parent_slug,
            __('NICEPay Logs', 'nicepay-vasnap-gateway'),
            __('NICEPay Logs', 'nicepay-vasnap-gateway'),
            $this->capability,
            'nicepay-logs',
            array($this, 'render_logs_page')
        );
        
        // Handler untuk download
        if (isset($_GET['page']) && $_GET['page'] === 'nicepay-logs' && 
            isset($_GET['action']) && $_GET['action'] === 'download_logs' && 
            isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'nicepay_download_logs')) {
            $this->download_logs();
        }
    }
    
    public function ajax_clear_logs() {
        check_ajax_referer('nicepay_clear_logs', 'nonce');
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error('Permission denied');
        }
        
        delete_option($this->option_name);
        wp_send_json_success();
    }
    
    public function cleanup_logs() {
        error_log('NICEPay cleanup_logs called at ' . current_time('mysql'));
        
        // Ambil settings dari payment gateway
        $gateway_settings = get_option('woocommerce_nicepay_va_snap_settings', array());
        $retention_days = isset($gateway_settings['log_retention']) ? (int) $gateway_settings['log_retention'] : 7;
        
        error_log('NICEPay log retention setting: ' . $retention_days . ' days');
        
        // Skip jika retention diatur "keep indefinitely"
        if ($retention_days <= 0) {
            error_log('NICEPay logs retained indefinitely, skipping cleanup');
            return;
        }
        
        $logs = get_option($this->option_name, array());
        $original_count = count($logs);
        $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        error_log('NICEPay logs cleanup - cutoff time: ' . $cutoff_time);
        
        $filtered_logs = array_filter($logs, function($log) use ($cutoff_time) {
            return $log['time'] >= $cutoff_time;
        });
        
        $new_count = count($filtered_logs);
        error_log('NICEPay logs cleanup - removed ' . ($original_count - $new_count) . ' logs');
        
        if ($new_count !== $original_count) {
            update_option($this->option_name, $filtered_logs);
            error_log('NICEPay logs cleaned up - reduced from ' . $original_count . ' to ' . $new_count);
        }
    }
    
    public function download_logs() {
        if (!current_user_can($this->capability)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'nicepay-vasnap-gateway'));
        }
        
        $logs = get_option($this->option_name, array());
        
        // Format logs sebagai CSV
        $csv = "Time,Type,Message\n";
        foreach ($logs as $log) {
            $message = str_replace('"', '""', $log['message']); // Escape double quotes
            $csv .= '"' . $log['time'] . '","' . $log['type'] . '","' . $message . "\"\n";
        }
        
        // Set headers untuk download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="nicepay_logs_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        echo $csv;
        exit;
    }
    
    public function render_logs_page() {
        error_log('NICEPay_Log_Manager::render_logs_page called');
        // Handle clear logs action
        if (isset($_GET['action']) && $_GET['action'] === 'clear_logs' && 
            isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'nicepay_clear_logs')) {
            delete_option($this->option_name);
            echo '<div class="notice notice-success"><p>' . __('Logs cleared successfully.', 'nicepay-vasnap-gateway') . '</p></div>';
        }
        if (isset($_GET['action']) && $_GET['action'] === 'test_logs' && 
         isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'nicepay_test_logs')) {
         $created = $this->create_test_logs();
         if ($created) {
        echo '<div class="notice notice-success"><p>' . __('Test logs created successfully.', 'nicepay-vasnap-gateway') . '</p></div>';
         } else {
        echo '<div class="notice notice-warning"><p>' . __('Test logs were not created. Either debug mode is disabled or logs already exist.', 'nicepay-vasnap-gateway') . '</p></div>';
         }
        }
        if (isset($_GET['action']) && $_GET['action'] === 'test_tx_logs' && 
        isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'nicepay_test_tx_logs')) {
        $created = $this->test_transaction_logs();
        if ($created) {
        echo '<div class="notice notice-success"><p>' . __('Test transaction logs created successfully.', 'nicepay-vasnap-gateway') . '</p></div>';
        } else {
        echo '<div class="notice notice-warning"><p>' . __('Test transaction logs were not created. Debug mode is disabled.', 'nicepay-vasnap-gateway') . '</p></div>';
        }
        }
        $logs = get_option($this->option_name, array());
        
        // Render the page
        ?>
        <div class="wrap">
            <h1><?php echo __('NICEPay Debug Logs', 'nicepay-vasnap-gateway'); ?></h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=nicepay-logs&action=clear_logs'), 'nicepay_clear_logs'); ?>" 
                       class="button" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to clear all logs?', 'nicepay-vasnap-gateway')); ?>');">
                       <?php echo __('Clear Logs', 'nicepay-vasnap-gateway'); ?>
                    </a>
                    
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=nicepay-logs&action=test_logs'), 'nicepay_test_logs'); ?>" 
                    class="button">
                    <?php echo __('Add Test Logs', 'nicepay-vasnap-gateway'); ?>
                    </a>

                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=nicepay-logs&action=test_tx_logs'), 'nicepay_test_tx_logs'); ?>" 
                    class="button">
                    <?php echo __('Add Transaction Logs', 'nicepay-vasnap-gateway'); ?>
                    </a>

                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=nicepay-logs&action=download_logs'), 'nicepay_download_logs'); ?>" 
                       class="button">
                       <?php echo __('Download Logs', 'nicepay-vasnap-gateway'); ?>
                    </a>
                    
                    <select id="log-filter-type">
                        <option value=""><?php echo __('All types', 'nicepay-vasnap-gateway'); ?></option>
                        <option value="info"><?php echo __('Info', 'nicepay-vasnap-gateway'); ?></option>
                        <option value="error"><?php echo __('Error', 'nicepay-vasnap-gateway'); ?></option>
                        <option value="warning"><?php echo __('Warning', 'nicepay-vasnap-gateway'); ?></option>
                    </select>
                    
                    <button class="button" id="log-filter-button"><?php echo __('Filter', 'nicepay-vasnap-gateway'); ?></button>
                </div>
                
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo sprintf(_n('%s item', '%s items', count($logs), 'nicepay-vasnap-gateway'), number_format_i18n(count($logs))); ?></span>
                </div>
                <br class="clear">
            </div>
            
            <table class="wp-list-table widefat fixed striped posts">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-time"><?php echo __('Time', 'nicepay-vasnap-gateway'); ?></th>
                        <th scope="col" class="manage-column column-type"><?php echo __('Type', 'nicepay-vasnap-gateway'); ?></th>
                        <th scope="col" class="manage-column column-message"><?php echo __('Message', 'nicepay-vasnap-gateway'); ?></th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php if (empty($logs)) : ?>
                        <tr>
                            <td colspan="3"><?php echo __('No logs found.', 'nicepay-vasnap-gateway'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach (array_reverse($logs) as $log) : ?>
                            <tr class="log-entry log-type-<?php echo esc_attr($log['type']); ?>">
                                <td class="column-time">
                                    <?php echo esc_html($log['time']); ?>
                                </td>
                                <td class="column-type">
                                    <span class="log-type log-type-<?php echo esc_attr($log['type']); ?>">
                                        <?php echo esc_html(ucfirst($log['type'])); ?>
                                    </span>
                                </td>
                                <td class="column-message">
                                    <?php 
                                    // Deteksi dan format JSON untuk keterbacaan lebih baik
                                    $message = $log['message'];
                                    if (substr($message, 0, 1) === '{' && json_decode($message)) {
                                        echo '<pre class="json-message">' . esc_html(json_encode(json_decode($message), JSON_PRETTY_PRINT)) . '</pre>';
                                    } else {
                                        echo esc_html($message);
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <style>
            .log-type {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-weight: bold;
            }
            .log-type-info {
                background-color: #e5f5fa;
                color: #0073aa;
            }
            .log-type-error {
                background-color: #fbeaea;
                color: #dc3232;
            }
            .log-type-warning {
                background-color: #fff8e5;
                color: #ffb900;
            }
            .column-time {
                width: 150px;
            }
            .column-type {
                width: 100px;
            }
            pre.json-message {
                white-space: pre-wrap;
                word-wrap: break-word;
                background: #f7f7f7;
                padding: 10px;
                margin: 0;
                max-height: 200px;
                overflow: auto;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#log-filter-button').on('click', function() {
                var filterType = $('#log-filter-type').val();
                
                if (filterType === '') {
                    $('.log-entry').show();
                } else {
                    $('.log-entry').hide();
                    $('.log-type-' + filterType).closest('tr').show();
                }
            });
        });
        </script>
        <?php
    }
    
    // Static method to write log
    public static function log($message, $type = 'info') {
        $gateway_settings = get_option('woocommerce_nicepay_va_snap_settings', array());

        error_log("NICEPay_Log_Manager - Debug setting: " . (isset($gateway_settings['debug']) ? var_export($gateway_settings['debug'], true) : 'not set'));
        error_log("NICEPay_Log_Manager - All settings: " . print_r($gateway_settings, true));
        
        $debug_enabled = isset($gateway_settings['debug']) && 
        ($gateway_settings['debug'] === 'yes' || 
         $gateway_settings['debug'] === '1' || 
         $gateway_settings['debug'] === true);

         error_log("NICEPay_Log_Manager - Debug enabled: " . ($debug_enabled ? 'yes' : 'no'));

        if (!$debug_enabled) {
        return;
        }
        
        $log_entry = array(
            'time'    => current_time('mysql'),
            'type'    => $type,
            'message' => is_array($message) || is_object($message) ? json_encode($message) : $message
        );
        
        $logs = get_option('nicepay_va_debug_logs', array());
        $current_count = count($logs);
        $logs[] = $log_entry;

        // Batasi jumlah log entries yang disimpan
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }
        
        $result = update_option('nicepay_va_debug_logs', $logs);
        error_log("NICEPay log saved: " . ($result ? 'success' : 'failed') . ", previous count: {$current_count}, new count: " . count($logs));
        
        // Untuk debugging, catat entry log yang baru saja dibuat
        $msg_excerpt = substr(is_array($message) || is_object($message) ? json_encode($message) : $message, 0, 100);
        error_log("NICEPay log entry added: {$type} - {$msg_excerpt}...");
    }
}

// Inisialisasi class
new NICEPay_Log_Manager();