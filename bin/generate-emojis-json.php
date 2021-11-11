<?php
/**
 * A script to generate the most popular emojis.
 *
 * @package Friends
 */

if ( 'cli' !== php_sapi_name() ) {
	fwrite( STDERR, "Must run from CLI.\n" );
	exit( 1 );
}

$pre_selected = array( '👍', '❤️', '😂', '😍', '😭', '😊', '😩', '😁', '👎' );
$out = array();
$cache = __DIR__ . '/../emoji-rankings.json';
$popular = json_decode( file_get_contents( file_exists( $cache ) ? $cache : 'https://api.emojitracker.com/v1/rankings' ) );
foreach ( $pre_selected as $emoji ) {
	foreach ( $popular as $data ) {
		if ( $emoji === $data->char ) {
			$data->id = strtolower( $data->id );
			$out[ $data->id ] = array(
				'char' => $data->char,
				'name' => $data->name,
			);
		}
	}
}
foreach ( $popular as $data ) {
	$data->id = strtolower( $data->id );
	if ( ! isset( $out[ $data->id ] ) ) {
		$out[ $data->id ] = array(
			'char' => $data->char,
			'name' => $data->name,
		);
	}
	if ( count( $out ) >= 250 ) {
		break;
	}
}

file_put_contents( __DIR__ . '/../emojis.json', json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
