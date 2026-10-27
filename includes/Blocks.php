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
 * Helper class for registering blocks.
 */
class Blocks {

	/**
	 * Main class runner.
	 */
	public static function run() {
		// Register the PhotoBlock.
		add_action( 'init', array( static::class, 'register_block' ) );
		add_action( 'enqueue_block_assets', array( static::class, 'register_block_assets' ) );
		add_action( 'enqueue_block_assets', array( static::class, 'enqueue_frontend_assets' ) );

		// Enqueue any frontend assets.
		add_action( 'wp_footer', array( static::class, 'enqueue_footer_assets' ), 999 ); // Load late so other scripts have a chance to be enqueued.

		// Add inline styles to fancybox.
		add_action( 'wp_enqueue_scripts', array( static::class, 'add_fancybox_inline_styles' ), 999 );

		// For adding the render callbacks to the blocks.
		add_action( 'block_type_metadata_settings', array( static::class, 'add_render_callbacks_to_blocks' ), 20, 2 );
	}

	/**
	 * Registers the block on server.
	 */
	public static function register_block() {

		if ( function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
			/**
			 * For WP 6.8+. This registers the blocks from the blocks-manifest.php file.
			 */
			wp_register_block_types_from_metadata_collection( Functions::get_plugin_dir( '/build' ), Functions::get_plugin_dir( '/build/blocks-manifest.php' ) );
			return;
		} elseif ( function_exists( 'wp_register_block_metadata_collection' ) ) {
			/**
			 * For WP 6.7. This registers the block collection.
			 */
			wp_register_block_metadata_collection( Functions::get_plugin_dir( '/build' ), Functions::get_plugin_dir( '/build/blocks-manifest.php' ) );
		}

		$manifest_data = require Functions::get_plugin_dir( '/build/blocks-manifest.php' );
		foreach ( array_keys( $manifest_data ) as $block_type ) {
			switch ( $block_type ) {
				case 'photo-block':
					register_block_type( Functions::get_plugin_dir( "/build/blocks/{$block_type}" ) );
					break;
				case 'photo-caption-block':
					register_block_type( Functions::get_plugin_dir( "/build/blocks/{$block_type}" ) );
					break;
			}
		}
	}

	/**
	 * Add the render callbacks to the blocks.
	 *
	 * @param array $settings The block settings.
	 * @param array $block_meta_data The block metadata.
	 * @return array The block settings.
	 */
	public static function add_render_callbacks_to_blocks( $settings, $block_meta_data ) {
		switch ( $block_meta_data['name'] ) {
			case 'dlxplugins/photo-block':
				$settings['render_callback'] = array( static::class, 'block_frontend' );
				break;
			case 'dlxplugins/photo-caption-block':
				$settings['render_callback'] = array( static::class, 'caption_frontend' );
				break;
		}
		return $settings;
	}

