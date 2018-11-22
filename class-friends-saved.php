<?php
/**
 * Friends Admin
 *
 * This contains the functions for saving articles.
 *
 * @package Friends
 */

/**
 * This is the class for saving articles with the Friends Plugin.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Saved {
	const CPT = 'friends_saved';

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
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 10, 3 );
		add_action( 'edit_form_before_permalink', array( $this, 'edit_form_before_permalink' ), 10, 3 );
		add_action( 'post_row_actions', array( $this, 'post_row_actions' ), 10, 2 );
		add_action( 'wp_ajax_friends_save_article', array( $this, 'ajax_save_article' ) );
	}

	/**
	 * Registers the admin menus
	 */
	public function register_admin_menu() {
		add_submenu_page( 'edit.php?post_type=' . self::CPT, __( 'Save Article', 'friends' ), __( 'Save Article', 'friends' ), 'manage_options', 'friends-save-article', array( $this, 'render_save_article' ) );
		add_action( 'load-friends_saved_page_friends-save-article', array( $this, 'process_admin_save_article' ) );
	}

	/**
	 * Registers the custom post type
	 */
	public function register_custom_post_type() {
		$labels = array(
			'name'               => _x( 'Saved Articles', 'taxonomy plural name', 'friends' ),
			'singular_name'      => _x( 'Saved Article', 'taxonomy singular name', 'friends' ),
			'add_new'            => _x( 'Add New', 'saved article', 'friends' ),
			'add_new_item'       => __( 'Add New Saved Article', 'friends' ),
			'edit_item'          => __( 'Edit Saved Article', 'friends' ),
			'new_item'           => __( 'New Saved Article', 'friends' ),
			'all_items'          => __( 'All Saved Articles', 'friends' ),
			'view_item'          => __( 'View Saved Article', 'friends' ),
			'search_items'       => __( 'Search Saved Articles', 'friends' ),
			'not_found'          => __( 'No Saved Articles found', 'friends' ),
			'not_found_in_trash' => __( 'No Saved Articles found in the Trash', 'friends' ),
			'parent_item_colon'  => '',
			'menu_name'          => _x( 'Saved Articles', 'taxonomy plural name', 'friends' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => _x( 'Saved Article', 'taxonomy singular name', 'friends' ),
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'exclude_from_search' => false,
			'public'              => false,
			'menu_position'       => 6,
			'menu_icon'           => 'dashicons-media-document',
			'supports'            => array( 'title', 'editor', 'author' ),
			'taxonomies'          => array( 'post_tag' ),
			'has_archive'         => true,
		);

		register_post_type( self::CPT, $args );
	}

	/**
	 * Save the saved_article via the saved_articlelet
	 */
	function ajax_save_article() {
		if ( empty( $_GET['url'] ) ) {
			return new WP_Error( 'invalid-url', __( 'You entered an invalid URL.', 'friends' ) );
		}

		$error = $this->save_article( $_GET['url'] );
		wp_safe_redirect( add_query_arg( 'error', $error->get_error_code(), self_admin_url( 'admin.php?page=friends-save-article&url=' . esc_url( $_GET['url'] ) ) ) );
	}

	/**
	 * Save the saved_article
	 */
	function process_admin_save_article() {
		$error = false;

		if ( ! empty( $_POST ) && wp_verify_nonce( $_POST['_wpnonce'], 'save-article' ) ) {
			return $this->save_article( $_POST['url'] );
		}

		if ( isset( $_GET['error'] ) ) {
			switch ( $_GET['error'] ) {
				case 'invalid-url':
					return new WP_Error( $_GET['error'], __( 'You entered an invalid URL.', 'friends' ) );
				case 'invalid-content':
					return new WP_Error( $_GET['error'], __( 'No content was extracted.', 'friends' ) );
				case 'could-not-download':
					return new WP_Error( $_GET['error'], __( 'Could not download the URL.', 'friends' ) );
				default:
					return new WP_Error( $_GET['error'], $_GET['error'] );
			}
		}
		return false;
	}

	/**
	 * Save the saved_article through the UI
	 */
	function render_save_article() {
		$error = $this->process_admin_save_article();
		?>
		<h1><?php esc_html_e( 'Save saved_article', 'friends' ); ?></h1>
		<?php
		if ( is_wp_error( $error ) ) {
			?>
			<div id="message" class="updated error is-dismissible"><p><?php echo esc_html( $error->get_error_message() ); ?></p></div>
			<?php
		}

		if ( ! empty( $_GET['url'] ) ) {
			$url = $_GET['url'];
		}

		include apply_filters( 'friends_template_path', 'admin/save-article.php' );
	}

	/**
	 * Download and save the saved_article as a CPT
	 *
	 * @param  string $url The URL to save.
	 * @return WP_Error    Potentially an error message
	 */
	function save_article( $url ) {
		if ( ! is_string( $url ) || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'invalid-url', __( 'You entered an invalid URL.', 'friends' ) );
		}

		$post_id = $this->friends->feed->url_to_postid( $url, get_current_user_id() );
		if ( is_null( $post_id ) ) {
			$item = $this->download( $url );
			if ( is_wp_error( $item ) ) {
				return $item;
			}

			if ( ! $item->content && ! $item->title ) {
				return new WP_Error( 'invalid-content', __( 'No content was extracted.', 'friends' ) );
			}

			$title   = strip_tags( trim( $item->title ) );
			$content = wp_kses_post( trim( $item->content ) );

			$post_data = array(
				'post_title'    => $title,
				'post_content'  => $content,
				'post_date_gmt' => date( 'Y-m-d H:i:s' ),
				'post_status'   => 'private',
				'guid'          => $item->url,
				'post_type'     => self::CPT,
			);

			$post_id = wp_insert_post( $post_data, true );
		}
		wp_untrash_post( $post_id );
		wp_safe_redirect( self_admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
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

			if ( in_array( $key, array( 'title', 'date', 'body', 'author' ) ) ) {
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

			if ( in_array( $key, array( 'strip', 'strip_id_or_class' ) ) ) {
				if ( ! isset( $site_config[ $key ] ) ) {
					$site_config[ $key ] = array();
				}

				$site_config[ $key ][] = $value;
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
			'headers'     => array(
				'user-agent' => 'Friends Plugin',
			),
		);

		$site_config = $this->get_site_config( $url );
		if ( isset( $site_config['http_header'] ) ) {
			$args['headers'] = array_merge( $args['headers'], $site_config['http_header'] );
		}

		$response = wp_safe_remote_get( $url, $args );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'could-not-download', __( 'Could not download the URL.', 'friends' ) );
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

		if ( isset( $site_config['replace'] ) ) {
			foreach ( $site_config['replace'] as $search => $replace ) {
				$html = str_replace( $search, $replace, $html );
			}
		}

		$item = (object) array(
			'title'   => false,
			'content' => false,
		);

		if ( ! class_exists( 'Readability', false ) ) {
			require_once __DIR__ . '/lib/PressForward-Readability/Readability.php';
		}

		set_error_handler( '__return_null' );
		$readability = new Readability( '<?xml encoding="utf-8" ?>' . $html );
		restore_error_handler();
		$xpath = new DOMXpath( $readability->dom );

		if ( isset( $site_config['strip_id_or_class'] ) ) {
			foreach ( $site_config['strip_id_or_class'] as $id_or_class ) {
				$strip = $xpath->query( '//*[contains(@class, "' . esc_attr( $id_or_class ) . '")]|//*[@id="' . esc_attr( $id_or_class ) . '"]' );
				$this->remove_node( $strip );
			}
		}

		if ( isset( $site_config['strip'] ) ) {
			foreach ( $site_config['strip'] as $xp ) {
				$this->remove_node( $xpath->query( $xp ) );
			}
		}

		if ( isset( $site_config['title'] ) ) {
			$item->title = $xpath->query( $site_config['title'] );
			if ( $item->title ) {
				$item->title = $this->get_inner_html( $item->title );
			}
		}

		if ( isset( $site_config['body'] ) ) {
			$item->content = $xpath->query( $site_config['body'] );
			if ( $item->content ) {
				$item->content = $this->get_inner_html( $item->content );
			}
		}

		if ( ! $item->title || ! $item->content ) {
			$copied_dom = clone $readability->dom;
			$result     = $readability->init();
			if ( $result ) {
				if ( ! $item->title ) {
					$item->title = $readability->getTitle()->textContent;
				}
				if ( ! $item->content ) {
					$item->content = $readability->getContent()->innerHTML;
				}
			} else {
				$xpath = new DOMXpath( $copied_dom );

				if ( ! $item->title ) {
					$item->title = $xpath->query( '(//h1)[1]' );
					if ( $item->title ) {
						$item->title = $this->get_inner_html( $item->title );
					} else {
						$item->title = $xpath->query( '//title' );
						if ( $item->title ) {
							$item->title = $this->get_inner_html( $item->title );
						}
					}
				}
				if ( ! $item->content ) {
					$articles      = array( 'article', 'blog', 'body', 'content', 'entry', 'hentry', 'main', 'page', 'post', 'text', 'story' );
					$item->content = $xpath->query( '(//*[contains(@class, "' . implode( '")]|//*[contains(@class, "', $articles ) . '")]|*[contains(@id, "' . implode( '")]|//*[contains(@id, "', $articles ) . '")])[1]' );
					if ( $item->content ) {
						$item->content = $this->get_inner_html( $item->content );
					} else {
						$item->title = $xpath->query( '//body' );
						if ( $item->title ) {
							$item->title = $this->get_inner_html( $item->title );
						}
					}
				}
			}
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
			$html .= $child->innerHTML; // @codingStandardsIgnoreLine
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
			return $actions;
		}
		$actions['visit'] = '<a href="' . esc_url( $post->guid ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $post->guid ) . '</a>';

		return $actions;
	}

	/**
	 * Show the URL on the saved_articles custom post type
	 *
	 * @param  WP_Post $post The post to be shown.
	 */
	public function edit_form_before_permalink( WP_Post $post ) {
		if ( self::CPT !== $post->post_type ) {
			return;
		}

		?>
		<p>
			<label><?php _e( 'URL' ); ?> <input type="text" name="guid" value="<?php echo esc_url( $post->guid ); ?>" class="regular-text"/></label>
			(Saving URL changes doesn't work yet)
			<a href="<?php echo esc_url( site_url( '/friends/' . $post->ID . '/' ) ); ?>">View on frontend</a>
		</p>
		<?php
	}
}
