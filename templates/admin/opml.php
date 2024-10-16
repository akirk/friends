<?php
/**
 * This template generates the OPML to read your friend posts in a feed reader.
 *
 * @version 1.0
 * @package Friends
 */

header( 'Content-Disposition: attachment; filename=' . $args['filename'] );
header( 'Content-Type: text/opml+xml' );

echo '<' . '?xml version="1.0" encoding="utf-8"?' . '>';

?>
<opml version="2.0">
	<head>
		<title><?php echo esc_html( $args['title'] ); ?></title>
		<dateCreated><?php echo esc_html( gmdate( 'r' ) ); ?></dateCreated>
		<ownerName><?php echo esc_html( wp_get_current_user()->display_name ); ?></ownerName>
	</head>
	<body>
<?php
foreach ( $args['feeds'] as $_role => $users ) {
	?>
	<outline text="<?php echo esc_attr( $_role ); ?>">
<?php foreach ( $users as $user ) : /* phpcs:ignore Generic.WhiteSpace.ScopeIndent.Incorrect */ ?>
<?php foreach ( $user['feeds'] as $feed ) : /* phpcs:ignore Generic.WhiteSpace.ScopeIndent.Incorrect */ ?>
		<outline text="<?php echo esc_attr( $user['friend_user']->display_name ); ?>" htmlUrl="<?php echo esc_url( $feed['html_url'] ); ?>" title="<?php echo esc_attr( $feed['title'] ); ?>" type="<?php echo esc_attr( $feed['type'] ); ?>" xmlUrl="<?php echo esc_url( $feed['xml_url'] ); ?>"/>
<?php endforeach; ?>
<?php endforeach; ?>
	</outline>
<?php } ?>
	</body>
</opml>
