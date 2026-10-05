<?php

namespace StackBoost\ForSupportCandy\Modules\TicketGuard;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Core Business Logic for Ticket Guard.
 *
 * Handles rules management, evaluation, and backend validation.
 *
 * @package StackBoost\ForSupportCandy\Modules\TicketGuard
 */
class Core {

	/** @var Core|null */
	private static ?Core $instance = null;

	/**
	 * Get the single instance of the class.
	 */
	public static function get_instance(): Core {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Check if the Ticket Guard feature is enabled globally.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		if ( ! stackboost_is_feature_active( 'ticket_guard' ) ) {
			return false;
		}

		$options = get_option( 'stackboost_settings', [] );
		return ! empty( $options['enable_ticket_guard'] );
	}

	/**
	 * Retrieve all active Ticket Guard rules.
	 *
	 * @return array
	 */
	public function get_rules(): array {
		$options = get_option( 'stackboost_settings', [] );
		$rules   = $options['ticket_guard_rules'] ?? [];
		return is_array( $rules ) ? array_values( $rules ) : [];
	}

	/**
	 * Save Ticket Guard configuration rules and master switch state.
	 *
	 * @param array $rules
	 * @param bool  $enabled
	 * @return true|\WP_Error
	 */
	public function save_config( array $rules, bool $enabled ) {
		$options = get_option( 'stackboost_settings', [] );
		if ( ! is_array( $options ) ) {
			$options = [];
		}

		$sanitized_rules = [];
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$keywords_raw = $rule['keywords'] ?? [];
			if ( is_string( $keywords_raw ) ) {
				$keywords_array = array_filter( array_map( 'trim', explode( "\n", $keywords_raw ) ) );
			} else {
				$keywords_array = array_map( 'sanitize_text_field', (array) $keywords_raw );
			}

			$monitored_field = '';
			if ( ! empty( $rule['monitored_field'] ) ) {
				$monitored_field = sanitize_key( $rule['monitored_field'] );
			} elseif ( ! empty( $rule['monitored_fields'] ) && is_array( $rule['monitored_fields'] ) ) {
				$monitored_field = sanitize_key( reset( $rule['monitored_fields'] ) );
			}

			$swap_field           = ! empty( $rule['swap_field'] ) ? sanitize_key( $rule['swap_field'] ) : '';
			$swap_value           = ! empty( $rule['swap_value'] ) ? sanitize_text_field( $rule['swap_value'] ) : ( ! empty( $rule['suggested_category'] ) ? sanitize_text_field( $rule['suggested_category'] ) : '' );
			$secondary_swap_field = ! empty( $rule['secondary_swap_field'] ) ? sanitize_key( $rule['secondary_swap_field'] ) : '';
			$secondary_swap_value = ! empty( $rule['secondary_swap_value'] ) ? sanitize_text_field( $rule['secondary_swap_value'] ) : '';

			$sanitized_rules[] = [
				'id'                   => sanitize_key( $rule['id'] ?? ( 'rule_' . wp_rand( 1000, 9999 ) ) ),
				'name'                 => sanitize_text_field( $rule['name'] ?? '' ),
				'enabled'              => ! empty( $rule['enabled'] ),
				'monitored_field'      => $monitored_field,
				'keywords'             => array_values( $keywords_array ),
				'swap_field'           => $swap_field,
				'swap_value'           => $swap_value,
				'secondary_swap_field' => $secondary_swap_field,
				'secondary_swap_value' => $secondary_swap_value,
				'suggested_category'   => $swap_value,
				'actions'              => [
					'disable_submit'      => ! empty( $rule['actions']['disable_submit'] ),
					'show_modal'          => ! empty( $rule['actions']['show_modal'] ),
					'show_inline_warning' => ! empty( $rule['actions']['show_inline_warning'] ),
					'auto_swap_category'  => ! empty( $rule['actions']['auto_swap_category'] ),
				],
				'messaging'            => [
					'modal_title'             => sanitize_text_field( $rule['messaging']['modal_title'] ?? '' ),
					'modal_body'              => wp_kses_post( $rule['messaging']['modal_body'] ?? '' ),
					'inline_warning'          => sanitize_text_field( $rule['messaging']['inline_warning'] ?? '' ),
					'inline_level'            => sanitize_key( $rule['messaging']['inline_level'] ?? 'alert' ),
					'submit_disabled_message' => sanitize_text_field( $rule['messaging']['submit_disabled_message'] ?? '' ),
					'is_kb_steering'          => ! empty( $rule['messaging']['is_kb_steering'] ),
					'kb_message'              => ! empty( $rule['messaging']['kb_message'] ) ? wp_kses_post( $rule['messaging']['kb_message'] ) : '',
					'kb_doc_ids'              => ! empty( $rule['messaging']['kb_doc_ids'] ) && is_array( $rule['messaging']['kb_doc_ids'] ) ? array_values( array_map( 'sanitize_text_field', $rule['messaging']['kb_doc_ids'] ) ) : [],
					'kb_docs'                 => $this->resolve_kb_docs( $rule['messaging']['is_kb_steering'] ?? false, $rule['messaging']['kb_doc_ids'] ?? [] ),
				],
			];
		}

