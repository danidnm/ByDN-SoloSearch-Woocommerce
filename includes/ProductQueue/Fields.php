<?php

namespace Bydn\SoloSearchWoo\ProductQueue;

use Bydn\SoloSearchWoo\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Computes every SoloSearch field for a single product - shared by the full
 * feed (FeedGenerator, which wraps this into XML) and the real-time
 * single-product sync (Sync.php, which wraps this into a JSON payload), so
 * a product synced in real time can never disagree in shape or values with
 * what a full feed generation would have produced for it. Mirrors
 * suite-magento's Model/ProductFieldsBuilder.php - the two are siblings,
 * both consuming this class, neither depends on the other.
 */
class Fields {

    /**
     * @param \WC_Product                                            $product
     * @param array<int, array{field: string, source: string, key: string}> $mapping
     * @return array<string, string>
     */
    public static function build( \WC_Product $product, array $mapping ): array {
        $fields = array(
            'id'    => (string) $product->get_id(),
            'sku'   => (string) $product->get_sku(),
            'title' => (string) $product->get_name(),
            'link'  => (string) $product->get_permalink(),
            'image' => self::get_image_url( $product ),
        );

        $prices                 = self::get_price_fields( $product );
        $fields['price']        = $prices['price'];
        $fields['sale_price']   = $prices['sale_price'];

        $fields['availability']         = $product->is_in_stock() ? 'in_stock' : 'out_of_stock';
        $fields['disable_add_to_cart']  = self::has_disabled_add_to_cart( $product ) ? '1' : '0';
        $fields['categories']           = self::get_category_names( $product );
        $fields['currency']             = (string) get_woocommerce_currency();
        $fields['content_type']         = 'product';

        // Field mapping - any row targeting a structural field name is
        // ignored, same as suite-magento's ProductFieldsBuilder::buildFields().
        foreach ( $mapping as $row ) {
            if ( in_array( $row['field'], Config::STRUCTURAL_FIELDS, true ) ) {
                continue;
            }

            $value = 'custom_field' === $row['source']
                ? $product->get_meta( $row['key'] )
                : $product->get_attribute( $row['key'] );

            $fields[ $row['field'] ] = (string) $value;
        }

        return $fields;
    }

    /**
     * Whether a single product currently qualifies for the feed on
     * status/visibility grounds - used outside a full feed generation
     * (ProductQueue\ChangeNotifier) to tell whether a status/visibility
     * change should result in the product being updated in the index or
     * removed from it entirely. Mirrors suite-magento's
     * ProductFieldsBuilder::isVisibleInFeed().
     *
     * @param \WC_Product $product
     * @return bool
     */
    public static function is_visible( \WC_Product $product ): bool {
        return 'publish' === $product->get_status();
    }

    /**
     * Fields for a blog post, sent alongside products when
     * Config::includesPosts() is on - deliberately a much smaller set than
     * build() above, since a post has no price/sku/stock/categories/currency.
     * disable_add_to_cart is forced to '1' so a post never shows a broken
     * add-to-cart button if it ends up rendered in the main results grid
     * instead of a dedicated secondary zone.
     *
     * Only used by FeedGenerator today - real-time sync (ProductQueue\Sync)
     * stays product-only for now, posts only ever get updated by a full feed
     * regeneration.
     *
     * @return array<string, string>
     */
    public static function build_post( \WP_Post $post ): array {
        return array(
            'id'                   => (string) $post->ID,
            'title'                => (string) get_the_title( $post ),
            'link'                 => (string) get_permalink( $post ),
            'image'                => self::get_post_image_url( $post ),
            'content'              => self::get_post_content( $post ),
            'disable_add_to_cart'  => '1',
            'content_type'         => 'post',
        );
    }

    private static function get_post_image_url( \WP_Post $post ): string {
        $url = get_the_post_thumbnail_url( $post, 'full' );

        return $url ? (string) $url : '';
    }

    /**
     * Plain text body, for full-text search relevance - without this, a post
     * would only ever match a query against its title. Shortcodes are
     * stripped rather than executed (strip_shortcodes(), not do_shortcode())
     * to avoid running arbitrary plugin code during feed generation.
     */
    private static function get_post_content( \WP_Post $post ): string {
        $content = strip_shortcodes( $post->post_content );
        $content = wp_strip_all_tags( $content );
        $content = preg_replace( '/\s+/', ' ', $content );

        return trim( (string) $content );
    }

    /**
     * See FeedGenerator's original docblock for the variable-product
     * approximation reasoning (WooCommerce only keeps _price, the "effective"
     * price, synced on the parent - not a regular/sale split).
     *
     * @return array{price: string, sale_price: string}
     */
    private static function get_price_fields( \WC_Product $product ): array {
        if ( ! $product->is_type( 'variable' ) ) {
            $regular = $product->get_regular_price();
            $sale    = $product->get_sale_price();

            // get_sale_price() returns whatever is stored regardless of whether it's still
            // actually lower than get_regular_price() (e.g. the regular price was later lowered
            // to match, or below, an old sale price) - a sale_price that isn't a real discount
            // must not be sent, same guard the variable-product branch below already has via
            // $onSale.
            $onSale = '' !== $regular && '' !== $sale && (float) $sale < (float) $regular;

            return array(
                'price'      => (string) $regular,
                'sale_price' => $onSale ? (string) $sale : '',
            );
        }

        $regular = $product->get_variation_regular_price( 'min' );
        $current = $product->get_variation_price( 'min' );
        $onSale  = '' !== $regular && '' !== $current && (float) $current < (float) $regular;

        return array(
            'price'      => (string) $regular,
            'sale_price' => $onSale ? (string) $current : '',
        );
    }

    private static function get_image_url( \WC_Product $product ): string {
        $imageId = $product->get_image_id();

        if ( ! $imageId ) {
            return '';
        }

        $url = wp_get_attachment_image_url( $imageId, 'full' );

        return $url ? $url : '';
    }

    /**
     * Flat list of category names (not a hierarchical path), joined with
     * ' %% ' - same separator suite-magento uses.
     */
    private static function get_category_names( \WC_Product $product ): string {
        $names = array();

        foreach ( $product->get_category_ids() as $categoryId ) {
            $term = get_term( $categoryId, 'product_cat' );
            if ( $term && ! is_wp_error( $term ) ) {
                $names[] = $term->name;
            }
        }

        return implode( ' %% ', $names );
    }

    /**
     * True when the product can't be added to cart with a plain
     * product_id+qty=1 request and needs its own product page instead - see
     * suite-woocommerce/CLAUDE.md's field mapping table for the reasoning
     * per product type.
     */
    private static function has_disabled_add_to_cart( \WC_Product $product ): bool {
        return ! $product->is_purchasable()
            || $product->is_type( 'variable' )
            || $product->is_type( 'grouped' )
            || $product->is_type( 'external' );
    }
}
