/**
 * Connection List Component
 * 
 * Displays a list of all database connections with health status,
 * actions for edit/delete/test operations.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Card,
    CardHeader,
    CardBody,
    Button,
    Notice,
    DropdownMenu,
    __experimentalHeading as Heading,
    __experimentalGrid as Grid
} from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import { formatRelativeTime } from '../../utils/format-time';
import SchemaSetupModal from './SchemaSetupModal';

const ConnectionList = ({
    connections,
    onEdit,
    onDelete,
    onTest,
    onAdd,
    testingConnection,
    testResults,
    onDismissTestResult
}) => {
    const [schemaStatuses, setSchemaStatuses] = useState({});
    const [searchSchemaStatuses, setSearchSchemaStatuses] = useState({});
    const [healthStatuses, setHealthStatuses] = useState({});
    const [creatingSchema, setCreatingSchema] = useState({});
    const [creatingSearchSchema, setCreatingSearchSchema] = useState({});
    const [fixingSettings, setFixingSettings] = useState({});
    const [settingsNeedFix, setSettingsNeedFix] = useState({});
    const [settingsFixed, setSettingsFixed] = useState({});
    const [checkingHealth, setCheckingHealth] = useState({});
    const [healthCheckResults, setHealthCheckResults] = useState({});
    const [upgradingSchema, setUpgradingSchema] = useState({});
    const [verifyingSchema, setVerifyingSchema] = useState({});
    const [schemaModalOpen, setSchemaModalOpen] = useState(false);
    const [schemaModalConnection, setSchemaModalConnection] = useState(null);
    const [schemaModalMode, setSchemaModalMode] = useState('setup');
    const [schemaActionError, setSchemaActionError] = useState({});

    // Fetch schema status for all connections
    useEffect(() => {
        if (connections) {
            Object.keys(connections).forEach(async (name) => {
                try {
                    const response = await apiFetch({
                        path: `/gregius-data/v1/schema/status?connection=${name}`
                    });
                    setSchemaStatuses(prev => ({ ...prev, [name]: response }));
                } catch (err) {
                }

                try {
                    const searchResponse = await apiFetch({
                        path: `/gregius-data/v1/search/status?connection=${name}`
                    });
                    setSearchSchemaStatuses(prev => ({ ...prev, [name]: searchResponse }));
                    // If search schema exists, check settings health
                    if (searchResponse?.schema_version) {
                        checkSettingsHealth(name);
                    }
                } catch (err) {
                    // Search schema is optional, silently fail
                }

                // Fetch health status
                try {
                    const healthResponse = await apiFetch({
                        path: `/gregius-data/v1/sync/connection-health?connection=${name}`
                    });
                    if (healthResponse.success) {
                        setHealthStatuses(prev => ({ ...prev, [name]: healthResponse.data }));
                    }
                } catch (err) {
                }
            });
        }
    }, [connections]);

    // Handler to create schema
    const handleCreateSchema = async (connectionName) => {
        const connection = connections[connectionName];

        // For Supabase, open schema setup modal
        if (connection.type === 'postgrest') {
            setSchemaModalConnection(connectionName);
            setSchemaModalMode('setup');
            setSchemaModalOpen(true);
            return;
        }

        // For PDO connections, create schema directly
        try {
            setCreatingSchema(prev => ({ ...prev, [connectionName]: true }));
            setSchemaActionError(prev => ({ ...prev, [connectionName]: null }));
            const response = await apiFetch({
                path: `/gregius-data/v1/schema/create?connection=${connectionName}`,
                method: 'POST'
            });

            if (response.success) {
                // Refresh schema status
                const schemaResponse = await apiFetch({
                    path: `/gregius-data/v1/schema/status?connection=${connectionName}`
                });
                setSchemaStatuses(prev => ({ ...prev, [connectionName]: schemaResponse }));
            }
        } catch (err) {
            setSchemaActionError(prev => ({
                ...prev,
                [connectionName]: err?.data?.message || err?.message || __('Failed to create schema.', 'gregius-data')
            }));
        } finally {
            setCreatingSchema(prev => ({ ...prev, [connectionName]: false }));
        }
    };

    // Handler to create search schema
    const handleCreateSearchSchema = async (connectionName) => {
        try {
            setCreatingSearchSchema(prev => ({ ...prev, [connectionName]: true }));
            const response = await apiFetch({
                path: `/gregius-data/v1/search/schema/create`,
                method: 'POST',
                data: {
                    connection: connectionName
                }
            });

            if (response.success) {
                // Refresh search schema status
                const searchResponse = await apiFetch({
                    path: `/gregius-data/v1/search/status?connection=${connectionName}`
                });
                setSearchSchemaStatuses(prev => ({ ...prev, [connectionName]: searchResponse }));
                // Check settings health for this connection
                checkSettingsHealth(connectionName);
            }
        } catch (err) {
        } finally {
            setCreatingSearchSchema(prev => ({ ...prev, [connectionName]: false }));
        }
    };

    const checkSettingsHealth = async (connectionName) => {
        if (settingsFixed[connectionName]) {
            return;
        }

        try {
            const response = await apiFetch({
                path: `/gregius-data/v1/search/language-status?connection=${connectionName}`
            });
            // Show Update button if: language setting missing OR language mismatches current locale
            setSettingsNeedFix(prev => ({
                ...prev,
                [connectionName]: !response.stored || response.mismatch
            }));
        } catch (err) {
            setSettingsNeedFix(prev => ({ ...prev, [connectionName]: true }));
        }
    };

    const handleFixSettings = async (connectionName) => {
        try {
            setFixingSettings(prev => ({ ...prev, [connectionName]: true }));
            const response = await apiFetch({
                path: `/gregius-data/v1/search/fix-settings?connection=${connectionName}`,
                method: 'POST'
            });

            if (response.success) {
                const searchResponse = await apiFetch({
                    path: `/gregius-data/v1/search/status?connection=${connectionName}`
                });
                setSearchSchemaStatuses(prev => ({ ...prev, [connectionName]: searchResponse }));
                setSettingsNeedFix(prev => ({ ...prev, [connectionName]: false }));
                setSettingsFixed(prev => ({ ...prev, [connectionName]: true }));
            }
        } catch (err) {
        } finally {
            setFixingSettings(prev => ({ ...prev, [connectionName]: false }));
        }
    };

    // Handler to check health
    const handleCheckHealth = async (connectionName) => {
        try {
            setCheckingHealth(prev => ({ ...prev, [connectionName]: true }));
            setHealthCheckResults(prev => ({ ...prev, [connectionName]: null }));
            const response = await apiFetch({
                path: '/gregius-data/v1/sync/connection-health/check',
                method: 'POST',
                data: { connection: connectionName }
            });

            if (response.success) {
                setHealthStatuses(prev => ({ ...prev, [connectionName]: response.data }));
                setHealthCheckResults(prev => ({
                    ...prev,
                    [connectionName]: {
                        status: response.data?.status,
                        message: response.data?.last_error || ''
                    }
                }));
            }
        } catch (err) {
            setHealthCheckResults(prev => ({
                ...prev,
                [connectionName]: {
                    status: 'error',
                    message: err?.data?.message || err?.message || __('Health check failed.', 'gregius-data')
                }
            }));
        } finally {
            setCheckingHealth(prev => ({ ...prev, [connectionName]: false }));
        }
    };

    // Handler to open the schema update modal (Supabase manual re-run)
    const handleUpgradeSchemaModal = (connectionName) => {
        setSchemaModalConnection(connectionName);
        setSchemaModalMode('upgrade');
        setSchemaModalOpen(true);
    };

    // Handler to upgrade schema
    const handleUpgradeSchema = async (connectionName) => {
        try {
            setUpgradingSchema(prev => ({ ...prev, [connectionName]: true }));
            setSchemaActionError(prev => ({ ...prev, [connectionName]: null }));
            const response = await apiFetch({
                path: `/gregius-data/v1/schema/upgrade?connection=${connectionName}`,
                method: 'POST'
            });

            if (response.success) {
                // Refresh schema status to remove update banner
                const schemaResponse = await apiFetch({
                    path: `/gregius-data/v1/schema/status?connection=${connectionName}`
                });
                setSchemaStatuses(prev => ({ ...prev, [connectionName]: schemaResponse }));
            }
        } catch (err) {
            setSchemaActionError(prev => ({
                ...prev,
                [connectionName]: err?.data?.message || err?.message || __('Failed to upgrade schema.', 'gregius-data')
            }));
        } finally {
            setUpgradingSchema(prev => ({ ...prev, [connectionName]: false }));
        }
    };

    // Handler for successful schema setup
    const handleSchemaSetupSuccess = async () => {
        if (schemaModalConnection) {
            const connectionName = schemaModalConnection;

            // Refresh schema status
            const schemaResponse = await apiFetch({
                path: `/gregius-data/v1/schema/status?connection=${connectionName}`
            });
            setSchemaStatuses(prev => ({ ...prev, [connectionName]: schemaResponse }));
        }
    };

    // Helper to render the "Schema" detail cell for a connection.
    const renderSchemaCell = (name, connection) => {
        const status = schemaStatuses[name];
        if (!status) return null;

        // Connection failure: report it, offer no action (the action would fail).
        if (status.connected === false || status.error) {
            return (
                <div className="gg-health-detail">
                    <strong>{__('Schema: ', 'gregius-data')}</strong>
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                        <span>{__('Unavailable', 'gregius-data')}</span>
                        {status.message && (
                            <span style={{ fontSize: '0.85em', opacity: 0.7 }} title={status.message}>
                                {status.message}
                            </span>
                        )}
                    </span>
                </div>
            );
        }

        const version = (status.schema_version && status.schema_version !== '0.0.0')
            ? status.schema_version
            : null;

        switch (status.schema_status) {
            case 'missing':
                return (
                    <div className="gg-health-detail">
                        <strong>{__('Schema: ', 'gregius-data')}</strong>
                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                            <span>{__('Not Created', 'gregius-data')}</span>
                            <Button
                                variant="primary"
                                onClick={() => handleCreateSchema(name)}
                                disabled={creatingSchema[name]}
                            >
                                {creatingSchema[name] ? __('Creating...', 'gregius-data') : __('Create Schema', 'gregius-data')}
                            </Button>
                        </span>
                    </div>
                );

            case 'update_available':
                return (
                    <div className="gg-health-detail">
                        <strong>{__('Schema: ', 'gregius-data')}</strong>
                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                            <code>{version || __('Unknown', 'gregius-data')}</code>
                            <Button
                                variant="primary"
                                onClick={() => connection.type === 'postgrest' ? handleUpgradeSchemaModal(name) : handleUpgradeSchema(name)}
                                disabled={upgradingSchema[name]}
                            >
                                {upgradingSchema[name] ? __('Updating...', 'gregius-data') : __('Update Schema', 'gregius-data')}
                            </Button>
                        </span>
                    </div>
                );

            case 'schema_newer':
                return (
                    <div className="gg-health-detail">
                        <strong>{__('Schema: ', 'gregius-data')}</strong>
                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                            <code>{version || __('Unknown', 'gregius-data')}</code>
                            <span className="components-badge is-info">{__('Newer', 'gregius-data')}</span>
                        </span>
                    </div>
                );

            case 'current':
            default:
                return (
                    <div className="gg-health-detail">
                        <strong>{__('Schema: ', 'gregius-data')}</strong>
                        <code>{version || __('Unknown', 'gregius-data')}</code>
                    </div>
                );
        }
    };

    // Helper function to get connection status badge
    const getStatusBadge = (connection) => {
        const isActive = connection.is_active;

        return (
            <span className={`components-badge is-${isActive ? 'success' : 'warning'}`}>
                {isActive ? __('Active', 'gregius-data') : __('Inactive', 'gregius-data')}
            </span>
        );
    };

    // Helper function to format connection info
    const getConnectionInfo = (connection) => {
        // Supabase uses project URL instead of host:port/database
        if (connection.type === 'postgrest') {
            return connection.project_url || __('Supabase Project', 'gregius-data');
        }

        // PostgreSQL format
        const host = connection.host || __('Unknown', 'gregius-data');
        const port = connection.port || 5432;
        const database = connection.database || __('Unknown', 'gregius-data');

        return `${host}:${port}/${database}`;
    };

    // Helper function to get SSL mode display
    const getSslModeDisplay = (sslMode) => {
        const modes = {
            'disable': __('Disabled', 'gregius-data'),
            'allow': __('Allow', 'gregius-data'),
            'prefer': __('Prefer', 'gregius-data'),
            'require': __('Require', 'gregius-data'),
            'verify-ca': __('Verify CA', 'gregius-data'),
            'verify-full': __('Verify Full', 'gregius-data')
        };

        return modes[sslMode] || sslMode || __('Unknown', 'gregius-data');
    };

    // Helper function to get database type display (Phase R.2.4)
    const getDatabaseTypeDisplay = (type) => {
        const types = {
            'postgresql': __('PostgreSQL (Direct)', 'gregius-data'),
            'supabase': __('Supabase (HTTP)', 'gregius-data'),
            'mysql': __('MySQL', 'gregius-data')
        };

        return types[type] || type || __('PostgreSQL', 'gregius-data');
    };

    // If no connections exist
    if (!connections || Object.keys(connections).length === 0) {
        return (
            <Card isRounded={false}>
                <CardBody style={{ textAlign: 'center', padding: '60px 40px' }}>
                    <p style={{ color: '#646970', marginBottom: '24px' }}>
                        {__('Add your first PostgreSQL database connection to get started', 'gregius-data')}
                    </p>
                    <Button
                        variant="secondary"
                        onClick={onAdd}
                    >
                        {__('Add Your First Connection', 'gregius-data')}
                    </Button>
                </CardBody>
            </Card>
        );
    }

    // Render list of connections
    return (
        <>
            {/* Schema Setup Modal for Supabase */}
            {schemaModalConnection && (
                <SchemaSetupModal
                    isOpen={schemaModalOpen}
                    onRequestClose={() => {
                        setSchemaModalOpen(false);
                        setSchemaModalConnection(null);
                    }}
                    connectionName={schemaModalConnection}
                    mode={schemaModalMode}
                    currentVersion={schemaStatuses[schemaModalConnection]?.schema_version}
                    requiredVersion={schemaStatuses[schemaModalConnection]?.required_schema_version || schemaStatuses[schemaModalConnection]?.plugin_version}
                    onSuccess={handleSchemaSetupSuccess}
                />
            )}

            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    gap: '2rem'
                }}
            >
            {Object.entries(connections).map(([name, connection]) => (
                <Card
                    isRounded={false}
                    key={`connection-${name}`}
                >
                    <CardHeader>
                        <div style={{
                            display: 'flex',
                            flexWrap: 'wrap',
                            gap: '1rem',
                            justifyContent: 'space-between',
                            alignItems: 'flex-start',
                            width: '100%'
                        }}>
                            <div>
                                <div style={{ display: 'flex', flexDirection: 'row', alignItems: 'center', gap: '.25em' }}>
                                    <Heading level={3} style={{ margin: 0 }}>{name}</Heading>
                                    {getStatusBadge(connection)}
                                </div>
                                {connection.description && (
                                    <p className="description" style={{ margin: 0 }}>
                                        {connection.description}
                                    </p>
                                )}
                            </div>
                            <DropdownMenu
                                icon={moreVertical}
                                label={__('Connection actions', 'gregius-data')}
                                controls={[
                                    {
                                        title: testingConnection === name
                                            ? __('Testing...', 'gregius-data')
                                            : __('Test Connection', 'gregius-data'),
                                        onClick: () => onTest(name),
                                        isDisabled: testingConnection === name,
                                    },
                                    {
                                        title: checkingHealth[name]
                                            ? __('Checking...', 'gregius-data')
                                            : __('Check Health', 'gregius-data'),
                                        onClick: () => handleCheckHealth(name),
                                        isDisabled: checkingHealth[name],
                                    },
                                    {
                                        title: __('Edit Connection', 'gregius-data'),
                                        onClick: () => onEdit({ name, ...connection }),
                                    },
                                    {
                                        title: __('Delete Connection', 'gregius-data'),
                                        onClick: () => onDelete(name),
                                        className: 'has-text-color has-vivid-red-color',
                                    },
                                ]}
                            />
                        </div>
                    </CardHeader>
                    <CardBody>
                        {/* Schema update available banner (text only; action lives in the Schema row below) */}
                        {schemaStatuses[name]?.update_available && (
                            <Notice
                                status="warning"
                                isDismissible={false}
                                style={{ marginBottom: '16px' }}
                            >
                                <strong>{__('Schema Update Required', 'gregius-data')}</strong>
                                <span style={{ marginLeft: '8px' }}>
                                    {__('Your schema is', 'gregius-data')} <code>{schemaStatuses[name].schema_version}</code>, {__('this plugin requires', 'gregius-data')} <code>{schemaStatuses[name].required_schema_version || schemaStatuses[name].plugin_version}</code>.
                                </span>
                            </Notice>
                        )}

                        {/* Schema action error (create/upgrade failure) */}
                        {schemaActionError[name] && (
                            <Notice
                                status="error"
                                isDismissible={false}
                                style={{ marginBottom: '16px' }}
                            >
                                <strong>{__('Schema action failed:', 'gregius-data')}</strong>{' '}
                                {schemaActionError[name]}
                            </Notice>
                        )}

                        {/* Test result for this specific connection */}
                        {testResults && testResults.connectionName === name && (
                            <Notice
                                status={testResults.result.success ? 'success' : 'error'}
                                isDismissible={true}
                                onDismiss={onDismissTestResult}
                                style={{ marginBottom: '16px' }}
                            >
                                <strong>{__('Test Result: ', 'gregius-data')}</strong>
                                <span>{testResults.result.message}</span>
                                {testResults.result.success && testResults.result.response_time && (
                                    <span style={{ marginLeft: '8px', fontSize: '0.9em', opacity: 0.8 }}>
                                        ({testResults.result.response_time}ms)
                                    </span>
                                )}
                            </Notice>
                        )}

                        {/* Health check result for this specific connection */}
                        {healthCheckResults && healthCheckResults[name] && (
                            <Notice
                                status={healthCheckResults[name].status === 'healthy' ? 'success' : 'error'}
                                isDismissible={true}
                                onDismiss={() => setHealthCheckResults(prev => ({ ...prev, [name]: null }))}
                                style={{ marginBottom: '16px' }}
                            >
                                <strong>{__('Health Check: ', 'gregius-data')}</strong>
                                <span>
                                    {healthCheckResults[name].status === 'healthy'
                                        ? __('healthy', 'gregius-data')
                                        : healthCheckResults[name].message || __('unhealthy', 'gregius-data')}
                                </span>
                            </Notice>
                        )}

                        {/* Health Metrics */}
                        {healthStatuses[name] && (
                            <>
                                <Grid columns={3} gap={4}>
                                    <div>
                                        <strong>{__('Uptime: ', 'gregius-data')}</strong> <span className='components-badge is-info'>{Math.round(healthStatuses[name].uptime_percentage || 100)}%</span>
                                    </div>
                                    <div>
                                        <strong>{__('Total Checks: ', 'gregius-data')}</strong> <span className='components-badge is-info'>{healthStatuses[name].total_checks || 0}</span>
                                    </div>
                                    <div>
                                        <strong>{__('Consecutive Failures: ', 'gregius-data')}</strong>
                                        <span className={`components-badge ${healthStatuses[name].consecutive_failures > 0 ? 'is-warning' : 'is-info'}`}>
                                            {healthStatuses[name].consecutive_failures || 0}
                                        </span>
                                    </div>
                                    <div>
                                        <strong>{__('Total Failures: ', 'gregius-data')}</strong> <span className='components-badge is-info'>{healthStatuses[name].total_failures || 0}</span>
                                    </div>
                                    <div>
                                        <strong>{__('Last Health Check: ', 'gregius-data')}</strong> <span className='components-badge is-info'>{formatRelativeTime(healthStatuses[name].last_check)}</span>
                                    </div>
                                    <div>
                                        <strong>{__('Last Successful Check: ', 'gregius-data')}</strong> <span className='components-badge is-info'>{formatRelativeTime(healthStatuses[name].last_success)}</span>
                                    </div>
                                </Grid>

                                <hr style={{ margin: '16px 0' }} />
                            </>
                        )}

                        {/* Connection metrics (metric-style boxes) */}
                        <Grid columns={3} gap={4}>
                            <div>
                                <strong>{__('Connection:', 'gregius-data')}</strong> {getConnectionInfo(connection)}
                            </div>
                            <div>
                                <strong>{__('Database Type:', 'gregius-data')}</strong> {getDatabaseTypeDisplay(connection.type)}
                            </div>
                            {connection.type !== 'supabase' && (
                                <div>
                                    <strong>{__('Username:', 'gregius-data')}</strong> {connection.username || __('Not set', 'gregius-data')}
                                </div>
                            )}
                            {connection.type !== 'supabase' && (
                                <div>
                                    <strong>{__('SSL Mode:', 'gregius-data')}</strong> {getSslModeDisplay(connection.ssl_mode)}
                                </div>
                            )}
                            <div>
                                <strong>{__('Timeout:', 'gregius-data')}</strong> {connection.connect_timeout || 30}s
                            </div>
                        </Grid>

                        <hr style={{ margin: '16px 0' }} />

                        {/* Schema information (detail rows like Search Health Card) */}
                        <Grid columns={3} gap={4} style={{ marginTop: '16px' }}>
                            {renderSchemaCell(name, connection)}

                            {schemaStatuses[name] && (
                                <div className="gg-health-detail">
                                    <strong>{__('pg_trgm: ', 'gregius-data')}</strong>
                                    {schemaStatuses[name].pg_trgm_extension ? (
                                        <span>{__('Installed', 'gregius-data')}</span>
                                    ) : (
                                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}>
                                            <span>{__('Not Installed', 'gregius-data')}</span>
                                        </span>
                                    )}
                                </div>
                            )}

                            {schemaStatuses[name] && (
                                <div className="gg-health-detail">
                                    <strong>{__('pgvector: ', 'gregius-data')}</strong>
                                    {schemaStatuses[name].vector_extension ? (
                                        <span>{__('Installed', 'gregius-data')}</span>
                                    ) : (
                                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}>
                                            <span>{__('Not Installed', 'gregius-data')}</span>
                                        </span>
                                    )}
                                </div>
                            )}
                        </Grid>
                    </CardBody>
                </Card>
            ))}
            </div>
        </>
    );
};

export default ConnectionList;
