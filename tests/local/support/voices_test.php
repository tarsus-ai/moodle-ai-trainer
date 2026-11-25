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
 * The brain's voice list: asked for at most once a day, never while the
 * kill switch is on (demo mode answers instead of the brain).
 *
 * @package    mod_tarsuscoach
 * @category   test
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_tarsuscoach\local\support\voices
 */
#[\PHPUnit\Framework\Attributes\CoversClass(voices::class)]
final class voices_test extends \advanced_testcase {
    /** @var array A stored list the brain would never send. */
    private const STORED = [['alias' => 'stored-voice', 'country' => 'de', 'gender' => 'female']];

    /**
     * Store a voice list as if it had been fetched at the given time.
     *
     * @param int $fetched When it was fetched.
     */
    private function store_list(int $fetched): void {
        config::set('voices_cache', json_encode(self::STORED));
        config::set('voices_cachetime', (string) $fetched);
    }

    /**
     * A list from the last day is used as it is.
     */
    public function test_recent_list_is_used(): void {
        $this->resetAfterTest();
        $this->store_list(time() - HOURSECS);
        $this->assertSame(self::STORED, voices::catalogue());
    }

    /**
     * An older list is fetched again.
     */
    public function test_stale_list_is_fetched_again(): void {
        $this->resetAfterTest();
        $this->store_list(time() - 3 * DAYSECS);
        $voices = voices::catalogue();
        $this->assertNotEmpty($voices);
        $this->assertNotSame('stored-voice', $voices[0]['alias']);
        $this->assertSame(json_encode($voices), config::get('voices_cache', ''));
    }

    /**
     * With the kill switch on, the brain is not asked: the stored list
     * stands, however old.
     */
    public function test_killswitch_keeps_the_stored_list(): void {
        $this->resetAfterTest();
        config::set('killswitch', '1');
        $this->store_list(time() - 3 * DAYSECS);
        $this->assertSame(self::STORED, voices::catalogue());
        $this->assertSame(self::STORED, voices::catalogue(true));
    }
}
