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
 * The evaluation as a CSV file: every learner's figures, result and status.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_tarsuscoach\local\activity\learners;
use mod_tarsuscoach\local\activity\scoring;
use mod_tarsuscoach\local\activity\settings;
use mod_tarsuscoach\local\support\platform;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('tarsuscoach', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$tarsus = $DB->get_record('tarsuscoach', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/tarsuscoach:manage', $context);

$settings = settings::load((int) $tarsus->id);
$overview = (new learners((int) $tarsus->id, (int) $course->id, $settings))->overview();
$t = static fn(string $key): string => get_string($key, 'mod_tarsuscoach');

$lines = [[
    get_string('lastname'), get_string('firstname'), get_string('username'),
    $t('col_progress'), $t('xp'), $t('xp') . ' ' . $t('total'),
    $t('learning_units'), $t('learning_units') . ' ' . $t('total'),
    $t('col_score'), $t('col_lp_status'), $t('col_last_activity'),
]];
foreach ($overview['learners'] as $learner) {
    $status = $learner['status'] === scoring::LP_COMPLETED ? 'lp_completed'
        : ($learner['status'] === scoring::LP_IN_PROGRESS ? 'lp_in_progress' : 'lp_not_attempted');
    $lines[] = [
        $learner['lastname'], $learner['firstname'], $learner['login'],
        number_format((float) $learner['progress'], 2, '.', ''),
        number_format((float) $learner['earnedxp'], 2, '.', ''), number_format((float) $learner['totalxp'], 2, '.', ''),
        number_format((float) $learner['earnedunits'], 2, '.', ''), number_format((float) $learner['totalunits'], 2, '.', ''),
        $learner['grade'] === null ? '' : number_format((float) $learner['grade'], 2, '.', ''),
        $t($status),
        $learner['lastactivity'] > 0 ? date('Y-m-d H:i', (int) $learner['lastactivity']) : '',
    ];
}

// UTF-8 with a byte order mark and semicolons: opens as columns in Excel.
$csv = "\xEF\xBB\xBF";
foreach ($lines as $line) {
    $csv .= implode(';', array_map(static function ($value): string {
        $value = (string) $value;
        return '"' . str_replace('"', '""', $value) . '"';
    }, $line)) . "\r\n";
}

platform::send_download('tarsus_' . clean_filename(format_string($tarsus->name)) . '_' . date('Ymd') . '.csv', $csv, 'text/csv; charset=utf-8');
