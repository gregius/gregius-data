/**
 * Post Statuses Configuration Card
 * 
 * Select which post statuses should be synchronized.
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

const PostStatusesCard = ({ selectedConnectionId, onConfigurationChange }) => {
    const [selectedStatuses, setSelectedStatuses] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState(null);
    const [fullConfig, setFullConfig] = useState({});

    const postStatuses = [
        { value: 'publish', label: __('Published', 'gregius-data') },
        { value: 'private', label: __('Private', 'gregius-data') },
        { value: 'draft', label: __('Draft', 'gregius-data') },
        { value: 'pending', label: __('Pending Review', 'gregius-data') },
        { value: 'future', label: __('Scheduled', 'gregius-data') }
    ];

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
            const statuses = response.configuration?.enabled_statuses;
            // Ensure statuses is always an array (use empty array if not provided)
            setSelectedStatuses(Array.isArray(statuses) ? statuses : []);
        } catch (err) {
            setError(__('Failed to load configuration: ', 'gregius-data') + err.message);
        } finally {
            setIsLoading(false);
        }
    };

    const handleStatusToggle = async (status, checked) => {
        const oldStatuses = selectedStatuses;
        // Ensure selectedStatuses is an array before filtering
        const currentStatuses = Array.isArray(selectedStatuses) ? selectedStatuses : [];
        const updatedStatuses = checked
            ? [...currentStatuses, status]
            : currentStatuses.filter(s => s !== status);

        try {
            setIsSaving(true);
            setSelectedStatuses(updatedStatuses); // Optimistic
            setError(null);

            // Fetch latest config to avoid race conditions
            const latestConfigRes = await apiFetch({ 
                path: `/gg-data/v1/sync/configuration?connection=${encodeURIComponent(selectedConnectionId)}` 
            });

            const response = await apiFetch({
                path: `/gg-data/v1/sync/configuration?connection=${encodeURIComponent(selectedConnectionId)}`,
                method: 'POST',
                data: {
                    ...latestConfigRes.configuration,
                    enabled_statuses: updatedStatuses
                }
            });

            if (response.success) {
                // Use the returned configuration to ensure state matches what was actually saved
                setFullConfig(response.configuration || {});
                const returnedStatuses = response.configuration?.enabled_statuses;
                // Ensure we always set an array
                setSelectedStatuses(Array.isArray(returnedStatuses) ? returnedStatuses : updatedStatuses);
                if (onConfigurationChange) onConfigurationChange();
            } else {
                throw new Error(response.message || 'Save failed');
            }
        } catch (err) {
            setError(__('Failed to save: ', 'gregius-data') + err.message);
            // Revert to old state
            setSelectedStatuses(oldStatuses);
            // Re-fetch to sync
            fetchConfiguration();
        } finally {
            setIsSaving(false);
        }
    };

    if (!selectedConnectionId) return null;

    return (
        <Card isRounded={false}>
            <CardHeader>
                <Heading level={3}>{__('Post Statuses', 'gregius-data')}</Heading>
            </CardHeader>
            <CardBody>
                {isLoading ? (
                    <Spinner />
                ) : (
                    <>
                        <div className="gg-data-sync-section">
                            <p className="description">
                                {__('Select which post statuses should be synchronized:', 'gregius-data')}
                            </p>

                            <div className="gg-data-toggle-grid" style={{display: 'flex', flexDirection: 'column', gap: '.5rem'}}>
                                {postStatuses.map(status => (
                                    <ToggleControl
                                        key={status.value}
                                        label={status.label}
                                        checked={selectedStatuses.includes(status.value)}
                                        onChange={(checked) => handleStatusToggle(status.value, checked)}
                                        disabled={isSaving}
                                        __nextHasNoMarginBottom={true}
                                    />
                                ))}
                            </div>
                        </div>
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

export default PostStatusesCard;
