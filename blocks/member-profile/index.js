/* eslint-disable */
(function (wp) {
	const { createElement: el, useState, useEffect } = wp.element;
	const { __ }                                     = wp.i18n;
	const { InspectorControls, useBlockProps, InnerBlocks }                   = wp.blockEditor;
	const { PanelBody, ComboboxControl, ToggleControl, RangeControl, Notice } = wp.components;
	const ServerSideRender = wp.serverSideRender.default || wp.serverSideRender;
	wp.blocks.registerBlockType(
		'assemblypress/member-profile',
		{
			edit: function (props) {
				const [members, setMembers] = useState( [] );
				const [fields, setFields]   = useState( [] );
				const [search, setSearch]   = useState( '' );
				const [error, setError]     = useState( false );
				useEffect(
					function () {
						let active = true;
						wp.apiFetch( {path: '/assemblypress/v1/members?per_page=100&search=' + encodeURIComponent( search )} ).then(
							function (data) {
								if (active) {
									setMembers( data.members ); setError( false ); }
							}
						).catch(
							function () {
								if (active) {
									setError( true );
								} }
						);
						return function () {
							active = false; };
					},
					[search]
				);
				useEffect(
					function () {
						let active = true;
						wp.apiFetch( {path: '/assemblypress/v1/profile-fields'} ).then(
							function (data) {
								if (active) {
									setFields( data );
								} }
						).catch(
							function () {
								if (active) {
									setError( true );
								} }
						);
						return function () {
								active = false; };
					},
					[]
				);
				const controls = [];
 controls.push(el(ToggleControl, {key: 'fields', label: __('Show all public fields', 'assemblypress'), help: __('Turn off to compose individual Profile Field blocks below this member heading.', 'assemblypress'), checked: props.attributes.showFields !== false, onChange: function(v) {props.setAttributes({showFields: v});}}));
				controls.push(
					el(
						ComboboxControl,
						{key: 'member', label: __( 'Member', 'assemblypress' ), help: __( 'Automatic uses the member being viewed. Choose a named member only for a fixed profile.', 'assemblypress' ), value: String( props.attributes.userId || 0 ), options: [{value: '0', label: __( 'Automatic member context', 'assemblypress' )}].concat(
							members.map(
								function (m) {
									return {value: String( m.id ), label: m.display_name}; }
							)
						), onFilterValueChange: setSearch, onChange: function (v) {
							props.setAttributes( {userId: Number( v ) || 0} ); }}
					)
				);
				return el(
					'div',
					useBlockProps(),
					el( InspectorControls, {}, el( PanelBody, {title: __( 'Member display settings', 'assemblypress' )}, controls ) ),
					error && el( Notice, {status: 'warning', isDismissible: false}, __( 'Could not load preview choices. Reload to try again.', 'assemblypress' ) ),
					el( 'p', {}, props.attributes.userId ? __( 'Preview of the selected member.', 'assemblypress' ) : __( 'Automatic context: the frontend follows the member being viewed; this preview uses your account.', 'assemblypress' ) ),
					el( ServerSideRender, {block: 'assemblypress/member-profile', attributes: props.attributes} )
				);
			},
			save: function () {
				return null; }
		}
	);
})( window.wp );
