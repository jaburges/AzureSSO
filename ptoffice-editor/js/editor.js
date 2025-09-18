// Post/Page Editor Manager

class EditorManager {
    constructor() {
        this.currentItem = null;
        this.currentItemType = null; // 'posts' or 'pages'
        this.quillEditor = null;
        this.isDirty = false;
        this.autoSaveTimer = null;
        
        this.initializeEditor();
        this.setupEventListeners();
    }

    initializeEditor() {
        // Initialize Quill rich text editor
        const toolbarOptions = [
            [{ 'header': [1, 2, 3, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            [{ 'indent': '-1'}, { 'indent': '+1' }],
            [{ 'align': [] }],
            ['link', 'blockquote', 'code-block'],
            ['clean']
        ];

        this.quillEditor = new Quill('#content-editor', {
            theme: 'snow',
            modules: {
                toolbar: toolbarOptions
            },
            placeholder: 'Start writing your content...'
        });

        // Handle editor changes for dirty state and auto-save
        this.quillEditor.on('text-change', () => {
            this.markDirty();
            this.scheduleAutoSave();
        });
    }

    setupEventListeners() {
        // Modal controls
        document.getElementById('close-editor').addEventListener('click', () => {
            this.closeEditor();
        });

        document.getElementById('cancel-edit-btn').addEventListener('click', () => {
            this.closeEditor();
        });

        // Form controls
        document.getElementById('save-post-btn').addEventListener('click', () => {
            this.saveItem();
        });

        document.getElementById('delete-post-btn').addEventListener('click', () => {
            this.confirmDeleteItem();
        });

        document.getElementById('preview-btn').addEventListener('click', () => {
            this.previewItem();
        });

        // Featured image upload
        document.getElementById('upload-featured-image').addEventListener('click', () => {
            document.getElementById('featured-image-upload').click();
        });

        document.getElementById('featured-image-upload').addEventListener('change', (e) => {
            if (e.target.files[0]) {
                this.uploadFeaturedImage(e.target.files[0]);
            }
        });

        document.getElementById('remove-featured-image').addEventListener('click', () => {
            this.removeFeaturedImage();
        });

        // Content image insertion
        document.getElementById('insert-image-btn').addEventListener('click', () => {
            this.openImageUploadModal();
        });

        // Image upload modal
        document.getElementById('close-image-upload').addEventListener('click', () => {
            this.closeImageUploadModal();
        });

        document.getElementById('browse-images-btn').addEventListener('click', () => {
            document.getElementById('image-file-input').click();
        });

        document.getElementById('image-file-input').addEventListener('change', (e) => {
            if (e.target.files[0]) {
                this.uploadContentImage(e.target.files[0]);
            }
        });

        // Form change detection
        const formInputs = ['post-title', 'post-status', 'post-excerpt'];
        formInputs.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('input', () => {
                    this.markDirty();
                    this.scheduleAutoSave();
                });
            }
        });

        // Drag and drop for images
        this.setupImageDragDrop();

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                if (e.key === 's') {
                    e.preventDefault();
                    this.saveItem();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    this.previewItem();
                }
            }
            
