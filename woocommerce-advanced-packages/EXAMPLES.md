# WooCommerce Advanced Packages - Code Examples

This file contains practical code examples for customizing the WooCommerce Advanced Packages plugin.

## Basic Customization Examples

### 1. Custom Printify Detection

If your Printify integration uses different meta keys or you want custom logic:

```php
// functions.php or custom plugin
add_filter( 'wc_advanced_packages_is_printify_item', function( $is_printify, $product, $cart_item ) {
    // Check for different meta key
    if ( $product->get_meta( '_custom_printify_id' ) ) {
        return true;
    }
    
    // Check for product attribute
    $printify_attr = $product->get_attribute( 'printify-product' );
    if ( $printify_attr === 'yes' ) {
        return true;
    }
    
    // Check for specific product categories
    $categories = $product->get_category_ids();
    $printify_categories = array( 45, 67, 89 ); // Your Printify category IDs
    if ( array_intersect( $categories, $printify_categories ) ) {
        return true;
    }
    
    return $is_printify;
}, 10, 3 );
```

### 2. Custom Pickup Item Detection

Use product categories instead of shipping classes:

```php
add_filter( 'wc_advanced_packages_is_pickup_item', function( $is_pickup, $product, $cart_item ) {
    // Use product categories for pickup detection
    $categories = $product->get_category_ids();
    $pickup_categories = array( 123, 456 ); // Your pickup category IDs
    
    if ( array_intersect( $categories, $pickup_categories ) ) {
        return true;
    }
    
    // Check for custom product meta
    if ( $product->get_meta( '_local_pickup_only' ) === 'yes' ) {
        return true;
    }
    
    // Check for specific product tags
    $tags = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'slugs' ) );
    if ( in_array( 'pickup-only', $tags ) ) {
        return true;
    }
    
    return $is_pickup;
}, 10, 3 );
```

## Advanced Package Customization

### 3. Adding Express Shipping Package

Create a fourth package for express shipping items:

```php
// Add express items to grouped items
add_filter( 'wc_advanced_packages_group_items', function( $grouped_items, $default_package ) {
    $express_items = array();
    
    // Check items for express shipping flag
    foreach ( $grouped_items['other'] as $item_key => $cart_item ) {
        $product = $cart_item['data'];
        
        // Check for express shipping meta
        if ( $product->get_meta( '_express_shipping' ) === 'yes' ) {
            $express_items[ $item_key ] = $cart_item;
            unset( $grouped_items['other'][ $item_key ] );
        }
        
        // Or check for expensive items (over $100)
        elseif ( $product->get_price() > 100 ) {
            $express_items[ $item_key ] = $cart_item;
            unset( $grouped_items['other'][ $item_key ] );
        }
    }
    
    // Add express group
    $grouped_items['express'] = $express_items;
    
    return $grouped_items;
}, 10, 2 );

// Create express package (you'd need to modify this in the main plugin or extend it)
add_action( 'init', function() {
    // Hook into the package creation process
    // This example shows the concept - actual implementation would need 
    // to be integrated into the main plugin logic
});
```

### 4. Weight-Based Package Splitting

Split packages based on total weight:

```php
add_filter( 'wc_advanced_packages_group_items', function( $grouped_items, $default_package ) {
    $heavy_items = array();
    $light_items = array();
    $weight_threshold = 10; // 10 kg threshold
    
    foreach ( $grouped_items['other'] as $item_key => $cart_item ) {
        $product = $cart_item['data'];
        $item_weight = $product->get_weight() * $cart_item['quantity'];
        
        if ( $item_weight > $weight_threshold ) {
            $heavy_items[ $item_key ] = $cart_item;
        } else {
            $light_items[ $item_key ] = $cart_item;
        }
    }
    
    $grouped_items['other'] = $light_items;
    $grouped_items['heavy'] = $heavy_items;
    
    return $grouped_items;
}, 10, 2 );
```

## Shipping Method Customization

### 5. Custom Shipping Methods for Different Packages

Allow different shipping methods based on package type:

