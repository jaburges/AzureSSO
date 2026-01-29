<?php
/**
 * Plugin Name: Microsoft PTA
 * Plugin URI: https://github.com/jaburges/AzureSSO
 * Description: Complete Microsoft 365 integration for WordPress - SSO authentication with Azure AD claims mapping, automated backup to Azure Blob Storage, Outlook calendar embedding with shared mailbox support, TEC calendar sync, email via Microsoft Graph API, PTA role management with O365 Groups sync, WooCommerce class products with TEC event generation, Newsletter module, and OneDrive media integration.
 * Version: 3.12
 * Author: Jamie Burgess
 * License: GPL v2 or later
 * Text Domain: azure-plugin
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AZURE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AZURE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AZURE_PLUGIN_VERSION', '3.12');

// Main plugin class for Microsoft PTA
class AzurePlugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new AzurePlugin();
        }
        return self::$instance;
    }
    
    private function __construct() {
        try {
            // Register hooks
            add_action('plugins_loaded', array($this, 'load_dependencies'), 5);
            add_action('init', array($this, 'init'), 10);
            
            // Activation/deactivation hooks must be registered immediately
            register_activation_hook(__FILE__, array($this, 'activate'));
            register_deactivation_hook(__FILE__, array($this, 'deactivate'));
            
        } catch (Exception $e) {
            // Log critical constructor errors only
            if (class_exists('Azure_Logger')) {
                Azure_Logger::error('Plugin constructor failed: ' . $e->getMessage(), array(
                    'module' => 'Core',
                    'file' => __FILE__,
                    'line' => __LINE__
                ));
            }
            error_log('Azure Plugin: Constructor error - ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function load_dependencies() {
        try {
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
                
                // User Account functionality
                'class-user-account-shortcode.php' => 'User Account Shortcode class',
                
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
                
                // TEC Integration functionality
                'class-tec-integration.php' => 'TEC Integration class',
                'class-tec-sync-engine.php' => 'TEC Sync Engine class',
                'class-tec-data-mapper.php' => 'TEC Data Mapper class',
                'class-tec-calendar-mapping-manager.php' => 'TEC Calendar Mapping Manager class',
                'class-tec-sync-scheduler.php' => 'TEC Sync Scheduler class',
                'class-tec-integration-ajax.php' => 'TEC Integration AJAX handlers class',
                
                // PTA functionality
                'class-pta-database.php' => 'PTA Database class',
                'class-pta-manager.php' => 'PTA Manager class',
                'class-pta-sync-engine.php' => 'PTA Sync Engine class',
                'class-pta-groups-manager.php' => 'PTA Groups Manager class',
                'class-pta-shortcode.php' => 'PTA Shortcode class',
                'class-pta-beaver-builder.php' => 'PTA Beaver Builder class',
                
                // OneDrive Media functionality
                'class-onedrive-media-auth.php' => 'OneDrive Media Auth class',
                'class-onedrive-media-graph-api.php' => 'OneDrive Media Graph API class',
                'class-onedrive-media-manager.php' => 'OneDrive Media Manager class',
                
                // Classes functionality
                'class-classes-module.php' => 'Classes Module class',
                
                // Upcoming Events shortcode functionality
                'class-upcoming-module.php' => 'Upcoming Events Module class',
                
                // Newsletter functionality
                'class-newsletter-module.php' => 'Newsletter Module class',
                
                // Setup Wizard
                'class-setup-wizard.php' => 'Setup Wizard class'
            );
            
            // Load critical files first - these must succeed
            foreach ($critical_files as $file => $description) {
                $file_path = AZURE_PLUGIN_PATH . 'includes/' . $file;
                
                if (!file_exists($file_path)) {
                    $error_msg = "Critical file not found: {$file_path}";
                    error_log('Azure Plugin: ' . $error_msg);
                    throw new Exception($error_msg);
                }
                
                try {
                    require_once $file_path;
                } catch (ParseError $e) {
                    $error_msg = "Parse error in critical file {$file}: " . $e->getMessage();
                    error_log('Azure Plugin: ' . $error_msg . ' at ' . $e->getFile() . ':' . $e->getLine());
                    throw new Exception($error_msg);
                } catch (Error $e) {
                    $error_msg = "Fatal error in critical file {$file}: " . $e->getMessage();
                    error_log('Azure Plugin: ' . $error_msg . ' at ' . $e->getFile() . ':' . $e->getLine());
                    throw new Exception($error_msg);
                } catch (Exception $e) {
                    $error_msg = "Error loading critical file {$file}: " . $e->getMessage();
                    error_log('Azure Plugin: ' . $error_msg);
                    throw new Exception($error_msg);
                }
            }
            
            // Load optional files - failures are logged but don't stop loading
            foreach ($optional_files as $file => $description) {
                $file_path = AZURE_PLUGIN_PATH . 'includes/' . $file;
                
                if (!file_exists($file_path)) {
                    if (WP_DEBUG) {
                        error_log("Azure Plugin: Optional file not found: {$file_path}");
                    }
                    continue;
                }
                
                try {
                    require_once $file_path;
                } catch (Exception $e) {
                    error_log("Azure Plugin: Error loading optional file {$file}: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            if (class_exists('Azure_Logger')) {
                Azure_Logger::error('Failed to load dependencies: ' . $e->getMessage(), array(
                    'module' => 'Core',
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ));
            }
            error_log('Azure Plugin: load_dependencies failed - ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function init() {
        try {
            // Initialize logger first if not already initialized
            if (class_exists('Azure_Logger') && !Azure_Logger::is_initialized()) {
                Azure_Logger::init();
            }
            
            // Register scheduled log cleanup
            if (!wp_next_scheduled('azure_plugin_cleanup_logs')) {
                wp_schedule_event(time(), 'daily', 'azure_plugin_cleanup_logs');
            }
            add_action('azure_plugin_cleanup_logs', array('Azure_Logger', 'scheduled_cleanup'));
            
            // Load plugin textdomain
            load_plugin_textdomain('azure-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages');
            
            // Initialize settings system
            if (class_exists('Azure_Settings')) {
                Azure_Settings::get_instance();
            }
            
            // Initialize admin components
            if (is_admin() && class_exists('Azure_Admin')) {
                Azure_Admin::get_instance();
            }
            
            // Get settings for module initialization
            $settings = get_option('azure_plugin_settings', array());
            
            // Initialize enabled modules
            if (!empty($settings['enable_sso'])) {
                $this->init_sso_components();
            }
            
            if (!empty($settings['enable_backup'])) {
                $this->init_backup_components();
            }
            
            if (!empty($settings['enable_calendar'])) {
                $this->init_calendar_components();
            }
            
            if ($settings['enable_tec_integration'] ?? false) {
                $this->init_tec_components();
            }
            
            // Always initialize Email Logger (logs all WordPress emails)
            $this->init_email_logger();
            
            if (!empty($settings['enable_email'])) {
                $this->init_email_components();
            }
            
            if (!empty($settings['enable_pta'])) {
                $this->init_pta_components();
            }
            
            if (!empty($settings['enable_onedrive_media'])) {
                $this->init_onedrive_media_components();
            }
            
            // Always register Classes taxonomy (needed for admin URLs even when module is disabled)
            $this->register_classes_taxonomy();
            
            if (!empty($settings['enable_classes'])) {
                $this->init_classes_components();
            }
            
            // Upcoming Events module - always available (no credentials needed)
            $this->init_upcoming_components();
            
            // Always load newsletter AJAX handlers (needed for table creation even when module is disabled)
            $this->load_newsletter_ajax();
            
            if (!empty($settings['enable_newsletter'])) {
                $this->init_newsletter_components();
            }
            
        } catch (Exception $e) {
            if (class_exists('Azure_Logger')) {
                Azure_Logger::error('Plugin init failed: ' . $e->getMessage(), array(
                    'module' => 'Core',
                    'file' => __FILE__,
                    'line' => __LINE__
                ));
            }
            error_log('Azure Plugin: init error - ' . $e->getMessage());
        }
    }
    
    private function init_sso_components() {
        try {
            if (class_exists('Azure_SSO_Auth')) {
                new Azure_SSO_Auth();
                Azure_Logger::debug_module('SSO', 'Azure_SSO_Auth initialized successfully');
            }
            if (class_exists('Azure_SSO_Shortcode')) {
                new Azure_SSO_Shortcode();
                Azure_Logger::debug_module('SSO', 'Azure_SSO_Shortcode initialized successfully');
            }
            if (class_exists('Azure_SSO_Sync')) {
                new Azure_SSO_Sync();
                Azure_Logger::debug_module('SSO', 'Azure_SSO_Sync initialized successfully');
            }
            if (class_exists('Azure_User_Account_Shortcode')) {
                new Azure_User_Account_Shortcode();
                Azure_Logger::debug_module('SSO', 'Azure_User_Account_Shortcode initialized successfully');
            }
        } catch (Exception $e) {
            Azure_Logger::error('SSO init failed: ' . $e->getMessage(), array(
                'module' => 'SSO',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            error_log('Azure Plugin: SSO init error - ' . $e->getMessage());
        }
    }
    
    private function init_backup_components() {
        try {
            // Initialize backup components in the correct order to avoid dependency issues
            // Storage class should NOT be instantiated here as it's only used internally by other classes
            if (class_exists('Azure_Backup')) {
                new Azure_Backup();
                Azure_Logger::debug_module('Backup', 'Azure_Backup initialized successfully');
            }
            if (class_exists('Azure_Backup_Restore')) {
                new Azure_Backup_Restore();
                Azure_Logger::debug_module('Backup', 'Azure_Backup_Restore initialized successfully');
            }
            if (class_exists('Azure_Backup_Scheduler')) {
                new Azure_Backup_Scheduler();
                Azure_Logger::debug_module('Backup', 'Azure_Backup_Scheduler initialized successfully');
            }
            // Note: Azure_Backup_Storage is not instantiated here - it's created on-demand by other classes
        } catch (Exception $e) {
            Azure_Logger::error('Backup init failed: ' . $e->getMessage(), array(
                'module' => 'Backup',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            error_log('Azure Plugin: Backup init error - ' . $e->getMessage());
        }
    }
    
    private function init_calendar_components() {
        try {
            if (class_exists('Azure_Calendar_Auth')) {
                new Azure_Calendar_Auth();
                Azure_Logger::debug_module('Calendar', 'Azure_Calendar_Auth initialized successfully');
            }
            if (class_exists('Azure_Calendar_GraphAPI')) {
                new Azure_Calendar_GraphAPI();
                Azure_Logger::debug_module('Calendar', 'Azure_Calendar_GraphAPI initialized successfully');
            }
            if (class_exists('Azure_Calendar_Manager')) {
                new Azure_Calendar_Manager();
                Azure_Logger::debug_module('Calendar', 'Azure_Calendar_Manager initialized successfully');
            }
            if (class_exists('Azure_Calendar_Shortcode')) {
                new Azure_Calendar_Shortcode();
                Azure_Logger::debug_module('Calendar', 'Azure_Calendar_Shortcode initialized successfully');
            }
            if (class_exists('Azure_Calendar_EventsCPT')) {
                new Azure_Calendar_EventsCPT();
                Azure_Logger::debug_module('Calendar', 'Azure_Calendar_EventsCPT initialized successfully');
            }
            if (class_exists('Azure_Calendar_ICalSync')) {
                new Azure_Calendar_ICalSync();
                Azure_Logger::debug_module('Calendar', 'Azure_Calendar_ICalSync initialized successfully');
            }
            if (class_exists('Azure_Calendar_EventsShortcode')) {
                new Azure_Calendar_EventsShortcode();
                Azure_Logger::debug_module('Calendar', 'Azure_Calendar_EventsShortcode initialized successfully');
            }
        } catch (Exception $e) {
            Azure_Logger::error('Calendar init failed: ' . $e->getMessage(), array(
                'module' => 'Calendar',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            error_log('Azure Plugin: Calendar init error - ' . $e->getMessage());
        }
    }
    
    private function init_tec_components() {
        try {
            if (class_exists('Azure_TEC_Integration')) {
                Azure_TEC_Integration::get_instance();
                Azure_Logger::debug_module('TEC', 'Azure_TEC_Integration initialized successfully');
            }
            
            // Initialize TEC AJAX handlers
            if (class_exists('Azure_TEC_Integration_Ajax')) {
                new Azure_TEC_Integration_Ajax();
                Azure_Logger::debug_module('TEC', 'Azure_TEC_Integration_Ajax initialized successfully');
            }
            
            // Initialize TEC Sync Scheduler
            if (class_exists('Azure_TEC_Sync_Scheduler')) {
                new Azure_TEC_Sync_Scheduler();
                Azure_Logger::debug_module('TEC', 'Azure_TEC_Sync_Scheduler initialized successfully');
            }
        } catch (Exception $e) {
            Azure_Logger::error('TEC init failed: ' . $e->getMessage(), array(
                'module' => 'TEC',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            error_log('Azure Plugin: TEC init error - ' . $e->getMessage());
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
        try {
            if (class_exists('Azure_Email_Auth')) {
                new Azure_Email_Auth();
                Azure_Logger::debug_module('Email', 'Azure_Email_Auth initialized successfully');
            }
            
            if (class_exists('Azure_Email_Mailer')) {
                new Azure_Email_Mailer();
                Azure_Logger::debug_module('Email', 'Azure_Email_Mailer initialized successfully');
            }
            
            if (class_exists('Azure_Email_Shortcode')) {
                new Azure_Email_Shortcode();
                Azure_Logger::debug_module('Email', 'Azure_Email_Shortcode initialized successfully');
            }
        } catch (Exception $e) {
            Azure_Logger::error('Email init failed: ' . $e->getMessage(), array(
                'module' => 'Email',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            error_log('Azure Plugin: Email init error - ' . $e->getMessage());
        }
    }
    
    private function init_pta_components() {
        try {
            Azure_Logger::debug_module('PTA', 'Starting PTA components initialization');
            
            if (class_exists('Azure_PTA_Database')) {
                Azure_PTA_Database::init();
                Azure_Logger::debug_module('PTA', 'Azure_PTA_Database initialized successfully');
            }
            
            if (class_exists('Azure_PTA_Manager')) {
                Azure_PTA_Manager::get_instance();
                Azure_Logger::debug_module('PTA', 'Azure_PTA_Manager initialized successfully');
            }
            
            if (class_exists('Azure_PTA_Sync_Engine')) {
                new Azure_PTA_Sync_Engine();
                Azure_Logger::debug_module('PTA', 'Azure_PTA_Sync_Engine initialized successfully');
            }
            
            if (class_exists('Azure_PTA_Groups_Manager')) {
                new Azure_PTA_Groups_Manager();
                Azure_Logger::debug_module('PTA', 'Azure_PTA_Groups_Manager initialized successfully');
            }
            
            if (class_exists('Azure_PTA_Shortcode')) {
                new Azure_PTA_Shortcode();
                Azure_Logger::debug_module('PTA', 'Azure_PTA_Shortcode initialized successfully');
            }
            
            if (class_exists('Azure_PTA_BeaverBuilder')) {
                new Azure_PTA_BeaverBuilder();
                Azure_Logger::debug_module('PTA', 'Azure_PTA_BeaverBuilder initialized successfully');
            }
            
            Azure_Logger::debug_module('PTA', 'All PTA components initialized successfully');
            
        } catch (Exception $e) {
            Azure_Logger::error('PTA init failed: ' . $e->getMessage(), array(
                'module' => 'PTA',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            error_log('Azure Plugin: PTA init error - ' . $e->getMessage());
        }
    }
    
    private function init_onedrive_media_components() {
        try {
            Azure_Logger::debug_module('OneDrive', 'Starting OneDrive Media components initialization');
            
            if (class_exists('Azure_OneDrive_Media_Auth')) {
                new Azure_OneDrive_Media_Auth();
                Azure_Logger::debug_module('OneDrive', 'Azure_OneDrive_Media_Auth initialized successfully');
            }
            
            if (class_exists('Azure_OneDrive_Media_GraphAPI')) {
                new Azure_OneDrive_Media_GraphAPI();
                Azure_Logger::debug_module('OneDrive', 'Azure_OneDrive_Media_GraphAPI initialized successfully');
            }
            
            if (class_exists('Azure_OneDrive_Media_Manager')) {
                Azure_OneDrive_Media_Manager::get_instance();
                Azure_Logger::debug_module('OneDrive', 'Azure_OneDrive_Media_Manager initialized successfully');
            }
            
            Azure_Logger::debug_module('OneDrive', 'All OneDrive Media components initialized successfully');
            
        } catch (Exception $e) {
            Azure_Logger::error('OneDrive Media init failed: ' . $e->getMessage(), array(
                'module' => 'OneDrive',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            error_log('Azure Plugin: OneDrive Media init error - ' . $e->getMessage());
        }
    }
    
    /**
     * Register Classes taxonomy (always, even when module is disabled)
     * This ensures admin URLs work and the taxonomy is available
     */
    private function register_classes_taxonomy() {
        // Only register if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Check if already registered
        if (taxonomy_exists('class_provider')) {
            return;
        }
        
        $labels = array(
            'name'              => _x('Class Providers', 'taxonomy general name', 'azure-plugin'),
            'singular_name'     => _x('Class Provider', 'taxonomy singular name', 'azure-plugin'),
            'search_items'      => __('Search Providers', 'azure-plugin'),
            'all_items'         => __('All Providers', 'azure-plugin'),
            'edit_item'         => __('Edit Provider', 'azure-plugin'),
            'update_item'       => __('Update Provider', 'azure-plugin'),
            'add_new_item'      => __('Add New Provider', 'azure-plugin'),
            'new_item_name'     => __('New Provider Name', 'azure-plugin'),
            'menu_name'         => __('Class Providers', 'azure-plugin'),
        );
        
        $args = array(
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud'     => false,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => 'class-provider'),
        );
        
        register_taxonomy('class_provider', array('product'), $args);
    }
    
    private function init_classes_components() {
        try {
            Azure_Logger::debug_module('Classes', 'Starting Classes module initialization');
            
            if (class_exists('Azure_Classes_Module')) {
                Azure_Classes_Module::get_instance();
                Azure_Logger::debug_module('Classes', 'Azure_Classes_Module initialized successfully');
            }
            
            Azure_Logger::debug_module('Classes', 'All Classes components initialized successfully');
            
        } catch (Exception $e) {
            Azure_Logger::error('Classes init failed: ' . $e->getMessage(), array(
                'module' => 'Classes',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            error_log('Azure Plugin: Classes init error - ' . $e->getMessage());
        }
    }
    
    private function init_upcoming_components() {
        try {
            if (class_exists('Azure_Upcoming_Module')) {
                Azure_Upcoming_Module::get_instance();
            }
        } catch (Exception $e) {
            Azure_Logger::error('Upcoming Events init failed: ' . $e->getMessage(), array(
                'module' => 'Upcoming',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
        }
    }
    
    /**
     * Load Newsletter AJAX handlers (always needed for table management)
     */
    private function load_newsletter_ajax() {
        // Only load in admin AJAX context
        if (!is_admin()) {
            return;
        }
        
        $ajax_file = AZURE_PLUGIN_PATH . 'includes/class-newsletter-ajax.php';
        if (file_exists($ajax_file) && !class_exists('Azure_Newsletter_Ajax')) {
            require_once $ajax_file;
        }
    }
    
    private function init_newsletter_components() {
        try {
            Azure_Logger::debug_module('Newsletter', 'Starting Newsletter module initialization');
            
            if (class_exists('Azure_Newsletter_Module')) {
                Azure_Newsletter_Module::get_instance();
                Azure_Logger::debug_module('Newsletter', 'Azure_Newsletter_Module initialized successfully');
            }
            
            Azure_Logger::debug_module('Newsletter', 'All Newsletter components initialized successfully');
            
        } catch (Exception $e) {
            Azure_Logger::error('Newsletter init failed: ' . $e->getMessage(), array(
                'module' => 'Newsletter',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            error_log('Azure Plugin: Newsletter init error - ' . $e->getMessage());
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
                    Azure_PTA_Database::create_pta_tables();  // Fixed: correct method name
                    Azure_Logger::info('PTA database tables created successfully');
                    
                    // Seed initial data from CSV (only if tables are empty)
                    $write_log("⏳ **[STEP 6b]** Seeding PTA data from CSV");
                    Azure_Logger::info('Seeding PTA initial data from CSV');
                    Azure_PTA_Database::seed_initial_data(false);  // false = skip if already populated
                    Azure_Logger::info('PTA initial data seeded successfully');
                    $write_log("✅ **[STEP 6b]** PTA data seeded from CSV");
                } catch (Exception $e) {
                    $write_log("❌ **[STEP 6 ERROR]** Failed to create/seed PTA tables: " . $e->getMessage());
                    Azure_Logger::error('Failed to create/seed PTA database tables: ' . $e->getMessage());
                    // Continue with activation but log the error
                }
            }
            
            // Create Newsletter database tables
            if (class_exists('Azure_Newsletter_Module')) {
                try {
                    $write_log("⏳ **[STEP 6c]** Creating Newsletter database tables");
                    Azure_Logger::info('Creating Newsletter database tables');
                    Azure_Newsletter_Module::create_tables();
                    Azure_Logger::info('Newsletter database tables created successfully');
                    $write_log("✅ **[STEP 6c]** Newsletter tables created");
                } catch (Exception $e) {
                    $write_log("❌ **[STEP 6c ERROR]** Failed to create Newsletter tables: " . $e->getMessage());
                    Azure_Logger::error('Failed to create Newsletter database tables: ' . $e->getMessage());
                    // Continue with activation but log the error
                }
            }
            
            $write_log("✅ **[STEP 6]** Database tables created and seeded");
            
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

// Initialize the plugin
try {
    AzurePlugin::get_instance();
} catch (Exception $e) {
    if (class_exists('Azure_Logger')) {
        Azure_Logger::error('Plugin initialization failed: ' . $e->getMessage(), array(
            'module' => 'Core',
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ));
    }
    error_log('Azure Plugin: Fatal initialization error - ' . $e->getMessage());
}