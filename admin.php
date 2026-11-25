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
 * The site administration pages of Tarsus besides its settings: plan &
 * credits, Brains, Deletions and status.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

use core\output\notification;
use mod_tarsuscoach\form\plan_form;
use mod_tarsuscoach\local\activity\scoring;
use mod_tarsuscoach\local\client\client_factory;
use mod_tarsuscoach\local\client\configuration_exception;
use mod_tarsuscoach\local\client\transport_exception;
use mod_tarsuscoach\local\guardrail\blocked_exception;
use mod_tarsuscoach\local\guardrail\pipeline;
use mod_tarsuscoach\local\index\reconciler;
use mod_tarsuscoach\local\index\repository;
use mod_tarsuscoach\local\index\status;
use mod_tarsuscoach\local\output\html;
use mod_tarsuscoach\local\output\views;
use mod_tarsuscoach\local\support\budget;
use mod_tarsuscoach\local\support\config;
use mod_tarsuscoach\local\support\plan;
use mod_tarsuscoach\local\support\platform;
use mod_tarsuscoach\local\support\usage_meter;
use mod_tarsuscoach\local\support\voices;

$tab = optional_param('tab', 'status', PARAM_ALPHA);
if (!in_array($tab, ['plan', 'brains', 'deletions', 'status'], true)) {
    $tab = 'status';
}
$action = optional_param('action', '', PARAM_ALPHA);

admin_externalpage_setup('mod_tarsuscoach_' . $tab);
$url = new moodle_url('/mod/tarsuscoach/admin.php', ['tab' => $tab]);
$PAGE->set_url($url);

$t = static fn(string $key, array $params = []): string => platform::txt($key, $params);
$yesno = static fn(bool $value): string => get_string($value ? 'yes' : 'no');

// ----------------------------------------------------------------------
// Actions (POST, with the session key)
// ----------------------------------------------------------------------

