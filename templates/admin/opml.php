<?php
/**
 * This template generates the OPML to read your friend posts in a feed reader.
 *
 * @package Friends
 */

header( 'Content-Disposition: attachment; filename=friends-' . wp_get_current_user()->user_login . '.opml' );
header( 'Content-Type: text/xml' );

echo '<' . '?xml version="1.0" encoding="utf-8"?' . '>';

$users = array();
foreach ( $friends->get_results() as $friend_user ) {
	$role = $friend_user->get_role_name( true );
	if ( ! isset( $users[ $role ] ) ) {
		$users[ $role ] = array();
	}
	$users[ $role ][] = $friend_user;
}
ksort( $users );
?>
<opml version="2.0">
	<head>
		<title><?php esc_html_e( 'My Friends', 'friends' ); ?></title>
		<dateCreated><?php echo esc_html( gmdate( 'r' ) ); ?></dateCreated>
		<ownerName><?php echo esc_html( wp_get_current_user()->display_name ); ?></ownerName>
	</head>
	<body>
<?php
foreach ( $users as $role => $friend_users ) :
	?>
		<outline text="<?php echo esc_attr( $role ); ?>">
	<?php
	foreach ( $friend_users as $friend_user ) {
		foreach ( $friend_user->get_active_feeds() as $feed ) {
			?>
			<outline text="<?php echo esc_attr( $friend_user->display_name ); ?>" htmlUrl="<?php echo esc_url( $feed->get_local_html_url() ); ?>" title="<?php echo esc_attr( $feed->get_title() ); ?>" type="rss" version="RSS2" xmlUrl="<?php echo esc_url( $feed->get_local_url() ); ?>?auth=<?php echo esc_attr( $_GET['auth'] ); ?>"/>
			<?php
		}
	}
	?>
		</outline>
<?php endforeach; ?>
	</body>
</opml>
