<?php
/**
 * First Church MCP Abilities — MCP resources + prompts.
 *
 * Passive context resources (content guide, taxonomy vocabulary) and ready-made editorial prompts.
 *
 * Loaded by ../firstchurch-mcp-abilities.php (WordPress does not auto-load
 * mu-plugin subdirectories). Procedural, global namespace, no autoloader —
 * matches the rest of the mu-plugin.
 *
 * @package FirstChurch\Mcp
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$can_read      = static function () { return current_user_can( 'read' ); };

		/* ---- MCP RESOURCES (passive context the client can read) ---- */

		wp_register_ability(
			'firstchurch/guide-content',
			array(
				'label'               => 'Content & style guide',
				'description'         => 'First Church Seattle editorial guide: when to use an event vs announcement vs post, the house voice, CTA/weight/expires conventions, images/alt-text, recurrence, and the draft-first workflow. Exposed as an MCP resource.',
				'category'            => 'firstchurch',
				'execute_callback'    => static function () {
					return fcmcp_resource_content_guide();
				},
				'permission_callback' => $can_read,
				'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'resource', 'uri' => 'firstchurch://guide/content', 'mimeType' => 'text/markdown' ) ),
			)
		);

		wp_register_ability(
			'firstchurch/vocabulary',
			array(
				'label'               => 'Taxonomy vocabulary',
				'description'         => 'The site\'s current, valid taxonomy terms (event categories and post categories) with slugs and counts, so content is filed under terms that already exist. Exposed as an MCP resource (JSON).',
				'category'            => 'firstchurch',
				'execute_callback'    => static function () {
					return wp_json_encode( fcmcp_resource_taxonomies_data() );
				},
				'permission_callback' => $can_read,
				'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'resource', 'uri' => 'firstchurch://vocabulary/taxonomies', 'mimeType' => 'application/json' ) ),
			)
		);

		/* ---- MCP PROMPTS (ready-made workflows) ---- */

		wp_register_ability(
			'firstchurch/prompt-review-queue',
			array(
				'label'               => 'Workflow: triage the review queue',
				'description'         => 'Walk through the draft/pending review queue and publish or flag each item.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'types' => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => array( 'events', 'announcements' ) ), 'description' => 'Limit to these content types (default: all).' ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_prompt_review_queue',
				'permission_callback' => $can_read,
				'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'prompt' ) ),
			)
		);

		wp_register_ability(
			'firstchurch/prompt-draft-announcement',
			array(
				'label'               => 'Workflow: draft an announcement',
				'description'         => 'Draft an on-brand announcement about a topic and save it as a draft for review.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'topic'   => array( 'type' => 'string', 'description' => 'What the announcement is about.' ),
						'cta_url' => array( 'type' => 'string', 'description' => 'Optional call-to-action URL (form, email, page).' ),
					),
					'required'             => array( 'topic' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_prompt_draft_announcement',
				'permission_callback' => $can_read,
				'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'prompt' ) ),
			)
		);

		wp_register_ability(
			'firstchurch/prompt-add-event',
			array(
				'label'               => 'Workflow: add an event',
				'description'         => 'Turn a freeform event description into a structured draft event (date/time/venue/recurrence/category/image).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'details' => array( 'type' => 'string', 'description' => 'Freeform description of the event (what/when/where).' ),
					),
					'required'             => array( 'details' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_prompt_add_event',
				'permission_callback' => $can_read,
				'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'prompt' ) ),
			)
		);
	}
);

/** The editorial/style guide, served as the firstchurch://guide/content resource. */
function fcmcp_resource_content_guide(): string {
	return <<<'MD'
# First Church Seattle — content guide

The house voice is warm, plain, and invitational. Lead with what's happening and
why someone would care; keep sentences short; avoid churchy jargon and hype.

## Which content type?
- **Event** (`create-event`) — something happening at a date/time (service, class,
  potluck, concert). Has start/end date & time, venue, address, optional
  registration URL, optional recurrence.
- **Announcement** (`create-announcement`) — news or an invitation that should
  surface on `/engage` and the pre-service carousel. Supports a call-to-action
  button (`cta_text`/`cta_url`), a `weight` (10 = featured, 20 = pinned), and an
  `expires` date (drops off the surfaces but stays in the news archive).
- **Post** (`create-post`) — a general blog/news article that isn't an
  Announcement.

## Draft-first
Everything an agent creates defaults to **draft** for a human to publish. Use
`status=pending` to queue for approval; only use `status=publish` when explicitly
asked. The `review-queue` tool lists everything awaiting a human.

## Conventions
- **Categories/terms:** file content under terms that already exist — read the
  `firstchurch://vocabulary/taxonomies` resource (or the `list-*` tools) first;
  only create a new term when nothing fits.
- **Images:** give every item a featured image. Reuse from `search-media`, or
  import an attribution-safe one via the stock-photo tools. Always set descriptive
  alt-text (`label-image`).
- **Recurrence (events):** weekly/monthly/yearly with an interval, specific
  weekdays, or "nth week of the month" — see `set-event-recurrence`.
- **Links/redirects:** when a page's URL changes, add a 301 with the redirect
  tools so old links keep working.
MD;
}

