<?php
/**
 * Class Local_Feed_Fetcher
 *
 * @package Friends
 */

namespace Friends;

/**
 * Allow local fetching of a feed by attaching a filter
 */
class Local_Feed_Fetcher extends SimplePie_File {
	/**
	 * The URL that was retrieved.
	 *
	 * @var string
	 */
	public $url;

	/**
	 * The user-agent that was used.
	 *
	 * @var $useragent
	 */
	public $useragent;

	/**
	 * Whether the fetch was successful.
	 *
	 * @var boolean
	 */
	public $success = true;

	/**
	 * The server headers returned.
	 *
	 * @var array
	 */
	public $headers = array(
		'content-type' => 'application/rss+xml',
	);

	/**
	 * The body that was returned.
	 *
	 * @var string
	 */
	public $body;

	/**
	 * The status code that was returned.
	 *
	 * @var integer
	 */
	public $status_code = 200;

	/**
	 * The number of redirects that happened.
	 *
	 * @var integer
	 */
	public $redirects = 0;

	/**
	 * A possible error message
	 *
	 * @var string
	 */
	public $error;

	/**
	 * The method that was used to retrieve the content.
	 *
	 * @var integer
	 */
	public $method = SIMPLEPIE_FILE_SOURCE_REMOTE;

	/**
	 * Instanciate the class
	 *
	 * @param string  $url             The url to retrieve.
	 * @param integer $timeout         The time we have to fetch this.
	 * @param integer $redirects       How many redirects are ok.
	 * @param array   $headers         Headers to send in the request.
	 * @param string  $useragent       User-agent to use in this request.
	 * @param boolean $force_fsockopen Whether to force usage of fsockopen.
	 */
	public function __construct( $url, $timeout = 10, $redirects = 5, $headers = null, $useragent = null, $force_fsockopen = false ) {
		$this->url = $url;
		$this->useragent = $useragent;
		return apply_filters( 'local_fetch_feed', $this );
	}
}
