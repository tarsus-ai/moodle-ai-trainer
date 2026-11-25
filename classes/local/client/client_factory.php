<?php

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

    public static function get_client(): client_interface
    {
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
    public static function override(?client_interface $client): void
    {
        self::$override = $client;
    }
}
