=== MsgRoute WhatsApp ===
Contributors: anshul
Tags: whatsapp, woocommerce, notifications, messaging, automation
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send WooCommerce order updates on WhatsApp, admin alerts, and bulk WhatsApp messages through a MsgRoute REST API key.

== Description ==

MsgRoute WhatsApp connects WordPress and WooCommerce to a configured MsgRoute WhatsApp REST API endpoint. It helps store owners send outgoing WhatsApp messages for order status changes, admin alerts, manual test messages, and selected bulk notifications.

The plugin is designed for outgoing business notifications. It does not store incoming WhatsApp messages.

= Key features =

* MsgRoute API connection settings.
* Send a test WhatsApp message from WordPress admin.
* WooCommerce order status templates.
* Live template preview with supported variables.
* Customer opt-in checkbox on WooCommerce checkout.
* Admin WhatsApp alerts for selected order statuses.
* Message logs for outgoing attempts and API errors.
* Connection status check for API/session health.
* Bulk WhatsApp sender for opted-in recipients.
* WhatsApp chat button shortcode.

= Template variables =

Use these variables in WooCommerce templates:

* `{order_id}`
* `{customer_name}`
* `{customer_phone}`
* `{order_total}`
* `{site_name}`
* `{order_status}`
* `{order_items}`
* `{order_items_count}`
* `{billing_email}`
* `{shipping_address}`
* `{payment_method}`

= Shortcode =

Use this shortcode to show a WhatsApp chat button:

`[msgroute_whatsapp_button phone="919898989898" text="Hello" label="Chat on WhatsApp"]`

= External services =

This plugin connects to a MsgRoute REST API endpoint configured by the site administrator in MsgRoute > Settings. Administrators must enter their own MsgRoute API URL before sending messages.

When a message is sent, the plugin sends the recipient phone number and message body to the configured MsgRoute API using the site's saved API key. Connection Status sends a status request to the configured API endpoint. No data is sent to MsgRoute until the administrator configures the API URL/key and triggers a message workflow, test message, bulk send, or connection check.

Service provider: MsgRoute. Production installs should use the MsgRoute service URL supplied with the site's API credentials. MsgRoute service terms and privacy policy apply to messages sent through that configured API endpoint.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/msgroute-whatsapp/`, or install the ZIP from Plugins > Add New > Upload Plugin.
2. Activate the plugin from the WordPress Plugins screen.
3. Go to MsgRoute > Settings.
4. Enter your MsgRoute API Base URL and Active API Key.
5. Save settings.
6. Open MsgRoute > Connection Status to verify the API connection.
7. Open MsgRoute > Message Templates to enable WooCommerce order status messages.
8. Open MsgRoute > Customer Opt-in to configure checkout consent text.

== Frequently Asked Questions ==

= Does this plugin include a WhatsApp gateway? =

No. This plugin requires a configured MsgRoute API endpoint and active API key.

= Does it work without WooCommerce? =

Yes. You can use Settings, Send Test Message, Message Logs, Connection Status, Bulk Sender, and the shortcode without WooCommerce. WooCommerce is required for order status templates and checkout opt-in.

= Are incoming WhatsApp messages stored? =

No. The plugin stores outgoing message attempts and errors only. Incoming WhatsApp messages are not stored by this plugin.

= Where are message logs stored? =

Outgoing logs are stored in the WordPress database table `wp_msgroute_whatsapp_logs` using the current site's database prefix.

= How many recipients can Bulk Sender process at once? =

Bulk Sender is capped at 100 recipients per submit to reduce accidental large sends.

= Does the plugin require customer consent? =

The plugin includes an optional WooCommerce checkout opt-in checkbox. You are responsible for configuring consent text and using the plugin according to applicable messaging, privacy, and anti-spam laws.

== Screenshots ==

1. Settings screen with API connection and test message panel.
2. Message Templates screen with status-specific WooCommerce templates and live preview.
3. Message Logs screen showing outgoing attempts and errors.
4. Admin Notification screen for admin WhatsApp alerts.
5. Connection Status and Bulk Sender screens.

== Changelog ==

= 1.1.0 =
* Added WooCommerce status templates with preview.
* Added message logs.
* Added admin notifications.
* Added customer opt-in settings.
* Added connection status check.
* Added bulk sender.
* Added security hardening for nonces, capabilities, sanitization, and escaping.

= 1.0.0 =
* Initial plugin with API settings, test message, WooCommerce notifications, and shortcode.

== Upgrade Notice ==

= 1.1.0 =
Adds advanced admin screens, logging, opt-in settings, and bulk sender. Review settings after updating.

