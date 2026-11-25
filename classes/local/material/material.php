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

namespace mod_tarsuscoach\local\material;

/**
 * One unit of material - a single bit destined for the brain.
 *
 * Produced from current ILIAS state. The content hash (change detection)
 * derives from a cheap fingerprint of the source - file revision, page
 * modification times, link targets - so detecting a change never needs the
 * content itself; the payload is only built when a bit is actually sent.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class material
{
    /** @var string The resource id that addresses the bit. */
    public string $resourceid;
    /** @var string One of the bit_type constants. */
    public string $bittype;
    /** @var int The course. */
    public int $containerref;
    /** @var string The module of the source (resource, page, url...). */
    public string $srctype;
    /** @var int The source's course module. */
    public int $srcref;
    /** @var int The source's module instance. */
    public int $srcobjid;
    /** @var string The bit's own title (the object's, or a link's). */
    public string $title;
    /** @var string The source object's title. */
    public string $objecttitle;
    /** @var string A cheap fingerprint of the source that changes with its content. */
    public string $fingerprint;
    /** @var array<string, mixed> Source details (rid, mimetype, url, page ids, ...). */
    public array $source;

    /**
     * Create a bit from the details of its source.
     *
     * @param string $resourceid The resource id that addresses the bit.
     * @param string $bittype One of the bit_type constants.
     * @param int $containerref The course.
     * @param string $srctype The module of the source (resource, page, url...).
     * @param int $srcref The source's course module.
     * @param int $srcobjid The source's module instance.
     * @param string $title The bit's own title (the object's, or a link's).
     * @param string $objecttitle The source object's title.
     * @param string $fingerprint A cheap fingerprint of the source that changes with its content.
     * @param array<string, mixed> $source Source details (rid, mimetype, url, page ids, ...).
     */
    public function __construct(
        string $resourceid,
        string $bittype,
        int $containerref,
        string $srctype,
        int $srcref,
        int $srcobjid,
        string $title,
        string $objecttitle,
        string $fingerprint,
        array $source = []
    ) {
        $this->resourceid = $resourceid;
        $this->bittype = $bittype;
        $this->containerref = $containerref;
        $this->srctype = $srctype;
        $this->srcref = $srcref;
        $this->srcobjid = $srcobjid;
        $this->title = $title;
        $this->objecttitle = $objecttitle;
        $this->fingerprint = $fingerprint;
        $this->source = $source;
    }

    /**
     * A stable hash of this material's content: an unchanged hash means no
     * API call.
     */
    public function content_hash(): string {
        return hash('sha256', implode("\x1f", [$this->bittype, trim($this->title), $this->fingerprint]));
    }
}
