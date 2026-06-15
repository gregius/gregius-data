/**
 * Main App Component for Gregius PostgreSQL Dashboard
 * 
 * Root React component that manages the overall dashboard structure,
 * routing, and state management.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Card,
    CardBody,
    CardHeader,
    TabPanel,
    Spinner,
    Notice,
    Flex,
    FlexItem,
    __experimentalHeading as Heading
} from '@wordpress/components';

// Import stores (this registers them with WordPress data)
import '../stores/connections';
import '../stores/selectedConnection'; // Shared state for Content/Sync/Vector pages
import '../stores/searchConnection'; // Independent state for Search page
import '../stores/settings';
import '../stores/logs';

// Import page components
import ConnectionsPage from '../pages/ConnectionsPage';
import SearchPage from '../pages/SearchPage';
import ModelsPage from '../pages/ModelsPage';
import VectorsPage from '../pages/VectorsPage';
import SyncPage from '../pages/SyncPage';
import PromptsPage from '../pages/PromptsPage';
import LogsPage from '../pages/LogsPage';
import GregiusLogo from './GregiusLogo';


// Import utilities
import { useSelect } from '@wordpress/data';
import { checkApiConnection } from '../utils/api';

const App = () => {
    const [isLoading, setIsLoading] = useState(true);
    const [apiStatus, setApiStatus] = useState(null);
    const [activeTab, setActiveTab] = useState('sync');

    // Use WordPress data store for settings
    const { settings, isLoadingSettings, settingsError } = useSelect((select) => ({
        settings: select('gg-data/settings').getSettings(),
        isLoadingSettings: select('gg-data/settings').isLoading(),
        settingsError: select('gg-data/settings').getError(),
    }), []);

    useEffect(() => {
        // Listen for custom navigation events (e.g., from SearchSettingsCard)
        const handleNavigateToTab = (event) => {
            if (event.detail && event.detail.tab) {
                setActiveTab(event.detail.tab);
            }
        };

        window.addEventListener('gg-navigate-to-tab', handleNavigateToTab);

        // Initialize dashboard
        const initializeDashboard = async () => {
            try {
                // Check REST API connection
                const apiCheck = await checkApiConnection();
                setApiStatus(apiCheck);

                setIsLoading(false);
            } catch (error) {
                setApiStatus({ success: false, message: error.message });
                setIsLoading(false);

                // Signal React load failure
                if (window.jQuery) {
                    window.jQuery(document).trigger('gg-data-react-failed');
                }
            }
        };

        initializeDashboard();

        // Cleanup event listener
        return () => {
            window.removeEventListener('gg-navigate-to-tab', handleNavigateToTab);
        };
    }, []);

    // Signal successful React load when dashboard has finished loading
    useEffect(() => {
        if (!isLoading && window.jQuery) {
            // React dashboard has loaded successfully, regardless of API status
            window.jQuery(document).trigger('gg-data-react-loaded');
        }
    }, [isLoading]);

    // Tab configuration
    const tabs = [
        {
            name: 'models',
            title: __('Models', 'gregius-data'),
            className: 'tab-models',
            component: ModelsPage
        },
        {
            name: 'connections',
            title: __('Connections', 'gregius-data'),
            className: 'tab-connections',
            component: ConnectionsPage
        },
        {
            name: 'sync',
            title: __('Sync', 'gregius-data'),
            className: 'tab-sync',
            component: SyncPage
        },
        {
            name: 'vectors',
            title: __('Vectors', 'gregius-data'),
            className: 'tab-vectors',
            component: VectorsPage
        },
        {
            name: 'prompts',
            title: __('Prompts', 'gregius-data'),
            className: 'tab-prompts',
            component: PromptsPage
        },
        {
            name: 'search',
            title: __('Search', 'gregius-data'),
            className: 'tab-search',
            component: SearchPage
        },
        {
            name: 'logs',
            title: __('Logs', 'gregius-data'),
            className: 'tab-logs',
            component: LogsPage
        }
    ];

    // Show loading state
    if (isLoading) {
        return (
            <Flex direction="column" align="center" justify="center" gap={4}>
                <FlexItem>
                    <Spinner style={{ width: '30px', height: '30px' }} />
                </FlexItem>
                <FlexItem>
                    <p>{__('Initializing Gregius Data Dashboard...', 'gregius-data')}</p>
                </FlexItem>
            </Flex>
        );
    }

    // Show API connection error
    if (apiStatus && !apiStatus.success) {
        return (
            <div className="gg-data-dashboard-error">
                <Notice
                    status="error"
                    isDismissible={false}
                >
                    <h3>{__('REST API Connection Failed', 'gregius-data')}</h3>
                    <p>{apiStatus.message}</p>
                    <p>{__('Please ensure the plugin is properly activated and the REST API endpoints are accessible.', 'gregius-data')}</p>
                </Notice>
            </div>
        );
    }


    return (
        <div className="gg-data-react-dashboard gg-data-react-app">
            {/* Main dashboard content */}
            <Card isRounded={false} className="gg-data-dashboard-main">
                <CardHeader style={{ flexWrap: 'wrap' }}>
                        <Heading level={1} style={{ padding: 0, lineHeight: 1, display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                            <GregiusLogo />
                            <svg
                                className="gg-data-logo-svg"
                                version="1.1"
                                id="Layer_1"
                                xmlns="http://www.w3.org/2000/svg"
                                xmlnsXlink="http://www.w3.org/1999/xlink"
                                x="0px"
                                y="0px"
                                width="28px"
                                height="28px"
                                viewBox="0 0 32 32"
                                enableBackground="new 0 0 32 32"
                                xmlSpace="preserve"
                                aria-hidden="true"
                                focusable="false"
                            >
                                <g>
                                    <g>
                                        <linearGradient id="ggDataDashboardTitleSvgGradient" gradientUnits="userSpaceOnUse" x1="13.665" y1="11.2705" x2="27.9102" y2="11.2705">
                                            <stop offset="0" style={{ stopColor: '#00A651' }}>
                                                <animate
                                                    attributeName="stop-color"
                                                    values="#00A651;#58C6D1;#F1667C;#00A651"
                                                    dur="8s"
                                                    repeatCount="indefinite"
                                                />
                                            </stop>
                                            <stop offset="0.021" style={{ stopColor: '#00A85B' }} />
                                            <stop offset="0.0699" style={{ stopColor: '#00AF7A' }} />
                                            <stop offset="0.1201" style={{ stopColor: '#00B695' }} />
                                            <stop offset="0.1707" style={{ stopColor: '#30BCAB' }} />
                                            <stop offset="0.222" style={{ stopColor: '#46C0BD' }} />
                                            <stop offset="0.274" style={{ stopColor: '#52C4C9' }}>
                                                <animate
                                                    attributeName="stop-color"
                                                    values="#52C4C9;#ED683C;#00AF7A;#52C4C9"
                                                    dur="8s"
                                                    begin="-2s"
                                                    repeatCount="indefinite"
                                                />
                                            </stop>
                                            <stop offset="0.3275" style={{ stopColor: '#58C6D1' }}>
                                                <animate
                                                    attributeName="stop-color"
                                                    values="#58C6D1;#ED1162;#00A85B;#58C6D1"
                                                    dur="8s"
                                                    begin="-4s"
                                                    repeatCount="indefinite"
                                                />
                                            </stop>
                                            <stop offset="0.3843" style={{ stopColor: '#5AC7D4' }} />
                                            <stop offset="1" style={{ stopColor: '#F1667C' }}>
                                                <animate
                                                    attributeName="stop-color"
                                                    values="#F1667C;#00B695;#EF4B70;#F1667C"
                                                    dur="8s"
                                                    begin="-1s"
                                                    repeatCount="indefinite"
                                                />
                                            </stop>
                                            <stop offset="1" style={{ stopColor: '#EF4B70' }} />
                                            <stop offset="1" style={{ stopColor: '#ED1162' }} />
                                            <stop offset="1" style={{ stopColor: '#ED683C' }} />
                                        </linearGradient>
                                        <path fill="url(#ggDataDashboardTitleSvgGradient)" d="M16.074,18.394c-0.616,0-1.233-0.236-1.704-0.705c-0.94-0.942-0.94-2.468,0-3.409l9.427-9.427 c0.941-0.941,2.466-0.941,3.408,0c0.94,0.941,0.94,2.466,0,3.407l-9.428,9.428C17.307,18.157,16.69,18.394,16.074,18.394z" />
                                    </g>
                                    <g>
                                        <defs>
                                            <path id="ggDataDashboardTitleSvgPath1" d="M15.619,0.289l0.335-0.026c1.325-0.102,2.483,0.89,2.585,2.216c0.102,1.321-0.882,2.475-2.2,2.584 l-0.146,0.012L15.94,5.096L15.619,0.289z" />
                                        </defs>
                                        <clipPath id="ggDataDashboardTitleSvgClip1">
                                            <use xlinkHref="#ggDataDashboardTitleSvgPath1" overflow="visible" />
                                        </clipPath>
                                        <g transform="matrix(1 0 0 1 -9.536743e-007 0)" clipPath="url(#ggDataDashboardTitleSvgClip1)">
                                            <image overflow="visible" width="43" height="24" xlinkHref="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAC4AAAAdCAYAAADVV140AAAACXBIWXMAAC4jAAAuIwF4pT92AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAOZJREFUeNpiYBgF9AWMA2Xxj/dOCUAqngytCzkE9y1gGUBHKEAxqeAgiGAZYEeQDeAO//bOO4GR8Q/Qwb/p7giKHA51rMNQyZxMQ7VUYcIsZBiHaogPSYf/B+J/o2mcfg7/P3TSOAv2NP5/MLs5HlhJPhiKSQVcQaI6nHGoZs7/TEMmvw6XCujfYM+Yw66tMjQdvuA/AxMIDy2HcwltfQDMmA8HeeZcAMSOIBpHzUm32hPkkIUkqH8A7Cg/wFHlU1Ttk+0QKrRVUGpPujmEVICSoIEdZgVgh1mBkeE33R0yCgY7AAgwAPoFPF9RAZEaAAAAAElFTkSuQmCC" transform="matrix(0.24 0 0 0.24 15.6182 -0.479)" />
                                        </g>
                                        <image overflow="visible" width="132" height="132" xlinkHref="data:image/png;base64,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" transform="matrix(0.24 0 0 0.24 0.25 0.2622)" />
                                        <defs>
                                            <path id="ggDataDashboardTitleSvgPath2" d="M26.924,16.231l0.007-0.247l0.004-0.133c0.04-1.33,1.151-2.375,2.48-2.334 c1.325,0.04,2.367,1.144,2.334,2.467l-0.009,0.342L26.924,16.231z" />
                                        </defs>
                                        <clipPath id="ggDataDashboardTitleSvgClip2">
                                            <use xlinkHref="#ggDataDashboardTitleSvgPath2" overflow="visible" />
                                        </clipPath>
                                        <g clipPath="url(#ggDataDashboardTitleSvgClip2)">
                                            <image overflow="visible" width="22" height="42" xlinkHref="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAB0AAAAuCAYAAADUfRIMAAAACXBIWXMAAC4jAAAuIwF4pT92AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAJJJREFUeNpiYBgAwIgu8Fo0RQFIJVDZngeir+csgHFYsCgAWVpPZUsPADHcUqaBCN5ha+kDIF5Id0uRE9EIjlNoHo2nt09pUTCMnCwzaumopaOWjlo6aumopaOWjlo6aumopaOWjlo6aim5gIVsJ/yH0ow4xP4Taynj/wdAspEoS2EGM+IQA+F/YNEHDCMWAAQYAMmbF57TnuUhAAAAAElFTkSuQmCC" transform="matrix(0.24 0 0 0.24 26.9233 6.3472)" />
                                        </g>
                                    </g>
                                </g>
                            </svg>
                            <span>{__('Gregius Data', 'gregius-data')}</span>
                        </Heading>
                        <p>{__('The orchestration layer for AI workflows in WordPress', 'gregius-data')}</p>
                </CardHeader>

                <CardBody>
                    {/* Tab navigation and content */}
                    <TabPanel
                        activeClass="is-active"
                        onSelect={setActiveTab}
                        tabs={tabs.map(tab => ({
                            name: tab.name,
                            title: tab.title,
                            className: tab.className
                        }))}
                    >
                        {(tab) => {
                            const selectedTab = tabs.find(t => t.name === tab.name);
                            if (!selectedTab) return null;

                            const TabComponent = selectedTab.component;
                            return (
                                <TabComponent
                                    settings={settings || {}}
                                    isLoading={isLoadingSettings}
                                    error={settingsError}
                                    apiStatus={apiStatus}
                                />
                            );
                        }}
                    </TabPanel>
                </CardBody>
            </Card>
        </div>
    );
};

export default App;
