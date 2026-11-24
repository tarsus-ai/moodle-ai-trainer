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
 * The structure of a Tarsus activity in a backup.
 *
 * @package    mod_tarsus
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The activity's settings and the material it may use.
 */
class backup_tarsus_activity_structure_step extends backup_activity_structure_step {
    /**
     * The elements of the backup.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $tarsus = new backup_nested_element('tarsus', ['id'], [
            'name', 'intro', 'introformat', 'grade', 'conv_style', 'dqr', 'industry', 'tax_levels', 'q_types',
            'operators', 'anticheat', 'learn_tempo', 'ask_on', 'test_on', 'build_on', 'discuss_on', 'gif_on',
            'learn_method', 'interaction', 'think_level', 'visual_acc', 'refs_on', 'voice_on', 'completionprogress',
            'timecreated', 'timemodified',
        ]);
        $inclusions = new backup_nested_element('inclusions');
        $inclusion = new backup_nested_element('inclusion', ['id'], ['resource_id', 'created']);

        $tarsus->add_child($inclusions);
        $inclusions->add_child($inclusion);

        $tarsus->set_source_table('tarsus', ['id' => backup::VAR_ACTIVITYID]);
        $inclusion->set_source_table('tarsus_inc', ['obj_id' => backup::VAR_PARENTID], 'id ASC');

        $tarsus->annotate_files('mod_tarsus', 'intro', null);

        return $this->prepare_activity_structure($tarsus);
    }
}
