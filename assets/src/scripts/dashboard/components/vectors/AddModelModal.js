/**
 * Add Model Modal Component
 *
 * Modal for adding global embedding models to a specific connection.
 * Shows all available models with checkmarks for already-added ones.
 *
 * @package    Gregius_Data
 * @subpackage Gregius_Data/assets/src/scripts/dashboard/components/vectors
 * @since      1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, SelectControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * AddModelModal Component
 *
 * @param {Object}   props                Props object.
 * @param {string}   props.connection     Connection name.
 * @param {Array}    props.existingModels Array of models already added to connection.
 * @param {Function} props.onAdd          Callback when model is added (receives modelKey).
 * @param {Function} props.onClose        Callback when modal is closed.
 * @return {JSX.Element} Modal component.
 */
const AddModelModal = ( { connection, existingModels, onAdd, onClose } ) => {
	const [ availableModels, setAvailableModels ] = useState( [] );
	const [ selectedModel, setSelectedModel ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		fetchAvailableModels();
	}, [] );

	/**
	 * Fetch all global embedding models from registry
	 */
	const fetchAvailableModels = async () => {
		setIsLoading( true );
		setError( null );

		try {
			// Fetch all global embedding models.
			const response = await apiFetch( {
				path: '/gg-data/v1/models?type=embeddings&status=active',
			} );

			if ( response.success && response.data ) {
				setAvailableModels( response.data );
			} else {
				setError( __( 'Failed to fetch models', 'gregius-data' ) );
			}
		} catch ( err ) {
			console.error( 'Failed to fetch models:', err );
			setError(
				err.message ||
					__( 'Failed to load models. Please try again.', 'gregius-data' )
			);
		} finally {
			setIsLoading( false );
		}
	};

	/**
	 * Get select options with checkmarks for already-added models
	 */
	const getModelOptions = () => {
		const existingKeys = existingModels.map( ( m ) => m.model_key );

		return [
			{ label: __( 'Select a model...', 'gregius-data' ), value: '' },
			...availableModels.map( ( model ) => ( {
				label: `${ existingKeys.includes( model.model_key ) ? '✓ ' : '' }${
					model.provider
				} - ${ model.provider_model_id } (${ model.dimensions }D)`,
				value: model.model_key,
				disabled: existingKeys.includes( model.model_key ),
			} ) ),
		];
	};

	/**
	 * Handle add button click
	 */
	const handleAdd = () => {
		if ( selectedModel ) {
			onAdd( selectedModel );
		}
	};

	return (
		<Modal
			title={ __( 'Add Embedding Model', 'gregius-data' ) }
			onRequestClose={ onClose }
			className="gg-data-add-model-modal"
		>
			{ isLoading ? (
				<div className="gg-data-modal-loading">
					<Spinner />
					<p>{ __( 'Loading available models...', 'gregius-data' ) }</p>
				</div>
			) : error ? (
				<div className="gg-data-modal-error">
					<p style={ { color: '#d63638' } }>{ error }</p>
					<Button onClick={ fetchAvailableModels }>
						{ __( 'Retry', 'gregius-data' ) }
					</Button>
				</div>
			) : (
				<>
					<SelectControl
						label={ __( 'Select Model', 'gregius-data' ) }
						value={ selectedModel }
						onChange={ setSelectedModel }
						options={ getModelOptions() }
					/>

					{ availableModels.length === 0 && (
						<p style={ { marginTop: '16px', color: '#757575' } }>
							{ __(
								'No embedding models found. Add embedding models in the Models tab.',
								'gregius-data'
							) }
						</p>
					) }

					<div
						style={ {
							display: 'flex',
							justifyContent: 'flex-start',
							marginTop: '20px',
							gap: '8px',
						} }
					>
						<Button
							isPrimary
							onClick={ handleAdd }
							disabled={ ! selectedModel }
						>
							{ __( 'Add Model', 'gregius-data' ) }
						</Button>

						<Button onClick={ onClose }>
							{ __( 'Cancel', 'gregius-data' ) }
						</Button>
					</div>
				</>
			) }
		</Modal>
	);
};

export default AddModelModal;
