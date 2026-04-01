<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class AuditTrail extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Audit Trail';
    protected static ?string $slug            = 'audit-trail';
    protected static string  $view            = 'filament.pages.audit-trail';

    public string $doctype = '';
    public string $docname = '';

    public function mount(): void
    {
        $this->doctype = request('doctype', '');
        $this->docname = request('docname', '');
    }

    public function getAuditLogs(): array
    {
        $query = DB::table('pascal_audit_logs')
            ->orderByDesc('created_at')
            ->limit(100);

        if ($this->doctype) $query->where('doctype', $this->doctype);
        if ($this->docname) $query->where('docname', $this->docname);

        return $query->get()->map(fn ($r) => (array) $r)->toArray();
    }

    public function getTitle(): string
    {
        if ($this->doctype && $this->docname) {
            return "Audit Trail: {$this->doctype} / {$this->docname}";
        }
        return 'Audit Trail';
    }
}
