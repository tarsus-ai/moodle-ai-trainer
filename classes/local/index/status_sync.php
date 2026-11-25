<?php

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
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
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

    private repository $repo;
    private pipeline $pipeline;
    private client_interface $client;

    public function __construct(?repository $repo = null, ?pipeline $pipeline = null, ?client_interface $client = null)
    {
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
    public function refresh(array $resourceids): array
    {
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

    private function apply(\stdClass $row, string $reported): bool
    {
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
