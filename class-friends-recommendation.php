<?php
/**
 * Friends Recommendation
 *
 * This contains the functions for Recommendation.
 *
 * @package Friends
 */

/**
 * This is the class for the Recommendation part of the Friends Plugin.
 *
 * @since 0.9
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Recommendation {
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
		add_action( 'wp_ajax_friends_recommend_post', array( $this, 'recommend_post' ) );
		add_action( 'the_content', array( $this, 'post_recommendation' ), 25 );
		add_action( 'wp_footer', array( $this, 'recommendation_form' ), 25 );
	}

	/**
	 * Unregister the hooks that attach recommendation to content
	 */
	public function unregister_content_hooks() {
		remove_action( 'the_content', array( $this, 'post_recommendation' ), 25 );
	}

	/**
	 * Display the recommend button under a post.
	 *
	 * @param  string  $text The post content.
	 * @param  boolean $echo Whether the content should be echoed.
	 * @return string        The post content with buttons or nothing if echoed.
	 */
	public function post_recommendation( $text = '', $echo = false ) {
		if ( $this->friends->is_cached_post_type( get_post_type() ) && is_user_logged_in() ) {
			ob_start();
			include apply_filters( 'friends_template_path', 'friends/post-recommendation.php' );
			$recommendation_text = ob_get_contents();
			ob_end_clean();

			$text .= $recommendation_text;
		}

		if ( ! $echo ) {
			return $text;
		}

		echo $text;
	}

	/**
	 * Output the form for sending a recommendation.
	 */
	public function recommendation_form() {
		if ( is_user_logged_in() ) {
			$friends = Friends::all_friends();
			include apply_filters( 'friends_template_path', 'friends/recommendation-form.php' );
		}
	}

	/**
	 * Recommend a post to specific friends.
	 */
	public function recommend_post() {
		check_ajax_referer( 'friends-recommendation' );

		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'unauthorized', 'You are not authorized to send a recommendation.' );
		}

		if ( ! isset( $_POST['post_id'] ) || empty( $_POST['friends'] ) ) {
			wp_send_json_error(
				array(
					'result' => __( 'There was a problem sending your recommendation.', 'friends' ),
				)
			);
		}

		if ( ! isset( $_POST['message'] ) ) {
			$_POST['message'] = '';
		}

		if ( ! is_array( $_POST['friends'] ) || count( $_POST['friends'] ) <= 0 ) {
			wp_send_json_error(
				array(
					'result' => __( 'There was a problem sending your recommendation.', 'friends' ),
				)
			);
		}

		if ( ! is_numeric( $_POST['post_id'] ) || $_POST['post_id'] <= 0 ) {
			wp_send_json_error(
				array(
					'result' => __( 'There was a problem sending your recommendation.', 'friends' ),
				)
			);
		}

		$post_id = intval( $_POST['post_id'] );
		$post    = WP_Post::get_instance( $post_id );
		if ( ! $this->friends->is_cached_post_type( $post->post_type ) ) {
			return;
		}

		$friend_user = new WP_User( $post->post_author );
		if (
			$friend_user->has_cap( 'subscription' )
			|| 'publish' === $post->post_status
		) {
			// The link is public so let's include title and content.
			$recommendation = array(
				'link'        => get_permalink( $post ),
				'title'       => get_the_title( $post ),
				'author'      => get_the_author_meta( 'display_name', $post->post_author ),
				'gravatar'    => get_avatar_url( $friend_user->ID ),
				'description' => $post->post_content,
				'post_type'   => $post->post_type,
				'message'     => $_POST['message'],
			);
		} else {
			$recommendation = array(
				'sha1_link' => sha1( get_permalink( $post ) ),
				'message'   => $_POST['message'],
			);

			// TODO: maybe anonymously recommend this (potentially private post) to friends,
			// so that they can make use of the recommendation _if_ they also have that
			// friend's (private) post cached on their side.
			return;
		}

		$recommendation['reactions'] = $reactions;

		$friends = new WP_User_Query(
			array(
				'role'    => 'friend',
				'include' => array_map( 'intval', $_POST['friends'] ),
			)
		);

		foreach ( $friends->get_results() as $friend_user ) {
			$friend_rest_url = $this->friends->access_control->get_rest_url( $friend_user );

			$response = wp_safe_remote_post(
				$friend_rest_url . '/recommendation',
				array(
					'body'        => array_merge(
						$recommendation,
						array(
							'friend' => get_user_option( 'friends_out_token', $friend_user->ID ),
						)
					),
					'timeout'     => 2,
					'redirection' => 5,
				)
			);
		}
		wp_send_json_success(
			array(
				'result' => __( 'Your recommendation was sent.', 'friends' ),
			)
		);
	}
}
