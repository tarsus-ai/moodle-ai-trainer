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

/**
 * Reconciles a set of local index rows against the brain in one batch call.
 *
 * The brain reports up to client_interface::BATCH_LIMIT resources per call,
 * which is why a page of the teacher's material list holds the same
 * number: one page costs exactly one call. Only the local index map is
 * written; the brain is never mutated here.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status_sync
{
    /**
     * Statuses worth asking the brain about. Rows with queued local work
     * (pending, pendingreindex, pendingdeletion) are deliberately absent:
     * the brain's answer describes the previous content, and adopting it
     * would cancel the queued work.
     */
    private const SYNCABLE = [
        status::NOT_INDEXED,
        status::PENDING_CONFIRMATION,
        status::INDEXED,
        status::ERROR,
    ];

    /** @var repository The local index map. */
    private repository $repo;
    /** @var pipeline The guardrails every call goes through. */
    private pipeline $pipeline;
    /** @var client_interface The Tarsus client. */
    private client_interface $client;

    /**
     * Set up the sync. Collaborators not given are created; the client is
     * the configured one.
     *
     * @param repository|null $repo The local index map.
     * @param pipeline|null $pipeline The guardrails every call goes through.
     * @param client_interface|null $client The Tarsus client.
     */
    public function __construct(?repository $repo = null, ?pipeline $pipeline = null, ?client_interface $client = null) {
        $this->repo = $repo ?? new repository();
        $this->pipeline = $pipeline ?? new pipeline();
        $this->client = $client ?? client_factory::get_client();
    }

    /**
     * Ask the brain about the given resources and apply the answer locally.
     *
     * @param string[] $resourceids
     * @return array<string, string> Resource ids whose status changed => new status.
     * @throws blocked_exception|transport_exception
     */
    public function refresh(array $resourceids): array {
        $rows = [];
        foreach ($this->repo->get_many($resourceids) as $resourceid => $row) {
            if (in_array($row->status, self::SYNCABLE, true)) {
                $rows[$resourceid] = $row;
            }
        }
        if ($rows === []) {
            return [];
        }

        $changed = [];
        foreach (array_chunk(array_keys($rows), client_interface::BATCH_LIMIT) as $chunk) {
            $checked = $this->pipeline->execute(fn() => $this->client->check_tracked_resources($chunk));
            foreach ($chunk as $resourceid) {
                $row = $rows[$resourceid];
                $reported = $checked[$resourceid]['status'] ?? null;
                if ($reported === null || $reported === $row->status) {
                    continue;
                }
                if ($this->apply($row, (string) $reported)) {
                    $changed[$resourceid] = (string) $reported;
                }
            }
        }

        return $changed;
    }

    /**
     * Adopt the status the brain reported for a row, if it is a valid status
     * and the transition is allowed. A row confirmed as indexed keeps the
     * hash of the content that was sent.
     *
     * @param \stdClass $row The index row.
     * @param string $reported The status the brain reported.
     * @return bool Whether the row changed.
     */
    private function apply(\stdClass $row, string $reported): bool {
        if (!status::is_valid($reported) || !status::can_transition($row->status, $reported)) {
            return false;
        }

        if ($reported === status::INDEXED) {
            // The brain confirmed the content that was uploaded, whose hash
            // is already on the row.
            $this->repo->mark_indexed($row->resourceid, $row->bitref, $row->contenthash);
            return true;
        }

        $this->repo->set_status($row->resourceid, $reported);
        return true;
    }
}
