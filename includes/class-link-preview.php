<?php
/**
 * Friends Link Preview
 *
 * This contains the functions for fetching and displaying link previews.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the link previews of the Friends Plugin.
 *
 * Incoming posts that just contain a link (for example Mastodon statuses that
 * point to a news article) are enriched with the Open Graph metadata of that
 * link so that a preview card can be displayed alongside the post.
 *
 * @since 4.3
 *
 * @package Friends
 * @author Alex Kirk
 */
class Link_Preview {
	const META         = 'link_preview';
	const META_CHECKED = 'link_preview_checked';
	const CRON_HOOK    = 'friends_fetch_link_preview';

	/**
	 * How long to wait before looking at a post again that had no preview.
	 */
	const RETRY_AFTER = WEEK_IN_SECONDS;

	/**
	 * How much of the remote document to download while looking for meta tags.
	 */
	const MAX_RESPONSE_SIZE = 300000;

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
		add_action( 'friends_retrieved_new_posts', array( $this, 'queue_new_posts' ), 10, 2 );
		add_action( self::CRON_HOOK, array( $this, 'fetch_link_preview' ) );
	}

	/**
	 * Whether link previews should be fetched and displayed.
	 *
	 * @return     bool  True if enabled.
	 */
	public static function is_enabled() {
		return ! get_option( 'friends_disable_link_previews' );
	}

	/**
	 * Queue newly retrieved posts for a link preview lookup.
	 *
	 * @param      User_Feed $user_feed     The user feed the posts came from.
	 * @param      array     $new_post_ids  The ids of the newly created posts.
	 */
	public function queue_new_posts( $user_feed, $new_post_ids ) {
		if ( ! self::is_enabled() || ! is_array( $new_post_ids ) ) {
			return;
		}

		foreach ( $new_post_ids as $post_id ) {
			self::queue( $post_id );
		}
	}

	/**
	 * Schedule a link preview lookup for a post.
	 *
	 * @param      int $post_id  The post id.
	 */
	public static function queue( $post_id ) {
		$post_id = intval( $post_id );
		if ( ! $post_id ) {
			return;
		}

		if ( wp_next_scheduled( self::CRON_HOOK, array( $post_id ) ) ) {
			return;
		}

		wp_schedule_single_event( time(), self::CRON_HOOK, array( $post_id ) );
	}

	/**
	 * Cron callback: look up the link preview for a post.
	 *
	 * @param      int $post_id  The post id.
	 */
	public function fetch_link_preview( $post_id ) {
		self::update_link_preview( $post_id );
	}

	/**
	 * Get the link preview for a post, scheduling a lookup if we don't have one yet.
	 *
	 * @param      \WP_Post|int|null $post   The post (defaults to the current post).
	 *
	 * @return     array|false       The link preview data or false if there is none.
	 */
	public static function get_for_post( $post = null ) {
		if ( ! self::is_enabled() ) {
			return false;
		}

		$post = get_post( $post );
		if ( ! $post ) {
			return false;
		}

		$preview = get_post_meta( $post->ID, self::META, true );
		if ( is_array( $preview ) && ! empty( $preview['url'] ) ) {
			/**
			 * Filters the link preview data before it is displayed.
			 *
			 * @param array    $preview  The link preview data.
			 * @param \WP_Post $post     The post the preview belongs to.
			 */
			return apply_filters( 'friends_link_preview', $preview, $post );
		}

		$checked = intval( get_post_meta( $post->ID, self::META_CHECKED, true ) );
		if ( ( ! $checked || $checked < time() - self::RETRY_AFTER ) && self::is_supported_post( $post ) ) {
			self::queue( $post->ID );
		}

		return false;
	}

	/**
	 * Whether a post is a candidate for a link preview.
	 *
	 * @param      \WP_Post $post   The post.
	 *
	 * @return     bool     True if a preview could be shown for this post.
	 */
	public static function is_supported_post( $post ) {
		if ( ! in_array( $post->post_type, apply_filters( 'friends_frontend_post_types', array() ), true ) ) {
			return false;
		}

		$post_format = get_post_format( $post );
		if ( ! $post_format ) {
			$post_format = 'standard';
		}

		/**
		 * Filters the post formats for which link previews are shown.
		 *
		 * @param array    $post_formats  The post formats.
		 * @param \WP_Post $post          The post.
		 */
		$post_formats = apply_filters( 'friends_link_preview_post_formats', array( 'status', 'link', 'aside' ), $post );

		return in_array( $post_format, $post_formats, true );
	}

	/**
	 * Look up the link preview for a post and store it in post meta.
	 *
	 * @param      int  $post_id  The post id.
	 * @param      bool $force    Whether to look it up even if we already did.
	 *
	 * @return     array|false  The link preview data or false if there is none.
	 */
	public static function update_link_preview( $post_id, $force = false ) {
		if ( ! self::is_enabled() ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post || ! self::is_supported_post( $post ) ) {
			return false;
		}

		if ( ! $force ) {
			$checked = intval( get_post_meta( $post->ID, self::META_CHECKED, true ) );
			if ( $checked && $checked > time() - self::RETRY_AFTER ) {
				$preview = get_post_meta( $post->ID, self::META, true );
				return is_array( $preview ) && ! empty( $preview['url'] ) ? $preview : false;
			}
		}

		update_post_meta( $post->ID, self::META_CHECKED, time() );

		$url = self::extract_url( $post );
		if ( ! $url ) {
			update_post_meta( $post->ID, self::META, array() );
			return false;
		}

		$preview = self::fetch( $url );
		if ( is_wp_error( $preview ) ) {
			do_action( 'friends_link_preview_failed', $url, $preview, $post );
			update_post_meta( $post->ID, self::META, array() );
			return false;
		}

		update_post_meta( $post->ID, self::META, $preview );

		return $preview;
	}

	/**
	 * Find the URL in a post that a preview should be shown for.
	 *
	 * Mirrors what Mastodon does: the first link in the post that is not a
	 * mention or a hashtag, and only if the post doesn't already show media.
	 *
	 * @param      \WP_Post $post   The post.
	 *
	 * @return     string|false  The url or false if there is none.
	 */
	public static function extract_url( $post ) {
		$content = $post->post_content;

		if ( preg_match( '/<(?:img|video|audio|iframe|embed|object)[\s\/>]/i', $content ) ) {
			// The post already displays media, no need for a preview card.
			return false;
		}

		if ( ! preg_match_all( '/<a\s((?>[^>"\']+|"[^"]*"|\'[^\']*\')*)>/i', $content, $matches ) ) {
			return false;
		}

		$own_host = wp_parse_url( $post->guid, PHP_URL_HOST );

		foreach ( $matches[1] as $attributes ) {
			if ( ! preg_match( '/\bhref\s*=\s*(["\'])(.*?)\1/is', $attributes, $m ) ) {
				continue;
			}
			$url = html_entity_decode( trim( $m[2] ), ENT_QUOTES, get_bloginfo( 'charset' ) );

			if ( preg_match( '/\bclass\s*=\s*(["\'])(.*?)\1/is', $attributes, $m ) ) {
				$classes = preg_split( '/\s+/', strtolower( $m[2] ) );
				if ( array_intersect( $classes, array( 'mention', 'hashtag', 'u-url' ) ) ) {
					continue;
				}
			}

			if ( preg_match( '/\brel\s*=\s*(["\'])(.*?)\1/is', $attributes, $m ) ) {
				$rels = preg_split( '/\s+/', strtolower( $m[2] ) );
				if ( array_intersect( $rels, array( 'tag', 'author' ) ) ) {
					continue;
				}
			}

			$parsed_url = wp_parse_url( $url );
			if ( ! isset( $parsed_url['host'] ) || ! isset( $parsed_url['scheme'] ) ) {
				continue;
			}
			if ( ! in_array( strtolower( $parsed_url['scheme'] ), array( 'http', 'https' ), true ) ) {
				continue;
			}
			if ( $own_host && strtolower( $parsed_url['host'] ) === strtolower( $own_host ) ) {
				// Don't preview the post itself or its neighbors on the same site.
				continue;
			}
			if ( preg_match( '/\.(?:jpe?g|png|gif|webp|avif|svg|mp4|webm|mp3|ogg|pdf)$/i', $parsed_url['path'] ?? '' ) ) {
				continue;
			}

			/**
			 * Filters the URL a link preview will be fetched for.
			 *
			 * Return false to not show a link preview for this post.
			 *
			 * @param string   $url   The url.
			 * @param \WP_Post $post  The post.
			 */
			return apply_filters( 'friends_link_preview_url', $url, $post );
		}

		return false;
	}

	/**
	 * Download a URL and extract its Open Graph metadata.
	 *
	 * @param      string $url    The url.
	 *
	 * @return     array|\WP_Error  The link preview data or an error.
	 */
	public static function fetch( $url ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 10,
				'redirection'         => 3,
				'limit_response_size' => self::MAX_RESPONSE_SIZE,
				'headers'             => array(
					'Accept' => 'text/html,application/xhtml+xml',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code >= 300 ) {
			return new \WP_Error( 'friends_link_preview_http_error', wp_remote_retrieve_response_message( $response ), $response_code );
		}

		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		if ( $content_type && false === stripos( $content_type, 'html' ) ) {
			return new \WP_Error( 'friends_link_preview_not_html', $content_type );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! $body ) {
			return new \WP_Error( 'friends_link_preview_empty_body' );
		}

		return self::parse( $body, self::get_final_url( $response, $url ), $url );
	}

	/**
	 * Get the URL a request ended up at after following redirects.
	 *
	 * @param      array  $response  The response of wp_safe_remote_get.
	 * @param      string $url       The url that was requested.
	 *
	 * @return     string  The final url.
	 */
	private static function get_final_url( $response, $url ) {
		if ( ! isset( $response['http_response'] ) || ! is_object( $response['http_response'] ) ) {
			return $url;
		}
		if ( ! method_exists( $response['http_response'], 'get_response_object' ) ) {
			return $url;
		}
		$response_object = $response['http_response']->get_response_object();
		if ( isset( $response_object->url ) && $response_object->url ) {
			return $response_object->url;
		}

		return $url;
	}

	/**
	 * Extract the Open Graph metadata from an HTML document.
	 *
	 * @param      string $html       The HTML document.
	 * @param      string $final_url  The url the document was loaded from.
	 * @param      string $url        The url as it appeared in the post.
	 *
	 * @return     array|\WP_Error  The link preview data or an error.
	 */
	public static function parse( $html, $final_url, $url = null ) {
		if ( is_null( $url ) ) {
			$url = $final_url;
		}

		$head = $html;
		if ( preg_match( '/<head\b[^>]*>(.*?)<\/head>/is', $html, $m ) ) {
			$head = $m[1];
		}

		$meta = array();
		if ( preg_match_all( '/<meta\s((?>[^>"\']+|"[^"]*"|\'[^\']*\')*)\/?>/is', $head, $matches ) ) {
			foreach ( $matches[1] as $attributes ) {
				if ( ! preg_match( '/\b(?:property|name)\s*=\s*(["\'])\s*(.*?)\s*\1/is', $attributes, $m ) ) {
					continue;
				}
				$key = strtolower( $m[2] );
				if ( isset( $meta[ $key ] ) ) {
					continue;
				}
				if ( ! preg_match( '/\bcontent\s*=\s*(["\'])(.*?)\1/is', $attributes, $m ) ) {
					continue;
				}
				$meta[ $key ] = trim( html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ) );
			}
		}

		$title = self::first_of( $meta, array( 'og:title', 'twitter:title' ) );
		if ( ! $title && preg_match( '/<title\b[^>]*>(.*?)<\/title>/is', $head, $m ) ) {
			$title = trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) );
		}

		$description = self::first_of( $meta, array( 'og:description', 'twitter:description', 'description' ) );
		$image       = self::first_of( $meta, array( 'og:image:secure_url', 'og:image:url', 'og:image', 'twitter:image', 'twitter:image:src' ) );
		$site_name   = self::first_of( $meta, array( 'og:site_name', 'application-name' ) );

		if ( $image ) {
			$image = \WP_Http::make_absolute_url( $image, $final_url );
			$scheme = wp_parse_url( $image, PHP_URL_SCHEME );
			if ( ! in_array( strtolower( (string) $scheme ), array( 'http', 'https' ), true ) ) {
				$image = '';
			}
		}

		if ( ! $title && ! $description && ! $image ) {
			return new \WP_Error( 'friends_link_preview_no_metadata', $url );
		}

		$preview = array(
			'url'         => $url,
			'host'        => self::pretty_host( $final_url ),
			'title'       => self::shorten( sanitize_text_field( $title ), 200 ),
			'description' => self::shorten( sanitize_text_field( $description ), 400 ),
			'image'       => $image ? esc_url_raw( $image ) : '',
			'site_name'   => self::shorten( sanitize_text_field( $site_name ), 100 ),
		);

		foreach ( array( 'width', 'height' ) as $dimension ) {
			$value = isset( $meta[ 'og:image:' . $dimension ] ) ? intval( $meta[ 'og:image:' . $dimension ] ) : 0;
			if ( $value > 0 ) {
				$preview[ 'image_' . $dimension ] = $value;
			}
		}

		return $preview;
	}

	/**
	 * Get the first non-empty value of a list of keys.
	 *
	 * @param      array $meta   The metadata.
	 * @param      array $keys   The keys to look at, in order.
	 *
	 * @return     string  The value or an empty string.
	 */
	private static function first_of( $meta, $keys ) {
		foreach ( $keys as $key ) {
			if ( ! empty( $meta[ $key ] ) ) {
				return $meta[ $key ];
			}
		}

		return '';
	}

	/**
	 * Shorten a string to a maximum length.
	 *
	 * @param      string $text    The text.
	 * @param      int    $length  The maximum length.
	 *
	 * @return     string  The shortened text.
	 */
	private static function shorten( $text, $length ) {
		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		return trim( mb_substr( $text, 0, $length - 1 ) ) . '…';
	}

	/**
	 * Render the link preview card for the current post.
	 *
	 * @return     string  The HTML of the card or an empty string.
	 */
	public static function render() {
		if ( ! self::is_enabled() ) {
			return '';
		}

		ob_start();
		Friends::template_loader()->get_template_part( 'frontend/parts/link-preview', get_post_format(), array() );
		return ob_get_clean();
	}

	/**
	 * Get the hostname of a URL without a leading www.
	 *
	 * @param      string $url    The url.
	 *
	 * @return     string  The hostname.
	 */
	public static function pretty_host( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return '';
		}

		return preg_replace( '/^www\./i', '', $host );
	}
}
