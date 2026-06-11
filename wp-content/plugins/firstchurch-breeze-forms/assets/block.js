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
			title: { type: 'string', default: '' },
			label: { type: 'string', default: 'Open form' },
			newTab: { type: 'boolean', default: true },
			height: { type: 'number', default: 0 },
			maxWidth: { type: 'number', default: 0 },
			backgroundColor: { type: 'string', default: '' },
			borderColor: { type: 'string', default: '' },
			borderWidth: { type: 'string', default: '' },
			buttonColor: { type: 'string', default: '' }
		},

		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;

			var selected = forms.filter( function ( f ) {
				return f.id === a.id;
			} )[ 0 ];
			var desc = selected && selected.description ? selected.description : '';
			var hint = desc.length > 160 ? desc.slice( 0, 160 ) + '…' : desc;

			// "Native" (Mode 3 — our in-theme form posting straight to Breeze) is
			// only offered for forms with a baked field contract on the server.
			var modeOptions = [
				{ label: __( 'Button (links out)', 'firstchurch-breeze-forms' ), value: 'button' },
				{ label: __( 'Embed (in-page iframe)', 'firstchurch-breeze-forms' ), value: 'embed' }
			];
			if ( selected && selected.native ) {
				modeOptions.push( { label: __( 'Native (in-page form)', 'firstchurch-breeze-forms' ), value: 'native' } );
			}

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
						help: hint || undefined,
						onChange: function ( id ) {
							chooseForm( set, id );
						}
					} ),
					el( c.SelectControl, {
						label: __( 'Display as', 'firstchurch-breeze-forms' ),
						value: a.mode,
						options: modeOptions,
						onChange: function ( mode ) {
							set( { mode: mode } );
						}
					} )
				),
				a.mode === 'native'
					? el(
							c.PanelBody,
							{ title: __( 'Native form', 'firstchurch-breeze-forms' ), initialOpen: false },
							el( 'p', { style: { color: '#757575', marginTop: 0 } },
								__( 'Renders in-page and posts directly to Breeze — no iframe. Fields come from the form\'s saved layout.', 'firstchurch-breeze-forms' )
							),
							el( c.TextControl, {
								label: __( 'Heading (optional)', 'firstchurch-breeze-forms' ),
								value: a.title,
								help: __( 'Overrides the form\'s own title above the fields.', 'firstchurch-breeze-forms' ),
								onChange: function ( v ) {
									set( { title: v } );
								}
							} )
					  )
					: a.mode === 'button'
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
							el( 'p', { style: { color: '#757575', marginTop: 0 } },
								__( 'The embedded form auto-sizes to its content. Colors are hex (e.g. 92b765); leave blank for Breeze defaults.', 'firstchurch-breeze-forms' )
							),
							el( c.RangeControl, {
								label: __( 'Max width (px)', 'firstchurch-breeze-forms' ),
								value: a.maxWidth || 680,
								min: 320,
								max: 1200,
								onChange: function ( v ) {
									set( { maxWidth: v } );
								}
							} ),
							el( c.TextControl, {
								label: __( 'Button color', 'firstchurch-breeze-forms' ),
								value: a.buttonColor,
								onChange: function ( v ) {
									set( { buttonColor: v } );
								}
							} ),
							el( c.TextControl, {
								label: __( 'Background color', 'firstchurch-breeze-forms' ),
								value: a.backgroundColor,
								onChange: function ( v ) {
									set( { backgroundColor: v } );
								}
							} ),
							el( c.TextControl, {
								label: __( 'Border color', 'firstchurch-breeze-forms' ),
								value: a.borderColor,
								onChange: function ( v ) {
									set( { borderColor: v } );
								}
							} ),
							el( c.TextControl, {
								label: __( 'Border width (px)', 'firstchurch-breeze-forms' ),
								value: a.borderWidth,
								onChange: function ( v ) {
									set( { borderWidth: v } );
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
			} else if ( a.mode === 'embed' ) {
				// The live embed needs Breeze's form_embed.js, which doesn't run
				// in the editor preview — so show an informative placeholder.
				body = el(
					c.Placeholder,
					{
						icon: 'feedback',
						label: ( selected ? selected.name : __( 'Breeze form', 'firstchurch-breeze-forms' ) ),
						instructions: __( 'Embedded form — it appears and auto-sizes on the published page.', 'firstchurch-breeze-forms' )
					}
				);
			} else if ( a.mode === 'native' ) {
				// The native form carries a live nonce and submit handler — those
				// belong on the published page, not the editor preview.
				body = el(
					c.Placeholder,
					{
						icon: 'feedback',
						label: ( a.title || ( selected ? selected.name : __( 'Breeze form', 'firstchurch-breeze-forms' ) ) ),
						instructions: __( 'Native in-page form — it renders in the site theme and posts to Breeze on the published page.', 'firstchurch-breeze-forms' )
					}
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
