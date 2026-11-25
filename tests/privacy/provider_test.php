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

namespace mod_tarsuscoach\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * What the plugin keeps about learners, and how Moodle's privacy tools
 * find, export and delete it (demo mode: the conversations in the brain
 * are not called).
 *
 * @package    mod_tarsuscoach
 * @category   test
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_tarsuscoach\privacy\provider
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /** @var \stdClass The activity. */
    private \stdClass $tarsus;

    /** @var \context_module The activity's context. */
    private \context_module $context;

    /** @var \stdClass A learner with figures and a request. */
    private \stdClass $learner;

    /** @var \stdClass A learner with figures only. */
    private \stdClass $other;

    #[\Override]
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->tarsus = $this->getDataGenerator()->create_module('tarsuscoach', ['course' => $course->id]);
        $this->context = \context_module::instance($this->tarsus->cmid);
        $this->learner = $this->getDataGenerator()->create_user();
        $this->other = $this->getDataGenerator()->create_user();
        foreach ([$this->learner, $this->other] as $user) {
            $DB->insert_record('tarsuscoach_progress', [
                'tarsuscoachid' => $this->tarsus->id,
                'userid' => $user->id,
                'earnedxp' => 3,
                'totalxp' => 10,
                'earnedunits' => 1,
                'totalunits' => 4,
                'overallprogress' => 30,
                'timemodified' => time(),
            ]);
        }
        $DB->insert_record('tarsuscoach_usage', [
            'courseid' => $course->id,
            'tarsuscoachid' => $this->tarsus->id,
            'userid' => $this->learner->id,
            'operation' => 'ask',
            'credits' => 1,
            'timecreated' => time(),
        ]);
    }

    /**
     * Whether a learner still has figures or requests in the activity.
     *
     * @param \stdClass $user The learner.
     * @return bool
     */
    private function has_data(\stdClass $user): bool {
        global $DB;
        $where = ['tarsuscoachid' => $this->tarsus->id, 'userid' => $user->id];
        return $DB->record_exists('tarsuscoach_progress', $where) || $DB->record_exists('tarsuscoach_usage', $where);
    }

    /**
     * The two tables and what is sent to Tarsus are declared.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('mod_tarsuscoach'));
        $names = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        $this->assertContains('tarsuscoach_progress', $names);
        $this->assertContains('tarsuscoach_usage', $names);
        $this->assertContains('tarsuscoach', $names);
    }

    /**
     * The activity is found for a learner with data, for nobody else.
     */
    public function test_get_contexts_for_userid(): void {
        $this->assertSame(
            [(int) $this->context->id],
            array_map('intval', provider::get_contexts_for_userid((int) $this->learner->id)->get_contextids())
        );
        $nobody = $this->getDataGenerator()->create_user();
        $this->assertCount(0, provider::get_contexts_for_userid((int) $nobody->id));
    }

    /**
     * The learners with data in the activity.
     */
    public function test_get_users_in_context(): void {
        $userlist = new userlist($this->context, 'mod_tarsuscoach');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->learner->id, $this->other->id], $userlist->get_userids());
    }

    /**
     * A learner's figures and requests are exported.
     */
    public function test_export_user_data(): void {
        $this->export_context_data_for_user((int) $this->learner->id, $this->context, 'mod_tarsuscoach');
        $data = writer::with_context($this->context)->get_data([]);
        $this->assertSame('3 / 10', $data->figures->experiencepoints);
        $this->assertSame('1 / 4', $data->figures->learningunits);
        $this->assertSame('30 %', $data->figures->progress);
        $this->assertCount(1, $data->requests);
        $this->assertSame('ask', $data->requests[0]->request);
    }

    /**
     * Deleting one learner's data leaves the other's.
     */
    public function test_delete_data_for_user(): void {
        provider::delete_data_for_user(new approved_contextlist($this->learner, 'mod_tarsuscoach', [$this->context->id]));
        $this->assertFalse($this->has_data($this->learner));
        $this->assertTrue($this->has_data($this->other));
    }

    /**
     * Deleting the data of chosen learners.
     */
    public function test_delete_data_for_users(): void {
        provider::delete_data_for_users(new approved_userlist($this->context, 'mod_tarsuscoach', [$this->other->id]));
        $this->assertTrue($this->has_data($this->learner));
        $this->assertFalse($this->has_data($this->other));
    }

    /**
     * Deleting everything of the activity.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertFalse($this->has_data($this->learner));
        $this->assertFalse($this->has_data($this->other));
    }
}
