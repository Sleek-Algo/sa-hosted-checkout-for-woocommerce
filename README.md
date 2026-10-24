# SA Hosted Checkout for WooCommerce

## Description

SA Hosted Checkout for WooCommerce lets you choose how customers pay: redirect to Stripe's hosted checkout, embed checkout on a page in your store, or open it in a popup. All three experiences are available in the free version, with branding controls for your display name, colors, font and corner shape. Hosted Checkout also supports a custom logo.

Customers review their cart in WooCommerce and pay through Stripe Checkout. The free version includes WooCommerce cart coupons, payment-method selection, checkout language selection and optional quantity changes for eligible products. The plugin carries the saved order's discounts, fees, shipping and taxes into the payment total and verifies Stripe payment confirmations before marking an order paid. Additional premium tools include checkout custom fields and text, a session-management screen and administrator-only test mode.

## Version 1.1.1

Version 1.1.1 bypasses the standard WooCommerce form on ordinary checkout-page visits for carts requiring payment, including classic shortcode and Checkout block pages. Customers continue to the selected Hosted, Embedded Page or Embedded Popup experience without completing a second checkout form. Empty-cart, zero-total, existing-order payment and receipt flows retain their appropriate WooCommerce handling. All three experiences and basic branding remain free. See [CHANGELOG.md](CHANGELOG.md) for the release notes.

**Prerequisites**

- WordPress 6.3 or later
- WooCommerce 8.6 or later
- PHP 7.4 or later, with the curl, json and mbstring extensions

### 🎉 Free Version - Features

* Stripe authentication is supported using either a *Standard API Key* or a *Restricted API Key*.
* Choose Hosted Checkout, Embedded Checkout on a store page, or Embedded Checkout in a popup
* Customize the checkout display name, background and button colors, font and corner shape, or inherit your Stripe Dashboard branding; add a custom logo for Hosted Checkout
* Create a dedicated payment page automatically, or choose a published page containing `[sahcfwc_checkout]`
* WooCommerce coupons and discounts applied in the cart are reflected in Stripe Checkout
* Include WooCommerce fees, shipping and taxes in the payment total, with separate lines for additional charges
* Choose eligible Stripe payment methods, including Cards and supported local payment methods
* Use the customer's browser language or choose a supported Stripe Checkout language
* Allow quantity changes for eligible products in Stripe Checkout (optional, disabled by default)
* Enable or Disable Phone Number field on Stripe Checkout
* Add or remove Terms & Services checkbox from Stripe Checkout
* Set an optional Hosted Checkout cancellation URL, or return customers to the cart

Payment-method availability and quantity-adjustment limits are explained below.

### 🌟 Premium Version - Features: 🎯

* Add custom fields to Stripe Checkout which can be a number field, a text field and a dropdown field
* Add custom text to be displayed as terms and services text
* Add custom text to be displayed before and after submit button of Stripe Checkout
* Add custom text to be displayed after shipping address fields of Stripe Checkout
* View Stripe Checkout sessions created by the plugin, with their status and customer information
* Automatically enable test mode of Stripe payment for administrators of the website

Get the [premium version](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/) now 🚀.

### 👉 Video Tutorial 👈

https://www.youtube.com/watch?v=2ktSTBzG95c


### Documentation 📚

