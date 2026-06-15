/**
 * Search Page Component
 *
 * Manages PostgreSQL Full-Text Search configuration and health monitoring.
 * Cards now manage their own state and load settings from global settings.
 *
 * @since 2.0.0
 */

import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
	__experimentalHeading as Heading
} from '@wordpress/components';
import SearchSettingsCard from '../components/search/SearchSettingsCard';
import SearchHealthCard from '../components/search/SearchHealthCard';

const SearchPage = () => {
	// Get connections list for the settings card dropdown
	const { connections } = useSelect((select) => ({
		connections: select('gg-data/connections').getConnectionsList(),
	}), []);

	return (
		<div className="gg-data-page">
			<div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16, padding: '2rem 1.5rem 0', borderTop: '1px solid rgba(0, 0, 0, 0.1)' }}>
				<div style={{
					display: 'flex',
					flexWrap: 'wrap',
					gap: '1rem',
					justifyContent: 'space-between',
					alignItems: 'flex-start',
					width: '100%'
				}}>
					<div>
						<Heading level={2}>{__('Search', 'gregius-data')}</Heading>
					</div>
				</div>
			</div>

			<SearchHealthCard />
			<SearchSettingsCard connections={connections} />
		</div>
	);
};

export default SearchPage;
