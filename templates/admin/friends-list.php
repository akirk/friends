<?php
/**
 * This template shows the latest friends section.
 *
 * @version 1.0
 * @package Friends
 */

if ( empty( $args['friends'] ) ) {
	return;
}

?>
<h3><?php esc_html_e( 'Your Friends & Subscriptions', 'friends' ); ?></h3>
<table class="wp-list-table widefat fixed striped" style="margin-top: 2em; margin-right: 1em">
	<thead>
		<tr>
			<th style="width: 18em" class="column-primary column-site"><?php esc_html_e( 'Site', 'friends' ); ?></th>
			<th style="width: 15em" class="column-date"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Date' ); ?></th>
			<th class="column-status"><?php esc_html_e( 'Status', 'friends' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $args['friends'] as $friend_user ) : ?>
		<tr>
			<td class="site column-site column-primary" data-colname="<?php esc_attr_e( 'Site', 'friends' ); ?>">
				<a href="<?php echo esc_url( apply_filters( 'get_edit_user_link', $friend_user->user_url, $friend_user->ID ) ); ?>"><?php echo esc_html( $friend_user->display_name ); ?></a>
				<button type="button" class="toggle-row"><span class="screen-reader-text"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Show more details' ); ?></span></button>
			</td>
			<td class="date column-date" data-colname="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Date' ); ?>"><?php echo esc_html( date_i18n( __( 'F j, Y g:i a' ), strtotime( $friend_user->user_registered ) ) ); ?></td>
			<td class="status column-status" data-colname="<?php esc_attr_e( 'Status', 'friends' ); ?>"><?php echo esc_html( $friend_user->get_role_name() ); ?></td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
</div>
