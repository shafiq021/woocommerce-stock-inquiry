# Changelog

## 1.1.0

- Settings reorganised into per-element tabs: General, Inquiry Method, Heading, Button, Description, Branding, Product Rules, Advanced. Each element keeps all of its own controls.
- Heading and Description controls only appear while their toggle is on.
- New "Where to Show" switches (single product page / product listings) for Heading, Description and Branding. Default: only on the single product page; listings get just the button.
- New Branding tab: font family, font size and placement (left / center / right). No color control.
- Font family controls for Button, Heading, Description and Branding; heading font weight.
- Live preview is a sticky right-hand column on every tab, rendered with the same classes and CSS as the front end (heading → button → description → branding), with a Product page / Listings switch. On screens under 1100px it stacks above the controls.
- WhatsApp preview is now a chat-bubble mockup (gray incoming, green outgoing with tails) that shows the full message and updates as you type the template.
- Branding is rendered server-side as plain markup (no JavaScript).
- Fixed undefined-index notices for the font family fields that were not in the defaults/sanitizer.

## 1.0.0

- Initial release.
