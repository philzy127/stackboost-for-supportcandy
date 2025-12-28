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
        });

        // 3. Theme Preset Logic
        // Use event delegation for dynamic elements or just class selector
        $(document).on('change', '#sb_chat_global_theme_selector', function() {
            var val = $(this).val();
            // Show/Hide Tab Navigation Items
            if (val === 'custom') {
                $('.sb-chat-tab.type-tab').css('display', 'block'); // Show Agent/Customer/Note tabs
            } else {
                $('.sb-chat-tab.type-tab').hide(); // Hide Agent/Customer/Note tabs
                // Force switch to 'general' tab if a type tab was active
                if ($('.sb-chat-tab.active').hasClass('type-tab')) {
                    $('.sb-chat-tab[data-target="general"]').click();
                }
            }
            updatePreview();
        });

        // 4. Reset Logic
        $('#sb_chat_reset_all').on('click', function() {
            if(!confirm('Are you sure you want to reset all Chat Bubble settings to defaults?')) return;
            // Reset Global fields
            $('#chat_bubbles_enable').prop('checked', false);
            $('#sb_chat_global_theme_selector').val('default').trigger('change');
            $('#chat_bubbles_tail').val('none');

            // Reset Type fields via loop
            ['agent', 'customer', 'note'].forEach(function(type) {
                resetSection(type);
            });
            updatePreview();
        });

        $('.sb-chat-reset-type').on('click', function() {
            var type = $(this).data('type');
            if(!confirm('Reset settings for ' + type + '?')) return;
            resetSection(type);
            updatePreview();
        });

        function resetSection(type) {
            var prefixName = 'stackboost_settings[chat_bubbles_' + type + '_';
            // Reset colors to default
            $('input[name="' + prefixName + 'bg_color]"]').val('#f1f1f1').trigger('change');
            $('input[name="' + prefixName + 'text_color]"]').val('#333333').trigger('change');
            $('select[name="' + prefixName + 'font_family]"]').val('');
            $('input[name="' + prefixName + 'font_size]"]').val('');
            $('input[name="' + prefixName + 'font_bold]"]').prop('checked', false);
            $('input[name="' + prefixName + 'font_italic]"]').prop('checked', false);
            $('input[name="' + prefixName + 'font_underline]"]').prop('checked', false);
            $('select[name="' + prefixName + 'alignment]"]').val('left');
            $('input[name="' + prefixName + 'width]"]').val('85');
            $('input[name="' + prefixName + 'radius]"]').val('15');
        }

        // 5. Live Preview Logic
        $('form input, form select').on('change input', function() {
            updatePreview();
        });

        function updatePreview() {
            // Get Global Settings
            var theme = $('#sb_chat_global_theme_selector').val();
            // Default Theme is now 'default'
            if (!theme) theme = 'default';

            var tailStyle = $('#chat_bubbles_tail').val();

            // Iterate over all 3 types to update the 3 bubbles
            ['agent', 'customer', 'note'].forEach(function(type) {
                updateBubble(type, theme, tailStyle);
            });
        }

        function updateBubble(type, theme, tailStyle) {
            var $preview = $('#preview-bubble-' + type);
            var prefixName = 'stackboost_settings[chat_bubbles_' + type + '_';

            // Default Values
            var styles = {
                bg: '#f1f1f1',
                text: '#333333',
                font: '',
                fontSize: '',
                align: 'left',
                width: '85',
                radius: '15',
                tail: tailStyle,
                bold: false,
                italic: false,
                underline: false
            };

            // Apply Theme Logic
            // Note: Note types default to center/85% in themes.
            if (theme === 'stackboost') {
                if (type === 'agent') {
                    styles.bg = 'var(--sb-accent, #2271b1)'; // Use var for preview
                    styles.text = '#ffffff';
                    styles.align = 'right';
                    styles.radius = '15';
                } else if (type === 'note') {
                    styles.bg = '#fff8e5';
                    styles.text = '#333333';
                    styles.align = 'center';
                    styles.radius = '5';
                } else {
                    styles.bg = 'var(--sb-bg-main, #f0f0f1)';
                    styles.text = '#3c434a';
                    styles.align = 'left';
                    styles.radius = '15';
                }
                styles.font = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
                styles.tail = 'round'; // Default for SB

            } else if (theme === 'supportcandy') {
                var scPrimary = (typeof stackboostChatBubbles !== 'undefined' && stackboostChatBubbles.scPrimaryColor)
                    ? stackboostChatBubbles.scPrimaryColor
                    : '#2271b1';

                if (type === 'agent') {
                    styles.bg = scPrimary;
                    styles.text = '#ffffff';
                    styles.align = 'right';
                    styles.radius = '5';
                } else if (type === 'note') {
                    styles.bg = '#fffbcc';
                    styles.text = '#333333';
                    styles.align = 'center';
                    styles.radius = '0';
                } else {
                    styles.bg = '#e5e5e5';
                    styles.text = '#333333';
                    styles.align = 'left';
                    styles.radius = '5';
                }
                styles.tail = 'none';

            } else if (theme === 'classic') {
                if (type === 'agent') {
                    styles.bg = '#2271b1';
                    styles.text = '#ffffff';
                    styles.align = 'right';
                    styles.radius = '5';
                } else if (type === 'note') {
                    styles.bg = '#fdfdfd';
                    styles.text = '#333333';
                    styles.align = 'center';
                    styles.radius = '0';
                } else {
                    styles.bg = '#e5e5e5';
                    styles.text = '#333333';
                    styles.align = 'left';
                    styles.radius = '5';
                }
                styles.tail = 'none';

            } else if (theme === 'default') {
                // Blue/Grey standard
                if (type === 'agent') {
                    styles.bg = '#2271b1';
                    styles.text = '#ffffff';
                    styles.align = 'right';
                    styles.radius = '15';
                } else if (type === 'note') {
                    styles.bg = '#fff8e5';
                    styles.text = '#333333';
                    styles.align = 'center';
                    styles.radius = '5';
                } else {
                    styles.bg = '#f0f0f1';
                    styles.text = '#3c434a';
                    styles.align = 'left';
                    styles.radius = '15';
                }
                styles.font = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
                styles.tail = 'round';

            } else if (theme === 'ios') {
                if (type === 'agent') {
                    styles.bg = '#007aff';
                    styles.text = '#ffffff';
                    styles.align = 'right';
                    styles.width = '75';
                    styles.radius = '20';
                } else if (type === 'note') {
                    styles.bg = '#fffae6';
                    styles.text = '#333333';
                    styles.align = 'center';
                    styles.width = '85';
                    styles.radius = '10';
                } else {
                    styles.bg = '#e5e5ea';
                    styles.text = '#000000';
                    styles.align = 'left';
                    styles.width = '75';
                    styles.radius = '20';
                }
                styles.font = '-apple-system, BlinkMacSystemFont, sans-serif';
                styles.tail = 'round';

            } else if (theme === 'android') {
                if (type === 'agent') {
                    styles.bg = '#d9fdd3';
                    styles.text = '#111b21';
                    styles.align = 'right';
                    styles.width = '80';
                    styles.radius = '8';
                } else if (type === 'note') {
                    styles.bg = '#fffbcc';
                    styles.text = '#333333';
                    styles.align = 'center';
                    styles.width = '85';
                    styles.radius = '5';
                } else {
                    styles.bg = '#ffffff';
                    styles.text = '#111b21';
                    styles.align = 'left';
                    styles.width = '80';
                    styles.radius = '8';
                }
                styles.font = 'Roboto, sans-serif';
                styles.tail = 'sharp';

            } else if (theme === 'modern') {
                if (type === 'agent') {
                    styles.bg = '#000000';
                    styles.text = '#ffffff';
                    styles.align = 'right';
                    styles.width = '60';
                    styles.radius = '0';
                } else if (type === 'note') {
                    styles.bg = '#f9f9f9';
                    styles.text = '#555555';
                    styles.align = 'center';
                    styles.width = '85';
                    styles.radius = '0';
                } else {
                    styles.bg = '#f2f2f2';
                    styles.text = '#000000';
                    styles.align = 'left';
                    styles.width = '60';
                    styles.radius = '0';
                }
                styles.font = 'Helvetica, Arial, sans-serif';
                styles.tail = 'none';

            } else if (theme === 'custom') {
                // Custom overrides everything
                styles.bg = $('input[name="' + prefixName + 'bg_color]"]').val();
                styles.text = $('input[name="' + prefixName + 'text_color]"]').val();
                styles.font = $('select[name="' + prefixName + 'font_family]"]').val();
                styles.fontSize = $('input[name="' + prefixName + 'font_size]"]').val();
                styles.align = $('select[name="' + prefixName + 'alignment]"]').val();
                styles.width = $('input[name="' + prefixName + 'width]"]').val();
                styles.radius = $('input[name="' + prefixName + 'radius]"]').val();

                // Font Styles
                styles.bold = $('input[name="' + prefixName + 'font_bold]"]').is(':checked');
                styles.italic = $('input[name="' + prefixName + 'font_italic]"]').is(':checked');
                styles.underline = $('input[name="' + prefixName + 'font_underline]"]').is(':checked');

                // For custom, use Global Tail
                styles.tail = tailStyle;
            }

            // Apply Base CSS
            var cssMap = {
                'background-color': styles.bg,
                'color': styles.text,
                'border-radius': styles.radius + 'px',
                'width': styles.width + '%',
                'font-family': styles.font,
                'padding': '15px',
                'font-weight': styles.bold ? 'bold' : 'normal',
                'font-style': styles.italic ? 'italic' : 'normal',
                'text-decoration': styles.underline ? 'underline' : 'none',
                'border': 'none' // Remove borders
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
            } else if (styles.align === 'center') {
                $preview.css({
                    'margin-left': 'auto',
                    'margin-right': 'auto',
                    'align-self': 'center'
                });
            } else {
                $preview.css({
                    'margin-right': 'auto',
                    'margin-left': '0',
                    'align-self': 'flex-start'
                });
            }

            // Tail Logic
            $preview.find('.preview-tail').remove();

            // Tail only if enabled, not note, and not center aligned
            if (styles.tail !== 'none' && type !== 'note' && styles.align !== 'center') {
                $preview.css('position', 'relative');
                var $tail = $('<div class="preview-tail"></div>');
                // Use computed background color for tail to handle vars
                var tailColor = $preview.css('background-color');

                $tail.css({
                    'position': 'absolute',
                    'width': '0',
                    'height': '0',
                    'border-style': 'solid',
                    'z-index': 1
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
                        // Round approximation
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
                        // Round approximation
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
        $('#sb_chat_global_theme_selector').trigger('change');

        // Initial Preview
        updatePreview();

    });
})(jQuery);
