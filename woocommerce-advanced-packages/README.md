# WooCommerce Advanced Packages

A comprehensive WordPress plugin that automatically splits WooCommerce shipping packages based on product criteria - Printify items, Free Pickup items, and other products.

## Features

- **Automatic Package Splitting**: Intelligently categorizes cart items into separate shipping packages
- **Printify Integration**: Identifies Printify products using customizable meta keys
- **Pickup Item Filtering**: Restricts shipping methods for pickup items to local pickup only
- **Flexible Configuration**: Comprehensive admin settings for easy customization
- **Developer Friendly**: Multiple hooks and filters for advanced customization
- **WooCommerce Native**: Uses WooCommerce hooks and filters for maximum compatibility

## Installation

1. Download the plugin files
2. Upload the `woocommerce-advanced-packages` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **WooCommerce > Advanced Packages** to configure settings

## Requirements

- WordPress 6.0 or higher
- WooCommerce 8.0 or higher
- PHP 7.4 or higher
- Printify WooCommerce integration plugin (for Printify functionality)

## Setup Guide

### Step 1: Basic Configuration

1. Go to **WooCommerce > Advanced Packages** in your admin dashboard
2. Enable the plugin by checking "Enable Package Splitting"
3. Configure the basic settings:
   - **Printify Meta Key**: Default `_printify_blueprint_id` (adjust if your Printify integration uses a different key)
   - **Pickup Shipping Class**: Default `free-pickup` (must match your WooCommerce shipping class slug)
   - **Allowed Pickup Methods**: Default `local_pickup` (shipping method IDs allowed for pickup packages)

### Step 2: Shipping Classes Setup

1. In WooCommerce, go to **WooCommerce > Settings > Shipping > Shipping Classes**
2. Create a new shipping class called "Free Pickup" with slug `free-pickup`
3. Assign this shipping class to products that should only be available for pickup

### Step 3: Local Pickup Configuration

1. Go to **WooCommerce > Settings > Shipping**
2. Edit your shipping zones
3. Ensure "Local Pickup" shipping method is enabled where needed

### Step 4: Testing

1. Add items from different categories to your cart:
   - Printify products (with Printify meta data)
   - Products with "Free Pickup" shipping class
   - Regular products
2. Go to cart/checkout and verify separate shipping packages appear
3. Confirm pickup items only show local pickup options

## How It Works

### Package Splitting Logic

The plugin uses the `woocommerce_cart_shipping_packages` filter to intercept and split cart items:

1. **Printify Items**: Identified by meta key (default: `_printify_blueprint_id`)
2. **Pickup Items**: Identified by shipping class slug (default: `free-pickup`)
3. **Other Items**: All remaining shippable products

### Shipping Method Filtering

For pickup packages, the plugin uses `woocommerce_package_rates` filter to:
- Hide all shipping methods except allowed ones (default: `local_pickup`)
- Preserve normal shipping behavior for other packages

## Customization

### Available Filters

The plugin provides several filters for advanced customization:

#### `wc_advanced_packages_is_printify_item`

Customize how Printify items are identified:

```php
add_filter( 'wc_advanced_packages_is_printify_item', function( $is_printify, $product, $cart_item ) {
    // Custom logic to identify Printify items
    // Check for custom meta or product attributes
    if ( $product->get_meta( '_custom_printify_flag' ) ) {
        return true;
    }
    
    return $is_printify;
}, 10, 3 );
```

#### `wc_advanced_packages_is_pickup_item`

Customize how pickup items are identified:

```php
add_filter( 'wc_advanced_packages_is_pickup_item', function( $is_pickup, $product, $cart_item ) {
    // Custom logic for pickup items
    // Maybe check product categories instead of shipping class
    $categories = $product->get_category_ids();
    if ( in_array( 123, $categories ) ) { // Category ID 123 for pickup items
        return true;
    }
    
    return $is_pickup;
}, 10, 3 );
```

#### `wc_advanced_packages_group_items`

Modify the grouped items before packages are created:

```php
add_filter( 'wc_advanced_packages_group_items', function( $grouped_items, $default_package ) {
    // Add custom logic to regroup items
    // Maybe move expensive items to a special package
    
    return $grouped_items;
}, 10, 2 );
```

#### `wc_advanced_packages_final_packages`

Modify the final packages before they're returned:

