/* eslint-disable */
( function ( blocks, blockEditor, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'assemblypress/edit-profile-form', {
		edit: function () {
			return el(
				'div',
				useBlockProps(),
				el( 'strong', {}, __( 'Profile Value Editor', 'assemblypress' ) ),
				el( 'p', {}, __( 'Temporary surface for testing editable AssemblyPress profile values.', 'assemblypress' ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
