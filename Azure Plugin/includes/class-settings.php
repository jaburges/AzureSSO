<?php
/**
 * Azure Plugin Settings Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Settings {
    
    private static $option_name = 'azure_plugin_settings';
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new Azure_Settings();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function register_settings() {
        register_setting('azure_plugin_settings', self::$option_name);
    }
    
    public static function get_all_settings() {
        return get_option(self::$option_name, array());
    }
    
    public static function get_setting($key, $default = '') {
        $settings = self::get_all_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    public static function update_setting($key, $value) {
        $settings = self::get_all_settings();
        $old_value = isset($settings[$key]) ? $settings[$key] : 'not_set';
        $settings[$key] = $value;
        
        // Debug logging
        error_log("Azure Plugin Settings Debug: Updating key '{$key}' from '{$old_value}' to '{$value}'");
        error_log("Azure Plugin Settings Debug: Option name: '" . self::$option_name . "'");
        error_log("Azure Plugin Settings Debug: Settings array size: " . count($settings));
        error_log("Azure Plugin Settings Debug: Settings content: " . json_encode($settings));
        
        $result = update_option(self::$option_name, $settings);
        
        error_log("Azure Plugin Settings Debug: update_option result: " . ($result ? 'SUCCESS' : 'FAILED'));
        
        if (!$result) {
            // Try to get more info about why it failed
            $current_option = get_option(self::$option_name, 'OPTION_NOT_EXISTS');
            error_log("Azure Plugin Settings Debug: Current option value: " . json_encode($current_option));
            
            // Check if the issue is the option already has the same value
            if ($current_option === $settings) {
                error_log("Azure Plugin Settings Debug: Option already has the same value - this is normal");
                return true; // WordPress returns false if value hasn't changed
            }
            
            // Try alternative approaches to fix the issue
            if ($current_option === 'OPTION_NOT_EXISTS') {
                error_log("Azure Plugin Settings Debug: Option doesn't exist, trying add_option");
                $result = add_option(self::$option_name, $settings);
                error_log("Azure Plugin Settings Debug: add_option result: " . ($result ? 'SUCCESS' : 'FAILED'));
            } else {
                // Try deleting and recreating the option
                error_log("Azure Plugin Settings Debug: Trying to delete and recreate option");
                delete_option(self::$option_name);
                $result = add_option(self::$option_name, $settings);
                error_log("Azure Plugin Settings Debug: delete/add_option result: " . ($result ? 'SUCCESS' : 'FAILED'));
            }
        }
        
        return $result;
    }
    
    public static function update_settings($new_settings) {
        $settings = self::get_all_settings();
        $settings = array_merge($settings, $new_settings);
        return update_option(self::$option_name, $settings);
    }
    
    /**
     * Get credentials for a specific module
     * If use_common_credentials is true, returns common credentials
     * Otherwise returns module-specific credentials
     */
    public static function get_credentials($module) {
        $settings = self::get_all_settings();
        $use_common = self::get_setting('use_common_credentials', true);
        
        if ($use_common) {
            return array(
                'client_id' => self::get_setting('common_client_id', ''),
                'client_secret' => self::get_setting('common_client_secret', ''),
                'tenant_id' => self::get_setting('common_tenant_id', 'common')
            );
        }
        
        // Return module-specific credentials
        switch ($module) {
            case 'sso':
                return array(
                    'client_id' => self::get_setting('sso_client_id', ''),
                    'client_secret' => self::get_setting('sso_client_secret', ''),
                    'tenant_id' => self::get_setting('sso_tenant_id', 'common')
                );
            
            case 'backup':
                return array(
                    'client_id' => self::get_setting('backup_client_id', ''),
                    'client_secret' => self::get_setting('backup_client_secret', ''),
                    'tenant_id' => self::get_setting('backup_tenant_id', 'common')
                );
            
            case 'calendar':
                return array(
                    'client_id' => self::get_setting('calendar_client_id', ''),
                    'client_secret' => self::get_setting('calendar_client_secret', ''),
                    'tenant_id' => self::get_setting('calendar_tenant_id', 'common')
                );
            
            case 'email':
                return array(
                    'client_id' => self::get_setting('email_client_id', ''),
                    'client_secret' => self::get_setting('email_client_secret', ''),
                    'tenant_id' => self::get_setting('email_tenant_id', 'common')
                );
            
            case 'pta':
                return array(
                    'client_id' => self::get_setting('pta_client_id', ''),
                    'client_secret' => self::get_setting('pta_client_secret', ''),
                    'tenant_id' => self::get_setting('pta_tenant_id', 'common')
                );
            
            default:
                return array(
                    'client_id' => '',
                    'client_secret' => '',
                    'tenant_id' => 'common'
                );
        }
    }
    
    public static function is_module_enabled($module) {
        return self::get_setting("enable_{$module}", false);
    }
    
    public static function enable_module($module, $enabled = true) {
        return self::update_setting("enable_{$module}", $enabled);
    }
    
    public static function get_module_settings($module) {
        $settings = self::get_all_settings();
        $module_settings = array();
        
        $prefix = $module . '_';
        foreach ($settings as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $module_key = substr($key, strlen($prefix));
                $module_settings[$module_key] = $value;
            }
        }
        
        return $module_settings;
    }
    
    public static function validate_credentials($client_id, $client_secret, $tenant_id = 'common') {
        if (empty($client_id) || empty($client_secret)) {
            return array(
                'valid' => false,
                'message' => 'Client ID and Client Secret are required'
            );
        }
        
        // Basic format validation
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $client_id)) {
            return array(
                'valid' => false,
                'message' => 'Client ID must be a valid UUID format'
            );
        }
        
        // Test the credentials by making a basic auth request
        $auth_url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token";
        
        $response = wp_remote_post($auth_url, array(
            'body' => array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'valid' => false,
                'message' => 'Failed to connect to Microsoft: ' . $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return array(
                'valid' => false,
                'message' => 'Authentication failed: ' . $data['error_description']
            );
        }
        
        if (isset($data['access_token'])) {
            return array(
                'valid' => true,
                'message' => 'Credentials are valid'
            );
        }
        
        return array(
            'valid' => false,
            'message' => 'Unexpected response from Microsoft'
        );
    }
    
    public static function get_default_settings() {
        return array(
            // General settings
            'enable_sso' => false,
            'enable_backup' => false,
            'enable_calendar' => false,
            'enable_email' => false,
            'enable_pta' => false,
            
            // Common credentials
            'use_common_credentials' => true,
            'common_client_id' => '',
            'common_client_secret' => '',
            'common_tenant_id' => 'common',
            
            // SSO specific settings
            'sso_client_id' => '',
            'sso_client_secret' => '',
            'sso_tenant_id' => 'common',
            'sso_redirect_uri' => home_url('/wp-admin/admin-ajax.php?action=azure_sso_callback'),
            'sso_require_sso' => false,
            'sso_auto_create_users' => true,
            'sso_default_role' => 'subscriber',
            'sso_show_on_login_page' => true,
            'sso_use_custom_role' => false,
            'sso_custom_role_name' => 'AzureAD',
            'sso_sync_enabled' => false,
            'sso_sync_frequency' => 'daily',
            'sso_preserve_local_data' => false,
            
            // Backup specific settings
            'backup_client_id' => '',
            'backup_client_secret' => '',
            'backup_storage_account' => '',
            'backup_storage_key' => '',
            'backup_container_name' => 'wordpress-backups',
            'backup_types' => array('content', 'media', 'plugins', 'themes'),
            'backup_retention_days' => 30,
            'backup_max_execution_time' => 300,
            'backup_schedule_enabled' => false,
            'backup_schedule_frequency' => 'daily',
            'backup_schedule_time' => '02:00',
            'backup_email_notifications' => true,
            'backup_notification_email' => get_option('admin_email'),
            
            // Calendar specific settings
            'calendar_client_id' => '',
            'calendar_client_secret' => '',
            'calendar_tenant_id' => '',
            'calendar_default_timezone' => 'America/New_York',
            'calendar_default_view' => 'month',
            'calendar_default_color_theme' => 'blue',
            'calendar_cache_duration' => 3600,
            'calendar_max_events_per_calendar' => 100,
            
            // Email specific settings
            'email_client_id' => '',
            'email_client_secret' => '',
            'email_tenant_id' => '',
            'email_auth_method' => 'graph_api',
            'email_send_as_alias' => '',
            'email_override_wp_mail' => false,
            'email_hve_smtp_server' => 'smtp-hve.office365.com',
            'email_hve_smtp_port' => 587,
            'email_hve_username' => '',
            'email_hve_password' => '',
            'email_hve_from_email' => '',
            'email_hve_encryption' => 'tls',
            'email_hve_override_wp_mail' => false,
            'email_acs_connection_string' => '',
            'email_acs_endpoint' => '',
            'email_acs_access_key' => '',
            'email_acs_from_email' => '',
            'email_acs_display_name' => '',
            'email_acs_override_wp_mail' => false,
            
            // PTA specific settings
            'pta_client_id' => '',
            'pta_client_secret' => '',
            'pta_tenant_id' => 'common',
            'pta_sync_enabled' => true,
            'pta_sync_frequency' => 'hourly',
            'pta_auto_provision' => true,
            'pta_delete_azure_users' => true,
            'pta_welcome_email_enabled' => true,
            'pta_license_sku' => 'O365_BUSINESS_ESSENTIALS'
        );
    }
    
    public static function reset_to_defaults() {
        $defaults = self::get_default_settings();
        return update_option(self::$option_name, $defaults);
    }
    
    public static function export_settings() {
        $settings = self::get_all_settings();
        
        // Remove sensitive data for export
        $safe_settings = $settings;
        $sensitive_keys = array(
            'common_client_secret', 'sso_client_secret', 'backup_client_secret',
            'calendar_client_secret', 'email_client_secret', 'pta_client_secret',
            'backup_storage_key', 'email_hve_password', 'email_acs_access_key'
        );
        
        foreach ($sensitive_keys as $key) {
            if (isset($safe_settings[$key])) {
                $safe_settings[$key] = '***REDACTED***';
            }
        }
        
        return json_encode($safe_settings, JSON_PRETTY_PRINT);
    }
    
    public static function import_settings($json_data) {
        $imported_settings = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => 'Invalid JSON format'
            );
        }
        
        // Filter out redacted values
        foreach ($imported_settings as $key => $value) {
            if ($value === '***REDACTED***') {
                unset($imported_settings[$key]);
            }
        }
        
        $current_settings = self::get_all_settings();
        $merged_settings = array_merge($current_settings, $imported_settings);
        
        if (update_option(self::$option_name, $merged_settings)) {
            return array(
                'success' => true,
                'message' => 'Settings imported successfully'
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Failed to update settings'
        );
    }
}
