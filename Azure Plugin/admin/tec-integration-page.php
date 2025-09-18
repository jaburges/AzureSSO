<?php
/**
 * TEC Integration Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

// Get current settings
$settings = Azure_Settings::get_settings();
$tec_integration = Azure_TEC_Integration::get_instance();

// Handle form submission
if (isset($_POST['submit']) && wp_verify_nonce($_POST['azure_tec_nonce'], 'azure_tec_settings')) {
    
    // Update TEC integration settings
    $tec_settings = array(
        'enable_tec_integration' => isset($_POST['enable_tec_integration']) ? 1 : 0,
        'tec_outlook_calendar_id' => sanitize_text_field($_POST['tec_outlook_calendar_id'] ?? 'primary'),
        'tec_default_venue' => sanitize_text_field($_POST['tec_default_venue'] ?? 'School Campus'),
        'tec_default_organizer' => sanitize_text_field($_POST['tec_default_organizer'] ?? 'PTSA'),
        'tec_organizer_email' => sanitize_email($_POST['tec_organizer_email'] ?? get_option('admin_email')),
        'tec_sync_frequency' => sanitize_text_field($_POST['tec_sync_frequency'] ?? 'hourly'),
        'tec_conflict_resolution' => sanitize_text_field($_POST['tec_conflict_resolution'] ?? 'outlook_wins'),
        'tec_include_event_url' => isset($_POST['tec_include_event_url']) ? 1 : 0,
        'tec_event_footer' => sanitize_textarea_field($_POST['tec_event_footer'] ?? ''),
        'tec_default_category' => sanitize_text_field($_POST['tec_default_category'] ?? 'School Event')
    );
    
    // Merge with existing settings
    $updated_settings = array_merge($settings, $tec_settings);
    
    // Save settings
    if (Azure_Settings::save_settings($updated_settings)) {
        echo '<div class="notice notice-success"><p>TEC Integration settings saved successfully!</p></div>';
        
        // Schedule sync cron if enabled
        if ($tec_settings['enable_tec_integration'] && $settings['enable_calendar']) {
            $frequency = $tec_settings['tec_sync_frequency'];
            if (!wp_next_scheduled('azure_tec_sync_from_outlook')) {
                wp_schedule_event(time(), $frequency, 'azure_tec_sync_from_outlook');
            }
        } else {
            wp_clear_scheduled_hook('azure_tec_sync_from_outlook');
        }
        
        // Refresh settings
        $settings = Azure_Settings::get_settings();
    } else {
        echo '<div class="notice notice-error"><p>Failed to save TEC Integration settings.</p></div>';
    }
}

// Get sync statistics
$sync_engine = new Azure_TEC_Sync_Engine();
$sync_stats = $sync_engine->get_sync_statistics();

?>

<div class="wrap">
    <h1>TEC Integration Settings</h1>
    
    <?php if (!class_exists('Tribe__Events__Main')): ?>
    <div class="notice notice-warning">
        <p><strong>Warning:</strong> The Events Calendar plugin is not active. TEC Integration requires The Events Calendar to be installed and activated.</p>
    </div>
    <?php endif; ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('azure_tec_settings', 'azure_tec_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">Enable TEC Integration</th>
                <td>
                    <label>
                        <input type="checkbox" name="enable_tec_integration" value="1" 
                               <?php checked($settings['enable_tec_integration'] ?? false); ?>
                               <?php echo !$settings['enable_calendar'] ? 'disabled' : ''; ?>>
                        Enable bidirectional sync between The Events Calendar and Outlook
                    </label>
                    <?php if (!$settings['enable_calendar']): ?>
                    <p class="description" style="color: #d63638;">
                        <strong>Calendar functionality must be enabled first.</strong> 
                        <a href="<?php echo admin_url('admin.php?page=azure-calendar'); ?>">Enable Calendar Module</a>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <h2>Sync Settings</h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">Outlook Calendar</th>
                <td>
                    <select name="tec_outlook_calendar_id">
                        <option value="primary" <?php selected($settings['tec_outlook_calendar_id'] ?? 'primary', 'primary'); ?>>
                            Primary Calendar
                        </option>
                        <!-- Additional calendar options would be loaded via AJAX in production -->
                    </select>
                    <p class="description">Select which Outlook calendar to sync with TEC events.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Sync Frequency</th>
                <td>
                    <select name="tec_sync_frequency">
                        <option value="hourly" <?php selected($settings['tec_sync_frequency'] ?? 'hourly', 'hourly'); ?>>
                            Every Hour
                        </option>
                        <option value="twicedaily" <?php selected($settings['tec_sync_frequency'] ?? 'hourly', 'twicedaily'); ?>>
                            Twice Daily
                        </option>
                        <option value="daily" <?php selected($settings['tec_sync_frequency'] ?? 'hourly', 'daily'); ?>>
                            Daily
                        </option>
                        <option value="manual" <?php selected($settings['tec_sync_frequency'] ?? 'hourly', 'manual'); ?>>
                            Manual Only
                        </option>
                    </select>
                    <p class="description">How often to automatically sync events from Outlook to TEC.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Conflict Resolution</th>
                <td>
                    <select name="tec_conflict_resolution">
                        <option value="outlook_wins" <?php selected($settings['tec_conflict_resolution'] ?? 'outlook_wins', 'outlook_wins'); ?>>
                            Outlook Wins (Recommended)
                        </option>
                        <option value="tec_wins" <?php selected($settings['tec_conflict_resolution'] ?? 'outlook_wins', 'tec_wins'); ?>>
                            TEC Wins
                        </option>
                        <option value="manual" <?php selected($settings['tec_conflict_resolution'] ?? 'outlook_wins', 'manual'); ?>>
                            Manual Resolution
                        </option>
                    </select>
                    <p class="description">How to resolve conflicts when the same event is modified in both systems.</p>
                </td>
            </tr>
        </table>
        
        <h2>Default Event Settings</h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">Default Venue</th>
                <td>
                    <input type="text" name="tec_default_venue" 
                           value="<?php echo esc_attr($settings['tec_default_venue'] ?? 'School Campus'); ?>" 
                           class="regular-text">
                    <p class="description">Default venue for events synced from Outlook.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Default Organizer</th>
                <td>
                    <input type="text" name="tec_default_organizer" 
                           value="<?php echo esc_attr($settings['tec_default_organizer'] ?? 'PTSA'); ?>" 
                           class="regular-text">
                    <p class="description">Default organizer name for events.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Organizer Email</th>
                <td>
                    <input type="email" name="tec_organizer_email" 
                           value="<?php echo esc_attr($settings['tec_organizer_email'] ?? get_option('admin_email')); ?>" 
                           class="regular-text">
                    <p class="description">Email address for the event organizer.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Default Category</th>
                <td>
                    <input type="text" name="tec_default_category" 
                           value="<?php echo esc_attr($settings['tec_default_category'] ?? 'School Event'); ?>" 
                           class="regular-text">
                    <p class="description">Default category for events synced from Outlook.</p>
                </td>
            </tr>
        </table>
        
        <h2>Content Settings</h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">Include Event URL</th>
                <td>
                    <label>
                        <input type="checkbox" name="tec_include_event_url" value="1" 
                               <?php checked($settings['tec_include_event_url'] ?? true); ?>>
                        Include event URL in Outlook event description
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Event Footer</th>
                <td>
                    <textarea name="tec_event_footer" rows="3" cols="50" class="large-text"><?php 
                        echo esc_textarea($settings['tec_event_footer'] ?? ''); 
                    ?></textarea>
                    <p class="description">Text to append to all event descriptions synced to Outlook.</p>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Save TEC Integration Settings'); ?>
    </form>
    
    <hr>
    
    <h2>Sync Status</h2>
    
    <div class="azure-tec-stats">
        <div class="azure-tec-stat-box">
            <h3>Total TEC Events</h3>
            <p class="azure-tec-stat-number"><?php echo intval($sync_stats['total_tec_events'] ?? 0); ?></p>
        </div>
        
        <div class="azure-tec-stat-box">
            <h3>Synced Events</h3>
            <p class="azure-tec-stat-number"><?php echo intval($sync_stats['synced_events'] ?? 0); ?></p>
        </div>
        
        <div class="azure-tec-stat-box">
            <h3>Pending Sync</h3>
            <p class="azure-tec-stat-number"><?php echo intval($sync_stats['pending_events'] ?? 0); ?></p>
        </div>
        
        <div class="azure-tec-stat-box">
            <h3>Sync Errors</h3>
            <p class="azure-tec-stat-number"><?php echo intval($sync_stats['error_events'] ?? 0); ?></p>
        </div>
    </div>
    
    <?php if (!empty($sync_stats['last_sync'])): ?>
    <p><strong>Last Sync:</strong> <?php echo esc_html(date('Y-m-d H:i:s', strtotime($sync_stats['last_sync']))); ?></p>
    <?php endif; ?>
    
    <h2>Sync Actions</h2>
    
    <div class="azure-tec-actions">
        <button type="button" class="button button-primary" onclick="azureTecBulkSync('sync_to_outlook')">
            Sync All TEC Events to Outlook
        </button>
        
        <button type="button" class="button button-secondary" onclick="azureTecBulkSync('sync_from_outlook')">
            Sync All Outlook Events to TEC
        </button>
        
        <button type="button" class="button" onclick="azureTecRefreshStats()">
            Refresh Statistics
        </button>
    </div>
    
    <div id="azure-tec-sync-progress" style="display: none;">
        <p>Sync in progress... Please wait.</p>
        <div class="progress-bar">
            <div class="progress-bar-fill"></div>
        </div>
    </div>
</div>

<style>
.azure-tec-stats {
    display: flex;
    gap: 20px;
    margin: 20px 0;
}

.azure-tec-stat-box {
    background: #f1f1f1;
    padding: 20px;
    border-radius: 5px;
    text-align: center;
    min-width: 120px;
}

.azure-tec-stat-box h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
}

.azure-tec-stat-number {
    font-size: 24px;
    font-weight: bold;
    margin: 0;
    color: #0073aa;
}

.azure-tec-actions {
    margin: 20px 0;
}

.azure-tec-actions .button {
    margin-right: 10px;
}

.progress-bar {
    width: 100%;
    height: 20px;
    background-color: #f1f1f1;
    border-radius: 10px;
    overflow: hidden;
    margin-top: 10px;
}

.progress-bar-fill {
    height: 100%;
    background-color: #0073aa;
    width: 0%;
    transition: width 0.3s ease;
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Bulk sync function
    window.azureTecBulkSync = function(action) {
        $('#azure-tec-sync-progress').show();
        $('.progress-bar-fill').css('width', '0%');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'azure_tec_bulk_sync',
                action_type: action,
                nonce: '<?php echo wp_create_nonce('azure_tec_action'); ?>'
            },
            success: function(response) {
                $('#azure-tec-sync-progress').hide();
                $('.progress-bar-fill').css('width', '100%');
                
                if (response.success) {
                    alert('Bulk sync completed successfully!');
                    azureTecRefreshStats();
                } else {
                    alert('Bulk sync failed: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                $('#azure-tec-sync-progress').hide();
                alert('Bulk sync failed due to a network error.');
            }
        });
    };
    
    // Refresh statistics
    window.azureTecRefreshStats = function() {
        location.reload();
    };
    
    // Manual sync function (for individual events)
    window.azureTecManualSync = function(postId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'azure_tec_manual_sync',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('azure_tec_action'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Manual sync initiated successfully!');
                    location.reload();
                } else {
                    alert('Manual sync failed: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Manual sync failed due to a network error.');
            }
        });
    };
    
    // Break sync function (for individual events)
    window.azureTecBreakSync = function(postId) {
        if (confirm('Are you sure you want to break the sync relationship for this event? This action cannot be undone.')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'azure_tec_break_sync',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('azure_tec_action'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Sync relationship broken successfully!');
                        location.reload();
                    } else {
                        alert('Failed to break sync: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Failed to break sync due to a network error.');
                }
            });
        }
    };
});
</script>