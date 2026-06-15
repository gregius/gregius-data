/**
 * TF-IDF Vector Card Component
 *
 * Consolidated card component for TF-IDF embedding model showing:
 * - Vocabulary status (version, drift, cached vs current posts)
 * - Vector generation status and coverage
 * - Actions: Prepare/Regenerate Vocabulary, Generate/Regenerate Vectors, Remove Model
 *
 * @package    Gregius_Data
 * @subpackage Gregius_Data/assets/src/scripts/dashboard/components/vectors
 * @since      1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { Card, CardHeader, CardBody, Button, Spinner, DropdownMenu, Modal, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { moreVertical } from '@wordpress/icons';
import BatchDeleteModal from './BatchDeleteModal';

/**
 * TFIDFVectorCard Component
 *
 * @param {Object}   props                      Props object.
 * @param {Object}   props.model                Model configuration.
 * @param {string}   props.connection           Connection name.
 * @param {Object}   props.vocabularyStatus     Vocabulary status from parent.
 * @param {Function} props.onVocabularyPrepared Callback when vocabulary is prepared/regenerated.
 * @param {Function} props.onRemove             Callback when remove is clicked (receives modelKey, vectorCount).
 * @param {Function} props.onRefresh            Callback to refresh parent data.
 * @return {JSX.Element} Card component.
 */
