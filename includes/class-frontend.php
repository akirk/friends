<?php
/**
 * Friends Page
 *
 * This contains the functions for /friends/
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the /friends/ part of the Friends Plugin.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Frontend {
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
	 * Whether a tag is being displayed
	 *
	 * @var string|false
	 */
	public $tag = false;

	/**
	 * Whether a reaciton is being displayed
	 *
	 * @var string|false
	 */
	public $reaction = false;

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
		add_filter( 'wp_loaded', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'init', array( $this, 'register_friends_sidebar' ) );
		add_action( 'init', array( $this, 'add_theme_supports' ) );
		add_action( 'wp_ajax_friends_publish', array( $this, 'ajax_frontend_publish_post' ) );
		add_action( 'wp_ajax_friends-change-post-format', array( $this, 'ajax_change_post_format' ) );
		add_action( 'wp_ajax_friends-load-next-page', array( $this, 'ajax_load_next_page' ) );
		add_action( 'wp_ajax_friends-autocomplete', array( $this, 'ajax_autocomplete' ) );
		add_action( 'wp_ajax_friends-in-reply-to-preview', array( $this, 'ajax_in_reply_to_preview' ) );
		add_action( 'wp_ajax_friends-star', array( $this, 'ajax_star_friend_user' ) );
		add_action( 'wp_ajax_friends-load-comments', array( $this, 'ajax_load_comments' ) );
		add_action( 'wp_ajax_friends-reblog', array( $this, 'wp_ajax_reblog' ) );
		add_action( 'friends_post_footer_first', array( $this, 'reblog_button' ) );
		add_filter( 'friends_reblog', array( get_called_class(), 'reblog' ), 10, 2 );
		add_filter( 'friends_unreblog', array( get_called_class(), 'unreblog' ), 10, 2 );
		add_action( 'wp_untrash_post_status', array( $this, 'untrash_post_status' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_scripts' ), 99999 );
		add_action( 'wp_footer', array( $this, 'dequeue_scripts' ) );
		add_action( 'the_post', array( $this, 'the_post' ), 10, 2 );
		add_action( 'parse_query', array( $this, 'parse_query' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );

		add_filter( 'friends_override_author_name', array( $this, 'override_author_name' ), 10, 3 );
	}

	/**
	 * We're asking WordPress to handle the title for us.
	 */
	public function add_rewrite_rule() {
		add_rewrite_rule(
			'friends/(.*)/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$',
			'index.php?pagename=friends/$matches[1]&feed=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'friends/(.*)/(\d+)/?$',
			'index.php?pagename=friends/$matches[1]&page=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'friends/(.*)',
			'index.php?pagename=friends/$matches[1]',
			'top'
		);
	}

	/**
	 * Run our add_theme_supports if on the frontend.
	 */
	public function add_theme_supports() {
		if ( ! Friends::on_frontend() ) {
			return;
		}

		$this->add_theme_support_title_tag();
		$this->add_theme_support_admin_bar();
	}

	/**
	 * We're asking WordPress to handle the title for us.
	 */
	private function add_theme_support_title_tag() {
		add_theme_support( 'title-tag' );
		add_filter( 'document_title_parts', array( $this, 'modify_page_title' ) );
	}

	/**
	 * Remove the margin-top on the friends page.
	 */
	private function add_theme_support_admin_bar() {
		add_theme_support(
			'admin-bar',
			array(
				'callback' => '__return_false',
			)
		);
	}

	/**
	 * Modify the page title to be output. This function is only invoked on the friends page.
	 *
	 * @param      array $title  The title.
	 *
	 * @return     array  The modified title.
	 */
	public function modify_page_title( $title ) {
		if ( is_single() ) {
			$title['page'] = __( 'Friends', 'friends' );
			$title['site'] = $this->author->display_name;
		} elseif ( $this->author instanceof User ) {
			$title['title'] = __( 'Friends', 'friends' );
			$title['site'] = $this->author->display_name;
		} else {
			$title = array(
				'site' => __( 'Friends', 'friends' ),
			);
		}
		return $title;
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

		if ( Friends::on_frontend() ) {
			add_action( 'customize_register', '__return_true' );
		}
	}

	/**
	 * Reference our script for the /friends page
	 */
	public function enqueue_scripts() {
		global $wp_query;

		if ( is_user_logged_in() && Friends::on_frontend() ) {
			$handle = 'friends';
			$file = 'friends.js';
			$version = Friends::VERSION;
			wp_enqueue_script( $handle, plugins_url( $file, FRIENDS_PLUGIN_FILE ), array( 'common', 'jquery', 'wp-util' ), apply_filters( 'friends_debug_enqueue', $version, $handle, dirname( FRIENDS_PLUGIN_FILE ) . '/' . $file ) );

			$query_vars = serialize( $this->get_minimal_query_vars( $wp_query->query_vars ) );

			$variables = array(
				'emojis_json'        => plugins_url( 'emojis.json', FRIENDS_PLUGIN_FILE ),
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'text_link_expired'  => __( 'The link has expired. A new link has been generated, please click it again.', 'friends' ),
				'text_undo'          => __( 'Undo' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'text_trash_post'    => __( 'Trash this post', 'friends' ),
				'text_del_convers'   => __( 'Do you really want to delete this conversation?', 'friends' ),
				'text_no_more_posts' => __( 'No more posts available.', 'friends' ),
				'query_vars'         => $query_vars,
				'qv_sign'            => sha1( wp_salt( 'nonce' ) . $query_vars ),
				'current_page'       => get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1,
				'max_page'           => $wp_query->max_num_pages,
			);
			wp_localize_script( 'friends', 'friends', $variables );

			$handle = 'friends';
			$file = 'friends.css';
			$version = Friends::VERSION;
			wp_enqueue_style( $handle, plugins_url( $file, FRIENDS_PLUGIN_FILE ), array(), apply_filters( 'friends_debug_enqueue', $version, $handle, dirname( FRIENDS_PLUGIN_FILE ) . '/' . $file ) );
		}
	}

	public function dequeue_scripts() {
		if ( is_user_logged_in() && Friends::on_frontend() ) {
			// Dequeue theme styles so taht they don't interact with the Friends frontend.
			$wp_styles = wp_styles();
			foreach ( $wp_styles->queue as $style ) {
				$src = $wp_styles->registered[ $style ]->src;
				if ( 'global-styles' === $style || false !== strpos( $src, '/themes/' ) ) {
					wp_dequeue_style( $style );
				}
			}
		}
	}

	public function the_post( $post, $query ) {
		Subscription::set_authordata_by_query( $query );
	}

	public function parse_query( $query ) {
		Subscription::set_authordata_by_query( $query );
	}

	/**
	 * Add a CSS class to the body
	 *
	 * @param array $classes The existing CSS classes.
	 * @return array The modified CSS classes.
	 */
	public function add_body_class( $classes ) {
		if ( $this->friends->on_frontend() ) {
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


	public function wp_ajax_reblog() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_send_json_error( 'error' );
		}

		$post = get_post( $_POST['post_id'] );
		if ( ! $post || ! Friends::check_url( $post->guid ) ) {
			wp_send_json_error( 'unknown-post', array( 'guid' => $post->guid ) );
		}

		/**
		 * Reblogs a post
		 *
		 * @param int|null $reblog_post_id The post ID of the reblogged post. Default null.
		 * @param WP_Post  $post           The post object.
		 */
		$reblog_post_id = apply_filters( 'friends_reblog', null, $post );
		if ( ! $reblog_post_id || is_wp_error( $reblog_post_id ) ) {
			wp_send_json_error( 'error' );
		}

		wp_send_json_success(
			array(
				'post_id' => $reblog_post_id,
			)
		);
	}

	public function reblog_button() {
		$button_label = apply_filters( 'friends_reblog_button_label', _x( 'Reblog', 'button', 'friends' ) );

		Friends::template_loader()->get_template_part(
			'frontend/parts/reblog-button',
			null,
			array(
				'button-label' => $button_label,
			)
		);
	}

	public static function reblog( $ret, $post ) {
		if ( ! $post ) {
			return $ret;
		}
		$post = get_post( $post );
		if ( ! $post ) {
			return $ret;
		}

		$author = get_post_meta( $post->ID, 'author', true );
		if ( ! $author ) {
			$friend = User::get_post_author( $post );
			$author = $friend->display_name;
		}

		$reblog  = '<!-- wp:paragraph -->' . PHP_EOL . '<p>';
		$reblog .= sprintf(
			// translators: %s is a link.
			__( 'Reblog via %s', 'friends' ),
			'<a href="' . esc_url( $post->guid ) . '">' . esc_html( $author ) . '</a>'
		);

		$reblog .= PHP_EOL . '</p>' . PHP_EOL . '<!-- /wp:paragraph -->' . PHP_EOL;
		$new_post = array(
			'post_title'   => $post->title,
			'post_author'  => get_current_user_id(),
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_content' => $reblog . $post->post_content,
		);
		$new_post_id = wp_insert_post( $new_post );

		set_post_format( $new_post_id, get_post_format( $post ) );
		update_post_meta( $new_post_id, 'reblog', $post->guid );
		update_post_meta( $new_post_id, 'reblog_of', $post->ID );
		update_post_meta( $post->ID, 'reblogged', $new_post_id );
		update_post_meta( $post->ID, 'reblogged_by', get_current_user_id() );

		return $new_post_id;
	}

	public static function unreblog( $ret, $post ) {
		if ( ! $post ) {
			return $ret;
		}
		$post = get_post( $post );
		if ( ! $post ) {
			return $ret;
		}

		$reblogged = get_post_meta( $post->ID, 'reblogged', true );
		if ( ! $reblogged ) {
			return $ret;
		}

		wp_trash_post( $reblogged );

		return $reblogged;
	}

	/**
	 * The Ajax function to be called upon posting from /friends
	 */
	public function ajax_frontend_publish_post() {
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'friends_publish' ) ) {
			return false;
		}
		$p = array(
			'post_type'    => 'post',
			'post_title'   => isset( $_POST['title'] ) ? $_POST['title'] : '',
			'post_content' => isset( $_POST['content'] ) ? $_POST['content'] : '',
			'post_status'  => isset( $_POST['status'] ) ? $_POST['status'] : '',
			'post_format'  => isset( $_POST['format'] ) ? $_POST['format'] : '',
		);

		if ( empty( $p['post_status'] ) ) {
			$p['post_status'] = 'publish';
		}
		$result = 'empty';

		if ( ! empty( $_POST['in_reply_to'] ) ) {
			$p['post_meta_input'] = array(
				'activitypub_in_reply_to' => $_POST['in_reply_to'],
			);
		}

		if ( ! empty( $p['post_content'] ) || ! empty( $p['post_title'] ) ) {
			$post_id = wp_insert_post( $p );
			if ( ! empty( $p['post_format'] ) ) {
				set_post_format( $post_id, $p['post_format'] );
			}
			$result = is_wp_error( $post_id ) ? 'error' : 'success';
		}
		if ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest' ) {
			echo esc_html( $result );
			exit;
		} else {
			wp_safe_redirect( remove_query_arg( 'in_reply_to', add_query_arg( 'result', $result, $_SERVER['HTTP_REFERER'] ) ) );
			exit;
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
			exit;
		}

		$post_formats = get_post_format_strings();
		if ( ! isset( $post_formats[ $post_format ] ) ) {
			wp_send_json_error();
			exit;
		}

		if ( ! set_post_format( $post_id, $post_format ) ) {
			wp_send_json_error();
			exit;
		}

		wp_send_json_success();
	}

	/**
	 * Calculates an estimating read time.
	 *
	 * @param      string $original_text  The original text.
	 *
	 * @return     float  The read time in seconds.
	 */
	private static function calculate_read_time( $original_text ) {
		// from wp_trim_words().
		$text = wp_strip_all_tags( $original_text );

		/*
		 * translators: If your word count is based on single characters (e.g. East Asian characters),
		 * enter 'characters_excluding_spaces' or 'characters_including_spaces'. Otherwise, enter 'words'.
		 * Do not translate into your own language.
		 */
		if ( strpos( _x( 'words', 'Word count type. Do not translate!' ), 'characters' ) === 0 && preg_match( '/^utf\-?8$/i', get_option( 'blog_charset' ) ) ) { // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			$text = trim( preg_replace( "/[\n\r\t ]+/", ' ', $text ), ' ' );
			preg_match_all( '/./u', $text, $words_array );
			$words_array = array_shift( $words_array );
			$words_per_minute = 500;
		} else {
			$words_array = preg_split( "/[\n\r\t ]+/", $text, -1, PREG_SPLIT_NO_EMPTY );
			$words_per_minute = 200;
		}

		$additional_time = 0;
		$figures = substr_count( strtolower( $original_text ), '<figure' );
		for ( $i = 0; $i < $figures; $i++ ) {
			if ( $i < 10 ) {
				$additional_time += 12 - $i;
			} else {
				$additional_time += 3;
			}
		}

		return count( $words_array ) / $words_per_minute * 60 + $additional_time;
	}

	/**
	 * Handles the post loop on the Friends page.
	 */
	public static function have_posts() {
		$friends = Friends::get_instance();
		while ( have_posts() ) {
			global $post;
			the_post();
			$args = array(
				'friends' => $friends,
				'avatar'  => get_post_meta( get_the_ID(), 'gravatar', true ),
			);

			$args['friend_user'] = User::get_post_author( $post );

			$read_time = self::calculate_read_time( get_the_content() );
			if ( $read_time >= 60 ) {
				$mins = ceil( $read_time / MINUTE_IN_SECONDS );
				/* translators: Time difference between two dates, in minutes (min=minute). %s: Number of minutes. */
				$args['read_time'] = sprintf( _n( '%s min', '%s mins', $mins ), $mins ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			} elseif ( $read_time > 20 ) {
				/* translators: Time difference between two dates, in minutes (min=minute). %s: Number of minutes. */
				$args['read_time'] = _x( '< 1 min', 'reading time', 'friends' );
			}

			Friends::template_loader()->get_template_part(
				'frontend/parts/content',
				get_post_format(),
				$args
			);
		}
	}

	/**
	 * The Ajax function to load more posts for infinite scrolling.
	 */
	public function ajax_load_next_page() {
		$query_vars = wp_unslash( $_POST['query_vars'] );
		if ( sha1( wp_salt( 'nonce' ) . $query_vars ) !== $_POST['qv_sign'] ) {
			wp_send_json_error();
			exit;
		}
		$query_vars = unserialize( $query_vars );
		$query_vars['paged'] = intval( $_POST['page'] ) + 1;

		query_posts( $query_vars );
		ob_start();
		if ( have_posts() ) {
			self::have_posts();
		} else {
			esc_html_e( 'No further posts of your friends could were found.', 'friends' );
		}

		$posts = ob_get_contents();
		ob_end_clean();

		wp_send_json_success( $posts );
	}

	/**
	 * Get metadata for in_reply_to_preview.
	 *
	 * @param      string $url    The url.
	 *
	 * @return    array|WP_Error  The in reply to metadata.
	 */
	public function get_in_reply_to_metadata( $url ) {
		$meta = apply_filters( 'friends_get_activitypub_metadata', array(), $url );
		if ( is_wp_error( $meta ) ) {
			return $meta;
		}

		if ( ! $meta || ! isset( $meta['attributedTo'] ) ) {
			return new \WP_Error( 'no-activitypub', 'No ActivityPub metadata found.' );
		}

		$html = 'URL: ' . make_clickable( $meta['id'] );
		$html .= '<blockquote>' . force_balance_tags( wp_kses_post( $meta['content'] ) ) . '</blockquote>';

		$webfinger = apply_filters( 'friends_get_activitypub_metadata', array(), $meta['attributedTo'] );
		$mention = '';
		if ( $webfinger && ! is_wp_error( $webfinger ) ) {
			$mention = '@' . $webfinger['preferredUsername'] . '@' . parse_url( $url, PHP_URL_HOST );
		}

		return array(
			'url'     => $url,
			'html'    => $html,
			'author'  => $meta['attributedTo'],
			'mention' => $mention,
		);
	}

	/**
	 * The Ajax function to fill the in-reply-to-preview.
	 */
	public function ajax_in_reply_to_preview() {
		$url = wp_unslash( $_POST['url'] );

		if ( ! wp_parse_url( $url ) ) {
			wp_send_json_error();
			exit;
		}

		$meta = $this->get_in_reply_to_metadata( $_POST['url'] );

		if ( is_wp_error( $meta ) ) {
			wp_send_json_error( $meta->get_error_message() );
			exit;
		}
		wp_send_json_success( $meta );
	}
	/**
	 * The Ajax function to autocomplete search.
	 */
	public function ajax_autocomplete() {
		$q = wp_unslash( $_POST['q'] );
		$users = User_Query::search( '*' . $q . '*' );
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
	 * The Ajax function to load comments.
	 */
	public function ajax_load_comments() {
		if ( ! isset( $_POST['post_id'] ) || ! intval( $_POST['post_id'] ) ) {
			wp_send_json_error();
			exit;
		}

		$post_id = intval( $_POST['post_id'] );
		check_ajax_referer( "comments-$post_id" );

		$comments = get_comments(
			array(
				'post_id' => $post_id,
			)
		);

		$author_id = get_post_field( 'post_author', $post_id );
		$friend_user = new User( $author_id );

		$comments_url = get_post_meta( $post_id, Feed::COMMENTS_FEED_META, true );
		if ( ! $comments_url && empty( $comments ) ) {
			wp_send_json_error( __( 'No comments feed available.', 'friends' ) );
			exit;
		}

		if ( $friend_user->is_friend_url( $comments_url ) && friends::has_required_privileges() || wp_doing_cron() ) {
			$comments_url = apply_filters( 'friends_friend_private_feed_url', $comments_url, $friend_user );
			$comments_url = $this->friends->access_control->append_auth( $comments_url, $friend_user, 300 );
		}

		$feed_comments = $this->friends->feed->preview( 'simplepie', $comments_url );
		if ( empty( $comments ) && ( is_wp_error( $feed_comments ) || ! is_array( $feed_comments ) ) ) {
			wp_send_json_error( '<small>' . __( 'Unfortunately, comments were not available via RSS.', 'friends' ) . '</small>' );
			exit;
		} elseif ( is_wp_error( $feed_comments ) ) {
			$feed_comments = array();
		}

		$template_loader = Friends::template_loader();
		ob_start();
		?>
		<h5><?php esc_html_e( 'Comments' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></h5>
		<?php
		foreach ( $comments as $comment ) {
			$template_loader->get_template_part(
				'frontend/parts/comment',
				null,
				array(
					'author'       => $comment->comment_author,
					'date'         => $comment->comment_date,
					'permalink'    => $comment->guid . '#comment-' . $comment->comment_ID,
					'post_content' => $comment->comment_content,
				)
			);
		}
		foreach ( $feed_comments as $comment ) {
			$template_loader->get_template_part(
				'frontend/parts/comment',
				null,
				array(
					'author'       => $comment->author,
					'date'         => $comment->date,
					'permalink'    => $comment->permalink,
					'post_content' => $comment->post_content,
				)
			);

		}
		?>
		<p>
		<a href="<?php echo esc_url( get_comments_link( $post_id ) ); ?>"><?php esc_html_e( 'Leave a Comment' );  /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></a>
		</p>
		<?php
		$content = ob_get_contents();
		ob_end_clean();

		wp_send_json_success( $content );
	}

	public function ajax_star_friend_user() {
		if ( ! isset( $_POST['friend_id'] ) || ! $_POST['friend_id'] ) {
			wp_send_json_error();
			exit;
		}

		$friend_id = wp_unslash( $_POST['friend_id'] );
		check_ajax_referer( "star-$friend_id" );

		$friend_user = User::get_by_username( $friend_id );

		$starred = boolval( $_POST['starred'] );
		$friend_user->set_starred( $starred );

		wp_send_json_success(
			array(
				'starred' => $friend_user->get_starred(),
			)
		);
	}

	/**
	 * Ensure that untrashed friends posts go back to published.
	 *
	 * @param string $new_status      The new status of the post being restored.
	 * @param int    $post_id         The ID of the post being restored.
	 * @param string $previous_status The status of the post at the point where it was trashed.
	 */
	public function untrash_post_status( $new_status, $post_id, $previous_status ) {
		if ( ! in_array( get_post_type( $post_id ), apply_filters( 'friends_frontend_post_types', array() ), true ) ) {
			return $new_status;
		}
		return 'publish';
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
				function ( $feed ) {
					$feed->enable_cache( false );
				}
			);
			$this->friends->feed->retrieve_friend_posts( true );
		}

		global $args;
		$args = array(
			'friends'               => $this->friends,
			'friend_user'           => $this->author,
			'frontend_default_view' => get_option( 'friends_frontend_default_view', 'expanded' ),
			'blocks-everywhere'     => get_user_option( 'friends_blocks_everywhere' ),
		);

		if ( isset( $_GET['in_reply_to'] ) && wp_parse_url( $_GET['in_reply_to'] ) ) {
			$args['in_reply_to'] = $args['friends']->frontend->get_in_reply_to_metadata( $_GET['in_reply_to'] );
		}

		return Friends::template_loader()->get_template_part( 'frontend/index', null, $args, false );
	}

	/**
	 * Modify the Friends page title depending on context, for example add an author name or post format.
	 *
	 * @param      string $title  The original title.
	 *
	 * @return     string  The modified title.
	 */
	public function header_widget_title( $title ) {
		$title = '<a href="' . esc_url( home_url( '/friends/' ) ) . '">' . esc_html( $title ) . '</a>';
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
	 * @param      string $url          The url.
	 * @param      string $text             The link text.
	 * @param      array  $html_attributes    HTML attributes.
	 * @param      User   $friend_user  The friend user.
	 */
	public function link( $url, $text, array $html_attributes = array(), User $friend_user = null ) {
		echo wp_kses(
			self::get_link( $url, $text, $html_attributes, $friend_user ),
			array(
				'a'    => array(
					'href'        => array(),
					'title'       => array(),
					'target'      => array(),
					'rel'         => array(),
					'class'       => array(),
					'style'       => array(),
					'data-nonce'  => array(),
					'data-cnonce' => array(),
					'data-token'  => array(),
					'data-friend' => array(),
					'data-id'     => array(),
					'data-url'    => array(),
				),
				'span' => array( 'class' => array() ),
			)
		);
	}

	/**
	 * Get a link, potentially augmented with authication information.
	 *
	 * @param      string $url              The url.
	 * @param      string $text             The link text.
	 * @param      array  $html_attributes  HTML attributes.
	 * @param      User   $friend_user      The friend user.
	 *
	 * @return     string       The link.
	 */
	public static function get_link( $url, $text, array $html_attributes = array(), User $friend_user = null ) {
		if ( is_null( $friend_user ) ) {
			$friend_user = new User( get_the_author_meta( 'ID' ) );
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
			if ( ! in_array( $name, array( 'title', 'target', 'rel', 'class', 'style', 'data-nonce', 'data-cnonce', 'data-token', 'data-friend', 'data-id', 'data-url' ) ) ) {
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

		if ( $post && in_array( $post->post_type, apply_filters( 'friends_frontend_post_types', array() ), true ) ) {
			if ( Friends::on_frontend() ) {
				$new_link = false;
			} else {
				$new_link = get_the_guid( $post );
			}
			/**
			 * Allow overriding the link for editing friend posts.
			 *
			 * By default on the Frontend, a post cannot be edited because $new_link is false.
			 *
			 * Example:
			 *
			 * ```php
			 * add_filter( 'friend_post_edit_link', function( $link, $original_link ) {
			 *     if ( ! $link ) {
			 *          $link = $original_link; // always allow editing.
			 *      }
			 *      return $link;
			 * }, 10, 2 );
			 * ```
			 */
			return apply_filters( 'friend_post_edit_link', $new_link, $link );
		}
		return $link;
	}

	/**
	 * Link friend posts to the remote site.
	 *
	 * @param string   $post_link The post's permalink.
	 * @param \WP_Post $post      The post in question.
	 * @reeturn string The overriden post link.
	 */
	public function friend_post_link( $post_link, \WP_Post $post ) {
		if ( $post && in_array( $post->post_type, apply_filters( 'friends_frontend_post_types', array() ), true ) ) {
			return get_the_guid( $post );
		}
		return $post_link;
	}

	/**
	 * Potentially override the post author name with metadata.
	 *
	 * @param      string $overridden_author_name  The already overridden author name.
	 * @param      string $author_name  The author name.
	 * @param      int    $post_id      The post id.
	 *
	 * @return     string  The modified author name.
	 */
	public function override_author_name( $overridden_author_name, $author_name, $post_id ) {
		if ( $overridden_author_name && $overridden_author_name !== $author_name ) {
			return $overridden_author_name;
		}
		$override_author_name = get_post_meta( $post_id, 'author', true );
		if ( $override_author_name ) {
			return $override_author_name;
		}
		return $author_name;
	}

	/**
	 * Render the Friends OPML
	 *
	 * @param      bool $only_public  Only public feed URLs.
	 */
	protected function render_opml( $only_public = false ) {
		$user = wp_get_current_user();

		// translators: %s is a name.
		$title = sprintf( __( "%s' Subscriptions", 'friends' ), $user->display_name );
		$filename = 'friends-';
		if ( ! $only_public ) {
			$title = __( 'My Friends', 'friends' );
			$filename .= 'private-';
		}
		$filename .= $user->user_login . '.opml';

		$feeds = array();
		$users = array();

		$friend_users = new User_Query( array( 'role__in' => array( 'friend', 'acquaintance', 'friend_request', 'subscription' ) ) );
		foreach ( $friend_users->get_results() as $friend_user ) {
			$role = $friend_user->get_role_name( true, 9 );
			if ( $only_public ) {
				$role = 'Feeds';
			}
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

				if ( $need_local_feed && ! $only_public ) {
					$user_feeds = array_slice( $user_feeds, 0, 1 );
				}

				$user = array(
					'friend_user' => $friend_user,
					'feeds'       => array(),
				);
				$html_url = $friend_user->user_url;

				foreach ( $user_feeds as $feed ) {
					$type = 'rss';
					$feed_title = $friend_user->display_name;

					if ( ! $only_public ) {
						$html_url = $feed->get_local_html_url();
					}

					if ( $need_local_feed ) {
						if ( $only_public ) {
							// Cannot create a public URL.
							continue;
						}
						$xml_url = $feed->get_local_url() . '?auth=' . $_GET['auth'];
					} else {
						if ( $only_public ) {
							$xml_url = $feed->get_url();
						} else {
							$xml_url = $feed->get_private_url( YEAR_IN_SECONDS );
						}
						if ( 'application/atom+xml' === $feed->get_mime_type() ) {
							$type = 'atom';
						}
					}
					$user['feeds'][] = array(
						'xml_url'  => $xml_url,
						'html_url' => $html_url,
						'title'    => $feed_title,
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
				'title'    => $title,
				'feeds'    => $feeds,
				'filename' => $filename,
			)
		);
		exit;
	}

	private function has_required_priviledges() {
		if ( Friends::is_main_user() ) {
			return true;
		}

		if ( is_multisite() ) {
			if ( is_user_member_of_blog( get_current_user_id(), get_current_blog_id() ) ) {
				return true;
			}

			if ( is_super_admin( get_current_user_id() ) ) {
				// Super admins would count as administrators.
				return false;
			}
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Modify the main query for the /friends page
	 *
	 * @param  \WP_Query $query The main query.
	 * @return \WP_Query The modified main query.
	 */
	public function friend_posts_query( $query ) {
		global $wp_query, $wp, $authordata;
		if ( $wp_query !== $query || $query->is_admin() || $query->is_home() ) {
			return $query;
		}

		$pagename = '';
		if ( isset( $wp_query->query['pagename'] ) ) {
			$pagename = $wp_query->query['pagename'];
		} elseif ( isset( $wp_query->query['name'] ) ) {
			$pagename = $wp_query->query['name'];
		}

		$pagename_parts = explode( '/', trim( $pagename, '/' ) );
		$is_friends = array_shift( $pagename_parts );
		if ( 'friends' !== $is_friends ) {
			return $query;
		}

		// Not available for the general public or friends.
		$viewable = $this->has_required_priviledges() && Friends::on_frontend();
		if ( $query->is_feed() ) {
			// Feeds can be viewed through extra authentication.
			if ( $this->friends->access_control->private_rss_is_authenticated() ) {
				$viewable = true;
			} elseif ( isset( $wp_query->query['pagename'] ) ) {
				if ( apply_filters( 'friends_friend_feed_viewable', false, isset( $pagename_parts[0] ) ? $pagename_parts[0] : false ) ) {
					$viewable = true;
				}
			}
		}

		if ( ! $viewable && isset( $wp_query->query['pagename'] ) ) {
			if ( apply_filters( 'friends_friend_posts_query_viewable', false, isset( $pagename_parts[0] ) ? $pagename_parts[0] : false ) ) {
				$viewable = true;
			}
		}

		$page_id = get_query_var( 'page' );

		if ( isset( $_GET['share'] ) ) {
			$share_hash = hash( 'crc32b', apply_filters( 'friends_share_salt', wp_salt( 'nonce' ), $page_id ) . $page_id );
			if ( $_GET['share'] === $share_hash ) {
				$viewable = true;
			}
		}

		if ( ! $viewable ) {
			if ( $query->is_feed() ) {
				status_header( 404 );
				$query->set_404();
			} elseif ( ! Friends::on_frontend() ) {
				if ( count( $pagename_parts ) > 0 ) {
					wp_safe_redirect( home_url( '/friends/' ) );
					exit;
				}
			} elseif ( ! Friends::is_main_user() ) {
				wp_die( __( 'You are not allowed to view this page.', 'friends' ) );
			}

			return $query;
		}

		// Don't let the Post Kinds plugin interfere with this query.
		remove_action( 'pre_get_posts', array( 'Kind_Taxonomy', 'kind_firehose_query' ), 99 );

		switch_to_locale( get_user_locale() );

		$tax_query = array();
		$post_format = null;

		while ( $pagename_parts ) {
			$pagename_part = $pagename_parts[0];
			$current_part = array_shift( $pagename_parts );
			if ( 'reaction' === substr( $current_part, 0, 8 ) && strlen( $current_part ) > 8 ) {
				$reaction = Reactions::validate_emoji( substr( $current_part, 8 ) );
				if ( ! $reaction ) {
					continue;
				}
				$this->reaction = $reaction;
				if ( ! empty( $tax_query ) ) {
					$tax_query['relation'] = 'AND';
				}

				$tax_query[] = array(
					'taxonomy' => 'friend-reaction-' . get_current_user_id(),
					'field'    => 'slug',
					'terms'    => array( substr( $current_part, 8 ) ),
				);
				continue;
			}

			switch ( $current_part ) {
				case 'opml':
					return $this->render_opml( isset( $_REQUEST['public'] ) );

				case 'type':
					$post_format = array_shift( $pagename_parts );
					$tax_query = $this->friends->wp_query_get_post_format_tax_query( $tax_query, explode( ',', $post_format ) );
					if ( $tax_query ) {
						$this->post_format = $post_format;
					}
					break;

				case 'tag':
					$this->tag = array_shift( $pagename_parts );
					$tax_query = array(
						array(
							'taxonomy' => 'post_tag',
							'field'    => 'slug',
							'terms'    => array( $this->tag ),
						),
					);
					break;

				default: // Maybe an author.
					$author = User::get_by_username( $current_part );
					if ( false === $author || is_wp_error( $author ) ) {
						if ( $query->is_feed() ) {
							status_header( 404 );
							$query->set_404();
							return $query;
						}
						wp_safe_redirect( home_url( '/friends/' ) );
						exit;
					}

					$this->author = $author;
					break;
			}
		}

		$this->is_friends_page = true;
		$query->is_friends_page = true;
		$query->is_singular = false;
		$query->is_single = false;
		$query->queried_object = null;
		$query->queried_object_id = null;
		$post_types = apply_filters( 'friends_frontend_post_types', array() );

		if ( 'status' === $post_format ) {
			// Show your own posts on the status feed.
			$post_types[] = 'post';
		}

		$query->set( 'post_type', $post_types );
		$query->set( 'tax_query', $tax_query );

		if ( ( isset( $_GET['share'] ) && $_GET['share'] === $share_hash ) || $viewable ) {
			$post_status = array( 'publish', 'private' );
			if ( isset( $_GET['show-hidden'] ) ) {
				$post_status[] = 'trash';
			}
			$query->set( 'post_status', $post_status );
		}
		$query->is_page = false;
		$query->is_comments_feed = false;
		$query->set( 'pagename', null );
		if ( 'collapsed' === get_option( 'friends_frontend_default_view', 'expanded' ) && get_option( 'posts_per_page' ) < 20 ) {
			if ( 'status' === $post_format ) {
				$query->set( 'posts_per_page', 30 );
			} else {
				$query->set( 'posts_per_page', 20 );
			}
		}

		if ( $page_id ) {
			$query->set( 'page_id', $page_id );
			if ( ! $this->author ) {
				$post = get_post( $page_id );
				$author = User::get_post_author( $post );
				if ( false !== $author ) {
					$this->author = $author;
				}
			}
			$query->is_single = true;
			$query->is_singular = true;
			$wp->set_query_var( 'page', null );
			$wp->set_query_var( 'page_id', $page_id );
		}

		if ( $this->author ) {
			if ( $this->author instanceof User ) {
				$authordata = $this->author;
				$this->author->modify_query_by_author( $query );
				if ( ! $page_id ) {
					$query->is_author = true;
				}
			} else {
				$this->author = null;
			}
		}

		if ( ! $query->is_singular && ! $query->is_author ) {
			// This is the main friends page.
			$hide_from_friends_page = get_user_option( 'friends_hide_from_friends_page' );
			$hide_from_friends_page = array_diff( array_map( 'intval', (array) $hide_from_friends_page ), array( 0 ) );

			if ( $hide_from_friends_page ) {
				$query->set( 'author__not_in', $hide_from_friends_page );
			}
		}

		return $query;
	}
}
