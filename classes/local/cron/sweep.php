<?php

namespace mod_tarsuscoach\local\cron;

use mod_tarsuscoach\local\index\reconciler;
use mod_tarsuscoach\local\support\budget;
use mod_tarsuscoach\local\support\config;
use mod_tarsuscoach\local\support\platform;

/**
 * What the cron job does, independent of the ILIAS release's cron API:
 * reconcile every course with indexed material against the brain.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class sweep
{
    public const JOB_ID = 'xtar_sync';

    /** Changed bits one run re-sends at most. */
    public const UPLOAD_BUDGET = 10;

    /**
     * @return array{ok: bool, acted: bool, message: string}
     */
    public static function run(): array
    {
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
            budget::has_limit() ? ' of ' . number_format(budget::limit()) . ($usage === null ? ' (brain figure unavailable)' : '') : ''
        );
        return ['ok' => $summary['failed'] === 0, 'acted' => $acted, 'message' => $message];
    }
}
