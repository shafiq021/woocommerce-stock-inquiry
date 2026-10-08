# WooCommerce Stock Inquiry

A lightweight, free, and open-source WooCommerce plugin that automatically replaces the **Add to Cart** button with a customizable **Send Inquiry** button when a product reaches a configurable stock threshold.

Built for WooCommerce stores that want to turn low-stock and unavailable products into customer inquiries without hiding products or changing WooCommerce stock data.

> **No shortcode required.** Configure the plugin once and let it automatically work with WooCommerce and Elementor product layouts.

---

## Overview

WooCommerce Stock Inquiry monitors the product's current WooCommerce stock state and changes the customer-facing purchase action when the configured stock threshold is reached.

For example, with a stock threshold of **5**:

| Stock | Action |
|---:|---|
| 10 | Add to Cart |
| 6 | Add to Cart |
| 5 | Add to Cart |
| 4 | Send Inquiry |
| 2 | Send Inquiry |
| 1 | Send Inquiry |
| 0 | Send Inquiry |

When the product is restocked, the normal **Add to Cart** action automatically returns.

The plugin does not:

- Hide products
- Change WooCommerce stock
- Create a duplicate stock database
- Require manual stock synchronization
- Require a shortcode for the core functionality

WooCommerce remains the source of truth for product stock.

---

# Why WooCommerce Stock Inquiry?

WooCommerce normally uses the Add to Cart button even when store owners may prefer customers to contact them about products that are almost unavailable or currently unavailable.

WooCommerce Stock Inquiry provides a simple solution:

