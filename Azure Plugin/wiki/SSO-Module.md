# SSO Authentication Module

Enable Single Sign-On (SSO) for WordPress using Azure Active Directory. Users can log in with their Microsoft 365 credentials.

---

## 📋 Overview

| Feature | Description |
|---------|-------------|
| Azure AD Login | Users sign in with Microsoft 365 accounts |
| Auto User Creation | Automatically create WordPress users |
| Claims Mapping | Sync name, email, department from Azure |
| Role Assignment | Set default role for new users |
| Login Page Integration | Add SSO button to wp-login.php |

---

## ⚙️ Configuration

### Location
**Azure Plugin → SSO Settings**

### Prerequisites
- Azure App Registration with `User.Read` permission
- HTTPS enabled on your site

### Settings

#### Credentials
| Setting | Description |
|---------|-------------|
| **Use Common Credentials** | Use shared plugin credentials |
| **Client ID** | App Registration client ID (if not using common) |
| **Client Secret** | App Registration secret (if not using common) |
| **Tenant ID** | Azure AD tenant ID (if not using common) |

#### Login Options
| Setting | Description |
|---------|-------------|
| **Show on Login Page** | Add SSO button to wp-login.php |
| **Button Text** | Customize the login button text |
| **Require SSO** | Force all logins through Azure AD ⚠️ |

#### User Creation
| Setting | Description |
|---------|-------------|
| **Auto Create Users** | Create WordPress users for new Azure AD users |
| **Default Role** | WordPress role for new users |
| **Create Custom Role** | Create "AzureAD" role for easy identification |

#### Synchronization
| Setting | Description |
|---------|-------------|
| **Enable Automatic Sync** | Periodically sync users from Azure AD |
| **Sync Frequency** | How often to sync (hourly/daily/weekly) |
| **Preserve Local Data** | Don't overwrite local user modifications |

---

## 🔄 Claims Mapping

Azure AD claims are automatically mapped to WordPress:

| Azure AD Claim | WordPress Field |
|----------------|-----------------|
| `displayName` | `display_name` |
| `givenName` | `first_name` |
| `surname` | `last_name` |
| `mail` | `user_email` |
| `userPrincipalName` | `user_email` (fallback) |
| `department` | `department` (user meta) |

---

## 📝 Shortcodes

### Login Button

```
[azure_sso_login]
```

**Attributes:**
| Attribute | Default | Description |
|-----------|---------|-------------|
| `text` | "Sign in with Microsoft" | Button text |
| `redirect` | Current page | URL after login |
| `class` | "" | Additional CSS classes |

**Examples:**
```
[azure_sso_login text="Employee Login" redirect="/dashboard"]
[azure_sso_login text="Sign in" class="my-button-class"]
```

### Logout Button

```
[azure_sso_logout]
```

**Attributes:**
| Attribute | Default | Description |
|-----------|---------|-------------|
| `text` | "Sign out" | Button text |
| `redirect` | Home page | URL after logout |

**Example:**
```
[azure_sso_logout text="Log out" redirect="/goodbye"]
```

### User Info Display

```
[azure_user_info]
```

**Attributes:**
| Attribute | Default | Description |
|-----------|---------|-------------|
| `field` | (all) | Specific field to display |
| `format` | "text" | Output format (text/json) |

**Available Fields:**
- `display_name`
- `first_name`
- `last_name`
- `email`
- `department`
- `user_login`
- `roles`

**Examples:**
```
[azure_user_info field="display_name"]
[azure_user_info field="department"]
[azure_user_info]  <!-- Shows all info -->
```

---

## 🔒 Security Considerations

### "Require SSO" Warning

⚠️ **Be careful with "Require SSO"!**

When enabled, ALL users must log in via Azure AD. This can lock you out if:
- Azure AD is misconfigured
- Your App Registration expires
- Microsoft services are down

**Recommendation:** Keep at least one local admin account and test thoroughly before enabling.

### Session Security

- Sessions are validated against Azure AD tokens
- Tokens expire based on Azure AD policies
- Users are logged out when Azure AD session ends

---

## 🔧 Troubleshooting

### "Invalid redirect URI"
1. Verify the redirect URI in Azure matches exactly
2. Check for HTTPS vs HTTP mismatch
3. Ensure no trailing slash differences

### "User not created"
1. Enable "Auto Create Users"
2. Check that `User.Read` permission is granted
3. Review logs at Azure Plugin → System Logs

### "Wrong role assigned"
1. Check default role setting
2. Verify user doesn't already exist
3. Role is only set on first creation

### "Department not syncing"
1. Ensure department is set in Azure AD
2. User.Read.All permission needed for full sync
3. Run manual sync to update existing users

---

## 📊 User Sync

### Manual Sync

1. Go to **Azure Plugin → SSO**
2. Click **Sync Users Now**
3. Review sync results

### Automatic Sync

Configure scheduled sync:
1. Enable **Automatic Sync**
2. Set **Sync Frequency**
3. Optionally enable **Preserve Local Data**

### What Gets Synced

- Display name
- First name
- Last name
- Email address
- Department
- User status (active/disabled)

---

## ➡️ Related

- **[Azure App Registration](Azure-App-Registration)** - Set up Azure app
- **[Troubleshooting](Troubleshooting)** - Common issues
- **[Advanced Configuration](Advanced-Configuration)** - Power user settings


