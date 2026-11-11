/**
 * Checkout Impact Pro: adds the Alerts tab and report export buttons to the
 * free plugin's admin screen through wp.hooks filters.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.element || ! wp.apiFetch ) {
		return;
	}

	const h = wp.element.createElement;
	const useState = wp.element.useState;
	const useEffect = wp.element.useEffect;
	const __ = wp.i18n.__;
	const API = '/wpecir-pro/v1';

	function download( filename, mime, content ) {
		const blob = new window.Blob( [ content ], { type: mime + ';charset=utf-8' } );
		const url = window.URL.createObjectURL( blob );
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = filename;
		document.body.appendChild( link );
		link.click();
		link.remove();
		window.setTimeout( function () { window.URL.revokeObjectURL( url ); }, 1000 );
	}

	function ExportButtons( props ) {
		const [ busy, setBusy ] = useState( '' );
		const run = function ( format ) {
			setBusy( format );
			wp.apiFetch( { path: API + '/scenarios/' + Number( props.scenarioId ) + '/export?format=' + format } )
				.then( function ( file ) { download( file.filename, file.mime, file.content ); } )
				.catch( function ( error ) { window.alert( error.message || __( 'Export failed.', 'checkout-impact-regression-pro' ) ); } )
				.finally( function () { setBusy( '' ); } );
		};
		const disabled = ! props.scenarioId || ! props.count;
		return h( 'span', { className: 'wpecir-pro-export' },
			h( 'button', { type: 'button', className: 'button', disabled: disabled || busy !== '', onClick: function () { run( 'csv' ); } }, busy === 'csv' ? __( 'Exporting…', 'checkout-impact-regression-pro' ) : __( 'Export CSV', 'checkout-impact-regression-pro' ) ),
			' ',
			h( 'button', { type: 'button', className: 'button', disabled: disabled || busy !== '', onClick: function () { run( 'json' ); } }, busy === 'json' ? __( 'Exporting…', 'checkout-impact-regression-pro' ) : __( 'Export JSON', 'checkout-impact-regression-pro' ) )
		);
	}

	function AlertsTab() {
		const [ enabled, setEnabled ] = useState( false );
		const [ recipients, setRecipients ] = useState( '' );
		const [ busy, setBusy ] = useState( true );
		const [ message, setMessage ] = useState( null );
		const [ loaded, setLoaded ] = useState( false );
		const [ lastFailure, setLastFailure ] = useState( '' );
		useEffect( function () {
			wp.apiFetch( { path: API + '/alerts' } ).then( function ( settings ) {
				setEnabled( Boolean( settings.enabled ) );
				setRecipients( settings.recipients || '' );
				setLastFailure( settings.last_failure || '' );
				setLoaded( true );
			} ).catch( function ( error ) {
				setMessage( { tone: 'error', text: error.message || __( 'Could not load alert settings. Reload the page before changing them.', 'checkout-impact-regression-pro' ) } );
			} ).finally( function () { setBusy( false ); } );
		}, [] );
		const save = function ( event ) {
			event.preventDefault();
			if ( ! loaded ) {
				return;
			}
			setBusy( true );
			setMessage( null );
			wp.apiFetch( { path: API + '/alerts', method: 'POST', data: { enabled: enabled, recipients: recipients } } )
				.then( function ( settings ) { setRecipients( settings.recipients ); setMessage( { tone: 'success', text: __( 'Alert settings saved.', 'checkout-impact-regression-pro' ) } ); } )
				.catch( function ( error ) { setMessage( { tone: 'error', text: error.message || __( 'Could not save.', 'checkout-impact-regression-pro' ) } ); } )
				.finally( function () { setBusy( false ); } );
		};
		return h( 'section', { className: 'wpecir-card', 'aria-labelledby': 'wpecir-pro-alerts-title' },
			h( 'header', { className: 'wpecir-card__header' }, h( 'div', null,
				h( 'p', { className: 'wpecir-eyebrow' }, __( 'Pro', 'checkout-impact-regression-pro' ) ),
				h( 'h2', { id: 'wpecir-pro-alerts-title', className: 'wpecir-card__title' }, __( 'Change alerts', 'checkout-impact-regression-pro' ) ),
				h( 'p', { className: 'wpecir-card__description' }, __( 'Get one email when a plugin is updated or a payment or shipping setting changes, so you know when to compare your scenarios. Changes within 15 minutes are grouped into one email.', 'checkout-impact-regression-pro' ) )
			) ),
			lastFailure ? h( 'div', { className: 'wpecir-notice wpecir-notice--warning', role: 'status' }, h( 'p', null, __( 'The last alert email could not be sent after 3 tries (UTC):', 'checkout-impact-regression-pro' ) + ' ' + lastFailure + '. ' + __( 'Check that your site can send email.', 'checkout-impact-regression-pro' ) ) ) : null,
			message ? h( 'div', { className: 'wpecir-notice wpecir-notice--' + message.tone, role: message.tone === 'error' ? 'alert' : 'status' }, h( 'p', null, message.text ) ) : null,
			h( 'form', { className: 'wpecir-form wpecir-form--narrow', onSubmit: save },
				h( 'label', { className: 'wpecir-check' },
					h( 'input', { type: 'checkbox', checked: enabled, onChange: function () { setEnabled( ! enabled ); } } ),
					h( 'span', null, __( 'Email me when a change is recorded', 'checkout-impact-regression-pro' ) )
				),
				h( 'div', { className: 'wpecir-field' },
					h( 'label', { htmlFor: 'wpecir-pro-recipients' }, __( 'Send to', 'checkout-impact-regression-pro' ) ),
					h( 'input', { id: 'wpecir-pro-recipients', type: 'text', value: recipients, onChange: function ( event ) { setRecipients( event.target.value ); }, 'aria-describedby': 'wpecir-pro-recipients-help' } ),
					h( 'p', { id: 'wpecir-pro-recipients-help', className: 'wpecir-help' }, __( 'Up to 5 addresses, separated by commas.', 'checkout-impact-regression-pro' ) )
				),
				h( 'div', { className: 'wpecir-form-actions' }, h( 'button', { type: 'submit', className: 'button button-primary', disabled: busy || ! loaded }, busy ? __( 'Saving…', 'checkout-impact-regression-pro' ) : __( 'Save alert settings', 'checkout-impact-regression-pro' ) ) )
			)
		);
	}

	wp.hooks.addFilter( 'wpecir.adminTabs', 'wpecir-pro/tabs', function ( tabs ) {
		const docsIndex = tabs.findIndex( function ( tab ) { return tab.key === 'docs'; } );
		const alerts = { key: 'alerts', label: __( 'Alerts', 'checkout-impact-regression-pro' ), render: function () { return h( AlertsTab ); } };
		const next = tabs.slice();
		next.splice( docsIndex === -1 ? next.length : docsIndex, 0, alerts );
		return next;
	} );

	wp.hooks.addFilter( 'wpecir.reportActions', 'wpecir-pro/export', function ( actions, context ) {
		return actions.concat( [ h( ExportButtons, { key: 'pro-export', scenarioId: context.scenarioId, count: context.reports.length } ) ] );
	} );
} )( window.wp );
