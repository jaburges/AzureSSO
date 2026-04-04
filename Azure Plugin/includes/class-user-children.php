<?php
/**
 * User Children Profiles
 *
 * Allows parents to maintain child profiles that auto-populate
 * Product Fields at checkout. Children are saved/updated automatically
 * from completed orders and can be managed via My Account.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_User_Children {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        $this->init_hooks();
    }

    private function init_hooks() {
        // My Account tab
        add_filter('woocommerce_account_menu_items', array($this, 'add_my_account_tab'));
        add_action('woocommerce_account_my-children_endpoint', array($this, 'render_my_account_page'));
        add_action('init', array($this, 'register_endpoint'));

        // AJAX handlers (logged-in users)
        add_action('wp_ajax_azure_uc_get_children', array($this, 'ajax_get_children'));
        add_action('wp_ajax_azure_uc_save_child', array($this, 'ajax_save_child'));
        add_action('wp_ajax_azure_uc_delete_child', array($this, 'ajax_delete_child'));
        add_action('wp_ajax_azure_uc_get_child_meta', array($this, 'ajax_get_child_meta'));

        // Frontend assets on product pages and My Account
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Auto-save child from completed orders
        add_action('woocommerce_order_status_completed', array($this, 'auto_save_from_order'), 20);
        add_action('woocommerce_payment_complete', array($this, 'auto_save_from_order'), 20);
    }

    public function register_endpoint() {
        add_rewrite_endpoint('my-children', EP_ROOT | EP_PAGES);
    }

    // ─── Data Access ───────────────────────────────────────────────────

    public static function get_children_for_user($user_id) {
        global $wpdb;
        $table = Azure_Database::get_table_name('user_children');
        if (!$table) {
            return array();
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND is_active = 1 ORDER BY child_name ASC",
            $user_id
        ));
    }

    public static function get_child($child_id, $user_id = null) {
        global $wpdb;
        $table = Azure_Database::get_table_name('user_children');
        if (!$table) {
            return null;
        }
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $child_id);
        if ($user_id) {
            $sql .= $wpdb->prepare(" AND user_id = %d", $user_id);
        }
        return $wpdb->get_row($sql);
    }

    public static function get_child_meta($child_id) {
        global $wpdb;
        $table = Azure_Database::get_table_name('user_children_meta');
        if (!$table) {
            return array();
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$table} WHERE child_id = %d",
            $child_id
        ));
        $meta = array();
        foreach ($rows as $row) {
            $meta[$row->meta_key] = $row->meta_value;
        }
        return $meta;
    }

    public static function save_child($user_id, $data) {
        global $wpdb;
        $table = Azure_Database::get_table_name('user_children');
        if (!$table) {
            return false;
        }

        $id = intval($data['id'] ?? 0);
        $child_name = sanitize_text_field($data['child_name'] ?? '');

        if (empty($child_name)) {
            return false;
        }

        if ($id > 0) {
            $existing = self::get_child($id, $user_id);
            if (!$existing) {
                return false;
            }
            $wpdb->update($table, array(
                'child_name' => $child_name,
                'date_of_birth' => !empty($data['date_of_birth']) ? sanitize_text_field($data['date_of_birth']) : null,
                'updated_at' => current_time('mysql'),
            ), array('id' => $id));
        } else {
            $wpdb->insert($table, array(
                'user_id' => $user_id,
                'child_name' => $child_name,
                'date_of_birth' => !empty($data['date_of_birth']) ? sanitize_text_field($data['date_of_birth']) : null,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ));
            $id = $wpdb->insert_id;
        }

        if ($id && !empty($data['meta']) && is_array($data['meta'])) {
            self::update_child_meta($id, $data['meta']);
        }

        return $id;
    }

    public static function update_child_meta($child_id, $meta_array) {
        global $wpdb;
        $table = Azure_Database::get_table_name('user_children_meta');
        if (!$table) {
            return;
        }

        foreach ($meta_array as $key => $value) {
            $key = sanitize_text_field($key);
            $value = sanitize_text_field($value);

            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE child_id = %d AND meta_key = %s",
                $child_id, $key
            ));

            if ($existing) {
                $wpdb->update($table, array('meta_value' => $value), array('id' => $existing));
            } else {
                $wpdb->insert($table, array(
                    'child_id' => $child_id,
                    'meta_key' => $key,
                    'meta_value' => $value,
                ));
            }
        }
    }

    public static function delete_child($child_id, $user_id) {
        global $wpdb;
        $table = Azure_Database::get_table_name('user_children');
        $meta_table = Azure_Database::get_table_name('user_children_meta');

        $existing = self::get_child($child_id, $user_id);
        if (!$existing) {
            return false;
        }

        $wpdb->delete($meta_table, array('child_id' => $child_id));
        $wpdb->delete($table, array('id' => $child_id));
        return true;
    }

    /**
     * Find an existing child by name for a user, or return null.
     */
    public static function find_child_by_name($user_id, $child_name) {
        global $wpdb;
        $table = Azure_Database::get_table_name('user_children');
        if (!$table) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND child_name = %s AND is_active = 1",
            $user_id, $child_name
        ));
    }

    // ─── Auto-save from completed orders ───────────────────────────────

    public function auto_save_from_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }

        foreach ($order->get_items() as $item) {
            $raw = $item->get_meta('_azure_product_fields_raw', true);
            if (empty($raw) || !is_array($raw)) {
                continue;
            }

            $child_name = '';
            $meta = array();

            foreach ($raw as $field) {
                $label_lower = strtolower($field['label']);
                if (strpos($label_lower, 'child') !== false && strpos($label_lower, 'name') !== false) {
                    $child_name = $field['value'];
                }
                if ($field['value'] !== '') {
                    $meta[$field['label']] = $field['value'];
                }
            }

            if (empty($child_name)) {
                continue;
            }

            $existing = self::find_child_by_name($user_id, $child_name);
            if ($existing) {
                self::update_child_meta($existing->id, $meta);
            } else {
                self::save_child($user_id, array(
                    'child_name' => $child_name,
                    'meta' => $meta,
                ));
            }
        }
    }

    // ─── My Account Tab ────────────────────────────────────────────────

    public function add_my_account_tab($items) {
        $new_items = array();
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            if ($key === 'orders') {
                $new_items['my-children'] = __('My Children', 'azure-plugin');
            }
        }
        return $new_items;
    }

    public function render_my_account_page() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $children = self::get_children_for_user($user_id);
        ?>
        <div class="azure-my-children-page">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;"><?php _e('My Children', 'azure-plugin'); ?></h3>
                <button type="button" class="button azure-uc-add-child"><?php _e('Add Child', 'azure-plugin'); ?></button>
            </div>

            <?php if (empty($children)): ?>
            <p class="azure-uc-empty"><?php _e('No children added yet. Add a child profile to speed up checkout for enrichment programs and events.', 'azure-plugin'); ?></p>
            <?php endif; ?>

            <div class="azure-uc-children-list">
                <?php foreach ($children as $child): ?>
                    <?php $meta = self::get_child_meta($child->id); ?>
                    <div class="azure-uc-child-card" data-child-id="<?php echo esc_attr($child->id); ?>">
                        <div class="azure-uc-child-header">
                            <strong><?php echo esc_html($child->child_name); ?></strong>
                            <span class="azure-uc-child-actions">
                                <a href="#" class="azure-uc-edit-child" data-id="<?php echo esc_attr($child->id); ?>"><?php _e('Edit', 'azure-plugin'); ?></a>
                                <a href="#" class="azure-uc-delete-child" data-id="<?php echo esc_attr($child->id); ?>" style="color: #dc3545;"><?php _e('Remove', 'azure-plugin'); ?></a>
                            </span>
                        </div>
                        <?php if (!empty($meta)): ?>
                        <div class="azure-uc-child-meta">
                            <?php foreach ($meta as $key => $value): ?>
                                <div class="azure-uc-meta-row">
                                    <span class="azure-uc-meta-label"><?php echo esc_html($key); ?>:</span>
                                    <span class="azure-uc-meta-value"><?php echo esc_html($value); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Add/Edit Child Modal -->
        <div id="azure-uc-child-modal" style="display:none;">
            <div class="azure-uc-modal-overlay"></div>
            <div class="azure-uc-modal-content">
                <h3 id="azure-uc-modal-title"><?php _e('Add Child', 'azure-plugin'); ?></h3>
                <form id="azure-uc-child-form">
                    <input type="hidden" name="child_id" id="azure-uc-child-id" value="0" />
                    <p>
                        <label for="azure-uc-child-name"><?php _e('Child\'s Name', 'azure-plugin'); ?> <span class="required">*</span></label>
                        <input type="text" id="azure-uc-child-name" name="child_name" required />
                    </p>
                    <div id="azure-uc-meta-fields">
                        <!-- Dynamic meta fields will be loaded here -->
                    </div>
                    <p class="azure-uc-modal-actions">
                        <button type="submit" class="button button-primary"><?php _e('Save', 'azure-plugin'); ?></button>
                        <button type="button" class="button azure-uc-modal-close"><?php _e('Cancel', 'azure-plugin'); ?></button>
                    </p>
                </form>
            </div>
        </div>

        <script>
        jQuery(function($) {
            var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            var nonce = '<?php echo esc_js(wp_create_nonce('azure_uc_nonce')); ?>';

            var defaultMetaFields = <?php echo wp_json_encode(self::get_common_meta_fields()); ?>;

            function openModal(id, name, meta) {
                $('#azure-uc-child-id').val(id || 0);
                $('#azure-uc-child-name').val(name || '');
                $('#azure-uc-modal-title').text(id ? '<?php echo esc_js(__('Edit Child', 'azure-plugin')); ?>' : '<?php echo esc_js(__('Add Child', 'azure-plugin')); ?>');

                var $metaContainer = $('#azure-uc-meta-fields').empty();
                meta = meta || {};

                $.each(defaultMetaFields, function(i, field) {
                    var val = meta[field.key] || '';
                    var html = '<p><label>' + field.label + '</label>';
                    if (field.type === 'select' && field.options) {
                        html += '<select name="meta[' + field.key + ']">';
                        html += '<option value="">-- Select --</option>';
                        $.each(field.options, function(j, opt) {
                            html += '<option value="' + opt + '"' + (val === opt ? ' selected' : '') + '>' + opt + '</option>';
                        });
                        html += '</select>';
                    } else if (field.type === 'checkbox') {
                        html += '<label class="azure-pf-checkbox-label"><input type="checkbox" name="meta[' + field.key + ']" value="Yes"' + (val === 'Yes' ? ' checked' : '') + ' /> Yes</label>';
                    } else {
                        html += '<input type="' + (field.type || 'text') + '" name="meta[' + field.key + ']" value="' + $('<span>').text(val).html() + '" />';
                    }
                    html += '</p>';
                    $metaContainer.append(html);
                });

                $('#azure-uc-child-modal').show();
            }

            function closeModal() {
                $('#azure-uc-child-modal').hide();
            }

            $('.azure-uc-add-child').on('click', function() { openModal(); });
            $(document).on('click', '.azure-uc-modal-close, .azure-uc-modal-overlay', closeModal);

            $(document).on('click', '.azure-uc-edit-child', function(e) {
                e.preventDefault();
                var childId = $(this).data('id');
                $.post(ajaxUrl, { action: 'azure_uc_get_child_meta', nonce: nonce, child_id: childId }, function(res) {
                    if (res.success) {
                        openModal(res.data.id, res.data.child_name, res.data.meta);
                    }
                });
            });

            $(document).on('click', '.azure-uc-delete-child', function(e) {
                e.preventDefault();
                if (!confirm('<?php echo esc_js(__('Remove this child profile?', 'azure-plugin')); ?>')) return;
                var childId = $(this).data('id');
                $.post(ajaxUrl, { action: 'azure_uc_delete_child', nonce: nonce, child_id: childId }, function(res) {
                    if (res.success) location.reload();
                });
            });

            $('#azure-uc-child-form').on('submit', function(e) {
                e.preventDefault();
                var formData = $(this).serializeArray();
                var postData = { action: 'azure_uc_save_child', nonce: nonce };
                $.each(formData, function(i, field) { postData[field.name] = field.value; });

                $.post(ajaxUrl, postData, function(res) {
                    if (res.success) location.reload();
                    else alert(res.data || 'Error saving child');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Returns common meta field definitions for child profiles,
     * derived from product field groups that have enrichment-style fields.
     */
    public static function get_common_meta_fields() {
        global $wpdb;
        $fld_table = Azure_Database::get_table_name('product_fields');
        if (!$fld_table) {
            return self::get_default_meta_fields();
        }

        $fields = $wpdb->get_results(
            "SELECT DISTINCT label, field_type, options_json FROM {$fld_table} ORDER BY sort_order ASC"
        );

        if (empty($fields)) {
            return self::get_default_meta_fields();
        }

        $meta_fields = array();
        $seen = array();
        foreach ($fields as $f) {
            $label_lower = strtolower($f->label);
            if (isset($seen[$label_lower])) {
                continue;
            }
            $seen[$label_lower] = true;

            $field_def = array(
                'key' => $f->label,
                'label' => $f->label,
                'type' => $f->field_type,
            );

            if ($f->field_type === 'select' && !empty($f->options_json)) {
                $field_def['options'] = json_decode($f->options_json, true) ?: array();
            }

            $meta_fields[] = $field_def;
        }

        return $meta_fields;
    }

    private static function get_default_meta_fields() {
        return array(
            array('key' => 'Grade', 'label' => 'Grade', 'type' => 'text'),
            array('key' => 'Teacher', 'label' => 'Teacher', 'type' => 'text'),
            array('key' => 'Emergency Contact Name', 'label' => 'Emergency Contact Name', 'type' => 'text'),
            array('key' => 'Emergency Contact Phone', 'label' => 'Emergency Contact Phone', 'type' => 'tel'),
            array('key' => 'Emergency Contact Email', 'label' => 'Emergency Contact Email', 'type' => 'email'),
            array('key' => 'Allergies', 'label' => 'Allergies', 'type' => 'text'),
        );
    }

    // ─── AJAX Handlers ─────────────────────────────────────────────────

    public function ajax_get_children() {
        check_ajax_referer('azure_uc_nonce', 'nonce');
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Not logged in');
        }

        $children = self::get_children_for_user($user_id);
        $result = array();

        foreach ($children as $child) {
            $result[] = array(
                'id' => $child->id,
                'child_name' => $child->child_name,
                'meta' => self::get_child_meta($child->id),
            );
        }

        wp_send_json_success($result);
    }

    public function ajax_save_child() {
        check_ajax_referer('azure_uc_nonce', 'nonce');
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Not logged in');
        }

        $meta = array();
        if (!empty($_POST['meta']) && is_array($_POST['meta'])) {
            foreach ($_POST['meta'] as $key => $value) {
                $meta[sanitize_text_field($key)] = sanitize_text_field($value);
            }
        }

        $id = self::save_child($user_id, array(
            'id' => intval($_POST['child_id'] ?? 0),
            'child_name' => sanitize_text_field($_POST['child_name'] ?? ''),
            'meta' => $meta,
        ));

        if ($id) {
            wp_send_json_success(array('id' => $id));
        } else {
            wp_send_json_error('Could not save child profile');
        }
    }

    public function ajax_delete_child() {
        check_ajax_referer('azure_uc_nonce', 'nonce');
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Not logged in');
        }

        $child_id = intval($_POST['child_id'] ?? 0);
        if (self::delete_child($child_id, $user_id)) {
            wp_send_json_success('Deleted');
        } else {
            wp_send_json_error('Could not delete');
        }
    }

    public function ajax_get_child_meta() {
        check_ajax_referer('azure_uc_nonce', 'nonce');
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Not logged in');
        }

        $child_id = intval($_POST['child_id'] ?? 0);
        $child = self::get_child($child_id, $user_id);
        if (!$child) {
            wp_send_json_error('Child not found');
        }

        wp_send_json_success(array(
            'id' => $child->id,
            'child_name' => $child->child_name,
            'meta' => self::get_child_meta($child->id),
        ));
    }

    // ─── Frontend Assets ───────────────────────────────────────────────

    public function enqueue_assets() {
        if (is_account_page() || is_product()) {
            wp_enqueue_style(
                'azure-user-children',
                AZURE_PLUGIN_URL . 'css/user-children.css',
                array(),
                AZURE_PLUGIN_VERSION
            );
        }
    }
}
