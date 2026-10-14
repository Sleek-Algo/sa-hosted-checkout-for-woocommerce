=== SA Hosted Checkout for WooCommerce ===
Contributors: sleekalgo
Tags: stripe, stripe checkout, woocommerce, secure checkout, express checkout
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept WooCommerce payments on Stripe Checkout, with cart discounts, fees, shipping, taxes and configurable payment methods.

== Description ==

SA Hosted Checkout for WooCommerce connects your store to Stripe's hosted payment page. Customers review their cart in WooCommerce and pay through Stripe Checkout. The plugin carries the saved order's discounts, fees, shipping and taxes into the payment total and verifies Stripe payment confirmations before marking an order paid.

The free version includes WooCommerce cart coupons, payment-method selection, checkout language selection and optional quantity changes for eligible products. Premium adds checkout custom fields and text, a session-management screen and administrator-only test mode.

=== 🗝️ Key FEATURES: ===

=  🎉 Free Version - Features: =

* Stripe authentication is supported using either a *Standard API Key* or a *Restricted API Key*.
* Send customers from the WooCommerce cart to Stripe's hosted checkout
* WooCommerce coupons and discounts applied in the cart are reflected in Stripe Checkout
* Include WooCommerce fees, shipping and taxes in the payment total, with separate lines for additional charges
* Choose eligible Stripe payment methods, including Cards and supported local payment methods
* Use the customer's browser language or choose a supported Stripe Checkout language
* Allow quantity changes for eligible products in Stripe Checkout (optional, disabled by default)
* Enable or Disable Phone Number field on Stripe Checkout
* Add or remove Terms & Services checkbox from Stripe Checkout
* Set an optional cancellation URL, or return customers to the cart

Payment-method availability depends on your Stripe account, enabled methods, business and customer countries, currency and order. Quantity changes have fixed-charge and stock-reservation limits explained in the FAQ below.


= 🌟 Premium Version - Features: 🎯 =

* All features in the free version and the following
* Add custom fields to Stripe Checkout which can be a number field, a text field and a dropdown field
* Add custom text to be displayed as terms and services text
* Add custom text to be displayed before and after submit button of Stripe Checkout
* Add custom text to be displayed after shipping address fields of Stripe Checkout
* View Stripe Checkout sessions created by the plugin, with their status and customer information
* Automatically enable test mode of Stripe payment for administrators of the website

