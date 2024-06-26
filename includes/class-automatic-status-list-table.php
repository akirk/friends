<?php
/**
 * Friends_Automatic_Status_List_Table class
 *
 * @package Friends
 */

namespace Friends;

/**
 * Class used to implement displaying automatic status posts in a list table.
 *
 * @see \WP_Posts_List_Table
 */
class Automatic_Status_List_Table extends \WP_Posts_List_Table {
	/**
	 * Constructor
	 *
	 * @param array $args Array of arguments.
	 */
	public function __construct( $args = array() ) {
		parent::__construct(
			array(
				'plural' => 'posts',
				'screen' => \WP_Screen::get( 'edit' ),
			)
		);
	}

	/**
	 * Prepares the list of items for displaying.
	 */
	public function prepare_items() {
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		global $mode, $avail_post_stati, $wp_query, $per_page;
		$mode = 'excerpt';

		$post_data = array(
			'post_type'   => 'post',
			'post_format' => 'status',
			'post_status' => 'private',
			'post_author' => get_current_user_id(),
		);

		$avail_post_stati = get_available_post_statuses( $post_data['post_type'] );
		if ( ! empty( $_REQUEST['post_status'] ) && in_array( $_REQUEST['post_status'], $avail_post_stati, true ) ) {
			// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$post_data['post_status'] = $_REQUEST['post_status'];
		} elseif ( isset( $_REQUEST['all_posts'] ) ) {
			unset( $post_data['post_status'] );
		}

		wp( $post_data );

		$post_type = $this->screen->post_type;
		$per_page  = $this->get_items_per_page( 'edit_' . $post_type . '_per_page' );

		/** This filter is documented in wp-admin/includes/post.php */
		$per_page = apply_filters( 'edit_posts_per_page', $per_page, $post_type );
		// phpcs:enable
		$this->is_trash = 'trash' === $post_data['post_status'];
	}

