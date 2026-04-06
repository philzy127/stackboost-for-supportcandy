jQuery(document).ready(function($) {
    if ( typeof stackboost_ats_manage === 'undefined' ) {
        return;
    }

    const modal = $( '#stackboost-ats-category-modal' ).dialog({
        autoOpen: false,
        modal: true,
        width: 500,
        buttons: {
            'Save Category': function() {
                saveCategory();
            },
            Cancel: function() {
                $( this ).dialog( 'close' );
            }
        },
        close: function() {
            resetCategoryForm();
        }
    });

    $( '#stackboost-ats-add-category' ).on( 'click', function() {
        modal.dialog( 'option', 'title', 'Add New Category' );
        modal.dialog( 'open' );
    });

    $( document ).on( 'click', '.stackboost-ats-edit-category', function() {
        const id = $( this ).data( 'id' );

        $.post( stackboost_ats_manage.ajax_url, {
            action: 'stackboost_ats_get_category',
            nonce: stackboost_ats_manage.nonce,
            category_id: id
        }, function( response ) {
            if ( response.success ) {
                const data = response.data;
                $( '#stackboost-ats-category-id' ).val( data.id );
                $( '#stackboost-ats-category-name' ).val( data.name );
                $( '#stackboost-ats-category-description' ).val( data.description );

                modal.dialog( 'option', 'title', 'Edit Category' );
                modal.dialog( 'open' );
            } else {
                alert( 'Error fetching category: ' + response.data );
            }
        });
    });

    $( document ).on( 'click', '.stackboost-ats-delete-category', function() {
        if ( confirm( 'Are you sure you want to delete this category? Questions assigned to it will revert to "No Category".' ) ) {
            const id = $( this ).data( 'id' );

            $.post( stackboost_ats_manage.ajax_url, {
                action: 'stackboost_ats_delete_category',
                nonce: stackboost_ats_manage.nonce,
                category_id: id
            }, function( response ) {
                if ( response.success ) {
                    location.reload();
                } else {
                    alert( 'Error deleting category: ' + response.data );
                }
            });
        }
    });

    function saveCategory() {
        const data = {
            action: 'stackboost_ats_save_category',
            nonce: stackboost_ats_manage.nonce,
            category_id: $( '#stackboost-ats-category-id' ).val(),
            name: $( '#stackboost-ats-category-name' ).val(),
            description: $( '#stackboost-ats-category-description' ).val()
        };

        if ( !data.name ) {
            alert( 'Category name is required.' );
            return;
        }

        $.post( stackboost_ats_manage.ajax_url, data, function( response ) {
            if ( response.success ) {
                location.reload();
            } else {
                alert( 'Error saving category: ' + response.data );
            }
        });
    }

    function resetCategoryForm() {
        $( '#stackboost-ats-category-form' )[0].reset();
        $( '#stackboost-ats-category-id' ).val( '' );
    }
});