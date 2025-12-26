<?php
/**
 * StackBoost Directory Shortcode.
 *
 * This file defines the shortcode for displaying the Company Directory
 * on the front end. It is a migration of the shortcode from the
 * standalone plugin, adapted for the StackBoost framework.
 *
 * @package StackBoost
 * @subpackage Modules\Directory\Shortcodes
 */

namespace StackBoost\ForSupportCandy\Modules\Directory\Shortcodes;

use StackBoost\ForSupportCandy\Services\DirectoryService;
use StackBoost\ForSupportCandy\Modules\Directory\Data\CustomPostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * DirectoryShortcode Class
 *
 * Handles the [stackboost_directory] shortcode.
 */
class DirectoryShortcode {

	/**
	 * Constructor.
	 *
	 * @param CustomPostTypes $cpts An instance of the CustomPostTypes class.
	 */
	public function __construct( CustomPostTypes $cpts ) {
		add_shortcode( 'stackboost_directory', array( $this, 'render_directory_shortcode' ) );
	}

	/**
	 * Render the directory shortcode content.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output for the directory table.
	 */
	public function render_directory_shortcode( $atts ) {
		// Parse Attributes with Defaults
		$atts = shortcode_atts(
			array(
                'theme'                 => 'standard',
				'showSearch'            => true,
				'visibleColumns'        => array( 'name', 'phone', 'department', 'title' ),
				'itemsPerPage'          => 10,
				'allowPageLengthChange' => true,
				'linkBehavior'          => '', // Defaults to global setting if empty
				'departmentFilter'      => array(),
                'preferGravatar'        => false,
                'enableGravatarFallback'=> false,
                'photoShape'            => 'circle',
                'specificUsers'         => array(), // Array of objects { id, value } or just IDs
                'showOfficePhone'       => true,
                'showMobilePhone'       => true,
			),
			$atts,
			'stackboost_directory'
		);

		// Sanitize Attributes
        $theme                   = in_array( $atts['theme'], [ 'standard', 'modern' ], true ) ? $atts['theme'] : 'standard';
		$show_search             = filter_var( $atts['showSearch'], FILTER_VALIDATE_BOOLEAN );
		$allow_page_length_change = filter_var( $atts['allowPageLengthChange'], FILTER_VALIDATE_BOOLEAN );
		$items_per_page          = absint( $atts['itemsPerPage'] );
		$visible_columns         = is_array( $atts['visibleColumns'] ) ? $atts['visibleColumns'] : explode( ',', $atts['visibleColumns'] );
		$department_filter       = is_array( $atts['departmentFilter'] ) ? $atts['departmentFilter'] : ( ! empty( $atts['departmentFilter'] ) ? explode( ',', $atts['departmentFilter'] ) : array() );
		$link_behavior           = sanitize_key( $atts['linkBehavior'] );
        $prefer_gravatar         = filter_var( $atts['preferGravatar'], FILTER_VALIDATE_BOOLEAN );
        $enable_gravatar_fallback = filter_var( $atts['enableGravatarFallback'], FILTER_VALIDATE_BOOLEAN );
        $photo_shape             = in_array( $atts['photoShape'], [ 'circle', 'square', 'portrait', 'landscape' ], true ) ? $atts['photoShape'] : 'circle';

        $show_office_phone       = filter_var( $atts['showOfficePhone'], FILTER_VALIDATE_BOOLEAN );
        $show_mobile_phone       = filter_var( $atts['showMobilePhone'], FILTER_VALIDATE_BOOLEAN );

        // Sanitize Specific Users
        // Ideally comes as array of objects from block, but might be array of IDs or comma-separated string if used manually in shortcode
        $specific_users = $atts['specificUsers'];
        $specific_user_ids = [];
        if ( ! empty( $specific_users ) ) {
            if ( is_string( $specific_users ) ) {
                $specific_user_ids = array_map( 'absint', explode( ',', $specific_users ) );
            } elseif ( is_array( $specific_users ) ) {
                foreach ( $specific_users as $user ) {
                    if ( is_array( $user ) && isset( $user['id'] ) ) {
                        $specific_user_ids[] = absint( $user['id'] );
                    } elseif ( is_numeric( $user ) ) {
                        $specific_user_ids[] = absint( $user );
                    }
                }
            }
        }

		// Normalize columns (trim whitespace)
		$visible_columns = array_map( 'trim', $visible_columns );

		// Fetch Employees
		$directory_service = DirectoryService::get_instance();

        // Logic: If specific users are selected, fetch ONLY them (regardless of 'active' status flag, to allow flexibility).
        // Otherwise, fetch all 'active' employees and apply department filters.
        if ( ! empty( $specific_user_ids ) ) {
            $employees = [];
            foreach ( $specific_user_ids as $id ) {
                $employee = $directory_service->retrieve_employee_data( $id );

                // Check for Privacy
                $is_private = get_post_meta( $id, '_private', true ) === 'Yes';

                if ( $employee && ! $is_private ) {
                    $employees[] = $employee;
                }
            }
        } else {
            $employees = $directory_service->get_all_active_employees_for_shortcode();

            // Filter Employees by Department (if set)
            if ( ! empty( $department_filter ) ) {
                $employees = array_filter( $employees, function( $employee ) use ( $department_filter ) {
                    // Department is stored as a string name in employee object
                    return in_array( $employee->department_program, $department_filter, true );
                } );
            }
        }

		$directory_wordpress = \StackBoost\ForSupportCandy\Modules\Directory\WordPress::get_instance();
		$can_edit_entries    = $directory_wordpress->can_user_edit();

		$settings             = get_option( \StackBoost\ForSupportCandy\Modules\Directory\Admin\Settings::OPTION_NAME, array() );
		$global_link_mode     = $settings['listing_display_mode'] ?? 'page';

		// Determine final link mode: attribute overrides global if set, unless 'none'
		$listing_display_mode = ( ! empty( $link_behavior ) && in_array( $link_behavior, [ 'modal', 'page', 'none' ], true ) ) ? $link_behavior : $global_link_mode;

        // Modern Mode requires a specific class on the table to trigger CSS Grid layout in DataTables
        // or we render a completely different structure. DataTables is great for search/sort/pagination.
        // We can use DataTables for the heavy lifting but style the rows as cards.
        $table_classes = 'display stackboost-staff-directory-table';
        if ( 'modern' === $theme ) {
            $table_classes .= ' stackboost-directory-modern-grid';
        }

		ob_start();
		?>
		<div class="stackboost-staff-directory-container stackboost-theme-<?php echo esc_attr( $theme ); ?>">
            <?php if ( defined( 'REST_REQUEST' ) && REST_REQUEST && wp_is_json_request() ) : ?>
                <!-- Editor Preview Controls (Visual Only) -->
                 <div class="stackboost-directory-editor-controls" style="display: flex; justify-content: space-between; margin-bottom: 10px; opacity: 0.6; pointer-events: none;">
                    <?php if ( $allow_page_length_change ) : ?>
                        <div class="dataTables_length">
                            <label>
                                <?php esc_html_e( 'Show', 'stackboost-for-supportcandy' ); ?>
                                <select name="stackboostStaffDirectoryTable_length" aria-controls="stackboostStaffDirectoryTable" style="margin: 0 5px;">
                                    <option value="<?php echo esc_attr( $items_per_page ); ?>"><?php echo esc_html( $items_per_page ); ?></option>
                                </select>
                                <?php esc_html_e( 'entries', 'stackboost-for-supportcandy' ); ?>
                            </label>
                        </div>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>

                    <?php if ( $show_search ) : ?>
                        <div class="dataTables_filter">
                            <label>
                                <?php esc_html_e( 'Search:', 'stackboost-for-supportcandy' ); ?>
                                <input type="search" placeholder="" aria-controls="stackboostStaffDirectoryTable" style="margin-left: 5px; border: 1px solid #ddd; padding: 2px 5px;">
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

			<div id="stackboost-full-directory-table-wrapper">
				<?php if ( ! empty( $employees ) ) : ?>
					<table id="stackboostStaffDirectoryTable"
						   class="<?php echo esc_attr( $table_classes ); ?>"
						   data-search-enabled="<?php echo $show_search ? 'true' : 'false'; ?>"
						   data-page-length="<?php echo esc_attr( $items_per_page ); ?>"
						   data-length-change-enabled="<?php echo $allow_page_length_change ? 'true' : 'false'; ?>">
						<thead>
							<tr>
                                <?php // Modern grid uses a hidden thead generally, or we style it away. But for accessibility/datatables it needs to exist. ?>

                                <?php if ( in_array( 'photo', $visible_columns, true ) ) : ?>
									<th class="sb-col-photo"><?php esc_html_e( '', 'stackboost-for-supportcandy' ); // No label for photo ?></th>
								<?php endif; ?>

                                <?php if ( in_array( 'name', $visible_columns, true ) ) : ?>
									<th class="sb-col-name"><?php esc_html_e( 'Name', 'stackboost-for-supportcandy' ); ?></th>
								<?php endif; ?>
								<?php if ( in_array( 'phone', $visible_columns, true ) ) : ?>
									<th class="sb-col-phone"><?php esc_html_e( 'Phone', 'stackboost-for-supportcandy' ); ?></th>
								<?php endif; ?>
								<?php if ( in_array( 'department', $visible_columns, true ) ) : ?>
									<th class="sb-col-dept"><?php esc_html_e( 'Department / Program', 'stackboost-for-supportcandy' ); ?></th>
								<?php endif; ?>
								<?php if ( in_array( 'title', $visible_columns, true ) ) : ?>
									<th class="sb-col-title"><?php esc_html_e( 'Title', 'stackboost-for-supportcandy' ); ?></th>
								<?php endif; ?>
                                <?php if ( in_array( 'location', $visible_columns, true ) ) : ?>
                                    <th class="sb-col-location"><?php esc_html_e( 'Location', 'stackboost-for-supportcandy' ); ?></th>
                                <?php endif; ?>
                                <?php if ( in_array( 'room_number', $visible_columns, true ) ) : ?>
                                    <th class="sb-col-room"><?php esc_html_e( 'Room #', 'stackboost-for-supportcandy' ); ?></th>
                                <?php endif; ?>
							</tr>
						</thead>
						<tbody>
							<?php
							$allowed_html = array(
								'strong' => array(),
								'br'     => array(),
								'a'      => array(
									'href'            => true,
									'class'           => true,
									'data-post-id'    => true,
									'data-wp-nonce'   => true,
								),
								'span'   => array(
									'class'          => true,
									'data-phone'     => true,
									'data-extension' => true,
									'data-copy-text' => true,
									'title'          => true,
									'data-email'     => true,
									'style'          => true, // Allow style for inline styling of icons
								),
								'svg'    => array(
									'class'   => true,
									'xmlns'   => true,
									'width'   => true,
									'height'  => true,
									'viewbox' => true,
									'fill'    => true,
									'style'   => true,
								),
								'path'   => array(
									'd' => true,
								),
								'td'     => array(
									'data-search' => true,
									'class'       => true,
								),
                                'img'    => array(
                                    'src' => true,
                                    'class' => true,
                                    'alt' => true,
                                ),
                                'div'    => array(
                                    'class' => true,
                                    'style' => true,
                                ),
							);

							foreach ( $employees as $employee ) :
                                // Prepare Phone Output based on granular settings
								$searchable_phone_string = '';
                                $html_lines = [];

                                // We can reuse logic from Service but need to apply filters.
                                // It's cleaner to use the Service method if we could filter inside it, but Service is unaware of Shortcode atts.
                                // We will replicate the logic here or wrap the service output.
                                // Actually, standardizing this logic is better. Let's assume we modify the output or re-generate it.
                                // Service::get_formatted_phone_numbers_html generates both if present.
                                // We can rebuild it here to respect flags.

                                $copy_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16px" height="16px" style="vertical-align: middle; margin-left: 5px; cursor: pointer;"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>';

                                if ( $show_office_phone && ! empty( $employee->office_phone ) ) {
                                    $searchable_phone_string .= preg_replace( '/\D/', '', $employee->office_phone . $employee->extension );

                                    $formatted_office_phone = $directory_service->format_phone_number_string( $employee->office_phone );
                                    // Manually build URI as helper is private.
                                    // Actually we can duplicate the simple URI logic or make it public.
                                    // For now, simple duplication is safe.
                                    $clean_number = preg_replace( '/[^0-9+]/', '', $employee->office_phone );
                                    $office_tel_uri = 'tel:' . $clean_number;
                                    if ( ! empty( $employee->extension ) ) {
                                        $clean_extension = preg_replace( '/[^0-9]/', '', $employee->extension );
                                        $office_tel_uri .= ';ext=' . $clean_extension;
                                    }

                                    $office_link = '<a href="' . esc_url( $office_tel_uri ) . '">' . $formatted_office_phone . '</a>';
                                    $office_line = '<span class="dashicons dashicons-building" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle; margin-right: 5px; color: #555;" title="' . esc_attr__( 'Office', 'stackboost-for-supportcandy' ) . '"></span>' . $office_link;
                                    if ( ! empty( $employee->extension ) ) {
                                        $office_line .= ' <span style="color: #777; font-size: 0.9em;">' . esc_html__( 'ext.', 'stackboost-for-supportcandy' ) . ' ' . esc_html( $employee->extension ) . '</span>';
                                    }

                                    // Copy text
                                    $office_copy_text = $formatted_office_phone;
                                    if ( ! empty( $employee->extension ) ) {
                                        $office_copy_text .= ' ' . esc_html__( 'ext.', 'stackboost-for-supportcandy' ) . ' ' . $employee->extension;
                                    }
                                    $office_line .= sprintf(
                                        ' <span class="stackboost-copy-phone-icon" data-phone="%s" data-extension="%s" data-copy-text="%s" title="%s">%s</span>',
                                        esc_attr( $employee->office_phone ),
                                        esc_attr( $employee->extension ),
                                        esc_attr( $office_copy_text ),
                                        esc_attr__( 'Click to copy phone', 'stackboost-for-supportcandy' ),
                                        $copy_icon_svg
                                    );

                                    $html_lines[] = $office_line;
                                }

                                if ( $show_mobile_phone && ! empty( $employee->mobile_phone ) ) {
                                    $searchable_phone_string .= preg_replace( '/\D/', '', $employee->mobile_phone );

                                    $formatted_mobile_phone = $directory_service->format_phone_number_string( $employee->mobile_phone );
                                    $clean_number = preg_replace( '/[^0-9+]/', '', $employee->mobile_phone );
                                    $mobile_tel_uri = 'tel:' . $clean_number;

                                    $mobile_link = '<a href="' . esc_url( $mobile_tel_uri ) . '">' . $formatted_mobile_phone . '</a>';
                                    $mobile_line = '<span class="dashicons dashicons-smartphone" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle; margin-right: 5px; color: #555;" title="' . esc_attr__( 'Mobile', 'stackboost-for-supportcandy' ) . '"></span>' . $mobile_link;

                                    $mobile_copy_text = $formatted_mobile_phone;
                                    $mobile_line .= sprintf(
                                        ' <span class="stackboost-copy-phone-icon" data-phone="%s" data-extension="" data-copy-text="%s" title="%s">%s</span>',
                                        esc_attr( $employee->mobile_phone ),
                                        esc_attr( $mobile_copy_text ),
                                        esc_attr__( 'Click to copy phone', 'stackboost-for-supportcandy' ),
                                        $copy_icon_svg
                                    );

                                    $html_lines[] = $mobile_line;
                                }

								$formatted_phone_output  = implode( '<br>', $html_lines );


                                // Determine Photo URL based on preferences
                                $display_photo_url = '';
                                $custom_photo = $employee->custom_photo_url;
                                $gravatar_url = $employee->gravatar_url;
                                $placeholder_url = $employee->placeholder_url;

                                if ( $prefer_gravatar ) {
                                    if ( ! empty( $gravatar_url ) ) {
                                        $display_photo_url = $gravatar_url;
                                    } elseif ( ! empty( $custom_photo ) ) {
                                        $display_photo_url = $custom_photo;
                                    } elseif ( $enable_gravatar_fallback && ! empty( $gravatar_url ) ) {
                                         // Should have been caught by first if, but rigorous check
                                         $display_photo_url = $gravatar_url;
                                    } else {
                                        $display_photo_url = $placeholder_url;
                                    }
                                } else {
                                    // Prefer Custom
                                    if ( ! empty( $custom_photo ) ) {
                                        $display_photo_url = $custom_photo;
                                    } elseif ( $enable_gravatar_fallback && ! empty( $gravatar_url ) ) {
                                        $display_photo_url = $gravatar_url;
                                    } else {
                                        $display_photo_url = $placeholder_url;
                                    }
                                }

                                // If absolutely nothing resolved (should be placeholder at least), fallback
                                if ( empty( $display_photo_url ) ) {
                                    $display_photo_url = $placeholder_url;
                                }

                                // Prepare Photo HTML with Shape Class
                                $photo_class = 'stackboost-directory-avatar sb-shape-' . esc_attr( $photo_shape );
                                $photo_html = sprintf( '<img src="%s" class="%s" alt="%s">', esc_url( $display_photo_url ), esc_attr( $photo_class ), esc_attr( $employee->name ) );

                                ?>
								<tr>
                                    <?php if ( in_array( 'photo', $visible_columns, true ) ) : ?>
                                        <td class="sb-col-photo">
                                            <?php if ( 'modern' === $theme ) : ?>
                                                <div class="sb-modern-avatar-wrapper"><?php echo wp_kses( $photo_html, $allowed_html ); ?></div>
                                            <?php else : ?>
                                                <?php echo wp_kses( $photo_html, $allowed_html ); ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>

									<?php if ( in_array( 'name', $visible_columns, true ) ) : ?>
										<td class="sb-col-name">
											<?php if ( 'modal' === $listing_display_mode ) : ?>
												<a href="#" class="stackboost-modal-trigger" data-post-id="<?php echo esc_attr( $employee->id ); ?>"><?php echo esc_html( $employee->name ); ?></a>
											<?php elseif ( 'page' === $listing_display_mode ) : ?>
												<a href="<?php echo esc_url( $employee->permalink ); ?>"><?php echo esc_html( $employee->name ); ?></a>
											<?php else : // 'none' ?>
												<?php echo esc_html( $employee->name ); ?>
											<?php endif; ?>

											<?php if ( $can_edit_entries && $employee->edit_post_link ) : ?>
												<a href="<?php echo esc_url( $employee->edit_post_link ); ?>" title="<?php esc_attr_e( 'Edit this entry', 'stackboost-for-supportcandy' ); ?>" style="margin-left: 5px;">
													<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16px" height="16px" style="vertical-align: middle; cursor: pointer;">
														<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
													</svg>
												</a>
											<?php endif; ?>

                                            <?php
                                            // Email Display Logic
                                            $show_email_text = in_array( 'email', $visible_columns, true );
                                            ?>

											<?php if ( ! empty( $employee->email ) ) : ?>
                                                <?php if ( $show_email_text ) : ?>
                                                    <div class="stackboost-directory-email-wrapper" style="font-size: 0.9em; margin-top: 3px;">
                                                        <a href="mailto:<?php echo esc_attr( $employee->email ); ?>"><?php echo esc_html( $employee->email ); ?></a>
                                                        <span class="stackboost-copy-email-icon"
                                                              data-email="<?php echo esc_attr( $employee->email ); ?>"
                                                              title="<?php esc_attr_e( 'Click to copy email', 'stackboost-for-supportcandy' ); ?>"
                                                              style="margin-left: 5px;">
                                                            <?php echo wp_kses( $copy_icon_svg, $allowed_html ); ?>
                                                        </span>
                                                    </div>
                                                <?php else : ?>
                                                    <span class="stackboost-copy-email-icon"
                                                          data-email="<?php echo esc_attr( $employee->email ); ?>"
                                                          title="<?php esc_attr_e( 'Click to copy email', 'stackboost-for-supportcandy' ); ?>">
                                                        <?php echo wp_kses( $copy_icon_svg, $allowed_html ); ?>
                                                    </span>
                                                <?php endif; ?>
											<?php endif; ?>
										</td>
									<?php endif; ?>

									<?php if ( in_array( 'phone', $visible_columns, true ) ) : ?>
										<td class="sb-col-phone" data-search="<?php echo esc_attr( $searchable_phone_string ); ?>"><?php echo ! empty( $formatted_phone_output ) ? wp_kses( $formatted_phone_output, $allowed_html ) : '&mdash;'; ?></td>
									<?php endif; ?>

									<?php if ( in_array( 'department', $visible_columns, true ) ) : ?>
										<td class="sb-col-dept"><?php echo esc_html( $employee->department_program ); ?></td>
									<?php endif; ?>

									<?php if ( in_array( 'title', $visible_columns, true ) ) : ?>
										<td class="sb-col-title"><?php echo esc_html( $employee->job_title ); ?></td>
									<?php endif; ?>

                                    <?php if ( in_array( 'location', $visible_columns, true ) ) : ?>
                                        <td class="sb-col-location"><?php echo esc_html( $employee->location_name ); ?></td>
                                    <?php endif; ?>

                                    <?php if ( in_array( 'room_number', $visible_columns, true ) ) : ?>
                                        <td class="sb-col-room"><?php echo esc_html( $employee->room_number ); ?></td>
                                    <?php endif; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e( 'No directory entries found.', 'stackboost-for-supportcandy' ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( 'modal' === $listing_display_mode ) : ?>
				<div id="stackboost-staff-modal" class="stackboost-modal" style="display: none;">
					<div class="stackboost-modal-content">
						<span class="stackboost-modal-close">&times;</span>
						<div class="stackboost-modal-body">
							<!-- Content will be loaded here via AJAX -->
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

}
