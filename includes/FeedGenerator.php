<?php

namespace Bydn\SoloSearchWoo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generates the SoloSearch product feed - an XML file in the "SoloSearch
 * format" (suite/resources/docs/en/feed-formats/solosearch-format.md),
 * written to wp-content/uploads/solosearch/feed.xml so it's reachable by a
 * plain URL, the same way suite-magento writes a static file under pub/.
 *
 * Both the WP-CLI command (Cli\FeedCommand) and the scheduled cron task
 * (Cron\FeedSchedule) call generate(), so there is a single place that does
 * the real work - mirrors suite-magento's Model/FeedGenerator.php.
 *
 * Loads every published product into memory in one go (no batching) - fine
 * for the catalogue sizes this plugin targets today, but a large catalogue
 * would need a batched/paginated rewrite of this method.
 */
class FeedGenerator {

    /**
     * @return void
     */
    public function generate() {
        $mapping  = ( new Config() )->getFieldMapping();
        $document = new \DOMDocument( '1.0', 'UTF-8' );
        $root     = $document->createElement( 'products' );
        $document->appendChild( $root );

        $products = wc_get_products(
            array(
                'status' => 'publish',
                'limit'  => -1,
                'return' => 'objects',
            )
        );

        foreach ( $products as $product ) {
            $root->appendChild( $this->buildProductNode( $document, $product, $mapping ) );
        }

        $document->formatOutput = true;

        $this->writeFeedFile( $document->saveXML() );

        Logger::info( sprintf( 'Feed generated: %d products.', count( $products ) ) );

        // TODO: notify suite to reindex once SoloSearchClient exists (mirrors
        // suite-magento's SoloSearchClient::requestReindex(), called right
        // after a successful generateForStoreIfEnabled()).
    }

    /**
     * Public URL of the generated feed file - what a tenant pastes into
     * suite as the feed URL.
     */
    public function getFeedUrl(): string {
        $uploadDir = wp_upload_dir();

        return trailingslashit( $uploadDir['baseurl'] ) . 'solosearch/feed.xml';
    }

    /**
     * @param array<int, array{field: string, source: string, key: string}> $mapping
     */
    private function buildProductNode( \DOMDocument $document, \WC_Product $product, array $mapping ): \DOMElement {
        $item = $document->createElement( 'product' );

        // Structural fields - always computed directly from the product,
        // never from field mapping. See the mapping table in CLAUDE.md.
        $this->appendField( $document, $item, 'id', $product->get_id() );
        $this->appendField( $document, $item, 'sku', $product->get_sku() );
        $this->appendField( $document, $item, 'title', $product->get_name() );
        $this->appendField( $document, $item, 'link', $product->get_permalink() );
        $this->appendField( $document, $item, 'image', $this->getImageUrl( $product ) );

        $prices = $this->getPriceFields( $product );
        $this->appendField( $document, $item, 'price', $prices['price'] );
        $this->appendField( $document, $item, 'sale_price', $prices['sale_price'] );

        $this->appendField( $document, $item, 'availability', $product->is_in_stock() ? 'in_stock' : 'out_of_stock' );
        $this->appendField( $document, $item, 'disable_add_to_cart', $this->hasDisabledAddToCart( $product ) ? '1' : '0' );
        $this->appendField( $document, $item, 'categories', $this->getCategoryNames( $product ) );
        $this->appendField( $document, $item, 'currency', get_woocommerce_currency() );

        // Field mapping - any row targeting a structural field name is
        // ignored, same as suite-magento's buildItemNode().
        foreach ( $mapping as $row ) {
            if ( in_array( $row['field'], Config::STRUCTURAL_FIELDS, true ) ) {
                continue;
            }

            $value = 'custom_field' === $row['source']
                ? $product->get_meta( $row['key'] )
                : $product->get_attribute( $row['key'] );

            $this->appendField( $document, $item, $row['field'], $value );
        }

        return $item;
    }

    /**
     * get_regular_price()/get_sale_price() only carry a meaningful value on
     * simple-type products - on a variable product's parent they're empty,
     * since WooCommerce only keeps its "effective" _price meta (the one used
     * for sorting/filtering) synced to the min variation price, not a
     * regular/sale split. For variable products this approximates "price" as
     * the min variation's regular price, and "sale_price" as its current
     * price only when that's actually lower (i.e. the cheapest variation is
     * on sale) - see "Pendiente de decidir" in CLAUDE.md, resolved in favour
     * of this approximation rather than leaving variable products priceless
     * in the feed.
     *
     * @return array{price: string, sale_price: string}
     */
    private function getPriceFields( \WC_Product $product ): array {
        if ( ! $product->is_type( 'variable' ) ) {
            return array(
                'price'      => (string) $product->get_regular_price(),
                'sale_price' => (string) $product->get_sale_price(),
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

    private function getImageUrl( \WC_Product $product ): string {
        $imageId = $product->get_image_id();

        if ( ! $imageId ) {
            return '';
        }

        $url = wp_get_attachment_image_url( $imageId, 'full' );

        return $url ? $url : '';
    }

    /**
     * Flat list of category names (not a hierarchical path) joined with the
     * same ' %% ' separator suite-magento uses - see CLAUDE.md. WooCommerce
     * has no equivalent to Magento's "only categories in navigation menu"
     * option, so every assigned category is included.
     */
    private function getCategoryNames( \WC_Product $product ): string {
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
     * the field mapping table in CLAUDE.md for the reasoning per product
     * type. Also covers any product that's outright not purchasable (e.g.
     * out of stock without backorders) - resolves the "simple non-purchasable"
     * item from "Pendiente de decidir" in favour of never showing a broken
     * Add to cart button.
     */
    private function hasDisabledAddToCart( \WC_Product $product ): bool {
        return ! $product->is_purchasable()
            || $product->is_type( 'variable' )
            || $product->is_type( 'grouped' )
            || $product->is_type( 'external' );
    }

    /**
     * @param mixed $value
     */
    private function appendField( \DOMDocument $document, \DOMElement $parent, string $name, $value ): void {
        $element = $document->createElement( $name );
        $element->appendChild( $document->createTextNode( (string) $value ) );
        $parent->appendChild( $element );
    }

    private function writeFeedFile( string $xml ): void {
        $uploadDir = wp_upload_dir();
        $dir       = trailingslashit( $uploadDir['basedir'] ) . 'solosearch';

        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
            // Prevent directory listing - unlike WooCommerce's own wc-logs
            // directory, the feed file itself must stay publicly fetchable
            // (suite reads it by URL), so no "deny from all" here.
            file_put_contents( $dir . '/index.html', '' );
        }

        file_put_contents( $dir . '/feed.xml', $xml );
    }
}
