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

namespace mod_tarsuscoach\local\client;

/**
 * The plugin is not configured well enough to reach the brain (no base URL,
 * no API key, or no brain selected). The message key names a language
 * variable of the plugin.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class configuration_exception extends \RuntimeException
{
    /** @var string The plugin's language string key that names the problem. */
    public string $langkey;

    /**
     * Keep the language string key; it is the exception message too.
     *
     * @param string $langkey The plugin's language string key that names the problem.
     */
    public function __construct(string $langkey) {
        $this->langkey = $langkey;
        parent::__construct($langkey);
    }
}
