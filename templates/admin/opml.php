<?php
/**
 * This template generates the OPML to read your friend posts in a feed reader.
 *
 * @package Friends
 */

header( 'Content-Disposition: attachment; filename=friends-' . wp_get_current_user()->user_login . '.opml' );
header( 'Content-Type: text/xml' );

echo '<' . '?xml version="1.0" encoding="utf-8"?' . '>'
?>
<opml version="2.0">
	<head>
		<title><?php esc_html_e( 'My Friends', 'friends' ); ?></title>
		<dateCreated><?php echo esc_html( gmdate( 'r' ) ); ?></dateCreated>
		<ownerName><?php echo esc_html( wp_get_current_user()->display_name ); ?></ownerName>
		</head>
	<body>
		<outline text="Friends">
		<?php foreach ( $friends->get_results() as $friend_user ) : ?>
			<?php foreach ( $friend_user->get_active_feeds() as $feed ) : ?>
			<outline text="<?php echo esc_attr( $friend_user->display_name ); ?>" htmlUrl="<?php echo esc_url( $feed->get_html_url() ); ?>" title="<?php echo esc_attr( $feed->get_title() ); ?>" type="rss" version="RSS2" xmlUrl="<?php echo esc_url( $feed->get_local_url() ); ?>"/>
		<?php endforeach; ?>
		<?php endforeach; ?>
		</outline>
	</body>
</opml>
