( function () {
	function getApiRoot() {
		if ( window.wpApiSettings && window.wpApiSettings.root ) {
			return window.wpApiSettings.root;
		}

		return window.location.origin.replace( /\/$/, '' ) + '/wp-json/';
	}

	function setStatus( block, message ) {
		var status = block.querySelector( '.wp-native-vector-search-box__status' );
		if ( status ) {
			status.textContent = message;
		}
	}

	function clearResults( block ) {
		var results = block.querySelector( '.wp-native-vector-search-box__results' );
		if ( results ) {
			results.replaceChildren();
		}
	}

	function renderResults( block, items ) {
		var results = block.querySelector( '.wp-native-vector-search-box__results' );
		if ( ! results ) {
			return;
		}

		results.replaceChildren();

		items.forEach( function ( item ) {
			var li = document.createElement( 'li' );
			var link = document.createElement( 'a' );
			var score = document.createElement( 'span' );

			link.href = item.url;
			link.textContent = item.title || String( item.post_id );
			score.className = 'wp-native-vector-search-box__score';
			score.textContent = Number( item.score ).toFixed( 3 );

			li.appendChild( link );
			li.appendChild( score );
			results.appendChild( li );
		} );
	}

	function initBlock( block ) {
		var form = block.querySelector( '.wp-native-vector-search-box__form' );
		var input = block.querySelector( '.wp-native-vector-search-box__input' );

		if ( ! form || ! input ) {
			return;
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var query = input.value.trim();
			if ( ! query ) {
				clearResults( block );
				setStatus( block, '' );
				return;
			}

			clearResults( block );
			setStatus( block, 'Searching...' );

			window
				.fetch( getApiRoot() + 'vector-search/v1/search', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( {
						query: query,
						limit: 10,
					} ),
				} )
				.then( function ( response ) {
					return response.json().then( function ( body ) {
						if ( ! response.ok ) {
							throw new Error( body.message || 'Search failed.' );
						}

						return body;
					} );
				} )
				.then( function ( body ) {
					var items = body.results || [];
					renderResults( block, items );
					setStatus( block, items.length ? '' : 'No results found.' );
				} )
				.catch( function ( error ) {
					clearResults( block );
					setStatus( block, error.message || 'Search failed.' );
				} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.wp-native-vector-search-box' ).forEach( initBlock );
	} );
} )();
