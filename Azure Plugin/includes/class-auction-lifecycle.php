<?php
/**
 * Auction lifecycle - end auction, determine winner, create order, send email
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Auction_Lifecycle {

    /**
     * If auction end time has passed and not yet processed, set ended, determine winner, create order, send email.
     *
     * @param int $product_id
     * @return void
     */
    public function maybe_process_ended_auction($product_id) {
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'auction') {
            return;
        }
        if ($product->get_auction_status() === 'sold') {
            return;
        }
        if (!$product->is_auction_ended()) {
            return;
        }
        if ($product->get_auction_status() === 'ended') {
            return;
        }

        update_post_meta($product_id, '_auction_status', 'ended');
        update_post_meta($product_id, '_auction_ended_at', current_time('mysql'));

        $bids = new Azure_Auction_Bids();
        $high = $this->get_high_bid_row($product_id);
        if (!$high) {
            return;
        }

        $winner_user_id = (int) $high->user_id;
        $winning_amount = (float) $high->bid_amount;
        update_post_meta($product_id, '_auction_winner_user_id', $winner_user_id);
        update_post_meta($product_id, '_auction_winning_amount', $winning_amount);

        $order = $this->create_winner_order($product_id, $winner_user_id, $winning_amount);
        if ($order) {
            update_post_meta($product_id, '_auction_winner_order_id', $order->get_id());
            if (class_exists('Azure_Auction_Emails')) {
                $emails = new Azure_Auction_Emails();
                $emails->send_winner_email($order, $product_id, $winning_amount);
            }
        }
    }

    private function get_high_bid_row($product_id) {
        global $wpdb;
        $table = Azure_Database::get_table_name('auction_bids');
        if (!$table) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, bid_amount FROM {$table} WHERE product_id = %d ORDER BY bid_amount DESC, created_at DESC LIMIT 1",
            $product_id
        ));
    }

    /**
     * Create WooCommerce order for auction winner.
     *
     * @param int   $product_id
     * @param int   $winner_user_id
     * @param float $winning_amount
     * @return WC_Order|null
     */
    public function create_winner_order($product_id, $winner_user_id, $winning_amount) {
        if (!function_exists('wc_create_order')) {
            return null;
        }
        $product = wc_get_product($product_id);
        if (!$product) {
            return null;
        }
        $order = wc_create_order(array('customer_id' => $winner_user_id));
        if (!$order) {
            return null;
        }
        $order->add_product($product, 1, array(
            'subtotal' => $winning_amount,
            'total'    => $winning_amount,
        ));
        $order->set_status('wc-pending');
        $order->add_order_note(__('Auction win. Customer must complete payment.', 'azure-plugin'));
        $order->calculate_totals();
        $order->save();
        return $order;
    }

    /**
     * Create order for Buy It Now and mark auction as sold.
     *
     * @param int $product_id
     * @param int $user_id
     * @return WC_Order|null
     */
    public function create_buy_it_now_order($product_id, $user_id) {
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'auction') {
            return null;
        }
        if ($product->is_auction_ended() || $product->get_auction_status() !== '') {
            return null;
        }
        $price = $product->get_buy_it_now_price();
        if ($price <= 0) {
            return null;
        }
        if (!function_exists('wc_create_order')) {
            return null;
        }
        $order = wc_create_order(array('customer_id' => $user_id));
        if (!$order) {
            return null;
        }
        $order->add_product($product, 1, array(
            'subtotal' => $price,
            'total'    => $price,
        ));
        $order->set_status('wc-pending');
        $order->add_order_note(__('Buy It Now - auction item. Customer must complete payment.', 'azure-plugin'));
        $order->calculate_totals();
        $order->save();

        update_post_meta($product_id, '_auction_status', 'sold');
        update_post_meta($product_id, '_auction_sold_at', current_time('mysql'));
        update_post_meta($product_id, '_auction_sold_order_id', $order->get_id());

        return $order;
    }
}
