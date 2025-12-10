# Installation Guide

This guide covers detailed installation steps for Microsoft WP plugin.

---

## 📦 Installation Methods

### Method 1: WordPress Admin Upload (Recommended)

1. **Download** the latest release:
   - Go to [GitHub Releases](https://github.com/jamieburgess/microsoft-wp/releases)
   - Download the `microsoft-wp-vX.X.zip` file

2. **Upload** the plugin:
   - Log in to WordPress admin
   - Go to **Plugins → Add New**
   - Click **Upload Plugin**
   - Choose the ZIP file
   - Click **Install Now**

3. **Activate** the plugin:
   - Click **Activate Plugin**
   - Or go to **Plugins** and click **Activate** next to "Microsoft WP"

### Method 2: FTP/SFTP Upload

1. **Extract** the ZIP file on your computer

2. **Connect** to your server via FTP/SFTP

3. **Upload** the `azure-plugin` folder to:
   ```
   /wp-content/plugins/azure-plugin/
   ```

4. **Activate** in WordPress:
   - Go to **Plugins**
   - Find "Microsoft WP"
   - Click **Activate**

### Method 3: Git Clone (Development)

```bash
# Navigate to plugins directory
cd /path/to/wordpress/wp-content/plugins/

# Clone the repository
git clone https://github.com/jamieburgess/microsoft-wp.git azure-plugin

# Set permissions (Linux/Mac)
chmod -R 755 azure-plugin
```

---

## 📁 Plugin File Structure

After installation, you should have:

```
wp-content/plugins/azure-plugin/
├── admin/                    # Admin page templates
│   ├── main-page.php
│   ├── sso-page.php
│   ├── backup-page.php
│   └── ...
├── css/                      # Stylesheets
│   ├── admin.css
│   ├── calendar-embed.css
│   └── ...
├── includes/                 # Core PHP classes
│   ├── class-admin.php
│   ├── class-sso-auth.php
│   └── ...
├── js/                       # JavaScript files
│   ├── admin.js
│   ├── calendar.js
│   └── ...
├── templates/                # Email/output templates
├── wiki/                     # Documentation
├── azure-plugin.php          # Main plugin file
└── README.md                 # Readme
```

---

## ✅ Verification

After activation, verify the installation:

### 1. Check Admin Menu
- You should see **Azure Plugin** in the WordPress admin menu
- Clicking it should show the main dashboard

### 2. Check for Errors
- Look at the top of admin pages for any PHP notices
- Go to **Azure Plugin → System Logs** for error logs

### 3. Verify Files
Check that key files exist:
```
/wp-content/plugins/azure-plugin/azure-plugin.php
/wp-content/plugins/azure-plugin/includes/class-admin.php
/wp-content/plugins/azure-plugin/includes/class-settings.php
```

---

## ⚙️ Server Configuration

### PHP Requirements

Ensure your server meets these requirements:

```php
// Check in PHP Info or via code:
phpversion();              // Should be 7.4+
ini_get('memory_limit');   // Should be 128M+
ini_get('max_execution_time'); // Should be 60+
```

### Recommended php.ini Settings

```ini
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 64M
post_max_size = 64M
```

### WordPress Configuration

Add to `wp-config.php` if needed:

```php
// Increase memory limit
define('WP_MEMORY_LIMIT', '256M');

// Enable debug logging (development only)
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

---

## 🔧 Post-Installation Setup

### 1. Create Azure App Registration

Before configuring the plugin, you need an Azure App Registration:

**[→ Azure App Registration Guide](Azure-App-Registration)**

### 2. Enter Credentials

1. Go to **Azure Plugin** in admin
2. Scroll to **Common Credentials**
3. Enter:
   - Client ID
   - Client Secret
   - Tenant ID
4. Click **Save Settings**
5. Click **Test Connection**

### 3. Enable Modules

Each module can be enabled independently:

1. On the main dashboard, find the module you want
2. Click the **Enable** toggle
3. Click **Save Settings**
4. Configure module-specific settings

---

## 🔌 Optional Dependencies

Some modules require additional plugins:

### The Events Calendar

Required for: **Calendar Sync**, **Classes**, **Upcoming Events**

```
Install from: Plugins → Add New → Search "The Events Calendar"
Or download from: https://theeventscalendar.com/
```

### WooCommerce

Required for: **Classes** module

```
Install from: Plugins → Add New → Search "WooCommerce"
Or download from: https://woocommerce.com/
```

---

## 🔄 Updating the Plugin

### Manual Update

1. **Backup** your site first!
2. **Deactivate** the plugin
3. **Delete** the old plugin files (settings are preserved in database)
4. **Install** the new version
5. **Activate** the plugin
6. **Verify** settings are intact

### Settings Preservation

Plugin settings are stored in the WordPress database and are NOT deleted when you:
- Deactivate the plugin
- Delete plugin files
- Update to a new version

Settings ARE deleted if you:
- Uninstall via WordPress (with "Delete Files")
- Run the plugin's cleanup function

---

## 🗑️ Uninstallation

### Keep Settings (Temporary Removal)

1. Go to **Plugins**
2. Click **Deactivate** on Microsoft WP
3. Settings are preserved for later

### Complete Removal

1. **Deactivate** the plugin
2. Click **Delete**
3. Confirm deletion
4. Optionally, clean up database tables:

```sql
-- Remove plugin options (optional)
DELETE FROM wp_options WHERE option_name LIKE 'azure_%';

-- Remove PTA tables (if used)
DROP TABLE IF EXISTS wp_pta_departments;
DROP TABLE IF EXISTS wp_pta_roles;
DROP TABLE IF EXISTS wp_pta_user_roles;
DROP TABLE IF EXISTS wp_pta_audit_log;

-- Remove backup tables (if used)
DROP TABLE IF EXISTS wp_azure_backup_jobs;
```

---

## 🆘 Installation Troubleshooting

### "Plugin could not be activated"

**Cause:** PHP error in plugin code
**Solution:**
1. Enable WP_DEBUG in wp-config.php
2. Check error logs at `/wp-content/debug.log`
3. Verify PHP version is 7.4+

### "Missing required files"

**Cause:** Incomplete upload
**Solution:**
1. Re-download the plugin
2. Verify ZIP integrity
3. Try manual FTP upload

### "Permission denied"

**Cause:** File permissions too restrictive
**Solution:**
```bash
# Fix permissions (Linux/Mac)
chmod -R 755 /wp-content/plugins/azure-plugin/
chown -R www-data:www-data /wp-content/plugins/azure-plugin/
```

### "Maximum execution time exceeded"

**Cause:** Large site or slow server
**Solution:**
1. Increase `max_execution_time` in php.ini
2. Or add to .htaccess: `php_value max_execution_time 300`

---

## ➡️ Next Steps

Installation complete! Now:

1. **[Create Azure App Registration](Azure-App-Registration)** - Set up Azure credentials
2. **[Quick Start](Quick-Start)** - Configure your first module
3. **[Module Guides](Home)** - Explore all features

---

*Having trouble? Check [Troubleshooting](Troubleshooting) or [open an issue](https://github.com/jamieburgess/microsoft-wp/issues).*


