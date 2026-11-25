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
 * The Tarsus activities of a course.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$PAGE->set_url('/mod/tarsuscoach/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . get_string('modulenameplural', 'mod_tarsuscoach'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');
$PAGE->navbar->add(get_string('modulenameplural', 'mod_tarsuscoach'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_tarsuscoach'));

$activities = get_all_instances_in_course('tarsuscoach', $course);
if (!$activities) {
    notice(get_string('nonewmodules', 'mod_tarsuscoach'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->head = [get_string('name', 'mod_tarsuscoach'), get_string('moduleintro')];
foreach ($activities as $activity) {
    $link = html_writer::link(
        new moodle_url('/mod/tarsuscoach/view.php', ['id' => $activity->coursemodule]),
        format_string($activity->name),
        $activity->visible ? [] : ['class' => 'dimmed']
    );
    $table->data[] = [$link, format_module_intro('tarsuscoach', $activity, $activity->coursemodule)];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
