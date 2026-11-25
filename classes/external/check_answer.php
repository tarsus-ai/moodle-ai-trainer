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

namespace mod_tarsuscoach\external;

use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_tarsuscoach\local\page\coach;

/**
 * The learner's answer to a question, evaluated by the brain.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_answer extends base {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module of the activity'),
            'widgetid' => new external_value(PARAM_RAW, 'The question'),
            'widgettype' => new external_value(PARAM_RAW, 'The kind of question'),
            'state' => new external_value(
                PARAM_ALPHA,
                'practice (Test knowledge) or learn (a learning unit)',
                VALUE_DEFAULT,
                'practice'
            ),
            'answer' => new external_value(PARAM_RAW, 'The chosen or typed answer', VALUE_DEFAULT, ''),
            'answers' => new external_multiple_structure(new external_single_structure([
                'variable' => new external_value(PARAM_RAW, 'A variable of the question'),
                'answer' => new external_value(PARAM_RAW, 'Its value'),
            ]), 'The values of the variables', VALUE_DEFAULT, []),
            'table' => new external_multiple_structure(
                new external_multiple_structure(new external_value(PARAM_RAW, 'A cell')),
                'The rows of the table',
                VALUE_DEFAULT,
                []
            ),
            'columns' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'A column'),
                'The columns of the table',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * The learner's answer to a question, evaluated by the brain.
     *
     * @param int $cmid The course module of the activity.
     * @param string $widgetid The question.
     * @param string $widgettype The kind of question.
     * @param string $state practice (Test knowledge) or learn (a learning unit).
     * @param string $answer The chosen or typed answer.
     * @param array $answers The values of the variables.
     * @param array $table The rows of the table.
     * @param array $columns The columns of the table.
     * @return array The reply, JSON encoded.
     */
    public static function execute(
        int $cmid,
        string $widgetid,
        string $widgettype,
        string $state = 'practice',
        string $answer = '',
        array $answers = [],
        array $table = [],
        array $columns = []
    ): array {
        [
            'cmid' => $cmid,
            'widgetid' => $widgetid,
            'widgettype' => $widgettype,
            'state' => $state,
            'answer' => $answer,
            'answers' => $answers,
            'table' => $table,
            'columns' => $columns,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'widgetid' => $widgetid,
            'widgettype' => $widgettype,
            'state' => $state,
            'answer' => $answer,
            'answers' => $answers,
            'table' => $table,
            'columns' => $columns,
        ]);
        [$tarsus, $settings] = self::activity($cmid, false);
        return self::respond(
            'practiceCheck',
            static fn(): array => coach::learner_api($tarsus, $settings)->practice_check([
                'widgetid' => $widgetid, 'widgettype' => $widgettype, 'state' => $state, 'answer' => $answer,
                'answers' => $answers, 'table' => $table, 'columns' => $columns,
            ])
        );
    }
}
