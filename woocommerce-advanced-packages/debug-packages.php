<?php
/**
 * Debug script for WooCommerce Advanced Packages
 * 
 * Usage: Add items to cart, then navigate to /wp-content/plugins/woocommerce-advanced-packages/debug-packages.php
 * This script will show detailed information about how packages are being split and what shipping methods are available.
 */

// Load WordPress
require_once '../../../wp-load.php';

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Access denied. You must be an administrator to view this page.' );
}

// Check if WooCommerce is active
if ( ! class_exists( 'WooCommerce' ) ) {
    wp_die( 'WooCommerce is not active.' );
}

// Check if cart has items
if ( WC()->cart->is_empty() ) {
    wp_die( 'Cart is empty. Please add some items to the cart first.' );
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>WooCommerce Advanced Packages - Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { max-width: 1200px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .debug-section { background: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #0073aa; }
        .error { border-left-color: #d63638; }
        .success { border-left-color: #00a32a; }
        .warning { border-left-color: #dba617; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .item-list { background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>

<div class="container">
    <h1>🐛 WooCommerce Advanced Packages Debug</h1>
    <p><em>Debug information for troubleshooting package splitting issues.</em></p>

    <?php
    // Get plugin instance
    $plugin = WC_Advanced_Packages::instance();
    $settings = get_option( 'wc_advanced_packages_settings', array() );
    
    echo '<div class="debug-section">';
    echo '<h2>🔧 Plugin Settings</h2>';
    echo '<ul>';
    echo '<li><strong>Plugin Enabled:</strong> ' . ( isset( $settings['enabled'] ) && $settings['enabled'] === 'yes' ? '✅ Yes' : '❌ No' ) . '</li>';
    echo '<li><strong>Printify Meta Key:</strong> ' . ( $settings['printify_meta_key'] ?? '_printify_blueprint_id' ) . '</li>';
    echo '<li><strong>Pickup Shipping Class:</strong> ' . ( $settings['pickup_shipping_class'] ?? 'free-pickup' ) . '</li>';
    echo '<li><strong>Allowed Pickup Methods:</strong> ' . ( $settings['allowed_pickup_methods'] ?? 'local_pickup' ) . '</li>';
    echo '</ul>';
    echo '</div>';

    // Show cart contents
    echo '<div class="debug-section">';
    echo '<h2>🛒 Cart Contents (' . WC()->cart->get_cart_contents_count() . ' items)</h2>';
    
    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        $product = $cart_item['data'];
        echo '<div class="item-list">';
        echo '<strong>' . esc_html( $product->get_name() ) . '</strong> (Qty: ' . $cart_item['quantity'] . ')<br>';
        echo 'Product ID: ' . $product->get_id() . '<br>';
        echo 'Shipping Class: ' . ( $product->get_shipping_class() ?: 'None' ) . '<br>';
        echo 'Needs Shipping: ' . ( $product->needs_shipping() ? '✅ Yes' : '❌ No' ) . '<br>';
        echo '<small><em>(Note: Pickup items still "need shipping" - they just use local pickup method)</em></small><br>';
        
        // Check Printify meta
        $printify_meta_key = $settings['printify_meta_key'] ?? '_printify_blueprint_id';
        $printify_meta = $product->get_meta( $printify_meta_key );
        echo 'Printify Meta (' . $printify_meta_key . '): ' . ( $printify_meta ? '✅ ' . esc_html( $printify_meta ) : '❌ Not found' ) . '<br>';
        
        // Check if it would be categorized
        $is_printify = ! empty( $printify_meta );
        $is_pickup = $product->get_shipping_class() === ( $settings['pickup_shipping_class'] ?? 'free-pickup' );
        
        if ( $is_printify ) {
            echo '<span style="background: #e1f5fe; padding: 2px 6px; border-radius: 3px;">🖨️ PRINTIFY ITEM</span><br>';
        } elseif ( $is_pickup ) {
            echo '<span style="background: #e8f5e8; padding: 2px 6px; border-radius: 3px;">🏪 PICKUP ITEM</span><br>';
        } else {
            echo '<span style="background: #fff3e0; padding: 2px 6px; border-radius: 3px;">📋 OTHER ITEM</span><br>';
        }
        
        echo '</div>';
    }
    echo '</div>';

    // Get original packages (before our filter)
    remove_filter( 'woocommerce_cart_shipping_packages', array( $plugin, 'split_shipping_packages' ), 10 );
    $original_packages = WC()->shipping()->get_packages();
    add_filter( 'woocommerce_cart_shipping_packages', array( $plugin, 'split_shipping_packages' ), 10, 1 );

    echo '<div class="debug-section">';
    echo '<h2>📦 Original Packages (Before Splitting)</h2>';
    echo '<p>Total packages: ' . count( $original_packages ) . '</p>';
    
    // If no packages, investigate why
    if ( empty( $original_packages ) ) {
        echo '<div class="debug-section error">';
        echo '<h3>❌ No Packages Created - Diagnostics</h3>';
        
        // Check if cart has items
        $cart_count = WC()->cart->get_cart_contents_count();
        echo '<strong>Cart Items:</strong> ' . $cart_count . '<br>';
        
        // Check if shipping is needed
        $needs_shipping = WC()->cart->needs_shipping();
        echo '<strong>Cart Needs Shipping:</strong> ' . ( $needs_shipping ? '✅ Yes' : '❌ No' ) . '<br>';
        
        // Check if shipping address is set
        $country = WC()->customer->get_shipping_country();
        $state = WC()->customer->get_shipping_state();
        echo '<strong>Shipping Address Set:</strong> ' . ( $country ? '✅ Yes (' . $country . ':' . $state . ')' : '❌ No' ) . '<br>';
        
        // Check if WooCommerce shipping is initialized
        $shipping = WC()->shipping();
        echo '<strong>WC Shipping Object:</strong> ' . ( $shipping ? '✅ Available' : '❌ Missing' ) . '<br>';
        
        // Try to force calculate shipping
        if ( $needs_shipping && $country ) {
            echo '<strong>Attempting to force shipping calculation...</strong><br>';
            
            // Clear any existing packages
            WC()->shipping()->reset_shipping();
            
            // Calculate shipping
            WC()->cart->calculate_shipping();
            
            // Check again
            $forced_packages = WC()->shipping()->get_packages();
            echo '<strong>After forced calculation:</strong> ' . count( $forced_packages ) . ' packages<br>';
            
            if ( ! empty( $forced_packages ) ) {
                echo '<span style="color: green;">✅ Shipping calculation successful after forcing</span><br>';
            } else {
                // Check for errors
                $notices = wc_get_notices( 'error' );
                if ( ! empty( $notices ) ) {
                    echo '<strong>WooCommerce Errors:</strong><br>';
                    foreach ( $notices as $notice ) {
                        echo '• ' . esc_html( $notice['notice'] ) . '<br>';
                    }
                }
            }
        }
        
        echo '<br><strong>Possible causes:</strong><br>';
        echo '• No shipping methods enabled in zones<br>';
        echo '• Shipping address not set properly<br>';
        echo '• WooCommerce shipping not initialized<br>';
        echo '• All products are virtual/downloadable<br>';
        echo '</div>';
    } else {
        foreach ( $original_packages as $i => $package ) {
            echo '<div class="item-list">';
            echo '<strong>Package ' . $i . ':</strong><br>';
            echo 'Items: ' . count( $package['contents'] ) . '<br>';
            echo 'Cost: ' . wc_price( $package['contents_cost'] ) . '<br>';
            echo 'Destination: ' . ( $package['destination']['country'] ?? 'Unknown' ) . '<br>';
            echo '</div>';
        }
    }
    echo '</div>';

    // Get split packages (after our filter)
    $split_packages = WC()->shipping()->get_packages();

    echo '<div class="debug-section">';
    echo '<h2>🔀 Split Packages (After Our Filter)</h2>';
    echo '<p>Total packages: ' . count( $split_packages ) . '</p>';
    
    if ( count( $split_packages ) === count( $original_packages ) ) {
        echo '<div class="debug-section warning">';
        echo '<strong>⚠️ WARNING:</strong> Package count is the same before and after splitting. This might indicate:';
        echo '<ul>';
        echo '<li>Plugin is disabled</li>';
        echo '<li>No items match the splitting criteria</li>';
        echo '<li>There\'s an issue with the filtering logic</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    foreach ( $split_packages as $i => $package ) {
        $package_type = 'Standard';
        $type_class = '';
        
        if ( isset( $package['is_pickup'] ) && $package['is_pickup'] ) {
            $package_type = '🏪 Pickup Package';
            $type_class = 'success';
        } elseif ( isset( $package['is_printify'] ) && $package['is_printify'] ) {
            $package_type = '🖨️ Printify Package';
            $type_class = 'success';
        } elseif ( isset( $package['is_other'] ) && $package['is_other'] ) {
            $package_type = '📋 Other Items Package';
            $type_class = 'success';
        }
        
        echo '<div class="debug-section ' . $type_class . '">';
        echo '<strong>' . $package_type . ' (Package ' . $i . '):</strong><br>';
        echo 'Items: ' . count( $package['contents'] ) . '<br>';
        echo 'Cost: ' . wc_price( $package['contents_cost'] ) . '<br>';
        echo 'Destination: ' . ( $package['destination']['country'] ?? 'Unknown' ) . '<br>';
        
        if ( ! empty( $package['contents'] ) ) {
            echo 'Products: ';
            $product_names = array();
            foreach ( $package['contents'] as $cart_item ) {
                $product_names[] = $cart_item['data']->get_name();
            }
            echo esc_html( implode( ', ', $product_names ) ) . '<br>';
        }
        
        // Calculate shipping for this package
        $shipping_rates = WC()->shipping()->calculate_shipping_for_package( $package );
        echo 'Available shipping methods: ';
        if ( ! empty( $shipping_rates['rates'] ) ) {
            $method_names = array();
            foreach ( $shipping_rates['rates'] as $rate ) {
                $method_names[] = $rate->get_label() . ' (' . $rate->get_method_id() . ')';
            }
            echo implode( ', ', $method_names );
        } else {
            echo '<span style="color: #d63638;">❌ NO METHODS AVAILABLE</span>';
        }
        echo '<br>';
        
        echo '</div>';
    }
    echo '</div>';

    // Show shipping zones and methods
    echo '<div class="debug-section">';
    echo '<h2>🌍 Shipping Zones & Methods</h2>';
    
    $shipping_zones = WC_Shipping_Zones::get_zones();
    foreach ( $shipping_zones as $zone ) {
        echo '<div class="item-list">';
        echo '<strong>' . esc_html( $zone['zone_name'] ) . '</strong><br>';
        echo 'Locations: ';
        if ( ! empty( $zone['zone_locations'] ) ) {
            $locations = array();
            foreach ( $zone['zone_locations'] as $location ) {
                $locations[] = $location->code;
            }
            echo implode( ', ', $locations );
        } else {
            echo 'None';
        }
        echo '<br>';
        echo 'Shipping Methods: ';
        if ( ! empty( $zone['shipping_methods'] ) ) {
            $methods = array();
            foreach ( $zone['shipping_methods'] as $method ) {
                $status = $method->is_enabled() ? '✅ Enabled' : '❌ Disabled';
                $methods[] = $method->get_title() . ' (' . $method->id . ') - ' . $status;
            }
            echo implode( '<br>&nbsp;&nbsp;• ', $methods );
        } else {
            echo 'None';
        }
        echo '<br>';
        
        // Check if this zone covers the customer's location
        $customer_country = WC()->customer->get_shipping_country();
        $customer_state = WC()->customer->get_shipping_state();
        $customer_location = $customer_country . ':' . $customer_state;
        
        $covers_customer = false;
        if ( ! empty( $zone['zone_locations'] ) ) {
            foreach ( $zone['zone_locations'] as $location ) {
                if ( $location->code === $customer_country || 
                     $location->code === $customer_location ||
                     $location->code === 'NA' ) {
                    $covers_customer = true;
                    break;
                }
            }
        }
        
        if ( $covers_customer ) {
            echo '<span style="background: #e8f5e8; padding: 2px 6px; border-radius: 3px;">📍 This zone covers your customer\'s location</span><br>';
        }
        
        echo '</div>';
    }
    
    // Rest of world zone
    $row_zone = new WC_Shipping_Zone( 0 );
    echo '<div class="item-list">';
    echo '<strong>Rest of World</strong><br>';
    echo 'Shipping Methods: ';
    $row_methods = $row_zone->get_shipping_methods();
    if ( ! empty( $row_methods ) ) {
        $methods = array();
        foreach ( $row_methods as $method ) {
            $methods[] = $method->get_title() . ' (' . $method->id . ')';
        }
        echo implode( ', ', $methods );
    } else {
        echo 'None';
    }
    echo '<br>';
    echo '</div>';
    
    echo '</div>';

    // Show customer shipping address
    echo '<div class="debug-section">';
    echo '<h2>📍 Customer Shipping Address</h2>';
    $customer = WC()->customer;
    echo 'Country: ' . $customer->get_shipping_country() . '<br>';
    echo 'State: ' . $customer->get_shipping_state() . '<br>';
    echo 'City: ' . $customer->get_shipping_city() . '<br>';
    echo 'Postcode: ' . $customer->get_shipping_postcode() . '<br>';
    echo '</div>';

    // Show specific recommendations based on current setup
    echo '<div class="debug-section">';
    echo '<h2>💡 Based on your current setup, here\'s what to fix:</h2>';
    
    // Check if Local Pickup is enabled globally (not in zones)
    // Try different possible option names for Local Pickup
    $local_pickup_settings = get_option( 'woocommerce_local_pickup_settings', array() );
    $local_pickup_enabled = false;
    
    // Check various ways WooCommerce might store Local Pickup settings
    if ( isset( $local_pickup_settings['enabled'] ) && $local_pickup_settings['enabled'] === 'yes' ) {
        $local_pickup_enabled = true;
    } elseif ( get_option( 'woocommerce_local_pickup_enabled' ) === 'yes' ) {
        $local_pickup_enabled = true;
    } elseif ( class_exists( 'WC_Shipping_Local_Pickup' ) ) {
        // Check if Local Pickup class exists and is enabled
        $shipping_methods = WC()->shipping()->get_shipping_methods();
        if ( isset( $shipping_methods['local_pickup'] ) && $shipping_methods['local_pickup']->is_enabled() ) {
            $local_pickup_enabled = true;
            $local_pickup_settings = $shipping_methods['local_pickup']->settings;
        }
    }
    
    // Debug: Show all Local Pickup related options
    echo '<div style="background: #f9f9f9; padding: 10px; margin: 10px 0; font-size: 11px; border: 1px solid #ddd;">';
    echo '<strong>🔍 Local Pickup Settings Debug:</strong><br>';
    echo 'woocommerce_local_pickup_settings: <pre>' . print_r( $local_pickup_settings, true ) . '</pre>';
    echo 'woocommerce_local_pickup_enabled: ' . get_option( 'woocommerce_local_pickup_enabled', 'not found' ) . '<br>';
    
    // Check shipping methods
    if ( class_exists( 'WC_Shipping_Local_Pickup' ) ) {
        $shipping_methods = WC()->shipping()->get_shipping_methods();
        if ( isset( $shipping_methods['local_pickup'] ) ) {
            echo 'Local Pickup Method Found: ' . ( $shipping_methods['local_pickup']->is_enabled() ? 'Enabled' : 'Disabled' ) . '<br>';
            echo 'Method Settings: <pre>' . print_r( $shipping_methods['local_pickup']->settings, true ) . '</pre>';
        }
    }
    echo '</div>';
    
    if ( ! $local_pickup_enabled ) {
        echo '<div class="debug-section error">';
        echo '<h3>❌ IMMEDIATE FIX NEEDED: Enable Local Pickup</h3>';
        echo '<p><strong>Local Pickup is not enabled globally!</strong></p>';
        echo '<ol>';
        echo '<li>Go to <strong>WooCommerce > Settings > Shipping</strong></li>';
        echo '<li>Click on <strong>"Local pickup"</strong> tab (not a zone!)</li>';
        echo '<li>Check <strong>"Enable local pickup"</strong></li>';
        echo '<li>Configure title and cost if needed</li>';
        echo '<li>Save changes</li>';
        echo '</ol>';
        echo '<p><em>Local Pickup is a global setting, not added to shipping zones.</em></p>';
        echo '</div>';
    } else {
        echo '<div class="debug-section success">';
        echo '<h3>✅ Local Pickup is globally enabled</h3>';
        $title = isset( $local_pickup_settings['title'] ) ? $local_pickup_settings['title'] : 'Local pickup';
        $cost = isset( $local_pickup_settings['cost'] ) ? $local_pickup_settings['cost'] : 0;
        echo '<p>Title: <strong>' . esc_html( $title ) . '</strong></p>';
        echo '<p>Cost: <strong>' . ( $cost ? wc_price( $cost ) : 'Free' ) . '</strong></p>';
        echo '</div>';
        
        // Check if packages are being created but still showing no methods
        $packages = WC()->shipping()->get_packages();
        if ( ! empty( $packages ) ) {
            $pickup_packages_with_no_methods = 0;
            foreach ( $packages as $package ) {
                if ( isset( $package['is_pickup'] ) && $package['is_pickup'] ) {
                    $rates = WC()->shipping()->calculate_shipping_for_package( $package );
                    if ( empty( $rates['rates'] ) ) {
                        $pickup_packages_with_no_methods++;
                    }
                }
            }
            
            if ( $pickup_packages_with_no_methods > 0 ) {
                echo '<div class="debug-section warning">';
                echo '<h3>⚠️ Issue: Pickup Package Has No Shipping Methods</h3>';
                echo '<p>Local Pickup is enabled but not appearing for pickup packages. The plugin has been updated to fix this automatically.</p>';
                echo '<p><strong>Try:</strong></p>';
                echo '<ul>';
                echo '<li>Clear your cart and re-add the pickup item</li>';
                echo '<li>Refresh this debug page</li>';
                echo '<li>Check if Local Pickup now appears in "Available shipping methods" below</li>';
                echo '</ul>';
                echo '</div>';
            }
        }
    }
    
    echo '<h3>Other Troubleshooting Steps:</h3>';
    echo '<ul>';
    echo '<li>If packages still aren\'t splitting after adding Local Pickup: Clear cart and re-add items</li>';
    echo '<li>If wrong shipping methods show for pickup items: Check the "Allowed Pickup Methods" setting in plugin</li>';
    echo '<li>Check WooCommerce logs under <strong>WooCommerce > Status > Logs</strong> for detailed error messages</li>';
    echo '<li>Try disabling other shipping/checkout plugins temporarily to test for conflicts</li>';
    echo '</ul>';
    
    echo '<div style="background: #e8f5e8; padding: 15px; margin: 10px 0; border-radius: 5px;">';
    echo '<h4>🎯 Expected Result After Fix:</h4>';
    echo '<p>With your pickup item in the cart, you should see:</p>';
    echo '<ul>';
    echo '<li><strong>1 package</strong> created (pickup package)</li>';
    echo '<li><strong>Only Local Pickup</strong> shipping option available</li>';
    echo '<li><strong>No regular shipping methods</strong> (flat rate, etc.)</li>';
    echo '</ul>';
    echo '</div>';
    
    echo '</div>';
    ?>

    <div class="debug-section">
        <h2>🔗 Useful Links</h2>
        <ul>
            <li><a href="<?php echo admin_url( 'admin.php?page=wc-advanced-packages' ); ?>">Plugin Settings</a></li>
            <li><a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=shipping' ); ?>">WooCommerce Shipping Settings</a></li>
            <li><a href="<?php echo admin_url( 'admin.php?page=wc-status&tab=logs' ); ?>">WooCommerce Logs</a></li>
        </ul>
    </div>

    <p><small><em>This debug page is only visible to administrators and should be removed or secured in production environments.</em></small></p>
</div>

</body>
</html>
