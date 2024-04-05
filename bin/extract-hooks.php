<?php
/**
 * Extract hooks from the codebase.
 *
 * @package Friends
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

$ignore_filters = array(
	'_get_template_part_',
	'_get_template_part',
	'_template_paths',
	'bulk_post_updated_messages',
	'convert_chars',
	'edit_posts_per_page',
	'fake_http_response',
	'get_edit_user_link',
	'get_template_part_',
	'local_fetch_feed',
	'rest_api_init',
	'the_content',
	'widget_title',
	'wp_feed_cache_transient_lifetime',
	'wp_mail_from',
	'wp_update_comment_count',
	'wptexturize',
);

$base = dirname( __DIR__ );
$files = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $base ),
	RecursiveIteratorIterator::LEAVES_ONLY
);
$b = strlen( $base ) + 1;
$filters = array();
foreach ( $files as $file ) {
	if ( $file->getExtension() !== 'php' ) {
		continue;
	}
	$dir = substr( $file->getPath(), $b );
	$main_dir = strtok( $dir, '/' );
	if ( in_array(
		$main_dir,
		array(
			'blocks',
			'libs',
			'tests',
		)
	) ) {
		continue;
	}
	$tokens = token_get_all( file_get_contents( $file ) );
	foreach ( $tokens as $i => $token ) {
		if ( ! is_array( $token ) || ! isset( $token[1] ) ) {
			continue;
		}
		if ( ! in_array( ltrim( $token[1], '\\' ), array( 'apply_filters', 'do_action' ) ) ) {
			continue;
		}

		$comment = false;
		$hook = false;

		for ( $j = $i; $j > max( 0, $i - 10 ); $j-- ) {
			if ( ! is_array( $tokens[ $j ] ) ) {
				continue;
			}

			if ( T_DOC_COMMENT === $tokens[ $j ][0] ) {
				$comment = $tokens[ $j ][1];
				break;
			}

			if ( T_COMMENT === $tokens[ $j ][0] ) {
				$comment = $tokens[ $j ][1];
				break;
			}
		}

		for ( $j = $i + 1; $j < $i + 10; $j++ ) {
			if ( ! is_array( $tokens[ $j ] ) ) {
				continue;
			}

			if ( T_CONSTANT_ENCAPSED_STRING === $tokens[ $j ][0] ) {
				$hook = trim( $tokens[ $j ][1], '"\'' );
				break;
			}
		}

		if (
		$hook
		&& ! in_array( $hook, $ignore_filters )
		&& ! preg_match( '/^(activitypub_)/', $hook )

		) {
			if ( ! isset( $filters[ $hook ] ) ) {
				$filters[ $hook ] = array(
					'files'   => array(),
					'section' => $main_dir,
					'type'    => $token[1],
				);
			}

			$filters[ $hook ]['files'][] = $dir . '/' . $file->getFilename() . ':' . $token[2];
			$filters[ $hook ]['base_dirs'][ $main_dir ] = true;

			if ( ! $comment ) {
				$comment = '/**' . PHP_EOL;
				// generate a fake doccomment if it's missing.
				for ( $j = $i + 1; $j < $i + 10; $j++ ) {
					if ( ! isset( $tokens[ $j ] ) ) {
						break;
					}

					if ( ! is_array( $tokens[ $j ] ) ) {
						continue;
					}

					if ( T_VARIABLE === $tokens[ $j ][0] ) {
						$comment .= ' * @param unknown ' . $tokens[ $j ][1] . PHP_EOL;
					}
				}

				$comment .= '*/';
			}

			if ( $comment ) {
				$docblock = parse_docblock( $comment );

				if ( ( ! empty( $docblock['comment'] ) && ! preg_match( '#^Documented in#i', $docblock['comment'] ) ) || ! empty( $docblock['param'] ) ) {
					$filters[ $hook ] = array_merge( $docblock, $filters[ $hook ] );
				}
			}
		}
	}
}
uksort(
	$filters,
	function ( $a, $b ) use ( $filters ) {
		if ( $filters[ $a ]['section'] === $filters[ $b ]['section'] ) {
			return $a < $b ? -1 : 1;
		}
		return $filters[ $a ]['section'] < $filters[ $b ]['section'] ? -1 : 1;
	}
);

