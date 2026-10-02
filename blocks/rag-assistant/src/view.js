/**
 * RAG Chat Block - Frontend View
 *
 * Handles React hydration on the frontend.
 * Note: Using regular script build instead of ES module due to WordPress dependency limitations.
 *
 * @package gregius-data
 */

import { render } from '@wordpress/element';
import ChatInterface from './rag-assistant';

/**
 * Initialize RAG Chat blocks on the frontend.
 */
window.addEventListener('DOMContentLoaded', () => {
	const containers = document.querySelectorAll('.wp-block-gregius-data-rag-assistant');

	if (containers.length === 0) {
		return;
	}

	containers.forEach((container) => {
		const connectionId = container.getAttribute('data-connection-id');
		const embeddingModelKey = container.getAttribute('data-embedding-model-key');
		const llmModelId = container.getAttribute('data-llm-model-id');
		const rewriteModelId = container.getAttribute('data-rewrite-model-id') || '';
		const rerankModelId = container.getAttribute('data-rerank-model-id') || '';
		const promptId = parseInt(container.getAttribute('data-prompt-id'), 10) || 0;
		const securityPromptId = parseInt(container.getAttribute('data-security-prompt-id'), 10) || 0;
		const placeholder = container.getAttribute('data-placeholder');
		const enableStreaming = container.getAttribute('data-enable-streaming') !== 'false';
		const guestAccessNonce = container.getAttribute('data-gg-guest-access-nonce') || '';
		const guestAccessBlockId = container.getAttribute('data-gg-block-id') || '';
		const guestAccessPostId = parseInt(container.getAttribute('data-current-post-id'), 10) || 0;

		// Render React component into container
		render(
			<ChatInterface
				connectionId={connectionId}
				embeddingModelKey={embeddingModelKey}
				llmModelId={llmModelId}
				rewriteModelId={rewriteModelId}
				rerankModelId={rerankModelId}
				promptId={promptId}
				securityPromptId={securityPromptId}

				placeholder={placeholder}
				useSSE={enableStreaming}
				guestAccessNonce={guestAccessNonce}
				guestAccessBlockId={guestAccessBlockId}
				guestAccessPostId={guestAccessPostId}
			/>,
			container
		);
	});
});
