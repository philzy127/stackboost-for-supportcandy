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
            $('#chat_bubbles_enable_ticket').prop('checked', false);
            $('#chat_bubbles_enable_email').prop('checked', false);
            $('#sb_chat_global_theme_selector').val('default').trigger('change');

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

            var containerBg = '#f0f0f1'; // Default light

            // Iterate over all 3 types to update the 3 bubbles
            ['agent', 'customer', 'note'].forEach(function(type) {
                updateBubble(type, theme);
            });

            // Update container background if Android (Droid) or iOS (Fruit) for better contrast
            if (theme === 'android') {
                containerBg = '#ece5dd'; // Droid Wallpaper
            } else if (theme === 'modern') {
                containerBg = '#ffffff';
            }
            $('.stackboost-chat-preview-container').css('background', containerBg);
        }

        function updateBubble(type, theme) {
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
                bold: false,
                italic: false,
                underline: false
            };

            // Apply Theme Logic
            // Note: Note types default to center/85% in themes.
            if (theme === 'stackboost') {
                // StackBoost: Syncs with Appearance module. Values passed via localized script.
                var sbColors = (typeof stackboostChatBubbles !== 'undefined' && stackboostChatBubbles.sbTheme)
                    ? stackboostChatBubbles.sbTheme
                    : { primary: '#2271b1', background: '#f0f0f1', text: '#3c434a' };

                if (type === 'agent') {
                    styles.bg = sbColors.primary; // Accent
                    styles.text = '#ffffff';
                    styles.align = 'right';
                    styles.radius = '15';
                } else if (type === 'note') {
                    styles.bg = '#fff8e5';
                    styles.text = '#333333';
                    styles.align = 'center';
                    styles.radius = '5';
                } else {
                    styles.bg = sbColors.background; // Main BG or Surface
                    styles.text = sbColors.text;
                    styles.align = 'left';
                    styles.radius = '15';
                }
                styles.font = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

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
                    styles.bg = '#e6e6e6';
                    styles.text = '#3c434a';
                    styles.align = 'left';
                    styles.radius = '15';
                }
                styles.font = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

            } else if (theme === 'ios') {
                // Now "Fruit"
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

            } else if (theme === 'android') {
                // Now "Droid"
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

            // Remove Tails Logic (per user request)
            $preview.find('.preview-tail').remove();
        }

        // Initialize UI
        $('#sb_chat_global_theme_selector').trigger('change');

        // Initial Preview
        updatePreview();

    });
})(jQuery);
