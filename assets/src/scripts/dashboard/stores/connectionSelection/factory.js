/**
 * Connection Selection Store Factory
 * 
 * Creates isolated connection selection stores for different contexts (RAG, Search, Content/Sync).
 * Each store maintains its own selected connection with localStorage persistence.
 * 
 * @since 2.2.0
 */

import { registerStore } from '@wordpress/data';

/**
 * Factory function to create a connection selection store
 * 
 * @param {Object} config - Store configuration
 * @param {string} config.storeName - WordPress data store name (e.g., 'gg-data/rag-connection')
 * @param {string} config.storageKey - localStorage key (e.g., 'gg_data_rag_connection')
 * @param {string} config.actionPrefix - Action type prefix (e.g., 'RAG')
 * @returns {string} Store name
 */
export const createConnectionSelectionStore = ({ storeName, storageKey, actionPrefix }) => {
	const DEFAULT_STATE = {
		selectedConnectionId: null,
	};

	/**
	 * Load initial state from localStorage
	 */
	const getInitialState = () => {
		const stored = localStorage.getItem(storageKey);
		
		// Migration: Clear old numeric IDs (we now use connection names)
		if (stored && /^\d+$/.test(stored)) {
			localStorage.removeItem(storageKey);
			return { selectedConnectionId: null };
		}
		
		return {
			selectedConnectionId: stored && stored !== 'undefined' ? stored : null,
		};
	};

	/**
	 * Actions
	 */
	const actions = {
		setConnection(id) {
			// Persist to localStorage
			// Treat empty string as null/cleared selection
			if (id && id !== '' && id !== 'undefined') {
				localStorage.setItem(storageKey, id);
			} else {
				localStorage.removeItem(storageKey);
				id = null; // Normalize empty values to null
			}

			return {
				type: `SET_${actionPrefix}_CONNECTION`,
				id,
			};
		},

		clearConnection() {
			localStorage.removeItem(storageKey);
			return {
				type: `CLEAR_${actionPrefix}_CONNECTION`,
			};
		},
	};

	/**
	 * Selectors
	 */
	const selectors = {
		getConnectionId(state) {
			return state.selectedConnectionId;
		},

		hasConnection(state) {
			return state.selectedConnectionId !== null && state.selectedConnectionId !== '';
		},
	};

	/**
	 * Reducer
	 */
	const reducer = (state = getInitialState(), action) => {
		switch (action.type) {
			case `SET_${actionPrefix}_CONNECTION`:
				return {
					...state,
					selectedConnectionId: action.id,
				};

			case `CLEAR_${actionPrefix}_CONNECTION`:
				return {
					...state,
					selectedConnectionId: null,
				};

			default:
				return state;
		}
	};

	// Register the store
	registerStore(storeName, {
		reducer,
		actions,
		selectors,
	});

	return storeName;
};
