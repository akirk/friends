<?php
/**
 * Bounded feed queries and cursor pagination.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Executes an independent feed query with SQL hooks scoped to that query only.
 */
class Feed_Query {
	/**
	 * The query currently being executed.
	 *
	 * @var \WP_Query
	 */
	private $query;

	/**
	 * Fetch a page plus one lookahead post, without counting the complete feed.
	 *
	 * @param array      $vars Signed feed query arguments without rendering context.
	 * @param array|null $cursor Validated date and ID cursor.
	 * @param bool       $single Whether diagnostics should fetch exactly one post.
	 * @return array Query, lookahead information, and the next cursor.
	 */
	public function fetch( $vars, $cursor = null, $single = false ) {
		$vars['paged'] = max( 1, isset( $vars['paged'] ) ? intval( $vars['paged'] ) : 1 );
		$per_page = max( 1, isset( $vars['posts_per_page'] ) ? intval( $vars['posts_per_page'] ) : intval( get_option( 'posts_per_page' ) ) );
		if ( $single ) {
			$per_page = 1;
		}
		$vars['offset'] = $cursor ? 0 : ( $vars['paged'] - 1 ) * $per_page;
		$vars['friends_feed_cursor'] = $cursor;
		$vars['posts_per_page'] = $single ? 1 : $per_page + 1;
		$vars['no_found_rows'] = true;
		$vars['friends_feed_fast'] = true;
		$vars['orderby'] = 'date ID';
		$this->query = new \WP_Query();
		add_filter( 'posts_clauses_request', array( $this, 'clauses' ), 99, 2 );
		try {
			$this->query->query( $vars );
		} finally {
			remove_filter( 'posts_clauses_request', array( $this, 'clauses' ), 99 );
		}
		$rows_fetched = $this->query->post_count;
		$has_more = $rows_fetched > $per_page;
		if ( $has_more ) {
			array_pop( $this->query->posts );
			$this->query->post_count = count( $this->query->posts );
		}
		$next_cursor = null;
		if ( $has_more ) {
			$last_post = end( $this->query->posts );
			$next_cursor = wp_json_encode(
				array(
					'date' => $last_post->post_date,
					'id'   => intval( $last_post->ID ),
				)
			);
		}
		return array(
			'query'        => $this->query,
			'has_more'     => $has_more,
			'rows_fetched' => $rows_fetched,
			'cursor'       => $next_cursor,
		);
	}

	/**
	 * Apply cursor conditions before choosing the optimized or standard query plan.
	 *
	 * @param array     $clauses WordPress-generated SQL clauses.
	 * @param \WP_Query $query Query being executed.
	 * @return array Feed SQL clauses; unrelated queries are untouched.
	 */
	public function clauses( $clauses, $query ) {
		if ( $query !== $this->query ) {
			return $clauses;
		}
		$clauses['where'] = $this->cursor_where( $clauses['where'], $query );
		return $this->partition_clauses( $clauses, $query );
	}

	/**
	 * Resume a feed after the last visible post without scanning skipped rows.
	 *
	 * @param string    $where Existing SQL conditions.
	 * @param \WP_Query $query Query being executed.
	 * @return string SQL conditions including the feed cursor.
	 */
	private function cursor_where( $where, $query ) {
		$cursor = $query->get( 'friends_feed_cursor' );
		if ( ! is_array( $cursor ) || empty( $cursor['id'] ) || empty( $cursor['date'] ) ) {
			return $where;
		}
		global $wpdb;
		$comparison = 'ASC' === strtoupper( $query->get( 'order' ) ) ? '>' : '<';
		// The comparison operator is selected from fixed strings above.
		return $where . $wpdb->prepare(
			" AND ( {$wpdb->posts}.post_date $comparison %s OR ( {$wpdb->posts}.post_date = %s AND {$wpdb->posts}.ID $comparison %d ) )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$cursor['date'],
			$cursor['date'],
			$cursor['id']
		);
	}

