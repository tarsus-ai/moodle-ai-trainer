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

namespace mod_tarsuscoach\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use mod_tarsuscoach\local\output\views;
use mod_tarsuscoach\local\support\budget;
use mod_tarsuscoach\local\support\plan;
use mod_tarsuscoach\local\support\platform;

/**
 * The booked package, its prices, the monthly limit and the recipients of
 * the warnings.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plan_form extends \moodleform {
    /**
     * The fields.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'tab', 'plan');
        $mform->setType('tab', PARAM_ALPHA);

        $mform->addElement('header', 'plansection', get_string('plan_section', 'mod_tarsuscoach'));
        $mform->addElement('static', 'plansectiondesc', '', get_string('plan_section_desc', 'mod_tarsuscoach'));
        $radios = [];
        foreach (plan::ALL as $key) {
            $radios[] = $mform->createElement('radio', 'plan', '', get_string('plan_' . $key, 'mod_tarsuscoach')
                . ' – ' . get_string('plan_' . $key . '_desc', 'mod_tarsuscoach'), $key);
        }
        $mform->addGroup($radios, 'plangroup', get_string('plan', 'mod_tarsuscoach'), \html_writer::empty_tag('br'), false);
        $mform->addRule('plangroup', null, 'required');

        $mform->addElement('text', 'plan_credits', get_string('plan_credits', 'mod_tarsuscoach'), ['size' => 10]);
        $mform->setType('plan_credits', PARAM_INT);
        $mform->addElement(
            'static',
            'plan_credits_desc',
            '',
            get_string('plan_credits_desc', 'mod_tarsuscoach', (object) ['default' => number_format(plan::DEFAULT_CREDITS)])
        );

        $mform->addElement('text', 'plan_price', get_string('plan_price', 'mod_tarsuscoach'), ['size' => 8]);
        $mform->setType('plan_price', PARAM_RAW_TRIMMED);
        $mform->addElement(
            'static',
            'plan_price_desc',
            '',
            get_string('plan_price_desc', 'mod_tarsuscoach', (object) ['default' => number_format(plan::DEFAULT_PRICE, 2)])
        );

        $mform->addElement('text', 'plan_currency', get_string('plan_currency', 'mod_tarsuscoach'), ['size' => 6, 'maxlength' => 8]);
        $mform->setType('plan_currency', PARAM_TEXT);

        $mform->addElement('header', 'limitsection', get_string('limit_section', 'mod_tarsuscoach'));
        $mform->addElement('static', 'limitsectiondesc', '', get_string('limit_section_desc', 'mod_tarsuscoach'));
        $mform->addElement('text', 'monthly_limit', get_string('monthly_limit', 'mod_tarsuscoach'), ['size' => 10]);
        $mform->setType('monthly_limit', PARAM_INT);
        $mform->addRule('monthly_limit', null, 'required');
        $mform->addElement('static', 'monthly_limit_desc', '', get_string('monthly_limit_desc', 'mod_tarsuscoach', (object) [
            'used' => budget::format(budget::used()),
            'included' => number_format(plan::included_credits()),
            'reset' => views::date(budget::next_month_start()),
        ]));

        $mform->addElement('textarea', 'notify_emails', get_string('notify_emails', 'mod_tarsuscoach'), ['rows' => 2, 'cols' => 60]);
        $mform->setType('notify_emails', PARAM_RAW_TRIMMED);
        $mform->addElement('static', 'notify_emails_desc', '', get_string('notify_emails_desc', 'mod_tarsuscoach', (object) [
            'admin' => s(platform::admin_email()),
            'thresholds' => implode(' %, ', budget::THRESHOLDS) . ' %',
        ]));

        $this->add_action_buttons(false);
    }

    /**
     * The e-mail addresses must be valid.
     *
     * @param array $data The values.
     * @param array $files The files.
     * @return array Errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (self::addresses((string) ($data['notify_emails'] ?? '')) as $address) {
            if (!validate_email($address)) {
                $errors['notify_emails'] = get_string('error_email', 'mod_tarsuscoach', (object) ['email' => s($address)]);
                break;
            }
        }
        if (!in_array((string) ($data['plan'] ?? ''), plan::ALL, true)) {
            $errors['plangroup'] = get_string('required');
        }
        return $errors;
    }

    /**
     * The addresses of a list separated by spaces, commas or semicolons.
     *
     * @param string $raw The list.
     * @return string[]
     */
    public static function addresses(string $raw): array {
        $raw = str_replace([',', ';', "\r", "\n", "\t"], ' ', $raw);
        return array_values(array_filter(array_map('trim', explode(' ', $raw)), static fn(string $a): bool => $a !== ''));
    }
}
