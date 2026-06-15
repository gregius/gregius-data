/**
 * Vectors Page Component
 *
 * Multi-model embedding management with card-per-model UI pattern.
 * Each embedding model gets its own card with independent actions.
 *
 * Architecture:
 * - Models are global (stored in MySQL wp_gg_settings)
 * - Connection-model associations per database (PostgreSQL connection_embedding_models)
 * - Each card shows model-specific vector status, cost, and actions
 * - "+ Add Model" button to add global models to this connection
 *
 * Card Types:
 * - TFIDFVectorCard: Free, vocabulary-based embeddings (auto-added)
 * - APIEmbeddingCard: OpenAI, Voyage AI, etc. (user-added)
 *
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { __experimentalHeading as Heading, Button, Spinner, Card, CardBody } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import DatabaseSelector from '../components/DatabaseSelector';
import VocabularyIntegrityCard from '../components/vectors/VocabularyIntegrityCard';
import AddModelModal from '../components/vectors/AddModelModal';
import TFIDFVectorCard from '../components/vectors/TFIDFVectorCard';
import APIEmbeddingCard from '../components/vectors/APIEmbeddingCard';

const VectorsPage = ( { settings, isLoading, error, apiStatus } ) => {
	const [ connectionModels, setConnectionModels ] = useState( [] );
	const [ showAddModal, setShowAddModal ] = useState( false );
	const [ isLoadingModels, setIsLoadingModels ] = useState( false );
	const [ vocabularyStatus, setVocabularyStatus ] = useState( null );

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
	 * Fetch connection models and auto-add TF-IDF when connection changes
	 */
	useEffect( () => {
		if ( selectedConnectionId ) {
			fetchConnectionModels();
			fetchVocabularyStatus();
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
	 * Fetch vocabulary status (for TF-IDF)
	 */
	const fetchVocabularyStatus = async () => {
		try {
			const response = await apiFetch( {
				path: `/gg-data/v1/vocabulary/status?connection_name=${ selectedConnectionId }`,
			} );

			if ( response.success ) {
				setVocabularyStatus( response.status );
			} else {
				setVocabularyStatus( null );
			}
		} catch ( err ) {
			setVocabularyStatus( null );
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

	/**
	 * Handle vocabulary preparation complete
	 */
	const handleVocabularyPrepared = () => {
		fetchVocabularyStatus();
	};

	// Check if TF-IDF is in connection models.
	const hasTFIDF = connectionModels.some( ( m ) => m.model_key === 'tfidf-300' );

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
					{ /* VocabularyIntegrityCard removed - vocabulary now integrated into TFIDFVectorCard */ }

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
							{ connectionModels.map( ( model ) =>
								( model.provider === 'internal' && model.provider_model_id === 'tfidf-300' ) ? (
									<TFIDFVectorCard
										key={ model.model_key }
										model={ model }
										connection={ selectedConnectionId }
										vocabularyStatus={ vocabularyStatus }
										onVocabularyPrepared={ handleVocabularyPrepared }
										onRemove={ handleRemoveModel }
										onRefresh={ fetchConnectionModels }
									/>
								) : (
									<APIEmbeddingCard
										key={ model.model_key }
										model={ model }
										connection={ selectedConnectionId }
										onRemove={ handleRemoveModel }
										onRefresh={ fetchConnectionModels }
									/>
								)
							) }
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
