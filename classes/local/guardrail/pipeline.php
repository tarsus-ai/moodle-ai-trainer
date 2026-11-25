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

namespace mod_tarsuscoach\local\guardrail;

use mod_tarsuscoach\local\client\replay_exception;
use mod_tarsuscoach\local\client\transport_exception;
use mod_tarsuscoach\local\support\budget;
use mod_tarsuscoach\local\support\config;

/**
 * Client-side guardrails for every outbound Tarsus call.
 *
 * Gate order: kill switch -> circuit breaker -> monthly credit limit ->
 * rate limit -> send. A success resets the failure counter; repeated
 * outages (no answer, 5xx) trip the breaker, a refused request (4xx) does
 * not. The monthly limit only gates calls that may cost credits, judged
 * by the brain's own usage figure: reading the feed or the figures stays
 * possible when the month's credits are spent. The pipeline never sleeps: retries
 * are the caller's business.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pipeline
{
    /** Consecutive transport failures that trip the breaker. */
    public const FAILURE_THRESHOLD = 5;

    /** Seconds the breaker stays open before a trial call. */
    public const BREAKER_COOLDOWN = 300;

    /** @var state_store Where the breaker and the token bucket are kept. */
    private state_store $store;

    /**
     * Guardrails that keep their state in the given store.
     *
     * @param state_store|null $store The store; null for the plugin's database table.
     */
    public function __construct(?state_store $store = null) {
        $this->store = $store ?? new state_store();
    }

    /**
     * Run a guarded outbound call.
     *
     * @param callable $call The call, without arguments; its result is returned.
     * @param bool $charged Whether the call may cost AI credits: it is
     *                      refused once the month's limit is reached, by
     *                      the brain's own usage figure. Reading back
     *                      (feed, statistics, sources) is never refused.
     * @return mixed What the call returns.
     * @throws blocked_exception  When a gate refuses the call.
     * @throws transport_exception When the call fails at the transport level.
     */
    public function execute(callable $call, bool $charged = false) {
        $this->guard_killswitch();
        $this->guard_breaker();
        $this->guard_budget($charged);
        $this->guard_ratelimit();

        try {
            $result = $call();
        } catch (transport_exception $e) {
            // Only an outage (no answer, 5xx) trips the breaker; a 4xx is
            // Tarsus refusing this one request and says nothing about the
            // service.
            if ($e->is_outage()) {
                $this->record_failure();
            }
            throw $e;
        } catch (replay_exception $e) {
            // HTTP 409 is a normal answer of Build knowledge.
            $this->record_success();
            throw $e;
        }

        $this->record_success();

        return $result;
    }

    /**
     * Refuse every call while the kill switch is engaged.
     */
    private function guard_killswitch(): void {
        if (config::killswitch()) {
            throw new blocked_exception('killswitch');
        }
    }

    /**
     * Fail fast while the circuit breaker is open; close it after the cooldown.
     */
    private function guard_breaker(): void {
        $this->store->mutate('breaker', function (\stdClass $row): void {
            if ($row->state !== 'open') {
                return;
            }
            if (time() - (int) $row->opensince < self::BREAKER_COOLDOWN) {
                throw new blocked_exception('breaker');
            }
            // Cooldown elapsed: allow a trial call by closing the breaker.
            $row->state = 'closed';
            $row->failurecount = 0;
        });
    }

    /**
     * Refuse a call that may cost credits once the month's limit is
     * reached - by what the brain says it charged, not by an estimate.
     */
    private function guard_budget(bool $charged): void {
        if ($charged && budget::exhausted(true)) {
            throw new blocked_exception('budget');
        }
    }

    /**
     * Take a token from the refilling bucket, or refuse the call.
     */
    private function guard_ratelimit(): void {
        $capacity = config::rate_limit();
        $ratepersecond = $capacity / 60;

        $this->store->mutate('bucket', function (\stdClass $row) use ($capacity, $ratepersecond): void {
            $now = time();
            $last = (int) $row->lastrefill;
            $tokens = (float) $row->tokens;

            if ($last <= 0) {
                // First use: start with a full bucket.
                $tokens = $capacity;
                $last = $now;
            }

            $tokens = min((float) $capacity, $tokens + max(0, $now - $last) * $ratepersecond);
            $row->lastrefill = $now;

            if ($tokens < 1) {
                $row->tokens = $tokens;
                throw new blocked_exception('ratelimit');
            }

            $row->tokens = $tokens - 1;
        });
    }

    /**
     * Count an outage; FAILURE_THRESHOLD of them in a row open the breaker.
     */
    private function record_failure(): void {
        $this->store->mutate('breaker', function (\stdClass $row): void {
            $row->failurecount = (int) $row->failurecount + 1;
            if ($row->failurecount >= self::FAILURE_THRESHOLD) {
                $row->state = 'open';
                $row->opensince = time();
            }
        });
    }

    /**
     * Reset the failure count and close the breaker.
     */
    private function record_success(): void {
        $this->store->mutate('breaker', function (\stdClass $row): void {
            $row->failurecount = 0;
            $row->state = 'closed';
        });
    }

    /**
     * A snapshot of the guardrail state for the admin status page.
     *
     * @return array<string, \stdClass>
     */
    public function snapshot(): array {
        return [
            'breaker' => $this->store->get('breaker'),
            'bucket' => $this->store->get('bucket'),
        ];
    }

    /**
     * Close the breaker by hand (admin action).
     */
    public function reset_breaker(): void {
        $this->store->mutate('breaker', function (\stdClass $row): void {
            $row->failurecount = 0;
            $row->state = 'closed';
            $row->opensince = 0;
        });
    }
}
