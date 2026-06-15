/**
 * Connections Store - Selectors
 * 
 * Selector functions for accessing connections state
 */

import { createSelector } from '@wordpress/data';

// Basic selectors
export const getConnections = (state) => state.connections;

export const isLoading = (state) => state.isLoading;

export const getError = (state) => state.error;

// Memoized selectors
export const getConnection = createSelector(
    (state, name) => state.connections[name],
    (state, name) => [state.connections, name]
);

export const getConnectionsList = createSelector(
    (state) => Object.entries(state.connections).map(([name, config]) => ({
        name,
        ...config,
    })),
    (state) => [state.connections]
);

export const hasConnections = createSelector(
    (state) => Object.keys(state.connections).length > 0,
    (state) => [state.connections]
);

export const getConnectionCount = createSelector(
    (state) => Object.keys(state.connections).length,
    (state) => [state.connections]
);

export const getActiveConnections = createSelector(
    (state) => Object.entries(state.connections)
        .filter(([, config]) => config.is_active)
        .reduce((acc, [name, config]) => ({ ...acc, [name]: config }), {}),
    (state) => [state.connections]
);

export const getDefaultConnection = createSelector(
    (state) => {
        const defaultEntry = Object.entries(state.connections)
            .find(([, config]) => config.is_default);
        return defaultEntry ? defaultEntry[0] : null;
    },
    (state) => [state.connections]
);

export const getConnectionByName = createSelector(
    (state, name) => {
        const entry = Object.entries(state.connections)
            .find(([connName]) => connName === name);
        return entry ? { name: entry[0], ...entry[1] } : null;
    },
    (state, name) => [state.connections, name]
);
