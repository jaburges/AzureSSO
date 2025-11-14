# Microsoft WP - Complete WordPress Azure Integration Plugin

A comprehensive WordPress plugin that seamlessly integrates Microsoft Azure and Microsoft 365 services into WordPress. Manage everything from single sign-on authentication to calendar sync, email services, backups, PTA organizational management, and media storage—all from one unified plugin.

---

## 📋 **Table of Contents**

1. [Introduction](#introduction)
2. [Features Overview](#features-overview)
3. [Requirements](#requirements)
4. [Initial Setup & Basic Configuration](#initial-setup--basic-configuration)
5. [Common Configuration](#common-configuration)
6. [Module Configurations](#module-configurations)
   - [SSO (Single Sign-On)](#sso-single-sign-on)
   - [Backup](#backup)
   - [Calendar Embed](#calendar-embed)
   - [Calendar Sync (TEC Integration)](#calendar-sync-tec-integration)
   - [Email](#email)
   - [PTA Roles Management](#pta-roles-management)
   - [OneDrive/SharePoint Media](#onenedrivesharepoint-media)
7. [Shortcodes Reference](#shortcodes-reference)
8. [Troubleshooting](#troubleshooting)
9. [Support & Documentation](#support--documentation)

---

## 🎯 **Introduction**

**Microsoft WP** is an all-in-one plugin that brings the power of Microsoft Azure and Microsoft 365 to your WordPress site. Whether you need enterprise-grade authentication, automated backups, calendar integration, email services, or organizational management for PTAs and nonprofits, this plugin has you covered.

### **Why Microsoft WP?**

- **Unified Management**: One plugin for all your Microsoft integrations
- **Enterprise-Grade Security**: OAuth 2.0 authentication with Azure AD
- **Flexible Configuration**: Use common credentials or separate credentials per module
- **Modular Design**: Enable only the modules you need
- **Professional Grade**: Built for reliability, scalability, and ease of use

---

## ✨ **Features Overview**

### **🔐 SSO (Single Sign-On)**
- Azure AD authentication for WordPress
- Replace or supplement traditional WordPress login
- Automatic user provisioning
- Custom button text and branding
- Forced SSO mode for enhanced security

### **💾 Backup**
- Automated backups to Azure Blob Storage
- Database, files, media, plugins, and themes backup
- Scheduled backups with customizable frequency
- One-click restore functionality
- Email notifications

### **📅 Calendar Embed**
- Embed Microsoft 365/Outlook calendars
- Multiple view options (month, week, day, list)
- Customizable appearance
- Event filtering and display options
- Responsive design

### **🔄 Calendar Sync (TEC Integration)**
- Sync Microsoft 365 calendars to The Events Calendar (TEC)
- Bi-directional sync support
- Category mapping
- Automated scheduled sync
- Per-mapping sync schedules

### **📧 Email**
- Send emails via Microsoft Graph API
- Multiple authentication methods
- Contact forms with spam protection
- Email queue management
- Replace WordPress wp_mail() function

### **🏛️ PTA Roles Management**
- Complete organizational structure management
- Department and role hierarchy
- Azure AD user provisioning
- Office 365 group sync
- Audit trail and reporting

### **📁 OneDrive/SharePoint Media**
- Browse and attach OneDrive files
- SharePoint document library integration
- Site and drive browsing
- Folder organization
- Year-based folder creation

---

## 📋 **Requirements**

### **Minimum Requirements**
- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **HTTPS**: Required for Azure authentication
- **cURL Extension**: Required for API communications

### **Recommended Requirements**
- **WordPress**: 6.0 or higher
- **PHP**: 8.0 or higher
- **MySQL**: 8.0 or higher
- **Memory**: 256MB or higher
- **Max Execution Time**: 300 seconds (for backups and sync operations)

### **Required PHP Extensions**
- `curl` - For API communications
- `json` - For data processing
- `openssl` - For secure communications
- `zip` - For backup archive creation
- `mysqli` - For database operations

### **Azure Requirements**
- Active Azure subscription (free tier available)
- Azure AD tenant
- App registration in Azure AD
- Appropriate API permissions (varies by module)

---

## 🚀 **Initial Setup & Basic Configuration**

### **Step 1: Install the Plugin**

1. Download the plugin ZIP file
2. In WordPress, go to **Plugins** → **Add New** → **Upload Plugin**
3. Select the `Azure Plugin.zip` file
4. Click **Install Now**
5. After installation, click **Activate Plugin**

### **Step 2: Create Azure App Registration**

Before configuring the plugin, you need to create an App Registration in Azure AD:

1. Go to the [Azure Portal](https://portal.azure.com/)
2. Navigate to **Azure Active Directory** → **App registrations**
3. Click **New registration**
4. Configure your application:
   - **Name**: `WordPress Microsoft Integration` (or your site name)
   - **Supported account types**: 
     - Single tenant (recommended for most organizations)
     - Multi-tenant if needed for guests
   - **Redirect URI**: 
     - Platform: **Web**
     - URL: `https://yoursite.com/wp-admin/admin-ajax.php?action=azure_sso_callback`
     - (Replace `yoursite.com` with your actual domain)

5. Click **Register**

### **Step 3: Get Your Credentials**

After registration, note these important values:

1. **Application (client) ID**: Found on the app overview page
2. **Directory (tenant) ID**: Found on the app overview page
3. **Client Secret**: 
   - Go to **Certificates & secrets**
   - Click **New client secret**
   - Add a description (e.g., "WordPress Plugin")
   - Choose expiration (recommend 24 months)
   - Click **Add**
   - **Copy the Value immediately** (you can't see it again!)

### **Step 4: Configure API Permissions**

In your App Registration, go to **API permissions**:

1. Click **Add a permission**
2. Select **Microsoft Graph**
3. Choose **Delegated permissions** or **Application permissions** based on your needs

**Recommended Starting Permissions:**
- **Delegated permissions**:
  - `User.Read` - Read user profile
  - `Calendars.Read` - Read calendars
  - `Calendars.ReadWrite` - Write to calendars
  - `Mail.Send` - Send emails
  - `Files.Read.All` - Read OneDrive files
  - `Sites.Read.All` - Read SharePoint sites

- **Application permissions** (for PTA module):
  - `User.ReadWrite.All` - Manage users
  - `Group.ReadWrite.All` - Manage groups
  - `Directory.ReadWrite.All` - Update user properties

4. Click **Add permissions**
5. Click **Grant admin consent for [Your Organization]** (requires admin rights)

---

## ⚙️ **Common Configuration**

After installing and activating the plugin, navigate to **Azure Plugin** in your WordPress admin menu.

### **Common Credentials Setup**

The plugin supports two credential modes:

#### **Option 1: Use Common Credentials (Recommended)**

Use one set of Azure credentials for all modules:

1. Navigate to **Azure Plugin** → **Main Settings**
2. Check **"Use common credentials for all modules"**
3. Enter your credentials:
   - **Client ID**: Your Application (client) ID
   - **Client Secret**: Your client secret value
   - **Tenant ID**: Your Directory (tenant) ID (or use `common` for multi-tenant)

4. Click **Save Settings**

**Benefits:**
- Simpler management
- Single app registration needed
- Easier permission management

#### **Option 2: Module-Specific Credentials**

Use different Azure app registrations for each module:

1. Leave **"Use common credentials"** unchecked
2. Configure credentials individually in each module settings page
3. Each module will show its own credential input fields

**Benefits:**
- Granular permission control
- Separate apps for different purposes
- Better security isolation

### **Module Management**

Enable or disable modules based on your needs:

1. Go to **Azure Plugin** → **Main Settings**
2. Toggle modules on/off:
   - **SSO** - Single Sign-On authentication
   - **Backup** - Azure Blob Storage backups
   - **Calendar** - Calendar embedding and sync
   - **Email** - Microsoft Graph email services
   - **PTA Roles** - Organizational management
   - **OneDrive Media** - OneDrive/SharePoint integration

3. Click **Configure** next to any enabled module to access its settings

---

## 📦 **Module Configurations**

## 🔐 **SSO (Single Sign-On)**

**Admin Page**: Azure Plugin → SSO

### **Overview**
Enable secure authentication using Microsoft Azure AD. Users can sign in with their Microsoft 365 accounts instead of (or in addition to) WordPress passwords.

### **Configuration Steps**

#### **1. Basic Settings**

- **Require SSO**: Force all users to authenticate via Azure AD (disables WordPress login)
  - ⚠️ Warning: Test SSO thoroughly before enabling this!
- **Auto Create Users**: Automatically create WordPress accounts for new Azure AD users
- **Default Role**: WordPress role assigned to new SSO users (Subscriber, Author, Editor, etc.)

#### **2. Custom Role Configuration**

- **Use Custom Role**: Create a custom WordPress role specifically for Azure AD users
- **Custom Role Name**: Name for the custom role (default: "AzureAD")
  - The role will have basic subscriber capabilities plus `azure_ad_user` capability

#### **3. Login Button Configuration**

- **Show on Login Page**: Display the SSO button on the WordPress login page
- **Button Text**: Customize the text shown on the login button
  - Default: "Sign in with WilderPTSA Email"
  - The Microsoft icon will always be displayed
  - Example: "Sign in with Company Email"

#### **4. User Synchronization**

- **Enable User Sync**: Automatically sync user data from Azure AD
- **Sync Frequency**: How often to sync users (hourly, daily, weekly)
- **Preserve Local Data**: Keep local WordPress user data during sync

### **Testing SSO**

1. Click **Test SSO Connection** to verify your Azure configuration
2. Open an incognito/private browser window
3. Go to your WordPress login page
4. Click the SSO button
5. Sign in with a Microsoft account
6. Verify you're logged into WordPress

### **SSO Shortcodes**

#### **Login Button**
```
[azure_sso_login]
[azure_sso_login text="Sign in with WilderPTSA Email" redirect="/dashboard"]
```

**Parameters:**
- `text` - Button text (default: "Sign in with WilderPTSA Email")
- `redirect` - URL to redirect to after login
- `class` - CSS class for the button
- `style` - Inline CSS styles

#### **Logout Button**
```
[azure_sso_logout]
[azure_sso_logout text="Sign out" redirect="/"]
```

**Parameters:**
- `text` - Button text (default: "Sign out")
- `redirect` - URL to redirect to after logout
- `class` - CSS class for the button
- `style` - Inline CSS styles

#### **User Information**
```
[azure_user_info]
[azure_user_info field="display_name"]
[azure_user_info field="email"]
```

**Parameters:**
- `field` - Specific field to display:
  - `display_name` - User's display name
  - `email` - User's email address
  - `azure_id` - Azure user ID
  - `last_login` - Last login timestamp
  - `wp_username` - WordPress username
  - `wp_display_name` - WordPress display name
- `logged_out_text` - Message shown when user is not logged in
- `format` - Output format (`html` or `json`)

---

## 💾 **Backup**

**Admin Page**: Azure Plugin → Backup

### **Overview**
Automated backups of your WordPress site to Azure Blob Storage. Securely store database, files, media, plugins, and themes in the cloud.

### **Prerequisites**
- Azure Storage Account
- Azure Blob Storage container
- Storage account access key

### **Azure Storage Setup**

1. In Azure Portal, create a **Storage Account**
2. Create a **Container** (e.g., "wordpress-backups")
3. Note your **Storage Account Name** and **Access Key**

### **Configuration Steps**

#### **1. Storage Credentials**

If not using common credentials:
- **Storage Account Name**: Your Azure storage account name
- **Storage Account Key**: Access key from Azure Portal
- **Container Name**: Name of your blob container

#### **2. Backup Settings**

- **Backup Types**: Select what to backup:
  - ☑ Database
  - ☑ WordPress Content (`wp-content` folder)
  - ☑ Media Files (`wp-content/uploads`)
  - ☑ Plugins
  - ☑ Themes

- **Scheduled Backups**:
  - Enable automated backups
  - Frequency: hourly, daily, weekly, monthly

- **Backup Retention**:
  - Number of backups to keep
  - Older backups are automatically deleted

- **Email Notifications**:
  - Send email when backup completes
  - Send email on backup failures
  - Notification email address

#### **3. Manual Backup**

Click **Create Backup Now** to run an immediate backup.

#### **4. Restore from Backup**

1. Go to **Backup** → **Restore**
2. Select a backup from the list
3. Choose what to restore (database, files, etc.)
4. Click **Restore**
5. ⚠️ **Warning**: This will overwrite existing data!

### **No Shortcodes Available**

---

## 📅 **Calendar Embed**

**Admin Page**: Azure Plugin → Calendar

### **Overview**
Embed Microsoft 365 or Outlook calendars directly into your WordPress pages and posts. Display events in multiple views with customizable styling.

### **Configuration Steps**

#### **1. Authentication**

- **Your M365 Account**: Your Microsoft 365 email address
- **Shared Mailbox Email** (optional): Email of a shared mailbox to access
- Click **Authenticate Calendar** to sign in with Microsoft
- Grant permissions when prompted

#### **2. Calendar Selection**

After authentication:
1. Click **Refresh Calendars** to load your available calendars
2. Select which calendars to enable for embedding
3. Set timezone for each calendar
4. Click **Save Calendar Settings**

### **Calendar Embed Shortcodes**

#### **Full Calendar Display**
```
[azure_calendar id="calendar_id"]
[azure_calendar id="AQMkADAwATMwMAItZjFiZS00" view="month" height="600px"]
```

**Parameters:**
- `id` - **Required**. Calendar ID from Calendar admin page
- `view` - Display view: `month`, `week`, `day`, `list` (default: `month`)
- `height` - Calendar height (default: `600px`)
- `width` - Calendar width (default: `100%`)
- `theme` - Color theme: `default`, `dark`
- `timezone` - Timezone for display
- `max_events` - Maximum events to display (default: 100)
- `start_date` - Start date filter (ISO format)
- `end_date` - End date filter (ISO format)
- `show_toolbar` - Show calendar navigation toolbar (default: `true`)
- `show_weekends` - Display weekends (default: `true`)
- `first_day` - First day of week: `0` (Sunday) or `1` (Monday)
- `time_format` - Time format: `12h` or `24h`

#### **Events List**
```
[azure_calendar_events id="calendar_id"]
[azure_calendar_events id="calendar_id" limit="10" format="list" upcoming_only="true"]
```

**Parameters:**
- `id` - **Required**. Calendar ID
- `limit` - Number of events to display (default: 10)
- `format` - Display format: `list`, `grid`, `compact` (default: `list`)
- `show_dates` - Show event dates (default: `true`)
- `show_times` - Show event times (default: `true`)
- `show_location` - Show event location (default: `true`)
- `show_description` - Show event description (default: `false`)
- `date_format` - PHP date format (default: `M j, Y`)
- `time_format` - PHP time format (default: `g:i A`)
- `upcoming_only` - Show only future events (default: `true`)
- `class` - CSS class for styling

#### **Single Event**
```
[azure_calendar_event id="calendar_id" event_id="event_id"]
```

**Parameters:**
- `id` - **Required**. Calendar ID
- `event_id` - **Required**. Specific event ID
- `show_attendees` - Show event attendees (default: `false`)
- `show_description` - Show event description (default: `true`)
- `class` - CSS class for styling

---

## 🔄 **Calendar Sync (TEC Integration)**

**Admin Page**: Azure Plugin → Calendar → TEC Sync Tab

### **Overview**
Synchronize Microsoft 365 calendars with The Events Calendar (TEC) plugin. Automatically import Outlook events as WordPress events with category mapping and scheduled sync.

### **Prerequisites**
- The Events Calendar plugin must be installed and activated
- Calendar authentication (same as Calendar Embed module)

### **Configuration Steps**

#### **1. Authentication**

Use the same authentication as Calendar Embed:
- **Your M365 Account**: Your Microsoft 365 email address
- **Shared Mailbox Email** (optional): Shared mailbox to sync from
- Click **Authenticate Calendar**

#### **2. Create Calendar Mappings**

1. Click **Add Calendar Mapping**
2. In the modal:
   - **Outlook Calendar**: Select the source Microsoft calendar
   - **TEC Category**: Choose or create a TEC category
   - **Schedule Settings**:
     - Enable scheduled sync
     - Sync frequency (every 15min, 30min, hourly, daily)
     - Lookback days (how far in the past to sync)
     - Lookahead days (how far in the future to sync)
3. Click **Save Mapping**

#### **3. Sync Options**

- **Manual Sync**: Click **Sync Now** to immediately sync all mappings
- **Scheduled Sync**: Enable per-mapping automatic synchronization
- **Delete Mapping**: Remove calendar mappings you no longer need

### **How It Works**

1. Plugin fetches events from your Outlook calendar
2. Events are created/updated in The Events Calendar
3. Events are assigned to the mapped TEC category
4. Sync runs on the configured schedule
5. Duplicate events are prevented by checking event IDs

### **No Additional Shortcodes**

TEC Integration uses The Events Calendar's built-in shortcodes and display features. Refer to TEC documentation for displaying synced events.

---

## 📧 **Email**

**Admin Page**: Azure Plugin → Email

### **Overview**
Send emails through Microsoft Graph API instead of traditional SMTP. Includes contact forms, email queue management, and WordPress wp_mail() replacement.

### **Configuration Steps**

#### **1. Authentication Methods**

Choose your authentication approach:

- **User Authentication**: Send emails on behalf of specific users
  - Enter user email address
  - Authenticate with Microsoft
  - Grant Mail.Send permissions

- **Application Authentication**: Send emails as the application
  - Requires application-level permissions
  - No per-user authentication needed

#### **2. Email Settings**

- **From Name**: Default sender name for emails
- **From Email**: Default sender email address
- **Reply-To Email**: Email for replies
- **Replace wp_mail()**: Use Microsoft Graph for all WordPress emails

#### **3. Contact Form Configuration**

- **Enable Contact Forms**: Allow use of contact form shortcode
- **Default Recipient**: Email address to receive form submissions
- **Spam Protection**: Enable built-in spam filtering
- **Rate Limiting**: Limit form submissions per IP

#### **4. Email Queue**

- **Enable Queue**: Process emails asynchronously
- **Queue Processing**: Frequency of queue processing
- **Retry Failed**: Automatically retry failed emails
- **Max Retries**: Number of retry attempts

### **Email Shortcodes**

#### **Contact Form**
```
[azure_contact_form]
[azure_contact_form to="admin@site.com" subject="Contact Form Submission"]
```

**Parameters:**
- `to` - Recipient email address
- `subject` - Email subject line
- `show_phone` - Show phone number field (default: `false`)
- `show_company` - Show company field (default: `false`)
- `required_fields` - Comma-separated list: `name,email,phone,message`
- `success_message` - Message shown after successful submission
- `button_text` - Submit button text (default: "Send Message")
- `class` - CSS class for form styling

**Example with all options:**
```
[azure_contact_form 
    to="sales@company.com" 
    subject="Sales Inquiry" 
    show_phone="true" 
    show_company="true"
    required_fields="name,email,phone,message"
    success_message="Thanks! We'll contact you soon."
    button_text="Get in Touch"]
```

#### **Email Status** (Admin Only)
```
[azure_email_status]
[azure_email_status show_queue_count="true"]
```

**Parameters:**
- `show_queue_count` - Display pending email count (default: `true`)
- `show_failed_count` - Display failed email count (default: `true`)
- `show_success_rate` - Display success rate percentage (default: `true`)

#### **Email Queue** (Admin Only)
```
[azure_email_queue]
[azure_email_queue limit="20" status="pending"]
```

**Parameters:**
- `limit` - Number of emails to display (default: 10)
- `status` - Filter by status: `pending`, `sent`, `failed`, `all` (default: `all`)
- `show_details` - Show email content preview (default: `false`)

---

## 🏛️ **PTA Roles Management**

**Admin Page**: Azure Plugin → PTA Roles

### **Overview**
Complete organizational management system for PTAs and nonprofits. Manage departments, roles, and assignments with automatic Azure AD provisioning and Office 365 group synchronization.

### **Configuration Steps**

#### **1. Enable PTA Module**

1. Go to **Azure Plugin** → **Main Settings**
2. Enable **PTA Roles** module
3. Ensure you have these Azure permissions:
   - `User.ReadWrite.All`
   - `Group.ReadWrite.All`
   - `Directory.ReadWrite.All`

#### **2. Department Management**

1. Go to **PTA Roles** → **Departments**
2. Default departments are pre-configured:
   - Exec Board
   - Communications
   - Enrichment
   - Events
   - Volunteers
   - Ways and Means
3. Assign Vice Presidents (VPs) to each department
4. VPs become Azure AD managers for their department members

#### **3. Role Management**

1. Go to **PTA Roles** → **Roles**
2. 58+ pre-configured roles available
3. Each role has:
   - Name and description
   - Department assignment
   - Maximum occupancy
   - Current assignment count

#### **4. User Assignments**

1. Go to **PTA Roles** → **Assign Users**
2. Select a user
3. Assign to one or more roles
4. Set primary role (determines Azure AD manager)
5. Changes automatically sync to Azure AD

#### **5. Office 365 Groups**

1. Go to **PTA Roles** → **O365 Groups**
2. Click **Sync O365 Groups** to import groups from tenant
3. Create mappings:
   - Map individual roles to groups
   - Map entire departments to groups
4. Group memberships automatically sync based on role assignments

#### **6. Monitoring**

- **Sync Queue**: Monitor background jobs for user provisioning
- **Audit Logs**: Complete history of all organizational changes
- **Dashboard**: Overview of departments, roles, and assignments

### **How It Works**

1. **User Assignment**: Admin assigns WordPress user to PTA role
2. **Azure AD Provisioning**: If user doesn't have Azure AD account:
   - Account is automatically created
   - Email: `firstname+lastInitial@wilderptsa.net`
   - Office 365 Business Basic license assigned
   - Temporary password generated
3. **Manager Hierarchy**: Primary role determines Azure AD manager
   - Department VPs become managers
   - Hierarchy syncs to Azure AD
4. **Group Membership**: User is added to mapped Office 365 groups
5. **Job Title Sync**: Role assignments become Azure AD job titles

### **PTA Shortcodes**

#### **Roles Directory**
```
[pta-roles-directory]
[pta-roles-directory department="communications" columns="3" layout="team-cards"]
```

**Parameters:**
- `department` - Filter by department name or slug
- `description` - Show role descriptions (default: `false`)
- `status` - Filter by status: `all`, `open`, `filled`, `partial` (default: `all`)
- `columns` - Number of columns: 1-5 (default: 3)
- `show_count` - Show assignment count (default: `true`)
- `show_vp` - Show department VP (default: `false`)
- `layout` - Display layout: `grid`, `list`, `cards`, `team-cards` (default: `grid`)
- `show_avatars` - Show user avatars in team-cards layout (default: `true`)
- `show_contact` - Show contact links in team-cards layout (default: `true`)
- `avatar_size` - Avatar size in pixels (default: 80)

#### **Department Roles**
```
[pta-department-roles department="communications"]
[pta-department-roles department="events" show_vp="true" show_description="true"]
```

**Parameters:**
- `department` - **Required**. Department name or slug
- `show_vp` - Show department VP (default: `true`)
- `show_description` - Show role descriptions (default: `false`)
- `layout` - Display layout: `list`, `grid` (default: `list`)

#### **Org Chart**
```
[pta-org-chart]
[pta-org-chart department="all" interactive="true" height="500px"]
```

**Parameters:**
- `department` - Department to display or `all` (default: `all`)
- `interactive` - Enable interactive features (default: `false`)
- `height` - Chart height (default: `400px`)

#### **Role Card**
```
[pta-role-card role="president"]
[pta-role-card role="communications-vp" show_contact="true"]
```

**Parameters:**
- `role` - **Required**. Role name or slug
- `show_contact` - Show contact information (default: `false`)
- `show_description` - Show role description (default: `true`)
- `show_assignments` - Show assigned users (default: `true`)

#### **Department VP**
```
[pta-department-vp department="communications"]
[pta-department-vp department="events" show_email="true"]
```

**Parameters:**
- `department` - **Required**. Department name or slug
- `show_contact` - Show contact information (default: `false`)
- `show_email` - Show email address (default: `false`)

#### **Open Positions**
```
[pta-open-positions]
[pta-open-positions department="volunteers" limit="5"]
```

**Parameters:**
- `department` - Filter by department or `all` (default: `all`)
- `limit` - Maximum positions to show (default: -1 for all)
- `show_department` - Show department names (default: `true`)
- `show_description` - Show role descriptions (default: `false`)

#### **User Roles**
```
[pta-user-roles]
[pta-user-roles user_id="123" show_department="true"]
```

**Parameters:**
- `user_id` - User ID to display roles for (default: current user)
- `show_department` - Show department names (default: `true`)
- `show_description` - Show role descriptions (default: `false`)

---

## 📁 **OneDrive/SharePoint Media**

**Admin Page**: Azure Plugin → OneDrive Media

### **Overview**
Browse and attach files from OneDrive or SharePoint document libraries. Organize media in year-based folders and integrate cloud storage with WordPress.

### **Configuration Steps**

#### **1. Authentication**

- Enter your M365 email address
- Click **Authorize OneDrive Access**
- Grant permissions:
  - `Files.Read.All`
  - `Files.ReadWrite.All`
  - `Sites.Read.All`

#### **2. Storage Type Selection**

Choose your storage location:

**Option A: OneDrive**
1. Select **OneDrive**
2. Click **Browse Folders**
3. Select your base folder
4. Click **Select Folder**

**Option B: SharePoint**
1. Select **SharePoint**
2. Enter SharePoint site URL
3. Click **Browse Sites** to search for your site
4. Click **Browse Drives** to select document library
5. Click **Browse Folders** to select base folder

#### **3. Media Organization**

- **Base Folder**: Root folder for media files
- **Year-Based Folders**: Automatically organize media by year
- **Create Year Folders**: Click to generate folder structure

#### **4. Usage**

- **Browse Files**: View available files from OneDrive/SharePoint
- **Attach to Posts**: Link cloud files to WordPress content
- **Sync Files**: Keep local references updated

### **No Shortcodes Available**

Files are accessed through the WordPress media library interface.

---

## 📚 **Shortcodes Reference**

### **Quick Reference Table**

| Module | Shortcode | Purpose |
|--------|-----------|---------|
| **SSO** | `[azure_sso_login]` | Login button |
| | `[azure_sso_logout]` | Logout button |
| | `[azure_user_info]` | Display user information |
| **Calendar** | `[azure_calendar]` | Full calendar display |
| | `[azure_calendar_events]` | Events list |
| | `[azure_calendar_event]` | Single event |
| **Email** | `[azure_contact_form]` | Contact form |
| | `[azure_email_status]` | Email status (admin) |
| | `[azure_email_queue]` | Email queue (admin) |
| **PTA** | `[pta-roles-directory]` | All roles display |
| | `[pta-department-roles]` | Department-specific roles |
| | `[pta-org-chart]` | Organization chart |
| | `[pta-role-card]` | Single role details |
| | `[pta-department-vp]` | Department VP info |
| | `[pta-open-positions]` | Open positions list |
| | `[pta-user-roles]` | User's role assignments |

### **Shortcode Examples**

See each module's section above for detailed parameters and examples.

---

## 🔧 **Troubleshooting**

### **Common Issues**

#### **Authentication Failed**
- Verify Client ID, Client Secret, and Tenant ID are correct
- Check that redirect URI matches in Azure App Registration
- Ensure admin consent is granted for API permissions
- Clear browser cookies and try again

#### **Calendar Not Loading**
- Verify calendar authentication is complete
- Check that Calendar.Read permission is granted
- Clear calendar cache in plugin settings
- Check browser console for JavaScript errors

#### **Backup Failures**
- Verify Azure Storage account credentials
- Check that container exists and is accessible
- Ensure WordPress has write permissions
- Review backup logs for specific errors
- Increase PHP max execution time if needed

#### **Email Not Sending**
- Verify Mail.Send permission is granted
- Check email authentication status
- Review email queue for failed messages
- Check WordPress debug logs
- Verify sender email is from your domain

#### **PTA Sync Issues**
- Verify User.ReadWrite.All permission
- Check sync queue for failed jobs
- Ensure Office 365 licenses are available
- Review PTA sync engine logs
- Verify department VPs are assigned

#### **OneDrive Connection Failed**
- Check Files.Read.All permission
- Verify authentication is complete
- Test connection button
- Check site URL format for SharePoint

### **Debug Mode**

Enable WordPress debug mode for detailed logging:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs in `wp-content/debug.log` and `Azure Plugin/logs.md`

### **Testing Connections**

Each module has a **Test Connection** button to verify:
- Azure credentials are valid
- Permissions are granted
- API endpoints are accessible
- Authentication is working

### **Performance Issues**

- **Clear caches**: Plugin has cache clearing options
- **Reduce sync frequency**: Adjust scheduled sync intervals
- **Limit event lookback**: Reduce days for calendar sync
- **Increase PHP limits**: Memory and execution time
- **Enable object caching**: Use Redis or Memcached

---

## 📞 **Support & Documentation**

### **Getting Help**

1. **Check this README**: Comprehensive documentation for all features
2. **Admin Help Text**: Each settings page has helpful descriptions
3. **Test Connections**: Use built-in testing tools
4. **Review Logs**: Check plugin logs and WordPress debug logs
5. **WordPress Forums**: Post questions with plugin tag
6. **GitHub Issues**: Report bugs and feature requests

### **Useful Links**

- **Azure Portal**: https://portal.azure.com/
- **Microsoft Graph Explorer**: https://developer.microsoft.com/graph/graph-explorer
- **Azure AD Documentation**: https://docs.microsoft.com/azure/active-directory/
- **Microsoft Graph API**: https://docs.microsoft.com/graph/

### **Best Practices**

- **Start with Common Credentials**: Easier to manage initially
- **Test in Staging**: Try features on a test site first
- **Regular Backups**: Enable automated backups immediately
- **Monitor Sync Queues**: Check PTA sync status regularly
- **Review Permissions**: Grant only needed Azure permissions
- **Keep Updated**: Update plugin when new versions release

---

## 📄 **License**

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

---

## 🙏 **Credits**

This plugin integrates and enhances functionality from multiple Microsoft services:

- Microsoft Azure Active Directory
- Microsoft Graph API
- Azure Blob Storage
- Microsoft 365 / Office 365
- OneDrive for Business
- SharePoint Online
- Microsoft Outlook Calendar
- Exchange Online

**Built with ❤️ for WordPress and the Microsoft community.**

---

**Version**: 1.1  
**Author**: Jamie Burgess  
**Plugin URI**: https://github.com/jamieburgess/microsoft-wp

**Ready to get started?** Follow the [Initial Setup](#initial-setup--basic-configuration) guide above!