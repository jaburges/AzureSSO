<?php
/**
 * Newsletter Editor - 4-Step Progressive Workflow
 */

if (!defined('ABSPATH')) {
    exit;
}

// Enqueue GrapesJS and dependencies
wp_enqueue_media(); // For WordPress Media Library integration
wp_enqueue_style('grapesjs', 'https://unpkg.com/grapesjs@0.21.10/dist/css/grapes.min.css', array(), '0.21.10');
wp_enqueue_style('grapesjs-newsletter', 'https://unpkg.com/grapesjs-preset-newsletter@1.0.2/dist/grapesjs-preset-newsletter.css', array('grapesjs'), '1.0.2');
wp_enqueue_script('grapesjs', 'https://unpkg.com/grapesjs@0.21.10/dist/grapes.min.js', array(), '0.21.10', true);
wp_enqueue_script('grapesjs-newsletter', 'https://unpkg.com/grapesjs-preset-newsletter@1.0.2/dist/grapesjs-preset-newsletter.min.js', array('grapesjs'), '1.0.2', true);
wp_enqueue_script('newsletter-editor', AZURE_PLUGIN_URL . 'js/newsletter-editor.js', array('jquery', 'grapesjs', 'grapesjs-newsletter'), AZURE_PLUGIN_VERSION, true);

$settings = Azure_Settings::get_all_settings();
$from_addresses = $settings['newsletter_from_addresses'] ?? array();

// Get current user for test send
$current_user = wp_get_current_user();

// Check if editing existing newsletter
$newsletter_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$newsletter = null;

if ($newsletter_id > 0) {
    global $wpdb;
    $table = $wpdb->prefix . 'azure_newsletters';
    $newsletter = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $newsletter_id));
}

// Load template if specified
$template_id = isset($_GET['template']) ? intval($_GET['template']) : 0;
$template = null;
if ($template_id > 0 && !$newsletter) {
    global $wpdb;
    $templates_table = $wpdb->prefix . 'azure_newsletter_templates';
    $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$templates_table} WHERE id = %d", $template_id));
}

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
?>

