<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get OneDrive Media statistics
$stats = array();
if (class_exists('Azure_OneDrive_Media_Manager')) {
    $manager = Azure_OneDrive_Media_Manager::get_instance();
    $stats = $manager->get_sync_stats();
}

// Get settings
$settings = Azure_Settings::get_all_settings();
$use_common = $settings['use_common_credentials'] ?? true;

// Get authorized users
$authorized_users = array();
if (class_exists('Azure_OneDrive_Media_Auth')) {
    $auth = new Azure_OneDrive_Media_Auth();
    $authorized_users = $auth->get_authorized_users();
}

// Handle auth success message
$show_auth_success = isset($_GET['auth']) && $_GET['auth'] === 'success';
$has_auth = !empty($authorized_users);
?>

<div class="wrap">
    <h1>PTA Tools - OneDrive Media Settings</h1>
    
    <!-- Module Toggle Section -->
    <div class="module-status-section">
        <h2>Module Status</h2>
        <div class="module-toggle-card">
            <div class="module-info">
                <h3><span class="dashicons dashicons-cloud-upload"></span> OneDrive Media Module</h3>
                <p>Store WordPress media files in OneDrive/SharePoint for better organization and CDN performance</p>
            </div>
            <div class="module-control">
                <label class="switch">
                    <input type="checkbox" class="onedrive-media-module-toggle" <?php checked(Azure_Settings::is_module_enabled('onedrive_media')); ?> />
                    <span class="slider"></span>
                </label>
                <span class="toggle-status"><?php echo Azure_Settings::is_module_enabled('onedrive_media') ? 'Enabled' : 'Disabled'; ?></span>
            </div>
        </div>
        <?php if (!Azure_Settings::is_module_enabled('onedrive_media')): ?>
        <div class="notice notice-warning inline">
            <p><strong>OneDrive Media module is disabled.</strong> Enable it above or in the <a href="<?php echo admin_url('admin.php?page=azure-plugin'); ?>">main settings</a> to use this functionality.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($show_auth_success): ?>
    <div class="notice notice-success is-dismissible">
        <p><strong>Success!</strong> OneDrive authorization completed successfully. You can now sync media files.</p>
    </div>
    <?php endif; ?>
    
    <!-- Statistics Dashboard -->
    <div class="onedrive-media-dashboard">
        <h2>Storage Statistics</h2>
        
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-number"><?php echo intval($stats['total_files'] ?? 0); ?></div>
                <div class="stat-label">Total Files</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-number"><?php echo intval($stats['synced_files'] ?? 0); ?></div>
                <div class="stat-label">Synced</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-number"><?php echo intval($stats['pending_files'] ?? 0); ?></div>
                <div class="stat-label">Pending</div>
            </div>
            
            <div class="stat-card error">
                <div class="stat-number"><?php echo intval($stats['error_files'] ?? 0); ?></div>
                <div class="stat-label">Errors</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo size_format($stats['total_size'] ?? 0); ?></div>
                <div class="stat-label">Total Size</div>
            </div>
        </div>
    </div>
    
    <!-- Quick Sync Action -->
    <?php if ($has_auth): ?>
    <div class="onedrive-media-quick-sync">
        <h2>Manual Sync</h2>
        <button type="button" class="button button-primary sync-from-onedrive-btn">
            <span class="dashicons dashicons-update"></span>
            Sync from OneDrive Now
        </button>
        <p class="description">Manually trigger a sync to import files from OneDrive to WordPress Media Library</p>
    </div>
    <?php endif; ?>
    
    <!-- Settings Form -->
    <div class="onedrive-media-settings-section">
        <form method="post" action="">
            <?php wp_nonce_field('azure_plugin_settings'); ?>
            
            <!-- Step 1: Authorization -->
            <div class="onedrive-step-section">
                <h2><span class="step-number">1</span> Azure App Registration &amp; Authorization</h2>
                
                <!-- App Registration Credentials -->
                <div class="onedrive-media-credentials-section">
                    <h3>App Registration</h3>
                    
                    <?php if ($use_common): ?>
                    <div class="notice notice-info inline">
                        <p>
                            <strong>Using Common Credentials:</strong> This module is using the common Azure credentials configured in the 
                            <a href="<?php echo admin_url('admin.php?page=azure-plugin'); ?>">main settings</a>.
                            <?php if (!empty($settings['common_client_id'])): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> Credentials configured
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color: #f56e28;"></span> Please configure credentials in main settings
                            <?php endif; ?>
                        </p>
                        <p class="description">
                            To use separate credentials for OneDrive Media, uncheck "Use Common Credentials" in the 
                            <a href="<?php echo admin_url('admin.php?page=azure-plugin'); ?>">main settings</a>.
                        </p>
                    </div>
                    <?php else: ?>
                    <div class="notice notice-warning inline">
                        <p><strong>Module-Specific Credentials:</strong> Configure Azure app registration credentials specifically for OneDrive Media below.</p>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Client ID</th>
                            <td>
                                <input type="text" name="onedrive_media_client_id" id="onedrive_media_client_id" value="<?php echo esc_attr($settings['onedrive_media_client_id'] ?? ''); ?>" class="regular-text" />
                                <p class="description">Your Azure App Registration Client ID</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Client Secret</th>
                            <td>
                                <input type="password" name="onedrive_media_client_secret" id="onedrive_media_client_secret" value="<?php echo esc_attr($settings['onedrive_media_client_secret'] ?? ''); ?>" class="regular-text" />
                                <p class="description">Your Azure App Registration Client Secret</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Tenant ID</th>
                            <td>
                                <input type="text" name="onedrive_media_tenant_id" id="onedrive_media_tenant_id" value="<?php echo esc_attr($settings['onedrive_media_tenant_id'] ?? 'common'); ?>" class="regular-text" />
                                <p class="description">Your Azure Tenant ID (or 'common' for multi-tenant)</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="description">
                        <strong>Required API Permissions:</strong>
                        <br>• Files.ReadWrite.All - Read and write files in OneDrive
                        <br>• Sites.ReadWrite.All - Access SharePoint sites (if using SharePoint storage)
                    </p>
                    <?php endif; ?>
                </div>
                
                <!-- Authorization -->
                <div class="onedrive-media-auth-section">
                    <h3>User Authorization</h3>
                    
                    <?php if (!$use_common && empty($settings['onedrive_media_client_id'])): ?>
                    <div class="notice notice-error inline">
                        <p><strong>Configuration Required:</strong> Please configure your Azure app registration credentials above and save settings before authorizing.</p>
                    </div>
                    <?php elseif ($use_common && empty($settings['common_client_id'])): ?>
                    <div class="notice notice-error inline">
                        <p><strong>Configuration Required:</strong> Please configure common Azure credentials in the 
                        <a href="<?php echo admin_url('admin.php?page=azure-plugin'); ?>">main settings</a> before authorizing.</p>
                    </div>
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Authorized Users</th>
                            <td>
                                <?php if (!empty($authorized_users)): ?>
                                <div class="authorized-users">
                                    <?php foreach ($authorized_users as $user): ?>
                                    <div class="authorized-user" style="padding: 8px; background: #f0f0f1; margin-bottom: 8px; border-radius: 4px;">
                                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                        <strong><?php echo esc_html($user->user_email); ?></strong>
                                        <span style="margin-left: 10px; color: #666;">Expires: <?php echo esc_html($user->expires_at); ?></span>
                                        <button type="button" class="button button-small revoke-user-token" data-user-email="<?php echo esc_attr($user->user_email); ?>" style="margin-left: 10px;">Revoke</button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <p><em>No users have authorized OneDrive access yet.</em></p>
                                <?php endif; ?>
                                
                                <div class="auth-actions" style="margin-top: 10px;">
                                    <button type="button" class="button button-primary authorize-onedrive-user" 
                                        <?php if ((!$use_common && empty($settings['onedrive_media_client_id'])) || ($use_common && empty($settings['common_client_id']))): ?>
                                            disabled
                                        <?php endif; ?>
                                    >
                                        <span class="dashicons dashicons-admin-network"></span>
                                        Authorize OneDrive Access
                                    </button>
                                    
                                    <?php if ($has_auth): ?>
                                    <button type="button" class="button test-onedrive-connection" style="margin-left: 10px;">
                                        <span class="dashicons dashicons-admin-plugins"></span>
                                        Test Connection
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <p class="description">
                                    Click "Authorize OneDrive Access" to grant this WordPress site permission to access your OneDrive/SharePoint files.
                                    <?php if (!$has_auth): ?>You must authorize before you can configure storage settings below.<?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Step 2: Storage Configuration -->
            <div class="onedrive-step-section">
                <h2><span class="step-number">2</span> Storage Type &amp; Folder Configuration</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Storage Type</th>
                        <td>
                            <?php $storage_type = Azure_Settings::get_setting('onedrive_media_storage_type', 'onedrive'); ?>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="radio" name="onedrive_media_storage_type" value="onedrive" <?php checked($storage_type, 'onedrive'); ?> />
                                <strong>OneDrive for Business</strong> - Store files in your OneDrive
                            </label>
                            <label style="display: block;">
                                <input type="radio" name="onedrive_media_storage_type" value="sharepoint" <?php checked($storage_type, 'sharepoint'); ?> />
                                <strong>SharePoint Document Library</strong> - Store files in a SharePoint site
                            </label>
                            <p class="description">Choose where to store your media files.</p>
                        </td>
                    </tr>
                </table>
                
                <!-- SharePoint Settings (conditional) -->
                <div id="sharepoint-settings" style="<?php echo $storage_type === 'sharepoint' ? '' : 'display:none;'; ?>">
                    <h3>SharePoint Configuration</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">SharePoint Site URL</th>
                            <td>
                                <input type="url" name="onedrive_media_sharepoint_site_url" id="sharepoint_site_url" value="<?php echo esc_attr(Azure_Settings::get_setting('onedrive_media_sharepoint_site_url', '')); ?>" class="regular-text" placeholder="https://yourcompany.sharepoint.com/sites/yoursite" />
                                <p class="description">Enter your SharePoint site URL</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Site ID</th>
                            <td>
                                <?php $site_id = Azure_Settings::get_setting('onedrive_media_site_id', ''); ?>
                                <input type="text" name="onedrive_media_site_id" id="sharepoint_site_id" value="<?php echo esc_attr($site_id); ?>" class="regular-text" readonly />
                                <button type="button" class="button browse-sharepoint-sites" <?php echo !$has_auth ? 'disabled' : ''; ?>>
                                    <span class="dashicons dashicons-search"></span> Browse Sites
                                </button>
                                <p class="description">Select or enter SharePoint Site ID</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Drive ID</th>
                            <td>
                                <?php 
                                $drive_id = Azure_Settings::get_setting('onedrive_media_drive_id', '');
                                $drive_name = Azure_Settings::get_setting('onedrive_media_drive_name', '');
                                ?>
                                <input type="text" name="onedrive_media_drive_id" id="sharepoint_drive_id" value="<?php echo esc_attr($drive_id); ?>" class="regular-text" readonly />
                                <button type="button" class="button browse-sharepoint-drives" <?php echo (!$has_auth || empty($site_id)) ? 'disabled' : ''; ?>>
                                    <span class="dashicons dashicons-list-view"></span> Browse Drives
                                </button>
                                <input type="hidden" name="onedrive_media_drive_name" id="sharepoint_drive_name" value="<?php echo esc_attr($drive_name); ?>" />
                                <p class="description">Select or enter Document Library Drive ID</p>
                                <?php if (!empty($drive_name)): ?>
                                <p class="description" style="color: #46b450;">
                                    <span class="dashicons dashicons-yes-alt"></span> <strong>Selected:</strong> <?php echo esc_html($drive_name); ?>
                                </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Folder Configuration -->
                <h3>Folder Settings</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Base Folder</th>
                        <td>
                            <input type="text" name="onedrive_media_base_folder" id="onedrive_media_base_folder" value="<?php echo esc_attr(Azure_Settings::get_setting('onedrive_media_base_folder', 'WordPress Media')); ?>" class="regular-text" />
                            <button type="button" class="button browse-onedrive-folders" <?php echo !$has_auth ? 'disabled' : ''; ?>>
                                <span class="dashicons dashicons-open-folder"></span> Browse Folders
                            </button>
                            <p class="description">Base folder in OneDrive/SharePoint for media files</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Folder Organization</th>
                        <td>
                            <label>
                                <input type="checkbox" name="onedrive_media_use_year_folders" id="onedrive_media_use_year_folders" <?php checked(Azure_Settings::get_setting('onedrive_media_use_year_folders', true)); ?> />
                                <strong>Use year-based subfolders</strong> (Before 2024, 2024, 2025, etc.)
                            </label>
                            <p class="description">Organize media files in year-based subfolders for better management</p>
                            
                            <button type="button" class="button create-year-folders-btn" <?php echo !$has_auth ? 'disabled' : ''; ?> style="margin-top: 10px;">
                                <span class="dashicons dashicons-category"></span> Create Year Folders
                            </button>
                            <p class="description">Create "Before 2024", "2024", "2025"... up to current year folders in the base folder above</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Step 3: Sync Settings -->
            <div class="onedrive-step-section">
                <h2><span class="step-number">3</span> Sync Settings</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Auto-Sync</th>
                        <td>
                            <label>
                                <input type="checkbox" name="onedrive_media_auto_sync" <?php checked(Azure_Settings::get_setting('onedrive_media_auto_sync', false)); ?> />
                                <strong>Automatically sync files between WordPress and OneDrive</strong>
                            </label>
                            <p class="description">When enabled, files uploaded directly to OneDrive will appear in WordPress Media Library</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sync Frequency</th>
                        <td>
                            <select name="onedrive_media_sync_frequency">
                                <?php $sync_freq = Azure_Settings::get_setting('onedrive_media_sync_frequency', 'hourly'); ?>
                                <option value="hourly" <?php selected($sync_freq, 'hourly'); ?>>Hourly</option>
                                <option value="twicedaily" <?php selected($sync_freq, 'twicedaily'); ?>>Twice Daily</option>
                                <option value="daily" <?php selected($sync_freq, 'daily'); ?>>Daily</option>
                            </select>
                            <p class="description">How often to check for new files in OneDrive</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sync Direction</th>
                        <td>
                            <select name="onedrive_media_sync_direction">
                                <?php $sync_dir = Azure_Settings::get_setting('onedrive_media_sync_direction', 'two_way'); ?>
                                <option value="two_way" <?php selected($sync_dir, 'two_way'); ?>>Two-Way Sync (WordPress ↔ OneDrive)</option>
                                <option value="wp_to_onedrive" <?php selected($sync_dir, 'wp_to_onedrive'); ?>>WordPress → OneDrive Only</option>
                                <option value="onedrive_to_wp" <?php selected($sync_dir, 'onedrive_to_wp'); ?>>OneDrive → WordPress Only</option>
                            </select>
                            <p class="description">Control sync direction between WordPress and OneDrive</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Step 4: CDN & Public Access -->
            <div class="onedrive-step-section">
                <h2><span class="step-number">4</span> Public Access &amp; CDN</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Sharing Link Type</th>
                        <td>
                            <select name="onedrive_media_sharing_link_type">
                                <?php $link_type = Azure_Settings::get_setting('onedrive_media_sharing_link_type', 'anonymous'); ?>
                                <option value="anonymous" <?php selected($link_type, 'anonymous'); ?>>Anonymous (Public) - Anyone with link can access</option>
                                <option value="organization" <?php selected($link_type, 'organization'); ?>>Organization Only - Only your org members can access</option>
                            </select>
                            <p class="description">Type of sharing link to generate for public access to media files</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Link Expiration</th>
                        <td>
                            <select name="onedrive_media_link_expiration">
                                <?php $expiration = Azure_Settings::get_setting('onedrive_media_link_expiration', 'never'); ?>
                                <option value="never" <?php selected($expiration, 'never'); ?>>Never expire</option>
                                <option value="30" <?php selected($expiration, '30'); ?>>30 Days</option>
                                <option value="90" <?php selected($expiration, '90'); ?>>90 Days</option>
                                <option value="365" <?php selected($expiration, '365'); ?>>1 Year</option>
                            </select>
                            <p class="description">When sharing links should expire</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">CDN Optimization</th>
                        <td>
                            <label>
                                <input type="checkbox" name="onedrive_media_cdn_optimization" <?php checked(Azure_Settings::get_setting('onedrive_media_cdn_optimization', true)); ?> />
                                <strong>Enable CDN optimization for faster delivery</strong>
                            </label>
                            <p class="description">Leverage Microsoft's global CDN for faster media delivery worldwide</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Step 5: Media Library Options -->
            <div class="onedrive-step-section">
                <h2><span class="step-number">5</span> Media Library Options</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">OneDrive Badge</th>
                        <td>
                            <label>
                                <input type="checkbox" name="onedrive_media_show_badge" <?php checked(Azure_Settings::get_setting('onedrive_media_show_badge', true)); ?> />
                                Display OneDrive badge on media files in WordPress Media Library
                            </label>
                            <p class="description">Show a visual indicator for files stored in OneDrive</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Local Storage</th>
                        <td>
                            <label>
                                <input type="checkbox" name="onedrive_media_keep_local_copies" <?php checked(Azure_Settings::get_setting('onedrive_media_keep_local_copies', false)); ?> />
                                Keep local copies of files (uses more server storage)
                            </label>
                            <p class="description">By default, files are deleted locally after upload to OneDrive to save server space</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Step 6: Advanced Options -->
            <div class="onedrive-step-section">
                <h2><span class="step-number">6</span> Advanced Options</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Maximum File Size</th>
                        <td>
                            <input type="number" name="onedrive_media_max_file_size" value="<?php echo esc_attr(Azure_Settings::get_setting('onedrive_media_max_file_size', 4294967296)); ?>" class="small-text" />
                            bytes (<?php echo size_format(Azure_Settings::get_setting('onedrive_media_max_file_size', 4294967296)); ?>)
                            <p class="description">Maximum file size for uploads. OneDrive limit: 4GB (4294967296 bytes)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Upload Chunk Size</th>
                        <td>
                            <input type="number" name="onedrive_media_chunk_size" value="<?php echo esc_attr(Azure_Settings::get_setting('onedrive_media_chunk_size', 10485760)); ?>" class="small-text" />
                            bytes (<?php echo size_format(Azure_Settings::get_setting('onedrive_media_chunk_size', 10485760)); ?>)
                            <p class="description">Chunk size for resumable uploads of large files. Default: 10MB (10485760 bytes)</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <input type="submit" name="azure_plugin_submit" class="button-primary" value="Save Settings" />
            </p>
        </form>
    </div>