function parse_docblock( $raw_comment ) {
	// Adapted from https://github.com/kamermans/docblock-reflection.
	$tags = array();
	$lines = explode( PHP_EOL, trim( $raw_comment ) );
	$matches = null;
	$comment = '';

	switch ( count( $lines ) ) {
		case 1:
			// Handle single-line docblock.
			if ( ! preg_match( '#\\/\\*\\*([^*]*)\\*\\/#', $lines[0], $matches ) ) {
				return array(
					'comment' => trim( ltrim( $lines[0], "/ \t" ) ),
				);
			}
			$lines[0] = \substr( $lines[0], 3, -2 );
			break;

		case 2:
			// Probably malformed.
			return array();

		default:
			// Handle multi-line docblock.
			array_shift( $lines );
			array_pop( $lines );
			break;
	}

	foreach ( $lines as $line ) {
		$line = preg_replace( '#^[ \t]*\* ?#', '', $line );

		if ( preg_match( '#@([^ ]+)(.*)#', $line, $matches ) ) {
			$tag_name = $matches[1];
			$tag_value = \trim( $matches[2] );

			// If this tag was already parsed, make its value an array.
			if ( isset( $tags[ $tag_name ] ) ) {
				if ( ! \is_array( $tags[ $tag_name ] ) ) {
					$tags[ $tag_name ] = array( $tags[ $tag_name ] );
				}

				$tags[ $tag_name ][] = $tag_value;
			} else {
				$tags[ $tag_name ] = $tag_value;
			}
			continue;
		}

		$comment .= "$line\n";
	}
	$ret = array_filter(
		array_merge(
			$tags,
			array(
				'comment' => trim( $comment ),
			)
		)
	);
	if ( empty( $ret ) ) {
		return array();
	}

	return $ret;
}

$docs = $base . '/../friends.wiki/';
if ( ! file_exists( $docs ) ) {
	mkdir( $docs, 0777, true );
}

$index = '';
$section = '';
foreach ( $filters as $hook => $data ) {
	if ( $section !== $data['section'] ) {
		$section = $data['section'];
		$index .= PHP_EOL . '## ' . $section . PHP_EOL . PHP_EOL;
	}
	$doc = '';
	$index .= "- [`$hook`]($hook)";

	if ( ! empty( $data['comment'] ) ) {
		$index .= ' ' . strtok( $data['comment'], PHP_EOL );
		$doc .= PHP_EOL . $data['comment'] . PHP_EOL . PHP_EOL;
	}

	$index .= PHP_EOL;

	if ( ! empty( $data['param'] ) ) {
		if ( 'do_action' === $data['type'] ) {
			$signature = 'add_action(';
		} else {
			$signature = 'add_filter(';
		}
		$signature .= PHP_EOL . '    \'' . $hook . '\',';
		$signature .= PHP_EOL . '    function (';

		$doc .= "## Parameters\n";
		$first = false;
		$count = 0;
		foreach ( (array) $data['param'] as $param ) {
			$count += 1;
			$p = explode( ' ', $param, 3 );
			if ( '\\' === substr( $p[0], 0, 1 ) ) {
				$p[0] = substr( $p[0], 1 );
			} elseif ( ! in_array( $p[0], array( 'int', 'string', 'bool', 'array', 'unknown' ) ) && substr( $p[0], 0, 3 ) !== 'WP_' ) {
				$p[0] = 'Friends\\' . $p[0];
			}
			if ( ! $first ) {
				$first = $p[1];
			}
			if ( 'unknown' === $p[0] ) {
				$doc .= "\n- `{$p[1]}`";
				$signature .= "\n        {$p[1]},";
			} else {
				$doc .= "\n- *`{$p[0]}`* `{$p[1]}`";
				if ( isset( $p[2] ) ) {
					$doc .= ' ' . $p[2];
				}
				$signature .= "\n        {$p[0]} {$p[1]},";
			}
		}
		if ( 1 === $count ) {
			$signature = str_replace( 'function (' . PHP_EOL . '        ', 'function ( ', substr( $signature, 0, -1 ) );
			$signature .= ' ) {';
		} else {
			$signature = substr( $signature, 0, -1 ) . PHP_EOL . '    ) {';
		}
		$signature .= PHP_EOL . '        // Your code here';
		if ( 'do_action' !== $data['type'] ) {
			$signature .= PHP_EOL . '        return ' . $first . ';';
		}
		$signature .= PHP_EOL . '    }';
		if ( $count > 1 ) {
			$signature .= ',';
			$signature .= PHP_EOL . '    10,';
			$signature .= PHP_EOL . '    ' . $count;
		}
		$signature .= PHP_EOL . ');';

		$doc = '```php' . PHP_EOL . $signature . PHP_EOL . '```' . PHP_EOL . $doc;
		$doc .= PHP_EOL . PHP_EOL;
	}

	$doc .= "## Files\n\n";
	foreach ( $data['files'] as $file ) {
		$doc .= "- [$file](https://github.com/akirk/friends/blob/main/" . str_replace( ':', '#L', $file ) . ")\n";
	}
	$doc .= "\n\n[Hooks](Hooks)\n";

	file_put_contents(
		$docs . "/$hook.md",
		$doc
	);

}
file_put_contents(
	$docs . '/Hooks.md',
	$index
);

echo 'Genearated ' . count( $filters ) . ' hooks documentation files in ' . realpath( $docs ) . PHP_EOL; // phpcs:ignore
