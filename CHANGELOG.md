# Changelog

All notable changes to **WooCommerce Stock Inquiry**, newest first. Format: Added, Changed, Improved, Fixed, Security. For the version-by-version story and the roadmap see [`docs/VERSION-HISTORY.md`](docs/VERSION-HISTORY.md).

## 3.0.0: Inquiry Management System

The plugin stays free and open source. Existing settings are kept.

### Added
- **Inquiry Form (modal)** method and `[wsi_inquiry_form]` shortcode (options: `product_id`, `text`, `send`, `title`, `only_if_needed`). Inquiries are saved in the database; no form plugin is needed.
- **Request a Quote / B2B use:** category rules can set the inquiry method, **when the button shows** and the **button text**, so a "Wholesale" category can show "Request a Quote" on every product regardless of stock.
- **WooCommerce > Inquiries:** dashboard, All Inquiries (search; filters by status, product, source and date; sorting; pagination; bulk status change; bulk delete with confirmation) and inquiry details (product image and link, customer data, message, status, notes, history). A deleted product does not break an inquiry.
- **Statuses:** New, Contacted, In Progress, Completed, Closed. Every change updates the modification time and is written to the history.
- **Private internal notes:** linked to the inquiry, never shown on the storefront or emailed.
- **Email notifications** (one system): admin notification, customer confirmation and optional status-change email. Editable subjects and plain-text messages with placeholders `{inquiry_id}`, `{product_name}`, `{customer_name}`, `{customer_email}`, `{customer_phone}`, `{inquiry_message}`, `{inquiry_status}`, `{site_name}`, `{admin_link}`. Settings > Notifications with a **Send a test email** button. Filter `wsi_notification_email`.
- **Rules tab:** a different inquiry method per product category. Priority: product settings, then category rule, then global. The most specific category wins (a sub-category beats its parent), then the rule listed first. Unusable rules are skipped.
- **More WhatsApp numbers:** named numbers with optional message templates; the existing number is the default and the fallback. Selectable per category rule and per product. Numbers are validated (international format).
- **Analytics** (Inquiries > Analytics): total, by status, by source, most requested products, 12-month trend, from stored inquiries only. Button clicks are not tracked in 3.0.0 and opening WhatsApp or an email app is never counted as an inquiry; click tracking is planned for 3.1.0.
- **CSV export** of the current search and filters: UTF-8 with BOM, formula-injection protection (`=`, `+`, `-`, `@` are prefixed), streamed in batches of 500, notes never included.
- **Privacy:** WordPress personal data export and erase, suggested privacy policy text, no IP address or browser data stored.
- **Modern admin interface** for the settings page and the Inquiries screens (new stylesheet `admin/assets/ui.css`, loaded only on the plugin's screens).
- Tables `{prefix}wsi_inquiries` and `{prefix}wsi_inquiry_notes`, created by activation and by the 3.0.0 migration (`dbDelta`, UTC timestamps, indexed).

### Changed
- The "Powered By SK" credit and the Branding tab were removed (WordPress.org friendly). Old branding keys are ignored.
- The plugin is positioned for quotes and B2B as well as out-of-stock products.

### Improved
- The new-inquiries count in the admin menu is cached for a few minutes and refreshed when an inquiry is added, changed or deleted.

### Fixed
- **Upgrades keep customer emails opt-in:** stores with saved settings get customer confirmation and status emails switched off once, instead of starting to email customers by surprise. Fresh installs default to on.
- Uninstall (with "delete data" on) now also removes per-product WhatsApp number meta, the upgrade marker option and the cached count.
- A failing or throwing mail transport can no longer stop an inquiry from being saved or the other email from being sent.

### Security
- Nonces and capability checks (`manage_woocommerce`) on every action, prepared SQL, escaped output, honeypot, rate limit (5 per visitor per 10 minutes), duplicate detection, server-side validation and product verification.
- Email subjects and display names are cleaned against header injection; placeholder values are inserted as plain text.

### Upgrade notes
- Updating needs no reactivation: the migration runs once and creates the tables. Every saved setting is kept.

## 2.0.0: Core upgrade

Existing settings are kept and migrated automatically.

### Added
- **Trigger modes:** Out of stock only, Below stock threshold, Out of stock OR below threshold (the version 1 behaviour), Always show inquiry, Disabled; optional "less than or equal".
- **Variable products:** the button follows the selected variation, judged on its own stock, backorder and managed-stock settings.
- **Per-product overrides** (Product data > Stock Inquiry): trigger, threshold, method, button text and message; "Disabled" and "Always" per product.
- **New methods:** Elementor Popup (Elementor Pro), Email (mailto) and Phone (tel). Popup fields are filled through the classes `wsi-product-name`, `wsi-product-url`, `wsi-product-id`, `wsi-product-sku`, `wsi-product-price`, `wsi-message`.
- **Shared placeholders:** `{product_id}`, `{product_name}`, `{product_url}`, `{sku}`, `{price}`, `{regular_price}`, `{sale_price}`, `{stock_quantity}`, `{stock_status}`, `{category}`, `{site_name}`, `{site_url}`.
- **Display controls** per location (shop, category and tag archives, search, related, upsells, cross-sells, other grids) and the optional `[wsi_button]` shortcode.
- **Configuration validation** with clear messages and a marker on the affected tab; **Test** buttons for each method; preview states (Out of stock, Low stock, In stock), preview with a real product and an email preview.
- Settings tabs: General, Trigger, Inquiry Method, Messages, Products, Display, Button, Heading, Description, Branding, Integrations, Advanced.

### Improved
- WhatsApp line breaks and encoding; Contact Page passes the selected variation and supports `sku` and `price`.
- The stylesheet and script load only on pages that print a button.
- Uninstall also removes per-product override data and the migration marker.

### Fixed
- Variable products were evaluated on the parent only, so an unavailable variation never got the inquiry button.
- WhatsApp and Contact Page links for a variation now name and link to that variation.

### Security
- Capability and nonce checks on every admin action; test actions use sanitised values only; popup IDs, shortcode attributes and button attributes are validated; `javascript:` and other unsafe URL schemes are rejected.
- The "Powered By SK" credit is off by default for new installs.

### Upgrade notes
- A one-time migration (guarded by `wsi_db_version`) adds the new keys: a threshold above 0 becomes "Out of stock OR below threshold" with the strict "less than" comparison (identical to version 1); a threshold of 0 becomes "Out of stock only". Saved values are never overwritten.
- Requires WordPress 5.8+, PHP 7.4+, WooCommerce 7.0+.

## 1.1.0

### Added
- Settings reorganised into per-element tabs: General, Inquiry Method, Heading, Button, Description, Branding, Product Rules, Advanced.
- "Where to show" switches (single product page or listings) for heading, description and branding.
- Font family controls for button, heading, description and branding; heading font weight.
- Sticky live preview on every tab with a Product page / Listings switch; WhatsApp chat-bubble preview that updates as you type.

### Fixed
- Undefined-index notices for the font family fields missing from the defaults and the sanitizer.

## 1.0.0

### Added
- Initial release: inquiry button instead of Add to Cart for out-of-stock or low-stock products, Contact Page and WhatsApp methods, message placeholders for product name and URL, button styling, product and category exclusions, Elementor and WooCommerce product page support.
