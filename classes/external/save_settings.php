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
use mod_tarsuscoach\local\service\settings_api;

/**
 * The settings the teacher changed in the panel.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_settings extends base {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module of the activity'),
            'values' => new external_value(PARAM_RAW, 'The settings by name, JSON encoded'),
        ]);
    }

    /**
     * The settings the teacher changed in the panel.
     *
     * @param int $cmid The course module of the activity.
     * @param string $values The settings by name, JSON encoded.
     * @return array The reply, JSON encoded.
     */
    public static function execute(int $cmid, string $values): array {
        [
            'cmid' => $cmid,
            'values' => $values,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'values' => $values,
        ]);
        [$tarsus, $settings] = self::activity($cmid, true, false);
        return self::respond('saveSettings', static function () use ($tarsus, $settings, $values): array {
            $settings->objid = (int) $tarsus->id;
            $decoded = json_decode($values, true);
            return (new settings_api($settings))->save(is_array($decoded) ? $decoded : []);
        });
    }
}
