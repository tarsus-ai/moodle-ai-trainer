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
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
class configuration_exception extends \RuntimeException
{
    public string $langKey;

    public function __construct(string $langKey) {
        $this->langKey = $langKey;
        parent::__construct($langKey);
    }
}
