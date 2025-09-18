# PTA Office Content Editor

A simplified web application for creating and editing WordPress posts and pages without the complexity of the full WordPress admin interface. Built specifically for content creators who need a clean, intuitive interface to manage their content.

## Features

### 📝 Content Management
- **Posts & Pages**: Create, edit, update, and delete WordPress posts and pages
- **Rich Text Editor**: Powered by Quill.js with formatting tools
- **Draft/Publish Toggle**: Easily switch between draft and published status
- **Search & Filter**: Find content quickly with search and status filters

### 🖼️ Image Management
- **Featured Images**: Upload and set featured images for posts/pages
- **Content Images**: Insert images directly into content with drag-and-drop
- **WordPress Media Library**: Images are saved to your WordPress media repository
- **File Validation**: Automatic validation for file types and sizes

### 🔐 Azure AD Authentication
- **Single Sign-On**: Uses your existing Azure AD credentials
- **Same Authentication**: Uses the same Azure AD app as your WordPress site
- **Secure Access**: Token-based authentication with automatic refresh

### 💡 User-Friendly Features
- **Auto-Save**: Automatically saves drafts while you work
- **Keyboard Shortcuts**: Ctrl+S to save, Ctrl+N for new, Alt+1/2 for tabs
- **Preview**: Open content in new tab to see how it looks live
- **Mobile Responsive**: Works on desktop, tablet, and mobile devices

## Setup Instructions

### Prerequisites

1. **WordPress Site**: Your WordPress site must have:
   - WordPress REST API enabled (standard in WordPress 4.7+)
   - Azure AD SSO plugin installed and configured
   - Proper user permissions for content management

2. **Azure AD App Registration**: You'll use the same Azure AD app from your WordPress Azure SSO plugin

### Installation

1. **Upload Files**: Upload the `ptoffice-editor` folder to your web server
2. **Configure Settings**: Edit `config/config.js` with your settings
3. **Set Permissions**: Ensure the web server can read all files

### Configuration

Edit the `config/config.js` file with your specific settings:

```javascript
const APP_CONFIG = {
    azure: {
        clientId: 'your-azure-client-id',      // Same as WordPress plugin
        authority: 'https://login.microsoftonline.com/your-tenant-id',
        redirectUri: 'https://yourdomain.com/ptoffice-editor/', // Update this URL
        scopes: ['openid', 'profile', 'email', 'User.Read']
    },
    wordpress: {
        baseUrl: 'https://wilderptsa.net',     // Your WordPress site URL
        apiBase: 'https://wilderptsa.net/wp-json/wp/v2',
        mediaApi: 'https://wilderptsa.net/wp-json/wp/v2/media'
    }
};
```

### Azure AD App Configuration

In your Azure AD app registration, add the redirect URI:
- Go to Azure Portal → App registrations → Your app
- Navigate to Authentication
- Add Web redirect URI: `https://yourdomain.com/ptoffice-editor/`
- Save changes

### WordPress Permissions

Ensure your Azure AD users have appropriate WordPress roles:
- **Editor** or **Administrator** roles can create/edit/delete posts and pages
- **Author** role can create/edit their own posts
- **Contributor** role can create/edit drafts

## File Structure

```
ptoffice-editor/
├── index.html              # Main application file
├── config/
│   └── config.js           # Configuration settings
├── css/
│   ├── main.css            # Main application styles
│   └── editor.css          # Editor modal styles
├── js/
│   ├── auth.js             # Azure AD authentication
│   ├── wordpress-api.js    # WordPress REST API client
│   ├── editor.js           # Post/page editor functionality
│   └── main.js             # Main application logic
├── assets/                 # Static assets (images, icons)
└── README.md              # This file
```

## Usage

### Getting Started
1. Navigate to your editor URL (e.g., `https://yourdomain.com/ptoffice-editor/`)
2. Click "Sign in with Microsoft" and use your Azure AD credentials
3. Once logged in, you'll see the main interface with Posts and Pages tabs

### Creating Content
1. Click "New Post" or "New Page"
2. Enter a title and content using the rich text editor
3. Optionally upload a featured image
4. Add an excerpt if desired
5. Choose status (Draft or Published)
6. Click "Save" to create the content

### Editing Content
1. Click on any post or page in the list to edit it
2. Make your changes in the editor
3. The app will auto-save drafts every 30 seconds
4. Click "Save" to save changes immediately
5. Use "Preview" to see how it looks on your website

### Managing Images
- **Featured Image**: Click "Upload Featured Image" in the editor
- **Content Images**: Click "Insert Image" button or drag-and-drop into the editor
- **Supported Formats**: JPEG, PNG, GIF, WebP (max 5MB)

### Keyboard Shortcuts
- `Ctrl+S` (Cmd+S): Save current item
- `Ctrl+N` (Cmd+N): Create new item
- `Ctrl+Enter` (Cmd+Enter): Preview current item
- `Alt+1`: Switch to Posts tab
- `Alt+2`: Switch to Pages tab
- `Escape`: Close editor modal

## Troubleshooting

### Authentication Issues
- Verify Azure AD client ID and tenant ID are correct
- Check that redirect URI is registered in Azure AD
- Ensure user has WordPress account with appropriate permissions

### API Connection Issues
- Verify WordPress REST API is accessible
- Check WordPress URL configuration
- Ensure CORS is properly configured if needed

### Upload Issues
- Check file size limits (default 5MB)
- Verify supported file types (JPEG, PNG, GIF, WebP)
- Ensure WordPress media upload permissions

### Common Errors

**"Authentication failed"**
- Check Azure AD configuration
- Verify user permissions in WordPress

**"API Request failed"**
- Check WordPress REST API availability
- Verify authentication tokens
- Check network connectivity

**"Upload failed"**
- Check file size and type restrictions
- Verify WordPress media permissions
- Check server upload limits

## Development

### Local Development
1. Serve files from a local web server (not file://)
2. Update config.js with localhost redirect URI
3. Register localhost redirect in Azure AD app

### Customization
- Modify CSS files for different styling
- Update config.js for different default behaviors
- Extend JavaScript files for additional functionality

## Security Considerations

- Files are served over HTTPS in production
- Authentication tokens are stored securely
- WordPress REST API calls are authenticated
- File uploads are validated for type and size
- User permissions are enforced through WordPress roles

## Browser Support

- Chrome 80+ (recommended)
- Firefox 75+
- Safari 13+
- Edge 80+

## License

This project is created for the PTA Office and is provided as-is for internal use.

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Verify configuration settings
3. Check browser console for error messages
4. Test WordPress REST API accessibility directly

