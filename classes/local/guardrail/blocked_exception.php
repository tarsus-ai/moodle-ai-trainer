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

namespace mod_tarsuscoach\local\guardrail;

/**
 * Raised when a guardrail gate refuses an outbound call.
 *
 * The reason ('killswitch', 'breaker', 'budget', 'ratelimit') lets callers
 * decide whether to hold, back off, or surface an error.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blocked_exception extends \RuntimeException
{
    /** @var string The gate that refused: killswitch, breaker, budget or ratelimit. */
    public string $reason;

    /**
     * A refusal by the given gate.
     *
     * @param string $reason The gate: killswitch, breaker, budget or ratelimit.
     */
    public function __construct(string $reason) {
        $this->reason = $reason;
        parent::__construct('Tarsus call blocked: ' . $reason);
    }
}
