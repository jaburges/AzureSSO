<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get calendar authentication status
$auth_status = array('authenticated' => false);
if (class_exists('Azure_Calendar_Auth')) {
    $auth = new Azure_Calendar_Auth();
    $auth_status = $auth->get_auth_status();
}

// Get user calendars if authenticated
$user_calendars = array();
if ($auth_status['authenticated'] && class_exists('Azure_Calendar_GraphAPI')) {
    $graph_api = new Azure_Calendar_GraphAPI();
    $user_calendars = $graph_api->get_calendars();
}

// Handle auth success message
$show_auth_success = isset($_GET['auth']) && $_GET['auth'] === 'success';
?>

<div class="wrap">
    <h1>Azure Plugin - Calendar Settings</h1>
    
    <!-- Module Toggle Section -->
    <div class="module-status-section">
        <h2>Module Status</h2>
        <div class="module-toggle-card">
            <div class="module-info">
                <h3><span class="dashicons dashicons-calendar-alt"></span> Calendar Embed Module</h3>
                <p>Embed Microsoft Outlook calendars in your website</p>
            </div>
            <div class="module-control">
                <label class="switch">
                    <input type="checkbox" class="calendar-module-toggle" <?php checked(Azure_Settings::is_module_enabled('calendar')); ?> />
                    <span class="slider"></span>
                </label>
                <span class="toggle-status"><?php echo Azure_Settings::is_module_enabled('calendar') ? 'Enabled' : 'Disabled'; ?></span>
            </div>
        </div>
        <?php if (!Azure_Settings::is_module_enabled('calendar')): ?>
        <div class="notice notice-warning inline">
            <p><strong>Calendar module is disabled.</strong> Enable it above or in the <a href="<?php echo admin_url('admin.php?page=azure-plugin'); ?>">main settings</a> to use calendar functionality.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($show_auth_success): ?>
    <div class="notice notice-success is-dismissible">
        <p><strong>Success!</strong> Calendar authorization completed successfully. You can now manage your calendars below.</p>
    </div>
    <?php endif; ?>
    
    <div class="azure-calendar-dashboard">
        <!-- Authentication Status -->
        <div class="calendar-auth-section">
            <h2>Authentication Status</h2>
            
            <div class="auth-status-card">
                <?php if ($auth_status['authenticated']): ?>
                    <?php if (isset($auth_status['expired']) && $auth_status['expired']): ?>
                    <div class="auth-status warning">
                        <span class="dashicons dashicons-warning"></span>
                        <div class="auth-info">
                            <strong>Token Expired</strong>
                            <p>Your access token has expired. Please re-authorize to continue using calendar features.</p>
                            <p><small>Expired: <?php echo esc_html($auth_status['expires_at']); ?></small></p>
                        </div>
                        <div class="auth-actions">
                            <button type="button" class="button button-primary authorize-calendar">
                                Re-authorize Calendar Access
                            </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="auth-status success">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <div class="auth-info">
                            <strong>Authenticated</strong>
                            <p>Calendar access is working properly.</p>
                            <p><small>Expires: <?php echo esc_html($auth_status['expires_at']); ?> (<?php echo esc_html($auth_status['expires_in_hours']); ?> hours)</small></p>
                        </div>
                        <div class="auth-actions">
                            <button type="button" class="button test-calendar-connection">
                                Test Connection
                            </button>
                            <button type="button" class="button button-secondary revoke-calendar-auth">
                                Revoke Access
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                <div class="auth-status error">
                    <span class="dashicons dashicons-dismiss"></span>
                    <div class="auth-info">
                        <strong>Not Authenticated</strong>
                        <p>You need to authorize access to your Microsoft calendars before you can use calendar features.</p>
                    </div>
                    <div class="auth-actions">
                        <button type="button" class="button button-primary authorize-calendar">
                            Authorize Calendar Access
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Available Calendars -->
        <?php if (!empty($user_calendars)): ?>
        <div class="calendar-list-section">
            <h2>Your Calendars</h2>
            
            <div class="calendars-grid">
                <?php foreach ($user_calendars as $calendar): ?>
                <div class="calendar-item" data-calendar-id="<?php echo esc_attr($calendar['id']); ?>">
                    <div class="calendar-header">
                        <h3><?php echo esc_html($calendar['name']); ?></h3>
                        <div class="calendar-actions">
                            <button type="button" class="button button-small preview-calendar" data-calendar-id="<?php echo esc_attr($calendar['id']); ?>">
                                Preview
                            </button>
                            <button type="button" class="button button-small sync-calendar" data-calendar-id="<?php echo esc_attr($calendar['id']); ?>">
                                Sync
                            </button>
                        </div>
                    </div>
                    
                    <div class="calendar-info">
                        <p><strong>ID:</strong> <code><?php echo esc_html($calendar['id']); ?></code></p>
                        <?php if (isset($calendar['description'])): ?>
                        <p><strong>Description:</strong> <?php echo esc_html($calendar['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="calendar-shortcodes">
                            <h4>Shortcodes for this calendar:</h4>
                            <div class="shortcode-examples">
                                <div class="shortcode">
                                    <label>Calendar View:</label>
                                    <input type="text" readonly value='[azure_calendar id="<?php echo esc_attr($calendar['id']); ?>" view="month"]' onclick="this.select();">
                                </div>
                                <div class="shortcode">
                                    <label>Events List:</label>
                                    <input type="text" readonly value='[azure_calendar_events id="<?php echo esc_attr($calendar['id']); ?>" limit="10"]' onclick="this.select();">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Settings Form -->
        <div class="calendar-settings-section">
            <form method="post" action="">
                <?php wp_nonce_field('azure_plugin_settings'); ?>
                
                <!-- Credentials Section -->
                <?php if (!($settings['use_common_credentials'] ?? true)): ?>
                <div class="credentials-section">
                    <h2>Azure Calendar Credentials</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Client ID</th>
                            <td>
                                <input type="text" name="calendar_client_id" value="<?php echo esc_attr($settings['calendar_client_id'] ?? ''); ?>" class="regular-text" />
                                <p class="description">Your Azure App Registration Client ID</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Client Secret</th>
                            <td>
                                <input type="password" name="calendar_client_secret" value="<?php echo esc_attr($settings['calendar_client_secret'] ?? ''); ?>" class="regular-text" />
                                <p class="description">Your Azure App Registration Client Secret</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Tenant ID</th>
                            <td>
                                <input type="text" name="calendar_tenant_id" value="<?php echo esc_attr($settings['calendar_tenant_id'] ?? 'common'); ?>" class="regular-text" />
                                <p class="description">Your Azure Tenant ID (or 'common' for multi-tenant)</p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <button type="button" class="button test-credentials" 
                                    data-client-id-field="calendar_client_id" 
                                    data-client-secret-field="calendar_client_secret" 
                                    data-tenant-id-field="calendar_tenant_id">
                                    Test Credentials
                                </button>
                                <span class="credentials-status"></span>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>
                
                <!-- Display Settings -->
                <div class="calendar-display">
                    <h2>Display Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Default Timezone</th>
                            <td>
                                <select name="calendar_default_timezone">
                                    <?php
                                    $current_timezone = $settings['calendar_default_timezone'] ?? 'America/New_York';
                                    $timezones = array(
                                        'America/New_York' => 'Eastern Time (US & Canada)',
                                        'America/Chicago' => 'Central Time (US & Canada)',
                                        'America/Denver' => 'Mountain Time (US & Canada)',
                                        'America/Los_Angeles' => 'Pacific Time (US & Canada)',
                                        'America/Phoenix' => 'Arizona',
                                        'America/Anchorage' => 'Alaska',
                                        'Pacific/Honolulu' => 'Hawaii',
                                        'UTC' => 'UTC',
                                        'Europe/London' => 'London',
                                        'Europe/Paris' => 'Paris',
                                        'Europe/Berlin' => 'Berlin',
                                        'Asia/Tokyo' => 'Tokyo',
                                        'Asia/Shanghai' => 'Shanghai',
                                        'Australia/Sydney' => 'Sydney'
                                    );
                                    ?>
                                    <?php foreach ($timezones as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($current_timezone, $value); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Default timezone for calendar displays</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Default View</th>
                            <td>
                                <select name="calendar_default_view">
                                    <?php
                                    $current_view = $settings['calendar_default_view'] ?? 'month';
                                    $views = array(
                                        'month' => 'Month View',
                                        'week' => 'Week View',
                                        'day' => 'Day View',
                                        'list' => 'List View'
                                    );
                                    ?>
                                    <?php foreach ($views as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($current_view, $value); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Default view for calendar displays</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Color Theme</th>
                            <td>
                                <select name="calendar_default_color_theme">
                                    <?php
                                    $current_theme = $settings['calendar_default_color_theme'] ?? 'blue';
                                    $themes = array(
                                        'blue' => 'Blue',
                                        'green' => 'Green',
                                        'red' => 'Red',
                                        'purple' => 'Purple',
                                        'orange' => 'Orange',
                                        'gray' => 'Gray'
                                    );
                                    ?>
                                    <?php foreach ($themes as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($current_theme, $value); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Default color theme for calendar events</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Performance Settings -->
                <div class="calendar-performance">
                    <h2>Performance Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Cache Duration</th>
                            <td>
                                <input type="number" name="calendar_cache_duration" value="<?php echo intval($settings['calendar_cache_duration'] ?? 3600); ?>" min="300" max="86400" class="small-text" />
                                <span>seconds</span>
                                <p class="description">How long to cache calendar data (300-86400 seconds)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Max Events Per Calendar</th>
                            <td>
                                <input type="number" name="calendar_max_events_per_calendar" value="<?php echo intval($settings['calendar_max_events_per_calendar'] ?? 100); ?>" min="10" max="1000" class="small-text" />
                                <span>events</span>
                                <p class="description">Maximum number of events to fetch per calendar (10-1000)</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <input type="submit" name="azure_plugin_submit" class="button-primary" value="Save Calendar Settings" />
                </p>
            </form>
        </div>
        
        <!-- Shortcode Documentation -->
        <div class="calendar-shortcodes-section">
            <h2>Calendar Shortcodes</h2>
            
            <div class="shortcode-documentation">
                <div class="shortcode-example">
                    <h4>Full Calendar Display</h4>
                    <code>[azure_calendar id="calendar_id" view="month" height="600px"]</code>
                    
                    <h5>Parameters:</h5>
                    <ul>
                        <li><code>id</code> - Required. The calendar ID from above</li>
                        <li><code>view</code> - month, week, day, list (default: month)</li>
                        <li><code>height</code> - CSS height value (default: 600px)</li>
                        <li><code>width</code> - CSS width value (default: 100%)</li>
                        <li><code>timezone</code> - Override default timezone</li>
                        <li><code>max_events</code> - Maximum events to show</li>
                        <li><code>show_weekends</code> - true/false (default: true)</li>
                    </ul>
                </div>
                
                <div class="shortcode-example">
                    <h4>Events List</h4>
                    <code>[azure_calendar_events id="calendar_id" limit="10" format="list"]</code>
                    
                    <h5>Parameters:</h5>
                    <ul>
                        <li><code>id</code> - Required. The calendar ID</li>
                        <li><code>limit</code> - Number of events to show (default: 10)</li>
                        <li><code>format</code> - list, grid, compact (default: list)</li>
                        <li><code>upcoming_only</code> - true/false (default: true)</li>
                        <li><code>show_dates</code> - true/false (default: true)</li>
                        <li><code>show_times</code> - true/false (default: true)</li>
                        <li><code>show_location</code> - true/false (default: true)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Calendar Preview Modal -->
<div id="calendar-preview-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Calendar Preview</h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="calendar-preview-container">
                Loading...
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle calendar authorization
    $('.authorize-calendar').click(function() {
        var button = $(this);
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Authorizing...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_calendar_authorize',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (response.success && response.data.auth_url) {
                // Open authorization URL in new window
                window.open(response.data.auth_url, 'azure_auth', 'width=600,height=700');
                
                // Check periodically for auth completion
                var checkAuth = setInterval(function() {
                    $.post(azure_plugin_ajax.ajax_url, {
                        action: 'azure_calendar_check_auth',
                        nonce: azure_plugin_ajax.nonce
                    }, function(authResponse) {
                        if (authResponse.success) {
                            clearInterval(checkAuth);
                            location.reload();
                        }
                    });
                }, 2000);
                
                button.prop('disabled', false).html('Authorize Calendar Access');
            } else {
                alert('❌ Failed to generate authorization URL: ' + (response.data || 'Unknown error'));
                button.prop('disabled', false).html('Authorize Calendar Access');
            }
        });
    });
    
    // Handle revoke authorization
    $('.revoke-calendar-auth').click(function() {
        if (!confirm('Are you sure you want to revoke calendar access? You will need to re-authorize to use calendar features.')) {
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).text('Revoking...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_calendar_revoke',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('❌ Failed to revoke access: ' + (response.data || 'Unknown error'));
                button.prop('disabled', false).text('Revoke Access');
            }
        });
    });
    
    // Handle calendar preview
    $('.preview-calendar').click(function() {
        var calendarId = $(this).data('calendar-id');
        var modal = $('#calendar-preview-modal');
        var container = $('#calendar-preview-container');
        
        modal.show();
        container.html('Loading calendar preview...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_calendar_get_events',
            calendar_id: calendarId,
            max_events: 10,
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (response.success) {
                var events = response.data;
                var html = '<h3>Upcoming Events</h3>';
                
                if (events.length === 0) {
                    html += '<p>No upcoming events found.</p>';
                } else {
                    html += '<ul>';
                    events.forEach(function(event) {
                        var startDate = new Date(event.start).toLocaleString();
                        html += '<li><strong>' + event.title + '</strong><br>';
                        html += '<small>' + startDate + '</small>';
                        if (event.location) {
                            html += '<br><em>Location: ' + event.location + '</em>';
                        }
                        html += '</li>';
                    });
                    html += '</ul>';
                }
                
                container.html(html);
            } else {
                container.html('<p class="error">Failed to load calendar events: ' + (response.data || 'Unknown error') + '</p>');
            }
        });
    });
    
    // Handle calendar sync
    $('.sync-calendar').click(function() {
        var calendarId = $(this).data('calendar-id');
        var button = $(this);
        
        button.prop('disabled', true).text('Syncing...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_sync_calendar',
            calendar_id: calendarId,
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            if (response.success) {
                alert('✅ Calendar synced successfully!');
            } else {
                alert('❌ Calendar sync failed: ' + (response.data || 'Unknown error'));
            }
            
            button.prop('disabled', false).text('Sync');
        });
    });
    
    // Handle modal close
    $('.modal-close, .modal').click(function(e) {
        if (e.target === this) {
            $('.modal').hide();
        }
    });
    
    // Test calendar connection
    $('.test-calendar-connection').click(function() {
        var button = $(this);
        button.prop('disabled', true).text('Testing...');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_test_calendar_connection',
            nonce: azure_plugin_ajax.nonce
        }, function(response) {
            button.prop('disabled', false).text('Test Connection');
            
            if (response.success) {
                alert('✅ Calendar connection successful!\n\n' + response.data.message);
            } else {
                alert('❌ Calendar connection failed: ' + (response.data || 'Unknown error'));
            }
        });
    });
    
    // Handle module toggle
    $('.calendar-module-toggle').change(function() {
        var enabled = $(this).is(':checked');
        var statusText = $('.toggle-status');
        
        $.post(azure_plugin_ajax.ajax_url, {
            action: 'azure_toggle_module',
            module: 'calendar',
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
                $('.calendar-module-toggle').prop('checked', !enabled);
                alert('Failed to toggle Calendar module: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            // Revert toggle if failed
            $('.calendar-module-toggle').prop('checked', !enabled);
            alert('Network error occurred');
        });
    });
});
</script>

