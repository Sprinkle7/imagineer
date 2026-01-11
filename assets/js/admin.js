/**
 * Image Converter Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Toast Notification System
    window.ImagineerToast = {
        container: null,
        
        init() {
            if (!this.container) {
                this.container = $('<div class="ic-toast-container"></div>');
                $('body').append(this.container);
            }
        },
        
        show(type, title, message, duration = 5000) {
            this.init();
            
            const icons = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ'
            };
            
            const toast = $(`
                <div class="ic-toast ${type}">
                    <div class="ic-toast-icon">${icons[type]}</div>
                    <div class="ic-toast-content">
                        <div class="ic-toast-title">${title}</div>
                        ${message ? `<div class="ic-toast-message">${message}</div>` : ''}
                    </div>
                    <button class="ic-toast-close">×</button>
                </div>
            `);
            
            this.container.append(toast);
            
            // Auto remove
            if (duration > 0) {
                setTimeout(() => this.remove(toast), duration);
            }
            
            // Manual close
            toast.find('.ic-toast-close').on('click', () => this.remove(toast));
            
            return toast;
        },
        
        remove(toast) {
            toast.addClass('removing');
            setTimeout(() => toast.remove(), 300);
        },
        
        success(title, message, duration) {
            return this.show('success', title, message, duration);
        },
        
        error(title, message, duration) {
            return this.show('error', title, message, duration);
        },
        
        warning(title, message, duration) {
            return this.show('warning', title, message, duration);
        },
        
        info(title, message, duration) {
            return this.show('info', title, message, duration);
        }
    };
    
    // Professional Dialog System
    window.ImagineerDialog = {
        overlay: null,
        dialog: null,
        
        init() {
            if (!this.overlay) {
                this.overlay = $('<div class="ic-dialog-overlay"></div>');
                this.dialog = $('<div class="ic-dialog"></div>');
                this.overlay.append(this.dialog);
                $('body').append(this.overlay);
                
                // Close on overlay click
                this.overlay.on('click', (e) => {
                    if ($(e.target).hasClass('ic-dialog-overlay')) {
                        this.close();
                    }
                });
                
                // Close on ESC key
                $(document).on('keydown', (e) => {
                    if (e.key === 'Escape' && this.overlay.hasClass('show')) {
                        this.close();
                    }
                });
            }
        },
        
        show(options) {
            this.init();
            
            const {
                title = 'Notice',
                message = '',
                type = 'info', // info, success, error, warning
                buttons = [],
                showClose = true,
                onClose = null
            } = options;
            
            const icons = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ'
            };
            
            const iconColors = {
                success: 'success',
                error: 'error',
                warning: 'warning',
                info: 'info'
            };
            
            let buttonsHtml = '';
            if (buttons.length > 0) {
                buttonsHtml = '<div class="ic-dialog-footer">';
                buttons.forEach(btn => {
                    const btnClass = btn.class || 'ic-dialog-btn-secondary';
                    buttonsHtml += `<button class="ic-dialog-btn ${btnClass}" data-action="${btn.action || ''}">${btn.text}</button>`;
                });
                buttonsHtml += '</div>';
            }
            
            const dialogHtml = `
                <div class="ic-dialog-header">
                    <h3>${title}</h3>
                    ${showClose ? '<button class="ic-dialog-close" aria-label="Close">×</button>' : ''}
                </div>
                <div class="ic-dialog-body">
                    ${type ? `<div class="ic-dialog-icon ${iconColors[type]}">${icons[type]}</div>` : ''}
                    <div>${message}</div>
                </div>
                ${buttonsHtml}
            `;
            
            this.dialog.html(dialogHtml);
            
            // Attach button handlers
            this.dialog.find('.ic-dialog-btn').on('click', (e) => {
                const action = $(e.target).data('action');
                if (action && options.onButtonClick) {
                    options.onButtonClick(action);
                }
                this.close();
            });
            
            // Close button
            this.dialog.find('.ic-dialog-close').on('click', () => {
                this.close();
                if (onClose) onClose();
            });
            
            // Show dialog
            setTimeout(() => {
                this.overlay.addClass('show');
            }, 10);
            
            return this;
        },
        
        alert(title, message, type = 'info') {
            return this.show({
                title: title,
                message: message,
                type: type,
                buttons: [{
                    text: 'OK',
                    class: 'ic-dialog-btn-primary',
                    action: 'ok'
                }]
            });
        },
        
        confirm(title, message, onConfirm, onCancel) {
            return this.show({
                title: title,
                message: message,
                type: 'warning',
                buttons: [
                    {
                        text: 'Cancel',
                        class: 'ic-dialog-btn-secondary',
                        action: 'cancel'
                    },
                    {
                        text: 'Confirm',
                        class: 'ic-dialog-btn-primary',
                        action: 'confirm'
                    }
                ],
                onButtonClick: (action) => {
                    if (action === 'confirm' && onConfirm) {
                        onConfirm();
                    } else if (action === 'cancel' && onCancel) {
                        onCancel();
                    }
                }
            });
        },
        
        close() {
            this.overlay.removeClass('show');
            setTimeout(() => {
                this.dialog.html('');
            }, 200);
        }
    };
    
    let selectedFiles = [];
    let isConverting = false;
    let beforeImage = null;
    let afterImage = null;
    
    $(document).ready(function() {
        initUploadArea();
        initControls();
        initComparisonSlider();
    });
    
    /**
     * Initialize upload area
     */
    function initUploadArea() {
        const $uploadArea = $('#ic-upload-area');
        const $fileInput = $('#ic-file-input');
        const maxFiles = icData.capabilities.bulk_processing ? -1 : 1;
        
        // Click to browse
        $uploadArea.on('click', function(e) {
            if (!isConverting) {
                // Prevent event from being blocked by child elements
                e.stopPropagation();
                $fileInput[0].click();
            }
        });
        
        // Also allow direct clicks on file input
        $fileInput.on('click', function(e) {
            e.stopPropagation();
        });
        
        // Drag and drop
        $uploadArea.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag-over');
        });
        
        $uploadArea.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
        });
        
        $uploadArea.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
            
            if (isConverting) return;
            
            const files = e.originalEvent.dataTransfer.files;
            handleFiles(files);
        });
        
        // File input change
        $fileInput.on('change', function(e) {
            if (isConverting) return;
            handleFiles(e.target.files);
        });
    }
    
    /**
     * Handle selected files
     */
    function handleFiles(files) {
        selectedFiles = Array.from(files);
        
        // Check file count limit for free version
        if (!icData.capabilities.bulk_processing && selectedFiles.length > 1) {
            showError(icData.strings.upgradeRequired + ' ' + icData.strings.selectFormat);
            selectedFiles = [selectedFiles[0]]; // Keep only first file
        }
        
        // Validate file sizes
        const maxSize = icData.maxFileSize;
        const invalidFiles = selectedFiles.filter(file => file.size > maxSize);
        
        if (invalidFiles.length > 0) {
            showError(icData.strings.fileTooLarge);
            selectedFiles = selectedFiles.filter(file => file.size <= maxSize);
        }
        
        if (selectedFiles.length > 0) {
            updateUploadArea();
        }
    }
    
    /**
     * Update upload area display
     */
    function updateUploadArea() {
        const $uploadArea = $('#ic-upload-area');
        const count = selectedFiles.length;
        const text = count === 1 
            ? `${selectedFiles[0].name} (${formatFileSize(selectedFiles[0].size)})`
            : `${count} files selected`;
        
        $uploadArea.find('h3').text(text);
        $uploadArea.find('p').first().text('Click to change files');
    }
    
    /**
     * Initialize controls
     */
    function initControls() {
        // Quality slider
        const $qualitySlider = $('#ic-quality[type="range"]');
        if ($qualitySlider.length) {
            $qualitySlider.on('input', function() {
                $('#ic-quality-value').text($(this).val());
            });
        }
        
        // Convert button
        $('#ic-convert-btn').on('click', function() {
            if (selectedFiles.length === 0) {
                showError('Please select at least one image.');
                return;
            }
            
            convertImages();
        });
    }
    
    /**
     * Convert images
     */
    function convertImages() {
        if (isConverting) return;
        
        isConverting = true;
        const $convertBtn = $('#ic-convert-btn');
        const $progress = $('#ic-progress');
        const $progressFill = $('#ic-progress-fill');
        const $progressText = $('#ic-progress-text');
        const $results = $('#ic-results');
        
        $convertBtn.prop('disabled', true).text(icData.strings.converting);
        $progress.show();
        $results.empty();
        
        const targetFormat = $('#ic-target-format').val();
        const quality = $('#ic-quality').val() || $('#ic-quality').find('option:selected').val();
        const resizeWidth = $('#ic-resize-width').val() || '';
        const resizeHeight = $('#ic-resize-height').val() || '';
        
        // Store original file sizes for space calculation
        selectedFiles.forEach(file => {
            file.originalSize = file.size;
        });
        
        // Single file conversion
        if (selectedFiles.length === 1 || !icData.capabilities.bulk_processing) {
            convertSingleFile(selectedFiles[0], targetFormat, quality, resizeWidth, resizeHeight, $progressFill, $progressText, $results);
        } else {
            // Bulk conversion (Pro only)
            convertBulkFiles(selectedFiles, targetFormat, quality, resizeWidth, resizeHeight, $progressFill, $progressText, $results);
        }
    }
    
    /**
     * Convert single file
     */
    function convertSingleFile(file, targetFormat, quality, resizeWidth, resizeHeight, $progressFill, $progressText, $results) {
        const formData = new FormData();
        formData.append('action', 'ic_convert_image');
        formData.append('nonce', icData.nonce);
        formData.append('target_format', targetFormat);
        formData.append('quality', quality);
        if (resizeWidth) formData.append('resize_width', resizeWidth);
        if (resizeHeight) formData.append('resize_height', resizeHeight);
        formData.append('image', file);
        
        $progressFill.css('width', '30%');
        $progressText.text(icData.strings.uploading);
        
        $.ajax({
            url: icData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $progressFill.css('width', '100%');
                    $progressText.text(icData.strings.success);
                    
                    setTimeout(() => {
                        // Show comparison slider for single image
                        if (selectedFiles.length === 1 && response.data.url) {
                            const originalUrl = URL.createObjectURL(selectedFiles[0]);
                            showComparisonSlider(
                                originalUrl,
                                response.data.url,
                                response.data.original_size || selectedFiles[0].size,
                                response.data.size
                            );
                            ImagineerToast.success('Conversion Complete!', 'Drag the slider to compare');
                        } else {
                            displayResult(response.data, $results);
                            $progressText.text(icData.strings.downloaded || 'Downloaded!');
                            setTimeout(() => {
                                resetConverter();
                            }, 2000);
                        }
                    }, 500);
                } else {
                    handleError(response.data, $results);
                }
            },
            error: function(xhr, status, error) {
                handleError({ message: error }, $results);
            }
        });
    }
    
    /**
     * Convert bulk files (Pro)
     */
    function convertBulkFiles(files, targetFormat, quality, resizeWidth, resizeHeight, $progressFill, $progressText, $results) {
        let completed = 0;
        const total = files.length;
        const convertedFiles = [];
        
        $progressText.text(`Processing ${total} files...`);
        
        // Convert files one by one for better reliability
        function convertNext(index) {
            if (index >= total) {
                $progressFill.css('width', '100%');
                $progressText.text(`✅ All ${total} files converted!`);
                
                // Offer ZIP download if multiple files
                if (convertedFiles.length > 1) {
                    createZipDownload(convertedFiles, $results);
                }
                
                setTimeout(() => {
                    resetConverter();
                }, 5000);
                return;
            }
            
            const file = files[index];
            const formData = new FormData();
            formData.append('action', 'ic_convert_image');
            formData.append('nonce', icData.nonce);
            formData.append('target_format', targetFormat);
            formData.append('quality', quality);
            if (resizeWidth) formData.append('resize_width', resizeWidth);
            if (resizeHeight) formData.append('resize_height', resizeHeight);
            formData.append('image', file);
            
            const progress = Math.round((index / total) * 100);
            $progressFill.css('width', progress + '%');
            $progressText.text(`Converting ${index + 1} of ${total}...`);
            
            $.ajax({
                url: icData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        completed++;
                        convertedFiles.push(response.data);
                        displayResult(response.data, $results);
                        // Continue to next file
                        convertNext(index + 1);
                    } else {
                        showError(`Error converting ${file.name}: ${response.data.message}`);
                        // Continue anyway
                        convertNext(index + 1);
                    }
                },
                error: function() {
                    showError(`Error converting ${file.name}`);
                    convertNext(index + 1);
                }
            });
        }
        
        // Start converting
        convertNext(0);
    }
    
    /**
     * Create ZIP download for bulk conversions
     */
    function createZipDownload(files, $results) {
        $.ajax({
            url: icData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ic_download_zip',
                nonce: icData.nonce,
                files: JSON.stringify(files)
            },
            success: function(response) {
                if (response.success) {
                    // Add ZIP download button
                    const $zipBtn = $('<div class="ic-zip-download"></div>');
                    $zipBtn.html(`
                        <h3>📦 Download All as ZIP</h3>
                        <p>All ${files.length} files in one download (${formatFileSize(response.data.size)})</p>
                        <button type="button" class="button button-primary button-large" id="ic-zip-download-btn" data-url="${response.data.url}" data-filename="${response.data.filename}">
                            Download ZIP File
                        </button>
                    `);
                    $results.prepend($zipBtn);
                    
                    // Handle ZIP download button click
                    $('#ic-zip-download-btn').on('click', function(e) {
                        e.preventDefault();
                        const url = $(this).data('url');
                        const filename = $(this).data('filename');
                        triggerDownload(url, filename);
                    });
                    
                    // Auto-trigger ZIP download
                    triggerDownload(response.data.url, response.data.filename);
                }
            }
        });
    }
    
    /**
     * Display conversion result
     */
    function displayResult(data, $results) {
        const $item = $('<div class="ic-result-item"></div>');
        
        // Add status badge
        const $badge = $('<div class="ic-status-badge">✓ Converted</div>');
        $item.append($badge);
        
        const $img = $('<img>').attr('src', data.url).attr('alt', data.filename);
        
        // Calculate space saved if original size is available
        let spaceSavedHtml = '';
        if (data.original_size && data.size) {
            const spaceSaved = data.original_size - data.size;
            const percentage = Math.round((spaceSaved / data.original_size) * 100);
            const color = spaceSaved > 0 ? 'green' : 'red';
            const symbol = spaceSaved > 0 ? '↓' : '↑';
            
            spaceSavedHtml = `<p style="margin: 5px 0; font-size: 12px; color: ${color}; font-weight: bold;">
                ${symbol} ${formatFileSize(Math.abs(spaceSaved))} (${Math.abs(percentage)}%)
            </p>`;
        }
        
        // Ensure filename has proper extension for download button
        let downloadFilename = data.filename;
        if (!downloadFilename || downloadFilename.endsWith('.undefined')) {
            const targetFormat = $('#ic-target-format').val() || 'jpg';
            const baseName = selectedFiles.length > 0 ? selectedFiles[0].name.replace(/\.[^/.]+$/, '') : 'converted';
            downloadFilename = baseName + '.' + targetFormat;
        }
        
        const $download = $('<button>')
            .addClass('ic-download-btn')
            .attr('type', 'button')
            .text(icData.strings.download || 'Download')
            .on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                triggerDownload(data.url, downloadFilename);
            });
        
        $item.append($img);
        $item.append($('<p style="margin: 10px 0 5px; font-weight: 600;"></p>').text(data.filename));
        $item.append($('<p style="margin: 0 0 5px; font-size: 12px; color: #666;"></p>').text('New size: ' + formatFileSize(data.size)));
        if (spaceSavedHtml) {
            $item.append($(spaceSavedHtml));
        }
        $item.append($download);
        
        $results.append($item);
        
        // Auto-download the file (using the downloadFilename already set above)
        triggerDownload(data.url, downloadFilename);
    }
    
    /**
     * Trigger automatic download
     * Uses server-side download handler for reliable downloads (works online)
     */
    function triggerDownload(url, filename) {
        // Use server-side download handler for better compatibility
        const downloadNonce = icData.downloadNonce || icData.nonce;
        const downloadUrl = icData.ajaxUrl + '?action=ic_download_file&file=' + encodeURIComponent(url) + 
                           '&filename=' + encodeURIComponent(filename) + 
                           '&nonce=' + encodeURIComponent(downloadNonce);
        
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
    
    /**
     * Handle errors
     */
    function handleError(data, $results) {
        const message = data.message || icData.strings.error;
        showError(message);
        
        if (data.upgrade) {
            const $upgrade = $('<div class="ic-upgrade-banner" style="margin-top: 20px;"><h3>Upgrade to Pro</h3><p>' + message + '</p><a href="#" class="button button-primary">Get Pro Version</a></div>');
            $results.append($upgrade);
        }
        
        resetConverter();
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        ImagineerToast.error('Error', message);
    }
    
    function showSuccess(message) {
        ImagineerToast.success('Success', message);
    }
    
    function showInfo(message) {
        ImagineerToast.info('Info', message);
    }
    
    /**
     * Initialize comparison slider
     */
    function initComparisonSlider() {
        let isDragging = false;
        
        $(document).on('mousedown touchstart', '.ic-comparison-handle', function(e) {
            isDragging = true;
            e.preventDefault();
        });
        
        $(document).on('mousemove touchmove', function(e) {
            if (!isDragging) return;
            
            const $slider = $('.ic-comparison-slider');
            if (!$slider.length) return;
            
            const containerRect = $slider[0].getBoundingClientRect();
            const clientX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
            let position = ((clientX - containerRect.left) / containerRect.width) * 100;
            position = Math.max(0, Math.min(100, position));
            
            $('.ic-comparison-after').css('clip-path', `inset(0 ${100 - position}% 0 0)`);
            $('.ic-comparison-handle').css('left', position + '%');
        });
        
        $(document).on('mouseup touchend', function() {
            isDragging = false;
        });
        
        // Download buttons
        $(document).on('click', '#ic-download-converted', function(e) {
            e.preventDefault();
            if (afterImage) {
                const targetFormat = $('#ic-target-format').val() || 'jpg';
                const filename = 'converted-image.' + targetFormat;
                triggerDownload(afterImage, filename);
                ImagineerToast.success('Downloaded!', 'Converted image saved');
            }
        });
        
        $(document).on('click', '#ic-convert-another', function() {
            $('.ic-comparison-container').removeClass('active');
            resetConverter();
        });
    }
    
    /**
     * Show comparison slider
     */
    function showComparisonSlider(original, converted, originalSize, convertedSize) {
        beforeImage = original;
        afterImage = converted;
        
        const sizeSaved = originalSize - convertedSize;
        const percentSaved = ((sizeSaved / originalSize) * 100).toFixed(1);
        
        const html = `
            <div class="ic-comparison-header">
                <h3 class="ic-comparison-title">🎨 Conversion Complete!</h3>
                <div class="ic-comparison-stats">
                    <div class="ic-comparison-stat">
                        <strong>${formatFileSize(sizeSaved)}</strong> saved (${percentSaved}%)
                    </div>
                </div>
            </div>
            <div class="ic-comparison-slider">
                <div class="ic-comparison-before">
                    <img src="${original}" alt="Before" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div class="ic-comparison-after" style="clip-path: inset(0 50% 0 0);">
                    <img src="${converted}" alt="After" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div class="ic-comparison-handle" style="left: 50%;"></div>
                <div class="ic-comparison-labels">
                    <span class="ic-comparison-label">Before</span>
                    <span class="ic-comparison-label">After</span>
                </div>
            </div>
            <div class="ic-comparison-actions">
                <button class="ic-comparison-btn ic-comparison-btn-primary" id="ic-download-converted">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M8 1v10M4 8l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 15h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    Download Converted
                </button>
                <button class="ic-comparison-btn ic-comparison-btn-secondary" id="ic-convert-another">
                    Convert Another
                </button>
            </div>
        `;
        
        let $container = $('.ic-comparison-container');
        if (!$container.length) {
            $container = $('<div class="ic-comparison-container"></div>');
            $('.ic-converter-container').after($container);
        }
        
        $container.html(html).addClass('active');
        
        // Scroll to comparison
        $('html, body').animate({
            scrollTop: $container.offset().top - 100
        }, 500);
    }
    
    /**
     * Reset converter
     */
    function resetConverter() {
        isConverting = false;
        selectedFiles = [];
        $('#ic-file-input').val('');
        $('#ic-convert-btn').prop('disabled', false).text('Convert');
        $('#ic-upload-area').find('h3').text('Drag & Drop Images Here');
        $('#ic-upload-area').find('p').first().text('or click to browse');
        $('#ic-progress').hide();
        $('#ic-progress-fill').css('width', '0%');
    }
    
    /**
     * Format file size
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
})(jQuery);

