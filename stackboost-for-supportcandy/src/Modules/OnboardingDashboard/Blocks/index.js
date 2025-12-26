( function( wp ) {
    var registerBlockType = wp.blocks.registerBlockType;
    var ServerSideRender = wp.serverSideRender;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var __ = wp.i18n.__;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;

    registerBlockType( 'stackboost/onboarding-dashboard', {
        title: 'Onboarding Dashboard',
        icon: 'welcome-learn-more',
        category: 'widgets',
        edit: function( props ) {
            var blockProps = useBlockProps();

            // "Free Parking" Card Structure
            var freeParkingCard = el('div', { className: 'stackboost-free-parking-card' },
                // Top Bar
                el('div', { className: 'stackboost-fp-bar' }),
                // Content
                el('div', { className: 'stackboost-fp-content' },
                    // Icon Container
                    el('div', { className: 'stackboost-fp-icon-container' },
                        // Car Icon (SVG)
                        el('svg', {
                            className: 'stackboost-fp-icon',
                            viewBox: '0 0 24 24',
                            fill: 'currentColor'
                        },
                            el('path', { d: 'M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z' })
                        ),
                        // Dots
                        el('div', { className: 'stackboost-fp-dot-1' }),
                        el('div', { className: 'stackboost-fp-dot-2' })
                    ),
                    // Headline
                    el('h2', { className: 'stackboost-fp-title' }, 'Free Parking'),
                    // Divider
                    el('div', { className: 'stackboost-fp-divider' }),
                    // Blurb
                    el('p', { className: 'stackboost-fp-text' },
                        "Consider this spot 'Free Parking.' This module runs on autopilot, so there are no settings to manage right now. However, we have officially claimed this real estate for our roadmapped enhancements. We are looking forward to building on this board with expanded options that go beyond the current functionality."
                    ),
                    // Button (Decorative)
                    el('button', { className: 'stackboost-fp-button', type: 'button' },
                        el('span', {}, 'Collect Jackpot'),
                        // Arrow Icon (SVG)
                        el('svg', {
                            className: 'material-symbols-outlined',
                            style: { width: '20px', height: '20px', fill: 'currentColor' },
                            viewBox: '0 0 24 24'
                        },
                            el('path', { d: 'M16.01 11H4v2h12.01v3L20 12l-3.99-4z' })
                        )
                    )
                ),
                // Footer
                el('div', { className: 'stackboost-fp-footer' },
                    el('span', {},
                        el('svg', { style: { width: '14px', height: '14px', fill: 'currentColor' }, viewBox: '0 0 24 24' },
                            el('path', { d: 'M13 13v8h8v-8h-8zM3 21h8v-8H3v8zM3 3v8h8V3H3zm13.66-1.31L11 7.34 16.66 13l5.66-5.66-5.66-5.65z' })
                        ),
                        ' WordPress Block Preview'
                    )
                )
            );

            return el( Fragment, {},
                el( InspectorControls, {},
                    el( PanelBody, { title: __( 'Onboarding Settings', 'stackboost-for-supportcandy' ), initialOpen: true },
                        freeParkingCard
                    )
                ),
                el( 'div', blockProps,
                    el( ServerSideRender, {
                        block: 'stackboost/onboarding-dashboard',
                        attributes: props.attributes
                    } )
                )
            );
        },
        save: function() {
            return null;
        },
    } );
} )( window.wp );
