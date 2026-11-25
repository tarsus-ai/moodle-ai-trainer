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

use mod_tarsuscoach\local\client\transport_exception;
use mod_tarsuscoach\local\support\config;

/**
 * The guardrails every call to Tarsus goes through: the kill switch, the
 * circuit breaker and the rate limit.
 *
 * @package    mod_tarsuscoach
 * @category   test
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_tarsuscoach\local\guardrail\pipeline
 */
#[\PHPUnit\Framework\Attributes\CoversClass(pipeline::class)]
final class pipeline_test extends \advanced_testcase {
    /**
     * The reason a call was refused, or '' when it was made.
     *
     * @param pipeline $pipeline The guardrails.
     * @param callable $call The call.
     * @return string
     */
    private function refusal(pipeline $pipeline, callable $call): string {
        try {
            $pipeline->execute($call);
        } catch (blocked_exception $e) {
            return $e->reason;
        }
        return '';
    }

    /**
     * Fail a number of calls the way Tarsus fails.
     *
     * @param pipeline $pipeline The guardrails.
     * @param int $count How many calls.
     * @param int $status The HTTP status of the failure (0: no answer).
     */
    private function fail_calls(pipeline $pipeline, int $count, int $status): void {
        for ($i = 0; $i < $count; $i++) {
            try {
                $pipeline->execute(function () use ($status) {
                    throw new transport_exception($status, 'failed');
                });
            } catch (transport_exception $e) {
                // The failure is passed on to the caller, as it should be.
                $this->assertSame($status, $e->httpstatus);
            }
        }
    }

    /**
     * A call the gates let through is made and its result comes back.
     */
    public function test_call_is_made(): void {
        $this->resetAfterTest();
        $this->assertSame('answer', (new pipeline())->execute(fn() => 'answer'));
    }

    /**
     * With the kill switch on, every call is refused and none is made.
     */
    public function test_killswitch_refuses_every_call(): void {
        $this->resetAfterTest();
        config::set('killswitch', '1');
        $made = false;
        $call = function () use (&$made) {
            $made = true;
            return 'answer';
        };
        $this->assertSame('killswitch', $this->refusal(new pipeline(), $call));
        $this->assertFalse($made);

        config::set('killswitch', '0');
        $this->assertSame('', $this->refusal(new pipeline(), $call));
        $this->assertTrue($made);
    }

    /**
     * Five outages in a row open the breaker; calls are then refused
     * without being made.
     */
    public function test_outages_open_the_breaker(): void {
        $this->resetAfterTest();
        $pipeline = new pipeline();
        $this->fail_calls($pipeline, pipeline::FAILURE_THRESHOLD - 1, 503);
        $this->assertSame('', $this->refusal($pipeline, fn() => 'answer'), 'Four outages and a success: still closed.');

        $this->fail_calls($pipeline, pipeline::FAILURE_THRESHOLD, 0);
        $made = false;
        $this->assertSame('breaker', $this->refusal($pipeline, function () use (&$made) {
            $made = true;
        }));
        $this->assertFalse($made);
    }

    /**
     * Tarsus refusing single requests (4xx) says nothing about the service:
     * the breaker stays closed.
     */
    public function test_refusals_do_not_open_the_breaker(): void {
        $this->resetAfterTest();
        $pipeline = new pipeline();
        $this->fail_calls($pipeline, 2 * pipeline::FAILURE_THRESHOLD, 422);
        $this->assertSame('', $this->refusal($pipeline, fn() => 'answer'));
    }

    /**
     * After the cool-down, a trial call is let through and closes the
     * breaker again; resetting it closes it at once.
     */
    public function test_breaker_closes_again(): void {
        $this->resetAfterTest();
        $pipeline = new pipeline();
        $this->fail_calls($pipeline, pipeline::FAILURE_THRESHOLD, 500);
        $this->assertSame('breaker', $this->refusal($pipeline, fn() => 'answer'));

        (new state_store())->mutate('breaker', function (\stdClass $row): void {
            $row->opensince = time() - pipeline::BREAKER_COOLDOWN - 1;
        });
        $this->assertSame('', $this->refusal($pipeline, fn() => 'answer'));
        $this->assertSame('closed', $pipeline->snapshot()['breaker']->state);

        $this->fail_calls($pipeline, pipeline::FAILURE_THRESHOLD, 500);
        $pipeline->reset_breaker();
        $this->assertSame('', $this->refusal($pipeline, fn() => 'answer'));
    }

    /**
     * The rate limit lets the configured number of calls per minute through.
     */
    public function test_rate_limit(): void {
        $this->resetAfterTest();
        config::set('ratelimit', '3');
        $pipeline = new pipeline();
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame('', $this->refusal($pipeline, fn() => 'answer'), 'Call ' . ($i + 1));
        }
        $this->assertSame('ratelimit', $this->refusal($pipeline, fn() => 'answer'));
    }
}
