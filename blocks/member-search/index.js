( function ( wp ) {
	wp.blocks.registerBlockType( 'assemblypress/member-search', {
		edit() {
			return wp.element.createElement(
				'div',
				wp.blockEditor.useBlockProps(),
				wp.element.createElement(
					'p',
					{},
					wp.i18n.__( 'Member Search and Filters', 'assemblypress' )
				)
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp );
