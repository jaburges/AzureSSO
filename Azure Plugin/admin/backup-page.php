<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get backup statistics
$backup_stats = array();
if (class_exists('Azure_Backup_Scheduler')) {
    $scheduler = new Azure_Backup_Scheduler();
    $backup_stats = $scheduler->get_backup_stats();
    $schedule_info = $scheduler->get_schedule_info();
}

// Get recent backup jobs
global $wpdb;
$backup_jobs_table = Azure_Database::get_table_name('backup_jobs');
$recent_jobs = array();

if ($backup_jobs_table) {
    $recent_jobs = $wpdb->get_results("SELECT * FROM {$backup_jobs_table} ORDER BY created_at DESC LIMIT 10");
}
?>

<div class="wrap">
    <h1>Azure Plugin - Backup Settings</h1>
    
    <!-- Module Toggle Section -->
    <div class="module-status-section">
        <h2>Module Status</h2>
        <div class="module-toggle-card">
            <div class="module-info">
                <h3><span class="dashicons dashicons-backup"></span> Azure Backup Module</h3>
                <p>Backup your WordPress site to Azure Blob Storage</p>
            </div>
            <div class="module-control">
                <label class="switch">
                    <input type="checkbox" class="backup-module-toggle" <?php checked(Azure_Settings::is_module_enabled('backup')); ?> />
                    <span class="slider"></span>
                </label>
                <span class="toggle-status"><?php echo Azure_Settings::is_module_enabled('backup') ? 'Enabled' : 'Disabled'; ?></span>
            </div>
        </div>
        <?php if (!Azure_Settings::is_module_enabled('backup')): ?>
        <div class="notice notice-warning inline">
            <p><strong>Backup module is disabled.</strong> Enable it above or in the <a href="<?php echo admin_url('admin.php?page=azure-plugin'); ?>">main settings</a> to use backup functionality.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="azure-backup-dashboard">
        <!-- Backup Statistics -->
        <div class="backup-stats-section">
            <h2>Backup Statistics</h2>
            
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $backup_stats['total_backups'] ?? 0; ?></div>
                    <div class="stat-label">Total Backups</div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-number"><?php echo $backup_stats['completed_backups'] ?? 0; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                
                <div class="stat-card error">
                    <div class="stat-number"><?php echo $backup_stats['failed_backups'] ?? 0; ?></div>
                    <div class="stat-label">Failed</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $backup_stats['total_size_formatted'] ?? '0 B'; ?></div>
                    <div class="stat-label">Total Size</div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="backup-actions-section">
            <h2>Quick Actions</h2>
            
            <div class="action-buttons">
                <button type="button" class="button button-primary start-backup">
                    <span class="dashicons dashicons-backup"></span>
                    Start Manual Backup
                </button>
                
                <button type="button" class="button test-azure-connection" data-type="backup">
                    <span class="dashicons dashicons-cloud"></span>
                    Test Azure Connection
                </button>
                
                <button type="button" class="button sync-schedule">
                    <span class="dashicons dashicons-clock"></span>
                    Update Schedule
                </button>
            </div>
        </div>
        
        <!-- Settings Form -->
        <div class="backup-settings-section">
            <form method="post" action="">
                <?php wp_nonce_field('azure_plugin_settings'); ?>
                
                <!-- Azure Storage Configuration (Always Required) -->
                <div class="credentials-section">
                    <h2>Azure Storage Configuration</h2>
                    <p class="description">Azure Storage Account is required for backup functionality, regardless of authentication credentials.</p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Storage Account Name</th>
                            <td>
                                <input type="text" name="backup_storage_account" value="<?php echo esc_attr($settings['backup_storage_account'] ?? ''); ?>" class="regular-text" required />
                                <p class="description">Your Azure Storage Account name (without .blob.core.windows.net)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Storage Access Key</th>
                            <td>
                                <input type="password" name="backup_storage_key" value="<?php echo esc_attr($settings['backup_storage_key'] ?? ''); ?>" class="regular-text" required />
                                <p class="description">Primary or secondary access key for your storage account</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Container Name</th>
                            <td>
                                <input type="text" name="backup_container_name" value="<?php echo esc_attr($settings['backup_container_name'] ?? 'wordpress-backups'); ?>" class="regular-text" />
                                <p class="description">Azure Blob Storage container name for backups (will be created if it doesn't exist)</p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <button type="button" class="button test-azure-storage">
                                    <span class="dashicons dashicons-cloud"></span>
                                    Test Storage Connection
                                </button>
                                <span class="storage-test-status"></span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Backup Settings -->
                <div class="backup-configuration">
                    <h2>Backup Configuration</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Backup Types</th>
                            <td>
                                <?php
                                $backup_types = $settings['backup_types'] ?? array('content', 'media', 'plugins', 'themes', 'database');
                                $available_types = array(
                                    'database' => 'Database',
                                    'content' => 'Content Files',
                                    'media' => 'Media Files',
                                    'plugins' => 'Plugins',
                                    'themes' => 'Themes'
                                );
                                ?>
                                <?php foreach ($available_types as $type => $label): ?>
                                <label>
                                    <input type="checkbox" name="backup_types[]" value="<?php echo $type; ?>" <?php checked(in_array($type, $backup_types)); ?> />
                                    <?php echo $label; ?>
                                </label><br>
                                <?php endforeach; ?>
                                <p class="description">Select which components to include in backups</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Retention Days</th>
                            <td>
                                <input type="number" name="backup_retention_days" value="<?php echo esc_attr($settings['backup_retention_days'] ?? 30); ?>" min="1" max="365" class="small-text" />
                                <p class="description">Number of days to keep backups (0 = forever)</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Schedule Settings -->
                <div class="backup-schedule">
                    <h2>Backup Schedule</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Scheduled Backups</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="backup_schedule_enabled" <?php checked($settings['backup_schedule_enabled'] ?? false); ?> />
                                    Run backups automatically
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Backup Frequency</th>
                            <td>
                                <select name="backup_schedule_frequency">
                                    <?php
                                    $current_frequency = $settings['backup_schedule_frequency'] ?? 'daily';
                                    $frequencies = array(
                                        'hourly' => 'Hourly',
                                        'daily' => 'Daily',
                                        'weekly' => 'Weekly',
                                        'monthly' => 'Monthly'
                                    );
                                    ?>
                                    <?php foreach ($frequencies as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php selected($current_frequency, $value); ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Backup Time</th>
                            <td>
                                <input type="time" name="backup_schedule_time" value="<?php echo esc_attr($settings['backup_schedule_time'] ?? '02:00'); ?>" />
                                <p class="description">Time to run scheduled backups (24-hour format)</p>
                            </td>
                        </tr>
                        <?php if (isset($schedule_info['next_backup'])): ?>
                        <tr>
                            <th scope="row">Next Scheduled Backup</th>
                            <td>
                                <strong><?php echo esc_html($schedule_info['next_backup']); ?></strong>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <!-- Notification Settings -->
                <div class="backup-notifications">
                    <h2>Notification Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Email Notifications</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="backup_email_notifications" <?php checked($settings['backup_email_notifications'] ?? true); ?> />
                                    Send email notifications for backup status
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Notification Email</th>
                            <td>
                                <input type="email" name="backup_notification_email" value="<?php echo esc_attr($settings['backup_notification_email'] ?? get_option('admin_email')); ?>" class="regular-text" />
                                <p class="description">Email address to receive backup notifications</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <input type="submit" name="azure_plugin_submit" class="button-primary" value="Save Backup Settings" />
                </p>
            </form>
        </div>
        
        <!-- Recent Backup Jobs -->
        <div class="backup-jobs-section">
            <h2>Recent Backup Jobs</h2>
            
            <?php if (!empty($recent_jobs)): ?>
            <table class="backup-jobs-table widefat striped">
                <thead>
                    <tr>
                        <th>Job Name</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Size</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_jobs as $job): ?>
                    <tr>
                        <td><?php echo esc_html($job->job_name); ?></td>
                        <td>
                            <?php
                            $status_class = $job->status === 'completed' ? 'success' : ($job->status === 'failed' ? 'error' : 'warning');
                            ?>
                            <span class="status-indicator <?php echo $status_class; ?>">
                                <?php echo esc_html(ucfirst($job->status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($job->created_at); ?></td>
                        <td><?php echo $job->file_size ? size_format($job->file_size) : '-'; ?></td>
                        <td>
                            <?php if ($job->status === 'completed' && !empty($job->azure_blob_name)): ?>
                            <button class="button button-small restore-backup" data-backup-id="<?php echo $job->id; ?>" data-backup-name="<?php echo esc_attr($job->job_name); ?>">
                                Restore
                            </button>
                            <button class="button button-small delete-backup" data-backup-id="<?php echo $job->id; ?>" data-blob-name="<?php echo esc_attr($job->azure_blob_name); ?>">
                                Delete
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($job->status === 'failed' && !empty($job->error_message)): ?>
                            <button class="button button-small view-error" data-error="<?php echo esc_attr($job->error_message); ?>">
                                View Error
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>No backup jobs found. <a href="#" class="start-backup">Create your first backup</a>.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle backup actions
    $('.start-backup').click(function() {
        if (!confirm('Are you sure you want to start a manual backup? This may take several minutes.')) {
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Starting...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_start_backup',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (response.success) {
                alert('Backup started successfully! You will receive an email notification when it completes.');
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                alert('Failed to start backup: ' + (response.data || 'Unknown error'));
                button.prop('disabled', false).html('<span class="dashicons dashicons-backup"></span> Start Manual Backup');
            }
        }).fail(function() {
            alert('Network error occurred');
            button.prop('disabled', false).html('<span class="dashicons dashicons-backup"></span> Start Manual Backup');
        });
    });
    
    // Handle restore
    $('.restore-backup').click(function() {
        var backupId = $(this).data('backup-id');
        var backupName = $(this).data('backup-name');
        
        if (!confirm('Are you sure you want to restore "' + backupName + '"? This will overwrite your current content and cannot be undone.')) {
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).text('Restoring...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_restore_backup',
            backup_id: backupId,
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (response.success) {
                alert('Restore completed successfully!');
                location.reload();
            } else {
                alert('Restore failed: ' + (response.data || 'Unknown error'));
                button.prop('disabled', false).text('Restore');
            }
        }).fail(function() {
            alert('Network error occurred');
            button.prop('disabled', false).text('Restore');
        });
    });
    
    // Handle delete backup
    $('.delete-backup').click(function() {
        var backupId = $(this).data('backup-id');
        
        if (!confirm('Are you sure you want to delete this backup? This action cannot be undone.')) {
            return;
        }
        
        var button = $(this);
        var row = button.closest('tr');
        
        button.prop('disabled', true).text('Deleting...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_delete_backup',
            backup_id: backupId,
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (response.success) {
                row.fadeOut(function() {
                    row.remove();
                });
            } else {
                alert('Delete failed: ' + (response.data || 'Unknown error'));
                button.prop('disabled', false).text('Delete');
            }
        }).fail(function() {
            alert('Network error occurred');
            button.prop('disabled', false).text('Delete');
        });
    });
    
    // Handle view error
    $('.view-error').click(function() {
        var error = $(this).data('error');
        alert('Error Details:\n\n' + error);
    });
    
    // Test Azure connection
    $('.test-azure-connection').click(function() {
        var button = $(this);
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Testing...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_test_storage_connection',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            button.prop('disabled', false).html('<span class="dashicons dashicons-cloud"></span> Test Azure Connection');
            
            if (response.success) {
                alert('✅ Connection successful! Azure Storage is properly configured.');
            } else {
                alert('❌ Connection failed: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            button.prop('disabled', false).html('<span class="dashicons dashicons-cloud"></span> Test Azure Connection');
            alert('❌ Network error occurred');
        });
    });
    
    // Update schedule
    $('.sync-schedule').click(function() {
        var button = $(this);
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Updating...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_schedule_backup',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            button.prop('disabled', false).html('<span class="dashicons dashicons-clock"></span> Update Schedule');
            
            if (response.success) {
                alert('✅ Schedule updated successfully!');
                location.reload();
            } else {
                alert('❌ Failed to update schedule: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            button.prop('disabled', false).html('<span class="dashicons dashicons-clock"></span> Update Schedule');
            alert('❌ Network error occurred');
        });
    });
    
    // Handle module toggle
    $('.backup-module-toggle').change(function() {
        var enabled = $(this).is(':checked');
        var statusText = $('.toggle-status');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_toggle_module',
            module: 'backup',
            enabled: enabled,
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (response.success) {
                statusText.text(enabled ? 'Enabled' : 'Disabled');
                if (enabled) {
                    $('.notice.notice-warning.inline').fadeOut();
                } else {
                    location.reload(); // Refresh to show warning
                }
            } else {
                // Revert toggle if failed
                $('.backup-module-toggle').prop('checked', !enabled);
                alert('Failed to toggle backup module: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            // Revert toggle if failed
            $('.backup-module-toggle').prop('checked', !enabled);
            alert('Network error occurred');
        });
    });
    
    // Test Azure Storage Connection
    $('.test-azure-storage').click(function() {
        var button = $(this);
        var status = $('.storage-test-status');
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Testing...');
        status.html('');
        
        var storageAccount = $('input[name="backup_storage_account"]').val();
        var storageKey = $('input[name="backup_storage_key"]').val();
        var containerName = $('input[name="backup_container_name"]').val();
        
        if (!storageAccount || !storageKey) {
            button.prop('disabled', false).html('<span class="dashicons dashicons-cloud"></span> Test Storage Connection');
            status.html('<span class="dashicons dashicons-dismiss" style="color: red;"></span> Please fill in Storage Account Name and Access Key');
            return;
        }
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_test_storage_connection',
            storage_account: storageAccount,
            storage_key: storageKey,
            container_name: containerName,
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            button.prop('disabled', false).html('<span class="dashicons dashicons-cloud"></span> Test Storage Connection');
            
            if (response.success) {
                status.html('<span class="dashicons dashicons-yes-alt" style="color: green;"></span> ' + (response.data || 'Connection successful! Storage is properly configured.'));
            } else {
                var errorMsg = response.data || 'Unknown error';
                // Format multiline error messages
                errorMsg = errorMsg.replace(/\n/g, '<br>');
                status.html('<span class="dashicons dashicons-dismiss" style="color: red;"></span> <strong>Connection failed:</strong><br>' + errorMsg);
            }
            
            // Don't auto-hide for error messages - they need to be read
            if (response.success) {
                setTimeout(function() {
                    status.fadeOut();
                }, 10000);
            }
        }).fail(function(xhr, textStatus, errorThrown) {
            button.prop('disabled', false).html('<span class="dashicons dashicons-cloud"></span> Test Storage Connection');
            var networkError = 'Network error occurred';
            if (xhr.responseJSON && xhr.responseJSON.data) {
                networkError += ': ' + xhr.responseJSON.data;
            } else if (errorThrown) {
                networkError += ': ' + errorThrown;
            }
            status.html('<span class="dashicons dashicons-dismiss" style="color: red;"></span> ' + networkError);
        });
    });
    
    // Auto-refresh backup jobs every 30 seconds if there are any running
    <?php if (!empty($recent_jobs) && in_array('running', array_column($recent_jobs, 'status'))): ?>
    setInterval(function() {
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_get_backup_jobs',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (response.success && response.data) {
                $('.backup-jobs-table tbody').html(response.data);
            }
        });
    }, 30000);
    <?php endif; ?>
});
</script>

