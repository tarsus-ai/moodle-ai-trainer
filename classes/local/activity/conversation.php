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
use mod_tarsuscoach\local\support\pseudonymizer;

/**
 * The shared identity of every t0 call: context, robot, user and the
 * object's didactic settings.
 *
 * The context is the Tarsus object (site-scoped id), the robot identity is
 * a global admin setting, and the user travels as pseudonym + display name
 * + interface language. Email addresses and logins never leave ILIAS.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class conversation
{
    /** @var string[] Languages the Tarsus t0 surface supports. */
    private const LANGUAGES = ['en', 'de', 'fr', 'ar', 'zh', 'it', 'es', 'he', 'ja', 'nl', 'tr', 'uk', 'ru'];

    /**
     * The parameters every t0 call shares, for an object and the current user.
     *
     * @param int $objid The Tarsus object.
     * @param string $title Its title.
     * @param string $description Its description (HTML); the title stands in when it is empty.
     * @param settings $settings Its settings.
     * @param string[] $allowed Resource ids allowed to ground the call.
     * @return array<string, mixed>
     */
    public static function params(int $objid, string $title, string $description, settings $settings, array $allowed): array {
        $name = trim($title);
        $description = trim(html_entity_decode(strip_tags($description), ENT_QUOTES, 'UTF-8'));
        if ($description === '') {
            // The brain requires a non-empty description.
            $description = $name;
        }

        global $USER;

        return [
            'contextid' => scope::conversation_context_id($objid),
            'contextname' => $name,
            'contextdescription' => $description,
            'conversationstyle' => $settings->conversationstyle,
            'dqr' => $settings->dqr,
            'industry' => trim($settings->industry),
            // Full-text search stays off: semantic retrieval alone grounds
            // the answers.
            'fulltextsearch' => false,
            'taxonomylevels' => $settings->taxonomylevels,
            'questiontypes' => $settings->questiontypes,
            'operators' => $settings->operators,
            'gif' => $settings->gifenabled,
            'learningmethod' => $settings->learningmethod,
            'anticheat' => $settings->anticheat,
            'learntempo' => $settings->learntempo,
            'interactiontype' => $settings->effective_interaction(),
            'thinkinglevel' => $settings->thinkinglevel,
            'visualaccessibility' => $settings->visualaccessibility,
            'robotname' => config::robot_name(),
            'robotid' => config::robot_id(),
            'robotdescription' => config::robot_description(),
            'userid' => pseudonymizer::pseudonymize(platform::user_id()),
            'username' => trim(fullname($USER)),
            'userlanguage' => self::language(),
            'resourceids' => $allowed,
        ];
    }

    /**
     * The user's interface language, reduced to what the brain supports.
     */
    public static function language(): string {
        $language = platform::user_language();
        return in_array($language, self::LANGUAGES, true) ? $language : 'de';
    }
}
