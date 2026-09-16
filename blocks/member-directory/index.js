/* eslint-disable */
(function (wp) {
	const { createElement: el, useState, useEffect } = wp.element;
	const { __ }                                     = wp.i18n;
	const { InspectorControls, useBlockProps, InnerBlocks }                   = wp.blockEditor;
	const { PanelBody, ComboboxControl, ToggleControl, RangeControl, Notice } = wp.components;
	const ServerSideRender = wp.serverSideRender.default || wp.serverSideRender;
	wp.blocks.registerBlockType(
		'assemblypress/member-directory',
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
				controls.push(
					el(
						RangeControl,
						{key: 'count', label: __( 'Members per page', 'assemblypress' ), value: props.attributes.perPage, min: 1, max: 100, onChange: function (v) {
							props.setAttributes( {perPage: v} );}}
					)
				);
				controls.push(
					el(
						ToggleControl,
						{key: 'audience', label: __( 'Include all site accounts', 'assemblypress' ), help: __( 'By default, only subscribers appear. Enabling this also lists editors, administrators, and other roles.', 'assemblypress' ), checked: ! ! props.attributes.includeAllAccounts, onChange: function (v) {
							props.setAttributes( {includeAllAccounts: v} );}}
					)
				);
				return el(
					'div',
					useBlockProps(),
					el( InspectorControls, {}, el( PanelBody, {title: __( 'Member display settings', 'assemblypress' )}, controls ) ),
					error && el( Notice, {status: 'warning', isDismissible: false}, __( 'Could not load preview choices. Reload to try again.', 'assemblypress' ) ),
					el( 'p', {}, __( 'Member card layout. Card-only layouts repeat for each member. For a fully arranged directory, use the Member directory pattern with separate search, results, and pagination blocks. Keep fields on Automatic member context.', 'assemblypress' ) ),
					el( InnerBlocks, {template: [['core/group', {layout: {type: 'flex'}}, [['assemblypress/profile-field', {fieldKey: 'avatar', linkToProfile: true}], ['assemblypress/profile-field', {fieldKey: 'display_name', linkToProfile: true}]]]], templateLock: false} )
				);
			},
			save: function () {
				return el( InnerBlocks.Content ); }
		}
	);
})( window.wp );
