<?php
/**
 * A script to generate the most popular emojis.
 *
 * @package Friends
 */

$rankings_map = array();
$out          = array();
$popular      = array();
$emojis       = file_get_contents( 'https://raw.githubusercontent.com/iamcal/emoji-data/master/emoji.json' );
$rankings     = file_get_contents( 'http://www.emojitracker.com/api/rankings' );

foreach ( json_decode( $rankings ) as $emoji ) {
	$rankings_map[ '&#x' . strtoupper( $emoji->id ) . ';' ] = intval( $emoji->score );
}

foreach ( json_decode( $emojis ) as $emoji ) {
	foreach ( $emoji->short_names as $short_name ) {
		if ( in_array( $short_name, array( '+1', '-1' ), true ) ) {
			continue;
		}
		$html = '&#x' . str_replace( '-', ';&#x', $emoji->unified ) . ';';
		if ( isset( $rankings_map[ $html ] ) ) {
			$popular[ $short_name ] = $html;
		}
	}
}

uasort(
	$popular, function ( $a, $b ) use ( $rankings_map ) {
		if ( isset( $rankings_map[ $a ] ) ) {
			$a = $rankings_map[ $a ];
		} else {
			$a = 0;
		}
		if ( isset( $rankings_map[ $b ] ) ) {
			$b = $rankings_map[ $b ];
		} else {
			$b = 0;
		}
		if ( $a === $b ) {
			return 0;
		}
		return ( $a > $b ) ? -1 : 1;
	}
);
$popular = array_slice( $popular, 0, 100 );

file_put_contents( __DIR__ . '/../emojis.json', json_encode( $popular, JSON_PRETTY_PRINT ) );
