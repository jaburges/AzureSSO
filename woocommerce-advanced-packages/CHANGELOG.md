# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-01-01

### Added
- Initial release of WooCommerce Advanced Packages
- Automatic shipping package splitting based on product criteria
- Support for Printify items detection via meta keys
- Free pickup items identification via shipping classes
- Comprehensive admin settings page under WooCommerce menu
- Shipping method filtering for pickup packages
- Customizable package labels
- Multiple hooks and filters for developer customization
- Debug information in admin interface
- Compatibility with WooCommerce 8.x+ and WordPress 6.x+
- Reset to defaults functionality in admin
- Comprehensive documentation and examples

### Package Splitting Features
- Printify items package (identified by meta key, default: `_printify_blueprint_id`)
- Free pickup items package (identified by shipping class slug, default: `free-pickup`)
- Other items package (fallback for remaining products)
- Automatic package creation only when items exist in each category
- Proper cost calculation and coupon inheritance per package

### Shipping Method Filtering
- Hide all shipping methods except local pickup for pickup packages
- Configurable allowed shipping methods
- Package-specific shipping method filtering
- Maintains normal shipping behavior for non-pickup packages

### Admin Interface
- Settings page under WooCommerce > Advanced Packages
- Enable/disable plugin functionality
- Configurable meta keys and shipping class slugs
- Customizable package display labels
- Debug information showing current shipping classes and methods
- Reset to defaults with confirmation
- Responsive design for mobile admin access

### Developer Customization
- `wc_advanced_packages_is_printify_item` filter
- `wc_advanced_packages_is_pickup_item` filter  
- `wc_advanced_packages_group_items` filter
- `wc_advanced_packages_final_packages` filter
- `wc_advanced_packages_allowed_methods_for_pickup` filter
- Comprehensive code examples and documentation

### Compatibility
- WooCommerce 8.0 - 8.5 tested
- WordPress 6.0 - 6.4 tested
- PHP 7.4+ support
- WooCommerce Subscriptions compatibility
- Variable Products support
- Grouped Products support
- Internationalization ready

### Documentation
- Comprehensive README with setup guide
- EXAMPLES.md with practical code samples
- Inline code documentation
- Admin page help sections
- Troubleshooting guide




