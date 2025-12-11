<?php
/**
 * Newsletter Campaigns List
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$newsletters_table = $wpdb->prefix . 'azure_newsletters';
$stats_table = $wpdb->prefix . 'azure_newsletter_stats';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$newsletters_table}'") === $newsletters_table;

if (!$table_exists) {
    echo '<div class="notice notice-info"><p>' . __('Newsletter tables not yet created. Please deactivate and reactivate the plugin to create the necessary database tables.', 'azure-plugin') . '</p></div>';
    return;
}

// Handle bulk actions
if (isset($_POST['action']) && isset($_POST['newsletter_ids'])) {
    if (wp_verify_nonce($_POST['_wpnonce'], 'bulk_newsletters')) {
        $ids = array_map('intval', $_POST['newsletter_ids']);
        switch ($_POST['action']) {
            case 'delete':
                $wpdb->query("DELETE FROM {$newsletters_table} WHERE id IN (" . implode(',', $ids) . ")");
                echo '<div class="notice notice-success"><p>' . __('Selected campaigns deleted.', 'azure-plugin') . '</p></div>';
                break;
            case 'duplicate':
                foreach ($ids as $id) {
                    $original = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$newsletters_table} WHERE id = %d", $id), ARRAY_A);
                    if ($original) {
                        unset($original['id']);
                        $original['name'] = $original['name'] . ' (Copy)';
                        $original['status'] = 'draft';
                        $original['created_at'] = current_time('mysql');
                        $wpdb->insert($newsletters_table, $original);
                    }
                }
                echo '<div class="notice notice-success"><p>' . __('Selected campaigns duplicated.', 'azure-plugin') . '</p></div>';
                break;
        }
    }
}

// Get filter and search params
$status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Build query
$where = "WHERE 1=1";
if ($status_filter) {
    $where .= $wpdb->prepare(" AND status = %s", $status_filter);
}
if ($search) {
    $where .= $wpdb->prepare(" AND (name LIKE %s OR subject LIKE %s)", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
}

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

$total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$newsletters_table} {$where}");
$total_pages = ceil($total_items / $per_page);

// Get campaigns
$campaigns = $wpdb->get_results("
    SELECT * FROM {$newsletters_table} 
    {$where}
    ORDER BY created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
");

// Get status counts
$status_counts = $wpdb->get_results("
    SELECT status, COUNT(*) as count 
    FROM {$newsletters_table} 
    GROUP BY status
", OBJECT_K);
?>

<div class="newsletter-campaigns-page">
    
    <!-- Quick Stats -->
    <div class="quick-stats">
        <div class="stat-box">
            <span class="stat-value"><?php echo intval($status_counts['sent']->count ?? 0); ?></span>
            <span class="stat-label"><?php _e('Sent', 'azure-plugin'); ?></span>
        </div>
        <div class="stat-box">
            <span class="stat-value"><?php echo intval($status_counts['scheduled']->count ?? 0); ?></span>
            <span class="stat-label"><?php _e('Scheduled', 'azure-plugin'); ?></span>
        </div>
        <div class="stat-box">
            <span class="stat-value"><?php echo intval($status_counts['draft']->count ?? 0); ?></span>
            <span class="stat-label"><?php _e('Drafts', 'azure-plugin'); ?></span>
        </div>
    </div>
    
    <!-- Filters and Search -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select name="filter_status" id="filter-status">
                <option value=""><?php _e('All statuses', 'azure-plugin'); ?></option>
                <option value="draft" <?php selected($status_filter, 'draft'); ?>><?php _e('Draft', 'azure-plugin'); ?></option>
                <option value="scheduled" <?php selected($status_filter, 'scheduled'); ?>><?php _e('Scheduled', 'azure-plugin'); ?></option>
                <option value="sending" <?php selected($status_filter, 'sending'); ?>><?php _e('Sending', 'azure-plugin'); ?></option>
                <option value="sent" <?php selected($status_filter, 'sent'); ?>><?php _e('Sent', 'azure-plugin'); ?></option>
                <option value="paused" <?php selected($status_filter, 'paused'); ?>><?php _e('Paused', 'azure-plugin'); ?></option>
            </select>
            <button type="button" class="button" id="filter-btn"><?php _e('Filter', 'azure-plugin'); ?></button>
        </div>
        
        <form method="get" class="search-box">
            <input type="hidden" name="page" value="azure-plugin-newsletter">
            <input type="hidden" name="tab" value="campaigns">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search campaigns...', 'azure-plugin'); ?>">
            <button type="submit" class="button"><?php _e('Search', 'azure-plugin'); ?></button>
        </form>
        
        <div class="tablenav-pages">
            <?php if ($total_pages > 1): ?>
            <span class="pagination-links">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'current' => $current_page,
                    'total' => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;'
                ));
                ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (empty($campaigns)): ?>
    <div class="empty-state">
        <span class="dashicons dashicons-email-alt"></span>
        <h3><?php _e('No campaigns yet', 'azure-plugin'); ?></h3>
        <p><?php _e('Create your first newsletter campaign to get started.', 'azure-plugin'); ?></p>
        <a href="<?php echo admin_url('admin.php?page=azure-plugin-newsletter&action=new'); ?>" class="button button-primary button-hero">
            <?php _e('Create Your First Campaign', 'azure-plugin'); ?>
        </a>
    </div>
    <?php else: ?>
    
    <form method="post">
        <?php wp_nonce_field('bulk_newsletters'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="action" id="bulk-action-selector">
                    <option value=""><?php _e('Bulk Actions', 'azure-plugin'); ?></option>
                    <option value="delete"><?php _e('Delete', 'azure-plugin'); ?></option>
                    <option value="duplicate"><?php _e('Duplicate', 'azure-plugin'); ?></option>
                </select>
                <button type="submit" class="button"><?php _e('Apply', 'azure-plugin'); ?></button>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped campaigns-table">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all">
                    </td>
                    <th class="column-name"><?php _e('Name', 'azure-plugin'); ?></th>
                    <th class="column-subject"><?php _e('Subject', 'azure-plugin'); ?></th>
                    <th class="column-status"><?php _e('Status', 'azure-plugin'); ?></th>
                    <th class="column-sent"><?php _e('Sent', 'azure-plugin'); ?></th>
                    <th class="column-opens"><?php _e('Opens', 'azure-plugin'); ?></th>
                    <th class="column-clicks"><?php _e('Clicks', 'azure-plugin'); ?></th>
                    <th class="column-date"><?php _e('Date', 'azure-plugin'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $campaign): ?>
                <?php
                // Get stats for this campaign
                $sent_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$stats_table} WHERE newsletter_id = %d AND event_type = 'sent'",
                    $campaign->id
                ));
                $open_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT email) FROM {$stats_table} WHERE newsletter_id = %d AND event_type = 'opened'",
                    $campaign->id
                ));
                $click_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT email) FROM {$stats_table} WHERE newsletter_id = %d AND event_type = 'clicked'",
                    $campaign->id
                ));
                
                $open_rate = $sent_count > 0 ? round(($open_count / $sent_count) * 100, 1) : 0;
                $click_rate = $sent_count > 0 ? round(($click_count / $sent_count) * 100, 1) : 0;
                ?>
                <tr>
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="newsletter_ids[]" value="<?php echo $campaign->id; ?>">
                    </th>
                    <td class="column-name">
                        <strong>
                            <a href="<?php echo admin_url('admin.php?page=azure-plugin-newsletter&action=new&id=' . $campaign->id); ?>">
                                <?php echo esc_html($campaign->name); ?>
                            </a>
                        </strong>
                        <div class="row-actions">
                            <span class="edit">
                                <a href="<?php echo admin_url('admin.php?page=azure-plugin-newsletter&action=new&id=' . $campaign->id); ?>">
                                    <?php _e('Edit', 'azure-plugin'); ?>
                                </a> |
                            </span>
                            <span class="duplicate">
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=azure-plugin-newsletter&tab=campaigns&action=duplicate&id=' . $campaign->id), 'duplicate_' . $campaign->id); ?>">
                                    <?php _e('Duplicate', 'azure-plugin'); ?>
                                </a> |
                            </span>
                            <?php if ($campaign->status === 'draft'): ?>
                            <span class="delete">
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=azure-plugin-newsletter&tab=campaigns&action=delete&id=' . $campaign->id), 'delete_' . $campaign->id); ?>" 
                                   class="submitdelete" onclick="return confirm('<?php _e('Are you sure?', 'azure-plugin'); ?>')">
                                    <?php _e('Delete', 'azure-plugin'); ?>
                                </a>
                            </span>
                            <?php endif; ?>
                            <span class="view">
                                <a href="<?php echo admin_url('admin.php?page=azure-plugin-newsletter&tab=statistics&campaign=' . $campaign->id); ?>">
                                    <?php _e('View Stats', 'azure-plugin'); ?>
                                </a>
                            </span>
                        </div>
                    </td>
                    <td class="column-subject"><?php echo esc_html($campaign->subject); ?></td>
                    <td class="column-status">
                        <span class="status-badge status-<?php echo esc_attr($campaign->status); ?>">
                            <?php echo ucfirst($campaign->status); ?>
                        </span>
                        <?php if ($campaign->status === 'scheduled' && $campaign->scheduled_at): ?>
                        <br><small><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->scheduled_at)); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="column-sent"><?php echo number_format($sent_count); ?></td>
                    <td class="column-opens">
                        <?php if ($sent_count > 0): ?>
                        <?php echo number_format($open_count); ?> <small>(<?php echo $open_rate; ?>%)</small>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td class="column-clicks">
                        <?php if ($sent_count > 0): ?>
                        <?php echo number_format($click_count); ?> <small>(<?php echo $click_rate; ?>%)</small>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td class="column-date">
                        <?php 
                        if ($campaign->sent_at) {
                            echo date_i18n(get_option('date_format'), strtotime($campaign->sent_at));
                        } else {
                            echo date_i18n(get_option('date_format'), strtotime($campaign->created_at));
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>
    <?php endif; ?>
</div>

<style>
.newsletter-campaigns-page .quick-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}
.newsletter-campaigns-page .stat-box {
    background: #f0f6fc;
    padding: 15px 25px;
    border-radius: 4px;
    text-align: center;
    border-left: 4px solid #2271b1;
}
.newsletter-campaigns-page .stat-value {
    display: block;
    font-size: 28px;
    font-weight: 700;
    color: #1d2327;
}
.newsletter-campaigns-page .stat-label {
    color: #646970;
    font-size: 13px;
}
.newsletter-campaigns-page .empty-state {
    text-align: center;
    padding: 60px 20px;
}
.newsletter-campaigns-page .empty-state .dashicons {
    font-size: 64px;
    width: 64px;
    height: 64px;
    color: #dcdcde;
}
.newsletter-campaigns-page .empty-state h3 {
    margin: 20px 0 10px;
}
.newsletter-campaigns-page .search-box {
    float: right;
    display: flex;
    gap: 5px;
}
.newsletter-campaigns-page .column-cb {
    width: 30px;
}
.newsletter-campaigns-page .column-status {
    width: 120px;
}
.newsletter-campaigns-page .column-sent,
.newsletter-campaigns-page .column-opens,
.newsletter-campaigns-page .column-clicks {
    width: 100px;
}
.newsletter-campaigns-page .column-date {
    width: 100px;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#filter-btn').on('click', function() {
        var status = $('#filter-status').val();
        var url = '<?php echo admin_url('admin.php?page=azure-plugin-newsletter&tab=campaigns'); ?>';
        if (status) {
            url += '&status=' + status;
        }
        window.location.href = url;
    });
    
    $('#cb-select-all').on('change', function() {
        $('input[name="newsletter_ids[]"]').prop('checked', $(this).is(':checked'));
    });
});
</script>
