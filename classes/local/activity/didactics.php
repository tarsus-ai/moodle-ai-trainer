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

/**
 * Didactic settings vocabulary: taxonomy levels, question types per
 * difficulty grade, operators, learning pace, modes and methods.
 *
 * The Test knowledge mode is steered by the enabled Bloom taxonomy levels
 * (1 Knowledge, 2 Understanding, 3 Application) and a map of difficulty
 * grade (1 Easy, 2 Medium, 3 Hard) to the question widget types the brain
 * may generate in that grade. Difficulty has no API parameter of its own;
 * the brain requires every grade to carry at least one type.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class didactics
{
    /** Single choice question. */
    public const TYPE_SINGLECHOICE = 'singleChoiceQuestionWidget';

    /** True or false question. */
    public const TYPE_TRUEFALSE = 'trueFalseQuestionWidget';

    /** Open question, answered in free text. */
    public const TYPE_OPEN = 'openQuestionWidget';

    /** Rule of three, answered per variable. */
    public const TYPE_CROSSMULTIPLICATION = 'crossMultiplicationQuestionWidget';

    /** Spreadsheet calculation: a table with cells to fill in. */
    public const TYPE_TRADECOSTING = 'tradeCostingQuestionWidget';

    /**
     * Accounting exercise (Buchhaltung). A requestable type only: the brain
     * answers it with tradeCostingQuestionWidget tasks, so display and
     * evaluation are those of the spreadsheet calculation.
     */
    public const TYPE_ACCOUNTING = 'accountingWidget';

    /** @var string[] */
    public const TYPES = [
        self::TYPE_SINGLECHOICE,
        self::TYPE_TRUEFALSE,
        self::TYPE_OPEN,
        self::TYPE_CROSSMULTIPLICATION,
        self::TYPE_TRADECOSTING,
        self::TYPE_ACCOUNTING,
    ];

    /** @var int[] Difficulty grades (1 Easy, 2 Medium, 3 Hard). */
    public const GRADES = [1, 2, 3];

    /** @var int[] Bloom taxonomy levels (1 Knowledge, 2 Understanding, 3 Application). */
    public const TAXONOMY_LEVELS = [1, 2, 3];

    /** Standard operators: the questions may use any task operator. */
    public const OPERATORS_STANDARD = 'standard';

    /** IHK operators: the questions use only name, describe, explain and elaborate. */
    public const OPERATORS_IHK = 'ihk';

    /** @var string[] */
    public const OPERATORS = [self::OPERATORS_STANDARD, self::OPERATORS_IHK];

    /** @var float[] Learning pace steps; 1 is recommended. */
    public const LEARN_TEMPOS = [0.1, 0.25, 0.5, 0.75, 1.0, 1.25, 1.5, 1.75, 2.0];

    /** Default learning pace, the recommended one. */
    public const LEARN_TEMPO_DEFAULT = 1.0;

    /** @var string[] Interaction modes, in switcher order as in Tarsus (discuss: Live interaction). */
    public const MODES = ['build', 'test', 'discuss', 'ask'];

    /** Micro-learning: teaching in short, focused units. */
    public const METHOD_MICROLEARNING = 'micro-learning';

    /** @var string[] */
    public const LEARNING_METHODS = [self::METHOD_MICROLEARNING];

    /** @var int[] conversation styles (1 Closed .. 5 Open). */
    public const STYLES = [1, 2, 3, 4, 5];

    /** @var int[] DQR levels. */
    public const DQR_LEVELS = [3, 4, 5, 6, 7];

    /**
     * A known learning method; micro-learning for anything else.
     *
     * @param string|null $value The learning method to check.
     * @return string
     */
    public static function learning_method(?string $value): string {
        $value = strtolower(trim((string) $value));
        return in_array($value, self::LEARNING_METHODS, true) ? $value : self::METHOD_MICROLEARNING;
    }

    /**
     * A known operator set; the standard operators for anything else.
     *
     * @param string|null $value The operator set to check.
     * @return string
     */
    public static function operators(?string $value): string {
        $value = strtolower(trim((string) $value));
        return in_array($value, self::OPERATORS, true) ? $value : self::OPERATORS_STANDARD;
    }

    /**
     * A learning pace, rounded to two decimals and held between 0.1 and 2;
     * the default for anything that is not a number.
     *
     * @param mixed $value The pace to check.
     * @return float
     */
    public static function learn_tempo($value): float {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return self::LEARN_TEMPO_DEFAULT;
        }
        return max(0.1, min(2.0, round((float) $value, 2)));
    }

    /**
     * The language key of a question type's label.
     */
    public static function type_label_key(string $type): string {
        switch ($type) {
            case self::TYPE_SINGLECHOICE:
                return 'qtype_singlechoice';
            case self::TYPE_TRUEFALSE:
                return 'qtype_truefalse';
            case self::TYPE_OPEN:
                return 'qtype_open';
            case self::TYPE_CROSSMULTIPLICATION:
                return 'qtype_crossmultiplication';
            case self::TYPE_TRADECOSTING:
                return 'qtype_tradecosting';
            case self::TYPE_ACCOUNTING:
                return 'qtype_accounting';
            default:
                return 'qtype_unknown';
        }
    }

    /**
     * The taxonomy levels enabled by default: all three.
     *
     * @return int[]
     */
    public static function default_taxonomy_levels(): array {
        return self::TAXONOMY_LEVELS;
    }

    /**
     * The default question types per grade: closed formats when easy,
     * everything in the middle, open questions only when hard.
     *
     * @return array<int, string[]>
     */
    public static function default_question_types(): array {
        return [
            1 => [self::TYPE_SINGLECHOICE, self::TYPE_TRUEFALSE],
            2 => [self::TYPE_SINGLECHOICE, self::TYPE_TRUEFALSE, self::TYPE_OPEN],
            3 => [self::TYPE_OPEN],
        ];
    }

    /**
     * The taxonomy levels for storage: the valid ones, each once, sorted and
     * separated by commas.
     *
     * @param array<int|string, mixed> $levels The enabled levels.
     * @return string
     */
    public static function encode_taxonomy_levels(array $levels): string {
        $levels = array_values(array_unique(array_intersect(array_map('intval', $levels), self::TAXONOMY_LEVELS)));
        sort($levels);
        return implode(',', $levels);
    }

    /**
     * The stored taxonomy levels as a sorted list.
     *
     * @param string|null $encoded The levels as stored, separated by commas.
     * @return int[] At least one level; invalid values fall back to the defaults.
     */
    public static function decode_taxonomy_levels(?string $encoded): array {
        $levels = array_values(array_unique(array_intersect(
            array_map('intval', explode(',', (string) $encoded)),
            self::TAXONOMY_LEVELS
        )));
        sort($levels);
        return $levels === [] ? self::default_taxonomy_levels() : $levels;
    }

    /**
     * The question types per grade for storage, as a JSON object. Unknown
     * grades and types are left out, and so are grades without a type.
     *
     * @param array<int|string, mixed> $map Grade => question widget types.
     * @return string
     */
    public static function encode_question_types(array $map): string {
        $clean = [];
        foreach (self::GRADES as $grade) {
            $types = array_values(array_intersect((array) ($map[$grade] ?? []), self::TYPES));
            if ($types !== []) {
                $clean[(string) $grade] = $types;
            }
        }
        return (string) json_encode((object) $clean);
    }

    /**
     * Decode the stored map; a grade that ends up empty takes its default,
     * so the result is always complete and sendable.
     *
     * @return array<int, string[]>
     */
    public static function decode_question_types(?string $encoded): array {
        $decoded = json_decode((string) $encoded, true);
        $map = [];
        if (is_array($decoded)) {
            foreach ($decoded as $grade => $types) {
                $grade = (int) $grade;
                if (!in_array($grade, self::GRADES, true) || !is_array($types)) {
                    continue;
                }
                $types = array_values(array_intersect($types, self::TYPES));
                if ($types !== []) {
                    $map[$grade] = $types;
                }
            }
        }
        foreach (self::default_question_types() as $grade => $types) {
            $map[$grade] = $map[$grade] ?? $types;
        }
        ksort($map);
        return $map;
    }

    /**
     * Format a pace for display: x0.25, x1, ...
     */
    public static function tempo_label(float $tempo): string {
        return 'x' . rtrim(rtrim(number_format($tempo, 2, '.', ''), '0'), '.');
    }
}
