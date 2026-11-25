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
use core_external\external_value;
use mod_tarsuscoach\local\page\coach;

/**
 * The learner's feed in the coach: answers, questions and learning units, newest first.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_feed extends base {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module of the activity'),
            'offset' => new external_value(PARAM_INT, 'How many cards the browser shows already', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * The learner's feed in the coach: answers, questions and learning units, newest first.
     *
     * @param int $cmid The course module of the activity.
     * @param int $offset How many cards the browser shows already.
     * @return array The reply, JSON encoded.
     */
    public static function execute(int $cmid, int $offset = 0): array {
        [
            'cmid' => $cmid,
            'offset' => $offset,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'offset' => $offset,
        ]);
        [$tarsus, $settings] = self::activity($cmid, false);
        return self::respond('feed', static fn(): array => coach::learner_api($tarsus, $settings)->feed($offset));
    }
}
