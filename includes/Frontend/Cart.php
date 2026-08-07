<?php

namespace Bydn\SoloSearchWoo\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Prints the inline listener that connects the widget's Add to cart button
 * to WooCommerce's real cart - mirrors suite-magento's widget.phtml add-to-
 * cart script, adapted to WooCommerce's native AJAX endpoint
 * (WC_AJAX::get_endpoint('add_to_cart')) instead of Magento's
 * checkout/cart/add + form_key.
 */
class Cart {

    /**
     * Called from WidgetEmbed::render() right after the widget <script> tag -
     * same reasoning as suite-magento keeping both in a single widget.phtml:
     * they share the exact same "should the widget even be here" guard, so
     * there's no separate guard to duplicate here.
     *
     * @return void
     */
    public static function render() {
        $ajax_url = \WC_AJAX::get_endpoint( 'add_to_cart' );
        ?>
        <script>
        (function () {
            var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;

            // Briefly swaps the clicked button's own label to "Added" as
            // feedback, then restores whatever text it had before. Finds the
            // button the same way cart.js's own resetAtcLoading() does
            // ([data-ss-atc][data-ss-product-id]) - no widget.js change
            // needed, we already have productId in scope for the AJAX call.
            function showAddedFeedback( productId ) {
                var btn = document.querySelector( '[data-ss-atc][data-ss-product-id="' + productId + '"]' );
                if ( ! btn ) {
                    return;
                }
                var originalText = btn.textContent;
                btn.textContent = 'Added';
                setTimeout( function () {
                    btn.textContent = originalText;
                }, 2000 );
            }

            // Fired by widget.js when the user clicks a [data-ss-atc] button
            // on a search result card. See resources/js/widget/cart.js in
            // suite-search for the dispatch side (event.detail has
            // id/title/price/sale_price - only id is needed here).
            document.addEventListener( 'ss:atc', function ( event ) {
                var detail = event.detail || {};
                var productId = detail.id;

                if ( ! productId ) {
                    return;
                }

                var xhr = new XMLHttpRequest();
                xhr.open( 'POST', ajaxUrl, true );
                xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
                xhr.setRequestHeader( 'X-Requested-With', 'XMLHttpRequest' );

                xhr.onreadystatechange = function () {
                    if ( xhr.readyState !== XMLHttpRequest.DONE ) {
                        return;
                    }

                    // WC_AJAX::add_to_cart() replies with HTTP 200 even on a
                    // logical failure (e.g. out of stock) - the failure is
                    // only visible in the JSON body as { error: true, ... } -
                    // so a 2xx/3xx status alone isn't enough to call it a
                    // success.
                    var success = xhr.status >= 200 && xhr.status < 400;
                    var response = null;

                    if ( success ) {
                        try {
                            response = JSON.parse( xhr.responseText );
                            success = ! response.error;
                        } catch ( e ) {
                            success = false;
                        }
                    }

                    if ( success ) {
                        showAddedFeedback( productId );

                        // Refreshes any fragment-based mini-cart on the page
                        // (classic/non-block themes) - the same way
                        // WooCommerce's own "Add to cart" buttons do. See
                        // assets/js/frontend/add-to-cart.js, onAddedToCart().
                        if ( response && response.fragments && typeof jQuery !== 'undefined' ) {
                            jQuery( document.body ).trigger( 'added_to_cart', [ response.fragments, response.cart_hash ] );
                        }

                        // Refreshes the block-based Mini Cart (Cart/Checkout
                        // blocks), which reads from the wc/store/cart data
                        // store (registered by WooCommerce Blocks, see
                        // wc-blocks-data.js) instead of reacting to the
                        // jQuery event above. invalidateResolutionForStoreSelector
                        // is a real, documented @wordpress/data core API
                        // (wp-includes/js/dist/data.js) for exactly this -
                        // telling a store its cached getCartData() result is
                        // stale, so any block subscribed to it (via
                        // wp.data.subscribe(), same as mini-cart-frontend.js
                        // does) re-fetches and re-renders. A previous attempt
                        // using a wc-blocks_added_to_cart CustomEvent (deduced
                        // from reading minified JS without source maps) did
                        // not work when tested in the browser - this is
                        // grounded in confirmed-present core API instead.
                        if ( window.wp && window.wp.data && window.wp.data.dispatch ) {
                            var cartStore = window.wp.data.dispatch( 'wc/store/cart' );
                            if ( cartStore && cartStore.invalidateResolutionForStoreSelector ) {
                                cartStore.invalidateResolutionForStoreSelector( 'getCartData' );
                            }
                        }
                    }

                    // widget.js clears the button's loading state (and
                    // re-enables it) on this event regardless of success, so
                    // a failed add never leaves the button stuck - see
                    // cart.js's setupAtcDoneListener() in suite-search.
                    document.dispatchEvent( new CustomEvent( 'ss:atc:done', {
                        detail: { id: productId, success: success }
                    } ) );
                };

                var params = new URLSearchParams();
                params.append( 'product_id', productId );
                params.append( 'quantity', '1' );
                xhr.send( params.toString() );
            } );
        })();
        </script>
        <?php
    }
}
