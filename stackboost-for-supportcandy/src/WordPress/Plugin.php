	/**
	 * A utility function to get SupportCandy custom fields.
	 * Results are cached for 1 hour to improve performance.
	 *
	 * @return array
	 */
	public function get_supportcandy_columns(): array {
		$cached_columns = get_transient( 'stackboost_sc_columns_cache' );
		if ( false !== $cached_columns ) {
			return $cached_columns;
		}

		global $wpdb;
		$columns             = [];

		// 1. Fetch Custom Fields from DB
		$custom_fields_table = $wpdb->prefix . 'psmsc_custom_fields';

		// Performance Optimization: Check existence only if not cached,
		// and use a lighter check than SHOW TABLES if possible, but SHOW TABLES is standard.
		// However, we can wrap this in a try/catch if we assume it exists to skip the check.
		// For robustness + speed, we cache the result of the logic.

		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $custom_fields_table ) ) ) {
			$custom_fields = $wpdb->get_results( "SELECT slug, name FROM `{$custom_fields_table}`", ARRAY_A );
			if ( $custom_fields ) {
				foreach ( $custom_fields as $field ) {
					$columns[ $field['slug'] ] = $field['name'];
				}
			}
		}

		// 2. Add Standard Fields (Safe Hardcoding)
		$standard_fields = [
			'status'      => __( 'Status', 'stackboost-for-supportcandy' ),
			'df_status'   => __( 'Status', 'stackboost-for-supportcandy' ),
			'category'    => __( 'Category', 'stackboost-for-supportcandy' ),
			'df_category' => __( 'Category', 'stackboost-for-supportcandy' ),
			'priority'    => __( 'Priority', 'stackboost-for-supportcandy' ),
			'df_priority' => __( 'Priority', 'stackboost-for-supportcandy' ),
		];

		foreach ( $standard_fields as $slug => $name ) {
			if ( ! isset( $columns[ $slug ] ) ) {
				$columns[ $slug ] = $name;
			}
		}

		asort( $columns );

		set_transient( 'stackboost_sc_columns_cache', $columns, HOUR_IN_SECONDS );

		return $columns;
	}

	/**
	 * A utility function to get SupportCandy statuses.
	 * Results are cached for 1 hour.
	 *
	 * @return array Associative array of [ ID => Name ]
	 */
	public function get_supportcandy_statuses(): array {
		$cached_statuses = get_transient( 'stackboost_sc_statuses_cache' );
		if ( false !== $cached_statuses ) {
			return $cached_statuses;
		}

		global $wpdb;
		$statuses      = [];
		$status_table  = $wpdb->prefix . 'psmsc_statuses';

		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $status_table ) ) ) {
			$results = $wpdb->get_results( "SELECT id, name FROM `{$status_table}` ORDER BY name ASC" );
			if ( $results ) {
				foreach ( $results as $status ) {
					$statuses[ $status->id ] = $status->name;
				}
			}
		}

		set_transient( 'stackboost_sc_statuses_cache', $statuses, HOUR_IN_SECONDS );

		return $statuses;
	}
