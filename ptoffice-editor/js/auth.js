// Authentication Manager using Microsoft Authentication Library (MSAL)

class AuthManager {
    constructor() {
        this.msalInstance = null;
        this.currentUser = null;
        this.accessToken = null;
        this.initializeMSAL();
    }

    initializeMSAL() {
        const msalConfig = {
            auth: {
                clientId: APP_CONFIG.azure.clientId,
                authority: APP_CONFIG.azure.authority,
                redirectUri: APP_CONFIG.azure.redirectUri,
            },
            cache: {
                cacheLocation: "localStorage", // Store tokens in localStorage
                storeAuthStateInCookie: false, // Set to true for IE 11
            }
        };

        this.msalInstance = new msal.PublicClientApplication(msalConfig);

        // Handle redirect response
        this.handleRedirectResponse();
    }

    async handleRedirectResponse() {
        try {
            const response = await this.msalInstance.handleRedirectResponse();
            if (response && response.account) {
                this.currentUser = response.account;
                this.accessToken = response.accessToken;
                this.onLoginSuccess();
            } else {
                // Check if user is already logged in
                const accounts = this.msalInstance.getAllAccounts();
                if (accounts.length > 0) {
                    this.currentUser = accounts[0];
                    await this.acquireTokenSilent();
                    this.onLoginSuccess();
                } else {
                    this.onLoginRequired();
                }
            }
        } catch (error) {
            console.error('Error handling redirect response:', error);
            this.onLoginError(error);
        }
    }

    async login() {
        const loginRequest = {
            scopes: APP_CONFIG.azure.scopes,
            prompt: 'select_account'
        };

        try {
            showLoading('Signing in...');
            await this.msalInstance.loginRedirect(loginRequest);
        } catch (error) {
            console.error('Login error:', error);
            hideLoading();
            this.onLoginError(error);
        }
    }

    async acquireTokenSilent() {
        const silentRequest = {
            scopes: APP_CONFIG.azure.scopes,
            account: this.currentUser
        };

        try {
            const response = await this.msalInstance.acquireTokenSilent(silentRequest);
            this.accessToken = response.accessToken;
            return this.accessToken;
        } catch (error) {
            console.warn('Silent token acquisition failed:', error);
            
            // If silent acquisition fails, try interactive
            if (error.name === 'InteractionRequiredAuthError') {
                return await this.acquireTokenInteractive();
            }
            throw error;
        }
    }

    async acquireTokenInteractive() {
        const interactiveRequest = {
            scopes: APP_CONFIG.azure.scopes,
            account: this.currentUser
        };

        try {
            const response = await this.msalInstance.acquireTokenPopup(interactiveRequest);
            this.accessToken = response.accessToken;
            return this.accessToken;
        } catch (error) {
            console.error('Interactive token acquisition failed:', error);
            throw error;
        }
    }

    async getAccessToken() {
        if (!this.accessToken) {
            return await this.acquireTokenSilent();
        }
        
        // Check if token is expired (basic check - tokens are JWTs)
        try {
            const tokenPayload = this.parseJWT(this.accessToken);
            const now = Math.floor(Date.now() / 1000);
            
            // Refresh token if it expires in the next 5 minutes
            if (tokenPayload.exp && (tokenPayload.exp - now) < 300) {
                return await this.acquireTokenSilent();
            }
        } catch (error) {
            console.warn('Error checking token expiration:', error);
        }
        
        return this.accessToken;
    }

    parseJWT(token) {
        try {
            const base64Url = token.split('.')[1];
            const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
            const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
            return JSON.parse(jsonPayload);
        } catch (error) {
            console.error('Error parsing JWT:', error);
            return {};
        }
    }

    async logout() {
        try {
            showLoading('Signing out...');
            
            const logoutRequest = {
                account: this.currentUser,
                postLogoutRedirectUri: APP_CONFIG.azure.redirectUri
            };
            
            await this.msalInstance.logoutRedirect(logoutRequest);
        } catch (error) {
            console.error('Logout error:', error);
            hideLoading();
        }
    }

    getCurrentUser() {
        return this.currentUser;
    }

    isAuthenticated() {
        return this.currentUser !== null;
    }

    getUserDisplayName() {
        if (!this.currentUser) return '';
        return this.currentUser.name || this.currentUser.username || 'User';
    }

    getUserEmail() {
        if (!this.currentUser) return '';
        return this.currentUser.username || this.currentUser.localAccountId || '';
    }

    onLoginSuccess() {
        console.log('Login successful:', this.currentUser);
        hideLoading();
        
        // Update UI
        document.getElementById('login-screen').style.display = 'none';
        document.getElementById('main-app').style.display = 'flex';
        document.getElementById('user-name').textContent = this.getUserDisplayName();
        
        // Initialize the main application
        if (window.mainApp) {
            window.mainApp.initialize();
        }
    }

    onLoginRequired() {
        console.log('Login required');
        hideLoading();
        
        // Show login screen
        document.getElementById('login-screen').style.display = 'flex';
        document.getElementById('main-app').style.display = 'none';
    }

    onLoginError(error) {
        console.error('Authentication error:', error);
        hideLoading();
        
        // Show error message
        this.showErrorMessage('Authentication failed. Please try again.');
        this.onLoginRequired();
    }

    showErrorMessage(message) {
        // Create a simple error message display
        const errorDiv = document.createElement('div');
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #dc3545;
            color: white;
            padding: 1rem;
            border-radius: 4px;
            z-index: 10001;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        `;
        errorDiv.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-exclamation-triangle"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="background: none; border: none; color: white; font-size: 1.2rem; cursor: pointer; margin-left: auto;">
                    &times;
                </button>
            </div>
        `;
        document.body.appendChild(errorDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentElement) {
                errorDiv.remove();
            }
        }, 5000);
    }
}

// Helper functions for loading states
function showLoading(message = 'Loading...') {
    const overlay = document.getElementById('loading-overlay');
    const spinner = overlay.querySelector('.loading-spinner p');
    if (spinner) {
        spinner.textContent = message;
    }
    overlay.style.display = 'flex';
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    overlay.style.display = 'none';
}

// Initialize authentication when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize auth manager
    window.authManager = new AuthManager();
    
    // Set up event listeners
    const loginButton = document.getElementById('login-button');
    const logoutButton = document.getElementById('logout-button');
    
    if (loginButton) {
        loginButton.addEventListener('click', () => {
            window.authManager.login();
        });
    }
    
    if (logoutButton) {
        logoutButton.addEventListener('click', () => {
            window.authManager.logout();
        });
    }
});

// Export for use in other modules
window.AuthManager = AuthManager;




















