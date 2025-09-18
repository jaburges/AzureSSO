# AcyMailing PTA Roles Add-on

This WordPress plugin integrates AcyMailing with PTA Roles Manager, allowing you to insert dynamic PTA role information into your email campaigns.

## Requirements

- **WordPress** 5.0 or higher
- **AcyMailing** plugin (any version)
- **PTA Roles Manager** plugin

## Installation

This is a **WordPress plugin** that integrates with AcyMailing, similar to how the official AcyMailing WooCommerce integration works.

### Method 1: WordPress Plugin Installation (Recommended)

1. **Download/Upload the Plugin**
   - Copy the entire `acymailer-ptaroles-addon` folder to your `/wp-content/plugins/` directory
   - Or zip the folder and upload via WordPress admin → Plugins → Add New → Upload Plugin

2. **Activate the Plugin**
   - Go to WordPress admin → **Plugins**
   - Find **"AcyMailing integration for PTA Roles Manager"** and click **Activate**

3. **Verify Installation**
   - Go to **AcyMailing → Configuration → Add-ons**
   - You should see **"PTA Roles"** listed as an available add-on
   - Make sure it shows as **enabled** (green checkmark)

### Requirements
- **WordPress** 5.0 or higher
- **AcyMailing** plugin (version 10.4.0 or higher)
- **PTA Roles Manager** plugin must be installed and active

### Troubleshooting Installation

**Plugin doesn't appear in AcyMailing add-ons:**
- Verify both AcyMailing and PTA Roles Manager are active
- Check that you're using AcyMailing version 10.4.0+
- Deactivate and reactivate the integration plugin

**Dynamic text options don't appear:**
- Refresh the AcyMailing editor page
- Make sure you have PTA roles created in PTA Roles Manager
- Check WordPress error logs for PHP errors

**Empty content in emails:**
- Verify that PTA roles are published and have assigned users
- Make sure email recipients exist as WordPress users
- Check that role assignments are saved properly in PTA Roles Manager

## Usage

Once installed and activated, the add-on will appear in the AcyMailing email editor as a new dynamic text type called **"PTA Roles"**.

### Accessing Dynamic Text Options

1. **Create or edit an email** in AcyMailing
2. **Click the "Dynamic text" button** in the email editor
3. **Select "PTA Roles"** from the category dropdown
4. **Choose from available insertion options**

### Available Dynamic Text Options

#### User-Specific Information
These options insert personalized content based on the email recipient:

- **User's Assigned Roles** - Simple list of roles assigned to the user
- **User's Roles (with descriptions)** - Detailed list with role descriptions
- **Number of User's Roles** - Count of roles assigned to the user

#### Organization Information
These options insert general PTA information (same for all recipients):

- **List of Open Positions** - All currently unfilled roles
- **Number of Open Positions** - Total count of open positions
- **Executive Board Directory** - List of executive board members
- **Committee Chairs Directory** - List of committee chairs

#### Complete Directories
Full directory listings for newsletters and communication:

- **Complete PTA Directory** - All roles and their holders in table format
- **Complete Directory (with descriptions)** - Detailed directory with role descriptions

#### Statistics
Useful statistics for PTA communications:

- **Total Number of Roles** - Count of all PTA roles
- **Number of Filled Roles** - Count of roles that are fully staffed
- **Total Number of Volunteers** - Unique count of all volunteers

## How It Works

### User-Specific Content
When you insert user-specific dynamic text (like "User's Assigned Roles"), AcyMailing will:
1. Find the WordPress user by email address
2. Query the PTA Roles Manager database for roles assigned to that user
3. Generate personalized content for each email recipient

### Organization Content
When you insert organization-wide content (like "Open Positions"), AcyMailing will:
1. Query all PTA roles from the database
2. Generate the same content for all email recipients
3. Cache the content for better performance

## Examples

### Newsletter Template
```
Dear {user:name},

Thank you for your continued involvement in our PTA! Here are your current roles:
{ptaroles:user_roles_detailed}

We currently have {ptaroles:open_roles_count} open positions that need volunteers:
{ptaroles:open_roles}

Best regards,
PTA Communications
```

### Directory Email
```
PTA Member Directory - {date:format:F Y}

{ptaroles:full_directory_detailed}

Statistics:
- Total Roles: {ptaroles:total_roles}
- Filled Roles: {ptaroles:filled_roles} 
- Active Volunteers: {ptaroles:volunteer_count}
```

### Recruitment Email
```
Hi {user:name},

We have exciting volunteer opportunities available! Currently, we have {ptaroles:open_roles_count} open positions:

{ptaroles:open_roles}

Your current involvement: {ptaroles:user_roles}

Contact us if you're interested in taking on additional roles!
```

## Technical Notes

### Performance Considerations
- Organization-wide content is generated once per email campaign
- User-specific content is generated for each recipient
- The plugin caches database queries when possible

### Styling
The generated HTML includes CSS classes for styling:
- `.pta-directory-simple` - Simple table format
- `.pta-directory-detailed` - Detailed card format  
- `.pta-open-roles` - Open positions list
- `.user-pta-roles` - User's role list

### Troubleshooting

**Plugin won't activate:**
- Ensure AcyMailing and PTA Roles Manager are both installed and active
- Check WordPress error logs for specific error messages

**Dynamic text not appearing:**
- Make sure the plugin is activated
- Try refreshing the AcyMailing editor page
- Check that you have PTA roles created in the PTA Roles Manager

**Empty content in emails:**
- Verify that PTA roles are published and have assigned users
- Check that email recipients exist as WordPress users
- Ensure role assignments are saved properly in PTA Roles Manager

## Support

This add-on integrates with:
- **PTA Roles Manager** - For role data and user assignments
- **AcyMailing** - For email campaign functionality

For issues related to:
- **Role assignments or data** - Check PTA Roles Manager settings
- **Email sending or templates** - Check AcyMailing configuration  
- **Integration problems** - Check WordPress error logs

## Changelog

### Version 1.0.0
- Initial release
- User-specific role information
- Organization directories
- Open position listings
- Statistics and counts
- Full integration with AcyMailing editor
