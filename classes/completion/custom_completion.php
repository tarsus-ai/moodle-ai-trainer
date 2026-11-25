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

namespace mod_tarsuscoach\completion;

use core_completion\activity_custom_completion;
use mod_tarsuscoach\local\activity\scoring;
use mod_tarsuscoach\local\activity\settings;

/**
 * The activity's completion rule: the learner reaches a progress.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Whether the learner reached the progress of the rule.
     *
     * @param string $rule The rule.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    public function get_state(string $rule): int {
        $this->validate_rule($rule);
        $settings = settings::load((int) $this->cm->instance);
        $row = scoring::row((int) $this->cm->instance, $this->userid);
        return scoring::lp_status($settings, $row) === scoring::LP_COMPLETED ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * The rules this activity defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionprogress'];
    }

    /**
     * What the rules mean, for the learner's completion list.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $progress = (int) ($this->cm->customdata['customcompletionrules']['completionprogress'] ?? 0);
        return ['completionprogress' => get_string('completionprogress_desc', 'mod_tarsuscoach', $progress)];
    }

    /**
     * The order of the conditions shown.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return ['completionview', 'completionprogress', 'completionusegrade', 'completionpassgrade'];
    }
}
