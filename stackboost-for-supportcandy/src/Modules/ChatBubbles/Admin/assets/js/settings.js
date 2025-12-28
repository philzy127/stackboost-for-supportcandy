(function($) {
    $(document).ready(function() {

        // 1. Initialize Color Pickers
        $('.my-color-field').wpColorPicker({
            change: function(event, ui) {
                updatePreview();
            }
        });

        // 2. Toggle Custom Fields based on Theme Selection
        $(document).on('change', '.sb-chat-theme-selector', function() {
            var val = $(this).val();
            var type = $(this).data('type');
            var $customFields = $('#sb_chat_custom_fields_' + type);

            if (val === 'custom') {
                $customFields.fadeIn();
            } else {
                $customFields.hide();
            }
            updatePreview();
        });

        // 3. Tab Switching
        // When clicking a tab, hide other tab contents and show current
        $('.nav-tab-wrapper a').on('click', function(e) {
            e.preventDefault();

            // Remove active class from all tabs
            $('.nav-tab-wrapper a').removeClass('nav-tab-active');
            // Add active class to clicked tab
            $(this).addClass('nav-tab-active');

            // Get target tab
            var url = new URL($(this).attr('href'), window.location.origin);
            var targetTab = url.searchParams.get('tab');

            // Update hidden input
            $('input[name="active_tab"]').val(targetTab);

            // Toggle visibility
            $('div[id^="tab-content-"]').hide();
            $('#tab-content-' + targetTab).show();

            // Update URL (optional, for bookmarking/reload)
            window.history.pushState(null, '', $(this).attr('href'));

            updatePreview();
        });

        // 4. Live Preview Logic
        $('form input, form select').on('change input', function() {
            updatePreview();
        });

        function updatePreview() {
            // Determine active tab to preview
            var activeTab = $('input[name="active_tab"]').val() || 'agent';

            // Find inputs for the active tab
            // Prefix: stackboost_settings[chat_bubbles_{activeTab}_{field}]
            var prefixName = 'stackboost_settings[chat_bubbles_' + activeTab + '_';

            var theme = $('select[name="' + prefixName + 'theme]"]').val();
            var $preview = $('#stackboost-chat-preview-bubble');

            // Default Values
            var styles = {
                bg: '#f1f1f1',
                text: '#333333',
                font: '',
                fontSize: '',
                align: 'left',
                width: '85',
                radius: '15',
                tail: 'none'
            };

            // Apply Theme Logic
            if (theme === 'stackboost') {
                if (activeTab === 'agent') {
                    styles.bg = '#2271b1';
                    styles.text = '#ffffff';
                    styles.align = 'right';
                } else {
                    styles.bg = '#f0f0f1';
                    styles.text = '#3c434a';
                    styles.align = 'left';
                }
                styles.font = '-apple-system, BlinkMacSystemFont, sans-serif';
                styles.radius = '15';
                styles.tail = 'round';

            } else if (theme === 'ios') {
                if (activeTab === 'agent') {
                    styles.bg = '#007aff';
                    styles.text = '#ffffff';
                    styles.align = 'right';
                } else {
                    styles.bg = '#e5e5ea';
                    styles.text = '#000000';
                    styles.align = 'left';
                }
                styles.radius = '20';
                styles.tail = 'round';
                styles.width = '75';

            } else if (theme === 'android') {
                if (activeTab === 'agent') {
                    styles.bg = '#d9fdd3';
                    styles.text = '#111b21';
                    styles.align = 'right';
                } else {
                    styles.bg = '#ffffff';
                    styles.text = '#111b21';
                    styles.align = 'left';
                }
                styles.radius = '8';
                styles.tail = 'sharp';
                styles.width = '80';

            } else if (theme === 'modern') {
                if (activeTab === 'agent') {
                    styles.bg = '#000000';
                    styles.text = '#ffffff';
                    styles.align = 'right';
                } else {
                    styles.bg = '#f2f2f2';
                    styles.text = '#000000';
                    styles.align = 'left';
                }
                styles.radius = '0';
                styles.tail = 'none';
                styles.width = '60';

            } else if (theme === 'supportcandy') {
                // Use dynamic SC color passed from PHP, or default blue
                var scPrimary = (typeof stackboostChatBubbles !== 'undefined' && stackboostChatBubbles.scPrimaryColor)
                    ? stackboostChatBubbles.scPrimaryColor
                    : '#2271b1';

                if (activeTab === 'agent') {
                    styles.bg = scPrimary;
                    styles.text = '#ffffff';
                    styles.align = 'right';
                } else {
                    styles.bg = '#e5e5e5';
                    styles.text = '#333333';
                    styles.align = 'left';
                }
                styles.radius = '5';
                styles.tail = 'none';
                styles.width = '85';

            } else if (theme === 'custom') {
                styles.bg = $('input[name="' + prefixName + 'bg_color]"]').val();
                styles.text = $('input[name="' + prefixName + 'text_color]"]').val();
                styles.font = $('select[name="' + prefixName + 'font_family]"]').val();
                styles.fontSize = $('input[name="' + prefixName + 'font_size]"]').val();
                styles.align = $('select[name="' + prefixName + 'alignment]"]').val();
                styles.width = $('input[name="' + prefixName + 'width]"]').val();
                styles.radius = $('input[name="' + prefixName + 'radius]"]').val();
                styles.tail = $('select[name="' + prefixName + 'tail]"]').val();
            }

            // Apply Styles to Preview Element
            var cssMap = {
                'background-color': styles.bg,
                'color': styles.text,
                'border-radius': styles.radius + 'px',
                'width': styles.width + '%',
                'font-family': styles.font,
                'padding': '15px'
            };

            if (styles.fontSize) {
                cssMap['font-size'] = styles.fontSize + 'px';
            } else {
                cssMap['font-size'] = ''; // Reset
            }

            $preview.css(cssMap);

            // Alignment
            if (styles.align === 'right') {
                $preview.css({ 'margin-left': 'auto', 'margin-right': '0' });
            } else {
                $preview.css({ 'margin-right': 'auto', 'margin-left': '0' });
            }

            // Tail Logic
            $preview.find('.preview-tail').remove();

            if (styles.tail !== 'none') {
                $preview.css('position', 'relative');
                var $tail = $('<div class="preview-tail"></div>');
                var tailColor = styles.bg;

                $tail.css({
                    'position': 'absolute',
                    'width': '0',
                    'height': '0',
                    'border-style': 'solid'
                });

                if (styles.align === 'right') {
                    $tail.css({ 'right': '-8px', 'bottom': '0' });
                    if (styles.tail === 'sharp') {
                        $tail.css({
                            'border-width': '10px 0 10px 15px',
                            'border-color': 'transparent transparent transparent ' + tailColor,
                            'right': '-10px',
                            'bottom': '10px'
                        });
                    } else {
                        $tail.css({
                            'border-width': '15px 0 0 15px',
                            'border-color': 'transparent transparent transparent ' + tailColor,
                            'transform': 'skewX(-10deg)'
                        });
                    }
                } else {
                    $tail.css({ 'left': '-8px', 'bottom': '0' });
                    if (styles.tail === 'sharp') {
                        $tail.css({
                            'border-width': '10px 15px 10px 0',
                            'border-color': 'transparent ' + tailColor + ' transparent transparent',
                            'left': '-10px',
                            'bottom': '10px'
                        });
                    } else {
                        $tail.css({
                            'border-width': '15px 15px 0 0',
                            'border-color': 'transparent ' + tailColor + ' transparent transparent',
                            'transform': 'skewX(10deg)'
                        });
                    }
                }
                $preview.append($tail);
            }
        }

        // Initialize UI
        // Hide non-active tabs initially handled by PHP, but ensure custom fields are toggled
        $('.sb-chat-theme-selector').trigger('change');

        // Initial Preview
        updatePreview();

    });
})(jQuery);