<style>
.backup-stats-section {
    margin-bottom: 20px;
}

.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.stat-card.success {
    border-left: 4px solid #46b450;
}

.stat-card.error {
    border-left: 4px solid #dc3232;
}

.stat-number {
    font-size: 2em;
    font-weight: bold;
    color: #0073aa;
}

.stat-label {
    margin-top: 10px;
    color: #666;
    text-transform: uppercase;
    font-size: 0.9em;
}

.backup-actions-section {
    margin-bottom: 30px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.action-buttons .button {
    display: flex;
    align-items: center;
    gap: 5px;
}

.backup-jobs-table {
    margin-top: 15px;
}

.backup-jobs-table th,
.backup-jobs-table td {
    padding: 12px;
}

.backup-configuration,
.backup-schedule,
.backup-notifications,
.credentials-section {
    background: #fff !important;
    color: #333 !important;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    margin-bottom: 20px;
}

/* Module Status Section */
.module-status-section {
    margin-bottom: 30px;
}

.module-toggle-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff !important;
    color: #333 !important;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    margin-bottom: 15px;
}

.module-info h3 {
    margin: 0 0 8px 0;
    color: #333 !important;
    display: flex;
    align-items: center;
    gap: 8px;
}

.module-info p {
    margin: 0;
    color: #666 !important;
}

