(function($) {
    $(document).ready(function() {

        // 1. Initialize Color Pickers
        $('.my-color-field').wpColorPicker({
            change: function(event, ui) {
                updatePreview();
            }
        });

        // 2. Tab Switching Logic
        $('.sb-chat-tab').on('click', function() {
            // UI Update
            $('.sb-chat-tab').removeClass('active');
            $(this).addClass('active');

            // Show/Hide Section
            var target = $(this).data('target');
            $('.sb-chat-config-section').hide();
            $('#sb-chat-config-' + target).fadeIn();

            // Note: We don't need to update preview here because preview shows ALL bubbles.
            // But we might want to highlight the bubble being edited? (Optional enhancement)
        });

        // 3. Toggle Custom Fields based on Theme Selection
        // Use event delegation for dynamic elements or just class selector
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

        // 4. Live Preview Logic
        $('form input, form select').on('change input', function() {
            updatePreview();
        });

        function updatePreview() {
            // Iterate over all 3 types to update the 3 bubbles
            ['agent', 'customer', 'note'].forEach(function(type) {
                updateBubble(type);
            });
        }

        function updateBubble(type) {
            // Find inputs for this type
            // Prefix: stackboost_settings[chat_bubbles_{type}_{field}]
            var prefixName = 'stackboost_settings[chat_bubbles_' + type + '_';

            var theme = $('select[name="' + prefixName + 'theme]"]').val();
            var $preview = $('#preview-bubble-' + type);

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
            // Note: Keep in sync with Core.php logic!
            if (theme === 'stackboost') {
                if (type === 'agent') {
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
                if (type === 'agent') {
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
                if (type === 'agent') {
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
                if (type === 'agent') {
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
                var scPrimary = (typeof stackboostChatBubbles !== 'undefined' && stackboostChatBubbles.scPrimaryColor)
                    ? stackboostChatBubbles.scPrimaryColor
                    : '#2271b1';

                if (type === 'agent') {
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

            // Apply CSS
            var cssMap = {
                'background-color': styles.bg,
                'color': styles.text,
                'border-radius': styles.radius + 'px',
                'width': styles.width + '%',
                'font-family': styles.font,
                'padding': '15px',
                // Reset margins for flex/grid context?
                // Preview context uses flexbox/grid layout so align-self or margin-auto works.
            };

            if (styles.fontSize) {
                cssMap['font-size'] = styles.fontSize + 'px';
            } else {
                cssMap['font-size'] = '';
            }

            $preview.css(cssMap);

            // Alignment (Flexbox self-alignment)
            if (styles.align === 'right') {
                $preview.css({
                    'margin-left': 'auto',
                    'margin-right': '0',
                    'align-self': 'flex-end'
                });
            } else {
                $preview.css({
                    'margin-right': 'auto',
                    'margin-left': '0',
                    'align-self': 'flex-start'
                });
            }

            // Special handling for Note (Centering usually)
            // But if user sets 'Right' for Note, we respect it.
            // Core default for Note? Usually centered or full width.
            // Our JS default is 'Left'.

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
                    $tail.css({ 'right': '-8px', 'bottom': '0', 'left': 'auto' });
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
                    $tail.css({ 'left': '-8px', 'bottom': '0', 'right': 'auto' });
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
        // Trigger change on all theme selectors to set initial field visibility
        $('.sb-chat-theme-selector').each(function() {
            var val = $(this).val();
            var type = $(this).data('type');
            if(val !== 'custom') {
                $('#sb_chat_custom_fields_' + type).hide();
            }
        });

        // Initial Preview
        updatePreview();

    });
})(jQuery);
