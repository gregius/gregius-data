import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	Button,
	Notice,
	Spinner,
	__experimentalGrid as Grid,
	__experimentalHeading as Heading,
	DropdownMenu,
	MenuItem
} from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

/**
 * Retry Queue Status Card Component
 * 
 * Displays retry queue metrics, pending retries, and dead letter queue items.
 * Provides manual retry and clear functionality.
 */
const RetryQueueCard = ({ selectedConnectionId }) => {
	// Show empty state if no connection selected
	if (!selectedConnectionId) {
		return (
			<Notice status="warning" isDismissible={false}>
				{__('Please select a connection to begin.', 'gregius-data')}
			</Notice>
		);
	}

	const [queueStatus, setQueueStatus] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [refreshInterval, setRefreshInterval] = useState(null);

	/**
	 * Fetch queue status from REST API
	 */
	const fetchQueueStatus = async () => {
		try {
			const response = await apiFetch({
				path: '/gg-data/v1/sync/retry-queue',
				method: 'GET',
			});

			if (response.success) {
				setQueueStatus(response.data);
				setError(null);
			}
		} catch (err) {
			setError(err.message || __('Failed to fetch retry queue status', 'gregius-data'));
		} finally {
			setLoading(false);
		}
	};

	/**
	 * Manual retry of dead letter item
	 */
	const handleManualRetry = async (index) => {
		try {
			const response = await apiFetch({
				path: `/gg-data/v1/sync/retry-queue/retry/${index}`,
				method: 'POST',
			});

			if (response.success) {
				// Refresh queue status after retry
				await fetchQueueStatus();
			}
		} catch (err) {
			setError(err.message || __('Failed to retry item', 'gregius-data'));
		}
	};

	/**
	 * Clear dead letter queue
	 */
	const handleClearDeadLetter = async () => {
		if (!window.confirm(__('Are you sure you want to clear all permanently failed items?', 'gregius-data'))) {
			return;
		}

		try {
			const response = await apiFetch({
				path: '/gg-data/v1/sync/retry-queue/clear',
				method: 'DELETE',
			});

			if (response.success) {
				// Refresh queue status after clearing
				await fetchQueueStatus();
			}
		} catch (err) {
			setError(err.message || __('Failed to clear dead letter queue', 'gregius-data'));
		}
	};

	/**
	 * Setup auto-refresh on mount and when connection changes
	 */
	useEffect(() => {
		// Initial fetch
		fetchQueueStatus();

		// Auto-refresh every 30 seconds
		const interval = setInterval(fetchQueueStatus, 30000);
		setRefreshInterval(interval);

		// Cleanup on unmount or connection change
		return () => {
			if (interval) {
				clearInterval(interval);
			}
		};
	}, [selectedConnectionId]);

	if (loading) {
		return (
			<div className="retry-queue-card">
				<div className="retry-queue-loading">
					<Spinner />
					<p>{__('Loading queue status...', 'gregius-data')}</p>
				</div>
			</div>
		);
	}

	if (error) {
		return (
			<div className="retry-queue-card retry-queue-error">
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			</div>
		);
	}

	const { pending_retries, failed_permanently, items, dead_letter_items } = queueStatus || {};

	// Combine items for display: pending items + dead letter items with source tracking
	const allItems = [
		...(items || []).map((item, idx) => ({ ...item, source: 'pending', originalIndex: idx })),
		...(dead_letter_items || []).map((item, idx) => ({ ...item, source: 'dead_letter', originalIndex: idx }))
	];

	return (
		<Card isRounded={false} className="gg-search-health-card retry-queue-card">
			<CardHeader style={{
				display: 'flex',
				justifyContent: 'space-between',
				alignItems: 'center',
				width: '100%'
			}}>
				<div>
					<Heading level={3} style={{ margin: 0 }}>{__('Retry Queue', 'gregius-data')}</Heading>
					<p className="description" style={{ margin: 0 }}>
						{__('Automatic retry with exponential backoff for transient sync errors', 'gregius-data')}
					</p>
				</div>
				{failed_permanently > 0 && (
					<Button
						variant="secondary"
						onClick={handleClearDeadLetter}
					>
						{__('Clear Dead Letter Queue', 'gregius-data')}
					</Button>
				)}
			</CardHeader>

			<CardBody>
				{/* Queue Metrics - 2 column grid */}
				<Grid columns={3} gap={4}>
					<div>
						<strong>{__('Pending Retries:', 'gregius-data')}</strong> <span className='components-badge is-info'>{pending_retries || 0}</span>
					</div>
					<div>
						<strong>{__('Failed Permanently:', 'gregius-data')}</strong> <span className='components-badge is-info'>{failed_permanently || 0}</span>
					</div>
					</Grid>

					<hr style={{ marginTop: '16px' }} />
	
				{/* Empty State or Queue Items Table */}
				{allItems.length > 0 ? (
					<div>
						<Heading level={4}>{__('Queue Items', 'gregius-data')}</Heading>
						<table>
							<thead>
								<tr>
									<th>{__('Operation', 'gregius-data')}</th>
									<th>{__('Entity ID', 'gregius-data')}</th>
									<th>{__('Attempt', 'gregius-data')}</th>
									<th>{__('Status', 'gregius-data')}</th>
									<th>{__('Last Error', 'gregius-data')}</th>
									<th style={{ width: '60px' }}>{__('Actions', 'gregius-data')}</th>
								</tr>
							</thead>
							<tbody>
								{allItems.map((item, index) => (
									<tr key={`${item.source}-${index}`}>
										<td>{item.operation_type}</td>
										<td>{item.entity_id}</td>
										<td>{item.attempt_count || 0}</td>
										<td>
											<span className={`components-badge is-${item.source === 'pending' ? 'info' : 'warning'}`}>
												{item.source === 'pending'
													? __('Pending', 'gregius-data')
													: __('Failed', 'gregius-data')}
											</span>
										</td>
										<td
											className="error-cell"
											title={item.last_error}
										>
											{item.last_error ? item.last_error.substring(0, 50) + '...' : '-'}
										</td>
										<td>
											{item.source === 'dead_letter' && (
												<DropdownMenu
													icon={moreVertical}
													label={__('Actions', 'gregius-data')}
												>
													{({ onClose }) => (
														<MenuItem
															onClick={() => {
																handleManualRetry(item.originalIndex);
																onClose();
															}}
														>
															{__('Retry', 'gregius-data')}
														</MenuItem>
													)}
												</DropdownMenu>
											)}
										</td>
									</tr>
								))}
							</tbody>
						</table>
					</div>
				) : (
					<Grid columns={1} gap={4}>
						<div>
							<p>{__('No retry queue items.', 'gregius-data')}</p>
						</div>
					</Grid>
				)}
			</CardBody>
		</Card>
	);
};

export default RetryQueueCard;
