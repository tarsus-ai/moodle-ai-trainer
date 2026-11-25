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

/**
 * The external functions of the coach and of the materials list, called by its pages (core/ajax).
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_tarsuscoach_get_feed' => [
        'classname' => 'mod_tarsuscoach\\external\\get_feed',
        'description' => 'The learner\'s feed in the coach: answers, questions and learning units, newest first.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:view',
    ],
    'mod_tarsuscoach_ask' => [
        'classname' => 'mod_tarsuscoach\\external\\ask',
        'description' => 'A typed question: the brain\'s answer, grounded in the activity\'s material.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:view',
    ],
    'mod_tarsuscoach_ask_voice' => [
        'classname' => 'mod_tarsuscoach\\external\\ask_voice',
        'description' => 'A spoken question: the recording is turned into text and answered like a typed one.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:view',
    ],
    'mod_tarsuscoach_next_question' => [
        'classname' => 'mod_tarsuscoach\\external\\next_question',
        'description' => 'The next question in Test knowledge.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:view',
    ],
    'mod_tarsuscoach_check_answer' => [
        'classname' => 'mod_tarsuscoach\\external\\check_answer',
        'description' => 'The learner\'s answer to a question, evaluated by the brain.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:view',
    ],
    'mod_tarsuscoach_next_learning_unit' => [
        'classname' => 'mod_tarsuscoach\\external\\next_learning_unit',
        'description' => 'The next learning unit in Build knowledge.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:view',
    ],
    'mod_tarsuscoach_get_audio' => [
        'classname' => 'mod_tarsuscoach\\external\\get_audio',
        'description' => 'The narration of a learning unit, made by the brain the first time it is asked for.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:view',
    ],
    'mod_tarsuscoach_start_call' => [
        'classname' => 'mod_tarsuscoach\\external\\start_call',
        'description' => 'A live interaction: the page of the call, from the brain.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:view',
    ],
    'mod_tarsuscoach_get_references' => [
        'classname' => 'mod_tarsuscoach\\external\\get_references',
        'description' => 'The sources an answer of the brain is based on.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:view',
    ],
    'mod_tarsuscoach_push_progress' => [
        'classname' => 'mod_tarsuscoach\\external\\push_progress',
        'description' => 'The grade and completion a request that waited for Tarsus could not write.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:view',
    ],
    'mod_tarsuscoach_get_statistics' => [
        'classname' => 'mod_tarsuscoach\\external\\get_statistics',
        'description' => 'The teacher\'s evaluation of the activity: every learner\'s result, progress and completion.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:manage',
    ],
    'mod_tarsuscoach_get_history' => [
        'classname' => 'mod_tarsuscoach\\external\\get_history',
        'description' => 'One learner\'s history in the activity, for the teacher.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:manage',
    ],
    'mod_tarsuscoach_get_analytics' => [
        'classname' => 'mod_tarsuscoach\\external\\get_analytics',
        'description' => 'One learner\'s growth in experience points and learning units, for the teacher.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:manage',
    ],
    'mod_tarsuscoach_get_settings' => [
        'classname' => 'mod_tarsuscoach\\external\\get_settings',
        'description' => 'The activity\'s settings as the teacher\'s panel shows them.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:manage',
    ],
    'mod_tarsuscoach_save_settings' => [
        'classname' => 'mod_tarsuscoach\\external\\save_settings',
        'description' => 'The settings the teacher changed in the panel.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:manage',
    ],
    'mod_tarsuscoach_material_action' => [
        'classname' => 'mod_tarsuscoach\\external\\material_action',
        'description' => 'An action on one piece of material: index, re-index, retry or remove.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:manage',
    ],
    'mod_tarsuscoach_set_material_included' => [
        'classname' => 'mod_tarsuscoach\\external\\set_material_included',
        'description' => 'Whether the activity may use a piece of material.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:manage',
    ],
    'mod_tarsuscoach_sync_materials' => [
        'classname' => 'mod_tarsuscoach\\external\\sync_materials',
        'description' => 'The brain\'s current state of the listed material.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/tarsuscoach:manage',
    ],
];
