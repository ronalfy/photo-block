/**
 * This is the initial screen of the block. It is the first screen that the user sees when they add the block to the editor.
 */
import { useEffect } from '@wordpress/element';
import {
	InspectorControls,
} from '@wordpress/block-editor';
import classnames from 'classnames';
import { useSelect } from '@wordpress/data';
import UploadTypes from '../../components/UploadTypes';
import UploadTarget from '../../components/UploadTarget';
import UploadStatus from '../../components/UploadStatus';
import { blockStore } from '../../store';

/**
 * InitialScreen component.
 *
 * @param {Object} props - Component props.
 * @return {Function} Component.
 */
const InitialScreen = ( props ) => {
	const { blockUniqueId, clientId, setAttributes } = props;
	const {
		isUploading,
		isProcessingUpload,
		isUploadError,
	} = useSelect( ( select ) => {
		return {
			isUploading: select( blockStore( blockUniqueId ) ).isUploading(),
			isProcessingUpload: select( blockStore( blockUniqueId ) ).isProcessingUpload(),
			isUploadError: select( blockStore( blockUniqueId ) ).isUploadError(),
		};
	} );

	useEffect( () => {
		if ( props.attributes.loadFile && photoBlock.settings.insertOnPaste ) {
			const filepondInstance = window.dlxPhotoBlockFilePonds[ blockUniqueId ];
			if ( filepondInstance ) {
				filepondInstance.addFile( props.attributes.file );
				filepondInstance.processFile();
				setAttributes( { loadFile: false, file: null } );
			}
		}
	}, [] );

	// Set the local inspector controls.
	const localInspectorControls = (
		<InspectorControls />
	);

	const initialDefaultInterface = (
		<>
			{ localInspectorControls }
			<div className="dlx-photo-block__screen-initial">
				{ ( ! isUploading && ! isProcessingUpload && ! isUploadError ) && (
					<UploadTypes attributes={ props.attributes } setAttributes={ props.setAttributes } blockUniqueId={ blockUniqueId } clientId={ clientId } />
				) }
				{ ( isUploading || isProcessingUpload || isUploadError ) && (
					<UploadStatus blockUniqueId={ blockUniqueId } />
				) }
				<div
					className={
						classnames(
							{
								'dlx-photo-block__is-uploading': isUploading || isProcessingUpload || isUploadError,
							}
						)
					}
				>
					<UploadTarget attributes={ props.attributes } setAttributes={ props.setAttributes } blockUniqueId={ blockUniqueId } clientId={ clientId } />
				</div>
			</div>
		</>
	);

	return initialDefaultInterface;
};
export default InitialScreen;
