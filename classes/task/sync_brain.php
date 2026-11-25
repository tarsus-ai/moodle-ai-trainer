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

namespace mod_tarsuscoach\task;

use mod_tarsuscoach\local\cron\sweep;

/**
 * Keeps the brain in step with the course material: re-sends what was
 * edited, withdraws what was deleted, confirms what the brain finished
 * reading, and fetches the month's AI credit figure.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_brain extends \core\task\scheduled_task {
    /**
     * The task's name in Site administration > Server > Scheduled tasks.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_sync', 'mod_tarsuscoach');
    }

    /**
     * Run the sweep.
     */
    public function execute() {
        $outcome = sweep::run();
        mtrace($outcome['message']);
    }
}
