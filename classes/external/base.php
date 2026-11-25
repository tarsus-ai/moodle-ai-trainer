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

use core_external\external_api;
use core_external\external_single_structure;
use core_external\external_value;
use mod_tarsuscoach\local\activity\scoring;
use mod_tarsuscoach\local\activity\settings;
use mod_tarsuscoach\local\support\platform;

/**
 * What every request of the coach and of the materials list shares: the
 * checks, the activity, and the reply as one JSON text (the replies are
 * nested structures that follow what Tarsus returns).
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends external_api {
    /**
     * The reply of every request.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'json' => new external_value(PARAM_RAW, 'The reply, JSON encoded'),
        ]);
    }

    /**
     * The activity of a course module, once the user may use it (or manage
     * it) and is not a guest.
     *
     * A request that waits for Tarsus closes the session first: Moodle
     * locks the session while a request runs, so the user's other pages
     * would wait too. Such a request cannot write the grade and completion;
     * its reply says so (progressSync) and the browser asks for them with
     * mod_tarsuscoach_push_progress.
     *
     * @param int $cmid The course module.
     * @param bool $manage Whether the request needs the right to manage the activity.
     * @param bool $wait Whether the request waits for Tarsus.
     * @return array The activity record and its settings.
     */
    protected static function activity(int $cmid, bool $manage, bool $wait = true): array {
        global $DB;
        $cm = get_coursemodule_from_id('tarsuscoach', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/tarsuscoach:view', $context);
        if ($manage) {
            require_capability('mod/tarsuscoach:manage', $context);
        }
        if (isguestuser()) {
            throw new \moodle_exception('noguestaccess', 'mod_tarsuscoach');
        }
        $tarsus = $DB->get_record('tarsuscoach', ['id' => $cm->instance], '*', MUST_EXIST);
        if ($wait) {
            \core\session\manager::write_close();
            scoring::defer_pushes();
        }
        return [$tarsus, settings::load((int) $tarsus->id)];
    }

    /**
     * Run a request and encode its reply. A failure is logged and answered
     * with a general message: its details may name the brain's address.
     *
     * @param string $name The request, for the log.
     * @param callable $request Returns the reply as an array.
     * @return array The reply, JSON encoded.
     */
    protected static function respond(string $name, callable $request): array {
        try {
            $data = $request();
        } catch (\Throwable $e) {
            platform::log($name . ' failed: ' . $e->getMessage(), 'error');
            $data = ['status' => 'error', 'ok' => false, 'error' => get_string('unexpected_error', 'mod_tarsuscoach')];
        }
        if (scoring::pushes_pending()) {
            $data['progressSync'] = true;
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return ['json' => $json === false ? '{"ok":false,"error":"encoding"}' : $json];
    }
}
