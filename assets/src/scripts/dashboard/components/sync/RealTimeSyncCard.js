/**
 * Real-time Sync Configuration Card
 * 
 * Global toggle for real-time synchronization.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Card,
    CardBody,
    CardHeader,
    ToggleControl,
    Notice,
    Spinner,
    __experimentalHeading as Heading
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const RealTimeSyncCard = ({ selectedConnectionId, onConfigurationChange }) => {
    const [syncEnabled, setSyncEnabled] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState(null);
    
    // We need to store other config parts to avoid overwriting them
    const [fullConfig, setFullConfig] = useState({});

    useEffect(() => {
        if (selectedConnectionId) {
            fetchConfiguration();
        }
    }, [selectedConnectionId]);

    const fetchConfiguration = async () => {
        try {
            setIsLoading(true);
            const response = await apiFetch({ 
                path: `/gg-data/v1/sync/configuration?connection=${encodeURIComponent(selectedConnectionId)}` 
            });
            
            setFullConfig(response.configuration || {});
            setSyncEnabled(response.configuration?.real_time_sync || false);
        } catch (err) {
            setError(__('Failed to load configuration: ', 'gregius-data') + err.message);
        } finally {
            setIsLoading(false);
        }
    };

    const handleSyncEnabledToggle = async (checked) => {
        try {
            setIsSaving(true);
            setSyncEnabled(checked); // Optimistic update
            setError(null);

            const response = await apiFetch({
                path: `/gg-data/v1/sync/configuration?connection=${encodeURIComponent(selectedConnectionId)}`,
                method: 'POST',
                data: {
                    ...fullConfig,
                    real_time_sync: checked
                }
            });

            if (response.success) {
                // Use the returned configuration to ensure state matches what was actually saved
                setFullConfig(response.configuration || {});
                setSyncEnabled(response.configuration?.real_time_sync ?? checked);
                if (onConfigurationChange) onConfigurationChange();
            }
        } catch (err) {
            setError(__('Failed to save: ', 'gregius-data') + err.message);
            setSyncEnabled(!checked); // Revert
        } finally {
            setIsSaving(false);
        }
    };

    if (!selectedConnectionId) return null;

    return (
        <Card isRounded={false}>
            <CardHeader>
                <Heading level={3}>{__('Real-time', 'gregius-data')}</Heading>
            </CardHeader>
            <CardBody>
                {isLoading ? (
                    <Spinner />
                ) : (
                    <>
                        <ToggleControl
                            label={__('Enable', 'gregius-data')}
                            help={__('Automatically synchronize content changes.', 'gregius-data')}
                            checked={syncEnabled}
                            onChange={handleSyncEnabledToggle}
                            disabled={isSaving}
                            __nextHasNoMarginBottom={true}
                        />
                        {error && (
                            <Notice status="error" onRemove={() => setError(null)} style={{ marginTop: '1rem' }}>
                                {error}
                            </Notice>
                        )}
                    </>
                )}
            </CardBody>
        </Card>
    );
};

export default RealTimeSyncCard;