const TFIDFVectorCard = ({ model, connection, vocabularyStatus, onVocabularyPrepared, onRemove, onRefresh }) => {
	// Unified operation state for all actions (prepare-vocab, generate, regenerate)
	const [operationState, setOperationState] = useState({
		status: 'idle', // 'idle' | 'processing' | 'completed' | 'stopped' | 'error'
		type: null, // 'prepare-vocab' | 'generate' | 'regenerate'
		data: null,
		result: null,
	});

	// Modal state
	const [modalState, setModalState] = useState(null); // { type: 'prepare-vocab' | 'generate' | 'regenerate', data: ... }

	// Delete modal state
	const [showDeleteModal, setShowDeleteModal] = useState(false);

	// Abort controller for stopping batch operations
	const [abortController, setAbortController] = useState(null);

	// Vector status (fetched independently)
	const [vectorStatus, setVectorStatus] = useState(null);
	const [error, setError] = useState(null);

	/**
	 * Fetch vector status for this model
	 */
	const fetchVectorStatus = async () => {
		// Guard: Don't fetch if model data is incomplete
		if (!model?.model_key || !connection) {
			return;
		}

		try {
			const response = await apiFetch({
				path: `/gg-data/v1/vectors/status?connection_name=${connection}&model_key=${model.model_key}`,
			});

			if (response.success && response.status) {
				setVectorStatus(response.status);
			}
		} catch (err) {
			console.error('Failed to fetch vector status:', err);
			// Silently fail - the card will still show without vector status
		}
	};

	// Fetch vector status on mount and when operation completes
	useEffect(() => {
		fetchVectorStatus();
	}, [model?.model_key, connection]);

	/**
	 * Handle Prepare Vocabulary action
	 */
	const handlePrepareVocabularyClick = () => {
		setModalState({
			type: 'prepare-vocab',
			data: vocabularyStatus,
		});
		setOperationState({
			status: 'idle',
			type: 'prepare-vocab',
			data: vocabularyStatus,
			result: null,
		});
	};

	/**
	 * Handle Generate Vectors action
	 */
	const handleGenerateClick = async () => {
		// Validate vocabulary exists
		if (!vocabularyStatus || !vocabularyStatus.exists) {
			return;
		}

		// Refresh status before opening modal to show accurate counts
		await fetchVectorStatus();

		setModalState({
			type: 'generate',
			data: vectorStatus,
		});
		setOperationState({
			status: 'idle',
			type: 'generate',
			data: vectorStatus,
			result: null,
		});
	};

	/**
	 * Handle Regenerate All Vectors action
	 */
	const handleRegenerateClick = async () => {
		// Validate vocabulary exists
		if (!vocabularyStatus || !vocabularyStatus.exists) {
			return;
		}

		// Refresh status before opening modal to show accurate counts
		await fetchVectorStatus();

		setModalState({
			type: 'regenerate',
			data: vectorStatus,
		});
		setOperationState({
			status: 'idle',
			type: 'regenerate',
			data: vectorStatus,
			result: null,
		});
	};

	/**
	 * Start vocabulary preparation
	 */
	const startVocabularyPreparation = async () => {
		setOperationState({
			status: 'processing',
			type: 'prepare-vocab',
			data: vocabularyStatus,
			result: null,
		});

		try {
			const response = await apiFetch({
				path: `/gg-data/v1/vocabulary/prepare?connection_name=${connection}`,
				method: 'POST',
			});

			if (response.success) {
				setOperationState({
					status: 'completed',
					type: 'prepare-vocab',
					data: vocabularyStatus,
					result: {
						version: response.vocabulary.version,
						unique_terms: response.vocabulary.unique_terms,
						post_count: response.vocabulary.post_count,
					},
				});

				// Notify parent to refresh vocabulary status
				if (onVocabularyPrepared) {
					await onVocabularyPrepared();
				}
			} else {
				setOperationState({
					status: 'error',
					type: 'prepare-vocab',
					data: vocabularyStatus,
					result: {
						error: response.message || __('Failed to prepare vocabulary', 'gregius-data'),
					},
				});
			}
		} catch (err) {
			setOperationState({
				status: 'error',
				type: 'prepare-vocab',
				data: vocabularyStatus,
				result: {
					error: err.message || __('Error preparing vocabulary', 'gregius-data'),
				},
			});
		}
	};

	/**
	 * Start batch vector generation/regeneration loop
	 * Processes 10 posts per request until all complete
	 */
	const startBatchProcessing = async (regenerateSince = null) => {
		const startTime = Date.now();
		const totalPending = regenerateSince
			? (vectorStatus?.total_posts || 0)
			: (vectorStatus?.posts_pending_vectors || 0);
		
		// Check if there's actually work to do
		if (!regenerateSince && totalPending === 0) {
			setOperationState({
				status: 'completed',
				type: 'generate',
				data: vectorStatus,
				result: {
					processed: 0,
					total: 0,
					failed: 0,
					duration: 0,
				},
			});
			return;
		}
		
		const controller = new AbortController();
		setAbortController(controller);

		setOperationState({
			status: 'processing',
			type: regenerateSince ? 'regenerate' : 'generate',
			data: vectorStatus,
			result: {
				processed: 0,
				total: totalPending,
				duration: 0,
			},
		});

		try {
			let hasMore = true;
			let totalProcessed = 0;
			let totalFailed = 0;

			// Batch processing loop
			while (hasMore && !controller.signal.aborted) {
				const response = await apiFetch({
					path: '/gg-data/v1/vectors/batch-generate',
					method: 'POST',
					data: {
						connection_name: connection,
						batch_size: 10,
						regenerate_since: regenerateSince,
						model_key: model.model_key,
					},
					signal: controller.signal,
				});

				if (!response.success || !response.batch) {
					throw new Error(response.message || __('Batch processing failed', 'gregius-data'));
				}

				// Update totals
				totalProcessed += response.batch.processed || 0;
				totalFailed += response.batch.failed || 0;

				// Update progress
				const duration = (Date.now() - startTime) / 1000;
				setOperationState((prev) => ({
					...prev,
					result: {
						processed: totalProcessed,
						total: totalPending,
						failed: totalFailed,
						duration,
					},
				}));

				// Check if more batches remain
				hasMore = response.batch.has_more;

				// Short delay between batches (100ms) for UI updates
				if (hasMore) {
					await new Promise((resolve) => setTimeout(resolve, 100));
				}
			}

			// Check if aborted
			if (controller.signal.aborted) {
				const duration = (Date.now() - startTime) / 1000;
				setOperationState((prev) => ({
					...prev,
					status: 'stopped',
					result: {
						...prev.result,
						duration,
					},
				}));
			} else {
				// All batches complete
				const duration = (Date.now() - startTime) / 1000;
				setOperationState((prev) => ({
					...prev,
					status: 'completed',
					result: {
						processed: totalProcessed,
						total: totalPending,
						failed: totalFailed,
						duration,
					},
				}));

				// Refresh vector status and parent data
				await fetchVectorStatus();
				if (onRefresh) {
					await onRefresh();
				}
			}
		} catch (err) {
			if (err.name === 'AbortError') {
				const duration = (Date.now() - startTime) / 1000;
				setOperationState((prev) => ({
					...prev,
					status: 'stopped',
					result: {
						...prev.result,
						duration,
					},
				}));
			} else {
				setOperationState((prev) => ({
					...prev,
					status: 'error',
					result: {
						...prev.result,
						error: err.message || __('Batch processing failed', 'gregius-data'),
					},
				}));
			}
		}
	};

	/**
	 * Stop batch processing
	 */
	const handleStop = () => {
		if (abortController) {
			abortController.abort();
		}
	};

	/**
	 * Handle remove model
	 */
	const handleRemove = () => {
		// Confirm before removing
		if (!window.confirm(__('Are you sure you want to remove this model? Vector data will remain in the database.', 'gregius-data'))) {
			return;
		}

		const count = vectorStatus?.posts_with_vectors || 0;
		onRemove(model.model_key, count);
	};

	/**
	 * Handle delete vectors action
	 */
	const handleDeleteVectors = () => {
		if (actualVectors === 0) {
			alert(__('No vectors to delete.', 'gregius-data'));
			return;
		}

		setShowDeleteModal(true);
	};

	/**
	 * Handle successful vector deletion
	 */
	const handleDeleteSuccess = (deleted) => {
		setShowDeleteModal(false);
		// Refresh vector status and parent data
		fetchVectorStatus();
		if (onRefresh) {
			onRefresh();
		}
	};

	// Vocabulary status helpers
	const vocabularyExists = vocabularyStatus?.exists || false;
	const vocabularyVersion = vocabularyStatus?.version || __('None', 'gregius-data');
	const currentPosts = vocabularyStatus?.current_post_count || 0;
	const cachedPosts = vocabularyStatus?.cached_post_count || 0;
	const drift = vocabularyStatus?.posts_added || 0;
	const driftPercentage = vocabularyStatus?.drift_percentage || 0;
	const driftStatus = vocabularyStatus?.status || 'success';

	// Vector status helpers
	const totalPosts = vectorStatus?.total_posts || 0;
	const totalChunks = vectorStatus?.total_chunks || 0;
	const expectedVectors = vectorStatus?.expected_vectors || 0;
	const actualVectors = vectorStatus?.actual_vectors || 0;
	const postsWithVectors = vectorStatus?.posts_with_vectors || 0;
	const postsPending = vectorStatus?.posts_pending_vectors || 0;
	const postsOutdated = vectorStatus?.posts_with_outdated_vectors || 0;
	const totalDrift = postsPending + postsOutdated; // Total posts needing vectors (pending + outdated)
	const vectorDriftPercentage = vectorStatus?.drift_percentage || 0; // Percentage of existing vectors that are outdated
	const vectorCoverage = expectedVectors > 0 ? ((actualVectors / expectedVectors) * 100).toFixed(1) : '0.0';

	return (
		<>
			<Card className="gg-data-vector-card gg-data-tfidf-card" isRounded={false}>
				<CardHeader>
					<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', width: '100%' }}>
						<div className="gg-data-card-header-content">
							<div style={{ display: 'flex', flexDirection: 'row', alignItems: 'center', gap: '.25em' }}>
								<h3 style={{ margin: '0' }}>
									{__('TF-IDF Embeddings', 'gregius-data')}{' '}
								</h3>
								<span className="components-badge is-info" style={{ marginLeft: '.25em', fontWeight: 'unset' }}>
									{__('Internal (Free)', 'gregius-data')}
								</span>
							</div>
							<p style={{ margin: '0' }}>
								{model.dimensions}{__(' dimensions', 'gregius-data')}
							</p>
						</div>

						<DropdownMenu
							icon={moreVertical}
							label={__('Actions', 'gregius-data')}
							controls={[
								{
									title: operationState.status === 'processing' && operationState.type === 'prepare-vocab'
										? __('Preparing...', 'gregius-data')
										: vocabularyExists
											? __('Regenerate Vocabulary', 'gregius-data')
											: __('Prepare Vocabulary', 'gregius-data'),
									onClick: handlePrepareVocabularyClick,
									isDisabled: operationState.status === 'processing',
								},
								{
									title: operationState.status === 'processing' && operationState.type === 'generate'
										? __('Generating...', 'gregius-data')
										: __('Generate Vectors', 'gregius-data'),
									onClick: handleGenerateClick,
									isDisabled: operationState.status === 'processing' || !vocabularyExists,
								},
								{
									title: operationState.status === 'processing' && operationState.type === 'regenerate'
										? __('Regenerating...', 'gregius-data')
										: __('Regenerate All Vectors', 'gregius-data'),
									onClick: handleRegenerateClick,
									isDisabled: operationState.status === 'processing' || !vocabularyExists,
								},
								{
									title: __('Delete Vectors', 'gregius-data'),
									onClick: handleDeleteVectors,
									isDisabled: operationState.status === 'processing' || actualVectors === 0,
								},
								{
									title: __('Remove Model', 'gregius-data'),
									onClick: handleRemove,
									isDisabled: operationState.status === 'processing',
								},
							]}
						/>
					</div>
				</CardHeader>

				<CardBody>
					{ /* Vocabulary Section */}
					<div style={{ marginBottom: '20px' }}>
						<h4 style={{ margin: '0 0 8px 0', fontSize: '13px', fontWeight: 600 }}>
							{__('Vocabulary', 'gregius-data')}
						</h4>
						<div style={{ fontSize: '13px', lineHeight: '1.8' }}>
							<div>
								<strong>{__('Version: ', 'gregius-data')}</strong>
								<span>{vocabularyVersion}</span>
							</div>
							<div>
								<strong>{__('Processed: ', 'gregius-data')}</strong>
								<span>{currentPosts.toLocaleString()} {__('posts', 'gregius-data')}</span>
							</div>
							<div>
								<strong>{__('Cached: ', 'gregius-data')}</strong>
								<span>{cachedPosts.toLocaleString()} {__('posts', 'gregius-data')}</span>
							</div>
						<div>
							<strong>{__('Drift: ', 'gregius-data')}</strong>
							<span className={`components-badge is-${currentPosts > 0 ? (driftPercentage === 0 ? 'success' : Math.abs(driftPercentage) > 10 ? 'error' : 'warning') : 'info'}`}>
								{currentPosts > 0
									? `${driftPercentage > 0 ? '+' : ''}${Number(driftPercentage).toFixed(1)}%`
									: __('N/A', 'gregius-data')
								}
							</span>
						</div>
						</div>
					</div>

					{ /* Vector Status Section */}
					<div>
						<h4 style={{ margin: '0 0 8px 0', fontSize: '13px', fontWeight: 600 }}>
							{__('Vector Status', 'gregius-data')}
						</h4>
						{vectorStatus ? (
							<div style={{ fontSize: '13px', lineHeight: '1.8' }}>
								<div>
									<strong>{__('Expected: ', 'gregius-data')}</strong>
									<span>{expectedVectors.toLocaleString()} {__('vectors', 'gregius-data')}</span>
									<span style={{ fontSize: '11px', color: '#757575', marginLeft: '4px' }}>
										({totalPosts.toLocaleString()} × 2 + {totalChunks.toLocaleString()})
									</span>
								</div>
								<div>
									<strong>{__('Generated: ', 'gregius-data')}</strong>
									<span>{actualVectors.toLocaleString()} {__('vectors', 'gregius-data')}</span>
								</div>
							<div>
								<strong>{__('Drift: ', 'gregius-data')}</strong>
								<span className={`components-badge is-${vectorDriftPercentage === 0 ? 'success' : Math.abs(vectorDriftPercentage) > 10 ? 'error' : 'warning'}`}>
									{expectedVectors > 0
										? `${vectorDriftPercentage > 0 ? '+' : ''}${Number(vectorDriftPercentage).toFixed(1)}%`
										: __('N/A', 'gregius-data')
									}
								</span>
							</div>
							</div>
						) : (
							<Spinner />
						)}
					</div>
				</CardBody>
			</Card>

			{ /* Modals */}
			{modalState && (
				<Modal
					title={
						modalState.type === 'prepare-vocab'
							? vocabularyExists
								? __('Regenerate Vocabulary', 'gregius-data')
								: __('Prepare Vocabulary', 'gregius-data')
							: modalState.type === 'regenerate'
								? __('Regenerate All Vectors', 'gregius-data')
								: __('Generate Vectors', 'gregius-data')
					}
					onRequestClose={() => {
						if (operationState.status === 'idle' || operationState.status === 'completed' || operationState.status === 'stopped') {
							setModalState(null);
						}
					}}
					isDismissible={operationState.status === 'idle' || operationState.status === 'completed' || operationState.status === 'stopped'}
				>
					{ /* IDLE STATE */}
					{operationState.status === 'idle' && (
						<>
							<p>
								{modalState.type === 'prepare-vocab'
									? sprintf(
										__('This will analyze %s posts and build the vocabulary cache.', 'gregius-data'),
										currentPosts.toLocaleString()
									)
									: modalState.type === 'regenerate'
										? sprintf(
											__('This will regenerate vectors for all %s posts. This may take some time.', 'gregius-data'),
											totalPosts.toLocaleString()
										)
										: sprintf(
											__('This will process %s posts.', 'gregius-data'),
											(vectorStatus?.posts_pending_vectors || 0).toLocaleString()
										)
								}
							</p>
							<div style={{ display: 'flex', gap: '8px', marginTop: '16px', justifyContent: 'flex-start' }}>
								<Button
									variant="primary"
									onClick={() => {
										if (modalState.type === 'prepare-vocab') {
											startVocabularyPreparation();
										} else {
											startBatchProcessing(modalState.type === 'regenerate' ? new Date().toISOString() : null);
										}
									}}
								>
									{modalState.type === 'prepare-vocab'
										? __('Prepare', 'gregius-data')
										: modalState.type === 'regenerate'
											? __('Regenerate All', 'gregius-data')
											: __('Generate', 'gregius-data')
									}
								</Button>
								<Button
									variant="link"
									onClick={() => setModalState(null)}
								>
									{__('Cancel', 'gregius-data')}
								</Button>
							</div>
						</>
					)}

					{ /* PROCESSING STATE */}
					{operationState.status === 'processing' && (
						<>
							<p>
								{modalState.type === 'prepare-vocab'
									? __('Preparing vocabulary...', 'gregius-data')
									: sprintf(
										__('Processed %s of %s posts...', 'gregius-data'),
										(operationState.result?.processed || 0).toLocaleString(),
										(operationState.result?.total || 0).toLocaleString()
									)
								}
							</p>

							{ /* Error notice inline */}
							{operationState.result?.error && (
								<Notice status="error" isDismissible={false} style={{ marginTop: '12px' }}>
									{operationState.result.error}
								</Notice>
							)}

							<div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-start', marginTop: '16px' }}>
								<Button variant="secondary" isBusy disabled>
									{__('Processing...', 'gregius-data')}
								</Button>
								{modalState.type !== 'prepare-vocab' && (
									<Button variant="link" onClick={handleStop}>
										{__('Stop', 'gregius-data')}
									</Button>
								)}
							</div>
						</>
					)}

					{ /* COMPLETED STATE */}
					{operationState.status === 'completed' && (
						<>
							{modalState.type === 'prepare-vocab' ? (
								<>
									<p>{__('Vocabulary prepared successfully!', 'gregius-data')}</p>
									<p style={{ fontSize: '13px', color: '#757575' }}>
										{sprintf(
											__('Version %s', 'gregius-data'),
											operationState.result?.version || ''
										)}
										<br />
										{sprintf(
											__('%s terms from %s posts', 'gregius-data'),
											(operationState.result?.unique_terms || 0).toLocaleString(),
											(operationState.result?.post_count || 0).toLocaleString()
										)}
									</p>
								</>
							) : (
								<>
									<p>
										{sprintf(
											__('Generated %s vectors successfully!', 'gregius-data'),
											(operationState.result?.processed || 0).toLocaleString()
										)}
									</p>
									<p style={{ fontSize: '13px', color: '#757575' }}>
										{sprintf(
											__('Duration: %s seconds', 'gregius-data'),
											(operationState.result?.duration || 0).toFixed(1)
										)}
									</p>
								</>
							)}
							<div style={{ display: 'flex', gap: '8px', marginTop: '16px', justifyContent: 'flex-start' }}>
								<Button
									variant="primary"
									onClick={() => setModalState(null)}
								>
									{__('Close', 'gregius-data')}
								</Button>
							</div>
						</>
					)}

					{ /* STOPPED STATE */}
					{operationState.status === 'stopped' && (
						<>
							<p>{__('Processing stopped by user.', 'gregius-data')}</p>
							<p style={{ fontSize: '13px', color: '#757575' }}>
								{sprintf(
									__('Processed %s of %s posts', 'gregius-data'),
									(operationState.result?.processed || 0).toLocaleString(),
									(operationState.result?.total || 0).toLocaleString()
								)}
							</p>
							<div style={{ display: 'flex', gap: '8px', marginTop: '16px', justifyContent: 'flex-start' }}>
								<Button
									variant="primary"
									onClick={() => setModalState(null)}
								>
									{__('Close', 'gregius-data')}
								</Button>
							</div>
						</>
					)}

					{ /* ERROR STATE */}
					{operationState.status === 'error' && (
						<>
							<Notice status="error" isDismissible={false}>
								{operationState.result?.error || __('An error occurred', 'gregius-data')}
							</Notice>
							<div style={{ display: 'flex', gap: '8px', marginTop: '16px', justifyContent: 'flex-start' }}>
								<Button
									variant="primary"
									onClick={() => setModalState(null)}
								>
									{__('Close', 'gregius-data')}
								</Button>
							</div>
						</>
					)}
				</Modal>
			)}

			{/* Batch Delete Modal */}
			<BatchDeleteModal
				isOpen={showDeleteModal}
				modelKey={model.model_key}
				connectionName={connection}
				totalVectors={actualVectors}
				batchSize={500}
				onClose={() => setShowDeleteModal(false)}
				onSuccess={handleDeleteSuccess}
			/>
		</>
	);
};

export default TFIDFVectorCard;
