=== SA Hosted Checkout for WooCommerce ===
Contributors: sleekalgo
Tags: stripe, stripe checkout, woocommerce, payment gateway, embedded checkout
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Take customers from WooCommerce cart to Stripe Checkout with hosted, embedded or popup checkout, free branding and WooCommerce coupon support.

== Description ==

SA Hosted Checkout for WooCommerce replaces the standard WooCommerce checkout form with Stripe Checkout for carts requiring payment. Customers can continue from supported cart and mini-cart buttons or visit the store's checkout page directly, without filling in two checkout forms. They review products and apply WooCommerce coupon codes in the cart, then enter their payment details through Stripe.

Choose a Stripe-hosted payment page, Embedded Checkout on a page in your store, or Embedded Checkout in a popup. All three checkout experiences are free, together with branding controls, WooCommerce cart discounts, payment-method selection, checkout language selection and optional quantity changes for eligible products.

The plugin creates a WooCommerce order from the cart and uses its saved totals for Stripe Checkout. Payment confirmations are verified before the order is marked paid. See the FAQ for supported checkout paths, payment-method eligibility and the calculation limits that apply when customers change quantities or addresses.

= Free features =

* Hosted, embedded page and popup checkout: choose how customers use Stripe Checkout.
* Cart-to-Stripe checkout: bypass the standard checkout form from supported cart and mini-cart buttons and ordinary checkout-page visits, including WooCommerce Cart, Mini-Cart and Checkout blocks and the classic Checkout shortcode.
* Branding controls: set the display name, background and button colors, font and corner shape, or inherit Stripe Dashboard branding. Custom logo overrides are available for Hosted Checkout.
* Dedicated payment page: create one automatically or select a published page containing the [sahcfwc_checkout] shortcode.
* WooCommerce coupons and discounts: include eligible discounts already applied in the cart.
* Order totals: include WooCommerce shipping, fees and taxes, with separate lines for additional charges.
* Payment-method selection: choose Cards and eligible local payment methods supported by the plugin and your Stripe account.
* Checkout language: use the customer's browser language or choose a supported Stripe language.
* Optional quantity changes: let customers adjust eligible product quantities within configured limits. Disabled by default.
* Checkout fields: enable phone-number collection and the terms of service checkbox.
* Hosted cancellation URL: choose a return page or send customers back to the cart.
* Stripe connection: use a standard secret API key or a restricted API key with the required permissions, in test or live mode.
* WooCommerce High-Performance Order Storage (HPOS) compatibility.

Payment-method availability depends on your Stripe account, enabled methods, business and customer countries, currency and order. WooCommerce calculates discounts, shipping, fees and taxes before Stripe Checkout opens. These amounts are not recalculated in Stripe when a customer changes an address or product quantity. See the FAQ before enabling quantity changes.

= Premium features =

The separate premium edition adds:

* Custom text, number and dropdown fields.
* Custom terms of service text and text before or after the payment button.
* Custom text alongside shipping-address collection.
* A session-management screen for checkout sessions created by the plugin.
* Administrator-only test mode.

