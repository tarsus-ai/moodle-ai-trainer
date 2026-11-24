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
 * The structure of a Tarsus activity in a restore.
 *
 * @package    mod_tarsus
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The activity's settings and the material it may use.
 */
class restore_tarsus_activity_structure_step extends restore_activity_structure_step {
    /**
     * The elements read from the backup.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $paths = [
            new restore_path_element('tarsus', '/activity/tarsus'),
            new restore_path_element('tarsus_inclusion', '/activity/tarsus/inclusions/inclusion'),
        ];
        return $this->prepare_activity_structure($paths);
    }

    /**
     * The activity.
     *
     * @param array $data Its fields from the backup.
     */
    protected function process_tarsus($data) {
        global $DB;
        $data = (object) $data;
        $data->course = $this->get_courseid();
        if (isset($data->grade) && (int) $data->grade < 0) {
            // A scale: the restored copy of it, where there is one.
            $scale = $this->get_mappingid('scale', abs((int) $data->grade));
            if ($scale) {
                $data->grade = -$scale;
            }
        }
        $newitemid = $DB->insert_record('tarsus', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * One piece of material the activity may use; the task maps it to the
     * restored material once the whole course is restored.
     *
     * @param array $data Its fields from the backup.
     */
    protected function process_tarsus_inclusion($data) {
        global $DB;
        $data = (object) $data;
        $objid = $this->get_new_parentid('tarsus');
        $resourceid = (string) ($data->resource_id ?? '');
        if ($resourceid === '' || $DB->record_exists('tarsus_inc', ['obj_id' => $objid, 'resource_id' => $resourceid])) {
            return;
        }
        $DB->insert_record('tarsus_inc', (object) [
            'obj_id' => $objid,
            'resource_id' => $resourceid,
            'created' => time(),
        ]);
    }

    /**
     * The files of the description.
     */
    protected function after_execute() {
        $this->add_related_files('mod_tarsus', 'intro', null);
    }
}
