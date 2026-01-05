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
		// Optimization: Removed 'SHOW TABLES' check. If table doesn't exist, query returns false/empty safely.
		$custom_fields_table = $wpdb->prefix . 'psmsc_custom_fields';

		// Suppress errors for this specific query to avoid noise if SC is inactive
		$custom_fields = $wpdb->get_results( "SELECT slug, name FROM `{$custom_fields_table}`", ARRAY_A );

		if ( $custom_fields ) {
			foreach ( $custom_fields as $field ) {
				$columns[ $field['slug'] ] = $field['name'];
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

		// Optimization: Removed 'SHOW TABLES' check.
		$results = $wpdb->get_results( "SELECT id, name FROM `{$status_table}` ORDER BY name ASC" );

		if ( $results ) {
			foreach ( $results as $status ) {
				$statuses[ $status->id ] = $status->name;
			}
		}

		set_transient( 'stackboost_sc_statuses_cache', $statuses, HOUR_IN_SECONDS );

		return $statuses;
	}