Discover how to make the most of SA Hosted Checkout for WooCommerce with our detailed and user-friendly [documentation](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#installation-guide).

### 🌐 Translation Ready 🤩

The plugin includes translatable interface strings. Contribute translations through [WordPress.org](https://translate.wordpress.org/projects/wp-plugins/sa-hosted-checkout-for-woocommerce/). The Stripe Checkout language is configured separately in the plugin's Checkout Settings.

### ⏩ Use of Third-Party Libraries 🛠️ 

The *SA Hosted Checkout for WooCommerce* plugin has been built using the following third-party libraries to enhance functionality and user experience:

- [Stripe PHP SDK](https://github.com/stripe/stripe-php) – This SDK is integrated to support [Stripe Checkout](https://stripe.com/payments/checkout), allowing secure and seamless payment processing.
- [React.js](https://react.dev/) – React.js is used to manage the plugin’s admin interface components for a dynamic and responsive user experience.
- [Ant Design](https://ant.design/) – We used Ant Design and [Ant Design ProComponents](https://procomponents.ant.design/en-US) to create a polished and intuitive UI for the plugin's admin interfaces.
- [WordPress Scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/) – WordPress Scripts manage the plugin’s build system, as the admin interface is developed in React’s JSX syntax, with builds generated via WordPress's robust tooling.

### 😍 Useful Links 📌

* [Documentation](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#installation-guide)
* [Support Forum](https://wordpress.org/support/plugin/sa-hosted-checkout-for-woocommerce/)
* [Translations](https://translate.wordpress.org/projects/wp-plugins/sa-hosted-checkout-for-woocommerce/)

### Become a Contributor 👨🏻‍💻

*SA Hosted Checkout for WooCommerce* is an open-source project, and we welcome contributors to be part of our vibrant community! Help us improve the plugin and make it even better.

### 🤝 Support 👀

For help, use the WordPress.org [Support Forum](https://wordpress.org/support/plugin/sa-hosted-checkout-for-woocommerce/). Before starting a new thread, check the [documentation](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#installation-guide) and existing support threads. Describe the issue and include your WordPress, WooCommerce and plugin versions. Do not post API keys or customer payment details.


## Installation

Install a supported version of WooCommerce before activating this plugin. See the prerequisites above.

1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure Stripe API credentials and the webhook signing secret in Stripe Settings. Start in test mode.
4. Choose Hosted Checkout, Embedded payment page, or Embedded popup. Embedded modes also require the publishable key from the same Stripe account and test or live mode as the secret or restricted key.
5. For Embedded Checkout, use the Automatic payment-page option or select a published page containing `[sahcfwc_checkout]`. Save settings to create the automatic page.
6. Optionally enable Checkout branding. Leave any field empty to inherit its Stripe Dashboard setting. Logo overrides apply to Hosted Checkout only and require a public HTTPS PNG, JPEG or SVG image URL.
7. Use HTTPS for production Embedded Checkout and exclude its payment page from page caching. Test products, coupons, shipping, taxes, payment methods and webhook delivery before enabling live payments.

## Checkout setup and limitations

### Hosted page and popup experiences

In Stripe Settings, select one of three free checkout experiences:

- **Hosted Checkout:** redirect to Stripe's payment page. This remains the default for existing installations.
- **Embedded payment page:** display Stripe Checkout on a dedicated page within your website.
- **Embedded popup:** open Stripe Checkout in a popup from cart buttons. Classic WooCommerce checkout and existing-order payment links first open the dedicated payment page, which then opens the popup.

Embedded modes need a valid publishable key from the same Stripe account and mode as the configured secret or restricted key. Use HTTPS in production. Some payment methods still redirect customers to their bank or payment provider for authorization. Apple Pay and Google Pay also depend on Stripe payment-method domain registration, account eligibility, browser and device.

Select Automatic to create a dedicated payment page when settings are saved. To use an existing page, publish it with `[sahcfwc_checkout]` and select it in Stripe Settings. It must not be password protected or assigned as WooCommerce's Cart, Checkout or My Account page. The shortcode displays payment only when opened through a valid order-payment link; it does not create an order by itself.

Keep the payment page and its query-string URLs out of page caches. Popup mode also uses this page for payment-provider returns. Closing a popup does not cancel the order: reopening it reuses the existing payable session. Orders remain subject to the same saved totals, stock checks and verified payment completion in every mode.

After an administrator changes the checkout mode, an existing popup session may continue on the dedicated payment page instead of being replaced.

If payment has already been verified for the shopper's unchanged cart, reopening checkout returns the existing order receipt. If the paid cart cannot be matched safely, checkout stops with a review notice instead of starting another payment. After the receipt clears the cart, customers can start another purchase.

### Free checkout branding

Enable Checkout branding in Stripe Settings to override the checkout display name, background and button colors, font and corner shape. Leave fields empty or disable the override to inherit Stripe Dashboard branding.

Logo overrides apply to Hosted Checkout only. Stripe's Embedded Checkout rejects per-session logo and icon overrides, so the plugin omits them in Embedded page and popup mode. Your saved Hosted logo remains available when you switch back to Hosted Checkout.

A Hosted logo must use a publicly accessible HTTPS URL whose path ends in `.png`, `.jpg`, `.jpeg`, `.jfif`, `.pjpeg`, `.pjp` or `.svg`. Query strings are allowed. WebP, localhost, private-network and password-protected images are not supported. Stripe retrieves the image; WordPress does not fetch it.

Changes apply to new checkout sessions, not sessions already open. They do not change Stripe account settings, receipts or invoices. Stripe controls its protected payment interface; arbitrary WordPress CSS can style the surrounding page but not the inside of Stripe's payment frame.

### Payment methods and language are free

In Payment Methods, the default mode keeps card-only payments. A custom selection can include Cards and eligible local methods such as Link, iDEAL | Wero, Klarna, SEPA Direct Debit, ACH Direct Debit and USD bank transfer. Card brands belong to the Cards choice; USD bank transfer is different from ACH Direct Debit. Link requires Cards to be selected, and Canadian pre-authorized debit requires a Personal or Business mandate type.

Enable the selected methods in Stripe. Availability depends on the Stripe account, business and customer countries, currency, order amount and other eligibility rules. Selecting a method does not guarantee that Stripe will display it. Consult [Stripe's payment-method support](https://docs.stripe.com/payments/payment-methods/payment-method-support).

In Checkout Settings, use automatic browser-language detection or choose a supported Stripe language. This affects Stripe Checkout, not the WordPress site language or product names.

### WooCommerce coupons, fees, shipping and taxes

Cart coupons are supported in the free version. Enable coupon codes in WooCommerce > Settings > General if needed; the plugin does not change this setting. Apply coupons in the WooCommerce cart before opening Stripe Checkout. The plugin does not add Stripe coupon-code entry. Coupon eligibility and usage restrictions still apply: once-per-customer coupons require login, and guests using higher per-customer limits need a valid billing email before applying the coupon or must log in.

Checkout starts with the saved WooCommerce order total. Positive fees and taxes appear as separate lines, with shipping shown as a separate charge. Changing an address in Stripe Checkout does not recalculate WooCommerce shipping or taxes. Set up and verify the cart's shipping destination and totals before opening checkout. Embedded display does not add live recalculation of WooCommerce prices or charges.

### Optional quantity changes

The free "Allow quantity changes in Stripe Checkout" option is disabled by default and requires a working Stripe webhook. Eligible simple products and variations can be adjusted within the allowed limits. Sold-individually products and lines needing split prices stay fixed. Carts with fractional quantities keep fixed quantities. Customers cannot remove a product by setting its quantity to zero, and discounts can impose a higher minimum.

WooCommerce discount amounts, shipping charges, fees and taxes stay fixed when quantities change in Stripe Checkout. Coupon rules and other cart pricing rules are not recalculated. Enable only if these fixed charges are appropriate for your store. An existing percentage coupon's calculated discount amount, for example, does not grow when the customer adds more units in Stripe.

The maximum defaults to 10 per eligible product and accepts values from 1 to 999999. A higher starting cart quantity is preserved, while available stock may lower the allowed maximum. For stock-managed products, the allowed maximum is reserved while checkout is open, not just the starting quantity. Set a cap suitable for your inventory.

Verified final quantities and totals are synced to WooCommerce after checkout is submitted. Delayed payments remain on hold until Stripe confirms payment. An unverifiable final quantity or unavailable stock reservation requires order review before fulfillment.

### Webhooks and checkout compatibility

Copy the webhook endpoint URL from Stripe Settings into the matching Stripe test or live environment. Subscribe to `checkout.session.completed`, `checkout.session.async_payment_succeeded` and `checkout.session.async_payment_failed`, then save that endpoint's signing secret in the plugin. The listener also accepts `payment_intent.succeeded` and `charge.succeeded`; subscribing to all Stripe events is unnecessary.

Use a publicly reachable HTTPS endpoint and verify successful delivery in Stripe. A working webhook is required for delayed-payment methods and quantity adjustment and keeps orders in sync even if customers do not return. Submission is not proof of successful payment; see [Stripe's post-payment event guidance](https://docs.stripe.com/payments/existing-customers?platform=web&ui=stripe-hosted).

The selected checkout experience is supported from WooCommerce Cart and Mini-Cart block buttons and ordinary checkout-page visits. For carts requiring payment, both classic Checkout shortcode pages and WooCommerce Checkout block pages show the Stripe launcher instead of the standard WooCommerce form. This is a form replacement, not a payment-method option inside the Checkout block. The separate classic gateway supports existing-order payment flows. JavaScript is required. Empty carts, zero-total orders, order-payment and receipt endpoints, and API requests are excluded from the ordinary-page takeover. Custom buttons, themes and checkout extensions still require store-specific testing.

In popup mode, supported cart-button clicks are handled before WooCommerce's navigation handler. WooCommerce's `cart_proceed_to_checkout` event is not emitted for those intercepted clicks. Extensions relying on that event or other button-click handlers require separate compatibility testing.

## External services

The plugin uses Stripe Checkout and requires a Stripe account. Its server requests verify account configuration and create, retrieve or expire checkout sessions and verify payment status. Requests contain order references and verification metadata, product information and public image URLs, quantities, amounts, discounts, fees, shipping, taxes, currency, return URLs and billing email when available. Enabled branding overrides also send the selected appearance values; Hosted Checkout can also receive a public logo URL. Stripe retrieves the logo; WordPress does not download it through this feature. USD bank transfers require a Stripe customer associated with the order.

When checkout is enabled or an existing payment page is opened, the storefront loads Stripe.js directly from `js.stripe.com`. Customers' browsers connect to Stripe to provide its payment interface and payment security services. Payment details are entered in Stripe's hosted page or Stripe-controlled embedded interface. Customer contact and address information may also be collected according to checkout settings. Verified payment status, customer details and eligible final quantity changes are returned to update the WooCommerce order.

See the [Stripe Privacy Policy](https://stripe.com/privacy) and [Stripe Services Agreement](https://stripe.com/legal/ssa).

## Development Environment Setup

### PHP Setup

The release includes the scoped Stripe PHP SDK and Composer autoload files; rebuilding JavaScript does not require reinstalling PHP dependencies. The SDK is pinned to `stripe/stripe-php` 18.0.0 and defaults to Stripe API `2025-09-30.clover`. Do not upgrade the API version as part of an asset-only build.

The legacy dependency-maintenance command is:

```bash
composer run setup-development
```

It uses Mozart to prefix the Stripe namespace. Current Composer security checks can block its legacy development dependencies; do not disable those checks to force installation. Review dependency maintenance separately from runtime packaging.

Check for WP Coding Standards errors with:

```bash
composer run wpcs
```

Fix any fixable WP Coding Standards errors by running:

```bash
composer run wpcs:fix
```

### Build Setup

Run the following NPM commands from the `node-dev` directory. Admin JavaScript and SCSS source is in `node-dev/free-app`, shared settings components are in `node-dev/shared`, and storefront JavaScript and CSS are in `node-dev/frontend/checkout`. `node-dev/webpack.config.js` builds the admin interface into `assets/backend` and the storefront controller into `assets/frontend/checkout`. WordPress provides the external WordPress and React packages at runtime. Stripe.js is loaded directly from Stripe and must not be copied into the bundle.

Install NPM dependencies with:

```bash
npm install
```

Watch for changes and automatically rebuild CSS and JS assets:

```bash
npm run start
```

Format component files:

```bash
npm run format
```

Update the plugin translation files using WP-CLI (requires terminal access to WP-CLI):

```bash
npm run translate
```

Generate production assets without running the legacy ZIP workflow:

```bash
npx cross-env NODE_ENV=production CREATE_ZIP=no npm run build
```

The `build:development` and `build:production` scripts rebuild Composer dependencies and the scoped Stripe SDK; they are dependency-maintenance tasks, not the JavaScript asset build. They are not required to package the bundled runtime.

For releases, package only the plugin runtime files, bundled licenses, readable admin/shared/storefront source and build configuration. Include all three source directories so the distributed assets can be rebuilt. Exclude local environments, credentials, test artifacts, `.git` and `node_modules`. The legacy `build:zip` task replaces shared backup folders and is not the release packaging workflow.

## Changelog

[See all version changelogs](CHANGELOG.md)
