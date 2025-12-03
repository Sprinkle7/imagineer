/**
 * Imagineer Frontend JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initConverters();
        initBulkConverters();
    });
    
    /**
     * Initialize single converters
     */
    function initConverters() {
        $('.imagineer-converter-widget, .imagineer-resize-widget').each(function() {
            const $widget = $(this);
            const $uploadZone = $widget.find('.ic-upload-zone');
            const $fileInput = $widget.find('.ic-file-input');
            const $convertBtn = $widget.find('.ic-convert-button');
            const $result = $widget.find('.ic-widget-result');
            
            let selectedFile = null;
            
            // Click to browse
            $uploadZone.on('click', function() {
                $fileInput.click();
            });
            
            // File selection
            $fileInput.on('change', function(e) {
                selectedFile = e.target.files[0];
                if (selectedFile) {
                    $uploadZone.addClass('has-file');
                    $uploadZone.find('p').text('✓ ' + selectedFile.name);
                }
            });
            
            // Drag & drop
            $uploadZone.on('dragover', function(e) {
                e.preventDefault();
                $(this).css('border-color', '#667eea');
            });
            
            $uploadZone.on('dragleave', function(e) {
                e.preventDefault();
                $(this).css('border-color', '#ddd');
            });
            
            $uploadZone.on('drop', function(e) {
                e.preventDefault();
                $(this).css('border-color', '#ddd');
                
                selectedFile = e.originalEvent.dataTransfer.files[0];
                if (selectedFile) {
                    $uploadZone.addClass('has-file');
                    $uploadZone.find('p').text('✓ ' + selectedFile.name);
                }
            });
            
            // Convert button
            $convertBtn.on('click', function() {
                if (!selectedFile) {
                    alert(imagineerData.strings.selectFile);
                    return;
                }
                
                const targetFormat = $(this).data('to');
                const quality = $(this).data('quality') || 80;
                const resizeWidth = $(this).data('width') || '';
                const resizeHeight = $(this).data('height') || '';
                
                convertImage(selectedFile, targetFormat, quality, resizeWidth, resizeHeight, $convertBtn, $result);
            });
        });
    }
    
    /**
     * Initialize bulk converters
     */
    function initBulkConverters() {
        $('.imagineer-bulk-widget').each(function() {
            const $widget = $(this);
            const $uploadZone = $widget.find('.ic-bulk-upload');
            const $fileInput = $widget.find('.ic-bulk-file-input');
            const $convertBtn = $widget.find('.ic-bulk-convert-button');
            const $progress = $widget.find('.ic-bulk-progress');
            const $results = $widget.find('.ic-bulk-results');
            
            let selectedFiles = [];
            
            // Click to browse
            $uploadZone.on('click', function() {
                $fileInput.click();
            });
            
            // File selection
            $fileInput.on('change', function(e) {
                selectedFiles = Array.from(e.target.files);
                if (selectedFiles.length > 0) {
                    $uploadZone.addClass('has-file');
                    $uploadZone.find('p').text('✓ ' + selectedFiles.length + ' files selected');
                }
            });
            
            // Convert button
            $convertBtn.on('click', function() {
                if (selectedFiles.length === 0) {
                    alert(imagineerData.strings.selectFile);
                    return;
                }
                
                const format = $widget.find('.ic-bulk-format').val();
                const quality = $widget.find('.ic-bulk-quality').val();
                const width = $widget.find('.ic-bulk-width').val();
                const height = $widget.find('.ic-bulk-height').val();
                
                convertBulk(selectedFiles, format, quality, width, height, $convertBtn, $progress, $results);
            });
        });
    }
    
    /**
     * Convert single image
     */
    function convertImage(file, targetFormat, quality, width, height, $btn, $result) {
        const formData = new FormData();
        formData.append('action', 'ic_convert_image');
        formData.append('nonce', imagineerData.nonce);
        formData.append('target_format', targetFormat);
        formData.append('quality', quality);
        formData.append('image', file);
        
        if (width) formData.append('resize_width', width);
        if (height) formData.append('resize_height', height);
        
        $btn.prop('disabled', true).text(imagineerData.strings.converting);
        
        $.ajax({
            url: imagineerData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showResult(response.data, $result);
                    $btn.text(imagineerData.strings.success);
                    
                    // Auto-download
                    const link = document.createElement('a');
                    link.href = response.data.url;
                    link.download = response.data.filename;
                    link.click();
                    
                    setTimeout(() => {
                        $btn.prop('disabled', false).text('Convert');
                    }, 3000);
                } else {
                    alert(imagineerData.strings.error + ': ' + response.data.message);
                    $btn.prop('disabled', false).text('Convert');
                }
            },
            error: function() {
                alert(imagineerData.strings.error);
                $btn.prop('disabled', false).text('Convert');
            }
        });
    }
    
    /**
     * Convert bulk images
     */
    function convertBulk(files, format, quality, width, height, $btn, $progress, $results) {
        $btn.prop('disabled', true).text('Converting...');
        $progress.addClass('show').html(`
            <div class="ic-progress-bar">
                <div class="ic-progress-fill" style="width: 0%"></div>
            </div>
            <p class="ic-progress-text">Starting...</p>
        `);
        $results.empty();
        
        let completed = 0;
        
        function convertNext(index) {
            if (index >= files.length) {
                $progress.find('.ic-progress-text').text(`✅ All ${completed} files converted!`);
                $btn.prop('disabled', false).text('Convert All');
                return;
            }
            
            const file = files[index];
            const formData = new FormData();
            formData.append('action', 'ic_convert_image');
            formData.append('nonce', imagineerData.nonce);
            formData.append('target_format', format);
            formData.append('quality', quality);
            formData.append('image', file);
            
            if (width) formData.append('resize_width', width);
            if (height) formData.append('resize_height', height);
            
            const percent = Math.round((index / files.length) * 100);
            $progress.find('.ic-progress-fill').css('width', percent + '%');
            $progress.find('.ic-progress-text').text(`Converting ${index + 1} of ${files.length}...`);
            
            $.ajax({
                url: imagineerData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        completed++;
                        showBulkResult(response.data, $results);
                        
                        // Auto-download
                        const link = document.createElement('a');
                        link.href = response.data.url;
                        link.download = response.data.filename;
                        link.click();
                    }
                    convertNext(index + 1);
                },
                error: function() {
                    convertNext(index + 1);
                }
            });
        }
        
        convertNext(0);
    }
    
    /**
     * Show single result
     */
    function showResult(data, $result) {
        const spaceSaved = data.original_size - data.size;
        const spaceSavedText = spaceSaved > 0 
            ? `Saved ${formatFileSize(spaceSaved)}` 
            : `Size increased by ${formatFileSize(Math.abs(spaceSaved))}`;
        
        $result.html(`
            <h4>✅ Conversion Complete!</h4>
            <p><strong>${data.filename}</strong></p>
            <p>Size: ${formatFileSize(data.size)}</p>
            <p style="color: ${spaceSaved > 0 ? 'green' : 'red'}; font-weight: 600;">${spaceSavedText}</p>
            <a href="${data.url}" download="${data.filename}" class="ic-download-link">
                Download ${data.format.toUpperCase()} File
            </a>
        `).addClass('show');
    }
    
    /**
     * Show bulk result
     */
    function showBulkResult(data, $results) {
        const $item = $(`
            <div class="ic-bulk-result-item">
                <p>${data.filename}</p>
                <a href="${data.url}" download="${data.filename}">Download</a>
            </div>
        `);
        $results.append($item);
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

