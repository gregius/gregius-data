import { __, sprintf } from '@wordpress/i18n';

/**
 * Format timestamp as relative time (e.g., "Just now", "5 minutes ago", "2 hours ago")
 * 
 * Handles multiple input formats:
 * - ISO date strings (e.g., "2024-11-16T10:30:00Z")
 * - Unix timestamps (seconds since epoch)
 * - Date objects
 * 
 * Falls back to locale-formatted date for entries older than 7 days.
 * 
 * @param {string|number|Date} timestamp - Timestamp to format
 * @param {string|null} neverText - Text to show when timestamp is null/undefined (defaults to "Never")
 * @returns {string} Formatted relative time
 * 
 * @example
 * formatRelativeTime('2024-11-16T10:30:00Z') // "5 minutes ago"
 * formatRelativeTime(1700140800) // "2 hours ago"
 * formatRelativeTime(new Date()) // "Just now"
 * formatRelativeTime(null, 'Not available') // "Not available"
 */
export const formatRelativeTime = (timestamp, neverText = null) => {
	if (!timestamp) {
		return neverText !== null ? neverText : __('Never', 'gregius-data');
	}
	
	try {
		// Handle different input types
		let date;
		if (typeof timestamp === 'number') {
			// Unix timestamp (seconds since epoch)
			date = new Date(timestamp * 1000);
		} else if (typeof timestamp === 'string') {
			// ISO string or other date string
			date = new Date(timestamp);
		} else {
			// Assume Date object
			date = timestamp;
		}
		
		const now = new Date();
		const diffMs = now - date;
		const diffSecs = Math.floor(diffMs / 1000);
		const diffMins = Math.floor(diffSecs / 60);
		const diffHours = Math.floor(diffMins / 60);
		const diffDays = Math.floor(diffHours / 24);

		if (diffSecs < 60) {
			return __('Just now', 'gregius-data');
		}
		
		if (diffMins < 60) {
			return diffMins === 1 
				? __('1 minute ago', 'gregius-data')
				: sprintf(__('%d minutes ago', 'gregius-data'), diffMins);
		}
		
		if (diffHours < 24) {
			return diffHours === 1
				? __('1 hour ago', 'gregius-data')
				: sprintf(__('%d hours ago', 'gregius-data'), diffHours);
		}
		
		if (diffDays < 7) {
			return diffDays === 1
				? __('1 day ago', 'gregius-data')
				: sprintf(__('%d days ago', 'gregius-data'), diffDays);
		}

		// Fall back to formatted date for older entries
		return date.toLocaleString();
	} catch (e) {
		// If parsing fails, return the original value as string
		return String(timestamp);
	}
};
