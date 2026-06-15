import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Card, CardHeader, CardBody, Notice, Spinner, DropdownMenu, Modal, SelectControl, __experimentalHeading as Heading } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { moreVertical } from '@wordpress/icons';

/**
 * Vector Integrity Card Component
 * 
 * Enhanced component that combines vector status display with generation controls:
 * - Shows summary metrics table (Posts, With Vectors, Drift, Status)
 * - Handles batch vector generation with sync-style AJAX loop
 * - Processes 10 posts per request with real-time progress updates
 * - Validates vocabulary cache before generation (Phase 4)
 * - Auto-refreshes parent status when generation completes
 * 
 * States:
 * - Ready: Shows "Generate Vectors" button with confirmation
 * - Processing: Shows progress bar with live counter
 * - Complete: Shows success message with updated status
 * - Error: Shows error with retry option
 * - Blocked: Vocabulary not prepared or critical drift detected
 * 
 * @since 1.9.4
 */
const VectorIntegrityCard = ({ selectedConnectionId, vectorStatus, vocabularyStatus, onGenerationComplete, models, selectedModel, onSelectModel }) => {
	// Modal state for vector generation
	const [modalState, setModalState] = useState(null); // { type: 'generate', data: vectorStatus }
	const [operationState, setOperationState] = useState({
		status: 'idle', // 'idle' | 'processing' | 'completed' | 'stopped'
		type: null,
		data: null,
		result: null
	});
	const [abortController, setAbortController] = useState(null);

	/**
	 * Handle Generate Vectors button click
	 * Opens modal for confirmation (matches sync/clean pattern)
	 */
	const handleGenerateClick = () => {
		// Phase 4: Validate vocabulary before generation ONLY for TF-IDF
		if (!selectedModel) {
			if (!vocabularyStatus || !vocabularyStatus.exists) {
				return; // Will show error in UI
			}

			if (vocabularyStatus.status === 'error') {
				return; // Will show error in UI
			}
		}

		// Open confirmation modal
		setModalState({
			type: 'generate',
			data: vectorStatus
		});
		setOperationState({
			status: 'idle',
			type: 'generate',
			data: vectorStatus,
			result: null
		});
	};

	/**
	 * Handle Regenerate Vectors button click
	 */
	const handleRegenerateClick = () => {
		// Phase 4: Validate vocabulary before generation ONLY for TF-IDF
		if (!selectedModel) {
			if (!vocabularyStatus || !vocabularyStatus.exists) {
				return; // Will show error in UI
			}

			if (vocabularyStatus.status === 'error') {
				return; // Will show error in UI
			}
		}

		// Open confirmation modal
		setModalState({
			type: 'regenerate',
			data: vectorStatus
		});
		setOperationState({
			status: 'idle',
			type: 'regenerate',
			data: vectorStatus,
			result: null
		});
	};

	/**
	 * Start batch vector generation loop
	 * Processes 10 posts per request until all complete
	 * Mirrors handleBatchSync/handleClean pattern
	 */
	const startBatchProcessing = async (regenerateSince = null) => {
		const startTime = Date.now();
		const totalPending = regenerateSince ? (vectorStatus?.total_posts || 0) : (vectorStatus?.posts_pending_vectors || 0);
		const controller = new AbortController();
		setAbortController(controller);

		// Set processing state
		setOperationState({
			status: 'processing',
			type: regenerateSince ? 'regenerate' : 'generate',
			data: vectorStatus,
			result: {
				processed: 0,
				total: totalPending,
				duration: 0
			}
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
						connection_name: selectedConnectionId || 'default',
						batch_size: 10,
						regenerate_since: regenerateSince,
						model_key: selectedModel
					},
					signal: controller.signal
				});

				if (!response.success || !response.batch) {
					throw new Error(response.message || __('Batch processing failed', 'gregius-data'));
				}

				// Update totals
				totalProcessed += response.batch.processed || 0;
				totalFailed += response.batch.failed || 0;

				// Update progress
				const duration = (Date.now() - startTime) / 1000;
				setOperationState(prev => ({
					...prev,
					result: {
						processed: totalProcessed,
						total: totalPending,
						failed: totalFailed,
						duration
					}
				}));

				// Check if more batches remain
				hasMore = response.batch.has_more;

				// Short delay between batches (100ms) for UI updates
				if (hasMore) {
					await new Promise(resolve => setTimeout(resolve, 100));
				}
			}

			// Check if aborted
			if (controller.signal.aborted) {
				const duration = (Date.now() - startTime) / 1000;
				setOperationState(prev => ({
					...prev,
					status: 'stopped',
					result: {
						...prev.result,
						duration
					}
				}));
			} else {
				// All batches complete
				const duration = (Date.now() - startTime) / 1000;
				setOperationState(prev => ({
					...prev,
					status: 'completed',
					result: {
						processed: totalProcessed,
						total: totalPending,
						failed: totalFailed,
						duration
					}
				}));

				// Notify parent to refresh status
				if (onGenerationComplete) {
					await onGenerationComplete();
				}
			}
		} catch (err) {
			if (err.name === 'AbortError') {
				const duration = (Date.now() - startTime) / 1000;
				setOperationState(prev => ({
					...prev,
					status: 'stopped',
					result: {
						...prev.result,
						duration
					}
				}));
			} else {
				setOperationState(prev => ({
					...prev,
					status: 'idle',
					result: null
				}));
			}
		}
	};

	// No connection selected - show notice
	if (!selectedConnectionId) {
		return (
			<Notice status="warning" isDismissible={false}>
				{__('Please select a connection to begin.', 'gregius-data')}
			</Notice>
		);
	}

	// Loading state - show spinner without card
	if (!vectorStatus) {
		return (
			<div style={{ display: 'flex', alignItems: 'center', gap: '12px', padding: '1rem' }}>
				<Spinner />
				<p>{__('Loading vector status...', 'gregius-data')}</p>
			</div>
		);
	}

	return (
		<Card isRounded={false}>
			<CardHeader>
				<Heading level={3} style={{ margin: 0 }}>{__('Vector Integrity', 'gregius-data')}</Heading>
			</CardHeader>
			<CardBody>
				<div style={{ marginBottom: '20px' }}>
					<SelectControl
						label={__('Embedding Model', 'gregius-data')}
						value={selectedModel}
						options={[
							{ label: __('Select a model...', 'gregius-data'), value: '' },
							...(models || []).map(m => ({ 
								label: m.model_key === 'tfidf-300' ? __('TF-IDF (Internal)', 'gregius-data') : (m.model_name || m.model_key), 
								value: m.model_key 
							}))
						]}
						onChange={onSelectModel}
						help={__('Select the model to use for vector generation.', 'gregius-data')}
					/>
				</div>

				{/* Vocabulary warning notice (Phase 4) - Only for TF-IDF */}
				{!selectedModel && vocabularyStatus && vocabularyStatus.status === 'warning' && (
					<Notice status="warning" isDismissible={false}>
						{__(`Minor vocabulary drift detected (${vocabularyStatus.drift_percentage?.toFixed(2)}%). Consider regenerating vocabulary for optimal accuracy.`, 'gregius-data')}
					</Notice>
				)}

				{/* Vocabulary not prepared - Block generation (Phase 4) - Only for TF-IDF */}
				{!selectedModel && (!vocabularyStatus || !vocabularyStatus.exists) && (
					<Notice status="error" isDismissible={false}>
						{__('Vocabulary must be prepared before generating vectors.', 'gregius-data')}
					</Notice>
				)}

				{/* Critical vocabulary drift - Block generation (Phase 4) - Only for TF-IDF */}
				{!selectedModel && vocabularyStatus && vocabularyStatus.status === 'error' && (
					<Notice status="error" isDismissible={false}>
						{__(`Critical vocabulary drift (${vocabularyStatus.drift_percentage?.toFixed(2)}%). Regenerate vocabulary before generating vectors.`, 'gregius-data')}
					</Notice>
				)}

				{/* Complete State - All vectors generated */}
				{vectorStatus.posts_pending_vectors === 0 && (selectedModel || (vocabularyStatus && vocabularyStatus.exists)) && (
					<Notice status="success" isDismissible={false}>
						{__('All posts have vectors generated!', 'gregius-data')}
					</Notice>
				)}

				{/* Vector status summary table */}
				<div  style={{ overflowX: 'auto' }}>
					<table className="sync-validation-table">
						<thead>
							<tr>
								<th>{__('Metric', 'gregius-data')}</th>
								<th>{__('Total', 'gregius-data')}</th>
								<th>{__('With Vectors', 'gregius-data')}</th>
								<th>{__('Drift', 'gregius-data')}</th>
								<th>{__('Status', 'gregius-data')}</th>
								<th>{__('Actions', 'gregius-data')}</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>{__('Posts', 'gregius-data')}</td>
								<td>{vectorStatus.total_posts?.toLocaleString() || 0}</td>
								<td>{vectorStatus.posts_with_vectors?.toLocaleString() || 0}</td>
								<td className={vectorStatus.posts_pending_vectors > 0 ? 'has-drift' : ''}>
									+{vectorStatus.posts_pending_vectors?.toLocaleString() || 0}
								</td>
								<td>
									{operationState.status === 'processing' && operationState.result?.processed > 0 ? (
										<span className="components-badge">
											{sprintf(__('Generating... (%d/%d)', 'gregius-data'), operationState.result.processed, operationState.result.total)}
										</span>
									) : vectorStatus.total_posts === 0 ? (
										<span className="components-badge">
											{__('No Posts', 'gregius-data')}
										</span>
									) : vectorStatus.posts_pending_vectors === 0 ? (
										<span className="components-badge is-success">
											{__('Complete', 'gregius-data')}
										</span>
									) : (
										<span className="components-badge is-warning">
											{__('Pending', 'gregius-data')}
										</span>
									)}
								</td>
							<td>
								<DropdownMenu
									icon={moreVertical}
									label={__('Actions', 'gregius-data')}
									controls={[
										{
											title: operationState.status === 'processing' ? __('Generating...', 'gregius-data') : __('Generate Vectors', 'gregius-data'),
											onClick: handleGenerateClick,
											isDisabled: operationState.status === 'processing' || (!selectedModel && (!vocabularyStatus || !vocabularyStatus.exists || vocabularyStatus.status === 'error')),
										},
										{
											title: __('Regenerate All', 'gregius-data'),
											onClick: handleRegenerateClick,
											isDisabled: operationState.status === 'processing' || (!selectedModel && (!vocabularyStatus || !vocabularyStatus.exists || vocabularyStatus.status === 'error')),
										},
									]}
								/>
							</td>
							</tr>
						</tbody>
					</table>
				</div>
			</CardBody>

			{modalState && (
				<Modal
					title={modalState?.type === 'regenerate' ? sprintf(__('Regenerate Vectors', 'gregius-data')) : sprintf(__('Generate Vectors', 'gregius-data'))}
					onRequestClose={() => {
						if (operationState.status === 'idle' || operationState.status === 'completed' || operationState.status === 'stopped') {
							setModalState(null);
						}
					}}
				>
					{operationState.status === 'idle' && (
						<>
							<p>
								{modalState?.type === 'regenerate' ? 
									sprintf(
										__('This will regenerate vectors for all %d posts. This may take some time.', 'gregius-data'),
										vectorStatus.total_posts
									) :
									sprintf(
										__('This will process %d posts.', 'gregius-data'),
										vectorStatus.posts_pending_vectors
									)
								}
							</p>
							<div style={{ display: 'flex', gap: '8px', marginTop: '16px', justifyContent: 'flex-start' }}>
								<Button
									variant="primary"
									onClick={() => startBatchProcessing(modalState?.type === 'regenerate' ? new Date().toISOString() : null)}
								>
									{modalState?.type === 'regenerate' ? __('Regenerate All', 'gregius-data') : __('Generate', 'gregius-data')}
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

					{operationState.status === 'processing' && (
						<>
							<p>
								{sprintf(
									__('Processed %d of %d posts...', 'gregius-data'),
									operationState.result.processed || 0,
									operationState.result.total || 0
								)}
							</p>
							<div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-start', marginTop: '16px' }}>
								<Button variant="secondary" isBusy disabled>
									{__('Generating...', 'gregius-data')}
								</Button>
								<Button
									variant="link"
									onClick={() => {
										if (abortController) {
											abortController.abort();
										}
									}}
								>
									{__('Stop', 'gregius-data')}
								</Button>
							</div>
						</>
					)}

					{(operationState.status === 'completed' || operationState.status === 'stopped') && (
						<>
							{operationState.status === 'completed' && (
								<>
									<Notice status="success" isDismissible={false}>
										{__('Operation completed successfully', 'gregius-data')}
									</Notice>
									<p>
										{(() => {
											const processed = operationState.result.processed || 0;
											const total = operationState.result.total || 0;
											const skipped = total - processed;
											const duration = typeof operationState.result.duration === 'number'
												? operationState.result.duration.toFixed(2) + 's'
												: operationState.result.duration || '0s';
											
											return skipped > 0
												? sprintf(
													__('%d vectors generated, %d skipped (%s).', 'gregius-data'),
													processed,
													skipped,
													duration
												)
												: sprintf(
													__('%d vectors generated (%s).', 'gregius-data'),
													processed,
													duration
												);
										})()}
									</p>
								</>
							)}
							{operationState.status === 'stopped' && (
								<Notice status="warning" isDismissible={false}>
									{sprintf(
										__('Vector generation stopped. Processed %d of %d posts.', 'gregius-data'),
										operationState.result.processed,
										operationState.result.total
									)}
								</Notice>
							)}
							{operationState.result.failures && operationState.result.failures.length > 0 && (
								<Notice status="error" isDismissible={false}>
									{sprintf(
										__('Failed to process %d posts.', 'gregius-data'),
										operationState.result.failures.length
									)}
								</Notice>
							)}
							<div style={{ display: 'flex', gap: '8px', marginTop: '16px', justifyContent: 'flex-start' }}>
								<Button
									variant="link"
									onClick={() => {
										setModalState(null);
										if (onGenerationComplete) {
											onGenerationComplete();
										}
									}}
								>
									{__('Close', 'gregius-data')}
								</Button>
							</div>
						</>
					)}
				</Modal>
			)}
		</Card>
	);
};

export default VectorIntegrityCard;
