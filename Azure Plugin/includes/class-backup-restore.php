<?php
/**
 * Backup restore functionality for Azure Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Backup_Restore {
    
    private static $restore_in_progress = false;
    private $storage;
    
    public function __construct() {
        try {
            if (class_exists('Azure_Backup_Storage')) {
                $this->storage = new Azure_Backup_Storage();
            }
            
            // AJAX actions
            add_action('wp_ajax_azure_restore_backup', array($this, 'ajax_restore_backup'));
            add_action('wp_ajax_azure_get_restore_status', array($this, 'ajax_get_restore_status'));
            
        } catch (Exception $e) {
            if (class_exists('Azure_Logger')) {
                Azure_Logger::error('Backup Restore: Constructor error - ' . $e->getMessage());
            }
            $this->storage = null;
        }
    }
    
    /**
     * Restore backup from Azure Storage
     */
    public function restore_backup($backup_id, $restore_types = null) {
        if (self::$restore_in_progress) {
            throw new Exception('Another restore operation is already in progress.');
        }
        
        if (!$this->storage) {
            throw new Exception('Azure Storage is not configured.');
        }
        
        global $wpdb;
        $table = Azure_Database::get_table_name('backup_jobs');
        
        if (!$table) {
            throw new Exception('Database table not found.');
        }
        
        // Get backup information
        $backup = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND status = 'completed'",
            $backup_id
        ));
        
        if (!$backup) {
            throw new Exception('Backup not found or not completed.');
        }
        
        if (empty($backup->azure_blob_name)) {
            throw new Exception('Backup blob name not found.');
        }
        
        self::$restore_in_progress = true;
        
        try {
            Azure_Logger::info('Restore: Starting restore operation for backup: ' . $backup->job_name);
            Azure_Database::log_activity('backup', 'restore_started', 'backup', $backup_id);
            
            // Download backup from Azure Storage
            $restore_dir = $this->prepare_restore_directory();
            $archive_path = $restore_dir . '/backup.zip';
            
            $this->storage->download_backup($backup->azure_blob_name, $archive_path);
            
            // Extract backup archive
            $extract_dir = $restore_dir . '/extracted';
            if (!$this->extract_backup($archive_path, $extract_dir)) {
                throw new Exception('Failed to extract backup archive.');
            }
            
            // Determine what to restore
            $backup_types = json_decode($backup->backup_types, true);
            if ($restore_types) {
                $backup_types = array_intersect($backup_types, $restore_types);
            }
            
            // Perform restoration
            $this->perform_restore($extract_dir, $backup_types);
            
            // Clean up temporary files
            $this->cleanup_restore_files($restore_dir);
            
            Azure_Logger::info('Restore: Restore operation completed successfully');
            Azure_Database::log_activity('backup', 'restore_completed', 'backup', $backup_id);
            
            self::$restore_in_progress = false;
            
            return true;
            
        } catch (Exception $e) {
            Azure_Logger::error('Restore: Restore failed: ' . $e->getMessage());
            Azure_Database::log_activity('backup', 'restore_failed', 'backup', $backup_id, array('error' => $e->getMessage()));
            
            // Clean up on error
            if (isset($restore_dir)) {
                $this->cleanup_restore_files($restore_dir);
            }
            
            self::$restore_in_progress = false;
            
            throw $e;
        }
    }
    
    /**
     * Prepare restore directory
     */
    private function prepare_restore_directory() {
        $restore_dir = AZURE_PLUGIN_PATH . 'backups/restore_' . uniqid();
        
        if (!wp_mkdir_p($restore_dir)) {
            throw new Exception('Failed to create restore directory.');
        }
        
        return $restore_dir;
    }
    
    /**
     * Extract backup archive
     */
    private function extract_backup($archive_path, $extract_dir) {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive extension is not available.');
        }
        
        $zip = new ZipArchive();
        $result = $zip->open($archive_path);
        
        if ($result !== TRUE) {
            throw new Exception('Failed to open backup archive: ' . $result);
        }
        
        if (!wp_mkdir_p($extract_dir)) {
            $zip->close();
            throw new Exception('Failed to create extraction directory.');
        }
        
        $extracted = $zip->extractTo($extract_dir);
        $zip->close();
        
        if (!$extracted) {
            throw new Exception('Failed to extract backup archive.');
        }
        
        return true;
    }
    
    /**
     * Perform the actual restore operation
     */
    private function perform_restore($extract_dir, $backup_types) {
        // Create backup of current site before restore
        $this->create_pre_restore_backup();
        
        foreach ($backup_types as $type) {
            Azure_Logger::info("Restore: Restoring {$type}");
            
            switch ($type) {
                case 'database':
                    $this->restore_database($extract_dir);
                    break;
                case 'content':
                    $this->restore_content($extract_dir);
                    break;
                case 'media':
                    $this->restore_media($extract_dir);
                    break;
                case 'plugins':
                    $this->restore_plugins($extract_dir);
                    break;
                case 'themes':
                    $this->restore_themes($extract_dir);
                    break;
            }
        }
        
        // Clear caches after restore
        $this->clear_caches();
    }
    
    /**
     * Restore database
     */
    private function restore_database($extract_dir) {
        $sql_files = glob($extract_dir . '/database_*.sql');
        
        if (empty($sql_files)) {
            throw new Exception('Database backup file not found.');
        }
        
        $sql_file = $sql_files[0];
        $sql_content = file_get_contents($sql_file);
        
        if ($sql_content === false) {
            throw new Exception('Failed to read database backup file.');
        }
        
        global $wpdb;
        
        // Split SQL content into individual queries
        $queries = array_filter(explode(";\n", $sql_content));
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (empty($query) || strpos($query, '--') === 0) {
                continue;
            }
            
            $result = $wpdb->query($query);
            
            if ($result === false) {
                Azure_Logger::warning('Restore: Failed to execute query: ' . substr($query, 0, 100));
            }
        }
        
        Azure_Logger::info('Restore: Database restored successfully');
    }
    
    /**
     * Restore content files
     */
    private function restore_content($extract_dir) {
        $content_dirs = glob($extract_dir . '/content_*', GLOB_ONLYDIR);
        
        if (empty($content_dirs)) {
            Azure_Logger::warning('Restore: Content backup not found');
            return;
        }
        
        $content_dir = $content_dirs[0];
        $wp_content = WP_CONTENT_DIR;
        
        // Backup current content
        $backup_dir = $wp_content . '_backup_' . date('YmdHis');
        if (!rename($wp_content, $backup_dir)) {
            throw new Exception('Failed to backup current content directory.');
        }
        
        // Restore content
        if (!$this->copy_directory($content_dir, $wp_content)) {
            // Restore original on failure
            rename($backup_dir, $wp_content);
            throw new Exception('Failed to restore content directory.');
        }
        
        // Remove backup if successful
        $this->remove_directory($backup_dir);
        
        Azure_Logger::info('Restore: Content restored successfully');
    }
    
    /**
     * Restore media files
     */
    private function restore_media($extract_dir) {
        $media_dirs = glob($extract_dir . '/media_*', GLOB_ONLYDIR);
        
        if (empty($media_dirs)) {
            Azure_Logger::warning('Restore: Media backup not found');
            return;
        }
        
        $media_dir = $media_dirs[0];
        $uploads_dir = wp_upload_dir()['basedir'];
        
        // Backup current uploads
        $backup_dir = $uploads_dir . '_backup_' . date('YmdHis');
        if (is_dir($uploads_dir)) {
            if (!rename($uploads_dir, $backup_dir)) {
                throw new Exception('Failed to backup current uploads directory.');
            }
        }
        
        // Restore media
        if (!$this->copy_directory($media_dir, $uploads_dir)) {
            // Restore original on failure
            if (is_dir($backup_dir)) {
                rename($backup_dir, $uploads_dir);
            }
            throw new Exception('Failed to restore media directory.');
        }
        
        // Remove backup if successful
        if (is_dir($backup_dir)) {
            $this->remove_directory($backup_dir);
        }
        
        Azure_Logger::info('Restore: Media restored successfully');
    }
    
    /**
     * Restore plugins
     */
    private function restore_plugins($extract_dir) {
        $plugins_dirs = glob($extract_dir . '/plugins_*', GLOB_ONLYDIR);
        
        if (empty($plugins_dirs)) {
            Azure_Logger::warning('Restore: Plugins backup not found');
            return;
        }
        
        $plugins_dir = $plugins_dirs[0];
        $wp_plugins = WP_PLUGIN_DIR;
        
        // Deactivate all plugins before restore
        $active_plugins = get_option('active_plugins');
        update_option('active_plugins', array());
        
        // Backup current plugins
        $backup_dir = $wp_plugins . '_backup_' . date('YmdHis');
        if (!rename($wp_plugins, $backup_dir)) {
            // Restore active plugins on failure
            update_option('active_plugins', $active_plugins);
            throw new Exception('Failed to backup current plugins directory.');
        }
        
        // Restore plugins
        if (!$this->copy_directory($plugins_dir, $wp_plugins)) {
            // Restore original on failure
            rename($backup_dir, $wp_plugins);
            update_option('active_plugins', $active_plugins);
            throw new Exception('Failed to restore plugins directory.');
        }
        
        // Remove backup if successful
        $this->remove_directory($backup_dir);
        
        // Reactivate plugins
        update_option('active_plugins', $active_plugins);
        
        Azure_Logger::info('Restore: Plugins restored successfully');
    }
    
    /**
     * Restore themes
     */
    private function restore_themes($extract_dir) {
        $themes_dirs = glob($extract_dir . '/themes_*', GLOB_ONLYDIR);
        
        if (empty($themes_dirs)) {
            Azure_Logger::warning('Restore: Themes backup not found');
            return;
        }
        
        $themes_dir = $themes_dirs[0];
        $wp_themes = get_theme_root();
        
        // Backup current themes
        $backup_dir = $wp_themes . '_backup_' . date('YmdHis');
        if (!rename($wp_themes, $backup_dir)) {
            throw new Exception('Failed to backup current themes directory.');
        }
        
        // Restore themes
        if (!$this->copy_directory($themes_dir, $wp_themes)) {
            // Restore original on failure
            rename($backup_dir, $wp_themes);
            throw new Exception('Failed to restore themes directory.');
        }
        
        // Remove backup if successful
        $this->remove_directory($backup_dir);
        
        Azure_Logger::info('Restore: Themes restored successfully');
    }
    
    /**
     * Create backup before restore
     */
    private function create_pre_restore_backup() {
        try {
            if (class_exists('Azure_Backup')) {
                $backup = new Azure_Backup();
                $backup->create_backup(
                    'Pre-restore backup - ' . date('Y-m-d H:i:s'),
                    array('database', 'content', 'media'),
                    false
                );
            }
        } catch (Exception $e) {
            Azure_Logger::warning('Restore: Failed to create pre-restore backup: ' . $e->getMessage());
        }
    }
    
    /**
     * Copy directory recursively
     */
    private function copy_directory($source, $destination) {
        if (!is_dir($source)) {
            return false;
        }
        
        if (!wp_mkdir_p($destination)) {
            return false;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $source_path = $item->getPathname();
            $relative_path = str_replace($source, '', $source_path);
            $destination_path = $destination . $relative_path;
            
            if ($item->isDir()) {
                wp_mkdir_p($destination_path);
            } elseif ($item->isFile()) {
                wp_mkdir_p(dirname($destination_path));
                if (!copy($source_path, $destination_path)) {
                    return false;
                }
            }
        }
        
        return true;
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
     * Clear caches after restore
     */
    private function clear_caches() {
        // Clear WordPress caches
        wp_cache_flush();
        
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        // Clear common caching plugins
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        
        if (class_exists('autoptimizeCache')) {
            autoptimizeCache::clearall();
        }
        
        Azure_Logger::info('Restore: Caches cleared');
    }
    
    /**
     * Clean up restore files
     */
    private function cleanup_restore_files($restore_dir) {
        if (is_dir($restore_dir)) {
            $this->remove_directory($restore_dir);
        }
    }
    
    /**
     * AJAX handler for restore backup
     */
    public function ajax_restore_backup() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_die('Unauthorized access');
        }
        
        $backup_id = intval($_POST['backup_id']);
        $restore_types = isset($_POST['restore_types']) ? array_map('sanitize_text_field', $_POST['restore_types']) : null;
        
        try {
            $this->restore_backup($backup_id, $restore_types);
            
            wp_send_json_success('Restore completed successfully');
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for restore status
     */
    public function ajax_get_restore_status() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'azure_plugin_nonce')) {
            wp_die('Unauthorized access');
        }
        
        wp_send_json_success(array(
            'in_progress' => self::$restore_in_progress
        ));
    }
    
    /**
     * Check if restore is in progress
     */
    public static function is_restore_in_progress() {
        return self::$restore_in_progress;
    }
}
