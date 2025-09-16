<?php
/**
 * Admin Page: Data Sources (UI Simplified)
 * - Full-width layout using core admin styles.
 * - Single primary action: “Add Source” linking to the Add Source flow.
 * - Separate “API Settings (Saved Once)” form to persist provider keys globally.
 * - Uses settings_errors() for notices and wp_nonce_field for security.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'zc-dmt' ) );
}

/**
 * Load saved API settings.
 */
$zc_dmt_api_settings = get_option( 'zc_dmt_api_settings', array() );

/**
 * Save API settings (POST).
 */
if ( isset( $_POST['zc_dmt_action'] ) && $_POST['zc_dmt_action'] === 'save_api_settings' ) {
    check_admin_referer( 'zc_dmt_save_api_settings', 'zc_dmt_api_nonce' );

    $new = array(
        'fred_api_key'       => isset($_POST['fred_api_key']) ? sanitize_text_field( wp_unslash($_POST['fred_api_key']) ) : '',
        'bls_api_key'        => isset($_POST['bls_api_key']) ? sanitize_text_field( wp_unslash($_POST['bls_api_key']) ) : '',
        'google_api_key'     => isset($_POST['google_api_key']) ? sanitize_text_field( wp_unslash($_POST['google_api_key']) ) : '',
        'yahoo_api_key'      => isset($_POST['yahoo_api_key']) ? sanitize_text_field( wp_unslash($_POST['yahoo_api_key']) ) : '',
        'worldbank_api_key'  => isset($_POST['worldbank_api_key']) ? sanitize_text_field( wp_unslash($_POST['worldbank_api_key']) ) : '',
        'oecd_api_key'       => isset($_POST['oecd_api_key']) ? sanitize_text_field( wp_unslash($_POST['oecd_api_key']) ) : '',
        'eurostat_api_key'   => isset($_POST['eurostat_api_key']) ? sanitize_text_field( wp_unslash($_POST['eurostat_api_key']) ) : '',
        // Extend with more providers as needed.
    );

    $merged = is_array( $zc_dmt_api_settings ) ? $zc_dmt_api_settings : array();
    foreach ( $new as $k => $v ) {
        $merged[ $k ] = $v;
    }
    update_option( 'zc_dmt_api_settings', $merged, false );
    $zc_dmt_api_settings = $merged;

    add_settings_error( 'zc_dmt_api_settings', 'saved', esc_html__( 'API settings saved.', 'zc-dmt' ), 'updated' );
}

/**
 * Build Add Source URL (menu slug expected to route to admin/add-source.php).
 * Adjust the page slug if your menu registration uses a different one.
 */
$add_source_url = admin_url( 'admin.php?page=zc-dmt-add-source' ); // Uses admin_url() to stay portable.
/* If needed, you can verify/alter the slug used when registering the Add Source admin page. */

