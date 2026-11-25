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

namespace mod_tarsuscoach\local\service;

use mod_tarsuscoach\local\client\client_interface;
use mod_tarsuscoach\local\client\configuration_exception;
use mod_tarsuscoach\local\client\transport_exception;
use mod_tarsuscoach\local\guardrail\blocked_exception;
use mod_tarsuscoach\local\index\inclusions;
use mod_tarsuscoach\local\index\indexer;
use mod_tarsuscoach\local\index\reconciler;
use mod_tarsuscoach\local\index\repository;
use mod_tarsuscoach\local\index\status;
use mod_tarsuscoach\local\index\status_sync;
use mod_tarsuscoach\local\material\resolver;
use mod_tarsuscoach\local\material\resource_id;
use mod_tarsuscoach\local\output\presenter;
use mod_tarsuscoach\local\support\platform;

/**
 * The teacher's side: per-resource index actions, the inclusion switch and
 * the page sync, all for the course a Tarsus object lives in.
 *
 * Every resource id that arrives from the browser is checked to belong to
 * this object's course and to point at material the teacher can read -
 * isolation by id on the shared brain.
 *
 * @package    mod_tarsuscoach
 * @copyright  2026 Tarsus GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager_api
{
    /** @var string[] */
    public const ACTIONS = ['index', 'reindex', 'remove', 'retry'];

    /** @var int The Tarsus activity. */
    private int $objid;
    /** @var int The course the activity lives in. */
    private int $containerref;
    /** @var repository The local index map: what the brain holds of the material. */
    private repository $repo;
    /** @var resolver Finds the course's material and turns it into bits for the brain. */
    private resolver $resolver;

    /**
     * Set up the material actions for one activity and its course.
     *
     * @param int $objid The Tarsus activity.
     * @param int $containerref The course the activity lives in.
     */
    public function __construct(int $objid, int $containerref) {
        $this->objid = $objid;
        $this->containerref = $containerref;
        $this->repo = new repository();
        $this->resolver = new resolver();
    }

    /**
     * Whether a resource id from the browser may be acted on here.
     */
    private function allowed(string $resourceid): bool {
        $parts = resource_id::parse($resourceid);
        if ($parts === null || !resource_id::is_local($resourceid) || $parts['containerref'] !== $this->containerref) {
            return false;
        }
        $row = $this->repo->get($resourceid);
        if ($row !== null && !platform::cm_alive($parts['refid'])) {
            // Material that is gone may still be withdrawn.
            return true;
        }
        return $this->resolver->in_scope($parts['refid'], $this->containerref)
            && platform::cm_visible_to_user($parts['refid'], $this->containerref);
    }

    /**
     * One resource as the materials list shows it: its index status, the
     * actions it allows and whether this activity includes it.
     *
     * @param string $resourceid The resource.
     * @return array<string, mixed>
     */
    private function present(string $resourceid): array {
        $row = $this->repo->get($resourceid);
        $status = $row->status ?? status::NOT_INDEXED;
        return presenter::present($resourceid, $status, (int) ($row->timemodified ?? 0))
            + ['included' => in_array($resourceid, inclusions::for_object($this->objid), true)];
    }

    /**
     * Apply one action to one resource, synchronously.
     *
     * @return array<string, mixed> {ok, error?, row}
     */
    public function action(string $resourceid, string $action): array {
        if (!in_array($action, self::ACTIONS, true) || !$this->allowed($resourceid)) {
            return ['ok' => false, 'error' => platform::txt('invalid_resource')];
        }

        $indexer = new indexer($this->resolver, $this->repo);
        switch ($action) {
            case 'index':
                $marked = $indexer->index_resource($resourceid);
                break;
            case 'reindex':
                $marked = $indexer->reindex_resource($resourceid);
                break;
            case 'remove':
                $marked = $indexer->remove_resource($resourceid);
                break;
            default:
                $marked = $indexer->retry_resource($resourceid);
                break;
        }
        if (!$marked) {
            return ['ok' => false, 'error' => platform::txt('action_failed'), 'row' => $this->present($resourceid)];
        }

        // The whole action runs in this request: allow a slow file upload
        // to finish (the client caps uploads at 300 seconds).
        if (function_exists('set_time_limit')) {
            @set_time_limit(330);
        }
        $error = '';
        try {
            $indexer->execute_now($resourceid);
        } catch (blocked_exception $e) {
            $error = platform::txt('guardrail_blocked_' . $e->reason);
        } catch (transport_exception $e) {
            platform::log($e->getMessage());
            $error = platform::txt('transport_error');
        } catch (configuration_exception $e) {
            $error = platform::txt($e->langkey);
        }

        return ['ok' => $error === '', 'error' => $error, 'row' => $this->present($resourceid)];
    }

    /**
     * Include or exclude a resource from this object's scope.
     *
     * @return array<string, mixed>
     */
    public function set_included(string $resourceid, bool $included): array {
        if (!$this->allowed($resourceid)) {
            return ['ok' => false, 'error' => platform::txt('invalid_resource')];
        }
        if ($included) {
            // Only content the brain holds may be put in scope.
            $row = $this->repo->get($resourceid);
            if ($row === null || $row->status !== status::INDEXED) {
                return ['ok' => false, 'error' => platform::txt('include_not_indexed'), 'row' => $this->present($resourceid)];
            }
            inclusions::include($this->objid, $resourceid);
        } else {
            inclusions::exclude($this->objid, $resourceid);
        }
        return ['ok' => true, 'error' => '', 'row' => $this->present($resourceid)];
    }

    /**
     * Reconcile one page of the list against the brain in a single call,
     * then finish deletions that could not be delivered earlier.
     *
     * @param string[] $resourceids
     * @return array<string, mixed>
     */
    public function sync(array $resourceids): array {
        $resourceids = array_values(array_unique(array_map('strval', $resourceids)));
        if (count($resourceids) > client_interface::BATCH_LIMIT) {
            return ['ok' => false, 'error' => platform::txt('too_many_resources', ['max' => client_interface::BATCH_LIMIT])];
        }
        foreach ($resourceids as $resourceid) {
            if (!$this->allowed($resourceid)) {
                return ['ok' => false, 'error' => platform::txt('invalid_resource')];
            }
        }

        $error = '';
        try {
            (new status_sync($this->repo))->refresh($resourceids);
        } catch (blocked_exception $e) {
            $error = platform::txt('guardrail_blocked_' . $e->reason);
        } catch (transport_exception $e) {
            platform::log($e->getMessage());
            $error = platform::txt('transport_error');
        } catch (configuration_exception $e) {
            $error = platform::txt($e->langkey);
        }

        // Deliveries that failed when content left the course have nowhere
        // else to be retried from; this is the moment to finish them.
        (new reconciler($this->repo, $this->resolver))->sweep();

        $rows = [];
        foreach ($resourceids as $resourceid) {
            $rows[] = $this->present($resourceid);
        }
        return ['ok' => $error === '', 'error' => $error, 'rows' => $rows];
    }
}
