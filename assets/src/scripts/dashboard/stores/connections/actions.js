/**
 * Connections Store - Actions
 * 
 * Action creators for connections store
 */

export const setConnections = (connections) => ({
    type: 'SET_CONNECTIONS',
    connections,
});

export const setLoading = (isLoading) => ({
    type: 'SET_LOADING',
    isLoading,
});

export const setError = (error) => ({
    type: 'SET_ERROR',
    error,
});

export const addConnection = (name, connection) => ({
    type: 'ADD_CONNECTION',
    name,
    connection,
});

export const updateConnectionData = (name, updates) => ({
    type: 'UPDATE_CONNECTION',
    name,
    updates,
});

export const removeConnection = (name) => ({
    type: 'DELETE_CONNECTION',
    name,
});

// Action creators for async operations (used by resolvers)
export function* createConnection(connectionData) {
    const { name, ...config } = connectionData;
    
    try {
        const response = yield {
            type: 'API_FETCH',
            request: {
                path: '/gg-data/v1/connections',
                method: 'POST',
                data: connectionData,
            },
        };
        
        if (response.success) {
            yield addConnection(name, response.data);
            return response.data;
        } else {
            throw new Error(response.message || 'Failed to create connection');
        }
    } catch (error) {
        yield setError(error.message);
        throw error;
    }
}

export function* updateConnection(name, updates) {
    try {
        const response = yield {
            type: 'API_FETCH',
            request: {
                path: `/gg-data/v1/connections/${name}`,
                method: 'PUT',
                data: updates,
            },
        };
        
        if (response.success) {
            yield updateConnectionData(name, response.data);
            return response.data;
        } else {
            throw new Error(response.message || 'Failed to update connection');
        }
    } catch (error) {
        yield setError(error.message);
        throw error;
    }
}

export function* deleteConnection(name) {
    try {
        const response = yield {
            type: 'API_FETCH',
            request: {
                path: `/gg-data/v1/connections/${name}`,
                method: 'DELETE',
            },
        };
        
        if (response.success) {
            yield removeConnection(name);
            return true;
        } else {
            throw new Error(response.message || 'Failed to delete connection');
        }
    } catch (error) {
        yield setError(error.message);
        throw error;
    }
}

export function* testConnection(name) {
    try {
        const response = yield {
            type: 'API_FETCH',
            request: {
                path: `/gg-data/v1/connections/${name}/test`,
                method: 'POST',
            },
        };
        
        return response;
    } catch (error) {
        yield setError(error.message);
        throw error;
    }
}

export function* getConnectionHealth(name) {
    try {
        const response = yield {
            type: 'API_FETCH',
            request: {
                path: `/gg-data/v1/connections/${name}/health`,
                method: 'GET',
            },
        };
        
        return response;
    } catch (error) {
        yield setError(error.message);
        throw error;
    }
}
