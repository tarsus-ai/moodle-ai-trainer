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

namespace mod_tarsuscoach\local\page;

use mod_tarsuscoach\local\activity\settings;
use mod_tarsuscoach\local\output\icons;
use mod_tarsuscoach\local\service\learner_api;
use mod_tarsuscoach\local\service\manager_api;
use mod_tarsuscoach\local\service\teacher_api;
use mod_tarsuscoach\local\support\config;
use mod_tarsuscoach\local\support\platform;
use mod_tarsuscoach\local\support\plan;

/**
 * What the coach page and its background requests share: the texts the
 * browser needs, the request addresses and the configuration of the coach.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coach {
    /** Longest spoken question accepted, in seconds. */
    public const VOICE_MAX_SECONDS = 180;

    /** Materials listed per page of the teacher's list (one brain call per page). */
    public const PAGE_SIZE = 10;

    /** @var string[] Interface texts of the coach. */
    public const CHAT_STRINGS = [
        'a11y_you_asked', 'a11y_question', 'a11y_learning_unit', 'a11y_working', 'a11y_answered',
        'a11y_correct', 'a11y_incorrect', 'a11y_partial',
        'a11y_your_answer_correct', 'a11y_your_answer_incorrect', 'a11y_not_correct', 'a11y_answer_missing',
        'a11y_mode_ask', 'a11y_mode_next', 'a11y_mode_discuss',
        'practice_start', 'practice_next', 'practice_check', 'practice_answer_placeholder',
        'practice_variable_placeholder', 'practice_score', 'practice_correct_value', 'xp', 'learning_units', 'learn_slide_of',
        'learn_previous', 'learn_next', 'learn_image_enlarge', 'learn_image_close', 'learn_audio', 'learn_audio_play',
        'learn_audio_pause', 'learn_ask_about', 'learn_ask_about_unit',
        'chat_reply_about', 'chat_reply_clear', 'learn_complete_title',
        'frame_title', 'frame_enlarge', 'learn_motivation_title', 'mode_build', 'mode_test', 'mode_ask', 'mode_discuss',
        'unexpected_error', 'session_lost', 'grade_chip', 'grade_title',
        'voice_to_voice', 'voice_to_text', 'voice_start', 'voice_stop', 'voice_pause', 'voice_resume', 'voice_discard',
        'voice_play', 'voice_recording', 'voice_paused', 'voice_ready', 'voice_question', 'voice_transcribing',
        'voice_transcript_note', 'voice_limit', 'voice_unsupported', 'mic_denied', 'mic_notfound', 'mic_failed',
        'live_start', 'live_new', 'live_intro', 'live_expired', 'live_end', 'live_end_confirm', 'live_end_title', 'live_title',
        'live_starting',
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
        'confirmremove_title', 'confirmremove_body', 'unexpected_error', 'session_lost', 'sync_done', 'bulk_done',
        'include_not_indexed', 'action_index', 'action_reindex', 'action_remove', 'action_retry',
    ];

    /**
     * The external functions of the coach (or the materials list) by the
     * name the browser uses for them (core/ajax).
     *
     * @param string[] $actions Key in the browser => function, without the component prefix.
     * @return array
     */
    public static function endpoints(array $actions): array {
        $endpoints = [];
        foreach ($actions as $key => $function) {
            $endpoints[$key] = 'mod_tarsuscoach_' . $function;
        }
        return $endpoints;
    }

    /**
     * The texts of the given identifiers, by identifier, for the browser:
     * the script fills {name} placeholders where Moodle writes {$a->name}.
     *
     * @param string[] $keys The identifiers.
     * @return array
     */
    public static function strings(array $keys): array {
        $strings = [];
        foreach ($keys as $key) {
            $strings[$key] = str_replace('{$a->', '{', platform::txt($key));
        }
        return $strings;
    }

    /**
     * The configuration of the coach in the browser.
     *
     * @param \stdClass $cm The course module.
     * @param settings $settings The activity's settings.
     * @param bool $teacher Whether the user manages the activity.
     * @return array
     */
    public static function config(\stdClass $cm, settings $settings, bool $teacher): array {
        global $USER, $PAGE;
        $robotavatar = config::robot_avatar();
        if ($robotavatar === '') {
            $robotavatar = platform::asset_url('images/robot.svg');
        }
        $picture = new \user_picture($USER);
        $picture->size = 100;
        $useravatar = $picture->get_url($PAGE)->out(false);

        $modes = $settings->available_modes();
        return [
            'kind' => 'chat',
            'cmid' => (int) $cm->id,
            'endpoints' => self::endpoints([
                'feed' => 'get_feed',
                'ask' => 'ask',
                'askVoice' => 'ask_voice',
                'practiceNext' => 'next_question',
                'practiceCheck' => 'check_answer',
                'learnNext' => 'next_learning_unit',
                'audio' => 'get_audio',
                'discuss' => 'start_call',
                'references' => 'get_references',
                'progress' => 'push_progress',
            ]),
            // The teacher's panels: settings, evaluation, history.
            'teacher' => $teacher ? [
                'endpoints' => self::endpoints([
                    'stats' => 'get_statistics',
                    'history' => 'get_history',
                    'analytics' => 'get_analytics',
                    'settings' => 'get_settings',
                    'saveSettings' => 'save_settings',
                ]),
                'exportUrl' => (new \moodle_url('/mod/tarsuscoach/export.php', ['id' => $cm->id]))->out(false),
                'settingsUrl' => (new \moodle_url('/course/modedit.php', ['update' => $cm->id, 'return' => 1]))->out(false),
            ] : null,
            'modes' => $modes,
            // Narration of learning units is part of Build Knowledge and
            // Pay as you grow.
            'audio' => plan::allows_audio(),
            // Spoken questions in Ask a question (package and switch).
            'voice' => $settings->voice_input_active(),
            'voiceMaxSeconds' => self::VOICE_MAX_SECONDS,
            'voiceMaxBytes' => platform::voice_limit(),
            // The "View sources" button at the answers.
            'references' => $settings->referencesenabled,
            // The result in percent: the experience points' share while
            // Test knowledge is active, the learning units' otherwise.
            'gradeBasis' => $settings->mode_active('test') ? 'xp' : 'units',
            'interactive' => $settings->effective_interaction() === plan::INTERACTION_AI,
            'pageSize' => learner_api::FEED_PAGE_SIZE,
            'userAvatar' => $useravatar,
            'robotAvatar' => $robotavatar,
            'robotName' => config::robot_name(),
            'demo' => config::mock_mode(),
            'images' => [
                'welcome' => platform::asset_url('images/welcome.png'),
                'objectives' => platform::asset_url('images/objectives.png'),
                'motivation' => platform::asset_url('images/motivation.png'),
                'success' => platform::asset_url('images/success.png'),
            ],
            'icons' => icons::PATHS,
            'strings' => self::strings(array_merge(self::CHAT_STRINGS, $teacher ? self::TEACHER_STRINGS : [])),
        ];
    }

    /**
     * The learner's side of an activity.
     *
     * @param \stdClass $tarsus The activity.
     * @param settings $settings Its settings.
     * @return learner_api
     */
    public static function learner_api(\stdClass $tarsus, settings $settings): learner_api {
        return new learner_api(
            (int) $tarsus->id,
            (string) $tarsus->name,
            (string) ($tarsus->intro ?? ''),
            $settings,
            (int) $tarsus->course
        );
    }

    /**
     * The teacher's panels of an activity.
     *
     * @param \stdClass $tarsus The activity.
     * @param settings $settings Its settings.
     * @return teacher_api
     */
    public static function teacher_api(\stdClass $tarsus, settings $settings): teacher_api {
        return new teacher_api((int) $tarsus->id, (int) $tarsus->course, $settings);
    }

    /**
     * The materials list of an activity.
     *
     * @param \stdClass $tarsus The activity.
     * @return manager_api
     */
    public static function manager_api(\stdClass $tarsus): manager_api {
        return new manager_api((int) $tarsus->id, (int) $tarsus->course);
    }
}
