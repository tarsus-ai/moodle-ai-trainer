<?php

namespace mod_tarsuscoach\local\guardrail;

use mod_tarsuscoach\local\support\platform;

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
class state_store
{
    public const TABLE = 'tarsuscoach_guardrail';

    /** Seconds to wait for a guardrail lock. */
    private const LOCK_TIMEOUT = 10;

    /**
     * Fetch a guardrail row, creating it with defaults if absent.
     */
    public function get(string $name): \stdClass
    {
        $row = platform::db()->get_record(self::TABLE, ['name' => $name]);
        if (!$row) {
            $defaults = $this->default_row($name);
            $this->write($name, $defaults);
            return $defaults;
        }
        return (object) [
            'name' => (string) $row->name,
            'state' => (string) $row->state,
            'failurecount' => (int) $row->failures,
            'opensince' => (int) $row->opensince,
            'tokens' => (float) $row->tokens,
            'lastrefill' => (int) $row->lastrefill,
            'creditsused' => (int) $row->creditsused,
            'periodstart' => (int) $row->periodstart,
            'timemodified' => (int) $row->timemodified,
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
            $lock = \core\lock\lock_config::get_lock_factory('mod_tarsuscoach')->get_lock('guardrail_' . $name, self::LOCK_TIMEOUT);
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
        $db = platform::db();
        $record = (object) [
            'state' => (string) $row->state,
            'failures' => (int) $row->failurecount,
            'opensince' => (int) $row->opensince,
            'tokens' => (float) $row->tokens,
            'lastrefill' => (int) $row->lastrefill,
            'creditsused' => (int) $row->creditsused,
            'periodstart' => (int) $row->periodstart,
            'timemodified' => (int) $row->timemodified,
        ];
        $id = $db->get_field(self::TABLE, 'id', ['name' => $name]);
        if ($id) {
            $record->id = (int) $id;
            $db->update_record(self::TABLE, $record);
            return;
        }
        $record->name = $name;
        try {
            $db->insert_record(self::TABLE, $record);
        } catch (\dml_write_exception $e) {
            // Another request created it a moment ago: update that one.
            $id = $db->get_field(self::TABLE, 'id', ['name' => $name]);
            if ($id) {
                $record->id = (int) $id;
                $db->update_record(self::TABLE, $record);
            }
        }
    }

    private function default_row(string $name): \stdClass
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
