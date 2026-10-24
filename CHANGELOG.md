## 1.1.1 (2026-10-18)

- Replaced the standard WooCommerce checkout form on ordinary checkout-page visits with the selected Stripe Checkout experience for carts requiring payment, including classic shortcode and Checkout block pages.
- Added automatic checkout launch with clear retry and return-to-cart controls, while preserving empty-cart, zero-total, existing-order payment and receipt flows.
- Prevented hidden or prerendered pages from starting a checkout session and required POST requests for checkout creation.
- Updated the plugin description, free and premium feature lists, installation instructions and FAQs, including webhook setup and checkout calculation limits.

## 1.1.0 (2026-10-17)

- Added free Embedded Checkout on a dedicated store page or in a popup, while preserving Hosted Checkout as the default for existing installations.
- Added free checkout branding controls for the display name, background and button colors, font and corner shape, plus a Hosted Checkout logo override, with Stripe Dashboard inheritance for empty fields.
- Added automatic payment-page creation and support for a custom published page containing `[sahcfwc_checkout]`.
- Added mode-matched publishable-key validation and private, non-cached access to embedded payment sessions.
- Reused saved WooCommerce order totals and verified payment handling across all three checkout experiences, including existing-order payment links and payment-provider returns.
- Added popup close/reopen handling and retry safeguards without creating parallel payment sessions for an order.
- Fixed WooCommerce Cart block checkout buttons remaining in a loading state after closing Embedded Checkout.
- Kept Stripe quantity and payment-authentication dialogs accessible above the checkout popup.
- Added receipt recovery when an unchanged cart's payment has already been verified, without rechecking consumed stock or coupon uses. An unverifiable paid cart is blocked from starting another payment.
- Fixed unnecessary payment-processing conflicts between stores using separate databases on the same database server.
- Updated the bundled Stripe PHP SDK to 18.0.0 with Stripe API version 2025-09-30.clover.
- Updated setup instructions, free-feature descriptions, Stripe.js service disclosure and readable storefront source/build information.

## 1.0.7 ( October 8, 2026 )

- Made Stripe payment-method selection and checkout language selection available in the free version, while preserving the default card-only and automatic-language behavior.
- Added validation for method currency eligibility, Link's Cards requirement and Canadian debit mandate settings, with webhook requirements for delayed payments.
- Added optional quantity changes for eligible products, configurable maximum quantities and stock reservations while checkout is open.
- Added verification and WooCommerce order synchronization for final Stripe quantities and totals, including delayed-payment handling. Existing discounts, fees, shipping and taxes remain fixed when quantities change.
- Reloaded persisted order state under the payment lock to prevent overlapping callbacks from repeating completion emails or reopening refunded orders.
- Restored coupon usage after failed delayed payments on older supported WooCommerce versions, without double-counting retries.
- Declared compatibility with WooCommerce High-Performance Order Storage after testing both order-storage modes.
- Improved settings save validation and error recovery; an empty optional cancellation URL no longer prevents saving Checkout Settings.
- Updated free and premium feature descriptions and documented payment-method, webhook and quantity-adjustment limits.

## 1.0.6 ( October 7, 2026 )

- Added WooCommerce cart coupon support to the free version while preserving the store's coupon setting and eligibility rules.
- Fixed payment totals to include saved WooCommerce order discounts, fees, shipping and taxes, with separate lines for additional charges.
- Improved checkout validation, rounding and retry handling to prevent mismatched totals and duplicate checkout sessions.
- Improved payment confirmation and webhook validation, including protection against repeated order completion.
- Improved cancellation and expired-session handling while preserving the customer's cart.
- Replaced unclear setup and payment notices with professional messages, including guidance for missing Stripe keys.
- Corrected the settings screen version display and updated compatibility requirements to WordPress 6.3, WooCommerce 8.6 and PHP 7.4 or later.

## 1.0.5

- Updated WordPress and PHP compatibility metadata and the release version.

## 1.0.4 ( February 12, 2026 )

- Bug Fix: The stripe checkout slow redirection issue has been fixed.

## 1.0.3 ( July 22, 2025 )

- Bug Fix: The webhook listener PHP fatal error has been fixed.

## 1.0.2 ( July 04, 2025 )

- Bug Fix: The stripe settings 'integration mode' UI switching issue has been fixed.
- New Feature: The Stripe 'Restricted API Key' based authentication support has been added.

## 1.0.1 ( July 26, 2024 )

- Fixed the issue with virtual products. Users can now add virtual products as well.
- Fixed the empty cart issue. If the user returns to the site from Stripe, the cart is no longer cleared.
- Updated the temporary order changes feature. When the user enables 'Delete Temporary Order,' it only deletes the temporary order without emptying the cart.

## 1.0.0 ( July 12, 2024 )

- Initial release
