# Email Module

Send WordPress emails through Microsoft Graph API for reliable delivery and better tracking.

---

## 📋 Overview

| Feature | Description |
|---------|-------------|
| Graph API Email | Send via Microsoft infrastructure |
| Email Logging | Track all sent emails |
| WordPress Integration | Replaces wp_mail() |
| Template Support | Reusable email templates |

---

## ⚙️ Configuration

### Location
**Azure Plugin → Email**

### Prerequisites
- Azure App Registration with `Mail.Send` permission (delegated or application)

### Settings

| Setting | Description |
|---------|-------------|
| **Use Common Credentials** | Use shared plugin credentials |
| **From Email** | Email address to send from |
| **From Name** | Display name for sender |
| **Enable Logging** | Log all sent emails |

### Sender Configuration

**Delegated Permission (Mail.Send delegated):**
- Sends as the authenticated user
- User must have a mailbox

**Application Permission (Mail.Send application):**
- Can send as any user in organization
- Specify sender in settings

---

## 📧 How It Works

### WordPress Integration

When enabled, the plugin:
1. Hooks into WordPress `wp_mail()` function
2. Redirects emails through Microsoft Graph
3. Logs results (if enabled)
4. Returns success/failure to WordPress

### Compatible With

- Contact Form 7
- WooCommerce emails
- User notification emails
- Password reset emails
- Any plugin using wp_mail()

---

## 📊 Email Logging

### Location
**Azure Plugin → Email Logs**

### Logged Information

| Field | Description |
|-------|-------------|
| **Date/Time** | When email was sent |
| **To** | Recipient email(s) |
| **Subject** | Email subject line |
| **Status** | Success or Failed |
| **Error** | Error message if failed |

### Log Retention

- Logs are stored in database
- Configure retention in settings
- Old logs automatically purged

---

## 📝 Shortcode

### Email Form

```
[azure_email_form]
```

Creates a simple contact form.

**Attributes:**
| Attribute | Default | Description |
|-----------|---------|-------------|
| `to` | Admin email | Recipient |
| `subject` | "Contact Form" | Email subject |
| `success` | "Sent!" | Success message |
| `button` | "Send" | Submit button text |

**Example:**
```
[azure_email_form to="info@org.net" subject="Website Inquiry"]
```

---

## 🔧 Troubleshooting

### "Email not sending"

1. Verify `Mail.Send` permission granted
2. Check sender email is valid
3. Review logs for specific errors
4. Test with simple recipient

### "Emails going to spam"

1. Microsoft 365 has good deliverability
2. Check SPF/DKIM records for domain
3. Avoid spam trigger words
4. Include unsubscribe option for marketing

### "Permission denied"

1. For delegated: user must have mailbox
2. For application: admin consent required
3. Verify correct permission type added

---

## ➡️ Related

- **[Azure App Registration](Azure-App-Registration)** - Set up permissions
- **[Troubleshooting](Troubleshooting)** - Common issues