            if (e.key === 'Escape') {
                this.closeEditor();
            }
        });

        // Warn before closing if unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (this.isDirty && document.getElementById('editor-modal').classList.contains('active')) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
    }

    setupImageDragDrop() {
        const dropArea = document.getElementById('image-drop-area');
        if (!dropArea) return;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => {
                dropArea.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => {
                dropArea.classList.remove('dragover');
            });
        });

        dropArea.addEventListener('drop', (e) => {
            const files = Array.from(e.dataTransfer.files);
            const imageFile = files.find(file => file.type.startsWith('image/'));
            
            if (imageFile) {
                this.uploadContentImage(imageFile);
            }
        });

        // Make drop area clickable
        dropArea.addEventListener('click', () => {
            document.getElementById('image-file-input').click();
        });
    }

    // Item management
    async openEditor(item = null, type = 'posts') {
        this.currentItem = item;
        this.currentItemType = type;
        
        // Update modal title
        const title = document.getElementById('editor-title');
        if (item) {
            title.textContent = `Edit ${type === 'posts' ? 'Post' : 'Page'}`;
            document.getElementById('delete-post-btn').style.display = 'block';
        } else {
            title.textContent = `New ${type === 'posts' ? 'Post' : 'Page'}`;
            document.getElementById('delete-post-btn').style.display = 'none';
        }

        // Populate form
        if (item) {
            await this.populateForm(item);
        } else {
            this.resetForm();
        }

        // Show modal
        document.getElementById('editor-modal').classList.add('active');
        document.body.style.overflow = 'hidden';

        // Focus on title
        document.getElementById('post-title').focus();
        
        this.isDirty = false;
    }

    closeEditor() {
        if (this.isDirty) {
            if (!confirm('You have unsaved changes. Are you sure you want to close?')) {
                return;
            }
        }

        // Clear auto-save timer
        if (this.autoSaveTimer) {
            clearTimeout(this.autoSaveTimer);
        }

        // Hide modal
        document.getElementById('editor-modal').classList.remove('active');
        document.body.style.overflow = '';

        // Reset form
        this.resetForm();
        this.currentItem = null;
        this.currentItemType = null;
        this.isDirty = false;
    }

    async populateForm(item) {
        // Basic fields
        document.getElementById('post-title').value = item.title.rendered || '';
        document.getElementById('post-status').value = item.status || 'draft';
        document.getElementById('post-excerpt').value = item.excerpt.rendered || '';
        document.getElementById('post-id').value = item.id || '';

        // Content
        const content = item.content.rendered || '';
        this.quillEditor.root.innerHTML = content;

        // Featured image
        if (item.featured_media && item.featured_media > 0) {
            const imageUrl = window.wpAPI.getFeaturedImageUrl(item, 'medium');
            if (imageUrl) {
                this.displayFeaturedImage(imageUrl, item.featured_media);
            }
        } else {
            this.removeFeaturedImage();
        }
    }

    resetForm() {
        document.getElementById('post-title').value = '';
        document.getElementById('post-status').value = 'draft';
        document.getElementById('post-excerpt').value = '';
        document.getElementById('post-id').value = '';
        document.getElementById('featured-image-id').value = '';
        
        this.quillEditor.root.innerHTML = '';
        this.removeFeaturedImage();
    }

    // Save/Update operations
    async saveItem() {
        const saveBtn = document.getElementById('save-post-btn');
        const originalText = saveBtn.innerHTML;
        
        try {
            saveBtn.classList.add('loading');
            saveBtn.textContent = 'Saving...';

            // Collect form data
            const data = this.collectFormData();
            
            // Validate required fields
            if (!data.title) {
                throw new Error('Title is required');
            }

            let result;
            if (this.currentItem && this.currentItem.id) {
                // Update existing item
                if (this.currentItemType === 'posts') {
                    result = await window.wpAPI.updatePost(this.currentItem.id, data);
                } else {
                    result = await window.wpAPI.updatePage(this.currentItem.id, data);
                }
                this.showSuccessMessage('Updated successfully!');
            } else {
                // Create new item
                if (this.currentItemType === 'posts') {
                    result = await window.wpAPI.createPost(data);
                } else {
                    result = await window.wpAPI.createPage(data);
                }
                this.currentItem = result;
                document.getElementById('post-id').value = result.id;
                document.getElementById('delete-post-btn').style.display = 'block';
                this.showSuccessMessage('Created successfully!');
            }

            this.isDirty = false;
            
            // Refresh the main list
            if (window.mainApp) {
                await window.mainApp.refreshCurrentTab();
            }

        } catch (error) {
            console.error('Save failed:', error);
            this.showErrorMessage('Save failed: ' + error.message);
        } finally {
            saveBtn.classList.remove('loading');
            saveBtn.innerHTML = originalText;
        }
    }

    collectFormData() {
        const title = document.getElementById('post-title').value.trim();
        const status = document.getElementById('post-status').value;
        const excerpt = document.getElementById('post-excerpt').value.trim();
        const content = this.quillEditor.root.innerHTML;
        const featuredMediaId = document.getElementById('featured-image-id').value;

        const data = {
            title,
            status,
            excerpt,
            content
        };

        if (featuredMediaId) {
            data.featured_media = parseInt(featuredMediaId);
        } else if (this.currentItem && this.currentItem.featured_media) {
            data.featured_media = 0; // Remove featured image
        }

        return data;
    }

    async confirmDeleteItem() {
        if (!this.currentItem || !this.currentItem.id) return;

        const itemType = this.currentItemType === 'posts' ? 'post' : 'page';
        const itemTitle = this.currentItem.title.rendered || 'Untitled';
        
        if (!confirm(`Are you sure you want to permanently delete this ${itemType}?\n\n"${itemTitle}"`)) {
            return;
        }

        const deleteBtn = document.getElementById('delete-post-btn');
        const originalText = deleteBtn.innerHTML;

        try {
            deleteBtn.classList.add('loading');
            deleteBtn.textContent = 'Deleting...';

            if (this.currentItemType === 'posts') {
                await window.wpAPI.deletePost(this.currentItem.id);
            } else {
                await window.wpAPI.deletePage(this.currentItem.id);
            }

            this.showSuccessMessage('Deleted successfully!');
            this.closeEditor();
            
            // Refresh the main list
            if (window.mainApp) {
                await window.mainApp.refreshCurrentTab();
            }

        } catch (error) {
            console.error('Delete failed:', error);
            this.showErrorMessage('Delete failed: ' + error.message);
        } finally {
            deleteBtn.classList.remove('loading');
            deleteBtn.innerHTML = originalText;
        }
    }

    previewItem() {
        if (this.currentItem && this.currentItem.id) {
            const previewUrl = window.wpAPI.getPreviewUrl(this.currentItem);
            window.open(previewUrl, '_blank');
        } else {
            this.showErrorMessage('Please save the item first to preview it');
        }
    }

    // Featured image management
    async uploadFeaturedImage(file) {
        const uploadBtn = document.getElementById('upload-featured-image');
        const originalText = uploadBtn.innerHTML;

        try {
            uploadBtn.classList.add('loading');
            uploadBtn.textContent = 'Uploading...';

            const media = await window.wpAPI.uploadMedia(file);
            this.displayFeaturedImage(media.source_url, media.id);
            document.getElementById('featured-image-id').value = media.id;
            
            this.markDirty();
            this.showSuccessMessage('Featured image uploaded!');

        } catch (error) {
            console.error('Featured image upload failed:', error);
            this.showErrorMessage('Upload failed: ' + error.message);
        } finally {
            uploadBtn.classList.remove('loading');
            uploadBtn.innerHTML = originalText;
        }
    }

    displayFeaturedImage(imageUrl, mediaId) {
        const preview = document.getElementById('featured-image-preview');
        const img = document.getElementById('featured-image-img');
        
        img.src = imageUrl;
        preview.style.display = 'block';
        
        if (mediaId) {
            document.getElementById('featured-image-id').value = mediaId;
        }
    }

    removeFeaturedImage() {
        const preview = document.getElementById('featured-image-preview');
        const img = document.getElementById('featured-image-img');
        
        img.src = '';
        preview.style.display = 'none';
        document.getElementById('featured-image-id').value = '';
        
        this.markDirty();
    }

    // Content image management
    openImageUploadModal() {
        document.getElementById('image-upload-modal').classList.add('active');
    }

    closeImageUploadModal() {
        document.getElementById('image-upload-modal').classList.remove('active');
        
        // Reset upload state
        const progress = document.getElementById('image-upload-progress');
        progress.style.display = 'none';
        document.getElementById('upload-progress-fill').style.width = '0%';
        document.getElementById('upload-status').textContent = 'Uploading...';
    }

    async uploadContentImage(file) {
        const progress = document.getElementById('image-upload-progress');
        const progressFill = document.getElementById('upload-progress-fill');
        const statusText = document.getElementById('upload-status');

        try {
            progress.style.display = 'block';
            progressFill.style.width = '0%';
            statusText.textContent = 'Uploading...';

            // Simulate progress (since we can't track real progress with fetch)
            const progressInterval = setInterval(() => {
                const currentWidth = parseInt(progressFill.style.width) || 0;
                if (currentWidth < 90) {
                    progressFill.style.width = (currentWidth + 10) + '%';
                }
            }, 200);

            const media = await window.wpAPI.uploadMedia(file);
            
            clearInterval(progressInterval);
            progressFill.style.width = '100%';
            statusText.textContent = 'Upload complete!';

            // Insert image into editor
            const range = this.quillEditor.getSelection();
            const index = range ? range.index : this.quillEditor.getLength();
            
            this.quillEditor.insertEmbed(index, 'image', media.source_url);
            this.quillEditor.setSelection(index + 1);

            this.markDirty();
            this.showSuccessMessage('Image inserted!');
            
            // Close modal after a short delay
            setTimeout(() => {
                this.closeImageUploadModal();
            }, 1000);

        } catch (error) {
            console.error('Content image upload failed:', error);
            statusText.textContent = 'Upload failed!';
            progressFill.style.backgroundColor = '#dc3545';
            this.showErrorMessage('Upload failed: ' + error.message);
        }
    }

    // Auto-save functionality
    markDirty() {
        this.isDirty = true;
    }

    scheduleAutoSave() {
        if (!APP_CONFIG.ui.autoSave) return;

        if (this.autoSaveTimer) {
            clearTimeout(this.autoSaveTimer);
        }

        this.autoSaveTimer = setTimeout(() => {
            if (this.isDirty && this.currentItem && this.currentItem.id) {
                this.autoSave();
            }
        }, APP_CONFIG.ui.autoSaveInterval);
    }

    async autoSave() {
        try {
            const data = this.collectFormData();
            
            if (this.currentItemType === 'posts') {
                await window.wpAPI.updatePost(this.currentItem.id, data);
            } else {
                await window.wpAPI.updatePage(this.currentItem.id, data);
            }
            
            this.showAutoSaveIndicator();
            this.isDirty = false;
            
        } catch (error) {
            console.error('Auto-save failed:', error);
        }
    }

    showAutoSaveIndicator() {
        let indicator = document.querySelector('.saving-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'saving-indicator';
            document.body.appendChild(indicator);
        }
        
        indicator.textContent = 'Auto-saved';
        indicator.classList.add('show', 'success');
        
        setTimeout(() => {
            indicator.classList.remove('show');
            setTimeout(() => {
                indicator.classList.remove('success');
            }, 300);
        }, 2000);
    }

    // Message helpers
    showSuccessMessage(message) {
        this.showMessage(message, 'success');
    }

    showErrorMessage(message) {
        this.showMessage(message, 'error');
    }

    showMessage(message, type = 'info') {
        const messageDiv = document.createElement('div');
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            z-index: 10002;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        `;

        let bgColor, icon;
        switch (type) {
            case 'success':
                bgColor = '#28a745';
                icon = '<i class="fas fa-check-circle"></i>';
                break;
            case 'error':
                bgColor = '#dc3545';
                icon = '<i class="fas fa-exclamation-triangle"></i>';
                break;
            default:
                bgColor = '#0078d4';
                icon = '<i class="fas fa-info-circle"></i>';
        }

        messageDiv.style.backgroundColor = bgColor;
        messageDiv.innerHTML = `${icon}<span>${message}</span>`;
        
        document.body.appendChild(messageDiv);
        
        // Auto-remove after 4 seconds
        setTimeout(() => {
            if (messageDiv.parentElement) {
                messageDiv.style.opacity = '0';
                messageDiv.style.transform = 'translateY(-20px)';
                setTimeout(() => messageDiv.remove(), 300);
            }
        }, 4000);
    }
}

// Initialize editor when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.editorManager = new EditorManager();
});

// Export for use in other modules
window.EditorManager = EditorManager;





