( function ( wp ) {
	const { createElement: el } = wp.element;
	wp.blocks.registerBlockType( 'assemblypress/member-results', {
		edit( props ) {
			return el(
				'div',
				wp.blockEditor.useBlockProps(),
				el(
					wp.blockEditor.InspectorControls,
					{},
					el(
						wp.components.PanelBody,
						{
							title: wp.i18n.__(
								'Results layout',
								'assemblypress'
							),
						},
						el( wp.components.RangeControl, {
							label: wp.i18n.__( 'Columns', 'assemblypress' ),
							min: 1,
							max: 6,
							value: props.attributes.columns,
							onChange( columns ) {
								props.setAttributes( { columns } );
							},
						} )
					)
				),
				el(
					'p',
					{},
					wp.i18n.__(
						'Design one member card. It repeats for each search result.',
						'assemblypress'
					)
				),
				el( wp.blockEditor.InnerBlocks, {
					template: [
						[
							'assemblypress/profile-field',
							{ fieldKey: 'display_name', linkToProfile: true },
						],
					],
					templateLock: false,
				} )
			);
		},
		save() {
			return el( wp.blockEditor.InnerBlocks.Content );
		},
	} );
} )( window.wp );
