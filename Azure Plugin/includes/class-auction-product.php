<?php
/**
 * WooCommerce Auction Product Class
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_Product')) {
    return;
}

class WC_Product_Auction extends WC_Product {

    protected $product_type = 'auction';

    public function __construct($product = 0) {
        parent::__construct($product);
    }

    public function get_type() {
        return 'auction';
    }

    public function is_virtual() {
        return false;
    }

    public function is_sold_individually() {
        return true;
    }

    /**
     * Bidding end datetime (Y-m-d H:i:s or stored as timestamp/string)
     */
    public function get_auction_bidding_end() {
        return get_post_meta($this->get_id(), '_auction_bidding_end', true);
    }

    /**
     * Whether bidding has ended
     */
    public function is_auction_ended() {
        $end = $this->get_auction_bidding_end();
        if (empty($end)) {
            return false;
        }
        $end_ts = is_numeric($end) ? (int) $end : strtotime($end);
        return $end_ts && time() >= $end_ts;
    }

    public function is_buy_it_now_enabled() {
        return get_post_meta($this->get_id(), '_auction_buy_it_now_enabled', true) === 'yes';
    }

    public function get_buy_it_now_price() {
        $p = get_post_meta($this->get_id(), '_auction_buy_it_now_price', true);
        return $p !== '' ? (float) $p : 0;
    }

    public function is_buy_it_now_pay_immediately() {
        return get_post_meta($this->get_id(), '_auction_buy_it_now_pay_immediately', true) === 'yes';
    }

    /**
     * Auction status (e.g. ended, sold via buy it now)
     */
    public function get_auction_status() {
        return get_post_meta($this->get_id(), '_auction_status', true) ?: '';
    }
}
