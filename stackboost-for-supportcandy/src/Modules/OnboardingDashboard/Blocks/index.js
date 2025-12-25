( function( wp ) {
    var registerBlockType = wp.blocks.registerBlockType;
    var ServerSideRender = wp.serverSideRender;
    var __ = wp.i18n.__;

    registerBlockType( 'stackboost/onboarding-dashboard', {
        title: 'Onboarding Dashboard',
        icon: 'welcome-learn-more',
        category: 'widgets',
        edit: function( props ) {
            return wp.element.createElement( ServerSideRender, {
                block: 'stackboost/onboarding-dashboard',
                attributes: props.attributes
            } );
        },
        save: function() {
            return null;
        },
    } );
} )( window.wp );
