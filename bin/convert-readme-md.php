<?php
/**
 * Rewrite README.md into WordPress's readme.txt
 * adapted from https://raw.githubusercontent.com/GoogleChromeLabs/pwa-wp/develop/bin/transform-readme.php
 *
 * @package Friends
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fwrite
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents

if ( 'cli' !== php_sapi_name() ) {
	fwrite( STDERR, "Must run from CLI.\n" );
	exit( 1 );
}

// Run the conversion only if the file wasn't included.
$included_files = get_included_files();
if ( __FILE__ === $included_files[0] ) {
	$readme_txt = convert_readme_md( file_get_contents( __DIR__ . '/../README.md' ), true );

	if ( false === $readme_txt ) {
		// error was already printed.
		exit( 1 );
	}

	if ( ! file_put_contents( __DIR__ . '/../readme.txt', $readme_txt ) ) {
		fwrite( STDERR, "Failed to write readme.txt.\n" );
		exit( 1 );
	}

	fwrite( STDOUT, "Validated README.md and generated readme.txt\n" );
	exit( 0 );
}

/**
 * Convert a (Github) README.md to a (WordPress.org) readme.txt
 *
 * @param      string $readme_md     The readme md file contents.
 * @param      bool   $print_errors  Whether to print the errors.
 *
 * @return     bool|string    False if an error, the readme.txt otherwise.
 */
function convert_readme_md( $readme_md, $print_errors = false ) {
	$readme_txt = $readme_md;
	$metadata = array();

	// Transform the sections above the description.
	$readme_txt = preg_replace_callback(
		'/^.+?(?=## Description)/s',
		static function ( $matches ) use ( $print_errors, &$metadata ) {
			// Delete lines with images.
			$input = trim( preg_replace( '/\[?!\[.+/', '', $matches[0] ) );

			$parts = preg_split( '/\n\n+/', $input );

			if ( 3 !== count( $parts ) ) {
				if ( $print_errors ) {
					fwrite( STDERR, "Too many sections in header found.\n" );
				}
				return false;
			}

			$header = $parts[0];

			$description = $parts[1];
			if ( strlen( $description ) > 150 ) {
				if ( $print_errors ) {
					fwrite( STDERR, 'The short description is too long (' . strlen( $description ) . " chars): $description\n" );
				}
				return false;
			}

			foreach ( explode( "\n", $parts[2] ) as $meta ) {
				$meta = trim( $meta );
				if ( ! preg_match( '/^\*\*(?P<key>.+?):\*\* (?P<value>.+)/', $meta, $matches ) ) {
					if ( $print_errors ) {
						fwrite( STDERR, "Parse error for meta line: $meta.\n" );
					}
					return false;
				}

				$unlinked_value = preg_replace( '/\[(.+?)]\(.+?\)/', '$1', $matches['value'] );

				$metadata[ $matches['key'] ] = $unlinked_value;

				// Extract License URI from link.
				if ( 'License' === $matches['key'] ) {
					$license_uri = preg_replace( '/\[.+?]\((.+?)\)/', '$1', $matches['value'] );

					if ( 0 !== strpos( $license_uri, 'http' ) ) {
						if ( $print_errors ) {
							fwrite( STDERR, "Unable to extract License URI from: $meta.\n" );
						}
						return false;
					}

					$metadata['License URI'] = $license_uri;
				}
			}

			$expected_metadata = array(
				'Contributors',
				'Tags',
				'Requires at least',
				'Tested up to',
				'Stable tag',
				'License',
				'License URI',
				'Requires PHP',
			);
			foreach ( $expected_metadata as $key ) {
				if ( empty( $metadata[ $key ] ) ) {
					if ( $print_errors ) {
						fwrite( STDERR, "Failed to parse metadata. Missing: $key\n" );
					}
					return false;
				}
			}

			$replaced = "$header\n";
			foreach ( $metadata as $key => $value ) {
				$replaced .= "$key: $value\n";
			}
			$replaced .= "\n$description\n\n";

			return $replaced;
		},
		$readme_txt
	);

	// Convert markdown headings into WP readme headings for good measure.
	$readme_txt = preg_replace_callback(
		'/^(#+)\s(.+)/m',
		static function ( $matches ) use ( $print_errors ) {
			$md_heading_level = strlen( $matches[1] );
			$heading_text     = $matches[2];

			// #: ===
			// ##: ==
			// ###: =
			$txt_heading_level = 4 - $md_heading_level;
			if ( $txt_heading_level <= 0 ) {
				if ( $print_errors ) {
					fwrite( STDERR, "Heading too small to transform: {$matches[0]}.\n" );
				}
				return false;
			}

			return sprintf(
				'%1$s %2$s %1$s',
				str_repeat( '=', $txt_heading_level ),
				$heading_text
			);
		},
		$readme_txt,
		-1,
		$replace_count
	);

	if ( 0 === $replace_count ) {
		if ( $print_errors ) {
					fwrite( STDERR, "Unable to transform headings.\n" );
		}
		return false;
	}

	// Convert Youtube video links to just a URL since the WordPress plugin directory will display them.
	$readme_txt = preg_replace(
		'#^\[!\[[^\]]+\]\([^\)]+\)\]\((https://www.youtube.com/[^\)]+|https://youtu.be/[^\)]+)\)#m',
		'$1',
		$readme_txt
	);

	return $readme_txt;
}
