<?php

namespace mod_tarsuscoach\local\support;

/**
 * Stable pseudonyms of ILIAS user ids for transmission to Tarsus: an HMAC
 * of the user id with a secret generated once per installation. Tarsus
 * never receives logins or email addresses.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class pseudonymizer
{
    public static function pseudonymize(int $userid): string
    {
        return hash_hmac('sha256', (string) $userid, config::hmac_secret());
    }
}
