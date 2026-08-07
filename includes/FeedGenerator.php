<?php

namespace Bydn\SoloSearchWoo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generates the SoloSearch product feed. Skeleton for now - reads no products
 * yet. Both the WP-CLI command (Cli\FeedCommand) and the scheduled cron task
 * (Cron\FeedSchedule) call generate(), so there is a single place that does
 * the real work - mirrors suite-magento's Model/FeedGenerator.php.
 */
class FeedGenerator {

    /**
     * TODO: iterate published products, build the feed in the SoloSearch
     * format (suite/resources/docs/en/feed-formats/solosearch-format.md):
     *   - structural fields (Config::STRUCTURAL_FIELDS) straight from the
     *     product, as mapped in suite-woocommerce/CLAUDE.md.
     *   - extra fields from Config::getFieldMapping() - $row['source'] is
     *     'attribute' (WC_Product::get_attribute($row['key'])) or
     *     'custom_field' (WC_Product::get_meta($row['key'])). Skip any row
     *     whose 'field' collides with a Config::STRUCTURAL_FIELDS name,
     *     same as suite-magento's buildItemNode() ignoring Field Mapping
     *     rows that target a structural field.
     * Then write the feed to its destination, and notify suite to reindex
     * (SoloSearchClient, not built yet either).
     *
     * @return void
     */
    public function generate() {
    }
}
