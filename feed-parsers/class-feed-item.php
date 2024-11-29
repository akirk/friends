<?php
/**
 * Friends Feed Item
 *
 * This contains the reference implementation for a item.
 *
 * @package Friends
 */

namespace Friends;

/**
/**
 * This class describes a friends feed item.
 */
class Feed_Item {
	/**
	 * Holds the feed item data.
	 *
	 * @var        array
	 */
	private $data = array(
		'post_format' => 'standard',
		'post_status' => 'publish',
		'is_new'      => false,
		'meta'        => array(),
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
		if ( 'content' === $key ) {
			$key = 'post_content';
		} elseif ( 'title' === $key ) {
			$key = 'post_title';
		}

		if ( ! $this->__isset( $key ) ) {
			return null;
		}

		switch ( $key ) {
			case 'updated_date':
			case 'date':
				return gmdate( 'Y-m-d H:i:s', $this->data[ $key ] );
			case 'meta':
				$meta = $this->data['meta'];
				if ( isset( $meta['enclosure'] ) ) {
					if ( isset( $meta['enclosure']['url'] ) ) {
						$url = $meta['enclosure']['url'];
						$len = '';
						if ( isset( $meta['enclosure']['length'] ) ) {
							$len = $meta['enclosure']['length'];
						}
						$mime = '';
						if ( isset( $meta['enclosure']['mime'] ) ) {
							$mime = $meta['enclosure']['mime'];
						}

						$meta['enclosure'] = "$url\n$len\n$mime\n";
					} else {
						// An enclosure only makes sense if it has a URL.
						unset( $meta['enclosure'] );
					}
				}

				return $meta;
		}

		return $this->data[ $key ];
	}

	/**
	 * Whether the specified key is set
	 *
	 * @param      string $key    The key.
	 *
	 * @return     bool  Whether the key is set.
	 */
	public function __isset( $key ) {
		if ( 'content' === $key ) {
			$key = 'post_content';
		} elseif ( 'title' === $key ) {
			$key = 'post_title';
		}

		return isset( $this->data[ $key ] );
	}

	/**
	 * Sets the value for the key and validates it.
	 *
	 * @param      string $key    The key.
	 * @param      mixed  $value  The value.
	 *
	 * @return     mixed|\WP_Error  The value or a wp error.
	 */
	public function __set( $key, $value ) {
		if ( apply_filters( 'feed_item_allow_set_metadata', false, $key, $value, $this ) ) {
			$this->data['meta'][ $key ] = $value;
			return $value;
		}

		switch ( $key ) {
			case 'permalink':
				$value = $this->validate_url( $value, 'invalid-permalink' );
				break;

			case 'gravatar':
				$value = $this->validate_url( $value, 'invalid-gravatar' );
				break;

			case 'title':
			case 'post_title':
				$key = 'post_title';
				$value = $this->validate_string( $value, 300, 'invalid-title' );
				break;

			case 'author':
				$value = $this->validate_string( $value, 300, 'invalid-author' );
				break;

			case 'post_content':
			case 'content':
				$key = 'post_content';
				$value = $this->validate_string( $value, 50000, 'invalid-content' );
				break;

			case 'comment_count':
				$value = $this->validate_integer( $value, 0, 10000, 'invalid-comments-count' );
				break;

			case 'comments_feed':
				$value = $this->validate_url( $value, 'invalid-comments-feed' );
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

			case 'enclosure':
				if ( ! is_array( $value ) ) {
					return new \WP_Error( 'invalid-enclosure', 'This value cannot be stored in a enclosure.' );
				}

				if ( ! isset( $value['url'] ) || ! $this->validate_url( $value['url'], 'invalid-enclosure-url' ) ) {
					return new \WP_Error( 'invalid-enclosure-url', 'The enclosure URL is invalid.' );
				}

				$this->data['meta']['enclosure'] = $value;
				break;

			// Internal.
			case '_feed_rule_delete':
				$value = boolval( $value );
				break;
			case '_feed_rule_transform':
				if ( ! is_array( $value ) ) {
					$value = new \WP_Error( 'invalid-key', 'This value cannot be stored in a _feed_rule_transform.' );
				}
				break;
			case '_is_new':
				$value = boolval( $value );
				break;
			case '_full_content_fetched':
				$value = boolval( $value );
				$this->data['meta']['full-content-fetched'] = $value;
				return $value;
			case '_external_id':
				$value = strval( $value );
				$this->data['meta']['external-id'] = $value;
				return $value;

			default:
				return new \WP_Error( 'invalid-key', 'This value cannot be stored in a feed item.' );
		}

		if ( ! is_wp_error( $value ) ) {
			$this->data[ $key ] = $value;
		}

		return $value;
	}

