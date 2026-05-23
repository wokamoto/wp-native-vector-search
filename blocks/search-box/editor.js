( function ( blocks, element, blockEditor, i18n ) {
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var __ = i18n.__;

	blocks.registerBlockType( 'wp-native-vector-search/search-box', {
		edit: function () {
			var blockProps = useBlockProps( {
				className: 'wp-native-vector-search-box',
			} );

			return el(
				'div',
				blockProps,
				el(
					'div',
					{ className: 'wp-native-vector-search-box__form' },
					el( 'input', {
						className: 'wp-native-vector-search-box__input',
						type: 'search',
						placeholder: __( 'Search posts', 'wp-native-vector-search' ),
						disabled: true,
					} ),
					el(
						'button',
						{
							className: 'wp-native-vector-search-box__button',
							type: 'button',
							disabled: true,
						},
						__( 'Search', 'wp-native-vector-search' )
					)
				),
				el( 'div', { className: 'wp-native-vector-search-box__status' } ),
				el( 'ol', { className: 'wp-native-vector-search-box__results' } )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n );
