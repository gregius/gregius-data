/**
 * Content Sync Table Component
 * 
 * Unified interface for managing content synchronization.
 * Combines configuration (toggles) and validation (counts/drift) into a single view.
 */

import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { 
    Card, 
    CardHeader, 
    CardBody, 
    Button, 
    Spinner,
    ToggleControl,
    DropdownMenu,
    MenuItem,
    Modal,
    Notice,
    __experimentalHeading as Heading
} from '@wordpress/components';
import { moreVertical, check, warning, close } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

const ContentSyncTable = ({ selectedConnectionId, refreshTrigger, onPostTypeChange }) => {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [postTypeLabels, setPostTypeLabels] = useState({});
    const [config, setConfig] = useState(null);
    
    // Operation states
    const [modalState, setModalState] = useState(null); // { type: 'sync'|'clean'|'remove', postType, label }
    const [operationState, setOperationState] = useState({
        status: 'idle',
        result: null
    });
    const [abortController, setAbortController] = useState(null);

    // Full data fetch on connection change
    useEffect(() => {
        if (selectedConnectionId) {
            fetchData();
        }
    }, [selectedConnectionId]);

    // Refresh validation counts when config changes (but don't remount)
    useEffect(() => {
        if (selectedConnectionId && refreshTrigger > 0) {
            refreshValidationData();
        }
    }, [refreshTrigger]);

    const fetchData = async () => {
        try {
            setLoading(true);
            // Fetch all post types, configuration, and validation data in parallel
            const [postTypesRes, configRes, validationRes] = await Promise.all([
                apiFetch({ path: `/gg-data/v1/sync/post-types?connection=${selectedConnectionId}` }),
                apiFetch({ path: `/gg-data/v1/sync/configuration?connection=${selectedConnectionId}` }),
                apiFetch({ path: `/gg-data/v1/sync/validation/fast?connection=${selectedConnectionId}` })
            ]);

            const allPostTypes = postTypesRes.post_types || [];
            const enabledTypes = configRes.configuration?.enabled_post_types || [];
            const validationData = validationRes.data?.posts || {};

            setConfig(configRes.configuration);

            // Store labels for use in modal/display
            const labels = {};
            allPostTypes.forEach(type => labels[type.name] = type.label);
            setPostTypeLabels(labels);

            // Map ALL available post types to table rows
            const mergedData = allPostTypes.map(type => ({
                name: type.name,
                label: type.label,
                enabled: enabledTypes.includes(type.name),
                validation: validationData[type.name] || { 
                    wordpress_count: 0, 
                    postgresql_count: 0, 
                    drift: 0, 
                    status: 'unknown' 
                }
            }));

            setData(mergedData);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    const refreshValidationData = async () => {
        try {
            // Only refresh validation data, keep existing post type toggles
            const validationRes = await apiFetch({ 
                path: `/gg-data/v1/sync/validation/fast?connection=${selectedConnectionId}` 
            });

            const validationData = validationRes.data?.posts || {};

            // Update only validation data in existing rows
            setData(prevData => prevData.map(type => ({
                ...type,
                validation: validationData[type.name] || { 
                    wordpress_count: 0, 
                    postgresql_count: 0, 
                    drift: 0, 
                    status: 'unknown' 
                }
            })));
        } catch (err) {
            // Silently fail validation refresh, don't disrupt user
            console.error('Failed to refresh validation data:', err);
        }
    };

    const handleToggleSync = async (postType, checked) => {
        // Optimistic update
        const oldData = data;
        const newData = data.map(item => 
            item.name === postType ? { ...item, enabled: checked } : item
        );
        setData(newData);

        try {
            const enabledTypes = newData.filter(i => i.enabled).map(i => i.name);
            
            // Fetch latest config to avoid overwriting other components' changes
            const latestConfigRes = await apiFetch({
                path: `/gg-data/v1/sync/configuration?connection=${selectedConnectionId}`
            });
            
            // Preserve ALL config settings from latest fetch
            const updatePayload = {
                ...latestConfigRes.configuration,
                enabled_post_types: enabledTypes
            };
            
            const response = await apiFetch({
                path: `/gg-data/v1/sync/configuration?connection=${selectedConnectionId}`,
                method: 'POST',
                data: updatePayload
            });
            
            if (response.success) {
                // Use the returned configuration to ensure state matches what was actually saved
                setConfig(response.configuration || {});
                
                // Update the data with the actual saved configuration
                const actualEnabledTypes = response.configuration?.enabled_post_types || enabledTypes;
                setData(prevData => prevData.map(item => ({
                    ...item,
                    enabled: actualEnabledTypes.includes(item.name)
                })));
                
                // Notify parent that post types changed (will trigger remount)
                if (onPostTypeChange) {
                    onPostTypeChange();
                }
            } else {
                throw new Error(response.message || 'Update failed');
            }
        } catch (err) {
            setError(__('Failed to update configuration: ', 'gregius-data') + err.message);
            // Revert on error
            setData(oldData);
            fetchData();
        }
    };

    const handleBatchOperation = async (type, postType) => {
        let endpoint;
        let method = 'POST';
        
        if (type === 'sync') {
            endpoint = `/gg-data/v1/sync/batch-sync-post-type/${postType}`;
        } else if (type === 'clean') {
            endpoint = `/gg-data/v1/sync/post-type/${postType}/clean`;
        } else if (type === 'remove') {
            endpoint = `/gg-data/v1/sync/post-type/${postType}/orphans?connection=${selectedConnectionId}&batch_size=100`;
            method = 'DELETE';
        }

        const startTime = Date.now();
        const controller = new AbortController();
        setAbortController(controller);

        // Get original total for progress from modalState.data (already contains validation)
        // Fallback to searching in data array if needed
        const validationData = modalState?.data || data.find(d => d.name === postType)?.validation || {};
        const originalTotal = type === 'remove' 
            ? Math.max(0, (validationData.postgresql_count || 0) - (validationData.wordpress_count || 0)) // Approx orphans
            : (validationData.wordpress_count || 0);

        setOperationState({
            status: 'processing',
            type: type,
            rowType: postType,
            data: modalState.data,
            result: { processed: 0, total: originalTotal || 0, duration: 0, skipped: 0, failed: 0 }
        });

        try {
            let hasMore = true;
            let totalProcessed = 0;
            let totalSkipped = 0;
            let totalFailed = 0;
            let batchCount = 0;
            let currentOffset = 0;

            while (hasMore && !controller.signal.aborted) {
                // For remove operations, we need to append the offset to the URL
                const currentEndpoint = type === 'remove' 
                    ? `${endpoint}&offset=${currentOffset}`
                    : endpoint;

                const response = await apiFetch({
                    path: currentEndpoint,
                    method: method,
                    data: method === 'POST' ? {
                        connection_name: selectedConnectionId,
                        batch_size: 100,
                        offset: batchCount * 100
                    } : undefined,
                    signal: controller.signal
                });

                if (response.success) {
                    const batchProcessed = response.batch?.processed || response.processed || response.deleted || 0;
                    const batchSkipped = response.batch?.skipped || 0;
                    const batchFailed = response.batch?.failed || 0;
                    const batchDeleted = response.deleted || 0;
                    const batchChecked = response.processed || 0;

                    totalProcessed += batchProcessed;
                    totalSkipped += batchSkipped;
                    totalFailed += batchFailed;
                    batchCount++;

                    // Update offset for remove operations: skip the records we kept (checked - deleted)
                    if (type === 'remove') {
                        currentOffset += (batchChecked - batchDeleted);
                    }

                    setOperationState(prev => ({
                        ...prev,
                        result: {
                            ...prev.result,
                            processed: totalProcessed,
                            skipped: totalSkipped,
                            failed: totalFailed,
                            duration: (Date.now() - startTime) / 1000
                        }
                    }));

                    hasMore = response.batch?.has_more || response.has_more;
                    if (hasMore) await new Promise(r => setTimeout(r, 100));
                } else {
                    throw new Error(response.message || 'Operation failed');
                }
            }

            if (controller.signal.aborted) {
                setOperationState(prev => ({ 
                    ...prev, 
                    status: 'stopped',
                    result: {
                        ...prev.result,
                        duration: (Date.now() - startTime) / 1000
                    }
                }));
            } else {
                setOperationState(prev => ({ 
                    ...prev, 
                    status: 'completed',
                    result: {
                        ...prev.result,
                        processed: totalProcessed,
                        skipped: totalSkipped,
                        failed: totalFailed,
                        duration: (Date.now() - startTime) / 1000
                    }
                }));
                fetchData(); // Refresh table data
            }
        } catch (err) {
            if (err.name === 'AbortError') {
                setOperationState(prev => ({ 
                    ...prev, 
                    status: 'stopped',
                    result: {
                        ...prev.result,
                        duration: (Date.now() - startTime) / 1000
                    }
                }));
            } else {
                setError(err.message);
                setModalState(null);
            }
        } finally {
            setAbortController(null);
        }
    };

    const getStatusBadge = (status, drift) => {
        let color = 'green';
        let label = status;
        
        if (status === 'error' || Math.abs(drift) > 5) {
            color = 'red';
            label = 'Critical';
        } else if (status === 'warning' || Math.abs(drift) > 0) {
            color = 'orange';
            label = 'Warning';
        } else if (status === 'healthy') {
            color = 'green';
            label = 'Healthy';
        }

        return (
            <span className={`components-badge is-${status === 'healthy' ? 'success' : status === 'warning' ? 'warning' : 'error'}`}>
                {label}
            </span>
        );
    };

    if (!selectedConnectionId) return null;

    return (
        <>
            <Card isRounded={false} className="gg-content-sync-table">
                <CardHeader>
                    <Heading level={3}>{__('Types', 'gregius-data')}</Heading>
                </CardHeader>
                <CardBody>
                    {loading ? (
                        <Spinner />
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table>
                                <thead>
                                    <tr>
                                        <th>{__('Post Type', 'gregius-data')}</th>
                                        <th style={{ width: '100px' }}>{__('Sync', 'gregius-data')}</th>
                                        <th>{__('WordPress', 'gregius-data')}</th>
                                        <th>{__('PostgreSQL', 'gregius-data')}</th>
                                        <th>{__('Drift', 'gregius-data')}</th>
                                        <th>{__('Status', 'gregius-data')}</th>
                                        <th style={{ width: '60px' }}>{__('Actions', 'gregius-data')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.map(row => (
                                        <tr key={row.name}>
                                            <td>
                                                <strong>{row.label || row.name} </strong>
                                            </td>
                                            <td>
                                                <ToggleControl
                                                    checked={row.enabled}
                                                    onChange={(checked) => handleToggleSync(row.name, checked)}
                                                    __nextHasNoMarginBottom
                                                />
                                            </td>
                                            <td>{row.validation.wordpress_count?.toLocaleString()}</td>
                                            <td>{row.validation.postgresql_count?.toLocaleString()}</td>
                                            <td>
                                                {row.validation.drift_percentage !== undefined ? (
                                                    <span className={`components-badge is-${row.validation.drift_percentage === 0 ? 'success' : Math.abs(row.validation.drift_percentage) > 10 ? 'error' : 'warning'}`}>
                                                        {row.validation.drift_percentage > 0 ? '+' : ''}{Number(row.validation.drift_percentage).toFixed(1)}%
                                                    </span>
                                                ) : (
                                                    <span className="components-badge is-success">0.0%</span>
                                                )}
                                            </td>
                                            <td>
                                                {row.enabled 
                                                    ? getStatusBadge(row.validation.status, row.validation.drift_percentage)
                                                    : <span className="components-badge">Disabled</span>
                                                }
                                            </td>
                                            <td>
                                                <DropdownMenu
                                                    icon={moreVertical}
                                                    label={__('Actions', 'gregius-data')}
                                                >
                                                    { ( { onClose } ) => (
                                                        <>
                                                            <MenuItem
                                                                onClick={ () => {
                                                                    setModalState({ 
                                                                        type: 'sync', 
                                                                        postType: row.name, 
                                                                        label: row.label || row.name,
                                                                        data: row.validation 
                                                                    });
                                                                    onClose();
                                                                } }
                                                                disabled={ !row.enabled }
                                                            >
                                                                { sprintf( __('Sync %s', 'gregius-data'), row.label || row.name ) }
                                                            </MenuItem>
                                                            <MenuItem
                                                                onClick={ () => {
                                                                    setModalState({ 
                                                                        type: 'remove', 
                                                                        postType: row.name, 
                                                                        label: row.label || row.name,
                                                                        data: row.validation 
                                                                    });
                                                                    onClose();
                                                                } }
                                                                className="has-text-color has-vivid-red-color"
                                                                disabled={ !row.validation.postgresql_count || row.validation.postgresql_count === 0 }
                                                            >
                                                                { __('Remove Orphans', 'gregius-data') }
                                                            </MenuItem>
                                                        </>
                                                    ) }
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    ))}
                                    {data.length === 0 && (
                                        <tr>
                                            <td colSpan="7">{__('No post types found.', 'gregius-data')}</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                    
                    {error && (
                        <Notice status="error" onRemove={() => setError(null)} style={{ marginTop: '1rem' }}>
                            {error}
                        </Notice>
                    )}
                </CardBody>
            </Card>

            {modalState && (
                <Modal
                    title={sprintf(
                        modalState.type === 'sync' ? __('Sync %s', 'gregius-data') : 
                        modalState.type === 'clean' ? __('Clean %s', 'gregius-data') : 
                        __('Remove %s Orphans', 'gregius-data'),
                        modalState.label
                    )}
                    onRequestClose={() => {
                        if (
                            operationState.status === 'idle' ||
                            operationState.status === 'completed' ||
                            operationState.status === 'stopped'
                        ) {
                            setModalState(null);
                            setOperationState({
                                status: 'idle',
                                type: null,
                                rowType: null,
                                data: null,
                                result: null
                            });
                        }
                    }}
                    shouldCloseOnClickOutside={
                        operationState.status === 'idle' ||
                        operationState.status === 'completed' ||
                        operationState.status === 'stopped'
                    }
                    isDismissible={
                        operationState.status === 'idle' ||
                        operationState.status === 'completed' ||
                        operationState.status === 'stopped'
                    }
                >
                    {/* Idle State: Confirmation */}
                    {operationState.status === 'idle' && (
                        <>
                            <p>
                                {modalState.type === 'sync'
                                    ? sprintf(
                                        __('This will process %d %s.', 'gregius-data'),
                                        modalState.data?.wordpress_count || 0,
                                        modalState.label.toLowerCase()
                                    )
                                    : modalState.type === 'clean'
                                        ? sprintf(
                                            __('This will clean %d %s by removing markup.', 'gregius-data'),
                                            modalState.data?.wordpress_count || 0,
                                            modalState.label.toLowerCase()
                                        )
                                        : sprintf(
                                            __('This will permanently delete %s that exist in PostgreSQL but not in WordPress. This operation cannot be undone.', 'gregius-data'),
                                            modalState.label.toLowerCase()
                                        )}
                            </p>
                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'flex-start',
                                    gap: '12px',
                                    marginTop: '20px',
                                }}
                            >
                                <Button
                                    variant="primary"
                                    isDestructive={modalState.type === 'remove'}
                                    onClick={() => handleBatchOperation(modalState.type, modalState.postType)}
                                >
                                    {modalState.type === 'sync' ? __('Sync', 'gregius-data') : modalState.type === 'clean' ? __('Clean', 'gregius-data') : __('Remove', 'gregius-data')}
                                </Button>
                                <Button
                                    variant="link"
                                    onClick={() => {
                                        setModalState(null);
                                        setOperationState({
                                            status: 'idle',
                                            type: null,
                                            rowType: null,
                                            data: null,
                                            result: null
                                        });
                                    }}
                                >
                                    {__('Cancel', 'gregius-data')}
                                </Button>
                            </div>
                        </>
                    )}

                    {/* Processing State: Progress */}
                    {operationState.status === 'processing' && (
                        <>
                            <p>
                                {sprintf(
                                    __('Processed %d of %d records...', 'gregius-data'),
                                    (operationState.result?.processed || 0) + (operationState.result?.skipped || 0),
                                    operationState.result?.total || 0
                                )}
                            </p>
                            <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-start', marginTop: '16px' }}>
                                <Button variant="secondary" isBusy disabled>
                                    {modalState.type === 'sync' ? __('Syncing...', 'gregius-data') : modalState.type === 'clean' ? __('Cleaning...', 'gregius-data') : __('Removing...', 'gregius-data')}
                                </Button>
                                <Button 
                                    variant="link" 
                                    onClick={() => {
                                        if (abortController) {
                                            abortController.abort();
                                            setOperationState(prev => ({ ...prev, status: 'stopping' }));
                                        }
                                    }}
                                >
                                    {__('Stop', 'gregius-data')}
                                </Button>
                            </div>
                        </>
                    )}

                    {/* Stopping State */}
                    {operationState.status === 'stopping' && (
                        <>
                            <p>{__('Stopping operation...', 'gregius-data')}</p>
                            <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-start', marginTop: '16px' }}>
                                <Button variant="secondary" disabled>
                                    {__('Stopping...', 'gregius-data')}
                                </Button>
                            </div>
                        </>
                    )}

                    {/* Stopped State: Partial Results */}
                    {operationState.status === 'stopped' && (
                        <>
                            <Notice status="warning" isDismissible={false}>
                                {__('Operation stopped', 'gregius-data')}
                            </Notice>
                            <p>
                                {sprintf(
                                    __('Processed %d of %d records (%s).', 'gregius-data'),
                                    operationState.result?.processed || 0,
                                    operationState.result?.total || 0,
                                    typeof operationState.result?.duration === 'number'
                                        ? operationState.result.duration.toFixed(2) + 's'
                                        : operationState.result?.duration || '0s'
                                )}
                            </p>
                            <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-start', marginTop: '16px' }}>
                                <Button
                                    variant="link"
                                    onClick={() => {
                                        setModalState(null);
                                        setOperationState({
                                            status: 'idle',
                                            type: null,
                                            rowType: null,
                                            data: null,
                                            result: null
                                        });
                                    }}
                                >
                                    {__('Close', 'gregius-data')}
                                </Button>
                            </div>
                        </>
                    )}

                    {/* Completed State: Success */}
                    {operationState.status === 'completed' && (
                        <>
                            <Notice status="success" isDismissible={false}>
                                {__('Operation completed successfully', 'gregius-data')}
                            </Notice>
                            <p>
                                {modalState.type === 'sync'
                                    ? operationState.result?.skipped && operationState.result.skipped > 0
                                        ? sprintf(
                                            __('%d records synchronized, %d skipped (%s).', 'gregius-data'),
                                            operationState.result.processed || 0,
                                            operationState.result.skipped,
                                            typeof operationState.result?.duration === 'number'
                                                ? operationState.result.duration.toFixed(2) + 's'
                                                : operationState.result?.duration || '0s'
                                        )
                                        : sprintf(
                                            __('%d records synchronized (%s).', 'gregius-data'),
                                            operationState.result?.processed || 0,
                                            typeof operationState.result?.duration === 'number'
                                                ? operationState.result.duration.toFixed(2) + 's'
                                                : operationState.result?.duration || '0s'
                                        )
                                    : modalState.type === 'clean'
                                        ? operationState.result?.skipped && operationState.result.skipped > 0
                                            ? sprintf(
                                                __('%d records cleaned, %d skipped (%s).', 'gregius-data'),
                                                operationState.result.processed || 0,
                                                operationState.result.skipped,
                                                typeof operationState.result?.duration === 'number'
                                                    ? operationState.result.duration.toFixed(2) + 's'
                                                    : operationState.result?.duration || '0s'
                                            )
                                            : sprintf(
                                                __('%d records cleaned (%s).', 'gregius-data'),
                                                operationState.result?.processed || 0,
                                                typeof operationState.result?.duration === 'number'
                                                    ? operationState.result.duration.toFixed(2) + 's'
                                                    : operationState.result?.duration || '0s'
                                            )
                                        : sprintf(
                                            __('%d orphan %s deleted (%s).', 'gregius-data'),
                                            operationState.result?.deleted || operationState.result?.processed || 0,
                                            modalState.label.toLowerCase(),
                                            typeof operationState.result?.duration === 'number'
                                                ? operationState.result.duration.toFixed(2) + 's'
                                                : operationState.result?.duration || '0s'
                                        )}
                            </p>
                            <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-start', marginTop: '16px' }}>
                                <Button
                                    variant="link"
                                    onClick={() => {
                                        setModalState(null);
                                        setOperationState({
                                            status: 'idle',
                                            type: null,
                                            rowType: null,
                                            data: null,
                                            result: null
                                        });
                                    }}
                                >
                                    {__('Close', 'gregius-data')}
                                </Button>
                            </div>
                        </>
                    )}
                </Modal>
            )}

        </>
    );
};

export default ContentSyncTable;
