<?php
/**
 * Calendar shortcode handler for Azure Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Calendar_Shortcode {
    
    private $graph_api;
    
    public function __construct() {
        if (class_exists('Azure_Calendar_GraphAPI')) {
            $this->graph_api = new Azure_Calendar_GraphAPI();
        }
        
        // Register shortcodes
        add_shortcode('azure_calendar', array($this, 'calendar_shortcode'));
        add_shortcode('azure_calendar_events', array($this, 'events_list_shortcode'));
        add_shortcode('azure_calendar_event', array($this, 'single_event_shortcode'));
        
        // Enqueue scripts and styles for frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }
    
    /**
     * Main calendar shortcode
     * Usage: [azure_calendar id="calendar_id" view="month" height="600px"]
     * 
     * For shared mailboxes, use:
     * [azure_calendar id="calendar_id" user_email="user@domain.com" mailbox_email="shared@domain.com"]
     * 
     * Legacy support: 'email' parameter is treated as mailbox_email, and user_email is fetched from settings
     */
    public function calendar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
            'email' => '', // Legacy: treated as mailbox_email for shared mailbox access
            'user_email' => '', // Authenticated user who has token
            'mailbox_email' => '', // Shared mailbox to access (optional)
            'view' => 'month', // month, week, day, list
            'height' => '600px',
            'width' => '100%',
            'theme' => 'default',
            'timezone' => '',
            'max_events' => 100,
            'start_date' => '',
            'end_date' => '',
            'show_toolbar' => true,
            'show_weekends' => true,
            'first_day' => 0, // 0 = Sunday, 1 = Monday
            'time_format' => '24h',
            'slot_min_time' => '08:00:00', // Start time for day/week views (e.g., '08:00:00')
            'slot_max_time' => '18:00:00', // End time for day/week views (e.g., '18:00:00')
            'slot_duration' => '00:30:00' // Time slot duration (e.g., '00:30:00' for 30 min slots)
        ), $atts);
        
        if (empty($atts['id'])) {
            return '<p class="azure-calendar-error">Calendar ID is required.</p>';
        }
        
        if (!$this->graph_api) {
            return '<p class="azure-calendar-error">Calendar service is not available.</p>';
        }
        
        // Generate unique container ID
        $container_id = 'azure-calendar-' . uniqid();
        
        // Get calendar events
        $start_date = $atts['start_date'] ?: date('Y-m-d\TH:i:s\Z');
        $end_date = $atts['end_date'] ?: date('Y-m-d\TH:i:s\Z', strtotime('+30 days'));
        
        // Handle email parameters for shared mailbox access
        // If 'email' is provided (legacy), treat it as the mailbox_email
        // and get user_email from calendar embed settings
        $mailbox_email = !empty($atts['mailbox_email']) ? sanitize_email($atts['mailbox_email']) : null;
        $user_email = !empty($atts['user_email']) ? sanitize_email($atts['user_email']) : null;
        
        // Legacy support: if 'email' is provided but not 'mailbox_email' or 'user_email'
        if (!empty($atts['email']) && empty($mailbox_email)) {
            $mailbox_email = sanitize_email($atts['email']);
        }
        
        // If we have a mailbox but no user_email, get the authenticated user from settings
        if ($mailbox_email && !$user_email) {
            $settings = Azure_Settings::get_all_settings();
            // Try calendar embed user first, then TEC calendar user
            $user_email = $settings['calendar_embed_user_email'] ?? $settings['tec_calendar_user_email'] ?? '';
            
            if (empty($user_email)) {
                Azure_Logger::warning("Calendar Shortcode: Mailbox email provided but no authenticated user configured. Please set up Calendar Embed authentication.", 'Calendar');
                return '<p class="azure-calendar-error">Calendar authentication not configured. Please contact the site administrator.</p>';
            }
        }
        
        // If no emails specified at all, try to get both from settings
        if (!$user_email && !$mailbox_email) {
            $settings = Azure_Settings::get_all_settings();
            $user_email = $settings['calendar_embed_user_email'] ?? '';
            $mailbox_email = $settings['calendar_embed_mailbox_email'] ?? '';
            
            // If still no user_email, check if they're using the same email for both
            if (empty($user_email)) {
                Azure_Logger::warning("Calendar Shortcode: No user_email configured. Please set up Calendar Embed authentication.", 'Calendar');
            }
        }
        
        // If no mailbox specified, user_email is both the token holder and calendar owner
        if (!$mailbox_email && $user_email) {
            // User is accessing their own calendar
            $mailbox_email = null;
        }
        
        Azure_Logger::debug("Calendar Shortcode: Fetching events for calendar {$atts['id']}, user_email: " . ($user_email ?: 'default') . ", mailbox_email: " . ($mailbox_email ?: 'none'), 'Calendar');
        
        $events = $this->graph_api->get_calendar_events(
            $atts['id'],
            $start_date,
            $end_date,
            intval($atts['max_events']),
            false, // force_refresh
            $user_email,
            $mailbox_email
        );
        
        if ($events === false) {
            return '<p class="azure-calendar-error">Failed to load calendar events.</p>';
        }
        
        // Convert events to FullCalendar format
        $calendar_events = $this->format_events_for_calendar($events);
        
        // Build calendar HTML and JavaScript
        $output = '<div id="' . esc_attr($container_id) . '" class="azure-calendar-container" style="height: ' . esc_attr($atts['height']) . '; width: ' . esc_attr($atts['width']) . ';"></div>';
        
        $output .= $this->get_calendar_script($container_id, $calendar_events, $atts);
        
        return $output;
    }
    
    /**
     * Events list shortcode
     * Usage: [azure_calendar_events id="calendar_id" limit="10" format="list"]
     * 
     * For shared mailboxes:
     * [azure_calendar_events id="calendar_id" user_email="user@domain.com" mailbox_email="shared@domain.com"]
     */
    public function events_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
            'email' => '', // Legacy: treated as mailbox_email
            'user_email' => '', // Authenticated user who has token
            'mailbox_email' => '', // Shared mailbox to access
            'limit' => 10,
            'format' => 'list', // list, grid, compact
            'show_dates' => true,
            'show_times' => true,
            'show_location' => true,
            'show_description' => false,
            'date_format' => 'M j, Y',
            'time_format' => 'g:i A',
            'upcoming_only' => true,
            'class' => 'azure-events-list'
        ), $atts);
        
        if (empty($atts['id'])) {
            return '<p class="azure-calendar-error">Calendar ID is required.</p>';
        }
        
        if (!$this->graph_api) {
            return '<p class="azure-calendar-error">Calendar service is not available.</p>';
        }
        
        // Get events
        $start_date = $atts['upcoming_only'] ? date('Y-m-d\TH:i:s\Z') : date('Y-m-d\TH:i:s\Z', strtotime('-30 days'));
        $end_date = date('Y-m-d\TH:i:s\Z', strtotime('+90 days'));
        
        // Handle email parameters for shared mailbox access
        $mailbox_email = !empty($atts['mailbox_email']) ? sanitize_email($atts['mailbox_email']) : null;
        $user_email = !empty($atts['user_email']) ? sanitize_email($atts['user_email']) : null;
        
        // Legacy support: if 'email' is provided but not 'mailbox_email'
        if (!empty($atts['email']) && empty($mailbox_email)) {
            $mailbox_email = sanitize_email($atts['email']);
        }
        
        // If we have a mailbox but no user_email, get from settings
        if ($mailbox_email && !$user_email) {
            $settings = Azure_Settings::get_all_settings();
            $user_email = $settings['calendar_embed_user_email'] ?? $settings['tec_calendar_user_email'] ?? '';
            
            if (empty($user_email)) {
                return '<p class="azure-calendar-error">Calendar authentication not configured.</p>';
            }
        }
        
        $events = $this->graph_api->get_calendar_events(
            $atts['id'],
            $start_date,
            $end_date,
            intval($atts['limit']) * 2, // Get more to account for filtering
            false, // force_refresh
            $user_email,
            $mailbox_email
        );
        
        if ($events === false) {
            return '<p class="azure-calendar-error">Failed to load events.</p>';
        }
        
        // Filter and limit events
        if ($atts['upcoming_only']) {
            $now = time();
            $events = array_filter($events, function($event) use ($now) {
                return strtotime($event['start']) >= $now;
            });
        }
        
        $events = array_slice($events, 0, intval($atts['limit']));
        
        if (empty($events)) {
            return '<p class="azure-calendar-no-events">No upcoming events found.</p>';
        }
        
        return $this->render_events_list($events, $atts);
    }
    
    /**
     * Single event shortcode
     * Usage: [azure_calendar_event id="calendar_id" event_id="event_id"]
     */
    public function single_event_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
            'event_id' => '',
            'show_attendees' => false,
            'show_description' => true,
            'class' => 'azure-single-event'
        ), $atts);
        
        if (empty($atts['id']) || empty($atts['event_id'])) {
            return '<p class="azure-calendar-error">Calendar ID and Event ID are required.</p>';
        }
        
        // This would require implementing a get_single_event method
        return '<p class="azure-calendar-error">Single event display not yet implemented.</p>';
    }
    
    /**
     * Format events for FullCalendar
     */
    private function format_events_for_calendar($events) {
        $calendar_events = array();
        
        foreach ($events as $event) {
            $calendar_events[] = array(
                'id' => $event['id'],
                'title' => $event['title'],
                'start' => $event['start'],
                'end' => $event['end'],
                'allDay' => $event['allDay'],
                'location' => $event['location'],
                'description' => $event['description'],
                'backgroundColor' => $this->get_event_color($event),
                'borderColor' => $this->get_event_color($event),
                'textColor' => '#ffffff'
            );
        }
        
        return $calendar_events;
    }
    
    /**
     * Get event color based on categories or default
     */
    private function get_event_color($event) {
        $default_colors = array(
            '#0073aa', '#46b450', '#dc3232', '#ffb900', '#826eb4',
            '#f56e28', '#00a0d2', '#007cba', '#d54e21', '#78c8db'
        );
        
        if (!empty($event['categories'])) {
            // Use category to determine color
            $category = $event['categories'][0];
            $color_index = crc32($category) % count($default_colors);
            return $default_colors[$color_index];
        }
        
        // Default color
        return $default_colors[0];
    }
    
    /**
     * Generate calendar JavaScript
     */
    private function get_calendar_script($container_id, $events, $atts) {
        $events_json = json_encode($events);
        
        // Determine timezone: shortcode attr > per-calendar setting > plugin default > WordPress default
        $timezone = '';
        if (!empty($atts['timezone'])) {
            $timezone = $atts['timezone'];
        } else {
            $settings = Azure_Settings::get_all_settings();
            // Check for per-calendar timezone setting
            if (!empty($atts['id']) && !empty($settings['calendar_timezone_' . $atts['id']])) {
                $timezone = $settings['calendar_timezone_' . $atts['id']];
            } elseif (!empty($settings['calendar_default_timezone'])) {
                // Fall back to plugin default timezone
                $timezone = $settings['calendar_default_timezone'];
            } else {
                // Fall back to WordPress timezone
                $timezone = wp_timezone_string();
            }
        }
        
        // Map view names to FullCalendar v6 view names
        $view_map = array(
            'month' => 'dayGridMonth',
            'week' => 'timeGridWeek',
            'day' => 'timeGridDay',
            'list' => 'listWeek'
        );
        $initial_view = isset($view_map[$atts['view']]) ? $view_map[$atts['view']] : 'dayGridMonth';
        
        // Time range settings for day/week views
        $slot_min_time = esc_js($atts['slot_min_time']);
        $slot_max_time = esc_js($atts['slot_max_time']);
        $slot_duration = esc_js($atts['slot_duration']);
        
        $script = "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('{$container_id}');
            
            if (typeof FullCalendar !== 'undefined') {
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: '{$initial_view}',
                    timeZone: '{$timezone}',
                    events: {$events_json},
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                    },
                    weekends: " . ($atts['show_weekends'] ? 'true' : 'false') . ",
                    firstDay: {$atts['first_day']},
                    slotMinTime: '{$slot_min_time}',
                    slotMaxTime: '{$slot_max_time}',
                    slotDuration: '{$slot_duration}',
                    scrollTime: '{$slot_min_time}',
                    eventClick: function(info) {
                        alert('Event: ' + info.event.title + '\\nTime: ' + info.event.start.toLocaleString());
                    },
                    height: '{$atts['height']}',
                    themeSystem: 'standard'
                });
                
                calendar.render();
            } else {
                calendarEl.innerHTML = '<p class=\"azure-calendar-error\">FullCalendar library not loaded. Please make sure JavaScript is enabled.</p>';
            }
        });
        </script>";
        
        return $script;
    }
    
    /**
     * Render events list HTML
     */
    private function render_events_list($events, $atts) {
        $output = '<div class="' . esc_attr($atts['class']) . ' format-' . esc_attr($atts['format']) . '">';
        
        foreach ($events as $event) {
            $start_time = strtotime($event['start']);
            $end_time = strtotime($event['end']);
            
            $output .= '<div class="azure-event-item">';
            
            // Event title
            $output .= '<h3 class="event-title">' . esc_html($event['title']) . '</h3>';
            
            // Event date/time
            if ($atts['show_dates'] || $atts['show_times']) {
                $output .= '<div class="event-datetime">';
                
                if ($atts['show_dates']) {
                    $output .= '<span class="event-date">' . date($atts['date_format'], $start_time) . '</span>';
                }
                
                if ($atts['show_times'] && !$event['allDay']) {
                    $output .= ' <span class="event-time">' . date($atts['time_format'], $start_time);
                    if ($start_time !== $end_time) {
                        $output .= ' - ' . date($atts['time_format'], $end_time);
                    }
                    $output .= '</span>';
                }
                
                $output .= '</div>';
            }
            
            // Event location
            if ($atts['show_location'] && !empty($event['location'])) {
                $output .= '<div class="event-location"><strong>Location:</strong> ' . esc_html($event['location']) . '</div>';
            }
            
            // Event description
            if ($atts['show_description'] && !empty($event['description'])) {
                $description = wp_trim_words($event['description'], 30);
                $output .= '<div class="event-description">' . esc_html($description) . '</div>';
            }
            
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Always enqueue on frontend - it's better to load when not needed than to miss it when needed
        // The library is loaded from CDN so it won't impact server resources
        if (!is_admin()) {
            // Enqueue FullCalendar
            wp_enqueue_script(
                'fullcalendar',
                'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js',
                array(),
                '6.1.8',
                true
            );
            
            // Enqueue calendar styles
            wp_enqueue_style(
                'azure-calendar-frontend',
                AZURE_PLUGIN_URL . 'css/calendar-frontend.css',
                array(),
                AZURE_PLUGIN_VERSION
            );
        }
    }
}
