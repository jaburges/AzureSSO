<?php
/**
 * Azure Plugin Logger Class - Enhanced with log rotation and crash-safe logging
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Logger {
    
    private static $log_file = '';
    private static $max_file_size = 20971520; // 20MB in bytes
    private static $initialized = false;
    
    public static function is_initialized() {
        return self::$initialized;
    }
    
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        self::$log_file = AZURE_PLUGIN_PATH . 'logs.md';
        self::$initialized = true;
        
        // Create log file header if it doesn't exist
        if (!file_exists(self::$log_file)) {
            $header = "# Microsoft WP Debug Logs\n\n";
            $header .= "**Started:** " . date('Y-m-d H:i:s') . "  \n";
            $header .= "**Plugin Version:** " . (defined('AZURE_PLUGIN_VERSION') ? AZURE_PLUGIN_VERSION : 'Unknown') . "  \n";
            $header .= "**WordPress Version:** " . get_bloginfo('version') . "  \n";
            $header .= "**PHP Version:** " . PHP_VERSION . "  \n\n";
            $header .= "---\n\n";
            
            self::write_to_file($header);
        }
        
        // Check file size and rotate if needed
        self::rotate_log_if_needed();
    }
    
    /**
     * Standard logging methods
     */
    public static function info($message, $context = array()) {
        self::log('INFO', $message, $context, '✅');
    }
    
    public static function error($message, $context = array()) {
        self::log('ERROR', $message, $context, '❌');
    }
    
    public static function warning($message, $context = array()) {
        self::log('WARNING', $message, $context, '⚠️');
    }
    
    public static function debug($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::log('DEBUG', $message, $context, '🔍');
        }
    }
    
    /**
     * Enhanced logging methods with custom emojis and formatting
     */
    public static function step($message, $step_number = '', $emoji = '⏳') {
        $prefix = !empty($step_number) ? "[STEP {$step_number}]" : "[STEP]";
        self::log_formatted($emoji, $prefix, $message);
    }
    
    public static function success($message, $category = 'SUCCESS', $emoji = '🎉') {
        self::log_formatted($emoji, "[{$category}]", $message);
    }
    
    public static function loading($message, $category = 'LOAD', $emoji = '⏳') {
        self::log_formatted($emoji, "[{$category}]", $message);
    }
    
    public static function complete($message, $category = 'COMPLETE', $emoji = '✅') {
        self::log_formatted($emoji, "[{$category}]", $message);
    }
    
    public static function fatal($message, $location = '', $emoji = '💀') {
        $full_message = $message;
        if (!empty($location)) {
            $full_message .= " - Location: {$location}";
        }
        self::log_formatted($emoji, '[FATAL ERROR]', $full_message);
        self::log('ERROR', $full_message, array('location' => $location));
    }
    
    public static function system($message, $category = 'SYSTEM', $emoji = '🔧') {
        self::log_formatted($emoji, "[{$category}]", $message);
    }
    
    /**
     * Core logging method
     */
    private static function log($level, $message, $context = array(), $emoji = '') {
        if (!self::$initialized) {
            self::init();
        }
        
        $timestamp = date('m-d-Y H:i:s');
        $context_str = !empty($context) ? ' - Context: ' . json_encode($context) : '';
        
        // Extract module name from the message (e.g., "SSO: message" -> "[SSO]")
        $module = 'System';
        if (preg_match('/^([A-Za-z\s]+):\s*(.*)/', $message, $matches)) {
            $module = trim($matches[1]);
            $message = $matches[2];
        }
        
        // Standard log format for parsing
        $log_entry = "{$timestamp} [{$module}] - {$level} - {$message}{$context_str}\n";
        
        self::write_to_file($log_entry);
        
        // Also log to database for activity tracking (but don't let it break logging)
        try {
            self::log_to_database($level, $module, $message, $context);
        } catch (Exception $e) {
            // Silently fail to prevent database issues from breaking logging
            error_log('Azure Logger: Database logging failed - ' . $e->getMessage());
        }
    }
    
    /**
     * Enhanced formatted logging for debugging
     */
    private static function log_formatted($emoji, $category, $message) {
        if (!self::$initialized) {
            self::init();
        }
        
        $timestamp = date('Y-m-d H:i:s');
        
        // Format: **2025-09-18 03:47:36** 🎉 **[SUCCESS]** Message
        $log_entry = "**{$timestamp}** {$emoji} **{$category}** {$message}  \n";
        
        self::write_to_file($log_entry);
    }
    
    /**
     * Safe file writing with crash protection
     */
    private static function write_to_file($content) {
        try {
            // Ensure directory exists
            $log_dir = dirname(self::$log_file);
            if (!is_dir($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            
            // Rotate log if needed before writing
            self::rotate_log_if_needed();
            
            // Write with file locking for crash safety
            $result = file_put_contents(self::$log_file, $content, FILE_APPEND | LOCK_EX);
            
            if ($result === false) {
                error_log('Azure Logger: Failed to write to log file: ' . self::$log_file);
            }
            
        } catch (Exception $e) {
            error_log('Azure Logger: Exception during file write - ' . $e->getMessage());
        }
    }
    
    /**
     * Log rotation to keep file under 20MB
     */
    private static function rotate_log_if_needed() {
        if (!file_exists(self::$log_file)) {
            return;
        }
        
        $file_size = filesize(self::$log_file);
        
        if ($file_size >= self::$max_file_size) {
            // Create backup with timestamp
            $backup_file = dirname(self::$log_file) . '/logs-backup-' . date('Y-m-d-H-i-s') . '.md';
            
            try {
                // Move current log to backup
                rename(self::$log_file, $backup_file);
                
                // Create new log file with header
                $header = "# Microsoft WP Debug Logs (Rotated)\n\n";
                $header .= "**Previous log backed up to:** " . basename($backup_file) . "  \n";
                $header .= "**Rotated at:** " . date('Y-m-d H:i:s') . "  \n";
                $header .= "**File size was:** " . number_format($file_size / 1024 / 1024, 2) . " MB  \n\n";
                $header .= "---\n\n";
                
                file_put_contents(self::$log_file, $header, LOCK_EX);
                
                // Clean up old backups (keep only last 5)
                self::cleanup_old_backups();
                
            } catch (Exception $e) {
                error_log('Azure Logger: Failed to rotate log file - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Clean up old backup files
     */
    private static function cleanup_old_backups() {
        $log_dir = dirname(self::$log_file);
        $backup_files = glob($log_dir . '/logs-backup-*.md');
        
        if (count($backup_files) > 5) {
            // Sort by modification time (oldest first)
            usort($backup_files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Delete oldest files, keep only 5 most recent
            $files_to_delete = array_slice($backup_files, 0, -5);
            foreach ($files_to_delete as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get logs for display
     */
    public static function get_logs($lines = 100) {
        if (!file_exists(self::$log_file)) {
            return array();
        }
        
        $logs = file(self::$log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_slice($logs, -$lines);
    }
    
    /**
     * Get formatted logs for display with filtering
     */
    public static function get_formatted_logs($lines = 500, $level_filter = '', $module_filter = '') {
        if (!file_exists(self::$log_file)) {
            return array();
        }
        
        $all_logs = file(self::$log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // Filter out header lines and apply filters
        $log_lines = array();
        foreach ($all_logs as $line) {
            // Match both standard and formatted log lines
            $is_log_line = (
                preg_match('/^\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2} \[/', $line) ||
                preg_match('/^\*\*\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\*\*/', $line)
            );
            
            if ($is_log_line) {
                // Apply filters
                if (!empty($level_filter) && strpos($line, " - {$level_filter} - ") === false) {
                    continue;
                }
                if (!empty($module_filter) && strpos($line, "[{$module_filter}]") === false) {
                    continue;
                }
                $log_lines[] = $line;
            }
        }
        
        // Return most recent lines
        return array_slice($log_lines, -$lines);
    }
    
    /**
     * Clear all logs
     */
    public static function clear_logs() {
        if (file_exists(self::$log_file)) {
            unlink(self::$log_file);
        }
        
        // Clear backup files
        $log_dir = dirname(self::$log_file);
        $backup_files = glob($log_dir . '/logs-backup-*.md');
        foreach ($backup_files as $file) {
            unlink($file);
        }
        
        // Also clear database logs
        try {
            global $wpdb;
            if (class_exists('Azure_Database')) {
                $activity_table = Azure_Database::get_table_name('activity_log');
                if ($activity_table) {
                    $wpdb->query("DELETE FROM {$activity_table}");
                }
            }
        } catch (Exception $e) {
            error_log('Azure Logger: Failed to clear database logs - ' . $e->getMessage());
        }
        
        // Reinitialize with new header
        self::$initialized = false;
        self::init();
    }
    
    /**
     * Get log file size in MB
     */
    public static function get_log_file_size() {
        if (!file_exists(self::$log_file)) {
            return 0;
        }
        
        return round(filesize(self::$log_file) / 1024 / 1024, 2);
    }
    
    /**
     * Get log file path for direct access
     */
    public static function get_log_file_path() {
        return self::$log_file;
    }
    
    /**
     * Log to database for activity tracking
     */
    private static function log_to_database($level, $module, $message, $context = array()) {
        global $wpdb;
        
        // Skip if database isn't initialized yet
        if (!class_exists('Azure_Database')) {
            return;
        }
        
        $activity_table = Azure_Database::get_table_name('activity_log');
        if (!$activity_table) {
            return;
        }
        
        // Check if table exists before trying to insert
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$activity_table'");
        if (!$table_exists) {
            return; // Skip logging if table doesn't exist
        }
        
        // Map log levels to activity status
        $status_map = array(
            'ERROR' => 'error',
            'WARNING' => 'warning',
            'INFO' => 'success',
            'DEBUG' => 'info'
        );
        
        $status = $status_map[$level] ?? 'info';
        
        // Insert into activity log - match actual table schema
        $wpdb->insert(
            $activity_table,
            array(
                'module' => $module,
                'action' => strtolower($module) . '_log',
                'object_type' => 'log_entry',
                'object_id' => null,
                'user_id' => get_current_user_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'details' => json_encode(array(
                    'level' => $level,
                    'message' => $message,
                    'context' => $context
                )),
                'status' => $status
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );
    }
}