# Azure App Registration Guide

This guide walks you through creating and configuring an Azure App Registration for Microsoft WP.

---

## 📋 What You'll Need

- Microsoft 365 admin account
- Access to [Azure Portal](https://portal.azure.com)
- Your WordPress site URL (must be HTTPS)

---

## Step 1: Access Azure Portal

### 1.1 Sign In

1. Open [portal.azure.com](https://portal.azure.com)
2. Sign in with your **Microsoft 365 administrator** account
3. If prompted, complete MFA (multi-factor authentication)

### 1.2 Navigate to App Registrations

1. In the search bar at the top, type "App registrations"
2. Click **App registrations** under Services

![Azure Portal Search](https://docs.microsoft.com/en-us/azure/active-directory/develop/media/quickstart-register-app/azure-portal-new-app-registration.png)

---

## Step 2: Create New Registration

### 2.1 Start New Registration

1. Click **+ New registration** button

### 2.2 Configure Basic Settings

Fill in the registration form:

| Field | Value | Notes |
|-------|-------|-------|
| **Name** | `WordPress Integration` | Any descriptive name |
| **Supported account types** | `Accounts in this organizational directory only` | For single organization |
| **Redirect URI - Platform** | `Web` | Required for OAuth |
| **Redirect URI - URI** | See below | Your specific URL |

### 2.3 Redirect URI

The redirect URI must match exactly. Use this format:

```
https://yoursite.com/wp-admin/admin-ajax.php?action=azure_sso_callback
```

**Examples:**
- `https://example.org/wp-admin/admin-ajax.php?action=azure_sso_callback`
- `https://mysite.com/wp-admin/admin-ajax.php?action=azure_sso_callback`
- `https://subdomain.example.com/wp-admin/admin-ajax.php?action=azure_sso_callback`

> ⚠️ **Important:**
> - Must use HTTPS (not HTTP)
> - Must match your exact domain
> - Include www if your site uses it
> - Case-sensitive

### 2.4 Complete Registration

Click **Register**

You'll be taken to your new app's Overview page.

---

## Step 3: Note Your Credentials

On the **Overview** page, copy these values:

### Application (client) ID
- Find "Application (client) ID"
- Click the copy icon
- Save as: **Client ID**

### Directory (tenant) ID
- Find "Directory (tenant) ID"
- Click the copy icon
- Save as: **Tenant ID**

```
Example values:
Client ID: 12345678-1234-1234-1234-123456789012
Tenant ID: 87654321-4321-4321-4321-210987654321
```

---

## Step 4: Create Client Secret

### 4.1 Navigate to Secrets

1. In the left menu, click **Certificates & secrets**
2. Click the **Client secrets** tab

### 4.2 Add New Secret

1. Click **+ New client secret**
2. Fill in:
   - **Description:** `WordPress Plugin`
   - **Expires:** `24 months` (recommended)
3. Click **Add**

### 4.3 Copy the Secret Value

> ⚠️ **CRITICAL:** Copy the **Value** immediately! It will only be shown once.

- Copy the value in the **Value** column (NOT the Secret ID)
- Save as: **Client Secret**

```
Example:
Client Secret: ABC~123456789abcdefghijklmnop.qrs
```

If you lose the secret, you'll need to create a new one.

---

## Step 5: Configure API Permissions

### 5.1 Navigate to Permissions

1. In the left menu, click **API permissions**
2. You'll see `User.Read` is already added (default)

### 5.2 Add Required Permissions

Click **+ Add a permission** → **Microsoft Graph**

#### For SSO Module

**Delegated permissions:**
- `User.Read` (already added by default)

**Application permissions (for user sync):**
- `User.Read.All`

#### For Calendar Modules

**Delegated permissions:**
- `Calendars.Read` - View calendars
- `Calendars.ReadWrite` - Sync calendars (two-way)

**Application permissions:**
- `Calendars.Read` - Background sync
- `Calendars.ReadWrite` - Background sync (two-way)

#### For Email Module

**Delegated permissions:**
- `Mail.Send` - Send as user

**Application permissions:**
- `Mail.Send` - Send as any user

#### For PTA Groups Module

**Application permissions:**
- `Group.Read.All` - Read groups
- `Group.ReadWrite.All` - Manage group membership

#### For OneDrive Module

**Delegated permissions:**
- `Files.Read.All` - Read files
- `Files.ReadWrite.All` - Full access

### Permission Summary Table

| Module | Delegated | Application |
|--------|-----------|-------------|
| SSO | `User.Read` | `User.Read.All` |
| Calendar Embed | `Calendars.Read` | - |
| Calendar Sync | `Calendars.ReadWrite` | `Calendars.ReadWrite` |
| Email | `Mail.Send` | `Mail.Send` |
| PTA Groups | - | `Group.ReadWrite.All` |
| OneDrive | `Files.ReadWrite.All` | - |

### 5.3 Grant Admin Consent

> ⚠️ **Required Step!**

1. After adding all permissions, click **Grant admin consent for [Your Organization]**
2. Click **Yes** to confirm
3. Verify all permissions show ✅ green checkmarks

![Grant Consent](https://docs.microsoft.com/en-us/azure/active-directory/develop/media/quickstart-configure-app-access-web-apis/portal-grant-consent.png)

---

## Step 6: Add Additional Redirect URIs (Optional)

If you need multiple redirect URIs (e.g., staging site):

1. Go to **Authentication** in the left menu
2. Under "Web" section, click **Add URI**
3. Add additional URIs:
   ```
   https://staging.example.com/wp-admin/admin-ajax.php?action=azure_sso_callback
   https://dev.example.com/wp-admin/admin-ajax.php?action=azure_sso_callback
   ```
4. Click **Save**

---

## Step 7: Configure Token Settings (Optional)

For advanced scenarios, configure tokens:

1. Go to **Token configuration** in left menu
2. Add optional claims if needed:
   - `email` - User email
   - `family_name` - Last name
   - `given_name` - First name

---

## 🔐 Your Credentials Summary

You should now have these three values:

| Credential | Format | Example |
|------------|--------|---------|
| **Client ID** | UUID | `12345678-1234-1234-1234-123456789012` |
| **Client Secret** | String | `ABC~123456789abcdefghijk` |
| **Tenant ID** | UUID | `87654321-4321-4321-4321-210987654321` |

Keep these secure! They provide access to your Microsoft 365 environment.

---

## ➡️ Next Steps

1. **[Configure WordPress](Quick-Start#step-3-configure-plugin)** - Enter credentials in plugin
2. **[Enable SSO](SSO-Module)** - Set up single sign-on
3. **[Explore Modules](Home)** - Configure other features

---

## 🔄 Maintenance

### Rotating Client Secrets

Client secrets expire after 24 months. To rotate:

1. Create a new secret BEFORE the old one expires
2. Update the secret in WordPress
3. Test the connection
4. Delete the old secret

### Monitoring

Check your app's usage:
1. Go to your App Registration
2. Click **Monitoring → Audit logs**
3. Review sign-in attempts and API calls

### Troubleshooting

#### "AADSTS50011: Reply URL does not match"
- Check redirect URI exactly matches your site URL
- Verify HTTPS vs HTTP
- Check for trailing slashes

#### "AADSTS7000215: Invalid client secret"
- Secret may have expired
- Create a new secret
- Verify you copied the Value (not Secret ID)

#### "AADSTS65001: User consent required"
- Grant admin consent in API permissions
- Or enable user consent in Azure AD settings

---

*Need help? Check [Troubleshooting](Troubleshooting) or [open an issue](https://github.com/jamieburgess/microsoft-wp/issues).*