</div>

<!-- SharePoint Site Browser Modal -->
<div id="sharepoint-site-browser-modal" style="display: none;">
    <div class="onedrive-folder-browser">
        <div class="folder-browser-header">
            <h3>Select SharePoint Site</h3>
            <button type="button" class="close-site-browser">×</button>
        </div>
        <div class="folder-browser-body">
            <p class="description" style="margin-bottom: 15px;">Select which SharePoint site to use for media storage:</p>
            <div class="site-list" id="site-list">
                <p>Loading SharePoint sites...</p>
            </div>
        </div>
        <div class="folder-browser-footer">
            <button type="button" class="button close-site-browser">Cancel</button>
        </div>
    </div>
</div>

<!-- Document Library Browser Modal -->
<div id="sharepoint-library-browser-modal" style="display: none;">
    <div class="onedrive-folder-browser">
        <div class="folder-browser-header">
            <h3>Select Document Library</h3>
            <button type="button" class="close-library-browser">×</button>
        </div>
        <div class="folder-browser-body">
            <p class="description" style="margin-bottom: 15px;">Select which SharePoint document library to use for media storage:</p>
            <div class="library-list" id="library-list">
                <p>Loading document libraries...</p>
            </div>
        </div>
        <div class="folder-browser-footer">
            <button type="button" class="button close-library-browser">Cancel</button>
        </div>
    </div>
