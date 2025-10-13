<?php
/**
 * Core backup functionality for Azure Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Backup {
    
    private static $backup_in_progress = false;
    private static $current_backup_id = null;
    private $settings;
    
    public function __construct() {
        try {
            $this->settings = Azure_Settings::get_all_settings();
            
            // Hook for scheduled backups
            add_action('azure_backup_scheduled', array($this, 'run_scheduled_backup'));
            
            // Hook for background backup processing
            add_action('azure_backup_process', array($this, 'process_background_backup'));
            
            // AJAX actions for admin
            add_action('wp_ajax_azure_start_backup', array($this, 'ajax_start_backup'));
            add_action('wp_ajax_azure_get_backup_jobs', array($this, 'ajax_get_backup_jobs'));
            add_action('wp_ajax_azure_get_backup_progress', array($this, 'ajax_get_backup_progress'));
            add_action('wp_ajax_azure_trigger_backup_process', array($this, 'ajax_trigger_backup_process'));
            
        } catch (Exception $e) {
            if (class_exists('Azure_Logger')) {
                Azure_Logger::error('Backup: Constructor error - ' . $e->getMessage());
            }
            // Initialize with empty settings if there's an error
            $this->settings = array();
        }
    }
    
    /**
     * Create a new backup
     */
    public function create_backup($backup_name, $backup_types, $scheduled = false) {
        if (self::$backup_in_progress) {
            throw new Exception('Another backup is already in progress.');
        }
        
        // Validate backup types
        $valid_types = array('content', 'media', 'plugins', 'themes', 'database');
        $backup_types = array_intersect($backup_types, $valid_types);
        
        if (empty($backup_types)) {
            throw new Exception('No valid backup types specified.');
        }
        
        // Generate unique backup ID
        $backup_id = uniqid('backup_', true);
        $backup_id = str_replace('.', '', $backup_id);
        
        // Create backup record in database
        $job_id = $this->create_backup_job($backup_id, $backup_name, $backup_types, $scheduled);
        
        if (!$job_id) {
            throw new Exception('Failed to create backup job record.');
        }
        
        self::$backup_in_progress = true;
        self::$current_backup_id = $backup_id;
        
        Azure_Logger::info('Backup: Starting backup job: ' . $backup_name);
        Azure_Database::log_activity('backup', 'backup_started', 'job', $job_id, array('types' => $backup_types));
        
        try {
            // Create backup directory
            $backup_dir = $this->get_backup_directory($backup_id);
            if (!wp_mkdir_p($backup_dir)) {
                throw new Exception('Failed to create backup directory.');
            }
            
            // Start backup process
            $this->process_backup($job_id, $backup_id, $backup_types, $backup_dir);
            
            Azure_Logger::info('Backup: Backup completed successfully: ' . $backup_name);
            return $backup_id;
            
        } catch (Exception $e) {
            Azure_Logger::error('Backup: Backup failed: ' . $e->getMessage());
            $this->update_backup_status($job_id, 'failed', $e->getMessage());
            
            self::$backup_in_progress = false;
            self::$current_backup_id = null;
            
            throw $e;
        }
    }
    
    /**
     * Process backup
     */
    private function process_backup($job_id, $backup_id, $backup_types, $backup_dir) {
        $this->update_backup_status($job_id, 'running');
        $this->update_backup_progress($backup_id, 10, 'running', 'Starting backup process...');
        
        $backup_files = array();
        $total_types = count($backup_types);
        $completed_types = 0;
        
        foreach ($backup_types as $type) {
            Azure_Logger::info("Backup: Processing {$type} backup");
            $progress = 10 + (40 * $completed_types / $total_types); // 10-50% for individual backups
            $this->update_backup_progress($backup_id, $progress, 'running', "Backing up {$type}...");
            
            switch ($type) {
                case 'database':
                    $files = $this->backup_database($backup_dir, $backup_id);
                    break;
                case 'content':
                    $files = $this->backup_content($backup_dir, $backup_id);
                    break;
                case 'media':
                    $files = $this->backup_media($backup_dir, $backup_id);
                    break;
                case 'plugins':
                    $files = $this->backup_plugins($backup_dir, $backup_id);
                    break;
                case 'themes':
                    $files = $this->backup_themes($backup_dir, $backup_id);
                    break;
            }
            
            if ($files) {
                $backup_files = array_merge($backup_files, $files);
            }
            
            $completed_types++;
        }
        
        // Create final archive
        $this->update_backup_progress($backup_id, 60, 'running', 'Creating backup archive...');
        $archive_path = $this->create_backup_archive($backup_dir, $backup_id, $backup_files);
        
        if (!$archive_path) {
            throw new Exception('Failed to create backup archive.');
        }
        
        // Upload to Azure Storage
        $this->update_backup_progress($backup_id, 80, 'running', 'Uploading to Azure Storage...');
        
        // Verify archive exists and has content before upload
        if (!file_exists($archive_path)) {
            throw new Exception('Backup archive not found at: ' . $archive_path);
        }
        
        $archive_size = filesize($archive_path);
        if ($archive_size < 1024) { // Less than 1KB is suspicious
            throw new Exception('Backup archive is too small (' . $archive_size . ' bytes) - likely incomplete');
        }
        
        Azure_Logger::info('Backup: Archive verified - Size: ' . $this->format_bytes($archive_size) . ' at: ' . $archive_path, 'Backup');
        
        if (class_exists('Azure_Backup_Storage')) {
            $storage = new Azure_Backup_Storage();
            
            // Test storage connection before upload
            $storage_test = $storage->test_connection();
            if (!$storage_test['success']) {
                throw new Exception('Azure Storage connection failed: ' . $storage_test['message']);
            }
            
            Azure_Logger::info('Backup: Azure Storage connection verified, starting upload', 'Backup');
            
            $blob_name = $storage->upload_backup($archive_path, $backup_id);
            
            if ($blob_name) {
                $this->update_backup_blob_info($job_id, $blob_name, $archive_size);
                Azure_Logger::info('Backup: Successfully uploaded to Azure Storage: ' . $blob_name . ' (' . $this->format_bytes($archive_size) . ')', 'Backup');
                $this->update_backup_progress($backup_id, 90, 'running', 'Upload completed: ' . $blob_name);
            } else {
                throw new Exception('Failed to upload backup to Azure Storage - no blob name returned');
            }
        } else {
            throw new Exception('Azure_Backup_Storage class not available');
        }
        
        // Clean up local files
        $this->update_backup_progress($backup_id, 95, 'running', 'Cleaning up temporary files...');
        $this->cleanup_backup_files($backup_dir, $archive_path);
        
        // Mark as complete
        $this->update_backup_status($job_id, 'completed');
        $this->update_backup_progress($backup_id, 100, 'completed', 'Backup completed successfully!');
        
        self::$backup_in_progress = false;
        self::$current_backup_id = null;
        
        // Send notification if enabled
        if ($this->settings['backup_email_notifications'] ?? false) {
            $this->send_backup_notification($job_id, true, 'Backup completed successfully');
        }
    }
    
    /**
     * Backup database
     */
    private function backup_database($backup_dir, $backup_id) {
        $sql_file = $backup_dir . '/database_' . $backup_id . '.sql';
        
        global $wpdb;
        
        // Get all tables
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        
        if (empty($tables)) {
            throw new Exception('No database tables found.');
        }
        
        $sql_content = '';
        
        // Add database info header
        $sql_content .= "-- WordPress Database Backup\n";
        $sql_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql_content .= "-- Site: " . get_site_url() . "\n\n";
        $sql_content .= "SET foreign_key_checks = 0;\n\n";
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            
            // Get table structure
            $create_table = $wpdb->get_row("SHOW CREATE TABLE `{$table_name}`", ARRAY_N);
            $sql_content .= "DROP TABLE IF EXISTS `{$table_name}`;\n";
            $sql_content .= $create_table[1] . ";\n\n";
            
            // Get table data
            $rows = $wpdb->get_results("SELECT * FROM `{$table_name}`", ARRAY_A);
            
            if (!empty($rows)) {
                $sql_content .= "INSERT INTO `{$table_name}` VALUES ";
                $value_strings = array();
                
                foreach ($rows as $row) {
                    $values = array();
                    foreach ($row as $value) {
                        if (is_null($value)) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $wpdb->_real_escape($value) . "'";
                        }
                    }
                    $value_strings[] = '(' . implode(',', $values) . ')';
                }
                
                $sql_content .= implode(',', $value_strings) . ";\n\n";
            }
        }
        
        $sql_content .= "SET foreign_key_checks = 1;\n";
        
        if (file_put_contents($sql_file, $sql_content) === false) {
            throw new Exception('Failed to write database backup file.');
        }
        
        return array($sql_file);
    }
    
    /**
     * Backup WordPress content
     */
    private function backup_content($backup_dir, $backup_id) {
        $content_dir = $backup_dir . '/content_' . $backup_id;
        wp_mkdir_p($content_dir);
        
        $wp_content = WP_CONTENT_DIR;
        $exclude_dirs = array('uploads', 'plugins', 'themes', 'cache', 'backup');
        
        return $this->copy_directory($wp_content, $content_dir, $exclude_dirs);
    }
    
    /**
     * Backup media files
     */
    private function backup_media($backup_dir, $backup_id) {
        $media_dir = $backup_dir . '/media_' . $backup_id;
        wp_mkdir_p($media_dir);
        
        $uploads_dir = wp_upload_dir()['basedir'];
        
        if (!is_dir($uploads_dir)) {
            Azure_Logger::warning('Backup: Uploads directory not found: ' . $uploads_dir);
            return array();
        }
        
        return $this->copy_directory($uploads_dir, $media_dir);
    }
    
    /**
     * Backup plugins
     */
    private function backup_plugins($backup_dir, $backup_id) {
        $plugins_dir = $backup_dir . '/plugins_' . $backup_id;
        wp_mkdir_p($plugins_dir);
        
        $wp_plugins = WP_PLUGIN_DIR;
        
        return $this->copy_directory($wp_plugins, $plugins_dir);
    }
    
    /**
     * Backup themes
     */
    private function backup_themes($backup_dir, $backup_id) {
        $themes_dir = $backup_dir . '/themes_' . $backup_id;
        wp_mkdir_p($themes_dir);
        
        $wp_themes = get_theme_root();
        
        return $this->copy_directory($wp_themes, $themes_dir);
    }
    
    /**
     * Copy directory recursively
     */
    private function copy_directory($source, $destination, $exclude_dirs = array()) {
        $files = array();
        
        if (!is_dir($source)) {
            return $files;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $source_path = $item->getPathname();
            $relative_path = str_replace($source, '', $source_path);
            $destination_path = $destination . $relative_path;
            
            // Check if we should exclude this path
            $should_exclude = false;
            foreach ($exclude_dirs as $exclude) {
                if (strpos($relative_path, DIRECTORY_SEPARATOR . $exclude . DIRECTORY_SEPARATOR) !== false ||
                    strpos($relative_path, DIRECTORY_SEPARATOR . $exclude) === 0) {
                    $should_exclude = true;
                    break;
                }
            }
            
            if ($should_exclude) {
                continue;
            }
            
            if ($item->isDir()) {
                wp_mkdir_p($destination_path);
            } elseif ($item->isFile()) {
                wp_mkdir_p(dirname($destination_path));
                if (copy($source_path, $destination_path)) {
                    $files[] = $destination_path;
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Format bytes to human readable string
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Create backup archive
     */
    private function create_backup_archive($backup_dir, $backup_id, $files) {
        $archive_path = AZURE_PLUGIN_PATH . 'backups/' . $backup_id . '.zip';
        
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive extension is not available.');
        }
        
        $zip = new ZipArchive();
        $result = $zip->open($archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        
        if ($result !== TRUE) {
            throw new Exception('Failed to create backup archive: ' . $result);
        }
        
        // Add all files to archive
        foreach ($files as $file) {
            $relative_name = str_replace($backup_dir . '/', '', $file);
            $zip->addFile($file, $relative_name);
        }
        
        $zip->close();
        
        if (!file_exists($archive_path)) {
            throw new Exception('Failed to create backup archive file.');
        }
        
        return $archive_path;
    }
    
    /**
     * Get backup directory path
     */
    private function get_backup_directory($backup_id) {
        return AZURE_PLUGIN_PATH . 'backups/temp_' . $backup_id;
    }
    
    /**
     * Create backup job record
     */
    private function create_backup_job($backup_id, $backup_name, $backup_types, $scheduled) {
        global $wpdb;
        
        Azure_Logger::debug('Backup: create_backup_job called with ID: ' . $backup_id, 'Backup');
        
        try {
            $table = Azure_Database::get_table_name('backup_jobs');
            Azure_Logger::debug('Backup: Table name retrieved: ' . ($table ? $table : 'NULL'), 'Backup');
            
            if (!$table) {
                Azure_Logger::error('Backup: Backup jobs table name not found', 'Backup');
                return false;
            }
            
            // Check if table actually exists
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if (!$table_exists) {
                Azure_Logger::error('Backup: Table does not exist: ' . $table . ' - attempting to create it', 'Backup');
                
                // Try to create the missing table
                try {
                    if (method_exists('Azure_Database', 'create_tables')) {
                        Azure_Database::create_tables();
                        Azure_Logger::info('Backup: Database tables creation attempted', 'Backup');
                        
                        // Check again if table exists now
                        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
                        if (!$table_exists) {
                            Azure_Logger::error('Backup: Table still does not exist after creation attempt: ' . $table, 'Backup');
                            // Let's try to create just the backup jobs table manually
                            $this->create_backup_jobs_table_manual();
                            
                            // Final check
                            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
                            if (!$table_exists) {
                                Azure_Logger::error('Backup: Manual table creation also failed: ' . $table, 'Backup');
                                return false;
                            }
                        } else {
                            Azure_Logger::info('Backup: Table created successfully: ' . $table, 'Backup');
                        }
                    } else {
                        throw new Exception('Azure_Database::create_tables method not found');
                    }
                } catch (Exception $create_exception) {
                    Azure_Logger::error('Backup: Failed to create database tables: ' . $create_exception->getMessage(), 'Backup');
                    return false;
                }
            }
            
            Azure_Logger::debug('Backup: Table exists, proceeding with insert', 'Backup');
            
            $insert_data = array(
                'backup_id' => $backup_id,
                'job_name' => $backup_name,
                'backup_types' => json_encode($backup_types),
                'status' => 'pending',
                'progress' => 0,
                'message' => 'Backup initialized',
                'started_at' => current_time('mysql')
            );
            
            Azure_Logger::debug('Backup: Insert data prepared: ' . json_encode($insert_data), 'Backup');
            
            $result = $wpdb->insert(
                $table,
                $insert_data,
                array('%s', '%s', '%s', '%s', '%d', '%s', '%s')
            );
            
            if ($result === false) {
                Azure_Logger::error('Backup: Database insert failed. Error: ' . $wpdb->last_error, 'Backup');
                return false;
            }
            
            $insert_id = $wpdb->insert_id;
            Azure_Logger::debug('Backup: Insert successful, ID: ' . $insert_id, 'Backup');
            
            return $insert_id;
            
        } catch (Exception $e) {
            Azure_Logger::error('Backup: Exception in create_backup_job: ' . $e->getMessage(), 'Backup');
            return false;
        }
    }
    
    /**
     * Create backup jobs table manually as fallback
     */
    private function create_backup_jobs_table_manual() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'azure_backup_jobs';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            backup_id varchar(255) NOT NULL,
            job_name varchar(255) NOT NULL,
            backup_types longtext,
            status varchar(50) DEFAULT 'pending',
            progress int(11) DEFAULT 0,
            message longtext,
            file_path varchar(500),
            file_size bigint(20) DEFAULT 0,
            azure_blob_name varchar(500),
            started_at datetime,
            completed_at datetime,
            error_message longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY backup_id (backup_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        Azure_Logger::debug('Backup: Manual table creation result: ' . json_encode($result), 'Backup');
        
        return $result;
    }
    
    /**
     * Update backup status
     */
    private function update_backup_status($job_id, $status, $error_message = null) {
        global $wpdb;
        
        $table = Azure_Database::get_table_name('backup_jobs');
        if (!$table) {
            return false;
        }
        
        $data = array('status' => $status);
        $formats = array('%s');
        
        if ($status === 'completed') {
            $data['completed_at'] = current_time('mysql');
            $formats[] = '%s';
        }
        
        if ($error_message) {
            $data['error_message'] = $error_message;
            $formats[] = '%s';
        }
        
        return $wpdb->update(
            $table,
            $data,
            array('id' => $job_id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Update backup blob information
     */
    private function update_backup_blob_info($job_id, $blob_name, $file_size) {
        global $wpdb;
        
        $table = Azure_Database::get_table_name('backup_jobs');
        if (!$table) {
            return false;
        }
        
        return $wpdb->update(
            $table,
            array(
                'azure_blob_name' => $blob_name,
                'file_size' => $file_size
            ),
            array('id' => $job_id),
            array('%s', '%d'),
            array('%d')
        );
    }
    
    /**
     * Clean up backup files
     */
    private function cleanup_backup_files($backup_dir, $archive_path = null) {
        // Remove temporary directory
        if (is_dir($backup_dir)) {
            $this->remove_directory($backup_dir);
        }
        
        // Remove archive if specified (after upload)
        if ($archive_path && file_exists($archive_path)) {
            unlink($archive_path);
        }
    }
    
    /**
     * Remove directory recursively
     */
    private function remove_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->remove_directory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
    
    /**
     * Send backup notification
     */
    private function send_backup_notification($job_id, $success, $message) {
        $notification_email = $this->settings['backup_notification_email'] ?? get_option('admin_email');
        
        if (empty($notification_email)) {
            return;
        }
        
        $subject = $success ? 'Backup Completed Successfully' : 'Backup Failed';
        $subject .= ' - ' . get_bloginfo('name');
        
        $body = "Backup notification from " . get_bloginfo('name') . "\n\n";
        $body .= "Status: " . ($success ? 'Success' : 'Failed') . "\n";
        $body .= "Message: " . $message . "\n";
        $body .= "Time: " . current_time('mysql') . "\n";
        $body .= "Site URL: " . get_site_url() . "\n";
        
        wp_mail($notification_email, $subject, $body);
    }
    
    /**
     * Run scheduled backup
     */
    public function run_scheduled_backup() {
        if (!Azure_Settings::get_setting('backup_schedule_enabled', false)) {
            return;
        }
        
        $backup_types = Azure_Settings::get_setting('backup_types', array('content', 'media', 'plugins', 'themes', 'database'));
        $backup_name = 'Scheduled Backup - ' . date('Y-m-d H:i:s');
        
        try {
            $this->create_backup($backup_name, $backup_types, true);
        } catch (Exception $e) {
            Azure_Logger::error('Backup: Scheduled backup failed: ' . $e->getMessage());
            $this->send_backup_notification(0, false, $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for starting backup
     */
    public function ajax_start_backup() {
        // Enhanced debugging for backup start
        Azure_Logger::debug('Backup: ajax_start_backup called', 'Backup');
        
        if (!current_user_can('manage_options')) {
            Azure_Logger::error('Backup: User lacks manage_options capability', 'Backup');
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            Azure_Logger::error('Backup: Invalid nonce', 'Backup');
            wp_send_json_error('Invalid security token');
        }
        
        Azure_Logger::info('Backup: Manual backup requested via AJAX', 'Backup');
        
        try {
            // Check if Azure_Settings class exists
            if (!class_exists('Azure_Settings')) {
                throw new Exception('Azure_Settings class not found');
            }
            
            // Check if Azure_Database class exists  
            if (!class_exists('Azure_Database')) {
                throw new Exception('Azure_Database class not found');
            }
            
            Azure_Logger::debug('Backup: Required classes found', 'Backup');
            
            $backup_types = Azure_Settings::get_setting('backup_types', array('content', 'media', 'plugins', 'themes', 'database'));
            $backup_name = 'Manual Backup - ' . date('Y-m-d H:i:s');
            
            Azure_Logger::debug('Backup: Settings retrieved - types: ' . json_encode($backup_types), 'Backup');
            
            // Generate backup ID for progress tracking
            $backup_id = 'backup_' . time() . '_' . wp_generate_password(8, false);
            Azure_Logger::debug('Backup: Generated backup ID: ' . $backup_id, 'Backup');
            
            // Check database connection
            global $wpdb;
            if (!$wpdb) {
                throw new Exception('WordPress database connection not available');
            }
            
            Azure_Logger::debug('Backup: Database connection confirmed', 'Backup');
            
            // Create backup job record (but don't run the backup yet)
            $job_id = $this->create_backup_job($backup_id, $backup_name, $backup_types, false);
            if (!$job_id) {
                throw new Exception('Failed to create backup job record - check database');
            }
            
            Azure_Logger::info('Backup: Created backup job with ID: ' . $backup_id . ' (Job ID: ' . $job_id . ')', 'Backup');
            
            // Schedule background backup processing
            $scheduled = wp_schedule_single_event(time(), 'azure_backup_process', array($backup_id));
            if ($scheduled === false) {
                throw new Exception('Failed to schedule background backup process');
            }
            
            Azure_Logger::debug('Backup: Background process scheduled successfully', 'Backup');
            
            wp_send_json_success(array(
                'message' => 'Backup started successfully',
                'backup_id' => $backup_id,
                'requires_progress' => true
            ));
            
        } catch (Exception $e) {
            Azure_Logger::error('Backup: Failed to start backup - Exception: ' . $e->getMessage(), 'Backup');
            Azure_Logger::error('Backup: Exception trace: ' . $e->getTraceAsString(), 'Backup');
            wp_send_json_error('Backup initialization failed: ' . $e->getMessage());
        } catch (Error $e) {
            Azure_Logger::error('Backup: Failed to start backup - Fatal Error: ' . $e->getMessage(), 'Backup');
            Azure_Logger::error('Backup: Error trace: ' . $e->getTraceAsString(), 'Backup');
            wp_send_json_error('Fatal error during backup initialization: ' . $e->getMessage());
        } catch (Throwable $e) {
            Azure_Logger::error('Backup: Failed to start backup - Throwable: ' . $e->getMessage(), 'Backup');
            wp_send_json_error('Unexpected error during backup initialization: ' . $e->getMessage());
        }
    }
    
    /**
     * Process backup in background
     */
    public function process_background_backup($backup_id) {
        if (empty($backup_id)) {
            Azure_Logger::error('Backup: Background process called without backup ID', 'Backup');
            return;
        }
        
        Azure_Logger::info('Backup: Starting background process for backup ID: ' . $backup_id, 'Backup');
        
        global $wpdb;
        $table = Azure_Database::get_table_name('backup_jobs');
        if (!$table) {
            Azure_Logger::error('Backup: Backup jobs table not found', 'Backup');
            return;
        }
        
        // Get the backup job
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE backup_id = %s",
            $backup_id
        ));
        
        if (!$job) {
            Azure_Logger::error('Backup: Job not found for backup ID: ' . $backup_id, 'Backup');
            return;
        }
        
        try {
            // Parse backup types from JSON
            $backup_types = json_decode($job->backup_types, true);
            if (!is_array($backup_types)) {
                $backup_types = array('content', 'media', 'plugins', 'themes', 'database');
            }
            
            // Update status to running
            $this->update_backup_progress($backup_id, 5, 'running', 'Initializing backup...');
            
            // Create backup directory
            $backup_dir = $this->get_backup_directory($backup_id);
            if (!wp_mkdir_p($backup_dir)) {
                throw new Exception('Failed to create backup directory: ' . $backup_dir);
            }
            
            // Process the backup
            $this->process_backup($job->id, $backup_id, $backup_types, $backup_dir);
            
            Azure_Logger::info('Backup: Background process completed successfully for: ' . $backup_id, 'Backup');
            
        } catch (Exception $e) {
            Azure_Logger::error('Backup: Background process failed for ' . $backup_id . ': ' . $e->getMessage(), 'Backup');
            $this->update_backup_progress($backup_id, 0, 'failed', 'Backup failed: ' . $e->getMessage());
            
            // Update job status
            $wpdb->update(
                $table,
                array('status' => 'failed', 'error_message' => $e->getMessage()),
                array('id' => $job->id),
                array('%s', '%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Update backup job progress
     */
    public function update_backup_progress($backup_id, $progress, $status = null, $message = null) {
        global $wpdb;
        
        $table = Azure_Database::get_table_name('backup_jobs');
        if (!$table) {
            return false;
        }
        
        $update_data = array(
            'progress' => $progress,
            'updated_at' => current_time('mysql')
        );
        
        if ($status) {
            $update_data['status'] = $status;
        }
        
        if ($message) {
            $update_data['message'] = $message;
        }
        
        $wpdb->update(
            $table,
            $update_data,
            array('backup_id' => $backup_id),
            array('%d', '%s', '%s', '%s'),
            array('%s')
        );
        
        Azure_Logger::debug('Backup: Progress updated - ' . $backup_id . ' (' . $progress . '%): ' . $message, 'Backup');
    }
    
    /**
     * AJAX handler for getting backup progress
     */
    public function ajax_get_backup_progress() {
        if (!current_user_can('manage_options') || !isset($_POST['backup_id'])) {
            wp_send_json_error('Unauthorized or missing backup ID');
        }
        
        global $wpdb;
        $backup_id = sanitize_text_field($_POST['backup_id']);
        
        $table = Azure_Database::get_table_name('backup_jobs');
        if (!$table) {
            wp_send_json_error('Backup jobs table not found');
        }
        
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE backup_id = %s",
            $backup_id
        ));
        
        if (!$job) {
            wp_send_json_error('Backup job not found');
        }
        
        wp_send_json_success(array(
            'backup_id' => $job->backup_id,
            'backup_name' => $job->job_name,
            'status' => $job->status,
            'progress' => isset($job->progress) ? (int)$job->progress : 0,
            'message' => isset($job->message) ? $job->message : 'Processing...',
            'created_at' => $job->created_at,
            'updated_at' => isset($job->updated_at) ? $job->updated_at : $job->created_at
        ));
    }
    
    /**
     * AJAX handler to manually trigger backup process (fallback)
     */
    public function ajax_trigger_backup_process() {
        if (!current_user_can('manage_options') || !isset($_POST['backup_id']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized');
        }
        
        $backup_id = sanitize_text_field($_POST['backup_id']);
        Azure_Logger::info('Backup: Manual trigger requested for backup ID: ' . $backup_id, 'Backup');
        
        // Check if backup is still pending
        global $wpdb;
        $table = Azure_Database::get_table_name('backup_jobs');
        if (!$table) {
            wp_send_json_error('Backup jobs table not found');
        }
        
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE backup_id = %s",
            $backup_id
        ));
        
        if (!$job) {
            wp_send_json_error('Backup job not found');
        }
        
        if ($job->status === 'pending') {
            // Trigger the backup process immediately
            $this->process_background_backup($backup_id);
            wp_send_json_success('Backup process triggered');
        } else {
            wp_send_json_success('Backup already ' . $job->status);
        }
    }
    
    /**
     * AJAX handler for getting backup jobs
     */
    public function ajax_get_backup_jobs() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_die('Unauthorized access');
        }
        
        global $wpdb;
        $table = Azure_Database::get_table_name('backup_jobs');
        
        if (!$table) {
            wp_send_json_error('Database table not found');
            return;
        }
        
        $jobs = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 10");
        
        ob_start();
        foreach ($jobs as $job) {
            $status_class = $job->status === 'completed' ? 'success' : ($job->status === 'failed' ? 'error' : 'warning');
            echo '<tr>';
            echo '<td>' . esc_html($job->job_name) . '</td>';
            echo '<td><span class="status-indicator ' . $status_class . '">' . esc_html($job->status) . '</span></td>';
            echo '<td>' . esc_html($job->created_at) . '</td>';
            echo '<td>' . ($job->file_size ? size_format($job->file_size) : '-') . '</td>';
            echo '<td>';
            if ($job->status === 'completed' && $job->azure_blob_name) {
                echo '<button class="button restore-backup" data-backup-id="' . $job->id . '">Restore</button>';
            }
            echo '</td>';
            echo '</tr>';
        }
        $output = ob_get_clean();
        
        wp_send_json_success($output);
    }
}
?>


