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
 * @package    mod_tarsus
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_tarsus\local\Client\ClientInterface;
use mod_tarsus\local\Index\Inclusions;
use mod_tarsus\local\Index\Reconciler;
use mod_tarsus\local\Index\Repository;
use mod_tarsus\local\Index\Status;
use mod_tarsus\local\Material\Resolver;
use mod_tarsus\local\Output\Presenter;
use mod_tarsus\local\Output\Views;
use mod_tarsus\local\Page\Coach;
use mod_tarsus\local\Support\Budget;
use mod_tarsus\local\Support\Help;
use mod_tarsus\local\Support\Platform;

$id = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('tarsus', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$tarsus = $DB->get_record('tarsus', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/tarsus:manage', $context);

$url = new moodle_url('/mod/tarsus/materials.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($tarsus->name) . ': ' . get_string('tab_materials', 'mod_tarsus'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_secondary_active_tab('mod_tarsus_materials');
$version = Platform::pluginVersion();
$PAGE->requires->css(new moodle_url('/mod/tarsus/assets/css/tarsus.css', ['v' => $version]));
$PAGE->requires->js(new moodle_url('/mod/tarsus/assets/js/tarsus.js', ['v' => $version]));

$resolver = new Resolver();
$repo = new Repository();
$materials = $resolver->forContainer((int) $course->id);

// What the course holds now against what the brain holds: vanished
// material is withdrawn, changed material flagged. The brain is not called
// for uploads here; the page's sync does the reads.
try {
    (new Reconciler($repo, $resolver))->reconcileContainer((int) $course->id, 0, true, $materials);
} catch (\Throwable $e) {
    Platform::log('reconcile failed: ' . $e->getMessage());
}

$rows = $repo->getByContainer((int) $course->id);
$included = array_flip(Inclusions::forObject((int) $tarsus->id));

$typelabel = static function (string $srctype, string $bittype): string {
    $label = Platform::modLabel($srctype);
    if ($bittype !== '' && Platform::hasTxt('bittype_' . $bittype)) {
        $label .= ' · ' . get_string('bittype_' . $bittype, 'mod_tarsus');
    }
    return $label;
};

$entries = [];
$seen = [];
foreach ($materials as $material) {
    $seen[$material->resourceid] = true;
    $row = $rows[$material->resourceid] ?? null;
    $status = $row->status ?? Status::NOT_INDEXED;
    $entries[] = Presenter::present($material->resourceid, $status, (int) ($row->timemodified ?? 0)) + [
        'included' => isset($included[$material->resourceid]),
        'title' => $material->title,
        'objecttitle' => $material->objecttitle,
        'typelabel' => $typelabel($material->srctype, $material->bittype),
        'url' => Platform::cmAlive($material->srcref) ? Platform::cmUrl($material->srcref, $material->srctype) : '',
    ];
}
// Rows the brain still holds for material that is gone stay listed until
// the deletion is delivered.
foreach ($rows as $resourceid => $row) {
    if (isset($seen[$resourceid]) || $row->status === Status::NOT_INDEXED) {
        continue;
    }
    $entries[] = Presenter::present($resourceid, $row->status, (int) $row->timemodified) + [
        'included' => false,
        'title' => get_string('material_gone', 'mod_tarsus'),
        'objecttitle' => get_string('material_gone', 'mod_tarsus'),
        'typelabel' => $typelabel($row->srctype, ''),
        'url' => '',
    ];
}

$counts = ['total' => count($entries), 'indexed' => 0, 'included' => 0];
foreach ($entries as $entry) {
    if ($entry['status'] === Status::INDEXED) {
        $counts['indexed']++;
        if ($entry['included']) {
            $counts['included']++;
        }
    }
}

$pages = max(1, (int) ceil(count($entries) / Coach::PAGE_SIZE));
$page = max(0, min($pages - 1, $page));
$pageentries = array_slice($entries, $page * Coach::PAGE_SIZE, Coach::PAGE_SIZE);
$pager = Views::pager($page, $pages, static function (int $target) use ($cm): string {
    return (new moodle_url('/mod/tarsus/materials.php', ['id' => $cm->id, 'page' => $target]))->out(false);
});

$config = [
    'kind' => 'materials',
    'endpoints' => Coach::endpoints((int) $cm->id, ['action' => 'action', 'include' => 'include', 'sync' => 'sync']),
    'batchLimit' => ClientInterface::BATCH_LIMIT,
    'strings' => Coach::strings(Coach::MATERIAL_STRINGS),
];

echo $OUTPUT->header();
$banner = Budget::banner();
if ($banner !== null) {
    echo $OUTPUT->notification($banner['text'], $banner['type'] === 'failure' ? 'error' : $banner['type']);
}
$help = Views::help(Help::links(), 'tarsus-help-' . $tarsus->id);
if ($help !== '') {
    echo html_writer::div($help, 'tarsus-app tarsus-help-row');
}
echo Views::materials('tarsus-materials-' . $tarsus->id, $pageentries, $pager, $config, $counts);
echo $OUTPUT->footer();
