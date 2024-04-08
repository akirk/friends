<?php
/**
 * Extract hooks from this codebase.
 *
 * @author Alex Kirk
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

function sample_ini() {
	return <<<INI
namespace = App_Namespace
base_dir = ..
wiki_directory = ../repo.wiki
github_blob_url = https://github.com/username/repo/blob/main/
[hooks]
ignore_filter[] = filter_name1
ignore_filter[] = filter_name2
INI;
}
$dirs = array( getcwd(), __DIR__ );
if ( isset( $_SERVER['argv'][1] ) && file_exists( $_SERVER['argv'][1] ) ) {
	if ( is_dir( $_SERVER['argv'][1] ) ) {
		array_unshift( $dirs, $_SERVER['argv'][1] );
	} else {
		array_unshift( $dirs, dirname( $_SERVER['argv'][1] ) );
	}
}

foreach ( $dirs as $dir ) {
	$ini_file = $dir . '/extract-hooks.ini';
	if ( file_exists( $ini_file ) ) {
		break;
	}
}

if ( ! file_exists( $ini_file ) ) {
	echo 'Please provide an extract-hooks.ini file in the current directory or the same directory as this script. Example: ', PHP_EOL, sample_ini(), PHP_EOL;
	exit( 1 );
}
echo 'Loading ', realpath( $ini_file ), PHP_EOL;
$ini = parse_ini_file( $ini_file );

foreach ( array( 'namespace', 'base_dir', 'wiki_directory', 'github_url' ) as $key ) {
	if ( ! isset( $ini[ $key ] ) ) {
		echo 'Missing ini entry ', $key, '. Example: ', PHP_EOL, sample_ini(), PHP_EOL;
		exit( 1 );
	}
}

if ( empty( $ini['ignore_filter'] ) ) {
	$ini['ignore_filter'] = array();
}
if ( empty( $ini['ignore_regex'] ) ) {
	$ini['ignore_regex'] = false;
}
if ( empty( $ini['section'] ) ) {
	$ini['section'] = 'file';
}
// if the base dir is not absolute (also on windows), prepend it with $dir.
if ( '/' === substr( $ini['base_dir'], 0, 1 ) ) {
	$base = $ini['base_dir'];
} else {
	$base = realpath( $dir . '/' . $ini['base_dir'] );
}
echo 'Scanning ', $base, PHP_EOL;
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

		$comment = '';
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
			&& ! in_array( $hook, $ini['ignore_filter'] )
			&& ( ! $ini['ignore_regex'] || ! preg_match( $ini['ignore_regex'], $hook ) )
		) {
			if ( ! isset( $filters[ $hook ] ) ) {
				$filters[ $hook ] = array(
					'files'   => array(),
					'section' => 'dir' === $ini['section'] ? $main_dir : basename( $file->getFilename() ),
					'type'    => $token[1],
					'params'  => array(),
					'comment' => '',
				);
			}

			$ret = extract_vars( $filters[ $hook ]['params'], $tokens, $i );
			$filters[ $hook ]['files'][ $dir . '/' . $file->getFilename() . ':' . $token[2] ] = $ret[1];
			$filters[ $hook ]['params'] = $ret[0];
			$filters[ $hook ] = array_merge( $filters[ $hook ], parse_docblock( $comment, $filters[ $hook ]['params'] ) );
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

function extract_vars( $params, $tokens, $i ) {
	$parens = array();
	$var = 0;
	$vars = array( '' );
	$signature = $tokens[ $i ][1];
	$line = $tokens[ $i ][2];
	$search_window = 50;
	for ( $j = $i + 1; $j < $i + $search_window; $j++ ) {
		if ( ! isset( $tokens[ $j ] ) ) {
			break;
		}
		$token = $tokens[ $j ];
		if ( is_string( $token ) ) {
			$open_paren = false;
			$signature .= $token;
			switch ( $token ) {
				case '[':
				case '(':
				case '{':
					$vars[ $var ] .= $token;
					$parens[] = $token;

					break;
				case ')':
					$open_paren = '(';
					// Intentional fallthrough.
				case ']':
					if ( ! $open_paren ) {
						$open_paren = '[';
					}
					// Intentional fallthrough.
				case '}':
					if ( ! $open_paren ) {
						$open_paren = '{';
					}
					$vars[ $var ] .= $token;

					if ( end( $parens ) === $open_paren ) {
						array_pop( $parens );
					}
					if ( empty( $parens ) ) {
						$vars[ $var ] = substr( $vars[ $var ], 0, -1 );
						// all of the filter has been consumed.
						break 2;
					}
					break;
				case ',':
					if ( count( $parens ) === 1 ) {
						++$var;
						$vars[ $var ] = '';
					} else {
						$vars[ $var ] .= $token;
					}
					break;
				default:
					$vars[ $var ] .= $token;
					break;
			}
		} elseif ( is_array( $token ) ) {
			$signature .= $token[1];
			if ( T_WHITESPACE !== $token[0] ) {
				$vars[ $var ] .= $token[1];
			}
		}
	}
	if ( $j === $i + $search_window ) {
		$signature = rtrim( $signature ) . PHP_EOL . '// ...';
	}
	array_shift( $vars );
	foreach ( $vars as $k => $var ) {
		if ( isset( $params[ $k ] ) ) {
			if ( ! in_array( $var, $params[ $k ] ) ) {
				$params[ $k ][] = $var;
			}
		} else {
			$params[ $k ] = array( $var );
		}
	}
	return array( $params, $signature );
}

function parse_docblock( $raw_comment, $params ) {
	if ( preg_match( '#^([ \t]*\*\s*|//\s*)?Documented (in|at) #m', $raw_comment ) ) {
		return array();
	}
	// Adapted from https://github.com/kamermans/docblock-reflection.
	$tags = array();
	$lines = array_filter( explode( PHP_EOL, trim( $raw_comment ) ) );
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

		case 0:
		case 2:
			return array();

		default:
			// Handle multi-line docblock.
			array_shift( $lines );
			array_pop( $lines );
			break;
	}

	foreach ( $lines as $line ) {
		$line = preg_replace( '#^[ \t]*\* ?#', '', $line );
		if ( preg_match( '#^Documented (in|at) #', $line ) ) {
			return array();
		}

		if ( preg_match( '#@(param)(.*)#', $line, $matches ) ) {
			$tag_value = \trim( $matches[2] );

			// If this tag was already parsed, make its value an array.
			if ( isset( $tags['params'] ) ) {
				$tags['params'][] = array( $tag_value );
			} else {
				$tags['params'] = array( array( $tag_value ) );
			}
			continue;
		}
		if ( preg_match( '#@([^ ]+)(.*)#', $line, $matches ) ) {
			$tag_name = $matches[1] . 's';
			$tag_value = \trim( $matches[2] );

			// If this tag was already parsed, make its value an array.
			if ( isset( $tags[ $tag_name ] ) ) {
				$tags[ $tag_name ][] = array( $tag_value );
			} else {
				$tags[ $tag_name ] = $tag_value;
			}
			continue;
		}

		$comment .= "$line\n";
	}
	if ( ! isset( $tags['params'] ) ) {
		$tags['params'] = array();
	}
	foreach ( $params as $k => $param ) {
		if ( ! isset( $tags['params'][ $k ] ) ) {
			$tags['params'][ $k ] = $param;
		} elseif ( ! in_array( $tags['params'][ $k ], $param ) ) {
			$tags['params'][ $k ] = array_merge( $tags['params'][ $k ], $param );
		}
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

$docs = $base . '/' . $ini['wiki_directory'];
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
	$has_example = false;
	$index .= "- [`$hook`]($hook)";
	if ( ! empty( $data['comment'] ) ) {
		$index .= ' ' . strtok( $data['comment'], PHP_EOL );
		$has_example = preg_match( '/^Example:?$/m', $data['comment'] );
		$doc .= PHP_EOL . preg_replace( '/^Example:?$/m', '### Example' . PHP_EOL, $data['comment'] ) . PHP_EOL . PHP_EOL;
	}

	$index .= PHP_EOL;

	if ( ! empty( $data['params'] ) ) {
		if ( 'do_action' === $data['type'] ) {
			$signature = 'add_action(';
		} else {
			$signature = 'add_filter(';
		}
		$signature .= PHP_EOL . '    \'' . $hook . '\',';
		$signature .= PHP_EOL . '    function (';

		$params = "## Parameters\n";
		$first = false;
		$count = 0;
		foreach ( $data['params'] as $i => $vars ) {
			$param = false;
			foreach ( $vars as $k => $var ) {
				if ( false !== strpos( $var, ' ' ) && false === strpos( $var, '\'' ) ) {
					$param = $var;
					$p = preg_split( '/ +/', $param, 3 );
					$vars[ $k ] = $p[1];
				}
			}
			$type = 'unknown';

			// This was an extracted variable, so let's create a parameter definition.
			foreach ( $vars as $k => $var ) {
				if ( preg_match( '#array\(#', $var, $matches ) ) {
					$type = 'array';
					$vars[ $k ] = '$array';
				} elseif ( preg_match( '#\$(?:[a-zA-Z0-9_]+)\[[\'"]([^\'"]+)[\'"]#', $var, $matches ) ) {
					$vars[ $k ] = '$' . $matches[1];
				} elseif ( preg_match( '#([a-zA-Z0-9_]+)\(\)#', $var, $matches ) ) {
					$vars[ $k ] = '$' . str_replace( 'wp_get_', '', $matches[1] );
				} elseif ( preg_match( '#\$(?:[a-zA-Z0-9_]+)->(.+)$#', $var, $matches ) ) {
					$vars[ $k ] = '$' . $matches[1];
				} elseif ( preg_match( '#_[_xn]\(\s*([\'"][^\'"]+[\'"])#', $var, $matches ) ) {
					$type = 'string';
					$vars[ $k ] = '$' . preg_replace( '/[^a-z0-9]/', '_', strtolower( trim( $matches[1], '"\'' ) ) );
				} elseif ( strlen( $var ) - strlen( trim( $var, '"\'' ) ) === 2 ) {
					$type = 'string';
					$vars[ $k ] = '$' . preg_replace( '/[^a-z0-9]/', '_', strtolower( trim( $var, '"\'' ) ) );
				} elseif ( is_numeric( $var ) ) {
					$type = 'int';
					$vars[ $k ] = '$int';
				} elseif ( 'true' === $var || 'false' === $var ) {
					$type = 'bool';
					$vars[ $k ] = '$' . $var;
				} elseif ( 'null' === $var ) {
					$vars[ $k ] = '$ret';
				} elseif ( in_array( $var, array( '$url' ), true ) ) {
					$type = 'string';
				} elseif ( '$array' === $var ) {
					$type = 'array';
					$vars[ $k ] = '$array';
				}
			}
			if ( ! $param ) {
				$var = reset( $vars );
				$param = $type . ' ' . $var;
				$other = array_unique( array_diff( $vars, array( $param, $var, 'null' ) ) );
				if ( $other ) {
					$param .= ' Other variable names: `' . implode( '`, `', $other ) . '`';
				}
			}


			$count += 1;
			$p = preg_split( '/ +/', $param, 3 );
			if ( '\\' === substr( $p[0], 0, 1 ) ) {
				$p[0] = substr( $p[0], 1 );
			} elseif ( ! in_array( strtok( $p[0], '|' ), array( 'int', 'string', 'bool', 'array', 'unknown' ) ) && substr( $p[0], 0, 3 ) !== 'WP_' ) {
				$p[0] = $ini['namespace'] . '\\' . $p[0];
			}
			if ( ! $first ) {
				$first = $p[1];
			}
			if ( 'unknown' === $p[0] ) {
				$params .= "\n- `{$p[1]}`";
				$signature .= "\n        {$p[1]},";
				if ( isset( $p[2] ) ) {
					$params .= ' ' . $p[2];
				}
			} else {
				$params .= "\n- *`{$p[0]}`* `{$p[1]}`";
				if ( isset( $p[2] ) ) {
					$params .= ' ' . $p[2];
				}
				if ( substr( $p[0], -5 ) === '|null' ) { // Remove this if, if you don't want to support PHP 7.4 or below.
					$signature .= "\n        " . substr( $p[0], 0, -5 ) . ' ' . $p[1] . ' = null,';
				} else {
					$signature .= "\n        {$p[0]} {$p[1]},";
				}
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
		if ( ! $has_example ) {
			$doc .= '### Auto-generated Example' . PHP_EOL . PHP_EOL . '```php' . PHP_EOL . $signature . PHP_EOL . '```' . PHP_EOL . PHP_EOL;
		}
		$doc .= $params . PHP_EOL . PHP_EOL;
	}

	if ( ! empty( $data['returns'] ) ) {
		$doc .= "## Returns\n";
		$p = preg_split( '/ +/', $data['returns'], 2 );
		if ( '\\' === substr( $p[0], 0, 1 ) ) {
			$p[0] = substr( $p[0], 1 );
		} elseif ( ! in_array( strtok( $p[0], '|' ), array( 'int', 'string', 'bool', 'array', 'unknown' ) ) && substr( $p[0], 0, 3 ) !== 'WP_' ) {
			$p[0] = $ini['namespace'] . '\\' . $p[0];
		}
		$doc .= "\n`{$p[0]}` {$p[1]}";

		$doc .= PHP_EOL . PHP_EOL;
	}

	$doc .= "## Files\n\n";
	foreach ( $data['files'] as $file => $signature ) {
		$doc .= "- [$file](" . $ini['github_url'] . str_replace( ':', '#L', $file ) . ")\n";
		$doc .= '```php' . PHP_EOL . $signature . PHP_EOL . '```' . PHP_EOL . PHP_EOL;
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
