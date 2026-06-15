/**
 * Search Connection Store
 * 
 * Manages connection selection specifically for the Search page.
 * Independent from Content/Sync connection selection.
 * 
 * @since 2.1.0
 */

import { createConnectionSelectionStore } from '../connectionSelection/factory';

const store = createConnectionSelectionStore({
	storeName: 'gg-data/search-connection',
	storageKey: 'gg_data_search_connection',
	actionPrefix: 'SEARCH',
});

export default store;
