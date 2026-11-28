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

namespace mod_tarsuscoach\local\service;

use mod_tarsuscoach\local\client\transport_exception;

/**
 * "Test connection" names a refused key as such: Tarsus answers a key,
 * brain or license key it does not accept with an upper-case sentence; an
 * outage or any other refusal keeps the message "try again in a moment".
 *
 * @package    mod_tarsuscoach
 * @category   test
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_tarsuscoach\local\service\messages
 */
#[\PHPUnit\Framework\Attributes\CoversClass(messages::class)]
final class messages_test extends \advanced_testcase {
    /**
     * Tarsus's answers: HTTP status, body, whether the key, brain or license key is refused.
     *
     * @return array
     */
    public static function answers(): array {
        return [
            'key unknown or inactive' => [400, 'UNKNOWN OR INACTIVE API KEY', true],
            'key unavailable' => [400, 'UNAVAILABLE API KEY', true],
            'brain unavailable' => [400, 'UNAVAILABLE BRAIN ID', true],
            'brain missing' => [400, 'BRAIN IS EITHER INEXISTENT OR NOT ACCESSIBLE', true],
            'brain memory missing' => [404, 'BRAIN MEMORY DOES NOT EXIST', true],
            'lower case' => [400, 'unknown or inactive api key', true],
            'no connection' => [0, '', false],
            'server error' => [500, 'UNABLE TO TRANSCRIBE AUDIO NOW. TRY AGAIN LATER', false],
            'other refusal' => [400, 'NO BITS WERE FOUND', false],
        ];
    }

    /**
     * Only a key, brain or license key Tarsus does not accept counts as refused access.
     *
     * @dataProvider answers
     * @param int $status The HTTP status.
     * @param string $body Tarsus's answer.
     * @param bool $refused Whether access is refused.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('answers')]
    public function test_refuses_access(int $status, string $body, bool $refused): void {
        $e = new transport_exception($status, 'unexpected HTTP status calling awareness/list/memories', $body);
        $this->assertSame($refused, messages::refuses_access($e));
    }
}
