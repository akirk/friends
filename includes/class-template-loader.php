<?php
/**
 * Template Loader for Plugins.
 *
 * @package   Gamajo_Template_Loader
 * @author    Gary Jones
 * @link      http://github.com/GaryJones/Gamajo-Template-Loader
 * @copyright 2013 Gary Jones
 * @license   GPL-2.0-or-later
 * @version   1.3.1
 */

namespace Friends;

if ( ! class_exists( '\Gamajo_Template_Loader_1_4_0' ) ) {
	require __DIR__ . '/class-gamajo-template-loader-1-4-0.php';
}
/**
 * Template loader.
 *
 * Originally based on functions in Easy Digital Downloads (thanks Pippin!).

 * @package Friends
 * @author  Alex Kirk
 */
class Template_Loader extends \Gamajo_Template_Loader_1_4_0 {
	/**
	 * Prefix for filter names.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $filter_prefix = 'friends';

	/**
	 * Directory name where custom templates for this plugin should be found in the theme.
	 *
	 * For example: 'your-plugin-templates'.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $theme_template_directory = 'friends';

	/**
	 * Reference to the root directory path of this plugin.
	 *
	 * Can either be a defined constant, or a relative reference from where the subclass lives.
	 *
	 * e.g. YOUR_PLUGIN_TEMPLATE or plugin_dir_path( dirname( __FILE__ ) ); etc.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $plugin_directory = FRIENDS_PLUGIN_DIR;

	/**
	 * Directory name where templates are found in this plugin.
	 *
	 * Can either be a defined constant, or a relative reference from where the subclass lives.
	 *
	 * e.g. 'templates' or 'includes/templates', etc.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	protected $plugin_template_directory = 'templates';
}
