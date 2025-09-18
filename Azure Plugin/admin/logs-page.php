<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get recent logs using the new formatted logger
$level_filter = $_GET['level'] ?? '';
$module_filter = $_GET['module'] ?? '';
$log_lines = Azure_Logger::get_formatted_logs(500, $level_filter, $module_filter);

// Get activity log statistics
$activity_stats = array();
global $wpdb;
$activity_table = Azure_Database::get_table_name('activity_log');

if ($activity_table) {
    $activity_stats['total_activities'] = $wpdb->get_var("SELECT COUNT(*) FROM {$activity_table}");
    $activity_stats['today_activities'] = $wpdb->get_var("SELECT COUNT(*) FROM {$activity_table} WHERE DATE(created_at) = CURDATE()");
    $activity_stats['errors_today'] = $wpdb->get_var("SELECT COUNT(*) FROM {$activity_table} WHERE DATE(created_at) = CURDATE() AND status = 'error'");
    
    // Get recent activity
    $recent_activity = $wpdb->get_results("SELECT * FROM {$activity_table} ORDER BY created_at DESC LIMIT 20");
} else {
    $recent_activity = array();
}
?>

<div class="wrap">
    <h1>Azure Plugin - Logs & Activity</h1>
    
    <div class="azure-logs-dashboard">
        <!-- Activity Statistics -->
        <?php if (!empty($activity_stats)): ?>
        <div class="activity-stats-section">
            <h2>Activity Overview</h2>
            
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-number"><?php echo intval($activity_stats['total_activities'] ?? 0); ?></div>
                    <div class="stat-label">Total Activities</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo intval($activity_stats['today_activities'] ?? 0); ?></div>
                    <div class="stat-label">Today's Activities</div>
                </div>
                
                <div class="stat-card <?php echo intval($activity_stats['errors_today'] ?? 0) > 0 ? 'error' : 'success'; ?>">
                    <div class="stat-number"><?php echo intval($activity_stats['errors_today'] ?? 0); ?></div>
                    <div class="stat-label">Errors Today</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($log_lines); ?></div>
                    <div class="stat-label">Log Entries</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Log Controls -->
        <div class="log-controls-section">
            <h2>Log Management</h2>
            
            <div class="log-controls">
                <button type="button" class="button refresh-logs">
                    <span class="dashicons dashicons-update"></span>
                    Refresh Logs
                </button>
                
                <button type="button" class="button clear-logs">
                    <span class="dashicons dashicons-trash"></span>
                    Clear Logs
                </button>
                
                <button type="button" class="button download-logs">
                    <span class="dashicons dashicons-download"></span>
                    Download Logs
                </button>
                
                <select id="log-level-filter" class="log-level-filter">
                    <option value="">All Levels</option>
                    <option value="ERROR">Errors Only</option>
                    <option value="WARNING">Warnings Only</option>
                    <option value="INFO">Info Only</option>
                    <option value="DEBUG">Debug Only</option>
                </select>
                
                <select id="module-filter" class="module-filter">
                    <option value="">All Modules</option>
                    <option value="sso">SSO</option>
                    <option value="backup">Backup</option>
                    <option value="calendar">Calendar</option>
                    <option value="email">Email</option>
                    <option value="pta">PTA Roles</option>
                    <option value="admin">Admin</option>
                    <option value="system">System</option>
                </select>
            </div>
        </div>
        
        <!-- Debug Logs Viewer -->
        <div class="debug-logs-section">
            <h2>Debug Logs</h2>
            
            <?php if (!empty($log_lines)): ?>
            <div class="log-viewer" id="log-content">
                <?php foreach ($log_lines as $line): ?>
                    <?php if (trim($line)): ?>
                        <?php
                        // Parse log line: "MM-DD-YYYY HH:MM:SS [Module] - LEVEL - message"
                        $level_class = 'info';
                        if (strpos($line, '- ERROR -') !== false) $level_class = 'error';
                        elseif (strpos($line, '- WARNING -') !== false) $level_class = 'warning';
                        elseif (strpos($line, '- DEBUG -') !== false) $level_class = 'debug';
                        
                        // Extract parts for better formatting
                        if (preg_match('/^(\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}) (\[.*?\]) - (\w+) - (.*)$/', $line, $matches)) {
                            $timestamp = $matches[1];
                            $module = $matches[2];
                            $level = $matches[3];
                            $message = $matches[4];
                        } else {
                            $timestamp = '';
                            $module = '';
                            $level = '';
                            $message = $line;
                        }
                        ?>
                    <div class="log-line <?php echo $level_class; ?>" data-level="<?php echo strtolower($level); ?>" data-module="<?php echo strtolower(trim($module, '[]')); ?>">
                        <?php if ($timestamp): ?>
                            <span class="log-timestamp"><?php echo esc_html($timestamp); ?></span> <span class="log-module module-badge module-<?php echo strtolower(trim($module, '[]')); ?>"><?php echo esc_html($module); ?></span> <span class="log-level level-<?php echo strtolower($level); ?>"><?php echo esc_html($level); ?></span> <span class="log-message"><?php echo esc_html($message); ?></span>
                        <?php else: ?>
                            <?php echo esc_html($line); ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="log-viewer" id="log-content">
                <div class="log-line info">No logs available yet. Activity will appear here as you use the plugin modules.</div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Activity -->
        <?php if (!empty($recent_activity)): ?>
        <div class="recent-activity-section">
            <h2>Recent Activity</h2>
            
            <table class="activity-table widefat striped">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Object</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Time</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_activity as $activity): ?>
                    <?php 
                    $user_name = 'System';
                    if ($activity->user_id) {
                        $user = get_user_by('id', $activity->user_id);
                        $user_name = $user ? $user->display_name : 'User #' . $activity->user_id;
                    }
                    ?>
                    <tr>
                        <td>
                            <span class="module-badge module-<?php echo esc_attr($activity->module); ?>">
                                <?php echo esc_html(strtoupper($activity->module)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($activity->action); ?></td>
                        <td>
                            <?php if ($activity->object_type): ?>
                                <?php echo esc_html($activity->object_type); ?>
                                <?php if ($activity->object_id): ?>
                                    <code>#<?php echo esc_html($activity->object_id); ?></code>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($user_name); ?></td>
                        <td>
                            <span class="status-indicator <?php echo esc_attr($activity->status); ?>">
                                <?php echo esc_html(ucfirst($activity->status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($activity->created_at); ?></td>
                        <td>
                            <?php if ($activity->details): ?>
                            <button type="button" class="button button-small view-details" data-details="<?php echo esc_attr($activity->details); ?>">
                                View
                            </button>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- System Information -->
        <div class="system-info-section">
            <h2>System Information</h2>
            
            <div class="system-info-grid">
                <div class="info-card">
                    <h4>WordPress</h4>
                    <ul>
                        <li><strong>Version:</strong> <?php echo get_bloginfo('version'); ?></li>
                        <li><strong>Multisite:</strong> <?php echo is_multisite() ? 'Yes' : 'No'; ?></li>
                        <li><strong>Debug:</strong> <?php echo defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled'; ?></li>
                        <li><strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?></li>
                    </ul>
                </div>
                
                <div class="info-card">
                    <h4>Server</h4>
                    <ul>
                        <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
                        <li><strong>Web Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></li>
                        <li><strong>Max Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?>s</li>
                        <li><strong>Upload Max Size:</strong> <?php echo ini_get('upload_max_filesize'); ?></li>
                    </ul>
                </div>
                
                <div class="info-card">
                    <h4>Azure Plugin</h4>
                    <ul>
                        <li><strong>Version:</strong> <?php echo AZURE_PLUGIN_VERSION; ?></li>
                        <li><strong>Path:</strong> <code><?php echo AZURE_PLUGIN_PATH; ?></code></li>
                        <li><strong>URL:</strong> <code><?php echo AZURE_PLUGIN_URL; ?></code></li>
                        <li><strong>Log File:</strong> <?php echo file_exists(AZURE_PLUGIN_PATH . 'logs.md') ? 'Exists' : 'Not Found'; ?></li>
                    </ul>
                </div>
                
                <div class="info-card">
                    <h4>Modules Status</h4>
                    <ul>
                        <li><strong>SSO:</strong> <?php echo Azure_Settings::is_module_enabled('sso') ? '✅ Enabled' : '❌ Disabled'; ?></li>
                        <li><strong>Backup:</strong> <?php echo Azure_Settings::is_module_enabled('backup') ? '✅ Enabled' : '❌ Disabled'; ?></li>
                        <li><strong>Calendar:</strong> <?php echo Azure_Settings::is_module_enabled('calendar') ? '✅ Enabled' : '❌ Disabled'; ?></li>
                        <li><strong>Email:</strong> <?php echo Azure_Settings::is_module_enabled('email') ? '✅ Enabled' : '❌ Disabled'; ?></li>
                        <li><strong>PTA Roles:</strong> <?php echo Azure_Settings::is_module_enabled('pta') ? '✅ Enabled' : '❌ Disabled'; ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Export/Import Settings -->
        <div class="settings-export-section">
            <h2>Settings Management</h2>
            
            <div class="export-import-controls">
                <div class="export-section">
                    <h4>Export Settings</h4>
                    <p>Export your Azure plugin settings for backup or migration.</p>
                    <button type="button" class="button export-settings">
                        <span class="dashicons dashicons-download"></span>
                        Export Settings
                    </button>
                </div>
                
                <div class="import-section">
                    <h4>Import Settings</h4>
                    <p>Import settings from a previously exported file.</p>
                    <input type="file" id="import-settings-file" accept=".json" style="display: none;">
                    <button type="button" class="button import-settings">
                        <span class="dashicons dashicons-upload"></span>
                        Import Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Refresh logs
    $('.refresh-logs').click(function() {
        var button = $(this);
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Refreshing...');
        
        var level_filter = $('#log-level-filter').val() || '';
        var module_filter = $('#module-filter').val() || '';
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_refresh_logs',
            level: level_filter,
            module: module_filter,
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Refresh Logs');
            
            if (response.success) {
                $('#log-content').html(response.data.html);
                $('.stat-card:last-child .stat-number').text(response.data.count);
                
                // Scroll to bottom to show newest logs
                var logViewer = $('#log-content')[0];
                logViewer.scrollTop = logViewer.scrollHeight;
            } else {
                alert('❌ Failed to refresh logs: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Refresh Logs');
            alert('❌ Network error while refreshing logs');
        });
    });
    
    // Clear logs
    $('.clear-logs').click(function() {
        if (!confirm('Are you sure you want to clear all logs? This action cannot be undone.')) {
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Clearing...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_clear_logs',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Clear Logs');
            
            if (response.success) {
                $('#log-content').html('<div class="log-line info">Logs cleared successfully.</div>');
                $('.stat-card:last-child .stat-number').text('0');
            } else {
                alert('❌ Failed to clear logs: ' + (response.data || 'Unknown error'));
            }
        });
    });
    
    // Download logs
    $('.download-logs').click(function() {
        var button = $(this);
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Preparing...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_download_logs',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Download Logs');
            
            if (response.success) {
                var blob = new Blob([response.data.content], { type: 'text/plain' });
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = response.data.filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else {
                alert('❌ Failed to prepare logs for download: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Download Logs');
            alert('❌ Network error while downloading logs');
        });
    });
    
    // Filter logs
    $('#log-level-filter, #module-filter').change(function() {
        $('.refresh-logs').click(); // Trigger refresh with new filters
    });
    
    // Auto-refresh logs every 10 seconds
    setInterval(function() {
        if ($('#log-content').length && !$('.refresh-logs').prop('disabled')) {
            $('.refresh-logs').click();
        }
    }, 10000);
    
    // Initialize: Load logs on page load
    $(document).ready(function() {
        $('.refresh-logs').click();
    });
    
    // View activity details
    $('.view-details').click(function() {
        var details = $(this).data('details');
        try {
            var parsed = JSON.parse(details);
            details = JSON.stringify(parsed, null, 2);
        } catch (e) {
            // Use raw details if not JSON
        }
        
        alert('Activity Details:\n\n' + details);
    });
    
    // Export settings
    $('.export-settings').click(function() {
        var button = $(this);
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Exporting...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_export_settings',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Export Settings');
            
            if (response.success) {
                var blob = new Blob([response.data], { type: 'application/json' });
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'azure-plugin-settings-' + new Date().toISOString().slice(0, 10) + '.json';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else {
                alert('❌ Failed to export settings: ' + (response.data || 'Unknown error'));
            }
        });
    });
    
    // Import settings
    $('.import-settings').click(function() {
        $('#import-settings-file').click();
    });
    
    $('#import-settings-file').change(function() {
        var file = this.files[0];
        if (!file) return;
        
        var reader = new FileReader();
        reader.onload = function(e) {
            if (!confirm('Are you sure you want to import these settings? This will overwrite your current configuration.')) {
                return;
            }
            
            $.post(azure_plugin_ajax.ajax_url, {
                action: 'azure_import_settings',
                settings_data: e.target.result,
                nonce: azure_plugin_ajax.nonce
            }, function(response) {
                if (response.success) {
                    alert('✅ Settings imported successfully! The page will reload.');
                    location.reload();
                } else {
                    alert('❌ Failed to import settings: ' + (response.data || 'Unknown error'));
                }
            });
        };
        reader.readAsText(file);
    });
    
    // Auto-refresh activity
    setInterval(function() {
        if ($('.recent-activity-section').length) {
            // Silently refresh activity table
            $.post(azure_plugin_ajax.ajax_url, {
                action: 'azure_get_recent_activity',
                nonce: azure_plugin_ajax.nonce
            }, function(response) {
                if (response.success && response.data) {
                    $('.activity-table tbody').html(response.data);
                }
            });
        }
    }, 30000); // Refresh every 30 seconds
});
</script>

