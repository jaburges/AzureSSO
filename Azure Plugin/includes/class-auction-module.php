<?php
/**
 * Auction Module - Main Module Class
 *
 * WooCommerce auction products with bidding, max bid, Buy It Now, and winner checkout.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Auction_Module {

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

        $this->load_dependencies();
        $this->init_hooks();

        if (class_exists('Azure_Logger')) {
            Azure_Logger::debug_module('Auction', 'Auction module initialized');
        }
    }

    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p><strong>' . esc_html__('Auction Module:', 'azure-plugin') . '</strong> ' . esc_html__('WooCommerce is required.', 'azure-plugin') . '</p></div>';
    }

    private function load_dependencies() {
        $files = array(
            'class-auction-product.php',
            'class-auction-product-type.php',
            'class-auction-bids.php',
            'class-auction-emails.php',
            'class-auction-lifecycle.php',
        );
        foreach ($files as $file) {
            $path = AZURE_PLUGIN_PATH . 'includes/' . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        if (class_exists('Azure_Auction_Product_Type')) {
            new Azure_Auction_Product_Type();
        }
    }

    private function init_hooks() {
        add_action('wp_ajax_azure_auction_place_bid', array($this, 'ajax_place_bid'));
        add_action('wp_ajax_nopriv_azure_auction_place_bid', array($this, 'ajax_place_bid_guest'));
        add_action('wp_ajax_azure_auction_get_bid_history', array($this, 'ajax_get_bid_history'));
        add_action('wp_ajax_nopriv_azure_auction_get_bid_history', array($this, 'ajax_get_bid_history_public'));
        add_action('wp_ajax_azure_auction_buy_it_now', array($this, 'ajax_buy_it_now'));
        add_action('wp_ajax_nopriv_azure_auction_buy_it_now', array($this, 'ajax_buy_it_now_guest'));

        add_action('woocommerce_single_product_summary', array($this, 'remove_auction_add_to_cart'), 5);
        add_action('woocommerce_single_product_summary', array($this, 'maybe_process_ended_auction'), 1);
        add_action('woocommerce_single_product_summary', array($this, 'render_single_product_auction'), 35);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_auction_scripts'));
    }

    public function maybe_process_ended_auction() {
        global $product;
        if ($product && $product->get_type() === 'auction' && class_exists('Azure_Auction_Lifecycle')) {
            (new Azure_Auction_Lifecycle())->maybe_process_ended_auction($product->get_id());
        }
    }

    public function remove_auction_add_to_cart() {
        global $product;
        if ($product && $product->get_type() === 'auction') {
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        }
    }

    public function render_single_product_auction() {
        global $product;
        if (!$product || $product->get_type() !== 'auction') {
            return;
        }
        $product_id = $product->get_id();
        if ($product->is_auction_ended() || in_array($product->get_auction_status(), array('ended', 'sold'), true)) {
            echo '<p class="auction-ended">' . esc_html__('This auction has ended.', 'azure-plugin') . '</p>';
            return;
        }
        $logged_in = is_user_logged_in();
        $bids = class_exists('Azure_Auction_Bids') ? (new Azure_Auction_Bids())->get_masked_bid_history($product_id) : array('bids' => array(), 'current_price' => $product->get_regular_price());
        $current_price = isset($bids['current_price']) ? (float) $bids['current_price'] : (float) $product->get_regular_price();
        ?>
        <div class="azure-auction-bid-wrapper" data-product-id="<?php echo esc_attr($product_id); ?>">
            <p class="auction-current-price">
                <strong><?php _e('Current price:', 'azure-plugin'); ?></strong>
                <span class="auction-price-value"><?php echo wc_price($current_price); ?></span>
            </p>
            <?php if ($product->is_buy_it_now_enabled() && $product->get_buy_it_now_price() > 0) : ?>
            <p class="auction-buy-it-now">
                <button type="button" class="button auction-buy-it-now-btn"><?php printf(__('Buy It Now for %s', 'azure-plugin'), wc_price($product->get_buy_it_now_price())); ?></button>
            </p>
            <?php endif; ?>
            <?php if ($logged_in) : ?>
            <div class="auction-bid-form">
                <label for="auction-bid-amount"><?php _e('Your bid', 'azure-plugin'); ?></label>
                <input type="number" id="auction-bid-amount" class="auction-bid-amount" min="0" step="0.01" value="<?php echo esc_attr($current_price + 5); ?>" />
                <button type="button" class="button auction-quick-bid" data-increment="5">+<?php echo esc_html(wc_price(5)); ?></button>
                <button type="button" class="button auction-quick-bid" data-increment="10">+<?php echo esc_html(wc_price(10)); ?></button>
                <button type="button" class="button auction-quick-bid" data-increment="20">+<?php echo esc_html(wc_price(20)); ?></button>
                <p class="auction-max-bid-row">
                    <label><input type="checkbox" class="auction-use-max-bid" /> <?php _e('Set max bid (auto-bid up to this amount)', 'azure-plugin'); ?></label>
                    <input type="number" class="auction-max-bid-amount" min="0" step="0.01" style="display:none; width:100px;" />
                </p>
                <button type="button" class="button alt auction-place-bid"><?php _e('Place bid', 'azure-plugin'); ?></button>
                <span class="auction-bid-message" style="display:none;"></span>
            </div>
            <?php else : ?>
            <p class="auction-login-required"><?php _e('Please log in or register to bid.', 'azure-plugin'); ?></p>
            <?php endif; ?>
            <div class="auction-bid-history">
                <h4><?php _e('Recent bids', 'azure-plugin'); ?></h4>
                <ul class="auction-bid-list">
                    <?php foreach (isset($bids['bids']) ? $bids['bids'] : array() as $b) : ?>
                    <li><?php echo esc_html(isset($b['bidder']) ? $b['bidder'] : '***'); ?> — <?php echo wc_price(isset($b['amount']) ? $b['amount'] : 0); ?> <span class="bid-time"><?php echo esc_html(isset($b['time']) ? $b['time'] : ''); ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (empty($bids['bids'])) : ?>
                <p class="no-bids"><?php _e('No bids yet.', 'azure-plugin'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function enqueue_auction_scripts() {
        global $product;
        if (!is_product() || !$product || $product->get_type() !== 'auction') {
            return;
        }
        wp_enqueue_script(
            'azure-auction-bid',
            AZURE_PLUGIN_URL . 'js/auction-bid.js',
            array('jquery'),
            defined('AZURE_PLUGIN_VERSION') ? AZURE_PLUGIN_VERSION : '1.0',
            true
        );
        wp_localize_script('azure-auction-bid', 'azureAuction', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('azure_auction_bid'),
            'productId' => $product->get_id(),
            'i18n'    => array(
                'buyItNowConfirm' => __('Create order and go to checkout?', 'azure-plugin'),
            ),
        ));
    }

    public function ajax_place_bid_guest() {
        wp_send_json_error(array('message' => __('You must be logged in to bid.', 'azure-plugin')));
    }

    public function ajax_place_bid() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to bid.', 'azure-plugin')));
        }
        if (!class_exists('Azure_Auction_Bids')) {
            wp_send_json_error(array('message' => __('Auction bids not available.', 'azure-plugin')));
        }
        $bids = new Azure_Auction_Bids();
        $result = $bids->place_bid();
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        wp_send_json_success($result);
    }

    public function ajax_get_bid_history_public() {
        $this->ajax_get_bid_history();
    }

    public function ajax_get_bid_history() {
        if (!class_exists('Azure_Auction_Bids')) {
            wp_send_json_success(array('bids' => array(), 'current_price' => 0));
        }
        $product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
        if (!$product_id) {
            wp_send_json_error(array('message' => __('Invalid product.', 'azure-plugin')));
        }
        $bids = new Azure_Auction_Bids();
        $data = $bids->get_masked_bid_history($product_id);
        wp_send_json_success($data);
    }

    public function ajax_buy_it_now_guest() {
        wp_send_json_error(array('message' => __('You must be logged in to use Buy It Now.', 'azure-plugin')));
    }

    public function ajax_buy_it_now() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to use Buy It Now.', 'azure-plugin')));
        }
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if (!$product_id) {
            wp_send_json_error(array('message' => __('Invalid product.', 'azure-plugin')));
        }
        if (!wp_verify_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '', 'azure_auction_bid')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'azure-plugin')));
        }
        if (!class_exists('Azure_Auction_Lifecycle')) {
            wp_send_json_error(array('message' => __('Auction not available.', 'azure-plugin')));
        }
        $lifecycle = new Azure_Auction_Lifecycle();
        $order = $lifecycle->create_buy_it_now_order($product_id, get_current_user_id());
        if (!$order) {
            wp_send_json_error(array('message' => __('Could not create order. The item may no longer be available.', 'azure-plugin')));
        }
        wp_send_json_success(array('checkout_url' => $order->get_checkout_payment_url()));
    }
}
