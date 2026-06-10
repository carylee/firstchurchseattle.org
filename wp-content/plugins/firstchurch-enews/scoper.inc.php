<?php
/**
 * PHP-Scoper config: build a shippable copy of this plugin whose third-party
 * dependencies (the Mailchimp SDK + its Guzzle/PSR tree) are moved into a
 * private namespace — `FirstChurch\ENews\Vendor\…` — so two plugins can each
 * carry their own Guzzle without the "Cannot redeclare class" collision that
 * bit firstchurch-events' php-rrule. Our own code (the `FirstChurch\ENews`
 * namespace and the global `fcen_*` functions) is left untouched, and so are
 * WordPress's global functions (php-scoper only prefixes namespaced symbols).
 *
 * Driven by build.sh in CD; the prefixed result lands in dist/ and is what ships.
 *
 * @see ops/docs/composer-on-prod.md
 */

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return [
    'prefix' => 'FirstChurch\\ENews\\Vendor',

    'finders' => [
        // The production dependency tree — ALL files, not just *.php, so the
        // Composer autoloader metadata (installed.json, autoload_*.php) is copied
        // and patched; restricting to *.php drops installed.json and the scoped
        // autoloader can't find its packages.
        Finder::create()->files()->in('vendor'),
        // Our own PHP — so php-scoper rewrites the `use MailchimpMarketing\…`
        // references in inc/ to the prefixed namespace too (source stays clean).
        Finder::create()->files()->name('*.php')->in(['src', 'inc']),
        Finder::create()->files()->in('.')->depth(0)->name(['firstchurch-enews.php', 'composer.json']),
    ],

    // Keep our first-party namespace as-is; only the deps get prefixed.
    'exclude-namespaces' => ['FirstChurch\\ENews'],

    // Don't prefix WordPress's global constants/functions the plugin calls.
    'exclude-constants' => ['ABSPATH', 'MINUTE_IN_SECONDS'],
];
