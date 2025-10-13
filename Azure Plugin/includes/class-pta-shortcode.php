<?php
/**
 * PTA Shortcode handler for Azure Plugin
 * 
 * TEAM MEMBERS INSPIRED LAYOUT USAGE:
 * 
 * Basic Team Cards Layout:
 * [pta-roles-directory layout="team-cards" columns="3"]
 * 
 * Advanced Team Cards with Custom Options:
 * [pta-roles-directory layout="team-cards" columns="4" department="communications" 
 *  show_avatars="true" show_contact="true" avatar_size="80" description="true"]
 * 
 * Layout Options:
 * - grid: Traditional grid cards (default)
 * - list: Simple list view
 * - cards: Enhanced cards with borders
 * - team-cards: Team Members plugin inspired with circular avatars
 * 
 * Team Cards Specific Options:
 * - show_avatars: true/false (show user avatars)
 * - show_contact: true/false (show email/phone links)
 * - avatar_size: number (avatar size in pixels, default 80)
 * - columns: 1-5 (responsive: desktop full, tablet 2, mobile 1)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_PTA_Shortcode {
    
    private $pta_manager;
    
    public function __construct() {
        // Check if PTA Manager is available before using it
        if (class_exists('Azure_PTA_Manager')) {
            $this->pta_manager = Azure_PTA_Manager::get_instance();
        } else {
            $this->pta_manager = null;
            Azure_Logger::warning('PTA Shortcode: PTA Manager class not available - shortcodes will display placeholder content');
        }
        
        // Register PTA shortcodes
        add_shortcode('pta-roles-directory', array($this, 'roles_directory_shortcode'));
        add_shortcode('pta-department-roles', array($this, 'department_roles_shortcode'));
        add_shortcode('pta-org-chart', array($this, 'org_chart_shortcode'));
        add_shortcode('pta-role-card', array($this, 'role_card_shortcode'));
        add_shortcode('pta-department-vp', array($this, 'department_vp_shortcode'));
        add_shortcode('pta-open-positions', array($this, 'open_positions_shortcode'));
        add_shortcode('pta-user-roles', array($this, 'user_roles_shortcode'));
        
        // Enqueue frontend styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }
    
    /**
     * Check if PTA Manager is available and show appropriate message
     */
    private function check_pta_manager_availability() {
        if ($this->pta_manager === null) {
            return '<div class="pta-shortcode-unavailable">' . 
                   '<p><strong>PTA Manager Unavailable:</strong> This shortcode requires the PTA Manager component which is currently disabled.</p>' . 
                   '</div>';
        }
        return false;
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style('pta-roles-frontend', AZURE_PLUGIN_URL . 'css/pta-roles-frontend.css', array(), AZURE_PLUGIN_VERSION);
        wp_enqueue_script('pta-shortcodes', AZURE_PLUGIN_URL . 'assets/pta-shortcodes.js', array('jquery'), AZURE_PLUGIN_VERSION, true);
    }
    
    /**
     * Roles Directory shortcode
     * Usage: [pta-roles-directory department="communications" description=true status="open" columns=3]
     */
    public function roles_directory_shortcode($atts) {
        // Check if PTA Manager is available
        $availability_check = $this->check_pta_manager_availability();
        if ($availability_check !== false) {
            return $availability_check;
        }
        
        $atts = shortcode_atts(array(
            'department' => '',
            'description' => false,
            'status' => 'all', // all, open, filled, partial
            'columns' => 3,
            'show_count' => true,
            'show_vp' => false,
            'layout' => 'grid', // grid, list, cards, team-cards
            'show_avatars' => true,
            'show_contact' => true,
            'avatar_size' => 80
        ), $atts);
        
        // Get roles
        $department_id = null;
        if ($atts['department']) {
            $departments = $this->pta_manager->get_departments();
            foreach ($departments as $dept) {
                if (strtolower($dept->slug) === strtolower($atts['department']) || 
                    strtolower($dept->name) === strtolower($atts['department'])) {
                    $department_id = $dept->id;
                    break;
                }
            }
        }
        
        $roles = $this->pta_manager->get_roles($department_id, true);
        
        if (empty($roles)) {
            return '<p class="pta-no-roles">No roles found.</p>';
        }
        
        // Filter by status
        if ($atts['status'] !== 'all') {
            $roles = array_filter($roles, function($role) use ($atts) {
                $status = $this->get_role_status($role);
                return $status === $atts['status'];
            });
        }
        
        return $this->render_roles_directory($roles, $atts);
    }
    
    /**
     * Department Roles shortcode
     * Usage: [pta-department-roles department="communications" show_vp=true]
     */
    public function department_roles_shortcode($atts) {
        // Check if PTA Manager is available
        $availability_check = $this->check_pta_manager_availability();
        if ($availability_check !== false) {
            return $availability_check;
        }
        
        $atts = shortcode_atts(array(
            'department' => '',
            'show_vp' => true,
            'show_description' => false,
            'layout' => 'list'
        ), $atts);
        
        if (empty($atts['department'])) {
            return '<p class="pta-error">Department parameter is required.</p>';
        }
        
        // Get department
        $departments = $this->pta_manager->get_departments(true);
        $department = null;
        foreach ($departments as $dept) {
            if (strtolower($dept->slug) === strtolower($atts['department']) || 
                strtolower($dept->name) === strtolower($atts['department'])) {
                $department = $dept;
                break;
            }
        }
        
        if (!$department) {
            return '<p class="pta-error">Department not found.</p>';
        }
        
        $roles = $this->pta_manager->get_roles($department->id, true);
        
        $output = '<div class="pta-department-roles">';
        $output .= '<h3>' . esc_html($department->name) . '</h3>';
        
        if ($atts['show_vp'] && $department->vp_user_id) {
            $vp_user = get_user_by('ID', $department->vp_user_id);
            if ($vp_user) {
                $output .= '<p class="pta-department-vp"><strong>VP:</strong> ' . esc_html($vp_user->display_name) . '</p>';
            }
        }
        
        if (!empty($roles)) {
            $output .= $this->render_roles_list($roles, $atts);
        } else {
            $output .= '<p class="pta-no-roles">No roles in this department.</p>';
        }
        
        $output .= '</div>';
        return $output;
    }
    
    /**
     * Org Chart shortcode
     * Usage: [pta-org-chart department="all" interactive=true]
     */
    public function org_chart_shortcode($atts) {
        // Check if PTA Manager is available
        $availability_check = $this->check_pta_manager_availability();
        if ($availability_check !== false) {
            return $availability_check;
        }
        
        $atts = shortcode_atts(array(
            'department' => 'all',
            'interactive' => false,
            'height' => '400px'
        ), $atts);
        
        wp_enqueue_script('d3', 'https://d3js.org/d3.v7.min.js', array(), '7.0.0', true);
        
        $org_data = $this->get_org_chart_data($atts['department']);
        
        $chart_id = 'pta-org-chart-' . uniqid();
        
        $output = '<div class="pta-org-chart-container">';
        $output .= '<div id="' . $chart_id . '" class="pta-org-chart" style="height: ' . esc_attr($atts['height']) . ';"></div>';
        $output .= '</div>';
        
        // Add inline JavaScript for the chart
        $output .= '<script>
        jQuery(document).ready(function($) {
            var orgData = ' . json_encode($org_data) . ';
            if (typeof renderPTAOrgChart === "function") {
                renderPTAOrgChart("' . $chart_id . '", orgData, ' . json_encode($atts) . ');
            }
        });
        </script>';
        
        return $output;
    }
    
    /**
     * Role Card shortcode
     * Usage: [pta-role-card role="president" show_contact=true]
     */
    public function role_card_shortcode($atts) {
        // Check if PTA Manager is available
        $availability_check = $this->check_pta_manager_availability();
        if ($availability_check !== false) {
            return $availability_check;
        }
        
        $atts = shortcode_atts(array(
            'role' => '',
            'show_contact' => false,
            'show_description' => true,
            'show_assignments' => true
        ), $atts);
        
        if (empty($atts['role'])) {
            return '<p class="pta-error">Role parameter is required.</p>';
        }
        
        $roles = $this->pta_manager->get_roles(null, true);
        $role = null;
        
        foreach ($roles as $r) {
            if (strtolower($r->slug) === strtolower($atts['role']) || 
                strtolower($r->name) === strtolower($atts['role'])) {
                $role = $r;
                break;
            }
        }
        
        if (!$role) {
            return '<p class="pta-error">Role not found.</p>';
        }
        
        return $this->render_role_card($role, $atts);
    }
    
    /**
     * Department VP shortcode
     * Usage: [pta-department-vp department="communications"]
     */
    public function department_vp_shortcode($atts) {
        // Check if PTA Manager is available
        $availability_check = $this->check_pta_manager_availability();
        if ($availability_check !== false) {
            return $availability_check;
        }
        
        $atts = shortcode_atts(array(
            'department' => '',
            'show_contact' => false,
            'show_email' => false
        ), $atts);
        
        if (empty($atts['department'])) {
            return '<p class="pta-error">Department parameter is required.</p>';
        }
        
        $departments = $this->pta_manager->get_departments(true);
        $department = null;
        
        foreach ($departments as $dept) {
            if (strtolower($dept->slug) === strtolower($atts['department']) || 
                strtolower($dept->name) === strtolower($atts['department'])) {
                $department = $dept;
                break;
            }
        }
        
        if (!$department || !$department->vp_user_id) {
            return '<p class="pta-no-vp">No VP assigned for this department.</p>';
        }
        
        $vp_user = get_user_by('ID', $department->vp_user_id);
        if (!$vp_user) {
            return '<p class="pta-no-vp">VP user not found.</p>';
        }
        
        $output = '<div class="pta-department-vp-card">';
        $output .= '<h4>' . esc_html($department->name) . ' VP</h4>';
        $output .= '<div class="pta-vp-name">' . esc_html($vp_user->display_name) . '</div>';
        
        if ($atts['show_email'] && $vp_user->user_email) {
            $output .= '<div class="pta-vp-email"><a href="mailto:' . esc_attr($vp_user->user_email) . '">' . esc_html($vp_user->user_email) . '</a></div>';
        }
        
        $output .= '</div>';
        return $output;
    }
    
    /**
     * Open Positions shortcode
     * Usage: [pta-open-positions department="all" limit=10]
     */
    public function open_positions_shortcode($atts) {
        // Check if PTA Manager is available
        $availability_check = $this->check_pta_manager_availability();
        if ($availability_check !== false) {
            return $availability_check;
        }
        
        $atts = shortcode_atts(array(
            'department' => 'all',
            'limit' => -1,
            'show_department' => true,
            'show_description' => false
        ), $atts);
        
        $department_id = null;
        if ($atts['department'] !== 'all') {
            $departments = $this->pta_manager->get_departments();
            foreach ($departments as $dept) {
                if (strtolower($dept->slug) === strtolower($atts['department']) || 
                    strtolower($dept->name) === strtolower($atts['department'])) {
                    $department_id = $dept->id;
                    break;
                }
            }
        }
        
        $roles = $this->pta_manager->get_roles($department_id, true);
        
        // Filter to only open positions
        $open_roles = array_filter($roles, function($role) {
            return $role->assigned_count < $role->max_occupants;
        });
        
        if (empty($open_roles)) {
            return '<p class="pta-no-openings">No open positions available.</p>';
        }
        
        // Limit results
        if ($atts['limit'] > 0) {
            $open_roles = array_slice($open_roles, 0, $atts['limit']);
        }
        
        return $this->render_open_positions($open_roles, $atts);
    }
    
    /**
     * User Roles shortcode
     * Usage: [pta-user-roles user_id=123] or [pta-user-roles] (current user)
     */
    public function user_roles_shortcode($atts) {
        // Check if PTA Manager is available
        $availability_check = $this->check_pta_manager_availability();
        if ($availability_check !== false) {
            return $availability_check;
        }
        
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'show_department' => true,
            'show_description' => false
        ), $atts);
        
        if (empty($atts['user_id'])) {
            return '<p class="pta-error">No user specified and no current user.</p>';
        }
        
        $user = get_user_by('ID', $atts['user_id']);
        if (!$user) {
            return '<p class="pta-error">User not found.</p>';
        }
        
        $assignments = $this->pta_manager->get_user_assignments($atts['user_id']);
        
        if (empty($assignments)) {
            return '<p class="pta-no-assignments">No role assignments found.</p>';
        }
        
        return $this->render_user_roles($assignments, $atts);
    }
    
    /**
     * Helper methods for rendering
     */
    private function render_roles_directory($roles, $atts) {
        $columns = max(1, min(5, intval($atts['columns'])));
        $layout = $atts['layout'];
        
        $output = '<div class="pta-roles-directory pta-layout-' . esc_attr($layout) . '" data-columns="' . $columns . '">';
        
        foreach ($roles as $role) {
            $status = $this->get_role_status($role);
            
            if ($layout === 'team-cards') {
                $output .= $this->render_team_card($role, $atts, $status);
            } else {
                // Original grid/list/cards layout
                $output .= '<div class="pta-role-item pta-status-' . esc_attr($status) . '">';
                $output .= '<h4 class="pta-role-name">' . esc_html($role->name) . '</h4>';
                
                if ($atts['show_count']) {
                    $output .= '<div class="pta-role-count">' . $role->assigned_count . ' of ' . $role->max_occupants . ' filled</div>';
                }
                
                if ($atts['description'] && $role->description) {
                    $output .= '<div class="pta-role-description">' . esc_html($role->description) . '</div>';
                }
                
                $output .= '<div class="pta-role-department">' . esc_html($role->department_name) . '</div>';
                $output .= '<div class="pta-role-status pta-status-' . esc_attr($status) . '">' . ucfirst($status) . '</div>';
                $output .= '</div>';
            }
        }
        
        $output .= '</div>';
        return $output;
    }
    
    /**
     * Render team member style card (Team Members plugin inspired)
     */
    private function render_team_card($role, $atts, $status) {
        $output = '<div class="pta-role-item pta-status-' . esc_attr($status) . '">';
        
        // Get first assigned user for avatar (or show placeholder)
        $assigned_user = null;
        if (!empty($role->assignments)) {
            $assigned_user = get_user_by('ID', $role->assignments[0]->user_id);
        }
        
        // Avatar/Photo section
        if ($atts['show_avatars'] && $assigned_user) {
            $avatar_url = get_avatar_url($assigned_user->ID, array('size' => intval($atts['avatar_size'])));
            $output .= '<div class="pta-role-avatar" style="background-image: url(' . esc_url($avatar_url) . ');"></div>';
        } else {
            // Placeholder avatar with initials or icon
            $initials = $this->get_role_initials($role->name);
            $output .= '<div class="pta-role-avatar">' . esc_html($initials) . '</div>';
        }
        
        // Text content section
        $output .= '<div class="pta-role-textblock">';
        
        // Role name
        $output .= '<h4 class="pta-role-name">' . esc_html($role->name) . '</h4>';
        
        // Department
        $output .= '<div class="pta-role-department">' . esc_html($role->department_name) . '</div>';
        
        // Assignment count
        if ($atts['show_count']) {
            $output .= '<div class="pta-role-count">' . $role->assigned_count . ' of ' . $role->max_occupants . ' filled</div>';
        }
        
        // Description
        if ($atts['description'] && $role->description) {
            $output .= '<div class="pta-role-description">' . esc_html($role->description) . '</div>';
        }
        
        // Contact links section
        if ($atts['show_contact'] && $assigned_user) {
            $output .= '<div class="pta-role-contacts">';
            
            // Email link
            if ($assigned_user->user_email) {
                $output .= '<a href="mailto:' . esc_attr($assigned_user->user_email) . '" class="pta-role-contact-link" title="Email ' . esc_attr($assigned_user->display_name) . '">@</a>';
            }
            
            // Phone link (if available in user meta)
            $phone = get_user_meta($assigned_user->ID, 'phone', true);
            if ($phone) {
                $output .= '<a href="tel:' . esc_attr($phone) . '" class="pta-role-contact-link" title="Call ' . esc_attr($assigned_user->display_name) . '">📞</a>';
            }
            
            $output .= '</div>';
        }
        
        $output .= '</div>'; // Close text block
        
        // Status badge
        $output .= '<div class="pta-role-status pta-status-' . esc_attr($status) . '">' . ucfirst($status) . '</div>';
        
        $output .= '</div>'; // Close role item
        
        return $output;
    }
    
    /**
     * Generate initials from role name for placeholder avatars
     */
    private function get_role_initials($name) {
        $words = explode(' ', trim($name));
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        } else {
            return strtoupper(substr($name, 0, 2));
        }
    }
    
    private function render_role_card($role, $atts) {
        $status = $this->get_role_status($role);
        
        $output = '<div class="pta-role-card pta-status-' . esc_attr($status) . '">';
        $output .= '<h3 class="pta-role-title">' . esc_html($role->name) . '</h3>';
        $output .= '<div class="pta-role-department">' . esc_html($role->department_name) . '</div>';
        
        if ($atts['show_description'] && $role->description) {
            $output .= '<div class="pta-role-description">' . esc_html($role->description) . '</div>';
        }
        
        $output .= '<div class="pta-role-count">' . $role->assigned_count . ' of ' . $role->max_occupants . ' positions filled</div>';
        
        if ($atts['show_assignments'] && !empty($role->assignments)) {
            $output .= '<div class="pta-role-assignments">';
            $output .= '<h5>Current Assignments:</h5>';
            $output .= '<ul>';
            foreach ($role->assignments as $assignment) {
                $user = get_user_by('ID', $assignment->user_id);
                if ($user) {
                    $output .= '<li>' . esc_html($user->display_name);
                    if ($atts['show_contact'] && $user->user_email) {
                        $output .= ' - <a href="mailto:' . esc_attr($user->user_email) . '">' . esc_html($user->user_email) . '</a>';
                    }
                    $output .= '</li>';
                }
            }
            $output .= '</ul>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        return $output;
    }
    
    private function get_role_status($role) {
        if ($role->assigned_count >= $role->max_occupants) {
            return 'filled';
        } elseif ($role->assigned_count > 0) {
            return 'partial';
        } else {
            return 'open';
        }
    }
    
    private function get_org_chart_data($department_filter) {
        $departments = $this->pta_manager->get_departments(true);
        $roles = $this->pta_manager->get_roles(null, true);
        
        $org_data = array(
            'departments' => array(),
            'roles' => array(),
            'assignments' => array()
        );
        
        foreach ($departments as $dept) {
            if ($department_filter !== 'all' && strtolower($dept->slug) !== strtolower($department_filter)) {
                continue;
            }
            
            $vp_name = '';
            if ($dept->vp_user_id) {
                $vp_user = get_user_by('ID', $dept->vp_user_id);
                if ($vp_user) {
                    $vp_name = $vp_user->display_name;
                }
            }
            
            $org_data['departments'][] = array(
                'id' => $dept->id,
                'name' => $dept->name,
                'vp' => $vp_name
            );
        }
        
        foreach ($roles as $role) {
            if ($department_filter !== 'all') {
                $dept_match = false;
                foreach ($departments as $dept) {
                    if ($dept->id == $role->department_id && strtolower($dept->slug) === strtolower($department_filter)) {
                        $dept_match = true;
                        break;
                    }
                }
                if (!$dept_match) continue;
            }
            
            $org_data['roles'][] = array(
                'id' => $role->id,
                'name' => $role->name,
                'department_id' => $role->department_id,
                'max_occupants' => $role->max_occupants,
                'assigned_count' => $role->assigned_count
            );
            
            if (!empty($role->assignments)) {
                foreach ($role->assignments as $assignment) {
                    $user = get_user_by('ID', $assignment->user_id);
                    if ($user) {
                        $org_data['assignments'][] = array(
                            'role_id' => $role->id,
                            'user_name' => $user->display_name,
                            'user_email' => $user->user_email
                        );
                    }
                }
            }
        }
        
        return $org_data;
    }
    
    private function render_open_positions($roles, $atts) {
        $output = '<div class="pta-open-positions">';
        $output .= '<h3>Open Positions</h3>';
        $output .= '<ul class="pta-positions-list">';
        
        foreach ($roles as $role) {
            $open_count = $role->max_occupants - $role->assigned_count;
            $output .= '<li class="pta-position-item">';
            $output .= '<strong>' . esc_html($role->name) . '</strong>';
            
            if ($atts['show_department']) {
                $output .= ' <span class="pta-department">(' . esc_html($role->department_name) . ')</span>';
            }
            
            $output .= ' <span class="pta-open-count">' . $open_count . ' opening' . ($open_count > 1 ? 's' : '') . '</span>';
            
            if ($atts['show_description'] && $role->description) {
                $output .= '<div class="pta-position-description">' . esc_html($role->description) . '</div>';
            }
            
            $output .= '</li>';
        }
        
        $output .= '</ul>';
        $output .= '</div>';
        return $output;
    }
    
    private function render_user_roles($assignments, $atts) {
        $output = '<div class="pta-user-roles">';
        $output .= '<ul class="pta-assignments-list">';
        
        foreach ($assignments as $assignment) {
            $role = $this->pta_manager->get_role($assignment->role_id);
            if ($role) {
                $output .= '<li class="pta-assignment-item">';
                $output .= '<strong>' . esc_html($role->name) . '</strong>';
                
                if ($atts['show_department']) {
                    $output .= ' <span class="pta-department">(' . esc_html($role->department_name) . ')</span>';
                }
                
                if ($atts['show_description'] && $role->description) {
                    $output .= '<div class="pta-assignment-description">' . esc_html($role->description) . '</div>';
                }
                
                $output .= '</li>';
            }
        }
        
        $output .= '</ul>';
        $output .= '</div>';
        return $output;
    }
}


