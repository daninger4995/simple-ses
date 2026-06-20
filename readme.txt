=== Daninger's SMTP for Amazon SES ===

Contributors: daninger4995
Tags: smtp, amazon ses, email, mailer
Requires at least: 5.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A lightweight plugin that sends all WordPress email through Amazon SES using plain SMTP. No API keys, no other providers, no upsells.

== Description ==

Daninger's SMTP for Amazon SES reconfigures the WordPress `wp_mail()` function to deliver email through **Amazon SES** using your SES **SMTP credentials**. It hooks into `phpmailer_init` and points PHPMailer at the Amazon SES SMTP endpoint for your region.

This plugin does one thing only. There are no other mailer integrations, no onboarding wizards, no usage tracking, no email logging, and no upsells.

Daninger's SMTP for Amazon SES is an independent project and is not affiliated with, endorsed by, or sponsored by Amazon Web Services. "Amazon SES" and "Amazon Web Services" are trademarks of Amazon.com, Inc. or its affiliates.

= Features =

* Send WordPress email via Amazon SES SMTP
* Simple settings page (enable, region, host, port, encryption, username, password, from email/name, force-from)
* Region helper that auto-fills the correct SES SMTP host
* Secure defaults: port 587, TLS encryption
* Built-in test email form
* Force-from option to override the From address set by other plugins
* SMTP password is stored but never displayed back in the admin

= What it does NOT do =

* It does not use the AWS SDK or the SES API — it uses plain SMTP only.
* It does not include any other email providers (Gmail, Outlook, Mailgun, SendGrid, Brevo, etc.).

== External services ==

This plugin sends your site's outgoing email through **Amazon Simple Email Service (Amazon SES)**, a third-party email delivery service operated by Amazon Web Services, Inc. (AWS). You must supply your own Amazon SES account and SMTP credentials; the plugin does not create an account for you and does not connect to any service operated by the plugin author.

What data is sent, and when: whenever WordPress sends an email (for example password resets, new-user notifications, contact-form messages, or the built-in test email), and the plugin is enabled and configured, it connects over SMTP to the Amazon SES endpoint you specify (e.g. `email-smtp.us-east-1.amazonaws.com`) and transmits that email — including the recipient address, subject, body, headers, and any attachments — to Amazon SES for delivery. Your SMTP username and password are sent to Amazon SES to authenticate the connection. No email or data is sent to Amazon SES unless WordPress itself triggers an email.

The plugin does not collect analytics and does not contact any other external service.

Amazon SES is provided by Amazon Web Services, Inc.:

* Service information: https://aws.amazon.com/ses/
* AWS Service Terms: https://aws.amazon.com/service-terms/
* AWS Privacy Notice: https://aws.amazon.com/privacy/

== Installation ==

1. In the Amazon SES console, verify your sending domain or email address.
2. In the Amazon SES console, open **SMTP settings** and **Create SMTP credentials**. Save the SMTP username and password (these are different from your AWS access keys).
3. Upload and activate this plugin.
4. Go to **Settings → Daninger's SMTP**.
5. Choose your SES **Region** (this fills in the SMTP host), enter your SMTP **username** and **password**, set the **From Email** to a verified SES identity, then **Enable** the plugin and save.
6. Use the **Send a Test Email** form to confirm delivery.

== Configuration ==

* **SES Region** — must match the region where your SES account is verified.
* **SMTP Host** — e.g. `email-smtp.us-east-1.amazonaws.com`, `email-smtp.us-west-2.amazonaws.com`, `email-smtp.eu-west-1.amazonaws.com`.
* **Port** — `587` or `25` for STARTTLS/TLS, `465` or `2465` for SSL.
* **Encryption** — `TLS` (recommended), `SSL`, or `None`.
* **From Email** — must be a verified SES identity.

== Frequently Asked Questions ==

= My SES account is in sandbox mode. Can I send to anyone? =

No. While your SES account is in the sandbox, both the From and To addresses must be verified identities. Request production access in the SES console to send to arbitrary recipients.

= Where do I get the SMTP username and password? =

In the Amazon SES console under **SMTP settings → Create SMTP credentials**. They are not your AWS access key ID/secret.

= Is my password safe? =

The password is stored in the WordPress options table (like all SMTP plugins) and is never displayed back in the admin once saved.

== Upgrade Notice ==

= 1.1.0 =
Plugin renamed to "Daninger's SMTP for Amazon SES" with WordPress.org compliance updates.

== Changelog ==

= 1.1.0 =
* Renamed plugin to "Daninger's SMTP for Amazon SES".
* Enqueued the admin region-helper script instead of inline output.
* Added an External Services section documenting Amazon SES usage.
* Distinctive prefixes for all functions, options, and the main class.

= 1.0.0 =
* Initial release: send WordPress email through Amazon SES SMTP via the `phpmailer_init` hook.
* Simple settings page, region helper, and built-in test email form.