</div>

<!-- Folder Browser Modal -->
<div id="onedrive-folder-browser-modal" style="display: none;">
    <div class="onedrive-folder-browser">
        <div class="folder-browser-header">
            <h3>Browse OneDrive Folders</h3>
            <button type="button" class="close-folder-browser">×</button>
        </div>
        <div class="folder-browser-body">
            <div class="current-path">
                <strong>Current Path:</strong> <span id="current-folder-path">/</span>
            </div>
            <div class="folder-list" id="folder-list">
                <p>Loading folders...</p>
            </div>
        </div>
        <div class="folder-browser-footer">
            <button type="button" class="button button-primary select-folder">Select This Folder</button>
            <button type="button" class="button close-folder-browser">Cancel</button>
        </div>
    </div>
</div>

<style>
.onedrive-step-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.onedrive-step-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #0073aa;
}

.step-number {
    display: inline-block;
    width: 32px;
    height: 32px;
    line-height: 32px;
    text-align: center;
    background: #0073aa;
    color: #fff;
    border-radius: 50%;
    margin-right: 10px;
    font-weight: bold;
}

.onedrive-step-section h3 {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
}

.onedrive-step-section:first-of-type h3:first-of-type {
    margin-top: 0;
    padding-top: 0;
    border-top: none;
}

