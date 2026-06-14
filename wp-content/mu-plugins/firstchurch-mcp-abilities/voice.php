<?php
/**
 * First Church MCP Abilities — the church-voice layer.
 *
 * One canonical house voice (fc_church_voice) on top of WordPress 7.0's core AI
 * Client (wp_ai_client_prompt), exposed as a small set of abilities that EVERY
 * surface shares: the intake processor (classify + extract a forwarded email
 * into a draft), and the block editor ("rewrite this selection in our voice",
 * title/excerpt suggestions). Registering them as MCP-public abilities means an
 * interactive MCP agent can call the exact same capabilities.
 *
 * Provider-agnostic: the model + key are configured in Settings → Connectors.
 * Today that's the Google (Gemini) connector. No key lives in this code.
 *
 * Loaded by ../firstchurch-mcp-abilities.php. Procedural, global namespace —
 * matches the rest of the mu-plugin.
 *
 * @package FirstChurch\Mcp
 */

defined( 'ABSPATH' ) || exit;

/** Option holding a live, admin-edited override of the house voice. */
const FCMCP_VOICE_OPTION = 'fc_church_voice';

/**
 * The canonical First Church Seattle house voice — the single source of truth fed
 * as the system instruction to every AI call here, and exposed as the
 * guide-church-voice MCP resource. Returns the live admin-edited override if one
 * is saved, otherwise the built-in default. Edit it at Tools → Church Voice.
 */
function fc_church_voice(): string {
	$custom = trim( (string) get_option( FCMCP_VOICE_OPTION, '' ) );
	return '' !== $custom ? $custom : fcmcp_church_voice_default();
}

/**
 * The built-in default house voice — the seed shipped in code and the fallback
 * whenever no override is saved (so the voice is never empty and survives a DB
 * loss / ddev pull-prod). Derived from the 2026 First Church Weekly News corpus
 * (../enews/STYLE_GUIDE_2026.md) and the firstchurchnews extraction prompt.
 */
