/**
 * Post Type Sync Configuration Component
 * 
 * Allows users to select which post types and statuses to sync
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

const PostTypeSyncCard = ({ selectedConnectionId, onConfigurationChange }) => {
    // Show notices if no connection is selected
    if (!selectedConnectionId) {
        return (
            <Notice status="warning" isDismissible={false}>
                {__('Please select a connection to begin.', 'gregius-data')}
            </Notice>
        );
    }

    const [postTypes, setPostTypes] = useState([]);
    const [postTypeCounts, setPostTypeCounts] = useState({}); // Dynamic counts per post type per status
    const [selectedTypes, setSelectedTypes] = useState([]);
    const [selectedStatuses, setSelectedStatuses] = useState([]);
    const [syncEnabled, setSyncEnabled] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [saveResult, setSaveResult] = useState(null);

    const postStatuses = [
        { value: 'publish', label: __('Published', 'gregius-data') },
        { value: 'private', label: __('Private', 'gregius-data') },
        { value: 'draft', label: __('Draft', 'gregius-data') },
        { value: 'pending', label: __('Pending Review', 'gregius-data') },
        { value: 'future', label: __('Scheduled', 'gregius-data') }
    ];


    useEffect(() => {
        fetchSyncConfiguration();
    }, [selectedConnectionId]);

    // Calculate dynamic post count based on selected statuses
    const calculatePostTypeCount = (postTypeName) => {
        const postType = postTypes.find(pt => pt.name === postTypeName);
        if (!postType || !postType.status_counts) return 0;
        
        // Sum counts for all selected statuses
        return selectedStatuses.reduce((total, status) => {
            return total + (parseInt(postType.status_counts[status]) || 0);
        }, 0);
    };

    const fetchSyncConfiguration = async () => {
        try {
            setIsLoading(true);
            const queryParam = selectedConnectionId ? `?connection=${encodeURIComponent(selectedConnectionId)}` : '';
            const [typesResponse, configResponse] = await Promise.all([
                apiFetch({ path: `/gg-data/v1/sync/post-types${queryParam}` }),
                apiFetch({ path: `/gg-data/v1/sync/configuration${queryParam}` })
            ]);
            
            setPostTypes(typesResponse.post_types || []);
            setSelectedTypes(configResponse.configuration?.enabled_post_types || []);
            setSelectedStatuses(configResponse.configuration?.enabled_statuses || ['publish', 'draft', 'private', 'pending', 'future']);
            setSyncEnabled(configResponse.configuration?.real_time_sync || false);
        } catch (err) {
            setSaveResult({
                status: 'error',
                message: __('Failed to load sync configuration: ', 'gregius-data') + err.message
            });
        } finally {
            setIsLoading(false);
        }
    };

    const saveConfiguration = async (updatedTypes = selectedTypes, updatedStatuses = selectedStatuses, updatedSyncEnabled = syncEnabled) => {
        try {
            setIsSaving(true);
            setSaveResult(null);
            const queryParam = selectedConnectionId ? `?connection=${encodeURIComponent(selectedConnectionId)}` : '';
            const response = await apiFetch({
                path: `/gg-data/v1/sync/configuration${queryParam}`,
                method: 'POST',
                data: {
                    enabled_post_types: updatedTypes,
                    enabled_statuses: updatedStatuses,
                    real_time_sync: updatedSyncEnabled
                }
            });
            if (response.success) {
                // Show success message - user-dismissible only, no auto-dismiss
                setSaveResult({
                    status: 'success',
                    message: __('Configuration saved', 'gregius-data')
                });

                // Refetch post types to update counts after status changes
                const typesResponse = await apiFetch({ 
                    path: `/gg-data/v1/sync/post-types${queryParam}` 
                });
                setPostTypes(typesResponse.post_types || []);

                // Notify parent component that configuration has changed
                if (onConfigurationChange) {
                    onConfigurationChange();
                }
            }
        } catch (err) {
            setSaveResult({
                status: 'error',
                message: __('Failed to save: ', 'gregius-data') + err.message
            });
        } finally {
            setIsSaving(false);
        }
    };

    const handlePostTypeToggle = async (postType, checked) => {
        const updatedTypes = checked
            ? [...selectedTypes, postType]
            : selectedTypes.filter(type => type !== postType);

        setSelectedTypes(updatedTypes);
        await saveConfiguration(updatedTypes, selectedStatuses, syncEnabled);
    };

    const handleStatusToggle = async (status, checked) => {
        const updatedStatuses = checked
            ? [...selectedStatuses, status]
            : selectedStatuses.filter(s => s !== status);

        setSelectedStatuses(updatedStatuses);
        await saveConfiguration(selectedTypes, updatedStatuses, syncEnabled);
    };

    const handleSyncEnabledToggle = async (checked) => {
        setSyncEnabled(checked);
        await saveConfiguration(selectedTypes, selectedStatuses, checked);
    };

    if (isLoading) {
        return (
            <div className="gg-post-type-sync-card">
                <div className="gg-post-type-sync-loading">
                    <Spinner />
                    <p>{__('Loading sync configuration...', 'gregius-data')}</p>
                </div>
            </div>
        );
    }

    return (
        <>
            {saveResult && (
                <Notice
                    status={saveResult.status}
                    isDismissible={true}
                    onDismiss={() => setSaveResult(null)}
                >
                    {saveResult.message}
                </Notice>
            )}
            <Card isRounded={false}>
                <CardHeader>
                    <Heading level={3}>{__('Real-time', 'gregius-data')}</Heading>
                </CardHeader>
                <CardBody>
                    <ToggleControl
                        label={__('Enable', 'gregius-data')}
                        help={__('Automatically synchronize content changes.', 'gregius-data')}
                        checked={syncEnabled}
                        onChange={handleSyncEnabledToggle}
                        disabled={isSaving}
                        __nextHasNoMarginBottom={true}
                    />
                </CardBody>
            </Card>
            <Card isRounded={false}>
                <CardHeader>
                    <Heading level={3}>{__('Post Types', 'gregius-data')}</Heading>
                </CardHeader>
                <CardBody>
                    <div className="gg-data-sync-section">
                        <p className="description">
                            {__('Select which post types should be synchronized:', 'gregius-data')}
                        </p>

                        <div className="gg-data-toggle-grid" style={{display: 'flex', flexDirection: 'column', gap: '.5rem'}}>
                            {postTypes.map(postType => (
                                <ToggleControl
                                    key={postType.name}
                                    label={`${postType.label} (${calculatePostTypeCount(postType.name)})`}
                                    checked={selectedTypes.includes(postType.name)}
                                    onChange={(checked) => handlePostTypeToggle(postType.name, checked)}
                                    disabled={isSaving}
                                    __nextHasNoMarginBottom={true}
                                />
                            ))}
                        </div>
                    </div>
                </CardBody>
            </Card>
            <Card isRounded={false}>
                <CardHeader>
                    <Heading level={3}>{__('Post Statuses', 'gregius-data')}</Heading>
                </CardHeader>

                <CardBody>
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
                </CardBody>
            </Card>
        </>
    );
};

export default PostTypeSyncCard;
