<?php

/**
 * Provides functions to load Gutenberg assets
 */
class GutenbergEverywhere_Editor {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'template_redirect', [ $this, 'setup_media' ] );
		add_filter( 'block_editor_settings', [ $this, 'block_editor_settings' ] );
		add_filter( 'block_editor_settings', [ $this, 'remove_theme_json' ], 20 );
	}

	// Gutenberg 10.3.2 adds detection for theme.json. If this doesn't exist in the theme then it loads 'classic.css', which overrides a bunch of P2 styles
	// Until we have proper theme.json support just remove this dependency
	public function remove_theme_json( $settings ) {
		$exclude = [
			'wp-edit-blocks' => [ 'wp-editor-classic-layout-styles' ],
			'wp-reset-editor-styles' => [ 'forms', 'common' ],
		];
		$styles = wp_styles();

		foreach ( $exclude as $handle => $deps ) {
			// Find the handle
			$style = $styles->query( $handle, 'registered' );

			if ( $style ) {
				// Remove the dependencies without breaking the parent style itself
				$style->deps = array_filter(
					$style->deps,
					function( $item ) use ( $deps ) {
						return ! in_array( $item, $deps );
					}
				);
			}
		}

		return $settings;
	}

	/**
	 * Load Gutenberg
	 *
	 * Based on wp-admin/edit-form-blocks.php
	 *
	 * @return void
	 */
	public function load() {
		$this->load_extra_blocks();

		// Gutenberg scripts
		wp_enqueue_script( 'wp-block-library' );
		wp_enqueue_script( 'wp-format-library' );
		wp_enqueue_script( 'wp-editor' );

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
	}

	/**
	 * Get the current version of Gutenberg
	 *
	 * @return String|Bool Version number, or false
	 */
	public function get_gutenberg_version() {
		if ( defined( 'GUTENBERG_PLUGIN_VERSION' ) ) {
			return GUTENBERG_PLUGIN_VERSION;
		}

		// Check for locally installed plugin
		if ( ! function_exists( 'is_plugin_active' ) ) {
			/**
			 * @psalm-suppress MissingFile
			 */
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( 'gutenberg/gutenberg.php' ) ) {
			// Assumes Gutenberg plugin is in this directory
			$data = get_plugin_data( WP_PLUGIN_DIR . '/gutenberg/gutenberg.php' );

			if ( isset( $data['Version'] ) && $data['Version'] !== 'Version' ) {
				return $data['Version'];
			}
		}

		return false;
	}

	/**
	 * Load any third-party blocks
	 *
	 * @return void
	 */
	private function load_extra_blocks() {
		// phpcs:ignore
		if ( ! is_admin() ) {
			$GLOBALS['hook_suffix'] = '';
		}

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
	public function block_editor_settings( array $settings ) {
		$settings['availableLegacyWidgets'] = (object) [];
		$settings['hasPermissionsToManageWidgets'] = false;

		// Start with no patterns
		$settings['__experimentalBlockPatterns'] = [];

		return $settings;
	}

	/**
	 * Set up Gutenberg editor settings
	 *
	 * @return Array
	 */
	public function get_editor_settings() {
		// This is copied from core
		// phpcs:disable
		global $editor_styles, $post;

		$align_wide    = get_theme_support( 'align-wide' );
		$color_palette = current( (array) get_theme_support( 'editor-color-palette' ) );
		$font_sizes    = current( (array) get_theme_support( 'editor-font-sizes' ) );

		$max_upload_size = wp_max_upload_size();
		if ( ! $max_upload_size ) {
			$max_upload_size = 0;
		}

		if ( ! WP_Theme_JSON_Resolver::theme_has_support() ) {
			$styles = array(
				array(
					'css'            => 'body { font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif }',
					'__unstableType' => 'core',
				),
			);
		} else {
			$styles = array();
		}

		$locale_font_family = esc_html_x( 'Noto Serif', 'CSS Font Family for Editor Font' );
		$styles[]           = array(
			'css' => "body { font-family: '$locale_font_family' }",
		);

		if ( $editor_styles && current_theme_supports( 'editor-styles' ) ) {
			foreach ( $editor_styles as $style ) {
				if ( preg_match( '~^(https?:)?//~', $style ) ) {
					$response = wp_remote_get( $style );
					if ( ! is_wp_error( $response ) ) {
						$styles[] = array(
							'css' => wp_remote_retrieve_body( $response ),
						);
					}
				} else {
					$file = get_theme_file_path( $style );
					if ( is_file( $file ) ) {
						$styles[] = array(
							'css'     => file_get_contents( $file ),
							'baseURL' => get_theme_file_uri( $style ),
						);
					}
				}
			}
		}

		$image_size_names = apply_filters(
			'image_size_names_choose',
			array(
				'thumbnail' => __( 'Thumbnail' ),
				'medium'    => __( 'Medium' ),
				'large'     => __( 'Large' ),
				'full'      => __( 'Full Size' ),
			)
		);

		$available_image_sizes = array();
		foreach ( $image_size_names as $image_size_slug => $image_size_name ) {
			$available_image_sizes[] = array(
				'slug' => $image_size_slug,
				'name' => $image_size_name,
			);
		}

		/**
		 * @psalm-suppress TooManyArguments
		 */
		$body_placeholder = apply_filters( 'write_your_story', __( 'Start writing or type / to choose a block' ), $post );
		$allowed_block_types = apply_filters( 'allowed_block_types', true, $post );

		/**
		 * @psalm-suppress TooManyArguments
		 */
		$editor_settings = array(
			'alignWide'              => $align_wide,
			'disableCustomColors'    => get_theme_support( 'disable-custom-colors' ),
			'disableCustomFontSizes' => get_theme_support( 'disable-custom-font-sizes' ),
			'disablePostFormats'     => ! current_theme_supports( 'post-formats' ),
			/** This filter is documented in wp-admin/edit-form-advanced.php */
			'titlePlaceholder'       => apply_filters( 'enter_title_here', __( 'Add title' ), $post ),
			'bodyPlaceholder'        => $body_placeholder,
			'isRTL'                  => is_rtl(),
			'autosaveInterval'       => AUTOSAVE_INTERVAL,
			'maxUploadFileSize'      => $max_upload_size,
			'allowedMimeTypes'       => [],
			'styles'                 => $styles,
			'imageSizes'             => $available_image_sizes,
			'richEditingEnabled'     => user_can_richedit(),
			'codeEditingEnabled'     => false,
			'allowedBlockTypes'      => $allowed_block_types,
			'__experimentalCanUserUseUnfilteredHTML' => false,
		);

		if ( false !== $color_palette ) {
			$editor_settings['colors'] = $color_palette;
		}

		if ( false !== $font_sizes ) {
			$editor_settings['fontSizes'] = $font_sizes;
		}

		/**
		 * @psalm-suppress TooManyArguments
		 */
		return apply_filters( 'block_editor_settings', $editor_settings, $post );
		// phpcs:enable
	}

	/**
	 * Set up the Gutenberg REST API and preloaded data
	 *
	 * We set the 'post' to be whatever the latest P2 post is, but we change the post ID to 0
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
		$preload_data = array_reduce( $preload_paths, 'rest_preload_api_request', array() );

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
		/**
		 * @psalm-suppress MissingFile
		 */
		require_once ABSPATH . 'wp-admin/includes/media.php';

		wp_enqueue_media();
	}
}
