<?php
/**
 * The Events Calendar Sync Engine
 * Handles bidirectional synchronization between TEC and Outlook Calendar
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_TEC_Sync_Engine {
    
    private $graph_api;
    private $data_mapper;
    private $calendar_id;
    
    public function __construct() {
        // Initialize Graph API client
        if (class_exists('Azure_Calendar_GraphAPI')) {
            $this->graph_api = new Azure_Calendar_GraphAPI();
        }
        
        // Initialize data mapper
        if (class_exists('Azure_TEC_Data_Mapper')) {
            $this->data_mapper = new Azure_TEC_Data_Mapper();
        }
        
        // Get default calendar ID from settings
        $settings = Azure_Settings::get_all_settings();
        $this->calendar_id = $settings['tec_outlook_calendar_id'] ?? 'primary';
        
        Azure_Logger::debug('TEC Sync Engine: Initialized with calendar ID: ' . $this->calendar_id, 'TEC');
    }
    
    /**
     * Sync a TEC event to Outlook
     */
    public function sync_tec_to_outlook($tec_event_id) {
        if (!$this->graph_api || !$this->data_mapper) {
            Azure_Logger::error('TEC Sync Engine: Required components not available', 'TEC');
            return false;
        }
        
        Azure_Logger::info("TEC Sync Engine: Starting sync to Outlook for TEC event {$tec_event_id}", 'TEC');
        
        try {
            // Get TEC event data
            $tec_event = get_post($tec_event_id);
            
            if (!$tec_event || $tec_event->post_type !== 'tribe_events') {
                Azure_Logger::error("TEC Sync Engine: Invalid TEC event ID: {$tec_event_id}", 'TEC');
                return false;
            }
            
            // Check if event already exists in Outlook
            $outlook_event_id = get_post_meta($tec_event_id, '_outlook_event_id', true);
            
            // Map TEC event to Outlook format
            $outlook_event_data = $this->data_mapper->map_tec_to_outlook($tec_event_id);
            
            if (!$outlook_event_data) {
                Azure_Logger::error("TEC Sync Engine: Failed to map TEC event {$tec_event_id} to Outlook format", 'TEC');
                return false;
            }
            
            if ($outlook_event_id) {
                // Update existing Outlook event
                Azure_Logger::debug("TEC Sync Engine: Updating existing Outlook event {$outlook_event_id}", 'TEC');
                $result = $this->graph_api->update_event($this->calendar_id, $outlook_event_id, $outlook_event_data);
                
                if ($result) {
                    Azure_Logger::info("TEC Sync Engine: Successfully updated Outlook event {$outlook_event_id}", 'TEC');
                    $this->update_sync_timestamp($tec_event_id);
                    return true;
                } else {
                    // Event might have been deleted in Outlook, try creating new one
                    Azure_Logger::warning("TEC Sync Engine: Failed to update Outlook event, trying to create new one", 'TEC');
                    delete_post_meta($tec_event_id, '_outlook_event_id');
                    $outlook_event_id = null;
                }
            }
            
            if (!$outlook_event_id) {
                // Create new Outlook event
                Azure_Logger::debug("TEC Sync Engine: Creating new Outlook event", 'TEC');
                $result = $this->graph_api->create_event($this->calendar_id, $outlook_event_data);
                
                if ($result && isset($result['id'])) {
                    $new_outlook_event_id = $result['id'];
                    Azure_Logger::info("TEC Sync Engine: Successfully created Outlook event {$new_outlook_event_id}", 'TEC');
                    
                    // Store Outlook event ID in TEC event metadata
                    update_post_meta($tec_event_id, '_outlook_event_id', $new_outlook_event_id);
                    $this->update_sync_timestamp($tec_event_id);
                    
                    return true;
                } else {
                    Azure_Logger::error("TEC Sync Engine: Failed to create Outlook event", 'TEC');
                    return false;
                }
            }
            
        } catch (Exception $e) {
            Azure_Logger::error("TEC Sync Engine: Exception syncing TEC event {$tec_event_id}: " . $e->getMessage(), 'TEC');
            return false;
        }
        
        return false;
    }
    
    /**
     * Sync Outlook events to TEC
     */
    public function sync_outlook_to_tec($start_date = null, $end_date = null) {
        if (!$this->graph_api || !$this->data_mapper) {
            Azure_Logger::error('TEC Sync Engine: Required components not available', 'TEC');
            return false;
        }
        
        Azure_Logger::info('TEC Sync Engine: Starting sync from Outlook to TEC', 'TEC');
        
        try {
            // Default to next 30 days if no date range specified
            if (!$start_date) {
                $start_date = date('Y-m-d\TH:i:s\Z');
            }
            if (!$end_date) {
                $end_date = date('Y-m-d\TH:i:s\Z', strtotime('+30 days'));
            }
            
            // Get events from Outlook
            $outlook_events = $this->graph_api->get_calendar_events($this->calendar_id, $start_date, $end_date, null, true);
            
            if (!$outlook_events) {
                Azure_Logger::info('TEC Sync Engine: No Outlook events found to sync', 'TEC');
                return true;
            }
            
            $synced_count = 0;
            $error_count = 0;
            
            foreach ($outlook_events as $outlook_event) {
                try {
                    $result = $this->sync_single_outlook_event_to_tec($outlook_event);
                    
                    if ($result) {
                        $synced_count++;
                    } else {
                        $error_count++;
                    }
                } catch (Exception $e) {
                    Azure_Logger::error("TEC Sync Engine: Exception syncing Outlook event {$outlook_event['id']}: " . $e->getMessage(), 'TEC');
                    $error_count++;
                }
            }
            
            Azure_Logger::info("TEC Sync Engine: Outlook to TEC sync completed. Synced: {$synced_count}, Errors: {$error_count}", 'TEC');
            
            return true;
            
        } catch (Exception $e) {
            Azure_Logger::error('TEC Sync Engine: Exception during Outlook to TEC sync: ' . $e->getMessage(), 'TEC');
            return false;
        }
    }
    
    /**
     * Sync a single Outlook event to TEC
     */
    private function sync_single_outlook_event_to_tec($outlook_event) {
        if (!isset($outlook_event['id'])) {
            return false;
        }
        
        $outlook_event_id = $outlook_event['id'];
        
        // Check if TEC event already exists for this Outlook event
        $existing_tec_event_id = $this->find_tec_event_by_outlook_id($outlook_event_id);
        
        if ($existing_tec_event_id) {
            // Update existing TEC event
            return $this->update_tec_event_from_outlook($existing_tec_event_id, $outlook_event);
        } else {
            // Create new TEC event
            return $this->create_tec_event_from_outlook($outlook_event);
        }
    }
    
    /**
     * Find TEC event by Outlook event ID
     */
    private function find_tec_event_by_outlook_id($outlook_event_id) {
        global $wpdb;
        
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_outlook_event_id' 
             AND meta_value = %s
             LIMIT 1",
            $outlook_event_id
        ));
        
        return $post_id ? intval($post_id) : false;
    }
    
    /**
     * Create new TEC event from Outlook event
     */
    private function create_tec_event_from_outlook($outlook_event) {
        if (!$this->data_mapper) {
            return false;
        }
        
        Azure_Logger::debug("TEC Sync Engine: Creating TEC event from Outlook event {$outlook_event['id']}", 'TEC');
        
        try {
            // Map Outlook event to TEC format
            $tec_event_data = $this->data_mapper->map_outlook_to_tec($outlook_event);
            
            if (!$tec_event_data) {
                Azure_Logger::error("TEC Sync Engine: Failed to map Outlook event {$outlook_event['id']} to TEC format", 'TEC');
                return false;
            }
            
            // Create TEC event post
            $post_data = array(
                'post_title' => $tec_event_data['title'],
                'post_content' => $tec_event_data['description'],
                'post_status' => 'publish',
                'post_type' => 'tribe_events'
            );
            
            // Temporarily remove our sync hook to prevent infinite loop
            remove_action('save_post_tribe_events', array(Azure_TEC_Integration::get_instance(), 'sync_tec_event_to_outlook'), 20);
            
            $tec_event_id = wp_insert_post($post_data);
            
            // Re-add our sync hook
            add_action('save_post_tribe_events', array(Azure_TEC_Integration::get_instance(), 'sync_tec_event_to_outlook'), 20, 2);
            
            if (is_wp_error($tec_event_id)) {
                Azure_Logger::error("TEC Sync Engine: Failed to create TEC event: " . $tec_event_id->get_error_message(), 'TEC');
                return false;
            }
            
            // Add TEC event metadata
            if (isset($tec_event_data['start_date'])) {
                update_post_meta($tec_event_id, '_EventStartDate', $tec_event_data['start_date']);
            }
            if (isset($tec_event_data['end_date'])) {
                update_post_meta($tec_event_id, '_EventEndDate', $tec_event_data['end_date']);
            }
            if (isset($tec_event_data['all_day'])) {
                update_post_meta($tec_event_id, '_EventAllDay', $tec_event_data['all_day'] ? 'yes' : 'no');
            }
            if (isset($tec_event_data['venue'])) {
                // Set venue (simplified - in production you might want to create/find venue posts)
                update_post_meta($tec_event_id, '_EventVenueID', 0);
                update_post_meta($tec_event_id, '_EventVenue', $tec_event_data['venue']);
            }
            if (isset($tec_event_data['organizer'])) {
                // Set organizer (simplified - in production you might want to create/find organizer posts)
                update_post_meta($tec_event_id, '_EventOrganizerID', 0);
                update_post_meta($tec_event_id, '_EventOrganizer', $tec_event_data['organizer']);
            }
            
            // Store sync metadata
            update_post_meta($tec_event_id, '_outlook_event_id', $outlook_event['id']);
            update_post_meta($tec_event_id, '_outlook_sync_status', 'synced');
            update_post_meta($tec_event_id, '_outlook_last_sync', current_time('mysql'));
            update_post_meta($tec_event_id, '_sync_direction', 'from_outlook');
            
            Azure_Logger::info("TEC Sync Engine: Successfully created TEC event {$tec_event_id} from Outlook event {$outlook_event['id']}", 'TEC');
            
            return $tec_event_id;
            
        } catch (Exception $e) {
            Azure_Logger::error("TEC Sync Engine: Exception creating TEC event from Outlook: " . $e->getMessage(), 'TEC');
            return false;
        }
    }
    
    /**
     * Update existing TEC event from Outlook event
     */
    private function update_tec_event_from_outlook($tec_event_id, $outlook_event) {
        if (!$this->data_mapper) {
            return false;
        }
        
        Azure_Logger::debug("TEC Sync Engine: Updating TEC event {$tec_event_id} from Outlook event {$outlook_event['id']}", 'TEC');
        
        try {
            // Check for conflicts
            if ($this->has_sync_conflict($tec_event_id, $outlook_event)) {
                return $this->resolve_sync_conflict($tec_event_id, $outlook_event);
            }
            
            // Map Outlook event to TEC format
            $tec_event_data = $this->data_mapper->map_outlook_to_tec($outlook_event);
            
            if (!$tec_event_data) {
                Azure_Logger::error("TEC Sync Engine: Failed to map Outlook event {$outlook_event['id']} to TEC format", 'TEC');
                return false;
            }
            
            // Update TEC event post
            $post_data = array(
                'ID' => $tec_event_id,
                'post_title' => $tec_event_data['title'],
                'post_content' => $tec_event_data['description']
            );
            
            // Temporarily remove our sync hook to prevent infinite loop
            remove_action('save_post_tribe_events', array(Azure_TEC_Integration::get_instance(), 'sync_tec_event_to_outlook'), 20);
            
            $result = wp_update_post($post_data);
            
            // Re-add our sync hook
            add_action('save_post_tribe_events', array(Azure_TEC_Integration::get_instance(), 'sync_tec_event_to_outlook'), 20, 2);
            
            if (is_wp_error($result)) {
                Azure_Logger::error("TEC Sync Engine: Failed to update TEC event: " . $result->get_error_message(), 'TEC');
                return false;
            }
            
            // Update TEC event metadata
            if (isset($tec_event_data['start_date'])) {
                update_post_meta($tec_event_id, '_EventStartDate', $tec_event_data['start_date']);
            }
            if (isset($tec_event_data['end_date'])) {
                update_post_meta($tec_event_id, '_EventEndDate', $tec_event_data['end_date']);
            }
            if (isset($tec_event_data['all_day'])) {
                update_post_meta($tec_event_id, '_EventAllDay', $tec_event_data['all_day'] ? 'yes' : 'no');
            }
            
            // Update sync metadata
            update_post_meta($tec_event_id, '_outlook_sync_status', 'synced');
            update_post_meta($tec_event_id, '_outlook_last_sync', current_time('mysql'));
            
            Azure_Logger::info("TEC Sync Engine: Successfully updated TEC event {$tec_event_id} from Outlook", 'TEC');
            
            return true;
            
        } catch (Exception $e) {
            Azure_Logger::error("TEC Sync Engine: Exception updating TEC event from Outlook: " . $e->getMessage(), 'TEC');
            return false;
        }
    }
    
    /**
     * Check for sync conflicts
     */
    private function has_sync_conflict($tec_event_id, $outlook_event) {
        // Get last sync timestamp
        $last_sync = get_post_meta($tec_event_id, '_outlook_last_sync', true);
        
        if (!$last_sync) {
            return false; // No previous sync, no conflict
        }
        
        // Get TEC event last modified time
        $tec_event = get_post($tec_event_id);
        $tec_modified = strtotime($tec_event->post_modified);
        $last_sync_time = strtotime($last_sync);
        
        // If TEC event was modified after last sync, there might be a conflict
        if ($tec_modified > $last_sync_time) {
            Azure_Logger::warning("TEC Sync Engine: Potential sync conflict detected for event {$tec_event_id}", 'TEC');
            return true;
        }
        
        return false;
    }
    
    /**
     * Resolve sync conflict
     */
    private function resolve_sync_conflict($tec_event_id, $outlook_event) {
        $settings = Azure_Settings::get_all_settings();
        $resolution_strategy = $settings['tec_conflict_resolution'] ?? 'outlook_wins';
        
        Azure_Logger::info("TEC Sync Engine: Resolving conflict for event {$tec_event_id} using strategy: {$resolution_strategy}", 'TEC');
        
        switch ($resolution_strategy) {
            case 'outlook_wins':
                // Outlook wins - update TEC event
                return $this->update_tec_event_from_outlook($tec_event_id, $outlook_event);
                
            case 'tec_wins':
                // TEC wins - sync TEC event to Outlook
                return $this->sync_tec_to_outlook($tec_event_id);
                
            case 'manual':
                // Manual resolution - log conflict and skip
                update_post_meta($tec_event_id, '_sync_conflict_resolution', 'manual_required');
                update_post_meta($tec_event_id, '_outlook_sync_status', 'conflict');
                Azure_Logger::warning("TEC Sync Engine: Manual conflict resolution required for event {$tec_event_id}", 'TEC');
                return false;
                
            default:
                // Default to Outlook wins
                return $this->update_tec_event_from_outlook($tec_event_id, $outlook_event);
        }
    }
    
    /**
     * Delete Outlook event
     */
    public function delete_outlook_event($outlook_event_id) {
        if (!$this->graph_api) {
            Azure_Logger::error('TEC Sync Engine: Graph API not available for deletion', 'TEC');
            return false;
        }
        
        try {
            $result = $this->graph_api->delete_event($this->calendar_id, $outlook_event_id);
            
            if ($result) {
                Azure_Logger::info("TEC Sync Engine: Successfully deleted Outlook event {$outlook_event_id}", 'TEC');
                return true;
            } else {
                Azure_Logger::warning("TEC Sync Engine: Failed to delete Outlook event {$outlook_event_id}", 'TEC');
                return false;
            }
            
        } catch (Exception $e) {
            Azure_Logger::error("TEC Sync Engine: Exception deleting Outlook event {$outlook_event_id}: " . $e->getMessage(), 'TEC');
            return false;
        }
    }
    
    /**
     * Bulk sync all TEC events to Outlook
     */
    public function bulk_sync_tec_to_outlook($limit = 50) {
        Azure_Logger::info("TEC Sync Engine: Starting bulk sync of TEC events to Outlook (limit: {$limit})", 'TEC');
        
        // Get published TEC events
        $args = array(
            'post_type' => 'tribe_events',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_outlook_sync_status',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_outlook_sync_status',
                    'value' => array('pending', 'error'),
                    'compare' => 'IN'
                )
            )
        );
        
        $events = get_posts($args);
        
        $synced_count = 0;
        $error_count = 0;
        
        foreach ($events as $event) {
            try {
                $result = $this->sync_tec_to_outlook($event->ID);
                
                if ($result) {
                    $synced_count++;
                } else {
                    $error_count++;
                }
                
                // Small delay to avoid rate limiting
                usleep(100000); // 0.1 second
                
            } catch (Exception $e) {
                Azure_Logger::error("TEC Sync Engine: Exception in bulk sync for event {$event->ID}: " . $e->getMessage(), 'TEC');
                $error_count++;
            }
        }
        
        Azure_Logger::info("TEC Sync Engine: Bulk sync completed. Synced: {$synced_count}, Errors: {$error_count}", 'TEC');
        
        return $synced_count > 0;
    }
    
    /**
     * Update sync timestamp
     */
    private function update_sync_timestamp($tec_event_id) {
        update_post_meta($tec_event_id, '_outlook_last_sync', current_time('mysql'));
    }
    
    /**
     * Get sync statistics
     */
    public function get_sync_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total TEC events
        $stats['total_tec_events'] = wp_count_posts('tribe_events')->publish;
        
        // Synced events
        $stats['synced_events'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_outlook_sync_status' 
             AND pm.meta_value = 'synced'
             AND p.post_type = 'tribe_events'
             AND p.post_status = 'publish'"
        );
        
        // Pending events
        $stats['pending_events'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_outlook_sync_status' 
             AND pm.meta_value = 'pending'
             AND p.post_type = 'tribe_events'
             AND p.post_status = 'publish'"
        );
        
        // Error events
        $stats['error_events'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_outlook_sync_status' 
             AND pm.meta_value = 'error'
             AND p.post_type = 'tribe_events'
             AND p.post_status = 'publish'"
        );
        
        // Unsynced events
        $stats['unsynced_events'] = $stats['total_tec_events'] - $stats['synced_events'] - $stats['pending_events'] - $stats['error_events'];
        
        // Last sync time
        $stats['last_sync'] = $wpdb->get_var(
            "SELECT MAX(meta_value) FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_outlook_last_sync'
             AND p.post_type = 'tribe_events'"
        );
        
        return $stats;
    }
    
    /**
     * Retry failed sync attempts (Task 2.8)
     * Implement exponential backoff for failed syncs
     */
    public function retry_failed_syncs($max_retries = 3) {
        Azure_Logger::info('TEC Sync Engine: Starting retry of failed sync attempts', 'TEC');
        
        // Get events with error status or excessive pending time
        $failed_events = get_posts(array(
            'post_type' => 'tribe_events',
            'posts_per_page' => 50, // Limit to prevent overwhelming
            'post_status' => array('publish', 'private'),
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_outlook_sync_status',
                    'value' => 'error'
                ),
                array(
                    'key' => '_outlook_sync_status',
                    'value' => 'pending',
                    'meta_query' => array(
                        array(
                            'key' => '_outlook_last_sync_attempt',
                            'value' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                            'compare' => '<'
                        )
                    )
                )
            )
        ));
        
        $retry_count = 0;
        $success_count = 0;
        $skip_count = 0;
        
        foreach ($failed_events as $event) {
            $event_id = $event->ID;
            
            // Check retry count
            $current_retries = get_post_meta($event_id, '_outlook_sync_retries', true) ?: 0;
            
            if ($current_retries >= $max_retries) {
                // Mark as permanently failed
                update_post_meta($event_id, '_outlook_sync_status', 'failed_permanent');
                update_post_meta($event_id, '_outlook_sync_message', 'Max retries exceeded');
                $skip_count++;
                Azure_Logger::warning("TEC Sync Engine: Event {$event_id} exceeded max retries ({$max_retries})", 'TEC');
                continue;
            }
            
            // Implement exponential backoff
            $backoff_minutes = pow(2, $current_retries) * 5; // 5, 10, 20 minutes
            $last_attempt = get_post_meta($event_id, '_outlook_last_sync_attempt', true);
            
            if ($last_attempt && strtotime($last_attempt) > strtotime("-{$backoff_minutes} minutes")) {
                // Still in backoff period
                $skip_count++;
                continue;
            }
            
            // Record retry attempt
            update_post_meta($event_id, '_outlook_sync_retries', $current_retries + 1);
            update_post_meta($event_id, '_outlook_last_sync_attempt', current_time('mysql'));
            
            // Attempt sync
            try {
                $result = $this->sync_tec_to_outlook($event_id);
                
                if ($result) {
                    // Success - reset retry count
                    delete_post_meta($event_id, '_outlook_sync_retries');
                    delete_post_meta($event_id, '_outlook_last_sync_attempt');
                    $success_count++;
                    Azure_Logger::info("TEC Sync Engine: Successfully retried sync for event {$event_id}", 'TEC');
                } else {
                    // Failed again
                    update_post_meta($event_id, '_outlook_sync_status', 'error');
                    update_post_meta($event_id, '_outlook_sync_message', 'Retry failed');
                    $retry_count++;
                }
                
            } catch (Exception $e) {
                // Exception occurred
                update_post_meta($event_id, '_outlook_sync_status', 'error');
                update_post_meta($event_id, '_outlook_sync_message', 'Retry exception: ' . $e->getMessage());
                $retry_count++;
                Azure_Logger::error("TEC Sync Engine: Retry failed for event {$event_id}: " . $e->getMessage(), 'TEC');
            }
        }
        
        Azure_Logger::info("TEC Sync Engine: Retry complete - {$success_count} succeeded, {$retry_count} failed, {$skip_count} skipped", 'TEC');
        
        return array(
            'processed' => count($failed_events),
            'success' => $success_count,
            'failed' => $retry_count,
            'skipped' => $skip_count
        );
    }
    
    /**
     * Handle API rate limiting (Task 3.13)
     * Implement rate limiting and throttling for Graph API calls
     */
    public function handle_rate_limiting($response_headers = array()) {
        // Check for rate limit headers from Microsoft Graph API
        if (isset($response_headers['Retry-After'])) {
            $retry_after = intval($response_headers['Retry-After']);
            Azure_Logger::warning("TEC Sync Engine: Rate limited, waiting {$retry_after} seconds", 'TEC');
            
            // Store rate limit info for future requests
            update_option('azure_tec_rate_limit_until', time() + $retry_after);
            
            return $retry_after;
        }
        
        // Check for throttling headers
        if (isset($response_headers['X-RateLimit-Remaining'])) {
            $remaining = intval($response_headers['X-RateLimit-Remaining']);
            
            if ($remaining < 10) {
                // Very low on requests, implement delay
                $delay = min(60, (10 - $remaining) * 2); // Up to 60 seconds
                Azure_Logger::info("TEC Sync Engine: Low rate limit remaining ({$remaining}), adding {$delay}s delay", 'TEC');
                
                update_option('azure_tec_throttle_delay', $delay);
                return $delay;
            }
        }
        
        return 0; // No delay needed
    }
    
    /**
     * Check if we should delay requests due to rate limiting
     */
    public function should_delay_request() {
        // Check if we're currently rate limited
        $rate_limit_until = get_option('azure_tec_rate_limit_until', 0);
        if ($rate_limit_until && time() < $rate_limit_until) {
            return $rate_limit_until - time();
        }
        
        // Check if we should throttle
        $throttle_delay = get_option('azure_tec_throttle_delay', 0);
        if ($throttle_delay) {
            // Gradually reduce throttle delay
            $new_delay = max(0, $throttle_delay - 5);
            update_option('azure_tec_throttle_delay', $new_delay);
            
            return $throttle_delay;
        }
        
        return 0;
    }
}