function fcmcp_church_voice_default(): string {
	return <<<'MD'
You write for First United Methodist Church of Seattle ("First Church"), a
progressive Christian congregation (firstchurchseattle.org). The house voice is
warm, plain, and invitational. Two rules above all: welcome before information,
and invitation never command. Name activist/justice work directly — do not
euphemize it. Never invent facts (dates, names, prices, URLs) that aren't given.

VOICE & PERSON
- Second-person plural and warm-institutional; frame programs as invitations,
  not requirements: "Members must RSVP by Friday" → "Let us know by Friday if
  you'd like to join us."
- First-person singular only inside a signed pastoral note.
- VARY YOUR OPENING — this matters. Do NOT begin items with "We invite you" or
  "Join us"; those openers have become formulaic and repetitive across the site.
  Open instead with the concrete specific — what it is, when, who it's for, or
  why it matters ("Pastor DJ preaches his first Sunday on July 12."; "Bring a
  dish and a friend…"; "Three Tuesdays this fall, we'll read…") — and let the
  warmth carry through the body. Reserve an explicit "we invite you" / "join us"
  for the rare case where it genuinely adds something the facts don't.

TITLES
- For a dated item, use "What | When" with a vertical bar:
  - "What" is title-case, specific, no church-name prefix, no "FW:".
  - "When" = weekday + month + day for a one-off ("Sunday, January 18"),
    abbreviated months for a multi-date series ("Feb. 1, 8, 15, 22"), or
    "Beginning [date]" for an ongoing series. A recurring weekly event uses
    day-of-week + time ("Fridays at 1:00 p.m. on Zoom").
  - No trailing period, no exclamation points, no ALL CAPS unless the event is
    literally named that way (e.g., "NO KINGS Rally").
- For an announcement with no single date, drop the bar — a specific noun-phrase
  title.

BODY
- 2–6 sentences for a standard announcement; up to ~250 words for a major teach
  (Lunch & Learn, retreat, stewardship). Don't pad.
- Names: role/title on first mention. "Rev." (with the period) for clergy; lay
  leaders get first + last on first reference.
- Numbers: spell out one through nine, numerals for 10+. Always numerals for
  ages, times, dates, and prices.
- Times: lowercase with periods and an en-space — "10:30 a.m.", "6:30 p.m."
  Normalize "10:30AM" / "10:30 am" → "10:30 a.m."
- Dates in prose: "January 18" (not "Jan. 18" — reserve abbreviations for titles).
- Use curly quotes (“ ”) and curly apostrophes (’). En-dash for ranges
  ("1–3 p.m."), em-dash for asides.

MARKUP
- Light HTML only: <p>, <a href>, <strong>, <em>, <ul>/<ol>/<li>. No headings
  (the title is the heading). One <p> per logical paragraph.
- Strip email signatures, quoted reply chains, forwarding boilerplate ("Begin
  forwarded message", "Sent from my iPhone"), legal footers, and tracking junk.

CALLS TO ACTION
- At most one CTA. Use the closest of these four canonical labels:
  "Sign Up Here" (sign-ups), "Register Here" (registration/payment),
  "Learn More" (background/longer read), "Give Here" (donations).
- For a "contact X" ask, use a mailto: link with text like "Email Pastor Jackie".
- NEVER write "Click here" — name the destination instead.

CANONICAL NAMES (use these exact forms)
- Lunch & Learn · Faith in Action · Defend Migrants Alliance (DMA, spelled out on
  first mention) · Faith Action Network (FAN, spelled out on first mention) ·
  First Church Forward · Sione’s Closet · Minute for Mother Earth ·
  Shared Breakfast.
- The church: "First Church" in body prose; "First United Methodist Church of
  Seattle" on first formal mention.
MD;
}

/**
 * Call the core AI Client with the house voice as the system instruction.
 *
 * @param string      $user   The user content (text to act on).
 * @param string|null $schema JSON schema (PHP array, JSON-encoded) for a
 *                            structured response; null for plain text.
 * @param array       $opts   { system?:string, temperature?:float }
 * @return string|array|WP_Error  Decoded array when $schema is given, the text
 *                                otherwise, or WP_Error if the AI call fails
 *                                (e.g. no provider configured in Connectors).
 */
function fcmcp_voice_generate( string $user, ?array $schema = null, array $opts = array() ) {
	if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
		return new WP_Error( 'ai_unavailable', 'The WordPress AI Client is not available.' );
	}

	$system = $opts['system'] ?? fc_church_voice();

	try {
		$builder = wp_ai_client_prompt( $user )->using_system_instruction( $system );
		if ( isset( $opts['temperature'] ) ) {
			$builder = $builder->using_temperature( (float) $opts['temperature'] );
		}
		if ( null !== $schema ) {
			$builder = $builder->as_json_response( $schema );
		}
		$text = $builder->generate_text();
	} catch ( \Throwable $e ) {
		return new WP_Error(
			'ai_failed',
			'AI generation failed: ' . $e->getMessage()
			. ' (is a provider key set in Settings → Connectors?)'
		);
	}

	// Core generator methods RETURN WP_Error on failure (e.g. no provider key
	// configured) rather than throwing — so check before using the value.
	if ( is_wp_error( $text ) ) {
		return $text;
	}

	if ( null === $schema ) {
		return (string) $text;
	}

	$data = json_decode( (string) $text, true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'ai_bad_json', 'AI returned an unparseable structured response.' );
	}
	return $data;
}

/* ---- Capability functions (shared by intake + the editor + MCP agents) ---- */

/**
 * Rewrite a passage in the house voice. The editor's "Rewrite in church voice"
 * button and any agent call land here.
 */