<div class="wrap newsletter-editor-wrap">
    <h1><?php echo $newsletter ? __('Edit Newsletter', 'azure-plugin') : __('Create Newsletter', 'azure-plugin'); ?></h1>
    
    <!-- Arrow Flow Progress Steps -->
    <div class="arrow-flow-steps">
        <div class="arrow-step <?php echo $step === 1 ? 'current' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>" data-step="1">
            <div class="arrow-content">
                <?php if ($step > 1): ?>
                <span class="dashicons dashicons-yes-alt"></span>
                <?php else: ?>
                <span class="step-num">1</span>
                <?php endif; ?>
                <span class="step-text"><?php _e('Setup', 'azure-plugin'); ?></span>
            </div>
        </div>
        <div class="arrow-step <?php echo $step === 2 ? 'current' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?> <?php echo $step < 2 ? 'pending' : ''; ?>" data-step="2">
            <div class="arrow-content">
                <?php if ($step > 2): ?>
                <span class="dashicons dashicons-yes-alt"></span>
                <?php else: ?>
                <span class="step-num">2</span>
                <?php endif; ?>
                <span class="step-text"><?php _e('Design', 'azure-plugin'); ?></span>
            </div>
        </div>
        <div class="arrow-step <?php echo $step === 3 ? 'current' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?> <?php echo $step < 3 ? 'pending' : ''; ?>" data-step="3">
            <div class="arrow-content">
                <?php if ($step > 3): ?>
                <span class="dashicons dashicons-yes-alt"></span>
                <?php else: ?>
                <span class="step-num">3</span>
                <?php endif; ?>
                <span class="step-text"><?php _e('Review', 'azure-plugin'); ?></span>
            </div>
        </div>
        <div class="arrow-step <?php echo $step === 4 ? 'current' : ''; ?> <?php echo $step < 4 ? 'pending' : ''; ?>" data-step="4">
            <div class="arrow-content">
                <span class="step-num">4</span>
                <span class="step-text"><?php _e('Send', 'azure-plugin'); ?></span>
            </div>
        </div>
    </div>
    
    <form id="newsletter-form" method="post">
        <?php wp_nonce_field('newsletter_editor', 'newsletter_nonce'); ?>
        <input type="hidden" name="newsletter_id" id="newsletter_id" value="<?php echo esc_attr($newsletter_id); ?>">
        <input type="hidden" name="current_step" id="current_step" value="<?php echo esc_attr($step); ?>">
        
        <!-- Step 1: Setup -->
        <div class="step-content" id="step-1-content" style="<?php echo $step !== 1 ? 'display:none;' : ''; ?>">
            <div class="step-panel">
                <h2><?php _e('Newsletter Setup', 'azure-plugin'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th><label for="newsletter_name"><?php _e('Internal Name', 'azure-plugin'); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="text" id="newsletter_name" name="newsletter_name" class="regular-text" required
                                   value="<?php echo esc_attr($newsletter->name ?? ''); ?>"
                                   placeholder="<?php _e('e.g., December 2025 Newsletter', 'azure-plugin'); ?>">
                            <p class="description"><?php _e('For your reference only. Not shown to subscribers.', 'azure-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="newsletter_subject"><?php _e('Email Subject', 'azure-plugin'); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="text" id="newsletter_subject" name="newsletter_subject" class="large-text" required
                                   value="<?php echo esc_attr($newsletter->subject ?? ''); ?>"
                                   placeholder="<?php _e('e.g., Your December Newsletter is here!', 'azure-plugin'); ?>">
                            <div class="subject-tools">
                                <button type="button" class="button button-small insert-personalization" data-tag="{{first_name}}">
                                    <?php _e('Insert First Name', 'azure-plugin'); ?>
                                </button>
                                <span class="char-count"><span id="subject-chars">0</span>/60</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="newsletter_from"><?php _e('From', 'azure-plugin'); ?> <span class="required">*</span></label></th>
                        <td>
                            <?php if (!empty($from_addresses)): ?>
                            <select id="newsletter_from" name="newsletter_from" class="regular-text" required>
                                <option value=""><?php _e('Select sender...', 'azure-plugin'); ?></option>
                                <?php foreach ($from_addresses as $addr): ?>
                                <option value="<?php echo esc_attr($addr['email'] . '|' . $addr['name']); ?>"
                                        <?php selected(($newsletter->from_email ?? '') . '|' . ($newsletter->from_name ?? ''), $addr['email'] . '|' . $addr['name']); ?>>
                                    <?php echo esc_html($addr['name'] . ' <' . $addr['email'] . '>'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <p class="notice notice-warning" style="margin:0;">
                                <?php _e('No sender addresses configured.', 'azure-plugin'); ?>
                                <a href="<?php echo admin_url('admin.php?page=azure-plugin-newsletter&tab=settings'); ?>">
                                    <?php _e('Configure now', 'azure-plugin'); ?>
                                </a>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Recipients', 'azure-plugin'); ?> <span class="required">*</span></label></th>
                        <td>
                            <div class="recipient-checkboxes">
                                <label class="recipient-checkbox">
                                    <input type="checkbox" name="newsletter_lists[]" value="all" checked>
                                    <span class="checkbox-label">
                                        <strong><?php _e('All WordPress Subscribers', 'azure-plugin'); ?></strong>
                                        <span class="list-count" data-list="all"></span>
                                    </span>
                                </label>
                                <?php
                                // Load custom lists
                                global $wpdb;
                                $lists_table = $wpdb->prefix . 'azure_newsletter_lists';
                                if ($wpdb->get_var("SHOW TABLES LIKE '{$lists_table}'") === $lists_table) {
                                    $lists = $wpdb->get_results("SELECT id, name, type FROM {$lists_table} ORDER BY name");
                                    foreach ($lists as $list): ?>
                                <label class="recipient-checkbox">
                                    <input type="checkbox" name="newsletter_lists[]" value="<?php echo esc_attr($list->id); ?>">
                                    <span class="checkbox-label">
                                        <strong><?php echo esc_html($list->name); ?></strong>
                                        <span class="list-type"><?php echo esc_html(ucfirst($list->type)); ?></span>
                                        <span class="list-count" data-list="<?php echo esc_attr($list->id); ?>"></span>
                                    </span>
                                </label>
                                    <?php endforeach;
                                }
                                ?>
                            </div>
                            <p class="description">
                                <span class="dashicons dashicons-info-outline"></span>
                                <?php _e('Select one or more lists. Duplicate emails are automatically removed.', 'azure-plugin'); ?>
                            </p>
                            <div id="recipient-summary" class="recipient-summary">
                                <strong><?php _e('Total recipients:', 'azure-plugin'); ?></strong>
                                <span id="total-recipient-count">0</span>
                            </div>
                        </td>
                    </tr>
                </table>
                
                <div class="step-actions">
                    <button type="button" class="button button-primary next-step" data-next="2">
                        <?php _e('Continue to Design', 'azure-plugin'); ?> &rarr;
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Step 2: Design (GrapesJS Editor) -->
        <div class="step-content step-design" id="step-2-content" style="<?php echo $step !== 2 ? 'display:none;' : ''; ?>">
            <div class="editor-toolbar">
                <div class="toolbar-left">
                    <button type="button" class="button prev-step" data-prev="1">&larr; <?php _e('Back', 'azure-plugin'); ?></button>
                </div>
                <div class="toolbar-center">
                    <div class="device-buttons">
                        <button type="button" class="device-btn active" data-device="desktop" title="<?php _e('Desktop', 'azure-plugin'); ?>">
                            <span class="dashicons dashicons-desktop"></span>
                        </button>
                        <button type="button" class="device-btn" data-device="tablet" title="<?php _e('Tablet', 'azure-plugin'); ?>">
                            <span class="dashicons dashicons-tablet"></span>
                        </button>
                        <button type="button" class="device-btn" data-device="mobile" title="<?php _e('Mobile', 'azure-plugin'); ?>">
                            <span class="dashicons dashicons-smartphone"></span>
                        </button>
                    </div>
                </div>
                <div class="toolbar-right">
                    <button type="button" class="button" id="btn-undo" title="<?php _e('Undo', 'azure-plugin'); ?>">
                        <span class="dashicons dashicons-undo"></span>
                    </button>
                    <button type="button" class="button" id="btn-redo" title="<?php _e('Redo', 'azure-plugin'); ?>">
                        <span class="dashicons dashicons-redo"></span>
                    </button>
                    <button type="button" class="button" id="btn-code" title="<?php _e('View Code', 'azure-plugin'); ?>">
                        <span class="dashicons dashicons-editor-code"></span>
                    </button>
                    <button type="button" class="button button-primary next-step" data-next="3">
                        <?php _e('Review', 'azure-plugin'); ?> &rarr;
                    </button>
                </div>
            </div>
            
            <div class="editor-container">
                <!-- LEFT SIDEBAR: Blocks & Layers -->
                <div class="editor-sidebar editor-sidebar-left">
                    <div class="sidebar-tabs">
                        <button type="button" class="sidebar-tab active" data-panel="blocks"><?php _e('Blocks', 'azure-plugin'); ?></button>
                        <button type="button" class="sidebar-tab" data-panel="layers"><?php _e('Layers', 'azure-plugin'); ?></button>
                    </div>
                    <div id="blocks-panel" class="sidebar-panel"></div>
                    <div id="layers-panel" class="sidebar-panel" style="display:none;"></div>
                </div>
                
                <!-- MAIN CANVAS -->
                <div class="editor-main">
                    <div id="gjs-editor"></div>
                </div>
                
                <!-- RIGHT SIDEBAR: Settings & Styles -->
                <div class="editor-sidebar editor-sidebar-right">
                    <div class="sidebar-tabs">
                        <button type="button" class="sidebar-tab active" data-panel="settings"><?php _e('Settings', 'azure-plugin'); ?></button>
                        <button type="button" class="sidebar-tab" data-panel="styles"><?php _e('Styles', 'azure-plugin'); ?></button>
                    </div>
                    <div id="settings-panel" class="sidebar-panel">
                        <div class="settings-placeholder">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <p><?php _e('Select an element to see its settings', 'azure-plugin'); ?></p>
                        </div>
                        <div id="traits-container"></div>
                    </div>
                    <div id="styles-panel" class="sidebar-panel" style="display:none;">
                        <div class="selected-element-indicator" id="selected-element-name">
                            <span class="dashicons dashicons-info-outline"></span>
                            <span class="element-name"><?php _e('No element selected', 'azure-plugin'); ?></span>
                        </div>
                        <div id="styles-container"></div>
                    </div>
                </div>
            </div>
            
            <input type="hidden" id="newsletter_content_html" name="newsletter_content_html" value="">
            <input type="hidden" id="newsletter_content_json" name="newsletter_content_json" value="">
        </div>
        
        <!-- Step 3: Review & Test -->
        <div class="step-content" id="step-3-content" style="<?php echo $step !== 3 ? 'display:none;' : ''; ?>">
            <div class="step-panel">
                <h2><?php _e('Review & Test', 'azure-plugin'); ?></h2>
                
                <div class="review-grid">
                    <div class="review-preview">
                        <h3><?php _e('Preview', 'azure-plugin'); ?></h3>
                        <div class="preview-device-toggle">
                            <button type="button" class="preview-device active" data-device="desktop"><?php _e('Desktop', 'azure-plugin'); ?></button>
                            <button type="button" class="preview-device" data-device="mobile"><?php _e('Mobile', 'azure-plugin'); ?></button>
                        </div>
                        <iframe id="preview-frame" class="preview-frame"></iframe>
                    </div>
                    
                    <div class="review-sidebar">
                        <!-- Summary -->
                        <div class="review-section">
                            <h4><?php _e('Summary', 'azure-plugin'); ?></h4>
                            <table class="summary-table">
                                <tr>
                                    <td><?php _e('Subject:', 'azure-plugin'); ?></td>
                                    <td id="summary-subject"></td>
                                </tr>
                                <tr>
                                    <td><?php _e('From:', 'azure-plugin'); ?></td>
                                    <td id="summary-from"></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Recipients:', 'azure-plugin'); ?></td>
                                    <td id="summary-recipients"></td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Spam Score -->
                        <div class="review-section">
                            <h4><?php _e('Spam Score', 'azure-plugin'); ?></h4>
                            <div id="spam-score-container">
                                <button type="button" class="button" id="check-spam-score">
                                    <?php _e('Check Spam Score', 'azure-plugin'); ?>
                                </button>
                                <div id="spam-score-result" style="display:none;"></div>
                            </div>
                        </div>
                        
                        <!-- Accessibility -->
                        <div class="review-section">
                            <h4><?php _e('Accessibility', 'azure-plugin'); ?></h4>
                            <div id="accessibility-container">
                                <button type="button" class="button" id="check-accessibility">
                                    <?php _e('Check Accessibility', 'azure-plugin'); ?>
                                </button>
                                <div id="accessibility-result" style="display:none;"></div>
                            </div>
                        </div>
                        
                        <!-- Test Send -->
                        <div class="review-section">
                            <h4><?php _e('Send Test Email', 'azure-plugin'); ?></h4>
                            <div class="test-send-form">
                                <input type="email" id="test_email" value="<?php echo esc_attr($current_user->user_email); ?>" class="regular-text">
                                <button type="button" class="button" id="send-test-email">
                                    <?php _e('Send Test', 'azure-plugin'); ?>
                                </button>
                            </div>
                            <div id="test-send-result" style="display:none;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="step-actions">
                    <button type="button" class="button prev-step" data-prev="2">&larr; <?php _e('Back to Editor', 'azure-plugin'); ?></button>
                    <button type="button" class="button button-primary next-step" data-next="4">
                        <?php _e('Schedule / Send', 'azure-plugin'); ?> &rarr;
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Step 4: Schedule & Send -->
        <div class="step-content" id="step-4-content" style="<?php echo $step !== 4 ? 'display:none;' : ''; ?>">
            <div class="step-panel">
                <h2><?php _e('Schedule & Send', 'azure-plugin'); ?></h2>
                
                <div class="send-options">
                    <div class="send-option">
                        <label>
                            <input type="radio" name="send_option" value="now" checked>
                            <span class="option-card">
                                <span class="dashicons dashicons-controls-play"></span>
                                <strong><?php _e('Send Now', 'azure-plugin'); ?></strong>
                                <span><?php _e('Start sending immediately', 'azure-plugin'); ?></span>
                            </span>
                        </label>
                    </div>
                    
                    <div class="send-option">
                        <label>
                            <input type="radio" name="send_option" value="schedule">
                            <span class="option-card">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <strong><?php _e('Schedule', 'azure-plugin'); ?></strong>
                                <span><?php _e('Send at a specific time', 'azure-plugin'); ?></span>
                            </span>
                        </label>
                    </div>
                    
                    <div class="send-option">
                        <label>
                            <input type="radio" name="send_option" value="draft">
                            <span class="option-card">
                                <span class="dashicons dashicons-edit"></span>
                                <strong><?php _e('Save as Draft', 'azure-plugin'); ?></strong>
                                <span><?php _e('Continue editing later', 'azure-plugin'); ?></span>
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="schedule-options" id="schedule-options" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th><label><?php _e('Date & Time (PST)', 'azure-plugin'); ?></label></th>
                            <td>
                                <input type="date" id="schedule_date" name="schedule_date" 
                                       min="<?php echo date('Y-m-d'); ?>">
                                <input type="time" id="schedule_time" name="schedule_time" value="09:00">
                                <p class="description"><?php _e('Pacific Standard Time (PST)', 'azure-plugin'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Create WordPress Page Option -->
                <div class="page-options">
                    <h4><?php _e('Archive Page Options', 'azure-plugin'); ?></h4>
                    <label>
                        <input type="checkbox" name="create_wp_page" id="create_wp_page" value="1">
                        <?php _e('Create a WordPress page for this newsletter (view in browser)', 'azure-plugin'); ?>
                    </label>
                    
                    <div class="page-settings" id="page-settings" style="display:none;">
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Page Category/Tag', 'azure-plugin'); ?></label></th>
                                <td>
                                    <input type="text" name="page_category" id="page_category" class="regular-text"
                                           value="<?php echo esc_attr($settings['newsletter_default_category'] ?? 'newsletter'); ?>">
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="step-actions">
                    <button type="button" class="button prev-step" data-prev="3">&larr; <?php _e('Back', 'azure-plugin'); ?></button>
                    <button type="submit" name="save_newsletter" class="button" id="save-draft-btn">
                        <?php _e('Save Draft', 'azure-plugin'); ?>
                    </button>
                    <button type="submit" name="send_newsletter" class="button button-primary button-hero" id="final-send-btn">
                        <span class="dashicons dashicons-email"></span>
                        <?php _e('Send Newsletter', 'azure-plugin'); ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
var newsletterEditorConfig = {
    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('azure_newsletter_nonce'); ?>',
    pluginUrl: '<?php echo AZURE_PLUGIN_URL; ?>',
    initialContent: <?php echo json_encode($newsletter->content_json ?? ($template ? $template->content_json : '') ?? ''); ?>,
    initialHtml: <?php echo json_encode($newsletter->content_html ?? ($template ? $template->content_html : '') ?? ''); ?>,
    templateId: <?php echo $template_id; ?>,
    templateName: <?php echo json_encode($template ? $template->name : ''); ?>
};
</script>




