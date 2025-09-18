<?php
/**
 * Plugin Name: WooCommerce Advanced Packages
 * Plugin URI: https://github.com/yourcompany/woocommerce-advanced-packages
 * Description: Automatically splits WooCommerce shipping packages based on product criteria - Printify items, Free Pickup items, and other products.
 * Version: 1.0.0
 * Author: Your Company
 * Author URI: https://yourcompany.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wc-advanced-packages
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 8.5
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'WC_ADVANCED_PACKAGES_VERSION', '1.0.0' );
define( 'WC_ADVANCED_PACKAGES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_ADVANCED_PACKAGES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_ADVANCED_PACKAGES_PLUGIN_FILE', __FILE__ );

/**
 * Main WooCommerce Advanced Packages Class
 */
class WC_Advanced_Packages {
    
    /**
     * Plugin instance
     * @var WC_Advanced_Packages
     */
    private static $_instance = null;
    
    /**
     * Plugin settings
     * @var array
     */
    private $settings = array();
    
    /**
     * Get plugin instance
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if ( ! $this->is_woocommerce_active() ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }
        
        // Load settings
        $this->load_settings();
        
        // Initialize hooks
        $this->init_hooks();
        
        // Load admin functionality
        if ( is_admin() ) {
            $this->init_admin();
        }
        
        // Load textdomain for translations
        load_plugin_textdomain( 'wc-advanced-packages', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
    
    /**
     * Load plugin settings
     */
    private function load_settings() {
        $defaults = array(
            'printify_meta_key' => '_printify_blueprint_id',
            'pickup_shipping_class' => 'free-pickup',
            'allowed_pickup_methods' => 'local_pickup',
            'printify_package_label' => __( 'Printify Items', 'wc-advanced-packages' ),
            'pickup_package_label' => __( 'Local Pickup Items', 'wc-advanced-packages' ),
            'other_package_label' => __( 'Other Items', 'wc-advanced-packages' ),
            'enabled' => 'yes',
            'debug_mode' => 'no'
        );
        
        $saved_settings = get_option( 'wc_advanced_packages_settings', array() );
        $this->settings = wp_parse_args( $saved_settings, $defaults );
    }
    
