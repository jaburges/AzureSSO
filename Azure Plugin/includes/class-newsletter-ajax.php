<?php
/**
 * Newsletter AJAX Handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Newsletter_Ajax {
    
    public function __construct() {
        // Newsletter AJAX handlers
        add_action('wp_ajax_azure_newsletter_save', array($this, 'save_newsletter'));
        add_action('wp_ajax_azure_newsletter_send', array($this, 'send_newsletter'));
        add_action('wp_ajax_azure_newsletter_send_test', array($this, 'send_test_email'));
        add_action('wp_ajax_azure_newsletter_spam_check', array($this, 'check_spam_score'));
        add_action('wp_ajax_azure_newsletter_accessibility_check', array($this, 'check_accessibility'));
        add_action('wp_ajax_azure_newsletter_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_azure_newsletter_get_recipients_count', array($this, 'get_recipients_count'));
        add_action('wp_ajax_azure_newsletter_pause', array($this, 'pause_newsletter'));
        add_action('wp_ajax_azure_newsletter_resume', array($this, 'resume_newsletter'));
        add_action('wp_ajax_azure_newsletter_cancel', array($this, 'cancel_newsletter'));
        
        // Database management
        add_action('wp_ajax_azure_newsletter_create_tables', array($this, 'create_tables'));
        add_action('wp_ajax_azure_newsletter_reset_data', array($this, 'reset_data'));
        
        // Settings page test email
        add_action('wp_ajax_azure_newsletter_send_test_email', array($this, 'send_test_email_from_settings'));
        
        // Template management
        add_action('wp_ajax_azure_newsletter_get_template', array($this, 'get_template'));
        add_action('wp_ajax_azure_newsletter_reset_templates', array($this, 'reset_templates'));
        
        // Campaign preview
        add_action('wp_ajax_azure_newsletter_get_preview', array($this, 'get_newsletter_preview'));
        
        // List member management
        add_action('wp_ajax_azure_newsletter_get_list_members', array($this, 'get_list_members'));
        add_action('wp_ajax_azure_newsletter_search_users', array($this, 'search_users'));
        add_action('wp_ajax_azure_newsletter_add_list_member', array($this, 'add_list_member'));
        add_action('wp_ajax_azure_newsletter_remove_list_member', array($this, 'remove_list_member'));
    }
    
    /**
     * Reset system templates to defaults
     */
    public function reset_templates() {
        check_ajax_referer('azure_newsletter_reset_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Ensure Newsletter Module class is loaded
        if (!class_exists('Azure_Newsletter_Module')) {
            require_once AZURE_PLUGIN_PATH . 'includes/class-newsletter-module.php';
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'azure_newsletter_templates';
        
        // Check if is_system column exists, add it if not
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'is_system'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN is_system tinyint(1) DEFAULT 0");
        }
        
        // Check if content_html column exists, add it if not
        $content_col_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'content_html'");
        if (empty($content_col_exists)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN content_html longtext AFTER description");
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN content_json longtext AFTER content_html");
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN thumbnail_url varchar(500) AFTER description");
        }
        
        // Delete ALL existing templates (since old ones don't have is_system)
        $deleted = $wpdb->query("DELETE FROM {$table}");
        
        // Re-insert default templates with HTML content
        $default_templates = Azure_Newsletter_Module::get_default_templates();
        $inserted = 0;
        $errors = array();
        
        foreach ($default_templates as $template) {
            $result = $wpdb->insert($table, $template);
            if ($result) {
                $inserted++;
            } else {
                $errors[] = $template['name'] . ': ' . $wpdb->last_error;
            }
        }
        
        if (!empty($errors)) {
            wp_send_json_error('Insert errors: ' . implode(', ', $errors));
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Reset complete. Deleted %d old templates, inserted %d new templates.', 'azure-plugin'), $deleted, $inserted),
            'deleted' => $deleted,
            'inserted' => $inserted
        ));
    }
    
    /**
     * Get template content for preview or editor
     */
    public function get_template() {
        check_ajax_referer('newsletter_get_template', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $template_id = intval($_POST['template_id'] ?? 0);
        
        if (!$template_id) {
            wp_send_json_error('Invalid template ID');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'azure_newsletter_templates';
        
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $template_id
        ));
        
        if (!$template) {
            wp_send_json_error('Template not found');
        }
        
        wp_send_json_success(array(
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'content_html' => $template->content_html,
            'content_json' => $template->content_json
        ));
    }
    
    /**
     * Get newsletter preview HTML for Quick View
     */
    public function get_newsletter_preview() {
        check_ajax_referer('azure_newsletter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $newsletter_id = intval($_POST['newsletter_id'] ?? 0);
        
        if (!$newsletter_id) {
            wp_send_json_error('Invalid newsletter ID');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'azure_newsletters';
        $queue_table = $wpdb->prefix . 'azure_newsletter_queue';
        $stats_table = $wpdb->prefix . 'azure_newsletter_stats';
        $lists_table = $wpdb->prefix . 'azure_newsletter_lists';
        
        $newsletter = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $newsletter_id
        ));
        
        if (!$newsletter) {
            wp_send_json_error('Newsletter not found');
        }
        
        // Get recipient info from saved lists OR queue
        $recipients = array('total' => 0, 'lists' => array());
        
        // First try to get from saved recipient_lists
        $saved_lists = json_decode($newsletter->recipient_lists ?? '[]', true);
        if (!empty($saved_lists)) {
            foreach ($saved_lists as $list_id) {
                if ($list_id === 'all') {
                    $count = count_users()['total_users'];
                    $recipients['lists'][] = array('name' => 'All WordPress Subscribers', 'count' => $count);
                    $recipients['total'] += $count;
                } else {
                    $list = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$lists_table} WHERE id = %d",
                        intval($list_id)
                    ));
                    if ($list) {
                        $count = 0;
                        if ($list->type === 'role') {
                            $criteria = json_decode($list->criteria, true);
                            if (!empty($criteria['roles'])) {
                                foreach ($criteria['roles'] as $role) {
                                    $count += count(get_users(array('role' => $role)));
                                }
                            }
                        } elseif ($list->type === 'custom') {
                            $members_table = $wpdb->prefix . 'azure_newsletter_list_members';
                            $count = intval($wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$members_table} WHERE list_id = %d AND unsubscribed_at IS NULL",
                                $list_id
                            )));
                        }
                        $recipients['lists'][] = array('name' => $list->name, 'count' => $count);
                        $recipients['total'] += $count;
                    }
                }
            }
        } else {
            // Fall back to queue count for sent newsletters
            $queued_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$queue_table} WHERE newsletter_id = %d",
                $newsletter_id
            ));
            $recipients['total'] = intval($queued_count);
        }
        
        // Get stats if sent
        $stats = null;
        if ($newsletter->status === 'sent') {
            $sent_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$stats_table} WHERE newsletter_id = %d AND event_type = 'sent'",
                $newsletter_id
            ));
            $open_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT email) FROM {$stats_table} WHERE newsletter_id = %d AND event_type = 'opened'",
                $newsletter_id
            ));
            $click_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT email) FROM {$stats_table} WHERE newsletter_id = %d AND event_type = 'clicked'",
                $newsletter_id
            ));
            $bounce_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$stats_table} WHERE newsletter_id = %d AND event_type = 'bounced'",
                $newsletter_id
            ));
            
            $stats = array(
                'sent' => intval($sent_count),
                'opens' => intval($open_count),
                'clicks' => intval($click_count),
                'bounces' => intval($bounce_count),
                'open_rate' => $sent_count > 0 ? round(($open_count / $sent_count) * 100, 1) : 0,
                'click_rate' => $sent_count > 0 ? round(($click_count / $sent_count) * 100, 1) : 0
            );
        }
        
        // Format scheduled_at
        $scheduled_at = null;
        if ($newsletter->scheduled_at) {
            $scheduled_at = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($newsletter->scheduled_at));
        }
        
        // Clean up HTML for preview - remove any CSS text that leaked into body
        $clean_html = $this->clean_html_for_preview($newsletter->content_html);
        
        // Return all data
        wp_send_json_success(array(
            'html' => $clean_html,
            'subject' => $newsletter->subject,
            'name' => $newsletter->name,
            'status' => $newsletter->status,
            'from_email' => $newsletter->from_email,
            'from_name' => $newsletter->from_name,
            'scheduled_at' => $scheduled_at,
            'recipients' => $recipients,
            'stats' => $stats
        ));
    }
    
    /**
     * Get list members
     */
    public function get_list_members() {
        check_ajax_referer('azure_newsletter_lists', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $list_id = intval($_POST['list_id'] ?? 0);
        
        if (!$list_id) {
            wp_send_json_error('Invalid list ID');
        }
        
        global $wpdb;
        $members_table = $wpdb->prefix . 'azure_newsletter_list_members';
        
        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT m.user_id, m.email, u.user_email, u.display_name 
             FROM {$members_table} m
             LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
             WHERE m.list_id = %d AND m.unsubscribed_at IS NULL
             ORDER BY u.display_name ASC",
            $list_id
        ));
        
        wp_send_json_success(array('members' => $members));
    }
    
    /**
     * Search WordPress users
     */
    public function search_users() {
        check_ajax_referer('azure_newsletter_lists', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        
        if (strlen($query) < 2) {
            wp_send_json_success(array('users' => array()));
        }
        
        // Search users by name or email
        $users = get_users(array(
            'search' => '*' . $query . '*',
            'search_columns' => array('user_login', 'user_email', 'display_name'),
            'number' => 20,
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
        
        $results = array();
        foreach ($users as $user) {
            $results[] = array(
                'ID' => $user->ID,
                'user_email' => $user->user_email,
                'display_name' => $user->display_name
            );
        }
        
        wp_send_json_success(array('users' => $results));
    }
    
    /**
     * Add member to list
     */
    public function add_list_member() {
        check_ajax_referer('azure_newsletter_lists', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $list_id = intval($_POST['list_id'] ?? 0);
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$list_id || !$user_id) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Get user email
        $user = get_user_by('id', $user_id);
        if (!$user) {
            wp_send_json_error('User not found');
        }
        
        global $wpdb;
        $members_table = $wpdb->prefix . 'azure_newsletter_list_members';
        
        // Check if already a member
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$members_table} WHERE list_id = %d AND user_id = %d",
            $list_id, $user_id
        ));
        
        if ($existing) {
            // Reactivate if unsubscribed
            $wpdb->update(
                $members_table,
                array('unsubscribed_at' => null),
                array('id' => $existing)
            );
        } else {
            // Insert new member
            $wpdb->insert($members_table, array(
                'list_id' => $list_id,
                'user_id' => $user_id,
                'email' => $user->user_email,
                'subscribed_at' => current_time('mysql')
            ));
        }
        
        wp_send_json_success(array('message' => 'Member added'));
    }
    
    /**
     * Remove member from list
     */
    public function remove_list_member() {
        check_ajax_referer('azure_newsletter_lists', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $list_id = intval($_POST['list_id'] ?? 0);
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$list_id || !$user_id) {
            wp_send_json_error('Invalid parameters');
        }
        
        global $wpdb;
        $members_table = $wpdb->prefix . 'azure_newsletter_list_members';
        
        // Soft delete by setting unsubscribed_at
        $wpdb->update(
            $members_table,
            array('unsubscribed_at' => current_time('mysql')),
            array('list_id' => $list_id, 'user_id' => $user_id)
        );
        
        wp_send_json_success(array('message' => 'Member removed'));
    }
    
    /**
     * Save newsletter (draft or scheduled)
     */
    public function save_newsletter() {
        check_ajax_referer('azure_newsletter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'azure_newsletters';
        
        // Ensure recipient_lists column exists (migration)
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'recipient_lists'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN recipient_lists text AFTER content_json");
        }
        
        $newsletter_id = intval($_POST['newsletter_id'] ?? 0);
        $send_option = sanitize_key($_POST['send_option'] ?? 'draft');
        
        // Parse from field
        $from_parts = explode('|', sanitize_text_field($_POST['newsletter_from'] ?? ''));
        $from_email = $from_parts[0] ?? '';
        $from_name = $from_parts[1] ?? '';
        
        // Handle recipient lists - decode from JSON string
        $recipient_lists = array();
        if (!empty($_POST['newsletter_lists'])) {
            $lists_raw = stripslashes($_POST['newsletter_lists']);
            $decoded = json_decode($lists_raw, true);
            if (is_array($decoded)) {
                $recipient_lists = array_map('sanitize_text_field', $decoded);
            } elseif (is_string($_POST['newsletter_lists'])) {
                // Fallback for single value
                $recipient_lists = array(sanitize_text_field($_POST['newsletter_lists']));
            }
        }
        
        $data = array(
            'name' => sanitize_text_field($_POST['newsletter_name'] ?? ''),
            'subject' => sanitize_text_field($_POST['newsletter_subject'] ?? ''),
            'from_email' => $from_email,
            'from_name' => $from_name,
            'content_html' => wp_kses_post($_POST['newsletter_content_html'] ?? ''),
            'content_json' => wp_unslash($_POST['newsletter_content_json'] ?? ''),
            'recipient_lists' => json_encode($recipient_lists),
            'updated_at' => current_time('mysql')
        );
        
        // Handle scheduling
        if ($send_option === 'schedule') {
            $schedule_date = sanitize_text_field($_POST['schedule_date'] ?? '');
            $schedule_time = sanitize_text_field($_POST['schedule_time'] ?? '09:00');
            
            if ($schedule_date) {
                // Convert PST to server time
                $pst = new DateTimeZone('America/Los_Angeles');
                $server_tz = new DateTimeZone(wp_timezone_string());
                
                $dt = new DateTime($schedule_date . ' ' . $schedule_time, $pst);
                $dt->setTimezone($server_tz);
                
                $data['scheduled_at'] = $dt->format('Y-m-d H:i:s');
                $data['status'] = 'scheduled';
            }
        } elseif ($send_option === 'now') {
            $data['status'] = 'scheduled';
            $data['scheduled_at'] = current_time('mysql');
        } else {
            $data['status'] = 'draft';
        }
        
        // Generate archive token if not exists
        if (empty($data['archive_token'])) {
            $data['archive_token'] = wp_generate_password(32, false);
        }
        
        if ($newsletter_id > 0) {
            // Update existing
            $wpdb->update($table, $data, array('id' => $newsletter_id));
        } else {
            // Create new
            $data['created_by'] = get_current_user_id();
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
            $newsletter_id = $wpdb->insert_id;
        }
        
        // Create WordPress page if requested
        if (!empty($_POST['create_wp_page'])) {
            $this->create_newsletter_page($newsletter_id, $data);
        }
        
        // Queue for sending if scheduled for now
        if ($send_option === 'now' || $send_option === 'schedule') {
            // Use the already-decoded recipient_lists
            $lists_to_queue = !empty($recipient_lists) ? $recipient_lists : array('all');
            
            // Ensure queue class is loaded
            if (!class_exists('Azure_Newsletter_Queue')) {
                require_once AZURE_PLUGIN_PATH . 'includes/class-newsletter-queue.php';
            }
            
            $queue = new Azure_Newsletter_Queue();
            $total_queued = 0;
            
            // Queue for each selected list
            foreach ($lists_to_queue as $list_id) {
                $queue_result = $queue->queue_newsletter($newsletter_id, $list_id, $data['scheduled_at']);
                $total_queued += ($queue_result['queued'] ?? 0);
            }
            
            wp_send_json_success(array(
                'newsletter_id' => $newsletter_id,
                'status' => $data['status'],
                'queued' => $total_queued
            ));
        }
        
        wp_send_json_success(array(
            'newsletter_id' => $newsletter_id,
            'status' => $data['status']
        ));
    }
    
    /**
     * Send test email
     */
    public function send_test_email() {
        check_ajax_referer('azure_newsletter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $email = sanitize_email($_POST['email'] ?? '');
        $html = wp_kses_post($_POST['html'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? 'Test Newsletter');
        
        // Parse from field
        $from_parts = explode('|', sanitize_text_field($_POST['from'] ?? ''));
        $from_email = $from_parts[0] ?? get_option('admin_email');
        $from_name = $from_parts[1] ?? get_bloginfo('name');
        
        if (!is_email($email)) {
            wp_send_json_error('Invalid email address');
        }
        
        if (empty($html)) {
            wp_send_json_error('No email content provided');
        }
        
        // Clean and prepare HTML for email
        $html = self::prepare_email_html($html);
        
        // Ensure sender class is loaded
        if (!class_exists('Azure_Newsletter_Sender')) {
            require_once AZURE_PLUGIN_PATH . 'includes/class-newsletter-sender.php';
        }
        
        $sender = new Azure_Newsletter_Sender();
        $result = $sender->send(array(
            'to' => $email,
            'from' => $from_email,
            'from_name' => $from_name,
            'subject' => '[TEST] ' . $subject,
            'html' => $html
        ));
        
        if ($result['success']) {
            wp_send_json_success('Test email sent successfully');
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * Prepare HTML for email sending - clean up GrapesJS output
     */
    private static function prepare_email_html($html) {
        // Remove raw CSS text that appears before HTML tags (GrapesJS bug)
        // This catches patterns like: "* { box-sizing: border-box; } body {margin: 0;} ..."
        $html = preg_replace('/^[^<]*\*\s*\{[^}]*\}[^<]*/s', '', $html);
        
        // Remove any text content before the first HTML tag
        $html = preg_replace('/^[^<]+/', '', $html);
        
        // Extract style tags from body and collect them
        $styles = '';
        if (preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $matches)) {
            foreach ($matches[1] as $style) {
                $styles .= $style . "\n";
            }
            // Remove style tags from body
            $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        }
        
        // Check if it already has a proper structure
        if (stripos($html, '<!DOCTYPE') === false) {
            // Build proper email HTML structure
            $email_html = "<!DOCTYPE html>\n";
            $email_html .= "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n";
            $email_html .= "<head>\n";
            $email_html .= "<meta charset=\"UTF-8\">\n";
            $email_html .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
            $email_html .= "<meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">\n";
            $email_html .= "<title>Newsletter</title>\n";
            
            // Add collected styles in head
            if (!empty($styles)) {
                $email_html .= "<style type=\"text/css\">\n";
                $email_html .= "/* Email Reset */\n";
                $email_html .= "body, table, td { margin: 0; padding: 0; }\n";
                $email_html .= "img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; display: block; }\n";
                $email_html .= $styles;
                $email_html .= "</style>\n";
            }
            
            $email_html .= "</head>\n";
            $email_html .= "<body style=\"margin:0;padding:0;\">\n";
            $email_html .= trim($html);
            $email_html .= "\n</body>\n</html>";
            
            return $email_html;
        }
        
        // Already has structure - just move styles to head if they're in body
        if (!empty($styles) && preg_match('/<head[^>]*>(.*?)<\/head>/is', $html, $head_match)) {
            $new_head = $head_match[1] . "\n<style type=\"text/css\">\n" . $styles . "\n</style>\n";
            $html = str_replace($head_match[1], $new_head, $html);
        }
        
        return $html;
    }
    
    /**
     * Check spam score - combines local checks with optional external SpamAssassin
     */
    public function check_spam_score() {
        check_ajax_referer('azure_newsletter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $html = wp_kses_post($_POST['html'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $from_email = sanitize_email($_POST['from_email'] ?? 'test@example.com');
        $use_external = isset($_POST['use_external']) && $_POST['use_external'] === 'true';
        
        // Run local checks first (always available, fast)
        $local_result = $this->run_local_spam_checks($html, $subject);
        
        // Optionally run external SpamAssassin check via Postmark (free API)
        $external_result = null;
        if ($use_external) {
            $external_result = $this->run_postmark_spamcheck($html, $subject, $from_email);
        }
        
        // Combine results
        $combined_score = $local_result['score'];
        $combined_issues = $local_result['issues'];
        
        if ($external_result && $external_result['success']) {
            $sa_score = $external_result['score'];
            
            // Add SpamAssassin findings
            if (!empty($external_result['rules'])) {
                foreach ($external_result['rules'] as $rule) {
                    if ($rule['score'] > 0) { // Only show rules that add to spam score
                        $combined_issues[] = array(
                            'type' => 'spamassassin',
                            'message' => $rule['description'] . ' [' . $rule['name'] . ']',
                            'score' => $rule['score']
                        );
                    }
                }
            }
            
            // Weight: average of local and SpamAssassin scores
            $combined_score = round(($local_result['score'] + min($sa_score, 10)) / 2, 1);
        }
        
        // Determine overall message
        $message = 'Excellent! Your email looks good.';
        if ($combined_score >= 3 && $combined_score < 5) {
            $message = 'Good, but there are some areas for improvement.';
        } elseif ($combined_score >= 5) {
            $message = 'Warning: Your email may be flagged as spam.';
        }
        
        wp_send_json_success(array(
            'score' => min($combined_score, 10),
            'message' => $message,
            'issues' => $combined_issues,
            'local_score' => $local_result['score'],
            'external_result' => $external_result,
            'checks_performed' => array(
                'local' => true,
                'spamassassin' => $use_external && $external_result && $external_result['success']
            )
        ));
    }
    
    /**
     * Run local spam checks (no external dependencies)
     */
    private function run_local_spam_checks($html, $subject) {
        $issues = array();
        $score = 0;
        
        // === Subject Line Checks ===
        $spam_words = array(
            'free' => 1, 'win' => 1.5, 'winner' => 1.5, 'cash' => 1.5, 
            'prize' => 1.5, 'urgent' => 1, 'act now' => 1.5, 'limited time' => 1,
            'click here' => 1, 'buy now' => 1, 'order now' => 1, 'don\'t miss' => 0.5,
            'exclusive deal' => 1, 'risk free' => 1.5, 'no obligation' => 1,
            'million' => 1.5, 'billion' => 1.5, 'guarantee' => 0.5,
            '100%' => 1, 'double your' => 1.5, 'earn money' => 1.5
        );
        
        foreach ($spam_words as $word => $weight) {
            if (stripos($subject, $word) !== false) {
                $issues[] = array(
                    'type' => 'subject',
                    'message' => "Subject contains spam trigger: '{$word}'",
                    'score' => $weight
                );
                $score += $weight;
            }
        }
        
        // ALL CAPS subject
        if (strlen($subject) > 5 && strtoupper($subject) === $subject) {
            $issues[] = array('type' => 'subject', 'message' => 'Subject is all uppercase', 'score' => 2);
            $score += 2;
        }
        
        // Excessive punctuation
        if (substr_count($subject, '!') > 1) {
            $issues[] = array('type' => 'subject', 'message' => 'Multiple exclamation marks', 'score' => 1);
            $score += 1;
        }
        
        // RE: or FW: spam trick
        if (preg_match('/^(RE:|FW:|FWD:)/i', $subject)) {
            $issues[] = array('type' => 'subject', 'message' => 'Starts with RE:/FW: (spam technique)', 'score' => 1.5);
            $score += 1.5;
        }
        
        // === Content Checks ===
        if (empty($html)) {
            $issues[] = array('type' => 'content', 'message' => 'No HTML content', 'score' => 3);
            $score += 3;
        } else {
            // Image to text ratio
            preg_match_all('/<img/i', $html, $images);
            $text_length = strlen(trim(strip_tags($html)));
            $image_count = count($images[0]);
            
            if ($image_count > 0 && $text_length < 100) {
                $issues[] = array('type' => 'content', 'message' => 'Low text-to-image ratio', 'score' => 2);
                $score += 2;
            }
            
            // Unsubscribe link
            if (stripos($html, 'unsubscribe') === false) {
                $issues[] = array('type' => 'compliance', 'message' => 'Missing unsubscribe link', 'score' => 2);
                $score += 2;
            }
            
            // Physical address
            $has_address = stripos($html, 'address') !== false || 
                           stripos($html, 'street') !== false ||
                           preg_match('/\d{5}(-\d{4})?/', $html);
            if (!$has_address) {
                $issues[] = array('type' => 'compliance', 'message' => 'No physical address (CAN-SPAM)', 'score' => 1);
                $score += 1;
            }
            
            // Spam phrases in content
            $content_spam = array('click below' => 0.5, 'act immediately' => 1, 'dear friend' => 1,
                'you have been selected' => 1.5, 'this is not spam' => 2);
            foreach ($content_spam as $phrase => $weight) {
                if (stripos($html, $phrase) !== false) {
                    $issues[] = array('type' => 'content', 'message' => "Spam phrase: '{$phrase}'", 'score' => $weight);
                    $score += $weight;
                }
            }
            
            // URL shorteners
            $shorteners = array('bit.ly', 'tinyurl', 'goo.gl', 't.co');
            foreach ($shorteners as $shortener) {
                if (stripos($html, $shortener) !== false) {
                    $issues[] = array('type' => 'content', 'message' => "URL shortener ({$shortener})", 'score' => 1);
                    $score += 1;
                    break;
                }
            }
        }
        
        return array('score' => round($score, 1), 'issues' => $issues);
    }
    
    /**
     * Run SpamAssassin check via Postmark's free API
     * https://spamcheck.postmarkapp.com/
     */
    private function run_postmark_spamcheck($html, $subject, $from_email) {
        // Generate proper email headers to avoid false positives
        $message_id = '<' . uniqid('newsletter-', true) . '@' . parse_url(home_url(), PHP_URL_HOST) . '>';
        $date = date('r'); // RFC 2822 format
        $to_email = 'test@example.com'; // Placeholder for spam check
        
        // Generate plain text version from HTML
        $plain_text = $this->html_to_plain_text($html);
        
        // Build multipart email with both HTML and plain text
        $boundary = 'boundary_' . md5(time());
        
        // Build raw email format for SpamAssassin with proper headers
        $raw_email = "From: {$from_email}\r\n";
        $raw_email .= "To: {$to_email}\r\n";
        $raw_email .= "Subject: {$subject}\r\n";
        $raw_email .= "Date: {$date}\r\n";
        $raw_email .= "Message-ID: {$message_id}\r\n";
        $raw_email .= "MIME-Version: 1.0\r\n";
        $raw_email .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $raw_email .= "\r\n";
        
        // Plain text part
        $raw_email .= "--{$boundary}\r\n";
        $raw_email .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $raw_email .= "Content-Transfer-Encoding: quoted-printable\r\n";
        $raw_email .= "\r\n";
        $raw_email .= $plain_text . "\r\n";
        $raw_email .= "\r\n";
        
        // HTML part
        $raw_email .= "--{$boundary}\r\n";
        $raw_email .= "Content-Type: text/html; charset=UTF-8\r\n";
        $raw_email .= "Content-Transfer-Encoding: quoted-printable\r\n";
        $raw_email .= "\r\n";
        $raw_email .= $html . "\r\n";
        $raw_email .= "\r\n";
        
        // End boundary
        $raw_email .= "--{$boundary}--\r\n";
        
        $response = wp_remote_post('https://spamcheck.postmarkapp.com/filter', array(
            'headers' => array('Accept' => 'application/json', 'Content-Type' => 'application/json'),
            'body' => json_encode(array('email' => $raw_email, 'options' => 'long')),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['score'])) {
            return array('success' => false, 'error' => 'Invalid API response');
        }
        
        $rules = array();
        if (!empty($body['rules'])) {
            foreach ($body['rules'] as $rule) {
                $rules[] = array(
                    'name' => $rule['name'] ?? 'Unknown',
                    'score' => floatval($rule['score'] ?? 0),
                    'description' => $rule['description'] ?? ''
                );
            }
        }
        
        return array(
            'success' => true,
            'score' => floatval($body['score']),
            'is_spam' => isset($body['success']) && $body['success'] === false,
            'rules' => $rules
        );
    }
    
    /**
     * Convert HTML email to plain text
     */
    private function html_to_plain_text($html) {
        // Remove style and script tags
        $text = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $text = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $text);
        
        // Convert common HTML elements
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = preg_replace('/<\/div>/i', "\n", $text);
        $text = preg_replace('/<\/tr>/i', "\n", $text);
        $text = preg_replace('/<\/h[1-6]>/i', "\n\n", $text);
        
        // Convert links to text with URL
        $text = preg_replace('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i', '$2 ($1)', $text);
        
        // Remove remaining HTML tags
        $text = strip_tags($text);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Clean up whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s+\n/', "\n\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Check accessibility
     */
    public function check_accessibility() {
        check_ajax_referer('azure_newsletter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $html = wp_kses_post($_POST['html'] ?? '');
        
        $checks = array();
        
        // Check for alt text on images
        preg_match_all('/<img[^>]+>/i', $html, $images);
        $images_without_alt = 0;
        foreach ($images[0] as $img) {
            if (strpos($img, 'alt=') === false || preg_match('/alt=["\'][\s]*["\']/', $img)) {
                $images_without_alt++;
            }
        }
        
        $checks[] = array(
            'pass' => $images_without_alt === 0,
            'message' => $images_without_alt === 0 
                ? 'All images have alt text' 
                : $images_without_alt . ' image(s) missing alt text'
        );
        
        // Check for link text
        preg_match_all('/<a[^>]*>(.*?)<\/a>/is', $html, $links);
        $bad_links = 0;
        foreach ($links[1] as $link_text) {
            $text = strtolower(trim(strip_tags($link_text)));
            if (in_array($text, array('click here', 'here', 'read more', 'more'))) {
                $bad_links++;
            }
        }
        
        $checks[] = array(
            'pass' => $bad_links === 0,
            'message' => $bad_links === 0 
                ? 'Link text is descriptive' 
                : $bad_links . ' link(s) have non-descriptive text'
        );
        
        // Check color contrast (simplified - just check for very light text)
        $has_light_text = preg_match('/color:\s*#[fF]{3,6}|color:\s*white|color:\s*rgb\(255/i', $html);
        $has_light_bg = preg_match('/background[^:]*:\s*#[fF]{3,6}|background[^:]*:\s*white/i', $html);
        
        $checks[] = array(
            'pass' => !($has_light_text && $has_light_bg),
            'message' => !($has_light_text && $has_light_bg) 
                ? 'Color contrast appears adequate' 
                : 'Check color contrast - light text on light background detected'
        );
        
        // Check for table layout accessibility
        $has_tables = strpos($html, '<table') !== false;
        $has_role = strpos($html, 'role="presentation"') !== false;
        
        $checks[] = array(
            'pass' => !$has_tables || $has_role,
            'message' => !$has_tables 
                ? 'No layout tables detected' 
                : ($has_role ? 'Layout tables have role="presentation"' : 'Consider adding role="presentation" to layout tables')
        );
        
        // Check heading structure
        preg_match_all('/<h([1-6])/i', $html, $headings);
        $has_headings = !empty($headings[1]);
        
        $checks[] = array(
            'pass' => $has_headings,
            'message' => $has_headings 
                ? 'Document has heading structure' 
                : 'Consider adding headings for structure'
        );
        
        wp_send_json_success(array('checks' => $checks));
    }
    
    /**
     * Test sending service connection
     */
    public function test_connection() {
        check_ajax_referer('newsletter_test_connection', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $service = sanitize_key($_POST['service'] ?? '');
        
        // Ensure sender class is loaded
        if (!class_exists('Azure_Newsletter_Sender')) {
            require_once AZURE_PLUGIN_PATH . 'includes/class-newsletter-sender.php';
        }
        
        $sender = new Azure_Newsletter_Sender($service);
        $result = $sender->test_connection();
        
        if ($result['success']) {
            wp_send_json_success('Connection successful');
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * Send a test email from the settings page
     */
    public function send_test_email_from_settings() {
        // Enable error reporting for debugging
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        
        // Set custom error handler to catch all errors
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
        
        try {
            check_ajax_referer('newsletter_send_test_email', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Permission denied', 'azure-plugin'));
                return;
            }
            
            $email = sanitize_email($_POST['email'] ?? '');
            
            if (!is_email($email)) {
                wp_send_json_error(__('Invalid email address', 'azure-plugin'));
                return;
            }
            // Ensure required classes are loaded
            if (!class_exists('Azure_Settings')) {
                require_once AZURE_PLUGIN_PATH . 'includes/class-settings.php';
            }
            if (!class_exists('Azure_Newsletter_Sender')) {
                require_once AZURE_PLUGIN_PATH . 'includes/class-newsletter-sender.php';
            }
            
            $settings = Azure_Settings::get_all_settings();
            $service = $settings['newsletter_sending_service'] ?? 'mailgun';
            $from_addresses = $settings['newsletter_from_addresses'] ?? array();
            $from_name = get_bloginfo('name');
            
            // Get the first from address - handle both string and array formats
            $from_email = '';
            if (!empty($from_addresses)) {
                if (is_array($from_addresses)) {
                    $first_address = reset($from_addresses);
                    $from_email = is_array($first_address) ? ($first_address['email'] ?? '') : $first_address;
                } else {
                    $from_email = $from_addresses;
                }
            }
            
            // Fallback to admin email
            if (empty($from_email)) {
                $from_email = get_option('admin_email');
            }
            
            // Check if service is configured
            if (empty($service)) {
                wp_send_json_error(__('No sending service configured. Please select a sending service and save settings.', 'azure-plugin'));
                return;
            }
            
            // Parse from address if it contains name (format: "email|name")
            if (is_string($from_email) && strpos($from_email, '|') !== false) {
                $parts = explode('|', $from_email);
                $from_email = $parts[0];
                $from_name = $parts[1] ?? $from_name;
            }
            
            if (empty($from_email) || !is_email($from_email)) {
                wp_send_json_error(__('No valid "From" email address configured. Please add a From Address in settings.', 'azure-plugin'));
                return;
            }
            
            // Create test email HTML
            $site_name = get_bloginfo('name');
            $site_url = home_url();
            $current_time = current_time('F j, Y g:i a');
            
            $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="padding: 40px 40px 20px; text-align: center; background: linear-gradient(135deg, #2271b1 0%, #135e96 100%); border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">Test Email</h1>
                            <p style="margin: 10px 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">Email Configuration Test</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="margin: 0 0 20px; color: #1d2327; font-size: 20px;">Success!</h2>
                            <p style="margin: 0 0 20px; color: #50575e; font-size: 16px; line-height: 1.6;">
                                Your newsletter email configuration is working correctly. 
                                This test email was sent using <strong>' . esc_html(ucfirst($service)) . '</strong>.
                            </p>
                            <table width="100%" style="background: #f8f9fa; border-radius: 6px; margin: 20px 0;">
                                <tr><td style="padding: 15px;">
                                    <p style="margin: 5px 0;"><strong>From:</strong> ' . esc_html($from_name) . ' &lt;' . esc_html($from_email) . '&gt;</p>
                                    <p style="margin: 5px 0;"><strong>To:</strong> ' . esc_html($email) . '</p>
                                    <p style="margin: 5px 0;"><strong>Service:</strong> ' . esc_html(ucfirst($service)) . '</p>
                                    <p style="margin: 5px 0;"><strong>Sent:</strong> ' . esc_html($current_time) . '</p>
                                </td></tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 40px; background: #f8f9fa; border-radius: 0 0 8px 8px; text-align: center;">
                            <p style="margin: 0; color: #646970; font-size: 13px;">
                                Sent from <a href="' . esc_url($site_url) . '" style="color: #2271b1;">' . esc_html($site_name) . '</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
            
            // Get reply-to address if configured
            $reply_to = $settings['newsletter_reply_to'] ?? '';
            
            $sender = new Azure_Newsletter_Sender($service);
            $result = $sender->send(array(
                'to' => $email,
                'from' => $from_email,
                'from_name' => $from_name,
                'reply_to' => $reply_to,
                'subject' => sprintf('[%s] Test Email - Configuration Verified', $site_name),
                'html' => $html,
                'text' => "Test Email from {$site_name}\n\nYour email configuration is working correctly!\n\nService: {$service}\nFrom: {$from_name} <{$from_email}>\nTo: {$email}\nSent at: {$current_time}"
            ));
            
            if ($result['success']) {
                wp_send_json_success(sprintf(
                    __('Test email sent successfully to %s', 'azure-plugin'),
                    $email
                ));
            } else {
                wp_send_json_error(sprintf(
                    __('Failed to send: %s', 'azure-plugin'),
                    $result['error'] ?? 'Unknown error'
                ));
            }
            
        } catch (Throwable $e) {
            restore_error_handler();
            wp_send_json_error(sprintf(
                __('Error: %s (Line %d in %s)', 'azure-plugin'),
                $e->getMessage(),
                $e->getLine(),
                basename($e->getFile())
            ));
        }
        
        restore_error_handler();
    }
    
    /**
     * Get recipients count for a list
     */
    public function get_recipients_count() {
        check_ajax_referer('azure_newsletter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $list_id = sanitize_text_field($_POST['list_id'] ?? 'all');
        
        if ($list_id === 'all') {
            $count = count_users()['total_users'];
        } else {
            global $wpdb;
            $lists_table = $wpdb->prefix . 'azure_newsletter_lists';
            $members_table = $wpdb->prefix . 'azure_newsletter_list_members';
            
            $list = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$lists_table} WHERE id = %d",
                intval($list_id)
            ));
            
            if ($list && $list->type === 'role') {
                $criteria = json_decode($list->criteria, true);
                $count = 0;
                foreach ($criteria['roles'] ?? array() as $role) {
                    $count += count(get_users(array('role' => $role)));
                }
            } elseif ($list && $list->type === 'custom') {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$members_table} WHERE list_id = %d AND unsubscribed_at IS NULL",
                    intval($list_id)
                ));
            } else {
                $count = 0;
            }
        }
        
        wp_send_json_success(array('count' => intval($count)));
    }
    
    /**
     * Pause newsletter sending
     */
    public function pause_newsletter() {
        check_ajax_referer('azure_newsletter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $newsletter_id = intval($_POST['newsletter_id'] ?? 0);
        
        if (!$newsletter_id) {
            wp_send_json_error('Invalid newsletter ID');
        }
        
        // Ensure queue class is loaded
        if (!class_exists('Azure_Newsletter_Queue')) {
            require_once AZURE_PLUGIN_PATH . 'includes/class-newsletter-queue.php';
        }
        
        $queue = new Azure_Newsletter_Queue();
        $queue->pause_newsletter($newsletter_id);
        
        wp_send_json_success('Newsletter paused');
    }
    
    /**
     * Resume newsletter sending
     */
    public function resume_newsletter() {
        check_ajax_referer('azure_newsletter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $newsletter_id = intval($_POST['newsletter_id'] ?? 0);
        
        if (!$newsletter_id) {
            wp_send_json_error('Invalid newsletter ID');
        }
        
        // Ensure queue class is loaded
        if (!class_exists('Azure_Newsletter_Queue')) {
            require_once AZURE_PLUGIN_PATH . 'includes/class-newsletter-queue.php';
        }
        
        $queue = new Azure_Newsletter_Queue();
        $queue->resume_newsletter($newsletter_id);
        
        wp_send_json_success('Newsletter resumed');
    }
    
    /**
     * Cancel newsletter sending
     */
    public function cancel_newsletter() {
        check_ajax_referer('azure_newsletter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $newsletter_id = intval($_POST['newsletter_id'] ?? 0);
        
        if (!$newsletter_id) {
            wp_send_json_error('Invalid newsletter ID');
        }
        
        // Ensure queue class is loaded
        if (!class_exists('Azure_Newsletter_Queue')) {
            require_once AZURE_PLUGIN_PATH . 'includes/class-newsletter-queue.php';
        }
        
        $queue = new Azure_Newsletter_Queue();
        $result = $queue->cancel_newsletter($newsletter_id);
        
        wp_send_json_success(array(
            'message' => 'Newsletter cancelled',
            'deleted' => $result['deleted']
        ));
    }
    
    /**
     * Create WordPress page for newsletter
     */
    private function create_newsletter_page($newsletter_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'azure_newsletters';
        
        $category = sanitize_text_field($_POST['page_category'] ?? 'newsletter');
        
        // Create or get the category/tag
        $term = get_term_by('slug', $category, 'category');
        if (!$term) {
            $term = get_term_by('slug', $category, 'post_tag');
        }
        if (!$term) {
            // Create as category
            $term_result = wp_insert_term($category, 'category');
            $term_id = is_array($term_result) ? $term_result['term_id'] : 0;
        } else {
            $term_id = $term->term_id;
        }
        
        // Create the page
        $page_data = array(
            'post_title' => $data['name'],
            'post_content' => $data['content_html'],
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id()
        );
        
        // Check if page already exists
        $existing_page_id = $wpdb->get_var($wpdb->prepare(
            "SELECT wp_page_id FROM {$table} WHERE id = %d",
            $newsletter_id
        ));
        
        if ($existing_page_id) {
            $page_data['ID'] = $existing_page_id;
            $page_id = wp_update_post($page_data);
        } else {
            $page_id = wp_insert_post($page_data);
        }
        
        if ($page_id && !is_wp_error($page_id)) {
            // Update newsletter with page ID
            $wpdb->update(
                $table,
                array(
                    'wp_page_id' => $page_id,
                    'page_category' => $category
                ),
                array('id' => $newsletter_id)
            );
            
            // Add category
            if ($term_id) {
                wp_set_post_categories($page_id, array($term_id), true);
            }
            
            Azure_Logger::info("Newsletter #{$newsletter_id} page created: {$page_id}");
        }
        
        return $page_id;
    }
    
    /**
     * Create/Update newsletter database tables
     */
    public function create_tables() {
        check_ajax_referer('azure_newsletter_create_tables', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'azure-plugin'));
        }
        
        try {
            global $wpdb;
            $charset_collate = $wpdb->get_charset_collate();
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            // Newsletters table
            $table_newsletters = $wpdb->prefix . 'azure_newsletters';
            $sql = "CREATE TABLE IF NOT EXISTS {$table_newsletters} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                subject varchar(255) NOT NULL,
                from_email varchar(255) NOT NULL,
                from_name varchar(255) NOT NULL,
                content_html longtext,
                content_json longtext,
                recipient_lists text,
                status varchar(20) DEFAULT 'draft',
                scheduled_at datetime DEFAULT NULL,
                sent_at datetime DEFAULT NULL,
                created_by bigint(20) UNSIGNED NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                archive_token varchar(64) DEFAULT NULL,
                wp_page_id bigint(20) UNSIGNED DEFAULT NULL,
                page_category varchar(100) DEFAULT 'newsletter',
                PRIMARY KEY (id),
                KEY status (status),
                KEY scheduled_at (scheduled_at),
                KEY archive_token (archive_token)
            ) {$charset_collate};";
            dbDelta($sql);
            
            // Queue table
            $table_queue = $wpdb->prefix . 'azure_newsletter_queue';
            $sql = "CREATE TABLE IF NOT EXISTS {$table_queue} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                newsletter_id bigint(20) UNSIGNED NOT NULL,
                email varchar(255) NOT NULL,
                user_id bigint(20) UNSIGNED DEFAULT NULL,
                status varchar(20) DEFAULT 'pending',
                scheduled_at datetime NOT NULL,
                sent_at datetime DEFAULT NULL,
                error_message text,
                attempts int(11) DEFAULT 0,
                PRIMARY KEY (id),
                KEY newsletter_id (newsletter_id),
                KEY status (status),
                KEY scheduled_at (scheduled_at),
                KEY email (email)
            ) {$charset_collate};";
            dbDelta($sql);
            
            // Stats table
            $table_stats = $wpdb->prefix . 'azure_newsletter_stats';
            $sql = "CREATE TABLE IF NOT EXISTS {$table_stats} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                newsletter_id bigint(20) UNSIGNED NOT NULL,
                email varchar(255) NOT NULL,
                event_type varchar(20) NOT NULL,
                event_data text,
                ip_address varchar(45) DEFAULT NULL,
                user_agent text,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY newsletter_id (newsletter_id),
                KEY event_type (event_type),
                KEY email (email)
            ) {$charset_collate};";
            dbDelta($sql);
            
            // Lists table
            $table_lists = $wpdb->prefix . 'azure_newsletter_lists';
            $sql = "CREATE TABLE IF NOT EXISTS {$table_lists} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                description text,
                type varchar(20) DEFAULT 'custom',
                criteria longtext,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY type (type)
            ) {$charset_collate};";
            dbDelta($sql);
            
            // List members table
            $table_members = $wpdb->prefix . 'azure_newsletter_list_members';
            $sql = "CREATE TABLE IF NOT EXISTS {$table_members} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                list_id bigint(20) UNSIGNED NOT NULL,
                email varchar(255) NOT NULL,
                user_id bigint(20) UNSIGNED DEFAULT NULL,
                subscribed_at datetime NOT NULL,
                unsubscribed_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY list_email (list_id, email),
                KEY list_id (list_id),
                KEY email (email)
            ) {$charset_collate};";
            dbDelta($sql);
            
            // Bounces table
            $table_bounces = $wpdb->prefix . 'azure_newsletter_bounces';
            $sql = "CREATE TABLE IF NOT EXISTS {$table_bounces} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                email varchar(255) NOT NULL,
                newsletter_id bigint(20) UNSIGNED DEFAULT NULL,
                bounce_type varchar(20) DEFAULT 'hard',
                bounce_reason text,
                bounced_at datetime NOT NULL,
                blocked tinyint(1) DEFAULT 0,
                PRIMARY KEY (id),
                KEY email (email),
                KEY newsletter_id (newsletter_id),
                KEY blocked (blocked)
            ) {$charset_collate};";
            dbDelta($sql);
            
            // Templates table
            $table_templates = $wpdb->prefix . 'azure_newsletter_templates';
            $sql = "CREATE TABLE IF NOT EXISTS {$table_templates} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                description text,
                category varchar(100) DEFAULT 'general',
                content_html longtext,
                content_json longtext,
                thumbnail_url varchar(500) DEFAULT NULL,
                is_default tinyint(1) DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY category (category),
                KEY is_default (is_default)
            ) {$charset_collate};";
            dbDelta($sql);
            
            // Sending config table
            $table_sending = $wpdb->prefix . 'azure_newsletter_sending_config';
            $sql = "CREATE TABLE IF NOT EXISTS {$table_sending} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                from_name varchar(255) NOT NULL,
                from_email varchar(255) NOT NULL,
                is_default tinyint(1) DEFAULT 0,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY is_default (is_default)
            ) {$charset_collate};";
            dbDelta($sql);
            
            // Log the result
            if (class_exists('Azure_Logger')) {
                Azure_Logger::info('Newsletter tables created/updated via AJAX', array('module' => 'Newsletter'));
            }
            
            wp_send_json_success(__('Newsletter database tables created/updated successfully!', 'azure-plugin'));
            
        } catch (Exception $e) {
            if (class_exists('Azure_Logger')) {
                Azure_Logger::error('Failed to create newsletter tables: ' . $e->getMessage(), array(
                    'module' => 'Newsletter',
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ));
            }
            wp_send_json_error(__('Failed to create tables: ', 'azure-plugin') . $e->getMessage());
        }
    }
    
    /**
     * Reset newsletter data (dangerous!)
     */
    public function reset_data() {
        check_ajax_referer('azure_newsletter_reset_data', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'azure-plugin'));
        }
        
        // Verify confirmation
        $confirm = sanitize_text_field($_POST['confirm'] ?? '');
        if ($confirm !== 'RESET') {
            wp_send_json_error(__('Please type RESET to confirm this action.', 'azure-plugin'));
        }
        
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'azure_newsletter_queue',
            $wpdb->prefix . 'azure_newsletter_stats',
            $wpdb->prefix . 'azure_newsletter_bounces',
        );
        
        foreach ($tables as $table) {
            $wpdb->query("TRUNCATE TABLE {$table}");
        }
        
        if (class_exists('Azure_Logger')) {
            Azure_Logger::warning('Newsletter data reset by user', array(
                'module' => 'Newsletter',
                'user_id' => get_current_user_id()
            ));
        }
        
        wp_send_json_success(__('Newsletter data has been reset.', 'azure-plugin'));
    }
    
    /**
     * Clean HTML for preview display
     * Removes CSS text that may have leaked into body content
     */
    private function clean_html_for_preview($html) {
        if (empty($html)) {
            return '';
        }
        
        // If it's a full HTML document, extract and clean the body
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $matches)) {
            $body_content = $matches[1];
            $body_content = $this->strip_css_text($body_content);
            
            // Rebuild the HTML with cleaned body
            $html = preg_replace('/(<body[^>]*>).*(<\/body>)/is', '$1' . $body_content . '$2', $html);
        } else {
            // Not a full document, just clean the content
            $html = $this->strip_css_text($html);
        }
        
        return $html;
    }
    
    /**
     * Strip CSS text that appears before HTML content
     */
    private function strip_css_text($content) {
        // Trim whitespace first
        $content = ltrim($content);
        
        // If content starts with a tag, it's clean
        if (strpos($content, '<') === 0) {
            return $content;
        }
        
        // Find the position of the first HTML tag
        $first_tag_pos = strpos($content, '<');
        
        if ($first_tag_pos === false) {
            // No HTML tags found, return as-is
            return $content;
        }
        
        // Get the text before the first tag
        $before_tag = substr($content, 0, $first_tag_pos);
        
        // Check if this looks like CSS (contains { and })
        if (strpos($before_tag, '{') !== false && strpos($before_tag, '}') !== false) {
            // It's CSS text, strip it all
            return substr($content, $first_tag_pos);
        }
        
        // Also check for CSS comment at start
        if (preg_match('/^\s*\/\*/', $before_tag)) {
            return substr($content, $first_tag_pos);
        }
        
        return $content;
    }
}

// Initialize
new Azure_Newsletter_Ajax();


