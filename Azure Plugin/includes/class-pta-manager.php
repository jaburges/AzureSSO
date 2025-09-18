<?php
/**
 * PTA Manager - Core functionality for roles, departments, and assignments
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_PTA_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize database - wrap in try/catch to prevent fatal errors
        try {
            Azure_PTA_Database::init();
        } catch (Exception $e) {
            if (class_exists('Azure_Logger')) {
                Azure_Logger::error('PTA Manager: Database init failed - ' . $e->getMessage());
            }
            error_log('PTA Manager: Database init failed - ' . $e->getMessage());
        }
        
        // AJAX handlers for admin
        add_action('wp_ajax_pta_get_roles', array($this, 'ajax_get_roles'));
        add_action('wp_ajax_pta_get_departments', array($this, 'ajax_get_departments'));
        add_action('wp_ajax_pta_get_assignments', array($this, 'ajax_get_assignments'));
        add_action('wp_ajax_pta_assign_role', array($this, 'ajax_assign_role'));
        add_action('wp_ajax_pta_remove_assignment', array($this, 'ajax_remove_assignment'));
        add_action('wp_ajax_pta_update_role', array($this, 'ajax_update_role'));
        add_action('wp_ajax_pta_update_department', array($this, 'ajax_update_department'));
        add_action('wp_ajax_pta_get_org_data', array($this, 'ajax_get_org_data'));
        add_action('wp_ajax_pta_get_users', array($this, 'ajax_get_users'));
        add_action('wp_ajax_pta_create_role', array($this, 'ajax_create_role'));
        add_action('wp_ajax_pta_delete_role', array($this, 'ajax_delete_role'));
        add_action('wp_ajax_pta_create_department', array($this, 'ajax_create_department'));
        add_action('wp_ajax_pta_delete_department', array($this, 'ajax_delete_department'));
        add_action('wp_ajax_pta_bulk_delete_users', array($this, 'ajax_bulk_delete_users'));
        add_action('wp_ajax_pta_bulk_change_role', array($this, 'ajax_bulk_change_role'));
        add_action('wp_ajax_pta_reimport_default_tables', array($this, 'ajax_reimport_default_tables'));
        
        // Hooks for user sync
        add_action('pta_user_assignment_changed', array($this, 'trigger_user_sync'), 10, 3);
        add_action('pta_department_vp_changed', array($this, 'trigger_department_sync'), 10, 2);
        
        // Schedule cleanup
        add_action('init', array($this, 'schedule_cleanup'));
        add_action('pta_daily_cleanup', array($this, 'daily_cleanup'));
    }
    
    /**
     * Get all departments
     */
    public function get_departments($include_roles = false) {
        global $wpdb;
        $table = Azure_PTA_Database::get_table_name('departments');
        
        $departments = $wpdb->get_results("SELECT * FROM $table ORDER BY name");
        
        if ($include_roles) {
            foreach ($departments as $dept) {
                $dept->roles = $this->get_roles_by_department($dept->id);
                $dept->vp_info = $this->get_user_info($dept->vp_user_id);
            }
        }
        
        return $departments;
    }
    
    /**
     * Get all roles
     */
    public function get_roles($department_id = null, $include_assignments = false) {
        global $wpdb;
        $roles_table = Azure_PTA_Database::get_table_name('roles');
        $dept_table = Azure_PTA_Database::get_table_name('departments');
        
        $where = '';
        $params = array();
        
        if ($department_id) {
            $where = 'WHERE r.department_id = %d';
            $params[] = $department_id;
        }
        
        $sql = "SELECT r.*, d.name as department_name 
                FROM $roles_table r 
                JOIN $dept_table d ON r.department_id = d.id 
                $where 
                ORDER BY d.name, r.name";
        
        Azure_Logger::debug("PTA: Executing SQL: $sql with params: " . json_encode($params));
        
        if (empty($params)) {
            $roles = $wpdb->get_results($sql);
        } else {
            $roles = $wpdb->get_results($wpdb->prepare($sql, $params));
        }
        
        if ($wpdb->last_error) {
            Azure_Logger::error("PTA: SQL Error in get_roles(): " . $wpdb->last_error);
        }
        
        Azure_Logger::debug("PTA: get_roles() found " . count($roles) . " roles");
        
        if ($include_assignments) {
            foreach ($roles as $role) {
                $assignments = $this->get_role_assignments($role->id);
                $role->assignments = $assignments;
                $role->assigned_count = count($assignments);
                $role->open_positions = max(0, $role->max_occupants - $role->assigned_count);
                $role->status = $this->calculate_role_status($role);
            }
        }
        
        return $roles;
    }
    
    /**
     * Get roles by department
     */
    public function get_roles_by_department($department_id) {
        return $this->get_roles($department_id, true);
    }
    
    /**
     * Get a single role by ID
     */
    public function get_role($role_id) {
        global $wpdb;
        $roles_table = Azure_PTA_Database::get_table_name('roles');
        $dept_table = Azure_PTA_Database::get_table_name('departments');
        
        $sql = "SELECT r.*, d.name as department_name 
                FROM $roles_table r 
                JOIN $dept_table d ON r.department_id = d.id 
                WHERE r.id = %d";
        
        $role = $wpdb->get_row($wpdb->prepare($sql, $role_id));
        
        if ($role) {
            $assignments = $this->get_role_assignments($role->id);
            $role->assignments = $assignments;
            $role->assigned_count = count($assignments);
            $role->open_positions = max(0, $role->max_occupants - $role->assigned_count);
            $role->status = $this->calculate_role_status($role);
        }
        
        return $role;
    }
    
    /**
     * Get user assignments (roles for a specific user)
     */
    public function get_user_assignments($user_id) {
        global $wpdb;
        $assignments_table = Azure_PTA_Database::get_table_name('role_assignments');
        $roles_table = Azure_PTA_Database::get_table_name('roles');
        $dept_table = Azure_PTA_Database::get_table_name('departments');
        
        $sql = "SELECT ra.*, r.name as role_name, r.description as role_description, 
                       d.name as department_name, r.id as role_id
                FROM $assignments_table ra 
                JOIN $roles_table r ON ra.role_id = r.id 
                JOIN $dept_table d ON r.department_id = d.id
                WHERE ra.user_id = %d 
                ORDER BY d.name, r.name";
        
        return $wpdb->get_results($wpdb->prepare($sql, $user_id));
    }
    
    /**
     * Get role assignments
     */
    public function get_role_assignments($role_id) {
        global $wpdb;
        $table = Azure_PTA_Database::get_table_name('assignments');
        
        $assignments = $wpdb->get_results($wpdb->prepare(
            "SELECT ra.*, u.display_name, u.user_email 
             FROM $table ra 
             JOIN {$wpdb->users} u ON ra.user_id = u.ID 
             WHERE ra.role_id = %d AND ra.status = 'active' 
             ORDER BY ra.is_primary DESC, u.display_name",
            $role_id
        ));
        
        foreach ($assignments as $assignment) {
            $assignment->user_meta = get_userdata($assignment->user_id);
        }
        
        return $assignments;
    }
    
    /**
     * Get user assignments
     */
    public function get_user_assignments($user_id, $active_only = true) {
        global $wpdb;
        $assignments_table = Azure_PTA_Database::get_table_name('assignments');
        $roles_table = Azure_PTA_Database::get_table_name('roles');
        $dept_table = Azure_PTA_Database::get_table_name('departments');
        
        $where = 'WHERE ra.user_id = %d';
        $params = array($user_id);
        
        if ($active_only) {
            $where .= ' AND ra.status = %s';
            $params[] = 'active';
        }
        
        $sql = "SELECT ra.*, r.name as role_name, r.slug as role_slug, 
                       d.name as department_name, d.slug as department_slug
                FROM $assignments_table ra
                JOIN $roles_table r ON ra.role_id = r.id
                JOIN $dept_table d ON r.department_id = d.id
                $where
                ORDER BY ra.is_primary DESC, d.name, r.name";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    /**
     * Assign user to role
     */
    public function assign_user_to_role($user_id, $role_id, $is_primary = false, $assigned_by = null) {
        global $wpdb;
        
        // Validate inputs
        if (!get_userdata($user_id)) {
            throw new Exception('Invalid user ID');
        }
        
        $role = $this->get_role($role_id);
        if (!$role) {
            throw new Exception('Invalid role ID');
        }
        
        // Check if role is full
        $current_assignments = $this->get_role_assignments($role_id);
        if (count($current_assignments) >= $role->max_occupants) {
            throw new Exception('Role is already full');
        }
        
        // Check if user is already assigned to this role
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM " . Azure_PTA_Database::get_table_name('assignments') . " 
             WHERE user_id = %d AND role_id = %d AND status = 'active'",
            $user_id, $role_id
        ));
        
        if ($existing) {
            throw new Exception('User is already assigned to this role');
        }
        
        $table = Azure_PTA_Database::get_table_name('assignments');
        $assigned_by = $assigned_by ?: get_current_user_id();
        
        // If this is a primary role, remove any existing primary roles
        if ($is_primary) {
            $wpdb->update(
                $table,
                array('is_primary' => 0),
                array('user_id' => $user_id),
                array('%d'),
                array('%d')
            );
        }
        
        $result = $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'role_id' => $role_id,
                'is_primary' => $is_primary ? 1 : 0,
                'assigned_by' => $assigned_by,
                'metadata' => json_encode(array('assigned_via' => 'admin_interface'))
            ),
            array('%d', '%d', '%d', '%d', '%s')
        );
        
        if ($result) {
            $assignment_id = $wpdb->insert_id;
            
            // Log the assignment
            Azure_PTA_Database::log_audit(
                'role_assignment', 
                $assignment_id, 
                'assigned',
                null,
                array(
                    'user_id' => $user_id,
                    'role_id' => $role_id,
                    'is_primary' => $is_primary
                )
            );
            
            // Update user job title
            $this->update_user_job_title($user_id);
            
            // Trigger sync
            do_action('pta_user_assignment_changed', $user_id, $role_id, 'assigned');
            
            Azure_Logger::info("PTA: User $user_id assigned to role $role_id");
            
            return $assignment_id;
        }
        
        throw new Exception('Failed to create assignment');
    }
    
    /**
     * Remove user from role
     */
    public function remove_user_from_role($user_id, $role_id) {
        global $wpdb;
        $table = Azure_PTA_Database::get_table_name('assignments');
        
        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND role_id = %d AND status = 'active'",
            $user_id, $role_id
        ));
        
        if (!$assignment) {
            throw new Exception('Assignment not found');
        }
        
        $result = $wpdb->update(
            $table,
            array('status' => 'inactive'),
            array('id' => $assignment->id),
            array('%s'),
            array('%d')
        );
        
        if ($result) {
            // Log the removal
            Azure_PTA_Database::log_audit(
                'role_assignment',
                $assignment->id,
                'removed',
                array(
                    'user_id' => $user_id,
                    'role_id' => $role_id,
                    'is_primary' => $assignment->is_primary
                ),
                null
            );
            
            // Update user job title
            $this->update_user_job_title($user_id);
            
            // Trigger sync
            do_action('pta_user_assignment_changed', $user_id, $role_id, 'removed');
            
            Azure_Logger::info("PTA: User $user_id removed from role $role_id");
            
            return true;
        }
        
        throw new Exception('Failed to remove assignment');
    }
    
    /**
     * Update user's job title based on role assignments
     */
    public function update_user_job_title($user_id) {
        $assignments = $this->get_user_assignments($user_id);
        
        $job_titles = array();
        foreach ($assignments as $assignment) {
            $job_titles[] = $assignment->role_name;
        }
        
        $job_title = implode(', ', $job_titles);
        
        // Update WordPress user meta
        update_user_meta($user_id, 'job_title', $job_title);
        
        // Queue Azure AD sync
        Azure_PTA_Database::queue_sync(
            'user_sync',
            'user',
            $user_id,
            'update_job_title',
            array('job_title' => $job_title),
            5 // High priority
        );
        
        return $job_title;
    }
    
    /**
     * Calculate role status (filled, partially filled, open)
     */
    private function calculate_role_status($role) {
        if ($role->assigned_count >= $role->max_occupants) {
            return array(
                'status' => 'filled',
                'label' => 'Filled',
                'color' => 'success'
            );
        } elseif ($role->assigned_count > 0) {
            return array(
                'status' => 'partially_filled',
                'label' => 'Partially Filled',
                'color' => 'warning'
            );
        } else {
            return array(
                'status' => 'open',
                'label' => 'Open',
                'color' => 'error'
            );
        }
    }
    
    /**
     * Get role by ID
     */
    public function get_role($role_id) {
        global $wpdb;
        $table = Azure_PTA_Database::get_table_name('roles');
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $role_id));
    }
    
    /**
     * Get department by ID
     */
    public function get_department($department_id) {
        global $wpdb;
        $table = Azure_PTA_Database::get_table_name('departments');
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $department_id));
    }
    
    /**
     * Update department VP
     */
    public function update_department_vp($department_id, $vp_user_id) {
        global $wpdb;
        $table = Azure_PTA_Database::get_table_name('departments');
        
        $old_department = $this->get_department($department_id);
        
        $result = $wpdb->update(
            $table,
            array('vp_user_id' => $vp_user_id),
            array('id' => $department_id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            // Log the change
            Azure_PTA_Database::log_audit(
                'department',
                $department_id,
                'vp_updated',
                array('vp_user_id' => $old_department->vp_user_id),
                array('vp_user_id' => $vp_user_id)
            );
            
            // Trigger sync for all users in this department
            do_action('pta_department_vp_changed', $department_id, $vp_user_id);
            
            Azure_Logger::info("PTA: Department $department_id VP updated to user $vp_user_id");
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get user info for display
     */
    private function get_user_info($user_id) {
        if (!$user_id) return null;
        
        $user = get_userdata($user_id);
        if (!$user) return null;
        
        return array(
            'id' => $user->ID,
            'display_name' => $user->display_name,
            'user_email' => $user->user_email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name
        );
    }
    
    /**
     * Get organizational chart data
     */
    public function get_org_chart_data() {
        $departments = $this->get_departments(true);
        $org_data = array();
        
        foreach ($departments as $dept) {
            $dept_data = array(
                'id' => "dept_" . $dept->id,
                'name' => $dept->name,
                'type' => 'department',
                'vp' => $dept->vp_info,
                'roles' => array()
            );
            
            foreach ($dept->roles as $role) {
                $role_data = array(
                    'id' => "role_" . $role->id,
                    'name' => $role->name,
                    'type' => 'role',
                    'max_occupants' => $role->max_occupants,
                    'assigned_count' => $role->assigned_count,
                    'status' => $role->status,
                    'assignments' => array()
                );
                
                foreach ($role->assignments as $assignment) {
                    $role_data['assignments'][] = array(
                        'id' => $assignment->user_id,
                        'name' => $assignment->display_name,
                        'email' => $assignment->user_email,
                        'is_primary' => (bool) $assignment->is_primary
                    );
                }
                
                $dept_data['roles'][] = $role_data;
            }
            
            $org_data[] = $dept_data;
        }
        
        return $org_data;
    }
    
    /**
     * Trigger user sync when assignment changes
     */
    public function trigger_user_sync($user_id, $role_id, $action) {
        // Queue manager sync update
        $assignments = $this->get_user_assignments($user_id);
        $primary_assignment = null;
        
        foreach ($assignments as $assignment) {
            if ($assignment->is_primary) {
                $primary_assignment = $assignment;
                break;
            }
        }
        
        if ($primary_assignment) {
            $department = $this->get_department($primary_assignment->department_id);
            $manager_user_id = $department->vp_user_id;
            
            Azure_PTA_Database::queue_sync(
                'user_sync',
                'user',
                $user_id,
                'update_manager',
                array('manager_user_id' => $manager_user_id),
                5
            );
        }
        
        // Queue group membership sync
        Azure_PTA_Database::queue_sync(
            'group_sync',
            'user',
            $user_id,
            'sync_group_memberships',
            array('role_id' => $role_id, 'action' => $action),
            10
        );
    }
    
    /**
     * Trigger department sync when VP changes
     */
    public function trigger_department_sync($department_id, $vp_user_id) {
        // Get all users in this department
        global $wpdb;
        $sql = "SELECT DISTINCT ra.user_id 
                FROM " . Azure_PTA_Database::get_table_name('assignments') . " ra
                JOIN " . Azure_PTA_Database::get_table_name('roles') . " r ON ra.role_id = r.id
                WHERE r.department_id = %d AND ra.status = 'active' AND ra.is_primary = 1";
        
        $user_ids = $wpdb->get_col($wpdb->prepare($sql, $department_id));
        
        foreach ($user_ids as $user_id) {
            Azure_PTA_Database::queue_sync(
                'user_sync',
                'user',
                $user_id,
                'update_manager',
                array('manager_user_id' => $vp_user_id),
                5
            );
        }
    }
    
    /**
     * Schedule daily cleanup
     */
    public function schedule_cleanup() {
        if (!wp_next_scheduled('pta_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'pta_daily_cleanup');
        }
    }
    
    /**
     * Daily cleanup tasks
     */
    public function daily_cleanup() {
        // Clean up old audit logs
        Azure_PTA_Database::cleanup_old_logs(90);
        
        // Clean up completed sync queue items
        global $wpdb;
        $table = Azure_PTA_Database::get_table_name('sync_queue');
        
        $deleted = $wpdb->query(
            "DELETE FROM $table 
             WHERE status IN ('completed', 'cancelled') 
             AND processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        if ($deleted) {
            Azure_Logger::info("PTA: Cleaned up $deleted old sync queue items");
        }
    }
    
    /**
     * AJAX Handlers
     */
    public function ajax_get_roles() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $department_id = isset($_POST['department_id']) ? intval($_POST['department_id']) : null;
        
        // Add debugging
        global $wpdb;
        $roles_table = Azure_PTA_Database::get_table_name('roles');
        $departments_table = Azure_PTA_Database::get_table_name('departments');
        
        Azure_Logger::debug("PTA AJAX: Getting roles from tables - Roles: $roles_table, Departments: $departments_table");
        
        // Check table counts
        $roles_count = $wpdb->get_var("SELECT COUNT(*) FROM $roles_table");
        $departments_count = $wpdb->get_var("SELECT COUNT(*) FROM $departments_table");
        
        Azure_Logger::debug("PTA AJAX: Current table counts - Roles: $roles_count, Departments: $departments_count");
        
        $roles = $this->get_roles($department_id, true);
        
        Azure_Logger::debug("PTA AJAX: Retrieved " . count($roles) . " roles from get_roles() method");
        
        wp_send_json_success($roles);
    }
    
    public function ajax_get_departments() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $departments = $this->get_departments(true);
        wp_send_json_success($departments);
    }
    
    public function ajax_get_org_data() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $org_data = $this->get_org_chart_data();
        wp_send_json_success($org_data);
    }
    
    public function ajax_assign_role() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $user_id = intval($_POST['user_id']);
        $role_id = intval($_POST['role_id']);
        $is_primary = isset($_POST['is_primary']) && $_POST['is_primary'];
        
        try {
            $assignment_id = $this->assign_user_to_role($user_id, $role_id, $is_primary);
            wp_send_json_success(array('assignment_id' => $assignment_id));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_remove_assignment() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $user_id = intval($_POST['user_id']);
        $role_id = intval($_POST['role_id']);
        
        try {
            $this->remove_user_from_role($user_id, $role_id);
            wp_send_json_success();
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_get_users() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        try {
            global $wpdb;
            
            // Get all users with Azure SSO mapping (synced from Azure AD)
            $sso_users_table = Azure_Database::get_table_name('sso_users');
            
            $sso_user_ids = $wpdb->get_col("SELECT DISTINCT wordpress_user_id FROM $sso_users_table");
            
            // Get users with AzureAD role (SSO users)
            $azure_users = get_users(array(
                'role' => 'azuread',
                'fields' => array('ID', 'display_name', 'user_email', 'user_registered', 'user_login')
            ));
            
            // If no AzureAD role users, fall back to SSO mapped users or all users
            if (empty($azure_users)) {
                $user_args = array(
                    'fields' => array('ID', 'display_name', 'user_email', 'user_registered', 'user_login')
                );
                
                if (!empty($sso_user_ids)) {
                    $user_args['include'] = $sso_user_ids;
                } else {
                    // Fallback to all users
                    $user_args['number'] = 100; // Limit to prevent performance issues
                }
                
                $users = get_users($user_args);
            } else {
                $users = $azure_users;
            }
            
            $users_data = array();
            foreach ($users as $user) {
                $assignments = $this->get_user_assignments($user->ID);
                $roles_list = array();
                $primary_role = null;
                
                foreach ($assignments as $assignment) {
                    $roles_list[] = $assignment->role_name;
                    if ($assignment->is_primary) {
                        $primary_role = $assignment->role_name;
                    }
                }
                
                // Check if user is from Azure AD
                $azure_info = $wpdb->get_row($wpdb->prepare(
                    "SELECT azure_email, azure_display_name, last_login FROM $sso_users_table WHERE wordpress_user_id = %d",
                    $user->ID
                ));
                
                // Get user meta for additional info
                $first_name = get_user_meta($user->ID, 'first_name', true);
                $last_name = get_user_meta($user->ID, 'last_name', true);
                $job_title = get_user_meta($user->ID, 'job_title', true);
                
                $user_data = array(
                    'ID' => $user->ID,
                    'user_login' => $user->user_login,
                    'display_name' => $user->display_name,
                    'user_email' => $user->user_email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'job_title' => $job_title,
                    'user_registered' => $user->user_registered,
                    'roles' => !empty($roles_list) ? implode(', ', $roles_list) : null,
                    'primary_role' => $primary_role,
                    'assignments_count' => count($assignments),
                    'is_azure_user' => !empty($azure_info),
                    'azure_email' => $azure_info ? $azure_info->azure_email : null,
                    'azure_display_name' => $azure_info ? $azure_info->azure_display_name : null,
                    'last_login' => $azure_info ? $azure_info->last_login : null,
                    'has_roles' => count($assignments) > 0
                );
                
                $users_data[] = $user_data;
            }
            
            // Sort users: users with no roles first, then by display name
            usort($users_data, function($a, $b) {
                if ($a['has_roles'] != $b['has_roles']) {
                    return $a['has_roles'] ? 1 : -1; // No roles first
                }
                return strcasecmp($a['display_name'], $b['display_name']);
            });
            
            wp_send_json_success($users_data);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_create_role() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $department_id = intval($_POST['department_id'] ?? 0);
        $max_occupants = intval($_POST['max_occupants'] ?? 1);
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        
        if (empty($name) || !$department_id) {
            wp_send_json_error('Role name and department are required');
        }
        
        try {
            $role_id = $this->create_role($name, $department_id, $max_occupants, $description);
            if ($role_id) {
                wp_send_json_success(array('role_id' => $role_id, 'message' => 'Role created successfully'));
            } else {
                wp_send_json_error('Failed to create role');
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_delete_role() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $role_id = intval($_POST['role_id'] ?? 0);
        
        if (!$role_id) {
            wp_send_json_error('Role ID is required');
        }
        
        try {
            $result = $this->delete_role($role_id);
            if ($result) {
                wp_send_json_success(array('message' => 'Role deleted successfully'));
            } else {
                wp_send_json_error('Failed to delete role');
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_create_department() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $vp_user_id = intval($_POST['vp_user_id'] ?? 0);
        
        if (empty($name)) {
            wp_send_json_error('Department name is required');
        }
        
        try {
            $dept_id = $this->create_department($name, $vp_user_id);
            if ($dept_id) {
                wp_send_json_success(array('dept_id' => $dept_id, 'message' => 'Department created successfully'));
            } else {
                wp_send_json_error('Failed to create department');
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_delete_department() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $dept_id = intval($_POST['dept_id'] ?? 0);
        
        if (!$dept_id) {
            wp_send_json_error('Department ID is required');
        }
        
        try {
            $result = $this->delete_department($dept_id);
            if ($result) {
                wp_send_json_success(array('message' => 'Department deleted successfully'));
            } else {
                wp_send_json_error('Failed to delete department');
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Helper methods for CRUD operations
     */
    private function create_role($name, $department_id, $max_occupants = 1, $description = '') {
        global $wpdb;
        $table = Azure_PTA_Database::get_table_name('roles');
        
        $slug = sanitize_title($name);
        
        // Handle duplicate slugs
        $existing_slug = $wpdb->get_var($wpdb->prepare("SELECT slug FROM $table WHERE slug = %s", $slug));
        if ($existing_slug) {
            $dept_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM " . Azure_PTA_Database::get_table_name('departments') . " WHERE id = %d", $department_id));
            $slug = sanitize_title($name . '-' . $dept_name);
        }
        
        $result = $wpdb->insert($table, array(
            'name' => $name,
            'slug' => $slug,
            'department_id' => $department_id,
            'max_occupants' => $max_occupants,
            'description' => $description
        ), array('%s', '%s', '%d', '%d', '%s'));
        
        return $result ? $wpdb->insert_id : false;
    }
    
    private function delete_role($role_id) {
        global $wpdb;
        $roles_table = Azure_PTA_Database::get_table_name('roles');
        $assignments_table = Azure_PTA_Database::get_table_name('assignments');
        
        // Check if role has assignments
        $assignment_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $assignments_table WHERE role_id = %d AND status = 'active'", $role_id));
        
        if ($assignment_count > 0) {
            throw new Exception('Cannot delete role: ' . $assignment_count . ' people are assigned to this role');
        }
        
        return $wpdb->delete($roles_table, array('id' => $role_id), array('%d'));
    }
    
    private function create_department($name, $vp_user_id = null) {
        global $wpdb;
        $table = Azure_PTA_Database::get_table_name('departments');
        
        $slug = sanitize_title($name);
        
        $result = $wpdb->insert($table, array(
            'name' => $name,
            'slug' => $slug,
            'vp_user_id' => $vp_user_id ?: null
        ), array('%s', '%s', $vp_user_id ? '%d' : null));
        
        return $result ? $wpdb->insert_id : false;
    }
    
    private function delete_department($dept_id) {
        global $wpdb;
        $dept_table = Azure_PTA_Database::get_table_name('departments');
        $roles_table = Azure_PTA_Database::get_table_name('roles');
        
        // Check if department has roles
        $role_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $roles_table WHERE department_id = %d", $dept_id));
        
        if ($role_count > 0) {
            throw new Exception('Cannot delete department: ' . $role_count . ' roles belong to this department');
        }
        
        return $wpdb->delete($dept_table, array('id' => $dept_id), array('%d'));
    }
    
    /**
     * Bulk delete users AJAX handler
     */
    public function ajax_bulk_delete_users() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : array();
        
        if (empty($user_ids)) {
            wp_send_json_error('No users selected');
        }
        
        try {
            $deleted_count = 0;
            $errors = array();
            
            foreach ($user_ids as $user_id) {
                $user = get_user_by('ID', $user_id);
                if (!$user) {
                    $errors[] = "User ID $user_id not found";
                    continue;
                }
                
                // Remove user assignments first
                $this->remove_all_user_assignments($user_id);
                
                // Delete from WordPress
                $wp_deleted = wp_delete_user($user_id);
                
                if ($wp_deleted) {
                    $deleted_count++;
                    
                    // Remove from SSO mapping table
                    global $wpdb;
                    $sso_table = Azure_Database::get_table_name('sso_users');
                    $wpdb->delete($sso_table, array('wordpress_user_id' => $user_id), array('%d'));
                    
                    // TODO: Delete from Azure AD (would need Graph API integration)
                    Azure_Logger::info("PTA: User deleted - ID: $user_id, Name: {$user->display_name}");
                } else {
                    $errors[] = "Failed to delete user: {$user->display_name}";
                }
            }
            
            $message = "Deleted $deleted_count user(s) successfully";
            if (!empty($errors)) {
                $message .= ". Errors: " . implode(', ', $errors);
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'deleted_count' => $deleted_count,
                'errors' => $errors
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Bulk change role AJAX handler
     */
    public function ajax_bulk_change_role() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : array();
        $role_id = intval($_POST['role_id'] ?? 0);
        $action_type = sanitize_text_field($_POST['action_type'] ?? ''); // 'add' or 'replace'
        $is_primary = isset($_POST['is_primary']) && $_POST['is_primary'];
        
        if (empty($user_ids) || empty($role_id)) {
            wp_send_json_error('Missing required parameters');
        }
        
        try {
            $updated_count = 0;
            $errors = array();
            
            foreach ($user_ids as $user_id) {
                $user = get_user_by('ID', $user_id);
                if (!$user) {
                    $errors[] = "User ID $user_id not found";
                    continue;
                }
                
                if ($action_type === 'replace') {
                    // Remove all current assignments
                    $this->remove_all_user_assignments($user_id);
                }
                
                // Add the new role assignment
                $assignment_result = $this->assign_user_to_role($user_id, $role_id, $is_primary);
                
                if ($assignment_result) {
                    $updated_count++;
                    Azure_Logger::info("PTA: Bulk role change - User: {$user->display_name}, Role ID: $role_id, Action: $action_type");
                } else {
                    $errors[] = "Failed to assign role to user: {$user->display_name}";
                }
            }
            
            $message = "Updated $updated_count user(s) successfully";
            if (!empty($errors)) {
                $message .= ". Errors: " . implode(', ', $errors);
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'updated_count' => $updated_count,
                'errors' => $errors
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Remove all role assignments for a user
     */
    private function remove_all_user_assignments($user_id) {
        global $wpdb;
        $assignments_table = Azure_PTA_Database::get_table_name('assignments');
        
        return $wpdb->update(
            $assignments_table,
            array('status' => 'inactive'),
            array('user_id' => $user_id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Reimport default tables AJAX handler
     */
    public function ajax_reimport_default_tables() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized');
        }
        
        try {
            global $wpdb;
            
            // Clear existing data first
            $roles_table = Azure_PTA_Database::get_table_name('roles');
            $departments_table = Azure_PTA_Database::get_table_name('departments');
            
            Azure_Logger::debug("PTA Manager: Using tables - Roles: $roles_table, Departments: $departments_table");
            
            if ($roles_table && $departments_table) {
                // Check if tables exist first
                $roles_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$roles_table'");
                $departments_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$departments_table'");
                
                Azure_Logger::debug("PTA Manager: Table existence - Roles: " . ($roles_table_exists ? 'exists' : 'missing') . ", Departments: " . ($departments_table_exists ? 'exists' : 'missing'));
                
                if (!$roles_table_exists || !$departments_table_exists) {
                    Azure_Logger::error("PTA Manager: Required tables don't exist. Plugin may need to be reactivated.");
                    // DO NOT create tables here - this runs on every page load and causes performance issues
                    // Tables should only be created during plugin activation
                }
                
                // Delete existing roles and departments
                $deleted_roles = $wpdb->query("DELETE FROM $roles_table");
                $deleted_departments = $wpdb->query("DELETE FROM $departments_table");
                
                Azure_Logger::debug("PTA Manager: Deleted $deleted_roles roles and $deleted_departments departments");
                
                // Reset auto increment
                $wpdb->query("ALTER TABLE $roles_table AUTO_INCREMENT = 1");
                $wpdb->query("ALTER TABLE $departments_table AUTO_INCREMENT = 1");
                
                Azure_Logger::info('PTA Manager: Cleared existing roles and departments tables');
                
                // Re-seed the data (force reseed to bypass existing data checks)
                Azure_Logger::debug("PTA Manager: Starting seed_initial_data(true)");
                Azure_PTA_Database::seed_initial_data(true);
                Azure_Logger::debug("PTA Manager: Finished seed_initial_data(true)");
                
                // Get counts of new data
                $roles_count = $wpdb->get_var("SELECT COUNT(*) FROM $roles_table");
                $departments_count = $wpdb->get_var("SELECT COUNT(*) FROM $departments_table");
                
                Azure_Logger::debug("PTA Manager: Final counts - Roles: $roles_count, Departments: $departments_count");
                
                wp_send_json_success(array(
                    'message' => "Successfully reimported default tables! Created $departments_count departments and $roles_count roles from CSV file.",
                    'departments_count' => $departments_count,
                    'roles_count' => $roles_count
                ));
            } else {
                wp_send_json_error('Database tables not found. Please check PTA module configuration.');
            }
        } catch (Exception $e) {
            Azure_Logger::error('PTA Manager: Reimport failed - ' . $e->getMessage());
            wp_send_json_error('Reimport failed: ' . $e->getMessage());
        }
    }
}
?>
