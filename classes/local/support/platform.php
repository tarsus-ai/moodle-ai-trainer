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

namespace mod_tarsuscoach\local\support;

/**
 * The Moodle services the plugin uses, in one place.
 *
 * The rest of the plugin is the same as Tarsus for ILIAS; everything that
 * belongs to Moodle (texts, the database, courses and their modules, files,
 * mail) is reached through here.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class platform {
    /** The frankenstyle name of the plugin. */
    public const COMPONENT = 'mod_tarsuscoach';

    /**
     * The Moodle database.
     *
     * @return \moodle_database
     */
    public static function db(): \moodle_database {
        global $DB;
        return $DB;
    }

    /**
     * The id of the current user (0 when nobody is logged in).
     *
     * @return int
     */
    public static function user_id(): int {
        global $USER;
        return isset($USER->id) ? (int) $USER->id : 0;
    }

    /**
     * URL of a file inside the plugin's assets folder, with the plugin
     * version as cache buster.
     *
     * @param string $relative Path below assets/.
     * @param bool $versioned Whether to add the version.
     * @return string
     */
    public static function asset_url(string $relative, bool $versioned = true): string {
        $params = $versioned ? ['v' => self::plugin_version()] : [];
        return (new \moodle_url('/mod/tarsuscoach/assets/' . ltrim($relative, '/'), $params))->out(false);
    }

    /**
     * The plugin's release (version.php), for the user agent and cache busting.
     *
     * @return string
     */
    public static function plugin_version(): string {
        static $release = null;
        if ($release === null) {
            $plugin = new \stdClass();
            $file = dirname(__DIR__, 3) . '/version.php';
            if (is_file($file)) {
                include($file);
            }
            $release = (string) ($plugin->release ?? '0');
        }
        return $release;
    }

    /**
     * A text of the plugin with {$a->name} placeholders filled in.
     *
     * @param string $key The string identifier.
     * @param array $params Placeholder values by name.
     * @return string
     */
    public static function txt(string $key, array $params = []): string {
        if (!self::has_txt($key)) {
            // A missing text must never break a page or an answer.
            return $key;
        }
        return get_string($key, self::COMPONENT, $params === [] ? null : (object) $params);
    }

    /**
     * Whether the plugin has a text with this identifier.
     *
     * @param string $key The string identifier.
     * @return bool
     */
    public static function has_txt(string $key): bool {
        return get_string_manager()->string_exists($key, self::COMPONENT);
    }

    /**
     * The current user's two-letter interface language.
     *
     * @return string
     */
    public static function user_language(): string {
        return strtolower(substr((string) current_language(), 0, 2));
    }

    /**
     * Log a line: into the task log when running as a task, else into the
     * web server's error log. Never contains an API key.
     *
     * @param string $message What happened.
     * @param string $level error, warning or info.
     */
    public static function log(string $message, string $level = 'warning'): void {
        $line = 'mod_tarsuscoach ' . $level . ': ' . $message;
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            mtrace($line);
            return;
        }
        // phpcs:ignore moodle.PHP.ForbiddenFunctions.FoundWithAlternative
        error_log($line);
    }

    /**
     * Send a generated file as a download and stop.
     *
     * @param string $filename The file name the browser shows.
     * @param string $content The content.
     * @param string $mimetype Its MIME type.
     */
    public static function send_download(string $filename, string $content, string $mimetype): void {
        send_file($content, clean_filename($filename), 0, 0, true, true, $mimetype);
        exit;
    }

    /**
     * The largest recording a spoken question may have, in bytes. It
     * travels base64 encoded (a third larger) inside one request, so it
     * must fit post_max_size with room for the rest of the request (0 means
     * no limit).
     *
     * @return int
     */
    public static function voice_limit(): int {
        $post = self::ini_bytes((string) ini_get('post_max_size'));
        if ($post <= 0) {
            return 0;
        }
        return max(1, (int) floor(($post - 65536) * 3 / 4));
    }

    /**
     * A php.ini size ("8M", "512K", "1G", "1048576") in bytes.
     *
     * @param string $value The ini value.
     * @return int
     */
    private static function ini_bytes(string $value): int {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $unit = strtoupper(substr($value, -1));
        $number = (float) (ctype_alpha($unit) ? substr($value, 0, -1) : $value);
        switch ($unit) {
            case 'G':
                $number *= 1024;
                // Fall through.
            case 'M':
                $number *= 1024;
                // Fall through.
            case 'K':
                $number *= 1024;
        }
        return (int) $number;
    }

    /**
     * A date and time in the user's language and time zone.
     *
     * @param int $timestamp Unix time.
     * @return string
     */
    public static function date(int $timestamp): string {
        return userdate($timestamp, get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * The site's name for mails and banners.
     *
     * @return string
     */
    public static function site_title(): string {
        global $SITE;
        $title = trim(format_string((string) ($SITE->fullname ?? ''), true, ['context' => \context_system::instance()]));
        return $title !== '' ? $title : 'Moodle';
    }

    /**
     * The address of the site's support or main administrator, '' if none.
     *
     * @return string
     */
    public static function admin_email(): string {
        global $CFG;
        $email = trim((string) ($CFG->supportemail ?? ''));
        if ($email === '') {
            $admin = get_admin();
            $email = $admin ? trim((string) $admin->email) : '';
        }
        return $email;
    }

    /**
     * The server's time zone (for the month of the AI credits).
     *
     * @return string
     */
    public static function timezone(): string {
        return \core_date::get_server_timezone();
    }

    /**
     * Send a plain-text mail from the site's no-reply address. Failures are
     * logged, never thrown: a warning mail must not break the request it
     * rides on.
     *
     * @param string[] $recipients E-mail addresses.
     * @param string $subject The subject.
     * @param string $body The plain text.
     * @return bool
     */
    public static function mail(array $recipients, string $subject, string $body): bool {
        $from = \core_user::get_noreply_user();
        $sent = false;
        foreach ($recipients as $address) {
            $address = trim((string) $address);
            if ($address === '' || !validate_email($address)) {
                continue;
            }
            $to = clone $from;
            $to->email = $address;
            $to->mailformat = 0;
            $to->emailstop = 0;
            $to->deleted = 0;
            $to->suspended = 0;
            $to->auth = 'manual';
            try {
                $sent = email_to_user($to, $from, $subject, $body) || $sent;
            } catch (\Throwable $e) {
                self::log('mail failed: ' . $e->getMessage());
            }
        }
        return $sent;
    }

    // ------------------------------------------------------------------
    // Courses and their modules
    // ------------------------------------------------------------------

    /**
     * Whether a course exists (the site front page is no course here).
     *
     * @param int $courseid The course.
     * @return bool
     */
    public static function course_alive(int $courseid): bool {
        return $courseid > SITEID && self::db()->record_exists('course', ['id' => $courseid]);
    }

    /**
     * Whether a course module exists and is not on its way to the recycle bin.
     *
     * @param int $cmid The course module.
     * @return bool
     */
    public static function cm_alive(int $cmid): bool {
        if ($cmid <= 0) {
            return false;
        }
        $cm = self::db()->get_record('course_modules', ['id' => $cmid], 'id, deletioninprogress');
        return $cm !== false && empty($cm->deletioninprogress);
    }

    /**
     * Whether the current user may open a course module (visible to them,
     * available, and the user may see the course).
     *
     * @param int $cmid The course module.
     * @param int $courseid Its course.
     * @return bool
     */
    public static function cm_visible_to_user(int $cmid, int $courseid): bool {
        if ($cmid <= 0 || !self::course_alive($courseid)) {
            return false;
        }
        try {
            $cm = get_fast_modinfo($courseid)->get_cm($cmid);
        } catch (\Throwable $e) {
            return false;
        }
        return (bool) $cm->uservisible;
    }

    /**
     * The view URL of a course module.
     *
     * @param int $cmid The course module.
     * @param string $modname Its module (resource, page, url...).
     * @return string
     */
    public static function cm_url(int $cmid, string $modname): string {
        return (new \moodle_url('/mod/' . $modname . '/view.php', ['id' => $cmid]))->out(false);
    }

    /**
     * The localised name of a module type ("File", "Page"...).
     *
     * @param string $modname The module.
     * @return string
     */
    public static function mod_label(string $modname): string {
        if (get_string_manager()->string_exists('modulename', 'mod_' . $modname)) {
            return get_string('modulename', 'mod_' . $modname);
        }
        return $modname;
    }
}
