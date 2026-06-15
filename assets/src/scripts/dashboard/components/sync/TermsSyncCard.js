/**
 * Terms Sync Card Component
 * 
 * Dedicated card for managing Terms (Categories, Tags) synchronization.
 * Handles display of term counts and sync actions.
 */

import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { 
    Card, 
    CardHeader, 
    CardBody, 
    Button, 
    Spinner,
    __experimentalHeading as Heading,
    Modal,
    Notice,
    DropdownMenu,
    MenuItem
} from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

const TermsSyncCard = ({ selectedConnectionId, onSyncComplete }) => {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    
    // Operation states
    const [modalState, setModalState] = useState(null); // { type: 'sync'|'remove' }
    const [operationState, setOperationState] = useState({
        status: 'idle', // idle|processing|stopping|completed
        result: null
    });
    const [abortController, setAbortController] = useState(null);

    useEffect(() => {
        if (selectedConnectionId) {
            fetchTermsStats();
        }
    }, [selectedConnectionId]);

    const fetchTermsStats = async () => {
        try {
            setLoading(true);
            const response = await apiFetch({
                path: `/gg-data/v1/sync/validation/fast?connection=${selectedConnectionId}`
            });
            
            if (response.success && response.data?.terms) {
                setStats(response.data.terms);
            }
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    const handleBatchOperation = async (type) => {
        const endpoint = type === 'sync' 
            ? '/gg-data/v1/sync/batch-sync-terms'
            : `/gg-data/v1/sync/terms/orphans?connection=${selectedConnectionId}&batch_size=100`;
            
        const method = type === 'sync' ? 'POST' : 'DELETE';
        const startTime = Date.now();
        const controller = new AbortController();
        setAbortController(controller);

        const originalTotal = type === 'sync' 
            ? stats.wordpress_count 
            : (stats.postgresql_count - stats.wordpress_count); // Approx orphans

        setOperationState({
            status: 'processing',
            type: type,
            rowType: 'terms',
            data: stats,
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
                    data: type === 'sync' ? {
                        connection_name: selectedConnectionId,
                        batch_size: 500,
                        offset: batchCount * 500
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
                fetchTermsStats();
                if (onSyncComplete) onSyncComplete();
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

    if (!selectedConnectionId) return null;

    return (
        <>
            <Card isRounded={false} className="gg-terms-sync-card">
                <CardHeader>
                    <Heading level={3}>{__('Taxonomies', 'gregius-data')}</Heading>
                </CardHeader>
                <CardBody>
                    {loading ? (
                        <Spinner />
                    ) : stats ? (
                        <div style={{ overflowX: 'auto' }}>
                            <table>
                                <thead>
                                    <tr>
                                        <th>{__('Item', 'gregius-data')}</th>
                                        <th>{__('WordPress', 'gregius-data')}</th>
                                        <th>{__('PostgreSQL', 'gregius-data')}</th>
                                        <th>{__('Status', 'gregius-data')}</th>
                                        <th style={{ width: '60px' }}>{__('Actions', 'gregius-data')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>{__('Terms & Taxonomies', 'gregius-data')}</strong></td>
                                        <td>{stats.wordpress_count?.toLocaleString()}</td>
                                        <td>{stats.postgresql_count?.toLocaleString()}</td>
                                        <td>
                                            <span className={`components-badge is-${stats.status === 'healthy' ? 'success' : 'warning'}`}>
                                                {stats.status || 'Unknown'}
                                            </span>
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
                                                                setModalState({ type: 'sync', data: stats });
                                                                onClose();
                                                            } }
                                                        >
                                                            { __('Sync Terms', 'gregius-data') }
                                                        </MenuItem>
                                                        <MenuItem
                                                            onClick={ () => {
                                                                setModalState({ type: 'remove', data: stats });
                                                                onClose();
                                                            } }
                                                            className="has-text-color has-vivid-red-color"
                                                            disabled={ !stats.postgresql_count || stats.postgresql_count === 0 }
                                                        >
                                                            { __('Remove Orphans', 'gregius-data') }
                                                        </MenuItem>
                                                    </>
                                                ) }
                                            </DropdownMenu>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p>{__('No term data available.', 'gregius-data')}</p>
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
                    title={modalState.type === 'sync' ? __('Sync Terms', 'gregius-data') : __('Remove Orphan Terms', 'gregius-data')}
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
                                        __('This will process %d terms.', 'gregius-data'),
                                        modalState.data?.wordpress_count || 0
                                    )
                                    : __('This will permanently delete terms that exist in PostgreSQL but not in WordPress. This operation cannot be undone.', 'gregius-data')
                                }
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
                                    onClick={() => handleBatchOperation(modalState.type)}
                                >
                                    {modalState.type === 'sync' ? __('Sync', 'gregius-data') : __('Remove', 'gregius-data')}
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
                                    {modalState.type === 'sync' ? __('Syncing...', 'gregius-data') : __('Removing...', 'gregius-data')}
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
                                    : sprintf(
                                        __('%d orphan terms deleted (%s).', 'gregius-data'),
                                        operationState.result?.deleted || operationState.result?.processed || 0,
                                        typeof operationState.result?.duration === 'number'
                                            ? operationState.result.duration.toFixed(2) + 's'
                                            : operationState.result?.duration || '0s'
                                    )
                                }
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

export default TermsSyncCard;
