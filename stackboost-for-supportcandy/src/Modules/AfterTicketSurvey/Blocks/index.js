( function( wp ) {
    var registerBlockType = wp.blocks.registerBlockType;
    var ServerSideRender = wp.serverSideRender;
    var __ = wp.i18n.__;

    registerBlockType( 'stackboost/after-ticket-survey', {
        title: 'After Ticket Survey',
        icon: 'clipboard',
        category: 'widgets',
        edit: function( props ) {
            return wp.element.createElement( ServerSideRender, {
                block: 'stackboost/after-ticket-survey',
                attributes: props.attributes
            } );
        },
        save: function() {
            return null;
        },
    } );
} )( window.wp );
