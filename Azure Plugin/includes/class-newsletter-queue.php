<?php
/**
 * Newsletter Queue - Batch email sending with rate limiting
 * 
 * Handles queuing emails, processing batches via WP-Cron,
 * and respecting rate limits from sending services.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Newsletter_Queue {
    
    private $table;
    private $newsletters_table;
    private $stats_table;
    
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'azure_newsletter_queue';
        $this->newsletters_table = $wpdb->prefix . 'azure_newsletters';
        $this->stats_table = $wpdb->prefix . 'azure_newsletter_stats';
    }
    
    /**
     * Queue a newsletter for sending to a list
     * 
     * @param int $newsletter_id Newsletter ID
     * @param string $list_id List ID or 'all' for all WordPress users
     * @param string $scheduled_at Scheduled time (MySQL datetime)
     * @return array Result with count of queued emails
     */
    public function queue_newsletter($newsletter_id, $list_id = 'all', $scheduled_at = null) {
        global $wpdb;
        
        if (!$scheduled_at) {
            $scheduled_at = current_time('mysql');
        }
        
        // Get recipients based on list
        $recipients = $this->get_recipients($list_id);
        
        if (empty($recipients)) {
            return array(
                'success' => false,
                'error' => 'No recipients found for the selected list'
            );
        }
        
        // Filter out blocked/bounced emails
        $recipients = $this->filter_blocked_recipients($recipients);
        
        // Queue each recipient
        $queued = 0;
        $skipped = 0;
        
        foreach ($recipients as $recipient) {
            $result = $wpdb->insert($this->table, array(
                'newsletter_id' => $newsletter_id,
                'user_id' => $recipient['user_id'],
                'email' => $recipient['email'],
                'status' => 'pending',
                'scheduled_at' => $scheduled_at
            ), array('%d', '%d', '%s', '%s', '%s'));
            
            if ($result) {
                $queued++;
            } else {
                // Duplicate or error - skip
                $skipped++;
            }
        }
        
        // Update newsletter status
        $wpdb->update(
            $this->newsletters_table,
            array('status' => 'scheduled', 'scheduled_at' => $scheduled_at),
            array('id' => $newsletter_id)
        );
        
        Azure_Logger::info("Newsletter #{$newsletter_id} queued: {$queued} emails, {$skipped} skipped");
        
        return array(
            'success' => true,
            'queued' => $queued,
            'skipped' => $skipped
        );
    }
    
    /**
     * Get recipients for a list
     */
    private function get_recipients($list_id) {
        global $wpdb;
        
        $recipients = array();
        
        if ($list_id === 'all') {
            // All WordPress users with email addresses
            $users = get_users(array(
                'fields' => array('ID', 'user_email', 'display_name'),
                'number' => -1
            ));
            
            foreach ($users as $user) {
                if (!empty($user->user_email)) {
                    $recipients[] = array(
                        'user_id' => $user->ID,
                        'email' => $user->user_email,
                        'name' => $user->display_name
                    );
                }
            }
        } else {
            // Custom list
            $lists_table = $wpdb->prefix . 'azure_newsletter_lists';
            $members_table = $wpdb->prefix . 'azure_newsletter_list_members';
            
            $list = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$lists_table} WHERE id = %d",
                $list_id
            ));
            
            if ($list) {
                switch ($list->type) {
                    case 'role':
                        $criteria = json_decode($list->criteria, true);
                        if (!empty($criteria['roles'])) {
                            foreach ($criteria['roles'] as $role) {
                                $users = get_users(array(
                                    'role' => $role,
                                    'fields' => array('ID', 'user_email', 'display_name')
                                ));
                                
                                foreach ($users as $user) {
                                    if (!empty($user->user_email)) {
                                        $recipients[] = array(
                                            'user_id' => $user->ID,
                                            'email' => $user->user_email,
                                            'name' => $user->display_name
                                        );
                                    }
                                }
                            }
                        }
                        break;
                        
                    case 'custom':
                        $members = $wpdb->get_results($wpdb->prepare(
                            "SELECT user_id, email, first_name, last_name 
                             FROM {$members_table} 
                             WHERE list_id = %d AND unsubscribed_at IS NULL",
                            $list_id
                        ));
                        
                        foreach ($members as $member) {
                            $recipients[] = array(
                                'user_id' => $member->user_id,
                                'email' => $member->email,
                                'name' => trim($member->first_name . ' ' . $member->last_name)
                            );
                        }
                        break;
                }
            }
        }
        
        // Deduplicate by email
        $seen = array();
        $unique = array();
        foreach ($recipients as $r) {
            $email_lower = strtolower($r['email']);
            if (!isset($seen[$email_lower])) {
                $seen[$email_lower] = true;
                $unique[] = $r;
            }
        }
        
        return $unique;
    }
    
    /**
     * Filter out blocked/bounced recipients
     */
    private function filter_blocked_recipients($recipients) {
        global $wpdb;
        
        $bounces_table = $wpdb->prefix . 'azure_newsletter_bounces';
        
        // Get blocked emails
        $blocked = $wpdb->get_col("SELECT email FROM {$bounces_table} WHERE is_blocked = 1");
        $blocked_lower = array_map('strtolower', $blocked);
        
        // Filter out blocked
        return array_filter($recipients, function($r) use ($blocked_lower) {
            return !in_array(strtolower($r['email']), $blocked_lower);
        });
    }
    
    /**
     * Process a batch of queued emails
     * 
     * @return array Result with sent/failed counts
     */
    public function process_batch() {
        global $wpdb;
        
        $settings = Azure_Settings::get_all_settings();
        $batch_size = $settings['newsletter_batch_size'] ?? 100;
        $rate_limit = $settings['newsletter_rate_limit_per_hour'] ?? 1000;
        
        // Check rate limit
        $sent_this_hour = $this->get_sent_this_hour();
        if ($sent_this_hour >= $rate_limit) {
            Azure_Logger::debug_module('Newsletter', 'Rate limit reached, skipping batch');
            return array('sent' => 0, 'failed' => 0, 'total' => 0, 'rate_limited' => true);
        }
        
        // Adjust batch size based on remaining rate limit
        $remaining = $rate_limit - $sent_this_hour;
        $batch_size = min($batch_size, $remaining);
        
        // Get pending emails that are due
        $pending = $wpdb->get_results($wpdb->prepare(
            "SELECT q.*, n.subject, n.from_name, n.from_email, n.content_html
             FROM {$this->table} q
             JOIN {$this->newsletters_table} n ON q.newsletter_id = n.id
             WHERE q.status = 'pending' 
             AND q.scheduled_at <= %s
             AND q.attempts < 3
             ORDER BY q.scheduled_at ASC
             LIMIT %d",
            current_time('mysql'),
            $batch_size
        ));
        
        if (empty($pending)) {
            return array('sent' => 0, 'failed' => 0, 'total' => 0);
        }
        
        $sender = new Azure_Newsletter_Sender();
        $sent = 0;
        $failed = 0;
        
        foreach ($pending as $item) {
            // Get recipient name
            $name = '';
            if ($item->user_id) {
                $user = get_user_by('id', $item->user_id);
                if ($user) {
                    $name = $user->display_name;
                }
            }
            
            // Personalize content
            $html = $this->personalize_content($item->content_html, array(
                'email' => $item->email,
                'first_name' => $this->get_first_name($name, $item->email),
                'user_id' => $item->user_id
            ));
            
            // Send email
            $result = $sender->send(array(
                'to' => $item->email,
                'to_name' => $name,
                'from' => $item->from_email,
                'from_name' => $item->from_name,
                'subject' => $this->personalize_subject($item->subject, array(
                    'first_name' => $this->get_first_name($name, $item->email)
                )),
                'html' => $html,
                'newsletter_id' => $item->newsletter_id
            ));
            
            if ($result['success']) {
                // Mark as sent
                $wpdb->update(
                    $this->table,
                    array(
                        'status' => 'sent',
                        'sent_at' => current_time('mysql')
                    ),
                    array('id' => $item->id)
                );
                
                // Record sent stat
                $this->record_stat($item->newsletter_id, $item->email, $item->user_id, 'sent');
                
                $sent++;
            } else {
                // Increment attempts
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->table} 
                     SET attempts = attempts + 1, error_message = %s
                     WHERE id = %d",
                    $result['error'],
                    $item->id
                ));
                
                // If max attempts reached, mark as failed
                if ($item->attempts + 1 >= 3) {
                    $wpdb->update(
                        $this->table,
                        array('status' => 'failed'),
                        array('id' => $item->id)
                    );
                }
                
                $failed++;
            }
            
            // Small delay between sends to avoid overwhelming the service
            usleep(100000); // 100ms
        }
        
        // Check if newsletter is complete
        $this->check_newsletter_completion();
        
        return array(
            'sent' => $sent,
            'failed' => $failed,
            'total' => count($pending)
        );
    }
    
    /**
     * Get number of emails sent in the current hour
     */
    private function get_sent_this_hour() {
        global $wpdb;
        
        $hour_start = date('Y-m-d H:00:00');
        
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} 
             WHERE status = 'sent' AND sent_at >= %s",
            $hour_start
        ));
    }
    
    /**
     * Personalize email content with merge tags
     */
    private function personalize_content($html, $data) {
        $replacements = array(
            '{{first_name}}' => $data['first_name'] ?? '',
            '{{email}}' => $data['email'] ?? '',
            '{{user_id}}' => $data['user_id'] ?? ''
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }
    
    /**
     * Personalize email subject
     */
    private function personalize_subject($subject, $data) {
        $replacements = array(
            '{{first_name}}' => $data['first_name'] ?? ''
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $subject);
    }
    
    /**
     * Extract first name from display name or email
     */
    private function get_first_name($display_name, $email) {
        if (!empty($display_name)) {
            $parts = explode(' ', $display_name);
            return $parts[0];
        }
        
        // Fall back to email prefix
        $parts = explode('@', $email);
        return ucfirst($parts[0]);
    }
    
    /**
     * Record a statistics event
     */
    private function record_stat($newsletter_id, $email, $user_id, $event_type, $event_data = null) {
        global $wpdb;
        
        $wpdb->insert($this->stats_table, array(
            'newsletter_id' => $newsletter_id,
            'email' => $email,
            'user_id' => $user_id,
            'event_type' => $event_type,
            'event_data' => $event_data,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => current_time('mysql')
        ));
    }
    
    /**
     * Check if a newsletter has completed sending
     */
    private function check_newsletter_completion() {
        global $wpdb;
        
        // Find newsletters that are "sending" but have no more pending emails
        $newsletters = $wpdb->get_col(
            "SELECT DISTINCT newsletter_id 
             FROM {$this->table} 
             WHERE status = 'pending'"
        );
        
        // Get all newsletters marked as "sending" or "scheduled"
        $active = $wpdb->get_col(
            "SELECT id FROM {$this->newsletters_table} 
             WHERE status IN ('sending', 'scheduled')"
        );
        
        foreach ($active as $id) {
            if (!in_array($id, $newsletters)) {
                // No more pending - mark as sent
                $wpdb->update(
                    $this->newsletters_table,
                    array(
                        'status' => 'sent',
                        'sent_at' => current_time('mysql')
                    ),
                    array('id' => $id)
                );
                
                // Get stats for logging
                $stats = $this->get_newsletter_stats($id);
                Azure_Logger::info(sprintf(
                    'Newsletter #%d completed: %d/%d sent successfully',
                    $id,
                    $stats['sent'],
                    $stats['total']
                ));
            }
        }
    }
    
    /**
     * Get stats for a newsletter
     */
    public function get_newsletter_stats($newsletter_id) {
        global $wpdb;
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE newsletter_id = %d",
            $newsletter_id
        ));
        
        $sent = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE newsletter_id = %d AND status = 'sent'",
            $newsletter_id
        ));
        
        $failed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE newsletter_id = %d AND status = 'failed'",
            $newsletter_id
        ));
        
        $pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE newsletter_id = %d AND status = 'pending'",
            $newsletter_id
        ));
        
        return array(
            'total' => (int)$total,
            'sent' => (int)$sent,
            'failed' => (int)$failed,
            'pending' => (int)$pending
        );
    }
    
    /**
     * Pause a newsletter
     */
    public function pause_newsletter($newsletter_id) {
        global $wpdb;
        
        $wpdb->update(
            $this->newsletters_table,
            array('status' => 'paused'),
            array('id' => $newsletter_id)
        );
        
        Azure_Logger::info("Newsletter #{$newsletter_id} paused");
        
        return true;
    }
    
    /**
     * Resume a paused newsletter
     */
    public function resume_newsletter($newsletter_id) {
        global $wpdb;
        
        $wpdb->update(
            $this->newsletters_table,
            array('status' => 'sending'),
            array('id' => $newsletter_id)
        );
        
        Azure_Logger::info("Newsletter #{$newsletter_id} resumed");
        
        return true;
    }
    
    /**
     * Cancel a newsletter and remove pending queue items
     */
    public function cancel_newsletter($newsletter_id) {
        global $wpdb;
        
        // Delete pending queue items
        $deleted = $wpdb->delete(
            $this->table,
            array('newsletter_id' => $newsletter_id, 'status' => 'pending')
        );
        
        // Update newsletter status
        $wpdb->update(
            $this->newsletters_table,
            array('status' => 'draft'),
            array('id' => $newsletter_id)
        );
        
        Azure_Logger::info("Newsletter #{$newsletter_id} cancelled, {$deleted} queue items removed");
        
        return array('deleted' => $deleted);
    }
}




