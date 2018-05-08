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
class Friends_Page {
	const NAMESPACE = 'friends/v1';
	/**
	 * Contains a reference to the Friends class.
	 */
	private $friends;

	/**
	 * Whether we are on the /friends page.
	 *
	 * @var boolean
	 */
	private $on_friends_page = false;

	/**
	 * Constructor
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;
		$this->register_hooks();
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_filter( 'pre_get_posts',           array( $this, 'friend_posts_query' ), 2 );
		add_filter( 'post_type_link',          array( $this, 'friend_post_link' ), 10, 4 );
		add_filter( 'get_edit_post_link',      array( $this, 'friend_post_edit_link' ), 10, 2 );
		add_filter( 'template_include',        array( $this, 'template_override' ) );
		add_filter( 'init',                    array( $this, 'register_friends_sidebar' ) );
		add_action( 'wp_ajax_friends_publish', array( $this, 'frontend_publish_post' ) );
		add_action( 'wp_enqueue_scripts',      array( $this, 'enqueue_scripts' ) );
		add_filter( 'body_class',              array( $this, 'add_body_class' ) );
	}

	/**
	 * Registers the sidebar for the /friends page.
	 */
	public function register_friends_sidebar() {
		register_sidebar(
			array(
				'name' => 'Friends Sidebar',
				'id' => 'friends-sidebar',
				'before_widget' => '<div class="friends-widget">',
				'after_widget' => '</div>',
				'before_title' => '<h3>',
				'after_title' => '</h3>',
			)
		);
	}

	/**
	 * Reference our script for the /friends page
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'friends-js', plugin_dir_url( __FILE__ ) . 'friends.js', 'jquery' );
		wp_enqueue_style( 'friends-css', plugin_dir_url( __FILE__ ) . 'friends.css' );
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
					'post_type'         => 'post',
					'post_title'        => $_POST['title'],
					'post_content'      => $_POST['content'],
					'post_status'       => $_POST['status'],
				)
			);
			$result = is_wp_error( $post_id ) ? 'error' : 'success';
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
	 * @param  string $template The original template intended to load.
	 * @return string The new template to be loaded.
	 */
	public function template_override( $template ) {
		global $wp_query;

		if ( isset( $wp_query->query['pagename'] ) && 'friends' === $wp_query->query['pagename'] ) {
			if ( current_user_can( 'edit_posts' ) ) {
				if ( isset( $_GET['refresh'] ) ) {
					add_filter( 'notify_about_new_friend_post', '__return_false', 999 );
					add_filter( 'wp_feed_options', function( $feed ) {
						$feed->enable_cache( false );
					} );
					$this->friends->feed->retrieve_friend_posts( null, true );
				}

				$friends = new WP_User_Query( array( 'role__in' => array( 'friend', 'pending_friend_request' ) ) );

				if ( ! have_posts() ) {
					return __DIR__ . '/templates/friends/no-posts.php';
				}
				return __DIR__ . '/templates/friends/posts.php';
			}

			if ( $wp_query->is_404 ) {
				$wp_query->is_404 = false;
				if ( current_user_can( 'friend' ) ) {
					$user = wp_get_current_user();
					wp_safe_redirect( $user->user_url . '/friends/' );
					exit;
				}

				return __DIR__ . '/templates/friends/logged-out.php';
			}
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

		if ( $post && $this->friends::FRIEND_POST_CACHE === $post->post_type ) {
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
		if ( $this->friends::FRIEND_POST_CACHE === $post->post_type ) {
			return get_the_guid( $post );
		}
		return $post_link;
	}

	/**
	 * Modify the main query for the /friends page
	 *
	 * @param  WP_Query $query The main query.
	 * @return WP_Query The modified main query.
	 */
	public function friend_posts_query( $query ) {
		global $wp_query;
		if ( $wp_query !== $query || 'friends' !== get_query_var( 'pagename' ) ) {
			return $query;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return $query;
		}
		$this->on_friends_page = true;

		$page_id = get_query_var( 'page' );

		$query->set( 'post_status', array( 'publish', 'private' ) );
		$query->set( 'post_type', array( $this->friends::FRIEND_POST_CACHE, 'post' ) );
		$query->is_page = false;
		$query->set( 'page', null );
		$query->set( 'pagename', null );

		if ( $page_id ) {
			$query->set( 'page_id', $page_id );
			$query->is_singular = true;
		} else {
			$query->is_singular = false;
		}

		return $query;
	}
}
