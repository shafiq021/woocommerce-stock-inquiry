=== WooCommerce Stock Inquiry ===
Contributors: shafiqurrehman
Tags: woocommerce, out of stock, inquiry, whatsapp, add to cart
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keep out-of-stock products visible and replace Add to Cart with a Send Inquiry button (Contact Page or WhatsApp). Add to Cart returns automatically on restock.

== Description ==

When WooCommerce says a product is unavailable, this plugin keeps the product on your shop and swaps the purchase button for a **Send Inquiry** button. When you restock the product, Add to Cart comes back by itself.

* No product scanning, no cron jobs, no duplicate stock data. The plugin reads the live WooCommerce product every time a page renders.
* Inquiry methods: **Contact Page** (product details passed in the URL) and **WhatsApp** (editable message with `{product_name}` and `{product_url}`). Popup is planned for a later version.
* Button designer with a live preview: text, font size and weight, padding, border, radius, normal and hover colors.
* Configurable stock threshold ("show inquiry when stock is less than N").
* Exclude individual products or whole categories.
* Button position: replace, before, or after Add to Cart.
* Works on shop, category, search, related products, upsells, cross-sells and single product pages (simple and variable products). Uses WooCommerce hooks, not JavaScript.

= How stock is evaluated =

1. Product excluded, or plugin disabled: normal WooCommerce.
2. Out of stock (marked manually or quantity reached zero): Send Inquiry.
3. On backorder / backorders allowed: normal WooCommerce (customers can still buy).
4. Stock managed and quantity below the threshold: Send Inquiry.
5. Stock not managed: only step 2 applies.

= Contact page pre-fill =

Customers arrive on your contact page with `?wsi_product=ID`. Use `[wsi_product]`, `[wsi_product field="url"]` or `[wsi_product field="id"]` on that page to show or pre-fill the product in your form.

== Installation ==

1. Upload the plugin ZIP via Plugins > Add New > Upload Plugin, then activate it.
2. Go to WooCommerce > Stock Inquiry.
3. Choose Contact Page or WhatsApp and fill in the page or number.
4. Adjust the button and click Save Changes.

== Frequently Asked Questions ==

= Does it change my stock values or hide products? =

No. It never writes to products and never changes catalog visibility.

= Nothing changed after activating. Why? =

The chosen inquiry method must be complete: a published contact page, or a valid international WhatsApp number. Until then WooCommerce is left untouched on purpose.

= Does it work with Elementor? =

It hooks the standard WooCommerce loop and add-to-cart templates, which Elementor's WooCommerce widgets use. Widgets or block themes that build their own button markup may need a small compatibility addition.

= Variable products? =

A variable product switches when WooCommerce marks the whole product out of stock (every variation unavailable), or when the parent itself manages stock below the threshold. Per-variation inquiry is not part of version 1.

== Changelog ==

= 1.0.0 =
* First release: Contact Page and WhatsApp inquiry, button designer with live preview, stock threshold, product and category exclusions, button position.
