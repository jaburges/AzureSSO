<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Azure Plugin - Main Settings</h1>
    
    <div class="azure-plugin-dashboard">
        <div class="azure-plugin-modules">
            <h2>Module Status</h2>
            
            <div class="module-cards">
                <div class="module-card <?php echo $settings['enable_sso'] ? 'enabled' : 'disabled'; ?>">
                    <div class="module-header">
                        <h3><span class="dashicons dashicons-admin-users"></span> SSO Authentication</h3>
                        <div class="module-controls">
                            <label class="switch">
                                <input type="checkbox" class="module-toggle" data-module="sso" <?php checked($settings['enable_sso']); ?> />
                                <span class="slider"></span>
                            </label>
                            <a href="<?php echo admin_url('admin.php?page=azure-plugin-sso'); ?>" class="button button-configure">Configure</a>
                        </div>
                    </div>
                    <div class="module-description">
                        <p>Enable Azure AD Single Sign-On for user authentication.</p>
                    </div>
                </div>
                
                <div class="module-card <?php echo $settings['enable_backup'] ? 'enabled' : 'disabled'; ?>">
                    <div class="module-header">
                        <h3><span class="dashicons dashicons-backup"></span> Azure Backup</h3>
                        <div class="module-controls">
                            <label class="switch">
                                <input type="checkbox" class="module-toggle" data-module="backup" <?php checked($settings['enable_backup']); ?> />
                                <span class="slider"></span>
                            </label>
                            <a href="<?php echo admin_url('admin.php?page=azure-plugin-backup'); ?>" class="button button-configure">Configure</a>
                        </div>
                    </div>
                    <div class="module-description">
                        <p>Backup your WordPress site to Azure Blob Storage.</p>
                    </div>
                </div>
                
                <div class="module-card <?php echo $settings['enable_calendar'] ? 'enabled' : 'disabled'; ?>">
                    <div class="module-header">
                        <h3><span class="dashicons dashicons-calendar-alt"></span> Calendar Embed</h3>
                        <div class="module-controls">
                            <label class="switch">
                                <input type="checkbox" class="module-toggle" data-module="calendar" <?php checked($settings['enable_calendar']); ?> />
                                <span class="slider"></span>
                            </label>
                            <a href="<?php echo admin_url('admin.php?page=azure-plugin-calendar'); ?>" class="button button-configure">Configure</a>
                        </div>
                    </div>
                    <div class="module-description">
                        <p>Embed Microsoft Outlook calendars in your website.</p>
                    </div>
                </div>
                
                <div class="module-card <?php echo $settings['enable_email'] ? 'enabled' : 'disabled'; ?>">
                    <div class="module-header">
                        <h3><span class="dashicons dashicons-email-alt"></span> Email Sender</h3>
                        <div class="module-controls">
                            <label class="switch">
                                <input type="checkbox" class="module-toggle" data-module="email" <?php checked($settings['enable_email']); ?> />
                                <span class="slider"></span>
                            </label>
                            <a href="<?php echo admin_url('admin.php?page=azure-plugin-email'); ?>" class="button button-configure">Configure</a>
                        </div>
                    </div>
                    <div class="module-description">
                        <p>Send emails through Microsoft Graph API.</p>
                    </div>
                </div>
                
                <div class="module-card <?php echo $settings['enable_pta'] ? 'enabled' : 'disabled'; ?>">
                    <div class="module-header">
                        <h3><span class="dashicons dashicons-networking"></span> PTA Roles Manager</h3>
                        <div class="module-controls">
                            <label class="switch">
                                <input type="checkbox" class="module-toggle" data-module="pta" <?php checked($settings['enable_pta']); ?> />
                                <span class="slider"></span>
                            </label>
                            <a href="<?php echo admin_url('admin.php?page=azure-plugin-pta'); ?>" class="button button-configure">Configure</a>
                        </div>
                    </div>
                    <div class="module-description">
                        <p>Manage PTA organizational structure with Azure AD sync.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="azure-plugin-settings">
            <form method="post" action="">
                <?php wp_nonce_field('azure_plugin_settings'); ?>
                
                <!-- Hidden inputs to mirror module toggle states -->
                <input type="hidden" name="enable_sso" id="hidden_enable_sso" value="<?php echo $settings['enable_sso'] ? '1' : '0'; ?>" />
                <input type="hidden" name="enable_backup" id="hidden_enable_backup" value="<?php echo $settings['enable_backup'] ? '1' : '0'; ?>" />
                <input type="hidden" name="enable_calendar" id="hidden_enable_calendar" value="<?php echo $settings['enable_calendar'] ? '1' : '0'; ?>" />
                <input type="hidden" name="enable_email" id="hidden_enable_email" value="<?php echo $settings['enable_email'] ? '1' : '0'; ?>" />
                <input type="hidden" name="enable_pta" id="hidden_enable_pta" value="<?php echo $settings['enable_pta'] ? '1' : '0'; ?>" />
                
                <div class="credentials-section">
                    <h2>Azure Credentials</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Use Common Credentials</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="use_common_credentials" id="use_common_credentials" <?php checked($settings['use_common_credentials'] ?? true); ?> />
                                    Use the same Azure credentials for all enabled modules
                                </label>
                                <p class="description">When enabled, all modules will use the common credentials below. When disabled, each module can have its own credentials.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <div id="common-credentials" <?php echo !($settings['use_common_credentials'] ?? true) ? 'style="display:none;"' : ''; ?>>
                        <h3>Common Credentials</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Client ID</th>
                                <td>
                                    <input type="text" name="common_client_id" id="common_client_id" value="<?php echo esc_attr($settings['common_client_id'] ?? ''); ?>" class="regular-text" />
                                    <p class="description">Your Azure App Registration Client ID</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Client Secret</th>
                                <td>
                                    <input type="password" name="common_client_secret" id="common_client_secret" value="<?php echo esc_attr($settings['common_client_secret'] ?? ''); ?>" class="regular-text" />
                                    <p class="description">Your Azure App Registration Client Secret</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Tenant ID</th>
                                <td>
                                    <input type="text" name="common_tenant_id" id="common_tenant_id" value="<?php echo esc_attr($settings['common_tenant_id'] ?? 'common'); ?>" class="regular-text" />
                                    <p class="description">Your Azure Tenant ID (or 'common' for multi-tenant)</p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <button type="button" class="button test-credentials" 
                                        data-client-id-field="common_client_id" 
                                        data-client-secret-field="common_client_secret" 
                                        data-tenant-id-field="common_tenant_id">
                                        Test Credentials
                                    </button>
                                    <span class="credentials-status"></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="azure_plugin_submit" class="button-primary" value="Save Settings" />
                </p>
            </form>
        </div>
        
        <div class="azure-plugin-info">
            <div class="info-box">
                <h3>Quick Setup Guide</h3>
                <ol>
                    <li>Create an Azure App Registration in your Azure portal</li>
                    <li>Add the required API permissions for the modules you want to use</li>
                    <li>Copy the Client ID, Client Secret, and Tenant ID to the credentials section above</li>
                    <li>Enable the modules you want to use</li>
                    <li>Configure each module using the links above</li>
                </ol>
            </div>
            
            <div class="info-box">
                <h3>Required API Permissions</h3>
                <ul>
                    <li><strong>SSO:</strong> User.Read, openid, profile, email</li>
                    <li><strong>Backup:</strong> Files.ReadWrite.All (for backup storage)</li>
                    <li><strong>Calendar:</strong> Calendar.Read, Calendar.ReadWrite</li>
                    <li><strong>Email:</strong> Mail.Send, Mail.ReadWrite</li>
                </ul>
            </div>
            
            <div class="info-box">
                <h3>Support & Documentation</h3>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=azure-plugin-logs'); ?>" class="button">View Logs</a>
                    <a href="#" class="button" onclick="location.reload();">Refresh Status</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle common credentials toggle
    $('#use_common_credentials').change(function() {
        if ($(this).is(':checked')) {
            $('#common-credentials').slideDown();
        } else {
            $('#common-credentials').slideUp();
        }
    });
    
    // Handle module toggles
    $('.module-toggle').change(function() {
        var module = $(this).data('module');
        var enabled = $(this).is(':checked');
        var card = $(this).closest('.module-card');
        
        // Update hidden input to match toggle state
        $('#hidden_enable_' + module).val(enabled ? '1' : '0');
        
        // Send AJAX request
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_toggle_module',
            module: module,
            enabled: enabled,
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (response.success) {
                if (enabled) {
                    card.removeClass('disabled').addClass('enabled');
                } else {
                    card.removeClass('enabled').addClass('disabled');
                }
                console.log('Module ' + module + ' toggled successfully:', response.data);
            } else {
                // Revert toggle if AJAX failed
                $(this).prop('checked', !enabled);
                $('#hidden_enable_' + module).val(enabled ? '0' : '1');
                alert('Failed to toggle module: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            // Revert toggle if AJAX failed
            $(this).prop('checked', !enabled);
            $('#hidden_enable_' + module).val(enabled ? '0' : '1');
            alert('Failed to toggle module: Network error');
        });
    });
    
    // Handle credentials test
    $('.test-credentials').click(function() {
        var button = $(this);
        var status = button.siblings('.credentials-status');
        var clientIdField = $('#' + button.data('client-id-field'));
        var clientSecretField = $('#' + button.data('client-secret-field'));
        var tenantIdField = $('#' + button.data('tenant-id-field'));
        
        button.prop('disabled', true).text('Testing...');
        status.html('<span class="spinner is-active"></span>');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_test_credentials',
            client_id: clientIdField.val(),
            client_secret: clientSecretField.val(),
            tenant_id: tenantIdField.val(),
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            button.prop('disabled', false).text('Test Credentials');
            
            if (response.valid) {
                status.html('<span class="dashicons dashicons-yes-alt" style="color: green;"></span> ' + response.message);
            } else {
                status.html('<span class="dashicons dashicons-dismiss" style="color: red;"></span> ' + response.message);
            }
            
            setTimeout(function() {
                status.fadeOut();
            }, 5000);
        });
    });
});
</script>
