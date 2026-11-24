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

namespace mod_tarsus\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_tarsus\local\Activity\Scope;
use mod_tarsus\local\Client\ClientFactory;
use mod_tarsus\local\Support\Config;
use mod_tarsus\local\Support\Platform;
use mod_tarsus\local\Support\Pseudonymizer;

/**
 * What mod_tarsus stores about learners and what it sends to Tarsus.
 *
 * @package    mod_tarsus
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
        $collection->add_database_table('tarsus_prg', [
            'usr_id' => 'privacy:metadata:tarsus_prg:usr_id',
            'earned_xp' => 'privacy:metadata:tarsus_prg:earned_xp',
            'earned_units' => 'privacy:metadata:tarsus_prg:earned_units',
            'overall_prg' => 'privacy:metadata:tarsus_prg:overall_prg',
            'modified' => 'privacy:metadata:tarsus_prg:modified',
        ], 'privacy:metadata:tarsus_prg');
        $collection->add_database_table('tarsus_use', [
            'usr_id' => 'privacy:metadata:tarsus_use:usr_id',
            'op_name' => 'privacy:metadata:tarsus_use:op_name',
            'credits' => 'privacy:metadata:tarsus_use:credits',
            'created' => 'privacy:metadata:tarsus_use:created',
        ], 'privacy:metadata:tarsus_use');
        $collection->add_external_location_link('tarsus', [
            'pseudonym' => 'privacy:metadata:tarsus:pseudonym',
            'fullname' => 'privacy:metadata:tarsus:fullname',
            'question' => 'privacy:metadata:tarsus:question',
            'voice' => 'privacy:metadata:tarsus:voice',
            'language' => 'privacy:metadata:tarsus:language',
        ], 'privacy:metadata:tarsus');
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
                 WHERE EXISTS (SELECT 1 FROM {tarsus_prg} p WHERE p.obj_id = cm.instance AND p.usr_id = :userid1)
                    OR EXISTS (SELECT 1 FROM {tarsus_use} u WHERE u.obj_id = cm.instance AND u.usr_id = :userid2)";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE, 'modname' => 'tarsus', 'userid1' => $userid, 'userid2' => $userid,
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
        $params = ['cmid' => $context->instanceid, 'modname' => 'tarsus'];
        foreach (['tarsus_prg', 'tarsus_use'] as $table) {
            $sql = "SELECT t.usr_id
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                      JOIN {" . $table . "} t ON t.obj_id = cm.instance
                     WHERE cm.id = :cmid";
            $userlist->add_from_sql('usr_id', $sql, $params);
        }
    }

    /**
     * Export a user's figures and requests.
     *
     * @param approved_contextlist $contextlist The approved activities.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $db = Platform::db();
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            $cm = self::cm($context);
            if (!$cm) {
                continue;
            }
            $data = helper::get_context_data($context, $user);
            $row = $db->get_record('tarsus_prg', ['obj_id' => $cm->instance, 'usr_id' => $user->id]);
            if ($row) {
                $data->figures = (object) [
                    'experiencepoints' => (float) $row->earned_xp . ' / ' . (float) $row->total_xp,
                    'learningunits' => (float) $row->earned_units . ' / ' . (float) $row->total_units,
                    'progress' => (float) $row->overall_prg . ' %',
                    'updated' => transform::datetime($row->modified),
                ];
            }
            $requests = [];
            foreach ($db->get_records('tarsus_use', ['obj_id' => $cm->instance, 'usr_id' => $user->id], 'created') as $use) {
                $requests[] = (object) [
                    'request' => (string) $use->op_name,
                    'credits' => (int) $use->credits,
                    'time' => transform::datetime($use->created),
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
        $db = Platform::db();
        foreach ($db->get_fieldset_select('tarsus_prg', 'usr_id', 'obj_id = ?', [$cm->instance]) as $userid) {
            self::erase((int) $cm->instance, (int) $userid);
        }
        $db->delete_records('tarsus_prg', ['obj_id' => $cm->instance]);
        $db->delete_records('tarsus_use', ['obj_id' => $cm->instance]);
    }

    /**
     * Delete one user's data in the approved activities.
     *
     * @param approved_contextlist $contextlist The approved activities.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $db = Platform::db();
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $cm = self::cm($context);
            if (!$cm) {
                continue;
            }
            self::erase((int) $cm->instance, $userid);
            $db->delete_records('tarsus_prg', ['obj_id' => $cm->instance, 'usr_id' => $userid]);
            $db->delete_records('tarsus_use', ['obj_id' => $cm->instance, 'usr_id' => $userid]);
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
        $db = Platform::db();
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        foreach ($userids as $userid) {
            self::erase((int) $cm->instance, (int) $userid);
        }
        [$insql, $params] = $db->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['objid'] = $cm->instance;
        $db->delete_records_select('tarsus_prg', 'obj_id = :objid AND usr_id ' . $insql, $params);
        $db->delete_records_select('tarsus_use', 'obj_id = :objid AND usr_id ' . $insql, $params);
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
        $cm = get_coursemodule_from_id('tarsus', $context->instanceid, 0, false, IGNORE_MISSING);
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
        if (Config::mockMode() || !Config::isConnected()) {
            return;
        }
        try {
            ClientFactory::getClient()->resetConversation(Scope::conversationContextId($objid), Pseudonymizer::pseudonymize($userid));
        } catch (\Throwable $e) {
            Platform::log('could not erase a conversation in the brain: ' . $e->getMessage());
        }
    }
}
