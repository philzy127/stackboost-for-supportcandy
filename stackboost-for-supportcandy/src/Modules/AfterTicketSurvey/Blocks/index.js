( function( wp ) {
    var registerBlockType = wp.blocks.registerBlockType;
    var ServerSideRender = wp.serverSideRender;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var __ = wp.i18n.__;
    var el = wp.element.createElement;

    registerBlockType( 'stackboost/after-ticket-survey', {
        title: 'After Ticket Survey',
        icon: 'clipboard',
        category: 'widgets',
        edit: function( props ) {
            var blockProps = useBlockProps();
            return el( 'div', blockProps,
                el( ServerSideRender, {
                    block: 'stackboost/after-ticket-survey',
                    attributes: props.attributes
                } )
            );
        },
        save: function() {
            return null;
        },
    } );
} )( window.wp );
