( function ( wp ) {
	const { createElement: el } = wp.element;
	wp.blocks.registerBlockType( 'assemblypress/member-navigation', {
		edit( props ) {
			return el(
				'div',
				wp.blockEditor.useBlockProps(),
				el(
					wp.blockEditor.InspectorControls,
					{},
					el(
						wp.components.PanelBody,
						{ title: wp.i18n.__( 'Navigation', 'assemblypress' ) },
						el( wp.components.ToggleControl, {
							label: wp.i18n.__(
								'Show edit link to the member',
								'assemblypress'
							),
							checked: props.attributes.showEdit,
							onChange( showEdit ) {
								props.setAttributes( { showEdit } );
							},
						} )
					)
				),
				el(
					'p',
					{},
					wp.i18n.__(
						'Back to members · Edit my profile',
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
