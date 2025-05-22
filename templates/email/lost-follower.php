<?php
/**
 * This template contains the HTML for the New Follower notification e-mail.
 *
 * @version 1.0
 * @package Friends
 */

?>
<p>
	<?php
	// translators: %s is a user display name.
		echo esc_html( sprintf( __( 'Hi %s!', 'friends' ), $args['user']->display_name ) );
	?>
</p>

<p>
	<?php
	esc_html_e( 'Sorry to inform you that you lost a follower:', 'friends' );
	?>
</p>

<table>
	<tr>
		<td style="vertical-align: top">
			<a href="<?php echo esc_url( $args['follower']->get_url() ); ?>" style="float: left; margin-right: 1em;">
				<img src="<?php echo esc_url( $args['follower']->get_icon_url() ); /* phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage */ ?>" alt="<?php echo esc_attr( $args['follower']->get_name() ); ?>" width="64" height="64">
			</a>
		</td>
		<td>

			<a href="<?php echo esc_url( $args['follower']->get_url() ); ?>">
				<strong><?php echo esc_html( $args['follower']->get_name() ); ?></strong> (<?php echo esc_html( $args['follower']->get_preferred_username() . '@' . $args['server'] ); ?>)
			</a>
			<br>
			<?php
			if ( $args['follower']->get_summary() ) {
				echo wp_kses_post( nl2br( $args['follower']->get_summary() ) );
			}
			?>
		</td>
	</tr>
</table>

<p>
	<?php
	// translators: %s is a time duration.
	echo esc_html( sprintf( __( 'They have been following you for: %s', 'friends' ), $args['duration'] ) );
	?>
</p>


