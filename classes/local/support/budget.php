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

namespace mod_tarsuscoach\local\support;

use mod_tarsuscoach\local\client\client_factory;
use mod_tarsuscoach\local\guardrail\pipeline;

/**
 * The monthly AI-credit budget: what the brain charged this calendar
 * month, the limit the customer set, and the warnings on the way there.
 *
 * There is one figure: the brain's own usage, as Tarsus reports it (for
 * the whole brain, so it also covers other systems using it). The plugin
 * estimates nothing. The figure is fetched at most once a minute and kept,
 * by the cron job, the status pages and before a call that may cost
 * credits; one fetch runs at a time.
 *
 * The limit is in AI credits per calendar month; 0 means no limit.
 * Crossing 50, 75, 90 and 100 percent of it raises a banner for teachers
 * and administrators and sends one mail per threshold and month.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class budget
{
    /** Percentages of the limit that raise a warning. */
    public const THRESHOLDS = [50, 75, 90, 100];

    /** Seconds a fetched brain figure stays fresh. */
    public const REMOTE_TTL = 60;

    /** Seconds one fetch may take before another process may start its own. */
    private const FETCH_LOCK = 20;

    private const KEY_REMOTE = 'usage_remote';
    private const KEY_FETCHING = 'usage_fetching';
    private const KEY_NOTIFIED = 'usage_notified';

    // ------------------------------------------------------------------
    // Period
    // ------------------------------------------------------------------

    /**
     * Midnight of the first day of the month containing $time, in the
     * installation's time zone.
     */
    public static function month_start(?int $time = null): int {
        $date = self::local($time ?? time());
        $date->modify('first day of this month midnight');
        return $date->getTimestamp();
    }

    public static function next_month_start(?int $time = null): int {
        $date = self::local($time ?? time());
        $date->modify('first day of next month midnight');
        return $date->getTimestamp();
    }

    public static function month_key(?int $time = null): string {
        return self::local($time ?? time())->format('Y-m');
    }

    private static function local(int $time): \DateTime {
        $zone = null;
        try {
            $zone = new \DateTimeZone(platform::timezone());
        } catch (\Throwable $e) {
            $zone = null;
        }
        $date = new \DateTime('@' . $time);
        $date->setTimezone($zone ?? new \DateTimeZone(date_default_timezone_get()));
        return $date;
    }

    // ------------------------------------------------------------------
    // Limit and usage
    // ------------------------------------------------------------------

    /**
     * The customer's monthly limit in AI credits; 0 means no limit. Until
     * one is set, the plan's included credits (or the default amount on
     * Pay as you grow) bound the spending.
     */
    public static function limit(): int {
        $value = config::fresh('monthly_limit');
        if ($value === null || $value === '') {
            $included = plan::included_credits();
            return $included > 0 ? $included : plan::DEFAULT_CREDITS;
        }
        return max(0, (int) $value);
    }

    public static function has_limit(): bool {
        return self::limit() > 0;
    }

    /**
     * The brain's figure for this month, if one was fetched this month.
     *
     * @return array{credits: float, fetched: int, items: array<int, array{key: string, name: string, service: string, credits: float}>}|null
     */
    public static function remote(): ?array {
        $raw = config::fresh(self::KEY_REMOTE);
        if ($raw === null || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || (string) ($data['month'] ?? '') !== self::month_key()) {
            return null;
        }
        return [
            'credits' => round((float) ($data['credits'] ?? 0), 2),
            'fetched' => (int) ($data['fetched'] ?? 0),
            'items' => is_array($data['items'] ?? null) ? $data['items'] : [],
        ];
    }

    /**
     * Ask the brain what it charged this month and remember the answer.
     * Failures keep the previous figure; nothing is thrown.
     *
     * @return array{credits: float, fetched: int, items: array}|null The current figure.
     */
    public static function refresh_remote(bool $force = false): ?array {
        $current = self::remote();
        if (!$force && $current !== null && time() - $current['fetched'] < self::REMOTE_TTL) {
            return $current;
        }
        // One fetch at a time: another process fetching right now will
        // store the figure.
        $fetching = (int) config::fresh(self::KEY_FETCHING);
        if (!$force && $current !== null && time() - $fetching < self::FETCH_LOCK) {
            return $current;
        }
        config::set(self::KEY_FETCHING, (string) time());
        try {
            $usage = (new pipeline())->execute(
                fn() => client_factory::get_client()->get_usage(self::month_start(), time())
            );
        } catch (\Throwable $e) {
            platform::log('usage fetch failed: ' . $e->getMessage());
            return $current;
        } finally {
            config::set(self::KEY_FETCHING, null);
        }
        $items = [];
        foreach ((array) ($usage['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = [
                'key' => (string) ($item['key'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'service' => (string) ($item['service'] ?? ''),
                'credits' => round((float) ($item['credits'] ?? 0), 2),
            ];
        }
        usort($items, static fn(array $a, array $b): int => $b['credits'] <=> $a['credits']);
        $figure = [
            'month' => self::month_key(),
            'credits' => max(0.0, round((float) ($usage['total'] ?? 0), 2)),
            'fetched' => time(),
            'items' => $items,
        ];
        config::set(self::KEY_REMOTE, (string) json_encode($figure));
        self::check_thresholds();
        return ['credits' => $figure['credits'], 'fetched' => $figure['fetched'], 'items' => $items];
    }

    /**
     * Credits the brain charged this month, as last fetched; 0 until a
     * figure exists for the month.
     */
    public static function used(): float {
        $remote = self::remote();
        return $remote === null ? 0.0 : $remote['credits'];
    }

    public static function percent(): float {
        $limit = self::limit();
        if ($limit <= 0) {
            return 0.0;
        }
        return min(999.0, round(self::used() / $limit * 100, 1));
    }

    public static function remaining(): float {
        return max(0.0, self::limit() - self::used());
    }

    /**
     * Whether the month's limit is reached. With $refresh the brain's
     * figure is fetched first when it is older than a minute.
     */
    public static function exhausted(bool $refresh = false): bool {
        if (!self::has_limit()) {
            return false;
        }
        if ($refresh) {
            self::refresh_remote();
        }
        return self::used() >= self::limit();
    }

    /**
     * Severity of the current usage against the limit.
     */
    public static function level(): string {
        if (!self::has_limit()) {
            return 'ok';
        }
        $percent = self::percent();
        if ($percent >= 100) {
            return 'exhausted';
        }
        if ($percent >= 90) {
            return 'critical';
        }
        if ($percent >= 75) {
            return 'warning';
        }
        if ($percent >= 50) {
            return 'notice';
        }
        return 'ok';
    }

    // ------------------------------------------------------------------
    // Cost estimate
    // ------------------------------------------------------------------

    /**
     * AI credits this month beyond the package's included amount, and
     * what they cost at the agreed price per credit - both from the
     * brain's figure. On Pay as you grow every credit counts. A customer
     * with a license key is billed by Tarsus, so no partner price applies
     * and the cost is 0.
     *
     * @return array{used: float, cost: float, included: int, charged: float}
     */
    public static function overage(): array {
        return self::overage_of(self::used(), plan::included_credits(), plan::price_per_request(), plan::billed_by_tarsus());
    }

    /**
     * The arithmetic of {@see overage()}: credits beyond the included
     * amount, priced per credit.
     *
     * @return array{used: float, cost: float, included: int, charged: float}
     */
    public static function overage_of(float $used, int $included, float $price, bool $billedbytarsus = false): array {
        $charged = round(max(0.0, $used - max(0, $included)), 2);
        return [
            'used' => round($used, 2),
            'charged' => $charged,
            'included' => max(0, $included),
            'cost' => $billedbytarsus ? 0.0 : round($charged * $price, 2),
        ];
    }

    /**
     * A credit figure for people: whole numbers without decimals, fractions
     * with two (1,234.57 / 1.234,57).
     */
    public static function format(float $credits): string {
        $decimals = abs($credits - round($credits)) < 0.005 ? 0 : 2;
        $german = platform::user_language() === 'de';
        return number_format($credits, $decimals, $german ? ',' : '.', $german ? '.' : ',');
    }

    // ------------------------------------------------------------------
    // Warnings
    // ------------------------------------------------------------------

    /**
     * The banner for teachers and administrators, or null while usage is
     * below half of the limit.
     *
     * @return array{type: string, text: string}|null
     */
    public static function banner(): ?array {
        $level = self::level();
        if ($level === 'ok') {
            return null;
        }
        $params = [
            'used' => self::format(self::used()),
            'limit' => self::format(self::limit()),
            'percent' => number_format(self::percent(), 0),
            'reset' => platform::date(self::next_month_start()),
        ];
        if ($level === 'exhausted') {
            return ['type' => 'failure', 'text' => platform::txt('budget_banner_exhausted', $params)];
        }
        return ['type' => $level === 'notice' ? 'info' : 'warning', 'text' => platform::txt('budget_banner_warning', $params)];
    }

    /**
     * Send the mails for thresholds crossed this month that were not
     * announced yet. Cheap when nothing changed: one config read.
     */
    public static function check_thresholds(): void {
        if (!self::has_limit()) {
            return;
        }
        $month = self::month_key();
        $raw = config::fresh(self::KEY_NOTIFIED);
        $notified = json_decode((string) $raw, true);
        $done = is_array($notified) && is_array($notified[$month] ?? null) ? array_map('intval', $notified[$month]) : [];

        $percent = null;
        $pending = [];
        foreach (self::THRESHOLDS as $threshold) {
            if (in_array($threshold, $done, true)) {
                continue;
            }
            if ($percent === null) {
                $percent = self::percent();
            }
            if ($percent >= $threshold) {
                $pending[] = $threshold;
            }
        }
        if ($pending === []) {
            return;
        }
        // Only the highest new threshold is announced; the lower ones are
        // implied and marked done.
        $highest = max($pending);
        self::notify($highest);
        config::set(self::KEY_NOTIFIED, (string) json_encode([$month => array_values(array_unique(array_merge($done, $pending)))]));
    }

    /**
     * Recipients of the warnings: the configured addresses, else the
     * installation's administrator address.
     *
     * @return string[]
     */
    public static function recipients(): array {
        $raw = (string) config::get('notify_emails', '');
        $addresses = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $address) {
            $address = trim($address);
            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $addresses[] = $address;
            }
        }
        if ($addresses === []) {
            $admin = platform::admin_email();
            if ($admin !== '' && filter_var($admin, FILTER_VALIDATE_EMAIL)) {
                $addresses[] = $admin;
            }
        }
        return array_values(array_unique($addresses));
    }

    private static function notify(int $threshold): void {
        $recipients = self::recipients();
        if ($recipients === []) {
            return;
        }
        $params = [
            'percent' => (string) $threshold,
            'used' => self::format(self::used()),
            'limit' => self::format(self::limit()),
            'reset' => platform::date(self::next_month_start()),
            'site' => platform::site_title(),
        ];
        $subject = platform::txt($threshold >= 100 ? 'budget_mail_subject_exhausted' : 'budget_mail_subject', $params);
        $body = platform::txt($threshold >= 100 ? 'budget_mail_body_exhausted' : 'budget_mail_body', $params);
        platform::mail($recipients, $subject, $body);
    }

    /**
     * Forget the month's warning record (the limit was raised).
     */
    public static function reset_warnings(): void {
        config::set(self::KEY_NOTIFIED, null);
    }
}
