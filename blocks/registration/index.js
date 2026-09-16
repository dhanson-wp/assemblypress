( function ( wp ) {
	wp.blocks.registerBlockType( 'assemblypress/registration', {
		edit() {
			return wp.element.createElement(
				'div',
				wp.blockEditor.useBlockProps(),
				wp.element.createElement(
					wp.components.Notice,
					{ status: 'info', isDismissible: false },
					wp.i18n.__(
						'Member registration follows AssemblyPress → Settings. Visitors can join, request approval, or use an invitation according to your settings.',
						'assemblypress'
					)
				)
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp );
