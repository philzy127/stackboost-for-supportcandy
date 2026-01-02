# Removed Chat Bubbles Email Functionality

## Reason for Removal
The implementation required to properly intercept and style SupportCandy emails was becoming too invasive ("hijacking") or requiring "reinventing the wheel" due to SupportCandy's architecture. It has been removed for now.

## Removed Code

### From `src/Modules/ChatBubbles/Core.php`

#### Hooks
```php
// Hook into Email Notifications to style the body
// We use `wpsc_en_before_sending` to intercept all emails just before they go out.
// This gives us access to the fully constructed body and the thread object.
add_filter( 'wpsc_en_before_sending', [ $this, 'process_email_content' ] );

// Hook into SupportCandy Option retrieval to inject markers around history macros
// Targeting the main templates option which holds all email templates
add_filter( 'option_wpsc-email-templates', [ $this, 'inject_bubble_markers' ] );
```

#### Methods

```php
	/**
	 * Inject markers around history and current reply macros in email templates.
	 *
	 * @param mixed $value The option value (array of templates).
	 * @return mixed The modified option value.
	 */
	public function inject_bubble_markers( $value ) {
		// Check email specific enable switch
		$options = get_option( 'stackboost_settings', [] );
		if ( empty( $options['chat_bubbles_enable_email'] ) ) {
			return $value;
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		// Regex to find history macros: {ticket_history...} or {{ticket_history...}}
		// Now optionally matches surrounding <p> tags with optional attributes.
		$history_pattern = '/(<p\b[^>]*>\s*)?(\{\{?(ticket_history(?:_[a-z_]+)?)\}?})(\s*<\/p>)?/i';

		// Regex to find current message macros: {last_reply}, {last_note}, {ticket_description}
		// Now optionally matches surrounding <p> tags with optional attributes.
		$current_pattern = '/(<p\b[^>]*>\s*)?(\{\{?(last_reply|last_note|ticket_description)\}?})(\s*<\/p>)?/i';

		foreach ( $value as $key => $template ) {
			if ( isset( $template['body']['text'] ) ) {

				$original_text = $template['body']['text'];
				$modified_text = $original_text;
				$changed = false;

				// Log raw template (Before) - Unconditional check for existence of macros to reduce log noise,
				// but logging "Before" state if we are about to attempt modification.
				if ( preg_match( $history_pattern, $original_text ) || preg_match( $current_pattern, $original_text ) ) {
					if ( function_exists( 'stackboost_log' ) ) {
						stackboost_log( "DEBUG: Template [{$key}] raw content (BEFORE INJECTION):\n" . $original_text, 'chat_bubbles' );
					}
				}

				// 1. Inject History Markers
				// Strategy: Check if markers are already present in the string. If so, we assume injection happened.
				// This is safest to avoid corruption.
				if ( strpos( $modified_text, '<!--SB_HISTORY_START-->' ) === false ) {
					if ( preg_match( $history_pattern, $modified_text ) ) {
						$modified_text = preg_replace_callback(
							$history_pattern,
							function( $matches ) {
								if ( function_exists( 'stackboost_log' ) && ( !empty($matches[1]) || !empty($matches[3]) ) ) {
									stackboost_log( "DEBUG: Captured wrapping <p> tags around HISTORY macro. Stripping them.", 'chat_bubbles' );
								}
								// Wrap the ENTIRE matched string (including P tags if found)
								return '<!--SB_HISTORY_START-->' . $matches[0] . '<!--SB_HISTORY_END-->';
							},
							$modified_text
						);
						if ( $modified_text !== $original_text ) {
							$changed = true;
						}
					}
				}

				// 2. Inject Current Message Markers (If Enabled)
				if ( ! empty( $options['chat_bubbles_enable_email_current_message'] ) ) {
					if ( strpos( $modified_text, '<!--SB_CURRENT_START-->' ) === false ) {
						if ( preg_match( $current_pattern, $modified_text ) ) {
							$modified_text = preg_replace_callback(
								$current_pattern,
								function( $matches ) {
									if ( function_exists( 'stackboost_log' ) && ( !empty($matches[1]) || !empty($matches[3]) ) ) {
										stackboost_log( "DEBUG: Captured wrapping <p> tags around CURRENT MSG macro. Stripping them.", 'chat_bubbles' );
									}
									// Wrap the ENTIRE matched string (including P tags if found)
									return '<!--SB_CURRENT_START-->' . $matches[0] . '<!--SB_CURRENT_END-->';
								},
								$modified_text
							);
							$changed = true;
						}
					}
				}

				if ( $changed ) {
					// Update the value
					$value[ $key ]['body']['text'] = $modified_text;

					// Log modified template (After)
					if ( function_exists( 'stackboost_log' ) ) {
						stackboost_log( "DEBUG: Template [{$key}] modified content (AFTER INJECTION):\n" . $modified_text, 'chat_bubbles' );
					}
				}
			}
		}

		return $value;
	}

	/**
	 * Process Email Content.
	 * Wraps the new message content in a styled bubble container.
	 *
	 * @param object $en The Email Notification object.
	 * @return object The modified Email Notification object.
	 */
	public function process_email_content( $en ) {
		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "DEBUG: process_email_content called.", 'chat_bubbles' );
		}

		// Check email specific enable switch
		$options = get_option( 'stackboost_settings', [] );
		if ( empty( $options['chat_bubbles_enable_email'] ) ) {
			return $en;
		}

		// Only proceed if we have a valid thread object
		if ( ! isset( $en->thread ) || ! is_object( $en->thread ) ) {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "DEBUG: No valid thread object. Skipping.", 'chat_bubbles' );
			}
			return $en;
		}

		// -------------------------------------------------------------------------
		// DIAGNOSTIC LOGGING: Log the RAW Body we received
		// -------------------------------------------------------------------------
		if ( function_exists( 'stackboost_log' ) ) {
			// Log the first 2000 chars to cover the header and likely the history block
			$raw_snippet = substr( $en->body, 0, 2000 );
			stackboost_log( "DEBUG: RAW EMAIL BODY RECEIVED (First 2000 chars):\n" . $raw_snippet, 'chat_bubbles' );

			// Specifically search for our marker in the raw body
			if ( strpos( $en->body, '<!--SB_HISTORY_START-->' ) !== false ) {
				stackboost_log( "DEBUG: SUCCESS! Found '<!--SB_HISTORY_START-->' in email body.", 'chat_bubbles' );
			} else {
				stackboost_log( "DEBUG: FAILURE! Did NOT find '<!--SB_HISTORY_START-->' in email body.", 'chat_bubbles' );
			}
		}

		$ticket = null;
		if ( isset( $en->thread->ticket ) ) {
			$ticket = $en->thread->ticket;
		} elseif ( isset( $en->ticket ) ) {
			$ticket = $en->ticket;
		} elseif ( isset( $en->thread->ticket_id ) && class_exists( 'WPSC_Ticket' ) ) {
			$ticket = new \WPSC_Ticket( $en->thread->ticket_id );
		}

		// -------------------------------------------------------------------------
		// STEP 1: Process History (Post-Process HTML via Markers)
		// We look for <!--SB_HISTORY_START-->...<!--SB_HISTORY_END-->
		// -------------------------------------------------------------------------

		if ( $ticket ) {
			// Find the block between markers
			// Use #s modifier for dot-matches-newline
			$marker_pattern = '/<!--SB_HISTORY_START-->(.*?)<!--SB_HISTORY_END-->/s';

			if ( preg_match( $marker_pattern, $en->body, $block_matches ) ) {

				$history_block = $block_matches[1];

				// Now parse the ugly HTML INSIDE this block
				$inner_pattern = '#<strong>\s*(.*?)\s*<small>\s*<i>\s*(.*?)\s*</i>\s*</small>\s*</strong>\s*<div style="font-size:10px;">\s*(.*?)\s*</div>\s*(.*?)(?=<br><hr><br>|$)#si';

				$new_history_html = preg_replace_callback( $inner_pattern, function( $matches ) use ( $ticket ) {
					$name = trim( strip_tags( $matches[1] ) );
					$action = trim( strip_tags( $matches[2] ) ); // reported, replied, added a note
					$date_str = trim( $matches[3] );
					$content = $matches[4]; // Preserve HTML content

					// Determine User Type based on Name/Action
					// For email history, we don't have the nice classes, so we must infer.
					$user_type = 'agent'; // Default

					if ( stripos( $action, 'note' ) !== false ) {
						$user_type = 'note';
					} elseif ( stripos( $action, 'log' ) !== false || stripos( $action, 'status' ) !== false || stripos( $action, 'assignee' ) !== false ) {
						// Rudimentary check for log entries in history if they appear
						$user_type = 'log';
					} elseif ( $ticket && isset( $ticket->customer ) && $name === $ticket->customer->name ) {
						$user_type = 'customer';
					}

					// Get Styling
					$inline_css = $this->get_email_inline_styles( $user_type );

					if ( empty( $inline_css ) ) {
						return $matches[0];
					}

					// Build Bubble HTML
					$html = '<div style="margin-bottom: 20px;">'; // Wrapper for spacing

					// Bubble Body
					$html .= '<div style="' . esc_attr( $inline_css ) . '">';

					// Header inside the bubble
					$html .= '<div style="margin-bottom: 5px; font-size: 12px; color: inherit; opacity: 0.8; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 5px;">';
					$html .= '<strong>' . esc_html( $name ) . '</strong>';
					if ( $date_str ) {
						$html .= ' - ' . esc_html( $date_str );
					}
					$html .= '</div>';

					// Content
					$html .= $content;
					$html .= '</div>';

					$html .= '</div>';

					return $html;

				}, $history_block );

				$new_history_html = str_replace( '<br><hr><br>', '', $new_history_html );

				$en->body = str_replace( $block_matches[0], $new_history_html, $en->body );

				if ( function_exists( 'stackboost_log' ) ) {
					stackboost_log( "DEBUG: Replaced history block with styled bubbles.", 'chat_bubbles' );
				}

			}
		}

		// -------------------------------------------------------------------------
		// STEP 2: Process Current Reply (Existing Logic with NEW Marker Strategy)
		// -------------------------------------------------------------------------

		// Only process reply and note types for the CURRENT message wrapping
		if ( ! in_array( $en->thread->type, [ 'reply', 'note', 'report', 'create', 'log' ] ) ) { // Added 'create' just in case
			return $en;
		}

		// Find the Current Message block between markers
		$current_marker_pattern = '/<!--SB_CURRENT_START-->(.*?)<!--SB_CURRENT_END-->/s';

		if ( preg_match( $current_marker_pattern, $en->body, $current_matches ) ) {

			$content_to_wrap = $current_matches[1];

			// Determine user type
			$user_type = $this->get_thread_user_type( $en->thread );

			// Get Inline CSS
			$inline_css = $this->get_email_inline_styles( $user_type );

			if ( ! empty( $inline_css ) ) {
				// Build Bubble HTML
				$html = '<div style="margin-bottom: 20px;">'; // Wrapper for spacing

				// Bubble Body
				$html .= '<div style="' . esc_attr( $inline_css ) . '">';

				// Header Info
				// Correctly determine name based on user type
				$name = '';
				$date_str = '';

				if ( $user_type === 'customer' ) {
					if ( isset( $en->thread->customer ) && is_object( $en->thread->customer ) ) {
						$name = $en->thread->customer->name;
					} elseif ( isset( $ticket->customer ) && is_object( $ticket->customer ) ) {
						$name = $ticket->customer->name;
					}
				} elseif ( $user_type === 'log' ) {
					$name = __( 'System Log', 'stackboost-for-supportcandy' );
				} else {
					// Agent or Note (usually Agent)
					// Try to find agent info in thread
					if ( isset( $en->thread->agent ) && is_object( $en->thread->agent ) ) {
						$name = $en->thread->agent->name; // SupportCandy agent object typically has a name property
					} elseif ( isset( $en->thread->created_by ) ) {
						// SupportCandy stores creator ID in created_by usually.
						// If created_by is available, we can try to fetch the user.
						$user_id = $en->thread->created_by;
						$user_info = get_userdata( $user_id );
						if ( $user_info ) {
							$name = $user_info->display_name;
						}
					}

					// Fallback if name is still empty (e.g. system note?)
					if ( empty( $name ) ) {
						$name = __( 'Support Agent', 'stackboost-for-supportcandy' );
					}
				}

				if ( isset( $en->thread->date ) ) {
					// Format date. $en->thread->date is likely a DateTime object or string.
					// SupportCandy usually stores it as DateTime object in properties or returns formatted string.
					// Let's use standard WP date format if possible, or just raw if string.
					$date_obj = $en->thread->date;
					if ( is_a( $date_obj, 'DateTime' ) ) {
						$date_str = $date_obj->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
					} else {
						$date_str = (string) $date_obj;
					}
				}

				// Header inside the bubble
				$html .= '<div style="margin-bottom: 5px; font-size: 12px; color: inherit; opacity: 0.8; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 5px;">';
				$html .= '<strong>' . esc_html( $name ) . '</strong>';
				if ( $date_str ) {
					$html .= ' - ' . esc_html( $date_str );
				}
				$html .= '</div>';

				// Content
				$html .= $content_to_wrap;
				$html .= '</div>'; // End styled bubble
				$html .= '</div>'; // End wrapper

				// Replace the entire marked block with our new HTML
				$en->body = str_replace( $current_matches[0], $html, $en->body );

				if ( function_exists( 'stackboost_log' ) ) {
					stackboost_log( "DEBUG: Successfully wrapped current reply (using markers).", 'chat_bubbles' );
				}
			}

		} else {
			// Fallback? Or just log failure.
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "DEBUG: Failed to find '<!--SB_CURRENT_START-->' markers for current reply.", 'chat_bubbles' );
			}
		}

		return $en;
	}

	/**
	 * Determine User Type for a Thread.
	 */
	private function get_thread_user_type( $thread ): string {
		if ( $thread->type === 'note' ) {
			return 'note';
		}
		if ( $thread->type === 'log' ) {
			return 'log';
		}

		$is_agent = false;

		// Attempt to identify if author is agent
		if ( isset( $thread->customer ) && is_object( $thread->customer ) ) {
			$email = $thread->customer->email;
			$user = get_user_by( 'email', $email );

			if ( $user ) {
				// Check for Agent capability
				if ( $user->has_cap( 'wpsc_agent' ) ) {
					$is_agent = true;
				}
			}
		}

		return $is_agent ? 'agent' : 'customer';
	}

	/**
	 * Helper to generate inline CSS string for emails.
	 *
	 * @param string $user_type The user type ('agent', 'customer', 'note', 'log').
	 * @return string The inline CSS string.
	 */
	public function get_email_inline_styles( string $user_type ): string {
		$styles = $this->get_styles_for_type( $user_type );
		if ( empty( $styles ) ) {
			return '';
		}

		$inline_css = sprintf(
			"background-color: %s; color: %s; padding: 15px; margin-bottom: 10px; border-radius: %dpx; width: %d%%;",
			$styles['bg_color'],
			$styles['text_color'],
			$styles['radius'],
			$styles['width']
		);

		if ( $styles['alignment'] === 'right' ) {
			$inline_css .= " margin-left: auto; margin-right: 0;";
		} elseif ( $styles['alignment'] === 'center' ) {
			$inline_css .= " margin: 0 auto;";
		} else {
			$inline_css .= " margin-right: auto; margin-left: 0;";
		}

		// Font Styles
		if ( ! empty( $styles['font_bold'] ) ) {
			$inline_css .= " font-weight: bold;";
		}
		if ( ! empty( $styles['font_italic'] ) ) {
			$inline_css .= " font-style: italic;";
		}
		if ( ! empty( $styles['font_underline'] ) ) {
			$inline_css .= " text-decoration: underline;";
		}

		// Border Styles
		if ( ! empty( $styles['border_style'] ) && $styles['border_style'] !== 'none' ) {
			$inline_css .= sprintf( " border: %s %dpx %s;", $styles['border_style'], $styles['border_width'], $styles['border_color'] );
		}

		return $inline_css;
	}
```

