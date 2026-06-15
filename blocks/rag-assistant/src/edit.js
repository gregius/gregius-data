/**
 * RAG Assistant Block - Editor Interface
 *
 * @package gregius-data
 */

import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
    PanelBody,
    TextControl,
    Spinner,
    SelectControl,
    ToggleControl,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import ChatInterface from './rag-assistant';

/**
 * Edit component for RAG Chat block.
 *
 * @param {Object} props Block properties.
 * @return {JSX.Element} Edit component.
 */
export default function Edit({ attributes, setAttributes, clientId }) {
    const {
        blockId,
        connectionId,
        embeddingModelKey,
        llmModelId,
        rewriteModelId,
        rerankModelId,
        promptId,
        securityPromptId,
        placeholder,
        enableStreaming,
        requireLogin,
    } = attributes;
    const [connections, setConnections] = useState([]);
    const [embeddingModels, setEmbeddingModels] = useState([]);
    const [llmModels, setLLMModels] = useState([]);
    const [rerankModels, setRerankModels] = useState([]);
    const [prompts, setPrompts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadingEmbeddings, setLoadingEmbeddings] = useState(false);
    const [error, setError] = useState('');

    const blockProps = useBlockProps({
        className: 'gg-rag-chat-block-editor',
    });

    const systemPrompts = prompts.filter((prompt) => (prompt.prompt_type || 'system') === 'system');
    const securityPrompts = prompts.filter((prompt) => prompt.prompt_type === 'security');

    // Store clientId as blockId attribute on mount.
    useEffect(() => {
        if (clientId && !blockId) {
            setAttributes({ blockId: `gg-rag-chat-${clientId}` });
        }
    }, [clientId, blockId, setAttributes]);

    // Load connections and LLM models on mount.
    useEffect(() => {
        loadInitialData();
    }, []);

    // Load embedding models when connection changes.
    useEffect(() => {
        if (connectionId) {
            loadEmbeddingModels(connectionId);
        }
    }, [connectionId]);

    /**
     * Load connections and global LLM models.
     */
    const loadInitialData = async () => {
        try {
            const [connectionsResponse, llmModelsResponse, rerankModelsResponse, promptsResponse] = await Promise.all([
                apiFetch({ path: '/gg-data/v1/connections', method: 'GET' }),
                apiFetch({ path: '/gg-data/v1/models?type=llm', method: 'GET' }),
                apiFetch({ path: '/gg-data/v1/models?type=rerank', method: 'GET' }),
                apiFetch({ path: '/gg-data/v1/prompts', method: 'GET' })
            ]);

            if (connectionsResponse && connectionsResponse.success && connectionsResponse.data) {
                // Convert object to array of connections with name and config
                const connectionsArray = Object.entries(connectionsResponse.data).map(([name, config]) => ({
                    name: name,
                    ...config,
                }));

                setConnections(connectionsArray);

                // Set first connection as default if none selected.
                if (!connectionId && connectionsArray.length > 0) {
                    setAttributes({ connectionId: connectionsArray[0].name });
                }
            }

            if (llmModelsResponse && llmModelsResponse.success && llmModelsResponse.data) {
                // Models are returned as an object keyed by ID
                const modelsArray = Object.values(llmModelsResponse.data);
                setLLMModels(modelsArray);

                // Set first LLM model as default if none selected
                if (!llmModelId && modelsArray.length > 0) {
                    setAttributes({ llmModelId: modelsArray[0].id });
                }
            }

            if (rerankModelsResponse && rerankModelsResponse.success && rerankModelsResponse.data) {
                const rerankArray = Object.values(rerankModelsResponse.data);
                setRerankModels(rerankArray);
            }

            if (promptsResponse && promptsResponse.success && promptsResponse.data) {
                const promptArray = Array.isArray(promptsResponse.data) ? promptsResponse.data : [];
                setPrompts(promptArray);
            }

        } catch (err) {
            console.error('Failed to load initial data:', err);
            setError(__('Failed to load configuration. Please check your setup.', 'gregius-data'));
        } finally {
            setLoading(false);
        }
    };

    /**
     * Load embedding models for selected connection.
     *
     * @param {string} connection Connection name.
     */
    const loadEmbeddingModels = async (connection) => {
        if (!connection) {
            return;
        }

        setLoadingEmbeddings(true);
        try {
            const response = await apiFetch({
                path: `/gg-data/v1/connections/${connection}/vectors/models`,
                method: 'GET'
            });

            if (response && response.success && response.data) {
                const modelsArray = Array.isArray(response.data) ? response.data : Object.values(response.data);
                setEmbeddingModels(modelsArray);

                // Set first embedding model as default if none selected or if current selection is not in list
                const currentModelExists = modelsArray.some(m => m.model_key === embeddingModelKey);
                if ((!embeddingModelKey || !currentModelExists) && modelsArray.length > 0) {
                    setAttributes({ embeddingModelKey: modelsArray[0].model_key || 'tfidf-300' });
                }
            }
        } catch (err) {
            console.error('Failed to load embedding models:', err);
            setEmbeddingModels([]);
        } finally {
            setLoadingEmbeddings(false);
        }
    };



    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Settings', 'gregius-data')} initialOpen={true}>
                    {loading ? (
                        <Spinner />
                    ) : (
                        <>
                            <SelectControl
                                label={__('Connection', 'gregius-data')}
                                value={connectionId}
                                options={[
                                    { label: __('Select a connection...', 'gregius-data'), value: '' },
                                    ...connections.map((conn) => ({
                                        label: conn.name,
                                        value: conn.name,
                                    })),
                                ]}
                                onChange={(value) => setAttributes({ connectionId: value })}
                                help={__('Select which PostgreSQL connection to use for RAG queries.', 'gregius-data')}
                                __next40pxDefaultSize={true}
                                __nextHasNoMarginBottom={true}
                            />

                            <SelectControl
                                label={__('Security Prompt', 'gregius-data')}
                                value={securityPromptId ? String(securityPromptId) : ''}
                                options={[
                                    { label: __('Use selected security prompt', 'gregius-data'), value: '' },
                                    ...securityPrompts.map((prompt) => ({
                                        label: prompt.title,
                                        value: String(prompt.id),
                                    })),
                                ]}
                                onChange={(value) => setAttributes({ securityPromptId: parseInt(value, 10) || 0 })}
                                __next40pxDefaultSize={true}
                                __nextHasNoMarginBottom={true}
                            />

                            <SelectControl
                                label={__('System Prompt', 'gregius-data')}
                                value={promptId ? String(promptId) : ''}
                                options={[
                                    { label: __('Use selected system prompt', 'gregius-data'), value: '' },
                                    ...systemPrompts.map((prompt) => ({
                                        label: prompt.title,
                                        value: String(prompt.id),
                                    })),
                                ]}
                                onChange={(value) => setAttributes({ promptId: parseInt(value, 10) || 0 })}
                                __next40pxDefaultSize={true}
                                __nextHasNoMarginBottom={true}
                            />

                            {loadingEmbeddings ? (
                                <Spinner />
                            ) : (
                                <SelectControl
                                    label={__('Embedding Model', 'gregius-data')}
                                    value={embeddingModelKey}
                                    options={[
                                        { label: __('Select an embedding model...', 'gregius-data'), value: '' },
                                        ...embeddingModels.map((model) => ({
                                            label: `${model.model_name || model.model_key} (${model.dimensions}D)`,
                                            value: model.model_key,
                                        })),
                                    ]}
                                    onChange={(value) => setAttributes({ embeddingModelKey: value })}
                                    help={__('Select which embedding model to use for semantic search.', 'gregius-data')}
                                    disabled={!connectionId}
                                    __next40pxDefaultSize={true}
                                    __nextHasNoMarginBottom={true}
                                />
                            )}

                            <SelectControl
                                label={__('Agentic Model', 'gregius-data')}
                                value={rewriteModelId}
                                options={[
                                    { label: __('Select a model...', 'gregius-data'), value: '' },
                                    ...llmModels.map((model) => ({
                                        label: `${model.model_name} (${model.provider})`,
                                        value: model.id,
                                    })),
                                ]}
                                onChange={(value) => setAttributes({ rewriteModelId: value })}
                                help={__('Routes queries and resolves conversational references. Use a fast, cheap model.', 'gregius-data')}
                                __next40pxDefaultSize={true}
                                __nextHasNoMarginBottom={true}
                            />

                            <SelectControl
                                label={__('Rerank Model', 'gregius-data')}
                                value={rerankModelId}
                                options={[
                                    { label: __('Disabled', 'gregius-data'), value: '' },
                                    ...rerankModels.map((model) => ({
                                        label: `${model.model_name} (${model.provider})`,
                                        value: model.id,
                                    })),
                                ]}
                                onChange={(value) => setAttributes({ rerankModelId: value })}
                                help={__('Optional. Reranks retrieved documents for better relevance.', 'gregius-data')}
                                __next40pxDefaultSize={true}
                                __nextHasNoMarginBottom={true}
                            />

                            <SelectControl
                                label={__('Answer Model', 'gregius-data')}
                                value={llmModelId}
                                options={[
                                    { label: __('Select a model...', 'gregius-data'), value: '' },
                                    ...llmModels.map((model) => ({
                                        label: `${model.model_name} (${model.provider})`,
                                        value: model.id,
                                    })),
                                ]}
                                onChange={(value) => setAttributes({ llmModelId: value })}
                                help={__('Generates the final response using retrieved context.', 'gregius-data')}
                                __next40pxDefaultSize={true}
                                __nextHasNoMarginBottom={true}
                            />

                            <TextControl
                                label={__('Placeholder Text', 'gregius-data')}
                                value={placeholder}
                                onChange={(value) => setAttributes({ placeholder: value })}
                                help={__('Placeholder text shown in the input field.', 'gregius-data')}
                                __next40pxDefaultSize={true}
                                __nextHasNoMarginBottom={true}
                            />

                            <ToggleControl
                                label={__('Enable Streaming', 'gregius-data')}
                                checked={enableStreaming !== false}
                                onChange={(value) => setAttributes({ enableStreaming: value })}
                                help={
                                    enableStreaming !== false
                                        ? __('Responses stream in real-time. May not work on all hosting environments.', 'gregius-data')
                                        : __('Responses load all at once. More compatible with restricted hosting.', 'gregius-data')
                                }
                                __nextHasNoMarginBottom={true}
                            />

                            <ToggleControl
                                label={__('Require Login', 'gregius-data')}
                                checked={requireLogin === true}
                                onChange={(value) => setAttributes({ requireLogin: value })}
                                help={
                                    requireLogin === true
                                        ? __('Block only renders for logged-in users. Guests see a sign-in prompt.', 'gregius-data')
                                        : __('Block allows guest rendering. Guest usage still depends on global RAG access policy.', 'gregius-data')
                                }
                                __nextHasNoMarginBottom={true}
                            />
                        </>
                    )}
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <ChatInterface
                    connectionId={connectionId}
                    embeddingModelKey={embeddingModelKey}
                    llmModelId={llmModelId}
                    rewriteModelId={rewriteModelId}
                    rerankModelId={rerankModelId}
                    promptId={promptId}
                    securityPromptId={securityPromptId}
                    placeholder={placeholder}
                />
            </div>
        </>
    );
}