	/**
	 * Turn the status format's membership join into an indexed per-post lookup.
	 *
	 * A single term cannot duplicate posts. The scalar lookup also keeps the
	 * optimizer from starting with the taxonomy and sorting the whole result.
	 * Other taxonomies, compound filters, and modified joins remain unchanged.
	 *
	 * @param array     $clauses Query SQL clauses.
	 * @param \WP_Query $query Query being executed.
	 * @return array Clauses suitable for ordered partition lookups when recognized.
	 */
	private function status_format_clauses( $clauses, $query ) {
		if ( $query->get( 's' ) || empty( $query->tax_query->queries ) ) {
			return $clauses;
		}
		$filters = array_filter( $query->tax_query->queries, 'is_array' );
		$filter = reset( $filters );
		if ( 1 !== count( $filters ) || ! isset( $filter['taxonomy'], $filter['field'], $filter['terms'] )
			|| 'post_format' !== $filter['taxonomy'] || 'slug' !== $filter['field']
			|| array( 'post-format-status' ) !== array_values( (array) $filter['terms'] )
			|| ( isset( $filter['operator'] ) && 'IN' !== strtoupper( $filter['operator'] ) ) ) {
			return $clauses;
		}
		global $wpdb;
		$expected_join = "LEFT JOIN {$wpdb->term_relationships} ON ({$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id)";
		if ( preg_replace( '/\s+/', ' ', trim( $clauses['join'] ) ) !== $expected_join || trim( $clauses['groupby'] ) !== $wpdb->posts . '.ID' ) {
			return $clauses;
		}
		$pattern = '/' . preg_quote( $wpdb->term_relationships, '/' ) . '\.term_taxonomy_id\s+IN\s*\(\s*(\d+)\s*\)/';
		if ( 1 !== preg_match_all( $pattern, $clauses['where'], $matches ) ) {
			return $clauses;
		}
		if ( false !== strpos( str_replace( $matches[0][0], '', $clauses['where'] ), $wpdb->term_relationships ) ) {
			return $clauses;
		}
		$term_id = absint( $matches[1][0] );
		$membership = "( SELECT 1 FROM {$wpdb->term_relationships} AS friends_feed_format WHERE friends_feed_format.object_id = {$wpdb->posts}.ID AND friends_feed_format.term_taxonomy_id = $term_id LIMIT 1 ) = 1";
		$where = str_replace( $matches[0][0], $membership, $clauses['where'] );
		$clauses['where'] = $where;
		$clauses['join'] = '';
		$clauses['groupby'] = '';
		return $clauses;
	}

	/**
	 * Fetch small ordered sets per type/status using WordPress's existing index.
	 *
	 * The type_status_date index can supply date order when both its leading
	 * columns are fixed. Merge only each partition's newest candidates instead
	 * of sorting the complete archive across multiple statuses or post types.
	 *
	 * @param array     $clauses Query SQL clauses.
	 * @param \WP_Query $query The query being executed.
	 * @return array SQL clauses with a bounded candidate join where applicable.
	 */
	private function partition_clauses( $clauses, $query ) {
		$original_clauses = $clauses;
		if ( ! $query->get( 'friends_feed_fast' ) || ! $query->get( 'no_found_rows' ) ) {
			return $original_clauses;
		}
		$clauses = $this->status_format_clauses( $clauses, $query );
		if ( ! empty( $clauses['join'] ) || ! empty( $clauses['groupby'] ) || ! empty( $clauses['distinct'] ) ) {
			return $original_clauses;
		}
		global $wpdb;
		if ( ! in_array( trim( $clauses['fields'] ), array( $wpdb->posts . '.*', $wpdb->posts . '.ID' ), true ) ) {
			return $original_clauses;
		}
		$order = 'ASC' === strtoupper( $query->get( 'order' ) ) ? 'ASC' : 'DESC';
		$expected_order = "{$wpdb->posts}.post_date $order, {$wpdb->posts}.ID $order";
		if ( preg_replace( '/\s+/', ' ', trim( $clauses['orderby'] ) ) !== $expected_order
			|| ! preg_match( '/^LIMIT\s+(\d+)\s*,\s*(\d+)$/i', trim( $clauses['limits'] ), $limits ) ) {
			return $original_clauses;
		}
		$candidate_limit = intval( $limits[1] ) + intval( $limits[2] );
		// Bound materialization; deep numbered pages retain the ordinary query.
		if ( $candidate_limit < 1 || $candidate_limit > 500 ) {
			return $original_clauses;
		}
		$types = array_unique( (array) $query->get( 'post_type' ) );
		$statuses = array_unique( (array) $query->get( 'post_status' ) );
		$partition_count = count( $types ) * count( $statuses );
		if ( $partition_count < 2 || $partition_count > 12 || in_array( 'any', $types, true ) || in_array( 'any', $statuses, true ) ) {
			return $original_clauses;
		}
		$partitions = array();
		foreach ( $types as $type ) {
			foreach ( $statuses as $status ) {
				$conditions = $wpdb->prepare( " AND {$wpdb->posts}.post_type = %s AND {$wpdb->posts}.post_status = %s", $type, $status );
				$alias = 'friends_feed_partition_' . count( $partitions );
				$partitions[] = "SELECT ID, post_date FROM ( SELECT {$wpdb->posts}.ID, {$wpdb->posts}.post_date FROM {$wpdb->posts} WHERE 1=1 {$clauses['where']} $conditions ORDER BY {$clauses['orderby']} LIMIT $candidate_limit ) AS $alias";
			}
		}
		$candidates = 'SELECT ID FROM ( ' . implode( ' UNION ALL ', $partitions ) . " ) AS friends_feed_merge ORDER BY post_date $order, ID $order LIMIT $candidate_limit";
		$clauses['join'] = " INNER JOIN ( $candidates ) AS friends_feed_candidates ON {$wpdb->posts}.ID = friends_feed_candidates.ID";
		// Every existing condition (including the cursor) is applied inside each partition.
		$clauses['where'] = '';
		return $clauses;
	}
}
