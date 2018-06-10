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
		<dateCreated><?php echo esc_html( date( 'r' ) ); ?></dateCreated>
		<ownerName><?php echo esc_html( wp_get_current_user()->display_name ); ?></ownerName>
		</head>
	<body>
		<outline text="Friends">
		<?php foreach ( $friends->get_results() as $friend_user ) : ?>
			<outline text="<?php echo esc_url( $friend_user->display_name ); ?>" htmlUrl="<?php echo esc_url( $friend_user->user_url ); ?>" title="<?php echo esc_url( $friend_user->display_name ); ?>" type="rss" version="RSS2" xmlUrl="<?php echo esc_url( $feed->get_feed_url( $friend_user ) ); ?>"/>
		<?php endforeach; ?>
		</outline>
	</body>
</opml>
