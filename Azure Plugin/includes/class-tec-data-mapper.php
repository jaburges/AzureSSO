<?php
/**
 * The Events Calendar Data Mapper
 * Handles data transformation between TEC and Outlook Calendar formats
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_TEC_Data_Mapper {
    
    private $settings;
    private $default_venue;
    private $default_organizer;
    
    public function __construct() {
        $this->settings = Azure_Settings::get_settings();
        $this->default_venue = $this->settings['tec_default_venue'] ?? 'School Campus';
        $this->default_organizer = $this->settings['tec_default_organizer'] ?? 'PTSA';
        
        Azure_Logger::debug('TEC Data Mapper: Initialized with default venue: ' . $this->default_venue . ', organizer: ' . $this->default_organizer, 'TEC');
    }
    
    /**
     * Map TEC event to Outlook event format
     */
    public function map_tec_to_outlook($tec_event_id) {
        $tec_event = get_post($tec_event_id);
        
        if (!$tec_event || $tec_event->post_type !== 'tribe_events') {
            Azure_Logger::error("TEC Data Mapper: Invalid TEC event ID: {$tec_event_id}", 'TEC');
            return false;
        }
        
        Azure_Logger::debug("TEC Data Mapper: Mapping TEC event {$tec_event_id} to Outlook format", 'TEC');
        
        try {
            // Get TEC event metadata
            $start_date = get_post_meta($tec_event_id, '_EventStartDate', true);
            $end_date = get_post_meta($tec_event_id, '_EventEndDate', true);
            $all_day = get_post_meta($tec_event_id, '_EventAllDay', true) === 'yes';
            $venue = get_post_meta($tec_event_id, '_EventVenue', true);
            
            // Handle timezone conversion
            $timezone = $this->get_wordpress_timezone();
            
            // Format dates for Outlook
            $outlook_start = $this->format_date_for_outlook($start_date, $all_day, $timezone);
            $outlook_end = $this->format_date_for_outlook($end_date, $all_day, $timezone);
            
            if (!$outlook_start || !$outlook_end) {
                Azure_Logger::error("TEC Data Mapper: Invalid dates for event {$tec_event_id}", 'TEC');
                return false;
            }
            
            // Build Outlook event data
            $outlook_event = array(
                'subject' => $tec_event->post_title,
                'body' => array(
                    'contentType' => 'text',
                    'content' => $this->prepare_event_description($tec_event)
                ),
                'start' => $outlook_start,
                'end' => $outlook_end,
                'isAllDay' => $all_day,
                'location' => array(
                    'displayName' => $venue ?: $this->default_venue
                ),
                'showAs' => 'busy',
                'sensitivity' => 'normal'
            );
            
            // Add organizer information
            $outlook_event['organizer'] = array(
                'emailAddress' => array(
                    'name' => $this->default_organizer,
                    'address' => $this->settings['tec_organizer_email'] ?? get_option('admin_email')
                )
            );
            
            // Add categories if configured
            $categories = $this->get_tec_event_categories($tec_event_id);
            if (!empty($categories)) {
                $outlook_event['categories'] = $categories;
            }
            
            // Handle recurring events
            $recurrence = $this->get_tec_recurrence($tec_event_id);
            if ($recurrence) {
                $outlook_event['recurrence'] = $recurrence;
            }
            
            Azure_Logger::debug("TEC Data Mapper: Successfully mapped TEC event {$tec_event_id} to Outlook format", 'TEC');
            
            return $outlook_event;
            
        } catch (Exception $e) {
            Azure_Logger::error("TEC Data Mapper: Exception mapping TEC event {$tec_event_id}: " . $e->getMessage(), 'TEC');
            return false;
        }
    }
    
    /**
     * Map Outlook event to TEC event format
     */
    public function map_outlook_to_tec($outlook_event) {
        if (!isset($outlook_event['id']) || !isset($outlook_event['title'])) {
            Azure_Logger::error('TEC Data Mapper: Invalid Outlook event data', 'TEC');
            return false;
        }
        
        Azure_Logger::debug("TEC Data Mapper: Mapping Outlook event {$outlook_event['id']} to TEC format", 'TEC');
        
        try {
            // Handle timezone conversion
            $timezone = $this->get_wordpress_timezone();
            
            // Format dates for TEC
            $tec_start = $this->format_date_for_tec($outlook_event['start'], $timezone);
            $tec_end = $this->format_date_for_tec($outlook_event['end'], $timezone);
            
            if (!$tec_start || !$tec_end) {
                Azure_Logger::error("TEC Data Mapper: Invalid dates for Outlook event {$outlook_event['id']}", 'TEC');
                return false;
            }
            
            // Build TEC event data
            $tec_event = array(
                'title' => $outlook_event['title'],
                'description' => $outlook_event['description'] ?? '',
                'start_date' => $tec_start,
                'end_date' => $tec_end,
                'all_day' => $outlook_event['allDay'] ?? false,
                'venue' => $this->extract_venue_from_outlook($outlook_event),
                'organizer' => $this->extract_organizer_from_outlook($outlook_event)
            );
            
            // Handle categories
            if (!empty($outlook_event['categories'])) {
                $tec_event['categories'] = $outlook_event['categories'];
            }
            
            Azure_Logger::debug("TEC Data Mapper: Successfully mapped Outlook event {$outlook_event['id']} to TEC format", 'TEC');
            
            return $tec_event;
            
        } catch (Exception $e) {
            Azure_Logger::error("TEC Data Mapper: Exception mapping Outlook event {$outlook_event['id']}: " . $e->getMessage(), 'TEC');
            return false;
        }
    }
    
    /**
     * Format date for Outlook
     */
    private function format_date_for_outlook($date_string, $all_day = false, $timezone = 'UTC') {
        if (empty($date_string)) {
            return null;
        }
        
        try {
            // Parse the date
            $date = new DateTime($date_string, new DateTimeZone($timezone));
            
            if ($all_day) {
                // For all-day events, use date only format
                return array(
                    'dateTime' => $date->format('Y-m-d'),
                    'timeZone' => $timezone
                );
            } else {
                // For timed events, include time
                return array(
                    'dateTime' => $date->format('Y-m-d\TH:i:s.000'),
                    'timeZone' => $timezone
                );
            }
            
        } catch (Exception $e) {
            Azure_Logger::error("TEC Data Mapper: Exception formatting date for Outlook: " . $e->getMessage(), 'TEC');
            return null;
        }
    }
    
    /**
     * Format date for TEC
     */
    private function format_date_for_tec($outlook_date, $timezone = 'UTC') {
        if (empty($outlook_date) || !is_array($outlook_date)) {
            return null;
        }
        
        try {
            $date_string = $outlook_date['dateTime'] ?? $outlook_date;
            $source_timezone = $outlook_date['timeZone'] ?? 'UTC';
            
            // Create DateTime object with source timezone
            $date = new DateTime($date_string, new DateTimeZone($source_timezone));
            
            // Convert to WordPress timezone
            $date->setTimezone(new DateTimeZone($timezone));
            
            // Return in TEC format
            return $date->format('Y-m-d H:i:s');
            
        } catch (Exception $e) {
            Azure_Logger::error("TEC Data Mapper: Exception formatting date for TEC: " . $e->getMessage(), 'TEC');
            return null;
        }
    }
    
    /**
     * Get WordPress timezone
     */
    private function get_wordpress_timezone() {
        $timezone_string = get_option('timezone_string');
        
        if (!empty($timezone_string)) {
            return $timezone_string;
        }
        
        // Fallback to GMT offset
        $offset = get_option('gmt_offset');
        
        if ($offset == 0) {
            return 'UTC';
        }
        
        // Convert offset to timezone format
        $hours = intval($offset);
        $minutes = abs(($offset - $hours) * 60);
        
        return sprintf('%+03d:%02d', $hours, $minutes);
    }
    
    /**
     * Prepare event description
     */
    private function prepare_event_description($tec_event) {
        $description = $tec_event->post_content;
        
        // Strip HTML tags and decode entities
        $description = wp_strip_all_tags($description);
        $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
        
        // Add event URL if configured
        if ($this->settings['tec_include_event_url'] ?? true) {
            $event_url = get_permalink($tec_event->ID);
            $description .= "\n\nEvent Details: " . $event_url;
        }
        
        // Add custom footer if configured
        $footer = $this->settings['tec_event_footer'] ?? '';
        if (!empty($footer)) {
            $description .= "\n\n" . $footer;
        }
        
        return trim($description);
    }
    
    /**
     * Get TEC event categories
     */
    private function get_tec_event_categories($tec_event_id) {
        $categories = array();
        
        // Get TEC event categories
        $event_categories = wp_get_post_terms($tec_event_id, 'tribe_events_cat');
        
        if (!is_wp_error($event_categories) && !empty($event_categories)) {
            foreach ($event_categories as $category) {
                $categories[] = $category->name;
            }
        }
        
        // Add default category if configured
        $default_category = $this->settings['tec_default_category'] ?? '';
        if (!empty($default_category) && !in_array($default_category, $categories)) {
            $categories[] = $default_category;
        }
        
        return $categories;
    }
    
    /**
     * Get TEC recurrence pattern
     */
    private function get_tec_recurrence($tec_event_id) {
        // Check if TEC Pro is active (required for recurring events)
        if (!class_exists('Tribe__Events__Pro__Main')) {
            return null;
        }
        
        // Get recurrence metadata
        $is_recurring = get_post_meta($tec_event_id, '_EventRecurrence', true);
        
        if (empty($is_recurring)) {
            return null;
        }
        
        try {
            // Parse TEC recurrence pattern and convert to Outlook format
            $recurrence_data = json_decode($is_recurring, true);
            
            if (!$recurrence_data || !isset($recurrence_data['rules'])) {
                return null;
            }
            
            $rule = $recurrence_data['rules'][0] ?? null;
            
            if (!$rule) {
                return null;
            }
            
            // Convert TEC recurrence to Outlook recurrence
            return $this->convert_tec_recurrence_to_outlook($rule);
            
        } catch (Exception $e) {
            Azure_Logger::error("TEC Data Mapper: Exception processing recurrence: " . $e->getMessage(), 'TEC');
            return null;
        }
    }
    
    /**
     * Convert TEC recurrence to Outlook recurrence pattern
     */
    private function convert_tec_recurrence_to_outlook($tec_rule) {
        $outlook_pattern = array(
            'pattern' => array(),
            'range' => array()
        );
        
        // Map frequency
        $frequency_map = array(
            'Daily' => 'daily',
            'Weekly' => 'weekly',
            'Monthly' => 'absoluteMonthly',
            'Yearly' => 'absoluteYearly'
        );
        
        $tec_frequency = $tec_rule['type'] ?? 'Daily';
        $outlook_pattern['pattern']['type'] = $frequency_map[$tec_frequency] ?? 'daily';
        
        // Set interval
        $outlook_pattern['pattern']['interval'] = intval($tec_rule['custom']['interval'] ?? 1);
        
        // Handle end date
        if (isset($tec_rule['end-type']) && $tec_rule['end-type'] === 'On') {
            $end_date = $tec_rule['end'] ?? null;
            if ($end_date) {
                $outlook_pattern['range']['type'] = 'endDate';
                $outlook_pattern['range']['endDate'] = date('Y-m-d', strtotime($end_date));
            }
        } elseif (isset($tec_rule['end-type']) && $tec_rule['end-type'] === 'After') {
            $occurrences = intval($tec_rule['end-count'] ?? 1);
            $outlook_pattern['range']['type'] = 'numbered';
            $outlook_pattern['range']['numberOfOccurrences'] = $occurrences;
        } else {
            // No end date
            $outlook_pattern['range']['type'] = 'noEnd';
        }
        
        return $outlook_pattern;
    }
    
    /**
     * Extract venue from Outlook event
     */
    private function extract_venue_from_outlook($outlook_event) {
        if (!empty($outlook_event['location'])) {
            return $outlook_event['location'];
        }
        
        return $this->default_venue;
    }
    
    /**
     * Extract organizer from Outlook event
     */
    private function extract_organizer_from_outlook($outlook_event) {
        if (!empty($outlook_event['organizer']['emailAddress']['name'])) {
            return $outlook_event['organizer']['emailAddress']['name'];
        }
        
        return $this->default_organizer;
    }
    
    /**
     * Validate TEC event data
     */
    public function validate_tec_event_data($tec_event_id) {
        $errors = array();
        
        $tec_event = get_post($tec_event_id);
        
        if (!$tec_event) {
            $errors[] = 'Event not found';
            return $errors;
        }
        
        if ($tec_event->post_type !== 'tribe_events') {
            $errors[] = 'Not a TEC event';
            return $errors;
        }
        
        // Check required fields
        if (empty($tec_event->post_title)) {
            $errors[] = 'Event title is required';
        }
        
        $start_date = get_post_meta($tec_event_id, '_EventStartDate', true);
        if (empty($start_date)) {
            $errors[] = 'Event start date is required';
        }
        
        $end_date = get_post_meta($tec_event_id, '_EventEndDate', true);
        if (empty($end_date)) {
            $errors[] = 'Event end date is required';
        }
        
        // Validate dates
        if ($start_date && $end_date) {
            $start_timestamp = strtotime($start_date);
            $end_timestamp = strtotime($end_date);
            
            if ($start_timestamp === false) {
                $errors[] = 'Invalid start date format';
            }
            
            if ($end_timestamp === false) {
                $errors[] = 'Invalid end date format';
            }
            
            if ($start_timestamp && $end_timestamp && $start_timestamp >= $end_timestamp) {
                $errors[] = 'Start date must be before end date';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate Outlook event data
     */
    public function validate_outlook_event_data($outlook_event) {
        $errors = array();
        
        // Check required fields
        if (empty($outlook_event['id'])) {
            $errors[] = 'Outlook event ID is required';
        }
        
        if (empty($outlook_event['title']) && empty($outlook_event['subject'])) {
            $errors[] = 'Event title/subject is required';
        }
        
        if (empty($outlook_event['start'])) {
            $errors[] = 'Event start date is required';
        }
        
        if (empty($outlook_event['end'])) {
            $errors[] = 'Event end date is required';
        }
        
        return $errors;
    }
    
    /**
     * Get field mapping configuration
     */
    public function get_field_mapping() {
        return array(
            'tec_to_outlook' => array(
                'post_title' => 'subject',
                'post_content' => 'body.content',
                '_EventStartDate' => 'start.dateTime',
                '_EventEndDate' => 'end.dateTime',
                '_EventAllDay' => 'isAllDay',
                '_EventVenue' => 'location.displayName'
            ),
            'outlook_to_tec' => array(
                'subject' => 'post_title',
                'body.content' => 'post_content',
                'start.dateTime' => '_EventStartDate',
                'end.dateTime' => '_EventEndDate',
                'isAllDay' => '_EventAllDay',
                'location.displayName' => '_EventVenue'
            )
        );
    }
    
    /**
     * Get data mapping statistics
     */
    public function get_mapping_statistics() {
        $stats = array(
            'successful_tec_mappings' => 0,
            'failed_tec_mappings' => 0,
            'successful_outlook_mappings' => 0,
            'failed_outlook_mappings' => 0,
            'last_mapping_time' => null
        );
        
        // These would be tracked in actual implementation
        // For now, return basic structure
        
        return $stats;
    }
}