( function () {
	'use strict';

	if ( typeof artMasterInstallCatalog === 'undefined' ) {
		return;
	}

	const config = artMasterInstallCatalog;
	const i18n = config.i18n || {};
	const queue = [];
	const queuedSlugs = new Set();
	const rowSnapshots = new Map();
	let running = false;

	function row( slug ) {
		return document.querySelector( '.art-master-install-row[data-slug="' + slug + '"]' );
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

	function snapshotRow( slug ) {
		const rowEl = row( slug );
		if ( ! rowEl || rowSnapshots.has( slug ) ) {
			return;
		}

		const statusCell = rowEl.querySelector( '.art-master-install-status-cell' );
		const actionsCell = actionsEl( rowEl );

		rowSnapshots.set( slug, {
			statusHtml: statusCell ? statusCell.innerHTML : '',
			actionsHtml: actionsCell ? actionsCell.innerHTML : '',
		} );
	}

	function restoreRow( slug ) {
		const rowEl = row( slug );
		const snapshot = rowSnapshots.get( slug );

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
		rowSnapshots.delete( slug );
	}

	function setPendingStatus( slug, phase ) {
		const rowEl = row( slug );
		const badge = statusBadgeEl( rowEl );

		if ( ! badge ) {
			return;
		}

		const labels = {
			queued: i18n.queued,
			installing: i18n.installing,
			activating: i18n.activating,
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

	function renderActions( slug, payload ) {
		const rowEl = row( slug );
		const cell = actionsEl( rowEl );

		if ( ! cell || ! payload || ! payload.actions ) {
			return;
		}

		cell.innerHTML = '';

		if ( payload.actions.install ) {
			cell.appendChild( createActionButton( 'install', slug, i18n.install, true ) );
		}

		if ( payload.actions.update ) {
			cell.appendChild( createActionButton( 'update', slug, i18n.update, true ) );
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

	function createActionButton( action, slug, label, primary ) {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = primary ? 'button button-primary art-master-install-action' : 'button art-master-install-action';
		button.dataset.action = action;
		button.dataset.slug = slug;
		button.textContent = label;
		return button;
	}

	function applyPayload( payload ) {
		const rowEl = row( payload.slug );

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

		renderActions( payload.slug, payload );
		rowEl.classList.remove( 'is-busy' );
		rowSnapshots.delete( payload.slug );
	}

	function showNotice( message, type ) {
		const container = document.getElementById( 'art-master-install-notices' );
		if ( ! container || ! message ) {
			return;
		}

		const notice = document.createElement( 'div' );
		notice.className = 'notice notice-' + ( type || 'error' ) + ' is-dismissible art-master-install-inline-notice';
		const paragraph = document.createElement( 'p' );
		paragraph.textContent = message;
		notice.appendChild( paragraph );
		container.prepend( notice );
	}

	function enqueue( slug, action ) {
		if ( queuedSlugs.has( slug ) ) {
			return;
		}

		queuedSlugs.add( slug );
		snapshotRow( slug );
		queue.push( { slug: slug, action: action } );
		setPendingStatus( slug, 'queued' );
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
		setPendingStatus( job.slug, phase );

		const body = new URLSearchParams();
		body.set( 'action', config.ajaxAction );
		body.set( 'nonce', config.nonce );
		body.set( 'catalog_action', job.action );
		body.set( 'slug', job.slug );

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
			queuedSlugs.delete( job.slug );

			if ( ! data || ! data.success ) {
				const message = data && data.data && data.data.message ? data.data.message : i18n.genericError;
				showNotice( message, 'error' );
				restoreRow( job.slug );
				return;
			}

			if ( data.data && data.data.state ) {
				applyPayload( data.data.state );
			}
		} catch ( error ) {
			queuedSlugs.delete( job.slug );
			showNotice( i18n.genericError, 'error' );
			restoreRow( job.slug );
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

		if ( ! slug || ! action ) {
			return;
		}

		enqueue( slug, action );
	} );

	function updateLastCheckLabel( label ) {
		const element = document.getElementById( 'art-master-install-last-check' );
		if ( element && label ) {
			element.textContent = label;
		}
	}

	function updateMasterUpdatePanel( masterUpdate ) {
		const panel = document.getElementById( 'art-master-install-self-update' );
		if ( ! panel || ! masterUpdate ) {
			return;
		}

		const status = panel.querySelector( '.art-master-install-self-update-status' );
		if ( status ) {
			status.textContent = i18n.selfUpdateStatus
				.replace( '%1$s', masterUpdate.installed_version )
				.replace( '%2$s', masterUpdate.latest_version || '—' );
		}

		let notice = panel.querySelector( '.art-master-install-self-update-notice' );
		if ( masterUpdate.update_available ) {
			if ( ! notice ) {
				notice = document.createElement( 'p' );
				notice.className = 'art-master-install-self-update-notice';
				panel.appendChild( notice );
			}

			notice.innerHTML = '';
			notice.appendChild( document.createTextNode( i18n.selfUpdateAvailable + ' ' ) );

			const link = document.createElement( 'a' );
			link.href = masterUpdate.updates_url || '#';
			link.textContent = i18n.goToUpdates;
			notice.appendChild( link );
		} else if ( notice ) {
			notice.remove();
		}
	}

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

			if ( Array.isArray( data.data.items ) ) {
				data.data.items.forEach( function ( item ) {
					applyPayload( item );
				} );
			}

			updateLastCheckLabel( data.data.last_checked );
			updateMasterUpdatePanel( data.data.master_update );
			showNotice( data.data.message, data.data.updates_count > 0 ? 'warning' : 'success' );
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
}() );
