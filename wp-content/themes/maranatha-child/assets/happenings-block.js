/**
 * "Happenings" block — build-free editor UI (plain JS against global wp.*).
 *
 * Dynamic block: this only collects attributes; the front end is rendered in PHP
 * by fcs_happenings_block_render() from the firstchurch-happenings spine. The
 * live preview uses ServerSideRender, so the editor shows the real cards.
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

	registerBlockType( 'firstchurch/happenings', {
		apiVersion: 3,
		title: __( 'Happenings', 'maranatha-child' ),
		description: __( 'Show a section of the Happenings feed (Featured, Upcoming events, or Recent announcements) as cards.', 'maranatha-child' ),
		icon: 'megaphone',
		category: 'widgets',
		attributes: {
			section: { type: 'string', default: 'featured' },
			count: { type: 'number', default: 3 },
			weeks: { type: 'number', default: 8 },
			days: { type: 'number', default: 30 },
			heading: { type: 'string', default: '' }
		},

		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;

			var windowControl = a.section === 'events'
				? el( c.RangeControl, {
						label: __( 'Look ahead (weeks)', 'maranatha-child' ),
						value: a.weeks, min: 1, max: 52,
						onChange: function ( v ) { set( { weeks: v } ); }
				  } )
				: el( c.RangeControl, {
						label: __( 'Look back (days)', 'maranatha-child' ),
						value: a.days, min: 1, max: 365,
						onChange: function ( v ) { set( { days: v } ); }
				  } );

			var controls = el(
				InspectorControls, {},
				el(
					c.PanelBody,
					{ title: __( 'Happenings', 'maranatha-child' ), initialOpen: true },
					el( c.SelectControl, {
						label: __( 'Section', 'maranatha-child' ),
						value: a.section,
						options: [
							{ label: __( 'Featured (by weight)', 'maranatha-child' ), value: 'featured' },
							{ label: __( 'Upcoming events', 'maranatha-child' ), value: 'events' },
							{ label: __( 'Recent announcements', 'maranatha-child' ), value: 'announcements' }
						],
						onChange: function ( v ) { set( { section: v } ); }
					} ),
					el( c.RangeControl, {
						label: __( 'How many', 'maranatha-child' ),
						value: a.count, min: 1, max: 12,
						onChange: function ( v ) { set( { count: v } ); }
					} ),
					el( c.TextControl, {
						label: __( 'Heading (optional)', 'maranatha-child' ),
						value: a.heading,
						onChange: function ( v ) { set( { heading: v } ); }
					} ),
					windowControl
				)
			);

			var preview = el( ServerSideRender, {
				block: 'firstchurch/happenings',
				attributes: a
			} );

			return el( 'div', useBlockProps(), controls, preview );
		},

		// Dynamic block: rendered in PHP.
		save: function () {
			return null;
		}
	} );
} )( window.wp );
