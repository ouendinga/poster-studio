/**
 * GEM Poster Studio - Frontend Logic
 * Version 1.6 - Intuitive Zoom & Formatting
 */
(function ($) {
    'use strict';

    var PosterStudio = {
        userZoom: 1, // Multiplier for the auto-fit scale

        init: function () {
            this.bindEvents();
            this.adjustPreviewScale();
            this.initQRCode();
            this.updatePreviewFromSidebar();
        },

        bindEvents: function () {
            var self = this;
            var $container = $('#gem-poster-studio-app');
            var $workspace = $('.pdf-editor-workspace');

            // Text Sync
            $container.on('input', '#edit-title', function () {
                var val = $(this).val().toUpperCase();
                $('#preview-title').text(val);
            });

            $container.on('input', '#edit-date', function () {
                $('#preview-date').text($(this).val());
            });

            $container.on('input', '#edit-details', function () {
                var val = $(this).val().replace(/\n/g, '<br>');
                $('#preview-details').html(val);
            });

            // Styling Controls
            $container.on('change', '#pdf-font-family', function () {
                var font = $(this).val();
                $('#pdf-canvas').css('font-family', font);
            });

            $container.on('input', '#pdf-title-size', function () {
                var size = $(this).val();
                $('#preview-title').css('font-size', size + 'pt');
                $('#val-title-size').text(size);
            });

            $container.on('input', '#pdf-image-height', function () {
                var newH = $(this).val();
                var oldH = $('.pdf-preview-image-container').css('height');

                // Aplicar temporalmente para medir
                $('.pdf-preview-image-container').css('height', newH + 'mm');

                var $canvas = $('#pdf-canvas');
                var canvasHeight = $canvas.outerHeight();
                var $page = $('.pdf-preview-page');

                // Calculamos el final del contenido real
                // Usamos el fondo del último elemento visible para saber si toca el borde
                var contentBottom = 0;
                $page.find('*').each(function () {
                    var bottom = $(this).position().top + $(this).outerHeight(true);
                    if (bottom > contentBottom) contentBottom = bottom;
                });

                if (contentBottom > canvasHeight) {
                    // Si desborda, no permitimos aumentar más. 
                    // Revertimos al valor anterior si el nuevo es mayor
                    var prevH = parseFloat(oldH) / 3.78; // px to mm approx
                    if (parseFloat(newH) > prevH) {
                        $(this).val(Math.floor(prevH));
                        $('.pdf-preview-image-container').css('height', oldH);
                        $('#val-image-height').text(Math.floor(prevH));
                        return;
                    }
                }

                $('#val-image-height').text(newH);
            });

            $container.on('input', '#pdf-image-width', function () {
                var newW = $(this).val();
                $('.pdf-preview-image-container').css('width', newW + 'mm');
                $('#val-image-width').text(newW);
            });

            // Page Size Selection
            $container.on('change', '#pdf-page-size', function () {
                var size = $(this).val().toLowerCase();
                $('#pdf-canvas').removeClass('size-a3 size-a4').addClass('size-' + size);
                self.adjustPreviewScale();
            });

            // ZOOM LOGIC - Buttons
            $container.on('click', '#zoom-in', function () { self.changeZoom(0.2); });
            $container.on('click', '#zoom-out', function () { self.changeZoom(-0.2); });
            $container.on('click', '#zoom-reset', function () { self.resetZoom(); });

            // ZOOM LOGIC - Mouse Wheel (Maps style)
            $workspace.on('wheel', function (e) {
                if (e.ctrlKey || e.metaKey || $workspace.is(':hover')) {
                    e.preventDefault();
                    var delta = e.originalEvent.deltaY > 0 ? -0.1 : 0.1;
                    self.changeZoom(delta);
                }
            });

            // Image Dragging (Vertical Framing)
            var isDragging = false;
            var startY, startTop;

            $container.on('mousedown', '#pdf-preview-image', function (e) {
                isDragging = true;
                startY = e.pageY;
                startTop = parseInt($(this).css('top'), 10) || 0;
                $(this).css('cursor', 'grabbing');
                e.preventDefault();
            });

            $(document).on('mousemove', function (e) {
                if (!isDragging) return;
                var deltaY = (e.pageY - startY) / self.userZoom; // Use userZoom to normalize speed
                var newTop = startTop + deltaY;
                if (newTop > 0) newTop = 0;
                $('#pdf-preview-image').css('top', newTop + 'px');
            });

            $(document).on('mouseup', function () {
                if (isDragging) {
                    isDragging = false;
                    $('#pdf-preview-image').css('cursor', 'ns-resize');
                }
            });

            // PDF Generation
            $container.on('click', '#pdf-generate-final', function (e) {
                self.handleGeneratePDF(e);
            });

            // Window Resize scaling
            $(window).on('resize', function () {
                self.adjustPreviewScale();
            });
        },

        changeZoom: function (delta) {
            this.userZoom += delta;
            // Limit zoom
            if (this.userZoom < 0.2) this.userZoom = 0.2;
            if (this.userZoom > 3) this.userZoom = 3;
            this.adjustPreviewScale();
        },

        resetZoom: function () {
            this.userZoom = 1;
            this.adjustPreviewScale();
        },

        initQRCode: function () {
            var $app = $('#gem-poster-studio-app');
            var url = $app.data('post-url');
            var status = $app.data('post-status');
            var $qrContainer = $('#preview-qr');

            if ((status === 'publish' || status === 'future') && url) {
                var qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' + encodeURIComponent(url);
                $qrContainer.html('<img src="' + qrUrl + '" alt="QR Code Link">');
            } else {
                $qrContainer.html('<div style="color:#999; font-size:12px; padding-top:40px;">QR NOMÉS EN<br>PÀGINA PUBLICADA</div>');
            }
        },

        updatePreviewFromSidebar: function () {
            // Sync initial values
            $('#preview-title').text($('#edit-title').val().toUpperCase());
            $('#preview-date').text($('#edit-date').val());
            var details = $('#edit-details').val().replace(/\n/g, '<br>');
            $('#preview-details').html(details);

            var imgH = $('#pdf-image-height').val();
            $('.pdf-preview-image-container').css('height', imgH + 'mm');
        },

        adjustPreviewScale: function () {
            var $workspace = $('.pdf-editor-workspace');
            var $canvas = $('#pdf-canvas');

            if (!$workspace.length || !$canvas.length) return;

            var padding = 120;
            var availableWidth = $workspace.width() - padding;
            var canvasWidth = $canvas.outerWidth();

            // Calculate "fit" scale
            var fitScale = availableWidth / canvasWidth;

            var availableHeight = $workspace.height() - padding;
            var canvasHeight = $canvas.outerHeight();
            var fitScaleH = availableHeight / canvasHeight;

            if (fitScaleH < fitScale) fitScale = fitScaleH;

            // Apply user zoom over fit scale
            var finalScale = fitScale * this.userZoom;

            $canvas.css({
                'transform': 'scale(' + finalScale + ')',
                'transform-origin': 'center top',
                'margin-bottom': (canvasHeight * (finalScale - 1)) + 'px' // Adjust for space reduction at bottom if any
            });

            // Center the canvas vertically if it's smaller than the workspace
            if (canvasHeight * finalScale < availableHeight) {
                $workspace.css('align-items', 'center');
            } else {
                $workspace.css('align-items', 'flex-start');
            }
        },

        handleGeneratePDF: function (e) {
            e.preventDefault();
            var self = this;
            var $app = $('#gem-poster-studio-app');
            var $loading = $('.pdf-loading-indicator');
            var $btn = $(e.currentTarget);
            var $canvas = $('#pdf-canvas');

            var customOptions = {
                title: $('#edit-title').val(),
                date_text: $('#edit-date').val(),
                details: $('#edit-details').val(),
                font_family: $('#pdf-font-family').val(),
                title_size: $('#pdf-title-size').val(),
                image_height: $('#pdf-image-height').val(),
                image_width: $('#pdf-image-width').val(),
                image_top: $('#pdf-preview-image').css('top'),
                page_size: $('#pdf-page-size').val()
            };

            $loading.addClass('active');
            $btn.prop('disabled', true);

            // 1. Reset zoom to 1 before capturing to avoid scaling artifacts
            var originalZoom = self.userZoom;
            self.userZoom = 1;
            self.adjustPreviewScale();

            // 2. Capture with html2canvas (Scale for high DPI/Print quality)
            // Temporarily hide shadow and FORCE transform to none for 1:1 pixel capture
            var originalStyle = $canvas.attr('style');
            $canvas.css({
                'transform': 'none',
                'transform-origin': 'unset',
                'margin': '0',
                'box-shadow': 'none'
            });

            html2canvas($canvas[0], {
                scale: 3, // High resolution
                useCORS: true,
                logging: false,
                backgroundColor: '#ffffff'
            }).then(function (capturedCanvas) {
                var imageData = capturedCanvas.toDataURL('image/jpeg', 0.98);

                // Restore original styles (including zoom transform)
                $canvas.attr('style', originalStyle);
                self.adjustPreviewScale(); // Re-apply current zoom

                // 3. Send to server
                $.ajax({
                    url: posterStudio.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'poster_studio_generate_pdf',
                        post_id: $app.data('post-id'),
                        nonce: posterStudio.nonce,
                        custom_options: customOptions,
                        image_data: imageData // New: the actual visual capture
                    },
                    success: function (response) {
                        $loading.removeClass('active');
                        $btn.prop('disabled', false);

                        if (response.success && response.data.url) {
                            var link = document.createElement('a');
                            link.href = response.data.url;
                            var fileName = response.data.url.split('/').pop();
                            link.download = fileName;
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        } else {
                            alert(response.data.message || 'Error desconegut generant el PDF.');
                        }
                    },
                    error: function (xhr, status, error) {
                        $loading.removeClass('active');
                        $btn.prop('disabled', false);
                        console.error('PosterStudio Error:', error, status, xhr.responseText);
                        alert('Error crític de comunicació amb el servidor. Revisa la consola o els logs.');
                    }
                });
            }).catch(function (err) {
                $loading.removeClass('active');
                $btn.prop('disabled', false);
                // Restore zoom if error
                self.userZoom = originalZoom;
                self.adjustPreviewScale();
                console.error('Capture Error:', err);
                alert('Error al capturar la imatge del cartell.');
            });
        }
    };

    $(document).ready(function () {
        PosterStudio.init();
    });

})(jQuery);
