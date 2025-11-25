<?php

namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin;

class Sequence {

	/**
	 * Option name for storing the sequence.
	 */
	const OPTION_SEQUENCE = 'stackboost_onboarding_sequence';

	/**
	 * Initialize the Sequence page.
	 */
	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'handle_save' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue scripts for sortable UI.
	 */
	public static function enqueue_scripts( $hook ) {
        stackboost_log( "Sequence Enqueue Scripts called for hook: " . $hook, 'onboarding' );
		if ( 'stackboost_page_stackboost-onboarding-dashboard' !== $hook ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'steps';
		if ( 'steps' !== $tab ) {
			return;
		}

        stackboost_log( "Sequence Enqueue Scripts: Correct hook found, enqueueing assets.", 'onboarding' );
        wp_enqueue_script( 'jquery' ); // Explicit dependency
		wp_enqueue_script( 'jquery-ui-sortable' );

		$script = '
		jQuery(document).ready(function($) {
			console.log("StackBoost: Initializing Sortable for Onboarding Sequence");
			if ($("#stkb-sequence-list").length === 0 && $("#stkb-available-list").length === 0) {
				console.log("StackBoost: Sortable lists not found.");
				return;
			}

			try {
				$("#stkb-sequence-list, #stkb-available-list").sortable({
					connectWith: ".connectedSortable",
					placeholder: "ui-state-highlight",
					cursor: "move",
					helper: "clone",
					forcePlaceholderSize: true,
					tolerance: "pointer",
					receive: function(event, ui) {
						if (this.id === "stkb-sequence-list") {
							$(ui.item).find("input[type=hidden]").remove();
							$(ui.item).append(\'<input type="hidden" name="onboarding_sequence[]" value="\' + $(ui.item).data("post-id") + \'">\');
						} else {
							$(ui.item).find("input[type=hidden]").remove();
						}
					}
				}).disableSelection();
				console.log("StackBoost: Sortable initialized successfully.");
			} catch (e) {
				console.error("StackBoost: Sortable initialization failed:", e);
			}
		});
		';

		// Attach directly to jquery-ui-sortable to ensure it runs after the library loads.
		// The dummy script approach fails because WP doesn't output scripts with empty sources.
		wp_add_inline_script( 'jquery-ui-sortable', $script );
	}

	/**
	 * Handle form submission.
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['stkb_sequence_nonce'] ) && wp_verify_nonce( $_POST['stkb_sequence_nonce'], 'stkb_save_sequence' ) ) {
			if ( isset( $_POST['onboarding_sequence'] ) && is_array( $_POST['onboarding_sequence'] ) ) {
				stackboost_log( 'Saving new onboarding sequence...', 'onboarding' );
				$new_sequence = array_map( 'absint', $_POST['onboarding_sequence'] );
				update_option( self::OPTION_SEQUENCE, $new_sequence );
				stackboost_log( 'Onboarding sequence updated: ' . implode( ',', $new_sequence ), 'onboarding' );
				add_action( 'admin_notices', [ __CLASS__, 'save_success_notice' ] );
			}
		}
	}

	/**
	 * Success notice.
	 */
	public static function save_success_notice() {
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php _e( 'Onboarding sequence saved!', 'stackboost-for-supportcandy' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the page.
	 */
	public static function render_page() {
		$saved_sequence_ids = get_option( self::OPTION_SEQUENCE, [] );

		$all_posts = get_posts( [
			'post_type'      => 'stkb_onboarding_step',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$sequence_posts = [];
		$available_posts = [];

		$saved_sequence_map = array_flip( $saved_sequence_ids );

		foreach ( $all_posts as $post ) {
			if ( isset( $saved_sequence_map[ $post->ID ] ) ) {
				$sequence_posts[ $saved_sequence_map[ $post->ID ] ] = $post;
			} else {
				$available_posts[] = $post;
			}
		}

		ksort( $sequence_posts );
		?>
		<div>
			<h2><?php esc_html_e( 'Onboarding Steps', 'stackboost-for-supportcandy' ); ?>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=stkb_onboarding_step' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Step', 'stackboost-for-supportcandy' ); ?></a>
			</h2>
			<p><?php esc_html_e( 'Drag and drop the steps below to set the onboarding sequence.', 'stackboost-for-supportcandy' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'stkb_save_sequence', 'stkb_sequence_nonce' ); ?>

				<div class="stkb-sequence-container">
					<div class="stkb-list-wrapper">
						<h2><?php esc_html_e( 'Steps in Sequence', 'stackboost-for-supportcandy' ); ?></h2>
						<ul id="stkb-sequence-list" class="connectedSortable">
							<?php foreach ( $sequence_posts as $post ) : ?>
								<li class="ui-state-default" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
									<span class="stkb-step-title"><?php echo esc_html( $post->post_title ); ?></span>
									<div class="stkb-step-actions">
										<a href="<?php echo get_edit_post_link( $post->ID ); ?>" class="dashicons dashicons-edit" title="<?php esc_attr_e( 'Edit', 'stackboost-for-supportcandy' ); ?>"></a>
										<a href="<?php echo get_delete_post_link( $post->ID ); ?>" class="dashicons dashicons-trash" title="<?php esc_attr_e( 'Move to Trash', 'stackboost-for-supportcandy' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this step?', 'stackboost-for-supportcandy' ); ?>');"></a>
									</div>
									<input type="hidden" name="onboarding_sequence[]" value="<?php echo esc_attr( $post->ID ); ?>">
								</li>
							<?php endforeach; ?>
						</ul>
					</div>

					<div class="stkb-list-wrapper">
						<h2><?php esc_html_e( 'Available Steps', 'stackboost-for-supportcandy' ); ?></h2>
						<ul id="stkb-available-list" class="connectedSortable">
							<?php foreach ( $available_posts as $post ) : ?>
								<li class="ui-state-default" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
									<span class="stkb-step-title"><?php echo esc_html( $post->post_title ); ?></span>
									<div class="stkb-step-actions">
										<a href="<?php echo get_edit_post_link( $post->ID ); ?>" class="dashicons dashicons-edit" title="<?php esc_attr_e( 'Edit', 'stackboost-for-supportcandy' ); ?>"></a>
										<a href="<?php echo get_delete_post_link( $post->ID ); ?>" class="dashicons dashicons-trash" title="<?php esc_attr_e( 'Move to Trash', 'stackboost-for-supportcandy' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this step?', 'stackboost-for-supportcandy' ); ?>');"></a>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Sequence', 'stackboost-for-supportcandy' ); ?>">
				</p>
			</form>
		</div>

		<style>
			.stkb-sequence-container { display: flex; gap: 20px; }
			.stkb-list-wrapper { flex: 1; border: 1px solid #c3c4c7; border-radius: 4px; padding: 10px; background: #fff; }
			#stkb-sequence-list, #stkb-available-list { min-height: 100px; margin: 0; padding: 0; list-style: none; }
			#stkb-sequence-list li, #stkb-available-list li {
				margin: 5px;
				padding: 10px;
				cursor: move;
				background-color: #f6f7f7;
				border: 1px solid #dcdcde;
				border-radius: 3px;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}
			#stkb-sequence-list li:hover, #stkb-available-list li:hover { border-color: #2271b1; }
			.ui-state-highlight { height: 40px; line-height: 40px; border: 1px dashed #ccc; background: #f0f0f0; margin: 5px; }
			.stkb-step-actions a { text-decoration: none; color: #50575e; margin-left: 5px; }
			.stkb-step-actions a:hover { color: #2271b1; }
			.stkb-step-actions a.dashicons-trash:hover { color: #d63638; }
		</style>
		<?php
	}
}
