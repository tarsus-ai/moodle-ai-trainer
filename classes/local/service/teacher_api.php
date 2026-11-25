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

namespace mod_tarsuscoach\local\service;

use mod_tarsuscoach\local\activity\learners;
use mod_tarsuscoach\local\activity\scope;
use mod_tarsuscoach\local\activity\scoring;
use mod_tarsuscoach\local\activity\settings;
use mod_tarsuscoach\local\client\client_factory;
use mod_tarsuscoach\local\client\client_interface;
use mod_tarsuscoach\local\client\configuration_exception;
use mod_tarsuscoach\local\client\transport_exception;
use mod_tarsuscoach\local\guardrail\blocked_exception;
use mod_tarsuscoach\local\guardrail\pipeline;
use mod_tarsuscoach\local\output\shaper;
use mod_tarsuscoach\local\support\platform;
use mod_tarsuscoach\local\support\pseudonymizer;

/**
 * The teacher's side panels of a Tarsus object: the evaluation of all
 * learners, one learner's history (read only) and how that learner grew.
 *
 * The figures are Tarsus's own, read back with one call for all learners;
 * they also refresh the learning progress in ILIAS. Reading costs no
 * credits and is never refused by the monthly limit.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class teacher_api
{
    /** Days the growth chart covers. */
    public const ANALYTICS_DAYS = 30;

    /** @var int The Tarsus activity. */
    private int $objid;
    /** @var int The course the activity lives in. */
    private int $containerref;
    /** @var settings The activity's settings. */
    private settings $settings;
    /** @var client_interface The Tarsus client (mock or real, per the site configuration). */
    private client_interface $client;
    /** @var pipeline The guardrails every call to the brain goes through. */
    private pipeline $pipeline;

    /**
     * Keep the activity's details and set up the Tarsus client and the
     * guardrails.
     *
     * @param int $objid The Tarsus activity.
     * @param int $containerref The course the activity lives in.
     * @param settings $settings The activity's settings.
     */
    public function __construct(int $objid, int $containerref, settings $settings) {
        $this->objid = $objid;
        $this->containerref = $containerref;
        $this->settings = $settings;
        $this->client = client_factory::get_client();
        $this->pipeline = new pipeline();
    }

    /**
     * A result for the teacher's panels: the extra values with the status
     * and the user-facing error.
     *
     * @param string $status success, noscope or error.
     * @param string $error The message for the user; '' when there is none.
     * @param array<string, mixed> $extra Further values of the result.
     * @return array<string, mixed>
     */
    private static function result(string $status, string $error = '', array $extra = []): array {
        return $extra + ['status' => $status, 'error' => $error];
    }

    /**
     * Read from the brain through the guardrails; a failure comes back as
     * the message for the user.
     *
     * @param callable(): array<string, mixed> $call The read, without arguments.
     * @param string $context What is read, for the message and the log.
     * @return array<string, mixed>|string The response, or the message of the failure.
     */
    private function read(callable $call, string $context) {
        try {
            $response = $this->pipeline->execute($call);
            return is_array($response) ? $response : [];
        } catch (blocked_exception $e) {
            return platform::txt('guardrail_blocked_' . $e->reason);
        } catch (transport_exception $e) {
            return messages::for_transport($e, $context);
        } catch (configuration_exception $e) {
            return platform::txt($e->langkey);
        }
    }

    /**
     * Every learner of the course with Tarsus's figures, their result in
     * percent, their learning progress status in ILIAS and the groups of
     * the course for the filter.
     *
     * @return array<string, mixed>
     */
    public function statistics(): array {
        $learners = new learners($this->objid, $this->containerref, $this->settings);
        $userids = array_column($learners->overview()['learners'], 'userid');

        // Tarsus's figures for every learner at once; they refresh the
        // learning progress in ILIAS on the way.
        $pseudonyms = [];
        foreach ($userids as $userid) {
            $pseudonyms[pseudonymizer::pseudonymize((int) $userid)] = (int) $userid;
        }
        $warning = '';
        if ($pseudonyms !== []) {
            $contextid = scope::conversation_context_id($this->objid);
            $response = $this->read(
                fn() => ['statistics' => $this->client->get_context_statistics($contextid, array_keys($pseudonyms))],
                'statistics'
            );
            if (is_string($response)) {
                $warning = $response;
            } else {
                foreach ((array) ($response['statistics'] ?? []) as $pseudonym => $figures) {
                    if (isset($pseudonyms[$pseudonym]) && is_array($figures)) {
                        scoring::record($this->objid, $pseudonyms[$pseudonym], $figures, false);
                    }
                }
            }
        }

        // Read again: the overview now carries the brain's figures.
        $overview = $learners->overview();
        $groups = $this->groups();
        $rows = [];
        foreach ($overview['learners'] as $learner) {
            $userid = (int) $learner['userid'];
            $rows[] = [
                'userid' => $userid,
                'name' => trim($learner['lastname'] . ', ' . $learner['firstname'], ', '),
                'login' => (string) $learner['login'],
                'member' => (bool) $learner['member'],
                'started' => (bool) $learner['started'],
                'grade' => $learner['grade'] === null ? null : round((float) $learner['grade'], 1),
                'progress' => round((float) $learner['progress'], 1),
                'earnedxp' => round((float) $learner['earnedxp'], 2),
                'totalxp' => round((float) $learner['totalxp'], 2),
                'earnedunits' => round((float) $learner['earnedunits'], 2),
                'totalunits' => round((float) $learner['totalunits'], 2),
                'status' => (int) $learner['status'],
                'lastactivity' => (int) $learner['lastactivity'],
                'lastactivitylabel' => (int) $learner['lastactivity'] > 0 ? platform::date((int) $learner['lastactivity']) : '',
                'groups' => $groups['members'][$userid] ?? [],
            ];
        }

        return self::result('success', '', [
            'learners' => $rows,
            'summary' => $overview['summary'],
            'groups' => $groups['list'],
            'measurable' => scoring::measurable($this->settings),
            'threshold' => $this->settings->completionprogress,
            'warning' => $warning,
        ]);
    }

    /**
     * The groups inside the course, with their members, for the filter.
     *
     * @return array{list: array<int, array{id: int, title: string}>, members: array<int, int[]>}
     */
    private function groups(): array {
        $list = [];
        $members = [];
        try {
            if (!platform::course_alive($this->containerref)) {
                return ['list' => [], 'members' => []];
            }
            $context = \context_course::instance($this->containerref);
            foreach (groups_get_all_groups($this->containerref) as $group) {
                $groupid = (int) $group->id;
                // The coach script shows the name as text: filtered, not escaped.
                $list[] = [
                    'id' => $groupid,
                    'title' => format_string($group->name, true, ['context' => $context, 'escape' => false]),
                ];
                foreach (groups_get_members($groupid, 'u.id') as $user) {
                    $members[(int) $user->id][] = $groupid;
                }
            }
        } catch (\Throwable $e) {
            platform::log('groups of the course: ' . $e->getMessage());
        }
        return ['list' => $list, 'members' => $members];
    }

    /**
     * One learner's history as Tarsus stored it, newest page first, for
     * reading only: nothing is generated, nothing is charged.
     *
     * @return array<string, mixed>
     */
    public function history(int $userid, int $offset): array {
        if (!$this->is_learner($userid)) {
            return self::result('error', platform::txt('invalid_request'));
        }
        $allowed = scope::allowed_resource_ids($this->objid, $this->containerref);
        if ($allowed === []) {
            return self::result('noscope', platform::txt('chat_noscope'), ['items' => [], 'hasmore' => false]);
        }
        $params = [
            'contextid' => scope::conversation_context_id($this->objid),
            'userid' => pseudonymizer::pseudonymize($userid),
            'resourceids' => $allowed,
            'taxonomylevels' => $this->settings->taxonomylevels,
            'offset' => max(0, $offset),
            'pagesize' => learner_api::FEED_PAGE_SIZE,
        ];
        $response = $this->read(fn() => $this->client->retrieve_feed($params), 'history');
        if (is_string($response)) {
            return self::result('error', $response, ['items' => [], 'hasmore' => false]);
        }
        $names = learners::names([$userid]);
        return self::result('success', '', learner_api::feed_page($response) + [
            'progress' => shaper::progress((array) ($response['progress'] ?? [])),
            'name' => isset($names[$userid]) ? fullname($names[$userid]) : '',
        ]);
    }

    /**
     * How one learner's experience points and learning units grew over the
     * last 30 days, in percent of all.
     *
     * @return array<string, mixed>
     */
    public function analytics(int $userid): array {
        if (!$this->is_learner($userid)) {
            return self::result('error', platform::txt('invalid_request'));
        }
        $end = time();
        $start = $end - self::ANALYTICS_DAYS * 86400;
        $contextid = scope::conversation_context_id($this->objid);
        $pseudonym = pseudonymizer::pseudonymize($userid);
        $response = $this->read(
            fn() => $this->client->get_user_analytics($contextid, $pseudonym, $start, $end),
            'analytics'
        );
        if (is_string($response)) {
            return self::result('error', $response);
        }
        return self::result('success', '', [
            'xp' => array_values((array) ($response['xp'] ?? [])),
            'units' => array_values((array) ($response['units'] ?? [])),
            'start' => $start,
            'end' => $end,
        ]);
    }

    /**
     * Whether a user belongs to this object's learners: a member of its
     * course or group, or someone with figures here.
     */
    private function is_learner(int $userid): bool {
        if ($userid <= 0 || !platform::db()->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            return false;
        }
        $learners = new learners($this->objid, $this->containerref, $this->settings);
        return in_array($userid, $learners->members(), true) || scoring::row($this->objid, $userid) !== null;
    }
}
