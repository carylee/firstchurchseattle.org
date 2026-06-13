<?php
/**
 * Plugin Name: First Church MCP Abilities
 * Description: Read + draft-first write WordPress Abilities for events (fce_event), announcements, people (ctc_person), carousel cards, e-news, redirects, and navigation menus — exposed to AI via the MCP Adapter. Supports the draft-first workflow, recurrence, featured images, stock photo search/import, intake triage, deployment auditing (restore from trash + who/what/when audit log), content-health audits, plus MCP resources (content guide, taxonomy vocabulary) and prompts (editorial workflows).
 * Version:     0.9.0
 * Author:      First Church Seattle
 */

defined( 'ABSPATH' ) || exit;

/*
 * Split into focused modules under firstchurch-mcp-abilities/ (2026-06). This
 * file is the only one WordPress auto-loads from mu-plugins/; it pulls in the
 * rest. Each module registers its own slice of the ability surface on
 * wp_abilities_api_init. See firstchurch-mcp/ for the PHPUnit harness.
 */

require_once __DIR__ . '/firstchurch-mcp-abilities/bootstrap.php';
require_once __DIR__ . '/firstchurch-mcp-abilities/helpers.php';
require_once __DIR__ . '/firstchurch-mcp-abilities/safety.php';
require_once __DIR__ . '/firstchurch-mcp-abilities/health.php';
require_once __DIR__ . '/firstchurch-mcp-abilities/shared-writes.php';
require_once __DIR__ . '/firstchurch-mcp-abilities/events.php';
require_once __DIR__ . '/firstchurch-mcp-abilities/announcements.php';
require_once __DIR__ . '/firstchurch-mcp-abilities/media.php';
require_once __DIR__ . '/firstchurch-mcp-abilities/posts.php';
require_once __DIR__ . '/firstchurch-mcp-abilities/pages.php';
require_once __DIR__ . '/firstchurch-mcp-abilities/redirects.php';
require_once __DIR__ . '/firstchurch-mcp-abilities/menus.php';
require_once __DIR__ . '/firstchurch-mcp-abilities/resources-prompts.php';
require_once __DIR__ . '/firstchurch-mcp-abilities/voice.php';
require_once __DIR__ . '/firstchurch-mcp-abilities/editor.php';
require_once __DIR__ . '/firstchurch-mcp-abilities/intake.php';