function fcmcp_rewrite_in_voice( $input = array() ) {
	$text = trim( (string) ( $input['text'] ?? '' ) );
	if ( '' === $text ) {
		return new WP_Error( 'missing_text', 'Provide text to rewrite.' );
	}
	$kind = (string) ( $input['kind'] ?? '' ); // optional hint: title|body|announcement|selection
	$hint = '' !== $kind ? " This text is a(n) {$kind}." : '';

	$out = fcmcp_voice_generate(
		"Rewrite the passage below in the First Church house voice. Preserve every fact "
		. "(dates, names, prices, links) exactly; change only register and phrasing. Do NOT "
		. "add a title, heading, or preamble, and do NOT wrap it in quotes. Return ONLY the "
		. "rewritten passage, matching the input's format — plain text in, plain text out; "
		. "keep any inline links.{$hint}\n\n---\n{$text}"
	);
	if ( is_wp_error( $out ) ) {
		return $out;
	}
	return array( 'text' => trim( (string) $out ) );
}

/** Suggest a few on-voice titles for a piece of content. */
function fcmcp_suggest_title( $input = array() ) {
	$content = trim( (string) ( $input['content'] ?? '' ) );
	if ( '' === $content ) {
		return new WP_Error( 'missing_content', 'Provide content to title.' );
	}
	$schema = array(
		'type'                 => 'object',
		'properties'           => array(
			'titles' => array(
				'type'     => 'array',
				'items'    => array( 'type' => 'string' ),
				'minItems' => 3,
				'maxItems' => 5,
			),
		),
		'required'             => array( 'titles' ),
		'additionalProperties' => false,
	);
	$out = fcmcp_voice_generate(
		"Propose 3–5 title options for the following content, following the house "
		. "title rules (the \"What | When\" form for dated items; a specific "
		. "noun-phrase for undated ones).\n\n---\n{$content}",
		$schema
	);
	return is_wp_error( $out ) ? $out : array( 'titles' => array_values( (array) ( $out['titles'] ?? array() ) ) );
}

/** Draft a short (~160-char) on-voice excerpt/teaser for content. */
function fcmcp_draft_excerpt( $input = array() ) {
	$content = trim( (string) ( $input['content'] ?? '' ) );
	if ( '' === $content ) {
		return new WP_Error( 'missing_content', 'Provide content to summarize.' );
	}
	$out = fcmcp_voice_generate(
		"Write a single warm, plain teaser sentence (~160 characters, no HTML) that "
		. "invites the reader in. Return ONLY the sentence.\n\n---\n{$content}"
	);
	return is_wp_error( $out ) ? $out : array( 'excerpt' => trim( (string) $out ) );
}

/**
 * Classify a raw intake item: is it worth publicizing, and as what? The first
 * gate of the intake processor — room-booking noise ("team meeting", "choir
 * rehearsal") classifies as internal and is dismissed, not drafted.
 */
function fcmcp_intake_classify( $input = array() ) {
	$text = trim( (string) ( $input['text'] ?? '' ) );
	if ( '' === $text ) {
		return new WP_Error( 'missing_text', 'Provide the intake text to classify.' );
	}
	$schema = array(
		'type'                 => 'object',
		'properties'           => array(
			'class'      => array( 'type' => 'string', 'enum' => array( 'publicity', 'internal' ) ),
			'target'     => array( 'type' => 'string', 'enum' => array( 'event', 'announcement', 'both', 'none' ) ),
			'confidence' => array( 'type' => 'number', 'minimum' => 0, 'maximum' => 1 ),
			'reason'     => array( 'type' => 'string' ),
		),
		'required'             => array( 'class', 'target', 'confidence', 'reason' ),
		'additionalProperties' => false,
	);
	$system = "You triage inbound requests for a church website. Most of these arrive via the "
		. "public Event Request Form, so BIAS TOWARD PUBLICITY — when unsure, choose publicity.\n\n"
		. "PUBLICITY = anything the congregation or public is invited to or should see: worship "
		. "services and liturgies (e.g. Sunday worship, Maundy Thursday, Good Friday, Easter), "
		. "concerts, classes, retreats, potlucks, youth/children events, volunteer asks, giving "
		. "drives, news. Judge by the EVENT ITSELF — IGNORE internal logistics in the submission "
		. "(staff attending, AV/Zoom needs, catering/Duuna, room, headcount, parking); those do "
		. "NOT make an event internal. A worship service is ALWAYS publicity even if staff and AV "
		. "are involved.\n\n"
		. "INTERNAL = only when the item itself is a working/coordination gathering with no public "
		. "audience: a committee/team/staff/board/SPRC meeting, a rehearsal, or a pure room "
		. "booking. If real people outside the organizing group would want to attend, it is "
		. "PUBLICITY, not internal.\n\n"
		. "Then pick the best website target: a dated 'event', an undated 'announcement', 'both', "
		. "or 'none' (only for genuinely internal items). Return strict JSON.";
	return fcmcp_voice_generate( $text, $schema, array( 'system' => $system, 'temperature' => 0.1 ) );
}

