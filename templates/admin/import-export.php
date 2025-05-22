<?php
/**
 * This template contains the Friends Settings.
 *
 * @package Friends
 */

do_action( 'friends_settings_before_form' );

?>
<form method="post" enctype="multipart/form-data">
	<?php wp_nonce_field( 'friends-settings' ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row" rowspan="2"><?php esc_html_e( 'Feed Reader', 'friends' ); ?></th>
				<td>
					<span>
					<?php
					// translators: %s is a URL.
					echo wp_kses( sprintf( __( 'Download the <a href=%s>Private OPML file (contains private urls!)</a> and import it to your feed reader.', 'friends' ), esc_url( home_url( '/friends/opml/?auth=' . $args['private_rss_key'] ) ) ), array( 'a' => array( 'href' => array() ) ) );
					?>
					</span>
					<span>
					<?php
					// translators: %s is a URL.
					echo wp_kses( sprintf( __( 'Alternative: <a href=%s>Public OPML file (only public urls)</a>.', 'friends' ), esc_url( home_url( '/friends/opml/?public' ) ) ), array( 'a' => array( 'href' => array() ) ) );
					?>
					</span>
					<p class="description">
					<?php
					esc_html_e( 'If your feed reader supports it, you can also subscribe to this URL as the OPML file gets updated as you add or remove friends.', 'friends' );
					?>
					</p>
				</td>
			</tr>
			<tr>
				<td>
					<span>
					<?php
					// translators: %s is a URL.
					echo wp_kses( sprintf( __( 'You can also subscribe to a <a href=%s>compiled RSS feed of friend posts</a>.', 'friends' ), esc_url( home_url( '/friends/feed/?auth=' . $args['private_rss_key'] ) ) ), array( 'a' => array( 'href' => array() ) ) );
					?>
					</span>
					<p class="description">
					<?php
					esc_html_e( 'Please be careful what you do with these feeds as they might contain private posts of your friends.', 'friends' );
					?>
					</p>

				</td>
			</tr>

			<tr>
				<th>Import OPML</th>
				<td>
					<input type="file" name="opml" id="opml" accept=".opml" />
					<p class="description">
					<?php
					esc_html_e( 'Import an OPML file to import feeds.', 'friends' );
					?>
					</p>
				</td>
			</tr>
		</tbody>
	</table>
	<?php do_action( 'friends_settings_form_bottom' ); ?>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
	</p>
</form>
<?php
do_action( 'friends_settings_after_form' );
