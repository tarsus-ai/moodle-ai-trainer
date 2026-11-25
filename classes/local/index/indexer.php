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
use mod_tarsuscoach\local\guardrail\blocked_exception;
use mod_tarsuscoach\local\material\material;
use mod_tarsuscoach\local\material\resolver;
use mod_tarsuscoach\local\material\resource_id;

/**
 * Turns teacher actions into indexation work.
 *
 * The decision half: resolve the material, compare its content hash with
 * the index map, and only mark a bit for an API call when it is new or its
 * content changed - an unchanged bit costs nothing. The marked work then
 * runs synchronously via execute_now(): there is no background queue, so a
 * failure reverts the row to its previous stable state and surfaces to
 * the caller.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
class indexer
{
    private resolver $resolver;
    private repository $repo;
    private tracker $tracker;

    public function __construct(?resolver $resolver = null, ?repository $repo = null, ?tracker $tracker = null) {
        $this->resolver = $resolver ?? new resolver();
        $this->repo = $repo ?? new repository();
        $this->tracker = $tracker ?? new tracker($this->repo, null, null, $this->resolver);
    }

    /**
     * Index one material by its resource id (opt-in per-item action).
     */
    public function index_resource(string $resourceid): bool {
        $material = $this->resolver->resolve_by_resource_id($resourceid);
        if ($material === null) {
            return false;
        }
        return $this->index_material($material, false);
    }

    /**
     * Force a re-index of one resource.
     */
    public function reindex_resource(string $resourceid): bool {
        $row = $this->repo->get($resourceid);
        if ($row === null) {
            // Not tracked yet: a re-index request degrades to a first index.
            return $this->index_resource($resourceid);
        }

        if ($row->status === status::ERROR) {
            $this->repo->reset_attempts($resourceid);
        }

        if (!status::can_transition($row->status, status::PENDING_REINDEX)) {
            return false;
        }

        $this->repo->set_status($resourceid, status::PENDING_REINDEX);
        return true;
    }

    /**
     * Mark one resource for removal from the brain.
     */
    public function remove_resource(string $resourceid): bool {
        $row = $this->repo->get($resourceid);
        if ($row === null || !status::can_transition($row->status, status::PENDING_DELETION)) {
            return false;
        }

        $this->repo->set_status($resourceid, status::PENDING_DELETION);
        return true;
    }

    /**
     * Retry one resource stuck in the error state.
     */
    public function retry_resource(string $resourceid): bool {
        $row = $this->repo->get($resourceid);
        if ($row === null || $row->status !== status::ERROR) {
            return false;
        }

        $this->repo->reset_attempts($resourceid);
        $this->repo->set_status($resourceid, status::PENDING);
        return true;
    }

    /**
     * Run the pending work of a resource now, reverting on failure. The row
     * was just marked (pending / pendingreindex / pendingdeletion); when a
     * guardrail or the transport refuses, it returns to its previous
     * stable state - there is no queue to pick it up later - and the
     * failure propagates so the caller can show it.
     *
     * @throws blocked_exception|transport_exception
     */
    public function execute_now(string $resourceid): void {
        $row = $this->repo->get($resourceid);
        if ($row === null) {
            return;
        }

        try {
            $this->tracker->process($row);
        } catch (blocked_exception | transport_exception $e) {
            // Re-read: a re-index advances the row to pending once the old
            // content is untracked, and the revert must act on where the
            // sequence actually failed.
            $this->revert($this->repo->get($resourceid) ?? $row);
            throw $e;
        }
    }

    private function revert(\stdClass $row): void {
        switch ($row->status) {
            case status::PENDING:
                // A first upload failed: back to square one.
                $this->repo->set_status($row->resourceid, status::NOT_INDEXED);
                break;
            case status::PENDING_REINDEX:
            case status::PENDING_DELETION:
                // The old content is still live in the brain.
                $this->repo->set_status($row->resourceid, status::INDEXED);
                break;
        }
    }

    /**
     * Decide and mark one material.
     *
     * @param bool $onlytracked When true, skip materials that have no row yet.
     */
    public function index_material(material $material, bool $onlytracked = false): bool {
        $existing = $this->repo->get($material->resourceid);
        if ($onlytracked && $existing === null) {
            return false;
        }

        $row = $existing ?? $this->repo->ensure(
            $material->resourceid,
            $material->containerref,
            resource_id::asset_id($material->containerref),
            $material->srctype,
            $material->srcref
        );

        $target = $this->target_status($row, $material->content_hash());
        if ($target === null) {
            return false;
        }

        $this->repo->set_status($material->resourceid, $target);
        return true;
    }

    private function target_status(\stdClass $row, string $hash): ?string {
        if ($row->status === status::NOT_INDEXED || $row->status === status::ERROR) {
            return status::PENDING;
        }
        if ($row->status === status::INDEXED && $row->contenthash !== $hash) {
            return status::PENDING_REINDEX;
        }
        return null;
    }
}
