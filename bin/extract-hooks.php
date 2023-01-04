<?php
/**
 * Extract hooks from the codebase.
 *
 * @package Friends
 */

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
		if ( ! in_array( $token[1], array( 'apply_filters', 'do_action' ) ) ) {
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

			if ( T_DOC_COMMENT === $tokens[ $j ][0] ) {
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

			if ( $comment ) {
				if ( ! isset( $filters[ $hook ]['comment'] ) ) {
					$filters[ $hook ]['comment'] = '';
				}
				$filters[ $hook ]['comment'] .= $comment;
			}
		}
	}
}
uksort(
	$filters,
	function( $a, $b ) use ( $filters ) {
		if ( $filters[ $a ]['section'] === $filters[ $b ]['section'] ) {
			return $a <=> $b;
		}
		return $filters[ $a ]['section'] <=> $filters[ $b ]['section'];
	}
);

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
	$index .= "- [`$hook`]($hook)\n";
	if ( ! empty( $data['comment'] ) ) {
		$doc .= "```php\n";
		$doc .= $data['comment'];
		$doc .= "\n```\n";
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

echo 'Genearated ' . count( $filters ) . ' hooks documentation files.' . PHP_EOL;
