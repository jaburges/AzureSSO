<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get backup statistics
$backup_stats = array();
$schedule_info = array();

if (class_exists('Azure_Backup_Scheduler')) {
    try {
        $scheduler = new Azure_Backup_Scheduler();
        $backup_stats = $scheduler->get_backup_stats();
        $schedule_info = $scheduler->get_schedule_info();
    } catch (Exception $e) {
        // Set fallback values on error
        $backup_stats = array(
            'total_backups' => 0,
            'completed_backups' => 0,
            'failed_backups' => 0,
            'total_size_formatted' => '0 B'
        );
    }
}

// Get recent backup jobs
global $wpdb;
$backup_jobs_table = Azure_Database::get_table_name('backup_jobs');
$recent_jobs = array();

if ($backup_jobs_table) {
    $recent_jobs = $wpdb->get_results("SELECT * FROM {$backup_jobs_table} ORDER BY created_at DESC LIMIT 10");
}

// Get settings for form
$settings = Azure_Settings::get_all_settings();
?>

<div class="wrap">
    <h1>Azure Plugin - Backup Settings</h1>
    
    <!-- Module Status Toggle -->
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

    <!-- Backup Statistics - 4 Widgets -->
    <!-- FORCE REFRESH: <?php echo time(); ?> -->
    <div class="backup-stats-section" data-timestamp="<?php echo time(); ?>">
        <h2>Backup Statistics</h2>
        
        <div class="stats-cards" data-cards-count="4">
            <!-- Card 1: Total -->
            <div class="stat-card total-card">
                <div class="stat-number"><?php echo intval($backup_stats['total_backups'] ?? 0); ?></div>
                <div class="stat-label">Total</div>
            </div>
            
            <!-- Card 2: Completed -->
            <div class="stat-card success completed-card">
                <div class="stat-number"><?php echo intval($backup_stats['completed_backups'] ?? 0); ?></div>
                <div class="stat-label">Completed</div>
            </div>
            
            <!-- Card 3: Failed -->
            <div class="stat-card error failed-card">
                <div class="stat-number"><?php echo intval($backup_stats['failed_backups'] ?? 0); ?></div>
                <div class="stat-label">Failed</div>
            </div>
            
            <!-- Card 4: Size -->
            <div class="stat-card size-card">
                <div class="stat-number"><?php echo esc_html($backup_stats['total_size_formatted'] ?? '0 B'); ?></div>
                <div class="stat-label">Size</div>
            </div>
        </div>
        
        <!-- Debug info (remove in production) -->
        <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
        <p style="font-size: 11px; color: #666; margin-top: 10px;">
            Debug: Cards rendered at <?php echo date('H:i:s'); ?> | 
            Total: <?php echo $backup_stats['total_backups'] ?? 'null'; ?> | 
            Completed: <?php echo $backup_stats['completed_backups'] ?? 'null'; ?> | 
            Failed: <?php echo $backup_stats['failed_backups'] ?? 'null'; ?> | 
            Size: <?php echo $backup_stats['total_size_formatted'] ?? 'null'; ?>
        </p>
        <?php endif; ?>
    </div>

    <!-- Backup Progress Section (Hidden by default) -->
    <div id="backup-progress-section" style="display: none; margin-top: 20px; padding: 20px; background: #f9f9f9; border-radius: 8px; border-left: 4px solid #0073aa;">
        <h3 style="margin: 0 0 15px 0; color: #0073aa;">🔄 Backup in Progress</h3>
        <div id="backup-progress-details" style="margin-bottom: 15px;">
            <p id="backup-progress-name" style="margin: 0; font-weight: bold; color: #333;"></p>
            <p id="backup-progress-status" style="margin: 5px 0; color: #666; font-size: 14px;"></p>
        </div>
        <div style="background: #e9ecef; border-radius: 10px; height: 28px; margin: 15px 0; overflow: hidden; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
            <div id="backup-progress-bar" style="
                background: linear-gradient(90deg, #0073aa 0%, #005a87 100%); 
                height: 100%; 
                width: 0%; 
                transition: width 0.5s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
                text-shadow: 0 1px 2px rgba(0,0,0,0.3);
                font-size: 14px;
            ">
                <span id="backup-progress-percent">0%</span>
            </div>
        </div>
        <div id="backup-progress-message" style="margin: 15px 0 10px 0; padding: 12px; background: #fff; border-radius: 4px; border: 1px solid #ddd; font-size: 14px; color: #555; font-family: monospace;"></div>
        <div id="backup-progress-actions" style="text-align: right; margin-top: 15px; display: none;">
            <button type="button" class="button" onclick="hideBackupProgress()">Hide Progress</button>
            <button type="button" class="button button-primary" onclick="refreshPage()">Refresh Page</button>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions-section">
        <h2>Quick Actions</h2>
        
        <div class="action-buttons">
            <button type="button" class="button button-primary start-backup">
                <span class="dashicons dashicons-backup"></span>
                Start Manual Backup
            </button>
            
            <button type="button" class="button test-azure-connection" data-type="sso">
                <span class="dashicons dashicons-cloud"></span>
                Test Azure Connection
            </button>
            
            <button type="button" class="button test-storage-connection">
                <span class="dashicons dashicons-cloud"></span>
                Test Storage Connection
            </button>
            
            <?php
            // Check if there are any running or pending (stuck) backups
            $stalled_backups_count = 0;
            if ($backup_jobs_table) {
                $stalled_backups_count = $wpdb->get_var("SELECT COUNT(*) FROM {$backup_jobs_table} WHERE status IN ('running', 'pending')");
            }
            ?>
            <button type="button" class="button button-secondary cancel-all-backups" <?php echo $stalled_backups_count == 0 ? 'disabled' : ''; ?>>
                <span class="dashicons dashicons-dismiss"></span>
                Cancel Stalled Backups
                <?php if ($stalled_backups_count > 0): ?>
                    <span class="running-count">(<?php echo $stalled_backups_count; ?>)</span>
                <?php endif; ?>
            </button>
            
            <button type="button" class="button button-secondary cleanup-backup-files">
                <span class="dashicons dashicons-trash"></span>
                Clean Up Local Files
            </button>
        </div>
    </div>

    <!-- Settings Form -->
    <div class="backup-settings-section">
        <form method="post" action="">
            <?php wp_nonce_field('azure_plugin_settings'); ?>
            
            <!-- Azure Storage Configuration -->
            <div class="credentials-section">
                <h2>Azure Storage Configuration</h2>
                <p class="description">Azure Storage Account is required for backup functionality.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Storage Account Name</th>
                        <td>
                            <input type="text" name="azure_plugin_settings[backup_storage_account_name]" value="<?php echo esc_attr($settings['backup_storage_account_name'] ?? ''); ?>" class="regular-text" />
                            <p class="description">Your Azure Storage Account name (without .blob.core.windows.net)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Storage Access Key</th>
                        <td>
                            <input type="password" name="azure_plugin_settings[backup_storage_account_key]" value="<?php echo esc_attr($settings['backup_storage_account_key'] ?? ''); ?>" class="regular-text" />
                            <p class="description">Primary or secondary access key for your storage account</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Container Name</th>
                        <td>
                            <input type="text" name="azure_plugin_settings[backup_storage_container_name]" value="<?php echo esc_attr($settings['backup_storage_container_name'] ?? 'wordpress-backups'); ?>" class="regular-text" />
                            <p class="description">Azure Blob Storage container name for backups</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Backup Configuration -->
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
                                <input type="checkbox" name="azure_plugin_settings[backup_types][]" value="<?php echo $type; ?>" <?php checked(in_array($type, $backup_types)); ?> />
                                <?php echo $label; ?>
                            </label><br>
                            <?php endforeach; ?>
                            <p class="description">Select which components to include in backups</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enable Scheduled Backups</th>
                        <td>
                            <label>
                                <input type="checkbox" name="azure_plugin_settings[backup_schedule_enabled]" <?php checked($settings['backup_schedule_enabled'] ?? false); ?> />
                                Run backups automatically
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Backup Frequency</th>
                        <td>
                            <select name="azure_plugin_settings[backup_schedule_frequency]">
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
                        <th scope="row">Retention Days</th>
                        <td>
                            <input type="number" name="azure_plugin_settings[backup_retention_days]" value="<?php echo esc_attr($settings['backup_retention_days'] ?? 30); ?>" min="1" max="365" class="small-text" />
                            <p class="description">Number of days to keep backups (0 = forever)</p>
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
        <table class="wp-list-table widefat fixed striped">
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
                        <button class="button button-small restore-backup" data-backup-id="<?php echo $job->id; ?>">
                            Restore
                        </button>
                        <button class="button button-small delete-backup" data-backup-id="<?php echo $job->id; ?>">
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

<script>
jQuery(document).ready(function($) {
    // Handle backup actions
    $('.start-backup').click(function() {
        if (!confirm('Are you sure you want to start a manual backup? This may take several minutes.')) {
            return;
        }
        
        var button = $(this);
        startBackupWithProgress(button);
    });
    
    function startBackupWithProgress(button) {
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Starting...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_start_backup',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            // Parse JSON if response is a string
            if (typeof response === 'string') {
                try {
                    response = JSON.parse(response);
                } catch (e) {
                    alert('❌ Invalid response from server');
                    button.prop('disabled', false).html('<span class="dashicons dashicons-backup"></span> Start Manual Backup');
                    return;
                }
            }
            
            if (response && response.success && response.data.requires_progress) {
                showBackupProgress(response.data.backup_id, response.data.message);
                trackBackupProgress(response.data.backup_id);
            } else if (response && response.success) {
                alert('Backup started successfully!');
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                var errorMsg = response && response.data ? response.data : 'Unknown error';
                alert('Failed to start backup: ' + errorMsg);
                button.prop('disabled', false).html('<span class="dashicons dashicons-backup"></span> Start Manual Backup');
            }
        }).fail(function() {
            alert('❌ Network error occurred');
            button.prop('disabled', false).html('<span class="dashicons dashicons-backup"></span> Start Manual Backup');
        });
    }
    
    function showBackupProgress(backupId, message) {
        $('#backup-progress-section').show();
        $('#backup-progress-name').text('Backup ID: ' + backupId);
        $('#backup-progress-status').text('Status: Initializing...');
        $('#backup-progress-message').text(message || 'Backup started successfully');
        $('#backup-progress-percent').text('0%');
        $('#backup-progress-bar').css('width', '0%');
        $('#backup-progress-actions').hide();
        
        $('html, body').animate({
            scrollTop: $('#backup-progress-section').offset().top - 20
        }, 500);
    }
    
    function trackBackupProgress(backupId) {
        var progressInterval = setInterval(function() {
            $.post(azure_plugin_ajax.ajax_url, {
                action: 'azure_get_backup_progress',
                backup_id: backupId
            }, function(response) {
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch (e) {
                        return;
                    }
                }
                
                if (response && response.success) {
                    var data = response.data;
                    updateProgressDisplay(data.progress, data.status, data.message);
                    
                    if (data.status === 'completed' || data.status === 'failed' || data.status === 'error') {
                        clearInterval(progressInterval);
                        showBackupComplete(data);
                    }
                }
            });
        }, 3000);
        
        // Safety timeout - stop checking after 30 minutes
        setTimeout(function() {
            clearInterval(progressInterval);
            $('#backup-progress-message').text('Backup is taking longer than expected. Please check the logs for status.');
            $('#backup-progress-actions').show();
        }, 30 * 60 * 1000);
    }
    
    function updateProgressDisplay(progress, status, message) {
        $('#backup-progress-bar').css('width', progress + '%');
        $('#backup-progress-percent').text(progress + '%');
        $('#backup-progress-status').text('Status: ' + (status || 'running'));
        $('#backup-progress-message').text(message || 'Processing...');
        
        if (status === 'running') {
            $('#backup-progress-bar').css('background', 'linear-gradient(90deg, #0073aa 0%, #005a87 100%)');
        } else if (status === 'failed' || status === 'error') {
            $('#backup-progress-bar').css('background', 'linear-gradient(90deg, #dc3232 0%, #b32d2e 100%)');
        }
    }
    
    function showBackupComplete(data) {
        if (data.status === 'completed') {
            $('#backup-progress-bar').css('background', 'linear-gradient(90deg, #46b450 0%, #399245 100%)');
            $('#backup-progress-percent').text('100%');
            $('#backup-progress-status').text('Status: Completed Successfully');
            $('#backup-progress-message').text('✅ Backup completed successfully!');
        } else {
            $('#backup-progress-bar').css('background', 'linear-gradient(90deg, #dc3232 0%, #b32d2e 100%)');
            $('#backup-progress-percent').text('Error');
            $('#backup-progress-status').text('Status: Failed');
            $('#backup-progress-message').text('❌ Backup failed: ' + (data.message || 'Unknown error'));
        }
        
        $('#backup-progress-actions').show();
        $('.start-backup').prop('disabled', false).html('<span class="dashicons dashicons-backup"></span> Start Manual Backup');
        
        if (data.status === 'completed') {
            setTimeout(function() { location.reload(); }, 5000);
        }
    }
    
    window.hideBackupProgress = function() {
        $('#backup-progress-section').hide();
        $('.start-backup').prop('disabled', false).html('<span class="dashicons dashicons-backup"></span> Start Manual Backup');
    };
    
    window.refreshPage = function() {
        location.reload();
    };
    
    // Test Azure connection
    $('.test-azure-connection').click(function() {
        var button = $(this);
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Testing...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_test_sso_connection',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            button.prop('disabled', false).html('<span class="dashicons dashicons-cloud"></span> Test Azure Connection');
            
            if (typeof response === 'string') {
                try {
                    response = JSON.parse(response);
                } catch (e) {
                    alert('❌ Invalid response from server');
                    return;
                }
            }
            
            if (response && response.success) {
                alert('✅ Azure connection successful!');
            } else {
                alert('❌ Connection failed: ' + (response && response.data ? response.data : 'Unknown error'));
            }
        }).fail(function() {
            button.prop('disabled', false).html('<span class="dashicons dashicons-cloud"></span> Test Azure Connection');
            alert('❌ Network error occurred');
        });
    });
    
    // Test Storage connection
    $('.test-storage-connection').click(function() {
        var button = $(this);
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Testing...');
        
        var storageAccount = $('input[name="azure_plugin_settings[backup_storage_account_name]"]').val();
        var storageKey = $('input[name="azure_plugin_settings[backup_storage_account_key]"]').val();
        var containerName = $('input[name="azure_plugin_settings[backup_storage_container_name]"]').val();
        
        if (!storageAccount || !storageKey) {
            button.prop('disabled', false).html('<span class="dashicons dashicons-cloud"></span> Test Storage Connection');
            alert('❌ Please fill in Storage Account Name and Access Key');
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
            
            if (typeof response === 'string') {
                try {
                    response = JSON.parse(response);
                } catch (e) {
                    alert('❌ Invalid response from server');
                    return;
                }
            }
            
            if (response && response.success) {
                alert('✅ Storage connection successful!');
            } else {
                alert('❌ Storage connection failed: ' + (response && response.data ? response.data : 'Unknown error'));
            }
        }).fail(function() {
            button.prop('disabled', false).html('<span class="dashicons dashicons-cloud"></span> Test Storage Connection');
            alert('❌ Network error occurred');
        });
    });
    
    // Module toggle
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
                    location.reload();
                }
            } else {
                $('.backup-module-toggle').prop('checked', !enabled);
                alert('Failed to toggle backup module: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            $('.backup-module-toggle').prop('checked', !enabled);
            alert('Network error occurred');
        });
    });
    
    // Handle restore
    $('.restore-backup').click(function() {
        var backupId = $(this).data('backup-id');
        
        if (!confirm('Are you sure you want to restore this backup? This will overwrite your current content and cannot be undone.')) {
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
                row.fadeOut(function() { row.remove(); });
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
    
    // Clean up orphaned backup files
    $('.cleanup-backup-files').click(function() {
        if (!confirm('This will remove any orphaned backup files from the local server (temp directories and zip files not uploaded to Azure). Continue?')) {
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> Cleaning...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_cleanup_backup_files',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Clean Up Local Files');
            
            if (typeof response === 'string') {
                try {
                    response = JSON.parse(response);
                } catch (e) {
                    alert('❌ Invalid response from server');
                    return;
                }
            }
            
            if (response && response.success) {
                var msg = response.data.message;
                if (response.data.files_found && response.data.files_found.length > 0) {
                    msg += '\n\nFiles found before cleanup:';
                    response.data.files_found.forEach(function(f) {
                        var size = f.size ? ' (' + formatBytes(f.size) + ')' : '';
                        msg += '\n• ' + f.name + size;
                    });
                }
                alert('✅ ' + msg);
            } else {
                alert('❌ Cleanup failed: ' + (response && response.data ? response.data : 'Unknown error'));
            }
        }).fail(function() {
            button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Clean Up Local Files');
            alert('❌ Network error occurred');
        });
    });
    
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Cancel all running backups
    $('.cancel-all-backups').click(function() {
        if (!confirm('Are you sure you want to cancel ALL running backup jobs? This will mark them as failed and stop any progress tracking.')) {
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> Cancelling...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_cancel_all_backups',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (typeof response === 'string') {
                try {
                    response = JSON.parse(response);
                } catch (e) {
                    alert('❌ Invalid response from server');
                    button.prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> Cancel All Running Backups');
                    return;
                }
            }
            
            if (response && response.success) {
                alert('✅ ' + response.data.message);
                // Hide progress section if visible
                $('#backup-progress-section').hide();
                // Reload to show updated status
                location.reload();
            } else {
                alert('❌ Failed to cancel backups: ' + (response && response.data ? response.data : 'Unknown error'));
                button.prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> Cancel All Running Backups');
            }
        }).fail(function() {
            alert('❌ Network error occurred');
            button.prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> Cancel All Running Backups');
        });
    });
});
</script>