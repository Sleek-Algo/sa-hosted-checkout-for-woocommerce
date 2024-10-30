# SA Hosted Checkout for WooCommerce

## Description

Increase conversions by using Stripe Checkout on your WooCommerce website. Let your customers pay with confidence using highly optimized, Stripe hosted checkout. Setup in a few minutes. All configuration options are available.

## Version 1.0.0

**Prerequisites**

- WooCommerce Latest Version

**Features**
- Bypass your WooCommerce checkout completely and let your customers pay with confidence on Stripe hosted checkout.
- Allow adjusting product quantities on Stripe checkout.
- Display terms and services on the Stripe checkout page.
- Add a phone number field on your stripe checkout page.
- Ability to provide custom text messages such as adding text after shipping address fields, after submit button, before submit button and customizing terms and service text.
- Ability to display custom fields on the Stripe checkout page. For instance creating custom dropdown fields, custom text fields, and custom number fields.
- Ability to choose which payment methods are available on Stripe checkout for your customers.
- Ability to view all the Stripe Checkout sessions created on the web store with their status of completion and customer information.
- Supports WooCommerce discounts and Coupons out of the box.
- Add a custom cancellation page.
- Display a custom Thank You page after the customer completes checkout on Stripe.
- Ability to choose the language of the Stripe checkout.
- Enable or disable shipping address on Stripe checkout.
- Toggle between Live and Test modes easily.
- Enable Test mode for admin users only.


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