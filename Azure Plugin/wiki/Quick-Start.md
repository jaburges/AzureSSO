# Quick Start Guide

Get Microsoft WP up and running in 15 minutes! This guide covers the essentials to get your first module working.

---

## ⏱️ What We'll Do

1. Install the plugin (2 min)
2. Create Azure App Registration (5 min)
3. Configure plugin credentials (2 min)
4. Enable your first module (3 min)
5. Test it works (3 min)

**Total time: ~15 minutes**

---

## Step 1: Install the Plugin

### Option A: Upload ZIP (Recommended)

1. Download the latest release from [GitHub](https://github.com/jamieburgess/microsoft-wp/releases)
2. In WordPress, go to **Plugins → Add New → Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Click **Activate**

### Option B: Manual Upload

1. Extract the ZIP file
2. Upload the `azure-plugin` folder to `/wp-content/plugins/`
3. Go to **Plugins** in WordPress admin
4. Find "Microsoft WP" and click **Activate**

✅ **Success:** You should see "Azure Plugin" in your admin menu.

---

## Step 2: Create Azure App Registration

### 2.1 Open Azure Portal

1. Go to [portal.azure.com](https://portal.azure.com)
2. Sign in with your Microsoft 365 admin account
3. In the search bar, type "App registrations"
4. Click **App registrations**

### 2.2 Create New App

1. Click **+ New registration**
2. Fill in the form:

| Field | Value |
|-------|-------|
| **Name** | `WordPress Integration` |
| **Supported account types** | Accounts in this organizational directory only |
| **Redirect URI - Platform** | Web |
| **Redirect URI - URL** | `https://yoursite.com/wp-admin/admin-ajax.php?action=azure_sso_callback` |

> ⚠️ Replace `yoursite.com` with your actual domain!

3. Click **Register**

### 2.3 Note Your Credentials

On the Overview page, copy these values:
- **Application (client) ID** → This is your Client ID
- **Directory (tenant) ID** → This is your Tenant ID

### 2.4 Create Client Secret

1. In the left menu, click **Certificates & secrets**
2. Click **+ New client secret**
3. Description: `WordPress Plugin`
4. Expires: `24 months`
5. Click **Add**
6. **IMMEDIATELY copy the Value** (you won't see it again!)

> 🔐 **Save these three values:**
> - Client ID: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`
> - Tenant ID: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`
> - Client Secret: `xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`

### 2.5 Add API Permissions

1. Click **API permissions** in the left menu
2. Click **+ Add a permission**
3. Select **Microsoft Graph**
4. Add these permissions:

**For SSO (Delegated):**
- `User.Read`

**For Calendar Embed (Delegated):**
- `Calendars.Read`

**For Email (Delegated):**
- `Mail.Send`

5. Click **Grant admin consent for [Your Organization]**
6. Confirm by clicking **Yes**

✅ **Success:** All permissions should show green checkmarks.

---

## Step 3: Configure Plugin

### 3.1 Open Plugin Settings

1. In WordPress admin, go to **Azure Plugin**
2. You'll see the main dashboard

### 3.2 Enter Credentials

Scroll to the **Common Credentials** section:

| Field | Value |
|-------|-------|
| **Client ID** | Your Application (client) ID |
| **Client Secret** | Your secret value |
| **Tenant ID** | Your Directory (tenant) ID |

3. Click **Save Settings**

### 3.3 Test Connection

Click **Test Connection** button. You should see:
- ✅ "Connection successful!"

If you see an error:
- Double-check your credentials
- Verify the App Registration is complete
- Check that permissions are granted

---

## Step 4: Enable Your First Module

Let's enable SSO as our first module - it's the simplest to test.

### 4.1 Enable SSO Module

1. On the main Azure Plugin page, find the **SSO** module card
2. Click the **Enable** toggle
3. Click **Save Settings**

### 4.2 Configure SSO

1. Click on the SSO module card (or go to **Azure Plugin → SSO**)
2. Configure these settings:

| Setting | Recommended Value |
|---------|-------------------|
| **Use Common Credentials** | ✅ Enabled |
| **Show on Login Page** | ✅ Enabled |
| **Button Text** | "Sign in with Microsoft" |
| **Auto Create Users** | ✅ Enabled |
| **Default Role** | Subscriber |

3. Click **Save Settings**

---

## Step 5: Test SSO

### 5.1 Test the Login Button

1. Open a new browser window (incognito/private mode works best)
2. Go to `https://yoursite.com/wp-login.php`
3. You should see "Sign in with Microsoft" button below the regular login form

### 5.2 Complete Login

1. Click "Sign in with Microsoft"
2. Sign in with your Microsoft 365 account
3. Accept any permission prompts
4. You should be redirected back to WordPress, logged in!

### 5.3 Verify User

1. Go to **Users** in WordPress admin
2. Find the user that just logged in
3. Check that their name and email came from Azure AD

✅ **Success!** SSO is working!

---

## 🎉 What's Next?

Now that you have the basics working, explore more modules:

### Popular Next Steps

| Module | What It Does | Guide |
|--------|--------------|-------|
| **Calendar Embed** | Display Outlook calendars | [Calendar Embed Guide](Calendar-Embed-Module) |
| **Backup** | Automatic backups to Azure | [Backup Guide](Backup-Module) |
| **Email** | Send emails via Microsoft | [Email Guide](Email-Module) |

### Module Comparison

| Need | Recommended Module |
|------|-------------------|
| Show Outlook calendar on site | Calendar Embed |
| Sync events to The Events Calendar | Calendar Sync |
| Send reliable emails | Email |
| Back up to cloud | Backup |
| Manage volunteer roles | PTA Roles |
| Sell classes with events | Classes |

---

## 🆘 Quick Troubleshooting

### "Invalid redirect URI"
- Check that the redirect URI in Azure exactly matches your site URL
- Ensure you're using HTTPS
- The full URI should be: `https://yoursite.com/wp-admin/admin-ajax.php?action=azure_sso_callback`

### "Consent required"
- Go back to Azure → API permissions
- Click "Grant admin consent"
- Make sure all permissions have green checkmarks

### "Connection failed"
- Verify Client ID, Secret, and Tenant ID are correct
- Check that the client secret hasn't expired
- Ensure no extra spaces in the credential fields

### Need More Help?
- Check [Troubleshooting](Troubleshooting) for more solutions
- Review the [Installation](Installation) guide for detailed steps
- [Open an issue](https://github.com/jamieburgess/microsoft-wp/issues) on GitHub

---

*Congratulations on getting started! 🎉 Explore the [module guides](Home) to unlock more features.*