	/**
	 * Validate a given string.
	 *
	 * @param      string $str         The string.
	 * @param      int    $max_length  The maximum length of the string.
	 * @param      string $error_code  The error code.
	 *
	 * @return     string|\WP_Error  The validated string.
	 */
	public function validate_string( $str, $max_length, $error_code ) {
		if ( ! is_string( $str ) ) {
			return new \WP_Error( $error_code, 'No string was supplied.' );
		}

		return rtrim( substr( trim( $str ), 0, $max_length ) );
	}

	/**
	 * Validate a given integer.
	 *
	 * @param      int|string $integer  The integer.
	 * @param      int        $min      The minimum acceptable value.
	 * @param      int        $max      The maximum acceptable value.
	 * @param      string     $error_code  The error code.
	 *
	 * @return     int|\WP_Error  The validated integer.
	 */
	public function validate_integer( $integer, $min, $max, $error_code ) {
		if ( ! is_numeric( $integer ) ) {
			return new \WP_Error( $error_code, 'No number was supplied.' );
		}
		$int = intval( $integer );
		if ( $int > $max ) {
			return new \WP_Error( $error_code, 'Number exceeds the maximum value.' );
		}
		if ( $int < $min ) {
			return new \WP_Error( $error_code, 'Number is below the minimum value.' );
		}
		return $int;
	}

	/**
	 * Validate a given url.
	 *
	 * @param      string $url  The url.
	 * @param      string $error_code  The error code.
	 *
	 * @return     string|\WP_Error  The validated url.
	 */
	public function validate_url( $url, $error_code ) {
		$url = filter_var( $url, FILTER_VALIDATE_URL );
		if ( false === $url ) {
			return new \WP_Error( $error_code, 'An invalid URL was supplied.' );
		}

		return $url;
	}

	/**
	 * Validate a given date.
	 *
	 * @param      string|int $date  The date.
	 * @param      string     $error_code  The error code.
	 *
	 * @return     string|\WP_Error  The validated date.
	 */
	public function validate_date( $date, $error_code ) {
		if ( is_string( $date ) && ! is_numeric( $date ) ) {
			$date = strtotime( $date );
		}

		if ( is_null( $date ) || 0 > $date ) {
			return new \WP_Error( $error_code, 'An invalid timestamp was supplied.' );
		}

		if ( false === $date ) {
			return new \WP_Error( $error_code, 'The date could not be convered to a timestamp.' );
		}
		return $date;
	}

	/**
	 * Validate a given post format.
	 *
	 * @param      string $format  The post format.
	 *
	 * @return     string|\WP_Error  The validated format.
	 */
	public function validate_post_format( $format ) {
		$post_formats = get_post_format_strings();
		if ( ! isset( $post_formats[ $format ] ) ) {
			return new \WP_Error( 'invalid-post-format', 'The format needs to be one of get_post_format_strings().' );

		}
		return $format;
	}

	/**
	 * Validate a given post status.
	 *
	 * @param      string $status  The post status.
	 *
	 * @return     string|\WP_Error  The validated status.
	 */
	public function validate_post_status( $status ) {
		$valid_post_statuses = array( 'draft', 'publish', 'private' );
		if ( in_array( $status, $valid_post_statuses ) ) {
			return new \WP_Error( 'invalid-post-status', 'The status needs to be one of draft, publish, or private.' );

		}
		return $status;
	}

	/**
	 * Determines if the item is new.
	 *
	 * @return     bool  True if new, False otherwise.
	 */
	public function is_new() {
		return isset( $this->data['_is_new'] ) && $this->data['_is_new'];
	}
}
