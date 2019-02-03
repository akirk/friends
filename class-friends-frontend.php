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
	private $on_friends_page = false;

	/**
	 * Whether an author is being displayed
	 *
	 * @var string|false
	 */
	public $author = false;

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
		add_filter( 'get_edit_post_link', array( $this, 'friend_post_edit_link' ), 10, 2 );
		add_filter( 'template_include', array( $this, 'template_override' ) );
		add_filter( 'init', array( $this, 'register_friends_sidebar' ) );
		add_action( 'wp_ajax_friends_publish', array( $this, 'frontend_publish_post' ) );
		add_action( 'wp_ajax_trash_friends_post', array( $this, 'trash_friends_post' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
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
		if ( is_user_logged_in() ) {
			wp_enqueue_script( 'friends', plugins_url( 'friends.js', __FILE__ ), array( 'jquery' ), Friends::VERSION );
			$variables = array(
				'emojis_json' => plugins_url( 'emojis.json', __FILE__ ),
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'spinner_url' => admin_url( 'images/wpspin_light.gif' ),
				'text_undo'   => __( 'Undo' ),
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
		if ( $this->on_friends_page ) {
			$classes[] = 'friends-page';
		}

		return $classes;
	}

	/**
	 * The Ajax function to be called upon posting from /friends
	 */
	public function frontend_publish_post() {
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
	 * Load the template for /friends
	 *
	 * @param  string $template The original template intended to load.
	 * @return string The new template to be loaded.
	 */
	public function template_override( $template ) {
		if ( ! $this->is_friends_frontend() ) {
			return $template;
		}

		if ( current_user_can( 'edit_posts' ) ) {
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

			if ( ! have_posts() && ! get_query_var( 'author_name' ) ) {
				return apply_filters( 'friends_template_path', 'friends/no-posts.php' );
			}
			return apply_filters( 'friends_template_path', 'friends/posts.php' );
		}

		return $template;
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

		if ( $post && $this->friends->is_cached_post_type( $post->post_type ) ) {
			if ( $this->on_friends_page ) {
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
		if ( $post && $this->friends->is_cached_post_type( $post->post_type ) ) {
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
		if ( $wp_query !== $query || ! $this->is_friends_frontend() ) {
			return $query;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return $query;
		}
		$this->on_friends_page = true;
		$this->friends->reactions->unregister_content_hooks();
		$this->friends->recommendation->unregister_content_hooks();

		$page_id = get_query_var( 'page' );

		// Potentially limit post types to be displayed on the friends page.
		$post_types = get_user_option( 'friends_page_post_types' );
		if ( false === $post_types ) {
			$post_types = $this->friends->get_all_post_types();
		} else {
			$post_types = explode( ',', $post_types );
		}
		$query->set( 'post_type', $post_types );

		$query->set( 'post_status', array( 'publish', 'private' ) );
		$query->is_page = false;
		$query->set( 'pagename', null );

		$pagename_parts = explode( '/', trim( $wp_query->query['pagename'], '/' ) );
		if ( isset( $pagename_parts[1] ) ) {
			$this->author = get_user_by( 'login', $pagename_parts[1] );
			$query->set( 'author_name', $pagename_parts[1] );
			$query->is_singular = false;
			$query->is_author   = true;
		} elseif ( $page_id ) {
			$query->set( 'page_id', $page_id );
			$query->is_singular = true;
		} else {
			// This is the main friends page.
			$hide_from_friends_page = get_user_option( 'friends_hide_from_friends_page' );
			if ( $hide_from_friends_page ) {
				$query->set( 'author__not_in', $hide_from_friends_page );
			}
			$query->is_singular = false;
		}

		return $query;
	}
}
