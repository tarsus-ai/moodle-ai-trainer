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

namespace mod_tarsuscoach\local\output;

use mod_tarsuscoach\local\support\budget;
use mod_tarsuscoach\local\support\platform;

/**
 * Server-rendered markup of the plugin's screens, from the templates in
 * templates/. The chat's cards are rendered by the coach script
 * (amd/src/coach.js); everything here is the frame they live in, and the
 * teacher's material list.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class views {
    /**
     * The coach: feed, progress bar, composer with the mode switch, the
     * recorder for spoken questions, the bar of the live interaction, and
     * the panel that slides in from the right (sources for everyone;
     * settings, evaluation and history for teachers).
     *
     * @param string $containerid The id of the coach's root element.
     * @param array $config The client configuration (JSON-encoded into the page).
     * @param string $help The help button (views::help()), or ''.
     * @return string
     */
    public static function chat(string $containerid, array $config, string $help = ''): string {
        $modes = array_values((array) $config['modes']);
        $context = [
            'id' => $containerid,
            'config' => html::json($config),
            'help' => $help,
            'demo' => !empty($config['demo']),
            'teacher' => !empty($config['teacher']),
            'panels' => [],
            'voice' => !empty($config['voice']),
            'voicebuttons' => [],
            'discuss' => in_array('discuss', $modes, true),
            'modes' => [],
            'icons' => [
                'jumptop' => html::icon('jump_top'),
                'reply' => html::icon('reply'),
                'close' => html::icon('close'),
                'send' => html::icon('send'),
                'mic' => html::icon('mic'),
                'keyboard' => html::icon('keyboard'),
                'discuss' => html::icon('mode_discuss'),
            ],
        ];
        foreach ($modes as $mode) {
            $context['modes'][] = [
                'mode' => (string) $mode,
                'icon' => html::icon('mode_' . $mode),
                'label' => platform::txt('mode_' . $mode),
            ];
        }
        if ($context['teacher']) {
            foreach (['settings' => 'settings', 'stats' => 'chart', 'history' => 'history'] as $panel => $icon) {
                $context['panels'][] = [
                    'panel' => $panel,
                    'icon' => html::icon($icon),
                    'label' => platform::txt('panel_' . $panel),
                ];
            }
        }
        if ($context['voice']) {
            $buttons = [
                ['voice-start', 'record', 'voice_start'],
                ['voice-pause', 'audio_pause', 'voice_pause'],
                ['voice-resume', 'record', 'voice_resume'],
                ['voice-stop', 'stop', 'voice_stop'],
                ['voice-play', 'audio_play', 'voice_play'],
                ['voice-discard', 'trash', 'voice_discard'],
            ];
            foreach ($buttons as $index => [$action, $icon, $label]) {
                $context['voicebuttons'][] = [
                    'action' => $action,
                    'icon' => html::icon($icon),
                    'label' => platform::txt($label),
                    'record' => $index === 0,
                ];
            }
        }
        return self::render('coach', $context);
    }

    /**
     * The teacher's material list with status, inclusion switch and actions.
     *
     * @param string $rootid The id of the list's root element.
     * @param array $rows presenter rows plus title, objecttitle, url, typelabel and included.
     * @param string $pager The pager (views::pager()), or ''.
     * @param array $config The client configuration (JSON-encoded into the page).
     * @param array $counts The figures above the list: total, indexed, included.
     * @return string
     */
    public static function materials(string $rootid, array $rows, string $pager, array $config, array $counts): string {
        $context = [
            'id' => $rootid,
            'config' => html::json($config),
            'counttotal' => (int) ($counts['total'] ?? 0),
            'countindexed' => (int) ($counts['indexed'] ?? 0),
            'countincluded' => (int) ($counts['included'] ?? 0),
            'hasrows' => $rows !== [],
            'rows' => [],
            'pager' => $pager,
            'icons' => ['refresh' => html::icon('refresh')],
        ];
        foreach ($rows as $row) {
            $actions = [];
            $buttons = [
                ['index', 'tarsus-btn--primary', $row['canindex']],
                ['reindex', '', $row['canreindex']],
                ['retry', 'tarsus-btn--warning', $row['canretry']],
                ['remove', 'tarsus-btn--danger', $row['canremove']],
            ];
            foreach ($buttons as [$action, $modifier, $visible]) {
                $actions[] = [
                    'action' => $action,
                    'modifier' => $modifier,
                    'visible' => (bool) $visible,
                    'label' => platform::txt('action_' . $action),
                ];
            }
            $context['rows'][] = [
                'resourceid' => (string) $row['resourceid'],
                'hasurl' => $row['url'] !== '',
                'url' => (string) $row['url'],
                'objecttitle' => (string) $row['objecttitle'],
                'showtitle' => $row['title'] !== $row['objecttitle'],
                'title' => (string) $row['title'],
                'typelabel' => (string) $row['typelabel'],
                'badgeclass' => (string) $row['badgeclass'],
                'statuslabel' => (string) $row['statuslabel'],
                'inflight' => (bool) $row['inflight'],
                'included' => (bool) $row['included'],
                'caninclude' => (bool) $row['caninclude'],
                'actions' => $actions,
            ];
        }
        return self::render('materials', $context);
    }

    /**
     * Simple previous/next paging.
     *
     * @param int $page This page, from 0.
     * @param int $pages How many pages there are.
     * @param callable $url The address of a page: fn(int $page): string.
     * @return string The pager, or '' for a single page.
     */
    public static function pager(int $page, int $pages, callable $url): string {
        if ($pages <= 1) {
            return '';
        }
        return self::render('pager', [
            'hasprevious' => $page > 0,
            'previous' => $page > 0 ? $url($page - 1) : '',
            'state' => platform::txt('pager_state', ['page' => $page + 1, 'pages' => $pages]),
            'hasnext' => $page < $pages - 1,
            'next' => $page < $pages - 1 ? $url($page + 1) : '',
        ]);
    }

    /**
     * The help button with its panel of support links and videos. Empty
     * when nothing is configured.
     *
     * @param array $links The links: title, url, embed (help::links()).
     * @param string $id The id of the panel.
     * @return string
     */
    public static function help(array $links, string $id = 'tarsus-help'): string {
        if ($links === []) {
            return '';
        }
        $items = [];
        foreach ($links as $link) {
            $video = $link['embed'] !== '';
            $items[] = [
                'title' => $link['title'],
                'url' => $link['url'],
                'embed' => $link['embed'],
                'video' => $video,
                'icon' => html::icon($video ? 'video' : 'link', 'tarsus-icon tarsus-help__icon'),
            ];
        }
        return self::render('help', [
            'id' => $id,
            'links' => $items,
            'icons' => ['help' => html::icon('help'), 'close' => html::icon('close')],
        ]);
    }

    /**
     * A horizontal usage bar: used against limit, coloured by severity.
     *
     * @param float $used The AI credits used.
     * @param int $limit The limit (0: none).
     * @param string $level How close the limit is (budget::level()).
     * @return string
     */
    public static function usage_bar(float $used, int $limit, string $level): string {
        return self::render('usage_bar', self::usage_context($used, $limit, $level));
    }

    /**
     * The administrator's plan and usage panel on the status page.
     *
     * @param array $data plan, used, limit, level, rows (label and value, HTML) and items (the brain's figures).
     * @return string
     */
    public static function plan_usage(array $data): string {
        $rows = [];
        foreach ($data['rows'] as [$label, $value]) {
            $rows[] = ['label' => $label, 'value' => $value];
        }
        $items = [];
        foreach ($data['items'] as $item) {
            if ((float) $item['credits'] <= 0) {
                continue;
            }
            $items[] = [
                'service' => (string) $item['service'],
                'name' => (string) $item['name'],
                'credits' => budget::format((float) $item['credits']),
            ];
        }
        return self::render('plan_usage', [
            'plan' => (string) $data['plan'],
            'usage' => self::usage_context((float) $data['used'], (int) $data['limit'], (string) $data['level']),
            'rows' => $rows,
            'hasitems' => $data['items'] !== [],
            'items' => $items,
        ]);
    }

    /**
     * A number with at most one decimal and no trailing zero.
     *
     * @param float $value The number.
     * @return string
     */
    public static function figure(float $value): string {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }

    /**
     * A date as the user's language writes it.
     *
     * @param int $timestamp The time.
     * @return string
     */
    public static function date(int $timestamp): string {
        return platform::date($timestamp);
    }

    /**
     * The usage bar's figures.
     *
     * @param float $used The AI credits used.
     * @param int $limit The limit (0: none).
     * @param string $level How close the limit is.
     * @return array The context of mod_tarsuscoach/usage_bar.
     */
    private static function usage_context(float $used, int $limit, string $level): array {
        $percent = $limit > 0 ? min(100, $used / $limit * 100) : 0;
        // The brain's figure, written the way the user's language writes numbers.
        $label = $limit > 0
            ? platform::txt('usage_bar_label', [
                'used' => budget::format($used),
                'limit' => budget::format((float) $limit),
                'percent' => number_format($percent, 0),
            ])
            : platform::txt('usage_bar_nolimit', ['used' => budget::format($used)]);
        return ['level' => $level, 'label' => $label, 'width' => number_format($percent, 1, '.', '')];
    }

    /**
     * A template of this plugin.
     *
     * @param string $name The template's name.
     * @param array $context Its context.
     * @return string
     */
    private static function render(string $name, array $context): string {
        global $OUTPUT;
        return $OUTPUT->render_from_template('mod_tarsuscoach/' . $name, $context);
    }
}
