(function($) {
    $(document).ready(function() {

        // 1. Initialize Color Pickers
        $('.my-color-field').wpColorPicker({
            change: function(event, ui) {
                updatePreview();
            }
        });

        // 2. Toggle Custom Fields based on Theme Selection
        $('#sb_chat_theme_selector').on('change', function() {
            var val = $(this).val();
            var $customFields = $('#sb_chat_custom_fields');

            if (val === 'custom') {
                $customFields.fadeIn();
            } else {
                $customFields.hide();
            }
            updatePreview();
        });

        // 3. Live Preview Logic
        // We listen to changes on all inputs inside the form
        $('form input, form select').on('change input', function() {
            updatePreview();
        });

        function updatePreview() {
            var theme = $('#sb_chat_theme_selector').val();
            var $preview = $('#stackboost-chat-preview-bubble');

            // Default Values (Must match Core.php defaults for consistency)
            var styles = {
                bg: '#f1f1f1',
                text: '#333333',
                font: '',
                align: 'left',
                width: '85',
                radius: '15',
                tail: 'none'
            };

            // Apply Theme Logic (Hardcoded map for preview purposes)
            // This mirrors Core.php logic.
            if (theme === 'stackboost') {
                // Agent Logic is assumed for preview context if we are on agent tab
                // Wait, the tab determines what we are editing.
                // But the preview should reflect the current tab's user type.
                // Let's assume Agent for 'stackboost' theme as default blue.
                // But if we are editing 'Customer', stackboost theme makes them Grey.

                // Detection: URL parameter 'tab'
                var urlParams = new URLSearchParams(window.location.search);
                var currentTab = urlParams.get('tab') || 'agent';

                if (currentTab === 'agent') {
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
                var urlParams = new URLSearchParams(window.location.search);
                var currentTab = urlParams.get('tab') || 'agent';
                if (currentTab === 'agent') {
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
                var urlParams = new URLSearchParams(window.location.search);
                var currentTab = urlParams.get('tab') || 'agent';
                if (currentTab === 'agent') {
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
                var urlParams = new URLSearchParams(window.location.search);
                var currentTab = urlParams.get('tab') || 'agent';
                if (currentTab === 'agent') {
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

            } else if (theme === 'custom') {
                // Read from Inputs
                // We need to find the inputs with the correct names based on the prefix.
                // Prefix is chat_bubbles_{tab}_
                var urlParams = new URLSearchParams(window.location.search);
                var tab = urlParams.get('tab') || 'agent';
                var prefix = 'stackboost_settings[chat_bubbles_' + tab + '_'; // Name attribute prefix

                styles.bg = $('input[name="' + prefix + 'bg_color]"]').val();
                styles.text = $('input[name="' + prefix + 'text_color]"]').val();
                styles.font = $('select[name="' + prefix + 'font_family]"]').val();
                styles.align = $('select[name="' + prefix + 'alignment]"]').val();
                styles.width = $('input[name="' + prefix + 'width]"]').val();
                styles.radius = $('input[name="' + prefix + 'radius]"]').val();
                styles.tail = $('select[name="' + prefix + 'tail]"]').val();
            }

            // Apply Styles to Preview Element
            $preview.css({
                'background-color': styles.bg,
                'color': styles.text,
                'border-radius': styles.radius + 'px',
                'width': styles.width + '%',
                'font-family': styles.font,
                'padding': '15px'
            });

            // Alignment (Margin Auto)
            if (styles.align === 'right') {
                $preview.css({
                    'margin-left': 'auto',
                    'margin-right': '0'
                });
            } else {
                $preview.css({
                    'margin-right': 'auto',
                    'margin-left': '0'
                });
            }

            // Tail Logic (CSS Pseudo override via class toggling or inline style injection)
            // Since we can't easily inject pseudo-elements via inline style attribute on the element itself,
            // we will simulate the tail with a nested div or just leave it for the main CSS.
            // For a preview, we can append a small div absolute positioned.

            $preview.find('.preview-tail').remove(); // Clear old

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
                    $tail.css({
                        'right': '-8px',
                        'bottom': '0'
                    });
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
                    $tail.css({
                        'left': '-8px',
                        'bottom': '0'
                    });
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

        // Run once on load
        updatePreview();

    });
})(jQuery);
