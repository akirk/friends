<?php
/**
 * Friends Feed Parser
 *
 * This contains the reference implementation for a parser.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This class describes a friends feed parser.
 */
abstract class Feed_Parser {
	/**
	 * Get the badge to display for this parser type.
	 *
	 * Return an array with badge info:
	 *  array(
	 *      'label' => 'RSS',          // Short text to display
	 *      'color' => '#ee802f',      // Background color
	 *      'title' => 'RSS/Atom Feed' // Tooltip text
	 *  )
	 *
	 * @return array|null Badge info array, or null for no badge.
	 */
	public function get_badge() {
		return null;
	}

	/**
	 * Determines if this is a supported feed and to what degree we feel it's supported.
	 *
	 * @param      string      $url        The url.
	 * @param      string      $mime_type  The mime type.
	 * @param      string      $title      The title.
	 * @param      string|null $content    The content, it can't be assumed that it's always available.
	 *
	 * @return     int  Return 0 if unsupported, a positive value representing the confidence for the feed, use 10 if you're reasonably confident.
	 */
	public function feed_support_confidence( $url, $mime_type, $title, $content = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return 0;
	}

	/**
	 * Allow augmenting or modifying the details of a feed.
	 *
	 * The incoming $feed_details array looks like this:
	 *
	 *  $feed_details = array(
	 *      'url'         => 'https://url.of/the/feed',
	 *      'title'       => 'Title from the <link> tag if any',
	 *      'mime-type'   => 'mime-type from the <link> tag if any',
	 *      // You can add these fields in the response:
	 *      'autoselect'  => true|false,
	 *      'post-format' => 'standard', // or 'aside', etc. see get_post_format_strings() of WordPress core
	 *  );
	 *
	 * @param      array $feed_details  The feed details.
	 *
	 * @return     array  The (potentially) modified feed details.
	 */
	public function update_feed_details( $feed_details ) {
		return $feed_details;
	}

	/**
	 * Discover the feeds available at the URL specified.
	 *
	 * The content for the URL has already been fetched for you which can be analyzed.
	 *
	 * Return an array of supported feeds in the format of the $feed_details above:
	 *
	 *  return array(
	 *      array(
	 *          'url'       => 'https://url.of/the/feed',
	 *          'title'     => 'Title for the feed',
	 *          'mime-type' => 'mime-type for the feed',
	 *          'rel'       => 'e.g. alternate',
	 *      ),
	 *  );
	 *
	 * @param      string $content  The content for the URL is already provided here.
	 * @param      string $url      The url to search.
	 *
	 * @return     array  A list of supported feeds at the URL.
	 */
	public function discover_available_feeds( $content, $url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return array();
	}

