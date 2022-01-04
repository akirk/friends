<?php
/**
 * A script to generate a plugins.json to be used for the update functionality of the friends plugin.
 *
 * @package Friends
 */

// Use HEAD requests below.
stream_context_set_default(
	array(
		'http' => array(
			'method' => 'HEAD',
		),
	)
);

if ( 'cli' !== php_sapi_name() ) {
	fwrite( STDERR, "Must run from CLI.\n" );
	exit( 1 );
}

$json = array();
foreach ( glob( __DIR__ . '/../../friends-*', GLOB_ONLYDIR ) as $dir ) {
	$slug = basename( $dir );
	if ( ! file_exists( "$dir/$slug.php" ) ) {
		continue;
	}

	preg_match( '/^\s*\*\s+Version:\s*(.*)$/mi', file_get_contents( "$dir/$slug.php" ), $version );
	if ( ! $version ) {
		continue;
	}
	$version = $version[1];

	$readme_md = file_get_contents( "$dir/README.md" );

	$headline = strtok( $readme_md, PHP_EOL );
	$short_description_start = 2 + strlen( $headline );

	$name = trim( $headline, ' #' );

	$data = array(
		'name'              => $name,
		'short_description' => substr( $readme_md, $short_description_start, strpos( $readme_md, PHP_EOL, $short_description_start ) - $short_description_start ),
		'more_info'         => 'https://github.com/akirk/' . $slug,
		'slug'              => $slug,
		'version'           => $version,
	);

	$data['name'] = trim( strtok( $readme_md, PHP_EOL ), ' #' );

	$data['trunk'] = "https://github.com/akirk/$slug/archive/refs/tags/$version.zip";
	$data['download_link'] = $data['trunk'];

	$headers = get_headers( $data['download_link'] );
	$exists = false;
	foreach ( $headers as $header ) {
		if ( preg_match( '#HTTP/[0-9.]+\s404#', $header ) ) {
			$exists = false;
			break;
		}
		if ( preg_match( '#HTTP/[0-9.]+\s200#', $header ) ) {
			$exists = true;
			break;
		}
	}
	if ( ! $exists ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $slug, ' version ', $version, ' does not exist at ', $data['download_link'];
		exit( 1 );
	}

	$data['last_updated'] = gmdate( 'Y-m-d', exec( 'git --git-dir=' . $dir . '/.git/ log -1 --format=%ct ' ) );
	$data['sections'] = array();

	foreach ( explode( PHP_EOL . '## ', $readme_md ) as $i => $section ) {
		if ( 0 === $i ) {
			continue;
		}
		$title = strtok( $section, PHP_EOL );
		$data['sections'][ $title ] = simple_convert_markdown( substr( $section, strlen( $title ) ) );
	}
	$json[ $slug ] = $data;
}
file_put_contents( __DIR__ . '/../plugins.json', json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
echo 'plugins.json was created.', PHP_EOL;

/**
 * A simple function to convert Markdown to HTML.
 *
 * @param      string $md     The Markdown content.
 *
 * @return     string  The HTML.
 */
function simple_convert_markdown( $md ) {
	$html = $md;
	$html = preg_replace( '/^# (.*)$/m', '<h2>$1</h2>', $html );
	$html = preg_replace( '/^## (.*)$/m', '<h3>$1</h3>', $html );
	$html = preg_replace( '/^### (.*)$/m', '<h4>$1</h4>', $html );
	$html = preg_replace( '/\[([^\]]*)\]\(([^)]*)\)/', '<a href="$2">$1</a>', $html );
	return trim( preg_replace( '/\n+/', "<br/>\n", trim( $html ) ) );
}
