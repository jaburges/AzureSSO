# Advanced Configuration

Power user settings, performance optimization, and custom configurations.

---

## 🔧 wp-config.php Settings

### Memory and Execution

```php
// Increase memory for large operations
define('WP_MEMORY_LIMIT', '512M');

// Set admin memory higher
define('WP_MAX_MEMORY_LIMIT', '512M');
```

### Debug Settings

```php
// Enable debugging (development only!)
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Log location: /wp-content/debug.log
```

### Cron Settings

```php
// Disable WordPress cron (use server cron instead)
define('DISABLE_WP_CRON', true);

// Then set up server cron:
// */5 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron
```

---

## 📊 Performance Optimization

### Caching

The plugin supports page caching plugins:
- WP Super Cache
- W3 Total Cache
- LiteSpeed Cache
- Redis Object Cache

**Cache Exclusions:**
Exclude these from caching:
- `/wp-admin/admin-ajax.php`
- Pages with `[azure_calendar]` shortcode (or set short cache)

### Database Optimization

```sql
-- Check plugin table sizes
SELECT 
    table_name,
    ROUND(data_length / 1024 / 1024, 2) AS size_mb
FROM information_schema.tables
WHERE table_name LIKE 'wp_azure_%' OR table_name LIKE 'wp_pta_%';
```

### Query Optimization

For large sites, optimize calendar queries:
```php
// Add to theme's functions.php
add_filter('azure_calendar_cache_duration', function() {
    return 3600; // 1 hour cache
});
```

---

## 🔐 Security Hardening

### Credential Storage

Credentials are stored in WordPress options table, encrypted if possible:

```php
// Force encrypted storage (requires sodium extension)
add_filter('azure_encrypt_credentials', '__return_true');
```

### API Request Limiting

```php
// Limit API requests per minute
add_filter('azure_api_rate_limit', function() {
    return 60; // requests per minute
});
```

### IP Restrictions

Restrict callback endpoints by IP:
```php
// Only allow Azure IPs for callbacks
add_filter('azure_allowed_callback_ips', function($ips) {
    return array_merge($ips, array(
        '20.190.128.0/18',  // Azure AD
        '40.126.0.0/18',    // Azure AD
    ));
});
```

---

## 🔄 Custom Hooks

### SSO Module

```php
// After successful SSO login
add_action('azure_sso_login_success', function($user, $azure_data) {
    // Custom logic after login
}, 10, 2);

// Modify user data before creation
add_filter('azure_sso_user_data', function($data, $azure_claims) {
    $data['role'] = 'custom_role';
    return $data;
}, 10, 2);
```

### Calendar Module

```php
// Before calendar renders
add_action('azure_calendar_before_render', function($calendar_id) {
    // Add custom styles or scripts
});

// Filter events before display
add_filter('azure_calendar_events', function($events, $calendar_id) {
    // Filter or modify events
    return $events;
}, 10, 2);
```

### Backup Module

```php
// Before backup starts
add_action('azure_backup_before', function($backup_types) {
    // Prepare for backup
});

// After backup completes
add_action('azure_backup_complete', function($backup_id, $result) {
    // Post-backup actions (e.g., notify admin)
}, 10, 2);

// Exclude files from backup
add_filter('azure_backup_exclude_paths', function($paths) {
    $paths[] = 'wp-content/cache/';
    $paths[] = 'wp-content/debug.log';
    return $paths;
});
```

---

## 📝 Custom Shortcode Defaults

Override default shortcode attributes:

```php
// Custom calendar defaults
add_filter('shortcode_atts_azure_calendar', function($atts) {
    $atts['height'] = '700px';
    $atts['view'] = 'week';
    return $atts;
});

// Custom upcoming events defaults
add_filter('shortcode_atts_up-next', function($atts) {
    $atts['columns'] = '2';
    $atts['show-time'] = 'false';
    return $atts;
});
```

---

## 🌐 Multi-Site Configuration

### Network Activation

The plugin supports WordPress Multisite:
1. Network activate the plugin
2. Configure common credentials at network level
3. Each site can override with own settings

### Shared Credentials

```php
// Force all sites to use network credentials
add_filter('azure_use_network_credentials', '__return_true');
```

---

## 🔌 REST API

### Endpoints

The plugin registers REST API endpoints:

```
GET  /wp-json/azure/v1/calendars
GET  /wp-json/azure/v1/events
POST /wp-json/azure/v1/sync
```

### Custom Endpoints

```php
// Add custom endpoint
add_action('rest_api_init', function() {
    register_rest_route('azure/v1', '/custom', array(
        'methods' => 'GET',
        'callback' => 'my_custom_callback',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
});
```

---

## 📊 Logging Configuration

### Log Levels

```php
// Set minimum log level
add_filter('azure_log_level', function() {
    return 'warning'; // debug, info, warning, error
});
```

### Custom Log Handler

```php
// Send logs to external service
add_action('azure_log_entry', function($level, $message, $context) {
    // Send to Sentry, Loggly, etc.
}, 10, 3);
```

### Log Cleanup

```php
// Adjust log retention
add_filter('azure_log_retention_days', function() {
    return 14; // Keep 14 days
});
```

---

## 🔧 Troubleshooting Commands

### WP-CLI Commands

If WP-CLI is installed:

```bash
# Clear plugin caches
wp cache flush

# Run backup manually
wp azure backup run

# Sync calendars
wp azure calendar-sync

# Test Azure connection
wp azure test-connection
```

### Debug Mode

Enable verbose debugging:

```php
// Add to wp-config.php
define('AZURE_PLUGIN_DEBUG', true);
```

This enables:
- Detailed API request/response logging
- Step-by-step operation logging
- Extended error messages

---

## ➡️ Related

- **[Troubleshooting](Troubleshooting)** - Common issues
- **[Development](Development)** - Contributing code
- **[Security](Security)** - Security best practices


