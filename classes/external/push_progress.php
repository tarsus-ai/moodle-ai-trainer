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
use mod_tarsuscoach\local\activity\scoring;

/**
 * The grade and completion a request that waited for Tarsus could not write.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class push_progress extends base {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module of the activity'),
        ]);
    }

    /**
     * The grade and completion a request that waited for Tarsus could not write.
     *
     * @param int $cmid The course module of the activity.
     * @return array The reply, JSON encoded.
     */
    public static function execute(int $cmid): array {
        [
            'cmid' => $cmid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
        ]);
        [$tarsus, $settings] = self::activity($cmid, false, false);
        return self::respond('progress', static function () use ($tarsus): array {
            global $USER;
            scoring::push_learning_progress((int) $tarsus->id, (int) $USER->id);
            return ['status' => 'success', 'error' => ''];
        });
    }
}
