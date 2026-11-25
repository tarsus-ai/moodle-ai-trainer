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

namespace mod_tarsuscoach\local\output;

/**
 * Tarsus's markdown leaves the server escaped, for the coach script to
 * render.
 *
 * @package    mod_tarsuscoach
 * @category   test
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_tarsuscoach\local\output\markdown
 */
#[\PHPUnit\Framework\Attributes\CoversClass(markdown::class)]
final class markdown_test extends \advanced_testcase {
    /**
     * Markup in the text is escaped; line breaks stay.
     */
    public function test_render_escapes(): void {
        $this->assertSame(
            '<div class="tarsus-md">**Bold** &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; &#039;q&#039;'
                . "<br>\nnext</div>",
            markdown::render("**Bold** <script>alert(\"x\")</script> & 'q'\nnext")
        );
    }

    /**
     * An empty answer gives no element.
     */
    public function test_render_empty(): void {
        $this->assertSame('', markdown::render(''));
        $this->assertSame('', markdown::render("  \n "));
    }

    /**
     * Plain text as safe HTML.
     */
    public function test_plain(): void {
        $this->assertSame("a &lt;b&gt;<br>\nc", markdown::plain("a <b>\nc"));
    }
}
