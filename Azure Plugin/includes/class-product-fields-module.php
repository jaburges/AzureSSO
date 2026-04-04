<?php
/**
 * Product Fields Module
 *
 * Reusable custom fields for WooCommerce products, assigned by category.
 * Field values persist to user profiles for auto-population on repeat purchases.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Product_Fields_Module {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        $this->init_hooks();

        if (class_exists('Azure_Logger')) {
            Azure_Logger::debug_module('ProductFields', 'Product Fields module initialized');
        }
    }

    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p><strong>' . esc_html__('Product Fields Module:', 'azure-plugin') . '</strong> ' . esc_html__('WooCommerce is required.', 'azure-plugin') . '</p></div>';
    }

    private function init_hooks() {
        // Admin AJAX handlers
        add_action('wp_ajax_azure_pf_save_group', array($this, 'ajax_save_group'));
        add_action('wp_ajax_azure_pf_delete_group', array($this, 'ajax_delete_group'));
        add_action('wp_ajax_azure_pf_get_group', array($this, 'ajax_get_group'));
        add_action('wp_ajax_azure_pf_save_field', array($this, 'ajax_save_field'));
        add_action('wp_ajax_azure_pf_delete_field', array($this, 'ajax_delete_field'));
        add_action('wp_ajax_azure_pf_reorder_fields', array($this, 'ajax_reorder_fields'));

        // Frontend: render fields on product page
        add_action('woocommerce_before_add_to_cart_button', array($this, 'render_product_fields'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Cart: carry field data through
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);

        // Validation
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_fields'), 10, 3);

        // Order: save to line item meta
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_order_item_meta'), 10, 4);

        // Save to user profile on order completion
        add_action('woocommerce_order_status_completed', array($this, 'save_to_user_profile'));
        add_action('woocommerce_payment_complete', array($this, 'save_to_user_profile'));
    }

    // ─── Helper: get field groups for a product ────────────────────────

    public static function get_groups_for_product($product_id) {
        global $wpdb;

        $cat_table = Azure_Database::get_table_name('product_field_categories');
        $grp_table = Azure_Database::get_table_name('product_field_groups');
        $fld_table = Azure_Database::get_table_name('product_fields');

        if (!$cat_table || !$grp_table || !$fld_table) {
            return array();
        }

        $terms = wc_get_product_term_ids($product_id, 'product_cat');
        if (empty($terms)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($terms), '%d'));

        $groups = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT g.* FROM {$grp_table} g
             INNER JOIN {$cat_table} c ON g.id = c.group_id
             WHERE c.term_id IN ({$placeholders}) AND g.is_active = 1
             ORDER BY g.sort_order ASC",
            ...$terms
        ));

        foreach ($groups as &$group) {
            $group->fields = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$fld_table} WHERE group_id = %d ORDER BY sort_order ASC",
                $group->id
            ));
        }

        return $groups;
    }

    // ─── Frontend: render fields ───────────────────────────────────────

    public function render_product_fields() {
        global $product;
        if (!$product instanceof WC_Product) {
            return;
        }

        $groups = self::get_groups_for_product($product->get_id());
        if (empty($groups)) {
            return;
        }

        $user_id = get_current_user_id();
        $children = array();
        if ($user_id && class_exists('Azure_User_Children')) {
            $children = Azure_User_Children::get_children_for_user($user_id);
        }

        echo '<div class="azure-product-fields">';

        if (!empty($children)) {
            $children_data = array();
            foreach ($children as $child) {
                $children_data[] = array(
                    'id' => $child->id,
                    'name' => $child->child_name,
                    'meta' => Azure_User_Children::get_child_meta($child->id),
                );
            }

            echo '<div class="azure-pf-child-selector">';
            echo '<label for="azure-pf-select-child">' . esc_html__('Select Child', 'azure-plugin') . '</label>';
            echo '<select id="azure-pf-select-child">';
            echo '<option value="">' . esc_html__('-- Fill in manually --', 'azure-plugin') . '</option>';
            foreach ($children as $child) {
                echo '<option value="' . esc_attr($child->id) . '">' . esc_html($child->child_name) . '</option>';
            }
            echo '</select>';
            echo '</div>';

            echo '<script>var azureChildProfiles = ' . wp_json_encode($children_data) . ';</script>';
        }

        foreach ($groups as $group) {
            if (empty($group->fields)) {
                continue;
            }
            echo '<div class="azure-pf-group" data-group-id="' . esc_attr($group->id) . '">';
            if (!empty($group->name)) {
                echo '<h4 class="azure-pf-group-title">' . esc_html($group->name) . '</h4>';
            }
            foreach ($group->fields as $field) {
                $this->render_single_field($field, $user_id);
            }
            echo '</div>';
        }
        echo '</div>';
    }

    private function render_single_field($field, $user_id) {
        $value = '';
        if ($user_id && $field->save_to_profile && !empty($field->user_meta_key)) {
            $value = get_user_meta($user_id, $field->user_meta_key, true);
        }

        $name = 'azure_pf_' . $field->id;
        $required = $field->required ? ' required' : '';
        $req_star = $field->required ? ' <span class="required">*</span>' : '';

        echo '<p class="form-row azure-pf-field azure-pf-field-' . esc_attr($field->field_type) . '">';
        echo '<label for="' . esc_attr($name) . '">' . esc_html($field->label) . $req_star . '</label>';

        switch ($field->field_type) {
            case 'textarea':
                echo '<textarea name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" placeholder="' . esc_attr($field->placeholder) . '"' . $required . '>' . esc_textarea($value) . '</textarea>';
                break;

            case 'select':
                $options = json_decode($field->options_json, true) ?: array();
                echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '"' . $required . '>';
                echo '<option value="">' . esc_html($field->placeholder ?: '-- Select --') . '</option>';
                foreach ($options as $opt) {
                    $selected = ($value === $opt) ? ' selected' : '';
                    echo '<option value="' . esc_attr($opt) . '"' . $selected . '>' . esc_html($opt) . '</option>';
                }
                echo '</select>';
                break;

            case 'checkbox':
                $checked = $value ? ' checked' : '';
                echo '<label class="azure-pf-checkbox-label"><input type="checkbox" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="1"' . $checked . ' /> ' . esc_html($field->placeholder ?: $field->label) . '</label>';
                break;

            case 'number':
                echo '<input type="number" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($field->placeholder) . '"' . $required . ' />';
                break;

            default: // text, email, tel, etc.
                echo '<input type="' . esc_attr($field->field_type) . '" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($field->placeholder) . '"' . $required . ' />';
                break;
        }

        echo '</p>';
    }

    public function enqueue_frontend_assets() {
        if (!is_product()) {
            return;
        }

        global $product;
        if (!$product instanceof WC_Product) {
            return;
        }

        $groups = self::get_groups_for_product($product->get_id());
        if (empty($groups)) {
            return;
        }

        wp_enqueue_style(
            'azure-product-fields',
            AZURE_PLUGIN_URL . 'css/product-fields-frontend.css',
            array(),
            AZURE_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'azure-product-fields',
            AZURE_PLUGIN_URL . 'js/product-fields-frontend.js',
            array('jquery'),
            AZURE_PLUGIN_VERSION,
            true
        );
    }

    // ─── Validation ────────────────────────────────────────────────────

    public function validate_fields($passed, $product_id, $quantity) {
        $groups = self::get_groups_for_product($product_id);

        foreach ($groups as $group) {
            foreach ($group->fields as $field) {
                if (!$field->required) {
                    continue;
                }
                $key = 'azure_pf_' . $field->id;
                $val = isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : '';
                if ($val === '') {
                    wc_add_notice(sprintf(__('"%s" is a required field.', 'azure-plugin'), $field->label), 'error');
                    $passed = false;
                }
            }
        }

        return $passed;
    }

    // ─── Cart ──────────────────────────────────────────────────────────

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        $groups = self::get_groups_for_product($product_id);
        $field_values = array();

        foreach ($groups as $group) {
            foreach ($group->fields as $field) {
                $key = 'azure_pf_' . $field->id;
                if (isset($_POST[$key])) {
                    $field_values[$field->id] = array(
                        'label'           => $field->label,
                        'value'           => sanitize_text_field($_POST[$key]),
                        'save_to_profile' => (bool) $field->save_to_profile,
                        'user_meta_key'   => $field->user_meta_key,
                    );
                }
            }
        }

        if (!empty($field_values)) {
            $cart_item_data['azure_product_fields'] = $field_values;
        }

        return $cart_item_data;
    }

    public function display_cart_item_data($item_data, $cart_item) {
        if (empty($cart_item['azure_product_fields'])) {
            return $item_data;
        }

        foreach ($cart_item['azure_product_fields'] as $field) {
            if ($field['value'] === '') {
                continue;
            }
            $item_data[] = array(
                'key'   => $field['label'],
                'value' => $field['value'],
            );
        }

        return $item_data;
    }

    // ─── Order line item meta ──────────────────────────────────────────

    public function save_order_item_meta($item, $cart_item_key, $values, $order) {
        if (empty($values['azure_product_fields'])) {
            return;
        }

        foreach ($values['azure_product_fields'] as $field) {
            if ($field['value'] === '') {
                continue;
            }
            $item->update_meta_data($field['label'], $field['value']);
        }

        $item->update_meta_data('_azure_product_fields_raw', $values['azure_product_fields']);
    }

    // ─── Save to user profile on order completion ──────────────────────

    public function save_to_user_profile($order_id) {
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

            foreach ($raw as $field) {
                if (!$field['save_to_profile'] || empty($field['user_meta_key']) || $field['value'] === '') {
                    continue;
                }
                update_user_meta($user_id, $field['user_meta_key'], $field['value']);
            }
        }
    }

    // ─── Admin AJAX: Field Groups ──────────────────────────────────────

    public function ajax_save_group() {
        check_ajax_referer('azure_plugin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = Azure_Database::get_table_name('product_field_groups');
        $cat_table = Azure_Database::get_table_name('product_field_categories');

        if (!$table || $wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            Azure_Database::create_tables();
            $table = Azure_Database::get_table_name('product_field_groups');
            $cat_table = Azure_Database::get_table_name('product_field_categories');
            if (!$table || $wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
                wp_send_json_error('Database tables could not be created. Check error logs.');
            }
        }

        $id          = intval($_POST['id'] ?? 0);
        $name        = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $is_active   = intval($_POST['is_active'] ?? 1);
        $categories  = array_map('intval', (array)($_POST['categories'] ?? array()));

        if (empty($name)) {
            wp_send_json_error('Group name is required');
        }

        $data = array(
            'name'        => $name,
            'description' => $description,
            'is_active'   => $is_active,
            'updated_at'  => current_time('mysql'),
        );

        if ($id > 0) {
            $result = $wpdb->update($table, $data, array('id' => $id));
            if ($result === false) {
                wp_send_json_error('DB update failed: ' . $wpdb->last_error);
            }
        } else {
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($table, $data);
            if ($result === false) {
                wp_send_json_error('DB insert failed: ' . $wpdb->last_error);
            }
            $id = $wpdb->insert_id;
        }

        $wpdb->delete($cat_table, array('group_id' => $id));
        foreach ($categories as $term_id) {
            $wpdb->insert($cat_table, array('group_id' => $id, 'term_id' => $term_id));
        }

        wp_send_json_success(array('id' => $id, 'message' => 'Group saved'));
    }

    public function ajax_delete_group() {
        check_ajax_referer('azure_plugin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error('Invalid group ID');
        }

        $grp_table = Azure_Database::get_table_name('product_field_groups');
        $fld_table = Azure_Database::get_table_name('product_fields');
        $cat_table = Azure_Database::get_table_name('product_field_categories');

        $wpdb->delete($fld_table, array('group_id' => $id));
        $wpdb->delete($cat_table, array('group_id' => $id));
        $wpdb->delete($grp_table, array('id' => $id));

        wp_send_json_success('Group deleted');
    }

    public function ajax_get_group() {
        check_ajax_referer('azure_plugin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);

        $grp_table = Azure_Database::get_table_name('product_field_groups');
        $fld_table = Azure_Database::get_table_name('product_fields');
        $cat_table = Azure_Database::get_table_name('product_field_categories');

        $group = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$grp_table} WHERE id = %d", $id));
        if (!$group) {
            wp_send_json_error('Group not found');
        }

        $group->fields = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$fld_table} WHERE group_id = %d ORDER BY sort_order ASC", $id
        ));

        $group->categories = $wpdb->get_col($wpdb->prepare(
            "SELECT term_id FROM {$cat_table} WHERE group_id = %d", $id
        ));

        wp_send_json_success($group);
    }

    // ─── Admin AJAX: Fields ────────────────────────────────────────────

    public function ajax_save_field() {
        check_ajax_referer('azure_plugin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = Azure_Database::get_table_name('product_fields');

        $id              = intval($_POST['id'] ?? 0);
        $group_id        = intval($_POST['group_id'] ?? 0);
        $label           = sanitize_text_field($_POST['label'] ?? '');
        $field_type      = sanitize_text_field($_POST['field_type'] ?? 'text');
        $placeholder     = sanitize_text_field($_POST['placeholder'] ?? '');
        $required        = intval($_POST['required'] ?? 0);
        $save_to_profile = intval($_POST['save_to_profile'] ?? 0);
        $user_meta_key   = sanitize_key($_POST['user_meta_key'] ?? '');
        $options_json    = '';

        if (empty($label) || !$group_id) {
            wp_send_json_error('Label and group are required');
        }

        $valid_types = array('text', 'email', 'tel', 'number', 'textarea', 'select', 'checkbox');
        if (!in_array($field_type, $valid_types)) {
            $field_type = 'text';
        }

        if ($field_type === 'select' && !empty($_POST['options'])) {
            $options = array_filter(array_map('trim', explode("\n", sanitize_textarea_field($_POST['options']))));
            $options_json = wp_json_encode(array_values($options));
        }

        if ($save_to_profile && empty($user_meta_key)) {
            $user_meta_key = 'azure_pf_' . sanitize_key($label);
        }

        $data = array(
            'group_id'        => $group_id,
            'label'           => $label,
            'field_type'      => $field_type,
            'placeholder'     => $placeholder,
            'options_json'    => $options_json,
            'required'        => $required,
            'save_to_profile' => $save_to_profile,
            'user_meta_key'   => $user_meta_key,
        );

        if ($id > 0) {
            $wpdb->update($table, $data, array('id' => $id));
        } else {
            $max_order = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(sort_order) FROM {$table} WHERE group_id = %d", $group_id
            ));
            $data['sort_order'] = $max_order + 1;
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
            $id = $wpdb->insert_id;
        }

        wp_send_json_success(array('id' => $id, 'message' => 'Field saved'));
    }

    public function ajax_delete_field() {
        check_ajax_referer('azure_plugin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = Azure_Database::get_table_name('product_fields');
        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error('Invalid field ID');
        }

        $wpdb->delete($table, array('id' => $id));
        wp_send_json_success('Field deleted');
    }

    public function ajax_reorder_fields() {
        check_ajax_referer('azure_plugin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = Azure_Database::get_table_name('product_fields');
        $order = (array)($_POST['order'] ?? array());

        foreach ($order as $position => $field_id) {
            $wpdb->update($table, array('sort_order' => (int) $position), array('id' => (int) $field_id));
        }

        wp_send_json_success('Order updated');
    }
}