```text
Normal Stock
     ↓
Add to Cart

When stock becomes low:

Low Stock
     ↓
Send Inquiry
     ↓
Contact Page / WhatsApp

When the product is restocked:

Restocked
     ↓
Add to Cart

No manual changes are required.

V1 Features
1. Automatic Stock-Based Inquiry Button

Automatically replace the Add to Cart action when stock reaches the configured threshold.

Example

Default threshold:

5

Result:

Stock 10 → Add to Cart
Stock 5  → Add to Cart
Stock 4  → Send Inquiry
Stock 1  → Send Inquiry
Stock 0  → Send Inquiry

The threshold is configurable from the plugin settings.

2. Automatic Restock Detection

There is no need to manually activate or deactivate the inquiry button.

If a product changes from:

Stock: 4

to:

Stock: 10

the plugin automatically allows the normal WooCommerce Add to Cart action again.

The plugin does not require:

Cron jobs
Manual synchronization
Stock scanning
Database synchronization
Admin refresh actions
3. No Shortcode Required

One of the main goals of this plugin is to keep the setup simple.

You do not need to add:

[stock_inquiry_button]

to your Elementor templates.

You can continue using your existing Elementor product layout.

For example:

Elementor Loop Item
        ↓
WooCommerce Product Elements
        ↓
Elementor Loop Grid
        ↓
WooCommerce Stock Inquiry
        ↓
Automatic Inquiry Button

The plugin should use WooCommerce's native hooks and filters wherever possible.

A lightweight compatibility fallback may be used when a specific Elementor rendering path cannot be reliably handled through native WooCommerce hooks.

4. Elementor Compatibility

The plugin is designed with Elementor-based WooCommerce stores in mind.

Target compatibility includes:

Elementor Loop Grid
Elementor Loop Item
Elementor Archive templates
Elementor Products widget
WooCommerce product layouts created with Elementor

The goal is:

Build your Elementor product layout normally. The plugin handles the inquiry action automatically.

You should not have to rebuild your Loop Item simply to use this plugin.

5. WooCommerce Compatibility

The plugin is designed to work with common WooCommerce product locations.

Shop
Shop → Product → Send Inquiry
Product Categories
Category Archive → Product → Send Inquiry
Search Results
Search → Product → Send Inquiry
Single Product
Product Page → Send Inquiry
Related Products
Related Products → Send Inquiry
Upsells
Upsells → Send Inquiry
Cross-Sells
Cross-Sells → Send Inquiry
6. Inquiry Methods

V1 provides two fully functional inquiry methods.

Contact Page
WhatsApp

A third method is planned for a future version:

Popup

Popup functionality is not implemented in V1.

Contact Page Inquiry

The Contact Page method sends customers to a selected WordPress page.

Example:

Product
   ↓
Send Inquiry
   ↓
Contact Page

The plugin can safely pass product information to the destination page.

Possible product information includes:

Product ID
Product name
Product URL
Settings
Select Contact Page
Button Text
Open in:
    Same Tab
    New Tab
WhatsApp Inquiry

The WhatsApp method opens a WhatsApp conversation with a pre-filled message.

Example:

Hi, I am interested in Clearamax Liquid Detergent Lavender 3kg.

Please let me know when this product is available.

Product URL:
https://example.com/product/clearamax-liquid-detergent-lavender-3kg/

The WhatsApp number supports international numbers.

Example:

+923001234567
WhatsApp Message Template

Store owners can create their own message template.

Example:

Hi, I am interested in {product_name}.

Please let me know when this product is available.

Product URL:
{product_url}
Supported Placeholders
{product_name}
{product_url}

The plugin should safely encode the final WhatsApp message.

It should also avoid duplicate product information when automatic product information and manual placeholders are both enabled.

WhatsApp Settings

Example:

WhatsApp Number:
+92XXXXXXXXXX

Button Text:
Send Inquiry

Message Template:

Hi, I am interested in {product_name}.

Please let me know when this product is available.

Product URL:
{product_url}

Open in:
New Tab
7. Button Designer

The plugin provides a visual button customization system.

No custom CSS is required.

Button Settings
Button Text
Font Size
Font Weight

Padding Top
Padding Right
Padding Bottom
Padding Left

Border Radius
Border Width

Normal Background Color
Normal Text Color

Hover Background Color
Hover Text Color

The settings page should include a live button preview.

Example:

┌──────────────────────────────┐
│       Send Inquiry           │
└──────────────────────────────┘

Changes should be visible in the preview before saving.

8. Button Position

The plugin can provide the following position options:

Replace Add to Cart
Before Add to Cart
After Add to Cart
Default
Replace Add to Cart

The plugin should prioritize reliable WooCommerce behavior.

If a specific WooCommerce or Elementor rendering path cannot safely support a position, the plugin should avoid fragile frontend manipulation.

9. Product Exclusions

Store owners may want selected products to continue using the normal WooCommerce Add to Cart button.

The plugin should support product exclusions.

Example:

Excluded Product:
Special Membership

Result:

Excluded Product
        ↓
Normal Add to Cart
10. Category Exclusions

Entire WooCommerce categories can be excluded.

Example:

Excluded Category:
Digital Products

Products inside that category continue using the normal WooCommerce purchase action.

11. Product Visibility

The plugin does not hide products.

This is important.

An out-of-stock product remains visible:

Product
   ↓
Product Image
Product Name
Price
Description
Send Inquiry

Instead of:

Product Hidden

This allows customers to discover products and contact the store about availability.

12. Variable Product Support

Variable products require special handling because WooCommerce controls:

Variations
Variation stock
Variation prices
Variation selection
Variation AJAX
Add to Cart data

The plugin should avoid breaking normal WooCommerce variation functionality.

The implementation should be conservative with variable products.

If a specific variation behavior cannot be handled reliably, the plugin should fail gracefully instead of introducing fragile JavaScript behavior.

13. Backorder Support

WooCommerce backorder settings should be respected.

The plugin should not blindly replace the Add to Cart action if WooCommerce considers the product legitimately purchasable through a valid backorder configuration.

14. Stock Management Disabled

The plugin must safely handle products where WooCommerce stock management is disabled.

It should not assume that every product has a numeric stock quantity.

WooCommerce's actual purchasability and stock state should be considered before replacing the normal purchase action.

How It Works
                  WooCommerce Product
                           │
                           ▼
                  Check Product State
                           │
                           ▼
                 Check Stock Threshold
                           │
                 ┌─────────┴─────────┐
                 │                   │
          Stock >= Threshold   Stock < Threshold
                 │                   │
                 ▼                   ▼
            Add to Cart        Send Inquiry
                                      │
                              ┌───────┴───────┐
                              │               │
                              ▼               ▼
                        Contact Page       WhatsApp

WooCommerce remains responsible for the stock.

The plugin controls the customer-facing action.

Example Configuration
General
Plugin Status:
Enabled

Stock Threshold:
5
Inquiry Method
Inquiry Type:
WhatsApp
WhatsApp
WhatsApp Number:
+92XXXXXXXXXX

Button Text:
Send Inquiry
Message
Hi, I am interested in {product_name}.

Please let me know when this product is available.

Product URL:
{product_url}
Button Design
Font Size:
14px

Font Weight:
600

Padding:
12px 20px 12px 20px

Border Radius:
4px

Border Width:
1px
Installation
Method 1 — WordPress Admin
Download the plugin ZIP.
Open WordPress Admin.
Go to:
Plugins → Add New Plugin
Click:
Upload Plugin
Upload the plugin ZIP.
Install the plugin.
Activate the plugin.
Make sure WooCommerce is active.
Go to:
WooCommerce → Stock Inquiry
Configure the settings.
Save the settings.
Manual Installation

Upload the plugin directory to:

/wp-content/plugins/woocommerce-stock-inquiry/

Then activate it from:

WordPress → Plugins
Requirements

The plugin requires:

WordPress
WooCommerce
A PHP version supported by the active WordPress/WooCommerce environment

WooCommerce must be active.

If WooCommerce is not active, the plugin should display an administrator notice rather than causing a fatal error.

Settings Structure

The planned admin settings structure is:

WooCommerce
└── Stock Inquiry
    │
    ├── General
    │
    ├── Inquiry Method
    │
    ├── Button Design
    │
    ├── Product Rules
    │
    └── Advanced
General Settings

Possible settings:

Enable Plugin
Stock Threshold
Button Position

Default:

Enabled: Yes
Stock Threshold: 5
Button Position: Replace Add to Cart
Inquiry Method Settings

V1:

Contact Page
WhatsApp

Future:

Popup

Popup should appear as:

Coming Soon

in V1 rather than providing an incomplete popup implementation.

Contact Page Settings
Select Page
Button Text
Open in:
    Same Tab
    New Tab

Product information can be safely passed to the selected page.

WhatsApp Settings
WhatsApp Number
Button Text
Message Template
Automatically Add Product Name
Automatically Add Product URL
Open in:
    Same Tab
    New Tab
Product Rules

Product rules allow store owners to control which products use the inquiry system.

Potential rules include:

Product Name
Product
Category

Excluded products should retain normal WooCommerce behavior.

Security

Security is a core requirement of the project.

The plugin should follow WordPress and WooCommerce security practices.

Requirements include:

Capability checks
Nonce verification where applicable
Sanitization of settings
Validation of settings
Escaping frontend output
Safe URL generation
Safe query parameters
Proper handling of user input
No unsafe direct database queries
No trusting raw frontend data
No unnecessary customer data storage

Use WordPress and WooCommerce APIs whenever possible.

Performance

WooCommerce Stock Inquiry is designed to be lightweight.

The plugin should avoid:

Scanning every product during activation
Creating duplicate stock tables
Continuous AJAX polling
Cron-based stock synchronization
Large JavaScript libraries
Unnecessary API requests
Heavy frontend scripts
WooCommerce core modifications
Duplicate stock data

The plugin should check the product state only where required.

Development Architecture

The plugin should use a modular architecture.

Suggested structure:

woocommerce-stock-inquiry/
│
├── woocommerce-stock-inquiry.php
├── uninstall.php
├── readme.txt
├── README.md
├── LICENSE
├── CHANGELOG.md
│
├── includes/
│   ├── class-plugin.php
│   ├── class-settings.php
│   ├── class-stock-checker.php
│   ├── class-button.php
│   ├── class-contact.php
│   ├── class-whatsapp.php
│   └── class-product-rules.php
│
├── admin/
│   ├── class-admin.php
│   ├── views/
│   │   └── settings-page.php
│   └── assets/
│       ├── admin.css
│       └── admin.js
│
├── public/
│   ├── css/
│   │   └── frontend.css
│   └── js/
│       └── frontend.js
│
├── languages/
│
└── assets/
    ├── banner-1544x500.png
    ├── icon-256x256.png
    └── screenshots/

Use a unique prefix such as:

wsi_

for functions, options, hooks, and other identifiers where appropriate.

Development Principles

The project follows these principles:

WooCommerce remains the source of truth.
Never modify WooCommerce core files.
Never hide products.
Never modify product stock values.
Never require a shortcode for core functionality.
Prefer native WooCommerce hooks and filters.
Keep frontend JavaScript minimal.
Keep the plugin lightweight.
Make the admin interface easy for non-developers.
Keep future functionality modular.
Follow WordPress coding standards.
Follow WordPress security practices.
Keep the plugin translation-ready.
Avoid unnecessary third-party dependencies.
Elementor Workflow

The intended workflow is extremely simple.

Create Elementor Loop Item
            ↓
Build your normal product layout
            ↓
Create Elementor Loop Grid
            ↓
Install WooCommerce Stock Inquiry
            ↓
Configure stock threshold
            ↓
Plugin automatically handles inquiry action

You should not need to add:

[stock_inquiry_button]

to every product template.

Compatibility Philosophy

The plugin should integrate with WooCommerce instead of replacing WooCommerce's product system.

Preferred implementation order:

1. Native WooCommerce hooks/filters
            ↓
2. Elementor compatibility integration
            ↓
3. Lightweight frontend fallback
               only when necessary

Avoid fragile JavaScript that searches for arbitrary buttons by CSS selectors when a reliable server-side WooCommerce hook is available.

V1 Scope
Included in V1
Automatic stock threshold detection
Add to Cart replacement
Automatic restoration after restocking
Contact Page inquiry
WhatsApp inquiry
Custom WhatsApp message
Product name placeholder
Product URL placeholder
Button text customization
Button styling
Live button preview
Product exclusions
Category exclusions
Button position settings
Elementor compatibility
WooCommerce archive compatibility
Single product compatibility
Related product compatibility
Responsive frontend
Security and sanitization
Translation-ready architecture
Uninstall cleanup
Documentation
Not Included in V1

The following features are intentionally reserved for future versions:

Popup inquiry form
Inquiry database
Email notification system
Multiple WhatsApp numbers
Advanced inquiry analytics
Variation-specific inquiry forms
Elementor custom widget
Gutenberg block
Advanced conditional rules
Multiple inquiry templates
CRM integrations
V2 Roadmap
Popup Inquiry System

V2 will introduce a built-in popup inquiry system.

Planned features:

Popup inquiry form
Product information automatically attached
Customer name
Email
Phone
Message
Custom fields
AJAX submission
Form validation
Admin email notifications
Customer confirmation
Popup styling controls

Example:

Product
   ↓
Send Inquiry
   ↓
Popup
   ↓
Customer fills form
   ↓
Submit Inquiry
   ↓
Admin Notification

The popup should be modular and should not affect the existing Contact Page or WhatsApp functionality.

V3 Roadmap
Advanced Inquiry Management

V3 may turn the plugin into a complete product inquiry management system.

Inquiry Database

Potential information:

Product
Customer Name
Email
Phone
Message
Date
Inquiry Source
Status
Inquiry Status
New
Contacted
In Progress
Completed
Closed
Email Notifications

Potential functionality:

Admin Notification
Customer Confirmation
Custom Email Templates
Multiple WhatsApp Numbers

Future rules could allow different WhatsApp numbers based on:

Product
Category
Department
Store Location
Advanced Rules

Example:

IF stock < 5
    → Send Inquiry

IF category = Electronics
    → WhatsApp

IF category = Furniture
    → Contact Page
Analytics

Potential metrics:

Total inquiries
Product inquiries
Most requested products
Inquiry method
Inquiry trends
Date/time analysis
Future Ideas

The following features may be considered in future releases:

Elementor custom widget
Gutenberg block
Bricks compatibility
Additional page-builder integrations
Variation-specific inquiry buttons
Category-specific button styles
Product-specific messages
Multiple inquiry templates
Custom inquiry fields
REST API
Webhook support
CRM integrations
Telegram integration
Messenger integration
Inquiry export
Advanced analytics
Custom popup templates
Role-based inquiry management
Product-specific inquiry settings
Multiple inquiry buttons
Inquiry automation

These are future ideas and are not part of V1.

Testing Checklist

Before every release, test the following.

Stock Threshold
 Stock = 10
 Stock = 5
 Stock = 4
 Stock = 1
 Stock = 0
Product Types
 Simple product
 Variable product
 Stock management disabled
 Backorder-enabled product
 Manually out-of-stock product
WooCommerce Locations
 Shop
 Category archive
 Search results
 Single product
 Related products
 Upsells
 Cross-sells
Elementor
 Loop Grid
 Loop Item
 Archive template
 Products widget
Inquiry Methods
 Contact Page
 WhatsApp
 Invalid WhatsApp number
 Missing WhatsApp number
 Same Tab
 New Tab
Product Rules
 Product exclusion
 Category exclusion
 Threshold changes
 Plugin disabled
 Product restocked
Frontend
 Desktop
 Tablet
 Mobile
 Normal button state
 Hover state
 Button styling
 No layout break
 No PHP errors
 No JavaScript errors
Example User Journey
Normal Product
Customer visits product
        ↓
Stock = 20
        ↓
Add to Cart
Low Stock Product
Customer visits product
        ↓
Stock = 3
        ↓
Send Inquiry
        ↓
WhatsApp
        ↓
Pre-filled product message
Out-of-Stock Product
Customer visits product
        ↓
Stock = 0
        ↓
Send Inquiry
        ↓
Contact Page / WhatsApp
Restocked Product
Admin updates stock
        ↓
Stock = 15
        ↓
WooCommerce recognizes product as available
        ↓
Add to Cart returns
Contribution

Contributions are welcome.

Before submitting a Pull Request:

Test the change.
Test simple products where relevant.
Test variable products where relevant.
Follow WordPress coding standards.
Sanitize all input.
Validate all settings.
Escape all output.
Avoid unnecessary dependencies.
Update documentation.
Update the changelog.
Explain the problem and solution in the Pull Request.

For larger changes, open an issue before starting development.

Bug Reports

When reporting a bug, please provide:

WordPress version
WooCommerce version
PHP version
Plugin version
Theme
Elementor version, if applicable
Product type
Stock configuration
Steps to reproduce
Expected behavior
Actual behavior
Screenshots
Relevant error messages

Do not include:

Passwords
API keys
Customer personal information
Private credentials
Other sensitive information
Feature Requests

Feature requests are welcome.

When submitting a feature request, explain:

What problem the feature solves
How the feature should work
Who would benefit from it
Whether the feature should be optional
Whether it affects existing functionality
Project Goals

WooCommerce Stock Inquiry aims to be:

Free
Open source
Lightweight
Easy to configure
Developer friendly
WooCommerce compatible
Elementor friendly
Secure
Translation ready
Extensible
WordPress.org friendly

The long-term goal is to provide a reliable inquiry system that can start simple and grow into a complete WooCommerce product inquiry platform.

License

WooCommerce Stock Inquiry is free and open-source software.

This project is intended to use:

GNU General Public License v2.0 or later

GPL-2.0-or-later

See the LICENSE file for complete license information.

Roadmap Summary
Version	Main Focus	Status
V1.0	Stock-based inquiry button, Contact Page, WhatsApp, button customization, rules, Elementor compatibility	In Development
V2.0	Popup inquiry system and notifications	Planned
V3.0	Inquiry management, database, advanced rules and analytics	Planned
Future	Integrations, widgets, blocks, automation and advanced features	Ideas
Support the Project

If you find WooCommerce Stock Inquiry useful:

Star the GitHub repository
Report bugs
Suggest features
Submit Pull Requests
Share the project
Help test new releases
Contribute improvements

Every contribution helps make the plugin better for WooCommerce users and developers.

Author

Shafiq Khan

WordPress Developer

GitHub: Add your GitHub profile URL

LinkedIn: Add your LinkedIn profile URL

Changelog

Release history will be maintained in:

CHANGELOG.md

Current planned release:

V1.0.0
Thank You

Thank you for using and supporting WooCommerce Stock Inquiry.

The project is built with the goal of keeping WooCommerce inquiry functionality simple for store owners while maintaining a clean and extensible architecture for developers.
