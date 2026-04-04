<?php
/**
 * Auction Module Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = Azure_Settings::get_all_settings();
$auction_enabled = !empty($settings['enable_auction']);

$active_auctions = 0;
$total_bids = 0;
if (class_exists('WooCommerce')) {
    global $wpdb;
    $bids_table = Azure_Database::get_table_name('auction_bids');
    if ($bids_table && $wpdb->get_var("SHOW TABLES LIKE '{$bids_table}'") === $bids_table) {
        $total_bids = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$bids_table}");
    }
    $active_auctions = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_auction_bidding_end'
         WHERE p.post_type = 'product' AND p.post_status = 'publish'
         AND (pm.meta_value = '' OR pm.meta_value > NOW())"
    );
}
?>

<?php if (empty($GLOBALS['azure_tab_mode'])): ?>
<div class="wrap azure-auction-page">
    <h1>
        <span class="dashicons dashicons-hammer"></span>
        <?php _e('Auction Module', 'azure-plugin'); ?>
    </h1>
<?php else: ?>
<div class="azure-auction-page">
<?php endif; ?>

    <?php if (!$auction_enabled): ?>
    <div class="notice notice-warning" style="margin: 15px 0;">
        <p><?php _e('The Auction module is currently disabled.', 'azure-plugin'); ?>
        <a href="<?php echo admin_url('admin.php?page=azure-plugin'); ?>"><?php _e('Enable it on the main settings page.', 'azure-plugin'); ?></a></p>
    </div>
    <?php endif; ?>

    <?php if (!class_exists('WooCommerce')) : ?>
    <div class="notice notice-error">
        <p><strong><?php _e('WooCommerce Required:', 'azure-plugin'); ?></strong>
        <?php _e('The Auction module requires WooCommerce to be installed and activated.', 'azure-plugin'); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($auction_enabled && class_exists('WooCommerce')) : ?>
    <div class="azure-module-content">
        <div class="azure-stat-row">
            <div class="azure-stat-box">
                <span class="azure-stat-number"><?php echo (int) $active_auctions; ?></span>
                <span class="azure-stat-label"><?php _e('Active Auctions', 'azure-plugin'); ?></span>
            </div>
            <div class="azure-stat-box">
                <span class="azure-stat-number"><?php echo (int) $total_bids; ?></span>
                <span class="azure-stat-label"><?php _e('Total Bids', 'azure-plugin'); ?></span>
            </div>
        </div>

        <div class="azure-action-row">
            <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" class="button button-primary">
                <span class="dashicons dashicons-plus-alt"></span> <?php _e('Add New Product', 'azure-plugin'); ?>
            </a>
            <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button">
                <span class="dashicons dashicons-list-view"></span> <?php _e('View All Products', 'azure-plugin'); ?>
            </a>
        </div>
        <p class="description"><?php _e('Create a product and select "Auction" as the product type. Set bidding end date/time, optional Buy It Now price, and require immediate payment.', 'azure-plugin'); ?></p>
    </div>
    <?php endif; ?>
</div>
