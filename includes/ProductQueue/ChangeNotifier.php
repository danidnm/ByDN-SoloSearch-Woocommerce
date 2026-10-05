<?php

namespace Bydn\SoloSearchWoo\ProductQueue;

use Bydn\SoloSearchWoo\Config;
use Bydn\SoloSearchWoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Decides whether a product change is worth queuing for real-time sync, and
 * queues it. Hooked in Plugin::run() to:
 *  - woocommerce_product_object_updated_props ($product, $updated_props) -
 *    fires on both create and update (see handle_updated_props() in
 *    WooCommerce's class-wc-product-data-store-cpt.php), with $updated_props
 *    listing exactly which WC_Product properties changed.
 *  - woocommerce_trash_product / woocommerce_delete_product ($id only, no
 *    product object - it's already gone/trashed by the time these fire).
 *
 * Unlike suite-magento's ProductChangeNotifier (which only watches a
 * specific list of attribute codes via dataHasChangedFor()), this queues on
 * ANY non-empty $updated_props - WooCommerce doesn't cleanly expose a diff
 * for arbitrary post meta/product attributes (used by Field Mapping) the way
 * Magento's collection-based approach does, and the queue's own idempotent
 * reuse-pending-row design (see Repository::enqueue()) already keeps
 * over-triggering cheap. Bulk/quick edit needs no separate observer here:
 * WooCommerce saves each WC_Product individually in that path too (unlike
 * Magento's raw SQL mass update), so it already fires
 * woocommerce_product_object_updated_props like any other save.
 */
class ChangeNotifier {

    /**
     * @param \WC_Product $product
     * @param string[]    $updated_props
     * @return void
     */
    public static function product_changed( $product, $updated_props ) {
        if ( ! self::should_run() || empty( $updated_props ) ) {
            return;
        }

        // A product that no longer qualifies for the feed (unpublished) is
        // removed from the index instead of sent as a stale "update" -
        // mirrors suite-magento's ProductSaveAfter eligibility check.
        $operation = Fields::is_visible( $product ) ? Repository::OPERATION_UPDATE : Repository::OPERATION_DELETE;

        ( new Repository() )->enqueue( $product->get_id(), $operation );

        Logger::info( sprintf( 'ProductQueue: queued product %d for %s.', $product->get_id(), $operation ) );
    }

    /**
     * @param int $product_id
     * @return void
     */
    public static function product_deleted( $product_id ) {
        if ( ! self::should_run() ) {
            return;
        }

        ( new Repository() )->enqueue( (int) $product_id, Repository::OPERATION_DELETE );

        Logger::info( sprintf( 'ProductQueue: queued product %d for delete.', (int) $product_id ) );
    }

    /**
     * @return bool
     */
    private static function should_run(): bool {
        $config = new Config();

        return $config->isEnabled() && $config->isRealtimeSyncEnabled();
    }
}
