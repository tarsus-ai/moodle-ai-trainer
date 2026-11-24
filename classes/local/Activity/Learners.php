<?php

namespace mod_tarsus\local\Activity;

use mod_tarsus\local\Support\Platform;

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
final class Learners
{
    private int $objid;
    private int $containerref;
    private Settings $settings;

    public function __construct(int $objid, int $containerref, Settings $settings)
    {
        $this->objid = $objid;
        $this->containerref = $containerref;
        $this->settings = $settings;
    }

    /**
     * @return int[] The learners of the activity: enrolled in its course and
     *     allowed to use it, but not to manage it (teachers are no learners).
     */
    public function members(): array
    {
        try {
            $cm = get_coursemodule_from_instance('tarsus', $this->objid, $this->containerref, false, IGNORE_MISSING);
            if (!$cm) {
                return [];
            }
            $context = \context_module::instance($cm->id);
            $users = get_enrolled_users($context, 'mod/tarsus:view', 0, 'u.id', null, 0, 0, true);
            $managers = get_users_by_capability($context, 'mod/tarsus:manage', 'u.id');
            $ids = [];
            foreach ($users as $user) {
                if (!isset($managers[$user->id])) {
                    $ids[] = (int) $user->id;
                }
            }
            return $ids;
        } catch (\Throwable $e) {
            Platform::log('learners of the course: ' . $e->getMessage());
        }
        return [];
    }

    /**
     * Names of users by id (deleted users are left out).
     *
     * @param int[] $userids
     * @return array<int, \stdClass>
     */
    public static function names(array $userids): array
    {
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        if ($userids === []) {
            return [];
        }
        $names = [];
        foreach (array_chunk($userids, 500) as $chunk) {
            [$insql, $params] = Platform::db()->get_in_or_equal($chunk);
            $fields = 'id, username, ' . implode(', ', \core_user\fields::get_name_fields());
            foreach (Platform::db()->get_records_select('user', 'id ' . $insql . ' AND deleted = 0', $params, '', $fields) as $user) {
                $names[(int) $user->id] = $user;
            }
        }
        return $names;
    }

    /**
     * @return array{learners: array<int, array<string, mixed>>, summary: array<string, mixed>, hasmembers: bool}
     */
    public function overview(): array
    {
        $rows = Scoring::rows($this->objid);
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
            $progress = Scoring::progress($this->settings, $row);
            $grade = Scoring::grade($this->settings, $row);
            $status = Scoring::lpStatus($this->settings, $row);
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
            if ($learner['status'] === Scoring::LP_COMPLETED) {
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
