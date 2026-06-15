/**
 * Search Settings Card Component
 *
 * Enables/disables PostgreSQL full-text search with field weighting.
 * Search settings are GLOBAL (stored under '__global__' scope)
 * because search is a site-wide feature, not per-database-connection.
 *
 * @since 2.0.0
 */

import {
	Card,
	CardHeader,
	CardBody,
	ToggleControl,
	SelectControl,
	RangeControl,
	Notice,
	Button,
	Spinner,
	__experimentalHeading as Heading
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

// Global connection name for search settings (matches PHP constant)
const SEARCH_SETTINGS_CONNECTION = '__global__';

const SearchSettingsCard = ({ connections = [] }) => {
	const [searchEnabled, setSearchEnabled] = useState(false);
	const [searchConnection, setSearchConnection] = useState('');
	const [loading, setLoading] = useState(true);
	const [saving, setSaving] = useState(false);
	const [error, setError] = useState(null);
	const [success, setSuccess] = useState(null);
	const [schemaVersion, setSchemaVersion] = useState(null);

	// Phase 2.0.3: Language status state
	const [languageStatus, setLanguageStatus] = useState(null);
	const [isCheckingLanguage, setIsCheckingLanguage] = useState(false);
	const [isUpdatingLanguage, setIsUpdatingLanguage] = useState(false);

	// Phase 5-6: Typo tolerance state
	const [typoTolerance, setTypoTolerance] = useState(false);
	const [similarityThreshold, setSimilarityThreshold] = useState(0.3);
	const [retrievalMode, setRetrievalMode] = useState('hybrid_default');
	const [telemetryExpensiveProbesEnabled, setTelemetryExpensiveProbesEnabled] = useState(false);
	const [extensionStatus, setExtensionStatus] = useState(null);
	const [loadingTypoSettings, setLoadingTypoSettings] = useState(false);
	const [savingTypoSettings, setSavingTypoSettings] = useState(false);

	// Embedding model state
	const [embeddingModel, setEmbeddingModel] = useState('tfidf-300');
	const [availableModels, setAvailableModels] = useState([]);
	const [loadingModels, setLoadingModels] = useState(false);
	const [savingEmbeddingModel, setSavingEmbeddingModel] = useState(false);

	// Load global search settings on mount
	useEffect(() => {
		loadGlobalSearchSettings();
	}, []);

	// Load connection-specific data when searchConnection changes
	useEffect(() => {
		if (searchConnection) {
			checkSchemaVersion();
			checkLanguageStatus();
			checkExtensionStatus();
			loadTypoToleranceSettings();
			loadAvailableModels();
		} else {
			setSchemaVersion(null);
			setLanguageStatus(null);
			setExtensionStatus(null);
			setAvailableModels([]);
		}
	}, [searchConnection]);

	// Load all global search settings
	const loadGlobalSearchSettings = async () => {
		try {
			setLoading(true);
			setError(null);

			// Load global search settings in parallel.
			const [enabledRes, connectionRes, modelRes, retrievalModeRes, observabilityRes] = await Promise.all([
				apiFetch({ path: `/gg-data/v1/settings/search/enabled?connection=${SEARCH_SETTINGS_CONNECTION}` }),
				apiFetch({ path: `/gg-data/v1/settings/search/connection?connection=${SEARCH_SETTINGS_CONNECTION}` }),
				apiFetch({ path: `/gg-data/v1/settings/search/embedding_model?connection=${SEARCH_SETTINGS_CONNECTION}` }),
				apiFetch({ path: `/gg-data/v1/settings/search/retrieval_mode?connection=${SEARCH_SETTINGS_CONNECTION}` }),
				apiFetch({ path: `/gg-data/v1/settings/search/observability_enabled?connection=${SEARCH_SETTINGS_CONNECTION}` }),
			]);

			if (enabledRes) {
				setSearchEnabled(enabledRes.value === '1' || enabledRes.value === true || enabledRes.value === 1);
			}
			if (connectionRes && connectionRes.value) {
				setSearchConnection(connectionRes.value);
			}
			if (modelRes && modelRes.value) {
				setEmbeddingModel(modelRes.value);
			}
			if (retrievalModeRes && retrievalModeRes.value) {
				setRetrievalMode(retrievalModeRes.value);
			}
			if (observabilityRes) {
				const observabilityEnabled = observabilityRes.value === '1' || observabilityRes.value === true || observabilityRes.value === 1;
				setTelemetryExpensiveProbesEnabled(observabilityEnabled);
			}
		} catch (err) {
			setError(err.message);
		} finally {
			setLoading(false);
		}
	};

	// Handle expensive telemetry probe toggle (global setting)
	const handleTelemetryToggle = async (newValue) => {
		try {
			setSaving(true);
			setError(null);
			setSuccess(null);

			const response = await apiFetch({
				path: `/gg-data/v1/settings/search/observability_enabled`,
				method: 'POST',
				data: {
					connection: SEARCH_SETTINGS_CONNECTION,
					value: newValue,
				},
			});

			if (response && response.updated) {
				setTelemetryExpensiveProbesEnabled(newValue);
				setSuccess(__('Observability settings updated successfully!', 'gregius-data'));
			}
		} catch (err) {
			setError(__('Failed to update observability setting: ', 'gregius-data') + err.message);
			setTelemetryExpensiveProbesEnabled(!newValue);
		} finally {
			setSaving(false);
		}
	};

	const checkSchemaVersion = async () => {
		if (!searchConnection) {
			setSchemaVersion(null);
			return;
		}

		try {
			const response = await apiFetch({
				path: `/gg-data/v1/search/status?connection=${searchConnection}`,
			});

			if (response && response.success) {
				// Check both schema_version setting and function_exists
				if (response.schema_version || (response.data && response.data.function_exists)) {
					setSchemaVersion(response.schema_version || '1.0.0');
				} else {
					setSchemaVersion(null);
				}
			}
		} catch (err) {
			// Schema doesn't exist yet, that's okay
			setSchemaVersion(null);
		}
	};

	// Phase 2.0.3: Check language status
	const checkLanguageStatus = async () => {
		if (!searchConnection) {
			setLanguageStatus(null);
			return;
		}

		try {
			setIsCheckingLanguage(true);
			const response = await apiFetch({
				path: `/gg-data/v1/search/language-status?connection=${searchConnection}`,
			});

			if (response && response.success) {
				setLanguageStatus(response);
			}
		} catch (err) {
			// Language status not available (schema not created yet), that's okay
			setLanguageStatus(null);
		} finally {
			setIsCheckingLanguage(false);
		}
	};

	// Phase 2.0.3: Update search language
	const handleUpdateLanguage = async () => {
		try {
			setIsUpdatingLanguage(true);
			setError(null);
			setSuccess(null);

			const response = await apiFetch({
				path: `/gg-data/v1/search/update-language`,
				method: 'POST',
				data: {
					connection: searchConnection,
				},
			});

			if (response && response.success) {
				setSuccess(__('Search language updated successfully!', 'gregius-data'));
				// Refresh language status
				await checkLanguageStatus();
			}
		} catch (err) {
			setError(__('Failed to update search language: ', 'gregius-data') + err.message);
		} finally {
			setIsUpdatingLanguage(false);
		}
	};

	// Phase 5-6: Check extension status
	const checkExtensionStatus = async () => {
		if (!searchConnection) {
			setExtensionStatus(null);
			return;
		}

		try {
			const response = await apiFetch({
				path: `/gg-data/v1/search/typo-tolerance-status?connection=${searchConnection}`,
			});

			if (response && response.success) {
				setExtensionStatus(response);
			} else {
				setExtensionStatus(null);
			}
		} catch (err) {
			setExtensionStatus(null);
		}
	};

	// Phase 5-6: Load typo tolerance settings (global)
	const loadTypoToleranceSettings = async () => {
		try {
			setLoadingTypoSettings(true);
			const response = await apiFetch({
				path: `/gg-data/v1/search/typo-tolerance?connection=${SEARCH_SETTINGS_CONNECTION}`,
			});

		if (response && response.success) {
			setTypoTolerance(response.typo_tolerance);
			setSimilarityThreshold(response.similarity_threshold);
			if (response.retrieval_mode) {
				setRetrievalMode(response.retrieval_mode);
			}
		}
	} catch (err) {
		// Use defaults on error
	} finally {
		setLoadingTypoSettings(false);
	}
};

	// Handle retrieval mode toggle (global setting).
	const handleRetrievalModeToggle = async (isMergeEnabled) => {
		const newMode = isMergeEnabled ? 'hybrid_default' : 'postgresql_only';

		try {
			setSavingTypoSettings(true);
			setError(null);
			setSuccess(null);

			const response = await apiFetch({
				path: `/gg-data/v1/search/typo-tolerance`,
				method: 'POST',
				data: {
					connection: SEARCH_SETTINGS_CONNECTION,
					retrieval_mode: newMode,
				},
			});

			if (response && response.success) {
				setRetrievalMode(newMode);
				setSuccess(__('Search retrieval mode updated!', 'gregius-data'));
			}
		} catch (err) {
			setError(__('Failed to update retrieval mode: ', 'gregius-data') + err.message);
		} finally {
			setSavingTypoSettings(false);
		}
	};

	// Phase 5-6: Handle typo tolerance toggle (global setting)
	const handleTypoToleranceToggle = async (newValue) => {
		try {
			setSavingTypoSettings(true);
			setError(null);
			setSuccess(null);

			const response = await apiFetch({
				path: `/gg-data/v1/search/typo-tolerance`,
				method: 'POST',
				data: {
					connection: SEARCH_SETTINGS_CONNECTION,
					typo_tolerance: newValue,
				},
			});

			if (response && response.success) {
				setTypoTolerance(newValue);
				setSuccess(__('Typo tolerance settings updated!', 'gregius-data'));
			}
		} catch (err) {
			setError(__('Failed to update typo tolerance: ', 'gregius-data') + err.message);
			setTypoTolerance(!newValue);
		} finally {
			setSavingTypoSettings(false);
		}
	};

	// Phase 5-6: Handle similarity threshold change (global setting)
	const handleSimilarityThresholdChange = async (newValue) => {
		try {
			setSavingTypoSettings(true);
			setError(null);

			const response = await apiFetch({
				path: `/gg-data/v1/search/typo-tolerance`,
				method: 'POST',
				data: {
					connection: SEARCH_SETTINGS_CONNECTION,
					similarity_threshold: parseFloat(newValue),
				},
			});

			if (response && response.success) {
				setSimilarityThreshold(parseFloat(newValue));
			}
		} catch (err) {
			setError(__('Failed to update similarity threshold: ', 'gregius-data') + err.message);
		} finally {
			setSavingTypoSettings(false);
		}
	};

	// Load available embedding models from the selected search connection
	const loadAvailableModels = async () => {
		if (!searchConnection) {
			setAvailableModels([{ model_key: 'tfidf-300', model_name: 'TF-IDF 300D' }]);
			return;
		}

		try {
			setLoadingModels(true);
			// Get active_models from vectors category for the selected connection
			const response = await apiFetch({
				path: `/gg-data/v1/settings/vectors/active_models?connection=${searchConnection}`,
			});

			if (response && response.value && Array.isArray(response.value)) {
				// Transform model keys into option format
				const models = response.value.map(modelKey => {
					// Convert model key to display name
					const displayName = modelKey
						.split('-')
						.map(word => word.charAt(0).toUpperCase() + word.slice(1))
						.join(' ')
						.replace('Tfidf', 'TF-IDF')
						.replace(/(\d+)D?$/, '$1D');
					
					return {
						model_key: modelKey,
						model_name: displayName
					};
				});
				setAvailableModels(models);
			} else {
				// Use default if no models configured
				setAvailableModels([{ model_key: 'tfidf-300', model_name: 'TF-IDF 300D' }]);
			}
		} catch (err) {
			// Use default if loading fails
			setAvailableModels([{ model_key: 'tfidf-300', model_name: 'TF-IDF 300D' }]);
		} finally {
			setLoadingModels(false);
		}
	};

	// Handle embedding model change (global setting)
	const handleEmbeddingModelChange = async (newModel) => {
		try {
			setSavingEmbeddingModel(true);
			setError(null);
			setSuccess(null);

			const response = await apiFetch({
				path: `/gg-data/v1/settings/search/embedding_model`,
				method: 'POST',
				data: {
					connection: SEARCH_SETTINGS_CONNECTION,
					value: newModel,
				},
			});

			if (response && response.updated) {
				setEmbeddingModel(newModel);
				setSuccess(__('Embedding model updated successfully!', 'gregius-data'));
			}
		} catch (err) {
			setError(__('Failed to update embedding model: ', 'gregius-data') + err.message);
		} finally {
			setSavingEmbeddingModel(false);
		}
	};

	// Handle search connection change
	const handleConnectionChange = async (newConnection) => {
		try {
			setSaving(true);
			setError(null);

			const response = await apiFetch({
				path: `/gg-data/v1/settings/search/connection`,
				method: 'POST',
				data: {
					connection: SEARCH_SETTINGS_CONNECTION,
					value: newConnection,
				},
			});

			if (response && response.updated) {
				setSearchConnection(newConnection);
			}
		} catch (err) {
			setError(__('Failed to update search connection: ', 'gregius-data') + err.message);
		} finally {
			setSaving(false);
		}
	};

	// Handle search toggle (global setting)
	const handleToggle = async (newValue) => {
		try {
			setSaving(true);
			setError(null);
			setSuccess(null);

			const response = await apiFetch({
				path: `/gg-data/v1/settings/search/enabled`,
				method: 'POST',
				data: {
					connection: SEARCH_SETTINGS_CONNECTION,
					value: newValue,
				},
			});

			if (response && response.updated) {
				setSearchEnabled(newValue);
				setSuccess(__('Search settings updated successfully!', 'gregius-data'));

				// Also save the current embedding model and connection when enabling search
				if (newValue) {
					if (embeddingModel) {
						await apiFetch({
							path: `/gg-data/v1/settings/search/embedding_model`,
							method: 'POST',
							data: {
								connection: SEARCH_SETTINGS_CONNECTION,
								value: embeddingModel,
							},
						});
					}
					if (searchConnection) {
						await apiFetch({
							path: `/gg-data/v1/settings/search/connection`,
							method: 'POST',
							data: {
								connection: SEARCH_SETTINGS_CONNECTION,
								value: searchConnection,
							},
						});
					}
				}
			}
		} catch (err) {
			setError(err.message);
			setSearchEnabled(!newValue);
		} finally {
			setSaving(false);
		}
	};

	// Loading state
	if (loading) {
		return (
			<Card isRounded={false} className="gg-search-settings-card">
				<CardHeader>
					<Heading level={3}>{__('Search Settings', 'gregius-data')}</Heading>
				</CardHeader>
				<CardBody>
					<div className="gg-search-settings-loading" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
						<Spinner />
						<p style={{ margin: 0 }}>{__('Loading search settings...', 'gregius-data')}</p>
					</div>
				</CardBody>
			</Card>
		);
	}

	// Format connection options for SelectControl
	const connectionOptions = [
		{ 
			label: __('Select a connection...', 'gregius-data'), 
			value: ''
		},
		...connections.map(conn => ({
			label: conn.is_default ? `${conn.name} (Default)` : conn.name,
			value: conn.name || '',
			disabled: conn.is_active === false
		}))
	];

	const retrievalMergeEnabled = retrievalMode !== 'postgresql_only';

	return (
		<Card isRounded={false} className="gg-search-settings-card">
			<CardHeader>
				<Heading level={3}>{__('Search Settings', 'gregius-data')}</Heading>
			</CardHeader>
			<CardBody>
				{error && (
					<Notice status="error" isDismissible onRemove={() => setError(null)}>
						{error}
					</Notice>
				)}

				{success && (
					<Notice status="success" isDismissible onRemove={() => setSuccess(null)}>
						{success}
					</Notice>
				)}

				{/* Phase 2.0.3: Language mismatch warning */}
				{languageStatus && languageStatus.mismatch && schemaVersion && (
					<>
						<Notice status="warning" isDismissible={false}>
							<div style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
								<span>
									{__('Search language mismatch detected', 'gregius-data')}
								</span>
								<span>
									{__('Your WordPress locale has changed. The search function is using ', 'gregius-data')}
									<strong>{languageStatus.stored}</strong>
									{__(' but your site is now set to ', 'gregius-data')}
									<strong>{languageStatus.current}</strong>
									{__(' (', 'gregius-data')}{languageStatus.locale}{__(').', 'gregius-data')}
								</span>
								<Button
									variant="secondary"
									onClick={handleUpdateLanguage}
									isBusy={isUpdatingLanguage}
									disabled={isUpdatingLanguage}
								>
									{__('Update Search Language', 'gregius-data')}
								</Button>
							</div>
						</Notice>
						<div style={{ marginTop: '20px' }} />
					</>
				)}
				<div className="gg-search-settings-content">
					<ToggleControl
						label={__('Enable Enhanced Search', 'gregius-data')}
						help={
							searchEnabled
								? __('Enhanced search is active site-wide with full-text search, field weighting (title matches rank higher), stemming ("running" finds "run", "runs", "ran"), stop word filtering, typo tolerance (if pg_trgm extension available), and vector-based semantic search (finds related content "vehicle" also finds posts about "car" and "automobile").', 'gregius-data')
								: __('Enable enhanced search site-wide with full-text search, typo tolerance, and vector-based semantic search to find related content by meaning.', 'gregius-data')
						}
						checked={searchEnabled}
						onChange={handleToggle}
						disabled={loading || saving}
						__nextHasNoMarginBottom={true}
					/>
					{searchEnabled && (
						<>
							<div style={{ marginTop: '16px' }} />
							<ToggleControl
								label={__('Enhance Observability', 'gregius-data')}
								help={telemetryExpensiveProbesEnabled
									? __('Detailed observability values are enabled. Calculations can increase response latency.', 'gregius-data')
									: __('Detailed observability values are disabled by default to keep response latency low.', 'gregius-data')}
								checked={telemetryExpensiveProbesEnabled}
								onChange={handleTelemetryToggle}
								disabled={loading || saving}
								__nextHasNoMarginBottom={true}
							/>
							<div style={{ marginTop: '16px' }} />
							<ToggleControl
								label={__('Dual Retrieval', 'gregius-data')}
								help={retrievalMergeEnabled
									? __('Running PostgreSQL semantic vector search merged with WordPress MySQL. Ensures full coverage for unsynced post types, increases search latency.', 'gregius-data')
									: __('Running pure PostgreSQL semantic vector search. Provides ultra-fast, high-accuracy results exclusively for synced content only.', 'gregius-data')}
								checked={retrievalMergeEnabled}
								onChange={handleRetrievalModeToggle}
								disabled={loadingTypoSettings || savingTypoSettings}
								__nextHasNoMarginBottom={true}
							/>
						</>
					)}
				{searchEnabled && (
					<>
						<div style={{ marginTop: '20px' }} />
						<SelectControl
							label={__('Connection', 'gregius-data')}
							value={searchConnection}
							options={connectionOptions}
							onChange={handleConnectionChange}
							help={__('Select the database connection for search queries.', 'gregius-data')}
							__nextHasNoMarginBottom={true}
						/>
						<div style={{ marginTop: '20px' }} />
						<SelectControl
							label={__('Embedding Model', 'gregius-data')}
							value={embeddingModel}
							options={[
								{ label: loadingModels ? __('Loading models...', 'gregius-data') : __('Select a model', 'gregius-data'), value: '', disabled: true },
								...availableModels.map(model => ({
									label: model.model_name || model.model_key,
									value: model.model_key,
								}))
							]}
							onChange={handleEmbeddingModelChange}
							disabled={loadingModels || savingEmbeddingModel}
							help={__('Select which embedding model to use for semantic search. Must match the vector embeddings in your database.', 'gregius-data')}
							__nextHasNoMarginBottom={true}
						/>
					</>
				)}
					{searchEnabled && extensionStatus && !extensionStatus.is_installed && (
						<>
							<div style={{ marginTop: '16px' }} />
							<Notice status="info" isDismissible={false}>
								<div style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
									<span>
										{__('pg_trgm extension not installed', 'gregius-data')}
									</span>
									<span>
										{__('Enhanced search is active but typo tolerance is unavailable. The pg_trgm extension is required for typo forgiveness features. Contact your hosting provider or database administrator to install it.', 'gregius-data')}
									</span>
								</div>
							</Notice>
						</>
					)}
				</div>
			</CardBody>
		</Card>
	);
};

export default SearchSettingsCard;
