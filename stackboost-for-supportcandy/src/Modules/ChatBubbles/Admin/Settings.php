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
							<div class="sb-chat-tab active" data-target="general"><?php esc_html_e( 'Settings', 'stackboost-for-supportcandy' ); ?></div>
							<div class="sb-chat-tab type-tab" data-target="customer"><?php esc_html_e( 'Customer', 'stackboost-for-supportcandy' ); ?></div>
							<div class="sb-chat-tab type-tab" data-target="note"><?php esc_html_e( 'Private Note', 'stackboost-for-supportcandy' ); ?></div>
							<div class="sb-chat-tab type-tab" data-target="agent"><?php esc_html_e( 'Agent', 'stackboost-for-supportcandy' ); ?></div>
							<div class="sb-chat-tab type-tab" data-target="log"><?php esc_html_e( 'System Log', 'stackboost-for-supportcandy' ); ?></div>
						</div>

						<!-- Config Content Area -->
						<div class="sb-chat-config-content" style="padding: 0 20px 20px 20px;">

							<!-- General Settings Tab -->
							<div id="sb-chat-config-general" class="sb-chat-config-section">
								<table class="form-table">
									<?php self::render_general_fields(); ?>
								</table>
								<div style="margin-top: 20px; text-align: right;">
									<button type="button" class="button" id="sb_chat_reset_all"><?php esc_html_e( 'Reset All Settings', 'stackboost-for-supportcandy' ); ?></button>
								</div>
							</div>

							<!-- Type Specific Tabs -->
							<?php foreach ( ['customer', 'note', 'agent', 'log'] as $type ) : ?>
								<div id="sb-chat-config-<?php echo esc_attr( $type ); ?>" class="sb-chat-config-section" style="display:none;">
									<table class="form-table">
										<?php self::render_type_fields( $type ); ?>
									</table>
									<div style="margin-top: 20px; text-align: right;">
										<button type="button" class="button sb-chat-reset-type" data-type="<?php echo esc_attr( $type ); ?>"><?php esc_html_e( 'Reset This Section', 'stackboost-for-supportcandy' ); ?></button>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Column 2: Unified Preview -->
					<div class="stackboost-chat-preview-column">
						<div class="stackboost-card">
							<h2><?php esc_html_e( 'Live Preview', 'stackboost-for-supportcandy' ); ?></h2>
							<p class="description"><?php esc_html_e( 'This visualizes a sample conversation thread.', 'stackboost-for-supportcandy' ); ?></p>

							<div class="stackboost-chat-preview-container" style="background: #f0f0f1;">

								<!-- 1. Customer Bubble -->
								<div id="preview-bubble-customer" class="sb-preview-bubble customer">
									<div class="sb-preview-header">
										<img class="sb-preview-avatar" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y" alt="Avatar" style="display:none;" />
										<strong><?php esc_html_e( 'John Doe (Customer)', 'stackboost-for-supportcandy' ); ?></strong>
										<span class="sb-time">10:30 AM</span>
									</div>
									<div class="preview-text">
										<?php esc_html_e( 'Hi, I need help with my account. I cannot log in.', 'stackboost-for-supportcandy' ); ?>
									</div>
								</div>

								<!-- 2. Note Bubble -->
								<div id="preview-bubble-note" class="sb-preview-bubble note">
									<div class="sb-preview-header">
										<img class="sb-preview-avatar" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y" alt="Avatar" style="display:none;" />
										<strong><?php esc_html_e( 'Agent (Private Note)', 'stackboost-for-supportcandy' ); ?></strong>
										<span class="sb-time">10:32 AM</span>
									</div>
									<div class="preview-text">
										<?php esc_html_e( 'Checked logs. Failed login attempts from unknown IP.', 'stackboost-for-supportcandy' ); ?>
									</div>
								</div>

								<!-- 3. Log Bubble -->
								<div id="preview-bubble-log" class="sb-preview-bubble log">
									<div class="sb-preview-header">
										<img class="sb-preview-avatar" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y" alt="Avatar" style="display:none;" />
										<strong><?php esc_html_e( 'System Log', 'stackboost-for-supportcandy' ); ?></strong>
										<span class="sb-time">10:33 AM</span>
									</div>
									<div class="preview-text">
										<?php esc_html_e( 'Ticket Status changed from New to In Progress', 'stackboost-for-supportcandy' ); ?>
									</div>
								</div>

								<!-- 4. Agent Bubble -->
								<div id="preview-bubble-agent" class="sb-preview-bubble agent">
									<div class="sb-preview-header">
										<img class="sb-preview-avatar" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y" alt="Avatar" style="display:none;" />
										<strong><?php esc_html_e( 'Support Agent', 'stackboost-for-supportcandy' ); ?></strong>
										<span class="sb-time">10:35 AM</span>
									</div>
									<div class="preview-text">
										<?php esc_html_e( 'Hello John, I can reset that for you right now.', 'stackboost-for-supportcandy' ); ?>
										<br/>
										<img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxNTAgNTAiIHdpZHRoPSIxNTAiIGhlaWdodD0iNTAiPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9IiNjY2MiLz48dGV4dCB4PSI1MCUiIHk9IjUwJSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZm9udC1mYW1pbHk9InNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM1NTUiPkltYWdlPC90ZXh0Pjwvc3ZnPg==" alt="Placeholder" style="max-width:100%; height:auto; margin-top:5px;" class="sb-chat-preview-img" />
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
	 * Render General Settings Fields.
	 */
	public static function render_general_fields() {
		$options = get_option( 'stackboost_settings', [] );

		// Ticket Enable
		?>
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Enable on Ticket View', 'stackboost-for-supportcandy' ); ?></th>
			<td>
				<input type="checkbox" name="stackboost_settings[chat_bubbles_enable_ticket]" id="chat_bubbles_enable_ticket" value="1" <?php checked( $options['chat_bubbles_enable_ticket'] ?? 0, 1 ); ?> />
				<p class="description"><?php esc_html_e( 'Show chat bubbles on Ticket Views (Admin & Frontend).', 'stackboost-for-supportcandy' ); ?></p>
			</td>
		</tr>

		<?php
		// Show Avatars (GDPR Warning)
		?>
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Show User Avatars', 'stackboost-for-supportcandy' ); ?></th>
			<td>
				<input type="checkbox" name="stackboost_settings[chat_bubbles_show_avatars]" id="chat_bubbles_show_avatars" value="1" <?php checked( $options['chat_bubbles_show_avatars'] ?? 0, 1 ); ?> />
				<p class="description">
					<?php esc_html_e( 'Keep the default SupportCandy avatars visible next to bubbles.', 'stackboost-for-supportcandy' ); ?>
				</p>
			</td>
		</tr>

		<?php
		// Theme Preset
		$theme_val = $options['chat_bubbles_theme'] ?? 'default';
		?>
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Theme Preset', 'stackboost-for-supportcandy' ); ?></th>
			<td>
				<select name="stackboost_settings[chat_bubbles_theme]" id="sb_chat_global_theme_selector" class="regular-text">
					<option value="custom" <?php selected( $theme_val, 'custom' ); ?>><?php esc_html_e( 'Custom', 'stackboost-for-supportcandy' ); ?></option>
					<option value="default" <?php selected( $theme_val, 'default' ); ?>><?php esc_html_e( 'Default (Blue/Grey)', 'stackboost-for-supportcandy' ); ?></option>
					<option value="stackboost" <?php selected( $theme_val, 'stackboost' ); ?>><?php esc_html_e( 'StackBoost Theme', 'stackboost-for-supportcandy' ); ?> &#x21ba;</option>
					<option value="supportcandy" <?php selected( $theme_val, 'supportcandy' ); ?>><?php esc_html_e( 'SupportCandy Theme', 'stackboost-for-supportcandy' ); ?> &#x21ba;</option>
					<option value="classic" <?php selected( $theme_val, 'classic' ); ?>><?php esc_html_e( 'Classic', 'stackboost-for-supportcandy' ); ?></option>
					<option value="ios" <?php selected( $theme_val, 'ios' ); ?>><?php esc_html_e( 'Fruit', 'stackboost-for-supportcandy' ); ?></option>
					<option value="android" <?php selected( $theme_val, 'android' ); ?>><?php esc_html_e( 'Droid', 'stackboost-for-supportcandy' ); ?></option>
					<option value="modern" <?php selected( $theme_val, 'modern' ); ?>><?php esc_html_e( 'Minimal / Modern', 'stackboost-for-supportcandy' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Applies a base style to all bubbles. "Custom" unlocks detailed tabs.', 'stackboost-for-supportcandy' ); ?></p>
				<p class="description"><span style="font-size: 14px;">&#x21ba;</span> <?php esc_html_e( 'Indicates settings are synced from another module.', 'stackboost-for-supportcandy' ); ?></p>
			</td>
		</tr>

		<!-- Drop Shadow (Global) -->
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Drop Shadow', 'stackboost-for-supportcandy' ); ?></th>
			<td>
				<label style="margin-right: 15px;">
					<input type="checkbox" name="stackboost_settings[chat_bubbles_shadow_enable]" id="chat_bubbles_shadow_enable" value="1" <?php checked( $options['chat_bubbles_shadow_enable'] ?? 0, 1 ); ?> /> <?php esc_html_e( 'Enable', 'stackboost-for-supportcandy' ); ?>
				</label>
				<label style="margin-right: 5px;"><?php esc_html_e( 'Color:', 'stackboost-for-supportcandy' ); ?></label>
				<input type="text" name="stackboost_settings[chat_bubbles_shadow_color]" value="<?php echo esc_attr( $options['chat_bubbles_shadow_color'] ?? '#000000' ); ?>" class="my-color-field" data-default-color="#000000" data-alpha-enabled="true" />
				<br/>

				<!-- Distance -->
				<label style="margin-right: 5px; width: 60px; display:inline-block;"><?php esc_html_e( 'Distance:', 'stackboost-for-supportcandy' ); ?></label>
				<input type="range" name="stackboost_settings[chat_bubbles_shadow_distance]" min="0" max="20" step="1" value="<?php echo esc_attr( $options['chat_bubbles_shadow_distance'] ?? '2' ); ?>" oninput="this.nextElementSibling.value = this.value">
				<output><?php echo esc_html( $options['chat_bubbles_shadow_distance'] ?? '2' ); ?></output>px
				<br/>

				<!-- Blur -->
				<label style="margin-right: 5px; width: 60px; display:inline-block;"><?php esc_html_e( 'Blur:', 'stackboost-for-supportcandy' ); ?></label>
				<input type="range" name="stackboost_settings[chat_bubbles_shadow_blur]" min="0" max="50" step="1" value="<?php echo esc_attr( $options['chat_bubbles_shadow_blur'] ?? '5' ); ?>" oninput="this.nextElementSibling.value = this.value">
				<output><?php echo esc_html( $options['chat_bubbles_shadow_blur'] ?? '5' ); ?></output>px
				<br/>

				<!-- Spread -->
				<label style="margin-right: 5px; width: 60px; display:inline-block;"><?php esc_html_e( 'Spread:', 'stackboost-for-supportcandy' ); ?></label>
				<input type="range" name="stackboost_settings[chat_bubbles_shadow_spread]" min="-20" max="20" step="1" value="<?php echo esc_attr( $options['chat_bubbles_shadow_spread'] ?? '0' ); ?>" oninput="this.nextElementSibling.value = this.value">
				<output><?php echo esc_html( $options['chat_bubbles_shadow_spread'] ?? '0' ); ?></output>px
				<br/>

				<!-- Opacity -->
				<label style="margin-right: 5px; width: 60px; display:inline-block;"><?php esc_html_e( 'Opacity:', 'stackboost-for-supportcandy' ); ?></label>
				<input type="range" name="stackboost_settings[chat_bubbles_shadow_opacity]" min="0" max="100" step="5" value="<?php echo esc_attr( $options['chat_bubbles_shadow_opacity'] ?? '40' ); ?>" oninput="this.nextElementSibling.value = this.value">
				<output><?php echo esc_html( $options['chat_bubbles_shadow_opacity'] ?? '40' ); ?></output>%

				<p class="description"><?php esc_html_e( 'Adds a drop shadow to all bubbles.', 'stackboost-for-supportcandy' ); ?></p>
			</td>
		</tr>

		<!-- Image Styling -->
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Image Styling', 'stackboost-for-supportcandy' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="stackboost_settings[chat_bubbles_image_box]" id="chat_bubbles_image_box" value="1" <?php checked( $options['chat_bubbles_image_box'] ?? 0, 1 ); ?> /> <?php esc_html_e( 'Add Bounding Box to Images', 'stackboost-for-supportcandy' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Adds a border and padding around images to separate them from the bubble background.', 'stackboost-for-supportcandy' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render fields for a specific type tab (Agent/Customer/Note/Log).
	 */
	public static function render_type_fields( $type ) {
		$options = get_option( 'stackboost_settings', [] );
		$prefix  = "chat_bubbles_{$type}_"; // e.g. chat_bubbles_agent_

		?>
		<!-- Custom Fields Wrapper -->
		<tbody class="sb-chat-custom-fields" id="sb_chat_custom_fields_<?php echo esc_attr( $type ); ?>">

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
					<?php
					$font_size = $options[ $prefix . 'font_size' ] ?? '';
					if ( $font_size !== '' && (int) $font_size < 10 ) {
						$font_size = '';
					}
					?>
					<input type="number" name="stackboost_settings[<?php echo esc_attr( $prefix . 'font_size' ); ?>]" min="10" max="30" value="<?php echo esc_attr( $font_size ); ?>" class="small-text" placeholder="Default"> px
				</td>
			</tr>

			<!-- Font Styles -->
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Font Style', 'stackboost-for-supportcandy' ); ?></th>
				<td>
					<label style="margin-right: 10px;">
						<input type="checkbox" name="stackboost_settings[<?php echo esc_attr( $prefix . 'font_bold' ); ?>]" value="1" <?php checked( $options[$prefix . 'font_bold'] ?? 0, 1 ); ?> /> <?php esc_html_e( 'Bold', 'stackboost-for-supportcandy' ); ?>
					</label>
					<label style="margin-right: 10px;">
						<input type="checkbox" name="stackboost_settings[<?php echo esc_attr( $prefix . 'font_italic' ); ?>]" value="1" <?php checked( $options[$prefix . 'font_italic'] ?? 0, 1 ); ?> /> <?php esc_html_e( 'Italic', 'stackboost-for-supportcandy' ); ?>
					</label>
					<label>
						<input type="checkbox" name="stackboost_settings[<?php echo esc_attr( $prefix . 'font_underline' ); ?>]" value="1" <?php checked( $options[$prefix . 'font_underline'] ?? 0, 1 ); ?> /> <?php esc_html_e( 'Underline', 'stackboost-for-supportcandy' ); ?>
					</label>
				</td>
			</tr>

			<!-- Alignment -->
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Alignment', 'stackboost-for-supportcandy' ); ?></th>
				<td>
					<select name="stackboost_settings[<?php echo esc_attr( $prefix . 'alignment' ); ?>]" class="regular-text">
						<option value="left" <?php selected( $options[ $prefix . 'alignment' ] ?? 'left', 'left' ); ?>><?php esc_html_e( 'Left', 'stackboost-for-supportcandy' ); ?></option>
						<option value="right" <?php selected( $options[ $prefix . 'alignment' ] ?? 'left', 'right' ); ?>><?php esc_html_e( 'Right', 'stackboost-for-supportcandy' ); ?></option>
						<option value="center" <?php selected( $options[ $prefix . 'alignment' ] ?? 'left', 'center' ); ?>><?php esc_html_e( 'Center', 'stackboost-for-supportcandy' ); ?></option>
					</select>
				</td>
			</tr>

			<!-- Width (Slider) -->
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Max Width', 'stackboost-for-supportcandy' ); ?></th>
				<td>
					<input type="range" name="stackboost_settings[<?php echo esc_attr( $prefix . 'width' ); ?>]" min="0" max="100" step="5" value="<?php echo esc_attr( $options[ $prefix . 'width' ] ?? '85' ); ?>" oninput="this.nextElementSibling.value = this.value">
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

			<!-- Padding -->
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Padding', 'stackboost-for-supportcandy' ); ?></th>
				<td>
					<input type="number" name="stackboost_settings[<?php echo esc_attr( $prefix . 'padding' ); ?>]" min="0" max="50" value="<?php echo esc_attr( $options[ $prefix . 'padding' ] ?? '15' ); ?>" class="small-text"> px
				</td>
			</tr>

			<!-- Borders -->
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Border', 'stackboost-for-supportcandy' ); ?></th>
				<td>
					<select name="stackboost_settings[<?php echo esc_attr( $prefix . 'border_style' ); ?>]" style="margin-right: 5px;">
						<option value="none" <?php selected( $options[ $prefix . 'border_style' ] ?? 'none', 'none' ); ?>><?php esc_html_e( 'None', 'stackboost-for-supportcandy' ); ?></option>
						<option value="solid" <?php selected( $options[ $prefix . 'border_style' ] ?? 'none', 'solid' ); ?>><?php esc_html_e( 'Solid', 'stackboost-for-supportcandy' ); ?></option>
						<option value="dashed" <?php selected( $options[ $prefix . 'border_style' ] ?? 'none', 'dashed' ); ?>><?php esc_html_e( 'Dashed', 'stackboost-for-supportcandy' ); ?></option>
						<option value="dotted" <?php selected( $options[ $prefix . 'border_style' ] ?? 'none', 'dotted' ); ?>><?php esc_html_e( 'Dotted', 'stackboost-for-supportcandy' ); ?></option>
					</select>
					<?php
					$border_width = $options[ $prefix . 'border_width' ] ?? '1';
					if ( (int) $border_width < 1 ) {
						$border_width = '1';
					}
					?>
					<input type="number" name="stackboost_settings[<?php echo esc_attr( $prefix . 'border_width' ); ?>]" min="1" max="10" value="<?php echo esc_attr( $border_width ); ?>" class="small-text" placeholder="px"> px
					<span style="margin-left: 5px;">Color:</span>
					<input type="text" name="stackboost_settings[<?php echo esc_attr( $prefix . 'border_color' ); ?>]" value="<?php echo esc_attr( $options[ $prefix . 'border_color' ] ?? '#cccccc' ); ?>" class="my-color-field" data-default-color="#cccccc" />
				</td>
			</tr>

		</tbody>
		<?php
	}

	/**
	 * Sanitize Settings keys.
	 */
	public static function get_settings_keys() {
		$keys = [
			'chat_bubbles_enable_ticket',
			'chat_bubbles_show_avatars',
			'chat_bubbles_theme',
			'chat_bubbles_shadow_enable',
			'chat_bubbles_shadow_color',
			'chat_bubbles_shadow_distance',
			'chat_bubbles_shadow_blur',
			'chat_bubbles_shadow_spread',
			'chat_bubbles_shadow_opacity',
			'chat_bubbles_image_box',
		];
		foreach ( ['agent', 'customer', 'note', 'log'] as $type ) {
			$prefix = "chat_bubbles_{$type}_";
			$keys[] = "{$prefix}bg_color";
			$keys[] = "{$prefix}text_color";
			$keys[] = "{$prefix}font_family";
			$keys[] = "{$prefix}font_size";
			$keys[] = "{$prefix}font_bold";
			$keys[] = "{$prefix}font_italic";
			$keys[] = "{$prefix}font_underline";
			$keys[] = "{$prefix}alignment";
			$keys[] = "{$prefix}width";
			$keys[] = "{$prefix}radius";
			$keys[] = "{$prefix}padding";
			$keys[] = "{$prefix}border_style";
			$keys[] = "{$prefix}border_width";
			$keys[] = "{$prefix}border_color";
		}
		return $keys;
	}
}