<style>
.activity-stats-section {
    margin-bottom: 30px;
}

.log-controls-section {
    margin-bottom: 20px;
}

.log-controls {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.log-level-filter,
.module-filter {
    min-width: 120px;
}

.debug-logs-section {
    margin-bottom: 30px;
}

.log-viewer {
    background: #1e1e1e;
    color: #f0f0f0;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.4;
    padding: 20px;
    border-radius: 4px;
    min-height: 800px;
    max-height: 1200px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
    border: 2px solid #333;
}

.log-line {
    margin-bottom: 1px;
    padding: 1px 0;
    display: block;
    white-space: nowrap;
}

.log-line.error {
    color: #ff6b6b;
}

.log-line.warning {
    color: #ffd93d;
}

.log-line.info {
    color: #74c0fc;
}

.log-line.debug {
    color: #95f985;
}

.log-timestamp {
    color: #00a0d2;
    font-weight: bold;
    margin-right: 8px;
}

.log-level {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: bold;
    margin-right: 8px;
    min-width: 50px;
    text-align: center;
}

.log-level.level-error {
    background-color: #dc3545;
    color: white;
}

.log-level.level-warning {
    background-color: #ffc107;
    color: #212529;
}

.log-level.level-info {
    background-color: #007cba;
    color: white;
}

.log-level.level-debug {
    background-color: #6c757d;
    color: white;
}

.log-message {
    margin-left: 4px;
}

.activity-table {
    margin-top: 15px;
}

.activity-table th,
.activity-table td {
    padding: 10px;
}

.module-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    color: white;
}

