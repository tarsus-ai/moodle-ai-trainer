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
 * The requests of the coach and of the materials list. Every request
 * carries the session key; the teacher's requests need mod/tarsuscoach:manage.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_tarsuscoach\local\activity\scoring;
use mod_tarsuscoach\local\activity\settings;
use mod_tarsuscoach\local\page\coach;
use mod_tarsuscoach\local\service\settings_api;
use mod_tarsuscoach\local\support\platform;

$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

$cm = get_coursemodule_from_id('tarsuscoach', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$tarsus = $DB->get_record('tarsuscoach', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm, false, true);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('mod/tarsuscoach:view', $context);

if (isguestuser()) {
    platform::send_json(['status' => 'error', 'ok' => false, 'error' => get_string('noguestaccess', 'mod_tarsuscoach')], 403);
}
$isteacher = in_array($action, coach::TEACHER_ACTIONS, true);
if (!$isteacher && !in_array($action, coach::LEARNER_ACTIONS, true)) {
    platform::send_json(['status' => 'error', 'ok' => false, 'error' => get_string('invalid_request', 'mod_tarsuscoach')], 400);
}
if ($isteacher && !has_capability('mod/tarsuscoach:manage', $context)) {
    platform::send_json(['status' => 'error', 'ok' => false, 'error' => get_string('nopermissions', 'error', '')], 403);
}

// Requests to Tarsus take a while: free the session so the learner can go
// on working in other tabs meanwhile. Moodle keeps some course caches in
// the session, so the learner's grade and completion are then written by
// a quick follow-up request ("progress") that keeps its session.
if (!in_array($action, ['progress', 'settings', 'saveSettings'], true)) {
    \core\session\manager::write_close();
    scoring::defer_pushes();
    platform::complete_json(static function (array $data): array {
        if (scoring::pushes_pending()) {
            $data['progressSync'] = true;
        }
        return $data;
    });
}

$settings = settings::load((int) $tarsus->id);
$payload = platform::json_payload();

try {
    switch ($action) {
        // Learner.
        case 'feed':
            platform::send_json(coach::learner_api($tarsus, $settings)->feed((int) ($payload['offset'] ?? 0)));
            break;
        case 'ask':
            platform::send_json(coach::learner_api($tarsus, $settings)->ask(
                (string) ($payload['query'] ?? ''),
                (string) ($payload['replyto'] ?? ''),
                (string) ($payload['replystack'] ?? '')
            ));
            break;
        case 'askVoice':
            // A spoken question: the recording arrives as the uploaded file
            // "audio", the reply target in the form field "payload".
            $file = $_FILES['audio'] ?? null;
            $error = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                platform::send_json(['status' => 'error', 'error' => get_string('err_toolarge', 'mod_tarsuscoach')]);
            }
            $path = is_array($file) ? (string) ($file['tmp_name'] ?? '') : '';
            if ($error !== UPLOAD_ERR_OK || $path === '' || !is_uploaded_file($path)) {
                platform::send_json(['status' => 'error', 'error' => get_string('err_audio_format', 'mod_tarsuscoach')]);
            }
            // The browser's type without its parameters (audio/webm;codecs=opus).
            $mimetype = strtolower(trim(explode(';', (string) ($file['type'] ?? ''))[0]));
            if (strncmp($mimetype, 'audio/', 6) !== 0 && strncmp($mimetype, 'video/', 6) !== 0) {
                $mimetype = 'application/octet-stream';
            }
            $name = clean_param(basename((string) ($file['name'] ?? 'question.webm')), PARAM_FILE);
            platform::send_json(coach::learner_api($tarsus, $settings)->ask_voice(
                $path,
                $mimetype,
                $name !== '' ? $name : 'question.webm',
                (string) ($payload['replyto'] ?? ''),
                (string) ($payload['replystack'] ?? '')
            ));
            break;
        case 'practiceNext':
            platform::send_json(coach::learner_api($tarsus, $settings)->practice_next());
            break;
        case 'practiceCheck':
            platform::send_json(coach::learner_api($tarsus, $settings)->practice_check($payload));
            break;
        case 'learnNext':
            platform::send_json(coach::learner_api($tarsus, $settings)->learn_next());
            break;
        case 'audio':
            platform::send_json(coach::learner_api($tarsus, $settings)->stack_audio((string) ($payload['stackid'] ?? '')));
            break;
        case 'discuss':
            platform::send_json(coach::learner_api($tarsus, $settings)->discuss());
            break;
        case 'progress':
            // The grade and completion a request with a closed session left.
            scoring::push_learning_progress((int) $tarsus->id, (int) $USER->id);
            platform::send_json(['status' => 'success', 'error' => '']);
            break;
        case 'references':
            platform::send_json(coach::learner_api($tarsus, $settings)->references(
                (string) ($payload['widgetid'] ?? ''),
                (string) ($payload['state'] ?? 'ask')
            ));
            break;

        // Teacher: the panels.
        case 'stats':
            platform::send_json(coach::teacher_api($tarsus, $settings)->statistics());
            break;
        case 'history':
            platform::send_json(coach::teacher_api($tarsus, $settings)->history(
                (int) ($payload['userid'] ?? 0),
                (int) ($payload['offset'] ?? 0)
            ));
            break;
        case 'analytics':
            platform::send_json(coach::teacher_api($tarsus, $settings)->analytics((int) ($payload['userid'] ?? 0)));
            break;
        case 'settings':
            platform::send_json(['status' => 'success', 'error' => ''] + (new settings_api($settings))->describe());
            break;
        case 'saveSettings':
            $settings->objid = (int) $tarsus->id;
            platform::send_json((new settings_api($settings))->save((array) ($payload['values'] ?? [])));
            break;

        // Teacher: the materials list.
        case 'action':
            platform::send_json(coach::manager_api($tarsus)->action(
                (string) ($payload['resourceid'] ?? ''),
                (string) ($payload['action'] ?? '')
            ));
            break;
        case 'include':
            platform::send_json(coach::manager_api($tarsus)->set_included(
                (string) ($payload['resourceid'] ?? ''),
                !empty($payload['included'])
            ));
            break;
        case 'sync':
            $ids = array_values(array_filter(array_map('strval', (array) ($payload['resourceids'] ?? [])), 'strlen'));
            platform::send_json(coach::manager_api($tarsus)->sync($ids));
            break;
    }
} catch (\Throwable $e) {
    platform::log($action . ' failed: ' . $e->getMessage(), 'error');
    platform::send_json(['status' => 'error', 'ok' => false, 'error' => get_string('unexpected_error', 'mod_tarsuscoach')], 500);
}
