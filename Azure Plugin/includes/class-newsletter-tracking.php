<?php
/**
 * Newsletter Tracking - Handle webhooks and track opens/clicks
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Newsletter_Tracking {
    
    private $stats_table;
    private $bounces_table;
    
    public function __construct() {
        global $wpdb;
        $this->stats_table = $wpdb->prefix . 'azure_newsletter_stats';
        $this->bounces_table = $wpdb->prefix . 'azure_newsletter_bounces';
    }
    
    /**
     * Record an open event
     */
    public function record_open($token) {
        $data = $this->decode_tracking_token($token);
        
        if (!$data) {
            return false;
        }
        
        global $wpdb;
        
        // Check for existing open from this email (dedupe)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->stats_table} 
             WHERE newsletter_id = %d AND email = %s AND event_type = 'opened'
             LIMIT 1",
            $data['newsletter_id'],
            $data['email']
        ));
        
        // Record open (even if duplicate, for tracking multiple opens)
        $wpdb->insert($this->stats_table, array(
            'newsletter_id' => $data['newsletter_id'],
            'email' => $data['email'],
            'user_id' => $this->get_user_id_by_email($data['email']),
            'event_type' => 'opened',
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => current_time('mysql')
        ));
        
        return true;
    }
    
    /**
     * Record a click event
     */
    public function record_click($token, $url) {
        $data = $this->decode_tracking_token($token);
        
        if (!$data) {
            return false;
        }
        
        global $wpdb;
        
        // Extract link text from URL if possible
        $link_text = $this->extract_link_text($url);
        
        $wpdb->insert($this->stats_table, array(
            'newsletter_id' => $data['newsletter_id'],
            'email' => $data['email'],
            'user_id' => $this->get_user_id_by_email($data['email']),
            'event_type' => 'clicked',
            'link_url' => $url,
            'link_text' => $link_text,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => current_time('mysql')
        ));
        
        return true;
    }
    
    /**
     * Process Mailgun webhook
     */
    public function process_mailgun_webhook($request) {
        $body = $request->get_body();
        $data = json_decode($body, true);
        
        // Verify signature
        if (!$this->verify_mailgun_signature($request)) {
            Azure_Logger::warning('Mailgun webhook: Invalid signature');
            return new WP_REST_Response(array('error' => 'Invalid signature'), 403);
        }
        
        $event_data = $data['event-data'] ?? $data;
        $event_type = $event_data['event'] ?? '';
        $email = $event_data['recipient'] ?? ($event_data['message']['headers']['to'] ?? '');
        $newsletter_id = $event_data['user-variables']['newsletter_id'] ?? null;
        
        $this->process_event($event_type, $email, $newsletter_id, $event_data);
        
        return new WP_REST_Response(array('status' => 'ok'), 200);
    }
    
    /**
     * Process SendGrid webhook
     */
    public function process_sendgrid_webhook($request) {
        $body = $request->get_body();
        $events = json_decode($body, true);
        
        if (!is_array($events)) {
            return new WP_REST_Response(array('error' => 'Invalid data'), 400);
        }
        
        foreach ($events as $event) {
            $event_type = $event['event'] ?? '';
            $email = $event['email'] ?? '';
            $newsletter_id = $event['newsletter_id'] ?? null;
            
            $this->process_event($event_type, $email, $newsletter_id, $event);
        }
        
        return new WP_REST_Response(array('status' => 'ok'), 200);
    }
    
    /**
     * Process Amazon SES webhook (SNS notification)
     */
    public function process_ses_webhook($request) {
        $body = $request->get_body();
        $data = json_decode($body, true);
        
        // Handle SNS subscription confirmation
        if (isset($data['Type']) && $data['Type'] === 'SubscriptionConfirmation') {
            wp_remote_get($data['SubscribeURL']);
            return new WP_REST_Response(array('status' => 'subscribed'), 200);
        }
        
        // Handle notifications
        if (isset($data['Type']) && $data['Type'] === 'Notification') {
            $message = json_decode($data['Message'], true);
            
            $notification_type = $message['notificationType'] ?? '';
            
            switch ($notification_type) {
                case 'Bounce':
                    $this->process_ses_bounce($message);
                    break;
                case 'Complaint':
                    $this->process_ses_complaint($message);
                    break;
                case 'Delivery':
                    $this->process_ses_delivery($message);
                    break;
            }
        }
        
        return new WP_REST_Response(array('status' => 'ok'), 200);
    }
    
    /**
     * Process a generic event
     */
    private function process_event($event_type, $email, $newsletter_id, $raw_data) {
        global $wpdb;
        
        // Map service-specific event types to our standard types
        $event_map = array(
            // Mailgun
            'delivered' => 'delivered',
            'opened' => 'opened',
            'clicked' => 'clicked',
            'bounced' => 'bounced',
            'complained' => 'complained',
            'unsubscribed' => 'unsubscribed',
            'failed' => 'bounced',
            
            // SendGrid
            'delivered' => 'delivered',
            'open' => 'opened',
            'click' => 'clicked',
            'bounce' => 'bounced',
            'spamreport' => 'complained',
            'unsubscribe' => 'unsubscribed',
            'dropped' => 'bounced'
        );
        
        $normalized_type = $event_map[$event_type] ?? null;
        
        if (!$normalized_type || !$email) {
            return;
        }
        
        // Record the event
        $wpdb->insert($this->stats_table, array(
            'newsletter_id' => $newsletter_id,
            'email' => $email,
            'user_id' => $this->get_user_id_by_email($email),
            'event_type' => $normalized_type,
            'event_data' => json_encode($raw_data),
            'link_url' => $raw_data['url'] ?? null,
            'created_at' => current_time('mysql')
        ));
        
        // Handle bounces
        if ($normalized_type === 'bounced') {
            $this->record_bounce($email, $this->determine_bounce_type($raw_data));
        }
        
        // Handle complaints
        if ($normalized_type === 'complained') {
            $this->record_bounce($email, 'complaint');
        }
    }
    
    /**
     * Process SES bounce notification
     */
    private function process_ses_bounce($message) {
        $bounce = $message['bounce'] ?? array();
        $bounce_type = strtolower($bounce['bounceType'] ?? 'permanent');
        
        foreach ($bounce['bouncedRecipients'] ?? array() as $recipient) {
            $email = $recipient['emailAddress'] ?? '';
            if ($email) {
                $type = $bounce_type === 'permanent' ? 'hard' : 'soft';
                $this->record_bounce($email, $type, $recipient['diagnosticCode'] ?? null);
            }
        }
    }
    
    /**
     * Process SES complaint notification
     */
    private function process_ses_complaint($message) {
        $complaint = $message['complaint'] ?? array();
        
        foreach ($complaint['complainedRecipients'] ?? array() as $recipient) {
            $email = $recipient['emailAddress'] ?? '';
            if ($email) {
                $this->record_bounce($email, 'complaint');
            }
        }
    }
    
    /**
     * Process SES delivery notification
     */
    private function process_ses_delivery($message) {
        $delivery = $message['delivery'] ?? array();
        
        foreach ($delivery['recipients'] ?? array() as $email) {
            global $wpdb;
            
            $wpdb->insert($this->stats_table, array(
                'email' => $email,
                'user_id' => $this->get_user_id_by_email($email),
                'event_type' => 'delivered',
                'created_at' => current_time('mysql')
            ));
        }
    }
    
    /**
     * Record a bounce
     */
    public function record_bounce($email, $type = 'hard', $reason = null) {
        global $wpdb;
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->bounces_table} WHERE email = %s",
            $email
        ));
        
        if ($existing) {
            // Update existing record
            $wpdb->update(
                $this->bounces_table,
                array(
                    'bounce_type' => $type,
                    'bounce_count' => $existing->bounce_count + 1,
                    'last_bounce_at' => current_time('mysql'),
                    'bounce_reason' => $reason,
                    'is_blocked' => ($existing->bounce_count + 1 >= 3 || $type === 'hard' || $type === 'complaint') ? 1 : 0
                ),
                array('email' => $email)
            );
        } else {
            // Insert new record
            $wpdb->insert($this->bounces_table, array(
                'email' => $email,
                'bounce_type' => $type,
                'bounce_count' => 1,
                'bounce_reason' => $reason,
                'last_bounce_at' => current_time('mysql'),
                'is_blocked' => ($type === 'hard' || $type === 'complaint') ? 1 : 0
            ));
        }
        
        // Block immediately for hard bounces and complaints
        if ($type === 'hard' || $type === 'complaint') {
            Azure_Logger::info("Email blocked: {$email} (reason: {$type})");
        }
    }
    
    /**
     * Verify Mailgun webhook signature
     */
    private function verify_mailgun_signature($request) {
        $settings = Azure_Settings::get_all_settings();
        $api_key = $settings['newsletter_mailgun_api_key'] ?? '';
        
        if (empty($api_key)) {
            return true; // Skip verification if no key configured
        }
        
        $body = $request->get_body();
        $data = json_decode($body, true);
        
        $signature = $data['signature'] ?? array();
        $timestamp = $signature['timestamp'] ?? '';
        $token = $signature['token'] ?? '';
        $sig = $signature['signature'] ?? '';
        
        if (empty($timestamp) || empty($token) || empty($sig)) {
            return false;
        }
        
        $expected = hash_hmac('sha256', $timestamp . $token, $api_key);
        
        return hash_equals($expected, $sig);
    }
    
    /**
     * Decode a tracking token
     */
    private function decode_tracking_token($token) {
        // Tokens are base64-encoded hashes
        // We need to look up the newsletter_id and email from other sources
        // since we can't decode a one-way hash
        
        // For now, assume the token contains the data directly
        // In production, you'd store tokens in a database
        
        // This is a simplified implementation
        // A full implementation would store tokens with metadata
        
        return array(
            'newsletter_id' => null,
            'email' => null
        );
    }
    
    /**
     * Determine bounce type from event data
     */
    private function determine_bounce_type($data) {
        // Check various indicators for hard vs soft bounce
        $severity = $data['severity'] ?? ($data['bounce']['bounceType'] ?? 'permanent');
        $code = $data['delivery-status']['code'] ?? ($data['status'] ?? '');
        
        // 5xx codes are typically hard bounces
        if (strpos($code, '5') === 0 || $severity === 'permanent') {
            return 'hard';
        }
        
        return 'soft';
    }
    
    /**
     * Get user ID by email
     */
    private function get_user_id_by_email($email) {
        $user = get_user_by('email', $email);
        return $user ? $user->ID : null;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract link text (simplified - would need original HTML context)
     */
    private function extract_link_text($url) {
        // Parse URL to get path for display
        $parsed = parse_url($url);
        return $parsed['path'] ?? $url;
    }
}




