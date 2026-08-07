<?php

namespace Bydn\SoloSearchWoo\Frontend;

use Bydn\SoloSearchWoo\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Prints the SoloSearch widget <script> tag on the storefront - mirrors
 * suite-magento's Block/Widget.php + widget.phtml. Hooked on wp_footer
 * (WordPress's equivalent of Magento's before.body.end) rather than
 * wp_head, matching the same placement choice.
 */
class WidgetEmbed {

    /**
     * @return void
     */
    public static function render() {
        // The feed only carries products, so the widget only makes sense on
        // WooCommerce pages (shop archive, product taxonomies, single
        // product) - is_woocommerce() deliberately excludes cart/checkout/
        // account (plain pages, not template-driven) and everything else
        // (blog posts, regular pages), so WordPress's own native search
        // stays untouched there instead of being hijacked into a
        // products-only search. See suite issue for "content types" -
        // making the feed support more than products so this restriction
        // can eventually be lifted.
        if ( ! is_woocommerce() ) {
            return;
        }

        $config = new Config();

        // Same guard as suite-magento's Block\Widget::_toHtml(): a
        // half-configured engine must not emit a broken script tag.
        if ( ! $config->shouldRenderWidget()
            || '' === $config->getSearchEngineId()
            || '' === $config->getScriptUrl()
        ) {
            return;
        }

        $attributes = array(
            'data-engine' => $config->getSearchEngineId(),
        );

        // Optional attributes are omitted entirely when empty (not sent as
        // empty strings), so widget.js applies its own defaults instead of
        // us duplicating them here - same reasoning as suite-magento's
        // widget.phtml.
        if ( '' !== $config->getInputSelector() ) {
            $attributes['data-input'] = $config->getInputSelector();
        }
        if ( '' !== $config->getLocale() ) {
            $attributes['data-locale'] = $config->getLocale();
        }
        if ( '' !== $config->getTemplateSetId() ) {
            $attributes['data-template'] = $config->getTemplateSetId();
        }

        $attribute_string = '';
        foreach ( $attributes as $name => $value ) {
            $attribute_string .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
        }

        printf(
            '<script src="%s"%s defer></script>' . "\n",
            esc_url( $config->getScriptUrl() ),
            $attribute_string // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built entirely from esc_attr() above.
        );
    }
}