/**
 * Extract a publicity item into the create-event / create-announcement contract,
 * voice-corrected. Mirrors the old worker's schema (intents[] + notes). The
 * intake processor feeds the chosen intent(s) straight into fcmcp_create_event /
 * fcmcp_create_announcement, whose own sanitizers clean the values.
 */
function fcmcp_intake_extract( $input = array() ) {
	$text = trim( (string) ( $input['text'] ?? '' ) );
	if ( '' === $text ) {
		return new WP_Error( 'missing_text', 'Provide the intake text to extract.' );
	}
	$received = (string) ( $input['received_date'] ?? gmdate( 'Y-m-d' ) );
	$attachments = array_values( array_filter( array_map( 'strval', (array) ( $input['attachment_urls'] ?? array() ) ) ) );

	$event = array(
		'type'       => 'object',
		'properties' => array(
			'title'            => array( 'type' => 'string' ),
			'description'      => array( 'type' => 'string', 'description' => 'Light HTML, house voice.' ),
			'start_date'       => array( 'type' => 'string', 'description' => 'YYYY-MM-DD' ),
			'time'             => array( 'type' => 'string', 'description' => 'HH:MM 24h; omit if unknown' ),
			'time_text'        => array( 'type' => 'string' ),
			'venue'            => array( 'type' => 'string' ),
			'registration_url' => array( 'type' => 'string' ),
			'category'         => array( 'type' => 'string' ),
		),
		'required'   => array( 'title', 'start_date' ),
	);
	$announcement = array(
		'type'       => 'object',
		'properties' => array(
			'title'    => array( 'type' => 'string' ),
			'content'  => array( 'type' => 'string', 'description' => 'Light HTML, house voice.' ),
			'excerpt'  => array( 'type' => 'string' ),
			'cta_text' => array( 'type' => 'string' ),
			'cta_url'  => array( 'type' => 'string' ),
			'expires'  => array( 'type' => 'string', 'description' => 'YYYY-MM-DD' ),
		),
		'required'   => array( 'title', 'content' ),
	);
	$schema = array(
		'type'                 => 'object',
		'properties'           => array(
			'intents' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'kind'         => array( 'type' => 'string', 'enum' => array( 'event', 'announcement' ) ),
						'confidence'   => array( 'type' => 'number', 'minimum' => 0, 'maximum' => 1 ),
						'event'        => $event,
						'announcement' => $announcement,
					),
					'required'   => array( 'kind', 'confidence' ),
				),
			),
			'notes'   => array( 'type' => 'string', 'description' => 'For the human reviewer: guesses, gaps, a flyer to attach.' ),
			'gaps'    => array(
				'type'        => 'array',
				'description' => 'Specific things you were unsure about — each a short question for the human reviewer.',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'field'    => array( 'type' => 'string', 'description' => 'The field in doubt, e.g. venue, time, cost.' ),
						'question' => array( 'type' => 'string', 'description' => 'A short question to resolve it.' ),
					),
					'required'   => array( 'question' ),
				),
			),
		),
		'required'             => array( 'intents', 'notes' ),
		'additionalProperties' => false,
	);

	$system = fc_church_voice() . "\n\n"
		. "EXTRACTION TASK\n"
		. "Convert the item below into structured DRAFT intents. It usually describes ONE "
		. "thing — an event (a specific date people attend) or an announcement (news / a "
		. "standing call to action). Emit BOTH only when a multi-week campaign or series is "
		. "also news in its own right; when in doubt, emit one.\n"
		. "- Resolve relative dates against the received-date; output YYYY-MM-DD and HH:MM (24h). "
		. "If a resolved date is BEFORE the received-date, keep your best guess, set confidence "
		. "≤ 0.5, and flag it in notes.\n"
		. "- URLs: only emit a URL that appears verbatim in the text; normalize to https://. "
		. "Never invent a Breeze/registration/social URL — omit and note 'link needed'.\n"
		. "- Set an announcement `expires` when it's tied to a date or has a deadline.\n"
		. "- Do not set images here; if a flyer/graphic is implied, say so in notes.\n"
		. "- For each thing you genuinely guessed or couldn't pin down (an ambiguous "
		. "venue, a missing end-time, an unconfirmed cost), add a `gaps` entry: the "
		. "field plus a short question the reviewer could ask the submitter. Leave "
		. "`gaps` empty when you're confident.";

	$user = "received-date: {$received}\n";
	if ( $attachments ) {
		$user .= 'attachments: ' . implode( ', ', $attachments ) . "\n";
	}
	$user .= "\n{$text}";

	return fcmcp_voice_generate( $user, $schema, array( 'system' => $system, 'temperature' => 0.2 ) );
}

