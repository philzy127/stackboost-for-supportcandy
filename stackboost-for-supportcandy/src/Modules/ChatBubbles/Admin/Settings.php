<?php

namespace StackBoost\ForSupportCandy\Modules\ChatBubbles\Admin;

/**
 * Settings Page for Chat Bubbles.
 */
class Settings {

	/**
	 * Render the administration page.
	 */
	public static function render_page() {
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
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php esc_html_e( 'Chat Bubbles', 'stackboost-for-supportcandy' ); ?></h1>
			<?php settings_errors(); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'stackboost_settings' );
				// stackboost_settings[page_slug]
				?>
				<input type="hidden" name="stackboost_settings[page_slug]" value="stackboost-chat-bubbles">

				<!-- Two Column Layout -->
				<div class="stackboost-chat-bubbles-layout" style="margin-top: 20px;">

					<!-- Column 1: Configuration -->
					<div class="stackboost-chat-config-column stackboost-card" style="padding: 0;">

						<!-- JS Tabs Header -->
						<div class="sb-chat-tabs">
							<div class="sb-chat-tab active" data-target="agent"><?php esc_html_e( 'Agent Reply', 'stackboost-for-supportcandy' ); ?></div>
							<div class="sb-chat-tab" data-target="customer"><?php esc_html_e( 'Customer Reply', 'stackboost-for-supportcandy' ); ?></div>
							<div class="sb-chat-tab" data-target="note"><?php esc_html_e( 'Internal Note', 'stackboost-for-supportcandy' ); ?></div>
						</div>

						<!-- Config Content Area -->
						<div class="sb-chat-config-content" style="padding: 0 20px 20px 20px;">
							<?php foreach ( ['agent', 'customer', 'note'] as $type ) : ?>
								<div id="sb-chat-config-<?php echo esc_attr( $type ); ?>" class="sb-chat-config-section" style="<?php echo $type !== 'agent' ? 'display:none;' : ''; ?>">
									<table class="form-table">
										<?php self::render_fields( $type ); ?>
									</table>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Column 2: Unified Preview -->
					<div class="stackboost-chat-preview-column">
						<div class="stackboost-card">
							<h2><?php esc_html_e( 'Live Preview', 'stackboost-for-supportcandy' ); ?></h2>
							<p class="description"><?php esc_html_e( 'This visualizes a sample conversation thread.', 'stackboost-for-supportcandy' ); ?></p>

							<div class="stackboost-chat-preview-container">

								<!-- 1. Customer Bubble -->
								<div id="preview-bubble-customer" class="sb-preview-bubble customer">
									<div class="sb-preview-header">
										<strong><?php esc_html_e( 'John Doe (Customer)', 'stackboost-for-supportcandy' ); ?></strong>
										<span>10:30 AM</span>
									</div>
									<div class="preview-text">
										<?php esc_html_e( 'Hi, I need help with my account. I cannot log in.', 'stackboost-for-supportcandy' ); ?>
									</div>
								</div>

								<!-- 2. Note Bubble -->
								<div id="preview-bubble-note" class="sb-preview-bubble note">
									<div class="sb-preview-header">
										<strong><?php esc_html_e( 'Agent (Private Note)', 'stackboost-for-supportcandy' ); ?></strong>
										<span>10:32 AM</span>
									</div>
									<div class="preview-text">
										<?php esc_html_e( 'Checked logs. Failed login attempts from unknown IP.', 'stackboost-for-supportcandy' ); ?>
									</div>
								</div>

								<!-- 3. Agent Bubble -->
								<div id="preview-bubble-agent" class="sb-preview-bubble agent">
									<div class="sb-preview-header">
										<strong><?php esc_html_e( 'Support Agent', 'stackboost-for-supportcandy' ); ?></strong>
										<span>10:35 AM</span>
									</div>
									<div class="preview-text">
										<?php esc_html_e( 'Hello John, I can reset that for you right now.', 'stackboost-for-supportcandy' ); ?>
									</div>
								</div>

