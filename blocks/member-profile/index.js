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
				el( 'strong', {}, __( 'Member Profile', 'assemblypress' ) ),
				el( 'p', {}, __( 'Displays a dynamic AssemblyPress member profile.', 'assemblypress' ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
