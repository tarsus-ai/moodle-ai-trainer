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

namespace mod_tarsuscoach\local\output;

use mod_tarsuscoach\local\index\status;

/**
 * Maps an index status to its presentation: badge and available actions.
 *
 * Single source of truth for the server-rendered material list and its
 * live updates, so the two never drift.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class presenter
{
    /**
     * Seconds after which a resource still awaiting the brain's
     * confirmation counts as stuck and may be removed by the teacher.
     */
    public const STUCK_AFTER = 600;

    /** @var array<string, string> status => badge modifier. */
    private const BADGES = [
        status::NOT_INDEXED => 'neutral',
        status::PENDING => 'warning',
        status::PENDING_CONFIRMATION => 'warning',
        status::PENDING_REINDEX => 'warning',
        status::PENDING_DELETION => 'muted',
        status::INDEXED => 'success',
        status::ERROR => 'danger',
    ];

    /**
     * The CSS classes of a status badge.
     *
     * @param string $status The index status.
     * @return string The classes; the neutral badge for an unknown status.
     */
    public static function badge_class(string $status): string {
        return 'tarsus-badge tarsus-badge--' . (self::BADGES[$status] ?? 'neutral');
    }

    /**
     * Whether a call is pending for the resource.
     */
    public static function is_inflight(string $status): bool {
        // The pendingreindex status is not in flight: it is a stable
        // "content changed" state that waits for the teacher (or the cron job).
        return in_array($status, [status::PENDING, status::PENDING_CONFIRMATION, status::PENDING_DELETION], true);
    }

    /**
     * The actions the teacher may take on a resource in the given status.
     *
     * @param string $status The index status.
     * @param int $age Seconds since the resource's row last changed.
     * @return string[] Subset of index, reindex, remove, retry.
     */
    public static function actions_for(string $status, int $age = 0): array {
        switch ($status) {
            case status::NOT_INDEXED:
                return ['index'];
            case status::INDEXED:
            case status::PENDING_REINDEX:
                return ['reindex', 'remove'];
            case status::ERROR:
                return ['retry', 'remove'];
            case status::PENDING_CONFIRMATION:
                // The brain never answered: after a while the teacher may
                // give up on the upload and untrack it.
                return $age >= self::STUCK_AFTER ? ['remove'] : [];
            default:
                return [];
        }
    }

    /**
     * The full presentational payload of one bit.
     *
     * @return array<string, mixed>
     */
    public static function present(string $resourceid, string $status, int $timemodified = 0): array {
        $age = $timemodified > 0 ? max(0, time() - $timemodified) : 0;
        $actions = self::actions_for($status, $age);
        return [
            'resourceid' => $resourceid,
            'status' => $status,
            'statuslabel' => status::label($status),
            'badgeclass' => self::badge_class($status),
            'inflight' => self::is_inflight($status),
            'canindex' => in_array('index', $actions, true),
            'canreindex' => in_array('reindex', $actions, true),
            'canremove' => in_array('remove', $actions, true),
            'canretry' => in_array('retry', $actions, true),
            // Only content the brain holds may be put in an object's scope.
            'caninclude' => $status === status::INDEXED,
        ];
    }
}
