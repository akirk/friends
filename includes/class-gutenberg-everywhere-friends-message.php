<?php
/**
 * Gutenberg-Everywhere Friends Message
 *
 * @package Friends
 */

/**
 * This is the class to load the Gutenberg editor for the Friends messages.
 *
 * @package Friends
 * @author Alex Kirk
 */
class Gutenberg_Everywhere_Friends_Message extends Gutenberg_Handler {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'friends_message_form', array( $this, 'add_to_form' ) );
		add_action( 'gutenberg_everywhere_allowed_blocks', array( $this, 'allowed_blocks' ), 10, 2 );
	}

	/**
	 * Gets the editor type.
	 *
	 * @return     string  The editor type.
	 */
	public function get_editor_type() {
		return 'friends';
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
		if ( 'friends' === $type ) {
			$allowed[] = 'core/embed';
		}
		return $allowed;
	}

	/**
	 * Get the HTML that the editor uses on the page
	 */
	public function add_to_form() {
		$this->load_editor( '#friends_message_message', '.gutenberg-everywhere' );
	}
}
