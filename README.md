# SA Hosted Checkout for WooCommerce

## Description

This plugin is the most extensive integration between WooCommerce and Stripe Checkout, developed to improve your online store's revenues by giving more confidence to the customers to checkout on your store. It effortlessly connects your WooCommerce store with Stripe Checkout bypassing the WooCommerce checkout completely. You can customize the Stripe Checkout displayed to your customers by using the settings available in the plugin. All possible options are available the premium version along with some additional features to review the Stripe checkout sessions created on your website. 

## Version 1.0.4

**Prerequisites**

- WooCommerce Latest Version

### 🎉 Free Version - Features
* Stripe authentication is supported using either a *Standard API Key* or a *Restricted API Key*.
* Bypass your WooCommerce checkout completely and let your customers pay with confidence on Stripe hosted checkout
* Enable or Disable Phone Number field on Stripe Checkout
* Add or remove Terms & Services checkbox from Stripe Checkout
* Add a Cancellation URL to redirect the customer to, when the customer cancels Strpe Checkout

### 🌟 Premium Version - Features: 🎯
* All features in the free version and the following
* Support WooCommerce Coupon codes and Discounts out of the box
* Support any or all payment methods from: 
* Change the language of Stripe checkout displayed to your customers
* Add custom fields to Stripe Checkout which can be a number field, a text field and a dropdown field
* Add custom text to be displayed as terms and services text
* Add custom text to be displayed before and after submit button of Stripe Checkout
* Add custom text to be displayed after shipping address fields of Stripe Checkout
* Let your customers adjust the quantity of products on Stripe Checkout
* Ability to view all the Stripe Checkout sessions created on the web store with their status of completion and customer information
* Automatically enable test mode of Stripe payment for administrators of the website

Get the [premium version](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/) now 🚀.

### 👉 Video Tutorial 👈
https://www.youtube.com/watch?v=2ktSTBzG95c


### Documentation 📚
Discover how to make the most of Otter Blocks with our detailed and user-friendly [documentation](https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#installation-guide).

### 🌐 Translation Ready 🤩
*SA Hosted Checkout for WooCommerce* is compatible with Loco Translate, WPML, Polylang, TranslatePress, Weglot, and more. To contribute, add a new language via translate.wordpress.org.

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
We offers full support on the WordPress.org [Forum](https://wordpress.org/support/plugin/sa-hosted-checkout-for-woocommerce/). Before starting a new thread, please check available [documentation](w.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#:~:text=Guide%20Video-,Documents,-Installation%20guide) and other support threads. Leave a clear and concise description of your issue, and we will respond as soon as possible.


## Installation

Before installation please make sure you have the latest WooCommerce plugin installed.

1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.

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

Generate the final build of assets:
```bash
npm run build
```

Generate a development version build:
```bash
npm run build:development
```

Generate a production version build:
```bash
npm run build:production
```

Create a plugin zip file (located in sahcfwc-backups under the wp installation folder, with separate folders for development and production versions):
```bash
npm run build:zip
```

## Changelog

[See all version changelogs](CHANGELOG.md)