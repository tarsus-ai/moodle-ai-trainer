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

namespace mod_tarsuscoach\local\index;

use mod_tarsuscoach\local\client\transport_exception;
use mod_tarsuscoach\local\client\configuration_exception;
use mod_tarsuscoach\local\guardrail\blocked_exception;
use mod_tarsuscoach\local\material\resolver;
use mod_tarsuscoach\local\support\platform;

/**
 * Keeps the brain in step with the repository.
 *
 * Content leaving ILIAS must leave the brain with it, and indexed content
 * that changes should reach the brain without anyone pressing Re-index.
 * ILIAS raises no event a repository plugin could listen to for either, so
 * the reconciler compares what each course holds now with what the index
 * map records for it - on the teacher's material page and from the cron
 * job - and acts on the difference:
 *
 * - a course or group that is gone (deleted or in the trash): everything
 *   filed under it is untracked in one call;
 * - material that vanished (deleted, trashed, moved out, link removed):
 *   untracked;
 * - indexed material whose content changed: marked "content changed" and,
 *   within a budget, re-sent.
 *
 * A deletion that cannot be delivered stays recorded as pendingdeletion,
 * the durable record a later run (or the admin's deletions page) retries.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconciler
{
    /** Pending deletions one sweep attempts. */
    public const SWEEP_LIMIT = 20;

    /** @var repository The local index map. */
    private repository $repo;
    /** @var resolver Reads the course material. */
    private resolver $resolver;
    /** @var indexer Re-sends changed material. */
    private indexer $indexer;
    /** @var tracker Runs indexation work against the brain. */
    private tracker $tracker;

    /** @var bool Whether this request has already swept. */
    private static bool $swept = false;

    /**
     * Set up the reconciler. Collaborators not given are created; the
     * indexer shares this repository, resolver and tracker.
     *
     * @param repository|null $repo The local index map.
     * @param resolver|null $resolver Reads the course material.
     * @param tracker|null $tracker Runs indexation work against the brain.
     */
    public function __construct(?repository $repo = null, ?resolver $resolver = null, ?tracker $tracker = null) {
        $this->repo = $repo ?? new repository();
        $this->resolver = $resolver ?? new resolver();
        $this->tracker = $tracker ?? new tracker($this->repo, null, null, $this->resolver);
        $this->indexer = new indexer($this->resolver, $this->repo, $this->tracker);
    }

    /**
     * Reconcile one container.
     *
     * @param int  $uploadbudget How many changed bits to re-send right away.
     * @param bool $deliver      Whether to call the brain now; when false only
     *                           the local map is updated and the deliveries
     *                           wait for the next sweep.
     * @param \mod_tarsuscoach\local\material\material[]|null $materials The container's
     *                           current material, when the caller has it.
     * @return array{withdrawn: int, changed: int, uploaded: int, failed: int, gone: bool}
     */
    public function reconcile_container(
        int $containerref,
        int $uploadbudget = 0,
        bool $deliver = true,
        ?array $materials = null
    ): array {
        $summary = ['withdrawn' => 0, 'changed' => 0, 'uploaded' => 0, 'failed' => 0, 'gone' => false];
        $rows = $this->repo->get_by_container($containerref);
        if ($rows === []) {
            return $summary;
        }

        if (!platform::course_alive($containerref)) {
            $summary['gone'] = true;
            if ($deliver) {
                $summary['withdrawn'] = $this->withdraw_container($containerref, $rows) ? count($rows) : 0;
            }
            return $summary;
        }

        $current = [];
        foreach ($materials ?? $this->resolver->for_container($containerref) as $material) {
            $current[$material->resourceid] = $material;
        }

        $doomed = [];
        $changed = [];
        foreach ($rows as $resourceid => $row) {
            $material = $current[$resourceid] ?? null;
            if ($material === null) {
                if ($row->status === status::NOT_INDEXED) {
                    // Never reached the brain: just forget it.
                    $this->repo->delete($resourceid);
                    inclusions::forget_resource($resourceid);
                    continue;
                }
                if ($row->status === status::PENDING_DELETION) {
                    $doomed[] = $row;
                    continue;
                }
                if (status::can_transition($row->status, status::PENDING_DELETION)) {
                    $this->repo->set_status($resourceid, status::PENDING_DELETION);
                    $doomed[] = $this->repo->get($resourceid);
                }
                continue;
            }
            if ($row->srcref !== $material->srcref) {
                $this->repo->touch_location($resourceid, $material->srcref);
            }
            if ($row->status === status::INDEXED && $row->contenthash !== $material->content_hash()) {
                $this->repo->set_status($resourceid, status::PENDING_REINDEX);
                $changed[] = $resourceid;
            } else if ($row->status === status::PENDING_REINDEX) {
                $changed[] = $resourceid;
            }
        }

        if ($doomed !== [] && $deliver) {
            if ($this->deliver(fn() => $this->tracker->process_deletions(array_values(array_filter($doomed))))) {
                $summary['withdrawn'] = count($doomed);
            } else {
                $summary['failed'] += count($doomed);
            }
        }

        $summary['changed'] = count($changed);
        foreach (array_slice($changed, 0, $deliver ? max(0, $uploadbudget) : 0) as $resourceid) {
            try {
                $this->indexer->execute_now($resourceid);
                $summary['uploaded']++;
            } catch (blocked_exception | transport_exception | configuration_exception $e) {
                // The failed execute_now() reverted the row to "indexed", which
                // is true of the brain but hides that ILIAS has moved on: restore
                // the content-changed status so the teacher sees Re-index.
                $this->indexer->reindex_resource($resourceid);
                $summary['failed']++;
                platform::log('re-sending ' . $resourceid . ' failed: ' . $e->getMessage());
            }
        }

        return $summary;
    }

    /**
     * The connection now leads to another brain: demo mode switched off or
     * on, another server, another brain ID or license key. What the index
     * map records was true of the previous brain only - material "indexed"
     * in the demo never reached any brain. Everything the previous brain
     * held or was confirming is therefore marked to be sent again; the cron
     * job and the teacher's Re-index send it as they send changed content,
     * untracking first, so nothing is doubled. Deletions still outstanding,
     * failed rows and material never indexed keep their state.
     *
     * @return int How many resources will be sent to the new brain.
     */
    public function retarget(): int {
        // Read every affected row first: a row changed below would otherwise
        // be met again under its new status.
        $rows = [];
        foreach ([status::INDEXED, status::PENDING_CONFIRMATION, status::PENDING_REINDEX] as $status) {
            foreach ($this->repo->get_by_status($status) as $row) {
                $rows[$row->resourceid] = $row;
            }
        }
        foreach ($rows as $row) {
            if ($row->status !== status::PENDING_REINDEX) {
                $this->repo->set_status($row->resourceid, status::PENDING_REINDEX);
            }
        }
        $count = count($rows);
        if ($count > 0) {
            platform::log('connection moved to another brain: ' . $count . ' resource(s) marked to be sent again');
        }
        return $count;
    }

    /**
     * Reconcile every container with index rows (cron).
     *
     * @return array{containers: int, withdrawn: int, changed: int, uploaded: int, failed: int, confirmed: int}
     */
    public function reconcile_all(int $uploadbudget): array {
        $total = ['containers' => 0, 'withdrawn' => 0, 'changed' => 0, 'uploaded' => 0, 'failed' => 0, 'confirmed' => 0];
        foreach ($this->repo->containers() as $containerref) {
            $total['containers']++;
            $remaining = max(0, $uploadbudget - $total['uploaded']);
            $summary = $this->reconcile_container($containerref, $remaining);
            foreach (['withdrawn', 'changed', 'uploaded', 'failed'] as $key) {
                $total[$key] += $summary[$key];
            }
        }

        // Rows still awaiting the brain's confirmation: ask in batches.
        $waiting = array_map(static fn($row) => $row->resourceid, $this->repo->get_by_status(status::PENDING_CONFIRMATION));
        if ($waiting !== []) {
            try {
                $total['confirmed'] = count((new status_sync($this->repo))->refresh(array_slice($waiting, 0, 200)));
            } catch (blocked_exception | transport_exception | configuration_exception $e) {
                $total['failed']++;
            }
        }

        $this->sweep(true);
        return $total;
    }

    /**
     * Retry outstanding deletions, at most once per request unless forced.
     *
     * @return int How many rows were cleared.
     */
    public function sweep(bool $force = false): int {
        if (self::$swept && !$force) {
            return 0;
        }
        self::$swept = true;

        $rows = array_slice($this->repo->get_by_status(status::PENDING_DELETION), 0, self::SWEEP_LIMIT);
        if ($rows === []) {
            return 0;
        }

        // Rows of a container that is gone go in one asset call.
        $bycontainer = [];
        foreach ($rows as $row) {
            $bycontainer[$row->containerref][$row->resourceid] = $row;
        }
        $cleared = 0;
        foreach ($bycontainer as $containerref => $containerrows) {
            if (!platform::course_alive((int) $containerref)) {
                if ($this->withdraw_container((int) $containerref, $containerrows)) {
                    $cleared += count($containerrows);
                }
                continue;
            }
            if ($this->deliver(fn() => $this->tracker->process_deletions(array_values($containerrows)))) {
                $cleared += count($containerrows);
            }
        }
        return $cleared;
    }

    /**
     * The rows still awaiting removal from the brain.
     *
     * @return \stdClass[]
     */
    public function outstanding(): array {
        return $this->repo->get_by_status(status::PENDING_DELETION);
    }

    /**
     * Untrack a whole container and forget its rows.
     *
     * @param array<string, \stdClass> $rows
     */
    private function withdraw_container(int $containerref, array $rows): bool {
        foreach ($rows as $resourceid => $row) {
            if ($row->status !== status::PENDING_DELETION && status::can_transition($row->status, status::PENDING_DELETION)) {
                $this->repo->set_status($resourceid, status::PENDING_DELETION);
            }
        }
        if (!$this->deliver(fn() => $this->tracker->untrack_container($containerref))) {
            return false;
        }
        foreach (array_keys($rows) as $resourceid) {
            $this->repo->delete($resourceid);
            inclusions::forget_resource($resourceid);
        }
        return true;
    }

    /**
     * Run an outbound call, never letting its failure escape.
     */
    private function deliver(callable $call): bool {
        try {
            $call();
            return true;
        } catch (blocked_exception | transport_exception | configuration_exception $e) {
            platform::log('could not deliver a deletion: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Allow this request to sweep again (tests).
     */
    public static function reset_sweep(): void {
        self::$swept = false;
    }
}
