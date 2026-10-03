<?php

namespace Bydn\SoloSearchWoo;

use Bydn\SoloSearchWoo\ProductQueue\Fields;

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

        ( new SoloSearchClient() )->requestReindex();
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

        foreach ( Fields::build( $product, $mapping ) as $name => $value ) {
            $this->appendField( $document, $item, $name, $value );
        }

        return $item;
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
