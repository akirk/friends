<?php
/**
 * Plugin name: Friends
 * Plugin author: Alex Kirk
 * Version: 0.1
 *
 * Description: Decentralized Facebook.
 */

class Friends {
	private $authenticated = null;

	public static function init() {
		static::get_instance();
	}

	public static function get_instance() {
		static $instance;
		if ( ! isset( $instance ) ) {
			$self = get_called_class();
			$instance = new $self;
		}
		return $instance;
	}

	public function __construct() {
		$this->add_hooks();
		$this->authenticate();
	}

	public function add_hooks() {
		add_filter( 'pre_get_posts',        array( $this, 'private_feed' ), 1 );
		add_filter( 'private_title_format', array( $this, 'private_title_format' ) );
		add_filter( 'the_content_feed',     array( $this, 'feed_content' ), 90 );
		add_filter( 'the_excerpt_rss',      array( $this, 'feed_content' ), 90 );
		add_filter( 'comment_text_rss',     array( $this, 'feed_content' ), 90 );
		add_filter( 'rss_use_excerpt',      array( $this, 'feed_use_excerpt' ), 90 );
		add_filter( 'user_register',        array( $this, 'generate_feed_secret' ), 90 );
	}

	public function private_title_format() {
		return '%s';
	}

	public function feed_use_excerpt() {
		return false;
	}

	function private_feed( $query ) {
		if ( ! $this->authenticated ) {
			return $query;
		}

	    if ( ! $query->is_admin && $query->is_feed ) {
	        $query->set( 'post_status', array( 'publish', 'private' ) );
	    }

	    return $query;
	}

	public function feed_content( $content ) {
		return get_current_user_id().$content;
	}

	public function authenticate() {
		if ( ! isset( $_GET['friend'] ) ) {
			return true;
		}

		$user_id = get_option( 'friends_feed_auth_' . $_GET['friend'] );
		if ( $user_id ) {
			$this->authenticated = true;
			wp_set_current_user( intval( $user_id ) );
		}

		return true;
	}

	public function generate_feed_secret( $user_id ) {

		$current_secret = get_user_meta( $user_id, 'feed_auth' );
		if ( $current_secret ) {
			delete_option( 'friends_feed_auth_' . $secret );
		}

		$user = get_userdata( $user_id );
		if ( ! $user->has_cap( 'read_private_posts' ) ) {
			return;
		}

		$secret = md5( uniqid( time(), true ) );
		if ( update_user_meta( $user_id, 'feed_auth', $secret, $current_secret ) ) {
			update_option( 'friends_feed_auth_' . $secret, $user_id );
		}
	}

	public static function activate_plugin() {
		$friend = get_role( 'friend' );
		if ( ! $friend ) {
			$friend = add_role( 'friend', 'Friend' );
		}
		$friend->add_cap( 'read_private_posts' );
		$friend->add_cap( 'read' );
		$friend->add_cap( 'level_0' );
	}
}


add_action( 'plugins_loaded', array( 'Friends', 'init' ) );
register_activation_hook( __FILE__, array( 'Friends', 'activate_plugin' ) );
