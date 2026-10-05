<?php

namespace Bydn\SoloSearchWoo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * HTTP client for SoloSearch's public API (suite - the panel, not
 * suite-search/the widget host). Endpoints are added here as SoloSearch's API
 * grows: requestReindex() (full feed), and sendProductBatch()/deleteProduct()
 * (real-time single-product sync) - mirrors suite-magento's
 * Model/SoloSearchClient.php.
 */
class SoloSearchClient {

    // Keeps a slow/unreachable SoloSearch API from stalling feed generation -
    // this request runs synchronously right after generate(), inside the
    // same cron run (or WP-CLI invocation) that already did the real work.
    const REQUEST_TIMEOUT_SECONDS = 5;

    /**
     * Asks SoloSearch to re-fetch and reindex this feed immediately, instead
     * of waiting for its own schedule (POST
     * {api_url}/api/v1/search-engines/{uuid}/reindex, Bearer token auth).
     *
     * Best-effort: any failure (missing config, network error, non-200
     * response - e.g. rate limited, no fetchable feeds, invalid token) is
     * logged and swallowed, never thrown. A reindex notification failing must
     * not fail feed generation itself, which already succeeded by the time
     * this runs.
     *
     * @return bool
     */
    public function requestReindex(): bool {
        $config = new Config();

        if ( ! $config->canCallApi() ) {
            Logger::info( __METHOD__ . ': skipped, missing api_token/search_engine_id config.' );
            return false;
        }

        $searchEngineId = $config->getSearchEngineId();
        $url            = $config->getApiUrl() . '/api/v1/search-engines/' . rawurlencode( $searchEngineId ) . '/reindex';

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $config->getApiToken(),
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            Logger::error( __METHOD__ . ": request to {$url} failed - " . $response->get_error_message() );
            return false;
        }

        $status = wp_remote_retrieve_response_code( $response );

        if ( 200 !== $status ) {
            Logger::error( __METHOD__ . ": unexpected status {$status} from {$url} - " . wp_remote_retrieve_body( $response ) );
            return false;
        }

        Logger::info( __METHOD__ . ": reindex requested successfully ({$url})" );

        return true;
    }

    /**
     * Adds/updates many products in a single call (POST
     * /api/v1/search-engines/{uuid}/products/batch, Bearer token auth, JSON
     * body {"products": [...]}) - used by the real-time product sync
     * (ProductQueue\Sync) instead of a full Feed Reindex. Mirrors
     * suite-magento's Model/SoloSearchClient::sendProductBatch().
     *
     * Best-effort, same reasoning as requestReindex(): returns null on any
     * failure (missing config, network error, non-200/malformed response) -
     * callers must treat null as "nothing was sent, try again later", not as
     * "every product failed". A 200 response's own `results` array (one
     * entry per product, {id, success, error}) is what tells the caller
     * which products actually succeeded, since the API accepts a
     * partially-failing batch with a 200.
     *
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array{id: string|null, success: bool, error: string|null}>|null
     */
    public function sendProductBatch( array $products ) {
        $config = new Config();

        if ( ! $config->canCallApi() ) {
            Logger::info( __METHOD__ . ': skipped, missing api_token/search_engine_id config.' );
            return null;
        }

        $url = $config->getApiUrl() . '/api/v1/search-engines/' . rawurlencode( $config->getSearchEngineId() ) . '/products/batch';

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $config->getApiToken(),
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( array( 'products' => array_values( $products ) ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            Logger::error( __METHOD__ . ": request to {$url} failed - " . $response->get_error_message() );
            return null;
        }

        $status = wp_remote_retrieve_response_code( $response );

        if ( 200 !== $status ) {
            Logger::error( __METHOD__ . ": unexpected status {$status} from {$url} - " . wp_remote_retrieve_body( $response ) );
            return null;
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $decoded ) || ! isset( $decoded['results'] ) || ! is_array( $decoded['results'] ) ) {
            Logger::error( __METHOD__ . ": malformed response from {$url} - " . wp_remote_retrieve_body( $response ) );
            return null;
        }

        Logger::info( __METHOD__ . ': sent ' . count( $products ) . " product(s) - {$decoded['indexed']} indexed, {$decoded['failed']} failed ({$url})" );

        return $decoded['results'];
    }

    /**
     * Deletes a single product from the index (DELETE
     * /api/v1/search-engines/{uuid}/products/{id}, Bearer token auth) - used
     * by the real-time product sync. No batch-delete endpoint exists yet, so
     * this is called once per product. Mirrors suite-magento's
     * Model/SoloSearchClient::deleteProduct() - simpler here since WordPress's
     * HTTP API takes the method directly, no CURLOPT_CUSTOMREQUEST override
     * (and matching cleanup) needed.
     *
     * Best-effort, same reasoning as requestReindex().
     *
     * @param int|string $product_id
     * @return bool
     */
    public function deleteProduct( $product_id ): bool {
        $config = new Config();

        if ( ! $config->canCallApi() ) {
            Logger::info( __METHOD__ . ': skipped, missing api_token/search_engine_id config.' );
            return false;
        }

        $url = $config->getApiUrl() . '/api/v1/search-engines/' . rawurlencode( $config->getSearchEngineId() )
            . '/products/' . rawurlencode( (string) $product_id );

        $response = wp_remote_request(
            $url,
            array(
                'method'  => 'DELETE',
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $config->getApiToken(),
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            Logger::error( __METHOD__ . ": request to {$url} failed - " . $response->get_error_message() );
            return false;
        }

        $status = wp_remote_retrieve_response_code( $response );

        if ( 200 !== $status ) {
            Logger::error( __METHOD__ . ": unexpected status {$status} from {$url} - " . wp_remote_retrieve_body( $response ) );
            return false;
        }

        Logger::info( __METHOD__ . ": product {$product_id} deleted successfully ({$url})" );

        return true;
    }
}
