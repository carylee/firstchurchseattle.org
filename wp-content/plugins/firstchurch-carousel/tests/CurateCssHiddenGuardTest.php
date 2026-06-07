<?php

declare(strict_types=1);

namespace FirstChurch\Carousel\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the Preview-modal-on-load bug.
 *
 * The curate overlays are shown/hidden via the HTML `hidden` attribute, but they
 * also need `display:flex` to lay out when open. An author `display` rule beats
 * the UA `[hidden]{display:none}` (author origin wins over UA), so any such
 * overlay MUST re-assert `[hidden]{display:none}` or it renders on page load and
 * covers the screen. There is no JS/DOM test runtime here, so we assert the rule
 * statically against curate.css: this is exactly the pairing that was missing.
 */
final class CurateCssHiddenGuardTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(__DIR__ . '/../assets/curate.css');
    }

    /**
     * @return string[] selectors that force `display` (the bug precondition)
     */
    private function selectorsForcingDisplay(string $css): array
    {
        // Base rules (no attribute/pseudo qualifier) that set a `display`.
        preg_match_all('/(\.[a-z0-9_-]+)\s*\{[^}]*\bdisplay\s*:[^};]+;/i', $css, $m);
        return array_values(array_unique($m[1]));
    }

    public function test_hidden_attribute_overlays_restore_display_none(): void
    {
        $css = $this->css();

        // The two overlays that triggered the bug must be guarded.
        foreach (['.fccar-preview-modal', '.fccar-preview-empty'] as $sel) {
            $this->assertMatchesRegularExpression(
                '/' . preg_quote($sel, '/') . '\s*\{[^}]*\bdisplay\s*:/i',
                $css,
                "$sel is expected to force display (the precondition for the [hidden] bug)"
            );
            $this->assertMatchesRegularExpression(
                '/' . preg_quote($sel, '/') . '\[hidden\]\s*\{[^}]*\bdisplay\s*:\s*none/i',
                $css,
                "$sel forces display but never restores [hidden]{display:none} — it will show on page load"
            );
        }
    }

    /**
     * Belt-and-suspenders: every base selector in curate.css that forces a
     * `display` and is ALSO used with the `hidden` attribute in the markup must
     * carry a `[hidden]{display:none}` guard. Catches a future overlay that
     * forgets the pairing.
     */
    public function test_no_display_forcing_selector_used_with_hidden_lacks_a_guard(): void
    {
        $css   = $this->css();
        $admin = (string) file_get_contents(__DIR__ . '/../inc/admin-curate.php');

        // Exact class tokens on elements carrying a standalone boolean `hidden`
        // (parse the class list — `\b` word boundaries mis-match hyphenated names
        // like fccar-drawer inside fccar-drawer-backdrop, and `aria-hidden` is not
        // the boolean attribute).
        $hiddenClasses = [];
        preg_match_all('/<[a-z][^>]*>/i', $admin, $tags);
        foreach ($tags[0] as $tag) {
            if (! preg_match('/\shidden(?=[\s>=])/', $tag)) {
                continue;
            }
            if (preg_match('/\sclass="([^"]*)"/', $tag, $cm)) {
                foreach (preg_split('/\s+/', trim($cm[1])) as $cls) {
                    if ('' !== $cls) {
                        $hiddenClasses[$cls] = true;
                    }
                }
            }
        }

        foreach ($this->selectorsForcingDisplay($css) as $sel) {
            if (! isset($hiddenClasses[ltrim($sel, '.')])) {
                continue;
            }
            $this->assertMatchesRegularExpression(
                '/' . preg_quote($sel, '/') . '\[hidden\]\s*\{[^}]*\bdisplay\s*:\s*none/i',
                $css,
                "$sel is rendered with [hidden] and forces display, but has no [hidden]{display:none} guard"
            );
        }
    }
}
