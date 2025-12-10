# Development Guide

Setting up a development environment and understanding the plugin architecture.

---

## 🛠️ Development Environment

### Local WordPress Setup

**Recommended Tools:**
- [LocalWP](https://localwp.com/) (easiest)
- [XAMPP](https://www.apachefriends.org/)
- [Docker + WordPress](https://hub.docker.com/_/wordpress)

### Minimum Requirements

- PHP 7.4+
- MySQL 5.6+
- WordPress 5.0+
- SSL certificate (for OAuth testing)

### Azure Development Tenant

For testing Azure features:
1. Create a [free Microsoft 365 developer tenant](https://developer.microsoft.com/en-us/microsoft-365/dev-program)
2. Set up App Registration as documented
3. Use developer tenant for testing

---

## 📁 Architecture Overview

### Plugin Structure

```
microsoft-wp/
├── azure-plugin.php      # Main entry point
├── includes/             # Core PHP classes
│   ├── class-admin.php           # Admin UI
│   ├── class-settings.php        # Settings management
│   ├── class-logger.php          # Logging
│   ├── class-database.php        # Database operations
│   │
│   ├── class-sso-auth.php        # SSO module
│   ├── class-sso-sync.php        # User sync
│   │
│   ├── class-backup.php          # Backup module
│   ├── class-backup-restore.php
│   ├── class-backup-azure-storage.php
│   │
│   ├── class-calendar-*.php      # Calendar modules
│   ├── class-email-*.php         # Email module
│   ├── class-pta-*.php           # PTA module
│   ├── class-classes-*.php       # Classes module
│   └── class-upcoming-*.php      # Upcoming module
│
├── admin/                # Admin page templates
│   ├── main-page.php
│   ├── sso-page.php
│   └── ...
│
├── css/                  # Stylesheets
├── js/                   # JavaScript
├── templates/            # Output templates
└── wiki/                 # Documentation
```

### Class Hierarchy

```
AzurePlugin (main class)
├── Azure_Admin           # Admin UI handling
├── Azure_Settings        # Settings storage/retrieval
├── Azure_Logger          # Logging functionality
├── Azure_Database        # Database table management
│
├── Azure_SSO_Auth        # SSO authentication
├── Azure_SSO_Sync        # User synchronization
│
├── Azure_Backup          # Backup operations
├── Azure_Calendar_*      # Calendar functionality
├── Azure_Email_*         # Email functionality
├── Azure_PTA_*           # PTA functionality
├── Azure_Classes_*       # Classes functionality
└── Azure_Upcoming_*      # Upcoming events
```

---

## 🔌 Adding a New Module

### Step 1: Create Module Class

```php
// includes/class-mymodule.php

<?php
if (!defined('ABSPATH')) exit;

class Azure_MyModule {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Register hooks
        add_action('init', array($this, 'init'));
        add_shortcode('my_shortcode', array($this, 'render_shortcode'));
    }
    
    public function init() {
        // Initialization logic
    }
    
    public function render_shortcode($atts) {
        return '<div class="my-module">Content</div>';
    }
}
```

### Step 2: Register in Main Plugin

```php
// azure-plugin.php

// Add to optional_files array
'class-mymodule.php' => 'MyModule class',

// Add initialization
private function init_mymodule_components() {
    if (class_exists('Azure_MyModule')) {
        Azure_MyModule::get_instance();
    }
}
```

### Step 3: Create Admin Page

```php
// admin/mymodule-page.php

<div class="wrap azure-admin-wrap">
    <h1>My Module</h1>
    <!-- Module UI -->
</div>
```

### Step 4: Register Admin Menu

```php
// In class-admin.php

add_submenu_page(
    'azure-plugin',
    'Azure Plugin - My Module',
    'My Module',
    'manage_options',
    'azure-plugin-mymodule',
    array($this, 'admin_page_mymodule')
);

public function admin_page_mymodule() {
    include AZURE_PLUGIN_PATH . 'admin/mymodule-page.php';
}
```

---

## 🧪 Testing

### Debug Mode

Enable debugging in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('AZURE_PLUGIN_DEBUG', true);
```

### Logging

Use the built-in logger:

```php
Azure_Logger::debug('Debug message', array('context' => 'value'));
Azure_Logger::info('Info message');
Azure_Logger::warning('Warning message');
Azure_Logger::error('Error message');
```

### AJAX Debugging

```javascript
// In browser console
console.log('AJAX URL:', ajaxurl);

jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'azure_my_action',
        nonce: azure_data.nonce
    },
    success: function(response) {
        console.log('Response:', response);
    },
    error: function(xhr, status, error) {
        console.error('Error:', error);
    }
});
```

---

## 📊 Database Tables

### Creating Tables

Use the database class:

```php
// In class-database.php

public static function create_my_table() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'azure_mytable';
    $charset = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        data longtext,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
```

### Migrations

Handle schema changes:

```php
public static function maybe_upgrade() {
    $version = get_option('azure_db_version', '1.0');
    
    if (version_compare($version, '2.0', '<')) {
        self::upgrade_to_2_0();
    }
    
    update_option('azure_db_version', '2.0');
}
```

---

## 🔐 Security Practices

### Nonce Verification

```php
// Create nonce
wp_nonce_field('azure_my_action', 'azure_my_nonce');

// Verify nonce
if (!wp_verify_nonce($_POST['azure_my_nonce'], 'azure_my_action')) {
    wp_die('Security check failed');
}
```

### Capability Checks

```php
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}
```

### Data Sanitization

```php
$text = sanitize_text_field($_POST['text']);
$email = sanitize_email($_POST['email']);
$url = esc_url_raw($_POST['url']);
$html = wp_kses_post($_POST['html']);
```

### Output Escaping

```php
echo esc_html($text);
echo esc_attr($attribute);
echo esc_url($url);
echo wp_kses_post($html);
```

---

## 🚀 Release Process

### Version Bump

1. Update version in `azure-plugin.php`:
   - Plugin header version
   - `AZURE_PLUGIN_VERSION` constant

2. Update `README.md` version badge

3. Update `CHANGELOG.md`

### Create Release

```bash
# Commit changes
git add .
git commit -m "Release v2.0.0"

# Tag release
git tag -a v2.0.0 -m "Version 2.0.0"

# Push
git push origin main
git push origin v2.0.0
```

### GitHub Release

1. Go to GitHub Releases
2. Create new release from tag
3. Add release notes
4. Attach ZIP file

---

## 📚 Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Microsoft Graph API](https://docs.microsoft.com/en-us/graph/)
- [Azure AD Documentation](https://docs.microsoft.com/en-us/azure/active-directory/)

---

## ➡️ Related

- **[Contributing](Contributing)** - How to contribute
- **[Advanced Configuration](Advanced-Configuration)** - Customization options


