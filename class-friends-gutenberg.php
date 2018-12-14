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
		$this->register_hooks();
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'language_data' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'register_friends_block_visibility' ) );
		add_action( 'render_block', array( $this, 'render_friends_block_visibility' ), 10, 2 );
	}

	/**
	 * Register the Gutenberg Block Visibility
	 */
	public function register_friends_block_visibility() {
		wp_enqueue_script(
			'friends-block-visibility',
			plugins_url( 'blocks/block-visibility.build.js', __FILE__ ),
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-editor' )
		);

		wp_enqueue_style(
			'friends-gutenberg',
			plugins_url( 'friends-gutenberg.css', __FILE__ )
		);
	}

	/**
	 * Render the "Only visible for friends" Gutenberg block
	 *
	 * @param  object $content    The content provided by the user.
	 * @param  object $block      Attributes for the block.
	 * @return string             The rendered content.
	 */
	public function render_friends_block_visibility( $content, $block ) {
		$visibility = ( empty( $block['attrs'] ) || empty( $block['attrs']['friends_visibility'] ) ) ? 'default' : $block['attrs']['friends_visibility'];

		switch ( $visibility ) {
			case 'only-friends':
				if ( current_user_can( 'friend' ) || current_user_can( 'administrator' ) ) {
					return $content;
				}
				return '';

			case 'not-friends':
				if ( current_user_can( 'friend' ) && ! current_user_can( 'administrator' ) ) {
					return '';
				}
				return $content;

		}

		return $content;
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

		if ( ! empty( $locale_data ) ) {
			wp_add_inline_script(
				'friends-block-not-friends',
				'wp.i18n.setLocaleData( ' . wp_json_encode( $locale_data ) . ', "friends" );',
				'before'
			);
		}
	}
}
