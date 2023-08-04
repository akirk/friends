<?php
namespace Friends\Blocks_Everywhere\Handler;


class Friends_Message extends Handler {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'friends_message_form', array( $this, 'add_to_form' ) );
		add_action( 'blocks_everywhere_allowed_blocks', array( $this, 'allowed_blocks' ), 10, 2 );
	}

	/**
	 * Gets the editor type.
	 *
	 * @return     string  The editor type.
	 */
	public function get_editor_type() {
		return 'friends-message';
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
		if ( 'friends-message' === $type ) {
			$allowed[] = 'core/embed';
		}
		return $allowed;
	}

	/**
	 * Get the HTML that the editor uses on the page
	 */
	public function add_to_form() {
		$this->load_editor( '.friends-message-message.blocks-everywhere-enabled', '.blocks-everywhere' );
	}
}
