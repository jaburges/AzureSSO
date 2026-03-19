<?php
/**
 * Auction Bids - Place bid, max-bid/auto-bid, masked history
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Auction_Bids {

    const MIN_INCREMENT = 5.00;

    /**
     * Place a bid (AJAX entry point: reads POST product_id, amount, max_bid, nonce).
     *
     * @return array|WP_Error { current_price, bids: masked list } or WP_Error
     */
    public function place_bid() {
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $amount = isset($_POST['amount']) ? self::parse_amount($_POST['amount']) : null;
        $max_bid = isset($_POST['max_bid']) ? self::parse_amount($_POST['max_bid']) : null;
        $is_max_bid = !empty($_POST['is_max_bid']) && $_POST['is_max_bid'] === '1';

        if (!$product_id || (!is_numeric($amount) && !$is_max_bid) || ($is_max_bid && (!is_numeric($max_bid) || $max_bid <= 0))) {
            return new WP_Error('invalid', __('Invalid bid data.', 'azure-plugin'));
        }

        if (!wp_verify_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '', 'azure_auction_bid')) {
            return new WP_Error('nonce', __('Security check failed.', 'azure-plugin'));
        }

        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'auction') {
            return new WP_Error('invalid', __('Product is not an auction.', 'azure-plugin'));
        }

        if ($product->is_auction_ended() || $product->get_auction_status() === 'ended' || $product->get_auction_status() === 'sold') {
            return new WP_Error('ended', __('Bidding has ended.', 'azure-plugin'));
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('login', __('You must be logged in to bid.', 'azure-plugin'));
        }

        $starting_bid = $this->get_starting_bid($product_id);
        $high = $this->get_current_high($product_id);
        $current_price = $high ? (float) $high->bid_amount : $starting_bid;
        $increment = self::MIN_INCREMENT;

        if ($is_max_bid && $max_bid > 0) {
            $bid_amount = $current_price + $increment;
            if ($bid_amount > $max_bid) {
                return new WP_Error('invalid', sprintf(__('Your max bid must be at least %s to be the high bidder.', 'azure-plugin'), wc_price($bid_amount)));
            }
            $bid_amount = min($bid_amount, $max_bid);
            $insert_max_bid = $max_bid;
            $is_auto = 1;
        } else {
            $required = $high ? ($current_price + $increment) : $starting_bid;
            if ($amount < $required) {
                return new WP_Error('invalid', sprintf(__('Minimum bid is %s.', 'azure-plugin'), wc_price($required)));
            }
            $bid_amount = $amount;
            $insert_max_bid = null;
            $is_auto = 0;
        }

        global $wpdb;
        $table = Azure_Database::get_table_name('auction_bids');
        if (!$table) {
            return new WP_Error('error', __('Bids table not available.', 'azure-plugin'));
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null;

        $wpdb->insert(
            $table,
            array(
                'product_id'  => $product_id,
                'user_id'     => $user_id,
                'bid_amount'  => $bid_amount,
                'max_bid'     => $insert_max_bid,
                'is_auto_bid' => $is_auto,
                'ip_address'  => $ip,
            ),
            array('%d', '%d', '%f', '%f', '%d', '%s')
        );

        if ($wpdb->last_error) {
            return new WP_Error('error', __('Could not save bid.', 'azure-plugin'));
        }

        $this->process_auto_bids($product_id, $increment, $starting_bid);

        $data = $this->get_masked_bid_history($product_id);
        $data['current_price'] = $this->get_current_high_amount($product_id);
        if ($data['current_price'] === null) {
            $data['current_price'] = $starting_bid;
        }
        return $data;
    }

    /**
     * After a new bid, if the previous high bidder had a max_bid, auto-place up to their max.
     */
    private function process_auto_bids($product_id, $increment, $starting_bid) {
        global $wpdb;
        $table = Azure_Database::get_table_name('auction_bids');
        if (!$table) {
            return;
        }

        $max_rounds = 50;
        $rounds = 0;
        while ($rounds < $max_rounds) {
            $high = $this->get_current_high($product_id);
            if (!$high || $high->max_bid === null || $high->max_bid <= (float) $high->bid_amount) {
                break;
            }
            $next_amount = (float) $high->bid_amount + $increment;
            if ($next_amount > $high->max_bid) {
                break;
            }
            $wpdb->insert(
                $table,
                array(
                    'product_id'  => $product_id,
                    'user_id'     => $high->user_id,
                    'bid_amount'  => $next_amount,
                    'max_bid'     => $high->max_bid,
                    'is_auto_bid' => 1,
                    'ip_address'  => null,
                ),
                array('%d', '%d', '%f', '%f', '%d', '%s')
            );
            $rounds++;
        }
    }

    private function get_starting_bid($product_id) {
        $p = wc_get_product($product_id);
        if (!$p) {
            return 0;
        }
        $price = $p->get_regular_price();
        return $price !== '' ? (float) $price : 0;
    }

    private function get_current_high($product_id) {
        global $wpdb;
        $table = Azure_Database::get_table_name('auction_bids');
        if (!$table) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, bid_amount, max_bid FROM {$table} WHERE product_id = %d ORDER BY bid_amount DESC, created_at DESC LIMIT 1",
            $product_id
        ));
    }

    private function get_current_high_amount($product_id) {
        $high = $this->get_current_high($product_id);
        return $high ? (float) $high->bid_amount : null;
    }

    private static function parse_amount($v) {
        if ($v === null || $v === '') {
            return null;
        }
        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * Get masked bid history for display (bidder = first 2 chars of username + "***").
     *
     * @param int $product_id
     * @param int $limit
     * @return array { bids: array of { bidder, amount, time }, current_price }
     */
    public function get_masked_bid_history($product_id, $limit = 10) {
        global $wpdb;
        $table = Azure_Database::get_table_name('auction_bids');
        if (!$table) {
            return array('bids' => array(), 'current_price' => 0);
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, bid_amount, created_at FROM {$table} WHERE product_id = %d ORDER BY created_at DESC LIMIT %d",
            $product_id,
            $limit
        ), ARRAY_A);

        $bids = array();
        foreach ($rows as $row) {
            $login = '';
            $user = get_userdata((int) $row['user_id']);
            if ($user && !empty($user->user_login)) {
                $login = substr($user->user_login, 0, 2) . '***';
            } else {
                $login = '***';
            }
            $bids[] = array(
                'bidder' => $login,
                'amount' => (float) $row['bid_amount'],
                'time'   => $row['created_at'],
            );
        }

        $current = $this->get_current_high_amount($product_id);
        $product = wc_get_product($product_id);
        $starting = $product ? $this->get_starting_bid($product_id) : 0;
        $current_price = $current !== null ? $current : $starting;

        return array(
            'bids'          => $bids,
            'current_price' => $current_price,
        );
    }
}
