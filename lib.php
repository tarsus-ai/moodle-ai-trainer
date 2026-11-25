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
 * Moodle's interface to the Tarsus activity.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_tarsuscoach\local\activity\scoring;
use mod_tarsuscoach\local\activity\settings;
use mod_tarsuscoach\local\index\inclusions;
use mod_tarsuscoach\local\service\settings_api;

/** The settings the activity form shares with the coach's settings panel. */
const TARSUSCOACH_FORM_SETTINGS = [
    'askenabled', 'testenabled', 'buildenabled', 'discussenabled', 'interactiontype', 'voiceinput',
    'referencesenabled', 'visualaccessibility', 'thinkinglevel', 'dqr', 'industry', 'anticheat',
    'conversationstyle', 'taxonomylevels', 'qtypes1', 'qtypes2', 'qtypes3', 'operators', 'learntempo',
    'learningmethod', 'gifenabled',
];

/**
 * What the activity supports.
 *
 * @param string $feature A FEATURE_xx constant.
 * @return mixed
 */
function tarsuscoach_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_OUTCOMES:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_PURPOSE:
            // "Interactive content" exists from Moodle 4.4 on; before, "content".
            return defined('MOD_PURPOSE_INTERACTIVECONTENT') ? constant('MOD_PURPOSE_INTERACTIVECONTENT') : MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * The activity settings of the form, in the shape settings_api::apply() reads.
 *
 * @param stdClass $data The form data.
 * @return array
 */
function tarsuscoach_form_values(stdClass $data): array {
    $values = [];
    foreach (TARSUSCOACH_FORM_SETTINGS as $name) {
        if (in_array($name, ['taxonomylevels', 'qtypes1', 'qtypes2', 'qtypes3'], true)) {
            // Checkbox groups arrive as name => 1 for each ticked option.
            $ticked = [];
            foreach ((array) ($data->$name ?? []) as $option => $on) {
                if (!empty($on)) {
                    $ticked[] = (string) $option;
                }
            }
            $values[$name] = $ticked;
        } else if (
            in_array($name, ['askenabled', 'testenabled', 'buildenabled', 'discussenabled', 'voiceinput',
                'referencesenabled', 'visualaccessibility', 'anticheat', 'gifenabled'], true)
        ) {
            $values[$name] = !empty($data->$name);
        } else if (property_exists($data, $name)) {
            $values[$name] = $data->$name;
        }
    }
    // The form's post-processing already stored 0 for an unticked rule; a
    // form without the completion section leaves the setting as it is.
    if (property_exists($data, 'completionprogress')) {
        $values['completionprogress'] = (int) $data->completionprogress;
    }
    return $values;
}

/**
 * Store a new Tarsus activity.
 *
 * @param stdClass $data The form data.
 * @param mod_tarsuscoach_mod_form|null $mform The form.
 * @return int The new activity's id.
 */
function tarsuscoach_add_instance(stdClass $data, $mform = null): int {
    global $DB;
    $now = time();
    $record = (object) [
        'course' => (int) $data->course,
        'name' => $data->name,
        'intro' => $data->intro ?? '',
        'introformat' => $data->introformat ?? FORMAT_HTML,
        'grade' => (int) ($data->grade ?? 100),
        'timecreated' => $now,
        'timemodified' => $now,
    ];
    $data->id = $DB->insert_record('tarsuscoach', $record);

    $settings = new settings((int) $data->id);
    (new settings_api($settings))->apply(tarsuscoach_form_values($data));
    $settings->save();

    $tarsus = $DB->get_record('tarsuscoach', ['id' => $data->id], '*', MUST_EXIST);
    $tarsus->cmidnumber = $data->cmidnumber ?? '';
    tarsuscoach_grade_item_update($tarsus);
    if (!empty($data->completionexpected)) {
        \core_completion\api::update_completion_date_event($data->coursemodule, 'tarsuscoach', $data->id, $data->completionexpected);
    }
    return (int) $data->id;
}

/**
 * Store the edited settings of a Tarsus activity.
 *
 * @param stdClass $data The form data.
 * @param mod_tarsuscoach_mod_form|null $mform The form.
 * @return bool
 */
