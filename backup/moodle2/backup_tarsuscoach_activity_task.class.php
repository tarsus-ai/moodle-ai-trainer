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
 * Backup of a Tarsus activity: its settings and the course material it
 * may use. The learners' conversations live in the brain, under this
 * activity, and do not travel with a backup.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/tarsuscoach/backup/moodle2/backup_tarsuscoach_stepslib.php');

/**
 * The backup task of a Tarsus activity.
 */
class backup_tarsuscoach_activity_task extends backup_activity_task {
    /**
     * No settings of its own.
     */
    protected function define_my_settings() {
    }

    /**
     * One step: the activity and its material selection.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_tarsuscoach_activity_structure_step('tarsus_structure', 'tarsus.xml'));
    }

    /**
     * Links to Tarsus activities in texts, as markers the restore turns back
     * into links to the restored activities.
     *
     * @param string $content Text that may contain links.
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;
        $content = self::encode_links((string) $content, $CFG->wwwroot . '/mod/tarsuscoach/index.php?id=', 'TARSUSCOACHINDEX');
        return self::encode_links($content, $CFG->wwwroot . '/mod/tarsuscoach/view.php?id=', 'TARSUSCOACHVIEWBYID');
    }

    /**
     * Every "<prefix><number>" in a text as "$@<marker>*<number>@$".
     *
     * @param string $content The text.
     * @param string $prefix The address up to the number.
     * @param string $marker The marker's name.
     * @return string
     */
    private static function encode_links(string $content, string $prefix, string $marker): string {
        $out = '';
        $offset = 0;
        $length = strlen($content);
        while (($at = strpos($content, $prefix, $offset)) !== false) {
            $start = $at + strlen($prefix);
            $end = $start;
            while ($end < $length && ctype_digit($content[$end])) {
                $end++;
            }
            if ($end === $start) {
                // No number after the address: leave it as it is.
                $out .= substr($content, $offset, $start - $offset);
                $offset = $start;
                continue;
            }
            $out .= substr($content, $offset, $at - $offset) . '$@' . $marker . '*' . substr($content, $start, $end - $start) . '@$';
            $offset = $end;
        }
        return $out . substr($content, $offset);
    }
}
