<?php
/**
 * Friends: SimplePie_File_Accept_Only_RSS class
 *
 * @package Friends
 * @since 1.0
 */

namespace Friends;

/**
 * Send an accept header that only allows for Atom or RSS.
 *
 * @see \WP_SimplePie_File
 */
class SimplePie_File_Accept_Only_RSS extends \WP_SimplePie_File {

	/**
	 * Constructor.
	 *
	 * @since 2.8.0
	 * @since 3.2.0 Updated to use a PHP5 constructor.
	 *
	 * @param string       $url             Remote file URL.
	 * @param int          $timeout         Optional. How long the connection should stay open in seconds.
	 *                                      Default 10.
	 * @param int          $redirects       Optional. The number of allowed redirects. Default 5.
	 * @param string|array $headers         Optional. Array or string of headers to send with the request.
	 *                                      Default null.
	 * @param string       $useragent       Optional. User-agent value sent. Default null.
	 * @param bool         $force_fsockopen Optional. Whether to force opening internet or unix domain socket
	 *                                      connection or not. Default false.
	 */
	public function __construct( $url, $timeout = 10, $redirects = 5, $headers = null, $useragent = null, $force_fsockopen = false ) {
		if ( ! is_array( $headers ) ) {
			$headers = array();
		}
		$headers['Accept'] = 'application/atom+xml, application/rss+xml, application/rdf+xml;q=0.9';
		parent::__construct( $url, $timeout, $redirects, $headers, $useragent, $force_fsockopen );
	}
}
