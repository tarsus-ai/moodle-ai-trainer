<?php

namespace mod_tarsus\local\Index;

use mod_tarsus\local\Support\Platform;

/**
 * Typed access to the local index map: the source of truth for what the
 * brain holds of this installation's material.
 *
 * Status reads are database reads - zero API calls on render. Every
 * transition is validated against Status, so a row can only move through
 * legal states.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
class Repository
{
    public const TABLE = 'tarsus_idx';

    public function get(string $resourceid): ?\stdClass
    {
        $row = Platform::db()->get_record(self::TABLE, ['resource_id' => $resourceid]);
        return $row ? self::map((array) $row) : null;
    }

    /**
     * Ensure a row exists, creating it as notindexed (idempotent).
     */
    public function ensure(string $resourceid, int $containerref, string $assetid, string $srctype, int $srcref): \stdClass
    {
        $existing = $this->get($resourceid);
        if ($existing !== null) {
            return $existing;
        }

        $now = time();
        Platform::db()->insert_record(self::TABLE, (object) [
            'resource_id' => $resourceid,
            'asset_id' => $assetid,
            'container_ref' => $containerref,
            'src_type' => $srctype,
            'src_ref' => $srcref,
            'content_hash' => null,
            'bit_ref' => null,
            'idx_status' => Status::NOT_INDEXED,
            'attempts' => 0,
            'next_retry' => 0,
            'created' => $now,
            'modified' => $now,
        ]);

        return $this->requireRow($resourceid);
    }

    /**
     * Move a row to a new status, validating the transition.
     */
    public function setStatus(string $resourceid, string $newstatus): void
    {
        $row = $this->requireRow($resourceid);

        if (!Status::canTransition($row->status, $newstatus)) {
            throw new \LogicException("Illegal index status transition {$row->status} -> {$newstatus} for {$resourceid}");
        }

        $this->write($resourceid, ['idx_status' => ['text', $newstatus]]);
    }

    /**
     * Mark a resource as live in the brain, storing its bit ref and hash.
     */
    public function markIndexed(string $resourceid, string $bitref, string $contenthash): void
    {
        $row = $this->requireRow($resourceid);
        if (!Status::canTransition($row->status, Status::INDEXED)) {
            throw new \LogicException("Illegal index status transition {$row->status} -> indexed for {$resourceid}");
        }
        $this->write($resourceid, [
            'idx_status' => ['text', Status::INDEXED],
            'bit_ref' => ['text', $bitref],
            'content_hash' => ['text', $contenthash],
            'attempts' => ['integer', 0],
            'next_retry' => ['integer', 0],
        ]);
    }

    /**
     * Record a successful upload: await confirmation, remembering the hash
     * of what was actually sent (a later confirmation must not recompute it
     * from current ILIAS state - the source may have changed meanwhile).
     */
    public function markUploaded(string $resourceid, string $contenthash): void
    {
        $row = $this->requireRow($resourceid);
        if (!Status::canTransition($row->status, Status::PENDING_CONFIRMATION)) {
            throw new \LogicException("Illegal index status transition {$row->status} -> pendingconfirmation for {$resourceid}");
        }
        $this->write($resourceid, [
            'idx_status' => ['text', Status::PENDING_CONFIRMATION],
            'content_hash' => ['text', $contenthash],
            'attempts' => ['integer', 0],
            'next_retry' => ['integer', 0],
        ]);
    }

    /**
     * Record a failed attempt: schedule the next retry, or fail to error.
     */
    public function recordFailure(string $resourceid): \stdClass
    {
        $row = $this->requireRow($resourceid);
        $attempts = $row->attempts + 1;
        $now = time();
        $values = ['attempts' => ['integer', $attempts]];

        if (Backoff::exhausted($attempts)) {
            if (Status::canTransition($row->status, Status::ERROR)) {
                $values['idx_status'] = ['text', Status::ERROR];
            }
            $values['next_retry'] = ['integer', 0];
        } else {
            $values['next_retry'] = ['integer', $now + Backoff::delay($attempts, true)];
        }

        $this->write($resourceid, $values);
        return $this->requireRow($resourceid);
    }

    public function resetAttempts(string $resourceid): void
    {
        $this->requireRow($resourceid);
        $this->write($resourceid, ['attempts' => ['integer', 0], 'next_retry' => ['integer', 0]]);
    }

    /**
     * Keep a row's location in step with its material (the container is
     * part of the id, the source ref is not when an object is moved).
     */
    public function touchLocation(string $resourceid, int $srcref): void
    {
        Platform::db()->set_field(self::TABLE, 'src_ref', $srcref, ['resource_id' => $resourceid]);
    }

    /**
     * @return \stdClass[]
     */
    public function getByStatus(string $status): array
    {
        return $this->select('idx_status = ?', [$status]);
    }

    /**
     * @return \stdClass[] Every row of one container, keyed by resource id.
     */
    public function getByContainer(int $containerref): array
    {
        $rows = [];
        foreach ($this->select('container_ref = ?', [$containerref]) as $row) {
            $rows[$row->resourceid] = $row;
        }
        return $rows;
    }

    /**
     * @param string[] $resourceids
     * @return array<string, \stdClass> Keyed by resource id.
     */
    public function getMany(array $resourceids): array
    {
        $resourceids = array_values(array_unique(array_filter(array_map('strval', $resourceids), 'strlen')));
        if ($resourceids === []) {
            return [];
        }
        $rows = [];
        foreach (array_chunk($resourceids, 200) as $chunk) {
            [$insql, $params] = Platform::db()->get_in_or_equal($chunk);
            foreach ($this->select('resource_id ' . $insql, $params) as $row) {
                $rows[$row->resourceid] = $row;
            }
        }
        return $rows;
    }

    /**
     * @return int[] The distinct containers with index rows.
     */
    public function containers(): array
    {
        return array_map('intval', Platform::db()->get_fieldset_sql('SELECT DISTINCT container_ref FROM {' . self::TABLE . '}'));
    }

    /**
     * @return array<string, int> Status => count, over all rows.
     */
    public function countByStatus(): array
    {
        $counts = [];
        $menu = Platform::db()->get_records_sql_menu('SELECT idx_status, COUNT(1) FROM {' . self::TABLE . '} GROUP BY idx_status');
        foreach ($menu as $status => $count) {
            $counts[(string) $status] = (int) $count;
        }
        return $counts;
    }

    public function delete(string $resourceid): void
    {
        Platform::db()->delete_records(self::TABLE, ['resource_id' => $resourceid]);
    }

    /**
     * @return \stdClass[]
     */
    private function select(string $where, array $params): array
    {
        $rows = [];
        foreach (Platform::db()->get_records_select(self::TABLE, $where, $params, 'id') as $row) {
            $rows[] = self::map((array) $row);
        }
        return $rows;
    }

    /**
     * @param array<string, array{0: string, 1: mixed}> $values
     */
    private function write(string $resourceid, array $values): void
    {
        $id = Platform::db()->get_field(self::TABLE, 'id', ['resource_id' => $resourceid]);
        if (!$id) {
            return;
        }
        // Values come as [type, value] pairs, as the shared code writes them.
        $record = (object) ['id' => (int) $id, 'modified' => time()];
        foreach ($values as $name => $value) {
            $record->$name = is_array($value) ? $value[1] : $value;
        }
        Platform::db()->update_record(self::TABLE, $record);
    }

    private function requireRow(string $resourceid): \stdClass
    {
        $row = $this->get($resourceid);
        if ($row === null) {
            throw new \LogicException("No index row for resource {$resourceid}");
        }
        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function map(array $row): \stdClass
    {
        return (object) [
            'id' => (int) $row['id'],
            'resourceid' => (string) $row['resource_id'],
            'assetid' => (string) $row['asset_id'],
            'containerref' => (int) $row['container_ref'],
            'srctype' => (string) $row['src_type'],
            'srcref' => (int) $row['src_ref'],
            'contenthash' => (string) ($row['content_hash'] ?? ''),
            'bitref' => (string) ($row['bit_ref'] ?? ''),
            'status' => (string) $row['idx_status'],
            'attempts' => (int) $row['attempts'],
            'nextretry' => (int) $row['next_retry'],
            'timecreated' => (int) $row['created'],
            'timemodified' => (int) $row['modified'],
        ];
    }
}
