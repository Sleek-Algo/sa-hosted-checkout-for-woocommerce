# SA Hosted Checkout for WooCommerce

## Description

SA Hosted Checkout for WooCommerce connects your store to Stripe's hosted payment page. Customers review their cart in WooCommerce and pay through Stripe Checkout. The plugin carries the saved order's discounts, fees, shipping and taxes into the payment total and verifies Stripe payment confirmations before marking an order paid.

The free version includes WooCommerce cart coupons, payment-method selection, checkout language selection and optional quantity changes for eligible products. Premium adds checkout custom fields and text, a session-management screen and administrator-only test mode.

## Version 1.0.7

Version 1.0.7 makes payment-method selection, checkout language and optional quantity changes available in the free version. See [CHANGELOG.md](CHANGELOG.md) for the complete release notes.

**Prerequisites**

- WordPress 6.3 or later
- WooCommerce 8.6 or later
- PHP 7.4 or later, with the curl, json and mbstring extensions

### 🎉 Free Version - Features

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

Payment-method availability and quantity-adjustment limits are explained below.

### 🌟 Premium Version - Features: 🎯

* All features in the free version and the following
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
4. Select checkout preferences and test your products, coupons, shipping, taxes and payment methods before enabling live payments.

## Checkout setup and limitations

### Payment methods and language are free

In Payment Methods, the default mode keeps card-only payments. A custom selection can include Cards and eligible local methods such as Link, iDEAL | Wero, Klarna, SEPA Direct Debit, ACH Direct Debit and USD bank transfer. Card brands belong to the Cards choice; USD bank transfer is different from ACH Direct Debit. Link requires Cards to be selected, and Canadian pre-authorized debit requires a Personal or Business mandate type.

Enable the selected methods in Stripe. Availability depends on the Stripe account, business and customer countries, currency, order amount and other eligibility rules. Selecting a method does not guarantee that Stripe will display it. Consult [Stripe's payment-method support](https://docs.stripe.com/payments/payment-methods/payment-method-support).

In Checkout Settings, use automatic browser-language detection or choose a supported Stripe language. This affects Stripe Checkout, not the WordPress site language or product names.

### WooCommerce coupons, fees, shipping and taxes

Cart coupons are supported in the free version. Enable coupon codes in WooCommerce > Settings > General if needed; the plugin does not change this setting. Apply coupons in the WooCommerce cart before opening Stripe Checkout. The plugin does not add Stripe coupon-code entry. Coupon eligibility and usage restrictions still apply: once-per-customer coupons require login, and guests using higher per-customer limits need a valid billing email before applying the coupon or must log in.

Checkout starts with the saved WooCommerce order total. Positive fees and taxes appear as separate lines, with shipping shown as a separate charge. Changing an address in Stripe Checkout does not recalculate WooCommerce shipping or taxes. Set up and verify the cart's shipping destination and totals before redirecting the customer.

### Optional quantity changes

The free "Allow quantity changes in Stripe Checkout" option is disabled by default and requires a working Stripe webhook. Eligible simple products and variations can be adjusted within the allowed limits. Sold-individually products and lines needing split prices stay fixed. Carts with fractional quantities keep fixed quantities. Customers cannot remove a product by setting its quantity to zero, and discounts can impose a higher minimum.

WooCommerce discount amounts, shipping charges, fees and taxes stay fixed when quantities change in Stripe Checkout. Coupon rules and other cart pricing rules are not recalculated. Enable only if these fixed charges are appropriate for your store. An existing percentage coupon's calculated discount amount, for example, does not grow when the customer adds more units in Stripe.

The maximum defaults to 10 per eligible product and accepts values from 1 to 999999. A higher starting cart quantity is preserved, while available stock may lower the allowed maximum. For stock-managed products, the allowed maximum is reserved while checkout is open, not just the starting quantity. Set a cap suitable for your inventory.

Verified final quantities and totals are synced to WooCommerce after checkout is submitted. Delayed payments remain on hold until Stripe confirms payment. An unverifiable final quantity or unavailable stock reservation requires order review before fulfillment.

### Webhooks and checkout compatibility

Copy the webhook endpoint URL from Stripe Settings into the matching Stripe test or live environment. Subscribe to `checkout.session.completed`, `checkout.session.async_payment_succeeded` and `checkout.session.async_payment_failed`, then save that endpoint's signing secret in the plugin. The listener also accepts `payment_intent.succeeded` and `charge.succeeded`; subscribing to all Stripe events is unnecessary.

Use a publicly reachable HTTPS endpoint and verify successful delivery in Stripe. A working webhook is required for delayed-payment methods and quantity adjustment and keeps orders in sync even if customers do not return. Submission is not proof of successful payment; see [Stripe's post-payment event guidance](https://docs.stripe.com/payments/existing-customers?platform=web&ui=stripe-hosted).

The plugin supports hosted redirection from WooCommerce Cart and Mini-Cart block buttons, and the classic WooCommerce checkout. Direct payment-method integration with the WooCommerce Checkout block is not supported. For the native checkout gateway, use the classic Checkout shortcode. Custom buttons, themes and checkout extensions still require store-specific testing. Orders with no payment due use WooCommerce checkout.

## Development Environment Setup

### PHP Setup

Install PHP dependencies by running:
```bash
composer run setup-development
```

Check for WP Coding Standards errors with:
```bash
composer run wpcs
```

Fix any fixable WP Coding Standards errors by running:
```bash
composer run wpcs:fix
```

### Build Setup

Run the following NPM commands from the `node-dev` directory. Readable JavaScript and SCSS source is included in `node-dev/free-app`; `node-dev/webpack.config.js` defines the build into `assets/backend`. WordPress provides the external WordPress and React packages at runtime.

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

The `build:development` and `build:production` scripts rebuild Composer dependencies and the scoped Stripe SDK; they are dependency-maintenance tasks, not the admin asset build. They are not required to package the tested runtime.

For releases, package only the plugin runtime files, bundled licenses, readable admin source and build configuration. Exclude local environments, credentials, test artifacts, `.git` and `node_modules`. The legacy `build:zip` task replaces shared backup folders and is not the release packaging workflow.

## Changelog

[See all version changelogs](CHANGELOG.md)