    /**
     * Initialize hooks and filters
     */
    private function init_hooks() {
        if ( $this->settings['enabled'] !== 'yes' ) {
            return;
        }
        
        // Package splitting
        add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'split_shipping_packages' ), 10, 1 );
        
        // Shipping method filtering
        add_filter( 'woocommerce_package_rates', array( $this, 'filter_shipping_methods' ), 10, 2 );
        
        // Add package labels to checkout
        add_filter( 'woocommerce_shipping_package_name', array( $this, 'customize_package_names' ), 10, 3 );
        
        // Add debug display if enabled (safer approach)
        if ( $this->settings['debug_mode'] === 'yes' ) {
            // Only show debug on cart page, not checkout to avoid JS conflicts
            add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'display_debug_info' ) );
            
            // Add admin bar debug link
            add_action( 'admin_bar_menu', array( $this, 'add_debug_admin_bar' ), 100 );
            
            // Add shortcode for debug info
            add_shortcode( 'wc_packages_debug', array( $this, 'debug_shortcode' ) );
            
            // Add console debug output
            add_action( 'wp_footer', array( $this, 'add_console_debug' ) );
        }
        
        // Always add the frontend shipping UI modifications
        add_action( 'wp_footer', array( $this, 'modify_checkout_shipping_ui' ) );
    }
    
    /**
     * Initialize admin functionality
     */
    private function init_admin() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_wc_advanced_packages_reset_settings', array( $this, 'reset_settings_ajax' ) );
    }
    
    /**
     * Split shipping packages based on product criteria
     */
    public function split_shipping_packages( $packages ) {
        // Get the default package
        $default_package = reset( $packages );
        
        if ( empty( $default_package['contents'] ) ) {
            return $packages;
        }
        
        // Initialize package arrays
        $printify_items = array();
        $pickup_items = array();
        $other_items = array();
        
        // Categorize cart items
        foreach ( $default_package['contents'] as $item_key => $cart_item ) {
            $product = $cart_item['data'];
            
            if ( ! $product || ! $product->needs_shipping() ) {
                continue;
            }
            
            // Check if it's a Printify item
            if ( $this->is_printify_item( $product, $cart_item ) ) {
                $printify_items[ $item_key ] = $cart_item;
            }
            // Check if it's a pickup item
            elseif ( $this->is_pickup_item( $product, $cart_item ) ) {
                $pickup_items[ $item_key ] = $cart_item;
            }
            // Otherwise, it's an other item
            else {
                $other_items[ $item_key ] = $cart_item;
            }
        }
        
        // Apply filter to allow customization
        $grouped_items = apply_filters( 'wc_advanced_packages_group_items', array(
            'printify' => $printify_items,
            'pickup' => $pickup_items,
            'other' => $other_items
        ), $default_package );
        
        // Create new packages
        $new_packages = array();
        
        // Create pickup package
        if ( ! empty( $grouped_items['pickup'] ) ) {
            $pickup_package = $this->create_package( $grouped_items['pickup'], $default_package );
            $pickup_package['is_pickup'] = true;
            $pickup_package['package_name'] = $this->settings['pickup_package_label'];
            $new_packages[] = $pickup_package;
        }
        
        // Create printify package
        if ( ! empty( $grouped_items['printify'] ) ) {
            $printify_package = $this->create_package( $grouped_items['printify'], $default_package );
            $printify_package['is_printify'] = true;
            $printify_package['package_name'] = $this->settings['printify_package_label'];
            $new_packages[] = $printify_package;
        }
        
        // Create other package
        if ( ! empty( $grouped_items['other'] ) ) {
            $other_package = $this->create_package( $grouped_items['other'], $default_package );
            $other_package['is_other'] = true;
            $other_package['package_name'] = $this->settings['other_package_label'];
            $new_packages[] = $other_package;
        }
        
        // If no packages were created, return original
        if ( empty( $new_packages ) ) {
            return $packages;
        }
        
        // Apply final filter
        $final_packages = apply_filters( 'wc_advanced_packages_final_packages', $new_packages, $packages );
        
        // Debug logging
        if ( WP_DEBUG_LOG && function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            $context = array( 'source' => 'wc-advanced-packages' );
            
            $logger->info( sprintf( 
                'Package splitting: %d original packages, created %d new packages', 
                count( $packages ),
                count( $final_packages )
            ), $context );
            
            foreach ( $final_packages as $i => $package ) {
                $type = 'unknown';
                if ( isset( $package['is_pickup'] ) ) $type = 'pickup';
                elseif ( isset( $package['is_printify'] ) ) $type = 'printify';
                elseif ( isset( $package['is_other'] ) ) $type = 'other';
                
                $logger->info( sprintf( 
                    'Package %d (%s): %d items, cost: %s, destination: %s', 
                    $i,
                    $type,
                    count( $package['contents'] ),
                    $package['contents_cost'],
                    isset( $package['destination']['country'] ) ? $package['destination']['country'] : 'unknown'
                ), $context );
            }
        }
        
        return $final_packages;
    }
    
    /**
     * Check if product is a Printify item
     */
    private function is_printify_item( $product, $cart_item ) {
        $meta_key = $this->settings['printify_meta_key'];
        
        // Check product meta
        $printify_meta = $product->get_meta( $meta_key );
        if ( ! empty( $printify_meta ) ) {
            return true;
        }
        
        // Check cart item meta (for variations)
        if ( isset( $cart_item[ $meta_key ] ) && ! empty( $cart_item[ $meta_key ] ) ) {
            return true;
        }
        
        // Allow custom filtering
        return apply_filters( 'wc_advanced_packages_is_printify_item', false, $product, $cart_item );
    }
    
    /**
     * Check if product is a pickup item
     */
    private function is_pickup_item( $product, $cart_item ) {
        $shipping_class = $product->get_shipping_class();
        $pickup_class = $this->settings['pickup_shipping_class'];
        
        $is_pickup = ( $shipping_class === $pickup_class );
        
        // Allow custom filtering
        return apply_filters( 'wc_advanced_packages_is_pickup_item', $is_pickup, $product, $cart_item );
    }
    
    /**
     * Create a package from items
     */
    private function create_package( $items, $original_package ) {
        $contents_cost = 0;
        
        // Calculate contents cost
        foreach ( $items as $cart_item ) {
            $contents_cost += $cart_item['line_total'];
        }
        
        // Create package with all required WooCommerce fields
        $package = array(
            'contents'        => $items,
            'contents_cost'   => $contents_cost,
            'applied_coupons' => isset( $original_package['applied_coupons'] ) ? $original_package['applied_coupons'] : array(),
            'user'           => isset( $original_package['user'] ) ? $original_package['user'] : array(),
            'destination'    => isset( $original_package['destination'] ) ? $original_package['destination'] : array(),
            'ship_via'       => isset( $original_package['ship_via'] ) ? $original_package['ship_via'] : array(),
        );
        
        // Ensure destination has all required fields
        $package['destination'] = wp_parse_args( $package['destination'], array(
            'country'   => '',
            'state'     => '',
            'postcode'  => '',
            'city'      => '',
            'address'   => '',
            'address_2' => ''
        ) );
        
        return $package;
    }
    
    /**
     * Filter shipping methods for packages
     */
    public function filter_shipping_methods( $rates, $package ) {
        // Debug logging
        if ( WP_DEBUG_LOG ) {
            $logger = wc_get_logger();
            $context = array( 'source' => 'wc-advanced-packages' );
            
            $package_type = 'unknown';
            if ( isset( $package['is_pickup'] ) && $package['is_pickup'] ) {
                $package_type = 'pickup';
            } elseif ( isset( $package['is_printify'] ) && $package['is_printify'] ) {
                $package_type = 'printify';
            } elseif ( isset( $package['is_other'] ) && $package['is_other'] ) {
                $package_type = 'other';
            }
            
            $logger->info( sprintf( 
                'Filtering shipping methods for %s package. Available rates: %s', 
                $package_type,
                implode( ', ', array_keys( $rates ) )
            ), $context );
        }
        
        // For pickup packages, ensure Local Pickup is available if enabled
        if ( isset( $package['is_pickup'] ) && $package['is_pickup'] ) {
            // Check if Local Pickup is enabled globally - try multiple methods
            $local_pickup_enabled = false;
            $local_pickup_settings = array();
            
            // Method 1: Check settings option
            $settings_option = get_option( 'woocommerce_local_pickup_settings', array() );
            if ( isset( $settings_option['enabled'] ) && $settings_option['enabled'] === 'yes' ) {
                $local_pickup_enabled = true;
                $local_pickup_settings = $settings_option;
            }
            
            // Method 2: Check direct option
            elseif ( get_option( 'woocommerce_local_pickup_enabled' ) === 'yes' ) {
                $local_pickup_enabled = true;
            }
            
            // Method 3: Check shipping method class
            elseif ( class_exists( 'WC_Shipping_Local_Pickup' ) ) {
                $shipping_methods = WC()->shipping()->get_shipping_methods();
                if ( isset( $shipping_methods['local_pickup'] ) && $shipping_methods['local_pickup']->is_enabled() ) {
                    $local_pickup_enabled = true;
                    $local_pickup_settings = $shipping_methods['local_pickup']->settings;
                }
            }
            
            if ( $local_pickup_enabled ) {
                // If no rates available or Local Pickup missing, add it manually
                $has_local_pickup = false;
                foreach ( $rates as $rate ) {
                    if ( $rate->get_method_id() === 'local_pickup' ) {
                        $has_local_pickup = true;
                        break;
                    }
                }
                
                if ( ! $has_local_pickup ) {
                    // Create Local Pickup rate manually
                    $local_pickup_title = __( 'Local pickup', 'woocommerce' );
                    $local_pickup_cost = 0;
                    
                    // Get title and cost from settings if available
                    if ( ! empty( $local_pickup_settings ) ) {
                        if ( isset( $local_pickup_settings['title'] ) && ! empty( $local_pickup_settings['title'] ) ) {
                            $local_pickup_title = $local_pickup_settings['title'];
                        }
                        if ( isset( $local_pickup_settings['cost'] ) ) {
                            $local_pickup_cost = $local_pickup_settings['cost'];
                        }
                    }
                    
                    $local_pickup_rate = new WC_Shipping_Rate(
                        'local_pickup:1',
                        $local_pickup_title,
                        $local_pickup_cost,
                        array(),
                        'local_pickup'
                    );
                    
                    $rates['local_pickup:1'] = $local_pickup_rate;
                    
                    if ( WP_DEBUG_LOG && function_exists( 'wc_get_logger' ) ) {
                        $logger->info( 'Added Local Pickup rate manually for pickup package', $context );
                    }
                }
            }
            
            // Now filter to only allowed methods
            $allowed_methods = explode( ',', $this->settings['allowed_pickup_methods'] );
            $allowed_methods = array_map( 'trim', $allowed_methods );
            
            // Allow custom filtering of allowed methods
            $allowed_methods = apply_filters( 'wc_advanced_packages_allowed_methods_for_pickup', $allowed_methods, $package );
            
            // Filter rates
            $original_rates = $rates;
            foreach ( $rates as $rate_id => $rate ) {
                $method_id = $rate->get_method_id();
                
                if ( ! in_array( $method_id, $allowed_methods ) ) {
                    unset( $rates[ $rate_id ] );
                }
            }
            
            // Debug log the results
            if ( WP_DEBUG_LOG && function_exists( 'wc_get_logger' ) ) {
                $logger->info( sprintf( 
                    'Pickup package filtering: %d rates before, %d rates after. Allowed methods: %s', 
                    count( $original_rates ),
                    count( $rates ),
                    implode( ', ', $allowed_methods )
                ), $context );
            }
        }
        
        return $rates;
    }
    
    /**
     * Customize package names for display
     */
    public function customize_package_names( $package_name, $i, $package ) {
        if ( isset( $package['package_name'] ) ) {
            return $package['package_name'];
        }
        
        return $package_name;
    }
    
    /**
     * Display debug information on cart/checkout pages
     */
    public function display_debug_info() {
        // Only show to administrators
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Use output buffering to prevent breaking JavaScript
        ob_start();
        
        try {
            $packages = WC()->shipping()->get_packages();
            
            if ( empty( $packages ) ) {
                ob_end_clean();
                return;
            }
            
            ?>
            <div id="wc-advanced-packages-debug" style="background: #f0f0f0; border: 1px solid #ccc; padding: 15px; margin: 15px 0; font-family: monospace; font-size: 12px; position: relative;">
                <div style="position: absolute; top: 5px; right: 10px;">
                    <button type="button" onclick="document.getElementById('wc-advanced-packages-debug').style.display='none'" style="background: none; border: none; font-size: 16px; cursor: pointer; color: #666;">✕</button>
                </div>
                
                <strong style="color: #d63638;">🐛 Advanced Packages Debug Info (Admin Only)</strong><br><br>
                
                <strong>Plugin Settings:</strong><br>
                - Enabled: <?php echo ( $this->settings['enabled'] === 'yes' ? '✅ Yes' : '❌ No' ); ?><br>
                - Printify Meta Key: <?php echo esc_html( $this->settings['printify_meta_key'] ); ?><br>
                - Pickup Shipping Class: <?php echo esc_html( $this->settings['pickup_shipping_class'] ); ?><br>
                - Allowed Pickup Methods: <?php echo esc_html( $this->settings['allowed_pickup_methods'] ); ?><br><br>
                
                <strong>Shipping Packages (<?php echo count( $packages ); ?> total):</strong><br>
                
                <?php foreach ( $packages as $i => $package ) : ?>
                    <?php
                    $package_type = 'Standard';
                    $type_emoji = '📦';
                    
                    if ( isset( $package['is_pickup'] ) && $package['is_pickup'] ) {
                        $package_type = 'Pickup';
                        $type_emoji = '🏪';
                    } elseif ( isset( $package['is_printify'] ) && $package['is_printify'] ) {
                        $package_type = 'Printify';
                        $type_emoji = '🖨️';
                    } elseif ( isset( $package['is_other'] ) && $package['is_other'] ) {
                        $package_type = 'Other';
                        $type_emoji = '📋';
                    }
                    ?>
                    
                    <strong><?php echo $type_emoji; ?> Package <?php echo $i; ?> (<?php echo esc_html( $package_type ); ?>):</strong><br>
                    &nbsp;&nbsp;• Items: <?php echo count( $package['contents'] ); ?><br>
                    &nbsp;&nbsp;• Cost: <?php echo wc_price( $package['contents_cost'] ); ?><br>
                    &nbsp;&nbsp;• Destination: <?php echo isset( $package['destination']['country'] ) ? esc_html( $package['destination']['country'] ) : 'Unknown'; ?><br>
                    
                    <?php if ( isset( $package['contents'] ) && ! empty( $package['contents'] ) ) : ?>
                        &nbsp;&nbsp;• Products: 
                        <?php
                        $product_names = array();
                        foreach ( $package['contents'] as $cart_item ) {
                            $product = $cart_item['data'];
                            if ( $product ) {
                                $product_names[] = $product->get_name();
                            }
                        }
                        echo esc_html( implode( ', ', array_slice( $product_names, 0, 3 ) ) );
                        if ( count( $product_names ) > 3 ) {
                            echo ' + ' . ( count( $product_names ) - 3 ) . ' more';
                        }
                        ?>
                        <br>
                    <?php endif; ?>
                    <br>
                <?php endforeach; ?>
                
                <strong>Available Shipping Methods:</strong><br>
                <?php foreach ( $packages as $i => $package ) : ?>
                    <?php
                    $rates = WC()->shipping()->calculate_shipping_for_package( $package );
                    ?>
                    Package <?php echo $i; ?>: 
                    <?php if ( ! empty( $rates['rates'] ) ) : ?>
                        <?php
                        $method_names = array();
                        foreach ( $rates['rates'] as $rate ) {
                            $method_names[] = $rate->get_label() . ' (' . $rate->get_method_id() . ')';
                        }
                        echo esc_html( implode( ', ', $method_names ) );
                        ?>
                    <?php else : ?>
                        ❌ No shipping methods available
                    <?php endif; ?>
                    <br>
                <?php endforeach; ?>
                
                <br>
                <small><em>
                    Debug script available at: <a href="<?php echo home_url( '/wp-content/plugins/woocommerce-advanced-packages/debug-packages.php' ); ?>" target="_blank">debug-packages.php</a>
                </em></small>
            </div>
            <?php
            
        } catch ( Exception $e ) {
            // If there's an error, log it and show a simple message
            if ( function_exists( 'wc_get_logger' ) ) {
                $logger = wc_get_logger();
                $logger->error( 'Debug display error: ' . $e->getMessage(), array( 'source' => 'wc-advanced-packages' ) );
            }
            
            echo '<div style="background: #fee; border: 1px solid #f00; padding: 10px; margin: 15px 0; font-size: 12px;">';
            echo '<strong>Debug Error:</strong> Unable to display debug information. Check logs for details.';
            echo '</div>';
        }
        
        $output = ob_get_clean();
        
        // Only output if we have content and it's safe
        if ( ! empty( $output ) ) {
            echo $output;
        }
    }
    
    /**
     * Add debug link to admin bar
     */
    public function add_debug_admin_bar( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Only show on cart/checkout pages
        if ( ! is_cart() && ! is_checkout() ) {
            return;
        }
        
        $wp_admin_bar->add_node( array(
            'id'    => 'wc-advanced-packages-debug',
            'title' => '🐛 Package Debug',
            'href'  => home_url( '/wp-content/plugins/woocommerce-advanced-packages/debug-packages.php' ),
            'meta'  => array(
                'target' => '_blank',
                'title'  => 'Open WooCommerce Advanced Packages Debug Information'
            )
        ) );
    }
    
    /**
     * Debug shortcode - use [wc_packages_debug] to display debug info anywhere
     */
    public function debug_shortcode( $atts ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return '';
        }
        
        ob_start();
        $this->display_debug_info();
        return ob_get_clean();
    }
    
    /**
     * Add debug information to browser console
     */
    public function add_console_debug() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Only on cart/checkout pages
        if ( ! is_cart() && ! is_checkout() ) {
            return;
        }
        
        try {
            $packages = WC()->shipping()->get_packages();
            
            if ( empty( $packages ) ) {
                return;
            }
            
            $debug_data = array(
                'plugin_enabled' => $this->settings['enabled'] === 'yes',
                'printify_meta_key' => $this->settings['printify_meta_key'],
                'pickup_shipping_class' => $this->settings['pickup_shipping_class'],
                'allowed_pickup_methods' => $this->settings['allowed_pickup_methods'],
                'packages_count' => count( $packages ),
                'packages' => array()
            );
            
            foreach ( $packages as $i => $package ) {
                $package_info = array(
                    'index' => $i,
                    'type' => 'standard',
                    'items_count' => count( $package['contents'] ),
                    'cost' => $package['contents_cost'],
                    'destination_country' => isset( $package['destination']['country'] ) ? $package['destination']['country'] : 'unknown',
                    'products' => array()
                );
                
                if ( isset( $package['is_pickup'] ) && $package['is_pickup'] ) {
                    $package_info['type'] = 'pickup';
                } elseif ( isset( $package['is_printify'] ) && $package['is_printify'] ) {
                    $package_info['type'] = 'printify';
                } elseif ( isset( $package['is_other'] ) && $package['is_other'] ) {
                    $package_info['type'] = 'other';
                }
                
                foreach ( $package['contents'] as $cart_item ) {
                    $product = $cart_item['data'];
                    if ( $product ) {
                        $package_info['products'][] = array(
                            'name' => $product->get_name(),
                            'id' => $product->get_id(),
                            'shipping_class' => $product->get_shipping_class()
                        );
                    }
                }
                
                // Get shipping methods for this package
                $rates = WC()->shipping()->calculate_shipping_for_package( $package );
                $package_info['shipping_methods'] = array();
                if ( ! empty( $rates['rates'] ) ) {
                    foreach ( $rates['rates'] as $rate ) {
                        $package_info['shipping_methods'][] = array(
                            'id' => $rate->get_method_id(),
                            'label' => $rate->get_label(),
                            'cost' => $rate->get_cost()
                        );
                    }
                }
                
                $debug_data['packages'][] = $package_info;
            }
            
            ?>
            <script type="text/javascript">
                console.group('🐛 WC Advanced Packages Debug');
                console.log('Plugin Settings:', <?php echo wp_json_encode( array(
                    'enabled' => $debug_data['plugin_enabled'],
                    'printify_meta_key' => $debug_data['printify_meta_key'],
                    'pickup_shipping_class' => $debug_data['pickup_shipping_class'],
                    'allowed_pickup_methods' => $debug_data['allowed_pickup_methods']
                ) ); ?>);
                console.log('Total Packages:', <?php echo $debug_data['packages_count']; ?>);
                console.log('Package Details:', <?php echo wp_json_encode( $debug_data['packages'] ); ?>);
                console.log('Debug Script URL:', '<?php echo home_url( '/wp-content/plugins/woocommerce-advanced-packages/debug-packages.php' ); ?>');
                console.groupEnd();
            </script>
            <?php
            
        } catch ( Exception $e ) {
            // Silent fail for console debug
        }
    }
    
    /**
     * Modify checkout shipping UI to hide ship option when only pickup items
     */
    public function modify_checkout_shipping_ui() {
        // Only on cart and checkout pages
        if ( ! is_cart() && ! is_checkout() ) {
            return;
        }
        
        // Check if we have packages and if they're all pickup packages
        $packages = WC()->shipping()->get_packages();
        if ( empty( $packages ) ) {
            return;
        }
        
        $all_pickup = true;
        $has_pickup = false;
        
        foreach ( $packages as $package ) {
            if ( isset( $package['is_pickup'] ) && $package['is_pickup'] ) {
                $has_pickup = true;
            } else {
                $all_pickup = false;
            }
        }
        
        // If all packages are pickup packages, hide shipping options
        if ( $all_pickup && $has_pickup ) {
            ?>
            <style>
                /* Hide the Ship option in WooCommerce Block Checkout */
                .wc-block-checkout__shipping-method-option:has(.wc-block-checkout__shipping-method-option-title:contains("Ship")) {
                    display: none !important;
                }
                
                /* Alternative selector for browsers that don't support :has() */
                .wc-block-checkout__shipping-method-option[aria-label*="Ship"]:not([aria-label*="Pickup"]) {
                    display: none !important;
                }
                
                /* Hide Ship option by checking the title text */
                .wc-block-checkout__shipping-method-option-title:contains("Ship") {
                    display: none !important;
                }
                
                /* Ensure Pickup option is visible and selected */
                .wc-block-checkout__shipping-method-option:has(.wc-block-checkout__shipping-method-option-title:contains("Pickup")) {
                    display: flex !important;
                }
                
                /* Hide other shipping method types */
                input[name="shipping_method"][value*="flat_rate"],
                input[name="shipping_method"][value*="free_shipping"],
                label[for*="flat_rate"],
                label[for*="free_shipping"],
                .shipping-method:not(.local_pickup) {
                    display: none !important;
                }
            </style>
            
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                
                function hideShippingShowPickup() {
                    console.log('🔍 WC Advanced Packages: Running hideShippingShowPickup for Block Checkout');
                    
                    // Target the specific WooCommerce Block Checkout structure
                    var $container = $('.wc-block-checkout__shipping-method-container');
                    console.log('📦 Found shipping method container:', $container.length);
                    
                    if ($container.length === 0) {
                        console.log('⚠️ No WC Block checkout container found, trying classic checkout...');
                        // Fallback to classic checkout handling
                        hideClassicCheckoutShipping();
                        return;
                    }
                    
                    // Find Ship and Pickup options
                    var $shipOption = null;
                    var $pickupOption = null;
                    
                    $container.find('.wc-block-checkout__shipping-method-option').each(function() {
                        var $option = $(this);
                        var titleText = $option.find('.wc-block-checkout__shipping-method-option-title').text().trim();
                        
                        console.log('🔍 Found option with title:', titleText);
                        
                        if (titleText === 'Ship') {
                            $shipOption = $option;
                        } else if (titleText === 'Pickup') {
                            $pickupOption = $option;
                        }
                    });
                    
                    // Hide Ship option
                    if ($shipOption && $shipOption.length > 0) {
                        console.log('❌ Hiding Ship option');
                        $shipOption.hide();
                    } else {
                        console.log('⚠️ Ship option not found');
                    }
                    
                    // Select and show Pickup option
                    if ($pickupOption && $pickupOption.length > 0) {
                        console.log('✅ Selecting Pickup option');
                        
                        // Remove selected class from all options
                        $container.find('.wc-block-checkout__shipping-method-option').removeClass('wc-block-checkout__shipping-method-option--selected').attr('aria-checked', 'false');
                        
                        // Add selected class to pickup option
                        $pickupOption.addClass('wc-block-checkout__shipping-method-option--selected').attr('aria-checked', 'true');
                        
                        // Trigger click event to ensure WooCommerce processes the selection
                        $pickupOption.trigger('click');
                        
                        // Show pickup option (in case it was hidden)
                        $pickupOption.show();
                    } else {
                        console.log('⚠️ Pickup option not found');
                    }
                    
                    console.log('✅ Block checkout Ship/Pickup toggle modified');
                }
                
                function hideClassicCheckoutShipping() {
                    console.log('🔍 Running classic checkout shipping hide');
                    
                    // Handle traditional WooCommerce checkout
                    $('input[name*="shipping_method"]').each(function() {
                        var $input = $(this);
                        var value = $input.val() || '';
                        
                        console.log('📋 Found shipping input:', value);
                        
                        if (value.includes('local_pickup') || value.includes('pickup')) {
                            console.log('✅ Auto-selecting pickup method:', value);
                            $input.prop('checked', true).trigger('change');
                        } else {
                            console.log('❌ Hiding shipping method:', value);
                            $input.closest('li, tr, .shipping_method, label').hide();
                        }
                    });
                }
                
                // Run immediately
                hideShippingShowPickup();
                
                // Run after various checkout events with delays
                $(document.body).on('updated_checkout updated_cart_totals updated_shipping_method', function() {
                    console.log('🔄 Checkout updated, re-running hide function');
                    setTimeout(hideShippingShowPickup, 100);
                    setTimeout(hideShippingShowPickup, 500); // Double-check after animations
                });
                
                // Run after DOM changes (for dynamic checkout blocks)
                var observer = new MutationObserver(function(mutations) {
                    var shouldRerun = false;
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length > 0) {
                            $(mutation.addedNodes).each(function() {
                                if ($(this).is('*[class*="shipping"], *[class*="delivery"], button') ||
                                    $(this).find('*[class*="shipping"], *[class*="delivery"], button').length > 0) {
                                    shouldRerun = true;
                                }
                            });
                        }
                    });
                    
                    if (shouldRerun) {
                        console.log('🔄 DOM changed with shipping elements, re-running');
                        setTimeout(hideShippingShowPickup, 100);
                    }
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
                
                console.log('🏪 WC Advanced Packages: Enhanced shipping UI modifier loaded');
            });
            </script>
            <?php
        }
    }
    
    /**
     * Add admin menu under WooCommerce Reports
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Advanced Packages Settings', 'wc-advanced-packages' ),
            __( 'Advanced Packages', 'wc-advanced-packages' ),
            'manage_woocommerce',
            'wc-advanced-packages',
            array( $this, 'admin_page' )
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting( 'wc_advanced_packages_settings', 'wc_advanced_packages_settings' );
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'wc_advanced_packages_settings' ) ) {
            $settings = array(
                'enabled' => isset( $_POST['enabled'] ) ? 'yes' : 'no',
                'printify_meta_key' => sanitize_text_field( $_POST['printify_meta_key'] ),
                'pickup_shipping_class' => sanitize_text_field( $_POST['pickup_shipping_class'] ),
                'allowed_pickup_methods' => sanitize_text_field( $_POST['allowed_pickup_methods'] ),
                'printify_package_label' => sanitize_text_field( $_POST['printify_package_label'] ),
                'pickup_package_label' => sanitize_text_field( $_POST['pickup_package_label'] ),
                'other_package_label' => sanitize_text_field( $_POST['other_package_label'] ),
                'debug_mode' => isset( $_POST['debug_mode'] ) ? 'yes' : 'no',
            );
            
            update_option( 'wc_advanced_packages_settings', $settings );
            $this->settings = $settings;
            
            echo '<div class="notice notice-success"><p>' . __( 'Settings saved successfully!', 'wc-advanced-packages' ) . '</p></div>';
        }
        
        include WC_ADVANCED_PACKAGES_PLUGIN_DIR . 'includes/admin-page.php';
    }
    
    /**
     * Reset settings via AJAX
     */
    public function reset_settings_ajax() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'wc_advanced_packages_reset' ) ) {
            wp_die( 'Security check failed' );
        }
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        delete_option( 'wc_advanced_packages_settings' );
        $this->load_settings();
        
        wp_send_json_success( array( 'message' => __( 'Settings reset successfully!', 'wc-advanced-packages' ) ) );
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'WooCommerce Advanced Packages requires WooCommerce to be installed and active. You can download %s here.', 'wc-advanced-packages' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create default settings if they don't exist
        if ( ! get_option( 'wc_advanced_packages_settings' ) ) {
            $this->load_settings();
            update_option( 'wc_advanced_packages_settings', $this->settings );
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup if needed
    }
    
    /**
     * Get setting value
     */
    public function get_setting( $key, $default = '' ) {
        return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
    }
}

// Initialize the plugin
WC_Advanced_Packages::instance();
