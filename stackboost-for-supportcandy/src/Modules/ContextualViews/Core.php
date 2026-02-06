	/**
	 * Shadow Sync: Updates SupportCandy's global list items to include
	 * the union of all columns required by all Contextual View rules.
	 */
	public function sync_shadow_list_items() {
		$rules = $this->get_rules();
		$all_required_columns = [];

		foreach ( $rules as $rule ) {
			if ( ! empty( $rule['columns'] ) && is_array( $rule['columns'] ) ) {
				foreach ( $rule['columns'] as $col ) {
					$all_required_columns[ $col ] = true;
				}
			}
		}

		// Fetch the current SC global list.
		$sc_list = get_option( self::SC_OPTION_LIST_ITEMS, [] );

		// Safety check: if option doesn't exist or isn't array, abort.
		if ( ! is_array( $sc_list ) ) {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( 'Shadow Sync Aborted: Option ' . self::SC_OPTION_LIST_ITEMS . ' is not an array.', 'contextual-views' );
			}
			return;
		}

		// Detect format: Associative (slug => label) vs Indexed (slugs only)
		$is_associative = ! empty( $sc_list ) && ( array_keys( $sc_list ) !== range( 0, count( $sc_list ) - 1 ) );
		$changed = false;

		foreach ( array_keys( $all_required_columns ) as $slug ) {
			if ( $is_associative ) {
				if ( ! isset( $sc_list[ $slug ] ) ) {
					$label = $this->get_column_label( $slug );
					$sc_list[ $slug ] = $label;
					$changed = true;
				}
			} else {
				if ( ! in_array( $slug, $sc_list ) ) {
					$sc_list[] = $slug;
					$changed = true;
				}
			}
		}

		if ( $changed ) {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( 'Shadow Sync: Updating ' . self::SC_OPTION_LIST_ITEMS . '.', 'contextual-views' );
			}
			update_option( self::SC_OPTION_LIST_ITEMS, $sc_list );
		}
	}

	/**
	 * Helper to get column label.
	 *
	 * @param string $slug
	 * @return string
	 */
	private function get_column_label( $slug ) {
		if ( class_exists( '\StackBoost\ForSupportCandy\WordPress\Plugin' ) ) {
			$columns = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance()->get_supportcandy_columns();
			return $columns[ $slug ] ?? ucfirst( str_replace( '_', ' ', $slug ) );
		}
		return ucfirst( str_replace( '_', ' ', $slug ) );
	}
