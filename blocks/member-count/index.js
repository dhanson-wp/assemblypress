( function ( wp ) {
	wp.blocks.registerBlockType( 'assemblypress/member-count', {
		edit() {
			return wp.element.createElement(
				'div',
				wp.blockEditor.useBlockProps(),
				wp.element.createElement(
					'p',
					{},
					wp.i18n.__( 'Member Result Count', 'assemblypress' )
				)
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp );
