<?php
/**
 * Set up the blocks and their attributes.
 *
 * @package PhotoBlock
 */

namespace DLXPlugins\PhotoBlock;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Admin class.
 */
class Admin {

	/**
	 * Instance of the class.
	 *
	 * @var Admin
	 */
	private static $instance;

	/**
	 * Get the instance of the class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Class runner.
	 */
	public static function run() {
		// Init the admin menu.
		add_action( 'admin_menu', array( self::get_instance(), 'add_admin_menu' ) );

		// Enqueue scripts for the admin page.
		add_action( 'admin_enqueue_scripts', array( self::get_instance(), 'enqueue_scripts' ) );

		// For saving the options.
		add_action( 'wp_ajax_dlx_pb_save_options', array( self::get_instance(), 'ajax_save_options' ) );

		// For resetting the options.
		add_action( 'wp_ajax_dlx_pb_reset_options', array( self::get_instance(), 'ajax_reset_options' ) );

		// For initializing settings links on the plugins screen.
		add_action( 'admin_init', array( self::get_instance(), 'init_settings_links' ) );
	}

	/**
	 * Initialize the setting links for the plugin page.
	 */
	public function init_settings_links() {
		add_action( 'plugin_action_links_' . plugin_basename( Functions::get_plugin_file() ), array( self::get_instance(), 'plugin_settings_link' ) );
	}

	/**
	 * Adds plugin settings page link to plugin links in WordPress Dashboard Plugins Page
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Uses $prefix . "plugin_action_links_$plugin_file" action.
	 * @return array Array of settings
	 */
	public function plugin_settings_link( $settings ) {
		$setting_links = array(
			'settings' => sprintf( '<a href="%s">%s</a>', esc_url( Functions::get_settings_url() ), esc_html__( 'Settings', 'photo-block' ) ),
			'docs'     => sprintf( '<a href="%s">%s</a>', esc_url( 'https://docs.dlxplugins.com/v/photo-block/' ), esc_html__( 'Docs', 'photo-block' ) ),
			'support'  => sprintf( '<a href="%s">%s</a>', esc_url( 'https://dlxplugins.com/support/' ), esc_html__( 'Support', 'photo-block' ) ),
		);
		if ( ! is_array( $settings ) ) {
			return $setting_links;
		} else {
			return array_merge( $setting_links, $settings );
		}
	}

	/**
	 * Save the options via Ajax.
	 */
	public function ajax_save_options() {
		// Get form data.
		$form_data = filter_input( INPUT_POST, 'formData', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		$nonce = $form_data['saveNonce'] ?? false;
		if ( ! wp_verify_nonce( $nonce, 'dlx-pb-admin-save-options' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message'     => __( 'Nonce or permission verification failed.', 'photo-block' ),
					'type'        => 'critical',
					'dismissable' => true,
					'title'       => __( 'Error', 'photo-block' ),
				)
			);
		}

		// Unset nonce data from form.
		unset( $form_data['saveNonce'] );
		unset( $form_data['resetNonce'] );
		unset( $form_data['getNonce'] );

		// Get array values.
		$form_data = Functions::sanitize_array_recursive( $form_data );

		// Update options.
		Options::update_options( $form_data );

		// Send success message.
		wp_send_json_success(
			array(
				'message'     => __( 'Options saved.', 'photo-block' ),
				'type'        => 'success',
				'dismissable' => true,
			)
		);
	}

	/**
	 * Reset the options.
	 */
	public function ajax_reset_options() {
		// Get form data.
		$form_data = filter_input( INPUT_POST, 'formData', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		$nonce = $form_data['resetNonce'] ?? false;
		if ( ! wp_verify_nonce( $nonce, 'dlx-pb-admin-reset-options' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message'     => __( 'Nonce or permission verification failed.', 'photo-block' ),
					'type'        => 'error',
					'dismissable' => true,
					'title'       => __( 'Error', 'photo-block' ),
				)
			);
		}

		// Get existing options.
		$options = Options::get_options();

		// Get defaults and reset.
		$default_options = Options::get_defaults();

		Options::update_options( $default_options );

		// Pull in nonces to default options before returning.
		$default_options['saveNonce']  = wp_create_nonce( 'dlx-pb-admin-save-options' );
		$default_options['resetNonce'] = wp_create_nonce( 'dlx-pb-admin-reset-options' );

		// Send success message.
		wp_send_json_success(
			array(
				'message'     => __( 'Options reset.', 'photo-block' ),
				'type'        => 'success',
				'dismissable' => true,
				'formData'    => $default_options,
			)
		);
	}

	/**
	 * Retrieve options via Ajax.
	 */
	public function ajax_get_options() {
		// Get nonce.
		$nonce = sanitize_text_field( filter_input( INPUT_POST, 'nonce', FILTER_DEFAULT ) );

		// Verify nonce.
		$nonce_action = 'dlx-pb-settings-get-options';
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message'     => __( 'Nonce or permission verification failed.', 'photo-block' ),
					'type'        => 'error',
					'dismissable' => true,
					'title'       => __( 'Error', 'photo-block' ),
				)
			);
		}
		$options = Options::get_options();
		wp_send_json_success( $options );
	}

	/**
	 * Add the admin menu.
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Photo Block', 'photo-block' ),
			__( 'Photo Block', 'photo-block' ),
			'manage_options',
			'photo-block',
			array( self::get_instance(), 'admin_page' ),
		);
	}

	/**
	 * Enqueue scripts for the admin page.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'settings_page_photo-block' !== $hook ) {
			return;
		}

		$options = Options::get_options();

		// Enqueue main scripts.
		$deps = require Functions::get_plugin_dir( 'dist/dlx-pb-admin.asset.php' );
		wp_enqueue_script(
			'dlx-pb-admin',
			Functions::get_plugin_url( 'dist/dlx-pb-admin.js' ),
			$deps['dependencies'],
			$deps['version'],
			true
		);

		wp_localize_script(
			'dlx-pb-admin',
			'dlxPBAdmin',
			array(
				'getNonce'            => wp_create_nonce( 'dlx-pb-admin-get-options' ),
				'saveNonce'           => wp_create_nonce( 'dlx-pb-admin-save-options' ),
				'resetNonce'          => wp_create_nonce( 'dlx-pb-admin-reset-options' ),
				'restNonce'           => wp_create_nonce( 'wp_rest' ),
				'hideCaptionAppender' => (bool) $options['hideCaptionAppender'],
				'insertOnPaste'       => (bool) $options['insertOnPaste'],
				'debugModeEnabled'    => $options['debugModeEnabled'],
			)
		);
		\wp_set_script_translations( 'dlx-pb-admin', 'photo-block' );

		// Enqueue admin styles.
		wp_enqueue_style(
			'dlx-pb-admin-css',
			Functions::get_plugin_url( 'dist/dlx-pb-admin-css.css' ),
			array(),
			Functions::get_plugin_version(),
			'all'
		);
	}

	/**
	 * Render the admin page.
	 */
	public function admin_page() {
		?>
		<div class="dlx-pb-admin-wrap">
			<header class="dlx-pb-admin-header">
				<div class="dlx-pb-logo-wrapper">
					<div class="dlx-pb-logo">
						<h2 id="dlx-pb-admin-header">
							<img src="<?php echo esc_url( Functions::get_plugin_url( 'assets/admin-logo.png' ) ); ?>" alt="Photo Block" /><?php // phpcs:ignorePluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
						</h2>
					</div>
					<div class="header__btn-wrap">
						<a href="<?php echo esc_url( 'https://docs.dlxplugins.com/v/photo-block/' ); ?>" target="_blank" rel="noopener noreferrer" class="dlx-pb__btn-primary"><?php esc_html_e( 'Docs', 'photo-block' ); ?></a>
						<a href="<?php echo esc_url( 'https://dlxplugins.com/support/' ); ?>" target="_blank" rel="noopener noreferrer" class="dlx-pb__btn-primary"><?php esc_html_e( 'Support', 'photo-block' ); ?></a>
					</div>
				</div>
			</header>
			<main	main class="dlx-pb-admin-body-wrapper">
				<div class="dlx-pb-body__content">
					<div id="dlx-pb-settings">
						<p><?php esc_html_e( 'Loading...', 'photo-block' ); ?></p>
					</div>
				</div>
			</main>
		</div>
		<?php
	}
}
