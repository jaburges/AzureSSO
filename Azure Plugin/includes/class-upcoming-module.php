<?php
/**
 * Upcoming Events Module
 * 
 * Provides shortcodes for displaying upcoming TEC events in a clean, customizable format.
 * 
 * @package AzurePlugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Upcoming_Module {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Register shortcode
        add_shortcode('up-next', array($this, 'render_upcoming_shortcode'));
        
        // Enqueue frontend styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
    }
    
    /**
     * Enqueue frontend styles
     */
    public function enqueue_frontend_styles() {
        wp_enqueue_style(
            'azure-upcoming-frontend',
            AZURE_PLUGIN_URL . 'css/upcoming-frontend.css',
            array(),
            AZURE_PLUGIN_VERSION
        );
    }
    
    /**
     * Render the [up-next] shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_upcoming_shortcode($atts) {
        // Check if TEC is active
        if (!class_exists('Tribe__Events__Main')) {
            return '<p class="upcoming-error">' . __('The Events Calendar plugin is required for this shortcode.', 'azure-plugin') . '</p>';
        }
        
        // Parse attributes with defaults
        $atts = shortcode_atts(array(
            'current-week'        => 'true',
            'next-week'           => 'true',
            'columns'             => '1',
            'exclude-categories'  => '',
            'week-start'          => 'monday',
            'show-time'           => 'true',
            'link-titles'         => 'true',
            'show-empty'          => 'true',
            'empty-message'       => __('No upcoming events.', 'azure-plugin'),
            'this-week-title'     => __('This Week', 'azure-plugin'),
            'next-week-title'     => __('Next Week', 'azure-plugin'),
        ), $atts, 'up-next');
        
        // Normalize boolean attributes
        $show_current_week = filter_var($atts['current-week'], FILTER_VALIDATE_BOOLEAN);
        $show_next_week = filter_var($atts['next-week'], FILTER_VALIDATE_BOOLEAN);
        $show_time = filter_var($atts['show-time'], FILTER_VALIDATE_BOOLEAN);
        $link_titles = filter_var($atts['link-titles'], FILTER_VALIDATE_BOOLEAN);
        $show_empty = filter_var($atts['show-empty'], FILTER_VALIDATE_BOOLEAN);
        $columns = intval($atts['columns']);
        if ($columns < 1) $columns = 1;
        if ($columns > 3) $columns = 3;
        
        // Parse excluded categories
        $exclude_categories = array_filter(array_map('trim', explode(',', $atts['exclude-categories'])));
        
        // Get week boundaries
        $week_start_day = strtolower($atts['week-start']) === 'sunday' ? 0 : 1; // 0 = Sunday, 1 = Monday
        $today = new DateTime('today', wp_timezone());
        
        // Calculate start of current week
        $current_day_of_week = (int) $today->format('w'); // 0 = Sunday
        if ($week_start_day === 1) { // Monday start
            $days_since_start = $current_day_of_week === 0 ? 6 : $current_day_of_week - 1;
        } else { // Sunday start
            $days_since_start = $current_day_of_week;
        }
        
        $current_week_start = clone $today;
        $current_week_start->modify("-{$days_since_start} days");
        $current_week_start->setTime(0, 0, 0);
        
        $current_week_end = clone $current_week_start;
        $current_week_end->modify('+7 days');
        
        $next_week_start = clone $current_week_end;
        $next_week_end = clone $next_week_start;
        $next_week_end->modify('+7 days');
        
        // Build output
        $output = '<div class="upcoming-events upcoming-columns-' . esc_attr($columns) . '">';
        
        $has_events = false;
        
        // Current week events
        if ($show_current_week) {
            $current_week_events = $this->get_events_in_range($current_week_start, $current_week_end, $exclude_categories);
            if (!empty($current_week_events) || $show_empty) {
                $output .= '<div class="upcoming-week upcoming-current-week">';
                $output .= '<h3>' . esc_html($atts['this-week-title']) . '</h3>';
                $output .= $this->render_events_list($current_week_events, $show_time, $link_titles, $atts['empty-message']);
                $output .= '</div>';
                if (!empty($current_week_events)) $has_events = true;
            }
        }
        
        // Next week events
        if ($show_next_week) {
            $next_week_events = $this->get_events_in_range($next_week_start, $next_week_end, $exclude_categories);
            if (!empty($next_week_events) || $show_empty) {
                $output .= '<div class="upcoming-week upcoming-next-week">';
                $output .= '<h3>' . esc_html($atts['next-week-title']) . '</h3>';
                $output .= $this->render_events_list($next_week_events, $show_time, $link_titles, $atts['empty-message']);
                $output .= '</div>';
                if (!empty($next_week_events)) $has_events = true;
            }
        }
        
        // If no events at all and not showing empty message
        if (!$has_events && !$show_empty) {
            return '';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Get TEC events within a date range
     * 
     * @param DateTime $start Start date
     * @param DateTime $end End date
     * @param array $exclude_categories Categories to exclude
     * @return array Array of event objects
     */
    private function get_events_in_range($start, $end, $exclude_categories = array()) {
        $args = array(
            'post_type'      => 'tribe_events',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_key'       => '_EventStartDate',
            'meta_query'     => array(
                array(
                    'key'     => '_EventStartDate',
                    'value'   => $start->format('Y-m-d H:i:s'),
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                ),
                array(
                    'key'     => '_EventStartDate',
                    'value'   => $end->format('Y-m-d H:i:s'),
                    'compare' => '<',
                    'type'    => 'DATETIME',
                ),
            ),
        );
        
        // Exclude categories if specified
        if (!empty($exclude_categories)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'tribe_events_cat',
                    'field'    => 'name',
                    'terms'    => $exclude_categories,
                    'operator' => 'NOT IN',
                ),
            );
        }
        
        $query = new WP_Query($args);
        $events = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $event_id = get_the_ID();
                
                $events[] = array(
                    'id'         => $event_id,
                    'title'      => get_the_title(),
                    'url'        => get_permalink(),
                    'start_date' => get_post_meta($event_id, '_EventStartDate', true),
                    'end_date'   => get_post_meta($event_id, '_EventEndDate', true),
                    'all_day'    => get_post_meta($event_id, '_EventAllDay', true) === 'yes',
                );
            }
            wp_reset_postdata();
        }
        
        // Sort by start date
        usort($events, function($a, $b) {
            return strtotime($a['start_date']) - strtotime($b['start_date']);
        });
        
        return $events;
    }
    
    /**
     * Render a list of events
     * 
     * @param array $events Array of event data
     * @param bool $show_time Whether to show event time
     * @param bool $link_titles Whether to make titles clickable
     * @param string $empty_message Message to show if no events
     * @return string HTML output
     */
    private function render_events_list($events, $show_time, $link_titles, $empty_message) {
        if (empty($events)) {
            return '<p class="upcoming-empty">' . esc_html($empty_message) . '</p>';
        }
        
        $output = '<ul class="upcoming-list">';
        
        foreach ($events as $event) {
            $start = strtotime($event['start_date']);
            
            // Format date: M/D (e.g., 12/4)
            $date_str = date_i18n('n/j', $start);
            
            // Format time if needed
            $time_str = '';
            if ($show_time && !$event['all_day']) {
                $time_str = ' ' . date_i18n('g:ia', $start);
            }
            
            $output .= '<li class="upcoming-event">';
            $output .= '<span class="upcoming-date">' . esc_html($date_str . $time_str) . '</span>';
            $output .= '<span class="upcoming-separator"> – </span>';
            
            if ($link_titles && !empty($event['url'])) {
                $output .= '<a href="' . esc_url($event['url']) . '" class="upcoming-title">' . esc_html($event['title']) . '</a>';
            } else {
                $output .= '<span class="upcoming-title">' . esc_html($event['title']) . '</span>';
            }
            
            $output .= '</li>';
        }
        
        $output .= '</ul>';
        
        return $output;
    }
    
    /**
     * Get available TEC categories for admin reference
     * 
     * @return array Array of category names
     */
    public static function get_tec_categories() {
        $categories = get_terms(array(
            'taxonomy'   => 'tribe_events_cat',
            'hide_empty' => false,
        ));
        
        if (is_wp_error($categories)) {
            return array();
        }
        
        return wp_list_pluck($categories, 'name');
    }
}

