( function () {
	'use strict';

	if ( typeof artMasterInstallCatalog === 'undefined' ) {
		return;
	}

	const config = artMasterInstallCatalog;
	const i18n = config.i18n || {};
	const queue = [];
	const queuedKeys = new Set();
	const rowSnapshots = new Map();
	let running = false;

	function queueKey( slug, catalogType ) {
		return ( catalogType || 'plugin' ) + ':' + slug;
	}

	function row( slug, catalogType ) {
		const type = catalogType || 'plugin';
		return document.querySelector(
			'.art-master-install-row[data-slug="' + slug + '"][data-catalog-type="' + type + '"]'
		);
	}

	function statusBadgeEl( rowEl ) {
		return rowEl ? rowEl.querySelector( '.art-master-install-status-badge' ) : null;
	}

	function statusVersionEl( rowEl ) {
		return rowEl ? rowEl.querySelector( '.art-master-install-status-version' ) : null;
	}

	function actionsEl( rowEl ) {
		return rowEl ? rowEl.querySelector( '.art-master-install-actions' ) : null;
	}

	function snapshotRow( slug, catalogType ) {
		const key = queueKey( slug, catalogType );
		const rowEl = row( slug, catalogType );

		if ( ! rowEl || rowSnapshots.has( key ) ) {
			return;
		}

		const statusCell = rowEl.querySelector( '.art-master-install-status-cell' );
		const actionsCell = actionsEl( rowEl );

		rowSnapshots.set( key, {
			statusHtml: statusCell ? statusCell.innerHTML : '',
			actionsHtml: actionsCell ? actionsCell.innerHTML : '',
		} );
	}

	function restoreRow( slug, catalogType ) {
		const key = queueKey( slug, catalogType );
		const rowEl = row( slug, catalogType );
		const snapshot = rowSnapshots.get( key );

		if ( ! rowEl || ! snapshot ) {
			return;
		}

		const statusCell = rowEl.querySelector( '.art-master-install-status-cell' );
		const actionsCell = actionsEl( rowEl );

		if ( statusCell ) {
			statusCell.innerHTML = snapshot.statusHtml;
		}

		if ( actionsCell ) {
			actionsCell.innerHTML = snapshot.actionsHtml;
		}

		rowEl.classList.remove( 'is-busy' );
		rowSnapshots.delete( key );
	}

	function setPendingStatus( slug, catalogType, phase ) {
		const rowEl = row( slug, catalogType );
		const badge = statusBadgeEl( rowEl );

		if ( ! badge ) {
			return;
		}

		const labels = {
			queued: i18n.queued,
			installing: i18n.installing,
			updating: i18n.updating,
		};

		badge.className = 'art-master-install-status art-master-install-status--pending art-master-install-status-badge';
		badge.replaceChildren();

		const spinner = document.createElement( 'span' );
		spinner.className = 'spinner is-active art-master-install-spinner';
		badge.appendChild( spinner );
		badge.appendChild( document.createTextNode( ' ' + ( labels[ phase ] || i18n.installing ) ) );

		if ( rowEl ) {
			rowEl.classList.add( 'is-busy' );
		}
	}

	function renderActions( slug, catalogType, payload ) {
		const rowEl = row( slug, catalogType );
		const cell = actionsEl( rowEl );

		if ( ! cell || ! payload || ! payload.actions ) {
			return;
		}

		cell.innerHTML = '';

		if ( payload.actions.install ) {
			cell.appendChild( createActionButton( 'install', slug, catalogType, i18n.install, true ) );
		}

		if ( payload.actions.update ) {
			cell.appendChild( createActionButton( 'update', slug, catalogType, i18n.update, true ) );
		}

		if ( payload.actions.activate && payload.activate_url ) {
			const link = document.createElement( 'a' );
			link.className = 'button';
			link.href = payload.activate_url;
			link.textContent = i18n.activate;
			cell.appendChild( link );
		}

		if ( payload.actions.up_to_date ) {
			const note = document.createElement( 'span' );
			note.className = 'description';
			note.textContent = i18n.upToDate;
			cell.appendChild( note );
		}
	}

	function createActionButton( action, slug, catalogType, label, primary ) {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = primary ? 'button button-primary art-master-install-action' : 'button art-master-install-action';
		button.dataset.action = action;
		button.dataset.slug = slug;
		button.dataset.catalogType = catalogType || 'plugin';
		button.textContent = label;
		return button;
	}

	function applyPayload( payload ) {
		const catalogType = payload.catalog_type || 'plugin';
		const rowEl = row( payload.slug, catalogType );

		if ( ! rowEl ) {
			return;
		}

		rowEl.dataset.status = payload.status;

		const badge = statusBadgeEl( rowEl );
		if ( badge ) {
			badge.className = payload.status_class + ' art-master-install-status-badge';
			badge.textContent = payload.status_label;
		}

		let versionEl = statusVersionEl( rowEl );
		if ( payload.installed_version ) {
			if ( ! versionEl ) {
				versionEl = document.createElement( 'span' );
				versionEl.className = 'description art-master-install-status-version';
				const statusCell = rowEl.querySelector( '.art-master-install-status-cell' );
				if ( statusCell ) {
					statusCell.appendChild( document.createElement( 'br' ) );
					statusCell.appendChild( versionEl );
				}
			}
			versionEl.textContent = i18n.versionLabel.replace( '%s', payload.installed_version );
		} else if ( versionEl ) {
			versionEl.remove();
		}

		renderActions( payload.slug, catalogType, payload );
		rowEl.classList.remove( 'is-busy' );
		rowSnapshots.delete( queueKey( payload.slug, catalogType ) );
	}

	function showNotice( message, type ) {
		const container = document.getElementById( 'art-master-install-notices' );
		if ( ! container || ! message ) {
			return;
		}

		const notice = document.createElement( 'div' );
		notice.className = 'notice notice-' + ( type || 'error' ) + ' is-dismissible inline art-master-install-inline-notice';
		const paragraph = document.createElement( 'p' );
		paragraph.textContent = message;
		notice.appendChild( paragraph );
		container.prepend( notice );
	}

	function enqueue( slug, catalogType, action ) {
		const key = queueKey( slug, catalogType );

		if ( queuedKeys.has( key ) ) {
			return;
		}

		queuedKeys.add( key );
		snapshotRow( slug, catalogType );
		queue.push( { slug: slug, catalogType: catalogType || 'plugin', action: action } );
		setPendingStatus( slug, catalogType, 'queued' );
		processQueue();
	}

	async function processQueue() {
		if ( running ) {
			return;
		}

		running = true;

		while ( queue.length > 0 ) {
			const job = queue.shift();
			await runJob( job );
		}

		running = false;
	}

	async function runJob( job ) {
		const phase = job.action === 'update' ? 'updating' : 'installing';
		setPendingStatus( job.slug, job.catalogType, phase );

		const body = new URLSearchParams();
		body.set( 'action', config.ajaxAction );
		body.set( 'nonce', config.nonce );
		body.set( 'catalog_action', job.action );
		body.set( 'slug', job.slug );
		body.set( 'catalog_type', job.catalogType || 'plugin' );

		const key = queueKey( job.slug, job.catalogType );

		try {
			const response = await fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: body.toString(),
			} );

			const data = await response.json();
			queuedKeys.delete( key );

			if ( ! data || ! data.success ) {
				const message = data && data.data && data.data.message ? data.data.message : i18n.genericError;
				showNotice( message, 'error' );
				restoreRow( job.slug, job.catalogType );
				return;
			}

			if ( data.data && data.data.state ) {
				applyPayload( data.data.state );
			}

			if ( data.data && data.data.message ) {
				showNotice( data.data.message, 'success' );
			}
		} catch ( error ) {
			queuedKeys.delete( key );
			showNotice( i18n.genericError, 'error' );
			restoreRow( job.slug, job.catalogType );
		}
	}

	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '.art-master-install-action' );
		if ( ! button ) {
			return;
		}

		event.preventDefault();

		const slug = button.dataset.slug;
		const action = button.dataset.action;
		const catalogType = button.dataset.catalogType || 'plugin';

		if ( ! slug || ! action ) {
			return;
		}

		enqueue( slug, catalogType, action );
	} );

	async function checkUpdates() {
		const button = document.getElementById( 'art-master-install-check-updates' );
		if ( ! button || button.disabled ) {
			return;
		}

		button.disabled = true;
		button.classList.add( 'is-busy' );
		button.textContent = i18n.checking;

		const body = new URLSearchParams();
		body.set( 'action', config.ajaxAction );
		body.set( 'nonce', config.nonce );
		body.set( 'catalog_action', 'check_updates' );

		try {
			const response = await fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: body.toString(),
			} );

			const data = await response.json();

			if ( ! data || ! data.success || ! data.data ) {
				showNotice( i18n.checkError, 'error' );
				return;
			}

			try {
				sessionStorage.setItem(
					'artMasterInstallCheckNotice',
					JSON.stringify( {
						message: data.data.message || '',
						type: data.data.updates_count > 0 ? 'warning' : 'success',
					} )
				);
			} catch ( storageError ) {
				// sessionStorage unavailable — still reload to show fresh versions.
			}

			window.location.reload();
		} catch ( error ) {
			showNotice( i18n.checkError, 'error' );
		} finally {
			button.disabled = false;
			button.classList.remove( 'is-busy' );
			button.textContent = i18n.checkUpdates;
		}
	}

	const checkButton = document.getElementById( 'art-master-install-check-updates' );
	if ( checkButton ) {
		checkButton.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			checkUpdates();
		} );
	}

	try {
		const rawNotice = sessionStorage.getItem( 'artMasterInstallCheckNotice' );
		if ( rawNotice ) {
			sessionStorage.removeItem( 'artMasterInstallCheckNotice' );
			const notice = JSON.parse( rawNotice );
			if ( notice && notice.message ) {
				showNotice( notice.message, notice.type || 'success' );
			}
		}
	} catch ( noticeError ) {
		sessionStorage.removeItem( 'artMasterInstallCheckNotice' );
	}
}() );
