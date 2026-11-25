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

use mod_tarsuscoach\local\activity\didactics;
use mod_tarsuscoach\local\activity\settings;

/**
 * Data generator of mod_tarsuscoach: Tarsus coach activities with the
 * activity form's defaults, for PHPUnit and Behat.
 *
 * @package    mod_tarsuscoach
 * @category   test
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_tarsuscoach_generator extends testing_module_generator {
    /**
     * A Tarsus coach activity. Any setting of the activity form may be
     * given, the others take the form's defaults; the completion rule is
     * off unless completionprogress is given.
     *
     * @param array|stdClass|null $record The activity's values.
     * @param array|null $options Course module options.
     * @return stdClass The activity's record, with its cmid.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;
        $defaults = new settings();
        $values = [
            'name' => 'Tarsus coach ' . ($this->instancecount + 1),
            'intro' => 'A Tarsus coach for the course.',
            'introformat' => FORMAT_HTML,
            'grade' => 100,
            'askenabled' => (int) $defaults->askenabled,
            'testenabled' => (int) $defaults->testenabled,
            'buildenabled' => (int) $defaults->buildenabled,
            'discussenabled' => (int) $defaults->discussenabled,
            'voiceinput' => (int) $defaults->voiceinput,
            'referencesenabled' => (int) $defaults->referencesenabled,
            'visualaccessibility' => (int) $defaults->visualaccessibility,
            'anticheat' => (int) $defaults->anticheat,
            'gifenabled' => (int) $defaults->gifenabled,
            'completionprogress' => 0,
        ];
        foreach ($values as $name => $value) {
            if (!isset($record->$name)) {
                $record->$name = $value;
            }
        }
        // The checkbox groups as the form sends them: option => 1 when ticked.
        if (!isset($record->taxonomylevels)) {
            $record->taxonomylevels = array_fill_keys($defaults->taxonomylevels, 1);
        }
        foreach (didactics::GRADES as $grade) {
            $name = 'qtypes' . $grade;
            if (!isset($record->$name)) {
                $record->$name = array_fill_keys($defaults->questiontypes[$grade] ?? [], 1);
            }
        }
        return parent::create_instance($record, (array) $options);
    }
}
