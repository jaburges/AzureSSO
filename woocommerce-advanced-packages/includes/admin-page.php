<?php
/**
 * Admin Settings Page Template
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <div class="wc-advanced-packages-admin">
        <div class="postbox-container" style="width: 75%; margin-right: 2%;">
            <div class="postbox">
                <h2 class="hndle"><span><?php _e( 'Package Splitting Settings', 'wc-advanced-packages' ); ?></span></h2>
                <div class="inside">
                    <form method="post" action="">
                        <?php wp_nonce_field( 'wc_advanced_packages_settings' ); ?>
                        
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="enabled"><?php _e( 'Enable Package Splitting', 'wc-advanced-packages' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="checkbox" id="enabled" name="enabled" value="yes" <?php checked( $this->get_setting( 'enabled' ), 'yes' ); ?> />
                                        <p class="description">
                                            <?php _e( 'Enable or disable the advanced package splitting functionality.', 'wc-advanced-packages' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="printify_meta_key"><?php _e( 'Printify Meta Key', 'wc-advanced-packages' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="printify_meta_key" name="printify_meta_key" value="<?php echo esc_attr( $this->get_setting( 'printify_meta_key' ) ); ?>" class="regular-text" />
                                        <p class="description">
                                            <?php _e( 'The meta key used to identify Printify products. Default: _printify_blueprint_id', 'wc-advanced-packages' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="pickup_shipping_class"><?php _e( 'Pickup Shipping Class Slug', 'wc-advanced-packages' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="pickup_shipping_class" name="pickup_shipping_class" value="<?php echo esc_attr( $this->get_setting( 'pickup_shipping_class' ) ); ?>" class="regular-text" />
                                        <p class="description">
                                            <?php _e( 'The shipping class slug for free pickup items. Default: free-pickup', 'wc-advanced-packages' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="allowed_pickup_methods"><?php _e( 'Allowed Pickup Methods', 'wc-advanced-packages' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="allowed_pickup_methods" name="allowed_pickup_methods" value="<?php echo esc_attr( $this->get_setting( 'allowed_pickup_methods' ) ); ?>" class="regular-text" />
                                        <p class="description">
                                            <?php _e( 'Comma-separated list of shipping method IDs allowed for pickup packages. Default: local_pickup', 'wc-advanced-packages' ); ?>
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <h3><?php _e( 'Package Labels', 'wc-advanced-packages' ); ?></h3>
                        <p><?php _e( 'Customize the display names for different package types on the cart and checkout pages.', 'wc-advanced-packages' ); ?></p>
                        
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="printify_package_label"><?php _e( 'Printify Package Label', 'wc-advanced-packages' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="printify_package_label" name="printify_package_label" value="<?php echo esc_attr( $this->get_setting( 'printify_package_label' ) ); ?>" class="regular-text" />
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="pickup_package_label"><?php _e( 'Pickup Package Label', 'wc-advanced-packages' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="pickup_package_label" name="pickup_package_label" value="<?php echo esc_attr( $this->get_setting( 'pickup_package_label' ) ); ?>" class="regular-text" />
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="other_package_label"><?php _e( 'Other Items Package Label', 'wc-advanced-packages' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="other_package_label" name="other_package_label" value="<?php echo esc_attr( $this->get_setting( 'other_package_label' ) ); ?>" class="regular-text" />
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="debug_mode"><?php _e( 'Enable Debug Mode', 'wc-advanced-packages' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="checkbox" id="debug_mode" name="debug_mode" value="yes" <?php checked( $this->get_setting( 'debug_mode' ), 'yes' ); ?> />
                                        <p class="description">
                                            <?php _e( 'Enable debug information for troubleshooting. Only visible to administrators. This adds:', 'wc-advanced-packages' ); ?>
                                            <br>• <?php _e( 'Debug panel on cart page', 'wc-advanced-packages' ); ?>
                                            <br>• <?php _e( 'Admin bar debug link on cart/checkout', 'wc-advanced-packages' ); ?>
                                            <br>• <?php _e( 'Browser console debug output', 'wc-advanced-packages' ); ?>
                                            <br>• <?php _e( 'Shortcode: [wc_packages_debug]', 'wc-advanced-packages' ); ?>
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button-primary" value="<?php _e( 'Save Settings', 'wc-advanced-packages' ); ?>" />
                            <button type="button" id="reset-settings" class="button-secondary" style="margin-left: 10px;">
                                <?php _e( 'Reset to Defaults', 'wc-advanced-packages' ); ?>
                            </button>
                        </p>
                    </form>
                </div>
            </div>
            
            <!-- Debug Information -->
            <div class="postbox">
                <h2 class="hndle"><span><?php _e( 'Debug Information', 'wc-advanced-packages' ); ?></span></h2>
                <div class="inside">
                    <h4><?php _e( 'Current Shipping Classes', 'wc-advanced-packages' ); ?></h4>
                    <div class="debug-info">
                        <?php
                        $shipping_classes = WC()->shipping()->get_shipping_classes();
                        if ( ! empty( $shipping_classes ) ) {
                            echo '<ul>';
                            foreach ( $shipping_classes as $class ) {
                                echo '<li><strong>' . esc_html( $class->name ) . '</strong> (slug: <code>' . esc_html( $class->slug ) . '</code>)</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<p>' . __( 'No shipping classes found.', 'wc-advanced-packages' ) . '</p>';
                        }
                        ?>
                    </div>
                    
                    <h4><?php _e( 'Available Shipping Methods', 'wc-advanced-packages' ); ?></h4>
                    <div class="debug-info">
                        <?php
                        $shipping_methods = WC()->shipping()->get_shipping_methods();
                        if ( ! empty( $shipping_methods ) ) {
                            echo '<ul>';
                            foreach ( $shipping_methods as $method_id => $method ) {
                                echo '<li><strong>' . esc_html( $method->get_method_title() ) . '</strong> (ID: <code>' . esc_html( $method_id ) . '</code>)</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<p>' . __( 'No shipping methods found.', 'wc-advanced-packages' ) . '</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="postbox-container" style="width: 23%;">
            <div class="postbox">
                <h2 class="hndle"><span><?php _e( 'Quick Setup Guide', 'wc-advanced-packages' ); ?></span></h2>
                <div class="inside">
                    <ol>
                        <li><?php _e( 'Ensure WooCommerce is installed and active', 'wc-advanced-packages' ); ?></li>
                        <li><?php _e( 'Install and configure the Printify WooCommerce plugin', 'wc-advanced-packages' ); ?></li>
                        <li><?php _e( 'Create a "Free Pickup" shipping class with slug "free-pickup"', 'wc-advanced-packages' ); ?></li>
                        <li><?php _e( 'Enable Local Pickup shipping method in your shipping zones', 'wc-advanced-packages' ); ?></li>
                        <li><?php _e( 'Configure the settings above and save', 'wc-advanced-packages' ); ?></li>
                        <li><?php _e( 'Test with products in different categories', 'wc-advanced-packages' ); ?></li>
                    </ol>
                </div>
            </div>
            
            <div class="postbox">
                <h2 class="hndle"><span><?php _e( 'Customization Hooks', 'wc-advanced-packages' ); ?></span></h2>
                <div class="inside">
                    <h4><?php _e( 'Available Filters:', 'wc-advanced-packages' ); ?></h4>
                    <ul>
                        <li><code>wc_advanced_packages_is_printify_item</code></li>
                        <li><code>wc_advanced_packages_is_pickup_item</code></li>
                        <li><code>wc_advanced_packages_group_items</code></li>
                        <li><code>wc_advanced_packages_final_packages</code></li>
                        <li><code>wc_advanced_packages_allowed_methods_for_pickup</code></li>
                    </ul>
                    <p><small><?php _e( 'See README.md for usage examples', 'wc-advanced-packages' ); ?></small></p>
                </div>
            </div>
            
            <div class="postbox">
                <h2 class="hndle"><span><?php _e( 'Debug Tools', 'wc-advanced-packages' ); ?></span></h2>
                <div class="inside">
                    <p><?php _e( 'If you\'re experiencing issues:', 'wc-advanced-packages' ); ?></p>
                    <ol>
                        <li><?php _e( 'Enable Debug Mode above', 'wc-advanced-packages' ); ?></li>
                        <li><?php _e( 'Add items to cart and view as admin', 'wc-advanced-packages' ); ?></li>
                        <li><?php _e( 'Check browser console (F12)', 'wc-advanced-packages' ); ?></li>
                        <li>
                            <a href="<?php echo home_url( '/wp-content/plugins/woocommerce-advanced-packages/debug-packages.php' ); ?>" target="_blank">
                                <?php _e( 'View detailed debug script', 'wc-advanced-packages' ); ?>
                            </a>
                        </li>
                    </ol>
                    <p><strong><?php _e( 'Debug Shortcode:', 'wc-advanced-packages' ); ?></strong> <code>[wc_packages_debug]</code></p>
                    <small><em><?php _e( 'Debug features only visible to administrators', 'wc-advanced-packages' ); ?></em></small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .wc-advanced-packages-admin {
        display: flex;
        flex-wrap: wrap;
    }
    
    .wc-advanced-packages-admin .postbox {
        margin-bottom: 20px;
    }
    
    .debug-info {
        background: #f7f7f7;
        padding: 10px;
        border-radius: 3px;
        margin: 10px 0;
    }
    
    .debug-info ul {
        margin: 0;
    }
    
    .debug-info code {
        background: #fff;
        padding: 2px 4px;
        border-radius: 2px;
    }
    
    @media (max-width: 960px) {
        .wc-advanced-packages-admin .postbox-container {
            width: 100% !important;
            margin-right: 0 !important;
        }
    }
</style>

<script>
jQuery(document).ready(function($) {
    $('#reset-settings').on('click', function(e) {
        e.preventDefault();
        
        if (confirm('<?php _e( 'Are you sure you want to reset all settings to defaults? This cannot be undone.', 'wc-advanced-packages' ); ?>')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc_advanced_packages_reset_settings',
                    nonce: '<?php echo wp_create_nonce( 'wc_advanced_packages_reset' ); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('<?php _e( 'Error resetting settings. Please try again.', 'wc-advanced-packages' ); ?>');
                    }
                },
                error: function() {
                    alert('<?php _e( 'Error resetting settings. Please try again.', 'wc-advanced-packages' ); ?>');
                }
            });
        }
    });
});
</script>