### From `src/Modules/ChatBubbles/Admin/Settings.php`

```php
		<?php
		// Email Enable
		?>
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Enable on Emails', 'stackboost-for-supportcandy' ); ?></th>
			<td>
				<input type="checkbox" name="stackboost_settings[chat_bubbles_enable_email]" id="chat_bubbles_enable_email" value="1" <?php checked( $options['chat_bubbles_enable_email'] ?? 0, 1 ); ?> />
				<p class="description"><?php esc_html_e( 'Apply chat styling to email notifications (Best Effort).', 'stackboost-for-supportcandy' ); ?></p>
			</td>
		</tr>

		<?php
		// Bubble Current Message
		?>
		<tr valign="top">
			<th scope="row"><?php esc_html_e( 'Bubble Current Message', 'stackboost-for-supportcandy' ); ?></th>
			<td>
				<input type="checkbox" name="stackboost_settings[chat_bubbles_enable_email_current_message]" id="chat_bubbles_enable_email_current_message" value="1" <?php checked( $options['chat_bubbles_enable_email_current_message'] ?? 0, 1 ); ?> />
				<p class="description"><?php esc_html_e( 'Wrap {{last_reply}}, {{last_note}}, and {{ticket_description}} in a chat bubble.', 'stackboost-for-supportcandy' ); ?></p>
			</td>
		</tr>
```
