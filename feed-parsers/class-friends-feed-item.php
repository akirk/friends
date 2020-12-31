<?php
/**
 * Friends Feed Item
 *
 * This contains the reference implementation for a item.
 *
 * @package Friends
 */

/**
/**
 * This class describes a friends feed item.
 */
class Friends_Feed_Item {
	/**
	 * Holds the feed item data.
	 *
	 * @var        array
	 */
	private $data = array(
		'post_format' => 'standard',
		'post_status' => 'publish',
	);

	/**
	 * Initialize a new feed item with the supplied data.
	 *
	 * @param      array $data   The data.
	 */
	public function __construct( array $data = array() ) {
		foreach ( $data as $key => $value ) {
			$this->__set( $key, $value );
		}
	}

	/**
	 * Gets the specified key.
	 *
	 * @param      string $key    The key.
	 *
	 * @return     mixed  The value.
	 */
	public function __get( $key ) {
		if ( ! isset( $this->data[ $key ] ) ) {
			return null;
		}

		switch ( $key ) {
			case 'updated_date':
			case 'date':
				return gmdate( 'Y-m-d H:i:s', $this->data[ $key ] );
		}

		return $this->data[ $key ];
	}

	/**
	 * Sets the value for the key and validates it.
	 *
	 * @param      string $key    The key.
	 * @param      mixed  $value  The value.
	 *
	 * @return     mixed|WP_Error  The value or a wp error.
	 */
	public function __set( $key, $value ) {
		switch ( $key ) {
			case 'permalink':
				$value = $this->validate_url( $value, 'invalid-permalink' );
				break;

			case 'gravatar':
				$value = $this->validate_url( $value, 'invalid-gravatar' );
				break;

			case 'title':
				$value = $this->validate_string( $value, 300, 'invalid-title' );
				break;

			case 'author':
				$value = $this->validate_string( $value, 300, 'invalid-author' );
				break;

			case 'content':
				$value = $this->validate_string( $value, 50000, 'invalid-content' );
				break;

			case 'comment_count':
				$value = $this->validate_integer( $value, 0, 10000, 'invalid-comments-count' );
				break;

			case 'post_id':
				$value = $this->validate_integer( $value, 0, PHP_INT_MAX, 'invalid-post-id' );
				break;

			case 'post_format':
				$value = $this->validate_post_format( $value );
				break;

			case 'post_status':
				$value = $this->validate_post_status( $value );
				break;

			case 'date':
				$value = $this->validate_date( $value, 'invalid-date' );
				break;

			case 'updated_date':
				$value = $this->validate_date( $value, 'invalid-updated-date' );
				break;

			// Internal.
			case '_feed_rule_delete':
				$value = boolval( $value );
				break;
			case '_feed_rule_transform':
				if ( ! is_array( $value ) ) {
					$value = new WP_Error( 'invalid-key', 'This value cannot be stored in a _feed_rule_transform.' );
				}
				break;

			default:
				return new WP_Error( 'invalid-key', 'This value cannot be stored in a feed item.' );
		}

		if ( ! is_wp_error( $value ) ) {
			$this->data[ $key ] = $value;
		}

		return $value;
	}

	/**
	 * Validate a given string.
	 *
	 * @param      string $string      The string.
	 * @param      int    $max_length  The maximum length of the string.
	 * @param      string $error_code  The error code.
	 *
	 * @return     string|WP_Error  The validated string.
	 */
	public function validate_string( $string, $max_length, $error_code ) {
		if ( ! is_string( $string ) ) {
			return new WP_Error( $error_code, 'No string was supplied.' );
		}

		return rtrim( substr( trim( $string ), 0, $max_length ) );
	}

	/**
	 * Validate a given integer.
	 *
	 * @param      int|string $integer  The integer.
	 * @param      int        $min      The minimum acceptable value.
	 * @param      int        $max      The maximum acceptable value.
	 * @param      string     $error_code  The error code.
	 *
	 * @return     int|WP_Error  The validated integer.
	 */
	public function validate_integer( $integer, $min, $max, $error_code ) {
		if ( ! is_numeric( $integer ) ) {
			return new WP_Error( $error_code, 'No number was supplied.' );
		}
		$int = intval( $integer );
		if ( $int > $max ) {
			return new WP_Error( $error_code, 'Number exceeds the maximum value.' );
		}
		if ( $int < $min ) {
			return new WP_Error( $error_code, 'Number is below the minimum value.' );
		}
		return $int;
	}

	/**
	 * Validate a given url.
	 *
	 * @param      string $url  The url.
	 * @param      string $error_code  The error code.
	 *
	 * @return     string|WP_Error  The validated url.
	 */
	public function validate_url( $url, $error_code ) {
		$url = filter_var( $url, FILTER_VALIDATE_URL );
		if ( false === $url ) {
			return new WP_Error( $error_code, 'An invalid URL was supplied.' );
		}

		return $url;
	}

	/**
	 * Validate a given date.
	 *
	 * @param      string|int $date  The date.
	 * @param      string     $error_code  The error code.
	 *
	 * @return     string|WP_Error  The validated date.
	 */
	public function validate_date( $date, $error_code ) {
		if ( ! is_int( $date ) ) {
			$date = strtotime( $date );
		}

		if ( 0 < $date ) {
			return new WP_Error( $error_code, 'An invalid timestamp was supplied.' );
		}

		if ( false === $date ) {
			return new WP_Error( $error_code, 'The date could not be convered to a timestamp.' );
		}
		return $date;
	}

	/**
	 * Validate a given post format.
	 *
	 * @param      string $format  The post format.
	 *
	 * @return     string|WP_Error  The validated format.
	 */
	public function validate_post_format( $format ) {
		$post_formats = get_post_format_strings();
		if ( ! isset( $post_formats[ $format ] ) ) {
			return new WP_Error( 'invalid-post-format', 'The format needs to be one of get_post_format_strings().' );

		}
		return $format;
	}

	/**
	 * Validate a given post status.
	 *
	 * @param      string $status  The post status.
	 *
	 * @return     string|WP_Error  The validated status.
	 */
	public function validate_post_status( $status ) {
		$valid_post_statuses = array( 'draft', 'publish', 'private' );
		if ( in_array( $status, $valid_post_statuses ) ) {
			return new WP_Error( 'invalid-post-status', 'The status needs to be one of draft, publish, or private.' );

		}
		return $format;
	}
}
