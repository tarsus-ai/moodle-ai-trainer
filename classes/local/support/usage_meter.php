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

namespace mod_tarsuscoach\local\support;

/**
 * Local usage ledger: credits and operations per container, object and
 * user. The shared brain's usage cannot be broken down by course, so this
 * ledger provides the attribution.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class usage_meter
{
    public const TABLE = 'tarsuscoach_usage';

    public function record(int $containerref, int $objid, int $userid, string $operation, int $credits = 0): void {
        platform::db()->insert_record(self::TABLE, (object) [
            'courseid' => $containerref,
            'tarsuscoachid' => $objid,
            'userid' => $userid,
            'operation' => substr($operation, 0, 50),
            'credits' => $credits,
            'timecreated' => time(),
        ]);
        if ($credits > 0) {
            // A charged call may have crossed one of the monthly warning
            // thresholds.
            budget::check_thresholds();
        }
    }

    /**
     * Requests and credits per day since a point in time, oldest first,
     * keyed by Y-m-d (days without usage are absent).
     *
     * @return array<string, array{calls: int, credits: int}>
     */
    public function daily(int $since, ?int $objid = null): array {
        [$where, $params] = self::where($since, $objid, 'credits > 0');
        $days = [];
        $rows = platform::db()->get_recordset_select(self::TABLE, $where, $params, 'timecreated', 'id, timecreated, credits');
        foreach ($rows as $row) {
            $day = date('Y-m-d', (int) $row->timecreated);
            if (!isset($days[$day])) {
                $days[$day] = ['calls' => 0, 'credits' => 0];
            }
            $days[$day]['calls']++;
            $days[$day]['credits'] += (int) $row->credits;
        }
        $rows->close();
        return $days;
    }

    /**
     * Distinct users with charged calls since a point in time.
     */
    public function active_users(int $since, ?int $objid = null): int {
        [$where, $params] = self::where($since, $objid, 'credits > 0 AND userid > 0');
        return (int) platform::db()->count_records_sql('SELECT COUNT(DISTINCT userid) FROM {' . self::TABLE . '} WHERE ' . $where, $params);
    }

    /**
     * Requests and credits per user since a point in time, busiest first.
     *
     * @return array<int, array{usrid: int, calls: int, credits: int, last: int}>
     */
    public function per_user(int $since, ?int $objid = null, int $limit = 10): array {
        [$where, $params] = self::where($since, $objid, 'credits > 0 AND userid > 0');
        $records = platform::db()->get_records_sql(
            'SELECT userid, COUNT(1) AS calls, SUM(credits) AS creditsum, MAX(timecreated) AS lastused FROM {' . self::TABLE . '}'
            . ' WHERE ' . $where . ' GROUP BY userid ORDER BY calls DESC, creditsum DESC',
            $params,
            0,
            $limit
        );
        $rows = [];
        foreach ($records as $row) {
            $rows[] = ['usrid' => (int) $row->userid, 'calls' => (int) $row->calls, 'credits' => (int) $row->creditsum, 'last' => (int) $row->lastused];
        }
        return $rows;
    }

    /**
     * Credits and calls per operation since a point in time.
     *
     * @return array<string, array{calls: int, credits: int}>
     */
    public function summary(int $since, ?int $objid = null): array {
        [$where, $params] = self::where($since, $objid, '');
        $records = platform::db()->get_records_sql(
            'SELECT operation, COUNT(1) AS calls, SUM(credits) AS creditsum FROM {' . self::TABLE . '} WHERE ' . $where . ' GROUP BY operation ORDER BY operation',
            $params
        );
        $summary = [];
        foreach ($records as $row) {
            $summary[(string) $row->operation] = ['calls' => (int) $row->calls, 'credits' => (int) $row->creditsum];
        }
        return $summary;
    }

    /**
     * Remove a user's ledger rows (the conversation was erased).
     */
    public function forget_user(int $objid, int $userid): void {
        platform::db()->delete_records(self::TABLE, ['tarsuscoachid' => $objid, 'userid' => $userid]);
    }

    /**
     * The WHERE clause and its parameters: since a point in time, for one
     * activity or all, plus a fixed condition.
     *
     * @return array{0: string, 1: array}
     */
    private static function where(int $since, ?int $objid, string $extra): array {
        $where = 'timecreated >= ?';
        $params = [$since];
        if ($extra !== '') {
            $where .= ' AND ' . $extra;
        }
        if ($objid !== null) {
            $where .= ' AND tarsuscoachid = ?';
            $params[] = $objid;
        }
        return [$where, $params];
    }
}
