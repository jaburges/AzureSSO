<?php
/**
 * Azure Plugin Database Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // SSO Users table for user mapping
        $table_sso_users = $wpdb->prefix . 'azure_sso_users';
        $sql_sso_users = "CREATE TABLE $table_sso_users (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            wordpress_user_id bigint(20) UNSIGNED NOT NULL,
            azure_user_id varchar(255) NOT NULL,
            azure_email varchar(320) NOT NULL,
            azure_display_name varchar(255),
            last_login datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY wordpress_user_id (wordpress_user_id),
            UNIQUE KEY azure_user_id (azure_user_id),
            KEY azure_email (azure_email)
        ) $charset_collate;";
        
        // Backup Jobs table
        $table_backup_jobs = $wpdb->prefix . 'azure_backup_jobs';
        $sql_backup_jobs = "CREATE TABLE $table_backup_jobs (
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
        
        // Backup Files table
        $table_backup_files = $wpdb->prefix . 'azure_backup_files';
        $sql_backup_files = "CREATE TABLE $table_backup_files (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            job_id mediumint(9) NOT NULL,
            file_type varchar(50) NOT NULL,
            original_path varchar(1000) NOT NULL,
            backup_path varchar(1000) NOT NULL,
            file_size bigint(20) DEFAULT 0,
            checksum varchar(255),
            status varchar(50) DEFAULT 'pending',
            error_message longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY file_type (file_type),
            KEY status (status)
        ) $charset_collate;";
        
        // Calendar Embeds table
        $table_calendar_embeds = $wpdb->prefix . 'azure_calendar_embeds';
        $sql_calendar_embeds = "CREATE TABLE $table_calendar_embeds (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            embed_name varchar(255) NOT NULL,
            calendar_id varchar(255) NOT NULL,
            user_email varchar(320),
            view_type varchar(50) DEFAULT 'month',
            timezone varchar(100) DEFAULT 'UTC',
            color_theme varchar(50) DEFAULT 'blue',
            max_events int(11) DEFAULT 50,
            cache_duration int(11) DEFAULT 3600,
            shortcode_params longtext,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY calendar_id (calendar_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Calendar Events Cache table
        $table_calendar_cache = $wpdb->prefix . 'azure_calendar_cache';
        $sql_calendar_cache = "CREATE TABLE $table_calendar_cache (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            calendar_id varchar(255) NOT NULL,
            event_data longtext NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY cache_key (cache_key),
            KEY calendar_id (calendar_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // Email Queue table
        $table_email_queue = $wpdb->prefix . 'azure_email_queue';
        $sql_email_queue = "CREATE TABLE $table_email_queue (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            to_email varchar(320) NOT NULL,
            from_email varchar(320),
            subject varchar(500) NOT NULL,
            message longtext NOT NULL,
            headers longtext,
            attachments longtext,
            priority int(11) DEFAULT 5,
            status varchar(50) DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            error_message longtext,
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY priority (priority),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";
        
        // Email Tokens table for OAuth tokens
        $table_email_tokens = $wpdb->prefix . 'azure_email_tokens';
        $sql_email_tokens = "CREATE TABLE $table_email_tokens (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_email varchar(320) NOT NULL,
            access_token longtext NOT NULL,
            refresh_token longtext,
            token_type varchar(50) DEFAULT 'Bearer',
            expires_at datetime NOT NULL,
            scope longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_email (user_email),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // Email Logs table for tracking all emails sent through WordPress
        $table_email_logs = $wpdb->prefix . 'azure_email_logs';
        $sql_email_logs = "CREATE TABLE $table_email_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            to_email varchar(500) NOT NULL,
            from_email varchar(320),
            subject varchar(500) NOT NULL,
            message longtext,
            headers longtext,
            attachments longtext,
            method varchar(50) DEFAULT 'wp_mail',
            status varchar(50) DEFAULT 'sent',
            error_message varchar(1000),
            plugin_source varchar(100),
            user_id mediumint(9),
            ip_address varchar(45),
            user_agent varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY to_email (to_email(100)),
            KEY from_email (from_email(100)),
            KEY status (status),
            KEY method (method),
            KEY plugin_source (plugin_source),
            FULLTEXT KEY search_content (subject, message)
        ) $charset_collate;";
        
        // Activity Log table for all modules
        $table_activity_log = $wpdb->prefix . 'azure_activity_log';
        $sql_activity_log = "CREATE TABLE $table_activity_log (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            module varchar(50) NOT NULL,
            action varchar(100) NOT NULL,
            object_type varchar(100),
            object_id varchar(255),
            user_id bigint(20) UNSIGNED,
            ip_address varchar(45),
            user_agent varchar(500),
            details longtext,
            status varchar(50) DEFAULT 'success',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY module (module),
            KEY action (action),
            KEY user_id (user_id),
            KEY created_at (created_at),
            KEY status (status)
        ) $charset_collate;";
        
        // TEC Sync History table for tracking sync operations
        $table_tec_sync_history = $wpdb->prefix . 'azure_tec_sync_history';
        $sql_tec_sync_history = "CREATE TABLE $table_tec_sync_history (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            tec_event_id bigint(20) UNSIGNED NOT NULL,
            outlook_event_id varchar(255),
            sync_direction varchar(20) NOT NULL,
            sync_action varchar(50) NOT NULL,
            sync_status varchar(50) NOT NULL,
            sync_message longtext,
            data_before longtext,
            data_after longtext,
            conflict_resolution varchar(50),
            sync_timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY tec_event_id (tec_event_id),
            KEY outlook_event_id (outlook_event_id),
            KEY sync_direction (sync_direction),
            KEY sync_status (sync_status),
            KEY sync_timestamp (sync_timestamp)
        ) $charset_collate;";
        
        // TEC Sync Conflicts table for manual resolution
        $table_tec_sync_conflicts = $wpdb->prefix . 'azure_tec_sync_conflicts';
        $sql_tec_sync_conflicts = "CREATE TABLE $table_tec_sync_conflicts (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            tec_event_id bigint(20) UNSIGNED NOT NULL,
            outlook_event_id varchar(255) NOT NULL,
            conflict_type varchar(50) NOT NULL,
            tec_data longtext NOT NULL,
            outlook_data longtext NOT NULL,
            resolution_status varchar(50) DEFAULT 'pending',
            resolution_method varchar(50),
            resolved_by bigint(20) UNSIGNED,
            resolved_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY tec_event_id (tec_event_id),
            KEY outlook_event_id (outlook_event_id),
            KEY resolution_status (resolution_status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // TEC Sync Queue table for batch processing
        $table_tec_sync_queue = $wpdb->prefix . 'azure_tec_sync_queue';
        $sql_tec_sync_queue = "CREATE TABLE $table_tec_sync_queue (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            tec_event_id bigint(20) UNSIGNED NOT NULL,
            sync_direction varchar(20) NOT NULL,
            sync_action varchar(50) NOT NULL,
            priority int(11) DEFAULT 5,
            status varchar(50) DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            error_message longtext,
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY tec_event_id (tec_event_id),
            KEY sync_direction (sync_direction),
            KEY status (status),
            KEY priority (priority),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";
        
        // TEC Calendar Mappings table for Outlook calendar to TEC category mappings
        $table_tec_calendar_mappings = $wpdb->prefix . 'azure_tec_calendar_mappings';
        $sql_tec_calendar_mappings = "CREATE TABLE $table_tec_calendar_mappings (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            outlook_calendar_id varchar(255) NOT NULL,
            outlook_calendar_name varchar(255) NOT NULL,
            tec_category_id bigint(20) UNSIGNED,
            tec_category_name varchar(255) NOT NULL,
            sync_enabled tinyint(1) DEFAULT 1,
            schedule_enabled tinyint(1) DEFAULT 0,
            schedule_frequency varchar(20) DEFAULT 'hourly',
            schedule_lookback_days int(11) DEFAULT 30,
            schedule_lookahead_days int(11) DEFAULT 365,
            last_sync datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY outlook_calendar_id (outlook_calendar_id),
            KEY sync_enabled (sync_enabled),
            KEY schedule_enabled (schedule_enabled),
            KEY last_sync (last_sync)
        ) $charset_collate;";
        
        // OneDrive Media Files table for file mappings
        $table_onedrive_files = $wpdb->prefix . 'azure_onedrive_files';
        $sql_onedrive_files = "CREATE TABLE $table_onedrive_files (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) UNSIGNED,
            onedrive_id varchar(255) NOT NULL,
            onedrive_path text NOT NULL,
            file_name varchar(255) NOT NULL,
            file_size bigint(20) UNSIGNED NOT NULL,
            mime_type varchar(100),
            public_url text,
            download_url text,
            thumbnail_url text,
            folder_year varchar(4),
            last_modified datetime,
            sync_status varchar(20) DEFAULT 'synced',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY onedrive_id (onedrive_id),
            KEY attachment_id (attachment_id),
            KEY folder_year (folder_year),
            KEY sync_status (sync_status)
        ) $charset_collate;";
        
        // OneDrive Media Sync Queue table for batch operations
        $table_onedrive_sync_queue = $wpdb->prefix . 'azure_onedrive_sync_queue';
        $sql_onedrive_sync_queue = "CREATE TABLE $table_onedrive_sync_queue (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            operation varchar(50) NOT NULL,
            file_id bigint(20) UNSIGNED,
            local_path text,
            onedrive_path text,
            status varchar(20) DEFAULT 'pending',
            retry_count int(11) DEFAULT 0,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY file_id (file_id)
        ) $charset_collate;";
        
        // OneDrive Media Tokens table for OAuth tokens
        $table_onedrive_tokens = $wpdb->prefix . 'azure_onedrive_tokens';
        $sql_onedrive_tokens = "CREATE TABLE $table_onedrive_tokens (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_email varchar(320) NOT NULL,
            access_token longtext NOT NULL,
            refresh_token longtext,
            token_type varchar(50) DEFAULT 'Bearer',
            expires_at datetime NOT NULL,
            scope longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_email (user_email),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create all tables
        dbDelta($sql_sso_users);
        dbDelta($sql_backup_jobs);
        dbDelta($sql_backup_files);
        dbDelta($sql_calendar_embeds);
        dbDelta($sql_calendar_cache);
        dbDelta($sql_email_queue);
        dbDelta($sql_email_tokens);
        dbDelta($sql_email_logs);
        dbDelta($sql_activity_log);
        dbDelta($sql_tec_sync_history);
        dbDelta($sql_tec_sync_conflicts);
        dbDelta($sql_tec_sync_queue);
        dbDelta($sql_tec_calendar_mappings);
        dbDelta($sql_onedrive_files);
        dbDelta($sql_onedrive_sync_queue);
        dbDelta($sql_onedrive_tokens);
        
        // Log successful table creation
        Azure_Logger::info('Azure Plugin database tables created successfully');
    }
    
    public static function get_table_name($table) {
        global $wpdb;
        
        $tables = array(
            'sso_users' => $wpdb->prefix . 'azure_sso_users',
            'backup_jobs' => $wpdb->prefix . 'azure_backup_jobs',
            'backup_files' => $wpdb->prefix . 'azure_backup_files',
            'calendar_embeds' => $wpdb->prefix . 'azure_calendar_embeds',
            'calendar_cache' => $wpdb->prefix . 'azure_calendar_cache',
            'email_queue' => $wpdb->prefix . 'azure_email_queue',
            'email_tokens' => $wpdb->prefix . 'azure_email_tokens',
            'email_logs' => $wpdb->prefix . 'azure_email_logs',
            'activity_log' => $wpdb->prefix . 'azure_activity_log',
            'tec_sync_history' => $wpdb->prefix . 'azure_tec_sync_history',
            'tec_sync_conflicts' => $wpdb->prefix . 'azure_tec_sync_conflicts',
            'tec_sync_queue' => $wpdb->prefix . 'azure_tec_sync_queue',
            'tec_calendar_mappings' => $wpdb->prefix . 'azure_tec_calendar_mappings',
            'onedrive_files' => $wpdb->prefix . 'azure_onedrive_files',
            'onedrive_sync_queue' => $wpdb->prefix . 'azure_onedrive_sync_queue',
            'onedrive_tokens' => $wpdb->prefix . 'azure_onedrive_tokens',
            'newsletters' => $wpdb->prefix . 'azure_newsletters',
            'newsletter_queue' => $wpdb->prefix . 'azure_newsletter_queue',
            'newsletter_stats' => $wpdb->prefix . 'azure_newsletter_stats',
            'newsletter_lists' => $wpdb->prefix . 'azure_newsletter_lists',
            'newsletter_list_members' => $wpdb->prefix . 'azure_newsletter_list_members',
            'newsletter_bounces' => $wpdb->prefix . 'azure_newsletter_bounces',
            'newsletter_templates' => $wpdb->prefix . 'azure_newsletter_templates',
            'newsletter_sending_config' => $wpdb->prefix . 'azure_newsletter_sending_config'
        );
        
        return isset($tables[$table]) ? $tables[$table] : false;
    }
    
    public static function log_activity($module, $action, $object_type = null, $object_id = null, $details = null, $status = 'success') {
        global $wpdb;
        
        $table = self::get_table_name('activity_log');
        if (!$table) {
            return false;
        }
        
        $user_id = get_current_user_id();
        $ip_address = self::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        
        $data = array(
            'module' => sanitize_text_field($module),
            'action' => sanitize_text_field($action),
            'object_type' => $object_type ? sanitize_text_field($object_type) : null,
            'object_id' => $object_id ? sanitize_text_field($object_id) : null,
            'user_id' => $user_id ?: null,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'details' => $details ? wp_json_encode($details) : null,
            'status' => sanitize_text_field($status)
        );
        
        $formats = array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s');
        
        return $wpdb->insert($table, $data, $formats);
    }
    
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    public static function cleanup_old_records($days = 90) {
        global $wpdb;
        
        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Clean up old backup jobs
        $backup_jobs_table = self::get_table_name('backup_jobs');
        if ($backup_jobs_table) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$backup_jobs_table} WHERE created_at < %s AND status IN ('completed', 'failed')",
                $date_threshold
            ));
        }
        
        // Clean up expired calendar cache
        $calendar_cache_table = self::get_table_name('calendar_cache');
        if ($calendar_cache_table) {
            $wpdb->query(
                "DELETE FROM {$calendar_cache_table} WHERE expires_at < NOW()"
            );
        }
        
        // Clean up old activity logs
        $activity_log_table = self::get_table_name('activity_log');
        if ($activity_log_table) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$activity_log_table} WHERE created_at < %s",
                $date_threshold
            ));
        }
        
        // Clean up sent emails from queue
        $email_queue_table = self::get_table_name('email_queue');
        if ($email_queue_table) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$email_queue_table} WHERE created_at < %s AND status = 'sent'",
                $date_threshold
            ));
        }
        
        // Clean up old TEC sync history
        $tec_sync_history_table = self::get_table_name('tec_sync_history');
        if ($tec_sync_history_table) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$tec_sync_history_table} WHERE created_at < %s",
                $date_threshold
            ));
        }
        
        // Clean up resolved TEC sync conflicts
        $tec_sync_conflicts_table = self::get_table_name('tec_sync_conflicts');
        if ($tec_sync_conflicts_table) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$tec_sync_conflicts_table} WHERE created_at < %s AND resolution_status = 'resolved'",
                $date_threshold
            ));
        }
        
        // Clean up processed TEC sync queue items
        $tec_sync_queue_table = self::get_table_name('tec_sync_queue');
        if ($tec_sync_queue_table) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$tec_sync_queue_table} WHERE created_at < %s AND status IN ('completed', 'failed')",
                $date_threshold
            ));
        }
    }
}
