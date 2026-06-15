/**
 * Gregius PostgreSQL Dashboard - React Entry Point
 * 
 * Modern React-based admin dashboard for the Gregius PostgreSQL plugin.
 * Integrates with the REST API endpoints for real-time settings management.
 */

import { render } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import apiFetch from '@wordpress/api-fetch';

// Import the main React app component
import App from './dashboard/components/App';

// Import styles
import '../styles/dashboard.scss';

/**
 * Initialize the React dashboard when DOM is ready
 */
domReady(() => {
    // Configure API fetch if WordPress settings are available
    if (window.wpApiSettings) {
        apiFetch.use(apiFetch.createNonceMiddleware(window.wpApiSettings.nonce));
        apiFetch.use(apiFetch.createRootURLMiddleware(window.wpApiSettings.root));
    } else if (window.ggPgDashboard && window.ggPgDashboard.nonce) {
        // Fallback to our own configuration
        apiFetch.use(apiFetch.createNonceMiddleware(window.ggPgDashboard.nonce));
        apiFetch.use(apiFetch.createRootURLMiddleware(window.ggPgDashboard.restUrl));
    }
    
    const dashboardContainer = document.getElementById('gg-data-react-dashboard');
    
    if (dashboardContainer) {
        // Set flag to indicate React dashboard is active
        window.ggPgReactActive = true;
        
        try {
            // Render the React app
            render(
                <App />,
                dashboardContainer
            );
        } catch (error) {
            console.error('❌ Error rendering React App:', error);
            console.error('Error stack:', error.stack);
            
            // Signal React load failure
            if (window.jQuery) {
                window.jQuery(document).trigger('gg-data-react-failed');
            }
        }
    } else {
        // Fallback: Create container if it doesn't exist
        const adminPage = document.querySelector('.wrap.gg-data-admin');
        if (adminPage) {
            const reactContainer = document.createElement('div');
            reactContainer.id = 'gg-data-react-dashboard';
            adminPage.appendChild(reactContainer);
            
            try {
                render(
                    <App />,
                    reactContainer
                );
            } catch (error) {
                console.error('❌ Error rendering React App in fallback:', error);
                console.error('Error stack:', error.stack);
                
                // Signal React load failure
                if (window.jQuery) {
                    window.jQuery(document).trigger('gg-data-react-failed');
                }
            }
        } else {
            console.error('❌ Could not find admin page container');
            // Dashboard container not found - React dashboard not initialized
        }
    }
});

// Make REST API configuration globally available
window.ggPgDashboard = {
    apiUrl: wpApiSettings?.root + 'gg-data/v1/',
    nonce: wpApiSettings?.nonce,
    currentUser: wpApiSettings?.currentUser || null,
    adminUrl: window.location.href,
    pluginUrl: window.location.origin + '/wp-content/plugins/gregius-data/',
};