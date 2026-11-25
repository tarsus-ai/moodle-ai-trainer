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

/**
 * The teacher's list of the course material: what is indexed in the brain
 * and what this activity may use.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_tarsuscoach\local\client\client_interface;
use mod_tarsuscoach\local\index\inclusions;
use mod_tarsuscoach\local\index\reconciler;
use mod_tarsuscoach\local\index\repository;
use mod_tarsuscoach\local\index\status;
use mod_tarsuscoach\local\material\resolver;
use mod_tarsuscoach\local\output\presenter;
use mod_tarsuscoach\local\output\views;
use mod_tarsuscoach\local\page\coach;
use mod_tarsuscoach\local\support\budget;
use mod_tarsuscoach\local\support\help;
use mod_tarsuscoach\local\support\platform;

$id = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('tarsuscoach', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$tarsus = $DB->get_record('tarsuscoach', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/tarsuscoach:manage', $context);

$url = new moodle_url('/mod/tarsuscoach/materials.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($tarsus->name) . ': ' . get_string('tab_materials', 'mod_tarsuscoach'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_secondary_active_tab('mod_tarsuscoach_materials');
$version = platform::plugin_version();

$resolver = new resolver();
$repo = new repository();
$materials = $resolver->for_container((int) $course->id);

// What the course holds now against what the brain holds: vanished
// material is withdrawn, changed material flagged. The brain is not called
// for uploads here; the page's sync does the reads.
try {
    (new reconciler($repo, $resolver))->reconcile_container((int) $course->id, 0, true, $materials);
} catch (\Throwable $e) {
    platform::log('reconcile failed: ' . $e->getMessage());
}

$rows = $repo->get_by_container((int) $course->id);
$included = array_flip(inclusions::for_object((int) $tarsus->id));

// Names as Moodle shows them (filters, e.g. multi-language names): HTML.
$coursecontext = context_course::instance($course->id);
$name = static fn(string $text): string => format_string($text, true, ['context' => $coursecontext]);

$typelabel = static function (string $srctype, string $bittype): string {
    $label = platform::mod_label($srctype);
    if ($bittype !== '' && platform::has_txt('bittype_' . $bittype)) {
        $label .= ' · ' . get_string('bittype_' . $bittype, 'mod_tarsuscoach');
    }
    return $label;
};

$entries = [];
$seen = [];
foreach ($materials as $material) {
    $seen[$material->resourceid] = true;
    $row = $rows[$material->resourceid] ?? null;
    $status = $row->status ?? status::NOT_INDEXED;
    $entries[] = presenter::present($material->resourceid, $status, (int) ($row->timemodified ?? 0)) + [
        'included' => isset($included[$material->resourceid]),
        'title' => $name($material->title),
        'objecttitle' => $name($material->objecttitle),
        'typelabel' => $typelabel($material->srctype, $material->bittype),
        'url' => platform::cm_alive($material->srcref) ? platform::cm_url($material->srcref, $material->srctype) : '',
    ];
}
// Rows the brain still holds for material that is gone stay listed until
// the deletion is delivered.
foreach ($rows as $resourceid => $row) {
    if (isset($seen[$resourceid]) || $row->status === status::NOT_INDEXED) {
        continue;
    }
    $entries[] = presenter::present($resourceid, $row->status, (int) $row->timemodified) + [
        'included' => false,
        'title' => get_string('material_gone', 'mod_tarsuscoach'),
        'objecttitle' => get_string('material_gone', 'mod_tarsuscoach'),
        'typelabel' => $typelabel($row->srctype, ''),
        'url' => '',
    ];
}

$counts = ['total' => count($entries), 'indexed' => 0, 'included' => 0];
foreach ($entries as $entry) {
    if ($entry['status'] === status::INDEXED) {
        $counts['indexed']++;
        if ($entry['included']) {
            $counts['included']++;
        }
    }
}

$pages = max(1, (int) ceil(count($entries) / coach::PAGE_SIZE));
$page = max(0, min($pages - 1, $page));
$pageentries = array_slice($entries, $page * coach::PAGE_SIZE, coach::PAGE_SIZE);
$pager = views::pager($page, $pages, static function (int $target) use ($cm): string {
    return (new moodle_url('/mod/tarsuscoach/materials.php', ['id' => $cm->id, 'page' => $target]))->out(false);
});

$config = [
    'kind' => 'materials',
    'cmid' => (int) $cm->id,
    'endpoints' => coach::endpoints(['action' => 'material_action', 'include' => 'set_material_included', 'sync' => 'sync_materials']),
    'batchLimit' => client_interface::BATCH_LIMIT,
    'strings' => coach::strings(coach::MATERIAL_STRINGS),
];

echo $OUTPUT->header();
$banner = budget::banner();
if ($banner !== null) {
    echo $OUTPUT->notification($banner['text'], $banner['type'] === 'failure' ? 'error' : $banner['type']);
}
$help = views::help(help::links(), 'tarsus-help-' . $tarsus->id);
if ($help !== '') {
    echo html_writer::div($help, 'tarsus-app tarsus-help-row');
}
echo views::materials('tarsus-materials-' . $tarsus->id, $pageentries, $pager, $config, $counts);
$PAGE->requires->js_call_amd('mod_tarsuscoach/coach-lazy', 'init');
echo $OUTPUT->footer();
