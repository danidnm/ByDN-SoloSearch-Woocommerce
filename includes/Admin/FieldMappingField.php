<?php

namespace Bydn\SoloSearchWoo\Admin;

use Bydn\SoloSearchWoo\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders the Field Mapping grid on the SoloSearch settings page, as a
 * custom WC_Settings_Page field type ('solosearch_mapping') - WooCommerce's
 * settings framework has no built-in repeater/grid field type. Mirrors
 * suite-magento's admin grid (Block/Adminhtml/Form/Field/FieldMapping.php),
 * adapted to WooCommerce's two mappable data sources (see CLAUDE.md).
 *
 * Saving needs no custom logic on our part: WC_Admin_Settings::save_fields()
 * falls back to wc_clean() for field types it doesn't recognise, which
 * sanitises our nested field[]/source[]/key[] arrays recursively and stores
 * the result as-is under Config::OPTION_FIELD_MAPPING.
 */
class FieldMappingField {

    const TYPE = 'solosearch_mapping';

    public function register(): void {
        add_action( 'woocommerce_admin_field_' . self::TYPE, array( $this, 'render' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * @param array $field The settings array entry for this field (id, type...).
     */
    public function render( $field ): void {
        $rows = ( new Config() )->getFieldMapping();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php esc_html_e( 'Field mapping', 'solosearch-for-woocommerce' ); ?></label>
            </th>
            <td class="forminp">
                <table class="widefat striped" id="ss-field-mapping-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'SoloSearch field', 'solosearch-for-woocommerce' ); ?></th>
                            <th><?php esc_html_e( 'Source', 'solosearch-for-woocommerce' ); ?></th>
                            <th><?php esc_html_e( 'Key', 'solosearch-for-woocommerce' ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $row ) : ?>
                            <?php $this->render_row( $row['field'], $row['source'], $row['key'] ); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <button type="button" class="button" id="ss-field-mapping-add"><?php esc_html_e( '+ Add field', 'solosearch-for-woocommerce' ); ?></button>
                </p>
                <template id="ss-field-mapping-row-template">
                    <?php $this->render_row( '', 'attribute', '' ); ?>
                </template>
                <p class="description">
                    <?php
                    printf(
                        /* translators: %s: comma-separated list of structural field names */
                        esc_html__( "Structural fields (%s) are always computed automatically and can't be overridden here.", 'solosearch-for-woocommerce' ),
                        esc_html( implode( ', ', Config::STRUCTURAL_FIELDS ) )
                    );
                    ?>
                </p>
            </td>
        </tr>
        <?php
    }

    private function render_row( string $field, string $source, string $key ): void {
        ?>
        <tr class="ss-field-mapping-row">
            <td>
                <input type="text" class="regular-text" name="<?php echo esc_attr( Config::OPTION_FIELD_MAPPING ); ?>[field][]" value="<?php echo esc_attr( $field ); ?>" placeholder="<?php esc_attr_e( 'e.g. color', 'solosearch-for-woocommerce' ); ?>">
            </td>
            <td>
                <select name="<?php echo esc_attr( Config::OPTION_FIELD_MAPPING ); ?>[source][]">
                    <option value="attribute" <?php selected( $source, 'attribute' ); ?>><?php esc_html_e( 'Attribute', 'solosearch-for-woocommerce' ); ?></option>
                    <option value="custom_field" <?php selected( $source, 'custom_field' ); ?>><?php esc_html_e( 'Custom field', 'solosearch-for-woocommerce' ); ?></option>
                </select>
            </td>
            <td>
                <input type="text" class="regular-text" name="<?php echo esc_attr( Config::OPTION_FIELD_MAPPING ); ?>[key][]" value="<?php echo esc_attr( $key ); ?>" placeholder="<?php esc_attr_e( 'e.g. pa_color or _custom_meta_key', 'solosearch-for-woocommerce' ); ?>">
            </td>
            <td>
                <button type="button" class="button ss-field-mapping-remove">&times;</button>
            </td>
        </tr>
        <?php
    }

    /**
     * Only enqueues on the Field Mapping section of our own settings page -
     * a repeater grid has no business loading anywhere else.
     *
     * @param string $hook Current admin page hook suffix.
     */
    public function enqueue_assets( $hook ): void {
        if ( 'woocommerce_page_wc-settings' !== $hook ) {
            return;
        }

        // Read-only check to decide whether to enqueue a script, not processing
        // form data - no nonce needed for that.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! isset( $_GET['tab'], $_GET['section'] ) || 'solosearch' !== $_GET['tab'] || 'mapping' !== $_GET['section'] ) {
            return;
        }

        wp_enqueue_script(
            'solosearch-woo-field-mapping',
            SOLOSEARCH_WOO_PLUGIN_URL . 'assets/js/field-mapping.js',
            array(),
            SOLOSEARCH_WOO_VERSION,
            true
        );

        wp_enqueue_style(
            'solosearch-woo-settings',
            SOLOSEARCH_WOO_PLUGIN_URL . 'assets/css/settings.css',
            array(),
            SOLOSEARCH_WOO_VERSION
        );
    }
}
