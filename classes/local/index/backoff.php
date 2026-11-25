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

namespace mod_tarsuscoach\local\index;

/**
 * Capped exponential backoff for retries: 120 -> 240 -> 480 -> 960 s,
 * four attempts at most, with optional jitter so a batch does not retry in
 * lockstep.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backoff
{
    /** Failed attempts after which retrying stops. */
    public const RETRY_LIMIT = 4;

    /** Seconds before the first retry; the delay doubles with each attempt. */
    public const BASE_SECONDS = 120;

    /**
     * Seconds to wait before the next try after a failed attempt.
     *
     * @param int $attempt How many attempts have failed so far (from 1).
     * @param bool $jitter Whether to vary the delay by up to a tenth either way.
     * @return int
     */
    public static function delay(int $attempt, bool $jitter = false): int {
        $attempt = max(1, $attempt);
        $delay = self::BASE_SECONDS * (2 ** ($attempt - 1));

        if ($jitter) {
            $spread = (int) round($delay * 0.1);
            $delay += random_int(-$spread, $spread);
        }

        return max(0, (int) $delay);
    }

    /**
     * Whether the retries are used up.
     *
     * @param int $attempts How many attempts have failed so far.
     * @return bool
     */
    public static function exhausted(int $attempts): bool {
        return $attempts >= self::RETRY_LIMIT;
    }
}
