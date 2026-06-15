/**
 * RAG Chat Interface - Shared Component
 *
 * Used in both block editor and frontend.
 * Supports SSE streaming for real-time progress and thinking display.
 *
 * @package gregius-data
 */

import { __ } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';
import { useState, useEffect, useRef, useCallback, useMemo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { parseMarkdown } from './utils/markdown';

/**
 * Progress stage display names and icons.
 */
const PROGRESS_STAGES = {
    analyzing: { label: __('Analyzing your question...', 'gregius-data') },
    tool_selected: { label: __('Routing request...', 'gregius-data') },
    searching: { label: __('Searching knowledge base...', 'gregius-data') },
    found: { label: __('Found sources', 'gregius-data') },
    reranking: { label: __('Reranking results...', 'gregius-data') },
    generating: { label: __('Generating answer...', 'gregius-data') },
};

/**
 * Shared chat interface component.
 *
 * @param {Object} props Component properties.
 * @param {string} props.connectionId PostgreSQL connection ID.
 * @param {string} props.embeddingModelKey Embedding model key (e.g., 'tfidf-300').
 * @param {string} props.llmModelId LLM model ID for answer generation.
 * @param {string} props.rewriteModelId LLM model ID for query rewriting (optional).
 * @param {string} props.rerankModelId Rerank model ID for result reranking (optional).
 * @param {number} props.promptId Prompt post ID (0 = active/default prompt).
 * @param {number} props.securityPromptId Security prompt post ID (0 = active/default security prompt).
 * @param {string} props.placeholder Input placeholder text.
 * @param {boolean} props.useSSE Whether to use SSE streaming (default: true on frontend).
 * @return {JSX.Element} Chat interface component.
 */
export default function ChatInterface({
    connectionId,
    embeddingModelKey,
    llmModelId,
    rewriteModelId,
    rerankModelId,
    promptId = 0,
    securityPromptId = 0,
    placeholder,
    useSSE = true,
}) {
    const [isProcessing, setIsProcessing] = useState(false);
    const [testQuery, setTestQuery] = useState('');
    const [conversationHistory, setConversationHistory] = useState([]);
    const [error, setError] = useState('');
    const [progressStage, setProgressStage] = useState(null);
    const [progressData, setProgressData] = useState(null);
    const [streamingContent, setStreamingContent] = useState('');
    const [streamingReasoning, setStreamingReasoning] = useState('');
    const inputContainerRef = useRef(null);
    const conversationContainerRef = useRef(null);

    /**
     * Decode HTML entities from a string using the browser's built-in parser.
     *
     * Converts encoded entities like &#8217; (right single quotation mark),
     * &#8211; (en dash), &#8220; (left double quote), etc. to their
     * corresponding Unicode characters.
     *
     * @param {string} text Text potentially containing HTML entities.
     * @return {string} Decoded text.
     */
    const decodeTitle = (text) => {
        if (!text || typeof text !== 'string') {
            return text;
        }
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    };
    const requestAbortControllerRef = useRef(null);
    const stopRequestedRef = useRef(false);
    const thinkingContentRef = useRef('');
    const streamingContentRef = useRef('');
    const streamingReasoningRef = useRef('');
    const streamingThinkingContainerRef = useRef(null);
    const streamingMessageRef = useRef(null);
    const progressContainerRef = useRef(null);
    const hasScrolledForStreamingRef = useRef(false);

    // Session-scoped streaming disabled flag for auto-fallback on failure.
    // Resets on page refresh. If streaming fails, we disable it for the session.
    const streamingDisabledRef = useRef(false);
    const streamingStartTimeRef = useRef(null);

    // Generate a stable conversation_id on mount for interaction tracking.
    const conversationId = useMemo(() => crypto.randomUUID(), []);

    /**
     * Resolve citation source map for inline markers.
     *
     * Prefer backend-provided citation_sources aligned to [Source N] context
     * labels. Fall back to references list for backward compatibility.
     *
     * @param {Object} message Assistant message object.
     * @return {Array} Citation source list.
     */
    const getCitationSources = useCallback((message) => {
        const mapped = message?.metadata?.citation_sources;
        if (Array.isArray(mapped) && mapped.length > 0) {
            return mapped;
        }

        return Array.isArray(message?.sources) ? message.sources : [];
    }, []);

    /**
     * Extract cited source indices from message content.
     *
     * @param {string} content Assistant message markdown content.
     * @return {Set<number>} 1-based cited indices.
     */
    const getCitedSourceIndices = useCallback((content) => {
        const cited = new Set();

        if (typeof content !== 'string' || !content) {
            return cited;
        }

        const regex = /\[Source\s+(\d+)\]/gi;
        let match;

        while ((match = regex.exec(content)) !== null) {
            const index = Number.parseInt(match[1], 10);
            if (Number.isInteger(index) && index > 0) {
                cited.add(index);
            }
        }

        return cited;
    }, []);

    /**
     * Build references list aligned with citation marker numbering.
     *
     * @param {Object} message Assistant message object.
     * @return {Array} Reference entries with citation labels.
     */
    const getReferenceEntries = useCallback((message) => {
        const citationSources = getCitationSources(message);
        const citedIndices = getCitedSourceIndices(message?.content || '');
        const hasInlineCitations = citedIndices.size > 0;

        if (citationSources.length > 0) {
            const entries = [];
            const seenUrls = new Set();

            citationSources.forEach((source, idx) => {
                const citationIndex = idx + 1;

                if (hasInlineCitations && !citedIndices.has(citationIndex)) {
                    return;
                }

                const url = source?.url || '';
                if (!url || seenUrls.has(url)) {
                    return;
                }

                seenUrls.add(url);
                entries.push({
                    citationIndex,
                    title: decodeTitle(source?.title || __('Untitled source', 'gregius-data')),
                    url,
                });
            });

            if (entries.length > 0) {
                return entries;
            }
        }

        if (Array.isArray(message?.sources)) {
            return message.sources
                .filter((source) => source?.url)
                .map((source, idx) => ({
                    citationIndex: idx + 1,
                    title: decodeTitle(source?.title || __('Untitled source', 'gregius-data')),
                    url: source.url,
                }));
        }

        return [];
    }, [getCitationSources, getCitedSourceIndices]);

    /**
     * Detect whether message content already includes [Source N] markers.
     *
     * @param {string} content Message content.
     * @return {boolean} True when inline source markers are present.
     */
    const hasInlineCitationMarkers = useCallback((content) => {
        return typeof content === 'string' && /\[Source\s+\d+\]/i.test(content);
    }, []);

    /**
     * Scroll page to position block 1-2 rems from bottom of viewport.
     * Called when user clicks textarea or submit button.
     */
    const scrollBlockToFocus = useCallback(() => {
        if (!inputContainerRef.current) return;

        // Get the block container (parent of input)
        const blockElement = inputContainerRef.current.closest('.wp-block-gregius-data-rag-assistant');
        if (!blockElement) return;

        // Calculate target position: block bottom should be 2rem from viewport bottom
        const blockRect = blockElement.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const remInPixels = parseFloat(getComputedStyle(document.documentElement).fontSize);
        const bottomMargin = remInPixels * 2; // 2rem from bottom

        // Absolute position of block top from page top
        const blockAbsoluteTop = window.scrollY + blockRect.top;
        
        // Calculate scroll position so block bottom is at (viewportHeight - bottomMargin)
        const targetScrollTop = blockAbsoluteTop - (viewportHeight - blockRect.height - bottomMargin);

        window.scrollTo({
            top: targetScrollTop,
            behavior: 'smooth',
        });
    }, []);

    /**
     * Get messages array for API request.
     * Returns all prior exchanges in API format.
     *
     * @return {Array} Messages array for API.
     */
    const getMessagesForAPI = useCallback(() => {
        // Filter to only user and assistant messages (not errors)
        const validMessages = conversationHistory.filter(
            (msg) => msg.role === 'user' || msg.role === 'assistant'
        );

        // Convert to API format (role + content only)
        return validMessages.map((msg) => ({
            role: msg.role,
            content: msg.content,
        }));
    }, [conversationHistory]);

    // Auto-scroll conversation container to bottom when new messages arrive
    useEffect(() => {
        if (conversationContainerRef.current && conversationHistory.length > 0) {
            // Scroll to bottom of the conversation container
            setTimeout(() => {
                conversationContainerRef.current.scrollTop =
                    conversationContainerRef.current.scrollHeight;
            }, 0);
        }
    }, [conversationHistory]);

    // Auto-scroll conversation container during streaming
    useEffect(() => {
        if (conversationContainerRef.current && (streamingContent || streamingReasoning)) {
            // Scroll to bottom as content streams in
            setTimeout(() => {
                conversationContainerRef.current.scrollTop =
                    conversationContainerRef.current.scrollHeight;
            }, 0);
        }
    }, [streamingContent, streamingReasoning]);

    // Cleanup in-flight requests on unmount.
    useEffect(() => {
        return () => {
            if (requestAbortControllerRef.current) {
                requestAbortControllerRef.current.abort();
            }
        };
    }, []);

    /**
     * Reset transient progress and streaming state.
     */
    const resetProcessingState = useCallback(() => {
        requestAbortControllerRef.current = null;
        stopRequestedRef.current = false;
        streamingContentRef.current = '';
        streamingReasoningRef.current = '';
        thinkingContentRef.current = '';
        setStreamingContent('');
        setStreamingReasoning('');
        setIsProcessing(false);
        setProgressStage(null);
        setProgressData(null);
    }, []);

    /**
     * Persist any streamed partial response when the user stops generation.
     */
    const persistPartialResponse = useCallback(() => {
        const partialContent = streamingContentRef.current.trim();
        const partialReasoning =
            streamingReasoningRef.current.trim() || thinkingContentRef.current.trim();

        if (!partialContent && !partialReasoning) {
            return;
        }

        setConversationHistory((prev) => [
            ...prev,
            {
                role: 'assistant',
                content: partialContent,
                sources: [],
                metadata: {
                    stopped: true,
                },
                thinking: partialReasoning,
                timestamp: new Date().toISOString(),
            },
        ]);
    }, []);

    const normalizeRagResult = useCallback((result) => {
        if (!result || typeof result !== 'object') {
            return {
                answer: '',
                sources: [],
                metadata: {},
            };
        }

        const legacy = result.legacy && typeof result.legacy === 'object' ? result.legacy : {};

        const normalizedMetadata = result.metadata && typeof result.metadata === 'object'
            ? { ...result.metadata }
            : (legacy.metadata && typeof legacy.metadata === 'object' ? { ...legacy.metadata } : {});

        if (!normalizedMetadata.reasoning_content && typeof result.diagnostics?.reasoning_content === 'string') {
            normalizedMetadata.reasoning_content = result.diagnostics.reasoning_content;
        }

        if (result.request || result.intent || result.outcome) {
            normalizedMetadata.observability = {
                request: result.request || {},
                intent: result.intent || {},
                outcome: result.outcome || {},
                execution: result.execution || {},
                retrieval_summary: result.retrieval_summary || {},
                policy: result.policy || {},
                security: result.security || {},
                context: result.context || {},
                diagnostics: result.diagnostics || {},
            };
        }

        const normalizedReferences = Array.isArray(result.references)
            ? result.references
                .filter((reference) => reference?.url)
                .map((reference) => ({
                    title: decodeTitle(reference?.title || __('Untitled source', 'gregius-data')),
                    url: reference.url,
                }))
            : [];

        return {
            ...result,
            answer: typeof result.answer === 'string'
                ? result.answer
                : (typeof result.outcome?.answer === 'string'
                    ? result.outcome.answer
                    : (typeof legacy.answer === 'string'
                        ? legacy.answer
                        : (typeof legacy.response === 'string' ? legacy.response : ''))),
            sources: Array.isArray(result.sources)
                ? result.sources
                : (Array.isArray(result.outcome?.sources)
                    ? result.outcome.sources
                    : (Array.isArray(legacy.sources)
                        ? legacy.sources
                        : normalizedReferences)),
            metadata: normalizedMetadata,
        };
    }, []);

    /**
     * Handle error.
     *
     * @param {string} message Error message.
     */
    const handleError = useCallback((message) => {
        const errorMessage = {
            role: 'error',
            content: message || __('Failed to generate answer. Please check your settings.', 'gregius-data'),
            timestamp: new Date().toISOString(),
        };
        setConversationHistory((prev) => [...prev, errorMessage]);
        resetProcessingState();
    }, [resetProcessingState]);

    /**
     * Handle successful completion.
     *
     * @param {Object} result   RAG result data.
     * @param {string} thinking Optional thinking/reasoning content.
     */
    const handleComplete = useCallback((result, thinking = '') => {
        const normalizedResult = normalizeRagResult(result);
        const normalizedAnswer = (normalizedResult.answer || '').trim();

        if (!normalizedAnswer) {
            handleError(__('We could not render an answer from the response. Please try again.', 'gregius-data'));
            return;
        }

        const assistantMessage = {
            role: 'assistant',
            content: normalizedAnswer,
            sources: normalizedResult.sources || [],
            metadata: normalizedResult.metadata || {},
            thinking: thinking || normalizedResult.metadata?.reasoning_content || '',
            timestamp: new Date().toISOString(),
        };
        setConversationHistory((prev) => [...prev, assistantMessage]);
        resetProcessingState();
    }, [normalizeRagResult, handleError, resetProcessingState]);

    /**
     * Stop the current request and keep any streamed partial answer.
     */
    const handleStop = useCallback(() => {
        if (!isProcessing) {
            return;
        }

        stopRequestedRef.current = true;

        if (requestAbortControllerRef.current) {
            requestAbortControllerRef.current.abort();
        }

        persistPartialResponse();
        resetProcessingState();
    }, [isProcessing, persistPartialResponse, resetProcessingState]);

    /**
     * Handle SSE event data.
     *
     * @param {Object} data Event data.
     */
    const handleSSEEvent = useCallback(
        (data) => {
            switch (data.type) {
                case 'progress':
                    setProgressStage(data.stage);
                    setProgressData(data.data);
                    // Reset streaming content when starting a new generation.
                    if (data.stage === 'streaming' || data.stage === 'generating') {
                        streamingContentRef.current = '';
                        streamingReasoningRef.current = '';
                        setStreamingContent('');
                        setStreamingReasoning('');
                    }
                    break;

                case 'token':
                    // Append token to streaming content.
                    streamingContentRef.current += data.content;
                    setStreamingContent(streamingContentRef.current);
                    break;

                case 'reasoning_token':
                    // Append token to streaming reasoning.
                    streamingReasoningRef.current += data.content;
                    setStreamingReasoning(streamingReasoningRef.current);
                    break;

                case 'thinking':
                    // Store thinking content to attach to the message
                    thinkingContentRef.current = data.content;
                    break;

                case 'complete':
                    // Use server-filtered answer (NBA pills stripped) over raw streamed content.
                    const finalContent = data.result.answer || streamingContentRef.current;
                    const finalReasoning = data.result.metadata?.reasoning_content || streamingReasoningRef.current || thinkingContentRef.current || '';
                    
                    // Create a modified result with streamed content.
                    const finalResult = {
                        ...data.result,
                        answer: finalContent,
                    };
                    
                    handleComplete(finalResult, finalReasoning);
                    
                    // Reset streaming state.
                    streamingContentRef.current = '';
                    streamingReasoningRef.current = '';
                    thinkingContentRef.current = '';
                    setStreamingContent('');
                    setStreamingReasoning('');
                    break;

                case 'error':
                    handleError(data.message);
                    // Reset streaming state on error.
                    streamingContentRef.current = '';
                    streamingReasoningRef.current = '';
                    setStreamingContent('');
                    setStreamingReasoning('');
                    break;
            }
        },
        [handleComplete, handleError]
    );

    /**
     * Send message using REST API (fallback).
     *
     * @param {string} currentQuery The query to send.
     */
    const sendWithREST = useCallback(
        async (currentQuery) => {
            const abortController = new AbortController();
            requestAbortControllerRef.current = abortController;

            try {
                const response = await apiFetch({
                    path: `/gg-data/v1/rag/chat`,
                    method: 'POST',
                    signal: abortController.signal,
                    data: {
                        query: currentQuery,
                        connection_name: connectionId,
                        embedding_model_key: embeddingModelKey,
                        llm_model_id: llmModelId,
                        rewrite_model: rewriteModelId || '',
                        rerank_model_id: rerankModelId || '',
                        prompt_id: parseInt(promptId, 10) || 0,
                        security_prompt_id: parseInt(securityPromptId, 10) || 0,
                        messages: getMessagesForAPI(),
                        conversation_id: conversationId,
                    },
                });

                // API returns { success: true, data: {...} }
                const result = response.data || response;
                handleComplete(result);
            } catch (err) {
                if (err?.name === 'AbortError') {
                    return;
                }

                console.error('RAG request failed:', err);
                handleError(err.message);
            }
        },
        [
            connectionId,
            conversationId,
            embeddingModelKey,
            llmModelId,
            rewriteModelId,
            rerankModelId,
            promptId,
            securityPromptId,
            getMessagesForAPI,
            handleComplete,
            handleError,
        ]
    );

    /**
     * Log streaming failure for diagnostics.
     *
     * @param {string} errorType    Type of error (timeout, network, parse, etc.).
     * @param {number} elapsedMs    Time elapsed before failure.
     * @param {string} partialContent Any content received before failure.
     */
    const logStreamingFailure = useCallback(
        (errorType, elapsedMs, partialContent = '') => {
            // Log to console for debugging.
            console.warn('Streaming failure logged:', {
                errorType,
                elapsedMs,
                partialContentLength: partialContent.length,
                modelId: llmModelId,
            });

            // Fire AJAX request to log on server (non-blocking, fire-and-forget).
            const sseConfig = window.ggDataRagSSE;
            if (sseConfig?.ajaxUrl) {
                const formData = new FormData();
                formData.append('action', 'gg_data_log_streaming_failure');
                formData.append('nonce', sseConfig.nonce);
                formData.append('error_type', errorType);
                formData.append('elapsed_ms', elapsedMs.toString());
                formData.append('partial_content_length', partialContent.length.toString());
                formData.append('model_id', llmModelId);
                formData.append('connection_id', connectionId);

                fetch(sseConfig.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                }).catch(() => {
                    // Silently ignore logging failures.
                });
            }
        },
        [llmModelId, connectionId]
    );

    /**
     * Send message using SSE streaming with auto-fallback on failure.
     *
     * If streaming fails, the session is marked to use REST for remaining requests.
     *
     * @param {string} currentQuery The query to send.
     */
    const sendWithSSE = useCallback(
        (currentQuery) => {
            // Check if SSE config is available (localized from PHP)
            const sseConfig = window.ggDataRagSSE;
            if (!sseConfig?.ajaxUrl || !sseConfig?.nonce) {
                // Fall back to REST API if SSE not configured
                sendWithREST(currentQuery);
                return;
            }

            // Check if streaming was disabled due to previous failure.
            if (streamingDisabledRef.current) {
                console.info('Streaming disabled for session, using REST fallback.');
                sendWithREST(currentQuery);
                return;
            }

            // Record start time for failure logging.
            streamingStartTimeRef.current = Date.now();
            stopRequestedRef.current = false;

            // Reset thinking content
            thinkingContentRef.current = '';

            // Track received content for failure logging.
            let receivedContent = '';
            let hasReceivedData = false;

            // Build form data for POST request
            const formData = new FormData();
            formData.append('action', 'gg_data_rag_stream');
            formData.append('nonce', sseConfig.nonce);
            formData.append('query', currentQuery);
            formData.append('connection_name', connectionId);
            formData.append('embedding_model_key', embeddingModelKey);
            formData.append('llm_model_id', llmModelId);
            formData.append('rewrite_model', rewriteModelId || '');
            formData.append('rerank_model_id', rerankModelId || '');
            formData.append('prompt_id', (parseInt(promptId, 10) || 0).toString());
            formData.append('security_prompt_id', (parseInt(securityPromptId, 10) || 0).toString());
            formData.append('messages', JSON.stringify(getMessagesForAPI()));
            formData.append('conversation_id', conversationId);

            const abortController = new AbortController();
            requestAbortControllerRef.current = abortController;

            // Use fetch with ReadableStream for SSE-like behavior with POST
            fetch(sseConfig.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                signal: abortController.signal,
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';

                    const processStream = () => {
                        return reader.read().then(({ done, value }) => {
                            if (done) {
                                requestAbortControllerRef.current = null;

                                if (!stopRequestedRef.current) {
                                    setIsProcessing(false);
                                    setProgressStage(null);
                                }

                                return;
                            }

                            buffer += decoder.decode(value, { stream: true });
                            hasReceivedData = true;

                            // Process complete SSE events (lines starting with "data: ")
                            const lines = buffer.split('\n');
                            buffer = lines.pop() || ''; // Keep incomplete line in buffer

                            for (const line of lines) {
                                if (line.startsWith('data: ')) {
                                    try {
                                        const data = JSON.parse(line.slice(6));
                                        handleSSEEvent(data);

                                        // Track content for failure logging.
                                        if (data.type === 'token' && data.content) {
                                            receivedContent += data.content;
                                        }
                                    } catch (e) {
                                        // Ignore parse errors for incomplete data
                                    }
                                }
                            }

                            return processStream();
                        });
                    };

                    return processStream();
                })
                .catch((err) => {
                    if (err?.name === 'AbortError' || stopRequestedRef.current) {
                        return;
                    }

                    console.error('SSE stream failed:', err);

                    // Calculate elapsed time.
                    const elapsedMs = streamingStartTimeRef.current
                        ? Date.now() - streamingStartTimeRef.current
                        : 0;

                    // Determine error type.
                    let errorType = 'unknown';
                    if (err.name === 'TypeError' && err.message.includes('network')) {
                        errorType = 'network';
                    } else if (err.message.includes('timeout') || elapsedMs > 55000) {
                        errorType = 'timeout';
                    } else if (err.message.includes('HTTP error')) {
                        errorType = 'http_error';
                    } else if (!hasReceivedData) {
                        errorType = 'connection_blocked';
                    }

                    // Log the failure.
                    logStreamingFailure(errorType, elapsedMs, receivedContent);

                    // Disable streaming for the rest of this session.
                    streamingDisabledRef.current = true;
                    console.warn('Streaming disabled for session due to failure. Future requests will use REST.');

                    // If we haven't received any data, retry with REST immediately.
                    // If we received partial data, just show the error (can't retry safely).
                    if (!hasReceivedData || receivedContent.length === 0) {
                        // Reset state and retry with REST.
                        setProgressStage('analyzing');
                        sendWithREST(currentQuery);
                    } else {
                        // Show error with partial content info.
                        handleError(
                            __('Streaming interrupted. Please try again (will use standard mode).', 'gregius-data')
                        );
                    }
                });
        },
        [
            connectionId,
            embeddingModelKey,
            llmModelId,
            rewriteModelId,
            rerankModelId,
            promptId,
            conversationId,
            getMessagesForAPI,
            handleSSEEvent,
            handleError,
            sendWithREST,
            logStreamingFailure,
        ]
    );

    /**
     * Handle sending the message.
     */
    const handleTest = useCallback(() => {
        const currentQuery = testQuery.trim();

        if (!currentQuery) {
            setError(__('Please enter a test question.', 'gregius-data'));
            return;
        }

        if (!connectionId) {
            setError(__('Please select a connection first.', 'gregius-data'));
            return;
        }

        if (!embeddingModelKey) {
            setError(__('Please select an embedding model first.', 'gregius-data'));
            return;
        }

        if (!llmModelId) {
            setError(__('Please select an LLM model first.', 'gregius-data'));
            return;
        }

        // Add user message to conversation history
        const userMessage = {
            role: 'user',
            content: currentQuery,
            timestamp: new Date().toISOString(),
        };
        setConversationHistory((prev) => [...prev, userMessage]);

        setTestQuery(''); // Clear input immediately
        setIsProcessing(true);
        setError('');
        setProgressStage('analyzing');
        stopRequestedRef.current = false;

        // Use SSE if available and enabled, otherwise fall back to REST
        if (useSSE && window.ggDataRagSSE) {
            sendWithSSE(currentQuery);
        } else {
            sendWithREST(currentQuery);
        }
    }, [testQuery, connectionId, embeddingModelKey, llmModelId, useSSE, sendWithSSE, sendWithREST]);

    /**
     * Clear conversation history.
     */
    const handleClearHistory = () => {
        setConversationHistory([]);
        setError('');
    };

    /**
     * Get the current progress display.
     *
     * @return {Object} Progress display info.
     */
    const getProgressDisplay = () => {
        if (!progressStage) {
            return { label: __('Thinking...', 'gregius-data') };
        }

        const stage = PROGRESS_STAGES[progressStage];
        if (!stage) {
            return { label: progressData || __('Processing...', 'gregius-data') };
        }

        // Special handling for 'found' stage to show count.
        if (progressStage === 'found' && progressData?.count) {
            return {
                label: progressData.message || stage.label,
            };
        }

        return stage;
    };

    if (!connectionId) {
        return (
            <div className="gg-rag-empty">
                {__('Select a connection in the sidebar to enable this block.', 'gregius-data')}
            </div>
        );
    }

    if (!embeddingModelKey) {
        return (
            <div className="gg-rag-empty">
                {__('Select an embedding model in the sidebar to enable this block.', 'gregius-data')}
            </div>
        );
    }

    if (!llmModelId) {
        return (
            <div className="gg-rag-empty">
                {__('Select an LLM model in the sidebar to enable this block.', 'gregius-data')}
            </div>
        );
    }

    const progressDisplay = getProgressDisplay();
    const hasUserInput = testQuery.trim().length > 0;

    return (
        <>
            { /* Screen reader status announcer - separate from busy region */}
            <div
                className="screen-reader-text"
                role="status"
                aria-live="polite"
                aria-atomic="true"
            >
                {isProcessing && __('Processing your question, please wait.', 'gregius-data')}
            </div>

            { /* Conversation History */}
            <div
                ref={conversationContainerRef}
                className="gg-rag-conversation"
                role="log"
                aria-label={__('Chat conversation', 'gregius-data')}
                aria-live="polite"
                aria-busy={isProcessing}
            >
                {conversationHistory.map((message, index) => (
                    <div key={index} className={`gg-rag-message is-${message.role}`}>
                        {message.role === 'user' && (
                            <div className="gg-rag-message__content">
                                <p>{message.content}</p>
                            </div>
                        )}
                        {message.role === 'assistant' && (
                            <div className="gg-rag-message__content">
                                { /* Thinking/Reasoning Display */}
                                {message.thinking && (
                                    <details className="gg-rag-thinking-display" open>
                                        <summary>
                                            {__('Reasoning', 'gregius-data')}
                                        </summary>
                                        <div className="gg-rag-thinking-display__content">
                                            {message.thinking}
                                        </div>
                                    </details>
                                )}

                                <div
                                    dangerouslySetInnerHTML={{
                                        __html: parseMarkdown(message.content, getCitationSources(message)),
                                    }}
                                />

                                {getReferenceEntries(message).length > 0 && !hasInlineCitationMarkers(message.content) && (
                                    <div className="gg-rag-message__citations-inline">
                                        <span className="gg-rag-message__citations-inline-label">
                                            {__('Citations:', 'gregius-data')}
                                        </span>
                                        <span className="gg-rag-message__citations-inline-list">
                                            {getReferenceEntries(message).map((reference) => (
                                                <a
                                                    key={reference.citationIndex}
                                                    href={reference.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="gg-rag-citation"
                                                    title={reference.title}
                                                >
                                                    [{reference.citationIndex}]
                                                </a>
                                            ))}
                                        </span>
                                    </div>
                                )}

                                {getReferenceEntries(message).length > 0 && (
                                    <div className="gg-rag-message__sources">
                                        <div className="gg-rag-message__sources-title">
                                            {__('References:', 'gregius-data')}
                                        </div>
                                        <ul>
                                            {getReferenceEntries(message).map((reference) => (
                                                <li key={reference.citationIndex}>
                                                    <a
                                                        href={reference.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        [{reference.citationIndex}] {reference.title}
                                                    </a>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </div>
                        )}

                        {message.role === 'error' && (
                            <div className="gg-rag-message__content" role="alert">
                                <strong>{__('Error', 'gregius-data')}</strong>
                                <p>{message.content}</p>
                            </div>
                        )}
                    </div>
                ))}

                {isProcessing && (
                    <div
                        className="gg-rag-progress"
                        role="status"
                        aria-label={__('Processing your question', 'gregius-data')}
                        ref={progressContainerRef}
                    >
                        {/* Show streaming content if available */}
                        {(streamingContent || streamingReasoning) ? (
                            <div className="gg-rag-message is-assistant is-streaming" ref={streamingMessageRef}>
                                <div className="gg-rag-message__content">
                                    {/* Streaming Reasoning Display */}
                                    {streamingReasoning && (
                                        <details className="gg-rag-thinking-display" open>
                                            <summary>
                                                {__('Reasoning', 'gregius-data')}
                                            </summary>
                                            <div
                                                className="gg-rag-thinking-display__content"
                                                ref={streamingThinkingContainerRef}
                                            >
                                                {streamingReasoning}
                                            </div>
                                        </details>
                                    )}
                                    
                                    {/* Streaming Answer */}
                                    {streamingContent && (
                                        <div
                                            dangerouslySetInnerHTML={{
                                                __html: parseMarkdown(streamingContent),
                                            }}
                                        />
                                    )}
                                    
                                    {/* Streaming cursor indicator */}
                                    <span className="gg-rag-streaming-cursor" />
                                </div>
                            </div>
                        ) : (
                            /* Show spinner only when not streaming yet */
                            <>
                                <Spinner />
                                <span className="gg-rag-progress__label">
                                    {progressDisplay.label}
                                </span>
                            </>
                        )}
                    </div>
                )}
            </div>

            { /* Input at Bottom */}
            <div ref={inputContainerRef} className="gg-rag-input">
                <label htmlFor="gg-rag-chat-input" className="screen-reader-text">
                    {__('Ask a question', 'gregius-data')}
                </label>
                <textarea
                    id="gg-rag-chat-input"
                    name="gg-rag-chat-query"
                    placeholder={placeholder}
                    rows="3"
                    value={testQuery}
                    onChange={(e) => setTestQuery(e.target.value)}
                    onClick={scrollBlockToFocus}
                    aria-describedby={isProcessing ? 'gg-rag-status' : undefined}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            if (!isProcessing && hasUserInput) {
                                handleTest();
                            }
                        }
                    }}
                />
                <div className="gg-rag-input__actions">
                    {conversationHistory.length > 0 && (
                        <Button
                            variant="secondary"
                            onClick={handleClearHistory}
                            disabled={isProcessing}
                            aria-disabled={isProcessing}
                            aria-label={__('Start new conversation', 'gregius-data')}
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="20"
                                height="20"
                                fill="currentColor"
                                className="icon"
                                viewBox="0 0 16 16"
                            >
                                <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4" />
                            </svg>
                            <span className="screen-reader-text">{__('New', 'gregius-data')}</span>
                        </Button>
                    )}
                    <Button
                        variant="primary"
                        onClick={() => {
                            if (isProcessing) {
                                handleStop();
                                return;
                            }

                            scrollBlockToFocus();
                            handleTest();
                        }}
                        disabled={!isProcessing && !hasUserInput}
                        aria-disabled={!isProcessing && !hasUserInput}
                        aria-label={
                            isProcessing
                                ? __('Stop generating answer', 'gregius-data')
                                : __('Send question', 'gregius-data')
                        }
                    >
                        {isProcessing ? (
                            <svg
                                width="20"
                                height="20"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                xmlns="http://www.w3.org/2000/svg"
                                className="icon"
                            >
                                <rect x="5" y="5" width="10" height="10" rx="1.5" />
                            </svg>
                        ) : (
                            <svg
                                width="20"
                                height="20"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                xmlns="http://www.w3.org/2000/svg"
                                className="icon"
                            >
                                <path d="M8.99992 16V6.41407L5.70696 9.70704C5.31643 10.0976 4.68342 10.0976 4.29289 9.70704C3.90237 9.31652 3.90237 8.6835 4.29289 8.29298L9.29289 3.29298L9.36907 3.22462C9.76184 2.90427 10.3408 2.92686 10.707 3.29298L15.707 8.29298L15.7753 8.36915C16.0957 8.76192 16.0731 9.34092 15.707 9.70704C15.3408 10.0732 14.7618 10.0958 14.3691 9.7754L14.2929 9.70704L10.9999 6.41407V16C10.9999 16.5523 10.5522 17 9.99992 17C9.44764 17 8.99992 16.5523 8.99992 16Z" />
                            </svg>
                        )}
                        <span className="screen-reader-text">
                            {isProcessing ? __('Stop', 'gregius-data') : __('Ask', 'gregius-data')}
                        </span>
                    </Button>
                </div>
            </div>
        </>
    );
}
