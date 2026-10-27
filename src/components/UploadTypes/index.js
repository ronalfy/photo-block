/**
 * Upload data row including Upload|Media Library|URL|Data.
 */
import './editor.scss';

import {
	ToggleControl,
	TextControl,
	Button,
	Slot,
} from '@wordpress/components';
import { createBlock } from '@wordpress/blocks';
import { applyFilters } from '@wordpress/hooks';
import { MediaUpload, MediaUploadCheck, store as blockEditorStore } from '@wordpress/block-editor';

import {
	Link,
	Image as LucideImage,
	Upload,
	Download,
	AlertCircle,
	Loader2,
	XCircle,
	ImagePlus,
	ArrowBigLeftDash,
} from 'lucide-react';

import { useState, useEffect } from '@wordpress/element';

import { __ } from '@wordpress/i18n';
import classnames from 'classnames';
import { useDispatch, useSelect } from '@wordpress/data';
import { blockStore } from '../../store';
import SendCommand from '../../utils/SendCommand';

import WPNotice from '../../components/Notice';

/**
 * UploadTypes component.
 *
 * @param {Object} props - Component props.
 * @return {Function} Component.
 */
const UploadTypes = ( props ) => {
	const { attributes, setAttributes, blockUniqueId } = props;

	const {
		setImageData,
		setPhotoMode,
		setScreen,
	} = useDispatch( blockStore( blockUniqueId ) );

	const {
		insertBlock,
	} = useDispatch( blockEditorStore );

	// Get current block data.
	const {
		imageData,
		photoMode,
	} = useSelect( ( select ) => {
		return {
			imageData: select( blockStore( blockUniqueId ) ).getImageData(),
			photoMode: select( blockStore( blockUniqueId ) ).getPhotoMode(),
		};
	} );

	const [ isUrlSelected, setIsUrlSelected ] = useState( false );
	const [ url, setUrl ] = useState( '' );
	const [ urlInput, setUrlInput ] = useState( null );
	const [ isUrlValidationError, setIsUrlValidationError ] = useState( false );
	const [ isUrlSaving, setIsUrlSaving ] = useState( false );
	const [ urlValidationErrorMessage, setUrlValidationErrorMessage ] = useState( '' );

	/**
	 * Focus on url when entered.
	 */
	useEffect( () => {
		if ( null !== urlInput ) {
			urlInput.focus();
		}
	}, [ urlInput ] );

	const getUrlIcon = () => {
		if ( isUrlSaving ) {
			return <Loader2 />;
		}
		if ( 'url' === photoMode ) {
			return <ImagePlus />;
		}
		return <Download />;
	};

	const getUrlLabel = () => {
		if ( isUrlSaving ) {
			return __( 'Uploading', 'photo-block' );
		}
		if ( 'url' === photoMode ) {
			return __( 'Add Image', 'photo-block' );
		}
		return __( 'Upload', 'photo-block' );
	};

	/**
	 * Check for a valid URL before submitting via Ajax.
	 *
	 * @param {string} testUrl The URL string to check.
	 * @return {boolean} True if the URL is valid, false if not.
	 */
	const isValidUrl = ( testUrl ) => {
		// Test the beginning part of the URL.
		const urlValidation = /^((http|https):\/\/)/;
		if ( ! urlValidation.test( testUrl ) ) {
			return false;
		}

		/**
		 * Filter the valid extensions for the photo block.
		 *
		 * @param {Array} [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif' ] The default valid extensions.
		 */
		const validExtensions = applyFilters(
			'dlx_photo_block_valid_extensions',
			[ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif' ]
		);

		// Test for valid extensions.
		const parseUrl = new URL( testUrl );
		const path = parseUrl.pathname.toLowerCase();

		return validExtensions.some( ( extension ) => path.endsWith( extension ) );
	};

	if ( isUrlSelected ) {
		return (
			<>
				<div className="dlx-photo-block__upload-types-checkbox__container">
					<ToggleControl
						__nextHasNoMarginBottom={ true }
						label={ __( 'Save image URL manually.', 'photo-block' ) }
						checked={ 'url' === photoMode }
						onChange={ ( value ) => {
							if ( value ) {
								setPhotoMode( 'url' );
							} else {
								setPhotoMode( 'image' );
							}
						} }
					/>
				</div>
				<div className="dlx-photo-block__upload-types-url__container">
					<TextControl
						__next40pxDefaultSize={ true }
						__nextHasNoMarginBottom={ true }
						type="url"
						className={
							classnames( 'dlx-photo-block__upload-types-url__input', {
								'is-url-saving': isUrlSaving,
								'is-validation-error': isUrlValidationError,
							} )
						}
						label={ __( 'Photo URL', 'photo-block' ) }
						value={ url }
						onChange={ ( value ) => {
							setUrlValidationErrorMessage( '' );
							setIsUrlValidationError( false );
							setUrl( value );
						} }
						ref={ setUrlInput }
						placeholder={ __( 'Please enter a valid image URL', 'photo-block' ) }
					/>
					<Button
						variant="primary"
						icon={ getUrlIcon() }
						disabled={ isUrlSaving || isUrlValidationError }
						className={ classnames( 'dlx-photo-block__upload-types-url__upload', {
							'is-url-saving': isUrlSaving,
							'is-validation-error': isUrlValidationError,
						} )
						}
						onClick={ () => {
							// Perform validation on the URL.
							if ( ! isValidUrl( url ) ) {
								setUrlValidationErrorMessage( __( 'Please enter a valid image URL', 'photo-block' ) );
								setIsUrlValidationError( true );
								urlInput.focus();
								return;
							}
							setUrlValidationErrorMessage( '' );
							setIsUrlSaving( true );
							setIsUrlValidationError( false );

							if ( 'url' !== photoMode ) {
								SendCommand(
									photoBlock.restNonce,
									{ url },
									photoBlock.restUrl + '/add-image-from-url',
									'POST'
								).then( ( response ) => {
									// Successful response.
									const maybeUrl = response.data?.url ?? false; // Double-checking.
									if ( maybeUrl ) {
										setAttributes( { imageData: response.data, photoMode: 'photo' } );
										setImageData( response.data );
										setScreen( 'edit' );
										setPhotoMode( 'photo' );
									}
								} ).catch( ( error ) => {
									const errorMessage = error?.response?.data?.message ?? __( 'An unknown error occurred', 'photo-block' );
									setUrlValidationErrorMessage( errorMessage );
									setIsUrlValidationError( true );
									urlInput.focus();
								} ).then( () => {
									setIsUrlSaving( false );
								} );
							} else {
								// Get width and height of the image.
								const newImage = new Image();
								newImage.src = url;
								newImage.onload = () => {
									const urlImageData = {
										id: 0,
										url,
										width: newImage.width,
										height: newImage.height,
										alt: '',
										caption: '',
									};

									setAttributes( { imageData: urlImageData, screen: 'edit', photoMode: 'url' } );
									setImageData( urlImageData );
									setScreen( 'edit' );
								};
							}
						} }
						label={ getUrlLabel() }
					/>
					<Button
						variant="secondary"
						icon={ <XCircle /> }
						className="dlx-photo-block__upload-types-url__cancel"
						onClick={ () => {
							setIsUrlSelected( false );
							setUrlValidationErrorMessage( '' );
							setIsUrlValidationError( false );
							setIsUrlSaving( false );
						} }
						label={ __( 'Cancel', 'photo-block' ) }
					/>
				</div>
				{
					isUrlValidationError && (
						<div className="dlx-photo-block__upload-types-url__error">
							<WPNotice
								message={ urlValidationErrorMessage }
								status="error"
								politeness="assertive"
								icon={ AlertCircle }
								inline={ false }
							/>
						</div>
					)
				}
			</>
		);
	}

	const handleSelect = ( media ) => {
		// Fetch image data.
		SendCommand(
			photoBlock.restNonce,
			{},
			`${ photoBlock.restUrl + '/get-image-by-size' }/id=${ media.id }/size=${ attributes.imageSize }`,
			'GET'
		)
			.then( ( response ) => {
				const { success, data } = response.data;
				if ( ! success ) {
					return;
				}
				setPhotoMode( 'photo' );
				setScreen( 'edit' );
				attributes.screen = 'edit';
				setAttributes( {
					imageData: data,
					screen: 'edit',
					photoMode: 'photo',
					hasCaption: true,
				} );
				setImageData( data );

				if ( data.caption !== '' ) {
					const newBlock = createBlock( 'dlxplugins/photo-caption-block', {
						captionManual: data.caption,
						uniqueId: blockUniqueId,
					} );

					// Ensure block editor is ready
					setTimeout( async() => {
						try {
							insertBlock( newBlock, undefined, props.clientId, false );
						} catch ( error ) {
							console.error( 'Error inserting block:', error );
						}
					}, 0 );
				}
			} )
			.catch( ( error ) => {
				// TODO: Handle error appropriately.
				console.error( 'Error fetching image data:', error );
			} );
	};
	return (
		<>
			<div className="dlx-photo-block__upload-types__container">
				{
					( imageData.url !== '' ) && (
						<Button
							variant="primary"
							icon={ <ArrowBigLeftDash /> }
							className="dlx-photo-block__upload-types__back"
							onClick={ () => {
								setAttributes( { screen: 'edit' } );
								setScreen( 'edit' );
							} }
						>
							{ __( 'Back', 'photo-block' ) }
						</Button>
					)
				}
				<Button
					variant="secondary"
					icon={ <Upload /> }
					onClick={ () => {
						const filepondInstance = window.dlxPhotoBlockFilePonds[ blockUniqueId ];
						if ( filepondInstance ) {
							filepondInstance.browse();
						}
					} }
				>
					{ __( 'Upload', 'photo-block' ) }
				</Button>
				<MediaUploadCheck>
					<MediaUpload
						allowedTypes="image"
						mode={ ( imageData?.id || 0 ) === 0 ? 'upload' : 'browse' }
						multiple={ false }
						value={ imageData?.id || 0 }
						title={ __( 'Please select a Photo', 'photo-block' ) }
						render={ ( { open } ) => (
							<Button
								variant="secondary"
								icon={ <LucideImage /> }
								onClick={ () => {
									open();
								} }
							>
								{ __( 'Media Library', 'photo-block' ) }
							</Button>
						) }
						onSelect={ handleSelect }
					/>
				</MediaUploadCheck>

				<Button
					variant="secondary"
					icon={ <Link /> }
					onClick={ () => {
						setIsUrlSelected( true );
					} }
				>
					{ __( 'URL', 'photo-block' ) }
				</Button>
				<Slot
					name="dlx-photo-block.upload-types"
					fillProps={ { ...props } }
				/>
			</div>
		</>
	);
};
export default UploadTypes;
