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
		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_CHAT_BUBBLES ) ) {
			return;
		}

		// Get active theme class
		$theme_class = 'sb-theme-clean-tech'; // Default
		if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
			$theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
		}

		// Common Actions SVG Block
		$action_icons = '
		<span title="Info"><svg viewBox="0 0 512 465" xmlns="http://www.w3.org/2000/svg" style="height: 14px; width: 14px;"><g fill="none"><g fill="currentColor"><path d="M239.911,109.088 C239.911,95.281 251.104,84.088 264.911,84.088 C278.718,84.088 289.911,95.281 289.911,109.088 C289.911,122.895 278.718,134.088 264.911,134.088 C251.104,134.088 239.911,122.896 239.911,109.088 Z M278.529,293.083 C274.506,295.046 271.305,293.43 269.757,292.29 C268.293,291.213 265.981,288.856 266.309,284.86 L282.185,221.713 C282.339,221.099 282.464,220.478 282.56,219.852 C285.288,201.964 277.926,184.192 263.348,173.471 C248.766,162.747 229.602,161.017 213.338,168.954 C199.782,175.568 196.97,176.938 196.96,176.943 L214.477,212.904 C214.477,212.904 217.28,211.539 230.879,204.903 C234.903,202.941 238.102,204.558 239.65,205.696 C241.114,206.773 243.426,209.131 243.099,213.127 L227.223,276.274 C227.069,276.888 226.944,277.509 226.848,278.134 C224.119,296.021 231.481,313.793 246.058,324.514 C254.566,330.771 264.633,333.967 274.776,333.967 C282.016,333.967 289.295,332.339 296.07,329.033 L309.658,322.402 L292.117,286.453 L278.529,293.083 Z M512,212 C512,248.195 505.077,280.709 491.422,308.641 C478.414,335.25 459.293,357.791 434.59,375.64 C390.803,407.277 329.047,424 256,424 C235.558,424 215.815,422.687 197.155,420.091 C154.825,462.553 102.766,465.001 91.419,465.001 C91.309,465.001 91.203,465.001 91.101,465.001 L90.996,465.001 L55.041,464.948 L74.024,434.428 C74.123,434.266 85.132,416.127 93.615,386.196 C32.285,350.171 0,290.284 0,212 C0,175.805 6.923,143.291 20.578,115.359 C33.586,88.75 52.708,66.208 77.41,48.36 C121.197,16.722 182.953,0 256,0 C329.047,0 390.803,16.723 434.59,48.36 C459.292,66.208 478.413,88.75 491.422,115.359 C505.077,143.29 512,175.805 512,212 Z M472,212 C472,154.097 451.532,109.949 411.164,80.783 C374.238,54.102 320.583,40 256,40 C191.417,40 137.762,54.103 100.836,80.783 C60.468,109.949 40,154.097 40,212 C40,280.877 68.818,330.016 125.654,358.051 L139.727,364.992 L136.333,380.312 C133.04,395.179 128.958,408.227 124.929,419.098 C140.446,413.869 158.727,404.049 174.561,385.659 L181.93,377.099 L193.066,378.99 C212.637,382.314 233.811,383.999 256.001,383.999 C320.584,383.999 374.239,369.896 411.165,343.216 C451.532,314.05 472,269.902 472,212 Z"></path></g></g></svg></span>
		<span title="New Ticket"><svg viewBox="0 0 427 427" xmlns="http://www.w3.org/2000/svg" style="height: 14px; width: 14px;"><g fill="none"><g fill="currentColor"><path d="M320,0 L106.666667,0 C47.7855328,0.0705304818 0.0705304818,47.7855328 0,106.666667 L0,320 C0.0705304818,378.881134 47.7855328,426.596136 106.666667,426.666667 L320,426.666667 C378.881134,426.596136 426.596136,378.881134 426.666667,320 L426.666667,106.666667 C426.596136,47.7855328 378.881134,0.0705304818 320,0 L320,0 Z M384,320 C384,355.346224 355.346224,384 320,384 L106.666667,384 C71.3204427,384 42.6666667,355.346224 42.6666667,320 L42.6666667,106.666667 C42.6666667,71.3204427 71.3204427,42.6666667 106.666667,42.6666667 L320,42.6666667 C355.346224,42.6666667 384,71.3204427 384,106.666667 L384,320 Z"></path><path d="M298.666667,192 L234.666667,192 L234.666667,128 C234.666667,116.217925 225.115408,106.666667 213.333333,106.666667 C201.551259,106.666667 192,116.217925 192,128 L192,192 L128,192 C116.217925,192 106.666667,201.551259 106.666667,213.333333 C106.666667,225.115408 116.217925,234.666667 128,234.666667 L192,234.666667 L192,298.666667 C192,310.448741 201.551259,320 213.333333,320 C225.115408,320 234.666667,310.448741 234.666667,298.666667 L234.666667,234.666667 L298.666667,234.666667 C310.448741,234.666667 320,225.115408 320,213.333333 C320,201.551259 310.448741,192 298.666667,192 Z"></path></g></g></svg></span>
		<span title="Edit"><svg viewBox="0 0 470 470" xmlns="http://www.w3.org/2000/svg" style="height: 14px; width: 14px;"><g fill="none"><g fill="currentColor"><path d="M418.133333,51.2 C384,17.0666667 330.666667,17.0666667 296.533333,51.2 L266.666667,81.0666667 L149.333333,200.533333 C145.066667,204.8 142.933333,209.066667 142.933333,213.333333 L128,317.866667 C128,324.266667 130.133333,330.666667 134.4,334.933333 C138.666667,339.2 142.933333,341.333333 149.333333,341.333333 L151.466667,341.333333 L256,326.4 C260.266667,326.4 264.533333,324.266667 268.8,320 L388.266667,202.666667 L418.133333,172.8 C452.266667,138.666667 452.266667,85.3333333 418.133333,51.2 Z M243.2,285.866667 L172.8,294.4 L181.333333,224 L281.6,128 L341.333333,187.733333 L243.2,285.866667 Z M388.266667,142.933333 L373.333333,157.866667 L313.6,98.1333333 L328.533333,83.2 C345.6,66.1333333 371.2,66.1333333 388.266667,83.2 C405.333333,98.1333333 405.333333,125.866667 388.266667,142.933333 Z"></path><path d="M341.333333,469.333333 L128,469.333333 C57.6,469.333333 0,411.733333 0,341.333333 L0,128 C0,57.6 57.6,0 128,0 L234.666667,0 C247.466667,0 256,8.53333333 256,21.3333333 C256,34.133333 247.466667,42.6666667 234.666667,42.6666667 L128,42.6666667 C81.0666667,42.6666667 42.6666667,81.0666667 42.6666667,128 L42.6666667,341.333333 C42.6666667,388.266667 81.0666667,426.666667 128,426.666667 L341.333333,426.666667 C388.266667,426.666667 426.666667,388.266667 426.666667,341.333333 L426.666667,234.666667 C426.666667,221.866667 435.2,213.333333 448,213.333333 C460.8,213.333333 469.333333,221.866667 469.333333,234.666667 L469.333333,341.333333 C469.333333,411.733333 411.733333,469.333333 341.333333,469.333333 Z"></path></g></g></svg></span>
		<span title="Delete"><svg viewBox="0 0 384 470" xmlns="http://www.w3.org/2000/svg" style="height: 14px; width: 14px;"><g fill="none"><g fill="currentColor"><path d="M341.333333,128 C329.551259,128 320,137.551259 320,149.333333 L320,388.074667 C318.726183,410.529608 299.584596,427.757036 277.12,426.666667 L106.88,426.666667 C84.4154039,427.757036 65.2738172,410.529608 64,388.074667 L64,149.333333 C63.9999996,137.551259 54.4487411,128 42.6666667,128 C30.8845923,128 21.3333333,137.551259 21.3333333,149.333333 L21.3333333,388.074667 C22.6011339,434.099423 60.850894,470.431926 106.88,469.333333 L277.12,469.333333 C323.149106,470.431926 361.398866,434.099423 362.666667,388.074667 L362.666667,149.333333 C362.666667,137.551259 353.115408,128 341.333333,128 Z"></path><path d="M362.666667,64 L277.333333,64 L277.333333,21.3333333 C277.333333,9.55125867 267.782075,0 256,0 L128,0 C116.217925,0 106.666667,9.55125867 106.666667,21.3333333 L106.666667,64 L21.3333333,64 C9.55125867,64 0,73.5512587 0,85.3333333 C0,97.115408 9.55125867,106.666667 21.3333333,106.666667 L362.666667,106.666667 C374.448741,106.666667 384,97.115408 384,85.3333333 C384,73.5512587 374.448741,64 362.666667,64 Z M149.333333,64 L149.333333,42.6666667 L234.666667,42.6666667 L234.666667,64 L149.333333,64 Z"></path><path d="M170.666667,341.333333 L170.666667,192 C170.666667,180.217925 161.115408,170.666667 149.333333,170.666667 C137.551259,170.666667 128,180.217925 128,192 L128,341.333333 C128,353.115408 137.551259,362.666667 149.333333,362.666667 C161.115408,362.666667 170.666667,353.115408 170.666667,341.333333 Z"></path><path d="M256,341.333333 L256,192 C256,180.217925 246.448741,170.666667 234.666667,170.666667 C222.884592,170.666667 213.333333,180.217925 213.333333,192 L213.333333,341.333333 C213.333333,353.115408 222.884592,362.666667 234.666667,362.666667 C246.448741,362.666667 256,353.115408 256,341.333333 Z"></path></g></g></svg></span>';

		// Define allowed HTML for SVG
		$allowed_svg = [
			'span' => [
				'title' => true,
			],
			'svg' => [
				'viewbox' => true,
				'xmlns' => true,
				'style' => true,
				'height' => true,
				'width' => true,
				'aria-hidden' => true,
			],
			'g' => [
				'fill' => true,
			],
			'path' => [
				'd' => true,
				'fill' => true,
			],
		];

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

							<div class="stackboost-chat-preview-container" style="background: #f0f0f1; padding: 20px;">

								<!-- 1. Customer Bubble -->
								<div id="preview-row-customer" class="sb-preview-row wpsc-thread reply customer">
									<div class="thread-avatar">
										<img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSIxMiIgZmlsbD0iI2NjYyIvPjxwYXRoIGQ9Ik0xMiAxMmMyLjIxIDAgNC0xLjc5IDQtNHMtMS43OS00LTQtNC00IDEuNzktNCA0IDEuNzkgNCA0IDR6bTAgMmMtMi42NyAwLTggMS4zNC04IDR2MmgxNnYtMmMwLTIuNjYtNS4zMy00LTgtNHoiIGZpbGw9IiNmZmYiLz48L3N2Zz4=" alt="Avatar" />
									</div>
									<div class="thread-body">
										<div class="thread-header">
											<div class="user-info">
												<div style="display: flex;">
													<h2 class="user-name"><?php esc_html_e( 'John Doe', 'stackboost-for-supportcandy' ); ?></h2>
													<h2>
														<small class="thread-type">
															<i><?php esc_html_e( 'reported', 'stackboost-for-supportcandy' ); ?></i>
														</small>
													</h2>
												</div>
												<span class="thread-time">10:30 AM</span>
											</div>
											<div class="actions">
												<?php
												echo wp_kses( $action_icons, $allowed_svg );
												?>
											</div>
										</div>
										<div class="thread-text">
											<p><?php esc_html_e( 'Hi, I need help with my account. I cannot log in.', 'stackboost-for-supportcandy' ); ?></p>
										</div>
									</div>
								</div>

								<!-- 2. Note Bubble -->
								<div id="preview-row-note" class="sb-preview-row wpsc-thread note">
									<div class="thread-avatar">
										<img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSIxMiIgZmlsbD0iI2NjYyIvPjxwYXRoIGQ9Ik0xMiAxMmMyLjIxIDAgNC0xLjc5IDQtNHMtMS43OS00LTQtNC00IDEuNzktNCA0IDEuNzkgNCA0IDR6bTAgMmMtMi42NyAwLTggMS4zNC04IDR2MmgxNnYtMmMwLTIuNjYtNS4zMy00LTgtNHoiIGZpbGw9IiNmZmYiLz48L3N2Zz4=" alt="Avatar" />
									</div>
									<div class="thread-body">
										<div class="thread-header">
											<div class="user-info">
												<div style="display: flex;">
													<h2 class="user-name"><?php esc_html_e( 'Agent', 'stackboost-for-supportcandy' ); ?></h2>
													<h2>
														<small class="thread-type">
															<i><?php esc_html_e( 'added a note', 'stackboost-for-supportcandy' ); ?></i>
														</small>
													</h2>
												</div>
												<span class="thread-time">10:32 AM</span>
											</div>
											<div class="actions">
												<?php
												echo wp_kses( $action_icons, $allowed_svg );
												?>
											</div>
										</div>
										<div class="thread-text">
											<p><?php esc_html_e( 'Checked logs. Failed login attempts from unknown IP.', 'stackboost-for-supportcandy' ); ?></p>
										</div>
									</div>
								</div>

								<!-- 3. Log Bubble (No Actions) -->
								<div id="preview-row-log" class="sb-preview-row wpsc-thread log">
									<div class="thread-avatar">
										<img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSIxMiIgZmlsbD0iI2NjYyIvPjxwYXRoIGQ9Ik0xMiAxMmMyLjIxIDAgNC0xLjc5IDQtNHMtMS43OS00LTQtNC00IDEuNzktNCA0IDEuNzkgNCA0IDR6bTAgMmMtMi42NyAwLTggMS4zNC04IDR2MmgxNnYtMmMwLTIuNjYtNS4zMy00LTgtNHoiIGZpbGw9IiNmZmYiLz48L3N2Zz4=" alt="Avatar" />
									</div>
									<div class="thread-body">
										<div class="thread-header">
											<div class="user-info">
												<div>
													<?php esc_html_e( 'The Status has been changed', 'stackboost-for-supportcandy' ); ?>
												</div>
												<span class="thread-time">10:33 AM</span>
											</div>
										</div>
										<div class="thread-text">
											<div class="wpsc-log-diff">
												<div class="lhs">New</div>
												<div class="transform-icon">
													<svg aria-hidden="true" style="height: 12px; width: 12px; margin: 0 10px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="currentColor" d="M190.5 66.9l22.2-22.2c9.4-9.4 24.6-9.4 33.9 0L441 239c9.4 9.4 9.4 24.6 0 33.9L246.6 467.3c-9.4 9.4-24.6 9.4-33.9 0l-22.2-22.2c-9.5-9.5-9.3-25 .4-34.3L311.4 296H24c-13.3 0-24-10.7-24-24v-32c0-13.3 10.7-24 24-24h287.4L190.9 101.2c-9.8-9.3-10-24.8-.4-34.3z"></path></svg>
												</div>
												<div class="rhs">In Progress</div>
											</div>
										</div>
									</div>
								</div>

								<!-- 4. Agent Bubble -->
								<div id="preview-row-agent" class="sb-preview-row wpsc-thread reply agent">
									<div class="thread-avatar">
										<img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSIxMiIgZmlsbD0iI2NjYyIvPjxwYXRoIGQ9Ik0xMiAxMmMyLjIxIDAgNC0xLjc5IDQtNHMtMS43OS00LTQtNC00IDEuNzktNCA0IDEuNzkgNCA0IDR6bTAgMmMtMi42NyAwLTggMS4zNC04IDR2MmgxNnYtMmMwLTIuNjYtNS4zMy00LTgtNHoiIGZpbGw9IiNmZmYiLz48L3N2Zz4=" alt="Avatar" />
									</div>
									<div class="thread-body">
										<div class="thread-header">
											<div class="user-info">
												<div style="display: flex;">
													<h2 class="user-name"><?php esc_html_e( 'Support Agent', 'stackboost-for-supportcandy' ); ?></h2>
													<h2>
														<small class="thread-type">
															<i><?php esc_html_e( 'replied', 'stackboost-for-supportcandy' ); ?></i>
														</small>
													</h2>
												</div>
												<span class="thread-time">10:35 AM</span>
											</div>
											<div class="actions">
												<?php
												echo wp_kses( $action_icons, $allowed_svg );
												?>
											</div>
										</div>
										<div class="thread-text">
											<p>
												<?php esc_html_e( 'Hello John, I can reset that for you right now.', 'stackboost-for-supportcandy' ); ?>
												<br/>
												<img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxNTAgNTAiIHdpZHRoPSIxNTAiIGhlaWdodD0iNTAiPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9IiNjY2MiLz48dGV4dCB4PSI1MCUiIHk9IjUwJSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZm9udC1mYW1pbHk9InNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM1NTUiPkltYWdlPC90ZXh0Pjwvc3ZnPg==" alt="Placeholder" style="max-width:100%; height:auto; margin-top:5px;" class="sb-chat-preview-img" />
											</p>
										</div>
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
