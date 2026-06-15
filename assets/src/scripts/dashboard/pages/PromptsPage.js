/**
 * Prompts Page Component
 *
 * Admin UI for managing reusable prompts used by the RAG pipeline.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
    Button,
    Card,
    CardBody,
    CardHeader,
    DropdownMenu,
    Modal,
    Notice,
    SelectControl,
    Spinner,
    TextControl,
    TextareaControl,
    __experimentalGrid as Grid,
    __experimentalHeading as Heading,
} from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';

const DEFAULT_FORM = {
    title: '',
    prompt_type: 'system',
    status: 'draft',
    content: '',
    notes: '',
};

const promptStatusOptions = [
    { label: __('Draft', 'gregius-data'), value: 'draft' },
    { label: __('Published', 'gregius-data'), value: 'published' },
];

const promptTypeOptions = [
    { label: __('System', 'gregius-data'), value: 'system' },
    { label: __('Security', 'gregius-data'), value: 'security' },
];

const PromptsPage = () => {
    const [prompts, setPrompts] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingPrompt, setEditingPrompt] = useState(null);
    const [form, setForm] = useState(DEFAULT_FORM);

    const loadPrompts = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            const response = await apiFetch({ path: '/gg-data/v1/prompts' });
            setPrompts(response?.data || []);
        } catch (requestError) {
            setError(requestError.message || __('Failed to load prompts.', 'gregius-data'));
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        loadPrompts();
    }, [loadPrompts]);

    const resetForm = () => {
        setForm(DEFAULT_FORM);
        setEditingPrompt(null);
    };

    const openCreateModal = () => {
        resetForm();
        setError(null);
        setSuccess(null);
        setIsModalOpen(true);
    };

    const openEditModal = (prompt) => {
        setEditingPrompt(prompt);
        setForm({
            title: prompt.title || '',
            prompt_type: prompt.prompt_type || 'system',
            status: prompt.status || 'draft',
            content: prompt.content || '',
            notes: prompt.notes || '',
        });
        setError(null);
        setSuccess(null);
        setIsModalOpen(true);
    };

    const closeModal = () => {
        setIsModalOpen(false);
        resetForm();
    };

    const updateFormField = (field, value) => {
        setForm((prev) => ({
            ...prev,
            [field]: value,
        }));
    };

    const savePrompt = async () => {
        if (!form.title.trim() || !form.content.trim()) {
            setError(__('Title and content are required.', 'gregius-data'));
            return;
        }

        setIsSubmitting(true);
        setError(null);
        setSuccess(null);

        try {
            const request = {
                path: editingPrompt ? `/gg-data/v1/prompts/${editingPrompt.id}` : '/gg-data/v1/prompts',
                method: editingPrompt ? 'PUT' : 'POST',
                data: {
                    title: form.title,
                    prompt_type: form.prompt_type,
                    status: form.status,
                    content: form.content,
                    notes: form.notes,
                },
            };

            await apiFetch(request);
            await loadPrompts();

            setSuccess(
                editingPrompt
                    ? __('Prompt updated successfully.', 'gregius-data')
                    : __('Prompt created successfully.', 'gregius-data')
            );

            closeModal();
        } catch (requestError) {
            setError(requestError.message || __('Failed to save prompt.', 'gregius-data'));
        } finally {
            setIsSubmitting(false);
        }
    };

    const selectPrompt = async (promptId) => {
        setIsSubmitting(true);
        setError(null);
        setSuccess(null);

        try {
            await apiFetch({
                path: `/gg-data/v1/prompts/${promptId}/activate`,
                method: 'POST',
            });

            await loadPrompts();
            setSuccess(__('Prompt selected.', 'gregius-data'));
        } catch (requestError) {
            setError(requestError.message || __('Failed to select prompt.', 'gregius-data'));
        } finally {
            setIsSubmitting(false);
        }
    };

    const deletePrompt = async (promptId) => {
        if (!window.confirm(__('Delete this prompt?', 'gregius-data'))) {
            return;
        }

        setIsSubmitting(true);
        setError(null);
        setSuccess(null);

        try {
            await apiFetch({
                path: `/gg-data/v1/prompts/${promptId}`,
                method: 'DELETE',
            });

            await loadPrompts();
            setSuccess(__('Prompt deleted.', 'gregius-data'));
        } catch (requestError) {
            setError(requestError.message || __('Failed to delete prompt.', 'gregius-data'));
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="gg-data-page gg-data-prompts-page">
            <div style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                padding: '2rem 1.5rem 0',
                borderTop: '1px solid rgba(0, 0, 0, 0.1)',
            }}>
                <div style={{ display: 'flex', flexDirection: 'column' }}>
                    <Heading level={2}>{__('Prompts', 'gregius-data')}</Heading>
                    <p className="description">
                        {__('Manage RAG pipeline prompts.', 'gregius-data')}
                    </p>
                </div>
                <Button variant="primary" onClick={openCreateModal} disabled={isSubmitting}>
                    {__('Add Prompt', 'gregius-data')}
                </Button>
            </div>

            {error && (
                <Notice status="error" isDismissible={true} onRemove={() => setError(null)}>
                    <p>{error}</p>
                </Notice>
            )}

            {success && (
                <Notice status="success" isDismissible={true} onRemove={() => setSuccess(null)}>
                    <p>{success}</p>
                </Notice>
            )}

            {isLoading ? (
                <Card isRounded={false} style={{ marginTop: 16 }}>
                    <CardBody style={{ textAlign: 'center', padding: '32px' }}>
                        <Spinner />
                        <p style={{ marginTop: 12 }}>{__('Loading prompts...', 'gregius-data')}</p>
                    </CardBody>
                </Card>
            ) : prompts.length === 0 ? (
                <Card isRounded={false} style={{ marginTop: 16 }}>
                    <CardBody style={{ textAlign: 'center', padding: '60px 40px' }}>
                        <p style={{ color: '#646970', marginBottom: '24px' }}>
                            {__('No prompts yet. Create your first prompt to start versioning prompt strategies.', 'gregius-data')}
                        </p>
                        <Button variant="secondary" onClick={openCreateModal}>
                            {__('Add Your First Prompt', 'gregius-data')}
                        </Button>
                    </CardBody>
                </Card>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '2rem' }}>
                    {prompts.map((prompt) => (
                        <Card key={prompt.id} isRounded={false}>
                            <CardHeader>
                                <div style={{
                                    display: 'flex',
                                    flexWrap: 'wrap',
                                    gap: '1rem',
                                    justifyContent: 'space-between',
                                    alignItems: 'flex-start',
                                    width: '100%',
                                }}>
                                    <Heading level={3} style={{ margin: 0 }}>
                                        {prompt.title}
                                    </Heading>
                                    <DropdownMenu
                                        icon={moreVertical}
                                        label={__('Prompt actions', 'gregius-data')}
                                        controls={[
                                            {
                                                title: __('Edit Prompt', 'gregius-data'),
                                                onClick: () => openEditModal(prompt),
                                            },
                                            ...(prompt.status === 'published' && !prompt.selected ? [{
                                                title: __('Select Prompt', 'gregius-data'),
                                                onClick: () => selectPrompt(prompt.id),
                                            }] : []),
                                            {
                                                title: __('Delete Prompt', 'gregius-data'),
                                                onClick: () => deletePrompt(prompt.id),
                                                className: 'has-text-color has-vivid-red-color',
                                            },
                                        ]}
                                    />
                                </div>
                            </CardHeader>
                            <CardBody>
                                <Grid columns={3} gap={4}>
                                    <div>
                                        <strong>{__('Version:', 'gregius-data')}</strong>{' '}
                                        <span className="components-badge is-info">{`v${prompt.version || 1}`}</span>
                                    </div>
                                    <div>
                                        <strong>{__('Prompt Type:', 'gregius-data')}</strong>{' '}
                                        <span className="components-badge is-info">{prompt.prompt_type || 'system'}</span>
                                    </div>
                                    <div>
                                        <strong>{__('Status:', 'gregius-data')}</strong>{' '}
                                        <span className="components-badge is-info">{prompt.status}</span>
                                    </div>
                                    <div>
                                        <strong>{__('Activity:', 'gregius-data')}</strong>{' '}
                                        {prompt.status === 'published' ? (
                                            prompt.selected
                                                ? <span className="components-badge is-success">{__('Active', 'gregius-data')}</span>
                                                : <span className="components-badge">{__('Inactive', 'gregius-data')}</span>
                                        ) : null}
                                    </div>
                                    <div>
                                        <strong>{__('Modified:', 'gregius-data')}</strong>{' '}
                                        <span className="components-badge is-info">
                                            {prompt.modified ? new Date(prompt.modified).toLocaleString() : '-'}
                                        </span>
                                    </div>
                                    {prompt.is_factory && (
                                        <div>
                                            <strong>{__('Origin:', 'gregius-data')}</strong>{' '}
                                            <span className="components-badge is-secondary">{__('System Default', 'gregius-data')}</span>
                                        </div>
                                    )}
                                </Grid>
                                {prompt.content && (
                                    <div style={{ marginTop: '16px', whiteSpace: 'pre-wrap', color: '#3c434a' }}>
                                        {prompt.content}
                                    </div>
                                )}
                            </CardBody>
                        </Card>
                    ))}
                </div>
            )}

            {isModalOpen && (
                <Modal
                    title={editingPrompt ? __('Edit Prompt', 'gregius-data') : __('Add New Prompt', 'gregius-data')}
                    onRequestClose={closeModal}
                    className="gg-data-prompt-modal"
                >
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                        <TextControl
                            label={__('Title', 'gregius-data')}
                            value={form.title}
                            onChange={(value) => updateFormField('title', value)}
                            __next40pxDefaultSize={true}
                            __nextHasNoMarginBottom={true}
                        />
                        <SelectControl
                            label={__('Prompt Type', 'gregius-data')}
                            value={form.prompt_type}
                            options={promptTypeOptions}
                            onChange={(value) => updateFormField('prompt_type', value)}
                            __next40pxDefaultSize={true}
                            __nextHasNoMarginBottom={true}
                        />
                        <SelectControl
                            label={__('Status', 'gregius-data')}
                            value={form.status}
                            options={promptStatusOptions}
                            onChange={(value) => updateFormField('status', value)}
                            __next40pxDefaultSize={true}
                            __nextHasNoMarginBottom={true}
                        />
                        <TextareaControl
                            label={__('Prompt Content', 'gregius-data')}
                            value={form.content}
                            rows={10}
                            onChange={(value) => updateFormField('content', value)}
                            __nextHasNoMarginBottom={true}
                        />
                        <TextareaControl
                            label={__('Notes', 'gregius-data')}
                            value={form.notes}
                            rows={3}
                            onChange={(value) => updateFormField('notes', value)}
                            __nextHasNoMarginBottom={true}
                        />

                        <div style={{ display: 'flex', gap: '1rem', marginTop: '1rem', alignItems: 'center' }}>
                            <Button variant="primary" onClick={savePrompt} isBusy={isSubmitting}>
                                {editingPrompt ? __('Save Changes', 'gregius-data') : __('Create Prompt', 'gregius-data')}
                            </Button>
                            <Button variant="tertiary" onClick={closeModal} disabled={isSubmitting} style={{ marginLeft: 'auto' }}>
                                {__('Cancel', 'gregius-data')}
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default PromptsPage;
