<?php
/**
 * Newsletter Templates Tab
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$templates_table = $wpdb->prefix . 'azure_newsletter_templates';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$templates_table}'") === $templates_table;

if (!$table_exists) {
    echo '<div class="notice notice-info"><p>' . __('Newsletter tables not yet created.', 'azure-plugin') . '</p></div>';
    return;
}

// Handle template deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_template')) {
        $id = intval($_GET['id']);
        $wpdb->delete($templates_table, array('id' => $id, 'is_system' => 0), array('%d', '%d'));
        echo '<div class="notice notice-success"><p>' . __('Template deleted.', 'azure-plugin') . '</p></div>';
    }
}

// Get templates grouped by category
$templates = $wpdb->get_results("
    SELECT * FROM {$templates_table}
    ORDER BY is_system DESC, category ASC, name ASC
");

$categories = array(
    'general' => __('General', 'azure-plugin'),
    'events' => __('Events', 'azure-plugin'),
    'onboarding' => __('Onboarding', 'azure-plugin'),
    'custom' => __('Custom', 'azure-plugin')
);

// Group templates
$grouped_templates = array();
foreach ($templates as $template) {
    $cat = $template->category ?: 'general';
    if (!isset($grouped_templates[$cat])) {
        $grouped_templates[$cat] = array();
    }
    $grouped_templates[$cat][] = $template;
}
?>

<div class="newsletter-templates">
    <div class="templates-intro">
        <p><?php _e('Templates help you get started quickly. Choose a template when creating a new newsletter, or create your own custom templates.', 'azure-plugin'); ?></p>
    </div>
    
    <?php foreach ($categories as $cat_slug => $cat_name): ?>
        <?php if (isset($grouped_templates[$cat_slug])): ?>
        <h3><?php echo esc_html($cat_name); ?></h3>
        <div class="templates-grid">
            <?php foreach ($grouped_templates[$cat_slug] as $template): ?>
            <div class="template-card <?php echo $template->is_system ? 'system-template' : ''; ?>">
                <div class="template-preview">
                    <?php if ($template->thumbnail_url): ?>
                    <img src="<?php echo esc_url($template->thumbnail_url); ?>" alt="<?php echo esc_attr($template->name); ?>">
                    <?php else: ?>
                    <div class="template-placeholder">
                        <span class="dashicons dashicons-email-alt"></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="template-info">
                    <h4><?php echo esc_html($template->name); ?></h4>
                    <p><?php echo esc_html($template->description); ?></p>
                    <div class="template-actions">
                        <a href="<?php echo admin_url('admin.php?page=azure-plugin-newsletter&action=new&template=' . $template->id); ?>" 
                           class="button button-primary button-small">
                            <?php _e('Use Template', 'azure-plugin'); ?>
                        </a>
                        <?php if (!$template->is_system): ?>
                        <a href="<?php echo admin_url('admin.php?page=azure-plugin-newsletter&tab=templates&action=edit&id=' . $template->id); ?>" 
                           class="button button-small">
                            <?php _e('Edit', 'azure-plugin'); ?>
                        </a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=azure-plugin-newsletter&tab=templates&action=delete&id=' . $template->id), 'delete_template'); ?>" 
                           class="button button-small" 
                           onclick="return confirm('<?php _e('Are you sure?', 'azure-plugin'); ?>')">
                            <?php _e('Delete', 'azure-plugin'); ?>
                        </a>
                        <?php else: ?>
                        <span class="system-badge"><?php _e('Built-in', 'azure-plugin'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
    
    <div class="create-template-section">
        <h3><?php _e('Create Custom Template', 'azure-plugin'); ?></h3>
        <p><?php _e('Design a newsletter and save it as a template for reuse.', 'azure-plugin'); ?></p>
        <a href="<?php echo admin_url('admin.php?page=azure-plugin-newsletter&action=new&save_as_template=1'); ?>" class="button">
            <?php _e('Create New Template', 'azure-plugin'); ?>
        </a>
    </div>
</div>

<style>
.newsletter-templates .templates-intro {
    margin-bottom: 20px;
}
.newsletter-templates .templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.newsletter-templates .template-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    overflow: hidden;
}
.newsletter-templates .template-card.system-template {
    border-color: #2271b1;
}
.newsletter-templates .template-preview {
    height: 180px;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid #eee;
}
.newsletter-templates .template-preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: cover;
}
.newsletter-templates .template-placeholder {
    color: #ccd0d4;
}
.newsletter-templates .template-placeholder .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
}
.newsletter-templates .template-info {
    padding: 15px;
}
.newsletter-templates .template-info h4 {
    margin: 0 0 8px;
}
.newsletter-templates .template-info p {
    margin: 0 0 15px;
    color: #646970;
    font-size: 13px;
}
.newsletter-templates .template-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}
.newsletter-templates .system-badge {
    font-size: 11px;
    color: #2271b1;
    background: #f0f6fc;
    padding: 2px 8px;
    border-radius: 3px;
}
.newsletter-templates .create-template-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 4px;
    margin-top: 30px;
}
</style>




