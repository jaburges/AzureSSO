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

// Handle reset system templates
if (isset($_POST['reset_system_templates']) && wp_verify_nonce($_POST['_wpnonce'], 'reset_templates')) {
    // Delete existing system templates
    $wpdb->delete($templates_table, array('is_system' => 1), array('%d'));
    
    // Re-insert default templates with HTML content
    $default_templates = Azure_Newsletter_Module::get_default_templates();
    foreach ($default_templates as $template) {
        $wpdb->insert($templates_table, $template);
    }
    
    echo '<div class="notice notice-success"><p>' . __('System templates have been reset with updated designs.', 'azure-plugin') . '</p></div>';
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
                        <?php if (!empty($template->content_html)): ?>
                        <button type="button" class="button button-small preview-template" 
                                data-template-id="<?php echo esc_attr($template->id); ?>"
                                data-template-name="<?php echo esc_attr($template->name); ?>">
                            <?php _e('Preview', 'azure-plugin'); ?>
                        </button>
                        <?php endif; ?>
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
    
    <div class="reset-templates-section">
        <h3><?php _e('Reset System Templates', 'azure-plugin'); ?></h3>
        <p><?php _e('If your built-in templates are missing content or outdated, you can reset them to the latest versions.', 'azure-plugin'); ?></p>
        <form method="post" onsubmit="return confirm('<?php _e('This will reset all system templates to their default state. Custom templates will not be affected. Continue?', 'azure-plugin'); ?>');">
            <?php wp_nonce_field('reset_templates'); ?>
            <button type="submit" name="reset_system_templates" class="button">
                <?php _e('Reset System Templates', 'azure-plugin'); ?>
            </button>
        </form>
    </div>
</div>

<!-- Template Preview Modal -->
<div id="template-preview-modal" class="template-modal" style="display: none;">
    <div class="template-modal-content">
        <div class="template-modal-header">
            <h2 id="preview-template-name"><?php _e('Template Preview', 'azure-plugin'); ?></h2>
            <button type="button" class="template-modal-close">&times;</button>
        </div>
        <div class="template-modal-body">
            <iframe id="template-preview-iframe" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Preview template
    $('.preview-template').on('click', function() {
        var templateId = $(this).data('template-id');
        var templateName = $(this).data('template-name');
        
        $('#preview-template-name').text(templateName + ' - Preview');
        
        // Load template content via AJAX
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'azure_newsletter_get_template',
                template_id: templateId,
                nonce: '<?php echo wp_create_nonce('newsletter_get_template'); ?>'
            },
            success: function(response) {
                if (response.success && response.data.content_html) {
                    var iframe = document.getElementById('template-preview-iframe');
                    iframe.contentDocument.open();
                    iframe.contentDocument.write(response.data.content_html);
                    iframe.contentDocument.close();
                    $('#template-preview-modal').fadeIn(200);
                } else {
                    alert('<?php _e('Template has no preview content.', 'azure-plugin'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Failed to load template preview.', 'azure-plugin'); ?>');
            }
        });
    });
    
    // Close modal
    $('.template-modal-close, .template-modal').on('click', function(e) {
        if (e.target === this) {
            $('#template-preview-modal').fadeOut(200);
        }
    });
    
    // ESC to close
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#template-preview-modal').fadeOut(200);
        }
    });
});
</script>

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
.newsletter-templates .create-template-section,
.newsletter-templates .reset-templates-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 4px;
    margin-top: 20px;
}
.newsletter-templates .reset-templates-section {
    background: #fff8e5;
    border: 1px solid #ffcc00;
}

/* Template Preview Modal */
.template-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.template-modal-content {
    background: #fff;
    width: 90%;
    max-width: 700px;
    height: 85vh;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}
.template-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    background: #f8f9fa;
}
.template-modal-header h2 {
    margin: 0;
    font-size: 16px;
}
.template-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    padding: 0;
    line-height: 1;
}
.template-modal-close:hover {
    color: #d63638;
}
.template-modal-body {
    flex: 1;
    overflow: auto;
    background: #e0e0e0;
    padding: 20px;
}
.template-modal-body iframe {
    background: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
</style>




