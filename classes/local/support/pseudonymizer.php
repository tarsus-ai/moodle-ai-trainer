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
 * Stable pseudonyms of ILIAS user ids for transmission to Tarsus: an HMAC
 * of the user id with a secret generated once per installation. Tarsus
 * never receives logins or email addresses.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pseudonymizer
{
    /**
     * The pseudonym of a user id: its HMAC-SHA256 with the installation's
     * secret, in hexadecimal.
     *
     * @param int $userid The user.
     * @return string
     */
    public static function pseudonymize(int $userid): string {
        return hash_hmac('sha256', (string) $userid, config::hmac_secret());
    }
}
