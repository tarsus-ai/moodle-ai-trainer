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
use mod_tarsuscoach\local\material\resource_id;

/**
 * A course copied through backup and restore keeps the activity's
 * settings, and its material choices follow the copied material.
 *
 * @package    mod_tarsuscoach
 * @category   test
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_tarsuscoach_activity_structure_step
 * @covers     \restore_tarsuscoach_activity_structure_step
 * @covers     \restore_tarsuscoach_activity_task
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_tarsuscoach_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_tarsuscoach_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_tarsuscoach_activity_task::class)]
final class backup_restore_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        parent::setUpBeforeClass();
    }

    /**
     * Copy a course into a new one through backup and restore.
     *
     * @param \stdClass $course The course.
     * @return int The new course's id.
     */
    private function copy_course(\stdClass $course): int {
        global $USER;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Copy of ' . $course->fullname, 'copy' . $course->id, $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return (int) $newcourseid;
    }

    /**
     * The copy has the activity with its settings; the included page now
     * means the copied page, and material from elsewhere is dropped.
     */
    public function test_course_copy(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $tarsus = $this->getDataGenerator()->create_module('tarsuscoach', [
            'course' => $course->id,
            'name' => 'Coach',
            'testenabled' => 0,
        ]);
        $included = [resource_id::build((int) $course->id, 'page', (int) $page->cmid), resource_id::build(999999, 'page', 999999)];
        foreach ($included as $id) {
            $DB->insert_record('tarsuscoach_inclusion', [
                'tarsuscoachid' => $tarsus->id,
                'resourceid' => $id,
                'timecreated' => time(),
            ]);
        }

        $newcourseid = $this->copy_course($course);

        $copy = $DB->get_record('tarsuscoach', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame('Coach', $copy->name);
        $settings = settings::load((int) $copy->id);
        $this->assertTrue($settings->askenabled);
        $this->assertFalse($settings->testenabled);

        $newpage = $DB->get_record('page', ['course' => $newcourseid], '*', MUST_EXIST);
        $newpagecm = get_coursemodule_from_instance('page', $newpage->id, $newcourseid, false, MUST_EXIST);
        $this->assertSame(
            [resource_id::build($newcourseid, 'page', (int) $newpagecm->id)],
            array_values($DB->get_fieldset_select('tarsuscoach_inclusion', 'resourceid', 'tarsuscoachid = ?', [$copy->id]))
        );
    }
}
