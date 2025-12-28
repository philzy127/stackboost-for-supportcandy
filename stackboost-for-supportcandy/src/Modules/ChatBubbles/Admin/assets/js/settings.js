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
            var btn = $(this);
            // Use stackboostConfirm if available, else native confirm
            if (typeof window.stackboostConfirm === 'function') {
                window.stackboostConfirm(
                    'Are you sure you want to reset all Chat Bubble settings to defaults?',
                    'Confirm Reset',
                    function() {
                        performGlobalReset();
                    },
                    null,
                    'Yes, Reset',
                    'Cancel',
                    true
                );
            } else {
                if(confirm('Are you sure you want to reset all Chat Bubble settings to defaults?')) {
                    performGlobalReset();
                }
            }
        });

        function performGlobalReset() {
            // Reset Global fields
            $('#chat_bubbles_enable_ticket').prop('checked', false);
            $('#chat_bubbles_enable_email').prop('checked', false);
            $('#sb_chat_global_theme_selector').val('default').trigger('change');
            $('#chat_bubbles_shadow_enable').prop('checked', false);
            $('input[name="stackboost_settings[chat_bubbles_shadow_color]"]').val('#000000').trigger('change');
            $('select[name="stackboost_settings[chat_bubbles_shadow_depth]"]').val('small');
            $('input[name="stackboost_settings[chat_bubbles_shadow_opacity]"]').val('40').trigger('input'); // Reset opacity

            // Reset Type fields via loop
            ['agent', 'customer', 'note'].forEach(function(type) {
                resetSection(type);
            });
            updatePreview();
        }

        $('.sb-chat-reset-type').on('click', function() {
            var type = $(this).data('type');
            if (typeof window.stackboostConfirm === 'function') {
                window.stackboostConfirm(
                    'Reset settings for ' + type + '?',
                    'Confirm Reset',
                    function() {
                        resetSection(type);
                        updatePreview();
                    },
                    null,
                    'Yes, Reset',
                    'Cancel',
                    true
                );
            } else {
                if(confirm('Reset settings for ' + type + '?')) {
                    resetSection(type);
                    updatePreview();
                }
            }
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
            // Reset Padding
            $('input[name="' + prefixName + 'padding]"]').val('15');
            // Reset Borders
            $('select[name="' + prefixName + 'border_style]"]').val('none');
            $('input[name="' + prefixName + 'border_width]"]').val('1');
            $('input[name="' + prefixName + 'border_color]"]').val('#cccccc').trigger('change');
        }

        // 5. Live Preview Logic
        $('form input, form select').on('change input', function() {
            updatePreview();
        });

        // Helper: Calculate luminance
        function getLuminance(hex) {
            // Remove hash
            hex = hex.replace('#', '');

            // Convert to RGB
            var r = parseInt(hex.substring(0, 2), 16) / 255;
            var g = parseInt(hex.substring(2, 4), 16) / 255;
            var b = parseInt(hex.substring(4, 6), 16) / 255;

            // Adjust for Gamma
            var a = [r, g, b].map(function (v) {
                return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
            });
            return a[0] * 0.2126 + a[1] * 0.7152 + a[2] * 0.0722;
        }

        function updatePreview() {
            // Get Global Settings
            var theme = $('#sb_chat_global_theme_selector').val();
            // Default Theme is now 'default'
            if (!theme) theme = 'default';

            var bubbleBgColors = [];

            // Iterate over all 3 types to update the 3 bubbles
            ['agent', 'customer', 'note'].forEach(function(type) {
                var bg = updateBubble(type, theme);
                if (bg) bubbleBgColors.push(bg);
            });

            // Smart Background Logic
            // If theme is specifically android or modern, we respect that first.
            // Otherwise we check contrast.
            var containerBg = '#ffffff'; // Default to White for better visibility

            if (theme === 'android') {
                containerBg = '#ece5dd';
            } else if (theme === 'modern') {
                containerBg = '#ffffff';
            } else {
                // Check average luminance of bubbles
                // Only consider hex colors for calculation (skip vars for now)
                var validColors = bubbleBgColors.filter(function(c) { return c && c.indexOf('#') === 0; });

                if (validColors.length > 0) {
                    var totalLum = 0;
                    validColors.forEach(function(c) { totalLum += getLuminance(c); });
                    var avgLum = totalLum / validColors.length;

                    // If bubbles are very light (avgLum > 0.85), switch to Dark Mode #333 to ensure visibility
                    if (avgLum > 0.85) {
                        containerBg = '#333333';
                    }
                }
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
                padding: '15',
                bold: false,
                italic: false,
                underline: false,
                borderStyle: 'none',
                borderWidth: '1',
                borderColor: '#cccccc'
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
                var scNote = (typeof stackboostChatBubbles !== 'undefined' && stackboostChatBubbles.scNoteColor)
                    ? stackboostChatBubbles.scNoteColor
                    : '#fffbcc';
                // Use Reply & Close color for Customer (3rd color)
                var scCustBg = (typeof stackboostChatBubbles !== 'undefined' && stackboostChatBubbles.scReplyCloseBg)
                    ? stackboostChatBubbles.scReplyCloseBg
                    : '#e5e5e5';
                var scCustText = (typeof stackboostChatBubbles !== 'undefined' && stackboostChatBubbles.scReplyCloseText)
                    ? stackboostChatBubbles.scReplyCloseText
                    : '#333333';

                if (type === 'agent') {
                    styles.bg = scPrimary;
                    styles.text = '#ffffff';
                    styles.align = 'right';
                    styles.radius = '5';
                } else if (type === 'note') {
                    styles.bg = scNote;
                    styles.text = '#333333';
                    styles.align = 'center';
                    styles.radius = '0';
                } else {
                    styles.bg = scCustBg;
                    styles.text = scCustText;
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
                // Fruit: Blue (Agent) vs Green (Customer)
                if (type === 'agent') {
                    styles.bg = '#007aff';
                    styles.text = '#ffffff';
                    styles.align = 'right';
                    styles.width = '75';
                    styles.radius = '20';
                } else if (type === 'note') {
                    styles.bg = '#fffbcc';
                    styles.text = '#333333';
                    styles.align = 'center';
                    styles.width = '85';
                    styles.radius = '10';
                } else {
                    styles.bg = '#34c759';
                    styles.text = '#ffffff';
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
                styles.padding = $('input[name="' + prefixName + 'padding]"]').val();

                // Font Styles
                styles.bold = $('input[name="' + prefixName + 'font_bold]"]').is(':checked');
                styles.italic = $('input[name="' + prefixName + 'font_italic]"]').is(':checked');
                styles.underline = $('input[name="' + prefixName + 'font_underline]"]').is(':checked');

                // Borders
                styles.borderStyle = $('select[name="' + prefixName + 'border_style]"]').val();
                styles.borderWidth = $('input[name="' + prefixName + 'border_width]"]').val();
                styles.borderColor = $('input[name="' + prefixName + 'border_color]"]').val();
            }

            // Apply Base CSS
            var cssMap = {
                'background-color': styles.bg,
                'color': styles.text,
                'border-radius': styles.radius + 'px',
                'width': styles.width + '%',
                'font-family': styles.font,
                'padding': styles.padding + 'px',
                'font-weight': styles.bold ? 'bold' : 'normal',
                'font-style': styles.italic ? 'italic' : 'normal',
                'text-decoration': styles.underline ? 'underline' : 'none'
            };

            // Border Logic
            if (styles.borderStyle && styles.borderStyle !== 'none') {
                cssMap['border'] = styles.borderWidth + 'px ' + styles.borderStyle + ' ' + styles.borderColor;
            } else {
                cssMap['border'] = 'none';
            }

            if (styles.fontSize) {
                cssMap['font-size'] = styles.fontSize + 'px';
            } else {
                cssMap['font-size'] = '';
            }

            // Drop Shadow Logic (Global)
            var shadowEnable = $('#chat_bubbles_shadow_enable').is(':checked');
            if (shadowEnable) {
                var shadowColor = $('input[name="stackboost_settings[chat_bubbles_shadow_color]"]').val();
                var shadowBlur = $('input[name="stackboost_settings[chat_bubbles_shadow_blur]"]').val();
                var shadowOpacity = $('input[name="stackboost_settings[chat_bubbles_shadow_opacity]"]').val();

                // Convert Opacity (0-100) to decimal
                var opacityVal = parseInt(shadowOpacity) / 100;

                // Helper to convert Hex to RGBA
                var r=0, g=0, b=0;
                if (shadowColor.length === 7) {
                    r = parseInt(shadowColor.substring(1,3), 16);
                    g = parseInt(shadowColor.substring(3,5), 16);
                    b = parseInt(shadowColor.substring(5,7), 16);
                    shadowColor = 'rgba(' + r + ',' + g + ',' + b + ',' + opacityVal + ')';
                }

                var blur = shadowBlur + 'px';
                cssMap['filter'] = 'drop-shadow(0 2px ' + blur + ' ' + shadowColor + ')';
                cssMap['box-shadow'] = 'none'; // clear old
            } else {
                cssMap['filter'] = 'none';
                cssMap['box-shadow'] = 'none';
            }

            // Image Styling Logic (Global)
            var imageBox = $('#chat_bubbles_image_box').is(':checked');
            var $img = $preview.find('img');
            if (imageBox) {
                $img.css({
                    'border': '1px solid rgba(0,0,0,0.2)',
                    'padding': '3px',
                    'background': 'rgba(255,255,255,0.5)',
                    'border-radius': '3px'
                });
            } else {
                $img.css({
                    'border': 'none',
                    'padding': '0',
                    'background': 'transparent',
                    'border-radius': '0'
                });
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

            return styles.bg; // Return bg for smart background calculation
        }

        // Initialize UI
        $('#sb_chat_global_theme_selector').trigger('change');

        // Initial Preview
        updatePreview();

    });
})(jQuery);
