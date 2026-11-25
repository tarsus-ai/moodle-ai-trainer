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
 * The pictures Tarsus sends: only data of known image types and https
 * links reach the page.
 *
 * @package    mod_tarsuscoach
 * @category   test
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_tarsuscoach\local\output\media
 */
#[\PHPUnit\Framework\Attributes\CoversClass(media::class)]
final class media_test extends \advanced_testcase {
    /**
     * Provider for test_image.
     *
     * @return array
     */
    public static function image_provider(): array {
        return [
            'png data' => ['data:image/png;base64,iVBORw0KGgo=', 'data:image/png;base64,iVBORw0KGgo='],
            'jpg spelled jpg' => ['data:image/jpg;base64,/9j/4AAQ', 'data:image/jpeg;base64,/9j/4AAQ'],
            'svg' => ['data:image/svg+xml;base64,PHN2Zy8+', 'data:image/svg+xml;base64,PHN2Zy8+'],
            'line breaks in the data' => ["data:image/png;base64,iVBO\nRw0K", 'data:image/png;base64,iVBORw0K'],
            'https link' => ['https://example.org/picture.png', 'https://example.org/picture.png'],
            'empty' => ['', ''],
            'http link' => ['http://example.org/picture.png', ''],
            'link with a quote' => ['https://example.org/a"onerror="x', ''],
            'link with a backtick' => ["https://example.org/a\x60b", ''],
            'unknown type' => ['data:image/tiff;base64,SUkq', ''],
            'not base64 encoded' => ['data:image/png,raw', ''],
            'not base64 data' => ['data:image/png;base64,<script>', ''],
            'html' => ['data:text/html;base64,PGh0bWw+', ''],
            'javascript' => ['javascript:alert(1)', ''],
        ];
    }

    /**
     * What may be put into an image's address.
     *
     * @dataProvider image_provider
     * @param string $value What Tarsus sent.
     * @param string $expected What reaches the page.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('image_provider')]
    public function test_image(string $value, string $expected): void {
        $this->assertSame($expected, media::image($value));
    }

    /**
     * A bare base64 file with its extension.
     */
    public function test_image_from_base64(): void {
        $this->assertSame('data:image/png;base64,iVBORw0KGgo=', media::image_from_base64('iVBORw0KGgo=', '.png'));
        $this->assertSame('data:image/svg+xml;base64,PHN2Zy8+', media::image_from_base64('PHN2Zy8+', 'bin'));
        $this->assertSame('', media::image_from_base64('iVBORw0KGgo=', 'exe'));
        $this->assertSame('', media::image_from_base64('not base64!', 'png'));
    }
}
