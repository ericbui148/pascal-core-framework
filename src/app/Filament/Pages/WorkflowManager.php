<?php

namespace App\Filament\Pages;

use App\Core\FormBuilder\Services\FormBuilderService;
use App\Core\Workflow\Services\WorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class WorkflowManager extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-arrow-path';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Workflow Manager';
    protected static ?string $slug            = 'workflow-manager';
    protected static ?int    $navigationSort  = 11;
    protected static string  $view            = 'filament.pages.workflow-manager';

    public array  $workflows = [];
    public ?array $selected  = null;   // currently viewed workflow
    public array  $doctypes  = [];

    public function mount(): void
    {
        $this->loadWorkflows();
        $this->doctypes = array_map(
            fn ($d) => (array) $d,
            app(FormBuilderService::class)->listDocTypes()
        );
    }

    public function loadWorkflows(): void
    {
        $this->workflows = array_map(
            fn ($w) => (array) $w,
            app(WorkflowService::class)->listWorkflows()
        );
    }

    public function selectWorkflow(int $id): void
    {
        $this->selected = app(WorkflowService::class)->getWorkflow($id);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('New Workflow')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->form([
                    TextInput::make('name')
                        ->label('Workflow Name')
                        ->required()
                        ->placeholder('e.g. Leave Approval'),

                    Select::make('doctype')
                        ->label('DocType')
                        ->required()
                        ->options(fn () => collect(app(FormBuilderService::class)->listDocTypes())
                            ->pluck('name', 'name')->toArray())
                        ->searchable(),

                    Repeater::make('states')
                        ->label('States')
                        ->schema([
                            TextInput::make('state')->required()->placeholder('e.g. Draft'),
                            Select::make('color')->options([
                                'gray'   => 'Gray',
                                'blue'   => 'Blue',
                                'green'  => 'Green',
                                'red'    => 'Red',
                                'yellow' => 'Yellow',
                                'purple' => 'Purple',
                            ])->default('gray'),
                            Select::make('doc_status')->label('Doc Status')->options([
                                '0' => '0 — Draft',
                                '1' => '1 — Submitted',
                                '2' => '2 — Cancelled',
                            ])->default('0'),
                            Toggle::make('is_initial')->label('Initial State')->default(false),
                            Toggle::make('allow_edit')->label('Allow Editing')->default(true),
                        ])
                        ->columns(3)
                        ->defaultItems(2)
                        ->minItems(2),

                    Repeater::make('transitions')
                        ->label('Transitions (action buttons)')
                        ->schema([
                            TextInput::make('from_state')->required()->placeholder('Draft'),
                            TextInput::make('to_state')->required()->placeholder('Pending Approval'),
                            TextInput::make('action')->required()->placeholder('Submit for Approval'),
                            Select::make('action_color')->options([
                                'primary' => 'Blue (primary)',
                                'success' => 'Green (success)',
                                'danger'  => 'Red (danger)',
                                'warning' => 'Yellow (warning)',
                                'gray'    => 'Gray',
                            ])->default('primary'),
                            TextInput::make('allowed_roles')
                                ->label('Allowed Roles (comma-separated)')
                                ->placeholder('user,manager,admin')
                                ->helperText('Use * for any role'),
                            Toggle::make('send_email')->label('Send Email'),
                            Toggle::make('requires_comment')->label('Require Comment'),
                        ])
                        ->columns(2)
                        ->defaultItems(1),
                ])
                ->action(function (array $data) {
                    try {
                        // Parse comma-separated roles into arrays
                        foreach ($data['transitions'] as &$tr) {
                            if (is_string($tr['allowed_roles'])) {
                                $tr['allowed_roles'] = array_map(
                                    'trim',
                                    explode(',', $tr['allowed_roles'])
                                );
                            }
                        }

                        app(WorkflowService::class)->create($data, auth('pascal')->user());
                        $this->loadWorkflows();
                        Notification::make()->title("Workflow [{$data['name']}] created.")->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    public function deleteWorkflow(int $id): void
    {
        try {
            app(WorkflowService::class)->deleteWorkflow($id);
            $this->selected = null;
            $this->loadWorkflows();
            Notification::make()->title('Workflow deleted.')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
