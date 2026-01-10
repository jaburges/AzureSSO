<?php
/**
 * Newsletter Queue Management Tab
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$queue_table = $wpdb->prefix . 'azure_newsletter_queue';
$newsletters_table = $wpdb->prefix . 'azure_newsletters';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$queue_table}'") === $queue_table;

if (!$table_exists) {
    echo '<div class="notice notice-warning"><p>' . __('Queue table does not exist. Please create database tables in Settings.', 'azure-plugin') . '</p></div>';
    return;
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && wp_verify_nonce($_POST['_wpnonce'], 'newsletter_queue_bulk')) {
    $action = sanitize_text_field($_POST['bulk_action']);
    $selected_ids = isset($_POST['queue_ids']) ? array_map('intval', (array)$_POST['queue_ids']) : array();
    
    if (!empty($selected_ids)) {
        $ids_placeholder = implode(',', array_fill(0, count($selected_ids), '%d'));
        
        switch ($action) {
            case 'delete':
                $wpdb->query($wpdb->prepare("DELETE FROM {$queue_table} WHERE id IN ({$ids_placeholder})", ...$selected_ids));
                echo '<div class="notice notice-success"><p>' . sprintf(__('%d queue items deleted.', 'azure-plugin'), count($selected_ids)) . '</p></div>';
                break;
            case 'retry':
                $wpdb->query($wpdb->prepare("UPDATE {$queue_table} SET status = 'pending', attempts = 0 WHERE id IN ({$ids_placeholder})", ...$selected_ids));
                echo '<div class="notice notice-success"><p>' . sprintf(__('%d queue items marked for retry.', 'azure-plugin'), count($selected_ids)) . '</p></div>';
                break;
        }
    }
}

// Handle clear actions
if (isset($_POST['clear_action']) && wp_verify_nonce($_POST['_wpnonce'], 'newsletter_queue_clear')) {
    $clear_type = sanitize_text_field($_POST['clear_action']);
    
    switch ($clear_type) {
        case 'clear_sent':
            $deleted = $wpdb->query("DELETE FROM {$queue_table} WHERE status = 'sent'");
            echo '<div class="notice notice-success"><p>' . sprintf(__('%d sent items cleared from queue.', 'azure-plugin'), $deleted) . '</p></div>';
            break;
        case 'clear_failed':
            $deleted = $wpdb->query("DELETE FROM {$queue_table} WHERE status = 'failed'");
            echo '<div class="notice notice-success"><p>' . sprintf(__('%d failed items cleared from queue.', 'azure-plugin'), $deleted) . '</p></div>';
            break;
        case 'clear_all':
            $deleted = $wpdb->query("TRUNCATE TABLE {$queue_table}");
            echo '<div class="notice notice-success"><p>' . __('Queue cleared.', 'azure-plugin') . '</p></div>';
            break;
    }
}

// Get current filter
$status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'all';

// Get queue statistics
$stats = array(
    'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$queue_table} WHERE status = 'pending'") ?: 0,
    'sent' => $wpdb->get_var("SELECT COUNT(*) FROM {$queue_table} WHERE status = 'sent'") ?: 0,
    'failed' => $wpdb->get_var("SELECT COUNT(*) FROM {$queue_table} WHERE status = 'failed'") ?: 0,
    'processing' => $wpdb->get_var("SELECT COUNT(*) FROM {$queue_table} WHERE status = 'processing'") ?: 0,
);
$stats['total'] = array_sum($stats);

// Pagination
$per_page = 50;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Build query
$where = "1=1";
if ($status_filter !== 'all') {
    $where = $wpdb->prepare("q.status = %s", $status_filter);
}

$total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$queue_table} q WHERE {$where}");
$total_pages = ceil($total_items / $per_page);

// Get queue items
$queue_items = $wpdb->get_results($wpdb->prepare(
    "SELECT q.*, n.name as newsletter_name, n.subject as newsletter_subject
     FROM {$queue_table} q
     LEFT JOIN {$newsletters_table} n ON q.newsletter_id = n.id
     WHERE {$where}
     ORDER BY q.scheduled_at DESC, q.id DESC
     LIMIT %d OFFSET %d",
    $per_page,
    $offset
));

// Get next scheduled cron
$next_cron = wp_next_scheduled('azure_newsletter_process_queue');
?>

<div class="newsletter-queue-wrap">
    <!-- Queue Statistics -->
    <div class="queue-stats-cards">
        <a href="<?php echo admin_url('admin.php?page=azure-plugin-newsletter&tab=queue&status=all'); ?>" 
           class="stat-card <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
            <span class="stat-icon dashicons dashicons-email-alt"></span>
            <div class="stat-info">
                <span class="stat-number"><?php echo number_format($stats['total']); ?></span>
                <span class="stat-label"><?php _e('Total', 'azure-plugin'); ?></span>
            </div>
        </a>
        <a href="<?php echo admin_url('admin.php?page=azure-plugin-newsletter&tab=queue&status=pending'); ?>" 
           class="stat-card pending <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
            <span class="stat-icon dashicons dashicons-clock"></span>
            <div class="stat-info">
                <span class="stat-number"><?php echo number_format($stats['pending']); ?></span>
                <span class="stat-label"><?php _e('Pending', 'azure-plugin'); ?></span>
            </div>
        </a>
        <a href="<?php echo admin_url('admin.php?page=azure-plugin-newsletter&tab=queue&status=processing'); ?>" 
           class="stat-card processing <?php echo $status_filter === 'processing' ? 'active' : ''; ?>">
            <span class="stat-icon dashicons dashicons-update"></span>
            <div class="stat-info">
                <span class="stat-number"><?php echo number_format($stats['processing']); ?></span>
                <span class="stat-label"><?php _e('Processing', 'azure-plugin'); ?></span>
            </div>
        </a>
        <a href="<?php echo admin_url('admin.php?page=azure-plugin-newsletter&tab=queue&status=sent'); ?>" 
           class="stat-card sent <?php echo $status_filter === 'sent' ? 'active' : ''; ?>">
            <span class="stat-icon dashicons dashicons-yes-alt"></span>
            <div class="stat-info">
                <span class="stat-number"><?php echo number_format($stats['sent']); ?></span>
                <span class="stat-label"><?php _e('Sent', 'azure-plugin'); ?></span>
            </div>
        </a>
        <a href="<?php echo admin_url('admin.php?page=azure-plugin-newsletter&tab=queue&status=failed'); ?>" 
           class="stat-card failed <?php echo $status_filter === 'failed' ? 'active' : ''; ?>">
            <span class="stat-icon dashicons dashicons-dismiss"></span>
            <div class="stat-info">
                <span class="stat-number"><?php echo number_format($stats['failed']); ?></span>
                <span class="stat-label"><?php _e('Failed', 'azure-plugin'); ?></span>
            </div>
        </a>
    </div>
    
    <!-- Actions Bar -->
    <div class="queue-actions-bar">
        <div class="left-actions">
            <button type="button" class="button button-primary" id="process-queue-btn">
                <span class="dashicons dashicons-controls-play"></span>
                <?php _e('Process Queue Now', 'azure-plugin'); ?>
            </button>
            <span id="process-status" class="status-message"></span>
            
            <span class="cron-info">
                <?php if ($next_cron): ?>
                    <span class="dashicons dashicons-clock"></span>
                    <?php printf(__('Next auto-run: %s', 'azure-plugin'), human_time_diff(time(), $next_cron)); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                    <?php _e('Cron not scheduled', 'azure-plugin'); ?>
                    <button type="button" class="button button-small" id="fix-cron-btn"><?php _e('Fix', 'azure-plugin'); ?></button>
                <?php endif; ?>
            </span>
        </div>
        
        <div class="right-actions">
            <form method="post" style="display: inline;">
                <?php wp_nonce_field('newsletter_queue_clear'); ?>
                <?php if ($stats['sent'] > 0): ?>
                <button type="submit" name="clear_action" value="clear_sent" class="button" 
                        onclick="return confirm('<?php _e('Clear all sent items from queue?', 'azure-plugin'); ?>');">
                    <?php _e('Clear Sent', 'azure-plugin'); ?>
                </button>
                <?php endif; ?>
                <?php if ($stats['failed'] > 0): ?>
                <button type="submit" name="clear_action" value="clear_failed" class="button" 
                        onclick="return confirm('<?php _e('Clear all failed items from queue?', 'azure-plugin'); ?>');">
                    <?php _e('Clear Failed', 'azure-plugin'); ?>
                </button>
                <?php endif; ?>
                <?php if ($stats['total'] > 0): ?>
                <button type="submit" name="clear_action" value="clear_all" class="button button-link-delete" 
                        onclick="return confirm('<?php _e('Clear ALL items from queue? This cannot be undone!', 'azure-plugin'); ?>');">
                    <?php _e('Clear All', 'azure-plugin'); ?>
                </button>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Queue Table -->
    <form method="post" id="queue-form">
        <?php wp_nonce_field('newsletter_queue_bulk'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="bulk_action" id="bulk-action-selector">
                    <option value=""><?php _e('Bulk Actions', 'azure-plugin'); ?></option>
                    <option value="retry"><?php _e('Retry', 'azure-plugin'); ?></option>
                    <option value="delete"><?php _e('Delete', 'azure-plugin'); ?></option>
                </select>
                <button type="submit" class="button action"><?php _e('Apply', 'azure-plugin'); ?></button>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(_n('%s item', '%s items', $total_items, 'azure-plugin'), number_format($total_items)); ?>
                </span>
                <span class="pagination-links">
                    <?php if ($current_page > 1): ?>
                    <a class="first-page button" href="<?php echo add_query_arg('paged', 1); ?>">«</a>
                    <a class="prev-page button" href="<?php echo add_query_arg('paged', $current_page - 1); ?>">‹</a>
                    <?php endif; ?>
                    <span class="paging-input">
                        <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                    </span>
                    <?php if ($current_page < $total_pages): ?>
                    <a class="next-page button" href="<?php echo add_query_arg('paged', $current_page + 1); ?>">›</a>
                    <a class="last-page button" href="<?php echo add_query_arg('paged', $total_pages); ?>">»</a>
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        
        <table class="wp-list-table widefat fixed striped queue-table">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all">
                    </td>
                    <th class="column-status"><?php _e('Status', 'azure-plugin'); ?></th>
                    <th class="column-recipient"><?php _e('Recipient', 'azure-plugin'); ?></th>
                    <th class="column-newsletter"><?php _e('Newsletter', 'azure-plugin'); ?></th>
                    <th class="column-scheduled"><?php _e('Scheduled', 'azure-plugin'); ?></th>
                    <th class="column-sent"><?php _e('Sent', 'azure-plugin'); ?></th>
                    <th class="column-attempts"><?php _e('Attempts', 'azure-plugin'); ?></th>
                    <th class="column-error"><?php _e('Error', 'azure-plugin'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queue_items)): ?>
                <tr>
                    <td colspan="8" class="no-items">
                        <?php 
                        if ($status_filter !== 'all') {
                            printf(__('No %s items in the queue.', 'azure-plugin'), $status_filter);
                        } else {
                            _e('The queue is empty.', 'azure-plugin');
                        }
                        ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($queue_items as $item): ?>
                <tr>
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="queue_ids[]" value="<?php echo esc_attr($item->id); ?>">
                    </th>
                    <td class="column-status">
                        <span class="status-badge status-<?php echo esc_attr($item->status); ?>">
                            <?php echo esc_html(ucfirst($item->status)); ?>
                        </span>
                    </td>
                    <td class="column-recipient">
                        <strong><?php echo esc_html($item->email); ?></strong>
                        <?php if ($item->user_id): ?>
                        <br><small class="user-id">User #<?php echo esc_html($item->user_id); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="column-newsletter">
                        <?php if ($item->newsletter_name): ?>
                        <a href="<?php echo admin_url('admin.php?page=azure-plugin-newsletter&action=new&id=' . $item->newsletter_id); ?>">
                            <?php echo esc_html($item->newsletter_name); ?>
                        </a>
                        <br><small><?php echo esc_html($item->newsletter_subject); ?></small>
                        <?php else: ?>
                        <span class="na">#<?php echo esc_html($item->newsletter_id); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="column-scheduled">
                        <?php 
                        if ($item->scheduled_at) {
                            $scheduled = strtotime($item->scheduled_at);
                            echo esc_html(date_i18n('M j, Y', $scheduled));
                            echo '<br><small>' . esc_html(date_i18n('g:i a', $scheduled)) . '</small>';
                        } else {
                            echo '<span class="na">—</span>';
                        }
                        ?>
                    </td>
                    <td class="column-sent">
                        <?php 
                        if ($item->sent_at) {
                            $sent = strtotime($item->sent_at);
                            echo esc_html(date_i18n('M j, Y', $sent));
                            echo '<br><small>' . esc_html(date_i18n('g:i a', $sent)) . '</small>';
                        } else {
                            echo '<span class="na">—</span>';
                        }
                        ?>
                    </td>
                    <td class="column-attempts">
                        <span class="attempts-count <?php echo $item->attempts >= 3 ? 'max-attempts' : ''; ?>">
                            <?php echo esc_html($item->attempts); ?>/3
                        </span>
                    </td>
                    <td class="column-error">
                        <?php if ($item->error_message): ?>
                        <span class="error-message" title="<?php echo esc_attr($item->error_message); ?>">
                            <?php echo esc_html(wp_trim_words($item->error_message, 10)); ?>
                        </span>
                        <?php else: ?>
                        <span class="na">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>
</div>

<style>
.newsletter-queue-wrap {
    margin-top: 20px;
}

/* Stats Cards */
.queue-stats-cards {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.stat-card {
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 8px;
    padding: 15px 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    text-decoration: none;
    transition: all 0.2s ease;
    min-width: 140px;
}

.stat-card:hover {
    border-color: #2271b1;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.stat-card.active {
    border-color: #2271b1;
    background: linear-gradient(135deg, #f0f6fc 0%, #fff 100%);
}

.stat-card .stat-icon {
    font-size: 28px;
    width: 28px;
    height: 28px;
    color: #646970;
}

.stat-card.pending .stat-icon { color: #dba617; }
.stat-card.processing .stat-icon { color: #2271b1; }
.stat-card.sent .stat-icon { color: #00a32a; }
.stat-card.failed .stat-icon { color: #d63638; }

.stat-card .stat-info {
    display: flex;
    flex-direction: column;
}

.stat-card .stat-number {
    font-size: 24px;
    font-weight: 600;
    color: #1d2327;
    line-height: 1.2;
}

.stat-card .stat-label {
    font-size: 12px;
    color: #646970;
    text-transform: uppercase;
}

/* Actions Bar */
.queue-actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    padding: 12px 15px;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 10px;
}

.queue-actions-bar .left-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

.queue-actions-bar .cron-info {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #646970;
    font-size: 13px;
}

.queue-actions-bar .status-message {
    font-size: 13px;
}

.queue-actions-bar .right-actions {
    display: flex;
    gap: 8px;
}

/* Queue Table */
.queue-table {
    margin-top: 0;
}

.queue-table .column-cb { width: 30px; }
.queue-table .column-status { width: 90px; }
.queue-table .column-recipient { width: 20%; }
.queue-table .column-newsletter { width: 25%; }
.queue-table .column-scheduled { width: 100px; }
.queue-table .column-sent { width: 100px; }
.queue-table .column-attempts { width: 70px; text-align: center; }
.queue-table .column-error { width: 15%; }

.queue-table .no-items {
    text-align: center;
    color: #646970;
    padding: 40px 20px;
}

.queue-table small {
    color: #646970;
}

.queue-table .na {
    color: #a7aaad;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.status-pending {
    background: #fcf0c3;
    color: #8a6d00;
}

.status-badge.status-processing {
    background: #e5f1f8;
    color: #0a4b78;
}

.status-badge.status-sent {
    background: #d7f4e3;
    color: #006028;
}

.status-badge.status-failed {
    background: #fce3e3;
    color: #8a0000;
}

/* Attempts */
.attempts-count {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    background: #f0f0f1;
    font-size: 12px;
}

.attempts-count.max-attempts {
    background: #fce3e3;
    color: #8a0000;
}

/* Error Message */
.error-message {
    display: inline-block;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #d63638;
    font-size: 12px;
    cursor: help;
}

/* Responsive */
@media screen and (max-width: 782px) {
    .queue-stats-cards {
        flex-direction: column;
    }
    
    .stat-card {
        width: 100%;
    }
    
    .queue-actions-bar {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Select all checkbox
    $('#cb-select-all').on('change', function() {
        $('input[name="queue_ids[]"]').prop('checked', $(this).prop('checked'));
    });
    
    // Process queue button
    $('#process-queue-btn').on('click', function() {
        var btn = $(this);
        var statusSpan = $('#process-status');
        
        btn.prop('disabled', true);
        btn.find('.dashicons').addClass('spin');
        statusSpan.html('<span style="color: #2271b1;"><?php _e('Processing...', 'azure-plugin'); ?></span>');
        
        $.post(ajaxurl, {
            action: 'azure_newsletter_process_queue',
            nonce: '<?php echo wp_create_nonce('azure_newsletter_process_queue'); ?>'
        }, function(response) {
            btn.prop('disabled', false);
            btn.find('.dashicons').removeClass('spin');
            
            if (response.success) {
                var data = response.data;
                var msg = data.sent + ' sent, ' + data.failed + ' failed';
                if (data.rate_limited) {
                    msg += ' (rate limited)';
                }
                statusSpan.html('<span style="color: #00a32a;"><span class="dashicons dashicons-yes"></span> ' + msg + '</span>');
                
                // Reload page after 1.5 seconds
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                statusSpan.html('<span style="color: #d63638;"><span class="dashicons dashicons-no"></span> ' + response.data + '</span>');
            }
        }).fail(function() {
            btn.prop('disabled', false);
            btn.find('.dashicons').removeClass('spin');
            statusSpan.html('<span style="color: #d63638;"><?php _e('Request failed', 'azure-plugin'); ?></span>');
        });
    });
    
    // Fix cron button
    $('#fix-cron-btn').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('<?php _e('Fixing...', 'azure-plugin'); ?>');
        
        $.post(ajaxurl, {
            action: 'azure_newsletter_schedule_cron',
            nonce: '<?php echo wp_create_nonce('azure_newsletter_schedule_cron'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                btn.prop('disabled', false).text('<?php _e('Fix', 'azure-plugin'); ?>');
                alert('<?php _e('Failed to schedule cron', 'azure-plugin'); ?>');
            }
        });
    });
});
</script>

