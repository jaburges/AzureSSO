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

<div class="wrap azure-auction-page">
    <h1>
        <span class="dashicons dashicons-hammer"></span>
        <?php _e('Auction Module', 'azure-plugin'); ?>
    </h1>

    <div class="azure-module-header">
        <div class="module-status <?php echo $auction_enabled ? 'enabled' : 'disabled'; ?>">
            <span class="status-indicator"></span>
            <span class="status-text">
                <?php echo $auction_enabled ? __('Module Enabled', 'azure-plugin') : __('Module Disabled', 'azure-plugin'); ?>
            </span>
        </div>
    </div>

    <?php if (!class_exists('WooCommerce')) : ?>
    <div class="notice notice-error">
        <p><strong><?php _e('WooCommerce Required:', 'azure-plugin'); ?></strong>
        <?php _e('The Auction module requires WooCommerce to be installed and activated.', 'azure-plugin'); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($auction_enabled && class_exists('WooCommerce')) : ?>
    <div class="azure-dashboard-grid">
        <div class="dashboard-card stats-card">
            <h2><?php _e('Quick Stats', 'azure-plugin'); ?></h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-value"><?php echo (int) $active_auctions; ?></span>
                    <span class="stat-label"><?php _e('Active Auctions', 'azure-plugin'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo (int) $total_bids; ?></span>
                    <span class="stat-label"><?php _e('Total Bids', 'azure-plugin'); ?></span>
                </div>
            </div>
        </div>

        <div class="dashboard-card actions-card">
            <h2><?php _e('Quick Actions', 'azure-plugin'); ?></h2>
            <div class="action-buttons">
                <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add New Product', 'azure-plugin'); ?>
                </a>
                <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php _e('View All Products', 'azure-plugin'); ?>
                </a>
            </div>
            <p class="description"><?php _e('Create a product and select "Auction" as the product type. Set bidding end date/time, optional Buy It Now price, and require immediate payment.', 'azure-plugin'); ?></p>
        </div>
    </div>
    <?php endif; ?>
</div>