```php
add_filter( 'woocommerce_package_rates', function( $rates, $package ) {
    // Custom handling for Printify packages
    if ( isset( $package['is_printify'] ) && $package['is_printify'] ) {
        // Only allow free shipping for Printify items over $50
        $package_total = $package['contents_cost'];
        if ( $package_total < 50 ) {
            // Remove free shipping
            foreach ( $rates as $rate_id => $rate ) {
                if ( $rate->get_method_id() === 'free_shipping' ) {
                    unset( $rates[ $rate_id ] );
                }
            }
        }
    }
    
    // Custom handling for heavy items
    if ( isset( $package['is_heavy'] ) && $package['is_heavy'] ) {
        // Force expensive shipping for heavy items
        foreach ( $rates as $rate_id => $rate ) {
            if ( $rate->get_method_id() === 'flat_rate' ) {
                $rate->cost = $rate->cost * 1.5; // 50% surcharge
            }
        }
    }
    
    return $rates;
}, 20, 2 ); // Lower priority to run after our plugin
```

### 6. Hide Specific Shipping Methods

Hide certain shipping methods based on package contents:

```php
add_filter( 'woocommerce_package_rates', function( $rates, $package ) {
    // Hide expedited shipping for fragile items
    $has_fragile_items = false;
    
    foreach ( $package['contents'] as $cart_item ) {
        $product = $cart_item['data'];
        if ( $product->get_meta( '_fragile_item' ) === 'yes' ) {
            $has_fragile_items = true;
            break;
        }
    }
    
    if ( $has_fragile_items ) {
        foreach ( $rates as $rate_id => $rate ) {
            // Remove expedited/express shipping methods
            if ( strpos( $rate_id, 'express' ) !== false || 
                 strpos( $rate_id, 'expedited' ) !== false ) {
                unset( $rates[ $rate_id ] );
            }
        }
    }
    
    return $rates;
}, 20, 2 );
```

## User Role Based Customization

### 7. Different Package Behavior for User Roles

```php
add_filter( 'woocommerce_cart_shipping_packages', function( $packages ) {
    // Skip package splitting for VIP customers
    if ( wc_current_user_has_role( 'vip_customer' ) ) {
        return $packages; // Return original single package
    }
    
    // Special handling for wholesale customers
    if ( wc_current_user_has_role( 'wholesale_customer' ) ) {
        // Maybe group differently or add special packages
        // This would need to integrate with the main plugin logic
    }
    
    return $packages; // Let plugin handle normally
}, 5, 1 ); // Higher priority to run before our plugin
```

### 8. Conditional Pickup Restrictions

```php
add_filter( 'wc_advanced_packages_allowed_methods_for_pickup', function( $allowed_methods, $package ) {
    // Allow delivery for premium customers
    if ( wc_current_user_has_role( 'premium_customer' ) ) {
        $allowed_methods[] = 'flat_rate';
        $allowed_methods[] = 'free_shipping';
    }
    
    // Check package value
    if ( $package['contents_cost'] > 200 ) {
        $allowed_methods[] = 'premium_delivery';
    }
    
    return $allowed_methods;
}, 10, 2 );
```

## Dynamic Package Labels

### 9. Smart Package Naming

```php
add_filter( 'woocommerce_shipping_package_name', function( $package_name, $i, $package ) {
    // Custom naming for pickup packages
    if ( isset( $package['is_pickup'] ) && $package['is_pickup'] ) {
        $item_count = count( $package['contents'] );
        $total_value = $package['contents_cost'];
        
        return sprintf( 
            __( 'Pickup Items (%d items - %s)', 'your-textdomain' ), 
            $item_count, 
            wc_price( $total_value ) 
        );
    }
    
    // Custom naming for Printify packages
    if ( isset( $package['is_printify'] ) && $package['is_printify'] ) {
        return __( 'Print-on-Demand Items', 'your-textdomain' );
    }
    
    // Add weight information to other packages
    if ( isset( $package['is_other'] ) && $package['is_other'] ) {
        $total_weight = 0;
        foreach ( $package['contents'] as $cart_item ) {
            $product = $cart_item['data'];
            $total_weight += $product->get_weight() * $cart_item['quantity'];
        }
        
        if ( $total_weight > 0 ) {
            return sprintf( 
                __( 'Standard Items (%.2f kg)', 'your-textdomain' ), 
                $total_weight 
            );
        }
    }
    
    return $package_name;
}, 10, 3 );
```