.module-control {
    display: flex;
    align-items: center;
    gap: 15px;
}

.toggle-status {
    font-weight: 500;
    color: #333 !important;
}

.notice.inline {
    margin: 15px 0;
}

/* Form Elements Contrast */
.form-table th {
    color: #333 !important;
    background: #f9f9f9 !important;
}

.form-table td {
    color: #333 !important;
}

.form-table input,
.form-table select,
.form-table textarea {
    color: #333 !important;
    background: #fff !important;
    border: 1px solid #ddd;
}

.form-table .description {
    color: #666 !important;
}

/* Section Headers */
.backup-stats-section h2,
.backup-actions-section h2,
.backup-jobs-section h2,
.credentials-section h2,
.backup-configuration h2,
.backup-schedule h2,
.backup-notifications h2 {
    color: #333 !important;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

/* Stats Cards */
.stat-card {
    background: #fff !important;
    color: #333 !important;
}

.stat-number {
    color: #0073aa !important;
}

.stat-label {
    color: #666 !important;
}

/* Status Test Results */
.storage-test-status {
    margin-left: 10px;
    font-weight: bold;
    color: #333 !important;
}

/* WordPress Dark Theme Overrides */
body.admin-color-midnight .module-toggle-card,
body.admin-color-midnight .credentials-section,
body.admin-color-midnight .backup-configuration,
body.admin-color-midnight .backup-schedule,
body.admin-color-midnight .backup-notifications,
body.admin-color-midnight .stat-card {
    background: #fff !important;
    color: #333 !important;
    border-color: #ccd0d4 !important;
}

/* Ensure all backgrounds are white and text is dark */
.azure-backup-dashboard .credentials-section,
.azure-backup-dashboard .backup-configuration,
.azure-backup-dashboard .backup-schedule,
.azure-backup-dashboard .backup-notifications {
    background: #fff !important;
    color: #333 !important;
    border: 1px solid #ccd0d4 !important;
}

/* Toggle Switch Styles */
.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    border-radius: 24px;
    transition: .4s;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    border-radius: 50%;
    transition: .4s;
}

input:checked + .slider {
    background-color: #0073aa;
}

input:checked + .slider:before {
    transform: translateX(26px);
}
</style>
