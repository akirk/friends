<?php
/**
 * A script to generate a plugins.json to be used for the update functionality of the friends plugin.
 *
 * @package Friends
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

stream_context_set_default(
	array(
		'http' => array(
			'user_agent' => 'WordPress/Friends',
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
	if ( ! file_exists( "$dir/README.md" ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo realpath( $dir ), '/README.md does not exist.', PHP_EOL;
		continue;
	}
	$readme_md = file_get_contents( "$dir/README.md" );

	$headline = strtok( $readme_md, PHP_EOL );
	$short_description_start = 2 + strlen( $headline );

	$name = trim( $headline, ' #' );

	$data = array(
		'name'              => $name,
		'short_description' => substr( $readme_md, $short_description_start, strpos( $readme_md, PHP_EOL, $short_description_start ) - $short_description_start ),
		'more_info'         => 'https://github.com/akirk/' . $slug,
		'author'            => '<a href="https://github.com/akirk/">Alex Kirk</a>',
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
		echo $slug, ' version ', $version, ' does not exist at ', $data['download_link'], PHP_EOL;
		continue;
	}

	$data['last_updated'] = gmdate( 'Y-m-d', exec( 'git --git-dir=' . $dir . '/.git/ log -1 --format=%ct ' ) );
	$data['sections'] = array();

	foreach ( explode( PHP_EOL . '## ', $readme_md ) as $i => $section ) {
		if ( 0 === $i ) {
			continue;
		}
		$title = strtok( $section, PHP_EOL );
		$data['sections'][ $title ] = simple_convert_markdown( substr( $section, strlen( $title ) ), 'https://github.com/akirk/' . $slug, 'https://raw.githubusercontent.com/akirk/' . $slug . '/HEAD/' );


	}
	$json[ $slug ] = $data;
}

foreach ( array(
	'alquimidia/fedipress',
) as $repo ) {
	$repo_info = json_decode( file_get_contents( "https://api.github.com/repos/$repo" ) );
	$slug = strtr( $repo_info->full_name, '/', '-' );

	$latest_release = json_decode( file_get_contents( "https://api.github.com/repos/$repo/releases/latest" ) );
	$data = array(
		'name'              => preg_replace( '/(\w)press/', '$1Press', ucfirst( $repo_info->name ) ),
		'short_description' => $repo_info->description,
		'more_info'         => $repo_info->html_url,
		'author'            => '<a href="' . $repo_info->owner->html_url . '">' . $repo_info->owner->login . '</a>',
		'slug'              => $slug,
		'version'           => $latest_release->tag_name,
		'trunk'             => $latest_release->zipball_url,
		'download_link'     => $latest_release->zipball_url,
		'last_updated'      => substr( $latest_release->published_at, 0, 10 ),
		'sections'          => array(),
	);

	$readme_md = 'Overview' . PHP_EOL . file_get_contents( "https://raw.githubusercontent.com/$repo/HEAD/README.md" );
	foreach ( explode( PHP_EOL . '## ', $readme_md ) as $section ) {
		$title = strtok( $section, PHP_EOL );
		$data['sections'][ $title ] = simple_convert_markdown( substr( $section, strlen( $title ) ), $repo_info->html_url, 'https://raw.githubusercontent.com/' . $repo . '/HEAD/' );
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
function simple_convert_markdown( $md, $url, $img_base_url = '' ) {
	if ( ! $img_base_url ) {
		$img_base_url = $url;
	}
	$html = $md;
	$html = preg_replace( '/^# (.*)$/m', '<h2>$1</h2>', $html );
	$html = preg_replace( '/^## (.*)$/m', '<h3>$1</h3>', $html );
	$html = preg_replace( '/^### (.*)$/m', '<h4>$1</h4>', $html );
	$html = preg_replace( '/\*\*\*(.*?)\*\*\*/', '<strong><em>$1</em></strong>', $html );
	$html = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html );
	$html = preg_replace( '/^> (.*)$/m', '<blockquote>$1</blockquote>', $html );
	$html = preg_replace( '/!\[([^\]]*)\]\((https?:\/\/[^)]*)\)/', '<img src="$2" alt="$1"/>', $html );
	$html = preg_replace( '/!\[([^\]]*)\]\(([^)]*)\)/', '<img src="' . $img_base_url . '/$2" alt="$1"/>', $html );
	$html = preg_replace( '/\[([^\]]*)\]\((https?:\/\/[^)]*)\)/', '<a href="$2">$1</a>', $html );
	$html = preg_replace( '/\[([^\]]*)\]\(([^)]*)\)/', '<a href="' . $url . '/$2">$1</a>', $html );
	return trim( preg_replace( '/\n+/', "<br/>\n", trim( $html ) ) );
}