	/**
	 * Strip tracking parameters from a URL.
	 *
	 * @param      string $url  The URL to clean.
	 *
	 * @return     string  The URL without tracking parameters.
	 */
	public static function strip_tracking_parameters( $url ) {
		$parsed = wp_parse_url( $url );
		if ( ! $parsed || empty( $parsed['query'] ) ) {
			return $url;
		}

		parse_str( $parsed['query'], $params );

		/**
		 * Filter the list of tracking parameter prefixes to strip from URLs.
		 *
		 * @param array $prefixes List of parameter prefixes to strip (e.g., 'utm_' matches utm_source, utm_medium, etc.).
		 */
		$tracking_prefixes = apply_filters(
			'friends_tracking_parameter_prefixes',
			array(
				'utm_',      // Google Analytics.
				'mtm_',      // Matomo.
				'pk_',       // Piwik/Matomo.
			)
		);

		/**
		 * Filter the list of exact tracking parameters to strip from URLs.
		 *
		 * @param array $params List of exact parameter names to strip.
		 */
		$tracking_params = apply_filters(
			'friends_tracking_parameters',
			array(
				'fbclid',    // Facebook.
				'gclid',     // Google Ads.
				'gclsrc',    // Google Ads.
				'dclid',     // Google Display Network.
				'gbraid',    // Google Ads (iOS).
				'wbraid',    // Google Ads (web-to-app).
				'msclkid',   // Microsoft Ads.
				'twclid',    // Twitter.
				'igshid',    // Instagram.
				'mc_eid',    // Mailchimp.
				'mc_cid',    // Mailchimp.
				'oly_enc_id', // Omeda.
				'oly_anon_id', // Omeda.
				'vero_id',   // Vero.
				'_hsenc',    // HubSpot.
				'_hsmi',     // HubSpot.
				'hsCtaTracking', // HubSpot.
				'ref',       // Generic referrer.
				'ref_src',   // Generic referrer.
				'ref_url',   // Generic referrer.
			)
		);

		$cleaned_params = array();
		foreach ( $params as $key => $value ) {
			$is_tracking = false;

			// Check exact matches.
			if ( in_array( $key, $tracking_params, true ) ) {
				$is_tracking = true;
			}

			// Check prefix matches.
			if ( ! $is_tracking ) {
				foreach ( $tracking_prefixes as $prefix ) {
					if ( str_starts_with( $key, $prefix ) ) {
						$is_tracking = true;
						break;
					}
				}
			}

			if ( ! $is_tracking ) {
				$cleaned_params[ $key ] = $value;
			}
		}

		// Rebuild the URL.
		$clean_url = '';
		if ( ! empty( $parsed['scheme'] ) ) {
			$clean_url .= $parsed['scheme'] . '://';
		}
		if ( ! empty( $parsed['host'] ) ) {
			if ( ! empty( $parsed['user'] ) ) {
				$clean_url .= $parsed['user'];
				if ( ! empty( $parsed['pass'] ) ) {
					$clean_url .= ':' . $parsed['pass'];
				}
				$clean_url .= '@';
			}
			$clean_url .= $parsed['host'];
			if ( ! empty( $parsed['port'] ) ) {
				$clean_url .= ':' . $parsed['port'];
			}
		}
		if ( ! empty( $parsed['path'] ) ) {
			$clean_url .= $parsed['path'];
		}
		if ( ! empty( $cleaned_params ) ) {
			$clean_url .= '?' . http_build_query( $cleaned_params );
		}
		if ( ! empty( $parsed['fragment'] ) ) {
			$clean_url .= '#' . $parsed['fragment'];
		}

		return $clean_url;
	}

	/**
	 * Convert relative URLs to absolute ones in incoming content.
	 *
	 * @param      string $html   The html.
	 * @param      string $permalink  The permalink of the feed.
	 *
	 * @return     string  The HTML with URLs replaced to their absolute represenation.
	 */
	public function convert_relative_urls_to_absolute_urls( $html, $permalink ) {
		if ( ! $html ) {
			$html = '';
		}

		// Strip off the hash.
		$permalink = strtok( $permalink, '#' );

		// For now this only converts links and image srcs.
		return preg_replace_callback(
			'~(src|href)=(?:"([^"]+)|\'([^\']+))~i',
			function ( $m ) use ( $permalink ) {
				// Don't update hash-only links.
				if ( str_starts_with( $m[2], '#' ) ) {
					return $m[0];
				}

				// Remove absolute URL from hashes so that it can become relative.
				if ( str_starts_with( $m[2], $permalink . '#' ) ) {
					return str_replace( $permalink, '', $m[0] );
				}

				// Don't convert content URLs like data:image/png;base64, etc.
				if ( str_starts_with( $m[2], 'data:' ) ) {
					return $m[0];
				}

				// Convert relative URLs to absolute ones and strip tracking parameters.
				$absolute_url = Mf2\resolveUrl( $permalink, $m[2] );
				$clean_url = self::strip_tracking_parameters( $absolute_url );
				return str_replace( $m[2], $clean_url, $m[0] );
			},
			$html
		);
	}
}
