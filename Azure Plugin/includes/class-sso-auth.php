<?php
/**
 * SSO Authentication handler for Azure Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_SSO_Auth {
    
    private $settings;
    private $credentials;
    
    public function __construct() {
        $this->settings = Azure_Settings::get_all_settings();
        $this->credentials = Azure_Settings::get_credentials('sso');
        
        add_action('wp_ajax_nopriv_azure_sso_callback', array($this, 'handle_callback'));
        add_action('wp_ajax_azure_sso_callback', array($this, 'handle_callback'));
        add_action('init', array($this, 'check_sso_requirement'));
        add_action('wp_logout', array($this, 'handle_logout'));
        add_filter('authenticate', array($this, 'maybe_bypass_password_auth'), 30, 3);
        add_action('login_form', array($this, 'add_sso_login_button'));
    }
    
    /**
     * Generate the Azure AD authorization URL
     */
    public function get_authorization_url($state = null) {
        if (empty($this->credentials['client_id']) || empty($this->credentials['tenant_id'])) {
            Azure_Logger::error('SSO: Missing client credentials');
            return false;
        }
        
        $tenant_id = $this->credentials['tenant_id'] ?: 'common';
        $base_url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/authorize";
        
        $params = array(
            'client_id' => $this->credentials['client_id'],
            'response_type' => 'code',
            'redirect_uri' => home_url('/wp-admin/admin-ajax.php?action=azure_sso_callback'),
            'response_mode' => 'query',
            'scope' => 'openid profile email User.Read',
            'state' => $state ?: wp_create_nonce('azure_sso_state')
        );
        
        return $base_url . '?' . http_build_query($params);
    }
    
    /**
     * Handle the OAuth callback from Azure AD
     */
    public function handle_callback() {
        Azure_Logger::info('SSO: Handling OAuth callback');
        
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            Azure_Logger::error('SSO: Invalid callback parameters');
            wp_die('Invalid callback parameters');
        }
        
        $code = sanitize_text_field($_GET['code']);
        $state = sanitize_text_field($_GET['state']);
        
        // Parse state parameter (could be simple nonce or nonce|redirect_url)
        $redirect_url = admin_url();
        $nonce_to_verify = $state;
        
        if (strpos($state, '|') !== false) {
            // Composite state with redirect URL
            list($nonce_to_verify, $encoded_redirect) = explode('|', $state, 2);
            $decoded_redirect = base64_decode($encoded_redirect);
            
            // Validate the decoded redirect URL for security
            if ($decoded_redirect && filter_var($decoded_redirect, FILTER_VALIDATE_URL)) {
                $redirect_url = esc_url_raw($decoded_redirect);
            }
        }
        
        // Verify state parameter nonce
        if (!wp_verify_nonce($nonce_to_verify, 'azure_sso_state')) {
            Azure_Logger::error('SSO: Invalid state parameter: ' . $state);
            wp_die('Invalid state parameter');
        }
        
        // Exchange code for access token
        $token_data = $this->exchange_code_for_token($code);
        
        if (!$token_data) {
            Azure_Logger::error('SSO: Failed to get access token');
            wp_die('Failed to get access token');
        }
        
        // Get user info from Microsoft Graph
        $user_info = $this->get_user_info($token_data['access_token']);
        
        if (!$user_info) {
            Azure_Logger::error('SSO: Failed to get user information');
            wp_die('Failed to get user information');
        }
        
        // Check if user is in the exclusion list (service accounts, shared mailboxes, etc.)
        $azure_user_id = $user_info['id'] ?? '';
        $user_email = $user_info['mail'] ?? $user_info['userPrincipalName'] ?? '';
        
        if (class_exists('Azure_SSO_Sync') && Azure_SSO_Sync::is_user_excluded($azure_user_id, $user_email)) {
            $display_name = $user_info['displayName'] ?? $user_email;
            Azure_Logger::warning("SSO: Login blocked for excluded account: {$display_name} ({$user_email})");
            Azure_Database::log_activity('sso', 'login_blocked', 'user', null, array(
                'reason' => 'User in exclusion list',
                'email' => $user_email,
                'display_name' => $display_name,
                'azure_id' => $azure_user_id
            ));
            
            wp_die(
                '<h1>Access Denied</h1>' .
                '<p>This account (<strong>' . esc_html($user_email) . '</strong>) is not authorized to log in to this site.</p>' .
                '<p>This may be a service account or shared mailbox that has been excluded from user access.</p>' .
                '<p>If you believe this is an error, please contact the site administrator.</p>' .
                '<p><a href="' . esc_url(home_url()) . '">Return to homepage</a></p>',
                'Access Denied',
                array('response' => 403)
            );
        }
        
        // Process the user login
        $user_id = $this->process_user_login($user_info, $token_data);
        
        if ($user_id) {
            // Log the user in
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
            
            Azure_Logger::info('SSO: User logged in successfully: ' . ($user_info['mail'] ?? $user_info['userPrincipalName']));
            Azure_Database::log_activity('sso', 'user_login', 'user', $user_id, $user_info);
            
            // Use the redirect URL from state parameter
            wp_redirect($redirect_url);
            exit;
        } else {
            Azure_Logger::error('SSO: Failed to process user login');
            wp_die('Failed to process user login');
        }
    }
    
    /**
     * Exchange authorization code for access token
     */
    private function exchange_code_for_token($code) {
        $tenant_id = $this->credentials['tenant_id'] ?: 'common';
        $token_url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token";
        
        $body = array(
            'client_id' => $this->credentials['client_id'],
            'client_secret' => $this->credentials['client_secret'],
            'code' => $code,
            'redirect_uri' => home_url('/wp-admin/admin-ajax.php?action=azure_sso_callback'),
            'grant_type' => 'authorization_code',
            'scope' => 'openid profile email User.Read'
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $body,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
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
        
        return $token_data;
    }
    
    /**
     * Get user information from Microsoft Graph
     */
    private function get_user_info($access_token) {
        $graph_url = 'https://graph.microsoft.com/v1.0/me?$select=id,displayName,mail,userPrincipalName,givenName,surname,department,jobTitle';
        
        $response = wp_remote_get($graph_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            Azure_Logger::error('SSO: Graph API request failed - ' . $response->get_error_message());
            return false;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $user_info = json_decode($response_body, true);
        
        if (isset($user_info['error'])) {
            Azure_Logger::error('SSO: Graph API error - ' . $user_info['error']['message']);
            return false;
        }
        
        return $user_info;
    }
    
    /**
     * Process user login - create or update WordPress user
     */
    private function process_user_login($user_info, $token_data) {
        $azure_user_id = $user_info['id'];
        $email = $user_info['mail'] ?? $user_info['userPrincipalName'];
        $display_name = $user_info['displayName'];
        
        if (empty($email)) {
            Azure_Logger::error('SSO: No email found in user info');
            return false;
        }
        
        // Check if user already exists in our mapping table
        global $wpdb;
        $sso_users_table = Azure_Database::get_table_name('sso_users');
        
        $existing_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$sso_users_table} WHERE azure_user_id = %s",
            $azure_user_id
        ));
        
        if ($existing_mapping) {
            // User exists in mapping, get WordPress user
            $wp_user = get_user_by('ID', $existing_mapping->wordpress_user_id);
            
            if ($wp_user) {
                // Update last login time
                $wpdb->update(
                    $sso_users_table,
                    array('last_login' => current_time('mysql')),
                    array('id' => $existing_mapping->id),
                    array('%s'),
                    array('%d')
                );
                
                return $wp_user->ID;
            }
        }
        
        // Check if WordPress user exists by email
        $wp_user = get_user_by('email', $email);
        
        if (!$wp_user) {
            // Auto-create user if enabled
            if (Azure_Settings::get_setting('sso_auto_create_users', true)) {
                $user_login = $this->generate_username($email, $display_name);
                $user_pass = wp_generate_password(20, true, true);
                
                $user_id = wp_create_user($user_login, $user_pass, $email);
                
                if (is_wp_error($user_id)) {
                    Azure_Logger::error('SSO: Failed to create user - ' . $user_id->get_error_message());
                    return false;
                }
                
                // Update user meta and assign AzureAD role
                wp_update_user(array(
                    'ID' => $user_id,
                    'display_name' => $display_name,
                    'first_name' => $user_info['givenName'] ?? '',
                    'last_name' => $user_info['surname'] ?? '',
                    'role' => $this->get_sso_role()  // Use configured SSO role
                ));
                
                // Map department from Azure AD to WordPress user meta
                if (!empty($user_info['department'])) {
                    update_user_meta($user_id, 'department', sanitize_text_field($user_info['department']));
                    Azure_Logger::debug("SSO: Set department '{$user_info['department']}' for user {$user_id}");
                }
                
                // Store Azure AD jobTitle for reference (but don't auto-assign)
                if (!empty($user_info['jobTitle'])) {
                    update_user_meta($user_id, 'azure_job_title', sanitize_text_field($user_info['jobTitle']));
                }
                
                $wp_user = get_user_by('ID', $user_id);
                
                Azure_Logger::info('SSO: Created new user: ' . $email);
            } else {
                Azure_Logger::error('SSO: Auto-create users disabled and user not found: ' . $email);
                return false;
            }
        }
        
        // Create or update SSO mapping
        if ($existing_mapping) {
            $wpdb->update(
                $sso_users_table,
                array(
                    'wordpress_user_id' => $wp_user->ID,
                    'azure_email' => $email,
                    'azure_display_name' => $display_name,
                    'last_login' => current_time('mysql')
                ),
                array('id' => $existing_mapping->id),
                array('%d', '%s', '%s', '%s'),
                array('%d')
            );
            
            // Store Azure AD jobTitle for reference (but don't auto-assign)
            if (!empty($user_info['jobTitle'])) {
                update_user_meta($wp_user->ID, 'azure_job_title', sanitize_text_field($user_info['jobTitle']));
            }
        } else {
            $wpdb->insert(
                $sso_users_table,
                array(
                    'wordpress_user_id' => $wp_user->ID,
                    'azure_user_id' => $azure_user_id,
                    'azure_email' => $email,
                    'azure_display_name' => $display_name,
                    'last_login' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s')
            );
        }
        
        return $wp_user->ID;
    }
    
    /**
     * Generate a unique username from email and display name
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
     * Check if SSO is required
     */
    public function check_sso_requirement() {
        if (!Azure_Settings::get_setting('sso_require_sso', false)) {
            return;
        }
        
        // Skip for AJAX, cron, CLI
        if (wp_doing_ajax() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
            return;
        }
        
        // Skip if user is already logged in
        if (is_user_logged_in()) {
            return;
        }
        
        // Skip for wp-login.php to avoid infinite redirect
        if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
            return;
        }
        
        // Skip for SSO callback
        if (strpos($_SERVER['REQUEST_URI'], 'azure_sso_callback') !== false) {
            return;
        }
        
        // Redirect to SSO
        $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $state = wp_create_nonce('azure_sso_state') . '|' . base64_encode($current_url);
        $sso_url = $this->get_authorization_url($state);
        
        if ($sso_url) {
            wp_redirect($sso_url);
            exit;
        }
    }
    
    /**
     * Add SSO login button to login form
     */
    public function add_sso_login_button() {
        if (!Azure_Settings::get_setting('sso_show_on_login_page', true)) {
            return;
        }
        
        // Check if button has already been rendered to avoid duplicates
        static $button_rendered = false;
        if ($button_rendered) {
            return;
        }
        $button_rendered = true;
        
        $sso_url = $this->get_authorization_url();
        
        if (!$sso_url) {
            return;
        }
        
        // Get custom button text from settings
        $button_text = Azure_Settings::get_setting('sso_login_button_text', 'Sign in with Microsoft');
        
        echo '<div style="margin: 20px 0; text-align: center;">';
        echo '<a href="' . esc_url($sso_url) . '" class="button button-primary" style="width: 100%; padding: 10px; font-size: 14px;">';
        echo '<span class="dashicons dashicons-admin-users" style="vertical-align: middle; margin-right: 5px;"></span>';
        echo esc_html($button_text);
        echo '</a>';
        echo '</div>';
        
        echo '<div style="margin: 20px 0; text-align: center; color: #666; position: relative;">';
        echo '<hr style="border: none; border-top: 1px solid #ddd; margin: 0;">';
        echo '<span style="background: #fff; padding: 0 15px; position: absolute; top: -10px; left: 50%; transform: translateX(-50%);">OR</span>';
        echo '</div>';
    }
    
    /**
     * Maybe bypass password authentication for SSO users
     */
    public function maybe_bypass_password_auth($user, $username, $password) {
        // If already authenticated or not our concern, return
        if (is_wp_error($user) || !$user) {
            return $user;
        }
        
        return $user;
    }
    
    /**
     * Handle logout
     */
    public function handle_logout() {
        Azure_Database::log_activity('sso', 'user_logout', 'user', get_current_user_id());
    }
    
    /**
     * Get SSO login URL
     */
    public function get_login_url($redirect_to = '') {
        $state = wp_create_nonce('azure_sso_state');
        
        if ($redirect_to) {
            $state .= '|' . base64_encode($redirect_to);
        }
        
        return $this->get_authorization_url($state);
    }
    
    /**
     * Test SSO connection with provided credentials
     */
    public function test_connection($client_id = null, $client_secret = null, $tenant_id = null) {
        // Use provided credentials or fall back to instance credentials
        $test_client_id = $client_id ?: $this->credentials['client_id'];
        $test_client_secret = $client_secret ?: $this->credentials['client_secret'];
        $test_tenant_id = $tenant_id ?: ($this->credentials['tenant_id'] ?: 'common');
        
        if (empty($test_client_id) || empty($test_client_secret) || empty($test_tenant_id)) {
            return array(
                'success' => false,
                'message' => 'Missing required SSO credentials (Client ID, Client Secret, or Tenant ID)'
            );
        }
        
        try {
            // Test by attempting to get the OAuth authorization endpoint
            $auth_url = "https://login.microsoftonline.com/{$test_tenant_id}/oauth2/v2.0/authorize";
            
            // Make a HEAD request to validate the tenant and endpoint
            $response = wp_remote_head($auth_url, array(
                'timeout' => 10,
                'headers' => array(
                    'User-Agent' => 'WordPress-Azure-Plugin/1.0'
                )
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Failed to connect to Azure AD: ' . $response->get_error_message()
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            
            // Azure AD authorization endpoint should return 200 for HEAD requests
            if ($response_code === 200) {
                // Additional validation: try to get tenant-specific metadata
                $metadata_url = "https://login.microsoftonline.com/{$test_tenant_id}/v2.0/.well-known/openid_configuration";
                $metadata_response = wp_remote_get($metadata_url, array(
                    'timeout' => 10
                ));
                
                if (!is_wp_error($metadata_response) && wp_remote_retrieve_response_code($metadata_response) === 200) {
                    $metadata_body = wp_remote_retrieve_body($metadata_response);
                    $metadata = json_decode($metadata_body, true);
                    
                    if ($metadata && isset($metadata['authorization_endpoint'])) {
                        return array(
                            'success' => true,
                            'message' => 'SSO connection successful! Azure AD tenant "' . $test_tenant_id . '" is accessible and properly configured.'
                        );
                    }
                }
                
                return array(
                    'success' => true,
                    'message' => 'SSO connection successful! Azure AD endpoint is accessible.'
                );
            } else if ($response_code === 404) {
                return array(
                    'success' => false,
                    'message' => 'Invalid Tenant ID: "' . $test_tenant_id . '" not found. Please check your tenant ID configuration.'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Azure AD connection failed with HTTP ' . $response_code . '. Please check your credentials and network connection.'
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'SSO test failed: ' . $e->getMessage()
            );
        }
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
}