<style>
.calendar-auth-section {
    margin-bottom: 30px;
}

.auth-status-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
}

.auth-status {
    display: flex;
    align-items: center;
    gap: 15px;
}

.auth-status .dashicons {
    font-size: 24px;
}

.auth-status.success .dashicons {
    color: #46b450;
}

.auth-status.warning .dashicons {
    color: #ffb900;
}

.auth-status.error .dashicons {
    color: #dc3232;
}

.auth-info {
    flex: 1;
}

.auth-info h3 {
    margin: 0 0 5px 0;
}

.auth-info p {
    margin: 0;
    color: #666;
}

.auth-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.calendars-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.calendar-item {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.calendar-header h3 {
    margin: 0;
    color: #0073aa;
}

.calendar-actions {
    display: flex;
    gap: 5px;
}

.calendar-info p {
    margin: 10px 0;
    font-size: 13px;
}

.calendar-shortcodes {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.calendar-shortcodes h4 {
    margin: 0 0 10px 0;
    font-size: 13px;
    color: #666;
}

.shortcode-examples .shortcode {
    margin: 8px 0;
}

.shortcode-examples label {
    display: inline-block;
    width: 80px;
    font-size: 12px;
    color: #666;
}

.shortcode-examples input {
    width: calc(100% - 85px);
    font-size: 11px;
    font-family: monospace;
    background: #f9f9f9;
    border: 1px solid #ddd;
    padding: 4px 6px;
    cursor: pointer;
}

.calendar-display,
.calendar-performance {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    margin-bottom: 20px;
}

.calendar-shortcodes-section {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    margin-top: 20px;
}

.shortcode-documentation {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
}

.shortcode-example {
    border: 1px solid #ddd;
    padding: 15px;
    border-radius: 4px;
    background: #f9f9f9;
}

.shortcode-example h4 {
    margin-top: 0;
    color: #0073aa;
}

.shortcode-example code {
    display: block;
    background: #fff;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 10px 0;
    font-size: 13px;
    word-break: break-all;
}

.shortcode-example h5 {
    margin: 15px 0 5px 0;
    color: #333;
}

.shortcode-example ul {
    margin: 0;
    padding-left: 20px;
}

.shortcode-example li {
    margin-bottom: 5px;
    font-size: 13px;
}

/* Modal Styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: #fff;
    border-radius: 8px;
    max-width: 800px;
    width: 90%;
    max-height: 80%;
    overflow-y: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 20px;
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

/* Contrast Improvements */
.calendar-auth-section,
.calendar-display,
.calendar-authentication {
    background: #fff !important;
    color: #333 !important;
}

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
.calendar-auth-section h2,
.calendar-display h2,
.calendar-authentication h2 {
    color: #333 !important;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

/* Calendar List Items */
.calendar-item {
    background: #fff !important;
    color: #333 !important;
    border: 1px solid #ccd0d4 !important;
}

.calendar-item h4 {
    color: #0073aa !important;
}

.calendar-item p {
    color: #666 !important;
}

/* WordPress Dark Theme Overrides */
body.admin-color-midnight .module-toggle-card,
body.admin-color-midnight .calendar-auth-section,
body.admin-color-midnight .calendar-display,
body.admin-color-midnight .calendar-authentication,
body.admin-color-midnight .calendar-item {
    background: #fff !important;
    color: #333 !important;
    border-color: #ccd0d4 !important;
}
</style>
