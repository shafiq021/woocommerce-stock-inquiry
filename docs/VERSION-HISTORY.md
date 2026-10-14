# WooCommerce Stock Inquiry: Version History

How the plugin grew, release by release: what each version is, what it added and what it fixed. The short, newest-first list for developers is in [`CHANGELOG.md`](../CHANGELOG.md).

| Version | Release name | In one line |
|---|---|---|
| 1.0.0 | Initial release | Replaces Add to Cart with an inquiry button for out-of-stock products |
| 1.1.0 | Settings and preview | Per-element settings tabs and a live preview |
| 2.0.0 | Core upgrade | Trigger modes, variable products, per-product overrides, more methods |
| 3.0.0 | Inquiry Management System | Inquiries saved in WordPress, statuses, notes, emails, quotes, analytics |

---

## Version 1: the inquiry button

### 1.0.0: initial release
A small plugin with one job: when a WooCommerce product is out of stock or below a stock threshold, show an **inquiry button** instead of Add to Cart, so the product stays visible and customers can ask about it.

**Features**
- Stock threshold detection: below the threshold or out of stock replaces Add to Cart with the button.
- Add to Cart returns automatically when stock is restored. WooCommerce stays the only source of truth for stock.
- Two inquiry methods: **Contact Page** (sends the customer to your page with the product in the link) and **WhatsApp** (opens `wa.me` with a message from your template).
- Message placeholders for the product name and URL.
- Button, heading and description styling, product and category exclusions.
- Works with WooCommerce product pages and archives, and Elementor product layouts.

**Fixed bugs:** none (first release).

### 1.1.0: settings and live preview

**New features**
- Settings reorganised into per-element tabs: General, Inquiry Method, Heading, Button, Description, Branding, Product Rules, Advanced.
- "Where to show" switches (single product page or listings) for heading, description and branding.
- Font family controls for button, heading, description and branding; heading font weight.
- Sticky live preview on every tab, using the same CSS as the front end, with a Product page / Listings switch.
- WhatsApp chat-bubble preview that updates as you type the template.

**Fixed bugs**
- Undefined-index notices for the font family fields that were missing from the defaults and the sanitizer.

---

## Version 2: the core upgrade (2.0.0)

Existing settings are kept and migrated automatically.

**New features**
- **Trigger modes:** Out of stock only, Below stock threshold, Out of stock OR below threshold (the version 1 behaviour), Always show inquiry, Disabled; optional "less than or equal" comparison.
- **Variable products:** the button follows the selected variation, judged on its own stock, backorder and managed-stock settings.
- **Per-product overrides** (Product data > Stock Inquiry): custom trigger, threshold, method, button text and message; "Disabled" and "Always" per product.
- **New inquiry methods:** Elementor Popup (Elementor Pro), Email (mailto) and Phone (tel).
- **Popup integration:** fills product details into popup form fields through CSS classes such as `wsi-product-name` and `wsi-product-url`.
- **Shared placeholders:** `{product_id}`, `{product_name}`, `{product_url}`, `{sku}`, `{price}`, `{regular_price}`, `{sale_price}`, `{stock_quantity}`, `{stock_status}`, `{category}`, `{site_name}`, `{site_url}`.
- **Display controls:** per-location switches (shop, category and tag archives, search, related, upsells, cross-sells, other grids).
- Optional `[wsi_button]` shortcode.
- **Configuration validation** with clear messages and a marker on the affected tab.
- **Test buttons** (WhatsApp, Email, Call link, Contact link, Popup) and live preview states (Out of stock, Low stock, In stock), preview with a real product.

**Improved**
- WhatsApp line breaks and encoding; Contact Page passes the selected variation.
- Stylesheet and script load only on pages that print a button.

**Fixed bugs**
- Variable products were evaluated on the parent only, so an unavailable variation never got the inquiry button.
- WhatsApp and Contact Page links for a variation now name and link to that variation.

**Security**
- Capability and nonce checks on every admin action; strict URL and attribute handling; `javascript:` and other unsafe URL schemes rejected; the "Powered By" credit is off by default for new installs.

---

## Version 3: Inquiry Management System (3.0.0)

The plugin grows from "a button" into a lightweight inquiry and quote manager inside WordPress. It is still free and open source, and still never changes WooCommerce stock data. Existing settings are kept.

**New features**
- **Inquiry Form (modal)** method and `[wsi_inquiry_form]` shortcode: name, email, phone and message, saved in your own database. The success message appears only after the inquiry is stored.
- **Request a Quote and B2B use:** the button is no longer only for out-of-stock items. Category rules can show a "Request a Quote" button on every product of a wholesale category.
- **Inquiries screen** (WooCommerce > Inquiries): dashboard, All Inquiries (search, filters by status, product, source and date, sorting, pagination, bulk actions), inquiry details with product image and link.
- **Five statuses:** New, Contacted, In Progress, Completed, Closed, with a history of every change.
- **Private internal notes** that are never shown on the storefront or emailed.
- **Email notifications:** admin notification, customer confirmation and optional status-change email; editable subjects and messages with placeholders; test email button; works with any SMTP plugin.
- **Rules by category:** a different method, trigger and button text per category. Priority: product settings, then category rule, then global settings.
- **Multiple WhatsApp numbers** for departments, with the existing number as the default fallback.
- **Analytics** from stored inquiries (by status, source, product, monthly trend).
- **CSV export** of the current filtered list, safe against spreadsheet formula injection.
- **Privacy:** WordPress personal data export and erase, suggested privacy policy text, no IP address or browser data stored.
- **Modern admin interface** for the settings page and the Inquiries screens.

**Changed**
- The "Powered By" credit and the Branding tab were removed (WordPress.org friendly).
- Two tables are created: `{prefix}wsi_inquiries` and `{prefix}wsi_inquiry_notes`.

**Fixed bugs and hardening**
- Upgrades keep customer emails **opt-in**: stores with saved settings get customer confirmation and status emails switched off once, so nobody starts emailing customers by surprise.
- The new-inquiries count in the admin menu is cached instead of running a query on every admin page.
- Uninstall (when "delete data" is on) now also removes per-product WhatsApp number meta, the upgrade marker and the cached count.
- Email subjects and display names are cleaned so customer text cannot inject mail headers; a failing mail transport never stops an inquiry from being saved.

**Security:** nonces, capability checks, prepared SQL, escaped output, honeypot, rate limit (5 per visitor per 10 minutes), duplicate detection, server-side validation and product verification.

**Known limitation:** analytics counts stored inquiries only. Button clicks are not tracked in 3.0.0, and opening WhatsApp or an email app is never counted as an inquiry.

---

## Working on next (planned for 3.1.0, not released)

These are plans, not promises. Order may change.

- **Click tracking:** count button clicks separately from stored inquiries, so analytics can show clicks, submissions and stored inquiries as different numbers.
- **Quote fields for B2B:** optional company name and quantity fields on the inquiry form.
- **Reply from the inquiry screen:** send a reply email to the customer without leaving WordPress, with saved reply templates.
- **Optional spam protection** for the form (for example Cloudflare Turnstile or reCAPTCHA keys you supply).
- **Translation file (.pot)** and fuller translation coverage.

### Suggestions and feedback

Have an idea or found a problem? Open an issue on the GitHub repository, or contact the author, Shafiqur Rehman:

- Email: safiqkhan0102@gmail.com
