/**
 * Sync Page Component
 *
 * Handles all content sync, cleaning, and processing operations.
 */

import { useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
    __experimentalHeading as Heading
} from '@wordpress/components';
import DatabaseSelector from '../components/DatabaseSelector';
import TermsSyncCard from '../components/sync/TermsSyncCard';
import ContentSyncTable from '../components/sync/ContentSyncTable';
import RealTimeSyncCard from '../components/sync/RealTimeSyncCard';
import PostStatusesCard from '../components/sync/PostStatusesCard';
import RetryQueueCard from '../components/sync/RetryQueueCard';

const SyncPage = ({ settings, isLoading, error, apiStatus }) => {
    // Refresh trigger for coordinating child card updates
    const [refreshTrigger, setRefreshTrigger] = useState(0);

    // Use WordPress data stores (no duplicate calls!)
    const { connections, isLoadingConnections } = useSelect((select) => ({
        connections: select('gg-data/connections').getConnectionsList(),
        isLoadingConnections: select('gg-data/connections').isLoading(),
    }), []);

    const { selectedConnectionId } = useSelect((select) => ({
        selectedConnectionId: select('gg-data/selected').getConnectionId(),
    }), []);

    const { setConnection } = useDispatch('gg-data/selected');

    // Callback for when sync configuration changes (post types)
    const handlePostTypeChange = () => {
        setRefreshTrigger(prev => prev + 1);
        // Don't remount table - component handles optimistic updates internally
    };

    // Callback for when other settings change (statuses, real-time sync)
    const handleOtherConfigChange = () => {
        setRefreshTrigger(prev => prev + 1);
        // Don't remount table - status changes shouldn't affect post type toggles
    };

    return (
        <div className="gg-data-page">
            <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 16, padding: '2rem 1.5rem 0', borderTop: '1px solid rgba(0, 0, 0, 0.1)' }}>
                <Heading level={2}>{__('Synchronization', 'gregius-data')}</Heading>
                {!isLoadingConnections && connections.length > 0 && (
                    <div style={{ minWidth: 220, }}>
                        <DatabaseSelector
                            connections={connections}
                            selectedConnectionId={selectedConnectionId}
                            onSelect={setConnection}
                        />
                    </div>
                )}
            </div>
            
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem', padding: '1.5rem' }}>
                <RetryQueueCard selectedConnectionId={selectedConnectionId} />

                <RealTimeSyncCard 
                    selectedConnectionId={selectedConnectionId} 
                    onConfigurationChange={handleOtherConfigChange}
                />

                <PostStatusesCard 
                    selectedConnectionId={selectedConnectionId} 
                    onConfigurationChange={handleOtherConfigChange}
                />

                <TermsSyncCard selectedConnectionId={selectedConnectionId} />
                
                <ContentSyncTable 
                    selectedConnectionId={selectedConnectionId} 
                    refreshTrigger={refreshTrigger} // Pass as prop for validation refresh
                    onPostTypeChange={handlePostTypeChange} // Notify when post types change
                />
            </div>
        </div>
    );
};

export default SyncPage;
