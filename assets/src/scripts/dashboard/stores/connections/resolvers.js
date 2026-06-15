/**
 * Connections Store - Resolvers
 * 
 * Resolvers handle async data fetching for selectors
 */

import apiFetch from '@wordpress/api-fetch';
import { setConnections, setLoading, setError } from './actions';

// Resolver for getConnections selector
export function* getConnections() {
    // Get current state via select
    const currentConnections = yield { 
        type: 'SELECT',
        storeName: 'gg-data/connections',
        selectorName: 'getConnections'
    };
    
    // If already loaded, return cached data
    if (currentConnections && Object.keys(currentConnections).length > 0) {
        return;
    }
    
    yield setLoading(true);
    
    try {
        const response = yield {
            type: 'API_FETCH',
            request: {
                path: '/gg-data/v1/connections',
                method: 'GET',
            }
        };
        
        if (response.success) {
            yield setConnections(response.data || {});
        } else {
            throw new Error(response.message || 'Failed to fetch connections');
        }
    } catch (error) {
        yield setError(error.message);
        yield setConnections({});
    } finally {
        yield setLoading(false);
    }
}

// Resolver for getConnection selector
export function* getConnection(name) {
    // First ensure connections are loaded
    const connections = yield {
        type: 'SELECT',
        storeName: 'gg-data/connections',
        selectorName: 'getConnections'
    };
    
    // If no connections, trigger fetch
    if (!connections || Object.keys(connections).length === 0) {
        yield* getConnections();
    }
}

// Resolver for getConnectionsList selector
// This ensures data is fetched when using getConnectionsList
export function* getConnectionsList() {
    // Delegate to getConnections resolver since getConnectionsList
    // is just a transformed view of the same data
    yield* getConnections();
}

// Helper to invalidate cache and force refetch
export function* invalidateConnections() {
    yield setLoading(true);
    
    try {
        const response = yield {
            type: 'API_FETCH',
            request: {
                path: '/gg-data/v1/connections',
                method: 'GET',
            }
        };
        
        if (response.success) {
            yield setConnections(response.data || {});
        } else {
            throw new Error(response.message || 'Failed to fetch connections');
        }
    } catch (error) {
        yield setError(error.message);
    } finally {
        yield setLoading(false);
    }
}
