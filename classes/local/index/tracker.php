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

use mod_tarsuscoach\local\client\client_factory;
use mod_tarsuscoach\local\client\client_interface;
use mod_tarsuscoach\local\client\transport_exception;
use mod_tarsuscoach\local\guardrail\blocked_exception;
use mod_tarsuscoach\local\guardrail\pipeline;
use mod_tarsuscoach\local\material\resolver;
use mod_tarsuscoach\local\material\resource_id;
use mod_tarsuscoach\local\support\usage_meter;

/**
 * Runs one unit of indexation work against the brain, synchronously.
 *
 * Calls go through the guardrails pipeline and advance the index state
 * machine. Failures are not absorbed: a guardrail block or transport error
 * propagates to the caller, which decides how to present it and how to
 * revert the row (indexer::execute_now()).
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
class tracker
{
    private repository $repo;
    private pipeline $pipeline;
    private usage_meter $usage;
    private resolver $resolver;
    private client_interface $client;

    public function __construct(
        ?repository $repo = null,
        ?pipeline $pipeline = null,
        ?usage_meter $usage = null,
        ?resolver $resolver = null,
        ?client_interface $client = null
    ) {
        $this->repo = $repo ?? new repository();
        $this->pipeline = $pipeline ?? new pipeline();
        $this->usage = $usage ?? new usage_meter();
        $this->resolver = $resolver ?? new resolver();
        $this->client = $client ?? client_factory::get_client();
    }

    /**
     * Route a row to the handler for its status.
     *
     * @throws blocked_exception|transport_exception
     */
    public function process(\stdClass $row): void {
        switch ($row->status) {
            case status::PENDING:
            case status::PENDING_REINDEX:
                $this->process_upload($row);
                break;
            case status::PENDING_CONFIRMATION:
                $this->process_confirmation($row);
                break;
            case status::PENDING_DELETION:
                $this->process_deletions([$row]);
                break;
        }
    }

    /**
     * Upload (or re-upload) a resource. Tracking the same id twice does not
     * replace the previous bits - the brain keeps both - so the upload is
     * always preceded by an untrack, which clears whatever the brain still
     * holds under this id. Untracking an unknown id is a no-op.
     */
    private function process_upload(\stdClass $row): void {
        // Re-extract from current state so the upload carries what is there now.
        $material = $this->resolver->resolve_by_resource_id($row->resourceid);
        if ($material === null) {
            throw new transport_exception(0, 'the material no longer exists');
        }
        $payload = $this->resolver->payload($material);
        $hash = $material->content_hash();

        $this->pipeline->execute(fn() => $this->client->untrack_resource([$row->resourceid]));

        // The brain now holds nothing under this id. Record that before the
        // upload: should it fail, the row must not keep claiming the
        // previous version is live.
        if ($row->status === status::PENDING_REINDEX) {
            $this->repo->set_status($row->resourceid, status::PENDING);
        }

        $result = $this->pipeline->execute(fn() => $this->client->track_resource($payload));

        $this->usage->record($row->containerref, 0, 0, 'track', (int) ($result['credits'] ?? 0));

        $bitref = (string) ($result['bitref'] ?? '');
        if (($result['status'] ?? '') === status::INDEXED) {
            $this->repo->mark_indexed($row->resourceid, $bitref, $hash);
        } else {
            // Persist the hash of what was actually sent: confirmation
            // compares against this, not against the source by then.
            $this->repo->mark_uploaded($row->resourceid, $hash);
        }
    }

    /**
     * Ask the brain whether an upload has been ingested.
     */
    private function process_confirmation(\stdClass $row): void {
        $result = $this->pipeline->execute(fn() => $this->client->get_resource_status($row->resourceid));

        if (($result['status'] ?? '') === status::INDEXED) {
            $this->repo->mark_indexed($row->resourceid, (string) ($result['bitref'] ?? $row->bitref), $row->contenthash);
        } else if (($result['status'] ?? '') === status::NOT_INDEXED) {
            if (status::can_transition($row->status, status::NOT_INDEXED)) {
                $this->repo->set_status($row->resourceid, status::NOT_INDEXED);
            }
        }
        // Still ingesting: leave the row awaiting confirmation.
    }

    /**
     * Untrack every row of a set in a single call and forget them.
     *
     * @param \stdClass[] $rows Rows marked for deletion.
     * @throws blocked_exception|transport_exception
     */
    public function process_deletions(array $rows): void {
        $resourceids = [];
        foreach ($rows as $row) {
            if ($row !== null) {
                $resourceids[] = $row->resourceid;
            }
        }
        if ($resourceids === []) {
            return;
        }

        $this->pipeline->execute(fn() => $this->client->untrack_resource($resourceids));

        foreach ($resourceids as $resourceid) {
            $this->repo->delete($resourceid);
            inclusions::forget_resource($resourceid);
        }
    }

    /**
     * Remove everything the brain holds for one container, in one call.
     *
     * @throws blocked_exception|transport_exception
     */
    public function untrack_container(int $containerref): void {
        $assetid = resource_id::asset_id($containerref);
        $this->pipeline->execute(fn() => $this->client->untrack_assets([$assetid]));
    }
}
