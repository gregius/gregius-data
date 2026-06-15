/**
 * Settings Store
 * 
 * WordPress Data Store for managing plugin settings
 */

import { registerStore } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

const DEFAULT_STATE = {
    settings: {},
    isLoading: false,
    error: null,
};

const actions = {
    setSettings(settings) {
        return {
            type: 'SET_SETTINGS',
            settings,
        };
    },
    
    setLoading(isLoading) {
        return {
            type: 'SET_LOADING',
            isLoading,
        };
    },
    
    setError(error) {
        return {
            type: 'SET_ERROR',
            error,
        };
    },
    
    updateSettingData(key, value) {
        return {
            type: 'UPDATE_SETTING',
            key,
            value,
        };
    },
    
    removeSettingData(key) {
        return {
            type: 'DELETE_SETTING',
            key,
        };
    },
    
    *updateSetting(key, value) {
        try {
            const response = yield apiFetch({
                path: `/gg-data/v1/settings/${key}`,
                method: 'PUT',
                data: { setting_value: value },
            });
            
            if (response.success) {
                yield actions.updateSettingData(key, value);
                return true;
            } else {
                throw new Error(response.message || 'Failed to update setting');
            }
        } catch (error) {
            yield actions.setError(error.message);
            throw error;
        }
    },
    
    *deleteSetting(key) {
        try {
            const response = yield apiFetch({
                path: `/gg-data/v1/settings/${key}`,
                method: 'DELETE',
            });
            
            if (response.success) {
                yield actions.removeSettingData(key);
                return true;
            } else {
                throw new Error(response.message || 'Failed to delete setting');
            }
        } catch (error) {
            yield actions.setError(error.message);
            throw error;
        }
    },
    
    *addSetting(category, key, value) {
        try {
            const response = yield apiFetch({
                path: '/gg-data/v1/settings',
                method: 'POST',
                data: {
                    category,
                    setting_key: key,
                    setting_value: value,
                },
            });
            
            if (response.success) {
                yield actions.updateSettingData(key, value);
                return response;
            } else {
                throw new Error(response.message || 'Failed to add setting');
            }
        } catch (error) {
            yield actions.setError(error.message);
            throw error;
        }
    },
};

const selectors = {
    getSettings(state) {
        return state.settings;
    },
    
    getSetting(state, key, defaultValue = null) {
        return state.settings[key] !== undefined ? state.settings[key] : defaultValue;
    },
    
    isLoading(state) {
        return state.isLoading;
    },
    
    getError(state) {
        return state.error;
    },
    
    hasSettings(state) {
        return Object.keys(state.settings).length > 0;
    },
    
    getSettingsByCategory(state, category) {
        return Object.entries(state.settings)
            .filter(([key]) => key.startsWith(category + '_'))
            .reduce((acc, [key, value]) => ({ ...acc, [key]: value }), {});
    },
};

const resolvers = {
    *getSettings() {
        const settings = yield { type: 'GET_SETTINGS_FROM_STATE' };
        
        // If already loaded, return cached data
        if (Object.keys(settings).length > 0) {
            return;
        }
        
        yield actions.setLoading(true);
        
        try {
            const response = yield apiFetch({
                path: '/gg-data/v1/settings',
                method: 'GET',
            });
            
            if (response && response.settings) {
                yield actions.setSettings(response.settings);
            } else {
                yield actions.setSettings({});
            }
        } catch (error) {
            yield actions.setError(error.message);
            yield actions.setSettings({});
        } finally {
            yield actions.setLoading(false);
        }
    },
    
    *getSetting(key) {
        // Trigger getSettings if not loaded
        yield { type: 'RESOLVE_GET_SETTINGS' };
    },
};

const reducer = (state = DEFAULT_STATE, action) => {
    switch (action.type) {
        case 'SET_SETTINGS':
            return {
                ...state,
                settings: action.settings,
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
        
        case 'UPDATE_SETTING':
            return {
                ...state,
                settings: {
                    ...state.settings,
                    [action.key]: action.value,
                },
            };
        
        case 'DELETE_SETTING':
            const { [action.key]: deleted, ...remainingSettings } = state.settings;
            return {
                ...state,
                settings: remainingSettings,
            };
        
        case 'GET_SETTINGS_FROM_STATE':
            return state.settings;
        
        default:
            return state;
    }
};

const controls = {
    API_FETCH({ request }) {
        return window.wp.apiFetch(request);
    },
    GET_SETTINGS_FROM_STATE() {
        return { type: 'NOOP' };
    },
    RESOLVE_GET_SETTINGS() {
        return { type: 'NOOP' };
    },
};

// Register the store
registerStore('gg-data/settings', {
    reducer,
    actions,
    selectors,
    resolvers,
    controls,
});

export default 'gg-data/settings';
