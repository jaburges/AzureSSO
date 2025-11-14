<?php
/**
 * Plugin Name: Microsoft WP
 * Plugin URI: https://github.com/jamieburgess/microsoft-wp
 * Description: Complete Microsoft integration plugin for WordPress - includes SSO authentication, backup to Azure Storage, calendar embedding, and email sending via Microsoft Graph API.
 * Version: 1.1
 * Author: Jamie Burgess
 * License: GPL v2 or later
 * Text Domain: azure-plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AZURE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AZURE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AZURE_PLUGIN_VERSION', '1.1');

// Early debug logging - write to logs.md
$log_file = AZURE_PLUGIN_PATH . 'logs.md';
$timestamp = date('Y-m-d H:i:s');
$header = file_exists($log_file) ? '' : "# Microsoft WP Debug Logs\n\n";
$log_entry = $header . "**{$timestamp}** 🚀 **[INIT]** Microsoft WP main file loaded - Constants defined  \n";
$log_entry .= "**{$timestamp}** 📍 **[DEBUG]** Plugin URL: " . AZURE_PLUGIN_URL . "  \n";
$log_entry .= "**{$timestamp}** 📍 **[DEBUG]** Plugin Path: " . AZURE_PLUGIN_PATH . "  \n";
$log_entry .= "**{$timestamp}** 📍 **[DEBUG]** Plugin Version: " . AZURE_PLUGIN_VERSION . "  \n";
$log_entry .= "**{$timestamp}** 📍 **[DEBUG]** WordPress Version: " . (function_exists('get_bloginfo') ? get_bloginfo('version') : 'Unknown') . "  \n";
$log_entry .= "**{$timestamp}** 📍 **[DEBUG]** PHP Version: " . PHP_VERSION . "  \n";
$log_entry .= "**{$timestamp}** 📍 **[DEBUG]** Memory Limit: " . ini_get('memory_limit') . "  \n";
file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

// Main plugin class for Microsoft WP
class AzurePlugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new AzurePlugin();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $log_file = AZURE_PLUGIN_PATH . 'logs.md';
        $timestamp = date('Y-m-d H:i:s');
        
        $log_entry = "**{$timestamp}** 🔧 **[CONSTRUCT]** Plugin constructor started  \n";
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        try {
            // Register hooks - these can be registered immediately
            $log_entry = "**{$timestamp}** ⏳ **[CONSTRUCT]** Registering WordPress hooks  \n";
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            
            // Use plugins_loaded for initialization to ensure WordPress is ready
            add_action('plugins_loaded', array($this, 'load_dependencies'), 5);
            file_put_contents($log_file, "**{$timestamp}** ✅ **[CONSTRUCT]** plugins_loaded hook registered  \n", FILE_APPEND | LOCK_EX);
            
            add_action('init', array($this, 'init'), 10);
            file_put_contents($log_file, "**{$timestamp}** ✅ **[CONSTRUCT]** init hook registered  \n", FILE_APPEND | LOCK_EX);
            
            // Activation/deactivation hooks must be registered immediately
            register_activation_hook(__FILE__, array($this, 'activate'));
            register_deactivation_hook(__FILE__, array($this, 'deactivate'));
            file_put_contents($log_file, "**{$timestamp}** ✅ **[CONSTRUCT]** activation/deactivation hooks registered  \n", FILE_APPEND | LOCK_EX);
            
            $log_entry = "**{$timestamp}** ✅ **[CONSTRUCT]** WordPress hooks registered successfully  \n";
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            
            $log_entry = "**{$timestamp}** 🎉 **[CONSTRUCT]** Plugin constructor completed successfully  \n";
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            
        } catch (Exception $e) {
            $log_entry = "**{$timestamp}** ❌ **[CONSTRUCT ERROR]** " . $e->getMessage() . "  \n";
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            error_log('Azure Plugin: Constructor error - ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function load_dependencies() {
        $log_file = AZURE_PLUGIN_PATH . 'logs.md';
        $timestamp = date('Y-m-d H:i:s');
        
        // First thing - log that we're being called
        file_put_contents($log_file, "**{$timestamp}** 🚨 **[LOAD]** load_dependencies() METHOD CALLED  \n", FILE_APPEND | LOCK_EX);
        error_log('Azure Plugin: load_dependencies() called');
        
        $write_log = function($message) use ($log_file, $timestamp) {
            $log_entry = "**{$timestamp}** {$message}  \n";
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        };
        
        try {
            $write_log("🔄 **[LOAD]** Loading plugin dependencies");
            
            // Common utilities - CRITICAL FILES
            $critical_files = array(
                'class-logger.php' => 'Logger class',
                'class-database.php' => 'Database class',
                'class-admin.php' => 'Admin class',
                'class-settings.php' => 'Settings class'
            );
            
            // Optional feature files
            $optional_files = array(
                // SSO functionality
                'class-sso-auth.php' => 'SSO Auth class',
                'class-sso-shortcode.php' => 'SSO Shortcode class',
                'class-sso-sync.php' => 'SSO Sync class',
                
                // Backup functionality
                'class-backup.php' => 'Backup class',
                'class-backup-restore.php' => 'Backup Restore class',
                'class-backup-azure-storage.php' => 'Backup Azure Storage class',
                'class-backup-scheduler.php' => 'Backup Scheduler class',
                
                // Calendar functionality
                'class-calendar-auth.php' => 'Calendar Auth class',
                'class-calendar-graph-api.php' => 'Calendar Graph API class',
                'class-calendar-manager.php' => 'Calendar Manager class',
                'class-calendar-renderer.php' => 'Calendar Renderer class',
                'class-calendar-shortcode.php' => 'Calendar Shortcode class',
                'class-calendar-events-cpt.php' => 'Calendar Events CPT class',
                'class-calendar-ical-sync.php' => 'Calendar iCal Sync class',
                'class-calendar-events-shortcode.php' => 'Calendar Events Shortcode class',
                
                // Email functionality
                'class-email-auth.php' => 'Email Auth class',
                'class-email-mailer.php' => 'Email Mailer class',
                'class-email-shortcode.php' => 'Email Shortcode class',
                'class-email-logger.php' => 'Email Logger class',
                
                // TEC Integration functionality - FULL PRODUCTION VERSION (duplicate method fixed)
                // 'class-tec-integration-test.php' => 'TEC Integration TEST class',
                'class-tec-integration.php' => 'TEC Integration class',
                'class-tec-sync-engine.php' => 'TEC Sync Engine class',
                'class-tec-data-mapper.php' => 'TEC Data Mapper class',
                'class-tec-calendar-mapping-manager.php' => 'TEC Calendar Mapping Manager class',
                'class-tec-sync-scheduler.php' => 'TEC Sync Scheduler class',
                'class-tec-integration-ajax.php' => 'TEC Integration AJAX handlers class',
                // 'class-tec-integration-minimal.php' => 'MINIMAL TEC Integration class',
                
                // PTA functionality
                'class-pta-database.php' => 'PTA Database class',
                'class-pta-manager.php' => 'PTA Manager class',  // ENABLED FOR DEBUGGING
                'class-pta-sync-engine.php' => 'PTA Sync Engine class',
                'class-pta-groups-manager.php' => 'PTA Groups Manager class',
                'class-pta-shortcode.php' => 'PTA Shortcode class',
                'class-pta-beaver-builder.php' => 'PTA Beaver Builder class',
                
                // OneDrive Media functionality
                'class-onedrive-media-auth.php' => 'OneDrive Media Auth class',
                'class-onedrive-media-graph-api.php' => 'OneDrive Media Graph API class',
                'class-onedrive-media-manager.php' => 'OneDrive Media Manager class'
            );
            
            // Load critical files first - these must succeed
            foreach ($critical_files as $file => $description) {
                $file_path = AZURE_PLUGIN_PATH . 'includes/' . $file;
                $write_log("⏳ **[LOAD CRITICAL]** Loading {$description}: {$file}");
                
                if (!file_exists($file_path)) {
                    $error_msg = "Critical file not found: {$file_path}";
                    $write_log("💀 **[LOAD CRITICAL ERROR]** {$error_msg}");
                    throw new Exception($error_msg);
                }
                
                try {
                    require_once $file_path;
                    $write_log("✅ **[LOAD CRITICAL]** {$description} loaded successfully");
                } catch (ParseError $e) {
                    $error_msg = "Parse error in critical file {$file}: " . $e->getMessage();
                    $write_log("💀 **[LOAD CRITICAL PARSE ERROR]** {$error_msg}");
                    $write_log("📍 **[PARSE ERROR LOCATION]** " . $e->getFile() . " line " . $e->getLine());
                    throw new Exception($error_msg);
                } catch (Error $e) {
                    $error_msg = "Fatal error in critical file {$file}: " . $e->getMessage();
                    $write_log("💀 **[LOAD CRITICAL FATAL ERROR]** {$error_msg}");
                    $write_log("📍 **[FATAL ERROR LOCATION]** " . $e->getFile() . " line " . $e->getLine());
                    throw new Exception($error_msg);
                } catch (Exception $e) {
                    $error_msg = "Error loading critical file {$file}: " . $e->getMessage();
                    $write_log("❌ **[LOAD CRITICAL ERROR]** {$error_msg}");
                    $write_log("📍 **[ERROR LOCATION]** " . $e->getFile() . " line " . $e->getLine());
                    throw new Exception($error_msg);
                }
            }
            
            // Load optional files - failures are logged but don't stop loading
            $missing_optional_files = array();
            foreach ($optional_files as $file => $description) {
                $file_path = AZURE_PLUGIN_PATH . 'includes/' . $file;
                $write_log("⏳ **[LOAD]** Loading {$description}: {$file}");
                
                if (!file_exists($file_path)) {
                    $write_log("⚠️ **[LOAD WARNING]** Optional file not found: {$file_path}");
                    $missing_optional_files[] = $file;
                    continue;
                }
                
                try {
                    // Special handling for class-calendar-auth.php
                    if ($file === 'class-calendar-auth.php') {
                        $write_log("⏳ **[LOAD]** Loading class-calendar-auth.php (simplified version)");
                    }
                    
                    require_once $file_path;
                    
                    if ($file === 'class-calendar-auth.php') {
                        restore_error_handler(); // Restore previous error handler
                        $write_log("✅ **[DEBUG]** calendar-auth require_once completed - class exists: " . (class_exists('Azure_Calendar_Auth') ? 'YES' : 'NO'));
                    }
                    
                    $write_log("✅ **[LOAD]** {$description} loaded successfully");
                } catch (ParseError $e) {
                    $write_log("💀 **[LOAD PARSE ERROR]** Parse error in optional file {$file}: " . $e->getMessage());
                    $write_log("📍 **[PARSE ERROR LOCATION]** " . $e->getFile() . " line " . $e->getLine());
                    error_log("Azure Plugin PARSE ERROR in {$file}: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                    $missing_optional_files[] = $file;
                } catch (Error $e) {
                    $write_log("💀 **[LOAD FATAL ERROR]** Fatal error in optional file {$file}: " . $e->getMessage());
                    $write_log("📍 **[FATAL ERROR LOCATION]** " . $e->getFile() . " line " . $e->getLine());
                    $write_log("📋 **[STACK TRACE]** " . str_replace("\n", " | ", $e->getTraceAsString()));
                    error_log("Azure Plugin FATAL ERROR in {$file}: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                    error_log("Stack: " . $e->getTraceAsString());
                    $missing_optional_files[] = $file;
                } catch (Exception $e) {
                    $write_log("❌ **[LOAD ERROR]** Error loading optional file {$file}: " . $e->getMessage());
                    $write_log("📍 **[ERROR LOCATION]** " . $e->getFile() . " line " . $e->getLine());
                    error_log("Azure Plugin ERROR in {$file}: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                    $missing_optional_files[] = $file;
                }
            }
            
            if (!empty($missing_optional_files)) {
                $write_log("⚠️ **[LOAD SUMMARY]** " . count($missing_optional_files) . " optional files failed to load: " . implode(', ', $missing_optional_files));
            }
            
            $write_log("🎉 **[LOAD]** All dependencies loaded successfully");
            
        } catch (Exception $e) {
            $write_log("❌ **[LOAD ERROR]** " . $e->getMessage());
            $write_log("📍 **[LOAD ERROR FILE]** " . $e->getFile() . " line " . $e->getLine());
            throw $e;
        }
    }
    
    public function init() {
        $log_file = AZURE_PLUGIN_PATH . 'logs.md';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "**{$timestamp}** 🔄 **[INIT]** Plugin init started  \n";
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        try {
            // Initialize logger first if not already initialized
            if (class_exists('Azure_Logger') && !Azure_Logger::is_initialized()) {
                Azure_Logger::init();
            }
            
            // Only initialize if we're loaded and dependencies are available
            if (!class_exists('Azure_Logger')) {
                $log_entry = "**{$timestamp}** ⚠️ **[INIT]** Logger class not found, exiting init  \n";
                file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
                return;
            }
            $log_entry = "**{$timestamp}** ✅ **[INIT]** Logger class available  \n";
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            
            // Load plugin textdomain
            $log_entry = "**{$timestamp}** ⏳ **[INIT]** Loading textdomain  \n";
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            load_plugin_textdomain('azure-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages');
            $log_entry = "**{$timestamp}** ✅ **[INIT]** Textdomain loaded  \n";
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            
            // Initialize settings system FIRST (before Admin, as Admin depends on it)
            if (class_exists('Azure_Settings')) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[SETTINGS]** Creating Azure_Settings instance  \n", FILE_APPEND | LOCK_EX);
                Azure_Settings::get_instance();
                file_put_contents($log_file, "**{$timestamp}** ✅ **[SETTINGS]** Azure_Settings instance created successfully  \n", FILE_APPEND | LOCK_EX);
                $log_entry = "**{$timestamp}** ✅ **[INIT]** Settings initialized  \n";
                file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            }
            
            // Initialize admin components (AFTER Settings)
            if (is_admin()) {
                $log_entry = "**{$timestamp}** ⏳ **[INIT]** Initializing admin components  \n";
                file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
                if (class_exists('Azure_Admin')) {
                    file_put_contents($log_file, "**{$timestamp}** ⏳ **[ADMIN]** Creating Azure_Admin instance  \n", FILE_APPEND | LOCK_EX);
                    Azure_Admin::get_instance();
                    file_put_contents($log_file, "**{$timestamp}** ✅ **[ADMIN]** Azure_Admin instance created successfully  \n", FILE_APPEND | LOCK_EX);
                }
                $log_entry = "**{$timestamp}** ✅ **[INIT]** Admin initialized  \n";
                file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            }
            
            // Initialize SSO functionality if enabled
            $settings = get_option('azure_plugin_settings', array());
            if (!empty($settings['enable_sso'])) {
                $this->init_sso_components();
                $log_entry = "**{$timestamp}** ✅ **[INIT]** SSO components initialized  \n";
                file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            }
            
            // Initialize Backup functionality if enabled
            if (!empty($settings['enable_backup'])) {
                $this->init_backup_components();
                $log_entry = "**{$timestamp}** ✅ **[INIT]** Backup components initialized  \n";
                file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            }
            
            // Initialize Calendar functionality if enabled
            if (!empty($settings['enable_calendar'])) {
                $this->init_calendar_components();
                $log_entry = "**{$timestamp}** ✅ **[INIT]** Calendar components initialized  \n";
                file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            }
            
            // Initialize TEC Integration
            if ($settings['enable_tec_integration'] ?? false) {
                $this->init_tec_components();
                $log_entry = "**{$timestamp}** ✅ **[INIT]** TEC Integration components initialized  \n";
                file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            }
            
            // Always initialize Email Logger (logs all WordPress emails)
            $this->init_email_logger();
            $log_entry = "**{$timestamp}** ✅ **[INIT]** Email logger initialized  \n";
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            
            // Check email functionality setting
            file_put_contents($log_file, "**{$timestamp}** 🔍 **[DEBUG]** Checking email functionality setting...  \n", FILE_APPEND | LOCK_EX);
            
            // Initialize Email functionality if enabled
            if (!empty($settings['enable_email'])) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[DEBUG]** Email functionality enabled, initializing components...  \n", FILE_APPEND | LOCK_EX);
                $this->init_email_components();
                $log_entry = "**{$timestamp}** ✅ **[INIT]** Email components initialized  \n";
                file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            } else {
                file_put_contents($log_file, "**{$timestamp}** ℹ️ **[DEBUG]** Email functionality disabled, skipping...  \n", FILE_APPEND | LOCK_EX);
            }
            
            // Check PTA functionality setting
            file_put_contents($log_file, "**{$timestamp}** 🔍 **[DEBUG]** Checking PTA functionality setting...  \n", FILE_APPEND | LOCK_EX);
            
            // Initialize PTA functionality if enabled
            if (!empty($settings['enable_pta'])) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[DEBUG]** PTA functionality enabled, initializing components...  \n", FILE_APPEND | LOCK_EX);
                $this->init_pta_components();
                $log_entry = "**{$timestamp}** ✅ **[INIT]** PTA components initialized  \n";
                file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            } else {
                file_put_contents($log_file, "**{$timestamp}** ℹ️ **[DEBUG]** PTA functionality disabled, skipping...  \n", FILE_APPEND | LOCK_EX);
            }
            
            // Initialize OneDrive Media functionality if enabled
            if (!empty($settings['enable_onedrive_media'])) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[DEBUG]** OneDrive Media functionality enabled, initializing components...  \n", FILE_APPEND | LOCK_EX);
                $this->init_onedrive_media_components();
                $log_entry = "**{$timestamp}** ✅ **[INIT]** OneDrive Media components initialized  \n";
                file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            } else {
                file_put_contents($log_file, "**{$timestamp}** ℹ️ **[DEBUG]** OneDrive Media functionality disabled, skipping...  \n", FILE_APPEND | LOCK_EX);
            }
            
            file_put_contents($log_file, "**{$timestamp}** 🏁 **[DEBUG]** About to log completion message...  \n", FILE_APPEND | LOCK_EX);
            
            $log_entry = "**{$timestamp}** 🎉 **[INIT]** Plugin init completed successfully  \n";
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            
        } catch (Exception $e) {
            $log_entry = "**{$timestamp}** ❌ **[INIT ERROR]** " . $e->getMessage() . "  \n";
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            $log_entry = "**{$timestamp}** 📍 **[INIT ERROR FILE]** " . $e->getFile() . " line " . $e->getLine() . "  \n";
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            throw $e;
        }
    }
    
    private function init_sso_components() {
        $log_file = AZURE_PLUGIN_PATH . 'logs.md';
        $timestamp = date('Y-m-d H:i:s');
        
        try {
            if (class_exists('Azure_SSO_Auth')) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[SSO]** Initializing Azure_SSO_Auth  \n", FILE_APPEND | LOCK_EX);
                new Azure_SSO_Auth();
                file_put_contents($log_file, "**{$timestamp}** ✅ **[SSO]** Azure_SSO_Auth initialized successfully  \n", FILE_APPEND | LOCK_EX);
            }
            if (class_exists('Azure_SSO_Shortcode')) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[SSO]** Initializing Azure_SSO_Shortcode  \n", FILE_APPEND | LOCK_EX);
                new Azure_SSO_Shortcode();
                file_put_contents($log_file, "**{$timestamp}** ✅ **[SSO]** Azure_SSO_Shortcode initialized successfully  \n", FILE_APPEND | LOCK_EX);
            }
            if (class_exists('Azure_SSO_Sync')) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[SSO]** Initializing Azure_SSO_Sync  \n", FILE_APPEND | LOCK_EX);
                new Azure_SSO_Sync();
                file_put_contents($log_file, "**{$timestamp}** ✅ **[SSO]** Azure_SSO_Sync initialized successfully  \n", FILE_APPEND | LOCK_EX);
            }
        } catch (Error $e) {
            file_put_contents($log_file, "**{$timestamp}** 💀 **[SSO FATAL]** " . $e->getMessage() . "  \n", FILE_APPEND | LOCK_EX);
            file_put_contents($log_file, "**{$timestamp}** 📍 **[SSO FATAL LOCATION]** " . $e->getFile() . " line " . $e->getLine() . "  \n", FILE_APPEND | LOCK_EX);
            throw $e;
        } catch (Exception $e) {
            file_put_contents($log_file, "**{$timestamp}** ❌ **[SSO ERROR]** " . $e->getMessage() . "  \n", FILE_APPEND | LOCK_EX);
            file_put_contents($log_file, "**{$timestamp}** 📍 **[SSO ERROR LOCATION]** " . $e->getFile() . " line " . $e->getLine() . "  \n", FILE_APPEND | LOCK_EX);
            throw $e;
        }
    }
    
    private function init_backup_components() {
        $log_file = AZURE_PLUGIN_PATH . 'logs.md';
        $timestamp = date('Y-m-d H:i:s');
        
        try {
            // Initialize backup components in the correct order to avoid dependency issues
            // Storage class should NOT be instantiated here as it's only used internally by other classes
            if (class_exists('Azure_Backup')) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[BACKUP]** Initializing Azure_Backup  \n", FILE_APPEND | LOCK_EX);
                new Azure_Backup();
                file_put_contents($log_file, "**{$timestamp}** ✅ **[BACKUP]** Azure_Backup initialized successfully  \n", FILE_APPEND | LOCK_EX);
            }
            if (class_exists('Azure_Backup_Restore')) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[BACKUP]** Initializing Azure_Backup_Restore  \n", FILE_APPEND | LOCK_EX);
                new Azure_Backup_Restore();
                file_put_contents($log_file, "**{$timestamp}** ✅ **[BACKUP]** Azure_Backup_Restore initialized successfully  \n", FILE_APPEND | LOCK_EX);
            }
            if (class_exists('Azure_Backup_Scheduler')) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[BACKUP]** Initializing Azure_Backup_Scheduler  \n", FILE_APPEND | LOCK_EX);
                new Azure_Backup_Scheduler();
                file_put_contents($log_file, "**{$timestamp}** ✅ **[BACKUP]** Azure_Backup_Scheduler initialized successfully  \n", FILE_APPEND | LOCK_EX);
            }
            // Note: Azure_Backup_Storage is not instantiated here - it's created on-demand by other classes
        } catch (Error $e) {
            file_put_contents($log_file, "**{$timestamp}** 💀 **[BACKUP FATAL]** " . $e->getMessage() . "  \n", FILE_APPEND | LOCK_EX);
            file_put_contents($log_file, "**{$timestamp}** 📍 **[BACKUP FATAL LOCATION]** " . $e->getFile() . " line " . $e->getLine() . "  \n", FILE_APPEND | LOCK_EX);
            throw $e;
        } catch (Exception $e) {
            file_put_contents($log_file, "**{$timestamp}** ❌ **[BACKUP ERROR]** " . $e->getMessage() . "  \n", FILE_APPEND | LOCK_EX);
            file_put_contents($log_file, "**{$timestamp}** 📍 **[BACKUP ERROR LOCATION]** " . $e->getFile() . " line " . $e->getLine() . "  \n", FILE_APPEND | LOCK_EX);
            throw $e;
        }
    }
    
    private function init_calendar_components() {
        $log_file = AZURE_PLUGIN_PATH . 'logs.md';
        $timestamp = current_time('Y-m-d H:i:s');
        
        $write_log = function($message) use ($log_file, $timestamp) {
            file_put_contents($log_file, "**{$timestamp}** {$message}  \n", FILE_APPEND | LOCK_EX);
        };
        
        $write_log("🔧 **[CALENDAR]** Initializing calendar components");
        
        if (class_exists('Azure_Calendar_Auth')) {
            $write_log("🔍 **[CALENDAR]** Azure_Calendar_Auth class exists");
            try {
                $write_log("⏳ **[CALENDAR]** Creating Azure_Calendar_Auth instance...");
                new Azure_Calendar_Auth();
                $write_log("✅ **[CALENDAR]** Azure_Calendar_Auth instantiated successfully");
            } catch (Error $e) {
                $write_log("💀 **[CALENDAR FATAL]** " . $e->getMessage());
                $write_log("📍 **[CALENDAR FATAL LOCATION]** " . $e->getFile() . " line " . $e->getLine());
                throw $e;
            } catch (Exception $e) {
                $write_log("❌ **[CALENDAR ERROR]** " . $e->getMessage());
                $write_log("📍 **[CALENDAR ERROR LOCATION]** " . $e->getFile() . " line " . $e->getLine());
                throw $e;
            }
        } else {
            $write_log("⚠️ **[CALENDAR]** Azure_Calendar_Auth class NOT found");
        }
        if (class_exists('Azure_Calendar_GraphAPI')) {
            new Azure_Calendar_GraphAPI();
        }
        if (class_exists('Azure_Calendar_Manager')) {
            new Azure_Calendar_Manager();
        }
        if (class_exists('Azure_Calendar_Shortcode')) {
            new Azure_Calendar_Shortcode();
        }
        if (class_exists('Azure_Calendar_EventsCPT')) {
            new Azure_Calendar_EventsCPT();
        }
        if (class_exists('Azure_Calendar_ICalSync')) {
            new Azure_Calendar_ICalSync();
        }
        if (class_exists('Azure_Calendar_EventsShortcode')) {
            new Azure_Calendar_EventsShortcode();
        }
    }
    
    private function init_tec_components() {
        $log_file = AZURE_PLUGIN_PATH . 'logs.md';
        $timestamp = date('Y-m-d H:i:s');
        
        try {
            if (class_exists('Azure_TEC_Integration')) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[TEC]** Initializing Azure_TEC_Integration  \n", FILE_APPEND | LOCK_EX);
                Azure_TEC_Integration::get_instance();
                file_put_contents($log_file, "**{$timestamp}** ✅ **[TEC]** Azure_TEC_Integration initialized successfully  \n", FILE_APPEND | LOCK_EX);
            } else {
                file_put_contents($log_file, "**{$timestamp}** ⚠️ **[TEC]** Azure_TEC_Integration class not found  \n", FILE_APPEND | LOCK_EX);
            }
            
            // Initialize TEC AJAX handlers
            if (class_exists('Azure_TEC_Integration_Ajax')) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[TEC]** Initializing Azure_TEC_Integration_Ajax  \n", FILE_APPEND | LOCK_EX);
                new Azure_TEC_Integration_Ajax();
                file_put_contents($log_file, "**{$timestamp}** ✅ **[TEC]** Azure_TEC_Integration_Ajax initialized successfully  \n", FILE_APPEND | LOCK_EX);
            }
            
            // Initialize TEC Sync Scheduler
            if (class_exists('Azure_TEC_Sync_Scheduler')) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[TEC]** Initializing Azure_TEC_Sync_Scheduler  \n", FILE_APPEND | LOCK_EX);
                new Azure_TEC_Sync_Scheduler();
                file_put_contents($log_file, "**{$timestamp}** ✅ **[TEC]** Azure_TEC_Sync_Scheduler initialized successfully  \n", FILE_APPEND | LOCK_EX);
            }
        } catch (Error $e) {
            file_put_contents($log_file, "**{$timestamp}** 💀 **[TEC FATAL]** " . $e->getMessage() . "  \n", FILE_APPEND | LOCK_EX);
            file_put_contents($log_file, "**{$timestamp}** 📍 **[TEC FATAL LOCATION]** " . $e->getFile() . " line " . $e->getLine() . "  \n", FILE_APPEND | LOCK_EX);
            throw $e;
        } catch (Exception $e) {
            file_put_contents($log_file, "**{$timestamp}** ❌ **[TEC ERROR]** " . $e->getMessage() . "  \n", FILE_APPEND | LOCK_EX);
            file_put_contents($log_file, "**{$timestamp}** 📍 **[TEC ERROR LOCATION]** " . $e->getFile() . " line " . $e->getLine() . "  \n", FILE_APPEND | LOCK_EX);
            throw $e;
        }
    }
    
    private function init_email_logger() {
        // Initialize Email Logger to hook into wp_mail and register AJAX handlers
        if (class_exists('Azure_Email_Logger')) {
            Azure_Email_Logger::get_instance();
            Azure_Logger::debug('Email Logger: Initialized successfully with AJAX handlers');
        } else {
            Azure_Logger::warning('Email Logger: Azure_Email_Logger class not found');
        }
    }
    
    private function init_email_components() {
        $log_file = AZURE_PLUGIN_PATH . 'logs.md';
        $timestamp = date('Y-m-d H:i:s');
        
        try {
            if (class_exists('Azure_Email_Auth')) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[EMAIL]** Initializing Azure_Email_Auth  \n", FILE_APPEND | LOCK_EX);
                new Azure_Email_Auth();
                file_put_contents($log_file, "**{$timestamp}** ✅ **[EMAIL]** Azure_Email_Auth initialized successfully  \n", FILE_APPEND | LOCK_EX);
            }
            
            if (class_exists('Azure_Email_Mailer')) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[EMAIL]** Initializing Azure_Email_Mailer  \n", FILE_APPEND | LOCK_EX);
                new Azure_Email_Mailer();
                file_put_contents($log_file, "**{$timestamp}** ✅ **[EMAIL]** Azure_Email_Mailer initialized successfully  \n", FILE_APPEND | LOCK_EX);
            }
            
            if (class_exists('Azure_Email_Shortcode')) {
                file_put_contents($log_file, "**{$timestamp}** ⏳ **[EMAIL]** Initializing Azure_Email_Shortcode  \n", FILE_APPEND | LOCK_EX);
                new Azure_Email_Shortcode();
                file_put_contents($log_file, "**{$timestamp}** ✅ **[EMAIL]** Azure_Email_Shortcode initialized successfully  \n", FILE_APPEND | LOCK_EX);
            }
        } catch (Error $e) {
            file_put_contents($log_file, "**{$timestamp}** 💀 **[EMAIL FATAL]** " . $e->getMessage() . "  \n", FILE_APPEND | LOCK_EX);
            file_put_contents($log_file, "**{$timestamp}** 📍 **[EMAIL FATAL LOCATION]** " . $e->getFile() . " line " . $e->getLine() . "  \n", FILE_APPEND | LOCK_EX);
            throw $e;
        } catch (Exception $e) {
            file_put_contents($log_file, "**{$timestamp}** ❌ **[EMAIL ERROR]** " . $e->getMessage() . "  \n", FILE_APPEND | LOCK_EX);
            file_put_contents($log_file, "**{$timestamp}** 📍 **[EMAIL ERROR LOCATION]** " . $e->getFile() . " line " . $e->getLine() . "  \n", FILE_APPEND | LOCK_EX);
            throw $e;
        }
    }
    
    private function init_pta_components() {
        $log_file = AZURE_PLUGIN_PATH . 'logs.md';
        $timestamp = date('Y-m-d H:i:s');
        
        try {
            Azure_Logger::loading('Starting PTA components initialization', 'PTA');
            
            if (class_exists('Azure_PTA_Database')) {
                Azure_Logger::loading('Initializing Azure_PTA_Database', 'PTA');
                Azure_PTA_Database::init();
                Azure_Logger::complete('Azure_PTA_Database initialized successfully', 'PTA');
            } else {
                Azure_Logger::warning('PTA: Azure_PTA_Database class not found');
            }
            
            // Initialize PTA Manager - RE-ENABLED AFTER SUCCESSFUL DEBUGGING
            if (class_exists('Azure_PTA_Manager')) {
                Azure_Logger::loading('Initializing Azure_PTA_Manager', 'PTA');
                Azure_PTA_Manager::get_instance();
                Azure_Logger::complete('Azure_PTA_Manager initialized successfully', 'PTA');
            } else {
                Azure_Logger::warning('PTA: Azure_PTA_Manager class not found');
            }
            
            if (class_exists('Azure_PTA_Sync_Engine')) {
                Azure_Logger::loading('Initializing Azure_PTA_Sync_Engine', 'PTA');
                new Azure_PTA_Sync_Engine();
                Azure_Logger::complete('Azure_PTA_Sync_Engine initialized successfully', 'PTA');
            } else {
                Azure_Logger::info('PTA: Azure_PTA_Sync_Engine class not found - this is optional');
            }
            
            if (class_exists('Azure_PTA_Groups_Manager')) {
                Azure_Logger::loading('Initializing Azure_PTA_Groups_Manager', 'PTA');
                new Azure_PTA_Groups_Manager();
                Azure_Logger::complete('Azure_PTA_Groups_Manager initialized successfully', 'PTA');
            } else {
                Azure_Logger::info('PTA: Azure_PTA_Groups_Manager class not found - this is optional');
            }
            
            // Initialize PTA shortcodes
            if (class_exists('Azure_PTA_Shortcode')) {
                Azure_Logger::loading('Initializing Azure_PTA_Shortcode', 'PTA');
                new Azure_PTA_Shortcode();
                Azure_Logger::complete('Azure_PTA_Shortcode initialized successfully', 'PTA');
            } else {
                Azure_Logger::warning('PTA: Azure_PTA_Shortcode class not found');
            }
            
            // Initialize Beaver Builder integration (only if Beaver Builder is active)
            if (class_exists('Azure_PTA_BeaverBuilder')) {
                Azure_Logger::loading('Initializing Azure_PTA_BeaverBuilder', 'PTA');
                // Constructor will check if Beaver Builder is available
                new Azure_PTA_BeaverBuilder();
                Azure_Logger::complete('Azure_PTA_BeaverBuilder initialized successfully', 'PTA');
            } else {
                Azure_Logger::warning('PTA: Azure_PTA_BeaverBuilder class not found');
            }
            
            Azure_Logger::success('All PTA components initialized successfully', 'PTA');
            
        } catch (Error $e) {
            Azure_Logger::fatal('PTA: ' . $e->getMessage(), $e->getFile() . ' line ' . $e->getLine());
            throw $e;
        } catch (Exception $e) {
            Azure_Logger::error('PTA: ' . $e->getMessage(), array('location' => $e->getFile() . ' line ' . $e->getLine()));
            throw $e;
        }
    }
    
    private function init_onedrive_media_components() {
        $log_file = AZURE_PLUGIN_PATH . 'logs.md';
        $timestamp = date('Y-m-d H:i:s');
        
        try {
            Azure_Logger::loading('Starting OneDrive Media components initialization', 'OneDrive Media');
            
            // Initialize OneDrive Media Auth
            if (class_exists('Azure_OneDrive_Media_Auth')) {
                Azure_Logger::loading('Initializing Azure_OneDrive_Media_Auth', 'OneDrive Media');
                new Azure_OneDrive_Media_Auth();
                Azure_Logger::complete('Azure_OneDrive_Media_Auth initialized successfully', 'OneDrive Media');
            } else {
                Azure_Logger::warning('OneDrive Media: Azure_OneDrive_Media_Auth class not found');
            }
            
            // Initialize OneDrive Media Graph API
            if (class_exists('Azure_OneDrive_Media_GraphAPI')) {
                Azure_Logger::loading('Initializing Azure_OneDrive_Media_GraphAPI', 'OneDrive Media');
                new Azure_OneDrive_Media_GraphAPI();
                Azure_Logger::complete('Azure_OneDrive_Media_GraphAPI initialized successfully', 'OneDrive Media');
            } else {
                Azure_Logger::warning('OneDrive Media: Azure_OneDrive_Media_GraphAPI class not found');
            }
            
            // Initialize OneDrive Media Manager (main orchestration)
            if (class_exists('Azure_OneDrive_Media_Manager')) {
                Azure_Logger::loading('Initializing Azure_OneDrive_Media_Manager', 'OneDrive Media');
                Azure_OneDrive_Media_Manager::get_instance();
                Azure_Logger::complete('Azure_OneDrive_Media_Manager initialized successfully', 'OneDrive Media');
            } else {
                Azure_Logger::warning('OneDrive Media: Azure_OneDrive_Media_Manager class not found');
            }
            
            Azure_Logger::success('All OneDrive Media components initialized successfully', 'OneDrive Media');
            
        } catch (Error $e) {
            Azure_Logger::fatal('OneDrive Media: ' . $e->getMessage(), $e->getFile() . ' line ' . $e->getLine());
            throw $e;
        } catch (Exception $e) {
            Azure_Logger::error('OneDrive Media: ' . $e->getMessage(), array('location' => $e->getFile() . ' line ' . $e->getLine()));
            throw $e;
        }
    }
    
    /**
     * Plugin activation with comprehensive debugging
     */
    public function activate() {
        // Create debug log file immediately
        $log_file = AZURE_PLUGIN_PATH . 'logs.md';
        $timestamp = date('Y-m-d H:i:s');
        $header = file_exists($log_file) ? '' : "# Azure Plugin Activation Debug Logs\n\n";
        
        // Helper function to write debug logs
        $write_log = function($message) use ($log_file, $timestamp) {
            $log_entry = "**{$timestamp}** {$message}  \n";
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        };
        
        $write_log($header . "🚀 **[START]** Plugin activation initiated");
        $write_log("📁 **[DEBUG]** Plugin path: " . AZURE_PLUGIN_PATH);
        $write_log("🌐 **[DEBUG]** WordPress version: " . get_bloginfo('version'));
        $write_log("🗄️ **[DEBUG]** Database version: " . $GLOBALS['wpdb']->db_version());
        
        try {
            $write_log("⏳ **[STEP 1]** Starting activation process");
            
            // Check if required directories exist
            $includes_dir = AZURE_PLUGIN_PATH . 'includes/';
            if (!is_dir($includes_dir)) {
                throw new Exception("Includes directory not found: {$includes_dir}");
            }
            $write_log("✅ **[STEP 1]** Includes directory exists");
            
            $write_log("⏳ **[STEP 2]** Loading core classes");
            // Ensure logger is available
            $logger_file = AZURE_PLUGIN_PATH . 'includes/class-logger.php';
            if (!file_exists($logger_file)) {
                throw new Exception("Logger class file not found: {$logger_file}");
            }
            
            if (!class_exists('Azure_Logger')) {
                require_once $logger_file;
                if (!class_exists('Azure_Logger')) {
                    throw new Exception("Failed to load Azure_Logger class after require");
                }
            }
            $write_log("✅ **[STEP 2]** Logger class loaded successfully");
            
            $write_log("⏳ **[STEP 3]** Loading database class");
            // Ensure database class is available
            $db_file = AZURE_PLUGIN_PATH . 'includes/class-database.php';
            if (!file_exists($db_file)) {
                throw new Exception("Database class file not found: {$db_file}");
            }
            
            if (!class_exists('Azure_Database')) {
                require_once $db_file;
                if (!class_exists('Azure_Database')) {
                    throw new Exception("Failed to load Azure_Database class after require");
                }
            }
            $write_log("✅ **[STEP 3]** Database class loaded successfully");
            
            $write_log("⏳ **[STEP 4]** Starting logger-based logging");
            // Log with our logger
            Azure_Logger::info('Azure Plugin activation started');
            $write_log("✅ **[STEP 4]** Logger-based logging working");
            
            $write_log("⏳ **[STEP 5]** Checking database connection");
            global $wpdb;
            $db_test = $wpdb->get_var("SELECT 1");
            if ($db_test !== '1') {
                throw new Exception("Database connection test failed");
            }
            $write_log("✅ **[STEP 5]** Database connection verified");
            
            $write_log("⏳ **[STEP 6]** Creating database tables");
            // Create database tables
            try {
                Azure_Logger::info('Creating database tables');
                Azure_Database::create_tables();
                Azure_Logger::info('Database tables created successfully');
            } catch (Exception $e) {
                $write_log("❌ **[STEP 6 ERROR]** Failed to create main tables: " . $e->getMessage());
                throw new Exception("Database table creation failed: " . $e->getMessage());
            }
            
            // Create PTA database tables
            if (class_exists('Azure_PTA_Database')) {
                try {
                    Azure_Logger::info('Creating PTA database tables');
                    Azure_PTA_Database::create_tables();
                    Azure_Logger::info('PTA database tables created successfully');
                } catch (Exception $e) {
                    $write_log("❌ **[STEP 6 ERROR]** Failed to create PTA tables: " . $e->getMessage());
                    Azure_Logger::error('Failed to create PTA database tables: ' . $e->getMessage());
                    // Continue with activation but log the error
                }
            }
            
            $write_log("✅ **[STEP 6]** Database tables created");
            
            $write_log("⏳ **[STEP 7]** Creating AzureAD role");
            // Create AzureAD WordPress role
            $this->create_azuread_role();
            $write_log("✅ **[STEP 7]** AzureAD role created");
            
            $write_log("⏳ **[STEP 8]** Setting default options");
            // Set default options
            Azure_Logger::info('Setting default options');
            if (!get_option('azure_plugin_settings')) {
                $default_settings = array(
                    // General settings
                    'enable_sso' => false,
                    'enable_backup' => false,
                    'enable_calendar' => false,
                    'enable_email' => false,
                    'enable_pta' => false,
                    'enable_tec_integration' => false,
                    
                    // Common credentials
                    'use_common_credentials' => true,
                    'common_client_id' => '',
                    'common_client_secret' => '',
                    'common_tenant_id' => 'common',
                    
                    // SSO specific settings
                    'sso_client_id' => '',
                    'sso_client_secret' => '',
                    'sso_tenant_id' => 'common',
                    'sso_redirect_uri' => home_url('/wp-admin/admin-ajax.php?action=azure_sso_callback'),
                    'sso_require_sso' => false,
                    'sso_auto_create_users' => true,
                    'sso_default_role' => 'subscriber',
                    'sso_show_on_login_page' => true,
                    
                    // Backup specific settings
                    'backup_client_id' => '',
                    'backup_client_secret' => '',
                    'backup_storage_account' => '',
                    'backup_storage_key' => '',
                    'backup_container_name' => 'wordpress-backups',
                    'backup_types' => array('content', 'media', 'plugins', 'themes'),
                    'backup_retention_days' => 30,
                    'backup_max_execution_time' => 300,
                    'backup_schedule_enabled' => false,
                    'backup_schedule_frequency' => 'daily',
                    'backup_schedule_time' => '02:00',
                    'backup_email_notifications' => true,
                    'backup_notification_email' => get_option('admin_email'),
                    
                    // Calendar specific settings
                    'calendar_client_id' => '',
                    'calendar_client_secret' => '',
                    'calendar_tenant_id' => '',
                    'calendar_default_timezone' => 'America/New_York',
                    'calendar_default_view' => 'month',
                    'calendar_default_color_theme' => 'blue',
                    'calendar_cache_duration' => 3600,
                    'calendar_max_events_per_calendar' => 100,
                    
                    // Email specific settings
                    'email_client_id' => '',
                    'email_client_secret' => '',
                    'email_tenant_id' => '',
                    'email_auth_method' => 'graph_api',
                    'email_send_as_alias' => '',
                    'email_override_wp_mail' => false,
                    'email_hve_smtp_server' => 'smtp-hve.office365.com',
                    'email_hve_smtp_port' => 587,
                    'email_hve_username' => '',
                    'email_hve_password' => '',
                    'email_hve_from_email' => '',
                    'email_hve_encryption' => 'tls',
                    'email_hve_override_wp_mail' => false,
                    'email_acs_connection_string' => '',
                    'email_acs_endpoint' => '',
                    'email_acs_access_key' => '',
                    'email_acs_from_email' => '',
                    'email_acs_display_name' => '',
                    'email_acs_override_wp_mail' => false,
                    
                    // TEC Integration specific settings
                    'tec_outlook_calendar_id' => 'primary',
                    'tec_default_venue' => 'School Campus',
                    'tec_default_organizer' => 'PTSA',
                    'tec_organizer_email' => get_option('admin_email'),
                    'tec_sync_frequency' => 'hourly',
                    'tec_conflict_resolution' => 'outlook_wins',
                    'tec_include_event_url' => true,
                    'tec_event_footer' => '',
                    'tec_default_category' => 'School Event'
                );
                update_option('azure_plugin_settings', $default_settings);
                Azure_Logger::info('Default settings created');
                $write_log("✅ **[STEP 7]** Default settings created");
            } else {
                $write_log("ℹ️ **[STEP 7]** Settings already exist, skipping");
            }
            
            $write_log("⏳ **[STEP 8]** Creating backup directory");
            // Create backup directory
            $backup_dir = AZURE_PLUGIN_PATH . 'backups/';
            if (!is_dir($backup_dir)) {
                if (!wp_mkdir_p($backup_dir)) {
                    throw new Exception("Failed to create backup directory: {$backup_dir}");
                }
                // Add .htaccess for security
                $htaccess_content = "Order deny,allow\nDeny from all\n";
                file_put_contents($backup_dir . '.htaccess', $htaccess_content);
            }
            $write_log("✅ **[STEP 8]** Backup directory ready");
            
            $write_log("⏳ **[STEP 9]** Finalizing activation");
            Azure_Logger::info('Plugin activation completed successfully');
            $write_log("🎉 **[SUCCESS]** Plugin activation completed successfully");
            
        } catch (Exception $e) {
            $error_msg = 'Failed during activation: ' . $e->getMessage();
            $write_log("❌ **[ERROR]** {$error_msg}");
            $write_log("📍 **[ERROR FILE]** " . $e->getFile() . " line " . $e->getLine());
            $write_log("📝 **[ERROR TRACE]** " . $e->getTraceAsString());
            
            if (class_exists('Azure_Logger')) {
                Azure_Logger::error($error_msg);
                Azure_Logger::error('Error in file: ' . $e->getFile() . ' line ' . $e->getLine());
                Azure_Logger::error('Stack trace: ' . $e->getTraceAsString());
            } else {
                error_log('Azure Plugin: ' . $error_msg);
            }
            
            $write_log("🔄 **[ACTION]** Deactivating plugin to prevent broken state");
            // Deactivate plugin to prevent broken state
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('Azure Plugin activation failed: ' . $error_msg . ' - Check ' . $log_file . ' for detailed logs.');
            
        } catch (Error $e) {
            $error_msg = 'Fatal error during activation: ' . $e->getMessage();
            $write_log("💀 **[FATAL ERROR]** {$error_msg}");
            $write_log("📍 **[ERROR FILE]** " . $e->getFile() . " line " . $e->getLine());
            $write_log("📝 **[ERROR TRACE]** " . $e->getTraceAsString());
            
            error_log('Azure Plugin Fatal Error: ' . $error_msg);
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('Azure Plugin fatal error: ' . $error_msg . ' - Check ' . $log_file . ' for detailed logs.');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        if (class_exists('Azure_Logger')) {
            Azure_Logger::info('Azure Plugin deactivated');
        }
        
        // Clear any scheduled events from all modules
        wp_clear_scheduled_hook('azure_backup_scheduled');
        wp_clear_scheduled_hook('azure_backup_cleanup');
        wp_clear_scheduled_hook('azure_calendar_sync_events');
        wp_clear_scheduled_hook('azure_mail_token_refresh');
        wp_clear_scheduled_hook('azure_sso_scheduled_sync');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create AzureAD WordPress role for SSO users
     */
    private function create_azuread_role() {
        // Check if role already exists
        if (get_role('azuread')) {
            Azure_Logger::info('AzureAD role already exists');
            return;
        }
        
        // Add AzureAD role with basic capabilities
        add_role('azuread', 'Azure AD User', array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'publish_posts' => false,
            'upload_files' => false,
            'edit_pages' => false,
            'edit_others_posts' => false,
            'edit_published_posts' => false,
            'delete_others_posts' => false,
            'delete_published_posts' => false,
            'delete_pages' => false,
            'manage_categories' => false,
            'manage_links' => false,
            'moderate_comments' => false,
            'unfiltered_html' => false,
            'edit_others_pages' => false,
            'edit_published_pages' => false,
            'delete_others_pages' => false,
            'delete_published_pages' => false
        ));
        
        Azure_Logger::info('Created AzureAD role for SSO users');
    }
}

// Initialize the plugin with debugging
try {
    $log_file = AZURE_PLUGIN_PATH . 'logs.md';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "**{$timestamp}** 🏁 **[MAIN]** Initializing plugin instance  \n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    AzurePlugin::get_instance();
    
    $log_entry = "**{$timestamp}** ✅ **[MAIN]** Plugin instance initialized successfully  \n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
} catch (Exception $e) {
    $log_entry = "**{$timestamp}** ❌ **[MAIN ERROR]** " . $e->getMessage() . "  \n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    $log_entry = "**{$timestamp}** 📍 **[MAIN ERROR FILE]** " . $e->getFile() . " line " . $e->getLine() . "  \n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
} catch (Error $e) {
    $log_entry = "**{$timestamp}** 💀 **[MAIN FATAL]** " . $e->getMessage() . "  \n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    $log_entry = "**{$timestamp}** 📍 **[MAIN FATAL FILE]** " . $e->getFile() . " line " . $e->getLine() . "  \n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}
