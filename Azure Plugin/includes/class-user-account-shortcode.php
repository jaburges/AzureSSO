<?php
/**
 * User Account Dropdown Shortcode
 * 
 * Provides a collapsible dropdown menu for logged-in users with WooCommerce integration
 * 
 * Usage: [user-account-dropdown]
 * 
 * Parameters:
 * - show_avatar: true/false (default: true) - Show user avatar
 * - avatar_size: number (default: 40) - Avatar size in pixels
 * - show_orders: true/false (default: true) - Show orders link (requires WooCommerce)
 * - show_downloads: true/false (default: false) - Show downloads link
 * - show_addresses: true/false (default: false) - Show addresses link
 * - show_payment_methods: true/false (default: false) - Show payment methods link
 * - custom_links: JSON array of custom links (optional)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_User_Account_Shortcode {
    
    public function __construct() {
        // Register shortcodes
        add_shortcode('user-account-dropdown', array($this, 'account_dropdown_shortcode'));
        add_shortcode('user-account-menu', array($this, 'account_dropdown_shortcode')); // Alias
        
        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue if shortcode is used
        global $post;
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'user-account-dropdown') ||
            has_shortcode($post->post_content, 'user-account-menu')
        )) {
            wp_enqueue_style(
                'azure-user-account-dropdown',
                AZURE_PLUGIN_URL . 'css/user-account-dropdown.css',
                array(),
                AZURE_PLUGIN_VERSION
            );
        }
    }
    
    /**
     * User account dropdown shortcode
     */
    public function account_dropdown_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_avatar' => 'true',
            'avatar_size' => 40,
            'show_orders' => 'true',
            'show_downloads' => 'false',
            'show_addresses' => 'false',
            'show_payment_methods' => 'false',
            'show_store_credit' => 'false',
            'collapsed' => 'true', // Start collapsed
            'style' => 'default', // default, minimal, card
        ), $atts);
        
        // Convert string booleans to actual booleans
        $show_avatar = filter_var($atts['show_avatar'], FILTER_VALIDATE_BOOLEAN);
        $show_orders = filter_var($atts['show_orders'], FILTER_VALIDATE_BOOLEAN);
        $show_downloads = filter_var($atts['show_downloads'], FILTER_VALIDATE_BOOLEAN);
        $show_addresses = filter_var($atts['show_addresses'], FILTER_VALIDATE_BOOLEAN);
        $show_payment_methods = filter_var($atts['show_payment_methods'], FILTER_VALIDATE_BOOLEAN);
        $show_store_credit = filter_var($atts['show_store_credit'], FILTER_VALIDATE_BOOLEAN);
        $collapsed = filter_var($atts['collapsed'], FILTER_VALIDATE_BOOLEAN);
        $avatar_size = intval($atts['avatar_size']);
        $style = sanitize_text_field($atts['style']);
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return $this->render_logged_out_state();
        }
        
        $current_user = wp_get_current_user();
        $user_display_name = $current_user->display_name;
        
        // Build menu items
        $menu_items = $this->get_menu_items($atts);
        
        // Get avatar
        $avatar_html = '';
        if ($show_avatar) {
            $avatar_html = get_avatar($current_user->ID, $avatar_size, '', $user_display_name, array(
                'class' => 'user-account-avatar'
            ));
        }
        
        // Generate unique ID for this instance
        $dropdown_id = 'user-account-dropdown-' . wp_rand(1000, 9999);
        
        // Build output
        ob_start();
        ?>
        <div class="user-account-dropdown-wrapper style-<?php echo esc_attr($style); ?>" id="<?php echo esc_attr($dropdown_id); ?>" style="position: relative; display: inline-block; z-index: 9999;">
            <div class="user-account-toggle" role="button" tabindex="0" aria-expanded="false" aria-controls="<?php echo esc_attr($dropdown_id); ?>-menu" onclick="azureToggleAccountMenu('<?php echo esc_js($dropdown_id); ?>')" style="cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                <?php if ($show_avatar): ?>
                    <?php echo $avatar_html; ?>
                <?php endif; ?>
                <span class="user-account-name"><?php echo esc_html($user_display_name); ?></span>
                <span class="user-account-arrow" style="transition: transform 0.2s ease;">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            </div>
            
            <div class="user-account-menu" id="<?php echo esc_attr($dropdown_id); ?>-menu" style="display: none; position: absolute; top: 100%; right: 0; min-width: 200px; background: #fff; border: 1px solid #e1e4e8; border-radius: 8px; margin-top: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 10000;">
                <ul class="user-account-menu-list" style="list-style: none; margin: 0; padding: 8px 0;">
                    <?php foreach ($menu_items as $item): ?>
                        <li class="user-account-menu-item" style="margin: 0; padding: 0;">
                            <a href="<?php echo esc_url($item['url']); ?>" class="user-account-menu-link" style="display: flex; align-items: center; gap: 12px; padding: 10px 16px; color: #24292e; text-decoration: none;">
                                <?php if (!empty($item['icon'])): ?>
                                    <span class="menu-icon" style="display: flex; width: 20px; height: 20px; color: #6a737d;"><?php echo $item['icon']; ?></span>
                                <?php endif; ?>
                                <span class="menu-text"><?php echo esc_html($item['label']); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <script>
        function azureToggleAccountMenu(id) {
            var wrapper = document.getElementById(id);
            if (!wrapper) return;
            
            var toggle = wrapper.querySelector('.user-account-toggle');
            var menu = document.getElementById(id + '-menu');
            
            if (menu.style.display === 'none' || menu.style.display === '') {
                menu.style.display = 'block';
                toggle.setAttribute('aria-expanded', 'true');
                toggle.classList.add('is-open');
            } else {
                menu.style.display = 'none';
                toggle.setAttribute('aria-expanded', 'false');
                toggle.classList.remove('is-open');
            }
        }
        
        // Close when clicking outside
        document.addEventListener('click', function(e) {
            var dropdowns = document.querySelectorAll('.user-account-dropdown-wrapper');
            dropdowns.forEach(function(wrapper) {
                if (!wrapper.contains(e.target)) {
                    var menu = wrapper.querySelector('.user-account-menu');
                    var toggle = wrapper.querySelector('.user-account-toggle');
                    if (menu) menu.style.display = 'none';
                    if (toggle) {
                        toggle.setAttribute('aria-expanded', 'false');
                        toggle.classList.remove('is-open');
                    }
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get menu items based on settings
     */
    private function get_menu_items($atts) {
        $items = array();
        $wc_active = class_exists('WooCommerce');
        $my_account_url = $wc_active ? wc_get_page_permalink('myaccount') : admin_url('profile.php');
        
        // Dashboard
        $items[] = array(
            'label' => __('Dashboard', 'azure-plugin'),
            'url' => $my_account_url,
            'icon' => $this->get_icon('dashboard')
        );
        
        // Orders (WooCommerce)
        if (filter_var($atts['show_orders'], FILTER_VALIDATE_BOOLEAN) && $wc_active) {
            $items[] = array(
                'label' => __('Orders', 'azure-plugin'),
                'url' => wc_get_endpoint_url('orders', '', $my_account_url),
                'icon' => $this->get_icon('orders')
            );
        }
        
        // Store Credit (if enabled)
        if (filter_var($atts['show_store_credit'], FILTER_VALIDATE_BOOLEAN) && $wc_active) {
            $items[] = array(
                'label' => __('Store Credit', 'azure-plugin'),
                'url' => wc_get_endpoint_url('store-credit', '', $my_account_url),
                'icon' => $this->get_icon('credit')
            );
        }
        
        // Downloads (WooCommerce)
        if (filter_var($atts['show_downloads'], FILTER_VALIDATE_BOOLEAN) && $wc_active) {
            $items[] = array(
                'label' => __('Downloads', 'azure-plugin'),
                'url' => wc_get_endpoint_url('downloads', '', $my_account_url),
                'icon' => $this->get_icon('downloads')
            );
        }
        
        // Addresses (WooCommerce)
        if (filter_var($atts['show_addresses'], FILTER_VALIDATE_BOOLEAN) && $wc_active) {
            $items[] = array(
                'label' => __('Addresses', 'azure-plugin'),
                'url' => wc_get_endpoint_url('edit-address', '', $my_account_url),
                'icon' => $this->get_icon('addresses')
            );
        }
        
        // Payment Methods (WooCommerce)
        if (filter_var($atts['show_payment_methods'], FILTER_VALIDATE_BOOLEAN) && $wc_active) {
            $items[] = array(
                'label' => __('Payment Methods', 'azure-plugin'),
                'url' => wc_get_endpoint_url('payment-methods', '', $my_account_url),
                'icon' => $this->get_icon('payment')
            );
        }
        
        // Account Details
        $items[] = array(
            'label' => __('Account Details', 'azure-plugin'),
            'url' => $wc_active ? wc_get_endpoint_url('edit-account', '', $my_account_url) : admin_url('profile.php'),
            'icon' => $this->get_icon('account')
        );
        
        // Logout
        $items[] = array(
            'label' => __('Log Out', 'azure-plugin'),
            'url' => wp_logout_url(home_url()),
            'icon' => $this->get_icon('logout')
        );
        
        return $items;
    }
    
    /**
     * Get SVG icon
     */
    private function get_icon($type) {
        $icons = array(
            'dashboard' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
            'orders' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
            'credit' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
            'downloads' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
            'addresses' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
            'payment' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
            'account' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
            'logout' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        );
        
        return isset($icons[$type]) ? $icons[$type] : '';
    }
    
    /**
     * Render logged out state
     */
    private function render_logged_out_state() {
        $login_url = wp_login_url(get_permalink());
        
        ob_start();
        ?>
        <div class="user-account-dropdown-wrapper logged-out">
            <a href="<?php echo esc_url($login_url); ?>" class="user-account-login-link">
                <span class="user-account-login-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                </span>
                <span><?php _e('Log In', 'azure-plugin'); ?></span>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
}

