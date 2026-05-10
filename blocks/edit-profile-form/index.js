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
				el( 'strong', {}, __( 'Edit Profile Form', 'assemblypress' ) ),
				el( 'p', {}, __( 'Displays editable AssemblyPress profile fields for the logged-in member.', 'assemblypress' ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
