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

namespace mod_tarsus\local\Page;

use mod_tarsus\local\Activity\Settings;
use mod_tarsus\local\Output\Icons;
use mod_tarsus\local\Service\LearnerApi;
use mod_tarsus\local\Service\ManagerApi;
use mod_tarsus\local\Service\TeacherApi;
use mod_tarsus\local\Support\Config;
use mod_tarsus\local\Support\Platform;
use mod_tarsus\local\Support\Plan;

/**
 * What the coach page and its background requests share: the texts the
 * browser needs, the request addresses and the configuration of the coach.
 *
 * @package    mod_tarsus
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class Coach {
    /** Longest spoken question accepted, in seconds. */
    public const VOICE_MAX_SECONDS = 180;

    /** Materials listed per page of the teacher's list (one brain call per page). */
    public const PAGE_SIZE = 10;

    /** @var string[] Requests of the coach that only need the right to use it. */
    public const LEARNER_ACTIONS = [
        'feed', 'ask', 'askVoice', 'practiceNext', 'practiceCheck', 'learnNext', 'audio', 'discuss', 'references',
    ];

    /** @var string[] Requests that need the right to manage the activity. */
    public const TEACHER_ACTIONS = [
        'stats', 'history', 'analytics', 'settings', 'saveSettings', 'action', 'include', 'sync',
    ];

    /** @var string[] Interface texts of the coach. */
    public const CHAT_STRINGS = [
        'practice_start', 'practice_next', 'practice_check', 'practice_answer_placeholder',
        'practice_variable_placeholder', 'practice_score', 'practice_correct_value', 'xp', 'learning_units', 'learn_slide_of',
        'learn_previous', 'learn_next', 'learn_image_enlarge', 'learn_image_close', 'learn_audio', 'learn_audio_play',
        'learn_audio_pause', 'learn_ask_about', 'learn_ask_about_unit', 'chat_reply_about', 'chat_reply_clear', 'learn_complete_title',
        'frame_title', 'frame_enlarge', 'learn_motivation_title', 'mode_build', 'mode_test', 'mode_ask', 'mode_discuss',
        'unexpected_error', 'session_lost', 'grade_chip', 'grade_title',
        'voice_to_voice', 'voice_to_text', 'voice_start', 'voice_stop', 'voice_pause', 'voice_resume', 'voice_discard',
        'voice_play', 'voice_recording', 'voice_paused', 'voice_ready', 'voice_question', 'voice_transcribing',
        'voice_transcript_note', 'voice_limit', 'voice_unsupported', 'mic_denied', 'mic_notfound', 'mic_failed',
        'live_start', 'live_new', 'live_intro', 'live_expired', 'live_end_confirm', 'live_title', 'live_starting',
        'sources_show', 'sources_title', 'sources_count', 'sources_none', 'sources_open', 'sources_transcript',
        'source_type_text', 'source_type_table', 'source_type_image', 'source_type_audio', 'source_type_video',
        'source_type_question', 'source_type_form', 'source_type_other', 'panel_close', 'loading',
        'proc_ask_classify', 'proc_ask_search', 'proc_ask_media', 'proc_ask_select', 'proc_ask_cube',
        'proc_ask_dqr', 'proc_ask_compose', 'proc_ask_verify', 'proc_ask_polish', 'proc_ask_transcribe',
        'proc_practice_cube_create', 'proc_practice_baseline', 'proc_practice_evaluate',
        'proc_practice_compare', 'proc_practice_surprise', 'proc_practice_reestimate',
        'proc_practice_align', 'proc_practice_cells', 'proc_practice_history', 'proc_practice_action',
        'proc_practice_taxonomy', 'proc_practice_difficulty', 'proc_practice_format',
        'proc_practice_generate', 'proc_practice_operators', 'proc_practice_options',
        'proc_practice_solution', 'proc_practice_polish',
        'proc_learn_path_create', 'proc_learn_progress', 'proc_learn_objectives', 'proc_learn_cells',
        'proc_learn_next', 'proc_learn_passages', 'proc_learn_dqr', 'proc_learn_split',
        'proc_learn_examples', 'proc_learn_link', 'proc_learn_check', 'proc_learn_path_update',
        'proc_learn_polish',
    ];

    /** @var string[] Interface texts of the teacher's panels. */
    public const TEACHER_STRINGS = [
        'panel_settings', 'panel_stats', 'panel_history', 'settings_save', 'settings_saved', 'settings_saving',
        'settings_open_full', 'form_invalid', 'stats_learners', 'stats_avg_grade', 'stats_completed', 'stats_avg_progress',
        'stats_distribution', 'stats_distribution_help', 'stats_status', 'stats_status_help', 'stats_col_name',
        'stats_col_grade', 'stats_col_progress', 'stats_col_status', 'stats_col_build', 'stats_col_test', 'stats_col_last',
        'stats_history', 'stats_group_all', 'stats_group', 'stats_export', 'stats_none', 'stats_count_learners',
        'lp_completed', 'lp_in_progress', 'lp_not_attempted', 'history_readonly', 'history_pick', 'history_growth',
        'history_xp', 'history_units', 'history_empty', 'history_more', 'history_back', 'history_of',
    ];

    /** @var string[] Interface texts of the materials list. */
    public const MATERIAL_STRINGS = [
        'confirmremove_body', 'unexpected_error', 'session_lost', 'sync_done', 'bulk_done',
        'include_not_indexed', 'action_index', 'action_reindex', 'action_remove', 'action_retry',
    ];

    /**
     * Request addresses of the coach (or the materials list), each carrying
     * the session key.
     *
     * @param int $cmid The course module.
     * @param string[] $actions Key in the browser => request.
     * @return array
     */
    public static function endpoints(int $cmid, array $actions): array {
        $endpoints = [];
        foreach ($actions as $key => $action) {
            $endpoints[$key] = (new \moodle_url('/mod/tarsus/ajax.php', [
                'id' => $cmid, 'action' => $action, 'sesskey' => sesskey(),
            ]))->out(false);
        }
        return $endpoints;
    }

    /**
     * The texts of the given identifiers, by identifier.
     *
     * @param string[] $keys The identifiers.
     * @return array
     */
    public static function strings(array $keys): array {
        $strings = [];
        foreach ($keys as $key) {
            $strings[$key] = Platform::txt($key);
        }
        return $strings;
    }

    /**
     * The configuration of the coach in the browser.
     *
     * @param \stdClass $cm The course module.
     * @param Settings $settings The activity's settings.
     * @param bool $teacher Whether the user manages the activity.
     * @return array
     */
    public static function config(\stdClass $cm, Settings $settings, bool $teacher): array {
        global $USER, $PAGE;
        $robotavatar = Config::robotAvatar();
        if ($robotavatar === '') {
            $robotavatar = Platform::assetUrl('images/robot.svg');
        }
        $picture = new \user_picture($USER);
        $picture->size = 100;
        $useravatar = $picture->get_url($PAGE)->out(false);

        $modes = $settings->availableModes();
        return [
            'kind' => 'chat',
            'endpoints' => self::endpoints((int) $cm->id, [
                'feed' => 'feed',
                'ask' => 'ask',
                'askVoice' => 'askVoice',
                'practiceNext' => 'practiceNext',
                'practiceCheck' => 'practiceCheck',
                'learnNext' => 'learnNext',
                'audio' => 'audio',
                'discuss' => 'discuss',
                'references' => 'references',
            ]),
            // The teacher's panels: settings, evaluation, history.
            'teacher' => $teacher ? [
                'endpoints' => self::endpoints((int) $cm->id, [
                    'stats' => 'stats',
                    'history' => 'history',
                    'analytics' => 'analytics',
                    'settings' => 'settings',
                    'saveSettings' => 'saveSettings',
                ]),
                'exportUrl' => (new \moodle_url('/mod/tarsus/export.php', ['id' => $cm->id]))->out(false),
                'settingsUrl' => (new \moodle_url('/course/modedit.php', ['update' => $cm->id, 'return' => 1]))->out(false),
            ] : null,
            'modes' => $modes,
            // Narration of learning units is part of Build Knowledge and
            // Pay as you grow.
            'audio' => Plan::allowsAudio(),
            // Spoken questions in Ask a question (package and switch).
            'voice' => $settings->voiceInputActive(),
            'voiceMaxSeconds' => self::VOICE_MAX_SECONDS,
            'voiceMaxBytes' => Platform::uploadLimit(),
            // "View sources" at the answers.
            'references' => $settings->referencesenabled,
            // The result in percent: the experience points' share while
            // Test knowledge is active, the learning units' otherwise.
            'gradeBasis' => $settings->modeActive('test') ? 'xp' : 'units',
            'interactive' => $settings->effectiveInteraction() === Plan::INTERACTION_AI,
            'pageSize' => LearnerApi::FEED_PAGE_SIZE,
            'userAvatar' => $useravatar,
            'robotAvatar' => $robotavatar,
            'robotName' => Config::robotName(),
            'demo' => Config::mockMode(),
            'images' => [
                'welcome' => Platform::assetUrl('images/welcome.png'),
                'objectives' => Platform::assetUrl('images/objectives.png'),
                'motivation' => Platform::assetUrl('images/motivation.png'),
                'success' => Platform::assetUrl('images/success.png'),
            ],
            'icons' => Icons::PATHS,
            'strings' => self::strings(array_merge(self::CHAT_STRINGS, $teacher ? self::TEACHER_STRINGS : [])),
        ];
    }

    /**
     * The learner's side of an activity.
     *
     * @param \stdClass $tarsus The activity.
     * @param Settings $settings Its settings.
     * @return LearnerApi
     */
    public static function learnerApi(\stdClass $tarsus, Settings $settings): LearnerApi {
        return new LearnerApi((int) $tarsus->id, (string) $tarsus->name, (string) ($tarsus->intro ?? ''), $settings, (int) $tarsus->course);
    }

    /**
     * The teacher's panels of an activity.
     *
     * @param \stdClass $tarsus The activity.
     * @param Settings $settings Its settings.
     * @return TeacherApi
     */
    public static function teacherApi(\stdClass $tarsus, Settings $settings): TeacherApi {
        return new TeacherApi((int) $tarsus->id, (int) $tarsus->course, $settings);
    }

    /**
     * The materials list of an activity.
     *
     * @param \stdClass $tarsus The activity.
     * @return ManagerApi
     */
    public static function managerApi(\stdClass $tarsus): ManagerApi {
        return new ManagerApi((int) $tarsus->id, (int) $tarsus->course);
    }
}
