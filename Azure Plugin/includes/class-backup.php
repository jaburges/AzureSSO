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
            add_action('wp_ajax_nopriv_azure_backup_process', array($this, 'process_backup_chunk'));
            add_action('wp_ajax_azure_backup_process', array($this, 'process_backup_chunk'));
            
            // AJAX actions for admin
            add_action('wp_ajax_azure_start_backup', array($this, 'ajax_start_backup'));
            add_action('wp_ajax_azure_get_backup_jobs', array($this, 'ajax_get_backup_jobs'));
            
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
        
        $backup_files = array();
        
        foreach ($backup_types as $type) {
            Azure_Logger::info("Backup: Processing {$type} backup");
            
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
        }
        
        // Create final archive
        $archive_path = $this->create_backup_archive($backup_dir, $backup_id, $backup_files);
        
        if (!$archive_path) {
            throw new Exception('Failed to create backup archive.');
        }
        
        // Upload to Azure Storage
        if (class_exists('Azure_Backup_Storage')) {
            $storage = new Azure_Backup_Storage();
            $blob_name = $storage->upload_backup($archive_path, $backup_id);
            
            if ($blob_name) {
                $this->update_backup_blob_info($job_id, $blob_name, filesize($archive_path));
                Azure_Logger::info('Backup: Successfully uploaded to Azure Storage: ' . $blob_name);
            } else {
                throw new Exception('Failed to upload backup to Azure Storage.');
            }
        }
        
        // Clean up local files
        $this->cleanup_backup_files($backup_dir, $archive_path);
        
        $this->update_backup_status($job_id, 'completed');
        
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
        
        $table = Azure_Database::get_table_name('backup_jobs');
        if (!$table) {
            return false;
        }
        
        $result = $wpdb->insert(
            $table,
            array(
                'job_name' => $backup_name,
                'backup_types' => json_encode($backup_types),
                'status' => 'pending',
                'started_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
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
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_die('Unauthorized access');
        }
        
        $backup_types = Azure_Settings::get_setting('backup_types', array('content', 'media', 'plugins', 'themes', 'database'));
        $backup_name = 'Manual Backup - ' . date('Y-m-d H:i:s');
        
        try {
            $backup_id = $this->create_backup($backup_name, $backup_types, false);
            
            wp_send_json_success(array(
                'message' => 'Backup started successfully',
                'backup_id' => $backup_id
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
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


