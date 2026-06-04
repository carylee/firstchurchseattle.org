/**
 * "Breeze Form" block — build-free editor UI (plain JS against global wp.*).
 *
 * Dynamic block: this only collects attributes; the front end is rendered in
 * PHP by Shortcode::render (via the block's render_callback). The live preview
 * uses ServerSideRender, so what you see in the editor is the real output.
 *
 * The form list comes from window.fcbfForms (localized in PHP from the synced
 * list).
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var c = wp.components;
	var ServerSideRender = wp.serverSideRender;

	var forms = window.fcbfForms || [];
	var formOptions = [ { label: __( '— Select a form —', 'firstchurch-breeze-forms' ), value: '' } ].concat(
		forms.map( function ( f ) {
			return { label: f.name, value: f.id };
		} )
	);

	function chooseForm( setAttributes, id ) {
		var match = forms.filter( function ( f ) {
			return f.id === id;
		} )[ 0 ];
		setAttributes( { id: id, slug: match ? match.slug : '' } );
	}

	registerBlockType( 'firstchurch/breeze-form', {
		apiVersion: 3,
		title: __( 'Breeze Form', 'firstchurch-breeze-forms' ),
		description: __( 'Embed or link to a Breeze form.', 'firstchurch-breeze-forms' ),
		icon: 'feedback',
		category: 'widgets',
		attributes: {
			slug: { type: 'string', default: '' },
			id: { type: 'string', default: '' },
			mode: { type: 'string', default: 'button' },
			label: { type: 'string', default: 'Open form' },
			newTab: { type: 'boolean', default: true },
			height: { type: 'number', default: 0 },
			maxWidth: { type: 'number', default: 0 }
		},

		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;

			var settings = el(
				InspectorControls,
				{},
				el(
					c.PanelBody,
					{ title: __( 'Form', 'firstchurch-breeze-forms' ), initialOpen: true },
					el( c.SelectControl, {
						label: __( 'Breeze form', 'firstchurch-breeze-forms' ),
						value: a.id,
						options: formOptions,
						onChange: function ( id ) {
							chooseForm( set, id );
						}
					} ),
					el( c.SelectControl, {
						label: __( 'Display as', 'firstchurch-breeze-forms' ),
						value: a.mode,
						options: [
							{ label: __( 'Button (links out)', 'firstchurch-breeze-forms' ), value: 'button' },
							{ label: __( 'Embed (in-page iframe)', 'firstchurch-breeze-forms' ), value: 'embed' }
						],
						onChange: function ( mode ) {
							set( { mode: mode } );
						}
					} )
				),
				a.mode === 'button'
					? el(
							c.PanelBody,
							{ title: __( 'Button', 'firstchurch-breeze-forms' ), initialOpen: false },
							el( c.TextControl, {
								label: __( 'Button label', 'firstchurch-breeze-forms' ),
								value: a.label,
								onChange: function ( v ) {
									set( { label: v } );
								}
							} ),
							el( c.ToggleControl, {
								label: __( 'Open in a new tab', 'firstchurch-breeze-forms' ),
								checked: a.newTab,
								onChange: function ( v ) {
									set( { newTab: v } );
								}
							} )
					  )
					: el(
							c.PanelBody,
							{ title: __( 'Embed', 'firstchurch-breeze-forms' ), initialOpen: false },
							el( c.RangeControl, {
								label: __( 'Height (px)', 'firstchurch-breeze-forms' ),
								value: a.height || 800,
								min: 300,
								max: 2000,
								onChange: function ( v ) {
									set( { height: v } );
								}
							} ),
							el( c.RangeControl, {
								label: __( 'Max width (px)', 'firstchurch-breeze-forms' ),
								value: a.maxWidth || 680,
								min: 320,
								max: 1200,
								onChange: function ( v ) {
									set( { maxWidth: v } );
								}
							} )
					  )
			);

			var body;
			if ( ! a.id ) {
				body = el(
					c.Placeholder,
					{
						icon: 'feedback',
						label: __( 'Breeze Form', 'firstchurch-breeze-forms' ),
						instructions: __( 'Choose which Breeze form to show.', 'firstchurch-breeze-forms' )
					},
					el( c.SelectControl, {
						value: a.id,
						options: formOptions,
						onChange: function ( id ) {
							chooseForm( set, id );
						}
					} )
				);
			} else {
				body = el( ServerSideRender, {
					block: 'firstchurch/breeze-form',
					attributes: a
				} );
			}

			return el( 'div', useBlockProps(), settings, body );
		},

		// Dynamic block: rendered in PHP.
		save: function () {
			return null;
		}
	} );
} )( window.wp );
