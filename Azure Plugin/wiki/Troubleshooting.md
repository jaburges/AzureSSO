# Troubleshooting Guide

Solutions to common issues with Microsoft WP plugin.

---

## 🔍 General Debugging

### Enable Debug Mode

Add to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### View Plugin Logs

Go to **Azure Plugin → System Logs** to view:
- Error messages
- API responses
- Sync results
- Authentication issues

### Clear Caches

1. Clear browser cache
2. Clear WordPress caches:
   - Object cache
   - Page cache
   - Transient cache

---

## 🔐 Authentication Issues

### "Invalid redirect URI"

**Symptoms:** Error after Microsoft sign-in

**Solutions:**
1. Check redirect URI in Azure matches exactly:
   ```
   https://yoursite.com/wp-admin/admin-ajax.php?action=azure_sso_callback
   ```
2. Verify HTTPS vs HTTP
3. Check for trailing slashes
4. Ensure domain matches exactly (www vs non-www)

### "Consent required"

**Symptoms:** Permission error during auth

**Solutions:**
1. Go to Azure → API permissions
2. Click "Grant admin consent"
3. Verify green checkmarks on all permissions

### "Token expired"

**Symptoms:** Features stop working after a while

**Solutions:**
1. Re-authenticate in module settings
2. Check token refresh is working
3. Verify Azure AD session policies

### "Invalid client secret"

**Symptoms:** Connection test fails

**Solutions:**
1. Client secrets expire (max 24 months)
2. Create new secret in Azure
3. Update in WordPress settings

---

## 📅 Calendar Issues

### "No calendars found"

**Symptoms:** Empty calendar list after auth

**Solutions:**
1. Verify delegate access to shared mailbox:
   - Check in Microsoft 365 Admin
   - User must have "Full Access" or "Send As"
2. Click "Refresh Calendars"
3. Check `Calendars.Read` permission granted

### "Events not showing"

**Symptoms:** Calendar displays but no events

**Solutions:**
1. Check date range settings
2. Verify events exist in Outlook
3. Clear calendar cache
4. Check timezone settings

### "Wrong timezone"

**Symptoms:** Events show at wrong times

**Solutions:**
1. Set timezone in shortcode:
   ```
   [azure_calendar timezone="America/Los_Angeles"]
   ```
2. Update default timezone in settings
3. Check WordPress timezone (Settings → General)
4. Verify Outlook event timezones

---

## 💾 Backup Issues

### "Backup stuck at X%"

**Symptoms:** Progress stops, never completes

**Solutions:**
1. Increase PHP limits:
   ```php
   // wp-config.php
   define('WP_MEMORY_LIMIT', '512M');
   ini_set('max_execution_time', 600);
   ```
2. Exclude large directories
3. Try backing up one component at a time
4. Check Azure Storage connectivity

### "Storage connection failed"

**Symptoms:** Can't connect to Azure Storage

**Solutions:**
1. Verify account name (no .blob.core.windows.net)
2. Check access key is correct (Key, not connection string)
3. Verify container name has no special characters
4. Check firewall settings on storage account

### "Restoration failed"

**Symptoms:** Can't restore from backup

**Solutions:**
1. Verify backup file exists in Azure
2. Check file permissions on server
3. Ensure sufficient disk space
4. Try partial restoration

---

## 📧 Email Issues

### "Email not sending"

**Symptoms:** Emails fail silently

**Solutions:**
1. Check Email Logs for errors
2. Verify `Mail.Send` permission
3. Test with simple recipient
4. Check sender email is valid M365 mailbox

### "Emails going to spam"

**Symptoms:** Deliverability issues

**Solutions:**
1. Verify SPF record includes Microsoft
2. Check DKIM is configured
3. Use organizational domain
4. Avoid spam trigger words

---

## 👥 PTA/Roles Issues

### "Table doesn't exist"

**Symptoms:** Database errors in PTA module

**Solutions:**
1. Go to **Azure Plugin → PTA Roles**
2. Click **Reimport Default Tables**
3. Tables are created automatically

### "O365 Groups not syncing"

**Symptoms:** Group membership not updating

**Solutions:**
1. Check `Group.ReadWrite.All` permission
2. Grant admin consent
3. Verify group exists in M365
4. Check sync logs for errors

---

## 🛒 Classes Issues

### "Add to Cart not showing"

**Symptoms:** No purchase button on class products

**Solutions:**
1. Ensure product is published (not draft)
2. Verify stock/spots available
3. Check price is set (fixed) or variable pricing enabled
4. Flush permalinks (Settings → Permalinks → Save)

### "Events not creating"

**Symptoms:** TEC events not generated

**Solutions:**
1. Verify The Events Calendar is active
2. Check all schedule fields are complete
3. Ensure dates are in the future
4. Save product (not auto-draft)

### "Provider taxonomy error"

**Symptoms:** "Invalid Taxonomy" message

**Solutions:**
1. Ensure Classes module is enabled
2. Flush permalinks
3. Deactivate/reactivate plugin

---

## 🔧 Performance Issues

### "Slow page loads"

**Symptoms:** Pages with plugin content load slowly

**Solutions:**
1. Enable caching (plugin caches API responses)
2. Increase cache duration in settings
3. Use page caching plugin
4. Limit API requests per page

### "Memory exhausted"

**Symptoms:** Fatal error about memory

**Solutions:**
```php
// wp-config.php
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '512M');
```

### "Timeout errors"

**Symptoms:** Operations fail with timeout

**Solutions:**
```php
// wp-config.php or php.ini
ini_set('max_execution_time', 300);
```

---

## 🔄 Sync Issues

### "Duplicate events"

**Symptoms:** Same event appears multiple times

**Solutions:**
1. Check for manually created duplicates
2. Use "Outlook wins" conflict resolution
3. Review sync mapping configuration

### "Sync never completes"

**Symptoms:** Sync runs but doesn't finish

**Solutions:**
1. Reduce lookahead/lookback days
2. Check for API rate limiting
3. Run sync in off-peak hours

---

## 📱 SSO Issues

### "Infinite redirect loop"

**Symptoms:** Browser keeps redirecting

**Solutions:**
1. Check redirect URI matches exactly
2. Disable "Require SSO" temporarily
3. Clear browser cookies
4. Verify user exists or auto-create is enabled

### "User not created"

**Symptoms:** SSO works but no WP user

**Solutions:**
1. Enable "Auto Create Users"
2. Check `User.Read` permission
3. Verify email claim is present
4. Check logs for creation errors

---

## 💡 Still Stuck?

### Check Logs

1. WordPress debug log: `/wp-content/debug.log`
2. Plugin logs: **Azure Plugin → System Logs**
3. Browser console: F12 → Console tab

### Gather Information

When reporting issues, include:
- WordPress version
- PHP version
- Plugin version
- Error messages
- Steps to reproduce

### Get Help

- [Open an issue](https://github.com/jamieburgess/microsoft-wp/issues)
- Include all relevant details
- Share sanitized logs (remove credentials!)

---

## ➡️ Related

- **[Advanced Configuration](Advanced-Configuration)** - Power user settings
- **[Development](Development)** - Debug techniques


