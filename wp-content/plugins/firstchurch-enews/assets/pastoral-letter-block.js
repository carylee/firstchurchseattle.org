/**
 * "From the Pastor" block — build-free editor UI (plain JS against global wp.*).
 *
 * Dynamic block: this only collects attributes (the look-back window + the
 * fallback prose); the front end + email are rendered in PHP from the latest
 * `pastoral-letters` post (inc/pastoral-letter.php). The canvas uses
 * ServerSideRender so staff see the REAL letter that will be sent; when no recent
 * letter exists the placeholder points them at the fallback in the sidebar.
 *
 * A classic editor script (registered with wp-* deps), mirroring the theme's
 * happenings-block.js — it relies on the global `wp` the block editor provides.
 */
((wp) => {
	// biome-ignore lint/suspicious/noRedundantUseStrict: classic (non-module) editor script — strict mode is not implied here.
	'use strict';

	const el = wp.element.createElement;
	const { __ } = wp.i18n;
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const c = wp.components;
	const ServerSideRender = wp.serverSideRender;

	registerBlockType('firstchurch/pastoral-letter', {
		apiVersion: 3,
		title: __('From the Pastor', 'firstchurch-enews'),
		description: __(
			'Shows the latest Pastoral Letter post automatically (when one was published within the look-back window). If there is none, the fallback message you write here is used.',
			'firstchurch-enews',
		),
		icon: 'email',
		category: 'widgets',
		attributes: {
			days: { type: 'number', default: 5 },
			fallback: { type: 'string', default: '' },
		},

		edit(props) {
			const a = props.attributes;
			const set = props.setAttributes;

			const controls = el(
				InspectorControls,
				{},
				el(
					c.PanelBody,
					{ title: __('From the Pastor', 'firstchurch-enews'), initialOpen: true },
					el(c.RangeControl, {
						label: __('Look back (days)', 'firstchurch-enews'),
						help: __(
							'Auto-show a Pastoral Letter post published within this many days.',
							'firstchurch-enews',
						),
						value: a.days,
						min: 1,
						max: 21,
						onChange: (v) => set({ days: v }),
					}),
					el(c.TextareaControl, {
						label: __('Fallback message', 'firstchurch-enews'),
						help: __(
							'Used only when no recent Pastoral Letter post exists. Blank lines separate paragraphs.',
							'firstchurch-enews',
						),
						value: a.fallback,
						rows: 6,
						onChange: (v) => set({ fallback: v }),
					}),
				),
			);

			const preview = el(ServerSideRender, {
				block: 'firstchurch/pastoral-letter',
				attributes: a,
				EmptyResponsePlaceholder: () =>
					el(c.Placeholder, {
						icon: 'email',
						label: __('From the Pastor', 'firstchurch-enews'),
						instructions: __(
							'No Pastoral Letter post within the look-back window. Write a fallback message in the block settings (right sidebar), or publish a post in the Pastoral Letters category.',
							'firstchurch-enews',
						),
					}),
			});

			return el('div', useBlockProps(), controls, preview);
		},

		// Dynamic block: rendered in PHP.
		save() {
			return null;
		},
	});
})(window.wp);