?>
<style>
    /* Layout helpers for full-width, responsive cards */
    .zc-dmt-wrap .card { padding:16px; }
    .zc-dmt-grid {
        display:grid;
        grid-template-columns: repeat(12, 1fr);
        gap:16px;
    }
    .zc-dmt-col-12 { grid-column: span 12; }
    .zc-dmt-col-6 { grid-column: span 6; }
    .zc-dmt-col-4 { grid-column: span 4; }
    .zc-dmt-col-3 { grid-column: span 3; }
    @media (max-width: 1200px) {
        .zc-dmt-col-6 { grid-column: span 12; }
        .zc-dmt-col-4 { grid-column: span 6; }
        .zc-dmt-col-3 { grid-column: span 6; }
    }
    @media (max-width: 782px) {
        .zc-dmt-grid { gap:12px; }
        .zc-dmt-col-4, .zc-dmt-col-3 { grid-column: span 12; }
    }
    .zc-dmt-actions {
        display:flex;
        flex-wrap:wrap;
        gap:12px;
        align-items:center;
    }
    .zc-dmt-field {
        margin-bottom:12px;
    }
    .zc-dmt-field input[type="text"],
    .zc-dmt-field input[type="password"],
    .zc-dmt-field input[type="url"] {
        width: 100%;
        max-width: 480px;
    }
    .zc-dmt-muted { color:#666; }
</style>

<div class="wrap zc-dmt-wrap">
    <h1><?php esc_html_e( 'Data Sources', 'zc-dmt' ); ?></h1>

    <?php
    // Display settings API notices (API settings saved, errors, etc.).
    settings_errors( 'zc_dmt_api_settings' );
    ?>

    <!-- Primary actions -->
    <div class="zc-dmt-grid" style="margin-top:16px;">
        <div class="zc-dmt-col-12">
            <div class="card">
                <h2 style="margin-top:0;"><?php esc_html_e( 'Manage Data Sources', 'zc-dmt' ); ?></h2>
                <p class="zc-dmt-muted"><?php esc_html_e( 'Add new sources and manage provider API keys from here.', 'zc-dmt' ); ?></p>
                <div class="zc-dmt-actions">
                    <a href="<?php echo esc_url( $add_source_url ); ?>" class="button button-primary button-hero">
                        <?php esc_html_e( 'Add Source', 'zc-dmt' ); ?>
                    </a>
                    <span class="zc-dmt-muted"><?php esc_html_e( 'The Add Source flow handles source selection and required details.', 'zc-dmt' ); ?></span>
                </div>
            </div>
        </div>

        <!-- Configured Data Sources (list placeholder; render your table/list here if available) -->
        <div class="zc-dmt-col-12">
            <div class="card">
                <h2 style="margin-top:0;"><?php esc_html_e( 'Configured Data Sources', 'zc-dmt' ); ?></h2>
                <p class="zc-dmt-muted"><?php esc_html_e( 'Your configured data sources will appear here.', 'zc-dmt' ); ?></p>
                <?php
                // If you already have a renderer/list table, call it here.
                // Example: do_action( 'zc_dmt_render_sources_list' );
                ?>
            </div>
        </div>

        <!-- API Settings -->
        <div class="zc-dmt-col-12">
            <div class="card">
                <h2 style="margin-top:0;"><?php esc_html_e( 'API Settings (Saved Once)', 'zc-dmt' ); ?></h2>
                <p class="zc-dmt-muted"><?php esc_html_e( 'Save provider API keys once; the Add Source flow will auto-detect configured providers.', 'zc-dmt' ); ?></p>

                <form method="post" action="">
                    <?php wp_nonce_field( 'zc_dmt_save_api_settings', 'zc_dmt_api_nonce' ); ?>
                    <input type="hidden" name="zc_dmt_action" value="save_api_settings" />

                    <div class="zc-dmt-grid">
                        <div class="zc-dmt-col-4">
                            <div class="zc-dmt-field">
                                <label for="fred_api_key"><strong><?php esc_html_e( 'FRED API Key', 'zc-dmt' ); ?></strong></label>
                                <input type="password" id="fred_api_key" name="fred_api_key" value="<?php echo esc_attr( $zc_dmt_api_settings['fred_api_key'] ?? '' ); ?>" />
                            </div>
                        </div>
                        <div class="zc-dmt-col-4">
                            <div class="zc-dmt-field">
                                <label for="bls_api_key"><strong><?php esc_html_e( 'BLS API Key', 'zc-dmt' ); ?></strong></label>
                                <input type="password" id="bls_api_key" name="bls_api_key" value="<?php echo esc_attr( $zc_dmt_api_settings['bls_api_key'] ?? '' ); ?>" />
                            </div>
                        </div>
                        <div class="zc-dmt-col-4">
                            <div class="zc-dmt-field">
                                <label for="google_api_key"><strong><?php esc_html_e( 'Google API Key', 'zc-dmt' ); ?></strong></label>
                                <input type="password" id="google_api_key" name="google_api_key" value="<?php echo esc_attr( $zc_dmt_api_settings['google_api_key'] ?? '' ); ?>" />
                            </div>
                        </div>

                        <div class="zc-dmt-col-4">
                            <div class="zc-dmt-field">
                                <label for="yahoo_api_key"><strong><?php esc_html_e( 'Yahoo Finance API Key', 'zc-dmt' ); ?></strong></label>
                                <input type="password" id="yahoo_api_key" name="yahoo_api_key" value="<?php echo esc_attr( $zc_dmt_api_settings['yahoo_api_key'] ?? '' ); ?>" />
                            </div>
                        </div>
                        <div class="zc-dmt-col-4">
                            <div class="zc-dmt-field">
                                <label for="worldbank_api_key"><strong><?php esc_html_e( 'World Bank API Key', 'zc-dmt' ); ?></strong></label>
                                <input type="password" id="worldbank_api_key" name="worldbank_api_key" value="<?php echo esc_attr( $zc_dmt_api_settings['worldbank_api_key'] ?? '' ); ?>" />
                            </div>
                        </div>
                        <div class="zc-dmt-col-4">
                            <div class="zc-dmt-field">
                                <label for="oecd_api_key"><strong><?php esc_html_e( 'OECD API Key', 'zc-dmt' ); ?></strong></label>
                                <input type="password" id="oecd_api_key" name="oecd_api_key" value="<?php echo esc_attr( $zc_dmt_api_settings['oecd_api_key'] ?? '' ); ?>" />
                            </div>
                        </div>

                        <div class="zc-dmt-col-4">
                            <div class="zc-dmt-field">
                                <label for="eurostat_api_key"><strong><?php esc_html_e( 'Eurostat API Key', 'zc-dmt' ); ?></strong></label>
                                <input type="password" id="eurostat_api_key" name="eurostat_api_key" value="<?php echo esc_attr( $zc_dmt_api_settings['eurostat_api_key'] ?? '' ); ?>" />
                            </div>
                        </div>
                    </div>

                    <p style="margin-top:8px;">
                        <button type="submit" class="button button-secondary">
                            <?php esc_html_e( 'Save API Settings', 'zc-dmt' ); ?>
                        </button>
                    </p>
                </form>
                <p class="description"><?php esc_html_e( 'These keys are stored once and reused during the Add Source flow (no repeated typing).', 'zc-dmt' ); ?></p>
            </div>
        </div>

        <!-- Supported Data Sources (static info cards) -->
        <div class="zc-dmt-col-12">
            <div class="card">
                <h2 style="margin-top:0;"><?php esc_html_e( 'Supported Data Sources', 'zc-dmt' ); ?></h2>
                <div class="zc-dmt-grid">
                    <?php
                    // You can replace this simple set with programmatic list from your classes.
                    $supported = array(
                        'FRED (Federal Reserve Economic Data)' => array( 'API Key', 'Series ID' ),
                        'Eurostat' => array( 'Dataset Code', 'Indicator Code' ),
                        'World Bank' => array( 'Indicator Code', 'Country Code' ),
                        'OECD' => array( 'Dataset ID', 'Indicator Code' ),
                        'DBnomics' => array( 'Provider Code', 'Dataset Code', 'Series Code' ),
                        'Yahoo Finance' => array( 'Symbol' ),
                        'Google Finance' => array( 'Symbol', 'API Key (Optional)' ),
                        'Google Sheets' => array( 'Sheet URL', 'Sheet ID (Optional)', 'API Key (Optional)' ),
                        'ZIP File Processing' => array( 'ZIP URL', 'Extraction Rules' ),
                    );
                    foreach ( $supported as $name => $fields ) : ?>
                        <div class="zc-dmt-col-3">
                            <div class="card" style="height:100%;">
                                <h3 style="margin-top:0;"><?php echo esc_html( $name ); ?></h3>
                                <p><strong><?php esc_html_e( 'Configuration fields:', 'zc-dmt' ); ?></strong></p>
                                <ul style="list-style:disc; margin-left:18px;">
                                    <?php foreach ( $fields as $f ) : ?>
                                        <li><?php echo esc_html( $f ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="zc-dmt-muted"><?php esc_html_e( 'Use “Add Source” to choose a provider and complete details in a guided two-stage flow.', 'zc-dmt' ); ?></p>
            </div>
        </div>
    </div>
</div>
