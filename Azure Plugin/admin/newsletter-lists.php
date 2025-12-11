<?php
/**
 * Newsletter Lists Management
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$lists_table = $wpdb->prefix . 'azure_newsletter_lists';
$members_table = $wpdb->prefix . 'azure_newsletter_list_members';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$lists_table}'") === $lists_table;

if (!$table_exists) {
    echo '<div class="notice notice-info"><p>' . __('Newsletter tables not yet created.', 'azure-plugin') . '</p></div>';
    return;
}

// Handle list creation
if (isset($_POST['create_list']) && wp_verify_nonce($_POST['_wpnonce'], 'create_newsletter_list')) {
    $name = sanitize_text_field($_POST['list_name']);
    $description = sanitize_textarea_field($_POST['list_description']);
    $type = sanitize_key($_POST['list_type']);
    $criteria = array();
    
    if ($type === 'role') {
        $criteria['roles'] = array_map('sanitize_key', (array)$_POST['list_roles']);
    } elseif ($type === 'tag') {
        $criteria['tags'] = array_map('sanitize_text_field', (array)$_POST['list_tags']);
    }
    
    $wpdb->insert($lists_table, array(
        'name' => $name,
        'description' => $description,
        'type' => $type,
        'criteria' => json_encode($criteria),
        'created_at' => current_time('mysql')
    ));
    
    echo '<div class="notice notice-success"><p>' . __('List created successfully.', 'azure-plugin') . '</p></div>';
}

// Handle list deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_list')) {
        $id = intval($_GET['id']);
        $wpdb->delete($lists_table, array('id' => $id), array('%d'));
        $wpdb->delete($members_table, array('list_id' => $id), array('%d'));
        echo '<div class="notice notice-success"><p>' . __('List deleted.', 'azure-plugin') . '</p></div>';
    }
}

// Get all lists
$lists = $wpdb->get_results("SELECT * FROM {$lists_table} ORDER BY name ASC");

// Get WordPress user count (all subscribers)
$total_users = count_users();
$all_users_count = $total_users['total_users'];

// Get WordPress roles
$wp_roles = wp_roles();
$roles = $wp_roles->get_names();
?>

<div class="newsletter-lists-page">
    
    <!-- Default List (All WordPress Users) -->
    <div class="default-list-section">
        <h3><?php _e('Default List', 'azure-plugin'); ?></h3>
        <div class="list-card default-list">
            <h4>
                <span class="dashicons dashicons-admin-users"></span>
                <?php _e('All WordPress Subscribers', 'azure-plugin'); ?>
            </h4>
            <div class="list-meta">
                <span><?php printf(__('%s subscribers', 'azure-plugin'), number_format($all_users_count)); ?></span>
            </div>
            <p class="description"><?php _e('All registered WordPress users will receive your newsletters by default.', 'azure-plugin'); ?></p>
        </div>
    </div>
    
    <!-- Custom Lists -->
    <div class="custom-lists-section">
        <h3>
            <?php _e('Custom Lists', 'azure-plugin'); ?>
            <button type="button" class="button button-primary" id="create-list-btn">
                <?php _e('+ Create New List', 'azure-plugin'); ?>
            </button>
        </h3>
        
        <?php if (empty($lists)): ?>
        <div class="empty-lists">
            <p><?php _e('No custom lists yet. Create a list to segment your subscribers by role, tag, or custom criteria.', 'azure-plugin'); ?></p>
        </div>
        <?php else: ?>
        <div class="lists-grid">
            <?php foreach ($lists as $list): ?>
            <?php
            // Count members
            $member_count = 0;
            $criteria = json_decode($list->criteria, true);
            
            if ($list->type === 'all_users') {
                $member_count = $all_users_count;
            } elseif ($list->type === 'role' && !empty($criteria['roles'])) {
                foreach ($criteria['roles'] as $role) {
                    $users = get_users(array('role' => $role, 'count_total' => true));
                    $member_count += count($users);
                }
            } elseif ($list->type === 'custom') {
                $member_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$members_table} WHERE list_id = %d AND unsubscribed_at IS NULL",
                    $list->id
                ));
            }
            ?>
            <div class="list-card">
                <h4><?php echo esc_html($list->name); ?></h4>
                <div class="list-meta">
                    <span class="type-badge type-<?php echo esc_attr($list->type); ?>">
                        <?php echo ucfirst($list->type); ?>
                    </span>
                    <span><?php printf(__('%s subscribers', 'azure-plugin'), number_format($member_count)); ?></span>
                </div>
                <p><?php echo esc_html($list->description); ?></p>
                
                <?php if ($list->type === 'role' && !empty($criteria['roles'])): ?>
                <div class="list-criteria">
                    <strong><?php _e('Roles:', 'azure-plugin'); ?></strong>
                    <?php echo implode(', ', array_map(function($r) use ($roles) { return $roles[$r] ?? $r; }, $criteria['roles'])); ?>
                </div>
                <?php endif; ?>
                
                <div class="list-actions">
                    <a href="<?php echo admin_url('admin.php?page=azure-plugin-newsletter&tab=lists&action=edit&id=' . $list->id); ?>" class="button button-small">
                        <?php _e('Edit', 'azure-plugin'); ?>
                    </a>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=azure-plugin-newsletter&tab=lists&action=delete&id=' . $list->id), 'delete_list'); ?>" 
                       class="button button-small" onclick="return confirm('<?php _e('Are you sure?', 'azure-plugin'); ?>')">
                        <?php _e('Delete', 'azure-plugin'); ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create List Modal -->
<div id="create-list-modal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Create New List', 'azure-plugin'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <form method="post">
            <?php wp_nonce_field('create_newsletter_list'); ?>
            <div class="modal-body">
                <table class="form-table">
                    <tr>
                        <th><label for="list_name"><?php _e('List Name', 'azure-plugin'); ?></label></th>
                        <td><input type="text" name="list_name" id="list_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="list_description"><?php _e('Description', 'azure-plugin'); ?></label></th>
                        <td><textarea name="list_description" id="list_description" class="regular-text" rows="2"></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="list_type"><?php _e('List Type', 'azure-plugin'); ?></label></th>
                        <td>
                            <select name="list_type" id="list_type" required>
                                <option value="role"><?php _e('Based on User Role', 'azure-plugin'); ?></option>
                                <option value="custom"><?php _e('Manual / Custom', 'azure-plugin'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <div id="role-options" class="type-options">
                    <h4><?php _e('Select Roles', 'azure-plugin'); ?></h4>
                    <div class="roles-checkboxes">
                        <?php foreach ($roles as $role_key => $role_name): ?>
                        <label>
                            <input type="checkbox" name="list_roles[]" value="<?php echo esc_attr($role_key); ?>">
                            <?php echo esc_html($role_name); ?>
                            (<?php echo number_format($total_users['avail_roles'][$role_key] ?? 0); ?>)
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div id="custom-options" class="type-options" style="display:none;">
                    <p class="description"><?php _e('Manual lists allow you to add subscribers individually or import from CSV.', 'azure-plugin'); ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button modal-cancel"><?php _e('Cancel', 'azure-plugin'); ?></button>
                <button type="submit" name="create_list" class="button button-primary"><?php _e('Create List', 'azure-plugin'); ?></button>
            </div>
        </form>
    </div>
</div>

<style>
.newsletter-lists-page h3 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
}
.newsletter-lists-page .default-list-section h3 {
    margin-top: 0;
}
.newsletter-lists-page .lists-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 15px;
}
.newsletter-lists-page .list-card {
    background: #f8f9fa;
    border: 1px solid #ccd0d4;
    padding: 20px;
    border-radius: 4px;
}
.newsletter-lists-page .list-card.default-list {
    background: #f0f6fc;
    border-color: #2271b1;
    max-width: 400px;
}
.newsletter-lists-page .list-card h4 {
    margin: 0 0 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.newsletter-lists-page .list-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
    color: #646970;
    font-size: 13px;
}
.newsletter-lists-page .type-badge {
    background: #dcdcde;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
}
.newsletter-lists-page .type-badge.type-role {
    background: #f0f6fc;
    color: #2271b1;
}
.newsletter-lists-page .list-criteria {
    margin: 10px 0;
    padding: 10px;
    background: #fff;
    border-radius: 3px;
    font-size: 13px;
}
.newsletter-lists-page .list-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}
.newsletter-lists-page .empty-lists {
    background: #f8f9fa;
    padding: 30px;
    text-align: center;
    color: #646970;
    margin-top: 15px;
}

/* Modal Styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-content {
    background: #fff;
    width: 90%;
    max-width: 600px;
    border-radius: 4px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
}
.modal-header h3 {
    margin: 0;
}
.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}
.modal-body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}
.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.type-options {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}
.roles-checkboxes {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}
.roles-checkboxes label {
    display: flex;
    align-items: center;
    gap: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Open modal
    $('#create-list-btn').on('click', function() {
        $('#create-list-modal').show();
    });
    
    // Close modal
    $('.modal-close, .modal-cancel').on('click', function() {
        $('#create-list-modal').hide();
    });
    
    // Close on background click
    $('#create-list-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // Toggle type options
    $('#list_type').on('change', function() {
        $('.type-options').hide();
        if ($(this).val() === 'role') {
            $('#role-options').show();
        } else if ($(this).val() === 'custom') {
            $('#custom-options').show();
        }
    });
});
</script>
