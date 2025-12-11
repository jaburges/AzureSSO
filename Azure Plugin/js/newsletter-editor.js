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
        
        // Initialize GrapesJS when on step 2
        if ($('#gjs-editor').length) {
            initGrapesJS();
        }
    });

    /**
     * Initialize GrapesJS Editor
     */
    function initGrapesJS() {
        editor = grapesjs.init({
            container: '#gjs-editor',
            fromElement: false,
            height: '100%',
            width: 'auto',
            storageManager: false,
            panels: { defaults: [] },
            
            // Use newsletter preset
            plugins: ['grapesjs-preset-newsletter'],
            pluginsOpts: {
                'grapesjs-preset-newsletter': {
                    modalTitleImport: 'Import HTML',
                    modalBtnImport: 'Import',
                }
            },
            
            // Device manager for responsive preview
            deviceManager: {
                devices: [
                    { name: 'Desktop', width: '' },
                    { name: 'Tablet', width: '768px', widthMedia: '992px' },
                    { name: 'Mobile', width: '320px', widthMedia: '480px' },
                ]
            },
            
            // Asset manager for WordPress Media Library
            assetManager: {
                upload: false,
                custom: {
                    open: function(props) {
                        openMediaLibrary(props);
                    },
                    close: function() {}
                }
            },
            
            // Style manager
            styleManager: {
                appendTo: '#styles-panel'
            },
            
            // Layer manager
            layerManager: {
                appendTo: '#layers-panel'
            },
            
            // Block manager
            blockManager: {
                appendTo: '#blocks-panel'
            }
        });

        // Load initial content
        if (newsletterEditorConfig.initialContent) {
            try {
                var data = JSON.parse(newsletterEditorConfig.initialContent);
                editor.loadProjectData(data);
            } catch (e) {
                if (newsletterEditorConfig.initialHtml) {
                    editor.setComponents(newsletterEditorConfig.initialHtml);
                }
            }
        }

        // Add custom blocks
        addCustomBlocks();
        
        // Device toggle buttons
        $('.device-btn').on('click', function() {
            var device = $(this).data('device');
            $('.device-btn').removeClass('active');
            $(this).addClass('active');
            
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
        });

        // Undo/Redo buttons
        $('#btn-undo').on('click', function() {
            editor.UndoManager.undo();
        });
        
        $('#btn-redo').on('click', function() {
            editor.UndoManager.redo();
        });

        // View code button
        $('#btn-code').on('click', function() {
            var html = editor.getHtml();
            var css = editor.getCss();
            alert('HTML:\n' + html + '\n\nCSS:\n' + css);
        });

        // Sidebar tabs
        $('.sidebar-tab').on('click', function() {
            var panel = $(this).data('panel');
            $('.sidebar-tab').removeClass('active');
            $(this).addClass('active');
            $('.sidebar-panel').hide();
            $('#' + panel + '-panel').show();
        });
    }

    /**
     * Add custom blocks for newsletter
     */
    function addCustomBlocks() {
        if (!editor) return;

        var bm = editor.BlockManager;

        // Header block
        bm.add('newsletter-header', {
            label: 'Header',
            category: 'Newsletter',
            content: `
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #2271b1;">
                    <tr>
                        <td align="center" style="padding: 30px 20px;">
                            <img src="${newsletterEditorConfig.pluginUrl}images/logo-placeholder.png" alt="Logo" width="150" style="display: block;">
                        </td>
                    </tr>
                </table>
            `,
            attributes: { class: 'fa fa-header' }
        });

        // Button block
        bm.add('newsletter-button', {
            label: 'Button',
            category: 'Newsletter',
            content: `
                <table cellpadding="0" cellspacing="0" border="0" align="center">
                    <tr>
                        <td align="center" style="background-color: #2271b1; border-radius: 4px;">
                            <a href="#" target="_blank" style="display: inline-block; padding: 14px 28px; color: #ffffff; text-decoration: none; font-family: Arial, sans-serif; font-size: 16px; font-weight: bold;">
                                Click Here
                            </a>
                        </td>
                    </tr>
                </table>
            `,
            attributes: { class: 'fa fa-link' }
        });

        // Divider block
        bm.add('newsletter-divider', {
            label: 'Divider',
            category: 'Newsletter',
            content: `
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="padding: 20px 0;">
                            <hr style="border: none; border-top: 1px solid #dddddd; margin: 0;">
                        </td>
                    </tr>
                </table>
            `,
            attributes: { class: 'fa fa-minus' }
        });

        // Spacer block
        bm.add('newsletter-spacer', {
            label: 'Spacer',
            category: 'Newsletter',
            content: `
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="height: 30px; line-height: 30px; font-size: 1px;">&nbsp;</td>
                    </tr>
                </table>
            `,
            attributes: { class: 'fa fa-arrows-v' }
        });

        // Social icons block
        bm.add('newsletter-social', {
            label: 'Social Icons',
            category: 'Newsletter',
            content: `
                <table cellpadding="0" cellspacing="0" border="0" align="center">
                    <tr>
                        <td style="padding: 0 5px;">
                            <a href="#" target="_blank"><img src="https://cdn-icons-png.flaticon.com/32/733/733547.png" alt="Facebook" width="32" height="32"></a>
                        </td>
                        <td style="padding: 0 5px;">
                            <a href="#" target="_blank"><img src="https://cdn-icons-png.flaticon.com/32/733/733579.png" alt="Twitter" width="32" height="32"></a>
                        </td>
                        <td style="padding: 0 5px;">
                            <a href="#" target="_blank"><img src="https://cdn-icons-png.flaticon.com/32/733/733558.png" alt="Instagram" width="32" height="32"></a>
                        </td>
                    </tr>
                </table>
            `,
            attributes: { class: 'fa fa-share-alt' }
        });

        // Footer block
        bm.add('newsletter-footer', {
            label: 'Footer',
            category: 'Newsletter',
            content: `
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f8f9fa;">
                    <tr>
                        <td align="center" style="padding: 30px 20px; font-family: Arial, sans-serif; font-size: 12px; color: #666666;">
                            <p style="margin: 0 0 10px;">© ${new Date().getFullYear()} Your Company. All rights reserved.</p>
                            <p style="margin: 0;">
                                <a href="{{unsubscribe_url}}" style="color: #666666;">Unsubscribe</a> | 
                                <a href="{{view_in_browser_url}}" style="color: #666666;">View in Browser</a>
                            </p>
                        </td>
                    </tr>
                </table>
            `,
            attributes: { class: 'fa fa-bookmark' }
        });

        // Personalization block
        bm.add('newsletter-personalization', {
            label: 'First Name',
            category: 'Personalization',
            content: '{{first_name}}',
            attributes: { class: 'fa fa-user' }
        });
    }

    /**
     * Open WordPress Media Library
     */
    function openMediaLibrary(props) {
        var frame = wp.media({
            title: 'Select Image',
            button: { text: 'Insert' },
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            editor.AssetManager.add({
                src: attachment.url,
                width: attachment.width,
                height: attachment.height
            });
            if (props && props.target) {
                props.target.set('src', attachment.url);
            }
        });

        frame.open();
    }

    /**
     * Workflow Navigation
     */
    function initWorkflowNavigation() {
        // Next step buttons
        $('.next-step').on('click', function() {
            var nextStep = parseInt($(this).data('next'));
            goToStep(nextStep);
        });

        // Previous step buttons
        $('.prev-step').on('click', function() {
            var prevStep = parseInt($(this).data('prev'));
            goToStep(prevStep);
        });

        // Step indicators (allow clicking completed steps)
        $('.workflow-steps .step').on('click', function() {
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
        // Validate current step before proceeding
        if (step > currentStep && !validateStep(currentStep)) {
            return;
        }

        // Save editor content before leaving step 2
        if (currentStep === 2 && editor) {
            var html = editor.getHtml();
            var css = editor.getCss();
            var fullHtml = '<style>' + css + '</style>' + html;
            $('#newsletter_content_html').val(fullHtml);
            $('#newsletter_content_json').val(JSON.stringify(editor.getProjectData()));
        }

        // Update step indicators
        $('.workflow-steps .step').each(function() {
            var stepNum = parseInt($(this).data('step'));
            $(this).removeClass('active completed');
            if (stepNum < step) {
                $(this).addClass('completed');
            } else if (stepNum === step) {
                $(this).addClass('active');
            }
        });

        // Update connectors
        $('.step-connector').each(function(i) {
            $(this).toggleClass('completed', i < step - 1);
        });

        // Show/hide content
        $('.step-content').hide();
        $('#step-' + step + '-content').show();

        // Update current step
        currentStep = step;
        $('#current_step').val(step);

        // Step-specific initialization
        if (step === 3) {
            updateReviewSummary();
            updatePreview();
        }
    }

    /**
     * Validate step before proceeding
     */
    function validateStep(step) {
        if (step === 1) {
            var name = $('#newsletter_name').val().trim();
            var subject = $('#newsletter_subject').val().trim();
            var from = $('#newsletter_from').val();

            if (!name) {
                alert('Please enter a newsletter name.');
                $('#newsletter_name').focus();
                return false;
            }
            if (!subject) {
                alert('Please enter an email subject.');
                $('#newsletter_subject').focus();
                return false;
            }
            if (!from) {
                alert('Please select a sender.');
                $('#newsletter_from').focus();
                return false;
            }
        }

        return true;
    }

    /**
     * Update review summary
     */
    function updateReviewSummary() {
        $('#summary-subject').text($('#newsletter_subject').val());
        $('#summary-from').text($('#newsletter_from option:selected').text());
        $('#summary-recipients').text($('#newsletter_list option:selected').text());
    }

    /**
     * Update preview iframe
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
            } else {
                $('#subject-chars').css('color', '');
            }
        }).trigger('input');
    }

    /**
     * Send options toggle
     */
    function initSendOptions() {
        $('input[name="send_option"]').on('change', function() {
            var option = $(this).val();
            $('#schedule-options').toggle(option === 'schedule');
            
            // Update button text
            if (option === 'draft') {
                $('#final-send-btn').hide();
                $('#save-draft-btn').show();
            } else {
                $('#final-send-btn').show();
                $('#save-draft-btn').hide();
                $('#final-send-btn').find('span:last').text(option === 'now' ? 'Send Now' : 'Schedule');
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
     * Preview device toggle
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
     * Check spam score
     */
    $('#check-spam-score').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Checking...');

        $.post(newsletterEditorConfig.ajaxUrl, {
            action: 'azure_newsletter_spam_check',
            nonce: newsletterEditorConfig.nonce,
            html: $('#newsletter_content_html').val(),
            subject: $('#newsletter_subject').val()
        }, function(response) {
            btn.prop('disabled', false).text('Check Spam Score');
            
            var result = $('#spam-score-result');
            result.show();
            
            if (response.success) {
                var score = response.data.score;
                var scoreClass = score <= 3 ? 'good' : (score <= 5 ? 'warning' : 'bad');
                result.html(
                    '<div class="spam-score">' +
                    '<span class="spam-score-value ' + scoreClass + '">' + score + '/10</span>' +
                    '<span>' + response.data.message + '</span>' +
                    '</div>' +
                    (response.data.issues ? '<ul><li>' + response.data.issues.join('</li><li>') + '</li></ul>' : '')
                );
            } else {
                result.html('<p class="error">' + response.data + '</p>');
            }
        });
    });

    /**
     * Check accessibility
     */
    $('#check-accessibility').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Checking...');

        $.post(newsletterEditorConfig.ajaxUrl, {
            action: 'azure_newsletter_accessibility_check',
            nonce: newsletterEditorConfig.nonce,
            html: $('#newsletter_content_html').val()
        }, function(response) {
            btn.prop('disabled', false).text('Check Accessibility');
            
            var result = $('#accessibility-result');
            result.show();
            
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
                result.html('<p class="error">' + (response.data || 'Check failed') + '</p>');
            }
        });
    });

    /**
     * Send test email
     */
    $('#send-test-email').on('click', function() {
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
            
            var result = $('#test-send-result');
            result.show();
            
            if (response.success) {
                result.html('<p class="success" style="color: #00a32a;">✓ Test email sent to ' + email + '</p>');
            } else {
                result.html('<p class="error" style="color: #d63638;">✗ ' + response.data + '</p>');
            }
        });
    });

    /**
     * Insert personalization tag
     */
    $('.insert-personalization').on('click', function() {
        var tag = $(this).data('tag');
        var input = $('#newsletter_subject');
        var val = input.val();
        var pos = input[0].selectionStart;
        input.val(val.substring(0, pos) + tag + val.substring(pos));
        input.trigger('input');
    });

})(jQuery);




