<?php

namespace App\Core\DocType;

use Illuminate\Support\Str;

class DocTypeSchema
{
    public readonly string $name;
    public readonly string $module;
    public readonly bool   $isSubmittable;
    public readonly bool   $isTree;
    public readonly bool   $trackChanges;
    public readonly bool   $isSingleton;

    public function __construct(array $data)
    {
        $this->name          = $data['name'];
        $this->module        = $data['module']         ?? 'Core';
        $this->isSubmittable = $data['is_submittable'] ?? false;
        $this->isTree        = $data['is_tree']        ?? false;
        $this->trackChanges  = $data['track_changes']  ?? true;
        $this->isSingleton   = $data['is_singleton']   ?? false;
    }

    /** DB table: plural snake_case  e.g. "User" → "users", "SalesInvoice" → "sales_invoices" */
    public function getTable(): string
    {
        return Str::snake(Str::plural($this->name));
    }
}
