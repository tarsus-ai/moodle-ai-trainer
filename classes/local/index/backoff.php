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
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class backoff
{
    public const RETRY_LIMIT = 4;

    public const BASE_SECONDS = 120;

    public static function delay(int $attempt, bool $jitter = false): int {
        $attempt = max(1, $attempt);
        $delay = self::BASE_SECONDS * (2 ** ($attempt - 1));

        if ($jitter) {
            $spread = (int) round($delay * 0.1);
            $delay += random_int(-$spread, $spread);
        }

        return max(0, (int) $delay);
    }

    public static function exhausted(int $attempts): bool {
        return $attempts >= self::RETRY_LIMIT;
    }
}
