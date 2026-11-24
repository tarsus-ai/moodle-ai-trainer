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

namespace mod_tarsus\local\Activity;

use mod_tarsus\local\Support\Platform;

/**
 * Turns the brain's figures into a result and a completion status.
 *
 * With every answer the brain reports the learner's experience points
 * (Test knowledge) and learning units (Build knowledge) with their totals,
 * plus its progress percentages. The latest figures are kept per learner
 * and activity. Which figure is the result and which the progress depends
 * on the active modes (switched on and included in the plan):
 *
 *   Test knowledge only:  result = XP in percent,  progress = practiceProgress
 *   Build knowledge only: result = LU in percent,  progress = learnProgress
 *   both:                 result = XP in percent,  progress = LU in percent
 *   neither:              no result, no progress (a read-only activity)
 *
 * The result goes to the gradebook; the completion rule compares the
 * progress with the teacher's threshold.
 *
 * @package    mod_tarsus
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class Scoring {
    /** The figures per learner and activity. */
    public const TABLE = 'tarsus_prg';

    /** Status: no figures yet. */
    public const LP_NOT_ATTEMPTED = 0;

    /** Status: working on it. */
    public const LP_IN_PROGRESS = 1;

    /** Status: the progress reached the completion threshold. */
    public const LP_COMPLETED = 2;

    /**
     * Store the figures the brain just reported and refresh the grade and
     * completion when anything changed.
     *
     * @param int $objid The Tarsus activity.
     * @param int $userid The learner.
     * @param array $progress The brain's figures.
     * @param bool $interaction Whether the learner did something (not just read the feed).
     */
    public static function record(int $objid, int $userid, array $progress, bool $interaction = true): void {
        if ($progress === [] || $userid <= 0 || isguestuser($userid)) {
            return;
        }
        $values = [
            'earned_xp' => round((float) ($progress['earnedxp'] ?? 0), 2),
            'total_xp' => round((float) ($progress['totalxp'] ?? 0), 2),
            'earned_units' => round((float) ($progress['earnedunits'] ?? 0), 2),
            'total_units' => round((float) ($progress['totalunits'] ?? 0), 2),
            'practice_prg' => round((float) ($progress['practiceprogress'] ?? 0), 2),
            'learn_prg' => round((float) ($progress['learnprogress'] ?? 0), 2),
            'overall_prg' => round((float) ($progress['progress'] ?? 0), 2),
        ];

        $existing = self::raw($objid, $userid);
        if ($existing === null && !$interaction) {
            // Merely reading the feed with nothing earned yet creates no
            // row: the learner still counts as "not attempted" until the
            // first question, answer or learning unit.
            $touched = false;
            foreach (['earned_xp', 'earned_units', 'practice_prg', 'learn_prg', 'overall_prg'] as $key) {
                if ($values[$key] > 0) {
                    $touched = true;
                }
            }
            if (!$touched) {
                return;
            }
        }
        if ($existing !== null) {
            $same = true;
            foreach ($values as $key => $value) {
                if (abs((float) $existing->$key - $value) > 0.001) {
                    $same = false;
                    break;
                }
            }
            if ($same) {
                return;
            }
        }

        $record = (object) ($values + ['modified' => time()]);
        $db = Platform::db();
        if ($existing !== null) {
            $record->id = $existing->id;
            $db->update_record(self::TABLE, $record);
        } else {
            $record->obj_id = $objid;
            $record->usr_id = $userid;
            $db->insert_record(self::TABLE, $record);
        }

        self::pushLearningProgress($objid, $userid);
    }

    /**
     * Note that a learner interacted (asked a question) when the brain
     * reported no figures: the learner is then "in progress".
     *
     * @param int $objid The Tarsus activity.
     * @param int $userid The learner.
     */
    public static function touch(int $objid, int $userid): void {
        if ($userid <= 0 || isguestuser($userid) || self::raw($objid, $userid) !== null) {
            return;
        }
        self::record($objid, $userid, ['earnedxp' => 0, 'totalxp' => 0], true);
    }

    /**
     * Forget a learner's figures (the conversation was erased).
     *
     * @param int $objid The Tarsus activity.
     * @param int $userid The learner.
     */
    public static function clear(int $objid, int $userid): void {
        Platform::db()->delete_records(self::TABLE, ['obj_id' => $objid, 'usr_id' => $userid]);
        self::pushLearningProgress($objid, $userid);
    }

    /**
     * Forget every learner's figures of an activity (it is deleted).
     *
     * @param int $objid The Tarsus activity.
     */
    public static function forgetObject(int $objid): void {
        Platform::db()->delete_records(self::TABLE, ['obj_id' => $objid]);
    }

    /**
     * The figures of one learner, null when there are none.
     *
     * @param int $objid The Tarsus activity.
     * @param int $userid The learner.
     * @return \stdClass|null
     */
    public static function row(int $objid, int $userid): ?\stdClass {
        $raw = self::raw($objid, $userid);
        return $raw === null ? null : self::map($raw);
    }

    /**
     * The figures of every learner of an activity.
     *
     * @param int $objid The Tarsus activity.
     * @return \stdClass[] User id => figures.
     */
    public static function rows(int $objid): array {
        $rows = [];
        foreach (Platform::db()->get_records(self::TABLE, ['obj_id' => $objid]) as $row) {
            $rows[(int) $row->usr_id] = self::map($row);
        }
        return $rows;
    }

    /**
     * Whether a result and a progress exist at all: at least one of Test
     * knowledge and Build knowledge is active. Otherwise the activity is a
     * read-only one (Ask a question alone).
     *
     * @param Settings $settings The activity's settings.
     * @return bool
     */
    public static function measurable(Settings $settings): bool {
        return $settings->modeActive('test') || $settings->modeActive('build');
    }

    /**
     * The result (0-100): the experience points in percent whenever Test
     * knowledge is active, the learning units in percent when only Build
     * knowledge is; null when neither is.
     *
     * @param Settings $settings The activity's settings.
     * @param \stdClass|null $row The learner's figures.
     * @return float|null
     */
    public static function grade(Settings $settings, ?\stdClass $row): ?float {
        $test = $settings->modeActive('test');
        $build = $settings->modeActive('build');
        if (!$test && !$build) {
            return null;
        }
        if ($row === null) {
            return 0.0;
        }
        return round($test ? self::share($row->earnedxp, $row->totalxp) : self::share($row->earnedunits, $row->totalunits), 2);
    }

    /**
     * The progress percentage (0-100) the completion rule looks at: Test
     * knowledge alone the brain's practiceProgress, Build knowledge alone
     * its learnProgress, both the learning units in percent; neither none.
     *
     * @param Settings $settings The activity's settings.
     * @param \stdClass|null $row The learner's figures.
     * @return float
     */
    public static function progress(Settings $settings, ?\stdClass $row): float {
        if ($row === null) {
            return 0.0;
        }
        $test = $settings->modeActive('test');
        $build = $settings->modeActive('build');
        if ($test && $build) {
            $value = self::share($row->earnedunits, $row->totalunits);
        } else if ($build) {
            $value = $row->learnprogress;
        } else if ($test) {
            $value = $row->practiceprogress;
        } else {
            $value = 0.0;
        }
        return round(max(0.0, min(100.0, $value)), 2);
    }

    /**
     * The status of a learner. A read-only activity (neither Test nor Build
     * knowledge active) has none.
     *
     * @param Settings $settings The activity's settings.
     * @param \stdClass|null $row The learner's figures.
     * @return int
     */
    public static function lpStatus(Settings $settings, ?\stdClass $row): int {
        if ($row === null || !self::measurable($settings)) {
            return self::LP_NOT_ATTEMPTED;
        }
        if ($settings->completionprogress > 0 && self::progress($settings, $row) >= $settings->completionprogress) {
            return self::LP_COMPLETED;
        }
        return self::LP_IN_PROGRESS;
    }

    /**
     * A share in percent, 0 when there is no total.
     *
     * @param float $earned Earned.
     * @param float $total Total.
     * @return float
     */
    private static function share(float $earned, float $total): float {
        if ($total <= 0) {
            return 0.0;
        }
        return max(0.0, min(100.0, $earned / $total * 100));
    }

    /**
     * Write the learner's result to the gradebook and let Moodle recompute
     * the activity completion.
     *
     * @param int $objid The Tarsus activity.
     * @param int $userid The learner.
     */
    public static function pushLearningProgress(int $objid, int $userid): void {
        global $CFG;
        try {
            require_once($CFG->dirroot . '/mod/tarsus/lib.php');
            require_once($CFG->libdir . '/completionlib.php');
            $db = Platform::db();
            $tarsus = $db->get_record('tarsus', ['id' => $objid]);
            if (!$tarsus) {
                return;
            }
            tarsus_update_grades($tarsus, $userid);
            $cm = get_coursemodule_from_instance('tarsus', $objid, (int) $tarsus->course, false, IGNORE_MISSING);
            $course = $cm ? $db->get_record('course', ['id' => $cm->course]) : null;
            if ($cm && $course) {
                $completion = new \completion_info($course);
                if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC && !empty($tarsus->completionprogress)) {
                    $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
                }
            }
        } catch (\Throwable $e) {
            Platform::log('could not update the grade or completion: ' . $e->getMessage());
        }
    }

    /**
     * Refresh the grade and completion of every learner of an activity
     * (after the threshold or the modes changed).
     *
     * @param int $objid The Tarsus activity.
     */
    public static function pushAll(int $objid): void {
        foreach (array_keys(self::rows($objid)) as $userid) {
            self::pushLearningProgress($objid, (int) $userid);
        }
    }

    /**
     * The stored row of one learner.
     *
     * @param int $objid The Tarsus activity.
     * @param int $userid The learner.
     * @return \stdClass|null
     */
    private static function raw(int $objid, int $userid): ?\stdClass {
        $row = Platform::db()->get_record(self::TABLE, ['obj_id' => $objid, 'usr_id' => $userid]);
        return $row ?: null;
    }

    /**
     * A stored row in the shape the rest of the plugin reads.
     *
     * @param \stdClass $row The stored row.
     * @return \stdClass
     */
    private static function map(\stdClass $row): \stdClass {
        return (object) [
            'userid' => (int) $row->usr_id,
            'earnedxp' => (float) $row->earned_xp,
            'totalxp' => (float) $row->total_xp,
            'earnedunits' => (float) $row->earned_units,
            'totalunits' => (float) $row->total_units,
            'practiceprogress' => (float) $row->practice_prg,
            'learnprogress' => (float) $row->learn_prg,
            'progress' => (float) $row->overall_prg,
            'timemodified' => (int) $row->modified,
        ];
    }
}
