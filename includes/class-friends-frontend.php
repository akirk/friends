<?php
/**
 * Friends Page
 *
 * This contains the functions for /friends/
 *
 * @package Friends
 */

/**
 * This is the class for the /friends/ part of the Friends Plugin.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Frontend {
	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends;

	/**
	 * Whether we are on the /friends page.
	 *
	 * @var boolean
	 */
	private $is_friends_page = false;

	/**
	 * Whether an author is being displayed
	 *
	 * @var object|false
	 */
	public $author = false;

	/**
	 * Whether a post-format is being displayed
	 *
	 * @var string|false
	 */
	public $post_format = false;

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
		add_filter( 'pre_get_posts', array( $this, 'friend_posts_query' ), 2 );
		add_filter( 'post_type_link', array( $this, 'friend_post_link' ), 10, 2 );
		add_filter( 'friends_header_widget_title', array( $this, 'header_widget_title' ) );
		add_filter( 'get_edit_post_link', array( $this, 'friend_post_edit_link' ) );
		add_filter( 'template_include', array( $this, 'template_override' ) );
		add_filter( 'init', array( $this, 'register_friends_sidebar' ) );
		add_action( 'wp', array( $this, 'remove_top_margin' ) );
		add_action( 'wp_ajax_friends_publish', array( $this, 'ajax_frontend_publish_post' ) );
		add_action( 'wp_ajax_friends-change-post-format', array( $this, 'ajax_change_post_format' ) );
		add_action( 'wp_ajax_friends-load-next-page', array( $this, 'ajax_load_next_page' ) );
		add_action( 'wp_ajax_friends-autocomplete', array( $this, 'ajax_autocomplete' ) );
		add_action( 'wp_untrash_post_status', array( $this, 'untrash_post_status' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 99999 );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Registers the sidebar for the /friends page.
	 */
	public function remove_top_margin() {
		if ( Friends::on_frontend() ) {
			// remove the margin-top on the friends page.
			add_theme_support(
				'admin-bar',
				array(
					'callback' => '__return_false',
				)
			);
		}
	}

	/**
	 * Registers the sidebar for the /friends page.
	 */
	public function register_friends_sidebar() {
		register_sidebar(
			array(
				'name'          => 'Friends Topbar',
				'id'            => 'friends-topbar',
				'before_widget' => '<div class="friends-main-widget">',
				'after_widget'  => '</div>',
				'before_title'  => '<h1>',
				'after_title'   => '</h1>',
			)
		);
		register_sidebar(
			array(
				'name'          => 'Friends Sidebar',
				'id'            => 'friends-sidebar',
				'before_widget' => '<div class="friends-widget">',
				'after_widget'  => '</div>',
				'before_title'  => '<h5>',
				'after_title'   => '</h5>',
			)
		);
	}

	/**
	 * Reference our script for the /friends page
	 */
	public function enqueue_scripts() {
		global $wp_query;

		if ( is_user_logged_in() && Friends::on_frontend() ) {
			wp_enqueue_script( 'friends', plugins_url( 'friends.js', FRIENDS_PLUGIN_FILE ), array( 'common', 'jquery', 'wp-util' ), Friends::VERSION );
			$query_vars = serialize( $this->get_minimal_query_vars( $wp_query->query_vars ) );

			$variables = array(
				'emojis_json'       => plugins_url( 'emojis.json', FRIENDS_PLUGIN_FILE ),
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'text_link_expired' => __( 'The link has expired. A new link has been generated, please click it again.', 'friends' ),
				'text_undo'         => __( 'Undo' ),
				'text_trash_post'   => __( 'Trash this post', 'friends' ),
				'query_vars'        => $query_vars,
				'qv_sign'           => sha1( wp_salt( 'nonce' ) . $query_vars ),
				'current_page'      => get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1,
				'max_page'          => $wp_query->max_num_pages,
			);
			wp_localize_script( 'friends', 'friends', $variables );

			$non_theme_styles = array_flip(
				apply_filters(
					'friends_page_allowed_styles',
					array(
						'admin-bar',
						'customize-preview',
						'wp-block-library',
						'wp-block-library-theme',
						'wp-mediaelement',
					)
				)
			);

			foreach ( wp_styles()->queue as $style ) {
				if ( ! isset( $non_theme_styles[ $style ] ) ) {
					wp_dequeue_style( $style );
				}
			}

			wp_enqueue_style( 'friends', plugins_url( 'friends.css', FRIENDS_PLUGIN_FILE ), array(), Friends::VERSION );
		}
	}

	/**
	 * Add a CSS class to the body
	 *
	 * @param array $classes The existing CSS classes.
	 * @return array The modified CSS classes.
	 */
	public function add_body_class( $classes ) {
		if ( $this->is_friends_page ) {
			$classes[] = 'friends-page';
		}

		return $classes;
	}

	/**
	 * Gets the minimal query variables.
	 *
	 * @param      array $query_vars  The query variables.
	 *
	 * @return     array  The minimal query variables.
	 */
	private function get_minimal_query_vars( $query_vars ) {
		return array_filter( array_intersect_key( $query_vars, array_flip( array( 'p', 'page_id', 'pagename', 'author', 'author__not_in', 'post_type', 'post_status', 'posts_per_page', 'order', 'tax_query' ) ) ) );
	}

	/**
	 * The Ajax function to be called upon posting from /friends
	 */
	public function ajax_frontend_publish_post() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'friends_publish' ) ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'post',
					'post_title'   => $_POST['title'],
					'post_content' => $_POST['content'],
					'post_status'  => $_POST['status'],
				)
			);
			$result  = is_wp_error( $post_id ) ? 'error' : 'success';
			if ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest' ) {
				echo $result;
			} else {
				wp_safe_redirect( $_SERVER['HTTP_REFERER'] );
				exit;
			}
		}
	}

	/**
	 * The Ajax function to change the post format from the Frontend.
	 */
	public function ajax_change_post_format() {
		$post_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$post_format = isset( $_POST['format'] ) ? $_POST['format'] : 'standard';

		check_ajax_referer( "friends-change-post-format_$post_id" );

		if ( ! current_user_can( Friends::REQUIRED_ROLE, $post_id ) ) {
			wp_send_json_error();
		}

		$post_formats = get_post_format_strings();
		if ( ! isset( $post_formats[ $post_format ] ) ) {
			wp_send_json_error();
		}

		if ( ! set_post_format( $post_id, $post_format ) ) {
			wp_send_json_error();
		}

		wp_send_json_success();
	}

	/**
	 * The Ajax function to load more posts for infinite scrolling.
	 */
	public function ajax_load_next_page() {
		$query_vars = stripslashes( $_POST['query_vars'] );
		if ( sha1( wp_salt( 'nonce' ) . $query_vars ) !== $_POST['qv_sign'] ) {
			wp_send_json_error();
		}
		$query_vars = unserialize( $query_vars );
		$query_vars['paged'] = intval( $_POST['page'] ) + 1;

		query_posts( $query_vars );
		ob_start();
		if ( have_posts() ) {
			while ( have_posts() ) {
				the_post();

				Friends::template_loader()->get_template_part(
					'frontend/parts/content',
					get_post_format(),
					array(
						'friends'     => $this->friends,
						'friend_user' => new Friend_User( get_the_author_meta( 'ID' ) ),
						'avatar'      => get_post_meta( get_the_ID(), 'gravatar', true ),
					)
				);
			}
		} else {
			esc_html_e( 'No further posts of your friends could were found.', 'friends' );
		}

		$posts = ob_get_contents();
		ob_end_clean();

		wp_send_json_success( $posts );
	}

	/**
	 * The Ajax function to autocomplete search.
	 */
	function ajax_autocomplete() {
		$q = stripslashes( $_POST['q'] );
		$users = Friend_User_Query::search( '*' . $q . '*' );
		$results = array();
		foreach ( $users->get_results() as $friend ) {
			$result = '<li class="menu-item">';
			$result .= '<a href="' . esc_url( $friend->get_local_friends_page_url() ) . '" class="has-icon-left">';
			$result .= str_ireplace( $q, '<mark>' . $q . '</mark>', $friend->display_name );
			$result .= ' <small>';
			$result .= str_ireplace( $q, '<mark>' . $q . '</mark>', $friend->user_login );
			$result .= '</small></a></li>';
			$results[] = $result;
		}

		wp_send_json_success( implode( PHP_EOL, $results ) );

	}

	/**
	 * Ensure that untrashed friends posts go back to published.
	 *
	 * @param string $new_status      The new status of the post being restored.
	 * @param int    $post_id         The ID of the post being restored.
	 * @param string $previous_status The status of the post at the point where it was trashed.
	 */
	public function untrash_post_status( $new_status, $post_id, $previous_status ) {
		if ( Friends::CPT === get_post_type( $post_id ) ) {
			return $previous_status;
		}
		return $new_status;
	}

	/**
	 * Load the template for /friends
	 *
	 * @param  string $template The original template intended to load.
	 * @return string The new template to be loaded.
	 */
	public function template_override( $template ) {
		if ( ! Friends::on_frontend() ) {
			return $template;
		}

		if ( isset( $_GET['refresh'] ) ) {
			add_filter( 'notify_about_new_friend_post', '__return_false', 999 );
			add_filter(
				'wp_feed_options',
				function( $feed ) {
					$feed->enable_cache( false );
				}
			);
			$this->friends->feed->retrieve_friend_posts( null, true );
		}

		return Friends::template_loader()->get_template_part( 'frontend/index', null, array(), false );
	}

	/**
	 * Modify the Friends page title depending on context, for example add an author name or post format.
	 *
	 * @param      string $title  The original title.
	 *
	 * @return     string  The modified title.
	 */
	function header_widget_title( $title ) {
		$title = '<a href="' . esc_url( site_url( '/friends/' ) ) . '">' . esc_html( $title ) . '</a>';
		if ( $this->author ) {
			$title .= ' &raquo; ' . '<a href="' . esc_url( $this->author->get_local_friends_page_url() ) . '">' . esc_html( $this->author->display_name ) . '</a>';
		}
		if ( $this->post_format ) {
			$post_formats = get_post_format_strings();
			$title .= ' &raquo; ' . $post_formats[ $this->post_format ];
		}
		return $title;
	}

	/**
	 * Output a link, potentially augmented with authication information.
	 *
	 * @param      string      $url          The url.
	 * @param      string      $text             The link text.
	 * @param      array       $html_attributes    HTML attributes.
	 * @param      Friend_User $friend_user  The friend user.
	 */
	function link( $url, $text, array $html_attributes = array(), Friend_User $friend_user = null ) {
		echo $this->get_link( $url, $text, $html_attributes, $friend_user );
	}

	/**
	 * Get a link, potentially augmented with authication information.
	 *
	 * @param      string      $url              The url.
	 * @param      string      $text             The link text.
	 * @param      array       $html_attributes  HTML attributes.
	 * @param      Friend_User $friend_user      The friend user.
	 *
	 * @return     string       The link.
	 */
	function get_link( $url, $text, array $html_attributes = array(), Friend_User $friend_user = null ) {
		if ( is_null( $friend_user ) ) {
			$friend_user = new Friend_User( get_the_author_meta( 'ID' ) );
		}

		if ( $friend_user->is_friend_url( $url ) && $friend_user->is_valid_friend() ) {
			$html_attributes['target'] = '_blank';
			$html_attributes['rel'] = 'noopener noreferrer';
			if ( ! isset( $html_attributes['class'] ) ) {
				$html_attributes['class'] = '';
			}
			$html_attributes['class'] = trim( $html_attributes['class'] . ' friends-auth-link' );
			if ( ! isset( $html_attributes['dashicon_back'] ) ) {
				$html_attributes['dashicon_back'] = 'admin-users';
			}
			$html_attributes['data-token'] = $friend_user->get_friend_auth();
			$html_attributes['data-nonce'] = wp_create_nonce( 'auth-link-' . $url );
			$html_attributes['data-friend'] = $friend_user->user_login;
		}

		$link = '<a href="' . esc_url( $url ) . '"';
		foreach ( $html_attributes as $name => $value ) {
			if ( ! in_array( $name, array( 'title', 'target', 'rel', 'class', 'style', 'data-nonce', 'data-token', 'data-friend' ) ) ) {
				continue;
			}
			$link .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
		$link .= '>';
		if ( isset( $html_attributes['dashicon_front'] ) ) {
			$link .= '<span class="dashicons dashicons-' . esc_attr( $html_attributes['dashicon_front'] ) . '"></span>';
		}
		$link .= esc_html( $text );
		if ( isset( $html_attributes['dashicon_back'] ) ) {
			$link .= '<span class="dashicons dashicons-' . esc_attr( $html_attributes['dashicon_back'] ) . '"></span>';
		}
		$link .= '</a>';

		return $link;
	}

	/**
	 * Don't show the edit link for friend posts.
	 *
	 * @param  string $link    The edit link.
	 * @return string|bool The edit link or false.
	 */
	public function friend_post_edit_link( $link ) {
		global $post;

		if ( $post && Friends::CPT === $post->post_type ) {
			if ( Friends::on_frontend() ) {
				$new_link = false;
			} else {
				$new_link = get_the_guid( $post );
			}
			return apply_filters( 'friend_post_edit_link', $new_link, $link );
		}
		return $link;
	}

	/**
	 * Link friend posts to the remote site.
	 *
	 * @param string  $post_link The post's permalink.
	 * @param WP_Post $post      The post in question.
	 * @reeturn string The overriden post link.
	 */
	public function friend_post_link( $post_link, WP_Post $post ) {
		if ( $post && Friends::CPT === $post->post_type ) {
			return get_the_guid( $post );
		}
		return $post_link;
	}

	/**
	 * Render the Friends OPML
	 */
	protected function render_opml() {
		$feeds = array();
		$users = array();

		$friend_users = new Friend_User_Query( array( 'role__in' => array( 'friend', 'acquaintance', 'friend_request', 'subscription' ) ) );
		foreach ( $friend_users->get_results() as $friend_user ) {
			$role = $friend_user->get_role_name( true, 9 );
			if ( ! isset( $users[ $role ] ) ) {
				$users[ $role ] = array();
			}
			$users[ $role ][] = $friend_user;
		}
		ksort( $users );
		foreach ( $users as $role => $friend_users ) {
			foreach ( $friend_users as $friend_user ) {
				$user_feeds = $friend_user->get_active_feeds();

				$need_local_feed = false;

				foreach ( $user_feeds as $feed ) {
					switch ( $feed->get_mime_type() ) {
						case 'application/atom+xml':
						case 'application/atomxml':
						case 'application/rss+xml':
						case 'application/rssxml':
							break;
						default:
							$need_local_feed = true;
							break 2;
					}
				}

				if ( $need_local_feed ) {
					$user_feeds = array_slice( $user_feeds, 0, 1 );
				}

				$user = array(
					'friend_user' => $friend_user,
					'feeds'       => array(),
				);

				foreach ( $user_feeds as $feed ) {
					$type = 'rss';
					if ( $need_local_feed ) {
						$xml_url = $feed->get_local_url() . '?auth=' . $_GET['auth'];
						$title = $friend_user->display_name;
					} else {
						$xml_url = $feed->get_private_url( YEAR_IN_SECONDS );
						if ( 'application/atom+xml' === $feed->get_mime_type() ) {
							$type = 'atom';
						}
					}
					$user['feeds'][] = array(
						'xml_url'  => $xml_url,
						'html_url' => $feed->get_local_html_url(),
						'title'    => $title,
						'type'     => $type,
					);
				}

				if ( ! empty( $user['feeds'] ) ) {
					if ( ! isset( $feeds[ $role ] ) ) {
						$feeds[ $role ] = array();
					}
					$feeds[ $role ][] = $user;
				}
			}
		}
		Friends::template_loader()->get_template_part(
			'admin/opml',
			null,
			array(
				'feeds' => $feeds,
			)
		);
		exit;
	}

	/**
	 * Modify the main query for the /friends page
	 *
	 * @param  WP_Query $query The main query.
	 * @return WP_Query The modified main query.
	 */
	public function friend_posts_query( $query ) {
		global $wp_query, $wp;

		if ( $wp_query !== $query || $query->is_admin ) {
			return $query;
		}
		// Not available for the general public or friends.
		$viewable = current_user_can( Friends::REQUIRED_ROLE );
		if ( $query->is_feed() ) {
			// Feeds can be viewed through extra authentication.
			if ( $this->friends->access_control->private_rss_is_authenticated() ) {
				$viewable = true;
			} elseif ( isset( $wp_query->query['pagename'] ) ) {
				$pagename_parts = explode( '/', trim( $wp_query->query['pagename'], '/' ) );
				if ( apply_filters( 'friends_friend_feed_viewable', false, $pagename_parts[1] ) ) {
					$viewable = true;
				}
			}
		}

		if ( ! ( Friends::on_frontend() || $query->is_feed() ) || ! $viewable ) {
			return $query;
		}

		// Super Admins cannot view other's friend pages.
		if ( is_multisite() && is_super_admin( get_current_user_id() ) ) {
			return $query;
		}

		$this->is_friends_page = true;
		$query->is_friends_page = true;
		$query->is_singular = false;
		$query->is_single = false;

		$page_id = get_query_var( 'page' );

		$query->set( 'post_type', Friends::CPT );
		if ( current_user_can( Friends::REQUIRED_ROLE ) ) {
			$query->set( 'post_status', array( 'publish', 'private' ) );
		}
		$query->is_page = false;
		$query->is_comment_feed = false;
		$query->set( 'pagename', null );

		$pagename_parts = explode( '/', trim( $wp_query->query['pagename'], '/' ) );
		if ( isset( $pagename_parts[1] ) ) {
			if ( 'opml' === $pagename_parts[1] ) {
				return $this->render_opml();
			}
			$potential_post_format = false;
			if ( 'type' === $pagename_parts[1] && isset( $pagename_parts[2] ) ) {
				$potential_post_format = $pagename_parts[2];
			} else {
				$author = get_user_by( 'login', $pagename_parts[1] );
				if ( false !== $author ) {
					$this->author = new Friend_User( $author );
					$query->set( 'author', $author->ID );
					$query->is_author = true;
					if ( ! $page_id && isset( $pagename_parts[2] ) && 'type' === $pagename_parts[2] && isset( $pagename_parts[3] ) ) {
						$potential_post_format = $pagename_parts[3];
					}
				}
			}

			$tax_query = $this->friends->wp_query_get_post_format_tax_query( $potential_post_format );
			if ( $tax_query ) {
				$this->post_format = $potential_post_format;
				$query->set( 'tax_query', $tax_query );
			}
		}

		if ( $page_id ) {
			$query->set( 'page_id', $page_id );
			$query->is_single = true;
			$query->is_singular = true;
			$wp->set_query_var( 'page', null );
		}

		if ( ! $query->is_singular && ! $query->is_author ) {
			// This is the main friends page.
			$hide_from_friends_page = get_user_option( 'friends_hide_from_friends_page' );
			if ( $hide_from_friends_page ) {
				$query->set( 'author__not_in', $hide_from_friends_page );
			}
		}

		return $query;
	}
}
