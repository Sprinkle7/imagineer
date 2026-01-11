/**
 * Imagineer Frontend JavaScript
 * Handles shortcode widgets on frontend
 */

(function($) {
    'use strict';
    
    /**
     * Format file size for display
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    /**
     * Initialize converter widgets (single file)
     */
    function initConverterWidgets() {
        $('.imagineer-converter-widget, .imagineer-resize-widget').each(function() {
            const $widget = $(this);
            const $uploadZone = $widget.find('.ic-upload-zone');
            const $fileInput = $widget.find('.ic-file-input');
            const $convertBtn = $widget.find('.ic-convert-button');
            const $result = $widget.find('.ic-widget-result');
            let selectedFile = null;
            
            // File input change handler
            $fileInput.on('change', function(e) {
                e.stopPropagation();
                selectedFile = e.target.files[0];
                if (selectedFile) {
                    $uploadZone.addClass('has-file');
                    $uploadZone.find('p').text('✓ ' + selectedFile.name);
                }
            });
            
            // Prevent file input click from bubbling
            $fileInput.on('click', function(e) {
                e.stopPropagation();
            });
            
            // Drag and drop handlers
            $uploadZone.on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });
            
            $uploadZone.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });
            
            $uploadZone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    // Set files to input
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(files[0]);
                    $fileInput[0].files = dataTransfer.files;
                    
                    // Trigger change event
                    $fileInput.trigger('change');
                }
            });
            
            // Prevent file input click from bubbling to upload zone
            $fileInput.on('click', function(e) {
                e.stopPropagation();
            });
            
            // Drag and drop handlers
            $uploadZone.on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });
            
            $uploadZone.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });
            
            $uploadZone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    // Set the file input
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(files[0]);
                    $fileInput[0].files = dataTransfer.files;
                    
                    // Trigger change event
                    $fileInput.trigger('change');
                }
            });
            
            // Convert button click
            $convertBtn.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (!selectedFile) {
                    if (typeof ImagineerDialog !== 'undefined') {
                        ImagineerDialog.alert('File Required', 'Please select a file to convert.', 'warning');
                    } else {
                        alert(imagineerData.strings.selectFile);
                    }
                    return;
                }
                
                const targetFormat = $(this).data('to');
                const quality = $(this).data('quality') || 80;
                const width = $(this).data('width') || '';
                const height = $(this).data('height') || '';
                
                // Create form data
                const formData = new FormData();
                formData.append('action', 'ic_convert_image');
                formData.append('nonce', imagineerData.nonce);
                formData.append('target_format', targetFormat);
                formData.append('quality', quality);
                formData.append('image', selectedFile);
                
                if (width) {
                    formData.append('resize_width', width);
                }
                if (height) {
                    formData.append('resize_height', height);
                }
                
                // Disable button
                $convertBtn.prop('disabled', true).text(imagineerData.strings.converting);
                
                // AJAX request
                $.ajax({
                    url: imagineerData.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            const spaceSaved = data.original_size - data.size;
                            const spaceText = spaceSaved > 0 
                                ? 'Saved ' + formatFileSize(spaceSaved)
                                : 'Size increased by ' + formatFileSize(Math.abs(spaceSaved));
                            
                            $result.html(
                                '<h4>✅ Conversion Complete!</h4>' +
                                '<p><strong>' + data.filename + '</strong></p>' +
                                '<p>Size: ' + formatFileSize(data.size) + '</p>' +
                                '<p style="color: ' + (spaceSaved > 0 ? 'green' : 'red') + '; font-weight: 600;">' + spaceText + '</p>' +
                                '<button type="button" class="ic-download-link" data-url="' + data.url + '" data-filename="' + data.filename + '">' +
                                'Download ' + data.format.toUpperCase() + ' File' +
                                '</button>'
                            ).addClass('show');
                            
                            $convertBtn.text(imagineerData.strings.success);
                            
                            // Auto-download using fetch+blob method
                            triggerDownload(data.url, data.filename);
                            
                            setTimeout(function() {
                                $convertBtn.prop('disabled', false).text('Convert');
                            }, 3000);
                        } else {
                            const errorMsg = response.data && response.data.message 
                                ? response.data.message 
                                : 'Conversion failed';
                            
                            if (typeof ImagineerDialog !== 'undefined') {
                                ImagineerDialog.alert('Conversion Failed', errorMsg, 'error');
                            } else {
                                alert(imagineerData.strings.error + ': ' + errorMsg);
                            }
                            
                            $convertBtn.prop('disabled', false).text('Convert');
                        }
                    },
                    error: function() {
                        if (typeof ImagineerDialog !== 'undefined') {
                            ImagineerDialog.alert('Error', imagineerData.strings.error, 'error');
                        } else {
                            alert(imagineerData.strings.error);
                        }
                        $convertBtn.prop('disabled', false).text('Convert');
                    }
                });
            });
        });
    }
    
    /**
     * Initialize bulk converter widgets
     */
    function initBulkWidgets() {
        $('.imagineer-bulk-widget').each(function() {
            const $widget = $(this);
            const $uploadZone = $widget.find('.ic-bulk-upload');
            const $fileInput = $widget.find('.ic-bulk-file-input');
            const $convertBtn = $widget.find('.ic-bulk-convert-button');
            const $progress = $widget.find('.ic-bulk-progress');
            const $results = $widget.find('.ic-bulk-results');
            let selectedFiles = [];
            
            // File input change
            $fileInput.on('change', function(e) {
                e.stopPropagation();
                selectedFiles = Array.from(e.target.files);
                if (selectedFiles.length > 0) {
                    $uploadZone.addClass('has-file');
                    $uploadZone.find('p').text('✓ ' + selectedFiles.length + ' files selected');
                }
            });
            
            // Prevent file input click from bubbling
            $fileInput.on('click', function(e) {
                e.stopPropagation();
            });
            
            // Drag and drop handlers for bulk upload
            $uploadZone.on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });
            
            $uploadZone.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });
            
            $uploadZone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    // Set files to input (support multiple files)
                    const dataTransfer = new DataTransfer();
                    for (let i = 0; i < files.length; i++) {
                        dataTransfer.items.add(files[i]);
                    }
                    $fileInput[0].files = dataTransfer.files;
                    
                    // Trigger change event
                    $fileInput.trigger('change');
                }
            });
            
            // Convert button
            $convertBtn.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (selectedFiles.length === 0) {
                    if (typeof ImagineerDialog !== 'undefined') {
                        ImagineerDialog.alert('Files Required', imagineerData.strings.selectFile, 'warning');
                    } else {
                        alert(imagineerData.strings.selectFile);
                    }
                    return;
                }
                
                const targetFormat = $widget.find('.ic-bulk-format').val();
                const quality = $widget.find('.ic-bulk-quality').val();
                const width = $widget.find('.ic-bulk-width').val();
                const height = $widget.find('.ic-bulk-height').val();
                
                // Disable button
                $convertBtn.prop('disabled', true).text('Converting...');
                
                // Show progress
                $progress.addClass('show').html(
                    '<div class="ic-progress-bar">' +
                    '<div class="ic-progress-fill" style="width: 0%"></div>' +
                    '</div>' +
                    '<p class="ic-progress-text">Starting...</p>'
                );
                
                $results.empty();
                
                let completed = 0;
                const total = selectedFiles.length;
                
                function convertNext(index) {
                    if (index >= total) {
                        $progress.find('.ic-progress-text').text('✅ All ' + completed + ' files converted!');
                        $convertBtn.prop('disabled', false).text('Convert All');
                        return;
                    }
                    
                    const file = selectedFiles[index];
                    const formData = new FormData();
                    formData.append('action', 'ic_convert_image');
                    formData.append('nonce', imagineerData.nonce);
                    formData.append('target_format', targetFormat);
                    formData.append('quality', quality);
                    formData.append('image', file);
                    
                    if (width) {
                        formData.append('resize_width', width);
                    }
                    if (height) {
                        formData.append('resize_height', height);
                    }
                    
                    const progressPercent = Math.round((index / total) * 100);
                    $progress.find('.ic-progress-fill').css('width', progressPercent + '%');
                    $progress.find('.ic-progress-text').text('Converting ' + (index + 1) + ' of ' + total + '...');
                    
                    $.ajax({
                        url: imagineerData.ajaxUrl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                completed++;
                                const data = response.data;
                                
                                const $resultItem = $(
                                    '<div class="ic-bulk-result-item">' +
                                    '<p>' + data.filename + '</p>' +
                                    '<button type="button" class="ic-bulk-download-btn" data-url="' + data.url + '" data-filename="' + data.filename + '">Download</button>' +
                                    '</div>'
                                );
                                
                                $results.append($resultItem);
                                
                                // Auto-download using fetch+blob method
                                triggerDownload(data.url, data.filename);
                            }
                            convertNext(index + 1);
                        },
                        error: function() {
                            convertNext(index + 1);
                        }
                    });
                }
                
                convertNext(0);
            });
        });
    }
    
    /**
     * Trigger automatic download using server-side handler
     * This ensures files download instead of opening in browser (works online)
     */
    function triggerDownload(url, filename) {
        // Use server-side download handler for better compatibility
        const downloadUrl = imagineerData.ajaxUrl + '?action=ic_download_file&file=' + encodeURIComponent(url) + 
                           '&filename=' + encodeURIComponent(filename) + 
                           '&nonce=' + encodeURIComponent(imagineerData.downloadNonce || imagineerData.nonce);
        
        // Create hidden iframe for download (works better than link.click())
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = downloadUrl;
        document.body.appendChild(iframe);
        
        // Clean up after download starts
        setTimeout(() => {
            document.body.removeChild(iframe);
        }, 2000);
        
        // Fallback: Try direct link method if iframe doesn't work
        setTimeout(() => {
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.target = '_blank';
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            setTimeout(() => {
                document.body.removeChild(link);
            }, 100);
        }, 100);
    }
    
    // Initialize on document ready
    $(document).ready(function() {
        initConverterWidgets();
        initBulkWidgets();
        
        // Handle download button clicks
        $(document).on('click', '.ic-download-link', function(e) {
            e.preventDefault();
            const url = $(this).data('url');
            const filename = $(this).data('filename');
            if (url && filename) {
                triggerDownload(url, filename);
            }
        });
        
        $(document).on('click', '.ic-bulk-download-btn', function(e) {
            e.preventDefault();
            const url = $(this).data('url');
            const filename = $(this).data('filename');
            if (url && filename) {
                triggerDownload(url, filename);
            }
        });
    });
    
    // Re-initialize on dynamic content load (for AJAX-loaded content)
    $(document).on('imagineer:init', function() {
        initConverterWidgets();
        initBulkWidgets();
    });
    
})(jQuery);