#onedrive-folder-browser-modal,
#sharepoint-site-browser-modal,
#sharepoint-library-browser-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.onedrive-folder-browser {
    background: #fff;
    width: 600px;
    max-width: 90%;
    max-height: 80vh;
    border-radius: 4px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    display: flex;
    flex-direction: column;
}

.folder-browser-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.folder-browser-header h3 {
    margin: 0;
}

.close-folder-browser,
.close-site-browser,
.close-library-browser {
    background: none;
    border: none;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
    color: #666;
}

.close-folder-browser:hover,
.close-site-browser:hover,
.close-library-browser:hover {
    color: #000;
}

.folder-browser-body {
    padding: 20px;
    flex: 1;
    overflow-y: auto;
}

.current-path {
    padding: 10px;
    background: #f0f0f1;
    border-radius: 4px;
    margin-bottom: 15px;
}

.folder-list {
    max-height: 400px;
    overflow-y: auto;
}

.folder-item {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
}

.folder-item:hover {
    background: #f0f0f1;
}

.folder-item .dashicons {
    margin-right: 10px;
    color: #0073aa;
}

.folder-browser-footer {
    padding: 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}

.folder-browser-footer .button {
    margin-left: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    var currentFolderPath = '/';
    
    // Module toggle
    $('.onedrive-media-module-toggle').change(function() {
        var enabled = $(this).is(':checked');
        
        $.post(ajaxurl, {
            action: 'azure_toggle_module',
            module: 'onedrive_media',
            enabled: enabled,
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (response.success) {
                $('.toggle-status').text(enabled ? 'Enabled' : 'Disabled');
                location.reload();
            } else {
                alert('Failed to toggle module');
                $('.onedrive-media-module-toggle').prop('checked', !enabled);
            }
        });
    });
    
    // Storage type change
    $('input[name="onedrive_media_storage_type"]').change(function() {
        if ($(this).val() === 'sharepoint') {
            $('#sharepoint-settings').slideDown();
        } else {
            $('#sharepoint-settings').slideUp();
        }
    });
    
    // Resolve SharePoint site
    $('.resolve-sharepoint-site').click(function() {
        var siteUrl = $('#sharepoint_site_url').val().trim();
        
        if (!siteUrl) {
            alert('Please enter a SharePoint site URL first');
            return;
        }
        
        var $btn = $(this);
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Connecting...');
        
        $.post(ajaxurl, {
            action: 'onedrive_media_resolve_sharepoint_site',
            site_url: siteUrl,
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            $btn.prop('disabled', false).html(originalHtml);
            
            if (response.success) {
                $('#sharepoint_site_id').val(response.data.site_id);
                alert('Connected to site successfully!\n\nSite: ' + response.data.site_name + '\nSite ID: ' + response.data.site_id);
                location.reload();
            } else {
                alert('Failed to connect to site: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            $btn.prop('disabled', false).html(originalHtml);
            alert('Connection request failed. Please try again.');
        });
    });
    
    // Browse SharePoint sites
    $('.browse-sharepoint-sites').click(function() {
        $('#sharepoint-site-browser-modal').fadeIn();
        loadSharePointSites();
    });
    
    // Close site browser
    $('.close-site-browser').click(function() {
        $('#sharepoint-site-browser-modal').fadeOut();
    });
    
    // Load SharePoint sites
    function loadSharePointSites() {
        var siteUrl = $('#sharepoint_site_url').val().trim();
        
        $('#site-list').html('<p>Loading SharePoint sites...</p>');
        
        $.post(ajaxurl, {
            action: 'onedrive_media_list_sharepoint_sites',
            site_url: siteUrl,
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (response.success && response.data.sites) {
                var html = '';
                
                if (response.data.sites.length === 0) {
                    html = '<p><em>No SharePoint sites found.</em></p>';
                } else {
                    response.data.sites.forEach(function(site) {
                        html += '<div class="folder-item site-item" data-site-id="' + site.id + '" data-site-name="' + site.name + '" data-site-url="' + site.webUrl + '">';
                        html += '<span class="dashicons dashicons-admin-multisite"></span> ';
                        html += '<strong>' + site.name + '</strong>';
                        if (site.description) {
                            html += '<br><span style="color: #666; font-size: 12px; margin-left: 28px;">' + site.description + '</span>';
                        }
                        html += '<br><span style="color: #888; font-size: 11px; margin-left: 28px;">' + site.webUrl + '</span>';
                        html += '</div>';
                    });
                }
                
                $('#site-list').html(html);
                
                // Handle site selection
                $('.site-item').click(function() {
                    var siteId = $(this).data('site-id');
                    var siteName = $(this).data('site-name');
                    var siteUrl = $(this).data('site-url');
                    
                    $('#sharepoint_site_id').val(siteId);
                    $('#sharepoint_site_url').val(siteUrl);
                    
                    $('#sharepoint-site-browser-modal').fadeOut();
                    alert('Selected site: ' + siteName + '\n\nPlease save settings to persist this selection.');
                    
                    // Enable the Browse Drives button
                    $('.browse-sharepoint-drives').prop('disabled', false);
                });
            } else {
                $('#site-list').html('<p class="error">Failed to load SharePoint sites: ' + (response.data || 'Unknown error') + '</p>');
            }
        }).fail(function() {
            $('#site-list').html('<p class="error">Failed to connect to SharePoint. Please check your authorization.</p>');
        });
    }
    
    // Browse SharePoint drives (document libraries)
    $('.browse-sharepoint-drives').click(function() {
        $('#sharepoint-library-browser-modal').fadeIn();
        loadSharePointLibraries();
    });
    
    // Close library browser
    $('.close-library-browser').click(function() {
        $('#sharepoint-library-browser-modal').fadeOut();
    });
    
    // Load SharePoint document libraries
    function loadSharePointLibraries() {
        var siteId = $('#sharepoint_site_id').val();
        
        if (!siteId) {
            $('#library-list').html('<p class="error">Site ID not found. Please connect to a SharePoint site first.</p>');
            return;
        }
        
        $('#library-list').html('<p>Loading document libraries...</p>');
        
        $.post(ajaxurl, {
            action: 'onedrive_media_list_sharepoint_drives',
            site_id: siteId,
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (response.success && response.data.drives) {
                var html = '';
                
                if (response.data.drives.length === 0) {
                    html = '<p><em>No document libraries found in this site.</em></p>';
                } else {
                    response.data.drives.forEach(function(drive) {
                        html += '<div class="folder-item library-item" data-drive-id="' + drive.id + '" data-drive-name="' + drive.name + '">';
                        html += '<span class="dashicons dashicons-portfolio"></span> ';
                        html += '<strong>' + drive.name + '</strong>';
                        if (drive.description) {
                            html += ' <span style="color: #666; font-size: 12px;">(' + drive.description + ')</span>';
                        }
                        html += '</div>';
                    });
                }
                
                $('#library-list').html(html);
                
                // Handle library selection
                $('.library-item').click(function() {
                    var driveId = $(this).data('drive-id');
                    var driveName = $(this).data('drive-name');
                    
                    $('#sharepoint_drive_id').val(driveId);
                    $('#sharepoint_drive_name').val(driveName);
                    
                    $('#sharepoint-library-browser-modal').fadeOut();
                    alert('Selected document library: ' + driveName + '\n\nPlease save settings to persist this selection.');
                });
            } else {
                $('#library-list').html('<p class="error">Failed to load document libraries: ' + (response.data || 'Unknown error') + '</p>');
            }
        }).fail(function() {
            $('#library-list').html('<p class="error">Failed to connect to SharePoint. Please check your authorization.</p>');
        });
    }
    
    // Authorize OneDrive
    $('.authorize-onedrive-user').click(function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'azure_onedrive_authorize',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (response.success) {
                window.location.href = response.data.auth_url;
            } else {
                alert('Authorization failed: ' + (response.data || 'Unknown error'));
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Sync from OneDrive
    $('.sync-from-onedrive-btn').click(function() {
        var $btn = $(this);
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: rotation 2s infinite linear;"></span> Syncing...');
        
        $.post(ajaxurl, {
            action: 'onedrive_media_sync_from_onedrive',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            $btn.prop('disabled', false).html(originalHtml);
            
            if (response.success) {
                alert('Sync completed: ' + response.data.message);
                location.reload();
            } else {
                alert('Sync failed: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            $btn.prop('disabled', false).html(originalHtml);
            alert('Sync request failed. Please try again.');
        });
    });
    
    // Test connection
    $('.test-onedrive-connection').click(function() {
        var $btn = $(this);
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Testing...');
        
        $.post(ajaxurl, {
            action: 'onedrive_media_test_connection',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            $btn.prop('disabled', false).html(originalHtml);
            
            if (response.success) {
                alert('Connection successful: ' + response.data);
            } else {
                alert('Connection failed: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            $btn.prop('disabled', false).html(originalHtml);
            alert('Connection test failed. Please try again.');
        });
    });
    
    // Browse OneDrive/SharePoint folders
    $('.browse-onedrive-folders').click(function() {
        currentFolderPath = '/';
        $('#current-folder-path').text(currentFolderPath);
        $('#onedrive-folder-browser-modal').fadeIn();
        loadFolders(currentFolderPath);
    });
    
    // Close folder browser
    $('.close-folder-browser').click(function() {
        $('#onedrive-folder-browser-modal').fadeOut();
    });
    
    // Select folder
    $('.select-folder').click(function() {
        $('#onedrive_media_base_folder').val(currentFolderPath === '/' ? '' : currentFolderPath);
        $('#onedrive-folder-browser-modal').fadeOut();
    });
    
    // Load folders from OneDrive or SharePoint
    function loadFolders(path) {
        $('#folder-list').html('<p>Loading folders...</p>');
        
        // Check if SharePoint storage is selected
        var storageType = $('input[name="onedrive_media_storage_type"]:checked').val();
        var siteId = $('#sharepoint_site_id').val();
        var driveId = $('#sharepoint_drive_id').val();
        
        var ajaxData = {
            action: 'onedrive_media_browse_folders',
            path: path,
            storage_type: storageType,
            nonce: azure_plugin_ajax.nonce
        };
        
        // Add SharePoint-specific parameters if SharePoint is selected
        if (storageType === 'sharepoint') {
            if (!siteId || !driveId) {
                $('#folder-list').html('<p class="error">Please select a SharePoint site and document library first.</p>');
                return;
            }
            ajaxData.site_id = siteId;
            ajaxData.drive_id = driveId;
        }
        
        $.post(ajaxurl, ajaxData, function(response) {
            if (response.success && response.data.folders) {
                var html = '';
                
                // Add parent folder option if not at root
                if (path !== '/') {
                    html += '<div class="folder-item parent-folder" data-path=".."><span class="dashicons dashicons-arrow-up-alt"></span> <strong>.. (Parent folder)</strong></div>';
                }
                
                if (response.data.folders.length === 0) {
                    html += '<p><em>No subfolders found in this location.</em></p>';
                } else {
                    response.data.folders.forEach(function(folder) {
                        html += '<div class="folder-item" data-path="' + folder.path + '"><span class="dashicons dashicons-category"></span> ' + folder.name + '</div>';
                    });
                }
                
                $('#folder-list').html(html);
                
                // Handle folder clicks
                $('.folder-item').click(function() {
                    var folderPath = $(this).data('path');
                    if (folderPath === '..') {
                        // Go to parent
                        var parts = currentFolderPath.split('/').filter(Boolean);
                        parts.pop();
                        currentFolderPath = '/' + parts.join('/');
                        if (currentFolderPath !== '/') {
                            currentFolderPath += '/';
                        }
                    } else {
                        currentFolderPath = folderPath;
                    }
                    $('#current-folder-path').text(currentFolderPath);
                    loadFolders(currentFolderPath);
                });
            } else {
                $('#folder-list').html('<p class="error">Failed to load folders: ' + (response.data || 'Unknown error') + '</p>');
            }
        }).fail(function() {
            $('#folder-list').html('<p class="error">Failed to connect to ' + (storageType === 'sharepoint' ? 'SharePoint' : 'OneDrive') + '. Please check your authorization.</p>');
        });
    }
    
    // Create year folders
    $('.create-year-folders-btn').click(function() {
        if (!confirm('This will create year folders (Before 2024, 2024, 2025, etc.) in the base folder. Continue?')) {
            return;
        }
        
        var $btn = $(this);
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Creating folders...');
        
        $.post(ajaxurl, {
            action: 'onedrive_media_create_year_folders',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            $btn.prop('disabled', false).html(originalHtml);
            
            if (response.success) {
                alert('Year folders created successfully: ' + response.data.message);
            } else {
                alert('Failed to create folders: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            $btn.prop('disabled', false).html(originalHtml);
            alert('Request failed. Please try again.');
        });
    });
    
    // Revoke user token
    $('.revoke-user-token').click(function() {
        var userEmail = $(this).data('user-email');
        
        if (!confirm('Are you sure you want to revoke authorization for ' + userEmail + '?')) {
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'azure_onedrive_revoke_token',
            user_email: userEmail,
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Failed to revoke token: ' + (response.data || 'Unknown error'));
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>

<style>
@keyframes rotation {
    from { transform: rotate(0deg); }
    to { transform: rotate(359deg); }
}
</style>