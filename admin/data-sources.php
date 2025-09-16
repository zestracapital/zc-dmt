<?php
/**
 * Admin Page: Data Sources
 * - Inline UI to select a source type and instantly render its config form.
 * - A separate persistent "API Settings" form to store provider API keys once.
 * - Add Source submit posts to the existing Add Source handler (backward compatible).
 *
 * Safe to drop-in replace admin/data-sources.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'zc-dmt' ) );
}

/**
 * Load saved API settings once
 */
$zc_dmt_api_settings = get_option( 'zc_dmt_api_settings', array() );

/**
 * Supported sources map (fallback)
 * If plugin class provides a richer definition, it will be used instead of this map.
 */
$fallback_sources = array(
    'fred' => array(
        'label'  => 'FRED (Federal Reserve Economic Data)',
        'fields' => array(
            array('key' => 'api_key',   'label' => 'API Key',   'type' => 'password', 'required' => true,  'prefill' => isset($zc_dmt_api_settings['fred_api_key']) ? $zc_dmt_api_settings['fred_api_key'] : ''),
            array('key' => 'series_id', 'label' => 'Series ID', 'type' => 'text',     'required' => true),
        ),
    ),
    'eurostat' => array(
        'label'  => 'Eurostat',
        'fields' => array(
            array('key' => 'dataset_code',  'label' => 'Dataset Code',  'type' => 'text', 'required' => true),
            array('key' => 'indicator_code','label' => 'Indicator Code','type' => 'text', 'required' => true),
        ),
    ),
    'worldbank' => array(
        'label'  => 'World Bank',
        'fields' => array(
            array('key' => 'indicator_code','label' => 'Indicator Code','type' => 'text', 'required' => true),
            array('key' => 'country_code',  'label' => 'Country Code',  'type' => 'text', 'required' => true),
        ),
    ),
    'oecd' => array(
        'label'  => 'OECD',
        'fields' => array(
            array('key' => 'dataset_id',    'label' => 'Dataset ID',    'type' => 'text', 'required' => true),
            array('key' => 'indicator_code','label' => 'Indicator Code','type' => 'text', 'required' => true),
        ),
    ),
    'dbnomics' => array(
        'label'  => 'DBnomics',
        'fields' => array(
            array('key' => 'provider_code', 'label' => 'Provider Code', 'type' => 'text', 'required' => true),
            array('key' => 'dataset_code',  'label' => 'Dataset Code',  'type' => 'text', 'required' => true),
            array('key' => 'series_code',   'label' => 'Series Code',   'type' => 'text', 'required' => true),
        ),
    ),
    'yahoo_finance' => array(
        'label'  => 'Yahoo Finance',
        'fields' => array(
            array('key' => 'symbol', 'label' => 'Symbol', 'type' => 'text', 'required' => true),
        ),
    ),
    'google_finance' => array(
        'label'  => 'Google Finance',
        'fields' => array(
            array('key' => 'symbol', 'label' => 'Symbol', 'type' => 'text', 'required' => true),
            array('key' => 'api_key','label' => 'API Key','type' => 'password', 'required' => false, 'prefill' => isset($zc_dmt_api_settings['google_api_key']) ? $zc_dmt_api_settings['google_api_key'] : ''),
        ),
    ),
    'google_sheets' => array(
        'label'  => 'Google Sheets',
        'fields' => array(
            array('key' => 'sheet_url', 'label' => 'Sheet URL', 'type' => 'url',  'required' => true),
            array('key' => 'sheet_id',  'label' => 'Sheet ID',  'type' => 'text', 'required' => false),
            array('key' => 'api_key',   'label' => 'API Key',   'type' => 'password', 'required' => false, 'prefill' => isset($zc_dmt_api_settings['google_api_key']) ? $zc_dmt_api_settings['google_api_key'] : ''),
        ),
    ),
    'zip_file' => array(
        'label'  => 'ZIP File Processing',
        'fields' => array(
            array('key' => 'zip_url',         'label' => 'ZIP URL',         'type' => 'url',  'required' => true),
            array('key' => 'extraction_rules','label' => 'Extraction Rules','type' => 'text', 'required' => true),
        ),
    ),
);

