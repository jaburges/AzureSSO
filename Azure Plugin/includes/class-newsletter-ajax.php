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
        
        $newsletter_id = intval($_POST['newsletter_id'] ?? 0);
        $send_option = sanitize_key($_POST['send_option'] ?? 'draft');
        
        // Parse from field
        $from_parts = explode('|', sanitize_text_field($_POST['newsletter_from'] ?? ''));
        $from_email = $from_parts[0] ?? '';
        $from_name = $from_parts[1] ?? '';
        
        $data = array(
            'name' => sanitize_text_field($_POST['newsletter_name'] ?? ''),
            'subject' => sanitize_text_field($_POST['newsletter_subject'] ?? ''),
            'from_email' => $from_email,
            'from_name' => $from_name,
            'content_html' => wp_kses_post($_POST['newsletter_content_html'] ?? ''),
            'content_json' => sanitize_text_field($_POST['newsletter_content_json'] ?? ''),
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
            $list_id = sanitize_text_field($_POST['newsletter_list'] ?? 'all');
            
            // Ensure queue class is loaded
            if (!class_exists('Azure_Newsletter_Queue')) {
                require_once AZURE_PLUGIN_PATH . 'includes/class-newsletter-queue.php';
            }
            
            $queue = new Azure_Newsletter_Queue();
            $queue_result = $queue->queue_newsletter($newsletter_id, $list_id, $data['scheduled_at']);
            
            wp_send_json_success(array(
                'newsletter_id' => $newsletter_id,
                'status' => $data['status'],
                'queued' => $queue_result['queued'] ?? 0
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
     * Check spam score
     */
    public function check_spam_score() {
        check_ajax_referer('azure_newsletter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $html = wp_kses_post($_POST['html'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        
        // Simple spam checks
        $issues = array();
        $score = 0;
        
        // Check subject line
        $spam_words = array('free', 'win', 'winner', 'cash', 'prize', 'urgent', 'act now', 'limited time', 'click here', 'buy now');
        foreach ($spam_words as $word) {
            if (stripos($subject, $word) !== false) {
                $issues[] = "Subject contains spam trigger word: '{$word}'";
                $score += 1;
            }
        }
        
        // Check for ALL CAPS in subject
        if (strtoupper($subject) === $subject && strlen($subject) > 5) {
            $issues[] = 'Subject is all uppercase';
            $score += 2;
        }
        
        // Check for excessive exclamation marks
        if (substr_count($subject, '!') > 1) {
            $issues[] = 'Subject has multiple exclamation marks';
            $score += 1;
        }
        
        // Check HTML content
        if (empty($html)) {
            $issues[] = 'No HTML content';
            $score += 3;
        } else {
            // Check image to text ratio
            preg_match_all('/<img/i', $html, $images);
            $text_length = strlen(strip_tags($html));
            
            if (count($images[0]) > 0 && $text_length < 100) {
                $issues[] = 'Low text-to-image ratio';
                $score += 2;
            }
            
            // Check for unsubscribe link
            if (stripos($html, 'unsubscribe') === false) {
                $issues[] = 'Missing unsubscribe link';
                $score += 2;
            }
            
            // Check for physical address (CAN-SPAM)
            // This is a simplified check
            if (stripos($html, 'address') === false && stripos($html, 'contact') === false) {
                $issues[] = 'Consider adding physical address (CAN-SPAM compliance)';
                $score += 1;
            }
        }
        
        // Determine message
        $message = 'Excellent! Your email looks good.';
        if ($score >= 3 && $score < 5) {
            $message = 'Good, but there are some areas for improvement.';
        } elseif ($score >= 5) {
            $message = 'Warning: Your email may be flagged as spam.';
        }
        
        wp_send_json_success(array(
            'score' => min($score, 10),
            'message' => $message,
            'issues' => $issues
        ));
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
            
            $sender = new Azure_Newsletter_Sender($service);
            $result = $sender->send(array(
                'to' => $email,
                'from_email' => $from_email,
                'from_name' => $from_name,
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
}

// Initialize
new Azure_Newsletter_Ajax();


