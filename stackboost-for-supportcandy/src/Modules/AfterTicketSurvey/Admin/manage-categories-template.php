<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Template for managing survey categories.
 *
 * @var array $categories List of categories.
 */
?>
<div class="stackboost-ats-header-actions">
    <h2><?php esc_html_e( 'Manage Categories', 'stackboost-for-supportcandy' ); ?></h2>
    <button type="button" class="button button-primary" id="stackboost-ats-add-category"><?php esc_html_e( 'Add New Category', 'stackboost-for-supportcandy' ); ?></button>
</div>

<p class="description">
    <?php esc_html_e( 'Categories allow you to logically group rating questions (e.g., "Agent Performance", "Helpdesk Quality"). This allows you to measure these areas distinctly in the Ticket Metrics dashboard.', 'stackboost-for-supportcandy' ); ?>
</p>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th><?php esc_html_e( 'Category Name', 'stackboost-for-supportcandy' ); ?></th>
            <th><?php esc_html_e( 'Description', 'stackboost-for-supportcandy' ); ?></th>
            <th style="width: 100px; text-align:center;"><?php esc_html_e( 'Actions', 'stackboost-for-supportcandy' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if ( empty( $categories ) ) : ?>
            <tr>
                <td colspan="3" style="text-align:center;"><?php esc_html_e( 'No categories found. Create one to get started.', 'stackboost-for-supportcandy' ); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ( $categories as $cat ) : ?>
                <tr id="stackboost-ats-category-<?php echo esc_attr( $cat['id'] ); ?>">
                    <td><strong><?php echo esc_html( $cat['name'] ); ?></strong></td>
                    <td><?php echo esc_html( $cat['description'] ); ?></td>
                    <td style="text-align:center;">
                        <button type="button" class="stackboost-ats-edit-category" data-id="<?php echo esc_attr( $cat['id'] ); ?>" title="<?php esc_attr_e( 'Edit', 'stackboost-for-supportcandy' ); ?>" style="color:#d68a00; background:none; border:none; box-shadow:none; cursor:pointer;">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button type="button" class="stackboost-ats-delete-category" data-id="<?php echo esc_attr( $cat['id'] ); ?>" title="<?php esc_attr_e( 'Delete', 'stackboost-for-supportcandy' ); ?>" style="color:#d63638; background:none; border:none; box-shadow:none; cursor:pointer;">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- Category Modal -->
<div id="stackboost-ats-category-modal" title="<?php esc_attr_e( 'Manage Category', 'stackboost-for-supportcandy' ); ?>" style="display:none;">
    <form id="stackboost-ats-category-form">
        <input type="hidden" name="category_id" id="stackboost-ats-category-id" value="">

        <table class="form-table">
            <tr>
                <th scope="row"><label for="stackboost-ats-category-name"><?php esc_html_e( 'Name', 'stackboost-for-supportcandy' ); ?> <span class="required">*</span></label></th>
                <td>
                    <input type="text" name="name" id="stackboost-ats-category-name" class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="stackboost-ats-category-description"><?php esc_html_e( 'Description', 'stackboost-for-supportcandy' ); ?></label></th>
                <td>
                    <textarea name="description" id="stackboost-ats-category-description" rows="3" class="large-text"></textarea>
                </td>
            </tr>
        </table>
    </form>
</div>
