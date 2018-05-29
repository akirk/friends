<?php
/**
 * Friends 3rd Parties
 *
 * This contains the functions for working with third party plugins.
 *
 * @package Friends
 */

/**
 * This is the class for the Friends Plugin 3rd Party handling.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_3rd_Parties {
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
		add_filter( 'option_fx-private-site', array( $this, 'fx_private_site' ) );
	}

	/**
	 * Allow accessing the private feed when the FX Private Site plugin is installed.
	 *
	 * @param mixed $value  Value of the option.
	 */
	function fx_private_site( $value ) {
		if ( $this->friends->access_control->feed_is_authenticated() ) {
			$value['enable'] = false;
		}
		return $value;
	}
}