function tarsuscoach_update_instance(stdClass $data, $mform = null): bool {
    global $DB;
    $id = (int) $data->instance;
    $DB->update_record('tarsuscoach', (object) [
        'id' => $id,
        'name' => $data->name,
        'intro' => $data->intro ?? '',
        'introformat' => $data->introformat ?? FORMAT_HTML,
        'grade' => (int) ($data->grade ?? 100),
        'timemodified' => time(),
    ]);

    $settings = settings::load($id);
    (new settings_api($settings))->apply(tarsuscoach_form_values($data));
    $settings->save();

    $tarsus = $DB->get_record('tarsuscoach', ['id' => $id], '*', MUST_EXIST);
    $tarsus->cmidnumber = $data->cmidnumber ?? '';
    tarsuscoach_grade_item_update($tarsus);
    // A changed threshold or mode changes every learner's grade and status.
    scoring::push_all($id);
    \core_completion\api::update_completion_date_event($data->coursemodule, 'tarsuscoach', $id, $data->completionexpected ?? null);
    return true;
}

/**
 * Delete a Tarsus activity and what only it uses. The course material in
 * the brain stays: other Tarsus activities of the course may use it, and
 * the scheduled task withdraws it when the material itself goes.
 *
 * @param int $id The activity.
 * @return bool
 */
function tarsuscoach_delete_instance($id): bool {
    global $DB;
    $tarsus = $DB->get_record('tarsuscoach', ['id' => $id]);
    if (!$tarsus) {
        return false;
    }
    inclusions::forget_object((int) $id);
    scoring::forget_object((int) $id);
    $DB->delete_records('tarsuscoach_usage', ['tarsuscoachid' => $id]);
    tarsuscoach_grade_item_delete($tarsus);
    $DB->delete_records('tarsuscoach', ['id' => $id]);
    return true;
}

/**
 * Create or update the activity's grade item.
 *
 * @param stdClass $tarsus The activity (with cmidnumber when known).
 * @param array|string|null $grades Grades to write, 'reset' to reset them.
 * @return int A GRADE_UPDATE_xx constant.
 */
function tarsuscoach_grade_item_update(stdClass $tarsus, $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = ['itemname' => $tarsus->name];
    if (isset($tarsus->cmidnumber)) {
        $params['idnumber'] = $tarsus->cmidnumber;
    }
    $grade = (int) ($tarsus->grade ?? 100);
    if ($grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $grade;
        $params['grademin'] = 0;
    } else if ($grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = -$grade;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }
    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }
    return grade_update('mod/tarsuscoach', $tarsus->course, 'mod', 'tarsuscoach', $tarsus->id, 0, $grades, $params);
}

/**
 * Delete the activity's grade item.
 *
 * @param stdClass $tarsus The activity.
 * @return int A GRADE_UPDATE_xx constant.
 */
function tarsuscoach_grade_item_delete(stdClass $tarsus): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/tarsuscoach', $tarsus->course, 'mod', 'tarsuscoach', $tarsus->id, 0, null, ['deleted' => 1]);
}

/**
 * The learners' results as grades: the result in percent, scaled to the
 * maximum grade (or to the scale).
 *
 * @param stdClass $tarsus The activity.
 * @param int $userid One learner, or 0 for all.
 * @return array User id => grade object.
 */
function tarsuscoach_get_user_grades(stdClass $tarsus, int $userid = 0): array {
    global $DB;
    $settings = settings::load((int) $tarsus->id);
    $rows = $userid > 0 ? [$userid => scoring::row((int) $tarsus->id, $userid)] : scoring::rows((int) $tarsus->id);
    $grade = (int) ($tarsus->grade ?? 100);
    $scaleitems = 0;
    if ($grade < 0) {
        $scale = $DB->get_field('scale', 'scale', ['id' => -$grade]);
        $scaleitems = $scale ? count(explode(',', $scale)) : 0;
    }
    $grades = [];
    foreach ($rows as $id => $row) {
        $result = $row === null ? null : scoring::grade($settings, $row);
        if ($result === null) {
            $raw = null;
        } else if ($grade > 0) {
            $raw = round($result / 100 * $grade, 5);
        } else if ($scaleitems > 0) {
            $raw = max(1, (int) ceil($result / 100 * $scaleitems));
        } else {
            $raw = null;
        }
        $grades[(int) $id] = (object) ['userid' => (int) $id, 'rawgrade' => $raw];
    }
    return $grades;
}

