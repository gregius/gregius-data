import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Notice, Spinner, Card, CardHeader, CardBody, DropdownMenu, __experimentalHeading as Heading } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { moreVertical } from '@wordpress/icons';
import { formatRelativeTime } from '../../utils/format-time';

/**
 * Vocabulary Integrity Card Component
 * 
 * Displays TF-IDF vocabulary cache integrity with drift detection.
 * Shows vocabulary version, post count, drift percentage, and status.
 * Provides prepare, validate, and clear vocabulary actions.
 * 
 * @since Phase 4
 */
const VocabularyIntegrityCard = ({ selectedConnectionId, vocabularyStatus, onVocabularyPrepared, fetchVocabularyStatus }) => {
	// Check if a connection is selected
	if (!selectedConnectionId) {
		return (
			<Notice status="warning" isDismissible={false}>
				{__('Please select a connection to begin.', 'gregius-data')}
			</Notice>
		);
	}

	const [preparing, setPreparing] = useState(false);
	const [clearing, setClearing] = useState(false);
	const [error, setError] = useState(null);
	const [actionNotice, setActionNotice] = useState(null); // { status: 'success'|'warning'|'error', message: '...' }

	/**
	 * Prepare vocabulary (build and cache)
	 */
	const handlePrepareVocabulary = async () => {
		try {
			setPreparing(true);
			setError(null);
			setActionNotice(null);
			
			const response = await apiFetch({
				path: `/gg-data/v1/vocabulary/prepare?connection_name=${selectedConnectionId}`,
			method: 'POST'
		});

		if (response.success) {
				// Show success notice
				setActionNotice({
					status: 'success',
					message: __(`Vocabulary prepared successfully. Version ${response.vocabulary.version}, ${response.vocabulary.unique_terms.toLocaleString()} unique terms from ${response.vocabulary.post_count.toLocaleString()} posts.`, 'gregius-data')
				});
				
				// Notify parent to refresh vocabulary status
				if (onVocabularyPrepared) {
					onVocabularyPrepared();
				}
			} else {
				setActionNotice({
					status: 'error',
					message: response.message || __('Failed to prepare vocabulary', 'gregius-data')
				});
			}
		} catch (err) {
			setError(err.message);
			setActionNotice({
				status: 'error',
				message: err.message || __('Error preparing vocabulary', 'gregius-data')
		});
	} finally {
		setPreparing(false);
	}
};	/**
	 * Clear vocabulary cache
	 */
	const handleClearVocabulary = async () => {
		// Confirm before clearing
		if (!window.confirm(__('Are you sure you want to clear the vocabulary cache? You will need to prepare vocabulary again before generating vectors.', 'gregius-data'))) {
			return;
		}

		try {
			setClearing(true);
			setError(null);
			setActionNotice(null);
			
			const response = await apiFetch({
				path: `/gg-data/v1/vocabulary/cache?connection_name=${selectedConnectionId}`,
			method: 'DELETE'
		});

		if (response.success) {
				setActionNotice({
					status: 'success',
					message: __(`Vocabulary cache cleared. Version ${response.cleared_version} removed.`, 'gregius-data')
				});

				// Notify parent to refresh vocabulary status
				if (onVocabularyPrepared) {
					onVocabularyPrepared();
				}
			} else {
				setActionNotice({
					status: 'error',
					message: response.message || __('Failed to clear vocabulary cache', 'gregius-data')
				});
			}
		} catch (err) {
			setError(err.message);
			setActionNotice({
				status: 'error',
				message: err.message || __('Error clearing vocabulary cache', 'gregius-data')
			});
		} finally {
			setClearing(false);
		}
	};

	// Loading state - show spinner until vocabularyStatus is fetched
	if (vocabularyStatus === null) {
		return (
			<div style={{ display: 'flex', alignItems: 'center', padding: '1rem' }}>
				<Spinner />
				<p>{__('Loading vector status...', 'gregius-data')}</p>
			</div>
		);
	}

	// Determine overall status for display
	const vocabularyExists = vocabularyStatus.exists;

	// Get overall status notice
	let overallNoticeStatus = 'success';
	let overallNoticeMessage = __('Vocabulary is healthy and ready for vector generation', 'gregius-data');

	if (!vocabularyExists) {
		overallNoticeStatus = 'warning';
		overallNoticeMessage = __('Vocabulary not prepared. Prepare vocabulary before generating vectors (8x performance improvement).', 'gregius-data');
	} else if (vocabularyStatus.status === 'error') {
		overallNoticeStatus = 'error';
		overallNoticeMessage = __('Critical vocabulary drift detected (>5%). Regenerate vocabulary before generating vectors.', 'gregius-data');
	} else if (vocabularyStatus.status === 'warning') {
		overallNoticeStatus = 'warning';
		overallNoticeMessage = __('Minor vocabulary drift detected (2-5%). Consider regenerating vocabulary for optimal accuracy.', 'gregius-data');
	}

	return (
		<Card isRounded={false}>
			<CardHeader>
				<Heading level={3} style={{ margin: 0 }}>{__('Vocabulary Integrity', 'gregius-data')}</Heading>
			</CardHeader>
			<CardBody>
				{/* Last prepared timestamp */}
				<p>
					<strong>{__('Last prepared: ', 'gregius-data')}</strong>
					<span className='components-badge is-info'>{vocabularyExists ? formatRelativeTime(vocabularyStatus.generated_at) : __('Never', 'gregius-data')}</span>
				</p>

				{/* Action notice */}
				{actionNotice && (
					<Notice
						status={actionNotice.status}
						isDismissible={true}
						onRemove={() => setActionNotice(null)}
					>
						{actionNotice.message}
					</Notice>
				)}

				{/* Overall status */}
				<Notice
					status={overallNoticeStatus}
					isDismissible={false}
					className="vocabulary-status-notice"
				>
					{overallNoticeMessage}
				</Notice>

				{/* Vocabulary status table */}
				<div style={{ overflowX: 'auto' }}>
					<table className="sync-validation-table">
						<thead>
							<tr>
								<th>{__('Metric', 'gregius-data')}</th>
								<th>{__('Cached', 'gregius-data')}</th>
								<th>{__('Current', 'gregius-data')}</th>
								<th>{__('Drift', 'gregius-data')}</th>
								<th>{__('Status', 'gregius-data')}</th>
								<th>{__('Actions', 'gregius-data')}</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>{__('Version', 'gregius-data')}</td>
								<td>{vocabularyExists ? vocabularyStatus.version : '-'}</td>
								<td>-</td>
								<td>-</td>
								<td>-</td>
								<td></td>
							</tr>
							<tr>
								<td>{__('Post Count', 'gregius-data')}</td>
								<td>{vocabularyExists ? (vocabularyStatus.cached_post_count?.toLocaleString() || 0) : '-'}</td>
								<td>{vocabularyExists ? (vocabularyStatus.current_post_count?.toLocaleString() || 0) : '-'}</td>
								<td className={vocabularyExists && vocabularyStatus.posts_added > 0 ? 'has-drift' : ''}>
									{vocabularyExists ? (
										<>
											{vocabularyStatus.posts_added >= 0 ? `+${vocabularyStatus.posts_added}` : vocabularyStatus.posts_added}
											{' ('}
											{vocabularyStatus.drift_percentage?.toFixed(2) || '0.00'}
											{'%)'}
										</>
									) : '-'}
								</td>
								<td>
									{vocabularyExists ? (
										<span className={`components-badge is-${vocabularyStatus.status === 'success' ? 'success' : (vocabularyStatus.status === 'warning' ? 'warning' : 'error')}`}>
											{vocabularyStatus.status.charAt(0).toUpperCase() + vocabularyStatus.status.slice(1).toLowerCase()}
										</span>
									) : '-'}
								</td>
								<td>
									<DropdownMenu
										icon={moreVertical}
										label={__('Actions', 'gregius-data')}
										controls={[
											{
												title: preparing ? __('Preparing...', 'gregius-data') : (vocabularyExists ? __('Regenerate Vocabulary', 'gregius-data') : __('Prepare Vocabulary', 'gregius-data')),
												onClick: handlePrepareVocabulary,
												isDisabled: preparing,
											},
										]}
									/>
								</td>
							</tr>
							<tr>
								<td>{__('Unique Terms', 'gregius-data')}</td>
								<td>{vocabularyExists ? (vocabularyStatus.unique_terms?.toLocaleString() || 0) : '-'}</td>
								<td>-</td>
								<td>-</td>
								<td>-</td>
								<td></td>
							</tr>
						</tbody>
					</table>
				</div>

				{/* Error notice */}
				{error && (
					<Notice
						status="error"
						isDismissible={true}
						onRemove={() => setError(null)}
					>
						{error}
					</Notice>
				)}
			</CardBody>
		</Card>
	);
};

export default VocabularyIntegrityCard;
