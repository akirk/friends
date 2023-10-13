<?php

namespace Friends\Blocks_Everywhere;

/**
 * Provides functions to load Gutenberg assets
 */
class Editor {
	/**
	 * Can upload?
	 *
	 * @var boolean
	 */
	private $can_upload = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( ! class_exists( 'WP_Theme_JSON_Data_Gutenberg' ) ) {
			return null;
		}
		add_action( 'template_redirect', [ $this, 'setup_media' ] );
		add_filter( 'block_editor_settings_all', [ $this, 'block_editor_settings_all' ] );
		add_filter( 'should_load_block_editor_scripts_and_styles', '__return_true' );
		add_filter( 'wp_theme_json_data_theme', [ $this, 'wp_theme_json_data_theme' ] );
	}

	/**
	 * Provide theme.json
	 *
	 * @param \WP_Theme_JSON_Data_Gutenberg $json JSON.
	 * @return \WP_Theme_JSON_Data_Gutenberg
	 */
	public function wp_theme_json_data_theme( $json ) {
		$theme = new \WP_Theme_JSON_Data_Gutenberg(
			[
				'version' => 2,
				'settings' => [
					'color' => [
						'background' => false,
						'custom' => false,
						'customDuotone' => false,
						'customGradient' => false,
						'defaultGradients' => false,
						'defaultPalette' => false,
						'text' => false,
					],
					'typography' => [
						'customFontSize' => false,
						'dropCap' => false,
						'fontStyle' => false,
						'fontWeight' => false,
						'letterSpacing' => false,
						'lineHeight' => false,
						'textDecoration' => false,
						'textTransform' => false,
						'fontSizes' => [],
						'fontFamilies' => [],
					],
				],
			]
		);

		return $theme;
	}

	/**
	 * Restrict TinyMCE to the basics
	 *
	 * @param array $settings TinyMCE settings.
	 * @return array
	 */
	public function tiny_mce_before_init( $settings ) {
		$settings['toolbar1'] = 'bold,italic,bullist,numlist,blockquote,pastetext,removeformat,undo,redo';
		$settings['toolbar2'] = '';

		return $settings;
	}

	/**
	 * Load Gutenberg
	 *
	 * Based on wp-admin/edit-form-blocks.php
	 *
	 * @param array $settings Plugin settings.
	 * @return void
	 */
	public function load( $settings ) {
		global $post;

		$this->can_upload = isset( $settings['editor']['hasUploadPermissions'] ) && $settings['editor']['hasUploadPermissions'];
		$this->load_extra_blocks();

		// Restrict tinymce buttons
		add_filter( 'tiny_mce_before_init', [ $this, 'tiny_mce_before_init' ] );

		// Gutenberg scripts
		wp_enqueue_script( 'wp-block-library' );
		wp_enqueue_script( 'wp-format-library' );
		wp_enqueue_script( 'wp-editor' );
		wp_enqueue_script( 'wp-plugins' );

		// Gutenberg styles
		wp_enqueue_style( 'wp-edit-post' );
		wp_enqueue_style( 'wp-format-library' );

		// Keep Jetpack out of things
		add_filter(
			'jetpack_blocks_variation',
			function() {
				return 'no-post-editor';
			}
		);

		wp_tinymce_inline_scripts();
		wp_enqueue_editor();

		do_action( 'enqueue_block_editor_assets' );

		add_action( 'wp_print_footer_scripts', array( '_WP_Editors', 'print_default_editor_scripts' ), 45 );

		$this->setup_rest_api();

		set_current_screen( 'front' );
		wp_styles()->done = array( 'wp-reset-editor-styles' );

		$categories = wp_json_encode( get_block_categories( $post ) );

		if ( $categories !== false ) {
			wp_add_inline_script(
				'wp-blocks',
				sprintf( 'wp.blocks.setCategories( %s );', $categories ),
				'after'
			);
		}

		/**
		 * @psalm-suppress PossiblyFalseOperand
		 */
		wp_add_inline_script(
			'wp-blocks',
			'wp.blocks.unstable__bootstrapServerSideBlockDefinitions(' . wp_json_encode( get_block_editor_server_block_settings() ) . ');'
		);

		$this->setup_media();
	}

	/**
	 * Load any third-party blocks
	 *
	 * @return void
	 */
	private function load_extra_blocks() {
		// phpcs:ignore
		$GLOBALS['hook_suffix'] = '';

		/**
		 * @psalm-suppress MissingFile
		 */
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		/**
		 * @psalm-suppress MissingFile
		 */
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		/**
		 * @psalm-suppress MissingFile
		 */
		require_once ABSPATH . 'wp-admin/includes/post.php';

		// Fake a WP_Screen object so we can pretend we're in the block editor, and therefore other block libraries load
		set_current_screen();

		$current_screen = get_current_screen();
		if ( $current_screen ) {
			$current_screen->is_block_editor( true );
		}
	}

	/**
	 * Override some features that probably don't make sense in an isolated editor
	 *
	 * @param array $settings Settings array.
	 * @return array
	 */
	public function block_editor_settings_all( array $settings ) {
		$settings['availableLegacyWidgets']        = (object) [];
		$settings['hasPermissionsToManageWidgets'] = false;

		return $settings;
	}

	/**
	 * Set up Gutenberg editor settings
	 *
	 * @return Array
	 */
	public function get_editor_settings() {
		global $post;

		// phpcs:ignore
		$body_placeholder = apply_filters( 'write_your_story', null, $post );

		$editor_settings = array(
			'availableTemplates'                   => [],
			'disablePostFormats'                   => ! current_theme_supports( 'post-formats' ),
			/** This filter is documented in wp-admin/edit-form-advanced.php */
			// phpcs:ignore
			'titlePlaceholder'                     => apply_filters( 'enter_title_here', __( 'Add title', 'blocks-everywhere' ), $post ),
			'bodyPlaceholder'                      => $body_placeholder,
			'autosaveInterval'                     => AUTOSAVE_INTERVAL,
			'styles'                               => get_block_editor_theme_styles(),
			'richEditingEnabled'                   => user_can_richedit(),
			'postLock'                             => false,
			'supportsLayout'                       => \WP_Theme_JSON_Resolver::theme_has_support(),
			'__experimentalBlockPatterns'          => [],
			'__experimentalBlockPatternCategories' => [],
			'supportsTemplateMode'                 => current_theme_supports( 'block-templates' ),
			'enableCustomFields'                   => false,
			'generateAnchors'                      => true,
			'canLockBlocks'                        => false,
		);

		$editor_settings['__unstableResolvedAssets'] = $this->wp_get_iframed_editor_assets();

		$block_editor_context = new \WP_Block_Editor_Context( array( 'post' => $post ) );
		return get_block_editor_settings( $editor_settings, $block_editor_context );
	}

	/**
	 * Set up the Gutenberg REST API and preloaded data
	 *
	 * @return void
	 */
	public function setup_rest_api() {
		global $post;

		$post_type = 'post';

		// Preload common data.
		$preload_paths = array(
			'/',
			'/wp/v2/types?context=edit',
			'/wp/v2/taxonomies?per_page=-1&context=edit',
			'/wp/v2/themes?status=active',
			sprintf( '/wp/v2/types/%s?context=edit', $post_type ),
			sprintf( '/wp/v2/users/me?post_type=%s&context=edit', $post_type ),
			array( '/wp/v2/media', 'OPTIONS' ),
			array( '/wp/v2/blocks', 'OPTIONS' ),
		);

		/**
		 * @psalm-suppress TooManyArguments
		 */
		$preload_paths = apply_filters( 'block_editor_preload_paths', $preload_paths, $post );
		$preload_data  = array_reduce( $preload_paths, 'rest_preload_api_request', array() );

		$encoded = wp_json_encode( $preload_data );
		if ( $encoded !== false ) {
			wp_add_inline_script(
				'wp-editor',
				sprintf( 'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );', $encoded ),
				'after'
			);
		}
	}

	/**
	 * Ensure media works in Gutenberg
	 *
	 * @return void
	 */
	public function setup_media() {
		if ( ! $this->can_upload ) {
			return;
		}

		// If we've already loaded the media stuff then don't do it again
		if ( did_action( 'wp_enqueue_media' ) > 0 ) {
			return;
		}

		/**
		 * @psalm-suppress MissingFile
		 */
		require_once ABSPATH . 'wp-admin/includes/media.php';

		wp_enqueue_media();
	}

	public function wp_get_iframed_editor_assets() {
		$script_handles = array();
		$style_handles  = array(
			'wp-block-editor',
			'wp-block-library',
			'wp-edit-blocks',
		);

		if ( current_theme_supports( 'wp-block-styles' ) ) {
			$style_handles[] = 'wp-block-library-theme';
		}

		$block_registry = \WP_Block_Type_Registry::get_instance();

		foreach ( $block_registry->get_all_registered() as $block_type ) {
			if ( ! empty( $block_type->style ) ) {
				$style_handles = array_merge( $style_handles, (array) $block_type->style );
			}

			if ( ! empty( $block_type->editor_style ) ) {
				$style_handles = array_merge( $style_handles, (array) $block_type->editor_style );
			}

			if ( ! empty( $block_type->script ) ) {
				$script_handles = array_merge( $script_handles, (array) $block_type->script );
			}

			if ( ! empty( $block_type->view_script ) ) {
				$script_handles = array_merge( $script_handles, (array) $block_type->view_script );
			}
		}

		$style_handles = apply_filters( 'blocks_everywhere_editor_styles', $style_handles );
		$style_handles = array_unique( $style_handles );
		$done          = wp_styles()->done;

		ob_start();

		// We do not need reset styles for the iframed editor.
		wp_styles()->done = array( 'wp-reset-editor-styles' );
		wp_styles()->do_items( $style_handles );
		wp_styles()->done = $done;

		$styles = ob_get_clean();

		$script_handles = array_unique( apply_filters( 'blocks_everywhere_editor_scripts', $script_handles ) );
		$done           = wp_scripts()->done;

		ob_start();

		wp_scripts()->done = array();
		wp_scripts()->do_items( $script_handles );
		wp_scripts()->done = $done;

		$scripts = ob_get_clean();

		return wp_json_encode(
			[
				'styles'  => $styles,
				'scripts' => $scripts,
			]
		);
	}
}
