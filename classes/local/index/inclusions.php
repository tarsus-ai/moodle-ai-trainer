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

namespace mod_tarsuscoach\local\index;

use mod_tarsuscoach\local\support\platform;

/**
 * Which indexed resources a teacher included in which Tarsus object.
 *
 * Different Tarsus objects in one course can cover different subsets of
 * the course's indexed material.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class inclusions
{
    /** The table holding the inclusions. */
    public const TABLE = 'tarsuscoach_inclusion';

    /**
     * The resources included in a Tarsus object, in order of inclusion.
     *
     * @param int $objid The Tarsus object.
     * @return string[] The resource ids included in an object.
     */
    public static function for_object(int $objid): array {
        $ids = platform::db()->get_fieldset_select(
            self::TABLE,
            'resourceid',
            'tarsuscoachid = ? ORDER BY timecreated, id',
            [$objid]
        );
        return array_map('strval', $ids);
    }

    /**
     * Include a resource in a Tarsus object, or renew the time of an
     * inclusion that already exists.
     *
     * @param int $objid The Tarsus object.
     * @param string $resourceid The resource id.
     */
    public static function include(int $objid, string $resourceid): void {
        $db = platform::db();
        $id = $db->get_field(self::TABLE, 'id', ['tarsuscoachid' => $objid, 'resourceid' => $resourceid]);
        if ($id) {
            $db->set_field(self::TABLE, 'timecreated', time(), ['id' => $id]);
        } else {
            $db->insert_record(self::TABLE, (object) [
                'tarsuscoachid' => $objid,
                'resourceid' => $resourceid,
                'timecreated' => time(),
            ]);
        }
    }

    /**
     * Take a resource out of a Tarsus object.
     *
     * @param int $objid The Tarsus object.
     * @param string $resourceid The resource id.
     */
    public static function exclude(int $objid, string $resourceid): void {
        platform::db()->delete_records(self::TABLE, ['tarsuscoachid' => $objid, 'resourceid' => $resourceid]);
    }

    /**
     * A resource that left the brain leaves every object's scope with it:
     * keeping the choice would silently resurrect it on a later re-index.
     */
    public static function forget_resource(string $resourceid): void {
        platform::db()->delete_records(self::TABLE, ['resourceid' => $resourceid]);
    }

    /**
     * Forget every inclusion of a Tarsus object.
     *
     * @param int $objid The Tarsus object.
     */
    public static function forget_object(int $objid): void {
        platform::db()->delete_records(self::TABLE, ['tarsuscoachid' => $objid]);
    }

    /**
     * How many resources a Tarsus object includes.
     *
     * @param int $objid The Tarsus object.
     * @return int
     */
    public static function count(int $objid): int {
        return platform::db()->count_records(self::TABLE, ['tarsuscoachid' => $objid]);
    }
}
