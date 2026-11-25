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

namespace mod_tarsuscoach\local\support;

/**
 * The help links the administrator lists, one per line, and the videos
 * among them.
 *
 * @package    mod_tarsuscoach
 * @category   test
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_tarsuscoach\local\support\help
 */
#[\PHPUnit\Framework\Attributes\CoversClass(help::class)]
final class help_test extends \advanced_testcase {
    /**
     * Titles, plain addresses and comments.
     */
    public function test_parse(): void {
        $links = help::parse("# Our help pages\nGetting started | https://example.org/help?a=1&b=2\n\n"
            . "https://example.org/faq\n  Video | https://www.youtube.com/watch?v=abcdef12345  \n");
        $this->assertSame([
            ['title' => 'Getting started', 'url' => 'https://example.org/help?a=1&b=2', 'embed' => ''],
            ['title' => 'example.org', 'url' => 'https://example.org/faq', 'embed' => ''],
            ['title' => 'Video', 'url' => 'https://www.youtube.com/watch?v=abcdef12345',
                'embed' => 'https://www.youtube-nocookie.com/embed/abcdef12345'],
        ], $links);
    }

    /**
     * Only plain https addresses are taken.
     */
    public function test_parse_refuses_other_addresses(): void {
        $this->assertSame([], help::parse("Old | http://example.org/\nScript | javascript:alert(1)\n"
            . "Quote | https://example.org/\"onmouseover=\"x\nSpace | https://example.org/a b\n"));
    }

    /**
     * Twenty links at most.
     */
    public function test_parse_keeps_twenty(): void {
        $lines = [];
        for ($i = 1; $i <= 25; $i++) {
            $lines[] = 'Link ' . $i . ' | https://example.org/' . $i;
        }
        $this->assertCount(20, help::parse(implode("\n", $lines)));
    }

    /**
     * Provider for test_embed_url.
     *
     * @return array
     */
    public static function embed_url_provider(): array {
        return [
            'YouTube watch' => [
                'https://www.youtube.com/watch?v=abcdef12345&t=10',
                'https://www.youtube-nocookie.com/embed/abcdef12345',
            ],
            'YouTube short link' => ['https://youtu.be/abcdef12345', 'https://www.youtube-nocookie.com/embed/abcdef12345'],
            'YouTube shorts' => ['https://youtube.com/shorts/abcdef12345', 'https://www.youtube-nocookie.com/embed/abcdef12345'],
            'Vimeo' => ['https://vimeo.com/123456789', 'https://player.vimeo.com/video/123456789?dnt=1'],
            'YouTube without a video' => ['https://www.youtube.com/channel/xyz', ''],
            'look-alike host' => ['https://notyoutube.com/watch?v=abcdef12345', ''],
            'any other page' => ['https://example.org/video/123456', ''],
        ];
    }

    /**
     * Videos of YouTube and Vimeo play in their privacy-friendly players.
     *
     * @dataProvider embed_url_provider
     * @param string $url The link.
     * @param string $expected The player's address, or ''.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('embed_url_provider')]
    public function test_embed_url(string $url, string $expected): void {
        $this->assertSame($expected, help::embed_url($url));
    }
}
