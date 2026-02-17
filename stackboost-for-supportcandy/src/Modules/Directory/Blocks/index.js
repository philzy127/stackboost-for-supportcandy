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
    var TextControl = wp.components.TextControl;
    var Button = wp.components.Button;
    var Spinner = wp.components.Spinner;
    var __ = wp.i18n.__;
    var el = wp.element.createElement;
    var withSelect = wp.data.withSelect;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useDebounce = wp.compose.useDebounce;

    // Specific User Search Component
    var UserSearchControl = function( props ) {
        var specificUsers = props.specificUsers; // Array of { id, value }
        var setAttributes = props.setAttributes;

        var [ searchTerm, setSearchTerm ] = useState( '' );
        var [ suggestions, setSuggestions ] = useState( [] );
        var [ isLoading, setIsLoading ] = useState( false );

        // Debounced search function
        var debouncedSearch = useDebounce( function( term ) {
            if ( ! term ) {
                setSuggestions( [] );
                setIsLoading( false );
                return;
            }

            setIsLoading( true );

            wp.data.resolveSelect( 'core' ).getEntityRecords( 'postType', 'sb_staff_dir', {
                search: term,
                per_page: 10,
                status: 'publish',
                _fields: ['id', 'title'] // Optimize fetch
            } )
            .then( function( records ) {
                if ( records ) {
                    // Map records to { id, value } format
                    var results = records.map( function( record ) {
                        return {
                            id: record.id,
                            value: record.title.rendered
                        };
                    } );
                    setSuggestions( results );
                } else {
                    setSuggestions( [] );
                }
                setIsLoading( false );
            } )
            .catch( function() {
                setSuggestions( [] );
                setIsLoading( false );
            } );
        }, 500 );

        // Trigger search on input change
        useEffect( function() {
            debouncedSearch( searchTerm );
        }, [ searchTerm ] );

        var addUser = function( user ) {
            // Check for duplicates
            var exists = specificUsers.some( function( u ) { return u.id === user.id; } );
            if ( ! exists ) {
                var newUsers = specificUsers.concat( [ user ] );
                setAttributes( { specificUsers: newUsers } );
            }
            setSearchTerm( '' );
            setSuggestions( [] );
        };

        var removeUser = function( userId ) {
            var newUsers = specificUsers.filter( function( u ) { return u.id !== userId; } );
            setAttributes( { specificUsers: newUsers } );
        };

        return el( 'div', { className: 'stackboost-user-search-control' },
            el( TextControl, {
                label: __( 'Search Specific Users', 'stackboost-for-supportcandy' ),
                value: searchTerm,
                onChange: function( val ) { setSearchTerm( val ); },
                placeholder: __( 'Type name to search...', 'stackboost-for-supportcandy' ),
                __next40pxDefaultSize: true,
                __nextHasNoMarginBottom: true
            } ),
            isLoading && el( Spinner ),
            ! isLoading && searchTerm && suggestions.length === 0 && el( 'p', { style: { fontStyle: 'italic', color: '#666' } }, __( 'No results found.', 'stackboost-for-supportcandy' ) ),
            suggestions.length > 0 && el( 'ul', { className: 'stackboost-user-search-results', style: { border: '1px solid #ddd', maxHeight: '150px', overflowY: 'auto', padding: '0', margin: '0 0 10px 0', listStyle: 'none' } },
                suggestions.map( function( user ) {
                    return el( 'li', { key: user.id, style: { padding: '5px', cursor: 'pointer', borderBottom: '1px solid #eee' }, onClick: function() { addUser( user ); } }, user.value );
                } )
            ),
            el( 'div', { className: 'stackboost-selected-users-list' },
                specificUsers.length > 0 && el( 'p', { style: { fontWeight: 'bold', marginBottom: '5px' } }, __( 'Selected Users:', 'stackboost-for-supportcandy' ) ),
                specificUsers.map( function( user ) {
                    return el( 'div', { key: user.id, style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', background: '#f0f0f0', padding: '5px', marginBottom: '5px', borderRadius: '4px' } },
                        el( 'span', {}, user.value ),
                        el( Button, {
                            icon: 'dismiss',
                            label: __( 'Remove', 'stackboost-for-supportcandy' ),
                            isSmall: true,
                            onClick: function() { removeUser( user.id ); },
                            style: { minWidth: '24px', height: '24px', padding: 0 }
                        } )
                    );
                } )
            )
        );
    };

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
                        onChange: function( val ) { setAttributes( { theme: val } ); },
                        __next40pxDefaultSize: true,
                        __nextHasNoMarginBottom: true
                    } ),
                    el( ToggleControl, {
                        label: __( 'Show Search Bar', 'stackboost-for-supportcandy' ),
                        checked: attributes.showSearch,
                        onChange: function( val ) { setAttributes( { showSearch: val } ); },
                        __nextHasNoMarginBottom: true
                    } ),
                    el( TextControl, {
                        label: __( 'Search Bar Width', 'stackboost-for-supportcandy' ),
                        help: __( 'Enter a width (e.g., "300px" or "50%"). Leave empty for default.', 'stackboost-for-supportcandy' ),
                        value: attributes.searchBarWidth,
                        onChange: function( val ) { setAttributes( { searchBarWidth: val } ); },
                        __next40pxDefaultSize: true,
                        __nextHasNoMarginBottom: true
                    } ),
                    el( SelectControl, {
                        label: __( 'Controls Alignment', 'stackboost-for-supportcandy' ),
                        value: attributes.headerAlignment,
                        options: [
                            { label: __( 'Split (Space Between)', 'stackboost-for-supportcandy' ), value: 'space-between' },
                            { label: __( 'Left', 'stackboost-for-supportcandy' ), value: 'flex-start' },
                            { label: __( 'Center', 'stackboost-for-supportcandy' ), value: 'center' },
                            { label: __( 'Right', 'stackboost-for-supportcandy' ), value: 'flex-end' }
                        ],
                        onChange: function( val ) { setAttributes( { headerAlignment: val } ); },
                        __next40pxDefaultSize: true,
                        __nextHasNoMarginBottom: true
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
                        onChange: function( val ) { setAttributes( { photoShape: val } ); },
                        __next40pxDefaultSize: true,
                        __nextHasNoMarginBottom: true
                    } ),
                    el( ToggleControl, {
                        label: __( 'Prefer Gravatar', 'stackboost-for-supportcandy' ),
                        help: __( 'If enabled, always try to show Gravatar first.', 'stackboost-for-supportcandy' ),
                        checked: attributes.preferGravatar,
                        onChange: function( val ) {
                            if ( val === true ) {
                                if ( typeof window.stackboostConfirm === 'function' ) {
                                    window.stackboostConfirm(
                                        __( 'Warning: Enabling Gravatars will expose visitor IP addresses to Automattic/Gravatar.com. This may violate GDPR/privacy policies. Do you strictly consent to this external connection?', 'stackboost-for-supportcandy' ),
                                        __( 'GDPR / Privacy Warning', 'stackboost-for-supportcandy' ),
                                        function() { setAttributes( { preferGravatar: true } ); },
                                        function() { /* Cancelled, do nothing */ },
                                        __( 'I Consent', 'stackboost-for-supportcandy' ),
                                        __( 'Cancel', 'stackboost-for-supportcandy' ),
                                        true // isDanger
                                    );
                                } else if ( window.confirm( __( 'Warning: Enabling Gravatars will expose visitor IP addresses to Automattic/Gravatar.com. This may violate GDPR/privacy policies. Do you strictly consent to this external connection?', 'stackboost-for-supportcandy' ) ) ) {
                                    setAttributes( { preferGravatar: val } );
                                }
                            } else {
                                setAttributes( { preferGravatar: val } );
                            }
                        },
                        __nextHasNoMarginBottom: true
                    } ),
                    el( ToggleControl, {
                        label: __( 'Fallback to Gravatar', 'stackboost-for-supportcandy' ),
                        help: __( 'If no custom photo is found, try Gravatar.', 'stackboost-for-supportcandy' ),
                        checked: attributes.enableGravatarFallback,
                        onChange: function( val ) {
                            if ( val === true ) {
                                if ( typeof window.stackboostConfirm === 'function' ) {
                                    window.stackboostConfirm(
                                        __( 'Warning: Enabling Gravatars will expose visitor IP addresses to Automattic/Gravatar.com. This may violate GDPR/privacy policies. Do you strictly consent to this external connection?', 'stackboost-for-supportcandy' ),
                                        __( 'GDPR / Privacy Warning', 'stackboost-for-supportcandy' ),
                                        function() { setAttributes( { enableGravatarFallback: true } ); },
                                        function() { /* Cancelled, do nothing */ },
                                        __( 'I Consent', 'stackboost-for-supportcandy' ),
                                        __( 'Cancel', 'stackboost-for-supportcandy' ),
                                        true // isDanger
                                    );
                                } else if ( window.confirm( __( 'Warning: Enabling Gravatars will expose visitor IP addresses to Automattic/Gravatar.com. This may violate GDPR/privacy policies. Do you strictly consent to this external connection?', 'stackboost-for-supportcandy' ) ) ) {
                                    setAttributes( { enableGravatarFallback: val } );
                                }
                            } else {
                                setAttributes( { enableGravatarFallback: val } );
                            }
                        },
                        __nextHasNoMarginBottom: true
                    } ),
                    el( 'p', { className: 'components-base-control__label' }, __( 'Visible Fields', 'stackboost-for-supportcandy' ) ),
                    el( CheckboxControl, {
                        label: __( 'Photo', 'stackboost-for-supportcandy' ),
                        checked: attributes.visibleColumns.indexOf( 'photo' ) !== -1,
                        onChange: function() { toggleColumn( 'photo' ); },
                        __nextHasNoMarginBottom: true
                    } ),
                    el( CheckboxControl, {
                        label: __( 'Name', 'stackboost-for-supportcandy' ),
                        checked: attributes.visibleColumns.indexOf( 'name' ) !== -1,
                        onChange: function() { toggleColumn( 'name' ); },
                        disabled: true, // Name should probably always be visible
                        __nextHasNoMarginBottom: true
                    } ),
                    el( CheckboxControl, {
                        label: __( 'Email', 'stackboost-for-supportcandy' ),
                        checked: attributes.visibleColumns.indexOf( 'email' ) !== -1,
                        onChange: function() { toggleColumn( 'email' ); },
                        help: __( 'Displayed below the name.', 'stackboost-for-supportcandy' ),
                        __nextHasNoMarginBottom: true
                    } ),
                    el( CheckboxControl, {
                        label: __( 'Phone', 'stackboost-for-supportcandy' ),
                        checked: attributes.visibleColumns.indexOf( 'phone' ) !== -1,
                        onChange: function() { toggleColumn( 'phone' ); },
                        __nextHasNoMarginBottom: true
                    } ),
                    // Phone granular options - only show if Phone is checked
                    attributes.visibleColumns.indexOf( 'phone' ) !== -1 && el( 'div', { style: { marginLeft: '20px', marginBottom: '10px' } },
                        el( ToggleControl, {
                            label: __( 'Show Office Phone', 'stackboost-for-supportcandy' ),
                            checked: attributes.showOfficePhone,
                            onChange: function( val ) { setAttributes( { showOfficePhone: val } ); },
                            __nextHasNoMarginBottom: true
                        } ),
                        el( ToggleControl, {
                            label: __( 'Show Mobile Phone', 'stackboost-for-supportcandy' ),
                            checked: attributes.showMobilePhone,
                            onChange: function( val ) { setAttributes( { showMobilePhone: val } ); },
                            __nextHasNoMarginBottom: true
                        } )
                    ),
                    el( CheckboxControl, {
                        label: __( 'Department', 'stackboost-for-supportcandy' ),
                        checked: attributes.visibleColumns.indexOf( 'department' ) !== -1,
                        onChange: function() { toggleColumn( 'department' ); },
                        __nextHasNoMarginBottom: true
                    } ),
                    el( CheckboxControl, {
                        label: __( 'Title', 'stackboost-for-supportcandy' ),
                        checked: attributes.visibleColumns.indexOf( 'title' ) !== -1,
                        onChange: function() { toggleColumn( 'title' ); },
                        __nextHasNoMarginBottom: true
                    } ),
                    el( CheckboxControl, {
                        label: __( 'Location', 'stackboost-for-supportcandy' ),
                        checked: attributes.visibleColumns.indexOf( 'location' ) !== -1,
                        onChange: function() { toggleColumn( 'location' ); },
                        __nextHasNoMarginBottom: true
                    } ),
                    el( CheckboxControl, {
                        label: __( 'Room Number', 'stackboost-for-supportcandy' ),
                        checked: attributes.visibleColumns.indexOf( 'room_number' ) !== -1,
                        onChange: function() { toggleColumn( 'room_number' ); },
                        __nextHasNoMarginBottom: true
                    } ),
                    el( RangeControl, {
                        label: __( 'Items Per Page', 'stackboost-for-supportcandy' ),
                        value: attributes.itemsPerPage,
                        onChange: function( val ) { setAttributes( { itemsPerPage: val } ); },
                        min: 1,
                        max: 100,
                        __next40pxDefaultSize: true,
                        __nextHasNoMarginBottom: true
                    } ),
                    el( ToggleControl, {
                        label: __( 'Allow User to Change Page Length', 'stackboost-for-supportcandy' ),
                        checked: attributes.allowPageLengthChange,
                        onChange: function( val ) { setAttributes( { allowPageLengthChange: val } ); },
                        __nextHasNoMarginBottom: true
                    } ),
                    el( SelectControl, {
                        label: __( 'Link Behavior', 'stackboost-for-supportcandy' ),
                        value: attributes.linkBehavior,
                        options: [
                            { label: __( 'Open Modal', 'stackboost-for-supportcandy' ), value: 'modal' },
                            { label: __( 'Link to Page', 'stackboost-for-supportcandy' ), value: 'page' },
                            { label: __( 'No Link', 'stackboost-for-supportcandy' ), value: 'none' }
                        ],
                        onChange: function( val ) { setAttributes( { linkBehavior: val } ); },
                        __next40pxDefaultSize: true,
                        __nextHasNoMarginBottom: true
                    } )
                ),
                el( PanelBody, { title: __( 'Filters', 'stackboost-for-supportcandy' ), initialOpen: false },
                    el( 'p', {}, __( 'Specific Users Override:', 'stackboost-for-supportcandy' ) ),
                    el( UserSearchControl, {
                        specificUsers: attributes.specificUsers,
                        setAttributes: setAttributes
                    } ),
                    el( 'hr', {} ),
                    el( 'p', {}, __( 'Or Filter by Department:', 'stackboost-for-supportcandy' ) ),
                    ( ! departments ) ? el( 'p', {}, __( 'Loading departments...', 'stackboost-for-supportcandy' ) ) :
                    ( departments.length === 0 ) ? el( 'p', {}, __( 'No departments found.', 'stackboost-for-supportcandy' ) ) :
                    departments.map( function( dept ) {
                        var decodedTitle = decodeHTML( dept.title.rendered );
                        return el( CheckboxControl, {
                            key: dept.id,
                            label: decodedTitle,
                            checked: attributes.departmentFilter.indexOf( decodedTitle ) !== -1,
                            onChange: function() { toggleDepartment( decodedTitle ); },
                            __nextHasNoMarginBottom: true
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
            departments: select( 'core' ).getEntityRecords( 'postType', 'sb_department', { per_page: -1, status: 'publish' } )
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
