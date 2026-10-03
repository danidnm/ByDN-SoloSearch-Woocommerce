<?php

namespace Bydn\SoloSearchWoo\ProductQueue;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Creates/upgrades the custom table backing the real-time product queue
 * (Repository.php). Called from Plugin::activate() - dbDelta() is also safe
 * to call again on every activation (e.g. after a plugin update), it only
 * applies the diff against whatever's already there.
 *
 * No unique constraint on product_id: a product can accumulate several
 * finished (success/error) rows over time as a visibility/audit trail (see
 * Repository::enqueue() for the "reuse only if still pending" rule) - same
 * keep-history design as suite-magento's Api\Data\ProductQueueItemInterface.
 * Single-site scope, so no store_id column (unlike suite-magento, which
 * needs one per store view).
 */
class Schema {

    /**
     * @return string
     */
    public static function table_name(): string {
        global $wpdb;

        return $wpdb->prefix . 'solosearch_product_queue';
    }

    /**
     * @return void
     */
    public static function install() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name      = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            operation VARCHAR(10) NOT NULL DEFAULT 'update',
            status VARCHAR(10) NOT NULL DEFAULT 'pending',
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            result TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta( $sql );
    }
}
