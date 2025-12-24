<?php

namespace StackBoost\ForSupportCandy\Modules\AfterHoursNotice;

use StackBoost\ForSupportCandy\Core\Module;
use StackBoost\ForSupportCandy\Modules\AfterHoursNotice\Core as AfterHoursNoticeCore;
use DateTime;
use DateTimeZone;

/**
 * WordPress Adapter for the After Hours Notice feature.
 *
 * This class handles all the WordPress-specific implementations, like
 * registering hooks, settings, and displaying the notice on the frontend.
 *
 * @package StackBoost\ForSupportCandy\Modules\AfterHoursNotice
 */
class WordPress extends Module {

	/**
	 * The single instance of the class.
	 * @var WordPress|null
	 */
	private static ?WordPress $instance = null;

	/**
	 * The core logic class instance.
	 * @var AfterHoursNoticeCore
	 */
	private AfterHoursNoticeCore $core;

	/**
	 * Get the single instance of the class.
	 */
	public static function get_instance(): WordPress {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Feature gating check.
		if ( ! stackboost_is_feature_active( 'after_hours_notice' ) ) {
			return;
		}

		$this->core = new AfterHoursNoticeCore();
		parent::__construct();
	}

	/**
	 * Get the slug for this module.
	 * @return string
	 */
	public function get_slug(): string {
		return 'after_hours_notice';
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'wpsc_before_create_ticket_form', [ $this, 'display_notice' ] );
		add_filter( 'wpsc_create_ticket_email_data', [ $this, 'add_after_hours_notice_to_email' ] );
		add_filter( 'wpsc_agent_reply_email_data', [ $this, 'add_after_hours_notice_to_email' ] );
		add_filter( 'wpsc_cust_reply_email_data', [ $this, 'add_after_hours_notice_to_email' ] );
	}

	/**
	 * Render the administration page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get active theme class
		$theme_class = 'sb-theme-clean-tech'; // Default
		if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
			$theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
		}

		?>
		<!-- StackBoost Wrapper Start -->
		<!-- Theme: <?php echo esc_html( $theme_class ); ?> -->
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors(); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'stackboost_settings' );
				echo '<input type="hidden" name="stackboost_settings[page_slug]" value="stackboost-after-hours">';
				?>

				<div class="stackboost-dashboard-grid">

					<!-- Card 1: Configuration -->
					<div class="stackboost-card">
						<h2><?php esc_html_e( 'Configuration', 'stackboost-for-supportcandy' ); ?></h2>
						<table class="form-table">
							<tr>
								<th><?php esc_html_e( 'Enable Feature', 'stackboost-for-supportcandy' ); ?></th>
								<td><?php $this->render_checkbox_field( [ 'id' => 'enable_after_hours_notice', 'desc' => 'Displays a notice on the ticket form when submitted outside of business hours.' ] ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Add Notice to Emails', 'stackboost-for-supportcandy' ); ?></th>
								<td><?php $this->render_checkbox_field( [ 'id' => 'after_hours_in_email', 'desc' => 'Prepend the after-hours message to email notifications if the main feature is enabled.' ] ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Use SupportCandy Working Hours & Exceptions', 'stackboost-for-supportcandy' ); ?></th>
								<td><?php $this->render_checkbox_field( [ 'id' => 'use_sc_working_hours', 'desc' => 'Inherit scheduling from SupportCandy\'s "Working Hours" settings. This will override manual schedule settings.' ] ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Use SupportCandy Holiday Schedule', 'stackboost-for-supportcandy' ); ?></th>
								<td><?php $this->render_checkbox_field( [ 'id' => 'use_sc_holidays', 'desc' => 'Inherit holidays from SupportCandy\'s "Holiday" settings. This will override manual holiday settings.' ] ); ?></td>
							</tr>
						</table>
					</div>

					<!-- Card 2: Schedule -->
					<div class="stackboost-card" id="stackboost-after-hours-schedule-card">
						<h2><?php esc_html_e( 'Schedule', 'stackboost-for-supportcandy' ); ?></h2>
						<table class="form-table">
							<tr>
								<th><?php esc_html_e( 'After Hours Start (24h)', 'stackboost-for-supportcandy' ); ?></th>
								<td><?php $this->render_number_field( [ 'id' => 'after_hours_start', 'default' => '17', 'desc' => 'The hour when after-hours starts (e.g., 17 for 5 PM).' ] ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Before Hours End (24h)', 'stackboost-for-supportcandy' ); ?></th>
								<td><?php $this->render_number_field( [ 'id' => 'before_hours_end', 'default' => '8', 'desc' => 'The hour when business hours resume (e.g., 8 for 8 AM).' ] ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Include All Weekends', 'stackboost-for-supportcandy' ); ?></th>
								<td><?php $this->render_checkbox_field( [ 'id' => 'include_all_weekends', 'desc' => 'Enable this to show the notice all day on Saturdays and Sundays.' ] ); ?></td>
							</tr>
						</table>
					</div>

					<!-- Card 3: Holidays -->
					<div class="stackboost-card" id="stackboost-after-hours-holidays-card">
						<h2><?php esc_html_e( 'Holidays', 'stackboost-for-supportcandy' ); ?></h2>
						<table class="form-table">
							<tr>
								<th><?php esc_html_e( 'Holidays', 'stackboost-for-supportcandy' ); ?></th>
								<td><?php $this->render_textarea_field( [ 'id' => 'holidays', 'class' => 'regular-text', 'desc' => 'List holidays, one per line, in MM-DD-YYYY format (e.g., 12-25-2024). The notice will show all day on these dates.' ] ); ?></td>
							</tr>
						</table>
					</div>

					<!-- Card 4: Message -->
					<div class="stackboost-card">
						<h2><?php esc_html_e( 'Notice Message', 'stackboost-for-supportcandy' ); ?></h2>
						<table class="form-table">
							<tr>
								<th><?php esc_html_e( 'After Hours Message', 'stackboost-for-supportcandy' ); ?></th>
								<td>
									<?php
									$default_message = '<strong>StackBoost Helpdesk -- After Hours</strong><br><br>You have submitted an IT ticket outside of normal business hours, and it will be handled in the order it was received. If this is an emergency, or has caused a complete stoppage of work, please call the IT On-Call number at: <u>(719) 266-2837</u> <br><br> (Available <b>5pm</b> to <b>11pm(EST) M-F, 8am to 11pm</b> weekends and Holidays)';
									$this->render_wp_editor_field( [ 'id' => 'after_hours_message', 'default' => $default_message, 'desc' => 'The message to display to users. Basic HTML is allowed.' ] );
									?>
								</td>
							</tr>
						</table>
					</div>

				</div>

				<?php submit_button( __( 'Save Settings', 'stackboost-for-supportcandy' ) ); ?>
			</form>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    function toggleVisibility() {
                        var useSCWorkingHours = $('#use_sc_working_hours').is(':checked');
                        var useSCHolidays = $('#use_sc_holidays').is(':checked');

                        if (useSCWorkingHours) {
                            $('#stackboost-after-hours-schedule-card').hide();
                        } else {
                            $('#stackboost-after-hours-schedule-card').show();
                        }

                        if (useSCHolidays) {
                            $('#stackboost-after-hours-holidays-card').hide();
                        } else {
                            $('#stackboost-after-hours-holidays-card').show();
                        }
                    }

                    // Initial check
                    toggleVisibility();

                    // Change listeners
                    $('#use_sc_working_hours, #use_sc_holidays').change(function() {
                        toggleVisibility();
                    });
                });
            </script>
		</div>
		<?php
	}

    /**
     * Determines if the current time is considered "After Hours".
     * Centralized logic to prevent duplication.
     *
     * @return bool
     */
    private function is_currently_after_hours(): bool {
        $options = get_option( 'stackboost_settings', [] );

        $use_sc_working_hours = ! empty( $options['use_sc_working_hours'] );
        $use_sc_holidays      = ! empty( $options['use_sc_holidays'] );

        $is_after_hours = false;

        // Logic branching based on toggle configurations
        if ( $use_sc_working_hours ) {
            // SC is Definitive source for schedule.
            // Check SC Status (Exceptions + Standard Hours)
            // If use_sc_holidays is true, SC handles holidays too.
            // If use_sc_holidays is false, we must manually check manual holiday list.

            $sc_status = $this->get_sc_status( time(), $use_sc_holidays );

            if ( $sc_status === 'closed' ) {
                $is_after_hours = true;
            } else {
                // SC says Open. But wait! If use_sc_holidays is FALSE,
                // we must check if today is a MANUAL holiday (which SC ignored).
                if ( ! $use_sc_holidays ) {
                    $manual_holidays = $this->core->parse_holidays( $options['holidays'] ?? '' );
                    $timezone_string = wp_timezone_string();

                    $timezone = new DateTimeZone( $timezone_string );
                    $now      = new DateTime( 'now', $timezone );
                    if ( in_array( $now->format( 'Y-m-d' ), $manual_holidays, true ) ) {
                        $is_after_hours = true;
                    }
                }
            }

        } else {
            // Manual Schedule is Definitive source for schedule.
            // Construct settings for Core logic.

            $settings = [
                'start_hour'       => $options['after_hours_start'] ?? 17,
                'end_hour'         => $options['before_hours_end'] ?? 8,
                'include_weekends' => ! empty( $options['include_all_weekends'] ),
                'holidays'         => [], // Logic handled below
            ];

            // Determine holidays source
            if ( $use_sc_holidays ) {
                // Fetch SC holidays and pass to settings
                 $settings['holidays'] = $this->get_sc_holidays();
            } else {
                // Use manual list
                $settings['holidays'] = $this->core->parse_holidays( $options['holidays'] ?? '' );
            }

            // Use Core Logic
             if ( $this->core->is_after_hours( $settings, null, wp_timezone_string() ) ) {
                 $is_after_hours = true;
             }
        }

        return $is_after_hours;
    }

	/**
	 * Display the notice on the frontend if it's after hours.
	 */
	public function display_notice() {
		$options = get_option( 'stackboost_settings', [] );
		if ( empty( $options['enable_after_hours_notice'] ) ) {
			return;
		}

		if ( $this->is_currently_after_hours() ) {
			stackboost_log( 'AfterHoursNotice: Currently after hours. Displaying notice on form.', 'after_hours' );
			$message = $options['after_hours_message'] ?? '';
			if ( ! empty( $message ) ) {

				// Get active theme class for wrapper
				$theme_class = 'sb-theme-clean-tech'; // Default
				if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
					$theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
				}

				// Wrap in theme container if we are in admin area
				if ( is_admin() ) {
					echo '<div class="stackboost-dashboard ' . esc_attr( $theme_class ) . '" style="background:none; padding:0; margin:0; border:none; box-shadow:none;">';
				}

				// The message is saved via wp_kses_post, so it's safe to display.
				echo '<div class="stackboost-after-hours-notice" style="margin-left: 15px; margin-bottom: 15px;">' . wpautop( $message ) . '</div>';

				if ( is_admin() ) {
					echo '</div>';
				}
			}
		}
	}

	/**
	 * Add the after-hours notice to the email body if conditions are met.
	 *
	 * @param array $email_data The email data array.
	 * @return array The modified email data array.
	 */
	public function add_after_hours_notice_to_email( array $email_data ): array {
		$options = get_option( 'stackboost_settings', [] );

		// Exit early if the main feature or the email feature is disabled.
		if ( empty( $options['enable_after_hours_notice'] ) || empty( $options['after_hours_in_email'] ) ) {
			return $email_data;
		}

		if ( $this->is_currently_after_hours() ) {
			stackboost_log( 'AfterHoursNotice: Currently after hours. Prepending notice to email.', 'after_hours' );
			$message = $options['after_hours_message'] ?? '';
			if ( ! empty( $message ) ) {
				$notice      = '<div class="stackboost-after-hours-notice" style="margin-bottom: 20px; padding: 15px; border-left: 5px solid #ffba00; background-color: #fff8e5;">' . wpautop( $message ) . '</div>';
				$email_data['body'] = $notice . $email_data['body'];
			}
		}

		return $email_data;
	}

    /**
     * Get list of holidays from SupportCandy.
     *
     * @return array Array of holiday strings in Y-m-d format.
     */
    private function get_sc_holidays(): array {
        if ( ! class_exists( '\WPSC_Holiday' ) ) {
            return [];
        }

        // We need to fetch all holidays. WPSC_Holiday::get_holidays() dies, so we use find() directly.
        // Or replicate the logic from get_holidays() without the die().

        $holidays_list = [];

        // 1. Non-Recurring
        $non_recurring = \WPSC_Holiday::find( [
            'meta_query' => [
                'relation' => 'AND',
                [ 'slug' => 'agent', 'compare' => '=', 'val' => 0 ],
                [ 'slug' => 'is_recurring', 'compare' => '=', 'val' => 0 ],
            ]
        ] );

        if ( isset( $non_recurring['results'] ) ) {
            foreach ( $non_recurring['results'] as $holiday ) {
                if ( $holiday->holiday instanceof DateTime ) {
                    $holidays_list[] = $holiday->holiday->format( 'Y-m-d' );
                }
            }
        }

        // 2. Recurring - This is tricky because Core logic expects explicit Y-m-d dates.
        // We need to generate this year's instance of the recurring holiday.
        $recurring = \WPSC_Holiday::find( [
            'meta_query' => [
                'relation' => 'AND',
                [ 'slug' => 'agent', 'compare' => '=', 'val' => 0 ],
                [ 'slug' => 'is_recurring', 'compare' => '=', 'val' => 1 ],
            ]
        ] );

        if ( isset( $recurring['results'] ) ) {
            // Use wp_date to respect site timezone, not server timezone.
            $current_year = wp_date('Y');
            foreach ( $recurring['results'] as $holiday ) {
                if ( $holiday->holiday instanceof DateTime ) {
                     // Recurring holidays are stored with a dummy year (often creation year).
                     // We just need month and day.
                     $md = $holiday->holiday->format( 'm-d' );
                     $holidays_list[] = $current_year . '-' . $md;
                }
            }
        }

        return $holidays_list;
    }

    /**
     * Determine Open/Closed status from SupportCandy.
     *
     * @param int $timestamp Current timestamp.
     * @param bool $check_holidays Whether to check SC holidays or not.
     * @return string 'open' or 'closed'.
     */
    private function get_sc_status( int $timestamp, bool $check_holidays ): string {
        if ( ! class_exists( '\WPSC_Working_Hour' ) || ! class_exists( '\WPSC_Wh_Exception' ) ) {
            return 'open'; // Default to open if SC is missing to avoid blocking users.
        }

        $timezone = new DateTimeZone( wp_timezone_string() );
        $date_obj = new DateTime( 'now', $timezone );
        $date_obj->setTimestamp( $timestamp );

        // 1. Check Exceptions (Definitive Override)
        // WPSC_Wh_Exception::get_exception_by_date( $date, $agent_id = 0 )
        $exception = \WPSC_Wh_Exception::get_exception_by_date( $date_obj, 0 );
        if ( $exception ) {
            // Exception exists! It defines the working hours for today.
            // Check if current time is within exception start/end.
            $now_time = $date_obj->format( 'H:i:s' );
            if ( $now_time >= $exception->start_time && $now_time < $exception->end_time ) {
                return 'open';
            } else {
                return 'closed';
            }
        }

        // 2. Check Holidays (if enabled)
        if ( $check_holidays ) {
            // WPSC_Holiday::get_holiday_by_date( $date, $agent_id = 0 )
            // Note: This checks both recurring and non-recurring internally.
            if ( class_exists( '\WPSC_Holiday' ) ) {
                 $holiday = \WPSC_Holiday::get_holiday_by_date( $date_obj, 0 );
                 if ( $holiday ) {
                     return 'closed';
                 }
            }
        }

        // 3. Check Standard Working Hours
        // We CANNOT use WPSC_Working_Hour::get_working_hrs_by_date() because it forces a holiday check.
        // We must query the raw schedule manually.

        // WPSC_Working_Hour::get( $agent_id = 0 ) returns array indexed by day number (1-7).
        $working_hrs = \WPSC_Working_Hour::get( 0 );
        $day_num = $date_obj->format( 'N' ); // 1 (Mon) - 7 (Sun)

        if ( isset( $working_hrs[ $day_num ] ) ) {
            $wh = $working_hrs[ $day_num ];
            if ( $wh->start_time === 'off' ) {
                return 'closed';
            }

            $now_time = $date_obj->format( 'H:i:s' );
            if ( $now_time >= $wh->start_time && $now_time < $wh->end_time ) {
                return 'open';
            } else {
                return 'closed';
            }
        }

        return 'closed'; // Default closed if no schedule found? Or Open? SC likely defaults to closed if not set.
    }

	/**
	 * Register settings fields for this module.
	 */
	public function register_settings() {
		$page_slug = 'stackboost-after-hours';

		add_settings_section(
			'stackboost_after_hours_section',
			__( 'After Hours Notice', 'stackboost-for-supportcandy' ),
			[ $this, 'render_section_description' ],
			$page_slug
		);

		add_settings_field( 'stackboost_enable_after_hours_notice', __( 'Enable Feature', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'enable_after_hours_notice', 'desc' => 'Displays a notice on the ticket form when submitted outside of business hours.' ] );
		add_settings_field( 'stackboost_after_hours_in_email', __( 'Add Notice to Emails', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'after_hours_in_email', 'desc' => 'Prepend the after-hours message to email notifications if the main feature is enabled.' ] );
        add_settings_field( 'stackboost_use_sc_working_hours', __( 'Use SupportCandy Working Hours', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'use_sc_working_hours', 'desc' => 'Inherit scheduling from SupportCandy\'s "Working Hours" settings. This will override manual schedule settings.' ] );
        add_settings_field( 'stackboost_use_sc_holidays', __( 'Use SupportCandy Holiday Schedule', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'use_sc_holidays', 'desc' => 'Inherit holidays from SupportCandy\'s "Holiday" settings. This will override manual holiday settings.' ] );

		add_settings_field( 'stackboost_after_hours_start', __( 'After Hours Start (24h)', 'stackboost-for-supportcandy' ), [ $this, 'render_number_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'after_hours_start', 'default' => '17', 'desc' => 'The hour when after-hours starts (e.g., 17 for 5 PM).' ] );
		add_settings_field( 'stackboost_before_hours_end', __( 'Before Hours End (24h)', 'stackboost-for-supportcandy' ), [ $this, 'render_number_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'before_hours_end', 'default' => '8', 'desc' => 'The hour when business hours resume (e.g., 8 for 8 AM).' ] );
		add_settings_field( 'stackboost_include_all_weekends', __( 'Include All Weekends', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'include_all_weekends', 'desc' => 'Enable this to show the notice all day on Saturdays and Sundays.' ] );
		add_settings_field( 'stackboost_holidays', __( 'Holidays', 'stackboost-for-supportcandy' ), [ $this, 'render_textarea_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'holidays', 'class' => 'regular-text', 'desc' => 'List holidays, one per line, in MM-DD-YYYY format (e.g., 12-25-2024). The notice will show all day on these dates.' ] );

		$default_message = '<strong>StackBoost Helpdesk -- After Hours</strong><br><br>You have submitted an IT ticket outside of normal business hours, and it will be handled in the order it was received. If this is an emergency, or has caused a complete stoppage of work, please call the IT On-Call number at: <u>(719) 266-2837</u> <br><br> (Available <b>5pm</b> to <b>11pm(EST) M-F, 8am to 11pm</b> weekends and Holidays)';
		add_settings_field( 'stackboost_after_hours_message', __( 'After Hours Message', 'stackboost-for-supportcandy' ), [ $this, 'render_wp_editor_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'after_hours_message', 'default' => $default_message, 'desc' => 'The message to display to users. Basic HTML is allowed.' ] );
	}

	/**
	 * Render the description for the section.
	 */
	public function render_section_description() {
		echo '<p>' . esc_html__( 'This feature shows a customizable message at the top of the "Create Ticket" form if a user is accessing it outside of your defined business hours.', 'stackboost-for-supportcandy' ) . '</p>';
	}

	// Note: The rendering functions (render_checkbox_field, etc.) will be moved to a shared Admin/Settings class.
	// For now, we'll assume they exist on the parent Module class for compilation.

	/**
	 * Render a WP Editor (WYSIWYG) field.
	 * Overrides the parent method to add specific toolbar options.
	 *
	 * @param array $args The arguments for the field.
	 */
	public function render_wp_editor_field( array $args ) {
		$options = get_option( 'stackboost_settings', [] );
		$content = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : ( $args['default'] ?? '' );

		wp_editor(
			$content,
			'stackboost_settings_' . esc_attr( $args['id'] ),
			[
				'textarea_name' => 'stackboost_settings[' . esc_attr( $args['id'] ) . ']',
				'media_buttons' => false,
				'textarea_rows' => 10,
				'teeny'         => true,
				'tinymce'       => [
					'toolbar1' => 'formatselect,styleselect,bold,italic,underline,forecolor,blockquote,strikethrough,bullist,numlist,outdent,indent,alignleft,aligncenter,alignright,undo,redo,link,unlink,hr,removeformat,fullscreen',
					'plugins'  => 'lists,wplink,wordpress,paste,fullscreen,textcolor,hr',
				],
			]
		);
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}
}
