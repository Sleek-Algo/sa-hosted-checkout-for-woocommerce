(function ($, w) {
    'use strict';

    $(document).ready(function () {
        
        /**
         * Custom Function to get Stripe Checkout URLs by Ajax
        */
       function sahcfwcGetStripCheckoutUrl(){
           
           const  wcCartUrl   = sahcfwc_frontend_localized_data?.wc_cart_url;
           const  checkoutURL = sahcfwc_frontend_localized_data?.wc_checkout_url;
            $('a[href="'+checkoutURL+'"], .checkout-button, .wp-block-woocommerce-mini-cart-checkout-button-block, .checkout' ).each(function() {
                $(this).addClass('sahcfwc-disabled-checkout-btn');
            });
            const data = { action: 'stripe_checkout_ajax_handler' }; 
            setTimeout(function() {
                $.ajax({
                    type: "POST",
                    data: data,
                    url: sahcfwc_frontend_localized_data?.ajax?.url,
                    data: $.extend({
                        action: sahcfwc_frontend_localized_data?.ajax?.action,
                        security: sahcfwc_frontend_localized_data?.ajax?.security,
                    },{}),
                    beforeSend: function( xhr ) {
                        xhr.setRequestHeader( 'X-WP-Nonce', sahcfwc_frontend_localized_data?.ajax?.security);
                        $(this).addClass('sahcfwc-disabled-checkout-btn-loading');
                    },
                    success: function (response) {
                        
                        $(this).removeClass('sahcfwc-disabled-checkout-btn-loading');
                        
                        if( response?.status === "success" ){
                            window.location.replace(response?.stripe_checkout_session_url)
                            $('a[href="'+checkoutURL+'"], .checkout-button, .wp-block-woocommerce-mini-cart-checkout-button-block, .checkout' ).each(function() {
                                $(this).removeClass('sahcfwc-disabled-checkout-btn');
                                $(this).attr('href', response?.stripe_checkout_session_url);
                            });
                        }else if ( response?.status === "failed" ){
                            const currentUrl = window?.location.href;
                            let error_html = `
                            <div class="woocommerce">
                                <ul class="woocommerce-error" role="alert">
                                    <li>
                                        `+response?.message+` 
                                    </li>
                                </ul>
                            </div>`;
                            if (currentUrl === wcCartUrl) {
                                $('.content-area').prepend(error_html);
                                $('.woocommerce-cart-form').siblings('.woocommerce-notices-wrapper').html(error_html);
                            }
                            $('a[href="'+checkoutURL+'"], .checkout-button, .wp-block-woocommerce-mini-cart-checkout-button-block, .checkout').each(function() {
                                $(this).addClass('sahcfwc-disabled-checkout-btn');
                                $(this).attr('href', 'javascript:;');
                            });
                        }
                    }
                }); 

            }, 1000);
        }
        
        // For legacy shortcode cart
        $(document.body).on('updated_cart_totals', function(event) {
            $('a[href="'+sahcfwc_frontend_localized_data?.wc_checkout_url+'"], .checkout-button, .wp-block-woocommerce-mini-cart-checkout-button-block, .checkout, .wc-block-cart__submit-button' ).each(function(){
                $(this).attr('href', 'javascript:;');
            });
        });

        // Use event delegation for the click event
        $(document.body).on('click', 'a[href="'+sahcfwc_frontend_localized_data?.wc_checkout_url+'"], .checkout-button, .wp-block-woocommerce-mini-cart-checkout-button-block, .checkout, .wc-block-cart__submit-button', function(event) {
            event.preventDefault();
            sahcfwcGetStripCheckoutUrl();
        });
            
        // For WooCommerce AJAX fragment updates
        $(document.body).on('wc_fragment_refresh', function(event) {
            sahcfwcGetStripCheckoutUrl();
        });

        // For WooCommerce AJAX fragment updates on Item remove
        $(document.body).on('removed_from_cart', function(event) {
            sahcfwcGetStripCheckoutUrl();
        });
        
        // For WC Cart Block on Cart Page
        if (typeof wp !== 'undefined' && typeof wp.data !== 'undefined' && sahcfwc_frontend_localized_data?.is_wc_cart_page === 'yes') {
            wp.data.subscribe(function() {
                const select = wp.data.select('wc/store/cart');
                if (select && select.hasItems) { // Check if select and select.hasItems are defined
                    if (select.hasItems()) {
                        const cart = select.getCartData(); // Adjust the method if getCartData is not available
                        const cartKey = cart ? cart.key : null;
                        if (cartKey && cartKey !== window.previousCartKey) {
                            window.previousCartKey = cartKey;
                            sahcfwcGetStripCheckoutUrl();
                        }
                    }
                }
            });
        }

        // Function to execute when 'drawer-open' class is added
        function onDrawerOpen() {
            sahcfwcGetStripCheckoutUrl();
        }
        
        // Create a MutationObserver to watch for class changes on the body element
        const observer = new MutationObserver(function(mutationsList) {
            for (let mutation of mutationsList) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    const body = mutation.target;
                    if ($(body).hasClass('drawer-open')) {
                        onDrawerOpen();
                    }
                }
            }
        });
        // Start observing the body element for attribute changes
        observer.observe(document.body, { attributes: true });

    });

})(jQuery, window);