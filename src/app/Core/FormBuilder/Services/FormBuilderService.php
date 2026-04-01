<?php

namespace App\Core\FormBuilder\Services;

use App\Core\DocType\DocTypeRegistry;
use App\Core\DocType\DocTypeSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * FormBuilderService — Create and modify DocTypes at runtime.
 *
 * This is the engine behind the Form Builder UI.
 * No code deployment needed — changes take effect immediately.
 */
class FormBuilderService
{
    // ── DocType CRUD ──────────────────────────────────────────────────────────

    public function createDocType(array $data, mixed $user): array
    {
        $name = $data['name'];

        if (DB::table('pascal_doctypes')->where('name', $name)->exists()) {
            throw ValidationException::withMessages([
                'name' => ["DocType [{$name}] already exists."],
            ]);
        }

        if (!preg_match('/^[A-Za-z][A-Za-z0-9 ]*$/', $name)) {
            throw ValidationException::withMessages([
                'name' => ['DocType name must start with a letter and contain only letters, numbers, and spaces.'],
            ]);
        }

        $doctype = [
            'name'           => $name,
            'module'         => $data['module']         ?? 'Custom',
            'label'          => $data['label']           ?? $name,
            'description'    => $data['description']     ?? null,
            'icon'           => $data['icon']            ?? 'heroicon-o-document',
            'is_submittable' => $data['is_submittable']  ?? false,
            'is_tree'        => $data['is_tree']         ?? false,
            'track_changes'  => $data['track_changes']   ?? true,
            'is_system'      => false,
            'is_custom'      => true,
            'title_field'    => $data['title_field']     ?? null,
            'owner'          => is_object($user) ? ($user->email ?? 'system') : 'system',
            'created_at'     => now(),
            'updated_at'     => now(),
        ];

        DB::table('pascal_doctypes')->insert($doctype);

        // Register in-memory so it's immediately usable
        $this->reloadDocTypeInRegistry($name);

        return $doctype;
    }

    public function updateDocType(string $name, array $data): array
    {
        $doctype = DB::table('pascal_doctypes')->where('name', $name)->first();

        if (!$doctype) {
            throw new \RuntimeException("DocType [{$name}] not found.");
        }

        if ($doctype->is_system && isset($data['name'])) {
            throw ValidationException::withMessages([
                'name' => ['System DocTypes cannot be renamed.'],
            ]);
        }

        $updates = array_filter([
            'label'          => $data['label']          ?? null,
            'description'    => $data['description']    ?? null,
            'icon'           => $data['icon']            ?? null,
            'is_submittable' => $data['is_submittable']  ?? null,
            'track_changes'  => $data['track_changes']   ?? null,
            'title_field'    => $data['title_field']     ?? null,
            'updated_at'     => now(),
        ], fn ($v) => $v !== null);

        DB::table('pascal_doctypes')->where('name', $name)->update($updates);
        $this->reloadDocTypeInRegistry($name);

        return (array) DB::table('pascal_doctypes')->where('name', $name)->first();
    }

    public function deleteDocType(string $name): void
    {
        $doctype = DB::table('pascal_doctypes')->where('name', $name)->first();

        if (!$doctype) {
            throw new \RuntimeException("DocType [{$name}] not found.");
        }

        if ($doctype->is_system) {
            throw ValidationException::withMessages([
                'name' => ['System DocTypes cannot be deleted.'],
            ]);
        }

        // Check if there are records
        $count = DB::table('pascal_custom_data')->where('doctype', $name)->count();
        if ($count > 0) {
            throw ValidationException::withMessages([
                'name' => ["Cannot delete DocType [{$name}]: it has {$count} records. Delete all records first."],
            ]);
        }

        DB::table('pascal_doctypes')->where('name', $name)->delete();
    }

