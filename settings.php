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
 * plus the pages Plan & credits, Brains, Deletions and Status.
 *
 * @package    mod_tarsus
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/tarsus/lib.php');

use mod_tarsus\local\Support\Budget;
use mod_tarsus\local\Support\Config;
use mod_tarsus\local\Support\Voices;

$hidden = $module->is_enabled() === false;
$ADMIN->add('modsettings', new admin_category('modtarsusfolder', new lang_string('pluginname', 'mod_tarsus'), $hidden));

$settings = new admin_settingpage($section, new lang_string('settings_title', 'mod_tarsus'), 'moodle/site:config', $hidden);
$pageurl = static fn(string $tab): string => (new moodle_url('/mod/tarsus/admin.php', ['tab' => $tab]))->out(false);

if ($ADMIN->fulltree) {
    // Connection.
    $settings->add(new admin_setting_heading('mod_tarsus/connection',
        new lang_string('settings_connection', 'mod_tarsus'), new lang_string('settings_connection_desc', 'mod_tarsus')));

    $setting = new admin_setting_configtext('mod_tarsus/base_url',
        new lang_string('baseurl', 'mod_tarsus'), new lang_string('baseurl_desc', 'mod_tarsus'), '', PARAM_URL, 60);
    $setting->set_updatedcallback('mod_tarsus_connection_changed');
    $settings->add($setting);

    $settings->add(new admin_setting_configpasswordunmask('mod_tarsus/api_key',
        new lang_string('apikey', 'mod_tarsus'), new lang_string('apikey_desc', 'mod_tarsus'), ''));

    $setting = new admin_setting_configtext('mod_tarsus/brain_id', new lang_string('brainid', 'mod_tarsus'),
        get_string('brainid_desc', 'mod_tarsus') . ' ' . html_writer::link($pageurl('brains'), get_string('cfg_tab_brains', 'mod_tarsus'))
            . ' ' . get_string('brainid_sandbox', 'mod_tarsus'),
        '', PARAM_RAW_TRIMMED, 40);
    $setting->set_updatedcallback('mod_tarsus_connection_changed');
    $settings->add($setting);

    // Existing Tarsus customers: a license key in place of a brain ID, billed by Tarsus.
    $setting = new admin_setting_configpasswordunmask('mod_tarsus/license_key',
        new lang_string('licensekey', 'mod_tarsus'), new lang_string('licensekey_desc', 'mod_tarsus'), '');
    $setting->set_updatedcallback('mod_tarsus_connection_changed');
    $settings->add($setting);

    // Assistant.
    $settings->add(new admin_setting_heading('mod_tarsus/assistant',
        new lang_string('settings_assistant', 'mod_tarsus'), new lang_string('settings_assistant_desc', 'mod_tarsus')));
    $settings->add(new admin_setting_configtext('mod_tarsus/robot_name', new lang_string('robotname', 'mod_tarsus'),
        new lang_string('robotname_desc', 'mod_tarsus'), Config::DEFAULT_ROBOT_NAME, PARAM_TEXT, 30));
    $settings->add(new admin_setting_configtext('mod_tarsus/robot_id', new lang_string('robotid', 'mod_tarsus'),
        new lang_string('robotid_desc', 'mod_tarsus'), '', PARAM_TEXT, 40));
    $settings->add(new admin_setting_configtextarea('mod_tarsus/robot_description', new lang_string('robotdescription', 'mod_tarsus'),
        new lang_string('robotdescription_desc', 'mod_tarsus'), '', PARAM_TEXT, 60, 3));
    $setting = new admin_setting_configstoredfile('mod_tarsus/robot_avatar_file', new lang_string('robotavatar', 'mod_tarsus'),
        new lang_string('robotavatar_desc', 'mod_tarsus'), 'robotavatar', 0,
        ['maxfiles' => 1, 'accepted_types' => ['.png', '.jpg', '.jpeg', '.gif', '.webp']]);
    $setting->set_updatedcallback('mod_tarsus_avatar_changed');
    $settings->add($setting);
    $settings->add(new admin_setting_configselect('mod_tarsus/voice_mode', new lang_string('voice_mode', 'mod_tarsus'),
        new lang_string('voice_mode_desc', 'mod_tarsus'), Voices::MODE_AUTO, [
            Voices::MODE_AUTO => new lang_string('voice_mode_auto', 'mod_tarsus'),
            Voices::MODE_FIXED => new lang_string('voice_mode_fixed', 'mod_tarsus'),
        ]));
    $voices = Voices::cachedOptions();
    $current = trim((string) Config::get('voice', ''));
    if ($current !== '' && !isset($voices[$current])) {
        $voices = [$current => $current] + $voices;
    }
    if (!isset($voices[Voices::DEFAULT_VOICE])) {
        $voices = [Voices::DEFAULT_VOICE => Voices::DEFAULT_VOICE] + $voices;
    }
    $settings->add(new admin_setting_configselect('mod_tarsus/voice', new lang_string('voice', 'mod_tarsus'),
        get_string('voice_desc', 'mod_tarsus') . ' ' . html_writer::link($pageurl('status'), get_string('voice_refresh', 'mod_tarsus')),
        Voices::DEFAULT_VOICE, $voices));

    // Guardrails.
    $settings->add(new admin_setting_heading('mod_tarsus/guardrails',
        new lang_string('settings_guardrails', 'mod_tarsus'), new lang_string('settings_guardrails_desc', 'mod_tarsus')));
    $setting = new admin_setting_configcheckbox('mod_tarsus/mockmode', new lang_string('mockmode', 'mod_tarsus'),
        new lang_string('mockmode_desc', 'mod_tarsus'), '1');
    $setting->set_updatedcallback('mod_tarsus_connection_changed');
    $settings->add($setting);
    $settings->add(new admin_setting_configcheckbox('mod_tarsus/killswitch', new lang_string('killswitch', 'mod_tarsus'),
        new lang_string('killswitch_desc', 'mod_tarsus'), '0'));
    $settings->add(new admin_setting_configtext('mod_tarsus/ratelimit', new lang_string('ratelimit', 'mod_tarsus'),
        new lang_string('ratelimit_desc', 'mod_tarsus'), (string) Config::DEFAULT_RATE_LIMIT, PARAM_INT, 6));
    $settings->add(new admin_setting_description('mod_tarsus/monthly_limit_note', new lang_string('monthly_limit', 'mod_tarsus'),
        get_string('monthly_limit_elsewhere', 'mod_tarsus', (object) [
            'limit' => Budget::hasLimit() ? number_format(Budget::limit()) : get_string('no_limit', 'mod_tarsus'),
        ]) . ' ' . html_writer::link($pageurl('plan'), get_string('cfg_tab_plan', 'mod_tarsus'))));

    // Help links shown behind the coach's help button.
    $settings->add(new admin_setting_heading('mod_tarsus/help',
        new lang_string('settings_help', 'mod_tarsus'), new lang_string('settings_help_desc', 'mod_tarsus')));
    $settings->add(new admin_setting_configtextarea('mod_tarsus/help_links', new lang_string('help_links', 'mod_tarsus'),
        new lang_string('help_links_desc', 'mod_tarsus'), '', PARAM_RAW_TRIMMED, 60, 5));
}
$ADMIN->add('modtarsusfolder', $settings);

foreach (['plan', 'brains', 'deletions', 'status'] as $tab) {
    $ADMIN->add('modtarsusfolder', new admin_externalpage(
        'mod_tarsus_' . $tab,
        new lang_string('cfg_tab_' . $tab, 'mod_tarsus'),
        new moodle_url('/mod/tarsus/admin.php', ['tab' => $tab]),
        'moodle/site:config',
        $hidden
    ));
}

// Every page is in the category: no extra page from Moodle.
$settings = null;
