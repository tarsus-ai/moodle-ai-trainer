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
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The activity's settings and the material it may use.
 */
class backup_tarsuscoach_activity_structure_step extends backup_activity_structure_step {
    /**
     * The elements of the backup.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $tarsus = new backup_nested_element('tarsuscoach', ['id'], [
            'name', 'intro', 'introformat', 'grade', 'conversationstyle', 'dqr', 'industry', 'taxonomylevels', 'questiontypes',
            'operators', 'anticheat', 'learntempo', 'askenabled', 'testenabled', 'buildenabled', 'discussenabled', 'gifenabled',
            'learningmethod', 'interactiontype', 'thinkinglevel', 'visualaccessibility', 'referencesenabled', 'voiceinput', 'completionprogress',
            'timecreated', 'timemodified',
        ]);
        $inclusions = new backup_nested_element('inclusions');
        $inclusion = new backup_nested_element('inclusion', ['id'], ['resourceid', 'timecreated']);

        $tarsus->add_child($inclusions);
        $inclusions->add_child($inclusion);

        $tarsus->set_source_table('tarsuscoach', ['id' => backup::VAR_ACTIVITYID]);
        $inclusion->set_source_table('tarsuscoach_inclusion', ['tarsuscoachid' => backup::VAR_PARENTID], 'id ASC');

        $tarsus->annotate_files('mod_tarsuscoach', 'intro', null);

        return $this->prepare_activity_structure($tarsus);
    }
}
