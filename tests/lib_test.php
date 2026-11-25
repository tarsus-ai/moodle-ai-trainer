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

namespace mod_tarsuscoach;

use mod_tarsuscoach\local\activity\settings;

/**
 * The activity's lifecycle and Moodle callbacks: creation with the form's
 * settings, update, deletion, the features and the course reset.
 *
 * @package    mod_tarsuscoach
 * @category   test
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::tarsuscoach_add_instance
 * @covers     ::tarsuscoach_update_instance
 * @covers     ::tarsuscoach_delete_instance
 * @covers     ::tarsuscoach_supports
 * @covers     ::tarsuscoach_reset_userdata
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('tarsuscoach_add_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('tarsuscoach_update_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('tarsuscoach_delete_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('tarsuscoach_supports')]
#[\PHPUnit\Framework\Attributes\CoversFunction('tarsuscoach_reset_userdata')]
final class lib_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/tarsuscoach/lib.php');
        require_once($CFG->libdir . '/gradelib.php');
        parent::setUpBeforeClass();
    }

    /**
     * The activity's grade item.
     *
     * @param \stdClass $tarsus The activity.
     * @return \grade_item|false
     */
    private function grade_item(\stdClass $tarsus) {
        return \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'tarsuscoach',
            'iteminstance' => $tarsus->id,
            'courseid' => $tarsus->course,
        ]);
    }

    /**
     * A new activity has its record, the form's default settings and a
     * grade item out of 100.
     */
    public function test_add_instance_with_the_forms_defaults(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $tarsus = $this->getDataGenerator()->create_module('tarsuscoach', ['course' => $course->id, 'name' => 'Coach']);

        $this->assertSame('Coach', $DB->get_field('tarsuscoach', 'name', ['id' => $tarsus->id]));
        $settings = settings::load((int) $tarsus->id);
        $this->assertTrue($settings->askenabled);
        $this->assertTrue($settings->testenabled);
        $this->assertTrue($settings->buildenabled);
        $this->assertTrue($settings->discussenabled);
        $item = $this->grade_item($tarsus);
        $this->assertNotFalse($item);
        $this->assertEquals(100, $item->grademax);
    }

    /**
     * The settings of the activity form are stored.
     */
    public function test_add_instance_stores_the_forms_settings(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $tarsus = $this->getDataGenerator()->create_module('tarsuscoach', [
            'course' => $course->id,
            'testenabled' => 0,
            'discussenabled' => 0,
            'anticheat' => 1,
        ]);

        $settings = settings::load((int) $tarsus->id);
        $this->assertTrue($settings->askenabled);
        $this->assertFalse($settings->testenabled);
        $this->assertTrue($settings->buildenabled);
        $this->assertFalse($settings->discussenabled);
        $this->assertTrue($settings->anticheat);
    }

    /**
     * An update changes the record and the grade item's maximum.
     */
    public function test_update_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $tarsus = $this->getDataGenerator()->create_module('tarsuscoach', ['course' => $course->id]);

        $this->assertTrue(tarsuscoach_update_instance((object) [
            'instance' => $tarsus->id,
            'coursemodule' => $tarsus->cmid,
            'name' => 'Renamed coach',
            'intro' => '',
            'grade' => 50,
        ]));

        $this->assertSame('Renamed coach', $DB->get_field('tarsuscoach', 'name', ['id' => $tarsus->id]));
        $this->assertEquals(50, $this->grade_item($tarsus)->grademax);
    }

    /**
     * Deleting the activity removes its record, material choices, figures,
     * requests and grade item; a second deletion finds nothing.
     */
    public function test_delete_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $tarsus = $this->getDataGenerator()->create_module('tarsuscoach', ['course' => $course->id]);
        $now = time();
        $DB->insert_record('tarsuscoach_inclusion', ['tarsuscoachid' => $tarsus->id, 'resourceid' => 'r1', 'timecreated' => $now]);
        $DB->insert_record('tarsuscoach_usage', [
            'courseid' => $course->id,
            'tarsuscoachid' => $tarsus->id,
            'userid' => $user->id,
            'operation' => 'ask',
            'credits' => 1,
            'timecreated' => $now,
        ]);
        $DB->insert_record('tarsuscoach_progress', [
            'tarsuscoachid' => $tarsus->id,
            'userid' => $user->id,
            'earnedxp' => 3,
            'totalxp' => 10,
            'timemodified' => $now,
        ]);

        $this->assertTrue(tarsuscoach_delete_instance($tarsus->id));

        $this->assertFalse($DB->record_exists('tarsuscoach', ['id' => $tarsus->id]));
        foreach (['tarsuscoach_inclusion', 'tarsuscoach_usage', 'tarsuscoach_progress'] as $table) {
            $this->assertFalse($DB->record_exists($table, ['tarsuscoachid' => $tarsus->id]), $table);
        }
        $this->assertFalse($this->grade_item($tarsus));
        $this->assertFalse(tarsuscoach_delete_instance($tarsus->id));
    }

    /**
     * The features the activity declares.
     */
    public function test_supports(): void {
        $this->assertTrue(tarsuscoach_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(tarsuscoach_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertTrue(tarsuscoach_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(tarsuscoach_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertFalse(tarsuscoach_supports(FEATURE_GROUPINGS));
        $this->assertSame(MOD_PURPOSE_INTERACTIVECONTENT, tarsuscoach_supports(FEATURE_MOD_PURPOSE));
        $this->assertNull(tarsuscoach_supports('a feature nobody knows'));
    }

    /**
     * The course reset forgets the learners' figures only when asked to.
     */
    public function test_reset_userdata(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $tarsus = $this->getDataGenerator()->create_module('tarsuscoach', ['course' => $course->id]);
        $DB->insert_record('tarsuscoach_progress', [
            'tarsuscoachid' => $tarsus->id,
            'userid' => $user->id,
            'timemodified' => time(),
        ]);

        $this->assertSame([], tarsuscoach_reset_userdata((object) ['courseid' => $course->id]));
        $this->assertTrue($DB->record_exists('tarsuscoach_progress', ['tarsuscoachid' => $tarsus->id]));

        $status = tarsuscoach_reset_userdata((object) ['courseid' => $course->id, 'reset_tarsuscoach_progress' => 1]);
        $this->assertCount(1, $status);
        $this->assertFalse($status[0]['error']);
        $this->assertFalse($DB->record_exists('tarsuscoach_progress', ['tarsuscoachid' => $tarsus->id]));
    }
}
