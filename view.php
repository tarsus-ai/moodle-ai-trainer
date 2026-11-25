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
 * The coach: the learner's chat with the four modes, and for teachers the
 * settings, evaluation and history panels sliding in from the right.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_tarsuscoach\local\activity\scope;
use mod_tarsuscoach\local\activity\settings;
use mod_tarsuscoach\local\output\views;
use mod_tarsuscoach\local\page\coach;
use mod_tarsuscoach\local\support\budget;
use mod_tarsuscoach\local\support\help;
use mod_tarsuscoach\local\support\platform;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('tarsuscoach', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$tarsus = $DB->get_record('tarsuscoach', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/tarsuscoach:view', $context);

$url = new moodle_url('/mod/tarsuscoach/view.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($tarsus->name));
$PAGE->set_heading(format_string($course->fullname));

$teacher = has_capability('mod/tarsuscoach:manage', $context);
$settings = settings::load((int) $tarsus->id);

// Reset chat history: confirm first, then erase.
if ($action === 'reset') {
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('resetchat_confirm', 'mod_tarsuscoach', format_string($tarsus->name)),
        new moodle_url($url, ['action' => 'doreset', 'sesskey' => sesskey()]),
        $url
    );
    echo $OUTPUT->footer();
    exit;
}
if ($action === 'doreset') {
    require_sesskey();
    $result = coach::learner_api($tarsus, $settings)->reset();
    if (($result['status'] ?? '') === 'success') {
        redirect($url, get_string('resetchat_done', 'mod_tarsuscoach'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect(
        $url,
        get_string('resetchat_failed', 'mod_tarsuscoach') . ' ' . s((string) ($result['error'] ?? '')),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

tarsuscoach_view($tarsus, $course, $cm, $context);

$version = platform::plugin_version();
// Tarsus's texts are markdown with KaTeX formulas: the coach renders them
// like the Tarsus app, with KaTeX's stylesheet and the bundled renderer
// (mod_tarsuscoach/markdown-lazy).
$PAGE->requires->css(new moodle_url('/mod/tarsuscoach/assets/vendor/katex/katex.min.css', ['v' => $version]));

echo $OUTPUT->header();

if (isguestuser()) {
    // A guest account is shared: the coach keeps one conversation per person.
    echo $OUTPUT->notification(get_string('noguestaccess', 'mod_tarsuscoach'), 'info');
    echo $OUTPUT->footer();
    exit;
}

if (!$cm->visible && $teacher) {
    echo $OUTPUT->notification(get_string('offline_notice', 'mod_tarsuscoach'), 'info');
}

// AI credits: teachers see how much of the month is used, learners when it is spent.
if ($teacher) {
    $banner = budget::banner();
    if ($banner !== null) {
        echo $OUTPUT->notification(
            $banner['text'],
            $banner['type'] === 'failure' ? 'error' : ($banner['type'] === 'question' ? 'warning' : $banner['type'])
        );
    }
} else if (budget::exhausted()) {
    echo $OUTPUT->notification(get_string('budget_exhausted_learner', 'mod_tarsuscoach'), 'info');
}

$modes = $settings->available_modes();
if ($modes === []) {
    echo $OUTPUT->notification(
        get_string($settings->modes() === [] ? 'no_modes_notice' : 'no_modes_plan_notice', 'mod_tarsuscoach'),
        'info'
    );
    echo $OUTPUT->footer();
    exit;
}

if ($teacher && scope::allowed_resource_ids((int) $tarsus->id, (int) $course->id) === []) {
    $materials = (new moodle_url('/mod/tarsuscoach/materials.php', ['id' => $cm->id]))->out(false);
    echo $OUTPUT->notification(get_string('noscope_teacher', 'mod_tarsuscoach', $materials), 'info');
}

echo html_writer::div(
    $OUTPUT->single_button(new moodle_url($url, ['action' => 'reset']), get_string('resetchat', 'mod_tarsuscoach'), 'get'),
    'tarsus-toolbar mb-3'
);

echo views::chat(
    'tarsus-chat-' . $tarsus->id,
    coach::config($cm, $settings, $teacher),
    views::help(help::links(), 'tarsus-help-chat')
);
$PAGE->requires->js_call_amd('mod_tarsuscoach/coach-lazy', 'init');

echo $OUTPUT->footer();
