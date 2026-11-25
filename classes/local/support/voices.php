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

/**
 * The brain's voices: a cached catalogue and the choice for a learner.
 *
 * The brain lists voices and demands one by name on every narration call.
 * The admin decides: one voice for everyone, or the first voice of the
 * learner's language with the configured voice as the fallback.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class voices
{
    /** Voice mode: one voice for everyone. */
    public const MODE_FIXED = 'fixed';
    /** Voice mode: the first voice of the learner's language, else the configured one. */
    public const MODE_AUTO = 'auto';
    /** The voice used when none is configured. */
    public const DEFAULT_VOICE = 'Daniel';

    /** Seconds the catalogue is kept before asking the brain again. */
    private const CACHE_TTL = 86400;

    /**
     * The brain's voices, asked for at most once a day unless refreshed.
     * While the brain cannot be reached, the last known list stands.
     *
     * @param bool $refresh Whether to ask the brain even when the stored list is recent.
     * @return array<int, array{alias: string, country: string, gender: string}>
     */
    public static function catalogue(bool $refresh = false): array {
        $cached = json_decode((string) config::get('voices_cache', ''), true);
        $fetched = (int) config::get('voices_cachetime', '0');
        if (!$refresh && is_array($cached) && $fetched > time() - self::CACHE_TTL) {
            return $cached;
        }

        try {
            $voices = client_factory::get_client()->list_voices();
        } catch (\Throwable $e) {
            // An unreachable brain keeps the last known list, however old.
            return is_array($cached) ? $cached : [];
        }
        if ($voices !== []) {
            config::set('voices_cache', (string) json_encode($voices));
            config::set('voices_cachetime', (string) time());
        }
        return $voices;
    }

    /**
     * The voices as options for a menu; asks the brain unless a catalogue
     * from the last day is stored.
     *
     * @return array<string, string> alias => "Alias (language, gender)".
     */
    public static function options(): array {
        return self::options_of(self::catalogue());
    }

    /**
     * The options from the stored catalogue only: never asks the brain (for
     * the settings page, which Moodle builds for every admin page).
     *
     * @return array<string, string> alias => "Alias (language, gender)".
     */
    public static function cached_options(): array {
        $cached = json_decode((string) config::get('voices_cache', ''), true);
        return self::options_of(is_array($cached) ? $cached : []);
    }

    /**
     * Menu options for a catalogue: each voice's alias with its language
     * and gender, named in the user's language.
     *
     * @param array<int, array{alias: string, country: string, gender: string}> $catalogue The voices.
     * @return array<string, string> alias => "Alias (language, gender)".
     */
    private static function options_of(array $catalogue): array {
        $languages = get_string_manager()->get_list_of_languages();
        $options = [];
        foreach ($catalogue as $voice) {
            $code = strtolower(substr((string) $voice['country'], 0, 2));
            $language = (string) ($languages[$code] ?? $voice['country']);
            $gender = in_array($voice['gender'], ['female', 'male'], true)
                ? platform::txt('voice_' . $voice['gender']) : (string) $voice['gender'];
            $options[(string) $voice['alias']] = platform::txt('voice_label', [
                'alias' => (string) $voice['alias'], 'language' => $language, 'gender' => $gender,
            ]);
        }
        return $options;
    }

    /**
     * The voice to read to a learner with.
     */
    public static function choose(string $language): string {
        $fixed = trim((string) config::get('voice', ''));
        if ($fixed === '') {
            $fixed = self::DEFAULT_VOICE;
        }
        if (config::get('voice_mode', self::MODE_AUTO) === self::MODE_FIXED) {
            return $fixed;
        }

        $code = strtolower(substr($language, 0, 2));
        foreach (self::catalogue() as $voice) {
            if (strtolower(substr((string) $voice['country'], 0, 2)) === $code) {
                return (string) $voice['alias'];
            }
        }
        return $fixed;
    }
}
