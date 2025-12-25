( function( wp ) {
    var registerBlockType = wp.blocks.registerBlockType;
    var ServerSideRender = wp.serverSideRender;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var ToggleControl = wp.components.ToggleControl;
    var RangeControl = wp.components.RangeControl;
    var SelectControl = wp.components.SelectControl;
    var CheckboxControl = wp.components.CheckboxControl;
    var __ = wp.i18n.__;
    var el = wp.element.createElement;
    var withSelect = wp.data.withSelect;

    // Edit Component
    var DirectoryEdit = function( props ) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var departments = props.departments; // From withSelect

        var blockProps = useBlockProps();

        // Helper to handle multi-select for visible columns
        var toggleColumn = function( column ) {
            var newColumns = attributes.visibleColumns.slice();
            var index = newColumns.indexOf( column );
            if ( index !== -1 ) {
                newColumns.splice( index, 1 );
            } else {
                newColumns.push( column );
            }
            setAttributes( { visibleColumns: newColumns } );
        };

        // Helper to handle department filter
        var toggleDepartment = function( deptName ) {
             var newDepts = attributes.departmentFilter.slice();
             var index = newDepts.indexOf( deptName );
             if ( index !== -1 ) {
                 newDepts.splice( index, 1 );
             } else {
                 newDepts.push( deptName );
             }
             setAttributes( { departmentFilter: newDepts } );
        };

        // Helper to decode HTML entities
        var decodeHTML = function(html) {
            var txt = document.createElement("textarea");
            txt.innerHTML = html;
            return txt.value;
        };


        return el( 'div', blockProps,
            el( InspectorControls, {},
                el( PanelBody, { title: __( 'Display Settings', 'stackboost-for-supportcandy' ), initialOpen: true },
                    el( SelectControl, {
                        label: __( 'Theme', 'stackboost-for-supportcandy' ),
                        value: attributes.theme,
                        options: [
                            { label: __( 'Standard Table', 'stackboost-for-supportcandy' ), value: 'standard' },
                            { label: __( 'Modern Cards', 'stackboost-for-supportcandy' ), value: 'modern' }
                        ],
                        onChange: function( val ) { setAttributes( { theme: val } ); }
                    } ),
                    el( ToggleControl, {
                        label: __( 'Show Search Bar', 'stackboost-for-supportcandy' ),
                        checked: attributes.showSearch,
                        onChange: function( val ) { setAttributes( { showSearch: val } ); }
                    } ),
                    el( 'p', { className: 'components-base-control__label' }, __( 'Photo Options', 'stackboost-for-supportcandy' ) ),
                    el( SelectControl, {
                        label: __( 'Photo Shape', 'stackboost-for-supportcandy' ),
                        value: attributes.photoShape,
                        options: [
                            { label: __( 'Circle', 'stackboost-for-supportcandy' ), value: 'circle' },
                            { label: __( 'Square', 'stackboost-for-supportcandy' ), value: 'square' },
                            { label: __( 'Portrait', 'stackboost-for-supportcandy' ), value: 'portrait' },
                            { label: __( 'Landscape', 'stackboost-for-supportcandy' ), value: 'landscape' }
                        ],
                        onChange: function( val ) { setAttributes( { photoShape: val } ); }
                    } ),
                    el( ToggleControl, {
                        label: __( 'Prefer Gravatar', 'stackboost-for-supportcandy' ),
                        help: __( 'If enabled, always try to show Gravatar first.', 'stackboost-for-supportcandy' ),
                        checked: attributes.preferGravatar,
                        onChange: function( val ) { setAttributes( { preferGravatar: val } ); }
                    } ),
                    el( ToggleControl, {
                        label: __( 'Fallback to Gravatar', 'stackboost-for-supportcandy' ),
                        help: __( 'If no custom photo is found, try Gravatar.', 'stackboost-for-supportcandy' ),
                        checked: attributes.enableGravatarFallback,
                        onChange: function( val ) { setAttributes( { enableGravatarFallback: val } ); }
                    } ),
                    el( 'p', { className: 'components-base-control__label' }, __( 'Visible Fields', 'stackboost-for-supportcandy' ) ),
                    el( CheckboxControl, {
                        label: __( 'Photo', 'stackboost-for-supportcandy' ),
                        checked: attributes.visibleColumns.indexOf( 'photo' ) !== -1,
                        onChange: function() { toggleColumn( 'photo' ); }
                    } ),
                    el( CheckboxControl, {
                        label: __( 'Name', 'stackboost-for-supportcandy' ),
                        checked: attributes.visibleColumns.indexOf( 'name' ) !== -1,
                        onChange: function() { toggleColumn( 'name' ); },
                        disabled: true // Name should probably always be visible
                    } ),
                    el( CheckboxControl, {
                        label: __( 'Email', 'stackboost-for-supportcandy' ),
                        checked: attributes.visibleColumns.indexOf( 'email' ) !== -1,
                        onChange: function() { toggleColumn( 'email' ); }
                    } ),
                    el( CheckboxControl, {
                        label: __( 'Phone', 'stackboost-for-supportcandy' ),
                        checked: attributes.visibleColumns.indexOf( 'phone' ) !== -1,
                        onChange: function() { toggleColumn( 'phone' ); }
                    } ),
                    el( CheckboxControl, {
                        label: __( 'Department', 'stackboost-for-supportcandy' ),
                        checked: attributes.visibleColumns.indexOf( 'department' ) !== -1,
                        onChange: function() { toggleColumn( 'department' ); }
                    } ),
                    el( CheckboxControl, {
                        label: __( 'Title', 'stackboost-for-supportcandy' ),
                        checked: attributes.visibleColumns.indexOf( 'title' ) !== -1,
                        onChange: function() { toggleColumn( 'title' ); }
                    } ),
                    el( RangeControl, {
                        label: __( 'Items Per Page', 'stackboost-for-supportcandy' ),
                        value: attributes.itemsPerPage,
                        onChange: function( val ) { setAttributes( { itemsPerPage: val } ); },
                        min: 1,
                        max: 100
                    } ),
                    el( ToggleControl, {
                        label: __( 'Allow User to Change Page Length', 'stackboost-for-supportcandy' ),
                        checked: attributes.allowPageLengthChange,
                        onChange: function( val ) { setAttributes( { allowPageLengthChange: val } ); }
                    } ),
                    el( SelectControl, {
                        label: __( 'Link Behavior', 'stackboost-for-supportcandy' ),
                        value: attributes.linkBehavior,
                        options: [
                            { label: __( 'Open Modal', 'stackboost-for-supportcandy' ), value: 'modal' },
                            { label: __( 'Link to Page', 'stackboost-for-supportcandy' ), value: 'page' },
                            { label: __( 'No Link', 'stackboost-for-supportcandy' ), value: 'none' }
                        ],
                        onChange: function( val ) { setAttributes( { linkBehavior: val } ); }
                    } )
                ),
                el( PanelBody, { title: __( 'Filters', 'stackboost-for-supportcandy' ), initialOpen: false },
                    ( ! departments ) ? el( 'p', {}, __( 'Loading departments...', 'stackboost-for-supportcandy' ) ) :
                    ( departments.length === 0 ) ? el( 'p', {}, __( 'No departments found.', 'stackboost-for-supportcandy' ) ) :
                    departments.map( function( dept ) {
                        var decodedTitle = decodeHTML( dept.title.rendered );
                        return el( CheckboxControl, {
                            key: dept.id,
                            label: decodedTitle,
                            checked: attributes.departmentFilter.indexOf( decodedTitle ) !== -1,
                            onChange: function() { toggleDepartment( decodedTitle ); }
                        } );
                    } )
                )
            ),
            el( ServerSideRender, {
                block: 'stackboost/directory',
                attributes: attributes
            } )
        );
    };

    // HOC to fetch departments
    var DirectoryEditWithData = withSelect( function( select ) {
        return {
            departments: select( 'core' ).getEntityRecords( 'postType', 'sb_department', { per_page: -1 } )
        };
    } )( DirectoryEdit );

    registerBlockType( 'stackboost/directory', {
        title: 'StackBoost Directory',
        icon: 'groups',
        category: 'widgets',
        edit: DirectoryEditWithData,
        save: function() {
            return null;
        },
    } );
} )( window.wp );
