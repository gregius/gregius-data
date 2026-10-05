/**
 * Deactivation modal — data-retention preference.
 *
 * Records the user's "keep or remove data on uninstall" choice before the
 * plugin is deactivated. The choice is executed only at uninstall time.
 *
 * @package gregius-data
 */

import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { useState, useEffect, createRoot } from '@wordpress/element';
import { Modal, RadioControl, Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const { deactivateId, removeData } = window.ggDataDeactivation || {};

function DeactivationModal() {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ href, setHref ] = useState( '' );
	const [ choice, setChoice ] = useState( removeData ? 'remove' : 'keep' );
	const [ isSaving, setIsSaving ] = useState( false );

	useEffect( () => {
		if ( ! deactivateId ) {
			return;
		}

		const link = document.getElementById( deactivateId );
		if ( ! link ) {
			return;
		}

		const onClick = ( event ) => {
			event.preventDefault();
			setHref( link.getAttribute( 'href' ) || '' );
			setIsOpen( true );
		};

		link.addEventListener( 'click', onClick );

		return () => link.removeEventListener( 'click', onClick );
	}, [ deactivateId ] );

	const proceed = async () => {
		if ( isSaving ) {
			return;
		}

		setIsSaving( true );

		try {
			await apiFetch( {
				path: '/gg-data/v1/data-retention',
				method: 'POST',
				data: { remove_data: choice === 'remove' },
			} );
		} catch ( error ) {
			// Deactivation proceeds regardless; data is preserved by default.
		}

		window.location.href = href;
	};

	if ( ! isOpen ) {
		return null;
	}

	return (
		<Modal
			title={ __( 'Deactivate Gregius Data', 'gregius-data' ) }
			onRequestClose={ proceed }
		>
			<p>
				{ __(
					'What should happen to Gregius Data data if you later delete the plugin?',
					'gregius-data'
				) }
			</p>
			<RadioControl
				label={ __( 'Data handling on plugin deletion', 'gregius-data' ) }
				selected={ choice }
				options={ [
					{
						label: __( 'Keep my data (recommended)', 'gregius-data' ),
						value: 'keep',
					},
					{
						label: __(
							'Remove all data when the plugin is deleted',
							'gregius-data'
						),
						value: 'remove',
					},
				] }
				onChange={ setChoice }
				help={ __(
					'Deactivating never removes data — this only affects deletion.',
					'gregius-data'
				) }
			/>
			<Button variant="primary" onClick={ proceed } disabled={ isSaving }>
				{ __( 'Deactivate', 'gregius-data' ) }
			</Button>
		</Modal>
	);
}

domReady( () => {
	if ( window.wpApiSettings && window.wpApiSettings.nonce ) {
		apiFetch.use( apiFetch.createNonceMiddleware( window.wpApiSettings.nonce ) );
		apiFetch.use( apiFetch.createRootURLMiddleware( window.wpApiSettings.root ) );
	}

	const rootEl = document.createElement( 'div' );
	document.body.appendChild( rootEl );
	createRoot( rootEl ).render( <DeactivationModal /> );
} );