.module-badge.module-sso {
    background: #0073aa;
}

.module-badge.module-backup {
    background: #46b450;
}

.module-badge.module-calendar {
    background: #dc3232;
}

.module-badge.module-email {
    background: #ffb900;
}

.module-badge.module-admin {
    background: #826eb4;
}

.module-badge.module-pta {
    background: #d63638;
}

.module-badge.module-system {
    background: #555;
}

.system-info-section {
    margin: 30px 0;
}

.system-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.info-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
}

.info-card h4 {
    margin-top: 0;
    color: #0073aa;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.info-card ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.info-card li {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
}

.info-card li:last-child {
    border-bottom: none;
}

.info-card code {
    font-size: 11px;
    background: #f9f9f9;
    padding: 2px 4px;
    border-radius: 3px;
}

.settings-export-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    margin-top: 30px;
}

.export-import-controls {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-top: 15px;
}

.export-section,
.import-section {
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f9f9f9;
}

.export-section h4,
.import-section h4 {
    margin-top: 0;
    color: #333;
}

.export-section p,
.import-section p {
    color: #666;
    font-size: 13px;
}

<?php
// Helper method for log level classes (this would be in the admin class)
function get_log_level_class($line) {
    if (strpos($line, '[ERROR]') !== false) return 'error';
    if (strpos($line, '[WARNING]') !== false) return 'warning';
    if (strpos($line, '[DEBUG]') !== false) return 'debug';
    return 'info';
}
?>
</style>
