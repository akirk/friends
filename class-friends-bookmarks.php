<?php
/**
 * Friends Admin
 *
 * This contains the functions for the admin section.
 *
 * @package Friends
 */

/**
 * This is the class for storing bookmarks with the Friends Plugin.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Bookmarks {
	const CPT = 'friends_bookmark';

	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends;

	/**
	 * Constructor
	 *
	 * @param Friends $friends A reference to the Friends object.
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;
		$this->register_hooks();
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_filter( 'init', array( $this, 'register_custom_post_type' ) );
		add_action( 'post_row_actions', array( $this, 'post_row_actions' ), 10, 2 );
		add_action( 'wp_ajax_friends_save_bookmark', array( $this, 'save_bookmark' ) );
	}

	/**
	 * Registers the custom post type
	 */
	public function register_custom_post_type() {
		$labels = array(
			'name'               => _x( 'Bookmarks', 'taxonomy plural name', 'friends' ),
			'singular_name'      => _x( 'Bookmark', 'taxonomy singular name', 'friends' ),
			'add_new'            => _x( 'Add New', 'bookmark' ),
			'add_new_item'       => __( 'Add New Bookmark', 'friends' ),
			'edit_item'          => __( 'Edit Bookmark', 'friends' ),
			'new_item'           => __( 'New Bookmark', 'friends' ),
			'all_items'          => __( 'All Bookmarks', 'friends' ),
			'view_item'          => __( 'View Bookmark', 'friends' ),
			'search_items'       => __( 'Search Bookmarks', 'friends' ),
			'not_found'          => __( 'No Bookmarks found', 'friends' ),
			'not_found_in_trash' => __( 'No Bookmarks found in the Trash', 'friends' ),
			'parent_item_colon'  => '',
			'menu_name'          => __( 'Bookmarks' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => __( "A cached friend's post", 'friends' ),
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'exclude_from_search' => false,
			'public'              => false,
			'menu_position'       => 6,
			'menu_icon'           => 'dashicons-media-document',
			'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ),
			'has_archive'         => true,
		);
		register_post_type( self::CPT, $args );
	}

	/**
	 * Save the bookmark
	 */
	function save_bookmark() {
		if ( empty( $_GET['url'] ) || ! is_string( $_GET['url'] ) || ! wp_http_validate_url( $_GET['url'] ) ) {
			return new WP_Error( 'invalid-url', __( 'You entererd an invalid URL.', 'friends' ) );
		}
		$item = $this->download( $_GET['url'] );
		if ( ! $item || is_wp_error( $item ) ) {
			return $item;
		}

		if ( ! $item->content && ! $item->title ) {
			return new WP_Error( 'invalid-content', 'No Content' );
		}

		$title   = strip_tags( trim( $item->title ) );
		$content = wp_kses_post( trim( $item->content ) );
		$post_id = null;
		if ( is_null( $post_id ) ) {
			$post_id = $this->friends->feed->url_to_postid( $item->url, get_current_user_id() );
		}

		$post_data = array(
			'post_title'    => $title,
			'post_content'  => $content,
			'post_date_gmt' => date( 'Y-m-d H:i:s' ),
			'post_status'   => 'publish',
			'guid'          => $item->url,
			'post_type'     => self::CPT,
		);

		if ( ! is_null( $post_id ) ) {
			$post_data['ID'] = $post_id;
			wp_update_post( $post_data );
		} else {
			$post_id = wp_insert_post( $post_data, true );
		}

		wp_safe_redirect( str_replace( '&amp;', '&', get_edit_post_link( $post_id ) ) );
		exit;
	}

	/**
	 * Download site config for a URL if it exists
	 *
	 * @param  string $filename The filename to download.
	 * @return string|false The site config.
	 */
	public function download_site_config( $filename ) {
		$response = wp_safe_remote_get(
			'https://raw.githubusercontent.com/fivefilters/ftr-site-config/master/' . $filename,
			array(
				'timeout'     => 20,
				'redirection' => 5,
			)
		);

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Get the parsed site config for a URL
	 *
	 * @param  string $url The URL for which to retrieve the site config.
	 * @return array|false The site config.
	 */
	public function get_site_config( $url ) {
		foreach ( $this->get_site_config_filenames( $url ) as $filename ) {
			$text = $this->download_site_config( $filename );
			if ( ! $text ) {
				continue;
			}

			return $this->parse_site_config( $text );
		}
		return false;
	}

	/**
	 * Prase the site config
	 *
	 * @param  string $text The site config text.
	 * @return array The parsed site config.
	 */
	public function parse_site_config( $text ) {
		$site_config = array();
		$search      = false;
		foreach ( explode( PHP_EOL, $text ) as $line ) {
			if ( false === strpos( $line, ':' ) || '#' === substr( ltrim( $line ), 0, 1 ) ) {
				continue;
			}

			list( $key, $value ) = explode( ':', $line, 2 );
			$key                 = strtolower( trim( $key ) );
			$value               = trim( $value );

			if ( 'find_string' === $key ) {
				$search = $value;
				continue;
			}

			if ( in_array( $key, array( 'title', 'date', 'body' ) ) ) {
				$site_config[ $key ] = $value;
				continue;
			}

			if ( 'replace_string' === $key ) {
				if ( false === $search ) {
					continue;
				}

				if ( ! isset( $site_config['replace'] ) ) {
					$site_config['replace'] = array();
				}

				$site_config['replace'][ $search ] = $value;
				$search                            = false;
				continue;

			}

			if ( 'http_header(' === substr( $key, 0, 12 ) ) {
				if ( ! isset( $site_config['http_header'] ) ) {
					$site_config['http_header'] = array();
				}

				$site_config['http_header'][ substr( $key, 12, -1 ) ] = $value;
				continue;
			}

			if ( 'strip_id_or_class' === $key ) {
				if ( ! isset( $site_config['strip_id_or_class'] ) ) {
					$site_config['strip_id_or_class'] = array();
				}

				$site_config['strip_id_or_class'][] = $value;
				continue;
			}
		}

		return $site_config;
	}

	/**
	 * Get possible site config filenames
	 *
	 * @param  string $url The URL for which to get possible site config filenames.
	 * @return array An array of potential filenames.
	 */
	public function get_site_config_filenames( $url ) {
		$host = parse_url( $url, PHP_URL_HOST );
		if ( 'www.' === substr( $host, 0, 4 ) ) {
			$host = substr( $host, 4 );
		}

		$filenames = array( $host . '.txt' );
		if ( substr_count( $host, '.' ) > 1 ) {
			$filenames[] = substr( $host, strpos( $host, '.' ) ) . '.txt';
		}

		return $filenames;
	}

	/**
	 * Download the article from the URL
	 *
	 * @param  string $url The URL to download.
	 * @return object An item object.
	 */
	public function download( $url ) {
		$args = array(
			'timeout'     => 20,
			'redirection' => 5,
		);

		$site_config = $this->get_site_config( $url );
		if ( isset( $site_config['http_header'] ) ) {
			$args['headers'] = $site_config['http_header'];
		}

		$response = wp_safe_remote_get( $url, $args );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$item      = $this->extract_content( wp_remote_retrieve_body( $response ), $site_config );
		$item->url = $url;
		return $item;

	}

	/**
	 * Extract the content of a URL
	 *
	 * @param  string $html        The HTML from which to extract the content.
	 * @param  array  $site_config The site config.
	 * @return object The parsed content.
	 */
	public function extract_content( $html, $site_config = array() ) {
		if ( ! $site_config ) {
			$site_config = array();
		}

		$articles    = array( 'article', 'blog', 'body', 'content', 'entry', 'hentry', 'main', 'page', 'post', 'text', 'story' );
		$dates       = array( 'date' );
		$site_config = array_merge(
			array(
				'title' => '//h1',
				'body'  => '//*[contains(@class, "' . implode( '")]|//*[contains(@class, "', $articles ) . '")]|*[contains(@id, "' . implode( '")]|//*[contains(@id, "', $articles ) . '")]',
				'date'  => '//*[contains(@class, "' . implode( '")]|//*[contains(@class, "', $dates ) . '")]|*[contains(@id, "' . implode( '")]|//*[contains(@id, "', $dates ) . '")]',
			),
			$site_config
		);

		if ( isset( $site_config['replace'] ) ) {
			foreach ( $site_config['replace'] as $search => $replace ) {
				$html = str_replace( $search, $replace, $html );
			}
		}

		$item = (object) array();
		$dom  = new DOMDocument();

		set_error_handler( '__return_null' );
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		restore_error_handler();

		$xpath = new DOMXpath( $dom );

		if ( isset( $site_config['strip_id_or_class'] ) ) {
			foreach ( $site_config['strip_id_or_class'] as $id_or_class ) {
				$strip = $xpath->query( '//*[contains(@class, "' . esc_attr( $id_or_class ) . '")]|//*[@id="' . esc_attr( $id_or_class ) . '"]' );
				$this->remove_node( $strip );
			}
		}

		$item->title = $xpath->query( $site_config['title'] );
		if ( $item->title ) {
			$item->title = $this->get_inner_html( $item->title );
		} else {
			$item->title = $this->get_inner_html( $xpath->query( '//title' ) );
		}
		$item->content = $xpath->query( $site_config['body'] );
		if ( $item->content ) {
			$item->content = $this->get_inner_html( $item->content );
		} else {
			$item->content = $this->get_inner_html( $xpath->query( '//body' ) );
		}

		$item->date = $xpath->query( $site_config['date'] );
		if ( $item->date ) {
			$item->date = $this->get_inner_html( $item->date );
			if ( $item->date ) {
				$item->date = strtotime( $item->date );
			}
		}
		if ( ! $item->date ) {
			$item->date = time();
		}

		return $item;
	}

	/**
	 * Extract the innerHTML of a node
	 *
	 * @param  object $node The DOM node or a DOMNodeList.
	 * @return string The innerHTML.
	 */
	private function get_inner_html( $node ) {
		$html = '';
		if ( $node instanceof DOMNodeList ) {
			$nodelist = $node;
		} elseif ( isset( $node->childNodes ) ) { // @codingStandardsIgnoreLine
			$nodelist = $node->childNodes; // @codingStandardsIgnoreLine
		} else {
			return false;
		}

		foreach ( $nodelist as $child ) {
			$outer_html = $child->ownerDocument->saveXML( $child ); // @codingStandardsIgnoreLine
			$html      .= preg_replace( "#^<(\\w*)(?:\\s*\\w+=(?:\"[^\"]*\"|\'[^\']*\'))*\\s*>(.*)</\\1>$#s", '\2', $outer_html ) . PHP_EOL;
		}

		return $this->clean_html( $html );
	}

	/**
	 * Remove the node from the DOM.
	 *
	 * @param  object $node The DOM node or a DOMNodeList to remove.
	 */
	private function remove_node( $node ) {
		if ( $node instanceof DOMNodeList ) {
			$nodelist = $node;
		} elseif ( isset( $node->childNodes ) ) { // @codingStandardsIgnoreLine
			$nodelist = $node->childNodes; // @codingStandardsIgnoreLine
		} else {
			return false;
		}

		foreach ( $nodelist as $child ) {
			$child->parentNode->removeChild( $child ); // @codingStandardsIgnoreLine
		}
	}

	/**
	 * Clean the HTML
	 *
	 * @param  string $html The HTML to be cleaned.
	 * @return string       The cleaned HTML.
	 */
	private function clean_html( $html ) {
		$html = preg_replace( '#\n\s*\n\s*#', PHP_EOL . PHP_EOL, trim( $html ) );

		return $html;
	}

	/**
	 * Add actions to the post rows
	 *
	 * @param  array   $actions The existing actions.
	 * @param  WP_Post $post    The post in question.
	 * @return array The extended actions.
	 */
	public function post_row_actions( array $actions, WP_Post $post ) {
		if ( self::CPT !== $post->post_type ) {
			return;
		}
		$actions['visit'] = '<a href="' . esc_url( $post->guid ) . '" target="_blank" rel="noopener noreferrer">' . __( 'Visit' ) . '</a>';

		return $actions;
	}
}
