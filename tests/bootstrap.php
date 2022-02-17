<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Friends
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

require_once dirname( dirname( __FILE__ ) ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/friends.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// We need to prevent output since we'll want to test some headers later.
ob_start();

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
Friends\Friends::activate_plugin();
ob_end_clean();

// Make sure to be able to query these hosts.
add_filter(
	'http_request_host_is_external',
	function( $in, $host ) {
		if ( in_array( $host, array( 'me.local', 'friend.local' ) ) ) {
			return true;
		}
		return $in;
	},
	10,
	2
);

add_filter(
	'http_request_args',
	function( $args, $url ) {
		if ( in_array( parse_url( $url, PHP_URL_HOST ), array( 'me.local', 'friend.local' ) ) ) {
			$args['reject_unsafe_urls'] = false;
		}
		return $args;
	},
	10,
	2
);


// Disable the feed fetching after a friendship was established.
add_filter( 'friends_immediately_fetch_feed', '__return_false' );

// Disable sending e-mails.
add_filter( 'friends_send_mail', '__return_false' );

// Output setting of options during debugging.
if ( defined( 'TESTS_VERBOSE' ) && TESTS_VERBOSE ) {
	add_filter(
		'pre_update_option',
		function( $value, $option ) {
			if ( ! in_array( $option, array( 'rewrite_rules' ) ) ) {
				echo PHP_EOL, $option, ' => ', $value, PHP_EOL;
			}
			return $value;
		},
		10,
		2
	);

	add_action(
		'update_user_metadata',
		function( $meta_id, $object_id, $meta_key, $meta_value ) {
			echo PHP_EOL, $meta_key, ' (', $object_id, ') => ';
			if ( is_numeric( $meta_value ) || is_string( $meta_value ) ) {
				echo $meta_value, PHP_EOL;
			} else {
				var_dump( $meta_value );
			}
		},
		10,
		4
	);
}

Friends\User_Query::$cache = false;