if ($action !== '') {
    require_sesskey();
    switch ($action) {
        case 'testconnection':
            $statusurl = new moodle_url('/mod/tarsuscoach/admin.php', ['tab' => 'status']);
            try {
                $brains = (new pipeline())->execute(fn() => client_factory::get_client()->list_brains(), 0);
                redirect($statusurl, $t('status_test_ok', ['count' => count((array) $brains)]), null, notification::NOTIFY_SUCCESS);
            } catch (configuration_exception $e) {
                redirect($statusurl, $t($e->langKey), null, notification::NOTIFY_ERROR);
            } catch (blocked_exception $e) {
                redirect($statusurl, $t('guardrail_blocked_' . $e->reason), null, notification::NOTIFY_ERROR);
            } catch (\Throwable $e) {
                if ($e instanceof \moodle_exception && !($e instanceof \dml_exception)) {
                    throw $e;
                }
                redirect($statusurl, $t('transport_error') . ' ' . s($e->getMessage()), null, notification::NOTIFY_ERROR);
            }
            break;

        case 'resetbreaker':
            (new pipeline())->reset_breaker();
            redirect($url, $t('status_breaker_reset'), null, notification::NOTIFY_SUCCESS);
            break;

        case 'refreshusage':
            $before = budget::remote();
            $after = budget::refresh_remote(true);
            if ($after === null || ($before !== null && $after['fetched'] === $before['fetched'])) {
                redirect($url, $t('usage_refresh_failed'), null, notification::NOTIFY_ERROR);
            }
            redirect($url, $t('usage_refreshed', ['credits' => budget::format($after['credits'])]), null, notification::NOTIFY_SUCCESS);
            break;

        case 'refreshvoices':
            try {
                $count = count(voices::catalogue(true));
                redirect($url, $t('voices_refreshed', ['count' => $count]), null, notification::NOTIFY_SUCCESS);
            } catch (\Throwable $e) {
                if ($e instanceof \moodle_exception) {
                    throw $e;
                }
                redirect($url, $t('transport_error'), null, notification::NOTIFY_ERROR);
            }
            break;

        case 'activatebrain':
            $brainid = trim(optional_param('brainid', '', PARAM_TEXT));
            $ok = false;
            if ($brainid !== '') {
                try {
                    foreach (client_factory::get_client()->list_brains() as $brain) {
                        if ((string) $brain['brainid'] === $brainid && !empty($brain['active']) && strpos($brainid, 'deleted-') !== 0) {
                            $ok = true;
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    $ok = false;
                }
            }
            if (!$ok) {
                redirect($url, $t('brain_activate_failed'), null, notification::NOTIFY_ERROR);
            }
            set_config('brain_id', $brainid, 'mod_tarsuscoach');
            mod_tarsuscoach_connection_changed('mod_tarsuscoach/brain_id');
            redirect($url, $t('brain_activated', ['brain' => s($brainid)]), null, notification::NOTIFY_SUCCESS);
            break;

        case 'retrydeletions':
            $cleared = (new reconciler())->sweep(true);
            redirect(
                $url,
                $t('deletions_retried', ['count' => $cleared]),
                null,
                $cleared > 0 ? notification::NOTIFY_SUCCESS : notification::NOTIFY_INFO
            );
            break;

        case 'confirmplan':
            $values = [];
            foreach (['plan', 'plan_credits', 'plan_price', 'plan_currency', 'monthly_limit', 'notify_emails'] as $key) {
                $values[$key] = optional_param('plan_' . $key, '', PARAM_RAW_TRIMMED);
            }
            if (!in_array($values['plan'], plan::ALL, true)) {
                redirect($url);
            }
            mod_tarsuscoach_store_plan($values);
            redirect($url, get_string('changessaved'), null, notification::NOTIFY_SUCCESS);
            break;
    }
}

/**
 * Store the plan form's values; a changed plan changes every learner's
 * result and progress.
 *
 * @param array $values The values.
 */
function mod_tarsuscoach_store_plan(array $values): void {
    global $DB;
    $old = budget::limit();
    $planchanged = plan::current() !== $values['plan'];
    config::set('plan', $values['plan']);
    config::set('plan_credits', (string) max(0, (int) $values['plan_credits']));
    config::set('plan_price', number_format(max(0, (float) str_replace(',', '.', (string) $values['plan_price'])), 2, '.', ''));
    config::set('plan_currency', core_text::substr(trim((string) $values['plan_currency']), 0, 8));
    config::set('monthly_limit', (string) max(0, (int) $values['monthly_limit']));
    config::set('notify_emails', trim((string) $values['notify_emails']));
    if (budget::limit() !== $old) {
        // The thresholds moved: warn afresh when they are crossed again.
        budget::reset_warnings();
        budget::check_thresholds();
    }
    if ($planchanged) {
        foreach ($DB->get_fieldset_select('tarsuscoach', 'id', '1 = 1') as $objid) {
            scoring::push_all((int) $objid);
        }
    }
}

// ----------------------------------------------------------------------
// The plan form
// ----------------------------------------------------------------------

$planform = null;
$confirmation = '';
if ($tab === 'plan') {
    $planform = new plan_form($url);
    $planform->set_data([
        'plan' => plan::current(),
        'plan_credits' => plan::is_pay_as_you_grow() ? '' : (string) plan::included_credits(),
        'plan_price' => number_format(plan::price_per_request(), 2, '.', ''),
        'plan_currency' => plan::currency(),
        'monthly_limit' => (string) budget::limit(),
        'notify_emails' => (string) config::get('notify_emails', ''),
    ]);
    if ($data = $planform->get_data()) {
        $values = [
            'plan' => (string) $data->plan,
            'plan_credits' => (string) max(0, (int) $data->plan_credits),
            'plan_price' => (string) $data->plan_price,
            'plan_currency' => (string) $data->plan_currency,
            'monthly_limit' => (string) max(0, (int) $data->monthly_limit),
            'notify_emails' => implode(' ', plan_form::addresses((string) $data->notify_emails)),
        ];
        $old = budget::limit();
        $new = (int) $values['monthly_limit'];
        if ($new > 0 && ($old === 0 || $new < $old)) {
            // Lowering the limit (or introducing one) can block requests this
            // very month: confirm it first.
            $params = ['tab' => 'plan', 'action' => 'confirmplan'];
            foreach ($values as $key => $value) {
                $params['plan_' . $key] = $value;
            }
            $confirmation = $OUTPUT->confirm(
                $t('limit_reduce_confirm', [
                    'old' => $old > 0 ? number_format($old) : $t('no_limit'),
                    'new' => number_format($new),
                    'used' => budget::format(budget::used()),
                ]),
                new single_button(new moodle_url('/mod/tarsuscoach/admin.php', $params), get_string('confirm'), 'post'),
                new single_button($url, get_string('cancel'), 'get')
            );
        } else {
            mod_tarsuscoach_store_plan($values);
            redirect($url, get_string('changessaved'), null, notification::NOTIFY_SUCCESS);
        }
    }
}

// ----------------------------------------------------------------------
// Panels
// ----------------------------------------------------------------------

/**
 * The plan, this month's usage and the cost: all from the brain's own usage
 * figure, the only one there is.
 *
 * @return string
 */
function mod_tarsuscoach_usage_panel(): string {
    $t = static fn(string $key, array $params = []): string => platform::txt($key, $params);
    $remote = budget::refresh_remote();
    $overage = budget::overage();
    $tarsusbilled = plan::billed_by_tarsus();
    $rows = [
        [$t('plan_billing'), $tarsusbilled ? $t('plan_billing_tarsus') : $t('plan_billing_partner')],
        [$t('plan_included'), plan::is_pay_as_you_grow()
            ? ($tarsusbilled ? $t('plan_payg_note_tarsus') : $t('plan_payg_note', ['price' => html::e(plan::money(plan::price_per_request()))]))
            : ($tarsusbilled
                ? $t('plan_included_value_tarsus', ['credits' => number_format(plan::included_credits())])
                : $t('plan_included_value', ['credits' => number_format(plan::included_credits()), 'price' => html::e(plan::money(plan::price_per_request()))]))],
        [$t('usage_remote'), $remote === null
            ? $t('usage_remote_none')
            : $t('usage_remote_value', ['credits' => budget::format($remote['credits']), 'fetched' => html::e(views::date($remote['fetched']))])],
        [$t('monthly_limit'), budget::has_limit()
            ? $t('monthly_limit_value', ['limit' => budget::format(budget::limit()), 'remaining' => budget::format(budget::remaining())])
            : $t('no_limit')],
        [$t('usage_charged'), $overage['charged'] > 0
            ? ($tarsusbilled
                ? $t('usage_charged_value_tarsus', ['requests' => budget::format($overage['charged'])])
                : $t('usage_charged_value', ['requests' => budget::format($overage['charged']), 'cost' => html::e(plan::money($overage['cost']))]))
            : $t('usage_charged_none')],
        [$t('usage_reset'), html::e(views::date(budget::next_month_start()))],
    ];
    return views::plan_usage([
        'plan' => plan::label() . (plan::is_configured() ? '' : ' (' . $t('plan_default_note') . ')'),
        'used' => budget::used(),
        'limit' => budget::limit(),
        'level' => budget::level(),
        'rows' => $rows,
        'items' => $remote === null ? [] : $remote['items'],
    ]);
}

/**
 * Which features each package includes, the current one marked: the table
 * of the partner agreement.
 *
 * @return string
 */
function mod_tarsuscoach_feature_panel(): string {
    $t = static fn(string $key, array $params = []): string => platform::txt($key, $params);
    $current = plan::current();
    $html = '<details class="tarsus-details tarsus-details--plan"><summary>' . $t('plan_features_title') . '</summary>'
        . '<p class="tarsus-muted">' . $t('plan_features_desc') . '</p>'
        . '<div class="tarsus-table-wrap"><table class="tarsus-table tarsus-table--features"><thead><tr><th>' . $t('plan_feature') . '</th>';
    foreach ([plan::ASK, plan::PRACTICE, plan::LEARN, plan::PAYG] as $plan) {
        $html .= '<th class="tarsus-center' . ($plan === $current ? ' tarsus-th--current' : '') . '">' . $t('plan_' . $plan . '_short')
            . ($plan === $current ? '<br><span class="tarsus-badge tarsus-badge--info">' . $t('plan_current') . '</span>' : '') . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach (plan::ALL_FEATURES as $feature) {
        $html .= '<tr><td>' . $t('plan_feature_' . $feature) . '</td>';
        foreach ([plan::ASK, plan::PRACTICE, plan::LEARN, plan::PAYG] as $plan) {
            $yes = plan::allows($feature, $plan);
            $html .= '<td class="tarsus-center' . ($plan === $current ? ' tarsus-td--current' : '') . '">'
                . '<span class="tarsus-badge tarsus-badge--' . ($yes ? 'success' : 'muted') . '">' . get_string($yes ? 'yes' : 'no') . '</span></td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div><p class="tarsus-muted">' . $t('plan_features_note') . '</p></details>';
}

/**
 * A button that posts an action of this page.
 *
 * @param moodle_url $url This page.
 * @param string $action The action.
 * @param string $label The button's text.
 * @param bool $primary Whether it is the main button.
 * @param array $params More values to post.
 * @return string
 */
function mod_tarsuscoach_action_button(moodle_url $url, string $action, string $label, bool $primary = false, array $params = []): string {
    global $OUTPUT;
    // Moodle 4.2 and newer name the style; 4.1 takes "primary or not".
    $style = defined('single_button::BUTTON_PRIMARY')
        ? ($primary ? constant('single_button::BUTTON_PRIMARY') : constant('single_button::BUTTON_SECONDARY'))
        : $primary;
    $button = new single_button(new moodle_url($url, ['action' => $action] + $params), $label, 'post', $style);
    $button->class = 'tarsus-inline-form d-inline-block me-2 mr-2';
    return $OUTPUT->render($button);
}

// ----------------------------------------------------------------------
// Page
// ----------------------------------------------------------------------

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'mod_tarsuscoach'));

$tabs = [new tabobject('settings', new moodle_url('/admin/settings.php', ['section' => 'modsettingtarsuscoach']), $t('cfg_tab_settings'))];
foreach (['plan', 'brains', 'deletions', 'status'] as $name) {
    $tabs[] = new tabobject($name, new moodle_url('/mod/tarsuscoach/admin.php', ['tab' => $name]), $t('cfg_tab_' . $name));
}
echo $OUTPUT->tabtree($tabs, $tab);

$banner = budget::banner();
if ($banner !== null) {
    echo $OUTPUT->notification($banner['text'], $banner['type'] === 'failure' ? 'error' : $banner['type']);
}
if (plan::needs_configuration() && $tab !== 'plan') {
    // The partner agreement asks to enforce the booked package here.
    echo $OUTPUT->notification($t('plan_not_set_hint', ['plan' => plan::label()]) . ' '
        . html_writer::link(new moodle_url('/mod/tarsuscoach/admin.php', ['tab' => 'plan']), $t('cfg_tab_plan')), 'info');
}

switch ($tab) {
    case 'plan':
        if ($confirmation !== '') {
            echo $confirmation;
            break;
        }
        echo html_writer::div(mod_tarsuscoach_usage_panel() . mod_tarsuscoach_feature_panel(), 'tarsus-app');
        $planform->display();
        break;

    case 'brains':
        $current = config::brain_id();
        try {
            $brains = client_factory::get_client()->list_brains();
        } catch (configuration_exception $e) {
            echo $OUTPUT->notification($t($e->langKey), 'info');
            break;
        } catch (transport_exception | blocked_exception $e) {
            echo $OUTPUT->notification($t('transport_error') . ' ' . s($e->getMessage()), 'error');
            break;
        }
        if ($brains === []) {
            echo $OUTPUT->notification($t('brain_none_found'), 'info');
            break;
        }
        $ids = array_column($brains, 'brainid');
        if ($current === '') {
            echo $OUTPUT->notification($t('brain_none_selected'), 'info');
        } else if (!in_array($current, $ids, true)) {
            echo $OUTPUT->notification($t('brain_missing', ['brain' => s($current)]), 'error');
        }
        $html = '<div class="tarsus-app"><p class="tarsus-muted tarsus-sandbox-note">' . $t('brain_sandbox_note') . '</p>'
            . '<div class="tarsus-table-wrap"><table class="tarsus-table"><thead><tr>'
            . '<th>' . $t('brain_col_id') . '</th><th>' . $t('brain_col_status') . '</th><th>' . $t('brain_col_language') . '</th>'
            . '<th>' . $t('brain_col_tag') . '</th><th>' . $t('brain_col_created') . '</th><th>' . $t('brain_col_action') . '</th>'
            . '</tr></thead><tbody>';
        foreach ($brains as $brain) {
            $id = (string) $brain['brainid'];
            $deleted = strpos($id, 'deleted-') === 0;
            $active = !empty($brain['active']) && !$deleted;
            $inuse = $id === $current;
            $state = $deleted ? ['muted', 'brain_deleted'] : ($active ? ['success', 'brain_active'] : ['neutral', 'brain_inactive']);
            $html .= '<tr><td><code>' . s($id) . '</code>'
                . ($inuse ? ' <span class="tarsus-badge tarsus-badge--info">' . $t('brain_inuse') . '</span>' : '') . '</td>'
                . '<td><span class="tarsus-badge tarsus-badge--' . $state[0] . '">' . $t($state[1]) . '</span></td>'
                . '<td>' . s((string) $brain['language']) . '</td>'
                . '<td>' . s((string) $brain['tag']) . '</td>'
                . '<td class="tarsus-nowrap">' . ((int) $brain['timecreated'] > 0 ? s(views::date((int) $brain['timecreated'])) : '&ndash;') . '</td><td>'
                . ($active && !$inuse ? mod_tarsuscoach_action_button($url, 'activatebrain', $t('brain_activate'), false, ['brainid' => $id]) : '')
                . '</td></tr>';
        }
        echo $html . '</tbody></table></div></div>';
        break;

    case 'deletions':
        $rows = (new reconciler())->outstanding();
        if ($rows === []) {
            echo $OUTPUT->notification($t('deletions_none'), 'success');
            break;
        }
        echo $OUTPUT->notification($t('deletions_pending', ['count' => count($rows)]), 'info');
        $html = '<div class="tarsus-app">' . mod_tarsuscoach_action_button($url, 'retrydeletions', $t('deletions_retry'), true)
            . '<div class="tarsus-table-wrap"><table class="tarsus-table"><thead><tr>'
            . '<th>' . $t('deletions_resource') . '</th><th>' . $t('deletions_container') . '</th><th>' . $t('deletions_since') . '</th>'
            . '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $course = $DB->get_record('course', ['id' => (int) $row->containerref], 'id, fullname');
            $title = $course ? format_string($course->fullname) . ' (' . $course->id . ')' : $t('container_gone', ['ref' => (int) $row->containerref]);
            $html .= '<tr><td><code>' . s($row->resourceid) . '</code></td><td>' . s($title) . '</td>'
                . '<td class="tarsus-nowrap">' . s(views::date((int) $row->timemodified)) . '</td></tr>';
        }
        echo $html . '</tbody></table></div></div>';
        break;

    case 'status':
        $snapshot = (new pipeline())->snapshot();
        $breaker = $snapshot['breaker'];
        $bucket = $snapshot['bucket'];
        $rows = [
            [$t('mockmode'), $yesno(config::mock_mode())],
            [$t('status_configured'), $yesno(config::is_connected())],
            [$t('status_customer'), config::license_key() !== '' ? $t('status_customer_tarsus') : $t('status_customer_partner')],
            [$t('status_plan'), plan::label() . (plan::is_configured() ? '' : ' (' . $t('plan_default_note') . ')')],
            [$t('killswitch'), $yesno(config::killswitch())],
            [$t('status_breaker'), ($breaker->state === 'open'
                ? $t('status_breaker_open', ['since' => views::date((int) $breaker->opensince)]) : $t('status_breaker_closed'))
                . ' (' . $t('status_failures', ['count' => (int) $breaker->failurecount]) . ')'],
            [$t('status_bucket'), $t('status_bucket_value', [
                // A bucket never used yet is full.
                'tokens' => number_format((int) $bucket->lastrefill === 0 ? (float) config::rate_limit() : (float) $bucket->tokens, 1),
                'limit' => config::rate_limit(),
            ])],
        ];
        $html = '<div class="tarsus-app"><div class="tarsus-dashboard__bulk mb-3">'
            . mod_tarsuscoach_action_button($url, 'testconnection', $t('status_test'), true)
            . mod_tarsuscoach_action_button($url, 'resetbreaker', $t('status_reset_breaker'))
            . mod_tarsuscoach_action_button($url, 'refreshusage', $t('usage_refresh'))
            . mod_tarsuscoach_action_button($url, 'refreshvoices', $t('voice_refresh'))
            . '</div>';
        $html .= mod_tarsuscoach_usage_panel();
        $html .= '<h3 class="tarsus-h3">' . $t('status_guardrails') . '</h3><div class="tarsus-table-wrap"><table class="tarsus-table"><tbody>';
        foreach ($rows as [$label, $value]) {
            $html .= '<tr><th scope="row" style="width:30%">' . $label . '</th><td>' . s($value) . '</td></tr>';
        }
        $html .= '</tbody></table></div>';

        // Index map totals.
        $html .= '<h3 class="tarsus-h3">' . $t('status_index') . '</h3>';
        $counts = (new repository())->count_by_status();
        if ($counts === []) {
            $html .= '<p class="tarsus-muted">' . $t('status_index_none') . '</p>';
        } else {
            $html .= '<div class="tarsus-table-wrap"><table class="tarsus-table"><tbody>';
            foreach ($counts as $status => $count) {
                $html .= '<tr><th scope="row" style="width:30%">' . s(status::label((string) $status)) . '</th><td>' . (int) $count . '</td></tr>';
            }
            $html .= '</tbody></table></div>';
        }

        // Requests of the last 24 hours.
        $html .= '<h3 class="tarsus-h3">' . $t('status_usage') . '</h3>';
        $summary = (new usage_meter())->summary(time() - 86400);
        if ($summary === []) {
            $html .= '<p class="tarsus-muted">' . $t('status_usage_none') . '</p>';
        } else {
            $html .= '<div class="tarsus-table-wrap"><table class="tarsus-table"><thead><tr><th>' . $t('status_operation') . '</th><th>'
                . $t('status_calls') . '</th></tr></thead><tbody>';
            foreach ($summary as $operation => $figures) {
                $html .= '<tr><td>' . s($operation) . '</td><td>' . (int) $figures['calls'] . '</td></tr>';
            }
            $html .= '</tbody></table></div>';
        }
        echo $html . '</div>';
        break;
}

echo $OUTPUT->footer();
