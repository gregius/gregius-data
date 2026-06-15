/**
 * API Utilities for Gregius PostgreSQL Dashboard
 * 
 * Provides WordPress REST API integration for the React dashboard.
 * All API calls follow WordPress authentication and nonce patterns.
 */

import apiFetch from '@wordpress/api-fetch';

/**
 * Base API path for Gregius PostgreSQL endpoints
 */
const API_BASE = '/gg-data/v1';

/**
 * Settings API endpoints
 */
export const settingsAPI = {
    /**
     * Get all settings
     * @param {Object} params - Query parameters
     * @returns {Promise} API response
     */
    getAll: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        const url = `${API_BASE}/settings${queryString ? `?${queryString}` : ''}`;
        return apiFetch({ path: url });
    },

    /**
     * Get single setting by ID
     * @param {number} id - Setting ID
     * @returns {Promise} API response
     */
    get: (id) => {
        return apiFetch({ path: `${API_BASE}/settings/${id}` });
    },

    /**
     * Create new setting
     * @param {Object} data - Setting data
     * @returns {Promise} API response
     */
    create: (data) => {
        return apiFetch({
            path: `${API_BASE}/settings`,
            method: 'POST',
            data
        });
    },

    /**
     * Update existing setting
     * @param {number} id - Setting ID
     * @param {Object} data - Updated setting data
     * @returns {Promise} API response
     */
    update: (id, data) => {
        return apiFetch({
            path: `${API_BASE}/settings/${id}`,
            method: 'PUT',
            data
        });
    },

    /**
     * Delete setting
     * @param {number} id - Setting ID
     * @returns {Promise} API response
     */
    delete: (id) => {
        return apiFetch({
            path: `${API_BASE}/settings/${id}`,
            method: 'DELETE'
        });
    },

    /**
     * Bulk update multiple settings
     * @param {Array} settings - Array of setting objects
     * @returns {Promise} API response
     */
    bulkUpdate: (settings) => {
        return apiFetch({
            path: `${API_BASE}/settings/bulk`,
            method: 'POST',
            data: { settings }
        });
    },

    /**
     * Get settings by category
     * @param {string} category - Setting category
     * @returns {Promise} API response
     */
    getByCategory: (category) => {
        return apiFetch({
            path: `${API_BASE}/settings?category=${encodeURIComponent(category)}`
        });
    },

    /**
     * Get settings by connection name
     * @param {string} connectionName - Connection name
     * @returns {Promise} API response
     */
    getByConnection: (connectionName) => {
        return apiFetch({
            path: `${API_BASE}/settings?connection_name=${encodeURIComponent(connectionName)}`
        });
    }
};

/**
 * Connection-specific API endpoints
 */
export const connectionAPI = {
    /**
     * Get all connections (settings grouped by connection_name)
     * @returns {Promise} API response
     */
    getAll: () => {
        return apiFetch({ path: `${API_BASE}/settings/connections` });
    },

    /**
     * Get specific connection settings
     * @param {string} connectionName - Connection name
     * @returns {Promise} API response
     */
    get: (connectionName) => {
        return apiFetch({
            path: `${API_BASE}/settings/connections/${encodeURIComponent(connectionName)}`
        });
    },

    /**
     * Update connection settings
     * @param {string} connectionName - Connection name
     * @param {Object} settings - Connection settings
     * @returns {Promise} API response
     */
    update: (connectionName, settings) => {
        return apiFetch({
            path: `${API_BASE}/settings/connections/${encodeURIComponent(connectionName)}`,
            method: 'PUT',
            data: { settings }
        });
    },

    /**
     * Delete connection and all its settings
     * @param {string} connectionName - Connection name
     * @returns {Promise} API response
     */
    delete: (connectionName) => {
        return apiFetch({
            path: `${API_BASE}/settings/connections/${encodeURIComponent(connectionName)}`,
            method: 'DELETE'
        });
    }
};

/**
 * Check API connection and WordPress REST API availability
 * @returns {Promise} API response with status
 */
export const checkApiConnection = async () => {
    try {
        // Simple test to verify WordPress REST API is available
        const response = await apiFetch({ path: '/wp/v2/users/me' });
        return {
            success: true,
            message: 'API connection successful',
            user: response
        };
    } catch (error) {
        return handleAPIError(error);
    }
};

/**
 * Error handling utility
 * @param {Error} error - API error
 * @returns {Object} Formatted error response
 */
export const handleAPIError = (error) => {
    
    // Extract meaningful error message
    let message = 'An unexpected error occurred';
    
    if (error.message) {
        message = error.message;
    } else if (error.data && error.data.message) {
        message = error.data.message;
    }
    
    return {
        success: false,
        message,
        error: error
    };
};

/**
 * Default API configuration
 */
export const API_CONFIG = {
    base: API_BASE,
    endpoints: {
        settings: `${API_BASE}/settings`,
        connections: `${API_BASE}/settings/connections`
    }
};
