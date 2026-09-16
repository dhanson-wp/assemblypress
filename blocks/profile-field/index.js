/* eslint-disable */
(function (wp) {
	const { createElement: el, useState, useEffect } = wp.element;
	const { __ }                                     = wp.i18n;
	const { InspectorControls, useBlockProps, InnerBlocks }                   = wp.blockEditor;
	const { PanelBody, ComboboxControl, ToggleControl, RangeControl, Notice } = wp.components;
	const ServerSideRender = wp.serverSideRender.default || wp.serverSideRender;
	wp.blocks.registerBlockType(
		'assemblypress/profile-field',
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
                controls.push(el(ToggleControl, {key: 'label', label: __('Show field label', 'assemblypress'), checked: props.attributes.showLabel !== false, onChange: function(value) { props.setAttributes({showLabel: value}); }}));
                if (props.attributes.fieldKey === 'display_name') {
                    controls.push(el(RangeControl, {key: 'heading', label: __('Heading level (0 for plain text)', 'assemblypress'), min: 0, max: 6, value: props.attributes.headingLevel || 0, onChange: function(value) {props.setAttributes({headingLevel: value});}}));
                }
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
				controls.push(
					el(
						ComboboxControl,
						{key: 'field', label: __( 'Profile field', 'assemblypress' ), value: props.attributes.fieldKey, options: fields.map(
							function (f) {
								return {value: f.key, label: f.label + (f.visibility === 'private' ? ' (private)' : '')};}
						), onChange : function (v) {
							props.setAttributes( {fieldKey : v || ''} );}}
					)
				);
				controls.push(
					el(
						ToggleControl,
						{key: 'link', label: __( 'Link to member profile', 'assemblypress' ), checked: ! ! props.attributes.linkToProfile, onChange: function (v) {
							props.setAttributes( {linkToProfile: v} );}}
					)
				);
				return el(
					'div',
					useBlockProps(),
					el( InspectorControls, {}, el( PanelBody, {title: __( 'Member display settings', 'assemblypress' )}, controls ) ),
					error && el( Notice, {status: 'warning', isDismissible: false}, __( 'Could not load preview choices. Reload to try again.', 'assemblypress' ) ),
					el( 'p', {}, props.attributes.userId ? __( 'Preview of the selected member.', 'assemblypress' ) : __( 'Automatic context: the frontend follows the member being viewed; this preview uses your account.', 'assemblypress' ) ),
					el( ServerSideRender, {block: 'assemblypress/profile-field', attributes: props.attributes} )
				);
			},
			save: function () {
				return null; }
		}
	);
})( window.wp );
