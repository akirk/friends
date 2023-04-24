<?php
namespace Friends\Blocks_Everywhere;
/*
 This is a copy of https://github.com/Automattic/blocks-everywhere version 1.14.1

 Changes:
 - Adjusted namespaces to start with Friends.
 - Handlers for the Friends plugin.
 - Removed the existing handlers.
 */

use Friends\Blocks_Everywhere\Handler;

require_once __DIR__ . '/classes/class-handler.php';
require_once __DIR__ . '/classes/class-editor.php';

class Blocks_Everywhere {
	const VERSION = '1.18.0';

	/**
	 * Instance variable
	 * @var Blocks_Everywhere|null
	 */
	private static $instance = null;

	/**
	 * Gutenberg editor
	 *
	 * @var Blocks_Everywhere|null
	 */
	private $gutenberg = null;

	/**
	 * Gutenberg handlers
	 *
	 * @var Handler\Handler[]
	 */
	private $handlers = [];

	/**
	 * Singleton access
	 *
	 * @return Blocks_Everywhere
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Blocks_Everywhere();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'load_handlers' ] );

		// Admin editors
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Load whatever handler is configured
	 *
	 * @return void
	 */
	public function load_handlers() {
		$this->handlers[] = new Handler\Friends_Message();
		$this->handlers[] = new Handler\Friends_Status_Post();
	}

	/**
	 * Perform additional admin tasks when on the comment page
	 *
	 * @param String $hook Page hook.
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		foreach ( $this->handlers as $handler ) {
			if ( $handler->can_show_admin_editor( $hook ) ) {
				add_action(
					'admin_head',
					function() use ( $handler ) {
						add_filter( 'the_editor', [ $handler, 'the_editor' ] );
						add_filter( 'wp_editor_settings', [ $handler, 'wp_editor_settings' ], 10, 2 );
					}
				);

				// Stops a problem with the Gutenberg plugin accessing widgets that don't exist
				remove_action( 'admin_footer', 'gutenberg_block_editor_admin_footer' );

				// Load Gutenberg in in_admin_header so WP admin doesn't set the 'block-editor-page' body class
				add_action(
					'in_admin_header',
					function() use ( $handler ) {
						$handler->load_editor( '.wp-editor-area' );
					}
				);

				break;
			}
		}
	}
}

Blocks_Everywhere::init();
