/**
 * API Embedding Card Component
 *
 * Card component for API-based embedding models (OpenAI, Voyage AI, etc.)
 * showing generation status, token tracking, and batch processing actions.
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
 * APIEmbeddingCard Component
 *
 * @param {Object}   props            Props object.
 * @param {Object}   props.model      Model configuration.
 * @param {string}   props.connection Connection name.
 * @param {Function} props.onRemove   Callback when remove is clicked (receives modelKey, vectorCount).
 * @param {Function} props.onRefresh  Callback to refresh parent data.
 * @return {JSX.Element} Card component.
 */
const APIEmbeddingCard = ({ model, connection, onRemove, onRefresh }) => {
	// Unified operation state for all actions (generate, regenerate)
	const [operationState, setOperationState] = useState({
		status: 'idle', // 'idle' | 'processing' | 'completed' | 'stopped' | 'error'
		type: null, // 'generate' | 'regenerate'
		data: null,
		result: null,
	});

	// Modal state
	const [modalState, setModalState] = useState(null); // { type: 'generate' | 'regenerate', data: ... }

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
	 * Handle Generate Vectors action
	 */
	const handleGenerateClick = () => {
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
	const handleRegenerateClick = () => {
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
	 * Start batch vector generation/regeneration loop
	 * Processes posts in batches until all complete, accumulating tokens
	 */
	const startBatchProcessing = async (regenerateSince = null) => {
		const startTime = Date.now();
		const totalPending = regenerateSince
			? (vectorStatus?.total_posts || 0)
			: (vectorStatus?.posts_pending_vectors || 0);
		const controller = new AbortController();
		setAbortController(controller);

		setOperationState({
			status: 'processing',
			type: regenerateSince ? 'regenerate' : 'generate',
			data: vectorStatus,
			result: {
				processed: 0,
				total: totalPending,
				totalTokens: 0,
				duration: 0,
			},
		});

		try {
			let hasMore = true;
			let totalProcessed = 0;
			let totalFailed = 0;
			let totalTokens = 0;

			// Batch processing loop
			while (hasMore && !controller.signal.aborted) {
				const response = await apiFetch({
					path: '/gg-data/v1/vectors/batch-generate',
					method: 'POST',
					data: {
						connection_name: connection,
						batch_size: 10, // Smaller batches for API calls
						regenerate_since: regenerateSince,
						model_key: model.model_key,
					},
					signal: controller.signal,
				});

				if (!response.success || !response.batch) {
					throw new Error(response.message || __('Batch processing failed', 'gregius-data'));
				}

				// Update totals (accumulate tokens, not cost)
				totalProcessed += response.batch.processed || 0;
				totalFailed += response.batch.failed || 0;
				totalTokens += response.batch.total_tokens || 0;

				// Update progress
				const duration = (Date.now() - startTime) / 1000;
				setOperationState((prev) => ({
					...prev,
					result: {
						processed: totalProcessed,
						total: totalPending,
						failed: totalFailed,
						totalTokens,
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
						totalTokens,
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
	 * Close modal and reset operation state
	 */
	const handleCloseModal = () => {
		// Don't allow closing while processing
		if (operationState.status === 'processing') {
			return;
		}
		setModalState(null);
		setOperationState({
			status: 'idle',
			type: null,
			data: null,
			result: null,
		});
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
		const deletableVectors = vectorStatus?.actual_vectors ?? vectorStatus?.posts_with_vectors ?? 0;
		if (deletableVectors === 0) {
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

	// Guard against undefined model data
	if (!model || !model.provider) {
		return null;
	}

	// Vector status helpers
	const totalPosts = vectorStatus?.total_posts || 0;
	const postsWithVectors = vectorStatus?.posts_with_vectors || 0;
	const postsPending = vectorStatus?.posts_pending_vectors || 0;
	const postsOutdated = vectorStatus?.posts_with_outdated_vectors || 0;
	const totalDrift = postsPending + postsOutdated; // Total posts needing vectors (pending + outdated)
	const driftPercentage = vectorStatus?.drift_percentage || 0;
	const percentage = totalPosts > 0 ? Math.round((postsWithVectors / totalPosts) * 100) : 0;
	const actualVectors = vectorStatus?.actual_vectors || 0;
	const deletableVectors = vectorStatus?.actual_vectors ?? vectorStatus?.posts_with_vectors ?? 0;

	return (
		<>
			<Card className="gg-data-vector-card gg-data-api-embedding-card">
				<CardHeader>
					<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', width: '100%' }}>

						<div className="gg-data-card-header-content">
							<div style={{ display: 'flex', flexDirection: 'row', alignItems: 'center', gap: '.25em' }}>
								<h3 style={{ margin: '0' }}>
									{model.provider_model_id || model.model_key}{' '}
								</h3>
								<span className="components-badge is-info">
									{model.provider === 'internal' ? __('Internal (Free)', 'gregius-data') : model.provider.toUpperCase()}
								</span>
							</div>
							<p style={{ margin: '0' }}>
								{model.dimensions || 0}{__(' dimensions', 'gregius-data')}
							</p>
						</div>

						<DropdownMenu
							icon={moreVertical}
							label={__('Vector Actions', 'gregius-data')}
							controls={[
								{
									title: __('Generate Vectors', 'gregius-data'),
									onClick: handleGenerateClick,
									disabled: postsPending === 0,
								},
								{
									title: __('Regenerate All Vectors', 'gregius-data'),
									onClick: handleRegenerateClick,
								},
								{
									title: __('Delete Vectors', 'gregius-data'),
									onClick: handleDeleteVectors,
									disabled: deletableVectors === 0,
								},
								{
									title: __('Remove Model', 'gregius-data'),
									onClick: handleRemove,
								},
							]}
						/>
					</div>
				</CardHeader>

				<CardBody>
					{error && (
						<div
							className="gg-data-error-notice"
							style={{
								padding: '8px 12px',
								marginBottom: '12px',
								background: '#fef7f7',
								border: '1px solid #d63638',
								borderRadius: '4px',
								color: '#d63638',
							}}
						>
							{error}
						</div>
					)}

					<div className="gg-data-vector-stats">
						<div style={{marginBottom: '20px'}}>
							<span className="gg-data-stat-label">
								{__('Provider:', 'gregius-data')}
							</span>
							<span className="gg-data-stat-value">
								{model.provider}
							</span>
						</div>

						<div style={{marginBottom: '20px'}}>
							<h4 className="gg-data-stat-label" style={{margin: '0 0 8px', fontSize: '13px', fontWeight: 600}}>
								{__('Vector Status', 'gregius-data')}
							</h4>
							{vectorStatus !== null ? (
								<div style={{ fontSize: '13px', lineHeight: '1.8' }}>
									<div>
										<strong>{__('Processed: ', 'gregius-data')}</strong>
										<span>{totalPosts.toLocaleString()} {__('posts', 'gregius-data')}</span>
									</div>
									<div>
										<strong>{__('Vectorized: ', 'gregius-data')}</strong>
										<span>{postsWithVectors.toLocaleString()} {__('posts', 'gregius-data')}</span>
									</div>
							<div>
								<strong>{__('Drift: ', 'gregius-data')}</strong>
								{postsWithVectors === 0 && totalPosts > 0 ? (
									<span className="components-badge is-info">
										{__('Not generated', 'gregius-data')}
									</span>
								) : (
									<span className={`components-badge is-${driftPercentage === 0 ? 'success' : Math.abs(driftPercentage) > 10 ? 'error' : 'warning'}`}>
										{totalPosts > 0
											? `${driftPercentage > 0 ? '+' : ''}${Number(driftPercentage).toFixed(1)}%`
											: __('N/A', 'gregius-data')
										}
									</span>
								)}
							</div>
								</div>
							) : (
								<Spinner />
							)}
						</div>
					</div>
				</CardBody>
			</Card>

			{/* Modal for batch processing */}
			{modalState && (
				<Modal
					title={
						modalState.type === 'generate'
							? __('Generate Vectors', 'gregius-data')
							: __('Regenerate All Vectors', 'gregius-data')
					}
					onRequestClose={handleCloseModal}
					className="gg-data-vector-modal"
					shouldCloseOnClickOutside={operationState.status !== 'processing'}
					shouldCloseOnEsc={operationState.status !== 'processing'}
				>
					{operationState.status === 'idle' && (
						<>
							<p>
								{modalState.type === 'generate'
									? sprintf(
										__('Generate vectors for %d posts using %s?', 'gregius-data'),
										postsPending,
										model.provider_model_id
									)
									: sprintf(
										__('Regenerate vectors for all %d posts using %s?', 'gregius-data'),
										totalPosts,
										model.provider_model_id
									)}
							</p>
							<div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end', marginTop: '16px' }}>
								<Button onClick={handleCloseModal}>
									{__('Cancel', 'gregius-data')}
								</Button>
								<Button
									isPrimary
									onClick={() =>
										startBatchProcessing(
											modalState.type === 'regenerate' ? new Date().toISOString() : null
										)
									}
								>
									{modalState.type === 'generate'
										? __('Generate', 'gregius-data')
										: __('Regenerate All', 'gregius-data')}
								</Button>
							</div>
						</>
					)}

					{operationState.status === 'processing' && (
						<>
							<div style={{ marginBottom: '16px' }}>
								<div style={{ fontSize: '14px', marginBottom: '8px', color: '#1e1e1e' }}>
									{sprintf(
										__('Processing %d of %d (%d%%)', 'gregius-data'),
										operationState.result?.processed || 0,
										operationState.result?.total || 0,
										operationState.result?.total > 0
											? Math.round(
												((operationState.result?.processed || 0) / operationState.result.total) * 100
											)
											: 0
									)}
								</div>
								<div style={{ fontSize: '13px', color: '#757575' }}>
									{sprintf(
										__('%s tokens', 'gregius-data'),
										(operationState.result?.totalTokens || 0).toLocaleString()
									)}
								</div>
							</div>
							<div style={{ display: 'flex', justifyContent: 'flex-end' }}>
								<Button isDestructive onClick={handleStop}>
									{__('Stop', 'gregius-data')}
								</Button>
							</div>
						</>
					)}

					{operationState.status === 'completed' && (
						<>
							<Notice status="success" isDismissible={false}>
								{sprintf(
									__('Generated %d vectors (%s tokens)', 'gregius-data'),
									operationState.result?.processed || 0,
									(operationState.result?.totalTokens || 0).toLocaleString()
								)}
							</Notice>
							<div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '16px' }}>
								<Button isPrimary onClick={handleCloseModal}>
									{__('Close', 'gregius-data')}
								</Button>
							</div>
						</>
					)}

					{operationState.status === 'stopped' && (
						<>
							<Notice status="warning" isDismissible={false}>
								{sprintf(
									__('Stopped. Processed %d vectors (%s tokens)', 'gregius-data'),
									operationState.result?.processed || 0,
									(operationState.result?.totalTokens || 0).toLocaleString()
								)}
							</Notice>
							<div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '16px' }}>
								<Button isPrimary onClick={handleCloseModal}>
									{__('Close', 'gregius-data')}
								</Button>
							</div>
						</>
					)}

					{operationState.status === 'error' && (
						<>
							<Notice status="error" isDismissible={false}>
								{operationState.result?.error || __('An error occurred', 'gregius-data')}
							</Notice>
							<div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '16px' }}>
								<Button isPrimary onClick={handleCloseModal}>
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
				totalVectors={deletableVectors}
				batchSize={500}
				onClose={() => setShowDeleteModal(false)}
				onSuccess={handleDeleteSuccess}
			/>
		</>
	);
};

export default APIEmbeddingCard;
