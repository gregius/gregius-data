/**
 * Logs Page Component
 *
 * Dashboard page for viewing and managing plugin logs and interactions.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardBody,
	CardHeader,
	Button,
	Spinner,
	Flex,
	FlexItem,
	FlexBlock,
	SelectControl,
	TextControl,
	ToggleControl,
	DropdownMenu,
	__experimentalHeading as Heading,
	__experimentalText as Text,
} from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';

// Import the logs store
import '../stores/logs';

// Level badge class mappings
const LEVEL_BADGE_CLASSES = {
	debug: 'is-info',           // Default badge style
	info: 'is-success',  // Green
	warning: 'is-warning', // Yellow/Orange
	error: 'is-error',   // Red
	critical: 'is-error', // Red (critical uses same as error)
};

const LogsPage = () => {
	// Local state for filters
	const [ filterLevel, setFilterLevel ] = useState( '' );
	const [ filterComponent, setFilterComponent ] = useState( '' );
	const [ filterDateFrom, setFilterDateFrom ] = useState( '' );
	const [ filterDateTo, setFilterDateTo ] = useState( '' );
	const [ expandedLogId, setExpandedLogId ] = useState( null );

	// Get data from store
	const {
		logs,
		pagination,
		stats,
		settings,
		isLoading,
		error,
		autoRefresh,
	} = useSelect( ( select ) => ( {
		logs: select( 'gg-data/logs' ).getLogs(),
		pagination: select( 'gg-data/logs' ).getPagination(),
		stats: select( 'gg-data/logs' ).getStats(),
		settings: select( 'gg-data/logs' ).getSettings(),
		isLoading: select( 'gg-data/logs' ).isLoading(),
		error: select( 'gg-data/logs' ).getError(),
		autoRefresh: select( 'gg-data/logs' ).getAutoRefresh(),
	} ), [] );

	// Get connections for filter dropdown
	const { connections } = useSelect( ( select ) => ( {
		connections: select( 'gg-data/connections' ).getConnectionsList(),
	} ), [] );

	const [ filterConnection, setFilterConnection ] = useState( '' );

	// Store actions
	const { fetchLogs, fetchStats, fetchSettings, setAutoRefresh } = useDispatch( 'gg-data/logs' );

	// Build current filters object
	const getCurrentFilters = useCallback( () => ( {
		page: pagination?.page || 1,
		per_page: pagination?.perPage || 50,
		level: filterLevel || undefined,
		component: filterComponent || undefined,
		connection_id: filterConnection || undefined,
		date_from: filterDateFrom || undefined,
		date_to: filterDateTo || undefined,
	} ), [ pagination, filterLevel, filterComponent, filterConnection, filterDateFrom, filterDateTo ] );

	// Initial load
	useEffect( () => {
		fetchLogs();
		fetchStats();
		fetchSettings();
	}, [] );

	// Auto-refresh effect
	useEffect( () => {
		let interval;
		if ( autoRefresh ) {
			interval = setInterval( () => {
				fetchLogs( getCurrentFilters() );
				fetchStats(); // Update level counts in dropdown
			}, 5000 );
		}
		return () => {
			if ( interval ) {
				clearInterval( interval );
			}
		};
	}, [ autoRefresh, fetchLogs, getCurrentFilters ] );

	// Effect to fetch when filters change (instant filtering)
	useEffect( () => {
		fetchLogs( {
			...getCurrentFilters(),
			page: 1, // Reset to first page when filtering
		} );
		fetchStats();
	}, [ filterLevel, filterComponent, filterConnection, filterDateFrom, filterDateTo ] );

	// Pagination
	const handlePageChange = ( newPage ) => {
		fetchLogs( {
			...getCurrentFilters(),
			page: newPage,
		} );
	};

	// Export handlers
	const handleExportCsv = () => {
		const params = new URLSearchParams();
		params.append( 'format', 'csv' );
		if ( filterLevel ) params.append( 'level', filterLevel );
		if ( filterComponent ) params.append( 'component', filterComponent );
		if ( filterConnection ) params.append( 'connection_id', filterConnection );
		if ( filterDateFrom ) params.append( 'date_from', filterDateFrom );
		if ( filterDateTo ) params.append( 'date_to', filterDateTo );

		const restUrl = window.wpApiSettings?.root || '/wp-json/';
		const exportUrl = `${ restUrl }gg-data/v1/logs/export?${ params.toString() }&_wpnonce=${ window.wpApiSettings?.nonce || '' }`;
		window.open( exportUrl, '_blank' );
	};

	const handleExportJson = () => {
		const params = new URLSearchParams();
		params.append( 'format', 'json' );
		if ( filterLevel ) params.append( 'level', filterLevel );
		if ( filterComponent ) params.append( 'component', filterComponent );
		if ( filterConnection ) params.append( 'connection_id', filterConnection );
		if ( filterDateFrom ) params.append( 'date_from', filterDateFrom );
		if ( filterDateTo ) params.append( 'date_to', filterDateTo );

		const restUrl = window.wpApiSettings?.root || '/wp-json/';
		const exportUrl = `${ restUrl }gg-data/v1/logs/export?${ params.toString() }&_wpnonce=${ window.wpApiSettings?.nonce || '' }`;
		window.open( exportUrl, '_blank' );
	};

	// Level options for select with counts from stats
	const getLevelCount = ( level ) => {
		if ( ! stats?.by_level ) return 0;
		return stats.by_level[ level ] || 0;
	};

	const levelOptions = [
		{ label: `${ __( 'All Levels', 'gregius-data' ) } (${ stats?.total || 0 })`, value: '' },
		{ label: `${ __( 'Debug', 'gregius-data' ) } (${ getLevelCount( 'debug' ) })`, value: 'debug' },
		{ label: `${ __( 'Info', 'gregius-data' ) } (${ getLevelCount( 'info' ) })`, value: 'info' },
		{ label: `${ __( 'Warning', 'gregius-data' ) } (${ getLevelCount( 'warning' ) })`, value: 'warning' },
		{ label: `${ __( 'Error', 'gregius-data' ) } (${ getLevelCount( 'error' ) })`, value: 'error' },
		{ label: `${ __( 'Critical', 'gregius-data' ) } (${ getLevelCount( 'critical' ) })`, value: 'critical' },
	];

	// Component options for select
	const componentOptions = [
		{ label: __( 'All Components', 'gregius-data' ), value: '' },
		{ label: __( 'RAG', 'gregius-data' ), value: 'rag' },
		{ label: __( 'Search', 'gregius-data' ), value: 'search' },
		{ label: __( 'Sync', 'gregius-data' ), value: 'sync' },
		{ label: __( 'Vectors', 'gregius-data' ), value: 'vectors' },
		{ label: __( 'Connection', 'gregius-data' ), value: 'connection' },
		{ label: __( 'Model', 'gregius-data' ), value: 'model' },
		{ label: __( 'Cron', 'gregius-data' ), value: 'cron' },
		{ label: __( 'System', 'gregius-data' ), value: 'system' },
	];

	// Connection options for select
	const connectionOptions = [
		{ label: __( 'All Connections', 'gregius-data' ), value: '' },
		...( connections || [] ).map( ( conn ) => ( {
			label: conn.name,
			value: conn.name,
		} ) ),
	];

	// Format timestamp
	const formatTime = ( timestamp ) => {
		const date = new Date( timestamp );
		return date.toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit', second: '2-digit' } );
	};

	const formatDate = ( timestamp ) => {
		const date = new Date( timestamp );
		return date.toLocaleDateString();
	};

	return (
		<div className="gg-data-page gg-data-logs-page">
			<div style={ { display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 16, padding: '2rem 1.5rem 0', borderTop: '1px solid rgba(0, 0, 0, 0.1)' } }>
				<Heading level={ 2 }>{ __( 'Logs', 'gregius-data' ) }</Heading>
				<Flex gap={ 3 }>
					<FlexItem>
						<ToggleControl
							label={ __( 'Auto-refresh', 'gregius-data' ) }
							checked={ autoRefresh }
							onChange={ setAutoRefresh }
						/>
					</FlexItem>
					<FlexItem>
						<Button
							variant="secondary"
							onClick={ () => {
								fetchLogs( getCurrentFilters() );
								fetchStats();
							} }
							disabled={ isLoading }
							isBusy={ isLoading }
						>
							{ isLoading ? __( 'Refreshing...', 'gregius-data' ) : __( 'Refresh', 'gregius-data' ) }
						</Button>
					</FlexItem>
				</Flex>
			</div>

			<div style={ { display: 'flex', flexDirection: 'column', gap: '1.5rem', padding: '1.5rem' } }>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }

				{ /* Filters */ }
				<Card isRounded={false}>
					<CardHeader>
						<Heading level={ 4 }>{ __( 'Filters', 'gregius-data' ) }</Heading>
					</CardHeader>
					<CardBody>
						<Flex gap={ 4 } wrap align="flex-end">
							<FlexItem>
								<SelectControl
									label={ __( 'Level', 'gregius-data' ) }
									value={ filterLevel }
									options={ levelOptions }
									onChange={ setFilterLevel }
									__nextHasNoMarginBottom
								/>
							</FlexItem>
							<FlexItem>
								<SelectControl
									label={ __( 'Component', 'gregius-data' ) }
									value={ filterComponent }
									options={ componentOptions }
									onChange={ setFilterComponent }
									__nextHasNoMarginBottom
								/>
							</FlexItem>
							<FlexItem>
								<SelectControl
									label={ __( 'Connection', 'gregius-data' ) }
									value={ filterConnection }
									options={ connectionOptions }
									onChange={ setFilterConnection }
									__nextHasNoMarginBottom
								/>
							</FlexItem>
							<FlexItem>
								<TextControl
									label={ __( 'From Date', 'gregius-data' ) }
									type="date"
									value={ filterDateFrom }
									onChange={ setFilterDateFrom }
									__nextHasNoMarginBottom
								/>
							</FlexItem>
							<FlexItem>
								<TextControl
									label={ __( 'To Date', 'gregius-data' ) }
									type="date"
									value={ filterDateTo }
									onChange={ setFilterDateTo }
									__nextHasNoMarginBottom
								/>
							</FlexItem>
						</Flex>
					</CardBody>
				</Card>

				{ /* Logs Table */ }
				<Card isRounded={false}>
					<CardHeader>
						<Flex justify="space-between" align="center">
							<Heading level={ 4 }>
								{ __( 'Log Entries', 'gregius-data' ) }
								{ pagination && (
									<Text as="span" style={ { fontWeight: 'normal', marginLeft: 8 } }>
										({ pagination.totalItems } { __( 'total', 'gregius-data' ) })
									</Text>
								) }
							</Heading>
							<DropdownMenu
								icon={ moreVertical }
								label={ __( 'Log actions', 'gregius-data' ) }
								controls={ [
									{
										title: __( 'Export CSV', 'gregius-data' ),
										onClick: handleExportCsv,
									},
									{
										title: __( 'Export JSON', 'gregius-data' ),
										onClick: handleExportJson,
									},
								] }
							/>
						</Flex>
					</CardHeader>
					<CardBody>
						{ /* Loading state */ }
						{ isLoading && ! logs?.length ? (
							<Flex justify="center" style={ { padding: 40 } }>
								<Spinner />
							</Flex>
						) : /* Empty state */ ! logs?.length ? (
							<Flex justify="center" style={ { padding: 40 } }>
								<Text>{ __( 'No logs found.', 'gregius-data' ) }</Text>
							</Flex>
						) : (
							<>
								<table style={ { margin: 0 } }>
									<thead>
										<tr>
											<th style={ { width: 60 } }>{ __( 'Level', 'gregius-data' ) }</th>
											<th style={ { width: 100 } }>{ __( 'Component', 'gregius-data' ) }</th>
											<th style={ { width: 150 } }>{ __( 'Time', 'gregius-data' ) }</th>
											<th>{ __( 'Message', 'gregius-data' ) }</th>
											<th style={ { width: 120 } }>{ __( 'Connection', 'gregius-data' ) }</th>
										</tr>
									</thead>
									<tbody>
										{ logs.map( ( item ) => (
											<>
												<tr
													key={ item.id }
													onClick={ () => setExpandedLogId( expandedLogId === item.id ? null : item.id ) }
													style={ { cursor: item.context ? 'pointer' : 'default' } }
												>
													<td>
														<span
															className={ `components-badge ${ LEVEL_BADGE_CLASSES[ item.level ] || '' }` }
															style={ { textTransform: 'uppercase' } }
														>
															{ item.level }
														</span>
													</td>
													<td>
														<span className="components-badge is-info">
															{ item.component }
														</span>
													</td>
													<td>
														<Text size="small">
															{ formatDate( item.logged_at ) }
															<br />
															{ formatTime( item.logged_at ) }
														</Text>
													</td>
													<td>
														{ item.message }
														{ item.context && (
															<span style={ { marginLeft: 8, color: '#666' } }>
																{ expandedLogId === item.id ? '▼' : '▶' }
															</span>
														) }
													</td>
													<td>
														<Text size="small">{ item.connection_id || '-' }</Text>
													</td>
												</tr>
												{ /* Expanded row for context */ }
												{ expandedLogId === item.id && item.context && (
													<tr key={ `${ item.id }-context` }>
														<td colSpan={ 5 } style={ { backgroundColor: '#f6f7f7', padding: 16 } }>
															<pre style={ { margin: 0, whiteSpace: 'pre-wrap', fontSize: 12 } }>
																{ JSON.stringify( item.context, null, 2 ) }
															</pre>
														</td>
													</tr>
												) }
											</>
										) ) }
									</tbody>
								</table>

								{ /* Pagination */ }
								{ pagination && pagination.totalPages > 1 && (
									<Flex justify="space-between" align="center" style={ { padding: 16, borderTop: '1px solid #ddd' } }>
										<Text>
											{ __( 'Showing', 'gregius-data' ) } { ( ( pagination.page - 1 ) * pagination.perPage ) + 1 }-{ Math.min( pagination.page * pagination.perPage, pagination.totalItems ) } { __( 'of', 'gregius-data' ) } { pagination.totalItems }
										</Text>
										<Flex gap={ 2 }>
											<Button
												variant="secondary"
												size="small"
												disabled={ pagination.page <= 1 }
												onClick={ () => handlePageChange( pagination.page - 1 ) }
											>
												{ __( '← Previous', 'gregius-data' ) }
											</Button>
											<Text style={ { padding: '6px 12px' } }>
												{ pagination.page } / { pagination.totalPages }
											</Text>
											<Button
												variant="secondary"
												size="small"
												disabled={ pagination.page >= pagination.totalPages }
												onClick={ () => handlePageChange( pagination.page + 1 ) }
											>
												{ __( 'Next →', 'gregius-data' ) }
											</Button>
										</Flex>
									</Flex>
								) }
							</>
						) }
					</CardBody>
				</Card>
			</div>
		</div>
	);
};

export default LogsPage;
