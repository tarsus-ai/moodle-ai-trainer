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
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class material
{
    public string $resourceid;
    public string $bittype;
    public int $containerref;
    public string $srctype;
    public int $srcref;
    public int $srcobjid;
    /** The bit's own title (the object's, or a link's). */
    public string $title;
    /** The source object's title. */
    public string $objecttitle;
    public string $fingerprint;
    /** @var array<string, mixed> Source details (rid, mimetype, url, page ids, ...). */
    public array $source;

    /**
     * @param array<string, mixed> $source
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
