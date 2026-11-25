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

namespace mod_tarsuscoach\local\client;

/**
 * The HTTP client of Tarsus, through Moodle's curl class (its answers
 * mocked; no request leaves the test).
 *
 * @package    mod_tarsuscoach
 * @category   test
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_tarsuscoach\local\client\http_client
 */
#[\PHPUnit\Framework\Attributes\CoversClass(http_client::class)]
final class http_client_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        parent::setUpBeforeClass();
    }

    /**
     * A client of a made-up Tarsus.
     *
     * @return http_client
     */
    private function client(): http_client {
        return new http_client('https://tarsus.example.org', 'your-api-key', 'your-brain-id');
    }

    /**
     * The brains of the account, read from Tarsus's answer; entries that
     * are not brains are left out.
     */
    public function test_list_brains(): void {
        $this->resetAfterTest();
        \curl::mock_response(json_encode(['awareness' => ['brain_ids' => [
            ['_id' => 'brain-1', 'active' => true, 'brainLanguage' => 'de', 'brainTag' => 'default',
                'brainCreatedDateTime' => 1700000000],
            ['_id' => 'brain-2'],
            'not a brain',
            ['active' => true],
        ]]]));

        $brains = $this->client()->list_brains();

        $this->assertSame([
            ['brainid' => 'brain-1', 'active' => true, 'language' => 'de', 'tag' => 'default', 'timecreated' => 1700000000],
            ['brainid' => 'brain-2', 'active' => false, 'language' => '', 'tag' => '', 'timecreated' => 0],
        ], $brains);
    }

    /**
     * A server Moodle's security settings block is a configuration error,
     * and no request is sent.
     */
    public function test_blocked_host(): void {
        $this->resetAfterTest();
        set_config('curlsecurityblockedhosts', 'tarsus.example.org');
        try {
            $this->client()->list_brains();
            $this->fail('A blocked server must not be called.');
        } catch (configuration_exception $e) {
            $this->assertSame('err_urlblocked', $e->langkey);
        }
        // Moodle reports the blocked address itself (a debugging message in tests).
        $this->resetDebugging();
    }

    /**
     * Without a server, nothing is sent.
     */
    public function test_not_configured(): void {
        $this->resetAfterTest();
        try {
            (new http_client('', 'your-api-key', 'your-brain-id'))->list_brains();
            $this->fail('There is no server to call.');
        } catch (configuration_exception $e) {
            $this->assertSame('err_notconfigured', $e->langkey);
        }
    }
}
