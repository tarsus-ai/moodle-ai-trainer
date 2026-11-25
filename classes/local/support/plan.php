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

/**
 * The customer's Tarsus package and what it includes.
 *
 * Four packages exist, and each contains the one before it: Ask Question
 * (ASK) answers questions from the documents; Knowledge Test (PRACTICE)
 * adds adaptive quizzes; Build Knowledge (LEARN) adds the full coach with
 * the audio explainer, voice input, the agent (AI-driven) mode and the
 * live interaction; Pay as you grow (PAYG) has access to everything. The matrix in {@see FEATURES}
 * is the table "Funktionen je Paket" of the Tarsus partner agreement
 * (tarsus.ai/impressum/partnervereinbarung, 12 November 2026); the partner
 * has to enforce the booked package in the integration, so everything the
 * plugin offers goes through {@see allows()}.
 *
 * The subscription packages include a monthly amount of AI interactions
 * (100,000 by default) that does not carry over; interactions beyond it
 * are charged per interaction (0.32 EUR flat for partners). Pay as you
 * grow includes nothing: every interaction is charged. Custom agreements
 * change the figures, so the partner sets them in the plugin configuration.
 * A customer with a Tarsus license key is billed by Tarsus directly.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class plan
{
    /** Build Knowledge subscription: every feature. */
    public const LEARN = 'learn';
    /** Knowledge Test subscription: Ask and Test, trainer-driven only. */
    public const PRACTICE = 'practice';
    /** Ask Question subscription: Ask only, trainer-driven only. */
    public const ASK = 'ask';
    /** Pay as you grow: every feature, every interaction charged. */
    public const PAYG = 'payg';

    public const ALL = [self::LEARN, self::PRACTICE, self::ASK, self::PAYG];

    /** Ask a question: the AI answers from the documents. */
    public const FEATURE_ASK = 'ask';
    /** Test knowledge: the AI creates and marks quizzes. */
    public const FEATURE_TEST = 'test';
    /** Build knowledge: the coach explains step by step. */
    public const FEATURE_BUILD = 'build';
    /** The audio explainer of the coach (Generate Stack Audio Explainer). */
    public const FEATURE_AUDIO_EXPLAINER = 'audio_explainer';
    /** Questions or answers spoken into the coach (audio_content). */
    public const FEATURE_VOICE_INPUT = 'voice_input';
    /** Agent mode: the AI-driven interaction type. */
    public const FEATURE_AI_DRIVEN = 'ai_driven';
    /** Live interaction: a spoken conversation with the coach (state discuss). */
    public const FEATURE_DISCUSS = 'discuss';

    /** Every feature a package can include, in the agreement's order. */
    public const ALL_FEATURES = [
        self::FEATURE_ASK,
        self::FEATURE_TEST,
        self::FEATURE_BUILD,
        self::FEATURE_AUDIO_EXPLAINER,
        self::FEATURE_VOICE_INPUT,
        self::FEATURE_AI_DRIVEN,
        self::FEATURE_DISCUSS,
    ];

    /** @var string[] The modes, in switcher order. */
    public const MODES = ['ask', 'test', 'build', 'discuss'];

    /**
     * "Funktionen je Paket": what each package includes. Standard narration
     * of text (Audio Generation) is not part of the table and stays
     * available everywhere; the plugin does not use it.
     *
     * @var array<string, string[]>
     */
    public const FEATURES = [
        self::ASK => [self::FEATURE_ASK],
        self::PRACTICE => [self::FEATURE_ASK, self::FEATURE_TEST],
        self::LEARN => self::ALL_FEATURES,
        self::PAYG => self::ALL_FEATURES,
    ];

    /** AI interactions a subscription includes per month unless agreed otherwise. */
    public const DEFAULT_CREDITS = 100000;
    /** Price of one interaction beyond the included amount unless agreed otherwise. */
    public const DEFAULT_PRICE = 0.32;
    public const DEFAULT_CURRENCY = 'EUR';

    /** The teacher writes the didactics; the brain answers in text and pictures. */
    public const INTERACTION_TRAINER = 'trainer-driven';
    /** The brain may answer with interactive HTML pages (animations, simulations). */
    public const INTERACTION_AI = 'ai-driven';
    public const INTERACTIONS = [self::INTERACTION_TRAINER, self::INTERACTION_AI];

    /**
     * The configured package; Build Knowledge until the partner sets another.
     */
    public static function current(): string {
        $plan = (string) config::get('plan', '');
        return in_array($plan, self::ALL, true) ? $plan : self::LEARN;
    }

    public static function is_configured(): bool {
        return in_array((string) config::get('plan', ''), self::ALL, true);
    }

    /**
     * Whether the package still has to be set although a live connection
     * exists: the agreement asks the partner to enforce the booked
     * package, and until it is set every feature is reachable.
     */
    public static function needs_configuration(): bool {
        return !self::is_configured() && !config::mock_mode() && config::api_key() !== '';
    }

    public static function label(?string $plan = null): string {
        return platform::txt('plan_' . ($plan ?? self::current()));
    }

    /**
     * The features a package includes.
     *
     * @return string[]
     */
    public static function features(?string $plan = null): array {
        return self::FEATURES[$plan ?? self::current()] ?? self::FEATURES[self::LEARN];
    }

    /**
     * Whether a package includes a feature. Unknown features are never
     * included: a typo cannot open a door.
     */
    public static function allows(string $feature, ?string $plan = null): bool {
        return in_array($feature, self::features($plan), true);
    }

    /**
     * The modes a package includes, in switcher order.
     *
     * @return string[]
     */
    public static function modes(?string $plan = null): array {
        return array_values(array_filter(
            self::MODES,
            static fn(string $mode): bool => self::allows($mode, $plan)
        ));
    }

    public static function allows_mode(string $mode, ?string $plan = null): bool {
        return in_array($mode, self::MODES, true) && self::allows($mode, $plan);
    }

    /**
     * The audio explainer of learning units: Build Knowledge and Pay as
     * you grow.
     */
    public static function allows_audio(?string $plan = null): bool {
        return self::allows(self::FEATURE_AUDIO_EXPLAINER, $plan);
    }

    /**
     * Spoken questions and answers: Build Knowledge and Pay as you grow.
     */
    public static function allows_voice_input(?string $plan = null): bool {
        return self::allows(self::FEATURE_VOICE_INPUT, $plan);
    }

    /**
     * AI-driven interaction (interactive HTML answers and units): Build
     * Knowledge and Pay as you grow.
     */
    public static function allows_ai_driven(?string $plan = null): bool {
        return self::allows(self::FEATURE_AI_DRIVEN, $plan);
    }

    /**
     * The interaction type that may actually be sent for a wanted one: the
     * package decides, not the object's setting.
     */
    public static function interaction_type(string $wanted): string {
        return ($wanted === self::INTERACTION_AI && self::allows_ai_driven()) ? self::INTERACTION_AI : self::INTERACTION_TRAINER;
    }

    public static function is_pay_as_you_grow(): bool {
        return self::current() === self::PAYG;
    }

    /**
     * Whether Tarsus bills this customer directly: an existing Tarsus
     * customer carries a license key, and the partner's prices and cost
     * estimates do not apply.
     */
    public static function billed_by_tarsus(): bool {
        return config::license_key() !== '';
    }

    /**
     * AI interactions the package includes per calendar month; none for
     * Pay as you grow.
     */
    public static function included_credits(): int {
        if (self::is_pay_as_you_grow()) {
            return 0;
        }
        $value = config::get('plan_credits');
        return ($value === null || $value === '') ? self::DEFAULT_CREDITS : max(0, (int) $value);
    }

    /**
     * Price of one interaction beyond the included amount (or of every
     * interaction on Pay as you grow).
     */
    public static function price_per_request(): float {
        $value = config::get('plan_price');
        return ($value === null || $value === '') ? self::DEFAULT_PRICE : max(0.0, (float) str_replace(',', '.', $value));
    }

    public static function currency(): string {
        $value = trim((string) config::get('plan_currency', ''));
        return $value !== '' ? $value : self::DEFAULT_CURRENCY;
    }

    /**
     * Money formatted for the interface language.
     */
    public static function money(float $amount): string {
        $decimal = platform::user_language() === 'de' ? ',' : '.';
        $thousands = platform::user_language() === 'de' ? '.' : ',';
        return number_format($amount, 2, $decimal, $thousands) . ' ' . self::currency();
    }
}
