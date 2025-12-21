/**
 * Newsletter Editor with GrapesJS
 */

(function($) {
    'use strict';

    var editor = null;
    var currentStep = 1;

    // Initialize when document is ready
    $(document).ready(function() {
        initWorkflowNavigation();
        initSubjectCharCount();
        initSendOptions();
        initPageOptions();
        initRecipientCheckboxes();
        
        // Initialize GrapesJS when editor container exists
        if ($('#gjs-editor').length) {
            // Small delay to ensure libraries are loaded
            setTimeout(initGrapesJS, 100);
        }
    });

    /**
     * Initialize GrapesJS Editor
     */
    function initGrapesJS() {
        // Check if GrapesJS is available
        if (typeof grapesjs === 'undefined') {
            console.error('GrapesJS not loaded');
            $('#gjs-editor').html('<p style="padding:20px;color:#d63638;">Error: GrapesJS library not loaded. Please refresh the page.</p>');
            return;
        }

        try {
            editor = grapesjs.init({
                container: '#gjs-editor',
                fromElement: false,
                height: '100%',
                width: 'auto',
                storageManager: false,
                
                // Panels configuration
                panels: { defaults: [] },
                
                // Canvas configuration
                canvas: {
                    styles: [
                        'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap'
                    ]
                },
                
                // Device manager for responsive preview
                deviceManager: {
                    devices: [
                        { 
                            name: 'Desktop', 
                            width: '' 
                        },
                        { 
                            name: 'Tablet', 
                            width: '768px',
                            widthMedia: '992px'
                        },
                        { 
                            name: 'Mobile', 
                            width: '375px',
                            widthMedia: '480px'
                        },
                    ]
                },
                
                // Plugins
                plugins: ['grapesjs-preset-newsletter'],
                pluginsOpts: {
                    'grapesjs-preset-newsletter': {
                        modalTitleImport: 'Import HTML',
                        modalBtnImport: 'Import',
                        importPlaceholder: '<table>...</table>',
                        cellStyle: {
                            'font-family': 'Arial, sans-serif',
                            'font-size': '14px',
                            'color': '#333333'
                        }
                    }
                },
                
                // Style manager sectors
                styleManager: {
                    appendTo: '#styles-panel',
                    sectors: [
                        {
                            name: 'Typography',
                            open: true,
                            properties: [
                                'font-family',
                                'font-size',
                                'font-weight',
                                'letter-spacing',
                                'color',
                                'line-height',
                                'text-align',
                                'text-decoration'
                            ]
                        },
                        {
                            name: 'Spacing',
                            open: false,
                            properties: [
                                'padding',
                                'padding-top',
                                'padding-right',
                                'padding-bottom',
                                'padding-left',
                                'margin',
                                'margin-top',
                                'margin-right',
                                'margin-bottom',
                                'margin-left'
                            ]
                        },
                        {
                            name: 'Background',
                            open: false,
                            properties: [
                                'background-color',
                                'background-image',
                                'background-repeat',
                                'background-position',
                                'background-size'
                            ]
                        },
                        {
                            name: 'Border',
                            open: false,
                            properties: [
                                'border-width',
                                'border-style',
                                'border-color',
                                'border-radius'
                            ]
                        },
                        {
                            name: 'Dimensions',
                            open: false,
                            properties: [
                                'width',
                                'height',
                                'max-width',
                                'min-height'
                            ]
                        }
                    ]
                },
                
                // Layer manager
                layerManager: {
                    appendTo: '#layers-panel'
                },
                
                // Block manager
                blockManager: {
                    appendTo: '#blocks-panel'
                },
                
                // Asset manager for WordPress Media Library
                assetManager: {
                    upload: false,
                    uploadFile: function(e) {
                        // Use WordPress Media Library instead
                        openMediaLibrary();
                    },
                    custom: {
                        open: function(props) {
                            openMediaLibrary(props);
                        },
                        close: function() {}
                    }
                }
            });

            // Add custom email blocks
            addEmailBlocks();
            
            // Load initial content if available
            loadInitialContent();
            
            // Setup UI controls
            setupDeviceButtons();
            setupToolbarButtons();
            setupSidebarTabs();
            
            console.log('GrapesJS Newsletter Editor initialized successfully');
            
        } catch (error) {
            console.error('GrapesJS initialization error:', error);
            $('#gjs-editor').html('<p style="padding:20px;color:#d63638;">Error initializing editor: ' + error.message + '</p>');
        }
    }

    /**
     * Add custom email blocks with modern visual previews
     */
    function addEmailBlocks() {
        if (!editor) return;

        var bm = editor.BlockManager;

        // Clean Elementor-style SVG icons
        var c = '#6d7882'; // Icon color
        var icons = {
            // Layout
            section: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect x="4" y="8" width="40" height="32" rx="2" fill="none" stroke="'+c+'" stroke-width="2"/><line x1="4" y1="18" x2="44" y2="18" stroke="'+c+'" stroke-width="2"/></svg>',
            columns2: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect x="4" y="8" width="18" height="32" rx="2" fill="none" stroke="'+c+'" stroke-width="2"/><rect x="26" y="8" width="18" height="32" rx="2" fill="none" stroke="'+c+'" stroke-width="2"/></svg>',
            columns3: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect x="3" y="8" width="12" height="32" rx="2" fill="none" stroke="'+c+'" stroke-width="2"/><rect x="18" y="8" width="12" height="32" rx="2" fill="none" stroke="'+c+'" stroke-width="2"/><rect x="33" y="8" width="12" height="32" rx="2" fill="none" stroke="'+c+'" stroke-width="2"/></svg>',
            
            // Content
            text: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><line x1="6" y1="12" x2="42" y2="12" stroke="'+c+'" stroke-width="3" stroke-linecap="round"/><line x1="6" y1="20" x2="36" y2="20" stroke="'+c+'" stroke-width="3" stroke-linecap="round"/><line x1="6" y1="28" x2="42" y2="28" stroke="'+c+'" stroke-width="3" stroke-linecap="round"/><line x1="6" y1="36" x2="26" y2="36" stroke="'+c+'" stroke-width="3" stroke-linecap="round"/></svg>',
            heading: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><text x="8" y="35" font-family="Arial, sans-serif" font-size="28" font-weight="bold" fill="'+c+'">T</text><line x1="28" y1="34" x2="40" y2="34" stroke="'+c+'" stroke-width="2"/></svg>',
            image: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect x="4" y="8" width="40" height="32" rx="2" fill="none" stroke="'+c+'" stroke-width="2"/><circle cx="14" cy="18" r="4" fill="'+c+'"/><polyline points="4,36 16,24 24,32 32,22 44,36" fill="none" stroke="'+c+'" stroke-width="2" stroke-linejoin="round"/></svg>',
            button: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect x="6" y="16" width="36" height="16" rx="8" fill="none" stroke="'+c+'" stroke-width="2"/><line x1="16" y1="24" x2="32" y2="24" stroke="'+c+'" stroke-width="3" stroke-linecap="round"/></svg>',
            divider: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><line x1="4" y1="24" x2="44" y2="24" stroke="'+c+'" stroke-width="2"/></svg>',
            spacer: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><line x1="24" y1="8" x2="24" y2="40" stroke="'+c+'" stroke-width="2" stroke-dasharray="4 4"/><polyline points="16,14 24,6 32,14" fill="none" stroke="'+c+'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="16,34 24,42 32,34" fill="none" stroke="'+c+'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            
            // Sections
            header: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><text x="6" y="30" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="'+c+'">H</text><line x1="26" y1="18" x2="42" y2="18" stroke="'+c+'" stroke-width="2"/><line x1="26" y1="28" x2="38" y2="28" stroke="'+c+'" stroke-width="2"/></svg>',
            footer: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect x="4" y="28" width="40" height="12" rx="2" fill="'+c+'" opacity="0.15"/><rect x="4" y="28" width="40" height="12" rx="2" fill="none" stroke="'+c+'" stroke-width="2"/><line x1="12" y1="34" x2="36" y2="34" stroke="'+c+'" stroke-width="2"/><rect x="4" y="8" width="40" height="16" rx="2" fill="none" stroke="'+c+'" stroke-width="2" stroke-dasharray="4 4"/></svg>',
            social: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><circle cx="12" cy="24" r="6" fill="none" stroke="'+c+'" stroke-width="2"/><circle cx="24" cy="24" r="6" fill="none" stroke="'+c+'" stroke-width="2"/><circle cx="36" cy="24" r="6" fill="none" stroke="'+c+'" stroke-width="2"/></svg>',
            
            // Personalization
            user: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><circle cx="24" cy="16" r="8" fill="none" stroke="'+c+'" stroke-width="2"/><path d="M8,42 C8,32 16,26 24,26 C32,26 40,32 40,42" fill="none" stroke="'+c+'" stroke-width="2"/></svg>',
            email: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect x="4" y="10" width="40" height="28" rx="2" fill="none" stroke="'+c+'" stroke-width="2"/><polyline points="4,12 24,26 44,12" fill="none" stroke="'+c+'" stroke-width="2" stroke-linejoin="round"/></svg>',
            link: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><path d="M20,28 C18,26 18,22 20,20 L26,14 C28,12 32,12 34,14 C36,16 36,20 34,22 L32,24" fill="none" stroke="'+c+'" stroke-width="2" stroke-linecap="round"/><path d="M28,20 C30,22 30,26 28,28 L22,34 C20,36 16,36 14,34 C12,32 12,28 14,26 L16,24" fill="none" stroke="'+c+'" stroke-width="2" stroke-linecap="round"/></svg>',
            browser: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect x="4" y="8" width="40" height="32" rx="2" fill="none" stroke="'+c+'" stroke-width="2"/><line x1="4" y1="16" x2="44" y2="16" stroke="'+c+'" stroke-width="2"/><circle cx="10" cy="12" r="2" fill="'+c+'"/><circle cx="16" cy="12" r="2" fill="'+c+'"/><circle cx="22" cy="12" r="2" fill="'+c+'"/></svg>'
        };

        // === LAYOUT BLOCKS ===
        bm.add('section', {
            label: 'Section',
            category: 'Layout',
            media: icons.section,
            content: `
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="padding: 20px; background-color: #ffffff;">
                            <p>Section content here...</p>
                        </td>
                    </tr>
                </table>
            `
        });

        bm.add('columns-2', {
            label: '2 Columns',
            category: 'Layout',
            media: icons.columns2,
            content: `
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td width="50%" valign="top" style="padding: 10px;">
                            <p>Left column</p>
                        </td>
                        <td width="50%" valign="top" style="padding: 10px;">
                            <p>Right column</p>
                        </td>
                    </tr>
                </table>
            `
        });

        bm.add('columns-3', {
            label: '3 Columns',
            category: 'Layout',
            media: icons.columns3,
            content: `
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td width="33%" valign="top" style="padding: 10px;">
                            <p>Column 1</p>
                        </td>
                        <td width="34%" valign="top" style="padding: 10px;">
                            <p>Column 2</p>
                        </td>
                        <td width="33%" valign="top" style="padding: 10px;">
                            <p>Column 3</p>
                        </td>
                    </tr>
                </table>
            `
        });

        // === CONTENT BLOCKS ===
        bm.add('text-block', {
            label: 'Text',
            category: 'Content',
            media: icons.text,
            content: `
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="padding: 10px 20px; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333333;">
                            <p>Add your text content here. You can style this text using the Styles panel.</p>
                        </td>
                    </tr>
                </table>
            `
        });

        bm.add('heading', {
            label: 'Heading',
            category: 'Content',
            media: icons.heading,
            content: `
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="padding: 10px 20px;">
                            <h1 style="margin: 0; font-family: Arial, sans-serif; font-size: 28px; font-weight: bold; color: #1d2327;">
                                Your Heading Here
                            </h1>
                        </td>
                    </tr>
                </table>
            `
        });

        bm.add('image-block', {
            label: 'Image',
            category: 'Content',
            media: icons.image,
            content: `
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td align="center" style="padding: 10px;">
                            <img src="https://via.placeholder.com/600x300/e0e0e0/666666?text=Click+to+add+image" alt="Image" width="600" style="display: block; max-width: 100%; height: auto;">
                        </td>
                    </tr>
                </table>
            `
        });

        bm.add('button', {
            label: 'Button',
            category: 'Content',
            media: icons.button,
            content: `
                <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 15px auto;">
                    <tr>
                        <td align="center" bgcolor="#2271b1" style="border-radius: 4px;">
                            <a href="#" target="_blank" style="display: inline-block; padding: 14px 30px; font-family: Arial, sans-serif; font-size: 16px; font-weight: bold; color: #ffffff; text-decoration: none;">
                                Click Here
                            </a>
                        </td>
                    </tr>
                </table>
            `
        });

        bm.add('divider', {
            label: 'Divider',
            category: 'Content',
            media: icons.divider,
            content: `
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="padding: 20px;">
                            <hr style="border: none; border-top: 1px solid #dddddd; margin: 0;">
                        </td>
                    </tr>
                </table>
            `
        });

        bm.add('spacer', {
            label: 'Spacer',
            category: 'Content',
            media: icons.spacer,
            content: `
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="height: 30px; line-height: 30px; font-size: 1px;">&nbsp;</td>
                    </tr>
                </table>
            `
        });

        // === HEADER/FOOTER BLOCKS ===
        bm.add('header', {
            label: 'Header',
            category: 'Sections',
            media: icons.header,
            content: `
                <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#2271b1">
                    <tr>
                        <td align="center" style="padding: 30px 20px;">
                            <img src="https://via.placeholder.com/200x60/2271b1/ffffff?text=YOUR+LOGO" alt="Logo" width="200" style="display: block;">
                        </td>
                    </tr>
                </table>
            `
        });

        bm.add('footer', {
            label: 'Footer',
            category: 'Sections',
            media: icons.footer,
            content: `
                <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f8f9fa">
                    <tr>
                        <td align="center" style="padding: 30px 20px; font-family: Arial, sans-serif; font-size: 12px; color: #666666; line-height: 1.6;">
                            <p style="margin: 0 0 10px;">© ${new Date().getFullYear()} Your Organization. All rights reserved.</p>
                            <p style="margin: 0 0 10px;">123 Main Street, City, State 12345</p>
                            <p style="margin: 0;">
                                <a href="{{unsubscribe_url}}" style="color: #2271b1; text-decoration: underline;">Unsubscribe</a> &nbsp;|&nbsp; 
                                <a href="{{view_in_browser_url}}" style="color: #2271b1; text-decoration: underline;">View in Browser</a>
                            </p>
                        </td>
                    </tr>
                </table>
            `
        });

        bm.add('social-icons', {
            label: 'Social Icons',
            category: 'Sections',
            media: icons.social,
            content: `
                <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 20px auto;">
                    <tr>
                        <td style="padding: 0 8px;">
                            <a href="#" target="_blank">
                                <img src="https://cdn-icons-png.flaticon.com/32/733/733547.png" alt="Facebook" width="32" height="32" style="display: block;">
                            </a>
                        </td>
                        <td style="padding: 0 8px;">
                            <a href="#" target="_blank">
                                <img src="https://cdn-icons-png.flaticon.com/32/733/733579.png" alt="Twitter" width="32" height="32" style="display: block;">
                            </a>
                        </td>
                        <td style="padding: 0 8px;">
                            <a href="#" target="_blank">
                                <img src="https://cdn-icons-png.flaticon.com/32/733/733558.png" alt="Instagram" width="32" height="32" style="display: block;">
                            </a>
                        </td>
                        <td style="padding: 0 8px;">
                            <a href="#" target="_blank">
                                <img src="https://cdn-icons-png.flaticon.com/32/733/733561.png" alt="LinkedIn" width="32" height="32" style="display: block;">
                            </a>
                        </td>
                    </tr>
                </table>
            `
        });

        // === PERSONALIZATION BLOCKS ===
        bm.add('first-name', {
            label: 'First Name',
            category: 'Personalization',
            media: icons.user,
            content: '<span data-gjs-type="text">{{first_name}}</span>'
        });

        bm.add('last-name', {
            label: 'Last Name',
            category: 'Personalization',
            media: icons.user,
            content: '<span data-gjs-type="text">{{last_name}}</span>'
        });

        bm.add('email-tag', {
            label: 'Email',
            category: 'Personalization',
            media: icons.email,
            content: '<span data-gjs-type="text">{{email}}</span>'
        });

        bm.add('unsubscribe-link', {
            label: 'Unsubscribe',
            category: 'Personalization',
            media: icons.link,
            content: '<a href="{{unsubscribe_url}}" style="color: #666666;">Unsubscribe</a>'
        });

        bm.add('view-browser-link', {
            label: 'View Online',
            category: 'Personalization',
            media: icons.browser,
            content: '<a href="{{view_in_browser_url}}" style="color: #666666;">View in Browser</a>'
        });
    }

    /**
     * Load initial content into editor
     */
    function loadInitialContent() {
        if (!editor || typeof newsletterEditorConfig === 'undefined') return;

        if (newsletterEditorConfig.initialContent) {
            try {
                var data = JSON.parse(newsletterEditorConfig.initialContent);
                if (data && Object.keys(data).length > 0) {
                    editor.loadProjectData(data);
                    return;
                }
            } catch (e) {
                console.log('Could not parse JSON content, trying HTML');
            }
        }

        if (newsletterEditorConfig.initialHtml) {
            editor.setComponents(newsletterEditorConfig.initialHtml);
        } else {
            // Set default starter template
            editor.setComponents(`
                <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f4f4f4">
                    <tr>
                        <td align="center" style="padding: 20px;">
                            <table width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="max-width: 600px;">
                                <tr>
                                    <td align="center" bgcolor="#2271b1" style="padding: 30px 20px;">
                                        <h1 style="margin: 0; font-family: Arial, sans-serif; font-size: 24px; color: #ffffff;">
                                            Your Newsletter Title
                                        </h1>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 30px 20px; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333333;">
                                        <p>Hello {{first_name}},</p>
                                        <p>Start creating your newsletter by dragging blocks from the left panel. You can add text, images, buttons, and more.</p>
                                        <p>Use the Styles panel to customize colors, fonts, and spacing.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" bgcolor="#f8f9fa" style="padding: 20px; font-family: Arial, sans-serif; font-size: 12px; color: #666666;">
                                        <p style="margin: 0;">
                                            <a href="{{unsubscribe_url}}" style="color: #666666;">Unsubscribe</a> | 
                                            <a href="{{view_in_browser_url}}" style="color: #666666;">View in Browser</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            `);
        }
    }

    /**
     * Setup device preview buttons
     */
    function setupDeviceButtons() {
        $('.device-btn').on('click', function() {
            var device = $(this).data('device');
            $('.device-btn').removeClass('active');
            $(this).addClass('active');
            
            if (editor) {
                switch (device) {
                    case 'desktop':
                        editor.setDevice('Desktop');
                        break;
                    case 'tablet':
                        editor.setDevice('Tablet');
                        break;
                    case 'mobile':
                        editor.setDevice('Mobile');
                        break;
                }
            }
        });
    }

    /**
     * Setup toolbar buttons (undo, redo, code)
     */
    function setupToolbarButtons() {
        // Undo
        $('#btn-undo').on('click', function() {
            if (editor) {
                editor.UndoManager.undo();
            }
        });
        
        // Redo
        $('#btn-redo').on('click', function() {
            if (editor) {
                editor.UndoManager.redo();
            }
        });

        // View/Edit code
        $('#btn-code').on('click', function() {
            if (editor) {
                var html = editor.getHtml();
                var css = editor.getCss();
                
                // Create modal for code view
                var modal = editor.Modal;
                modal.setTitle('Email HTML Code');
                modal.setContent(`
                    <div style="padding: 10px;">
                        <h4>HTML</h4>
                        <textarea style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;">${escapeHtml(html)}</textarea>
                        <h4 style="margin-top: 15px;">CSS</h4>
                        <textarea style="width: 100%; height: 100px; font-family: monospace; font-size: 12px;">${escapeHtml(css)}</textarea>
                    </div>
                `);
                modal.open();
            }
        });
    }

    /**
     * Setup sidebar tabs (Blocks, Styles, Layers)
     */
    function setupSidebarTabs() {
        $('.sidebar-tab').on('click', function() {
            var panel = $(this).data('panel');
            
            // Update tab active state
            $('.sidebar-tab').removeClass('active');
            $(this).addClass('active');
            
            // Show/hide panels
            $('.sidebar-panel').hide();
            $('#' + panel + '-panel').show();
        });
    }

    /**
     * Open WordPress Media Library
     */
    function openMediaLibrary(props) {
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            alert('WordPress Media Library not available');
            return;
        }

        var frame = wp.media({
            title: 'Select Image for Newsletter',
            button: { text: 'Insert Image' },
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            
            if (editor) {
                editor.AssetManager.add({
                    src: attachment.url,
                    width: attachment.width,
                    height: attachment.height,
                    name: attachment.filename
                });
                
                // If we have a target (existing image), update it
                if (props && props.target) {
                    props.target.set('src', attachment.url);
                }
            }
        });

        frame.open();
    }

    /**
     * Escape HTML for display
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Workflow Navigation
     */
    function initWorkflowNavigation() {
        currentStep = parseInt($('#current_step').val()) || 1;

        // Next step buttons
        $('.next-step').on('click', function(e) {
            e.preventDefault();
            var nextStep = parseInt($(this).data('next'));
            if (validateStep(currentStep)) {
                goToStep(nextStep);
            }
        });

        // Previous step buttons
        $('.prev-step').on('click', function(e) {
            e.preventDefault();
            var prevStep = parseInt($(this).data('prev'));
            goToStep(prevStep);
        });

        // Arrow step indicators (clickable for completed steps)
        $('.arrow-step').on('click', function() {
            var step = parseInt($(this).data('step'));
            if ($(this).hasClass('completed') || step <= currentStep) {
                goToStep(step);
            }
        });
    }

    /**
     * Go to specific step
     */
    function goToStep(step) {
        // Save editor content before leaving step 2
        if (currentStep === 2 && editor) {
            var html = editor.getHtml();
            var css = editor.getCss();
            var fullHtml = '<!DOCTYPE html><html><head><style>' + css + '</style></head><body>' + html + '</body></html>';
            $('#newsletter_content_html').val(fullHtml);
            $('#newsletter_content_json').val(JSON.stringify(editor.getProjectData()));
        }

        // Update arrow flow visual
        $('.arrow-step').each(function() {
            var stepNum = parseInt($(this).data('step'));
            $(this).removeClass('current completed pending');
            
            if (stepNum < step) {
                $(this).addClass('completed');
                // Replace number with checkmark
                var $content = $(this).find('.arrow-content');
                if ($content.find('.step-num').length) {
                    $content.find('.step-num').replaceWith('<span class="dashicons dashicons-yes-alt"></span>');
                }
            } else if (stepNum === step) {
                $(this).addClass('current');
                // Ensure it shows the number
                var $content = $(this).find('.arrow-content');
                if ($content.find('.dashicons').length) {
                    $content.find('.dashicons').replaceWith('<span class="step-num">' + stepNum + '</span>');
                }
            } else {
                $(this).addClass('pending');
                var $content = $(this).find('.arrow-content');
                if ($content.find('.dashicons').length) {
                    $content.find('.dashicons').replaceWith('<span class="step-num">' + stepNum + '</span>');
                }
            }
        });

        // Show/hide content
        $('.step-content').hide();
        $('#step-' + step + '-content').show();

        // Update current step
        currentStep = step;
        $('#current_step').val(step);

        // Step-specific initialization
        if (step === 2 && !editor && $('#gjs-editor').length) {
            initGrapesJS();
        }
        
        if (step === 3) {
            updateReviewSummary();
            updatePreview();
        }

        // Scroll to top
        $('html, body').animate({
            scrollTop: $('.newsletter-editor-wrap').offset().top - 50
        }, 300);
    }

    /**
     * Validate step before proceeding
     */
    function validateStep(step) {
        var errors = [];

        if (step === 1) {
            var name = $('#newsletter_name').val().trim();
            var subject = $('#newsletter_subject').val().trim();
            var from = $('#newsletter_from').val();
            var recipients = $('input[name="newsletter_lists[]"]:checked').length;

            if (!name) {
                errors.push('Please enter a newsletter name.');
                $('#newsletter_name').focus();
            }
            if (!subject) {
                errors.push('Please enter an email subject.');
            }
            if (!from) {
                errors.push('Please select a sender.');
            }
            if (recipients === 0) {
                errors.push('Please select at least one recipient list.');
            }
        }

        if (errors.length > 0) {
            alert(errors.join('\n'));
            return false;
        }

        return true;
    }

    /**
     * Update review summary (Step 3)
     */
    function updateReviewSummary() {
        $('#summary-subject').text($('#newsletter_subject').val());
        $('#summary-from').text($('#newsletter_from option:selected').text() || 'Not selected');
        
        var selectedLists = [];
        $('input[name="newsletter_lists[]"]:checked').each(function() {
            selectedLists.push($(this).closest('label').find('strong').text());
        });
        $('#summary-recipients').text(selectedLists.join(', ') || 'None selected');
    }

    /**
     * Update preview iframe (Step 3)
     */
    function updatePreview() {
        var html = $('#newsletter_content_html').val();
        var frame = document.getElementById('preview-frame');
        if (frame && html) {
            var doc = frame.contentDocument || frame.contentWindow.document;
            doc.open();
            doc.write(html);
            doc.close();
        }
    }

    /**
     * Subject character count
     */
    function initSubjectCharCount() {
        $('#newsletter_subject').on('input', function() {
            var count = $(this).val().length;
            $('#subject-chars').text(count);
            if (count > 60) {
                $('#subject-chars').css('color', '#d63638');
            } else if (count > 50) {
                $('#subject-chars').css('color', '#dba617');
            } else {
                $('#subject-chars').css('color', '#646970');
            }
        }).trigger('input');
    }

    /**
     * Recipient checkboxes
     */
    function initRecipientCheckboxes() {
        $('input[name="newsletter_lists[]"]').on('change', function() {
            updateRecipientCount();
        });
        updateRecipientCount();
    }

    /**
     * Update recipient count
     */
    function updateRecipientCount() {
        var selectedLists = $('input[name="newsletter_lists[]"]:checked');
        
        if (selectedLists.length === 0) {
            $('#total-recipient-count').text('0');
            return;
        }

        // Simple count for now - in production, this would make AJAX calls
        var count = 0;
        selectedLists.each(function() {
            if ($(this).val() === 'all') {
                // Rough estimate for all WordPress users
                count += 100; // This should be replaced with actual AJAX call
            } else {
                count += 50; // Placeholder
            }
        });
        
        $('#total-recipient-count').text(count.toLocaleString() + '+');
    }

    /**
     * Send options toggle
     */
    function initSendOptions() {
        $('input[name="send_option"]').on('change', function() {
            var option = $(this).val();
            $('#schedule-options').toggle(option === 'schedule');
            
            if (option === 'draft') {
                $('#final-send-btn').hide();
                $('#save-draft-btn').show();
            } else {
                $('#final-send-btn').show();
                $('#save-draft-btn').hide();
            }
        });
    }

    /**
     * Page options toggle
     */
    function initPageOptions() {
        $('#create_wp_page').on('change', function() {
            $('#page-settings').toggle($(this).is(':checked'));
        });
    }

    /**
     * Preview device toggle (Step 3)
     */
    $(document).on('click', '.preview-device', function() {
        var device = $(this).data('device');
        $('.preview-device').removeClass('active');
        $(this).addClass('active');
        
        if (device === 'mobile') {
            $('#preview-frame').addClass('mobile');
        } else {
            $('#preview-frame').removeClass('mobile');
        }
    });

    /**
     * Spam score check
     */
    $(document).on('click', '#check-spam-score', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Checking...');

        $.post(newsletterEditorConfig.ajaxUrl, {
            action: 'azure_newsletter_spam_check',
            nonce: newsletterEditorConfig.nonce,
            html: $('#newsletter_content_html').val(),
            subject: $('#newsletter_subject').val()
        }, function(response) {
            btn.prop('disabled', false).text('Check Spam Score');
            var result = $('#spam-score-result').show();
            
            if (response.success) {
                var score = response.data.score;
                var scoreClass = score <= 3 ? 'good' : (score <= 5 ? 'warning' : 'bad');
                result.html(
                    '<div class="spam-score">' +
                    '<span class="spam-score-value ' + scoreClass + '">' + score + '/10</span>' +
                    '<span>' + response.data.message + '</span>' +
                    '</div>' +
                    (response.data.issues && response.data.issues.length ? '<ul><li>' + response.data.issues.join('</li><li>') + '</li></ul>' : '')
                );
            } else {
                result.html('<p class="error" style="color:#d63638;">' + (response.data || 'Error checking spam score') + '</p>');
            }
        });
    });

    /**
     * Accessibility check
     */
    $(document).on('click', '#check-accessibility', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Checking...');

        $.post(newsletterEditorConfig.ajaxUrl, {
            action: 'azure_newsletter_accessibility_check',
            nonce: newsletterEditorConfig.nonce,
            html: $('#newsletter_content_html').val()
        }, function(response) {
            btn.prop('disabled', false).text('Check Accessibility');
            var result = $('#accessibility-result').show();
            
            if (response.success && response.data.checks) {
                var html = '';
                response.data.checks.forEach(function(check) {
                    var icon = check.pass ? 'yes' : 'no';
                    var status = check.pass ? 'pass' : 'fail';
                    html += '<div class="accessibility-item ' + status + '">' +
                            '<span class="dashicons dashicons-' + icon + '"></span>' +
                            '<span>' + check.message + '</span>' +
                            '</div>';
                });
                result.html(html);
            } else {
                result.html('<p class="error" style="color:#d63638;">Error checking accessibility</p>');
            }
        });
    });

    /**
     * Send test email
     */
    $(document).on('click', '#send-test-email', function() {
        var btn = $(this);
        var email = $('#test_email').val();
        
        if (!email) {
            alert('Please enter an email address.');
            return;
        }

        btn.prop('disabled', true).text('Sending...');

        $.post(newsletterEditorConfig.ajaxUrl, {
            action: 'azure_newsletter_send_test',
            nonce: newsletterEditorConfig.nonce,
            email: email,
            html: $('#newsletter_content_html').val(),
            subject: $('#newsletter_subject').val(),
            from: $('#newsletter_from').val()
        }, function(response) {
            btn.prop('disabled', false).text('Send Test');
            var result = $('#test-send-result').show();
            
            if (response.success) {
                result.html('<p style="color:#00a32a;">✓ Test email sent to ' + email + '</p>');
            } else {
                result.html('<p style="color:#d63638;">✗ ' + (response.data || 'Failed to send test email') + '</p>');
            }
        });
    });

    /**
     * Insert personalization tag into subject
     */
    $(document).on('click', '.insert-personalization', function() {
        var tag = $(this).data('tag');
        var input = $('#newsletter_subject')[0];
        var val = $(input).val();
        var start = input.selectionStart;
        var end = input.selectionEnd;
        
        $(input).val(val.substring(0, start) + tag + val.substring(end));
        $(input).trigger('input');
        
        // Set cursor position after tag
        input.selectionStart = input.selectionEnd = start + tag.length;
        input.focus();
    });

})(jQuery);
