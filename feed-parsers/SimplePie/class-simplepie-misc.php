<?php
/**
 * Friends: SimplePie_Misc class
 *
 * @package Friends
 * @since 1.0
 */

namespace Friends;

/**
 * Overrides SimplePie_Misc functions as necessary.
 *
 * @see SimplePie_Misc
 */
class SimplePie_Misc extends \SimplePie_Misc {
	/**
	 * Change a string from one encoding to another
	 *
	 * @param string $data Raw data in $input encoding.
	 * @param string $input Encoding of $data.
	 * @param string $output Encoding you want.
	 * @return string|boolean False if we can't convert it.
	 */
	public static function change_encoding( $data, $input, $output ) {
		if ( 'UTF-8' === $input && 'UTF-8' === $output ) {
			$clean_utf8_regex = '
!                                 # Thanks, https://stackoverflow.com/a/1401716/578588
  (                               # modified to clean for valid XML ASCII, see https://www.w3.org/TR/REC-xml/#NT-Char
    (?: [\x9\xD]                  # valid XML single-bytes
    |   [\x20-\x7F]               # single-byte sequences   0xxxxxxx
    |   [\xC0-\xDF][\x80-\xBF]    # double-byte sequences   110xxxxx 10xxxxxx
    |   [\xE0-\xEF][\x80-\xBF]{2} # triple-byte sequences   1110xxxx 10xxxxxx * 2
    |   [\xF0-\xF7][\x80-\xBF]{3} # quadruple-byte sequence 11110xxx 10xxxxxx * 3
    ){1,100}                      # ...one or more times
  )
| ( [\x80-\xBF] )                 # invalid byte in range 10000000 - 10111111
| ( [\xC0-\xFF] )                 # invalid byte in range 11000000 - 11111111
| (.)
!x
';
			$data = preg_replace_callback(
				$clean_utf8_regex,
				function ( $captures ) {
					if ( $captures[1] ) {
						// Valid byte sequence. Return unmodified.
						return $captures[1];
					} elseif ( $captures[2] ) {
						// Invalid byte of the form 10xxxxxx.
						// Encode as 11000010 10xxxxxx.
						return "\xC2" . $captures[2];
					} elseif ( $captures[3] ) {
						// Invalid byte of the form 11xxxxxx.
						// Encode as 11000011 10xxxxxx.
						return "\xC3" . chr( ord( $captures[3] ) - 64 );
					}
					// Else: ignore a single-byte characters invalid for XML. Ignore.
				},
				$data
			);
		}
		return parent::change_encoding( $data, $input, $output );
	}

	public static function error( $message, $level, $file, $line ) {
		if ( apply_filters( 'friends_debug', false ) ) {
			return parent::error( $message, $level, $file, $line );
		}
	}
}
