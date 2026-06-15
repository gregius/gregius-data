/**
 * Connections Store
 * 
 * WordPress Data Store for managing PostgreSQL database connections
 */

import { registerStore } from '@wordpress/data';
import * as actions from './actions';
import * as selectors from './selectors';
import * as resolvers from './resolvers';

const DEFAULT_STATE = {
    connections: {},
    isLoading: false,
    error: null,
};

const reducer = (state = DEFAULT_STATE, action) => {
    switch (action.type) {
        case 'SET_CONNECTIONS':
            return {
                ...state,
                connections: action.connections,
                isLoading: false,
            };
        
        case 'SET_LOADING':
            return {
                ...state,
                isLoading: action.isLoading,
            };
        
        case 'SET_ERROR':
            return {
                ...state,
                error: action.error,
                isLoading: false,
            };
        
        case 'ADD_CONNECTION':
            return {
                ...state,
                connections: {
                    ...state.connections,
                    [action.name]: action.connection,
                },
            };
        
        case 'UPDATE_CONNECTION':
            return {
                ...state,
                connections: {
                    ...state.connections,
                    [action.name]: {
                        ...state.connections[action.name],
                        ...action.updates,
                    },
                },
            };
        
        case 'DELETE_CONNECTION':
            const { [action.name]: deleted, ...remainingConnections } = state.connections;
            return {
                ...state,
                connections: remainingConnections,
            };
        
        default:
            return state;
    }
};

// Controls for handling generator actions
const controls = {
    API_FETCH({ request }) {
        return window.wp.apiFetch(request);
    },
    SELECT({ storeName, selectorName, args = [] }) {
        return window.wp.data.select(storeName)[selectorName](...args);
    },
};

// Register the store
registerStore('gg-data/connections', {
    reducer,
    actions,
    selectors,
    resolvers,
    controls,
});

export default 'gg-data/connections';
