<?php
if (!defined('ABSPATH')) {
    exit;
}

final class MsgRoute_Notifications_Advanced {
    private const OPTION_KEY = 'msgroute_notifications_advanced';
    private const LOG_TABLE = 'msgroute_notifications_logs';

    public static function boot(): void {
        add_action('admin_menu', [__CLASS__, 'register_menus'], 20);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('woocommerce_after_order_notes', [__CLASS__, 'render_checkout_optin']);
        add_action('woocommerce_checkout_update_order_meta', [__CLASS__, 'save_checkout_optin']);
        add_action('woocommerce_order_status_changed', [__CLASS__, 'order_status_changed'], 10, 4);
        register_activation_hook(dirname(__DIR__) . '/msgroute-notifications.php', [__CLASS__, 'activate']);
    }

    public static function activate(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::LOG_TABLE;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            phone varchar(40) NOT NULL,
            message text NOT NULL,
            context varchar(100) NOT NULL DEFAULT 'manual',
            status varchar(30) NOT NULL DEFAULT 'pending',
            response longtext NULL,
            error text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY context (context),
            KEY created_at (created_at)
        ) {$charset};");
        $current = get_option(self::OPTION_KEY, []);
        update_option(self::OPTION_KEY, array_merge(self::defaults(), is_array($current) ? $current : []));
    }

    private static function defaults(): array {
        return [
            'require_optin' => '1',
            'optin_label' => 'Send me order updates',
            'enabled_statuses' => ['processing', 'completed'],
            'templates' => self::default_templates(),
            'admin_enabled' => '0',
            'admin_statuses' => ['processing'],
            'admin_template' => 'Order #{order_id} is now {order_status}. Customer: {customer_name}. Phone: {customer_phone}. Total: {order_total}. Items: {order_items}.'
        ];
    }

    private static function default_templates(): array {
        return [
            'pending' => 'Hi {customer_name}, your order #{order_id} is pending payment. Amount: {order_total}.',
            'processing' => 'Hi {customer_name}, your order #{order_id} has been received. Items: {order_items}. Amount: {order_total}.',
            'on-hold' => 'Hi {customer_name}, your order #{order_id} is on hold. We will update you soon.',
            'completed' => 'Hi {customer_name}, your order #{order_id} has been completed. Thank you for shopping with {site_name}.',
            'cancelled' => 'Hi {customer_name}, your order #{order_id} has been cancelled.',
            'failed' => 'Hi {customer_name}, payment for order #{order_id} failed. Please try again or contact support.',
            'refunded' => 'Hi {customer_name}, refund update for order #{order_id}: status is {order_status}.'
        ];
    }

    private static function options(): array {
        $saved = get_option(self::OPTION_KEY, []);
        $options = array_merge(self::defaults(), is_array($saved) ? $saved : []);
        $options['templates'] = array_merge(self::default_templates(), is_array($options['templates'] ?? null) ? $options['templates'] : []);
        $options['enabled_statuses'] = is_array($options['enabled_statuses'] ?? null) ? $options['enabled_statuses'] : [];
        $options['admin_statuses'] = is_array($options['admin_statuses'] ?? null) ? $options['admin_statuses'] : [];
        return $options;
    }

    private static function base_settings(): array {
        $settings = get_option('msgroute_notifications_settings', []);
        return is_array($settings) ? $settings : [];
    }

    public static function register_menus(): void {
        add_submenu_page('msgroute-notifications', 'Message Templates by Status', 'Message Templates', 'manage_options', 'msgroute-templates', [__CLASS__, 'templates_page']);
        add_submenu_page('msgroute-notifications', 'Message Logs', 'Message Logs', 'manage_options', 'msgroute-logs', [__CLASS__, 'logs_page']);
        add_submenu_page('msgroute-notifications', 'Admin Notification', 'Admin Notification', 'manage_options', 'msgroute-admin-notification', [__CLASS__, 'admin_notification_page']);
        add_submenu_page('msgroute-notifications', 'Customer Opt-in Checkbox', 'Customer Opt-in', 'manage_options', 'msgroute-optin', [__CLASS__, 'optin_page']);
        add_submenu_page('msgroute-notifications', 'Connection Status', 'Connection Status', 'manage_options', 'msgroute-connection-status', [__CLASS__, 'connection_status_page']);
        add_submenu_page('msgroute-notifications', 'Bulk Message Sender', 'Bulk Sender', 'manage_options', 'msgroute-bulk-sender', [__CLASS__, 'bulk_sender_page']);
    }

    public static function register_settings(): void {
        register_setting('msgroute_notifications_advanced_group', self::OPTION_KEY, ['type' => 'array', 'sanitize_callback' => [__CLASS__, 'sanitize'], 'default' => self::defaults()]);
    }

    public static function sanitize($input): array {
        $input = is_array($input) ? $input : [];
        $current = self::options();
        $statuses = array_keys(self::statuses());
        $templates = [];
        foreach ($statuses as $status) {
            $templates[$status] = sanitize_textarea_field((string)($input['templates'][$status] ?? $current['templates'][$status] ?? self::default_templates()[$status] ?? ''));
        }
        return [
            'require_optin' => array_key_exists('optin_label', $input) ? (array_key_exists('require_optin', $input) ? '1' : '0') : ($current['require_optin'] ?? '0'),
            'optin_label' => sanitize_text_field((string)($input['optin_label'] ?? $current['optin_label'] ?? self::defaults()['optin_label'])),
            'enabled_statuses' => array_key_exists('templates', $input) ? array_values(array_intersect(array_map('sanitize_key', (array)($input['enabled_statuses'] ?? [])), $statuses)) : ($current['enabled_statuses'] ?? []),
            'templates' => $templates,
            'admin_enabled' => array_key_exists('admin_template', $input) ? (array_key_exists('admin_enabled', $input) ? '1' : '0') : ($current['admin_enabled'] ?? '0'),
            'admin_statuses' => array_key_exists('admin_template', $input) ? array_values(array_intersect(array_map('sanitize_key', (array)($input['admin_statuses'] ?? [])), $statuses)) : ($current['admin_statuses'] ?? []),
            'admin_template' => sanitize_textarea_field((string)($input['admin_template'] ?? $current['admin_template'] ?? self::defaults()['admin_template']))
        ];
    }

    private static function statuses(): array {
        if (function_exists('wc_get_order_statuses')) {
            $out = [];
            foreach (wc_get_order_statuses() as $key => $label) {
                $out[str_replace('wc-', '', $key)] = $label;
            }
            return $out;
        }
        return ['pending' => 'Pending payment', 'processing' => 'Processing', 'on-hold' => 'On hold', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded', 'failed' => 'Failed'];
    }
    private static function header(string $title, string $description): void {
        echo '<div class="wrap msgroute-wrap"><div class="msgroute-header"><div><h1>' . esc_html($title) . '</h1><p>' . esc_html($description) . '</p></div><span class="msgroute-pill">MsgRoute</span></div>';
    }

    private static function footer(): void { echo '</div>'; }

    private static function variables_list(): void {
        $vars = ['{order_id}', '{customer_name}', '{customer_phone}', '{order_total}', '{site_name}', '{order_status}', '{order_items}', '{order_items_count}', '{billing_email}', '{shipping_address}', '{payment_method}'];
        echo '<ul class="msgroute-vars">';
        foreach ($vars as $var) echo '<li><code>' . esc_html($var) . '</code></li>';
        echo '</ul>';
    }

    public static function templates_page(): void {
        if (!current_user_can('manage_options')) return;
        $options = self::options();
        self::header('Message Templates by Status', 'Enable message templates for each WooCommerce order status. Preview updates live and variables include full order item list.');
        echo '<form method="post" action="options.php" class="msgroute-grid">';
        settings_fields('msgroute_notifications_advanced_group');
        echo '<section class="msgroute-card msgroute-wide"><h2>Variables</h2>';
        self::variables_list();
        echo '</section>';
        foreach (self::statuses() as $status => $label) {
            echo '<section class="msgroute-card"><h2>' . esc_html($label) . '</h2>';
            echo '<label class="msgroute-check"><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[enabled_statuses][]" value="' . esc_attr($status) . '" ' . checked(in_array($status, $options['enabled_statuses'], true), true, false) . '><span>Enable customer message for this status</span></label>';
            echo '<label><span>Template</span><textarea class="msgroute-template-input" data-preview="preview-' . esc_attr($status) . '" name="' . esc_attr(self::OPTION_KEY) . '[templates][' . esc_attr($status) . ']" rows="5">' . esc_textarea($options['templates'][$status] ?? '') . '</textarea></label>';
            echo '<div class="msgroute-preview"><strong>Template Preview</strong><p id="preview-' . esc_attr($status) . '"></p></div>';
            echo '</section>';
        }
        echo '<section class="msgroute-card msgroute-wide">';
        submit_button('Save Templates', 'primary', 'submit', false);
        echo '</section></form>';
        self::footer();
    }

    public static function admin_notification_page(): void {
        if (!current_user_can('manage_options')) return;
        $options = self::options();
        $base = self::base_settings();
        self::header('Admin Notification', 'Send message alerts to the admin number when selected order statuses happen.');
        echo '<form method="post" action="options.php" class="msgroute-grid">';
        settings_fields('msgroute_notifications_advanced_group');
        echo '<section class="msgroute-card"><h2>Admin Alerts</h2>';
        echo '<label class="msgroute-check"><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[admin_enabled]" value="1" ' . checked($options['admin_enabled'], '1', false) . '><span>Enable admin message alerts</span></label>';
        echo '<p><strong>Admin number:</strong> ' . esc_html($base['admin_phone'] ?? 'Not set') . '</p><p class="description">Admin number is managed on the main Settings page.</p></section>';
        echo '<section class="msgroute-card"><h2>Trigger Statuses</h2>';
        foreach (self::statuses() as $status => $label) {
            echo '<label class="msgroute-check"><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[admin_statuses][]" value="' . esc_attr($status) . '" ' . checked(in_array($status, $options['admin_statuses'], true), true, false) . '><span>' . esc_html($label) . '</span></label>';
        }
        echo '</section><section class="msgroute-card msgroute-wide"><h2>Admin Message Template</h2>';
        self::variables_list();
        echo '<label><span>Message</span><textarea class="msgroute-template-input" data-preview="admin-preview" name="' . esc_attr(self::OPTION_KEY) . '[admin_template]" rows="5">' . esc_textarea($options['admin_template']) . '</textarea></label>';
        echo '<div class="msgroute-preview"><strong>Template Preview</strong><p id="admin-preview"></p></div>';
        submit_button('Save Admin Notification', 'primary', 'submit', false);
        echo '</section></form>';
        self::footer();
    }


    public static function optin_page(): void {
        if (!current_user_can('manage_options')) return;
        $options = self::options();
        self::header('Customer Opt-in Checkbox', 'Show a checkout checkbox and send customer order message updates only after consent.');
        echo '<form method="post" action="options.php" class="msgroute-grid">';
        settings_fields('msgroute_notifications_advanced_group');
        echo '<section class="msgroute-card msgroute-wide"><h2>Checkout Consent</h2>';
        echo '<label class="msgroute-check"><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[require_optin]" value="1" ' . checked($options['require_optin'], '1', false) . '><span>Require customer opt-in before sending order notifications</span></label>';
        echo '<label><span>Checkbox label</span><input type="text" name="' . esc_attr(self::OPTION_KEY) . '[optin_label]" value="' . esc_attr($options['optin_label']) . '"></label>';
        echo '<p class="description">The opt-in value is saved on the WooCommerce order as <code>_msgroute_notifications_optin</code>.</p>';
        submit_button('Save Opt-in Settings', 'primary', 'submit', false);
        echo '</section></form>';
        self::footer();
    }
    public static function connection_status_page(): void {
        if (!current_user_can('manage_options')) return;
        $base = self::base_settings();
        $status = self::connection_status();
        self::header('Connection Status', 'Check API key validity and messaging session state from WordPress.');
        echo '<div class="msgroute-grid"><section class="msgroute-card"><h2>API Configuration</h2><p><strong>Base URL:</strong> ' . esc_html($base['api_base_url'] ?? '') . '</p><p><strong>API Key:</strong> ' . (!empty($base['api_key']) ? esc_html(substr($base['api_key'], 0, 14) . '...') : 'Missing') . '</p></section>';
        echo '<section class="msgroute-card"><h2>Live Status</h2>';
        if (is_wp_error($status)) echo '<p class="msgroute-result error">' . esc_html($status->get_error_message()) . '</p>';
        else echo '<p class="msgroute-result success">API connected.</p><pre>' . esc_html(wp_json_encode($status, JSON_PRETTY_PRINT)) . '</pre>';
        echo '</section></div>';
        self::footer();
    }

    public static function logs_page(): void {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $table = $wpdb->prefix . self::LOG_TABLE;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['msgroute_clear_logs_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['msgroute_clear_logs_nonce'])), 'msgroute_clear_logs')) {
            $wpdb->query("TRUNCATE TABLE {$table}");
            echo '<div class="notice notice-success"><p>Message logs cleared.</p></div>';
        }
        $logs = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 100");
        self::header('Message Logs', 'Review outgoing message attempts from WordPress. Incoming messages are not stored here.');
        echo '<section class="msgroute-card msgroute-wide"><form method="post" class="msgroute-toolbar">';
        wp_nonce_field('msgroute_clear_logs', 'msgroute_clear_logs_nonce');
        echo '<button class="button button-secondary">Clear Logs</button></form><div class="msgroute-table-wrap"><table class="widefat striped"><thead><tr><th>Time</th><th>Phone</th><th>Context</th><th>Status</th><th>Message/Error</th></tr></thead><tbody>';
        if (!$logs) echo '<tr><td colspan="5">No message logs yet.</td></tr>';
        foreach ($logs as $log) {
            echo '<tr><td>' . esc_html($log->created_at) . '</td><td>' . esc_html($log->phone) . '</td><td>' . esc_html($log->context) . '</td><td><span class="msgroute-status msgroute-status-' . esc_attr($log->status) . '">' . esc_html($log->status) . '</span></td><td><strong>' . esc_html(wp_trim_words($log->message, 18)) . '</strong>' . ($log->error ? '<br><span class="msgroute-error">' . esc_html($log->error) . '</span>' : '') . '</td></tr>';
        }
        echo '</tbody></table></div></section>';
        self::footer();
    }

    public static function bulk_sender_page(): void {
        if (!current_user_can('manage_options')) return;
        $notice = '';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['msgroute_bulk_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['msgroute_bulk_nonce'])), 'msgroute_bulk_send')) {
            $notice = self::handle_bulk_send();
        }
        self::header('Bulk Message Sender', 'Send a manual message broadcast to selected phone numbers. Use only for opted-in recipients.');
        if ($notice) echo wp_kses_post($notice);
        echo '<form method="post" class="msgroute-grid"><section class="msgroute-card msgroute-wide"><h2>Recipients</h2>';
        wp_nonce_field('msgroute_bulk_send', 'msgroute_bulk_nonce');
        echo '<label><span>Phone numbers, one per line or comma separated</span><textarea name="phones" rows="7" placeholder="919898989898&#10;919696969696"></textarea></label><label><span>Message</span><textarea name="message" rows="5" placeholder="Hello from MsgRoute"></textarea></label><p class="description">Bulk sender logs every attempt in Message Logs.</p>';
        submit_button('Send Bulk Message', 'primary', 'submit', false);
        echo '</section></form>';
        self::footer();
    }

    public static function render_checkout_optin($checkout): void {
        if (!function_exists('woocommerce_form_field')) return;
        $options = self::options();
        woocommerce_form_field('msgroute_notifications_optin', ['type' => 'checkbox', 'class' => ['form-row-wide'], 'label' => $options['optin_label'], 'required' => false], $checkout->get_value('msgroute_notifications_optin'));
    }

    public static function save_checkout_optin($order_id): void {
        update_post_meta($order_id, '_msgroute_notifications_optin', !empty($_POST['msgroute_notifications_optin']) ? 'yes' : 'no');
    }

    public static function order_status_changed($order_id, $old_status, $new_status, $order): void {
        if (!$order || !is_object($order)) return;
        $options = self::options();
        if (in_array($new_status, $options['enabled_statuses'], true)) {
            if ($options['require_optin'] !== '1' || $order->get_meta('_msgroute_notifications_optin') === 'yes') {
                self::send_order_message($order, $options['templates'][$new_status] ?? '', 'order_' . $new_status);
            }
        }
        $base = self::base_settings();
        if ($options['admin_enabled'] === '1' && !empty($base['admin_phone']) && in_array($new_status, $options['admin_statuses'], true)) {
            self::send_message(self::normalize_phone($base['admin_phone']), self::render_template($options['admin_template'], $order), 'admin_order_' . $new_status);
        }
    }

    private static function send_order_message($order, string $template, string $context) {
        $phone = self::normalize_phone((string)$order->get_billing_phone());
        if ($phone === '') return new WP_Error('msgroute_missing_phone', 'Order billing phone is empty.');
        return self::send_message($phone, self::render_template($template, $order), $context);
    }

    private static function handle_bulk_send(): string {
        $phones_raw = sanitize_textarea_field((string)wp_unslash($_POST['phones'] ?? ''));
        $message = sanitize_textarea_field((string)wp_unslash($_POST['message'] ?? ''));
        $phones = array_filter(array_map([__CLASS__, 'normalize_phone'], preg_split('/[\r\n,]+/', $phones_raw)));
        if (!$phones || $message === '') return '<div class="notice notice-error"><p>Recipients and message are required.</p></div>';
        $phones = array_slice(array_unique($phones), 0, 100);
        $sent = 0; $failed = 0;
        foreach ($phones as $phone) {
            $result = self::send_message($phone, $message, 'bulk');
            is_wp_error($result) ? $failed++ : $sent++;
        }
        return '<div class="notice notice-success"><p>' . esc_html(sprintf('Bulk send complete. Sent: %d, Failed: %d', $sent, $failed)) . '</p></div>';
    }

    public static function send_manual_message(string $phone, string $message, string $context = 'manual') {
        return self::send_message($phone, $message, $context);
    }

    private static function send_message(string $phone, string $message, string $context = 'manual') {
        $base = self::base_settings();
        if (empty($base['api_base_url']) || empty($base['api_key'])) {
            self::log_message($phone, $message, $context, 'failed', null, 'MsgRoute API URL and key are required.');
            return new WP_Error('msgroute_not_configured', 'MsgRoute API URL and key are required.');
        }
        $response = wp_remote_post(esc_url_raw(rtrim($base['api_base_url'], '/')) . '/send', ['timeout' => 25, 'headers' => ['Content-Type' => 'application/json', 'x-api-key' => $base['api_key']], 'body' => wp_json_encode(['to' => $phone, 'message' => $message])]);
        if (is_wp_error($response)) {
            self::log_message($phone, $message, $context, 'failed', null, $response->get_error_message());
            return $response;
        }
        $code = (int)wp_remote_retrieve_response_code($response);
        $body_raw = (string)wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);
        if ($code < 200 || $code >= 300 || empty($body['success'])) {
            $error = isset($body['error']['message']) ? $body['error']['message'] : 'MsgRoute API request failed.';
            self::log_message($phone, $message, $context, 'failed', $body_raw, $error);
            return new WP_Error('msgroute_api_failed', $error);
        }
        self::log_message($phone, $message, $context, 'sent', $body_raw, null);
        return $body;
    }

    private static function connection_status() {
        $base = self::base_settings();
        if (empty($base['api_base_url']) || empty($base['api_key'])) return new WP_Error('msgroute_not_configured', 'MsgRoute API URL and key are required.');
        $response = wp_remote_get(esc_url_raw(rtrim($base['api_base_url'], '/')) . '/status', ['timeout' => 20, 'headers' => ['x-api-key' => $base['api_key']]]);
        if (is_wp_error($response)) return $response;
        $code = (int)wp_remote_retrieve_response_code($response);
        $body = json_decode((string)wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || empty($body['success'])) return new WP_Error('msgroute_status_failed', isset($body['error']['message']) ? $body['error']['message'] : 'Connection check failed.');
        return $body['data'] ?? $body;
    }

    private static function log_message(string $phone, string $message, string $context, string $status, ?string $response, ?string $error): void {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . self::LOG_TABLE, ['phone' => $phone, 'message' => $message, 'context' => $context, 'status' => $status, 'response' => $response, 'error' => $error, 'created_at' => current_time('mysql')], ['%s','%s','%s','%s','%s','%s','%s']);
    }

    private static function render_template(string $template, $order): string {
        $items = []; $count = 0;
        foreach ($order->get_items() as $item) {
            $qty = method_exists($item, 'get_quantity') ? (int)$item->get_quantity() : 1;
            $name = method_exists($item, 'get_name') ? $item->get_name() : '';
            $items[] = trim($name . ' x ' . $qty);
            $count += $qty;
        }
        $replacements = ['{order_id}' => (string)$order->get_order_number(), '{customer_name}' => trim((string)$order->get_billing_first_name() . ' ' . (string)$order->get_billing_last_name()), '{customer_phone}' => (string)$order->get_billing_phone(), '{order_total}' => wp_strip_all_tags($order->get_formatted_order_total()), '{site_name}' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES), '{order_status}' => function_exists('wc_get_order_status_name') ? wc_get_order_status_name($order->get_status()) : $order->get_status(), '{order_items}' => implode(', ', $items), '{order_items_count}' => (string)$count, '{billing_email}' => (string)$order->get_billing_email(), '{shipping_address}' => wp_strip_all_tags($order->get_formatted_shipping_address()), '{payment_method}' => (string)$order->get_payment_method_title()];
        return strtr($template, $replacements);
    }

    public static function normalize_phone(string $phone): string {
        $base = self::base_settings();
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') return '';
        if (strlen($digits) === 10 && !empty($base['default_country_code'])) return $base['default_country_code'] . $digits;
        return $digits;
    }
}

MsgRoute_Notifications_Advanced::boot();