## Integration with Other Plugins

### 10. WooCommerce Subscriptions Integration

```php
add_filter( 'wc_advanced_packages_is_pickup_item', function( $is_pickup, $product, $cart_item ) {
    // Handle subscription products differently
    if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
        // Maybe force subscription products to standard shipping
        return false;
    }
    
    return $is_pickup;
}, 10, 3 );
```

### 11. Multi-Vendor Integration

```php
add_filter( 'wc_advanced_packages_group_items', function( $grouped_items, $default_package ) {
    // Group items by vendor instead of/in addition to other criteria
    $vendor_groups = array();
    
    foreach ( $grouped_items['other'] as $item_key => $cart_item ) {
        $product = $cart_item['data'];
        $vendor_id = get_post_field( 'post_author', $product->get_id() );
        
        if ( ! isset( $vendor_groups[ $vendor_id ] ) ) {
            $vendor_groups[ $vendor_id ] = array();
        }
        
        $vendor_groups[ $vendor_id ][ $item_key ] = $cart_item;
    }
    
    // Replace 'other' with vendor-specific groups
    unset( $grouped_items['other'] );
    foreach ( $vendor_groups as $vendor_id => $items ) {
        $grouped_items[ 'vendor_' . $vendor_id ] = $items;
    }
    
    return $grouped_items;
}, 10, 2 );
```

## Debugging and Testing

### 12. Debug Package Information

```php
add_action( 'woocommerce_cart_totals_before_shipping', function() {
    if ( ! current_user_can( 'manage_options' ) || ! WP_DEBUG ) {
        return;
    }
    
    $packages = WC()->shipping()->get_packages();
    echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; font-size: 12px;">';
    echo '<strong>Debug: Shipping Packages</strong><br>';
    foreach ( $packages as $i => $package ) {
        echo "Package $i: " . count( $package['contents'] ) . ' items<br>';
        if ( isset( $package['is_pickup'] ) ) echo '- Is Pickup Package<br>';
        if ( isset( $package['is_printify'] ) ) echo '- Is Printify Package<br>';
        if ( isset( $package['is_other'] ) ) echo '- Is Other Package<br>';
        echo '- Cost: ' . wc_price( $package['contents_cost'] ) . '<br>';
    }
    echo '</div>';
});
```

### 13. Log Package Creation

```php
add_filter( 'wc_advanced_packages_final_packages', function( $packages, $original_packages ) {
    if ( WP_DEBUG_LOG ) {
        $logger = wc_get_logger();
        $context = array( 'source' => 'wc-advanced-packages' );
        
        $logger->info( sprintf( 
            'Created %d packages from %d original packages', 
            count( $packages ), 
            count( $original_packages ) 
        ), $context );
        
        foreach ( $packages as $i => $package ) {
            $type = 'unknown';
            if ( isset( $package['is_pickup'] ) ) $type = 'pickup';
            elseif ( isset( $package['is_printify'] ) ) $type = 'printify';
            elseif ( isset( $package['is_other'] ) ) $type = 'other';
            
            $logger->info( sprintf( 
                'Package %d (%s): %d items, cost: %s', 
                $i, 
                $type, 
                count( $package['contents'] ),
                $package['contents_cost']
            ), $context );
        }
    }
    
    return $packages;
}, 10, 2 );
```

## Performance Optimizations

### 14. Cache Expensive Operations

```php
add_filter( 'wc_advanced_packages_is_printify_item', function( $is_printify, $product, $cart_item ) {
    static $printify_cache = array();
    
    $product_id = $product->get_id();
    
    // Check cache first
    if ( isset( $printify_cache[ $product_id ] ) ) {
        return $printify_cache[ $product_id ];
    }
    
    // Expensive operation here
    $is_printify = perform_expensive_printify_check( $product );
    
    // Cache result
    $printify_cache[ $product_id ] = $is_printify;
    
    return $is_printify;
}, 10, 3 );
```

These examples should give you a solid foundation for customizing the WooCommerce Advanced Packages plugin to meet specific requirements. Remember to test thoroughly after implementing any customizations!




