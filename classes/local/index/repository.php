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
 * Typed access to the local index map: the source of truth for what the
 * brain holds of this installation's material.
 *
 * status reads are database reads - zero API calls on render. Every
 * transition is validated against status, so a row can only move through
 * legal states.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository
{
    /** The table holding the index map. */
    public const TABLE = 'tarsuscoach_material';

    /**
     * The index row of a resource.
     *
     * @param string $resourceid The resource id.
     * @return \stdClass|null The row, or null when the resource is not tracked.
     */
    public function get(string $resourceid): ?\stdClass {
        $row = platform::db()->get_record(self::TABLE, ['resourceid' => $resourceid]);
        return $row ? self::map((array) $row) : null;
    }

    /**
     * Ensure a row exists, creating it as notindexed (idempotent).
     */
    public function ensure(string $resourceid, int $containerref, string $assetid, string $srctype, int $srcref): \stdClass {
        $existing = $this->get($resourceid);
        if ($existing !== null) {
            return $existing;
        }

        $now = time();
        platform::db()->insert_record(self::TABLE, (object) [
            'resourceid' => $resourceid,
            'assetid' => $assetid,
            'courseid' => $containerref,
            'sourcetype' => $srctype,
            'sourceid' => $srcref,
            'contenthash' => null,
            'bitref' => null,
            'status' => status::NOT_INDEXED,
            'attempts' => 0,
            'nextretry' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return $this->require_row($resourceid);
    }

    /**
     * Move a row to a new status, validating the transition.
     */
    public function set_status(string $resourceid, string $newstatus): void {
        $row = $this->require_row($resourceid);

        if (!status::can_transition($row->status, $newstatus)) {
            throw new \LogicException("Illegal index status transition {$row->status} -> {$newstatus} for {$resourceid}");
        }

        $this->write($resourceid, ['status' => ['text', $newstatus]]);
    }

    /**
     * Mark a resource as live in the brain, storing its bit ref and hash.
     */
    public function mark_indexed(string $resourceid, string $bitref, string $contenthash): void {
        $row = $this->require_row($resourceid);
        if (!status::can_transition($row->status, status::INDEXED)) {
            throw new \LogicException("Illegal index status transition {$row->status} -> indexed for {$resourceid}");
        }
        $this->write($resourceid, [
            'status' => ['text', status::INDEXED],
            'bitref' => ['text', $bitref],
            'contenthash' => ['text', $contenthash],
            'attempts' => ['integer', 0],
            'nextretry' => ['integer', 0],
        ]);
    }

    /**
     * Record a successful upload: await confirmation, remembering the hash
     * of what was actually sent (a later confirmation must not recompute it
     * from current ILIAS state - the source may have changed meanwhile).
     */
    public function mark_uploaded(string $resourceid, string $contenthash): void {
        $row = $this->require_row($resourceid);
        if (!status::can_transition($row->status, status::PENDING_CONFIRMATION)) {
            throw new \LogicException("Illegal index status transition {$row->status} -> pendingconfirmation for {$resourceid}");
        }
        $this->write($resourceid, [
            'status' => ['text', status::PENDING_CONFIRMATION],
            'contenthash' => ['text', $contenthash],
            'attempts' => ['integer', 0],
            'nextretry' => ['integer', 0],
        ]);
    }

    /**
     * Record a failed attempt: schedule the next retry, or fail to error.
     */
    public function record_failure(string $resourceid): \stdClass {
        $row = $this->require_row($resourceid);
        $attempts = $row->attempts + 1;
        $now = time();
        $values = ['attempts' => ['integer', $attempts]];

        if (backoff::exhausted($attempts)) {
            if (status::can_transition($row->status, status::ERROR)) {
                $values['status'] = ['text', status::ERROR];
            }
            $values['nextretry'] = ['integer', 0];
        } else {
            $values['nextretry'] = ['integer', $now + backoff::delay($attempts, true)];
        }

        $this->write($resourceid, $values);
        return $this->require_row($resourceid);
    }

    /**
     * Clear a row's failed attempts and its scheduled retry.
     *
     * @param string $resourceid The resource id.
     */
    public function reset_attempts(string $resourceid): void {
        $this->require_row($resourceid);
        $this->write($resourceid, ['attempts' => ['integer', 0], 'nextretry' => ['integer', 0]]);
    }

    /**
     * Keep a row's location in step with its material (the container is
     * part of the id, the source ref is not when an object is moved).
     */
    public function touch_location(string $resourceid, int $srcref): void {
        platform::db()->set_field(self::TABLE, 'sourceid', $srcref, ['resourceid' => $resourceid]);
    }

    /**
     * Every row in one status, oldest first.
     *
     * @param string $status The status.
     * @return \stdClass[]
     */
    public function get_by_status(string $status): array {
        return $this->select('status = ?', [$status]);
    }

    /**
     * The rows of one course.
     *
     * @param int $containerref The course.
     * @return \stdClass[] Every row of one container, keyed by resource id.
     */
    public function get_by_container(int $containerref): array {
        $rows = [];
        foreach ($this->select('courseid = ?', [$containerref]) as $row) {
            $rows[$row->resourceid] = $row;
        }
        return $rows;
    }

    /**
     * The rows of several resources; untracked ones are left out.
     *
     * @param string[] $resourceids The resource ids.
     * @return array<string, \stdClass> Keyed by resource id.
     */
    public function get_many(array $resourceids): array {
        $resourceids = array_values(array_unique(array_filter(array_map('strval', $resourceids), 'strlen')));
        if ($resourceids === []) {
            return [];
        }
        $rows = [];
        foreach (array_chunk($resourceids, 200) as $chunk) {
            [$insql, $params] = platform::db()->get_in_or_equal($chunk);
            foreach ($this->select('resourceid ' . $insql, $params) as $row) {
                $rows[$row->resourceid] = $row;
            }
        }
        return $rows;
    }

    /**
     * The courses the index map holds rows for.
     *
     * @return int[] The distinct containers with index rows.
     */
    public function containers(): array {
        return array_map('intval', platform::db()->get_fieldset_sql('SELECT DISTINCT courseid FROM {' . self::TABLE . '}'));
    }

    /**
     * How many rows each status has.
     *
     * @return array<string, int> status => count, over all rows.
     */
    public function count_by_status(): array {
        $counts = [];
        $menu = platform::db()->get_records_sql_menu('SELECT status, COUNT(1) FROM {' . self::TABLE . '} GROUP BY status');
        foreach ($menu as $status => $count) {
            $counts[(string) $status] = (int) $count;
        }
        return $counts;
    }

    /**
     * Delete the row of a resource.
     *
     * @param string $resourceid The resource id.
     */
    public function delete(string $resourceid): void {
        platform::db()->delete_records(self::TABLE, ['resourceid' => $resourceid]);
    }

    /**
     * The rows matching an SQL condition, oldest first.
     *
     * @param string $where The condition, with ? placeholders.
     * @param array $params The values for the placeholders.
     * @return \stdClass[]
     */
    private function select(string $where, array $params): array {
        $rows = [];
        foreach (platform::db()->get_records_select(self::TABLE, $where, $params, 'id') as $row) {
            $rows[] = self::map((array) $row);
        }
        return $rows;
    }

    /**
     * Update fields of a resource's row and its modification time; a missing
     * row is left alone.
     *
     * @param string $resourceid The resource id.
     * @param array<string, array{0: string, 1: mixed}> $values New values by field name, as [type, value] pairs.
     */
    private function write(string $resourceid, array $values): void {
        $id = platform::db()->get_field(self::TABLE, 'id', ['resourceid' => $resourceid]);
        if (!$id) {
            return;
        }
        // Values come as [type, value] pairs, as the shared code writes them.
        $record = (object) ['id' => (int) $id, 'timemodified' => time()];
        foreach ($values as $name => $value) {
            $record->$name = is_array($value) ? $value[1] : $value;
        }
        platform::db()->update_record(self::TABLE, $record);
    }

    /**
     * The index row of a resource, which must exist.
     *
     * @param string $resourceid The resource id.
     * @return \stdClass
     * @throws \LogicException When the resource has no row.
     */
    private function require_row(string $resourceid): \stdClass {
        $row = $this->get($resourceid);
        if ($row === null) {
            throw new \LogicException("No index row for resource {$resourceid}");
        }
        return $row;
    }

    /**
     * Turn a database record into a row with typed values and the field
     * names the index code uses (containerref, srctype, srcref).
     *
     * @param array<string, mixed> $row The database record.
     * @return \stdClass
     */
    private static function map(array $row): \stdClass {
        return (object) [
            'id' => (int) $row['id'],
            'resourceid' => (string) $row['resourceid'],
            'assetid' => (string) $row['assetid'],
            'containerref' => (int) $row['courseid'],
            'srctype' => (string) $row['sourcetype'],
            'srcref' => (int) $row['sourceid'],
            'contenthash' => (string) ($row['contenthash'] ?? ''),
            'bitref' => (string) ($row['bitref'] ?? ''),
            'status' => (string) $row['status'],
            'attempts' => (int) $row['attempts'],
            'nextretry' => (int) $row['nextretry'],
            'timecreated' => (int) $row['timecreated'],
            'timemodified' => (int) $row['timemodified'],
        ];
    }
}
