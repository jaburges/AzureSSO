<?php
/**
 * SSO Sync handler for Azure Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_SSO_Sync {
    
    public function __construct() {
        add_action('wp_ajax_azure_sync_users', array($this, 'sync_users_ajax'));
        add_action('azure_sso_scheduled_sync', array($this, 'scheduled_sync'));
        
        // Schedule sync if enabled
        $this->setup_scheduled_sync();
    }
    
    /**
     * Setup scheduled sync
     */
    private function setup_scheduled_sync() {
        $sync_enabled = Azure_Settings::get_setting('sso_sync_enabled', false);
        $sync_frequency = Azure_Settings::get_setting('sso_sync_frequency', 'daily');
        
        // Clear existing schedule
        wp_clear_scheduled_hook('azure_sso_scheduled_sync');
        
        if ($sync_enabled) {
            if (!wp_next_scheduled('azure_sso_scheduled_sync')) {
                wp_schedule_event(time(), $sync_frequency, 'azure_sso_scheduled_sync');
            }
        }
    }
    
    /**
     * AJAX handler for manual user sync
     */
    public function sync_users_ajax() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_die('Unauthorized access');
        }
        
        $result = $this->sync_users();
        
        wp_send_json($result);
    }
    
    /**
     * Scheduled sync callback
     */
    public function scheduled_sync() {
        Azure_Logger::info('SSO: Starting scheduled user sync');
        $result = $this->sync_users();
        
        if ($result['success']) {
            Azure_Logger::info('SSO: Scheduled sync completed successfully');
        } else {
            Azure_Logger::error('SSO: Scheduled sync failed: ' . $result['message']);
        }
    }
    
    /**
     * Sync users with Azure AD
     */
    public function sync_users() {
        try {
            $credentials = Azure_Settings::get_credentials('sso');
            
            if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
                return array(
                    'success' => false,
                    'message' => 'SSO credentials not configured'
                );
            }
            
            // Get access token for application permissions
            $access_token = $this->get_app_access_token($credentials);
            
            if (!$access_token) {
                return array(
                    'success' => false,
                    'message' => 'Failed to get access token'
                );
            }
            
            // Get all users from Azure AD
            $azure_users = $this->get_azure_users($access_token);
            
            if (!$azure_users) {
                return array(
                    'success' => false,
                    'message' => 'Failed to retrieve users from Azure AD'
                );
            }
            
            $total_users = count($azure_users);
            $sync_stats = array(
                'successful' => 0,
                'created' => 0,
                'updated' => 0,
                'linked' => 0,
                'skipped' => 0,
                'errors' => 0,
                'total' => $total_users
            );
            
            $detailed_results = array(
                'successful' => array(),
                'skipped' => array(),
                'errors' => array()
            );
            
            Azure_Logger::info("SSO: Starting sync of $total_users Azure AD users");
            
            $processed = 0;
            // Sync each user
            foreach ($azure_users as $azure_user) {
                $processed++;
                $email = $azure_user['mail'] ?? $azure_user['userPrincipalName'] ?? 'Unknown';
                
                // Log progress every 10 users
                if ($processed % 10 === 0 || $processed === $total_users) {
                    $percentage = round(($processed / $total_users) * 100);
                    Azure_Logger::info("SSO: Sync progress: $processed/$total_users ($percentage%)");
                }
                
                $result = $this->sync_single_user($azure_user);
                
                // Handle new array return format
                if (is_array($result)) {
                    $status = $result['status'];
                    $message = $result['message'];
                } else {
                    // Handle old string format for backward compatibility
                    $status = $result;
                    $message = "User '$email': $status";
                }
                
                switch ($status) {
                    case 'updated':
                        $sync_stats['updated']++;
                        $sync_stats['successful']++;
                        $detailed_results['successful'][] = $message;
                        break;
                    case 'created':
                        $sync_stats['created']++;
                        $sync_stats['successful']++;
                        $detailed_results['successful'][] = $message;
                        break;
                    case 'linked':
                        $sync_stats['linked']++;
                        $sync_stats['successful']++;
                        $detailed_results['successful'][] = $message;
                        break;
                    case 'skipped':
                        $sync_stats['skipped']++;
                        $detailed_results['skipped'][] = $message;
                        Azure_Logger::warning("SSO: $message");
                        break;
                    case 'error':
                    default:
                        $sync_stats['errors']++;
                        $detailed_results['errors'][] = $message;
                        Azure_Logger::error("SSO: $message");
                        break;
                }
            }
            
            // Log final results
            Azure_Logger::info("SSO: Sync completed - Successful: {$sync_stats['successful']}, Skipped: {$sync_stats['skipped']}, Errors: {$sync_stats['errors']}");
            
            // Store detailed results including individual messages for widgets
            $complete_results = array_merge($sync_stats, array(
                'detailed_results' => $detailed_results
            ));
            
            Azure_Database::log_activity('sso', 'users_synced', 'sync', null, $complete_results);
            
            return array(
                'success' => true,
                'message' => sprintf(
                    'Sync completed. Successful: %d (Created: %d, Updated: %d, Linked: %d), Skipped: %d, Errors: %d',
                    $sync_stats['successful'],
                    $sync_stats['created'], 
                    $sync_stats['updated'],
                    $sync_stats['linked'],
                    $sync_stats['skipped'],
                    $sync_stats['errors']
                ),
                'stats' => $sync_stats,
                'details' => $detailed_results
            );
            
        } catch (Exception $e) {
            Azure_Logger::error('SSO: Sync error: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Get application access token
     */
    private function get_app_access_token($credentials) {
        $tenant_id = $credentials['tenant_id'] ?: 'common';
        $token_url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token";
        
        $body = array(
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials'
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $body,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            Azure_Logger::error('SSO: Token request failed - ' . $response->get_error_message());
            return false;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $token_data = json_decode($response_body, true);
        
        if (isset($token_data['error'])) {
            Azure_Logger::error('SSO: Token error - ' . $token_data['error_description']);
            return false;
        }
        
        return $token_data['access_token'] ?? false;
    }
    
    /**
     * Get all users from Azure AD
     */
    private function get_azure_users($access_token) {
        $users = array();
        $next_link = 'https://graph.microsoft.com/v1.0/users?$select=id,displayName,mail,userPrincipalName,givenName,surname,accountEnabled';
        
        do {
            $response = wp_remote_get($next_link, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                Azure_Logger::error('SSO: Users request failed - ' . $response->get_error_message());
                return false;
            }
            
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);
            
            if (isset($data['error'])) {
                Azure_Logger::error('SSO: Users API error - ' . $data['error']['message']);
                return false;
            }
            
            if (isset($data['value'])) {
                $users = array_merge($users, $data['value']);
            }
            
            $next_link = $data['@odata.nextLink'] ?? null;
            
        } while ($next_link);
        
        return $users;
    }
    
    /**
     * Sync a single user
     */
    private function sync_single_user($azure_user) {
        global $wpdb;
        
        // Skip disabled accounts
        if (!($azure_user['accountEnabled'] ?? true)) {
            return 'skipped';
        }
        
        $azure_user_id = $azure_user['id'];
        $email = $azure_user['mail'] ?? $azure_user['userPrincipalName'];
        $display_name = $azure_user['displayName'];
        
        if (empty($email)) {
            Azure_Logger::warning('SSO: No email for user: ' . $azure_user_id);
            return 'error';
        }
        
        $sso_users_table = Azure_Database::get_table_name('sso_users');
        
        // Check if mapping exists
        $existing_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$sso_users_table} WHERE azure_user_id = %s",
            $azure_user_id
        ));
        
        if ($existing_mapping) {
            $preserve_local_data = Azure_Settings::get_setting('sso_preserve_local_data', false);
            
            // Update existing mapping
            $wpdb->update(
                $sso_users_table,
                array(
                    'azure_email' => $email,
                    'azure_display_name' => $display_name,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing_mapping->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            // Update WordPress user if exists and preserve_local_data is disabled
            $wp_user = get_user_by('ID', $existing_mapping->wordpress_user_id);
            if ($wp_user) {
                if (!$preserve_local_data) {
                    wp_update_user(array(
                        'ID' => $wp_user->ID,
                        'display_name' => $display_name,
                        'first_name' => $azure_user['givenName'] ?? '',
                        'last_name' => $azure_user['surname'] ?? ''
                    ));
                    Azure_Logger::info("SSO: Updated existing mapped user '$email' with Azure AD data");
                } else {
                    Azure_Logger::info("SSO: Preserved local data for mapped user '$email' (preserve_local_data enabled)");
                }
            }
            
            return array('status' => 'updated', 'message' => "Updated existing mapping for '$email'");
        }
        
        // Check if auto-create is enabled
        if (!Azure_Settings::get_setting('sso_auto_create_users', true)) {
            return array('status' => 'skipped', 'message' => "Skipped user '$email' (auto-create disabled)");
        }
        
        // Check if WordPress user exists by email
        $wp_user = get_user_by('email', $email);
        $preserve_local_data = Azure_Settings::get_setting('sso_preserve_local_data', false);
        
        if (!$wp_user) {
            // Create new WordPress user
            $username = $this->generate_username($email, $display_name);
            $password = wp_generate_password(20, true, true);
            
            $user_id = wp_create_user($username, $password, $email);
            
            if (is_wp_error($user_id)) {
                Azure_Logger::error('SSO: Failed to create user - ' . $user_id->get_error_message());
                return array('status' => 'error', 'message' => $user_id->get_error_message());
            }
            
            // Determine the role to assign
            $role_to_assign = $this->get_sso_role();
            
            // Update user data
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $display_name,
                'first_name' => $azure_user['givenName'] ?? '',
                'last_name' => $azure_user['surname'] ?? '',
                'role' => $role_to_assign
            ));
            
            $wp_user = get_user_by('ID', $user_id);
            Azure_Logger::info("SSO: Created new user '$email' with role '$role_to_assign'");
            
        } else {
            // User exists by email - link to Azure AD
            Azure_Logger::info("SSO: Linking existing user '$email' to Azure AD");
            
            if (!$preserve_local_data) {
                // Update user data with Azure AD info
                wp_update_user(array(
                    'ID' => $wp_user->ID,
                    'display_name' => $display_name,
                    'first_name' => $azure_user['givenName'] ?? '',
                    'last_name' => $azure_user['surname'] ?? ''
                ));
                Azure_Logger::info("SSO: Updated existing user '$email' with Azure AD data");
            } else {
                Azure_Logger::info("SSO: Preserved local data for existing user '$email' (preserve_local_data enabled)");
            }
        }
        
        // Create SSO mapping
        $wpdb->insert(
            $sso_users_table,
            array(
                'wordpress_user_id' => $wp_user->ID,
                'azure_user_id' => $azure_user_id,
                'azure_email' => $email,
                'azure_display_name' => $display_name
            ),
            array('%d', '%s', '%s', '%s')
        );
        
        if ($existing_mapping) {
            return array('status' => 'updated', 'message' => "Updated user '$email'");
        } else {
            $status = isset($user_id) ? 'created' : 'linked';
            $message = isset($user_id) ? "Created new user '$email'" : "Linked existing user '$email'";
            return array('status' => $status, 'message' => $message);
        }
    }
    
    /**
     * Generate unique username
     */
    private function generate_username($email, $display_name) {
        // Try email prefix first
        $username = sanitize_user(substr($email, 0, strpos($email, '@')));
        
        if (!username_exists($username)) {
            return $username;
        }
        
        // Try display name
        $username = sanitize_user(strtolower(str_replace(' ', '', $display_name)));
        
        if (!username_exists($username)) {
            return $username;
        }
        
        // Add numbers until unique
        $base_username = $username;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * Get the role to assign to SSO users (custom or default)
     */
    private function get_sso_role() {
        $use_custom_role = Azure_Settings::get_setting('sso_use_custom_role', false);
        
        if ($use_custom_role) {
            $custom_role_name = Azure_Settings::get_setting('sso_custom_role_name', 'AzureAD');
            
            // Sanitize the role name (WordPress role names should be lowercase with underscores)
            $role_slug = sanitize_key(strtolower($custom_role_name));
            $role_display_name = sanitize_text_field($custom_role_name);
            
            // Check if the custom role exists, if not create it
            if (!get_role($role_slug)) {
                Azure_Logger::info("SSO: Creating custom role '$role_display_name' with slug '$role_slug'");
                
                // Create role with basic subscriber capabilities
                $subscriber_role = get_role('subscriber');
                $capabilities = $subscriber_role ? $subscriber_role->capabilities : array(
                    'read' => true,
                    'level_0' => true
                );
                
                // Add some identifying capabilities
                $capabilities['azure_ad_user'] = true;
                
                add_role($role_slug, $role_display_name, $capabilities);
                
                Azure_Logger::info("SSO: Custom role '$role_display_name' created successfully");
            }
            
            return $role_slug;
        }
        
        // Use standard WordPress role
        return Azure_Settings::get_setting('sso_default_role', 'subscriber');
    }
    
    /**
     * Get sync statistics
     */
    public function get_sync_stats() {
        global $wpdb;
        
        $sso_users_table = Azure_Database::get_table_name('sso_users');
        
        if (!$sso_users_table) {
            return false;
        }
        
        $total_mappings = $wpdb->get_var("SELECT COUNT(*) FROM {$sso_users_table}");
        
        $recent_logins = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$sso_users_table} WHERE last_login > %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        return array(
            'total_mappings' => intval($total_mappings),
            'recent_logins' => intval($recent_logins),
            'last_sync' => get_option('azure_sso_last_sync', 'Never')
        );
    }
}
?>
