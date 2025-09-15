<?php
/**
 * ZC DMT API Keys Management Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    return;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Handle form submissions
$messages = array();

// Handle generating a new API key
if (isset($_POST['action']) && $_POST['action'] === 'generate_key' && 
    isset($_POST['zc_dmt_generate_key_nonce']) && 
    wp_verify_nonce($_POST['zc_dmt_generate_key_nonce'], 'zc_dmt_generate_key')) {
    
    $key_name = isset($_POST['key_name']) ? sanitize_text_field($_POST['key_name']) : '';
    
    if (empty($key_name)) {
        $messages[] = array(
            'type' => 'error',
            'text' => __('Key name is required.', 'zc-dmt')
        );
    } else {
        // Generate API key
        if (class_exists('ZC_DMT_Security')) {
            $security = ZC_DMT_Security::get_instance();
            if (method_exists($security, 'generate_api_key')) {
                $new_key = $security->generate_api_key($key_name);
                
                if (is_wp_error($new_key)) {
                    $messages[] = array(
                        'type' => 'error',
                        'text' => $new_key->get_error_message()
                    );
                } else {
                    $generated_key = $new_key['key'];
                    $messages[] = array(
                        'type' => 'success',
                        'text' => sprintf(__('API key "%s" generated successfully.', 'zc-dmt'), esc_html($key_name))
                    );
                }
            } else {
                $messages[] = array(
                    'type' => 'error',
                    'text' => __('API key generation method not found.', 'zc-dmt')
                );
            }
        } else {
            $messages[] = array(
                'type' => 'error',
                'text' => __('Security class not found.', 'zc-dmt')
            );
        }
    }
}

// Handle revoking an API key
if (isset($_POST['action']) && $_POST['action'] === 'revoke_key' && 
    isset($_POST['zc_dmt_revoke_key_nonce']) && 
    wp_verify_nonce($_POST['zc_dmt_revoke_key_nonce'], 'zc_dmt_revoke_key')) {
    
    $key_id = isset($_POST['key_id']) ? intval($_POST['key_id']) : 0;
    
    if ($key_id > 0) {
        // Revoke API key
        if (class_exists('ZC_DMT_Security')) {
            $security = ZC_DMT_Security::get_instance();
            if (method_exists($security, 'revoke_api_key')) {
                $result = $security->revoke_api_key($key_id);
                
                if (is_wp_error($result)) {
                    $messages[] = array(
                        'type' => 'error',
                        'text' => $result->get_error_message()
                    );
                } else {
                    $messages[] = array(
                        'type' => 'success',
                        'text' => __('API key revoked successfully.', 'zc-dmt')
                    );
                }
            } else {
                $messages[] = array(
                    'type' => 'error',
                    'text' => __('API key revoke method not found.', 'zc-dmt')
                );
            }
        } else {
            $messages[] = array(
                'type' => 'error',
                'text' => __('Security class not found.', 'zc-dmt')
            );
        }
    }
}

// Handle activating an API key
if (isset($_POST['action']) && $_POST['action'] === 'activate_key' && 
    isset($_POST['zc_dmt_activate_key_nonce']) && 
    wp_verify_nonce($_POST['zc_dmt_activate_key_nonce'], 'zc_dmt_activate_key')) {
    
    $key_id = isset($_POST['key_id']) ? intval($_POST['key_id']) : 0;
    
    if ($key_id > 0) {
        // Activate API key
        if (class_exists('ZC_DMT_Security')) {
            $security = ZC_DMT_Security::get_instance();
            if (method_exists($security, 'activate_api_key')) {
                $result = $security->activate_api_key($key_id);
                
                if (is_wp_error($result)) {
                    $messages[] = array(
                        'type' => 'error',
                        'text' => $result->get_error_message()
                    );
                } else {
                    $messages[] = array(
                        'type' => 'success',
                        'text' => __('API key activated successfully.', 'zc-dmt')
                    );
                }
            } else {
                $messages[] = array(
                    'type' => 'error',
                    'text' => __('API key activate method not found.', 'zc-dmt')
                );
            }
        } else {
            $messages[] = array(
                'type' => 'error',
                'text' => __('Security class not found.', 'zc-dmt')
            );
        }
    }
}

// Get all API keys
$api_keys = array();
if (class_exists('ZC_DMT_Security')) {
    $security = ZC_DMT_Security::get_instance();
    if (method_exists($security, 'get_all_keys')) {
        $api_keys = $security->get_all_keys();
    }
}
?>

<div class="wrap zc-dmt-api-management">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php foreach ($messages as $message): ?>
        <div class="notice notice-<?php echo esc_attr($message['type']); ?> is-dismissible">
            <p><?php echo esc_html($message['text']); ?></p>
        </div>
    <?php endforeach; ?>
    
    <div class="zc-api-keys-section">
        <h2><?php esc_html_e('Generate New API Key', 'zc-dmt'); ?></h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('zc_dmt_generate_key', 'zc_dmt_generate_key_nonce'); ?>
            <input type="hidden" name="action" value="generate_key">
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Key Name', 'zc-dmt'); ?></th>
                    <td>
                        <input type="text" name="key_name" id="key_name" class="regular-text" required>
                        <p class="description"><?php esc_html_e('Enter a descriptive name for this API key.', 'zc-dmt'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Generate Key', 'zc-dmt'), 'primary', 'generate_key'); ?>
        </form>
        
        <?php if (isset($generated_key)): ?>
            <div class="notice notice-success">
                <p><?php esc_html_e('Your new API key has been generated. Please copy it now as it will not be shown again:', 'zc-dmt'); ?></p>
                <input type="text" class="regular-text" value="<?php echo esc_attr($generated_key); ?>" readonly onclick="this.select();">
            </div>
        <?php endif; ?>
    </div>
    
    <div class="zc-api-keys-section">
        <h2><?php esc_html_e('Existing API Keys', 'zc-dmt'); ?></h2>
        
        <?php if (!empty($api_keys) && !is_wp_error($api_keys)) : ?>
            <table class="wp-list-table widefat fixed striped api-keys-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'zc-dmt'); ?></th>
                        <th><?php esc_html_e('Key Preview', 'zc-dmt'); ?></th>
                        <th><?php esc_html_e('Status', 'zc-dmt'); ?></th>
                        <th><?php esc_html_e('Created', 'zc-dmt'); ?></th>
                        <th><?php esc_html_e('Last Used', 'zc-dmt'); ?></th>
                        <th><?php esc_html_e('Actions', 'zc-dmt'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($api_keys as $key) : ?>
                        <tr>
                            <td><?php echo esc_html($key->name); ?></td>
                            <td>
                                <?php 
                                if (!empty($key->api_key_hash)) {
                                    echo esc_html(substr($key->api_key_hash, 0, 10) . '...' . substr($key->api_key_hash, -10));
                                } else {
                                    echo esc_html__('N/A', 'zc-dmt');
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($key->is_active) : ?>
                                    <span class="zc-status-active"><?php esc_html_e('Active', 'zc-dmt'); ?></span>
                                <?php else : ?>
                                    <span class="zc-status-inactive"><?php esc_html_e('Inactive', 'zc-dmt'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($key->created_at); ?></td>
                            <td>
                                <?php 
                                if (!empty($key->last_used)) {
                                    echo esc_html($key->last_used);
                                } else {
                                    echo esc_html__('Never', 'zc-dmt');
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($key->is_active) : ?>
                                    <form method="post" action="" style="display: inline;">
                                        <?php wp_nonce_field('zc_dmt_revoke_key', 'zc_dmt_revoke_key_nonce'); ?>
                                        <input type="hidden" name="action" value="revoke_key">
                                        <input type="hidden" name="key_id" value="<?php echo esc_attr($key->id); ?>">
                                        <?php submit_button(__('Revoke', 'zc-dmt'), 'small', 'revoke_key', false); ?>
                                    </form>
                                <?php else : ?>
                                    <form method="post" action="" style="display: inline;">
                                        <?php wp_nonce_field('zc_dmt_activate_key', 'zc_dmt_activate_key_nonce'); ?>
                                        <input type="hidden" name="action" value="activate_key">
                                        <input type="hidden" name="key_id" value="<?php echo esc_attr($key->id); ?>">
                                        <?php submit_button(__('Activate', 'zc-dmt'), 'small', 'activate_key', false); ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e('No API keys found.', 'zc-dmt'); ?></p>
        <?php endif; ?>
    </div>
</div>

<style>
.zc-api-keys-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.zc-api-keys-section h2 {
    margin-top: 0;
}

.zc-status-active {
    color: #00a32a;
    font-weight: bold;
}

.zc-status-inactive {
    color: #d63638;
    font-weight: bold;
}

.api-keys-table .zc-status-active,
.api-keys-table .zc-status-inactive {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    background: rgba(0, 163, 42, 0.1);
}

.api-keys-table .zc-status-inactive {
    background: rgba(214, 54, 56, 0.1);
}
</style>
