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
