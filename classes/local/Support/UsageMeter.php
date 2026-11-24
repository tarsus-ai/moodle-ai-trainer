<?php

namespace mod_tarsus\local\Support;

/**
 * Local usage ledger: credits and operations per container, object and
 * user. The shared brain's usage cannot be broken down by course, so this
 * ledger provides the attribution.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class UsageMeter
{
    public const TABLE = 'tarsus_use';

    public function record(int $containerref, int $objid, int $userid, string $operation, int $credits = 0): void
    {
        Platform::db()->insert_record(self::TABLE, (object) [
            'container_ref' => $containerref,
            'obj_id' => $objid,
            'usr_id' => $userid,
            'op_name' => substr($operation, 0, 50),
            'credits' => $credits,
            'created' => time(),
        ]);
        if ($credits > 0) {
            // A charged call may have crossed one of the monthly warning
            // thresholds.
            Budget::checkThresholds();
        }
    }

    /**
     * Requests and credits per day since a point in time, oldest first,
     * keyed by Y-m-d (days without usage are absent).
     *
     * @return array<string, array{calls: int, credits: int}>
     */
    public function daily(int $since, ?int $objid = null): array
    {
        [$where, $params] = self::where($since, $objid, 'credits > 0');
        $days = [];
        $rows = Platform::db()->get_recordset_select(self::TABLE, $where, $params, 'created', 'id, created, credits');
        foreach ($rows as $row) {
            $day = date('Y-m-d', (int) $row->created);
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
    public function activeUsers(int $since, ?int $objid = null): int
    {
        [$where, $params] = self::where($since, $objid, 'credits > 0 AND usr_id > 0');
        return (int) Platform::db()->count_records_sql('SELECT COUNT(DISTINCT usr_id) FROM {' . self::TABLE . '} WHERE ' . $where, $params);
    }

    /**
     * Requests and credits per user since a point in time, busiest first.
     *
     * @return array<int, array{usrid: int, calls: int, credits: int, last: int}>
     */
    public function perUser(int $since, ?int $objid = null, int $limit = 10): array
    {
        [$where, $params] = self::where($since, $objid, 'credits > 0 AND usr_id > 0');
        $records = Platform::db()->get_records_sql(
            'SELECT usr_id, COUNT(1) AS calls, SUM(credits) AS creditsum, MAX(created) AS lastused FROM {' . self::TABLE . '}'
            . ' WHERE ' . $where . ' GROUP BY usr_id ORDER BY calls DESC, creditsum DESC',
            $params,
            0,
            $limit
        );
        $rows = [];
        foreach ($records as $row) {
            $rows[] = ['usrid' => (int) $row->usr_id, 'calls' => (int) $row->calls, 'credits' => (int) $row->creditsum, 'last' => (int) $row->lastused];
        }
        return $rows;
    }

    /**
     * Credits and calls per operation since a point in time.
     *
     * @return array<string, array{calls: int, credits: int}>
     */
    public function summary(int $since, ?int $objid = null): array
    {
        [$where, $params] = self::where($since, $objid, '');
        $records = Platform::db()->get_records_sql(
            'SELECT op_name, COUNT(1) AS calls, SUM(credits) AS creditsum FROM {' . self::TABLE . '} WHERE ' . $where . ' GROUP BY op_name ORDER BY op_name',
            $params
        );
        $summary = [];
        foreach ($records as $row) {
            $summary[(string) $row->op_name] = ['calls' => (int) $row->calls, 'credits' => (int) $row->creditsum];
        }
        return $summary;
    }

    /**
     * Remove a user's ledger rows (the conversation was erased).
     */
    public function forgetUser(int $objid, int $userid): void
    {
        Platform::db()->delete_records(self::TABLE, ['obj_id' => $objid, 'usr_id' => $userid]);
    }

    /**
     * The WHERE clause and its parameters: since a point in time, for one
     * activity or all, plus a fixed condition.
     *
     * @return array{0: string, 1: array}
     */
    private static function where(int $since, ?int $objid, string $extra): array
    {
        $where = 'created >= ?';
        $params = [$since];
        if ($extra !== '') {
            $where .= ' AND ' . $extra;
        }
        if ($objid !== null) {
            $where .= ' AND obj_id = ?';
            $params[] = $objid;
        }
        return [$where, $params];
    }
}
