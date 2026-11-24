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
 * carries the session key; the teacher's requests need mod/tarsus:manage.
 *
 * @package    mod_tarsus
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_tarsus\local\Activity\Scoring;
use mod_tarsus\local\Activity\Settings;
use mod_tarsus\local\Page\Coach;
use mod_tarsus\local\Service\SettingsApi;
use mod_tarsus\local\Support\Platform;

$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

$cm = get_coursemodule_from_id('tarsus', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$tarsus = $DB->get_record('tarsus', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm, false, true);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('mod/tarsus:view', $context);

if (isguestuser()) {
    Platform::sendJson(['status' => 'error', 'ok' => false, 'error' => get_string('noguestaccess', 'mod_tarsus')], 403);
}
$isteacher = in_array($action, Coach::TEACHER_ACTIONS, true);
if (!$isteacher && !in_array($action, Coach::LEARNER_ACTIONS, true)) {
    Platform::sendJson(['status' => 'error', 'ok' => false, 'error' => get_string('invalid_request', 'mod_tarsus')], 400);
}
if ($isteacher && !has_capability('mod/tarsus:manage', $context)) {
    Platform::sendJson(['status' => 'error', 'ok' => false, 'error' => get_string('nopermissions', 'error', '')], 403);
}

// Requests to Tarsus take a while: free the session so the learner can go
// on working in other tabs meanwhile. Moodle keeps some course caches in
// the session, so the learner's grade and completion are then written by
// a quick follow-up request ("progress") that keeps its session.
if (!in_array($action, ['progress', 'settings', 'saveSettings'], true)) {
    \core\session\manager::write_close();
    Scoring::deferPushes();
    Platform::completeJson(static function (array $data): array {
        if (Scoring::pushesPending()) {
            $data['progressSync'] = true;
        }
        return $data;
    });
}

$settings = Settings::load((int) $tarsus->id);
$payload = Platform::jsonPayload();

try {
    switch ($action) {
        // Learner.
        case 'feed':
            Platform::sendJson(Coach::learnerApi($tarsus, $settings)->feed((int) ($payload['offset'] ?? 0)));
            break;
        case 'ask':
            Platform::sendJson(Coach::learnerApi($tarsus, $settings)->ask(
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
                Platform::sendJson(['status' => 'error', 'error' => get_string('err_toolarge', 'mod_tarsus')]);
            }
            $path = is_array($file) ? (string) ($file['tmp_name'] ?? '') : '';
            if ($error !== UPLOAD_ERR_OK || $path === '' || !is_uploaded_file($path)) {
                Platform::sendJson(['status' => 'error', 'error' => get_string('err_audio_format', 'mod_tarsus')]);
            }
            // The browser's type without its parameters (audio/webm;codecs=opus).
            $mimetype = strtolower(trim(explode(';', (string) ($file['type'] ?? ''))[0]));
            if (strncmp($mimetype, 'audio/', 6) !== 0 && strncmp($mimetype, 'video/', 6) !== 0) {
                $mimetype = 'application/octet-stream';
            }
            $name = clean_param(basename((string) ($file['name'] ?? 'question.webm')), PARAM_FILE);
            Platform::sendJson(Coach::learnerApi($tarsus, $settings)->askVoice(
                $path,
                $mimetype,
                $name !== '' ? $name : 'question.webm',
                (string) ($payload['replyto'] ?? ''),
                (string) ($payload['replystack'] ?? '')
            ));
            break;
        case 'practiceNext':
            Platform::sendJson(Coach::learnerApi($tarsus, $settings)->practiceNext());
            break;
        case 'practiceCheck':
            Platform::sendJson(Coach::learnerApi($tarsus, $settings)->practiceCheck($payload));
            break;
        case 'learnNext':
            Platform::sendJson(Coach::learnerApi($tarsus, $settings)->learnNext());
            break;
        case 'audio':
            Platform::sendJson(Coach::learnerApi($tarsus, $settings)->stackAudio((string) ($payload['stackid'] ?? '')));
            break;
        case 'discuss':
            Platform::sendJson(Coach::learnerApi($tarsus, $settings)->discuss());
            break;
        case 'progress':
            // The grade and completion a request with a closed session left.
            Scoring::pushLearningProgress((int) $tarsus->id, (int) $USER->id);
            Platform::sendJson(['status' => 'success', 'error' => '']);
            break;
        case 'references':
            Platform::sendJson(Coach::learnerApi($tarsus, $settings)->references(
                (string) ($payload['widgetid'] ?? ''),
                (string) ($payload['state'] ?? 'ask')
            ));
            break;

        // Teacher: the panels.
        case 'stats':
            Platform::sendJson(Coach::teacherApi($tarsus, $settings)->statistics());
            break;
        case 'history':
            Platform::sendJson(Coach::teacherApi($tarsus, $settings)->history(
                (int) ($payload['userid'] ?? 0),
                (int) ($payload['offset'] ?? 0)
            ));
            break;
        case 'analytics':
            Platform::sendJson(Coach::teacherApi($tarsus, $settings)->analytics((int) ($payload['userid'] ?? 0)));
            break;
        case 'settings':
            Platform::sendJson(['status' => 'success', 'error' => ''] + (new SettingsApi($settings))->describe());
            break;
        case 'saveSettings':
            $settings->objid = (int) $tarsus->id;
            Platform::sendJson((new SettingsApi($settings))->save((array) ($payload['values'] ?? [])));
            break;

        // Teacher: the materials list.
        case 'action':
            Platform::sendJson(Coach::managerApi($tarsus)->action(
                (string) ($payload['resourceid'] ?? ''),
                (string) ($payload['action'] ?? '')
            ));
            break;
        case 'include':
            Platform::sendJson(Coach::managerApi($tarsus)->setIncluded(
                (string) ($payload['resourceid'] ?? ''),
                !empty($payload['included'])
            ));
            break;
        case 'sync':
            $ids = array_values(array_filter(array_map('strval', (array) ($payload['resourceids'] ?? [])), 'strlen'));
            Platform::sendJson(Coach::managerApi($tarsus)->sync($ids));
            break;
    }
} catch (\Throwable $e) {
    Platform::log($action . ' failed: ' . $e->getMessage(), 'error');
    Platform::sendJson(['status' => 'error', 'ok' => false, 'error' => get_string('unexpected_error', 'mod_tarsus')], 500);
}
