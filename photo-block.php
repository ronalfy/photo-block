<?php // phpcs:ignore

/*
 * Plugin Name: Photo Block
 * Plugin URI: https://dlxplugins.com/plugins/photo-block/
 * Description: An easy to use, but comprehensive photo block for WordPress
 * Author: DLX Plugins
 * Version: 1.8.0
 * Requires at least: 6.9
 * Requires PHP: 8.0
 * Author URI: https://dlxplugins.com/plugins/photo-block/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: photo-block
 * Contributors: ronalfy
 */

namespace DLXPlugins\PhotoBlock;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

define( 'DLX_PHOTO_BLOCK_VERSION', '1.8.0' );
define( 'DLX_PHOTO_BLOCK_CACHE_VERSION', '1.2.0' ); // For cache busting global styles.
define( 'DLX_PHOTO_BLOCK_FILE', __FILE__ );

// Support for site-level autoloading.
if ( file_exists( __DIR__ . '/lib/autoload.php' ) ) {
	require_once __DIR__ . '/lib/autoload.php';
}

/**
 * PhotoBlock Main Class
 */
class PhotoBlock {
	/**
	 * Photo Block instance.
	 *
	 * @var PhotoBlock $instance Instance of Highlight and Share class.
	 */
	private static $instance = null;

	/**
	 * Return an instance of the class
	 *
	 * Return an instance of the PhotoBlock Class.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return PhotoBlock class instance.
	 */
	public static function get_instance() {
		if ( null == self::$instance ) { // phpcs:ignore
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Class constructor.
	 *
	 * Initialize plugin and load text domain for internationalization
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function __construct() {
		// i18n initialization.
		load_plugin_textdomain( 'photo-block', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Entry point for the plugin.
	 */
	public function plugins_loaded() {
		Blocks::run();
		Global_Styles::run();
		Rest::run();
		Admin::run();
	}
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\photo_block_instantiate' );
/**
 * Instantiate the HAS class.
 */
function photo_block_instantiate() {
	$photoblock = PhotoBlock::get_instance();
	$photoblock->plugins_loaded();
}
