(function ($, w) {
    'use strict';

    $(document).ready(function () {
        const config = w.sahcfwc_frontend_localized_data;
        if (!config || !config.ajax || !config.wc_checkout_url) {
            return;
        }
        const selector = 'a[href="' + config.wc_checkout_url + '"], a.checkout-button, a.checkout, .wp-block-woocommerce-mini-cart-checkout-button-block, .wc-block-cart__submit-button';
        let pendingRequest = null;
        let requesting = false;

        function setBusy(busy) {
            $(selector)
                .toggleClass('sahcfwc-disabled-checkout-btn', busy)
                .toggleClass('sahcfwc-disabled-checkout-btn-loading', busy)
                .attr('aria-disabled', busy ? 'true' : 'false');
        }

        function showError(message) {
            $('.sahcfwc-checkout-error').remove();
            const notice = $('<ul>', {
                'class': 'woocommerce-error sahcfwc-checkout-error',
                'role': 'alert',
                'tabindex': '-1'
            }).append($('<li>').text(message || config.checkout_error || 'Unable to start checkout. Please try again.'));
            let target = $('.woocommerce-notices-wrapper').first();
            if (!target.length) {
                target = $('main, .content-area, .site-main').first();
            }
            if (!target.length) {
                target = $('body');
            }
            target.prepend(notice);
            notice.trigger('focus');
        }

        // A cart update or opening the drawer is not consent to start checkout.
        $(document.body).on('click.sahcfwc', selector, function (event) {
            event.preventDefault();
            if (requesting) {
                return;
            }
            requesting = true;
            setBusy(true);
            $('.sahcfwc-checkout-error').remove();
            pendingRequest = $.ajax({
                type: 'POST',
                dataType: 'json',
                url: config.ajax.url,
                data: {
                    action: config.ajax.action,
                    security: config.ajax.security
                },
                success: function (response) {
                    if (response && response.status === 'success' && response.stripe_checkout_session_url) {
                        let destination;
                        try {
                            destination = new URL(response.stripe_checkout_session_url, w.location.href);
                        } catch (error) {
                            showError();
                            return;
                        }
                        // Stripe can return a merchant's custom HTTPS Checkout domain.
                        // Trust the server-generated URL, but never execute a script URL.
                        if (destination.origin !== w.location.origin &&
                            destination.protocol !== 'https:') {
                            showError();
                            return;
                        }
                        w.location.assign(destination.href);
                    } else {
                        showError(response && response.message);
                    }
                },
                error: function (xhr, status) {
                    if (status !== 'abort') {
                        showError();
                    }
                },
                complete: function () {
                    pendingRequest = null;
                    requesting = false;
                    setBusy(false);
                }
            });
        });

        // An in-flight request belongs to the old cart. Keep the next click retryable.
        $(document.body).on('updated_cart_totals.sahcfwc removed_from_cart.sahcfwc wc_fragment_refresh.sahcfwc', function () {
            if (pendingRequest) {
                pendingRequest.abort();
            }
            pendingRequest = null;
            requesting = false;
            setBusy(false);
        });
    });
})(jQuery, window);
