( function( wp ) {
    var registerBlockType = wp.blocks.registerBlockType;
    var ServerSideRender = wp.serverSideRender;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var URLInput = wp.blockEditor.URLInput;
    var PanelBody = wp.components.PanelBody;
    var PanelColorSettings = wp.blockEditor.PanelColorSettings;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var SelectControl = wp.components.SelectControl;
    var __ = wp.i18n.__;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;

    registerBlockType( 'stackboost/after-ticket-survey', {
        title: 'After Ticket Survey',
        icon: 'clipboard',
        category: 'widgets',
        edit: function( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            return el( Fragment, {},
                el( InspectorControls, { group: 'styles' },
                    el( PanelColorSettings, {
                        title: 'Button Colors',
                        initialOpen: false,
                        colorSettings: [
                            {
                                value: attributes.submitButtonBackgroundColor,
                                onChange: function( value ) { setAttributes( { submitButtonBackgroundColor: value } ); },
                                label: 'Background Color',
                            },
                            {
                                value: attributes.submitButtonTextColor,
                                onChange: function( value ) { setAttributes( { submitButtonTextColor: value } ); },
                                label: 'Text Color',
                            },
                        ],
                    } ),
                    el( PanelColorSettings, {
                        title: 'Input Field Colors',
                        initialOpen: false,
                        colorSettings: [
                            {
                                value: attributes.inputBackgroundColor,
                                onChange: function( value ) { setAttributes( { inputBackgroundColor: value } ); },
                                label: 'Background Color',
                            },
                            {
                                value: attributes.inputTextColor,
                                onChange: function( value ) { setAttributes( { inputTextColor: value } ); },
                                label: 'Text Color',
                            },
                        ],
                    } )
                ),
                el( InspectorControls, {},
                    el( PanelBody, { title: 'Content Settings', initialOpen: true },
                        el( TextControl, {
                            label: 'Form Title',
                            value: attributes.formTitle,
                            onChange: function( value ) { setAttributes( { formTitle: value } ); },
                            help: 'Optional title displayed at the top of the form.',
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        } ),
                        el( 'div', { style: { marginBottom: '16px' } } ), // Spacer
                        el( TextareaControl, {
                            label: 'Intro Text',
                            value: attributes.introText,
                            onChange: function( value ) { setAttributes( { introText: value } ); },
                            help: 'Text displayed above the survey form.',
                            __nextHasNoMarginBottom: true
                        } ),
                        el( 'div', { style: { marginBottom: '16px' } } ), // Spacer
                        el( TextControl, {
                            label: 'Submit Button Text',
                            value: attributes.submitButtonText,
                            onChange: function( value ) { setAttributes( { submitButtonText: value } ); },
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        } ),
                        el( 'div', { style: { marginBottom: '16px' } } ), // Spacer
                        el( TextareaControl, {
                            label: 'Success Message',
                            value: attributes.successMessage,
                            onChange: function( value ) { setAttributes( { successMessage: value } ); },
                            help: 'Message displayed after successful submission. Ignored if Redirect URL is set.',
                            __nextHasNoMarginBottom: true
                        } ),
                        el( 'div', { style: { marginBottom: '16px' } } ), // Spacer
                        el( 'label', { className: 'components-base-control__label' }, 'Redirect URL (Optional)' ),
                        el( URLInput, {
                            value: attributes.redirectUrl,
                            onChange: function( value ) { setAttributes( { redirectUrl: value } ); },
                            autoFocus: false
                        } ),
                        el( 'p', { className: 'components-base-control__help' }, 'If set, users will be redirected to this URL after submission instead of seeing the success message.' )
                    ),
                    el( PanelBody, { title: 'Display Settings', initialOpen: true },
                        el( SelectControl, {
                            label: 'Layout',
                            value: attributes.layout,
                            options: [
                                { label: 'List (Vertical)', value: 'list' },
                                { label: 'Grid (2 Columns)', value: 'grid' }
                            ],
                            onChange: function( value ) { setAttributes( { layout: value } ); },
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true
                        } )
                    )
                ),
                el( 'div', blockProps,
                    el( ServerSideRender, {
                        block: 'stackboost/after-ticket-survey',
                        attributes: attributes
                    } )
                )
            );
        },
        save: function() {
            return null;
        },
    } );
} )( window.wp );
