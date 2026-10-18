/**
 * Search Connection Store
 * 
 * Manages connection selection specifically for the Search page.
 * Independent from the Sync/Vectors connection selection.
 * 
 * @since 2.1.0
 */

import { createConnectionSelectionStore } from '../connectionSelection/factory';

const store = createConnectionSelectionStore({
	storeName: 'gregius-data/search-connection',
	storageKey: 'gregius_data_search_connection',
	actionPrefix: 'SEARCH',
});

export default store;
