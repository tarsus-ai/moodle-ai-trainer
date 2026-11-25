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

namespace mod_tarsuscoach\local\activity;

use mod_tarsuscoach\local\support\config;
use mod_tarsuscoach\local\support\platform;
use mod_tarsuscoach\local\support\plan;

/**
 * The settings of one Tarsus object: availability, the didactic AI
 * settings grouped by mode, and the completion threshold.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class settings
{
    /** The activity's table, which holds the settings. */
    public const TABLE = 'tarsuscoach';

    /** Default completion threshold in percent. */
    public const DEFAULT_COMPLETION = 80;

    /** Thinking levels (1 fast, 2 slow, 3 deep, 4 intensive). */
    public const THINKING_LEVELS = [1, 2, 3, 4];

    /** What Tarsus does when no level is sent: no change for existing objects. */
    public const DEFAULT_THINKING_LEVEL = 4;

    /** @var int The Tarsus object; 0 when there is none yet. */
    public int $objid = 0;
    /** @var bool Whether the object is online: its course module is visible. */
    public bool $online = false;
    /** @var int Conversation style, 1 closed .. 5 open. */
    public int $conversationstyle = 3;
    /** @var int DQR level the answers are pitched at, 3 .. 7. */
    public int $dqr = 6;
    /** @var string Target group: the domain or field of expertise; may be empty. */
    public string $industry = '';
    /** @var int[] */
    public array $taxonomylevels = [1, 2, 3];
    /** @var array<int, string[]> */
    public array $questiontypes = [];
    /** @var string The task operators the practice questions follow: standard or ihk. */
    public string $operators = didactics::OPERATORS_STANDARD;
    /** @var bool Cheat prevention: learners cannot ask the chat about a question just set. */
    public bool $anticheat = false;
    /** @var float Learning pace, 0.1 .. 2 (1 is recommended). */
    public float $learntempo = didactics::LEARN_TEMPO_DEFAULT;
    /** @var bool Whether Ask a question is switched on. */
    public bool $askenabled = true;
    /** @var bool Whether Test knowledge is switched on. */
    public bool $testenabled = true;
    /** @var bool Whether Build knowledge is switched on. */
    public bool $buildenabled = true;
    /** @var bool Live interaction (state discuss): a spoken conversation with the coach. */
    public bool $discussenabled = true;
    /** @var bool Whether the learning cards may include GIF animations. */
    public bool $gifenabled = false;
    /** @var int How thoroughly the coach thinks: 1 fast .. 4 intensive (Tarsus's default). */
    public int $thinkinglevel = self::DEFAULT_THINKING_LEVEL;
    /** @var bool Pictures and other visual content come with a description. */
    public bool $visualaccessibility = false;
    /** @var bool Learners may look at the sources of an answer. */
    public bool $referencesenabled = true;
    /** @var bool Learners may speak their question in Ask a question. */
    public bool $voiceinput = true;
    /** @var string The didactic concept Build knowledge follows. */
    public string $learningmethod = didactics::METHOD_MICROLEARNING;
    /** @var string trainer-driven (text and pictures) or ai-driven (interactive pages). */
    public string $interactiontype = plan::INTERACTION_TRAINER;
    /** @var int Completion threshold in percent; 0 switches the rule off. */
    public int $completionprogress = self::DEFAULT_COMPLETION;

    /**
     * The default settings for an object.
     *
     * @param int $objid The Tarsus object; 0 when there is none yet.
     */
    public function __construct(int $objid = 0) {
        $this->objid = $objid;
        $this->questiontypes = didactics::default_question_types();
    }

    /**
     * The stored settings of an object, each value checked; the defaults
     * when it has no record.
     *
     * @param int $objid The Tarsus object.
     * @return self
     */
    public static function load(int $objid): self {
        $settings = new self($objid);
        $record = platform::db()->get_record(self::TABLE, ['id' => $objid]);
        if (!$record) {
            return $settings;
        }
        $row = (array) $record;
        $settings->online = self::is_online($objid);
        $settings->conversationstyle = self::within((int) $row['conversationstyle'], didactics::STYLES, 3);
        $settings->dqr = self::within((int) $row['dqr'], didactics::DQR_LEVELS, 6);
        $settings->industry = (string) ($row['industry'] ?? '');
        $settings->taxonomylevels = didactics::decode_taxonomy_levels((string) ($row['taxonomylevels'] ?? ''));
        $settings->questiontypes = didactics::decode_question_types((string) ($row['questiontypes'] ?? ''));
        $settings->operators = didactics::operators((string) ($row['operators'] ?? ''));
        $settings->anticheat = !empty($row['anticheat']);
        $settings->learntempo = didactics::learn_tempo($row['learntempo'] ?? null);
        $settings->askenabled = !empty($row['askenabled']);
        $settings->testenabled = !empty($row['testenabled']);
        $settings->buildenabled = !empty($row['buildenabled']);
        $settings->discussenabled = !empty($row['discussenabled']);
        $settings->gifenabled = !empty($row['gifenabled']);
        $settings->thinkinglevel = self::within(
            (int) ($row['thinkinglevel'] ?? self::DEFAULT_THINKING_LEVEL),
            self::THINKING_LEVELS,
            self::DEFAULT_THINKING_LEVEL
        );
        $settings->visualaccessibility = !empty($row['visualaccessibility']);
        $settings->referencesenabled = !isset($row['referencesenabled']) || !empty($row['referencesenabled']);
        $settings->voiceinput = !isset($row['voiceinput']) || !empty($row['voiceinput']);
        $settings->learningmethod = didactics::learning_method((string) ($row['learningmethod'] ?? ''));
        $settings->interactiontype = self::interaction((string) ($row['interactiontype'] ?? ''));
        $settings->completionprogress = max(0, min(100, (int) ($row['completionprogress'] ?? 0)));
        return $settings;
    }

    /**
     * Store the settings in the object's record, each value checked first.
     * Nothing is stored without an object.
     */
    public function save(): void {
        if ($this->objid <= 0) {
            return;
        }
        $columns = [
            'conversationstyle' => ['integer', self::within($this->conversationstyle, didactics::STYLES, 3)],
            'dqr' => ['integer', self::within($this->dqr, didactics::DQR_LEVELS, 6)],
            'industry' => ['text', config::bmp(mb_substr(trim($this->industry), 0, 255))],
            'taxonomylevels' => ['text', didactics::encode_taxonomy_levels($this->taxonomylevels) ?: '1,2,3'],
            'questiontypes' => ['text', didactics::encode_question_types($this->questiontypes)],
            'operators' => ['text', didactics::operators($this->operators)],
            'anticheat' => ['integer', $this->anticheat ? 1 : 0],
            'learntempo' => ['float', didactics::learn_tempo($this->learntempo)],
            'askenabled' => ['integer', $this->askenabled ? 1 : 0],
            'testenabled' => ['integer', $this->testenabled ? 1 : 0],
            'buildenabled' => ['integer', $this->buildenabled ? 1 : 0],
            'discussenabled' => ['integer', $this->discussenabled ? 1 : 0],
            'gifenabled' => ['integer', $this->gifenabled ? 1 : 0],
            'thinkinglevel' => ['integer', self::within($this->thinkinglevel, self::THINKING_LEVELS, self::DEFAULT_THINKING_LEVEL)],
            'visualaccessibility' => ['integer', $this->visualaccessibility ? 1 : 0],
            'referencesenabled' => ['integer', $this->referencesenabled ? 1 : 0],
            'voiceinput' => ['integer', $this->voiceinput ? 1 : 0],
            'learningmethod' => ['text', didactics::learning_method($this->learningmethod)],
            'interactiontype' => ['text', self::interaction($this->interactiontype)],
            'completionprogress' => ['integer', max(0, min(100, $this->completionprogress))],
        ];
        $record = (object) ['id' => $this->objid, 'timemodified' => time()];
        foreach ($columns as $name => $typed) {
            $record->$name = $typed[1];
        }
        platform::db()->update_record(self::TABLE, $record);
    }

    /**
     * A valid interaction type; trainer-driven for anything else.
     */
    public static function interaction(string $value): string {
        return in_array($value, plan::INTERACTIONS, true) ? $value : plan::INTERACTION_TRAINER;
    }

    /**
     * The interaction type that is actually in effect: the object's wish,
     * bounded by the plan.
     */
    public function effective_interaction(): string {
        return plan::interaction_type($this->interactiontype);
    }

    /**
     * Whether a mode is switched on here and included in the plan.
     */
    public function mode_active(string $mode): bool {
        return $this->mode_enabled($mode) && plan::allows_mode($mode);
    }

    /**
     * The modes the plan allows this object to offer, in switcher order.
     *
     * @return string[]
     */
    public function available_modes(): array {
        return array_values(array_filter($this->modes(), static fn(string $mode): bool => plan::allows_mode($mode)));
    }


    /**
     * A copy of these settings for another object.
     */
    public function copy_to(int $objid): self {
        $copy = clone $this;
        $copy->objid = $objid;
        return $copy;
    }

    /**
     * Whether a mode is switched on here, whatever the plan includes.
     *
     * @param string $mode The mode: ask, test, build or discuss.
     * @return bool
     */
    public function mode_enabled(string $mode): bool {
        switch ($mode) {
            case 'ask':
                return $this->askenabled;
            case 'test':
                return $this->testenabled;
            case 'build':
                return $this->buildenabled;
            case 'discuss':
                return $this->discussenabled;
        }
        return false;
    }

    /**
     * Whether learners may speak their question: switched on here and part
     * of the package.
     */
    public function voice_input_active(): bool {
        return $this->voiceinput && $this->mode_active('ask') && plan::allows_voice_input();
    }

    /**
     * The enabled modes in switcher order.
     *
     * @return string[]
     */
    public function modes(): array {
        return array_values(array_filter(didactics::MODES, fn(string $mode): bool => $this->mode_enabled($mode)));
    }

    /**
     * Whether the online flag of an object is set (cheap, for access checks).
     */
    public static function is_online(int $objid): bool {
        $cm = get_coursemodule_from_instance('tarsuscoach', $objid, 0, false, IGNORE_MISSING);
        return $cm !== false && !empty($cm->visible);
    }

    /**
     * The value if it is one of the allowed ones, the default otherwise.
     *
     * @param int $value The value to check.
     * @param int[] $allowed The allowed values.
     * @param int $default The value to use instead.
     * @return int
     */
    private static function within(int $value, array $allowed, int $default): int {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
