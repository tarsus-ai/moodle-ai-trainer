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

namespace mod_tarsuscoach\local\cron;

use mod_tarsuscoach\local\index\reconciler;
use mod_tarsuscoach\local\support\budget;
use mod_tarsuscoach\local\support\config;
use mod_tarsuscoach\local\support\platform;

/**
 * What the cron job does, independent of the ILIAS release's cron API:
 * reconcile every course with indexed material against the brain.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sweep
{
    /** The cron job's id. */
    public const JOB_ID = 'xtar_sync';

    /** Changed bits one run re-sends at most. */
    public const UPLOAD_BUDGET = 10;

    /**
     * Reconcile every course with indexed material against the brain, then
     * fetch the month's credit figure. Nothing is sent while the kill switch
     * is engaged.
     *
     * @return array{ok: bool, acted: bool, message: string} Whether nothing failed, whether
     *     anything was withdrawn, re-sent or confirmed, and the summary of the run.
     */
    public static function run(): array {
        if (config::killswitch()) {
            return ['ok' => true, 'acted' => false, 'message' => 'Kill switch engaged: nothing sent.'];
        }
        try {
            $summary = (new reconciler())->reconcile_all(self::UPLOAD_BUDGET);
        } catch (\Throwable $e) {
            platform::log('cron run failed: ' . $e->getMessage(), 'error');
            return ['ok' => false, 'acted' => false, 'message' => substr($e->getMessage(), 0, 250)];
        }
        // The brain's own usage figure for the month, for the limit and
        // the warnings.
        $usage = budget::refresh_remote(true);
        $acted = ($summary['withdrawn'] + $summary['uploaded'] + $summary['confirmed']) > 0;
        $message = sprintf(
            '%d course(s) checked: %d withdrawn, %d changed, %d re-sent, %d confirmed, %d failed. Credits this month: %s%s.',
            $summary['containers'],
            $summary['withdrawn'],
            $summary['changed'],
            $summary['uploaded'],
            $summary['confirmed'],
            $summary['failed'],
            number_format(budget::used()),
            budget::has_limit()
                ? ' of ' . number_format(budget::limit()) . ($usage === null ? ' (brain figure unavailable)' : '')
                : ''
        );
        return ['ok' => $summary['failed'] === 0, 'acted' => $acted, 'message' => $message];
    }
}