	/**
	 * Gets the post status counts.
	 *
	 * @param      string $post_type  The post type.
	 *
	 * @return     object  The counts.
	 */
	protected function get_post_status_counts( $post_type ) {
		global $wpdb;

		$cache_key = 'get_post_status_counts_' . $post_type;
		$counts = wp_cache_get( $cache_key, 'friends' );
		if ( false !== $counts ) {
			return $counts;
		}

		$counts = array();
		$post_status_counts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"SELECT posts.post_status, COUNT(posts.ID) AS count
				FROM {$wpdb->posts} AS posts
				JOIN {$wpdb->term_relationships} AS relationships
				JOIN {$wpdb->term_taxonomy} AS taxonomy
				JOIN {$wpdb->terms} AS terms

				WHERE posts.post_author = %d
				AND posts.post_type = %s
				AND relationships.object_id = posts.ID
				AND relationships.term_taxonomy_id = taxonomy.term_taxonomy_id
				AND taxonomy.taxonomy = 'post_format'

				AND terms.slug = 'post-format-status'

				GROUP BY posts.post_status",
				get_current_user_id(),
				$post_type
			)
		);

		foreach ( $post_status_counts as $row ) {
			$counts[ $row->post_status ] = $row->count;
		}
		$counts = (object) $counts;
		wp_cache_set( $cache_key, $counts, 'friends', HOUR_IN_SECONDS );

		return $counts;
	}

	/**
	 * The no items text.
	 */
	public function no_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if (
			( ! isset( $_REQUEST['post_status'] ) && ! isset( $_REQUEST['all_posts'] ) )
			|| ( isset( $_REQUEST['post_status'] ) && 'draft' === $_REQUEST['post_status'] )
		) {
			esc_html_e( 'No unpublished automatically generated statuses found.', 'friends' );
		} else {
			esc_html_e( 'No automatically generated statuses found.', 'friends' );
		}
		// phpcs:enable
	}

	/**
	 * Helper to create links to edit.php with params.
	 *
	 * @param string[] $args  Associative array of URL parameters for the link.
	 * @param string   $label Link text.
	 * @param string   $class_attribute Optional. Class attribute. Default empty string.
	 * @return string The formatted link string.
	 */
	protected function get_edit_link( $args, $label, $class_attribute = '' ) {
		$args = array_merge(
			array(
				'post_type'   => 'post',
				'post_format' => 'status',
				'post_author' => get_current_user_id(),
			),
			$args
		);
		$url = add_query_arg( $args, self_admin_url( 'admin.php?page=friends-auto-status' ) );
		$url = remove_query_arg( 'post_type', $url );

		$class_html   = '';
		$aria_current = '';

		if ( ! empty( $class_attribute ) ) {
			$class_html = sprintf(
				' class="%s"',
				esc_attr( $class_attribute )
			);

			if ( 'current' === $class_attribute ) {
				$aria_current = ' aria-current="page"';
			}
		}

		return sprintf(
			'<a href="%s"%s%s>%s</a>',
			esc_url( $url ),
			$class_html,
			$aria_current,
			$label
		);
	}

	/**
	 * Handles the title column output.
	 *
	 * @global string $mode List table view mode.
	 *
	 * @param \WP_Post $post The current \WP_Post object.
	 */
	public function column_title( $post ) {
		if ( current_user_can( 'read_post', $post->ID ) ) {
			echo wp_kses(
				get_the_content(),
				array(
					'img' => array( 'src' => array() ),
					'a'   => array( 'href' => array() ),
				)
			);
		}

		get_inline_data( $post );
	}

	/**
	 * Gets the list of views available on this table.
	 *
	 * The format is an associative array:
	 * - `'id' => 'link'`
	 *
	 * @return array
	 */
	protected function get_views() {
		global $locked_post_status, $avail_post_stati;

		$post_type = $this->screen->post_type;

		if ( ! empty( $locked_post_status ) ) {
			return array();
		}

		$status_links = array();
		$num_posts    = $this->get_post_status_counts( $post_type );

		$total_posts  = array_sum( (array) $num_posts );
		$class        = '';

		$current_user_id = get_current_user_id();
		$all_args        = array(
			'post_type' => $post_type,
			'all_posts' => 1,
		);
		$mine            = '';

		// Subtract post types that are not included in the admin all list.
		foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
			if ( isset( $num_posts->$state ) ) {
				$total_posts -= $num_posts->$state;
			}
		}

		if ( empty( $class ) && ( $this->is_base_request() || isset( $_REQUEST['all_posts'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$class = 'current';
		}

		$all_inner_html = sprintf(
			/* translators: %s: Number of posts. */
			_nx( // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'All <span class="count">(%s)</span>',
				'All <span class="count">(%s)</span>',
				$total_posts,
				'posts'
			),
			number_format_i18n( $total_posts )
		);

		$status_links['all'] = $this->get_edit_link( $all_args, $all_inner_html, $class );

		if ( $mine ) {
			$status_links['mine'] = $mine;
		}

		foreach ( get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' ) as $status ) {
			$class = '';

			$status_name = $status->name;

			if ( ! in_array( $status_name, $avail_post_stati, true ) || empty( $num_posts->$status_name ) ) {
				continue;
			}
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if ( isset( $_REQUEST['post_status'] ) && $status_name === $_REQUEST['post_status'] ) {
				$class = 'current';
			} elseif ( ! isset( $_REQUEST['post_status'] ) && ! isset( $_REQUEST['all_posts'] ) && 'draft' === $status_name ) {
				$class = 'current';
			}
			// phpcs:enable

			$status_args = array(
				'post_status' => $status_name,
				'post_type'   => $post_type,
			);

			$status_label = sprintf(
				translate_nooped_plural( $status->label_count, $num_posts->$status_name ),
				number_format_i18n( $num_posts->$status_name )
			);

			$status_links[ $status_name ] = $this->get_edit_link( $status_args, $status_label, $class );
		}

		if ( ! empty( $this->sticky_posts_count ) ) {
			$class = ! empty( $_REQUEST['show_sticky'] ) ? 'current' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$sticky_args = array(
				'post_type'   => $post_type,
				'show_sticky' => 1,
			);

			$sticky_inner_html = sprintf(
				/* translators: %s: Number of posts. */
				_nx( // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'Sticky <span class="count">(%s)</span>',
					'Sticky <span class="count">(%s)</span>',
					$this->sticky_posts_count,
					'posts'
				),
				number_format_i18n( $this->sticky_posts_count )
			);

			$sticky_link = array(
				'sticky' => $this->get_edit_link( $sticky_args, $sticky_inner_html, $class ),
			);

			// Sticky comes after Publish, or if not listed, after All.
			$split        = 1 + array_search( ( isset( $status_links['publish'] ) ? 'publish' : 'all' ), array_keys( $status_links ), true );
			$status_links = array_merge( array_slice( $status_links, 0, $split ), $sticky_link, array_slice( $status_links, $split ) );
		}
		return $status_links;
	}

	/**
	 * Retrieves the list of bulk actions available for this table.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions       = array();
		$post_type_obj = get_post_type_object( $this->screen->post_type );

		if ( current_user_can( $post_type_obj->cap->publish_posts ) ) {
			$actions['publish'] = __( 'Publish' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		}

		if ( current_user_can( $post_type_obj->cap->edit_posts ) ) {
			if ( $this->is_trash ) {
				$actions['untrash'] = __( 'Restore' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			} else {
				if ( apply_filters( 'friends_debug', false ) ) {
					$actions['edit'] = __( 'Edit' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				}
				$actions['publish-private'] = __( 'Publish Privately' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			}
		}

		if ( current_user_can( $post_type_obj->cap->delete_posts ) ) {
			if ( $this->is_trash || ! EMPTY_TRASH_DAYS ) {
				$actions['delete'] = __( 'Delete permanently' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			} else {
				$actions['trash'] = __( 'Move to Trash' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			}
		}

		return $actions;
	}

	/**
	 * Generates the extra table navigation above or below the table
	 *
	 * @param string $which The location of the bulk actions: 'top' or 'bottom'.
	 */
	protected function extra_tablenav( $which ) {
	}
}
