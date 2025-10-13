<?php
/**
 * The Events Calendar Integration Main Class
 * Handles the integration between The Events Calendar plugin and Microsoft Outlook Calendar
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_TEC_Integration {
    
    private static $instance = null;
    private $sync_engine;
    private $data_mapper;
    private $settings;
    
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new Azure_TEC_Integration();
        }
        return self::$instance;
    }
    
    public function __construct() {
        error_log('TEC Integration: 🚀 CONSTRUCTOR STARTED');
        
        try {
            error_log('TEC Integration: Step 1 - Checking Azure_Logger availability');
            if (!class_exists('Azure_Logger')) {
                error_log('TEC Integration: ❌ Azure_Logger not available - exiting constructor');
                return;
            }
            error_log('TEC Integration: ✅ Step 1 - Azure_Logger available');
            Azure_Logger::info('TEC Integration: Constructor started with Azure_Logger available', 'TEC');
            
            error_log('TEC Integration: Step 2 - Checking TEC plugin active');
            if (!$this->is_tec_active()) {
                error_log('TEC Integration: ℹ️ Step 2 - TEC not active, adding admin notice');
                add_action('admin_notices', array($this, 'tec_dependency_notice'));
                Azure_Logger::info('TEC Integration: The Events Calendar not active, showing dependency notice', 'TEC');
                error_log('TEC Integration: ✅ Step 2 - TEC dependency notice added successfully');
                return;
            }
            error_log('TEC Integration: ✅ Step 2 - TEC is active');
            Azure_Logger::info('TEC Integration: The Events Calendar is active, proceeding with initialization', 'TEC');
            
            error_log('TEC Integration: Step 3 - Calling init_components()');
            $this->init_components();
            error_log('TEC Integration: ✅ Step 3 - init_components() completed');
            
            error_log('TEC Integration: Step 4 - Calling register_hooks()');
            $this->register_hooks();
            error_log('TEC Integration: ✅ Step 4 - register_hooks() completed');
            
            error_log('TEC Integration: Step 5 - Checking if admin area');
            if (is_admin()) {
                error_log('TEC Integration: Step 5a - In admin area, calling init_admin()');
                $this->init_admin();
                error_log('TEC Integration: ✅ Step 5a - init_admin() completed');
            } else {
                error_log('TEC Integration: ℹ️ Step 5 - Not in admin area, skipping init_admin()');
            }
            
            error_log('TEC Integration: 🎉 CONSTRUCTOR COMPLETED SUCCESSFULLY');
            Azure_Logger::info('TEC Integration: Initialization completed successfully', 'TEC');
            
        } catch (Exception $e) {
            error_log('TEC Integration: 💥 EXCEPTION in constructor: ' . $e->getMessage());
            error_log('TEC Integration: Exception trace: ' . $e->getTraceAsString());
            if (class_exists('Azure_Logger')) {
                Azure_Logger::fatal('TEC Integration: Constructor Exception: ' . $e->getMessage(), 'TEC');
            }
        } catch (Error $e) {
            error_log('TEC Integration: 💀 FATAL ERROR in constructor: ' . $e->getMessage());
            error_log('TEC Integration: Error trace: ' . $e->getTraceAsString());
            if (class_exists('Azure_Logger')) {
                Azure_Logger::fatal('TEC Integration: Constructor Fatal Error: ' . $e->getMessage(), 'TEC');
            }
        }
    }
    
    /**
     * Check if The Events Calendar plugin is active
     */
    private function is_tec_active() {
        error_log('TEC Integration: 🔍 Checking if TEC plugin is active...');
        $is_active = class_exists('Tribe__Events__Main');
        if ($is_active) {
            error_log('TEC Integration: ✅ TEC plugin is active (Tribe__Events__Main class found)');
        } else {
            error_log('TEC Integration: ⚠️ TEC plugin is NOT active (Tribe__Events__Main class not found)');
        }
        return $is_active;
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        error_log('TEC Integration: 🔧 INIT_COMPONENTS STARTED');
        
        try {
            error_log('TEC Integration: init_components Step 1 - Loading settings');
            $this->settings = Azure_Settings::get_all_settings();
            error_log('TEC Integration: ✅ init_components Step 1 - Settings loaded successfully');
            
            error_log('TEC Integration: init_components Step 2 - Checking for Azure_TEC_Sync_Engine class');
            if (class_exists('Azure_TEC_Sync_Engine')) {
                error_log('TEC Integration: init_components Step 2a - Azure_TEC_Sync_Engine found, creating instance');
                $this->sync_engine = new Azure_TEC_Sync_Engine();
                error_log('TEC Integration: ✅ init_components Step 2a - Sync engine created successfully');
                Azure_Logger::debug('TEC Integration: Sync engine initialized', 'TEC');
            } else {
                error_log('TEC Integration: ℹ️ init_components Step 2 - Azure_TEC_Sync_Engine class not found (expected - not loaded yet)');
            }
            
            error_log('TEC Integration: init_components Step 3 - Checking for Azure_TEC_Data_Mapper class');
            if (class_exists('Azure_TEC_Data_Mapper')) {
                error_log('TEC Integration: init_components Step 3a - Azure_TEC_Data_Mapper found, creating instance');
                $this->data_mapper = new Azure_TEC_Data_Mapper();
                error_log('TEC Integration: ✅ init_components Step 3a - Data mapper created successfully');
                Azure_Logger::debug('TEC Integration: Data mapper initialized', 'TEC');
            } else {
                error_log('TEC Integration: ℹ️ init_components Step 3 - Azure_TEC_Data_Mapper class not found (expected - not loaded yet)');
            }
            
            error_log('TEC Integration: ✅ INIT_COMPONENTS COMPLETED SUCCESSFULLY');
            
        } catch (Exception $e) {
            error_log('TEC Integration: 💥 EXCEPTION in init_components: ' . $e->getMessage());
            Azure_Logger::fatal('TEC Integration: init_components Exception: ' . $e->getMessage(), 'TEC');
            throw $e;
        } catch (Error $e) {
            error_log('TEC Integration: 💀 FATAL ERROR in init_components: ' . $e->getMessage());
            Azure_Logger::fatal('TEC Integration: init_components Fatal Error: ' . $e->getMessage(), 'TEC');
            throw $e;
        }
    }
    
    /**
     * Register WordPress hooks for TEC events
     */
    private function register_hooks() {
        error_log('TEC Integration: 🔗 REGISTER_HOOKS STARTED');
        
        try {
            error_log('TEC Integration: register_hooks Step 1 - Adding TEC event lifecycle hooks');
            add_action('save_post_tribe_events', array($this, 'sync_tec_event_to_outlook'), 20, 2);
            add_action('before_delete_post', array($this, 'delete_outlook_event_from_tec'));
            add_action('tribe_events_update_meta', array($this, 'handle_tec_meta_update'), 10, 3);
            error_log('TEC Integration: ✅ register_hooks Step 1 - TEC lifecycle hooks added');
            
            error_log('TEC Integration: register_hooks Step 2 - Adding status change hooks');
            add_action('transition_post_status', array($this, 'handle_post_status_change'), 10, 3);
            error_log('TEC Integration: ✅ register_hooks Step 2 - Status change hooks added');
            
            error_log('TEC Integration: register_hooks Step 3 - Adding scheduled sync hooks');
            add_action('azure_tec_sync_from_outlook', array($this, 'scheduled_sync_from_outlook'));
            error_log('TEC Integration: ✅ register_hooks Step 3 - Scheduled sync hooks added');
            
            error_log('TEC Integration: register_hooks Step 4 - Adding admin hooks');
            add_action('add_meta_boxes', array($this, 'add_sync_metabox'));
            add_filter('manage_tribe_events_posts_columns', array($this, 'add_sync_status_column'));
            add_action('manage_tribe_events_posts_custom_column', array($this, 'display_sync_status_column'), 10, 2);
            error_log('TEC Integration: ✅ register_hooks Step 4 - Admin hooks added');
            
            error_log('TEC Integration: ✅ REGISTER_HOOKS COMPLETED SUCCESSFULLY');
            Azure_Logger::debug('TEC Integration: WordPress hooks registered', 'TEC');
            
        } catch (Exception $e) {
            error_log('TEC Integration: 💥 EXCEPTION in register_hooks: ' . $e->getMessage());
            Azure_Logger::fatal('TEC Integration: register_hooks Exception: ' . $e->getMessage(), 'TEC');
            throw $e;
        } catch (Error $e) {
            error_log('TEC Integration: 💀 FATAL ERROR in register_hooks: ' . $e->getMessage());
            Azure_Logger::fatal('TEC Integration: register_hooks Fatal Error: ' . $e->getMessage(), 'TEC');
            throw $e;
        }
    }
    
    /**
     * Initialize admin interface
     */
    private function init_admin() {
        error_log('TEC Integration: 🎛️ INIT_ADMIN STARTED');
        
        try {
            error_log('TEC Integration: init_admin Step 1 - Adding admin menu');
            add_action('admin_menu', array($this, 'add_admin_menu'), 20);
            error_log('TEC Integration: ✅ init_admin Step 1 - Admin menu action added');
            
            error_log('TEC Integration: init_admin Step 2 - Adding admin scripts enqueue action');
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            error_log('TEC Integration: ✅ init_admin Step 2 - Admin scripts enqueue action added');
            
            error_log('TEC Integration: init_admin Step 3 - Adding AJAX handlers');
            add_action('wp_ajax_azure_tec_manual_sync', array($this, 'ajax_manual_sync'));
            add_action('wp_ajax_azure_tec_bulk_sync', array($this, 'ajax_bulk_sync'));
            add_action('wp_ajax_azure_tec_break_sync', array($this, 'ajax_break_sync'));
            add_action('wp_ajax_azure_tec_get_sync_status', array($this, 'ajax_get_sync_status'));
            error_log('TEC Integration: ✅ init_admin Step 3 - AJAX handlers added');
            
            error_log('TEC Integration: ✅ INIT_ADMIN COMPLETED SUCCESSFULLY');
            
        } catch (Exception $e) {
            error_log('TEC Integration: 💥 EXCEPTION in init_admin: ' . $e->getMessage());
            Azure_Logger::fatal('TEC Integration: init_admin Exception: ' . $e->getMessage(), 'TEC');
            throw $e;
        } catch (Error $e) {
            error_log('TEC Integration: 💀 FATAL ERROR in init_admin: ' . $e->getMessage());
            Azure_Logger::fatal('TEC Integration: init_admin Fatal Error: ' . $e->getMessage(), 'TEC');
            throw $e;
        }
    }
    
    /**
     * Sync TEC event to Outlook
     */
    public function sync_tec_event_to_outlook($post_id, $post) {
        // Skip if not a published event or if sync is disabled
        if ($post->post_status !== 'publish' || !$this->is_sync_enabled()) {
            return;
        }
        
        // Skip if this is an auto-save or revision
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        Azure_Logger::info("TEC Integration: Starting sync to Outlook for event ID: {$post_id}", 'TEC');
        
        try {
            if ($this->sync_engine) {
                $result = $this->sync_engine->sync_tec_to_outlook($post_id);
                
                if ($result) {
                    Azure_Logger::info("TEC Integration: Successfully synced event {$post_id} to Outlook", 'TEC');
                    $this->update_sync_metadata($post_id, 'synced', 'Event synced to Outlook successfully');
                } else {
                    Azure_Logger::warning("TEC Integration: Failed to sync event {$post_id} to Outlook", 'TEC');
                    $this->update_sync_metadata($post_id, 'error', 'Failed to sync event to Outlook');
                }
            }
        } catch (Exception $e) {
            Azure_Logger::error("TEC Integration: Exception syncing event {$post_id}: " . $e->getMessage(), 'TEC');
            $this->update_sync_metadata($post_id, 'error', 'Exception: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete Outlook event when TEC event is deleted
     */
    public function delete_outlook_event_from_tec($post_id) {
        if (get_post_type($post_id) !== 'tribe_events') {
            return;
        }
        
        $outlook_event_id = get_post_meta($post_id, '_outlook_event_id', true);
        
        if (!empty($outlook_event_id) && $this->sync_engine) {
            Azure_Logger::info("TEC Integration: Deleting Outlook event for TEC event {$post_id}", 'TEC');
            
            try {
                $result = $this->sync_engine->delete_outlook_event($outlook_event_id);
                
                if ($result) {
                    Azure_Logger::info("TEC Integration: Successfully deleted Outlook event {$outlook_event_id}", 'TEC');
                } else {
                    Azure_Logger::warning("TEC Integration: Failed to delete Outlook event {$outlook_event_id}", 'TEC');
                }
            } catch (Exception $e) {
                Azure_Logger::error("TEC Integration: Exception deleting Outlook event: " . $e->getMessage(), 'TEC');
            }
        }
    }
    
    /**
     * Handle TEC meta update
     */
    public function handle_tec_meta_update($event_id, $data, $event) {
        // Check if this is a significant change that requires sync
        $significant_fields = array(
            '_EventStartDate',
            '_EventEndDate',
            '_EventAllDay',
            'post_title',
            'post_content'
        );
        
        $needs_sync = false;
        foreach ($significant_fields as $field) {
            if (isset($data[$field])) {
                $needs_sync = true;
                break;
            }
        }
        
        if ($needs_sync && $this->is_sync_enabled()) {
            Azure_Logger::debug("TEC Integration: Meta update detected for event {$event_id}, triggering sync", 'TEC');
            
            // Schedule sync for next request to avoid conflicts
            wp_schedule_single_event(time() + 1, 'azure_tec_delayed_sync', array($event_id));
        }
    }
    
    /**
     * Handle post status changes
     */
    public function handle_post_status_change($new_status, $old_status, $post) {
        if ($post->post_type !== 'tribe_events') {
            return;
        }
        
        Azure_Logger::debug("TEC Integration: Status change for event {$post->ID}: {$old_status} -> {$new_status}", 'TEC');
        
        // Handle publish/unpublish events
        if ($old_status === 'publish' && $new_status !== 'publish') {
            // Event was unpublished, delete from Outlook
            $this->delete_outlook_event_from_tec($post->ID);
        } elseif ($old_status !== 'publish' && $new_status === 'publish') {
            // Event was published, sync to Outlook
            $this->sync_tec_event_to_outlook($post->ID, $post);
        }
    }
    
    /**
     * Scheduled sync from Outlook
     */
    public function scheduled_sync_from_outlook() {
        if (!$this->is_sync_enabled() || !$this->sync_engine) {
            return;
        }
        
        Azure_Logger::info('TEC Integration: Starting scheduled sync from Outlook', 'TEC');
        
        try {
            $result = $this->sync_engine->sync_outlook_to_tec();
            
            if ($result) {
                Azure_Logger::info('TEC Integration: Scheduled sync from Outlook completed successfully', 'TEC');
            } else {
                Azure_Logger::warning('TEC Integration: Scheduled sync from Outlook failed', 'TEC');
            }
        } catch (Exception $e) {
            Azure_Logger::error('TEC Integration: Exception during scheduled sync: ' . $e->getMessage(), 'TEC');
        }
    }
    
    /**
     * Add sync status metabox to TEC event edit screen
     */
    public function add_sync_metabox() {
        add_meta_box(
            'azure_tec_sync_status',
            'Outlook Sync Status',
            array($this, 'render_sync_metabox'),
            'tribe_events',
            'side',
            'high'
        );
    }
    
    /**
     * Render sync status metabox
     */
    public function render_sync_metabox($post) {
        $outlook_event_id = get_post_meta($post->ID, '_outlook_event_id', true);
        $sync_status = get_post_meta($post->ID, '_outlook_sync_status', true);
        $last_sync = get_post_meta($post->ID, '_outlook_last_sync', true);
        $sync_message = get_post_meta($post->ID, '_outlook_sync_message', true);
        
        echo '<div id="azure-tec-sync-metabox">';
        echo '<p><strong>Sync Status:</strong> ';
        
        switch ($sync_status) {
            case 'synced':
                echo '<span style="color: green;">✓ Synced</span>';
                break;
            case 'pending':
                echo '<span style="color: orange;">⏳ Pending</span>';
                break;
            case 'error':
                echo '<span style="color: red;">✗ Error</span>';
                break;
            default:
                echo '<span style="color: gray;">Not synced</span>';
                break;
        }
        echo '</p>';
        
        if ($outlook_event_id) {
            echo '<p><strong>Outlook Event ID:</strong> ' . esc_html($outlook_event_id) . '</p>';
        }
        
        if ($last_sync) {
            echo '<p><strong>Last Sync:</strong> ' . esc_html(date('Y-m-d H:i:s', strtotime($last_sync))) . '</p>';
        }
        
        if ($sync_message) {
            echo '<p><strong>Message:</strong> ' . esc_html($sync_message) . '</p>';
        }
        
        // Action buttons
        echo '<div class="azure-tec-sync-actions">';
        echo '<button type="button" class="button" onclick="azureTecManualSync(' . $post->ID . ')">Force Sync to Outlook</button>';
        
        if ($outlook_event_id) {
            echo '<button type="button" class="button" onclick="azureTecBreakSync(' . $post->ID . ')">Break Sync</button>';
        }
        echo '</div>';
        echo '</div>';
        
        wp_nonce_field('azure_tec_sync_action', 'azure_tec_sync_nonce');
    }
    
    /**
     * Add sync status column to TEC events list
     */
    public function add_sync_status_column($columns) {
        $columns['outlook_sync'] = 'Outlook Sync';
        return $columns;
    }
    
    /**
     * Display sync status column content
     */
    public function display_sync_status_column($column, $post_id) {
        if ($column === 'outlook_sync') {
            $sync_status = get_post_meta($post_id, '_outlook_sync_status', true);
            $outlook_event_id = get_post_meta($post_id, '_outlook_event_id', true);
            
            switch ($sync_status) {
                case 'synced':
                    echo '<span style="color: green;" title="Synced to Outlook">✓</span>';
                    break;
                case 'pending':
                    echo '<span style="color: orange;" title="Sync pending">⏳</span>';
                    break;
                case 'error':
                    echo '<span style="color: red;" title="Sync error">✗</span>';
                    break;
                default:
                    echo '<span style="color: gray;" title="Not synced">—</span>';
                    break;
            }
            
            if ($outlook_event_id) {
                echo '<br><small>' . substr($outlook_event_id, 0, 8) . '...</small>';
            }
        }
    }
    
    /**
     * Add admin menu for TEC integration
     */
    public function add_admin_menu() {
        add_submenu_page(
            'azure-plugin',
            'TEC Integration',
            'TEC Integration',
            'manage_options',
            'azure-tec-integration',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Render TEC integration admin page
     */
    public function render_admin_page() {
        include AZURE_PLUGIN_PATH . 'admin/tec-integration-page.php';
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on TEC integration pages
        if (strpos($hook, 'azure-tec') === false && get_post_type() !== 'tribe_events') {
            return;
        }
        
        wp_enqueue_script(
            'azure-tec-admin',
            AZURE_PLUGIN_URL . 'js/tec-admin.js',
            array('jquery'),
            AZURE_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script('azure-tec-admin', 'azureTecAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('azure_tec_action')
        ));
    }
    
    /**
     * Update sync metadata for an event
     */
    private function update_sync_metadata($post_id, $status, $message = '', $outlook_event_id = null) {
        update_post_meta($post_id, '_outlook_sync_status', $status);
        update_post_meta($post_id, '_outlook_last_sync', current_time('mysql'));
        
        if ($message) {
            update_post_meta($post_id, '_outlook_sync_message', $message);
        }
        
        if ($outlook_event_id) {
            update_post_meta($post_id, '_outlook_event_id', $outlook_event_id);
        }
    }
    
    /**
     * Check if sync is enabled
     */
    private function is_sync_enabled() {
        $settings = Azure_Settings::get_all_settings();
        return !empty($settings['enable_tec_integration']) && !empty($settings['enable_calendar']);
    }
    
    /**
     * Show admin notice if TEC is not active
     */
    public function tec_dependency_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Azure Plugin TEC Integration:</strong> The Events Calendar plugin is required but not active. ';
        echo 'Please install and activate The Events Calendar to use this feature.';
        echo '</p></div>';
    }
    
    /**
     * AJAX handler for manual sync
     */
    public function ajax_manual_sync() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'azure_tec_action')) {
            wp_die('Unauthorized access');
        }
        
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id || get_post_type($post_id) !== 'tribe_events') {
            wp_send_json_error('Invalid event ID');
        }
        
        $post = get_post($post_id);
        $this->sync_tec_event_to_outlook($post_id, $post);
        
        wp_send_json_success('Sync initiated');
    }
    
    /**
     * AJAX handler for bulk sync
     */
    public function ajax_bulk_sync() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'azure_tec_action')) {
            wp_die('Unauthorized access');
        }
        
        $action = sanitize_text_field($_POST['action_type']);
        
        if ($this->sync_engine) {
            if ($action === 'sync_to_outlook') {
                $result = $this->sync_engine->bulk_sync_tec_to_outlook();
            } elseif ($action === 'sync_from_outlook') {
                $result = $this->sync_engine->sync_outlook_to_tec();
            } else {
                wp_send_json_error('Invalid action type');
            }
            
            if ($result) {
                wp_send_json_success('Bulk sync completed');
            } else {
                wp_send_json_error('Bulk sync failed');
            }
        } else {
            wp_send_json_error('Sync engine not available');
        }
    }
    
    /**
     * AJAX handler for breaking sync relationship
     */
    public function ajax_break_sync() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'azure_tec_action')) {
            wp_die('Unauthorized access');
        }
        
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id || get_post_type($post_id) !== 'tribe_events') {
            wp_send_json_error('Invalid event ID');
        }
        
        // Remove sync metadata
        delete_post_meta($post_id, '_outlook_event_id');
        delete_post_meta($post_id, '_outlook_sync_status');
        delete_post_meta($post_id, '_outlook_last_sync');
        delete_post_meta($post_id, '_outlook_sync_message');
        
        wp_send_json_success('Sync relationship broken');
    }
    
    /**
     * AJAX handler for getting sync status
     */
    public function ajax_get_sync_status() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'azure_tec_action')) {
            wp_die('Unauthorized access');
        }
        
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id || get_post_type($post_id) !== 'tribe_events') {
            wp_send_json_error('Invalid event ID');
        }
        
        $status = array(
            'outlook_event_id' => get_post_meta($post_id, '_outlook_event_id', true),
            'sync_status' => get_post_meta($post_id, '_outlook_sync_status', true),
            'last_sync' => get_post_meta($post_id, '_outlook_last_sync', true),
            'sync_message' => get_post_meta($post_id, '_outlook_sync_message', true)
        );
        
        wp_send_json_success($status);
    }
    
    /**
     * Migrate existing TEC events to add sync metadata
     */
    public static function migrate_existing_events() {
        global $wpdb;
        
        Azure_Logger::info('TEC Integration: Starting migration of existing TEC events', 'TEC');
        
        try {
            // Get all published TEC events without sync metadata
            $events = $wpdb->get_results(
                "SELECT p.ID FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_outlook_sync_status'
                 WHERE p.post_type = 'tribe_events' 
                 AND p.post_status = 'publish'
                 AND pm.meta_id IS NULL
                 LIMIT 100"
            );
            
            $migrated_count = 0;
            
            foreach ($events as $event) {
                // Add default sync metadata
                update_post_meta($event->ID, '_outlook_sync_status', 'not_synced');
                update_post_meta($event->ID, '_sync_conflict_resolution', 'outlook_wins');
                
                $migrated_count++;
            }
            
            Azure_Logger::info("TEC Integration: Migration completed. Processed {$migrated_count} events", 'TEC');
            
            return $migrated_count;
            
        } catch (Exception $e) {
            Azure_Logger::error('TEC Integration: Migration failed: ' . $e->getMessage(), 'TEC');
            return false;
        }
    }
    
    /**
     * Clean up orphaned sync metadata for deleted events
     */
    public static function cleanup_orphaned_sync_metadata() {
        global $wpdb;
        
        Azure_Logger::info('TEC Integration: Starting cleanup of orphaned sync metadata', 'TEC');
        
        try {
            // Clean up metadata for events that no longer exist
            $cleaned_meta = $wpdb->query(
                "DELETE pm FROM {$wpdb->postmeta} pm
                 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key LIKE '_outlook_%'
                 AND (p.ID IS NULL OR p.post_type != 'tribe_events')"
            );
            
            // Clean up sync history for deleted events
            $history_table = Azure_Database::get_table_name('tec_sync_history');
            if ($history_table) {
                $cleaned_history = $wpdb->query(
                    "DELETE h FROM {$history_table} h
                     LEFT JOIN {$wpdb->posts} p ON h.tec_event_id = p.ID
                     WHERE p.ID IS NULL OR p.post_type != 'tribe_events'"
                );
            }
            
            // Clean up sync conflicts for deleted events
            $conflicts_table = Azure_Database::get_table_name('tec_sync_conflicts');
            if ($conflicts_table) {
                $cleaned_conflicts = $wpdb->query(
                    "DELETE c FROM {$conflicts_table} c
                     LEFT JOIN {$wpdb->posts} p ON c.tec_event_id = p.ID
                     WHERE p.ID IS NULL OR p.post_type != 'tribe_events'"
                );
            }
            
            // Clean up sync queue for deleted events
            $queue_table = Azure_Database::get_table_name('tec_sync_queue');
            if ($queue_table) {
                $cleaned_queue = $wpdb->query(
                    "DELETE q FROM {$queue_table} q
                     LEFT JOIN {$wpdb->posts} p ON q.tec_event_id = p.ID
                     WHERE p.ID IS NULL OR p.post_type != 'tribe_events'"
                );
            }
            
            Azure_Logger::info("TEC Integration: Cleanup completed. Cleaned metadata: {$cleaned_meta}, history: " . ($cleaned_history ?? 0) . ", conflicts: " . ($cleaned_conflicts ?? 0) . ", queue: " . ($cleaned_queue ?? 0), 'TEC');
            
            return true;
            
        } catch (Exception $e) {
            Azure_Logger::error('TEC Integration: Cleanup failed: ' . $e->getMessage(), 'TEC');
            return false;
        }
    }
    
    /**
     * Get sync history for an event
     */
    public function get_sync_history($tec_event_id, $limit = 10) {
        global $wpdb;
        
        $history_table = Azure_Database::get_table_name('tec_sync_history');
        if (!$history_table) {
            return array();
        }
        
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$history_table} 
             WHERE tec_event_id = %d 
             ORDER BY sync_timestamp DESC 
             LIMIT %d",
            $tec_event_id,
            $limit
        ));
        
        return $history ? $history : array();
    }
    
    /**
     * Log sync operation
     */
    public function log_sync_operation($tec_event_id, $outlook_event_id, $sync_direction, $sync_action, $sync_status, $sync_message = '', $data_before = null, $data_after = null, $conflict_resolution = null) {
        global $wpdb;
        
        $history_table = Azure_Database::get_table_name('tec_sync_history');
        if (!$history_table) {
            return false;
        }
        
        $data = array(
            'tec_event_id' => $tec_event_id,
            'outlook_event_id' => $outlook_event_id,
            'sync_direction' => $sync_direction,
            'sync_action' => $sync_action,
            'sync_status' => $sync_status,
            'sync_message' => $sync_message,
            'data_before' => $data_before ? wp_json_encode($data_before) : null,
            'data_after' => $data_after ? wp_json_encode($data_after) : null,
            'conflict_resolution' => $conflict_resolution
        );
        
        $formats = array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');
        
        return $wpdb->insert($history_table, $data, $formats);
    }
    
    /**
     * Create sync conflict record
     */
    public function create_sync_conflict($tec_event_id, $outlook_event_id, $conflict_type, $tec_data, $outlook_data) {
        global $wpdb;
        
        $conflicts_table = Azure_Database::get_table_name('tec_sync_conflicts');
        if (!$conflicts_table) {
            return false;
        }
        
        $data = array(
            'tec_event_id' => $tec_event_id,
            'outlook_event_id' => $outlook_event_id,
            'conflict_type' => $conflict_type,
            'tec_data' => wp_json_encode($tec_data),
            'outlook_data' => wp_json_encode($outlook_data),
            'resolution_status' => 'pending'
        );
        
        $formats = array('%d', '%s', '%s', '%s', '%s', '%s');
        
        return $wpdb->insert($conflicts_table, $data, $formats);
    }
    
    /**
     * Add event to sync queue
     */
    public function add_to_sync_queue($tec_event_id, $sync_direction, $sync_action, $priority = 5) {
        global $wpdb;
        
        $queue_table = Azure_Database::get_table_name('tec_sync_queue');
        if (!$queue_table) {
            return false;
        }
        
        // Check if already in queue
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$queue_table} 
             WHERE tec_event_id = %d 
             AND sync_direction = %s 
             AND sync_action = %s 
             AND status = 'pending'",
            $tec_event_id,
            $sync_direction,
            $sync_action
        ));
        
        if ($existing) {
            return $existing; // Already queued
        }
        
        $data = array(
            'tec_event_id' => $tec_event_id,
            'sync_direction' => $sync_direction,
            'sync_action' => $sync_action,
            'priority' => $priority,
            'status' => 'pending'
        );
        
        $formats = array('%d', '%s', '%s', '%d', '%s');
        
        $result = $wpdb->insert($queue_table, $data, $formats);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Initialize sync metadata for existing TEC events (Task 1.7)
     * One-time setup for sites with pre-existing events
     */
    public function initialize_existing_events_for_sync($force = false) {
        Azure_Logger::info('TEC Integration: Starting initialization of existing TEC events for sync', 'TEC');
        
        $meta_query = array(
            array(
                'key' => '_outlook_sync_status',
                'compare' => 'NOT EXISTS'
            )
        );
        
        // If force is true, reinitialize all events
        if ($force) {
            $meta_query = array();
            Azure_Logger::info('TEC Integration: Force initialization - processing all TEC events', 'TEC');
        }
        
        $existing_events = get_posts(array(
            'post_type' => 'tribe_events',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'private', 'draft'),
            'meta_query' => $meta_query
        ));
        
        $initialized_count = 0;
        $skipped_count = 0;
        
        foreach ($existing_events as $event) {
            // Check if event already has sync metadata (unless force)
            if (!$force && get_post_meta($event->ID, '_outlook_sync_status', true)) {
                $skipped_count++;
                continue;
            }
            
            // Initialize sync metadata
            update_post_meta($event->ID, '_outlook_sync_status', 'pending');
            update_post_meta($event->ID, '_outlook_last_sync', '');
            update_post_meta($event->ID, '_sync_direction', 'not_synced');
            
            // Don't set _outlook_event_id - will be added when first synced to Outlook
            
            $initialized_count++;
            
            Azure_Logger::debug("TEC Integration: Initialized sync metadata for event {$event->ID}: {$event->post_title}", 'TEC');
        }
        
        Azure_Logger::info("TEC Integration: Initialization complete - {$initialized_count} events initialized, {$skipped_count} skipped", 'TEC');
        
        return array(
            'initialized' => $initialized_count,
            'skipped' => $skipped_count,
            'total_processed' => count($existing_events)
        );
    }
    
    /**
     * Clean up sync metadata (Task 1.8)
     * Remove our sync metadata from TEC events
     */
    public function cleanup_sync_metadata($delete_outlook_events = false) {
        Azure_Logger::info('TEC Integration: Starting cleanup of sync metadata', 'TEC');
        
        global $wpdb;
        
        // Get all TEC events with our sync metadata
        $events_with_sync_data = get_posts(array(
            'post_type' => 'tribe_events',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => '_outlook_sync_status',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        $cleaned_count = 0;
        $outlook_deleted_count = 0;
        
        foreach ($events_with_sync_data as $event) {
            // Optionally delete from Outlook first
            if ($delete_outlook_events) {
                $outlook_event_id = get_post_meta($event->ID, '_outlook_event_id', true);
                if ($outlook_event_id && $this->sync_engine) {
                    try {
                        $result = $this->sync_engine->delete_outlook_event($outlook_event_id);
                        if ($result) {
                            $outlook_deleted_count++;
                            Azure_Logger::debug("TEC Integration: Deleted Outlook event {$outlook_event_id} for TEC event {$event->ID}", 'TEC');
                        }
                    } catch (Exception $e) {
                        Azure_Logger::warning("TEC Integration: Failed to delete Outlook event {$outlook_event_id}: " . $e->getMessage(), 'TEC');
                    }
                }
            }
            
            // Remove all our sync metadata
            delete_post_meta($event->ID, '_outlook_event_id');
            delete_post_meta($event->ID, '_outlook_sync_status');
            delete_post_meta($event->ID, '_outlook_last_sync');
            delete_post_meta($event->ID, '_sync_conflict_resolution');
            delete_post_meta($event->ID, '_sync_direction');
            delete_post_meta($event->ID, '_outlook_sync_message');
            
            $cleaned_count++;
            
            Azure_Logger::debug("TEC Integration: Cleaned sync metadata for event {$event->ID}: {$event->post_title}", 'TEC');
        }
        
        // Clean up any orphaned sync metadata (shouldn't happen, but just in case)
        $orphaned_metadata = $wpdb->query("
            DELETE pm FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key IN (
                '_outlook_event_id', 
                '_outlook_sync_status', 
                '_outlook_last_sync', 
                '_sync_conflict_resolution', 
                '_sync_direction',
                '_outlook_sync_message'
            ) AND (p.ID IS NULL OR p.post_type != 'tribe_events')
        ");
        
        Azure_Logger::info("TEC Integration: Cleanup complete - {$cleaned_count} events cleaned, {$outlook_deleted_count} Outlook events deleted, {$orphaned_metadata} orphaned metadata removed", 'TEC');
        
        return array(
            'cleaned_events' => $cleaned_count,
            'outlook_events_deleted' => $outlook_deleted_count,
            'orphaned_metadata_removed' => $orphaned_metadata
        );
    }
}