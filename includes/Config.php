<?php

namespace Bydn\SoloSearchWoo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Single source of truth for reading plugin settings - nothing else in the
 * plugin should call get_option() directly for our own option keys. Mirrors
 * suite-magento's Helper/Config.php: one place that knows every option name
 * and its default, everything else asks this class instead.
 *
 * STRUCTURAL_FIELDS lists the feed fields that are always computed directly
 * from the product - never overridable via field mapping, even if an admin
 * enters one of these names, mirroring suite-magento's STRUCTURAL_FEED_FIELDS.
 * FeedGenerator (not built yet) must skip any mapping row targeting one of
 * these when it actually builds the feed.
 */
class Config {

    const OPTION_ENABLE                 = 'solosearch_woo_enable';
    const OPTION_API_URL                = 'solosearch_woo_api_url';
    const OPTION_API_TOKEN              = 'solosearch_woo_api_token';
    const OPTION_AUTO_GENERATION_ENABLE = 'solosearch_woo_auto_generation_enable';
    const OPTION_GENERATION_TIME        = 'solosearch_woo_generation_time';
    const OPTION_FIELD_MAPPING          = 'solosearch_woo_field_mapping';
    const OPTION_WIDGET_ENABLE          = 'solosearch_woo_widget_enable';
    const OPTION_SEARCH_ENGINE_ID = 'solosearch_woo_widget_search_engine_id';
    const OPTION_SCRIPT_URL       = 'solosearch_woo_widget_script_url';
    const OPTION_INPUT_SELECTOR   = 'solosearch_woo_widget_input_selector';
    const OPTION_LOCALE           = 'solosearch_woo_widget_locale';
    const OPTION_TEMPLATE_SET_ID  = 'solosearch_woo_widget_template_set_id';

    const STRUCTURAL_FIELDS = array(
        'id', 'sku', 'title', 'link', 'image', 'price', 'sale_price',
        'availability', 'disable_add_to_cart', 'categories', 'currency',
    );

    const DEFAULT_API_URL         = 'https://app.solosearch.app';
    const DEFAULT_SCRIPT_URL      = 'https://search.solosearch.app/widget.js';
    const DEFAULT_GENERATION_TIME = '03:00';

    /**
     * Master switch for the whole plugin - mirrors suite-magento's General >
     * Enable. When off, nothing runs: no feed generation (manual command or
     * cron) and no widget embed, regardless of the per-feature toggles below.
     * See isAutoGenerationEnabled() and shouldRenderWidget().
     */
    public function isEnabled(): bool {
        return 'yes' === get_option( self::OPTION_ENABLE, 'no' );
    }

    /**
     * Base URL of the suite panel API (reindex endpoint lives under this).
     */
    public function getApiUrl(): string {
        $url = get_option( self::OPTION_API_URL, self::DEFAULT_API_URL );
        return $url ? untrailingslashit( (string) $url ) : self::DEFAULT_API_URL;
    }

    /**
     * Bearer token for the search engine's reindex endpoint - same token
     * shown on the engine's General tab in the SoloSearch panel, under API
     * Token. Stored in plain text (WordPress has no built-in secret
     * encryption comparable to Magento's Backend\Encrypted), same as this
     * author's other plugins store API keys.
     */
    public function getApiToken(): string {
        return (string) get_option( self::OPTION_API_TOKEN, '' );
    }

    /**
     * Whether the feed regenerates automatically on the cron schedule below.
     * Only gates the cron path - manual generation (wp solosearch feed
     * generate) ignores this and only respects isEnabled(), same as
     * suite-magento's daily_generation_enabled only gating the cron job, not
     * the console command.
     */
    public function isAutoGenerationEnabled(): bool {
        return 'yes' === get_option( self::OPTION_AUTO_GENERATION_ENABLE, 'yes' );
    }

