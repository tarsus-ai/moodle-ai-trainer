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

namespace mod_tarsuscoach\local\activity;

use mod_tarsuscoach\local\index\inclusions;
use mod_tarsuscoach\local\index\repository;
use mod_tarsuscoach\local\index\status;
use mod_tarsuscoach\local\support\config;

/**
 * The set of resources allowed to ground a Tarsus object's conversation:
 * the teacher's inclusions, filtered down to what the brain actually holds
 * right now, in this object's course.
 *
 * Filtering at read time means a resource removed or replaced after being
 * included silently drops out of scope - no cleanup ordering to get wrong.
 * An empty scope must never reach the brain: it reads an empty list as
 * "search everything", which would cross course boundaries.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class scope
{
    /**
     * The brain-side conversation context id of a Tarsus object. Site
     * scoped, so two installations sharing a brain never share a
     * conversation.
     */
    public static function conversation_context_id(int $objid): string {
        return config::site_token() . ':tarsus:' . $objid;
    }

    /**
     * @return string[] Resource ids allowed to ground the object's answers.
     */
    public static function allowed_resource_ids(int $objid, int $containerref): array {
        $included = inclusions::for_object($objid);
        if ($included === []) {
            return [];
        }
        $allowed = [];
        foreach ((new repository())->get_many($included) as $resourceid => $row) {
            if ($row->status === status::INDEXED && $row->containerref === $containerref) {
                $allowed[] = $resourceid;
            }
        }
        return $allowed;
    }
}
