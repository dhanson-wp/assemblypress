/* eslint-disable */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var RangeControl = components.RangeControl;

	blocks.registerBlockType( 'assemblypress/member-directory', {
		edit: function ( props ) {
			var blockProps = useBlockProps();
			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Directory settings', 'assemblypress' ) },
						el( RangeControl, {
							label: __( 'Members to show', 'assemblypress' ),
							min: 1,
							max: 100,
							value: props.attributes.perPage,
							onChange: function ( value ) {
								props.setAttributes( { perPage: value } );
							},
						} )
					)
				),
				el(
					'div',
					{ className: 'assemblypress-editor-preview' },
					el( 'span', { className: 'assemblypress-editor-preview__label' }, __( 'Dynamic block', 'assemblypress' ) ),
					el( 'strong', {}, __( 'Member Directory', 'assemblypress' ) ),
					el( 'p', {}, __( 'Renders a theme-led member list on the frontend.', 'assemblypress' ) ),
					el(
						'span',
						{ className: 'assemblypress-editor-preview__meta' },
						__( 'Members to show:', 'assemblypress' ) + ' ' + props.attributes.perPage
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
