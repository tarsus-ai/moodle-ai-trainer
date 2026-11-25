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

namespace mod_tarsuscoach\local\material;

/**
 * The four bit types the brain ingests: it tracks text, links, documents
 * and audio/video differently.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bit_type
{
    /** Text (pages, books, wikis, glossaries). */
    public const TEXT = 'text';
    /** A web address. */
    public const URL = 'url';
    /** A file that is not audio or video. */
    public const DOCUMENT = 'document';
    /** An audio or video file. */
    public const MEDIA = 'media';

    /** @var string[] */
    public const ALL = [self::TEXT, self::URL, self::DOCUMENT, self::MEDIA];

    /**
     * Classify a file by its MIME type.
     */
    public static function for_mime(string $mimetype): string {
        $mimetype = strtolower($mimetype);
        if (strncmp($mimetype, 'audio/', 6) === 0 || strncmp($mimetype, 'video/', 6) === 0) {
            return self::MEDIA;
        }
        return self::DOCUMENT;
    }

    /**
     * Whether a string is one of the bit types.
     *
     * @param string $type The value to check.
     * @return bool
     */
    public static function is_valid(string $type): bool {
        return in_array($type, self::ALL, true);
    }
}
