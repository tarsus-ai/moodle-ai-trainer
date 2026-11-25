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
use mod_tarsuscoach\local\page\coach;
use mod_tarsuscoach\local\support\platform;

/**
 * A spoken question: the recording is turned into text and answered like a typed one.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ask_voice extends base {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module of the activity'),
            'audio' => new external_value(PARAM_RAW, 'The recording, base64 encoded'),
            'mimetype' => new external_value(PARAM_RAW, 'The type of the recording, as the browser names it', VALUE_DEFAULT, ''),
            'filename' => new external_value(PARAM_RAW, 'The file name of the recording', VALUE_DEFAULT, 'question.webm'),
            'replyto' => new external_value(PARAM_RAW, 'The answer the question follows up, if any', VALUE_DEFAULT, ''),
            'replystack' => new external_value(PARAM_RAW, 'The learning unit the question is about, if any', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * A spoken question: the recording is turned into text and answered like a typed one.
     *
     * @param int $cmid The course module of the activity.
     * @param string $audio The recording, base64 encoded.
     * @param string $mimetype The type of the recording, as the browser names it.
     * @param string $filename The file name of the recording.
     * @param string $replyto The answer the question follows up, if any.
     * @param string $replystack The learning unit the question is about, if any.
     * @return array The reply, JSON encoded.
     */
    public static function execute(
        int $cmid,
        string $audio,
        string $mimetype = '',
        string $filename = 'question.webm',
        string $replyto = '',
        string $replystack = ''
    ): array {
        [
            'cmid' => $cmid,
            'audio' => $audio,
            'mimetype' => $mimetype,
            'filename' => $filename,
            'replyto' => $replyto,
            'replystack' => $replystack,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'audio' => $audio,
            'mimetype' => $mimetype,
            'filename' => $filename,
            'replyto' => $replyto,
            'replystack' => $replystack,
        ]);
        [$tarsus, $settings] = self::activity($cmid, false);
        $question = static function () use ($tarsus, $settings, $audio, $mimetype, $filename, $replyto, $replystack): array {
            $bytes = base64_decode($audio, true);
            if ($bytes === false || $bytes === '') {
                return ['status' => 'error', 'error' => get_string('err_audio_format', 'mod_tarsuscoach')];
            }
            $limit = platform::voice_limit();
            if ($limit > 0 && strlen($bytes) > $limit) {
                return ['status' => 'error', 'error' => get_string('err_toolarge', 'mod_tarsuscoach')];
            }
            // The browser's type without its parameters (audio/webm;codecs=opus).
            $type = strtolower(trim(explode(';', $mimetype)[0]));
            if (strncmp($type, 'audio/', 6) !== 0 && strncmp($type, 'video/', 6) !== 0) {
                $type = 'application/octet-stream';
            }
            $name = clean_param(basename($filename), PARAM_FILE);
            $path = make_request_directory() . '/question';
            file_put_contents($path, $bytes);
            return coach::learner_api($tarsus, $settings)->ask_voice(
                $path,
                $type,
                $name !== '' ? $name : 'question.webm',
                $replyto,
                $replystack
            );
        };
        return self::respond('askVoice', $question);
    }
}
