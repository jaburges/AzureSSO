<?php
/**
 * OneDrive Media Manager - Main Orchestration Class
 * Manages WordPress Media Library integration with OneDrive/SharePoint
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_OneDrive_Media_Manager {
    
    private static $instance = null;
    private $auth;
    private $graph_api;
    private $enabled;
    
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new Azure_OneDrive_Media_Manager();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Check if module is enabled
        $this->enabled = Azure_Settings::is_module_enabled('onedrive_media');
        
        if (!$this->enabled) {
            return;
        }
        
        // Initialize dependencies
        if (class_exists('Azure_OneDrive_Media_Auth')) {
            $this->auth = new Azure_OneDrive_Media_Auth();
        }
        
        if (class_exists('Azure_OneDrive_Media_GraphAPI')) {
            $this->graph_api = new Azure_OneDrive_Media_GraphAPI();
        }
        
        // Hook into WordPress media upload
        add_filter('wp_handle_upload_prefilter', array($this, 'intercept_upload'), 10, 1);
        add_filter('wp_handle_upload', array($this, 'handle_upload_to_onedrive'), 10, 2);
        add_action('delete_attachment', array($this, 'handle_delete_attachment'), 10, 1);
        
        // Add custom fields to attachment
        add_filter('attachment_fields_to_edit', array($this, 'add_onedrive_fields'), 10, 2);
        add_filter('wp_get_attachment_url', array($this, 'filter_attachment_url'), 10, 2);
        add_filter('wp_get_attachment_image_src', array($this, 'filter_attachment_image_src'), 10, 4);
        
        // Register AJAX handlers
        add_action('wp_ajax_onedrive_media_sync_from_onedrive', array($this, 'ajax_sync_from_onedrive'));
        add_action('wp_ajax_onedrive_media_browse_folders', array($this, 'ajax_browse_folders'));
        add_action('wp_ajax_onedrive_media_create_folder', array($this, 'ajax_create_folder'));
        add_action('wp_ajax_onedrive_media_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_onedrive_media_list_sharepoint_sites', array($this, 'ajax_list_sharepoint_sites'));
        add_action('wp_ajax_onedrive_media_list_sharepoint_drives', array($this, 'ajax_list_sharepoint_drives'));
        add_action('wp_ajax_onedrive_media_resolve_sharepoint_site', array($this, 'ajax_resolve_sharepoint_site'));
        add_action('wp_ajax_onedrive_media_create_year_folders', array($this, 'ajax_create_year_folders'));
        
        // Schedule WordPress Cron for auto-sync
        if (!wp_next_scheduled('onedrive_media_auto_sync')) {
            $frequency = Azure_Settings::get_setting('onedrive_media_sync_frequency', 'hourly');
            wp_schedule_event(time(), $frequency, 'onedrive_media_auto_sync');
        }
        add_action('onedrive_media_auto_sync', array($this, 'run_auto_sync'));
    }
    
    /**
     * Intercept file upload before processing
     */
    public function intercept_upload($file) {
        // Validate file before upload
        $max_size = Azure_Settings::get_setting('onedrive_media_max_file_size', 4294967296); // 4GB default
        
        if ($file['size'] > $max_size) {
            $file['error'] = 'File size exceeds OneDrive limit';
            return $file;
        }
        
        return $file;
    }
    
    /**
     * Handle file upload to OneDrive after WordPress processes it
     */
    public function handle_upload_to_onedrive($upload, $context) {
        if (!$this->graph_api) {
            return $upload;
        }
        
        $local_file = $upload['file'];
        $file_name = basename($local_file);
        
        // Determine folder based on year setting
        $use_year_folders = Azure_Settings::get_setting('onedrive_media_use_year_folders', true);
        $base_folder = Azure_Settings::get_setting('onedrive_media_base_folder', 'WordPress Media');
        
        if ($use_year_folders) {
            $year = date('Y');
            $remote_path = $base_folder . '/' . $year;
        } else {
            $remote_path = $base_folder;
        }
        
        // Upload to OneDrive
        $file_data = $this->graph_api->upload_file($local_file, $remote_path, $file_name);
        
        if ($file_data) {
            // Store file metadata
            $this->store_file_mapping(null, $file_data, $local_file);
            
            // Generate public URL
            $public_url = $this->graph_api->create_sharing_link($file_data['id'], 'view', 'anonymous');
            
            if ($public_url) {
                // Update file mapping with public URL
                global $wpdb;
                $table = Azure_Database::get_table_name('onedrive_files');
                $wpdb->update(
                    $table,
                    array('public_url' => $public_url),
                    array('onedrive_id' => $file_data['id']),
                    array('%s'),
                    array('%s')
                );
            }
            
            // Get thumbnail
            $thumbnails = $this->graph_api->get_thumbnails($file_data['id']);
            if ($thumbnails && !empty($thumbnails['large'])) {
                global $wpdb;
                $table = Azure_Database::get_table_name('onedrive_files');
                $wpdb->update(
                    $table,
                    array('thumbnail_url' => $thumbnails['large']),
                    array('onedrive_id' => $file_data['id']),
                    array('%s'),
                    array('%s')
                );
            }
            
            // Optionally delete local file to save space
            $keep_local = Azure_Settings::get_setting('onedrive_media_keep_local_copies', false);
            if (!$keep_local) {
                @unlink($local_file);
            }
            
            Azure_Logger::info('OneDrive Media: File uploaded successfully - ' . $file_name);
        } else {
            Azure_Logger::error('OneDrive Media: Failed to upload file - ' . $file_name);
        }
        
        return $upload;
    }
    
    /**
     * Handle attachment deletion
     */
    public function handle_delete_attachment($attachment_id) {
        global $wpdb;
        $table = Azure_Database::get_table_name('onedrive_files');
        
        // Get OneDrive file info
        $file_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE attachment_id = %d",
            $attachment_id
        ));
        
        if ($file_row && $this->graph_api) {
            // Delete from OneDrive
            $result = $this->graph_api->delete_file($file_row->onedrive_id);
            
            if ($result) {
                // Remove from database
                $wpdb->delete($table, array('attachment_id' => $attachment_id), array('%d'));
                Azure_Logger::info('OneDrive Media: File deleted from OneDrive - ' . $file_row->file_name);
            } else {
                Azure_Logger::error('OneDrive Media: Failed to delete file from OneDrive - ' . $file_row->file_name);
            }
        }
    }
    
    /**
     * Store file mapping in database
     */
    private function store_file_mapping($attachment_id, $file_data, $local_path = null) {
        global $wpdb;
        $table = Azure_Database::get_table_name('onedrive_files');
        
        $folder_year = null;
        if (Azure_Settings::get_setting('onedrive_media_use_year_folders', true)) {
            $folder_year = date('Y');
        }
        
        $data = array(
            'attachment_id' => $attachment_id,
            'onedrive_id' => $file_data['id'],
            'onedrive_path' => $file_data['parent_path'],
            'file_name' => $file_data['name'],
            'file_size' => $file_data['size'],
            'mime_type' => $file_data['mime_type'],
            'folder_year' => $folder_year,
            'last_modified' => $file_data['modified'],
            'download_url' => $file_data['download_url'],
            'sync_status' => 'synced'
        );
        
        $wpdb->insert($table, $data, array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'));
        
        return $wpdb->insert_id;
    }
    
    /**
     * Add OneDrive fields to attachment edit screen
     */
    public function add_onedrive_fields($fields, $post) {
        global $wpdb;
        $table = Azure_Database::get_table_name('onedrive_files');
        
        $file_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE attachment_id = %d",
            $post->ID
        ));
        
        if ($file_row) {
            $fields['onedrive_status'] = array(
                'label' => 'OneDrive Status',
                'input' => 'html',
                'html' => '<span style="color: green;">✓ Stored in OneDrive</span><br>' .
                         'File ID: ' . esc_html($file_row->onedrive_id) . '<br>' .
                         'Path: ' . esc_html($file_row->onedrive_path) . '<br>' .
                         ($file_row->public_url ? '<a href="' . esc_url($file_row->public_url) . '" target="_blank">View in OneDrive</a>' : '')
            );
        }
        
        return $fields;
    }
    
    /**
     * Filter attachment URL to use OneDrive URL
     */
    public function filter_attachment_url($url, $attachment_id) {
        global $wpdb;
        $table = Azure_Database::get_table_name('onedrive_files');
        
        $file_row = $wpdb->get_row($wpdb->prepare(
            "SELECT public_url, download_url FROM {$table} WHERE attachment_id = %d",
            $attachment_id
        ));
        
        if ($file_row) {
            // Prefer public URL, fallback to download URL
            return $file_row->public_url ?: $file_row->download_url ?: $url;
        }
        
        return $url;
    }
    
    /**
     * Filter attachment image source to use OneDrive thumbnail
     */
    public function filter_attachment_image_src($image, $attachment_id, $size, $icon) {
        global $wpdb;
        $table = Azure_Database::get_table_name('onedrive_files');
        
        $file_row = $wpdb->get_row($wpdb->prepare(
            "SELECT thumbnail_url, public_url FROM {$table} WHERE attachment_id = %d",
            $attachment_id
        ));
        
        if ($file_row && $file_row->thumbnail_url) {
            $image[0] = $file_row->thumbnail_url;
        }
        
        return $image;
    }
    
    /**
     * Sync files from OneDrive to WordPress
     */
    public function sync_from_onedrive($folder_path = '') {
        if (!$this->graph_api) {
            return array('success' => false, 'message' => 'Graph API not initialized');
        }
        
        $base_folder = Azure_Settings::get_setting('onedrive_media_base_folder', 'WordPress Media');
        if (empty($folder_path)) {
            $folder_path = $base_folder;
        }
        
        // Get files from OneDrive
        $files = $this->graph_api->list_folder($folder_path);
        
        $synced_count = 0;
        $error_count = 0;
        
        foreach ($files as $file) {
            if ($file['is_folder']) {
                continue; // Skip folders
            }
            
            // Check if file already exists in database
            global $wpdb;
            $table = Azure_Database::get_table_name('onedrive_files');
            
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE onedrive_id = %s",
                $file['id']
            ));
            
            if (!$existing) {
                // Create WordPress attachment
                $attachment_id = $this->create_attachment_from_onedrive($file);
                
                if ($attachment_id) {
                    $synced_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        Azure_Logger::info("OneDrive Media: Sync completed - {$synced_count} files synced, {$error_count} errors");
        
        return array(
            'success' => true,
            'synced' => $synced_count,
            'errors' => $error_count,
            'message' => "Synced {$synced_count} files from OneDrive"
        );
    }
    
    /**
     * Create WordPress attachment from OneDrive file
     */
    private function create_attachment_from_onedrive($file_data) {
        // Download file temporarily to get WordPress metadata
        $temp_file = download_url($file_data['download_url']);
        
        if (is_wp_error($temp_file)) {
            Azure_Logger::error('OneDrive Media: Failed to download file for import - ' . $file_data['name']);
            return false;
        }
        
        // Prepare attachment data
        $file_array = array(
            'name' => $file_data['name'],
            'tmp_name' => $temp_file,
            'type' => $file_data['mime_type']
        );
        
        // Create attachment
        $attachment_id = media_handle_sideload($file_array, 0);
        
        // Clean up temp file
        @unlink($temp_file);
        
        if (is_wp_error($attachment_id)) {
            Azure_Logger::error('OneDrive Media: Failed to create attachment - ' . $file_data['name']);
            return false;
        }
        
        // Store mapping
        $this->store_file_mapping($attachment_id, $file_data);
        
        // Get public URL and thumbnail
        if ($this->graph_api) {
            $public_url = $this->graph_api->create_sharing_link($file_data['id'], 'view', 'anonymous');
            $thumbnails = $this->graph_api->get_thumbnails($file_data['id']);
            
            if ($public_url || $thumbnails) {
                global $wpdb;
                $table = Azure_Database::get_table_name('onedrive_files');
                
                $update_data = array();
                if ($public_url) {
                    $update_data['public_url'] = $public_url;
                }
                if ($thumbnails && !empty($thumbnails['large'])) {
                    $update_data['thumbnail_url'] = $thumbnails['large'];
                }
                
                if (!empty($update_data)) {
                    $wpdb->update(
                        $table,
                        $update_data,
                        array('onedrive_id' => $file_data['id']),
                        array_fill(0, count($update_data), '%s'),
                        array('%s')
                    );
                }
            }
        }
        
        return $attachment_id;
    }
    
    /**
     * Run auto-sync (scheduled via WordPress Cron)
     */
    public function run_auto_sync() {
        if (!Azure_Settings::get_setting('onedrive_media_auto_sync', false)) {
            return;
        }
        
        Azure_Logger::info('OneDrive Media: Starting auto-sync');
        $this->sync_from_onedrive();
    }
    
    /**
     * AJAX: Sync from OneDrive
     */
    public function ajax_sync_from_onedrive() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $result = $this->sync_from_onedrive();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Browse OneDrive/SharePoint folders
     */
    public function ajax_browse_folders() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $folder_path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '/';
        $storage_type = isset($_POST['storage_type']) ? sanitize_text_field($_POST['storage_type']) : 'onedrive';
        
        if ($this->graph_api) {
            // If SharePoint, use site and drive ID
            if ($storage_type === 'sharepoint') {
                $site_id = isset($_POST['site_id']) ? sanitize_text_field($_POST['site_id']) : '';
                $drive_id = isset($_POST['drive_id']) ? sanitize_text_field($_POST['drive_id']) : '';
                
                if (empty($site_id) || empty($drive_id)) {
                    wp_send_json_error('SharePoint site ID and drive ID required');
                    return;
                }
                
                $items = $this->graph_api->list_drive_folder($drive_id, $folder_path);
            } else {
                // OneDrive
                $items = $this->graph_api->list_folder($folder_path);
            }
            
            // Filter to only return folders
            $folders = array_filter($items, function($item) {
                return $item['is_folder'];
            });
            
            wp_send_json_success(array('folders' => array_values($folders)));
        } else {
            wp_send_json_error('Graph API not initialized');
        }
    }
    
    /**
     * AJAX: Create folder
     */
    public function ajax_create_folder() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $parent_path = isset($_POST['parent_path']) ? sanitize_text_field($_POST['parent_path']) : '';
        $folder_name = isset($_POST['folder_name']) ? sanitize_text_field($_POST['folder_name']) : '';
        
        if (empty($folder_name)) {
            wp_send_json_error('Folder name is required');
            return;
        }
        
        if ($this->graph_api) {
            $result = $this->graph_api->create_folder($parent_path, $folder_name);
            
            if ($result) {
                wp_send_json_success(array('folder' => $result));
            } else {
                wp_send_json_error('Failed to create folder');
            }
        } else {
            wp_send_json_error('Graph API not initialized');
        }
    }
    
    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        if ($this->auth) {
            $result = $this->auth->test_connection();
            
            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['message']);
            }
        } else {
            wp_send_json_error('Authentication not initialized');
        }
    }
    
    /**
     * AJAX: List SharePoint sites
     */
    public function ajax_list_sharepoint_sites() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check if OneDrive auth is initialized
        if (!$this->auth) {
            wp_send_json_error('OneDrive authentication not initialized. Please check Azure credentials.');
            return;
        }
        
        // Get access token
        $access_token = $this->auth->get_access_token();
        
        if (!$access_token) {
            wp_send_json_error('No access token available. Please authorize OneDrive access first (Step 1).');
            return;
        }
        
        // Make direct Graph API call
        $api_url = 'https://graph.microsoft.com/v1.0/sites?search=*';
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to connect to Microsoft Graph API: ' . $response->get_error_message());
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            wp_send_json_error('Graph API error: ' . ($data['error']['message'] ?? 'Unknown error'));
            return;
        }
        
        $sites = $data['value'] ?? array();
        
        if (empty($sites)) {
            wp_send_json_error('No SharePoint sites found. Make sure you have access to SharePoint sites and the required permissions (Sites.Read.All).');
            return;
        }
        
        wp_send_json_success(array('sites' => $sites));
    }
    
    /**
     * AJAX: List SharePoint document libraries (drives)
     */
    public function ajax_list_sharepoint_drives() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $site_id = isset($_POST['site_id']) ? sanitize_text_field($_POST['site_id']) : '';
        
        if (empty($site_id)) {
            wp_send_json_error('Site ID required');
            return;
        }
        
        // Check if OneDrive auth is initialized
        if (!$this->auth) {
            wp_send_json_error('OneDrive authentication not initialized');
            return;
        }
        
        $access_token = $this->auth->get_access_token();
        
        if (!$access_token) {
            wp_send_json_error('No access token available');
            return;
        }
        
        // Make direct Graph API call
        $api_url = "https://graph.microsoft.com/v1.0/sites/{$site_id}/drives";
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to connect: ' . $response->get_error_message());
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            wp_send_json_error('Graph API error: ' . ($data['error']['message'] ?? 'Unknown error'));
            return;
        }
        
        $drives = $data['value'] ?? array();
        
        if (empty($drives)) {
            wp_send_json_error('No document libraries found');
            return;
        }
        
        wp_send_json_success(array('drives' => $drives));
    }
    
    /**
     * AJAX: Resolve SharePoint site from URL
     */
    public function ajax_resolve_sharepoint_site() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $site_url = isset($_POST['site_url']) ? esc_url_raw($_POST['site_url']) : '';
        
        if (empty($site_url)) {
            wp_send_json_error('Site URL required');
            return;
        }
        
        if ($this->graph_api) {
            $site = $this->graph_api->get_site_by_url($site_url);
            
            if ($site) {
                wp_send_json_success(array(
                    'site_id' => $site['id'],
                    'site_name' => $site['displayName'] ?? $site['name']
                ));
            } else {
                wp_send_json_error('Failed to resolve SharePoint site');
            }
        } else {
            wp_send_json_error('Graph API not initialized');
        }
    }
    
    /**
     * AJAX: Create year folders
     */
    public function ajax_create_year_folders() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        if (!$this->graph_api) {
            wp_send_json_error('Graph API not initialized');
            return;
        }
        
        $base_folder = Azure_Settings::get_setting('onedrive_media_base_folder', 'WordPress Media');
        $current_year = date('Y');
        $folders_created = array();
        
        // Create "Before 2024" folder
        $result = $this->graph_api->create_folder($base_folder, 'Before 2024');
        if ($result) {
            $folders_created[] = 'Before 2024';
        }
        
        // Create folders for 2024 through current year
        for ($year = 2024; $year <= $current_year; $year++) {
            $result = $this->graph_api->create_folder($base_folder, (string)$year);
            if ($result) {
                $folders_created[] = (string)$year;
            }
        }
        
        if (!empty($folders_created)) {
            wp_send_json_success(array('message' => 'Created folders: ' . implode(', ', $folders_created)));
        } else {
            wp_send_json_error('No folders were created. They may already exist.');
        }
    }
    
    /**
     * Get sync statistics
     */
    public function get_sync_stats() {
        global $wpdb;
        $table = Azure_Database::get_table_name('onedrive_files');
        
        $stats = array(
            'total_files' => $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
            'synced_files' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE sync_status = 'synced'"),
            'pending_files' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE sync_status = 'pending'"),
            'error_files' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE sync_status = 'error'"),
            'total_size' => $wpdb->get_var("SELECT SUM(file_size) FROM {$table}")
        );
        
        return $stats;
    }
}