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

use mod_tarsuscoach\local\support\config;

/**
 * Builds and parses the namespaced resource id that addresses a bit:
 *
 *     {sitetoken}:{containerref}:{type}:{refid}:{bitkey}
 *
 * - sitetoken keeps two ILIAS installations that share a brain apart;
 * - containerref scopes content to one course (or group);
 * - type + refid locate the source object so the payload can be
 *   re-extracted at send time;
 * - bitkey distinguishes several bits of one object (each link of a web
 *   link list); single-bit objects use 'main'.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class resource_id
{
    /** The bit key of an object with a single bit. */
    public const MAIN = 'main';

    /** The separator between the parts of an id. */
    private const SEP = ':';

    /**
     * The resource id of a bit.
     *
     * @param int $containerref The course.
     * @param string $type The module (resource, page...).
     * @param int $refid The course module.
     * @param string $bitkey The bit within the module.
     * @return string
     */
    public static function build(int $containerref, string $type, int $refid, string $bitkey = self::MAIN): string {
        return implode(self::SEP, [
            config::site_token(),
            $containerref,
            $type,
            $refid,
            self::clean_bitkey($bitkey),
        ]);
    }

    /**
     * The parts of a resource id, or null when it is malformed.
     *
     * @param string $resourceid The resource id.
     * @return array{sitetoken: string, containerref: int, type: string, refid: int, bitkey: string}|null
     */
    public static function parse(string $resourceid): ?array {
        $parts = explode(self::SEP, $resourceid);
        if (count($parts) !== 5) {
            return null;
        }

        [$sitetoken, $containerref, $type, $refid, $bitkey] = $parts;
        if (
            $sitetoken === '' || $type === '' || $bitkey === ''
                || !ctype_digit($containerref) || !ctype_digit($refid)
                || !preg_match('/^[a-z]{2,8}$/', $type)
        ) {
            return null;
        }

        return [
            'sitetoken' => $sitetoken,
            'containerref' => (int) $containerref,
            'type' => $type,
            'refid' => (int) $refid,
            'bitkey' => $bitkey,
        ];
    }

    /**
     * The brain-side asset id of a container: every bit is filed under its
     * course (or group), so removing a course costs one call.
     */
    public static function asset_id(int $containerref): string {
        return config::site_token() . self::SEP . $containerref;
    }

    /**
     * Whether an id belongs to this installation.
     */
    public static function is_local(string $resourceid): bool {
        $parts = self::parse($resourceid);
        return $parts !== null && $parts['sitetoken'] === config::site_token();
    }

    /**
     * A bit key with every character but letters, digits, "_", "." and "-"
     * removed; main when nothing is left.
     *
     * @param string $bitkey The bit key.
     * @return string
     */
    private static function clean_bitkey(string $bitkey): string {
        $clean = preg_replace('/[^a-z0-9_.-]/i', '', $bitkey);
        return ($clean === '' || $clean === null) ? self::MAIN : $clean;
    }
}
