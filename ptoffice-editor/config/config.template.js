// Configuration Template for PTA Office Content Editor
// Copy this file to config.js and update with your actual values

const APP_CONFIG = {
    // Azure AD Configuration
    // Get these values from your Azure AD app registration (same as WordPress Azure SSO plugin)
    azure: {
        clientId: 'YOUR_AZURE_CLIENT_ID',           // Azure AD Application (client) ID
        authority: 'https://login.microsoftonline.com/YOUR_TENANT_ID', // Replace YOUR_TENANT_ID
        redirectUri: 'https://yourdomain.com/ptoffice-editor/', // Update to match your actual domain
        scopes: ['openid', 'profile', 'email', 'User.Read']
    },
    
    // WordPress Site Configuration
    wordpress: {
        baseUrl: 'https://wilderptsa.net',          // Your WordPress site URL (no trailing slash)
        apiBase: 'https://wilderptsa.net/wp-json/wp/v2', // WordPress REST API endpoint
        mediaApi: 'https://wilderptsa.net/wp-json/wp/v2/media' // Media API endpoint
    },
    
    // Application Settings (you can customize these)
    app: {
        name: 'PTA Office Content Editor',
        version: '1.0.0',
        postsPerPage: 20,                           // How many posts/pages to load at once
        maxImageSize: 5 * 1024 * 1024,            // Maximum image upload size (5MB)
        allowedImageTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
    },
    
    // UI Settings
    ui: {
        theme: 'light',                             // Theme (currently only 'light' supported)
        showAdvancedOptions: false,                 // Show advanced editing options
        autoSave: true,                             // Enable auto-save functionality
        autoSaveInterval: 30000                     // Auto-save interval in milliseconds (30 seconds)
    }
};

// Environment-specific configuration
// This allows for different settings in development vs production
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    // Development settings - update these for local testing
    APP_CONFIG.azure.redirectUri = 'http://localhost:8080/ptoffice-editor/'; // Update port as needed
    APP_CONFIG.ui.showAdvancedOptions = true; // Show more options in development
}

// Helper functions (don't modify these)
function getWordPressUrl(path = '') {
    return APP_CONFIG.wordpress.baseUrl + (path.startsWith('/') ? path : '/' + path);
}

function getApiUrl(endpoint = '') {
    return APP_CONFIG.wordpress.apiBase + (endpoint.startsWith('/') ? endpoint : '/' + endpoint);
}

function getMediaApiUrl(endpoint = '') {
    return APP_CONFIG.wordpress.mediaApi + (endpoint.startsWith('/') ? endpoint : '/' + endpoint);
}

// Export configuration (don't modify these)
window.APP_CONFIG = APP_CONFIG;
window.getWordPressUrl = getWordPressUrl;
window.getApiUrl = getApiUrl;
window.getMediaApiUrl = getMediaApiUrl;





