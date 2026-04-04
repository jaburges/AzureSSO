<?php
/**
 * Auction Product Type - WooCommerce registration and admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Auction_Product_Type {

    public function __construct() {
        add_filter('product_type_selector', array($this, 'add_product_type'));
        add_filter('woocommerce_product_class', array($this, 'product_class'), 10, 2);
        add_filter('woocommerce_product_data_tabs', array($this, 'add_product_data_tabs'));
        add_action('woocommerce_product_data_panels', array($this, 'add_product_data_panels'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_meta'));
        add_action('admin_footer', array($this, 'product_type_js'));
    }

    public function add_product_type($types) {
        $types['auction'] = __('Auction', 'azure-plugin');
        return $types;
    }

    public function product_class($classname, $product_type) {
        if ($product_type === 'auction') {
            return 'WC_Product_Auction';
        }
        return $classname;
    }

    public function add_product_data_tabs($tabs) {
        $tabs['auction'] = array(
            'label'    => __('Auction', 'azure-plugin'),
            'target'   => 'auction_data',
            'class'    => array('show_if_auction'),
            'priority' => 25,
        );
        return $tabs;
    }

    public function add_product_data_panels() {
        global $post;
        if (!$post) {
            return;
        }
        $product_id = $post->ID;

        $starting_bid = get_post_meta($product_id, '_regular_price', true);
        $bidding_end = get_post_meta($product_id, '_auction_bidding_end', true);
        $end_date = '';
        $end_time = '';
        if ($bidding_end) {
            $ts = is_numeric($bidding_end) ? (int) $bidding_end : strtotime($bidding_end);
            $end_date = date('Y-m-d', $ts);
            $end_time = date('H:i', $ts);
        }
        $buy_it_now = get_post_meta($product_id, '_auction_buy_it_now_enabled', true);
        $buy_it_now_price = get_post_meta($product_id, '_auction_buy_it_now_price', true);
        $pay_immediately = get_post_meta($product_id, '_auction_buy_it_now_pay_immediately', true);
        ?>
        <div id="auction_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field _auction_starting_bid_field">
                    <label for="_auction_starting_bid"><?php _e('Starting Bid ($)', 'azure-plugin'); ?></label>
                    <input type="number" id="_auction_starting_bid" name="_auction_starting_bid" value="<?php echo esc_attr($starting_bid); ?>" min="0" step="0.01" style="width: 120px;" />
                    <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('The opening bid price. This is stored as the regular price.', 'azure-plugin'); ?>"></span>
                </p>
                <p class="form-field _auction_bidding_end_date_field">
                    <label for="_auction_bidding_end_date"><?php _e('Bidding End Date', 'azure-plugin'); ?></label>
                    <input type="date" id="_auction_bidding_end_date" name="_auction_bidding_end_date" value="<?php echo esc_attr($end_date); ?>" style="width: 160px;" />
                    <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Date when bidding closes.', 'azure-plugin'); ?>"></span>
                </p>
                <p class="form-field _auction_bidding_end_time_field">
                    <label for="_auction_bidding_end_time"><?php _e('Bidding End Time', 'azure-plugin'); ?></label>
                    <input type="time" id="_auction_bidding_end_time" name="_auction_bidding_end_time" value="<?php echo esc_attr($end_time); ?>" style="width: 120px;" />
                    <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Time when bidding closes (site timezone).', 'azure-plugin'); ?>"></span>
                </p>
                <p class="form-field _auction_buy_it_now_enabled_field">
                    <label for="_auction_buy_it_now_enabled"><?php _e('Buy It Now', 'azure-plugin'); ?></label>
                    <input type="checkbox" id="_auction_buy_it_now_enabled" name="_auction_buy_it_now_enabled" value="yes" <?php checked($buy_it_now, 'yes'); ?> />
                    <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Allow buyers to purchase at a fixed price and end the auction.', 'azure-plugin'); ?>"></span>
                </p>
                <p class="form-field _auction_buy_it_now_price_field">
                    <label for="_auction_buy_it_now_price"><?php _e('Buy It Now Price', 'azure-plugin'); ?></label>
                    <input type="number" id="_auction_buy_it_now_price" name="_auction_buy_it_now_price" value="<?php echo esc_attr($buy_it_now_price); ?>" min="0" step="0.01" style="width: 120px;" />
                    <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Fixed price for Buy It Now.', 'azure-plugin'); ?>"></span>
                </p>
                <p class="form-field _auction_buy_it_now_pay_immediately_field">
                    <label for="_auction_buy_it_now_pay_immediately"><?php _e('Require immediate payment (Stripe/checkout)', 'azure-plugin'); ?></label>
                    <input type="checkbox" id="_auction_buy_it_now_pay_immediately" name="_auction_buy_it_now_pay_immediately" value="yes" <?php checked($pay_immediately, 'yes'); ?> />
                    <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('When checked, Buy It Now redirects to checkout to pay immediately.', 'azure-plugin'); ?>"></span>
                </p>
            </div>
        </div>
        <?php
    }

    public function save_product_meta($product_id) {
        $product_type = isset($_POST['product-type']) ? sanitize_text_field($_POST['product-type']) : '';
        if ($product_type !== 'auction') {
            return;
        }

        if (isset($_POST['_auction_starting_bid'])) {
            update_post_meta($product_id, '_regular_price', wc_clean(wp_unslash($_POST['_auction_starting_bid'])));
            update_post_meta($product_id, '_price', wc_clean(wp_unslash($_POST['_auction_starting_bid'])));
        }

        $end_date = isset($_POST['_auction_bidding_end_date']) ? sanitize_text_field($_POST['_auction_bidding_end_date']) : '';
        $end_time = isset($_POST['_auction_bidding_end_time']) ? sanitize_text_field($_POST['_auction_bidding_end_time']) : '';
        if ($end_date && $end_time) {
            $combined = $end_date . ' ' . $end_time . ':00';
            update_post_meta($product_id, '_auction_bidding_end', $combined);
        } else {
            update_post_meta($product_id, '_auction_bidding_end', '');
        }

        update_post_meta($product_id, '_auction_buy_it_now_enabled', isset($_POST['_auction_buy_it_now_enabled']) ? 'yes' : 'no');
        if (isset($_POST['_auction_buy_it_now_price'])) {
            update_post_meta($product_id, '_auction_buy_it_now_price', wc_clean(wp_unslash($_POST['_auction_buy_it_now_price'])));
        }
        update_post_meta($product_id, '_auction_buy_it_now_pay_immediately', isset($_POST['_auction_buy_it_now_pay_immediately']) ? 'yes' : 'no');
    }

    public function product_type_js() {
        global $post;
        if (!$post || get_post_type($post) !== 'product') {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(function($) {
            function toggleAuctionTabs() {
                var t = $('#product-type').val();
                if (t === 'auction') {
                    $('.show_if_auction').show();
                    $('.pricing').closest('.options_group').hide();
                } else {
                    $('.show_if_auction').hide();
                    $('.pricing').closest('.options_group').show();
                }
            }
            toggleAuctionTabs();
            $('#product-type').on('change', toggleAuctionTabs);
        });
        </script>
        <?php
    }
}