/**
 * Try to get supported sources from plugin class (if available)
 * Expected shape (id => ['label'=>string, 'fields'=>[ ['key'=>, 'label'=>, 'type'=>, 'required'=>bool ] ] ])
 */
$supported_sources = $fallback_sources;
if ( class_exists( 'ZC_DMT_Data_Sources' ) && method_exists( 'ZC_DMT_Data_Sources', 'get_supported_sources' ) ) {
    try {
        $maybe = ZC_DMT_Data_Sources::get_supported_sources();
        if ( is_array( $maybe ) && ! empty( $maybe ) ) {
            // Normalize into our expected format where needed
            $normalized = array();
            foreach ( $maybe as $id => $def ) {
                if ( is_array( $def ) ) {
                    $label = isset( $def['label'] ) ? $def['label'] : ( isset( $def['name'] ) ? $def['name'] : strtoupper( $id ) );
                    $fields = isset( $def['fields'] ) ? $def['fields'] : ( isset( $def['config_fields'] ) ? $def['config_fields'] : array() );
                    $normalized[ $id ] = array(
                        'label'  => $label,
                        'fields' => array_map( function( $f ) use ( $id, $zc_dmt_api_settings ) {
                            $key   = isset( $f['key'] ) ? $f['key'] : ( isset( $f['name'] ) ? $f['name'] : '' );
                            $label = isset( $f['label'] ) ? $f['label'] : ucwords( str_replace( '_', ' ', $key ) );
                            $type  = isset( $f['type'] ) ? $f['type'] : 'text';
                            $req   = ! empty( $f['required'] );
                            $prefill = '';
                            // basic prefill hook for common api key fields
                            if ( $key === 'api_key' && isset( $zc_dmt_api_settings[ "{$id}_api_key" ] ) ) {
                                $prefill = $zc_dmt_api_settings[ "{$id}_api_key" ];
                            }
                            return array(
                                'key' => $key, 'label' => $label, 'type' => $type, 'required' => $req, 'prefill' => $prefill
                            );
                        }, is_array( $fields ) ? $fields : array() ),
                    );
                }
            }
            if ( ! empty( $normalized ) ) {
                $supported_sources = $normalized;
            }
        }
    } catch ( \Throwable $e ) {
        // fall back silently
    }
}

/**
 * Handle API settings save (same page)
 */
if ( isset( $_POST['zc_dmt_action'] ) && $_POST['zc_dmt_action'] === 'save_api_settings' ) {
    check_admin_referer( 'zc_dmt_save_api_settings', 'zc_dmt_api_nonce' );

    $new = array(
        'fred_api_key'    => isset($_POST['fred_api_key']) ? sanitize_text_field( wp_unslash($_POST['fred_api_key']) ) : '',
        'bls_api_key'     => isset($_POST['bls_api_key']) ? sanitize_text_field( wp_unslash($_POST['bls_api_key']) ) : '',
        'google_api_key'  => isset($_POST['google_api_key']) ? sanitize_text_field( wp_unslash($_POST['google_api_key']) ) : '',
        'yahoo_api_key'   => isset($_POST['yahoo_api_key']) ? sanitize_text_field( wp_unslash($_POST['yahoo_api_key']) ) : '',
        'worldbank_api_key' => isset($_POST['worldbank_api_key']) ? sanitize_text_field( wp_unslash($_POST['worldbank_api_key']) ) : '',
        'oecd_api_key'    => isset($_POST['oecd_api_key']) ? sanitize_text_field( wp_unslash($_POST['oecd_api_key']) ) : '',
        'eurostat_api_key'=> isset($_POST['eurostat_api_key']) ? sanitize_text_field( wp_unslash($_POST['eurostat_api_key']) ) : '',
        // extend as needed
    );

    // Merge non-empty into existing settings
    $merged = is_array( $zc_dmt_api_settings ) ? $zc_dmt_api_settings : array();
    foreach ( $new as $k => $v ) {
        $merged[ $k ] = $v;
    }
    update_option( 'zc_dmt_api_settings', $merged, false );
    $zc_dmt_api_settings = $merged;

    add_settings_error( 'zc_dmt_api_settings', 'saved', esc_html__( 'API settings saved.', 'zc-dmt' ), 'updated' );
}

