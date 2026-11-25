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

namespace mod_tarsuscoach\local\client;

use mod_tarsuscoach\local\support\config;

/**
 * Returns the active Tarsus client (mock or real) per site configuration.
 *
 * Defaults to the mock when mock mode is unset (fresh install), so no code
 * path can reach a live endpoint by accident.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class client_factory
{
    private static ?client_interface $override = null;

    public static function get_client(): client_interface {
        if (self::$override !== null) {
            return self::$override;
        }
        if (config::mock_mode()) {
            return new mock_client();
        }
        return new http_client(config::base_url(), config::api_key(), config::brain_id(), config::license_key());
    }

    /**
     * Force a client (tests).
     */
    public static function override(?client_interface $client): void {
        self::$override = $client;
    }
}
