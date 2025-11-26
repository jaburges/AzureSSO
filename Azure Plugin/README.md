# Microsoft WP - Complete Microsoft 365 Integration for WordPress

**Version:** 1.2  
**Author:** Jamie Burgess  
**License:** GPL v2 or later  
**Requires WordPress:** 5.0+  
**Requires PHP:** 7.4+

Complete Microsoft 365 integration plugin for WordPress featuring SSO authentication with Azure AD claims mapping, automated backup to Azure Blob Storage, Outlook calendar embedding with shared mailbox support, email via Microsoft Graph API, PTA role management with O365 Groups sync, and The Events Calendar integration.

---

## 📋 Table of Contents

1. [Features](#features)
2. [Installation](#installation)
3. [Azure App Registration Setup](#azure-app-registration-setup)
4. [Module Configuration](#module-configuration)
   - [SSO Authentication](#sso-authentication-module)
   - [Backup to Azure Storage](#backup-module)
   - [Calendar Embed](#calendar-embed-module)
   - [Email via Microsoft Graph](#email-module)
   - [PTA Role Management](#pta-role-management-module)
   - [The Events Calendar Integration](#the-events-calendar-integration)
   - [OneDrive Media Library](#onedrive-media-library-module)
5. [Shortcodes](#shortcodes)
6. [Troubleshooting](#troubleshooting)
7. [Support](#support)

---

## 🚀 Features

### **Single Sign-On (SSO) Authentication**
- Azure AD authentication for WordPress login
- Automatic user creation and role assignment
- Azure AD claims mapping (department, name, email)
- Custom role support for Azure AD users
- User synchronization from Azure AD
- Configurable redirect URLs

### **Backup to Azure Blob Storage**
- Automated backups to Azure Blob Storage
- Database, files, media, plugins, and themes backup
- Scheduled backups (hourly, daily, weekly, monthly)
- Backup restoration
- Retention policy management
- Progress tracking and notifications

### **Calendar Embedding**
- Embed Outlook calendars using shortcodes
- Shared mailbox support
- Multiple calendar views (month, week, day, list)
- Timezone support
- Event filtering and customization
- Caching for performance

### **Email via Microsoft Graph**
- Send emails through Microsoft Graph API
- Email logging and tracking
- Template support
- Attachment handling
- Email shortcodes

### **PTA Role Management**
- Manage PTA roles and departments
- User assignments with primary/secondary roles
- O365 Groups synchronization
- Audit logging
- Organizational chart
- CSV import/export

### **The Events Calendar Integration**
- Sync Outlook calendars with The Events Calendar
- Multi-calendar support with category mapping
- Bidirectional sync
- Recurring events support
- Conflict resolution
- Scheduled synchronization

### **OneDrive Media Library**
- Browse OneDrive files
- Upload media to OneDrive
- Insert OneDrive files into posts
- Large file support
- Media library integration

---

## 📦 Installation

1. **Upload the plugin files** to `/wp-content/plugins/azure-plugin/` directory
2. **Activate the plugin** through the 'Plugins' menu in WordPress
3. **Configure Azure credentials** in the main settings page
4. **Enable desired modules** from the admin interface

---

## ☁️ Azure App Registration Setup

### Step 1: Create App Registration

1. Go to [Azure Portal](https://portal.azure.com)
2. Navigate to **Azure Active Directory** → **App registrations**
3. Click **New registration**
4. Set **Name**: `WordPress Integration`
5. Set **Supported account types**: `Accounts in this organizational directory only`
6. Set **Redirect URI**: 
   - Platform: `Web`
   - URI: `https://yoursite.com/wp-admin/admin-ajax.php?action=azure_sso_callback`
7. Click **Register**

### Step 2: Configure API Permissions

Add the following **Microsoft Graph** permissions:

#### Delegated Permissions (for user authentication)
- `User.Read` - Read signed-in user profile
- `Calendars.Read` - Read user calendars
- `Calendars.ReadWrite` - Manage user calendars (if syncing TEC)
- `Mail.Send` - Send mail as a user

#### Application Permissions (for background operations)
- `User.Read.All` - Read all users' profiles
- `Calendars.Read` - Read calendars in all mailboxes
- `Calendars.ReadWrite` - Read and write calendars in all mailboxes
- `Mail.Send` - Send mail as any user

**Important:** Click **Grant admin consent** after adding permissions.

### Step 3: Create Client Secret

1. Go to **Certificates & secrets**
2. Click **New client secret**
3. Set description: `WordPress Plugin`
4. Set expiration: `24 months` (recommended)
5. Click **Add**
6. **Copy the secret value immediately** (it won't be shown again)

### Step 4: Note Required Values

You'll need these values for WordPress configuration:
- **Client ID** (Application ID)
- **Client Secret** (from step 3)
- **Tenant ID** (Directory ID)

---

## ⚙️ Module Configuration

### SSO Authentication Module

**Location:** Azure Plugin → SSO Settings

#### Basic Configuration

1. **Enable the module** using the toggle switch
2. **Configure credentials:**
   - Use common credentials (shared) OR
   - Set module-specific Client ID, Secret, and Tenant ID
3. **Set authentication options:**
   - Enable "Show on Login Page" to add Azure AD button to wp-login.php
   - Customize button text (default: "Sign in with WilderPTSA Email")
   - Enable "Require SSO" to force all logins through Azure AD (⚠️ use carefully!)

#### User Management

- **Auto Create Users:** Automatically create WordPress accounts for new Azure AD users
- **Default Role:** Choose standard WordPress role or create custom role
- **Custom Role:** Option to create "AzureAD" role for easy identification

#### User Synchronization

- **Enable Automatic Sync:** Sync users periodically from Azure AD
- **Sync Frequency:** Hourly, twice daily, daily, or weekly
- **Preserve Local Data:** Prevent overwriting local user modifications

#### Claims Mapping

The plugin automatically maps these Azure AD claims to WordPress:
- `displayName` → `display_name`
- `givenName` → `first_name`
- `surname` → `last_name`
- `mail` / `userPrincipalName` → `user_email`
- `department` → `department` (user meta)

#### Shortcodes

```
[azure_sso_login text="Sign in with Azure" redirect="/dashboard"]
[azure_sso_logout text="Sign out" redirect="/"]
[azure_user_info field="display_name"]
[azure_user_info] <!-- Shows all user info -->
```

---

### Backup Module

**Location:** Azure Plugin → Backup Settings

#### Azure Storage Configuration

1. **Storage Account Name:** Your Azure Storage account (without .blob.core.windows.net)
2. **Storage Access Key:** Primary or secondary access key
3. **Container Name:** Container for backups (default: `wordpress-backups`)

#### Backup Configuration

- **Backup Types:** Select components to backup
  - Database
  - Content Files
  - Media Files
  - Plugins
  - Themes
- **Enable Scheduled Backups:** Automatic backup schedule
- **Backup Frequency:** Hourly, daily, weekly, or monthly
- **Retention Days:** Days to keep backups (0 = forever)

#### Performance Settings

- **Cache Duration:** 300-86400 seconds
- **Max Events:** Backup size limits

#### Manual Backup

Click **Start Manual Backup** to create an immediate backup. Progress will be displayed with:
- Real-time progress bar
- Current operation status
- File counts and sizes
- Estimated completion time

#### Backup Restoration

1. View backup list in **Recent Backup Jobs**
2. Click **Restore** on completed backup
3. Confirm restoration (⚠️ overwrites current content)
4. Wait for restoration to complete

---

### Calendar Embed Module

**Location:** Azure Plugin → Calendar Embed

#### Shared Mailbox Authentication

1. **Your M365 Account:** The account you'll authenticate with (e.g., `admin@wilderptsa.net`)
2. **Shared Mailbox Email:** The shared mailbox to access (e.g., `calendar@wilderptsa.net`)
3. Click **Save Settings**
4. Click **Authenticate Calendar**
5. Sign in with your Microsoft 365 account
6. Grant delegated permissions

**Note:** The authenticated user must have delegate access to the shared mailbox.

#### Calendar Selection

After authentication:
1. View available calendars from the shared mailbox
2. Toggle **Enable for embedding** for each calendar
3. Configure timezone per calendar
4. Click **Save Calendar Settings**

#### Display Settings

- **Default Timezone:** Pacific, Eastern, Central, etc.
- **Default View:** Month, week, day, or list
- **Color Theme:** Blue, green, red, purple, orange, gray
- **Cache Duration:** Performance optimization
- **Max Events:** Event display limits

#### Shortcodes

**Full Calendar Display:**
```
[azure_calendar email="calendar@wilderptsa.net" id="CALENDAR_ID" view="month" height="600px"]
```

**Parameters:**
- `email` (required) - Shared mailbox email
- `id` (required) - Calendar ID
- `view` - month, week, day, list (default: month)
- `height` - CSS height (default: 600px)
- `width` - CSS width (default: 100%)
- `timezone` - Override default timezone
- `max_events` - Maximum events to show
- `show_weekends` - true/false (default: true)

**Events List:**
```
[azure_calendar_events email="calendar@wilderptsa.net" id="CALENDAR_ID" limit="10" format="list"]
```

**Parameters:**
- `email` (required) - Shared mailbox email
- `id` (required) - Calendar ID
- `limit` - Number of events (default: 10)
- `format` - list, grid, compact (default: list)
- `upcoming_only` - true/false (default: true)
- `show_dates` - true/false (default: true)
- `show_times` - true/false (default: true)
- `show_location` - true/false (default: true)

---

### Email Module

**Location:** Azure Plugin → Email Settings

#### Configuration

1. Enable the Email module
2. Configure credentials (common or module-specific)
3. Set sender email address
4. Configure email logging options

#### Email Templates

Create reusable email templates with:
- Subject line templates
- Body content with merge tags
- Attachments
- Reply-to addresses

#### Email Logging

All sent emails are logged with:
- Recipient information
- Subject and content
- Delivery status
- Timestamps
- Error messages (if failed)

#### Shortcodes

```
[azure_email_form to="recipient@example.com" subject="Contact Form"]
```

---

### PTA Role Management Module

**Location:** Azure Plugin → PTA Roles

#### Initial Setup

1. Enable the PTA module
2. Click **Reimport Default Tables** to:
   - Create database tables if missing
   - Import departments from CSV
   - Import roles from CSV

#### Department Management

- Create departments (e.g., Communications, Fundraising)
- Assign Vice Presidents (VPs) to departments
- Set department metadata

#### Role Management

- Add roles within departments
- Set maximum occupants per role
- Add role descriptions
- Define role requirements

#### User Assignments

- Assign users to roles
- Set primary role for each user
- Support multiple roles per user
- Track assignment history
- Audit all changes

#### O365 Groups Synchronization

1. **Authenticate:** Connect to Microsoft 365
2. **Fetch Groups:** Import O365 groups
3. **Create Mappings:**
   - Map roles to O365 groups
   - Map departments to O365 groups
   - Set required vs optional groups
4. **Sync Users:** Automatically update group memberships
5. **Schedule Sync:** Automatic synchronization

#### CSV Import/Export

**Import Format:**
```csv
department,role,max_occupants,description
Communications,Newsletter Editor,1,Manages monthly newsletter
Fundraising,Event Chair,2,Organizes fundraising events
```

**Export:** Download current roles and departments as CSV

---

### The Events Calendar Integration

**Location:** Azure Plugin → TEC Integration

**Requirements:** The Events Calendar plugin must be installed and activated.

#### Authentication

1. **User Email:** Microsoft 365 account for authentication (e.g., `admin@wilderptsa.net`)
2. **Shared Mailbox:** Outlook mailbox to sync from (e.g., `calendar@wilderptsa.net`)
3. Click **Authenticate**
4. Grant calendar permissions

#### Multi-Calendar Setup

1. **Fetch Available Calendars** from shared mailbox
2. **Create Mappings:**
   - Select Outlook calendar
   - Choose or create TEC category
   - Set sync direction (One-way or Two-way)
   - Configure as primary/additional calendar
3. **Save Mapping**

#### Sync Configuration

- **Lookback Days:** How far back to sync (default: 30 days)
- **Lookahead Days:** How far forward to sync (default: 365 days)
- **Sync Frequency:** Manual, hourly, daily, or weekly
- **Conflict Resolution:** Outlook wins or TEC wins
- **Recurring Events:** Enable/disable recurring event sync

#### Manual Sync

Click **Sync Now** to immediately:
- Fetch events from Outlook
- Create/update TEC events
- Apply category mappings
- Handle recurring events
- Resolve conflicts

#### Monitoring

View sync results including:
- Events created
- Events updated
- Events skipped
- Error details
- Last sync timestamp

---

### OneDrive Media Library Module

**Location:** Azure Plugin → OneDrive Media

#### Setup

1. Enable the module
2. Authenticate with Microsoft 365
3. Grant OneDrive permissions

#### Features

- **Browse OneDrive:** Navigate folder structure
- **Upload Files:** Upload to OneDrive from WordPress
- **Insert Media:** Add OneDrive files to posts/pages
- **Large File Support:** Handles files >4MB with resumable uploads
- **Media Library Integration:** OneDrive files appear in media picker

#### Usage

1. Click **Add Media** in post editor
2. Select **OneDrive** tab
3. Browse and select files
4. Insert into content

---

## 📝 Shortcodes

### SSO Shortcodes

```php
// Login button
[azure_sso_login text="Sign in" redirect="/dashboard"]

// Logout button
[azure_sso_logout text="Sign out"]

// Display user info
[azure_user_info field="display_name"]
[azure_user_info field="email"]
[azure_user_info] // Show all fields
```

### Calendar Shortcodes

```php
// Full calendar
[azure_calendar email="calendar@wilderptsa.net" id="CALENDAR_ID" view="month"]

// Events list
[azure_calendar_events email="calendar@wilderptsa.net" id="CALENDAR_ID" limit="10"]
```

### PTA Shortcodes

```php
// Roles directory
[pta-roles-directory department="communications" columns="3"]

// Department roles
[pta-department-roles department="fundraising" show_vp="true"]

// Organization chart
[pta-org-chart department="all" interactive="true"]

// Open positions
[pta-open-positions limit="10" show_department="true"]

// Department VP
[pta-department-vp department="communications"]
```

---

## 🔧 Troubleshooting

### Common Issues

#### "No calendars showing after authentication"

**Solution:** 
- Verify you have delegate access to the shared mailbox
- Check that the shared mailbox email is correct
- Click "Refresh Calendars"
- Check error logs for API issues

#### "Backup stuck at 34%"

**Solution:** (Fixed in v1.2)
- The backup may still be processing large files
- Wait for the heartbeat updates (every 10 seconds)
- Check logs for specific file issues
- Large files >100MB are automatically skipped

#### "Table doesn't exist" errors for PTA

**Solution:**
- Click **Reimport Default Tables** button
- This will create all missing tables automatically
- Then import default roles and departments

#### "Invalid dates for Outlook event"

**Solution:** (Fixed in v1.2)
- Date format mismatch has been resolved
- Re-sync calendars to fix existing events

#### "Department field not syncing"

**Solution:** (Fixed in v1.2)
- Department is now automatically mapped from Azure AD
- Run a user sync to update existing users
- Verify `User.Read.All` permission is granted

### Debug Mode

Enable debug logging:
1. Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```
2. Check logs in: Azure Plugin → Logs

### Support Resources

- **Error Logs:** Azure Plugin → Logs page
- **Activity Log:** Recent actions and sync results
- **Test Connections:** Use connection test buttons in each module

---

## 🔐 Security Best Practices

1. **Client Secrets:** Store securely and rotate periodically (recommended: every 6-12 months)
2. **Permissions:** Grant only required API permissions
3. **Admin Access:** Restrict plugin settings to administrators
4. **HTTPS:** Always use HTTPS for production sites
5. **Backups:** Regularly backup WordPress database and Azure credentials
6. **Audit Logs:** Review PTA audit logs for suspicious activity

---

## 📊 Performance Optimization

### Caching

- **Calendar Cache:** 1-24 hours (default: 1 hour)
- **User Sync:** Daily recommended for most sites
- **TEC Sync:** Based on event frequency

### Large Sites

- Increase PHP memory limit: `512M` or higher
- Increase execution timeout: `300` seconds or more
- Use selective backup (exclude large media if backed up elsewhere)
- Limit calendar lookback/lookahead days

---

## 🔄 Updates & Maintenance

### Version 1.2 Changes

- ✅ Fixed calendar sync date validation for TEC
- ✅ Added Azure AD department claim mapping
- ✅ Fixed backup timeout and progress tracking
- ✅ Fixed calendar embed shared mailbox access
- ✅ Fixed PTA table creation in "Reimport Default Tables"
- ✅ Improved error handling and logging

### Updating the Plugin

1. **Backup** your WordPress site and database
2. **Deactivate** the plugin
3. **Replace** plugin files with new version
4. **Activate** the plugin
5. **Test** each enabled module

---

## 📞 Support

For issues, feature requests, or contributions:
- **GitHub:** https://github.com/jamieburgess/microsoft-wp
- **WordPress Admin:** Check Azure Plugin → Logs for error details

---

## 📜 License

This plugin is licensed under the GPL v2 or later.

---

## 🙏 Credits

- **Microsoft Graph API** for Azure integration
- **The Events Calendar** for event management
- **WordPress** community for plugin framework

---

**Version 1.2** | Last Updated: November 2024

