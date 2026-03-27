<?php
/**
 * Friends: SimplePie_Sanitize_KSES class
 *
 * Extends the WordPress SimplePie sanitizer to handle style tags and
 * convert embeddable iframes to plain URLs before wp_kses_post strips them.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Custom SimplePie sanitizer for Friends.
 */
class SimplePie_Sanitize_KSES extends \WP_SimplePie_Sanitize_KSES {
	/**
	 * Allowed iframe host patterns for conversion to plain URLs.
	 *
	 * @var array
	 */
	private static $embeddable_hosts = array(
		'youtube.com',
		'www.youtube.com',
		'youtube-nocookie.com',
		'www.youtube-nocookie.com',
		'youtu.be',
		'vimeo.com',
		'player.vimeo.com',
		'dailymotion.com',
		'www.dailymotion.com',
	);

	/**
	 * Sanitize data with additional pre-processing for styles and iframes.
	 *
	 * @param mixed  $data The data that needs to be sanitized.
	 * @param int    $type The type of data that it's supposed to be.
	 * @param string $base The xml:base value.
	 * @return mixed Sanitized data.
	 */
	public function sanitize( $data, $type, $base = '' ) {
		if ( $type & ( \SimplePie\SimplePie::CONSTRUCT_HTML | \SimplePie\SimplePie::CONSTRUCT_XHTML | \SimplePie\SimplePie::CONSTRUCT_MAYBE_HTML ) ) {
			// Strip <style> and <script> tags with their content before wp_kses_post
			// which only removes the tags but leaves the inner text behind.
			$data = preg_replace( '#<(style|script)\b[^>]*>.*?</\1>#si', '', $data );

			// Convert embeddable iframes to plain URLs so WordPress can auto-embed them.
			$data = preg_replace_callback(
				'#<(?:figure|div)[^>]*>\s*(?:<(?:figure|div)[^>]*>\s*)?<iframe\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>\s*</iframe>\s*(?:</(?:figure|div)>\s*)?(?:</(?:figure|div)>)?#si',
				array( $this, 'maybe_convert_iframe_to_url' ),
				$data
			);

			// Also handle standalone iframes not wrapped in figure/div.
			$data = preg_replace_callback(
				'#<iframe\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>\s*</iframe>#si',
				array( $this, 'maybe_convert_iframe_to_url' ),
				$data
			);
		}

		return parent::sanitize( $data, $type, $base );
	}

	/**
	 * Convert an iframe match to a plain URL if the host is embeddable.
	 *
	 * @param array $matches Regex matches.
	 * @return string The plain URL or the original match.
	 */
	private function maybe_convert_iframe_to_url( $matches ) {
		$url = html_entity_decode( $matches[1], ENT_QUOTES );
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! $host ) {
			return $matches[0];
		}

		foreach ( self::$embeddable_hosts as $allowed_host ) {
			if ( $host === $allowed_host ) {
				return "\n" . esc_url( self::convert_embed_url_to_watch_url( $url ) ) . "\n";
			}
		}

		return $matches[0];
	}

	/**
	 * Convert embed/player URLs to their canonical watch URLs.
	 *
	 * @param string $url The embed URL.
	 * @return string The canonical URL.
	 */
	private static function convert_embed_url_to_watch_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$path = wp_parse_url( $url, PHP_URL_PATH );

		// YouTube: /embed/VIDEO_ID -> https://www.youtube.com/watch?v=VIDEO_ID
		if ( in_array( $host, array( 'youtube.com', 'www.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com' ), true ) ) {
			if ( preg_match( '#^/embed/([a-zA-Z0-9_-]+)#', $path, $m ) ) {
				return 'https://www.youtube.com/watch?v=' . $m[1];
			}
		}

		// Vimeo: /video/VIDEO_ID -> https://vimeo.com/VIDEO_ID
		if ( in_array( $host, array( 'player.vimeo.com' ), true ) ) {
			if ( preg_match( '#^/video/(\d+)#', $path, $m ) ) {
				return 'https://vimeo.com/' . $m[1];
			}
		}

		return $url;
	}
}
