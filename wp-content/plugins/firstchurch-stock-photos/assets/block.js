/**
 * "Stock Photo Credit" block — build-free editor UI (plain JS against global wp.*).
 *
 * Dynamic block: it only stores an attachment id; the credit line is rendered in
 * PHP by fcsp_render_credit_block (the block's render_callback). The live preview
 * uses ServerSideRender, so the editor shows the real output. With no image
 * chosen, it falls back to the post's featured image.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var be = wp.blockEditor;
	var c = wp.components;
	var ServerSideRender = wp.serverSideRender;

	registerBlockType( 'firstchurch/stock-credit', {
		apiVersion: 3,
		title: __( 'Stock Photo Credit', 'firstchurch-stock-photos' ),
		description: __( 'Attribution for a stock photo imported via Stock Photos.', 'firstchurch-stock-photos' ),
		icon: 'tag',
		category: 'widgets',
		attributes: {
			id: { type: 'number', default: 0 }
		},

		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = be.useBlockProps();

			var controls = el(
				be.InspectorControls,
				{},
				el(
					c.PanelBody,
					{ title: __( 'Image', 'firstchurch-stock-photos' ), initialOpen: true },
					el(
						be.MediaUploadCheck,
						{},
						el( be.MediaUpload, {
							allowedTypes: [ 'image' ],
							value: a.id,
							onSelect: function ( media ) {
								set( { id: media && media.id ? media.id : 0 } );
							},
							render: function ( open ) {
								return el(
									c.Button,
									{ variant: 'secondary', onClick: open.open },
									a.id ? __( 'Change image', 'firstchurch-stock-photos' ) : __( 'Select image', 'firstchurch-stock-photos' )
								);
							}
						} )
					),
					a.id
						? el(
								c.Button,
								{ variant: 'link', isDestructive: true, onClick: function () { set( { id: 0 } ); } },
								__( 'Use the featured image instead', 'firstchurch-stock-photos' )
							)
						: null,
					el(
						'p',
						{ className: 'description' },
						__( 'With no image selected, the post’s featured image is credited.', 'firstchurch-stock-photos' )
					)
				)
			);

			var preview = el( ServerSideRender, {
				block: 'firstchurch/stock-credit',
				attributes: a
			} );

			return el( 'div', blockProps, controls, preview );
		},

		// Dynamic block — front end comes from the PHP render_callback.
		save: function () {
			return null;
		}
	} );
} )( window.wp );
