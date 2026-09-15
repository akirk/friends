<?php
/**
 * Blockroll integration.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Provides Friends subscriptions as a Blockroll source.
 */
class Blockroll {
	const HIDE_META = 'hide_from_blockroll';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'blockroll_sources', array( __CLASS__, 'sources' ) );
		add_filter( 'blockroll_source_links', array( __CLASS__, 'source_links' ), 10, 2 );
		add_action( 'friends_subscription_actions', array( __CLASS__, 'subscription_action' ) );
		add_action( 'wp_ajax_friends-blockroll-visibility', array( __CLASS__, 'ajax_visibility' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_script' ), 20 );
	}

	/**
	 * Register the Friends subscriptions source.
	 *
	 * @param array $sources Source slug => source label.
	 * @return array Sources.
	 */
	public static function sources( $sources ) {
		$sources['friends-subscriptions'] = __( 'Friends subscriptions', 'friends' );
		return $sources;
	}

	/**
	 * Provide Friends subscriptions as Blockroll links.
	 *
	 * @param array  $links  Current links.
	 * @param string $source Selected source.
	 * @return array Links.
	 */
	public static function source_links( $links, $source ) {
		if ( 'friends-subscriptions' !== $source ) {
			return $links;
		}

		$links = array();
		foreach ( User_Query::all_subscriptions()->get_results() as $subscription ) {
			if ( ! $subscription->user_url || self::is_hidden( $subscription ) ) {
				continue;
			}

			$links[] = array(
				'url'         => $subscription->user_url,
				'name'        => $subscription->display_name ? $subscription->display_name : $subscription->user_login,
				'description' => $subscription->description,
				'feedUrl'     => self::feed_url( $subscription ),
				'photo'       => $subscription->get_avatar_url(),
				'xfn'         => array(),
				'added'       => $subscription->user_registered,
			);
		}

		return $links;
	}

	/**
	 * Render the Blockroll visibility toggle for a subscription.
	 *
	 * @param User $subscription Friend user or virtual subscription.
	 */
	public static function subscription_action( User $subscription ) {
		if ( ! self::is_available() || ! $subscription instanceof Subscription ) {
			return;
		}

		$hidden = self::is_hidden( $subscription );
		?>
		<span class="subscription-actions">
			<label class="subscription-action subscription-blockroll-visibility">
				<input
					type="checkbox"
					class="friends-blockroll-visibility"
					value="1"
					data-id="<?php echo esc_attr( $subscription->user_login ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-blockroll-visibility-' . $subscription->user_login ) ); ?>"
					<?php checked( $hidden ); ?>
				/>
				<span><?php echo esc_html( self::visibility_label( $hidden ) ); ?></span>
			</label>
		</span>
		<?php
	}

	/**
	 * Enqueue the front-end behavior for the Blockroll visibility toggle.
	 */
	public static function enqueue_script() {
		if ( ! self::is_available() || ! Friends::on_frontend() ) {
			return;
		}

		wp_add_inline_script(
			'friends',
			<<<'JS'
( function ( $, wp ) {
	$( document ).on( 'change', '.friends-blockroll-visibility', function () {
		const input = $( this );
		const label = input.closest( 'label' ).find( 'span' );
		const previous = ! input.prop( 'checked' );

		input.prop( 'disabled', true );
		wp.ajax.send( 'friends-blockroll-visibility', {
			data: {
				friend_id: input.data( 'id' ),
				hidden: input.prop( 'checked' ) ? 1 : 0,
				_ajax_nonce: input.data( 'nonce' ),
			},
			success( response ) {
				input.prop( 'checked', !! response.hidden );
				label.text( response.label );
			},
			error() {
				input.prop( 'checked', previous );
			},
		} ).always( function () {
			input.prop( 'disabled', false );
		} );
	} );
} )( jQuery, wp );
JS
		);
	}

	/**
	 * Ajax handler to hide or show a subscription in Blockroll.
	 */
	public static function ajax_visibility() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) || ! self::is_available() || ! isset( $_POST['friend_id'] ) || ! isset( $_POST['hidden'] ) ) {
			wp_send_json_error();
			exit;
		}

		$friend_id = sanitize_text_field( wp_unslash( $_POST['friend_id'] ) );
		check_ajax_referer( "friends-blockroll-visibility-$friend_id" );

		$friend_user = User::get_by_username( $friend_id );
		if ( ! $friend_user || is_wp_error( $friend_user ) || ! ( $friend_user instanceof Subscription ) ) {
			wp_send_json_error( 'invalid-user' );
			exit;
		}

		self::set_hidden( $friend_user, boolval( $_POST['hidden'] ) );
		$hidden = self::is_hidden( $friend_user );

		wp_send_json_success(
			array(
				'hidden' => $hidden,
				'label'  => self::visibility_label( $hidden ),
			)
		);
	}

	/**
	 * Get a representative feed URL for a subscription.
	 *
	 * @param User $subscription Friend user or virtual subscription.
	 * @return string Feed URL.
	 */
	private static function feed_url( User $subscription ) {
		foreach ( $subscription->get_active_feeds() as $feed ) {
			return $feed->get_url();
		}

		foreach ( $subscription->get_feeds() as $feed ) {
			return $feed->get_url();
		}

		return '';
	}

	/**
	 * Whether Blockroll is available.
	 *
	 * @return bool True if Blockroll is active.
	 */
	public static function is_available() {
		return defined( 'BLOCKROLL_PLUGIN_FILE' );
	}

	/**
	 * Get the label for the Blockroll visibility toggle.
	 *
	 * @param bool $hidden Whether the subscription is hidden from Blockroll.
	 * @return string Toggle label.
	 */
	private static function visibility_label( $hidden ) {
		return $hidden ? __( 'Hidden from Blogroll', 'friends' ) : __( 'Hide from Blogroll', 'friends' );
	}

	/**
	 * Whether a subscription is hidden from Blockroll for the current user.
	 *
	 * @param User $subscription Friend user or virtual subscription.
	 * @return bool True if hidden from Blockroll.
	 */
	public static function is_hidden( User $subscription ) {
		if ( ! $subscription instanceof Subscription ) {
			return false;
		}

		return (bool) get_term_meta( $subscription->get_term_id(), self::HIDE_META, true );
	}

	/**
	 * Set whether a subscription is hidden from Blockroll.
	 *
	 * @param User $subscription Friend user or virtual subscription.
	 * @param bool $hide         Whether to hide the subscription.
	 */
	public static function set_hidden( User $subscription, $hide ) {
		if ( ! $subscription instanceof Subscription ) {
			return;
		}

		if ( $hide ) {
			update_term_meta( $subscription->get_term_id(), self::HIDE_META, true );
			return;
		}

		delete_term_meta( $subscription->get_term_id(), self::HIDE_META );
	}
}