/**
 * Handle Add Source (process here but keep BC with existing handler)
 * If your plugin already has a dedicated "add-source.php" processor, you can keep this page
 * purely as UI by switching the $process_locally flag to false (it will post to that page).
 */
$process_locally = true;

if ( isset( $_POST['zc_dmt_action'] ) && $_POST['zc_dmt_action'] === 'add_source' ) {
    check_admin_referer( 'zc_dmt_add_source', 'zc_dmt_add_nonce' );

    $source_type = isset($_POST['source_type']) ? sanitize_key( wp_unslash($_POST['source_type']) ) : '';
    $source_name = isset($_POST['source_name']) ? sanitize_text_field( wp_unslash($_POST['source_name']) ) : '';
    $source_slug = isset($_POST['source_slug']) ? sanitize_title( wp_unslash($_POST['source_slug']) ) : '';

    $config = array();
    if ( isset( $supported_sources[ $source_type ]['fields'] ) ) {
        foreach ( $supported_sources[ $source_type ]['fields'] as $f ) {
            $k = $f['key'];
            if ( $k === 'api_key' && empty( $_POST[ $k ] ) ) {
                // try fallback from saved settings
                $opt_key = "{$source_type}_api_key";
                $config[ $k ] = isset( $zc_dmt_api_settings[ $opt_key ] ) ? sanitize_text_field( $zc_dmt_api_settings[ $opt_key ] ) : '';
            } else {
                $val = isset($_POST[ $k ]) ? wp_unslash($_POST[ $k ]) : '';
                $config[ $k ] = is_array($val) ? array_map( 'sanitize_text_field', $val ) : sanitize_text_field( $val );
            }
        }
    }

    if ( $process_locally ) {
        $result = new WP_Error( 'not_saved', esc_html__( 'No handler found to save this source.', 'zc-dmt' ) );

        // Prefer a dedicated Data Sources class if available
        if ( class_exists( 'ZC_DMT_Data_Sources' ) ) {
            $ds = new ZC_DMT_Data_Sources();
            if ( method_exists( $ds, 'add_source' ) ) {
                $result = $ds->add_source( array(
                    'type'   => $source_type,
                    'name'   => $source_name,
                    'slug'   => $source_slug,
                    'config' => $config,
                ) );
            } elseif ( method_exists( $ds, 'add' ) ) {
                $result = $ds->add( array(
                    'type'   => $source_type,
                    'name'   => $source_name,
                    'slug'   => $source_slug,
                    'config' => $config,
                ) );
            }
        }

        // Fallback to Indicators if your project hooked "sources" into indicators (legacy)
        if ( is_wp_error( $result ) && class_exists( 'ZC_DMT_Indicators' ) ) {
            $ind = new ZC_DMT_Indicators();
            if ( method_exists( $ind, 'add_indicator' ) ) {
                $payload = array(
                    'type'   => $source_type,
                    'name'   => $source_name,
                    'slug'   => $source_slug,
                    'config' => $config,
                );
                $result = $ind->add_indicator( $payload );
            }
        }

        if ( is_wp_error( $result ) ) {
            add_settings_error( 'zc_dmt_add_source', 'error', esc_html( $result->get_error_message() ), 'error' );
        } else {
            add_settings_error( 'zc_dmt_add_source', 'added', esc_html__( 'Data source added successfully.', 'zc-dmt' ), 'updated' );
        }
    } else {
        // Post to legacy add-source page (ensure your menu slug matches)
        $legacy_url = add_query_arg(
            array( 'page' => 'zc-dmt-add-source', 'type' => $source_type ),
            admin_url( 'admin.php' )
        );
        wp_redirect( $legacy_url );
        exit;
    }
}

settings_errors( 'zc_dmt_api_settings' );
settings_errors( 'zc_dmt_add_source' );

