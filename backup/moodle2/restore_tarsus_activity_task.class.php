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
 * @package    mod_tarsus
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/tarsus/backup/moodle2/restore_tarsus_stepslib.php');

use mod_tarsus\local\Material\ResourceId;

/**
 * The restore task of a Tarsus activity.
 */
class restore_tarsus_activity_task extends restore_activity_task {
    /**
     * No settings of its own.
     */
    protected function define_my_settings() {
    }

    /**
     * One step: the activity and its material selection.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_tarsus_activity_structure_step('tarsus_structure', 'tarsus.xml'));
    }

    /**
     * Texts that may contain links to other activities.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('tarsus', ['intro'], 'tarsus')];
    }

    /**
     * The links the backup turned into markers.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('TARSUSVIEWBYID', '/mod/tarsus/view.php?id=$1', 'course_module'),
            new restore_decode_rule('TARSUSINDEX', '/mod/tarsus/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Log entries of the activity.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('tarsus', 'add', 'view.php?id={course_module}', '{tarsus}'),
            new restore_log_rule('tarsus', 'update', 'view.php?id={course_module}', '{tarsus}'),
            new restore_log_rule('tarsus', 'view', 'view.php?id={course_module}', '{tarsus}'),
        ];
    }

    /**
     * Log entries of the course's list of Tarsus activities.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course() {
        return [new restore_log_rule('tarsus', 'view all', 'index.php?id={course}', null)];
    }

    /**
     * Once the whole course is restored: the material the activity may use
     * is the restored copy of the material it used. In the same course (a
     * duplicated activity) it stays the same material; material that was
     * not restored with it is dropped from the selection.
     */
    public function after_restore() {
        global $DB;
        $tarsus = $DB->get_record('tarsus', ['id' => $this->get_activityid()], 'id, course');
        if (!$tarsus) {
            return;
        }
        $courseid = (int) $tarsus->course;
        foreach ($DB->get_records('tarsus_inc', ['obj_id' => $tarsus->id]) as $row) {
            $parts = ResourceId::parse((string) $row->resource_id);
            if ($parts !== null && $parts['containerref'] === $courseid && ResourceId::isLocal((string) $row->resource_id)) {
                continue;
            }
            $restored = $parts === null ? false
                : restore_dbops::get_backup_ids_record($this->get_restoreid(), 'course_module', $parts['refid']);
            $resourceid = '';
            if ($restored && !empty($restored->newitemid)) {
                $resourceid = ResourceId::build($courseid, $parts['type'], (int) $restored->newitemid, $parts['bitkey']);
            }
            if ($resourceid === '' || $DB->record_exists('tarsus_inc', ['obj_id' => $tarsus->id, 'resource_id' => $resourceid])) {
                $DB->delete_records('tarsus_inc', ['id' => $row->id]);
                continue;
            }
            $DB->set_field('tarsus_inc', 'resource_id', $resourceid, ['id' => $row->id]);
        }
    }
}
