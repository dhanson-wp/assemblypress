/* eslint-disable */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;

	blocks.registerBlockType( 'assemblypress/member-profile', {
		edit: function ( props ) {
			var memberSource = props.attributes.memberSource || 'current';
			var usesSelectedMember =
				'selected' === memberSource || !! props.attributes.userId;

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Profile settings', 'assemblypress' ) },
						el( SelectControl, {
							label: __( 'Member to display', 'assemblypress' ),
							value: usesSelectedMember ? 'selected' : 'current',
							options: [
								{
									label: __(
										'Current member context',
										'assemblypress'
									),
									value: 'current',
								},
								{
									label: __(
										'Selected user ID',
										'assemblypress'
									),
									value: 'selected',
								},
							],
							onChange: function ( value ) {
								props.setAttributes( {
									memberSource: value,
									userId:
										'current' === value
											? 0
											: props.attributes.userId,
								} );
							},
						} ),
						el( TextControl, {
							label: __( 'User ID', 'assemblypress' ),
							type: 'number',
							help: usesSelectedMember
								? __(
										'Enter a WordPress user ID. If the user does not exist, the block renders empty.',
										'assemblypress'
								  )
								: __(
										'Switch to selected user ID to pin this block to one member.',
										'assemblypress'
								  ),
							disabled: ! usesSelectedMember,
							value: props.attributes.userId || '',
							onChange: function ( value ) {
								props.setAttributes( {
									userId: parseInt( value, 10 ) || 0,
								} );
							},
						} )
					)
				),
				el(
					'div',
					{ className: 'assemblypress-editor-preview' },
					el(
						'span',
						{ className: 'assemblypress-editor-preview__label' },
						__( 'Dynamic block', 'assemblypress' )
					),
					el( 'strong', {}, __( 'Member Profile', 'assemblypress' ) ),
					el(
						'p',
						{},
						__(
							'Renders member profile sections from AssemblyPress profile fields.',
							'assemblypress'
						)
					),
					el(
						'span',
						{ className: 'assemblypress-editor-preview__meta' },
						usesSelectedMember
							? props.attributes.userId
								? __( 'Specific user ID:', 'assemblypress' ) +
								  ' ' +
								  props.attributes.userId
								: __(
										'Selected user ID is required.',
										'assemblypress'
								  )
							: __(
									'Uses current member context, then the logged-in member.',
									'assemblypress'
							  )
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.element,
	window.wp.i18n
);
