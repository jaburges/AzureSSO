# PTA Office Content Editor - Deployment Guide

## Pre-Deployment Checklist

### ✅ Prerequisites Verification
- [ ] WordPress site has Azure AD SSO plugin installed and working
- [ ] WordPress REST API is accessible (test at: `yoursite.com/wp-json/wp/v2/posts`)
- [ ] You have Azure AD App Registration details (Client ID, Tenant ID)
- [ ] You have a web server or hosting to serve the static files

### ✅ Azure AD Configuration
- [ ] Azure AD App Registration exists (same one used by WordPress)
- [ ] Redirect URI will be added after deployment
- [ ] Required permissions: `openid`, `profile`, `email`, `User.Read`
- [ ] App registration allows public client flows (if needed)

### ✅ WordPress Configuration  
- [ ] REST API is enabled (default in WordPress 4.7+)
- [ ] Users have appropriate roles (Editor/Administrator for full access)
- [ ] Media uploads are enabled and working
- [ ] CORS is configured if needed (usually not required)

## Deployment Steps

### 1. Prepare Configuration
1. Copy `config/config.template.js` to `config/config.js`
2. Update all configuration values:
   ```javascript
   azure: {
       clientId: 'your-actual-client-id',
       authority: 'https://login.microsoftonline.com/your-actual-tenant-id',
       redirectUri: 'https://yourdomain.com/ptoffice-editor/', // Update after upload
   },
   wordpress: {
       baseUrl: 'https://yourdomain.com', // Your WordPress site
       apiBase: 'https://yourdomain.com/wp-json/wp/v2',
       mediaApi: 'https://yourdomain.com/wp-json/wp/v2/media'
   }
   ```

### 2. Upload Files
Upload all files in the `ptoffice-editor` folder to your web server:

**Via FTP/SFTP:**
```
yourdomain.com/
├── ptoffice-editor/
│   ├── index.html
│   ├── test-config.html
│   ├── config/
│   │   └── config.js
│   ├── css/
│   ├── js/
│   └── assets/
```

**Via cPanel File Manager:**
1. Navigate to public_html (or appropriate directory)
2. Create `ptoffice-editor` folder
3. Upload all contents to this folder

### 3. Update Azure AD Redirect URI
1. Go to Azure Portal → App registrations → Your app
2. Navigate to **Authentication**
3. Add Web redirect URI: `https://yourdomain.com/ptoffice-editor/`
4. Save changes

### 4. Test Configuration
1. Navigate to: `https://yourdomain.com/ptoffice-editor/test-config.html`
2. Verify all configuration values show as ✅ Valid
3. Test WordPress API connectivity
4. Fix any issues before proceeding

### 5. Test Application
1. Navigate to: `https://yourdomain.com/ptoffice-editor/`
2. Click "Sign in with Microsoft"
3. Complete Azure AD authentication
4. Verify you can see posts/pages
5. Test creating a new post
6. Test image upload functionality

## Post-Deployment Configuration

### SSL Certificate
- Ensure your site has a valid SSL certificate
- All URLs should use `https://` (required for Azure AD)

### File Permissions
```bash
# Recommended file permissions
find ptoffice-editor -type f -exec chmod 644 {} \;
find ptoffice-editor -type d -exec chmod 755 {} \;
```

### Web Server Configuration
Add these headers for security (optional but recommended):

**Apache (.htaccess):**
```apache
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>
```

**Nginx:**
```nginx
add_header X-Content-Type-Options nosniff;
add_header X-Frame-Options DENY;
add_header X-XSS-Protection "1; mode=block";
```

## Testing Checklist

### ✅ Authentication Testing
- [ ] Login redirects to Azure AD correctly
- [ ] Authentication completes and returns to app
- [ ] User name displays in header
- [ ] Logout works correctly

### ✅ Content Management Testing  
- [ ] Posts list loads correctly
- [ ] Pages list loads correctly
- [ ] Can create new post
- [ ] Can create new page
- [ ] Can edit existing content
- [ ] Can delete content
- [ ] Can toggle draft/published status

### ✅ Media Upload Testing
- [ ] Featured image upload works
- [ ] Content image insertion works
- [ ] Images appear in WordPress media library
- [ ] Images display correctly in content
- [ ] File size/type restrictions work

### ✅ UI/UX Testing
- [ ] Responsive design works on mobile
- [ ] Search functionality works
- [ ] Status filters work
- [ ] Keyboard shortcuts work
- [ ] Auto-save functionality works

## Troubleshooting Common Issues

### "Authentication Failed"
1. Verify Azure AD Client ID and Tenant ID
2. Check redirect URI is registered correctly
3. Ensure user has WordPress account

### "API Request Failed"  
1. Test WordPress REST API directly: `yoursite.com/wp-json/wp/v2/posts`
2. Check WordPress site is accessible
3. Verify user has appropriate WordPress role

### "Upload Failed"
1. Check WordPress media upload settings
2. Verify file size limits (PHP and WordPress)  
3. Test direct upload in WordPress admin

### Mixed Content Errors
1. Ensure all URLs use HTTPS
2. Check WordPress site URL settings
3. Verify SSL certificate is valid

### CORS Issues (Rare)
If you get CORS errors, add to WordPress functions.php:
```php
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        header('Access-Control-Allow-Origin: https://yourdomain.com');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
        header('Access-Control-Allow-Credentials: true');
        return $value;
    });
});
```

## Security Considerations

### File Security
- Never commit `config/config.js` with real credentials to version control
- Use environment-specific configurations
- Regularly rotate Azure AD client secrets if using them

### Access Control
- Only authorized users should have the application URL
- Consider IP restrictions if needed
- Monitor Azure AD sign-in logs

### Data Protection
- All communication is encrypted via HTTPS
- Authentication tokens are stored securely
- No sensitive data is logged to console in production

## Maintenance

### Regular Updates
- Monitor WordPress for REST API changes
- Update CDN resources (Quill.js, MSAL.js) periodically
- Review Azure AD app permissions regularly

### Monitoring
- Monitor Azure AD sign-in logs
- Check WordPress error logs
- Monitor application performance

### Backup
- Backup configuration files
- Document any customizations
- Keep deployment notes updated

## Support Resources

- **WordPress REST API**: https://developer.wordpress.org/rest-api/
- **Azure AD Documentation**: https://docs.microsoft.com/en-us/azure/active-directory/
- **MSAL.js Documentation**: https://docs.microsoft.com/en-us/azure/active-directory/develop/msal-js-initializing-client-applications










