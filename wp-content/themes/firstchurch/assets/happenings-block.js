/**
 * "Happenings" block — build-free editor UI (plain JS against global wp.*).
 *
 * Dynamic block: this only collects attributes; the front end is rendered in PHP
 * by fcs_happenings_block_render() from the firstchurch-happenings spine. The
 * live preview uses ServerSideRender, so the editor shows the real cards.
 *
 * This is a classic editor script (registered with wp-* deps), not an ES module
 * — it relies on the global `wp` the block editor provides. The front-end
 * islands under assets/js/ are the module side of the theme.
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

	registerBlockType('firstchurch/happenings', {
		apiVersion: 3,
		title: __('Happenings', 'firstchurch'),
		description: __(
			'Show a section of the Happenings feed (Featured, Upcoming events, Weekly rhythms, Groups, or Recent announcements).',
			'firstchurch',
		),
		icon: 'megaphone',
		category: 'widgets',
		attributes: {
			section: { type: 'string', default: 'featured' },
			count: { type: 'number', default: 3 },
			weeks: { type: 'number', default: 8 },
			days: { type: 'number', default: 30 },
			heading: { type: 'string', default: '' },
			excludeFeatured: { type: 'boolean', default: false },
		},

		edit(props) {
			const a = props.attributes;
			const set = props.setAttributes;

			// Only meaningful for the recency list: drop items already promoted
			// into a Featured block on the same page (weight > 0) so they don't
			// show twice.
			const excludeControl =
				a.section === 'featured'
					? null
					: el(c.ToggleControl, {
							label: __('Hide items already featured', 'firstchurch'),
							checked: !!a.excludeFeatured,
							onChange: (v) => set({ excludeFeatured: v }),
						});

			const windowControl =
				a.section === 'events' || a.section === 'rhythms' || a.section === 'groups'
					? el(c.RangeControl, {
							label: __('Look ahead (weeks)', 'firstchurch'),
							value: a.weeks,
							min: 1,
							max: 52,
							onChange: (v) => set({ weeks: v }),
						})
					: el(c.RangeControl, {
							label: __('Look back (days)', 'firstchurch'),
							value: a.days,
							min: 1,
							max: 365,
							onChange: (v) => set({ days: v }),
						});

			const controls = el(
				InspectorControls,
				{},
				el(
					c.PanelBody,
					{ title: __('Happenings', 'firstchurch'), initialOpen: true },
					el(c.SelectControl, {
						label: __('Section', 'firstchurch'),
						value: a.section,
						options: [
							{ label: __('Featured (by weight)', 'firstchurch'), value: 'featured' },
							{ label: __('Upcoming events (one-offs)', 'firstchurch'), value: 'events' },
							{ label: __('Weekly rhythms (strip)', 'firstchurch'), value: 'rhythms' },
							{ label: __('Groups & gatherings', 'firstchurch'), value: 'groups' },
							{ label: __('Recent announcements', 'firstchurch'), value: 'announcements' },
						],
						onChange: (v) => set({ section: v }),
					}),
					el(c.RangeControl, {
						label: __('How many', 'firstchurch'),
						value: a.count,
						min: 1,
						max: 12,
						onChange: (v) => set({ count: v }),
					}),
					el(c.TextControl, {
						label: __('Heading (optional)', 'firstchurch'),
						value: a.heading,
						onChange: (v) => set({ heading: v }),
					}),
					windowControl,
					excludeControl,
				),
			);

			const preview = el(ServerSideRender, {
				block: 'firstchurch/happenings',
				attributes: a,
			});

			return el('div', useBlockProps(), controls, preview);
		},

		// Dynamic block: rendered in PHP.
		save() {
			return null;
		},
	});
})(window.wp);