							</div>
						</div>
					</div>

				</div>

				<?php submit_button( __( 'Save Settings', 'stackboost-for-supportcandy' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render fields for a specific tab.
	 */
	public static function render_fields( $type ) {
		$options = get_option( 'stackboost_settings', [] );
		$prefix  = "chat_bubbles_{$type}_"; // e.g. chat_bubbles_agent_

		// 1. Theme Preset
		$theme_key = "{$prefix}theme";
		$theme_val = $options[ $theme_key ] ?? 'custom';

		?>
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Theme Preset', 'stackboost-for-supportcandy' ); ?></th>
			<td>
				<select name="stackboost_settings[<?php echo esc_attr( $theme_key ); ?>]" class="sb-chat-theme-selector regular-text" data-type="<?php echo esc_attr( $type ); ?>">
					<option value="custom" <?php selected( $theme_val, 'custom' ); ?>><?php esc_html_e( 'Custom', 'stackboost-for-supportcandy' ); ?></option>
					<option value="stackboost" <?php selected( $theme_val, 'stackboost' ); ?>><?php esc_html_e( 'StackBoost Theme', 'stackboost-for-supportcandy' ); ?></option>
					<option value="supportcandy" <?php selected( $theme_val, 'supportcandy' ); ?>><?php esc_html_e( 'SupportCandy Theme', 'stackboost-for-supportcandy' ); ?></option>
					<option value="ios" <?php selected( $theme_val, 'ios' ); ?>><?php esc_html_e( 'iMessage (iOS)', 'stackboost-for-supportcandy' ); ?></option>
					<option value="android" <?php selected( $theme_val, 'android' ); ?>><?php esc_html_e( 'WhatsApp (Android)', 'stackboost-for-supportcandy' ); ?></option>
					<option value="modern" <?php selected( $theme_val, 'modern' ); ?>><?php esc_html_e( 'Minimal / Modern', 'stackboost-for-supportcandy' ); ?></option>
				</select>
			</td>
		</tr>

		<!-- Custom Fields Wrapper (Toggled by JS) -->
		<tbody class="sb-chat-custom-fields" id="sb_chat_custom_fields_<?php echo esc_attr( $type ); ?>" style="<?php echo $theme_val !== 'custom' ? 'display:none;' : ''; ?>">

			<!-- Background Color -->
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Background', 'stackboost-for-supportcandy' ); ?></th>
				<td>
					<input type="text" name="stackboost_settings[<?php echo esc_attr( $prefix . 'bg_color' ); ?>]" value="<?php echo esc_attr( $options[ $prefix . 'bg_color' ] ?? '#f1f1f1' ); ?>" class="my-color-field" data-default-color="#f1f1f1" />
				</td>
			</tr>

			<!-- Text Color -->
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Text Color', 'stackboost-for-supportcandy' ); ?></th>
				<td>
					<input type="text" name="stackboost_settings[<?php echo esc_attr( $prefix . 'text_color' ); ?>]" value="<?php echo esc_attr( $options[ $prefix . 'text_color' ] ?? '#333333' ); ?>" class="my-color-field" data-default-color="#333333" />
				</td>
			</tr>

			<!-- Font Family -->
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Font Family', 'stackboost-for-supportcandy' ); ?></th>
				<td>
					<select name="stackboost_settings[<?php echo esc_attr( $prefix . 'font_family' ); ?>]" class="regular-text">
						<?php
						$fonts = [
							'' => 'Default (Inherit)',
							'-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif' => 'System UI (Modern)',
							'Arial, Helvetica, sans-serif' => 'Arial / Helvetica',
							'Verdana, Geneva, sans-serif' => 'Verdana',
							'"Times New Roman", Times, serif' => 'Times New Roman',
							'"Courier New", Courier, monospace' => 'Courier New',
						];
						$current_font = $options[ $prefix . 'font_family' ] ?? '';
						foreach ( $fonts as $val => $label ) {
							echo '<option value="' . esc_attr( $val ) . '" ' . selected( $current_font, $val, false ) . '>' . esc_html( $label ) . '</option>';
						}
						?>
					</select>
				</td>
			</tr>

			<!-- Font Size -->
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Font Size', 'stackboost-for-supportcandy' ); ?></th>
				<td>
					<input type="number" name="stackboost_settings[<?php echo esc_attr( $prefix . 'font_size' ); ?>]" min="10" max="30" value="<?php echo esc_attr( $options[ $prefix . 'font_size' ] ?? '' ); ?>" class="small-text" placeholder="Default"> px
				</td>
			</tr>

			<!-- Alignment -->
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Alignment', 'stackboost-for-supportcandy' ); ?></th>
				<td>
					<select name="stackboost_settings[<?php echo esc_attr( $prefix . 'alignment' ); ?>]" class="regular-text">
						<option value="left" <?php selected( $options[ $prefix . 'alignment' ] ?? 'left', 'left' ); ?>><?php esc_html_e( 'Left', 'stackboost-for-supportcandy' ); ?></option>
						<option value="right" <?php selected( $options[ $prefix . 'alignment' ] ?? 'left', 'right' ); ?>><?php esc_html_e( 'Right', 'stackboost-for-supportcandy' ); ?></option>
					</select>
				</td>
			</tr>

			<!-- Width (Slider) -->
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Max Width', 'stackboost-for-supportcandy' ); ?></th>
				<td>
					<input type="range" name="stackboost_settings[<?php echo esc_attr( $prefix . 'width' ); ?>]" min="20" max="100" step="5" value="<?php echo esc_attr( $options[ $prefix . 'width' ] ?? '85' ); ?>" oninput="this.nextElementSibling.value = this.value">
					<output><?php echo esc_html( $options[ $prefix . 'width' ] ?? '85' ); ?></output>%
				</td>
			</tr>

			<!-- Corner Radius -->
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Radius', 'stackboost-for-supportcandy' ); ?></th>
				<td>
					<input type="number" name="stackboost_settings[<?php echo esc_attr( $prefix . 'radius' ); ?>]" min="0" max="50" value="<?php echo esc_attr( $options[ $prefix . 'radius' ] ?? '15' ); ?>" class="small-text"> px
				</td>
			</tr>

			<!-- Tail Style -->
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Tail Style', 'stackboost-for-supportcandy' ); ?></th>
				<td>
					<select name="stackboost_settings[<?php echo esc_attr( $prefix . 'tail' ); ?>]" class="regular-text">
						<option value="none" <?php selected( $options[ $prefix . 'tail' ] ?? 'none', 'none' ); ?>><?php esc_html_e( 'None (Box)', 'stackboost-for-supportcandy' ); ?></option>
						<option value="round" <?php selected( $options[ $prefix . 'tail' ] ?? 'none', 'round' ); ?>><?php esc_html_e( 'Rounded Tail', 'stackboost-for-supportcandy' ); ?></option>
						<option value="sharp" <?php selected( $options[ $prefix . 'tail' ] ?? 'none', 'sharp' ); ?>><?php esc_html_e( 'Sharp Triangle', 'stackboost-for-supportcandy' ); ?></option>
					</select>
				</td>
			</tr>

		</tbody>
		<?php
	}

	/**
	 * Sanitize Settings (Helper for centralized sanitizer).
	 * This returns the keys expected for this page.
	 */
	public static function get_settings_keys() {
		$keys = [];
		foreach ( ['agent', 'customer', 'note'] as $type ) {
			$prefix = "chat_bubbles_{$type}_";
			$keys[] = "{$prefix}theme";
			$keys[] = "{$prefix}bg_color";
			$keys[] = "{$prefix}text_color";
			$keys[] = "{$prefix}font_family";
			$keys[] = "{$prefix}font_size"; // Added font size key
			$keys[] = "{$prefix}alignment";
			$keys[] = "{$prefix}width";
			$keys[] = "{$prefix}radius";
			$keys[] = "{$prefix}tail";
		}
		return $keys;
	}
}
