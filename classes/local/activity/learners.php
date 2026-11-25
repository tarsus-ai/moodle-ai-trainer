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

namespace mod_tarsuscoach\local\activity;

use mod_tarsuscoach\local\support\platform;

/**
 * The teacher's overview of a Tarsus object: how the learners progress.
 *
 * Everything comes from the figures stored per learner; the overview never
 * calls the brain. The summary counts the course (or group) members, so
 * tutors and admins trying the object do not skew it.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class learners
{
    private int $objid;
    private int $containerref;
    private settings $settings;

    public function __construct(int $objid, int $containerref, settings $settings) {
        $this->objid = $objid;
        $this->containerref = $containerref;
        $this->settings = $settings;
    }

    /**
     * @return int[] The learners of the activity: enrolled in its course and
     *     allowed to use it, but not to manage it (teachers are no learners).
     */
    public function members(): array {
        try {
            $cm = get_coursemodule_from_instance('tarsuscoach', $this->objid, $this->containerref, false, IGNORE_MISSING);
            if (!$cm) {
                return [];
            }
            $context = \context_module::instance($cm->id);
            $users = get_enrolled_users($context, 'mod/tarsuscoach:view', 0, 'u.id', null, 0, 0, true);
            $managers = get_users_by_capability($context, 'mod/tarsuscoach:manage', 'u.id');
            $ids = [];
            foreach ($users as $user) {
                if (!isset($managers[$user->id])) {
                    $ids[] = (int) $user->id;
                }
            }
            return $ids;
        } catch (\Throwable $e) {
            platform::log('learners of the course: ' . $e->getMessage());
        }
        return [];
    }

    /**
     * Names of users by id (deleted users are left out).
     *
     * @param int[] $userids
     * @return array<int, \stdClass>
     */
    public static function names(array $userids): array {
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        if ($userids === []) {
            return [];
        }
        $names = [];
        foreach (array_chunk($userids, 500) as $chunk) {
            [$insql, $params] = platform::db()->get_in_or_equal($chunk);
            $fields = 'id, username, ' . implode(', ', \core_user\fields::get_name_fields());
            foreach (platform::db()->get_records_select('user', 'id ' . $insql . ' AND deleted = 0', $params, '', $fields) as $user) {
                $names[(int) $user->id] = $user;
            }
        }
        return $names;
    }

    /**
     * @return array{learners: array<int, array<string, mixed>>, summary: array<string, mixed>, hasmembers: bool}
     */
    public function overview(): array {
        $rows = scoring::rows($this->objid);
        $members = $this->members();
        $hasmembers = $members !== [];
        $memberset = array_flip($members);
        $userids = $hasmembers ? array_values(array_unique(array_merge($members, array_keys($rows)))) : array_keys($rows);

        $learners = [];
        $names = self::names($userids);
        foreach ($userids as $userid) {
            $userid = (int) $userid;
            if (!isset($names[$userid])) {
                continue;
            }
            $user = $names[$userid];
            $name = ['lastname' => (string) $user->lastname, 'firstname' => (string) $user->firstname, 'login' => (string) $user->username];
            $row = $rows[$userid] ?? null;
            $progress = scoring::progress($this->settings, $row);
            $grade = scoring::grade($this->settings, $row);
            $status = scoring::lp_status($this->settings, $row);
            $learners[] = [
                'userid' => $userid,
                'lastname' => (string) ($name['lastname'] ?? ''),
                'firstname' => (string) ($name['firstname'] ?? ''),
                'login' => (string) ($name['login'] ?? ''),
                'member' => !$hasmembers || isset($memberset[$userid]),
                'started' => $row !== null,
                'progress' => $progress,
                'grade' => $row !== null ? $grade : null,
                'earnedxp' => $row->earnedxp ?? 0.0,
                'totalxp' => $row->totalxp ?? 0.0,
                'earnedunits' => $row->earnedunits ?? 0.0,
                'totalunits' => $row->totalunits ?? 0.0,
                'status' => $status,
                'lastactivity' => $row->timemodified ?? 0,
            ];
        }
        usort($learners, static fn(array $a, array $b): int => strcasecmp($a['lastname'] . ' ' . $a['firstname'], $b['lastname'] . ' ' . $b['firstname']));

        $counted = array_values(array_filter($learners, static fn(array $l): bool => $l['member']));
        $completed = 0;
        $inprogress = 0;
        $progresses = [];
        $grades = [];
        $buckets = [0, 0, 0, 0];
        foreach ($counted as $learner) {
            if (!$learner['started']) {
                continue;
            }
            $progresses[] = $learner['progress'];
            if ($learner['grade'] !== null) {
                $grades[] = $learner['grade'];
            }
            $buckets[min(3, (int) floor($learner['progress'] / 25))]++;
            if ($learner['status'] === scoring::LP_COMPLETED) {
                $completed++;
            } else {
                $inprogress++;
            }
        }
        $total = count($counted);

        return [
            'learners' => $learners,
            'hasmembers' => $hasmembers,
            'summary' => [
                'total' => $total,
                'completed' => $completed,
                'inprogress' => $inprogress,
                'notstarted' => max(0, $total - $completed - $inprogress),
                'avgprogress' => $progresses ? array_sum($progresses) / count($progresses) : 0.0,
                'avggrade' => $grades ? array_sum($grades) / count($grades) : null,
                'buckets' => $buckets,
            ],
        ];
    }
}
