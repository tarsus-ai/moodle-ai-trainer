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
 * Tarsus answered HTTP 409 with {"replay": {"widget": {...}}}. In Build
 * knowledge it then sends a widget again instead of a new one: the
 * course-complete card once the learning path is finished, or the learn
 * question that is still unanswered. That is no error and costs nothing;
 * the widget is shown like any other.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class replay_exception extends \RuntimeException
{
    /** @var array<string, mixed> The widget Tarsus sent again. */
    public array $widget;

    /**
     * Keep the widget Tarsus sent again; the exception code is 409.
     *
     * @param array<string, mixed> $widget The widget Tarsus sent again.
     */
    public function __construct(array $widget) {
        $this->widget = $widget;
        parent::__construct('Tarsus sent a widget again (HTTP 409)', 409);
    }
}