?>
<div class="wrap">
    <h1><?php esc_html_e( 'Data Sources', 'zc-dmt' ); ?></h1>

    <div id="zc-dmt-add-box" class="card" style="padding:16px; margin-top:16px;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'Add New Data Source', 'zc-dmt' ); ?></h2>

        <div style="max-width:520px;">
            <label for="zc_dmt_source_type"><strong><?php esc_html_e( 'Source Type', 'zc-dmt' ); ?></strong></label><br/>
            <select id="zc_dmt_source_type" class="regular-text" style="min-width:320px;">
                <option value=""><?php esc_html_e( 'Select Source Type', 'zc-dmt' ); ?></option>
                <?php foreach ( $supported_sources as $id => $def ) : ?>
                    <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $def['label'] ); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e( 'Select the type of data source you want to add', 'zc-dmt' ); ?></p>
        </div>

        <form id="zc_dmt_add_form" method="post" action="">
            <?php wp_nonce_field( 'zc_dmt_add_source', 'zc_dmt_add_nonce' ); ?>
            <input type="hidden" name="zc_dmt_action" value="add_source"/>
            <input type="hidden" id="zc_dmt_selected_type" name="source_type" value=""/>

            <div style="display:flex; gap:24px; flex-wrap:wrap; margin-top:16px;">
                <div style="min-width:320px;">
                    <label for="zc_dmt_source_name"><strong><?php esc_html_e( 'Source Name', 'zc-dmt' ); ?></strong></label><br/>
                    <input type="text" id="zc_dmt_source_name" name="source_name" class="regular-text" placeholder="e.g., CPI (FRED)"/>
                </div>

                <div style="min-width:320px;">
                    <label for="zc_dmt_source_slug"><strong><?php esc_html_e( 'Slug', 'zc-dmt' ); ?></strong></label><br/>
                    <input type="text" id="zc_dmt_source_slug" name="source_slug" class="regular-text" placeholder="e.g., cpi-fred"/>
                </div>
            </div>

            <div id="zc_dmt_dynamic_fields" style="margin-top:16px;">
                <!-- dynamic config fields will appear here -->
            </div>

            <p style="margin-top:16px;">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Add Source', 'zc-dmt' ); ?></button>
            </p>
        </form>
    </div>

    <div id="zc-dmt-configured" class="card" style="padding:16px; margin-top:16px;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'Configured Data Sources', 'zc-dmt' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Your configured data sources will appear here (listing handled elsewhere).', 'zc-dmt' ); ?></p>
    </div>

    <div id="zc-dmt-api-settings" class="card" style="padding:16px; margin-top:16px;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'API Settings (Saved Once)', 'zc-dmt' ); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'zc_dmt_save_api_settings', 'zc_dmt_api_nonce' ); ?>
            <input type="hidden" name="zc_dmt_action" value="save_api_settings"/>

            <div style="display:flex; flex-wrap:wrap; gap:24px;">
                <div>
                    <label for="fred_api_key"><strong>FRED API Key</strong></label><br/>
                    <input type="password" id="fred_api_key" name="fred_api_key" class="regular-text" value="<?php echo esc_attr( $zc_dmt_api_settings['fred_api_key'] ?? '' ); ?>"/>
                </div>
                <div>
                    <label for="bls_api_key"><strong>BLS API Key</strong></label><br/>
                    <input type="password" id="bls_api_key" name="bls_api_key" class="regular-text" value="<?php echo esc_attr( $zc_dmt_api_settings['bls_api_key'] ?? '' ); ?>"/>
                </div>
                <div>
                    <label for="google_api_key"><strong>Google API Key</strong></label><br/>
                    <input type="password" id="google_api_key" name="google_api_key" class="regular-text" value="<?php echo esc_attr( $zc_dmt_api_settings['google_api_key'] ?? '' ); ?>"/>
                </div>
                <div>
                    <label for="yahoo_api_key"><strong>Yahoo Finance API Key</strong></label><br/>
                    <input type="password" id="yahoo_api_key" name="yahoo_api_key" class="regular-text" value="<?php echo esc_attr( $zc_dmt_api_settings['yahoo_api_key'] ?? '' ); ?>"/>
                </div>
                <div>
                    <label for="worldbank_api_key"><strong>World Bank API Key</strong></label><br/>
                    <input type="password" id="worldbank_api_key" name="worldbank_api_key" class="regular-text" value="<?php echo esc_attr( $zc_dmt_api_settings['worldbank_api_key'] ?? '' ); ?>"/>
                </div>
                <div>
                    <label for="oecd_api_key"><strong>OECD API Key</strong></label><br/>
                    <input type="password" id="oecd_api_key" name="oecd_api_key" class="regular-text" value="<?php echo esc_attr( $zc_dmt_api_settings['oecd_api_key'] ?? '' ); ?>"/>
                </div>
                <div>
                    <label for="eurostat_api_key"><strong>Eurostat API Key</strong></label><br/>
                    <input type="password" id="eurostat_api_key" name="eurostat_api_key" class="regular-text" value="<?php echo esc_attr( $zc_dmt_api_settings['eurostat_api_key'] ?? '' ); ?>"/>
                </div>
            </div>

            <p style="margin-top:16px;">
                <button type="submit" class="button button-secondary"><?php esc_html_e( 'Save API Settings', 'zc-dmt' ); ?></button>
            </p>
        </form>
        <p class="description"><?php esc_html_e( 'Saved keys will auto-fill relevant fields in the Add form to avoid re-typing.', 'zc-dmt' ); ?></p>
    </div>

    <div id="zc-dmt-supported" class="card" style="padding:16px; margin-top:16px;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'Supported Data Sources', 'zc-dmt' ); ?></h2>
        <div style="display:flex; flex-wrap:wrap; gap:16px;">
            <?php foreach ( $supported_sources as $id => $def ) : ?>
                <div class="card" style="width:320px; padding:16px;">
                    <h3 style="margin-top:0;"><?php echo esc_html( $def['label'] ); ?></h3>
                    <?php if ( ! empty( $def['fields'] ) ) : ?>
                        <p><strong><?php esc_html_e( 'Configuration fields:', 'zc-dmt' ); ?></strong></p>
                        <ul style="list-style:disc; padding-left:18px;">
                            <?php foreach ( $def['fields'] as $f ) : ?>
                                <li><?php echo esc_html( $f['label'] ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
(function() {
    const sourceSelect = document.getElementById('zc_dmt_source_type');
    const selectedType = document.getElementById('zc_dmt_selected_type');
    const fieldsHost   = document.getElementById('zc_dmt_dynamic_fields');

    // PHP -> JS map of supported sources
    const SOURCES = <?php
        $js = array();
        foreach ( $supported_sources as $id => $def ) {
            $js[$id] = array(
                'label'  => $def['label'],
                'fields' => array_map( function( $f ) {
                    return array(
                        'key'      => $f['key'],
                        'label'    => $f['label'],
                        'type'     => $f['type'],
                        'required' => ! empty( $f['required'] ),
                        'prefill'  => isset( $f['prefill'] ) ? $f['prefill'] : '',
                    );
                }, $def['fields'] ?? array() ),
            );
        }
        echo wp_json_encode( $js );
    ?>;

    function renderFields(type) {
        fieldsHost.innerHTML = '';
        if (!type || !SOURCES[type]) return;

        // Hidden input to keep the type in POST
        selectedType.value = type;

        const wrap = document.createElement('div');
        wrap.style.display = 'grid';
        wrap.style.gridTemplateColumns = 'repeat(auto-fit, minmax(280px, 1fr))';
        wrap.style.gap = '16px';

        // Build config fields
        (SOURCES[type].fields || []).forEach(field => {
            const block = document.createElement('div');

            const label = document.createElement('label');
            label.innerHTML = '<strong>' + field.label + (field.required ? ' *' : '') + '</strong>';
            block.appendChild(label);
            block.appendChild(document.createElement('br'));

            let input;
            if (field.type === 'textarea') {
                input = document.createElement('textarea');
                input.rows = 3;
            } else {
                input = document.createElement('input');
                input.type = field.type || 'text';
            }
            input.className = 'regular-text';
            input.name = field.key;
            input.placeholder = field.label;
            if (field.prefill) input.value = field.prefill;
            if (field.required) input.required = true;

            block.appendChild(input);
            wrap.appendChild(block);
        });

        fieldsHost.appendChild(wrap);
    }

    sourceSelect && sourceSelect.addEventListener('change', function(e) {
        renderFields(e.target.value);
    });
})();
</script>