/**
 * Write the learners' results to the gradebook.
 *
 * @param stdClass $tarsus The activity.
 * @param int $userid One learner, or 0 for all.
 * @param bool $nullifnone Whether to write an empty grade when there is none.
 */
function tarsuscoach_update_grades(stdClass $tarsus, $userid = 0, $nullifnone = true): void {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    if ((int) ($tarsus->grade ?? 100) === 0) {
        tarsuscoach_grade_item_update($tarsus);
        return;
    }
    $grades = tarsuscoach_get_user_grades($tarsus, (int) $userid);
    if ($grades) {
        tarsuscoach_grade_item_update($tarsus, $grades);
    } else if ($userid && $nullifnone) {
        tarsuscoach_grade_item_update($tarsus, [(int) $userid => (object) ['userid' => (int) $userid, 'rawgrade' => null]]);
    } else {
        tarsuscoach_grade_item_update($tarsus);
    }
}

/**
 * Rescale existing grades after the maximum grade changed.
 *
 * @param stdClass $course The course.
 * @param stdClass $cm The course module.
 * @param float $oldmin Old minimum.
 * @param float $oldmax Old maximum.
 * @param float $newmin New minimum.
 * @param float $newmax New maximum.
 * @return bool
 */
function tarsuscoach_rescale_activity_grades($course, $cm, $oldmin, $oldmax, $newmin, $newmax): bool {
    global $DB;
    // Grades are recomputed from the brain's figures, not stored here.
    $tarsus = $DB->get_record('tarsuscoach', ['id' => $cm->instance], '*', MUST_EXIST);
    tarsuscoach_update_grades($tarsus);
    return true;
}

/**
 * The course page's information about an activity: its name, its
 * description when shown, and the completion rule for the completion API.
 *
 * @param stdClass $coursemodule The course module.
 * @return cached_cm_info|false
 */
function tarsuscoach_get_coursemodule_info($coursemodule) {
    global $DB;
    $tarsus = $DB->get_record('tarsuscoach', ['id' => $coursemodule->instance], 'id, name, intro, introformat, completionprogress');
    if (!$tarsus) {
        return false;
    }
    $info = new cached_cm_info();
    $info->name = $tarsus->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('tarsuscoach', $tarsus, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionprogress'] = (int) $tarsus->completionprogress;
    }
    return $info;
}

/**
 * The description of the activity's custom completion rule.
 *
 * @param cm_info|stdClass $cm The course module.
 * @return array
 */
function mod_tarsuscoach_get_completion_active_rule_descriptions($cm): array {
    if (empty($cm->customdata['customcompletionrules']) || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }
    $descriptions = [];
    $progress = (int) ($cm->customdata['customcompletionrules']['completionprogress'] ?? 0);
    if ($progress > 0) {
        $descriptions[] = get_string('completionprogress_desc', 'mod_tarsuscoach', $progress);
    }
    return $descriptions;
}

/**
 * Note that the learner opened the activity (logs and completion).
 *
 * @param stdClass $tarsus The activity.
 * @param stdClass $course The course.
 * @param stdClass|cm_info $cm The course module.
 * @param context_module $context Its context.
 */
