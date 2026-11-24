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
 * @package    mod_tarsus
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_tarsus\local\Activity\Scope;
use mod_tarsus\local\Activity\Settings;
use mod_tarsus\local\Output\Views;
use mod_tarsus\local\Page\Coach;
use mod_tarsus\local\Support\Budget;
use mod_tarsus\local\Support\Help;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('tarsus', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$tarsus = $DB->get_record('tarsus', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/tarsus:view', $context);

$url = new moodle_url('/mod/tarsus/view.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($tarsus->name));
$PAGE->set_heading(format_string($course->fullname));

$teacher = has_capability('mod/tarsus:manage', $context);
$settings = Settings::load((int) $tarsus->id);

// Reset chat history: confirm first, then erase.
if ($action === 'reset') {
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('resetchat_confirm', 'mod_tarsus', format_string($tarsus->name)),
        new moodle_url($url, ['action' => 'doreset', 'sesskey' => sesskey()]),
        $url
    );
    echo $OUTPUT->footer();
    exit;
}
if ($action === 'doreset') {
    require_sesskey();
    $result = Coach::learnerApi($tarsus, $settings)->reset();
    if (($result['status'] ?? '') === 'success') {
        redirect($url, get_string('resetchat_done', 'mod_tarsus'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect(
        $url,
        get_string('resetchat_failed', 'mod_tarsus') . ' ' . s((string) ($result['error'] ?? '')),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

tarsus_view($tarsus, $course, $cm, $context);

$version = \mod_tarsus\local\Support\Platform::pluginVersion();
// Tarsus's texts are Markdown with KaTeX formulas: the coach renders them
// like the Tarsus app, with KaTeX's stylesheet and the bundled renderer.
$PAGE->requires->css(new moodle_url('/mod/tarsus/assets/vendor/katex/katex.min.css', ['v' => $version]));
$PAGE->requires->css(new moodle_url('/mod/tarsus/assets/css/tarsus.css', ['v' => $version]));
$PAGE->requires->js(new moodle_url('/mod/tarsus/assets/js/vendor/tarsus-markdown.min.js', ['v' => $version]));
$PAGE->requires->js(new moodle_url('/mod/tarsus/assets/js/tarsus.js', ['v' => $version]));

echo $OUTPUT->header();

if (isguestuser()) {
    // A guest account is shared: the coach keeps one conversation per person.
    echo $OUTPUT->notification(get_string('noguestaccess', 'mod_tarsus'), 'info');
    echo $OUTPUT->footer();
    exit;
}

if (!$cm->visible && $teacher) {
    echo $OUTPUT->notification(get_string('offline_notice', 'mod_tarsus'), 'info');
}

// AI credits: teachers see how much of the month is used, learners when it is spent.
if ($teacher) {
    $banner = Budget::banner();
    if ($banner !== null) {
        echo $OUTPUT->notification($banner['text'], $banner['type'] === 'failure' ? 'error' : ($banner['type'] === 'question' ? 'warning' : $banner['type']));
    }
} else if (Budget::exhausted()) {
    echo $OUTPUT->notification(get_string('budget_exhausted_learner', 'mod_tarsus'), 'info');
}

$modes = $settings->availableModes();
if ($modes === []) {
    echo $OUTPUT->notification(get_string($settings->modes() === [] ? 'no_modes_notice' : 'no_modes_plan_notice', 'mod_tarsus'), 'info');
    echo $OUTPUT->footer();
    exit;
}

if ($teacher && Scope::allowedResourceIds((int) $tarsus->id, (int) $course->id) === []) {
    $materials = (new moodle_url('/mod/tarsus/materials.php', ['id' => $cm->id]))->out(false);
    echo $OUTPUT->notification(get_string('noscope_teacher', 'mod_tarsus', $materials), 'info');
}

echo html_writer::div(
    $OUTPUT->single_button(new moodle_url($url, ['action' => 'reset']), get_string('resetchat', 'mod_tarsus'), 'get'),
    'tarsus-toolbar mb-3'
);

echo Views::chat(
    'tarsus-chat-' . $tarsus->id,
    Coach::config($cm, $settings, $teacher),
    Views::help(Help::links(), 'tarsus-help-chat')
);

echo $OUTPUT->footer();
