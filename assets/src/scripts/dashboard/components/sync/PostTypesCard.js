/**
 * Post Types Configuration Card
 * 
 * Select which post types should be synchronized.
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

const PostTypesCard = ({ selectedConnectionId, onConfigurationChange }) => {
    const [postTypes, setPostTypes] = useState([]);
    const [selectedTypes, setSelectedTypes] = useState([]);
    const [selectedStatuses, setSelectedStatuses] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState(null);
    const [fullConfig, setFullConfig] = useState({});

    useEffect(() => {
        if (selectedConnectionId) {
            fetchConfiguration();
        }
    }, [selectedConnectionId]);

    const fetchConfiguration = async () => {
        try {
            setIsLoading(true);
            const queryParam = `?connection=${encodeURIComponent(selectedConnectionId)}`;
            const [typesResponse, configResponse] = await Promise.all([
                apiFetch({ path: `/gg-data/v1/sync/post-types${queryParam}` }),
                apiFetch({ path: `/gg-data/v1/sync/configuration${queryParam}` })
            ]);
            
            setPostTypes(typesResponse.post_types || []);
            setFullConfig(configResponse.configuration || {});
            setSelectedTypes(configResponse.configuration?.enabled_post_types || []);
            setSelectedStatuses(configResponse.configuration?.enabled_statuses || []);
        } catch (err) {
            setError(__('Failed to load configuration: ', 'gregius-data') + err.message);
        } finally {
            setIsLoading(false);
        }
    };

    // Calculate dynamic post count based on selected statuses
    const calculatePostTypeCount = (postTypeName) => {
        const postType = postTypes.find(pt => pt.name === postTypeName);
        if (!postType || !postType.status_counts) return 0;
        
        // Sum counts for all selected statuses
        // If no statuses selected, assume all? Or none? 
        // Usually defaults to publish/draft etc if empty, but let's use the state.
        const statusesToCheck = selectedStatuses.length > 0 ? selectedStatuses : ['publish']; 
        
        return statusesToCheck.reduce((total, status) => {
            return total + (parseInt(postType.status_counts[status]) || 0);
        }, 0);
    };

    const handlePostTypeToggle = async (postType, checked) => {
        const oldTypes = selectedTypes;
        const updatedTypes = checked
            ? [...selectedTypes, postType]
            : selectedTypes.filter(type => type !== postType);

        try {
            setIsSaving(true);
            setSelectedTypes(updatedTypes); // Optimistic
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
                    enabled_post_types: updatedTypes
                }
            });

            if (response.success) {
                setFullConfig(response.configuration || latestConfigRes.configuration);
                if (onConfigurationChange) onConfigurationChange();
            } else {
                throw new Error(response.message || 'Save failed');
            }
        } catch (err) {
            setError(__('Failed to save: ', 'gregius-data') + err.message);
            // Revert to old state
            setSelectedTypes(oldTypes);
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
                <Heading level={3}>{__('Post Types', 'gregius-data')}</Heading>
            </CardHeader>
            <CardBody>
                {isLoading ? (
                    <Spinner />
                ) : (
                    <>
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

export default PostTypesCard;
