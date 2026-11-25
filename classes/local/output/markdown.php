<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_tarsuscoach\local\output;

/**
 * The brain's markdown for the browser.
 *
 * Tarsus writes its texts for React-markdown with KaTeX formulas. The
 * coach shows them exactly that way: the browser renders them with the
 * same pipeline (assets/js/vendor/tarsus-markdown.min.js, built from
 * build/markdown). The server only hands the text over, escaped, in an
 * element the coach script recognises; until it is rendered (or if the
 * script could not load) the text reads as plain text.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class markdown {
    /**
     * A markdown text for the coach script to render ('' for an empty one).
     *
     * @param string $markdown The text as Tarsus wrote it.
     * @return string Safe HTML.
     */
    public static function render(string $markdown): string {
        if (trim($markdown) === '') {
            return '';
        }
        return '<div class="tarsus-md">' . self::plain($markdown) . '</div>';
    }

    /**
     * Plain text as safe HTML (line breaks kept), for text that is not markdown.
     *
     * @param string $text The text.
     * @return string Safe HTML.
     */
    public static function plain(string $text): string {
        return nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
    }
}
