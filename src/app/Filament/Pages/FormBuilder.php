<?php

namespace App\Filament\Pages;

use App\Core\FormBuilder\Services\FormBuilderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\On;

class FormBuilder extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Form Builder';
    protected static ?string $slug            = 'form-builder';
    protected static ?int    $navigationSort  = 10;
    protected static string  $view            = 'filament.pages.form-builder';

    // State
    public ?string $selectedDocType = null;
    public array   $doctype         = [];
    public array   $fields          = [];
    public array   $doctypes        = [];

    // New DocType form
    public string  $newDtName          = '';
    public string  $newDtModule        = 'Custom';
    public string  $newDtLabel         = '';
    public bool    $newDtSubmittable   = false;

    // New Field form
    public string  $newFieldLabel      = '';
    public string  $newFieldType       = 'Data';
    public bool    $newFieldRequired   = false;
    public string  $newFieldOptions    = '';

    public function mount(): void
    {
        $this->loadDocTypes();
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_doctype')
                ->label('New DocType')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->form([
                    TextInput::make('name')
                        ->label('DocType Name')
                        ->required()
                        ->placeholder('e.g. Customer, SalesOrder')
                        ->helperText('Start with uppercase, spaces allowed: "Sales Invoice"'),
                    TextInput::make('label')
                        ->label('Display Label')
                        ->placeholder('Leave blank to use name'),
                    TextInput::make('module')
                        ->label('Module')
                        ->default('Custom'),
                    Toggle::make('is_submittable')
                        ->label('Submittable (has Submit/Cancel lifecycle)')
                        ->default(false),
                    Toggle::make('track_changes')
                        ->label('Track Changes (audit trail)')
                        ->default(true),
                ])
                ->action(function (array $data) {
                    try {
                        app(FormBuilderService::class)->createDocType($data, auth('pascal')->user());
                        $this->loadDocTypes();
                        $this->selectDocType($data['name']);
                        Notification::make()->title("DocType [{$data['name']}] created.")->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    // ── DocType selection ──────────────────────────────────────────────────────

    public function selectDocType(string $name): void
    {
        $this->selectedDocType = $name;
        $full = app(FormBuilderService::class)->getDocType($name);
        $this->doctype = (array) $full;
        $this->fields  = array_map(fn ($f) => (array) $f, $full['fields'] ?? []);
    }

    public function loadDocTypes(): void
    {
        $this->doctypes = array_map(
            fn ($d) => (array) $d,
            app(FormBuilderService::class)->listDocTypes()
        );
    }

    // ── Field management ───────────────────────────────────────────────────────

    public function addField(): void
    {
        if (!$this->selectedDocType || !$this->newFieldLabel) return;

        try {
            app(FormBuilderService::class)->addField($this->selectedDocType, [
                'label'     => $this->newFieldLabel,
                'fieldtype' => $this->newFieldType,
                'required'  => $this->newFieldRequired,
                'options'   => $this->newFieldOptions ?: null,
            ]);

            $this->selectDocType($this->selectedDocType);
            $this->newFieldLabel   = '';
            $this->newFieldOptions = '';
            $this->newFieldRequired = false;

            Notification::make()->title('Field added.')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function deleteField(string $fieldname): void
    {
        try {
            app(FormBuilderService::class)->deleteField($this->selectedDocType, $fieldname);
            $this->selectDocType($this->selectedDocType);
            Notification::make()->title("Field [{$fieldname}] deleted.")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function moveFieldUp(int $index): void
    {
        if ($index === 0) return;
        $order = $this->buildReorderMap();
        // Swap with previous
        $keys = array_keys($order);
        [$order[$keys[$index]], $order[$keys[$index - 1]]] = [$order[$keys[$index - 1]], $order[$keys[$index]]];
        app(FormBuilderService::class)->reorderFields($this->selectedDocType, $order);
        $this->selectDocType($this->selectedDocType);
    }

    public function moveFieldDown(int $index): void
    {
        if ($index >= count($this->fields) - 1) return;
        $order = $this->buildReorderMap();
        $keys  = array_keys($order);
        [$order[$keys[$index]], $order[$keys[$index + 1]]] = [$order[$keys[$index + 1]], $order[$keys[$index]]];
        app(FormBuilderService::class)->reorderFields($this->selectedDocType, $order);
        $this->selectDocType($this->selectedDocType);
    }

    public function toggleRequired(string $fieldname, bool $required): void
    {
        app(FormBuilderService::class)->updateField($this->selectedDocType, $fieldname, ['required' => $required]);
        $this->selectDocType($this->selectedDocType);
    }

    public function toggleListView(string $fieldname, bool $value): void
    {
        app(FormBuilderService::class)->updateField($this->selectedDocType, $fieldname, ['in_list_view' => $value]);
        $this->selectDocType($this->selectedDocType);
    }

    public function deleteDocType(): void
    {
        try {
            app(FormBuilderService::class)->deleteDocType($this->selectedDocType);
            $this->selectedDocType = null;
            $this->doctype = [];
            $this->fields  = [];
            $this->loadDocTypes();
            Notification::make()->title('DocType deleted.')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    private function buildReorderMap(): array
    {
        $order = [];
        foreach ($this->fields as $i => $field) {
            $order[$field['fieldname']] = ($i + 1) * 10;
        }
        return $order;
    }

    public function getFieldTypeOptions(): array
    {
        return [
            'Data' => 'Data (text)', 'Text Editor' => 'Text Editor',
            'Int' => 'Integer', 'Float' => 'Float', 'Currency' => 'Currency', 'Percent' => 'Percent',
            'Date' => 'Date', 'Datetime' => 'Date & Time', 'Time' => 'Time',
            'Check' => 'Checkbox', 'Select' => 'Select (dropdown)', 'Link' => 'Link (FK)',
            'Attach' => 'File Attach', 'Table' => 'Child Table',
            'Section Break' => 'Section Break', 'Column Break' => 'Column Break',
        ];
    }
}
