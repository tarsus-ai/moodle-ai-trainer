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

namespace mod_tarsuscoach\local\service;

use mod_tarsuscoach\local\client\transport_exception;
use mod_tarsuscoach\local\support\platform;

/**
 * One clear message for every way a call to Tarsus can fail, in the
 * user's language. Tarsus explains a refusal in an upper-case sentence
 * (the whole body of a 400); the known sentences get their own message,
 * everything else a message by status. The details always go to the ILIAS
 * log, never the request (it carries the API key).
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class messages
{
    /** Tarsus's sentences that mean the connection itself is wrong (key, brain). */
    private const ACCESS = [
        'UNKNOWN OR INACTIVE API KEY',
        'UNAVAILABLE API KEY',
        'UNAVAILABLE BRAIN ID',
        'BRAIN IS EITHER INEXISTENT',
        'BRAIN MEMORY DOES NOT EXIST',
    ];

    /**
     * The user's message for a failed call to Tarsus. The details go to the
     * log.
     *
     * @param transport_exception $e The failure.
     * @param string $context What was asked for: ask, voice, practice, check, learn, audio, discuss, references, statistics.
     * @return string
     */
    public static function for_transport(transport_exception $e, string $context = ''): string {
        platform::log($context . ': ' . $e->getMessage() . ($e->body !== '' ? ' - ' . $e->body : ''));

        if ($e->httpstatus === 0) {
            return platform::txt('err_connection');
        }
        foreach (self::ACCESS as $sentence) {
            if ($e->says($sentence)) {
                return platform::txt('err_access');
            }
        }
        if ($e->says('NO BITS WERE FOUND')) {
            return platform::txt('err_notindexed');
        }
        if ($e->says('NO SPEECH RECOGNIZED')) {
            return platform::txt('err_audio_nospeech');
        }
        if ($e->says('UNSUPPORTED OR INVALID AUDIO FORMAT') || $e->says('UNAVAILABLE AUDIO')) {
            return platform::txt('err_audio_format');
        }
        if ($e->says('UNABLE TO TRANSCRIBE AUDIO')) {
            return platform::txt('err_audio_transcribe');
        }
        if ($e->says('VOICE AGENT IS NOT SET UP') || ($context === 'discuss' && $e->httpstatus === 503)) {
            return platform::txt('err_live_unavailable');
        }
        if ($e->says('UNKNOWN VOICE ALIAS')) {
            return platform::txt('err_voice');
        }
        if ($e->says('STACK IS UNAVAILABLE')) {
            return platform::txt('err_stack');
        }
        if ($e->says('UNABLE TO EVALUATE')) {
            return platform::txt('err_evaluate');
        }
        if ($e->httpstatus === 413) {
            return platform::txt('err_toolarge');
        }
        if ($e->httpstatus === 503) {
            return platform::txt('err_busy');
        }
        if ($e->httpstatus >= 500 || $e->httpstatus < 400) {
            return platform::txt('err_server');
        }
        return platform::txt('err_request');
    }
}
