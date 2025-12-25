( function( wp ) {
    var registerBlockType = wp.blocks.registerBlockType;
    var ServerSideRender = wp.serverSideRender;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var ToggleControl = wp.components.ToggleControl;
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
                el( InspectorControls, {},
                    el( PanelBody, { title: 'Content Settings', initialOpen: true },
                        el( TextareaControl, {
                            label: 'Intro Text',
                            value: attributes.introText,
                            onChange: function( value ) { setAttributes( { introText: value } ); },
                            help: 'Text displayed above the survey form.'
                        } ),
                        el( TextareaControl, {
                            label: 'Success Message',
                            value: attributes.successMessage,
                            onChange: function( value ) { setAttributes( { successMessage: value } ); },
                            help: 'Message displayed after successful submission.'
                        } ),
                        el( TextControl, {
                            label: 'Submit Button Text',
                            value: attributes.submitButtonText,
                            onChange: function( value ) { setAttributes( { submitButtonText: value } ); }
                        } )
                    ),
                    el( PanelBody, { title: 'Display Settings', initialOpen: true },
                        el( SelectControl, {
                            label: 'Layout',
                            value: attributes.layout,
                            options: [
                                { label: 'List (Vertical)', value: 'list' },
                                { label: 'Grid (2 Columns)', value: 'grid' }
                            ],
                            onChange: function( value ) { setAttributes( { layout: value } ); }
                        } ),
                        el( ToggleControl, {
                            label: 'Hide Field Labels',
                            checked: attributes.hideLabels,
                            onChange: function( value ) { setAttributes( { hideLabels: value } ); }
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
