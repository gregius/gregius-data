/**
 * Vectors Page Component
 *
 * Multi-model embedding management with card-per-model UI pattern.
 * Each embedding model gets its own card with independent actions.
 *
 * Architecture:
 * - Models are global (stored in MySQL wp_gg_settings)
 * - Connection-model associations per database (PostgreSQL connection_embedding_models)
 * - Each card shows model-specific vector status and actions
 * - "+ Add Model" button to add global models to this connection
 *
 * Card Types:
 * - APIEmbeddingCard: internal (HashingTF) and API-provided embeddings (OpenAI, Voyage AI, etc.)
 *
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { __experimentalHeading as Heading, Button, Spinner, Card, CardBody } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import DatabaseSelector from '../components/DatabaseSelector';
import AddModelModal from '../components/vectors/AddModelModal';
import APIEmbeddingCard from '../components/vectors/APIEmbeddingCard';

const VectorsPage = ( { settings, isLoading, error, apiStatus } ) => {
	const [ connectionModels, setConnectionModels ] = useState( [] );
	const [ showAddModal, setShowAddModal ] = useState( false );
	const [ isLoadingModels, setIsLoadingModels ] = useState( false );

	// Use WordPress data stores.
	const { connections, isLoadingConnections } = useSelect( ( select ) => ( {
		connections: select( 'gg-data/connections' ).getConnectionsList(),
		isLoadingConnections: select( 'gg-data/connections' ).isLoading(),
	} ), [] );

	const selectedConnectionId = useSelect(
		( select ) => select( 'gg-data/selected' ).getConnectionId(),
		[]
	);

	const { setConnection } = useDispatch( 'gg-data/selected' );

	/**
	 * Fetch connection models when connection changes
	 */
	useEffect( () => {
		if ( selectedConnectionId ) {
			fetchConnectionModels();
		}
	}, [ selectedConnectionId ] );

	/**
	 * Fetch active models for this connection
	 */
	const fetchConnectionModels = async () => {
		setIsLoadingModels( true );
		try {
			const response = await apiFetch( {
				path: `/gg-data/v1/connections/${ selectedConnectionId }/vectors/models`,
			} );

			if ( response.success && response.data ) {
				setConnectionModels( response.data );
			}
		} catch ( err ) {
			console.error( 'Failed to fetch connection models:', err );
		} finally {
			setIsLoadingModels( false );
		}
	};

	/**
	 * Handle adding model to connection
	 */
	const handleAddModel = async ( modelKey ) => {
		try {
			await apiFetch( {
				path: `/gg-data/v1/connections/${ selectedConnectionId }/vectors/models`,
				method: 'POST',
				data: { model_key: modelKey },
			} );

			// Refresh connection models.
			fetchConnectionModels();
			setShowAddModal( false );
		} catch ( err ) {
			console.error( 'Failed to add model:', err );
			alert( err.message || __( 'Failed to add model', 'gregius-data' ) );
		}
	};

	/**
	 * Handle removing model from connection
	 */
	const handleRemoveModel = async ( modelKey, vectorCount ) => {
		if ( vectorCount > 0 ) {
			alert(
				/* translators: %d: Number of vectors */
				__( 'Cannot remove model with %d existing vectors. Delete vectors first.', 'gregius-data' ).replace( '%d', vectorCount )
			);
			return;
		}

		if (
			! confirm(
				/* translators: %s: Model key */
				__( 'Remove %s from this connection?', 'gregius-data' ).replace( '%s', modelKey )
			)
		) {
			return;
		}

		try {
			await apiFetch( {
				path: `/gg-data/v1/connections/${ selectedConnectionId }/vectors/models/${ modelKey }`,
				method: 'DELETE',
			} );

			// Refresh connection models.
			fetchConnectionModels();
		} catch ( err ) {
			console.error( 'Failed to remove model:', err );
			alert( err.message || __( 'Failed to remove model', 'gregius-data' ) );
		}
	};

	return (
		<div className="gg-data-page">
			<div
				style={ {
					display: 'flex',
					flexWrap: 'wrap',
					alignItems: 'center',
					justifyContent: 'space-between',
					gap: 16,
					padding: '2rem 1.5rem 0',
					borderTop: '1px solid rgba(0, 0, 0, 0.1)',
				} }
			>
				<div style={ { display: 'flex', flexDirection: 'column' } }>
					<Heading level={ 2 }>
						{ __( 'Vector Generation', 'gregius-data' ) }
					</Heading>
					<p className="description">
						{ __(
							'Manage embedding models and generate vectors for semantic search.',
							'gregius-data'
						) }
					</p>
				</div>
				{ ! isLoadingConnections && connections.length > 0 && (
					<div
						style={ {
							minWidth: 220,
							display: 'flex',
							flexDirection: 'row',
							alignItems: 'end',
							gap: '1rem',
						} }
					>
						<DatabaseSelector
							connections={ connections }
							selectedConnectionId={ selectedConnectionId }
							onSelect={ setConnection }
						/>
						{ selectedConnectionId && (
							<Button
								variant="primary"
								onClick={ () => setShowAddModal( true ) }
								style={ { justifyContent: 'center' } }
							>
								{ __( 'Add Embedding Model', 'gregius-data' ) }
							</Button>
						) }
					</div>
				) }
			</div>

			{ /* Only render content after connections are loaded */ }
			{ ! isLoadingConnections && selectedConnectionId && (
				<div style={ { padding: '1.5rem' } }>
					{ /* Model Cards */ }
					{ isLoadingModels ? (
						<div style={ { textAlign: 'center', padding: '40px' } }>
							<Spinner />
							<p>{ __( 'Loading models...', 'gregius-data' ) }</p>
						</div>
					) : connectionModels.length === 0 ? (
						<Card isRounded={ false }>
							<CardBody style={ { textAlign: 'center', padding: '60px 40px' } }>
								<p style={ { color: '#646970', marginBottom: '24px' } }>
									{ __(
										'Add your first embedding model to this connection to get started.',
										'gregius-data'
									) }
								</p>
								<Button
									variant="secondary"
									onClick={ () => setShowAddModal( true ) }
								>
									{ __( 'Add Your First Embedding Model', 'gregius-data' ) }
								</Button>
							</CardBody>
						</Card>
					) : (
						<div
							className="gg-data-model-cards"
							style={ {
								display: 'grid',
								gridTemplateColumns:
									'repeat(auto-fill, minmax(400px, 1fr))',
								gap: '20px',
							} }
						>
							{ connectionModels.map( ( model ) => (
								<APIEmbeddingCard
									key={ model.model_key }
									model={ model }
									connection={ selectedConnectionId }
									onRemove={ handleRemoveModel }
									onRefresh={ fetchConnectionModels }
								/>
							) ) }
						</div>
					) }

					{ /* Add Model Modal */ }
					{ showAddModal && (
						<AddModelModal
							connection={ selectedConnectionId }
							existingModels={ connectionModels }
							onAdd={ handleAddModel }
							onClose={ () => setShowAddModal( false ) }
						/>
					) }
				</div>
			) }
		</div>
	);
};

export default VectorsPage;
