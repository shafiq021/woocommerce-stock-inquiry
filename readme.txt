=== WooCommerce Stock Inquiry ===
Contributors: shafiq021
Tags: woocommerce, request a quote, b2b, inquiry, whatsapp
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Request a Quote and product inquiries for WooCommerce: replace Add to Cart with an inquiry button, save inquiries in WordPress, track their status and email customers.

== Description ==

Use it for out-of-stock products, B2B and wholesale catalogues (a "Request a Quote" button on selected products or categories) and made-to-order items. Customers send an inquiry, you manage it from a simple inbox inside WordPress.

Keep unavailable products visible and let customers ask about them instead of hitting a dead end. When a product matches your stock rule, Add to Cart is replaced by a customizable inquiry button. When stock returns, Add to Cart comes back automatically. Nothing in WooCommerce is changed or stored.

**No shortcode is required.** The button is placed automatically through WooCommerce. A `[wsi_button]` shortcode is available as an option.

**Features**

* Trigger modes: out of stock only, below a threshold, out of stock or below a threshold, always, disabled.
* Variable products: the button follows the selected variation.
* Per-product overrides for trigger, threshold, method, button text and message.
* Inquiry methods: Inquiry Form (modal), Contact Page, WhatsApp, Elementor Popup, Email, Phone.
* Rules by category: a different method, button text (for example "Request a Quote") and trigger per category.
* Multiple WhatsApp numbers for departments, with a default fallback.
* WooCommerce > Inquiries: dashboard, search and filters, five statuses, private notes, bulk actions.
* Email notifications to you and the customer with editable templates.
* Analytics from stored inquiries and secure CSV export.
* Placeholders such as {product_name}, {product_url}, {sku}, {price}, {stock_quantity}, {category} in every message.
* Choose where the button shows: single product page, shop, categories, search, related, upsells, cross-sells.
* Settings validation, test buttons and a live preview with a real product.
* Lightweight: no external requests and no tracking. Two small tables store inquiries.

**Elementor** is optional. Only the Popup method needs Elementor Pro. The plugin opens a popup you build yourself and does not include a form builder.

**Email** opens the customer's own email app (mailto link). The site does not send mail.

**Planned for 3.1.0:** click tracking kept separate from stored inquiries, company and quantity fields for quotes, reply from the inquiry screen, optional spam protection and a translation file. Ideas and bug reports are welcome on GitHub or at safiqkhan0102@gmail.com.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install it from the Plugins screen.
2. Activate it (WooCommerce must be active).
3. Go to WooCommerce > Stock Inquiry, choose a trigger and an inquiry method, and save.
4. Use the Test button to check your link, and open a product that matches your rule.

== Frequently Asked Questions ==

= Do I need a shortcode? =
No. Placement is automatic. The shortcode is optional.

= Where are inquiries stored? =
Inquiries sent with the Inquiry Form (modal) or the [wsi_inquiry_form] shortcode are saved in two tables in your WordPress database and shown in WooCommerce > Inquiries. Contact Page, WhatsApp, Email and Phone leave your site, so they cannot be stored. Delete or export a customer's inquiries from Tools > Export / Erase Personal Data.

= Which emails does the plugin send? =
For inquiries saved by the Inquiry Form: a notification to you, a confirmation to the customer, and (optional, off by default) an email to the customer when you change the status. Subjects and messages are editable under Settings > Notifications. Emails use the standard WordPress mail function, so an SMTP plugin works. Internal notes are never emailed.

= Do I need Elementor? =
No. Only the Elementor Popup method needs Elementor Pro.

= Does the Email method send an email from my site? =
No. It opens the customer's email app with the message filled in.

= How are variable products handled? =
Each variation is checked on its own stock. The button appears when a variation that matches your rule is selected.

= Can I change the rules for one product? =
Yes. Edit the product and open Product data > Stock Inquiry.

= Will my version 1 settings be kept? =
Yes. They are migrated automatically and behavior stays the same.

== Screenshots ==

1. Settings overview with the sticky live preview.
2. Inquiry method: Contact Page, WhatsApp, Inquiry Form, Popup, Email or Phone.
3. Message templates with placeholders.
4. Products: exclude products and categories.
5. Rules by category, for example a "Request a Quote" button for a wholesale category.
6. Heading, button and description styling.
7. Notifications: admin, customer and status emails.
8. Live preview for the product page and listings.
9. Request a Quote form on the product page.
10. Inquiries dashboard.
11. All Inquiries with search, filters, bulk actions and CSV export.
12. Inquiry details with status and private notes.
13. Analytics.

== Changelog ==

= 3.0.0 =
New features
* Inquiry Form (modal) method and [wsi_inquiry_form] shortcode; inquiries are saved in your database.
* Request a Quote and B2B use: category rules set the method, when the button shows and its text.
* WooCommerce > Inquiries: dashboard, searchable and filterable list, details, five statuses, bulk actions, private notes.
* Email notifications: admin, customer confirmation, optional status-change; editable templates; test email.
* Multiple WhatsApp numbers with a default fallback.
* Analytics and CSV export.
* Personal data export and erase, privacy policy text.
* Modern settings and inquiry screens.
Changes
* The "Powered By" credit and Branding tab were removed.
Fixed
* Upgrades keep customer emails switched off until you enable them.
* Uninstall now removes per-product WhatsApp number data and cached counts.
* A failing mail transport no longer stops an inquiry from being saved.
Security
* Nonces, capability checks, prepared queries, escaped output, honeypot, rate limit and duplicate detection.

= 2.0.0 =
New features
* Trigger modes, variation-aware button, per-product overrides.
* Elementor Popup, Email and Phone methods; shared placeholders; listing location switches; [wsi_button] shortcode.
* Configuration validation, test buttons, preview states.
Fixed
* Variable products were evaluated on the parent only; variation links and messages now name the variation.
Security
* Capability and nonce checks on every admin action, strict URL and attribute handling, branding credit off by default.

= 1.1.0 =
New features
* Per-element settings tabs, where-to-show switches, live preview, WhatsApp chat preview.
Fixed
* Undefined-index notices for font family fields.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 3.0.0 =
Adds quote requests, an inquiry form that saves inquiries in WordPress, an Inquiries screen, category rules, analytics and CSV export. Your settings are kept; customer emails stay off until you enable them. Two database tables are created on update.

= 2.0.0 =
Adds variation support, per-product overrides and new inquiry methods. Your settings are migrated automatically.