	/**
	 * Register frontend scripts/styles.
	 */
	public static function enqueue_frontend_assets() {
		wp_register_style(
			'dlx-photo-block-frontend-and-editor',
			Functions::get_plugin_url( 'dist/dlx-photo-block-style.css' ),
			array(),
			Functions::get_plugin_version(),
			'all'
		);
		if ( is_admin() ) {
			// Load scripts in the admin in an iframe in the block editor.
			wp_register_style(
				'dlx-css-gram',
				Functions::get_plugin_url( 'dist/dlx-css-gram.css' ),
				array(),
				Functions::get_plugin_version(),
				'all'
			);
			$filepond_asset_path = require Functions::get_plugin_dir( 'dist/dlx-filepond.asset.php' );
			wp_enqueue_script(
				'dlx-filepond',
				Functions::get_plugin_url( 'dist/dlx-filepond.js' ),
				$filepond_asset_path['dependencies'],
				$filepond_asset_path['version'],
				false
			);
			wp_localize_script(
				'dlx-filepond',
				'dlxFilepond',
				array(
					'restUrl'   => rest_url( 'dlxplugins/photo-block/v1' ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
				)
			);
			wp_enqueue_style(
				'dlx-filepond',
				Functions::get_plugin_url( 'dist/dlx-filepond.css' ),
				array(),
				$filepond_asset_path['version'],
				'all'
			);
			wp_enqueue_style(
				'dlx-react-crop',
				Functions::get_plugin_url( 'dist/dlx-react-crop.css' ),
				array(),
				Functions::get_plugin_version(),
				'all'
			);
			wp_register_style(
				'dlx-photo-block-frontend-and-editor',
				Functions::get_plugin_url( 'dist/dlx-photo-block-style.css' ),
				array(),
				Functions::get_plugin_version(),
				'all'
			);
			wp_register_style(
				'dlx-photo-block-editor-css',
				Functions::get_plugin_url( 'build/index.css' ),
				array(),
				Functions::get_plugin_version(),
				'all'
			);
		}
	}

	/**
	 * Register all block assets.
	 */
	public static function register_block_assets() {
		wp_register_style(
			'dlx-photo-block-frontend-and-editor',
			Functions::get_plugin_url( 'dist/dlx-photo-block-style.css' ),
			array(),
			Functions::get_plugin_version(),
			'all'
		);

		wp_register_script(
			'dlx-photo-block-editor',
			Functions::get_plugin_url( 'build/index.js' ),
			array(),
			Functions::get_plugin_version(),
			true
		);

		// Get supported innerBlocks for the Photo Caption Block.
		$caption_innerblocks_supported = array(
			'core/paragraph',
			'core/heading',
			'core/list',
			'core/quote',
			'core/pullquote',
			'core/verse',
			'core/preformatted',
			'core/spacer',
			'core/separator',
			'core/button',
			'core/columns',
			'core/group',
		);
		/**
		 * Filter the supported innerBlocks for the Photo Caption Block.
		 *
		 * @param array $caption_innerblocks_supported Array of supported innerBlocks.
		 */
		$caption_innerblocks_supported = apply_filters( 'dlx_photo_block_caption_innerblocks_supported', $caption_innerblocks_supported );

		// Get post types.
		$post_types       = get_post_types(
			array(
				'public' => true,
			)
		);
		$post_type_return = array();
		foreach ( $post_types as $index => $post_type ) {
			// Exclude non-standard post types.
			if ( in_array( $post_type, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) {
				continue;
			}
			$post_type_label          = $post_type;
			$post_type_label_singular = $post_type;
			$post_type_data           = get_post_type_object( $post_type );
			if ( isset( $post_type_data->label ) && ! empty( $post_type_data->label ) ) {
				$post_type_label = $post_type_data->label;
			}
			// Get singular label (if exists).
			if ( isset( $post_type_data->labels->singular_name ) && ! empty( $post_type_data->labels->singular_name ) ) {
				$post_type_label_singular = $post_type_data->labels->singular_name;
			}
			// If post type singular is still empty, get uppercase of post type slug.
			if ( empty( $post_type_label_singular ) ) {
				$post_type_label_singular = ucwords( str_replace( '_', ' ', $post_type ) );
			}
			$post_type_return[] = array(
				'value'    => $post_type,
				'label'    => $post_type_label,
				'singular' => $post_type_label_singular,
			);
		}

		// Get options.
		$options = Options::get_options();

		// Get localised vars to return for JS parsing.
		$localized_vars = array(
			'restUrl'                 => rest_url( 'dlxplugins/photo-block/v1' ),
			'restNonce'               => wp_create_nonce( 'wp_rest' ),
			'captionInnerBlocks'      => $caption_innerblocks_supported,
			'imageSizes'              => Functions::get_all_image_sizes(),
			'palette'                 => functions::get_theme_color_palette(),
			'postTypes'               => $post_type_return,
			'defaultImagePlacheolder' => Functions::get_plugin_url( 'assets/sample-image-ron-h-phoenix.jpg' ),
			'blockPreviewImage'       => Functions::get_plugin_url( 'assets/dlx-photo-block-preview.jpg' ),
			'settings'                => $options,
			'supportedQueryBlocks'    => Functions::get_supported_query_blocks(),
			'siteUrl'                 => esc_url_raw( rtrim( site_url(), '/' ) ),
		);

		// Add inline script to detect user role.
		$inline_vars = array(
			'canUploadFiles'       => current_user_can( 'upload_files' ), /* contributor and above */
			'canSavePresets'       => current_user_can( 'edit_others_posts' ), /* author and above */
			'canSetDefaultPresets' => current_user_can( 'edit_others_posts' ), /* editor and above */
			'canModifySettings'    => current_user_can( 'edit_others_posts' ), /* editor and above */
			'restNonce'            => wp_create_nonce( 'wp_rest' ),
		);

		/**
		 * Filter the localized variables for the Photo Block.
		 *
		 * @param array $localized_vars Array of localized variables.
		 */
		$localized_vars = apply_filters( 'dlx_photo_block_localized_vars', $localized_vars );

		wp_localize_script(
			'dlx-photo-block-editor',
			'photoBlock',
			$localized_vars
		);

		// Inline vars to avoid caching of scripts.
		wp_add_inline_script(
			'dlx-photo-block-editor',
			'var photoBlockUser = ' . wp_json_encode( $inline_vars ) . ';',
			'before'
		);

		wp_register_style(
			'dlx-photo-block-editor-css',
			Functions::get_plugin_url( 'build/index.css' ),
			array(),
			Functions::get_plugin_version(),
			'all'
		);
	}

	/**
	 * Output Photo caption block on the front-end.
	 *
	 * @param array    $attributes          Array of attributes for the Gutenberg block.
	 * @param string   $innerblocks_content The inner blocks content.
	 * @param WP_Block $block               The caption block content and attributes.
	 */
	public static function caption_frontend( $attributes, $innerblocks_content, $block ) {
		$doing_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
		if ( is_admin() && ! $doing_ajax ) {
			return;
		}

		// Determine if we want to execute this markup. Ignore for REST and admin requests.
		$can_output = true;
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			$can_output = false;
		}

		// Let's get the global style, if any.
		$has_global_style       = false;
		$global_style_css_class = '';
		$global_style_slug      = $attributes['globalStyle'] ?? false;

		if ( 'none' !== $global_style_slug && '' !== $global_style_slug ) {
			// Try to get global style from cache.
			$global_style = wp_cache_get( 'dlx_pb_global_style_' . $global_style_slug, 'dlx_photo_block' ); // can be WP_Post or null.

			// Get global style by slug.
			if ( false === $global_style ) {
				$global_style = get_page_by_path( $global_style_slug, OBJECT, 'dlx_pb_global_styles' );

				if ( null !== $global_style ) {
					$has_global_style = true;

					// Get the CSS classname.
					$global_style_css_class = get_post_meta( $global_style->ID, '_dlx_pb_css_class', true );
				}

				// Set object cache.
				wp_cache_set( 'dlx_pb_global_style_' . $global_style_slug, $global_style, 'photo-block', 60 * 60 );
			}

			// If we have a global style, get the attributes.
			if ( $global_style ) {
				$global_style_content = json_decode( $global_style->post_content, true );

				// Get photo attributes.
				$caption_attributes = $global_style_content['captionAttributes'] ?? array();
				$caption_attributes = Functions::sanitize_array_recursive( $caption_attributes );

				if ( ! empty( $caption_attributes ) ) {
					$attributes = array_replace_recursive( $attributes, $caption_attributes );
				}
			}
		}

		// First, let's determine if we're in a query loop.
		$is_in_query_loop = Functions::is_in_query_loop( $block );
		$context          = $block->context ?? array();
		$current_post_id  = $block->context['postId'] ?? 0;

		/**
		 * Filter whether the Photo block can output. Attempt not to load in admin.
		 *
		 * @param bool     $can_output Whether the Photo block caption can output.
		 * @param array    $attributes Array of attributes for the Gutenberg block.
		 * @param string   $innerblocks_content The inner blocks content.
		 * @param WP_Block $block The caption block content and attributes.
		 * @param array    $context The block context variables.
		 */
		$can_output = apply_filters( 'dlx_photo_block_can_output_caption', $can_output, $attributes, $innerblocks_content, $block, $context );
		if ( ! $can_output ) {
			return;
		}

		// Get unique ID from parent context.
		$unique_id = $context['photo-block/uniqueId'] ?? '';

		// Let's sanitize the attributes.
		$caption_raw = $attributes['captionManual'] ?? '';
		$attributes  = Functions::sanitize_array_recursive( $attributes );

		// Get caption mode.
		$mode = $attributes['mode'];

		// Begin caption classes.
		$caption_classes = array(
			'dlx-photo-block__caption',
		);
		// See if smart styles are enabled.
		if ( 'advanced' === $mode && ! $is_in_query_loop ) {
			$caption_classes[] = 'has-smart-styles';
		}

		// Get the image size.
		$image_size = $block->context['photo-block/imageSize'] ?? 'full';

		// Build image data. (only applicable if in data mode).
		$image_data = false;

		// Let's get image data and modify the attributes if in query loop or data mode.
		if ( $is_in_query_loop ) {
			// Get featured image from post.
			$maybe_featured_image_id = get_post_thumbnail_id( $current_post_id );
			if ( ! $maybe_featured_image_id ) {
				// Try to get fallback image.
				$has_fallback_image = $block->context['photo-block/dataHasFallbackImage'] ?? false;
				if ( $has_fallback_image ) {
					// Try to get image ID.
					$fallback_id = $block->context['photo-block/dataFallbackImage']['id'] ?? 0;
					if ( $fallback_id ) {
						// Try to get caption of image.
						$caption = get_post( $fallback_id )->post_excerpt;
					}
				}
			} else {
				$caption = get_post( $maybe_featured_image_id )->post_excerpt;
			}
		} else {
			if ( 'single' === $mode ) {
				$caption = $caption_raw;
			} else {
				$caption = $innerblocks_content;
			}
		}

		// If no caption, return early.
		if ( empty( $caption ) ) {
			return;
		}

		// Begin caption overlay classes.
		$caption_overlay_styles   = array( 'dlx-photo-block__caption-overlay dlx-photo-block__caption' );
		$caption_overlay_styles[] = 'is-overlay' === $attributes['captionPosition'] ? 'is-overlay' : '';
		$caption_overlay_styles[] = 'bottom' === $attributes['overlayCaptionVerticalPosition'] ? 'caption-vertical-bottom' : '';
		$caption_overlay_styles[] = 'middle' === $attributes['overlayCaptionVerticalPosition'] ? 'caption-vertical-middle' : '';
		$caption_overlay_styles[] = 'top' === $attributes['overlayCaptionVerticalPosition'] ? 'caption-vertical-top' : '';
		$caption_overlay_styles[] = 'left' === $attributes['overlayCaptionHorizontalPosition'] ? 'caption-horizontal-left' : '';
		$caption_overlay_styles[] = 'center' === $attributes['overlayCaptionHorizontalPosition'] ? 'caption-horizontal-center' : '';
		$caption_overlay_styles[] = 'right' === $attributes['overlayCaptionHorizontalPosition'] ? 'caption-horizontal-right' : '';
		$caption_overlay_styles[] = 'bottom' === $attributes['overlayVerticalPosition'] ? 'overlay-vertical-bottom' : '';
		$caption_overlay_styles[] = 'middle' === $attributes['overlayVerticalPosition'] ? 'overlay-vertical-middle' : '';
		$caption_overlay_styles[] = 'top' === $attributes['overlayVerticalPosition'] ? 'overlay-vertical-top' : '';
		$caption_overlay_styles[] = 'left' === $attributes['overlayHorizontalPosition'] ? 'overlay-horizontal-left' : '';
		$caption_overlay_styles[] = 'center' === $attributes['overlayHorizontalPosition'] ? 'overlay-horizontal-center' : '';
		$caption_overlay_styles[] = 'right' === $attributes['overlayHorizontalPosition'] ? 'overlay-horizontal-right' : '';

		// Set caption position if aligned.
		$caption_overlay_styles[] = 'align' . $attributes['captionAlign'];

		// Set hover overlay options.
		$caption_hover_overlay_styles   = array( 'dlx-photo-block__caption-wrapper' );
		$caption_hover_overlay_styles[] = ( 'overlay' === $attributes['captionPosition'] && (bool) $attributes['overlayDisplayOnHover'] ) ? 'overlay-display-hover' : '';
		if ( 'slide-down' === $attributes['overlayDisplayAnimation'] ) {
			$caption_hover_overlay_styles[] = 'overlay-slide-down';
		} elseif ( 'slide-up' === $attributes['overlayDisplayAnimation'] ) {
			$caption_hover_overlay_styles[] = 'overlay-slide-up';
		} elseif ( 'slide-left' === $attributes['overlayDisplayAnimation'] ) {
			$caption_hover_overlay_styles[] = 'overlay-slide-left';
		} elseif ( 'slide-right' === $attributes['overlayDisplayAnimation'] ) {
			$caption_hover_overlay_styles[] = 'overlay-slide-right';
		} else {
			$caption_hover_overlay_styles[] = 'overlay-fade';
		}
		$caption_hover_overlay_styles[] = 'overlay-type-' . $attributes['overlayBackgroundType'];
		if ( (bool) $attributes['hideOnMobile'] ) {
			$caption_hover_overlay_styles[] = 'hide-on-mobile';
		}
		if ( (bool) $attributes['hideOnTablet'] ) {
			$caption_hover_overlay_styles[] = 'hide-on-tablet';
		}
		if ( (bool) $attributes['hideOnDesktop'] ) {
			$caption_hover_overlay_styles[] = 'hide-on-desktop';
		}

		/**
		 * Filter the caption overlay styles.
		 *
		 * @param array $caption_overlay_styles The caption overlay styles.
		 * @param array $attributes             The block attributes.
		 * @param array $context                The block context variables.
		 *
		 * @since 1.0.0
		 */
		$caption_overlay_styles = apply_filters( 'dlx_photo_block_caption_overlay_styles', $caption_overlay_styles, $attributes, $context );

		// Zero out caption if hide caption is true.
		if ( (bool) $attributes['hideCaption'] && 'overlay' === $attributes['captionPosition'] ) {
			$caption = '';
		}

		ob_start();
		?>
		<?php
		if ( 'overlay' === $attributes['captionPosition'] ) {
			?>
				<div class="dlx-photo-block__caption--has-overlay">
			<?php
		}
		if ( 'overlay' !== $attributes['captionPosition'] ) {
			$caption_hover_overlay_styles = array( 'dlx-photo-block__caption-wrapper' );
			$caption_overlay_styles       = array(
				'dlx-photo-block__caption',
			);
			$caption_overlay_styles       = array_merge(
				$caption_overlay_styles,
				$caption_classes
			);
		}
		?>
			<div class="<?php echo esc_attr( implode( ' ', $caption_hover_overlay_styles ) ); ?> <?php echo esc_attr( implode( ' ', $caption_overlay_styles ) ); ?>">
				<figcaption class="<?php echo esc_attr( implode( ' ', $caption_overlay_styles ) ); ?> <?php echo implode( ' ', $caption_classes ); ?>">
					<div class="dlx-photo-block__caption-inner"><?php echo wp_kses( $caption, Functions::get_kses_allowed_html( true, true ) ); ?></div>
				</figcaption>
			</div>
		<?php
		if ( 'overlay' === $attributes['captionPosition'] ) {
			?>
				</div>
			<?php
		}
		$css_output = '';
		if ( ! $has_global_style ) {
			$css_output = Functions::generate_photo_block_caption_css( $attributes, $block->context['photo-block/uniqueId'] );
		}

		if ( ! $has_global_style ) {
			wp_register_style(
				'dlx-photo-block-caption-' . $unique_id,
				'',
				array(),
				Functions::get_plugin_version(),
				'all'
			);
			wp_add_inline_style(
				'dlx-photo-block-caption-' . $unique_id,
				esc_html( $css_output )
			);
			add_action(
				'wp_footer',
				function () use ( $unique_id ) {
					wp_print_styles( array( 'dlx-photo-block-caption-' . $unique_id ) );
				},
				100
			);
			?>
			<?php
		}
		$caption = ob_get_clean();
		$caption = wp_kses( $caption, Functions::get_kses_allowed_html( true, true ) );
		return $caption;
	}

	/**
	 * Output Photo block on the front-end.
	 *
	 * @param array    $attributes          Array of attributes for the Gutenberg block.
	 * @param string   $innerblocks_content The inner blocks content.
	 * @param WP_Block $block               The photo block content and attributes.
	 */
	public static function block_frontend( $attributes, $innerblocks_content, $block ) {
		$doing_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
		if ( is_admin() && ! $doing_ajax ) {
			return;
		}

		// Determine if we want to execute this markup. Ignore for REST and admin requests.
		$can_output = true;
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			$can_output = false;
		}

		/**
		 * Filter whether the Photo block can output. Attempt not to load in admin.
		 *
		 * @param bool     $can_output Whether the Photo block can output.
		 * @param array    $attributes Array of attributes for the Gutenberg block.
		 * @param string   $innerblocks_content The inner blocks content.
		 * @param WP_Block $block The photo block content and attributes.
		 *
		 * @since 1.0.0
		 */
		$can_output = apply_filters( 'dlx_photo_block_can_output', $can_output, $attributes, $innerblocks_content, $block );
		if ( ! $can_output ) {
			return;
		}

		// Let's get the global style, if any.
		$has_global_style       = false;
		$global_style_css_class = '';
		$global_style_slug      = $attributes['globalStyle'] ?? false;

		// Set aspect ratio, if not set.
		if ( ! isset( $attributes['photoAspectRatio'] ) || empty( $attributes['photoAspectRatio'] ) ) {
			$attributes['photoAspectRatio'] = array(
				'desktop' => 'unset',
				'tablet'  => 'unset',
				'mobile'  => 'unset',
			);
		}

		if ( 'none' !== $global_style_slug && '' !== $global_style_slug ) {
			// Try to get global style from cache.
			$global_style = wp_cache_get( 'dlx_pb_global_style_' . $global_style_slug, 'photo-block' ); // can be WP_Post or null.

			// Get global style by slug.
			if ( false === $global_style ) {
				$global_style = get_page_by_path( $global_style_slug, OBJECT, 'dlx_pb_global_styles' );

				if ( null !== $global_style ) {
					$has_global_style = true;

					// Get the CSS classname.
					$global_style_css_class = get_post_meta( $global_style->ID, '_dlx_pb_css_class', true );
				}

				// Set object cache.
				wp_cache_set( 'dlx_pb_global_style_' . $global_style_slug, $global_style, 'photo-block', 60 * 60 );
			}

			// If we have a global style, get the attributes.
			if ( $global_style ) {
				$global_style_content = json_decode( $global_style->post_content, true );
				// Get the CSS classname.
				$global_style_css_class = get_post_meta( $global_style->ID, '_dlx_pb_css_class', true );

				// Get photo attributes.
				$photo_attributes = $global_style_content['photoAttributes'] ?? array();
				$photo_attributes = Functions::sanitize_array_recursive( $photo_attributes );

				// Override image size if overriden.
				if ( isset( $attributes['imageSizeOverride'] ) && false !== (bool) $attributes['imageSizeOverride'] ) {
					$photo_attributes['imageSize'] = $attributes['imageSize'];
				}

				// Override link target if overridden.
				if ( isset( $attributes['mediaLinkOverride'] ) && false !== (bool) $attributes['mediaLinkOverride'] ) {
					$photo_attributes['mediaLinkType']       = $attributes['mediaLinkType'];
					$photo_attributes['mediaLinkTitle']      = $attributes['mediaLinkTitle'];
					$photo_attributes['mediaLinkUrl']        = $attributes['mediaLinkUrl'];
					$photo_attributes['mediaLinkNewTab']     = $attributes['mediaLinkNewTab'];
					$photo_attributes['mediaLinkAnchorId']   = $attributes['mediaLinkAnchorId'];
					$photo_attributes['mediaLinkClass']      = $attributes['mediaLinkClass'];
					$photo_attributes['mediaLinkRel']        = $attributes['mediaLinkRel'];
					$photo_attributes['lightboxCaption']     = $attributes['lightboxCaption'];
					$photo_attributes['lightboxEnabled']     = $attributes['lightboxEnabled'];
					$photo_attributes['lightboxShowCaption'] = $attributes['lightboxShowCaption'];
				}

				if ( ! empty( $photo_attributes ) ) {
					$attributes = array_replace_recursive( $attributes, $photo_attributes );
				}

				// Get global style location in uploads dir.
				$upload_dir     = wp_upload_dir();
				$upload_url     = $upload_dir['baseurl'] . '/photo-block/global-styles.css';
				$upload_version = sanitize_key( get_option( 'dlx_pb_global_style_version', 0 ) );

				// Register the style.
				wp_register_style(
					'dlx-photo-block-global-styles',
					$upload_url,
					array(),
					$upload_version,
					'all'
				);

				if ( ! wp_style_is( 'dlx-photo-block-global-styles', 'done' ) ) {
					add_action(
						'wp_footer',
						function () {
							wp_print_styles( array( 'dlx-photo-block-global-styles' ) );
						},
						100
					);
				}
			}
		}

		if ( ! wp_style_is( 'dlx-photo-block-frontend-and-editor', 'done' ) ) {
			wp_register_style(
				'dlx-photo-block-frontend-and-editor',
				Functions::get_plugin_url( 'dist/dlx-photo-block-style.css' ),
				array(),
				Functions::get_plugin_version(),
				'all'
			);
			if ( ! wp_style_is( 'dlx-photo-block-frontend-and-editor', 'done' ) ) {
				add_action(
					'wp_footer',
					function () {
						wp_print_styles( array( 'dlx-photo-block-frontend-and-editor' ) );
					},
					100
				);
			}
		}

		// Let's sanitize the attributes.
		$attributes = Functions::sanitize_array_recursive( $attributes );

		// Get the unique ID for the image.
		$unique_id = $attributes['uniqueId'];

		// Begin buffer.
		ob_start();

		// First, let's determine if we're in a query loop.
		$is_in_query_loop = (bool) $attributes['inQueryLoop'] ?? false;
		$context          = $block->context ?? array();
		$current_post_id  = $block->context['postId'] ?? 0;

		// Get the image size.
		$image_size = $attributes['imageSize'] ?? 'full';

		// Build image data. (only applicable if in data mode).
		$image_data = false;

		// Let's get image data and modify the attributes if in query loop or data mode.
		if ( $is_in_query_loop ) {
			// get featured image.

			$maybe_featured_image_id = get_post_thumbnail_id( $current_post_id );
			$maybe_featured_image    = false;

			// If no image, then try to get the fallback.
			if ( ! $maybe_featured_image_id ) {
				if ( (bool) $attributes['dataHasFallbackImage'] && '' !== $attributes['dataFallbackImage']['id'] ) {
					$attributes['imageData'] = $attributes['dataFallbackImage'];
				} else {
					// No image. Return.
					return;
				}
			} else {
				$featured_image                     = wp_get_attachment_image_src( $maybe_featured_image_id, $image_size );
				$attributes['imageData']['id']      = $maybe_featured_image_id;
				$attributes['imageData']['url']     = $featured_image[0];
				$attributes['imageData']['width']   = $featured_image[1];
				$attributes['imageData']['height']  = $featured_image[2];
				$attributes['imageData']['alt']     = get_post_meta( $maybe_featured_image_id, '_wp_attachment_image_alt', true );
				$attributes['imageData']['title']   = get_the_title( $maybe_featured_image_id );
				$attributes['imageData']['caption'] = get_post( $maybe_featured_image_id )->post_excerpt;
			}
		}

		// Get alt/title attributes.
		$image_alt   = $attributes['imageData']['alt'] ?? '';
		$image_title = $attributes['imageData']['title'] ?? '';

		// Let's get the image information.
		$image_markup = '';
		// Get the image ID.
		$image_id = absint( $attributes['imageData']['id'] ?? 0 );

		// Get any image classes.
		$image_classes = explode( ' ', $attributes['imageCSSClasses'] ) ?? array();

		/**
		 * Filter image classes.
		 *
		 * @param array $image_classes The image classes.
		 * @param array $attributes    The block attributes.
		 * @param array $context       The block context variables.
		 *
		 * @since 1.0.0
		 */
		$image_classes   = apply_filters( 'dlx_photo_block_image_classes', $image_classes, $attributes, $context );
		$image_classes[] = '' !== $global_style_css_class ? $global_style_css_class : '';

		// Determine if lazy loading is on.
		$skip_lazy_loading = (bool) $attributes['skipLazyLoading'] ?? false;

		// Get data-attr attributes.
		$image_data_attributes = array();
		if ( ! empty( $attributes['customAttributes'] ) ) {
			foreach ( $attributes['customAttributes'] as $attribute ) {
				$image_data_attributes[ sanitize_key( $attribute['name'] ) ] = esc_attr( $attribute['value'] );
			}
		}

		// Get whether a CSS Gram filter is enabled.
		$css_gram_filter = $attributes['cssGramFilter'] ?? 'none';
		if ( 'none' !== $css_gram_filter ) {
			$image_classes[] = 'has-css-gram';
			$image_classes[] = 'photo-block-' . $css_gram_filter;

			// Register CSS Gram stylesheet.
			if ( ! wp_style_is( 'dlx-css-gram', 'registered' ) ) {
				wp_register_style(
					'dlx-css-gram',
					Functions::get_plugin_url( 'dist/dlx-css-gram.css' ),
					array(),
					Functions::get_plugin_version(),
					'all'
				);
			}

			// Print out the CSS Gram stylesheet.
			if ( ! wp_style_is( 'dlx-css-gram', 'done' ) ) {
				wp_print_styles( 'dlx-css-gram' );
			}
		}

		// Get lightbox attributes.
		$lightbox_enabled = (bool) $attributes['lightboxEnabled'] ?? false;

		// Check if we're in URL mode.
		$image_markup = '';
		switch ( $attributes['photoMode'] ) { /* can be image, featuredImage, data, or url. */
			case 'url':
				$image_markup = '<img width="' . $attributes['imageData']['width'] . '" height="' . $attributes['imageData']['height'] . '" src="' . esc_url( $attributes['imageData']['url'] ) . '" alt="' . esc_attr( $image_alt ) . '" class="dlx-photo-block__image ' . esc_attr( implode( ' ', $image_classes ) ) . '" loading="' . ( $skip_lazy_loading ? 'auto' : 'lazy' ) . '">';
				break;
			case 'featuredImage':
			case 'image':
			case 'photo':
				$image_args = array(
					'class'   => 'dlx-photo-block__image ' . esc_attr( implode( ' ', $image_classes ) ),
					'loading' => $skip_lazy_loading ? 'auto' : 'lazy',
					'alt'     => $image_alt,
					'title'   => $image_title,
				);
				if ( $skip_lazy_loading ) {
					$image_args['data-skip-lazy'] = 'true';
				}

				$image_markup = wp_get_attachment_image(
					$image_id,
					$image_size,
					false,
					array_merge(
						$image_args,
						$image_data_attributes
					)
				);
				break;
		}

		/**
		 * Filter image markup in initial image form before other wrappers are added.
		 *
		 * @param string $image_markup The image markup.
		 * @param string $photo_mode   The photo mode. Can be image, featuredImage, data, or url.
		 * @param array  $attributes   The block attributes.
		 * @param array  $context      The block context variables.
		 *
		 * @since 1.0.0
		 */
		$image_markup = apply_filters( 'dlx_photo_block_image_markup_pre', $image_markup, $attributes['photoMode'], $attributes, $context );

		// Get the caption.
		if ( $attributes['lightboxCaption'] ) {
			$caption = $attributes['lightboxCaption'];
		} else {
			$caption = wp_kses( $innerblocks_content, Functions::get_kses_allowed_html( true, true ) );
		}

		// Get the image link type.
		$media_link_type = $attributes['mediaLinkType'] ?? 'none';
		if ( $is_in_query_loop ) {
			$media_link_type = $attributes['dataMediaLinkSource'] ?? 'none';
		}
		$media_link_url  = '';
		$media_link_atts = array();

		switch ( $media_link_type ) {
			case 'none':
				$media_link_url = '';
				break;
			case 'image':
			case 'imageFile':
				if ( 0 === $image_id ) {
					$media_link_url = $attributes['imageData']['url'] ?? '';
				} else {
					$media_link_url = wp_get_attachment_url( $image_id );
				}
				if ( $media_link_url ) {

					if ( $lightbox_enabled ) {
						$media_link_atts['data-fancybox'] = 'true';
						$media_link_atts['data-caption']  = esc_attr( $caption );

						// Add gallery slug if set.
						$media_link_lightbox_gallery_slug = esc_attr( $attributes['lightboxGallerySlug'] ?? '' );
						if ( ! empty( $media_link_lightbox_gallery_slug ) ) {
							$media_link_atts['data-fancybox'] = $media_link_lightbox_gallery_slug;
							if ( 0 !== $image_id ) {
								$media_link_atts['data-thumb'] = wp_get_attachment_image_src( $image_id, 'thumbnail' )[0];
							}
						}

						// Register the lightbox script/style. Check wp_footer.
						wp_register_script(
							'dlx-photo-block-fancybox-js',
							Functions::get_plugin_url( 'assets/fancybox/fancybox.js' ),
							array(),
							Functions::get_plugin_version(),
							true
						);
						wp_register_script(
							'dlx-photo-block-fancybox-js-inline',
							false,
							array(),
							Functions::get_plugin_version(),
							true
						);
						$fancybox_slug = 'none' === $media_link_lightbox_gallery_slug ? '' : $media_link_lightbox_gallery_slug;
						wp_add_inline_script(
							'dlx-photo-block-fancybox-js-inline',
							'document.addEventListener("DOMContentLoaded", function() {
								var selector = ".' . esc_attr( $unique_id ) . ' [data-fancybox]' . ( 'none' !== $fancybox_slug ? ', [data-fancybox=\"' . esc_js( $fancybox_slug ) . '\"]' : '' ) . '";
								if ( typeof Fancybox !== "undefined" ) {
									Fancybox.bind( selector );
								} else if ( typeof jQuery !== "undefined" && typeof jQuery.fancybox !== "undefined" ) {
									jQuery(selector).fancybox();
								}
							});'
						);

						// Get caption.
						$caption_enabled = (bool) $attributes['lightboxShowCaption'] ?? false;
						$caption_custom  = $attributes['lightboxCaption'] ?? '';
						if ( $caption_enabled && ! empty( $caption_custom ) ) {
							// todo - need to get regular single-line caption if available.
							$media_link_atts['data-caption'] = esc_attr( $caption_custom );
						}
					}
				}
				break;
			case 'imageAttachmentPage':
				$media_link_url = get_permalink( $image_id );
				break;
			case 'custom':
				$media_link_url = $attributes['mediaLinkUrl'] ?? '';
				// Get lightbox attributes.
				$custom_link_lightbox_enabled = (bool) $attributes['customLinkLightboxEnabled'] ?? false;
				if ( $custom_link_lightbox_enabled && preg_match( '/\.(jpg|jpeg|gif|png|webp|avif)$/i', $media_link_url ) ) {
					$media_link_atts['data-fancybox'] = 'true';
					$media_link_atts['data-caption']  = esc_attr( $caption );

					// Register the lightbox script/style. Check wp_footer.
					wp_register_script(
						'dlx-photo-block-fancybox-js',
						Functions::get_plugin_url( 'assets/fancybox/fancybox.js' ),
						array(),
						Functions::get_plugin_version(),
						true
					);
					wp_register_script(
						'dlx-photo-block-fancybox-js-inline',
						false,
						array(),
						Functions::get_plugin_version(),
						true
					);
					wp_add_inline_script(
						'dlx-photo-block-fancybox-js-inline',
						'document.addEventListener("DOMContentLoaded", function() { if ( typeof jQuery !== "undefined" && typeof jQuery.fancybox !== "undefined" ) { jQuery("#' . esc_js( $unique_id ) . '[data-fancybox]").fancybox() } else if ( typeof Fancybox !== "undefined" ) { Fancybox.bind("#' . esc_js( $unique_id ) . ' [data-fancybox]"); }  });'
					);

					// Get caption.
					$custom_link_lightbox_caption_enabled = (bool) $attributes['customLinkLightboxShowCaption'] ?? false;
					$custom_link_lightbox_caption_custom  = $attributes['customLinkLightboxCaption'] ?? '';
					if ( $custom_link_lightbox_caption_enabled && ! empty( $custom_link_lightbox_caption_custom ) ) {
						// todo - need to get regular single-line caption if available.
						$media_link_atts['data-caption'] = esc_attr( $custom_link_lightbox_caption_custom );
					}
				}
				break;
			case 'imageData':
				if ( $is_in_query_loop ) {
					// Image file of current post.
					$media_link_url = wp_get_attachment_url( $image_id );
				} else {
					// Image file of featured image.
					$media_link_url = $attributes['imageData']['url'] ?? '';
				}
				$lightbox_enabled = (bool) $attributes['lightboxEnabled'] ?? false;
				if ( $lightbox_enabled ) {
					$media_link_atts['data-fancybox'] = 'true';
					$media_link_atts['data-caption']  = esc_attr( $caption );

					// Register the lightbox script/style. Check wp_footer.
					wp_register_script(
						'dlx-photo-block-fancybox-js',
						Functions::get_plugin_url( 'assets/fancybox/fancybox.js' ),
						array(),
						Functions::get_plugin_version(),
						true
					);
					wp_register_script(
						'dlx-photo-block-fancybox-js-inline',
						false,
						array(),
						Functions::get_plugin_version(),
						true
					);
					wp_add_inline_script(
						'dlx-photo-block-fancybox-js-inline',
						'document.addEventListener("DOMContentLoaded", function() { if ( typeof jQuery !== "undefined" && typeof jQuery.fancybox !== "undefined" ) { jQuery("#' . esc_js( $unique_id ) . '[data-fancybox]").fancybox() } else if ( typeof Fancybox !== "undefined" ) { Fancybox.bind("#' . esc_js( $unique_id ) . ' [data-fancybox]"); }  });'
					);

					// Get caption.
					$caption_enabled = (bool) $attributes['lightboxShowCaption'] ?? false;
					$caption_custom  = $attributes['lightboxCaption'] ?? '';
					if ( $caption_enabled && ! empty( $caption_custom ) ) {
						// todo - need to get regular single-line caption if available.
						$media_link_atts['data-caption'] = $caption_custom;
					}
				}
				break;
			case 'postPermalink':
				$media_link_url = get_permalink( $current_post_id );
				break;
		}

		/**
		 * Filter the Media Link URL.
		 *
		 * @param string $media_link_url The media link URL.
		 * @param array  $attributes     The block attributes.
		 * @param array  $context        The block context variables.
		 *
		 * @since 1.0.0
		 */
		$media_link_url = apply_filters( 'dlx_photo_block_media_link_url', $media_link_url, $attributes, $context );

		// Fill in the link attributes.
		if ( 'none' !== $media_link_type ) {
			// Set new tab attribute.
			$open_new_tab = (bool) $attributes['mediaLinkNewTab'] ?? false;
			if ( $open_new_tab ) {
				$media_link_atts['target'] = '_blank';
			}

			// Set title attribute.
			$title_attr = $attributes['mediaLinkTitle'] ?? '';
			if ( ! empty( $title_attr ) ) {
				$media_link_atts['title'] = $title_attr;
			}

			// Set the rel attribute.
			$rel_attr = $attributes['mediaLinkRel'] ?? '';
			if ( ! empty( $rel_attr ) ) {
				$media_link_atts['rel'] = $rel_attr;
			}

			// Set the class attribute.
			$class_attr = $attributes['mediaLinkClass'] ?? '';
			if ( ! empty( $class_attr ) ) {
				$media_link_atts['class'] = $class_attr;
			}

			// Set the anchor ID attribute.
			$anchor_id_attr = $attributes['mediaLinkAnchorId'] ?? '';
			if ( ! empty( $anchor_id_attr ) ) {
				$media_link_atts['id'] = $anchor_id_attr;
			}
		}

		// Determine if there's a caption.
		$has_caption    = (bool) $attributes['hasCaption'] ?? false;
		$caption_markup = '';

		// Determine the caption position.
		$caption_position = $attributes['captionPosition'] ?? 'below';

		// Build caption markup if there's a caption.
		if ( ! empty( $innerblocks_content ) ) {

			$caption_markup = wp_kses( $innerblocks_content, Functions::get_kses_allowed_html( true, true ) );
		}

		// If overlay, include at same level of image.
		if ( ! empty( $innerblocks_content ) && ! empty( $caption_markup ) && 'overlay' === $caption_position ) {
			$image_markup = '<div class="dlx-photo-block__screen-edit-image-inner">' . $image_markup . $caption_markup . '</div>';
		}

		// Output the link HTML around the image.
		if ( '' !== $media_link_url ) {
			$image_markup = sprintf(
				'<a %4$s data-caption-"%4$s" href="%1$s" %2$s>%3$s</a>',
				esc_url( $media_link_url ),
				implode(
					' ',
					array_map(
						function ( $v, $k ) {
							return sprintf( '%s="%s"', sanitize_key( $k ), esc_attr( $v ) ); },
						$media_link_atts,
						array_keys( $media_link_atts )
					)
				),
				$image_markup,
				esc_attr( $caption ),
				( 'image' === $media_link_type && $lightbox_enabled ) ? 'data-fancybox' : ''
			);
		}

		// Add image wrapper.
		$image_markup = sprintf(
			'<div class="dlx-photo-block__image-wrapper">%2$s</div>',
			esc_attr( $unique_id ),
			$image_markup
		);

		// If caption and top or bottom position, add in caption markup.
		$has_overlay = false;
		if ( ! empty( $innerblocks_content ) && ! empty( $caption_markup ) && 'top' === $caption_position ) {
			$image_markup = $caption_markup . $image_markup;
		} elseif ( ! empty( $innerblocks_content ) && ! empty( $caption_markup ) && 'bottom' === $caption_position ) {
			$image_markup = $image_markup . $caption_markup;
		} elseif ( ! empty( $innerblocks_content ) && ! empty( $caption_markup ) && 'overlay' === $caption_position ) {
			$has_overlay = true;
		}

		// Get figure CSS advanced classes.
		$figure_css_classes = explode( ' ', $attributes['figureCSSClasses'] ?? '' );
		// Trim array for empty values.
		$figure_css_classes   = array_filter( $figure_css_classes );
		$figure_css_classes[] = 'dlx-photo-block__figure';
		if ( $has_global_style ) {
			$figure_css_classes[] = $global_style_css_class;
		}
		if ( $has_overlay ) {
			$figure_css_classes[] = 'dlx-caption-position-overlay';
		}

		/**
		 * Filter the figure CSS classes.
		 *
		 * @param array $figure_css_classes The figure CSS classes.
		 * @param array $attributes         The block attributes.
		 * @param array $context            The block context variables.
		 *
		 * @since 1.0.0
		 */
		$figure_css_classes = apply_filters( 'dlx_photo_block_figure_css_classes', $figure_css_classes, $attributes, $context );

		// Output figure markup.
		$image_markup = sprintf(
			'<figure class="%2$s">%1$s</figure>',
			$image_markup,
			esc_attr( implode( ' ', $figure_css_classes ) )
		);

		// Get section CSS classes.
		$section_css_classes = array(
			'dlx-photo-block__container',
			'align' . esc_attr( $attributes['align'] ),
			'dlx-photo-position-' . esc_attr( $attributes['photoPosition'] ),
			esc_attr( $unique_id ),
		);
		if ( $has_global_style ) {
			$section_css_classes[] = $global_style_css_class;
		}
		if ( (bool) $attributes['hideOnMobile'] ) {
			$section_css_classes[] = 'hide-on-mobile';
		}
		if ( (bool) $attributes['hideOnTablet'] ) {
			$section_css_classes[] = 'hide-on-tablet';
		}
		if ( (bool) $attributes['hideOnDesktop'] ) {
			$section_css_classes[] = 'hide-on-desktop';
		}

		/**
		 * Filter the section CSS classes.
		 *
		 * @param array $section_css_classes The section CSS classes.
		 * @param array $attributes          The block attributes.
		 * @param array $context             The block context variables.
		 *
		 * @since 1.0.0
		 */
		$section_css_classes = apply_filters( 'dlx_photo_block_section_css_classes', $section_css_classes, $attributes, $context );

		// Wrap figure in section tag, gather classes from block props.
		$image_markup = sprintf(
			'<section id="%1$s" class="dlx-photo-block__container align%3$s %4$s %5$s">%2$s</section>',
			$unique_id,
			$image_markup,
			esc_attr( $attributes['align'] ),
			esc_attr( implode( ' ', $section_css_classes ) ),
			esc_attr( $global_style_css_class )
		);

		/**
		 * Filter the final image markup.
		 *
		 * @param string $image_markup The image markup.
		 * @param array  $attributes   The block attributes.
		 * @param array  $context      The block context variables.
		 *
		 * @since 1.0.0
		 */
		$image_markup = apply_filters( 'dlx_photo_block_image_markup', $image_markup, $attributes, $context );

		// Begin frontend styles.
		$css_output = '';
		if ( ! $has_global_style ) {
			$css_output = Functions::generate_photo_block_css( $attributes, $unique_id );
		}

		// Determine if right+click protection is enabled.
		$right_click_protection_enabled = (bool) $attributes['imageProtectionEnabled'] ?? false;

		// If right+click protection is enabled, register dummy script and add inline image protection script.
		if ( $right_click_protection_enabled ) {
			wp_register_script(
				'dlx-photo-block-image-protection-js',
				null,
				array(),
				Functions::get_plugin_version(),
				true
			);
			wp_add_inline_script(
				'dlx-photo-block-image-protection-js',
				'document.getElementById("' . esc_js( $unique_id ) . '").addEventListener("contextmenu",function(e){e.preventDefault()},!1);'
			);
		}

		// Output styles.
		if ( ! $has_global_style ) {
			wp_register_style(
				'dlx-photo-block-' . $unique_id,
				'',
				array(),
				Functions::get_plugin_version(),
				'all'
			);
			wp_add_inline_style(
				'dlx-photo-block-' . $unique_id,
				esc_html( $css_output )
			);
			add_action(
				'wp_footer',
				function () use ( $unique_id ) {
					wp_print_styles( array( 'dlx-photo-block-' . $unique_id ) );
				},
				100
			);
		}

		// Output image markup.
		echo wp_kses( $image_markup, Functions::get_kses_allowed_html() );
		return ob_get_clean();
	}

	/**
	 * Add inline styles to Fancybox to override admin bar z-index.
	 */
	public static function add_fancybox_inline_styles() {
		// Register Fancybox for later if needed.
		wp_register_style(
			'dlx-photo-block-fancybox-css',
			Functions::get_plugin_url( 'assets/fancybox/fancybox.css' ),
			array(),
			Functions::get_plugin_version(),
			'all'
		);
		// Add CSS to override admin bar z-index.
		$fancybox_css_handles = array(
			'dlx-photo-block-fancybox-css', /* photo block */
			'jquery-fancybox', /* Easy Fancybox, jQuery Fancybox */
			'fancybox', /* Fancybox for WordPress */
		);
		foreach ( $fancybox_css_handles as $fancybox_css_handle ) {
			wp_add_inline_style(
				$fancybox_css_handle,
				'.fancybox__container{z-index:99999;}.fancybox-container{z-index:99999;}.fancybox__caption{ text-align: center;}'
			);
		}
	}

	/**
	 * Build the CSS for the dimensions components.
	 *
	 * @param array  $sizes {
	 *   An array of sizes.
	 *
	 *   @type string $top The top dimension.
	 *   @type string $right The right dimension.
	 *   @type string $bottom The bottom dimension.
	 *   @type string $left The left dimension.
	 *   @type string $unit The dimensions's unit.
	 * }
	 * @param string $screen_size Screen size (desktop|mobile|tablet).
	 * @param bool   $is_margin   Whether the dimension is a margin or not (so we do not set left/right margins).
	 *
	 * @return string The CSS for the dimensions.
	 */
	public static function build_dimensions_css( $sizes, $screen_size = 'desktop', $is_margin = false ) {
		$dimensions = $sizes[ $screen_size ];

		if ( 'desktop' === $screen_size ) {
			$css = self::get_dimensions_shorthand(
				$dimensions['top'],
				$dimensions['right'],
				$dimensions['bottom'],
				$dimensions['left'],
				$dimensions['unit'],
				$is_margin
			);
			return $css;
		}
		if ( 'tablet' === $screen_size || 'mobile' === $screen_size ) {
			$css = self::get_dimensions_shorthand(
				self::get_hierarchical_value( $sizes, $screen_size, $dimensions['top'], 'top' ),
				self::get_hierarchical_value( $sizes, $screen_size, $dimensions['right'], 'right' ),
				self::get_hierarchical_value( $sizes, $screen_size, $dimensions['bottom'], 'bottom' ),
				self::get_hierarchical_value( $sizes, $screen_size, $dimensions['left'], 'left' ),
				self::get_hierarchical_value_unit( $sizes, $screen_size, $dimensions['unit'], 'unit' ),
				$is_margin
			);
			return $css;
		}

		return '';
	}

	/**
	 * Return a hierarchical unit value.
	 *
	 * @param array  $sizes {
	 *   An array of sizes.
	 *
	 *   @type string $top The top dimension.
	 *   @type string $right The right dimension.
	 *   @type string $bottom The bottom dimension.
	 *   @type string $left The left dimension.
	 *   @type string $unit The dimensikon's unit.
	 * }
	 * @param string $screen_size Screen size (desktop|mobile|tablet).
	 * @param string $value       The unit value (px, em, rem).
	 *
	 * @return string The unit to use.
	 */
	public static function get_hierarchical_value_unit( $sizes, $screen_size, $value ) {
		// Check mobile screen size.
		if ( 'mobile' === $screen_size && null === $value ) {
			if ( null === $sizes['tablet']['unit'] ) {
				return $sizes['desktop']['unit'];
			}
			return $sizes['tablet']['unit'];
		}
		if ( 'tablet' === $screen_size && null === $value ) {
			return $sizes['desktop']['unit'];
		}
		if ( null === $value ) {
			return 'px';
		}
		return $value;
	}

	/**
	 * Get the hierarchical value for the dimension and screen size.
	 *
	 * @param array  $sizes {
	 *   An array of sizes.
	 *
	 *   @type string $top The top dimension.
	 *   @type string $right The right dimension.
	 *   @type string $bottom The bottom dimension.
	 *   @type string $left The left dimension.
	 *   @type string $unit The dimensikon's unit.
	 * }
	 * @param string $screen_size Screen size (desktop|mobile|tablet).
	 * @param string $value       The dimension value.
	 * @param string $type        The dimension type (top|right|bottom|left).
	 *
	 * @return string The value of the dimension type.
	 */
	public static function get_hierarchical_value( $sizes, $screen_size, $value, $type ) {
		// Check mobile screen size.
		if ( 'mobile' === $screen_size && '' === $value ) {
			// Check tablet.
			if ( '' !== $sizes['tablet'][ $type ] ) {
				return $sizes['tablet'][ $type ];
			} elseif ( '' !== $sizes['desktop'][ $type ] ) {
				// Check desktop.
				return $sizes['desktop'][ $type ];
			}
		}

		// Check tablet screen size.
		if ( 'tablet' === $screen_size && '' === $value ) {
			if ( '' !== $sizes['desktop'][ $type ] ) {
				// Check desktop.
				return $sizes['desktop'][ $type ];
			}
		}

		if ( '' !== $value ) {
			return $value;
		}

		return '0';
	}

	/**
	 * Get the hierarchical value for the dimension and screen size.
	 *
	 * @param array  $typography_settings {
	 *   An array of typography settings.
	 *
	 *   @type string $fontFamily        The Font Family.
	 *   @type string $fontFamilySlug    Font Family slug.
	 *   @type string $fontSize          The font size.
	 *   @type string $fontSizeUnit      The font size unit.
	 *   @type string $fontWeight        Font weight (100, 200, etc.).
	 *   @type string $lineHeight        The line height.
	 *   @type string $lineHeightUnit    The line height unit.
	 *   @type string $textTransform     The text transform (uppercase, lowercase, etc.).
	 *   @type string $letterSpacing     The letter spacing.
	 *   @type string $letterSpacingUnit The letter spacing unit.
	 *   @type string $fontType          The font type (google|adobe|web).
	 *   @type string $fontFallback      The font fallback.
	 * }
	 * @param string $screen_size Screen size (desktop|mobile|tablet).
	 * @param string $type        The dimension type (top|right|bottom|left).
	 *
	 * @return string The value of the typography type.
	 */
	public static function get_hierarchical_typography( $typography_settings, $screen_size, $type ) {
		// Check mobile screen size.
		if ( 'mobile' === $screen_size && '' === $typography_settings[ $screen_size ][ $type ] ) {
			// Check tablet.
			if ( '' !== $typography_settings['tablet'][ $type ] ) {
				return $typography_settings['tablet'][ $type ];
			} elseif ( '' !== $typography_settings['desktop'][ $type ] ) {
				// Check desktop.
				return $typography_settings['desktop'][ $type ];
			}
		}

		// Check tablet screen size.
		if ( 'tablet' === $screen_size && '' === $typography_settings[ $screen_size ][ $type ] ) {
			if ( '' !== $typography_settings['desktop'][ $type ] ) {
				// Check desktop.
				return $typography_settings['desktop'][ $type ];
			}
		}

		if ( '' !== $typography_settings[ $screen_size ][ $type ] ) {
			return $typography_settings[ $screen_size ][ $type ];
		}

		return '';
	}

	/**
	 * Return dimensions shorthand.
	 *
	 * @param string $top   The top dimension.
	 * @param string $right The right dimension.
	 * @param string $bottom The bottom dimension.
	 * @param string $left The left dimension.
	 * @param string $unit The dimensions's unit.
	 * @param bool   $is_margin Whether margin is set so left right values are not set.
	 *
	 * @return string The shorthand CSS for the dimensions.
	 */
	public static function get_dimensions_shorthand( $top, $right, $bottom, $left, $unit, $is_margin = false ) {
		if ( '' === $top && '' === $right && '' === $bottom && '' === $left ) {
			return;
		}

		$top    = ( 0 !== (float) $top && '' !== $top ) ? (float) $top . $unit . ' ' : '0 ';
		$right  = ( 0 !== (float) $right && '' !== $right ) ? (float) $right . $unit . ' ' : '0 ';
		$bottom = ( 0 !== (float) $bottom && '' !== $bottom ) ? (float) $bottom . $unit . ' ' : '0 ';
		$left   = ( 0 !== (float) $left && '' !== $left ) ? (float) $left . $unit . ' ' : '0 ';

		if ( $right === $left ) {
			$left = '';

			if ( $top === $bottom ) {

				if ( $top === $right ) {
					$right = '';
				}
			}
		}

		if ( $is_margin ) {
			$right = ' auto ';
			$left  = ' auto ';
		}

		$output = $top . $right . $bottom . $left;
		return trim( $output );
	}

	/**
	 * Output any scripts/styles needed in the footer.
	 */
	public static function enqueue_footer_assets() {
		// If we've already enqueued fancybox, we don't need to do it again.
		// Compatible with other fancybox jquery plugins.
		if ( wp_script_is( 'fancybox', 'enqueued' ) || wp_script_is( 'jquery-fancybox', 'enqueued' ) || wp_script_is( 'has-fancybox-js', 'registered' ) ) {
			if ( wp_script_is( 'dlx-photo-block-fancybox-js', 'registered' ) ) {

				wp_print_scripts( 'dlx-photo-block-fancybox-js-inline' );
			}
			return;
		}

		// Make sure our fancybox scripts are registered.
		if ( wp_script_is( 'dlx-photo-block-fancybox-js', 'registered' ) ) {
			// Output our version of Fancybox.
			wp_print_scripts( array( 'dlx-photo-block-fancybox-js' ) );

			if ( ! wp_script_is( 'dlx-photo-block-fancybox-js-inline', 'done' ) ) {

				wp_print_scripts( 'dlx-photo-block-fancybox-js-inline' );
			}
		}

		// Make sure our fancybox styles are registered.
		if ( wp_style_is( 'dlx-photo-block-fancybox-css', 'registered' ) ) {
			wp_print_styles( array( 'dlx-photo-block-fancybox-css' ) );
		}

		// Print right+click protection script, if registered.
		if ( wp_script_is( 'dlx-photo-block-image-protection-js', 'registered' ) ) {
			wp_print_scripts( array( 'dlx-photo-block-image-protection-js' ) );
		}
	}
}
