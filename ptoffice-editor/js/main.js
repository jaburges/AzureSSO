// Main Application Manager

class MainApplication {
    constructor() {
        this.currentTab = 'posts';
        this.currentData = {
            posts: [],
            pages: []
        };
        this.filters = {
            posts: { search: '', status: '' },
            pages: { search: '', status: '' }
        };
        this.loading = {
            posts: false,
            pages: false
        };
    }

    async initialize() {
        console.log('Initializing PTA Office Content Editor');
        
        this.setupEventListeners();
        await this.loadInitialData();
        this.renderCurrentTab();
    }

    setupEventListeners() {
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tabName = e.target.dataset.tab;
                this.switchTab(tabName);
            });
        });

        // New item buttons
        document.getElementById('new-post-btn').addEventListener('click', () => {
            window.editorManager.openEditor(null, 'posts');
        });

        document.getElementById('new-page-btn').addEventListener('click', () => {
            window.editorManager.openEditor(null, 'pages');
        });

        // Search and filter inputs
        document.getElementById('posts-search').addEventListener('input', 
            this.debounce((e) => {
                this.filters.posts.search = e.target.value;
                this.filterAndRenderContent('posts');
            }, 300)
        );

        document.getElementById('posts-status-filter').addEventListener('change', (e) => {
            this.filters.posts.status = e.target.value;
            this.filterAndRenderContent('posts');
        });

        document.getElementById('pages-search').addEventListener('input', 
            this.debounce((e) => {
                this.filters.pages.search = e.target.value;
                this.filterAndRenderContent('pages');
            }, 300)
        );

        document.getElementById('pages-status-filter').addEventListener('change', (e) => {
            this.filters.pages.status = e.target.value;
            this.filterAndRenderContent('pages');
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                if (e.key === 'n') {
                    e.preventDefault();
                    if (this.currentTab === 'posts') {
                        window.editorManager.openEditor(null, 'posts');
                    } else {
                        window.editorManager.openEditor(null, 'pages');
                    }
                }
            }
            
            // Tab switching with keyboard
            if (e.altKey) {
                if (e.key === '1') {
                    e.preventDefault();
                    this.switchTab('posts');
                } else if (e.key === '2') {
                    e.preventDefault();
                    this.switchTab('pages');
                }
            }
        });

        // Refresh shortcut
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                this.refreshCurrentTab();
            }
        });
    }

    async loadInitialData() {
        await Promise.all([
            this.loadPosts(),
            this.loadPages()
        ]);
    }

    async loadPosts() {
        if (this.loading.posts) return;
        
        this.loading.posts = true;
        this.showLoadingState('posts');

        try {
            const posts = await window.wpAPI.getPosts();
            this.currentData.posts = posts;
            
            if (this.currentTab === 'posts') {
                this.renderPosts();
            }
        } catch (error) {
            console.error('Failed to load posts:', error);
            this.showErrorState('posts', 'Failed to load posts: ' + error.message);
        } finally {
            this.loading.posts = false;
        }
    }

    async loadPages() {
        if (this.loading.pages) return;
        
        this.loading.pages = true;
        this.showLoadingState('pages');

        try {
            const pages = await window.wpAPI.getPages();
            this.currentData.pages = pages;
            
            if (this.currentTab === 'pages') {
                this.renderPages();
            }
        } catch (error) {
            console.error('Failed to load pages:', error);
            this.showErrorState('pages', 'Failed to load pages: ' + error.message);
        } finally {
            this.loading.pages = false;
        }
    }

    switchTab(tabName) {
        if (this.currentTab === tabName) return;

        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });

        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.toggle('active', content.id === tabName + '-tab');
        });

        this.currentTab = tabName;
        this.renderCurrentTab();
    }

    async renderCurrentTab() {
        if (this.currentTab === 'posts') {
            if (this.currentData.posts.length === 0 && !this.loading.posts) {
                await this.loadPosts();
            } else {
                this.renderPosts();
            }
        } else {
            if (this.currentData.pages.length === 0 && !this.loading.pages) {
                await this.loadPages();
            } else {
                this.renderPages();
            }
        }
    }

    filterAndRenderContent(type) {
        if (type === 'posts') {
            this.renderPosts();
        } else {
            this.renderPages();
        }
    }

    renderPosts() {
        const container = document.getElementById('posts-list');
        const posts = this.getFilteredContent('posts');
        
        if (this.loading.posts) {
            return; // Loading state already shown
        }

        if (posts.length === 0) {
            this.showEmptyState('posts');
            return;
        }

        container.innerHTML = posts.map(post => this.renderContentItem(post, 'posts')).join('');
        this.attachContentItemListeners('posts');
    }

    renderPages() {
        const container = document.getElementById('pages-list');
        const pages = this.getFilteredContent('pages');
        
        if (this.loading.pages) {
            return; // Loading state already shown
        }

        if (pages.length === 0) {
            this.showEmptyState('pages');
            return;
        }

        container.innerHTML = pages.map(page => this.renderContentItem(page, 'pages')).join('');
        this.attachContentItemListeners('pages');
    }

    renderContentItem(item, type) {
        const title = item.title.rendered || 'Untitled';
        const excerpt = window.wpAPI.stripHtml(item.excerpt.rendered || '');
        const truncatedExcerpt = window.wpAPI.truncateText(excerpt, 120);
        const date = window.wpAPI.formatDate(item.date);
        const author = item._embedded && item._embedded.author ? item._embedded.author[0].name : 'Unknown';
        const featuredImage = window.wpAPI.getFeaturedImageUrl(item, 'thumbnail');

        return `
            <div class="content-item" data-id="${item.id}" data-type="${type}">
                <div class="content-item-icon ${type === 'posts' ? 'post' : 'page'}">
                    <i class="fas ${type === 'posts' ? 'fa-file-alt' : 'fa-file'}"></i>
                </div>
                
                ${featuredImage ? `
                    <div class="content-item-thumbnail">
                        <img src="${featuredImage}" alt="${title}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                    </div>
                ` : ''}
                
                <div class="content-item-info">
                    <div class="content-item-title" title="${title}">${title}</div>
                    ${truncatedExcerpt ? `<div class="content-item-excerpt">${truncatedExcerpt}</div>` : ''}
                    <div class="content-item-meta">
                        <span class="content-item-status ${item.status}">${item.status}</span>
                        <span class="content-item-date">${date}</span>
                        <span class="content-item-author">by ${author}</span>
                    </div>
                </div>
                
                <div class="content-item-actions">
                    <button class="action-btn edit-btn" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="action-btn preview-btn" title="Preview">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="action-btn status-btn" title="Toggle Status">
                        <i class="fas ${item.status === 'publish' ? 'fa-eye-slash' : 'fa-eye'}"></i>
                    </button>
                </div>
            </div>
        `;
    }

    attachContentItemListeners(type) {
        const container = document.getElementById(type + '-list');
        
        container.addEventListener('click', (e) => {
            const contentItem = e.target.closest('.content-item');
            if (!contentItem) return;

            const itemId = parseInt(contentItem.dataset.id);
            const itemType = contentItem.dataset.type;
            const item = this.currentData[itemType].find(i => i.id === itemId);
            
            if (!item) return;

            if (e.target.closest('.edit-btn') || contentItem === e.target || e.target.closest('.content-item-info')) {
                // Edit item
                window.editorManager.openEditor(item, itemType);
            } else if (e.target.closest('.preview-btn')) {
                // Preview item
                const previewUrl = window.wpAPI.getPreviewUrl(item);
                window.open(previewUrl, '_blank');
            } else if (e.target.closest('.status-btn')) {
                // Toggle status
                this.toggleItemStatus(item, itemType);
            }
        });
    }

    async toggleItemStatus(item, type) {
        const newStatus = item.status === 'publish' ? 'draft' : 'publish';
        const statusBtn = document.querySelector(`[data-id="${item.id}"] .status-btn`);
        const originalIcon = statusBtn.innerHTML;

        try {
            statusBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            const data = { status: newStatus };
            let updatedItem;
            
            if (type === 'posts') {
                updatedItem = await window.wpAPI.updatePost(item.id, data);
            } else {
                updatedItem = await window.wpAPI.updatePage(item.id, data);
            }

            // Update local data
            const itemIndex = this.currentData[type].findIndex(i => i.id === item.id);
            if (itemIndex !== -1) {
                this.currentData[type][itemIndex] = updatedItem;
            }

            // Re-render the current tab
            this.renderCurrentTab();
            
            this.showMessage(`${type.slice(0, -1)} ${newStatus === 'publish' ? 'published' : 'saved as draft'}!`, 'success');

        } catch (error) {
            console.error('Status toggle failed:', error);
            statusBtn.innerHTML = originalIcon;
            this.showMessage('Failed to update status: ' + error.message, 'error');
        }
    }

    getFilteredContent(type) {
        const items = this.currentData[type] || [];
        const filter = this.filters[type];

        let filtered = items;

        // Apply search filter
        if (filter.search) {
            const searchLower = filter.search.toLowerCase();
            filtered = filtered.filter(item => {
                const title = item.title.rendered || '';
                const content = window.wpAPI.stripHtml(item.content.rendered || '');
                const excerpt = window.wpAPI.stripHtml(item.excerpt.rendered || '');
                
                return title.toLowerCase().includes(searchLower) ||
                       content.toLowerCase().includes(searchLower) ||
                       excerpt.toLowerCase().includes(searchLower);
            });
        }

        // Apply status filter
        if (filter.status) {
            filtered = filtered.filter(item => item.status === filter.status);
        }

        return filtered;
    }

    showLoadingState(type) {
        const container = document.getElementById(type + '-list');
        container.innerHTML = `
            <div class="loading-state" style="text-align: center; padding: 4rem 2rem; color: #666;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem; color: #0078d4;"></i>
                <h3>Loading ${type}...</h3>
                <p>Please wait while we fetch your content.</p>
            </div>
        `;
    }

    showEmptyState(type) {
        const container = document.getElementById(type + '-list');
        const hasFilters = this.filters[type].search || this.filters[type].status;
        
        if (hasFilters) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No ${type} found</h3>
                    <p>No ${type} match your current search criteria.</p>
                    <button class="btn btn-secondary" onclick="mainApp.clearFilters('${type}')">Clear Filters</button>
                </div>
            `;
        } else {
            const itemType = type === 'posts' ? 'post' : 'page';
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas ${type === 'posts' ? 'fa-file-alt' : 'fa-file'}"></i>
                    <h3>No ${type} yet</h3>
                    <p>Get started by creating your first ${itemType}.</p>
                    <button class="btn btn-primary" onclick="${type === 'posts' ? 'window.editorManager.openEditor(null, \'posts\')' : 'window.editorManager.openEditor(null, \'pages\')'}">
                        <i class="fas fa-plus"></i>
                        Create ${itemType}
                    </button>
                </div>
            `;
        }
    }

    showErrorState(type, message) {
        const container = document.getElementById(type + '-list');
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i>
                <h3>Failed to load ${type}</h3>
                <p>${message}</p>
                <button class="btn btn-primary" onclick="mainApp.refresh${type.charAt(0).toUpperCase() + type.slice(1)}()">
                    <i class="fas fa-refresh"></i>
                    Try Again
                </button>
            </div>
        `;
    }

    clearFilters(type) {
        this.filters[type] = { search: '', status: '' };
        
        // Clear UI inputs
        document.getElementById(type + '-search').value = '';
        document.getElementById(type + '-status-filter').value = '';
        
        // Re-render
        this.filterAndRenderContent(type);
    }

    async refreshCurrentTab() {
        if (this.currentTab === 'posts') {
            await this.refreshPosts();
        } else {
            await this.refreshPages();
        }
    }

    async refreshPosts() {
        window.wpAPI.clearCache();
        await this.loadPosts();
    }

    async refreshPages() {
        window.wpAPI.clearCache();
        await this.loadPages();
    }

    // Utility methods
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    showMessage(message, type = 'info') {
        // Reuse the message system from editor
        if (window.editorManager) {
            if (type === 'success') {
                window.editorManager.showSuccessMessage(message);
            } else if (type === 'error') {
                window.editorManager.showErrorMessage(message);
            } else {
                window.editorManager.showMessage(message, type);
            }
        }
    }
}

// Initialize application when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Create main app instance
    window.mainApp = new MainApplication();
    
    // The auth manager will call mainApp.initialize() when login is successful
});

// Export for use in other modules
window.MainApplication = MainApplication;