function tarsuscoach_view(stdClass $tarsus, stdClass $course, $cm, context_module $context): void {
    global $CFG;
    require_once($CFG->libdir . '/completionlib.php');
    $event = \mod_tarsuscoach\event\course_module_viewed::create([
        'objectid' => $tarsus->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('tarsuscoach', $tarsus);
    $event->trigger();
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * The Materials page in the activity's navigation (teachers).
 *
 * @param settings_navigation $settings The settings navigation.
 * @param navigation_node $node The activity's node.
 */
function tarsuscoach_extend_settings_navigation(settings_navigation $settings, navigation_node $node): void {
    $cm = $settings->get_page()->cm;
    if (!$cm || !has_capability('mod/tarsuscoach:manage', $cm->context)) {
        return;
    }
    $node->add(
        get_string('tab_materials', 'mod_tarsuscoach'),
        new moodle_url('/mod/tarsuscoach/materials.php', ['id' => $cm->id]),
        navigation_node::TYPE_SETTING,
        null,
        'mod_tarsuscoach_materials'
    );
}

/**
 * The course reset form: an option to delete the learners' figures.
 *
 * @param MoodleQuickForm $mform The reset form.
 */
function tarsuscoach_reset_course_form_definition(&$mform): void {
    $mform->addElement('header', 'tarsuscoachheader', get_string('modulenameplural', 'mod_tarsuscoach'));
    $mform->addElement('advcheckbox', 'reset_tarsuscoach_progress', get_string('resetprogress', 'mod_tarsuscoach'));
}

/**
 * The course reset form's default: delete the figures.
 *
 * @param stdClass $course The course.
 * @return array
 */
function tarsuscoach_reset_course_form_defaults($course): array {
    return ['reset_tarsuscoach_progress' => 1];
}

/**
 * Reset the activities of a course: the learners' figures and grades.
 *
 * @param stdClass $data The reset form data.
 * @return array The status lines.
 */
function tarsuscoach_reset_userdata($data): array {
    global $DB;
    $status = [];
    if (empty($data->reset_tarsuscoach_progress)) {
        return $status;
    }
    $ids = $DB->get_fieldset_select('tarsuscoach', 'id', 'course = ?', [$data->courseid]);
    foreach ($ids as $id) {
        scoring::forget_object((int) $id);
        $tarsus = $DB->get_record('tarsuscoach', ['id' => $id]);
        if ($tarsus) {
            tarsuscoach_grade_item_update($tarsus, 'reset');
        }
    }
    $status[] = [
        'component' => get_string('modulenameplural', 'mod_tarsuscoach'),
        'item' => get_string('resetprogress', 'mod_tarsuscoach'),
        'error' => false,
    ];
    return $status;
}

/**
 * After a connection setting changed (server, brain ID, license key, demo
 * mode): when the connection now leads to another brain, what the previous
 * brain held is sent to the new one, and the administrator is told.
 * Saving never fails over it.
 *
 * @param string $name The setting's full name.
 */
function mod_tarsuscoach_connection_changed($name): void {
    $before = (string) get_config('mod_tarsuscoach', 'last_target');
    $now = \mod_tarsuscoach\local\support\config::target();
    if ($before === $now) {
        return;
    }
    set_config('last_target', $now, 'mod_tarsuscoach');
    try {
        $count = (new \mod_tarsuscoach\local\index\reconciler())->retarget();
    } catch (\Throwable $e) {
        \mod_tarsuscoach\local\support\platform::log('retarget after a connection change: ' . $e->getMessage(), 'error');
        return;
    }
    if ($count > 0) {
        \core\notification::info(get_string('retarget_notice', 'mod_tarsuscoach', (object) ['count' => $count]));
    }
}

/**
 * After the avatar upload changed: keep the scaled picture for the coach.
 *
 * @param string $name The setting's full name.
 */
function mod_tarsuscoach_avatar_changed($name): void {
    \mod_tarsuscoach\local\support\avatar::from_setting();
}

/**
 * Serve the avatar upload of the settings page.
 *
 * @param stdClass $course The course.
 * @param stdClass|null $cm The course module.
 * @param context $context The context.
 * @param string $filearea The file area.
 * @param array $args The path.
 * @param bool $forcedownload Whether to download.
 * @param array $options Options.
 * @return bool
 */
function tarsuscoach_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_SYSTEM || $filearea !== 'robotavatar') {
        return false;
    }
    require_login();
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'mod_tarsuscoach', 'robotavatar', 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