Get the [premium version](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#buy_premium) now 🚀.

==👉 Video Tutorial 👈==

https://www.youtube.com/watch?v=2ktSTBzG95c

= Documentation 📚 =

Discover how to make the most of SA Hosted Checkout for WooCommerce with our detailed and user-friendly [documentation](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#installation-guide).

= 🌐 Translation Ready 🤩 =

The plugin includes translatable interface strings. Contribute translations through [WordPress.org](https://translate.wordpress.org/projects/wp-plugins/sa-hosted-checkout-for-woocommerce/). The Stripe Checkout language is configured separately in the plugin's Checkout Settings.

= ⏩ Use of Third-Party Libraries 🛠️ =

The *SA Hosted Checkout for WooCommerce* plugin has been built using the following third-party libraries to enhance functionality and user experience:

- [Stripe PHP SDK](https://github.com/stripe/stripe-php) – This SDK is integrated to support [Stripe Checkout](https://stripe.com/payments/checkout), allowing secure and seamless payment processing.
- [React.js](https://react.dev/) – React.js is used to manage the plugin’s admin interface components for a dynamic and responsive user experience.
- [Ant Design](https://ant.design/) – We used Ant Design and [Ant Design ProComponents](https://procomponents.ant.design/en-US) to create a polished and intuitive UI for the plugin's admin interfaces.
- [WordPress Scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/) – WordPress Scripts manage the plugin’s build system, as the admin interface is developed in React’s JSX syntax, with builds generated via WordPress's robust tooling.

= 😍 Useful Links 📌 =

* [Documentation](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#installation-guide)
* [Support Forum](https://wordpress.org/support/plugin/sa-hosted-checkout-for-woocommerce/)
* [Github](https://github.com/Sleek-Algo/sa-hosted-checkout-for-woocommerce/)
* [Translations](https://translate.wordpress.org/projects/wp-plugins/sa-hosted-checkout-for-woocommerce/)

= Become a Contributor 👨🏻‍💻 =

*SA Hosted Checkout for WooCommerce* is an open-source project, and we welcome contributors to be part of our vibrant community! Help us improve the plugin and make it even better - [GitHub Link](https://github.com/Sleek-Algo/sa-hosted-checkout-for-woocommerce/)

== 🤝 Support 👀 ==

For help, use the WordPress.org [Support Forum](https://wordpress.org/support/plugin/sa-hosted-checkout-for-woocommerce/). Before starting a new thread, check the [documentation](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#installation-guide) and existing support threads. Describe the issue and include your WordPress, WooCommerce and plugin versions. Do not post API keys or customer payment details.

==🐞 BUG REPORTS 📝 ==

Find a bug in *SA Hosted Checkout for WooCommerce* ? We welcome your bug reports! Please report bugs in the *SA Hosted Checkout for WooCommerce* [repository on GitHub](https://github.com/Sleek-Algo/sa-hosted-checkout-for-woocommerce). Note that GitHub is not a support forum but an efficient platform for addressing and resolving issues efficiently.

== Installation ==

Requires WordPress 6.3 or later, WooCommerce 8.6 or later, and PHP 7.4 or later with the curl, json and mbstring extensions.

* In your WordPress dashboard, choose Plugins > Add new.
* Search for the plugin with the search bar in the top right corner using the keywords "SA Hosted Checkout". A number of results may appear.
* After finding the plugin in the results, click Install Now. You can also click the plugin name to view more details about it.
* Activate the plugin, then configure your Stripe API credentials and webhook signing secret in Stripe Settings. Start in test mode.
* Select your checkout preferences and test your store's products, coupons, shipping, taxes and payment methods before enabling live payments.

For further details please check our [Installation Guide](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#installation-guide)

== Screenshots ==

1. How Stripe Checkout will look on your website
2. Enable SA Hosted Checkout for WooCommerce
3. Change Stripe mode between Test and Live
4. Change configuration for Stripe Checkout
5. Update cancellation page URL
6. Enable a custom Thank You page (premium)
7. Add custom text to Stripe Checkout (premium)
8. Add custom fields to Stripe Checkout (premium)
9. Select eligible payment methods for Stripe Checkout (free)
10. View Stripe Checkout session details (premium)

== Frequently Asked Questions ==

= Are fees, shipping and taxes included in Stripe Checkout? =

Yes. Stripe Checkout starts with the saved WooCommerce order total, including eligible discounts, fees, shipping and taxes. Positive fees and taxes are shown as separate lines, with shipping shown as a separate charge. WooCommerce calculates these amounts before Stripe Checkout opens; changing an address on Stripe Checkout does not recalculate WooCommerce shipping or taxes. Optional quantity changes affect eligible product amounts, but leave these charges and existing discount amounts fixed.

= Can I use WooCommerce coupons in the free version? =

Yes. To use coupons, enable "Enable the use of coupon codes" under WooCommerce > Settings > General and save your changes. The plugin respects this setting; it does not enable or disable coupons automatically. Customers apply coupon codes in the WooCommerce cart before opening Stripe Checkout, and eligible discounts are included in the payment total. This does not add coupon-code entry to Stripe Checkout. Coupon eligibility and usage restrictions still apply.

Coupons limited to one use per customer require login. For higher per-customer limits, guests must have a valid billing email available before applying the coupon; otherwise, they must log in.

= How does it bypass WooCommerce default checkout? =

When enabled and configured, the plugin can send customers from the WooCommerce cart to Stripe Checkout, including Cart and Mini-Cart block buttons. It also provides a gateway for the classic WooCommerce checkout. Direct payment-method integration with the WooCommerce Checkout block is not supported; use the classic Checkout shortcode for the native gateway. Custom checkout buttons, themes and other checkout extensions may behave differently; test your store's checkout paths before going live. Orders with no payment due use WooCommerce checkout.

= I have installed the plugin but my plugin appears disabled =

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

Copy the webhook endpoint URL shown in Stripe Settings and configure it in the matching Stripe test or live environment. Subscribe to checkout.session.completed, checkout.session.async_payment_succeeded and checkout.session.async_payment_failed, then save that endpoint's signing secret in the plugin. The plugin also accepts payment_intent.succeeded and charge.succeeded as payment-confirmation events. You do not need to select every Stripe event.

Use a publicly reachable HTTPS endpoint and verify successful delivery in Stripe. Webhooks allow order updates when the customer does not return to your store. They are required for delayed-payment methods and quantity adjustment; keep them working for ordinary card payments as well.

= I want to customize my Stripe Checkout page =

The [premium version](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#buy_premium) of SA Hosted Checkout for WooCommerce provides options to add custom text, number and dropdown fields for better customization.

= How can I check Stripe Checkout sessions which were created on my website but never completed? =

The [premium version](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#buy_premium) offers you access to Stripe Sessions for quick and easy viewing of the customer and order details.


== External services ==

This plugin uses Stripe Checkout to process payments and requires a Stripe account. After you configure Stripe API credentials, the plugin contacts Stripe to verify the account connection and create, retrieve or expire checkout sessions and verify their payment status.

Requests include order and item references, verification metadata, product names and public image URLs when available, quantities, totals, discounts, fees, shipping, taxes, currency, store return URLs and the billing email when available. USD bank transfers also require a Stripe customer record associated with the order. Customers enter payment details directly on Stripe's hosted page. Stripe may also collect their name, billing address, shipping address and phone number according to the checkout settings. The plugin receives payment status, customer details and, when quantity adjustment is enabled, final line-item quantities and amounts from Stripe to update the WooCommerce order.

See the [Stripe Privacy Policy](https://stripe.com/privacy) and [Stripe Services Agreement](https://stripe.com/legal/ssa).

== Source code and licenses ==

Readable admin JavaScript and SCSS source is included in node-dev/free-app. The package includes node-dev/package.json, node-dev/webpack.config.js and build instructions in README.md. Run npm install followed by npm run build from node-dev to build assets/backend. Third-party license notices are included in THIRD-PARTY-NOTICES.txt and the bundled libraries' license files. The plugin is licensed under GPLv2 or later; see LICENSE.

== Changelog ==

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

= 1.0.7 =

Payment-method selection, checkout language and optional quantity changes are now free. Existing card-only and automatic-language defaults are preserved. Quantity changes are disabled by default and require a working Stripe webhook; existing discount amounts, shipping, fees and taxes stay fixed. Back up your site and test your checkout paths before updating.

= 1.0.6 =

Includes payment-total, coupon and payment-confirmation fixes. Back up your site and test in staging before updating. Requires WordPress 6.3, WooCommerce 8.6 and PHP 7.4 or later. Enable coupons in WooCommerce settings if needed; this update does not change that setting.