    /**
     * "HH:MM", server time - when the daily feed generation cron fires. See
     * Cron\FeedSchedule.
     */
    public function getGenerationTime(): string {
        $time = get_option( self::OPTION_GENERATION_TIME, self::DEFAULT_GENERATION_TIME );
        return $time ? (string) $time : self::DEFAULT_GENERATION_TIME;
    }

    /**
     * Extra product data to send in the feed, beyond the structural fields.
     * Stored as parallel arrays (field[]/source[]/key[], one entry per row -
     * how the repeater grid in Admin\FieldMappingField submits itself) and
     * normalised here into a list of rows, skipping any row missing a field
     * name or key. $row['source'] is 'attribute' or 'custom_field' - see
     * CLAUDE.md for what each maps to on a WC_Product.
     *
     * Rows targeting a structural field name are not filtered out here -
     * that's FeedGenerator's job when it actually builds the feed (see
     * STRUCTURAL_FIELDS), same as Config staying a plain data accessor
     * everywhere else in this class.
     *
     * @return array<int, array{field: string, source: string, key: string}>
     */
    public function getFieldMapping(): array {
        $raw = get_option( self::OPTION_FIELD_MAPPING, array() );

        if ( ! is_array( $raw ) || empty( $raw['field'] ) || ! is_array( $raw['field'] ) ) {
            return array();
        }

        $rows = array();

        foreach ( $raw['field'] as $i => $field ) {
            $field = trim( (string) $field );
            $key   = isset( $raw['key'][ $i ] ) ? trim( (string) $raw['key'][ $i ] ) : '';

            if ( '' === $field || '' === $key ) {
                continue;
            }

            $source   = isset( $raw['source'][ $i ] ) ? (string) $raw['source'][ $i ] : 'attribute';
            $rows[] = array(
                'field'  => $field,
                'source' => 'custom_field' === $source ? 'custom_field' : 'attribute',
                'key'    => $key,
            );
        }

        return $rows;
    }

    public function isWidgetEnabled(): bool {
        return 'yes' === get_option( self::OPTION_WIDGET_ENABLE, 'no' );
    }

    /**
     * True only when both the plugin's master switch and the widget's own
     * toggle are on - the widget embed code (not built yet) should check
     * this instead of isWidgetEnabled() alone, so disabling the plugin
     * entirely also stops the widget without needing a second, separate
     * check wherever it renders.
     */
    public function shouldRenderWidget(): bool {
        return $this->isEnabled() && $this->isWidgetEnabled();
    }

    public function getSearchEngineId(): string {
        return (string) get_option( self::OPTION_SEARCH_ENGINE_ID, '' );
    }

    public function getScriptUrl(): string {
        $url = get_option( self::OPTION_SCRIPT_URL, self::DEFAULT_SCRIPT_URL );
        return $url ? (string) $url : self::DEFAULT_SCRIPT_URL;
    }

    /**
     * CSS selector for the shop's own search field. Empty means "let the
     * widget use its own default (#search)" - never invent a value here.
     */
    public function getInputSelector(): string {
        return (string) get_option( self::OPTION_INPUT_SELECTOR, '' );
    }

    /**
     * Empty means "use the engine's own default language" - never invent a
     * value here, the widget script omits the data-locale attribute entirely
     * when this is blank.
     */
    public function getLocale(): string {
        return (string) get_option( self::OPTION_LOCALE, '' );
    }

    /**
     * Empty means "use the engine's active/default template set" - advanced,
     * most stores never set this.
     */
    public function getTemplateSetId(): string {
        return (string) get_option( self::OPTION_TEMPLATE_SET_ID, '' );
    }

    /**
     * True only when everything the reindex API call needs is actually
     * configured - mirrors Magento's SoloSearchClient silently skipping the
     * notification (not erroring) whenever config is incomplete.
     */
    public function canNotifyReindex(): bool {
        return '' !== $this->getApiToken() && '' !== $this->getSearchEngineId();
    }
}
