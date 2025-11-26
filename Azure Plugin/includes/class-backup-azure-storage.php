<?php
/**
 * Azure Storage handler for backup functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Backup_Storage {
    
    private $storage_account;
    private $storage_key;
    private $container_name;
    
    public function __construct() {
        // Initialize settings safely to avoid dependency issues
        try {
            // Try new setting names first, fallback to old names for backwards compatibility
            $this->storage_account = Azure_Settings::get_setting('backup_storage_account_name', '');
            if (empty($this->storage_account)) {
                $this->storage_account = Azure_Settings::get_setting('backup_storage_account', '');
            }
            
            $this->storage_key = Azure_Settings::get_setting('backup_storage_account_key', '');
            if (empty($this->storage_key)) {
                $this->storage_key = Azure_Settings::get_setting('backup_storage_key', '');
            }
            
            $this->container_name = Azure_Settings::get_setting('backup_storage_container_name', 'wordpress-backups');
            if ($this->container_name === 'wordpress-backups') {
                // Fallback to old setting name
                $old_container = Azure_Settings::get_setting('backup_container_name', '');
                if (!empty($old_container)) {
                    $this->container_name = $old_container;
                }
            }
            
            if (empty($this->storage_account) || empty($this->storage_key)) {
                if (class_exists('Azure_Logger')) {
                    Azure_Logger::debug_module('Backup', 'Azure Storage credentials not configured');
                }
            }
        } catch (Exception $e) {
            // Handle cases where settings aren't initialized yet
            $this->storage_account = '';
            $this->storage_key = '';
            $this->container_name = 'wordpress-backups';
            if (class_exists('Azure_Logger')) {
                Azure_Logger::debug_module('Backup', 'Could not load settings - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Upload backup file to Azure Blob Storage
     */
    public function upload_backup($file_path, $backup_id) {
        if (!$this->is_configured()) {
            throw new Exception('Azure Storage is not properly configured');
        }
        
        if (!file_exists($file_path)) {
            throw new Exception('Backup file does not exist: ' . $file_path);
        }
        
        $blob_name = $this->generate_blob_name($backup_id, basename($file_path));
        
        try {
            Azure_Logger::info('Backup Storage: Uploading backup to Azure: ' . $blob_name);
            
            $result = $this->upload_blob($blob_name, $file_path);
            
            if ($result) {
                Azure_Logger::info('Backup Storage: Successfully uploaded: ' . $blob_name);
                return $blob_name;
            } else {
                throw new Exception('Failed to upload backup to Azure Storage');
            }
            
        } catch (Exception $e) {
            Azure_Logger::error('Backup Storage: Upload failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Download backup from Azure Blob Storage
     */
    public function download_backup($blob_name, $destination_path) {
        if (!$this->is_configured()) {
            throw new Exception('Azure Storage is not properly configured');
        }
        
        try {
            Azure_Logger::info('Backup Storage: Downloading backup from Azure: ' . $blob_name);
            
            $result = $this->download_blob($blob_name, $destination_path);
            
            if ($result) {
                Azure_Logger::info('Backup Storage: Successfully downloaded: ' . $blob_name);
                return true;
            } else {
                throw new Exception('Failed to download backup from Azure Storage');
            }
            
        } catch (Exception $e) {
            Azure_Logger::error('Backup Storage: Download failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * List backups in Azure Storage
     */
    public function list_backups($limit = 50) {
        if (!$this->is_configured()) {
            return array();
        }
        
        try {
            $blobs = $this->list_blobs($limit);
            $backups = array();
            
            foreach ($blobs as $blob) {
                $backups[] = array(
                    'name' => $blob['name'],
                    'size' => $blob['size'],
                    'modified' => $blob['modified'],
                    'url' => $this->generate_blob_url($blob['name'])
                );
            }
            
            return $backups;
            
        } catch (Exception $e) {
            Azure_Logger::error('Backup Storage: List failed: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Delete backup from Azure Storage
     */
    public function delete_backup($blob_name) {
        if (!$this->is_configured()) {
            throw new Exception('Azure Storage is not properly configured');
        }
        
        try {
            Azure_Logger::info('Backup Storage: Deleting backup from Azure: ' . $blob_name);
            
            $result = $this->delete_blob($blob_name);
            
            if ($result) {
                Azure_Logger::info('Backup Storage: Successfully deleted: ' . $blob_name);
                return true;
            } else {
                throw new Exception('Failed to delete backup from Azure Storage');
            }
            
        } catch (Exception $e) {
            Azure_Logger::error('Backup Storage: Delete failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Upload blob to Azure Storage
     */
    private function upload_blob($blob_name, $file_path) {
        $url = $this->get_blob_url($blob_name);
        $file_size = filesize($file_path);
        $file_content = file_get_contents($file_path);
        
        if ($file_content === false) {
            throw new Exception('Failed to read backup file');
        }
        
        $headers = array(
            'x-ms-blob-type' => 'BlockBlob',
            'x-ms-version' => '2020-04-08',
            'x-ms-date' => gmdate('D, d M Y H:i:s T'),
            'Content-Length' => $file_size,
            'Content-Type' => 'application/zip'
        );
        
        // Generate authorization header
        $auth_header = $this->generate_auth_header('PUT', $blob_name, $headers, $file_size);
        $headers['Authorization'] = $auth_header;
        
        $args = array(
            'method' => 'PUT',
            'headers' => $headers,
            'body' => $file_content,
            'timeout' => 300 // 5 minutes timeout for large files
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('HTTP request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 201) {
            $response_body = wp_remote_retrieve_body($response);
            throw new Exception('Upload failed with status ' . $response_code . ': ' . $response_body);
        }
        
        return true;
    }
    
    /**
     * Download blob from Azure Storage
     */
    private function download_blob($blob_name, $destination_path) {
        $url = $this->get_blob_url($blob_name);
        
        $headers = array(
            'x-ms-version' => '2020-04-08',
            'x-ms-date' => gmdate('D, d M Y H:i:s T')
        );
        
        // Generate authorization header
        $auth_header = $this->generate_auth_header('GET', $blob_name, $headers);
        $headers['Authorization'] = $auth_header;
        
        $args = array(
            'method' => 'GET',
            'headers' => $headers,
            'timeout' => 300
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('HTTP request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            throw new Exception('Download failed with status ' . $response_code . ': ' . $response_body);
        }
        
        $file_content = wp_remote_retrieve_body($response);
        
        if (file_put_contents($destination_path, $file_content) === false) {
            throw new Exception('Failed to write downloaded file');
        }
        
        return true;
    }
    
    /**
     * List blobs in container
     */
    private function list_blobs($limit = 50) {
        $url = "https://{$this->storage_account}.blob.core.windows.net/{$this->container_name}?restype=container&comp=list&maxresults={$limit}";
        
        $headers = array(
            'x-ms-version' => '2020-04-08',
            'x-ms-date' => gmdate('D, d M Y H:i:s T')
        );
        
        // Generate authorization header
        $auth_header = $this->generate_auth_header('GET', '', $headers, 0, $url);
        $headers['Authorization'] = $auth_header;
        
        $args = array(
            'method' => 'GET',
            'headers' => $headers,
            'timeout' => 30
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('HTTP request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            throw new Exception('List failed with status ' . $response_code . ': ' . $response_body);
        }
        
        $xml_content = wp_remote_retrieve_body($response);
        
        // Parse XML response
        return $this->parse_blob_list($xml_content);
    }
    
    /**
     * Delete blob from Azure Storage
     */
    private function delete_blob($blob_name) {
        $url = $this->get_blob_url($blob_name);
        
        $headers = array(
            'x-ms-version' => '2020-04-08',
            'x-ms-date' => gmdate('D, d M Y H:i:s T')
        );
        
        // Generate authorization header
        $auth_header = $this->generate_auth_header('DELETE', $blob_name, $headers);
        $headers['Authorization'] = $auth_header;
        
        $args = array(
            'method' => 'DELETE',
            'headers' => $headers,
            'timeout' => 30
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('HTTP request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 202) {
            $response_body = wp_remote_retrieve_body($response);
            throw new Exception('Delete failed with status ' . $response_code . ': ' . $response_body);
        }
        
        return true;
    }
    
    /**
     * Generate blob URL
     */
    private function get_blob_url($blob_name) {
        return "https://{$this->storage_account}.blob.core.windows.net/{$this->container_name}/{$blob_name}";
    }
    
    /**
     * Generate public blob URL
     */
    private function generate_blob_url($blob_name) {
        return $this->get_blob_url($blob_name);
    }
    
    /**
     * Generate blob name from backup ID
     */
    private function generate_blob_name($backup_id, $filename) {
        $date = date('Y/m/d');
        $site_name = sanitize_title(get_bloginfo('name'));
        return "{$site_name}/{$date}/{$backup_id}_{$filename}";
    }
    
    /**
     * Generate Azure Storage authorization header
     */
    private function generate_auth_header($method, $blob_name, $headers, $content_length = 0, $full_url = null) {
        $canonical_headers = '';
        $canonical_resource = '';
        
        // Sort headers
        ksort($headers);
        
        // Build canonical headers
        foreach ($headers as $name => $value) {
            if (strpos($name, 'x-ms-') === 0) {
                $canonical_headers .= strtolower($name) . ':' . $value . "\n";
            }
        }
        
        // Build canonical resource
        if ($full_url) {
            $parsed_url = parse_url($full_url);
            $canonical_resource = '/' . $this->storage_account . $parsed_url['path'];
            if (!empty($parsed_url['query'])) {
                parse_str($parsed_url['query'], $query_params);
                ksort($query_params);
                foreach ($query_params as $key => $value) {
                    $canonical_resource .= "\n" . strtolower($key) . ':' . $value;
                }
            }
        } else {
            $canonical_resource = '/' . $this->storage_account . '/' . $this->container_name;
            if (!empty($blob_name)) {
                $canonical_resource .= '/' . $blob_name;
            }
        }
        
        // Build string to sign - Azure Storage requires empty string for 0 content length
        $content_length_value = '';
        if (isset($headers['Content-Length'])) {
            $content_length_value = $headers['Content-Length'];
        } else if ($content_length > 0) {
            $content_length_value = $content_length;
        }
        
        $string_to_sign = $method . "\n" .
                         (isset($headers['Content-Encoding']) ? $headers['Content-Encoding'] : '') . "\n" .
                         (isset($headers['Content-Language']) ? $headers['Content-Language'] : '') . "\n" .
                         $content_length_value . "\n" .
                         (isset($headers['Content-MD5']) ? $headers['Content-MD5'] : '') . "\n" .
                         (isset($headers['Content-Type']) ? $headers['Content-Type'] : '') . "\n" .
                         (isset($headers['Date']) ? $headers['Date'] : '') . "\n" .
                         (isset($headers['If-Modified-Since']) ? $headers['If-Modified-Since'] : '') . "\n" .
                         (isset($headers['If-Match']) ? $headers['If-Match'] : '') . "\n" .
                         (isset($headers['If-None-Match']) ? $headers['If-None-Match'] : '') . "\n" .
                         (isset($headers['If-Unmodified-Since']) ? $headers['If-Unmodified-Since'] : '') . "\n" .
                         (isset($headers['Range']) ? $headers['Range'] : '') . "\n" .
                         $canonical_headers .
                         $canonical_resource;
        
        // Generate signature
        $signature = base64_encode(hash_hmac('sha256', $string_to_sign, base64_decode($this->storage_key), true));
        
        return 'SharedKey ' . $this->storage_account . ':' . $signature;
    }
    
    /**
     * Generate authentication header for testing with provided credentials
     */
    private function generate_auth_header_for_test($method, $blob_name, $headers, $content_length = 0, $full_url = null, $storage_account = null, $storage_key = null) {
        $canonical_headers = '';
        $canonical_resource = '';
        
        // Sort headers
        ksort($headers);
        
        // Build canonical headers
        foreach ($headers as $name => $value) {
            if (strpos($name, 'x-ms-') === 0) {
                $canonical_headers .= strtolower($name) . ':' . $value . "\n";
            }
        }
        
        // Build canonical resource
        if ($full_url) {
            $parsed_url = parse_url($full_url);
            $canonical_resource = '/' . $storage_account . $parsed_url['path'];
            if (!empty($parsed_url['query'])) {
                parse_str($parsed_url['query'], $query_params);
                ksort($query_params);
                foreach ($query_params as $key => $value) {
                    $canonical_resource .= "\n" . strtolower($key) . ':' . $value;
                }
            }
        } else {
            $canonical_resource = '/' . $storage_account . '/' . ($this->container_name ?: 'wordpress-backups');
            if (!empty($blob_name)) {
                $canonical_resource .= '/' . $blob_name;
            }
        }
        
        // Build string to sign - Azure Storage requires empty string for 0 content length
        $content_length_value = '';
        if (isset($headers['Content-Length'])) {
            $content_length_value = $headers['Content-Length'];
        } else if ($content_length > 0) {
            $content_length_value = $content_length;
        }
        
        $string_to_sign = $method . "\n" .
                         (isset($headers['Content-Encoding']) ? $headers['Content-Encoding'] : '') . "\n" .
                         (isset($headers['Content-Language']) ? $headers['Content-Language'] : '') . "\n" .
                         $content_length_value . "\n" .
                         (isset($headers['Content-MD5']) ? $headers['Content-MD5'] : '') . "\n" .
                         (isset($headers['Content-Type']) ? $headers['Content-Type'] : '') . "\n" .
                         (isset($headers['Date']) ? $headers['Date'] : '') . "\n" .
                         (isset($headers['If-Modified-Since']) ? $headers['If-Modified-Since'] : '') . "\n" .
                         (isset($headers['If-Match']) ? $headers['If-Match'] : '') . "\n" .
                         (isset($headers['If-None-Match']) ? $headers['If-None-Match'] : '') . "\n" .
                         (isset($headers['If-Unmodified-Since']) ? $headers['If-Unmodified-Since'] : '') . "\n" .
                         (isset($headers['Range']) ? $headers['Range'] : '') . "\n" .
                         $canonical_headers .
                         $canonical_resource;
        
        // Debug: Log the string to sign for troubleshooting
        Azure_Logger::debug('Backup Storage: String to sign: ' . str_replace("\n", "\\n", $string_to_sign));
        Azure_Logger::debug('Backup Storage: Canonical resource: ' . $canonical_resource);
        Azure_Logger::debug('Backup Storage: Canonical headers: ' . str_replace("\n", "\\n", $canonical_headers));
        
        // Generate signature
        $signature = base64_encode(hash_hmac('sha256', $string_to_sign, base64_decode($storage_key), true));
        
        return 'SharedKey ' . $storage_account . ':' . $signature;
    }
    
    /**
     * Parse blob list XML response
     */
    private function parse_blob_list($xml_content) {
        $blobs = array();
        
        try {
            $xml = simplexml_load_string($xml_content);
            
            if ($xml && isset($xml->Blobs->Blob)) {
                foreach ($xml->Blobs->Blob as $blob) {
                    $blobs[] = array(
                        'name' => (string)$blob->Name,
                        'size' => (int)$blob->Properties->{'Content-Length'},
                        'modified' => (string)$blob->Properties->{'Last-Modified'},
                        'etag' => (string)$blob->Properties->Etag
                    );
                }
            }
        } catch (Exception $e) {
            Azure_Logger::error('Backup Storage: Failed to parse blob list: ' . $e->getMessage());
        }
        
        return $blobs;
    }
    
    /**
     * Check if storage is properly configured
     */
    private function is_configured() {
        return !empty($this->storage_account) && !empty($this->storage_key);
    }
    
    /**
     * Test storage connection
     */
    public function test_connection($storage_account = null, $storage_key = null, $container_name = null) {
        // Use provided credentials or fall back to instance credentials
        $test_account = $storage_account ?: $this->storage_account;
        $test_key = $storage_key ?: $this->storage_key;
        $test_container = $container_name ?: $this->container_name;
        
        if (empty($test_account) || empty($test_key)) {
            return array(
                'success' => false,
                'message' => 'Storage credentials not configured. Please provide Storage Account Name and Access Key.'
            );
        }
        
        // Validate container name format
        if (!preg_match('/^[a-z0-9]([a-z0-9-]{1,61}[a-z0-9])?$/', $test_container)) {
            return array(
                'success' => false,
                'message' => 'Invalid container name "' . $test_container . '". Container names must be 3-63 characters long, contain only lowercase letters, numbers, and hyphens, and start/end with a letter or number.'
            );
        }
        
        // Validate access key format (should be base64)
        if (!base64_decode($test_key, true)) {
            return array(
                'success' => false,
                'message' => 'Invalid Access Key format. The access key should be a valid base64 string (88 characters long).'
            );
        }
        
        try {
            Azure_Logger::info('Backup Storage: Testing connection to ' . $test_account . ' with container: ' . $test_container);
            
            // First, try to list containers to verify account credentials
            $containers_url = "https://{$test_account}.blob.core.windows.net/?comp=list&maxresults=1";
            
            $date_header = gmdate('D, d M Y H:i:s T');
            $headers = array(
                'x-ms-version' => '2020-04-08',
                'x-ms-date' => $date_header
            );
            
            Azure_Logger::debug('Backup Storage: Testing with date header: ' . $date_header);
            
            $auth_header = $this->generate_auth_header_for_test('GET', '', $headers, 0, $containers_url, $test_account, $test_key);
            $headers['Authorization'] = $auth_header;
            
            Azure_Logger::debug('Backup Storage: Auth header: ' . substr($auth_header, 0, 50) . '...');
            
            $response = wp_remote_get($containers_url, array(
                'headers' => $headers,
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Connection failed: ' . $response->get_error_message()
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            Azure_Logger::info('Backup Storage: Account test response - Code: ' . $response_code);
            
            if ($response_code === 403) {
                // Parse error details from response
                $error_details = $this->parse_azure_error($response_body);
                return array(
                    'success' => false,
                    'message' => 'Access forbidden (403). ' . $error_details . ' Please verify:
1. Storage Account Name "' . $test_account . '" is correct
2. Access Key is correct and has not expired  
3. Account has Blob Storage permissions
4. Access key is the full primary or secondary key from Azure portal'
                );
            } else if ($response_code !== 200) {
                $error_details = $this->parse_azure_error($response_body);
                return array(
                    'success' => false,
                    'message' => 'Account verification failed (' . $response_code . '): ' . $error_details
                );
            }
            
            Azure_Logger::info('Backup Storage: Account credentials verified, now testing container access');
            
            // Now test the specific container
            $container_url = "https://{$test_account}.blob.core.windows.net/{$test_container}?restype=container&comp=list&maxresults=1";
            
            $container_response = wp_remote_get($container_url, array(
                'headers' => array(
                    'x-ms-version' => '2020-04-08',
                    'x-ms-date' => gmdate('D, d M Y H:i:s T'),
                    'Authorization' => $this->generate_auth_header_for_test('GET', '', array(
                        'x-ms-version' => '2020-04-08',
                        'x-ms-date' => gmdate('D, d M Y H:i:s T')
                    ), 0, $container_url, $test_account, $test_key)
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($container_response)) {
                return array(
                    'success' => false,
                    'message' => 'Container test failed: ' . $container_response->get_error_message()
                );
            }
            
            $container_code = wp_remote_retrieve_response_code($container_response);
            $container_body = wp_remote_retrieve_body($container_response);
            
            Azure_Logger::info('Backup Storage: Container test response - Code: ' . $container_code);
            
            if ($container_code === 200) {
                return array(
                    'success' => true,
                    'message' => 'Storage connection successful! Container "' . $test_container . '" is accessible and ready for backups.'
                );
            } else if ($container_code === 404) {
                // Container doesn't exist - try to create it
                Azure_Logger::info('Backup Storage: Container not found, attempting to create it');
                $create_result = $this->create_container_for_test($test_account, $test_key, $test_container);
                return $create_result;
            } else {
                $error_details = $this->parse_azure_error($container_body);
                return array(
                    'success' => false,
                    'message' => 'Container access failed (' . $container_code . '): ' . $error_details
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Parse Azure error response
     */
    private function parse_azure_error($response_body) {
        $error_message = '';
        if ($response_body) {
            // Try to parse XML error
            if (strpos($response_body, '<Error>') !== false) {
                $xml = simplexml_load_string($response_body);
                if ($xml && isset($xml->Code)) {
                    $error_message = (string)$xml->Code;
                    if (isset($xml->Message)) {
                        $error_message .= ': ' . (string)$xml->Message;
                    }
                }
            } else {
                // Fallback to first 200 chars of response
                $error_message = substr($response_body, 0, 200);
            }
        }
        return $error_message ?: 'Unknown Azure error';
    }
    
    /**
     * Create container for testing
     */
    private function create_container_for_test($storage_account, $storage_key, $container_name) {
        try {
            Azure_Logger::info('Backup Storage: Attempting to create container: ' . $container_name);
            
            $url = "https://{$storage_account}.blob.core.windows.net/{$container_name}?restype=container";
            
            $headers = array(
                'x-ms-version' => '2020-04-08',
                'x-ms-date' => gmdate('D, d M Y H:i:s T'),
                'x-ms-blob-public-access' => 'container' // Allow read access for easier testing
            );
            
            $auth_header = $this->generate_auth_header_for_test('PUT', '', $headers, 0, $url, $storage_account, $storage_key);
            $headers['Authorization'] = $auth_header;
            
            $response = wp_remote_request($url, array(
                'method' => 'PUT',
                'headers' => $headers,
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Container creation failed: ' . $response->get_error_message()
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            Azure_Logger::info('Backup Storage: Container creation response - Code: ' . $response_code);
            
            if ($response_code === 201) {
                return array(
                    'success' => true,
                    'message' => 'Storage connection successful! Container "' . $container_name . '" was created and is ready for backups.'
                );
            } else if ($response_code === 409) {
                // Container already exists
                return array(
                    'success' => true,
                    'message' => 'Storage connection successful! Container "' . $container_name . '" already exists and is accessible.'
                );
            } else {
                $error_details = $this->parse_azure_error($response_body);
                return array(
                    'success' => false,
                    'message' => 'Container creation failed (' . $response_code . '): ' . $error_details . '. You may need to create the container manually in Azure portal.'
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Container creation failed: ' . $e->getMessage()
            );
        }
    }
}