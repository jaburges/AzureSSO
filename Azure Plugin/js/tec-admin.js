/**
 * TEC Integration Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Manual sync function for individual events
    window.azureTecManualSync = function(postId) {
        if (!postId) {
            alert('Invalid event ID');
            return;
        }
        
        // Show loading indicator
        var button = $('button[onclick="azureTecManualSync(' + postId + ')"]');
        var originalText = button.text();
        button.text('Syncing...').prop('disabled', true);
        
        $.ajax({
            url: azureTecAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'azure_tec_manual_sync',
                post_id: postId,
                nonce: azureTecAdmin.nonce
            },
            success: function(response) {
                button.text(originalText).prop('disabled', false);
                
                if (response.success) {
                    alert('Manual sync initiated successfully!');
                    
                    // Refresh the sync status
                    azureTecRefreshSyncStatus(postId);
                } else {
                    alert('Manual sync failed: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                button.text(originalText).prop('disabled', false);
                alert('Manual sync failed due to a network error: ' + error);
            }
        });
    };
    
    // Break sync function for individual events
    window.azureTecBreakSync = function(postId) {
        if (!postId) {
            alert('Invalid event ID');
            return;
        }
        
        if (!confirm('Are you sure you want to break the sync relationship for this event? This action cannot be undone.')) {
            return;
        }
        
        // Show loading indicator
        var button = $('button[onclick="azureTecBreakSync(' + postId + ')"]');
        var originalText = button.text();
        button.text('Breaking...').prop('disabled', true);
        
        $.ajax({
            url: azureTecAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'azure_tec_break_sync',
                post_id: postId,
                nonce: azureTecAdmin.nonce
            },
            success: function(response) {
                button.text(originalText).prop('disabled', false);
                
                if (response.success) {
                    alert('Sync relationship broken successfully!');
                    
                    // Refresh the page or update the metabox
                    location.reload();
                } else {
                    alert('Failed to break sync: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                button.text(originalText).prop('disabled', false);
                alert('Failed to break sync due to a network error: ' + error);
            }
        });
    };
    
    // Bulk sync function
    window.azureTecBulkSync = function(actionType) {
        if (!actionType) {
            alert('Invalid action type');
            return;
        }
        
        // Show progress indicator
        $('#azure-tec-sync-progress').show();
        $('.progress-bar-fill').css('width', '10%');
        
        // Disable buttons during sync
        $('.azure-tec-actions .button').prop('disabled', true);
        
        var actionText = actionType === 'sync_to_outlook' ? 'TEC to Outlook' : 'Outlook to TEC';
        
        $.ajax({
            url: azureTecAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'azure_tec_bulk_sync',
                action_type: actionType,
                nonce: azureTecAdmin.nonce
            },
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                
                // Simulate progress (since we don't have real progress feedback)
                var progress = 10;
                var progressInterval = setInterval(function() {
                    if (progress < 90) {
                        progress += Math.random() * 20;
                        $('.progress-bar-fill').css('width', Math.min(progress, 90) + '%');
                    }
                }, 500);
                
                xhr.addEventListener('load', function() {
                    clearInterval(progressInterval);
                    $('.progress-bar-fill').css('width', '100%');
                });
                
                return xhr;
            },
            success: function(response) {
                $('#azure-tec-sync-progress').hide();
                $('.azure-tec-actions .button').prop('disabled', false);
                
                if (response.success) {
                    alert('Bulk sync (' + actionText + ') completed successfully!');
                    
                    // Refresh statistics
                    azureTecRefreshStats();
                } else {
                    alert('Bulk sync failed: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                $('#azure-tec-sync-progress').hide();
                $('.azure-tec-actions .button').prop('disabled', false);
                alert('Bulk sync failed due to a network error: ' + error);
            }
        });
    };
    
    // Refresh statistics
    window.azureTecRefreshStats = function() {
        // For now, just reload the page
        // In a more advanced implementation, this would use AJAX
        location.reload();
    };
    
    // Refresh sync status for a specific event
    window.azureTecRefreshSyncStatus = function(postId) {
        if (!postId) {
            return;
        }
        
        $.ajax({
            url: azureTecAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'azure_tec_get_sync_status',
                post_id: postId,
                nonce: azureTecAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Update the metabox with new status
                    updateSyncStatusMetabox(postId, response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to refresh sync status:', error);
            }
        });
    };
    
    // Update sync status metabox
    function updateSyncStatusMetabox(postId, statusData) {
        var metabox = $('#azure-tec-sync-metabox');
        
        if (metabox.length === 0) {
            return;
        }
        
        // Update sync status
        var statusText = '';
        var statusColor = 'gray';
        
        switch (statusData.sync_status) {
            case 'synced':
                statusText = '✓ Synced';
                statusColor = 'green';
                break;
            case 'pending':
                statusText = '⏳ Pending';
                statusColor = 'orange';
                break;
            case 'error':
                statusText = '✗ Error';
                statusColor = 'red';
                break;
            default:
                statusText = 'Not synced';
                statusColor = 'gray';
                break;
        }
        
        metabox.find('p:first').html('<strong>Sync Status:</strong> <span style="color: ' + statusColor + ';">' + statusText + '</span>');
        
        // Update Outlook event ID if present
        if (statusData.outlook_event_id) {
            var outlookIdP = metabox.find('p:contains("Outlook Event ID")');
            if (outlookIdP.length === 0) {
                metabox.find('p:first').after('<p><strong>Outlook Event ID:</strong> ' + statusData.outlook_event_id + '</p>');
            } else {
                outlookIdP.html('<strong>Outlook Event ID:</strong> ' + statusData.outlook_event_id);
            }
        }
        
        // Update last sync time
        if (statusData.last_sync) {
            var lastSyncP = metabox.find('p:contains("Last Sync")');
            var lastSyncFormatted = new Date(statusData.last_sync).toLocaleString();
            
            if (lastSyncP.length === 0) {
                metabox.find('p:last').before('<p><strong>Last Sync:</strong> ' + lastSyncFormatted + '</p>');
            } else {
                lastSyncP.html('<strong>Last Sync:</strong> ' + lastSyncFormatted);
            }
        }
        
        // Update message if present
        if (statusData.sync_message) {
            var messageP = metabox.find('p:contains("Message")');
            
            if (messageP.length === 0) {
                metabox.find('.azure-tec-sync-actions').before('<p><strong>Message:</strong> ' + statusData.sync_message + '</p>');
            } else {
                messageP.html('<strong>Message:</strong> ' + statusData.sync_message);
            }
        }
    }
    
    // Auto-refresh sync status every 30 seconds if on event edit page
    if ($('#azure-tec-sync-metabox').length > 0) {
        var postId = $('#post_ID').val();
        
        if (postId) {
            setInterval(function() {
                azureTecRefreshSyncStatus(postId);
            }, 30000); // 30 seconds
        }
    }
    
    // Handle sync status column clicks in event list
    $(document).on('click', '.column-outlook_sync', function(e) {
        e.preventDefault();
        
        var row = $(this).closest('tr');
        var postId = row.find('.check-column input[type="checkbox"]').val();
        
        if (postId) {
            // Show a popup with sync details or trigger manual sync
            var syncStatus = $(this).find('span').attr('title');
            
            if (confirm('Current status: ' + syncStatus + '\n\nWould you like to trigger a manual sync for this event?')) {
                azureTecManualSync(postId);
            }
        }
    });
    
    // Add bulk actions to TEC events list
    if ($('body.post-type-tribe_events').length > 0) {
        // Add bulk sync options
        var bulkActions = $('#bulk-action-selector-top, #bulk-action-selector-bottom');
        
        bulkActions.append('<option value="azure_tec_sync_to_outlook">Sync to Outlook</option>');
        bulkActions.append('<option value="azure_tec_break_sync">Break Outlook Sync</option>');
        
        // Handle bulk action submission
        $('#doaction, #doaction2').click(function(e) {
            var action = $(this).siblings('select').val();
            
            if (action === 'azure_tec_sync_to_outlook' || action === 'azure_tec_break_sync') {
                e.preventDefault();
                
                var checkedPosts = $('tbody th.check-column input[type="checkbox"]:checked');
                
                if (checkedPosts.length === 0) {
                    alert('Please select at least one event.');
                    return;
                }
                
                var postIds = [];
                checkedPosts.each(function() {
                    postIds.push($(this).val());
                });
                
                var actionText = action === 'azure_tec_sync_to_outlook' ? 'sync to Outlook' : 'break sync relationship';
                
                if (confirm('Are you sure you want to ' + actionText + ' for ' + postIds.length + ' selected event(s)?')) {
                    azureTecBulkAction(action, postIds);
                }
            }
        });
    }
    
    // Bulk action handler
    function azureTecBulkAction(action, postIds) {
        if (!postIds || postIds.length === 0) {
            alert('No events selected');
            return;
        }
        
        // Show progress
        var progressHtml = '<div id="azure-tec-bulk-progress" style="margin: 20px 0;"><p>Processing ' + postIds.length + ' events...</p><div class="progress-bar"><div class="progress-bar-fill"></div></div></div>';
        $('.wrap h1').after(progressHtml);
        
        var completed = 0;
        var errors = 0;
        
        // Process each post ID
        postIds.forEach(function(postId, index) {
            setTimeout(function() {
                var ajaxAction = action === 'azure_tec_sync_to_outlook' ? 'azure_tec_manual_sync' : 'azure_tec_break_sync';
                
                $.ajax({
                    url: azureTecAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: ajaxAction,
                        post_id: postId,
                        nonce: azureTecAdmin.nonce
                    },
                    success: function(response) {
                        completed++;
                        
                        if (!response.success) {
                            errors++;
                        }
                        
                        // Update progress
                        var progress = (completed / postIds.length) * 100;
                        $('.progress-bar-fill').css('width', progress + '%');
                        
                        // Check if all completed
                        if (completed === postIds.length) {
                            $('#azure-tec-bulk-progress').remove();
                            
                            var message = 'Bulk action completed!\n';
                            message += 'Processed: ' + completed + ' events\n';
                            
                            if (errors > 0) {
                                message += 'Errors: ' + errors + ' events';
                            }
                            
                            alert(message);
                            location.reload();
                        }
                    },
                    error: function() {
                        completed++;
                        errors++;
                        
                        // Update progress
                        var progress = (completed / postIds.length) * 100;
                        $('.progress-bar-fill').css('width', progress + '%');
                        
                        // Check if all completed
                        if (completed === postIds.length) {
                            $('#azure-tec-bulk-progress').remove();
                            alert('Bulk action completed with ' + errors + ' errors.');
                            location.reload();
                        }
                    }
                });
            }, index * 500); // Stagger requests to avoid overwhelming the server
        });
    }
});