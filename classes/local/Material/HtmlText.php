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

namespace mod_tarsus\local\Material;

/**
 * Turns the HTML of Moodle content (pages, book chapters, wiki pages,
 * glossary definitions) into plain text with a light Markdown structure the
 * brain reads well: headings, list items, table rows, paragraphs. The same
 * structure Tarsus for ILIAS sends for ILIAS pages.
 *
 * @package    mod_tarsus
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class HtmlText {
    /** Elements that end a line. */
    private const BLOCKS = [
        'p', 'div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav', 'blockquote', 'pre',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'figure', 'figcaption',
        'caption', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'details', 'summary', 'address',
    ];

    /** Elements whose content is not text the learner reads. */
    private const SKIP = ['script', 'style', 'noscript', 'template', 'head', 'svg', 'iframe', 'object', 'embed',
        'video', 'audio', 'canvas', 'button', 'select', 'textarea', 'input', 'map'];

    /** Markdown prefix of the headings (the material's own title is "#"). */
    private const HEADINGS = ['h1' => '## ', 'h2' => '### ', 'h3' => '#### ', 'h4' => '##### ', 'h5' => '##### ', 'h6' => '##### '];

    /**
     * Text of an HTML fragment.
     *
     * @param string $html The HTML.
     * @return string
     */
    public static function fromHtml(string $html): string {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $ok = $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok || $dom->documentElement === null) {
            return self::tidy(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $out = '';
        self::walk($dom->documentElement, $out);
        return self::tidy($out);
    }

    /**
     * Append the text of a node and its children.
     *
     * @param \DOMNode $node The node.
     * @param string $out The text so far.
     */
    private static function walk(\DOMNode $node, string &$out): void {
        if ($node instanceof \DOMText) {
            $out .= $node->nodeValue;
            return;
        }
        if (!$node instanceof \DOMElement) {
            return;
        }
        $name = strtolower($node->nodeName);
        if (in_array($name, self::SKIP, true)) {
            return;
        }
        if ($name === 'br') {
            $out .= "\n";
            return;
        }
        $prefix = '';
        if (isset(self::HEADINGS[$name])) {
            $prefix = self::HEADINGS[$name];
        } else if ($name === 'li') {
            $prefix = '- ';
        } else if ($name === 'td' || $name === 'th') {
            $prefix = ' | ';
        } else if ($name === 'caption' || $name === 'summary') {
            $prefix = '### ';
        } else if ($name === 'img') {
            // A picture counts with its description, if it has one.
            $alt = trim((string) $node->getAttribute('alt'));
            if ($alt !== '') {
                $out .= ' (' . $alt . ') ';
            }
            return;
        }
        $block = in_array($name, self::BLOCKS, true);
        if ($block) {
            $out .= "\n";
        }
        $out .= $prefix;
        foreach ($node->childNodes as $child) {
            self::walk($child, $out);
        }
        if ($block) {
            $out .= "\n";
        }
    }

    /**
     * Collapse runs of spaces within lines and of empty lines.
     *
     * @param string $text The raw text.
     * @return string
     */
    private static function tidy(string $text): string {
        $text = str_replace(["\r\n", "\r", "\u{00a0}", "\t"], ["\n", "\n", ' ', ' '], $text);
        $lines = [];
        $empty = 0;
        foreach (explode("\n", $text) as $line) {
            $line = trim(implode(' ', array_filter(explode(' ', $line), static fn(string $word): bool => $word !== '')));
            if ($line === '') {
                $empty++;
                if ($empty > 1) {
                    continue;
                }
            } else {
                $empty = 0;
            }
            $lines[] = $line;
        }
        return trim(implode("\n", $lines));
    }
}
