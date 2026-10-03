<?php

namespace Bydn\SoloSearchWoo\ProductQueue;

use Bydn\SoloSearchWoo\Config;
use Bydn\SoloSearchWoo\Logger;
use Bydn\SoloSearchWoo\SoloSearchClient;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sends pending queued product changes (see Repository.php) to SoloSearch's
 * single-product API - the real-time counterpart to FeedGenerator's
 * scheduled full Feed Reindex. Called by Cron\ProductQueueSchedule and the
 * `wp solosearch queue sync` command, both kept thin on purpose - this class
 * owns the actual logic. Mirrors suite-magento's Model/ProductQueueSync.php.
 */
class Sync {

    // Matches the API's own per-request cap (see suite's
    // OpenSearchClient::BULK_BATCH_SIZE) - no point pulling more pending
    // rows than a single batch call could ever send.
    const BATCH_SIZE = 500;

    // A row that keeps failing for the same reason (e.g. a permanently
    // missing required field) would otherwise retry forever - it stays
    // pending and is retried on every sync() run up to this many attempts,
    // then moves to the terminal "error" status (see Repository::mark_failed()).
    const MAX_ATTEMPTS = 3;

    // Set as a pending item's `result` when real-time sync is turned off
    // (Enable Real-Time Sync setting) and sync() finds it still waiting.
    const REALTIME_SYNC_DISABLED_MESSAGE = 'Real-time sync is disabled.';

    /**
     * @return void
     */
    public function sync() {
        $config = new Config();

        if ( ! $config->isEnabled() ) {
            return;
        }

        $repository = new Repository();

        if ( ! $config->isRealtimeSyncEnabled() ) {
            $this->reject_pending( $repository );
            return;
        }

        $items = $repository->get_pending( self::BATCH_SIZE );

        if ( empty( $items ) ) {
            return;
        }

        $update_items = array();
        $delete_items = array();

        foreach ( $items as $item ) {
            if ( Repository::OPERATION_DELETE === $item['operation'] ) {
                $delete_items[] = $item;
            } else {
                $update_items[] = $item;
            }
        }

        $indexed = 0;
        $failed  = 0;

        if ( ! empty( $update_items ) ) {
            list( $indexed, $failed, $reclassified ) = $this->sync_updates( $repository, $update_items );
            $delete_items = array_merge( $delete_items, $reclassified );
        }

        $deleted = 0;

        if ( ! empty( $delete_items ) ) {
            list( $deleted, $delete_failed ) = $this->sync_deletes( $repository, $delete_items );
            $failed += $delete_failed;
        }

        Logger::info( sprintf( 'ProductQueue sync: %d updated, %d deleted, %d failed.', $indexed, $deleted, $failed ) );
    }

    /**
     * Sends the update batch in one call. A product no longer found (deleted
     * outright) or no longer eligible for the feed (unpublished) is
     * reclassified as a delete instead, same as suite-magento's
     * ProductQueueSync::syncUpdates().
     *
     * @param array<int, array<string, mixed>> $items
     * @return array{0: int, 1: int, 2: array<int, array<string, mixed>>}
     */
    private function sync_updates( Repository $repository, array $items ): array {
        $indexed   = 0;
        $failed    = 0;
        $to_delete = array();

        $mapping             = ( new Config() )->getFieldMapping();
        $items_by_product_id = array();
        $payload             = array();

        foreach ( $items as $item ) {
            $product_id = (int) $item['product_id'];
            $items_by_product_id[ $product_id ] = $item;

            $product = wc_get_product( $product_id );

            if ( ! $product || ! Fields::is_visible( $product ) ) {
                $to_delete[] = $item;
                continue;
            }

            $fields       = Fields::build( $product, $mapping );
            $fields['id'] = (string) $product_id;
            $payload[]    = $fields;
        }

        if ( empty( $payload ) ) {
            return array( $indexed, $failed, $to_delete );
        }

        $results = ( new SoloSearchClient() )->sendProductBatch( $payload );

        if ( null === $results ) {
            // Nothing sent - every row stays pending as-is, retried next run.
            return array( $indexed, $failed, $to_delete );
        }

        foreach ( $results as $result ) {
            $product_id = isset( $result['id'] ) ? (int) $result['id'] : null;
            $item       = null !== $product_id && isset( $items_by_product_id[ $product_id ] ) ? $items_by_product_id[ $product_id ] : null;

            if ( null === $item ) {
                continue;
            }

            if ( ! empty( $result['success'] ) ) {
                $repository->mark_success( $item['id'] );
                $indexed++;
            } else {
                $repository->mark_failed( $item['id'], (int) $item['attempts'], (string) $result['error'], self::MAX_ATTEMPTS );
                $failed++;
            }
        }

        return array( $indexed, $failed, $to_delete );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{0: int, 1: int}
     */
    private function sync_deletes( Repository $repository, array $items ): array {
        $deleted = 0;
        $failed  = 0;
        $client  = new SoloSearchClient();

        foreach ( $items as $item ) {
            if ( $client->deleteProduct( $item['product_id'] ) ) {
                $repository->mark_success( $item['id'] );
                $deleted++;
            } else {
                $repository->mark_failed( $item['id'], (int) $item['attempts'], 'Delete request failed - see the SoloSearch log for details.', self::MAX_ATTEMPTS );
                $failed++;
            }
        }

        return array( $deleted, $failed );
    }

    /**
     * Real-time sync is off - whatever is still pending never gets sent.
     * Every pending item is rejected outright rather than left waiting: with
     * ChangeNotifier also gated on the same setting, nothing new is being
     * queued while this is off, so there is nothing to gain leaving old rows
     * waiting for a setting change that may never come. Mirrors
     * suite-magento's ProductQueueSync::rejectPendingItems().
     *
     * @return void
     */
    private function reject_pending( Repository $repository ) {
        $items = $repository->get_pending( self::BATCH_SIZE );

        if ( empty( $items ) ) {
            return;
        }

        $ids = wp_list_pluck( $items, 'id' );
        $repository->reject_pending( $ids, self::REALTIME_SYNC_DISABLED_MESSAGE );

        Logger::info( sprintf( 'ProductQueue sync: %d item(s) rejected, real-time sync disabled.', count( $items ) ) );
    }
}
