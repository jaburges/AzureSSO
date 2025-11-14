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
     */
    public function calendar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
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
            'time_format' => '24h'
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
        
        $events = $this->graph_api->get_calendar_events(
            $atts['id'],
            $start_date,
            $end_date,
            intval($atts['max_events'])
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
     */
    public function events_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
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
        
        $events = $this->graph_api->get_calendar_events(
            $atts['id'],
            $start_date,
            $end_date,
            intval($atts['limit']) * 2 // Get more to account for filtering
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
        $timezone = !empty($atts['timezone']) ? $atts['timezone'] : wp_timezone_string();
        
        $script = "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('{$container_id}');
            
            if (typeof FullCalendar !== 'undefined') {
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: '{$atts['view']}View',
                    timeZone: '{$timezone}',
                    events: {$events_json},
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                    },
                    weekends: " . ($atts['show_weekends'] ? 'true' : 'false') . ",
                    firstDay: {$atts['first_day']},
                    eventClick: function(info) {
                        alert('Event: ' + info.event.title + '\\nTime: ' + info.event.start.toLocaleString());
                    },
                    height: '{$atts['height']}',
                    themeSystem: 'bootstrap'
                });
                
                calendar.render();
            } else {
                calendarEl.innerHTML = '<p class=\"azure-calendar-error\">FullCalendar library not loaded.</p>';
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
        // Only enqueue if we're on a page that might have calendar shortcodes
        global $post;
        
        if (!$post || !has_shortcode($post->post_content, 'azure_calendar')) {
            return;
        }
        
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
