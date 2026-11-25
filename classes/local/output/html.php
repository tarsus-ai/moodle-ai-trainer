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
 * HTML helpers for the templates' contexts.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class html {
    /**
     * A JSON value safe to embed in an HTML attribute or a script block.
     *
     * @param mixed $value The value.
     * @return string
     */
    public static function json($value): string {
        return (string) json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
    }

    /**
     * An inline SVG icon from the plugin's icon set (the coach script draws
     * the same icons).
     *
     * @param string $name The icon's name in icons::PATHS.
     * @param string $class The icon's class.
     * @return string The SVG, or '' for an unknown name.
     */
    public static function icon(string $name, string $class = 'tarsus-icon'): string {
        $path = icons::PATHS[$name] ?? '';
        if ($path === '') {
            return '';
        }
        return \html_writer::tag('svg', $path, [
            'class' => $class,
            'viewBox' => '0 0 24 24',
            'width' => '16',
            'height' => '16',
            'aria-hidden' => 'true',
            'focusable' => 'false',
        ]);
    }
}
