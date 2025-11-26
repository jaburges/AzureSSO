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
            add_action('wp_ajax_azure_cancel_all_backups', array($this, 'ajax_cancel_all_backups'));
            
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
        Azure_Logger::info('Backup: ========== process_backup() ENTRY ==========', 'Backup');
        Azure_Logger::info('Backup: Job ID: ' . $job_id, 'Backup');
        Azure_Logger::info('Backup: Backup ID: ' . $backup_id, 'Backup');
        Azure_Logger::info('Backup: Backup Types: ' . implode(', ', $backup_types), 'Backup');
        Azure_Logger::info('Backup: Backup Dir: ' . $backup_dir, 'Backup');
        
        // Set execution time limit to prevent timeout
        Azure_Logger::info('Backup: Setting execution limits for process_backup...', 'Backup');
        @set_time_limit(3600); // 1 hour max
        @ini_set('memory_limit', '512M'); // Increase memory limit
        
        Azure_Logger::info('Backup: Updating job status to RUNNING...', 'Backup');
        $this->update_backup_status($job_id, 'running');
        $this->update_backup_progress($backup_id, 10, 'running', 'Starting backup process...');
        
        $backup_files = array();
        $total_types = count($backup_types);
        $completed_types = 0;
        
        Azure_Logger::info('Backup: Starting backup of ' . $total_types . ' types...', 'Backup');
        
        foreach ($backup_types as $type) {
            Azure_Logger::info("Backup: -------- Processing {$type} backup --------", 'Backup');
            $progress = 10 + (40 * $completed_types / $total_types); // 10-50% for individual backups
            $this->update_backup_progress($backup_id, $progress, 'running', "Backing up {$type}...");
            
            $type_start_time = microtime(true);
            $files = array();
            
            try {
                switch ($type) {
                    case 'database':
                        Azure_Logger::info('Backup: Calling backup_database()...', 'Backup');
                        $files = $this->backup_database($backup_dir, $backup_id);
                        break;
                    case 'content':
                        Azure_Logger::info('Backup: Calling backup_content()...', 'Backup');
                        $files = $this->backup_content($backup_dir, $backup_id);
                        break;
                    case 'media':
                        Azure_Logger::info('Backup: Calling backup_media()...', 'Backup');
                        $files = $this->backup_media($backup_dir, $backup_id, $backup_id, $progress);
                        break;
                    case 'plugins':
                        Azure_Logger::info('Backup: Calling backup_plugins()...', 'Backup');
                        $files = $this->backup_plugins($backup_dir, $backup_id);
                        break;
                    case 'themes':
                        Azure_Logger::info('Backup: Calling backup_themes()...', 'Backup');
                        $files = $this->backup_themes($backup_dir, $backup_id);
                        break;
                }
                
                $type_elapsed = round(microtime(true) - $type_start_time, 2);
                $file_count = is_array($files) ? count($files) : 0;
                
                if ($files) {
                    $backup_files = array_merge($backup_files, $files);
                }
                
                // Update progress after each type completes
                $completed_types++;
                $progress_complete = 10 + (40 * $completed_types / $total_types);
                $this->update_backup_progress($backup_id, $progress_complete, 'running', "{$type} backup completed");
                Azure_Logger::info("Backup: COMPLETED {$type} backup - {$file_count} files in {$type_elapsed}s", 'Backup');
                
            } catch (Exception $e) {
                Azure_Logger::error("Backup: FAILED to backup {$type}: " . $e->getMessage(), 'Backup');
                Azure_Logger::error("Backup: Exception in {$type}: " . $e->getTraceAsString(), 'Backup');
                // Continue with other backup types even if one fails
                $completed_types++;
            }
        }
        
        Azure_Logger::info('Backup: All backup types processed. Total files collected: ' . count($backup_files), 'Backup');
        
        // Create final archive
        Azure_Logger::info('Backup: -------- Creating backup archive --------', 'Backup');
        $this->update_backup_progress($backup_id, 60, 'running', 'Creating backup archive...');
        
        $archive_start_time = microtime(true);
        $archive_path = $this->create_backup_archive($backup_dir, $backup_id, $backup_files);
        $archive_elapsed = round(microtime(true) - $archive_start_time, 2);
        
        Azure_Logger::info('Backup: Archive creation took ' . $archive_elapsed . 's', 'Backup');
        
        if (!$archive_path) {
            Azure_Logger::error('Backup: create_backup_archive() returned empty/null', 'Backup');
            throw new Exception('Failed to create backup archive.');
        }
        
        Azure_Logger::info('Backup: Archive created at: ' . $archive_path, 'Backup');
        
        // Upload to Azure Storage
        Azure_Logger::info('Backup: -------- Uploading to Azure Storage --------', 'Backup');
        $this->update_backup_progress($backup_id, 80, 'running', 'Uploading to Azure Storage...');
        
        // Verify archive exists and has content before upload
        if (!file_exists($archive_path)) {
            Azure_Logger::error('Backup: Archive file does NOT exist at: ' . $archive_path, 'Backup');
            throw new Exception('Backup archive not found at: ' . $archive_path);
        }
        
        $archive_size = filesize($archive_path);
        Azure_Logger::info('Backup: Archive file size: ' . $archive_size . ' bytes (' . $this->format_bytes($archive_size) . ')', 'Backup');
        
        if ($archive_size < 1024) { // Less than 1KB is suspicious
            Azure_Logger::error('Backup: Archive is suspiciously small: ' . $archive_size . ' bytes', 'Backup');
            throw new Exception('Backup archive is too small (' . $archive_size . ' bytes) - likely incomplete');
        }
        
        Azure_Logger::info('Backup: Archive verified - Size: ' . $this->format_bytes($archive_size) . ' at: ' . $archive_path, 'Backup');
        
        Azure_Logger::info('Backup: Checking for Azure_Backup_Storage class...', 'Backup');
        if (class_exists('Azure_Backup_Storage')) {
            Azure_Logger::info('Backup: Azure_Backup_Storage class exists, instantiating...', 'Backup');
            $storage = new Azure_Backup_Storage();
            
            // Test storage connection before upload
            Azure_Logger::info('Backup: Testing Azure Storage connection...', 'Backup');
            $storage_test = $storage->test_connection();
            
            if (!$storage_test['success']) {
                Azure_Logger::error('Backup: Azure Storage connection FAILED: ' . $storage_test['message'], 'Backup');
                throw new Exception('Azure Storage connection failed: ' . $storage_test['message']);
            }
            
            Azure_Logger::info('Backup: Azure Storage connection verified successfully', 'Backup');
            Azure_Logger::info('Backup: Starting upload to Azure Storage...', 'Backup');
            
            $upload_start_time = microtime(true);
            $blob_name = $storage->upload_backup($archive_path, $backup_id);
            $upload_elapsed = round(microtime(true) - $upload_start_time, 2);
            
            Azure_Logger::info('Backup: Upload took ' . $upload_elapsed . 's', 'Backup');
            
            if ($blob_name) {
                Azure_Logger::info('Backup: Upload successful! Blob name: ' . $blob_name, 'Backup');
                $this->update_backup_blob_info($job_id, $blob_name, $archive_size);
                Azure_Logger::info('Backup: Successfully uploaded to Azure Storage: ' . $blob_name . ' (' . $this->format_bytes($archive_size) . ')', 'Backup');
                $this->update_backup_progress($backup_id, 90, 'running', 'Upload completed: ' . $blob_name);
            } else {
                Azure_Logger::error('Backup: Upload FAILED - no blob name returned', 'Backup');
                throw new Exception('Failed to upload backup to Azure Storage - no blob name returned');
            }
        } else {
            Azure_Logger::error('Backup: Azure_Backup_Storage class NOT FOUND', 'Backup');
            throw new Exception('Azure_Backup_Storage class not available');
        }
        
        // Clean up local files
        Azure_Logger::info('Backup: -------- Cleaning up temporary files --------', 'Backup');
        $this->update_backup_progress($backup_id, 95, 'running', 'Cleaning up temporary files...');
        $this->cleanup_backup_files($backup_dir, $archive_path);
        Azure_Logger::info('Backup: Cleanup completed', 'Backup');
        
        // Mark as complete
        Azure_Logger::info('Backup: -------- Marking backup as COMPLETED --------', 'Backup');
        $this->update_backup_status($job_id, 'completed');
        $this->update_backup_progress($backup_id, 100, 'completed', 'Backup completed successfully!');
        
        self::$backup_in_progress = false;
        self::$current_backup_id = null;
        
        Azure_Logger::info('Backup: ========== process_backup() COMPLETED SUCCESSFULLY ==========', 'Backup');
        
        // Send notification if enabled
        if ($this->settings['backup_email_notifications'] ?? false) {
            Azure_Logger::info('Backup: Sending email notification...', 'Backup');
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
    private function backup_media($backup_dir, $backup_id, $progress_backup_id = null, $base_progress = 0) {
        $media_dir = $backup_dir . '/media_' . $backup_id;
        wp_mkdir_p($media_dir);
        
        $uploads_dir = wp_upload_dir()['basedir'];
        
        if (!is_dir($uploads_dir)) {
            Azure_Logger::warning('Backup: Uploads directory not found: ' . $uploads_dir);
            return array();
        }
        
        // Update progress at start
        if ($progress_backup_id) {
            $this->update_backup_progress($progress_backup_id, $base_progress, 'running', 'Scanning media files...');
        }
        
        return $this->copy_directory($uploads_dir, $media_dir, array(), $progress_backup_id, $base_progress);
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
    private function copy_directory($source, $destination, $exclude_dirs = array(), $progress_backup_id = null, $base_progress = 0) {
        $files = array();
        $file_count = 0;
        $skipped_count = 0;
        $last_progress_update = time();
        
        if (!is_dir($source)) {
            return $files;
        }
        
        try {
            // Use SKIP_DOTS and don't follow symlinks to avoid broken symlink issues
            // Also catch UnexpectedValueException which occurs with broken symlinks
            $flags = RecursiveDirectoryIterator::SKIP_DOTS;
            
            $directory = new RecursiveDirectoryIterator($source, $flags);
            $iterator = new RecursiveIteratorIterator(
                $directory,
                RecursiveIteratorIterator::SELF_FIRST,
                RecursiveIteratorIterator::CATCH_GET_CHILD // Skip directories that can't be accessed
            );
            
            foreach ($iterator as $item) {
                try {
                    // Heartbeat - update progress every 10 seconds to show we're still working
                    if ($progress_backup_id && (time() - $last_progress_update) > 10) {
                        $this->update_backup_progress(
                            $progress_backup_id, 
                            $base_progress, 
                            'running', 
                            "Copying files... ({$file_count} processed, {$skipped_count} skipped)"
                        );
                        $last_progress_update = time();
                        
                        // Reset execution time limit to prevent timeout
                        @set_time_limit(600); // Add another 10 minutes
                    }
                    
                    $source_path = $item->getPathname();
                    
                    // Skip broken symlinks
                    if ($item->isLink() && !file_exists($item->getRealPath())) {
                        Azure_Logger::debug("Backup: Skipping broken symlink: {$source_path}");
                        $skipped_count++;
                        continue;
                    }
                    
                    // Skip if the path doesn't actually exist (can happen with some edge cases)
                    if (!file_exists($source_path)) {
                        Azure_Logger::debug("Backup: Skipping non-existent path: {$source_path}");
                        $skipped_count++;
                        continue;
                    }
                    
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
                        // Skip very large files (> 100MB) to prevent memory issues
                        $file_size = @$item->getSize();
                        if ($file_size === false) {
                            Azure_Logger::debug("Backup: Skipping unreadable file: {$source_path}");
                            $skipped_count++;
                            continue;
                        }
                        if ($file_size > 100 * 1024 * 1024) {
                            Azure_Logger::warning("Backup: Skipping large file (>100MB): {$source_path}");
                            $skipped_count++;
                            continue;
                        }
                        
                        wp_mkdir_p(dirname($destination_path));
                        if (@copy($source_path, $destination_path)) {
                            $files[] = $destination_path;
                            $file_count++;
                        } else {
                            Azure_Logger::warning("Backup: Failed to copy file: {$source_path}");
                            $skipped_count++;
                        }
                    }
                } catch (Exception $item_exception) {
                    // Log and continue - don't let one bad file stop the whole backup
                    Azure_Logger::warning("Backup: Exception processing item: " . $item_exception->getMessage());
                    $skipped_count++;
                    continue;
                }
            }
            
            // Final progress update
            if ($progress_backup_id) {
                $this->update_backup_progress(
                    $progress_backup_id, 
                    $base_progress, 
                    'running', 
                    "Copied {$file_count} files" . ($skipped_count > 0 ? " ({$skipped_count} skipped)" : "")
                );
            }
            
        } catch (UnexpectedValueException $e) {
            // This occurs when RecursiveDirectoryIterator encounters a broken symlink or inaccessible directory
            Azure_Logger::error("Backup: Directory access error in copy_directory: " . $e->getMessage());
            // Return what we've copied so far
        } catch (Exception $e) {
            Azure_Logger::error("Backup: Exception in copy_directory: " . $e->getMessage());
            // Return what we've copied so far
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
        
        // Ensure backups directory exists
        $backups_dir = AZURE_PLUGIN_PATH . 'backups/';
        if (!is_dir($backups_dir)) {
            wp_mkdir_p($backups_dir);
        }
        
        $zip = new ZipArchive();
        $result = $zip->open($archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        
        if ($result !== TRUE) {
            $error_messages = array(
                ZipArchive::ER_EXISTS => 'File already exists',
                ZipArchive::ER_INCONS => 'Zip archive inconsistent',
                ZipArchive::ER_INVAL => 'Invalid argument',
                ZipArchive::ER_MEMORY => 'Memory allocation failure',
                ZipArchive::ER_NOENT => 'No such file',
                ZipArchive::ER_NOZIP => 'Not a zip archive',
                ZipArchive::ER_OPEN => 'Cannot open file',
                ZipArchive::ER_READ => 'Read error',
                ZipArchive::ER_SEEK => 'Seek error',
            );
            $error_msg = isset($error_messages[$result]) ? $error_messages[$result] : 'Unknown error: ' . $result;
            throw new Exception('Failed to create backup archive: ' . $error_msg);
        }
        
        $added_count = 0;
        $failed_count = 0;
        
        // Add all files to archive
        foreach ($files as $file) {
            // Skip if file doesn't exist or isn't readable
            if (!file_exists($file) || !is_readable($file)) {
                Azure_Logger::warning("Backup: Skipping non-existent/unreadable file in archive: {$file}");
                $failed_count++;
                continue;
            }
            
            $relative_name = str_replace($backup_dir . '/', '', $file);
            
            // Normalize path separators for zip
            $relative_name = str_replace('\\', '/', $relative_name);
            
            if ($zip->addFile($file, $relative_name)) {
                $added_count++;
            } else {
                Azure_Logger::warning("Backup: Failed to add file to archive: {$file}");
                $failed_count++;
            }
        }
        
        Azure_Logger::info("Backup: Added {$added_count} files to archive, {$failed_count} failed");
        
        $zip->close();
        
        if (!file_exists($archive_path)) {
            throw new Exception('Failed to create backup archive file.');
        }
        
        $archive_size = filesize($archive_path);
        Azure_Logger::info("Backup: Archive created: {$archive_path} (" . size_format($archive_size) . ")");
        
        return $archive_path;
    }
    
    /**
     * Get backup directory path
     */
    private function get_backup_directory($backup_id) {
        return AZURE_PLUGIN_PATH . 'backups/temp_' . $backup_id;
    }
    
    /**
     * Spawn the backup cron process immediately
     * WordPress cron is "lazy" - it only runs when someone visits the site.
     * This function triggers the cron immediately via a non-blocking HTTP request.
     */
    private function spawn_backup_cron($backup_id) {
        Azure_Logger::info('Backup: ========== SPAWN CRON START ==========', 'Backup');
        Azure_Logger::info('Backup: Attempting to spawn cron for backup: ' . $backup_id, 'Backup');
        
        // First, try to spawn WordPress cron
        $cron_url = site_url('wp-cron.php');
        
        Azure_Logger::info('Backup: Cron URL: ' . $cron_url, 'Backup');
        Azure_Logger::info('Backup: Site URL: ' . site_url(), 'Backup');
        Azure_Logger::info('Backup: Home URL: ' . home_url(), 'Backup');
        
        // Check if DISABLE_WP_CRON is set
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            Azure_Logger::warning('Backup: DISABLE_WP_CRON is TRUE - WordPress cron is disabled!', 'Backup');
            Azure_Logger::warning('Backup: Will attempt direct backup execution instead', 'Backup');
            
            // Try to run the backup directly
            $this->run_backup_directly($backup_id);
            return;
        }
        
        // Use wp_remote_post with blocking=false to trigger cron without waiting
        $args = array(
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'body'      => array(
                'doing_wp_cron' => sprintf('%.22F', microtime(true))
            )
        );
        
        Azure_Logger::info('Backup: Sending cron spawn request...', 'Backup');
        $response = wp_remote_post($cron_url, $args);
        
        if (is_wp_error($response)) {
            Azure_Logger::error('Backup: Cron spawn FAILED: ' . $response->get_error_message(), 'Backup');
            Azure_Logger::info('Backup: Will try alternative method - direct execution', 'Backup');
            
            // Alternative: Try to run the backup directly
            $this->run_backup_directly($backup_id);
        } else {
            Azure_Logger::info('Backup: Cron spawn request sent successfully (non-blocking)', 'Backup');
            
            // Also set up the fallback transient
            $this->schedule_ajax_fallback($backup_id);
        }
        
        Azure_Logger::info('Backup: ========== SPAWN CRON END ==========', 'Backup');
    }
    
    /**
     * Run backup directly (fallback when cron doesn't work)
     */
    private function run_backup_directly($backup_id) {
        Azure_Logger::info('Backup: ========== DIRECT EXECUTION START ==========', 'Backup');
        Azure_Logger::info('Backup: Attempting direct backup execution for: ' . $backup_id, 'Backup');
        
        // Check if we're already in a cron context
        if (defined('DOING_CRON') && DOING_CRON) {
            Azure_Logger::info('Backup: Already in DOING_CRON context, calling process_background_backup directly', 'Backup');
            $this->process_background_backup($backup_id);
            return;
        }
        
        // Try a loopback request to admin-ajax.php to trigger the backup
        $ajax_url = admin_url('admin-ajax.php');
        Azure_Logger::info('Backup: Trying loopback to: ' . $ajax_url, 'Backup');
        
        $args = array(
            'timeout'   => 1, // Give it 1 second to start
            'blocking'  => false,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'body'      => array(
                'action'    => 'azure_trigger_backup_process',
                'backup_id' => $backup_id,
                'nonce'     => wp_create_nonce('azure_plugin_nonce')
            )
        );
        
        $response = wp_remote_post($ajax_url, $args);
        
        if (is_wp_error($response)) {
            Azure_Logger::error('Backup: Loopback request FAILED: ' . $response->get_error_message(), 'Backup');
            
            // Last resort: Try to run directly in this request
            Azure_Logger::warning('Backup: Attempting synchronous backup execution as last resort', 'Backup');
            
            // Set a flag to prevent infinite loops
            if (!get_transient('azure_backup_direct_running_' . $backup_id)) {
                set_transient('azure_backup_direct_running_' . $backup_id, true, 300);
                
                // Run the backup directly (this will block the current request)
                $this->process_background_backup($backup_id);
                
                delete_transient('azure_backup_direct_running_' . $backup_id);
            } else {
                Azure_Logger::warning('Backup: Direct execution already in progress for: ' . $backup_id, 'Backup');
            }
        } else {
            Azure_Logger::info('Backup: Loopback request sent successfully', 'Backup');
        }
        
        Azure_Logger::info('Backup: ========== DIRECT EXECUTION END ==========', 'Backup');
    }
    
    /**
     * Schedule an AJAX fallback for backup processing
     * This creates a transient that the progress checker can use to trigger the backup
     */
    private function schedule_ajax_fallback($backup_id) {
        // Set a transient that indicates this backup needs to be triggered
        set_transient('azure_backup_needs_trigger_' . $backup_id, true, 300); // 5 minutes
        Azure_Logger::info('Backup: Set AJAX fallback transient for: ' . $backup_id, 'Backup');
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
        
        try {
            $files = @scandir($dir);
            if ($files === false) {
                Azure_Logger::warning("Backup: Cannot scan directory for removal: {$dir}");
                return;
            }
            
            $files = array_diff($files, array('.', '..'));
            
            foreach ($files as $file) {
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                
                // Handle symlinks - just unlink them, don't follow
                if (is_link($path)) {
                    @unlink($path);
                    continue;
                }
                
                if (is_dir($path)) {
                    $this->remove_directory($path);
                } else {
                    @unlink($path);
                }
            }
            
            @rmdir($dir);
        } catch (Exception $e) {
            Azure_Logger::warning("Backup: Exception removing directory {$dir}: " . $e->getMessage());
        }
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
                Azure_Logger::warning('Backup: wp_schedule_single_event returned false, will try direct spawn', 'Backup');
            }
            
            Azure_Logger::debug('Backup: Background process scheduled, now spawning cron', 'Backup');
            
            // Spawn the cron immediately - don't wait for a page visit
            // This is crucial because WordPress cron is "lazy" and only runs on page visits
            $this->spawn_backup_cron($backup_id);
            
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
        Azure_Logger::info('Backup: ########## BACKGROUND PROCESS ENTRY ##########', 'Backup');
        Azure_Logger::info('Backup: Backup ID: ' . ($backup_id ?: 'EMPTY'), 'Backup');
        Azure_Logger::info('Backup: PHP Version: ' . PHP_VERSION, 'Backup');
        Azure_Logger::info('Backup: Memory Limit: ' . ini_get('memory_limit'), 'Backup');
        Azure_Logger::info('Backup: Max Execution Time: ' . ini_get('max_execution_time'), 'Backup');
        Azure_Logger::info('Backup: DOING_CRON: ' . (defined('DOING_CRON') && DOING_CRON ? 'true' : 'false'), 'Backup');
        Azure_Logger::info('Backup: DOING_AJAX: ' . (defined('DOING_AJAX') && DOING_AJAX ? 'true' : 'false'), 'Backup');
        
        if (empty($backup_id)) {
            Azure_Logger::error('Backup: Background process called without backup ID - ABORTING', 'Backup');
            return;
        }
        
        // Set execution limits
        Azure_Logger::info('Backup: Setting execution limits...', 'Backup');
        $time_result = @set_time_limit(3600); // 1 hour
        $memory_result = @ini_set('memory_limit', '512M');
        Azure_Logger::info('Backup: set_time_limit result: ' . ($time_result ? 'success' : 'failed/ignored'), 'Backup');
        Azure_Logger::info('Backup: ini_set memory_limit result: ' . ($memory_result ?: 'failed/ignored'), 'Backup');
        Azure_Logger::info('Backup: New Memory Limit: ' . ini_get('memory_limit'), 'Backup');
        Azure_Logger::info('Backup: New Max Execution Time: ' . ini_get('max_execution_time'), 'Backup');
        
        global $wpdb;
        Azure_Logger::info('Backup: Getting backup_jobs table name...', 'Backup');
        $table = Azure_Database::get_table_name('backup_jobs');
        if (!$table) {
            Azure_Logger::error('Backup: Backup jobs table not found - ABORTING', 'Backup');
            return;
        }
        Azure_Logger::info('Backup: Table name: ' . $table, 'Backup');
        
        // Get the backup job
        Azure_Logger::info('Backup: Fetching job from database...', 'Backup');
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE backup_id = %s",
            $backup_id
        ));
        
        if (!$job) {
            Azure_Logger::error('Backup: Job not found for backup ID: ' . $backup_id . ' - ABORTING', 'Backup');
            Azure_Logger::error('Backup: Last DB error: ' . $wpdb->last_error, 'Backup');
            return;
        }
        
        Azure_Logger::info('Backup: Job found successfully', 'Backup');
        Azure_Logger::info('Backup: Job ID: ' . $job->id, 'Backup');
        Azure_Logger::info('Backup: Job Status: ' . $job->status, 'Backup');
        Azure_Logger::info('Backup: Job Types: ' . $job->backup_types, 'Backup');
        Azure_Logger::info('Backup: Job Created: ' . ($job->created_at ?? $job->started_at ?? 'unknown'), 'Backup');
        
        // Check if job is already running or completed
        if ($job->status === 'running') {
            Azure_Logger::warning('Backup: Job is already running - checking if stale...', 'Backup');
            // Allow re-processing if job has been "running" for more than 10 minutes
            $job_time = strtotime($job->created_at ?? $job->started_at ?? 'now');
            if ((time() - $job_time) < 600) {
                Azure_Logger::warning('Backup: Job started less than 10 minutes ago, skipping to avoid duplicate processing', 'Backup');
                return;
            }
            Azure_Logger::warning('Backup: Job appears stale (>10 min), reprocessing...', 'Backup');
        } elseif ($job->status === 'completed') {
            Azure_Logger::info('Backup: Job already completed, skipping', 'Backup');
            return;
        } elseif ($job->status === 'failed' || $job->status === 'cancelled') {
            Azure_Logger::info('Backup: Job status is ' . $job->status . ', skipping', 'Backup');
            return;
        }
        
        try {
            // Parse backup types from JSON
            Azure_Logger::info('Backup: Parsing backup types...', 'Backup');
            $backup_types = json_decode($job->backup_types, true);
            if (!is_array($backup_types)) {
                Azure_Logger::warning('Backup: Failed to parse backup_types, using defaults', 'Backup');
                $backup_types = array('content', 'media', 'plugins', 'themes', 'database');
            }
            
            Azure_Logger::info('Backup: ========== STARTING BACKUP PROCESS ==========', 'Backup');
            Azure_Logger::info('Backup: Processing ' . count($backup_types) . ' backup types: ' . implode(', ', $backup_types), 'Backup');
            
            // Update status to running
            Azure_Logger::info('Backup: Updating job status to RUNNING...', 'Backup');
            $this->update_backup_progress($backup_id, 5, 'running', 'Initializing backup...');
            Azure_Logger::info('Backup: Job status updated to RUNNING', 'Backup');
            
            // Create backup directory
            Azure_Logger::info('Backup: Creating backup directory...', 'Backup');
            $backup_dir = $this->get_backup_directory($backup_id);
            Azure_Logger::info('Backup: Backup directory path: ' . $backup_dir, 'Backup');
            
            if (!wp_mkdir_p($backup_dir)) {
                Azure_Logger::error('Backup: FAILED to create backup directory: ' . $backup_dir, 'Backup');
                throw new Exception('Failed to create backup directory: ' . $backup_dir);
            }
            
            Azure_Logger::info('Backup: Backup directory created successfully', 'Backup');
            Azure_Logger::info('Backup: Directory exists: ' . (is_dir($backup_dir) ? 'YES' : 'NO'), 'Backup');
            Azure_Logger::info('Backup: Directory writable: ' . (is_writable($backup_dir) ? 'YES' : 'NO'), 'Backup');
            
            // Process the backup
            Azure_Logger::info('Backup: >>>>>> CALLING process_backup() >>>>>>', 'Backup');
            $this->process_backup($job->id, $backup_id, $backup_types, $backup_dir);
            
            Azure_Logger::info('Backup: <<<<<< process_backup() COMPLETED <<<<<<', 'Backup');
            Azure_Logger::info('Backup: ########## BACKGROUND PROCESS COMPLETED SUCCESSFULLY ##########', 'Backup');
            
        } catch (Exception $e) {
            Azure_Logger::error('Backup: !!!!! EXCEPTION IN BACKGROUND PROCESS !!!!!', 'Backup');
            Azure_Logger::error('Backup: Exception Message: ' . $e->getMessage(), 'Backup');
            Azure_Logger::error('Backup: Exception File: ' . $e->getFile() . ':' . $e->getLine(), 'Backup');
            Azure_Logger::error('Backup: Exception trace: ' . $e->getTraceAsString(), 'Backup');
            $this->update_backup_progress($backup_id, 0, 'failed', 'Backup failed: ' . $e->getMessage());
            
            // Update job status
            $wpdb->update(
                $table,
                array('status' => 'failed', 'error_message' => $e->getMessage()),
                array('id' => $job->id),
                array('%s', '%s'),
                array('%d')
            );
        } catch (Error $e) {
            Azure_Logger::error('Backup: !!!!! FATAL ERROR IN BACKGROUND PROCESS !!!!!', 'Backup');
            Azure_Logger::error('Backup: Error Message: ' . $e->getMessage(), 'Backup');
            Azure_Logger::error('Backup: Error File: ' . $e->getFile() . ':' . $e->getLine(), 'Backup');
            Azure_Logger::error('Backup: Error trace: ' . $e->getTraceAsString(), 'Backup');
            $this->update_backup_progress($backup_id, 0, 'failed', 'Fatal error: ' . $e->getMessage());
            
            // Update job status
            $wpdb->update(
                $table,
                array('status' => 'failed', 'error_message' => 'Fatal error: ' . $e->getMessage()),
                array('id' => $job->id),
                array('%s', '%s'),
                array('%d')
            );
        }
        
        Azure_Logger::info('Backup: ########## BACKGROUND PROCESS EXIT ##########', 'Backup');
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
        
        // Check if backup is stuck in 'pending' status for more than 30 seconds
        // This can happen if WordPress cron didn't trigger properly
        if ($job->status === 'pending') {
            $created_time = strtotime($job->created_at ?? $job->started_at);
            $elapsed = time() - $created_time;
            
            if ($elapsed > 30) {
                Azure_Logger::warning("Backup: Job {$backup_id} stuck in pending for {$elapsed}s, triggering directly", 'Backup');
                
                // Try to trigger the backup directly
                // Use a non-blocking approach to avoid timeout
                $this->trigger_backup_async($backup_id);
                
                // Return a message indicating we're retrying
                wp_send_json_success(array(
                    'backup_id' => $job->backup_id,
                    'backup_name' => $job->job_name,
                    'status' => 'pending',
                    'progress' => 2,
                    'message' => 'Backup is initializing... (retrying trigger)',
                    'created_at' => $job->created_at,
                    'updated_at' => isset($job->updated_at) ? $job->updated_at : $job->created_at
                ));
            }
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
     * Trigger backup processing asynchronously
     */
    private function trigger_backup_async($backup_id) {
        // Check if we've already tried to trigger this backup recently
        $trigger_key = 'azure_backup_trigger_' . $backup_id;
        if (get_transient($trigger_key)) {
            Azure_Logger::debug("Backup: Already attempted to trigger {$backup_id} recently, skipping", 'Backup');
            return;
        }
        
        // Set a transient to prevent multiple triggers
        set_transient($trigger_key, true, 60); // 1 minute cooldown
        
        // Try spawning cron again
        $this->spawn_backup_cron($backup_id);
        
        // Also try a direct loopback request to trigger the backup
        $trigger_url = admin_url('admin-ajax.php');
        
        $args = array(
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'body'      => array(
                'action'    => 'azure_trigger_backup_process',
                'backup_id' => $backup_id,
                'nonce'     => wp_create_nonce('azure_plugin_nonce')
            )
        );
        
        wp_remote_post($trigger_url, $args);
        Azure_Logger::debug("Backup: Sent async trigger request for {$backup_id}", 'Backup');
    }
    
    /**
     * AJAX handler to cancel all running backups
     */
    public function ajax_cancel_all_backups() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        $table = Azure_Database::get_table_name('backup_jobs');
        
        if (!$table) {
            wp_send_json_error('Backup jobs table not found');
        }
        
        // Get count of running AND pending backups (pending shows as "Initializing")
        $stalled_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status IN ('running', 'pending')");
        
        if ($stalled_count == 0) {
            wp_send_json_success(array(
                'cancelled' => 0,
                'message' => 'No running or pending backups to cancel'
            ));
        }
        
        // Update all running backups to 'cancelled' status
        $updated = $wpdb->update(
            $table,
            array(
                'status' => 'cancelled',
                'error_message' => 'Cancelled by administrator',
                'message' => 'Backup cancelled by administrator',
                'progress' => 0
            ),
            array('status' => 'running'),
            array('%s', '%s', '%s', '%d'),
            array('%s')
        );
        
        // Also cancel any 'pending' backups (these show as "Initializing" in UI)
        $pending_updated = $wpdb->update(
            $table,
            array(
                'status' => 'cancelled',
                'error_message' => 'Cancelled by administrator - backup was stuck in pending/initializing state',
                'message' => 'Backup cancelled by administrator'
            ),
            array('status' => 'pending'),
            array('%s', '%s', '%s'),
            array('%s')
        );
        
        // Clear any scheduled backup events for these jobs
        $pending_jobs = $wpdb->get_results("SELECT backup_id FROM {$table} WHERE status = 'cancelled'");
        foreach ($pending_jobs as $job) {
            wp_clear_scheduled_hook('azure_backup_process', array($job->backup_id));
        }
        
        // Reset the backup in progress flag
        self::$backup_in_progress = false;
        self::$current_backup_id = null;
        
        // Clean up any temp backup directories
        $this->cleanup_stale_temp_directories();
        
        $total_cancelled = ($updated !== false ? $updated : 0) + ($pending_updated !== false ? $pending_updated : 0);
        
        Azure_Logger::info("Backup: Cancelled {$total_cancelled} backup jobs by administrator", 'Backup');
        
        wp_send_json_success(array(
            'cancelled' => $total_cancelled,
            'message' => "Successfully cancelled {$total_cancelled} backup job(s)"
        ));
    }
    
    /**
     * Clean up stale temporary backup directories
     */
    private function cleanup_stale_temp_directories() {
        $backups_dir = AZURE_PLUGIN_PATH . 'backups/';
        
        if (!is_dir($backups_dir)) {
            return;
        }
        
        try {
            $items = @scandir($backups_dir);
            if ($items === false) {
                return;
            }
            
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                
                $path = $backups_dir . $item;
                
                // Remove temp directories (they start with "temp_")
                if (is_dir($path) && strpos($item, 'temp_') === 0) {
                    Azure_Logger::info("Backup: Cleaning up stale temp directory: {$item}", 'Backup');
                    $this->remove_directory($path);
                }
                
                // Also remove orphaned zip files older than 24 hours
                if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'zip') {
                    $file_age = time() - filemtime($path);
                    if ($file_age > 86400) { // 24 hours
                        Azure_Logger::info("Backup: Removing orphaned zip file: {$item}", 'Backup');
                        @unlink($path);
                    }
                }
            }
        } catch (Exception $e) {
            Azure_Logger::warning("Backup: Error cleaning up temp directories: " . $e->getMessage(), 'Backup');
        }
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
