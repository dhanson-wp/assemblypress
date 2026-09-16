( function ( wp ) {
	wp.blocks.registerBlockType( 'assemblypress/member-empty', {
		edit() {
			return wp.element.createElement(
				'div',
				wp.blockEditor.useBlockProps(),
				wp.element.createElement( wp.blockEditor.InnerBlocks, {
					template: [
						[
							'core/paragraph',
							{
								content: wp.i18n.__(
									'Try another name or clear the filters.',
									'assemblypress'
								),
							},
						],
					],
					templateLock: false,
				} )
			);
		},
		save() {
			return wp.element.createElement(
				wp.blockEditor.InnerBlocks.Content
			);
		},
	} );
} )( window.wp );
