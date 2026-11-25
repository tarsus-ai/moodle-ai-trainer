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
 * Site-wide plugin settings, stored in Moodle's plugin settings (config_plugins).
 *
 * One place to connect ILIAS to Tarsus, to record the customer's plan and
 * to bound traffic and credit spend. Operational switches (kill switch,
 * mock mode, limits) are read
 * fresh from the database on every call, bypassing the request cache, so
 * that flipping one mid-run - during a long cron run, say - takes effect
 * immediately.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class config
{
    /** The plugin whose settings these are (Moodle's config_plugins). */
    public const PLUGIN = 'mod_tarsuscoach';

    /** Default outbound calls per minute. */
    public const DEFAULT_RATE_LIMIT = 60;

    /** Default assistant name. */
    public const DEFAULT_ROBOT_NAME = 'Tarsus';

    /**
     * Read a setting (Moodle caches the plugin's settings per request).
     */
    public static function get(string $key, ?string $default = null): ?string {
        $value = get_config(self::PLUGIN, $key);
        if ($value === false || $value === null) {
            return $default;
        }
        return (string) $value;
    }

    /**
     * Read a setting straight from the database, skipping every cache.
     */
    public static function fresh(string $key, ?string $default = null): ?string {
        $value = platform::db()->get_field('config_plugins', 'value', ['plugin' => self::PLUGIN, 'name' => $key]);
        if ($value === false || $value === null) {
            return $default;
        }
        return (string) $value;
    }

    /**
     * Store a setting; null removes it.
     */
    public static function set(string $key, ?string $value): void {
        if ($value === null) {
            unset_config($key, self::PLUGIN);
        } else {
            set_config($key, $value, self::PLUGIN);
        }
    }

    /**
     * Forget cached settings (long task runs).
     */
    public static function reset(): void {
        \cache_helper::invalidate_by_definition('core', 'config', [], [self::PLUGIN]);
    }

    // ------------------------------------------------------------------
    // Typed accessors
    // ------------------------------------------------------------------

    public static function base_url(): string {
        return trim((string) self::get('base_url', ''));
    }

    public static function api_key(): string {
        return trim((string) self::get('api_key', ''));
    }

    public static function brain_id(): string {
        return trim((string) self::get('brain_id', ''));
    }

    /**
     * The Tarsus license key of an existing Tarsus customer. Such a
     * customer carries it in addition to the API key and in place of a
     * brain ID; the plugin sends it with every call, and Tarsus - not the
     * partner - bills the usage. Empty for partner customers.
     */
    public static function license_key(): string {
        return trim((string) self::get('license_key', ''));
    }

    /**
     * Whether the connection is complete: base URL, API key, and a brain
     * ID or a license key.
     */
    public static function is_connected(): bool {
        return self::base_url() !== '' && self::api_key() !== '' && (self::brain_id() !== '' || self::license_key() !== '');
    }

    /**
     * Which brain the connection leads to: the demo, or a server with a
     * brain ID or license key. When it changes, what the index map records
     * was true of the previous brain only (see reconciler::retarget()).
     * The API key is not part of it: another key for the same brain is
     * still the same brain.
     */
    public static function target(): string {
        if (self::mock_mode()) {
            return 'demo';
        }
        return implode("\x1f", [
            strtolower(rtrim(self::base_url(), '/')),
            self::brain_id(),
            self::license_key() === '' ? '' : hash('sha256', self::license_key()),
        ]);
    }

    /**
     * Mock mode defaults to on (fresh install), so no code path can reach a
     * live endpoint by accident.
     */
    public static function mock_mode(): bool {
        $value = self::fresh('mockmode');
        return $value === null || $value === '1';
    }

    public static function killswitch(): bool {
        return self::fresh('killswitch') === '1';
    }

    public static function rate_limit(): int {
        $value = self::fresh('ratelimit');
        return $value === null || $value === '' ? self::DEFAULT_RATE_LIMIT : max(1, (int) $value);
    }

    public static function robot_name(): string {
        $name = trim((string) self::get('robot_name', ''));
        return $name !== '' ? $name : self::DEFAULT_ROBOT_NAME;
    }

    public static function robot_id(): string {
        $id = trim((string) self::get('robot_id', ''));
        return $id !== '' ? $id : self::robot_name();
    }

    public static function robot_description(): string {
        $description = trim((string) self::get('robot_description', ''));
        return $description !== '' ? $description : self::robot_name();
    }

    /**
     * The uploaded assistant avatar as a data URI, or '' when none.
     */
    public static function robot_avatar(): string {
        $avatar = (string) self::get('robot_avatar', '');
        return preg_match('~^data:image/(png|jpeg|gif|webp|svg\+xml);base64,[A-Za-z0-9+/=]+$~', $avatar) ? $avatar : '';
    }

    /**
     * A short, stable token identifying this ILIAS installation (and
     * client), generated once. It keeps two installations that share one
     * brain apart.
     */
    public static function site_token(): string {
        $token = (string) self::get('site_token', '');
        if (!preg_match('/^site[0-9a-f]{12}$/', $token)) {
            $token = 'site' . substr(bin2hex(random_bytes(16)), 0, 12);
            self::set('site_token', $token);
        }
        return $token;
    }

    /**
     * The HMAC secret for user pseudonyms, generated once. Rotating it
     * invalidates every conversation in the brain, so it is never rotated
     * implicitly.
     */
    public static function hmac_secret(): string {
        $secret = (string) self::get('hmac_secret', '');
        if (strlen($secret) < 32) {
            $secret = bin2hex(random_bytes(32));
            self::set('hmac_secret', $secret);
        }
        return $secret;
    }

    /**
     * Text as it may be stored. Moodle keeps full Unicode (utf8mb4 on MySQL
     * and MariaDB), so nothing needs stripping; kept for the shared code.
     */
    public static function bmp(string $value): string {
        return $value;
    }
}