		$options['page_slug']           = 'stackboost-ticket-guard';
		$options['enable_ticket_guard'] = $enabled ? 1 : 0;
		$options['ticket_guard_rules']  = $sanitized_rules;

		$updated = update_option( 'stackboost_settings', $options );

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( sprintf( 'Ticket Guard settings saved. Enabled: %d, Rule Count: %d', $enabled ? 1 : 0, count( $sanitized_rules ) ), 'ticket_guard' );
		}

		return true;
	}

	/**
	 * Retrieve all choice fields (dropdowns, radio buttons, checkboxes) and their available options in SupportCandy.
	 *
	 * @return array
	 */
	public function get_dropdown_fields_and_options(): array {
		$dropdowns = [];

		// 1. Check \WPSC_Custom_Field::$custom_fields if available
		if ( class_exists( '\WPSC_Custom_Field' ) && ! empty( \WPSC_Custom_Field::$custom_fields ) ) {
			foreach ( \WPSC_Custom_Field::$custom_fields as $slug => $field_obj ) {
				$type_class = is_object( $field_obj ) ? $field_obj->type : '';
				$type_slug  = is_string( $type_class ) && class_exists( $type_class ) && isset( $type_class::$slug ) ? $type_class::$slug : (string) $type_class;

				// Check if this field is a choice field (single-select, radio, checkbox, multi-select)
				$is_choice = in_array( $type_slug, [ 'cf_single_select', 'select', 'single_select', 'radio', 'cf_radio', 'checkbox', 'cf_checkbox', 'multi_select', 'cf_multi_select', 'df_category', 'df_priority', 'df_status' ], true )
					|| strpos( strtolower( $type_class ), 'select' ) !== false
					|| strpos( strtolower( $type_class ), 'radio' ) !== false
					|| strpos( strtolower( $type_class ), 'checkbox' ) !== false
					|| in_array( $slug, [ 'category', 'df_category', 'priority', 'df_priority', 'status', 'df_status' ], true );

				if ( $is_choice ) {
					$label = is_object( $field_obj ) ? $field_obj->name : $slug;
					$opts  = [];

					if ( is_object( $field_obj ) && method_exists( $field_obj, 'get_options' ) ) {
						$raw_opts = $field_obj->get_options();
						foreach ( $raw_opts as $o ) {
							$opts[] = [
								'id'   => (string) ( is_object( $o ) ? $o->id : ( is_array( $o ) ? $o['id'] : $o ) ),
								'name' => is_object( $o ) ? $o->name : ( is_array( $o ) ? $o['name'] : $o ),
							];
						}
					} elseif ( is_object( $field_obj ) && isset( $field_obj->options ) && is_array( $field_obj->options ) ) {
						foreach ( $field_obj->options as $o ) {
							$opts[] = [
								'id'   => (string) ( is_object( $o ) ? $o->id : ( is_array( $o ) ? $o['id'] : $o ) ),
								'name' => is_object( $o ) ? $o->name : ( is_array( $o ) ? $o['name'] : $o ),
							];
						}
					}

					if ( ! empty( $opts ) ) {
						$dropdowns[ $slug ] = [
							'label'   => $label,
							'options' => $opts,
						];
					}
				}
			}
		}

		// 2. Query database for choice fields from psmsc_custom_fields / wpsc_custom_fields
		global $wpdb;
		$table_name = $wpdb->prefix . 'psmsc_custom_fields';
		$table_name_like = $wpdb->esc_like( $table_name );
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name_like ) ) !== $table_name ) {
			$table_name = $wpdb->prefix . 'wpsc_custom_fields';
		}

		$safe_table = esc_sql( $table_name );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$cf_rows = $wpdb->get_results( "SELECT id, slug, name, type FROM `{$safe_table}` WHERE type IN ('select', 'single_select', 'cf_single_select', 'radio', 'cf_radio', 'checkbox', 'cf_checkbox', 'multi_select', 'cf_multi_select')", ARRAY_A );

		if ( ! empty( $cf_rows ) ) {
			$opts_table = $wpdb->prefix . 'psmsc_custom_field_options';
			$opts_table_like = $wpdb->esc_like( $opts_table );
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $opts_table_like ) ) !== $opts_table ) {
				$opts_table = $wpdb->prefix . 'wpsc_custom_field_options';
			}
			$safe_opts_table = esc_sql( $opts_table );

			foreach ( $cf_rows as $row ) {
				$slug = $row['slug'];
				if ( isset( $dropdowns[ $slug ] ) && ! empty( $dropdowns[ $slug ]['options'] ) ) {
					continue;
				}

				$field_id = (int) $row['id'];
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$opt_rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM `{$safe_opts_table}` WHERE field_id = %d ORDER BY name ASC", $field_id ), ARRAY_A );

				if ( ! empty( $opt_rows ) ) {
					$opts = [];
					foreach ( $opt_rows as $or ) {
						$opts[] = [
							'id'   => (string) $or['id'],
							'name' => $or['name'],
						];
					}
					$dropdowns[ $slug ] = [
						'label'   => $row['name'],
						'options' => $opts,
					];
				}
			}
		}

		// 3. Fallback for Standard Category / Priority if not populated yet
		if ( empty( $dropdowns['df_category'] ) && class_exists( '\WPSC_Category' ) ) {
			$raw_cats = \WPSC_Category::find( [ 'items_per_page' => 0 ] )['results'] ?? [];
			$category_options = [];
			foreach ( $raw_cats as $cat ) {
				$category_options[] = [
					'id'   => (string) ( is_object( $cat ) ? $cat->id : $cat['id'] ),
					'name' => is_object( $cat ) ? $cat->name : $cat['name'],
				];
			}
			if ( ! empty( $category_options ) ) {
				$dropdowns['df_category'] = [
					'label'   => __( 'Category', 'stackboost-for-supportcandy' ),
					'options' => $category_options,
				];
			}
		}

		if ( empty( $dropdowns['df_priority'] ) && class_exists( '\WPSC_Priority' ) ) {
			$raw_priorities = \WPSC_Priority::find( [ 'items_per_page' => 0 ] )['results'] ?? [];
			$priority_options = [];
			foreach ( $raw_priorities as $pri ) {
				$priority_options[] = [
					'id'   => (string) ( is_object( $pri ) ? $pri->id : $pri['id'] ),
					'name' => is_object( $pri ) ? $pri->name : $pri['name'],
				];
			}
			if ( ! empty( $priority_options ) ) {
				$dropdowns['df_priority'] = [
					'label'   => __( 'Priority', 'stackboost-for-supportcandy' ),
					'options' => $priority_options,
				];
			}
		}

		foreach ( $dropdowns as $slug => &$d_info ) {
			if ( ! empty( $d_info['options'] ) && is_array( $d_info['options'] ) ) {
				usort( $d_info['options'], function( $a, $b ) {
					return strnatcasecmp( $a['name'] ?? '', $b['name'] ?? '' );
				} );
			}
		}
		unset( $d_info );

		uasort( $dropdowns, function( $a, $b ) {
			return strnatcasecmp( $a['label'] ?? '', $b['label'] ?? '' );
		} );

		return $dropdowns;
	}

	/**
	 * Retrieve SupportCandy field choices and categories for the admin rule builder.
	 *
	 * @return array
	 */
	public function get_form_options(): array {
		$textarea_fields = [];

		// 1. Standard Included Description Fields
		$textarea_fields['description']    = __( 'Description', 'stackboost-for-supportcandy' );
		$textarea_fields['df_description'] = __( 'Description', 'stackboost-for-supportcandy' );

		// 2. Query Custom Fields repository for textareas and tinymce fields
		if ( class_exists( '\StackBoost\ForSupportCandy\Integration\SupportCandyRepository' ) ) {
			$sc_repo = new \StackBoost\ForSupportCandy\Integration\SupportCandyRepository();
			$custom_textareas = $sc_repo->get_textarea_fields();
			if ( ! empty( $custom_textareas ) ) {
				foreach ( $custom_textareas as $field ) {
					if ( ! empty( $field['slug'] ) && ! empty( $field['name'] ) ) {
						$textarea_fields[ $field['slug'] ] = $field['name'];
					}
				}
			}
		}

		// Fallback: If repo query returned empty custom fields, search columns list for description/textarea/tinymce
		if ( count( $textarea_fields ) <= 2 && class_exists( '\StackBoost\ForSupportCandy\WordPress\Plugin' ) ) {
			$plugin_instance = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance();
			$all_columns      = $plugin_instance->get_supportcandy_columns();
			foreach ( $all_columns as $slug => $label ) {
				if ( false !== strpos( strtolower( $slug ), 'description' ) ||
				     false !== strpos( strtolower( $slug ), 'textarea' ) ||
				     false !== strpos( strtolower( $slug ), 'tinymce' ) ||
				     false !== strpos( strtolower( $label ), 'description' ) ||
				     false !== strpos( strtolower( $label ), 'textarea' ) ) {
					$textarea_fields[ $slug ] = $label;
				}
			}
		}

		$categories = [];
		if ( class_exists( '\WPSC_Category' ) ) {
			$raw_categories = \WPSC_Category::find( [ 'items_per_page' => 0 ] )['results'] ?? [];
			foreach ( $raw_categories as $cat ) {
				$categories[] = [
					'id'   => is_object( $cat ) ? $cat->id : $cat['id'],
					'name' => is_object( $cat ) ? $cat->name : $cat['name'],
				];
			}
		}

		natcasesort( $textarea_fields );

		return [
			'fields'              => $textarea_fields,
			'categories'          => $categories,
			'dropdowns'           => $this->get_dropdown_fields_and_options(),
			'betterdocs_articles' => $this->get_betterdocs_articles(),
		];
	}

	/**
	 * Query BetterDocs published documentation articles if BetterDocs is installed/active.
	 *
	 * @return array List of published BetterDocs articles.
	 */
	public function get_betterdocs_articles(): array {
		$articles = [];
		if ( post_type_exists( 'docs' ) ) {
			$posts = get_posts( [
				'post_type'      => 'docs',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			] );

			foreach ( $posts as $p ) {
				$articles[] = [
					'id'    => (string) $p->ID,
					'title' => $p->post_title,
					'url'   => get_permalink( $p->ID ),
				];
			}
		}
		return $articles;
	}

	/**
	 * Resolve article titles and permalinks for specified KB doc IDs.
	 *
	 * @param bool  $is_kb_steering
	 * @param array $doc_ids
	 * @return array
	 */
	public function resolve_kb_docs( bool $is_kb_steering, array $doc_ids ): array {
		if ( ! $is_kb_steering || empty( $doc_ids ) ) {
			return [];
		}

		$kb_docs      = [];
		$all_articles = $this->get_betterdocs_articles();
		$article_map  = [];
		foreach ( $all_articles as $art ) {
			$article_map[ (string) $art['id'] ] = $art;
		}

		foreach ( $doc_ids as $doc_id ) {
			if ( isset( $article_map[ (string) $doc_id ] ) ) {
				$kb_docs[] = $article_map[ (string) $doc_id ];
			}
		}

		return $kb_docs;
	}

	/**
	 * Evaluate incoming ticket creation data against configured rules for backend enforcement.
	 *
	 * @param array $ticket_data Incoming data filter array from `wpsc_create_ticket_data`.
	 * @return array Sanitized/filtered ticket data.
	 */
	public function evaluate_submission_rules( array $ticket_data ): array {
		if ( ! $this->is_enabled() ) {
			return $ticket_data;
		}

		$rules = $this->get_rules();
		if ( empty( $rules ) ) {
			return $ticket_data;
		}

		foreach ( $rules as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			$monitored_field = $rule['monitored_field'] ?? '';
			if ( empty( $monitored_field ) && ! empty( $rule['monitored_fields'] ) ) {
				$monitored_field = is_array( $rule['monitored_fields'] ) ? reset( $rule['monitored_fields'] ) : (string) $rule['monitored_fields'];
			}

			$keywords = (array) ( $rule['keywords'] ?? [] );
			$actions  = $rule['actions'] ?? [];

			if ( empty( $monitored_field ) || empty( $keywords ) ) {
				continue;
			}

			// Scan field for keywords
			$matched = false;
			if ( ! empty( $ticket_data[ $monitored_field ] ) ) {
				$content = (string) $ticket_data[ $monitored_field ];

				foreach ( $keywords as $kw_line ) {
					$kw_line = trim( $kw_line );
					if ( empty( $kw_line ) ) {
						continue;
					}

					// Compound AND matching via &
					$parts = array_filter( array_map( 'trim', explode( '&', $kw_line ) ) );
					if ( empty( $parts ) ) {
						continue;
					}

					$line_matches = true;
					foreach ( $parts as $sub_kw ) {
						if ( false === stripos( $content, $sub_kw ) ) {
							$line_matches = false;
							break;
						}
					}

					if ( $line_matches ) {
						$matched = true;
						break;
					}
				}
			}

			if ( $matched ) {
				if ( function_exists( 'stackboost_log' ) ) {
					stackboost_log( sprintf( 'Ticket Guard Rule Matched on Backend: %s', $rule['name'] ?? 'Unnamed' ), 'ticket_guard' );
				}

				// If auto-swap category / dropdown action is set, apply primary & secondary swaps on backend
				$swap_field           = ! empty( $rule['swap_field'] ) ? $rule['swap_field'] : '';
				$swap_value           = ! empty( $rule['swap_value'] ) ? $rule['swap_value'] : ( $rule['suggested_category'] ?? '' );
				$secondary_swap_field = ! empty( $rule['secondary_swap_field'] ) ? $rule['secondary_swap_field'] : '';
				$secondary_swap_value = ! empty( $rule['secondary_swap_value'] ) ? $rule['secondary_swap_value'] : '';

				if ( ! empty( $actions['auto_swap_category'] ) ) {
					if ( ! empty( $swap_value ) ) {
						$ticket_data[ $swap_field ] = $swap_value;
					}
					if ( ! empty( $secondary_swap_field ) && ! empty( $secondary_swap_value ) ) {
						$ticket_data[ $secondary_swap_field ] = $secondary_swap_value;
					}
				}
			}
		}

		return $ticket_data;
	}
}