```php
add_filter( 'wc_advanced_packages_final_packages', function( $packages, $original_packages ) {
    // Add express shipping package for high-priority items
    // Modify package contents or add package-specific data
    
    return $packages;
}, 10, 2 );
```

#### `wc_advanced_packages_allowed_methods_for_pickup`

Customize allowed shipping methods for pickup packages:

```php
add_filter( 'wc_advanced_packages_allowed_methods_for_pickup', function( $allowed_methods, $package ) {
    // Allow additional methods for pickup packages
    $allowed_methods[] = 'custom_pickup_method';
    
    return $allowed_methods;
}, 10, 2 );
```

### Advanced Customization Examples

#### Custom Package Types

Add a fourth package type for expedited items:

```php
// Add filter to identify expedited items
add_filter( 'wc_advanced_packages_group_items', function( $grouped_items, $default_package ) {
    $expedited_items = array();
    
    // Check each "other" item for expedited flag
    foreach ( $grouped_items['other'] as $item_key => $cart_item ) {
        $product = $cart_item['data'];
        if ( $product->get_meta( '_expedited_shipping' ) ) {
            $expedited_items[ $item_key ] = $cart_item;
            unset( $grouped_items['other'][ $item_key ] );
        }
    }
    
    $grouped_items['expedited'] = $expedited_items;
    
    return $grouped_items;
}, 10, 2 );

// Create expedited package
add_filter( 'wc_advanced_packages_final_packages', function( $packages, $original_packages ) {
    // This would need to be integrated into the main plugin logic
    // or you could extend the plugin class
    
    return $packages;
}, 10, 2 );
```

#### Dynamic Package Labels

Change package labels based on contents:

```php
add_filter( 'woocommerce_shipping_package_name', function( $package_name, $i, $package ) {
    if ( isset( $package['is_pickup'] ) && $package['is_pickup'] ) {
        $item_count = count( $package['contents'] );
        return sprintf( __( 'Pickup Items (%d items)', 'your-textdomain' ), $item_count );
    }
    
    return $package_name;
}, 10, 3 );
```

#### Conditional Package Splitting

Only split packages for specific user roles:

```php
add_filter( 'woocommerce_cart_shipping_packages', function( $packages ) {
    // Only split for wholesale customers
    if ( ! wc_current_user_has_role( 'wholesale_customer' ) ) {
        return $packages; // Return original packages
    }
    
    // Continue with normal splitting logic
    return $packages;
}, 5, 1 ); // Higher priority than our plugin
```

## Troubleshooting

### Common Issues

1. **Packages not splitting**: 
   - Check that the plugin is enabled in settings
   - Verify shipping class slugs match exactly
   - Ensure products have the correct meta data or shipping class

2. **Wrong shipping methods showing**:
   - Check "Allowed Pickup Methods" setting
   - Verify shipping method IDs are correct
   - Check shipping zone configurations

3. **Printify items not detected**:
   - Verify the Printify meta key setting
   - Check products have the correct meta data
   - Use browser dev tools to inspect product meta

### Debug Information

The admin page includes debug information showing:
- Current shipping classes and their slugs
- Available shipping methods and their IDs

### Logging

Enable WooCommerce logging and check logs for any errors:
1. Go to **WooCommerce > Status > Logs**
2. Look for logs prefixed with `wc-advanced-packages`

## Compatibility

### Tested With

- WooCommerce 8.0 - 8.5
- WordPress 6.0 - 6.4
- Printify WooCommerce Integration
- WooCommerce Subscriptions
- WooCommerce Variable Products

### Known Conflicts

- Some shipping plugins that heavily modify package behavior may conflict
- Third-party checkout plugins might not display packages correctly

## Support

### Documentation

- Check the admin page for quick setup guide
- Review the debug information for current configuration
- Test with simple scenarios first

### Customization

This plugin is designed to be developer-friendly. Most customizations can be achieved through the provided filters without modifying core plugin files.

## Changelog

### Version 1.0.0
- Initial release
- Package splitting functionality
- Admin settings page
- Customization hooks
- Printify integration
- Pickup item filtering

## License

GPL-2.0+ - See LICENSE file for details

## Contributing

When contributing to this plugin, please:
1. Follow WordPress coding standards
2. Test thoroughly with different WooCommerce setups
3. Document any new filters or hooks
4. Ensure backward compatibility




