<?php
namespace Friends\Blocks_Everywhere\Handler;


class Friends_Status_Post extends Handler {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'friends_post_status_form', array( $this, 'add_to_form' ) );
		add_action( 'blocks_everywhere_allowed_blocks', array( $this, 'allowed_blocks' ), 10, 2 );
		add_filter( 'blocks_everywhere_editor_settings', function( $settings ) {
			$settings['editor'] = array_merge(
				$settings['editor'],
				array(
					'hasUploadPermissions' => current_user_can( 'edit_private_posts' ),
					'allowedMimeTypes' => get_allowed_mime_types(),
				)
			);
			$settings['iso']['blocks']['allowBlocks'][] = 'core/image';
			$settings['iso']['blocks']['allowBlocks'][] = 'core/embed';
			$settings['iso']['toolbar']['inspector'] = true;
			return $settings;
		} );

	}

	/**
	 * Gets the editor type.
	 *
	 * @return     string  The editor type.
	 */
	public function get_editor_type() {
		return 'friends-status';
	}

	/**
	 * Modifies the allowed blocks.
	 *
	 * @param      array  $allowed  The allowed blocks.
	 * @param      string $type     The editor type.
	 *
	 * @return     array  The allowed blocks.
	 */
	public function allowed_blocks( $allowed, $type ) {
		if ( 'friends-status' === $type ) {
			$allowed[] = 'core/embed';
			$allowed[] = 'core/image';
		}
		return $allowed;
	}

	/**
	 * Get the HTML that the editor uses on the page
	 */
	public function add_to_form() {
		$this->load_editor( '.friends-status-content.blocks-everywhere-enabled', '.blocks-everywhere' );
	}
}
