<?php
/**
 * Auction Module - Winner and notification emails
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_Auction_Emails {

    /**
     * Send "You won the auction" email to the winner with checkout link.
     *
     * @param WC_Order $order
     * @param int      $product_id
     * @param float    $winning_amount
     * @return bool
     */
    public function send_winner_email($order, $product_id, $winning_amount) {
        if (!$order || !$order->get_id()) {
            return false;
        }
        $to = $order->get_billing_email();
        $customer_name = $order->get_billing_first_name();
        if (empty($customer_name)) {
            $customer_name = $order->get_billing_email();
        }
        $product = wc_get_product($product_id);
        $item_name = $product ? $product->get_name() : __('Auction item', 'azure-plugin');
        $payment_url = $order->get_checkout_payment_url();

        $subject = sprintf(__('You won: %s', 'azure-plugin'), $item_name);

        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #333;"><?php _e('You won the auction!', 'azure-plugin'); ?></h2>
            <p><?php printf(__('Hi %s,', 'azure-plugin'), esc_html($customer_name)); ?></p>
            <p><?php printf(
                __('Congratulations! You won <strong>%s</strong> with a winning bid of %s.', 'azure-plugin'),
                esc_html($item_name),
                wc_price($winning_amount)
            ); ?></p>
            <p><?php _e('Complete your purchase by paying below:', 'azure-plugin'); ?></p>
            <p style="margin: 25px 0;">
                <a href="<?php echo esc_url($payment_url); ?>" style="background: #0073aa; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 3px; display: inline-block;"><?php _e('Pay now', 'azure-plugin'); ?></a>
            </p>
            <p style="color: #666; font-size: 14px;">
                <?php _e('If you have any questions, please contact us.', 'azure-plugin'); ?>
            </p>
        </div>
        <?php
        $message = ob_get_clean();
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $result = wp_mail($to, $subject, $message, $headers);

        if ($result && class_exists('Azure_Logger')) {
            Azure_Logger::info('Auction: Winner email sent', array(
                'order_id' => $order->get_id(),
                'product_id' => $product_id,
                'email' => $to,
            ));
        }
        return $result;
    }
}
