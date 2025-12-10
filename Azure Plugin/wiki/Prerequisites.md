# Prerequisites

Before installing Microsoft WP, you'll need to have a few things in place. This guide covers everything from Microsoft accounts to Azure setup.

---

## 📋 Checklist

- [ ] WordPress 5.0+ with admin access
- [ ] PHP 7.4+ on your server
- [ ] Microsoft 365 tenant (or personal Microsoft account)
- [ ] Azure Active Directory access
- [ ] HTTPS enabled on your WordPress site

---

## 🌐 Understanding Microsoft Accounts & Services

### What is a Microsoft Account?

A Microsoft account is your identity for accessing Microsoft services. There are two types:

1. **Personal Account** (@outlook.com, @hotmail.com, @live.com)
   - Free to create
   - Access to consumer Microsoft services
   - Limited Azure AD features

2. **Work/School Account** (@yourorganization.com)
   - Part of a Microsoft 365 or Azure AD tenant
   - Managed by your organization
   - Full access to enterprise features

> 💡 **For organizations**, you'll typically use work/school accounts tied to your Microsoft 365 subscription.

### What is Microsoft 365?

Microsoft 365 (formerly Office 365) is Microsoft's cloud productivity suite including:
- **Exchange Online** - Email and calendars
- **SharePoint** - File storage and collaboration
- **Teams** - Chat and video conferencing
- **OneDrive** - Personal cloud storage
- **Azure Active Directory** - Identity and access management

### What is Azure?

Azure is Microsoft's cloud computing platform. Key services for this plugin:
- **Azure Active Directory (Azure AD)** - Manages user identities and app access
- **Azure Blob Storage** - Cloud storage for backups
- **App Registrations** - Allows applications to access Microsoft APIs

---

## 🆓 Free Resources for Nonprofits

If you're a nonprofit organization, Microsoft offers generous free programs:

### Microsoft 365 Business Basic - FREE

**What You Get:**
- 300 free user licenses
- Email with 50GB mailbox
- 1TB OneDrive storage per user
- Microsoft Teams
- SharePoint Online
- Azure Active Directory

**How to Apply:**
1. Go to [Microsoft Nonprofits](https://nonprofit.microsoft.com/en-us/getting-started)
2. Verify your nonprofit status
3. Apply for Microsoft 365 Business Basic grant
4. Wait for approval (usually 3-10 business days)

**Eligibility:**
- Must be a registered nonprofit
- Tax-exempt status required
- Must be in a participating country

### Azure for Nonprofits - $3,500/year

**What You Get:**
- $3,500 annual Azure credits
- Access to all Azure services
- Supports backups, hosting, and more

**How to Apply:**
1. Visit [Azure for Nonprofits](https://www.microsoft.com/en-us/nonprofits/azure)
2. Sign in with your nonprofit Microsoft account
3. Complete the application
4. Credits are applied automatically

> 💡 **Tip:** The Azure credits are more than enough for typical WordPress backup storage and App Registration usage.

---

## 💻 WordPress Requirements

### Server Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| WordPress | 5.0+ | Latest version |
| PHP | 7.4+ | 8.0+ |
| MySQL | 5.6+ | 8.0+ |
| Memory Limit | 128M | 256M+ |
| Max Execution | 60s | 300s |

### Plugins (Optional)

Some modules require additional plugins:

| Plugin | Required For |
|--------|--------------|
| [The Events Calendar](https://theeventscalendar.com/) | Calendar Sync, Classes, Upcoming Events |
| [WooCommerce](https://woocommerce.com/) | Classes module |

### HTTPS Requirement

**Your site MUST use HTTPS** (SSL/TLS) for:
- OAuth authentication flows
- Secure API communication
- Azure AD compliance

Most hosts provide free SSL through Let's Encrypt.

---

## ☁️ Azure Requirements

### Accessing Azure Portal

1. Go to [portal.azure.com](https://portal.azure.com)
2. Sign in with your Microsoft 365 admin account
3. You'll see the Azure Portal dashboard

### Azure Active Directory

Every Microsoft 365 subscription includes Azure AD. To verify:

1. In Azure Portal, search for "Azure Active Directory"
2. Click on it to access your directory
3. Note your **Tenant ID** (under Overview → Tenant ID)

### Required Permissions

To create an App Registration, you need one of these roles:
- **Global Administrator**
- **Application Administrator**
- **Cloud Application Administrator**

> ⚠️ If you don't have these roles, ask your IT administrator for help.

---

## 🔑 Understanding App Registrations

An **App Registration** is how you give WordPress permission to access Microsoft APIs.

### What It Does
- Creates a unique identity for your WordPress site
- Defines what Microsoft services WordPress can access
- Provides secure authentication credentials

### What You'll Need
After creating an App Registration, you'll have:
- **Client ID** - Your app's unique identifier
- **Client Secret** - Your app's password (keep this safe!)
- **Tenant ID** - Your organization's unique identifier

### Security Note
- Client secrets expire (max 24 months)
- Store credentials securely in WordPress
- Never share credentials publicly
- Rotate secrets periodically

---

## 📝 Pre-Installation Checklist

Before proceeding to installation, confirm:

### Microsoft Setup
- [ ] I have a Microsoft 365 account (or know who manages it)
- [ ] I can access [portal.azure.com](https://portal.azure.com)
- [ ] I have permission to create App Registrations

### WordPress Setup
- [ ] My site uses HTTPS
- [ ] I have WordPress admin access
- [ ] PHP version is 7.4 or higher
- [ ] I've installed any required plugins (TEC, WooCommerce)

### Information Ready
- [ ] I know which modules I want to use
- [ ] I have the email addresses for shared mailboxes (if using calendars)
- [ ] I understand which API permissions I'll need

---

## ➡️ Next Steps

Once you've confirmed the prerequisites:

1. **[Installation](Installation)** - Install the plugin
2. **[Azure App Registration](Azure-App-Registration)** - Create your Azure app
3. **[Quick Start](Quick-Start)** - Configure and test

---

*Need help with prerequisites? [Open an issue](https://github.com/jamieburgess/microsoft-wp/issues) and we'll help you get started.*