/**
 * Suggest 2–3 stock-photo search phrases for a draft. Titles make poor image
 * queries (proper nouns, dates, names), so this turns the title + description
 * into concrete VISUAL concepts — scenes, objects, light, nature, hands —
 * deliberately avoiding stock photos of people, which read "stocky" and risk a
 * tone-deaf mismatch. Used by the intake processor when a draft has no image.
 */
function fcmcp_suggest_image_queries( $input = array() ) {
	$title = trim( (string) ( $input['title'] ?? '' ) );
	$desc  = trim( (string) ( $input['description'] ?? '' ) );
	if ( '' === $title && '' === $desc ) {
		return new WP_Error( 'missing_content', 'Provide a title or description.' );
	}
	$schema = array(
		'type'                 => 'object',
		'properties'           => array(
			'queries' => array(
				'type'     => 'array',
				'items'    => array( 'type' => 'string' ),
				'minItems' => 1,
				'maxItems' => 3,
			),
		),
		'required'             => array( 'queries' ),
		'additionalProperties' => false,
	);
	$system = "You pick stock-photo search terms for a church website. Given an event "
		. "or announcement, return 2–3 short search phrases (2–4 words each) for a "
		. "warm, tasteful HERO image.\n"
		. "- Describe a VISUAL SCENE or OBJECT, never the literal title: 'Maundy "
		. "Thursday Service' → 'candlelit communion table', not 'Maundy Thursday'.\n"
		. "- Prefer scenes, objects, light, nature, food, hands. AVOID photos of "
		. "people's faces and anything that would look like a posed stock model.\n"
		. "- No proper nouns, dates, names, or church jargon. Return strict JSON.";
	$user = trim( "Title: {$title}\nDescription: {$desc}" );
	$out  = fcmcp_voice_generate( $user, $schema, array( 'system' => $system, 'temperature' => 0.3 ) );
	return is_wp_error( $out ) ? $out : array( 'queries' => array_values( (array) ( $out['queries'] ?? array() ) ) );
}

