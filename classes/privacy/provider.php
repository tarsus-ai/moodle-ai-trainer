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

namespace mod_tarsuscoach\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_tarsuscoach\local\activity\scope;
use mod_tarsuscoach\local\client\client_factory;
use mod_tarsuscoach\local\support\config;
use mod_tarsuscoach\local\support\platform;
use mod_tarsuscoach\local\support\pseudonymizer;

/**
 * What mod_tarsuscoach stores about learners and what it sends to Tarsus.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * The stored and sent personal data.
     *
     * @param collection $collection The collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tarsuscoach_progress', [
            'userid' => 'privacy:metadata:tarsuscoach_progress:userid',
            'earnedxp' => 'privacy:metadata:tarsuscoach_progress:earnedxp',
            'earnedunits' => 'privacy:metadata:tarsuscoach_progress:earnedunits',
            'overallprogress' => 'privacy:metadata:tarsuscoach_progress:overallprogress',
            'timemodified' => 'privacy:metadata:tarsuscoach_progress:timemodified',
        ], 'privacy:metadata:tarsuscoach_progress');
        $collection->add_database_table('tarsuscoach_usage', [
            'userid' => 'privacy:metadata:tarsuscoach_usage:userid',
            'operation' => 'privacy:metadata:tarsuscoach_usage:operation',
            'credits' => 'privacy:metadata:tarsuscoach_usage:credits',
            'timecreated' => 'privacy:metadata:tarsuscoach_usage:timecreated',
        ], 'privacy:metadata:tarsuscoach_usage');
        $collection->add_external_location_link('tarsuscoach', [
            'pseudonym' => 'privacy:metadata:tarsuscoach:pseudonym',
            'fullname' => 'privacy:metadata:tarsuscoach:fullname',
            'question' => 'privacy:metadata:tarsuscoach:question',
            'voice' => 'privacy:metadata:tarsuscoach:voice',
            'language' => 'privacy:metadata:tarsuscoach:language',
        ], 'privacy:metadata:tarsuscoach');
        return $collection;
    }

    /**
     * The Tarsus activities with data of a user.
     *
     * @param int $userid The user.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE EXISTS (SELECT 1 FROM {tarsuscoach_progress} p WHERE p.tarsuscoachid = cm.instance AND p.userid = :userid1)
                    OR EXISTS (SELECT 1 FROM {tarsuscoach_usage} u WHERE u.tarsuscoachid = cm.instance AND u.userid = :userid2)";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE, 'modname' => 'tarsuscoach', 'userid1' => $userid, 'userid2' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * The users with data in an activity.
     *
     * @param userlist $userlist The list to add to.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $params = ['cmid' => $context->instanceid, 'modname' => 'tarsuscoach'];
        foreach (['tarsuscoach_progress', 'tarsuscoach_usage'] as $table) {
            $sql = "SELECT t.userid
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                      JOIN {" . $table . "} t ON t.tarsuscoachid = cm.instance
                     WHERE cm.id = :cmid";
            $userlist->add_from_sql('userid', $sql, $params);
        }
    }

    /**
     * Export a user's figures and requests.
     *
     * @param approved_contextlist $contextlist The approved activities.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $db = platform::db();
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            $cm = self::cm($context);
            if (!$cm) {
                continue;
            }
            $data = helper::get_context_data($context, $user);
            $row = $db->get_record('tarsuscoach_progress', ['tarsuscoachid' => $cm->instance, 'userid' => $user->id]);
            if ($row) {
                $data->figures = (object) [
                    'experiencepoints' => (float) $row->earnedxp . ' / ' . (float) $row->totalxp,
                    'learningunits' => (float) $row->earnedunits . ' / ' . (float) $row->totalunits,
                    'progress' => (float) $row->overallprogress . ' %',
                    'updated' => transform::datetime($row->timemodified),
                ];
            }
            $requests = [];
            foreach ($db->get_records('tarsuscoach_usage', ['tarsuscoachid' => $cm->instance, 'userid' => $user->id], 'timecreated') as $use) {
                $requests[] = (object) [
                    'request' => (string) $use->operation,
                    'credits' => (int) $use->credits,
                    'time' => transform::datetime($use->timecreated),
                ];
            }
            if ($requests) {
                $data->requests = $requests;
            }
            writer::with_context($context)->export_data([], $data);
        }
    }

    /**
     * Delete everyone's data in an activity.
     *
     * @param \context $context The activity's context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        $cm = self::cm($context);
        if (!$cm) {
            return;
        }
        $db = platform::db();
        foreach ($db->get_fieldset_select('tarsuscoach_progress', 'userid', 'tarsuscoachid = ?', [$cm->instance]) as $userid) {
            self::erase((int) $cm->instance, (int) $userid);
        }
        $db->delete_records('tarsuscoach_progress', ['tarsuscoachid' => $cm->instance]);
        $db->delete_records('tarsuscoach_usage', ['tarsuscoachid' => $cm->instance]);
    }

    /**
     * Delete one user's data in the approved activities.
     *
     * @param approved_contextlist $contextlist The approved activities.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $db = platform::db();
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $cm = self::cm($context);
            if (!$cm) {
                continue;
            }
            self::erase((int) $cm->instance, $userid);
            $db->delete_records('tarsuscoach_progress', ['tarsuscoachid' => $cm->instance, 'userid' => $userid]);
            $db->delete_records('tarsuscoach_usage', ['tarsuscoachid' => $cm->instance, 'userid' => $userid]);
        }
    }

    /**
     * Delete several users' data in one activity.
     *
     * @param approved_userlist $userlist The approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $cm = self::cm($userlist->get_context());
        if (!$cm) {
            return;
        }
        $db = platform::db();
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        foreach ($userids as $userid) {
            self::erase((int) $cm->instance, (int) $userid);
        }
        [$insql, $params] = $db->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['objid'] = $cm->instance;
        $db->delete_records_select('tarsuscoach_progress', 'tarsuscoachid = :objid AND userid ' . $insql, $params);
        $db->delete_records_select('tarsuscoach_usage', 'tarsuscoachid = :objid AND userid ' . $insql, $params);
    }

    /**
     * The Tarsus course module of a context, null for other contexts.
     *
     * @param \context $context The context.
     * @return \stdClass|null
     */
    private static function cm(\context $context): ?\stdClass {
        if (!$context instanceof \context_module) {
            return null;
        }
        $cm = get_coursemodule_from_id('tarsuscoach', $context->instanceid, 0, false, IGNORE_MISSING);
        return $cm ?: null;
    }

    /**
     * Ask the brain to erase the learner's conversation in an activity. Best
     * effort: the local data goes in any case.
     *
     * @param int $objid The activity.
     * @param int $userid The learner.
     */
    private static function erase(int $objid, int $userid): void {
        if (config::mock_mode() || !config::is_connected()) {
            return;
        }
        try {
            client_factory::get_client()->reset_conversation(scope::conversation_context_id($objid), pseudonymizer::pseudonymize($userid));
        } catch (\Throwable $e) {
            platform::log('could not erase a conversation in the brain: ' . $e->getMessage());
        }
    }
}
