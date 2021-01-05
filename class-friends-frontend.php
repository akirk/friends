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
	 * @var string|false
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
		add_filter( 'post_type_link', array( $this, 'friend_post_link' ), 10, 4 );
		add_filter( 'friends_header_widget_title', array( $this, 'header_widget_title' ) );
		add_filter( 'get_edit_post_link', array( $this, 'friend_post_edit_link' ), 10, 2 );
		add_filter( 'template_include', array( $this, 'template_override' ) );
		add_filter( 'init', array( $this, 'register_friends_sidebar' ) );
		add_action( 'wp', array( $this, 'remove_top_margin' ) );
		add_action( 'wp_ajax_friends_publish', array( $this, 'ajax_frontend_publish_post' ) );
		add_action( 'wp_ajax_friends-change-post-format', array( $this, 'ajax_change_post_format' ) );
		add_action( 'wp_untrash_post_status', array( $this, 'untrash_post_status' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Registers the sidebar for the /friends page.
	 */
	public function remove_top_margin() {
		if ( $this->is_friends_frontend() ) {
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
		if ( is_user_logged_in() && $this->is_friends_frontend() ) {
			wp_enqueue_script( 'friends', plugins_url( 'friends.js', __FILE__ ), array( 'common', 'jquery', 'wp-util' ), Friends::VERSION );
			$variables = array(
				'emojis_json'       => plugins_url( 'emojis.json', __FILE__ ),
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'spinner_url'       => admin_url( 'images/wpspin_light.gif' ),
				'text_link_expired' => __( 'The link has expired. A new link has been generated, please click it again.', 'friends' ),
				'text_undo'         => __( 'Undo' ),
				'text_trash_post'   => __( 'Trash this post', 'friends' ),
			);
			wp_localize_script( 'friends', 'friends', $variables );
			wp_enqueue_style( 'friends', plugins_url( 'friends.css', __FILE__ ), array(), Friends::VERSION );
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
		if ( ! $this->is_friends_frontend() || ! current_user_can( Friends::REQUIRED_ROLE ) ) {
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

		return apply_filters( 'friends_template_path', 'friends/posts.php' );
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
	 * @param  int    $post_id The post id.
	 * @return string|bool The edit link or false.
	 */
	public function friend_post_edit_link( $link, $post_id ) {
		global $post;

		if ( $post && Friends::CPT === $post->post_type ) {
			if ( $this->is_friends_page ) {
				return false;
			}
			return get_the_guid( $post );
		}
		return $link;
	}

	/**
	 * Link friend posts to the remote site.
	 *
	 * @param string  $post_link The post's permalink.
	 * @param WP_Post $post      The post in question.
	 * @param bool    $leavename Whether to keep the post name.
	 * @param bool    $sample    Is it a sample permalink.
	 * @reeturn string The overriden post link.
	 */
	public function friend_post_link( $post_link, WP_Post $post, $leavename, $sample ) {
		if ( $post && Friends::CPT === $post->post_type ) {
			return get_the_guid( $post );
		}
		return $post_link;
	}

	/**
	 * Determine whether we are on the /friends/ page or a subpage.
	 *
	 * @return boolean Whether we are on a friends page URL.
	 */
	protected function is_friends_frontend() {
		global $wp_query;

		if ( ! isset( $wp_query ) || ! isset( $wp_query->query['pagename'] ) ) {
			return false;
		}

		if ( isset( $_GET['public'] ) ) {
			return false;
		}

		$pagename_parts = explode( '/', trim( $wp_query->query['pagename'], '/' ) );
		return count( $pagename_parts ) > 0 && 'friends' === $pagename_parts[0];
	}

	/**
	 * Modify the main query for the /friends page
	 *
	 * @param  WP_Query $query The main query.
	 * @return WP_Query The modified main query.
	 */
	public function friend_posts_query( $query ) {
		global $wp_query;
		if ( $wp_query !== $query || ! ( $this->is_friends_frontend() || $query->is_feed() ) ) {
			return $query;
		}

		// Not available for the general public or friends.
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) && ! $this->friends->access_control->private_rss_is_authenticated() ) {
			return $query;
		}

		$this->is_friends_page = true;
		$query->is_friends_page = true;
		$query->is_singular = false;

		$page_id = get_query_var( 'page' );

		$query->set( 'post_type', Friends::CPT );
		$query->set( 'post_status', array( 'publish', 'private' ) );
		$query->is_page = false;
		$query->is_comment_feed = false;
		$query->set( 'pagename', null );

		$post_format = false;
		$page_name = empty( $wp_query->query['pagename'] ) ? '' : $wp_query->query['pagename'];
		$pagename_parts = explode( '/', trim( $wp_query->query['pagename'], '/' ) );
		if ( isset( $pagename_parts[1] ) ) {
			if ( 'opml' === $pagename_parts[1] ) {
				$friends = new Friend_User_Query( array( 'role__in' => array( 'friend', 'acquaintance', 'friend_request', 'subscription' ) ) );

				$feed = $this->friends->feed;

				include apply_filters( 'friends_template_path', 'admin/opml.php' );
				exit;
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
					if ( $page_id ) {
						$query->set( 'page_id', $page_id );
						$query->is_singular = true;
					} elseif ( isset( $pagename_parts[2] ) && 'type' === $pagename_parts[2] && isset( $pagename_parts[3] ) ) {
						$potential_post_format = $pagename_parts[3];
					}
				}
			}

			$tax_query = $this->friends->wp_query_get_post_format_tax_query( $potential_post_format );
			if ( $tax_query ) {
				$this->post_format = $potential_post_format;
				$query->set( 'tax_query', $tax_query );
			}
		} elseif ( $page_id ) {
			$query->set( 'page_id', $page_id );
			$query->is_singular = true;
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
