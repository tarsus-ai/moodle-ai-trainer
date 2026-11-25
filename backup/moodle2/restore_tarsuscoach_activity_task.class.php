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

/**
 * Restore of a Tarsus activity.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/tarsuscoach/backup/moodle2/restore_tarsuscoach_stepslib.php');

use mod_tarsuscoach\local\material\resource_id;

/**
 * The restore task of a Tarsus activity.
 */
class restore_tarsuscoach_activity_task extends restore_activity_task {
    /**
     * No settings of its own.
     */
    protected function define_my_settings() {
    }

    /**
     * One step: the activity and its material selection.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_tarsuscoach_activity_structure_step('tarsus_structure', 'tarsus.xml'));
    }

    /**
     * Texts that may contain links to other activities.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('tarsuscoach', ['intro'], 'tarsuscoach')];
    }

    /**
     * The links the backup turned into markers.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('TARSUSCOACHVIEWBYID', '/mod/tarsuscoach/view.php?id=$1', 'course_module'),
            new restore_decode_rule('TARSUSCOACHINDEX', '/mod/tarsuscoach/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Log entries of the activity.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('tarsuscoach', 'add', 'view.php?id={course_module}', '{tarsuscoach}'),
            new restore_log_rule('tarsuscoach', 'update', 'view.php?id={course_module}', '{tarsuscoach}'),
            new restore_log_rule('tarsuscoach', 'view', 'view.php?id={course_module}', '{tarsuscoach}'),
        ];
    }

    /**
     * Log entries of the course's list of Tarsus activities.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course() {
        return [new restore_log_rule('tarsuscoach', 'view all', 'index.php?id={course}', null)];
    }

    /**
     * Once the whole course is restored: the material the activity may use
     * is the restored copy of the material it used. In the same course (a
     * duplicated activity) it stays the same material; material that was
     * not restored with it is dropped from the selection.
     */
    public function after_restore() {
        global $DB;
        $tarsus = $DB->get_record('tarsuscoach', ['id' => $this->get_activityid()], 'id, course');
        if (!$tarsus) {
            return;
        }
        $courseid = (int) $tarsus->course;
        foreach ($DB->get_records('tarsuscoach_inclusion', ['tarsuscoachid' => $tarsus->id]) as $row) {
            $parts = resource_id::parse((string) $row->resourceid);
            if ($parts !== null && $parts['containerref'] === $courseid && resource_id::is_local((string) $row->resourceid)) {
                continue;
            }
            $restored = $parts === null ? false
                : restore_dbops::get_backup_ids_record($this->get_restoreid(), 'course_module', $parts['refid']);
            $resourceid = '';
            if ($restored && !empty($restored->newitemid)) {
                $resourceid = resource_id::build($courseid, $parts['type'], (int) $restored->newitemid, $parts['bitkey']);
            }
            if ($resourceid === '' || $DB->record_exists('tarsuscoach_inclusion', ['tarsuscoachid' => $tarsus->id, 'resourceid' => $resourceid])) {
                $DB->delete_records('tarsuscoach_inclusion', ['id' => $row->id]);
                continue;
            }
            $DB->set_field('tarsuscoach_inclusion', 'resourceid', $resourceid, ['id' => $row->id]);
        }
    }
}