    public function listDocTypes(bool $includeSystem = true): array
    {
        $query = DB::table('pascal_doctypes')->whereNull('deleted_at');

        if (!$includeSystem) {
            $query->where('is_system', false);
        }

        return $query->orderBy('module')->orderBy('name')->get()->toArray();
    }

    public function getDocType(string $name): array
    {
        $doctype = DB::table('pascal_doctypes')->where('name', $name)->first();

        if (!$doctype) {
            throw new \RuntimeException("DocType [{$name}] not found.");
        }

        $fields = DB::table('pascal_docfields')
            ->where('doctype_id', $doctype->id)
            ->orderBy('sort_order')
            ->get()
            ->toArray();

        return array_merge((array) $doctype, ['fields' => $fields]);
    }

    // ── Field CRUD ────────────────────────────────────────────────────────────

    public function addField(string $doctype, array $data): array
    {
        $dt = DB::table('pascal_doctypes')->where('name', $doctype)->firstOrFail();

        $fieldname = $data['fieldname'] ?? Str::snake($data['label']);

        // Ensure fieldname is valid
        $fieldname = preg_replace('/[^a-z0-9_]/', '_', strtolower($fieldname));

        if (DB::table('pascal_docfields')
            ->where('doctype_id', $dt->id)
            ->where('fieldname', $fieldname)
            ->exists()) {
            throw ValidationException::withMessages([
                'fieldname' => ["Field [{$fieldname}] already exists in DocType [{$doctype}]."],
            ]);
        }

        // Get next sort order
        $maxOrder = DB::table('pascal_docfields')
            ->where('doctype_id', $dt->id)
            ->max('sort_order') ?? 0;

        $field = [
            'doctype_id'       => $dt->id,
            'fieldname'        => $fieldname,
            'fieldtype'        => $data['fieldtype'],
            'label'            => $data['label'],
            'required'         => $data['required']          ?? false,
            'in_list_view'     => $data['in_list_view']      ?? false,
            'in_standard_filter' => $data['in_standard_filter'] ?? false,
            'read_only'        => $data['read_only']         ?? false,
            'hidden'           => $data['hidden']            ?? false,
            'bold'             => $data['bold']              ?? false,
            'columns'          => $data['columns']           ?? 1,
            'sort_order'       => $maxOrder + 10,
            'options'          => $data['options']           ?? null,
            'depends_on'       => $data['depends_on']        ?? null,
            'default_value'    => $data['default_value']     ?? null,
            'placeholder'      => $data['placeholder']       ?? null,
            'description'      => $data['description']       ?? null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];

        DB::table('pascal_docfields')->insert($field);
        $this->reloadDocTypeInRegistry($doctype);

        return $field;
    }

    public function updateField(string $doctype, string $fieldname, array $data): array
    {
        $dt    = DB::table('pascal_doctypes')->where('name', $doctype)->firstOrFail();
        $field = DB::table('pascal_docfields')
            ->where('doctype_id', $dt->id)
            ->where('fieldname', $fieldname)
            ->firstOrFail();

        $allowed = ['label', 'required', 'in_list_view', 'in_standard_filter',
                    'read_only', 'hidden', 'bold', 'columns', 'options',
                    'depends_on', 'default_value', 'placeholder', 'description'];

        $updates = array_intersect_key($data, array_flip($allowed));
        $updates['updated_at'] = now();

        DB::table('pascal_docfields')->where('id', $field->id)->update($updates);
        $this->reloadDocTypeInRegistry($doctype);

        return (array) DB::table('pascal_docfields')->where('id', $field->id)->first();
    }

    public function deleteField(string $doctype, string $fieldname): void
    {
        $dt = DB::table('pascal_doctypes')->where('name', $doctype)->firstOrFail();

        DB::table('pascal_docfields')
            ->where('doctype_id', $dt->id)
            ->where('fieldname', $fieldname)
            ->delete();

        $this->reloadDocTypeInRegistry($doctype);
    }

