( function ( wp ) {
	wp.blocks.registerBlockType( 'assemblypress/member-pagination', {
		edit() {
			return wp.element.createElement(
				'div',
				wp.blockEditor.useBlockProps(),
				wp.element.createElement(
					'p',
					{},
					wp.i18n.__( 'Member Pagination', 'assemblypress' )
				)
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp );
