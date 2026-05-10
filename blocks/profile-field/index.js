/* eslint-disable */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;

	blocks.registerBlockType( 'assemblypress/profile-field', {
		edit: function ( props ) {
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Field settings', 'assemblypress' ) },
						el( TextControl, {
							label: __( 'Field key', 'assemblypress' ),
							value: props.attributes.fieldKey,
							onChange: function ( value ) {
								props.setAttributes( { fieldKey: value } );
							},
						} ),
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
				el( 'strong', {}, __( 'Profile Field', 'assemblypress' ) ),
				el( 'p', {}, props.attributes.fieldKey )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