Compare editions on the [plugin website](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#buy_premium). Hosted, embedded page and popup checkout, basic branding, cart coupons, payment-method selection, language selection and optional quantity changes do not require premium.

= Setup tutorial =

https://www.youtube.com/watch?v=HabYcVLdrkI

For current setup instructions and checkout options, see the [installation guide](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#installation-guide).

= Translations =

The plugin includes translatable interface strings. Contribute translations through [WordPress.org](https://translate.wordpress.org/projects/wp-plugins/sa-hosted-checkout-for-woocommerce/). The Stripe Checkout language is configured separately in the plugin's Checkout Settings.

= Support and contributions =

For help, use the WordPress.org [Support Forum](https://wordpress.org/support/plugin/sa-hosted-checkout-for-woocommerce/). Before starting a new thread, check the [documentation](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#installation-guide) and existing support threads. Describe the issue and include your WordPress, WooCommerce and plugin versions. Do not post API keys or customer payment details.

Report reproducible bugs or contribute code through [GitHub](https://github.com/Sleek-Algo/sa-hosted-checkout-for-woocommerce/). Source files, build instructions and third-party license notices are included with the plugin.

== Installation ==

Requires WordPress 6.3 or later, WooCommerce 8.6 or later, and PHP 7.4 or later with the curl, json and mbstring extensions.

1. Install and activate WooCommerce. In Plugins > Add New Plugin, search for "SA Hosted Checkout for WooCommerce", then install and activate it.
2. Open the plugin's Stripe Settings. Select test mode and enter your Stripe secret or restricted API key. Embedded Checkout also requires the publishable key from the same Stripe account and mode.
3. Copy the webhook endpoint URL shown in Stripe Settings. In the matching Stripe test environment, add this HTTPS endpoint and subscribe to checkout.session.completed, checkout.session.async_payment_succeeded and checkout.session.async_payment_failed. Save the endpoint's signing secret in the plugin. See the webhook FAQ below.
4. Enable checkout and choose Hosted Checkout, Embedded payment page or Embedded popup. Hosted remains the default for existing installations.
5. For either Embedded mode, use Automatic to create a dedicated payment page when settings are saved, or select a published page containing [sahcfwc_checkout]. Do not use the WooCommerce Cart, Checkout or My Account page. Keep this payment page and its query-string URLs out of page caches.
6. Optionally enable Checkout branding. Leave individual fields empty to inherit Stripe Dashboard settings. Custom logos apply to Hosted Checkout only and require a publicly accessible HTTPS PNG, JPEG or SVG image URL.
7. Configure Checkout Settings and Payment Methods, then save. Enable coupons in WooCommerce settings if you want customers to apply them in the cart. Review the fixed-charge limits before enabling quantity changes.
8. Verify test payments, order updates and webhook delivery with your products, coupons, shipping, taxes and selected payment methods. Before accepting live payments, switch to matching live API keys and a live webhook endpoint's signing secret. Use HTTPS in production.

See the [installation guide](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#installation-guide) for illustrated setup instructions.

== Screenshots ==

1. Stripe Checkout illustration for desktop and mobile.
2. Embedded Checkout on a payment page within your store.
3. Embedded Checkout in a popup over the WooCommerce cart.
4. Connect Stripe and configure the webhook, with credentials hidden.
5. Choose Hosted Checkout, Embedded payment page or Embedded popup.
6. Customize checkout branding, including colors, font and corner shape.
7. Configure checkout fields and optional product quantity limits.
8. Set the checkout language and Hosted Checkout cancellation URL.
9. Select eligible Stripe payment methods for your store.

== Frequently Asked Questions ==

= Does this plugin bypass the WooCommerce checkout form? =

Yes. When the plugin is enabled and configured and the cart requires payment, supported cart and mini-cart buttons take customers to the selected Stripe Checkout experience. Direct visits to the ordinary checkout page also open Stripe Checkout instead of displaying the standard WooCommerce form. Both classic Checkout shortcode pages and WooCommerce Checkout block pages are supported by this replacement flow.

Customers do not need to fill in two checkout forms. The plugin replaces the standard form rather than adding a payment-method choice inside the WooCommerce Checkout block. JavaScript must be enabled. Empty carts, orders with no payment due, order-payment links, receipt pages and API requests are not taken over by the ordinary checkout-page replacement. The separate classic gateway remains available for supported existing-order payment flows.

Custom checkout buttons, themes and checkout extensions require compatibility testing. In popup mode, intercepted cart-button clicks do not emit WooCommerce's cart_proceed_to_checkout event, so extensions that depend on this event need separate testing.

= Is Embedded Checkout included in the free version? =

Yes. In Stripe Settings, choose Hosted Checkout, Embedded payment page, or Embedded popup. Hosted redirects to Stripe and remains the default for existing installations. Embedded page and popup display Stripe's payment interface within your website. Some payment methods still redirect customers to their bank or payment provider for authorization.

Embedded Checkout needs a publishable key from the same Stripe account and mode as the configured secret or restricted key. Use HTTPS in production. Apple Pay and Google Pay also depend on Stripe payment-method domain registration, an eligible account, browser and device.

= How do I choose the Embedded payment page? =

Use Automatic to create a dedicated payment page when you save Stripe Settings. Alternatively, publish a page containing the [sahcfwc_checkout] shortcode and select it. The page must not be password protected or assigned as the WooCommerce Cart, Checkout or My Account page. Customers reach it through a private order-payment link; adding the shortcode alone does not create a payable order.

Popup checkout uses the same payment page for existing-order payment links and returns from payment providers. Closing the popup does not cancel the order or create another payment session; customers can reopen it while the session remains payable. Exclude the payment page and its query-string URLs from page caching.

After an administrator changes the checkout mode, an existing popup session may continue on the dedicated payment page instead of being replaced.

If payment has already been verified for the shopper's unchanged cart, reopening checkout returns the existing order receipt. If the paid cart cannot be matched safely, checkout stops with a review notice instead of starting another payment.

= Are checkout branding controls free? =

Yes. Enable Checkout branding in Stripe Settings to set the checkout display name, background and button colors, font and corner shape. Leave individual fields empty, or disable the override, to use Stripe Dashboard branding.

Custom logo overrides apply to Hosted Checkout only. Stripe's Embedded Checkout does not accept per-session logo or icon overrides, so those images are not sent in Embedded page or popup mode. A Hosted logo must be a publicly accessible HTTPS image with a URL path ending in .png, .jpg, .jpeg, .jfif, .pjpeg, .pjp or .svg. Query strings are allowed. WebP, localhost, private-network and password-protected images cannot be used.

Branding changes apply to new checkout sessions and do not change your Stripe account settings, receipts or invoices. An already-open session retains its existing settings. Stripe controls the payment interface, so arbitrary WordPress CSS cannot style the inside of its protected payment frame.

= How are discounts, fees, shipping and taxes calculated? =

WooCommerce calculates the cart before Stripe Checkout opens. The plugin creates an order and uses that saved total, including eligible discounts, fees, shipping and taxes, for payment. Positive fees and taxes are shown as separate lines, with shipping shown as a separate charge.

Changing an address on Stripe Checkout does not recalculate WooCommerce shipping or taxes. Optional quantity changes affect eligible product amounts, but leave these charges and existing discount amounts fixed. Stores with address-dependent shipping, tax or discount rules should test their calculation and checkout paths before going live.

= Can I use WooCommerce coupons in the free version? =

Yes. To use coupons, enable "Enable the use of coupon codes" under WooCommerce > Settings > General and save your changes. The plugin respects this setting; it does not enable or disable coupons automatically. Customers apply coupon codes in the WooCommerce cart before opening Stripe Checkout, and eligible discounts are included in the payment total. This does not add coupon-code entry to Stripe Checkout. Coupon eligibility and usage restrictions still apply.

Coupons limited to one use per customer require login. For higher per-customer limits, guests must have a valid billing email available before applying the coupon; otherwise, they must log in.

= Why is checkout unavailable after installation? =

Enter the appropriate Stripe API credentials and webhook signing secret in Stripe Settings, select the matching test or live mode, and enable checkout. Start in test mode and check any setup notice if checkout remains unavailable.

= Can I select payment methods in the free version? =

Yes. In Payment Methods, keep the default card-only mode or select a custom set of methods from the supported catalog. Examples include Cards, Link, iDEAL | Wero, Klarna, SEPA Direct Debit, ACH Direct Debit and USD bank transfer. Card brands such as Visa and Mastercard are included under Cards, not separate payment-method choices. USD bank transfer and ACH Direct Debit are different methods.

Enable the relevant methods in your Stripe account. Stripe determines availability using the account, business and customer countries, currency, order amount and other eligibility rules; selecting a method does not guarantee it will be displayed. See [Stripe's payment-method support](https://docs.stripe.com/payments/payment-methods/payment-method-support). Link requires Cards to be selected. Canadian pre-authorized debit also requires a Personal or Business mandate type in the plugin settings. Delayed-payment methods require a configured, working Stripe webhook.

= Can I choose the checkout language in the free version? =

Yes. In Checkout Settings, use automatic browser-language detection or select one of the Stripe languages listed by the plugin. This changes Stripe Checkout, not the language of your WordPress site or product names.

= Can customers change quantities in Stripe Checkout? =

Yes, when you enable the free "Allow quantity changes in Stripe Checkout" option. It is disabled by default and requires a working Stripe webhook. Eligible simple products and variations can be adjusted within the allowed limits. Sold-individually products and product lines that need split prices are not adjustable. Carts containing fractional quantities keep fixed quantities. Customers cannot remove a product by reducing its quantity to zero, and discounts can impose a higher minimum.

WooCommerce discount amounts, shipping charges, fees and taxes stay fixed when quantities change in Stripe Checkout. Coupon rules and other cart pricing rules are not recalculated. Enable only if these fixed charges are appropriate for your store. For example, an existing percentage coupon's calculated discount amount does not grow when the customer adds more units in Stripe.

The maximum defaults to 10 per eligible product and can be set from 1 to 999999. A higher quantity already in the cart is preserved, and available stock may lower the allowed maximum. For stock-managed products, the allowed maximum is reserved while checkout is open, not just the starting cart quantity. Choose a cap appropriate for your inventory.

Verified final quantities and totals are synced to WooCommerce after checkout is submitted. A submitted checkout is not proof of payment: delayed payments remain on hold until Stripe confirms payment. If the plugin cannot verify the final quantities or stock reservation, the order requires review before fulfillment.

= Which webhook events should I configure? =

Copy the webhook endpoint URL shown in Stripe Settings and configure it in the matching Stripe test or live environment. In Stripe Workbench > Webhooks, create an event destination for Your account, use the Snapshot payload format, and choose Webhook endpoint as the destination type. The route is /wp-json/sahcfwc/v1/webhooks/stripe-listener on your site's URL; copy the plugin's displayed Webhook URL rather than the WordPress admin address or a guessed URL, particularly if WordPress is installed in a subdirectory.

Subscribe to checkout.session.completed, checkout.session.async_payment_succeeded and checkout.session.async_payment_failed, then save that endpoint's signing secret in the plugin. A webhook signing secret is separate from an API key. Test and live endpoints have separate signing secrets. The plugin also accepts payment_intent.succeeded and charge.succeeded as payment-confirmation events. You do not need to select every Stripe event.

Use a publicly reachable HTTPS endpoint and verify successful delivery in Stripe. Webhooks allow order updates when the customer does not return to your store. They are required for delayed-payment methods and quantity adjustment; keep them working for ordinary card payments as well.

= Which checkout customizations require premium? =

Basic checkout branding is free, including the display name, colors, font and corner shape, plus a custom logo for Hosted Checkout. The [premium version](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#buy_premium) provides additional custom text and text, number and dropdown fields.

= Can I view incomplete checkout sessions? =

The premium edition includes a session-management screen for sessions created by this plugin, with customer, order and payment-status details. In either edition, you can also review payment activity in your Stripe Dashboard.


== External services ==

This plugin uses Stripe Checkout to process payments and requires a Stripe account. After you configure Stripe API credentials, the plugin contacts Stripe to verify the account connection and create, retrieve or expire checkout sessions and verify their payment status.

Requests include order and item references, verification metadata, product names and public image URLs when available, quantities, totals, discounts, fees, shipping, taxes, currency, store return URLs and the billing email when available. Enabled branding overrides also send the selected display name, colors, font and corner shape to Stripe; Hosted Checkout can also receive a public logo URL. Stripe retrieves the logo image; WordPress does not download it through this feature. USD bank transfers also require a Stripe customer record associated with the order.

The storefront loads Stripe.js directly from js.stripe.com when checkout is enabled or an existing payment page is opened. This connects the customer's browser to Stripe to provide its payment interface and payment security services. Customers enter payment details directly into Stripe's hosted page or Stripe-controlled embedded interface, not a WordPress payment-card form. Stripe may also collect their name, billing address, shipping address and phone number according to the checkout settings. The plugin receives payment status, customer details and, when quantity adjustment is enabled, final line-item quantities and amounts from Stripe to update the WooCommerce order.

See the [Stripe Privacy Policy](https://stripe.com/privacy) and [Stripe Services Agreement](https://stripe.com/legal/ssa).

== Source code and licenses ==

Readable admin JavaScript and SCSS source is included in node-dev/free-app and node-dev/shared. The storefront checkout controller and styles are in node-dev/frontend/checkout. The package includes node-dev/package.json, node-dev/webpack.config.js and build instructions in README.md. From node-dev, run npm install, then npx cross-env NODE_ENV=production CREATE_ZIP=no npm run build to generate assets/backend and assets/frontend/checkout. Stripe.js is loaded directly from Stripe, not bundled or self-hosted. Third-party license notices are included in THIRD-PARTY-NOTICES.txt and the bundled libraries' license files. The plugin is licensed under GPLv2 or later; see LICENSE.

== Changelog ==

= 1.1.1 =

* Replaced the standard WooCommerce checkout form on ordinary checkout-page visits with the selected Stripe Checkout experience for carts requiring payment, including classic shortcode and Checkout block pages.
* Added automatic checkout launch with clear retry and return-to-cart controls, while preserving empty-cart, zero-total, existing-order payment and receipt flows.
* Prevented hidden or prerendered pages from starting a checkout session and required POST requests for checkout creation.
* Updated the plugin description, free and premium feature lists, installation instructions and FAQs, including webhook setup and checkout calculation limits.

= 1.1.0 =

* Added free Embedded Checkout on a dedicated store page or in a popup, while preserving Hosted Checkout as the default for existing installations.
* Added free checkout branding controls for the display name, background and button colors, font and corner shape, plus a Hosted Checkout logo override, with Stripe Dashboard inheritance for empty fields.
* Added automatic payment-page creation and support for a custom published page containing [sahcfwc_checkout].
* Added mode-matched publishable-key validation and private, non-cached access to embedded payment sessions.
* Reused saved WooCommerce order totals and verified payment handling across all three checkout experiences, including existing-order payment links and payment-provider returns.
* Added popup close/reopen handling and retry safeguards without creating parallel payment sessions for an order.
* Fixed WooCommerce Cart block checkout buttons remaining in a loading state after closing Embedded Checkout.
* Kept Stripe quantity and payment-authentication dialogs accessible above the checkout popup.
* Added receipt recovery when an unchanged cart's payment has already been verified, without rechecking consumed stock or coupon uses. An unverifiable paid cart is blocked from starting another payment.
* Fixed unnecessary payment-processing conflicts between stores using separate databases on the same database server.
* Updated the bundled Stripe PHP SDK to 18.0.0 with Stripe API version 2025-09-30.clover.
* Updated setup instructions, free-feature descriptions, Stripe.js service disclosure and readable storefront source/build information.

= 1.0.7 =

* Made Stripe payment-method selection and checkout language selection available in the free version, while preserving the default card-only and automatic-language behavior.
* Added validation for method currency eligibility, Link's Cards requirement and Canadian debit mandate settings, with webhook requirements for delayed payments.
* Added optional quantity changes for eligible products, configurable maximum quantities and stock reservations while checkout is open.
* Added verification and WooCommerce order synchronization for final Stripe quantities and totals, including delayed-payment handling. Existing discounts, fees, shipping and taxes remain fixed when quantities change.
* Reloaded persisted order state under the payment lock to prevent overlapping callbacks from repeating completion emails or reopening refunded orders.
* Restored coupon usage after failed delayed payments on older supported WooCommerce versions, without double-counting retries.
* Declared compatibility with WooCommerce High-Performance Order Storage after testing both order-storage modes.
* Improved settings save validation and error recovery; an empty optional cancellation URL no longer prevents saving Checkout Settings.
* Updated free and premium feature descriptions and documented payment-method, webhook and quantity-adjustment limits.

= 1.0.6 =

* Added WooCommerce cart coupon support to the free version while preserving the store's coupon setting and eligibility rules.
* Fixed payment totals to include saved WooCommerce order discounts, fees, shipping and taxes, with separate lines for additional charges.
* Improved checkout validation, rounding and retry handling to prevent mismatched totals and duplicate checkout sessions.
* Improved payment confirmation and webhook validation, including protection against repeated order completion.
* Improved cancellation and expired-session handling while preserving the customer's cart.
* Replaced unclear setup and payment notices with professional messages, including guidance for missing Stripe keys.
* Corrected the settings screen version display and updated compatibility requirements to WordPress 6.3, WooCommerce 8.6 and PHP 7.4 or later.

= 1.0.5 =

* Updated WordPress and PHP compatibility metadata and the release version.

= 1.0.4 =

* Fixed slow redirection to Stripe Checkout.

= 1.0.3 =

* Fixed a PHP fatal error in the webhook listener.

= 1.0.2 =

* Fixed switching integration modes in Stripe settings.
* Added Stripe Restricted API Key authentication support.

= 1.0.1 =

* Fixed support for virtual products.
* Preserved the cart when customers return from Stripe Checkout.
* Updated Delete Temporary Order to remove only the temporary order, without emptying the cart.

= 1.0.0 =

* Initial release.

== Upgrade Notice ==

= 1.1.1 =

Fixes ordinary checkout-page visits showing the WooCommerce form before Stripe Checkout. Paid carts now use the selected Hosted, Embedded Page or Embedded Popup experience directly. Existing checkout settings are preserved. Back up your site and test custom checkout extensions, order-payment links and webhook delivery after updating.

= 1.1.0 =

Embedded page, popup checkout and branding controls are now free. Existing installations retain Hosted Checkout. Embedded modes need a matching Stripe publishable key and a dedicated payment page; use HTTPS in production. Existing WooCommerce discounts, shipping, fees and taxes keep their current calculation rules. Back up your site and test checkout and webhook delivery before enabling the new modes.

= 1.0.7 =

Payment-method selection, checkout language and optional quantity changes are now free. Existing card-only and automatic-language defaults are preserved. Quantity changes are disabled by default and require a working Stripe webhook; existing discount amounts, shipping, fees and taxes stay fixed. Back up your site and test your checkout paths before updating.

= 1.0.6 =

Includes payment-total, coupon and payment-confirmation fixes. Back up your site and test in staging before updating. Requires WordPress 6.3, WooCommerce 8.6 and PHP 7.4 or later. Enable coupons in WooCommerce settings if needed; this update does not change that setting.
