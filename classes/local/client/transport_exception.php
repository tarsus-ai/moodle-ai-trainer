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

/**
 * A failed call to Tarsus: no answer at all (status 0: connection failed,
 * timeout), or an HTTP status outside 2xx with the start of Tarsus's own
 * message in the body.
 *
 * Only an outage (no answer, 5xx) counts toward the circuit breaker; a 4xx
 * is Tarsus answering that this one request cannot be served, which says
 * nothing about the service. The mock raises this to exercise those paths
 * deterministically.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
class transport_exception extends \RuntimeException
{
    /** @var int The HTTP status code (0 for a timeout / connection failure). */
    public int $httpstatus;

    /** @var string The start of the response body on an HTTP error, for callers that recognise the brain's messages. */
    public string $body;

    public function __construct(int $httpstatus, string $detail = '', string $body = '') {
        $this->httpstatus = $httpstatus;
        $this->body = $body;
        parent::__construct(
            'Tarsus transport error (HTTP ' . $httpstatus . ')' . ($detail !== '' ? ': ' . $detail : ''),
            $httpstatus
        );
    }

    /**
     * Whether Tarsus could not be reached or failed itself: no answer, a
     * timeout or a 5xx. Only these trip the circuit breaker.
     */
    public function is_outage(): bool {
        return $this->httpstatus === 0 || $this->httpstatus >= 500;
    }

    /**
     * Whether Tarsus's message contains a phrase (case-insensitive). Tarsus
     * answers refusals with an upper-case sentence such as "UNKNOWN OR
     * INACTIVE API KEY" as the whole body.
     */
    public function says(string $phrase): bool {
        return $phrase !== '' && stripos($this->body, $phrase) !== false;
    }
}
