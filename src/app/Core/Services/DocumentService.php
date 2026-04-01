<?php

namespace App\Core\Services;

use App\Core\DocType\DocTypeRegistry;
use App\Core\Events\DocumentCancelled;
use App\Core\Events\DocumentCreated;
use App\Core\Events\DocumentDeleted;
use App\Core\Events\DocumentSubmitted;
use App\Core\Events\DocumentUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use LogicException;

/**
 * DocumentService — Core engine. Every DocType CRUD flows through here.
 *
 * Lifecycle (create):
 *   permission → controller.validate → controller.beforeSave
 *   → DB insert → controller.afterSave → event → audit
 */
class DocumentService
{
    public function __construct(
        protected AuditService      $audit,
        protected PermissionService $permission,
    ) {}

    // ── LIST ──────────────────────────────────────────────────────────────────

    public function list(string $doctype, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $schema = DocTypeRegistry::schema($doctype);
        $query  = DB::table($schema->getTable());

        foreach ($filters as $field => $value) {
            is_array($value)
                ? $query->whereIn($field, $value)
                : $query->where($field, $value);
        }

        $total = (clone $query)->count();
        $rows  = $query->limit($limit)->offset($offset)->get()->map(fn ($r) => (array) $r)->toArray();

        return ['data' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    // ── GET ───────────────────────────────────────────────────────────────────

    public function get(string $doctype, string $name): array
    {
        $schema = DocTypeRegistry::schema($doctype);

        $row = DB::table($schema->getTable())->where('name', $name)->first();

        if (!$row) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "DocType [{$doctype}] record [{$name}] not found."
            );
        }

        return (array) $row;
    }

    // ── CREATE ────────────────────────────────────────────────────────────────

    public function create(string $doctype, array $data, $user): array
    {
        $schema     = DocTypeRegistry::schema($doctype);
        $controller = DocTypeRegistry::controller($doctype);

        $this->permission->check($doctype, 'create', $user);

        // Inject standard fields
        $data['name']       = $data['name']  ?? $this->generateName($doctype);
        $data['docstatus']  = 0;
        $data['owner']      = $user->email ?? 'system';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $controller->validate($data);
        $controller->beforeSave($data, null);

        DB::table($schema->getTable())->insert($data);

        $controller->afterSave($data, 'create');

        $this->audit->log($doctype, $data['name'], 'create', null, $data, $user);
        Event::dispatch(new DocumentCreated($doctype, $data, $user));

        return $data;
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────

    public function update(string $doctype, string $name, array $data, $user): array
    {
        $schema     = DocTypeRegistry::schema($doctype);
        $controller = DocTypeRegistry::controller($doctype);

        $this->permission->check($doctype, 'write', $user);

        $existing = $this->get($doctype, $name);

        if (($existing['docstatus'] ?? 0) === 1) {
            throw new LogicException("Cannot edit a Submitted document. Cancel it first.");
        }

        $data['updated_at'] = now();

        $controller->validate($data);
        $controller->beforeSave($data, $existing);

        DB::table($schema->getTable())->where('name', $name)->update($data);

        $merged = array_merge($existing, $data);
        $diff   = array_diff_assoc($data, $existing);

        $controller->afterSave($merged, 'update');

        $this->audit->log($doctype, $name, 'update', $existing, $merged, $user, $diff);
        Event::dispatch(new DocumentUpdated($doctype, $merged, $user, $diff));

        return $merged;
    }

    // ── SUBMIT ────────────────────────────────────────────────────────────────

    public function submit(string $doctype, string $name, $user): array
    {
        $schema = DocTypeRegistry::schema($doctype);

        if (!$schema->isSubmittable) {
            throw new LogicException("[{$doctype}] is not a submittable DocType.");
        }

        $this->permission->check($doctype, 'submit', $user);

        $doc = $this->get($doctype, $name);

        if (($doc['docstatus'] ?? 0) !== 0) {
            throw new LogicException("Only Draft documents can be Submitted.");
        }

        DB::table($schema->getTable())
            ->where('name', $name)
            ->update(['docstatus' => 1, 'updated_at' => now()]);

        $doc['docstatus'] = 1;

        DocTypeRegistry::controller($doctype)->onSubmit($doc);

        $this->audit->log($doctype, $name, 'submit', null, null, $user);
        Event::dispatch(new DocumentSubmitted($doctype, $doc, $user));

        return $doc;
    }

    // ── CANCEL ────────────────────────────────────────────────────────────────

    public function cancel(string $doctype, string $name, $user): array
    {
        $schema = DocTypeRegistry::schema($doctype);

        $this->permission->check($doctype, 'cancel', $user);

        $doc = $this->get($doctype, $name);

        if (($doc['docstatus'] ?? 0) !== 1) {
            throw new LogicException("Only Submitted documents can be Cancelled.");
        }

        DB::table($schema->getTable())
            ->where('name', $name)
            ->update(['docstatus' => 2, 'updated_at' => now()]);

        $doc['docstatus'] = 2;

        DocTypeRegistry::controller($doctype)->onCancel($doc);

        $this->audit->log($doctype, $name, 'cancel', null, null, $user);
        Event::dispatch(new DocumentCancelled($doctype, $doc, $user));

        return $doc;
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    public function delete(string $doctype, string $name, $user): void
    {
        $schema     = DocTypeRegistry::schema($doctype);
        $controller = DocTypeRegistry::controller($doctype);

        $this->permission->check($doctype, 'delete', $user);

        $doc = $this->get($doctype, $name);

        if (($doc['docstatus'] ?? 0) === 1) {
            throw new LogicException("Cannot delete a Submitted document. Cancel it first.");
        }

        $controller->beforeDelete($doc);

        DB::table($schema->getTable())->where('name', $name)->delete();

        $this->audit->log($doctype, $name, 'delete', $doc, null, $user);
        Event::dispatch(new DocumentDeleted($doctype, $doc, $user));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function generateName(string $doctype): string
    {
        $prefix = strtoupper(str_replace(['_', ' '], '-', $doctype));
        $date   = now()->format('Ymd');
        $seq    = strtoupper(Str::random(4));
        return "{$prefix}-{$date}-{$seq}";
    }
}
