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

    useEffect(() => {
        if (selectedConnectionId) {
            fetchConfiguration();
        }
    }, [selectedConnectionId]);

    const fetchConfiguration = async () => {
        try {
            setIsLoading(true);
            const response = await apiFetch({ 
                path: `/gregius-data/v1/sync/configuration?connection=${encodeURIComponent(selectedConnectionId)}` 
            });
            
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

            // Fetch the latest config to avoid overwriting other cards' changes.
            const latestConfigRes = await apiFetch({
                path: `/gregius-data/v1/sync/configuration?connection=${encodeURIComponent(selectedConnectionId)}`
            });

            const response = await apiFetch({
                path: `/gregius-data/v1/sync/configuration?connection=${encodeURIComponent(selectedConnectionId)}`,
                method: 'POST',
                data: {
                    ...latestConfigRes.configuration,
                    real_time_sync: checked
                }
            });

            if (response.success) {
                // Use the returned configuration to ensure state matches what was actually saved
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
