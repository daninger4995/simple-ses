# Daninger's SMTP for Amazon SES

A lightweight WordPress plugin that sends all your site's email through Amazon SES using plain SMTP.

No API keys, no AWS SDK, no other providers, no onboarding wizards, no upsells. It does one thing: point WordPress's `wp_mail()` at the Amazon SES SMTP endpoint for your region.

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
![WordPress 5.5+](https://img.shields.io/badge/WordPress-5.5%2B-21759b.svg)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)

---

## Why this plugin?

Most SMTP plugins bundle a dozen mailer integrations, tracking, logging, and paid upsells. If all you want is reliable delivery through Amazon SES, that's a lot of weight. This plugin is a single ~25&nbsp;KB file with no dependencies.

- Sends WordPress email via Amazon SES SMTP (plain SMTP, no SES API)
- Simple settings page — enable, region, host, port, encryption, credentials, from address
- Auto-fills the correct SES SMTP host when you pick a region
- Secure defaults: port `587`, TLS encryption
- Built-in test email form
- Force-from option to override the From address set by other plugins
- SMTP password is stored but never displayed back in the admin
- Nonces + capability checks on every admin action; all input sanitized, all output escaped

## Requirements

- WordPress 5.5+
- PHP 7.4+
- An Amazon SES account with a verified sending domain or email address
- SES **SMTP** credentials (created in the SES console — these are *not* your AWS access keys)

## Installation

1. Download the latest `daningers-smtp-for-amazon-ses.zip` from the [Releases](../../releases) page.
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the zip, **Install**, then **Activate**.
3. Go to **Settings → Daninger's SMTP**.

Or clone into your plugins directory:

```bash
git clone https://github.com/daninger4995/daningers-smtp-for-amazon-ses.git wp-content/plugins/daningers-smtp-for-amazon-ses
```

## Setup

### 1. Get your SES SMTP credentials

1. In the [Amazon SES console](https://console.aws.amazon.com/ses/), verify your sending domain or email address.
2. Open **SMTP settings → Create SMTP credentials**.
3. Save the SMTP username and SMTP password — they're shown only once.

### 2. Configure the plugin

In **Settings → Daninger's SMTP**:

| Setting | Notes |
| --- | --- |
| Enable plugin | Turn routing on once everything below is filled in |
| SES Region | Choosing a region auto-fills the host. Must match where SES is verified |
| SMTP Host | e.g. `email-smtp.us-east-1.amazonaws.com` |
| Port | `587` or `25` for TLS/STARTTLS; `465` or `2465` for SSL |
| Encryption | `TLS` (recommended), `SSL`, or `None` |
| SMTP Username | Your SES SMTP username |
| SMTP Password | Your SES SMTP password (write-only; never shown again) |
| From Email | Must be a verified SES identity |
| From Name | Display name on outgoing mail |
| Force From | Always use the From address above, overriding other plugins |

### 3. Send a test email

Use the **Send a Test Email** form at the bottom of the settings page. Failures report the underlying error (without exposing your password).

> **Sandbox note:** While your SES account is in the sandbox, *both* the From and To addresses must be verified identities. Request production access in the SES console to send to anyone.

## How it works

The plugin hooks into `phpmailer_init` and, when enabled, configures PHPMailer for SMTP:

```php
$phpmailer->isSMTP();
$phpmailer->Host       = 'email-smtp.us-east-1.amazonaws.com';
$phpmailer->Port       = 587;
$phpmailer->SMTPAuth   = true;
$phpmailer->SMTPSecure = 'tls';
$phpmailer->Username   = '…'; // SES SMTP username
$phpmailer->Password   = '…'; // SES SMTP password
```

The From name/email are also applied through the `wp_mail_from` / `wp_mail_from_name` filters when Force From is on.

## External service

This plugin sends your outgoing email through **Amazon SES**, operated by Amazon Web Services, Inc. You provide your own SES account and SMTP credentials. When WordPress sends an email and the plugin is enabled, the message (recipient, subject, body, headers, attachments) and your SMTP credentials are transmitted to the SES endpoint you configure. See the [AWS Service Terms](https://aws.amazon.com/service-terms/) and [AWS Privacy Notice](https://aws.amazon.com/privacy/).

## Uninstalling

Deleting the plugin from the WordPress admin removes its single stored option (and cleans up across all sites on multisite). Nothing else is left behind.

## Contributing

Issues and pull requests are welcome. The entire plugin is one readable file (`daningers-smtp-for-amazon-ses.php`) — keep changes small, follow [WordPress coding standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/), and sanitize input / escape output.

## License

[GPL-3.0-or-later](https://www.gnu.org/licenses/gpl-3.0.html). This is free software — share and modify it.

> Daninger's SMTP for Amazon SES is an independent project and is not affiliated with or endorsed by Amazon Web Services. "Amazon SES" and "Amazon Web Services" are trademarks of Amazon.com, Inc. or its affiliates.