/* ---- Registration ---- */

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$mcp_public = array( 'mcp' => array( 'public' => true ) );
		$can_write  = static function () { return current_user_can( 'edit_posts' ); };

		wp_register_ability(
			'firstchurch/rewrite-in-voice',
			array(
				'label'               => 'Rewrite in church voice',
				'description'         => 'Rewrite a passage in the First Church house voice, preserving every fact. Used by the block-editor toolbar button and by agents.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'text' => array( 'type' => 'string', 'description' => 'The passage to rewrite.' ),
						'kind' => array( 'type' => 'string', 'description' => 'Optional hint: title | body | announcement.' ),
					),
					'required'             => array( 'text' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_rewrite_in_voice',
				'permission_callback' => $can_write,
				'meta'                => $mcp_public,
			)
		);

		wp_register_ability(
			'firstchurch/suggest-title',
			array(
				'label'               => 'Suggest titles',
				'description'         => 'Propose 3–5 on-voice title options for a piece of content.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'content' => array( 'type' => 'string' ),
						'kind'    => array( 'type' => 'string' ),
					),
					'required'             => array( 'content' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_suggest_title',
				'permission_callback' => $can_write,
				'meta'                => $mcp_public,
			)
		);

		wp_register_ability(
			'firstchurch/draft-excerpt',
			array(
				'label'               => 'Draft excerpt',
				'description'         => 'Write a short (~160-char) on-voice teaser/excerpt for content.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'content' => array( 'type' => 'string' ) ),
					'required'             => array( 'content' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_draft_excerpt',
				'permission_callback' => $can_write,
				'meta'                => $mcp_public,
			)
		);

		wp_register_ability(
			'firstchurch/suggest-image-queries',
			array(
				'label'               => 'Suggest image search terms',
				'description'         => 'Turn a draft title + description into 2–3 tasteful stock-photo search phrases (visual scenes/objects, not the literal title). Used by intake to suggest a hero image.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'       => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_suggest_image_queries',
				'permission_callback' => $can_write,
				'meta'                => $mcp_public,
			)
		);

		wp_register_ability(
			'firstchurch/intake-classify',
			array(
				'label'               => 'Classify intake item',
				'description'         => 'Decide whether a raw intake item is publicity (publish on the site) or internal (room-booking/meeting noise), and the best target (event/announcement/both/none).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'text' => array( 'type' => 'string' ) ),
					'required'             => array( 'text' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_intake_classify',
				'permission_callback' => $can_write,
				'meta'                => $mcp_public,
			)
		);

		wp_register_ability(
			'firstchurch/intake-extract',
			array(
				'label'               => 'Extract intake item to draft',
				'description'         => 'Extract a publicity intake item into voice-corrected create-event / create-announcement intents (intents[] + reviewer notes).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'text'            => array( 'type' => 'string' ),
						'received_date'   => array( 'type' => 'string', 'description' => 'YYYY-MM-DD; defaults to today.' ),
						'attachment_urls' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					),
					'required'             => array( 'text' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_intake_extract',
				'permission_callback' => $can_write,
				'meta'                => $mcp_public,
			)
		);

		/* The house voice as a readable MCP resource, so interactive agents and
		 * the editor share the exact same source of truth as the PHP calls. */
		wp_register_ability(
			'firstchurch/guide-church-voice',
			array(
				'label'               => 'Church voice guide',
				'description'         => 'The First Church Seattle house voice and writing-style rules (titles, CTAs, names, formatting) applied to every AI draft and rewrite. Exposed as an MCP resource.',
				'category'            => 'firstchurch',
				'execute_callback'    => static function () { return fc_church_voice(); },
				'permission_callback' => static function () { return current_user_can( 'read' ); },
				'meta'                => array( 'mcp' => array( 'public' => true, 'type' => 'resource', 'uri' => 'firstchurch://guide/church-voice', 'mimeType' => 'text/markdown' ) ),
			)
		);
	}
);
