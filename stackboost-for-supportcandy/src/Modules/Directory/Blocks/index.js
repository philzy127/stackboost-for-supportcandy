( function( wp ) {
    var registerBlockType = wp.blocks.registerBlockType;
    var ServerSideRender = wp.serverSideRender;
    var __ = wp.i18n.__;

    registerBlockType( 'stackboost/directory', {
        title: 'StackBoost Directory',
        icon: 'groups',
        category: 'widgets',
        edit: function( props ) {
            return wp.element.createElement( ServerSideRender, {
                block: 'stackboost/directory',
                attributes: props.attributes
            } );
        },
        save: function() {
            return null;
        },
    } );
} )( window.wp );
