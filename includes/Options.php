<?php
/**
 * Plugin Options.
 *
 * @package PhotoBlock
 */

namespace DLXPlugins\PhotoBlock;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Class Options
 */
class Options {

	/**
	 * The options key.
	 *
	 * @var string $options_key
	 */
	private static $options_key = 'photo_block_options';

	/**
	 * A list of options cached for saving.
	 *
	 * @var array $options
	 */
	private static $options = array();

	/**
	 * A flag to check if the settings have been registered.
	 *
	 * @var bool $setting_registered
	 */
	private static $setting_registered = false;

	/**
	 * Get the options for Photo Block.
	 *
	 * @since 3.0.0
	 *
	 * @param bool   $force true to retrieve options directly, false to use cached version.
	 * @param string $key The option key to retrieve.
	 *
	 * @return string|array|bool Return a string if key is set, array of options (default), or false if key is set and option is not found.
	 */
	public static function get_options( $force = false, $key = '' ) {
		$options        = self::$options;
		$stored_options = null;
		if ( ! is_array( $options ) || empty( $options ) || true === $force ) {
			$stored_options = get_option( self::$options_key, array() );
			$options        = $stored_options;
		}
		if ( false === $options || empty( $options ) ) {
			$options = self::get_defaults();
		} else {
			$options = wp_parse_args( $options, self::get_defaults() );
		}
		$options = self::remove_retired_options( $options );
		$options = Functions::sanitize_array_recursive( $options );

		// Drop stored ScreenshotOne credentials the first time options are read after removal.
		if ( is_array( $stored_options ) && self::has_retired_options( $stored_options ) ) {
			update_option( self::$options_key, $options );
		}

		self::$options = $options;

		// Return a key if set.
		if ( ! empty( $key ) ) {
			if ( isset( $options[ $key ] ) ) {
				return $options[ $key ];
			} else {
				return false;
			}
		}

		return self::$options;
	}

	/**
	 * Sanitize the options.
	 *
	 * @param string $options JSON encoded string of associative array options.
	 */
	public static function sanitize_options( $options = array() ) {

		if ( ! is_array( $options ) ) {
			$options = json_decode( $options, true );
		}

		// Get into option format.
		$options = Functions::sanitize_array_recursive( $options );
		$options = self::remove_retired_options( $options );

		// Get current options.
		$current_options = self::get_options();
		// Merge the current options with the new options.
		$options = wp_parse_args( $options, $current_options );

		// Remove any erronous values (values that are not flat).
		$options = array_filter(
			$options,
			function ( $value ) {
				return is_scalar( $value );
			}
		);

		// Return the sanitized options.
		return $options;
	}

	/**
	 * Save options for the plugin.
	 *
	 * @param array $options array of options.
	 */
	public static function update_options( $options = array() ) {
		$options       = self::sanitize_options( $options );
		self::$options = $options;
		update_option( self::$options_key, $options );
	}

	/**
	 * Get the default options for Photo Block.
	 *
	 * @since 3.0.0
	 */
	public static function get_defaults() {
		$defaults = array(
			'hideCaptionAppender' => false,
			'insertOnPaste'       => false,
			'debugModeEnabled'    => false,
		);

		/**
		 * Allow other plugins to add to the defaults.
		 *
		 * @since 1.1.0
		 *
		 * @param array $defaults An array of option defaults.
		 */
		$defaults = apply_filters( 'photo_block_options_defaults', $defaults );

		$defaults = Functions::sanitize_array_recursive( $defaults );

		return $defaults;
	}

	/**
	 * Option keys retired when ScreenshotOne was removed.
	 *
	 * @return string[] Option keys.
	 */
	private static function get_retired_option_keys() {
		return array(
			'screenshotOneEnabled',
			'screenshotOneAccessKey',
			'screenshotOneSecretKey',
			'screenshotOneAPIValid',
			'screenshotOneEnableSignedRequests',
			'screenshotOneDefaultImageFormat',
			'screenshotOneEnableAnimatedScreenshots',
			'screenshotOneTotalLimit',
			'screenshotOneAvailableRequests',
			'screenshotOneUsedRequests',
			'screenshotOneMaxImageWidth',
			'screenshotOneMaxImageHeight',
			'screenshotOneViewportWidth',
			'screenshotOneViewportHeight',
			'screenshotOneBlockCookieBanners',
			'screenshotOneBlockAds',
			'screenshotOneIgnoreHostErrors',
		);
	}

	/**
	 * Whether stored options still contain retired ScreenshotOne values.
	 *
	 * @param array $options Stored options.
	 *
	 * @return bool True when a retired key is present.
	 */
	private static function has_retired_options( $options ) {
		foreach ( self::get_retired_option_keys() as $key ) {
			if ( array_key_exists( $key, $options ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Remove retired ScreenshotOne options, including stored API credentials.
	 *
	 * @param array $options Plugin options.
	 *
	 * @return array Options without retired keys.
	 */
	private static function remove_retired_options( $options ) {
		if ( ! is_array( $options ) ) {
			return array();
		}
		foreach ( self::get_retired_option_keys() as $key ) {
			unset( $options[ $key ] );
		}
		return $options;
	}
}
