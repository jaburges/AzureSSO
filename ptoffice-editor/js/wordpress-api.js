// WordPress REST API Manager

class WordPressAPI {
    constructor() {
        this.baseURL = APP_CONFIG.wordpress.apiBase;
        this.mediaURL = APP_CONFIG.wordpress.mediaApi;
        this.cache = new Map();
        this.cacheTimeout = 5 * 60 * 1000; // 5 minutes
    }

    async makeRequest(url, options = {}) {
        try {
            // Get authentication token
            const token = await window.authManager.getAccessToken();
            
            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            const mergedOptions = {
                ...defaultOptions,
                ...options,
                headers: {
                    ...defaultOptions.headers,
                    ...options.headers
                }
            };

            const response = await fetch(url, mergedOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('API Request failed:', error);
            throw error;
        }
    }

    // Cache management
    getCacheKey(url, params = {}) {
        const paramString = Object.keys(params).sort().map(key => `${key}=${params[key]}`).join('&');
        return `${url}?${paramString}`;
    }

    getFromCache(key) {
        const cached = this.cache.get(key);
        if (cached && (Date.now() - cached.timestamp) < this.cacheTimeout) {
            return cached.data;
        }
        this.cache.delete(key);
        return null;
    }

    setCache(key, data) {
        this.cache.set(key, {
            data,
            timestamp: Date.now()
        });
    }

    clearCache() {
        this.cache.clear();
    }

    // Posts API
    async getPosts(params = {}) {
        const defaultParams = {
            per_page: APP_CONFIG.app.postsPerPage,
            _embed: true, // Include featured media and author info
            status: 'publish,draft,private'
        };

        const queryParams = { ...defaultParams, ...params };
        const url = `${this.baseURL}/posts`;
        const cacheKey = this.getCacheKey(url, queryParams);
        
        // Check cache first
        const cached = this.getFromCache(cacheKey);
        if (cached) {
            return cached;
        }

        const queryString = new URLSearchParams(queryParams).toString();
        const fullUrl = `${url}?${queryString}`;
        
        const posts = await this.makeRequest(fullUrl);
        
        // Cache the results
        this.setCache(cacheKey, posts);
        
        return posts;
    }

    async getPost(id) {
        const url = `${this.baseURL}/posts/${id}`;
        const cacheKey = this.getCacheKey(url, { _embed: true });
        
        const cached = this.getFromCache(cacheKey);
        if (cached) {
            return cached;
        }

        const post = await this.makeRequest(`${url}?_embed=true`);
        this.setCache(cacheKey, post);
        
        return post;
    }

    async createPost(postData) {
        const url = `${this.baseURL}/posts`;
        const post = await this.makeRequest(url, {
            method: 'POST',
            body: JSON.stringify(postData)
        });
        
        this.clearCache(); // Clear cache to ensure fresh data
        return post;
    }

    async updatePost(id, postData) {
        const url = `${this.baseURL}/posts/${id}`;
        const post = await this.makeRequest(url, {
            method: 'POST', // WordPress REST API uses POST for updates
            body: JSON.stringify(postData)
        });
        
        this.clearCache();
        return post;
    }

    async deletePost(id) {
        const url = `${this.baseURL}/posts/${id}`;
        const result = await this.makeRequest(url, {
            method: 'DELETE',
            body: JSON.stringify({ force: true }) // Permanently delete
        });
        
        this.clearCache();
        return result;
    }

    // Pages API
    async getPages(params = {}) {
        const defaultParams = {
            per_page: APP_CONFIG.app.postsPerPage,
            _embed: true,
            status: 'publish,draft,private'
        };

        const queryParams = { ...defaultParams, ...params };
        const url = `${this.baseURL}/pages`;
        const cacheKey = this.getCacheKey(url, queryParams);
        
        const cached = this.getFromCache(cacheKey);
        if (cached) {
            return cached;
        }

        const queryString = new URLSearchParams(queryParams).toString();
        const fullUrl = `${url}?${queryString}`;
        
        const pages = await this.makeRequest(fullUrl);
        this.setCache(cacheKey, pages);
        
        return pages;
    }

    async getPage(id) {
        const url = `${this.baseURL}/pages/${id}`;
        const cacheKey = this.getCacheKey(url, { _embed: true });
        
        const cached = this.getFromCache(cacheKey);
        if (cached) {
            return cached;
        }

        const page = await this.makeRequest(`${url}?_embed=true`);
        this.setCache(cacheKey, page);
        
        return page;
    }

    async createPage(pageData) {
        const url = `${this.baseURL}/pages`;
        const page = await this.makeRequest(url, {
            method: 'POST',
            body: JSON.stringify(pageData)
        });
        
        this.clearCache();
        return page;
    }

    async updatePage(id, pageData) {
        const url = `${this.baseURL}/pages/${id}`;
        const page = await this.makeRequest(url, {
            method: 'POST',
            body: JSON.stringify(pageData)
        });
        
        this.clearCache();
        return page;
    }

    async deletePage(id) {
        const url = `${this.baseURL}/pages/${id}`;
        const result = await this.makeRequest(url, {
            method: 'DELETE',
            body: JSON.stringify({ force: true })
        });
        
        this.clearCache();
        return result;
    }

    // Media API
    async uploadMedia(file, filename = null) {
        try {
            const token = await window.authManager.getAccessToken();
            
            // Validate file
            if (file.size > APP_CONFIG.app.maxImageSize) {
                throw new Error(`File too large. Maximum size is ${APP_CONFIG.app.maxImageSize / 1024 / 1024}MB`);
            }
            
            if (!APP_CONFIG.app.allowedImageTypes.includes(file.type)) {
                throw new Error('Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.');
            }

            // Create form data
            const formData = new FormData();
            formData.append('file', file);
            
            if (filename) {
                formData.append('title', filename);
            }

            const response = await fetch(this.mediaURL, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Upload failed: ${response.status} ${response.statusText} - ${errorText}`);
            }

            const media = await response.json();
            this.clearCache(); // Clear cache to ensure fresh media data
            
            return media;
        } catch (error) {
            console.error('Media upload failed:', error);
            throw error;
        }
    }

    async getMedia(id) {
        const url = `${this.mediaURL}/${id}`;
        const cacheKey = this.getCacheKey(url);
        
        const cached = this.getFromCache(cacheKey);
        if (cached) {
            return cached;
        }

        const media = await this.makeRequest(url);
        this.setCache(cacheKey, media);
        
        return media;
    }

    async deleteMedia(id) {
        const url = `${this.mediaURL}/${id}`;
        const result = await this.makeRequest(url, {
            method: 'DELETE',
            body: JSON.stringify({ force: true })
        });
        
        this.clearCache();
        return result;
    }

    // Utility methods
    getPreviewUrl(item) {
        if (!item) return '#';
        
        // Try to get the preview link
        if (item.link) {
            return item.link;
        }
        
        // Fallback to constructing URL
        const baseUrl = APP_CONFIG.wordpress.baseUrl;
        const slug = item.slug || item.id;
        const type = item.type || 'post';
        
        if (type === 'page') {
            return `${baseUrl}/${slug}/`;
        } else {
            return `${baseUrl}/${slug}/`;
        }
    }

    getFeaturedImageUrl(item, size = 'medium') {
        if (!item || !item._embedded) return null;
        
        const featuredMedia = item._embedded['wp:featuredmedia'];
        if (!featuredMedia || !featuredMedia[0]) return null;
        
        const media = featuredMedia[0];
        
        // Try to get the specific size
        if (media.media_details && media.media_details.sizes && media.media_details.sizes[size]) {
            return media.media_details.sizes[size].source_url;
        }
        
        // Fallback to source URL
        return media.source_url;
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    stripHtml(html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        return doc.body.textContent || "";
    }

    truncateText(text, maxLength = 100) {
        if (text.length <= maxLength) return text;
        return text.substr(0, maxLength) + '...';
    }

    // Search functionality
    async searchContent(query, type = 'posts') {
        const params = {
            search: query,
            per_page: 50
        };

        if (type === 'posts') {
            return await this.getPosts(params);
        } else {
            return await this.getPages(params);
        }
    }

    // Batch operations
    async batchUpdateStatus(ids, status, type = 'posts') {
        const promises = ids.map(id => {
            const data = { status };
            if (type === 'posts') {
                return this.updatePost(id, data);
            } else {
                return this.updatePage(id, data);
            }
        });

        return await Promise.all(promises);
    }
}

// Initialize WordPress API when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.wpAPI = new WordPressAPI();
});

// Export for use in other modules
window.WordPressAPI = WordPressAPI;





