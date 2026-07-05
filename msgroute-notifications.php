<?php
/**
 * Plugin Name: MsgRoute Notifications
 * Description: Connect WordPress and WooCommerce with the MsgRoute REST API for transactional message delivery.
 * Version: 1.1.0
 * Author: MsgRoute
 * License: GPL-2.0-or-later
 * Text Domain: msgroute-notifications
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/advanced.php';

final class MsgRoute_Notifications_Plugin {
    private const OPTION_KEY = 'msgroute_notifications_settings';
    private const NONCE_ACTION = 'msgroute_notifications_save_settings';
    private const TEST_ACTION = 'msgroute_notifications_send_test';
    private const VERSION = '1.1.0';

    private static $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('wp_ajax_msgroute_notifications_send_test', [$this, 'ajax_send_test_message']);
        add_shortcode('msgroute_message_button', [$this, 'render_message_button_shortcode']);
    }

    public static function activate(): void {
        $defaults = self::default_settings();
        $current = get_option(self::OPTION_KEY);
        if (!is_array($current)) {
            add_option(self::OPTION_KEY, $defaults);
            return;
        }
        update_option(self::OPTION_KEY, array_merge($defaults, $current));
    }

    private static function default_settings(): array {
        return [
            'api_base_url' => '',
            'api_key' => '',
            'default_country_code' => '91',
            'admin_phone' => ''
        ];
    }

    private function settings(): array {
        $saved = get_option(self::OPTION_KEY, []);
        return array_merge(self::default_settings(), is_array($saved) ? $saved : []);
    }

    public function register_admin_menu(): void {
        add_menu_page(
            __('MsgRoute Notifications', 'msgroute-notifications'),
            __('MsgRoute', 'msgroute-notifications'),
            'manage_options',
            'msgroute-notifications',
            [$this, 'render_settings_page'],
            'dashicons-email-alt2',
            81
        );
        add_submenu_page('msgroute-notifications', __('Settings', 'msgroute-notifications'), __('Settings', 'msgroute-notifications'), 'manage_options', 'msgroute-notifications', [$this, 'render_settings_page']);
    }

    public function register_settings(): void {
        register_setting('msgroute_notifications_group', self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => self::default_settings()
        ]);
    }

    public function sanitize_settings($input): array {
        $input = is_array($input) ? $input : [];
        $defaults = self::default_settings();

        return [
            'api_base_url' => esc_url_raw(rtrim((string)($input['api_base_url'] ?? $defaults['api_base_url']), '/')),
            'api_key' => sanitize_text_field((string)($input['api_key'] ?? '')),
            'default_country_code' => preg_replace('/\D+/', '', (string)($input['default_country_code'] ?? '91')) ?: '91',
            'admin_phone' => $this->normalize_phone((string)($input['admin_phone'] ?? ''))
        ];
    }

    public function enqueue_admin_assets(string $hook): void {
        if (strpos($hook, 'msgroute') === false) {
            return;
        }
        wp_enqueue_style('msgroute-notifications-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], self::VERSION);
        wp_enqueue_script('msgroute-notifications-admin', plugin_dir_url(__FILE__) . 'assets/admin.js', ['jquery'], self::VERSION, true);
        wp_localize_script('msgroute-notifications-admin', 'MsgRouteNotifications', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::TEST_ACTION)
        ]);
    }

    public function enqueue_frontend_assets(): void {
        wp_enqueue_style('msgroute-notifications-frontend', plugin_dir_url(__FILE__) . 'assets/frontend.css', [], self::VERSION);
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = $this->settings();
        ?>
        <div class="wrap msgroute-wrap">
            <div class="msgroute-header">
                <div>
                    <h1><?php esc_html_e('MsgRoute Notifications', 'msgroute-notifications'); ?></h1>
                    <p><?php esc_html_e('Send customer messages from WordPress and WooCommerce through your MsgRoute API key.', 'msgroute-notifications'); ?></p>
                </div>
                <span class="msgroute-pill"><?php esc_html_e('Connected by REST API', 'msgroute-notifications'); ?></span>
            </div>

            <div class="msgroute-settings-grid">
                <form method="post" action="options.php" class="msgroute-card msgroute-api-form">
                    <?php settings_fields('msgroute_notifications_group'); ?>
                    <h2><?php esc_html_e('API Connection', 'msgroute-notifications'); ?></h2>
                    <label>
                        <span><?php esc_html_e('API Base URL', 'msgroute-notifications'); ?></span>
                        <input type="url" name="<?php echo esc_attr(self::OPTION_KEY); ?>[api_base_url]" value="<?php echo esc_attr($settings['api_base_url']); ?>" required>
                    </label>
                    <label>
                        <span><?php esc_html_e('Active API Key', 'msgroute-notifications'); ?></span>
                        <input type="password" name="<?php echo esc_attr(self::OPTION_KEY); ?>[api_key]" value="<?php echo esc_attr($settings['api_key']); ?>" autocomplete="off" required>
                    </label>
                    <label>
                        <span><?php esc_html_e('Default Country Code', 'msgroute-notifications'); ?></span>
                        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_country_code]" value="<?php echo esc_attr($settings['default_country_code']); ?>" inputmode="numeric">
                    </label>
                    <label>
                        <span><?php esc_html_e('Admin Recipient Number', 'msgroute-notifications'); ?></span>
                        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[admin_phone]" value="<?php echo esc_attr($settings['admin_phone']); ?>" inputmode="tel">
                    </label>
                    <?php submit_button(__('Save Settings', 'msgroute-notifications'), 'primary', 'submit', false); ?>
                </form>

                <section class="msgroute-card msgroute-test-card">
                    <h2><?php esc_html_e('Send Test Message', 'msgroute-notifications'); ?></h2>
                    <div class="msgroute-test-row">
                        <input type="text" id="msgroute-test-phone" placeholder="919898989898" inputmode="tel">
                        <textarea id="msgroute-test-message" rows="5">MsgRoute test message from WordPress.</textarea>
                        <button type="button" class="button button-secondary" id="msgroute-send-test"><?php esc_html_e('Send Test', 'msgroute-notifications'); ?></button>
                    </div>
                    <p id="msgroute-test-result" class="msgroute-result"></p>
                </section>
            </div>
        </div>
        <?php
    }

    public function ajax_send_test_message(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'msgroute-notifications')], 403);
        }
        check_ajax_referer(self::TEST_ACTION, 'nonce');

        $phone = $this->normalize_phone((string)wp_unslash($_POST['phone'] ?? ''));
        $message = sanitize_textarea_field((string)wp_unslash($_POST['message'] ?? ''));
        if ($phone === '' || $message === '') {
            wp_send_json_error(['message' => __('Phone and message are required.', 'msgroute-notifications')], 422);
        }

        $response = class_exists('MsgRoute_Notifications_Advanced') ? MsgRoute_Notifications_Advanced::send_manual_message($phone, $message, 'test') : $this->send_message($phone, $message);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }
        wp_send_json_success(['message' => __('Message sent successfully.', 'msgroute-notifications'), 'response' => $response]);
    }
    private function send_message(string $phone, string $message) {
        $settings = $this->settings();
        if ($settings['api_base_url'] === '' || $settings['api_key'] === '') {
            return new WP_Error('msgroute_not_configured', __('MsgRoute API URL and key are required.', 'msgroute-notifications'));
        }

        $response = wp_remote_post(esc_url_raw(rtrim($settings['api_base_url'], '/')) . '/send', [
            'timeout' => 25,
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $settings['api_key']
            ],
            'body' => wp_json_encode([
                'to' => $phone,
                'message' => $message
            ])
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int)wp_remote_retrieve_response_code($response);
        $body = json_decode((string)wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || empty($body['success'])) {
            $message = isset($body['error']['message']) ? $body['error']['message'] : __('MsgRoute API request failed.', 'msgroute-notifications');
            return new WP_Error('msgroute_api_failed', $message, ['status' => $code, 'body' => $body]);
        }

        return $body;
    }
    private function normalize_phone(string $phone): string {
        $settings = $this->settings();
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 10 && !empty($settings['default_country_code'])) {
            return $settings['default_country_code'] . $digits;
        }
        return $digits;
    }

    public function render_message_button_shortcode($atts): string {
        $atts = shortcode_atts([
            'phone' => '',
            'text' => 'Hello, I need help.',
            'label' => 'Chat with us'
        ], $atts, 'msgroute_notifications_button');

        $phone = $this->normalize_phone((string)$atts['phone']);
        if ($phone === '') {
            $settings = $this->settings();
            $phone = $settings['admin_phone'];
        }
        if ($phone === '') {
            return '';
        }

        $url = 'https://wa.me/' . rawurlencode($phone) . '?text=' . rawurlencode((string)$atts['text']);
        return '<a class="msgroute-notifications-button" href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html((string)$atts['label']) . '</a>';
    }
}

register_activation_hook(__FILE__, ['MsgRoute_Notifications_Plugin', 'activate']);
MsgRoute_Notifications_Plugin::instance();



