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
     * TODO: iterate published products (see the field mapping table in
     * suite-woocommerce/CLAUDE.md), build the feed in the SoloSearch format
     * (suite/resources/docs/en/feed-formats/solosearch-format.md), write it
     * to its destination, and notify suite to reindex (SoloSearchClient,
     * not built yet either).
     *
     * @return void
     */
    public function generate() {
    }
}
