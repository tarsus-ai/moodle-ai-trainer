<?php

namespace mod_tarsus\local\Output;

use mod_tarsus\local\Support\Platform;

/**
 * Formula support across ILIAS releases.
 *
 * ILIAS 9 and 10 have the MathJax service (ilMathJax): formulas between
 * [tex] and [/tex] are converted to whatever the administration configured
 * - client-side MathJax with its delimiters, or server-rendered images.
 * ILIAS 11 bundles MathJax with the UI framework, configured for [tex]
 * delimiters inside elements with the class c-legacy__content--latex,
 * which is what the Markdown renderer emits.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class MathSupport
{
    /**
     * Prepare rendered HTML for the installed MathJax set-up.
     */
    public static function prepare(string $html): string
    {
        // Moodle's MathJax filter typesets in the browser: nothing to do here.
        return $html;
    }

    /**
     * Make sure MathJax is loaded on the current page.
     */
    public static function includeOnPage(): void
    {
        // Formulas show as TeX source until MathJax is wired to Moodle's filter.
    }
}
