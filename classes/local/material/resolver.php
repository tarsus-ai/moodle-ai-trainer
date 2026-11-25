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

use mod_tarsuscoach\local\client\http_client;
use mod_tarsuscoach\local\support\platform;

/**
 * Finds the material of a course and turns each course module into bits
 * for the brain.
 *
 * Supported: files (documents, audio, video), folders (each file), web
 * links, pages, books, wikis and glossaries. Text is read from the module
 * tables at send time; files are read from Moodle's file storage at send
 * time.
 *
 * The fingerprint of each bit changes whenever its content does (the
 * file's content hash, a page's revision, the modification time of a
 * chapter, wiki page or glossary entry), so edited material is sent again.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class resolver {
    /** @var string[] Course modules that carry material. */
    public const TYPES = ['resource', 'folder', 'page', 'url', 'book', 'wiki', 'glossary'];

    /** Characters of text sent per bit at most. */
    private const MAX_TEXT = 1500000;

    /**
     * Whether a module type carries material.
     *
     * @param string $type The module (resource, page...).
     * @return bool
     */
    public function supports(string $type): bool {
        return in_array($type, self::TYPES, true);
    }

    /**
     * The material modules of a course, in course order.
     *
     * @param int $courseid The course.
     * @return array<int, array{ref_id: int, obj_id: int, type: string, title: string, description: string}>
     */
    public function nodes(int $courseid): array {
        if (!platform::course_alive($courseid)) {
            return [];
        }
        $modinfo = get_fast_modinfo($courseid);
        $nodes = [];
        foreach ($modinfo->get_sections() as $cmids) {
            foreach ($cmids as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if (!$this->supports($cm->modname) || !empty($cm->deletioninprogress)) {
                    continue;
                }
                $nodes[] = [
                    'ref_id' => (int) $cm->id,
                    'obj_id' => (int) $cm->instance,
                    'type' => (string) $cm->modname,
                    'title' => (string) $cm->name,
                    'description' => '',
                ];
            }
        }
        return $nodes;
    }

    /**
     * Whether a course module belongs to a course's material.
     *
     * @param int $cmid The course module.
     * @param int $courseid The course.
     * @return bool
     */
    public function in_scope(int $cmid, int $courseid): bool {
        if ($cmid <= 0 || !platform::course_alive($courseid)) {
            return false;
        }
        $db = platform::db();
        $cm = $db->get_record('course_modules', ['id' => $cmid], 'id, course, module, deletioninprogress');
        if (!$cm || (int) $cm->course !== $courseid || !empty($cm->deletioninprogress)) {
            return false;
        }
        return $this->supports((string) $db->get_field('modules', 'name', ['id' => $cm->module]));
    }

    /**
     * Every bit in a course, in course order.
     *
     * @param int $courseid The course.
     * @return material[]
     */
    public function for_container(int $courseid): array {
        $materials = [];
        foreach ($this->nodes($courseid) as $node) {
            foreach ($this->for_node($node, $courseid) as $material) {
                $materials[] = $material;
            }
        }
        return $materials;
    }

    /**
     * The bits of one course module.
     *
     * @param array $node The module (ref_id, obj_id, type, title).
     * @param int $courseid The course.
     * @return material[]
     */
    public function for_node(array $node, int $courseid): array {
        try {
            switch ($node['type']) {
                case 'resource':
                    return $this->resource_bits($node, $courseid);
                case 'folder':
                    return $this->folder_bits($node, $courseid);
                case 'url':
                    return $this->url_bits($node, $courseid);
                case 'page':
                case 'book':
                case 'wiki':
                case 'glossary':
                    return $this->text_bits($node, $courseid);
            }
        } catch (\Throwable $e) {
            platform::log('could not read material ' . $node['type'] . ' ' . $node['ref_id'] . ': ' . $e->getMessage());
        }
        return [];
    }

    /**
     * Re-resolve one bit from its resource id (current state).
     *
     * @param string $resourceid The resource id.
     * @return material|null
     */
    public function resolve_by_resource_id(string $resourceid): ?material {
        $parts = resource_id::parse($resourceid);
        if ($parts === null || !resource_id::is_local($resourceid) || !$this->supports($parts['type'])) {
            return null;
        }
        $cmid = $parts['refid'];
        if (!$this->in_scope($cmid, $parts['containerref'])) {
            return null;
        }
        $cm = get_coursemodule_from_id('', $cmid, $parts['containerref'], false, IGNORE_MISSING);
        if (!$cm || $cm->modname !== $parts['type']) {
            return null;
        }
        $node = [
            'ref_id' => (int) $cm->id,
            'obj_id' => (int) $cm->instance,
            'type' => (string) $cm->modname,
            'title' => (string) $cm->name,
            'description' => '',
        ];
        foreach ($this->for_node($node, $parts['containerref']) as $material) {
            if ($material->resourceid === $resourceid) {
                return $material;
            }
        }
        return null;
    }

    /**
     * The payload the brain's track endpoint receives for a bit; text is
     * extracted here, at send time.
     *
     * @param material $material The bit.
     * @return array
     */
    public function payload(material $material): array {
        $payload = [
            'resource_id' => $material->resourceid,
            'context_id' => resource_id::asset_id($material->containerref),
            'type' => $material->bittype,
            'title' => $material->title,
        ];

        switch ($material->bittype) {
            case bit_type::URL:
                $payload['url'] = (string) ($material->source['url'] ?? '');
                break;
            case bit_type::DOCUMENT:
            case bit_type::MEDIA:
                $payload['rid'] = (string) ($material->source['rid'] ?? '');
                $payload['filename'] = (string) ($material->source['filename'] ?? $material->title);
                $payload['mimetype'] = (string) ($material->source['mimetype'] ?? 'application/octet-stream');
                break;
            default:
                $payload['text'] = $this->text($material);
                break;
        }

        return $payload;
    }

    // ------------------------------------------------------------------
    // Files and folders
    // ------------------------------------------------------------------

    /**
     * The main file of a File resource.
     *
     * @param array $node The module.
     * @param int $courseid The course.
     * @return material[]
     */
    private function resource_bits(array $node, int $courseid): array {
        $context = \context_module::instance((int) $node['ref_id']);
        $files = get_file_storage()->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
        $file = reset($files);
        if (!$file) {
            return [];
        }
        return [$this->file_bit($node, $courseid, $file, resource_id::MAIN, (string) $node['title'])];
    }

    /**
     * One bit per file of a Folder.
     *
     * @param array $node The module.
     * @param int $courseid The course.
     * @return material[]
     */
    private function folder_bits(array $node, int $courseid): array {
        $context = \context_module::instance((int) $node['ref_id']);
        $files = get_file_storage()->get_area_files($context->id, 'mod_folder', 'content', 0, 'filepath, filename', false);
        $bits = [];
        foreach ($files as $file) {
            // A stable key per path: a replaced file keeps its place.
            $key = 'f' . substr(sha1($file->get_filepath() . $file->get_filename()), 0, 16);
            $title = ltrim($file->get_filepath() . $file->get_filename(), '/');
            $bits[] = $this->file_bit($node, $courseid, $file, $key, $title);
        }
        return $bits;
    }

    /**
     * A bit for one stored file.
     *
     * @param array $node The module.
     * @param int $courseid The course.
     * @param \stored_file $file The file.
     * @param string $bitkey The bit within the module.
     * @param string $title The bit's title.
     * @return material
     */
    private function file_bit(array $node, int $courseid, \stored_file $file, string $bitkey, string $title): material {
        $mimetype = (string) $file->get_mimetype();
        return new material(
            resource_id::build($courseid, (string) $node['type'], (int) $node['ref_id'], $bitkey),
            bit_type::for_mime($mimetype),
            $courseid,
            (string) $node['type'],
            (int) $node['ref_id'],
            (int) $node['obj_id'],
            $title,
            (string) $node['title'],
            implode('|', [$file->get_contenthash(), $file->get_filesize(), $file->get_filename()]),
            [
                'rid' => (string) $file->get_id(),
                'filename' => (string) $file->get_filename(),
                'mimetype' => $mimetype,
                'size' => (int) $file->get_filesize(),
            ]
        );
    }

    // ------------------------------------------------------------------
    // Web links
    // ------------------------------------------------------------------

    /**
     * The address of a URL module (web addresses only).
     *
     * @param array $node The module.
     * @param int $courseid The course.
     * @return material[]
     */
    private function url_bits(array $node, int $courseid): array {
        $target = trim((string) platform::db()->get_field('url', 'externalurl', ['id' => (int) $node['obj_id']]));
        $lower = strtolower($target);
        if (strpos($lower, 'http://') !== 0 && strpos($lower, 'https://') !== 0) {
            return [];
        }
        return [new material(
            resource_id::build($courseid, 'url', (int) $node['ref_id']),
            bit_type::URL,
            $courseid,
            'url',
            (int) $node['ref_id'],
            (int) $node['obj_id'],
            (string) $node['title'],
            (string) $node['title'],
            $target,
            ['url' => $target]
        )];
    }

    // ------------------------------------------------------------------
    // Text modules: pages, books, wikis, glossaries
    // ------------------------------------------------------------------

    /**
     * One text bit for a page, book, wiki or glossary: its parts are read in
     * order at send time.
     *
     * @param array $node The module.
     * @param int $courseid The course.
     * @return material[]
     */
    private function text_bits(array $node, int $courseid): array {
        $instance = platform::db()->get_record((string) $node['type'], ['id' => (int) $node['obj_id']], '*', IGNORE_MISSING);
        if (!$instance) {
            return [];
        }
        $parts = $this->text_parts((string) $node['type'], $instance, false);
        if ($parts === []) {
            return [];
        }
        $fingerprint = [sha1((string) ($instance->intro ?? ''))];
        foreach ($parts as $part) {
            $fingerprint[] = $part['stamp'];
        }
        return [new material(
            resource_id::build($courseid, (string) $node['type'], (int) $node['ref_id']),
            bit_type::TEXT,
            $courseid,
            (string) $node['type'],
            (int) $node['ref_id'],
            (int) $node['obj_id'],
            (string) $node['title'],
            (string) $node['title'],
            hash('sha256', implode("\n", $fingerprint)),
            ['module' => (string) $node['type'], 'instance' => (int) $node['obj_id']]
        )];
    }

    /**
     * The parts of a text module in reading order: their headings, a stamp
     * that changes with them and, when asked, their HTML.
     *
     * @param string $module page, book, wiki or glossary.
     * @param \stdClass $instance The module's record.
     * @param bool $withcontent Whether to fetch the content.
     * @return array<int, array{heading: string, level: int, stamp: string, html: string}>
     */
    private function text_parts(string $module, \stdClass $instance, bool $withcontent): array {
        $db = platform::db();
        $parts = [];
        switch ($module) {
            case 'page':
                $parts[] = [
                    'heading' => '',
                    'level' => 0,
                    'stamp' => 'page:' . (int) ($instance->revision ?? 0) . ':' . (int) $instance->timemodified,
                    'html' => $withcontent ? (string) $instance->content : '',
                ];
                break;

            case 'book':
                $fields = $withcontent ? 'id, pagenum, subchapter, title, content, hidden, timemodified'
                    : 'id, pagenum, subchapter, title, hidden, timemodified';
                foreach ($db->get_records('book_chapters', ['bookid' => $instance->id], 'pagenum', $fields) as $chapter) {
                    if (!empty($chapter->hidden)) {
                        continue;
                    }
                    $parts[] = [
                        'heading' => (string) $chapter->title,
                        'level' => empty($chapter->subchapter) ? 2 : 3,
                        'stamp' => 'ch' . $chapter->id . ':' . (int) $chapter->timemodified . ':' . $chapter->pagenum . ':' . $chapter->title,
                        'html' => $withcontent ? (string) $chapter->content : '',
                    ];
                }
                break;

            case 'wiki':
                // Collaborative pages only: individual wikis are personal.
                if (($instance->wikimode ?? '') !== 'collaborative') {
                    break;
                }
                $fields = $withcontent ? 'p.id, p.title, p.cachedcontent, p.timemodified' : 'p.id, p.title, p.timemodified';
                $pages = $db->get_records_sql(
                    'SELECT ' . $fields . ' FROM {wiki_pages} p JOIN {wiki_subwikis} s ON s.id = p.subwikiid'
                    . ' WHERE s.wikiid = ? AND s.userid = 0 ORDER BY p.title, p.id',
                    [$instance->id]
                );
                foreach ($pages as $page) {
                    $parts[] = [
                        'heading' => (string) $page->title,
                        'level' => 2,
                        'stamp' => 'wp' . $page->id . ':' . (int) $page->timemodified . ':' . $page->title,
                        'html' => $withcontent ? (string) $page->cachedcontent : '',
                    ];
                }
                break;

            case 'glossary':
                $fields = $withcontent ? 'id, concept, definition, timemodified' : 'id, concept, timemodified';
                foreach ($db->get_records('glossary_entries', ['glossaryid' => $instance->id, 'approved' => 1], 'concept, id', $fields) as $entry) {
                    $parts[] = [
                        'heading' => (string) $entry->concept,
                        'level' => -1,
                        'stamp' => 'ge' . $entry->id . ':' . (int) $entry->timemodified . ':' . $entry->concept,
                        'html' => $withcontent ? (string) $entry->definition : '',
                    ];
                }
                break;
        }
        return $parts;
    }

    // ------------------------------------------------------------------
    // Text extraction (send time)
    // ------------------------------------------------------------------

    /**
     * The text of a text bit, read now.
     *
     * @param material $material The bit.
     * @return string
     */
    private function text(material $material): string {
        $module = (string) ($material->source['module'] ?? $material->srctype);
        $instance = platform::db()->get_record($module, ['id' => (int) ($material->source['instance'] ?? 0)], '*', IGNORE_MISSING);
        $parts = ['# ' . $material->objecttitle];
        if (!$instance) {
            return $parts[0];
        }
        $intro = html_text::from_html((string) ($instance->intro ?? ''));
        if ($intro !== '') {
            $parts[] = $intro;
        }
        foreach ($this->text_parts($module, $instance, true) as $part) {
            $text = html_text::from_html($part['html']);
            if ($part['level'] < 0) {
                // A glossary entry: the term, then its definition.
                $parts[] = '**' . $part['heading'] . '**: ' . str_replace("\n", ' ', $text);
                continue;
            }
            if ($part['heading'] !== '') {
                $parts[] = str_repeat('#', $part['level']) . ' ' . $part['heading'];
            }
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        $text = trim(implode("\n\n", array_filter($parts, static fn($part) => trim((string) $part) !== '')));
        if (\core_text::strlen($text) > self::MAX_TEXT) {
            $text = \core_text::substr($text, 0, self::MAX_TEXT);
        }
        return $text;
    }

    /**
     * Copy a file bit into a temporary file (diagnostics).
     *
     * @param material $material The bit.
     * @return string|null
     */
    public static function file_to_temp(material $material): ?string {
        return http_client::copy_to_temp((string) ($material->source['rid'] ?? ''));
    }
}
