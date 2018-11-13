<?php
/**
 * Friends Gutenberg
 *
 * This contains the functions for gutenberg.
 *
 * @package Friends
 */

/**
 * This is the class for the Friends Plugin gutenberg.
 *
 * @since 0.8
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Gutenberg {
	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends = null;

	/**
	 * Constructor
	 *
	 * @param Friends $friends A reference to the Friends object.
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;

		if ( ! function_exists( 'register_block_type' ) ) {
			// Gutenberg is not active.
			return;
		}

		$this->register_gutenberg_blocks();
	}

	/**
	 * Register the Gutenberg blocks
	 */
	private function register_gutenberg_blocks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'language_data' ) );
		add_action( 'init', array( $this, 'register_only_friends' ) );
		add_action( 'init', array( $this, 'register_not_friends' ) );
	}

	/**
	 * Register the "Only visible for friends" Gutenberg block
	 */
	public function register_only_friends() {
		wp_register_script(
			'friends-block-only-friends',
			plugins_url( 'blocks/only-friends.build.js', __FILE__ ),
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-editor' )
		);

		wp_register_style(
			'friends-gutenberg',
			plugins_url( 'friends-gutenberg.css', __FILE__ )
		);

		register_block_type(
			'friends/only-friends',
			array(
				'editor_script'   => 'friends-block-only-friends',
				'style'           => 'friends-gutenberg',
				'render_callback' => array( $this, 'render_only_friends' ),
			)
		);
	}

	/**
	 * Render the "Only visible for friends" Gutenberg block
	 *
	 * @param  object $attributes Attributes for the block.
	 * @param  object $content    The content provided by the user.
	 * @return string             The rendered content.
	 */
	public function render_only_friends( $attributes, $content ) {
		if ( current_user_can( 'friend' ) ) {
			return do_shortcode( $content );
		}

		if ( current_user_can( Friends::REQUIRED_ROLE ) ) {
			return '<div class="only-friends"><span class="watermark">' . __( 'Only friends', 'friends' ) . '</span>' . do_shortcode( $content ) . '</div>';
		}

		return '';
	}

	/**
	 * Register the "Only visible for non-friends" Gutenberg block
	 */
	public function register_not_friends() {
		wp_register_script(
			'friends-block-not-friends',
			plugins_url( 'blocks/not-friends.build.js', __FILE__ ),
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-editor' )
		);

		wp_register_style(
			'friends-gutenberg',
			plugins_url( 'friends-gutenberg.css', __FILE__ )
		);

		register_block_type(
			'friends/not-friends',
			array(
				'editor_script'   => 'friends-block-not-friends',
				'style'           => 'friends-gutenberg',
				'render_callback' => array( $this, 'render_not_friends' ),
			)
		);
	}

	/**
	 * Render the "Only visible for non-friends" Gutenberg block
	 *
	 * @param  object $attributes Attributes for the block.
	 * @param  object $content    The content provided by the user.
	 * @return string             The rendered content.
	 */
	public function render_not_friends( $attributes, $content ) {
		if ( current_user_can( 'friend' ) ) {
			return '';
		}

		if ( current_user_can( Friends::REQUIRED_ROLE ) ) {
			return '<div class="not-friends"><span class="watermark">' . __( 'Not friends', 'friends' ) . '</span>' . do_shortcode( $content ) . '</div>';
		}

		return do_shortcode( $content );
	}

	/**
	 * Load up the language data for the Gutenberg blocks
	 */
	public function language_data() {
		$locale_data = array();
		if ( function_exists( 'wp_get_jed_locale_data' ) ) {
			$locale_data = wp_get_jed_locale_data( 'friends' );
		} elseif ( function_exists( 'gutenberg_get_jed_locale_data' ) ) {
			$locale_data = gutenberg_get_jed_locale_data( 'friends' );
		}

		wp_add_inline_script(
			'friends-block-not-friends',
			'wp.i18n.setLocaleData( ' . wp_json_encode( $locale_data ) . ', "friends" );',
			'before'
		);
	}
}
