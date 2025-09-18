<?php
/**
 * Calendar authentication handler for Azure Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Calendar_Auth {
    
    private $settings;
    private $credentials;
    
    public function __construct() {
        $this->settings = Azure_Settings::get_all_settings();
        $this->credentials = Azure_Settings::get_credentials('calendar');
        
        // AJAX handlers for OAuth flow
        add_action('wp_ajax_nopriv_azure_calendar_callback', array($this, 'handle_callback'));
        add_action('wp_ajax_azure_calendar_callback', array($this, 'handle_callback'));
        add_action('wp_ajax_azure_calendar_authorize', array($this, 'ajax_authorize'));
        add_action('wp_ajax_azure_calendar_revoke', array($this, 'ajax_revoke_token'));
    }
    
    /**
     * Get authorization URL for Microsoft Graph API
     */
    public function get_authorization_url($state = null) {
        if (empty($this->credentials['client_id']) || empty($this->credentials['tenant_id'])) {
            Azure_Logger::error('Calendar Auth: Missing client credentials');
            return false;
        }
        
        $tenant_id = $this->credentials['tenant_id'] ?: 'common';
        $base_url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/authorize";
        
        $params = array(
            'client_id' => $this->credentials['client_id'],
            'response_type' => 'code',
            'redirect_uri' => home_url('/wp-admin/admin-ajax.php?action=azure_calendar_callback'),
            'response_mode' => 'query',
            'scope' => 'https://graph.microsoft.com/Calendar.Read https://graph.microsoft.com/Calendar.ReadWrite offline_access',
            'state' => $state ?: wp_create_nonce('azure_calendar_state')
        );
        
        return $base_url . '?' . http_build_query($params);
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_callback() {
        Azure_Logger::info('Calendar Auth: Handling OAuth callback');
        
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            Azure_Logger::error('Calendar Auth: Invalid callback parameters');
            wp_die('Invalid callback parameters');
        }
        
        $code = sanitize_text_field($_GET['code']);
        $state = sanitize_text_field($_GET['state']);
        
        // Verify state parameter
        if (!wp_verify_nonce($state, 'azure_calendar_state')) {
            Azure_Logger::error('Calendar Auth: Invalid state parameter');
            wp_die('Invalid state parameter');
        }
        
        // Exchange code for access token
        $token_data = $this->exchange_code_for_token($code);
        
        if (!$token_data) {
            Azure_Logger::error('Calendar Auth: Failed to get access token');
            wp_die('Failed to get access token');
        }
        
        // Store tokens
        $this->store_tokens($token_data);
        
        Azure_Logger::info('Calendar Auth: Authorization completed successfully');
        Azure_Database::log_activity('calendar', 'authorization_completed', 'auth', null);
        
        // Redirect back to calendar settings
        wp_redirect(admin_url('admin.php?page=azure-plugin-calendar&auth=success'));
        exit;
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
            'redirect_uri' => home_url('/wp-admin/admin-ajax.php?action=azure_calendar_callback'),
            'grant_type' => 'authorization_code',
            'scope' => 'https://graph.microsoft.com/Calendar.Read https://graph.microsoft.com/Calendar.ReadWrite offline_access'
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $body,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            Azure_Logger::error('Calendar Auth: Token request failed - ' . $response->get_error_message());
            return false;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $token_data = json_decode($response_body, true);
        
        if (isset($token_data['error'])) {
            Azure_Logger::error('Calendar Auth: Token error - ' . $token_data['error_description']);
            return false;
        }
        
        return $token_data;
    }
    
    /**
     * Store access and refresh tokens
     */
    private function store_tokens($token_data) {
        $expires_at = time() + ($token_data['expires_in'] ?? 3600);
        
        $token_info = array(
            'access_token' => $token_data['access_token'],
            'refresh_token' => $token_data['refresh_token'] ?? '',
            'expires_at' => $expires_at,
            'token_type' => $token_data['token_type'] ?? 'Bearer',
            'scope' => $token_data['scope'] ?? ''
        );
        
        update_option('azure_calendar_tokens', $token_info);
        
        Azure_Logger::info('Calendar Auth: Tokens stored successfully');
    }
    
    /**
     * Get valid access token (refresh if needed)
     */
    public function get_access_token() {
        $tokens = get_option('azure_calendar_tokens', array());
        
        if (empty($tokens['access_token'])) {
            return false;
        }
        
        // Check if token is expired
        if (time() >= ($tokens['expires_at'] ?? 0)) {
            Azure_Logger::info('Calendar Auth: Access token expired, refreshing...');
            
            if (!empty($tokens['refresh_token'])) {
                $new_tokens = $this->refresh_access_token($tokens['refresh_token']);
                
                if ($new_tokens) {
                    $tokens = $new_tokens;
                } else {
                    Azure_Logger::error('Calendar Auth: Failed to refresh token');
                    return false;
                }
            } else {
                Azure_Logger::error('Calendar Auth: No refresh token available');
                return false;
            }
        }
        
        return $tokens['access_token'];
    }
    
    /**
     * Refresh access token using refresh token
     */
    private function refresh_access_token($refresh_token) {
        $tenant_id = $this->credentials['tenant_id'] ?: 'common';
        $token_url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token";
        
        $body = array(
            'client_id' => $this->credentials['client_id'],
            'client_secret' => $this->credentials['client_secret'],
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token',
            'scope' => 'https://graph.microsoft.com/Calendar.Read https://graph.microsoft.com/Calendar.ReadWrite offline_access'
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $body,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            Azure_Logger::error('Calendar Auth: Token refresh failed - ' . $response->get_error_message());
            return false;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $token_data = json_decode($response_body, true);
        
        if (isset($token_data['error'])) {
            Azure_Logger::error('Calendar Auth: Token refresh error - ' . $token_data['error_description']);
            return false;
        }
        
        // Store new tokens
        $this->store_tokens($token_data);
        
        return get_option('azure_calendar_tokens', array());
    }
    
    /**
     * Check if user is authenticated
     */
    public function is_authenticated() {
        $tokens = get_option('azure_calendar_tokens', array());
        return !empty($tokens['access_token']) && !empty($tokens['refresh_token']);
    }
    
    /**
     * Get authentication status
     */
    public function get_auth_status() {
        $tokens = get_option('azure_calendar_tokens', array());
        
        if (empty($tokens['access_token'])) {
            return array(
                'authenticated' => false,
                'message' => 'Not authenticated'
            );
        }
        
        $expires_at = $tokens['expires_at'] ?? 0;
        $expires_in = $expires_at - time();
        
        if ($expires_in <= 0) {
            return array(
                'authenticated' => true,
                'expired' => true,
                'message' => 'Token expired',
                'expires_at' => date('Y-m-d H:i:s', $expires_at)
            );
        }
        
        return array(
            'authenticated' => true,
            'expired' => false,
            'message' => 'Authenticated',
            'expires_at' => date('Y-m-d H:i:s', $expires_at),
            'expires_in_hours' => round($expires_in / 3600, 2)
        );
    }
    
    /**
     * Revoke access tokens
     */
    public function revoke_tokens() {
        delete_option('azure_calendar_tokens');
        Azure_Logger::info('Calendar Auth: Tokens revoked');
        Azure_Database::log_activity('calendar', 'tokens_revoked', 'auth', null);
    }
    
    /**
     * AJAX handler for authorization
     */
    public function ajax_authorize() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_die('Unauthorized access');
        }
        
        $auth_url = $this->get_authorization_url();
        
        if ($auth_url) {
            wp_send_json_success(array('auth_url' => $auth_url));
        } else {
            wp_send_json_error('Failed to generate authorization URL. Check your credentials.');
        }
    }
    
    /**
     * AJAX handler for revoking tokens
     */
    public function ajax_revoke_token() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_die('Unauthorized access');
        }
        
        $this->revoke_tokens();
        
        wp_send_json_success('Authorization revoked successfully');
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        $access_token = $this->get_access_token();
        
        if (!$access_token) {
            return array(
                'success' => false,
                'message' => 'No valid access token available'
            );
        }
        
        // Try to get user calendars to test connection
        $response = wp_remote_get('https://graph.microsoft.com/v1.0/me/calendars', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            $calendar_count = isset($data['value']) ? count($data['value']) : 0;
            
            return array(
                'success' => true,
                'message' => 'Connection successful',
                'calendar_count' => $calendar_count
            );
        } else {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            
            return array(
                'success' => false,
                'message' => 'API Error (Status: ' . $response_code . '): ' . ($error_data['error']['message'] ?? 'Unknown error')
            );
        }
    }
    
    /**
     * Get user calendars
     */
    public function get_user_calendars() {
        $access_token = $this->get_access_token();
        
        if (!$access_token) {
            return false;
        }
        
        $response = wp_remote_get('https://graph.microsoft.com/v1.0/me/calendars', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            Azure_Logger::error('Calendar Auth: Failed to get calendars - ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            Azure_Logger::error('Calendar Auth: Calendar request failed with status ' . $response_code);
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data['value'] ?? array();
    }
    
    /**
     * Schedule token refresh
     */
    public function schedule_token_refresh() {
        if (!wp_next_scheduled('azure_calendar_token_refresh')) {
            wp_schedule_event(time() + 3600, 'hourly', 'azure_calendar_token_refresh');
        }
        
        add_action('azure_calendar_token_refresh', array($this, 'refresh_token_if_needed'));
    }
    
    /**
     * Refresh token if needed (scheduled task)
     */
    public function refresh_token_if_needed() {
        $tokens = get_option('azure_calendar_tokens', array());
        
        if (empty($tokens['access_token']) || empty($tokens['refresh_token'])) {
            return;
        }
        
        $expires_at = $tokens['expires_at'] ?? 0;
        $refresh_threshold = $expires_at - 1800; // Refresh 30 minutes before expiry
        
        if (time() >= $refresh_threshold) {
            Azure_Logger::info('Calendar Auth: Refreshing token proactively');
            $this->refresh_access_token($tokens['refresh_token']);
        }
    }
}
?>


