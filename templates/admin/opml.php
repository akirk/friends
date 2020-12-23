<?php
/**
 * This template generates the OPML to read your friend posts in a feed reader.
 *
 * @package Friends
 */

header( 'Content-Disposition: attachment; filename=friends-' . wp_get_current_user()->user_login . '.opml' );
header( 'Content-Type: text/opml+xml' );

echo '<' . '?xml version="1.0" encoding="utf-8"?' . '>';

$users = array();
foreach ( $friends->get_results() as $friend_user ) {
	$role = $friend_user->get_role_name( true, 9 );
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
		$feeds = $friend_user->get_active_feeds();

		$need_local_feed = false;

		foreach ( $feeds as $k => $feed ) {
			switch ( $feed->get_mime_type() ) {
				case 'application/atom+xml':
				case 'application/atomxml':
				case 'application/rss+xml':
				case 'application/rssxml':
					break;
				default:
					$need_local_feed = true;
					break 2;
			}
		}

		if ( $need_local_feed ) {
			$feeds = array_slice( $feeds, 0, 1 );
		}

		foreach ( $feeds as $feed ) {
			$type = 'rss';
			if ( $need_local_feed ) {
				$xml_url = $feed->get_local_url() . '?auth=' . $_GET['auth'];
				$title = $friend_user->display_name;
			} else {
				$xml_url = $feed->get_private_url( YEAR_IN_SECONDS );
				if ( 'application/atom+xml' === $feed->get_mime_type() ) {
					$type = 'atom';
				}
			}
			?>
			<outline text="<?php echo esc_attr( $friend_user->display_name ); ?>" htmlUrl="<?php echo esc_url( $feed->get_local_html_url() ); ?>" title="<?php echo esc_attr( $title ); ?>" type="<?php echo esc_attr( $type ); ?>" xmlUrl="<?php echo esc_url( $xml_url ); ?>"/>
			<?php
		}
	}
	?>
		</outline>
<?php endforeach; ?>
	</body>
</opml>
