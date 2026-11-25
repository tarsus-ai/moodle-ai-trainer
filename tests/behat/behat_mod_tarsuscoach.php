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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * The pages of the Tarsus coach for Behat:
 * - "mod_tarsuscoach > Plan", "> Brains", "> Deletions" and "> Status", the administration's tabs;
 * - "<activity name>" "mod_tarsuscoach > View" and "> Materials", an activity's coach and material list.
 *
 * @package    mod_tarsuscoach
 * @category   test
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_tarsuscoach extends behat_base {
    /**
     * The address of an administration tab.
     *
     * @param string $page The tab: Plan, Brains, Deletions or Status.
     * @return moodle_url
     */
    protected function resolve_page_url(string $page): moodle_url {
        $tab = strtolower(trim($page));
        if (!in_array($tab, ['plan', 'brains', 'deletions', 'status'], true)) {
            throw new Exception('Unrecognised mod_tarsuscoach page "' . $page . '".');
        }
        return new moodle_url('/mod/tarsuscoach/admin.php', ['tab' => $tab]);
    }

    /**
     * The address of a page of an activity.
     *
     * @param string $type The page: View or Materials.
     * @param string $identifier The activity's name.
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        $cm = $this->get_cm_by_activity_name('tarsuscoach', $identifier);
        switch (strtolower(trim($type))) {
            case 'view':
                return new moodle_url('/mod/tarsuscoach/view.php', ['id' => $cm->id]);
            case 'materials':
                return new moodle_url('/mod/tarsuscoach/materials.php', ['id' => $cm->id]);
        }
        throw new Exception('Unrecognised mod_tarsuscoach page type "' . $type . '".');
    }
}
