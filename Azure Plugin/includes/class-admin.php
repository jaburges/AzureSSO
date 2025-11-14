<?php
/**
 * Azure Plugin Admin Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new Azure_Admin();
        }
        return self::$instance;
    }
    
    public function __construct() {
        try {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_azure_test_credentials', array($this, 'ajax_test_credentials'));
        add_action('wp_ajax_azure_toggle_module', array($this, 'ajax_toggle_module'));
        add_action('wp_ajax_azure_calendar_authorize', array($this, 'ajax_calendar_authorize'));
            
            // Calendar Embed AJAX handlers
            add_action('wp_ajax_azure_save_calendar_embed_email', array($this, 'ajax_save_calendar_embed_email'));
            add_action('wp_ajax_azure_calendar_embed_authorize', array($this, 'ajax_calendar_embed_authorize'));
            add_action('wp_ajax_azure_calendar_embed_check_auth', array($this, 'ajax_calendar_embed_check_auth'));
            add_action('wp_ajax_azure_calendar_embed_revoke', array($this, 'ajax_calendar_embed_revoke'));
            add_action('wp_ajax_azure_toggle_calendar_embed', array($this, 'ajax_toggle_calendar_embed'));
            add_action('wp_ajax_azure_save_calendar_timezone', array($this, 'ajax_save_calendar_timezone'));
            add_action('wp_ajax_azure_calendar_get_events', array($this, 'ajax_calendar_get_events'));
            
            // TEC Calendar AJAX handlers
            add_action('wp_ajax_azure_save_tec_calendar_email', array($this, 'ajax_save_tec_calendar_email'));
            add_action('wp_ajax_azure_tec_calendar_authorize', array($this, 'ajax_tec_calendar_authorize'));
            add_action('wp_ajax_azure_tec_calendar_check_auth', array($this, 'ajax_tec_calendar_check_auth'));
            
            // OneDrive Media AJAX handlers
            add_action('wp_ajax_azure_onedrive_authorize', array($this, 'ajax_onedrive_authorize'));
            add_action('wp_ajax_onedrive_media_test_connection', array($this, 'ajax_onedrive_test_connection'));
            add_action('wp_ajax_onedrive_media_sync_from_onedrive', array($this, 'ajax_onedrive_sync_from_onedrive'));
            add_action('wp_ajax_onedrive_media_browse_folders', array($this, 'ajax_onedrive_browse_folders'));
            add_action('wp_ajax_onedrive_media_create_year_folders', array($this, 'ajax_onedrive_create_year_folders'));
            add_action('wp_ajax_onedrive_media_resolve_sharepoint_site', array($this, 'ajax_resolve_sharepoint_site'));
            add_action('wp_ajax_onedrive_media_list_sharepoint_drives', array($this, 'ajax_list_sharepoint_drives'));
            
        add_action('wp_ajax_azure_test_sso_connection', array($this, 'ajax_test_sso_connection'));
        add_action('wp_ajax_azure_test_storage_connection', array($this, 'ajax_test_storage_connection'));
        add_action('wp_ajax_azure_test_calendar_connection', array($this, 'ajax_test_calendar_connection'));
        add_action('wp_ajax_azure_delete_backup', array($this, 'ajax_delete_backup'));
        add_action('wp_ajax_azure_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_azure_refresh_logs', array($this, 'ajax_refresh_logs'));
        add_action('wp_ajax_azure_download_logs', array($this, 'ajax_download_logs'));
        add_action('wp_ajax_azure_export_settings', array($this, 'ajax_export_settings'));
        add_action('wp_ajax_azure_import_settings', array($this, 'ajax_import_settings'));
        add_action('wp_ajax_azure_get_recent_activity', array($this, 'ajax_get_recent_activity'));
        
        // Debug: Log that admin AJAX handlers are registered
        if (class_exists('Azure_Logger')) {
                try {
            Azure_Logger::debug('Admin: All AJAX handlers registered successfully', 'Admin');
                } catch (Exception $e) {
                    // Silently fail - logging is not critical
                    error_log('Azure Admin: Failed to log debug message - ' . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            // Log constructor error
            error_log('Azure Admin: Constructor failed - ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function admin_menu() {
        $log_file = AZURE_PLUGIN_PATH . 'logs.md';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "**{$timestamp}** ⏳ **[ADMIN HOOK]** admin_menu() callback started  \n", FILE_APPEND | LOCK_EX);
        
        try {
            // Main menu
        add_menu_page(
            'Azure Plugin',
            'Azure Plugin',
            'manage_options',
            'azure-plugin',
            array($this, 'admin_page'),
            'dashicons-cloud',
            30
        );
        
        // Submenus
        add_submenu_page(
            'azure-plugin',
            'Azure Plugin - SSO',
            'SSO',
            'manage_options',
            'azure-plugin-sso',
            array($this, 'admin_page_sso')
        );
        
        add_submenu_page(
            'azure-plugin',
            'Azure Plugin - Backup',
            'Backup',
            'manage_options',
            'azure-plugin-backup',
            array($this, 'admin_page_backup')
        );
        
        add_submenu_page(
            'azure-plugin',
            'Azure Plugin - Calendar Embed',
            'Calendar Embed',
            'manage_options',
            'azure-plugin-calendar',
            array($this, 'admin_page_calendar')
        );
        
        add_submenu_page(
            'azure-plugin',
            'Azure Plugin - Calendar Sync',
            'Calendar Sync',
            'manage_options',
            'azure-plugin-tec',
            array($this, 'admin_page_tec')
        );
        
        add_submenu_page(
            'azure-plugin',
            'Azure Plugin - Email',
            'Email',
            'manage_options',
            'azure-plugin-email',
            array($this, 'admin_page_email')
        );
        
        // Email Logs submenu under main Azure Plugin
        add_submenu_page(
            'azure-plugin',
            'Azure Plugin - Email Logs',
            'Email Logs',
            'manage_options',
            'azure-plugin-email-logs',
            array($this, 'admin_page_email_logs')
        );
        
        add_submenu_page(
            'azure-plugin',
            'Azure Plugin - PTA Roles',
            'PTA Roles',
            'manage_options',
            'azure-plugin-pta',
            array($this, 'admin_page_pta')
        );
        
        add_submenu_page(
            'azure-plugin-pta',
            'Azure Plugin - PTA Groups',
            'O365 Groups',
            'manage_options',
            'azure-plugin-pta-groups',
            array($this, 'admin_page_pta_groups')
        );
        
        add_submenu_page(
            'azure-plugin',
            'Azure Plugin - OneDrive Media',
            'OneDrive Media',
            'manage_options',
            'azure-plugin-onedrive-media',
            array($this, 'admin_page_onedrive_media')
        );
        
        add_submenu_page(
            'azure-plugin',
            'Azure Plugin - Logs',
            'System Logs',
            'manage_options',
            'azure-plugin-logs',
            array($this, 'admin_page_logs')
        );
        
        file_put_contents($log_file, "**{$timestamp}** ✅ **[ADMIN HOOK]** admin_menu() callback completed successfully  \n", FILE_APPEND | LOCK_EX);
        } catch (Error $e) {
            file_put_contents($log_file, "**{$timestamp}** 💀 **[ADMIN HOOK FATAL]** admin_menu() fatal error: " . $e->getMessage() . "  \n", FILE_APPEND | LOCK_EX);
            file_put_contents($log_file, "**{$timestamp}** 📍 **[ADMIN HOOK LOCATION]** " . $e->getFile() . " line " . $e->getLine() . "  \n", FILE_APPEND | LOCK_EX);
            throw $e;
        } catch (Exception $e) {
            file_put_contents($log_file, "**{$timestamp}** ❌ **[ADMIN HOOK ERROR]** admin_menu() error: " . $e->getMessage() . "  \n", FILE_APPEND | LOCK_EX);
            file_put_contents($log_file, "**{$timestamp}** 📍 **[ADMIN HOOK LOCATION]** " . $e->getFile() . " line " . $e->getLine() . "  \n", FILE_APPEND | LOCK_EX);
            throw $e;
        }
    }
    
    public function admin_init() {
        $log_file = AZURE_PLUGIN_PATH . 'logs.md';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "**{$timestamp}** ⏳ **[ADMIN HOOK]** admin_init() callback started  \n", FILE_APPEND | LOCK_EX);
        
        try {
        // Register settings through our Settings class to avoid conflicts
        Azure_Settings::get_instance()->register_settings();
        
        // Handle form submissions
        if (isset($_POST['azure_plugin_submit'])) {
            $this->handle_settings_save();
        }
        
        file_put_contents($log_file, "**{$timestamp}** ✅ **[ADMIN HOOK]** admin_init() callback completed successfully  \n", FILE_APPEND | LOCK_EX);
        } catch (Error $e) {
            file_put_contents($log_file, "**{$timestamp}** 💀 **[ADMIN HOOK FATAL]** admin_init() fatal error: " . $e->getMessage() . "  \n", FILE_APPEND | LOCK_EX);
            file_put_contents($log_file, "**{$timestamp}** 📍 **[ADMIN HOOK LOCATION]** " . $e->getFile() . " line " . $e->getLine() . "  \n", FILE_APPEND | LOCK_EX);
            throw $e;
        } catch (Exception $e) {
            file_put_contents($log_file, "**{$timestamp}** ❌ **[ADMIN HOOK ERROR]** admin_init() error: " . $e->getMessage() . "  \n", FILE_APPEND | LOCK_EX);
            file_put_contents($log_file, "**{$timestamp}** 📍 **[ADMIN HOOK LOCATION]** " . $e->getFile() . " line " . $e->getLine() . "  \n", FILE_APPEND | LOCK_EX);
            throw $e;
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        // Check if we're on any Azure Plugin admin page - be very permissive
        $is_azure_page = (
            strpos($hook, 'azure-plugin') !== false ||
            strpos($hook, 'azure_plugin') !== false ||
            strpos($hook, 'azure-') !== false ||
            (isset($_GET['page']) && strpos($_GET['page'], 'azure') !== false)
        );
        
        if (!$is_azure_page) {
            return;
        }
        
        // Use timestamp for cache busting during development/debugging
        $cache_version = AZURE_PLUGIN_VERSION . '.' . time();
        
        wp_enqueue_script('jquery');
        wp_enqueue_style('azure-plugin-admin', AZURE_PLUGIN_URL . 'css/admin.css', array(), $cache_version);
        wp_enqueue_script('azure-plugin-admin', AZURE_PLUGIN_URL . 'js/admin.js', array('jquery'), $cache_version);
        
        // Load page-specific CSS files
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';
        
        switch ($current_page) {
            case 'azure-plugin-backup':
                wp_enqueue_style('azure-backup-frontend', AZURE_PLUGIN_URL . 'css/backup-frontend.css', array(), $cache_version);
                break;
            case 'azure-plugin-email':
                wp_enqueue_style('azure-email-frontend', AZURE_PLUGIN_URL . 'css/email-frontend.css', array(), $cache_version);
                break;
            case 'azure-plugin-calendar':
                wp_enqueue_style('azure-calendar-frontend', AZURE_PLUGIN_URL . 'css/calendar-frontend.css', array(), $cache_version);
                break;
            case 'azure-plugin-tec':
                // Enqueue TEC admin JavaScript
                wp_enqueue_script('azure-tec-admin', AZURE_PLUGIN_URL . 'js/tec-admin.js', array('jquery'), $cache_version, true);
                wp_localize_script('azure-tec-admin', 'azureTecAdmin', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('azure_plugin_nonce')
                ));
                break;
        }

        wp_localize_script('azure-plugin-admin', 'azure_plugin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('azure_plugin_nonce')
        ));
    }
    
    private function handle_settings_save() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'azure_plugin_settings')) {
            wp_die('Unauthorized access');
        }
        
        $current_page = $_GET['page'] ?? 'azure-plugin';
        
        // Start with current settings to preserve existing values
        $settings = Azure_Settings::get_all_settings();
        
        // Only update module enable states when saving from main page
        if ($current_page === 'azure-plugin') {
            $settings['enable_sso'] = isset($_POST['enable_sso']) ? (bool)$_POST['enable_sso'] : false;
            $settings['enable_backup'] = isset($_POST['enable_backup']) ? (bool)$_POST['enable_backup'] : false;
            $settings['enable_calendar'] = isset($_POST['enable_calendar']) ? (bool)$_POST['enable_calendar'] : false;
            $settings['enable_email'] = isset($_POST['enable_email']) ? (bool)$_POST['enable_email'] : false;
            $settings['enable_pta'] = isset($_POST['enable_pta']) ? (bool)$_POST['enable_pta'] : false;
            $settings['enable_onedrive_media'] = isset($_POST['enable_onedrive_media']) ? (bool)$_POST['enable_onedrive_media'] : false;
        }
        // For module-specific pages, preserve current module states
        
        // Common credentials (only update if on main page or if explicitly posted)
        if ($current_page === 'azure-plugin' || isset($_POST['use_common_credentials'])) {
        $settings['use_common_credentials'] = isset($_POST['use_common_credentials']);
        }
        if (isset($_POST['common_client_id'])) {
            $settings['common_client_id'] = sanitize_text_field($_POST['common_client_id']);
        }
        if (isset($_POST['common_client_secret'])) {
            $settings['common_client_secret'] = sanitize_text_field($_POST['common_client_secret']);
        }
        if (isset($_POST['common_tenant_id'])) {
            $settings['common_tenant_id'] = sanitize_text_field($_POST['common_tenant_id']);
        } else if ($current_page === 'azure-plugin') {
            // Set default tenant ID only on main page if not provided
            $settings['common_tenant_id'] = 'common';
        }
        
        // Module-specific settings based on which page we're on
        
        switch ($current_page) {
            case 'azure-plugin-sso':
                $this->save_sso_settings($settings);
                break;
            case 'azure-plugin-backup':
                $this->save_backup_settings($settings);
                break;
            case 'azure-plugin-calendar':
                $this->save_calendar_settings($settings);
                break;
            case 'azure-plugin-email':
                $this->save_email_settings($settings);
                break;
            case 'azure-plugin-onedrive-media':
                $this->save_onedrive_media_settings($settings);
                break;
        }
        
        Azure_Settings::update_settings($settings);
        Azure_Database::log_activity('admin', 'settings_saved', 'settings', $current_page);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
        });
    }
    
    private function save_sso_settings(&$settings) {
        // Handle use_common_credentials checkbox from SSO page
        $settings['use_common_credentials'] = isset($_POST['use_common_credentials']);
        
        // Only save module-specific credentials if not using common credentials
        if (!$settings['use_common_credentials']) {
            $settings['sso_client_id'] = sanitize_text_field($_POST['sso_client_id'] ?? '');
            $settings['sso_client_secret'] = sanitize_text_field($_POST['sso_client_secret'] ?? '');
            $settings['sso_tenant_id'] = sanitize_text_field($_POST['sso_tenant_id'] ?? 'common');
        }
        
        $settings['sso_require_sso'] = isset($_POST['sso_require_sso']);
        $settings['sso_auto_create_users'] = isset($_POST['sso_auto_create_users']);
        $settings['sso_default_role'] = sanitize_text_field($_POST['sso_default_role'] ?? 'subscriber');
        $settings['sso_show_on_login_page'] = isset($_POST['sso_show_on_login_page']);
        $settings['sso_login_button_text'] = sanitize_text_field($_POST['sso_login_button_text'] ?? 'Sign in with WilderPTSA Email');
        
        // Custom role settings
        $settings['sso_use_custom_role'] = isset($_POST['sso_use_custom_role']);
        $settings['sso_custom_role_name'] = sanitize_text_field($_POST['sso_custom_role_name'] ?? 'AzureAD');
        
        // Sync settings
        $settings['sso_sync_enabled'] = isset($_POST['sso_sync_enabled']);
        $settings['sso_sync_frequency'] = sanitize_text_field($_POST['sso_sync_frequency'] ?? 'daily');
        $settings['sso_preserve_local_data'] = isset($_POST['sso_preserve_local_data']);
    }
    
    private function save_backup_settings(&$settings) {
        if (!Azure_Settings::get_setting('use_common_credentials')) {
            $settings['backup_client_id'] = sanitize_text_field($_POST['backup_client_id'] ?? '');
            $settings['backup_client_secret'] = sanitize_text_field($_POST['backup_client_secret'] ?? '');
        }
        
        // Azure Storage settings - using correct field names
        $settings['backup_storage_account_name'] = sanitize_text_field($_POST['azure_plugin_settings']['backup_storage_account_name'] ?? '');
        $settings['backup_storage_account_key'] = sanitize_text_field($_POST['azure_plugin_settings']['backup_storage_account_key'] ?? '');
        $settings['backup_storage_container_name'] = sanitize_text_field($_POST['azure_plugin_settings']['backup_storage_container_name'] ?? 'wordpress-backups');
        
        // Legacy field names for backwards compatibility
        if (empty($settings['backup_storage_account_name']) && !empty($_POST['backup_storage_account'])) {
            $settings['backup_storage_account_name'] = sanitize_text_field($_POST['backup_storage_account']);
        }
        if (empty($settings['backup_storage_account_key']) && !empty($_POST['backup_storage_key'])) {
            $settings['backup_storage_account_key'] = sanitize_text_field($_POST['backup_storage_key']);
        }
        if (empty($settings['backup_storage_container_name']) && !empty($_POST['backup_container_name'])) {
            $settings['backup_storage_container_name'] = sanitize_text_field($_POST['backup_container_name']);
        }
        
        // Backup configuration settings - from azure_plugin_settings array
        $settings['backup_types'] = isset($_POST['azure_plugin_settings']['backup_types']) 
            ? array_map('sanitize_text_field', $_POST['azure_plugin_settings']['backup_types']) 
            : array();
        
        $settings['backup_schedule_enabled'] = isset($_POST['azure_plugin_settings']['backup_schedule_enabled']);
        
        $settings['backup_schedule_frequency'] = isset($_POST['azure_plugin_settings']['backup_schedule_frequency'])
            ? sanitize_text_field($_POST['azure_plugin_settings']['backup_schedule_frequency'])
            : 'daily';
        
        $settings['backup_retention_days'] = isset($_POST['azure_plugin_settings']['backup_retention_days'])
            ? intval($_POST['azure_plugin_settings']['backup_retention_days'])
            : 30;
        
        // Legacy: also check direct POST variables for backwards compatibility
        if (empty($settings['backup_types']) && isset($_POST['backup_types'])) {
            $settings['backup_types'] = array_map('sanitize_text_field', $_POST['backup_types']);
        }
        if (!isset($_POST['azure_plugin_settings']['backup_schedule_enabled']) && isset($_POST['backup_schedule_enabled'])) {
        $settings['backup_schedule_enabled'] = isset($_POST['backup_schedule_enabled']);
        }
        if (!isset($_POST['azure_plugin_settings']['backup_schedule_frequency']) && isset($_POST['backup_schedule_frequency'])) {
            $settings['backup_schedule_frequency'] = sanitize_text_field($_POST['backup_schedule_frequency']);
        }
        if (!isset($_POST['azure_plugin_settings']['backup_retention_days']) && isset($_POST['backup_retention_days'])) {
            $settings['backup_retention_days'] = intval($_POST['backup_retention_days']);
        }
        
        // Other backup settings (these might be directly in POST or in subpages)
        $settings['backup_schedule_time'] = sanitize_text_field($_POST['backup_schedule_time'] ?? '02:00');
        $settings['backup_email_notifications'] = isset($_POST['backup_email_notifications']);
        $settings['backup_notification_email'] = sanitize_email($_POST['backup_notification_email'] ?? get_option('admin_email'));
    }
    
    private function save_calendar_settings(&$settings) {
        if (!Azure_Settings::get_setting('use_common_credentials')) {
            $settings['calendar_client_id'] = sanitize_text_field($_POST['calendar_client_id'] ?? '');
            $settings['calendar_client_secret'] = sanitize_text_field($_POST['calendar_client_secret'] ?? '');
            $settings['calendar_tenant_id'] = sanitize_text_field($_POST['calendar_tenant_id'] ?? 'common');
        }
        
        $settings['calendar_default_timezone'] = sanitize_text_field($_POST['calendar_default_timezone'] ?? 'America/New_York');
        $settings['calendar_default_view'] = sanitize_text_field($_POST['calendar_default_view'] ?? 'month');
        $settings['calendar_default_color_theme'] = sanitize_text_field($_POST['calendar_default_color_theme'] ?? 'blue');
        $settings['calendar_cache_duration'] = intval($_POST['calendar_cache_duration'] ?? 3600);
        $settings['calendar_max_events_per_calendar'] = intval($_POST['calendar_max_events_per_calendar'] ?? 100);
    }
    
    private function save_email_settings(&$settings) {
        if (!Azure_Settings::get_setting('use_common_credentials')) {
            $settings['email_client_id'] = sanitize_text_field($_POST['email_client_id'] ?? '');
            $settings['email_client_secret'] = sanitize_text_field($_POST['email_client_secret'] ?? '');
            $settings['email_tenant_id'] = sanitize_text_field($_POST['email_tenant_id'] ?? 'common');
        }
        
        $settings['email_auth_method'] = sanitize_text_field($_POST['email_auth_method'] ?? 'graph_api');
        $settings['email_send_as_alias'] = sanitize_text_field($_POST['email_send_as_alias'] ?? '');
        $settings['email_override_wp_mail'] = isset($_POST['email_override_wp_mail']);
        
        // HVE settings
        $settings['email_hve_smtp_server'] = sanitize_text_field($_POST['email_hve_smtp_server'] ?? 'smtp-hve.office365.com');
        $settings['email_hve_smtp_port'] = intval($_POST['email_hve_smtp_port'] ?? 587);
        $settings['email_hve_username'] = sanitize_text_field($_POST['email_hve_username'] ?? '');
        $settings['email_hve_password'] = sanitize_text_field($_POST['email_hve_password'] ?? '');
        $settings['email_hve_from_email'] = sanitize_email($_POST['email_hve_from_email'] ?? '');
        $settings['email_hve_encryption'] = sanitize_text_field($_POST['email_hve_encryption'] ?? 'tls');
        
        // ACS settings
        $settings['email_acs_connection_string'] = sanitize_text_field($_POST['email_acs_connection_string'] ?? '');
        $settings['email_acs_endpoint'] = sanitize_url($_POST['email_acs_endpoint'] ?? '');
        $settings['email_acs_access_key'] = sanitize_text_field($_POST['email_acs_access_key'] ?? '');
        $settings['email_acs_from_email'] = sanitize_email($_POST['email_acs_from_email'] ?? '');
        $settings['email_acs_display_name'] = sanitize_text_field($_POST['email_acs_display_name'] ?? '');
    }
    
    private function save_onedrive_media_settings(&$settings) {
        // Module-specific credentials (only save if not using common credentials)
        if (!Azure_Settings::get_setting('use_common_credentials')) {
            $settings['onedrive_media_client_id'] = sanitize_text_field($_POST['onedrive_media_client_id'] ?? '');
            $settings['onedrive_media_client_secret'] = sanitize_text_field($_POST['onedrive_media_client_secret'] ?? '');
            $settings['onedrive_media_tenant_id'] = sanitize_text_field($_POST['onedrive_media_tenant_id'] ?? 'common');
        }
        
        // Storage configuration
        $settings['onedrive_media_storage_type'] = sanitize_text_field($_POST['onedrive_media_storage_type'] ?? 'onedrive');
        $settings['onedrive_media_sharepoint_site_url'] = sanitize_url($_POST['onedrive_media_sharepoint_site_url'] ?? '');
        $settings['onedrive_media_site_id'] = sanitize_text_field($_POST['onedrive_media_site_id'] ?? '');
        $settings['onedrive_media_drive_id'] = sanitize_text_field($_POST['onedrive_media_drive_id'] ?? '');
        $settings['onedrive_media_drive_name'] = sanitize_text_field($_POST['onedrive_media_drive_name'] ?? '');
        
        // Folder configuration
        $settings['onedrive_media_base_folder'] = sanitize_text_field($_POST['onedrive_media_base_folder'] ?? 'WordPress Media');
        $settings['onedrive_media_use_year_folders'] = isset($_POST['onedrive_media_use_year_folders']);
        
        // Sync settings
        $settings['onedrive_media_auto_sync'] = isset($_POST['onedrive_media_auto_sync']);
        $settings['onedrive_media_sync_frequency'] = sanitize_text_field($_POST['onedrive_media_sync_frequency'] ?? 'hourly');
        $settings['onedrive_media_sync_direction'] = sanitize_text_field($_POST['onedrive_media_sync_direction'] ?? 'two_way');
        
        // Public access settings
        $settings['onedrive_media_sharing_link_type'] = sanitize_text_field($_POST['onedrive_media_sharing_link_type'] ?? 'anonymous');
        $settings['onedrive_media_link_expiration'] = sanitize_text_field($_POST['onedrive_media_link_expiration'] ?? 'never');
        $settings['onedrive_media_cdn_optimization'] = isset($_POST['onedrive_media_cdn_optimization']);
        
        // Media library options
        $settings['onedrive_media_show_badge'] = isset($_POST['onedrive_media_show_badge']);
        $settings['onedrive_media_keep_local_copies'] = isset($_POST['onedrive_media_keep_local_copies']);
        
        // Advanced options
        $settings['onedrive_media_max_file_size'] = intval($_POST['onedrive_media_max_file_size'] ?? 4294967296);
        $settings['onedrive_media_chunk_size'] = intval($_POST['onedrive_media_chunk_size'] ?? 10485760);
    }
    
    public function admin_page() {
        try {
            $settings = Azure_Settings::get_all_settings();
            
            // Debug: Log current settings when page loads
            error_log('Azure Plugin: Main page loaded, settings: ' . json_encode(array(
                'enable_sso' => $settings['enable_sso'] ?? 'not_set',
                'enable_backup' => $settings['enable_backup'] ?? 'not_set',
                'enable_calendar' => $settings['enable_calendar'] ?? 'not_set',
                'enable_email' => $settings['enable_email'] ?? 'not_set',
                'enable_pta' => $settings['enable_pta'] ?? 'not_set'
            )));
            
            include AZURE_PLUGIN_PATH . 'admin/main-page.php';
        } catch (Exception $e) {
            echo '<div class="wrap"><h1>Azure Plugin - Error</h1>';
            echo '<div class="notice notice-error"><p><strong>Critical Error:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
            echo '<p>Please check the system logs for more details. If this persists, try deactivating and reactivating the plugin.</p>';
            echo '</div>';
            Azure_Logger::error('Admin Page: Critical error - ' . $e->getMessage());
            Azure_Logger::error('Admin Page: Stack trace - ' . $e->getTraceAsString());
        }
    }
    
    public function admin_page_sso() {
        Azure_Logger::info('ADMIN: SSO page requested - starting admin_page_sso()');
        
        try {
            Azure_Logger::info('ADMIN: Getting all settings for SSO page');
            $settings = Azure_Settings::get_all_settings();
            Azure_Logger::info('ADMIN: Settings retrieved successfully, including SSO page');
            include AZURE_PLUGIN_PATH . 'admin/sso-page.php';
            Azure_Logger::info('ADMIN: SSO page included successfully');
        } catch (Exception $e) {
            Azure_Logger::error('ADMIN: Critical error in SSO page - ' . $e->getMessage());
            Azure_Logger::error('ADMIN: SSO error stack trace - ' . $e->getTraceAsString());
            $this->render_error_page('SSO', $e);
        }
    }
    
    public function admin_page_backup() {
        try {
            $settings = Azure_Settings::get_all_settings();
            include AZURE_PLUGIN_PATH . 'admin/backup-page.php';
        } catch (Exception $e) {
            $this->render_error_page('Backup', $e);
        }
    }
    
    public function admin_page_calendar() {
        try {
            $settings = Azure_Settings::get_all_settings();
            include AZURE_PLUGIN_PATH . 'admin/calendar-page.php';
        } catch (Exception $e) {
            $this->render_error_page('Calendar', $e);
        }
    }
    
    public function admin_page_tec() {
        try {
            $settings = Azure_Settings::get_all_settings();
            include AZURE_PLUGIN_PATH . 'admin/tec-integration-page.php';
        } catch (Exception $e) {
            $this->render_error_page('TEC Calendar Sync', $e);
        }
    }
    
    public function admin_page_email() {
        try {
            $settings = Azure_Settings::get_all_settings();
            include AZURE_PLUGIN_PATH . 'admin/email-page.php';
        } catch (Exception $e) {
            $this->render_error_page('Email', $e);
        }
    }
    
    public function admin_page_pta() {
        try {
            $settings = Azure_Settings::get_all_settings();
            include AZURE_PLUGIN_PATH . 'admin/pta-page.php';
        } catch (Exception $e) {
            $this->render_error_page('PTA Roles', $e);
        }
    }
    
    public function admin_page_pta_groups() {
        try {
            $settings = Azure_Settings::get_all_settings();
            include AZURE_PLUGIN_PATH . 'admin/pta-groups-page.php';
        } catch (Exception $e) {
            $this->render_error_page('PTA Groups', $e);
        }
    }
    
    public function admin_page_onedrive_media() {
        try {
            $settings = Azure_Settings::get_all_settings();
            include AZURE_PLUGIN_PATH . 'admin/onedrive-media-page.php';
        } catch (Exception $e) {
            $this->render_error_page('OneDrive Media', $e);
        }
    }
    
    public function admin_page_logs() {
        try {
            $logs = Azure_Logger::get_logs(200);
            include AZURE_PLUGIN_PATH . 'admin/logs-page.php';
        } catch (Exception $e) {
            $this->render_error_page('System Logs', $e);
        }
    }
    
    public function admin_page_email_logs() {
        try {
            // Get email statistics
            $email_logger = Azure_Email_Logger::get_instance();
            $email_stats = $email_logger->get_email_stats();
        
            include AZURE_PLUGIN_PATH . 'admin/email-logs-page.php';
        } catch (Exception $e) {
            $this->render_error_page('Email Logs', $e);
        }
    }
    
    /**
     * Render error page for admin pages
     */
    private function render_error_page($page_name, $exception) {
        echo '<div class="wrap">';
        echo '<h1>Azure Plugin - ' . esc_html($page_name) . ' - Error</h1>';
        echo '<div class="notice notice-error"><p><strong>Critical Error:</strong> ' . esc_html($exception->getMessage()) . '</p></div>';
        echo '<p>Please check the system logs for more details. If this persists, try deactivating and reactivating the plugin.</p>';
        echo '<p><a href="' . admin_url('admin.php?page=azure-plugin') . '" class="button">&larr; Back to Azure Plugin</a></p>';
        echo '</div>';
        
        // Log the error
        Azure_Logger::error("Admin Page ($page_name): Critical error - " . $exception->getMessage());
        Azure_Logger::error("Admin Page ($page_name): Stack trace - " . $exception->getTraceAsString());
    }
    
    public function ajax_test_credentials() {
        // Clean any output buffers to prevent whitespace before JSON
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Start fresh output buffer
        ob_start();
        
        // Security checks
        if (!current_user_can('manage_options')) {
            Azure_Logger::warning('Admin: Unauthorized credentials test attempt');
            wp_send_json_error('Unauthorized access - insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            Azure_Logger::warning('Admin: Invalid nonce in credentials test');
            wp_send_json_error('Unauthorized access - invalid nonce');
            return;
        }
        
        // Validate inputs
        if (empty($_POST['client_id']) || empty($_POST['client_secret']) || empty($_POST['tenant_id'])) {
            wp_send_json_error('Missing required credentials');
            return;
        }
        
        $client_id = sanitize_text_field($_POST['client_id']);
        $client_secret = sanitize_text_field($_POST['client_secret']);
        $tenant_id = sanitize_text_field($_POST['tenant_id']);
        
        try {
            $result = Azure_Settings::validate_credentials($client_id, $client_secret, $tenant_id);
            
            // Ensure result has required properties
            if (!isset($result['valid']) || !isset($result['message'])) {
                // Clean output buffer and send response
                ob_end_clean();
                wp_send_json_error('Invalid validation response');
                exit;
            }
            
            Azure_Logger::debug('Admin: Credentials test completed - Valid: ' . ($result['valid'] ? 'Yes' : 'No'));
            
            // Clean output buffer before sending JSON
            ob_end_clean();
            
            // Return in proper format based on validation result
            if ($result['valid']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
            exit;
        } catch (Exception $e) {
            Azure_Logger::error('Admin: Credentials test exception: ' . $e->getMessage());
            ob_end_clean();
            wp_send_json_error('Error testing credentials: ' . $e->getMessage());
            exit;
        }
    }
    
    public function ajax_toggle_module() {
        // Security checks for AJAX requests
        if (!current_user_can('manage_options')) {
            Azure_Logger::warning('Admin: Unauthorized access attempt from user ' . get_current_user_id());
            wp_send_json_error('Unauthorized access - insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            Azure_Logger::warning('Admin: Invalid nonce in AJAX request from user ' . get_current_user_id());
            wp_send_json_error('Unauthorized access - invalid nonce');
            return;
        }
        
        if (!isset($_POST['module']) || !isset($_POST['enabled'])) {
            Azure_Logger::warning('Admin: Missing required parameters in toggle_module AJAX request');
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        $module = sanitize_text_field($_POST['module']);
        $enabled = $_POST['enabled'] === 'true';
        
        // Validate module name
        $valid_modules = array('sso', 'backup', 'calendar', 'email', 'pta', 'tec_integration', 'onedrive_media');
        if (!in_array($module, $valid_modules)) {
            wp_send_json_error('Invalid module name: ' . $module);
        }
        
        try {
            // Debug: Check current settings before update
            $current_settings = Azure_Settings::get_all_settings();
            $current_value = Azure_Settings::is_module_enabled($module);
            
            $result = Azure_Settings::enable_module($module, $enabled);
            
            // Debug: Check settings after update
            $new_settings = Azure_Settings::get_all_settings();
            $new_value = Azure_Settings::is_module_enabled($module);
            
            if ($result) {
                Azure_Database::log_activity('admin', 'module_toggled', 'module', $module, array('enabled' => $enabled));
                wp_send_json_success(array(
                    'message' => ucfirst($module) . ' module ' . ($enabled ? 'enabled' : 'disabled') . ' successfully',
                    'debug' => array(
                        'current_value' => $current_value,
                        'new_value' => $new_value,
                        'update_result' => $result,
                        'settings_key' => "enable_{$module}"
                    )
                ));
                return;
            } else {
                Azure_Logger::error("Admin: Failed to toggle {$module} module - update_option returned false");
                wp_send_json_error(array(
                    'message' => 'Failed to update module settings',
                    'details' => 'update_option returned false',
                    'settings_key' => "enable_{$module}",
                    'current_value' => $current_value
                ));
                return;
            }
        } catch (Exception $e) {
            Azure_Logger::error("Admin: Exception toggling {$module} module: " . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Error toggling module',
                'error' => $e->getMessage()
            ));
            return;
        }
    }
    
    private function render_credentials_section($module, $settings) {
        $use_common = $settings['use_common_credentials'] ?? true;
        ?>
        <div class="credentials-section">
            <h3>Azure Credentials</h3>
            
            <?php if ($module === 'main'): ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Use Common Credentials</th>
                    <td>
                        <label>
                            <input type="checkbox" name="use_common_credentials" <?php checked($use_common); ?> />
                            Use the same Azure credentials for all enabled modules
                        </label>
                        <p class="description">When enabled, all modules will use the common credentials below. When disabled, each module can have its own credentials.</p>
                    </td>
                </tr>
            </table>
            
            <div id="common-credentials" <?php echo !$use_common ? 'style="display:none;"' : ''; ?>>
                <h4>Common Credentials</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">Client ID</th>
                        <td>
                            <input type="text" name="common_client_id" value="<?php echo esc_attr($settings['common_client_id'] ?? ''); ?>" class="regular-text" />
                            <p class="description">Your Azure App Registration Client ID</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Client Secret</th>
                        <td>
                            <input type="password" name="common_client_secret" value="<?php echo esc_attr($settings['common_client_secret'] ?? ''); ?>" class="regular-text" />
                            <p class="description">Your Azure App Registration Client Secret</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Tenant ID</th>
                        <td>
                            <input type="text" name="common_tenant_id" value="<?php echo esc_attr($settings['common_tenant_id'] ?? 'common'); ?>" class="regular-text" />
                            <p class="description">Your Azure Tenant ID (or 'common' for multi-tenant)</p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <button type="button" class="button test-credentials" 
                                data-client-id-field="common_client_id" 
                                data-client-secret-field="common_client_secret" 
                                data-tenant-id-field="common_tenant_id">
                                Test Credentials
                            </button>
                            <span class="credentials-status"></span>
                        </td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
            
            <?php if ($module !== 'main' && !$use_common): ?>
            <div id="<?php echo $module; ?>-credentials">
                <h4><?php echo ucfirst($module); ?> Specific Credentials</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">Client ID</th>
                        <td>
                            <input type="text" name="<?php echo $module; ?>_client_id" value="<?php echo esc_attr($settings[$module . '_client_id'] ?? ''); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Client Secret</th>
                        <td>
                            <input type="password" name="<?php echo $module; ?>_client_secret" value="<?php echo esc_attr($settings[$module . '_client_secret'] ?? ''); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Tenant ID</th>
                        <td>
                            <input type="text" name="<?php echo $module; ?>_tenant_id" value="<?php echo esc_attr($settings[$module . '_tenant_id'] ?? 'common'); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <button type="button" class="button test-credentials" 
                                data-client-id-field="<?php echo $module; ?>_client_id" 
                                data-client-secret-field="<?php echo $module; ?>_client_secret" 
                                data-tenant-id-field="<?php echo $module; ?>_tenant_id">
                                Test Credentials
                            </button>
                            <span class="credentials-status"></span>
                        </td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function ajax_calendar_authorize() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        if (!class_exists('Azure_Calendar_Auth')) {
            wp_send_json_error('Calendar authentication class not found');
        }
        
        try {
            $auth = new Azure_Calendar_Auth();
            $auth_url = $auth->get_authorization_url();
            
            if ($auth_url) {
                wp_send_json_success(array('auth_url' => $auth_url));
            } else {
                wp_send_json_error('Failed to generate authorization URL. Check your credentials.');
            }
        } catch (Exception $e) {
            wp_send_json_error('Authorization failed: ' . $e->getMessage());
        }
    }
    
    public function ajax_test_sso_connection() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        if (!class_exists('Azure_SSO_Auth')) {
            wp_send_json_error('SSO authentication class not found');
        }
        
        try {
            // Get credentials
            $settings = Azure_Settings::get_all_settings();
            $client_id = Azure_Settings::get_setting('use_common_credentials') ? 
                         $settings['common_client_id'] : $settings['sso_client_id'];
            $client_secret = Azure_Settings::get_setting('use_common_credentials') ? 
                             $settings['common_client_secret'] : $settings['sso_client_secret'];
            $tenant_id = Azure_Settings::get_setting('use_common_credentials') ? 
                         $settings['common_tenant_id'] : $settings['sso_tenant_id'];
            
            if (empty($client_id) || empty($client_secret) || empty($tenant_id)) {
                wp_send_json_error('Missing SSO credentials. Please configure them first.');
            }
            
            $auth = new Azure_SSO_Auth();
            $test_result = $auth->test_connection($client_id, $client_secret, $tenant_id);
            
            if ($test_result['success']) {
                wp_send_json_success($test_result['message']);
            } else {
                wp_send_json_error($test_result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('SSO test failed: ' . $e->getMessage());
        }
    }
    
    public function ajax_test_storage_connection() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        if (!class_exists('Azure_Backup_Storage')) {
            wp_send_json_error('Backup storage class not found');
        }
        
        try {
            // Get storage credentials from POST or settings (check both new and old names)
            $storage_account = isset($_POST['storage_account']) 
                ? sanitize_text_field($_POST['storage_account']) 
                : (Azure_Settings::get_setting('backup_storage_account_name') ?: Azure_Settings::get_setting('backup_storage_account'));
            
            $storage_key = isset($_POST['storage_key']) 
                ? sanitize_text_field($_POST['storage_key']) 
                : (Azure_Settings::get_setting('backup_storage_account_key') ?: Azure_Settings::get_setting('backup_storage_key'));
            
            $container_name = isset($_POST['container_name']) 
                ? sanitize_text_field($_POST['container_name']) 
                : (Azure_Settings::get_setting('backup_storage_container_name') ?: Azure_Settings::get_setting('backup_container_name'));
            
            if (empty($storage_account) || empty($storage_key)) {
                wp_send_json_error('Missing storage credentials. Please configure Storage Account Name and Access Key.');
            }
            
            if (empty($container_name)) {
                $container_name = 'wordpress-backups';
            }
            
            $storage = new Azure_Backup_Storage();
            $test_result = $storage->test_connection($storage_account, $storage_key, $container_name);
            
            if ($test_result['success']) {
                wp_send_json_success($test_result['message']);
            } else {
                wp_send_json_error($test_result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('Storage test failed: ' . $e->getMessage());
        }
    }
    
    public function ajax_test_calendar_connection() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        if (!class_exists('Azure_Calendar_Auth')) {
            wp_send_json_error('Calendar authentication class not found');
        }
        
        try {
            $auth = new Azure_Calendar_Auth();
            $test_result = $auth->test_connection();
            
            if ($test_result['success']) {
                wp_send_json_success($test_result['message']);
            } else {
                wp_send_json_error($test_result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('Calendar test failed: ' . $e->getMessage());
        }
    }
    
    public function ajax_delete_backup() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        if (!isset($_POST['backup_id'])) {
            wp_send_json_error('Missing backup ID');
        }
        
        $backup_id = intval($_POST['backup_id']);
        
        if (!class_exists('Azure_Backup_Restore')) {
            wp_send_json_error('Backup restore class not found');
        }
        
        try {
            $restore = new Azure_Backup_Restore();
            $result = $restore->delete_backup($backup_id);
            
            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('Delete backup failed: ' . $e->getMessage());
        }
    }
    
    public function ajax_clear_logs() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        try {
            // Use the logger's clear method to clear both file and database logs
            Azure_Logger::clear_logs();
            Azure_Logger::info('Admin: Logs cleared by user ' . get_current_user_id());
            
            wp_send_json_success('Logs cleared successfully');
        } catch (Exception $e) {
            wp_send_json_error('Failed to clear logs: ' . $e->getMessage());
        }
    }
    
    public function ajax_refresh_logs() {
        // Debug: Log that AJAX handler was called
        Azure_Logger::debug('Admin: ajax_refresh_logs called', 'Admin');
        
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            Azure_Logger::error('Admin: ajax_refresh_logs unauthorized access', 'Admin');
            wp_send_json_error('Unauthorized access');
        }
        
        try {
            $level_filter = sanitize_text_field($_POST['level'] ?? '');
            $module_filter = sanitize_text_field($_POST['module'] ?? '');
            
            $log_lines = Azure_Logger::get_formatted_logs(500, $level_filter, $module_filter);
            
            $html = '';
            if (empty($log_lines)) {
                $html = '<div class="log-line info">No logs available yet. Activity will appear here as you use the plugin modules.</div>';
            } else {
                foreach ($log_lines as $line) {
                    if (!trim($line)) continue;
                    
                    // Parse log line and determine level class
                    $level_class = 'info';
                    if (strpos($line, '- ERROR -') !== false) $level_class = 'error';
                    elseif (strpos($line, '- WARNING -') !== false) $level_class = 'warning';
                    elseif (strpos($line, '- DEBUG -') !== false) $level_class = 'debug';
                    
                    // Extract parts for better formatting
                    if (preg_match('/^(\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}) (\[.*?\]) - (\w+) - (.*)$/', $line, $matches)) {
                        $timestamp = esc_html($matches[1]);
                        $module = esc_html($matches[2]);
                        $level = esc_html($matches[3]);
                        $message = esc_html($matches[4]);
                        $module_clean = strtolower(trim($module, '[]'));
                        
                        $html .= sprintf(
                            '<div class="log-line %s" data-level="%s" data-module="%s">
                                <span class="log-timestamp">%s</span>
                                <span class="log-module module-badge module-%s">%s</span>
                                <span class="log-level level-%s">%s</span>
                                <span class="log-message">%s</span>
                            </div>',
                            $level_class,
                            strtolower($level),
                            $module_clean,
                            $timestamp,
                            $module_clean,
                            $module,
                            strtolower($level),
                            $level,
                            $message
                        );
                    } else {
                        $html .= '<div class="log-line ' . $level_class . '">' . esc_html($line) . '</div>';
                    }
                }
            }
            
            wp_send_json_success(array(
                'html' => $html,
                'count' => count($log_lines)
            ));
        } catch (Exception $e) {
            wp_send_json_error('Failed to refresh logs: ' . $e->getMessage());
        }
    }
    
    public function ajax_download_logs() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        try {
            $log_lines = Azure_Logger::get_formatted_logs(2000); // Get more lines for download
            
            if (empty($log_lines)) {
                wp_send_json_error('No logs available to download');
                return;
            }
            
            $filename = 'azure-plugin-logs-' . date('Y-m-d-H-i-s') . '.txt';
            $content = "Azure Plugin Logs - Downloaded on " . date('Y-m-d H:i:s') . "\n";
            $content .= str_repeat("=", 60) . "\n\n";
            $content .= implode("\n", $log_lines);
            
            wp_send_json_success(array(
                'filename' => $filename,
                'content' => $content
            ));
        } catch (Exception $e) {
            wp_send_json_error('Failed to prepare logs for download: ' . $e->getMessage());
        }
    }
    
    public function ajax_export_settings() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        try {
            $settings = Azure_Settings::get_all_settings();
            
            // Remove sensitive data
            unset($settings['common_client_secret']);
            unset($settings['sso_client_secret']);
            unset($settings['backup_client_secret']);
            unset($settings['calendar_client_secret']);
            unset($settings['email_client_secret']);
            unset($settings['backup_storage_key']);
            
            $export_data = array(
                'plugin_version' => AZURE_PLUGIN_VERSION,
                'export_date' => current_time('mysql'),
                'settings' => $settings
            );
            
            Azure_Logger::info('Admin: Settings exported by user ' . get_current_user_id());
            
            wp_send_json_success(json_encode($export_data, JSON_PRETTY_PRINT));
        } catch (Exception $e) {
            wp_send_json_error('Failed to export settings: ' . $e->getMessage());
        }
    }
    
    public function ajax_import_settings() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        try {
            $settings_data = json_decode(stripslashes($_POST['settings_data']), true);
            
            if (!$settings_data || !isset($settings_data['settings'])) {
                wp_send_json_error('Invalid settings file format');
            }
            
            $current_settings = Azure_Settings::get_all_settings();
            $import_settings = $settings_data['settings'];
            
            // Merge settings (keep sensitive data from current settings)
            $final_settings = array_merge($import_settings, array(
                'common_client_secret' => $current_settings['common_client_secret'] ?? '',
                'sso_client_secret' => $current_settings['sso_client_secret'] ?? '',
                'backup_client_secret' => $current_settings['backup_client_secret'] ?? '',
                'calendar_client_secret' => $current_settings['calendar_client_secret'] ?? '',
                'email_client_secret' => $current_settings['email_client_secret'] ?? '',
                'backup_storage_key' => $current_settings['backup_storage_key'] ?? ''
            ));
            
            Azure_Settings::update_settings($final_settings);
            
            Azure_Logger::info('Admin: Settings imported by user ' . get_current_user_id());
            
            wp_send_json_success('Settings imported successfully');
        } catch (Exception $e) {
            wp_send_json_error('Failed to import settings: ' . $e->getMessage());
        }
    }
    
    public function ajax_get_recent_activity() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        try {
            global $wpdb;
            $activity_table = Azure_Database::get_table_name('activity_log');
            
            if (!$activity_table) {
                wp_send_json_success('');
            }
            
            $recent_activity = $wpdb->get_results("SELECT * FROM {$activity_table} ORDER BY created_at DESC LIMIT 20");
            
            $html = '';
            foreach ($recent_activity as $activity) {
                $user_name = 'System';
                if ($activity->user_id) {
                    $user = get_user_by('id', $activity->user_id);
                    $user_name = $user ? $user->display_name : 'User #' . $activity->user_id;
                }
                
                $html .= '<tr>';
                $html .= '<td><span class="module-badge module-' . esc_attr($activity->module) . '">' . esc_html(strtoupper($activity->module)) . '</span></td>';
                $html .= '<td>' . esc_html($activity->action) . '</td>';
                $html .= '<td>';
                if ($activity->object_type) {
                    $html .= esc_html($activity->object_type);
                    if ($activity->object_id) {
                        $html .= ' <code>#' . esc_html($activity->object_id) . '</code>';
                    }
                } else {
                    $html .= '-';
                }
                $html .= '</td>';
                $html .= '<td>' . esc_html($user_name) . '</td>';
                $html .= '<td><span class="status-indicator ' . esc_attr($activity->status) . '">' . esc_html(ucfirst($activity->status)) . '</span></td>';
                $html .= '<td>' . esc_html($activity->created_at) . '</td>';
                $html .= '<td>';
                if ($activity->details) {
                    $html .= '<button type="button" class="button button-small view-details" data-details="' . esc_attr($activity->details) . '">View</button>';
                } else {
                    $html .= '-';
                }
                $html .= '</td>';
                $html .= '</tr>';
            }
            
            wp_send_json_success($html);
        } catch (Exception $e) {
            wp_send_json_error('Failed to get recent activity: ' . $e->getMessage());
        }
    }
    
    // ==========================================
    // Calendar Embed AJAX Handlers
    // ==========================================
    
    public function ajax_save_calendar_embed_email() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        $user_email = sanitize_email($_POST['user_email'] ?? '');
        $mailbox_email = sanitize_email($_POST['mailbox_email'] ?? '');
        
        if (empty($user_email)) {
            wp_send_json_error('Your M365 account email is required');
        }
        
        if (empty($mailbox_email)) {
            wp_send_json_error('Shared mailbox email is required');
        }
        
        try {
            // Use update_settings to save both emails
            Azure_Settings::update_settings(array(
                'calendar_embed_user_email' => $user_email,
                'calendar_embed_mailbox_email' => $mailbox_email
            ));
            
            // Verify the emails were saved by reading them back
            $saved_user_email = Azure_Settings::get_setting('calendar_embed_user_email', '');
            $saved_mailbox_email = Azure_Settings::get_setting('calendar_embed_mailbox_email', '');
            
            if ($saved_user_email === $user_email && $saved_mailbox_email === $mailbox_email) {
                if (class_exists('Azure_Logger')) {
                    try {
                        Azure_Logger::info('Calendar Embed: Settings saved', array(
                            'user_email' => $user_email,
                            'mailbox_email' => $mailbox_email
                        ));
                    } catch (Exception $e) {
                        // Ignore logging errors
                    }
                }
                wp_send_json_success('Settings saved successfully');
            } else {
                wp_send_json_error('Failed to save settings to database');
            }
        } catch (Exception $e) {
            wp_send_json_error('Failed to save settings: ' . $e->getMessage());
        }
    }
    
    public function ajax_calendar_embed_authorize() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        $user_email = sanitize_email($_POST['user_email'] ?? '');
        
        if (empty($user_email)) {
            wp_send_json_error('Your M365 account email is required');
        }
        
        if (!class_exists('Azure_Calendar_Auth')) {
            wp_send_json_error('Calendar authentication class not found');
        }
        
        try {
            // Check if credentials are configured
            $credentials = Azure_Settings::get_credentials('calendar');
            
            if (empty($credentials['client_id'])) {
                wp_send_json_error('Azure App Registration credentials not configured. Please configure them in Main Settings.');
            }
            
            if (empty($credentials['tenant_id'])) {
                wp_send_json_error('Azure Tenant ID not configured. Please configure it in Main Settings.');
            }
            
            $auth = new Azure_Calendar_Auth();
            // Authenticate as the user (not the shared mailbox)
            $auth_url = $auth->get_authorization_url_for_user($user_email, 'azure-plugin-calendar');
            
            if ($auth_url) {
                wp_send_json_success(array('auth_url' => $auth_url));
            } else {
                wp_send_json_error('Failed to generate authorization URL. Check your credentials in Main Settings.');
            }
        } catch (Exception $e) {
            wp_send_json_error('Authorization failed: ' . $e->getMessage());
        }
    }
    
    public function ajax_calendar_embed_check_auth() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (empty($email)) {
            wp_send_json_error('Email is required');
        }
        
        if (!class_exists('Azure_Calendar_Auth')) {
            wp_send_json_error('Calendar authentication class not found');
        }
        
        try {
            $auth = new Azure_Calendar_Auth();
            $is_authenticated = $auth->has_valid_user_token($email);
            
            if ($is_authenticated) {
                wp_send_json_success('Authenticated');
            } else {
                wp_send_json_error('Not yet authenticated');
            }
        } catch (Exception $e) {
            wp_send_json_error('Check failed: ' . $e->getMessage());
        }
    }
    
    public function ajax_calendar_embed_revoke() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (empty($email)) {
            wp_send_json_error('Email is required');
        }
        
        if (!class_exists('Azure_Calendar_Auth')) {
            wp_send_json_error('Calendar authentication class not found');
        }
        
        try {
            $auth = new Azure_Calendar_Auth();
            $auth->revoke_user_tokens($email);
            
            Azure_Logger::info('Calendar Embed: User tokens revoked', array('email' => $email));
            wp_send_json_success('Authorization revoked successfully');
        } catch (Exception $e) {
            wp_send_json_error('Revoke failed: ' . $e->getMessage());
        }
    }
    
    public function ajax_toggle_calendar_embed() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        $calendar_id = sanitize_text_field($_POST['calendar_id'] ?? '');
        $calendar_name = sanitize_text_field($_POST['calendar_name'] ?? '');
        $enabled = filter_var($_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        if (empty($calendar_id)) {
            wp_send_json_error('Calendar ID is required');
        }
        
        try {
            $settings = Azure_Settings::get_all_settings();
            $enabled_calendars = $settings['calendar_embed_enabled_calendars'] ?? array();
            
            if ($enabled) {
                // Add to enabled list
                if (!in_array($calendar_id, $enabled_calendars)) {
                    $enabled_calendars[] = $calendar_id;
                }
            } else {
                // Remove from enabled list
                $enabled_calendars = array_diff($enabled_calendars, array($calendar_id));
                $enabled_calendars = array_values($enabled_calendars); // Re-index
            }
            
            Azure_Settings::update_settings(array(
                'calendar_embed_enabled_calendars' => $enabled_calendars
            ));
            
            Azure_Logger::info(
                'Calendar Embed: Calendar ' . ($enabled ? 'enabled' : 'disabled'), 
                array('calendar_id' => $calendar_id, 'calendar_name' => $calendar_name)
            );
            
            wp_send_json_success('Calendar updated successfully');
        } catch (Exception $e) {
            wp_send_json_error('Failed to update calendar: ' . $e->getMessage());
        }
    }
    
    public function ajax_save_calendar_timezone() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        $calendar_id = sanitize_text_field($_POST['calendar_id'] ?? '');
        $timezone = sanitize_text_field($_POST['timezone'] ?? '');
        
        if (empty($calendar_id) || empty($timezone)) {
            wp_send_json_error('Calendar ID and timezone are required');
        }
        
        try {
            $settings = Azure_Settings::get_all_settings();
            $settings['calendar_timezone_' . $calendar_id] = $timezone;
            Azure_Settings::save_all_settings($settings);
            
            Azure_Logger::info(
                'Calendar Embed: Timezone saved', 
                array('calendar_id' => $calendar_id, 'timezone' => $timezone)
            );
            
            wp_send_json_success('Timezone saved successfully');
        } catch (Exception $e) {
            wp_send_json_error('Failed to save timezone: ' . $e->getMessage());
        }
    }
    
    public function ajax_calendar_get_events() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        $calendar_id = sanitize_text_field($_POST['calendar_id'] ?? '');
        $user_email = sanitize_email($_POST['user_email'] ?? '');
        $max_events = intval($_POST['max_events'] ?? 10);
        
        if (empty($calendar_id)) {
            wp_send_json_error('Calendar ID is required');
        }
        
        if (empty($user_email)) {
            wp_send_json_error('User email is required');
        }
        
        try {
            if (!class_exists('Azure_Calendar_GraphAPI')) {
                wp_send_json_error('Calendar API class not found');
            }
            
            $graph_api = new Azure_Calendar_GraphAPI();
            
            // Get events from the calendar
            $start_date = date('Y-m-d\TH:i:s');
            $end_date = date('Y-m-d\TH:i:s', strtotime('+30 days'));
            
            $events = $graph_api->get_calendar_events(
                $calendar_id, 
                $start_date, 
                $end_date, 
                $max_events,
                false, // don't force refresh
                $user_email
            );
            
            if ($events === false) {
                wp_send_json_error('Failed to retrieve events from Microsoft Graph API');
            }
            
            // Format events for display
            $formatted_events = array();
            foreach ($events as $event) {
                $formatted_events[] = array(
                    'title' => $event['subject'] ?? 'Untitled Event',
                    'start' => $event['start']['dateTime'] ?? '',
                    'end' => $event['end']['dateTime'] ?? '',
                    'location' => $event['location']['displayName'] ?? '',
                    'description' => $event['bodyPreview'] ?? ''
                );
            }
            
            Azure_Logger::info('Calendar Embed: Events retrieved', array(
                'calendar_id' => $calendar_id,
                'user_email' => $user_email,
                'event_count' => count($formatted_events)
            ));
            
            wp_send_json_success($formatted_events);
        } catch (Exception $e) {
            Azure_Logger::error('Calendar Embed: Failed to get events', array(
                'error' => $e->getMessage(),
                'calendar_id' => $calendar_id,
                'user_email' => $user_email
            ));
            wp_send_json_error('Failed to retrieve events: ' . $e->getMessage());
        }
    }
    
    // ==========================================
    // TEC Calendar AJAX Handlers
    // ==========================================
    
    public function ajax_save_tec_calendar_email() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        $user_email = sanitize_email($_POST['user_email'] ?? '');
        $mailbox_email = sanitize_email($_POST['mailbox_email'] ?? '');
        
        if (empty($user_email)) {
            wp_send_json_error('Your M365 account email is required');
        }
        
        if (empty($mailbox_email)) {
            wp_send_json_error('Shared mailbox email is required');
        }
        
        try {
            // Use update_settings to save both emails
            Azure_Settings::update_settings(array(
                'tec_calendar_user_email' => $user_email,
                'tec_calendar_mailbox_email' => $mailbox_email
            ));
            
            // Verify the emails were saved by reading them back
            $saved_user_email = Azure_Settings::get_setting('tec_calendar_user_email', '');
            $saved_mailbox_email = Azure_Settings::get_setting('tec_calendar_mailbox_email', '');
            
            if ($saved_user_email === $user_email && $saved_mailbox_email === $mailbox_email) {
                if (class_exists('Azure_Logger')) {
                    try {
                        Azure_Logger::info('TEC Calendar: Settings saved', array(
                            'user_email' => $user_email,
                            'mailbox_email' => $mailbox_email
                        ));
                    } catch (Exception $e) {
                        // Ignore logging errors
                    }
                }
                wp_send_json_success('Settings saved successfully');
            } else {
                wp_send_json_error('Failed to save settings to database');
            }
        } catch (Exception $e) {
            wp_send_json_error('Failed to save settings: ' . $e->getMessage());
        }
    }
    
    public function ajax_tec_calendar_authorize() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        $user_email = sanitize_email($_POST['user_email'] ?? '');
        
        if (empty($user_email)) {
            wp_send_json_error('Your M365 account email is required');
        }
        
        if (!class_exists('Azure_Calendar_Auth')) {
            wp_send_json_error('Calendar authentication class not found');
        }
        
        try {
            // Check if credentials are configured
            $credentials = Azure_Settings::get_credentials('calendar');
            
            if (empty($credentials['client_id'])) {
                wp_send_json_error('Azure App Registration credentials not configured. Please configure them in Main Settings.');
            }
            
            if (empty($credentials['tenant_id'])) {
                wp_send_json_error('Azure Tenant ID not configured. Please configure it in Main Settings.');
            }
            
            $auth = new Azure_Calendar_Auth();
            // Authenticate as the user (not the shared mailbox)
            $auth_url = $auth->get_authorization_url_for_user($user_email, 'azure-plugin-tec');
            
            if ($auth_url) {
                wp_send_json_success(array('auth_url' => $auth_url));
            } else {
                wp_send_json_error('Failed to generate authorization URL. Check your credentials in Main Settings.');
            }
        } catch (Exception $e) {
            wp_send_json_error('Authorization failed: ' . $e->getMessage());
        }
    }
    
    public function ajax_tec_calendar_check_auth() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized access');
        }
        
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (empty($email)) {
            wp_send_json_error('Email is required');
        }
        
        if (!class_exists('Azure_Calendar_Auth')) {
            wp_send_json_error('Calendar authentication class not found');
        }
        
        try {
            $auth = new Azure_Calendar_Auth();
            $is_authenticated = $auth->has_valid_user_token($email);
            
            if ($is_authenticated) {
                wp_send_json_success('Authenticated');
            } else {
                wp_send_json_error('Not yet authenticated');
            }
        } catch (Exception $e) {
            wp_send_json_error('Check failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: OneDrive authorization
     */
    public function ajax_onedrive_authorize() {
        check_ajax_referer('azure_plugin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!class_exists('Azure_OneDrive_Media_Auth')) {
            wp_send_json_error('OneDrive authentication class not found');
        }
        
        try {
            $auth = new Azure_OneDrive_Media_Auth();
            $auth_url = $auth->get_authorization_url();
            
            if ($auth_url) {
                wp_send_json_success(array('auth_url' => $auth_url));
            } else {
                wp_send_json_error('Failed to generate authorization URL');
            }
        } catch (Exception $e) {
            wp_send_json_error('Authorization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Test OneDrive connection
     */
    public function ajax_onedrive_test_connection() {
        check_ajax_referer('azure_plugin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!class_exists('Azure_OneDrive_Media_Auth')) {
            wp_send_json_error('OneDrive authentication class not found');
        }
        
        try {
            $auth = new Azure_OneDrive_Media_Auth();
            $result = $auth->test_connection();
            
            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('Connection test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Sync from OneDrive
     */
    public function ajax_onedrive_sync_from_onedrive() {
        check_ajax_referer('azure_plugin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!class_exists('Azure_OneDrive_Media_Manager')) {
            wp_send_json_error('OneDrive Media Manager class not found');
        }
        
        try {
            $manager = Azure_OneDrive_Media_Manager::get_instance();
            $result = $manager->sync_from_onedrive();
            
            if ($result['success']) {
                wp_send_json_success(array('message' => $result['message']));
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('Sync failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Browse OneDrive folders
     */
    public function ajax_onedrive_browse_folders() {
        check_ajax_referer('azure_plugin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!class_exists('Azure_OneDrive_Media_GraphAPI')) {
            wp_send_json_error('OneDrive Graph API class not found');
        }
        
        try {
            $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '/';
            $path = trim($path, '/'); // Remove leading/trailing slashes
            
            $graph_api = new Azure_OneDrive_Media_GraphAPI();
            $items = $graph_api->list_folder($path);
            
            if ($items !== false) {
                // Filter to only folders
                $folders = array_filter($items, function($item) {
                    return isset($item['is_folder']) && $item['is_folder'];
                });
                
                // Format for UI
                $formatted_folders = array_map(function($folder) use ($path) {
                    $folder_path = $path ? $path . '/' . $folder['name'] : $folder['name'];
                    return array(
                        'name' => $folder['name'],
                        'path' => '/' . $folder_path . '/'
                    );
                }, array_values($folders));
                
                wp_send_json_success(array('folders' => $formatted_folders));
            } else {
                wp_send_json_error('Failed to retrieve folders');
            }
        } catch (Exception $e) {
            wp_send_json_error('Browse failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Create year folders
     */
    public function ajax_onedrive_create_year_folders() {
        check_ajax_referer('azure_plugin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!class_exists('Azure_OneDrive_Media_GraphAPI')) {
            wp_send_json_error('OneDrive Graph API class not found');
        }
        
        try {
            $graph_api = new Azure_OneDrive_Media_GraphAPI();
            $base_folder = Azure_Settings::get_setting('onedrive_media_base_folder', 'WordPress Media');
            
            // Create folders for: Before 2024, 2024, 2025, ..., current year
            $current_year = date('Y');
            $folders_to_create = array('Before 2024');
            
            for ($year = 2024; $year <= $current_year; $year++) {
                $folders_to_create[] = (string)$year;
            }
            
            $created = array();
            $errors = array();
            
            foreach ($folders_to_create as $folder_name) {
                $result = $graph_api->create_folder($base_folder, $folder_name);
                if ($result) {
                    $created[] = $folder_name;
                } else {
                    $errors[] = $folder_name;
                }
            }
            
            if (count($created) > 0) {
                $message = 'Created ' . count($created) . ' folder(s): ' . implode(', ', $created);
                if (count($errors) > 0) {
                    $message .= '. Failed: ' . implode(', ', $errors);
                }
                wp_send_json_success(array('message' => $message));
            } else {
                wp_send_json_error('Failed to create any folders');
            }
        } catch (Exception $e) {
            wp_send_json_error('Create folders failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Resolve SharePoint site URL to Site ID
     */
    public function ajax_resolve_sharepoint_site() {
        check_ajax_referer('azure_plugin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!class_exists('Azure_OneDrive_Media_Auth')) {
            wp_send_json_error('OneDrive authentication class not found');
        }
        
        $site_url = isset($_POST['site_url']) ? esc_url_raw($_POST['site_url']) : '';
        
        if (empty($site_url)) {
            wp_send_json_error('Site URL is required');
        }
        
        try {
            $auth = new Azure_OneDrive_Media_Auth();
            $access_token = $auth->get_access_token();
            
            if (!$access_token) {
                wp_send_json_error('No access token available. Please authorize first.');
            }
            
            // Parse the site URL to get the hostname and path
            $parsed_url = parse_url($site_url);
            $hostname = $parsed_url['host'] ?? '';
            $path = isset($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';
            
            // Build Graph API URL to get site by URL
            // Format: /sites/{hostname}:/{path}
            if (empty($path)) {
                // Root site
                $api_url = "https://graph.microsoft.com/v1.0/sites/{$hostname}";
            } else {
                $api_url = "https://graph.microsoft.com/v1.0/sites/{$hostname}:/{$path}";
            }
            
            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error('Failed to connect to SharePoint: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code === 200) {
                $site_data = json_decode(wp_remote_retrieve_body($response), true);
                wp_send_json_success(array(
                    'site_id' => $site_data['id'],
                    'site_name' => $site_data['displayName'] ?? $site_data['name'] ?? 'SharePoint Site',
                    'site_url' => $site_data['webUrl'] ?? $site_url
                ));
            } else {
                $error_body = wp_remote_retrieve_body($response);
                $error_data = json_decode($error_body, true);
                $error_message = $error_data['error']['message'] ?? 'Unknown error';
                wp_send_json_error('Failed to resolve site (HTTP ' . $response_code . '): ' . $error_message);
            }
        } catch (Exception $e) {
            wp_send_json_error('Error resolving site: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: List SharePoint document libraries (drives)
     */
    public function ajax_list_sharepoint_drives() {
        check_ajax_referer('azure_plugin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!class_exists('Azure_OneDrive_Media_Auth')) {
            wp_send_json_error('OneDrive authentication class not found');
        }
        
        $site_id = isset($_POST['site_id']) ? sanitize_text_field($_POST['site_id']) : '';
        
        if (empty($site_id)) {
            wp_send_json_error('Site ID is required');
        }
        
        try {
            $auth = new Azure_OneDrive_Media_Auth();
            $access_token = $auth->get_access_token();
            
            if (!$access_token) {
                wp_send_json_error('No access token available. Please authorize first.');
            }
            
            // Get all drives for this site
            $api_url = "https://graph.microsoft.com/v1.0/sites/{$site_id}/drives";
            
            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error('Failed to list document libraries: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                $drives = array();
                
                foreach ($data['value'] ?? array() as $drive) {
                    $drives[] = array(
                        'id' => $drive['id'],
                        'name' => $drive['name'],
                        'description' => $drive['description'] ?? '',
                        'type' => $drive['driveType'] ?? 'documentLibrary'
                    );
                }
                
                wp_send_json_success(array('drives' => $drives));
            } else {
                $error_body = wp_remote_retrieve_body($response);
                $error_data = json_decode($error_body, true);
                $error_message = $error_data['error']['message'] ?? 'Unknown error';
                wp_send_json_error('Failed to list drives (HTTP ' . $response_code . '): ' . $error_message);
            }
        } catch (Exception $e) {
            wp_send_json_error('Error listing drives: ' . $e->getMessage());
        }
    }
}