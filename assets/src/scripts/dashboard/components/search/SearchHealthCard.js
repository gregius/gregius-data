/**
 * Search Health Card Component
 *
 * Displays PostgreSQL search health status with auto-refresh.
 * Loads connection from global search settings.
 *
 * @since 2.0.0
 */

import { Card, CardHeader, CardBody, Button, Notice, Spinner, DropdownMenu, __experimentalGrid as Grid, __experimentalHeading as Heading } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { moreVertical } from '@wordpress/icons';
import { formatRelativeTime } from '../../utils/format-time';

// Global settings connection constant (must match PHP GG_DATA_SEARCH_SETTINGS_CONNECTION)
const SEARCH_SETTINGS_CONNECTION = '__global__';

const SearchHealthCard = () => {
	const [searchConnection, setSearchConnection] = useState(null);
	const [health, setHealth] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [lastUpdate, setLastUpdate] = useState(null);
	const [healthCheckNotice, setHealthCheckNotice] = useState(null); // { status: 'success'|'error', message: '...' }

	// Load search connection from global settings
	const loadSearchConnection = async () => {
		try {
			const response = await apiFetch({
				path: `/gg-data/v1/settings/search/connection?connection=${SEARCH_SETTINGS_CONNECTION}`,
			});

			if (response && response.value) {
				setSearchConnection(response.value);
			} else {
				setSearchConnection(null);
			}
		} catch (err) {
			// Silently fail - will show empty state
			console.error('Failed to load search connection:', err);
		} finally {
			setLoading(false);
		}
	};

	// Initial load
	useEffect(() => {
		loadSearchConnection();
	}, []);

	// Poll for settings changes every 5 seconds (in case user changes connection in settings card)
	useEffect(() => {
		const interval = setInterval(() => {
			loadSearchConnection();
		}, 5000);

		return () => clearInterval(interval);
	}, []);

	// Fetch health status
	const fetchHealth = async () => {
		if (!searchConnection) return;

		try {
			setLoading(true);
			const response = await apiFetch({
				path: `/gg-data/v1/search/health?connection=${searchConnection}`,
			});

			if (response.success) {
				setHealth(response.data);
				setError(null);
				setLastUpdate(new Date());
			}
		} catch (err) {
			setError(err.message);
		} finally {
			setLoading(false);
		}
	};

	// Initial load and when connection changes
	useEffect(() => {
		if (searchConnection) {
			fetchHealth();
		} else {
			setHealth(null);
			setError(null);
		}
	}, [searchConnection]);

	// Auto-refresh every 30 seconds (only when connected)
	useEffect(() => {
		if (!searchConnection) return;

		const interval = setInterval(() => {
			fetchHealth();
		}, 30000);

		return () => clearInterval(interval);
	}, [searchConnection]);

	// Manual health check
	const runHealthCheck = async () => {
		if (!searchConnection) return;

		setHealthCheckNotice(null);
		const startTime = Date.now();

		try {
			setLoading(true);
			const response = await apiFetch({
				path: `/gg-data/v1/search/health/check?connection=${searchConnection}`,
				method: 'POST',
			});

			if (response.success) {
				// Refresh health status after check
				await fetchHealth();

				const duration = Date.now() - startTime;

				// Get health data for message
				const healthData = response.data || health;
				const successRate = healthData?.success_rate || 100;
				const lastLatency = healthData?.last_latency_ms || duration;

				// Determine message based on health status
				let message;
				if (successRate === 100) {
					message = __(`Search health check completed. All systems operational (${lastLatency}ms)`, 'gregius-data');
				} else {
					message = __(`Search health check completed. Success rate: ${successRate}% (${lastLatency}ms)`, 'gregius-data');
				}

				setHealthCheckNotice({
					status: successRate >= 95 ? 'success' : 'warning',
					message: message
				});
			}
		} catch (err) {
			const errorMessage = err.message || __('Health check failed', 'gregius-data');
			setError(errorMessage);
			setHealthCheckNotice({
				status: 'error',
				message: errorMessage
			});
			setLoading(false);
		}
	};

	// Reset health metrics
	const resetHealth = async () => {
		if (!searchConnection) return;

		if (!confirm('Reset all search health metrics? This cannot be undone.')) {
			return;
		}

		try {
			setLoading(true);
			await apiFetch({
				path: `/gg-data/v1/search/health/reset?connection=${searchConnection}`,
				method: 'POST',
			});

			// Refresh after reset
			fetchHealth();
		} catch (err) {
			setError(err.message);
			setLoading(false);
		}
	};

	// Get status badge color
	const getStatusBadgeClass = (status) => {
		switch (status) {
			case 'active':
				return 'gg-badge gg-badge-success';
			case 'degraded':
				return 'gg-badge gg-badge-warning';
			case 'critical':
				return 'gg-badge gg-badge-error';
			default:
				return 'gg-badge gg-badge-neutral';
		}
	};

	// Get status label (only show if degraded or critical)
	const getStatusLabel = (status) => {
		switch (status) {
			case 'degraded':
				return 'Degraded (Some Failures)';
			case 'critical':
				return 'Critical (MySQL Fallback)';
			default:
				return null; // Don't show status when everything is working
		}
	};

	// Format relative time
	if (loading && !health) {
		return (
			<Card isRounded={false} className="gg-search-health-card">
				<CardHeader>
					<Heading level={3}>{__('Search Health', 'gregius-data')}</Heading>
				</CardHeader>
				<CardBody>
					<div className="gg-search-health-loading" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
						<Spinner />
						<p style={{ margin: 0 }}>{__('Loading search health...', 'gregius-data')}</p>
					</div>
				</CardBody>
			</Card>
		);
	}

	// No connection selected - show empty state
	if (!searchConnection || !health) {
		return (
			<Card isRounded={false} className="gg-search-health-card">
				<CardHeader>
					<Heading level={3}>{__('Search Health', 'gregius-data')}</Heading>
				</CardHeader>
				<CardBody>
					<Grid columns={3} gap={4}>
						<div>
							<strong>Success Rate:</strong> <span className='components-badge'>—</span>
						</div>
						<div>
							<strong>Total Searches:</strong> <span className='components-badge'>—</span>
						</div>
						<div>
							<strong>Consecutive Failures:</strong> <span className='components-badge'>—</span>
						</div>
						<div>
							<strong>Last Latency:</strong> <span className='components-badge'>—</span>
						</div>
						<div>
							<strong>Last Health Check:</strong> <span className='components-badge'>—</span>
						</div>
						<div>
							<strong>Last Successful Search:</strong> <span className='components-badge'>—</span>
						</div>
					</Grid>
					{!searchConnection && (
						<p style={{ marginTop: '16px', color: '#757575' }}>
							{__('Enable search and select a connection to view health metrics.', 'gregius-data')}
						</p>
					)}
				</CardBody>
			</Card>
		);
	}

	if (error && !health) {
		return (
			<div className="gg-search-health-card gg-search-health-error">
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			</div>
		);
	}

	return (
		<Card isRounded={false} className="gg-search-health-card">
			<CardHeader>
				<div style={{
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					width: '100%'
				}}>
					<div>
						<Heading level={3} style={{ margin: 0 }}>{__('Search Health', 'gregius-data')}</Heading>
					</div>
					<DropdownMenu
						icon={moreVertical}
						label={__('Search health actions', 'gregius-data')}
						controls={[
							{
								title: loading ? __('Running...', 'gregius-data') : __('Run Health Check', 'gregius-data'),
								onClick: runHealthCheck,
								isDisabled: loading,
							},
							{
								title: __('Reset Health', 'gregius-data'),
								onClick: resetHealth,
								isDisabled: loading,
								className: 'has-text-color has-vivid-red-color',
							},
						]}
					/>
				</div>
			</CardHeader>
			<CardBody>
				{/* Health check action notice */}
				{healthCheckNotice && (
					<Notice
						status={healthCheckNotice.status}
						isDismissible={true}
						onRemove={() => setHealthCheckNotice(null)}
					>
						{healthCheckNotice.message}
					</Notice>
				)}

				{/* Status Badge - Only show if degraded or critical */}
				{health.status !== 'active' && (
					<div className="gg-health-status">
						<span className={getStatusBadgeClass(health.status)}>
							{getStatusLabel(health.status)}
						</span>
					</div>
				)}

				{/* Health Metrics - 3 column grid */}
				<Grid columns={3} gap={4}>
					<div>
						<strong>Success Rate:</strong> <span className='components-badge is-info'>{health.success_rate}%</span>
					</div>

					<div>
						<strong>Total Searches:</strong> <span className='components-badge is-info'>{health.total_searches}</span>
					</div>

					<div>
						<strong>Consecutive Failures:</strong> <span className={`components-badge ${health.consecutive_failures > 0 ? 'is-warning' : 'is-info'}`}>{health.consecutive_failures}</span>
					</div>

					{health.last_latency_ms !== null && health.last_latency_ms !== undefined && (
						<div>
							<strong>Last Latency:</strong> <span className='components-badge is-info'>{health.last_latency_ms}ms</span>
						</div>
					)}

					{lastUpdate && (
						<div>
							<strong>Last Health Check:</strong> <span className='components-badge is-info'>{formatRelativeTime(lastUpdate.toISOString())}</span>
						</div>
					)}

					{health.last_success && (
						<div>
							<strong>Last Successful Search:</strong> <span className='components-badge is-info'>{formatRelativeTime(health.last_success)}</span>
						</div>
					)}
				</Grid>

				{/* Last Error */}
				{health.last_error && (
					<div className="gg-health-detail gg-error">
						<strong>Last Error: </strong>
						{health.last_error}
						<br />
						<small>{formatRelativeTime(health.last_error_time)}</small>
					</div>
				)}

				{/* Recent Errors */}
				{health.recent_errors && health.recent_errors.length > 0 && (
					<details className="gg-recent-errors">
						<summary>Recent Errors ({health.recent_errors.length})</summary>
						<ul>
							{health.recent_errors.map((error, index) => (
								<li key={index}>
									<strong>{error.timestamp}: </strong>
									{error.message}
								</li>
							))}
						</ul>
					</details>
				)}
			</CardBody>
		</Card>
	);
};

export default SearchHealthCard;
