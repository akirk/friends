<?php
/**
 * Friends Automatic Status
 *
 * This contains the functions for the Automatic Status.
 *
 * @package Friends
 */

/**
 * This is the class for the Friends Plugin Automatic Status.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Automatic_Status {
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
		add_action( 'friends_user_post_reaction', array( $this, 'post_reaction' ), 10, 2 );
	}

	/**
	 * Adds a status post.
	 *
	 * @param      string $text   The text.
	 *
	 * @return     int|WP_Error  The post ID or a WP_Error.
	 */
	private function add_status( $text ) {
		return wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'draft',
				'tax_input'    => array( 'post_format' => 'status' ),
				'post_content' => $text,
			)
		);
	}

	/**
	 * Create a status based on a post reaction.
	 *
	 * @param      int    $post_id  The post ID.
	 * @param      string $emoji    The emoji.
	 */
	public function post_reaction( $post_id, $emoji ) {
		$post = get_post( $post_id );
		$this->add_status(
			sprintf(
				// translators: %1$s is an emoji, %2$s is a linked post title.
				__( 'I just reacted with %1$s on %2$s' ),
				$emoji,
				'<a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_url( get_the_title( $post ) ) . '</a>'
			)
		);
	}
}
