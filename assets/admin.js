/**
 * PixelShinobi Troubleshooting Finder admin: drives the bisect one probe step per request,
 * so the search survives the request time limits of cheap hosting,
 * and wires the debug bundle's copy-to-clipboard button.
 * Plain browser APIs, no dependencies.
 */
( function () {
	var cfg = window.ConflictRadar || {};

	// Copy the debug bundle. The Clipboard API needs a secure context,
	// so on plain-http admin screens fall back to selecting the text and
	// the legacy copy command; if both fail, say how to copy by hand.
	var copyButton = document.getElementById( 'cr-copy-debug' );
	var bundle = document.getElementById( 'cr-debug-bundle' );
	var copyStatus = document.getElementById( 'cr-copy-status' );
	if ( copyButton && bundle ) {
		copyButton.addEventListener( 'click', function () {
			function done( ok ) {
				if ( copyStatus ) {
					copyStatus.textContent = ok
						? 'Copied — paste it into your support thread.'
						: 'Copy failed — click the text, select all, and copy manually.';
				}
			}
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( bundle.value ).then(
					function () { done( true ); },
					function () { done( fallback() ); }
				);
			} else {
				done( fallback() );
			}
			function fallback() {
				bundle.focus();
				bundle.select();
				try {
					return document.execCommand( 'copy' );
				} catch ( e ) {
					return false;
				}
			}
		} );
	}

	if ( ! cfg.running ) {
		return;
	}
	var status = document.getElementById( 'cr-bisect-status' );
	var log = document.getElementById( 'cr-bisect-log' );

	function line( text ) {
		if ( ! log ) {
			return;
		}
		var li = document.createElement( 'li' );
		li.textContent = text;
		log.appendChild( li );
	}

	function step() {
		var body = new URLSearchParams();
		body.set( 'action', 'conflict_radar_bisect_step' );
		body.set( '_ajax_nonce', cfg.nonce );

		fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( res ) {
				if ( ! res || ! res.success ) {
					if ( status ) {
						status.textContent = 'The search stopped unexpectedly — reload the page to pick up where it left off.';
					}
					return;
				}
				var d = res.data;
				var parts = [ 'Step ' + d.steps + ':' ];
				if ( null !== d.subset_size ) {
					parts.push( d.subset_size + ' plugins on' );
				}
				if ( null !== d.fault ) {
					parts.push( d.fault ? 'problem reproduced' : 'page healthy' );
				}
				parts.push( d.suspects + ' suspects left' );
				line( parts.join( ' — ' ) );

				if ( d.complete ) {
					window.location.reload();
					return;
				}
				if ( status ) {
					status.textContent = 'Working… step ' + d.steps + ', ' + d.suspects + ' suspects left.';
				}
				step();
			} )
			.catch( function () {
				if ( status ) {
					status.textContent = 'Connection problem — reload the page to resume the search.';
				}
			} );
	}

	step();
} )();
