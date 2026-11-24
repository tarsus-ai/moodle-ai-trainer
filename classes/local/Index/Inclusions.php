<?php

namespace mod_tarsus\local\Index;

use mod_tarsus\local\Support\Platform;

/**
 * Which indexed resources a teacher included in which Tarsus object.
 *
 * Different Tarsus objects in one course can cover different subsets of
 * the course's indexed material.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class Inclusions
{
    public const TABLE = 'tarsus_inc';

    /**
     * @return string[] The resource ids included in an object.
     */
    public static function forObject(int $objid): array
    {
        $ids = Platform::db()->get_fieldset_select(self::TABLE, 'resource_id', 'obj_id = ? ORDER BY created, id', [$objid]);
        return array_map('strval', $ids);
    }

    public static function include(int $objid, string $resourceid): void
    {
        $db = Platform::db();
        $id = $db->get_field(self::TABLE, 'id', ['obj_id' => $objid, 'resource_id' => $resourceid]);
        if ($id) {
            $db->set_field(self::TABLE, 'created', time(), ['id' => $id]);
        } else {
            $db->insert_record(self::TABLE, (object) ['obj_id' => $objid, 'resource_id' => $resourceid, 'created' => time()]);
        }
    }

    public static function exclude(int $objid, string $resourceid): void
    {
        Platform::db()->delete_records(self::TABLE, ['obj_id' => $objid, 'resource_id' => $resourceid]);
    }

    /**
     * A resource that left the brain leaves every object's scope with it:
     * keeping the choice would silently resurrect it on a later re-index.
     */
    public static function forgetResource(string $resourceid): void
    {
        Platform::db()->delete_records(self::TABLE, ['resource_id' => $resourceid]);
    }

    public static function forgetObject(int $objid): void
    {
        Platform::db()->delete_records(self::TABLE, ['obj_id' => $objid]);
    }

    public static function count(int $objid): int
    {
        return Platform::db()->count_records(self::TABLE, ['obj_id' => $objid]);
    }
}
