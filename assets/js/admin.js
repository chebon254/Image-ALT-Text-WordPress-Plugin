/**
 * Image Alt Text Populator - Admin JavaScript
 */

(function($) {
    'use strict';

    let isProcessing = false;
    let totalUpdated = 0;
    let totalSkipped = 0;

    /**
     * Initialize plugin
     */
    function init() {
        // Handle bulk update button
        $('#iatp-bulk-update').on('click', handleBulkUpdate);
        
        // Handle alt text format change
        $('#iatp_alt_text_format').on('change', function() {
            toggleCustomTextField();
            updatePreview();
        });
        
        // Handle custom text input
        $('#iatp_custom_alt_text').on('input', updatePreview);
        
        // Initialize custom text field visibility
        toggleCustomTextField();
        
        // Initialize preview
        updatePreview();
        
        // Refresh stats button (if needed)
        refreshStats();
    }

    /**
     * Toggle custom text field visibility
     */
    function toggleCustomTextField() {
        const format = $('#iatp_alt_text_format').val();
        const customRow = $('#iatp_custom_alt_text_row');
        
        if (format === 'custom') {
            customRow.show();
        } else {
            customRow.hide();
        }
    }

    /**
     * Update preview based on selected format
     */
    function updatePreview() {
        const format = $('#iatp_alt_text_format').val();
        let previewText = '';
        
        switch (format) {
            case 'sitename':
                previewText = iatpData.siteName;
                break;
            
            case 'sitename_filename':
                previewText = iatpData.siteName + ' - Example Image';
                break;
            
            case 'custom':
                const customText = $('#iatp_custom_alt_text').val().trim();
                previewText = customText || iatpData.siteName;
                break;
            
            default:
                previewText = iatpData.siteName;
        }
        
        $('#iatp-alt-preview').text(previewText);
    }

    /**
     * Refresh statistics
     */
    function refreshStats() {
        $.ajax({
            url: iatpData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'iatp_get_progress',
                nonce: iatpData.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#iatp-total-images').text(response.data.total);
                    $('#iatp-with-alt').text(response.data.withAlt);
                    $('#iatp-without-alt').text(response.data.withoutAlt);
                }
            }
        });
    }

    /**
     * Handle bulk update
     */
    function handleBulkUpdate(e) {
        e.preventDefault();
        
        if (isProcessing) {
            return;
        }

        const $button = $(this);
        const confirmMessage = 'Are you sure you want to update alt text for all images? This may take a while depending on the number of images.';
        
        if (!confirm(confirmMessage)) {
            return;
        }

        isProcessing = true;
        totalUpdated = 0;
        totalSkipped = 0;

        // Update button state
        $button.prop('disabled', true)
               .html('<span class="iatp-spinner"></span>' + iatpData.strings.processing);

        // Show progress container
        $('#iatp-progress-container').show();
        $('#iatp-result').hide();

        // Start processing
        processBatch(0);
    }

    /**
     * Process a batch of images
     */
    function processBatch(batch) {
        $.ajax({
            url: iatpData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'iatp_bulk_update',
                nonce: iatpData.nonce,
                batch: batch
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    totalUpdated += data.updated;
                    totalSkipped += data.skipped;

                    // Update progress
                    const percentage = Math.min(100, Math.round((data.processed / data.total) * 100));
                    $('#iatp-progress-fill').width(percentage + '%');
                    $('#iatp-progress-text').text(percentage + '%');
                    $('#iatp-progress-info').text(
                        'Processed ' + Math.min(data.processed, data.total) + ' of ' + data.total + ' images'
                    );

                    // Continue or finish
                    if (data.hasMore) {
                        processBatch(data.nextBatch);
                    } else {
                        completeBulkUpdate();
                    }
                } else {
                    handleError(response.data.message);
                }
            },
            error: function() {
                handleError(iatpData.strings.error);
            }
        });
    }

    /**
     * Complete bulk update
     */
    function completeBulkUpdate() {
        isProcessing = false;

        // Update button
        const $button = $('#iatp-bulk-update');
        $button.prop('disabled', false)
               .text('Update All Images');

        // Show results
        const $result = $('#iatp-result');
        let resultHTML = '<strong>' + iatpData.strings.complete + '</strong>';
        resultHTML += '<div style="margin-top: 10px;">';
        resultHTML += '<strong>Updated:</strong> ' + totalUpdated + ' images<br>';
        resultHTML += '<strong>Skipped:</strong> ' + totalSkipped + ' images (already had alt text or no update needed)';
        resultHTML += '</div>';

        $result.removeClass('error info')
               .addClass('success')
               .html(resultHTML)
               .slideDown();

        // Hide progress after a delay
        setTimeout(function() {
            $('#iatp-progress-container').slideUp();
        }, 2000);

        // Refresh statistics
        refreshStats();
    }

    /**
     * Handle errors
     */
    function handleError(message) {
        isProcessing = false;

        // Update button
        const $button = $('#iatp-bulk-update');
        $button.prop('disabled', false)
               .text('Update All Images');

        // Show error
        $('#iatp-result').removeClass('success info')
                        .addClass('error')
                        .html('<strong>Error:</strong> ' + message)
                        .slideDown();

        // Hide progress
        $('#iatp-progress-container').slideUp();
    }

    // Initialize when document is ready
    $(document).ready(init);

})(jQuery);
