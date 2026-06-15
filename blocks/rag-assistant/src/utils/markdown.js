/**
 * Markdown Utility
 *
 * Provides comprehensive Markdown parsing using marked library with DOMPurify
 * sanitization for XSS protection.
 * Handles all Markdown features including tables, task lists, images, etc.
 *
 * @package gregius-data
 */

import DOMPurify from 'dompurify';
import { marked } from 'marked';

/**
 * Configure marked options for safe, GitHub-flavored Markdown.
 */
marked.setOptions({
	breaks: true, // Convert \n to <br>
	gfm: true, // GitHub Flavored Markdown (tables, task lists, strikethrough)
	headerIds: false, // Don't add IDs to headings (cleaner output)
	mangle: false, // Don't mangle email addresses
});

/**
 * DOMPurify configuration for safe HTML output.
 * Allows common formatting elements while blocking scripts and dangerous attributes.
 */
const PURIFY_CONFIG = {
	ALLOWED_TAGS: [
		// Text formatting
		'p',
		'br',
		'strong',
		'b',
		'em',
		'i',
		'u',
		's',
		'del',
		'ins',
		'mark',
		'sub',
		'sup',
		// Headings
		'h1',
		'h2',
		'h3',
		'h4',
		'h5',
		'h6',
		// Lists
		'ul',
		'ol',
		'li',
		// Code
		'code',
		'pre',
		// Links (href sanitized by DOMPurify by default)
		'a',
		// Tables
		'table',
		'thead',
		'tbody',
		'tr',
		'th',
		'td',
		// Other
		'blockquote',
		'hr',
		'div',
		'span',
	],
	ALLOWED_ATTR: [
		'href',
		'title',
		'target',
		'rel',
		'class',
		'id',
		// Table attributes
		'colspan',
		'rowspan',
		'scope',
	],
	// Ensure links open safely
	ADD_ATTR: ['target', 'rel'],
	// Force safe link attributes
	FORBID_ATTR: ['style', 'onclick', 'onerror', 'onload'],
	// Allow data URIs for code highlighting but not scripts
	ALLOW_DATA_ATTR: false,
};

/**
 * Parse Markdown text to sanitized HTML.
 *
 * Supports full Markdown specification including:
 * - Headings, bold, italic, strikethrough
 * - Ordered and unordered lists (including nested)
 * - Code blocks with syntax highlighting classes
 * - Inline code
 * - Links (sanitized)
 * - Tables
 * - Task lists
 * - Blockquotes
 * - Horizontal rules
 *
 * Security: All output is sanitized via DOMPurify to prevent XSS attacks.
 *
 * @param {string} markdown - Markdown text to parse.
 * @return {string} Sanitized HTML string.
 */
export const parseMarkdown = (markdown, sources = []) => {
	if (!markdown || typeof markdown !== 'string') {
		return '';
	}

	const normalizedSources = Array.isArray(sources) ? sources : [];

	// Convert inline citation markers like [Source 1] into clickable superscript links.
	// If a marker index has no matching source, keep the original text.
	const markdownWithCitations = markdown.replace(/\[Source\s+(\d+)\]/gi, (match, indexText) => {
		const sourceIndex = Number.parseInt(indexText, 10) - 1;
		const source = normalizedSources[sourceIndex];

		if (!source || !source.url) {
			return '';
		}

		const safeLabel = Number.isFinite(sourceIndex + 1) ? sourceIndex + 1 : indexText;
		return `<sup class="gg-rag-citation"><a href="${source.url}" target="_blank" rel="noopener noreferrer" title="${source.title || `Source ${safeLabel}`}">[${safeLabel}]</a></sup>`;
	});

	try {
		const rawHtml = marked.parse(markdownWithCitations);
		// Sanitize HTML to prevent XSS attacks from AI-generated content.
		const sanitizedHtml = DOMPurify.sanitize(rawHtml, PURIFY_CONFIG);

		// Remove duplicate inline citations that point to the same URL within the
		// same text block (paragraph/list item) to reduce visual noise.
		if (typeof document !== 'undefined') {
			const container = document.createElement('div');
			container.innerHTML = sanitizedHtml;

			container.querySelectorAll('p, li').forEach((node) => {
				const seenUrls = new Set();
				node.querySelectorAll('sup.gg-rag-citation a').forEach((anchor) => {
					const href = anchor.getAttribute('href') || '';
					if (!href) {
						return;
					}

					if (seenUrls.has(href)) {
						const sup = anchor.closest('sup.gg-rag-citation');
						if (sup) {
							sup.remove();
						}
						return;
					}

					seenUrls.add(href);
				});
			});

			return container.innerHTML;
		}

		return sanitizedHtml;
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error('Markdown parsing error:', error);
		// Fallback: return escaped text
		return `<p>${markdown.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</p>`;
	}
};
