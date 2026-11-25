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
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class bit_type
{
    public const TEXT = 'text';
    public const URL = 'url';
    public const DOCUMENT = 'document';
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

    public static function is_valid(string $type): bool {
        return in_array($type, self::ALL, true);
    }
}
