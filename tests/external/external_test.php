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

namespace mod_tarsuscoach\external;

use core_external\external_api;
use mod_tarsuscoach\local\activity\settings;
use mod_tarsuscoach\local\index\status;
use mod_tarsuscoach\local\material\resource_id;

/**
 * The coach's external functions, called the way the page calls them
 * (demo mode answers instead of Tarsus): who may call which, and what
 * they answer.
 *
 * @package    mod_tarsuscoach
 * @category   test
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_tarsuscoach\external\base
 * @covers     \mod_tarsuscoach\external\get_feed
 * @covers     \mod_tarsuscoach\external\get_statistics
 * @covers     \mod_tarsuscoach\external\save_settings
 * @covers     \mod_tarsuscoach\external\set_material_included
 */
#[\PHPUnit\Framework\Attributes\CoversClass(base::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_feed::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_statistics::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(save_settings::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(set_material_included::class)]
final class external_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The activity. */
    private \stdClass $tarsus;

    /** @var \stdClass A learner of the course. */
    private \stdClass $learner;

    /** @var \stdClass A teacher of the course. */
    private \stdClass $teacher;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->tarsus = $generator->create_module('tarsuscoach', ['course' => $this->course->id]);
        $this->learner = $generator->create_and_enrol($this->course, 'student');
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * Call an external function as the page does; the coach's reply.
     *
     * @param string $name The function, without the plugin's prefix.
     * @param array $args Its parameters besides the course module.
     * @return array The reply, decoded.
     */
    private function call(string $name, array $args = []): array {
        // The page sends the session key with every request.
        $_POST['sesskey'] = sesskey();
        $result = external_api::call_external_function('mod_tarsuscoach_' . $name, ['cmid' => $this->tarsus->cmid] + $args);
        $this->assertFalse($result['error'], json_encode($result['exception'] ?? null));
        return json_decode($result['data']['json'], true);
    }

    /**
     * The error of a call that must fail.
     *
     * @param string $name The function, without the plugin's prefix.
     * @param array $args Its parameters besides the course module.
     * @return string The exception's error code.
     */
    private function refused(string $name, array $args = []): string {
        // The page sends the session key with every request.
        $_POST['sesskey'] = sesskey();
        $result = external_api::call_external_function('mod_tarsuscoach_' . $name, ['cmid' => $this->tarsus->cmid] + $args);
        $this->assertTrue($result['error']);
        return (string) $result['exception']->errorcode;
    }

    /**
     * A page of the course, indexed in the brain.
     *
     * @return string Its resource id.
     */
    private function indexed_page(): string {
        global $DB;
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $resourceid = resource_id::build((int) $this->course->id, 'page', (int) $page->cmid);
        $DB->insert_record('tarsuscoach_material', [
            'resourceid' => $resourceid,
            'assetid' => resource_id::asset_id((int) $this->course->id),
            'courseid' => $this->course->id,
            'sourcetype' => 'page',
            'sourceid' => $page->cmid,
            'status' => status::INDEXED,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        return $resourceid;
    }

    /**
     * Without material in scope, the feed says so.
     */
    public function test_feed_without_material(): void {
        $this->setUser($this->learner);
        $reply = $this->call('get_feed');
        $this->assertSame('noscope', $reply['status']);
        $this->assertSame([], $reply['items']);
    }

    /**
     * With included material, the learner gets the feed.
     */
    public function test_feed_with_material(): void {
        global $DB;
        $resourceid = $this->indexed_page();
        $DB->insert_record('tarsuscoach_inclusion', ['tarsuscoachid' => $this->tarsus->id, 'resourceid' => $resourceid,
            'timecreated' => time()]);
        $this->setUser($this->learner);
        $reply = $this->call('get_feed');
        $this->assertSame('success', $reply['status'], json_encode($reply));
        $this->assertArrayHasKey('items', $reply);
    }

    /**
     * Someone outside the course cannot call the coach.
     */
    public function test_outsider_is_refused(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame('requireloginerror', $this->refused('get_feed'));
    }

    /**
     * The teacher's functions are refused to learners.
     */
    public function test_teacher_functions(): void {
        $this->setUser($this->learner);
        $this->assertSame('nopermissions', $this->refused('get_statistics'));
        $this->assertSame('nopermissions', $this->refused('save_settings', ['values' => '{}']));

        $this->setUser($this->teacher);
        $this->assertSame('success', $this->call('get_statistics')['status']);
    }

    /**
     * The teacher's settings are stored.
     */
    public function test_save_settings(): void {
        $this->setUser($this->teacher);
        $reply = $this->call('save_settings', ['values' => json_encode(['testenabled' => false])]);
        $this->assertSame('success', $reply['status'], json_encode($reply));
        $this->assertFalse(settings::load((int) $this->tarsus->id)->testenabled);
        $this->assertTrue(settings::load((int) $this->tarsus->id)->askenabled);
    }

    /**
     * The teacher chooses which indexed material the activity may use.
     */
    public function test_set_material_included(): void {
        global $DB;
        $resourceid = $this->indexed_page();
        $this->setUser($this->teacher);
        $where = ['tarsuscoachid' => $this->tarsus->id, 'resourceid' => $resourceid];

        $this->call('set_material_included', ['resourceid' => $resourceid, 'included' => true]);
        $this->assertTrue($DB->record_exists('tarsuscoach_inclusion', $where));

        $this->call('set_material_included', ['resourceid' => $resourceid, 'included' => false]);
        $this->assertFalse($DB->record_exists('tarsuscoach_inclusion', $where));
    }
}
