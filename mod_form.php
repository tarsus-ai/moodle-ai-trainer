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
 * The settings of a Tarsus activity: the same as the coach's settings panel.
 *
 * @package    mod_tarsus
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/tarsus/lib.php');

use mod_tarsus\local\Activity\Didactics;
use mod_tarsus\local\Activity\Settings;
use mod_tarsus\local\Service\SettingsApi;
use mod_tarsus\local\Support\Plan;

/**
 * The activity's settings form.
 */
class mod_tarsus_mod_form extends moodleform_mod {
    /**
     * The fields.
     */
    public function definition() {
        $mform = $this->_form;
        $plan = Plan::label();

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name', 'mod_tarsus'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        // Modes.
        $mform->addElement('header', 'tarsusmodes', get_string('settings_modes', 'mod_tarsus'));
        $mform->setExpanded('tarsusmodes');
        foreach (['ask', 'test', 'build', 'discuss'] as $mode) {
            $name = $mode . 'enabled';
            $mform->addElement('advcheckbox', $name, get_string($name, 'mod_tarsus'), get_string($name . '_help', 'mod_tarsus'));
            if (!Plan::allowsMode($mode)) {
                // Not part of the customer's package: shown, explained, locked.
                $mform->hardFreeze($name);
                $mform->addElement('static', $name . 'locked', '', html_writer::tag('strong',
                    get_string('plan_mode_unavailable', 'mod_tarsus', (object) ['plan' => $plan])));
            }
        }

        // Interaction.
        $mform->addElement('header', 'tarsusinteraction', get_string('settings_interaction', 'mod_tarsus'));
        $mform->addElement('static', 'tarsusinteractiondesc', '', get_string('settings_interaction_desc', 'mod_tarsus'));
        $radios = [
            $mform->createElement('radio', 'interactiontype', '', get_string('interaction_trainer', 'mod_tarsus'), Plan::INTERACTION_TRAINER),
            $mform->createElement('radio', 'interactiontype', '', get_string('interaction_ai', 'mod_tarsus'), Plan::INTERACTION_AI,
                Plan::allowsAiDriven() ? [] : ['disabled' => 'disabled']),
        ];
        $mform->addGroup($radios, 'interactiontypegroup', get_string('interactiontype', 'mod_tarsus'), html_writer::empty_tag('br'), false);
        if (!Plan::allowsAiDriven()) {
            $mform->addElement('static', 'interactionlocked', '', html_writer::tag('strong',
                get_string('plan_interaction_unavailable', 'mod_tarsus', (object) ['plan' => $plan])));
        }

        $mform->addElement('advcheckbox', 'voiceinput', get_string('voiceinput', 'mod_tarsus'), get_string('voiceinput_help', 'mod_tarsus'));
        if (!Plan::allowsVoiceInput()) {
            $mform->hardFreeze('voiceinput');
            $mform->addElement('static', 'voicelocked', '', html_writer::tag('strong',
                get_string('plan_feature_locked', 'mod_tarsus', (object) ['plan' => $plan])));
        }
        $mform->addElement('advcheckbox', 'referencesenabled', get_string('referencesenabled', 'mod_tarsus'),
            get_string('referencesenabled_help', 'mod_tarsus'));
        $mform->addElement('advcheckbox', 'visualaccessibility', get_string('visualaccessibility', 'mod_tarsus'),
            get_string('visualaccessibility_help', 'mod_tarsus'));

        // Thinking level: how thoroughly the coach thinks.
        $levels = [];
        foreach ([1 => 'fast', 2 => 'slow', 3 => 'deep', 4 => 'intense'] as $level => $key) {
            $levels[] = $mform->createElement('radio', 'thinkinglevel', '', get_string('thinking_' . $key, 'mod_tarsus')
                . ' – ' . get_string('thinking_' . $key . '_help', 'mod_tarsus'), $level);
        }
        $mform->addGroup($levels, 'thinkinglevelgroup', get_string('thinkinglevel', 'mod_tarsus'), html_writer::empty_tag('br'), false);
        $mform->addHelpButton('thinkinglevelgroup', 'thinkinglevel', 'mod_tarsus');

        // General didactics.
        $mform->addElement('header', 'tarsusgeneral', get_string('settings_general', 'mod_tarsus'));
        $dqr = [];
        foreach (Didactics::DQR_LEVELS as $level) {
            $dqr[$level] = $level . ' – ' . get_string('dqr_level' . $level, 'mod_tarsus');
        }
        $mform->addElement('select', 'dqr', get_string('dqr', 'mod_tarsus'), $dqr);
        $mform->addHelpButton('dqr', 'dqr', 'mod_tarsus');
        $mform->addElement('text', 'industry', get_string('industry', 'mod_tarsus'), ['size' => '48']);
        $mform->setType('industry', PARAM_TEXT);
        $mform->addRule('industry', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('industry', 'industry', 'mod_tarsus');
        $mform->addElement('advcheckbox', 'anticheat', get_string('anticheat', 'mod_tarsus'), get_string('anticheat_help', 'mod_tarsus'));

        // Ask a question.
        $mform->addElement('header', 'tarsusask', get_string('settings_ask', 'mod_tarsus'));
        $styles = [];
        foreach (['closed', 'lexical', 'tutorial', 'socratic', 'open'] as $i => $key) {
            $styles[$i + 1] = get_string('style_' . $key, 'mod_tarsus');
        }
        $mform->addElement('select', 'conversationstyle', get_string('conversationstyle', 'mod_tarsus'), $styles);
        $mform->addHelpButton('conversationstyle', 'conversationstyle', 'mod_tarsus');

        // Test knowledge.
        $mform->addElement('header', 'tarsustest', get_string('settings_test', 'mod_tarsus'));
        $taxonomy = [];
        foreach ([1 => 'taxonomy_knowledge', 2 => 'taxonomy_understanding', 3 => 'taxonomy_application'] as $level => $key) {
            $taxonomy[] = $mform->createElement('advcheckbox', $level, '', get_string($key, 'mod_tarsus'));
        }
        $mform->addGroup($taxonomy, 'taxonomylevels', get_string('taxonomylevels', 'mod_tarsus'), ' ', true);
        $mform->addHelpButton('taxonomylevels', 'taxonomylevels', 'mod_tarsus');

        foreach ([1 => 'difficulty_easy', 2 => 'difficulty_medium', 3 => 'difficulty_hard'] as $grade => $gradekey) {
            $types = [];
            foreach (Didactics::TYPES as $type) {
                $types[] = $mform->createElement('advcheckbox', $type, '', get_string(Didactics::typeLabelKey($type), 'mod_tarsus'));
            }
            $label = get_string('questiontypes_grade', 'mod_tarsus', (object) ['grade' => get_string($gradekey, 'mod_tarsus')]);
            $mform->addGroup($types, 'qtypes' . $grade, $label, html_writer::empty_tag('br'), true);
            if ($grade === 1) {
                $mform->addHelpButton('qtypes1', 'questiontypes', 'mod_tarsus');
            }
        }

        $operators = [
            $mform->createElement('radio', 'operators', '', get_string('operators_standard', 'mod_tarsus'), Didactics::OPERATORS_STANDARD),
            $mform->createElement('radio', 'operators', '', get_string('operators_ihk', 'mod_tarsus'), Didactics::OPERATORS_IHK),
        ];
        $mform->addGroup($operators, 'operatorsgroup', get_string('operators', 'mod_tarsus'), html_writer::empty_tag('br'), false);
        $mform->addHelpButton('operatorsgroup', 'operators', 'mod_tarsus');

        $tempos = [];
        foreach (Didactics::LEARN_TEMPOS as $value) {
            $label = Didactics::tempoLabel($value);
            $tempos[(string) $value] = $value === Didactics::LEARN_TEMPO_DEFAULT
                ? get_string('learntempo_recommended', 'mod_tarsus', (object) ['label' => $label]) : $label;
        }
        $mform->addElement('select', 'learntempo', get_string('learntempo', 'mod_tarsus'), $tempos);
        $mform->addHelpButton('learntempo', 'learntempo', 'mod_tarsus');

        // Build knowledge.
        $mform->addElement('header', 'tarsusbuild', get_string('settings_build', 'mod_tarsus'));
        $methods = [];
        foreach (Didactics::LEARNING_METHODS as $value) {
            $methods[$value] = get_string('learningmethod_' . str_replace('-', '', $value), 'mod_tarsus');
        }
        $mform->addElement('select', 'learningmethod', get_string('learningmethod', 'mod_tarsus'), $methods);
        $mform->addHelpButton('learningmethod', 'learningmethod', 'mod_tarsus');
        $mform->addElement('advcheckbox', 'gifenabled', get_string('gifenabled', 'mod_tarsus'), get_string('gifenabled_help', 'mod_tarsus'));

        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * The current settings (or the defaults of a new activity) as form values.
     *
     * @param array $defaultvalues The values, changed in place.
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        $settings = !empty($this->current->instance) ? Settings::load((int) $this->current->instance) : new Settings();
        $defaultvalues['askenabled'] = (int) $settings->askenabled;
        $defaultvalues['testenabled'] = (int) $settings->testenabled;
        $defaultvalues['buildenabled'] = (int) $settings->buildenabled;
        $defaultvalues['discussenabled'] = (int) $settings->discussenabled;
        $defaultvalues['interactiontype'] = $settings->interactiontype;
        $defaultvalues['voiceinput'] = (int) $settings->voiceinput;
        $defaultvalues['referencesenabled'] = (int) $settings->referencesenabled;
        $defaultvalues['visualaccessibility'] = (int) $settings->visualaccessibility;
        $defaultvalues['thinkinglevel'] = $settings->thinkinglevel;
        $defaultvalues['dqr'] = $settings->dqr;
        $defaultvalues['industry'] = $settings->industry;
        $defaultvalues['anticheat'] = (int) $settings->anticheat;
        $defaultvalues['conversationstyle'] = $settings->conversationstyle;
        $defaultvalues['operators'] = $settings->operators;
        $defaultvalues['learntempo'] = (string) $settings->learntempo;
        $defaultvalues['learningmethod'] = $settings->learningmethod;
        $defaultvalues['gifenabled'] = (int) $settings->gifenabled;
        $defaultvalues['taxonomylevels'] = [];
        foreach ([1, 2, 3] as $level) {
            $defaultvalues['taxonomylevels'][$level] = in_array($level, $settings->taxonomylevels, true) ? 1 : 0;
        }
        foreach (Didactics::GRADES as $grade) {
            $defaultvalues['qtypes' . $grade] = [];
            foreach (Didactics::TYPES as $type) {
                $defaultvalues['qtypes' . $grade][$type] = in_array($type, $settings->questiontypes[$grade] ?? [], true) ? 1 : 0;
            }
        }
        $suffix = $this->suffix();
        $progress = (int) $settings->completionprogress;
        $defaultvalues['completionprogressenabled' . $suffix] = $progress > 0 ? 1 : 0;
        $defaultvalues['completionprogress' . $suffix] = $progress > 0 ? $progress : Settings::DEFAULT_COMPLETION;
    }

    /**
     * The same checks as the coach's settings panel.
     *
     * @param array $data The submitted values.
     * @param array $files The files.
     * @return array Errors by field.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $settings = !empty($this->current->instance) ? Settings::load((int) $this->current->instance) : new Settings();
        $object = (object) $data;
        if (empty($object->{'completionprogressenabled' . $this->suffix()})) {
            $object->completionprogress = 0;
        }
        foreach ((new SettingsApi($settings))->apply(tarsus_form_values($object)) as $field => $message) {
            $map = ['askenabled' => 'askenabled', 'completionprogress' => 'completionprogressgroup' . $this->suffix()];
            $errors[$map[$field] ?? $field] = $message;
        }
        return $errors;
    }

    /**
     * The suffix Moodle 4.3 and newer add to completion elements (none before).
     *
     * @return string
     */
    private function suffix(): string {
        return method_exists($this, 'get_suffix') ? (string) $this->get_suffix() : '';
    }

    /**
     * The completion rule: the learner reaches a progress.
     *
     * @return array The rule's element names.
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->suffix();
        $group = [
            $mform->createElement('checkbox', 'completionprogressenabled' . $suffix, '', get_string('completionprogress_enable', 'mod_tarsus')),
            $mform->createElement('text', 'completionprogress' . $suffix, '', ['size' => 3]),
            $mform->createElement('static', 'completionprogresspercent' . $suffix, '', '%'),
        ];
        $mform->setType('completionprogress' . $suffix, PARAM_INT);
        $mform->addGroup($group, 'completionprogressgroup' . $suffix, get_string('completionprogress_rule', 'mod_tarsus'), ' ', false);
        $mform->addHelpButton('completionprogressgroup' . $suffix, 'completionprogress', 'mod_tarsus');
        $mform->disabledIf('completionprogress' . $suffix, 'completionprogressenabled' . $suffix, 'notchecked');
        return ['completionprogressgroup' . $suffix];
    }

    /**
     * Whether the completion rule is on.
     *
     * @param array $data The submitted values.
     * @return bool
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->suffix();
        return !empty($data['completionprogressenabled' . $suffix]) && (int) ($data['completionprogress' . $suffix] ?? 0) > 0;
    }

    /**
     * An unticked rule (or no automatic completion) stores 0.
     *
     * @param stdClass $data The submitted data.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        $suffix = $this->suffix();
        if (!empty($data->completionunlocked)) {
            $automatic = !empty($data->{'completion' . $suffix}) && $data->{'completion' . $suffix} == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->{'completionprogressenabled' . $suffix}) || !$automatic) {
                $data->{'completionprogress' . $suffix} = 0;
            }
        }
    }
}
