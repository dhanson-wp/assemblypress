/* eslint-disable */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;

	blocks.registerBlockType( 'assemblypress/member-profile', {
		edit: function ( props ) {
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Profile settings', 'assemblypress' ) },
						el( TextControl, {
							label: __( 'User ID', 'assemblypress' ),
							type: 'number',
							value: props.attributes.userId || '',
							onChange: function ( value ) {
								props.setAttributes( { userId: parseInt( value, 10 ) || 0 } );
							},
						} )
					)
				),
				el(
					'div',
					{ className: 'assemblypress-editor-preview' },
					el( 'span', { className: 'assemblypress-editor-preview__label' }, __( 'Dynamic block', 'assemblypress' ) ),
					el( 'strong', {}, __( 'Member Profile', 'assemblypress' ) ),
					el( 'p', {}, __( 'Renders member profile sections from AssemblyPress profile fields.', 'assemblypress' ) ),
					el(
						'span',
						{ className: 'assemblypress-editor-preview__meta' },
						props.attributes.userId
							? __( 'Specific user ID:', 'assemblypress' ) + ' ' + props.attributes.userId
							: __( 'Uses the current member context when available.', 'assemblypress' )
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
