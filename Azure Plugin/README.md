# Azure Plugin for WordPress

A comprehensive WordPress plugin that integrates multiple Azure services including Single Sign-On (SSO), Backup, Calendar, Email, and PTA Roles Management functionality into a single, unified solution.

## 🚀 Features

### 🔐 Single Sign-On (SSO)
- **Azure AD Integration**: Seamless authentication with Microsoft Azure Active Directory
- **User Management**: Automatic user creation and role assignment
- **Login Options**: Replace or supplement WordPress login with Azure AD
- **User Synchronization**: Scheduled sync of user data from Azure AD
- **Shortcodes**: Easy integration with `[azure_sso_login]` and `[azure_user_info]`

### 💾 Backup
- **Azure Blob Storage**: Secure cloud backups to Azure Storage
- **Flexible Backup Types**: Database, content, media, plugins, and themes
- **Scheduled Backups**: Automated backups with customizable frequency
- **Easy Restoration**: One-click restore from Azure Storage
- **Email Notifications**: Get notified when backups complete or fail

### 📅 Calendar
- **Microsoft Graph Integration**: Connect to Outlook/Microsoft 365 calendars
- **Multiple Display Options**: Month, week, day, and list views
- **Shortcodes**: Embed calendars with `[azure_calendar]` and `[azure_calendar_events]`
- **Responsive Design**: Works perfectly on all devices
- **Caching**: Optimized performance with intelligent caching

### 📧 Email
- **Multiple Send Methods**: Graph API, High Volume Email (HVE), and Azure Communication Services (ACS)
- **Contact Forms**: Built-in contact forms with `[azure_contact_form]`
- **WordPress Integration**: Replace wp_mail() function with Azure services
- **Email Queue**: Reliable email delivery with retry mechanism
- **Multiple Authentication**: Per-user or application-level authentication

### 🏛️ PTA Roles Management
- **Organizational Structure**: Complete department and role management system
- **WordPress as Primary Source**: WordPress controls all assignments, syncs to Azure AD
- **User Provisioning**: Automatic Azure AD user creation with Office 365 licenses
- **Hierarchy Management**: Department VPs become managers in Azure AD
- **Office 365 Groups**: Automatic group membership based on role assignments
- **Job Title Sync**: Role assignments become Azure AD job titles
- **Audit Trail**: Complete tracking of all organizational changes

## 📦 Installation

1. Upload the `Azure Plugin` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Azure Plugin** in the WordPress admin menu
4. Configure your Azure credentials and enable desired modules

## 🔧 Configuration

### Azure App Registration

Before using the plugin, you need to create an Azure App Registration:

