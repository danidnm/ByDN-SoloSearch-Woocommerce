<?php

namespace Bydn\SoloSearchWoo\ProductQueue;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Thin $wpdb wrapper around the product queue table (see Schema.php) - the
 * only class that writes SQL against it. Mirrors suite-magento's
 * Model/ProductQueueItemRepository.php + parts of Model/ProductQueueSync.php
 * (failItem()/finishItem()/rejectItem() live here instead, since this plugin
 * has no separate repository/model split).
 */
class Repository {

    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR   = 'error';

    const OPERATION_UPDATE = 'update';
    const OPERATION_DELETE = 'delete';

    /**
     * Finds the still-unprocessed (pending) queue row for a product, if one
     * exists. Deliberately ignores success/error rows - those are history,
     * never reused for a new change (see enqueue()).
     *
     * @param int $product_id
     * @return array<string, mixed>|null
     */
    public function find_pending( $product_id ) {
        global $wpdb;

        $table = Schema::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE product_id = %d AND status = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $product_id,
                self::STATUS_PENDING
            ),
            ARRAY_A
        );

        return $row ? $row : null;
    }

    /**
     * Queues a product+operation change. If a still-pending row already
     * exists for this product, it's reused - operation refreshed, attempts
     * reset to 0 - since nothing was actually sent for it yet. Otherwise a
     * new row is inserted; a product that already has success/error history
     * is never reused, so that history stays intact. Mirrors suite-magento's
     * ProductChangeNotifier::enqueue().
     *
     * @param int    $product_id
     * @param string $operation
     * @return void
     */
    public function enqueue( $product_id, $operation ) {
        global $wpdb;

        $table = Schema::table_name();
        $now   = current_time( 'mysql', true );
        $existing = $this->find_pending( $product_id );

        if ( $existing ) {
            $wpdb->update(
                $table,
                array(
                    'operation' => $operation,
                    'attempts'  => 0,
                    'result'    => null,
                    'updated_at' => $now,
                ),
                array( 'id' => $existing['id'] ),
                array( '%s', '%d', '%s', '%s' ),
                array( '%d' )
            );

            return;
        }

        $wpdb->insert(
            $table,
            array(
                'product_id' => $product_id,
                'operation'  => $operation,
                'status'     => self::STATUS_PENDING,
                'attempts'   => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s' )
        );
    }

    /**
     * Oldest-first, up to $limit pending rows - what the sync cron pulls per
     * batch run.
     *
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function get_pending( $limit ) {
        global $wpdb;

        $table = Schema::table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                self::STATUS_PENDING,
                $limit
            ),
            ARRAY_A
        );

        return $rows ? $rows : array();
    }

    /**
     * A queue row that was sent successfully is kept, not deleted - marked
     * success as a visibility/audit trail. `attempts` is left as-is, not
     * reset - useful information here, not just retry-budget bookkeeping.
     *
     * @param int $id
     * @return void
     */
    public function mark_success( $id ) {
        global $wpdb;

        $wpdb->update(
            Schema::table_name(),
            array(
                'status'     => self::STATUS_SUCCESS,
                'result'     => null,
                'updated_at' => current_time( 'mysql', true ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Records a failed send attempt. Stays pending (so the next sync() run
     * picks it up and retries it) as long as attempts is still under
     * $max_attempts; only moves to the terminal "error" status once that
     * limit is reached. Mirrors suite-magento's ProductQueueSync::failItem().
     *
     * @param int    $id
     * @param int    $attempts_before This attempt, i.e. the row's current attempts count before this failure.
     * @param string $error
     * @param int    $max_attempts
     * @return bool True when this failure was terminal (moved to "error").
     */
    public function mark_failed( $id, $attempts_before, $error, $max_attempts ) {
        global $wpdb;

        $attempts  = $attempts_before + 1;
        $exhausted = $attempts >= $max_attempts;

        $data   = array( 'attempts' => $attempts, 'result' => $error, 'updated_at' => current_time( 'mysql', true ) );
        $format = array( '%d', '%s', '%s' );

        if ( $exhausted ) {
            $data['status'] = self::STATUS_ERROR;
            $format[]        = '%s';
        }

        $wpdb->update( Schema::table_name(), $data, array( 'id' => $id ), $format, array( '%d' ) );

        return $exhausted;
    }

    /**
     * Real-time sync is disabled - every pending row passed here is rejected
     * outright: a terminal "error" status immediately, not retried like a
     * genuine send failure (see mark_failed()), since attempts weren't
     * actually spent trying. Mirrors suite-magento's
     * ProductQueueSync::rejectItem().
     *
     * @param array<int, int> $ids
     * @param string          $message
     * @return void
     */
    public function reject_pending( array $ids, $message ) {
        global $wpdb;

        if ( empty( $ids ) ) {
            return;
        }

        $table        = Schema::table_name();
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, result = %s, updated_at = %s WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                array_merge(
                    array( self::STATUS_ERROR, $message, current_time( 'mysql', true ) ),
                    $ids
                )
            )
        );
    }

    /**
     * Removes finished (success/error) rows last updated before $cutoff -
     * used by ProductQueue\Cleaner. Never touches pending rows regardless of
     * age - those are real unsent work, not history.
     *
     * @param string $cutoff "Y-m-d H:i:s", UTC (matches current_time('mysql', true) above).
     * @return int Number of rows removed.
     */
    public function delete_finished_older_than( $cutoff ) {
        global $wpdb;

        $table = Schema::table_name();

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE status IN (%s, %s) AND updated_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                self::STATUS_SUCCESS,
                self::STATUS_ERROR,
                $cutoff
            )
        );
    }
}
