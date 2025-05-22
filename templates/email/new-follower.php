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
	esc_html_e( 'You have a new follower:', 'friends' );
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
if ( $args['following'] ) {
	esc_html_e( 'You are already following them!', 'friends' );
	echo ' ';
	// translators: %s is a URL.
	echo wp_kses( sprintf( __( 'Go to their <a href="%s">friends page</a> to see what they recently posted about.', 'friends' ), esc_url( $args['following']->get_local_friends_page_url() ) ), array( 'a' => array( 'href' => array() ) ) );
} else {
	// translators: %s is a URL.
	echo wp_kses( sprintf( __( 'You can view their profile at %s', 'friends' ), '<a href="' . esc_url( $args['follower']->get_url() ) . '">' . esc_url( $args['follower']->get_url() ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) );
	echo '</p>';
	echo '<p>';
	// translators: %s is a URL.
	echo wp_kses( sprintf( __( 'Maybe you want to <a href="%s">follow them back</a>?', 'friends' ), esc_url( add_query_arg( 'url', $args['url'], admin_url( 'admin.php?page=add-friend' ) ) ) ), array( 'a' => array( 'href' => array() ) ) );
}
?>
</p>
