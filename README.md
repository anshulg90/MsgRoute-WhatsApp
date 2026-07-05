# MsgRoute Notifications

A WordPress and WooCommerce plugin for sending outgoing customer notifications through the MsgRoute REST API.

MsgRoute Notifications is built for store owners who want order status updates, admin alerts, test messages, bulk sends, and delivery logs from inside WordPress. The plugin only stores outgoing message attempts and API errors. It does not store incoming customer conversations.

## Features

- MsgRoute API connection settings
- Test message sender from WordPress admin
- WooCommerce order status message templates
- Live template preview with supported variables
- Customer opt-in checkbox on WooCommerce checkout
- Admin notification rules for selected order statuses
- Message logs for outgoing attempts and API errors
- Connection status screen for API/session checks
- Bulk sender with a 100-recipient safety cap
- Frontend chat button shortcode


## Screenshots

### Settings

![MsgRoute Notifications settings screen](https://raw.githubusercontent.com/anshulg90/MsgRoute-WhatsApp/refs/heads/main/assets/screenshot-1.png)

### Message Templates

![MsgRoute Notifications message templates screen](https://raw.githubusercontent.com/anshulg90/MsgRoute-WhatsApp/refs/heads/main/assets/screenshot-2.png)

### Message Logs

![MsgRoute Notifications message logs screen](https://raw.githubusercontent.com/anshulg90/MsgRoute-WhatsApp/refs/heads/main/assets/screenshot-3.png)
## Requirements

- WordPress 6.2 or newer
- PHP 7.4 or newer
- WooCommerce for order templates and checkout opt-in features
- Active MsgRoute API URL and API key

WooCommerce is optional for basic plugin features such as Settings, Send Test Message, Message Logs, Connection Status, Bulk Sender, and the shortcode.

## Installation

### Install From ZIP

1. Download or build `msgroute-notifications.zip`.
2. Open WordPress admin.
3. Go to `Plugins > Add New > Upload Plugin`.
4. Upload the ZIP file.
5. Activate `MsgRoute Notifications`.
6. Go to `MsgRoute > Settings`.
7. Add your MsgRoute API Base URL and Active API Key.
8. Open `MsgRoute > Connection Status` to verify the setup.

### Manual Install

Copy the plugin folder to:

```text
wp-content/plugins/msgroute-notifications/
```

Then activate it from the WordPress Plugins screen.

## Configuration

Open `MsgRoute > Settings` and configure:

- `API Base URL`: Your MsgRoute API endpoint
- `Active API Key`: API key generated from the MsgRoute client dashboard
- `Default Country Code`: Used when a 10-digit local phone number is entered
- `Admin Recipient Number`: Number used for admin order alerts

The plugin will not send any message until the API URL and API key are configured.

## WooCommerce Templates

Open `MsgRoute > Message Templates` to enable messages for order statuses such as:

- Pending payment
- Processing
- On hold
- Completed
- Cancelled
- Failed
- Refunded

Supported template variables:

```text
{order_id}
{customer_name}
{customer_phone}
{order_total}
{site_name}
{order_status}
{order_items}
{order_items_count}
{billing_email}
{shipping_address}
{payment_method}
```

Example template:

```text
Hi {customer_name}, your order #{order_id} is now {order_status}. Items: {order_items}. Total: {order_total}.
```

## Customer Opt-in

Open `MsgRoute > Customer Opt-in` to enable a checkout consent checkbox. When enabled, customer order notifications are sent only if the customer opts in.

The consent value is saved on the WooCommerce order as:

```text
_msgroute_notifications_optin
```

## Admin Notifications

Open `MsgRoute > Admin Notification` to send admin alerts for selected WooCommerce order statuses. The admin recipient number is managed from the main Settings page.

## Message Logs

Open `MsgRoute > Message Logs` to review outgoing message attempts.

Logs include:

- Time
- Recipient phone number
- Context
- Status
- Message/error summary

Incoming customer messages are not stored by this plugin.

## Bulk Sender

Open `MsgRoute > Bulk Sender` to send a manual broadcast to selected phone numbers.

Safety behavior:

- Maximum 100 recipients per submit
- Duplicate numbers are removed
- Every attempt is logged
- Use only for opted-in recipients

## Shortcode

Use this shortcode to show a frontend chat button:

```text
[msgroute_message_button phone="919898989898" text="Hello" label="Chat with us"]
```

If `phone` is empty, the plugin uses the admin recipient number from Settings.

## External Service Disclosure

This plugin connects to a MsgRoute REST API endpoint configured by the site administrator.

When a message is sent, the plugin sends the following data to the configured MsgRoute API endpoint:

- Recipient phone number
- Message body
- API key in the request header

Connection Status sends a status request to the configured API endpoint. No data is sent until the administrator configures the API URL/API key and triggers a workflow, test message, bulk send, or connection check.

## Privacy Notes

- The plugin stores outgoing message attempts and API errors.
- The plugin does not store incoming customer conversations.
- Store owners are responsible for consent text, lawful messaging, anti-spam compliance, and privacy policy updates.

## WordPress.org Package

Use this ZIP for plugin submission/testing:

```text
wordpress-plugin/msgroute-notifications.zip
```

Do not submit older packages that use restricted terms in the plugin display name or slug.

## Development

Main files:

```text
msgroute-notifications.php
includes/advanced.php
assets/admin.css
assets/admin.js
assets/frontend.css
readme.txt
```

Run a PHP syntax check:

```bash
php -l msgroute-notifications.php
php -l includes/advanced.php
```

## Security

The plugin uses:

- Capability checks with `manage_options`
- WordPress nonces for admin actions
- Sanitization for saved options and submitted form values
- Escaping for admin output
- `wp_remote_get` and `wp_remote_post` for API calls

Do not commit real API keys or production secrets.

## License

GPLv2 or later.