/** Live taxonomy vocabulary for the firstchurch://vocabulary/taxonomies resource. */
function fcmcp_resource_taxonomies_data(): array {
	$grab = static function ( $taxonomy ) {
		$out = array();
		foreach ( (array) get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) as $t ) {
			if ( is_object( $t ) ) {
				$out[] = array( 'slug' => $t->slug, 'name' => $t->name, 'count' => (int) $t->count );
			}
		}
		return $out;
	};
	return array(
		'event_categories' => $grab( 'ctc_event_category' ),
		'post_categories'  => $grab( 'category' ),
	);
}

/** Wrap a single user-role text message in the MCP prompt result shape. */
function fcmcp_prompt_result( string $description, string $text ): array {
	return array(
		'description' => $description,
		'messages'    => array(
			array( 'role' => 'user', 'content' => array( 'type' => 'text', 'text' => $text ) ),
		),
	);
}

function fcmcp_prompt_review_queue( $input = array() ) {
	$types = ( ! empty( $input['types'] ) && is_array( $input['types'] ) )
		? implode( ', ', array_map( 'sanitize_key', $input['types'] ) )
		: 'events and announcements';
	$text = "You are helping First Church Seattle staff clear the content review queue.\n\n"
		. "1. Call the `firstchurch/review-queue` tool (types: {$types}) to list every draft and pending item.\n"
		. "2. For each item, open it (get-event / get-announcement) and check: a clear title, correct date/time, an appropriate featured image, and on-brand copy (see the firstchurch://guide/content resource).\n"
		. "3. If it's ready, publish it with the matching set-*-status tool (status=publish). If it needs work, leave it as a draft and note what to fix.\n"
		. "4. Finish with a short summary: what you published, what you left for a human, and why.";
	return fcmcp_prompt_result( 'Triage the draft/pending review queue and publish or flag each item.', $text );
}

function fcmcp_prompt_draft_announcement( $input = array() ) {
	$topic = trim( (string) ( $input['topic'] ?? '' ) );
	$cta   = trim( (string) ( $input['cta_url'] ?? '' ) );
	$lines = array();
	$lines[] = 'Draft a First Church Seattle announcement' . ( '' !== $topic ? " about: {$topic}." : '.' );
	$lines[] = '';
	$lines[] = 'Use the house voice and conventions in the firstchurch://guide/content resource: warm, plain, invitational; lead with what is happening and why someone would care.';
	$lines[] = 'Write a short title and 1–3 short paragraphs of body (basic HTML is fine).';
	if ( '' !== $cta ) {
		$lines[] = "Add a call-to-action button: cta_url = {$cta} (choose a fitting cta_text such as \"RSVP\" or \"Learn more\").";
	} else {
		$lines[] = 'If there is a natural next step (a form, an email, a page), set cta_url + cta_text; otherwise omit the button.';
	}
	$lines[] = 'Set `expires` if it is time-bound, and only raise `weight` if it should be featured.';
	$lines[] = 'Create it with `firstchurch/create-announcement` as a DRAFT (status=draft) for a human to publish, then report the edit URL.';
	return fcmcp_prompt_result( 'Draft an on-brand announcement and save it for review.', implode( "\n", $lines ) );
}

function fcmcp_prompt_add_event( $input = array() ) {
	$details = trim( (string) ( $input['details'] ?? '' ) );
	$text = "Add a First Church Seattle event from these details:\n\n{$details}\n\n"
		. "Steps:\n"
		. "1. Extract the title, start_date (YYYY-MM-DD), start_time/end_time, venue, address, and registration_url if present.\n"
		. "2. If it repeats, set a recurrence rule (weekly/monthly/yearly with interval, weekly_days, or nth-week-of-month) — see firstchurch/set-event-recurrence.\n"
		. "3. Check firstchurch/list-event-categories (or the firstchurch://vocabulary/taxonomies resource) and assign the best-fitting category.\n"
		. "4. Pick or set a featured image (search-media, or a stock-photo import if needed).\n"
		. "5. Create it with firstchurch/create-event as a DRAFT for a human to publish, then report the edit URL.";
	return fcmcp_prompt_result( 'Turn a freeform event description into a structured draft event.', $text );
}

