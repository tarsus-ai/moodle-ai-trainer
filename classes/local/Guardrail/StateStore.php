<?php

namespace mod_tarsus\local\Guardrail;

use mod_tarsus\local\Support\Platform;

/**
 * Persistent guardrail state with locked read-modify-write.
 *
 * The breaker, token bucket and budget counters are shared mutable state.
 * Concurrent requests and cron runs must not corrupt them, so every
 * mutation runs under a named database lock.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
class StateStore
{
    public const TABLE = 'tarsus_grd';

    /** Seconds to wait for a guardrail lock. */
    private const LOCK_TIMEOUT = 10;

    /**
     * Fetch a guardrail row, creating it with defaults if absent.
     */
    public function get(string $name): \stdClass
    {
        $row = Platform::db()->get_record(self::TABLE, ['g_name' => $name]);
        if (!$row) {
            $defaults = $this->defaultRow($name);
            $this->write($name, $defaults);
            return $defaults;
        }
        return (object) [
            'name' => (string) $row->g_name,
            'state' => (string) $row->g_state,
            'failurecount' => (int) $row->failures,
            'opensince' => (int) $row->open_since,
            'tokens' => (float) $row->tokens,
            'lastrefill' => (int) $row->last_refill,
            'creditsused' => (int) $row->credits_used,
            'periodstart' => (int) $row->period_start,
            'timemodified' => (int) $row->modified,
        ];
    }

    /**
     * Read, mutate and persist a guardrail row under a lock. The callback
     * mutates the row in place; if it throws, nothing is written.
     *
     * @param callable(\stdClass): void $mutator
     */
    public function mutate(string $name, callable $mutator): \stdClass
    {
        $lock = null;
        try {
            $lock = \core\lock\lock_config::get_lock_factory('mod_tarsus')->get_lock('guardrail_' . $name, self::LOCK_TIMEOUT);
        } catch (\Throwable $e) {
            // No lock to be had: carry on unlocked; the state is advisory
            // and a lost update only costs precision.
            $lock = null;
        }

        try {
            $row = $this->get($name);
            $mutator($row);
            $row->timemodified = time();
            $this->write($name, $row);
            return $row;
        } finally {
            if ($lock) {
                $lock->release();
            }
        }
    }

    /**
     * Store a guardrail row (insert or update by name).
     */
    private function write(string $name, \stdClass $row): void
    {
        $db = Platform::db();
        $record = (object) [
            'g_state' => (string) $row->state,
            'failures' => (int) $row->failurecount,
            'open_since' => (int) $row->opensince,
            'tokens' => (float) $row->tokens,
            'last_refill' => (int) $row->lastrefill,
            'credits_used' => (int) $row->creditsused,
            'period_start' => (int) $row->periodstart,
            'modified' => (int) $row->timemodified,
        ];
        $id = $db->get_field(self::TABLE, 'id', ['g_name' => $name]);
        if ($id) {
            $record->id = (int) $id;
            $db->update_record(self::TABLE, $record);
            return;
        }
        $record->g_name = $name;
        try {
            $db->insert_record(self::TABLE, $record);
        } catch (\dml_write_exception $e) {
            // Another request created it a moment ago: update that one.
            $id = $db->get_field(self::TABLE, 'id', ['g_name' => $name]);
            if ($id) {
                $record->id = (int) $id;
                $db->update_record(self::TABLE, $record);
            }
        }
    }

    private function defaultRow(string $name): \stdClass
    {
        return (object) [
            'name' => $name,
            'state' => 'closed',
            'failurecount' => 0,
            'opensince' => 0,
            'tokens' => 0.0,
            'lastrefill' => 0,
            'creditsused' => 0,
            'periodstart' => 0,
            'timemodified' => time(),
        ];
    }
}