    /**
     * Reorder fields via drag & drop.
     * $order = ['fieldname1' => 10, 'fieldname2' => 20, ...]
     */
    public function reorderFields(string $doctype, array $order): void
    {
        $dt = DB::table('pascal_doctypes')->where('name', $doctype)->firstOrFail();

        foreach ($order as $fieldname => $sortOrder) {
            DB::table('pascal_docfields')
                ->where('doctype_id', $dt->id)
                ->where('fieldname', $fieldname)
                ->update(['sort_order' => $sortOrder, 'updated_at' => now()]);
        }

        $this->reloadDocTypeInRegistry($doctype);
    }

    // ── Registry sync ─────────────────────────────────────────────────────────

    /**
     * (Re)load a DocType from the DB into the in-memory DocTypeRegistry.
     * Called after every schema change so API calls see the new schema immediately.
     */
    public function reloadDocTypeInRegistry(string $name): void
    {
        $row = DB::table('pascal_doctypes')->where('name', $name)->first();
        if (!$row) return;

        DocTypeRegistry::register($name, null, [
            'module'         => $row->module,
            'is_submittable' => (bool) $row->is_submittable,
            'is_tree'        => (bool) $row->is_tree,
            'track_changes'  => (bool) $row->track_changes,
        ]);
    }

    /**
     * Boot all DB-persisted DocTypes into the registry on application start.
     * Called from CoreServiceProvider::boot().
     */
    public function bootAllFromDatabase(): void
    {
        try {
            $doctypes = DB::table('pascal_doctypes')
                ->whereNull('deleted_at')
                ->get();

            foreach ($doctypes as $dt) {
                $this->reloadDocTypeInRegistry($dt->name);
            }
        } catch (\Exception) {
            // DB might not exist yet during initial migration
        }
    }

    // ── Schema helpers ────────────────────────────────────────────────────────

    public function getFieldTypes(): array
    {
        return [
            'Basic' => [
                'Data'        => ['icon' => 'heroicon-o-pencil',       'label' => 'Text (single line)'],
                'Text Editor' => ['icon' => 'heroicon-o-document-text', 'label' => 'Text (rich editor)'],
                'Int'         => ['icon' => 'heroicon-o-hashtag',       'label' => 'Integer'],
                'Float'       => ['icon' => 'heroicon-o-calculator',    'label' => 'Decimal'],
                'Currency'    => ['icon' => 'heroicon-o-currency-dollar','label' => 'Currency'],
                'Percent'     => ['icon' => 'heroicon-o-chart-bar',     'label' => 'Percent'],
                'Check'       => ['icon' => 'heroicon-o-check-circle',  'label' => 'Checkbox'],
            ],
            'Date & Time' => [
                'Date'     => ['icon' => 'heroicon-o-calendar',  'label' => 'Date'],
                'Datetime' => ['icon' => 'heroicon-o-clock',     'label' => 'Date + Time'],
                'Time'     => ['icon' => 'heroicon-o-clock',     'label' => 'Time'],
            ],
            'Choice' => [
                'Select' => ['icon' => 'heroicon-o-chevron-down', 'label' => 'Dropdown'],
                'Link'   => ['icon' => 'heroicon-o-link',         'label' => 'Link (FK to another DocType)'],
            ],
            'File' => [
                'Attach' => ['icon' => 'heroicon-o-paper-clip', 'label' => 'File attachment'],
            ],
            'Layout' => [
                'Section Break' => ['icon' => 'heroicon-o-minus', 'label' => 'Section divider'],
                'Column Break'  => ['icon' => 'heroicon-o-view-columns', 'label' => 'Column divider'],
                'HTML'          => ['icon' => 'heroicon-o-code-bracket', 'label' => 'Custom HTML'],
            ],
            'Child' => [
                'Table' => ['icon' => 'heroicon-o-table-cells', 'label' => 'Child table (related records)'],
            ],
        ];
    }
}
