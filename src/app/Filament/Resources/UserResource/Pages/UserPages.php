<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

// ── List ──────────────────────────────────────────────────────────────────────

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New User'),
        ];
    }
}

// ── Create ────────────────────────────────────────────────────────────────────

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['docstatus'] = 0;
        $data['owner']     = auth('pascal')->user()?->email ?? 'system';

        // Auto-generate username from email if not set
        if (empty($data['name'])) {
            $data['name'] = str($data['email'])->before('@')->slug()->value();
        }

        return $data;
    }
}

// ── Edit ──────────────────────────────────────────────────────────────────────

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),

            Actions\Action::make('view_audit')
                ->label('Audit Trail')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->url(fn () => route('filament.admin.pages.audit-trail', [
                    'doctype' => 'User',
                    'docname' => $this->record->name,
                ]))
                ->openUrlInNewTab(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
