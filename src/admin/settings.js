import {
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useForm, Controller, useWatch, useFormState } from 'react-hook-form';

// Local imports.
import SaveResetButtons from './components/SaveResetButtons';

const Settings = () => {
	const {
		control,
		reset,
		setError,
		trigger,
		handleSubmit,
	} = useForm( {
		defaultValues: {
			saveNonce: dlxPBAdmin.saveNonce,
			resetNonce: dlxPBAdmin.resetNonce,
			hideCaptionAppender: dlxPBAdmin.hideCaptionAppender,
			insertOnPaste: dlxPBAdmin.insertOnPaste,
			debugModeEnabled: dlxPBAdmin.debugModeEnabled,
		},
	} );
	const formValues = useWatch( { control } );
	const { errors, isDirty, dirtyFields } = useFormState( {
		control,
	} );

	/**
	 * Save the option to the database.
	 */
	const onSubmit = () => {
		// do nothing.
	};

	return (
		<>
			<div className="dlx-pb-admin-content-heading">
				<h1><span className="dlx-pb-content-heading-text">{ __( 'Settings for Photo Block', 'photo-block' ) }</span></h1>
				<p className="description">
					{
						__( 'Configure the settings below for Photo Block.', 'photo-block' )
					}
				</p>
			</div>
			{ /* eslint-disable-next-line no-unused-vars */ }
			<form onSubmit={ handleSubmit( onSubmit ) }>
				<div id="dlx-pb-admin-table">
					<table className="form-table form-table-row-sections">
						<tbody>
							<tr>
								<th scope="row">
									{ __( 'Block Settings', 'photo-block' ) }
								</th>
								<td>
									<div className="dlx-admin__row">
										<Controller
											control={ control }
											name="hideCaptionAppender"
											render={ ( { field } ) => (
												<ToggleControl
													__nextHasNoMarginBottom={ true }
													{ ...field }
													checked={ field.value }
													onChange={ ( value ) => {
														field.onChange( value );
													} }
													label={ __( 'Hide Caption Appender by Default', 'photo-block' ) }
													help={ __( 'If hidden, you can still show the caption by enabling it in the block toolbar.', 'photo-block' ) }
												/>
											) }
										/>
									</div>
									<div className="dlx-admin__row">
										<Controller
											control={ control }
											name="insertOnPaste"
											render={ ( { field } ) => (
												<ToggleControl
													__nextHasNoMarginBottom={ true }
													{ ...field }
													checked={ field.value }
													onChange={ ( value ) => {
														field.onChange( value );
													} }
													label={ __( 'Insert on Paste/Drop', 'photo-block' ) }
													help={ __( 'If enabled, any pasted/dropped images will be inserted as a Photo Block.', 'photo-block' ) }
												/>
											) }
										/>
									</div>
								</td>
							</tr>
							<tr>
								<th scope="row">
									{ __( 'Advanced Settings', 'photo-block' ) }
								</th>
								<td>
							<div className="dlx-admin__row">
									<Controller
										name="debugModeEnabled"
										control={ control }
										render={ ( { field } ) => (
											<ToggleControl
												__nextHasNoMarginBottom={ true }
												{ ...field }
												checked={ field.value }
												onChange={ ( value ) => {
													field.onChange( value );
												} }
												label={ __( 'Debug Mode', 'photo-block' ) }
												help={ __( 'If enabled, Global Styles are shown in the WordPress admin.', 'photo-block' ) }
											/>
										) }
									/>
								</div>
								</td>
							</tr>
						</tbody>
					</table>
					<SaveResetButtons
						formValues={ formValues }
						setError={ setError }
						reset={ reset }
						errors={ errors }
						isDirty={ isDirty }
						dirtyFields={ dirtyFields }
						trigger={ trigger }
					/>
				</div>
			</form>
		</>
	);
};

export default Settings;
