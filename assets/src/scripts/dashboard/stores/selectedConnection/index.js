/**
 * Selected Connection Store
 * 
 * WordPress Data Store for managing the currently selected database connection.
 * Used by Content and Sync pages.
 * Persists selection in localStorage.
 * 
 * @since 1.0.0
 */

import { createConnectionSelectionStore } from '../connectionSelection/factory';

const store = createConnectionSelectionStore({
	storeName: 'gg-data/selected',
	storageKey: 'gg_data_selected_connection',
	actionPrefix: 'SELECTED',
});

export default store;
