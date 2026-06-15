/**
 * Batch Delete Modal Component
 *
 * Generic modal for batch vector deletion with:
 * - Progress tracking (processed / total)
 * - Time estimates and elapsed duration
 * - Error handling and retry logic
 * - Cancellation support via AbortController
 *
 * Reusable for TF-IDF, API embeddings, and future post deletion.
 *
 * @package    Gregius_Data
 * @subpackage Gregius_Data/assets/src/scripts/dashboard/components/vectors
 * @since      1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * BatchDeleteModal Component
 *
 * @param {Object}   props                    Props object.
 * @param {boolean}  props.isOpen             Whether modal is open.
 * @param {string}   props.modelKey           Model key being deleted.
 * @param {string}   props.connectionName     Database connection name.
 * @param {number}   props.totalVectors       Total vectors to delete (for progress).
 * @param {number}   props.batchSize          Vectors per request (default 500).
 * @param {Function} props.onClose            Callback when modal closes.
 * @param {Function} props.onSuccess          Callback after successful deletion.
 * @return {JSX.Element} Modal component or null.
 */
const BatchDeleteModal = ({
	isOpen,
	modelKey,
	connectionName,
	totalVectors = 0,
	batchSize = 500,
	mode = 'vectors',
	onClose,
	onSuccess,
}) => {
	// Batch delete operation state
	const [deleteState, setDeleteState] = useState({
		status: 'idle', // 'idle' | 'processing' | 'completed' | 'error' | 'stopped'
		processed: 0, // Vectors deleted so far
		total: totalVectors, // Total to delete
		offset: 0, // Current pagination cursor
		errors: [], // Batch-level errors
		duration: 0, // Elapsed time (ms)
		abortController: null, // For cancellation
	});

	// Keep total in sync with latest card status when modal opens.
	useEffect(() => {
		if (!isOpen) {
			return;
		}

		setDeleteState((prev) => ({
			...prev,
			total: totalVectors,
		}));
	}, [isOpen, totalVectors]);

	/**
	 * Execute batch delete loop
	 */
	const executeBatchDelete = async () => {
		setDeleteState((prev) => ({
			...prev,
			status: 'processing',
			processed: 0,
			offset: 0,
			errors: [],
			duration: 0,
		}));

		const startTime = Date.now();
		const controller = new AbortController();
		setDeleteState((prev) => ({ ...prev, abortController: controller }));

		let hasMore = true;
		let offset = 0;

		try {
			while (hasMore && !controller.signal.aborted) {
				try {
					const path = mode === 'sync' ? '/gg-data/v1/sync/batch-delete' : '/gg-data/v1/vectors/batch-delete';
					const payload = mode === 'sync'
						? {
							connection_name: connectionName,
							batch_size: batchSize,
							offset,
							limit: totalVectors,
						}
						: {
							model_key: modelKey,
							connection_name: connectionName,
							batch_size: batchSize,
							offset,
							limit: totalVectors,
						};

					const response = await apiFetch({
						path,
						method: 'POST',
						data: payload,
						signal: controller.signal,
					});

					if (!response.success) {
						throw new Error(response.message || __('Batch delete failed', 'gregius-data'));
					}

					// Update state with batch result
					setDeleteState((prev) => ({
						...prev,
						processed: response.total_deleted,
						offset: response.next_offset,
						duration: Date.now() - startTime,
						errors:
							response.errors && response.errors.length > 0
								? [...prev.errors, ...response.errors]
								: prev.errors,
					}));

					hasMore = response.has_more;
					offset = response.next_offset;

					// Throttle to avoid hammering server
					if (hasMore) {
						await new Promise((resolve) => setTimeout(resolve, 100));
					}
				} catch (error) {
					if (error.name === 'AbortError') {
						setDeleteState((prev) => ({
							...prev,
							status: 'stopped',
							duration: Date.now() - startTime,
						}));
						return;
					}

					// Batch request error
					setDeleteState((prev) => ({
						...prev,
						status: 'error',
						errors: [
							...prev.errors,
							{
								error: error.message,
								batch: offset,
							},
						],
						duration: Date.now() - startTime,
					}));
					return;
				}
			}

			// Success: all batches complete
			if (!controller.signal.aborted) {
				setDeleteState((prev) => ({
					...prev,
					status: 'completed',
					duration: Date.now() - startTime,
				}));
			}
		} catch (err) {
			setDeleteState((prev) => ({
				...prev,
				status: 'error',
				duration: Date.now() - startTime,
				errors: [
					...prev.errors,
					{
						error: err.message || __('Unexpected error', 'gregius-data'),
					},
				],
			}));
		}
	};

	/**
	 * Handle Stop button
	 */
	const handleStop = () => {
		if (deleteState.abortController) {
			deleteState.abortController.abort();
		}
	};

	/**
	 * Handle Close/Complete
	 */
	const handleClose = () => {
		// Don't close while processing
		if (deleteState.status === 'processing') {
			return;
		}

		// Call success callback if completed
		if (deleteState.status === 'completed' && onSuccess) {
			onSuccess(deleteState.processed);
		}

		// Reset and close
		setDeleteState({
			status: 'idle',
			processed: 0,
			total: totalVectors,
			offset: 0,
			errors: [],
			duration: 0,
			abortController: null,
		});

		onClose();
	};

	/**
	 * Retry current batch
	 */
	const handleRetry = () => {
		setDeleteState((prev) => ({
			...prev,
			status: 'idle',
			errors: [], // Clear errors for retry
		}));
		// Don't reset offset - retry from same position
		executeBatchDelete();
	};

	if (!isOpen) {
		return null;
	}

	const progressPercent =
		deleteState.total > 0
			? Math.min(100, Math.round((deleteState.processed / deleteState.total) * 100))
			: 0;

	const canClose =
		deleteState.status === 'idle' ||
		deleteState.status === 'completed' ||
		deleteState.status === 'error' ||
		deleteState.status === 'stopped';

	return (
		<Modal
			title={sprintf(
				mode === 'sync' ? __('Delete Synced Content', 'gregius-data') : __('Delete Vectors - %s', 'gregius-data'),
				modelKey || ''
			)}
			onRequestClose={handleClose}
			shouldCloseOnClickOutside={canClose}
			shouldCloseOnEsc={canClose}
			className="gg-data-batch-delete-modal"
		>
			{deleteState.status === 'idle' && (
				<>
					<p>
						{mode === 'sync'
							? __('This will delete synced content from PostgreSQL tables (wp_posts_chunks, wp_posts_clean, wp_posts). This operation cannot be undone.', 'gregius-data')
							: sprintf(
								__('This will delete all %d vectors for %s. This operation cannot be undone.', 'gregius-data'),
								deleteState.total,
								modelKey
							)}
					</p>

					<div
						style={{
							display: 'flex',
							gap: '12px',
							justifyContent: 'flex-start',
							marginTop: '20px',
						}}
					>
						<Button
							variant="primary"
							isDestructive
							onClick={executeBatchDelete}
						>
							{mode === 'sync' ? __('Delete Synced Content', 'gregius-data') : __('Delete Vectors', 'gregius-data')}
						</Button>
						<Button
							variant="link"
							onClick={handleClose}
						>
							{__('Cancel', 'gregius-data')}
						</Button>
					</div>
				</>
			)}

			{deleteState.status === 'processing' && (
				<>
					{deleteState.total > 0 ? (
						<p>
							{sprintf(
								__('Deleting %d of %d (%d%%)', 'gregius-data'),
								deleteState.processed,
								deleteState.total,
								progressPercent
							)}
						</p>
					) : (
						<p>
							{sprintf(
								__('Deleted %d records so far...', 'gregius-data'),
								deleteState.processed
							)}
						</p>
					)}

					{deleteState.errors.length > 0 && (
						<Notice
							status="warning"
							isDismissible={false}
							style={{ marginBottom: '16px' }}
						>
							{sprintf(
								__('Batch %d had errors. Continuing...', 'gregius-data'),
								deleteState.errors[0].batch
							)}
						</Notice>
					)}

					<div
						style={{
							display: 'flex',
							gap: '8px',
							justifyContent: 'flex-start',
						}}
					>
						<Button
							variant="secondary"
							isBusy
							disabled
						>
							{mode === 'sync' ? __('Deleting sync data...', 'gregius-data') : __('Deleting...', 'gregius-data')}
						</Button>
						<Button
							variant="link"
							onClick={handleStop}
						>
							{__('Stop', 'gregius-data')}
						</Button>
					</div>
				</>
			)}

			{deleteState.status === 'completed' && (
				<>
					<Notice status="success" isDismissible={false}>
						{mode === 'sync'
							? sprintf(
								__('Successfully deleted %d sync records in %s seconds', 'gregius-data'),
								deleteState.processed,
								(deleteState.duration / 1000).toFixed(1)
							)
							: sprintf(
								__('Successfully deleted %d vectors in %s seconds', 'gregius-data'),
								deleteState.processed,
								(deleteState.duration / 1000).toFixed(1)
							)}
					</Notice>

					{deleteState.errors.length > 0 && (
						<Notice status="warning" isDismissible={false} style={{ marginTop: '12px' }}>
							{sprintf(
								__('%d batch errors occurred but operation completed', 'gregius-data'),
								deleteState.errors.length
							)}
						</Notice>
					)}

					<div
						style={{
							marginTop: '20px',
							display: 'flex',
							justifyContent: 'flex-end',
							gap: '8px',
						}}
					>
						<Button
							variant="primary"
							onClick={handleClose}
						>
							{__('Close', 'gregius-data')}
						</Button>
					</div>
				</>
			)}

			{deleteState.status === 'error' && (
				<>
					<Notice status="error" isDismissible={false}>
						{deleteState.errors[0]?.error ||
							__('An error occurred during deletion', 'gregius-data')}
					</Notice>

					<div
						style={{
							marginTop: '12px',
							fontSize: '13px',
							color: '#757575',
						}}
					>
						{sprintf(
							__('Deleted %d of %d vectors before error.', 'gregius-data'),
							deleteState.processed,
							deleteState.total
						)}
					</div>

					<div
						style={{
							marginTop: '20px',
							display: 'flex',
							justifyContent: 'flex-end',
							gap: '8px',
						}}
					>
						<Button
							variant="primary"
							onClick={handleRetry}
						>
							{__('Retry', 'gregius-data')}
						</Button>
						<Button
							variant="link"
							onClick={handleClose}
						>
							{__('Close', 'gregius-data')}
						</Button>
					</div>
				</>
			)}

			{deleteState.status === 'stopped' && (
				<>
					<Notice status="warning" isDismissible={false}>
						{sprintf(
							__('Stopped at %d%%. %d vectors deleted.', 'gregius-data'),
							progressPercent,
							deleteState.processed
						)}
					</Notice>

					<div
						style={{
							marginTop: '20px',
							display: 'flex',
							justifyContent: 'flex-end',
							gap: '8px',
						}}
					>
						<Button
							variant="link"
							onClick={handleClose}
						>
							{__('Close', 'gregius-data')}
						</Button>
					</div>
				</>
			)}
		</Modal>
	);
};

export default BatchDeleteModal;
