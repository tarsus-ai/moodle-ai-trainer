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
 * Site administration > Plugins > Activity modules > Tarsus: the
 * connection to the brain, the assistant, the guardrails and help links,
 * plus the pages plan & credits, Brains, Deletions and status.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/tarsuscoach/lib.php');

use mod_tarsuscoach\local\support\budget;
use mod_tarsuscoach\local\support\config;
use mod_tarsuscoach\local\support\voices;

$hidden = $module->is_enabled() === false;
$ADMIN->add('modsettings', new admin_category('modtarsuscoachfolder', new lang_string('pluginname', 'mod_tarsuscoach'), $hidden));

$settings = new admin_settingpage($section, new lang_string('settings_title', 'mod_tarsuscoach'), 'moodle/site:config', $hidden);
$pageurl = static fn(string $tab): string => (new moodle_url('/mod/tarsuscoach/admin.php', ['tab' => $tab]))->out(false);

if ($ADMIN->fulltree) {
    // Connection.
    $settings->add(new admin_setting_heading(
        'mod_tarsuscoach/connection',
        new lang_string('settings_connection', 'mod_tarsuscoach'),
        new lang_string('settings_connection_desc', 'mod_tarsuscoach')
    ));

    $setting = new admin_setting_configtext(
        'mod_tarsuscoach/base_url',
        new lang_string('baseurl', 'mod_tarsuscoach'),
        new lang_string('baseurl_desc', 'mod_tarsuscoach'),
        '',
        PARAM_URL,
        60
    );
    $setting->set_updatedcallback('mod_tarsuscoach_connection_changed');
    $settings->add($setting);

    $settings->add(new admin_setting_configpasswordunmask(
        'mod_tarsuscoach/api_key',
        new lang_string('apikey', 'mod_tarsuscoach'),
        new lang_string('apikey_desc', 'mod_tarsuscoach'),
        ''
    ));

    $setting = new admin_setting_configtext(
        'mod_tarsuscoach/brain_id',
        new lang_string('brainid', 'mod_tarsuscoach'),
        get_string('brainid_desc', 'mod_tarsuscoach') . ' '
            . html_writer::link($pageurl('brains'), get_string('cfg_tab_brains', 'mod_tarsuscoach'))
            . ' ' . get_string('brainid_sandbox', 'mod_tarsuscoach'),
        '',
        PARAM_RAW_TRIMMED,
        40
    );
    $setting->set_updatedcallback('mod_tarsuscoach_connection_changed');
    $settings->add($setting);

    // Existing Tarsus customers: a license key in place of a brain ID, billed by Tarsus.
    $setting = new admin_setting_configpasswordunmask(
        'mod_tarsuscoach/license_key',
        new lang_string('licensekey', 'mod_tarsuscoach'),
        new lang_string('licensekey_desc', 'mod_tarsuscoach'),
        ''
    );
    $setting->set_updatedcallback('mod_tarsuscoach_connection_changed');
    $settings->add($setting);

    // Assistant.
    $settings->add(new admin_setting_heading(
        'mod_tarsuscoach/assistant',
        new lang_string('settings_assistant', 'mod_tarsuscoach'),
        new lang_string('settings_assistant_desc', 'mod_tarsuscoach')
    ));
    $settings->add(new admin_setting_configtext(
        'mod_tarsuscoach/robot_name',
        new lang_string('robotname', 'mod_tarsuscoach'),
        new lang_string('robotname_desc', 'mod_tarsuscoach'),
        config::DEFAULT_ROBOT_NAME,
        PARAM_TEXT,
        30
    ));
    $settings->add(new admin_setting_configtext(
        'mod_tarsuscoach/robot_id',
        new lang_string('robotid', 'mod_tarsuscoach'),
        new lang_string('robotid_desc', 'mod_tarsuscoach'),
        '',
        PARAM_TEXT,
        40
    ));
    $settings->add(new admin_setting_configtextarea(
        'mod_tarsuscoach/robot_description',
        new lang_string('robotdescription', 'mod_tarsuscoach'),
        new lang_string('robotdescription_desc', 'mod_tarsuscoach'),
        '',
        PARAM_TEXT,
        60,
        3
    ));
    $setting = new admin_setting_configstoredfile(
        'mod_tarsuscoach/robot_avatar_file',
        new lang_string('robotavatar', 'mod_tarsuscoach'),
        new lang_string('robotavatar_desc', 'mod_tarsuscoach'),
        'robotavatar',
        0,
        ['maxfiles' => 1, 'accepted_types' => ['.png', '.jpg', '.jpeg', '.gif', '.webp']]
    );
    $setting->set_updatedcallback('mod_tarsuscoach_avatar_changed');
    $settings->add($setting);
    $settings->add(new admin_setting_configselect(
        'mod_tarsuscoach/voice_mode',
        new lang_string('voice_mode', 'mod_tarsuscoach'),
        new lang_string('voice_mode_desc', 'mod_tarsuscoach'),
        voices::MODE_AUTO,
        [
            voices::MODE_AUTO => new lang_string('voice_mode_auto', 'mod_tarsuscoach'),
            voices::MODE_FIXED => new lang_string('voice_mode_fixed', 'mod_tarsuscoach'),
        ]
    ));
    $voices = voices::cached_options();
    $current = trim((string) config::get('voice', ''));
    if ($current !== '' && !isset($voices[$current])) {
        $voices = [$current => $current] + $voices;
    }
    if (!isset($voices[voices::DEFAULT_VOICE])) {
        $voices = [voices::DEFAULT_VOICE => voices::DEFAULT_VOICE] + $voices;
    }
    $settings->add(new admin_setting_configselect(
        'mod_tarsuscoach/voice',
        new lang_string('voice', 'mod_tarsuscoach'),
        get_string('voice_desc', 'mod_tarsuscoach') . ' '
            . html_writer::link($pageurl('status'), get_string('voice_refresh', 'mod_tarsuscoach')),
        voices::DEFAULT_VOICE,
        $voices
    ));

    // Guardrails.
    $settings->add(new admin_setting_heading(
        'mod_tarsuscoach/guardrails',
        new lang_string('settings_guardrails', 'mod_tarsuscoach'),
        new lang_string('settings_guardrails_desc', 'mod_tarsuscoach')
    ));
    $setting = new admin_setting_configcheckbox(
        'mod_tarsuscoach/mockmode',
        new lang_string('mockmode', 'mod_tarsuscoach'),
        new lang_string('mockmode_desc', 'mod_tarsuscoach'),
        '1'
    );
    $setting->set_updatedcallback('mod_tarsuscoach_connection_changed');
    $settings->add($setting);
    $settings->add(new admin_setting_configcheckbox(
        'mod_tarsuscoach/killswitch',
        new lang_string('killswitch', 'mod_tarsuscoach'),
        new lang_string('killswitch_desc', 'mod_tarsuscoach'),
        '0'
    ));
    $settings->add(new admin_setting_configtext(
        'mod_tarsuscoach/ratelimit',
        new lang_string('ratelimit', 'mod_tarsuscoach'),
        new lang_string('ratelimit_desc', 'mod_tarsuscoach'),
        (string) config::DEFAULT_RATE_LIMIT,
        PARAM_INT,
        6
    ));
    $settings->add(new admin_setting_description(
        'mod_tarsuscoach/monthly_limit_note',
        new lang_string('monthly_limit', 'mod_tarsuscoach'),
        get_string('monthly_limit_elsewhere', 'mod_tarsuscoach', (object) [
            'limit' => budget::has_limit() ? number_format(budget::limit()) : get_string('no_limit', 'mod_tarsuscoach'),
        ]) . ' ' . html_writer::link(
            $pageurl('plan'),
            get_string('cfg_tab_plan', 'mod_tarsuscoach')
        )
    ));

    // Help links shown behind the coach's help button.
    $settings->add(new admin_setting_heading(
        'mod_tarsuscoach/help',
        new lang_string('settings_help', 'mod_tarsuscoach'),
        new lang_string('settings_help_desc', 'mod_tarsuscoach')
    ));
    $settings->add(new admin_setting_configtextarea(
        'mod_tarsuscoach/help_links',
        new lang_string('help_links', 'mod_tarsuscoach'),
        new lang_string('help_links_desc', 'mod_tarsuscoach'),
        '',
        PARAM_RAW_TRIMMED,
        60,
        5
    ));
}
$ADMIN->add('modtarsuscoachfolder', $settings);

foreach (['plan', 'brains', 'deletions', 'status'] as $tab) {
    $ADMIN->add('modtarsuscoachfolder', new admin_externalpage(
        'mod_tarsuscoach_' . $tab,
        new lang_string('cfg_tab_' . $tab, 'mod_tarsuscoach'),
        new moodle_url('/mod/tarsuscoach/admin.php', ['tab' => $tab]),
        'moodle/site:config',
        $hidden
    ));
}

// Every page is in the category: no extra page from Moodle.
$settings = null;