1. Go to [Azure Portal](https://portal.azure.com/)
2. Navigate to **Azure Active Directory** > **App registrations**
3. Click **New registration**
4. Configure the application:
   - **Name**: Your WordPress site name
   - **Supported account types**: Choose based on your needs
   - **Redirect URI**: `https://yoursite.com/wp-admin/admin-ajax.php?action=azure_sso_callback`

5. After creation, note the **Application (client) ID** and **Directory (tenant) ID**
6. Go to **Certificates & secrets** and create a **New client secret**
7. Configure **API permissions** based on which modules you'll use:
   - **SSO**: `User.Read`, `Directory.Read.All` (optional)
   - **Calendar**: `Calendar.Read`, `Calendar.ReadWrite`
   - **Email**: `Mail.Send`, `Mail.ReadWrite`
   - **PTA**: `User.ReadWrite.All`, `Group.ReadWrite.All`, `Directory.ReadWrite.All`

### Plugin Configuration

1. **Common Credentials**: Set up shared credentials for all modules or use unique credentials per module
2. **Enable Modules**: Toggle individual modules on/off as needed
3. **Module-Specific Settings**: Configure each module's specific options

## 🎯 Usage

### SSO Shortcodes

```php
// Basic login button
[azure_sso_login]

// Custom login button
[azure_sso_login text="Sign in with Microsoft" redirect="/dashboard"]

// Logout button
[azure_sso_logout text="Sign out" redirect="/"]

// Display user information
[azure_user_info field="display_name"]
[azure_user_info] // Shows all user info
```

### Calendar Shortcodes

```php
// Full calendar display
[azure_calendar id="calendar_id" view="month" height="600px"]

// Events list
[azure_calendar_events id="calendar_id" limit="10" format="list"]

// Compact events list
[azure_calendar_events id="calendar_id" limit="5" format="compact" upcoming_only="true"]
```

### Email Shortcodes

```php
// Contact form
[azure_contact_form to="admin@site.com" subject="Contact Form"]

// Contact form with custom fields
[azure_contact_form to="sales@site.com" subject="Sales Inquiry" show_phone="true" required_fields="name,email,phone,message"]

// Email status (admin only)
[azure_email_status show_queue_count="true"]

// Email queue (admin only)
[azure_email_queue limit="10" status="pending"]
```

## 🏛️ PTA Roles Management

### Organizational Structure
The PTA module manages a complete organizational hierarchy:

- **7 Departments**: Exec Board, Communications, Enrichment, Events, Volunteers, Ways and Means
- **58+ Roles**: All with configurable occupancy limits
- **Hierarchy**: Department VPs manage their department members
- **Flexibility**: Primary roles determine manager hierarchy

### Key Features

#### WordPress as Source of Truth
- All role assignments managed in WordPress
- Changes automatically sync to Azure AD
- Complete audit trail of all changes
- WordPress user metadata updated with job titles

#### Azure AD Integration
- **User Provisioning**: Auto-create Azure AD accounts with temp passwords
- **Email Generation**: `firstname+lastInitial@wilderptsa.net`
- **License Assignment**: Automatic Office 365 Business Basic licenses
- **Manager Hierarchy**: Department VPs become Azure AD managers
- **Job Title Sync**: WordPress roles → Azure AD jobTitle field

#### Office 365 Groups Management
- **Automatic Sync**: Fetch all O365 groups from your tenant
- **Role Mappings**: Map PTA roles to specific O365 groups
- **Department Mappings**: Map entire departments to groups
- **Membership Sync**: Automatic group membership based on role assignments
- **Background Processing**: Reliable, queued sync operations

### Admin Interface

```
WordPress Admin → Azure Plugin → PTA Roles
├── 📊 Organization Dashboard    # Overview of departments, roles, assignments
├── 👥 Role Assignments         # Assign users to roles with primary role logic
├── 🏢 Department Management    # Configure departments and assign VPs
├── 🔄 Sync Monitoring         # Real-time sync queue status
├── 🌐 O365 Groups             # Manage Office 365 group mappings
└── 📋 Audit Logs             # Complete activity history
```

#### PTA Workflow
1. **Enable PTA Module**: Toggle in main Azure Plugin settings
2. **Sync O365 Groups**: Import groups from your Microsoft tenant
3. **Create Group Mappings**: Map departments/roles to O365 groups
4. **Assign Users to Roles**: Use admin interface for role assignments
5. **Monitor Sync**: Watch background jobs provision users and sync groups

## 🎨 Customization

### CSS Customization

The plugin includes responsive CSS that you can customize:

- **Calendar**: Modify `css/calendar-frontend.css`
- **Email Forms**: Modify `css/email-frontend.css`
- **Admin Styles**: Modify `css/admin.css`

### JavaScript Customization

Frontend JavaScript files can be customized:

- **Email Forms**: `js/email-frontend.js`
- **Admin Interface**: `js/admin.js`

### Hooks and Filters

The plugin provides several hooks for customization:

```php
// Modify SSO user creation
add_filter('azure_sso_create_user_data', function($user_data, $azure_user) {
    // Customize user data before creation
    return $user_data;
}, 10, 2);

// Modify backup file list
add_filter('azure_backup_files', function($files, $backup_type) {
    // Customize which files to include in backup
    return $files;
}, 10, 2);

// Modify calendar event display
add_filter('azure_calendar_event_data', function($event_data) {
    // Customize event data before display
    return $event_data;
});

// PTA role assignment hooks
add_action('pta_user_assignment_changed', function($user_id, $role_id, $action) {
    // Custom logic when user role assignments change
});

add_action('pta_department_vp_changed', function($department_id, $vp_user_id) {
    // Custom logic when department VP changes
});
```

## 🛡️ Security

- **Secure Authentication**: Uses OAuth 2.0 with Azure AD
- **Data Encryption**: All API communications use HTTPS
- **Permission Checks**: Proper WordPress capability checks
- **Input Validation**: All user inputs are sanitized and validated
- **Rate Limiting**: Contact forms include spam protection and rate limiting
- **Audit Logging**: Complete trail of all PTA organizational changes

## 📊 Monitoring

### Admin Dashboard

Monitor your Azure Plugin usage through the comprehensive admin dashboard:

- **Module Status**: Quick overview of all modules
- **Activity Logs**: Detailed logging of all plugin activities
- **Statistics**: Usage statistics for each module
- **Error Tracking**: Easy identification and resolution of issues
- **Sync Queues**: Real-time monitoring of background jobs

### PTA-Specific Monitoring

- **Role Fill Status**: Visual indicators for filled/open positions
- **Sync Queue**: Monitor user provisioning and group sync jobs
- **Unassigned Users**: Alerts for users without role assignments
- **Group Memberships**: Track O365 group membership changes

### Logging

The plugin provides detailed logging:

```php
Azure_Logger::info('Custom log message');
Azure_Logger::error('Error message with context', $context);
Azure_Logger::debug('Debug information');
```

### Activity Tracking

All plugin activities are tracked in the database:

- User logins and registrations
- Backup creation and restoration
- Calendar synchronizations
- Email sending attempts
- PTA role assignments and changes
- Office 365 group membership updates

## 🔧 Troubleshooting

### Common Issues

1. **SSO Not Working**
   - Check Azure App Registration redirect URI
   - Verify client ID, client secret, and tenant ID
   - Ensure proper API permissions are granted

2. **Backup Failures**
   - Check Azure Storage credentials
   - Verify container exists and is accessible
   - Check WordPress file permissions
   - Review backup logs for specific errors

3. **Calendar Not Loading**
   - Verify Microsoft Graph API permissions
   - Check authentication status
   - Clear calendar cache
   - Review browser console for JavaScript errors

4. **Email Not Sending**
   - Check authentication method configuration
   - Verify SMTP settings (for HVE)
   - Review email queue for failed messages
   - Check WordPress debug logs

5. **PTA Sync Issues**
   - Verify Azure AD permissions include User.ReadWrite.All
   - Check sync queue for failed jobs
   - Ensure Office 365 licenses are available
   - Review PTA sync engine logs

### Debug Mode

Enable WordPress debug mode and check the plugin logs:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 🔄 Updates

The plugin includes an update system:

1. **Automatic Updates**: Enable in plugin settings
2. **Manual Updates**: Download from plugin repository
3. **Backup Before Updates**: Always backup before updating

## 📋 Requirements

### Minimum Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **HTTPS**: Required for Azure authentication
- **cURL**: Required for API communications

### Recommended Requirements

- **WordPress**: 6.0 or higher
- **PHP**: 8.0 or higher
- **MySQL**: 8.0 or higher
- **Memory**: 256MB or higher
- **Max Execution Time**: 300 seconds (for backups and sync operations)

### PHP Extensions

- `curl` - For API communications
- `json` - For data processing
- `openssl` - For secure communications
- `zip` - For backup archive creation
- `mysqli` - For database operations

### Azure Permissions

For full PTA functionality, your Azure App Registration needs:

- **Application Permissions**:
  - `User.ReadWrite.All` - Create and manage users
  - `Group.ReadWrite.All` - Manage group memberships
  - `Directory.ReadWrite.All` - Update user properties like manager
- **Delegated Permissions** (for user-specific operations):
  - `User.Read` - Read user profile
  - `Mail.Send` - Send emails on behalf of users
  - `Calendar.ReadWrite` - Access user calendars

## 🤝 Support

### Documentation

- **Plugin Settings**: Detailed help text in admin panels
- **Shortcode Reference**: Built-in shortcode documentation
- **API Reference**: Complete API documentation for developers

### Getting Help

1. **Check Logs**: Review plugin logs for error details
2. **Documentation**: Consult this README and admin help text
3. **WordPress Forums**: Post questions with plugin tag
4. **GitHub Issues**: Report bugs and feature requests

## 📝 Changelog

### Version 1.0.0
- Initial release
- SSO integration with Azure AD
- Backup functionality with Azure Blob Storage
- Calendar integration with Microsoft Graph
- Email functionality with multiple providers
- PTA Roles Management with organizational structure
- Office 365 Groups synchronization
- Unified admin interface
- Comprehensive shortcode system
- Background sync processing
- Complete audit trail system

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 🙏 Credits

This plugin integrates and enhances functionality from multiple Azure services:

- Microsoft Azure Active Directory
- Microsoft Graph API
- Azure Blob Storage
- Azure Communication Services
- High Volume Email (HVE)
- Office 365 Groups

Built with ❤️ for the WordPress and PTA community.

---

**Ready to get started?** Install the plugin and follow the configuration guide above!

## 🏛️ PTA Quick Start Guide

### 1. Initial Setup
```
1. Enable PTA module in Azure Plugin settings
2. Configure Azure credentials (Client ID, Secret, Tenant)
3. Verify Azure App has required permissions
4. Visit PTA Roles page to see organizational structure
```

### 2. Office 365 Groups Setup
```
1. Go to PTA → O365 Groups
2. Click "Sync O365 Groups" to import from tenant
3. Create mappings between departments/roles and groups
4. Test group access to verify permissions
```

### 3. User Management
```
1. Assign users to PTA roles via admin interface
2. Set primary roles to determine manager hierarchy
3. Monitor sync queue for automatic Azure AD provisioning
4. Handle unassigned users as needed
```

### 4. Monitoring & Maintenance
```
1. Check sync queue status regularly
2. Review audit logs for organizational changes
3. Monitor group memberships in O365 admin center
4. Process failed sync jobs as needed
```

**Your PTA organization is now fully integrated with Azure AD and Office 365! 🎉**