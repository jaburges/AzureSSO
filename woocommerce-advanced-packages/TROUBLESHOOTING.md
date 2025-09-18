# WooCommerce Advanced Packages - Troubleshooting Guide

## Common Issues and Solutions

### Issue: "No shipping options are available for this address" 

This is the most common issue and can have several causes:

#### 1. **Package Structure Issues (Most Likely Cause)**

**Symptoms:**
- Shipping toggle disappeared
- "No shipping options available" message
- Previously working shipping stops working after plugin activation

**Solution:**
The plugin has been updated to fix package structure issues. Make sure you're using the latest version of the plugin files.

**What was fixed:**
- Added missing WooCommerce package fields (`ship_via`, proper destination structure)
- Added safety checks for package data
- Improved debug logging

#### 2. **Shipping Zone Configuration**

**Check:**
- Go to **WooCommerce > Settings > Shipping > Shipping Zones**
- Verify your customer's location (e.g., WA, US) is included in a shipping zone
- Ensure the zone has active shipping methods

**Debug Steps:**
1. Enable Debug Mode in plugin settings
2. View cart/checkout as admin to see debug information
3. Check which packages are created and what shipping methods are available

#### 3. **Missing Shipping Methods**

**For Pickup Packages:**
- Ensure "Local Pickup" is enabled in your shipping zones
- Check the "Allowed Pickup Methods" setting matches your actual method ID
- Default is `local_pickup` - change if you use a different pickup method

**For Other Packages:**
- Verify standard shipping methods (Flat Rate, Free Shipping, etc.) are configured
- Check shipping method conditions (minimum order amounts, etc.)

## Quick Diagnosis Steps

### Step 1: Enable Debug Mode
1. Go to **WooCommerce > Advanced Packages**
2. Check "Enable Debug Mode"
3. Save settings
4. View cart/checkout as an admin to see debug information

### Step 2: Use Debug Script
1. Add items to your cart
2. Navigate to: `/wp-content/plugins/woocommerce-advanced-packages/debug-packages.php`
3. Review the detailed package and shipping information

### Step 3: Check Plugin Settings
- **Printify Meta Key**: Default `_printify_blueprint_id` - verify this matches your Printify integration
- **Pickup Shipping Class**: Default `free-pickup` - must match exactly
- **Allowed Pickup Methods**: Default `local_pickup` - must match your shipping method ID

### Step 4: Verify Item Categorization
Items should be categorized as:
- **Printify Items**: Have the specified meta key with a non-empty value
- **Pickup Items**: Have the specified shipping class
- **Other Items**: Everything else that needs shipping

## Specific Fixes

### Fix 1: Update Package Structure (Already Applied)
The plugin has been updated to include all required WooCommerce package fields:
- `ship_via` field added
- Destination structure improved
- Safety checks for missing data

### Fix 2: Shipping Method ID Mismatch
If pickup items still show all shipping methods:

1. Check your actual Local Pickup method ID:
   - Go to **WooCommerce > Settings > Shipping**
   - Edit your shipping zone
   - Note the exact ID of your Local Pickup method

2. Update plugin settings:
   - Go to **WooCommerce > Advanced Packages**
   - Update "Allowed Pickup Methods" field
   - Common alternatives: `local_pickup`, `pickup_location`, `local_delivery`

### Fix 3: Shipping Class Issues
If items aren't being categorized correctly:

1. Check shipping class slug:
   - Go to **WooCommerce > Settings > Shipping > Shipping Classes**
   - Verify the slug matches your plugin setting exactly

2. Assign shipping class to products:
   - Edit products that should be pickup-only
   - Set shipping class to your pickup class

### Fix 4: Printify Integration Issues
If Printify items aren't being detected:

1. Check a Printify product's meta data:
   ```php
   // Add to functions.php temporarily for debugging
   add_action( 'wp_footer', function() {
       if ( is_product() ) {
           $product = wc_get_product( get_the_ID() );
           $meta = $product->get_meta_data();
           echo '<pre style="display:none;">';
           foreach ( $meta as $meta_item ) {
               echo $meta_item->key . ' = ' . $meta_item->value . "\n";
           }
           echo '</pre>';
       }
   });
   ```

2. Update the Printify Meta Key setting to match what you find

## Advanced Debugging

### Enable WooCommerce Logging
1. Add to `wp-config.php`: `define( 'WP_DEBUG_LOG', true );`
2. Go to **WooCommerce > Status > Logs**
3. Look for logs with source `wc-advanced-packages`

### Disable Plugin Temporarily
If you need to quickly restore normal shipping:
1. Go to **WooCommerce > Advanced Packages**
2. Uncheck "Enable Package Splitting"
3. Save settings

This disables package splitting while keeping your settings.

### Test with Simple Scenario
1. Empty cart
2. Add one product with pickup shipping class
3. Add one regular product
4. Check if two packages are created in debug mode

## Common Plugin Conflicts

### Shipping Plugins
- Other plugins that modify `woocommerce_cart_shipping_packages` may conflict
- Check plugin priorities and load order

### Checkout Plugins
- Custom checkout plugins may not display multiple packages correctly
- Test with default WooCommerce checkout

### Caching Plugins
- Cart/shipping data may be cached
- Clear all caches after making changes

## Getting Help

### Information to Provide
When reporting issues, include:

1. **Plugin Settings Screenshot**
2. **Debug Information** (from cart/checkout when debug mode enabled)  
3. **Shipping Zone Configuration**
4. **Sample Products** (shipping classes, meta data)
5. **Cart Contents** during testing
6. **Error Messages** from logs

### Test Environment
- Test on staging site first
- Use default WooCommerce theme for testing
- Disable other plugins to isolate issues

## Prevention

### After Setup
1. Test with different product combinations
2. Test with different shipping addresses
3. Verify checkout process completes successfully
4. Monitor logs for errors

### Regular Maintenance
1. Check settings after plugin updates
2. Verify compatibility with WooCommerce updates
3. Test after adding new shipping methods or zones

## Emergency Fixes

### Quick Disable
If the site breaks, add this to `functions.php`:
```php
add_filter( 'woocommerce_cart_shipping_packages', function( $packages ) {
    // Temporarily disable package splitting
    return $packages;
}, 5, 1 );
```

### Force Single Package
```php
add_filter( 'wc_advanced_packages_final_packages', function( $packages, $original ) {
    // Emergency: return original single package
    return $original;
}, 10, 2 );
```

Remove these fixes once the root issue is resolved.




